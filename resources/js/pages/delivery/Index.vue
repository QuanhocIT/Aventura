<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, shallowRef } from 'vue';
import {
    Truck, Package, Users, MapPin, CheckCircle, Clock, AlertCircle,
    RefreshCw, Route, Zap, XCircle, ChevronDown, ChevronUp, Phone,
    Navigation, Ban, Crosshair, Sparkles, Timer
} from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppShell from '@/components/AppShell.vue';
import DeliveryMap from '@/components/delivery/DeliveryMap.vue';
import Echo from '@/lib/echo';

interface DeliveryInfo {
    id: number; customer_name: string; phone: string | null;
    address: string; latitude: number | null; longitude: number | null;
    delivery_fee: number; estimated_distance_km: number | null;
}
interface UnassignedOrder {
    id: number; order_number: string; status: string;
    total_amount: number; created_at: string; delivery: DeliveryInfo | null;
}
interface ShipperLoad { orders: number; weight_kg: number; }
interface ActiveShipper {
    id: number; name: string; vehicle_type: string; vehicle_plate: string | null;
    max_orders_per_batch: number; max_capacity_kg: number; current_load: ShipperLoad;
    latitude: number | null; longitude: number | null; last_seen_at: string | null;
    active_batch_id: number | null;
}
interface BatchItem {
    id: number; order_id: number; sequence_order: number; status: string;
    delivered_at: string | null; picked_up_at: string | null;
    address: string | null; customer_name: string | null; phone: string | null;
    estimated_delivery_at: string | null;
}
interface ActiveBatch {
    id: number; status: string; total_orders: number;
    estimated_distance_km: number | null; estimated_duration_minutes: number | null;
    dispatched_at: string | null; completed_at: string | null; created_at: string;
    shipper: { id: number; name: string; vehicle_type: string; vehicle_plate: string | null } | null;
    optimized_route: any[] | null; items: BatchItem[];
    progress: { done: number; total: number };
}
interface RouteStop {
    order_id: number; order_number: string; address: string;
    latitude: number; longitude: number; customer_name: string;
    phone: string | null; sequence: number; estimated_arrival_minutes: number | null;
}
interface Stats {
    pending_orders: number; active_shippers: number; in_progress_batches: number;
    delivered_today: number; failed_today: number;
    avg_delivery_minutes: number | null; on_time_rate: number | null;
}
interface SuggestedShipper {
    id: number; name: string; vehicle_type: string; vehicle_plate: string | null;
    score: number; current_orders: number; available_slots: number;
    max_orders: number; has_gps: boolean; last_seen_at: string | null; distance_factor: number;
}
interface BatchSuggestion {
    order_ids: number[]; order_count: number; radius_km: number;
    centroid: { lat: number; lng: number };
    suggested_shipper: SuggestedShipper | null;
    reason: string;
}

const props = defineProps<{
    unassigned_orders: UnassignedOrder[];
    active_shippers: ActiveShipper[];
    active_batches: ActiveBatch[];
    google_maps_key: string;
    restaurant_address: string;
    restaurant_name: string;
    restaurant_lat: number;
    restaurant_lng: number;
}>();

const unassignedOrders = ref<UnassignedOrder[]>(props.unassigned_orders);
const activeShippers   = ref<ActiveShipper[]>(props.active_shippers);
const activeBatches    = ref<ActiveBatch[]>(props.active_batches);

const selectedOrderIds  = ref<number[]>([]);
const selectedShipperId = ref<number | null>(null);
const showCreateModal   = ref(false);
const showSuggestModal  = ref(false);
const optimizedRoute    = ref<RouteStop[]>([]);
const mapsUrl           = ref('');
const isOptimizing      = ref(false);
const isCreatingBatch   = ref(false);
const isLoadingSuggest  = ref(false);
const suggestedShippers = ref<SuggestedShipper[]>([]);
const batchSuggestions  = ref<BatchSuggestion[]>([]);
const cancellingBatchId = ref<number | null>(null);
const originAddress     = ref(props.restaurant_address);
const orderSearch       = ref('');
const expandedBatches   = ref<Set<number>>(new Set());
const trackingShipperId = ref<number | null>(null);
const mapRef            = shallowRef<{ focusShipper: (id: number) => void } | null>(null);

// Tick every second — powers ETA countdown + urgency age
const now = ref(Date.now());
let tickInterval: ReturnType<typeof setInterval> | null = null;

const stats = ref<Stats>({
    pending_orders:      props.unassigned_orders.length,
    active_shippers:     props.active_shippers.length,
    in_progress_batches: props.active_batches.filter(b => ['dispatched', 'in_progress'].includes(b.status)).length,
    delivered_today:     0,
    failed_today:        0,
    avg_delivery_minutes: null,
    on_time_rate: null,
});

const mapCenter = computed<[number, number]>(() => [props.restaurant_lat || 10.7769, props.restaurant_lng || 106.7009]);

const filteredOrders = computed(() =>
    orderSearch.value
        ? unassignedOrders.value.filter(o =>
            o.order_number.includes(orderSearch.value) ||
            o.delivery?.customer_name?.toLowerCase().includes(orderSearch.value.toLowerCase()) ||
            o.delivery?.address?.toLowerCase().includes(orderSearch.value.toLowerCase())
        )
        : unassignedOrders.value
);

