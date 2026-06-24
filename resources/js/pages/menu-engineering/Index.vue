<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    BarChart3, TrendingUp, TrendingDown, Minus, Star, FlaskConical,
    Play, Square, Loader2, Clock, Sun, Sunset, Moon, Leaf,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface ScoringItem {
    product_id: number; name: string; price: number; cost_price: number;
    qty: number; revenue: number; margin: number;
    popularity_score: number; profitability_score: number; trend_score: number;
    composite_score: number; suggestion: string; weekly_avg: number;
}

const props = defineProps<{
    scoring: ScoringItem[];
    priceTests: any[];
    days: number;
}>();

const page = usePage();
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
});

const activeTab = ref<'scoring' | 'ab-test' | 'schedule'>('scoring');

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

function submitTest() {
    testForm.post('/menu-engineering/price-tests', {
        onSuccess: () => { showTestDialog.value = false; },
    });
}

function completeTest(test: any) {
    if (!confirm('Kết thúc test và khôi phục giá gốc?')) return;
    router.post(`/menu-engineering/price-tests/${test.id}/complete`);
}

function cancelTest(test: any) {
    if (!confirm('Hủy test và khôi phục giá gốc?')) return;
    router.post(`/menu-engineering/price-tests/${test.id}/cancel`);
}

// Time slot / season update
function updateSchedule(productId: number, field: string, value: string | null) {
    router.patch(`/menu-engineering/products/${productId}/time-slot`, {
        [field]: value || null,
    }, { preserveScroll: true });
}

// Score color
function scoreColor(score: number): string {
    if (score >= 70) return 'text-green-600';
    if (score >= 40) return 'text-amber-500';
    return 'text-red-500';
}

function scoreBg(score: number): string {
    if (score >= 70) return 'bg-green-100 dark:bg-green-900/30';
    if (score >= 40) return 'bg-amber-100 dark:bg-amber-900/30';
    return 'bg-red-100 dark:bg-red-900/30';
}

const avgScore = computed(() => {
    if (!props.scoring.length) return 0;
    return Math.round(props.scoring.reduce((s, i) => s + i.composite_score, 0) / props.scoring.length);
});

const topPerformers = computed(() => props.scoring.filter(s => s.composite_score >= 70).length);
const needsAttention = computed(() => props.scoring.filter(s => s.composite_score < 40).length);
</script>

