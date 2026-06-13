<script setup lang="ts">
import 'leaflet/dist/leaflet.css';
import { onMounted, onUnmounted, watch, shallowRef, nextTick } from 'vue';
import type { Map, Marker, Polyline, CircleMarker } from 'leaflet';

interface Stop {
    sequence_order: number;
    latitude: number | null;
    longitude: number | null;
    customer_name: string | null;
    status: string;
}

const props = defineProps<{
    currentLat: number | null;
    currentLng: number | null;
    stops: Stop[];
}>();

const mapEl = shallowRef<HTMLElement | null>(null);
let L: typeof import('leaflet');
let map: Map;

// Current position — pulsing circle
let myOuter: CircleMarker | null = null;
let myInner: CircleMarker | null = null;

// Trail — accumulated GPS positions
const posHistory: [number, number][] = [];
let trailLine: Polyline | null = null;

// Route ahead — dashed line to remaining stops
let routeAheadLine: Polyline | null = null;
const stopMarkers: Marker[] = [];

// ── Icon ─────────────────────────────────────────────────────────

function makeStopIcon(seq: number, done: boolean): import('leaflet').DivIcon {
    const bg = done ? '#9ca3af' : '#ef4444';
    return L.divIcon({
        className: '',
        html: `<div style="
            background:${bg};color:#fff;
            width:22px;height:22px;
            border-radius:50% 50% 50% 0;transform:rotate(-45deg);
            display:flex;align-items:center;justify-content:center;
            box-shadow:0 1px 4px rgba(0,0,0,.3);border:2px solid #fff;
        "><span style="transform:rotate(45deg);font-size:10px;font-weight:700;font-family:system-ui">${seq}</span></div>`,
        iconSize: [22, 22], iconAnchor: [11, 22],
    });
}

// ── Update current position marker + trail ────────────────────────

function updatePosition() {
    if (!map || !props.currentLat || !props.currentLng) return;
    const ll: [number, number] = [props.currentLat, props.currentLng];

    // Append to trail (deduplicate)
    const last = posHistory[posHistory.length - 1];
    if (!last || Math.abs(last[0] - ll[0]) > 0.00001 || Math.abs(last[1] - ll[1]) > 0.00001) {
        posHistory.push(ll);
        if (posHistory.length > 120) posHistory.shift();
    }

    // Draw/update trail (solid indigo line)
    if (posHistory.length > 1) {
        if (trailLine) {
            trailLine.setLatLngs(posHistory);
        } else {
            trailLine = L.polyline(posHistory, { color: '#6366f1', weight: 3, opacity: 0.8 }).addTo(map);
        }
    }

    // Pulsing position marker
    if (myOuter) {
        myOuter.setLatLng(ll);
        myInner!.setLatLng(ll);
    } else {
        // Outer ring (semi-transparent, larger)
        myOuter = L.circleMarker(ll, {
            radius: 14, color: '#6366f1', fillColor: '#6366f1',
            fillOpacity: 0.15, weight: 2, opacity: 0.5,
        }).addTo(map);
        // Inner solid dot
        myInner = L.circleMarker(ll, {
            radius: 7, color: '#fff', fillColor: '#6366f1',
            fillOpacity: 1, weight: 2.5,
        }).addTo(map).bindPopup('<b>Vị trí của tôi</b>');
    }

    // Smooth pan to current position
    map.panTo(ll, { animate: true, duration: 0.6 });

    // Redraw route-ahead
    drawRouteAhead(ll);
}

// ── Draw dashed line: current pos → remaining stops in sequence ───

function drawRouteAhead(from: [number, number]) {
    routeAheadLine?.remove();
    const pending = [...props.stops]
        .filter(s => s.latitude && s.longitude && !['delivered', 'failed'].includes(s.status))
        .sort((a, b) => a.sequence_order - b.sequence_order);

    const latlngs: [number, number][] = [from, ...pending.map(s => [s.latitude!, s.longitude!] as [number, number])];
    if (latlngs.length < 2) return;
    routeAheadLine = L.polyline(latlngs, {
        color: '#f59e0b', weight: 2.5, opacity: 0.7, dashArray: '8,5',
    }).addTo(map);
}

// ── Sync stop markers ─────────────────────────────────────────────

function syncStops() {
    stopMarkers.forEach(m => m.remove());
    stopMarkers.length = 0;
    [...props.stops]
        .sort((a, b) => a.sequence_order - b.sequence_order)
        .forEach(s => {
            if (!s.latitude || !s.longitude) return;
            const done = ['delivered', 'failed'].includes(s.status);
            const m = L.marker([s.latitude, s.longitude], { icon: makeStopIcon(s.sequence_order, done) })
                .addTo(map)
                .bindPopup(`<b>${s.sequence_order}.</b> ${s.customer_name ?? ''}`);
            stopMarkers.push(m);
        });

    // Re-draw route if we have position
    if (props.currentLat && props.currentLng) {
        drawRouteAhead([props.currentLat, props.currentLng]);
    }
}

// ── Lifecycle ─────────────────────────────────────────────────────

onMounted(async () => {
    L = await import('leaflet');
    (L.Icon.Default.prototype as any)._getIconUrl = undefined;

    const center: [number, number] = props.currentLat && props.currentLng
        ? [props.currentLat, props.currentLng]
        : props.stops.find(s => s.latitude && s.longitude)
            ? [props.stops.find(s => s.latitude)!.latitude!, props.stops.find(s => s.longitude)!.longitude!]
            : [10.7769, 106.7009];

    map = L.map(mapEl.value!, {
        zoomControl: false,
        attributionControl: false,
        dragging: true,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        touchZoom: true,
    }).setView(center, 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

    syncStops();
    updatePosition();

    await nextTick();
    setTimeout(() => map.invalidateSize(), 100);
});

onUnmounted(() => map?.remove());

watch(() => [props.currentLat, props.currentLng], updatePosition);
watch(() => props.stops, syncStops, { deep: true });
</script>

<template>
    <div class="relative w-full rounded-xl overflow-hidden border" style="height: 200px;">
        <div ref="mapEl" class="w-full h-full" />
        <!-- Legend overlay -->
        <div class="absolute bottom-2 left-2 z-[1000] flex gap-2 text-[11px] pointer-events-none">
            <span class="bg-white/90 dark:bg-black/70 rounded-full px-2 py-0.5 flex items-center gap-1">
                <span class="inline-block w-3 h-0.5 bg-indigo-500 rounded"></span> Đường đã đi
            </span>
            <span class="bg-white/90 dark:bg-black/70 rounded-full px-2 py-0.5 flex items-center gap-1">
                <span class="inline-block w-3 h-0.5 bg-amber-400 rounded" style="border-top:2px dashed #f59e0b;height:0"></span> Còn lại
            </span>
        </div>
    </div>
</template>
