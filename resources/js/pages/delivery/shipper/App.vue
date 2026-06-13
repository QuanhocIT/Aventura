<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import {
    MapPin, CheckCircle, XCircle, Package, Navigation, Phone, Clock,
    Truck, AlertTriangle, Wifi, WifiOff
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import ShipperMiniMap from '@/components/delivery/ShipperMiniMap.vue';

interface BatchItem {
    id: number; order_id: number; sequence_order: number; status: string;
    picked_up_at: string | null; delivered_at: string | null;
    address: string | null; customer_name: string | null;
    phone: string | null; latitude: number | null; longitude: number | null; notes: string | null;
}
interface ActiveBatch {
    id: number; status: string; total_orders: number;
    optimized_route: any[] | null; items: BatchItem[];
}
interface ShipperInfo {
    id: number; vehicle_type: string; vehicle_plate: string | null; active_batch: ActiveBatch | null;
}

const GPS_QUEUE_KEY = 'dlv_gps_queue';
const GPS_BATCH_SIZE = 5;        // flush when queue hits this
const GPS_FLUSH_INTERVAL = 25_000; // flush every 25s regardless

const props = defineProps<{
    shipper: ShipperInfo | null;
    restaurant_id: number;
}>();

const batch      = ref<ActiveBatch | null>(props.shipper?.active_batch ?? null);
const isUpdating = ref<number | null>(null);
const gpsStatus  = ref<'idle' | 'active' | 'error'>('idle');
const isOnline   = ref(navigator.onLine);
const currentLat = ref<number | null>(null);
const currentLng = ref<number | null>(null);

// GPS state
let nextIntervalMs  = 10_000;  // adaptive, updated from server response
let gpsTimeout: ReturnType<typeof setTimeout>  | null = null;
let flushTimeout: ReturnType<typeof setTimeout> | null = null;

function getCsrf(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

// ── Offline GPS queue (localStorage) ─────────────────────────────────────────

function loadQueue(): any[] {
    try { return JSON.parse(localStorage.getItem(GPS_QUEUE_KEY) ?? '[]'); }
    catch { return []; }
}
function saveQueue(q: any[]) {
    try { localStorage.setItem(GPS_QUEUE_KEY, JSON.stringify(q)); } catch {}
}
function appendToQueue(ping: any) {
    const q = loadQueue();
    q.push(ping);
    // Cap at 50 to avoid unbounded growth during long offline periods
    saveQueue(q.slice(-50));
}
function clearQueue() {
    try { localStorage.removeItem(GPS_QUEUE_KEY); } catch {}
}

// ── GPS tracking ──────────────────────────────────────────────────────────────

async function flushQueue() {
    const q = loadQueue();
    if (!q.length || !props.shipper || !isOnline.value) return;

    try {
        const res = await fetch('/shipper/location-batch', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ shipper_id: props.shipper.id, pings: q }),
        });
        if (res.ok) {
            const data = await res.json();
            clearQueue();
            if (data.next_interval_ms) nextIntervalMs = data.next_interval_ms;
        }
    } catch {}
}

function schedulePing() {
    if (gpsTimeout) clearTimeout(gpsTimeout);
    gpsTimeout = setTimeout(takePing, nextIntervalMs);
}

function takePing() {
    if (!props.shipper || !navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const ping = {
                latitude:  pos.coords.latitude,
                longitude: pos.coords.longitude,
                speed_kmh: pos.coords.speed != null ? pos.coords.speed * 3.6 : null,
                heading:   pos.coords.heading ?? null,
                logged_at: new Date().toISOString(),
            };

            currentLat.value = pos.coords.latitude;
            currentLng.value = pos.coords.longitude;
            appendToQueue(ping);

            const q = loadQueue();
            if (q.length >= GPS_BATCH_SIZE) {
                flushQueue();
            }

            // Adapt interval based on speed
            if (pos.coords.speed !== null) {
                const kmh = pos.coords.speed * 3.6;
                nextIntervalMs = kmh < 3 ? 30_000 : kmh < 15 ? 10_000 : 5_000;
            }

            schedulePing();
        },
        () => {
            gpsStatus.value = 'error';
            schedulePing(); // retry on next interval
        },
        { enableHighAccuracy: true, timeout: 10_000, maximumAge: 5_000 }
    );
}

