<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    MapPin,
    Truck,
    Package,
    CheckCircle2,
    XCircle,
    RefreshCw,
    Navigation,
    Users,
    Clock,
    AlertTriangle,
    LayoutGrid,
    X,
    Plus,
    Send,
    Phone,
    Eye,
    Route,
    Timer,
    PackageCheck,
    PackageX,
    Search,
    ChevronDown,
    ArrowUpDown,
    Zap,
    Activity,
    Radio,
    CheckCheck,
    TrendingUp,
    TrendingDown,
    Minus,
    HelpCircle,
    BookOpen,
    ArrowRight,
    Sparkles,
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import type { Component } from 'vue';
import { toast } from 'vue-sonner';
import DeliveryMap from '@/components/delivery/DeliveryMap.vue';
import ShipperMiniMap from '@/components/delivery/ShipperMiniMap.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCountUp } from '@/composables/useCountUp';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

// ─── Types ───────────────────────────────────────────────────────────────────
interface DeliveryDetail {
    id: number;
    customer_name: string;
    phone: string;
    address: string;
    latitude: number | null;
    longitude: number | null;
    delivery_fee: number;
    delivery_status: string;
}
interface UnassignedOrder {
    id: number;
    order_number: string;
    status: string;
    total_amount: number;
    created_at: string;
    delivery_detail: DeliveryDetail | null;
    items_count: number;
}
interface BatchItem {
    id: number;
    order_id: number;
    order_number: string | null;
    sequence_order: number;
    status: string;
    eta: string | null;
    picked_up_at: string | null;
    delivered_at: string | null;
    address: string | null;
    customer_name: string | null;
    phone: string | null;
    latitude: number | null;
    longitude: number | null;
    total_amount: number;
    cod_amount: number;
    order_created_at: string | null;
    order_confirmed_at: string | null;
}
interface ActiveBatch {
    id: number;
    status: string;
    total_orders: number;
    estimated_duration_minutes: number | null;
    dispatched_at: string | null;
    optimized_route: any[] | null;
    items: BatchItem[];
}
interface ActiveShipper {
    id: number;
    name: string;
    vehicle_type: string;
    vehicle_plate: string | null;
    is_active: boolean;
    max_orders_per_batch: number;
    current_lat: number | null;
    current_lng: number | null;
    last_seen_at: string | null;
    has_gps: boolean;
    current_load: { orders: number; active_batch_id: number | null };
    trail: Array<{ lat: number; lng: number }>;
    active_batch: ActiveBatch | null;
}
interface SuggestedShipper {
    id: number;
    name: string;
    vehicle_type: string;
    vehicle_plate: string | null;
    score: number;
    current_orders: number;
    available_slots: number;
    max_orders: number;
    has_gps: boolean;
    last_seen_at: string | null;
    distance_factor: number;
}
interface Stats {
    pending_orders: number;
    active_shippers: number;
    active_batches: number;
    delivered_today: number;
    failed_today: number;
    delivered_yesterday?: number;
    failed_yesterday?: number;
}
interface RoutePreview {
    route: Array<{
        id: number;
        lat: number;
        lng: number;
        order_number: string;
        address: string;
        customer: string;
    }>;
    total_distance_km: number;
    estimated_duration_minutes: number;
}
interface ActivityEvent {
    id: number;
    type: 'delivered' | 'failed' | 'dispatched' | 'picked_up' | 'gps';
    message: string;
    time: Date;
}

const props = defineProps<{ initialStats: Stats }>();

// ─── State ───────────────────────────────────────────────────────────────────
const stats = ref<Stats>({ ...props.initialStats });
const unassignedOrders = ref<UnassignedOrder[]>([]);
const activeShippers = ref<ActiveShipper[]>([]);
const loading = ref(false);
const lastRefreshed = ref<Date | null>(null);
const selectedOrders = ref<Set<number>>(new Set());

const showDeliveryAnalytics = ref(true);
const showSopModal = ref(false);
const showSopBanner = ref(true);

const totalDeliveredToday = computed(
    () => stats.value.delivered_today + stats.value.failed_today,
);

const deliverySuccessRate = computed(() => {
    if (totalDeliveredToday.value === 0) {
        return 0;
    }

    return (stats.value.delivered_today / totalDeliveredToday.value) * 100;
});

const deliveryEvaluation = computed(() => {
    const totalToday = totalDeliveredToday.value;
    const shippersCount = stats.value.active_shippers;
    const pendingCount = stats.value.pending_orders;
    const successRate = deliverySuccessRate.value;

    let status = 'good';
    let title = 'Hệ thống Sẵn sàng Tiếp nhận Đơn Giao Hàng';
    let text =
        'Tất cả hạ tầng bản đồ GPS & thuật toán Smart Routing đã sẵn sàng. Khi có đơn giao hàng phát sinh, hệ thống sẽ tự động cập nhật thời gian thực.';
    const tips = [];

    if (totalToday === 0 && pendingCount === 0 && shippersCount === 0) {
        status = 'good';
        title = 'Sẵn sàng Vận hành Giao hàng (Quy trình 3 bước)';
        text =
            'Chưa có đơn phát sinh hôm nay. Hãy tạo đơn Giao Hàng tại POS hoặc mở kênh Đặt Hàng Online để bắt đầu trải nghiệm điều phối.';
        tips.push(
            'Bấm nút "Hướng dẫn vận hành SOP" góc trên bên phải để xem chi tiết luồng 3 bước.',
        );
    } else if (pendingCount > 0) {
        status = 'attention';
        title = 'Có đơn hàng đang chờ điều phối';
        text = `Hiện đang có ${pendingCount} đơn hàng giao vận đang chờ tài xế. Cần xếp chuyến sớm để đảm bảo thời gian cam kết (ETA).`;
        tips.push(
            `Khuyên dùng: Tích chọn đơn và nhấn "Gợi ý Shipper thông minh".`,
        );
    }

    if (shippersCount === 0 && pendingCount > 0) {
        status = 'warning';
        title = 'Không có shipper hoạt động';
        text =
            'Cảnh báo: Hiện tại chưa có nhân viên giao hàng nào đang hoạt động trực tuyến trên hệ thống.';
        tips.push('Nhắc nhở shipper đăng nhập app và bật định vị GPS.');
    } else if (shippersCount > 0) {
        tips.push(
            `Đang có ${shippersCount} shipper trực tuyến hỗ trợ giao hàng.`,
        );
    }

    if (totalToday > 0 && successRate < 90) {
        status = 'warning';
        title = 'Tỷ lệ giao thất bại cao';
        text = `Cảnh báo: Tỷ lệ giao hàng thành công chỉ đạt ${successRate.toFixed(1)}%. Có dấu hiệu chậm trễ hoặc từ chối đơn.`;
        tips.push('Liên hệ các tài xế để hỗ trợ giải quyết sự cố dọc đường.');
    } else if (totalToday > 0) {
        tips.push(
            `Hiệu suất giao hàng đạt ${successRate.toFixed(1)}% thành công.`,
        );
    }

    return { status, title, text, tips };
});

// Map
const mapTab = ref<'all' | 'pending' | 'active'>('all');
const focusedShipperId = ref<number | null>(null);

// Order list filters
const orderSearch = ref('');
const orderSort = ref<'newest' | 'oldest' | 'amount'>('newest');
const showSortMenu = ref(false);

// Create batch wizard
const showCreateModal = ref(false);
const wizardStep = ref<1 | 2 | 3>(1);
const suggestedShippers = ref<SuggestedShipper[]>([]);
const suggestedBatches = ref<
    Array<{
        centroid: any;
        order_ids: number[];
        count: number;
        radius_km: number;
    }>
>([]);
const selectedShipperId = ref<number | null>(null);
const batchCreating = ref(false);
const routePreview = ref<RoutePreview | null>(null);
const routeLoading = ref(false);
const wizardLoading = ref(false);

// Tracking drawer
const trackingItem = ref<BatchItem | null>(null);
const trackingShipper = ref<ActiveShipper | null>(null);
const showTracking = ref(false);

// ETA countdown
let etaTicker: number | null = null;
const tickNow = ref(Date.now());

// Activity feed
let activitySeq = 0;
const activityFeed = ref<ActivityEvent[]>([]);

// Auto-refresh
let autoRefreshTimer: number | null = null;
const AUTO_REFRESH_MS = 30_000;

// ─── Count-up animated stats ──────────────────────────────────────────────────
const statPending = computed(() => stats.value.pending_orders);
const statShippers = computed(() => stats.value.active_shippers);
const statBatches = computed(() => stats.value.active_batches);
const statDelivered = computed(() => stats.value.delivered_today);
const statFailed = computed(() => stats.value.failed_today);

const displayPending = useCountUp(statPending);
const displayShippers = useCountUp(statShippers);
const displayBatches = useCountUp(statBatches);
const displayDelivered = useCountUp(statDelivered);
const displayFailed = useCountUp(statFailed);

// Trend helpers
function trendDelivered(): number {
    return (
        (stats.value.delivered_today ?? 0) -
        (stats.value.delivered_yesterday ?? 0)
    );
}
function trendFailed(): number {
    return (
        (stats.value.failed_today ?? 0) - (stats.value.failed_yesterday ?? 0)
    );
}

// ─── Filtered / sorted orders ─────────────────────────────────────────────────
const filteredOrders = computed(() => {
    let list = unassignedOrders.value;
    const q = orderSearch.value.trim().toLowerCase();

    if (q) {
        list = list.filter(
            (o) =>
                o.order_number.toLowerCase().includes(q) ||
                o.delivery_detail?.customer_name?.toLowerCase().includes(q) ||
                o.delivery_detail?.address?.toLowerCase().includes(q),
        );
    }

    if (orderSort.value === 'newest') {
        return [...list].sort(
            (a, b) =>
                new Date(b.created_at).getTime() -
                new Date(a.created_at).getTime(),
        );
    }

    if (orderSort.value === 'oldest') {
        return [...list].sort(
            (a, b) =>
                new Date(a.created_at).getTime() -
                new Date(b.created_at).getTime(),
        );
    }

    return [...list].sort((a, b) => b.total_amount - a.total_amount);
});

