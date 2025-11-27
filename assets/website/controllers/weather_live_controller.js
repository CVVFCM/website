import {Controller} from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    static values = {
        mercureUrl: String,
    };

    component;
    eventSource;

    async connect() {
        this.component = await getComponent(this.element);

        this.eventSource = new EventSource(this.mercureUrlValue);
        this.eventSource.onmessage = async (e) => {
            const message = JSON.parse(e.data);

            if ('/weather/live' !== message.type) {
                return;
            }

            this.component.render();
        }
    }

    disconnect() {
        this.eventSource.close();
    }
}
