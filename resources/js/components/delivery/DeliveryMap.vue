<script setup lang="ts">
import { onMounted, onUnmounted, watch, ref, nextTick } from 'vue';

export interface ShipperMapData {
    id: number;
    name: string;
    lat: number;
    lng: number;
    vehicle_type: string;
    has_gps: boolean;
    trail?: Array<{ lat: number; lng: number }>;
    active_batch?: {
        id: number;
        items: Array<{
            sequence_order: number;
            status: string;
            lat: number | null;
            lng: number | null;
            address: string | null;
            customer_name: string | null;
            eta: string | null;
        }>;
    } | null;
}

export interface OrderMapData {
    id: number;
    lat: number;
    lng: number;
    address: string;
    customer_name: string;
    status: string;
    sequence?: number;
}

const props = withDefaults(defineProps<{
    shippers?: ShipperMapData[];
    orders?: OrderMapData[];
    center?: [number, number];
    zoom?: number;
    height?: string;
    focusShipperId?: number | null;
}>(), {
    shippers: () => [],
    orders: () => [],
    center: () => [10.7769, 106.7009],
    zoom: 13,
    height: '500px',
    focusShipperId: null,
});

const emit = defineEmits<{
    (e: 'click-shipper', id: number): void;
    (e: 'click-order', id: number): void;
}>();

const mapContainer = ref<HTMLDivElement | null>(null);
let map: any = null;
let L: any = null;

// Layer registries
const shipperMarkers: Record<number, any> = {};
const trailLines: Record<number, any> = {};
const orderMarkers: Record<number, any> = {};
const routeLines: Record<number, any[]> = {};
const stopMarkers: Record<number, any[]> = {};

const VEHICLE_ICONS: Record<string, string> = {
    bike:      '🚲',
    motorbike: '🏍️',
    car:       '🚗',
};

// Batch colors: one per shipper
const BATCH_COLORS = ['#7c3aed', '#0891b2', '#059669', '#d97706', '#dc2626', '#7c3aed'];
let colorIdx = 0;
const shipperColors: Record<number, string> = {};

const STATUS_ICON: Record<string, string> = {
    pending:   '#f59e0b',
    assigned:  '#3b82f6',
    picked_up: '#8b5cf6',
    delivered: '#10b981',
    failed:    '#ef4444',
};

async function initMap() {
    if (!mapContainer.value) {
return;
}

    L = (await import('leaflet')).default;
    await import('leaflet/dist/leaflet.css');

    map = L.map(mapContainer.value, {
        zoomControl: true,
        attributionControl: false,
    }).setView(props.center, props.zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap',
    }).addTo(map);

    L.control.attribution({ prefix: false }).addTo(map);

    renderAll();
}

function getShipperColor(shipperId: number): string {
    if (!shipperColors[shipperId]) {
        shipperColors[shipperId] = BATCH_COLORS[colorIdx % BATCH_COLORS.length];
        colorIdx++;
    }

    return shipperColors[shipperId];
}

// ── Shipper markers ──────────────────────────────────────────────────────────
function renderShippers() {
    if (!map || !L) {
return;
}

    // Clean up removed shippers
    Object.keys(shipperMarkers).forEach(id => {
        if (!props.shippers.find(s => s.id === +id)) {
            map.removeLayer(shipperMarkers[+id]);
            delete shipperMarkers[+id];

            if (trailLines[+id]) {
 map.removeLayer(trailLines[+id]); delete trailLines[+id]; 
}
        }
    });

    props.shippers.forEach(s => {
        const color = getShipperColor(s.id);
        const emoji = VEHICLE_ICONS[s.vehicle_type] ?? '🏍️';
        const isActive = props.focusShipperId === s.id;

        const iconHtml = `
          <div class="shipper-pin ${isActive ? 'shipper-pin--active' : ''}" style="--c:${color}">
            ${s.has_gps ? '<span class="shipper-pulse"></span>' : ''}
            <span class="shipper-vehicle">${emoji}</span>
          </div>`;

        const icon = L.divIcon({ html: iconHtml, className: '', iconSize: [40, 40], iconAnchor: [20, 20], popupAnchor: [0, -22] });

        if (shipperMarkers[s.id]) {
            shipperMarkers[s.id].setLatLng([s.lat, s.lng]).setIcon(icon);
        } else {
            shipperMarkers[s.id] = L.marker([s.lat, s.lng], { icon })
                .bindTooltip(`<b>${s.name}</b><br>${VEHICLE_ICONS[s.vehicle_type] ?? ''} ${s.vehicle_type}${s.has_gps ? ' · 🟢 GPS' : ' · ⚫ offline'}`, { direction: 'top' })
                .on('click', () => emit('click-shipper', s.id))
                .addTo(map);
        }

        // Trail
        if (s.trail && s.trail.length > 1) {
            const coords = s.trail.map(p => [p.lat, p.lng]);

            if (trailLines[s.id]) {
                trailLines[s.id].setLatLngs(coords);
            } else {
                trailLines[s.id] = L.polyline(coords, {
                    color, weight: 2, opacity: 0.45, dashArray: '5 5',
                }).addTo(map);
            }
        }

        // Route + stop markers for active batch
        renderBatchRoute(s);
    });
}

