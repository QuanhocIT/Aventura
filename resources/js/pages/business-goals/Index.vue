<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    BarChart3, CheckCircle2, Circle, Flag, Loader2, Plus, Target, Trash2, Trophy,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ activeGoals: any[]; history: any[] }>();

const page = usePage();
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
});

const activeTab = ref<'active' | 'history'>('active');

const metricLabel: Record<string, string> = {
    revenue: 'Doanh thu', orders: 'Số đơn hàng', customers: 'Khách hàng mới',
    rating: 'Đánh giá TB', cost_saving: 'Tiết kiệm chi phí', custom: 'Tùy chỉnh',
};
const periodLabel: Record<string, string> = { weekly: 'Tuần', monthly: 'Tháng', quarterly: 'Quý', yearly: 'Năm' };

function progressColor(p: number): string {
    if (p >= 100) return 'bg-green-500';
    if (p >= 50) return 'bg-blue-500';
    if (p >= 25) return 'bg-amber-500';
    return 'bg-red-400';
}

function formatValue(metric: string, value: number): string {
    if (metric === 'rating') return value.toFixed(1) + '/5';
    if (['revenue', 'cost_saving'].includes(metric)) return value.toLocaleString() + 'đ';
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
function addMilestone() { goalForm.milestones.push({ title: '', threshold_percent: 75 }); }
function submitGoal() {
    goalForm.post('/business-goals', { onSuccess: () => { showCreateDialog.value = false; goalForm.reset(); } });
}

// Add action
const showActionDialog = ref(false);
const actionGoalId = ref<number | null>(null);
const actionForm = useForm({ title: '', description: '', due_date: '' });
function openActionDialog(goalId: number) { actionGoalId.value = goalId; actionForm.reset(); showActionDialog.value = true; }
function submitAction() {
    if (!actionGoalId.value) return;
    actionForm.post(`/business-goals/${actionGoalId.value}/actions`, { onSuccess: () => { showActionDialog.value = false; } });
}
function toggleAction(actionId: number) { router.patch(`/business-goals/actions/${actionId}/toggle`); }
</script>

<template>
    <Head title="Mục tiêu & OKR" />

    <div class="flex flex-col gap-6 p-6 max-w-6xl mx-auto">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600">
                    <Target class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold">Mục Tiêu & OKR Doanh Nghiệp</h1>
                    <p class="text-sm text-muted-foreground">Đặt mục tiêu, tracking realtime, milestone, kế hoạch hành động.</p>
                </div>
            </div>
            <Button @click="showCreateDialog = true" class="gap-1.5"><Plus class="size-4" /> Tạo mục tiêu</Button>
        </div>

        <div class="flex gap-2 border-b pb-2">
            <button v-for="tab in [{key:'active',label:'Đang hoạt động'},{key:'history',label:'Lịch sử'}]" :key="tab.key"
                @click="activeTab = tab.key as any"
                :class="['px-4 py-2 rounded-lg text-sm font-semibold', activeTab === tab.key ? 'bg-amber-600 text-white' : 'hover:bg-muted']"
            >{{ tab.label }}</button>
        </div>

        <!-- Active goals -->
        <div v-if="activeTab === 'active'" class="space-y-4">
            <Card v-for="goal in activeGoals" :key="goal.id">
                <CardHeader class="pb-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-base flex items-center gap-2">
                                {{ goal.title }}
                                <Badge variant="secondary" class="text-xs">{{ metricLabel[goal.metric] }}</Badge>
                                <Badge variant="outline" class="text-xs">{{ periodLabel[goal.period] }}</Badge>
                            </CardTitle>
                            <CardDescription>
                                {{ formatValue(goal.metric, Number(goal.current_value)) }} / {{ formatValue(goal.metric, Number(goal.target_value)) }}
                                · Hết hạn {{ new Date(goal.end_date).toLocaleDateString('vi-VN') }}
                            </CardDescription>
                        </div>
                        <span :class="['text-2xl font-bold', goal.progress_percent >= 100 ? 'text-green-600' : goal.progress_percent >= 50 ? 'text-blue-600' : 'text-amber-600']">
                            {{ goal.progress_percent }}%
                        </span>
                    </div>
                    <!-- Progress bar -->
                    <div class="h-3 rounded-full bg-muted overflow-hidden mt-2">
                        <div :class="['h-full rounded-full transition-all', progressColor(goal.progress_percent)]"
                            :style="{ width: Math.min(100, goal.progress_percent) + '%' }"></div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <!-- Milestones -->
                    <div v-if="goal.milestones?.length" class="flex flex-wrap gap-2">
                        <div v-for="m in goal.milestones" :key="m.id" class="flex items-center gap-1 text-xs">
                            <Trophy v-if="m.reached" class="size-3.5 text-amber-500" />
                            <Flag v-else class="size-3.5 text-muted-foreground" />
                            <span :class="m.reached ? 'font-bold text-amber-600' : 'text-muted-foreground'">{{ m.title }} ({{ m.threshold_percent }}%)</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="border-t pt-2 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-muted-foreground">Kế hoạch hành động</span>
                            <Button variant="ghost" size="sm" class="text-xs gap-1" @click="openActionDialog(goal.id)"><Plus class="size-3" /> Thêm</Button>
                        </div>
                        <div v-for="action in goal.actions" :key="action.id" class="flex items-center gap-2 text-sm">
                            <button @click="toggleAction(action.id)" class="shrink-0">
                                <CheckCircle2 v-if="action.status === 'done'" class="size-4 text-green-500" />
                                <Circle v-else class="size-4 text-muted-foreground hover:text-green-500" />
                            </button>
                            <span :class="action.status === 'done' ? 'line-through text-muted-foreground' : ''">{{ action.title }}</span>
                            <span v-if="action.assignee" class="text-xs text-muted-foreground ml-auto">{{ action.assignee.name }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
            <p v-if="!activeGoals.length" class="text-center text-muted-foreground py-12">Chưa có mục tiêu nào. Tạo mục tiêu đầu tiên!</p>
        </div>

        <!-- History -->
        <div v-if="activeTab === 'history'">
            <Card>
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr class="text-left text-xs text-muted-foreground">
                                <th class="px-4 py-3">Mục tiêu</th>
                                <th class="px-4 py-3">Chỉ số</th>
                                <th class="px-4 py-3 text-center">Kết quả</th>
                                <th class="px-4 py-3">Trạng thái</th>
                                <th class="px-4 py-3">Kết thúc</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="g in history" :key="g.id" class="border-b last:border-0">
                                <td class="px-4 py-3 font-medium">{{ g.title }}</td>
                                <td class="px-4 py-3 text-xs">{{ metricLabel[g.metric] }} · {{ periodLabel[g.period] }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['font-bold', g.percent >= 100 ? 'text-green-600' : 'text-red-500']">{{ g.percent }}%</span>
                                    <p class="text-xs text-muted-foreground">{{ formatValue(g.metric, g.achieved) }} / {{ formatValue(g.metric, g.target) }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :class="g.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="text-xs">
                                        {{ g.status === 'completed' ? 'Đạt' : 'Không đạt' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-xs">{{ g.end_date }}</td>
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