function urgency(order: UnassignedOrder): 'new' | 'waiting' | 'urgent' {
    const mins = (Date.now() - new Date(order.created_at).getTime()) / 60000;

    if (mins > 30) {
        return 'urgent';
    }

    if (mins > 15) {
        return 'waiting';
    }

    return 'new';
}

function waitingLabel(order: UnassignedOrder): string {
    const mins = Math.round(
        (Date.now() - new Date(order.created_at).getTime()) / 60000,
    );

    if (mins < 1) {
        return 'Vừa đặt';
    }

    if (mins < 60) {
        return `${mins} phút`;
    }

    return `${Math.round(mins / 60)} giờ`;
}

// ─── Map data ─────────────────────────────────────────────────────────────────
const mapShippers = computed(() => {
    const src = mapTab.value === 'pending' ? [] : activeShippers.value;

    return src
        .filter((s) => s.current_lat !== null)
        .map((s) => ({
            id: s.id,
            name: s.name,
            lat: s.current_lat!,
            lng: s.current_lng!,
            vehicle_type: s.vehicle_type,
            has_gps: s.has_gps,
            trail: s.trail,
            active_batch: s.active_batch
                ? {
                      id: s.active_batch.id,
                      items: s.active_batch.items.map((i) => ({
                          sequence_order: i.sequence_order,
                          status: i.status,
                          lat: i.latitude,
                          lng: i.longitude,
                          address: i.address,
                          customer_name: i.customer_name,
                          eta: i.eta,
                      })),
                  }
                : null,
        }));
});

const mapOrders = computed(() => {
    if (mapTab.value === 'active') {
        return [];
    }

    return unassignedOrders.value
        .filter((o) => o.delivery_detail?.latitude)
        .map((o) => ({
            id: o.id,
            lat: o.delivery_detail!.latitude!,
            lng: o.delivery_detail!.longitude!,
            address: o.delivery_detail!.address,
            customer_name: o.delivery_detail!.customer_name,
            status: o.delivery_detail!.delivery_status,
        }));
});

// Map tab indicator (animated sliding underline)
const mapTabs = computed(() => [
    { id: 'all', label: 'Tổng quan', icon: LayoutGrid, count: 0 },
    {
        id: 'pending',
        label: 'Đơn chờ',
        icon: Package,
        count: unassignedOrders.value.length,
    },
    {
        id: 'active',
        label: 'Đang giao',
        icon: Truck,
        count: activeShippers.value.filter((s) => s.active_batch).length,
    },
]);

// ─── Data fetching ────────────────────────────────────────────────────────────
async function fetchAll() {
    if (showCreateModal.value || showTracking.value) {
        return;
    } // pause when modals open

    loading.value = true;

    try {
        const [oRes, sRes, stRes] = await Promise.all([
            fetch('/delivery/api/unassigned-orders'),
            fetch('/delivery/api/active-shippers'),
            fetch('/delivery/api/stats'),
        ]);

        if (oRes.ok) {
            unassignedOrders.value = await oRes.json().catch(() => []);
        }

        if (sRes.ok) {
            activeShippers.value = await sRes.json().catch(() => []);
        }

        if (stRes.ok) {
            const d = await stRes.json().catch(() => null);

            if (d) {
                stats.value = d;
            }
        }

        lastRefreshed.value = new Date();
    } catch {
        toast.error('Không thể tải dữ liệu. Vui lòng thử lại.');
    } finally {
        loading.value = false;
    }
}

function startAutoRefresh() {
    stopAutoRefresh();
    autoRefreshTimer = setInterval(
        () => fetchAll(),
        AUTO_REFRESH_MS,
    ) as unknown as number;
}
function stopAutoRefresh() {
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    }
}

const lastRefreshedLabel = computed(() => {
    if (!lastRefreshed.value) {
        return '';
    }

    const secs = Math.round(
        (tickNow.value - lastRefreshed.value.getTime()) / 1000,
    );

    if (secs < 5) {
        return 'Vừa cập nhật';
    }

    if (secs < 60) {
        return `${secs}s trước`;
    }

    return `${Math.round(secs / 60)} phút trước`;
});

// ─── Shipper helpers ──────────────────────────────────────────────────────────
function loadPercent(s: ActiveShipper): number {
    return Math.min(
        100,
        Math.round(
            (s.current_load.orders / Math.max(1, s.max_orders_per_batch)) * 100,
        ),
    );
}

function vehicleEmoji(type: string): string {
    return (
        ({ bike: '🚲', motorbike: '🏍️', car: '🚗' } as Record<string, string>)[
            type
        ] ?? '🚗'
    );
}

function avatarColor(name: string): string {
    const colors = [
        'from-violet-500 to-purple-600',
        'from-blue-500 to-cyan-600',
        'from-emerald-500 to-teal-600',
        'from-orange-500 to-amber-600',
        'from-rose-500 to-pink-600',
    ];
    let hash = 0;

    for (const ch of name) {
        hash = (hash * 31 + ch.charCodeAt(0)) & 0xffff;
    }

    return colors[hash % colors.length];
}

function lastSeenLabel(iso: string | null): string {
    if (!iso) {
        return 'Chưa kết nối';
    }

    const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000);

    if (mins < 1) {
        return 'Vừa xong';
    }

    if (mins < 60) {
        return `${mins} phút trước`;
    }

    return `${Math.round(mins / 60)} giờ trước`;
}

// ─── Order selection ──────────────────────────────────────────────────────────
function toggleOrder(id: number) {
    const s = new Set(selectedOrders.value);

    if (s.has(id)) {
        s.delete(id);
    } else {
        s.add(id);
    }

    selectedOrders.value = s;
}
function selectAll() {
    selectedOrders.value = new Set(unassignedOrders.value.map((o) => o.id));
}
function clearSelection() {
    selectedOrders.value = new Set();
}

// ─── Activity feed ────────────────────────────────────────────────────────────
function pushActivity(message: string, type: ActivityEvent['type']) {
    activityFeed.value.unshift({
        id: ++activitySeq,
        type,
        message,
        time: new Date(),
    });

    if (activityFeed.value.length > 20) {
        activityFeed.value.pop();
    }
}

// ─── Tracking drawer ──────────────────────────────────────────────────────────
function openTracking(item: BatchItem, shipper: ActiveShipper) {
    trackingItem.value = item;
    trackingShipper.value = shipper;
    showTracking.value = true;
    stopAutoRefresh();
}
function closeTracking() {
    showTracking.value = false;
    trackingItem.value = null;
    trackingShipper.value = null;
    startAutoRefresh();
}

const trackingSteps = computed(() => {
    const item = trackingItem.value;
    const shipper = trackingShipper.value;

    if (!item || !shipper) {
        return [];
    }

    return [
        {
            label: 'Đặt hàng',
            icon: Package,
            time: item.order_created_at,
            done: true,
        },
        {
            label: 'Xác nhận đơn',
            icon: CheckCircle2,
            time: item.order_confirmed_at,
            done: !!item.order_confirmed_at,
        },
        {
            label: 'Shipper nhận',
            icon: Truck,
            time: shipper.active_batch?.dispatched_at ?? null,
            done: !!shipper.active_batch?.dispatched_at,
        },
        {
            label: 'Đã lấy hàng',
            icon: PackageCheck,
            time: item.picked_up_at,
            done: !!item.picked_up_at,
        },
        {
            label:
                item.status === 'failed' ? 'Giao thất bại' : 'Giao thành công',
            icon: item.status === 'failed' ? PackageX : PackageCheck,
            time: item.delivered_at,
            done: item.status === 'delivered' || item.status === 'failed',
        },
    ];
});

const trackingStepProgress = computed(() => {
    const done = trackingSteps.value.filter((s) => s.done).length;
    const total = trackingSteps.value.length;

    return total > 0 ? Math.round(((done - 1) / (total - 1)) * 100) : 0;
});

function isCurrentStep(i: number): boolean {
    const steps = trackingSteps.value;
    const lastDone =
        steps
            .map((s, idx) => (s.done ? idx : -1))
            .filter((x) => x >= 0)
            .pop() ?? -1;

    return lastDone === i && i < steps.length - 1;
}

const trackingEta = computed(() => {
    const eta = trackingItem.value?.eta;

    if (!eta) {
        return null;
    }

    const diff = new Date(eta).getTime() - tickNow.value;

    if (diff <= 0) {
        return { label: 'Đang đến', mins: 0, pct: 100 };
    }

    const m = Math.floor(diff / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    const dispatched = trackingShipper.value?.active_batch?.dispatched_at;
    let pct = 50;

    if (dispatched) {
        const total = new Date(eta).getTime() - new Date(dispatched).getTime();
        const elapsed = tickNow.value - new Date(dispatched).getTime();
        pct =
            total > 0 ? Math.min(99, Math.round((elapsed / total) * 100)) : 50;
    }

    return { label: `${m}:${s.toString().padStart(2, '0')}`, mins: m, pct };
});

const trackingNextStop = computed(() => {
    const item = trackingItem.value;

    if (!item?.latitude || !item?.longitude) {
        return null;
    }

    return {
        lat: item.latitude,
        lng: item.longitude,
        address: item.address ?? '',
    };
});

// ─── Create batch wizard ──────────────────────────────────────────────────────
async function openCreateModal() {
    if (selectedOrders.value.size === 0) {
        toast.warning('Chọn ít nhất một đơn hàng');

        return;
    }

    showCreateModal.value = true;
    wizardStep.value = 1;
    suggestedShippers.value = [];
    suggestedBatches.value = [];
    selectedShipperId.value = null;
    routePreview.value = null;
    wizardLoading.value = true;
    stopAutoRefresh();

    const ids = [...selectedOrders.value];

    try {
        const [sRes, bRes] = await Promise.all([
            fetch('/delivery/api/suggest-shippers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
                body: JSON.stringify({ order_ids: ids }),
            }),
            fetch('/delivery/api/suggest-batches', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
                body: JSON.stringify({ order_ids: ids, max_per_batch: 5 }),
            }),
        ]);

        if (sRes.ok) {
            suggestedShippers.value = await sRes.json().catch(() => []);
        }

        if (bRes.ok) {
            suggestedBatches.value = await bRes.json().catch(() => []);
        }
    } catch {
        /* silent */
    } finally {
        wizardLoading.value = false;
    }
}

