<script setup>
// A small navigation family inspired by the compass in the supplied brand mark.
defineProps({ name: String, size: { type: Number, default: 20 } });
const paths = {
    discover: ['M12 2.5a9.5 9.5 0 1 0 9.5 9.5', 'm21.5 2.5-5 12-7 0 0-7 12-5Z', 'm9.5 14.5 7-7'],
    map: ['m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2V5Z', 'M9 3v16M15 5v16', 'm17 9 2-2'],
    saved: ['M12 20.5 3.8 12A5.2 5.2 0 0 1 12 5.7 5.2 5.2 0 0 1 20.2 12L12 20.5Z', 'm12 10 2-2'],
    route: ['M6 5h10a4 4 0 0 1 0 8H8a4 4 0 0 0 0 8h10', 'm15 18 3 3-3 2', 'M6 2v6M3 5h6'],
    events: ['M4 5h16v16H4V5Z', 'M8 2v6M16 2v6M4 10h16', 'm9 15 2 2 4-4'],
};
</script>
<template>
    <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path v-for="(path,index) in paths[name] || paths.discover" :key="index" :d="path" />
    </svg>
</template>
