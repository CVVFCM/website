import { Controller } from '@hotwired/stimulus';

/**
 * Forgie chat: POSTs the user message to the API (202) and streams the answer
 * from Mercure ({delta} frames, then {done} — or {error}).
 */
export default class extends Controller {
    static values = {
        hubUrl: String,
    };

    static targets = ['messages', 'input', 'submit'];

    conversationId = crypto.randomUUID();

    /**
     * @type {EventSource|null}
     */
    eventSource = null;

    /**
     * @type {HTMLElement|null}
     */
    pendingBubble = null;

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

    async send(event) {
        event.preventDefault();

        const message = this.inputTarget.value.trim();
        if (!message || this.pendingBubble) {
            return;
        }

        this.appendBubble('user', message);
        this.inputTarget.value = '';
        this.pendingBubble = this.appendBubble('forgie', '');
        this.pendingBubble.classList.add('Forgie__bubble--pending');
        this.submitTarget.disabled = true;

        const response = await fetch('/api/forgie/messages', {
            method: 'POST',
            headers: { 'Content-Type': 'application/ld+json' },
            body: JSON.stringify({ conversationId: this.conversationId, message }),
        });

        if (response.status !== 202) {
            this.failPending(response.status === 429
                ? 'Doucement moussaillon ! Réessaie dans une minute.'
                : 'Forgie est indisponible pour le moment.');
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
            this.pendingBubble.textContent += data.delta;
            this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight;
        }
    }

    appendBubble(author, text) {
        const bubble = document.createElement('p');
        bubble.className = `Forgie__bubble Forgie__bubble--${author}`;
        bubble.textContent = text;
        this.messagesTarget.appendChild(bubble);
        this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight;

        return bubble;
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
