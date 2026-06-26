<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2, Circle, Flag, Plus, Target, Trophy,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps<{ activeGoals: any[]; history: any[] }>();

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

function progressColor(p: number): string {
    if (p >= 100) {
        return 'bg-green-500';
    }

    if (p >= 50) {
        return 'bg-blue-500';
    }

    if (p >= 25) {
        return 'bg-amber-500';
    }

    return 'bg-red-400';
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

    <div class="flex flex-col gap-5 p-4 lg:p-6 max-w-7xl mx-auto w-full">
        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-border pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <Target class="size-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Mục Tiêu & OKR Doanh Nghiệp</h1>
                    <p class="text-sm text-muted-foreground">Đặt mục tiêu, tracking realtime, milestone, kế hoạch hành động.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <Button @click="showCreateDialog = true" class="gap-1.5 cursor-pointer rounded-xl bg-amber-600 hover:bg-amber-700 text-white transition active:scale-95">
                    <Plus class="size-4" /> Tạo mục tiêu
                </Button>
            </div>
        </div>

        <!-- Tabs Switcher (Pill Style) -->
        <div class="flex items-center gap-1 rounded-xl border border-border bg-muted p-1 self-start">
            <button v-for="tab in [{key:'active',label:'Đang hoạt động'},{key:'history',label:'Lịch sử'}]" :key="tab.key"
                @click="activeTab = tab.key as any"
                class="cursor-pointer rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-all whitespace-nowrap"
                :class="activeTab === tab.key ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
            >{{ tab.label }}</button>
        </div>

        <!-- Active goals -->
        <div v-if="activeTab === 'active'" class="space-y-4">
            <Card v-for="goal in activeGoals" :key="goal.id">
                <CardContent class="pt-5 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-base text-foreground">{{ goal.title }}</p>
                                <Badge variant="secondary" class="text-[10px] py-0.5 px-2">{{ metricLabel[goal.metric] }}</Badge>
                                <Badge variant="outline" class="text-[10px] py-0.5 px-2">{{ periodLabel[goal.period] }}</Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Tiến độ: <span class="font-bold text-foreground">{{ formatValue(goal.metric, Number(goal.current_value)) }}</span> / {{ formatValue(goal.metric, Number(goal.target_value)) }}
                                · Hạn cuối: {{ new Date(goal.end_date).toLocaleDateString('vi-VN') }}
                            </p>
                        </div>
                        <span :class="['text-2xl font-bold shrink-0', goal.progress_percent >= 100 ? 'text-emerald-600 dark:text-emerald-400' : goal.progress_percent >= 50 ? 'text-blue-600' : 'text-amber-600']">
                            {{ goal.progress_percent }}%
                        </span>
                    </div>

                    <!-- Progress bar -->
                    <div class="space-y-1">
                        <div class="h-2 rounded-full bg-muted overflow-hidden">
                            <div :class="['h-full rounded-full transition-all', progressColor(goal.progress_percent)]"
                                :style="{ width: Math.min(100, goal.progress_percent) + '%' }"></div>
                        </div>
                    </div>

                    <!-- Milestones -->
                    <div v-if="goal.milestones?.length" class="flex flex-wrap gap-2.5 pt-1">
                        <div v-for="m in goal.milestones" :key="m.id" class="flex items-center gap-1 text-xs">
                            <Trophy v-if="m.reached" class="size-3.5 text-amber-500" />
                            <Flag v-else class="size-3.5 text-muted-foreground" />
                            <span :class="m.reached ? 'font-bold text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">{{ m.title }} ({{ m.threshold_percent }}%)</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="border-t border-border pt-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Kế hoạch hành động</span>
                            <Button variant="ghost" size="sm" class="text-xs gap-1 h-7 px-2 cursor-pointer hover:bg-muted" @click="openActionDialog(goal.id)">
                                <Plus class="size-3" /> Thêm hành động
                            </Button>
                        </div>
                        <div v-if="!goal.actions || goal.actions.length === 0" class="text-xs text-muted-foreground italic py-1">
                            Chưa có hành động nào được tạo.
                        </div>
                        <div v-else class="space-y-1.5">
                            <div v-for="action in goal.actions" :key="action.id" class="flex items-center gap-2.5 text-sm p-1.5 rounded-lg hover:bg-muted/30 transition-colors">
                                <button @click="toggleAction(action.id)" class="shrink-0 cursor-pointer text-muted-foreground hover:text-emerald-500">
                                    <CheckCircle2 v-if="action.status === 'done'" class="size-4 text-emerald-500" />
                                    <Circle v-else class="size-4" />
                                </button>
                                <span :class="action.status === 'done' ? 'line-through text-muted-foreground' : 'text-foreground font-medium'">{{ action.title }}</span>
                                <span v-if="action.assignee" class="text-xs text-muted-foreground ml-auto bg-muted px-2 py-0.5 rounded-full">{{ action.assignee.name }}</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
            <p v-if="!activeGoals.length" class="text-center text-muted-foreground py-12">Chưa có mục tiêu nào. Tạo mục tiêu đầu tiên!</p>
        </div>

        <!-- History -->
        <div v-if="activeTab === 'history'">
            <Card>
                <CardContent class="pt-5 p-0">
                    <div class="px-5 pb-3">
                        <div class="flex items-center gap-2 mb-1">
                            <Trophy class="size-4 text-amber-500" />
                            <p class="font-semibold text-sm">Lịch sử mục tiêu</p>
                        </div>
                        <p class="text-xs text-muted-foreground">Danh sách các mục tiêu đã hoàn thành hoặc hết hạn.</p>
                    </div>
                    <table class="w-full text-sm border-t border-border">
                        <thead class="bg-muted/50">
                            <tr class="text-left text-xs text-muted-foreground border-b">
                                <th class="px-4 py-3">Mục tiêu</th>
                                <th class="px-4 py-3">Chỉ số</th>
                                <th class="px-4 py-3 text-center">Kết quả</th>
                                <th class="px-4 py-3">Trạng thái</th>
                                <th class="px-4 py-3">Kết thúc</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="g in history" :key="g.id" class="border-b last:border-0 hover:bg-muted/10 transition-colors">
                                <td class="px-4 py-3 font-medium">{{ g.title }}</td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">{{ metricLabel[g.metric] }} · {{ periodLabel[g.period] }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['font-bold', g.percent >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500']">{{ g.percent }}%</span>
                                    <p class="text-xs text-muted-foreground">{{ formatValue(g.metric, g.achieved) }} / {{ formatValue(g.metric, g.target) }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :class="g.status === 'completed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'" class="text-xs">
                                        {{ g.status === 'completed' ? 'Đạt' : 'Không đạt' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">{{ g.end_date }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!history.length" class="text-center text-muted-foreground py-12">Chưa có lịch sử mục tiêu.</p>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Create goal -->
    <Dialog v-model:open="showCreateDialog">
        <DialogContent class="max-w-lg max-h-[80vh] overflow-y-auto">
            <DialogHeader><DialogTitle>Tạo Mục Tiêu Mới</DialogTitle></DialogHeader>
            <form @submit.prevent="submitGoal" class="space-y-4">
                <div class="grid gap-1.5"><Label>Tiêu đề</Label><Input v-model="goalForm.title" placeholder="Doanh thu tháng 7 đạt 100 triệu" required /></div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Chỉ số đo lường</Label>
                        <select v-model="goalForm.metric" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="revenue">Doanh thu (VND)</option><option value="orders">Số đơn hàng</option>
                            <option value="customers">Khách hàng mới</option><option value="rating">Đánh giá TB</option>
                            <option value="cost_saving">Tiết kiệm chi phí</option><option value="custom">Tùy chỉnh</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Chu kỳ</Label>
                        <select v-model="goalForm.period" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="weekly">Tuần</option><option value="monthly">Tháng</option>
                            <option value="quarterly">Quý</option><option value="yearly">Năm</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5"><Label>Bắt đầu</Label><Input type="date" v-model="goalForm.start_date" required /></div>
                    <div class="grid gap-1.5"><Label>Kết thúc</Label><Input type="date" v-model="goalForm.end_date" required /></div>
                </div>
                <div class="grid gap-1.5"><Label>Giá trị mục tiêu</Label><Input type="number" step="0.01" v-model="goalForm.target_value" required /></div>

                <div class="space-y-2">
                    <Label>Milestones</Label>
                    <div v-for="(m, idx) in goalForm.milestones" :key="idx" class="flex items-center gap-2">
                        <Input v-model="m.title" placeholder="Tiêu đề" class="flex-1" />
                        <Input type="number" v-model="m.threshold_percent" class="w-20" />
                        <span class="text-xs">%</span>
                    </div>
                    <Button variant="outline" size="sm" type="button" @click="addMilestone" class="text-xs">+ Milestone</Button>
                </div>

                <DialogFooter>
                    <Button variant="outline" type="button" @click="showCreateDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="goalForm.processing">{{ goalForm.processing ? 'Đang tạo...' : 'Tạo mục tiêu' }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Add action -->
    <Dialog v-model:open="showActionDialog">
        <DialogContent class="max-w-md">
            <DialogHeader><DialogTitle>Thêm Hành Động</DialogTitle></DialogHeader>
            <form @submit.prevent="submitAction" class="space-y-4">
                <div class="grid gap-1.5"><Label>Tiêu đề</Label><Input v-model="actionForm.title" required /></div>
                <div class="grid gap-1.5"><Label>Hạn chót</Label><Input type="date" v-model="actionForm.due_date" /></div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="showActionDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="actionForm.processing">Thêm</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
