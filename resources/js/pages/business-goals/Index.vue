<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Circle,
    Flag,
    Plus,
    Target,
    Trophy,
    Banknote,
    ShoppingCart,
    Users,
    Star,
    TrendingDown,
    Settings,
    Calendar,
    Activity,
    Inbox,
    AlertTriangle,
    ArrowUpRight,
    ArrowRight,
    Trash,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
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
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ activeGoals: any[]; history: any[] }>();

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

const activeTab = ref<'active' | 'history'>('active');

const metricLabel: Record<string, string> = {
    revenue: 'Doanh thu',
    orders: 'Số đơn hàng',
    customers: 'Khách hàng mới',
    rating: 'Đánh giá TB',
    cost_saving: 'Tiết kiệm chi phí',
    custom: 'Tùy chỉnh',
};
const periodLabel: Record<string, string> = {
    weekly: 'Tuần',
    monthly: 'Tháng',
    quarterly: 'Quý',
    yearly: 'Năm',
};

const metricOptions = [
    { value: 'revenue', label: 'Doanh thu', icon: Banknote },
    { value: 'orders', label: 'Số đơn hàng', icon: ShoppingCart },
    { value: 'customers', label: 'Khách hàng mới', icon: Users },
    { value: 'rating', label: 'Đánh giá TB', icon: Star },
    { value: 'cost_saving', label: 'Tiết kiệm chi phí', icon: TrendingDown },
    { value: 'custom', label: 'Tùy chỉnh', icon: Settings },
];

const periodOptions = [
    { value: 'weekly', label: 'Tuần' },
    { value: 'monthly', label: 'Tháng' },
    { value: 'quarterly', label: 'Quý' },
    { value: 'yearly', label: 'Năm' },
];

function progressColor(p: number): string {
    if (p >= 100) {
        return 'bg-emerald-500';
    }

    if (p >= 50) {
        return 'bg-blue-500';
    }

    if (p >= 25) {
        return 'bg-amber-500';
    }

    return 'bg-rose-500';
}

function formatValue(metric: string, value: number): string {
    if (metric === 'rating') {
        return value.toFixed(1) + '/5';
    }

    if (['revenue', 'cost_saving'].includes(metric)) {
        return value.toLocaleString() + 'đ';
    }

    return value.toLocaleString();
}

function getTimeProgress(startDateStr: string, endDateStr: string): number {
    const start = new Date(startDateStr).getTime();
    const end = new Date(endDateStr).getTime();
    const now = new Date().getTime();

    if (end <= start) {
        return 0;
    }

    const elapsed = now - start;
    const total = end - start;

    return Math.max(0, Math.min(100, Math.round((elapsed / total) * 100)));
}

function getPaceStatus(
    progress: number,
    timeProgress: number,
): { label: string; color: string; badge: string; border: string } {
    if (progress >= 100) {
        return {
            label: 'Hoàn thành',
            color: 'text-emerald-500 dark:text-emerald-400',
            badge: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20 dark:border-emerald-500/30',
            border: 'border-emerald-500/30 dark:border-emerald-500/40 hover:shadow-[0_0_20px_-3px_rgba(16,185,129,0.12)]',
        };
    }

    const diff = timeProgress - progress;

    if (diff > 15) {
        return {
            label: 'Chậm tiến độ',
            color: 'text-rose-500 dark:text-rose-400',
            badge: 'bg-rose-500/10 text-rose-500 border-rose-500/20 dark:border-rose-500/30',
            border: 'border-rose-500/30 dark:border-rose-500/40 hover:shadow-[0_0_20px_-3px_rgba(239,68,68,0.12)]',
        };
    } else if (diff > 0) {
        return {
            label: 'Cần đẩy nhanh',
            color: 'text-amber-500 dark:text-amber-400',
            badge: 'bg-amber-500/10 text-amber-500 border-amber-500/20 dark:border-amber-500/30',
            border: 'border-amber-500/30 dark:border-amber-500/40 hover:shadow-[0_0_20px_-3px_rgba(245,158,11,0.12)]',
        };
    } else {
        return {
            label: 'Đúng tiến độ',
            color: 'text-blue-500 dark:text-blue-400',
            badge: 'bg-blue-500/10 text-blue-500 border-blue-500/20 dark:border-blue-500/30',
            border: 'border-blue-500/30 dark:border-blue-500/40 hover:shadow-[0_0_20px_-3px_rgba(59,130,246,0.12)]',
        };
    }
}

