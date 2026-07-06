<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import {
    BarChart3,
    Trophy,
    UserCheck,
    MessageSquare,
    Settings,
    ChevronRight,
    RefreshCw,
    CheckCircle2,
    Calendar,
    Users,
    AlertCircle,
    Star,
    Plus,
    X,
    TrendingUp,
    TrendingDown,
    DollarSign,
    Lock,
    ChefHat
} from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

// --- Types ---
type KpiMetricConfig = {
    id: number;
    restaurant_id: number;
    role: 'waiter' | 'kitchen' | 'cashier';
    metric_code: string;
    metric_name: string;
    weight: number;
    target: number;
    target_type: 'more_is_better' | 'less_is_better';
    bonus_amount: number;
    commission_rate: number;
};

type KpiMetricValue = {
    id: number;
    metric_code: string;
    metric_name: string;
    actual_value: number;
    target_value: number;
    score: number;
    is_achieved: boolean;
    bonus_earned: number;
    commission_earned: number;
};

type EmployeeKpi = {
    id: number;
    total_score: number;
    total_bonus: number;
    total_commission: number;
    status: 'draft' | 'finalized';
    metrics: KpiMetricValue[];
};

type Employee = {
    id: number;
    full_name: string;
    job_title: string;
    role_name: string;
    role: 'waiter' | 'kitchen' | 'cashier' | null;
    current_kpi: EmployeeKpi | null;
};

type LeaderboardItem = {
    id: number;
    employee_name: string;
    job_title: string;
    total_score: number;
    total_bonus: number;
    total_commission: number;
};

type PerformanceReview = {
    id: number;
    employee_id: number;
    employee_name: string;
    reviewer_name: string;
    reviewer_type: 'self' | 'manager' | 'peer';
    ratings: {
        communication: number;
        teamwork: number;
        reliability: number;
        skills: number;
    };
    average_score: number;
    comments: string | null;
    status: 'draft' | 'submitted';
    created_at: string;
};

const props = defineProps<{
    kpiConfigs: KpiMetricConfig[];
    employees: Employee[];
    leaderboard: LeaderboardItem[];
    reviews: PerformanceReview[];
    period: string;
    canManage: boolean;
}>();

// --- Active Tab ---
const activeTab = ref<'leaderboard' | 'performance' | 'reviews' | 'settings'>('leaderboard');

// --- Period Filter ---
const selectedPeriod = ref(props.period);

const changePeriod = () => {
    router.visit(route('kpis.index'), {
        data: { period: selectedPeriod.value },
        preserveState: true,
        preserveScroll: true,
    });
};

// --- Recalculate KPIs ---
const isRecalculating = ref(false);
const triggerRecalculate = () => {
    isRecalculating.value = true;
    router.post(route('kpis.recalculate'), { period: selectedPeriod.value }, {
        onSuccess: () => {
            toast.success('Đã tính toán lại toàn bộ chỉ số KPI trong tháng!');
            isRecalculating.value = false;
        },
        onError: () => {
            toast.error('Có lỗi xảy ra trong quá trình tính toán.');
            isRecalculating.value = false;
        }
    });
};

// --- Recalculating and Finalizing individual KPI ---
const isFinalizing = ref<number | null>(null);
const finalizeKpi = (kpiId: number) => {
    if (!confirm('Bạn có chắc chắn muốn chốt duyệt bảng KPI này? Số tiền thưởng sẽ được chuyển thẳng vào bảng lương nháp kỳ này.')) return;
    isFinalizing.value = kpiId;
    router.post(route('kpis.finalize', kpiId), {}, {
        onSuccess: () => {
            toast.success('Đã chốt duyệt bảng KPI của nhân viên!');
            isFinalizing.value = null;
        },
        onError: () => {
            toast.error('Có lỗi xảy ra khi duyệt bảng KPI.');
            isFinalizing.value = null;
        }
    });
};

// --- Modal: KPI Details ---
const activeEmployeeKpi = ref<Employee | null>(null);
const openKpiDetails = (employee: Employee) => {
    activeEmployeeKpi.value = employee;
};

// --- Modal: Submit Review ---
const isReviewModalOpen = ref(false);
const reviewForm = useForm({
    employee_id: '',
    reviewer_type: 'peer' as 'self' | 'manager' | 'peer',
    period: props.period,
    ratings: {
        communication: 5,
        teamwork: 5,
        reliability: 5,
        skills: 5
    },
    comments: ''
});

const submitReview = () => {
    reviewForm.post(route('kpis.reviews.store'), {
        onSuccess: () => {
            toast.success('Đã gửi phiếu đánh giá 360° thành công!');
            isReviewModalOpen.value = false;
            reviewForm.reset();
        },
        onError: (errs) => {
            Object.values(errs).forEach(e => toast.error(e));
        }
    });
};

// --- Modal: KPI Setup Configuration ---
const activeConfigEdit = ref<KpiMetricConfig | null>(null);
const configForm = useForm({
    weight: 0,
    target: 0,
    bonus_amount: 0,
    commission_rate: 0
});

