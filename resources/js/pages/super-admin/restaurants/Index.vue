<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Search, Eye, ShieldCheck, ShieldOff, Crown, UserCheck, Building2, CheckCircle, CreditCard, Ban, TrendingUp, AlertTriangle, Activity, Sparkles, ThumbsUp, ChevronRight, BarChart3, ShieldAlert, Store, User, Mail, Phone, MapPin, Hash, Tag, X, Check, Globe } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageHeader, StatCard, FilterBar, DataTable, StatusBadge, Pagination, ProgressBar } from '@/components/super-admin';
import type { Column } from '@/components/super-admin';
import AppLayout from '@/layouts/AppLayout.vue';
import AreaChart from '@/components/charts/AreaChart.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    restaurants: {
        data: Array<{
            id: number; name: string; code: string; status: string;
            plan: string; plan_code: string; owner: string; owner_email: string;
            owner_id?: number;
            branches_count: number; employees_count: number; tables_count: number;
            max_branches: number | null; max_tables: number | null; max_users: number | null;
            created_at: string;
            is_inactive_flagged?: boolean;
            last_active_at?: string;
        }>;
        links: any[];
        total: number;
        last_page: number;
    };
    plans: Array<{ id: number; code: string; name: string }>;
    filters: { status?: string; plan?: string; search?: string; flagged?: string };
    stats: { total: number; active: number; paid: number; suspended: number; flagged?: number };
    aiInsights?: {
        churn_risks: Array<{
            restaurant_id: number;
            name: string;
            risk_score: number;
            risk_level: 'high' | 'medium' | 'low';
            reasons: string[];
            actions: string[];
        }>;
        health_scores: Array<{
            restaurant_id: number;
            name: string;
            score: number;
            level: 'good' | 'fair' | 'poor';
            order_count_30d: number;
        }>;
        segments: {
            active_pro: number;
            trial_active: number;
            free_inactive: number;
            at_risk: number;
            churned: number;
            new: number;
        };
        mrr_forecast: Array<{
            month: string;
            predicted_mrr: number;
            trend: 'up' | 'down' | 'neutral';
        }>;
        overall_health: {
            score: number;
            label: string;
            color: string;
        };
    };
    planDistribution: Array<{ name: string; code: string; count: number }>;
    registrationGrowth: Array<{ label: string; value: number }>;
}>();

const search   = ref(props.filters.search ?? '');
const status   = ref(props.filters.status ?? '');
const planFilter = ref(props.filters.plan ?? '');
const flaggedFilter = ref(props.filters.flagged ?? '');

let searchTimer: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilter(), 400);
});

