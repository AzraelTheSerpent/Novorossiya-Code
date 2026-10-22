import * as maplibre from 'maplibre-gl';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
// MapLibre 6 ships a separate ESM worker. Vite must bundle it explicitly.
maplibre.setWorkerUrl(workerUrl);
export * from 'maplibre-gl';