// ── ETA & Urgency helpers ────────────────────────────────────────────────────

function etaCountdown(estimatedAt: string | null): string | null {
    if (!estimatedAt) return null;
    const ms = new Date(estimatedAt).getTime() - now.value;
    if (ms <= 0) return 'Quá hạn';
    const totalSec = Math.floor(ms / 1000);
    const min = Math.floor(totalSec / 60);
    const sec = totalSec % 60;
    if (min >= 60) return `${Math.floor(min / 60)}g ${min % 60}p`;
    if (min > 0)   return `${min}p ${sec}s`;
    return `${sec}s`;
}

function etaIsLate(estimatedAt: string | null): boolean {
    if (!estimatedAt) return false;
    return new Date(estimatedAt).getTime() < now.value;
}

function urgencyLevel(order: UnassignedOrder): 'critical' | 'warn' | 'normal' {
    const ageMin = (now.value - new Date(order.created_at).getTime()) / 60_000;
    if (ageMin > 45) return 'critical';
    if (ageMin > 20) return 'warn';
    return 'normal';
}

function waitTime(createdAt: string): string {
    const min = Math.floor((now.value - new Date(createdAt).getTime()) / 60_000);
    if (min < 1) return '<1p';
    if (min < 60) return `${min}p`;
    return `${Math.floor(min / 60)}g${min % 60}p`;
}

// ── Real-time ────────────────────────────────────────────────────────────────

let echoChannel: any = null;
let statsInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    fetchStats();
    statsInterval = setInterval(fetchStats, 60_000);
    tickInterval  = setInterval(() => { now.value = Date.now(); }, 1_000);

    const restaurantId = (window as any).__inertia_page?.props?.auth?.user?.restaurant_id;
    if (!restaurantId) return;

    // IMPORTANT: private() — matches PrivateChannel on the server
    echoChannel = Echo.private(`delivery.${restaurantId}`)
        .listen('.batch.dispatched', () => refreshData())
        .listen('.delivery.status.updated', (data: any) => handleStatusUpdated(data))
        .listen('.delivery.eta.updated',    (data: any) => handleEtaUpdated(data))
        .listen('.shipper.location.updated', (data: any) => {
            const shipper = activeShippers.value.find(s => s.id === data.shipper_id);
            if (shipper) {
                shipper.latitude    = data.latitude;
                shipper.longitude   = data.longitude;
                shipper.last_seen_at = data.timestamp;
            }
            if (trackingShipperId.value === data.shipper_id) {
                mapRef.value?.focusShipper(data.shipper_id);
            }
        });
});

onUnmounted(() => {
    echoChannel?.stopListening('.batch.dispatched');
    echoChannel?.stopListening('.delivery.status.updated');
    echoChannel?.stopListening('.delivery.eta.updated');
    echoChannel?.stopListening('.shipper.location.updated');
    if (statsInterval) clearInterval(statsInterval);
    if (tickInterval)  clearInterval(tickInterval);
});

// In-place status update — no full reload needed
function handleStatusUpdated(data: any) {
    const batch = activeBatches.value.find(b => b.id === data.batch_id);
    if (!batch) { refreshData(); return; }

    const item = batch.items.find(i => i.id === data.item_id);
    if (item) {
        item.status       = data.status;
        item.delivered_at = data.delivered_at ?? null;
        item.picked_up_at = data.picked_up_at ?? null;
    }

    if (data.batch_status) {
        batch.status = data.batch_status;
        if (batch.progress) {
            batch.progress.done  = data.batch_done  ?? batch.progress.done;
            batch.progress.total = data.batch_total ?? batch.progress.total;
        }
    }

    // Remove from active list if terminal
    if (['completed', 'cancelled'].includes(data.batch_status)) {
        setTimeout(() => {
            activeBatches.value = activeBatches.value.filter(b => b.id !== data.batch_id);
        }, 3_000);
    }

    fetchStats();
}

// In-place ETA update — no reload needed
function handleEtaUpdated(data: any) {
    const batch = activeBatches.value.find(b => b.id === data.batch_id);
    if (batch) {
        batch.optimized_route = data.updated_route;
        // Sync estimated_delivery_at on items from updated route
        const routeMap = new Map<number, any>((data.updated_route as any[]).map((r: any) => [r.order_id, r]));
        batch.items.forEach(item => {
            const stop = routeMap.get(item.order_id);
            if (stop?.estimated_delivery_at) item.estimated_delivery_at = stop.estimated_delivery_at;
        });
    }
}

// ── Data fetching ────────────────────────────────────────────────────────────

async function fetchStats() {
    try {
        const res = await fetch('/delivery/api/stats', { headers: { 'X-CSRF-TOKEN': getCsrf() } });
        if (res.ok) {
            const data = await res.json().catch(() => null);
            if (data) stats.value = data;
        }
    } catch {}
}

function refreshData() {
    router.reload({ only: ['unassigned_orders', 'active_shippers', 'active_batches'] });
    fetchStats();
}

