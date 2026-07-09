<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ChefHat,
    Clock,
    User,
    Check,
    Bell,
    RefreshCw,
    Inbox,
    CheckCircle,
    UtensilsCrossed,
    MessageSquare,
    AlertTriangle,
    RotateCcw,
    Search,
} from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Echo from '@/lib/echo';

interface PendingItem {
    id: number;
    product_name: string;
    quantity: number;
    notes: string | null;
    sent_to_kitchen_at: string;
    sent_to_kitchen_at_raw: string;
    creator_name: string;
    table_name: string;
    table_id: number | null;
    prep_minutes: number;
}

interface CompletedItem {
    id: number;
    product_name: string;
    quantity: number;
    notes: string | null;
    prepared_at: string;
    table_name: string;
}

interface Product {
    id: number;
    name: string;
    price: number;
    category_name: string;
    paused_until: string | null;
    out_of_stock_until: string | null;
    is_paused: boolean;
    is_out_of_stock: boolean;
}

const props = defineProps<{
    pendingItems: PendingItem[];
    completedItems: CompletedItem[];
    products: Product[];
    kitchenStats: {
        done_today: number;
        avg_prep_minutes: number | null;
        slowest_prep_minutes: number | null;
    };
}>();

// Tab selector state
const activeTab = ref<'orders' | 'menu'>('orders');

// Search query for products
const searchQuery = ref('');

// Product status actions
const handlePauseProduct = (productId: number, minutes: number) => {
    router.post(
        `/kitchen/products/${productId}/pause`,
        { minutes },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const handleOutOfStockProduct = (productId: number, minutes: number) => {
    router.post(
        `/kitchen/products/${productId}/out-of-stock`,
        { minutes },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const handleResumeProduct = (productId: number) => {
    router.post(
        `/kitchen/products/${productId}/resume`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const handlePauseCustom = (product: Product) => {
    const res = window.prompt(
        `Nhập số phút tạm dừng cho món "${product.name}":`,
        '120',
    );

    if (res === null) {
        return;
    }

    const mins = parseInt(res);

    if (isNaN(mins) || mins <= 0) {
        toast.error('Vui lòng nhập số phút hợp lệ!');

        return;
    }

    handlePauseProduct(product.id, mins);
};

const handleOutOfStockCustom = (product: Product) => {
    const res = window.prompt(
        `Nhập số phút báo hết cho món "${product.name}":`,
        '720',
    );

    if (res === null) {
        return;
    }

    const mins = parseInt(res);

    if (isNaN(mins) || mins <= 0) {
        toast.error('Vui lòng nhập số phút hợp lệ!');

        return;
    }

    handleOutOfStockProduct(product.id, mins);
};

// Group products by category
const groupedProducts = computed(() => {
    const groups: Record<string, Product[]> = {};
    props.products.forEach((p) => {
        const cat = p.category_name || 'Món khác';

        if (!groups[cat]) {
            groups[cat] = [];
        }

        groups[cat].push(p);
    });

    return groups;
});

// Filter grouped products by search query
const filteredGroupedProducts = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();

    if (!query) {
        return groupedProducts.value;
    }

    const groups: Record<string, Product[]> = {};

    for (const [catName, list] of Object.entries(groupedProducts.value)) {
        const filtered = list.filter((p) =>
            p.name.toLowerCase().includes(query),
        );

        if (filtered.length > 0) {
            groups[catName] = filtered;
        }
    }

    return groups;
});

// Phân nhóm các món đang chờ theo Bàn
const groupedPending = computed(() => {
    const groups: Record<string, PendingItem[]> = {};
    props.pendingItems.forEach((item) => {
        const key = item.table_name || 'Mang về';

        if (!groups[key]) {
            groups[key] = [];
        }

        groups[key].push(item);
    });

    return groups;
});

// Trạng thái load khi cập nhật
const isUpdating = ref<Record<number, boolean>>({});
const isManualRefreshing = ref(false);

// Web Audio API: Phát âm thanh chuông báo nhà hàng (Ding-dong chime)
const playNotificationSound = () => {
    try {
        const AudioContext =
            window.AudioContext || (window as any).webkitAudioContext;

        if (!AudioContext) {
            return;
        }

        const ctx = new AudioContext();

        // Tone 1: Âm cao ding
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(880, ctx.currentTime); // Nốt A5
        gain1.gain.setValueAtTime(0.08, ctx.currentTime);
        gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);

        // Tone 2: Âm trầm hơn dong (trễ nhẹ để tạo độ ngân)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(1320, ctx.currentTime + 0.08); // Nốt E6
        gain2.gain.setValueAtTime(0.04, ctx.currentTime + 0.08);
        gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);

        osc1.start();
        osc1.stop(ctx.currentTime + 0.65);
        osc2.start(ctx.currentTime + 0.08);
        osc2.stop(ctx.currentTime + 0.65);
    } catch (e) {
        console.error('Audio Context failed to play chime:', e);
    }
};

// Theo dõi danh sách món ăn chờ chế biến để phát chuông khi có món mới
watch(
    () => props.pendingItems,
    (newVal, oldVal) => {
        if (newVal && newVal.length > 0) {
            const oldIds = oldVal ? oldVal.map((i) => i.id) : [];
            const hasNewItem = newVal.some((item) => !oldIds.includes(item.id));

            if (hasNewItem) {
                playNotificationSound();
            }
        }
    },
    { deep: true },
);

// Reactively đếm thời gian trôi qua mỗi 10 giây (không cần reload trang)
const nowTime = ref(new Date());
let timerInterval: ReturnType<typeof setInterval> | null = null;
let secCountdownInterval: ReturnType<typeof setInterval> | null = null;

const getMinutesElapsed = (timeStr: string) => {
    if (!timeStr) {
        return 0;
    }

    const diffMs = nowTime.value.getTime() - new Date(timeStr).getTime();

    return Math.max(0, Math.floor(diffMs / 60000));
};

/**
 * Mức SLA của món theo thời gian chuẩn bị chuẩn (prep_minutes) riêng từng món:
 * ok = trong chuẩn, warn = vượt chuẩn (vàng), late = vượt 150% chuẩn (đỏ).
 */
const slaLevel = (item: PendingItem): 'ok' | 'warn' | 'late' => {
    const elapsed = getMinutesElapsed(item.sent_to_kitchen_at_raw);

    if (elapsed >= item.prep_minutes * 1.5) {
        return 'late';
    }

    if (elapsed >= item.prep_minutes) {
        return 'warn';
    }

    return 'ok';
};

// Check xem nhóm bàn có món nào trễ vượt SLA không
const hasOverdueItem = (items: PendingItem[]) => {
    return items.some((item) => slaLevel(item) === 'late');
};

// Số món đang vượt SLA trên toàn màn hình
const lateCount = computed(
    () => props.pendingItems.filter((i) => slaLevel(i) === 'late').length,
);

// Hoàn thành chế biến món ăn ở bếp
const handlePrepare = (itemId: number) => {
    if (isUpdating.value[itemId]) {
        return;
    }

    isUpdating.value[itemId] = true;
    router.post(
        `/kitchen/items/${itemId}/prepare`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isUpdating.value[itemId] = false;
            },
        },
    );
};

