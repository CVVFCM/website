import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle', 'menu'];

    connect() {
        this.element.classList.add('MainNavigation--close');

        const siteHeader = this.element.closest('.SiteHeader');
        this._stickyThreshold = parseFloat(getComputedStyle(siteHeader).top);
        this._onScroll = () => {
            this.element.classList.toggle('MainNavigation--stuck', window.scrollY >= this._stickyThreshold);
        };
        window.addEventListener('scroll', this._onScroll, { passive: true });
        this._onScroll();

        this._onClickOutside = (event) => {
            if (!this.element.classList.contains('MainNavigation--open')) return;
            if (!this.element.contains(event.target) || event.target === siteHeader) {
                this.close();
            }
        };
        document.addEventListener('click', this._onClickOutside);
    }

    disconnect() {
        window.removeEventListener('scroll', this._onScroll);
        document.removeEventListener('click', this._onClickOutside);
    }

    toggle(event) {
        event.preventDefault();
        if (this.element.classList.contains('MainNavigation--open')) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        this.element.classList.add('MainNavigation--open');
        this.element.classList.remove('MainNavigation--close');
        this.toggleTarget.setAttribute('aria-expanded', 'true');
        this.toggleTarget.setAttribute('aria-label', 'Fermer le menu de navigation');
    }

    close() {
        this.element.classList.remove('MainNavigation--open');
        this.element.classList.add('MainNavigation--close');
        this.toggleTarget.setAttribute('aria-expanded', 'false');
        this.toggleTarget.setAttribute('aria-label', 'Ouvrir le menu de navigation');
        this.toggleTarget.focus();
    }

    toggleSection(event) {
        // Category items are links (to their first page); while the mobile menu is
        // open a tap must keep toggling the section instead of navigating.
        if (this.element.classList.contains('MainNavigation--open')) {
            event.preventDefault();
        }
        const li = event.currentTarget.closest('li');
        li.classList.toggle('MainNavigation__item--open');
    }

    closeOnEscape(event) {
        if (event.key === 'Escape' && this.element.classList.contains('MainNavigation--open')) {
            this.close();
        }
    }
}
