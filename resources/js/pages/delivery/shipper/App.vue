<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    MapPin,
    CheckCircle2,
    XCircle,
    Package,
    Navigation,
    Wifi,
    WifiOff,
    Clock,
    Phone,
    ChevronDown,
    ChevronUp,
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { toast } from 'vue-sonner';
import ShipperMiniMap from '@/components/delivery/ShipperMiniMap.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

// ─── Types ────────────────────────────────────────────────────────────────────
interface BatchItem {
    id: number;
    order_id: number;
    order_number: string | null;
    sequence_order: number;
    status: string;
    eta: string | null;
    accepted_at?: string | null;
    address: string | null;
    customer_name: string | null;
    phone: string | null;
    latitude: number | null;
    longitude: number | null;
    cod_amount: number;
}

interface ActiveBatch {
    id: number;
    status: string;
    accepted_at: string | null;
    items: BatchItem[];
}

interface ShipperInfo {
    id: number;
    vehicle_type: string;
    name: string | null;
    active_batch: ActiveBatch | null;
}

const props = defineProps<{ shipper: ShipperInfo | null }>();

// ─── State ────────────────────────────────────────────────────────────────────
const batch = ref<ActiveBatch | null>(props.shipper?.active_batch ?? null);
const isOnline = ref(navigator.onLine);
const myLat = ref<number | null>(null);
const myLng = ref<number | null>(null);
const mySpeed = ref<number | null>(null);
const gpsError = ref<string | null>(null);
const trail = ref<Array<{ lat: number; lng: number }>>([]);
const expandedId = ref<number | null>(null);
const updatingId = ref<number | null>(null);
let refreshTimer: number | null = null;

// Offline GPS queue stored in localStorage
const QUEUE_KEY = `gps_queue_${props.shipper?.id}`;
const MAX_QUEUE = 50;

interface GpsPing {
    latitude: number;
    longitude: number;
    speed_kmh?: number;
    logged_at: string;
}

function loadQueue(): GpsPing[] {
    try {
        return JSON.parse(localStorage.getItem(QUEUE_KEY) ?? '[]');
    } catch {
        return [];
    }
}

function saveQueue(q: GpsPing[]) {
    localStorage.setItem(QUEUE_KEY, JSON.stringify(q.slice(-MAX_QUEUE)));
}

function enqueue(ping: GpsPing) {
    const q = loadQueue();
    q.push(ping);
    saveQueue(q);
}

// ─── GPS tracking ─────────────────────────────────────────────────────────────
const watchId: number | null = null;
let flushTimer: number | null = null;
let sendTimer: number | null = null;

function getAdaptiveInterval(speedKmh: number): number {
    if (speedKmh < 3) {
        return 30_000;
    } // stationary

    if (speedKmh < 15) {
        return 10_000;
    } // slow

    return 5_000; // normal
}

function onGpsSuccess(pos: GeolocationPosition) {
    const lat = pos.coords.latitude;
    const lng = pos.coords.longitude;
    const speed = pos.coords.speed !== null ? pos.coords.speed * 3.6 : null; // m/s → km/h

    myLat.value = lat;
    myLng.value = lng;
    mySpeed.value = speed;
    gpsError.value = null;

    // Add to trail (cap at 30 points for perf)
    trail.value = [...trail.value.slice(-29), { lat, lng }];

    const ping: GpsPing = {
        latitude: lat,
        longitude: lng,
        speed_kmh: speed ?? undefined,
        logged_at: new Date().toISOString(),
    };

    if (isOnline.value) {
        sendSinglePing(ping);
    } else {
        enqueue(ping);
    }

    // Schedule next poll based on speed
    if (sendTimer) {
        clearTimeout(sendTimer);
    }

    const interval = getAdaptiveInterval(speed ?? 0);
    sendTimer = setTimeout(() => startGps(), interval) as unknown as number;
}

