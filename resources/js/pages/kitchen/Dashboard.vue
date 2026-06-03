<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
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
    AlertTriangle
} from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
}

interface CompletedItem {
    id: number;
    product_name: string;
    quantity: number;
    notes: string | null;
    prepared_at: string;
    table_name: string;
}

const props = defineProps<{
    pendingItems: PendingItem[];
    completedItems: CompletedItem[];
}>();

// Phân nhóm các món đang chờ theo Bàn
const groupedPending = computed(() => {
    const groups: Record<string, PendingItem[]> = {};
    props.pendingItems.forEach(item => {
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
        const AudioContext = window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioContext) return;
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
        console.error("Audio Context failed to play chime:", e);
    }
};

// Theo dõi danh sách món ăn chờ chế biến để phát chuông khi có món mới
watch(() => props.pendingItems, (newVal, oldVal) => {
    if (newVal && newVal.length > 0) {
        const oldIds = oldVal ? oldVal.map(i => i.id) : [];
        const hasNewItem = newVal.some(item => !oldIds.includes(item.id));
        if (hasNewItem) {
            playNotificationSound();
        }
    }
}, { deep: true });

// Reactively đếm thời gian trôi qua mỗi 10 giây (không cần reload trang)
const nowTime = ref(new Date());
let timerInterval: ReturnType<typeof setInterval> | null = null;

const getMinutesElapsed = (timeStr: string) => {
    if (!timeStr) return 0;
    const diffMs = nowTime.value.getTime() - new Date(timeStr).getTime();
    return Math.max(0, Math.floor(diffMs / 60000));
};

// Check xem nhóm bàn có món nào trễ quá 10 phút không
const hasOverdueItem = (items: PendingItem[]) => {
    return items.some(item => getMinutesElapsed(item.sent_to_kitchen_at_raw) >= 10);
};

// Hoàn thành chế biến món ăn ở bếp
const handlePrepare = (itemId: number) => {
    if (isUpdating.value[itemId]) return;
    isUpdating.value[itemId] = true;
    router.post(`/kitchen/items/${itemId}/prepare`, {}, {
        preserveScroll: true,
        onFinish: () => {
            isUpdating.value[itemId] = false;
        }
    });
};

// Đã phục vụ / bê đi
const handleServe = (itemId: number) => {
    if (isUpdating.value[itemId]) return;
    isUpdating.value[itemId] = true;
    router.post(`/kitchen/items/${itemId}/serve`, {}, {
        preserveScroll: true,
        onFinish: () => {
            isUpdating.value[itemId] = false;
        }
    });
};

// Làm mới thủ công
const handleRefresh = () => {
    if (isManualRefreshing.value) return;
    isManualRefreshing.value = true;
    router.reload({
        only: ['pendingItems', 'completedItems'],
        onFinish: () => {
            isManualRefreshing.value = false;
        }
    });
};

// Setup Listeners (Tải tự động dự phòng 5s + Đồng bộ Realtime WebSockets)
let fallbackInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    // 1. Đồng bộ thời gian hiển thị
    timerInterval = setInterval(() => {
        nowTime.value = new Date();
    }, 10000);

    // 2. Dự phòng tự động cập nhật sau mỗi 5s (Short-polling fallback)
    fallbackInterval = setInterval(() => {
        router.reload({
            only: ['pendingItems', 'completedItems'],
            preserveState: true,
            preserveScroll: true
        });
    }, 5000);

    // 3. Lắng nghe qua WebSockets (Laravel Echo) nhận sự kiện real-time tức thời
    const pageProps = usePage().props as any;
    const restaurantId = pageProps.auth?.user?.restaurant_id;
    if (Echo && restaurantId) {
        Echo.channel(`kitchen.${restaurantId}`)
            .listen('.kitchen.updated', (e: any) => {
                console.log('Realtime update received via WebSocket:', e);
                router.reload({
                    only: ['pendingItems', 'completedItems'],
                    preserveState: true,
                    preserveScroll: true
                });
            });
    }
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
    if (fallbackInterval) clearInterval(fallbackInterval);
    
    // Ngắt kênh Echo
    const pageProps = usePage().props as any;
    const restaurantId = pageProps.auth?.user?.restaurant_id;
    if (Echo && restaurantId) {
        Echo.leave(`kitchen.${restaurantId}`);
    }
});
</script>