async function fetchBatchSuggestions() {
    isLoadingSuggest.value = true;
    try {
        const res = await fetch('/delivery/api/suggest-batches', { headers: { 'X-CSRF-TOKEN': getCsrf() } });
        if (res.ok) {
            const data = await res.json();
            batchSuggestions.value = data.suggestions ?? [];
            showSuggestModal.value = true;
        }
    } catch {}
    finally { isLoadingSuggest.value = false; }
}

function applySuggestion(s: BatchSuggestion) {
    selectedOrderIds.value  = [...s.order_ids];
    if (s.suggested_shipper) selectedShipperId.value = s.suggested_shipper.id;
    showSuggestModal.value = false;
    optimizeSelectedRoute();
}

// ── Actions ──────────────────────────────────────────────────────────────────

function toggleOrderSelect(orderId: number) {
    const idx = selectedOrderIds.value.indexOf(orderId);
    if (idx === -1) selectedOrderIds.value.push(orderId);
    else selectedOrderIds.value.splice(idx, 1);
}

function toggleBatch(id: number) {
    if (expandedBatches.value.has(id)) expandedBatches.value.delete(id);
    else expandedBatches.value.add(id);
}

function trackShipper(shipperId: number | undefined) {
    if (!shipperId) return;
    if (trackingShipperId.value === shipperId) {
        trackingShipperId.value = null;
    } else {
        trackingShipperId.value = shipperId;
        mapRef.value?.focusShipper(shipperId);
    }
}

async function optimizeSelectedRoute() {
    if (!selectedOrderIds.value.length) return;
    isOptimizing.value = true;
    try {
        const res = await fetch('/delivery/api/optimize-route', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ order_ids: selectedOrderIds.value, origin_address: originAddress.value }),
        });
        const data = await res.json();
        if (!res.ok) { alert(data.message || 'Lỗi tối ưu route'); return; }
        optimizedRoute.value = data.optimized_route;
        mapsUrl.value        = data.maps_url;

        const res2 = await fetch('/delivery/api/suggest-shippers', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ order_ids: selectedOrderIds.value }),
        });
        suggestedShippers.value = (await res2.json()).shippers ?? [];
        showCreateModal.value   = true;
    } catch { alert('Lỗi kết nối'); }
    finally { isOptimizing.value = false; }
}

async function createAndDispatchBatch() {
    if (!selectedShipperId.value) { alert('Vui lòng chọn shipper'); return; }
    isCreatingBatch.value = true;
    try {
        const res = await fetch('/delivery/api/batches', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ order_ids: selectedOrderIds.value, shipper_id: selectedShipperId.value }),
        });
        const data = await res.json();
        if (!res.ok) { alert(data.message || 'Lỗi tạo batch'); return; }
        const dispatchRes = await fetch(`/delivery/api/batches/${data.batch.id}/dispatch`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        });
        if (!dispatchRes.ok) { const d = await dispatchRes.json().catch(() => ({})); alert(d.message || 'Không thể dispatch batch'); return; }
        showCreateModal.value   = false;
        selectedOrderIds.value  = [];
        selectedShipperId.value = null;
        optimizedRoute.value    = [];
        refreshData();
    } catch { alert('Lỗi kết nối'); }
    finally { isCreatingBatch.value = false; }
}

async function cancelBatch(batchId: number) {
    if (!confirm('Hủy batch này? Các đơn sẽ quay về trạng thái chờ gán.')) return;
    cancellingBatchId.value = batchId;
    try {
        const res = await fetch(`/delivery/api/batches/${batchId}/cancel`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        });
        if (res.ok) refreshData();
        else { const d = await res.json(); alert(d.message || 'Không thể hủy batch'); }
    } catch { alert('Lỗi kết nối'); }
    finally { cancellingBatchId.value = null; }
}

