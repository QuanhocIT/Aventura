<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    TrendingUp,
    TrendingDown,
    Minus,
    Star,
    FlaskConical,
    Play,
    Square,
    Loader2,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface ScoringItem {
    product_id: number;
    name: string;
    price: number;
    cost_price: number;
    qty: number;
    revenue: number;
    margin: number;
    popularity_score: number;
    profitability_score: number;
    trend_score: number;
    composite_score: number;
    suggestion: string;
    weekly_avg: number;
}

const props = defineProps<{
    scoring: ScoringItem[];
    bcgData?: any[];
    priceTests: any[];
    days: number;
}>();

const page = usePage();
watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    },
);

const bcgStats = computed(() => {
    const list = props.bcgData || [];

    return {
        star: list.filter((i: any) => i.quadrant === 'star'),
        plowhorse: list.filter((i: any) => i.quadrant === 'plowhorse'),
        puzzle: list.filter((i: any) => i.quadrant === 'puzzle'),
        dog: list.filter((i: any) => i.quadrant === 'dog'),
    };
});

const activeTab = ref<'scoring' | 'ab-test' | 'schedule'>('scoring');

// BCG Matrix View state and coordinate math helpers
const scoringViewMode = ref<'bcg' | 'table'>('bcg');
const hoveredItem = ref<ScoringItem | null>(null);
const tooltipX = ref(0);
const tooltipY = ref(0);

const margin = { top: 40, right: 40, bottom: 60, left: 60 };
const width = 600;
const height = 500;
const plotWidth = width - margin.left - margin.right;
const plotHeight = height - margin.top - margin.bottom;

function getX(score: number) {
    return margin.left + (score / 100) * plotWidth;
}

function getY(score: number) {
    return margin.top + (1 - score / 100) * plotHeight;
}

function handleMouseEnter(item: ScoringItem, event: MouseEvent) {
    hoveredItem.value = item;
    const current = event.currentTarget as SVGElement;
    const parent = current.ownerSVGElement?.parentElement;

    if (parent) {
        const rect = current.getBoundingClientRect();
        const parentRect = parent.getBoundingClientRect();
        tooltipX.value = rect.left - parentRect.left + rect.width / 2;
        tooltipY.value = rect.top - parentRect.top - 8;
    }
}

function handleMouseLeave() {
    hoveredItem.value = null;
}

const tooltipStyle = computed(() => {
    if (!hoveredItem.value) {
        return {};
    }

    return {
        left: `${tooltipX.value}px`,
        top: `${tooltipY.value}px`,
        transform: 'translate(-50%, -100%)',
    };
});

// A/B Test form
const showTestDialog = ref(false);
const testForm = useForm({
    product_id: null as number | null,
    name: '',
    test_price: 0,
    start_at: '',
    end_at: '',
});

function openCreateTest(item?: ScoringItem) {
    testForm.reset();

    if (item) {
        testForm.product_id = item.product_id;
        testForm.name = `Test giá ${item.name}`;
        testForm.test_price = Math.round(item.price * 1.05);
    }

    showTestDialog.value = true;
}

// Complete test helper
function submitTest() {
    testForm.post('/menu-engineering/price-tests', {
        onSuccess: () => {
            showTestDialog.value = false;
        },
    });
}

async function completeTest(test: any) {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: 'Kết thúc test và khôi phục giá gốc?',
            variant: 'default',
        }))
    ) {
        return;
    }

    router.post(`/menu-engineering/price-tests/${test.id}/complete`);
}

async function cancelTest(test: any) {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: 'Hủy test và khôi phục giá gốc?',
        }))
    ) {
        return;
    }

    router.post(`/menu-engineering/price-tests/${test.id}/cancel`);
}

// Time slot / season update
function updateSchedule(
    productId: number,
    field: string,
    value: string | null,
) {
    router.patch(
        `/menu-engineering/products/${productId}/time-slot`,
        {
            [field]: value || null,
        },
        { preserveScroll: true },
    );
}

// Score color
function scoreColor(score: number): string {
    if (score >= 70) {
        return 'text-green-600';
    }

    if (score >= 40) {
        return 'text-amber-500';
    }

    return 'text-red-500';
}