<template>
    <Head title="Menu Engineering" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 dark:bg-violet-950/60 text-violet-600">
                <BarChart3 class="size-6" />
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Menu Engineering Nâng Cao</h1>
                <p class="text-sm text-muted-foreground">Phân tích, chấm điểm, A/B testing giá, và lập lịch menu theo giờ/mùa.</p>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <Card>
                <CardContent class="p-4 flex items-center gap-3">
                    <Star class="size-8 text-amber-500" />
                    <div>
                        <p class="text-xs text-muted-foreground">Điểm TB menu</p>
                        <p :class="['text-2xl font-bold', scoreColor(avgScore)]">{{ avgScore }}/100</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4 flex items-center gap-3">
                    <TrendingUp class="size-8 text-green-500" />
                    <div>
                        <p class="text-xs text-muted-foreground">Top performers</p>
                        <p class="text-2xl font-bold text-green-600">{{ topPerformers }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4 flex items-center gap-3">
                    <TrendingDown class="size-8 text-red-500" />
                    <div>
                        <p class="text-xs text-muted-foreground">Cần cải thiện</p>
                        <p class="text-2xl font-bold text-red-500">{{ needsAttention }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4 flex items-center gap-3">
                    <FlaskConical class="size-8 text-blue-500" />
                    <div>
                        <p class="text-xs text-muted-foreground">A/B Tests đang chạy</p>
                        <p class="text-2xl font-bold text-blue-600">{{ priceTests.filter(t => t.status === 'running').length }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 border-b pb-2">
            <button v-for="tab in [
                { key: 'scoring', label: 'Menu Scoring' },
                { key: 'ab-test', label: 'A/B Testing giá' },
                { key: 'schedule', label: 'Lịch menu theo giờ/mùa' },
            ]" :key="tab.key" @click="activeTab = tab.key as any"
                :class="['px-4 py-2 rounded-lg text-sm font-semibold transition-colors',
                    activeTab === tab.key ? 'bg-violet-600 text-white' : 'hover:bg-muted']"
            >{{ tab.label }}</button>
        </div>

        <!-- Tab: Scoring -->
        <div v-if="activeTab === 'scoring'">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Bảng điểm Menu ({{ days }} ngày gần nhất)</CardTitle>
                    <CardDescription>Điểm tổng hợp = Popularity (40%) × Profitability (35%) × Trend (25%)</CardDescription>
                </CardHeader>
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr class="text-left text-xs text-muted-foreground">
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3">Món</th>
                                <th class="px-4 py-3 text-center">Tổng điểm</th>
                                <th class="px-4 py-3 text-center">Popularity</th>
                                <th class="px-4 py-3 text-center">Profit</th>
                                <th class="px-4 py-3 text-center">Trend</th>
                                <th class="px-4 py-3 text-right">Doanh thu</th>
                                <th class="px-4 py-3 text-right">SL/tuần</th>
                                <th class="px-4 py-3">AI Gợi ý</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, idx) in scoring" :key="item.product_id" class="border-b last:border-0 hover:bg-muted/30">
                                <td class="px-4 py-3 text-muted-foreground">{{ idx + 1 }}</td>
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="font-medium">{{ item.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ item.price.toLocaleString() }}đ | Margin {{ item.margin }}%</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-sm font-bold', scoreBg(item.composite_score), scoreColor(item.composite_score)]">
                                        {{ item.composite_score }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-xs">{{ item.popularity_score }}</td>
                                <td class="px-4 py-3 text-center text-xs">{{ item.profitability_score }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="flex items-center justify-center gap-0.5 text-xs">
                                        <TrendingUp v-if="item.trend_score >= 60" class="size-3 text-green-500" />
                                        <TrendingDown v-else-if="item.trend_score < 40" class="size-3 text-red-500" />
                                        <Minus v-else class="size-3 text-muted-foreground" />
                                        {{ item.trend_score }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-xs font-medium">{{ item.revenue.toLocaleString() }}đ</td>
                                <td class="px-4 py-3 text-right text-xs">{{ item.weekly_avg }}</td>
                                <td class="px-4 py-3 text-xs max-w-xs">
                                    <p class="line-clamp-2">{{ item.suggestion }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!scoring.length" class="text-center text-muted-foreground py-12">Chưa có dữ liệu đơn hàng để phân tích.</p>
                </CardContent>
            </Card>
        </div>

        <!-- Tab: A/B Testing -->
        <div v-if="activeTab === 'ab-test'" class="space-y-4">
            <div class="flex justify-end">
                <Button @click="openCreateTest()" class="gap-1.5">
                    <FlaskConical class="size-4" /> Tạo A/B Test mới
                </Button>
            </div>

            <Card v-for="test in priceTests" :key="test.id">
                <CardContent class="p-4 flex items-center justify-between">
                    <div>
                        <p class="font-medium">{{ test.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ test.product?.name }} |
                            Giá gốc: {{ Number(test.original_price).toLocaleString() }}đ →
                            Giá test: {{ Number(test.test_price).toLocaleString() }}đ
                        </p>
                        <div v-if="test.results_json" class="mt-1 text-xs">
                            <span class="text-muted-foreground">Orders: {{ test.orders_original }} vs {{ test.orders_test }}</span>
                            <span v-if="test.results_json.impact_percent != null" class="ml-2 font-bold"
                                :class="test.results_json.impact_percent >= 0 ? 'text-green-600' : 'text-red-500'">
                                Impact: {{ test.results_json.impact_percent > 0 ? '+' : '' }}{{ test.results_json.impact_percent }}%
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge :variant="test.status === 'running' ? 'default' : 'secondary'">{{ test.status }}</Badge>
                        <Button v-if="test.status === 'running'" variant="outline" size="sm" @click="completeTest(test)">
                            <Square class="size-3.5 mr-1" /> Kết thúc
                        </Button>
                        <Button v-if="test.status === 'running'" variant="ghost" size="sm" class="text-red-500" @click="cancelTest(test)">
                            Hủy
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <p v-if="!priceTests.length" class="text-center text-muted-foreground py-12">
                Chưa có A/B test nào. Tạo test mới để so sánh hiệu quả giá bán.
            </p>
        </div>

        <!-- Tab: Schedule -->
        <div v-if="activeTab === 'schedule'">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Lịch hiển thị menu theo giờ & mùa</CardTitle>
                    <CardDescription>Để trống = hiển thị cả ngày, mọi mùa. Chỉ áp dụng cho QR menu và online ordering.</CardDescription>
                </CardHeader>
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr class="text-left text-xs text-muted-foreground">
                                <th class="px-4 py-3">Món</th>
                                <th class="px-4 py-3">Giá</th>
                                <th class="px-4 py-3">Khung giờ</th>
                                <th class="px-4 py-3">Mùa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in scoring" :key="item.product_id" class="border-b last:border-0">
                                <td class="px-4 py-3 font-medium">{{ item.name }}</td>
                                <td class="px-4 py-3 text-xs">{{ item.price.toLocaleString() }}đ</td>
                                <td class="px-4 py-3">
                                    <select
                                        class="h-8 rounded-md border bg-background px-2 text-xs"
                                        @change="(e: any) => updateSchedule(item.product_id, 'time_slot', e.target.value)"
                                    >
                                        <option value="">Cả ngày</option>
                                        <option value="morning">Sáng (trước 11h)</option>
                                        <option value="lunch">Trưa (11h-14h)</option>
                                        <option value="afternoon">Chiều (14h-17h)</option>
                                        <option value="dinner">Tối (sau 17h)</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <select
                                        class="h-8 rounded-md border bg-background px-2 text-xs"
                                        @change="(e: any) => updateSchedule(item.product_id, 'season', e.target.value)"
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
                    <select v-model="testForm.product_id" required class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                        <option :value="null" disabled>Chọn món...</option>
                        <option v-for="s in scoring" :key="s.product_id" :value="s.product_id">
                            {{ s.name }} ({{ s.price.toLocaleString() }}đ)
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Tên test</Label>
                    <Input v-model="testForm.name" placeholder="Ví dụ: Test tăng giá Phở 10%" required />
                </div>
                <div class="grid gap-1.5">
                    <Label>Giá thử nghiệm (VND)</Label>
                    <Input type="number" v-model="testForm.test_price" required />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Bắt đầu</Label>
                        <Input type="date" v-model="testForm.start_at" required />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Kết thúc</Label>
                        <Input type="date" v-model="testForm.end_at" required />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="showTestDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="testForm.processing" class="gap-1.5">
                        <Loader2 v-if="testForm.processing" class="size-4 animate-spin" />
                        <Play v-else class="size-4" />
                        {{ testForm.processing ? 'Đang tạo...' : 'Bắt đầu test' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
