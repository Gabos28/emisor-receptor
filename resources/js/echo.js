import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const localHosts = new Set(['localhost', '127.0.0.1', '::1', '[::1]']);
const pageUsesLocalHost = localHosts.has(window.location.hostname);
const reverbUsesLocalHost = localHosts.has(import.meta.env.VITE_REVERB_HOST);

if (pageUsesLocalHost || !reverbUsesLocalHost) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
