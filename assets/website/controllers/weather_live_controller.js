import {Controller} from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    static values = {
        mercureUrl: String,
    };

    component;
    eventSource;

    connect() {
        this.eventSource = new EventSource(this.mercureUrlValue);
        this.eventSource.onmessage = async (e) => {
            const message = JSON.parse(e.data);

            if ('/weather/live' !== message.type) {
                return;
            }

            (await getComponent(this.element)).render();
        }
    }

    disconnect() {
        this.eventSource.close();
    }
}
