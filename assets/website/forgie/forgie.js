import './forgie.css';
import { app } from '../bootstrap.js';
import ChatController from './chat_controller.js';

app.register('forgie-chat', ChatController);
