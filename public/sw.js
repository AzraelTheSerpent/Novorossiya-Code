/* Код Новороссии · service worker
 * Публичные страницы и каталог доступны офлайн, кабинет и аутентификация — нет.
 */
const VERSION = 'kod-shell-v7';
const PAGES = 'kod-pages-v1';
const DATA = 'kod-data-v1';
const TILES = 'kod-tiles-v1';
const FONTS = 'kod-fonts-v1';
const KEEP = [VERSION, PAGES, DATA, TILES, FONTS];

const PRECACHE = [
    '/offline.html',
    '/theme.js?v=1',
    '/redesign.css?v=1',
    '/assets/brand/logo-light.webp',
    '/assets/brand/logo-dark.webp',
    '/maps/region-ru-positron.json',
    '/maps/region-ru-dark.json',
    '/assets/city-night.jpg',
    '/pwa.css',
    '/pwa.js',
    '/manifest.webmanifest',
    '/icon.svg',
    '/icon-192.png',
    '/icon-512.png',
    '/icon-maskable-192.png',
    '/icon-maskable-512.png',
    '/apple-touch-icon.png',
];

const CACHEABLE_PAGE = /^\/(?:places|place\/|afisha|news|post\/|event\/)/;
const PRIVATE_PAGE = /^\/(?:admin|account|login|logout|register|password|forgot-password|reset-password|security|profile|api|platform\/(?:workspace|journeys|favorites))(?:\/|$)/;
const ASSET = /^\/(?:(?:build|assets|storage|maps)\/|manifest\.webmanifest$|(?:pwa|theme)\.(?:css|js)$|redesign\.css$|offline\.html$|icon\.svg$|icon-(?:192|512)\.png$|icon-maskable-(?:192|512)\.png$|apple-touch-icon\.png$|favicon\.ico$)/;

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(VERSION);
        await Promise.allSettled(PRECACHE.map(url => cache.add(new Request(url, {cache: 'reload'}))));
        await precacheShell();
        await precacheData();
    })());
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys
            .filter(key => key.startsWith('kod-') && !KEEP.includes(key))
            .map(key => caches.delete(key)));
        await self.clients.claim();
    })());
});

self.addEventListener('message', event => {
    if (!event.data) return;

    if (event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
        return;
    }

    if (event.data.type === 'CACHE_ASSETS') {
        event.waitUntil(cacheAssets(event.data.urls, event.ports[0]));
    }
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;
    if (request.headers.has('Authorization')) return;

    const url = new URL(request.url);

    // Inertia page visits are JSON and must never be replayed from cache.
    if (request.headers.has('X-Inertia')) return;

    // Accounts and staff areas always go to the network.
    if (url.origin === self.location.origin && PRIVATE_PAGE.test(url.pathname)) return;
    // Weather must never look current when the device is offline.
    if (url.origin === self.location.origin && url.pathname === '/platform/weather') return;

    if (request.mode === 'navigate') {
        // External links should fail in the browser, not show our offline page.
        if (url.origin !== self.location.origin) return;
        event.respondWith(navigationResponse(request, url));
        return;
    }

    if (url.origin === self.location.origin && url.pathname === '/platform/data') {
        event.respondWith(dataResponse(request));
        return;
    }

    if (url.origin === self.location.origin && ASSET.test(url.pathname)) {
        event.respondWith(assetResponse(request, event));
        return;
    }

    if (url.hostname === 'tile.openstreetmap.org') {
        event.respondWith(cacheFirst(request, TILES, 180));
        return;
    }

    if (url.hostname === 'tiles.openfreemap.org') {
        event.respondWith(cacheFirst(request, TILES, 180));
        return;
    }

    if (url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com') {
        event.respondWith(cacheFirst(request, FONTS, 40));
    }
});

/* ---------- стратегии ---------- */