function startGpsTracking() {
    if (!props.shipper || !navigator.geolocation) {
        gpsStatus.value = 'error';
        return;
    }
    gpsStatus.value = 'active';

    // Flush any pending offline pings first
    flushQueue();

    // Start periodic ping cycle
    takePing();

    // Periodic flush fallback (even if batch size not reached)
    flushTimeout = setInterval(flushQueue, GPS_FLUSH_INTERVAL);
}

const handleOnline  = () => { isOnline.value = true;  flushQueue(); };
const handleOffline = () => { isOnline.value = false; };

onMounted(() => {
    window.addEventListener('online',  handleOnline);
    window.addEventListener('offline', handleOffline);

    if (batch.value && ['dispatched', 'in_progress'].includes(batch.value.status)) {
        startGpsTracking();
    }
});

onUnmounted(() => {
    if (gpsTimeout)  clearTimeout(gpsTimeout);
    if (flushTimeout) clearInterval(flushTimeout);
    window.removeEventListener('online',  handleOnline);
    window.removeEventListener('offline', handleOffline);
    flushQueue();
});

// ── Status update ─────────────────────────────────────────────────────────────

async function updateStatus(item: BatchItem, status: 'picked_up' | 'delivered' | 'failed') {
    isUpdating.value = item.id;
    try {
        const res = await fetch(`/shipper/batch-items/${item.id}/status`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ status }),
        });
        if (res.ok && batch.value) {
            const idx = batch.value.items.findIndex(i => i.id === item.id);
            if (idx !== -1) {
                batch.value.items[idx].status = status;
                if (status === 'delivered') batch.value.items[idx].delivered_at = new Date().toISOString();
                if (status === 'picked_up') batch.value.items[idx].picked_up_at = new Date().toISOString();
            }
        }
    } finally {
        isUpdating.value = null;
    }
}

function openGoogleMaps(item: BatchItem) {
    if (item.latitude && item.longitude) {
        window.open(`https://www.google.com/maps/dir/?api=1&destination=${item.latitude},${item.longitude}&travelmode=driving`, '_blank');
    } else if (item.address) {
        window.open(`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(item.address)}`, '_blank');
    }
}

// ── Computed ──────────────────────────────────────────────────────────────────

const sortedItems     = computed(() =>
    batch.value ? [...batch.value.items].sort((a, b) => a.sequence_order - b.sequence_order) : []
);
const completedCount  = computed(() => sortedItems.value.filter(i => ['delivered', 'failed'].includes(i.status)).length);
const totalCount      = computed(() => sortedItems.value.length);
const currentItem     = computed(() => sortedItems.value.find(i => i.status === 'pending') ?? null);
const progressPct     = computed(() => totalCount.value > 0 ? (completedCount.value / totalCount.value) * 100 : 0);

// Distance to next stop from current GPS position (Haversine, km)
const distanceToNext  = computed((): string | null => {
    if (!currentItem.value || currentLat.value === null || currentLng.value === null) return null;
    const destLat = currentItem.value.latitude;
    const destLng = currentItem.value.longitude;
    if (!destLat || !destLng) return null;
    const R = 6371;
    const dLat = (destLat - currentLat.value) * Math.PI / 180;
    const dLng = (destLng - currentLng.value) * Math.PI / 180;
    const a = Math.sin(dLat/2)**2 + Math.cos(currentLat.value*Math.PI/180) * Math.cos(destLat*Math.PI/180) * Math.sin(dLng/2)**2;
    const d = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return d < 1 ? `${Math.round(d * 1000)}m` : `${d.toFixed(1)}km`;
});

const vehicleIcon: Record<string, string> = { bike: '🚲', motorbike: '🛵', car: '🚗' };