const editConfig = (config: KpiMetricConfig) => {
    activeConfigEdit.value = config;
    configForm.weight = Number(config.weight);
    configForm.target = Number(config.target);
    configForm.bonus_amount = Number(config.bonus_amount);
    configForm.commission_rate = Number(config.commission_rate);
};

const updateConfig = () => {
    if (!activeConfigEdit.value) return;
    configForm.post(route('kpis.metrics.update', activeConfigEdit.value.id), {
        onSuccess: () => {
            toast.success('Đã cập nhật cấu hình chỉ tiêu KPI thành công!');
            activeConfigEdit.value = null;
        },
        onError: (errs) => {
            Object.values(errs).forEach(e => toast.error(e));
        }
    });
};

// Format currency
const formatVnd = (num: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num);
};

const getRoleBadgeColor = (role: string | null) => {
    if (role === 'waiter') return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
    if (role === 'kitchen') return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
    if (role === 'cashier') return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
    return 'bg-zinc-500/10 text-zinc-400 border-zinc-500/20';
};

const getRoleText = (role: string | null) => {
    if (role === 'waiter') return 'Phục vụ';
    if (role === 'kitchen') return 'Bếp / Pha chế';
    if (role === 'cashier') return 'Thu ngân';
    return 'Khác';
};
</script>

