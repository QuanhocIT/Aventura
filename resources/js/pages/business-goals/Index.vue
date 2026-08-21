<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    Calendar,
    CalendarClock,
    CheckCircle2,
    Circle,
    CircleAlert,
    ClipboardCheck,
    Clock3,
    Flag,
    Inbox,
    Lightbulb,
    ListChecks,
    Plus,
    RefreshCw,
    Rocket,
    Settings2,
    Target,
    Timer,
    Trash2,
    Trophy,
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

type Metric =
    | 'revenue'
    | 'orders'
    | 'customers'
    | 'rating'
    | 'cost_saving'
    | 'custom';
type PaceKey = 'on_track' | 'watch' | 'at_risk' | 'overdue' | 'completed';

type GoalAction = {
    id: number;
    title: string;
    description?: string | null;
    status: 'pending' | 'in_progress' | 'done';
    due_date?: string | null;
    assignee?: { id: number; name: string } | null;
};

type GoalMilestone = {
    id: number;
    title: string;
    threshold_percent: number;
    reached: boolean;
    reached_at?: string | null;
};

type Goal = {
    id: number;
    title: string;
    description?: string | null;
    owner_name?: string | null;
    unit_name?: string | null;
    metric: Metric;
    period: 'weekly' | 'monthly' | 'quarterly' | 'yearly';
    start_date: string;
    end_date: string;
    target_value: number;
    current_value: number;
    progress_percent: number;
    status: 'active' | 'completed' | 'failed' | 'cancelled';
    milestones?: GoalMilestone[];
    actions?: GoalAction[];
};

type HistoryGoal = {
    id: number;
    title: string;
    metric: Metric;
    period: string;
    target: number;
    achieved: number;
    percent: number;
    status: 'completed' | 'failed' | 'cancelled';
    end_date: string;
};

const props = defineProps<{ activeGoals: Goal[]; history: HistoryGoal[] }>();
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
    { deep: true },
);

const activeTab = ref<'active' | 'history'>('active');
type GoalFilter = 'all' | 'risk' | 'on_track';

const goalFilter = ref<GoalFilter>('all');
const isRefreshing = ref(false);
const today = new Date();
const todayKey = today.toISOString().slice(0, 10);

const metricLabel: Record<Metric, string> = {
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
    { value: 'revenue' as Metric, label: 'Doanh thu', icon: ClipboardCheck },
    { value: 'orders' as Metric, label: 'Đơn hàng', icon: ListChecks },
    { value: 'customers' as Metric, label: 'Khách mới', icon: Users },
    { value: 'rating' as Metric, label: 'Đánh giá', icon: Trophy },
    { value: 'cost_saving' as Metric, label: 'Tiết kiệm', icon: Rocket },
    { value: 'custom' as Metric, label: 'Tùy chỉnh', icon: Settings2 },
];

const periodOptions = [
    { value: 'weekly', label: 'Tuần' },
    { value: 'monthly', label: 'Tháng' },
    { value: 'quarterly', label: 'Quý' },
    { value: 'yearly', label: 'Năm' },
];

const paceMeta: Record<
    PaceKey,
    { label: string; badge: string; text: string; border: string; dot: string }
> = {
    on_track: {
        label: 'Đúng tiến độ',
        badge: 'border-sky-500/20 bg-sky-500/10 text-sky-600 dark:text-sky-300',
        text: 'text-sky-600 dark:text-sky-300',
        border: 'border-slate-200/80 dark:border-white/[0.08]',
        dot: 'bg-sky-500',
    },
    watch: {
        label: 'Cần theo dõi',
        badge: 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-300',
        text: 'text-amber-600 dark:text-amber-300',
        border: 'border-amber-500/25 dark:border-amber-500/25',
        dot: 'bg-amber-500',
    },
    at_risk: {
        label: 'Có nguy cơ trễ',
        badge: 'border-rose-500/20 bg-rose-500/10 text-rose-600 dark:text-rose-300',
        text: 'text-rose-600 dark:text-rose-300',
        border: 'border-rose-500/25 dark:border-rose-500/25',
        dot: 'bg-rose-500',
    },
    overdue: {
        label: 'Đã quá hạn',
        badge: 'border-rose-500/25 bg-rose-500/15 text-rose-700 dark:text-rose-200',
        text: 'text-rose-600 dark:text-rose-300',
        border: 'border-rose-500/35 dark:border-rose-500/35',
        dot: 'bg-rose-600',
    },
    completed: {
        label: 'Hoàn thành',
        badge: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-300',
        text: 'text-emerald-600 dark:text-emerald-300',
        border: 'border-emerald-500/25 dark:border-emerald-500/25',
        dot: 'bg-emerald-500',
    },
};

