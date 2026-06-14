<script setup lang="ts">
import { onMounted, onUnmounted, watch, ref } from 'vue';

const props = defineProps<{
    lat: number;
    lng: number;
    nextStop?: { lat: number; lng: number; address: string } | null;
    trail?: Array<{ lat: number; lng: number }>;
}>();

const mapContainer = ref<HTMLDivElement | null>(null);
let map: any = null;
let L: any = null;
let posMarker: any = null;
let stopMarker: any = null;
let trailLine: any = null;

async function initMap() {
    if (!mapContainer.value) return;
    L = (await import('leaflet')).default;
    await import('leaflet/dist/leaflet.css');

    map = L.map(mapContainer.value, { zoomControl: false, attributionControl: false })
        .setView([props.lat, props.lng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

    posMarker = L.marker([props.lat, props.lng], {
        icon: L.divIcon({
            html: '<div style="background:#3b82f6;width:16px;height:16px;border-radius:50%;border:3px solid white;box-shadow:0 0 0 3px rgba(59,130,246,0.4)"></div>',
            className: '', iconSize: [16, 16], iconAnchor: [8, 8],
        }),
    }).addTo(map);

    if (props.nextStop) renderStop();
    if (props.trail) renderTrail();
}

function renderStop() {
    if (!map || !L || !props.nextStop) return;
    if (stopMarker) { map.removeLayer(stopMarker); stopMarker = null; }
    stopMarker = L.marker([props.nextStop.lat, props.nextStop.lng], {
        icon: L.divIcon({
            html: '<div style="background:#ef4444;width:14px;height:14px;border-radius:50%;border:2px solid white"></div>',
            className: '', iconSize: [14, 14], iconAnchor: [7, 7],
        }),
    })
        .bindPopup(props.nextStop.address)
        .addTo(map);
}

function renderTrail() {
    if (!map || !L || !props.trail || props.trail.length < 2) return;
    if (trailLine) { map.removeLayer(trailLine); trailLine = null; }
    trailLine = L.polyline(props.trail.map(p => [p.lat, p.lng]), {
        color: '#3b82f6', weight: 2, opacity: 0.6,
    }).addTo(map);
}

onMounted(initMap);

onUnmounted(() => {
    if (map) { map.remove(); map = null; }
});

watch(() => [props.lat, props.lng], ([lat, lng]) => {
    if (!map || !posMarker) return;
    posMarker.setLatLng([lat as number, lng as number]);
    map.panTo([lat as number, lng as number]);
});

watch(() => props.nextStop, renderStop);
watch(() => props.trail, renderTrail, { deep: true });
</script>

<template>
    <div ref="mapContainer" style="height: 220px; width: 100%; border-radius: 8px;" />
</template>
