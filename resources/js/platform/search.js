export function normalize(value) {
    return String(value ?? '').toLocaleLowerCase('ru').replaceAll('ё','е').replace(/[^\p{L}\p{N}\s]/gu,' ').trim();
}
export function distance(a,b) {
    const rad=x=>x*Math.PI/180;
    const dlat=rad(b.lat-a.lat), dlon=rad(b.lng-a.lng);
    const x=Math.sin(dlat/2)**2+Math.cos(rad(a.lat))*Math.cos(rad(b.lat))*Math.sin(dlon/2)**2;
    return 6371*2*Math.atan2(Math.sqrt(x),Math.sqrt(1-x));
}
export function editDistance(a,b) {
    let row=Array.from({length:b.length+1},(_,i)=>i);
    for(let i=1;i<=a.length;i++) {
        const next=[i];
        for(let j=1;j<=b.length;j++) next[j]=Math.min(next[j-1]+1,row[j]+1,row[j-1]+(a[i-1]!==b[j-1]));
        row=next;
    }
    return row[b.length];
}
export function matches(place,query) {
    const words=normalize([place.name,place.address,place.category,...(place.tags??[])].join(' ')).split(/\s+/);
    return normalize(query).split(/\s+/).filter(Boolean).every(token=>words.some(word=>
        word.includes(token) || (token.length>=4 && (word.startsWith(token.slice(0,-2)) || editDistance(word,token)<=1))
    ));
}
export function openNow(hours,date=new Date()) {
    const day=['sun','mon','tue','wed','thu','fri','sat'][date.getDay()];
    const interval=hours?.[day];
    if(interval==='24/7') return true;
    const now=date.getHours()*60+date.getMinutes();
    const parse=interval=>interval.split('-').map(t=>{const [h,m]=t.split(':').map(Number);return h*60+m;});
    if(interval&&interval!=='closed') {const [start,end]=parse(interval);if(end<start ? now>=start : now>=start&&now<end)return true;}
    const yesterday=hours?.[['sun','mon','tue','wed','thu','fri','sat'][(date.getDay()+6)%7]];
    if(yesterday&&yesterday!=='closed'&&yesterday!=='24/7') {const [start,end]=parse(yesterday);if(end<start&&now<end)return true;}
    return false;
}
