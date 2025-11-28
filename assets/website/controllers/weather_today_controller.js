import {Controller} from '@hotwired/stimulus';
import {getComponent} from "@symfony/ux-live-component";

export default class extends Controller {
    static values = {
        mercureUrl: String,
    }

    static outlets = ['mercure'];

    component;

    async initialize() {
        this.component = await getComponent(this.element);

        this.mercureOutlet.addListener('/weather/forecast', async () => {
            console.log('Refreshing weather forecast');
            await this.component.render();
        });
    }
}