function getCsrf(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

// ── View helpers ─────────────────────────────────────────────────────────────

function gpsAge(lastSeen: string | null): 'fresh' | 'stale' | 'none' {
    if (!lastSeen) return 'none';
    return (now.value - new Date(lastSeen).getTime()) < 2 * 60 * 1000 ? 'fresh' : 'stale';
}

function batchProgress(batch: ActiveBatch): { done: number; total: number } {
    if (batch.progress) return batch.progress;
    return { done: batch.items.filter(i => ['delivered', 'failed'].includes(i.status)).length, total: batch.items.length };
}

const vehicleLabel: Record<string, string> = { bike: '🚲', motorbike: '🛵', car: '🚗' };
const vehicleFull:  Record<string, string> = { bike: '🚲 Xe đạp', motorbike: '🛵 Xe máy', car: '🚗 Ô tô' };

const statusBadge: Record<string, { label: string; cls: string }> = {
    draft:       { label: 'Nháp',       cls: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' },
    dispatched:  { label: 'Đã giao',    cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' },
    in_progress: { label: 'Đang giao',  cls: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300' },
    completed:   { label: 'Hoàn thành', cls: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' },
    cancelled:   { label: 'Đã hủy',     cls: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' },
};

const itemIcon: Record<string, string> = {
    pending: '⏳', picked_up: '🔵', delivered: '✅', failed: '❌',
};
</script>

<template>
    <AppShell>
        <Head title="Điều phối Giao hàng" />

        <div class="flex flex-col gap-5 p-5 min-h-screen">

            <!-- ── Header ── -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <Truck class="h-6 w-6 text-primary" />
                        Điều phối Giao hàng
                    </h1>
                    <p class="text-sm text-muted-foreground">Smart Routing & Real-time Dispatch · {{ restaurant_name }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Departure address -->
                    <div class="flex items-center gap-1.5 bg-muted/60 border rounded-lg px-3 py-2 w-56 shrink-0">
                        <MapPin class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] text-muted-foreground leading-none mb-0.5">Điểm xuất phát</p>
                            <input v-model="originAddress" type="text"
                                :placeholder="restaurant_name || 'Địa chỉ nhà hàng'"
                                class="w-full text-xs bg-transparent border-none outline-none"
                                title="Địa chỉ được dùng khi tính route Google Maps" />
                        </div>
                    </div>
                    <Button variant="outline" size="sm" @click="refreshData">
                        <RefreshCw class="h-4 w-4 mr-1" /> Làm mới
                    </Button>
                    <!-- K-means++ batch suggestion -->
                    <Button variant="outline" size="sm"
                        :disabled="unassignedOrders.length === 0 || isLoadingSuggest"
                        @click="fetchBatchSuggestions"
                        class="text-violet-600 border-violet-300 hover:bg-violet-50 dark:hover:bg-violet-950/30">
                        <Sparkles class="h-4 w-4 mr-1" />
                        {{ isLoadingSuggest ? 'Đang phân tích...' : 'Đề xuất nhóm' }}
                    </Button>
                    <Button size="sm"
                        :disabled="selectedOrderIds.length === 0 || isOptimizing"
                        @click="optimizeSelectedRoute">
                        <Route class="h-4 w-4 mr-1" />
                        {{ isOptimizing ? 'Đang tối ưu...' : `Tạo Batch (${selectedOrderIds.length})` }}
                    </Button>
                    <Button variant="outline" size="sm" @click="router.get('/delivery/shippers')">
                        <Users class="h-4 w-4 mr-1" /> Shippers
                    </Button>
                </div>
            </div>

            <!-- ── KPI Cards ── -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-xl border bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800 p-4 flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-amber-100 dark:bg-amber-900/50">
                        <Package class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-amber-700 dark:text-amber-300">{{ stats.pending_orders }}</p>
                        <p class="text-xs text-amber-600/80 dark:text-amber-400/80 font-medium">Đơn chờ gán</p>
                    </div>
                </div>
                <div class="rounded-xl border bg-blue-50 dark:bg-blue-950/30 border-blue-200 dark:border-blue-800 p-4 flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/50">
                        <Users class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-blue-700 dark:text-blue-300">{{ stats.active_shippers }}</p>
                        <p class="text-xs text-blue-600/80 dark:text-blue-400/80 font-medium">Shipper hoạt động</p>
                    </div>
                </div>
                <div class="rounded-xl border bg-violet-50 dark:bg-violet-950/30 border-violet-200 dark:border-violet-800 p-4 flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-violet-100 dark:bg-violet-900/50">
                        <Truck class="h-5 w-5 text-violet-600 dark:text-violet-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-violet-700 dark:text-violet-300">{{ stats.in_progress_batches }}</p>
                        <p class="text-xs text-violet-600/80 dark:text-violet-400/80 font-medium">Đang giao</p>
                    </div>
                </div>
                <div class="rounded-xl border bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-800 p-4 flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-green-100 dark:bg-green-900/50">
                        <CheckCircle class="h-5 w-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-green-700 dark:text-green-300">{{ stats.delivered_today }}</p>
                        <p class="text-xs text-green-600/80 dark:text-green-400/80 font-medium">
                            Đã giao hôm nay
                            <span v-if="stats.avg_delivery_minutes" class="opacity-70"> · TB {{ stats.avg_delivery_minutes }}p</span>
                            <span v-if="stats.on_time_rate !== null" class="opacity-70"> · ĐH {{ stats.on_time_rate }}%</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- ── Main 2-column layout ── -->
            <div class="grid grid-cols-1 xl:grid-cols-[35%_65%] gap-4 flex-1">

                <!-- LEFT: Orders + Shippers -->
                <div class="flex flex-col gap-4 min-h-0">

                    <!-- Orders panel -->
                    <Card class="flex flex-col min-h-0" style="max-height: 420px">
                        <CardHeader class="pb-2 shrink-0">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-semibold flex items-center gap-2">
                                    <Package class="h-4 w-4 text-amber-500" />
                                    Đơn chờ gán
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                        {{ unassignedOrders.length }}
                                    </span>
                                    <span v-if="selectedOrderIds.length" class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                        {{ selectedOrderIds.length }} đã chọn
                                    </span>
                                </CardTitle>
                                <button v-if="selectedOrderIds.length" @click="selectedOrderIds = []"
                                    class="text-xs text-muted-foreground hover:text-foreground">Bỏ chọn</button>
                            </div>
                            <input v-model="orderSearch" type="text" placeholder="Tìm theo tên, địa chỉ..."
                                class="mt-2 w-full text-xs bg-muted/50 border rounded-md px-2.5 py-1.5 outline-none focus:ring-1 focus:ring-primary" />
                        </CardHeader>
                        <CardContent class="p-0 overflow-y-auto flex-1">
                            <div v-if="filteredOrders.length === 0" class="flex flex-col items-center justify-center py-10 text-muted-foreground text-sm gap-2">
                                <Package class="h-8 w-8 opacity-30" />
                                <p>{{ orderSearch ? 'Không tìm thấy đơn nào' : 'Không có đơn delivery chờ giao' }}</p>
                            </div>
                            <div v-for="order in filteredOrders" :key="order.id"
                                class="flex items-start gap-2.5 px-3 py-2.5 border-b cursor-pointer hover:bg-accent/40 transition-colors select-none border-l-2"
                                :class="{
                                    'bg-primary/5 border-l-primary': selectedOrderIds.includes(order.id),
                                    'border-l-red-400 bg-red-50/40 dark:bg-red-950/10': !selectedOrderIds.includes(order.id) && urgencyLevel(order) === 'critical',
                                    'border-l-amber-400 bg-amber-50/30 dark:bg-amber-950/10': !selectedOrderIds.includes(order.id) && urgencyLevel(order) === 'warn',
                                    'border-l-transparent': urgencyLevel(order) === 'normal' && !selectedOrderIds.includes(order.id),
                                }"
                                @click="toggleOrderSelect(order.id)">
                                <input type="checkbox" class="mt-0.5 cursor-pointer accent-primary"
                                    :checked="selectedOrderIds.includes(order.id)"
                                    @click.stop="toggleOrderSelect(order.id)" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="font-semibold text-sm">#{{ order.order_number }}</span>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <!-- Urgency badge -->
                                            <span v-if="urgencyLevel(order) === 'critical'"
                                                class="text-[10px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 font-medium flex items-center gap-0.5">
                                                <AlertCircle class="h-2.5 w-2.5" /> {{ waitTime(order.created_at) }}
                                            </span>
                                            <span v-else-if="urgencyLevel(order) === 'warn'"
                                                class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 font-medium">
                                                {{ waitTime(order.created_at) }}
                                            </span>
                                            <span v-else class="text-[11px] text-muted-foreground">
                                                {{ new Date(order.created_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) }}
                                            </span>
                                        </div>
                                    </div>
                                    <p class="text-xs font-medium truncate">{{ order.delivery?.customer_name }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate flex items-center gap-1 mt-0.5">
                                        <MapPin class="h-3 w-3 shrink-0" />
                                        {{ order.delivery?.address }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs font-medium text-foreground/80">{{ order.total_amount.toLocaleString('vi-VN') }}đ</span>
                                        <span v-if="order.delivery?.estimated_distance_km" class="text-[11px] text-muted-foreground">
                                            · {{ order.delivery.estimated_distance_km.toFixed(1) }}km
                                        </span>
                                        <span class="text-[11px] text-muted-foreground">
                                            · {{ order.status === 'confirmed' ? 'Đã xác nhận' : 'Đang làm' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Shippers panel -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-semibold flex items-center gap-2">
                                <Users class="h-4 w-4 text-blue-500" />
                                Shipper hoạt động
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                    {{ activeShippers.length }}
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div v-if="activeShippers.length === 0" class="flex flex-col items-center justify-center py-8 text-muted-foreground text-sm gap-2">
                                <Users class="h-7 w-7 opacity-30" />
                                <p>Chưa có shipper active</p>
                            </div>
                            <div v-for="shipper in activeShippers" :key="shipper.id"
                                class="flex items-center gap-3 px-3 py-2.5 border-b last:border-0 hover:bg-accent/30 transition-colors cursor-pointer"
                                @click="trackShipper(shipper.id)">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 text-sm font-bold text-white select-none"
                                    :class="{
                                        'bg-indigo-500': shipper.current_load.orders / shipper.max_orders_per_batch < 0.6,
                                        'bg-amber-500':  shipper.current_load.orders / shipper.max_orders_per_batch >= 0.6 && shipper.current_load.orders / shipper.max_orders_per_batch < 0.9,
                                        'bg-red-500':    shipper.current_load.orders / shipper.max_orders_per_batch >= 0.9,
                                    }">
                                    {{ shipper.name.split(' ').map((w: string) => w[0]).slice(-2).join('').toUpperCase() }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-1">
                                        <p class="font-medium text-sm truncate">{{ shipper.name }}</p>
                                        <div class="flex items-center gap-1 shrink-0">
                                            <span v-if="trackingShipperId === shipper.id"
                                                class="text-[10px] px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 font-medium">
                                                Theo dõi
                                            </span>
                                            <span v-if="shipper.active_batch_id"
                                                class="text-[10px] px-1.5 py-0.5 rounded-full bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300 font-medium">
                                                #{{ shipper.active_batch_id }}
                                            </span>
                                            <div class="w-2 h-2 rounded-full shrink-0"
                                                :class="{
                                                    'bg-green-500 animate-pulse': gpsAge(shipper.last_seen_at) === 'fresh',
                                                    'bg-yellow-400':              gpsAge(shipper.last_seen_at) === 'stale',
                                                    'bg-gray-300 dark:bg-gray-600': gpsAge(shipper.last_seen_at) === 'none',
                                                }"
                                                :title="shipper.last_seen_at ? `GPS: ${new Date(shipper.last_seen_at).toLocaleTimeString('vi-VN')}` : 'Chưa có GPS'" />
                                        </div>
                                    </div>
                                    <p class="text-[11px] text-muted-foreground">
                                        {{ vehicleFull[shipper.vehicle_type] }}
                                        <span v-if="shipper.vehicle_plate"> · {{ shipper.vehicle_plate }}</span>
                                    </p>
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <div class="h-1.5 flex-1 bg-secondary rounded-full overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-500"
                                                :class="{
                                                    'bg-green-500': shipper.current_load.orders / shipper.max_orders_per_batch < 0.6,
                                                    'bg-amber-500': shipper.current_load.orders / shipper.max_orders_per_batch >= 0.6 && shipper.current_load.orders / shipper.max_orders_per_batch < 0.9,
                                                    'bg-red-500':   shipper.current_load.orders / shipper.max_orders_per_batch >= 0.9,
                                                }"
                                                :style="{ width: `${Math.min(shipper.current_load.orders / shipper.max_orders_per_batch * 100, 100)}%` }" />
                                        </div>
                                        <span class="text-[11px] text-muted-foreground shrink-0">
                                            {{ shipper.current_load.orders }}/{{ shipper.max_orders_per_batch }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- RIGHT: Map + Batches -->
                <div class="flex flex-col gap-4 min-h-0">

                    <!-- Leaflet Map -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-semibold flex items-center gap-2">
                                <Navigation class="h-4 w-4 text-violet-500" />
                                Bản đồ Real-time
                                <span class="text-[11px] text-muted-foreground font-normal">OpenStreetMap</span>
                                <span v-if="trackingShipperId" class="ml-auto inline-flex items-center gap-1.5 text-[11px] font-medium text-blue-600 dark:text-blue-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                                    Theo dõi: {{ activeShippers.find(s => s.id === trackingShipperId)?.name ?? '...' }}
                                    <button @click="trackingShipperId = null" class="text-muted-foreground hover:text-foreground ml-0.5">✕</button>
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <DeliveryMap
                                ref="mapRef"
                                :shippers="activeShippers"
                                :orders="unassignedOrders"
                                :batches="activeBatches"
                                :center="mapCenter"
                                :selected-order-ids="selectedOrderIds"
                                @order-click="(id: number) => toggleOrderSelect(id)"
                            />
                        </CardContent>
                    </Card>

                    <!-- Batches accordion -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-semibold flex items-center gap-2">
                                <Truck class="h-4 w-4 text-indigo-500" />
                                Batch đang chạy
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                    {{ activeBatches.length }}
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div v-if="activeBatches.length === 0" class="flex flex-col items-center justify-center py-8 text-muted-foreground text-sm gap-2">
                                <Truck class="h-7 w-7 opacity-30" />
                                <p>Chưa có batch nào đang chạy</p>
                            </div>
                            <div v-for="batch in activeBatches" :key="batch.id" class="border-b last:border-0">
                                <!-- Batch header -->
                                <div class="flex items-center gap-2 px-3 py-3 cursor-pointer hover:bg-accent/30 transition-colors"
                                    @click="toggleBatch(batch.id)">
                                    <component :is="expandedBatches.has(batch.id) ? ChevronUp : ChevronDown"
                                        class="h-4 w-4 text-muted-foreground shrink-0" />
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-semibold text-sm">Batch #{{ batch.id }}</span>
                                            <span class="text-[11px] px-2 py-0.5 rounded-full font-medium"
                                                :class="statusBadge[batch.status]?.cls ?? 'bg-gray-100 text-gray-700'">
                                                {{ statusBadge[batch.status]?.label ?? batch.status }}
                                            </span>
                                            <span class="text-xs text-muted-foreground">
                                                {{ vehicleLabel[batch.shipper?.vehicle_type ?? 'motorbike'] }}
                                                {{ batch.shipper?.name ?? '—' }}
                                                <span v-if="batch.shipper?.vehicle_plate"> · {{ batch.shipper.vehicle_plate }}</span>
                                            </span>
                                        </div>
                                        <!-- Progress bar -->
                                        <div class="flex items-center gap-2 mt-1">
                                            <div class="h-1 flex-1 bg-secondary rounded-full overflow-hidden">
                                                <div class="h-full bg-green-500 rounded-full transition-all duration-500"
                                                    :style="{ width: `${(batchProgress(batch).done / Math.max(batchProgress(batch).total, 1)) * 100}%` }" />
                                            </div>
                                            <span class="text-[11px] text-muted-foreground shrink-0">
                                                {{ batchProgress(batch).done }}/{{ batchProgress(batch).total }}
                                            </span>
                                            <span v-if="batch.estimated_duration_minutes" class="text-[11px] text-muted-foreground shrink-0 flex items-center gap-0.5">
                                                <Clock class="h-3 w-3" /> ~{{ batch.estimated_duration_minutes }}p
                                            </span>
                                        </div>
                                    </div>
                                    <!-- Track shipper -->
                                    <Button v-if="batch.shipper && !['completed', 'cancelled'].includes(batch.status)"
                                        variant="ghost" size="sm"
                                        class="h-7 px-2 shrink-0 transition-colors"
                                        :class="trackingShipperId === batch.shipper.id
                                            ? 'text-blue-600 bg-blue-50 dark:bg-blue-950/30'
                                            : 'text-muted-foreground hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/30'"
                                        :title="trackingShipperId === batch.shipper.id ? 'Đang theo dõi — click để dừng' : 'Theo dõi shipper trên map'"
                                        @click.stop="trackShipper(batch.shipper?.id)">
                                        <Crosshair class="h-3.5 w-3.5" />
                                    </Button>
                                    <!-- Cancel -->
                                    <Button v-if="!['completed', 'cancelled'].includes(batch.status)"
                                        variant="ghost" size="sm"
                                        class="h-7 px-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-950/30 shrink-0"
                                        :disabled="cancellingBatchId === batch.id"
                                        @click.stop="cancelBatch(batch.id)">
                                        <Ban class="h-3.5 w-3.5" />
                                    </Button>
                                </div>

                                <!-- Batch items (expanded) -->
                                <div v-if="expandedBatches.has(batch.id)" class="px-4 pb-3 space-y-1.5 bg-muted/20">
                                    <div v-for="item in [...batch.items].sort((a, b) => a.sequence_order - b.sequence_order)"
                                        :key="item.id"
                                        class="flex items-start gap-2 text-xs py-1.5 rounded-lg px-1"
                                        :class="{
                                            'bg-red-50/60 dark:bg-red-950/20': item.estimated_delivery_at && etaIsLate(item.estimated_delivery_at) && item.status === 'pending',
                                        }">
                                        <span class="w-5 text-center shrink-0 text-lg leading-none">{{ itemIcon[item.status] }}</span>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium truncate">{{ item.customer_name ?? item.address }}</p>
                                            <p v-if="item.address" class="text-muted-foreground truncate">{{ item.address }}</p>
                                        </div>
                                        <div class="shrink-0 flex flex-col items-end gap-0.5">
                                            <span v-if="item.phone" class="text-muted-foreground flex items-center gap-0.5">
                                                <Phone class="h-2.5 w-2.5" />{{ item.phone }}
                                            </span>
                                            <!-- ETA countdown for pending/in-flight items -->
                                            <span v-if="item.estimated_delivery_at && !['delivered','failed'].includes(item.status)"
                                                class="flex items-center gap-0.5 font-mono font-medium text-[11px]"
                                                :class="etaIsLate(item.estimated_delivery_at) ? 'text-red-600' : 'text-primary'">
                                                <Timer class="h-2.5 w-2.5" />
                                                {{ etaCountdown(item.estimated_delivery_at) }}
                                            </span>
                                            <!-- Delivered time -->
                                            <span v-if="item.delivered_at" class="text-green-600 text-[11px]">
                                                ✓ {{ new Date(item.delivered_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) }}
                                            </span>
                                        </div>
                                    </div>
                                    <!-- Maps link -->
                                    <div v-if="batch.optimized_route" class="flex items-center gap-1 mt-2 pt-2 border-t">
                                        <a :href="`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(batch.items.at(-1)?.address ?? '')}`"
                                            target="_blank"
                                            class="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                                            <MapPin class="h-3 w-3" /> Mở Google Maps
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- ── Create Batch Modal ── -->
        <Teleport to="body">
            <div v-if="showCreateModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
                @click.self="showCreateModal = false">
                <div class="bg-background rounded-2xl shadow-2xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-5">
                            <h2 class="text-lg font-bold flex items-center gap-2">
                                <Zap class="h-5 w-5 text-primary" />
                                Tạo & Dispatch Batch
                            </h2>
                            <button @click="showCreateModal = false" class="text-muted-foreground hover:text-foreground">
                                <XCircle class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Route preview -->
                        <div class="mb-5">
                            <p class="text-sm font-semibold mb-2 flex items-center gap-1.5">
                                <Route class="h-4 w-4 text-primary" />
                                Tuyến tối ưu — {{ optimizedRoute.length }} điểm
                            </p>
                            <div class="space-y-1.5 max-h-44 overflow-y-auto pr-1">
                                <div v-for="stop in optimizedRoute" :key="stop.order_id"
                                    class="flex items-start gap-2.5 text-sm p-2.5 bg-muted/60 rounded-lg">
                                    <span class="w-6 h-6 rounded-full bg-primary text-primary-foreground text-xs font-bold flex items-center justify-center shrink-0">
                                        {{ stop.sequence }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-medium truncate">{{ stop.customer_name }}</p>
                                        <p class="text-xs text-muted-foreground truncate">{{ stop.address }}</p>
                                        <p v-if="stop.estimated_arrival_minutes" class="text-xs text-muted-foreground">
                                            ~{{ stop.estimated_arrival_minutes }} phút
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <a v-if="mapsUrl" :href="mapsUrl" target="_blank"
                                class="mt-2.5 inline-flex items-center gap-1 text-xs text-primary hover:underline font-medium">
                                <MapPin class="h-3 w-3" /> Xem trên Google Maps
                            </a>
                        </div>

                        <!-- Shipper selection -->
                        <div class="mb-5">
                            <p class="text-sm font-semibold mb-2 flex items-center gap-1.5">
                                <Users class="h-4 w-4 text-primary" />
                                Chọn Shipper
                            </p>
                            <div v-if="suggestedShippers.length === 0" class="text-sm text-muted-foreground p-3 bg-muted/50 rounded-lg">
                                Không có shipper khả dụng
                            </div>
                            <div class="space-y-2">
                                <label v-for="s in suggestedShippers" :key="s.id"
                                    class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer hover:bg-accent/40 transition-colors"
                                    :class="{ 'border-primary bg-primary/5 shadow-sm': selectedShipperId === s.id }">
                                    <input type="radio" :value="s.id" v-model="selectedShipperId" class="cursor-pointer accent-primary" />
                                    <div class="w-9 h-9 rounded-full bg-indigo-500 text-white text-sm font-bold flex items-center justify-center shrink-0">
                                        {{ s.name.split(' ').map((w: string) => w[0]).slice(-2).join('').toUpperCase() }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-semibold text-sm">{{ s.name }}</span>
                                            <span class="text-[11px] px-1.5 py-0.5 rounded-full bg-primary/10 text-primary font-medium">
                                                Score {{ s.score }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-muted-foreground">
                                            {{ vehicleFull[s.vehicle_type] }}
                                            · {{ s.current_orders }}/{{ s.max_orders }} đơn
                                            · {{ s.available_slots }} slot còn
                                            <span v-if="s.has_gps" class="text-green-600"> · GPS ✓</span>
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="flex gap-2 justify-end">
                            <Button variant="outline" @click="showCreateModal = false">Hủy</Button>
                            <Button :disabled="!selectedShipperId || isCreatingBatch" @click="createAndDispatchBatch">
                                <Zap v-if="!isCreatingBatch" class="h-4 w-4 mr-1.5" />
                                {{ isCreatingBatch ? 'Đang tạo...' : 'Tạo & Dispatch ngay' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Suggest Batches Modal (K-means++) ── -->
        <Teleport to="body">
            <div v-if="showSuggestModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
                @click.self="showSuggestModal = false">
                <div class="bg-background rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[85vh] overflow-y-auto">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h2 class="text-lg font-bold flex items-center gap-2">
                                    <Sparkles class="h-5 w-5 text-violet-500" />
                                    Đề xuất phân nhóm thông minh
                                </h2>
                                <p class="text-xs text-muted-foreground mt-0.5">Phân tích vị trí địa lý bằng K-means++ clustering</p>
                            </div>
                            <button @click="showSuggestModal = false" class="text-muted-foreground hover:text-foreground">
                                <XCircle class="h-5 w-5" />
                            </button>
                        </div>

                        <div v-if="batchSuggestions.length === 0" class="text-center py-8 text-muted-foreground">
                            <Sparkles class="h-8 w-8 mx-auto opacity-30 mb-2" />
                            <p>Không có đủ đơn hoặc shipper để phân nhóm</p>
                        </div>

                        <div class="space-y-4">
                            <div v-for="(s, idx) in batchSuggestions" :key="idx"
                                class="border rounded-xl p-4 hover:border-violet-300 transition-colors">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="w-6 h-6 rounded-full bg-violet-500 text-white text-xs font-bold flex items-center justify-center">
                                                {{ idx + 1 }}
                                            </span>
                                            <span class="font-semibold text-sm">Nhóm {{ idx + 1 }}</span>
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                                                {{ s.order_count }} đơn · bán kính {{ s.radius_km }}km
                                            </span>
                                        </div>
                                        <p class="text-xs text-muted-foreground mb-2">{{ s.reason }}</p>
                                        <!-- Suggested shipper -->
                                        <div v-if="s.suggested_shipper"
                                            class="flex items-center gap-2 bg-muted/50 rounded-lg p-2.5">
                                            <div class="w-7 h-7 rounded-full bg-indigo-500 text-white text-xs font-bold flex items-center justify-center shrink-0">
                                                {{ s.suggested_shipper.name.split(' ').map((w: string) => w[0]).slice(-2).join('').toUpperCase() }}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium truncate">{{ s.suggested_shipper.name }}</p>
                                                <p class="text-[11px] text-muted-foreground">
                                                    {{ vehicleFull[s.suggested_shipper.vehicle_type] }}
                                                    · còn {{ s.suggested_shipper.available_slots }} slot
                                                    · điểm {{ s.suggested_shipper.score }}
                                                </p>
                                            </div>
                                        </div>
                                        <div v-else class="text-xs text-muted-foreground bg-muted/50 rounded-lg p-2.5">
                                            Không tìm được shipper phù hợp
                                        </div>
                                    </div>
                                    <Button size="sm" @click="applySuggestion(s)"
                                        :disabled="!s.suggested_shipper"
                                        class="shrink-0">
                                        <Route class="h-3.5 w-3.5 mr-1" />
                                        Áp dụng
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end mt-4">
                            <Button variant="outline" @click="showSuggestModal = false">Đóng</Button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppShell>
</template>
