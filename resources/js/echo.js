import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Intelligente Umgebungserkennung anhand der URL im Browser
// Das verhindert, dass man beim lokalen Kompilieren (.env) ständig die Werte tauschen muss.
const isLocal = window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost' || window.location.hostname.endsWith('.test');

const dynamicWsHost = isLocal ? window.location.hostname : 'ws.mein-seelenfunke.de';
const dynamicWsPort = isLocal ? 6001 : 443;
const dynamicForceTLS = !isLocal; // TLS (https) nur auf Stage/Live erzwingen

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY || 'seelenfunke-key',
    cluster: process.env.MIX_PUSHER_APP_CLUSTER || 'mt1',
    wsHost: dynamicWsHost,
    wsPort: dynamicWsPort,
    wssPort: dynamicWsPort,
    forceTLS: dynamicForceTLS,
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
});