function onGpsError(err: GeolocationPositionError) {
    gpsError.value =
        err.code === 1
            ? 'Bị từ chối quyền GPS'
            : err.code === 2
              ? 'Không xác định được vị trí'
              : err.code === 3
                ? 'Hết thời gian GPS'
                : 'Lỗi GPS không xác định';
}

function startGps() {
    if (!navigator.geolocation) {
        gpsError.value = 'Trình duyệt không hỗ trợ GPS';

        return;
    }

    navigator.geolocation.getCurrentPosition(onGpsSuccess, onGpsError, {
        enableHighAccuracy: true,
        timeout: 10_000,
        maximumAge: 5_000,
    });
}

async function sendSinglePing(ping: GpsPing) {
    if (!props.shipper) {
        return;
    }

    try {
        await fetch('/delivery/api/shipper/location', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
            },
            body: JSON.stringify({ shipper_id: props.shipper.id, ...ping }),
        });
    } catch {
        enqueue(ping); // fallback to queue if network fails
    }
}

async function flushQueue() {
    if (!props.shipper || !isOnline.value) {
        return;
    }

    const q = loadQueue();

    if (q.length === 0) {
        return;
    }

    try {
        const res = await fetch('/delivery/api/shipper/location/batch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
            },
            body: JSON.stringify({ shipper_id: props.shipper.id, pings: q }),
        });

        if (res.ok) {
            saveQueue([]);
        }
    } catch {
        // keep queue for next flush
    }
}

// ─── Batch item actions ───────────────────────────────────────────────────────
async function updateStatus(
    item: BatchItem,
    status: 'picked_up' | 'delivered' | 'failed',
    notes?: string,
) {
    if (updatingId.value) {
        return;
    }

    if (status === 'failed') {
        const reason = window.prompt('Lý do giao thất bại?', notes ?? '');

        if (!reason?.trim()) {
            return;
        }

        notes = reason.trim();
    }

    updatingId.value = item.id;

    try {
        const res = await fetch(
            `/delivery/api/shipper/items/${item.id}/status`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
                body: JSON.stringify({ status, notes }),
            },
        );

        if (!res.ok) {
            const d = await res.json().catch(() => ({}));
            toast.error(d.message || 'Không thể cập nhật trạng thái');

            return;
        }

        const data = await res.json();

        if (batch.value) {
            const idx = batch.value.items.findIndex((i) => i.id === item.id);

            if (idx >= 0) {
                batch.value.items[idx] = {
                    ...batch.value.items[idx],
                    status: data.item.status,
                };
            }
        }

        const labels: Record<string, string> = {
            picked_up: '✅ Đã lấy hàng',
            delivered: '🎉 Đã giao thành công',
            failed: '❌ Giao thất bại',
        };
        toast.success(labels[status] ?? 'Cập nhật thành công');
    } catch (error) {
        console.error('Update status failed:', error);
        toast.error('Lỗi kết nối. Vui lòng thử lại.');
    } finally {
        updatingId.value = null;
    }
}

const batchAccepted = computed(
    () => !!batch.value && !!batch.value.accepted_at,
);

async function acceptBatch() {
    if (!props.shipper || !batch.value || batch.value.status !== 'dispatched') {
        return;
    }

    try {
        const res = await fetch(
            `/delivery/api/shipper/batches/${batch.value.id}/accept`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
            },
        );

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            toast.error(data.message || 'Không thể nhận chuyến giao hàng');

            return;
        }

        const data = await res.json();
        batch.value = {
            ...batch.value,
            accepted_at: data.batch?.accepted_at ?? new Date().toISOString(),
        };
        toast.success('Đã nhận chuyến giao hàng');
    } catch {
        toast.error('Lỗi kết nối. Vui lòng thử lại.');
    }
}

async function refreshCurrentBatch() {
    if (!props.shipper) {
        return;
    }

    try {
        const res = await fetch('/delivery/api/shipper/current');

        if (!res.ok) {
            return;
        }

        const data = await res.json();
        batch.value = data.shipper?.active_batch ?? null;
    } catch {
        // GPS and the offline queue remain usable when polling is unavailable.
    }
}

