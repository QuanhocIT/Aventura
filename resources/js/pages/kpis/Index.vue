<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    BarChart3,
    Trophy,
    UserCheck,
    MessageSquare,
    Settings,
    RefreshCw,
    Calendar,
    Users,
    AlertCircle,
    Star,
    Plus,
    X,
    DollarSign,
    ChefHat,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirmDialog } from '@/composables/useConfirm';
import { index, recalculate, finalize } from '@/routes/kpis';
import { update as updateMetric } from '@/routes/kpis/metrics';
import { store as storeReview } from '@/routes/kpis/reviews';
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
    branchContext?: { scope: string; active_branch_id: number | null };
}>();

// --- Active Tab ---
const activeTab = ref<'leaderboard' | 'performance' | 'reviews' | 'settings'>(
    'leaderboard',
);

// --- Period Filter ---
const selectedPeriod = ref(props.period);

const changePeriod = () => {
    router.visit(index.url(), {
        data: { period: selectedPeriod.value },
        preserveState: true,
        preserveScroll: true,
    });
};

// --- Recalculate KPIs ---
const isRecalculating = ref(false);
const triggerRecalculate = () => {
    isRecalculating.value = true;
    router.post(
        recalculate.url(),
        { period: selectedPeriod.value },
        {
            onSuccess: () => {
                toast.success(
                    'Đã tính toán lại toàn bộ chỉ số KPI trong tháng!',
                );
                isRecalculating.value = false;
            },
            onError: () => {
                toast.error('Có lỗi xảy ra trong quá trình tính toán.');
                isRecalculating.value = false;
            },
        },
    );
};

// --- Recalculating and Finalizing individual KPI ---
const isFinalizing = ref<number | null>(null);
const finalizeKpi = async (kpiId: number) => {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description:
                'Bạn có chắc chắn muốn chốt duyệt bảng KPI này? Số tiền thưởng sẽ được chuyển thẳng vào bảng lương nháp kỳ này.',
            variant: 'default',
        }))
    ) {
        return;
    }

    isFinalizing.value = kpiId;
    router.post(
        finalize.url(kpiId),
        {},
        {
            onSuccess: () => {
                toast.success('Đã chốt duyệt bảng KPI của nhân viên!');
                isFinalizing.value = null;
            },
            onError: () => {
                toast.error('Có lỗi xảy ra khi duyệt bảng KPI.');
                isFinalizing.value = null;
            },
        },
    );
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
        skills: 5,
    },
    comments: '',
});

const submitReview = () => {
    reviewForm.post(storeReview.url(), {
        onSuccess: () => {
            toast.success('Đã gửi phiếu đánh giá 360° thành công!');
            isReviewModalOpen.value = false;
            reviewForm.reset();
        },
        onError: (errs: any) => {
            Object.values(errs).forEach((e) => toast.error(String(e)));
        },
    });
};

// --- Modal: KPI Setup Configuration ---
const activeConfigEdit = ref<KpiMetricConfig | null>(null);
const configForm = useForm({
    weight: 0,
    target: 0,
    bonus_amount: 0,
    commission_rate: 0,
});

const editConfig = (config: KpiMetricConfig) => {
    activeConfigEdit.value = config;
    configForm.weight = Number(config.weight);
    configForm.target = Number(config.target);
    configForm.bonus_amount = Number(config.bonus_amount);
    configForm.commission_rate = Number(config.commission_rate);
};

const updateConfig = () => {
    if (!activeConfigEdit.value) {
        return;
    }

    configForm.post(updateMetric.url(activeConfigEdit.value.id), {
        onSuccess: () => {
            toast.success('Đã cập nhật cấu hình chỉ tiêu KPI thành công!');
            activeConfigEdit.value = null;
        },
        onError: (errs: any) => {
            Object.values(errs).forEach((e) => toast.error(String(e)));
        },
    });
};

// Format currency
const formatVnd = (num: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(num);
};

const getRoleBadgeColor = (role: string | null) => {
    if (role === 'waiter') {
        return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
    }

    if (role === 'kitchen') {
        return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
    }

    if (role === 'cashier') {
        return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
    }

    return 'bg-zinc-500/10 text-zinc-400 border-zinc-500/20';
};

const getRoleText = (role: string | null) => {
    if (role === 'waiter') {
        return 'Phục vụ';
    }

    if (role === 'kitchen') {
        return 'Bếp / Pha chế';
    }

    if (role === 'cashier') {
        return 'Thu ngân';
    }

    return 'Khác';
};
</script>