<template>
    <Head title="Màn hình Bếp - Aventura" />

    <div class="flex flex-col gap-6 p-6 min-h-screen bg-slate-50/50 dark:bg-slate-900/30">
        <!-- ── HEADER ── -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800/80 pb-5">
            <div class="flex items-center gap-3.5">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-500 dark:bg-indigo-500/20">
                    <ChefHat class="size-6 animate-pulse" />
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Màn hình Điều phối Bếp</h1>
                    <p class="text-xs text-muted-foreground mt-0.5 font-medium flex items-center gap-1.5">
                        <span class="inline-block h-2 w-2 rounded-full bg-emerald-500 animate-ping"></span>
                        Đồng bộ WebSockets Realtime & Cảnh báo âm thanh chủ động
                    </p>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <Button 
                    variant="outline" 
                    size="sm" 
                    class="rounded-xl font-bold text-xs gap-1.5 h-10 px-4 bg-background shadow-sm transition-all"
                    :disabled="isManualRefreshing"
                    @click="handleRefresh"
                >
                    <RefreshCw class="size-3.5" :class="{ 'animate-spin': isManualRefreshing }" />
                    Làm mới (F5)
                </Button>
            </div>
        </div>

        <!-- ── DANH SÁCH 2 CỘT CHÍNH ── -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
            
            <!-- ── CỘT TRÁI: NHẬN ĐƠN (PENDING) ── -->
            <div class="space-y-4">
                <div class="flex items-center justify-between bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/60 p-4 rounded-2xl shadow-sm">
                    <div class="flex items-center gap-2">
                        <UtensilsCrossed class="size-5 text-indigo-500" />
                        <h2 class="text-base font-bold text-slate-800 dark:text-slate-100">1. Đơn Chờ Chế Biến</h2>
                    </div>
                    <Badge variant="secondary" class="bg-indigo-100 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400 font-extrabold px-3 py-1 text-xs rounded-full">
                        {{ props.pendingItems.length }} món chờ làm
                    </Badge>
                </div>

                <div v-if="props.pendingItems.length === 0" class="flex flex-col items-center justify-center py-24 rounded-3xl border border-dashed border-slate-200 dark:border-slate-800/80 bg-white/40 dark:bg-slate-900/10 text-center">
                    <Inbox class="size-10 text-muted-foreground/30 mb-3" />
                    <p class="text-sm font-bold text-slate-700 dark:text-slate-300">Tuyệt vời! Bếp đã dọn sạch đơn hàng</p>
                    <p class="text-xs text-muted-foreground mt-1">Các món ăn mới sẽ hiển thị tại đây khi có khách đặt</p>
                </div>

                <div v-else class="space-y-4">
                    <Card 
                        v-for="(items, tableName) in groupedPending" 
                        :key="tableName" 
                        class="overflow-hidden border border-slate-200/80 dark:border-slate-800/60 shadow-sm bg-card hover:shadow-md transition-all rounded-2xl"
                        :class="{ 
                            'border-red-500/60 shadow-lg shadow-red-500/5 dark:border-red-950/50 bg-red-50/5 dark:bg-red-950/5 animate-pulse': hasOverdueItem(items) 
                        }"
                    >
                        <CardHeader class="py-3.5 px-4 border-b border-slate-200/80 dark:border-slate-800/60"
                            :class="hasOverdueItem(items) 
                                ? 'bg-red-50/50 dark:bg-red-950/20' 
                                : 'bg-slate-100/50 dark:bg-slate-800/20'"
                        >
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                                    <span class="inline-block w-2.5 h-2.5 rounded-full" 
                                          :class="hasOverdueItem(items) ? 'bg-red-500 animate-ping' : 'bg-indigo-500'">
                                    </span>
                                    Bàn: {{ tableName }}
                                </CardTitle>
                                
                                <div class="flex items-center gap-2">
                                    <Badge v-if="hasOverdueItem(items)" variant="destructive" class="text-[9px] font-black uppercase px-2 py-0.5 rounded animate-bounce">
                                        🚨 Có món trễ!
                                    </Badge>
                                    <Badge class="bg-slate-200/80 text-slate-700 dark:bg-slate-800 dark:text-slate-300 text-[10px] font-bold">
                                        {{ items.length }} món
                                    </Badge>
                                </div>
                            </div>
                        </CardHeader>
                        
                        <CardContent class="p-0 divide-y divide-slate-100 dark:divide-slate-800/60">
                            <div 
                                v-for="item in items" 
                                :key="item.id" 
                                class="p-4 flex items-center justify-between gap-4 hover:bg-slate-50/40 dark:hover:bg-slate-900/20 transition-colors"
                                :class="{ 
                                    'border-l-4 border-l-red-500 bg-red-500/5 dark:bg-red-950/10': getMinutesElapsed(item.sent_to_kitchen_at_raw) >= 10 
                                }"
                            >
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2.5">
                                        <Badge class="font-black text-xs px-2.5 py-0.5 rounded-lg"
                                               :class="getMinutesElapsed(item.sent_to_kitchen_at_raw) >= 10 
                                                    ? 'bg-red-500 text-white' 
                                                    : 'bg-indigo-500 text-white'"
                                        >
                                            x{{ Math.round(item.quantity) }}
                                        </Badge>
                                        <h3 class="font-bold text-slate-900 dark:text-slate-100 text-sm truncate">
                                            {{ item.product_name }}
                                        </h3>
                                    </div>
                                    
                                    <!-- Meta thông tin thêm (thời gian vào, tên tài khoản vào) -->
                                    <div class="mt-2 flex flex-wrap items-center gap-3 text-[10px] text-muted-foreground font-semibold">
                                        <span class="flex items-center gap-1">
                                            <Clock class="size-3 text-indigo-500" />
                                            Nhận: {{ item.sent_to_kitchen_at }}
                                        </span>
                                        <span>•</span>
                                        <span class="flex items-center gap-1">
                                            <User class="size-3 text-violet-500" />
                                            Gọi: {{ item.creator_name }}
                                        </span>
                                        
                                        <!-- Cảnh báo thời gian chờ chế biến -->
                                        <span v-if="getMinutesElapsed(item.sent_to_kitchen_at_raw) >= 10" 
                                              class="text-red-500 font-extrabold flex items-center gap-0.5 bg-red-500/10 px-1.5 py-0.5 rounded"
                                        >
                                            <AlertTriangle class="size-3 text-red-500 shrink-0" />
                                            Chờ {{ getMinutesElapsed(item.sent_to_kitchen_at_raw) }} phút!
                                        </span>
                                        <span v-else class="text-slate-500 font-medium">
                                            Chờ {{ getMinutesElapsed(item.sent_to_kitchen_at_raw) }}p
                                        </span>
                                    </div>

                                    <!-- Ghi chú món ăn nếu có -->
                                    <div v-if="item.notes" class="mt-2.5 inline-flex items-start gap-1.5 rounded-lg border border-amber-200 bg-amber-50/40 px-2 py-1 text-[10px] text-amber-700 dark:border-amber-950/30 dark:bg-amber-950/10 dark:text-amber-400 font-medium">
                                        <MessageSquare class="size-3 text-amber-500 shrink-0 mt-0.5" />
                                        <span>Ghi chú: {{ item.notes }}</span>
                                    </div>
                                </div>

                                <!-- Nút hoàn thành chuẩn bị -->
                                <Button 
                                    class="h-10 w-10 shrink-0 rounded-xl text-white shadow-sm transition-all"
                                    :class="getMinutesElapsed(item.sent_to_kitchen_at_raw) >= 10 
                                        ? 'bg-red-600 hover:bg-red-700 animate-bounce' 
                                        : 'bg-indigo-600 hover:bg-indigo-700'"
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
                <div class="flex items-center justify-between bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/60 p-4 rounded-2xl shadow-sm">
                    <div class="flex items-center gap-2">
                        <CheckCircle class="size-5 text-emerald-500" />
                        <h2 class="text-base font-bold text-slate-800 dark:text-slate-100">2. Chờ Phục Vụ / Lấy Đi</h2>
                    </div>
                    <Badge variant="secondary" class="bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 font-extrabold px-3 py-1 text-xs rounded-full">
                        {{ props.completedItems.length }} món sẵn sàng
                    </Badge>
                </div>

                <div v-if="props.completedItems.length === 0" class="flex flex-col items-center justify-center py-24 rounded-3xl border border-dashed border-slate-200 dark:border-slate-800/80 bg-white/40 dark:bg-slate-900/10 text-center">
                    <Bell class="size-10 text-muted-foreground/30 mb-3" />
                    <p class="text-sm font-bold text-slate-700 dark:text-slate-300">Không có món chờ bưng</p>
                    <p class="text-xs text-muted-foreground mt-1">Các món ăn chế biến xong sẽ chuyển sang bên này để phục vụ đi giao</p>
                </div>

                <div v-else class="space-y-3">
                    <div 
                        v-for="item in props.completedItems" 
                        :key="item.id" 
                        class="flex items-center justify-between gap-4 p-4 rounded-2xl border border-emerald-100 bg-white/80 dark:border-emerald-950/20 dark:bg-slate-950/20 shadow-sm hover:shadow-md hover:border-emerald-200 dark:hover:border-emerald-900/30 transition-all group animate-in slide-in-from-right-3 duration-200"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <Badge class="bg-emerald-500 text-white font-black text-xs px-2 py-0.5 rounded">
                                    x{{ Math.round(item.quantity) }}
                                </Badge>
                                <h3 class="font-extrabold text-slate-900 dark:text-slate-100 text-sm truncate">
                                    {{ item.product_name }}
                                </h3>
                            </div>
                            
                            <div class="mt-2 flex items-center gap-3 text-[10px] text-muted-foreground font-bold">
                                <span class="text-indigo-600 dark:text-indigo-400 flex items-center gap-0.5 bg-indigo-50 dark:bg-indigo-950/30 px-1.5 py-0.5 rounded">
                                    Bàn: {{ item.table_name }}
                                </span>
                                <span>•</span>
                                <span class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                    <Clock class="size-3" />
                                    Xong lúc: {{ item.prepared_at }}
                                </span>
                            </div>

                            <!-- Hiển thị lại ghi chú nếu có để phục vụ chú ý -->
                            <div v-if="item.notes" class="mt-2 inline-flex items-center gap-1 rounded bg-amber-50 px-1.5 py-0.5 text-[9px] text-amber-700 dark:bg-amber-950/10 dark:text-amber-400 font-semibold">
                                <MessageSquare class="size-2.5 text-amber-500" />
                                {{ item.notes }}
                            </div>
                        </div>

                        <!-- Nút hoàn thành phục vụ -->
                        <Button 
                            class="h-10 w-10 shrink-0 rounded-xl bg-emerald-500 text-white hover:bg-emerald-600 shadow-sm transition-all"
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
    </div>
</template>

<style scoped>
.bg-card {
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
}
.bg-card:hover {
    transform: translateY(-2px);
}
</style>