// Đã phục vụ / bê đi
const handleServe = (itemId: number) => {
    if (isUpdating.value[itemId]) {
        return;
    }

    isUpdating.value[itemId] = true;
    router.post(
        `/kitchen/items/${itemId}/serve`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isUpdating.value[itemId] = false;
            },
        },
    );
};

// Làm mới thủ công
const handleRefresh = () => {
    if (isManualRefreshing.value) {
        return;
    }

    isManualRefreshing.value = true;
    router.reload({
        only: ['pendingItems', 'completedItems', 'products', 'kitchenStats'],
        onFinish: () => {
            isManualRefreshing.value = false;
        },
    });
};

// Product countdown formatting helper
const getRemainingSeconds = (untilTimeStr: string | null) => {
    if (!untilTimeStr) {
        return 0;
    }

    const diffMs = new Date(untilTimeStr).getTime() - nowTime.value.getTime();

    return Math.max(0, Math.floor(diffMs / 1000));
};

const formatCountdown = (untilTimeStr: string | null) => {
    const totalSecs = getRemainingSeconds(untilTimeStr);

    if (totalSecs <= 0) {
        return '00:00';
    }

    const hours = Math.floor(totalSecs / 3600);
    const minutes = Math.floor((totalSecs % 3600) / 60);
    const seconds = totalSecs % 60;

    const pad = (n: number) => n.toString().padStart(2, '0');

    if (hours > 0) {
        return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
    }

    return `${pad(minutes)}:${pad(seconds)}`;
};

