<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ShoppingBag,
    UtensilsCrossed,
    BarChart3,
    Sparkles,
    CheckCircle2,
    Clock,
    TrendingUp,
    Zap,
    QrCode,
    ArrowRight,
    Check,
    Bot,
    RefreshCw,
    ShieldCheck,
    CreditCard,
} from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';

// Demo Tabs Definition
const demoTabs = [
    { key: 'pos', label: 'POS Checkout', icon: ShoppingBag },
    { key: 'kds', label: 'Bếp KDS', icon: UtensilsCrossed },
    { key: 'report', label: 'AI Analytics', icon: BarChart3 },
] as const;

type TabKey = (typeof demoTabs)[number]['key'];
const activeDemo = ref<TabKey>('pos');
const isSimulating = ref(false);
const autoPlay = ref(true);
let autoPlayInterval: ReturnType<typeof setInterval> | null = null;

// Interactive POS State
const posPaid = ref(true);

function togglePaymentStatus() {
    isSimulating.value = true;
    setTimeout(() => {
        posPaid.value = !posPaid.value;
        isSimulating.value = false;
    }, 350);
}

// KDS Interactive Tickets
const kdsTickets = ref([
    {
        id: '#104',
        table: 'Bàn 12',
        item: '🍜 Phở bò tái nạm (x2)',
        note: 'Ít hành, thêm giấm',
        time: '03:45',
        status: 'cooking',
        statusLabel: 'Đang nấu',
        color: 'text-amber-400 border-amber-500/30 bg-amber-500/10',
    },
    {
        id: '#105',
        table: 'Bàn 08',
        item: '🥤 Trà chanh giã tay (x2)',
        note: '50% đường, 100% đá',
        time: '01:12',
        status: 'ready',
        statusLabel: 'Sẵn sàng',
        color: 'text-emerald-400 border-emerald-500/30 bg-emerald-500/10',
    },
    {
        id: '#106',
        table: 'Bàn 03',
        item: '🍱 Cơm gà sốt mắm tỏi (x1)',
        note: 'Không cay',
        time: '06:20',
        status: 'pending',
        statusLabel: 'Chờ làm',
        color: 'text-sky-400 border-sky-500/30 bg-sky-500/10',
    },
]);

function cycleKdsStatus(index: number) {
    const statuses = [
        {
            status: 'pending',
            label: 'Chờ làm',
            color: 'text-sky-400 border-sky-500/30 bg-sky-500/10',
        },
        {
            status: 'cooking',
            label: 'Đang nấu',
            color: 'text-amber-400 border-amber-500/30 bg-amber-500/10',
        },
        {
            status: 'ready',
            label: 'Sẵn sàng',
            color: 'text-emerald-400 border-emerald-500/30 bg-emerald-500/10',
        },
    ];

    const currentIdx = statuses.findIndex(
        (s) => s.status === kdsTickets.value[index].status,
    );
    const next = statuses[(currentIdx + 1) % statuses.length];
    kdsTickets.value[index].status = next.status;
    kdsTickets.value[index].statusLabel = next.label;
    kdsTickets.value[index].color = next.color;
}

// Auto play tabs cycling
function startAutoPlay() {
    if (autoPlayInterval) {
        clearInterval(autoPlayInterval);
    }

    autoPlayInterval = setInterval(() => {
        if (!autoPlay.value) {
            return;
        }

        const keys: TabKey[] = ['pos', 'kds', 'report'];
        const currentIdx = keys.indexOf(activeDemo.value);
        activeDemo.value = keys[(currentIdx + 1) % keys.length];
    }, 7000);
}

function selectTab(key: TabKey) {
    activeDemo.value = key;
    autoPlay.value = false;
}

onMounted(() => {
    startAutoPlay();
});

onUnmounted(() => {
    if (autoPlayInterval) {
        clearInterval(autoPlayInterval);
    }
});
</script>

