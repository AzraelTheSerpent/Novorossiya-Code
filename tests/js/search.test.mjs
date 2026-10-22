import {test} from 'node:test';
import assert from 'node:assert/strict';
import {matches,normalize,openNow,distance} from '../../resources/js/platform/search.js';
test('Russian search handles case, ё and a typo',()=>{
    assert.equal(normalize('Ёлка'),'елка');
    assert.ok(matches({name:'Музей городских историй',tags:['архитектура']},'музей'));
    assert.ok(matches({name:'Мастерская Точка',tags:[]},'мастерска'));
    assert.ok(matches({name:'Музей',tags:[]},'музе'));
    assert.ok(!matches({name:'Кофейня',tags:[]},'парк'));
});
test('Opening hours include overnight and closed schedules',()=>{
    const monday=new Date(2026,9,19,23,0);
    assert.ok(openNow({mon:'20:00-02:00'},monday));
    assert.ok(!openNow({mon:'09:00-18:00'},monday));
    assert.ok(openNow({mon:'24/7'},monday));
    assert.ok(!openNow({},monday));
    assert.ok(openNow({sun:'20:00-02:00',mon:'closed'},new Date(2026,9,19,1,0)));
    assert.ok(!openNow({mon:'20:00-02:00'},new Date(2026,9,19,1,0)));
});
test('Distance is zero at the same point and plausible between cities',()=>{
    assert.equal(distance({lat:48,lng:37},{lat:48,lng:37}),0);
    const d=distance({lat:48,lng:37},{lat:49,lng:37});
    assert.ok(d>110&&d<112);
});
