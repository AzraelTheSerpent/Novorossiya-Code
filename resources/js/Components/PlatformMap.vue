<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import * as maplibregl from '@/platform/maplibre';
import 'maplibre-gl/dist/maplibre-gl.css';
import { activePromotion } from '@/platform/promotion.mjs';
const props=defineProps({places:Array,route:Array,position:Object});
const emit=defineEmits(['select']);
const element=ref(null), ready=ref(false), failed=ref(false);
let map, userMarker, refreshTimer; const clusterMarkers=new Map();
const styleUrl=()=>'/maps/region-ru-'+(document.documentElement.dataset.theme==='dark'?'dark':'positron')+'.json';
function changeTheme(){if(map){ready.value=false;for(const marker of clusterMarkers.values())marker.remove();clusterMarkers.clear();map.setStyle(styleUrl());}}
const geo=()=>({type:'FeatureCollection',features:props.places.filter(p=>p.lat!==null&&p.lng!==null).map(p=>({type:'Feature',geometry:{type:'Point',coordinates:[p.lng,p.lat]},properties:{id:p.id,name:p.name,discount:activePromotion(p)?.discount??0}}))});
function update() {
    if(!map?.isStyleLoaded()) return;
    map.getSource('places')?.setData(geo());
    const points=(props.route??[]).map(id=>props.places.find(p=>p.id===id)).filter(p=>p&&p.lng!==null&&p.lat!==null);
    map.getSource('route')?.setData({type:'FeatureCollection',features:points.length>1?[{type:'Feature',geometry:{type:'LineString',coordinates:points.map(p=>[p.lng,p.lat])}}]:[]});
}
onMounted(()=>{
    try {
        map=new maplibregl.Map({container:element.value,center:[37.805,48.015],zoom:12,
            style:styleUrl(),locale:{'NavigationControl.ZoomIn':'Приблизить','NavigationControl.ZoomOut':'Отдалить','NavigationControl.ResetBearing':'Сбросить поворот'}});
        map.addControl(new maplibregl.NavigationControl(),'bottom-right');
        map.on('error',()=>failed.value=true);
        map.on('idle',()=>{if(map.areTilesLoaded())failed.value=false;});
        window.addEventListener('kod:theme-change',changeTheme);
        refreshTimer=setInterval(update,60000);
        map.on('style.load',()=>{
            ready.value=true;
            map.addSource('places',{type:'geojson',data:geo(),cluster:true,clusterRadius:44,clusterMaxZoom:14});
            map.addLayer({id:'clusters',type:'circle',source:'places',filter:['has','point_count'],paint:{'circle-color':'#1763b5','circle-radius':['step',['get','point_count'],21,10,27],'circle-stroke-color':'#fff','circle-stroke-width':3}});
            map.addLayer({id:'points',type:'circle',source:'places',filter:['!',['has','point_count']],paint:{'circle-color':['case',['>', ['get','discount'],0],'#ac5826','#1763b5'],'circle-radius':11,'circle-stroke-color':'white','circle-stroke-width':3}});
            map.addSource('route',{type:'geojson',data:{type:'FeatureCollection',features:[]}});
            map.addLayer({id:'route',type:'line',source:'route',paint:{'line-color':'#1763b5','line-width':4,'line-dasharray':[2,2]}});
            update();
        });
        map.on('render',()=>{
            if(!map.isStyleLoaded()||!map.getSource('places')||!map.isSourceLoaded('places'))return;
            const seen=new Set();
            for(const feature of map.querySourceFeatures('places')){
                const id=feature.properties.cluster_id;
                if(!feature.properties.cluster||seen.has(id))continue;
                seen.add(id);
                if(!clusterMarkers.has(id)){const label=document.createElement('div');label.className='cluster-label';label.textContent=feature.properties.point_count;clusterMarkers.set(id,new maplibregl.Marker({element:label}).setLngLat(feature.geometry.coordinates).addTo(map));}
                const marker=clusterMarkers.get(id);marker.getElement().textContent=feature.properties.point_count;marker.setLngLat(feature.geometry.coordinates);
            }
            for(const [id,marker] of clusterMarkers)if(!seen.has(id)){marker.remove();clusterMarkers.delete(id);}
        });
        map.on('click','points',e=>emit('select',Number(e.features[0].properties.id)));
            map.on('click','clusters',async e=>{
                const f=e.features[0]; const source=map.getSource('places');
                const zoom=await source.getClusterExpansionZoom(Number(f.properties.cluster_id));
                map.easeTo({center:f.geometry.coordinates,zoom});
            });
            for(const id of ['clusters','points']) {
                map.on('mouseenter',id,()=>map.getCanvas().style.cursor='pointer');
                map.on('mouseleave',id,()=>map.getCanvas().style.cursor='');
            }
    } catch { failed.value=true; }
});
watch(()=>props.places,update,{deep:true});
watch(()=>props.route,update,{deep:true});
watch(()=>props.position,p=>{if(p&&map){userMarker??=new maplibregl.Marker({color:'#385eab'});userMarker.setLngLat([p.lng,p.lat]).addTo(map);map.flyTo({center:[p.lng,p.lat],zoom:14});}});
onBeforeUnmount(()=>{clearInterval(refreshTimer);window.removeEventListener('kod:theme-change',changeTheme);for(const marker of clusterMarkers.values())marker.remove();userMarker?.remove();map?.remove();});
</script>
<template>
    <div class="map-wrap" :aria-busy="!ready">
        <div ref="element" class="real-map" aria-label="Карта мест региона"></div>
        <div class="map-caption"><span class="live-dot"></span> Карта открытий <small>Синие — места · оранжевые — скидки</small></div>
        <p v-if="failed" class="map-warning">Подложка карты недоступна. Карточки и список мест продолжают работать.</p>
        <p v-if="route?.length>1" class="map-route-note">Линия соединяет точки маршрута. Дорожный маршрут доступен в карточке.</p>
    </div>
</template>
