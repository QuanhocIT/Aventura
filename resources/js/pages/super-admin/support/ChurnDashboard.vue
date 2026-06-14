<script setup lang="ts">
useLayout: true;
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ShieldAlert,
    AlertTriangle,
    CheckCircle2,
    Mail,
    Search,
    RefreshCw,
    SlidersHorizontal,
    Phone,
    User,
    TrendingDown,
    Calendar,
    HelpCircle,
    ChevronRight,
    ArrowUpDown,
    TicketCheck
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface RestaurantBreakdown {
    days_since_login: number;
    current_week_orders: number;
    prev_weekly_avg: number;
    unresolved_tickets: number;
    drop_percentage: number;
}

interface RestaurantData {
    id: number;
    name: string;
    code: string;
    status: string;
    health_score: number;
    churn_risk_level: 'high' | 'medium' | 'low';
    churn_risk_reason: string;
    churn_risk_flagged_at: string | null;
    last_health_checked_at: string | null;
    plan_name: string;
    owner_name: string;
    owner_email: string;
    owner_phone: string;
    breakdown: RestaurantBreakdown;
}

const props = defineProps<{
    stats: {
        total_checked: number;
        avg_health_score: number;
        high_risk: number;
        medium_risk: number;
        low_risk: number;
        emails_sent: number;
    };
    restaurants: {
        data: RestaurantData[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    plans: Array<{ id: number; name: string }>;
    filters: {
        risk_level?: string;
        plan_id?: string;
        search?: string;
    };
}>();

// Filter states
const search = ref(props.filters.search ?? '');
const riskLevel = ref(props.filters.risk_level ?? 'all');
const planId = ref(props.filters.plan_id ?? 'all');

const isRecalculating = ref(false);
const triggeringEmailId = ref<number | null>(null);

// Apply filters
const applyFilters = () => {
    router.get('/super-admin/churn-prediction', {
        search: search.value || undefined,
        risk_level: riskLevel.value !== 'all' ? riskLevel.value : undefined,
        plan_id: planId.value !== 'all' ? planId.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
};

// Clear filters
const resetFilters = () => {
    search.value = '';
    riskLevel.value = 'all';
    planId.value = 'all';
    applyFilters();
};

// Recalculate health metrics
const recalculateHealth = () => {
    isRecalculating.value = true;
    router.post('/super-admin/churn-prediction/recalculate', {}, {
        onSuccess: (page: any) => {
            toast.success(page.props.flash?.success ?? 'Đã cập nhật chỉ số sức khỏe hệ thống thành công!');
        },
        onError: () => {
            toast.error('Lỗi khi chạy quét sức khỏe doanh nghiệp.');
        },
        onFinish: () => {
            isRecalculating.value = false;
        }
    });
};

// Send manual care email
const sendOutreachEmail = (restaurantId: number) => {
    triggeringEmailId.value = restaurantId;
    router.post(`/super-admin/churn-prediction/trigger-email/${restaurantId}`, {}, {
        onSuccess: (page: any) => {
            toast.success(page.props.flash?.success ?? 'Đã gửi email thành công!');
        },
        onError: () => {
            toast.error('Không thể gửi email. Vui lòng kiểm tra cấu hình SMTP.');
        },
        onFinish: () => {
            triggeringEmailId.value = null;
        }
    });
};

// Styling helper for Health Score Badge
const getScoreBadgeClass = (score: number) => {
    if (score >= 80) {
return 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20';
}

    if (score >= 50) {
return 'bg-amber-500/10 text-amber-600 border border-amber-500/20';
}

    return 'bg-rose-500/10 text-rose-600 border border-rose-500/20 animate-pulse';
};

// Styling helper for Risk Level Badge
const getRiskBadgeClass = (level: 'high' | 'medium' | 'low') => {
    if (level === 'low') {
return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300';
}

    if (level === 'medium') {
return 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300';
}

    return 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300';
};

const getRiskLabel = (level: 'high' | 'medium' | 'low') => {
    if (level === 'low') {
return 'Nguy cơ thấp';
}

    if (level === 'medium') {
return 'Nguy cơ TB';
}

    return 'Nguy cơ cao (At-risk)';
};

// Hover Details Modal State
const selectedDetails = ref<RestaurantData | null>(null);

const openDetails = (restaurant: RestaurantData) => {
    selectedDetails.value = restaurant;
};

const closeDetails = () => {
    selectedDetails.value = null;
};
</script>

<template>
    <Head title="Customer Success & Churn Prediction Dashboard" />

    <div class="p-6 max-w-7xl mx-auto w-full space-y-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-border/60 pb-5">
            <div>
                <h1 class="text-2xl font-black tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 dark:from-white dark:to-slate-300 bg-clip-text text-transparent flex items-center gap-2">
                    <ShieldAlert class="size-6 text-rose-500" />
                    Dự Đoán Rời Bỏ & Chăm Sóc Khách Hàng (Customer Success & Churn)
                </h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Chủ động phát hiện các nhà hàng sắp ngừng sử dụng dịch vụ thông qua điểm sức khỏe (Tenant Health Score) dựa trên hành vi thực tế.
                </p>
            </div>
            <button
                type="button"
                @click="recalculateHealth"
                :disabled="isRecalculating"
                class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-primary-foreground hover:bg-primary/90 transition-all shadow-md cursor-pointer disabled:opacity-50"
            >
                <RefreshCw :class="['size-3.5', { 'animate-spin': isRecalculating }]" />
                Tính toán lại toàn bộ chỉ số
            </button>
        </div>

        <!-- Overviews Metric Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <!-- Health Score gauge card -->
            <Card class="bg-gradient-to-br from-teal-500/10 to-teal-500/5 border-teal-500/20">
                <CardContent class="pt-6 text-center space-y-2">
                    <p class="text-xs font-bold text-teal-600 dark:text-teal-400 uppercase tracking-wider">Điểm sức khỏe TB</p>
                    <div class="relative flex items-center justify-center">
                        <!-- Simple visual dial -->
                        <div class="text-3xl font-black text-teal-600 dark:text-teal-400 font-mono">{{ stats.avg_health_score }}%</div>
                    </div>
                    <p class="text-[10px] text-muted-foreground">Trung bình {{ stats.total_checked }} nhà hàng</p>
                </CardContent>
            </Card>

            <Card class="bg-gradient-to-br from-rose-500/10 to-rose-500/5 border-rose-500/20">
                <CardContent class="pt-6 text-center space-y-2">
                    <p class="text-xs font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider">Nguy cơ rời bỏ cao</p>
                    <div class="text-3xl font-black text-rose-600 dark:text-rose-400 font-mono flex items-center justify-center gap-1">
                        <AlertTriangle class="size-6 text-rose-500" />
                        {{ stats.high_risk }}
                    </div>
                    <p class="text-[10px] text-rose-600/80 font-semibold uppercase tracking-wider">Cần ưu tiên liên hệ ngay</p>
                </CardContent>
            </Card>

            <Card class="bg-gradient-to-br from-amber-500/10 to-amber-500/5 border-amber-500/20">
                <CardContent class="pt-6 text-center space-y-2">
                    <p class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Nguy cơ trung bình</p>
                    <div class="text-3xl font-black text-amber-600 dark:text-amber-400 font-mono">
                        {{ stats.medium_risk }}
                    </div>
                    <p class="text-[10px] text-muted-foreground">Theo dõi dấu hiệu suy giảm</p>
                </CardContent>
            </Card>

            <Card class="bg-gradient-to-br from-emerald-500/10 to-emerald-500/5 border-emerald-500/20">
                <CardContent class="pt-6 text-center space-y-2">
                    <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Vận hành khỏe mạnh</p>
                    <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400 font-mono flex items-center justify-center gap-1">
                        <CheckCircle2 class="size-6 text-emerald-500" />
                        {{ stats.low_risk }}
                    </div>
                    <p class="text-[10px] text-muted-foreground">Sử dụng đều đặn</p>
                </CardContent>
            </Card>

            <Card class="bg-gradient-to-br from-blue-500/10 to-blue-500/5 border-blue-500/20">
                <CardContent class="pt-6 text-center space-y-2">
                    <p class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">Đã tiếp cận (Email)</p>
                    <div class="text-3xl font-black text-blue-600 dark:text-blue-400 font-mono flex items-center justify-center gap-1">
                        <Mail class="size-6 text-blue-500" />
                        {{ stats.emails_sent }}
                    </div>
                    <p class="text-[10px] text-muted-foreground">Tự động kích hoạt coupon 30%</p>
                </CardContent>
            </Card>
        </div>

        <!-- Filters Block -->
        <Card class="bg-card/50">
            <CardContent class="p-4 flex flex-col sm:flex-row items-center gap-3">
                <div class="relative w-full sm:flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm theo tên nhà hàng, mã, hoặc email/sđt chủ..."
                        @keyup.enter="applyFilters"
                        class="w-full pl-9 pr-4 py-2 border border-border/80 rounded-xl text-xs focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary dark:bg-slate-900"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    <!-- Risk Filter -->
                    <div class="w-full sm:w-[150px]">
                        <Select v-model="riskLevel" @update:modelValue="applyFilters">
                            <SelectTrigger class="h-9 text-xs rounded-xl">
                                <SelectValue placeholder="Mức độ nguy cơ" />
                            </SelectTrigger>
                            <SelectContent class="text-xs">
                                <SelectItem value="all">Tất cả nguy cơ</SelectItem>
                                <SelectItem value="high">Nguy cơ cao</SelectItem>
                                <SelectItem value="medium">Nguy cơ trung bình</SelectItem>
                                <SelectItem value="low">Nguy cơ thấp</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Plan Filter -->
                    <div class="w-full sm:w-[150px]">
                        <Select v-model="planId" @update:modelValue="applyFilters">
                            <SelectTrigger class="h-9 text-xs rounded-xl">
                                <SelectValue placeholder="Gói dịch vụ" />
                            </SelectTrigger>
                            <SelectContent class="text-xs">
                                <SelectItem value="all">Tất cả các gói</SelectItem>
                                <SelectItem
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    :value="plan.id.toString()"
                                >
                                    {{ plan.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Actions -->
                    <button
                        type="button"
                        @click="applyFilters"
                        class="inline-flex items-center justify-center h-9 px-4 rounded-xl bg-muted font-bold text-xs hover:bg-muted/80 cursor-pointer transition-all"
                    >
                        Lọc kết quả
                    </button>
                    <button
                        v-if="search || riskLevel !== 'all' || planId !== 'all'"
                        type="button"
                        @click="resetFilters"
                        class="text-xs text-muted-foreground font-semibold hover:underline cursor-pointer"
                    >
                        Xóa bộ lọc
                    </button>
                </div>
            </CardContent>
        </Card>

        <!-- Tenants List Table -->
        <Card class="border border-border/60 overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-muted/40 border-b border-border/60 text-[10px] uppercase tracking-wider text-muted-foreground font-black">
                            <th class="p-4 w-[240px]">Nhà hàng</th>
                            <th class="p-4 w-[160px]">Chủ sở hữu</th>
                            <th class="p-4 w-[100px] text-center">Gói cước</th>
                            <th class="p-4 w-[120px] text-center">Sức khỏe (Score)</th>
                            <th class="p-4 w-[150px] text-center">Tình trạng rủi ro</th>
                            <th class="p-4 w-[160px] text-center">Tự động email</th>
                            <th class="p-4 text-right pr-6">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40 text-xs">
                        <tr
                            v-for="restaurant in restaurants.data"
                            :key="restaurant.id"
                            class="hover:bg-muted/20 transition-all"
                        >
                            <!-- Restaurant Column -->
                            <td class="p-4 font-medium">
                                <div class="font-bold text-slate-800 dark:text-slate-200">{{ restaurant.name }}</div>
                                <div class="text-[10px] text-muted-foreground font-mono flex items-center gap-1 mt-0.5">
                                    <span>Code: {{ restaurant.code }}</span>
                                    <span v-if="restaurant.status === 'suspended'" class="text-rose-500 font-bold ml-1 uppercase">(Tạm khóa)</span>
                                </div>
                            </td>

                            <!-- Owner Contact -->
                            <td class="p-4 text-muted-foreground">
                                <div class="font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                                    <User class="size-3 text-slate-400" />
                                    {{ restaurant.owner_name }}
                                </div>
                                <div class="text-[10px] leading-tight mt-0.5 flex flex-col">
                                    <span>{{ restaurant.owner_email }}</span>
                                    <span class="flex items-center gap-0.5 mt-0.5 text-slate-400 font-mono">
                                        <Phone class="size-2.5" /> {{ restaurant.owner_phone }}
                                    </span>
                                </div>
                            </td>

                            <!-- Plan -->
                            <td class="p-4 text-center">
                                <Badge variant="outline" class="font-semibold text-[10px] rounded-lg">
                                    {{ restaurant.plan_name }}
                                </Badge>
                            </td>

                            <!-- Health Score -->
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <span
                                        :class="['text-[11px] font-black font-mono rounded-full px-2 py-0.5', getScoreBadgeClass(restaurant.health_score)]"
                                    >
                                        {{ restaurant.health_score }}/100
                                    </span>
                                    <button
                                        type="button"
                                        @click="openDetails(restaurant)"
                                        class="text-muted-foreground hover:text-primary transition-all cursor-pointer"
                                        title="Xem chi tiết hành vi"
                                    >
                                        <HelpCircle class="size-3.5" />
                                    </button>
                                </div>
                            </td>

                            <!-- Churn Risk Level -->
                            <td class="p-4 text-center">
                                <span
                                    :class="['rounded-full text-[10px] font-bold px-2 py-0.5 border border-border/20', getRiskBadgeClass(restaurant.churn_risk_level)]"
                                >
                                    {{ getRiskLabel(restaurant.churn_risk_level) }}
                                </span>
                            </td>

                            <!-- Auto-outreach status -->
                            <td class="p-4 text-center text-[10px] text-muted-foreground">
                                <div v-if="restaurant.churn_risk_flagged_at" class="flex flex-col items-center gap-0.5 text-blue-600 dark:text-blue-400">
                                    <span class="flex items-center gap-1 font-bold">
                                        <Mail class="size-3" /> Đã gửi email
                                    </span>
                                    <span class="font-mono text-[9px] text-slate-400">{{ restaurant.churn_risk_flagged_at }}</span>
                                </div>
                                <div v-else class="text-slate-400 font-semibold italic">
                                    Chưa gửi
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="p-4 text-right pr-6">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Detail popup trigger -->
                                    <button
                                        type="button"
                                        @click="openDetails(restaurant)"
                                        class="inline-flex items-center justify-center rounded-lg border border-border/80 px-2.5 py-1 text-[11px] font-bold hover:bg-muted/70 cursor-pointer transition-all"
                                    >
                                        Chi tiết sức khỏe
                                    </button>

                                    <!-- Send Manual Campaign Outreach Email -->
                                    <button
                                        type="button"
                                        @click="sendOutreachEmail(restaurant.id)"
                                        :disabled="triggeringEmailId === restaurant.id"
                                        class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-blue-700 cursor-pointer transition-all disabled:opacity-50"
                                    >
                                        <Mail :class="['size-3', { 'animate-spin': triggeringEmailId === restaurant.id }]" />
                                        Gửi email tri ân
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="restaurants.data.length === 0">
                            <td colspan="7" class="p-8 text-center text-muted-foreground italic font-medium">
                                Không tìm thấy dữ liệu nhà hàng nào tương ứng với bộ lọc.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination links -->
            <div v-if="restaurants.last_page > 1" class="bg-muted/20 border-t border-border/60 p-4 flex items-center justify-between">
                <p class="text-xs text-muted-foreground">
                    Đang hiển thị trang <strong>{{ restaurants.current_page }}</strong> trên tổng số <strong>{{ restaurants.last_page }}</strong> trang (Tổng số {{ restaurants.total }} nhà hàng)
                </p>
                <div class="flex gap-1">
                    <Link
                        v-for="(link, idx) in restaurants.links"
                        :key="idx"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1 text-[11px] font-bold border rounded-lg transition-all',
                            link.active ? 'bg-primary border-primary text-primary-foreground' : 'bg-card border-border hover:bg-muted/50',
                            !link.url ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''
                        ]"
                    />
                </div>
            </div>
        </Card>

        <!-- Behavior details modal -->
        <Dialog :open="selectedDetails !== null" @update:open="closeDetails">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-slate-800 dark:text-slate-100">
                        <ShieldAlert class="size-5 text-rose-500" />
                        Chỉ số sức khỏe: {{ selectedDetails?.name }}
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Chi tiết phân tích hành vi sử dụng thực tế và lý do tính điểm rủi ro rời bỏ.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedDetails" class="space-y-4 pt-2">
                    <!-- Overall health score -->
                    <div class="flex items-center justify-between p-3 rounded-xl bg-muted/50 border border-border/40">
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Điểm sức khỏe hiện tại:</span>
                        <span :class="['text-sm font-black font-mono px-3 py-1 rounded-full', getScoreBadgeClass(selectedDetails.health_score)]">
                            {{ selectedDetails.health_score }} / 100
                        </span>
                    </div>

                    <!-- Behavior breakdown grid -->
                    <div class="space-y-3.5">
                        <h4 class="font-bold text-xs uppercase text-slate-500 tracking-wider">Phân rã các chỉ số thành phần</h4>
                        
                        <!-- Log Inactivity -->
                        <div class="flex items-start justify-between text-xs py-2 border-b border-border/40">
                            <div>
                                <p class="font-bold text-slate-800 dark:text-slate-200">Đăng nhập của Quản trị/Thu ngân</p>
                                <p class="text-[10px] text-muted-foreground">Mục tiêu: Đăng nhập đều đặn trong vòng 2 ngày</p>
                            </div>
                            <div class="text-right">
                                <span class="font-bold font-mono">{{ selectedDetails.breakdown.days_since_login }} ngày</span>
                                <p class="text-[9px] font-bold" :class="selectedDetails.breakdown.days_since_login > 2 ? 'text-rose-500' : 'text-emerald-500'">
                                    {{ selectedDetails.breakdown.days_since_login > 2 ? 'Inactivity Alert' : 'Healthy' }}
                                </p>
                            </div>
                        </div>

                        <!-- Order Frequency Drop -->
                        <div class="flex items-start justify-between text-xs py-2 border-b border-border/40">
                            <div>
                                <p class="font-bold text-slate-800 dark:text-slate-200">Đơn hàng tuần này vs 3 tuần trước</p>
                                <p class="text-[10px] text-muted-foreground">Tuần này: {{ selectedDetails.breakdown.current_week_orders }} đơn · TB 3 tuần trước: {{ selectedDetails.breakdown.prev_weekly_avg }} đơn/tuần</p>
                            </div>
                            <div class="text-right">
                                <span class="font-bold font-mono text-rose-500" v-if="selectedDetails.breakdown.drop_percentage > 0">
                                    Giảm -{{ selectedDetails.breakdown.drop_percentage }}%
                                </span>
                                <span class="font-bold font-mono text-emerald-500" v-else>
                                    Ổn định (0%)
                                </span>
                                <p class="text-[9px] font-bold" :class="selectedDetails.breakdown.drop_percentage >= 50 ? 'text-rose-500 animate-pulse' : 'text-slate-400'">
                                    {{ selectedDetails.breakdown.drop_percentage >= 50 ? 'Sudden Drop' : 'Normal' }}
                                </p>
                            </div>
                        </div>

                        <!-- Support complaints -->
                        <div class="flex items-start justify-between text-xs py-2 border-b border-border/40">
                            <div>
                                <p class="font-bold text-slate-800 dark:text-slate-200">Số lượng ticket khiếu nại chưa xử lý</p>
                                <p class="text-[10px] text-muted-foreground">Bao gồm ticket trạng thái open, in_progress, waiting_restaurant</p>
                            </div>
                            <div class="text-right">
                                <span class="font-bold font-mono">{{ selectedDetails.breakdown.unresolved_tickets }} ticket</span>
                                <p class="text-[9px] font-bold" :class="selectedDetails.breakdown.unresolved_tickets > 0 ? 'text-amber-500 font-black' : 'text-emerald-500'">
                                    {{ selectedDetails.breakdown.unresolved_tickets > 0 ? 'Unresolved Tickets' : 'Zero tickets' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Action items list -->
                    <div class="rounded-xl border border-rose-500/20 bg-rose-500/[0.03] p-4 space-y-2">
                        <p class="text-[10px] font-black text-rose-500 uppercase tracking-widest flex items-center gap-1">
                            <AlertTriangle class="size-3 text-rose-500 animate-bounce" />
                            Đánh giá nguyên nhân &amp; Khuyến nghị
                        </p>
                        <ul class="text-[11px] space-y-1.5 pl-3 list-disc text-rose-700 dark:text-rose-300 font-semibold leading-relaxed">
                            <li v-for="(reason, idx) in selectedDetails.churn_risk_reason.split(' | ')" :key="idx">
                                {{ reason }}
                            </li>
                        </ul>
                    </div>

                    <!-- Footer actions inside modal -->
                    <div class="flex justify-end gap-2 pt-3 border-t border-border/50">
                        <button
                            type="button"
                            @click="closeDetails"
                            class="inline-flex h-9 items-center justify-center rounded-xl border border-border px-4 text-xs font-bold hover:bg-muted/50 cursor-pointer transition-all"
                        >
                            Đóng cửa sổ
                        </button>
                        <button
                            type="button"
                            @click="sendOutreachEmail(selectedDetails.id); closeDetails()"
                            :disabled="triggeringEmailId === selectedDetails.id"
                            class="inline-flex h-9 items-center gap-1 rounded-xl bg-blue-600 px-4 text-xs font-bold text-white hover:bg-blue-700 cursor-pointer transition-all disabled:opacity-50 shadow-md"
                        >
                            <Mail :class="['size-3.5', { 'animate-spin': triggeringEmailId === selectedDetails.id }]" />
                            Gửi email cứu hộ ngay
                        </button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
