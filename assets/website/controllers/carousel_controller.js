import { Controller } from '@hotwired/stimulus';
import Splide from '@splidejs/splide';
import '@splidejs/splide/dist/css/splide.min.css';

export default class extends Controller {
    connect() {
        this.splide = new Splide(this.element, {
            type: 'loop',
            perPage: 1,
            perMove: 1,
            pagination: false,
            // The images are decorative content, not a task: don't move them under the reader.
            autoplay: false,
        }).mount();
    }

    disconnect() {
        this.splide?.destroy();
    }
}