const itemStatusStyle: Record<string, string> = {
    pending:   'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    picked_up: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    delivered: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    failed:    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};
const itemStatusLabel: Record<string, string> = {
    pending: 'Chờ giao', picked_up: 'Đã lấy', delivered: 'Đã giao', failed: 'Thất bại',
};
</script>

<template>
    <Head title="Giao hàng" />

    <div class="min-h-screen bg-background flex flex-col max-w-md mx-auto">
        <!-- ── Header ── -->
        <div class="bg-primary text-primary-foreground px-4 pt-4 pb-3 safe-top">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Truck class="h-5 w-5" />
                    <span class="font-bold text-base">Shipper App</span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <!-- Offline indicator -->
                    <div v-if="!isOnline" class="flex items-center gap-1 bg-red-500/30 px-2 py-0.5 rounded-full">
                        <WifiOff class="h-3 w-3" />
                        <span>Offline</span>
                    </div>
                    <!-- GPS indicator -->
                    <div class="flex items-center gap-1.5">
                        <div class="w-2 h-2 rounded-full"
                            :class="{
                                'bg-green-400 animate-pulse': gpsStatus === 'active',
                                'bg-yellow-300': gpsStatus === 'idle',
                                'bg-red-400':    gpsStatus === 'error',
                            }" />
                        <span class="opacity-90">{{ gpsStatus === 'active' ? 'GPS bật' : gpsStatus === 'error' ? 'GPS lỗi' : 'GPS tắt' }}</span>
                    </div>
                </div>
            </div>
            <div v-if="shipper?.vehicle_plate" class="text-xs opacity-75 mt-0.5">
                {{ vehicleIcon[shipper.vehicle_type] }} {{ shipper.vehicle_plate }}
            </div>
        </div>

        <!-- ── No profile ── -->
        <div v-if="!shipper" class="flex-1 flex items-center justify-center p-8">
            <div class="text-center">
                <AlertTriangle class="h-12 w-12 text-amber-400 mx-auto mb-3" />
                <p class="font-semibold text-base">Chưa có hồ sơ shipper</p>
                <p class="text-sm text-muted-foreground mt-1">Liên hệ quản lý để được phân công</p>
            </div>
        </div>

        <!-- ── Waiting for batch ── -->
        <div v-else-if="!batch" class="flex-1 flex items-center justify-center p-8">
            <div class="text-center">
                <Package class="h-14 w-14 text-muted-foreground mx-auto mb-3 opacity-40" />
                <p class="font-semibold text-lg">Chưa có đơn giao hàng</p>
                <p class="text-sm text-muted-foreground mt-1">Chờ quản lý phân công đơn mới</p>
            </div>
        </div>

        <!-- ── Active batch ── -->
        <div v-else class="flex-1 flex flex-col overflow-hidden">

            <!-- Progress header -->
            <div class="px-4 py-3 border-b bg-background">
                <div class="flex justify-between items-center text-sm mb-2">
                    <span class="font-semibold">Batch #{{ batch.id }}</span>
                    <span class="text-muted-foreground">{{ completedCount }}/{{ totalCount }} điểm giao</span>
                </div>
                <div class="h-2.5 bg-muted rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-700"
                        :class="progressPct === 100 ? 'bg-green-500' : 'bg-primary'"
                        :style="{ width: `${progressPct}%` }" />
                </div>
            </div>

            <!-- Mini map -->
            <div class="px-4 pt-3">
                <ShipperMiniMap
                    :current-lat="currentLat"
                    :current-lng="currentLng"
                    :stops="sortedItems" />
            </div>

            <!-- Current stop card -->
            <div v-if="currentItem" class="mx-4 mt-3 p-4 bg-primary/5 border-2 border-primary/25 rounded-2xl">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-1.5 text-xs text-primary font-semibold uppercase tracking-wide">
                        <Navigation class="h-3.5 w-3.5" />
                        Điểm tiếp theo — #{{ currentItem.sequence_order }}
                    </div>
                    <!-- Distance to next stop -->
                    <span v-if="distanceToNext"
                        class="text-xs font-medium text-muted-foreground bg-muted px-2 py-0.5 rounded-full flex items-center gap-1">
                        <MapPin class="h-3 w-3" />
                        {{ distanceToNext }}
                    </span>
                </div>
                <p class="font-bold text-lg leading-tight">{{ currentItem.customer_name }}</p>
                <p class="text-sm text-muted-foreground flex items-start gap-1.5 mt-1">
                    <MapPin class="h-4 w-4 shrink-0 mt-0.5 text-primary/70" />
                    {{ currentItem.address }}
                </p>
                <!-- Navigate + Call -->
                <div class="flex gap-2 mt-3">
                    <Button size="sm" variant="outline" class="flex-1" @click="openGoogleMaps(currentItem)">
                        <Navigation class="h-4 w-4 mr-1" /> Chỉ đường
                    </Button>
                    <a v-if="currentItem.phone" :href="`tel:${currentItem.phone}`"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 border rounded-md px-3 py-1.5 text-sm font-medium hover:bg-accent transition-colors">
                        <Phone class="h-4 w-4" /> {{ currentItem.phone }}
                    </a>
                </div>
                <!-- Delivered / Failed -->
                <div class="flex gap-2 mt-2">
                    <Button size="sm" class="flex-1 bg-green-600 hover:bg-green-700 text-white"
                        :disabled="isUpdating === currentItem.id"
                        @click="updateStatus(currentItem, 'delivered')">
                        <CheckCircle class="h-4 w-4 mr-1" />
                        {{ isUpdating === currentItem.id ? '...' : 'Đã giao ✓' }}
                    </Button>
                    <Button size="sm" variant="destructive" class="flex-1"
                        :disabled="isUpdating === currentItem.id"
                        @click="updateStatus(currentItem, 'failed')">
                        <XCircle class="h-4 w-4 mr-1" /> Thất bại
                    </Button>
                </div>
            </div>

            <!-- All stops list -->
            <div class="flex-1 overflow-y-auto px-4 py-3 space-y-2">
                <p class="text-xs text-muted-foreground font-medium uppercase tracking-wide mb-1">Tất cả điểm giao</p>
                <div v-for="item in sortedItems" :key="item.id"
                    class="flex items-start gap-3 p-3 border rounded-xl transition-all"
                    :class="{
                        'opacity-50 bg-muted/30': ['delivered', 'failed'].includes(item.status),
                        'border-primary bg-primary/5 shadow-sm': item === currentItem,
                        'hover:bg-accent/30': !['delivered', 'failed'].includes(item.status),
                    }">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                        :class="{
                            'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300': item.status === 'delivered',
                            'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300':   item.status === 'failed',
                            'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300': item.status === 'picked_up',
                            'bg-primary text-primary-foreground': item === currentItem && item.status === 'pending',
                            'bg-muted text-muted-foreground': item !== currentItem && item.status === 'pending',
                        }">
                        {{ item.sequence_order }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-semibold text-sm truncate">{{ item.customer_name }}</p>
                            <span class="text-[11px] px-1.5 py-0.5 rounded-full font-medium shrink-0"
                                :class="itemStatusStyle[item.status]">
                                {{ itemStatusLabel[item.status] }}
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground truncate mt-0.5">{{ item.address }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span v-if="item.delivered_at" class="text-xs text-green-600 flex items-center gap-0.5">
                                <Clock class="h-3 w-3" />
                                {{ new Date(item.delivered_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) }}
                            </span>
                            <button v-if="!['delivered', 'failed'].includes(item.status) && item !== currentItem"
                                class="text-xs text-primary flex items-center gap-0.5 hover:underline"
                                @click="openGoogleMaps(item)">
                                <MapPin class="h-3 w-3" /> Xem map
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Completion banner -->
            <div v-if="completedCount === totalCount && totalCount > 0"
                class="p-5 bg-green-50 dark:bg-green-950/40 text-center border-t border-green-200 dark:border-green-800">
                <CheckCircle class="h-10 w-10 text-green-500 mx-auto mb-2" />
                <p class="font-bold text-green-700 dark:text-green-300 text-lg">Hoàn thành! 🎉</p>
                <p class="text-sm text-green-600 dark:text-green-400 mt-1">Đã giao tất cả {{ totalCount }} điểm. Báo cáo với quản lý.</p>
            </div>
        </div>
    </div>
</template>