function renderBatchRoute(s: ShipperMapData) {
    if (!map || !L) {
return;
}

    // Clear existing route/stops for this shipper
    (routeLines[s.id] ?? []).forEach(l => map.removeLayer(l));
    (stopMarkers[s.id] ?? []).forEach(l => map.removeLayer(l));
    routeLines[s.id] = [];
    stopMarkers[s.id] = [];

    if (!s.active_batch) {
return;
}

    const color = getShipperColor(s.id);
    const items = [...s.active_batch.items].sort((a, b) => a.sequence_order - b.sequence_order);
    const validItems = items.filter(i => i.lat && i.lng);

    if (validItems.length === 0) {
return;
}

    // Build route coords: shipper pos → each stop in sequence
    const routeCoords: [number, number][] = [[s.lat, s.lng]];
    validItems.forEach(i => routeCoords.push([i.lat!, i.lng!]));

    // Draw segments with different style for completed vs remaining
    let prevCoord: [number, number] = [s.lat, s.lng];
    validItems.forEach(item => {
        const coord: [number, number] = [item.lat!, item.lng!];
        const isDone = item.status === 'delivered' || item.status === 'failed';
        const line = L.polyline([prevCoord, coord], {
            color: isDone ? '#9ca3af' : color,
            weight: isDone ? 2 : 3,
            opacity: isDone ? 0.4 : 0.85,
            dashArray: isDone ? '6 4' : undefined,
        }).addTo(map);
        routeLines[s.id].push(line);
        prevCoord = coord;
    });

    // Draw numbered stop markers
    validItems.forEach(item => {
        const isDone  = item.status === 'delivered';
        const isFail  = item.status === 'failed';
        const isActive = item.status === 'picked_up';
        const bg = isDone ? '#10b981' : isFail ? '#ef4444' : isActive ? '#8b5cf6' : color;

        const markerHtml = `
          <div class="stop-marker" style="background:${bg};border-color:white">
            ${isDone ? '✓' : isFail ? '✗' : item.sequence_order}
          </div>`;

        const stopIcon = L.divIcon({ html: markerHtml, className: '', iconSize: [26, 26], iconAnchor: [13, 26], popupAnchor: [0, -28] });
        const etaStr = item.eta ? formatEtaMs(new Date(item.eta).getTime() - Date.now()) : '';
        const marker = L.marker([item.lat!, item.lng!], { icon: stopIcon })
            .bindTooltip(`<b>#${item.sequence_order} ${item.customer_name ?? ''}</b><br>${item.address ?? ''} ${etaStr ? `· ETA ${etaStr}` : ''}`, { direction: 'top' })
            .addTo(map);
        stopMarkers[s.id].push(marker);
    });
}

// ── Unassigned order markers ─────────────────────────────────────────────────
function renderOrders() {
    if (!map || !L) {
return;
}

    Object.keys(orderMarkers).forEach(id => {
        if (!props.orders.find(o => o.id === +id)) {
            map.removeLayer(orderMarkers[+id]);
            delete orderMarkers[+id];
        }
    });

    props.orders.forEach(o => {
        const iconHtml = `<div class="order-dot" style="background:${STATUS_ICON[o.status] ?? '#6b7280'}">📦</div>`;
        const icon = L.divIcon({ html: iconHtml, className: '', iconSize: [28, 28], iconAnchor: [14, 28], popupAnchor: [0, -30] });

        if (orderMarkers[o.id]) {
            orderMarkers[o.id].setLatLng([o.lat, o.lng]).setIcon(icon);
        } else {
            orderMarkers[o.id] = L.marker([o.lat, o.lng], { icon })
                .bindTooltip(`<b>${o.customer_name}</b><br>${o.address}`, { direction: 'top' })
                .on('click', () => emit('click-order', o.id))
                .addTo(map);
        }
    });
}

