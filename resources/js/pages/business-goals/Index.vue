<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2, Circle, Flag, Plus, Target, Trophy,
    Banknote, ShoppingCart, Users, Star, TrendingDown, Settings, Calendar,
    Activity, Inbox, AlertTriangle, ArrowUpRight, ArrowRight, Trash
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
    }
);

const activeTab = ref<'active' | 'history'>('active');

const metricLabel: Record<string, string> = {
    revenue: 'Doanh thu', orders: 'Số đơn hàng', customers: 'Khách hàng mới',
    rating: 'Đánh giá TB', cost_saving: 'Tiết kiệm chi phí', custom: 'Tùy chỉnh',
};
const periodLabel: Record<string, string> = { weekly: 'Tuần', monthly: 'Tháng', quarterly: 'Quý', yearly: 'Năm' };

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

function getPaceStatus(progress: number, timeProgress: number): { label: string; color: string; badge: string; border: string } {
    if (progress >= 100) {
        return { 
            label: 'Hoàn thành', 
            color: 'text-emerald-500 dark:text-emerald-400', 
            badge: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20 dark:border-emerald-500/30', 
            border: 'border-emerald-500/30 dark:border-emerald-500/40 hover:shadow-[0_0_20px_-3px_rgba(16,185,129,0.12)]' 
        };
    }

    const diff = timeProgress - progress;

    if (diff > 15) {
        return { 
            label: 'Chậm tiến độ', 
            color: 'text-rose-500 dark:text-rose-400', 
            badge: 'bg-rose-500/10 text-rose-500 border-rose-500/20 dark:border-rose-500/30', 
            border: 'border-rose-500/30 dark:border-rose-500/40 hover:shadow-[0_0_20px_-3px_rgba(239,68,68,0.12)]' 
        };
    } else if (diff > 0) {
        return { 
            label: 'Cần đẩy nhanh', 
            color: 'text-amber-500 dark:text-amber-400', 
            badge: 'bg-amber-500/10 text-amber-500 border-amber-500/20 dark:border-amber-500/30', 
            border: 'border-amber-500/30 dark:border-amber-500/40 hover:shadow-[0_0_20px_-3px_rgba(245,158,11,0.12)]' 
        };
    } else {
        return { 
            label: 'Đúng tiến độ', 
            color: 'text-blue-500 dark:text-blue-400', 
            badge: 'bg-blue-500/10 text-blue-500 border-blue-500/20 dark:border-blue-500/30', 
            border: 'border-blue-500/30 dark:border-blue-500/40 hover:shadow-[0_0_20px_-3px_rgba(59,130,246,0.12)]' 
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
    props.activeGoals.forEach(g => {
        if (g.actions) {
            g.actions.forEach((a: any) => {
                total++;

                if (a.status === 'done') {
done++;
}
            });
        }
    });

    return { done, total, pct: total === 0 ? 0 : Math.round((done / total) * 100) };
});

const milestonesRatio = computed(() => {
    let total = 0;
    let reached = 0;
    props.activeGoals.forEach(g => {
        if (g.milestones) {
            g.milestones.forEach((m: any) => {
                total++;

                if (m.reached) {
reached++;
}
            });
        }
    });

    return { reached, total, pct: total === 0 ? 0 : Math.round((reached / total) * 100) };
});

// Create goal
const showCreateDialog = ref(false);
const goalForm = useForm({
    title: '', description: '', metric: 'revenue' as string, period: 'monthly' as string,
    start_date: new Date().toISOString().slice(0, 10), end_date: '',
    target_value: 0,
    milestones: [{ title: 'Đạt 50%', threshold_percent: 50 }, { title: 'Đạt 100%', threshold_percent: 100 }] as { title: string; threshold_percent: number }[],
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

    <div class="flex flex-col gap-6 p-4 lg:p-8 max-w-7xl mx-auto w-full">
        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-border/80 pb-6">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 ring-4 ring-amber-500/5">
                    <Target class="size-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-foreground flex items-center gap-2">
                        Mục Tiêu & OKR Doanh Nghiệp
                    </h1>
                    <p class="text-sm text-muted-foreground">Thiết lập mục tiêu dài hạn, theo dõi tiến độ thời gian thực và kế hoạch hành động.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <Button @click="showCreateDialog = true" class="gap-1.5 cursor-pointer rounded-xl bg-amber-600 hover:bg-amber-700 text-white transition active:scale-95 shadow-sm font-bold">
                    <Plus class="size-4" /> Tạo mục tiêu mới
                </Button>
            </div>
        </div>

        <!-- Tab & Stats Control Area -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <!-- Tabs Switcher (Pill Style) -->
            <div class="flex items-center gap-1 rounded-xl border border-border bg-muted p-1 self-start">
                <button v-for="tab in [{key:'active',label:'Đang hoạt động'},{key:'history',label:'Lịch sử'}]" :key="tab.key"
                    @click="activeTab = tab.key as any"
                    class="cursor-pointer rounded-lg px-4 py-2 text-xs font-bold transition-all whitespace-nowrap"
                    :class="activeTab === tab.key ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'"
                >{{ tab.label }}</button>
            </div>
        </div>

        <!-- Active goals tab -->
        <div v-if="activeTab === 'active'" class="space-y-6">
            <!-- Strategic Stats Panel -->
            <div v-if="activeGoals.length" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Average OKR Progress -->
                <Card class="bg-card border-border shadow-xs hover:shadow-md transition-all duration-300">
                    <CardContent class="p-5 flex items-center justify-between">
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Tiến độ TB</span>
                            <p class="text-2xl font-black text-foreground tracking-tight">{{ avgProgress }}%</p>
                        </div>
                        <div class="p-3 rounded-2xl bg-amber-500/10 text-amber-500 shrink-0">
                            <Target class="size-5" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Active Goals Count -->
                <Card class="bg-card border-border shadow-xs hover:shadow-md transition-all duration-300">
                    <CardContent class="p-5 flex items-center justify-between">
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Đang chạy</span>
                            <p class="text-2xl font-black text-foreground tracking-tight">{{ activeGoals.length }} mục tiêu</p>
                        </div>
                        <div class="p-3 rounded-2xl bg-blue-500/10 text-blue-500 shrink-0">
                            <Flag class="size-5" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Actions Ratio Completed -->
                <Card class="bg-card border-border shadow-xs hover:shadow-md transition-all duration-300">
                    <CardContent class="p-5 flex items-center justify-between">
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Hành động xong</span>
                            <p class="text-2xl font-black text-foreground tracking-tight">
                                {{ actionsRatio.done }}/{{ actionsRatio.total }}
                            </p>
                        </div>
                        <div class="p-3 rounded-2xl bg-emerald-500/10 text-emerald-500 shrink-0">
                            <CheckCircle2 class="size-5" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Milestones Ratio -->
                <Card class="bg-card border-border shadow-xs hover:shadow-md transition-all duration-300">
                    <CardContent class="p-5 flex items-center justify-between">
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Mốc hoàn thành</span>
                            <p class="text-2xl font-black text-foreground tracking-tight">
                                {{ milestonesRatio.reached }}/{{ milestonesRatio.total }}
                            </p>
                        </div>
                        <div class="p-3 rounded-2xl bg-purple-500/10 text-purple-500 shrink-0">
                            <Trophy class="size-5" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- List of goals -->
            <div v-if="activeGoals.length" class="grid grid-cols-1 gap-6">
                <Card v-for="goal in activeGoals" :key="goal.id"
                    :class="[
                        'bg-card text-card-foreground border shadow-xs hover:shadow-md transition-all duration-300',
                        getPaceStatus(goal.progress_percent, getTimeProgress(goal.start_date, goal.end_date)).border
                    ]"
                >
                    <CardContent class="p-6 space-y-6">
                        <!-- Top details -->
                        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                            <div class="space-y-1.5 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-extrabold text-lg text-foreground leading-tight truncate">{{ goal.title }}</h2>
                                    <Badge variant="outline" class="text-[9px] font-bold px-2 py-0.5 border-amber-500/20 bg-amber-500/5 text-amber-500 font-mono">
                                        {{ metricLabel[goal.metric] || goal.metric }}
                                    </Badge>
                                    <Badge variant="outline" class="text-[9px] font-bold px-2 py-0.5 border-blue-500/20 bg-blue-500/5 text-blue-500 font-mono">
                                        {{ periodLabel[goal.period] || goal.period }}
                                    </Badge>
                                    <!-- Pace Badge Indicator -->
                                    <Badge 
                                        :class="[
                                            'text-[9px] font-bold px-2 py-0.5 border-0',
                                            getPaceStatus(goal.progress_percent, getTimeProgress(goal.start_date, goal.end_date)).badge
                                        ]"
                                    >
                                        {{ getPaceStatus(goal.progress_percent, getTimeProgress(goal.start_date, goal.end_date)).label }}
                                    </Badge>
                                </div>
                                <p class="text-xs text-muted-foreground flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span>Tiến độ hiện tại: <span class="font-bold text-foreground">{{ formatValue(goal.metric, Number(goal.current_value)) }}</span> / {{ formatValue(goal.metric, Number(goal.target_value)) }}</span>
                                    <span>·</span>
                                    <span class="flex items-center gap-1">
                                        <Calendar class="size-3 text-muted-foreground/60" />
                                        Hạn cuối: <span class="font-semibold text-foreground/80">{{ new Date(goal.end_date).toLocaleDateString('vi-VN') }}</span>
                                    </span>
                                </p>
                            </div>
                            <div class="flex flex-col items-end shrink-0">
                                <span :class="['text-3xl font-black tracking-tight', getPaceStatus(goal.progress_percent, getTimeProgress(goal.start_date, goal.end_date)).color]">
                                    {{ goal.progress_percent }}%
                                </span>
                            </div>
                        </div>

                        <!-- Progress tracking bar -->
                        <div class="space-y-1.5">
                            <div class="h-2.5 rounded-full bg-muted overflow-hidden relative">
                                <!-- Actual Progress -->
                                <div :class="['h-full rounded-full transition-all duration-500', progressColor(goal.progress_percent)]"
                                    :style="{ width: Math.min(100, goal.progress_percent) + '%' }"></div>
                                <!-- Time Elapsed Marker dot -->
                                <div 
                                    v-if="goal.progress_percent < 100"
                                    class="absolute top-0 bottom-0 w-0.5 bg-foreground/35 hover:bg-foreground transition-colors cursor-help"
                                    :style="{ left: `${getTimeProgress(goal.start_date, goal.end_date)}%` }"
                                    :title="`Thời gian trôi qua: ${getTimeProgress(goal.start_date, goal.end_date)}%`"
                                ></div>
                            </div>
                            <div class="flex justify-between text-[10px] text-muted-foreground font-semibold">
                                <span class="flex items-center gap-1">
                                    <Activity class="size-3 text-muted-foreground/70" />
                                    Hạn OKR còn: {{ Math.max(0, Math.ceil((new Date(goal.end_date).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24))) }} ngày
                                </span>
                                <span>Thời gian đã trôi qua: {{ getTimeProgress(goal.start_date, goal.end_date) }}%</span>
                            </div>
                        </div>

                        <!-- Horizontal Milestones Step Indicator -->
                        <div v-if="goal.milestones?.length" class="space-y-2.5 pt-1">
                            <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Cột mốc chiến lược (Milestones)</span>
                            <div class="relative h-12 w-full select-none">
                                <!-- Connecting progress bar track -->
                                <div class="absolute left-2 right-2 top-[10px] h-1 bg-muted rounded-full">
                                    <div 
                                        class="h-full bg-gradient-to-r from-amber-500 to-emerald-500 rounded-full transition-all duration-500"
                                        :style="{ width: `${Math.min(goal.progress_percent, 100)}%` }"
                                    ></div>
                                </div>

                                <!-- Step milestones dots positioned absolutely -->
                                <div 
                                    v-for="m in goal.milestones" 
                                    :key="m.id"
                                    class="absolute -translate-x-1/2 flex flex-col items-center gap-1"
                                    :style="{ left: `${m.threshold_percent}%` }"
                                >
                                    <!-- Step Dot -->
                                    <div :class="[
                                        'flex h-5 w-5 items-center justify-center rounded-full border transition-all duration-300',
                                        m.reached 
                                            ? 'bg-emerald-500 border-emerald-500 text-white shadow-[0_0_8px_rgba(16,185,129,0.3)] scale-110' 
                                            : 'bg-card border-border text-muted-foreground/60'
                                    ]">
                                        <Trophy v-if="m.reached" class="size-2.5 text-white" />
                                        <Flag v-else class="size-2.5" />
                                    </div>
                                    <!-- Title label -->
                                    <span :class="[
                                        'text-[9px] font-bold whitespace-nowrap bg-background/90 px-1.5 py-0.5 border rounded-sm',
                                        m.reached ? 'text-emerald-500 border-emerald-500/20' : 'text-muted-foreground border-border/40'
                                    ]">
                                        {{ m.title || 'Mốc' }} ({{ m.threshold_percent }}%)
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions Action Plan Checklist -->
                        <div class="border-t border-border/80 pt-5 space-y-3.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                                    <CheckCircle2 class="size-4 text-muted-foreground/80" />
                                    Kế hoạch hành động
                                </span>
                                <Button variant="ghost" size="sm" class="text-xs gap-1 h-8 px-3 cursor-pointer hover:bg-muted font-bold rounded-lg border border-border" @click="openActionDialog(goal.id)">
                                    <Plus class="size-3.5" /> Thêm hành động
                                </Button>
                            </div>
                            
                            <!-- Empty Actions -->
                            <div v-if="!goal.actions || goal.actions.length === 0" class="text-xs text-muted-foreground italic py-3 px-4 border border-dashed border-border/80 rounded-xl bg-muted/5 flex items-center justify-center gap-2">
                                <Inbox class="size-4 text-muted-foreground/60" />
                                Chưa có hành động nào được tạo cho mục tiêu này.
                            </div>
                            
                            <!-- Actions List -->
                            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div v-for="action in goal.actions" :key="action.id" 
                                    class="flex items-center justify-between gap-3 text-sm p-3 rounded-xl border border-border/40 bg-muted/5 hover:bg-muted/15 transition-all"
                                    :class="{ 'border-emerald-500/10 bg-emerald-500/5 opacity-80': action.status === 'done' }"
                                >
                                    <div class="flex items-center gap-3 min-w-0">
                                        <button @click="toggleAction(action.id)" 
                                            class="shrink-0 cursor-pointer text-muted-foreground hover:text-emerald-500 group transition-colors"
                                        >
                                            <CheckCircle2 v-if="action.status === 'done'" class="size-5 text-emerald-500 animate-pulse" />
                                            <Circle v-else class="size-5 group-hover:scale-110 transition-transform" />
                                        </button>
                                        <span :class="[
                                            'truncate transition-all text-xs',
                                            action.status === 'done' ? 'line-through text-muted-foreground font-normal' : 'text-foreground font-bold'
                                        ]">{{ action.title }}</span>
                                    </div>
                                    
                                    <!-- Deadline and Assignee -->
                                    <div class="flex items-center gap-2 shrink-0 ml-2">
                                        <span v-if="action.due_date" class="text-[9px] text-muted-foreground font-bold font-mono bg-muted/40 px-1.5 py-0.5 rounded border border-border/40">
                                            Hạn: {{ new Date(action.due_date).toLocaleDateString('vi-VN') }}
                                        </span>
                                        <span v-if="action.assignee" class="text-[9px] font-bold text-foreground bg-muted px-2 py-0.5 rounded-full border border-border/80">
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
            <div class="flex flex-col items-center justify-center text-center py-16 px-4 border border-dashed border-border/80 rounded-2xl bg-muted/5">
                <div class="relative mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-500 ring-8 ring-amber-500/5">
                    <Target class="size-8" />
                    <div class="absolute -right-1 -top-1 h-3.5 w-3.5 rounded-full bg-emerald-500 border-2 border-background animate-pulse"></div>
                </div>
                <h3 class="text-base font-bold text-foreground">Chưa có mục tiêu & OKR nào</h3>
                <p class="text-xs text-muted-foreground mt-1 max-w-sm">
                    Thiết lập các mục tiêu kinh doanh chiến lược (Doanh thu, Đơn hàng, v.v.) và kế hoạch hành động để thúc đẩy tăng trưởng ngay hôm nay.
                </p>
                <Button @click="showCreateDialog = true" class="mt-5 cursor-pointer rounded-xl bg-amber-600 hover:bg-amber-700 text-white transition active:scale-95 shadow-sm font-bold text-xs">
                    Tạo mục tiêu đầu tiên
                </Button>
            </div>
        </div>

        <!-- History tab -->
        <div v-if="activeTab === 'history'">
            <Card class="bg-card text-card-foreground border border-border shadow-sm">
                <div class="p-5 border-b border-border/60 flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-amber-500/10 text-amber-500 shrink-0">
                        <Trophy class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-foreground">Lịch sử mục tiêu OKR</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Danh sách các mục tiêu đã hoàn thành hoặc hết hạn chu kỳ.</p>
                    </div>
                </div>
                <CardContent class="p-5">
                    <div v-if="history.length" class="border border-border/80 overflow-hidden rounded-xl bg-muted/5 shadow-xs">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-border bg-muted/30 text-muted-foreground font-semibold">
                                        <th class="px-5 py-3.5">Mục tiêu</th>
                                        <th class="px-5 py-3.5">Đo lường / Chu kỳ</th>
                                        <th class="px-5 py-3.5 text-center">Kết quả đạt được</th>
                                        <th class="px-5 py-3.5">Trạng thái</th>
                                        <th class="px-5 py-3.5">Ngày hoàn thành</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60">
                                    <tr v-for="g in history" :key="g.id" class="hover:bg-muted/15 transition-all duration-150">
                                        <td class="px-5 py-3.5 font-bold text-foreground">
                                            {{ g.title }}
                                        </td>
                                        <td class="px-5 py-3.5 text-muted-foreground">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <Badge variant="outline" class="text-[9px] px-1.5 py-0 border-border bg-background text-muted-foreground font-bold font-mono">
                                                    {{ metricLabel[g.metric] || g.metric }}
                                                </Badge>
                                                <Badge variant="outline" class="text-[9px] px-1.5 py-0 border-border bg-background text-muted-foreground font-bold font-mono">
                                                    {{ periodLabel[g.period] || g.period }}
                                                </Badge>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5 text-center">
                                            <span :class="['font-extrabold text-sm', g.percent >= 100 ? 'text-emerald-500 dark:text-emerald-400' : 'text-rose-500']">
                                                {{ g.percent }}%
                                            </span>
                                            <p class="text-[10px] text-muted-foreground mt-0.5">
                                                {{ formatValue(g.metric, g.achieved) }} / {{ formatValue(g.metric, g.target) }}
                                            </p>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <Badge 
                                                :variant="g.status === 'completed' ? 'secondary' : 'destructive'" 
                                                class="text-[10px] px-2.5 py-0.5 font-semibold font-mono border-0"
                                                :class="g.status === 'completed' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-rose-500/10 text-rose-500'"
                                            >
                                                {{ g.status === 'completed' ? 'ĐẠT' : 'KHÔNG ĐẠT' }}
                                            </Badge>
                                        </td>
                                        <td class="px-5 py-3.5 text-muted-foreground font-mono font-medium">
                                            {{ g.end_date }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div v-else class="flex flex-col items-center justify-center py-10 text-center text-muted-foreground/60 w-full">
                        <Inbox class="size-6 text-muted-foreground/30 mb-2" />
                        <p class="text-xs font-semibold">Chưa có lịch sử mục tiêu hoàn thành.</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Create goal dialog -->
    <Dialog v-model:open="showCreateDialog">
        <DialogContent class="max-w-lg max-h-[85vh] overflow-y-auto rounded-2xl">
            <DialogHeader>
                <DialogTitle class="text-lg font-black flex items-center gap-2">
                    <Target class="size-5 text-amber-500" />
                    Tạo Mục Tiêu OKR Mới
                </DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitGoal" class="space-y-5 pt-2">
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold text-foreground">Tiêu đề mục tiêu</Label>
                    <Input v-model="goalForm.title" placeholder="Ví dụ: Đạt doanh thu 100 triệu VND tháng này" required class="rounded-xl" />
                </div>
                
                <div class="space-y-2">
                    <Label class="text-xs font-bold text-foreground">Chỉ số đo lường (Metric)</Label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                        <button 
                            v-for="opt in metricOptions" 
                            :key="opt.value"
                            type="button"
                            @click="goalForm.metric = opt.value"
                            class="flex flex-col items-center justify-center p-3 rounded-xl border text-center transition-all duration-200 cursor-pointer"
                            :class="goalForm.metric === opt.value ? 'border-amber-500 bg-amber-500/10 text-amber-500 font-bold shadow-xs' : 'border-border bg-muted/20 text-muted-foreground hover:bg-muted/40 hover:text-foreground'"
                        >
                            <component :is="opt.icon" class="size-5 mb-1.5" />
                            <span class="text-xs leading-none font-bold">{{ opt.label }}</span>
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label class="text-xs font-bold text-foreground">Chu kỳ (Period)</Label>
                    <div class="flex flex-wrap gap-2">
                        <button 
                            v-for="opt in periodOptions" 
                            :key="opt.value"
                            type="button"
                            @click="goalForm.period = opt.value"
                            class="px-3.5 py-2 rounded-xl border text-xs font-bold transition-all cursor-pointer"
                            :class="goalForm.period === opt.value ? 'border-amber-500 bg-amber-500/10 text-amber-500' : 'border-border bg-muted/20 text-muted-foreground hover:bg-muted/40'"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold text-foreground">Ngày bắt đầu</Label>
                        <Input type="date" v-model="goalForm.start_date" required class="rounded-xl" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold text-foreground">Ngày kết thúc</Label>
                        <Input type="date" v-model="goalForm.end_date" required class="rounded-xl" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold text-foreground">Giá trị mục tiêu hướng tới (Target)</Label>
                    <Input type="number" step="0.01" v-model="goalForm.target_value" required class="rounded-xl" />
                </div>

                <!-- Milestones Editor -->
                <div class="space-y-3 border-t border-border/80 pt-4">
                    <div class="flex items-center justify-between">
                        <Label class="text-xs font-bold text-foreground flex items-center gap-1">
                            <Trophy class="size-4 text-purple-500" />
                            Cột mốc hoàn thành (Milestones)
                        </Label>
                        <Button variant="outline" size="sm" type="button" @click="addMilestone" class="text-xs gap-1 rounded-lg h-7 px-2.5 font-bold">
                            <Plus class="size-3" /> Thêm mốc
                        </Button>
                    </div>
                    
                    <div class="space-y-2 max-h-40 overflow-y-auto pr-1">
                        <div v-for="(m, idx) in goalForm.milestones" :key="idx" class="flex items-center gap-2 bg-muted/30 p-2 rounded-xl border border-border/40">
                            <Input v-model="m.title" placeholder="Tiêu đề mốc (ví dụ: Đạt 50% chặng đường)" class="flex-1 rounded-lg h-9 text-xs" required />
                            <div class="flex items-center gap-1 shrink-0 w-24">
                                <Input type="number" v-model="m.threshold_percent" class="w-16 rounded-lg h-9 text-xs text-center" min="1" max="100" required />
                                <span class="text-xs font-bold text-muted-foreground">%</span>
                            </div>
                            <Button 
                                type="button" 
                                variant="ghost" 
                                size="sm" 
                                @click="removeMilestone(idx)" 
                                class="h-9 w-9 p-0 rounded-lg text-muted-foreground hover:text-rose-500 shrink-0 hover:bg-rose-500/10 cursor-pointer"
                            >
                                <Trash class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <DialogFooter class="border-t border-border/80 pt-4">
                    <Button variant="outline" type="button" @click="showCreateDialog = false" class="rounded-xl">Hủy</Button>
                    <Button type="submit" :disabled="goalForm.processing" class="rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold">
                        {{ goalForm.processing ? 'Đang tạo...' : 'Tạo mục tiêu' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Add action dialog -->
    <Dialog v-model:open="showActionDialog">
        <DialogContent class="max-w-md rounded-2xl">
            <DialogHeader>
                <DialogTitle class="text-lg font-black flex items-center gap-2">
                    <Plus class="size-5 text-amber-500" />
                    Thêm Hành Động Chiến Lược
                </DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitAction" class="space-y-4 pt-2">
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold text-foreground">Hành động cần làm</Label>
                    <Input v-model="actionForm.title" placeholder="Ví dụ: Tổ chức chương trình khuyến mãi mua 1 tặng 1" required class="rounded-xl" />
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold text-foreground">Hạn chót hoàn thành (Due Date)</Label>
                    <Input type="date" v-model="actionForm.due_date" class="rounded-xl" />
                </div>
                <DialogFooter class="border-t border-border/80 pt-4">
                    <Button variant="outline" type="button" @click="showActionDialog = false" class="rounded-xl">Hủy</Button>
                    <Button type="submit" :disabled="actionForm.processing" class="rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold">
                        {{ actionForm.processing ? 'Đang thêm...' : 'Thêm hành động' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
