import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;

        Echo: Echo<any>;
    }
}

Pusher.logToConsole = false;
window.Pusher = Pusher;

const isReverbEnabled = import.meta.env.VITE_REVERB_ENABLED === 'true';

const createDummyEcho = () => {
    const dummyChannel: any = {
        listen: () => dummyChannel,
        stopListening: () => dummyChannel,
        notification: () => dummyChannel,
        listenForWhisper: () => dummyChannel,
        whisper: () => dummyChannel,
        subscribed: () => dummyChannel,
        error: () => dummyChannel,
    };

    return {
        channel: () => dummyChannel,
        private: () => dummyChannel,
        encryptedPrivate: () => dummyChannel,
        presence: () => dummyChannel,
        leave: () => {},
        leaveChannel: () => {},
        disconnect: () => {},
        connector: { pusher: null },
    };
};

if (isReverbEnabled && import.meta.env.VITE_REVERB_APP_KEY) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
        enabledTransports: ['ws'],
        autoConnect: false,
    });
} else {
    window.Echo = createDummyEcho() as any;
}

export default window.Echo;
