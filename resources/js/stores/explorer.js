import { defineStore } from 'pinia';
import { ref, watch, onScopeDispose } from 'vue';
function read(key, fallback) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } }
export const useExplorer = defineStore('explorer',()=>{
    const favorites=ref(read('kod:favorites',[]));
    const removedFavorites=ref(read('kod:removed-favorites',[]));
    const route=ref(read('kod:route',[]));
    const theme=ref(typeof document !== 'undefined' ? document.documentElement.dataset.theme || 'light' : 'light');
    const savedRoutes=ref(read('kod:routes',[]));
    const online=ref(typeof navigator !== 'undefined' ? navigator.onLine : true);
    const storageError=ref('');
    for(const [key,value] of Object.entries({favorites,'removed-favorites':removedFavorites,route,routes:savedRoutes})) watch(value,v=>{
        try { localStorage.setItem('kod:'+key,JSON.stringify(v)); }
        catch { storageError.value='Не удалось сохранить данные на устройстве. Проверьте свободное место и настройки браузера.'; }
    },{deep:true});
    const setOnline=()=>online.value=true, setOffline=()=>online.value=false;
    const receiveTheme=event=>theme.value=event.detail;
    if(typeof window !== 'undefined') {
        window.addEventListener('online',setOnline);
        window.addEventListener('offline',setOffline);
        window.addEventListener('kod:theme-change',receiveTheme);
        onScopeDispose(()=>{
            window.removeEventListener('online',setOnline);
            window.removeEventListener('offline',setOffline);
            window.removeEventListener('kod:theme-change',receiveTheme);
        });
    }
    watch(theme,v=>{
        if(typeof window !== 'undefined' && document.documentElement.dataset.theme!==v) window.KodTheme?.set(v);
    });
    function toggle(id) {
        if(favorites.value.includes(id)) {favorites.value=favorites.value.filter(x=>x!==id);if(!removedFavorites.value.includes(id))removedFavorites.value.push(id);}
        else {favorites.value.push(id);removedFavorites.value=removedFavorites.value.filter(x=>x!==id);}
    }
    function addStop(id) { if(!route.value.includes(id)) route.value.push(id); }
    function saveRoute(name) { savedRoutes.value.push({id:Date.now(),name,stops:[...route.value]}); }
    return {favorites,removedFavorites,route,theme,savedRoutes,online,storageError,toggle,addStop,saveRoute};
});
