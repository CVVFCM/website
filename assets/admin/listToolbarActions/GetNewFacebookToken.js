import {AbstractListToolbarAction} from 'sulu-admin-bundle/views';
import Requester from 'sulu-admin-bundle/services/Requester';
import router from 'sulu-admin-bundle/services/Router';

export default class GetNewFacebookToken extends AbstractListToolbarAction {
    getToolbarItemConfig() {
        return {
            type: 'button',
            label: 'Récupérer un jeton',
            icon: 'su-exchange-up',
            disabled: false,
            onClick: this.handleClick.bind(this),
        };
    }

    handleClick = () => {
        FB.getLoginStatus(function(response) {
            console.log(response);
            if ('connected' === response.status) {
                this.retrieveToken(response);

                return;
            }

            FB.login(
                function(response) { this.retrieveToken(response); }.bind(this),
                {
                    scope: 'instagram_basic,pages_read_engagement,pages_show_list',
                    return_scopes: true,
                    enable_profile_selector: true,
                }
            );
        }.bind(this));
    };

    retrieveToken = response => {
        if ('connected' !== response.status) {
            console.error('Not connected', response);

            return;
        }

        Requester
            .post('/admin/api/facebook-tokens/requests/longs-liveds', response.authResponse)
            .then(function() {
                location.reload();
            }.bind(this));
    }
}