// Global Stats Computeds
const avgProgress = computed(() => {
    if (props.activeGoals.length === 0) {
        return 0;
    }

    const sum = props.activeGoals.reduce((s, g) => s + g.progress_percent, 0);

    return Math.round(sum / props.activeGoals.length);
});

const actionsRatio = computed(() => {
    let total = 0;
    let done = 0;
    props.activeGoals.forEach((g) => {
        if (g.actions) {
            g.actions.forEach((a: any) => {
                total++;

                if (a.status === 'done') {
                    done++;
                }
            });
        }
    });

    return {
        done,
        total,
        pct: total === 0 ? 0 : Math.round((done / total) * 100),
    };
});

const milestonesRatio = computed(() => {
    let total = 0;
    let reached = 0;
    props.activeGoals.forEach((g) => {
        if (g.milestones) {
            g.milestones.forEach((m: any) => {
                total++;

                if (m.reached) {
                    reached++;
                }
            });
        }
    });

    return {
        reached,
        total,
        pct: total === 0 ? 0 : Math.round((reached / total) * 100),
    };
});

// Create goal
const showCreateDialog = ref(false);
const goalForm = useForm({
    title: '',
    description: '',
    owner_name: '',
    unit_name: '',
    metric: 'revenue' as string,
    period: 'monthly' as string,
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '',
    target_value: 0,
    milestones: [
        { title: 'Đạt 50%', threshold_percent: 50 },
        { title: 'Đạt 100%', threshold_percent: 100 },
    ] as { title: string; threshold_percent: number }[],
});

function addMilestone() {
    goalForm.milestones.push({ title: '', threshold_percent: 75 });
}

function removeMilestone(idx: number) {
    goalForm.milestones.splice(idx, 1);
}

function submitGoal() {
    goalForm.post('/business-goals', {
        onSuccess: () => {
            showCreateDialog.value = false;
            goalForm.reset();
        },
    });
}

// Add action
const showActionDialog = ref(false);
const actionGoalId = ref<number | null>(null);
const actionForm = useForm({ title: '', description: '', due_date: '' });

function openActionDialog(goalId: number) {
    actionGoalId.value = goalId;
    actionForm.reset();
    showActionDialog.value = true;
}

function submitAction() {
    if (!actionGoalId.value) {
        return;
    }

    actionForm.post(`/business-goals/${actionGoalId.value}/actions`, {
        onSuccess: () => {
            showActionDialog.value = false;
        },
    });
}

function toggleAction(actionId: number) {
    router.patch(`/business-goals/actions/${actionId}/toggle`);
}
</script>

