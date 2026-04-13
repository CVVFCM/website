import { Controller } from '@hotwired/stimulus';
import Splide from '@splidejs/splide';
import '@splidejs/splide/dist/css/splide.min.css';

export default class extends Controller {
    connect() {
        this.splide = new Splide(this.element, {
            type: 'loop',
            perPage: 3,
            perMove: 1,
            gap: '1rem',
            breakpoints: {
                48: {
                    perPage: 1,
                },
                64: {
                    perPage: 2,
                },
            },
        }).mount();
    }

    disconnect() {
        this.splide?.destroy();
    }
}