function applyFilter() {
    router.get('/super-admin/restaurants', {
        search: search.value || undefined,
        status: status.value || undefined,
        plan:   planFilter.value || undefined,
        flagged: flaggedFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

function impersonateUser(ownerId: number | undefined) {
    if (!ownerId) {
        alert('Không tìm thấy tài khoản chủ sở hữu để sắm vai.');
        return;
    }
    if (confirm('Bạn có chắc chắn muốn đăng nhập sắm vai dưới quyền của tài khoản chủ sở hữu này không?')) {
        router.post(`/super-admin/impersonate/${ownerId}`);
    }
}

function formatQuota(used: number, limit: number | null) {
    return limit === null ? `${used}/∞` : `${used}/${limit}`;
}

function quotaPercent(used: number, limit: number | null) {
    if (limit === null || limit === 0) return 0;
    return Math.round((used / limit) * 100);
}

function getChurnRisk(restaurantId: number) {
    return props.aiInsights?.churn_risks?.find(r => r.restaurant_id === restaurantId);
}

const columns: Column[] = [
    { key: 'name', label: 'Nhà hàng' },
    { key: 'plan', label: 'Gói' },
    { key: 'quota', label: 'Tài nguyên' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'created_at', label: 'Ngày tạo' },
    { key: 'actions', label: 'Thao tác', align: 'right' },
];

const statusLabel: Record<string, string> = {
    active: 'Hoạt động', suspended: 'Tạm ngưng', expired: 'Hết hạn',
};

// Dialog tạo nhà hàng
const showCreate = ref(false);
const activeCreateTab = ref<'info' | 'owner'>('info');
const createForm = useForm({
    name: '', tax_code: '', phone: '', email: '', address: '',
    plan_id: '', owner_name: '', owner_email: '',
    timezone: 'Asia/Ho_Chi_Minh', currency: 'VND',
});

watch(showCreate, (newVal) => {
    if (!newVal) {
        createForm.reset();
        activeCreateTab.value = 'info';
    }
});

function submitCreate() {
    createForm.post('/super-admin/restaurants', {
        onSuccess: () => {
            showCreate.value = false;
            createForm.reset();
            activeCreateTab.value = 'info';
            toast.success('Đã tạo nhà hàng thành công!');
        },
        onError: (errors: any) => {
            toast.error('Vui lòng điền đầy đủ và đúng định dạng các trường bắt buộc!');
            if (errors.owner_name || errors.owner_email) {
                activeCreateTab.value = 'owner';
            } else {
                activeCreateTab.value = 'info';
            }
        }
    });
}

// Dialog đổi trạng thái
const showStatus = ref(false);
const selectedRestaurant = ref<{ id: number; name: string; status: string } | null>(null);
const statusForm = useForm({ status: '', reason: '' });
function openStatus(r: any) {
    selectedRestaurant.value = r;
    statusForm.status = r.status;
    showStatus.value = true;
}
function submitStatus() {
    if (!selectedRestaurant.value) return;
    statusForm.patch(`/super-admin/restaurants/${selectedRestaurant.value.id}/status`, {
        onSuccess: () => { showStatus.value = false; },
    });
}
</script>

<template>
    <Head title="Quản lý nhà hàng" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Quản lý nhà hàng"
            :subtitle="`Tổng cộng ${restaurants.total ?? 0} nhà hàng`"
            :icon="Building2"
        >
            <template #actions>
                <Button @click="showCreate = true" class="gap-2">
                    <Plus class="size-4" /> Thêm nhà hàng
                </Button>
            </template>
        </PageHeader>

        <!-- Thống kê tổng quan -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <StatCard
                label="Tổng nhà hàng"
                :value="stats?.total ?? 0"
                :icon="Building2"
                color="blue"
                class=""
            />
            <StatCard
                label="Đang hoạt động"
                :value="stats?.active ?? 0"
                :icon="CheckCircle"
                color="emerald"
                class=""
            />
            <StatCard
                label="Gói trả phí"
                :value="stats?.paid ?? 0"
                :icon="CreditCard"
                color="purple"
                class=""
            />
            <StatCard
                label="Tạm ngưng / Khóa"
                :value="stats?.suspended ?? 0"
                :icon="Ban"
                color="amber"
                class=""
            />
            <StatCard
                label="Gắn cờ (Hậu mãi)"
                :value="stats?.flagged ?? 0"
                :icon="Ban"
                color="rose"
                clickable
                class=""
                @click="() => { flaggedFilter = flaggedFilter === '1' ? '' : '1'; applyFilter(); }"
            />
        </div>

        <!-- Biểu đồ & Phân tích Trực quan (SaaS Analytics) -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Biểu đồ xu hướng đăng ký mới -->
            <div class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-border/50">
                        <div class="space-y-1">
                            <h3 class="font-semibold leading-none tracking-tight flex items-center gap-2 text-sm text-foreground">
                                <TrendingUp class="size-4 text-primary" /> Xu hướng đăng ký mới
                            </h3>
                            <p class="text-xs text-muted-foreground">Thống kê số lượng tenant mới đăng ký trong 6 tháng qua</p>
                        </div>
                        <div class="rounded-lg bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary flex items-center gap-1">
                            <Sparkles class="size-3.5" /> Dữ liệu thật
                        </div>
                    </div>
                    <div class="h-44 flex items-end">
                        <AreaChart
                            v-if="registrationGrowth && registrationGrowth.length > 0"
                            :series="registrationGrowth"
                            gradient-id="regGrowthGrad"
                            color="#3b82f6"
                        >
                            <template #tooltip="{ point }">
                                <div class="flex flex-col gap-0.5 text-[10px] font-bold text-foreground">
                                    <span class="text-[8px] uppercase tracking-wider text-muted-foreground font-mono">{{ point.label }}</span>
                                    <span>+{{ point.value }} nhà hàng mới</span>
                                </div>
                            </template>
                        </AreaChart>
                        <div v-else class="w-full text-center py-10 text-xs text-muted-foreground">
                            Không có dữ liệu đăng ký mới
                        </div>
                    </div>
                </div>
            </div>

            <!-- Phân bổ gói dịch vụ hoạt động -->
            <div class="rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-border/50">
                        <div class="space-y-1">
                            <h3 class="font-semibold leading-none tracking-tight flex items-center gap-2 text-sm text-foreground">
                                <BarChart3 class="size-4 text-emerald-500" /> Phân bổ gói dịch vụ hoạt động
                            </h3>
                            <p class="text-xs text-muted-foreground">Tỷ lệ sử dụng các gói cước của các tenant đang hoạt động</p>
                        </div>
                    </div>
                    
                    <div class="space-y-3.5 mt-5">
                        <div v-for="plan in planDistribution" :key="plan.code" class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs font-medium">
                                <span class="flex items-center gap-1.5 text-foreground">
                                    <Crown v-if="plan.code === 'pro' || plan.code === 'enterprise'" class="size-3.5 text-amber-500" />
                                    {{ plan.name }}
                                    <span class="text-[10px] text-muted-foreground uppercase font-mono">({{ plan.code }})</span>
                                </span>
                                <span class="text-muted-foreground tabular-nums">{{ plan.count }} nhà hàng</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-secondary overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="{
                                        'bg-slate-400': plan.code === 'free',
                                        'bg-sky-500': plan.code === 'starter',
                                        'bg-purple-500': plan.code === 'pro',
                                        'bg-emerald-500': plan.code === 'enterprise'
                                    }"
                                    :style="{ width: `${(plan.count / (stats?.active || 1)) * 100}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Đánh giá & Đề xuất Hậu mãi (AI Insights) -->
        <div v-if="aiInsights" class="grid gap-6 lg:grid-cols-12">
            <!-- Cột trái: Đánh giá Sức khỏe Hệ thống -->
            <div class="lg:col-span-5 rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-border/50">
                        <div class="space-y-1">
                            <h3 class="font-semibold leading-none tracking-tight flex items-center gap-2 text-sm text-foreground">
                                <Activity class="size-4 text-rose-500 animate-pulse" /> Đánh giá Sức khỏe SaaS
                            </h3>
                            <p class="text-xs text-muted-foreground">Phân tích hành vi hoạt động và dự báo doanh thu định kỳ</p>
                        </div>
                    </div>

                    <!-- Health Score Gauge -->
                    <div class="flex items-center gap-5 mt-5">
                        <div class="relative flex items-center justify-center size-20 rounded-full border-4 border-muted shrink-0"
                             :style="{ borderColor: aiInsights.overall_health.color === 'green' ? '#10b981' : (aiInsights.overall_health.color === 'yellow' ? '#f59e0b' : '#ef4444') }">
                            <span class="text-xl font-black tabular-nums text-foreground">{{ aiInsights.overall_health.score }}%</span>
                        </div>
                        <div class="flex-1 space-y-1">
                            <p class="text-xs font-bold text-muted-foreground uppercase tracking-wide">Điểm sức khỏe</p>
                            <p class="text-sm font-extrabold text-foreground">{{ aiInsights.overall_health.label }}</p>
                            <p class="text-[11px] text-muted-foreground/90">
                                Tính toán dựa trên tần suất đơn hàng 30 ngày, tỷ lệ gia hạn gói cước và tần suất sử dụng hệ thống.
                            </p>
                        </div>
                    </div>

                    <!-- Segment Cards -->
                    <div class="grid grid-cols-2 gap-3 mt-5">
                        <div class="rounded-lg bg-secondary/35 p-3 flex flex-col justify-between border border-border/40">
                            <span class="text-[10px] font-bold text-muted-foreground uppercase">Trial đang chạy</span>
                            <span class="text-lg font-black text-foreground mt-1 tabular-nums">{{ aiInsights.segments.trial_active }}</span>
                        </div>
                        <div class="rounded-lg bg-secondary/35 p-3 flex flex-col justify-between border border-border/40">
                            <span class="text-[10px] font-bold text-muted-foreground uppercase">Free không hoạt động</span>
                            <span class="text-lg font-black text-rose-500 dark:text-rose-400 mt-1 tabular-nums">{{ aiInsights.segments.free_inactive }}</span>
                        </div>
                        <div class="rounded-lg bg-secondary/35 p-3 flex flex-col justify-between border border-border/40">
                            <span class="text-[10px] font-bold text-muted-foreground uppercase">Rủi ro rời bỏ (At Risk)</span>
                            <span class="text-lg font-black text-amber-500 mt-1 tabular-nums">{{ aiInsights.segments.at_risk }}</span>
                        </div>
                        <div class="rounded-lg bg-secondary/35 p-3 flex flex-col justify-between border border-border/40">
                            <span class="text-[10px] font-bold text-muted-foreground uppercase">Mới tạo (30 ngày)</span>
                            <span class="text-lg font-black text-sky-500 mt-1 tabular-nums">{{ aiInsights.segments.new }}</span>
                        </div>
                    </div>

                    <!-- MRR Forecast -->
                    <div class="mt-5 p-3.5 bg-secondary/20 rounded-lg border border-border/40">
                        <h4 class="text-xs font-bold text-foreground flex items-center gap-1.5 mb-2">
                            <TrendingUp class="size-3.5 text-primary" /> Dự báo MRR (3 tháng tới)
                        </h4>
                        <div class="flex items-center justify-between text-xs border-t border-border/30 pt-2" v-for="forecast in aiInsights.mrr_forecast" :key="forecast.month">
                            <span class="text-muted-foreground">{{ forecast.month }}</span>
                            <span class="font-extrabold text-foreground tabular-nums">{{ Number(forecast.predicted_mrr).toLocaleString() }} VND</span>
                            <span class="text-emerald-500 dark:text-emerald-400 font-bold flex items-center text-[10px] gap-0.5">
                                ▲ +{{ forecast.trend === 'up' ? 'Tăng' : 'Ổn định' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cột phải: Cảnh báo rời bỏ & Đề xuất CSKH -->
            <div class="lg:col-span-7 rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-border/50 mb-4">
                        <div class="space-y-1">
                            <h3 class="font-semibold leading-none tracking-tight flex items-center gap-2 text-sm text-foreground">
                                <ShieldAlert class="size-4 text-amber-500" /> Cảnh báo Rủi ro Churn & Gợi ý CSKH
                            </h3>
                            <p class="text-xs text-muted-foreground">Phát hiện tự động các tenant có nguy cơ ngưng sử dụng hoặc hết hạn cước</p>
                        </div>
                    </div>

                    <div class="space-y-4 overflow-y-auto max-h-[360px] pr-1.5">
                        <div v-for="risk in aiInsights.churn_risks" :key="risk.restaurant_id" 
                             class="p-4 rounded-xl border transition-all duration-300"
                             :class="{
                                 'border-rose-500/30 bg-rose-500/5 hover:bg-rose-500/10': risk.risk_level === 'high',
                                 'border-amber-500/30 bg-amber-500/5 hover:bg-amber-500/10': risk.risk_level === 'medium',
                                 'border-slate-500/20 bg-slate-500/5 hover:bg-slate-500/10': risk.risk_level === 'low'
                             }">
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <Link :href="`/super-admin/restaurants/${risk.restaurant_id}`" class="font-bold hover:underline text-sm text-foreground">
                                            {{ risk.name }}
                                        </Link>
                                        <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-bold"
                                              :class="{
                                                  'bg-rose-500/15 text-rose-600 dark:text-rose-400': risk.risk_level === 'high',
                                                  'bg-amber-500/15 text-amber-600 dark:text-amber-400': risk.risk_level === 'medium',
                                                  'bg-slate-500/15 text-slate-600 dark:text-slate-400': risk.risk_level === 'low'
                                              }">
                                            Rủi ro: {{ risk.risk_score }}%
                                        </span>
                                    </div>
                                    
                                    <!-- Reasons -->
                                    <div class="space-y-0.5 mt-2">
                                        <div v-for="(reason, idx) in risk.reasons" :key="idx" class="text-xs text-muted-foreground flex items-start gap-1">
                                            <span class="text-destructive font-bold">•</span>
                                            <span>{{ reason }}</span>
                                        </div>
                                    </div>

                                    <!-- Recommendations -->
                                    <div class="mt-3 pt-3 border-t border-border/30">
                                        <p class="text-[11px] font-bold text-foreground flex items-center gap-1 mb-1.5">
                                            <Sparkles class="size-3.5 text-primary" /> Đề xuất hành động CSKH:
                                        </p>
                                        <div class="space-y-1.5">
                                            <div v-for="(action, idx) in risk.actions" :key="idx" class="text-xs text-primary flex items-start gap-1 font-medium bg-primary/5 rounded px-2.5 py-1">
                                                <ThumbsUp class="size-3 mt-0.5 text-primary shrink-0" />
                                                <span>{{ action }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bộ lọc -->
        <FilterBar>
            <div class="relative min-w-48 flex-1">
                <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Tìm tên, mã, mã thuế..." class="pl-9" />
            </div>
            <Select v-model="status" @update:model-value="applyFilter">
                <SelectTrigger class="w-[170px]">
                    <SelectValue placeholder="Tất cả trạng thái" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả trạng thái</SelectItem>
                    <SelectItem value="active">Hoạt động</SelectItem>
                    <SelectItem value="suspended">Tạm ngưng</SelectItem>
                    <SelectItem value="expired">Hết hạn</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="planFilter" @update:model-value="applyFilter">
                <SelectTrigger class="w-[160px]">
                    <SelectValue placeholder="Tất cả gói" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả gói</SelectItem>
                    <SelectItem v-for="p in plans" :key="p.code" :value="p.code">{{ p.name }}</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="flaggedFilter" @update:model-value="applyFilter">
                <SelectTrigger class="w-[210px]">
                    <SelectValue placeholder="Tất cả hoạt động" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả hoạt động</SelectItem>
                    <SelectItem value="1">🚩 Chỉ bị gắn cờ</SelectItem>
                </SelectContent>
            </Select>
        </FilterBar>

        <!-- Bảng danh sách -->
        <DataTable
            :columns="columns"
            :rows="restaurants.data"
            :empty-icon="Building2"
            empty-title="Không tìm thấy nhà hàng nào"
            empty-description="Thử thay đổi bộ lọc hoặc thêm nhà hàng mới"
            class=""
        >
            <template #cell-name="{ row }">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <p class="font-medium">{{ row.name }}</p>
                    <span
                        v-if="row.is_inactive_flagged"
                        class="inline-flex items-center gap-1 rounded-md bg-rose-500/10 px-1.5 py-0.5 text-[9px] font-bold text-rose-700 dark:text-rose-300 border border-rose-500/20"
                    >
                        🚩 Cần hậu mãi
                    </span>
                    <span
                        v-if="getChurnRisk(row.id)?.risk_level === 'high'"
                        class="inline-flex items-center gap-1 rounded-md bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-bold text-amber-700 dark:text-amber-300 border border-amber-500/20"
                        title="AI cảnh báo rủi ro rời bỏ cao"
                    >
                        ⚠️ Rủi ro Churn
                    </span>
                </div>
                <p class="text-xs text-muted-foreground">{{ row.owner_email }}</p>
                <p class="font-mono text-xs text-muted-foreground">{{ row.code }}</p>
                <p class="mt-0.5 text-[10px] text-muted-foreground/70">Hoạt động cuối: {{ row.last_active_at }}</p>
            </template>

            <template #cell-plan="{ row }">
                <span :class="['flex items-center gap-1 text-xs font-medium', row.plan_code === 'PRO' ? 'text-purple-600 dark:text-purple-400' : 'text-muted-foreground']">
                    <Crown v-if="row.plan_code === 'PRO'" class="size-3" />
                    {{ row.plan }}
                </span>
            </template>

            <template #cell-quota="{ row }">
                <div class="space-y-1.5 min-w-[140px]">
                    <div class="flex items-center gap-2">
                        <span class="w-7 text-[10px] font-mono text-muted-foreground">CN</span>
                        <ProgressBar
                            :value="row.branches_count"
                            :max="row.max_branches ?? 999"
                            class="flex-1"
                        />
                        <span class="text-[10px] font-mono text-muted-foreground tabular-nums">{{ formatQuota(row.branches_count, row.max_branches) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-7 text-[10px] font-mono text-muted-foreground">NV</span>
                        <ProgressBar
                            :value="row.employees_count"
                            :max="row.max_users ?? 999"
                            class="flex-1"
                        />
                        <span class="text-[10px] font-mono text-muted-foreground tabular-nums">{{ formatQuota(row.employees_count, row.max_users) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-7 text-[10px] font-mono text-muted-foreground">Bàn</span>
                        <ProgressBar
                            :value="row.tables_count"
                            :max="row.max_tables ?? 999"
                            class="flex-1"
                        />
                        <span class="text-[10px] font-mono text-muted-foreground tabular-nums">{{ formatQuota(row.tables_count, row.max_tables) }}</span>
                    </div>
                </div>
            </template>

            <template #cell-status="{ row }">
                <StatusBadge :status="row.status">
                    {{ statusLabel[row.status] ?? row.status }}
                </StatusBadge>
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-muted-foreground">{{ row.created_at }}</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <Link :href="`/super-admin/restaurants/${row.id}`">
                        <Button variant="ghost" size="icon-sm" title="Xem chi tiết">
                            <Eye class="size-4" />
                        </Button>
                    </Link>
                    <Button
                        v-if="row.owner_id"
                        variant="ghost" size="icon-sm"
                        title="Sắm vai (Đăng nhập hộ)"
                        class="text-blue-600 hover:bg-blue-50 hover:text-blue-700 dark:text-blue-400 dark:hover:bg-blue-950/20"
                        @click="impersonateUser(row.owner_id)"
                    >
                        <UserCheck class="size-4" />
                    </Button>
                    <Button
                        variant="ghost" size="icon-sm"
                        :title="row.status === 'active' ? 'Tạm ngưng' : 'Kích hoạt'"
                        @click="openStatus(row)"
                    >
                        <ShieldOff v-if="row.status === 'active'" class="size-4 text-amber-600" />
                        <ShieldCheck v-else class="size-4 text-emerald-600" />
                    </Button>
                </div>
            </template>

            <template #pagination>
                <Pagination v-if="restaurants.last_page > 1" :links="restaurants.links" />
            </template>
        </DataTable>
    </div>

    <!-- Dialog Tạo nhà hàng -->
    <Dialog v-model:open="showCreate">
        <DialogContent class="max-w-lg rounded-2xl border border-border/80 bg-background/95 backdrop-blur-md shadow-2xl p-0 overflow-hidden">
            <!-- Modal Header -->
            <DialogHeader class="p-6 border-b border-border/40 bg-muted/10">
                <DialogTitle class="text-base font-bold flex items-center gap-2">
                    <div class="size-8 bg-orange-500/10 text-orange-500 border border-orange-500/20 rounded-lg flex items-center justify-center shrink-0">
                        <Store class="size-4.5" />
                    </div>
                    <span>Thêm nhà hàng mới</span>
                </DialogTitle>
            </DialogHeader>

            <!-- Tab Selector -->
            <div class="flex border-b border-border/40 mx-6 mt-4 bg-muted/40 p-1 rounded-xl">
                <button
                    type="button"
                    @click="activeCreateTab = 'info'"
                    class="flex-1 py-1.5 text-center text-xs font-bold rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1.5"
                    :class="activeCreateTab === 'info' ? 'bg-background shadow-xs text-foreground' : 'text-muted-foreground hover:text-foreground'"
                >
                    <Building2 class="size-3.5 text-orange-500" />
                    Thông tin nhà hàng
                </button>
                <button
                    type="button"
                    @click="activeCreateTab = 'owner'"
                    class="flex-1 py-1.5 text-center text-xs font-bold rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1.5"
                    :class="activeCreateTab === 'owner' ? 'bg-background shadow-xs text-foreground' : 'text-muted-foreground hover:text-foreground'"
                >
                    <User class="size-3.5 text-orange-500" />
                    Chủ sở hữu
                </button>
            </div>

            <form @submit.prevent="submitCreate" class="p-6 pt-4 flex flex-col gap-4">
                <!-- Tab 1: Restaurant Info -->
                <div v-show="activeCreateTab === 'info'" class="space-y-4 animate-in fade-in duration-200">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 grid gap-1.5">
                            <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                                Tên nhà hàng <span class="text-rose-500">*</span>
                            </Label>
                            <div class="relative flex items-center">
                                <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                    <Store class="size-4 text-orange-500" />
                                </div>
                                <Input v-model="createForm.name" placeholder="VD: Nhà hàng Aventura Hải Phòng" class="pl-9.5 rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500" required />
                            </div>
                            <p v-if="createForm.errors.name" class="text-xs text-destructive font-semibold">{{ createForm.errors.name }}</p>
                        </div>

                        <div class="grid gap-1.5">
                            <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">Mã số thuế</Label>
                            <div class="relative flex items-center">
                                <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                    <Hash class="size-4 text-orange-500" />
                                </div>
                                <Input v-model="createForm.tax_code" placeholder="0123456789" class="pl-9.5 rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500" />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">Số điện thoại</Label>
                            <div class="relative flex items-center">
                                <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                    <Phone class="size-4 text-orange-500" />
                                </div>
                                <Input v-model="createForm.phone" placeholder="0901234567" class="pl-9.5 rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500" />
                            </div>
                        </div>

                        <div class="col-span-2 grid gap-1.5">
                            <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">Email nhà hàng</Label>
                            <div class="relative flex items-center">
                                <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                    <Mail class="size-4 text-orange-500" />
                                </div>
                                <Input v-model="createForm.email" type="email" placeholder="contact@restaurant.com" class="pl-9.5 rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500" />
                            </div>
                        </div>

                        <div class="col-span-2 grid gap-1.5">
                            <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">Địa chỉ</Label>
                            <div class="relative flex items-center">
                                <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                    <MapPin class="size-4 text-orange-500" />
                                </div>
                                <Input v-model="createForm.address" placeholder="123 Đường ABC, Quận 1..." class="pl-9.5 rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500" />
                            </div>
                        </div>

                        <div class="col-span-2 grid gap-1.5">
                            <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                                Gói dịch vụ <span class="text-rose-500">*</span>
                            </Label>
                            <Select v-model="createForm.plan_id">
                                <SelectTrigger class="rounded-xl border-border focus:ring-orange-500/20 focus:border-orange-500">
                                    <SelectValue placeholder="Chọn gói..." />
                                </SelectTrigger>
                                <SelectContent class="rounded-xl">
                                    <SelectItem v-for="p in plans" :key="p.id" :value="String(p.id)">{{ p.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="createForm.errors.plan_id" class="text-xs text-destructive font-semibold">{{ createForm.errors.plan_id }}</p>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Owner Info -->
                <div v-show="activeCreateTab === 'owner'" class="space-y-4 animate-in fade-in duration-200">
                    <div class="grid gap-4">
                        <div class="grid gap-1.5">
                            <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                                Tên chủ sở hữu <span class="text-rose-500">*</span>
                            </Label>
                            <div class="relative flex items-center">
                                <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                    <User class="size-4 text-orange-500" />
                                </div>
                                <Input v-model="createForm.owner_name" placeholder="Nguyễn Văn A" class="pl-9.5 rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500" required />
                            </div>
                            <p v-if="createForm.errors.owner_name" class="text-xs text-destructive font-semibold">{{ createForm.errors.owner_name }}</p>
                        </div>

                        <div class="grid gap-1.5">
                            <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                                Email chủ sở hữu <span class="text-rose-500">*</span>
                            </Label>
                            <div class="relative flex items-center">
                                <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                    <Mail class="size-4 text-orange-500" />
                                </div>
                                <Input v-model="createForm.owner_email" type="email" placeholder="owner@email.com" class="pl-9.5 rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500" required />
                            </div>
                            <p v-if="createForm.errors.owner_email" class="text-xs text-destructive font-semibold">{{ createForm.errors.owner_email }}</p>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex gap-3 mt-4 pt-4 border-t border-border/40">
                    <Button type="button" variant="outline" class="flex-1 rounded-xl cursor-pointer" @click="showCreate = false">Hủy</Button>
                    <Button v-if="activeCreateTab === 'info'" type="button" class="flex-1 bg-orange-500 hover:bg-orange-600 text-white rounded-xl shadow-md transition-all cursor-pointer font-bold" @click="activeCreateTab = 'owner'">
                        Tiếp tục
                    </Button>
                    <Button v-else type="submit" :disabled="createForm.processing" class="flex-1 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white rounded-xl shadow-md transition-all cursor-pointer font-bold">
                        <Check class="mr-1.5 size-4" />
                        {{ createForm.processing ? 'Đang tạo...' : 'Tạo nhà hàng' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Dialog Đổi trạng thái -->
    <Dialog v-model:open="showStatus">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Đổi trạng thái: {{ selectedRestaurant?.name }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitStatus" class="grid gap-4 py-2">
                <div class="grid gap-1.5">
                    <Label>Trạng thái mới</Label>
                    <Select v-model="statusForm.status">
                        <SelectTrigger>
                            <SelectValue placeholder="Chọn trạng thái..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">✅ Kích hoạt</SelectItem>
                            <SelectItem value="suspended">⏸ Tạm ngưng</SelectItem>
                            <SelectItem value="expired">❌ Hết hạn</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Lý do (tuỳ chọn)</Label>
                    <Input v-model="statusForm.reason" placeholder="Ghi chú lý do..." />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showStatus = false">Hủy</Button>
                    <Button type="submit" :disabled="statusForm.processing">Xác nhận</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