<template>
    <!-- Right Column: Premium Interactive Live Demo Shell -->
    <div
        class="demo-shell group relative mx-auto w-full max-w-lg rounded-2xl border border-amber-500/25 bg-gradient-to-b from-zinc-900/95 via-zinc-950/95 to-black/95 p-3 shadow-[0_0_50px_rgba(245,158,11,0.12),0_25px_60px_rgba(0,0,0,0.85)] backdrop-blur-2xl transition-all duration-500 hover:border-amber-500/40 hover:shadow-[0_0_60px_rgba(245,158,11,0.2),0_30px_70px_rgba(0,0,0,0.9)] sm:p-6 lg:ml-auto"
    >
        <!-- Ambient Glowing Aura -->
        <div
            class="pointer-events-none absolute -top-12 -right-12 h-40 w-40 rounded-full bg-amber-500/15 blur-3xl"
        ></div>
        <div
            class="pointer-events-none absolute -bottom-12 -left-12 h-40 w-40 rounded-full bg-indigo-500/10 blur-3xl"
        ></div>

        <!-- Top macOS Window Controls Bar -->
        <div
            class="mb-3 flex items-center justify-between border-b border-white/10 pb-3 sm:mb-4"
        >
            <!-- Window Dots & Title -->
            <div class="flex min-w-0 items-center gap-2">
                <div class="flex items-center gap-1.5">
                    <span
                        class="h-2.5 w-2.5 rounded-full bg-rose-500/80 shadow-[0_0_8px_rgba(244,63,94,0.5)]"
                    ></span>
                    <span
                        class="h-2.5 w-2.5 rounded-full bg-amber-500/80 shadow-[0_0_8px_rgba(245,158,11,0.5)]"
                    ></span>
                    <span
                        class="h-2.5 w-2.5 rounded-full bg-emerald-500/80 shadow-[0_0_8px_rgba(16,185,129,0.5)]"
                    ></span>
                </div>
                <span
                    class="ml-2 hidden text-[11px] font-semibold text-zinc-400 sm:inline"
                >
                    Aventura POS v2.4 • Direct Engine
                </span>
            </div>

            <!-- Live Realtime Sync Indicator -->
            <div class="flex min-w-0 items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-bold text-emerald-400 shadow-[0_0_10px_rgba(16,185,129,0.15)]"
                >
                    <span
                        class="relative flex h-1.5 w-1.5 items-center justify-center"
                    >
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                        ></span>
                        <span
                            class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"
                        ></span>
                    </span>
                    Realtime Sync
                </span>
                <button
                    @click="autoPlay = !autoPlay"
                    class="cursor-pointer rounded border border-white/10 bg-white/5 p-1 text-zinc-400 transition hover:bg-white/10 hover:text-white"
                    :title="
                        autoPlay
                            ? 'Đang tự chuyển tab (Click để tạm dừng)'
                            : 'Click để phát tự động'
                    "
                >
                    <RefreshCw
                        class="h-3 w-3"
                        :class="{ 'animate-spin': autoPlay }"
                    />
                </button>
            </div>
        </div>

        <!-- Live Demo Title Header & Interactive Pill -->
        <div class="mb-4 flex items-center justify-between">
            <div>
                <div class="flex items-center gap-1.5">
                    <Sparkles class="h-3.5 w-3.5 text-amber-400" />
                    <span
                        class="text-[11px] font-extrabold tracking-widest text-amber-400 uppercase"
                    >
                        Trải nghiệm Live Demo
                    </span>
                </div>
                <h3 class="mt-0.5 text-lg font-black text-white sm:text-2xl">
                    {{
                        activeDemo === 'pos'
                            ? 'POS Checkout & In Bill'
                            : activeDemo === 'kds'
                              ? 'Màn hình Bếp KDS Realtime'
                              : 'Báo cáo AI & Quản lý Kho'
                    }}
                </h3>
            </div>

            <span
                class="hidden rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-[10px] font-extrabold tracking-wider text-amber-400 uppercase shadow-[0_0_12px_rgba(245,158,11,0.15)] sm:inline-block"
            >
                Interactive
            </span>
        </div>

        <!-- Segmented Tab Navigation Bar -->
        <div
            class="grid grid-cols-3 gap-1 rounded-xl border border-white/10 bg-zinc-950/80 p-1 backdrop-blur-md sm:gap-1.5 sm:p-1.5"
        >
            <button
                v-for="tab in demoTabs"
                :key="tab.key"
                @click="selectTab(tab.key)"
                class="relative flex min-w-0 cursor-pointer items-center justify-center gap-1 rounded-lg px-1 py-2 text-[11px] font-bold transition-all duration-300 sm:gap-1.5 sm:text-xs"
                :class="
                    activeDemo === tab.key
                        ? 'scale-[1.02] bg-gradient-to-r from-amber-500 to-yellow-500 font-black text-zinc-950 shadow-md shadow-amber-500/20'
                        : 'text-zinc-400 hover:bg-white/5 hover:text-white'
                "
            >
                <component
                    :is="tab.icon"
                    class="h-3 w-3 shrink-0 sm:h-3.5 sm:w-3.5"
                />
                <span class="truncate">{{ tab.label }}</span>
            </button>
        </div>

        <!-- Interactive Screen Panel Canvas -->
        <div
            class="demo-screen relative mt-3 overflow-hidden rounded-xl border border-white/10 bg-zinc-900/60 p-3 shadow-inner sm:mt-4 sm:p-4"
        >
            <Transition name="fade-slide-tab" mode="out-in">
                <!-- 🛒 TAB 1: POS CHECKOUT & ORDER SIMULATION -->
                <div v-if="activeDemo === 'pos'" key="pos" class="space-y-3">
                    <!-- POS Table Header bar -->
                    <div
                        class="flex min-w-0 items-center justify-between gap-2 rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-xs"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <span
                                class="rounded border border-amber-500/30 bg-amber-500/20 px-2 py-0.5 text-[10px] font-extrabold text-amber-400"
                            >
                                Bàn 12 • Tầng 1
                            </span>
                            <span
                                class="hidden font-medium text-zinc-400 sm:inline"
                                >Khách VIP (4 người)</span
                            >
                        </div>
                        <div
                            class="flex shrink-0 items-center gap-1.5 text-[11px] font-bold text-emerald-400"
                        >
                            <QrCode class="h-3.5 w-3.5" />
                            <span>QR Order tại bàn</span>
                        </div>
                    </div>

                    <!-- Order items list grid -->
                    <div class="space-y-1.5">
                        <div
                            class="flex min-w-0 items-center justify-between gap-2 rounded-lg border border-white/5 bg-white/5 px-3 py-2 text-xs transition hover:bg-white/10"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="text-base">🍜</span>
                                <div>
                                    <div class="truncate font-bold text-white">
                                        Phở bò tái nạm
                                    </div>
                                    <div class="text-[10px] text-zinc-400">
                                        Note: Ít hành, thêm giấm
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <div class="font-extrabold text-amber-400">
                                    138.000đ
                                </div>
                                <span
                                    class="py-0.2 rounded bg-indigo-500/20 px-1.5 text-[9px] font-bold text-indigo-300"
                                    >x2 món</span
                                >
                            </div>
                        </div>

                        <div
                            class="flex min-w-0 items-center justify-between gap-2 rounded-lg border border-white/5 bg-white/5 px-3 py-2 text-xs transition hover:bg-white/10"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="text-base">🥤</span>
                                <div>
                                    <div class="truncate font-bold text-white">
                                        Trà chanh giã tay
                                    </div>
                                    <div class="text-[10px] text-zinc-400">
                                        Note: 50% đường, 100% đá
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <div class="font-extrabold text-amber-400">
                                    40.000đ
                                </div>
                                <span
                                    class="py-0.2 rounded bg-indigo-500/20 px-1.5 text-[9px] font-bold text-indigo-300"
                                    >x2 ly</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Receipt summary & Payment toggle -->
                    <div
                        class="rounded-xl border border-white/10 bg-black/60 p-3"
                    >
                        <div
                            class="flex items-center justify-between text-xs text-zinc-400"
                        >
                            <span>Tạm tính (3 món):</span>
                            <span>178.000đ</span>
                        </div>
                        <div
                            class="mt-1 flex items-center justify-between border-b border-dashed border-white/15 pb-2 text-xs text-zinc-400"
                        >
                            <span>Thuế VAT (8%):</span>
                            <span>14.240đ</span>
                        </div>

                        <div
                            class="mt-2.5 flex flex-wrap items-center justify-between gap-2"
                        >
                            <div>
                                <div
                                    class="text-[10px] font-bold tracking-wider text-zinc-400 uppercase"
                                >
                                    Tổng thanh toán
                                </div>
                                <div
                                    class="text-lg font-black tracking-tight text-amber-400"
                                >
                                    192.240đ
                                </div>
                            </div>

                            <button
                                @click="togglePaymentStatus"
                                class="flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-extrabold shadow-md transition-all duration-300 active:scale-95"
                                :class="
                                    posPaid
                                        ? 'border-emerald-500/40 bg-emerald-500/20 text-emerald-400 shadow-emerald-500/10'
                                        : 'animate-pulse border-amber-500/40 bg-amber-500/20 text-amber-400 shadow-amber-500/10'
                                "
                            >
                                <component
                                    :is="posPaid ? CheckCircle2 : CreditCard"
                                    class="h-3.5 w-3.5"
                                />
                                <span>{{
                                    posPaid
                                        ? 'ĐÃ THANH TOÁN (VietQR)'
                                        : 'THỬ THANH TOÁN'
                                }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 🍳 TAB 2: KDS KITCHEN BOARD REALTIME -->
                <div
                    v-else-if="activeDemo === 'kds'"
                    key="kds"
                    class="space-y-3"
                >
                    <!-- KDS Header Metrics bar -->
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div
                            class="rounded-lg border border-white/10 bg-black/40 p-2"
                        >
                            <div
                                class="text-[10px] font-semibold text-zinc-400"
                            >
                                Chờ làm
                            </div>
                            <div class="text-sm font-extrabold text-sky-400">
                                1 order
                            </div>
                        </div>
                        <div
                            class="rounded-lg border border-amber-500/20 bg-amber-500/10 p-2"
                        >
                            <div
                                class="text-[10px] font-semibold text-zinc-400"
                            >
                                Đang chế biến
                            </div>
                            <div class="text-sm font-extrabold text-amber-400">
                                1 order
                            </div>
                        </div>
                        <div
                            class="rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-2"
                        >
                            <div
                                class="text-[10px] font-semibold text-zinc-400"
                            >
                                SLA Trung bình
                            </div>
                            <div
                                class="text-sm font-extrabold text-emerald-400"
                            >
                                4.2 phút
                            </div>
                        </div>
                    </div>

                    <!-- KDS Interactive Tickets list -->
                    <div class="space-y-2">
                        <div
                            v-for="(ticket, idx) in kdsTickets"
                            :key="ticket.id"
                            @click="cycleKdsStatus(idx)"
                            class="group/ticket flex min-w-0 cursor-pointer items-start justify-between gap-2 rounded-xl border border-white/10 bg-white/5 p-2.5 transition hover:border-white/20 hover:bg-white/10"
                        >
                            <div
                                class="flex min-w-0 flex-1 items-center gap-2.5"
                            >
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-white/10 bg-black/40 text-xs font-bold text-white"
                                >
                                    {{ ticket.table.replace('Bàn ', 'B') }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="block truncate text-xs font-bold text-white"
                                            >{{ ticket.item }}</span
                                        >
                                    </div>
                                    <div class="text-[10px] text-zinc-400">
                                        {{ ticket.note }}
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex shrink-0 flex-col items-end gap-1 sm:flex-row sm:items-center sm:gap-2"
                            >
                                <span
                                    class="flex items-center gap-1 text-[10px] font-bold text-zinc-400"
                                >
                                    <Clock class="h-3 w-3 text-amber-400" />
                                    {{ ticket.time }}
                                </span>
                                <span
                                    class="rounded-full border px-2 py-0.5 text-[10px] font-extrabold transition-all group-hover/ticket:scale-105"
                                    :class="ticket.color"
                                >
                                    {{ ticket.statusLabel }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="text-center text-[10px] text-zinc-400">
                        💡
                        <em
                            >Mẹo: Click vào bất kỳ thẻ đơn nào ở trên để đổi
                            trạng thái chế biến thực tế!</em
                        >
                    </div>
                </div>

                <!-- 📊 TAB 3: EXECUTIVE REALTIME AI ANALYTICS -->
                <div
                    v-else-if="activeDemo === 'report'"
                    key="report"
                    class="space-y-3"
                >
                    <!-- Revenue & KPI Grid -->
                    <div class="grid grid-cols-2 gap-2 sm:gap-2.5">
                        <div
                            class="rounded-xl border border-white/10 bg-black/40 p-2.5 sm:p-3"
                        >
                            <div
                                class="flex items-center justify-between text-[10px] font-bold text-zinc-400 uppercase"
                            >
                                <span>Doanh thu hôm nay</span>
                                <TrendingUp
                                    class="h-3.5 w-3.5 text-emerald-400"
                                />
                            </div>
                            <div
                                class="mt-1 text-base font-black text-white sm:text-lg"
                            >
                                28.650.000đ
                            </div>
                            <div
                                class="mt-0.5 flex items-center gap-1 text-[10px] font-bold text-emerald-400"
                            >
                                <span>+24.5%</span>
                                <span class="text-zinc-500">vs tuần trước</span>
                            </div>
                        </div>

                        <div
                            class="rounded-xl border border-white/10 bg-black/40 p-2.5 sm:p-3"
                        >
                            <div
                                class="flex items-center justify-between text-[10px] font-bold text-zinc-400 uppercase"
                            >
                                <span>Hóa đơn / SLA</span>
                                <Zap class="h-3.5 w-3.5 text-amber-400" />
                            </div>
                            <div
                                class="mt-1 text-base font-black text-amber-400 sm:text-lg"
                            >
                                142 đơn
                            </div>
                            <div
                                class="mt-0.5 text-[10px] font-bold text-emerald-400"
                            >
                                99.2% Đúng hạn (&lt;10p)
                            </div>
                        </div>
                    </div>

                    <!-- AI Insights Recommendation Card -->
                    <div
                        class="rounded-xl border border-indigo-500/30 bg-gradient-to-r from-indigo-950/40 via-purple-950/30 to-black/50 p-2.5 shadow-lg sm:p-3"
                    >
                        <div
                            class="flex items-center justify-between border-b border-indigo-500/20 pb-2"
                        >
                            <div class="flex items-center gap-1.5">
                                <Bot class="h-4 w-4 text-indigo-400" />
                                <span class="text-xs font-bold text-indigo-300"
                                    >AI Demand Forecast & Cảnh báo Kho</span
                                >
                            </div>
                            <span
                                class="rounded bg-indigo-500/20 px-2 py-0.5 text-[9px] font-extrabold text-indigo-400"
                                >AI Live</span
                            >
                        </div>

                        <div class="mt-2 text-xs leading-relaxed text-zinc-200">
                            🌦️ <strong>Dự báo thời tiết:</strong> Chiều nay mưa
                            lạnh 22°C. Dự báo món <em>Phở Bò & Lẩu</em> tăng
                            <strong>+35%</strong>.
                            <div
                                class="mt-1 flex items-center justify-between text-[11px] font-bold text-amber-400"
                            >
                                <span
                                    >⚠️ Cảnh báo: Thịt bò tái còn 3.2kg (Thiếu
                                    ~1.8kg)</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- CTA button at the bottom of the widget -->
        <div class="mt-4 border-t border-white/10 pt-3 sm:mt-5">
            <Button
                as-child
                size="lg"
                class="group/btn relative w-full overflow-hidden rounded-xl bg-gradient-to-r from-amber-500 via-yellow-500 to-amber-500 py-3.5 text-xs font-black tracking-wider text-zinc-950 uppercase shadow-[0_0_25px_rgba(245,158,11,0.3)] transition-all duration-300 hover:scale-[1.02] hover:from-amber-400 hover:via-yellow-400 hover:to-amber-400 hover:shadow-[0_0_35px_rgba(245,158,11,0.5)] active:scale-95"
            >
                <Link
                    :href="register()"
                    class="flex items-center justify-center gap-2"
                >
                    <span>Đăng ký dùng thử miễn phí</span>
                    <ArrowRight
                        class="h-4 w-4 transition-transform duration-300 group-hover/btn:translate-x-1"
                    />
                </Link>
            </Button>
            <div
                class="mt-2 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[10px] font-medium text-zinc-400 sm:gap-4"
            >
                <span class="flex items-center gap-1">
                    <ShieldCheck class="h-3 w-3 text-emerald-400" /> Dùng thử 14
                    ngày
                </span>
                <span class="flex items-center gap-1">
                    <Check class="h-3 w-3 text-amber-400" /> Không cần thẻ tín
                    dụng
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Keep the widget stable while each demo tab renders different content. */
.demo-shell {
    box-sizing: border-box;
}

.demo-screen {
    box-sizing: border-box;
    height: 340px;
    min-height: 340px;
    max-height: 340px;
}

@media (min-width: 640px) {
    .demo-screen {
        height: 322px;
        min-height: 322px;
        max-height: 322px;
    }

    .demo-shell {
        height: 645px;
        min-height: 645px;
        max-height: 645px;
    }
}

/* Smooth cross-fade tab transition */
.fade-slide-tab-enter-active,
.fade-slide-tab-leave-active {
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.fade-slide-tab-enter-from {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
}

.fade-slide-tab-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.98);
}
</style>