function scoreBg(score: number): string {
    if (score >= 70) {
        return 'bg-green-100 dark:bg-green-900/30';
    }

    if (score >= 40) {
        return 'bg-amber-100 dark:bg-amber-900/30';
    }

    return 'bg-red-100 dark:bg-red-900/30';
}

const avgScore = computed(() => {
    if (!props.scoring.length) {
        return 0;
    }

    return Math.round(
        props.scoring.reduce((s, i) => s + i.composite_score, 0) /
            props.scoring.length,
    );
});

const topPerformers = computed(
    () => props.scoring.filter((s) => s.composite_score >= 70).length,
);
const needsAttention = computed(
    () => props.scoring.filter((s) => s.composite_score < 40).length,
);
</script>

<template>
    <Head title="Menu Engineering" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 lg:p-6">
        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-500/10 text-violet-600 dark:text-violet-400"
                >
                    <BarChart3
                        class="size-6 text-violet-600 dark:text-violet-400"
                    />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        Menu Engineering Nâng Cao
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Phân tích, chấm điểm, A/B testing giá, và lập lịch menu
                        theo giờ/mùa.
                    </p>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <!-- 1. Điểm TB menu -->
            <Card>
                <CardContent class="px-4 pt-4 pb-4">
                    <div class="mb-2 flex items-center justify-between">
                        <span
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >Điểm TB menu</span
                        >
                        <Star class="size-4 text-amber-500" />
                    </div>
                    <p :class="['text-xl font-bold', scoreColor(avgScore)]">
                        {{ avgScore }}/100
                    </p>
                    <p class="mt-1.5 text-[10px] text-muted-foreground">
                        Điểm chất lượng menu tổng hợp
                    </p>
                </CardContent>
            </Card>

            <!-- 2. Top performers -->
            <Card>
                <CardContent class="px-4 pt-4 pb-4">
                    <div class="mb-2 flex items-center justify-between">
                        <span
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >Top performers</span
                        >
                        <TrendingUp class="size-4 text-emerald-500" />
                    </div>
                    <p
                        class="text-xl font-bold text-emerald-600 dark:text-emerald-400"
                    >
                        {{ topPerformers }} món
                    </p>
                    <p class="mt-1.5 text-[10px] text-muted-foreground">
                        Số món đạt điểm xuất sắc
                    </p>
                </CardContent>
            </Card>

            <!-- 3. Cần cải thiện -->
            <Card>
                <CardContent class="px-4 pt-4 pb-4">
                    <div class="mb-2 flex items-center justify-between">
                        <span
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >Cần cải thiện</span
                        >
                        <TrendingDown class="size-4 text-rose-500" />
                    </div>
                    <p class="text-xl font-bold text-rose-500">
                        {{ needsAttention }} món
                    </p>
                    <p class="mt-1.5 text-[10px] text-muted-foreground">
                        Số món cần tối ưu lại giá/cost
                    </p>
                </CardContent>
            </Card>

            <!-- 4. A/B Tests đang chạy -->
            <Card>
                <CardContent class="px-4 pt-4 pb-4">
                    <div class="mb-2 flex items-center justify-between">
                        <span
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >A/B Tests đang chạy</span
                        >
                        <FlaskConical class="size-4 text-blue-500" />
                    </div>
                    <p
                        class="text-xl font-bold text-blue-600 dark:text-blue-400"
                    >
                        {{
                            priceTests.filter((t) => t.status === 'running')
                                .length
                        }}
                    </p>
                    <p class="mt-1.5 text-[10px] text-muted-foreground">
                        Chiến dịch thử nghiệm giá hiện tại
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Tabs Switcher (Pill Style) -->
        <div
            class="flex max-w-full items-center gap-1 self-start overflow-x-auto rounded-xl border border-border bg-muted p-1"
        >
            <button
                v-for="tab in [
                    { key: 'scoring', label: 'Menu Scoring' },
                    { key: 'ab-test', label: 'A/B Testing giá' },
                    { key: 'schedule', label: 'Lịch menu theo giờ/mùa' },
                ]"
                :key="tab.key"
                @click="activeTab = tab.key as any"
                class="cursor-pointer rounded-lg px-3.5 py-1.5 text-xs font-semibold whitespace-nowrap transition-all"
                :class="
                    activeTab === tab.key
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Tab: Scoring -->
        <div v-if="activeTab === 'scoring'" class="animate-fade-in space-y-4">
            <!-- Toggle buttons for Table vs BCG -->
            <div
                class="flex flex-col justify-between gap-3 border-b border-border/40 pb-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h3 class="text-sm font-bold text-foreground">
                        Phân tích thực đơn
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Chọn kiểu hiển thị ma trận BCG hoặc bảng thống kê chi
                        tiết.
                    </p>
                </div>
                <div
                    class="flex shrink-0 items-center gap-1 rounded-xl bg-muted p-1"
                >
                    <button
                        @click="scoringViewMode = 'bcg'"
                        class="cursor-pointer rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                        :class="
                            scoringViewMode === 'bcg'
                                ? 'bg-background text-foreground shadow'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        📊 Ma trận BCG
                    </button>
                    <button
                        @click="scoringViewMode = 'table'"
                        class="cursor-pointer rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                        :class="
                            scoringViewMode === 'table'
                                ? 'bg-background text-foreground shadow'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        📋 Bảng chi tiết
                    </button>
                </div>
            </div>

            <!-- BCG Matrix View -->
            <div
                v-if="scoringViewMode === 'bcg'"
                class="relative overflow-visible rounded-2xl border bg-card p-6 shadow-sm"
            >
                <!-- SVG Area wrapper -->
                <div class="relative mx-auto w-full max-w-3xl overflow-visible">
                    <svg
                        viewBox="0 0 600 500"
                        class="h-auto w-full overflow-visible select-none"
                    >
                        <!-- Quadrants backgrounds -->
                        <!-- Top-Left: Plowhorses -->
                        <rect
                            :x="getX(0)"
                            :y="getY(100)"
                            :width="plotWidth / 2"
                            :height="plotHeight / 2"
                            class="fill-amber-500/[0.03] dark:fill-amber-500/[0.06]"
                        />
                        <!-- Top-Right: Stars -->
                        <rect
                            :x="getX(50)"
                            :y="getY(100)"
                            :width="plotWidth / 2"
                            :height="plotHeight / 2"
                            class="fill-emerald-500/[0.03] dark:fill-emerald-500/[0.06]"
                        />
                        <!-- Bottom-Left: Dogs -->
                        <rect
                            :x="getX(0)"
                            :y="getY(50)"
                            :width="plotWidth / 2"
                            :height="plotHeight / 2"
                            class="fill-red-500/[0.03] dark:fill-red-500/[0.06]"
                        />
                        <!-- Bottom-Right: Puzzles -->
                        <rect
                            :x="getX(50)"
                            :y="getY(50)"
                            :width="plotWidth / 2"
                            :height="plotHeight / 2"
                            class="fill-indigo-500/[0.03] dark:fill-indigo-500/[0.06]"
                        />

                        <!-- Grid dividers -->
                        <line
                            :x1="getX(50)"
                            :y1="getY(100)"
                            :x2="getX(50)"
                            :y2="getY(0)"
                            class="stroke-muted-foreground/25"
                            stroke-width="1.5"
                            stroke-dasharray="4"
                        />
                        <line
                            :x1="getX(0)"
                            :y1="getY(50)"
                            :x2="getX(100)"
                            :y2="getY(50)"
                            class="stroke-muted-foreground/25"
                            stroke-width="1.5"
                            stroke-dasharray="4"
                        />

                        <!-- Quadrant Labels -->
                        <!-- Stars -->
                        <text
                            :x="getX(75)"
                            :y="getY(75) - 10"
                            text-anchor="middle"
                            class="fill-emerald-600 text-xs font-extrabold tracking-wider uppercase dark:fill-emerald-400"
                        >
                            🌟 Stars (Món Ngôi Sao)
                        </text>
                        <text
                            :x="getX(75)"
                            :y="getY(75) + 8"
                            text-anchor="middle"
                            class="fill-muted-foreground text-[9px]"
                        >
                            Lợi nhuận cao & Bán chạy
                        </text>

                        <!-- Plowhorses -->
                        <text
                            :x="getX(25)"
                            :y="getY(75) - 10"
                            text-anchor="middle"
                            class="fill-amber-600 text-xs font-extrabold tracking-wider uppercase dark:fill-amber-400"
                        >
                            🐎 Plowhorses (Bò Sữa)
                        </text>
                        <text
                            :x="getX(25)"
                            :y="getY(75) + 8"
                            text-anchor="middle"
                            class="fill-muted-foreground text-[9px]"
                        >
                            Lợi nhuận thấp & Bán chạy
                        </text>

                        <!-- Puzzles -->
                        <text
                            :x="getX(75)"
                            :y="getY(25) - 10"
                            text-anchor="middle"
                            class="fill-indigo-600 text-xs font-extrabold tracking-wider uppercase dark:fill-indigo-400"
                        >
                            🧩 Puzzles (Câu Đố)
                        </text>
                        <text
                            :x="getX(75)"
                            :y="getY(25) + 8"
                            text-anchor="middle"
                            class="fill-muted-foreground text-[9px]"
                        >
                            Lợi nhuận cao & Bán chậm
                        </text>

                        <!-- Dogs -->
                        <text
                            :x="getX(25)"
                            :y="getY(25) - 10"
                            text-anchor="middle"
                            class="fill-red-600 text-xs font-extrabold tracking-wider uppercase dark:fill-red-400"
                        >
                            🐕 Dogs (Chó Hoang)
                        </text>
                        <text
                            :x="getX(25)"
                            :y="getY(25) + 8"
                            text-anchor="middle"
                            class="fill-muted-foreground text-[9px]"
                        >
                            Lợi nhuận thấp & Bán chậm
                        </text>

                        <!-- Axes and ticks -->
                        <!-- Y-Axis -->
                        <line
                            :x1="getX(0)"
                            :y1="getY(0)"
                            :x2="getX(0)"
                            :y2="getY(100)"
                            class="stroke-border"
                            stroke-width="2"
                        />
                        <!-- X-Axis -->
                        <line
                            :x1="getX(0)"
                            :y1="getY(0)"
                            :x2="getX(100)"
                            :y2="getY(0)"
                            class="stroke-border"
                            stroke-width="2"
                        />

                        <!-- Axis Titles -->
                        <text
                            :x="margin.left - 15"
                            :y="margin.top - 10"
                            text-anchor="start"
                            class="fill-foreground text-[10px] font-bold"
                        >
                            Độ phổ biến (%) ↑
                        </text>
                        <text
                            :x="getX(100) + 10"
                            :y="getY(0) + 4"
                            text-anchor="start"
                            class="fill-foreground text-[10px] font-bold"
                        >
                            Biên lợi nhuận (%) →
                        </text>

                        <!-- Axis Ticks & Numbers -->
                        <g
                            v-for="tick in [0, 25, 50, 75, 100]"
                            :key="'tick-x-' + tick"
                        >
                            <line
                                :x1="getX(tick)"
                                :y1="getY(0)"
                                :x2="getX(tick)"
                                :y2="getY(0) + 5"
                                class="stroke-border"
                                stroke-width="1.5"
                            />
                            <text
                                :x="getX(tick)"
                                :y="getY(0) + 18"
                                text-anchor="middle"
                                class="fill-muted-foreground font-mono text-[9px]"
                            >
                                {{ tick }}%
                            </text>
                        </g>
                        <g
                            v-for="tick in [0, 25, 50, 75, 100]"
                            :key="'tick-y-' + tick"
                        >
                            <line
                                :x1="getX(0) - 5"
                                :y1="getY(tick)"
                                :x2="getX(0)"
                                :y2="getY(tick)"
                                class="stroke-border"
                                stroke-width="1.5"
                            />
                            <text
                                :x="getX(0) - 10"
                                :y="getY(tick) + 3"
                                text-anchor="end"
                                class="fill-muted-foreground font-mono text-[9px]"
                            >
                                {{ tick }}%
                            </text>
                        </g>

                        <!-- Data Points (Circles) -->
                        <g v-for="item in scoring" :key="item.product_id">
                            <!-- Outer glow for hover -->
                            <circle
                                :cx="getX(item.profitability_score)"
                                :cy="getY(item.popularity_score)"
                                :r="8 + (item.composite_score / 100) * 12 + 6"
                                :class="[
                                    'cursor-pointer opacity-0 transition-all duration-300 hover:opacity-15',
                                    item.composite_score >= 70
                                        ? 'fill-emerald-500'
                                        : item.composite_score >= 40
                                          ? 'fill-amber-500'
                                          : 'fill-red-500',
                                ]"
                            />
                            <!-- Main circle -->
                            <circle
                                :cx="getX(item.profitability_score)"
                                :cy="getY(item.popularity_score)"
                                :r="8 + (item.composite_score / 100) * 12"
                                :fill="
                                    item.composite_score >= 70
                                        ? '#10b981'
                                        : item.composite_score >= 40
                                          ? '#f59e0b'
                                          : '#ef4444'
                                "
                                class="cursor-pointer stroke-white stroke-2 shadow-lg transition-transform duration-200 hover:scale-110 dark:stroke-slate-900"
                                @mouseenter="(e) => handleMouseEnter(item, e)"
                                @mouseleave="handleMouseLeave"
                            />
                            <!-- Abbreviated label or dot inside circle -->
                            <text
                                :x="getX(item.profitability_score)"
                                :y="getY(item.popularity_score) + 3"
                                text-anchor="middle"
                                class="pointer-events-none fill-white text-[8px] font-extrabold"
                            >
                                {{ item.name.substring(0, 2).toUpperCase() }}
                            </text>
                        </g>
                    </svg>

                    <!-- Text indicator if empty -->
                    <p
                        v-if="!scoring.length"
                        class="py-24 text-center text-muted-foreground"
                    >
                        Chưa có dữ liệu đơn hàng để phân tích.
                    </p>
                </div>

                <!-- HTML Tooltip overlay -->
                <transition name="fade">
                    <div
                        v-if="hoveredItem"
                        class="shadow-elevated pointer-events-none absolute z-20 max-w-xs rounded-xl border border-border bg-card p-3.5 transition-all duration-150 ease-out"
                        :style="tooltipStyle"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <h4 class="text-xs font-bold text-foreground">
                                {{ hoveredItem.name }}
                            </h4>
                            <span
                                :class="[
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold',
                                    scoreBg(hoveredItem.composite_score),
                                    scoreColor(hoveredItem.composite_score),
                                ]"
                            >
                                {{ hoveredItem.composite_score }}/100
                            </span>
                        </div>
                        <p
                            class="mt-0.5 text-[10px] font-medium text-muted-foreground"
                        >
                            Giá: {{ hoveredItem.price.toLocaleString() }}đ · Lợi
                            nhuận {{ hoveredItem.margin }}%
                        </p>

                        <div
                            class="mt-2 grid grid-cols-3 gap-2 border-t border-border/60 pt-2 text-[9px] font-semibold"
                        >
                            <div>
                                <span class="font-normal text-muted-foreground"
                                    >Doanh số:</span
                                >
                                <p class="mt-0.5 font-bold text-foreground">
                                    {{ hoveredItem.qty }} đơn
                                </p>
                            </div>
                            <div>
                                <span class="font-normal text-muted-foreground"
                                    >Phổ biến:</span
                                >
                                <p class="mt-0.5 font-bold text-foreground">
                                    {{ hoveredItem.popularity_score }}%
                                </p>
                            </div>
                            <div>
                                <span class="font-normal text-muted-foreground"
                                    >Xu hướng:</span
                                >
                                <p class="mt-0.5 font-bold text-foreground">
                                    {{ hoveredItem.trend_score }}/100
                                </p>
                            </div>
                        </div>

                        <div
                            class="mt-2.5 rounded-lg border border-violet-500/20 bg-violet-500/10 p-2 text-[10px] leading-normal font-medium text-violet-700 dark:text-violet-300"
                        >
                            💡 {{ hoveredItem.suggestion }}
                        </div>
                    </div>
                </transition>

                <!-- BCG Actions / Recommendations List -->
                <div class="mt-8 border-t border-border pt-6 text-left">
                    <h3
                        class="mb-4 flex items-center gap-1.5 text-xs font-bold text-slate-900 dark:text-white"
                    >
                        <Sparkles
                            class="size-4 animate-pulse text-violet-500"
                        />
                        Gợi ý thông minh từ ma trận BCG (BCG Actionable
                        Recommendations)
                    </h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <!-- Stars -->
                        <div
                            class="space-y-2 rounded-xl border border-emerald-500/20 bg-emerald-500/[0.02] p-4"
                        >
                            <h4
                                class="flex items-center gap-1 text-xs font-extrabold tracking-wide text-emerald-600 uppercase dark:text-emerald-400"
                            >
                                <span>⭐</span> Stars (Món ăn Ngôi sao -
                                {{ bcgStats.star.length }} món)
                            </h4>
                            <ul
                                v-if="bcgStats.star.length"
                                class="dark:text-slate-350 space-y-1.5 text-xs text-slate-600"
                            >
                                <li
                                    v-for="item in bcgStats.star"
                                    :key="item.product_id"
                                >
                                    •
                                    <strong
                                        class="text-slate-900 dark:text-white"
                                        >{{ item.name }}</strong
                                    >: {{ item.ai_recommendation }}
                                </li>
                            </ul>
                            <p
                                v-else
                                class="text-xs text-muted-foreground italic"
                            >
                                Chưa phát hiện món ăn Ngôi sao.
                            </p>
                        </div>

                        <!-- Plowhorses -->
                        <div
                            class="space-y-2 rounded-xl border border-amber-500/20 bg-amber-500/[0.02] p-4"
                        >
                            <h4
                                class="flex items-center gap-1 text-xs font-extrabold tracking-wide text-amber-600 uppercase dark:text-amber-400"
                            >
                                <span>🐎</span> Plowhorses (Món ăn Bò sữa -
                                {{ bcgStats.plowhorse.length }} món)
                            </h4>
                            <ul
                                v-if="bcgStats.plowhorse.length"
                                class="dark:text-slate-350 space-y-1.5 text-xs text-slate-600"
                            >
                                <li
                                    v-for="item in bcgStats.plowhorse"
                                    :key="item.product_id"
                                >
                                    •
                                    <strong
                                        class="text-slate-900 dark:text-white"
                                        >{{ item.name }}</strong
                                    >: {{ item.ai_recommendation }}
                                </li>
                            </ul>
                            <p
                                v-else
                                class="text-xs text-muted-foreground italic"
                            >
                                Chưa phát hiện món ăn Bò sữa.
                            </p>
                        </div>

                        <!-- Puzzles -->
                        <div
                            class="space-y-2 rounded-xl border border-indigo-500/20 bg-indigo-500/[0.02] p-4"
                        >
                            <h4
                                class="flex items-center gap-1 text-xs font-extrabold tracking-wide text-indigo-600 uppercase dark:text-indigo-400"
                            >
                                <span>🧩</span> Puzzles (Món ăn Câu đố -
                                {{ bcgStats.puzzle.length }} món)
                            </h4>
                            <ul
                                v-if="bcgStats.puzzle.length"
                                class="dark:text-slate-350 space-y-1.5 text-xs text-slate-600"
                            >
                                <li
                                    v-for="item in bcgStats.puzzle"
                                    :key="item.product_id"
                                >
                                    •
                                    <strong
                                        class="text-slate-900 dark:text-white"
                                        >{{ item.name }}</strong
                                    >: {{ item.ai_recommendation }}
                                </li>
                            </ul>
                            <p
                                v-else
                                class="text-xs text-muted-foreground italic"
                            >
                                Chưa phát hiện món ăn Câu đố.
                            </p>
                        </div>

                        <!-- Dogs -->
                        <div
                            class="space-y-2 rounded-xl border border-red-500/20 bg-red-500/[0.02] p-4"
                        >
                            <h4
                                class="flex items-center gap-1 text-xs font-extrabold tracking-wide text-red-600 uppercase dark:text-red-400"
                            >
                                <span>🐶</span> Dogs (Món ăn Thú cưng -
                                {{ bcgStats.dog.length }} món)
                            </h4>
                            <ul
                                v-if="bcgStats.dog.length"
                                class="dark:text-slate-350 space-y-1.5 text-xs text-slate-600"
                            >
                                <li
                                    v-for="item in bcgStats.dog"
                                    :key="item.product_id"
                                >
                                    •
                                    <strong
                                        class="text-slate-900 dark:text-white"
                                        >{{ item.name }}</strong
                                    >: {{ item.ai_recommendation }}
                                </li>
                            </ul>
                            <p
                                v-else
                                class="text-xs text-muted-foreground italic"
                            >
                                Chưa phát hiện món ăn Thú cưng.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table View -->
            <Card v-else>
                <CardContent class="p-0 pt-5">
                    <div class="px-5 pb-3">
                        <p class="mb-1 text-sm font-semibold">
                            Bảng điểm Menu ({{ days }} ngày gần nhất)
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Điểm tổng hợp = Popularity (40%) × Profitability
                            (35%) × Trend (25%)
                        </p>
                    </div>
                    <table class="w-full border-t border-border text-sm">
                        <thead class="bg-muted/50">
                            <tr
                                class="border-b text-left text-xs text-muted-foreground"
                            >
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3">Món</th>
                                <th class="px-4 py-3 text-center">Tổng điểm</th>
                                <th class="px-4 py-3 text-center">
                                    Popularity
                                </th>
                                <th class="px-4 py-3 text-center">Profit</th>
                                <th class="px-4 py-3 text-center">Trend</th>
                                <th class="px-4 py-3 text-right">Doanh thu</th>
                                <th class="px-4 py-3 text-right">SL/tuần</th>
                                <th class="px-4 py-3">AI Gợi ý</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, idx) in scoring"
                                :key="item.product_id"
                                class="border-b last:border-0 hover:bg-muted/30"
                            >
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ idx + 1 }}
                                </td>
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="font-medium">
                                            {{ item.name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ item.price.toLocaleString() }}đ |
                                            Margin {{ item.margin }}%
                                        </p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full px-2.5 py-1 text-sm font-bold',
                                            scoreBg(item.composite_score),
                                            scoreColor(item.composite_score),
                                        ]"
                                    >
                                        {{ item.composite_score }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-xs">
                                    {{ item.popularity_score }}
                                </td>
                                <td class="px-4 py-3 text-center text-xs">
                                    {{ item.profitability_score }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="flex items-center justify-center gap-0.5 text-xs"
                                    >
                                        <TrendingUp
                                            v-if="item.trend_score >= 60"
                                            class="size-3 text-green-500"
                                        />
                                        <TrendingDown
                                            v-else-if="item.trend_score < 40"
                                            class="size-3 text-red-500"
                                        />
                                        <Minus
                                            v-else
                                            class="size-3 text-muted-foreground"
                                        />
                                        {{ item.trend_score }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-xs font-medium"
                                >
                                    {{ item.revenue.toLocaleString() }}đ
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    {{ item.weekly_avg }}
                                </td>
                                <td class="max-w-xs px-4 py-3 text-xs">
                                    <p class="line-clamp-2">
                                        {{ item.suggestion }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="!scoring.length"
                        class="py-12 text-center text-muted-foreground"
                    >
                        Chưa có dữ liệu đơn hàng để phân tích.
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Tab: A/B Testing -->
        <div v-if="activeTab === 'ab-test'" class="animate-fade-in space-y-4">
            <div class="flex justify-end">
                <Button
                    @click="openCreateTest()"
                    class="cursor-pointer gap-1.5 rounded-xl bg-violet-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-violet-700 active:scale-95"
                >
                    <FlaskConical class="size-4" /> Tạo A/B Test mới
                </Button>
            </div>

            <Card
                v-for="test in priceTests"
                :key="test.id"
                class="transition-colors hover:bg-muted/10"
            >
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-sm font-medium">{{ test.name }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ test.product?.name }} | Giá gốc:
                            {{ Number(test.original_price).toLocaleString() }}đ
                            → Giá test:
                            {{ Number(test.test_price).toLocaleString() }}đ
                        </p>
                        <div v-if="test.results_json" class="mt-1 text-xs">
                            <span class="text-muted-foreground"
                                >Orders: {{ test.orders_original }} vs
                                {{ test.orders_test }}</span
                            >
                            <span
                                v-if="test.results_json.impact_percent != null"
                                class="ml-2 font-bold"
                                :class="
                                    test.results_json.impact_percent >= 0
                                        ? 'text-green-600'
                                        : 'text-red-500'
                                "
                            >
                                Impact:
                                {{
                                    test.results_json.impact_percent > 0
                                        ? '+'
                                        : ''
                                }}{{ test.results_json.impact_percent }}%
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge
                            :variant="
                                test.status === 'running'
                                    ? 'default'
                                    : 'secondary'
                            "
                            >{{ test.status }}</Badge
                        >
                        <Button
                            v-if="test.status === 'running'"
                            variant="outline"
                            size="sm"
                            @click="completeTest(test)"
                            class="cursor-pointer"
                        >
                            <Square class="mr-1 size-3.5" /> Kết thúc
                        </Button>
                        <Button
                            v-if="test.status === 'running'"
                            variant="ghost"
                            size="sm"
                            class="cursor-pointer text-red-500"
                            @click="cancelTest(test)"
                        >
                            Hủy
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <p
                v-if="!priceTests.length"
                class="py-12 text-center text-sm text-muted-foreground"
            >
                Chưa có A/B test nào. Tạo test mới để so sánh hiệu quả giá bán.
            </p>
        </div>

        <!-- Tab: Schedule -->
        <div v-if="activeTab === 'schedule'" class="animate-fade-in space-y-4">
            <Card>
                <CardContent class="p-0 pt-5">
                    <div class="px-5 pb-3">
                        <p class="mb-1 text-sm font-semibold">
                            Lịch hiển thị menu theo giờ & mùa
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Để trống = hiển thị cả ngày, mọi mùa. Chỉ áp dụng
                            cho QR menu và online ordering.
                        </p>
                    </div>
                    <table class="w-full border-t border-border text-sm">
                        <thead class="bg-muted/50">
                            <tr
                                class="border-b text-left text-xs text-muted-foreground"
                            >
                                <th class="px-4 py-3">Món</th>
                                <th class="px-4 py-3">Giá</th>
                                <th class="px-4 py-3">Khung giờ</th>
                                <th class="px-4 py-3">Mùa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in scoring"
                                :key="item.product_id"
                                class="border-b transition-colors last:border-0 hover:bg-muted/10"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ item.name }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{ item.price.toLocaleString() }}đ
                                </td>
                                <td class="px-4 py-3">
                                    <select
                                        class="h-8 cursor-pointer rounded-md border border-border bg-background px-2 text-xs"
                                        @change="
                                            (e: any) =>
                                                updateSchedule(
                                                    item.product_id,
                                                    'time_slot',
                                                    e.target.value,
                                                )
                                        "
                                    >
                                        <option value="">Cả ngày</option>
                                        <option value="morning">
                                            Sáng (trước 11h)
                                        </option>
                                        <option value="lunch">
                                            Trưa (11h-14h)
                                        </option>
                                        <option value="afternoon">
                                            Chiều (14h-17h)
                                        </option>
                                        <option value="dinner">
                                            Tối (sau 17h)
                                        </option>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <select
                                        class="h-8 cursor-pointer rounded-md border border-border bg-background px-2 text-xs"
                                        @change="
                                            (e: any) =>
                                                updateSchedule(
                                                    item.product_id,
                                                    'season',
                                                    e.target.value,
                                                )
                                        "
                                    >
                                        <option value="">Quanh năm</option>
                                        <option value="spring">Xuân</option>
                                        <option value="summer">Hè</option>
                                        <option value="autumn">Thu</option>
                                        <option value="winter">Đông</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- A/B Test Create Dialog -->
    <Dialog v-model:open="showTestDialog">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Tạo A/B Test Giá</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitTest" class="space-y-4">
                <div class="grid gap-1.5">
                    <Label>Chọn món</Label>
                    <select
                        v-model="testForm.product_id"
                        required
                        class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                    >
                        <option :value="null" disabled>Chọn món...</option>
                        <option
                            v-for="s in scoring"
                            :key="s.product_id"
                            :value="s.product_id"
                        >
                            {{ s.name }} ({{ s.price.toLocaleString() }}đ)
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Tên test</Label>
                    <Input
                        v-model="testForm.name"
                        placeholder="Ví dụ: Test tăng giá Phở 10%"
                        required
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label>Giá thử nghiệm (VND)</Label>
                    <Input
                        type="number"
                        v-model="testForm.test_price"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Bắt đầu</Label>
                        <Input
                            type="date"
                            v-model="testForm.start_at"
                            required
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Kết thúc</Label>
                        <Input type="date" v-model="testForm.end_at" required />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="showTestDialog = false"
                        >Hủy</Button
                    >
                    <Button
                        type="submit"
                        :disabled="testForm.processing"
                        class="gap-1.5"
                    >
                        <Loader2
                            v-if="testForm.processing"
                            class="size-4 animate-spin"
                        />
                        <Play v-else class="size-4" />
                        {{
                            testForm.processing ? 'Đang tạo...' : 'Bắt đầu test'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
