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
    Lock
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

    <div class="space-y-6 p-6 pb-24 text-zinc-100 max-w-7xl mx-auto">
        <!-- Top Title and Controls -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-zinc-800 pb-5">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center gap-2">
                    <BarChart3 class="w-8 h-8 text-amber-500" />
                    Đánh Giá Hiệu Suất & KPI
                </h1>
                <p class="text-sm text-zinc-400 mt-1">
                    Hệ thống tính điểm KPI tự động cho Phục vụ, Bếp, Thu ngân và Phiếu đánh giá 360° định kỳ.
                </p>
            </div>

            <!-- Header Controls -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Period Input -->
                <div class="flex items-center gap-2 bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2">
                    <Calendar class="w-4 h-4 text-amber-500" />
                    <input 
                        type="month" 
                        v-model="selectedPeriod" 
                        @change="changePeriod"
                        class="bg-transparent text-white text-sm border-none focus:outline-none focus:ring-0 cursor-pointer"
                    />
                </div>

                <!-- Recalculate KPI -->
                <Button 
                    v-if="canManage" 
                    @click="triggerRecalculate" 
                    :disabled="isRecalculating"
                    class="bg-amber-600 hover:bg-amber-700 text-white flex items-center gap-2"
                >
                    <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isRecalculating }" />
                    {{ isRecalculating ? 'Đang tính toán...' : 'Tính KPI Tự Động' }}
                </Button>

                <!-- Create 360 Review -->
                <Button 
                    @click="isReviewModalOpen = true" 
                    class="bg-zinc-800 hover:bg-zinc-700 text-white flex items-center gap-2 border border-zinc-700"
                >
                    <Plus class="w-4 h-4" />
                    Đánh Giá 360°
                </Button>
            </div>
        </div>

        <!-- Metric Config Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Card class="bg-zinc-900/60 border-zinc-800 backdrop-blur-md">
                <CardHeader class="pb-2">
                    <CardTitle class="text-blue-400 flex items-center gap-2">
                        <Users class="w-5 h-5" /> Phục vụ (Waiter)
                    </CardTitle>
                    <CardDescription class="text-zinc-500">Mục tiêu quy định</CardDescription>
                </CardHeader>
                <CardContent class="text-sm space-y-2 text-zinc-300">
                    <div class="flex justify-between border-b border-zinc-800 pb-1">
                        <span>Đơn phục vụ/ca:</span>
                        <span class="font-semibold text-white">>= {{ props.kpiConfigs.find(c => c.metric_code === 'waiter_orders_served')?.target ?? '15' }} đơn</span>
                    </div>
                    <div class="flex justify-between border-b border-zinc-800 pb-1">
                        <span>Điểm đánh giá KH:</span>
                        <span class="font-semibold text-white">>= {{ props.kpiConfigs.find(c => c.metric_code === 'waiter_customer_rating')?.target ?? '4.5' }} ★</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Tốc độ phục vụ:</span>
                        <span class="font-semibold text-white">&lt;= {{ props.kpiConfigs.find(c => c.metric_code === 'waiter_service_speed')?.target ?? '5' }} phút</span>
                    </div>
                </CardContent>
            </Card>

            <Card class="bg-zinc-900/60 border-zinc-800 backdrop-blur-md">
                <CardHeader class="pb-2">
                    <CardTitle class="text-amber-500 flex items-center gap-2">
                        <ChefHat class="w-5 h-5" /> Bếp (Kitchen)
                    </CardTitle>
                    <CardDescription class="text-zinc-500">Mục tiêu quy định</CardDescription>
                </CardHeader>
                <CardContent class="text-sm space-y-2 text-zinc-300">
                    <div class="flex justify-between border-b border-zinc-800 pb-1">
                        <span>Thời gian nấu TB:</span>
                        <span class="font-semibold text-white">&lt;= {{ props.kpiConfigs.find(c => c.metric_code === 'chef_prep_time')?.target ?? '15' }} phút</span>
                    </div>
                    <div class="flex justify-between border-b border-zinc-800 pb-1">
                        <span>Tỷ lệ món trả lại:</span>
                        <span class="font-semibold text-white">&lt;= {{ props.kpiConfigs.find(c => c.metric_code === 'chef_rejection_rate')?.target ?? '2%' }}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Chất lượng món ăn:</span>
                        <span class="font-semibold text-white">>= {{ props.kpiConfigs.find(c => c.metric_code === 'chef_food_rating')?.target ?? '4.5' }} ★</span>
                    </div>
                </CardContent>
            </Card>

            <Card class="bg-zinc-900/60 border-zinc-800 backdrop-blur-md">
                <CardHeader class="pb-2">
                    <CardTitle class="text-emerald-400 flex items-center gap-2">
                        <DollarSign class="w-5 h-5" /> Thu ngân (Cashier)
                    </CardTitle>
                    <CardDescription class="text-zinc-500">Mục tiêu quy định</CardDescription>
                </CardHeader>
                <CardContent class="text-sm space-y-2 text-zinc-300">
                    <div class="flex justify-between border-b border-zinc-800 pb-1">
                        <span>Doanh số xử lý:</span>
                        <span class="font-semibold text-white">>= {{ formatVnd(props.kpiConfigs.find(c => c.metric_code === 'cashier_processed_revenue')?.target ?? 50000000) }}</span>
                    </div>
                    <div class="flex justify-between border-b border-zinc-800 pb-1">
                        <span>Tỷ lệ sai két:</span>
                        <span class="font-semibold text-white">0%</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Tốc độ thanh toán:</span>
                        <span class="font-semibold text-white">&lt;= {{ props.kpiConfigs.find(c => c.metric_code === 'cashier_checkout_speed')?.target ?? '2' }} phút</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex border-b border-zinc-800">
            <button 
                @click="activeTab = 'leaderboard'"
                class="px-5 py-3 border-b-2 text-sm font-semibold flex items-center gap-2 transition"
                :class="activeTab === 'leaderboard' ? 'border-amber-500 text-amber-500' : 'border-transparent text-zinc-400 hover:text-white'"
            >
                <Trophy class="w-4 h-4" /> Bảng Xếp Hạng
            </button>
            <button 
                @click="activeTab = 'performance'"
                class="px-5 py-3 border-b-2 text-sm font-semibold flex items-center gap-2 transition"
                :class="activeTab === 'performance' ? 'border-amber-500 text-amber-500' : 'border-transparent text-zinc-400 hover:text-white'"
            >
                <UserCheck class="w-4 h-4" /> Chỉ Số KPI Nhân Sự
            </button>
            <button 
                @click="activeTab = 'reviews'"
                class="px-5 py-3 border-b-2 text-sm font-semibold flex items-center gap-2 transition"
                :class="activeTab === 'reviews' ? 'border-amber-500 text-amber-500' : 'border-transparent text-zinc-400 hover:text-white'"
            >
                <MessageSquare class="w-4 h-4" /> Đánh Giá 360° ({{ reviews.length }})
            </button>
            <button 
                v-if="canManage"
                @click="activeTab = 'settings'"
                class="px-5 py-3 border-b-2 text-sm font-semibold flex items-center gap-2 transition"
                :class="activeTab === 'settings' ? 'border-amber-500 text-amber-500' : 'border-transparent text-zinc-400 hover:text-white'"
            >
                <Settings class="w-4 h-4" /> Cấu Hình Chỉ Tiêu
            </button>
        </div>

        <!-- Tab contents -->
        <div class="mt-6">
            <!-- 1. Leaderboard Tab -->
            <div v-if="activeTab === 'leaderboard'" class="space-y-6 animate-fadeIn">
                <Card class="bg-zinc-900 border-zinc-800">
                    <CardHeader>
                        <CardTitle class="text-white text-xl flex items-center gap-2">
                            <Trophy class="w-5 h-5 text-amber-400" /> Bảng Vinh Danh Nhân Viên Xuất Sắc Kỳ {{ period }}
                        </CardTitle>
                        <CardDescription class="text-zinc-500">Xếp hạng dựa trên điểm số KPI thực tế tháng này.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="leaderboard.length === 0" class="flex flex-col items-center justify-center py-12 text-zinc-500 border border-dashed border-zinc-800 rounded-lg">
                            <AlertCircle class="w-12 h-12 text-zinc-600 mb-2" />
                            Chưa có dữ liệu xếp hạng tháng này. Hãy bấm "Tính KPI Tự Động" để tạo điểm.
                        </div>

                        <div v-else class="space-y-4">
                            <div 
                                v-for="(item, index) in leaderboard" 
                                :key="item.id"
                                class="flex items-center justify-between bg-zinc-950/60 p-4 rounded-xl border border-zinc-800 transition hover:border-zinc-700"
                            >
                                <div class="flex items-center gap-4">
                                    <!-- Rank Number -->
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-black text-lg border"
                                        :class="index === 0 ? 'bg-amber-500/20 text-amber-400 border-amber-500/50' : 
                                                index === 1 ? 'bg-zinc-400/20 text-zinc-300 border-zinc-400/50' : 
                                                index === 2 ? 'bg-orange-900/20 text-orange-400 border-orange-800/50' : 
                                                'bg-zinc-900 text-zinc-500 border-zinc-800'"
                                    >
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-white text-base">{{ item.employee_name }}</h3>
                                        <p class="text-xs text-zinc-500">{{ item.job_title }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-6">
                                    <!-- Money earned -->
                                    <div class="text-right">
                                        <p class="text-xs text-zinc-500">Thưởng & Hoa hồng</p>
                                        <p class="text-sm font-semibold text-emerald-400">
                                            +{{ formatVnd(item.total_bonus + item.total_commission) }}
                                        </p>
                                    </div>

                                    <!-- Score Display -->
                                    <div class="text-right">
                                        <p class="text-xs text-zinc-500">Điểm KPI</p>
                                        <div class="flex items-center gap-2">
                                            <span class="text-base font-extrabold text-white">{{ item.total_score }}</span>
                                            <span class="text-xs text-zinc-500">/ 100</span>
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
                <Card class="bg-zinc-900 border-zinc-800">
                    <CardContent class="p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-zinc-800 text-zinc-400 text-sm">
                                        <th class="py-3 px-4 font-semibold">Tên Nhân Viên</th>
                                        <th class="py-3 px-4 font-semibold">Công Việc</th>
                                        <th class="py-3 px-4 font-semibold text-center">Bộ Chỉ Số KPI</th>
                                        <th class="py-3 px-4 font-semibold text-right">Tổng Điểm KPI</th>
                                        <th class="py-3 px-4 font-semibold text-right">Thưởng Thêm</th>
                                        <th class="py-3 px-4 font-semibold text-center">Trạng Thái</th>
                                        <th class="py-3 px-4 font-semibold text-right">Hành Động</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-800 text-sm">
                                    <tr v-for="emp in employees" :key="emp.id" class="hover:bg-zinc-950/40">
                                        <td class="py-4 px-4 font-bold text-white">{{ emp.full_name }}</td>
                                        <td class="py-4 px-4 text-zinc-400">{{ emp.job_title }}</td>
                                        <td class="py-4 px-4 text-center">
                                            <span class="px-2 py-1 text-xs rounded-full border" :class="getRoleBadgeColor(emp.role)">
                                                {{ getRoleText(emp.role) }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-right font-black text-white">
                                            {{ emp.current_kpi ? emp.current_kpi.total_score : '—' }}
                                        </td>
                                        <td class="py-4 px-4 text-right text-emerald-400 font-semibold">
                                            {{ emp.current_kpi ? formatVnd(emp.current_kpi.total_bonus + emp.current_kpi.total_commission) : '—' }}
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <span v-if="emp.current_kpi" class="px-2.5 py-1 text-xs font-semibold rounded-full"
                                                :class="emp.current_kpi.status === 'finalized' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400'"
                                            >
                                                {{ emp.current_kpi.status === 'finalized' ? 'Đã chốt lương' : 'Bản nháp' }}
                                            </span>
                                            <span v-else class="text-zinc-600 text-xs">Chưa tính</span>
                                        </td>
                                        <td class="py-4 px-4 text-right space-x-2">
                                            <!-- Details -->
                                            <Button 
                                                v-if="emp.current_kpi" 
                                                @click="openKpiDetails(emp)"
                                                size="sm"
                                                class="bg-zinc-800 hover:bg-zinc-700 text-white text-xs border border-zinc-700"
                                            >
                                                Xem Chi Tiết
                                            </Button>

                                            <!-- Finalize KPI -->
                                            <Button 
                                                v-if="canManage && emp.current_kpi && emp.current_kpi.status === 'draft'"
                                                @click="finalizeKpi(emp.current_kpi.id)"
                                                size="sm"
                                                class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs"
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
                <Card class="bg-zinc-900 border-zinc-800">
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle class="text-white text-xl">Đánh giá 360° Định Kỳ</CardTitle>
                            <CardDescription class="text-zinc-500">Xem phiếu tự đánh giá, đánh giá từ đồng nghiệp và quản lý.</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="reviews.length === 0" class="flex flex-col items-center justify-center py-12 text-zinc-500 border border-dashed border-zinc-800 rounded-lg">
                            <AlertCircle class="w-12 h-12 text-zinc-600 mb-2" />
                            Chưa có phiếu đánh giá nào được gửi lên tháng này.
                        </div>

                        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <Card v-for="rev in reviews" :key="rev.id" class="bg-zinc-950/60 border-zinc-800">
                                <CardHeader class="pb-2">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <CardTitle class="text-white font-bold text-base">
                                                {{ rev.employee_name }}
                                            </CardTitle>
                                            <CardDescription class="text-zinc-500 text-xs">
                                                Người đánh giá: {{ rev.reviewer_name }} ({{ rev.reviewer_type === 'self' ? 'Tự đánh giá' : rev.reviewer_type === 'manager' ? 'Quản lý' : 'Đồng nghiệp' }})
                                            </CardDescription>
                                        </div>
                                        <div class="flex items-center gap-1 bg-amber-500/10 text-amber-400 px-2.5 py-1 rounded-lg border border-amber-500/20">
                                            <span class="font-extrabold text-sm">{{ rev.average_score }}</span>
                                            <Star class="w-3.5 h-3.5 fill-current" />
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent class="text-sm space-y-3">
                                    <div class="grid grid-cols-2 gap-2 border-t border-b border-zinc-800/80 py-2 text-zinc-400 text-xs">
                                        <div>Giao tiếp: <span class="text-white font-semibold">{{ rev.ratings.communication }}/5</span></div>
                                        <div>Làm việc nhóm: <span class="text-white font-semibold">{{ rev.ratings.teamwork }}/5</span></div>
                                        <div>Độ tin cậy: <span class="text-white font-semibold">{{ rev.ratings.reliability }}/5</span></div>
                                        <div>Kỹ năng chuyên môn: <span class="text-white font-semibold">{{ rev.ratings.skills }}/5</span></div>
                                    </div>
                                    <div v-if="rev.comments" class="text-zinc-300 italic text-xs leading-relaxed bg-zinc-900/60 p-2.5 rounded-lg border border-zinc-800">
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
                <Card class="bg-zinc-900 border-zinc-800">
                    <CardHeader>
                        <CardTitle class="text-white text-xl">Cấu Hình Bộ Luật KPI Chỉ Tiêu Hệ Thống</CardTitle>
                        <CardDescription class="text-zinc-500">Chỉ có Owner và Manager mới có quyền điều chỉnh bộ quy chuẩn, tỷ trọng và thưởng nóng chỉ tiêu này.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-zinc-800 text-zinc-400 text-sm">
                                        <th class="py-3 px-4 font-semibold">Vai Trò</th>
                                        <th class="py-3 px-4 font-semibold">Tên Chỉ Số</th>
                                        <th class="py-3 px-4 font-semibold text-right">Tỷ Trọng Điểm (%)</th>
                                        <th class="py-3 px-4 font-semibold text-right">Chỉ Tiêu Đề Ra</th>
                                        <th class="py-3 px-4 font-semibold text-right">Tiền Thưởng Đạt</th>
                                        <th class="py-3 px-4 font-semibold text-right">Hoa Hồng (%)</th>
                                        <th class="py-3 px-4 font-semibold text-right">Hành Động</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-800 text-sm">
                                    <tr v-for="cfg in kpiConfigs" :key="cfg.id" class="hover:bg-zinc-950/40">
                                        <td class="py-3.5 px-4 font-semibold text-white">
                                            <span class="px-2 py-0.5 rounded text-xs border" :class="getRoleBadgeColor(cfg.role)">
                                                {{ getRoleText(cfg.role) }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-zinc-300 font-medium">{{ cfg.metric_name }}</td>
                                        <td class="py-3.5 px-4 text-right text-white">{{ cfg.weight }}%</td>
                                        <td class="py-3.5 px-4 text-right text-white font-bold">
                                            {{ cfg.target }} {{ cfg.target_type === 'less_is_better' ? '(Tối Đa)' : '(Tối Thiểu)' }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right text-emerald-400">{{ formatVnd(cfg.bonus_amount) }}</td>
                                        <td class="py-3.5 px-4 text-right text-amber-500">{{ cfg.commission_rate }}%</td>
                                        <td class="py-3.5 px-4 text-right">
                                            <Button 
                                                @click="editConfig(cfg)"
                                                size="sm"
                                                class="bg-zinc-800 hover:bg-zinc-700 text-white text-xs border border-zinc-700"
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
        <div v-if="activeEmployeeKpi" class="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50 backdrop-blur-sm">
            <Card class="bg-zinc-900 border-zinc-800 w-full max-w-2xl text-zinc-100 max-h-[90vh] overflow-y-auto">
                <CardHeader class="flex flex-row items-center justify-between border-b border-zinc-800 pb-4">
                    <div>
                        <CardTitle class="text-white text-xl">Bảng KPI Chi Tiết - {{ activeEmployeeKpi.full_name }}</CardTitle>
                        <CardDescription class="text-zinc-500">{{ activeEmployeeKpi.job_title }} | Kỳ: {{ period }}</CardDescription>
                    </div>
                    <Button @click="activeEmployeeKpi = null" variant="ghost" class="p-2 text-zinc-400 hover:text-white">
                        <X class="w-6 h-6" />
                    </Button>
                </CardHeader>
                <CardContent class="p-6 space-y-6">
                    <!-- Overall KPI Summary -->
                    <div class="bg-zinc-950 p-4 rounded-xl border border-zinc-800 grid grid-cols-3 text-center">
                        <div class="border-r border-zinc-800">
                            <p class="text-xs text-zinc-500">Tổng điểm KPI</p>
                            <p class="text-2xl font-black text-white">{{ activeEmployeeKpi.current_kpi?.total_score }}</p>
                        </div>
                        <div class="border-r border-zinc-800">
                            <p class="text-xs text-zinc-500">Tiền thưởng đạt</p>
                            <p class="text-lg font-bold text-emerald-400">
                                {{ formatVnd(activeEmployeeKpi.current_kpi?.total_bonus ?? 0) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-zinc-500">Hoa hồng doanh số</p>
                            <p class="text-lg font-bold text-amber-500">
                                {{ formatVnd(activeEmployeeKpi.current_kpi?.total_commission ?? 0) }}
                            </p>
                        </div>
                    </div>

                    <!-- Metrics Details List -->
                    <div class="space-y-4">
                        <h4 class="font-bold text-zinc-400 text-sm">CHI TIẾT CHỈ TIÊU KPI THỰC TẾ:</h4>
                        
                        <div v-for="metric in activeEmployeeKpi.current_kpi?.metrics" :key="metric.id" class="p-4 bg-zinc-950/40 border border-zinc-800 rounded-xl space-y-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h5 class="text-sm font-bold text-white">{{ metric.metric_name }}</h5>
                                    <p class="text-xs text-zinc-500">Code: {{ metric.metric_code }}</p>
                                </div>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded"
                                    :class="metric.is_achieved ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'"
                                >
                                    {{ metric.is_achieved ? 'Đạt chỉ tiêu' : 'Chưa đạt' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-2 text-xs border-t border-b border-zinc-800 py-2 text-zinc-400">
                                <div>Mục tiêu: <span class="text-white font-semibold">{{ metric.target_value }}</span></div>
                                <div>Thực tế: <span class="text-white font-semibold">{{ metric.actual_value }}</span></div>
                                <div>Chấm điểm: <span class="text-white font-black text-sm">{{ metric.score }} / 100</span></div>
                            </div>

                            <div class="flex justify-between text-xs text-zinc-400">
                                <span>Thưởng nóng đạt chỉ tiêu: <strong class="text-emerald-400">{{ formatVnd(metric.bonus_earned) }}</strong></span>
                                <span>Hoa hồng nhận: <strong class="text-amber-500">{{ formatVnd(metric.commission_earned) }}</strong></span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- MODAL: Edit Metric Config Setup -->
        <div v-if="activeConfigEdit" class="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50 backdrop-blur-sm">
            <Card class="bg-zinc-900 border-zinc-800 w-full max-w-md text-zinc-100">
                <CardHeader class="flex flex-row items-center justify-between border-b border-zinc-800 pb-4">
                    <div>
                        <CardTitle class="text-white text-xl">Sửa Chỉ Tiêu KPI</CardTitle>
                        <CardDescription class="text-zinc-500">{{ activeConfigEdit.metric_name }}</CardDescription>
                    </div>
                    <Button @click="activeConfigEdit = null" variant="ghost" class="p-2 text-zinc-400 hover:text-white">
                        <X class="w-6 h-6" />
                    </Button>
                </CardHeader>
                <form @submit.prevent="updateConfig">
                    <CardContent class="p-6 space-y-4">
                        <div class="space-y-2">
                            <Label for="weight" class="text-zinc-400">Tỷ trọng điểm (%) trong tổng điểm KPI</Label>
                            <Input 
                                id="weight" 
                                type="number" 
                                v-model.number="configForm.weight" 
                                min="0" max="100" 
                                class="bg-zinc-950 border-zinc-800 text-white" 
                                required
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="target" class="text-zinc-400">Giá trị chỉ tiêu mục tiêu đề ra</Label>
                            <Input 
                                id="target" 
                                type="number" 
                                step="any"
                                v-model.number="configForm.target" 
                                min="0" 
                                class="bg-zinc-950 border-zinc-800 text-white" 
                                required
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="bonus" class="text-zinc-400">Tiền thưởng nóng đạt chỉ tiêu (VND)</Label>
                            <Input 
                                id="bonus" 
                                type="number" 
                                v-model.number="configForm.bonus_amount" 
                                min="0" 
                                class="bg-zinc-950 border-zinc-800 text-white" 
                                required
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="commission" class="text-zinc-400">Phần trăm hoa hồng doanh thu xử lý (%) (nếu có)</Label>
                            <Input 
                                id="commission" 
                                type="number" 
                                step="0.01"
                                v-model.number="configForm.commission_rate" 
                                min="0" max="100" 
                                class="bg-zinc-950 border-zinc-800 text-white" 
                                required
                            />
                        </div>
                    </CardContent>
                    <div class="flex justify-end gap-3 p-6 border-t border-zinc-800 bg-zinc-950/20">
                        <Button type="button" @click="activeConfigEdit = null" variant="ghost" class="text-zinc-400 hover:text-white">
                            Hủy
                        </Button>
                        <Button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white">
                            Cập Nhật
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- MODAL: Submit 360-Degree Review Form -->
        <div v-if="isReviewModalOpen" class="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50 backdrop-blur-sm">
            <Card class="bg-zinc-900 border-zinc-800 w-full max-w-lg text-zinc-100 max-h-[90vh] overflow-y-auto">
                <CardHeader class="flex flex-row items-center justify-between border-b border-zinc-800 pb-4">
                    <div>
                        <CardTitle class="text-white text-xl">Phiếu Đánh Giá Hiệu Suất 360°</CardTitle>
                        <CardDescription class="text-zinc-500">Gửi phiếu tự đánh giá, đánh giá quản lý hoặc đồng nghiệp.</CardDescription>
                    </div>
                    <Button @click="isReviewModalOpen = false" variant="ghost" class="p-2 text-zinc-400 hover:text-white">
                        <X class="w-6 h-6" />
                    </Button>
                </CardHeader>
                <form @submit.prevent="submitReview">
                    <CardContent class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="review_employee" class="text-zinc-400">Chọn Nhân Sự Chấm Điểm</Label>
                                <select 
                                    id="review_employee" 
                                    v-model="reviewForm.employee_id"
                                    class="w-full bg-zinc-950 border border-zinc-800 rounded-lg text-white p-2.5 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm"
                                    required
                                >
                                    <option value="" disabled>-- Chọn nhân viên --</option>
                                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.full_name }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label for="reviewer_type" class="text-zinc-400">Mối Quan Hệ Đánh Giá</Label>
                                <select 
                                    id="reviewer_type" 
                                    v-model="reviewForm.reviewer_type"
                                    class="w-full bg-zinc-950 border border-zinc-800 rounded-lg text-white p-2.5 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm"
                                    required
                                >
                                    <option value="self">Tự đánh giá bản thân</option>
                                    <option value="manager">Quản lý đánh giá</option>
                                    <option value="peer">Đồng nghiệp đánh giá chéo</option>
                                </select>
                            </div>
                        </div>

                        <!-- Ratings section -->
                        <div class="space-y-4 border-t border-zinc-800 pt-4">
                            <h4 class="font-bold text-amber-500 text-sm tracking-wide">CHẤM ĐIỂM TIÊU CHÍ (Từ 1 đến 5 sao):</h4>

                            <div class="space-y-3">
                                <!-- Communication -->
                                <div class="flex justify-between items-center bg-zinc-950/40 p-3 rounded-lg border border-zinc-800">
                                    <div>
                                        <Label class="text-white font-bold">Kỹ năng giao tiếp</Label>
                                        <p class="text-xs text-zinc-500">Khả năng truyền đạt, trao đổi thông tin với khách hàng & đồng đội.</p>
                                    </div>
                                    <input 
                                        type="number" 
                                        v-model.number="reviewForm.ratings.communication" 
                                        min="1" max="5" 
                                        class="bg-zinc-950 border border-zinc-800 rounded px-2 py-1 text-white font-bold w-14 text-center text-sm"
                                        required
                                    />
                                </div>

                                <!-- Teamwork -->
                                <div class="flex justify-between items-center bg-zinc-950/40 p-3 rounded-lg border border-zinc-800">
                                    <div>
                                        <Label class="text-white font-bold">Làm việc nhóm</Label>
                                        <p class="text-xs text-zinc-500">Khả năng phối hợp đồng đội, hỗ trợ chạy việc trong ca.</p>
                                    </div>
                                    <input 
                                        type="number" 
                                        v-model.number="reviewForm.ratings.teamwork" 
                                        min="1" max="5" 
                                        class="bg-zinc-950 border border-zinc-800 rounded px-2 py-1 text-white font-bold w-14 text-center text-sm"
                                        required
                                    />
                                </div>

                                <!-- Reliability -->
                                <div class="flex justify-between items-center bg-zinc-950/40 p-3 rounded-lg border border-zinc-800">
                                    <div>
                                        <Label class="text-white font-bold">Độ tin cậy & Đúng giờ</Label>
                                        <p class="text-xs text-zinc-500">Ý thức giờ giấc đi làm, chấp hành kỷ luật.</p>
                                    </div>
                                    <input 
                                        type="number" 
                                        v-model.number="reviewForm.ratings.reliability" 
                                        min="1" max="5" 
                                        class="bg-zinc-950 border border-zinc-800 rounded px-2 py-1 text-white font-bold w-14 text-center text-sm"
                                        required
                                    />
                                </div>

                                <!-- Skills -->
                                <div class="flex justify-between items-center bg-zinc-950/40 p-3 rounded-lg border border-zinc-800">
                                    <div>
                                        <Label class="text-white font-bold">Kỹ năng nghiệp vụ chuyên môn</Label>
                                        <p class="text-xs text-zinc-500">Trình độ làm bếp, phục vụ hoặc thu ngân chuẩn quy trình.</p>
                                    </div>
                                    <input 
                                        type="number" 
                                        v-model.number="reviewForm.ratings.skills" 
                                        min="1" max="5" 
                                        class="bg-zinc-950 border border-zinc-800 rounded px-2 py-1 text-white font-bold w-14 text-center text-sm"
                                        required
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Comments -->
                        <div class="space-y-2">
                            <Label for="comments" class="text-zinc-400">Ý kiến đánh giá khác / Nhận xét tổng quan</Label>
                            <textarea 
                                id="comments" 
                                v-model="reviewForm.comments" 
                                rows="3"
                                class="w-full bg-zinc-950 border border-zinc-800 rounded-lg text-white p-2.5 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm"
                                placeholder="Nhập ý kiến đóng góp ý kiến xây dựng cho nhân sự tại đây..."
                            ></textarea>
                        </div>
                    </CardContent>
                    <div class="flex justify-end gap-3 p-6 border-t border-zinc-800 bg-zinc-950/20">
                        <Button type="button" @click="isReviewModalOpen = false" variant="ghost" class="text-zinc-400 hover:text-white">
                            Hủy
                        </Button>
                        <Button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white">
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
