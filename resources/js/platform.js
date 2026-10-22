import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import '../css/platform.css';
createInertiaApp({
    title: t => `${t} · Код Новороссии`,
    resolve: async name => {
        const pages=import.meta.glob('./Pages/**/*.vue');
        return (await pages[`./Pages/${name}.vue`]()).default;
    },
    setup({el, App, props, plugin}) {
        createApp({render:()=>h(App,props)}).use(plugin).use(createPinia()).mount(el);
    },
    progress: {color:'#547864'},
});
if ('serviceWorker' in navigator) window.addEventListener('load',()=>navigator.serviceWorker.register('/sw.js').catch(()=>{}));