function renderAll() {
    renderShippers();
    renderOrders();
}

// Focus camera on a specific shipper
watch(() => props.focusShipperId, (id) => {
    if (!map || !id) {
return;
}

    const s = props.shippers.find(s => s.id === id);

    if (s) {
map.flyTo([s.lat, s.lng], 15, { duration: 0.8 });
}

    renderShippers(); // re-render to update active highlight
});

// Fit all visible markers into view
function fitAll() {
    if (!map || !L) {
return;
}

    const coords: [number, number][] = [];
    props.shippers.forEach(s => coords.push([s.lat, s.lng]));
    props.orders.forEach(o => coords.push([o.lat, o.lng]));

    if (coords.length === 0) {
return;
}

    if (coords.length === 1) {
 map.flyTo(coords[0], 15, { duration: 0.7 });

 return; 
}

    map.flyToBounds(L.latLngBounds(coords), { padding: [40, 40], duration: 0.8 });
}

defineExpose({ fitAll });

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(initMap);

onUnmounted(() => {
    if (map) {
 map.remove(); map = null; 
}
});

watch(() => props.shippers, renderShippers, { deep: true });
watch(() => props.orders,   renderOrders,   { deep: true });

// ── Helpers ──────────────────────────────────────────────────────────────────
function formatEtaMs(ms: number): string {
    if (ms <= 0) {
return 'Đang đến';
}

    const m = Math.round(ms / 60000);

    if (m < 60) {
return `~${m}p`;
}

    return `~${Math.floor(m / 60)}h${m % 60}p`;
}
</script>

<template>
    <div style="position:relative;width:100%;" :style="{ height }">
        <div ref="mapContainer" style="width:100%;height:100%;border-radius:8px" />

        <!-- Fit-all button -->
        <button
            title="Hiển thị tất cả"
            style="position:absolute;bottom:32px;left:10px;z-index:1000;background:white;border:2px solid rgba(0,0,0,.15);border-radius:6px;width:30px;height:30px;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 1px 5px rgba(0,0,0,.2);font-size:14px"
            @click="fitAll"
        >⊞</button>

        <!-- Legend -->
        <div style="position:absolute;bottom:32px;right:10px;z-index:1000;background:white;border:1px solid rgba(0,0,0,.12);border-radius:8px;padding:8px 10px;font-size:11px;line-height:1.6;box-shadow:0 1px 5px rgba(0,0,0,.15)">
            <div style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;display:inline-block"></span> Chờ giao</div>
            <div style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:50%;background:#8b5cf6;display:inline-block"></span> Đang lấy</div>
            <div style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:50%;background:#10b981;display:inline-block"></span> Đã giao</div>
            <div style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:50%;background:#ef4444;display:inline-block"></span> Thất bại</div>
        </div>
    </div>
</template>

<style>
.shipper-pin {
    position: relative;
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,.3));
}
.shipper-pin--active .shipper-vehicle {
    outline: 3px solid var(--c); border-radius: 50%;
}
.shipper-pulse {
    position: absolute;
    width: 40px; height: 40px; border-radius: 50%;
    background: color-mix(in srgb, var(--c) 30%, transparent);
    animation: gps-pulse 2s infinite;
}
@keyframes gps-pulse {
    0%   { transform: scale(.7); opacity: 1; }
    100% { transform: scale(1.8); opacity: 0; }
}
.shipper-vehicle { font-size: 22px; z-index: 1; }
.stop-marker {
    width: 26px; height: 26px; border-radius: 50% 50% 50% 0;
    transform: rotate(-45deg);
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 10px; font-weight: 700;
    border: 2px solid white;
    box-shadow: 0 2px 6px rgba(0,0,0,.25);
}
.stop-marker > * { transform: rotate(45deg); }
.order-dot {
    width: 28px; height: 28px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px;
    border: 2px solid white;
    box-shadow: 0 1px 4px rgba(0,0,0,.2);
}
</style>
