import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER || 'mt1',
    wsHost: process.env.MIX_PUSHER_HOST && process.env.MIX_PUSHER_HOST !== '127.0.0.1' ? process.env.MIX_PUSHER_HOST : window.location.hostname,
    wsPort: process.env.MIX_PUSHER_PORT || 6001,
    wssPort: process.env.MIX_PUSHER_PORT || 6001,
    forceTLS: (process.env.MIX_PUSHER_SCHEME === 'https') || window.location.protocol === 'https:',
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
});
