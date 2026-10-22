<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import * as maplibregl from '@/platform/maplibre';
import 'maplibre-gl/dist/maplibre-gl.css';
const props=defineProps({places:Array,route:Array,position:Object});
const emit=defineEmits(['select']);
const element=ref(null), ready=ref(false), failed=ref(false);
let map; let clusterMarkers=[];
const geo=()=>({type:'FeatureCollection',features:props.places.filter(p=>p.lat!==null&&p.lng!==null).map(p=>({type:'Feature',geometry:{type:'Point',coordinates:[p.lng,p.lat]},properties:{id:p.id,name:p.name}}))});
function update() {
    if(!map?.isStyleLoaded()) return;
    map.getSource('places')?.setData(geo());
    const points=(props.route??[]).map(id=>props.places.find(p=>p.id===id)).filter(p=>p&&p.lng!==null&&p.lat!==null);
    map.getSource('route')?.setData({type:'FeatureCollection',features:points.length>1?[{type:'Feature',geometry:{type:'LineString',coordinates:points.map(p=>[p.lng,p.lat])}}]:[]});
}
onMounted(()=>{
    try {
        map=new maplibregl.Map({container:element.value,center:[37.805,48.015],zoom:12,
            style:{version:8,sources:{osm:{type:'raster',tiles:['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],tileSize:256,attribution:'© OpenStreetMap contributors'}},layers:[{id:'osm',type:'raster',source:'osm',paint:{'raster-saturation':-.7,'raster-opacity':.82}}]}});
        map.addControl(new maplibregl.NavigationControl(),'bottom-right');
        map.on('error',()=>failed.value=true);
        map.once('style.load',()=>{
            ready.value=true;
            map.addSource('places',{type:'geojson',data:geo(),cluster:true,clusterRadius:44,clusterMaxZoom:14});
            map.addLayer({id:'clusters',type:'circle',source:'places',filter:['has','point_count'],paint:{'circle-color':'#1763b5','circle-radius':['step',['get','point_count'],21,10,27],'circle-stroke-color':'#fff','circle-stroke-width':3}});
            // DOM cluster labels avoid a remote glyph server and remain readable offline.
            map.on('render',()=>{
                if(!map.isSourceLoaded('places')) return;
                for(const m of clusterMarkers) m.remove();
                clusterMarkers=[];
                const seen=new Set();
                for(const f of map.querySourceFeatures('places')) {
                    if(!f.properties.cluster || seen.has(f.properties.cluster_id)) continue;
                    seen.add(f.properties.cluster_id);
                    const el=document.createElement('div'); el.className='cluster-label';el.textContent=f.properties.point_count;
                    clusterMarkers.push(new maplibregl.Marker({element:el}).setLngLat(f.geometry.coordinates).addTo(map));
                }
            });
            map.addLayer({id:'points',type:'circle',source:'places',filter:['!',['has','point_count']],paint:{'circle-color':'#1763b5','circle-radius':9,'circle-stroke-color':'white','circle-stroke-width':3}});
            map.addSource('route',{type:'geojson',data:{type:'FeatureCollection',features:[]}});
            map.addLayer({id:'route',type:'line',source:'route',paint:{'line-color':'#1763b5','line-width':4,'line-dasharray':[2,2]}});
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
            update();
        });
    } catch { failed.value=true; }
});
watch(()=>props.places,update,{deep:true});
watch(()=>props.route,update,{deep:true});
watch(()=>props.position,p=>{if(p&&map){new maplibregl.Marker({color:'#385eab'}).setLngLat([p.lng,p.lat]).addTo(map);map.flyTo({center:[p.lng,p.lat],zoom:14});}});
onBeforeUnmount(()=>map?.remove());
</script>
<template>
    <div class="map-wrap" :aria-busy="!ready">
        <div ref="element" class="real-map" aria-label="Карта мест региона"></div>
        <div class="map-caption"><span class="live-dot"></span> Места рядом с вами <small>Нажмите на метку</small></div>
        <p v-if="failed" class="map-warning">Подложка карты недоступна. Карточки и список мест продолжают работать.</p>
        <p v-if="route?.length>1" class="map-route-note">Линия соединяет точки маршрута. Дорожный маршрут доступен в карточке.</p>
    </div>
</template>
