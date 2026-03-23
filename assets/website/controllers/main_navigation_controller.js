import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle', 'menu'];

    toggle() {
        if (this.element.classList.contains('is-open')) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        this.element.classList.add('is-open');
        this.toggleTarget.setAttribute('aria-expanded', 'true');
        this.toggleTarget.setAttribute('aria-label', 'Fermer le menu de navigation');
    }

    close() {
        this.element.classList.remove('is-open');
        this.toggleTarget.setAttribute('aria-expanded', 'false');
        this.toggleTarget.setAttribute('aria-label', 'Ouvrir le menu de navigation');
        this.toggleTarget.focus();
    }

    closeOnEscape(event) {
        if (event.key === 'Escape' && this.element.classList.contains('is-open')) {
            this.close();
        }
    }
}
