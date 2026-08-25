/**
 * Web Push bootstrap.
 *
 * Registers the service worker, and exposes an Alpine store the
 * `<x-push-toggle>` component binds to. The browser only grants
 * permission from a user gesture, so nothing here asks for it on load —
 * it just reports the current state and re-syncs an existing
 * subscription, which is how a rotated endpoint reaches the server.
 */

const vapidPublicKey = document.querySelector('meta[name="vapid-public-key"]')?.content || '';
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

/** VAPID keys travel as base64url; `subscribe()` wants raw bytes. */
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);

    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
}

function post(url, body, method = 'POST') {
    return fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
        },
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : undefined,
    });
}

async function registration() {
    if (!supported) {
        return null;
    }

    return navigator.serviceWorker.register('/sw.js', { scope: '/' });
}

export function createPushStore() {
    return {
        supported,
        // 'default' | 'granted' | 'denied' | 'unsupported'
        permission: supported ? Notification.permission : 'unsupported',
        subscribed: false,
        busy: false,
        message: '',

        async init() {
            if (!supported || !vapidPublicKey) {
                this.permission = 'unsupported';

                return;
            }

            const worker = await registration().catch(() => null);

            if (!worker) {
                this.permission = 'unsupported';

                return;
            }

            const existing = await worker.pushManager.getSubscription();

            this.subscribed = existing !== null;

            // The push service may have handed the worker a fresh endpoint
            // while the app was closed; make sure the server has it.
            if (existing && Notification.permission === 'granted') {
                await post('/push-subscriptions', existing.toJSON()).catch(() => undefined);
            }
        },

        async enable() {
            if (!supported || this.busy) {
                return;
            }

            this.busy = true;
            this.message = '';

            try {
                const permission = await Notification.requestPermission();

                this.permission = permission;

                if (permission !== 'granted') {
                    this.message = 'Permissão negada pelo navegador.';

                    return;
                }

                const worker = await navigator.serviceWorker.ready;

                const subscription =
                    (await worker.pushManager.getSubscription()) ||
                    (await worker.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
                    }));

                const response = await post('/push-subscriptions', subscription.toJSON());

                if (!response.ok) {
                    throw new Error('rejected');
                }

                this.subscribed = true;
                this.message = 'Notificações ativadas neste dispositivo.';
            } catch (error) {
                this.message = 'Não foi possível ativar as notificações.';
            } finally {
                this.busy = false;
            }
        },

        async disable() {
            if (!supported || this.busy) {
                return;
            }

            this.busy = true;

            try {
                const worker = await navigator.serviceWorker.ready;
                const subscription = await worker.pushManager.getSubscription();

                if (subscription) {
                    await post('/push-subscriptions', { endpoint: subscription.endpoint }, 'DELETE');
                    await subscription.unsubscribe();
                }

                this.subscribed = false;
                this.message = 'Notificações desativadas neste dispositivo.';
            } catch (error) {
                this.message = 'Não foi possível desativar as notificações.';
            } finally {
                this.busy = false;
            }
        },

        async sendTest() {
            if (this.busy) {
                return;
            }

            this.busy = true;

            try {
                const response = await post('/push-subscriptions/teste');

                this.message = response.ok
                    ? 'Enviamos um teste. Deve chegar em instantes.'
                    : 'O servidor ainda não tem as chaves VAPID configuradas.';
            } finally {
                this.busy = false;
            }
        },
    };
}
