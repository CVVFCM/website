import { Controller } from '@hotwired/stimulus';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

// GFM (tables…) on; breaks: single newlines become <br>, chat-style.
marked.use({ gfm: true, breaks: true });

// Image upload limits — mirrored server-side by ForgieUploadController.
const MAX_UPLOAD_BYTES = 16 * 1024 * 1024;
const ALLOWED_UPLOAD_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

// Module-level (once): harden links coming out of the markdown — new tab, no opener,
// http(s)/mailto/relative hrefs only.
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if ('A' !== node.tagName) {
        return;
    }

    const href = node.getAttribute('href') ?? '';
    if (!/^(https?:|mailto:|\/|#)/i.test(href)) {
        node.removeAttribute('href');

        return;
    }

    node.setAttribute('target', '_blank');
    node.setAttribute('rel', 'noopener noreferrer');
});

/**
 * Forgie chat: POSTs the user message to the API (202) and streams the answer
 * from Mercure ({delta} frames, then {done} — or {error}). Assistant bubbles
 * are rendered as Markdown (marked, GFM) and sanitized (DOMPurify).
 */
export default class extends Controller {
    static values = {
        hubUrl: String,
    };

    static targets = ['messages', 'input', 'submit', 'file', 'preview'];

    conversationId = crypto.randomUUID();

    /**
     * Image staged for the next send (via the attach button).
     *
     * @type {File|null}
     */
    stagedFile = null;

    /**
     * @type {EventSource|null}
     */
    eventSource = null;

    /**
     * @type {HTMLElement|null}
     */
    pendingBubble = null;

    /**
     * Raw markdown accumulated for the in-flight answer.
     */
    buffer = '';

    connect() {
        const url = new URL(this.hubUrlValue);
        url.searchParams.append('topic', `/forgie/conversations/${this.conversationId}`);

        this.eventSource = new EventSource(url);
        this.eventSource.onmessage = (event) => this.onUpdate(JSON.parse(event.data));
    }

    disconnect() {
        this.eventSource?.close();
        this.eventSource = null;
    }

    pickFile() {
        this.fileTarget.click();
    }

    fileSelected() {
        const file = this.fileTarget.files?.[0];
        if (!file) {
            return;
        }

        if (!ALLOWED_UPLOAD_TYPES.includes(file.type)) {
            this.showPreviewError('Format non supporté (JPEG, PNG, WebP ou GIF).');
        } else if (file.size > MAX_UPLOAD_BYTES) {
            this.showPreviewError('Image trop volumineuse (16 Mo maximum).');
        } else {
            this.stagedFile = file;
            this.renderPreview(file);

            return;
        }

        this.fileTarget.value = '';
    }

    async send(event) {
        event.preventDefault();

        const message = this.inputTarget.value.trim();
        const file = this.stagedFile;
        if ((!message && !file) || this.pendingBubble) {
            return;
        }

        this.element.classList.add('Forgie--started');
        this.appendBubble('user', message, file);
        this.inputTarget.value = '';
        this.clearStagedFile();
        this.buffer = '';
        this.pendingBubble = this.appendBubble('forgie', '');
        this.pendingBubble.classList.add('Forgie__bubble--pending');
        this.submitTarget.disabled = true;

        let uploadId = null;
        if (file) {
            uploadId = await this.uploadImage(file);
            if (!uploadId) {
                return; // failPending already shown by uploadImage
            }
        }

        const body = { conversationId: this.conversationId, message };
        if (uploadId) {
            body.uploadId = uploadId;
        }

        const response = await fetch('/api/forgie/messages', {
            method: 'POST',
            headers: { 'Content-Type': 'application/ld+json' },
            body: JSON.stringify(body),
        });

        if (response.status !== 202) {
            this.failPending(response.status === 429
                ? 'Doucement moussaillon ! Réessaie dans une minute.'
                : 'Forgie est indisponible pour le moment.');
        }
    }