function formatNumber(
    value: number | string | null | undefined,
    fraction = 0,
): string {
    const number = Number(value ?? 0);

    if (!Number.isFinite(number)) {
        return '0';
    }

    return number.toLocaleString('vi-VN', {
        maximumFractionDigits: fraction,
        minimumFractionDigits: fraction,
    });
}

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return 'Chưa đặt';
    }

    const date = new Date(`${value.slice(0, 10)}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('vi-VN');
}

function formatValue(
    goal: Pick<Goal, 'metric' | 'unit_name'>,
    value: number | string,
): string {
    if (goal.metric === 'rating') {
        return `${formatNumber(value, 1)}/5`;
    }

    if (goal.metric === 'revenue' || goal.metric === 'cost_saving') {
        return `${formatNumber(value)} ₫`;
    }

    if (goal.metric === 'custom' && goal.unit_name) {
        return `${formatNumber(value)} ${goal.unit_name}`;
    }

    return formatNumber(value);
}

function getTimeProgress(goal: Pick<Goal, 'start_date' | 'end_date'>): number {
    const start = new Date(
        `${goal.start_date.slice(0, 10)}T00:00:00`,
    ).getTime();
    const end = new Date(`${goal.end_date.slice(0, 10)}T23:59:59`).getTime();
    const now = Date.now();

    if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) {
        return 0;
    }

    return Math.max(
        0,
        Math.min(100, Math.round(((now - start) / (end - start)) * 100)),
    );
}

function daysRemaining(goal: Pick<Goal, 'end_date'>): number {
    const end = new Date(`${goal.end_date.slice(0, 10)}T23:59:59`).getTime();

    return Math.ceil((end - Date.now()) / 86_400_000);
}

function getPace(goal: Goal): PaceKey {
    const progress = Number(goal.progress_percent ?? 0);

    if (progress >= 100) {
        return 'completed';
    }

    if (daysRemaining(goal) < 0) {
        return 'overdue';
    }

    const gap = getTimeProgress(goal) - progress;

    if (gap >= 15) {
        return 'at_risk';
    }

    if (gap >= 5) {
        return 'watch';
    }

    return 'on_track';
}

function actionSummary(goal: Goal): {
    done: number;
    total: number;
    overdue: number;
} {
    const actions = goal.actions ?? [];

    return {
        done: actions.filter((action) => action.status === 'done').length,
        total: actions.length,
        overdue: actions.filter(
            (action) =>
                action.status !== 'done' &&
                action.due_date &&
                action.due_date < todayKey,
        ).length,
    };
}

function milestoneSummary(goal: Goal): { reached: number; total: number } {
    const milestones = goal.milestones ?? [];

    return {
        reached: milestones.filter((milestone) => milestone.reached).length,
        total: milestones.length,
    };
}

function isActionOverdue(action: GoalAction): boolean {
    return (
        action.status !== 'done' &&
        Boolean(action.due_date && action.due_date < todayKey)
    );
}

function progressColor(goal: Goal): string {
    const pace = getPace(goal);

    if (pace === 'completed') {
        return 'bg-emerald-500';
    }

    if (pace === 'at_risk' || pace === 'overdue') {
        return 'bg-rose-500';
    }

    if (pace === 'watch') {
        return 'bg-amber-500';
    }

    return 'bg-sky-500';
}

const avgProgress = computed(() => {
    if (!props.activeGoals.length) {
        return 0;
    }

    return Math.round(
        props.activeGoals.reduce(
            (sum, goal) => sum + Number(goal.progress_percent || 0),
            0,
        ) / props.activeGoals.length,
    );
});

const actionsRatio = computed(() => {
    const actions = props.activeGoals.flatMap((goal) => goal.actions ?? []);
    const done = actions.filter((action) => action.status === 'done').length;

    return {
        done,
        total: actions.length,
        pct: actions.length ? Math.round((done / actions.length) * 100) : 0,
    };
});

const milestonesRatio = computed(() => {
    const milestones = props.activeGoals.flatMap(
        (goal) => goal.milestones ?? [],
    );
    const reached = milestones.filter((milestone) => milestone.reached).length;

    return {
        reached,
        total: milestones.length,
        pct: milestones.length
            ? Math.round((reached / milestones.length) * 100)
            : 0,
    };
});

const riskGoals = computed(() =>
    props.activeGoals.filter((goal) =>
        ['at_risk', 'overdue'].includes(getPace(goal)),
    ),
);
const overdueActionCount = computed(() =>
    props.activeGoals.reduce(
        (sum, goal) => sum + actionSummary(goal).overdue,
        0,
    ),
);
const timeProgressAverage = computed(() => {
    if (!props.activeGoals.length) {
        return 0;
    }

    return Math.round(
        props.activeGoals.reduce(
            (sum, goal) => sum + getTimeProgress(goal),
            0,
        ) / props.activeGoals.length,
    );
});

const executionLabel = computed(() => {
    if (!props.activeGoals.length) {
        return 'Chưa có mục tiêu';
    }

    if (riskGoals.value.length > 0) {
        return 'Cần can thiệp';
    }

    if (avgProgress.value >= timeProgressAverage.value) {
        return 'Đang bám kế hoạch';
    }

    return 'Cần đẩy nhanh';
});

const filteredGoals = computed(() => {
    return [...props.activeGoals]
        .filter((goal) => {
            if (goalFilter.value === 'risk') {
                return ['at_risk', 'overdue'].includes(getPace(goal));
            }

            if (goalFilter.value === 'on_track') {
                return ['on_track', 'watch'].includes(getPace(goal));
            }

            return true;
        })
        .sort((left, right) => {
            const priority: Record<PaceKey, number> = {
                overdue: 0,
                at_risk: 1,
                watch: 2,
                on_track: 3,
                completed: 4,
            };
            const paceDiff = priority[getPace(left)] - priority[getPace(right)];

            if (paceDiff !== 0) {
                return paceDiff;
            }

            return (
                new Date(left.end_date).getTime() -
                new Date(right.end_date).getTime()
            );
        });
});

const attentionItems = computed(() => {
    const items: {
        key: string;
        kind: 'goal' | 'action';
        title: string;
        detail: string;
        tone: 'danger' | 'warning';
        goalId?: number;
    }[] = [];

    riskGoals.value.slice(0, 3).forEach((goal) => {
        const pace = getPace(goal);
        items.push({
            key: `goal-${goal.id}`,
            kind: 'goal',
            title:
                pace === 'overdue'
                    ? `Mục tiêu đã quá hạn: ${goal.title}`
                    : `Mục tiêu có nguy cơ trễ: ${goal.title}`,
            detail: `${formatNumber(goal.progress_percent)}% hoàn thành • còn ${Math.max(0, daysRemaining(goal))} ngày`,
            tone: pace === 'overdue' ? 'danger' : 'warning',
            goalId: goal.id,
        });
    });

    props.activeGoals
        .flatMap((goal) =>
            (goal.actions ?? []).map((action) => ({ goal, action })),
        )
        .filter(({ action }) => isActionOverdue(action))
        .slice(0, 3)
        .forEach(({ goal, action }) => {
            items.push({
                key: `action-${action.id}`,
                kind: 'action',
                title: `Hành động quá hạn: ${action.title}`,
                detail: `Thuộc mục tiêu “${goal.title}” • hạn ${formatDate(action.due_date)}`,
                tone: 'danger',
                goalId: goal.id,
            });
        });

    return items.slice(0, 4);
});

// Create goal
const showCreateDialog = ref(false);
const initialStart = new Date().toISOString().slice(0, 10);
const initialEnd = new Date(Date.now() + 30 * 86_400_000)
    .toISOString()
    .slice(0, 10);
const goalForm = useForm({
    title: '',
    description: '',
    owner_name: '',
    unit_name: '',
    metric: 'revenue' as Metric,
    period: 'monthly',
    start_date: initialStart,
    end_date: initialEnd,
    target_value: 0,
    milestones: [
        { title: 'Đạt 50%', threshold_percent: 50 },
        { title: 'Đạt 100%', threshold_percent: 100 },
    ] as { title: string; threshold_percent: number }[],
});

function openCreateDialog(): void {
    goalForm.reset();
    goalForm.clearErrors();
    goalForm.start_date = initialStart;
    goalForm.end_date = initialEnd;
    goalForm.metric = 'revenue';
    goalForm.period = 'monthly';
    goalForm.milestones = [
        { title: 'Đạt 50%', threshold_percent: 50 },
        { title: 'Đạt 100%', threshold_percent: 100 },
    ];
    showCreateDialog.value = true;
}

function addMilestone(): void {
    goalForm.milestones.push({ title: '', threshold_percent: 75 });
}

function removeMilestone(index: number): void {
    if (goalForm.milestones.length <= 1) {
        return;
    }

    goalForm.milestones.splice(index, 1);
}

function submitGoal(): void {
    goalForm.post('/business-goals', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateDialog.value = false;
            toast.success('Đã tạo mục tiêu OKR.');
        },
    });
}

// Manual value for custom goals
const customValues = ref<Record<number, number>>({});
watch(
    () => props.activeGoals,
    (goals) => {
        goals
            .filter((goal) => goal.metric === 'custom')
            .forEach((goal) => {
                if (customValues.value[goal.id] === undefined) {
                    customValues.value[goal.id] = Number(
                        goal.current_value || 0,
                    );
                }
            });
    },
    { immediate: true, deep: true },
);

function saveCustomValue(goal: Goal): void {
    router.patch(
        `/business-goals/${goal.id}/value`,
        { current_value: Number(customValues.value[goal.id] ?? 0) },
        { preserveScroll: true },
    );
}

// Action plan
const showActionDialog = ref(false);
const actionGoalId = ref<number | null>(null);
const actionForm = useForm({ title: '', description: '', due_date: '' });

function openActionDialog(goalId: number): void {
    actionGoalId.value = goalId;
    actionForm.reset();
    actionForm.clearErrors();
    showActionDialog.value = true;
}

function submitAction(): void {
    if (!actionGoalId.value) {
        return;
    }

    actionForm.post(`/business-goals/${actionGoalId.value}/actions`, {
        preserveScroll: true,
        onSuccess: () => {
            showActionDialog.value = false;
            toast.success('Đã thêm hành động vào kế hoạch.');
        },
    });
}

function toggleAction(actionId: number): void {
    router.patch(
        `/business-goals/actions/${actionId}/toggle`,
        {},
        { preserveScroll: true },
    );
}

function deleteGoal(goal: Goal): void {
    if (
        !window.confirm(
            `Xóa mục tiêu “${goal.title}”? Các mốc và hành động liên quan cũng sẽ bị xóa.`,
        )
    ) {
        return;
    }

    router.delete(`/business-goals/${goal.id}`, { preserveScroll: true });
}

function refreshGoals(): void {
    if (isRefreshing.value) {
        return;
    }

    isRefreshing.value = true;
    router.get(
        '/business-goals',
        { refresh: 1 },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                isRefreshing.value = false;
                toast.success('Đã đồng bộ tiến độ mục tiêu.');
            },
        },
    );
}

function focusGoal(goalId?: number): void {
    if (!goalId) {
        return;
    }

    activeTab.value = 'active';
    goalFilter.value = 'all';
    window.setTimeout(
        () =>
            document
                .getElementById(`goal-${goalId}`)
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' }),
        0,
    );
}

function setGoalFilter(value: string): void {
    goalFilter.value = value === 'risk' || value === 'on_track' ? value : 'all';
}
</script>

<template>
    <Head title="Mục tiêu & OKR" />

    <div class="mx-auto w-full max-w-[1600px] space-y-5 p-4 lg:p-7">
        <header
            class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white shadow-sm dark:border-white/[0.08] dark:bg-slate-950/70"
        >
            <div class="relative px-5 py-6 lg:px-7">
                <div
                    class="absolute -top-28 -right-20 h-72 w-72 rounded-full bg-amber-500/[0.09] blur-3xl"
                />
                <div
                    class="relative flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-amber-500 text-slate-950 shadow-lg shadow-amber-500/20"
                        >
                            <Target class="size-7" />
                        </div>
                        <div>
                            <div
                                class="mb-1 flex items-center gap-2 text-[11px] font-bold tracking-[0.18em] text-amber-600 uppercase dark:text-amber-400"
                            >
                                <Rocket class="size-3.5" /> OKR / Execution
                                cockpit
                            </div>
                            <h1
                                class="text-2xl font-semibold tracking-tight text-slate-950 lg:text-3xl dark:text-white"
                            >
                                Biến mục tiêu thành hành động
                            </h1>
                            <p
                                class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                Theo dõi tiến độ, phát hiện mục tiêu có nguy cơ
                                trễ và điều phối kế hoạch hành động trên toàn
                                doanh nghiệp.
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="isRefreshing"
                            @click="refreshGoals"
                            ><RefreshCw
                                class="size-4"
                                :class="isRefreshing && 'animate-spin'"
                            />
                            Đồng bộ tiến độ</Button
                        >
                        <Button
                            variant="brand"
                            size="sm"
                            @click="openCreateDialog"
                            ><Plus class="size-4" /> Tạo mục tiêu mới</Button
                        >
                    </div>
                </div>
            </div>
            <div
                class="flex flex-col gap-3 border-t border-slate-200/80 bg-slate-50/70 px-5 py-3.5 text-xs sm:flex-row sm:items-center sm:justify-between lg:px-7 dark:border-white/[0.08] dark:bg-white/[0.03]"
            >
                <div
                    class="flex items-center gap-2 font-semibold text-slate-500 dark:text-slate-400"
                >
                    <span class="size-1.5 rounded-full bg-emerald-500" /> Mục
                    tiêu cấp doanh nghiệp
                    <span class="text-slate-300 dark:text-slate-600">•</span> Dữ
                    liệu được đồng bộ từ vận hành
                </div>
                <div class="flex items-center gap-1.5 text-slate-400">
                    <CalendarClock class="size-3.5" /> Hôm nay
                    {{ formatDate(todayKey) }}
                </div>
            </div>
        </header>

        <div
            class="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white p-2 shadow-sm lg:flex-row lg:items-center lg:justify-between dark:border-white/[0.08] dark:bg-slate-950/60"
        >
            <div class="flex gap-1 overflow-x-auto">
                <button
                    type="button"
                    class="flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition"
                    :class="
                        activeTab === 'active'
                            ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                            : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/[0.06]'
                    "
                    @click="activeTab = 'active'"
                >
                    <Flag class="size-4" /> Đang thực hiện
                    <span
                        class="rounded-full bg-white/15 px-1.5 py-0.5 text-[10px] dark:bg-black/10"
                        >{{ props.activeGoals.length }}</span
                    ></button
                ><button
                    type="button"
                    class="flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition"
                    :class="
                        activeTab === 'history'
                            ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                            : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/[0.06]'
                    "
                    @click="activeTab = 'history'"
                >
                    <Trophy class="size-4" /> Lịch sử
                    <span
                        class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] dark:bg-white/[0.08]"
                        >{{ props.history.length }}</span
                    >
                </button>
            </div>
            <div
                v-if="activeTab === 'active'"
                class="flex gap-1 overflow-x-auto rounded-xl bg-slate-50 p-1 dark:bg-white/[0.04]"
            >
                <button
                    v-for="filter in [
                        { key: 'all', label: 'Tất cả' },
                        { key: 'risk', label: 'Cần chú ý' },
                        { key: 'on_track', label: 'Đang bám kế hoạch' },
                    ]"
                    :key="filter.key"
                    type="button"
                    class="shrink-0 rounded-lg px-3 py-1.5 text-[11px] font-bold transition"
                    :class="
                        goalFilter === filter.key
                            ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-800 dark:text-white'
                            : 'text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                    "
                    @click="setGoalFilter(filter.key)"
                >
                    {{ filter.label
                    }}<span
                        v-if="filter.key === 'risk' && riskGoals.length"
                        class="ml-1 text-rose-500"
                        >{{ riskGoals.length }}</span
                    >
                </button>
            </div>
        </div>

        <section class="grid gap-4 xl:grid-cols-[1.35fr_0.65fr]">
            <div
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:p-6 dark:border-white/[0.08] dark:bg-slate-950/60"
            >
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-bold tracking-[0.14em] text-slate-400 uppercase"
                        >
                            Execution pulse
                        </p>
                        <h2
                            class="mt-1 text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            Nhịp thực thi mục tiêu
                        </h2>
                        <p
                            class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                        >
                            So sánh tiến độ thực tế với thời gian đã trôi qua
                            của các mục tiêu đang chạy.
                        </p>
                    </div>
                    <Badge
                        variant="outline"
                        :class="
                            riskGoals.length
                                ? 'border-amber-500/20 bg-amber-500/[0.06] text-amber-600 dark:text-amber-300'
                                : 'border-emerald-500/20 bg-emerald-500/[0.06] text-emerald-600 dark:text-emerald-300'
                        "
                        ><span
                            class="mr-1.5 size-1.5 rounded-full"
                            :class="
                                riskGoals.length
                                    ? 'bg-amber-500'
                                    : 'bg-emerald-500'
                            "
                        />
                        {{ executionLabel }}</Badge
                    >
                </div>
                <div
                    class="mt-7 flex flex-col gap-7 md:flex-row md:items-center"
                >
                    <div
                        class="relative flex size-36 shrink-0 items-center justify-center rounded-full"
                        :style="{
                            background: `conic-gradient(${riskGoals.length ? '#f59e0b' : '#0ea5e9'} ${avgProgress}%, rgba(148,163,184,.16) 0)`,
                        }"
                    >
                        <div
                            class="flex size-28 flex-col items-center justify-center rounded-full bg-white dark:bg-slate-950"
                        >
                            <span
                                class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white"
                                >{{ avgProgress }}%</span
                            ><span class="mt-1 text-[11px] text-slate-400"
                                >tiến độ TB</span
                            >
                        </div>
                    </div>
                    <div class="grid flex-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400">
                                Thời gian đã trôi qua
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"
                            >
                                {{ timeProgressAverage }}%
                            </p>
                            <div
                                class="mt-2 h-1.5 rounded-full bg-slate-100 dark:bg-white/[0.08]"
                            >
                                <div
                                    class="h-full rounded-full bg-slate-400"
                                    :style="{
                                        width: `${timeProgressAverage}%`,
                                    }"
                                />
                            </div>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400">
                                Mục tiêu cần can thiệp
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold"
                                :class="
                                    riskGoals.length
                                        ? 'text-rose-600 dark:text-rose-300'
                                        : 'text-emerald-600 dark:text-emerald-300'
                                "
                            >
                                {{ riskGoals.length }}
                            </p>
                            <p class="mt-2 text-[11px] text-slate-400">
                                {{ overdueActionCount }} hành động quá hạn
                            </p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400">
                                Hành động hoàn tất
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"
                            >
                                {{ actionsRatio.pct }}%
                            </p>
                            <p class="mt-2 text-[11px] text-slate-400">
                                {{ actionsRatio.done }}/{{
                                    actionsRatio.total || 0
                                }}
                                hành động
                            </p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400">
                                Mốc chiến lược
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"
                            >
                                {{ milestonesRatio.pct }}%
                            </p>
                            <p class="mt-2 text-[11px] text-slate-400">
                                {{ milestonesRatio.reached }}/{{
                                    milestonesRatio.total || 0
                                }}
                                mốc đạt được
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div
                class="rounded-2xl border border-amber-500/20 bg-amber-500/[0.05] p-5 shadow-sm lg:p-6 dark:bg-amber-500/[0.04]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-bold tracking-[0.14em] text-amber-600 uppercase dark:text-amber-300"
                        >
                            Focus queue
                        </p>
                        <h2
                            class="mt-1 text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            Bảng ưu tiên hôm nay
                        </h2>
                    </div>
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500"
                    >
                        <Lightbulb class="size-5" />
                    </div>
                </div>
                <div v-if="attentionItems.length" class="mt-5 space-y-3">
                    <button
                        v-for="item in attentionItems"
                        :key="item.key"
                        type="button"
                        class="flex w-full items-start gap-3 rounded-xl border bg-white/70 p-3 text-left transition hover:-translate-y-0.5 hover:shadow-sm dark:bg-slate-950/40"
                        :class="
                            item.tone === 'danger'
                                ? 'border-rose-500/15'
                                : 'border-amber-500/15'
                        "
                        @click="focusGoal(item.goalId)"
                    >
                        <span
                            class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg"
                            :class="
                                item.tone === 'danger'
                                    ? 'bg-rose-500/10 text-rose-500'
                                    : 'bg-amber-500/10 text-amber-500'
                            "
                            ><AlertTriangle
                                v-if="item.kind === 'goal'"
                                class="size-4" /><Clock3
                                v-else
                                class="size-4" /></span
                        ><span class="min-w-0"
                            ><span
                                class="block truncate text-xs font-bold text-slate-800 dark:text-slate-200"
                                >{{ item.title }}</span
                            ><span
                                class="mt-1 block truncate text-[11px] text-slate-500 dark:text-slate-400"
                                >{{ item.detail }}</span
                            ></span
                        ><ArrowRight
                            class="mt-1 size-4 shrink-0 text-slate-400"
                        />
                    </button>
                </div>
                <div
                    v-else
                    class="mt-6 flex min-h-36 flex-col items-center justify-center rounded-xl border border-dashed border-emerald-500/20 bg-emerald-500/[0.04] text-center"
                >
                    <CheckCircle2 class="size-8 text-emerald-500" />
                    <p
                        class="mt-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300"
                    >
                        Không có cảnh báo cần xử lý
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Các mục tiêu đang bám theo kế hoạch.
                    </p>
                </div>
            </div>
        </section>

        <section v-if="activeTab === 'active'" class="space-y-5">
            <div v-if="filteredGoals.length" class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="goal in filteredGoals"
                    :id="`goal-${goal.id}`"
                    :key="goal.id"
                    class="overflow-hidden rounded-2xl border bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:bg-slate-950/60"
                    :class="paceMeta[getPace(goal)].border"
                >
                    <div class="p-5 lg:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-start gap-3">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                                    :class="
                                        getPace(goal) === 'overdue' ||
                                        getPace(goal) === 'at_risk'
                                            ? 'bg-rose-500/10 text-rose-500'
                                            : 'bg-sky-500/10 text-sky-500'
                                    "
                                >
                                    <Target class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <div
                                        class="flex flex-wrap items-center gap-1.5"
                                    >
                                        <Badge
                                            variant="outline"
                                            class="border-slate-200 text-[10px] dark:border-white/[0.1]"
                                            >{{
                                                metricLabel[goal.metric]
                                            }}</Badge
                                        ><Badge
                                            variant="outline"
                                            class="border-slate-200 text-[10px] dark:border-white/[0.1]"
                                            >{{
                                                periodLabel[goal.period]
                                            }}</Badge
                                        ><Badge
                                            variant="outline"
                                            class="text-[10px]"
                                            :class="
                                                paceMeta[getPace(goal)].badge
                                            "
                                            >{{
                                                paceMeta[getPace(goal)].label
                                            }}</Badge
                                        >
                                    </div>
                                    <h3
                                        class="mt-2 line-clamp-2 text-base leading-5 font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ goal.title }}
                                    </h3>
                                    <p
                                        v-if="goal.description"
                                        class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        {{ goal.description }}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-500/10 hover:text-rose-500"
                                title="Xóa mục tiêu"
                                @click="deleteGoal(goal)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                        <div class="mt-6 flex items-end justify-between gap-3">
                            <div>
                                <p
                                    class="text-[11px] font-semibold text-slate-400"
                                >
                                    Kết quả hiện tại
                                </p>
                                <p
                                    class="mt-1 text-xl font-semibold text-slate-950 dark:text-white"
                                >
                                    {{ formatValue(goal, goal.current_value) }}
                                    <span
                                        class="text-sm font-normal text-slate-400"
                                        >/
                                        {{
                                            formatValue(goal, goal.target_value)
                                        }}</span
                                    >
                                </p>
                            </div>
                            <p
                                class="text-3xl font-semibold tracking-tight"
                                :class="paceMeta[getPace(goal)].text"
                            >
                                {{ formatNumber(goal.progress_percent) }}%
                            </p>
                        </div>
                        <div class="mt-4 space-y-2">
                            <div
                                class="relative h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/[0.08]"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="progressColor(goal)"
                                    :style="{
                                        width: `${Math.min(100, Math.max(0, goal.progress_percent))}%`,
                                    }"
                                />
                                <div
                                    v-if="goal.progress_percent < 100"
                                    class="absolute top-0 bottom-0 w-0.5 bg-slate-900/50 dark:bg-white/50"
                                    :style="{
                                        left: `${getTimeProgress(goal)}%`,
                                    }"
                                    title="Mốc thời gian đã trôi qua"
                                />
                            </div>
                            <div
                                class="flex items-center justify-between text-[11px] text-slate-400"
                            >
                                <span class="inline-flex items-center gap-1.5"
                                    ><Timer class="size-3.5" />{{
                                        daysRemaining(goal) < 0
                                            ? `Quá hạn ${Math.abs(daysRemaining(goal))} ngày`
                                            : `Còn ${daysRemaining(goal)} ngày`
                                    }}</span
                                ><span
                                    >Thời gian:
                                    {{ getTimeProgress(goal) }}%</span
                                >
                            </div>
                        </div>
                        <div
                            class="mt-5 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3.5 dark:bg-white/[0.04]"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase"
                                >
                                    Hạn cuối
                                </p>
                                <p
                                    class="mt-1 flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-200"
                                >
                                    <Calendar
                                        class="size-3.5 text-slate-400"
                                    />{{ formatDate(goal.end_date) }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase"
                                >
                                    Người phụ trách
                                </p>
                                <p
                                    class="mt-1 flex items-center gap-1.5 truncate text-xs font-bold text-slate-700 dark:text-slate-200"
                                >
                                    <Users class="size-3.5 text-slate-400" />{{
                                        goal.owner_name || 'Chưa phân công'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="goal.metric === 'custom'"
                            class="mt-4 flex items-end gap-2 rounded-xl border border-violet-500/15 bg-violet-500/[0.04] p-3"
                        >
                            <label class="min-w-0 flex-1"
                                ><span
                                    class="text-[11px] font-semibold text-violet-700 dark:text-violet-300"
                                    >Cập nhật số thực tế</span
                                ><Input
                                    v-model.number="customValues[goal.id]"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 h-9 rounded-lg bg-white text-sm dark:bg-slate-950" /></label
                            ><Button
                                size="sm"
                                variant="outline"
                                class="h-9 shrink-0 border-violet-500/20 text-xs text-violet-600 hover:bg-violet-500/10 dark:text-violet-300"
                                @click="saveCustomValue(goal)"
                                >Lưu số</Button
                            >
                        </div>

                        <div v-if="goal.milestones?.length" class="mt-5">
                            <div class="mb-2 flex items-center justify-between">
                                <span
                                    class="text-[10px] font-bold tracking-[0.14em] text-slate-400 uppercase"
                                    >Cột mốc chiến lược</span
                                ><span
                                    class="text-[11px] font-semibold text-slate-500"
                                    >{{ milestoneSummary(goal).reached }}/{{
                                        milestoneSummary(goal).total
                                    }}
                                    đạt</span
                                >
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="milestone in goal.milestones"
                                    :key="milestone.id"
                                    class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[10px] font-bold"
                                    :class="
                                        milestone.reached
                                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-300'
                                            : 'border-slate-200 bg-slate-50 text-slate-500 dark:border-white/[0.1] dark:bg-white/[0.04] dark:text-slate-400'
                                    "
                                    ><CheckCircle2
                                        v-if="milestone.reached"
                                        class="size-3"
                                    /><Circle v-else class="size-3" />{{
                                        milestone.title ||
                                        `Mốc ${milestone.threshold_percent}%`
                                    }}
                                    <span class="opacity-70"
                                        >{{
                                            milestone.threshold_percent
                                        }}%</span
                                    ></span
                                >
                            </div>
                        </div>

                        <div
                            class="mt-5 border-t border-slate-200/80 pt-4 dark:border-white/[0.08]"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <ListChecks
                                        class="size-4 text-slate-400"
                                    /><span
                                        class="text-xs font-bold text-slate-700 dark:text-slate-200"
                                        >Kế hoạch hành động</span
                                    ><Badge
                                        variant="outline"
                                        class="border-slate-200 text-[10px] dark:border-white/[0.1]"
                                        >{{ actionSummary(goal).done }}/{{
                                            actionSummary(goal).total
                                        }}</Badge
                                    >
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 gap-1 rounded-lg px-2 text-[11px] font-bold text-amber-600 hover:bg-amber-500/10 dark:text-amber-300"
                                    @click="openActionDialog(goal.id)"
                                    ><Plus class="size-3.5" /> Thêm</Button
                                >
                            </div>
                            <div
                                v-if="goal.actions?.length"
                                class="mt-3 space-y-2"
                            >
                                <div
                                    v-for="action in goal.actions.slice(0, 4)"
                                    :key="action.id"
                                    class="flex items-center gap-2.5 rounded-lg border p-2.5"
                                    :class="
                                        action.status === 'done'
                                            ? 'border-emerald-500/15 bg-emerald-500/[0.04]'
                                            : isActionOverdue(action)
                                              ? 'border-rose-500/20 bg-rose-500/[0.04]'
                                              : 'border-slate-200/70 bg-slate-50/40 dark:border-white/[0.08] dark:bg-white/[0.02]'
                                    "
                                >
                                    <button
                                        type="button"
                                        class="shrink-0 text-slate-400 transition hover:text-emerald-500"
                                        :aria-label="
                                            action.status === 'done'
                                                ? 'Đánh dấu chưa xong'
                                                : 'Đánh dấu hoàn thành'
                                        "
                                        @click="toggleAction(action.id)"
                                    >
                                        <CheckCircle2
                                            v-if="action.status === 'done'"
                                            class="size-4 text-emerald-500"
                                        /><Circle
                                            v-else
                                            class="size-4"
                                        /></button
                                    ><span
                                        class="min-w-0 flex-1 truncate text-xs"
                                        :class="
                                            action.status === 'done'
                                                ? 'text-slate-400 line-through'
                                                : 'font-semibold text-slate-700 dark:text-slate-200'
                                        "
                                        >{{ action.title }}</span
                                    ><span
                                        v-if="action.due_date"
                                        class="shrink-0 text-[10px] font-semibold"
                                        :class="
                                            isActionOverdue(action)
                                                ? 'text-rose-500'
                                                : 'text-slate-400'
                                        "
                                        >{{ formatDate(action.due_date) }}</span
                                    >
                                </div>
                                <p
                                    v-if="goal.actions.length > 4"
                                    class="text-center text-[11px] text-slate-400"
                                >
                                    + {{ goal.actions.length - 4 }} hành động
                                    khác
                                </p>
                            </div>
                            <div
                                v-else
                                class="mt-3 flex items-center gap-2 rounded-lg border border-dashed border-slate-200 px-3 py-3 text-xs text-slate-400 dark:border-white/[0.1]"
                            >
                                <Inbox class="size-4" /> Chưa có hành động. Hãy
                                tạo bước tiếp theo để mục tiêu có thể chạy.
                            </div>
                        </div>
                    </div>
                </article>
            </div>
            <div
                v-else
                class="flex min-h-72 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white text-center dark:border-white/[0.1] dark:bg-slate-950/40"
            >
                <CircleAlert
                    class="size-9 text-slate-300 dark:text-slate-600"
                />
                <h3
                    class="mt-3 text-base font-semibold text-slate-600 dark:text-slate-300"
                >
                    {{
                        props.activeGoals.length
                            ? 'Không có mục tiêu phù hợp bộ lọc'
                            : 'Chưa có mục tiêu đang chạy'
                    }}
                </h3>
                <p class="mt-1 max-w-md text-xs leading-5 text-slate-400">
                    {{
                        props.activeGoals.length
                            ? 'Thử chuyển về “Tất cả” để xem toàn bộ mục tiêu.'
                            : 'Tạo mục tiêu đầu tiên, đặt cột mốc và gắn hành động để bắt đầu quản trị thực thi.'
                    }}
                </p>
                <Button
                    v-if="!props.activeGoals.length"
                    variant="brand"
                    size="sm"
                    class="mt-5"
                    @click="openCreateDialog"
                    ><Plus class="size-4" /> Tạo mục tiêu đầu tiên</Button
                >
            </div>
        </section>

        <section
            v-else
            class="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
        >
            <div
                class="flex flex-col gap-2 border-b border-slate-200/80 p-5 sm:flex-row sm:items-start sm:justify-between lg:p-6 dark:border-white/[0.08]"
            >
                <div>
                    <p
                        class="text-xs font-bold tracking-[0.14em] text-slate-400 uppercase"
                    >
                        Outcome archive
                    </p>
                    <h2
                        class="mt-1 text-lg font-semibold text-slate-900 dark:text-white"
                    >
                        Lịch sử mục tiêu OKR
                    </h2>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        Đánh giá kết quả các chu kỳ đã kết thúc để cải thiện
                        việc đặt mục tiêu kế tiếp.
                    </p>
                </div>
                <Badge
                    variant="outline"
                    class="border-slate-200 dark:border-white/[0.1]"
                    >{{ props.history.length }} chu kỳ</Badge
                >
            </div>
            <div v-if="props.history.length" class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-xs">
                    <thead>
                        <tr
                            class="border-b border-slate-200 text-[10px] font-bold tracking-wide text-slate-400 uppercase dark:border-white/[0.08]"
                        >
                            <th class="px-5 py-3">Mục tiêu</th>
                            <th class="px-5 py-3">Chỉ số</th>
                            <th class="px-5 py-3">Kết quả</th>
                            <th class="px-5 py-3">Kỳ kết thúc</th>
                            <th class="px-5 py-3 text-right">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="goal in props.history"
                            :key="goal.id"
                            class="border-b border-slate-100 last:border-0 dark:border-white/[0.06]"
                        >
                            <td class="px-5 py-4">
                                <p
                                    class="font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    {{ goal.title }}
                                </p>
                                <p class="mt-1 text-[11px] text-slate-400">
                                    {{
                                        periodLabel[goal.period] || goal.period
                                    }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-slate-500">
                                {{ metricLabel[goal.metric] || goal.metric }}
                            </td>
                            <td class="px-5 py-4">
                                <p
                                    class="font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    {{ formatNumber(goal.percent) }}%
                                </p>
                                <p class="mt-1 text-[11px] text-slate-400">
                                    {{ formatNumber(goal.achieved) }} /
                                    {{ formatNumber(goal.target) }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-slate-500">
                                {{ goal.end_date }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <Badge
                                    variant="outline"
                                    :class="
                                        goal.status === 'completed'
                                            ? paceMeta.completed.badge
                                            : 'border-rose-500/20 bg-rose-500/10 text-rose-600 dark:text-rose-300'
                                    "
                                    >{{
                                        goal.status === 'completed'
                                            ? 'Hoàn thành'
                                            : 'Chưa đạt'
                                    }}</Badge
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-else
                class="flex min-h-56 flex-col items-center justify-center text-center"
            >
                <Trophy class="size-9 text-slate-300 dark:text-slate-600" />
                <p class="mt-3 text-sm font-semibold text-slate-500">
                    Chưa có lịch sử mục tiêu
                </p>
                <p class="mt-1 text-xs text-slate-400">
                    Các mục tiêu hoàn thành hoặc kết thúc sẽ xuất hiện ở đây.
                </p>
            </div>
        </section>

        <footer
            class="flex flex-col gap-2 border-t border-slate-200/80 px-1 pt-4 text-[11px] text-slate-400 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.08]"
        >
            <span class="inline-flex items-center gap-1.5"
                ><Activity class="size-3.5" /> Tiến độ tự động lấy từ dữ liệu
                vận hành; mục tiêu tùy chỉnh cần cập nhật thủ công.</span
            ><span>OKR cấp doanh nghiệp • Cập nhật lần cuối khi đồng bộ</span>
        </footer>
    </div>

    <Dialog v-model:open="showCreateDialog"
        ><DialogContent
            class="max-h-[88vh] max-w-2xl overflow-y-auto rounded-2xl"
            ><DialogHeader
                ><DialogTitle
                    class="flex items-center gap-2 text-lg font-semibold"
                    ><Target class="size-5 text-amber-500" /> Tạo mục tiêu OKR
                    mới</DialogTitle
                ></DialogHeader
            >
            <form class="space-y-5 pt-2" @submit.prevent="submitGoal">
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold">Tên mục tiêu</Label
                    ><Input
                        v-model="goalForm.title"
                        placeholder="Ví dụ: Tăng doanh thu quý 3"
                        required
                        class="rounded-xl"
                    />
                    <p
                        v-if="goalForm.errors.title"
                        class="text-xs text-rose-500"
                    >
                        {{ goalForm.errors.title }}
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold"
                        >Mô tả / kết quả cần đạt</Label
                    ><textarea
                        v-model="goalForm.description"
                        rows="2"
                        class="w-full rounded-xl border border-slate-200 bg-transparent px-3 py-2 text-sm outline-none focus:border-amber-500 dark:border-white/[0.1]"
                        placeholder="Nêu rõ kết quả kinh doanh và cách đo lường..."
                    />
                </div>
                <div class="space-y-2">
                    <Label class="text-xs font-bold">Chỉ số đo lường</Label>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <button
                            v-for="option in metricOptions"
                            :key="option.value"
                            type="button"
                            class="flex flex-col items-center justify-center rounded-xl border p-3 text-center transition"
                            :class="
                                goalForm.metric === option.value
                                    ? 'border-amber-500 bg-amber-500/10 text-amber-600 dark:text-amber-300'
                                    : 'border-slate-200 bg-slate-50/50 text-slate-500 hover:bg-slate-100 dark:border-white/[0.1] dark:bg-white/[0.03] dark:text-slate-400'
                            "
                            @click="goalForm.metric = option.value"
                        >
                            <component
                                :is="option.icon"
                                class="mb-1.5 size-5"
                            /><span class="text-xs font-bold">{{
                                option.label
                            }}</span>
                        </button>
                    </div>
                </div>
                <div class="space-y-2">
                    <Label class="text-xs font-bold">Chu kỳ</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in periodOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-xl border px-3.5 py-2 text-xs font-bold transition"
                            :class="
                                goalForm.period === option.value
                                    ? 'border-amber-500 bg-amber-500/10 text-amber-600 dark:text-amber-300'
                                    : 'border-slate-200 bg-slate-50/50 text-slate-500 dark:border-white/[0.1] dark:bg-white/[0.03] dark:text-slate-400'
                            "
                            @click="goalForm.period = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold">Ngày bắt đầu</Label
                        ><Input
                            v-model="goalForm.start_date"
                            type="date"
                            required
                            class="rounded-xl"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold">Ngày kết thúc</Label
                        ><Input
                            v-model="goalForm.end_date"
                            type="date"
                            required
                            class="rounded-xl"
                        />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold">Người phụ trách</Label
                        ><Input
                            v-model="goalForm.owner_name"
                            placeholder="Ví dụ: Quản lý vận hành"
                            class="rounded-xl"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold">Đơn vị đo</Label
                        ><Input
                            v-model="goalForm.unit_name"
                            placeholder="VNĐ, đơn, khách..."
                            class="rounded-xl"
                        />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold">Giá trị mục tiêu</Label
                    ><Input
                        v-model.number="goalForm.target_value"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                        class="rounded-xl"
                    />
                    <p
                        v-if="goalForm.errors.target_value"
                        class="text-xs text-rose-500"
                    >
                        {{ goalForm.errors.target_value }}
                    </p>
                </div>
                <div
                    class="space-y-3 border-t border-slate-200 pt-4 dark:border-white/[0.08]"
                >
                    <div class="flex items-center justify-between">
                        <Label
                            class="flex items-center gap-1.5 text-xs font-bold"
                            ><Trophy class="size-4 text-violet-500" /> Cột mốc
                            chiến lược</Label
                        ><Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 gap-1 rounded-lg text-xs"
                            @click="addMilestone"
                            ><Plus class="size-3.5" /> Thêm mốc</Button
                        >
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="(milestone, index) in goalForm.milestones"
                            :key="index"
                            class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/60 p-2 dark:border-white/[0.08] dark:bg-white/[0.03]"
                        >
                            <Input
                                v-model="milestone.title"
                                required
                                placeholder="Ví dụ: Đạt 50%"
                                class="h-9 flex-1 rounded-lg text-xs"
                            /><Input
                                v-model.number="milestone.threshold_percent"
                                type="number"
                                min="1"
                                max="100"
                                required
                                class="h-9 w-20 rounded-lg text-center text-xs"
                            /><span class="text-xs font-bold text-slate-400"
                                >%</span
                            ><Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="size-9 rounded-lg p-0 text-slate-400 hover:bg-rose-500/10 hover:text-rose-500"
                                :disabled="goalForm.milestones.length <= 1"
                                @click="removeMilestone(index)"
                                ><Trash2 class="size-4"
                            /></Button>
                        </div>
                    </div>
                </div>
                <DialogFooter
                    class="border-t border-slate-200 pt-4 dark:border-white/[0.08]"
                    ><Button
                        type="button"
                        variant="outline"
                        class="rounded-xl"
                        @click="showCreateDialog = false"
                        >Hủy</Button
                    ><Button
                        type="submit"
                        variant="brand"
                        class="rounded-xl"
                        :disabled="goalForm.processing"
                        >{{
                            goalForm.processing ? 'Đang tạo...' : 'Tạo mục tiêu'
                        }}</Button
                    ></DialogFooter
                >
            </form></DialogContent
        ></Dialog
    >

    <Dialog v-model:open="showActionDialog"
        ><DialogContent class="max-w-md rounded-2xl"
            ><DialogHeader
                ><DialogTitle
                    class="flex items-center gap-2 text-lg font-semibold"
                    ><ListChecks class="size-5 text-amber-500" /> Thêm hành động
                    chiến lược</DialogTitle
                ></DialogHeader
            >
            <form class="space-y-4 pt-2" @submit.prevent="submitAction">
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold">Hành động cần làm</Label
                    ><Input
                        v-model="actionForm.title"
                        required
                        placeholder="Ví dụ: Rà soát menu bán chạy"
                        class="rounded-xl"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold"
                        >Mô tả / kết quả đầu ra</Label
                    ><textarea
                        v-model="actionForm.description"
                        rows="3"
                        class="w-full rounded-xl border border-slate-200 bg-transparent px-3 py-2 text-sm outline-none focus:border-amber-500 dark:border-white/[0.1]"
                        placeholder="Ai làm, làm gì, kết quả cần bàn giao..."
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold">Hạn hoàn thành</Label
                    ><Input
                        v-model="actionForm.due_date"
                        type="date"
                        class="rounded-xl"
                    />
                </div>
                <DialogFooter
                    class="border-t border-slate-200 pt-4 dark:border-white/[0.08]"
                    ><Button
                        type="button"
                        variant="outline"
                        class="rounded-xl"
                        @click="showActionDialog = false"
                        >Hủy</Button
                    ><Button
                        type="submit"
                        variant="brand"
                        class="rounded-xl"
                        :disabled="actionForm.processing"
                        >{{
                            actionForm.processing
                                ? 'Đang thêm...'
                                : 'Thêm hành động'
                        }}</Button
                    ></DialogFooter
                >
            </form></DialogContent
        ></Dialog
    >
</template>
