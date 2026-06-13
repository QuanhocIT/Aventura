<script setup lang="ts">
import 'leaflet/dist/leaflet.css';
import { onMounted, onUnmounted, watch, shallowRef, nextTick } from 'vue';
import type { Map, Marker, Polyline, CircleMarker, DivIcon } from 'leaflet';

interface ShipperPin {
    id: number;
    name: string;
    latitude: number | null;
    longitude: number | null;
    vehicle_type: string;
    current_load: { orders: number };
    max_orders_per_batch: number;
    last_seen_at: string | null;
}

interface OrderPin {
    id: number;
    order_number: string;
    delivery: { customer_name: string; address: string; latitude: number | null; longitude: number | null } | null;
}

interface BatchRoute {
    id: number;
    shipper?: { id: number } | null;
    optimized_route: Array<{ latitude: number; longitude: number; sequence: number; customer_name?: string }> | null;
    status: string;
}

const props = defineProps<{
    shippers: ShipperPin[];
    orders: OrderPin[];
    batches: BatchRoute[];
    center: [number, number];
    selectedOrderIds: number[];
}>();

const emit = defineEmits<{ orderClick: [id: number] }>();

// Colors — each shipper gets a stable slot
const PALETTE = ['#6366f1', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316', '#84cc16'];
const shipperColorSlot = new Map<number, number>();
function colorOf(shipperId: number): string {
    if (!shipperColorSlot.has(shipperId)) {
        shipperColorSlot.set(shipperId, shipperColorSlot.size % PALETTE.length);
    }
    return PALETTE[shipperColorSlot.get(shipperId)!];
}

const mapEl = shallowRef<HTMLElement | null>(null);
let L: typeof import('leaflet');
let map: Map;

// Markers
const shipperMarkers = new Map<number, Marker>();
const orderMarkers  = new Map<number, Marker>();
const routeLines: Polyline[] = [];

// Trail tracking — accumulated from prop watches
const shipperTrails   = new Map<number, [number, number][]>();
const trailPolylines  = new Map<number, Polyline>();

// ── Icon builders ──────────────────────────────────────────────────

function makeShipperIcon(name: string, loadRatio: number, color: string, live: boolean): DivIcon {
    const initials = name.split(' ').map(w => w[0]).slice(-2).join('').toUpperCase();
    const bg = loadRatio >= 0.9 ? '#ef4444' : loadRatio >= 0.6 ? '#f59e0b' : color;
    const ring = live
        ? `<div class="dlv-ping" style="border-color:${bg}"></div>`
        : '';
    return L.divIcon({
        className: 'dlv-shipper-icon',
        html: `${ring}<div class="dlv-avatar" style="background:${bg}">${initials}</div>`,
        iconSize: [40, 40],
        iconAnchor: [20, 20],
    });
}

function makeOrderIcon(num: number, selected: boolean): DivIcon {
    const bg = selected ? '#f59e0b' : '#ef4444';
    return L.divIcon({
        className: '',
        html: `<div style="
            background:${bg};color:#fff;
            width:28px;height:28px;
            border-radius:50% 50% 50% 0;transform:rotate(-45deg);
            display:flex;align-items:center;justify-content:center;
            box-shadow:0 2px 6px rgba(0,0,0,.3);border:2px solid #fff;
        "><span style="transform:rotate(45deg);font-size:11px;font-weight:700;font-family:system-ui">${num}</span></div>`,
        iconSize: [28, 28],
        iconAnchor: [14, 28],
    });
}

// ── Trail management ───────────────────────────────────────────────

function appendTrail(id: number, lat: number, lng: number) {
    if (!map) return;
    const trail = shipperTrails.get(id) ?? [];
    const last = trail[trail.length - 1];
    // Deduplicate — only append if position actually changed
    if (last && Math.abs(last[0] - lat) < 0.00001 && Math.abs(last[1] - lng) < 0.00001) return;

    trail.push([lat, lng]);
    if (trail.length > 80) trail.shift();
    shipperTrails.set(id, trail);

    if (trail.length < 2) return;
    const color = colorOf(id);
    if (trailPolylines.has(id)) {
        trailPolylines.get(id)!.setLatLngs(trail);
    } else {
        const poly = L.polyline(trail, { color, weight: 3, opacity: 0.75 }).addTo(map);
        // Trail goes below route lines
        poly.bringToBack();
        trailPolylines.set(id, poly);
    }
}

// ── Sync functions ─────────────────────────────────────────────────

function syncShipperMarkers() {
    props.shippers.forEach(s => {
        if (!s.latitude || !s.longitude) return;
        const ratio = s.max_orders_per_batch > 0 ? s.current_load.orders / s.max_orders_per_batch : 0;
        const color = colorOf(s.id);
        const live  = !!s.last_seen_at && (Date.now() - new Date(s.last_seen_at).getTime()) < 3 * 60_000;
        const icon  = makeShipperIcon(s.name, ratio, color, live);

        // Record GPS position into trail
        appendTrail(s.id, s.latitude, s.longitude);

        if (shipperMarkers.has(s.id)) {
            const m = shipperMarkers.get(s.id)!;
            m.setLatLng([s.latitude, s.longitude]);
            m.setIcon(icon);
        } else {
            const m = L.marker([s.latitude, s.longitude], { icon, zIndexOffset: 1000 })
                .addTo(map)
                .bindPopup(`
                    <div style="font-family:system-ui;min-width:140px">
                        <b>${s.name}</b><br>
                        <span style="color:#6b7280;font-size:12px">
                            ${s.current_load.orders}/${s.max_orders_per_batch} đơn
                            ${live ? '· <span style="color:#22c55e">● Live</span>' : ''}
                        </span>
                    </div>
                `);
            shipperMarkers.set(s.id, m);
        }
    });
    // Remove stale
    shipperMarkers.forEach((m, id) => {
        if (!props.shippers.find(s => s.id === id)) {
            m.remove();
            shipperMarkers.delete(id);
            trailPolylines.get(id)?.remove();
            trailPolylines.delete(id);
        }
    });
}

function syncOrderMarkers() {
    let idx = 1;
    props.orders.forEach(o => {
        const d = o.delivery;
        if (!d?.latitude || !d?.longitude) return;
        const selected = props.selectedOrderIds.includes(o.id);
        const icon = makeOrderIcon(idx, selected);
        if (orderMarkers.has(o.id)) {
            orderMarkers.get(o.id)!.setIcon(icon);
        } else {
            const m = L.marker([d.latitude, d.longitude], { icon })
                .addTo(map)
                .bindPopup(`
                    <div style="font-family:system-ui">
                        <b>#${o.order_number}</b><br>
                        ${d.customer_name ?? ''}<br>
                        <small style="color:#6b7280">${d.address}</small>
                    </div>
                `);
            m.on('click', () => emit('orderClick', o.id));
            orderMarkers.set(o.id, m);
        }
        idx++;
    });
    orderMarkers.forEach((m, id) => {
        if (!props.orders.find(o => o.id === id)) {
            m.remove();
            orderMarkers.delete(id);
        }
    });
}

function drawRoutes() {
    routeLines.forEach(l => l.remove());
    routeLines.length = 0;
    props.batches.forEach(batch => {
        if (!batch.optimized_route?.length) return;
        // Use same color as the shipper
        const color = batch.shipper?.id ? colorOf(batch.shipper.id) : '#94a3b8';
        const latlngs = [...batch.optimized_route]
            .sort((a, b) => a.sequence - b.sequence)
            .map(s => [s.latitude, s.longitude] as [number, number]);
        const line = L.polyline(latlngs, {
            color, weight: 3, opacity: 0.45, dashArray: '10,6',
        }).addTo(map);
        routeLines.push(line);
    });
}

// ── Exposed: pan map to a shipper ─────────────────────────────────

function focusShipper(shipperId: number) {
    const s = props.shippers.find(x => x.id === shipperId);
    if (s?.latitude && s?.longitude && map) {
        map.flyTo([s.latitude, s.longitude], 15, { duration: 0.8 });
        shipperMarkers.get(shipperId)?.openPopup();
    }
}

defineExpose({ focusShipper });

// ── Lifecycle ──────────────────────────────────────────────────────

onMounted(async () => {
    L = await import('leaflet');
    (L.Icon.Default.prototype as any)._getIconUrl = undefined;

    map = L.map(mapEl.value!, { zoomControl: true, attributionControl: false })
        .setView(props.center, 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

    // Restaurant pin
    L.circleMarker(props.center, { radius: 10, color: '#fff', fillColor: '#6366f1', fillOpacity: 1, weight: 3 })
        .addTo(map)
        .bindPopup('<b>📍 Nhà hàng</b>');

    syncShipperMarkers();
    syncOrderMarkers();
    drawRoutes();

    await nextTick();
    setTimeout(() => map.invalidateSize(), 100);
});

onUnmounted(() => map?.remove());

watch(() => props.shippers, syncShipperMarkers, { deep: true });
watch(() => [props.orders, props.selectedOrderIds], syncOrderMarkers, { deep: true });
watch(() => props.batches, drawRoutes, { deep: true });
</script>

<template>
    <div ref="mapEl" class="w-full rounded-b-xl" style="height: 380px;" />
</template>

<style>
/* Shipper marker wrapper */
.dlv-shipper-icon {
    background: transparent !important;
    border: none !important;
    position: relative;
    width: 40px !important;
    height: 40px !important;
}
.dlv-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    border: 3px solid #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,.35);
    font-family: system-ui, sans-serif;
    position: absolute;
    top: 2px; left: 2px;
    z-index: 1;
}
/* Pulse ring for live shippers */
.dlv-ping {
    position: absolute;
    inset: -2px;
    border-radius: 50%;
    border: 2.5px solid;
    opacity: 0;
    animation: dlvPing 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
}
@keyframes dlvPing {
    0%   { transform: scale(1);   opacity: .75; }
    70%  { transform: scale(1.7); opacity: 0; }
    100% { transform: scale(1.7); opacity: 0; }
}
</style>