<template>
    <Head title="Đánh Giá Hiệu Suất & KPI" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6 text-slate-800 dark:text-slate-100"
    >
        <div
            v-if="props.branchContext?.scope === 'all'"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
        >
            Đang xem <strong>Toàn chuỗi</strong>. KPI được tổng hợp từ nhân sự
            của tất cả chi nhánh.
        </div>
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400"
                >
                    <BarChart3 class="size-6" />
                </div>
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        Đánh Giá Hiệu Suất & KPI
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Hệ thống tính điểm KPI tự động cho Phục vụ, Bếp, Thu
                        ngân và Phiếu đánh giá 360° định kỳ.
                    </p>
                </div>
            </div>

            <!-- Header Controls -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Period Input -->
                <div
                    class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <Calendar class="h-4 w-4 text-amber-500" />
                    <input
                        type="month"
                        v-model="selectedPeriod"
                        @change="changePeriod"
                        class="cursor-pointer border-none bg-transparent text-sm text-slate-800 focus:ring-0 focus:outline-none dark:text-slate-100"
                    />
                </div>

                <!-- Recalculate KPI -->
                <Button
                    v-if="canManage"
                    @click="triggerRecalculate"
                    :disabled="isRecalculating"
                    class="h-10 rounded-xl bg-amber-600 text-xs font-semibold text-white hover:bg-amber-700"
                >
                    <RefreshCw
                        class="mr-1.5 h-4 w-4"
                        :class="{ 'animate-spin': isRecalculating }"
                    />
                    {{
                        isRecalculating
                            ? 'Đang tính toán...'
                            : 'Tính KPI Tự Động'
                    }}
                </Button>

                <!-- Create 360 Review -->
                <Button
                    @click="isReviewModalOpen = true"
                    class="dark:text-slate-250 h-10 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800"
                >
                    <Plus class="mr-1.5 h-4 w-4 text-amber-500" />
                    Đánh Giá 360°
                </Button>
            </div>
        </div>

        <!-- Metric Config Summary Cards -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <Card
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
            >
                <CardHeader
                    class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                >
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-bold text-blue-600 dark:text-blue-400"
                    >
                        <Users class="h-4 w-4" /> Phục vụ (Waiter)
                    </CardTitle>
                    <CardDescription
                        class="text-xs text-slate-500 dark:text-slate-400"
                        >Mục tiêu quy định</CardDescription
                    >
                </CardHeader>
                <CardContent
                    class="text-slate-650 dark:text-slate-350 space-y-2 p-4 text-xs"
                >
                    <div
                        class="flex justify-between border-b border-slate-100 pb-1.5 dark:border-slate-800"
                    >
                        <span>Đơn phục vụ/ca:</span>
                        <span class="font-bold text-slate-900 dark:text-white"
                            >>=
                            {{
                                props.kpiConfigs.find(
                                    (c) =>
                                        c.metric_code ===
                                        'waiter_orders_served',
                                )?.target ?? '15'
                            }}
                            đơn</span
                        >
                    </div>
                    <div
                        class="flex justify-between border-b border-slate-100 pb-1.5 dark:border-slate-800"
                    >
                        <span>Điểm đánh giá KH:</span>
                        <span class="font-bold text-slate-900 dark:text-white"
                            >>=
                            {{
                                props.kpiConfigs.find(
                                    (c) =>
                                        c.metric_code ===
                                        'waiter_customer_rating',
                                )?.target ?? '4.5'
                            }}
                            ★</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span>Tốc độ phục vụ:</span>
                        <span class="font-bold text-slate-900 dark:text-white"
                            >&lt;=
                            {{
                                props.kpiConfigs.find(
                                    (c) =>
                                        c.metric_code ===
                                        'waiter_service_speed',
                                )?.target ?? '5'
                            }}
                            phút</span
                        >
                    </div>
                </CardContent>
            </Card>

            <Card
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
            >
                <CardHeader
                    class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                >
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-bold text-amber-600 dark:text-amber-500"
                    >
                        <ChefHat class="h-4 w-4" /> Bếp (Kitchen)
                    </CardTitle>
                    <CardDescription
                        class="text-xs text-slate-500 dark:text-slate-400"
                        >Mục tiêu quy định</CardDescription
                    >
                </CardHeader>
                <CardContent
                    class="text-slate-650 dark:text-slate-350 space-y-2 p-4 text-xs"
                >
                    <div
                        class="flex justify-between border-b border-slate-100 pb-1.5 dark:border-slate-800"
                    >
                        <span>Thời gian nấu TB:</span>
                        <span class="font-bold text-slate-900 dark:text-white"
                            >&lt;=
                            {{
                                props.kpiConfigs.find(
                                    (c) => c.metric_code === 'chef_prep_time',
                                )?.target ?? '15'
                            }}
                            phút</span
                        >
                    </div>
                    <div
                        class="flex justify-between border-b border-slate-100 pb-1.5 dark:border-slate-800"
                    >
                        <span>Tỷ lệ món trả lại:</span>
                        <span class="font-bold text-slate-900 dark:text-white"
                            >&lt;=
                            {{
                                props.kpiConfigs.find(
                                    (c) =>
                                        c.metric_code === 'chef_rejection_rate',
                                )?.target ?? '2%'
                            }}%</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span>Chất lượng món ăn:</span>
                        <span class="font-bold text-slate-900 dark:text-white"
                            >>=
                            {{
                                props.kpiConfigs.find(
                                    (c) => c.metric_code === 'chef_food_rating',
                                )?.target ?? '4.5'
                            }}
                            ★</span
                        >
                    </div>
                </CardContent>
            </Card>

            <Card
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
            >
                <CardHeader
                    class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                >
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-bold text-emerald-600 dark:text-emerald-400"
                    >
                        <DollarSign class="h-4 w-4" /> Thu ngân (Cashier)
                    </CardTitle>
                    <CardDescription
                        class="text-xs text-slate-500 dark:text-slate-400"
                        >Mục tiêu quy định</CardDescription
                    >
                </CardHeader>
                <CardContent
                    class="text-slate-650 dark:text-slate-350 space-y-2 p-4 text-xs"
                >
                    <div
                        class="flex justify-between border-b border-slate-100 pb-1.5 dark:border-slate-800"
                    >
                        <span>Doanh số xử lý:</span>
                        <span class="font-bold text-slate-900 dark:text-white"
                            >>=
                            {{
                                formatVnd(
                                    props.kpiConfigs.find(
                                        (c) =>
                                            c.metric_code ===
                                            'cashier_processed_revenue',
                                    )?.target ?? 50000000,
                                )
                            }}</span
                        >
                    </div>
                    <div
                        class="flex justify-between border-b border-slate-100 pb-1.5 dark:border-slate-800"
                    >
                        <span>Tỷ lệ sai két:</span>
                        <span class="font-bold text-slate-900 dark:text-white"
                            >0%</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span>Tốc độ thanh toán:</span>
                        <span class="font-bold text-slate-900 dark:text-white"
                            >&lt;=
                            {{
                                props.kpiConfigs.find(
                                    (c) =>
                                        c.metric_code ===
                                        'cashier_checkout_speed',
                                )?.target ?? '2'
                            }}
                            phút</span
                        >
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex border-b border-slate-200 dark:border-slate-800">
            <button
                @click="activeTab = 'leaderboard'"
                class="flex items-center gap-2 border-b-2 px-5 py-3 text-sm font-semibold transition"
                :class="
                    activeTab === 'leaderboard'
                        ? 'text-amber-605 border-amber-600 dark:border-amber-500 dark:text-amber-500'
                        : 'dark:hover:text-slate-250 border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400'
                "
            >
                <Trophy class="h-4 w-4" /> Bảng Xếp Hạng
            </button>
            <button
                @click="activeTab = 'performance'"
                class="flex items-center gap-2 border-b-2 px-5 py-3 text-sm font-semibold transition"
                :class="
                    activeTab === 'performance'
                        ? 'text-amber-605 border-amber-600 dark:border-amber-500 dark:text-amber-500'
                        : 'dark:hover:text-slate-250 border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400'
                "
            >
                <UserCheck class="h-4 w-4" /> Chỉ Số KPI Nhân Sự
            </button>
            <button
                @click="activeTab = 'reviews'"
                class="flex items-center gap-2 border-b-2 px-5 py-3 text-sm font-semibold transition"
                :class="
                    activeTab === 'reviews'
                        ? 'text-amber-605 border-amber-600 dark:border-amber-500 dark:text-amber-500'
                        : 'dark:hover:text-slate-250 border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400'
                "
            >
                <MessageSquare class="h-4 w-4" /> Đánh Giá 360° ({{
                    reviews.length
                }})
            </button>
            <button
                v-if="canManage"
                @click="activeTab = 'settings'"
                class="flex items-center gap-2 border-b-2 px-5 py-3 text-sm font-semibold transition"
                :class="
                    activeTab === 'settings'
                        ? 'text-amber-605 border-amber-600 dark:border-amber-500 dark:text-amber-500'
                        : 'dark:hover:text-slate-250 border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400'
                "
            >
                <Settings class="h-4 w-4" /> Cấu Hình Chỉ Tiêu
            </button>
        </div>

        <!-- Tab contents -->
        <div class="mt-6">
            <!-- 1. Leaderboard Tab -->
            <div
                v-if="activeTab === 'leaderboard'"
                class="animate-fade-in space-y-6"
            >
                <Card
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardHeader
                        class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                    >
                        <CardTitle
                            class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                        >
                            <Trophy class="h-5 w-5 text-amber-500" /> Bảng Vinh
                            Danh Nhân Viên Xuất Sắc Kỳ {{ period }}
                        </CardTitle>
                        <CardDescription
                            class="text-xs text-slate-500 dark:text-slate-400"
                            >Xếp hạng dựa trên điểm số KPI thực tế tháng
                            này.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="p-6">
                        <div
                            v-if="leaderboard.length === 0"
                            class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/20 py-12 text-slate-500 dark:border-slate-800 dark:bg-slate-950/20 dark:text-slate-400"
                        >
                            <AlertCircle
                                class="text-slate-350 mb-2 h-12 w-12 dark:text-slate-600"
                            />
                            Chưa có dữ liệu xếp hạng tháng này. Hãy bấm "Tính
                            KPI Tự Động" để tạo điểm.
                        </div>

                        <div v-else class="space-y-4">
                            <div
                                v-for="(item, index) in leaderboard"
                                :key="item.id"
                                class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/40 p-4 transition hover:border-slate-200 dark:border-slate-800/80 dark:bg-slate-950/40 dark:hover:border-slate-700/80"
                            >
                                <div class="flex items-center gap-4">
                                    <!-- Rank Number -->
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-full border text-lg font-black"
                                        :class="
                                            index === 0
                                                ? 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                : index === 1
                                                  ? 'bg-slate-350/15 border-slate-350/20 text-slate-600 dark:text-slate-300'
                                                  : index === 2
                                                    ? 'border-amber-700/20 bg-amber-700/10 text-amber-800 dark:text-amber-500'
                                                    : 'border-slate-200 bg-slate-100 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400'
                                        "
                                    >
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <h3
                                            class="text-base font-bold text-slate-900 dark:text-white"
                                        >
                                            {{ item.employee_name }}
                                        </h3>
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{ item.job_title }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-6">
                                    <!-- Money earned -->
                                    <div class="text-right">
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            Thưởng & Hoa hồng
                                        </p>
                                        <p
                                            class="text-sm font-bold text-emerald-600 dark:text-emerald-400"
                                        >
                                            +{{
                                                formatVnd(
                                                    item.total_bonus +
                                                        item.total_commission,
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <!-- Score Display -->
                                    <div class="text-right">
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            Điểm KPI
                                        </p>
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-base font-black text-slate-900 dark:text-white"
                                                >{{ item.total_score }}</span
                                            >
                                            <span
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                                >/ 100</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- 2. Performance Tracker Tab -->
            <div
                v-if="activeTab === 'performance'"
                class="animate-fade-in space-y-6"
            >
                <Card
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table
                                class="w-full border-collapse text-left text-xs"
                            >
                                <thead>
                                    <tr
                                        class="border-b border-slate-100 bg-slate-50/50 font-bold text-slate-500 dark:border-slate-800 dark:bg-slate-900/10 dark:text-slate-400"
                                    >
                                        <th class="px-6 py-4">Tên Nhân Viên</th>
                                        <th class="px-4 py-4">Công Việc</th>
                                        <th class="px-4 py-4 text-center">
                                            Bộ Chỉ Số KPI
                                        </th>
                                        <th class="px-4 py-4 text-right">
                                            Tổng Điểm KPI
                                        </th>
                                        <th class="px-4 py-4 text-right">
                                            Thưởng Thêm
                                        </th>
                                        <th class="px-4 py-4 text-center">
                                            Trạng Thế
                                        </th>
                                        <th class="px-6 py-4 text-right">
                                            Hành Động
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-slate-800"
                                >
                                    <tr
                                        v-for="emp in employees"
                                        :key="emp.id"
                                        class="dark:text-slate-350 text-slate-700 hover:bg-slate-50/30 dark:hover:bg-slate-950/30"
                                    >
                                        <td
                                            class="px-6 py-4 font-bold text-slate-900 dark:text-white"
                                        >
                                            {{ emp.full_name }}
                                        </td>
                                        <td
                                            class="px-4 py-4 text-slate-500 dark:text-slate-400"
                                        >
                                            {{ emp.job_title }}
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <span
                                                class="rounded-full border px-2 py-0.5 text-[10px] font-bold"
                                                :class="
                                                    getRoleBadgeColor(emp.role)
                                                "
                                            >
                                                {{ getRoleText(emp.role) }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-4 py-4 text-right font-black text-slate-900 dark:text-white"
                                        >
                                            {{
                                                emp.current_kpi
                                                    ? emp.current_kpi
                                                          .total_score
                                                    : '—'
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-4 text-right font-bold text-emerald-600 dark:text-emerald-400"
                                        >
                                            {{
                                                emp.current_kpi
                                                    ? formatVnd(
                                                          emp.current_kpi
                                                              .total_bonus +
                                                              emp.current_kpi
                                                                  .total_commission,
                                                      )
                                                    : '—'
                                            }}
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <span
                                                v-if="emp.current_kpi"
                                                class="rounded-full border px-2 py-0.5 text-[10px] font-bold"
                                                :class="
                                                    emp.current_kpi.status ===
                                                    'finalized'
                                                        ? 'border-emerald-200/50 bg-emerald-50 text-emerald-700 dark:border-emerald-900/30 dark:bg-emerald-950/30 dark:text-emerald-400'
                                                        : 'border-amber-200/50 bg-amber-50 text-amber-700 dark:border-amber-900/30 dark:bg-amber-950/30 dark:text-amber-400'
                                                "
                                            >
                                                {{
                                                    emp.current_kpi.status ===
                                                    'finalized'
                                                        ? 'Đã chốt lương'
                                                        : 'Bản nháp'
                                                }}
                                            </span>
                                            <span
                                                v-else
                                                class="text-xs text-slate-400 dark:text-slate-600"
                                                >Chưa tính</span
                                            >
                                        </td>
                                        <td
                                            class="space-x-2 px-6 py-4 text-right"
                                        >
                                            <!-- Details -->
                                            <Button
                                                v-if="emp.current_kpi"
                                                @click="openKpiDetails(emp)"
                                                size="sm"
                                                class="dark:text-slate-250 h-8 rounded-xl border border-slate-200 bg-white text-[11px] font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800"
                                            >
                                                Xem Chi Tiết
                                            </Button>

                                            <!-- Finalize KPI -->
                                            <Button
                                                v-if="
                                                    canManage &&
                                                    emp.current_kpi &&
                                                    emp.current_kpi.status ===
                                                        'draft'
                                                "
                                                @click="
                                                    finalizeKpi(
                                                        emp.current_kpi.id,
                                                    )
                                                "
                                                size="sm"
                                                class="h-8 rounded-xl bg-emerald-600 text-[11px] font-semibold text-white hover:bg-emerald-700"
                                                :disabled="
                                                    isFinalizing ===
                                                    emp.current_kpi.id
                                                "
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
            <div
                v-if="activeTab === 'reviews'"
                class="animate-fade-in space-y-6"
            >
                <Card
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                    >
                        <div>
                            <CardTitle
                                class="text-base font-bold text-slate-900 dark:text-white"
                                >Đánh giá 360° Định Kỳ</CardTitle
                            >
                            <CardDescription
                                class="text-xs text-slate-500 dark:text-slate-400"
                                >Xem phiếu tự đánh giá, đánh giá từ đồng nghiệp
                                và quản lý.</CardDescription
                            >
                        </div>
                    </CardHeader>
                    <CardContent class="p-6">
                        <div
                            v-if="reviews.length === 0"
                            class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/20 py-12 text-slate-500 dark:border-slate-800 dark:bg-slate-950/20 dark:text-slate-400"
                        >
                            <AlertCircle
                                class="text-slate-350 mb-2 h-12 w-12 dark:text-slate-600"
                            />
                            Chưa có phiếu đánh giá nào được gửi lên tháng này.
                        </div>

                        <div
                            v-else
                            class="grid grid-cols-1 gap-6 md:grid-cols-2"
                        >
                            <Card
                                v-for="rev in reviews"
                                :key="rev.id"
                                class="overflow-hidden rounded-xl border border-slate-100 bg-slate-50/30 shadow-xs dark:border-slate-800/80 dark:bg-slate-950/40"
                            >
                                <CardHeader class="pb-2">
                                    <div
                                        class="flex items-start justify-between"
                                    >
                                        <div>
                                            <CardTitle
                                                class="text-sm font-bold text-slate-900 dark:text-white"
                                            >
                                                {{ rev.employee_name }}
                                            </CardTitle>
                                            <CardDescription
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                Người đánh giá:
                                                {{ rev.reviewer_name }} ({{
                                                    rev.reviewer_type === 'self'
                                                        ? 'Tự đánh giá'
                                                        : rev.reviewer_type ===
                                                            'manager'
                                                          ? 'Quản lý'
                                                          : 'Đồng nghiệp'
                                                }})
                                            </CardDescription>
                                        </div>
                                        <div
                                            class="flex items-center gap-1 rounded-lg border border-amber-200/50 bg-amber-50 px-2 py-0.5 text-amber-600 dark:border-amber-900/30 dark:bg-amber-950/30 dark:text-amber-400"
                                        >
                                            <span
                                                class="text-xs font-extrabold"
                                                >{{ rev.average_score }}</span
                                            >
                                            <Star
                                                class="h-3 w-3 fill-current"
                                            />
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent class="space-y-3 p-4 pt-2 text-xs">
                                    <div
                                        class="grid grid-cols-2 gap-2 border-t border-b border-slate-100 py-2 text-slate-500 dark:border-slate-800/80 dark:text-slate-400"
                                    >
                                        <div>
                                            Giao tiếp:
                                            <span
                                                class="font-bold text-slate-900 dark:text-white"
                                                >{{
                                                    rev.ratings.communication
                                                }}/5</span
                                            >
                                        </div>
                                        <div>
                                            Làm việc nhóm:
                                            <span
                                                class="font-bold text-slate-900 dark:text-white"
                                                >{{
                                                    rev.ratings.teamwork
                                                }}/5</span
                                            >
                                        </div>
                                        <div>
                                            Độ tin cậy:
                                            <span
                                                class="font-bold text-slate-900 dark:text-white"
                                                >{{
                                                    rev.ratings.reliability
                                                }}/5</span
                                            >
                                        </div>
                                        <div>
                                            Kỹ năng chuyên môn:
                                            <span
                                                class="font-bold text-slate-900 dark:text-white"
                                                >{{
                                                    rev.ratings.skills
                                                }}/5</span
                                            >
                                        </div>
                                    </div>
                                    <div
                                        v-if="rev.comments"
                                        class="text-slate-650 dark:text-slate-350 border-slate-150 rounded-lg border bg-slate-100/50 p-2.5 text-[11px] leading-relaxed italic dark:border-zinc-800/50 dark:bg-zinc-900/30"
                                    >
                                        "{{ rev.comments }}"
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- 4. KPI Setup Tab -->
            <div
                v-if="activeTab === 'settings'"
                class="animate-fade-in space-y-6"
            >
                <Card
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardHeader
                        class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                    >
                        <CardTitle
                            class="text-base font-bold text-slate-900 dark:text-white"
                            >Cấu Hình Bộ Luật KPI Chỉ Tiêu Hệ Thống</CardTitle
                        >
                        <CardDescription
                            class="text-xs text-slate-500 dark:text-slate-400"
                            >Chỉ có Owner và Manager mới có quyền điều chỉnh bộ
                            quy chuẩn, tỷ trọng và thưởng nóng chỉ tiêu
                            này.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table
                                class="w-full border-collapse text-left text-xs"
                            >
                                <thead>
                                    <tr
                                        class="border-b border-slate-100 bg-slate-50/50 font-bold text-slate-500 dark:border-slate-800 dark:bg-slate-900/10 dark:text-slate-400"
                                    >
                                        <th class="px-6 py-4">Vai Trò</th>
                                        <th class="px-4 py-4">Tên Chỉ Số</th>
                                        <th class="px-4 py-4 text-right">
                                            Tỷ Trọng Điểm (%)
                                        </th>
                                        <th class="px-4 py-4 text-right">
                                            Chỉ Tiêu Đề Ra
                                        </th>
                                        <th class="px-4 py-4 text-right">
                                            Tiền Thưởng Đạt
                                        </th>
                                        <th class="px-4 py-4 text-right">
                                            Hoa Hồng (%)
                                        </th>
                                        <th class="px-6 py-4 text-right">
                                            Hành Động
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-slate-800"
                                >
                                    <tr
                                        v-for="cfg in kpiConfigs"
                                        :key="cfg.id"
                                        class="dark:text-slate-350 text-slate-700 hover:bg-slate-50/30 dark:hover:bg-slate-950/30"
                                    >
                                        <td class="px-6 py-3.5 font-semibold">
                                            <span
                                                class="rounded border px-2 py-0.5 text-[10px] font-bold"
                                                :class="
                                                    getRoleBadgeColor(cfg.role)
                                                "
                                            >
                                                {{ getRoleText(cfg.role) }}
                                            </span>
                                        </td>
                                        <td
                                            class="dark:text-slate-305 px-4 py-3.5 font-medium text-slate-700"
                                        >
                                            {{ cfg.metric_name }}
                                        </td>
                                        <td
                                            class="px-4 py-3.5 text-right font-bold text-slate-900 dark:text-white"
                                        >
                                            {{ cfg.weight }}%
                                        </td>
                                        <td
                                            class="px-4 py-3.5 text-right font-bold text-slate-900 dark:text-white"
                                        >
                                            {{ cfg.target }}
                                            {{
                                                cfg.target_type ===
                                                'less_is_better'
                                                    ? '(Tối Đa)'
                                                    : '(Tối Thiểu)'
                                            }}
                                        </td>
                                        <td
                                            class="text-emerald-650 px-4 py-3.5 text-right font-bold dark:text-emerald-400"
                                        >
                                            {{ formatVnd(cfg.bonus_amount) }}
                                        </td>
                                        <td
                                            class="px-4 py-3.5 text-right font-bold text-amber-600 dark:text-amber-500"
                                        >
                                            {{ cfg.commission_rate }}%
                                        </td>
                                        <td class="px-6 py-3.5 text-right">
                                            <Button
                                                @click="editConfig(cfg)"
                                                size="sm"
                                                class="dark:text-slate-250 h-8 rounded-xl border border-slate-200 bg-white text-[11px] font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800"
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
        <div
            v-if="activeEmployeeKpi"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border-slate-200 bg-white text-slate-800 shadow-2xl dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100"
            >
                <CardHeader
                    class="border-slate-150 dark:border-slate-850 flex flex-row items-center justify-between border-b pb-4"
                >
                    <div>
                        <CardTitle
                            class="text-lg font-bold text-slate-900 dark:text-white"
                            >Bảng KPI Chi Tiết -
                            {{ activeEmployeeKpi.full_name }}</CardTitle
                        >
                        <CardDescription
                            class="text-xs text-slate-500 dark:text-slate-400"
                            >{{ activeEmployeeKpi.job_title }} | Kỳ:
                            {{ period }}</CardDescription
                        >
                    </div>
                    <Button
                        @click="activeEmployeeKpi = null"
                        variant="ghost"
                        class="hover:text-slate-650 p-2 text-slate-400 dark:hover:text-white"
                    >
                        <X class="h-5 w-5" />
                    </Button>
                </CardHeader>
                <CardContent class="space-y-6 p-6">
                    <!-- Overall KPI Summary -->
                    <div
                        class="border-slate-150 dark:border-slate-850 grid grid-cols-3 rounded-xl border bg-slate-50 p-4 text-center dark:bg-slate-950/60"
                    >
                        <div
                            class="border-r border-slate-200 dark:border-slate-800"
                        >
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Tổng điểm KPI
                            </p>
                            <p
                                class="text-2xl font-black text-slate-900 dark:text-white"
                            >
                                {{ activeEmployeeKpi.current_kpi?.total_score }}
                            </p>
                        </div>
                        <div
                            class="border-r border-slate-200 dark:border-slate-800"
                        >
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Tiền thưởng đạt
                            </p>
                            <p
                                class="text-lg font-bold text-emerald-600 dark:text-emerald-400"
                            >
                                {{
                                    formatVnd(
                                        activeEmployeeKpi.current_kpi
                                            ?.total_bonus ?? 0,
                                    )
                                }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Hoa hồng doanh số
                            </p>
                            <p
                                class="text-lg font-bold text-amber-600 dark:text-amber-500"
                            >
                                {{
                                    formatVnd(
                                        activeEmployeeKpi.current_kpi
                                            ?.total_commission ?? 0,
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- Metrics Details List -->
                    <div class="space-y-4">
                        <h4
                            class="text-xs font-bold text-slate-500 dark:text-slate-400"
                        >
                            CHI TIẾT CHỈ TIÊU KPI THỰC TẾ:
                        </h4>

                        <div
                            v-for="metric in activeEmployeeKpi.current_kpi
                                ?.metrics"
                            :key="metric.id"
                            class="border-slate-150 space-y-3 rounded-xl border bg-slate-50/50 p-4 dark:border-slate-800/80 dark:bg-slate-950/40"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <h5
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        {{ metric.metric_name }}
                                    </h5>
                                    <p
                                        class="text-slate-550 dark:text-slate-450 text-xs"
                                    >
                                        Code: {{ metric.metric_code }}
                                    </p>
                                </div>
                                <span
                                    class="rounded px-2 py-0.5 text-xs font-semibold"
                                    :class="
                                        metric.is_achieved
                                            ? 'border border-emerald-200/50 bg-emerald-50 text-emerald-700 dark:border-emerald-900/30 dark:bg-emerald-950/30 dark:text-emerald-400'
                                            : 'border border-rose-200/50 bg-rose-50 text-rose-700 dark:border-rose-900/30 dark:bg-rose-950/30 dark:text-rose-400'
                                    "
                                >
                                    {{
                                        metric.is_achieved
                                            ? 'Đạt chỉ tiêu'
                                            : 'Chưa đạt'
                                    }}
                                </span>
                            </div>

                            <div
                                class="border-slate-150 dark:border-slate-855 grid grid-cols-3 gap-2 border-t border-b py-2 text-xs text-slate-500 dark:text-slate-400"
                            >
                                <div>
                                    Mục tiêu:
                                    <span
                                        class="font-bold text-slate-900 dark:text-white"
                                        >{{ metric.target_value }}</span
                                    >
                                </div>
                                <div>
                                    Thực tế:
                                    <span
                                        class="font-bold text-slate-900 dark:text-white"
                                        >{{ metric.actual_value }}</span
                                    >
                                </div>
                                <div>
                                    Chấm điểm:
                                    <span
                                        class="text-sm font-black text-slate-900 dark:text-white"
                                        >{{ metric.score }} / 100</span
                                    >
                                </div>
                            </div>

                            <div
                                class="flex justify-between text-xs text-slate-500 dark:text-slate-400"
                            >
                                <span
                                    >Thưởng nóng đạt chỉ tiêu:
                                    <strong
                                        class="text-emerald-600 dark:text-emerald-400"
                                        >{{
                                            formatVnd(metric.bonus_earned)
                                        }}</strong
                                    ></span
                                >
                                <span
                                    >Hoa hồng nhận:
                                    <strong
                                        class="text-amber-600 dark:text-amber-500"
                                        >{{
                                            formatVnd(metric.commission_earned)
                                        }}</strong
                                    ></span
                                >
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- MODAL: Edit Metric Config Setup -->
        <div
            v-if="activeConfigEdit"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-md overflow-hidden rounded-2xl border-slate-200 bg-white text-slate-800 shadow-2xl dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100"
            >
                <CardHeader
                    class="border-slate-150 dark:border-slate-850 flex flex-row items-center justify-between border-b pb-4"
                >
                    <div>
                        <CardTitle
                            class="text-lg font-bold text-slate-900 dark:text-white"
                            >Sửa Chỉ Tiêu KPI</CardTitle
                        >
                        <CardDescription
                            class="text-xs text-slate-500 dark:text-slate-400"
                            >{{ activeConfigEdit.metric_name }}</CardDescription
                        >
                    </div>
                    <Button
                        @click="activeConfigEdit = null"
                        variant="ghost"
                        class="hover:text-slate-650 p-2 text-slate-400 dark:hover:text-white"
                    >
                        <X class="h-5 w-5" />
                    </Button>
                </CardHeader>
                <form @submit.prevent="updateConfig">
                    <CardContent class="space-y-4 p-6 text-xs">
                        <div class="space-y-1.5">
                            <Label
                                for="weight"
                                class="font-semibold text-slate-600 dark:text-slate-400"
                                >Tỷ trọng điểm (%) trong tổng điểm KPI</Label
                            >
                            <Input
                                id="weight"
                                type="number"
                                v-model.number="configForm.weight"
                                min="0"
                                max="100"
                                class="border-slate-250 rounded-xl dark:border-slate-800"
                                required
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label
                                for="target"
                                class="font-semibold text-slate-600 dark:text-slate-400"
                                >Giá trị chỉ tiêu mục tiêu đề ra</Label
                            >
                            <Input
                                id="target"
                                type="number"
                                step="any"
                                v-model.number="configForm.target"
                                min="0"
                                class="border-slate-250 rounded-xl dark:border-slate-800"
                                required
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label
                                for="bonus"
                                class="font-semibold text-slate-600 dark:text-slate-400"
                                >Tiền thưởng nóng đạt chỉ tiêu (VND)</Label
                            >
                            <Input
                                id="bonus"
                                type="number"
                                v-model.number="configForm.bonus_amount"
                                min="0"
                                class="border-slate-250 rounded-xl dark:border-slate-800"
                                required
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label
                                for="commission"
                                class="font-semibold text-slate-600 dark:text-slate-400"
                                >Phần trăm hoa hồng doanh số xử lý (%) (nếu
                                có)</Label
                            >
                            <Input
                                id="commission"
                                type="number"
                                step="0.01"
                                v-model.number="configForm.commission_rate"
                                min="0"
                                max="100"
                                class="border-slate-250 rounded-xl dark:border-slate-800"
                                required
                            />
                        </div>
                    </CardContent>
                    <div
                        class="border-slate-150 dark:border-slate-850 flex justify-end gap-3 border-t bg-slate-50/50 p-6 dark:bg-slate-950/20"
                    >
                        <Button
                            type="button"
                            @click="activeConfigEdit = null"
                            variant="ghost"
                            class="font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"
                        >
                            Hủy
                        </Button>
                        <Button
                            type="submit"
                            class="rounded-xl bg-amber-600 font-semibold text-white hover:bg-amber-700"
                        >
                            Cập Nhật
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- MODAL: Submit 360-Degree Review Form -->
        <div
            v-if="isReviewModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="max-h-[90vh] w-full max-w-lg overflow-hidden overflow-y-auto rounded-2xl border-slate-200 bg-white text-slate-800 shadow-2xl dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100"
            >
                <CardHeader
                    class="border-slate-150 dark:border-slate-855 flex flex-row items-center justify-between border-b pb-4"
                >
                    <div>
                        <CardTitle
                            class="text-lg font-bold text-slate-900 dark:text-white"
                            >Phiếu Đánh Giá Hiệu Suất 360°</CardTitle
                        >
                        <CardDescription
                            class="text-xs text-slate-500 dark:text-slate-400"
                            >Gửi phiếu tự đánh giá, đánh giá quản lý hoặc đồng
                            nghiệp.</CardDescription
                        >
                    </div>
                    <Button
                        @click="isReviewModalOpen = false"
                        variant="ghost"
                        class="hover:text-slate-650 p-2 text-slate-400 dark:hover:text-white"
                    >
                        <X class="h-5 w-5" />
                    </Button>
                </CardHeader>
                <form @submit.prevent="submitReview">
                    <CardContent class="space-y-4 p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label
                                    for="review_employee"
                                    class="text-xs font-semibold text-slate-600 dark:text-slate-400"
                                    >Chọn Nhân Sự Chấm Điểm</Label
                                >
                                <select
                                    id="review_employee"
                                    v-model="reviewForm.employee_id"
                                    class="w-full cursor-pointer rounded-xl border border-slate-200 bg-white p-2.5 text-sm text-slate-900 focus:ring-1 focus:ring-amber-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                    required
                                >
                                    <option value="" disabled>
                                        -- Chọn nhân viên --
                                    </option>
                                    <option
                                        v-for="emp in employees"
                                        :key="emp.id"
                                        :value="emp.id"
                                    >
                                        {{ emp.full_name }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <Label
                                    for="reviewer_type"
                                    class="text-xs font-semibold text-slate-600 dark:text-slate-400"
                                    >Mối Quan Hệ Đánh Giá</Label
                                >
                                <select
                                    id="reviewer_type"
                                    v-model="reviewForm.reviewer_type"
                                    class="w-full cursor-pointer rounded-xl border border-slate-200 bg-white p-2.5 text-sm text-slate-900 focus:ring-1 focus:ring-amber-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                    required
                                >
                                    <option value="self">
                                        Tự đánh giá bản thân
                                    </option>
                                    <option value="manager">
                                        Quản lý đánh giá
                                    </option>
                                    <option value="peer">
                                        Đồng nghiệp đánh giá chéo
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Ratings section -->
                        <div
                            class="border-slate-150 dark:border-slate-850 space-y-4 border-t pt-4"
                        >
                            <h4
                                class="dark:text-amber-550 text-xs font-bold tracking-wider text-amber-600"
                            >
                                CHẤM ĐIỂM TIÊU CHÍ (Từ 1 đến 5 sao):
                            </h4>

                            <div class="space-y-3">
                                <!-- Communication -->
                                <div
                                    class="border-slate-150 flex items-center justify-between rounded-xl border bg-slate-50/40 p-3 dark:border-slate-800/80 dark:bg-zinc-950/40"
                                >
                                    <div class="pr-2">
                                        <Label
                                            class="text-sm font-bold text-slate-900 dark:text-white"
                                            >Kỹ năng giao tiếp</Label
                                        >
                                        <p
                                            class="dark:text-slate-450 text-[11px] leading-tight text-slate-500"
                                        >
                                            Khả năng truyền đạt, trao đổi thông
                                            tin với khách hàng & đồng đội.
                                        </p>
                                    </div>
                                    <input
                                        type="number"
                                        v-model.number="
                                            reviewForm.ratings.communication
                                        "
                                        min="1"
                                        max="5"
                                        class="border-slate-250 w-14 rounded-xl border bg-white px-2 py-1 text-center text-sm font-bold text-slate-900 dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                        required
                                    />
                                </div>

                                <!-- Teamwork -->
                                <div
                                    class="border-slate-150 flex items-center justify-between rounded-xl border bg-slate-50/40 p-3 dark:border-slate-800/80 dark:bg-zinc-950/40"
                                >
                                    <div class="pr-2">
                                        <Label
                                            class="text-sm font-bold text-slate-900 dark:text-white"
                                            >Làm việc nhóm</Label
                                        >
                                        <p
                                            class="dark:text-slate-450 text-[11px] leading-tight text-slate-500"
                                        >
                                            Khả năng phối hợp đồng đội, hỗ trợ
                                            chạy việc trong ca.
                                        </p>
                                    </div>
                                    <input
                                        type="number"
                                        v-model.number="
                                            reviewForm.ratings.teamwork
                                        "
                                        min="1"
                                        max="5"
                                        class="border-slate-250 w-14 rounded-xl border bg-white px-2 py-1 text-center text-sm font-bold text-slate-900 dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                        required
                                    />
                                </div>

                                <!-- Reliability -->
                                <div
                                    class="border-slate-150 flex items-center justify-between rounded-xl border bg-slate-50/40 p-3 dark:border-slate-800/80 dark:bg-zinc-950/40"
                                >
                                    <div class="pr-2">
                                        <Label
                                            class="text-sm font-bold text-slate-900 dark:text-white"
                                            >Độ tin cậy & Đúng giờ</Label
                                        >
                                        <p
                                            class="dark:text-slate-450 text-[11px] leading-tight text-slate-500"
                                        >
                                            Ý thức giờ giấc đi làm, chấp hành kỷ
                                            luật.
                                        </p>
                                    </div>
                                    <input
                                        type="number"
                                        v-model.number="
                                            reviewForm.ratings.reliability
                                        "
                                        min="1"
                                        max="5"
                                        class="border-slate-250 w-14 rounded-xl border bg-white px-2 py-1 text-center text-sm font-bold text-slate-900 dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                        required
                                    />
                                </div>

                                <!-- Skills -->
                                <div
                                    class="border-slate-150 flex items-center justify-between rounded-xl border bg-slate-50/40 p-3 dark:border-slate-800/80 dark:bg-zinc-950/40"
                                >
                                    <div class="pr-2">
                                        <Label
                                            class="text-sm font-bold text-slate-900 dark:text-white"
                                            >Kỹ năng nghiệp vụ chuyên môn</Label
                                        >
                                        <p
                                            class="dark:text-slate-450 text-[11px] leading-tight text-slate-500"
                                        >
                                            Trình độ làm bếp, phục vụ hoặc thu
                                            ngân chuẩn quy trình.
                                        </p>
                                    </div>
                                    <input
                                        type="number"
                                        v-model.number="
                                            reviewForm.ratings.skills
                                        "
                                        min="1"
                                        max="5"
                                        class="border-slate-250 w-14 rounded-xl border bg-white px-2 py-1 text-center text-sm font-bold text-slate-900 dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                        required
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Comments -->
                        <div class="space-y-1.5">
                            <Label
                                for="comments"
                                class="text-xs font-semibold text-slate-600 dark:text-slate-400"
                                >Ý kiến đánh giá khác / Nhận xét tổng
                                quan</Label
                            >
                            <textarea
                                id="comments"
                                v-model="reviewForm.comments"
                                rows="3"
                                class="w-full rounded-xl border border-slate-200 bg-white p-2.5 text-sm text-slate-900 focus:ring-1 focus:ring-amber-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                                placeholder="Nhập ý kiến đóng góp ý kiến xây dựng cho nhân sự tại đây..."
                            ></textarea>
                        </div>
                    </CardContent>
                    <div
                        class="border-slate-150 dark:border-slate-850 flex justify-end gap-3 border-t bg-slate-50/50 p-6 dark:bg-slate-950/20"
                    >
                        <Button
                            type="button"
                            @click="isReviewModalOpen = false"
                            variant="ghost"
                            class="hover:text-slate-850 font-semibold text-slate-500 dark:text-slate-400 dark:hover:text-white"
                        >
                            Hủy
                        </Button>
                        <Button
                            type="submit"
                            class="rounded-xl bg-amber-600 font-semibold text-white hover:bg-amber-700"
                        >
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
