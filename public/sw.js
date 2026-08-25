/**
 * Futebas service worker.
 *
 * Deliberately minimal: it exists to receive push messages and to make the
 * app installable, not to cache the app. The pages are server-rendered and
 * highly dynamic (who is confirmed, who applied to an SOS), so serving
 * them from a cache would show stale squads.
 */

const OFFLINE_URL = '/offline.html';
const CACHE = 'futebas-shell-v1';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll([OFFLINE_URL, '/images/icons/icon-192.png']))
            .then(() => self.skipWaiting())
            .catch(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

/**
 * Only navigations get a fallback, and only when the network fails
 * outright — everything else goes straight to the network.
 */
self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
});

/**
 * The payload is the JSON produced by App\Services\WebPush\PushMessage.
 * A push with no body still shows something, since some services send
 * empty pings to keep a subscription alive.
 */
self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = { title: 'Futebas', body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'Futebas';
    const url = payload.url || '/dashboard';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: payload.body || '',
            icon: payload.icon || '/images/icons/icon-192.png',
            badge: payload.badge || '/images/icons/badge-72.png',
            tag: payload.tag,
            renotify: Boolean(payload.tag),
            requireInteraction: Boolean(payload.requireInteraction),
            actions: payload.actions || [],
            vibrate: [80, 40, 80],
            data: { ...(payload.data || {}), url },
        }),
    );
});

/**
 * Focus an already-open tab when there is one, so tapping a notification
 * never leaves the user with three copies of the app open.
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = (event.notification.data && event.notification.data.url) || '/dashboard';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if ('focus' in client) {
                    client.navigate(url);

                    return client.focus();
                }
            }

            return self.clients.openWindow(url);
        }),
    );
});

/**
 * Push services rotate subscriptions occasionally. Re-subscribe here with
 * the same VAPID key so delivery keeps working; the *server* learns the
 * new endpoint from resources/js/push.js, which re-syncs whatever
 * subscription exists on every page load. A worker cannot POST it itself
 * without a CSRF token.
 */
self.addEventListener('pushsubscriptionchange', (event) => {
    const applicationServerKey = event.oldSubscription
        ? event.oldSubscription.options.applicationServerKey
        : undefined;

    if (!applicationServerKey) {
        return;
    }

    event.waitUntil(
        self.registration.pushManager
            .subscribe({ userVisibleOnly: true, applicationServerKey })
            .catch(() => undefined),
    );
});