<template>
    <Head title="Mục tiêu & OKR" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div
            class="flex flex-col gap-4 border-b border-border/80 pb-6 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 ring-4 ring-amber-500/5 dark:text-amber-400"
                >
                    <Target class="size-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-black tracking-tight text-foreground"
                    >
                        Mục Tiêu & OKR Doanh Nghiệp
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Thiết lập mục tiêu dài hạn, theo dõi tiến độ thời gian
                        thực và kế hoạch hành động.
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <Button
                    @click="showCreateDialog = true"
                    class="cursor-pointer gap-1.5 rounded-xl bg-amber-600 font-bold text-white shadow-sm transition hover:bg-amber-700 active:scale-95"
                >
                    <Plus class="size-4" /> Tạo mục tiêu mới
                </Button>
            </div>
        </div>

        <!-- Tab & Stats Control Area -->
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <!-- Tabs Switcher (Pill Style) -->
            <div
                class="flex items-center gap-1 self-start rounded-xl border border-border bg-muted p-1"
            >
                <button
                    v-for="tab in [
                        { key: 'active', label: 'Đang hoạt động' },
                        { key: 'history', label: 'Lịch sử' },
                    ]"
                    :key="tab.key"
                    @click="activeTab = tab.key as any"
                    class="cursor-pointer rounded-lg px-4 py-2 text-xs font-bold whitespace-nowrap transition-all"
                    :class="
                        activeTab === tab.key
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <!-- Active goals tab -->
        <div v-if="activeTab === 'active'" class="space-y-6">
            <!-- Strategic Stats Panel -->
            <div
                v-if="activeGoals.length"
                class="grid grid-cols-2 gap-4 lg:grid-cols-4"
            >
                <!-- Average OKR Progress -->
                <Card
                    class="border-border bg-card shadow-xs transition-all duration-300 hover:shadow-md"
                >
                    <CardContent class="flex items-center justify-between p-5">
                        <div class="space-y-1">
                            <span
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Tiến độ TB</span
                            >
                            <p
                                class="text-2xl font-black tracking-tight text-foreground"
                            >
                                {{ avgProgress }}%
                            </p>
                        </div>
                        <div
                            class="shrink-0 rounded-2xl bg-amber-500/10 p-3 text-amber-500"
                        >
                            <Target class="size-5" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Active Goals Count -->
                <Card
                    class="border-border bg-card shadow-xs transition-all duration-300 hover:shadow-md"
                >
                    <CardContent class="flex items-center justify-between p-5">
                        <div class="space-y-1">
                            <span
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Đang chạy</span
                            >
                            <p
                                class="text-2xl font-black tracking-tight text-foreground"
                            >
                                {{ activeGoals.length }} mục tiêu
                            </p>
                        </div>
                        <div
                            class="shrink-0 rounded-2xl bg-blue-500/10 p-3 text-blue-500"
                        >
                            <Flag class="size-5" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Actions Ratio Completed -->
                <Card
                    class="border-border bg-card shadow-xs transition-all duration-300 hover:shadow-md"
                >
                    <CardContent class="flex items-center justify-between p-5">
                        <div class="space-y-1">
                            <span
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Hành động xong</span
                            >
                            <p
                                class="text-2xl font-black tracking-tight text-foreground"
                            >
                                {{ actionsRatio.done }}/{{ actionsRatio.total }}
                            </p>
                        </div>
                        <div
                            class="shrink-0 rounded-2xl bg-emerald-500/10 p-3 text-emerald-500"
                        >
                            <CheckCircle2 class="size-5" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Milestones Ratio -->
                <Card
                    class="border-border bg-card shadow-xs transition-all duration-300 hover:shadow-md"
                >
                    <CardContent class="flex items-center justify-between p-5">
                        <div class="space-y-1">
                            <span
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Mốc hoàn thành</span
                            >
                            <p
                                class="text-2xl font-black tracking-tight text-foreground"
                            >
                                {{ milestonesRatio.reached }}/{{
                                    milestonesRatio.total
                                }}
                            </p>
                        </div>
                        <div
                            class="shrink-0 rounded-2xl bg-purple-500/10 p-3 text-purple-500"
                        >
                            <Trophy class="size-5" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- List of goals -->
            <div v-if="activeGoals.length" class="grid grid-cols-1 gap-6">
                <Card
                    v-for="goal in activeGoals"
                    :key="goal.id"
                    :class="[
                        'overflow-hidden border bg-card text-card-foreground shadow-xs transition-all duration-300 hover:shadow-md',
                        getPaceStatus(
                            goal.progress_percent,
                            getTimeProgress(goal.start_date, goal.end_date),
                        ).border,
                    ]"
                >
                    <CardContent class="space-y-6 p-6">
                        <!-- Top details -->
                        <div
                            class="flex flex-col justify-between gap-4 md:flex-row md:items-start"
                        >
                            <div class="min-w-0 space-y-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2
                                        class="truncate text-lg leading-tight font-extrabold text-foreground"
                                    >
                                        {{ goal.title }}
                                    </h2>
                                    <Badge
                                        variant="outline"
                                        class="border-amber-500/20 bg-amber-500/5 px-2 py-0.5 font-mono text-[9px] font-bold text-amber-500"
                                    >
                                        {{
                                            metricLabel[goal.metric] ||
                                            goal.metric
                                        }}
                                    </Badge>
                                    <Badge
                                        variant="outline"
                                        class="border-blue-500/20 bg-blue-500/5 px-2 py-0.5 font-mono text-[9px] font-bold text-blue-500"
                                    >
                                        {{
                                            periodLabel[goal.period] ||
                                            goal.period
                                        }}
                                    </Badge>
                                    <!-- Pace Badge Indicator -->
                                    <Badge
                                        :class="[
                                            'border-0 px-2 py-0.5 text-[9px] font-bold',
                                            getPaceStatus(
                                                goal.progress_percent,
                                                getTimeProgress(
                                                    goal.start_date,
                                                    goal.end_date,
                                                ),
                                            ).badge,
                                        ]"
                                    >
                                        {{
                                            getPaceStatus(
                                                goal.progress_percent,
                                                getTimeProgress(
                                                    goal.start_date,
                                                    goal.end_date,
                                                ),
                                            ).label
                                        }}
                                    </Badge>
                                </div>
                                <p
                                    class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                                >
                                    <span
                                        >Tiến độ hiện tại:
                                        <span
                                            class="font-bold text-foreground"
                                            >{{
                                                formatValue(
                                                    goal.metric,
                                                    Number(goal.current_value),
                                                )
                                            }}</span
                                        >
                                        /
                                        {{
                                            formatValue(
                                                goal.metric,
                                                Number(goal.target_value),
                                            )
                                        }}</span
                                    >
                                    <span>·</span>
                                    <span class="flex items-center gap-1">
                                        <Calendar
                                            class="size-3 text-muted-foreground/60"
                                        />
                                        Hạn cuối:
                                        <span
                                            class="font-semibold text-foreground/80"
                                            >{{
                                                new Date(
                                                    goal.end_date,
                                                ).toLocaleDateString('vi-VN')
                                            }}</span
                                        >
                                    </span>
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end">
                                <span
                                    :class="[
                                        'text-3xl font-black tracking-tight',
                                        getPaceStatus(
                                            goal.progress_percent,
                                            getTimeProgress(
                                                goal.start_date,
                                                goal.end_date,
                                            ),
                                        ).color,
                                    ]"
                                >
                                    {{ goal.progress_percent }}%
                                </span>
                            </div>
                        </div>

                        <!-- Progress tracking bar -->
                        <div class="space-y-1.5">
                            <div
                                class="relative h-2.5 overflow-hidden rounded-full bg-muted"
                            >
                                <!-- Actual Progress -->
                                <div
                                    :class="[
                                        'h-full rounded-full transition-all duration-500',
                                        progressColor(goal.progress_percent),
                                    ]"
                                    :style="{
                                        width:
                                            Math.min(
                                                100,
                                                goal.progress_percent,
                                            ) + '%',
                                    }"
                                ></div>
                                <!-- Time Elapsed Marker dot -->
                                <div
                                    v-if="goal.progress_percent < 100"
                                    class="absolute top-0 bottom-0 w-0.5 cursor-help bg-foreground/35 transition-colors hover:bg-foreground"
                                    :style="{
                                        left: `${getTimeProgress(goal.start_date, goal.end_date)}%`,
                                    }"
                                    :title="`Thời gian trôi qua: ${getTimeProgress(goal.start_date, goal.end_date)}%`"
                                ></div>
                            </div>
                            <div
                                class="flex justify-between text-[10px] font-semibold text-muted-foreground"
                            >
                                <span class="flex items-center gap-1">
                                    <Activity
                                        class="size-3 text-muted-foreground/70"
                                    />
                                    Hạn OKR còn:
                                    {{
                                        Math.max(
                                            0,
                                            Math.ceil(
                                                (new Date(
                                                    goal.end_date,
                                                ).getTime() -
                                                    new Date().getTime()) /
                                                    (1000 * 60 * 60 * 24),
                                            ),
                                        )
                                    }}
                                    ngày
                                </span>
                                <span
                                    >Thời gian đã trôi qua:
                                    {{
                                        getTimeProgress(
                                            goal.start_date,
                                            goal.end_date,
                                        )
                                    }}%</span
                                >
                            </div>
                        </div>

                        <!-- Horizontal Milestones Step Indicator -->
                        <div
                            v-if="goal.milestones?.length"
                            class="space-y-2.5 pt-1"
                        >
                            <span
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Cột mốc chiến lược (Milestones)</span
                            >
                            <div class="relative h-12 w-full select-none">
                                <!-- Connecting progress bar track -->
                                <div
                                    class="absolute top-[10px] right-2 left-2 h-1 rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-amber-500 to-emerald-500 transition-all duration-500"
                                        :style="{
                                            width: `${Math.min(goal.progress_percent, 100)}%`,
                                        }"
                                    ></div>
                                </div>

                                <!-- Step milestones dots positioned absolutely -->
                                <div
                                    v-for="m in goal.milestones"
                                    :key="m.id"
                                    class="absolute flex flex-col items-center gap-1"
                                    :style="{
                                        left: `clamp(12px, ${m.threshold_percent}%, calc(100% - 12px))`,
                                        transform:
                                            m.threshold_percent >= 80
                                                ? 'translateX(-85%)'
                                                : m.threshold_percent <= 20
                                                  ? 'translateX(-15%)'
                                                  : 'translateX(-50%)',
                                    }"
                                >
                                    <!-- Step Dot -->
                                    <div
                                        :class="[
                                            'flex h-5 w-5 items-center justify-center rounded-full border transition-all duration-300',
                                            m.reached
                                                ? 'scale-110 border-emerald-500 bg-emerald-500 text-white shadow-[0_0_8px_rgba(16,185,129,0.3)]'
                                                : 'border-border bg-card text-muted-foreground/60',
                                        ]"
                                    >
                                        <Trophy
                                            v-if="m.reached"
                                            class="size-2.5 text-white"
                                        />
                                        <Flag v-else class="size-2.5" />
                                    </div>
                                    <!-- Title label -->
                                    <span
                                        :class="[
                                            'max-w-[130px] truncate rounded-md border bg-card/95 px-2 py-0.5 text-[9px] font-bold shadow-xs sm:max-w-[190px]',
                                            m.reached
                                                ? 'border-emerald-500/30 text-emerald-500 dark:border-emerald-500/40 dark:text-emerald-400'
                                                : 'border-border/60 text-muted-foreground',
                                        ]"
                                        :title="`${m.title || 'Mốc'} (${m.threshold_percent}%)`"
                                    >
                                        {{ m.title || 'Mốc' }} ({{
                                            m.threshold_percent
                                        }}%)
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions Action Plan Checklist -->
                        <div class="space-y-3.5 border-t border-border/80 pt-5">
                            <div class="flex items-center justify-between">
                                <span
                                    class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <CheckCircle2
                                        class="size-4 text-muted-foreground/80"
                                    />
                                    Kế hoạch hành động
                                </span>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 cursor-pointer gap-1 rounded-lg border border-border px-3 text-xs font-bold hover:bg-muted"
                                    @click="openActionDialog(goal.id)"
                                >
                                    <Plus class="size-3.5" /> Thêm hành động
                                </Button>
                            </div>

                            <!-- Empty Actions -->
                            <div
                                v-if="
                                    !goal.actions || goal.actions.length === 0
                                "
                                class="flex items-center justify-center gap-2 rounded-xl border border-dashed border-border/80 bg-muted/5 px-4 py-3 text-xs text-muted-foreground italic"
                            >
                                <Inbox
                                    class="size-4 text-muted-foreground/60"
                                />
                                Chưa có hành động nào được tạo cho mục tiêu này.
                            </div>

                            <!-- Actions List -->
                            <div
                                v-else
                                class="grid grid-cols-1 gap-2 md:grid-cols-2"
                            >
                                <div
                                    v-for="action in goal.actions"
                                    :key="action.id"
                                    class="flex items-center justify-between gap-3 rounded-xl border border-border/40 bg-muted/5 p-3 text-sm transition-all hover:bg-muted/15"
                                    :class="{
                                        'border-emerald-500/10 bg-emerald-500/5 opacity-80':
                                            action.status === 'done',
                                    }"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <button
                                            @click="toggleAction(action.id)"
                                            class="group shrink-0 cursor-pointer text-muted-foreground transition-colors hover:text-emerald-500"
                                        >
                                            <CheckCircle2
                                                v-if="action.status === 'done'"
                                                class="size-5 animate-pulse text-emerald-500"
                                            />
                                            <Circle
                                                v-else
                                                class="size-5 transition-transform group-hover:scale-110"
                                            />
                                        </button>
                                        <span
                                            :class="[
                                                'truncate text-xs transition-all',
                                                action.status === 'done'
                                                    ? 'font-normal text-muted-foreground line-through'
                                                    : 'font-bold text-foreground',
                                            ]"
                                            >{{ action.title }}</span
                                        >
                                    </div>

                                    <!-- Deadline and Assignee -->
                                    <div
                                        class="ml-2 flex shrink-0 items-center gap-2"
                                    >
                                        <span
                                            v-if="action.due_date"
                                            class="rounded border border-border/40 bg-muted/40 px-1.5 py-0.5 font-mono text-[9px] font-bold text-muted-foreground"
                                        >
                                            Hạn:
                                            {{
                                                new Date(
                                                    action.due_date,
                                                ).toLocaleDateString('vi-VN')
                                            }}
                                        </span>
                                        <span
                                            v-if="action.assignee"
                                            class="rounded-full border border-border/80 bg-muted px-2 py-0.5 text-[9px] font-bold text-foreground"
                                        >
                                            👤 {{ action.assignee.name }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Premium Empty State -->
            <div
                v-else
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border/80 bg-muted/5 px-4 py-16 text-center"
            >
                <div
                    class="relative mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-500 ring-8 ring-amber-500/5"
                >
                    <Target class="size-8" />
                    <div
                        class="absolute -top-1 -right-1 h-3.5 w-3.5 animate-pulse rounded-full border-2 border-background bg-emerald-500"
                    ></div>
                </div>
                <h3 class="text-base font-bold text-foreground">
                    Chưa có mục tiêu & OKR nào
                </h3>
                <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                    Thiết lập các mục tiêu kinh doanh chiến lược (Doanh thu, Đơn
                    hàng, v.v.) và kế hoạch hành động để thúc đẩy tăng trưởng
                    ngay hôm nay.
                </p>
                <Button
                    @click="showCreateDialog = true"
                    class="mt-5 cursor-pointer rounded-xl bg-amber-600 text-xs font-bold text-white shadow-sm transition hover:bg-amber-700 active:scale-95"
                >
                    Tạo mục tiêu đầu tiên
                </Button>
            </div>
        </div>

        <!-- History tab -->
        <div v-if="activeTab === 'history'">
            <Card
                class="border border-border bg-card text-card-foreground shadow-sm"
            >
                <div
                    class="flex items-center gap-3 border-b border-border/60 p-5"
                >
                    <div
                        class="shrink-0 rounded-xl bg-amber-500/10 p-2.5 text-amber-500"
                    >
                        <Trophy class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-foreground">
                            Lịch sử mục tiêu OKR
                        </h3>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Danh sách các mục tiêu đã hoàn thành hoặc hết hạn
                            chu kỳ.
                        </p>
                    </div>
                </div>
                <CardContent class="p-5">
                    <div
                        v-if="history.length"
                        class="overflow-hidden rounded-xl border border-border/80 bg-muted/5 shadow-xs"
                    >
                        <div class="overflow-x-auto">
                            <table
                                class="w-full border-collapse text-left text-xs"
                            >
                                <thead>
                                    <tr
                                        class="border-b border-border bg-muted/30 font-semibold text-muted-foreground"
                                    >
                                        <th class="px-5 py-3.5">Mục tiêu</th>
                                        <th class="px-5 py-3.5">
                                            Đo lường / Chu kỳ
                                        </th>
                                        <th class="px-5 py-3.5 text-center">
                                            Kết quả đạt được
                                        </th>
                                        <th class="px-5 py-3.5">Trạng thái</th>
                                        <th class="px-5 py-3.5">
                                            Ngày hoàn thành
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60">
                                    <tr
                                        v-for="g in history"
                                        :key="g.id"
                                        class="transition-all duration-150 hover:bg-muted/15"
                                    >
                                        <td
                                            class="px-5 py-3.5 font-bold text-foreground"
                                        >
                                            {{ g.title }}
                                        </td>
                                        <td
                                            class="px-5 py-3.5 text-muted-foreground"
                                        >
                                            <div
                                                class="flex flex-wrap items-center gap-1.5"
                                            >
                                                <Badge
                                                    variant="outline"
                                                    class="border-border bg-background px-1.5 py-0 font-mono text-[9px] font-bold text-muted-foreground"
                                                >
                                                    {{
                                                        metricLabel[g.metric] ||
                                                        g.metric
                                                    }}
                                                </Badge>
                                                <Badge
                                                    variant="outline"
                                                    class="border-border bg-background px-1.5 py-0 font-mono text-[9px] font-bold text-muted-foreground"
                                                >
                                                    {{
                                                        periodLabel[g.period] ||
                                                        g.period
                                                    }}
                                                </Badge>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5 text-center">
                                            <span
                                                :class="[
                                                    'text-sm font-extrabold',
                                                    g.percent >= 100
                                                        ? 'text-emerald-500 dark:text-emerald-400'
                                                        : 'text-rose-500',
                                                ]"
                                            >
                                                {{ g.percent }}%
                                            </span>
                                            <p
                                                class="mt-0.5 text-[10px] text-muted-foreground"
                                            >
                                                {{
                                                    formatValue(
                                                        g.metric,
                                                        g.achieved,
                                                    )
                                                }}
                                                /
                                                {{
                                                    formatValue(
                                                        g.metric,
                                                        g.target,
                                                    )
                                                }}
                                            </p>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <Badge
                                                :variant="
                                                    g.status === 'completed'
                                                        ? 'secondary'
                                                        : 'destructive'
                                                "
                                                class="border-0 px-2.5 py-0.5 font-mono text-[10px] font-semibold"
                                                :class="
                                                    g.status === 'completed'
                                                        ? 'bg-emerald-500/10 text-emerald-500'
                                                        : 'bg-rose-500/10 text-rose-500'
                                                "
                                            >
                                                {{
                                                    g.status === 'completed'
                                                        ? 'ĐẠT'
                                                        : 'KHÔNG ĐẠT'
                                                }}
                                            </Badge>
                                        </td>
                                        <td
                                            class="px-5 py-3.5 font-mono font-medium text-muted-foreground"
                                        >
                                            {{ g.end_date }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex w-full flex-col items-center justify-center py-10 text-center text-muted-foreground/60"
                    >
                        <Inbox class="mb-2 size-6 text-muted-foreground/30" />
                        <p class="text-xs font-semibold">
                            Chưa có lịch sử mục tiêu hoàn thành.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Create goal dialog -->
    <Dialog v-model:open="showCreateDialog">
        <DialogContent
            class="max-h-[85vh] max-w-lg overflow-y-auto rounded-2xl"
        >
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-lg font-black">
                    <Target class="size-5 text-amber-500" />
                    Tạo Mục Tiêu OKR Mới
                </DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitGoal" class="space-y-5 pt-2">
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold text-foreground"
                        >Tiêu đề mục tiêu</Label
                    >
                    <Input
                        v-model="goalForm.title"
                        placeholder="Ví dụ: Đạt doanh thu 100 triệu VND tháng này"
                        required
                        class="rounded-xl"
                    />
                </div>

                <div class="space-y-2">
                    <Label class="text-xs font-bold text-foreground"
                        >Chỉ số đo lường (Metric)</Label
                    >
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                        <button
                            v-for="opt in metricOptions"
                            :key="opt.value"
                            type="button"
                            @click="goalForm.metric = opt.value"
                            class="flex cursor-pointer flex-col items-center justify-center rounded-xl border p-3 text-center transition-all duration-200"
                            :class="
                                goalForm.metric === opt.value
                                    ? 'border-amber-500 bg-amber-500/10 font-bold text-amber-500 shadow-xs'
                                    : 'border-border bg-muted/20 text-muted-foreground hover:bg-muted/40 hover:text-foreground'
                            "
                        >
                            <component :is="opt.icon" class="mb-1.5 size-5" />
                            <span class="text-xs leading-none font-bold">{{
                                opt.label
                            }}</span>
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label class="text-xs font-bold text-foreground"
                        >Chu kỳ (Period)</Label
                    >
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="opt in periodOptions"
                            :key="opt.value"
                            type="button"
                            @click="goalForm.period = opt.value"
                            class="cursor-pointer rounded-xl border px-3.5 py-2 text-xs font-bold transition-all"
                            :class="
                                goalForm.period === opt.value
                                    ? 'border-amber-500 bg-amber-500/10 text-amber-500'
                                    : 'border-border bg-muted/20 text-muted-foreground hover:bg-muted/40'
                            "
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold text-foreground"
                            >Ngày bắt đầu</Label
                        >
                        <Input
                            type="date"
                            v-model="goalForm.start_date"
                            required
                            class="rounded-xl"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold text-foreground"
                            >Ngày kết thúc</Label
                        >
                        <Input
                            type="date"
                            v-model="goalForm.end_date"
                            required
                            class="rounded-xl"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold text-foreground">
                            👤 Người chịu trách nhiệm (Owner)
                        </Label>
                        <Input
                            type="text"
                            v-model="goalForm.owner_name"
                            placeholder="VD: Nguyễn Văn A (Quản lý)..."
                            class="rounded-xl"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold text-foreground">
                            📐 Đơn vị đo lường (Unit)
                        </Label>
                        <Input
                            type="text"
                            v-model="goalForm.unit_name"
                            placeholder="VD: VNĐ, Đơn hàng, Điểm..."
                            class="rounded-xl"
                        />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold text-foreground"
                        >Giá trị mục tiêu hướng tới (Target)</Label
                    >
                    <Input
                        type="number"
                        step="0.01"
                        v-model="goalForm.target_value"
                        required
                        class="rounded-xl"
                    />
                </div>

                <!-- Milestones Editor -->
                <div class="space-y-3 border-t border-border/80 pt-4">
                    <div class="flex items-center justify-between">
                        <Label
                            class="flex items-center gap-1 text-xs font-bold text-foreground"
                        >
                            <Trophy class="size-4 text-purple-500" />
                            Cột mốc hoàn thành (Milestones)
                        </Label>
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            @click="addMilestone"
                            class="h-7 gap-1 rounded-lg px-2.5 text-xs font-bold"
                        >
                            <Plus class="size-3" /> Thêm mốc
                        </Button>
                    </div>

                    <div class="max-h-40 space-y-2 overflow-y-auto pr-1">
                        <div
                            v-for="(m, idx) in goalForm.milestones"
                            :key="idx"
                            class="flex items-center gap-2 rounded-xl border border-border/40 bg-muted/30 p-2"
                        >
                            <Input
                                v-model="m.title"
                                placeholder="Tiêu đề mốc (ví dụ: Đạt 50% chặng đường)"
                                class="h-9 flex-1 rounded-lg text-xs"
                                required
                            />
                            <div class="flex w-24 shrink-0 items-center gap-1">
                                <Input
                                    type="number"
                                    v-model="m.threshold_percent"
                                    class="h-9 w-16 rounded-lg text-center text-xs"
                                    min="1"
                                    max="100"
                                    required
                                />
                                <span
                                    class="text-xs font-bold text-muted-foreground"
                                    >%</span
                                >
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="removeMilestone(idx)"
                                class="h-9 w-9 shrink-0 cursor-pointer rounded-lg p-0 text-muted-foreground hover:bg-rose-500/10 hover:text-rose-500"
                            >
                                <Trash class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <DialogFooter class="border-t border-border/80 pt-4">
                    <Button
                        variant="outline"
                        type="button"
                        @click="showCreateDialog = false"
                        class="rounded-xl"
                        >Hủy</Button
                    >
                    <Button
                        type="submit"
                        :disabled="goalForm.processing"
                        class="rounded-xl bg-amber-600 font-bold text-white hover:bg-amber-700"
                    >
                        {{
                            goalForm.processing ? 'Đang tạo...' : 'Tạo mục tiêu'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Add action dialog -->
    <Dialog v-model:open="showActionDialog">
        <DialogContent class="max-w-md rounded-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-lg font-black">
                    <Plus class="size-5 text-amber-500" />
                    Thêm Hành Động Chiến Lược
                </DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitAction" class="space-y-4 pt-2">
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold text-foreground"
                        >Hành động cần làm</Label
                    >
                    <Input
                        v-model="actionForm.title"
                        placeholder="Ví dụ: Tổ chức chương trình khuyến mãi mua 1 tặng 1"
                        required
                        class="rounded-xl"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold text-foreground"
                        >Hạn chót hoàn thành (Due Date)</Label
                    >
                    <Input
                        type="date"
                        v-model="actionForm.due_date"
                        class="rounded-xl"
                    />
                </div>
                <DialogFooter class="border-t border-border/80 pt-4">
                    <Button
                        variant="outline"
                        type="button"
                        @click="showActionDialog = false"
                        class="rounded-xl"
                        >Hủy</Button
                    >
                    <Button
                        type="submit"
                        :disabled="actionForm.processing"
                        class="rounded-xl bg-amber-600 font-bold text-white hover:bg-amber-700"
                    >
                        {{
                            actionForm.processing
                                ? 'Đang thêm...'
                                : 'Thêm hành động'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