// ─── Computed ─────────────────────────────────────────────────────────────────
const pendingItems = computed(
    () =>
        batch.value?.items.filter(
            (i) => !['delivered', 'failed'].includes(i.status),
        ) ?? [],
);

const doneItems = computed(
    () =>
        batch.value?.items.filter((i) =>
            ['delivered', 'failed'].includes(i.status),
        ) ?? [],
);

const nextStop = computed(
    (): BatchItem | null =>
        pendingItems.value.find((i) => i.status === 'pending') ?? null,
);

const nextStopForMap = computed(() => {
    const s = nextStop.value;

    if (!s?.latitude || !s?.longitude) {
        return null;
    }

    return { lat: s.latitude, lng: s.longitude, address: s.address ?? '' };
});

function formatEta(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const diff = Math.round((new Date(iso).getTime() - Date.now()) / 60000);

    if (diff <= 0) {
        return 'Đang đến';
    }

    if (diff < 60) {
        return `${diff} phút`;
    }

    return `${Math.floor(diff / 60)}g${diff % 60}p`;
}

// ─── Event listeners (named refs to avoid memory leak) ───────────────────────
const handleOnline = () => {
    isOnline.value = true;
    flushQueue();
};
const handleOffline = () => {
    isOnline.value = false;
};

// ─── Utilities ────────────────────────────────────────────────────────────────
function getCsrf(): string {
    return (
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content ?? ''
    );
}

function openMaps(lat: number, lng: number) {
    window.open(
        `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`,
        '_blank',
    );
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    startGps();
    // Flush offline queue every 25 seconds
    flushTimer = setInterval(flushQueue, 25_000) as unknown as number;
    refreshTimer = setInterval(
        refreshCurrentBatch,
        15_000,
    ) as unknown as number;
    // Flush any pending pings from previous session
    flushQueue();
});

onUnmounted(() => {
    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
    }

    if (flushTimer !== null) {
        clearInterval(flushTimer);
    }

    if (refreshTimer !== null) {
        clearInterval(refreshTimer);
    }

    if (sendTimer !== null) {
        clearTimeout(sendTimer);
    }

    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
    flushQueue();
});
</script>