<template>
    <Head title="Đánh Giá Hiệu Suất & KPI" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full text-slate-800 dark:text-slate-100">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5 border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                    <BarChart3 class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Đánh Giá Hiệu Suất & KPI</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Hệ thống tính điểm KPI tự động cho Phục vụ, Bếp, Thu ngân và Phiếu đánh giá 360° định kỳ.
                    </p>
                </div>
            </div>

            <!-- Header Controls -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Period Input -->
                <div class="flex items-center gap-2 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2 shadow-sm">
                    <Calendar class="w-4 h-4 text-amber-500" />
                    <input 
                        type="month" 
                        v-model="selectedPeriod" 
                        @change="changePeriod"
                        class="bg-transparent text-slate-800 dark:text-slate-100 text-sm border-none focus:outline-none focus:ring-0 cursor-pointer"
                    />
                </div>

                <!-- Recalculate KPI -->
                <Button 
                    v-if="canManage" 
                    @click="triggerRecalculate" 
                    :disabled="isRecalculating"
                    class="h-10 text-xs bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-xl"
                >
                    <RefreshCw class="w-4 h-4 mr-1.5" :class="{ 'animate-spin': isRecalculating }" />
                    {{ isRecalculating ? 'Đang tính toán...' : 'Tính KPI Tự Động' }}
                </Button>

                <!-- Create 360 Review -->
                <Button 
                    @click="isReviewModalOpen = true" 
                    class="h-10 text-xs bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-250 font-semibold border border-slate-200 dark:border-slate-700 rounded-xl shadow-xs"
                >
                    <Plus class="w-4 h-4 mr-1.5 text-amber-500" />
                    Đánh Giá 360°
                </Button>
            </div>
        </div>

        <!-- Metric Config Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Card class="shadow-sm rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 overflow-hidden">
                <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                    <CardTitle class="text-sm font-bold text-blue-600 dark:text-blue-400 flex items-center gap-2">
                        <Users class="w-4 h-4" /> Phục vụ (Waiter)
                    </CardTitle>
                    <CardDescription class="text-xs text-slate-500 dark:text-slate-400">Mục tiêu quy định</CardDescription>
                </CardHeader>
                <CardContent class="text-xs p-4 space-y-2 text-slate-650 dark:text-slate-350">
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-1.5">
                        <span>Đơn phục vụ/ca:</span>
                        <span class="font-bold text-slate-900 dark:text-white">>= {{ props.kpiConfigs.find(c => c.metric_code === 'waiter_orders_served')?.target ?? '15' }} đơn</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-1.5">
                        <span>Điểm đánh giá KH:</span>
                        <span class="font-bold text-slate-900 dark:text-white">>= {{ props.kpiConfigs.find(c => c.metric_code === 'waiter_customer_rating')?.target ?? '4.5' }} ★</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Tốc độ phục vụ:</span>
                        <span class="font-bold text-slate-900 dark:text-white">&lt;= {{ props.kpiConfigs.find(c => c.metric_code === 'waiter_service_speed')?.target ?? '5' }} phút</span>
                    </div>
                </CardContent>
            </Card>

            <Card class="shadow-sm rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 overflow-hidden">
                <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                    <CardTitle class="text-sm font-bold text-amber-600 dark:text-amber-500 flex items-center gap-2">
                        <ChefHat class="w-4 h-4" /> Bếp (Kitchen)
                    </CardTitle>
                    <CardDescription class="text-xs text-slate-500 dark:text-slate-400">Mục tiêu quy định</CardDescription>
                </CardHeader>
                <CardContent class="text-xs p-4 space-y-2 text-slate-650 dark:text-slate-350">
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-1.5">
                        <span>Thời gian nấu TB:</span>
                        <span class="font-bold text-slate-900 dark:text-white">&lt;= {{ props.kpiConfigs.find(c => c.metric_code === 'chef_prep_time')?.target ?? '15' }} phút</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-1.5">
                        <span>Tỷ lệ món trả lại:</span>
                        <span class="font-bold text-slate-900 dark:text-white">&lt;= {{ props.kpiConfigs.find(c => c.metric_code === 'chef_rejection_rate')?.target ?? '2%' }}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Chất lượng món ăn:</span>
                        <span class="font-bold text-slate-900 dark:text-white">>= {{ props.kpiConfigs.find(c => c.metric_code === 'chef_food_rating')?.target ?? '4.5' }} ★</span>
                    </div>
                </CardContent>
            </Card>

            <Card class="shadow-sm rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 overflow-hidden">
                <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                    <CardTitle class="text-sm font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-2">
                        <DollarSign class="w-4 h-4" /> Thu ngân (Cashier)
                    </CardTitle>
                    <CardDescription class="text-xs text-slate-500 dark:text-slate-400">Mục tiêu quy định</CardDescription>
                </CardHeader>
                <CardContent class="text-xs p-4 space-y-2 text-slate-650 dark:text-slate-350">
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-1.5">
                        <span>Doanh số xử lý:</span>
                        <span class="font-bold text-slate-900 dark:text-white">>= {{ formatVnd(props.kpiConfigs.find(c => c.metric_code === 'cashier_processed_revenue')?.target ?? 50000000) }}</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-1.5">
                        <span>Tỷ lệ sai két:</span>
                        <span class="font-bold text-slate-900 dark:text-white">0%</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Tốc độ thanh toán:</span>
                        <span class="font-bold text-slate-900 dark:text-white">&lt;= {{ props.kpiConfigs.find(c => c.metric_code === 'cashier_checkout_speed')?.target ?? '2' }} phút</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex border-b border-slate-200 dark:border-slate-800">
            <button 
                @click="activeTab = 'leaderboard'"
                class="px-5 py-3 border-b-2 text-sm font-semibold flex items-center gap-2 transition"
                :class="activeTab === 'leaderboard' ? 'border-amber-600 text-amber-605 dark:border-amber-500 dark:text-amber-500' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-250'"
            >
                <Trophy class="w-4 h-4" /> Bảng Xếp Hạng
            </button>
            <button 
                @click="activeTab = 'performance'"
                class="px-5 py-3 border-b-2 text-sm font-semibold flex items-center gap-2 transition"
                :class="activeTab === 'performance' ? 'border-amber-600 text-amber-605 dark:border-amber-500 dark:text-amber-500' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-250'"
            >
                <UserCheck class="w-4 h-4" /> Chỉ Số KPI Nhân Sự
            </button>
            <button 
                @click="activeTab = 'reviews'"
                class="px-5 py-3 border-b-2 text-sm font-semibold flex items-center gap-2 transition"
                :class="activeTab === 'reviews' ? 'border-amber-600 text-amber-605 dark:border-amber-500 dark:text-amber-500' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-250'"
            >
                <MessageSquare class="w-4 h-4" /> Đánh Giá 360° ({{ reviews.length }})
            </button>
            <button 
                v-if="canManage"
                @click="activeTab = 'settings'"
                class="px-5 py-3 border-b-2 text-sm font-semibold flex items-center gap-2 transition"
                :class="activeTab === 'settings' ? 'border-amber-600 text-amber-605 dark:border-amber-500 dark:text-amber-500' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-250'"
            >
                <Settings class="w-4 h-4" /> Cấu Hình Chỉ Tiêu
            </button>
        </div>

        <!-- Tab contents -->
        <div class="mt-6">
            <!-- 1. Leaderboard Tab -->
            <div v-if="activeTab === 'leaderboard'" class="space-y-6 animate-fadeIn">
                <Card class="shadow-sm rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 overflow-hidden">
                    <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                        <CardTitle class="text-slate-900 dark:text-white font-bold text-base flex items-center gap-2">
                            <Trophy class="w-5 h-5 text-amber-500" /> Bảng Vinh Danh Nhân Viên Xuất Sắc Kỳ {{ period }}
                        </CardTitle>
                        <CardDescription class="text-xs text-slate-500 dark:text-slate-400">Xếp hạng dựa trên điểm số KPI thực tế tháng này.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-6">
                        <div v-if="leaderboard.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-500 dark:text-slate-400 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl bg-slate-50/20 dark:bg-slate-950/20">
                            <AlertCircle class="w-12 h-12 text-slate-350 dark:text-slate-600 mb-2" />
                            Chưa có dữ liệu xếp hạng tháng này. Hãy bấm "Tính KPI Tự Động" để tạo điểm.
                        </div>

                        <div v-else class="space-y-4">
                            <div 
                                v-for="(item, index) in leaderboard" 
                                :key="item.id"
                                class="flex items-center justify-between bg-slate-50/40 dark:bg-slate-950/40 p-4 rounded-xl border border-slate-100 dark:border-slate-800/80 transition hover:border-slate-200 dark:hover:border-slate-700/80"
                            >
                                <div class="flex items-center gap-4">
                                    <!-- Rank Number -->
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-black text-lg border"
                                        :class="index === 0 ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20' : 
                                                index === 1 ? 'bg-slate-350/15 text-slate-600 dark:text-slate-300 border-slate-350/20' : 
                                                index === 2 ? 'bg-amber-700/10 text-amber-800 dark:text-amber-500 border-amber-700/20' : 
                                                'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700'"
                                    >
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-900 dark:text-white text-base">{{ item.employee_name }}</h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ item.job_title }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-6">
                                    <!-- Money earned -->
                                    <div class="text-right">
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Thưởng & Hoa hồng</p>
                                        <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                            +{{ formatVnd(item.total_bonus + item.total_commission) }}
                                        </p>
                                    </div>

                                    <!-- Score Display -->
                                    <div class="text-right">
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Điểm KPI</p>
                                        <div class="flex items-center gap-2">
                                            <span class="text-base font-black text-slate-900 dark:text-white">{{ item.total_score }}</span>
                                            <span class="text-xs text-slate-500 dark:text-slate-400">/ 100</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- 2. Performance Tracker Tab -->
            <div v-if="activeTab === 'performance'" class="space-y-6 animate-fadeIn">
                <Card class="shadow-sm rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 overflow-hidden">
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold bg-slate-50/50 dark:bg-slate-900/10">
                                        <th class="py-4 px-6">Tên Nhân Viên</th>
                                        <th class="py-4 px-4">Công Việc</th>
                                        <th class="py-4 px-4 text-center">Bộ Chỉ Số KPI</th>
                                        <th class="py-4 px-4 text-right">Tổng Điểm KPI</th>
                                        <th class="py-4 px-4 text-right">Thưởng Thêm</th>
                                        <th class="py-4 px-4 text-center">Trạng Thế</th>
                                        <th class="py-4 px-6 text-right">Hành Động</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="emp in employees" :key="emp.id" class="hover:bg-slate-50/30 dark:hover:bg-slate-950/30 text-slate-700 dark:text-slate-350">
                                        <td class="py-4 px-6 font-bold text-slate-900 dark:text-white">{{ emp.full_name }}</td>
                                        <td class="py-4 px-4 text-slate-500 dark:text-slate-400">{{ emp.job_title }}</td>
                                        <td class="py-4 px-4 text-center">
                                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border" :class="getRoleBadgeColor(emp.role)">
                                                {{ getRoleText(emp.role) }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-right font-black text-slate-900 dark:text-white">
                                            {{ emp.current_kpi ? emp.current_kpi.total_score : '—' }}
                                        </td>
                                        <td class="py-4 px-4 text-right text-emerald-600 dark:text-emerald-400 font-bold">
                                            {{ emp.current_kpi ? formatVnd(emp.current_kpi.total_bonus + emp.current_kpi.total_commission) : '—' }}
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <span v-if="emp.current_kpi" class="px-2 py-0.5 text-[10px] font-bold rounded-full border"
                                                :class="emp.current_kpi.status === 'finalized' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400 border-emerald-200/50 dark:border-emerald-900/30' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400 border-amber-200/50 dark:border-amber-900/30'"
                                            >
                                                {{ emp.current_kpi.status === 'finalized' ? 'Đã chốt lương' : 'Bản nháp' }}
                                            </span>
                                            <span v-else class="text-slate-400 dark:text-slate-600 text-xs">Chưa tính</span>
                                        </td>
                                        <td class="py-4 px-6 text-right space-x-2">
                                            <!-- Details -->
                                            <Button 
                                                v-if="emp.current_kpi" 
                                                @click="openKpiDetails(emp)"
                                                size="sm"
                                                class="h-8 text-[11px] bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-250 font-semibold border border-slate-200 dark:border-slate-700 rounded-xl"
                                            >
                                                Xem Chi Tiết
                                            </Button>
 
                                            <!-- Finalize KPI -->
                                            <Button 
                                                v-if="canManage && emp.current_kpi && emp.current_kpi.status === 'draft'"
                                                @click="finalizeKpi(emp.current_kpi.id)"
                                                size="sm"
                                                class="h-8 text-[11px] bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl"
                                                :disabled="isFinalizing === emp.current_kpi.id"
                                            >
                                                Chốt Chờ Trả Lương
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
 
            <!-- 3. 360 Reviews Tab -->
            <div v-if="activeTab === 'reviews'" class="space-y-6 animate-fadeIn">
                <Card class="shadow-sm rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 overflow-hidden">
                    <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10 flex flex-row items-center justify-between">
                        <div>
                            <CardTitle class="text-slate-900 dark:text-white font-bold text-base">Đánh giá 360° Định Kỳ</CardTitle>
                            <CardDescription class="text-xs text-slate-500 dark:text-slate-400">Xem phiếu tự đánh giá, đánh giá từ đồng nghiệp và quản lý.</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent class="p-6">
                        <div v-if="reviews.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-500 dark:text-slate-400 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl bg-slate-50/20 dark:bg-slate-950/20">
                            <AlertCircle class="w-12 h-12 text-slate-350 dark:text-slate-600 mb-2" />
                            Chưa có phiếu đánh giá nào được gửi lên tháng này.
                        </div>
 
                        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <Card v-for="rev in reviews" :key="rev.id" class="shadow-xs rounded-xl bg-slate-50/30 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800/80 overflow-hidden">
                                <CardHeader class="pb-2">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <CardTitle class="text-slate-900 dark:text-white font-bold text-sm">
                                                {{ rev.employee_name }}
                                            </CardTitle>
                                            <CardDescription class="text-slate-500 dark:text-slate-400 text-xs">
                                                Người đánh giá: {{ rev.reviewer_name }} ({{ rev.reviewer_type === 'self' ? 'Tự đánh giá' : rev.reviewer_type === 'manager' ? 'Quản lý' : 'Đồng nghiệp' }})
                                            </CardDescription>
                                        </div>
                                        <div class="flex items-center gap-1 bg-amber-50 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 px-2 py-0.5 rounded-lg border border-amber-200/50 dark:border-amber-900/30">
                                            <span class="font-extrabold text-xs">{{ rev.average_score }}</span>
                                            <Star class="w-3 h-3 fill-current" />
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent class="text-xs p-4 pt-2 space-y-3">
                                    <div class="grid grid-cols-2 gap-2 border-t border-b border-slate-100 dark:border-slate-800/80 py-2 text-slate-500 dark:text-slate-400">
                                        <div>Giao tiếp: <span class="text-slate-900 dark:text-white font-bold">{{ rev.ratings.communication }}/5</span></div>
                                        <div>Làm việc nhóm: <span class="text-slate-900 dark:text-white font-bold">{{ rev.ratings.teamwork }}/5</span></div>
                                        <div>Độ tin cậy: <span class="text-slate-900 dark:text-white font-bold">{{ rev.ratings.reliability }}/5</span></div>
                                        <div>Kỹ năng chuyên môn: <span class="text-slate-900 dark:text-white font-bold">{{ rev.ratings.skills }}/5</span></div>
                                    </div>
                                    <div v-if="rev.comments" class="text-slate-650 dark:text-slate-350 italic text-[11px] leading-relaxed bg-slate-100/50 dark:bg-zinc-900/30 p-2.5 rounded-lg border border-slate-150 dark:border-zinc-800/50">
                                        "{{ rev.comments }}"
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- 4. KPI Setup Tab -->
            <div v-if="activeTab === 'settings'" class="space-y-6 animate-fadeIn">
                <Card class="shadow-sm rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 overflow-hidden">
                    <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                        <CardTitle class="text-slate-900 dark:text-white font-bold text-base">Cấu Hình Bộ Luật KPI Chỉ Tiêu Hệ Thống</CardTitle>
                        <CardDescription class="text-xs text-slate-500 dark:text-slate-400">Chỉ có Owner và Manager mới có quyền điều chỉnh bộ quy chuẩn, tỷ trọng và thưởng nóng chỉ tiêu này.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold bg-slate-50/50 dark:bg-slate-900/10">
                                        <th class="py-4 px-6">Vai Trò</th>
                                        <th class="py-4 px-4">Tên Chỉ Số</th>
                                        <th class="py-4 px-4 text-right">Tỷ Trọng Điểm (%)</th>
                                        <th class="py-4 px-4 text-right">Chỉ Tiêu Đề Ra</th>
                                        <th class="py-4 px-4 text-right">Tiền Thưởng Đạt</th>
                                        <th class="py-4 px-4 text-right">Hoa Hồng (%)</th>
                                        <th class="py-4 px-6 text-right">Hành Động</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="cfg in kpiConfigs" :key="cfg.id" class="hover:bg-slate-50/30 dark:hover:bg-slate-950/30 text-slate-700 dark:text-slate-350">
                                        <td class="py-3.5 px-6 font-semibold">
                                            <span class="px-2 py-0.5 rounded text-[10px] border font-bold" :class="getRoleBadgeColor(cfg.role)">
                                                {{ getRoleText(cfg.role) }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-700 dark:text-slate-305 font-medium">{{ cfg.metric_name }}</td>
                                        <td class="py-3.5 px-4 text-right text-slate-900 dark:text-white font-bold">{{ cfg.weight }}%</td>
                                        <td class="py-3.5 px-4 text-right text-slate-900 dark:text-white font-bold">
                                            {{ cfg.target }} {{ cfg.target_type === 'less_is_better' ? '(Tối Đa)' : '(Tối Thiểu)' }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right text-emerald-650 dark:text-emerald-400 font-bold">{{ formatVnd(cfg.bonus_amount) }}</td>
                                        <td class="py-3.5 px-4 text-right text-amber-600 dark:text-amber-500 font-bold">{{ cfg.commission_rate }}%</td>
                                        <td class="py-3.5 px-6 text-right">
                                            <Button 
                                                @click="editConfig(cfg)"
                                                size="sm"
                                                class="h-8 text-[11px] bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-250 font-semibold border border-slate-200 dark:border-slate-700 rounded-xl"
                                            >
                                                Sửa Chỉ Tiêu
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- MODAL: KPI Details (Slide-over / Pop-up) -->
        <div v-if="activeEmployeeKpi" class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50 backdrop-blur-xs">
            <Card class="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 w-full max-w-2xl text-slate-800 dark:text-slate-100 max-h-[90vh] overflow-y-auto shadow-2xl rounded-2xl">
                <CardHeader class="flex flex-row items-center justify-between border-b border-slate-150 dark:border-slate-850 pb-4">
                    <div>
                        <CardTitle class="text-slate-900 dark:text-white text-lg font-bold">Bảng KPI Chi Tiết - {{ activeEmployeeKpi.full_name }}</CardTitle>
                        <CardDescription class="text-xs text-slate-500 dark:text-slate-400">{{ activeEmployeeKpi.job_title }} | Kỳ: {{ period }}</CardDescription>
                    </div>
                    <Button @click="activeEmployeeKpi = null" variant="ghost" class="p-2 text-slate-400 hover:text-slate-650 dark:hover:text-white">
                        <X class="w-5 h-5" />
                    </Button>
                </CardHeader>
                <CardContent class="p-6 space-y-6">
                    <!-- Overall KPI Summary -->
                    <div class="bg-slate-50 dark:bg-slate-950/60 p-4 rounded-xl border border-slate-150 dark:border-slate-850 grid grid-cols-3 text-center">
                        <div class="border-r border-slate-200 dark:border-slate-800">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Tổng điểm KPI</p>
                            <p class="text-2xl font-black text-slate-900 dark:text-white">{{ activeEmployeeKpi.current_kpi?.total_score }}</p>
                        </div>
                        <div class="border-r border-slate-200 dark:border-slate-800">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Tiền thưởng đạt</p>
                            <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">
                                {{ formatVnd(activeEmployeeKpi.current_kpi?.total_bonus ?? 0) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Hoa hồng doanh số</p>
                            <p class="text-lg font-bold text-amber-600 dark:text-amber-500">
                                {{ formatVnd(activeEmployeeKpi.current_kpi?.total_commission ?? 0) }}
                            </p>
                        </div>
                    </div>

                    <!-- Metrics Details List -->
                    <div class="space-y-4">
                        <h4 class="font-bold text-slate-500 dark:text-slate-400 text-xs">CHI TIẾT CHỈ TIÊU KPI THỰC TẾ:</h4>
                        
                        <div v-for="metric in activeEmployeeKpi.current_kpi?.metrics" :key="metric.id" class="p-4 bg-slate-50/50 dark:bg-slate-950/40 border border-slate-150 dark:border-slate-800/80 rounded-xl space-y-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h5 class="text-sm font-bold text-slate-900 dark:text-white">{{ metric.metric_name }}</h5>
                                    <p class="text-xs text-slate-550 dark:text-slate-450">Code: {{ metric.metric_code }}</p>
                                </div>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded"
                                    :class="metric.is_achieved ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-900/30' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/30 dark:text-rose-400 border border-rose-200/50 dark:border-rose-900/30'"
                                >
                                    {{ metric.is_achieved ? 'Đạt chỉ tiêu' : 'Chưa đạt' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-2 text-xs border-t border-b border-slate-150 dark:border-slate-855 py-2 text-slate-500 dark:text-slate-400">
                                <div>Mục tiêu: <span class="text-slate-900 dark:text-white font-bold">{{ metric.target_value }}</span></div>
                                <div>Thực tế: <span class="text-slate-900 dark:text-white font-bold">{{ metric.actual_value }}</span></div>
                                <div>Chấm điểm: <span class="text-slate-900 dark:text-white font-black text-sm">{{ metric.score }} / 100</span></div>
                            </div>

                            <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span>Thưởng nóng đạt chỉ tiêu: <strong class="text-emerald-600 dark:text-emerald-400">{{ formatVnd(metric.bonus_earned) }}</strong></span>
                                <span>Hoa hồng nhận: <strong class="text-amber-600 dark:text-amber-500">{{ formatVnd(metric.commission_earned) }}</strong></span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- MODAL: Edit Metric Config Setup -->
        <div v-if="activeConfigEdit" class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50 backdrop-blur-xs">
            <Card class="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 w-full max-w-md text-slate-800 dark:text-slate-100 shadow-2xl rounded-2xl overflow-hidden">
                <CardHeader class="flex flex-row items-center justify-between border-b border-slate-150 dark:border-slate-850 pb-4">
                    <div>
                        <CardTitle class="text-slate-900 dark:text-white text-lg font-bold">Sửa Chỉ Tiêu KPI</CardTitle>
                        <CardDescription class="text-xs text-slate-500 dark:text-slate-400">{{ activeConfigEdit.metric_name }}</CardDescription>
                    </div>
                    <Button @click="activeConfigEdit = null" variant="ghost" class="p-2 text-slate-400 hover:text-slate-650 dark:hover:text-white">
                        <X class="w-5 h-5" />
                    </Button>
                </CardHeader>
                <form @submit.prevent="updateConfig">
                    <CardContent class="p-6 space-y-4 text-xs">
                        <div class="space-y-1.5">
                            <Label for="weight" class="text-slate-600 dark:text-slate-400 font-semibold">Tỷ trọng điểm (%) trong tổng điểm KPI</Label>
                            <Input 
                                id="weight" 
                                type="number" 
                                v-model.number="configForm.weight" 
                                min="0" max="100" 
                                class="rounded-xl border-slate-250 dark:border-slate-800" 
                                required
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="target" class="text-slate-600 dark:text-slate-400 font-semibold">Giá trị chỉ tiêu mục tiêu đề ra</Label>
                            <Input 
                                id="target" 
                                type="number" 
                                step="any"
                                v-model.number="configForm.target" 
                                min="0" 
                                class="rounded-xl border-slate-250 dark:border-slate-800" 
                                required
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="bonus" class="text-slate-600 dark:text-slate-400 font-semibold">Tiền thưởng nóng đạt chỉ tiêu (VND)</Label>
                            <Input 
                                id="bonus" 
                                type="number" 
                                v-model.number="configForm.bonus_amount" 
                                min="0" 
                                class="rounded-xl border-slate-250 dark:border-slate-800" 
                                required
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="commission" class="text-slate-600 dark:text-slate-400 font-semibold">Phần trăm hoa hồng doanh số xử lý (%) (nếu có)</Label>
                            <Input 
                                id="commission" 
                                type="number" 
                                step="0.01"
                                v-model.number="configForm.commission_rate" 
                                min="0" max="100" 
                                class="rounded-xl border-slate-250 dark:border-slate-800" 
                                required
                            />
                        </div>
                    </CardContent>
                    <div class="flex justify-end gap-3 p-6 border-t border-slate-150 dark:border-slate-850 bg-slate-50/50 dark:bg-slate-950/20">
                        <Button type="button" @click="activeConfigEdit = null" variant="ghost" class="text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white font-semibold">
                            Hủy
                        </Button>
                        <Button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-xl">
                            Cập Nhật
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- MODAL: Submit 360-Degree Review Form -->
        <div v-if="isReviewModalOpen" class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50 backdrop-blur-xs">
            <Card class="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 w-full max-w-lg text-slate-800 dark:text-slate-100 max-h-[90vh] overflow-y-auto shadow-2xl rounded-2xl overflow-hidden">
                <CardHeader class="flex flex-row items-center justify-between border-b border-slate-150 dark:border-slate-855 pb-4">
                    <div>
                        <CardTitle class="text-slate-900 dark:text-white text-lg font-bold">Phiếu Đánh Giá Hiệu Suất 360°</CardTitle>
                        <CardDescription class="text-xs text-slate-500 dark:text-slate-400">Gửi phiếu tự đánh giá, đánh giá quản lý hoặc đồng nghiệp.</CardDescription>
                    </div>
                    <Button @click="isReviewModalOpen = false" variant="ghost" class="p-2 text-slate-400 hover:text-slate-650 dark:hover:text-white">
                        <X class="w-5 h-5" />
                    </Button>
                </CardHeader>
                <form @submit.prevent="submitReview">
                    <CardContent class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="review_employee" class="text-slate-600 dark:text-slate-400 text-xs font-semibold">Chọn Nhân Sự Chấm Điểm</Label>
                                <select 
                                    id="review_employee" 
                                    v-model="reviewForm.employee_id"
                                    class="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white p-2.5 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm cursor-pointer"
                                    required
                                >
                                    <option value="" disabled>-- Chọn nhân viên --</option>
                                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.full_name }}</option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="reviewer_type" class="text-slate-600 dark:text-slate-400 text-xs font-semibold">Mối Quan Hệ Đánh Giá</Label>
                                <select 
                                    id="reviewer_type" 
                                    v-model="reviewForm.reviewer_type"
                                    class="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white p-2.5 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm cursor-pointer"
                                    required
                                >
                                    <option value="self">Tự đánh giá bản thân</option>
                                    <option value="manager">Quản lý đánh giá</option>
                                    <option value="peer">Đồng nghiệp đánh giá chéo</option>
                                </select>
                            </div>
                        </div>

                        <!-- Ratings section -->
                        <div class="space-y-4 border-t border-slate-150 dark:border-slate-850 pt-4">
                            <h4 class="font-bold text-amber-600 dark:text-amber-550 text-xs tracking-wider">CHẤM ĐIỂM TIÊU CHÍ (Từ 1 đến 5 sao):</h4>

                            <div class="space-y-3">
                                <!-- Communication -->
                                <div class="flex justify-between items-center bg-slate-50/40 dark:bg-zinc-950/40 p-3 rounded-xl border border-slate-150 dark:border-slate-800/80">
                                    <div class="pr-2">
                                        <Label class="text-slate-900 dark:text-white font-bold text-sm">Kỹ năng giao tiếp</Label>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-450 leading-tight">Khả năng truyền đạt, trao đổi thông tin với khách hàng & đồng đội.</p>
                                    </div>
                                    <input 
                                        type="number" 
                                        v-model.number="reviewForm.ratings.communication" 
                                        min="1" max="5" 
                                        class="bg-white dark:bg-slate-950 border border-slate-250 dark:border-slate-800 rounded-xl px-2 py-1 text-slate-900 dark:text-white font-bold w-14 text-center text-sm"
                                        required
                                    />
                                </div>

                                <!-- Teamwork -->
                                <div class="flex justify-between items-center bg-slate-50/40 dark:bg-zinc-950/40 p-3 rounded-xl border border-slate-150 dark:border-slate-800/80">
                                    <div class="pr-2">
                                        <Label class="text-slate-900 dark:text-white font-bold text-sm">Làm việc nhóm</Label>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-450 leading-tight">Khả năng phối hợp đồng đội, hỗ trợ chạy việc trong ca.</p>
                                    </div>
                                    <input 
                                        type="number" 
                                        v-model.number="reviewForm.ratings.teamwork" 
                                        min="1" max="5" 
                                        class="bg-white dark:bg-slate-950 border border-slate-250 dark:border-slate-800 rounded-xl px-2 py-1 text-slate-900 dark:text-white font-bold w-14 text-center text-sm"
                                        required
                                    />
                                </div>

                                <!-- Reliability -->
                                <div class="flex justify-between items-center bg-slate-50/40 dark:bg-zinc-950/40 p-3 rounded-xl border border-slate-150 dark:border-slate-800/80">
                                    <div class="pr-2">
                                        <Label class="text-slate-900 dark:text-white font-bold text-sm">Độ tin cậy & Đúng giờ</Label>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-450 leading-tight">Ý thức giờ giấc đi làm, chấp hành kỷ luật.</p>
                                    </div>
                                    <input 
                                        type="number" 
                                        v-model.number="reviewForm.ratings.reliability" 
                                        min="1" max="5" 
                                        class="bg-white dark:bg-slate-950 border border-slate-250 dark:border-slate-800 rounded-xl px-2 py-1 text-slate-900 dark:text-white font-bold w-14 text-center text-sm"
                                        required
                                    />
                                </div>

                                <!-- Skills -->
                                <div class="flex justify-between items-center bg-slate-50/40 dark:bg-zinc-950/40 p-3 rounded-xl border border-slate-150 dark:border-slate-800/80">
                                    <div class="pr-2">
                                        <Label class="text-slate-900 dark:text-white font-bold text-sm">Kỹ năng nghiệp vụ chuyên môn</Label>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-450 leading-tight">Trình độ làm bếp, phục vụ hoặc thu ngân chuẩn quy trình.</p>
                                    </div>
                                    <input 
                                        type="number" 
                                        v-model.number="reviewForm.ratings.skills" 
                                        min="1" max="5" 
                                        class="bg-white dark:bg-slate-950 border border-slate-250 dark:border-slate-800 rounded-xl px-2 py-1 text-slate-900 dark:text-white font-bold w-14 text-center text-sm"
                                        required
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Comments -->
                        <div class="space-y-1.5">
                            <Label for="comments" class="text-slate-600 dark:text-slate-400 text-xs font-semibold">Ý kiến đánh giá khác / Nhận xét tổng quan</Label>
                            <textarea 
                                id="comments" 
                                v-model="reviewForm.comments" 
                                rows="3"
                                class="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white p-2.5 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm"
                                placeholder="Nhập ý kiến đóng góp ý kiến xây dựng cho nhân sự tại đây..."
                            ></textarea>
                        </div>
                    </CardContent>
                    <div class="flex justify-end gap-3 p-6 border-t border-slate-150 dark:border-slate-850 bg-slate-50/50 dark:bg-slate-950/20">
                        <Button type="button" @click="isReviewModalOpen = false" variant="ghost" class="text-slate-500 hover:text-slate-850 dark:text-slate-400 dark:hover:text-white font-semibold">
                            Hủy
                        </Button>
                        <Button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-xl">
                            Gửi Đánh Giá
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    </div>
</template>

<style scoped>
.animate-fadeIn {
    animation: fadeIn 0.25s ease-out forwards;
}
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