// Setup Listeners (Đồng bộ Realtime WebSockets qua Laravel Echo)
onMounted(() => {
    // 1. Đồng bộ thời gian hiển thị (10 giây)
    timerInterval = setInterval(() => {
        nowTime.value = new Date();
    }, 10000);

    // 2. Đồng bộ thời gian đếm ngược giây cho món pause/out-of-stock
    secCountdownInterval = setInterval(() => {
        nowTime.value = new Date();

        // Auto reload if any countdown reaches 0 to refresh statuses
        let shouldReload = false;
        props.products.forEach((p) => {
            const timeStr = p.paused_until || p.out_of_stock_until;

            if (timeStr) {
                const diff =
                    new Date(timeStr).getTime() - nowTime.value.getTime();

                if (diff <= 0 && (p.is_paused || p.is_out_of_stock)) {
                    shouldReload = true;
                }
            }
        });

        if (shouldReload) {
            router.reload({
                only: ['products'],
                preserveState: true,
                preserveScroll: true,
            });
        }
    }, 1000);

    // 3. Lắng nghe qua WebSockets (Laravel Echo) nhận sự kiện real-time tức thời
    const pageProps = usePage().props as any;
    const restaurantId = pageProps.auth?.user?.restaurant_id;

    if (Echo && restaurantId) {
        Echo.channel(`kitchen.${restaurantId}`).listen(
            '.kitchen.updated',
            (e: any) => {
                console.log('Realtime update received via WebSocket:', e);
                router.reload({
                    only: ['pendingItems', 'completedItems', 'kitchenStats'],
                    preserveState: true,
                    preserveScroll: true,
                });
            },
        );

        // Lắng nghe thay đổi kho/thực đơn để hot-reload
        Echo.channel(`restaurant.${restaurantId}`).listen(
            '.product.stock_updated',
            (e: any) => {
                console.log('Product menu update received:', e);
                router.reload({
                    only: ['products'],
                    preserveState: true,
                    preserveScroll: true,
                });
            },
        );
    }
});

onUnmounted(() => {
    if (timerInterval) {
        clearInterval(timerInterval);
    }

    if (secCountdownInterval) {
        clearInterval(secCountdownInterval);
    }

    // Ngắt kênh Echo
    const pageProps = usePage().props as any;
    const restaurantId = pageProps.auth?.user?.restaurant_id;

    if (Echo && restaurantId) {
        Echo.leave(`kitchen.${restaurantId}`);
        Echo.leave(`restaurant.${restaurantId}`);
    }
});
</script>

