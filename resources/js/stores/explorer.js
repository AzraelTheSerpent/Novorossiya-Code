import { defineStore } from 'pinia';
import { ref, watch } from 'vue';
function read(key, fallback) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } }
export const useExplorer = defineStore('explorer',()=>{
    const favorites=ref(read('kod:favorites',[]));
    const removedFavorites=ref(read('kod:removed-favorites',[]));
    const route=ref(read('kod:route',[]));
    const theme=ref(read('kod:theme','light'));
    const savedRoutes=ref(read('kod:routes',[]));
    const online=ref(navigator.onLine);
    const storageError=ref('');
    for(const [key,value] of Object.entries({favorites,'removed-favorites':removedFavorites,route,theme,routes:savedRoutes})) watch(value,v=>{
        try { localStorage.setItem('kod:'+key,JSON.stringify(v)); }
        catch { storageError.value='Не удалось сохранить данные на устройстве. Проверьте свободное место и настройки браузера.'; }
    },{deep:true});
    window.addEventListener('online',()=>online.value=true);
    window.addEventListener('offline',()=>online.value=false);
    watch(theme,v=>document.documentElement.dataset.theme=v,{immediate:true});
    function toggle(id) {
        if(favorites.value.includes(id)) {favorites.value=favorites.value.filter(x=>x!==id);if(!removedFavorites.value.includes(id))removedFavorites.value.push(id);}
        else {favorites.value.push(id);removedFavorites.value=removedFavorites.value.filter(x=>x!==id);}
    }
    function addStop(id) { if(!route.value.includes(id)) route.value.push(id); }
    function saveRoute(name) { savedRoutes.value.push({id:Date.now(),name,stops:[...route.value]}); }
    return {favorites,removedFavorites,route,theme,savedRoutes,online,storageError,toggle,addStop,saveRoute};
});
