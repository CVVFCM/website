import { Controller } from '@hotwired/stimulus';

import '../forgie/launcher.css';

/**
 * Floating Forgie launcher (site-wide). On first open: fetches the embedded Forgie
 * fragment (/forgie?embedded — the response also sets the Mercure auth cookie), injects
 * it into a right-side pane and lazy-loads the forgie entrypoint (chat controller + CSS).
 * Closing only hides the pane, so the conversation and its EventSource survive a reopen.
 * The launcher is a plain link to /forgie: no-JS users and fetch failures fall back to it.
 */
const INVITE_KEY = 'forgie-invite';
const INVITE_MAX_VISITS = 3;
const INVITE_TTL = 30 * 24 * 60 * 60 * 1000; // 1 month

export default class extends Controller {
    /**
     * @type {HTMLElement|null}
     */
    pane = null;

    /**
     * @type {HTMLElement|null}
     */
    backdrop = null;

    /**
     * @type {HTMLElement|null}
     */
    invite = null;

    loading = false;

    connect() {
        this.maybeShowInvite();
    }

    /**
     * Invitation bubble next to the launcher, on the visitor's 3 first visits.
     * Counter in localStorage ({count, since}), one visit per browser session
     * (sessionStorage guard), counter resets after a month.
     */
    maybeShowInvite() {
        let state;
        try {
            state = JSON.parse(localStorage.getItem(INVITE_KEY));
            if (null === state || Date.now() - state.since > INVITE_TTL) {
                state = { count: 0, since: Date.now() };
            }

            if (!sessionStorage.getItem(INVITE_KEY)) {
                if (state.count >= INVITE_MAX_VISITS) {
                    return;
                }
                state.count += 1;
                sessionStorage.setItem(INVITE_KEY, '1');
                localStorage.setItem(INVITE_KEY, JSON.stringify(state));
            }
            // Same-session page loads don't re-count; the bubble keeps showing for the
            // whole session that consumed one of the 3 visits.
        } catch {
            // Storage unavailable (private mode, quota): no way to cap at 3, skip the bubble.
            return;
        }

        this.invite = document.createElement('button');
        this.invite.type = 'button';
        this.invite.className = 'ForgieLauncher__invite';
        this.invite.textContent = 'Une question sur le club, la météo, les régates ? Demande-moi !';
        this.invite.addEventListener('click', () => this.open());
        document.body.append(this.invite);
    }

    hideInvite() {
        this.invite?.remove();
        this.invite = null;
    }

    async open() {
        this.hideInvite();

        if (this.loading) {
            return;
        }

        if (this.pane) {
            this.show();

            return;
        }

        this.loading = true;
        this.element.classList.add('ForgieLauncher--loading');

        try {
            const response = await fetch('/forgie?embedded');
            if (!response.ok) {
                throw new Error(`Unexpected status ${response.status}`);
            }
            const fragment = await response.text();

            // The chat entrypoint (controller + forgie.css) loads only now, on demand.
            // Specifier goes through a variable: a literal would make AssetMapper's static
            // analyzer register forgie as a dependency and detect a circular reference
            // (controllers.js → this file → forgie → bootstrap → controllers.js). The
            // browser importmap resolves it at runtime just fine.
            const entrypoint = 'forgie';
            await import(entrypoint);

            this.buildPane(fragment);
            this.show();
        } catch {
            // Network/JS failure: fall back to the standalone page the link points to.
            window.location.assign(this.element.href);
        } finally {
            this.loading = false;
            this.element.classList.remove('ForgieLauncher--loading');
        }
    }

    close() {
        if (!this.pane || this.pane.classList.contains('ForgiePane--closed')) {
            return;
        }

        this.pane.classList.add('ForgiePane--closed');
        this.backdrop.classList.add('ForgiePane__backdrop--closed');
        this.element.focus();
    }

    show() {
        this.pane.classList.remove('ForgiePane--closed');
        this.backdrop.classList.remove('ForgiePane__backdrop--closed');
        this.pane.querySelector('.Forgie__input')?.focus();
    }

    buildPane(fragment) {
        this.backdrop = document.createElement('div');
        this.backdrop.className = 'ForgiePane__backdrop ForgiePane__backdrop--closed';
        this.backdrop.addEventListener('click', () => this.close());

        this.pane = document.createElement('aside');
        this.pane.className = 'ForgiePane ForgiePane--closed';
        this.pane.setAttribute('role', 'dialog');
        this.pane.setAttribute('aria-modal', 'true');
        this.pane.setAttribute('aria-label', 'Forgie, l\'assistant du club');
        this.pane.innerHTML = fragment;

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'ForgiePane__close';
        closeButton.setAttribute('aria-label', 'Fermer Forgie');
        closeButton.textContent = '✕';
        closeButton.addEventListener('click', () => this.close());
        this.pane.prepend(closeButton);

        document.body.append(this.backdrop, this.pane);

        // Let the browser paint the closed state first so the slide-in transitions.
        this.pane.getBoundingClientRect();
    }
}
