import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle', 'menu', 'search', 'searchToggle', 'searchInput'];

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
            const outside = !this.element.contains(event.target) || event.target === siteHeader;
            if (!outside) return;
            if (this.element.classList.contains('MainNavigation--open')) this.close();
            if (this.element.classList.contains('MainNavigation--searching')) this.closeSearch();
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

    /**
     * The magnifier is a plain link to the search page, so it keeps working without JS.
     * With JS we intercept the click and reveal the inline field instead.
     */
    toggleSearch(event) {
        if (!this.hasSearchTarget) return;
        event.preventDefault();

        if (this.element.classList.contains('MainNavigation--searching')) {
            this.closeSearch();
        } else {
            this.openSearch();
        }
    }

    openSearch() {
        this.element.classList.add('MainNavigation--searching');
        this.searchTarget.hidden = false;
        this.searchToggleTarget.setAttribute('aria-expanded', 'true');
        this.searchInputTarget.focus();
    }

    closeSearch() {
        this.element.classList.remove('MainNavigation--searching');
        this.searchTarget.hidden = true;
        this.searchToggleTarget.setAttribute('aria-expanded', 'false');
    }

    toggleSection(event) {
        const li = event.currentTarget.closest('li');
        const open = li.classList.toggle('MainNavigation__item--open');
        event.currentTarget.setAttribute('aria-expanded', String(open));
    }

    closeOnEscape(event) {
        if (event.key !== 'Escape') return;

        if (this.element.classList.contains('MainNavigation--searching')) {
            this.closeSearch();
            this.searchToggleTarget.focus();
        }

        if (this.element.classList.contains('MainNavigation--open')) {
            this.close();
        }
    }
}