    /**
     * Uploads the staged image and returns its id, or null on failure (after showing
     * an error on the pending bubble).
     */
    async uploadImage(file) {
        try {
            const form = new FormData();
            form.append('conversationId', this.conversationId);
            form.append('file', file);

            const response = await fetch('/api/forgie/uploads', { method: 'POST', body: form });
            if (!response.ok) {
                this.failPending(response.status === 429
                    ? 'Doucement moussaillon ! Réessaie dans une minute.'
                    : "L'image n'a pas pu être envoyée.");

                return null;
            }

            return (await response.json()).id;
        } catch {
            this.failPending("L'image n'a pas pu être envoyée.");

            return null;
        }
    }

    onUpdate(data) {
        if (!this.pendingBubble) {
            return;
        }

        if (data.error) {
            this.failPending('Forgie est indisponible pour le moment.');

            return;
        }

        if (data.done) {
            this.pendingBubble.classList.remove('Forgie__bubble--pending');
            this.pendingBubble = null;
            this.submitTarget.disabled = false;
            this.inputTarget.focus();

            return;
        }

        if (data.delta) {
            // Only auto-follow when the reader is already near the bottom — never
            // yank them back down while they scrolled up to re-read something.
            const el = this.messagesTarget;
            const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 64;

            this.buffer += data.delta;
            this.pendingBubble.innerHTML = this.renderMarkdown(this.buffer);

            if (nearBottom) {
                el.scrollTop = el.scrollHeight;
            }
        }
    }

    renderMarkdown(text) {
        return DOMPurify.sanitize(marked.parse(text), {
            ALLOWED_TAGS: [
                'p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'code', 'pre', 'h1', 'h2', 'h3',
                'table', 'thead', 'tbody', 'tr', 'th', 'td', 'del', 'blockquote', 'hr',
            ],
            ALLOWED_ATTR: ['href', 'target', 'rel', 'align'],
        });
    }

    appendBubble(author, text, file = null) {
        const bubble = document.createElement('p');
        bubble.className = `Forgie__bubble Forgie__bubble--${author}`;
        bubble.textContent = text;

        if (file) {
            const img = document.createElement('img');
            img.className = 'Forgie__bubbleImage';
            img.src = URL.createObjectURL(file);
            img.alt = file.name;
            img.addEventListener('load', () => URL.revokeObjectURL(img.src));
            bubble.appendChild(img);
        }

        this.messagesTarget.appendChild(bubble);
        this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight;

        return bubble;
    }

    renderPreview(file) {
        const preview = this.previewTarget;
        preview.textContent = '';
        preview.classList.remove('Forgie__preview--error');

        const thumb = document.createElement('img');
        thumb.className = 'Forgie__previewThumb';
        thumb.src = URL.createObjectURL(file);
        thumb.alt = file.name;
        thumb.addEventListener('load', () => URL.revokeObjectURL(thumb.src));

        const name = document.createElement('span');
        name.className = 'Forgie__previewName';
        name.textContent = file.name;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'Forgie__previewRemove';
        remove.setAttribute('aria-label', "Retirer l'image");
        remove.textContent = '×';
        remove.addEventListener('click', () => this.clearStagedFile());

        preview.append(thumb, name, remove);
        preview.hidden = false;
    }

    showPreviewError(text) {
        const preview = this.previewTarget;
        preview.textContent = text;
        preview.classList.add('Forgie__preview--error');
        preview.hidden = false;
    }

    clearStagedFile() {
        this.stagedFile = null;
        this.fileTarget.value = '';
        const preview = this.previewTarget;
        preview.textContent = '';
        preview.classList.remove('Forgie__preview--error');
        preview.hidden = true;
    }

    failPending(text) {
        if (this.pendingBubble) {
            this.pendingBubble.textContent = text;
            this.pendingBubble.classList.remove('Forgie__bubble--pending');
            this.pendingBubble.classList.add('Forgie__bubble--error');
            this.pendingBubble = null;
        }
        this.submitTarget.disabled = false;
    }
}