<template>
    <Head title="Shipper App" />

    <div class="min-h-screen bg-background">
        <!-- Top bar -->
        <div
            class="sticky top-0 z-10 flex items-center justify-between border-b bg-background px-4 py-3 shadow-sm"
        >
            <div>
                <h1 class="font-semibold">Giao hàng</h1>
                <p class="text-xs text-muted-foreground">
                    {{ shipper?.name ?? 'Shipper' }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span v-if="myLat" class="text-xs text-muted-foreground">
                    {{ mySpeed !== null ? `${mySpeed.toFixed(0)} km/h` : '' }}
                </span>
                <Badge
                    :variant="isOnline ? 'default' : 'destructive'"
                    class="gap-1"
                >
                    <Wifi v-if="isOnline" class="h-3 w-3" />
                    <WifiOff v-else class="h-3 w-3" />
                    {{ isOnline ? 'Online' : 'Offline' }}
                </Badge>
            </div>
        </div>

        <div class="space-y-4 p-4">
            <!-- No batch state -->
            <div v-if="!batch" class="py-20 text-center">
                <Package class="mx-auto mb-4 h-16 w-16 text-muted-foreground" />
                <p class="text-lg font-semibold">Chưa có đơn hàng</p>
                <p class="text-sm text-muted-foreground">
                    Quản lý sẽ giao batch cho bạn sớm
                </p>
            </div>

            <template v-else>
                <!-- Map -->
                <Card v-if="myLat && myLng">
                    <CardContent class="p-2">
                        <ShipperMiniMap
                            :lat="myLat"
                            :lng="myLng"
                            :next-stop="nextStopForMap"
                            :trail="trail"
                        />
                    </CardContent>
                </Card>

                <!-- Dispatch acceptance -->
                <Card
                    v-if="batch.status === 'dispatched' && !batch.accepted_at"
                    class="border-amber-300 bg-amber-50 dark:bg-amber-950/20"
                >
                    <CardContent class="space-y-3 p-4">
                        <div>
                            <p class="font-semibold">Bạn có chuyến giao mới</p>
                            <p class="text-sm text-muted-foreground">
                                Kiểm tra hàng và nhận chuyến trước khi bắt đầu
                                giao.
                            </p>
                        </div>
                        <Button class="w-full" @click="acceptBatch">
                            <CheckCircle2 class="mr-2 h-4 w-4" />
                            Nhận chuyến
                        </Button>
                    </CardContent>
                </Card>

                <!-- GPS error -->
                <div
                    v-if="gpsError"
                    class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
                >
                    <Navigation class="h-4 w-4 shrink-0" />
                    {{ gpsError }}
                </div>

                <!-- Next stop callout -->
                <Card v-if="nextStop" class="border-primary">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm">
                            <Navigation class="h-4 w-4 text-primary" />
                            Điểm giao tiếp theo (#{{ nextStop.sequence_order }})
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <p class="font-semibold">
                            {{ nextStop.customer_name }}
                        </p>
                        <p
                            class="flex items-start gap-1 text-sm text-muted-foreground"
                        >
                            <MapPin class="mt-0.5 h-4 w-4 shrink-0" />
                            {{ nextStop.address }}
                        </p>
                        <div class="flex items-center gap-2 text-sm">
                            <Phone class="h-4 w-4 text-muted-foreground" />
                            <a
                                :href="`tel:${nextStop.phone}`"
                                class="text-primary underline"
                                >{{ nextStop.phone }}</a
                            >
                            <span
                                v-if="nextStop.cod_amount > 0"
                                class="ml-auto font-semibold text-amber-600"
                            >
                                COD:
                                {{
                                    nextStop.cod_amount.toLocaleString('vi-VN')
                                }}₫
                            </span>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <Clock class="h-4 w-4 text-muted-foreground" />
                            ETA:
                            <span class="font-medium">{{
                                formatEta(nextStop.eta)
                            }}</span>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex gap-2 pt-2">
                            <Button
                                v-if="nextStop.latitude && nextStop.longitude"
                                variant="outline"
                                size="sm"
                                class="flex-1"
                                @click="
                                    openMaps(
                                        nextStop.latitude!,
                                        nextStop.longitude!,
                                    )
                                "
                            >
                                <Navigation class="mr-1 h-4 w-4" />
                                Chỉ đường
                            </Button>
                            <Button
                                v-if="
                                    batchAccepted &&
                                    nextStop.status === 'pending'
                                "
                                size="sm"
                                class="flex-1"
                                :disabled="updatingId === nextStop.id"
                                @click="updateStatus(nextStop, 'picked_up')"
                            >
                                <Package class="mr-1 h-4 w-4" />
                                Đã lấy hàng
                            </Button>
                            <Button
                                v-if="
                                    batchAccepted &&
                                    nextStop.status === 'picked_up'
                                "
                                size="sm"
                                variant="default"
                                class="flex-1 bg-green-600 hover:bg-green-700"
                                :disabled="updatingId === nextStop.id"
                                @click="updateStatus(nextStop, 'delivered')"
                            >
                                <CheckCircle2 class="mr-1 h-4 w-4" />
                                Đã giao
                            </Button>
                        </div>
                        <Button
                            v-if="
                                batchAccepted &&
                                ['pending', 'picked_up'].includes(
                                    nextStop.status,
                                )
                            "
                            variant="ghost"
                            size="sm"
                            class="w-full text-destructive hover:text-destructive"
                            :disabled="updatingId === nextStop.id"
                            @click="
                                updateStatus(
                                    nextStop,
                                    'failed',
                                    'Không giao được',
                                )
                            "
                        >
                            <XCircle class="mr-1 h-4 w-4" />
                            Giao thất bại
                        </Button>
                    </CardContent>
                </Card>

                <!-- All stops list -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm">
                            Danh sách điểm giao ({{ batch.items.length }})
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 p-3">
                        <div
                            v-for="item in batch.items"
                            :key="item.id"
                            class="rounded-lg border"
                            :class="{
                                'border-green-200 bg-green-50':
                                    item.status === 'delivered',
                                'border-red-200 bg-red-50':
                                    item.status === 'failed',
                                'border-purple-200 bg-purple-50':
                                    item.status === 'picked_up',
                                'border-border': item.status === 'pending',
                            }"
                        >
                            <button
                                class="flex w-full items-center gap-3 p-3 text-left"
                                @click="
                                    expandedId =
                                        expandedId === item.id ? null : item.id
                                "
                            >
                                <span
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold"
                                >
                                    {{ item.sequence_order }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">
                                        {{
                                            item.customer_name ??
                                            `Đơn #${item.order_id}`
                                        }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ item.address }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <CheckCircle2
                                        v-if="item.status === 'delivered'"
                                        class="h-5 w-5 text-green-600"
                                    />
                                    <XCircle
                                        v-else-if="item.status === 'failed'"
                                        class="h-5 w-5 text-red-600"
                                    />
                                    <Navigation
                                        v-else-if="item.status === 'picked_up'"
                                        class="h-5 w-5 text-purple-600"
                                    />
                                    <Package
                                        v-else
                                        class="h-5 w-5 text-amber-600"
                                    />
                                    <ChevronUp
                                        v-if="expandedId === item.id"
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <ChevronDown
                                        v-else
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                </div>
                            </button>

                            <div
                                v-if="expandedId === item.id"
                                class="space-y-2 border-t px-3 pt-2 pb-3"
                            >
                                <p class="text-xs">
                                    <span class="font-medium">SĐT:</span>
                                    {{ item.phone }}
                                </p>
                                <p class="text-xs">
                                    <span class="font-medium">ETA:</span>
                                    {{ formatEta(item.eta) }}
                                </p>
                                <p
                                    v-if="item.cod_amount > 0"
                                    class="text-xs font-semibold text-amber-600"
                                >
                                    COD:
                                    {{
                                        item.cod_amount.toLocaleString('vi-VN')
                                    }}₫
                                </p>
                                <div
                                    v-if="
                                        batchAccepted &&
                                        ['pending', 'picked_up'].includes(
                                            item.status,
                                        )
                                    "
                                    class="flex gap-2"
                                >
                                    <Button
                                        v-if="item.status === 'pending'"
                                        size="sm"
                                        variant="outline"
                                        class="flex-1"
                                        :disabled="updatingId === item.id"
                                        @click="updateStatus(item, 'picked_up')"
                                        >Đã lấy</Button
                                    >
                                    <Button
                                        v-if="item.status === 'picked_up'"
                                        size="sm"
                                        class="flex-1 bg-green-600 hover:bg-green-700"
                                        :disabled="updatingId === item.id"
                                        @click="updateStatus(item, 'delivered')"
                                        >Đã giao</Button
                                    >
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="text-destructive"
                                        :disabled="updatingId === item.id"
                                        @click="updateStatus(item, 'failed')"
                                        >Thất bại</Button
                                    >
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Progress summary -->
                <Card>
                    <CardContent class="pt-4">
                        <div class="flex items-center justify-between text-sm">
                            <span>Tiến độ batch</span>
                            <span class="font-semibold"
                                >{{ doneItems.length }} /
                                {{ batch.items.length }}</span
                            >
                        </div>
                        <div
                            class="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{
                                    width: `${batch.items.length > 0 ? (doneItems.length / batch.items.length) * 100 : 0}%`,
                                }"
                            />
                        </div>
                    </CardContent>
                </Card>
            </template>
        </div>
    </div>
</template>