function closeCreateModal() {
    showCreateModal.value = false;
    startAutoRefresh();
}

async function goToStep3() {
    if (!selectedShipperId.value) {
        toast.warning('Chọn shipper trước');

        return;
    }

    wizardStep.value = 3;
    routeLoading.value = true;
    routePreview.value = null;

    try {
        const res = await fetch('/delivery/api/optimize-route', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
            },
            body: JSON.stringify({
                order_ids: [...selectedOrders.value],
                shipper_id: selectedShipperId.value,
            }),
        });

        if (res.ok) {
            routePreview.value = await res.json().catch(() => null);
        }
    } catch {
        /* silent */
    } finally {
        routeLoading.value = false;
    }
}

function selectCluster(cluster: (typeof suggestedBatches.value)[0]) {
    selectedOrders.value = new Set(cluster.order_ids);
}

async function createAndDispatchBatch() {
    if (!selectedShipperId.value) {
        return;
    }

    batchCreating.value = true;

    try {
        const csrf = getCsrf();
        const cRes = await fetch('/delivery/api/batches', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                shipper_id: selectedShipperId.value,
                order_ids: [...selectedOrders.value],
            }),
        });

        if (!cRes.ok) {
            const d = await cRes.json().catch(() => ({}));
            toast.error(d.message || 'Không thể tạo batch');

            return;
        }

        const data = await cRes.json();

        const dRes = await fetch(
            `/delivery/api/batches/${data.batch.id}/dispatch`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
            },
        );

        if (!dRes.ok) {
            const d = await dRes.json().catch(() => ({}));
            toast.error(d.message || 'Dispatch thất bại');

            return;
        }

        const shipperName =
            suggestedShippers.value.find(
                (s) => s.id === selectedShipperId.value,
            )?.name ?? 'Shipper';
        toast.success(
            `Dispatch thành công! ${selectedOrders.value.size} đơn → ${shipperName}`,
        );
        pushActivity(
            `Dispatch batch ${data.batch.id} cho ${shipperName}`,
            'dispatched',
        );
        closeCreateModal();
        clearSelection();
        await fetchAll();
    } catch {
        toast.error('Lỗi kết nối. Vui lòng thử lại.');
    } finally {
        batchCreating.value = false;
    }
}

// ─── WebSocket ────────────────────────────────────────────────────────────────
const page = usePage();
let echoChannel: any = null;

function setupWebSocket() {
    const rid = (page.props as any).auth?.user?.restaurant_id;

    if (!rid || !(window as any).Echo) {
        return;
    }

    echoChannel = (window as any).Echo.private(`delivery.${rid}`);
    echoChannel
        .listen('.batch.dispatched', () => {
            fetchAll();
            pushActivity('Batch mới được dispatch', 'dispatched');
        })
        .listen('.delivery.status.updated', (e: any) => {
            if (e.item?.status === 'delivered') {
                pushActivity(
                    `Giao thành công: ${e.item?.customer_name ?? 'khách hàng'}`,
                    'delivered',
                );
            }

            if (e.item?.status === 'failed') {
                pushActivity(
                    `Giao thất bại: ${e.item?.customer_name ?? 'khách hàng'}`,
                    'failed',
                );
            }

            if (e.item?.status === 'picked_up') {
                pushActivity(`Shipper đã lấy hàng`, 'picked_up');
            }

            if (!showTracking.value) {
                fetchAll();
            }

            if (trackingItem.value?.id === e.item?.id) {
                trackingItem.value = { ...trackingItem.value, ...e.item };
            }
        })
        .listen(
            '.shipper.location.updated',
            (e: {
                shipper_id: number;
                latitude: number;
                longitude: number;
            }) => {
                const s = activeShippers.value.find(
                    (x) => x.id === e.shipper_id,
                );

                if (s) {
                    s.current_lat = e.latitude;
                    s.current_lng = e.longitude;
                    s.has_gps = true;
                    s.last_seen_at = new Date().toISOString();
                    s.trail = [
                        ...(s.trail ?? []).slice(-29),
                        { lat: e.latitude, lng: e.longitude },
                    ];
                }
            },
        )
        .listen('.delivery.eta.updated', () => fetchAll());
}

// ─── Keyboard shortcuts ───────────────────────────────────────────────────────
function handleKeydown(e: KeyboardEvent) {
    if (
        e.target instanceof HTMLInputElement ||
        e.target instanceof HTMLTextAreaElement
    ) {
        return;
    }

    if (e.key === 'Escape') {
        closeTracking();
        closeCreateModal();
    }

    if (e.key === 'r' || e.key === 'R') {
        fetchAll();
    }

    if (e.key === 'a' || e.key === 'A') {
        selectAll();
    }

    if (e.key === 'd' || e.key === 'D') {
        if (selectedOrders.value.size > 0) {
            openCreateModal();
        }
    }
}

