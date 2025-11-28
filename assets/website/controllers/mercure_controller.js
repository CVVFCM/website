import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        mercureUrl: String,
    }

    eventSource;
    listeners = [];

    async initialize() {
        this.eventSource = new EventSource(this.mercureUrlValue);
        this.eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.listeners.forEach(({eventType, callback}) => {
                if (data.type === eventType) {
                    callback(data);
                }
            });
        }
    }

    disconnect() {
        this.eventSource.close();
    }

    addListener(eventType, callback) {
        this.listeners.push({eventType, callback});
    }
}
