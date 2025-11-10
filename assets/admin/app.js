// Add project specific javascript code and import of additional bundles here:
import {listToolbarActionRegistry} from "sulu-admin-bundle/views";
import GetNewFacebookToken from "./listToolbarActions/GetNewFacebookToken";

listToolbarActionRegistry.add('app.facebook_tokens.get_new', GetNewFacebookToken);