// ─── Utilities ────────────────────────────────────────────────────────────────
function getCsrf(): string {
    return (
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content ?? ''
    );
}
function fmtTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleTimeString('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
    });
}
function fmtEta(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const diff = Math.round((new Date(iso).getTime() - Date.now()) / 60000);

    if (diff <= 0) {
        return 'Đang đến';
    }

    return `~${diff} phút`;
}
function vehicleLabel(type: string): string {
    return (
        (
            { bike: 'Xe đạp', motorbike: 'Xe máy', car: 'Ô tô' } as Record<
                string,
                string
            >
        )[type] ?? type
    );
}
function statusLabel(s: string): string {
    return (
        (
            {
                pending: 'Chờ',
                picked_up: 'Đã lấy',
                delivered: 'Đã giao',
                failed: 'Thất bại',
                dispatched: 'Đã giao việc',
                in_progress: 'Đang giao',
            } as Record<string, string>
        )[s] ?? s
    );
}
function statusVariant(
    s: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (s === 'delivered') {
        return 'default';
    }

    if (s === 'failed') {
        return 'destructive';
    }

    return 'secondary';
}
function scoreColor(score: number): string {
    if (score < 0.33) {
        return 'bg-emerald-500';
    }

    if (score < 0.66) {
        return 'bg-amber-500';
    }

    return 'bg-red-500';
}
function scoreLabel(score: number): string {
    if (score < 0.33) {
        return 'Tốt';
    }

    if (score < 0.66) {
        return 'Khá';
    }

    return 'Cao tải';
}
function activityColor(type: ActivityEvent['type']): string {
    return {
        delivered: 'text-emerald-500',
        failed: 'text-red-500',
        dispatched: 'text-blue-500',
        picked_up: 'text-purple-500',
        gps: 'text-muted-foreground',
    }[type];
}
function activityTimeLabel(time: Date): string {
    const secs = Math.round((Date.now() - time.getTime()) / 1000);

    if (secs < 60) {
        return `${secs}s`;
    }

    return `${Math.round(secs / 60)}m`;
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
    fetchAll();
    setupWebSocket();
    startAutoRefresh();
    etaTicker = setInterval(() => {
        tickNow.value = Date.now();
    }, 1000) as unknown as number;
    document.addEventListener('keydown', handleKeydown);
});
onUnmounted(() => {
    if (echoChannel) {
        echoChannel
            .stopListening('.batch.dispatched')
            .stopListening('.delivery.status.updated')
            .stopListening('.shipper.location.updated')
            .stopListening('.delivery.eta.updated');
    }

    if (etaTicker) {
        clearInterval(etaTicker);
    }

    stopAutoRefresh();
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Head title="Quản lý Giao hàng" />

    <div class="flex h-full flex-col gap-3 p-4">
        <!-- ── Header ─────────────────────────────────────────────────────── -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Điều phối Giao hàng
                </h1>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Smart Routing &amp; Dispatch · Real-time
                    <span v-if="lastRefreshedLabel" class="ml-1 opacity-70"
                        >· {{ lastRefreshedLabel }}</span
                    >
                </p>
            </div>
            <div class="flex items-center gap-2">
                <!-- Activity feed toggle -->
                <div
                    v-if="activityFeed.length > 0"
                    class="hidden items-center gap-1.5 rounded-lg border bg-card px-2.5 py-1.5 text-xs lg:flex"
                >
                    <Radio class="h-3 w-3 animate-pulse text-emerald-500" />
                    <span class="text-muted-foreground">{{
                        activityFeed[0]?.message
                    }}</span>
                    <span class="text-muted-foreground opacity-60">{{
                        activityTimeLabel(activityFeed[0]?.time)
                    }}</span>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="border-indigo-500/30 bg-indigo-500/10 font-semibold text-indigo-600 hover:bg-indigo-500/20 dark:text-indigo-400"
                    @click="showSopModal = true"
                >
                    <BookOpen class="mr-1.5 h-3.5 w-3.5 text-indigo-500" />
                    Hướng dẫn vận hành SOP
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    :disabled="loading"
                    @click="fetchAll"
                >
                    <RefreshCw
                        :class="loading ? 'animate-spin' : ''"
                        class="mr-1.5 h-3.5 w-3.5"
                    />
                    Làm mới
                </Button>
            </div>
        </div>

        <!-- ── KPI cards ──────────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
            <!-- Chờ giao -->
            <div
                class="relative overflow-hidden rounded-2xl border border-amber-500/20 bg-gradient-to-br from-amber-500/10 to-amber-500/5 p-4 transition-all hover:scale-[1.02] hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div class="rounded-xl bg-amber-500/20 p-2">
                        <Package class="h-4 w-4 text-amber-500" />
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tabular-nums">
                        {{ displayPending }}
                    </div>
                    <div class="mt-0.5 text-xs text-muted-foreground">
                        Chờ giao
                    </div>
                </div>
            </div>

            <!-- Shipper online -->
            <div
                class="relative overflow-hidden rounded-2xl border border-blue-500/20 bg-gradient-to-br from-blue-500/10 to-blue-500/5 p-4 transition-all hover:scale-[1.02] hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div class="rounded-xl bg-blue-500/20 p-2">
                        <Users class="h-4 w-4 text-blue-500" />
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tabular-nums">
                        {{ displayShippers }}
                    </div>
                    <div class="mt-0.5 text-xs text-muted-foreground">
                        Shipper online
                    </div>
                </div>
            </div>

            <!-- Batch đang chạy -->
            <div
                class="relative overflow-hidden rounded-2xl border border-purple-500/20 bg-gradient-to-br from-purple-500/10 to-purple-500/5 p-4 transition-all hover:scale-[1.02] hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div class="rounded-xl bg-purple-500/20 p-2">
                        <Truck class="h-4 w-4 text-purple-500" />
                    </div>
                    <span
                        v-if="stats.active_batches > 0"
                        class="relative flex h-2 w-2"
                    >
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-purple-400 opacity-75"
                        ></span>
                        <span
                            class="relative inline-flex h-2 w-2 rounded-full bg-purple-500"
                        ></span>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tabular-nums">
                        {{ displayBatches }}
                    </div>
                    <div class="mt-0.5 text-xs text-muted-foreground">
                        Batch đang chạy
                    </div>
                </div>
            </div>

            <!-- Đã giao hôm nay -->
            <div
                class="relative overflow-hidden rounded-2xl border border-emerald-500/20 bg-gradient-to-br from-emerald-500/10 to-emerald-500/5 p-4 transition-all hover:scale-[1.02] hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div class="rounded-xl bg-emerald-500/20 p-2">
                        <CheckCircle2 class="h-4 w-4 text-emerald-500" />
                    </div>
                    <span
                        v-if="trendDelivered() !== 0"
                        class="flex items-center gap-0.5 text-xs font-medium"
                        :class="
                            trendDelivered() > 0
                                ? 'text-emerald-500'
                                : 'text-red-500'
                        "
                    >
                        <TrendingUp
                            v-if="trendDelivered() > 0"
                            class="h-3 w-3"
                        />
                        <TrendingDown v-else class="h-3 w-3" />
                        {{ Math.abs(trendDelivered()) }}
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tabular-nums">
                        {{ displayDelivered }}
                    </div>
                    <div class="mt-0.5 text-xs text-muted-foreground">
                        Đã giao hôm nay
                    </div>
                </div>
            </div>

            <!-- Thất bại hôm nay -->
            <div
                class="relative overflow-hidden rounded-2xl border border-red-500/20 bg-gradient-to-br from-red-500/10 to-red-500/5 p-4 transition-all hover:scale-[1.02] hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div class="rounded-xl bg-red-500/20 p-2">
                        <XCircle class="h-4 w-4 text-red-500" />
                    </div>
                    <span
                        v-if="stats.failed_today > 0"
                        class="relative flex h-2 w-2"
                    >
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"
                        ></span>
                        <span
                            class="relative inline-flex h-2 w-2 rounded-full bg-red-500"
                        ></span>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tabular-nums">
                        {{ displayFailed }}
                    </div>
                    <div class="mt-0.5 text-xs text-muted-foreground">
                        Thất bại hôm nay
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Delivery Analytics & Performance Panel -->
        <Card
            class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
        >
            <CardHeader
                class="flex flex-row items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800"
            >
                <div class="flex items-center gap-2">
                    <Bot class="size-5 animate-bounce text-indigo-500" />
                    <div>
                        <CardTitle class="text-sm font-bold"
                            >Phân tích & Đánh giá Hiệu suất Giao hàng
                            AI</CardTitle
                        >
                        <p class="text-[10px] text-muted-foreground">
                            Phân tích tỷ lệ giao nhận thành công và đề xuất điều
                            phối tối ưu thời gian thực
                        </p>
                    </div>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-indigo-650 h-8 text-xs dark:text-indigo-400"
                    @click="showDeliveryAnalytics = !showDeliveryAnalytics"
                >
                    {{ showDeliveryAnalytics ? 'Thu gọn' : 'Mở rộng' }}
                </Button>
            </CardHeader>

            <CardContent v-if="showDeliveryAnalytics" class="space-y-4 p-5">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <!-- Column 1: Delivery Status Breakdown (Chart) -->
                    <div class="space-y-3">
                        <h4
                            class="dark:text-slate-350 flex items-center gap-1 text-xs font-bold text-slate-700"
                        >
                            <Sparkles class="size-3.5 text-indigo-500" /> Tỷ lệ
                            Giao hàng Thành công
                        </h4>

                        <!-- Mini SVG Pie Chart -->
                        <div class="flex items-center gap-4">
                            <div
                                class="relative flex h-24 w-24 items-center justify-center"
                            >
                                <svg
                                    viewBox="0 0 36 36"
                                    class="h-full w-full -rotate-90 transform"
                                >
                                    <circle
                                        cx="18"
                                        cy="18"
                                        r="15.915"
                                        fill="none"
                                        stroke="#f1f5f9"
                                        stroke-width="4"
                                        class="dark:stroke-slate-800"
                                    />
                                    <template v-if="totalDeliveredToday > 0">
                                        <!-- Success Segment -->
                                        <circle
                                            cx="18"
                                            cy="18"
                                            r="15.915"
                                            fill="none"
                                            stroke="#10b981"
                                            stroke-width="4"
                                            :stroke-dasharray="`${deliverySuccessRate} ${100 - deliverySuccessRate}`"
                                            stroke-dashoffset="0"
                                        />
                                        <!-- Failed Segment -->
                                        <circle
                                            cx="18"
                                            cy="18"
                                            r="15.915"
                                            fill="none"
                                            stroke="#ef4444"
                                            stroke-width="4"
                                            :stroke-dasharray="`${100 - deliverySuccessRate} ${deliverySuccessRate}`"
                                            :stroke-dashoffset="`-${deliverySuccessRate}`"
                                        />
                                    </template>
                                </svg>
                                <div
                                    class="absolute flex flex-col items-center justify-center text-center"
                                >
                                    <span
                                        v-if="totalDeliveredToday > 0"
                                        class="text-xs font-black text-slate-800 dark:text-slate-100"
                                        >{{
                                            deliverySuccessRate.toFixed(0)
                                        }}%</span
                                    >
                                    <span
                                        v-else
                                        class="text-xs font-bold text-slate-400 dark:text-slate-500"
                                        >0 đơn</span
                                    >
                                    <span
                                        class="text-[8px] text-muted-foreground uppercase"
                                        >{{
                                            totalDeliveredToday > 0
                                                ? 'Thành công'
                                                : 'Chưa có lượt'
                                        }}</span
                                    >
                                </div>
                            </div>

                            <div class="flex-1 space-y-1.5 text-[11px]">
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1"
                                        ><span
                                            class="h-2 w-2 rounded-full bg-emerald-500"
                                        ></span>
                                        Đã giao</span
                                    >
                                    <span class="font-bold"
                                        >{{ stats.delivered_today }} đơn</span
                                    >
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1"
                                        ><span
                                            class="h-2 w-2 rounded-full bg-red-500"
                                        ></span>
                                        Thất bại</span
                                    >
                                    <span class="font-bold"
                                        >{{ stats.failed_today }} đơn</span
                                    >
                                </div>
                                <div
                                    class="mt-1 flex items-center justify-between border-t pt-1 text-muted-foreground"
                                >
                                    <span>Tổng chuyến</span>
                                    <span class="font-bold"
                                        >{{
                                            stats.delivered_today +
                                            stats.failed_today
                                        }}
                                        đơn</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Logistics Load & Efficiency (KPI Progress) -->
                    <div
                        class="space-y-3 border-l border-slate-100 pl-0 md:pl-6 dark:border-slate-800"
                    >
                        <h4
                            class="dark:text-slate-350 flex items-center gap-1 text-xs font-bold text-slate-700"
                        >
                            <Activity class="size-3.5 text-emerald-500" /> Tải
                            lượng & Hiệu suất Shipper
                        </h4>
                        <div class="space-y-3 pt-1">
                            <div>
                                <div
                                    class="mb-1 flex items-center justify-between text-[11px]"
                                >
                                    <span class="text-muted-foreground"
                                        >Tận dụng Shipper (Active
                                        Shippers)</span
                                    >
                                    <span class="font-bold text-indigo-600"
                                        >{{
                                            stats.active_shippers > 0
                                                ? (
                                                      (stats.active_batches /
                                                          stats.active_shippers) *
                                                      100
                                                  ).toFixed(0)
                                                : 0
                                        }}%</span
                                    >
                                </div>
                                <div
                                    class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                >
                                    <div
                                        class="h-full rounded-full bg-indigo-500"
                                        :style="{
                                            width: `${stats.active_shippers > 0 ? Math.min(100, (stats.active_batches / stats.active_shippers) * 100) : 0}%`,
                                        }"
                                    ></div>
                                </div>
                            </div>

                            <div
                                class="grid grid-cols-2 gap-2 pt-1 text-[11px]"
                            >
                                <div
                                    class="rounded-lg bg-slate-50 p-2 text-center dark:bg-slate-900/50"
                                >
                                    <div
                                        class="text-xs font-bold text-indigo-600 dark:text-indigo-400"
                                    >
                                        {{ stats.active_batches }}
                                    </div>
                                    <div
                                        class="text-[9px] text-muted-foreground"
                                    >
                                        Batch đang chạy
                                    </div>
                                </div>
                                <div
                                    class="rounded-lg bg-slate-50 p-2 text-center dark:bg-slate-900/50"
                                >
                                    <div
                                        class="text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                    >
                                        {{ stats.active_shippers }}
                                    </div>
                                    <div
                                        class="text-[9px] text-muted-foreground"
                                    >
                                        Tài xế online
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column 3: AI Logistics Recommendations -->
                    <div
                        class="space-y-3 border-l border-slate-100 pl-0 md:pl-6 dark:border-slate-800"
                    >
                        <div class="flex items-center gap-1.5">
                            <span
                                class="flex h-2 w-2 rounded-full"
                                :class="{
                                    'bg-emerald-500':
                                        deliveryEvaluation.status === 'good',
                                    'bg-amber-500':
                                        deliveryEvaluation.status ===
                                        'attention',
                                    'bg-rose-500':
                                        deliveryEvaluation.status === 'warning',
                                }"
                            ></span>
                            <h4
                                class="dark:text-slate-350 text-xs font-bold text-slate-700"
                            >
                                {{ deliveryEvaluation.title }}
                            </h4>
                        </div>
                        <p
                            class="text-[11px] leading-relaxed text-muted-foreground"
                        >
                            {{ deliveryEvaluation.text }}
                        </p>

                        <div class="space-y-1.5 pt-1">
                            <div
                                v-for="(tip, idx) in deliveryEvaluation.tips"
                                :key="idx"
                                class="flex items-start gap-1.5 text-[10px] text-slate-700 dark:text-slate-400"
                            >
                                <span class="text-indigo-500 select-none"
                                    >•</span
                                >
                                <span>{{ tip }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- ── Main grid ───────────────────────────────────────────────────── -->
        <div
            class="grid min-h-0 flex-1 grid-cols-1 gap-3 lg:grid-cols-[280px_1fr_300px]"
        >
            <!-- Left: Unassigned orders -->
            <Card class="flex min-h-0 flex-col overflow-hidden">
                <CardHeader class="pt-3 pb-2">
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-sm">
                            Đơn chờ giao
                            <span
                                class="ml-1 rounded-full bg-amber-500/15 px-1.5 py-0.5 text-xs font-bold text-amber-600"
                            >
                                {{ unassignedOrders.length }}
                            </span>
                        </CardTitle>
                        <!-- Sort menu -->
                        <div class="relative">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="gap-1"
                                @click="showSortMenu = !showSortMenu"
                            >
                                <ArrowUpDown class="h-3 w-3" />
                            </Button>
                            <div
                                v-if="showSortMenu"
                                class="absolute top-full right-0 z-10 mt-1 min-w-[130px] rounded-lg border bg-popover p-1 shadow-lg"
                            >
                                <button
                                    v-for="opt in [
                                        { v: 'newest', l: 'Mới nhất' },
                                        { v: 'oldest', l: 'Cũ nhất' },
                                        { v: 'amount', l: 'Số tiền' },
                                    ]"
                                    :key="opt.v"
                                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs hover:bg-muted"
                                    :class="
                                        orderSort === opt.v
                                            ? 'font-semibold text-primary'
                                            : ''
                                    "
                                    @click="
                                        orderSort = opt.v as any;
                                        showSortMenu = false;
                                    "
                                >
                                    {{ opt.l }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Search -->
                    <div class="relative mt-1.5">
                        <Search
                            class="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            v-model="orderSearch"
                            type="text"
                            placeholder="Tìm khách, địa chỉ..."
                            class="w-full rounded-lg border bg-muted/50 py-1.5 pr-3 pl-8 text-xs outline-none focus:border-primary focus:ring-1 focus:ring-primary/30"
                        />
                    </div>
                    <!-- Select actions + dispatch -->
                    <div class="mt-1.5 flex gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-xs"
                            @click="selectAll"
                            >Chọn tất cả</Button
                        >
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-xs"
                            @click="clearSelection"
                            >Bỏ chọn</Button
                        >
                    </div>
                    <Button
                        v-if="selectedOrders.size > 0"
                        size="sm"
                        class="mt-1 w-full"
                        @click="openCreateModal"
                    >
                        <Zap class="mr-1.5 h-3.5 w-3.5" />
                        Dispatch {{ selectedOrders.size }} đơn
                    </Button>
                </CardHeader>

                <CardContent class="min-h-0 flex-1 overflow-y-auto p-2">
                    <!-- Skeleton loading -->
                    <template v-if="loading && unassignedOrders.length === 0">
                        <div
                            v-for="i in 5"
                            :key="i"
                            class="mb-2 rounded-lg border p-2.5"
                        >
                            <div class="flex gap-2">
                                <div class="flex-1 space-y-2">
                                    <div
                                        class="h-3.5 w-24 animate-pulse rounded bg-muted"
                                    ></div>
                                    <div
                                        class="h-3 w-40 animate-pulse rounded bg-muted"
                                    ></div>
                                    <div
                                        class="h-3 w-32 animate-pulse rounded bg-muted"
                                    ></div>
                                </div>
                                <div
                                    class="h-4 w-14 animate-pulse rounded bg-muted"
                                ></div>
                            </div>
                        </div>
                    </template>

                    <!-- Empty state -->
                    <div
                        v-else-if="filteredOrders.length === 0"
                        class="flex flex-col items-center justify-center gap-2 py-10"
                    >
                        <div class="rounded-full bg-muted p-4">
                            <CheckCheck class="h-8 w-8 text-muted-foreground" />
                        </div>
                        <p class="text-sm font-medium">
                            {{
                                orderSearch
                                    ? 'Không tìm thấy'
                                    : 'Không có đơn chờ'
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                orderSearch
                                    ? 'Thử tìm kiếm khác'
                                    : 'Tất cả đơn đã được giao!'
                            }}
                        </p>
                    </div>

                    <!-- Order cards -->
                    <div
                        v-for="order in filteredOrders"
                        :key="order.id"
                        class="group mb-2 cursor-pointer overflow-hidden rounded-lg border text-sm transition-all"
                        :class="
                            selectedOrders.has(order.id)
                                ? 'border-primary bg-primary/5 shadow-sm'
                                : 'border-border hover:border-primary/40 hover:bg-muted/30'
                        "
                        @click="toggleOrder(order.id)"
                    >
                        <!-- Urgency strip -->
                        <div class="flex">
                            <div
                                class="w-1 shrink-0 rounded-l-lg transition-colors"
                                :class="{
                                    'bg-red-500': urgency(order) === 'urgent',
                                    'bg-amber-400':
                                        urgency(order) === 'waiting',
                                    'bg-emerald-400': urgency(order) === 'new',
                                }"
                            />
                            <div class="flex-1 p-2.5">
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <!-- Checkbox circle -->
                                    <div
                                        class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 transition-all"
                                        :class="
                                            selectedOrders.has(order.id)
                                                ? 'border-primary bg-primary'
                                                : 'border-muted-foreground/30'
                                        "
                                    >
                                        <CheckCircle2
                                            v-if="selectedOrders.has(order.id)"
                                            class="h-3 w-3 text-primary-foreground"
                                        />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-semibold">
                                            {{ order.order_number }}
                                        </p>
                                        <p
                                            v-if="order.delivery_detail"
                                            class="mt-0.5 truncate text-xs text-muted-foreground"
                                        >
                                            {{
                                                order.delivery_detail
                                                    .customer_name
                                            }}
                                            <span class="opacity-60">
                                                ·
                                                {{
                                                    order.delivery_detail.phone
                                                }}</span
                                            >
                                        </p>
                                        <p
                                            v-if="order.delivery_detail"
                                            class="mt-0.5 flex items-start gap-1 text-xs text-muted-foreground"
                                        >
                                            <MapPin
                                                class="mt-0.5 h-3 w-3 shrink-0"
                                            />
                                            <span class="line-clamp-1">{{
                                                order.delivery_detail.address
                                            }}</span>
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="font-semibold">
                                            {{
                                                order.total_amount.toLocaleString(
                                                    'vi-VN',
                                                )
                                            }}₫
                                        </p>
                                        <Badge
                                            variant="outline"
                                            class="mt-0.5 text-[10px]"
                                            >{{ order.items_count }} món</Badge
                                        >
                                    </div>
                                </div>

                                <div class="mt-1.5 flex items-center gap-2">
                                    <!-- Waiting badge -->
                                    <span
                                        class="rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                        :class="{
                                            'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400':
                                                urgency(order) === 'urgent',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400':
                                                urgency(order) === 'waiting',
                                            'bg-muted text-muted-foreground':
                                                urgency(order) === 'new',
                                        }"
                                    >
                                        <span
                                            v-if="urgency(order) === 'urgent'"
                                            class="mr-0.5"
                                            >⚡</span
                                        >
                                        {{
                                            urgency(order) === 'urgent'
                                                ? 'Cấp thiết'
                                                : urgency(order) === 'waiting'
                                                  ? 'Chờ lâu'
                                                  : 'Mới'
                                        }}
                                        · {{ waitingLabel(order) }}
                                    </span>
                                    <!-- GPS warning -->
                                    <span
                                        v-if="!order.delivery_detail?.latitude"
                                        class="flex items-center gap-0.5 text-[10px] text-amber-600"
                                    >
                                        <AlertTriangle class="h-3 w-3" />Thiếu
                                        GPS
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Center: Map -->
            <Card class="flex min-h-0 flex-col overflow-hidden">
                <!-- Map tabs with animated indicator -->
                <div class="relative flex gap-0 border-b px-3 pt-2">
                    <button
                        v-for="tab in mapTabs"
                        :key="tab.id"
                        class="relative flex items-center gap-1.5 px-3 pt-1 pb-2.5 text-xs font-medium transition-colors"
                        :class="
                            mapTab === tab.id
                                ? 'text-primary'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="mapTab = tab.id as any"
                    >
                        <component :is="tab.icon" class="h-3.5 w-3.5" />
                        {{ tab.label }}
                        <span
                            v-if="tab.count > 0"
                            class="ml-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                            :class="
                                mapTab === tab.id
                                    ? 'bg-primary/20 text-primary'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ tab.count }}
                        </span>
                        <!-- Active underline -->
                        <span
                            v-if="mapTab === tab.id"
                            class="absolute right-0 bottom-0 left-0 h-0.5 rounded-full bg-primary transition-all"
                        />
                    </button>
                </div>
                <CardContent class="min-h-0 flex-1 p-2">
                    <DeliveryMap
                        :shippers="mapShippers"
                        :orders="mapOrders"
                        :focus-shipper-id="focusedShipperId"
                        height="100%"
                        @click-shipper="focusedShipperId = $event"
                        @click-order="
                            (id) => {
                                const o = unassignedOrders.find(
                                    (x) => x.id === id,
                                );
                                if (o) toggleOrder(o.id);
                            }
                        "
                    />
                </CardContent>
            </Card>

            <!-- Right: Shippers panel -->
            <Card class="flex min-h-0 flex-col overflow-hidden">
                <CardHeader class="pt-3 pb-2">
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-sm">
                            Shippers
                            <span
                                class="ml-1 text-xs font-normal text-muted-foreground"
                                >({{ activeShippers.length }})</span
                            >
                        </CardTitle>
                        <div
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <span class="relative flex h-2 w-2">
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                                ></span>
                                <span
                                    class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"
                                ></span>
                            </span>
                            Live
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="min-h-0 flex-1 overflow-y-auto p-2">
                    <!-- Skeleton -->
                    <template v-if="loading && activeShippers.length === 0">
                        <div
                            v-for="i in 3"
                            :key="i"
                            class="mb-3 rounded-xl border p-3"
                        >
                            <div class="flex items-center gap-2.5">
                                <div
                                    class="h-9 w-9 animate-pulse rounded-full bg-muted"
                                ></div>
                                <div class="flex-1 space-y-1.5">
                                    <div
                                        class="h-3.5 w-28 animate-pulse rounded bg-muted"
                                    ></div>
                                    <div
                                        class="h-3 w-20 animate-pulse rounded bg-muted"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Empty state -->
                    <div
                        v-else-if="activeShippers.length === 0"
                        class="flex flex-col items-center justify-center gap-2 py-10"
                    >
                        <div class="rounded-full bg-muted p-4">
                            <Users class="h-8 w-8 text-muted-foreground" />
                        </div>
                        <p class="text-sm font-medium">Chưa có shipper</p>
                        <p class="text-xs text-muted-foreground">
                            Khi shipper đăng nhập sẽ hiển thị ở đây
                        </p>
                    </div>

                    <!-- Shipper cards -->
                    <div
                        v-for="shipper in activeShippers"
                        :key="shipper.id"
                        class="mb-3 cursor-pointer rounded-xl border transition-all hover:border-primary/40 hover:shadow-sm"
                        :class="
                            focusedShipperId === shipper.id
                                ? 'border-primary bg-primary/5 shadow-sm shadow-primary/10'
                                : 'border-border'
                        "
                        @click="
                            focusedShipperId =
                                focusedShipperId === shipper.id
                                    ? null
                                    : shipper.id
                        "
                    >
                        <div class="p-3">
                            <!-- Header: avatar + info + GPS -->
                            <div class="flex items-center gap-2.5">
                                <!-- Avatar -->
                                <div class="relative shrink-0">
                                    <div
                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br text-sm font-bold text-white"
                                        :class="avatarColor(shipper.name)"
                                    >
                                        {{
                                            shipper.name.charAt(0).toUpperCase()
                                        }}
                                    </div>
                                    <span
                                        class="absolute -right-1 -bottom-1 text-sm leading-none"
                                        >{{
                                            vehicleEmoji(shipper.vehicle_type)
                                        }}</span
                                    >
                                </div>
                                <!-- Name + plate -->
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">
                                        {{ shipper.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            shipper.vehicle_plate ||
                                            vehicleLabel(shipper.vehicle_type)
                                        }}
                                    </p>
                                </div>
                                <!-- GPS status -->
                                <div class="flex shrink-0 items-center gap-1">
                                    <span class="relative flex h-2 w-2">
                                        <span
                                            v-if="shipper.has_gps"
                                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                                        ></span>
                                        <span
                                            class="relative inline-flex h-2 w-2 rounded-full"
                                            :class="
                                                shipper.has_gps
                                                    ? 'bg-emerald-500'
                                                    : 'bg-gray-400'
                                            "
                                        ></span>
                                    </span>
                                    <span
                                        class="text-xs"
                                        :class="
                                            shipper.has_gps
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{
                                            shipper.has_gps
                                                ? 'Live'
                                                : lastSeenLabel(
                                                      shipper.last_seen_at,
                                                  )
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- Load bar -->
                            <div class="mt-2.5 flex items-center gap-2">
                                <div
                                    class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="
                                            loadPercent(shipper) > 80
                                                ? 'bg-red-500'
                                                : loadPercent(shipper) > 50
                                                  ? 'bg-amber-500'
                                                  : 'bg-primary'
                                        "
                                        :style="{
                                            width: loadPercent(shipper) + '%',
                                        }"
                                    />
                                </div>
                                <span
                                    class="shrink-0 text-xs text-muted-foreground"
                                >
                                    {{ shipper.current_load.orders }}/{{
                                        shipper.max_orders_per_batch
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Active batch items -->
                        <div
                            v-if="shipper.active_batch"
                            class="rounded-b-xl border-t bg-muted/20 px-3 py-2"
                        >
                            <div
                                class="mb-1.5 flex items-center justify-between"
                            >
                                <span
                                    class="flex items-center gap-1 text-xs font-semibold"
                                >
                                    <Route class="h-3 w-3" />Batch #{{
                                        shipper.active_batch.id
                                    }}
                                </span>
                                <Badge variant="outline" class="text-[10px]">{{
                                    statusLabel(shipper.active_batch.status)
                                }}</Badge>
                            </div>
                            <div
                                v-for="item in shipper.active_batch.items"
                                :key="item.id"
                                class="mb-1 flex cursor-pointer items-center gap-1.5 rounded-lg px-1.5 py-1 text-xs transition-colors hover:bg-muted"
                                @click.stop="openTracking(item, shipper)"
                            >
                                <span
                                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white"
                                    :class="{
                                        'bg-emerald-500':
                                            item.status === 'delivered',
                                        'bg-red-500': item.status === 'failed',
                                        'bg-purple-500':
                                            item.status === 'picked_up',
                                        'bg-amber-500':
                                            item.status === 'pending',
                                    }"
                                    >{{ item.sequence_order }}</span
                                >
                                <span class="min-w-0 flex-1 truncate">{{
                                    item.customer_name ??
                                    item.address ??
                                    `#${item.order_id}`
                                }}</span>
                                <span
                                    v-if="
                                        item.status === 'pending' ||
                                        item.status === 'picked_up'
                                    "
                                    class="shrink-0 text-muted-foreground tabular-nums"
                                >
                                    {{ fmtEta(item.eta) }}
                                </span>
                                <CheckCircle2
                                    v-if="item.status === 'delivered'"
                                    class="h-3.5 w-3.5 shrink-0 text-emerald-500"
                                />
                                <XCircle
                                    v-else-if="item.status === 'failed'"
                                    class="h-3.5 w-3.5 shrink-0 text-red-500"
                                />
                                <Eye
                                    class="h-3.5 w-3.5 shrink-0 text-muted-foreground opacity-60"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Activity feed (bottom of shipper panel) -->
                    <div
                        v-if="activityFeed.length > 0"
                        class="mt-2 border-t pt-2"
                    >
                        <p
                            class="mb-1.5 flex items-center gap-1 text-xs font-semibold text-muted-foreground"
                        >
                            <Activity class="h-3 w-3" />Hoạt động gần đây
                        </p>
                        <TransitionGroup
                            name="feed"
                            tag="div"
                            class="space-y-1"
                        >
                            <div
                                v-for="ev in activityFeed.slice(0, 5)"
                                :key="ev.id"
                                class="flex items-start gap-1.5 rounded-lg px-1.5 py-1 text-xs"
                            >
                                <span
                                    class="mt-0.5 h-2 w-2 shrink-0 rounded-full"
                                    :class="{
                                        'bg-emerald-500':
                                            ev.type === 'delivered',
                                        'bg-red-500': ev.type === 'failed',
                                        'bg-blue-500': ev.type === 'dispatched',
                                        'bg-purple-500':
                                            ev.type === 'picked_up',
                                        'bg-muted-foreground':
                                            ev.type === 'gps',
                                    }"
                                />
                                <span class="flex-1 text-muted-foreground">{{
                                    ev.message
                                }}</span>
                                <span
                                    class="shrink-0 text-muted-foreground opacity-60"
                                    >{{ activityTimeLabel(ev.time) }}</span
                                >
                            </div>
                        </TransitionGroup>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- ══ Tracking Drawer ════════════════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition name="tracking-slide">
            <div
                v-if="showTracking && trackingItem && trackingShipper"
                class="fixed inset-y-0 right-0 z-50 flex w-full max-w-sm flex-col bg-background shadow-2xl"
            >
                <!-- Header -->
                <div
                    class="flex items-center justify-between border-b px-4 py-3"
                >
                    <div>
                        <h2 class="font-semibold">Theo dõi đơn hàng</h2>
                        <p class="text-xs text-muted-foreground">
                            {{
                                trackingItem.order_number ??
                                `Order #${trackingItem.order_id}`
                            }}
                        </p>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeTracking"
                        ><X class="h-4 w-4"
                    /></Button>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto p-4">
                    <!-- ETA ring -->
                    <div
                        v-if="
                            trackingEta &&
                            !['delivered', 'failed'].includes(
                                trackingItem.status,
                            )
                        "
                        class="flex items-center justify-between rounded-2xl bg-primary/10 px-5 py-4"
                    >
                        <!-- SVG ring -->
                        <div
                            class="relative flex h-20 w-20 items-center justify-center"
                        >
                            <svg
                                class="h-20 w-20 -rotate-90"
                                viewBox="0 0 80 80"
                            >
                                <circle
                                    cx="40"
                                    cy="40"
                                    r="34"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="6"
                                    class="text-muted/40"
                                />
                                <circle
                                    cx="40"
                                    cy="40"
                                    r="34"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="6"
                                    :stroke-dasharray="`${2 * Math.PI * 34}`"
                                    :stroke-dashoffset="`${2 * Math.PI * 34 * (1 - (trackingEta?.pct ?? 0) / 100)}`"
                                    stroke-linecap="round"
                                    class="transition-all duration-1000"
                                    :class="
                                        (trackingEta?.mins ?? 99) > 10
                                            ? 'text-primary'
                                            : (trackingEta?.mins ?? 99) > 5
                                              ? 'text-amber-500'
                                              : 'text-red-500'
                                    "
                                />
                            </svg>
                            <div class="absolute flex flex-col items-center">
                                <span
                                    class="text-lg leading-tight font-bold tabular-nums"
                                    >{{ trackingEta?.label }}</span
                                >
                                <span class="text-[10px] text-muted-foreground"
                                    >còn lại</span
                                >
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-muted-foreground">
                                Dự kiến đến
                            </p>
                            <p class="font-semibold">
                                {{ fmtTime(trackingItem.eta) }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ trackingShipper.name }}
                            </p>
                            <p class="text-xs">
                                {{ vehicleEmoji(trackingShipper.vehicle_type) }}
                            </p>
                        </div>
                    </div>

                    <!-- Done states -->
                    <div
                        v-else-if="trackingItem.status === 'delivered'"
                        class="flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3 dark:bg-emerald-950/30"
                    >
                        <CheckCircle2 class="h-8 w-8 text-emerald-500" />
                        <div>
                            <p
                                class="font-semibold text-emerald-700 dark:text-emerald-400"
                            >
                                Đã giao thành công
                            </p>
                            <p
                                class="text-xs text-emerald-600 dark:text-emerald-500"
                            >
                                {{ fmtTime(trackingItem.delivered_at) }}
                            </p>
                        </div>
                    </div>
                    <div
                        v-else-if="trackingItem.status === 'failed'"
                        class="flex items-center gap-3 rounded-xl bg-red-50 px-4 py-3 dark:bg-red-950/30"
                    >
                        <XCircle class="h-8 w-8 text-red-500" />
                        <div>
                            <p
                                class="font-semibold text-red-700 dark:text-red-400"
                            >
                                Giao thất bại
                            </p>
                            <p class="text-xs text-red-600">
                                {{ fmtTime(trackingItem.delivered_at) }}
                            </p>
                        </div>
                    </div>

                    <!-- Delivery info -->
                    <Card>
                        <CardContent class="space-y-1.5 pt-3 text-sm">
                            <p class="font-semibold">
                                {{ trackingItem.customer_name }}
                            </p>
                            <p
                                class="flex items-start gap-1.5 text-muted-foreground"
                            >
                                <MapPin class="mt-0.5 h-4 w-4 shrink-0" />{{
                                    trackingItem.address
                                }}
                            </p>
                            <a
                                v-if="trackingItem.phone"
                                :href="`tel:${trackingItem.phone}`"
                                class="flex items-center gap-1.5 text-primary"
                            >
                                <Phone class="h-4 w-4" />{{
                                    trackingItem.phone
                                }}
                            </a>
                            <div
                                v-if="trackingItem.cod_amount > 0"
                                class="font-semibold text-amber-600"
                            >
                                COD:
                                {{
                                    trackingItem.cod_amount.toLocaleString(
                                        'vi-VN',
                                    )
                                }}₫
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Mini-map -->
                    <div
                        v-if="trackingShipper.current_lat && trackingNextStop"
                        class="overflow-hidden rounded-xl border"
                    >
                        <ShipperMiniMap
                            :lat="trackingShipper.current_lat!"
                            :lng="trackingShipper.current_lng!"
                            :next-stop="trackingNextStop"
                            :trail="trackingShipper.trail"
                        />
                        <div
                            class="flex items-center justify-between border-t bg-muted/30 px-3 py-1.5 text-xs text-muted-foreground"
                        >
                            <span
                                >{{
                                    vehicleEmoji(trackingShipper.vehicle_type)
                                }}
                                {{ trackingShipper.name }}</span
                            >
                            <span
                                :class="
                                    trackingShipper.has_gps
                                        ? 'text-emerald-600'
                                        : ''
                                "
                            >
                                {{
                                    trackingShipper.has_gps
                                        ? '🟢 Live GPS'
                                        : '⚫ Offline'
                                }}
                            </span>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-400"
                    >
                        <Navigation class="h-4 w-4 shrink-0" />
                        {{
                            !trackingShipper.current_lat
                                ? 'Shipper chưa bật GPS'
                                : 'Đơn chưa có tọa độ'
                        }}
                    </div>

                    <!-- Animated timeline -->
                    <div>
                        <p class="mb-3 text-sm font-semibold">Lịch trình</p>
                        <div class="relative">
                            <!-- Background line -->
                            <div
                                class="absolute top-4 bottom-4 left-[15px] w-px bg-muted"
                            ></div>
                            <!-- Progress line -->
                            <div
                                class="absolute top-4 left-[15px] w-px bg-primary transition-all duration-1000"
                                :style="{ height: trackingStepProgress + '%' }"
                            ></div>

                            <div
                                v-for="(step, i) in trackingSteps"
                                :key="i"
                                class="relative mb-5 flex items-start gap-3"
                            >
                                <!-- Circle -->
                                <div
                                    class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full transition-all duration-500"
                                    :class="
                                        step.done
                                            ? 'bg-primary shadow-md shadow-primary/30'
                                            : 'bg-muted'
                                    "
                                >
                                    <component
                                        :is="step.icon"
                                        class="h-3.5 w-3.5"
                                        :class="
                                            step.done
                                                ? 'text-primary-foreground'
                                                : 'text-muted-foreground'
                                        "
                                    />
                                    <!-- Pulse on current step -->
                                    <span
                                        v-if="isCurrentStep(i)"
                                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-30"
                                    />
                                </div>
                                <!-- Content -->
                                <div class="flex-1 pt-1">
                                    <p
                                        class="text-sm font-medium"
                                        :class="
                                            step.done
                                                ? 'text-foreground'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ step.label }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            step.time
                                                ? fmtTime(step.time)
                                                : step.done
                                                  ? 'Hoàn thành'
                                                  : 'Đang chờ...'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Batch progress -->
                    <Card>
                        <CardContent class="pt-3">
                            <p
                                class="mb-2 text-xs font-semibold text-muted-foreground"
                            >
                                Toàn bộ batch #{{
                                    trackingShipper.active_batch?.id
                                }}
                            </p>
                            <div class="space-y-1">
                                <div
                                    v-for="item in trackingShipper.active_batch
                                        ?.items ?? []"
                                    :key="item.id"
                                    class="flex items-center gap-2 text-xs"
                                    :class="
                                        item.id === trackingItem.id
                                            ? 'font-semibold text-primary'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    <span
                                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white"
                                        :class="{
                                            'bg-emerald-500':
                                                item.status === 'delivered',
                                            'bg-red-500':
                                                item.status === 'failed',
                                            'bg-purple-500':
                                                item.status === 'picked_up',
                                            'bg-amber-500':
                                                item.status === 'pending',
                                        }"
                                        >{{ item.sequence_order }}</span
                                    >
                                    <span class="flex-1 truncate">{{
                                        item.customer_name ??
                                        item.address ??
                                        `#${item.order_id}`
                                    }}</span>
                                    <span>{{ fmtEta(item.eta) }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </Transition>

        <!-- Backdrop -->
        <Transition name="fade">
            <div
                v-if="showTracking"
                class="fixed inset-0 z-40 bg-black/30"
                @click="closeTracking"
            />
        </Transition>
    </Teleport>

    <!-- ══ Create Batch Wizard Modal ══════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="showCreateModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="closeCreateModal"
            >
                <div
                    class="w-full max-w-xl overflow-hidden rounded-2xl bg-background shadow-2xl"
                >
                    <!-- Modal header with step indicator -->
                    <div class="border-b px-5 py-4">
                        <div class="flex items-center justify-between">
                            <h2 class="font-semibold">Tạo Batch Giao Hàng</h2>
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="closeCreateModal"
                                ><X class="h-4 w-4"
                            /></Button>
                        </div>
                        <!-- Step dots -->
                        <div class="mt-3 flex items-center gap-2">
                            <template v-for="step in [1, 2, 3]" :key="step">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold transition-all"
                                        :class="
                                            wizardStep >= step
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        {{ step }}
                                    </div>
                                    <span
                                        class="text-xs"
                                        :class="
                                            wizardStep >= step
                                                ? 'text-foreground'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{
                                            [
                                                'Đơn hàng',
                                                'Chọn shipper',
                                                'Xác nhận',
                                            ][step - 1]
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="step < 3"
                                    class="h-px flex-1"
                                    :class="
                                        wizardStep > step
                                            ? 'bg-primary'
                                            : 'bg-muted'
                                    "
                                />
                            </template>
                        </div>
                    </div>

                    <div class="max-h-[55vh] overflow-y-auto px-5 py-4">
                        <!-- ── Step 1: Review orders + clusters ── -->
                        <div v-if="wizardStep === 1">
                            <div v-if="wizardLoading" class="space-y-2">
                                <div
                                    v-for="i in 3"
                                    :key="i"
                                    class="h-10 animate-pulse rounded-lg bg-muted"
                                ></div>
                            </div>
                            <template v-else>
                                <!-- Cluster suggestions -->
                                <div
                                    v-if="suggestedBatches.length > 0"
                                    class="mb-4 rounded-xl bg-muted/50 p-3"
                                >
                                    <p
                                        class="mb-2 flex items-center gap-2 text-sm font-medium"
                                    >
                                        <LayoutGrid
                                            class="h-4 w-4 text-primary"
                                        />
                                        AI đề xuất
                                        {{ suggestedBatches.length }} cụm giao
                                        hàng
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="(c, i) in suggestedBatches"
                                            :key="i"
                                            class="rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors hover:border-primary hover:bg-primary/5"
                                            @click="selectCluster(c)"
                                        >
                                            Cụm {{ i + 1 }} — {{ c.count }} đơn
                                            · {{ c.radius_km }} km
                                        </button>
                                    </div>
                                </div>

                                <!-- Selected orders table -->
                                <p class="mb-2 text-sm font-medium">
                                    {{ selectedOrders.size }} đơn được chọn
                                </p>
                                <div class="space-y-1">
                                    <div
                                        v-for="order in unassignedOrders.filter(
                                            (o) => selectedOrders.has(o.id),
                                        )"
                                        :key="order.id"
                                        class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm"
                                    >
                                        <div>
                                            <p class="font-medium">
                                                {{ order.order_number }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    order.delivery_detail
                                                        ?.customer_name
                                                }}
                                            </p>
                                        </div>
                                        <div
                                            class="text-right text-xs text-muted-foreground"
                                        >
                                            {{
                                                order.total_amount.toLocaleString(
                                                    'vi-VN',
                                                )
                                            }}₫
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- ── Step 2: Choose shipper ── -->
                        <div v-else-if="wizardStep === 2">
                            <p class="mb-3 text-sm font-medium">
                                Chọn Shipper
                                <span class="text-destructive">*</span>
                            </p>
                            <div
                                v-if="suggestedShippers.length === 0"
                                class="py-8 text-center text-sm text-muted-foreground"
                            >
                                Không có shipper khả dụng
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="s in suggestedShippers"
                                    :key="s.id"
                                    class="cursor-pointer rounded-xl border p-3 transition-all"
                                    :class="
                                        selectedShipperId === s.id
                                            ? 'border-primary bg-primary/5 shadow-sm'
                                            : 'hover:border-primary/40 hover:bg-muted/30'
                                    "
                                    @click="selectedShipperId = s.id"
                                >
                                    <div class="flex items-center gap-3">
                                        <!-- Avatar mini -->
                                        <div
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-sm font-bold text-white"
                                            :class="avatarColor(s.name)"
                                        >
                                            {{ s.name.charAt(0) }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-medium">
                                                {{ s.name }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    vehicleEmoji(s.vehicle_type)
                                                }}
                                                {{ s.current_orders }}/{{
                                                    s.max_orders
                                                }}
                                                đơn ·
                                                {{ s.available_slots }} slot
                                                trống
                                            </p>
                                        </div>
                                        <!-- Score bar -->
                                        <div class="shrink-0 text-right">
                                            <div
                                                class="mb-1 flex items-center justify-end gap-1.5"
                                            >
                                                <span
                                                    class="text-xs font-medium"
                                                    >{{
                                                        scoreLabel(s.score)
                                                    }}</span
                                                >
                                                <div
                                                    class="h-2 w-16 overflow-hidden rounded-full bg-muted"
                                                >
                                                    <div
                                                        class="h-full rounded-full transition-all"
                                                        :class="
                                                            scoreColor(s.score)
                                                        "
                                                        :style="{
                                                            width:
                                                                s.score * 100 +
                                                                '%',
                                                        }"
                                                    />
                                                </div>
                                            </div>
                                            <Badge
                                                :variant="
                                                    s.has_gps
                                                        ? 'default'
                                                        : 'secondary'
                                                "
                                                class="text-[10px]"
                                            >
                                                {{
                                                    s.has_gps
                                                        ? '🟢 Online'
                                                        : '⚫ Offline'
                                                }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── Step 3: Confirm + route preview ── -->
                        <div v-else-if="wizardStep === 3">
                            <!-- Summary -->
                            <div
                                class="mb-4 rounded-xl bg-primary/10 px-4 py-3"
                            >
                                <p class="text-sm font-semibold">
                                    Tóm tắt dispatch
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    <span class="font-medium text-foreground"
                                        >{{ selectedOrders.size }} đơn</span
                                    >
                                    →
                                    <span class="font-medium text-foreground">{{
                                        suggestedShippers.find(
                                            (s) => s.id === selectedShipperId,
                                        )?.name
                                    }}</span>
                                </p>
                                <div
                                    v-if="routePreview"
                                    class="mt-2 flex gap-4 text-sm"
                                >
                                    <span class="text-muted-foreground"
                                        >📍
                                        {{ routePreview.total_distance_km }}
                                        km</span
                                    >
                                    <span class="text-muted-foreground"
                                        >⏱ ~{{
                                            routePreview.estimated_duration_minutes
                                        }}
                                        phút</span
                                    >
                                </div>
                            </div>

                            <!-- Route sequence -->
                            <div v-if="routeLoading" class="space-y-2">
                                <div
                                    v-for="i in 3"
                                    :key="i"
                                    class="h-10 animate-pulse rounded-lg bg-muted"
                                ></div>
                            </div>
                            <div v-else-if="routePreview" class="space-y-1.5">
                                <p
                                    class="mb-1.5 text-xs font-semibold text-muted-foreground"
                                >
                                    Lộ trình tối ưu
                                </p>
                                <div
                                    v-for="(stop, i) in routePreview.route"
                                    :key="stop.id"
                                    class="flex items-center gap-2.5 rounded-lg border px-3 py-2 text-sm"
                                >
                                    <span
                                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground"
                                    >
                                        {{ i + 1 }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium">
                                            {{ stop.customer }}
                                        </p>
                                        <p
                                            class="truncate text-xs text-muted-foreground"
                                        >
                                            {{ stop.address }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer buttons -->
                    <div class="flex gap-2 border-t px-5 py-4">
                        <Button
                            variant="outline"
                            class="flex-1"
                            @click="
                                wizardStep > 1
                                    ? wizardStep--
                                    : closeCreateModal()
                            "
                        >
                            {{ wizardStep > 1 ? 'Quay lại' : 'Hủy' }}
                        </Button>

                        <Button
                            v-if="wizardStep === 1"
                            class="flex-1"
                            @click="wizardStep = 2"
                        >
                            Chọn Shipper →
                        </Button>
                        <Button
                            v-else-if="wizardStep === 2"
                            class="flex-1"
                            :disabled="!selectedShipperId"
                            @click="goToStep3"
                        >
                            Xem lộ trình →
                        </Button>
                        <Button
                            v-else
                            class="flex-1 gap-2"
                            :disabled="batchCreating"
                            @click="createAndDispatchBatch"
                        >
                            <Send class="h-4 w-4" />
                            {{
                                batchCreating
                                    ? 'Đang dispatch...'
                                    : 'Dispatch ngay!'
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ── Modal Hướng dẫn Vận hành SOP 3 Bước ────────────────────────── -->
    <Dialog v-model:open="showSopModal">
        <DialogContent
            class="max-w-2xl overflow-hidden rounded-2xl border-slate-200 dark:border-slate-800"
        >
            <DialogHeader
                class="border-b bg-indigo-50/50 p-6 dark:bg-indigo-950/20"
            >
                <div
                    class="flex items-center gap-2 text-indigo-600 dark:text-indigo-400"
                >
                    <BookOpen class="size-5" />
                    <DialogTitle class="text-lg font-bold"
                        >Hướng Dẫn Quy Trình Điều Phối Giao Hàng
                        (SOP)</DialogTitle
                    >
                </div>
                <DialogDescription class="mt-1 text-xs text-muted-foreground">
                    Quy trình 3 bước chuẩn giúp Quản lý Nhà hàng & Nhân viên vận
                    hành mượt mà, chính xác
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-6 p-6">
                <!-- Bước 1 -->
                <div class="flex items-start gap-4">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 font-bold text-amber-600 dark:text-amber-400"
                    >
                        1
                    </div>
                    <div class="space-y-1">
                        <h4
                            class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-200"
                        >
                            <Package class="size-4 text-amber-500" />
                            Tiếp nhận Đơn Giao Hàng (Delivery Orders)
                        </h4>
                        <p
                            class="text-xs leading-relaxed text-muted-foreground"
                        >
                            Tất cả đơn hàng có phương thức
                            <strong>Giao tận nơi (Delivery)</strong> được khách
                            tạo từ trang <strong>Đặt Hàng Online</strong> hoặc
                            nhân viên tạo trên app <strong>POS</strong> sẽ tự
                            động đổ về danh sách
                            <strong>"Đơn chờ giao"</strong> bên trái.
                        </p>
                    </div>
                </div>

                <!-- Bước 2 -->
                <div
                    class="flex items-start gap-4 border-t border-slate-100 pt-4 dark:border-slate-800"
                >
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 font-bold text-indigo-600 dark:text-indigo-400"
                    >
                        2
                    </div>
                    <div class="space-y-1">
                        <h4
                            class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-200"
                        >
                            <Zap class="size-4 text-indigo-500" />
                            Điều phối &amp; Gộp chuyến Thông minh (Smart
                            Dispatching)
                        </h4>
                        <p
                            class="text-xs leading-relaxed text-muted-foreground"
                        >
                            Tích chọn 1 hoặc nhiều đơn cần giao ➔ Bấm
                            <strong>"Gợi ý Shipper thông minh"</strong>. Thuật
                            toán AI sẽ tự động phân tích GPS, bán kính di chuyển
                            và gợi ý Shipper phù hợp nhất để gom 1 chuyến
                            (Batching) tiết kiệm chi phí.
                        </p>
                    </div>
                </div>

                <!-- Bước 3 -->
                <div
                    class="flex items-start gap-4 border-t border-slate-100 pt-4 dark:border-slate-800"
                >
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 font-bold text-emerald-600 dark:text-emerald-400"
                    >
                        3
                    </div>
                    <div class="space-y-1">
                        <h4
                            class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-200"
                        >
                            <MapPin class="size-4 text-emerald-500" />
                            Theo dõi GPS Real-time &amp; Hoàn tất Đơn hàng
                        </h4>
                        <p
                            class="text-xs leading-relaxed text-muted-foreground"
                        >
                            Theo dõi vị trí tài xế di chuyển thời gian thực trên
                            bản đồ Leaflet GPS. Khi Shipper phát đơn thành công
                            trên app Shipper, hệ thống sẽ tự động cập nhật trạng
                            thái đơn sang <strong>"Hoàn thành"</strong>.
                        </p>
                    </div>
                </div>
            </div>

            <div
                class="flex items-center justify-between border-t bg-slate-50/50 px-6 py-4 dark:bg-slate-900/40"
            >
                <span class="text-[11px] text-muted-foreground"
                    >Mọi thắc mắc vận hành, liên hệ bộ phận hỗ trợ kỹ thuật
                    Aventura.</span
                >
                <Button
                    variant="default"
                    size="sm"
                    @click="showSopModal = false"
                >
                    Đã hiểu quy trình
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.tracking-slide-enter-active,
.tracking-slide-leave-active {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.tracking-slide-enter-from,
.tracking-slide-leave-to {
    transform: translateX(100%);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.feed-enter-active {
    transition: all 0.3s ease;
}
.feed-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}
.feed-leave-active {
    transition: all 0.2s ease;
}
.feed-leave-to {
    opacity: 0;
}
</style>