async function navigationResponse(request, url) {
    const sameOrigin = url.origin === self.location.origin;
    const isPublic = sameOrigin && !PRIVATE_PAGE.test(url.pathname);

    try {
        const response = await fetch(request);
        if (isPublic && response.ok && CACHEABLE_PAGE.test(url.pathname)) {
            const cache = await caches.open(PAGES);
            await cache.put(request, response.clone());
            await trim(cache, 40);
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;

        if (isPublic) {
            const shell = await caches.match('/');
            if (shell) return shell;
        }

        return (await caches.match('/offline.html'))
            || new Response('Нет подключения к интернету.', {
                status: 503,
                headers: {'Content-Type': 'text/html; charset=utf-8'},
            });
    }
}

async function dataResponse(request) {
    const cache = await caches.open(DATA);
    try {
        const response = await fetch(request);
        if (response.ok) await cache.put('/platform/data', response.clone());
        return response;
    } catch {
        return (await cache.match('/platform/data'))
            || new Response('{}', {
                status: 503,
                headers: {'Content-Type': 'application/json; charset=utf-8'},
            });
    }
}

async function assetResponse(request, event) {
    const cache = await caches.open(VERSION);
    const cached = await cache.match(request);

    if (cached) {
        // Refresh stable URLs in the background; hashed build files never change.
        if (!request.url.includes('/build/')) {
            event.waitUntil((async () => {
                try {
                    const response = await fetch(request);
                    if (response.ok || response.type === 'opaque') {
                        await cache.put(request, response);
                        await trim(cache, 180, PRECACHE);
                    }
                } catch {
                    // Keep the cached copy when offline.
                }
            })());
        }
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok || response.type === 'opaque') {
            await cache.put(request, response.clone());
            await trim(cache, 180, PRECACHE);
        }
        return response;
    } catch {
        return new Response('', {status: 504, statusText: 'Offline'});
    }
}

async function cacheFirst(request, cacheName, max) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok || response.type === 'opaque') {
            await cache.put(request, response.clone());
            await trim(cache, max);
        }
        return response;
    } catch {
        return new Response('', {status: 504, statusText: 'Offline'});
    }
}

/* ---------- предварительное заполнение ---------- */

async function precacheShell() {
    try {
        const response = await fetch('/', {credentials: 'omit', cache: 'reload'});
        if (!response.ok) return;

        const pages = await caches.open(PAGES);
        await pages.put('/', response.clone());

        // The entry CSS/JS from the shell makes the first offline launch usable.
        const html = await response.text();
        const urls = [...html.matchAll(/(?:src|href)="(?:https?:\/\/[^\/"]+)?(\/(?:build|assets|storage)\/[^"]+)"/g)]
            .map(match => match[1]);
        const assets = await caches.open(VERSION);
        await Promise.allSettled(urls.slice(0, 40).map(url => assets.add(url)));
    } catch {
        // Installation still succeeds; the page is cached on the next visit.
    }
}

async function precacheData() {
    try {
        const response = await fetch('/platform/data', {credentials: 'omit'});
        if (!response.ok) return;
        const cache = await caches.open(DATA);
        await cache.put('/platform/data', response.clone());
    } catch {
        // The Explore screen keeps its own localStorage copy.
    }
}

async function cacheAssets(urls, port) {
    const cache = await caches.open(VERSION);
    let ok = true;
    let saved = 0;

    for (const raw of (urls || []).slice(0, 150)) {
        try {
            const url = new URL(raw, self.location.origin);
            if (url.origin !== self.location.origin) continue;
            const response = await fetch(url.href, {credentials: 'same-origin'});
            if (!response.ok) throw new Error('HTTP ' + response.status);
            await cache.put(url.href, response);
            saved += 1;
        } catch {
            ok = false;
        }
    }

    await trim(cache, 180, PRECACHE);
    port?.postMessage({ok, saved});
}

async function trim(cache, max, keepUrls = []) {
    const keys = await cache.keys();
    const removable = keepUrls.length
        ? keys.filter(key => !keepUrls.includes(new URL(key.url).pathname))
        : keys;
    const extra = removable.length - max;
    if (extra <= 0) return;
    for (const key of removable.slice(0, extra)) await cache.delete(key);
}
