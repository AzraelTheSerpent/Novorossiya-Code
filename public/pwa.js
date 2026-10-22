/* Код Новороссии · установка PWA, обновления и прогрев офлайн-кэша.
 * Обычный скрипт без сборщика: работает на Blade-страницах и в Inertia. */
(() => {
    'use strict';

    const INSTALL_DISMISSED = 'kod:pwa:install-dismissed';
    const IOS_DISMISSED = 'kod:pwa:ios-dismissed';
    const IOS_SHOWN = 'kod:pwa:ios-shown';
    const INSTALL_DISMISS_MS = 14 * 24 * 60 * 60 * 1000;
    const IOS_DISMISS_MS = 30 * 24 * 60 * 60 * 1000;

    const installUi = window.KOD_PWA_INSTALL_UI !== false;
    const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    let promptEvent = null;
    let waitingWorker = null;
    let reloadOnControllerChange = false;
    let currentMode = '';
    let banner = null;
    let updateDismissed = false;

    const storage = {
        get(key) {
            try {
                return JSON.parse(window.localStorage.getItem(key) || 'null');
            } catch {
                return null;
            }
        },
        set(key, value) {
            try {
                window.localStorage.setItem(key, JSON.stringify(value));
            } catch {
                // Private mode or storage disabled.
            }
        },
        remove(key) {
            try {
                window.localStorage.removeItem(key);
            } catch {
                // Ignore.
            }
        },
    };

    function recentlyDismissed(key, ms) {
        const value = storage.get(key);
        return typeof value === 'number' && Date.now() - value < ms;
    }

    function getBanner() {
        if (banner) return banner;
        if (!document.body) return null;

        banner = document.createElement('aside');
        banner.className = 'pwa-banner';
        banner.setAttribute('role', 'region');
        banner.setAttribute('aria-label', 'Приложение Код Новороссии');
        banner.setAttribute('aria-live', 'polite');
        banner.addEventListener('click', onBannerClick);
        document.body.appendChild(banner);
        return banner;
    }

    function show(mode) {
        if (!installUi || isStandalone()) return;
        if (mode !== 'update' && updateDismissed) return;

        const element = getBanner();
        if (!element) return;

        currentMode = mode;
        const copy = {
            install: {
                title: 'Установить приложение',
                text: 'Быстрый доступ к местам, маршрутам и сохранённому каталогу.',
                primary: 'Установить',
                action: 'install',
                secondary: 'Позже',
            },
            ios: {
                title: 'Установить на iPhone',
                text: 'В меню браузера нажмите «Поделиться» и выберите «На экран „Домой“».',
                primary: 'Понятно',
                action: 'dismiss',
                secondary: '',
            },
            update: {
                title: 'Доступно обновление',
                text: 'Новая версия уже загружена. Обновите, чтобы увидеть изменения.',
                primary: 'Обновить',
                action: 'update',
                secondary: 'Позже',
            },
        }[mode];

        if (!copy) return;

        element.innerHTML = `
            <img class="pwa-banner__icon" src="/icon-192.png" alt="" width="46" height="46">
            <div class="pwa-banner__body">
                <p class="pwa-banner__title">${copy.title}</p>
                <p class="pwa-banner__text">${copy.text}</p>
            </div>
            <div class="pwa-banner__actions">
                ${copy.secondary ? `<button type="button" class="pwa-btn pwa-btn--ghost" data-pwa-action="later">${copy.secondary}</button>` : ''}
                <button type="button" class="pwa-btn pwa-btn--primary" data-pwa-action="${copy.action}">${copy.primary}</button>
            </div>
            <button type="button" class="pwa-banner__close" data-pwa-action="close" aria-label="Закрыть">&times;</button>
        `;
        element.hidden = false;
        element.removeAttribute('aria-hidden');
        requestAnimationFrame(() => element.classList.add('is-visible'));
    }

    function hide() {
        if (!banner) return;
        banner.classList.remove('is-visible');
        banner.setAttribute('aria-hidden', 'true');
        currentMode = '';
    }

    function dismiss(mode = currentMode) {
        if (mode === 'install') storage.set(INSTALL_DISMISSED, Date.now());
        if (mode === 'ios') storage.set(IOS_DISMISSED, Date.now());
        if (mode === 'update') updateDismissed = true;
        hide();
    }

    async function onBannerClick(event) {
        const button = event.target.closest('[data-pwa-action]');
        if (!button) return;
        const action = button.dataset.pwaAction;

        if (action === 'later' || action === 'close' || action === 'dismiss') {
            dismiss();
            return;
        }

        if (action === 'install' && promptEvent) {
            const prompt = promptEvent;
            promptEvent = null;
            button.disabled = true;
            try {
                prompt.prompt();
                const choice = await prompt.userChoice;
                if (choice.outcome !== 'accepted') storage.set(INSTALL_DISMISSED, Date.now());
            } catch {
                // The browser may withdraw the prompt; the install menu still works.
            }
            hide();
            return;
        }

        if (action === 'update' && waitingWorker) {
            button.disabled = true;
            button.textContent = 'Обновляем…';
            reloadOnControllerChange = true;
            waitingWorker.postMessage({type: 'SKIP_WAITING'});
            hide();
        }
    }

    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        promptEvent = event;

        if (!installUi || isStandalone() || recentlyDismissed(INSTALL_DISMISSED, INSTALL_DISMISS_MS)) return;
        if (currentMode !== 'update') show('install');
    });

    window.addEventListener('appinstalled', () => {
        promptEvent = null;
        waitingWorker = null;
        storage.remove(INSTALL_DISMISSED);
        hide();
    });

    /* iOS/Safari does not fire beforeinstallprompt, so explain the manual route once. */
    const userAgent = navigator.userAgent;
    const isIos = /iphone|ipad|ipod/i.test(userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isInApp = /Telegram|Instagram|FBAN|FBAV|VKClient/i.test(userAgent);
    let iosShownThisSession = false;
    try {
        iosShownThisSession = window.sessionStorage.getItem(IOS_SHOWN) === '1';
    } catch {
        // Ignore.
    }

    if (installUi && isIos && !isInApp && !isStandalone() && !iosShownThisSession
        && !recentlyDismissed(IOS_DISMISSED, IOS_DISMISS_MS)) {
        window.setTimeout(() => {
            if (isStandalone() || currentMode === 'update') return;
            try {
                window.sessionStorage.setItem(IOS_SHOWN, '1');
            } catch {
                // Ignore.
            }
            iosShownThisSession = true;
            show('ios');
        }, 9000);
    }

    /* ---------- service worker ---------- */

    function offerUpdate(worker) {
        waitingWorker = worker;
        if (!installUi || updateDismissed) return;
        show('update');
    }

    function watchRegistration(registration) {
        if (registration.waiting && navigator.serviceWorker.controller) {
            offerUpdate(registration.waiting);
        }

        registration.addEventListener('updatefound', () => {
            const worker = registration.installing;
            if (!worker) return;

            worker.addEventListener('statechange', () => {
                if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                    offerUpdate(worker);
                }
            });
        });
    }

    const sentAssets = new Set();

    function warmCache() {
        const controller = navigator.serviceWorker && navigator.serviceWorker.controller;
        if (!controller) return;

        const urls = new Set();
        const add = value => {
            if (!value) return;
            try {
                const url = new URL(value, window.location.href);
                if (url.origin !== window.location.origin) return;
                if (!/^\/(?:build|assets|storage)\//.test(url.pathname)) return;
                if (!sentAssets.has(url.href)) urls.add(url.href);
            } catch {
                // Ignore malformed URLs.
            }
        };

        try {
            performance.getEntriesByType('resource').forEach(entry => add(entry.name));
        } catch {
            // Ignore.
        }

        document.querySelectorAll('img[src]').forEach(image => add(image.currentSrc || image.src));

        if (!urls.size) return;
        const batch = [...urls].slice(0, 150);
        batch.forEach(url => sentAssets.add(url));
        controller.postMessage({type: 'CACHE_ASSETS', urls: batch});
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (reloadOnControllerChange) {
                window.location.reload();
                return;
            }
            warmCache();
        });

        window.addEventListener('load', async () => {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js', {
                    scope: '/',
                    updateViaCache: 'none',
                });
                watchRegistration(registration);
                await navigator.serviceWorker.ready;
                warmCache();
                window.setTimeout(warmCache, 2500);
                window.setTimeout(warmCache, 9000);
            } catch {
                // A PWA is an enhancement: the site keeps working without a worker.
            }
        });
    }

    window.kodPwa = {
        get installed() {
            return isStandalone();
        },
        get canInstall() {
            return Boolean(promptEvent);
        },
        install() {
            if (promptEvent) {
                promptEvent.prompt();
                return;
            }
            if (installUi && !isStandalone() && isIos) show('ios');
        },
        dismiss,
    };
})();