<template>
    <Head title="Màn hình Bếp - Aventura" />

    <div
        class="flex min-h-screen flex-col gap-6 bg-slate-50/50 p-6 dark:bg-slate-900/30"
    >
        <!-- ── HEADER ── -->
        <div
            class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-5 md:flex-row md:items-center dark:border-slate-800/80"
        >
            <div class="flex items-center gap-3.5">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-500 dark:bg-indigo-500/20"
                >
                    <ChefHat class="size-6 animate-pulse" />
                </div>
                <div>
                    <h1
                        class="text-2xl font-black tracking-tight text-slate-900 dark:text-white"
                    >
                        Màn hình Điều phối Bếp
                    </h1>
                    <p
                        class="mt-0.5 flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <span
                            class="inline-block h-2 w-2 animate-ping rounded-full bg-emerald-500"
                        ></span>
                        Đồng bộ WebSockets Realtime & Cảnh báo âm thanh chủ động
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-10 gap-1.5 rounded-xl bg-background px-4 text-xs font-bold shadow-sm transition-all"
                    :disabled="isManualRefreshing"
                    @click="handleRefresh"
                >
                    <RefreshCw
                        class="size-3.5"
                        :class="{ 'animate-spin': isManualRefreshing }"
                    />
                    Làm mới (F5)
                </Button>
            </div>
        </div>

        <!-- ── THỐNG KÊ TỐC ĐỘ BẾP HÔM NAY ── -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div
                class="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-800/60 dark:bg-slate-900"
            >
                <p
                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                >
                    Đang chờ làm
                </p>
                <p
                    class="mt-1 text-2xl font-black text-indigo-600 tabular-nums dark:text-indigo-400"
                >
                    {{ props.pendingItems.length }}
                </p>
            </div>
            <div
                class="rounded-2xl border p-3.5 transition-colors"
                :class="
                    lateCount > 0
                        ? 'border-red-300 bg-red-50/60 dark:border-red-900/50 dark:bg-red-950/20'
                        : 'border-slate-200 bg-white dark:border-slate-800/60 dark:bg-slate-900'
                "
            >
                <p
                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                >
                    Vượt SLA
                </p>
                <p
                    class="mt-1 text-2xl font-black tabular-nums"
                    :class="
                        lateCount > 0
                            ? 'text-red-600 dark:text-red-400'
                            : 'text-slate-800 dark:text-slate-100'
                    "
                >
                    {{ lateCount }}
                    <AlertTriangle
                        v-if="lateCount > 0"
                        class="-mt-1 inline size-4 animate-pulse"
                    />
                </p>
            </div>
            <div
                class="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-800/60 dark:bg-slate-900"
            >
                <p
                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                >
                    Đã ra món hôm nay
                </p>
                <p
                    class="mt-1 text-2xl font-black text-emerald-600 tabular-nums dark:text-emerald-400"
                >
                    {{ props.kitchenStats.done_today }}
                </p>
            </div>
            <div
                class="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-slate-800/60 dark:bg-slate-900"
            >
                <p
                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                >
                    TB chế biến
                </p>
                <p
                    class="mt-1 text-2xl font-black text-slate-800 tabular-nums dark:text-slate-100"
                >
                    {{
                        props.kitchenStats.avg_prep_minutes !== null
                            ? `${props.kitchenStats.avg_prep_minutes}p`
                            : '—'
                    }}
                </p>
                <p
                    v-if="props.kitchenStats.slowest_prep_minutes !== null"
                    class="mt-0.5 text-[10px] text-muted-foreground"
                >
                    Chậm nhất: {{ props.kitchenStats.slowest_prep_minutes }}p
                </p>
            </div>
        </div>

        <!-- ── TAB CONTROL ── -->
        <div class="flex gap-1 border-b border-slate-200 dark:border-slate-800">
            <button
                class="flex items-center gap-2 border-b-2 px-5 py-3 text-sm font-bold transition-all"
                :class="
                    activeTab === 'orders'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                "
                @click="activeTab = 'orders'"
            >
                <UtensilsCrossed class="size-4" />
                <span>Điều Phối Món Ăn</span>
                <Badge
                    variant="secondary"
                    class="rounded-full bg-indigo-100 text-[10px] font-extrabold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400"
                >
                    {{ props.pendingItems.length }}
                </Badge>
            </button>
            <button
                class="flex items-center gap-2 border-b-2 px-5 py-3 text-sm font-bold transition-all"
                :class="
                    activeTab === 'menu'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                "
                @click="activeTab = 'menu'"
            >
                <ChefHat class="size-4" />
                <span>Quản Lý Thực Đơn</span>
            </button>
        </div>

        <!-- ── TAB 1: ĐIỀU PHỐI MÓN ĂN (ORDERS) ── -->
        <div
            v-if="activeTab === 'orders'"
            class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2"
        >
            <!-- ── CỘT TRÁI: NHẬN ĐƠN (PENDING) ── -->
            <div class="space-y-4">
                <div
                    class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800/60 dark:bg-slate-900"
                >
                    <div class="flex items-center gap-2">
                        <UtensilsCrossed class="size-5 text-indigo-500" />
                        <h2
                            class="text-base font-bold text-slate-800 dark:text-slate-100"
                        >
                            1. Đơn Chờ Chế Biến
                        </h2>
                    </div>
                    <Badge
                        variant="secondary"
                        class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-extrabold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400"
                    >
                        {{ props.pendingItems.length }} món chờ làm
                    </Badge>
                </div>

                <div
                    v-if="props.pendingItems.length === 0"
                    class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-slate-200 bg-white/40 py-24 text-center dark:border-slate-800/80 dark:bg-slate-900/10"
                >
                    <Inbox class="mb-3 size-10 text-muted-foreground/30" />
                    <p
                        class="text-sm font-bold text-slate-700 dark:text-slate-300"
                    >
                        Tuyệt vời! Bếp đã dọn sạch đơn hàng
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Các món ăn mới sẽ hiển thị tại đây khi có khách đặt
                    </p>
                </div>

                <div v-else class="space-y-4">
                    <Card
                        v-for="(items, tableName) in groupedPending"
                        :key="tableName"
                        class="overflow-hidden rounded-2xl border border-slate-200/80 bg-card shadow-sm transition-all hover:shadow-md dark:border-slate-800/60"
                        :class="{
                            'animate-pulse border-red-500/60 bg-red-50/5 shadow-lg shadow-red-500/5 dark:border-red-950/50 dark:bg-red-950/5':
                                hasOverdueItem(items),
                        }"
                    >
                        <CardHeader
                            class="border-b border-slate-200/80 px-4 py-3.5 dark:border-slate-800/60"
                            :class="
                                hasOverdueItem(items)
                                    ? 'bg-red-50/50 dark:bg-red-950/20'
                                    : 'bg-slate-100/50 dark:bg-slate-800/20'
                            "
                        >
                            <div class="flex items-center justify-between">
                                <CardTitle
                                    class="flex items-center gap-2 text-sm font-extrabold text-slate-900 dark:text-white"
                                >
                                    <span
                                        class="inline-block h-2.5 w-2.5 rounded-full"
                                        :class="
                                            hasOverdueItem(items)
                                                ? 'animate-ping bg-red-500'
                                                : 'bg-indigo-500'
                                        "
                                    >
                                    </span>
                                    Bàn: {{ tableName }}
                                </CardTitle>

                                <div class="flex items-center gap-2">
                                    <Badge
                                        v-if="hasOverdueItem(items)"
                                        variant="destructive"
                                        class="animate-bounce rounded px-2 py-0.5 text-[9px] font-black uppercase"
                                    >
                                        🚨 Có món trễ!
                                    </Badge>
                                    <Badge
                                        class="bg-slate-200/80 text-[10px] font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        {{ items.length }} món
                                    </Badge>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent
                            class="divide-y divide-slate-100 p-0 dark:divide-slate-800/60"
                        >
                            <div
                                v-for="item in items"
                                :key="item.id"
                                class="flex items-center justify-between gap-4 p-4 transition-colors hover:bg-slate-50/40 dark:hover:bg-slate-900/20"
                                :class="{
                                    'border-l-4 border-l-red-500 bg-red-500/5 dark:bg-red-950/10':
                                        slaLevel(item) === 'late',
                                    'border-l-4 border-l-amber-400 bg-amber-400/5 dark:bg-amber-950/10':
                                        slaLevel(item) === 'warn',
                                }"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2.5">
                                        <Badge
                                            class="rounded-lg px-2.5 py-0.5 text-xs font-black text-white"
                                            :class="
                                                slaLevel(item) === 'late'
                                                    ? 'bg-red-500'
                                                    : slaLevel(item) === 'warn'
                                                      ? 'bg-amber-500'
                                                      : 'bg-indigo-500'
                                            "
                                        >
                                            x{{ Math.round(item.quantity) }}
                                        </Badge>
                                        <h3
                                            class="truncate text-sm font-bold text-slate-900 dark:text-slate-100"
                                        >
                                            {{ item.product_name }}
                                        </h3>
                                    </div>

                                    <!-- Meta thông tin thêm (thời gian vào, tên tài khoản vào) -->
                                    <div
                                        class="mt-2 flex flex-wrap items-center gap-3 text-[10px] font-semibold text-muted-foreground"
                                    >
                                        <span class="flex items-center gap-1">
                                            <Clock
                                                class="size-3 text-indigo-500"
                                            />
                                            Nhận: {{ item.sent_to_kitchen_at }}
                                        </span>
                                        <span>•</span>
                                        <span class="flex items-center gap-1">
                                            <User
                                                class="size-3 text-violet-500"
                                            />
                                            Gọi: {{ item.creator_name }}
                                        </span>

                                        <!-- Cảnh báo SLA theo thời gian chuẩn riêng của món -->
                                        <span
                                            v-if="slaLevel(item) === 'late'"
                                            class="flex items-center gap-0.5 rounded bg-red-500/10 px-1.5 py-0.5 font-extrabold text-red-500"
                                        >
                                            <AlertTriangle
                                                class="size-3 shrink-0 text-red-500"
                                            />
                                            Chờ
                                            {{
                                                getMinutesElapsed(
                                                    item.sent_to_kitchen_at_raw,
                                                )
                                            }}p / chuẩn
                                            {{ item.prep_minutes }}p!
                                        </span>
                                        <span
                                            v-else-if="
                                                slaLevel(item) === 'warn'
                                            "
                                            class="flex items-center gap-0.5 rounded bg-amber-400/10 px-1.5 py-0.5 font-extrabold text-amber-600 dark:text-amber-400"
                                        >
                                            <Clock class="size-3 shrink-0" />
                                            Chờ
                                            {{
                                                getMinutesElapsed(
                                                    item.sent_to_kitchen_at_raw,
                                                )
                                            }}p / chuẩn {{ item.prep_minutes }}p
                                        </span>
                                        <span
                                            v-else
                                            class="font-medium text-slate-500"
                                        >
                                            Chờ
                                            {{
                                                getMinutesElapsed(
                                                    item.sent_to_kitchen_at_raw,
                                                )
                                            }}p / {{ item.prep_minutes }}p
                                        </span>
                                    </div>

                                    <!-- Ghi chú món ăn nếu có -->
                                    <div
                                        v-if="item.notes"
                                        class="mt-2.5 inline-flex items-start gap-1.5 rounded-lg border border-amber-200 bg-amber-50/40 px-2 py-1 text-[10px] font-medium text-amber-700 dark:border-amber-950/30 dark:bg-amber-950/10 dark:text-amber-400"
                                    >
                                        <MessageSquare
                                            class="mt-0.5 size-3 shrink-0 text-amber-500"
                                        />
                                        <span>Ghi chú: {{ item.notes }}</span>
                                    </div>
                                </div>

                                <!-- Nút hoàn thành chuẩn bị -->
                                <Button
                                    class="h-10 w-10 shrink-0 rounded-xl text-white shadow-sm transition-all"
                                    :class="
                                        slaLevel(item) === 'late'
                                            ? 'animate-bounce bg-red-600 hover:bg-red-700'
                                            : slaLevel(item) === 'warn'
                                              ? 'bg-amber-500 hover:bg-amber-600'
                                              : 'bg-indigo-600 hover:bg-indigo-700'
                                    "
                                    :disabled="isUpdating[item.id]"
                                    @click="handlePrepare(item.id)"
                                    title="Hoàn thành món"
                                >
                                    <Check class="size-5" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- ── CỘT PHẢI: ĐƠN ĐÃ XONG CHỜ PHỤC VỤ (COMPLETED) ── -->
            <div class="space-y-4">
                <div
                    class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800/60 dark:bg-slate-900"
                >
                    <div class="flex items-center gap-2">
                        <CheckCircle class="size-5 text-emerald-500" />
                        <h2
                            class="text-base font-bold text-slate-800 dark:text-slate-100"
                        >
                            2. Chờ Phục Vụ / Lấy Đi
                        </h2>
                    </div>
                    <Badge
                        variant="secondary"
                        class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-extrabold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"
                    >
                        {{ props.completedItems.length }} món sẵn sàng
                    </Badge>
                </div>

                <div
                    v-if="props.completedItems.length === 0"
                    class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-slate-200 bg-white/40 py-24 text-center dark:border-slate-800/80 dark:bg-slate-900/10"
                >
                    <Bell class="mb-3 size-10 text-muted-foreground/30" />
                    <p
                        class="text-sm font-bold text-slate-700 dark:text-slate-300"
                    >
                        Không có món chờ bưng
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Các món ăn chế biến xong sẽ chuyển sang bên này để phục
                        vụ đi giao
                    </p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="item in props.completedItems"
                        :key="item.id"
                        class="group flex animate-in items-center justify-between gap-4 rounded-2xl border border-emerald-100 bg-white/80 p-4 shadow-sm transition-all duration-200 slide-in-from-right-3 hover:border-emerald-200 hover:shadow-md dark:border-emerald-950/20 dark:bg-slate-950/20 dark:hover:border-emerald-900/30"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <Badge
                                    class="rounded bg-emerald-500 px-2 py-0.5 text-xs font-black text-white"
                                >
                                    x{{ Math.round(item.quantity) }}
                                </Badge>
                                <h3
                                    class="truncate text-sm font-extrabold text-slate-900 dark:text-slate-100"
                                >
                                    {{ item.product_name }}
                                </h3>
                            </div>

                            <div
                                class="mt-2 flex items-center gap-3 text-[10px] font-bold text-muted-foreground"
                            >
                                <span
                                    class="flex items-center gap-0.5 rounded bg-indigo-50 px-1.5 py-0.5 text-indigo-600 dark:bg-indigo-950/30 dark:text-indigo-400"
                                >
                                    Bàn: {{ item.table_name }}
                                </span>
                                <span>•</span>
                                <span
                                    class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400"
                                >
                                    <Clock class="size-3" />
                                    Xong lúc: {{ item.prepared_at }}
                                </span>
                            </div>

                            <!-- Hiển thị lại ghi chú nếu có để phục vụ chú ý -->
                            <div
                                v-if="item.notes"
                                class="mt-2 inline-flex items-center gap-1 rounded bg-amber-50 px-1.5 py-0.5 text-[9px] font-semibold text-amber-700 dark:bg-amber-950/10 dark:text-amber-400"
                            >
                                <MessageSquare
                                    class="size-2.5 text-amber-500"
                                />
                                {{ item.notes }}
                            </div>
                        </div>

                        <!-- Nút hoàn thành phục vụ -->
                        <Button
                            class="h-10 w-10 shrink-0 rounded-xl bg-emerald-500 text-white shadow-sm transition-all hover:bg-emerald-600"
                            :disabled="isUpdating[item.id]"
                            @click="handleServe(item.id)"
                            title="Xác nhận phục vụ đã lấy đi"
                        >
                            <Check class="size-5" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── TAB 2: QUẢN LÝ THỰC ĐƠN (MENU MANAGEMENT) ── -->
        <div v-else-if="activeTab === 'menu'" class="space-y-6">
            <!-- Search & Actions -->
            <div
                class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800/60 dark:bg-slate-900"
            >
                <div class="relative flex-1">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Tìm kiếm món ăn trong thực đơn..."
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pr-4 pl-9 text-sm text-slate-900 transition-all focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                    />
                </div>
            </div>

            <!-- Group lists -->
            <div
                v-if="Object.keys(filteredGroupedProducts).length === 0"
                class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-slate-200 bg-white/40 py-24 text-center dark:border-slate-800/80 dark:bg-slate-900/10"
            >
                <Inbox class="mb-3 size-10 text-muted-foreground/30" />
                <p class="text-sm font-bold text-slate-700 dark:text-slate-300">
                    Không tìm thấy món ăn nào
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Vui lòng thử từ khóa tìm kiếm khác
                </p>
            </div>

            <div v-else class="animate-in space-y-8 duration-250 fade-in">
                <div
                    v-for="(
                        productList, categoryName
                    ) in filteredGroupedProducts"
                    :key="categoryName"
                    class="space-y-4"
                >
                    <div
                        class="flex items-center gap-2 border-b border-slate-200 pb-2 dark:border-slate-800/50"
                    >
                        <h3
                            class="text-xs font-black tracking-wider text-slate-500 uppercase dark:text-slate-400"
                        >
                            {{ categoryName }}
                        </h3>
                        <Badge
                            variant="secondary"
                            class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-extrabold text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                        >
                            {{ productList.length }} món
                        </Badge>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4"
                    >
                        <Card
                            v-for="p in productList"
                            :key="p.id"
                            class="flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200/80 bg-card shadow-sm transition-all dark:border-slate-800/60"
                            :class="{
                                'border-amber-400/50 bg-amber-50/5 dark:border-amber-950/50':
                                    p.is_paused,
                                'border-orange-500/50 bg-orange-50/5 dark:border-orange-950/50':
                                    p.is_out_of_stock,
                            }"
                        >
                            <CardContent
                                class="flex h-full flex-col justify-between gap-3 p-4"
                            >
                                <div>
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <h4
                                            class="line-clamp-2 text-sm font-bold text-slate-900 dark:text-slate-100"
                                        >
                                            {{ p.name }}
                                        </h4>
                                        <Badge
                                            class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-black tracking-wider uppercase"
                                            :class="{
                                                'bg-emerald-500 text-white':
                                                    !p.is_paused &&
                                                    !p.is_out_of_stock,
                                                'animate-pulse bg-amber-500 text-white':
                                                    p.is_paused,
                                                'animate-pulse bg-orange-500 text-white':
                                                    p.is_out_of_stock,
                                            }"
                                        >
                                            {{
                                                !p.is_paused &&
                                                !p.is_out_of_stock
                                                    ? 'Đang Bán'
                                                    : p.is_paused
                                                      ? 'Tạm Dừng'
                                                      : 'Hết Món'
                                            }}
                                        </Badge>
                                    </div>
                                    <div
                                        class="mt-1 text-xs font-extrabold text-indigo-600 dark:text-indigo-400"
                                    >
                                        {{
                                            new Intl.NumberFormat(
                                                'vi-VN',
                                            ).format(p.price)
                                        }}đ
                                    </div>
                                </div>

                                <!-- Actions & Countdown -->
                                <div
                                    class="mt-2 border-t border-slate-100 pt-3 dark:border-slate-800/80"
                                >
                                    <div
                                        v-if="p.is_paused || p.is_out_of_stock"
                                        class="flex flex-col gap-2 rounded-xl border border-slate-100 bg-slate-50 p-2.5 dark:border-slate-800/60 dark:bg-slate-900"
                                    >
                                        <div
                                            class="flex items-center justify-between text-[11px] font-bold text-slate-500"
                                        >
                                            <span>Mở bán lại sau:</span>
                                            <span
                                                class="flex animate-pulse items-center gap-1 font-black text-indigo-600 dark:text-indigo-400"
                                            >
                                                <Clock class="size-3" />
                                                {{
                                                    formatCountdown(
                                                        p.paused_until ||
                                                            p.out_of_stock_until,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                        <Button
                                            size="sm"
                                            class="flex h-8 w-full items-center justify-center gap-1 rounded-lg bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                                            @click="handleResumeProduct(p.id)"
                                        >
                                            <RotateCcw class="size-3" />
                                            Mở bán lại ngay
                                        </Button>
                                    </div>

                                    <div v-else class="flex flex-col gap-2.5">
                                        <div class="flex flex-col gap-1">
                                            <span
                                                class="text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                                                >Tạm dừng món ăn</span
                                            >
                                            <div class="grid grid-cols-4 gap-1">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    class="h-7 rounded-md p-0 text-[10px] font-bold"
                                                    @click="
                                                        handlePauseProduct(
                                                            p.id,
                                                            15,
                                                        )
                                                    "
                                                    >15p</Button
                                                >
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    class="h-7 rounded-md p-0 text-[10px] font-bold"
                                                    @click="
                                                        handlePauseProduct(
                                                            p.id,
                                                            30,
                                                        )
                                                    "
                                                    >30p</Button
                                                >
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    class="h-7 rounded-md p-0 text-[10px] font-bold"
                                                    @click="
                                                        handlePauseProduct(
                                                            p.id,
                                                            60,
                                                        )
                                                    "
                                                    >1h</Button
                                                >
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    class="h-7 rounded-md bg-slate-100 p-0 text-[10px] font-black dark:bg-slate-800"
                                                    @click="
                                                        handlePauseCustom(p)
                                                    "
                                                    >...</Button
                                                >
                                            </div>
                                        </div>

                                        <div class="flex flex-col gap-1">
                                            <span
                                                class="text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                                                >Báo Hết nguyên liệu</span
                                            >
                                            <div class="grid grid-cols-4 gap-1">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    class="h-7 rounded-md border-orange-200 p-0 text-[10px] font-bold text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-950/20"
                                                    @click="
                                                        handleOutOfStockProduct(
                                                            p.id,
                                                            120,
                                                        )
                                                    "
                                                    >2h</Button
                                                >
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    class="h-7 rounded-md border-orange-200 p-0 text-[10px] font-bold text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-950/20"
                                                    @click="
                                                        handleOutOfStockProduct(
                                                            p.id,
                                                            240,
                                                        )
                                                    "
                                                    >4h</Button
                                                >
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    class="h-7 rounded-md border-orange-200 p-0 text-[10px] font-bold text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-950/20"
                                                    @click="
                                                        handleOutOfStockProduct(
                                                            p.id,
                                                            480,
                                                        )
                                                    "
                                                    >8h</Button
                                                >
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    class="h-7 rounded-md border-orange-200 bg-orange-50 p-0 text-[10px] font-black text-orange-600 hover:bg-orange-50 dark:bg-orange-950/10 dark:hover:bg-orange-950/20"
                                                    @click="
                                                        handleOutOfStockCustom(
                                                            p,
                                                        )
                                                    "
                                                    >...</Button
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bg-card {
    transition:
        transform 0.2s cubic-bezier(0.16, 1, 0.3, 1),
        border-color 0.2s ease;
}
.bg-card:hover {
    transform: translateY(-2px);
}
</style>
