<script setup>
import { computed, ref, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { Search, MapPin, Heart, Route, Compass, CalendarDays, ArrowUpRight, ArrowRight, SlidersHorizontal, Sun, Moon, Navigation, X, Plus, Check, Clock, Star, List, Map, WifiOff, Download, Share2, Flag, Building2, ChevronDown, MoveUp, MoveDown, Trash2, UserRound, Bot, Sparkles } from 'lucide-vue-next';
import PlatformMap from '@/Components/PlatformMap.vue';
import AiChat from '@/Components/AiChat.vue';
import KodIcon from '@/Components/KodIcon.vue';
import { useExplorer } from '@/stores/explorer';
import { matches, distance, openNow } from '@/platform/search';
import { getWorkerUrl } from '@/platform/maplibre';
const props=defineProps({data:Object,selectedSlug:String});
const page=usePage(), store=useExplorer();
const aiEnabled=computed(()=>page.props.aiChatEnabled===true);
const data=ref(props.data);
const tab=ref('Каталог'), query=ref(''), vertical=ref(0), category=ref(''), sort=ref('popular');
const openOnly=ref(false), minRating=ref(0), maxPrice=ref(4), format=ref(''), filters=ref(false), view=ref('split');
const position=ref(null), message=ref(''), selected=ref(null), reviews=ref([]), rating=ref(5), reviewText=ref('');
const reportText=ref(''), showReport=ref(false), reportType=ref('inaccuracy'), routeName=ref('Мой маршрут');
const myJourneys=ref([]), eventDate=ref(''), eventType=ref('');
const detailPanel=ref(null);
let previousFocus, previousOverflow;
function trapDialog(event) {
    if(!selected.value) return;
    if(event.key==='Escape') { event.preventDefault();close();return; }
    if(event.key!=='Tab') return;
    const items=[...(detailPanel.value?.querySelectorAll('a[href],button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex="0"]')??[])].filter(el=>el.getClientRects().length);
    const first=items[0], last=items.at(-1);
    if(!first) {event.preventDefault();return;}
    if(event.shiftKey&&(document.activeElement===first||document.activeElement===detailPanel.value)) {event.preventDefault();last.focus();}
    else if(!event.shiftKey&&document.activeElement===last) {event.preventDefault();first.focus();}
}
watch(selected,async(value,old)=>{
    if(value&&!old) {
        previousFocus=document.activeElement;previousOverflow=document.body.style.overflow;
        document.body.style.overflow='hidden';
        document.addEventListener('keydown',trapDialog);
        await nextTick();detailPanel.value?.focus();
    } else if(!value&&old) {
        document.body.style.overflow=previousOverflow??'';
        document.removeEventListener('keydown',trapDialog);previousFocus?.focus();
    }
});
onBeforeUnmount(()=>{document.removeEventListener('keydown',trapDialog);if(selected.value)document.body.style.overflow=previousOverflow??'';});
const user=computed(()=>page.props.auth?.user);
const tabs=[{name:'Каталог',icon:'discover'},{name:'Карта',icon:'map'},{name:'Избранное',icon:'saved'},{name:'Маршруты',icon:'route'},{name:'Афиша',icon:'events'}];
const filtered=computed(()=>{
    let list=data.value.places.filter(p=>(!vertical.value||p.vertical_id===Number(vertical.value))&&(!category.value||p.category===category.value)&&matches(p,query.value)&&p.rating>=Number(minRating.value)&&p.price<=Number(maxPrice.value)&&(!format.value||p.format===format.value)&&(!openOnly.value||openNow(p.hours))&&(tab.value!=='Избранное'||store.favorites.includes(p.id)));
    return list.sort((a,b)=> sort.value==='rating'?b.rating-a.rating:sort.value==='name'?a.name.localeCompare(b.name,'ru'):sort.value==='distance'&&position.value?distance(position.value,a)-distance(position.value,b):b.popularity-a.popularity);
});
const stops=computed(()=>store.route.map(id=>data.value.places.find(p=>p.id===id)).filter(Boolean));
const lengthKm=computed(()=>stops.value.reduce((sum,p,i)=>i?sum+distance(stops.value[i-1],p):sum,0));
const events=computed(()=>data.value.events.filter(e=>(!eventDate.value||e.starts_at.slice(0,10)===eventDate.value)&&(!eventType.value||e.type===eventType.value)));
const selectedVertical=computed(()=>data.value.verticals.find(v=>v.id===selected.value?.vertical_id));
const notice=text=>{message.value=text;setTimeout(()=>message.value='',5500);};
async function api(url,method='GET',body) {
    const r=await fetch(url,{method,credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':page.props.csrfToken},body:body?JSON.stringify(body):undefined});
    const result=await r.json();
    if(!r.ok) throw Error(Object.values(result.errors??{}).flat()[0]??result.message??'Не удалось выполнить действие.');
    return result;
}
async function select(p) {
    selected.value=p;reviews.value=[];showReport.value=false;
    history.replaceState({},'',`/explore/${p.slug}`);
    try {reviews.value=await api(`/platform/places/${p.id}/reviews`);} catch {notice('Отзывы доступны при подключении к сети.');}
}
function close() {selected.value=null;history.replaceState({},'','/');}
async function submitReview() {
    try {const r=await api(`/platform/places/${selected.value.id}/reviews`,'POST',{rating:Number(rating.value),text:reviewText.value});notice(r.message);reviewText.value='';} catch(e){notice(e.message);}
}
async function report() {
    try {const r=await api(`/platform/places/${selected.value.id}/reports`,'POST',{type:reportType.value,message:reportText.value});notice(r.message);showReport.value=false;reportText.value='';} catch(e){notice(e.message);}
}
async function share() {
    const url=location.origin+'/explore/'+selected.value.slug;
    try {if(navigator.share) await navigator.share({title:selected.value.name,url});else {await navigator.clipboard.writeText(url);notice('Ссылка скопирована.');}}catch {notice(url);}
}
function locate() {
    if(!navigator.geolocation) return notice('Браузер не поддерживает геолокацию.');
    navigator.geolocation.getCurrentPosition(p=>{position.value={lat:p.coords.latitude,lng:p.coords.longitude};sort.value='distance';notice('Места отсортированы по расстоянию.');},()=>notice('Нет доступа к геолокации. Разрешите её в настройках браузера.'),{timeout:10000});
}
async function sync() {
    try {
        const server=await api('/platform/favorites');
        store.favorites=[...new Set([...server.filter(id=>!store.removedFavorites.includes(id)),...store.favorites])];
        const r=await api('/platform/favorites','POST',{ids:store.favorites});store.removedFavorites=[];notice(r.message);
    } catch(e){notice(e.message);}
}
async function saveRoute() {
    if(stops.value.length<2) return notice('Добавьте хотя бы два места.');
    store.saveRoute(routeName.value||'Мой маршрут');
    if(user.value&&store.online) {
        try {await api('/platform/journeys','POST',{name:routeName.value,stops:store.route,duration:Math.round(lengthKm.value/4*60+stops.value.length*30),published:false});myJourneys.value=await api('/platform/journeys');notice('Маршрут сохранён на устройстве и в аккаунте.');}
        catch(e){notice('Сохранено на устройстве. '+e.message);}
    } else notice('Маршрут сохранён на устройстве.');
}
function move(index,delta) {const items=[...store.route];[items[index],items[index+delta]]=[items[index+delta],items[index]];store.route=items;}
async function offline() {
    try {
        const fresh=await api('/platform/data');data.value=fresh;
        const cache=await caches.open('kod-data-v1');await cache.put('/platform/data',new Response(JSON.stringify(fresh),{headers:{'Content-Type':'application/json'}}));
        localStorage.setItem('kod:catalog',JSON.stringify(fresh));
        const sw=(await navigator.serviceWorker.ready).active;
        const urls=[getWorkerUrl(),...fresh.places.map(p=>p.cover).filter(u=>u?.startsWith('/')),
            ...performance.getEntriesByType('resource').map(r=>r.name).filter(u=>u.startsWith(location.origin+'/build/'))];
        if(sw) await new Promise((resolve,reject)=>{const channel=new MessageChannel();const timer=setTimeout(()=>reject(Error('Истекло время сохранения файлов.')),30000);channel.port1.onmessage=e=>{clearTimeout(timer);e.data.ok?resolve():reject(Error('Не все файлы удалось сохранить.'));};sw.postMessage({type:'CACHE_ASSETS',urls},[channel.port2]);});
        notice('Каталог, маршруты и афиша сохранены. Офлайн-карта сохраняет только ранее просмотренные участки.');
    }catch(e){notice('Не удалось обновить офлайн-каталог. '+e.message);}
}
function useJourney(j) {store.route=j.stops.map(s=>s.id??s);tab.value='Маршруты';notice('Маршрут открыт. Точки можно переставлять.');}
function changeTab(name) {tab.value=name;if(name==='Карта')view.value='map';else view.value='split';}
const dateLabel=d=>new Date(d).toLocaleDateString('ru',{day:'numeric',month:'long'});
onMounted(async()=>{
    if(!navigator.onLine) try {data.value=JSON.parse(localStorage.getItem('kod:catalog'))??data.value;}catch{}
    if(props.selectedSlug) {const p=data.value.places.find(p=>p.slug===props.selectedSlug);if(p)select(p);}
    if(user.value) try {myJourneys.value=await api('/platform/journeys');}catch{}
    if(new URLSearchParams(location.search).get('tab')) changeTab(new URLSearchParams(location.search).get('tab'));
});
watch(()=>props.data,v=>data.value=v);
</script>

<template>
<Head title="Откройте свой регион" />
<div class="portal">
    <aside class="sidebar">
        <Link href="/" class="brand"><img src="/assets/brand/logo-light.webp" class="brand-logo brand-logo-light" alt="" width="76" height="76"><img src="/assets/brand/logo-dark.webp" class="brand-logo brand-logo-dark" alt="" width="76" height="76"><span>КОД<br><b>НОВОРОССИИ</b></span></Link>
        <div class="region"><MapPin :size="15"/> Донецк и регион <ChevronDown :size="14"/></div>
        <p class="nav-label">ИССЛЕДУЙТЕ</p>
        <nav aria-label="Основная навигация"><button v-for="item in tabs" :key="item.name" :class="{active:tab===item.name}" :aria-current="tab===item.name?'page':undefined" @click="changeTab(item.name)"><KodIcon :name="item.icon" :size="20"/>{{item.name}}<span v-if="item.name==='Избранное' && store.favorites.length" class="count">{{store.favorites.length}}</span></button></nav>
        <div class="side-divider"></div><p class="nav-label">ВОЗМОЖНОСТИ</p>
        <button class="side-link" @click="changeTab('Инвесторам')"><Building2 :size="20"/> Инвесторам <ArrowUpRight :size="15"/></button>
        <button class="side-link" :class="{active:tab==='Ещё'}" :aria-current="tab==='Ещё'?'page':undefined" @click="changeTab('Ещё')"><Bot :size="20"/> ИИ-помощник <Sparkles :size="15"/></button>
        <Link href="/platform/workspace" class="side-link"><Plus :size="20"/> Добавить своё место</Link>
        <div class="side-bottom"><div class="offline-promo"><Download :size="20"/><b>Регион всегда рядом</b><p>Сохраните места для прогулки без интернета.</p><button @click="offline">Скачать каталог <ArrowRight :size="15"/></button></div><button class="side-link" @click="store.theme=store.theme==='light'?'dark':'light'"><component :is="store.theme==='light'?Moon:Sun" :size="18"/>{{store.theme==='light'?'Тёмная':'Светлая'}} тема</button><small>Открывайте. Вдохновляйтесь. Возвращайтесь.</small></div>
    </aside>
    <main class="main">
        <header class="topbar"><Link href="/" class="mobile-brand" aria-label="Код Новороссии — главная"><img src="/assets/brand/logo-light.webp" class="brand-logo brand-logo-light" alt="" width="44" height="44"><img src="/assets/brand/logo-dark.webp" class="brand-logo brand-logo-dark" alt="" width="44" height="44"></Link><span class="breadcrumb">Путеводитель <span>/</span> {{tab}}</span><div class="top-actions"><span class="status"><i :class="{disconnected:!store.online}"></i>{{store.online?'Вы в сети':'Офлайн'}}</span><Link :href="user?'/platform/workspace':'/login'" class="account"><UserRound :size="17"/>{{user?user.name:'Войти'}}</Link></div></header>
        <div v-if="!store.online" class="network-banner"><WifiOff :size="16"/> Офлайн-режим: избранное и сохранённые места доступны. Отзывы и заявки требуют подключения.</div>
        <div v-if="store.storageError" class="network-banner">{{store.storageError}}</div>
        <template v-if="['Каталог','Карта','Избранное'].includes(tab)">
            <section class="intro"><div><p class="eyebrow">ВАШ РЕГИОН. ВАШИ ОТКРЫТИЯ.</p><h1>{{tab==='Избранное'?'Ваши любимые места':'Откройте места,'}}<br v-if="tab!=='Избранное'"><em v-if="tab!=='Избранное'">которые вдохновляют.</em></h1><p>{{tab==='Избранное'?'Сохраните то, к чему хочется вернуться.':'Культура, вкус и новые впечатления. Соберите свой маршрут по Новороссии — от любимой кофейни до большой истории.'}}</p></div><div class="intro-stamp"><Compass :size="36" :stroke-width="1"/><span>ОТКРОЙТЕ<br>СВОЙ РЕГИОН</span></div></section>
            <div class="vertical-tabs"><button :class="{chosen:!vertical}" @click="vertical=0">Все места <span>{{data.places.length}}</span></button><button v-for="v in data.verticals" :key="v.id" :class="{chosen:vertical===v.id}" @click="vertical=v.id"><i :style="{background:v.color}"></i>{{v.name}}</button></div>
            <section class="searchbar"><Search :size="20"/><input v-model="query" aria-label="Поиск мест" placeholder="Место, адрес или то, что вам интересно"><button class="filter-toggle" :class="{selected:filters}" :aria-expanded="filters" aria-controls="catalog-filters" @click="filters=!filters"><SlidersHorizontal :size="17"/>Фильтры</button></section>
            <div v-if="filters" id="catalog-filters" class="filter-panel"><label>Категория<select v-model="category"><option value="">Все категории</option><option v-for="c in [...new Set(data.places.map(p=>p.category))]" :key="c">{{c}}</option></select></label><label>Рейтинг<select v-model="minRating"><option :value="0">Любой</option><option :value="4">От 4</option><option :value="4.5">От 4,5</option></select></label><label>Стоимость<select v-model="maxPrice"><option :value="4">Любая</option><option :value="1">Бесплатно / бюджетно</option><option :value="2">До ₽₽</option></select></label><label>Посещение<select v-model="format"><option value="">Любое</option><option value="individual">Самостоятельно</option><option value="group">С группой</option><option value="booking">По записи</option></select></label><label class="check-label"><input v-model="openOnly" type="checkbox"> Открыто сейчас</label></div>
            <div class="results-bar"><p><b>{{filtered.length}}</b> мест для вашего открытия</p><div><select v-model="sort" aria-label="Сортировка"><option value="popular">По популярности</option><option value="rating">По рейтингу</option><option value="name">По названию</option><option v-if="position" value="distance">По расстоянию</option></select><button class="icon-btn" title="Найти рядом со мной" @click="locate"><Navigation :size="17"/></button><div class="view-switch"><button :class="{on:view==='list'}" aria-label="Список" @click="view='list'"><List :size="17"/></button><button :class="{on:view==='split'}" aria-label="Список и карта" @click="view='split'"><Map :size="17"/></button></div></div></div>
            <div class="explore-grid" :class="{'list-only':view==='list','map-only':view==='map'}">
                <div v-if="view!=='map'" class="cards-grid"><article v-for="(p,index) in filtered" :key="p.id" class="place-card"><div class="card-cover" :class="'cover-'+(p.id%4)" @click="select(p)"><img v-if="p.cover" :src="p.cover" :alt="p.name" loading="lazy" @error="$event.target.style.display='none'"><span class="card-type">{{p.category}}</span><button class="heart-btn" :class="{saved:store.favorites.includes(p.id)}" :aria-label="store.favorites.includes(p.id)?'Убрать из избранного':'В избранное'" @click.stop="store.toggle(p.id)"><Heart :size="18" :fill="store.favorites.includes(p.id)?'currentColor':'none'"/></button><span v-if="p.details?.promo" class="promo">{{p.details.promo}}</span></div><div class="card-body"><div class="card-title"><button @click="select(p)">{{p.name}}</button><span class="rating"><Star :size="13" fill="currentColor"/>{{p.rating?p.rating.toFixed(1):'Новое'}}</span></div><p class="card-summary">{{p.summary}}</p><p class="card-address"><MapPin :size="13"/>{{p.address}}</p><div class="card-footer"><span :class="{'is-open':openNow(p.hours)}"><i></i>{{openNow(p.hours)?'Открыто сейчас':'Время в карточке'}}</span><button @click="store.addStop(p.id);notice('Место добавлено в ваш маршрут.')"><Plus :size="14"/>В маршрут</button></div></div></article><div v-if="!filtered.length" class="empty"><Compass :size="38"/><h2>{{tab==='Избранное'?'Здесь будут ваши открытия':'Пока ничего не нашли'}}</h2><p>{{tab==='Избранное'?'Нажмите на сердечко в карточке места.':'Попробуйте другой запрос или измените фильтры.'}}</p><button class="primary" @click="query='';vertical=0;category='';minRating=0;openOnly=false;maxPrice=4;format='';tab='Каталог'">Показать все места</button></div></div>
                <div v-if="view!=='list'" class="map-column"><PlatformMap :places="filtered" :route="store.route" :position="position" @select="id=>select(data.places.find(p=>p.id===id))"/><div class="map-bottom"><span><i class="live-dot"></i> Ваше следующее открытие уже рядом</span><button @click="view=view==='map'?'split':'map'">{{view==='map'?'Вернуть список':'Развернуть карту'}} <ArrowUpRight :size="16"/></button></div><div class="route-teaser"><div class="teaser-icon"><Route :size="24"/></div><div><small>МАРШРУТ ДНЯ</small><h3>Креативный Донецк</h3><p>Музей, мастерская и кофе. Один город, разные истории.</p></div><button class="icon-btn" aria-label="Открыть маршрут дня" @click="useJourney(data.journeys[0])" v-if="data.journeys.length"><ArrowUpRight :size="22"/></button></div></div>
            </div>
            <button v-if="tab==='Избранное'&&user" class="primary mt-4" @click="sync">Синхронизировать с аккаунтом</button>
        </template>
        <section v-else-if="tab==='Маршруты'" class="content-page"><p class="eyebrow">ГОРОД В ВАШЕМ РИТМЕ</p><h1>Маршруты с характером</h1><p class="lead">Выберите готовую прогулку или соберите собственную.</p><div class="journeys"><article v-for="j in data.journeys" :key="j.id" class="journey-card"><Route :size="32"/><p class="eyebrow">{{j.stops.length}} ТОЧЕК · {{j.duration}} МИНУТ</p><h2>{{j.name}}</h2><p>{{j.description}}</p><button class="primary" @click="useJourney(j)">Открыть маршрут <ArrowRight :size="16"/></button></article></div><div class="route-builder"><h2>Ваша прогулка</h2><div class="route-builder-head"><input v-model="routeName" aria-label="Название маршрута" maxlength="180"><span>≈ {{lengthKm.toFixed(1)}} км по прямой · {{Math.round(lengthKm/4*60+stops.length*30)}} мин с остановками</span></div><div v-for="(p,i) in stops" :key="p.id" class="stop"><span class="stop-number">{{i+1}}</span><button class="stop-title" @click="select(p)">{{p.name}}<small>{{p.address}}</small></button><button class="icon-btn" :disabled="i===0" aria-label="Выше" @click="move(i,-1)"><MoveUp :size="16"/></button><button class="icon-btn" :disabled="i===stops.length-1" aria-label="Ниже" @click="move(i,1)"><MoveDown :size="16"/></button><button class="icon-btn" aria-label="Удалить точку" @click="store.route=store.route.filter(id=>id!==p.id)"><X :size="16"/></button></div><p v-if="!stops.length" class="muted">Добавляйте места кнопкой «В маршрут» в каталоге.</p><div class="button-row"><button class="primary" :disabled="stops.length<2" @click="saveRoute">Сохранить прогулку</button><button class="secondary" @click="changeTab('Каталог')">Добавить места <Plus :size="16"/></button></div></div><h2 v-if="store.savedRoutes.length">На этом устройстве</h2><div v-for="j in store.savedRoutes" :key="j.id" class="saved-route"><b>{{j.name}}</b><span>{{j.stops.length}} точек</span><button @click="store.route=[...j.stops]">Открыть</button><button aria-label="Удалить маршрут" @click="store.savedRoutes=store.savedRoutes.filter(x=>x.id!==j.id)"><Trash2 :size="16"/></button></div><h2 v-if="myJourneys.length">В аккаунте</h2><div v-for="j in myJourneys" :key="j.id" class="saved-route"><b>{{j.name}}</b><span>{{j.places.length}} точек</span><button @click="store.route=j.places.map(p=>p.id)">Открыть</button></div></section>
        <section v-else-if="tab==='Афиша'" class="content-page"><p class="eyebrow">ПЛАНЫ НА ВЫХОДНЫЕ</p><h1>Быть там, где интересно</h1><p class="lead">Выставки, встречи и мастер-классы региона.</p><div class="button-row"><input v-model="eventDate" type="date" aria-label="Дата события"><select v-model="eventType" aria-label="Тип события"><option value="">Все события</option><option value="exhibition">Выставки и мастер-классы</option><option value="concert">Концерты</option><option value="theater">Театр</option></select><button class="secondary" @click="eventDate='';eventType=''">Сбросить</button></div><article v-for="e in events" :key="e.id" class="event-row"><div class="event-date"><b>{{new Date(e.starts_at).getDate()}}</b>{{new Date(e.starts_at).toLocaleDateString('ru',{month:'short'})}}</div><div><small>{{e.price||'Уточняйте у организатора'}}</small><h2>{{e.title}}</h2><p>{{e.description}}</p><button v-if="data.places.find(p=>p.id===e.place_id)" class="text-link" @click="select(data.places.find(p=>p.id===e.place_id))">О месте проведения <ArrowUpRight :size="16"/></button></div></article><p v-if="!events.length" class="empty">На выбранную дату событий нет.</p></section>
        <section v-else-if="tab==='Инвесторам'" class="content-page"><p class="eyebrow">ВОЗМОЖНОСТИ РЕГИОНА</p><h1>Место для вашей идеи</h1><p class="lead">Площадки и прямой контакт с куратором «одного окна».</p><div class="journeys"><article v-for="s in data.sites" :key="s.id" class="journey-card"><Building2 :size="30"/><p class="eyebrow">{{s.area}} М² · {{s.format}}</p><h2>{{s.name}}</h2><p>{{s.description}}</p><p class="muted">{{s.address}}</p><Link class="primary" :href="'/platform/workspace?site='+s.id">Обсудить проект <ArrowUpRight :size="16"/></Link></article></div><p class="demo-note">Площадки и условия в прототипе демонстрационные. Они не являются публичным предложением.</p></section>
        <section v-if="tab==='Ещё'" class="content-page">
            <p class="eyebrow">ПОЛЕЗНОЕ РЯДОМ</p>
            <h1>Больше возможностей</h1>
            <div class="workspace-section">
                <div class="button-row">
                    <button class="secondary" @click="changeTab('Афиша')"><CalendarDays :size="18"/>Афиша событий</button>
                    <button class="secondary" @click="changeTab('Инвесторам')"><Building2 :size="18"/>Инвесторам</button>
                    <Link class="primary" href="/platform/workspace">Мой кабинет</Link>
                    <button class="secondary" @click="offline"><Download :size="18"/>Сохранить офлайн</button>
                    <button class="secondary" @click="store.theme=store.theme==='light'?'dark':'light'">Сменить тему</button>
                </div>
            </div>
            <div class="workspace-section ai-chat-section">
                <div class="ai-section-head">
                    <div>
                        <p class="eyebrow">ИИ-ПОМОЩНИК</p>
                        <h2>Спросите о регионе</h2>
                        <p class="muted">Места, маршруты, события и работа платформы. Ответы требуют подключения к сети.</p>
                    </div>
                    <span v-if="!aiEnabled" class="badge-status">Временно недоступен</span>
                </div>

                <AiChat v-if="aiEnabled" :online="store.online" />

                <p v-else class="muted">
                    ИИ-помощник временно недоступен. Ниже — ответы на частые вопросы.
                </p>

                <div class="ai-chat__faq">
                    <h3>Частые вопросы</h3>
                    <details>
                        <summary>Как сохранить место?</summary>
                        <p class="muted mt-3">Нажмите сердечко на карточке. Избранное хранится на устройстве и работает без входа. Войдите в аккаунт, чтобы вручную синхронизировать его.</p>
                    </details>
                    <details class="mt-4">
                        <summary>Как собрать прогулку?</summary>
                        <p class="muted mt-3">Нажмите «В маршрут» у нескольких мест. В разделе «Маршруты» измените порядок точек, укажите название и сохраните прогулку.</p>
                    </details>
                    <details class="mt-4">
                        <summary>Что работает без интернета?</summary>
                        <p class="muted mt-3">Сохранённый каталог, поиск, карточки и локальное избранное. Карта показывает ранее просмотренные участки. Отзывы, вход и заявки требуют сети.</p>
                    </details>
                    <details class="mt-4">
                        <summary>Как добавить бизнес или подать заявку инвестора?</summary>
                        <p class="muted mt-3">Зарегистрируйтесь и откройте кабинет. Добавьте объект на вкладке «Мои места» или выберите площадку на вкладке «Инвестору».</p>
                    </details>
                </div>
            </div>
        </section>
        <footer class="page-footer"><b>Код Новороссии</b><span>Откройте места, есть история. Найдите свою.</span><button @click="changeTab('Ещё')">Помощь</button><span>Учебный прототип · демоданные</span></footer>
    </main>
    <nav class="mobile-nav"><button v-for="item in tabs.slice(0,4)" :key="item.name" :class="{active:tab===item.name}" :aria-current="tab===item.name?'page':undefined" @click="changeTab(item.name)"><KodIcon :name="item.icon" :size="20"/><span>{{item.name}}</span></button><button @click="changeTab('Ещё')" :class="{active:tab==='Ещё'}"><UserRound :size="20"/><span>Ещё</span></button></nav>
    <Transition name="toast"><div v-if="message" class="notice" role="status" aria-live="polite"><Check :size="18"/>{{message}}</div></Transition>
    <div v-if="selected" class="modal-backdrop-custom" @click.self="close" @keydown.esc="close"><section ref="detailPanel" class="detail-modal" role="dialog" aria-modal="true" :aria-label="selected.name" tabindex="-1"><button class="modal-close" aria-label="Закрыть" @click="close"><X :size="22"/></button><div class="detail-cover" :class="'cover-'+(selected.id%4)"><img v-if="selected.cover" :src="selected.cover" :alt="selected.name" @error="$event.target.style.display='none'"><span>{{selected.category}}</span></div><div class="detail-body"><p class="eyebrow">{{selectedVertical?.name}}</p><h1>{{selected.name}}</h1><p class="detail-address"><MapPin :size="16"/>{{selected.address}}</p><div class="detail-meta"><span><Star :size="16"/> {{selected.rating||'Нет оценок'}} · {{selected.reviews_count}} отзывов</span><span>{{'₽'.repeat(selected.price)}}</span><span v-if="selected.verified_at">Проверено {{selected.verified_at}}</span></div><p class="detail-description">{{selected.description}}</p><div class="button-row"><button class="primary" @click="store.toggle(selected.id)"><Heart :size="17"/>{{store.favorites.includes(selected.id)?'В избранном':'В избранное'}}</button><button class="secondary" @click="store.addStop(selected.id);notice('Добавлено в маршрут.')"><Plus :size="17"/>В маршрут</button><button class="icon-btn" title="Поделиться" @click="share"><Share2 :size="19"/></button></div><a class="text-link mt-3" :href="`https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route=;${selected.lat},${selected.lng}`" target="_blank" rel="noopener">Построить дорожный маршрут <ArrowUpRight :size="16"/></a><div class="detail-info"><div><h3>Время работы</h3><p v-for="(hours,day) in selected.hours" :key="day">{{{mon:'Пн',tue:'Вт',wed:'Ср',thu:'Чт',fri:'Пт',sat:'Сб',sun:'Вс'}[day]}} {{hours==='closed'?'Выходной':hours}}</p></div><div><h3>Полезно знать</h3><p v-if="selected.phone">{{selected.phone}}</p><p>{{{individual:'Можно прийти самостоятельно',group:'Групповое посещение',booking:'Требуется предварительная запись'}[selected.format]}}</p><p v-for="(value,key) in selected.details" :key="key"><b>{{key}}:</b> {{value}}</p></div></div><button class="text-link" @click="showReport=!showReport"><Flag :size="15"/>Сообщить о неточности или нарушении прав</button><form v-if="showReport" class="inline-form" @submit.prevent="report"><select v-model="reportType"><option value="inaccuracy">Неточность</option><option value="copyright">Нарушение авторских прав</option><option value="review">Жалоба на отзыв</option></select><textarea v-model="reportText" required minlength="5" maxlength="2000" placeholder="Опишите проблему, для жалобы укажите номер отзыва"></textarea><button class="primary">Отправить</button></form><hr><h2>Впечатления посетителей</h2><article v-for="r in reviews" :key="r.id" class="review"><b>{{r.author_name}}</b><span class="rating">{{'★'.repeat(r.rating)}}</span><small>Отзыв №{{r.id}}</small><p>{{r.text}}</p><div v-if="r.owner_reply" class="owner-reply"><b>Ответ владельца</b><p>{{r.owner_reply}}</p></div><button v-if="user?.id===r.user_id" class="text-link" @click="api('/platform/reviews/'+r.id,'DELETE').then(()=>select(selected)).catch(e=>notice(e.message))">Удалить свой отзыв</button><form v-if="user?.id===selected.owner_id" @submit.prevent="api('/platform/reviews/'+r.id+'/reply','POST',{owner_reply:$event.target.elements.reply.value}).then(()=>select(selected)).catch(e=>notice(e.message))"><input name="reply" required maxlength="2000" placeholder="Ответ владельца"><button class="secondary">Ответить</button></form></article><p v-if="!reviews.length" class="muted">Будьте первым, кто поделится впечатлениями.</p><form v-if="user&&user.id!==selected.owner_id" class="inline-form" @submit.prevent="submitReview"><h3>Ваш отзыв</h3><select v-model="rating" aria-label="Ваша оценка"><option v-for="n in [5,4,3,2,1]" :key="n" :value="n">{{n}} из 5</option></select><textarea v-model="reviewText" required minlength="5" maxlength="2000" placeholder="Что вам понравилось?"></textarea><button class="primary">Отправить на модерацию</button></form><Link v-else-if="!user" href="/login" class="secondary">Войти, чтобы оставить отзыв</Link></div></section></div>
</div>
</template>

<style scoped>
.side-link.active {
    background: var(--soft);
    color: var(--accent);
    box-shadow: inset 3px 0 var(--accent);
    font-weight: 650;
}

.ai-chat-section .ai-section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
}

.ai-chat-section .ai-section-head h2 {
    margin: 0 0 6px;
}

.ai-chat-section .ai-section-head .muted {
    margin: 0;
    max-width: 560px;
}

.ai-chat-section .badge-status {
    margin-right: 0;
    white-space: nowrap;
}

.ai-chat-section .ai-chat__faq {
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1px solid var(--line);
}

.ai-chat-section .ai-chat__faq h3 {
    margin-bottom: 10px;
    font-size: 14px;
}

@media (max-width: 700px) {
    .ai-chat-section .ai-section-head {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
