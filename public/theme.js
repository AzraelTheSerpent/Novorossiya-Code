/* Runs in <head> before paint; shared by Blade, Vue and the offline shell. */
(function () {
    'use strict';
    const root = document.documentElement;
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    const valid = value => value === 'light' || value === 'dark';
    let preference;
    try { preference = JSON.parse(localStorage.getItem('kod:theme')); } catch (_) {}
    let manual = valid(preference);
    function apply(value) {
        root.dataset.theme = value;
        root.style.colorScheme = value;
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.content = value === 'dark' ? '#101722' : '#ffffff';
        document.querySelectorAll('[data-theme-toggle]').forEach(button => {
            button.setAttribute('aria-pressed', String(value === 'dark'));
            button.setAttribute('aria-label', value === 'dark' ? 'Включить светлую тему' : 'Включить тёмную тему');
            button.innerHTML = '<span aria-hidden="true">' + (value === 'dark' ? '☀' : '☾') + '</span><span>' + (value === 'dark' ? 'Светлая тема' : 'Тёмная тема') + '</span>';
        });
        window.dispatchEvent(new CustomEvent('kod:theme-change', { detail: value }));
    }
    window.KodTheme = {
        set(value) {
            if (!valid(value)) return;
            manual = true;
            try { localStorage.setItem('kod:theme', JSON.stringify(value)); } catch (_) {}
            apply(value);
        },
        toggle() { this.set(root.dataset.theme === 'dark' ? 'light' : 'dark'); }
    };
    apply(manual ? preference : system.matches ? 'dark' : 'light');
    system.addEventListener('change', event => { if (!manual) apply(event.matches ? 'dark' : 'light'); });
    window.addEventListener('storage', event => {
        if (event.key !== 'kod:theme') return;
        let value;
        try { value = JSON.parse(event.newValue); } catch (_) {}
        manual = valid(value);
        apply(manual ? value : system.matches ? 'dark' : 'light');
    });
    document.addEventListener('DOMContentLoaded', () => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'theme-control';
        button.dataset.themeToggle = '';
        button.addEventListener('click', () => window.KodTheme.toggle());
        document.body.appendChild(button);
        apply(root.dataset.theme);
    });
})();
