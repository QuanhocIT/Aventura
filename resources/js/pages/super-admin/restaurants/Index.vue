<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Plus,
    Search,
    Eye,
    ShieldCheck,
    ShieldOff,
    Crown,
    UserCheck,
    Building2,
    CheckCircle,
    CreditCard,
    Ban,
    TrendingUp,
    Activity,
    Sparkles,
    ThumbsUp,
    ChevronLeft,
    ChevronRight,
    BarChart3,
    ShieldAlert,
    Store,
    User,
    Mail,
    Phone,
    MapPin,
    Hash,
    Check,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { toast } from 'vue-sonner';
import AreaChart from '@/components/charts/AreaChart.vue';
import {
    PageHeader,
    StatCard,
    FilterBar,
    DataTable,
    StatusBadge,
    Pagination,
    ProgressBar,
} from '@/components/super-admin';
import type { Column } from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    restaurants: {
        data: Array<{
            id: number;
            name: string;
            code: string;
            status: string;
            plan: string;
            plan_code: string;
            owner: string;
            owner_email: string;
            owner_id?: number;
            branches_count: number;
            employees_count: number;
            tables_count: number;
            max_branches: number | null;
            max_tables: number | null;
            max_users: number | null;
            created_at: string;
            is_inactive_flagged?: boolean;
            last_active_at?: string;
        }>;
        links: any[];
        total: number;
        last_page: number;
    };
    plans: Array<{ id: number; code: string; name: string }>;
    filters: {
        status?: string;
        plan?: string;
        search?: string;
        flagged?: string;
    };
    stats: {
        total: number;
        active: number;
        paid: number;
        suspended: number;
        flagged?: number;
    };
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

const page = usePage();
watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.owner_temp_password) {
            toast.warning(
                `Mật khẩu tạm của chủ nhà hàng (chỉ hiển thị lần này): ${flash.owner_temp_password}`,
                { duration: 15000 },
            );
        }
    },
    { immediate: true, deep: true },
);

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status || 'all');
const planFilter = ref(props.filters.plan || 'all');
const flaggedFilter = ref(props.filters.flagged || 'all');

// Plan distribution pagination
const planPage = ref(1);
const planPageSize = 4;
const paginatedPlanDistribution = computed(() => {
    const start = (planPage.value - 1) * planPageSize;
    const end = start + planPageSize;

    return props.planDistribution?.slice(start, end) ?? [];
});
const totalPlanPages = computed(() => {
    return Math.ceil((props.planDistribution?.length ?? 0) / planPageSize);
});
function prevPlanPage() {
    if (planPage.value > 1) {
        planPage.value--;
    }
}
function nextPlanPage() {
    if (planPage.value < totalPlanPages.value) {
        planPage.value++;
    }
}

let searchTimer: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilter(), 400);
});

function applyFilter() {
    router.get(
        '/super-admin/restaurants',
        {
            search: search.value || undefined,
            status:
                status.value && status.value !== 'all'
                    ? status.value
                    : undefined,
            plan:
                planFilter.value && planFilter.value !== 'all'
                    ? planFilter.value
                    : undefined,
            flagged:
                flaggedFilter.value && flaggedFilter.value !== 'all'
                    ? flaggedFilter.value
                    : undefined,
        },
        { preserveState: true, replace: true },
    );
}

async function impersonateUser(ownerId: number | undefined) {
    if (!ownerId) {
        toast.error('Không tìm thấy tài khoản chủ sở hữu để sắm vai.');

        return;
    }

    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description:
                'Bạn có chắc chắn muốn đăng nhập sắm vai dưới quyền của tài khoản chủ sở hữu này không?',
            variant: 'default',
        })
    ) {
        const reason = window.prompt(
            'Nhập lý do sắm vai (tối thiểu 10 ký tự):',
            'Hỗ trợ xử lý ticket của tenant',
        );

        if (!reason || reason.trim().length < 10) {
            toast.error('Lý do sắm vai phải có ít nhất 10 ký tự.');

            return;
        }

        router.post(`/super-admin/impersonate/${ownerId}`, { reason });
    }
}

function formatQuota(used: number, limit: number | null) {
    return limit === null ? `${used}/∞` : `${used}/${limit}`;
}

function quotaPercent(used: number, limit: number | null) {
    if (limit === null || limit === 0) {
        return 0;
    }

    return Math.round((used / limit) * 100);
}
void quotaPercent;

function getChurnRisk(restaurantId: number) {
    return props.aiInsights?.churn_risks?.find(
        (r) => r.restaurant_id === restaurantId,
    );
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
    active: 'Hoạt động',
    suspended: 'Tạm ngưng',
    expired: 'Hết hạn',
};

// Dialog tạo nhà hàng
const showCreate = ref(false);
const activeCreateTab = ref<'info' | 'owner'>('info');
const createForm = useForm({
    name: '',
    tax_code: '',
    phone: '',
    email: '',
    address: '',
    plan_id: '',
    owner_name: '',
    owner_email: '',
    timezone: 'Asia/Ho_Chi_Minh',
    currency: 'VND',
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
            toast.error(
                'Vui lòng điền đầy đủ và đúng định dạng các trường bắt buộc!',
            );

            if (errors.owner_name || errors.owner_email) {
                activeCreateTab.value = 'owner';
            } else {
                activeCreateTab.value = 'info';
            }
        },
    });
}

// Dialog đổi trạng thái
const showStatus = ref(false);
const selectedRestaurant = ref<{
    id: number;
    name: string;
    status: string;
} | null>(null);
const statusForm = useForm({ status: '', reason: '' });
function openStatus(r: any) {
    selectedRestaurant.value = r;
    statusForm.status = r.status;
    showStatus.value = true;
}
function submitStatus() {
    if (!selectedRestaurant.value) {
        return;
    }

    statusForm.patch(
        `/super-admin/restaurants/${selectedRestaurant.value.id}/status`,
        {
            onSuccess: () => {
                showStatus.value = false;
            },
        },
    );
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
                @click="
                    () => {
                        flaggedFilter = flaggedFilter === '1' ? '' : '1';
                        applyFilter();
                    }
                "
            />
        </div>

        <!-- Biểu đồ & Phân tích Trực quan (SaaS Analytics) -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Biểu đồ xu hướng đăng ký mới -->
            <div
                class="flex flex-col justify-between rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm"
            >
                <div>
                    <div
                        class="flex items-center justify-between border-b border-border/50 pb-2"
                    >
                        <div class="space-y-1">
                            <h3
                                class="flex items-center gap-2 text-sm leading-none font-semibold tracking-tight text-foreground"
                            >
                                <TrendingUp class="size-4 text-primary" /> Xu
                                hướng đăng ký mới
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Thống kê số lượng tenant mới đăng ký trong 6
                                tháng qua
                            </p>
                        </div>
                        <div
                            class="flex items-center gap-1 rounded-lg bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary"
                        >
                            <Sparkles class="size-3.5" /> Dữ liệu thật
                        </div>
                    </div>
                    <div class="flex h-44 items-end">
                        <AreaChart
                            v-if="
                                registrationGrowth &&
                                registrationGrowth.length > 0
                            "
                            :series="registrationGrowth"
                            gradient-id="regGrowthGrad"
                            color="#3b82f6"
                        >
                            <template #tooltip="{ point }">
                                <div
                                    class="flex flex-col gap-0.5 text-[10px] font-bold text-foreground"
                                >
                                    <span
                                        class="font-mono text-[8px] tracking-wider text-muted-foreground uppercase"
                                        >{{ point.label }}</span
                                    >
                                    <span>+{{ point.value }} nhà hàng mới</span>
                                </div>
                            </template>
                        </AreaChart>
                        <div
                            v-else
                            class="w-full py-10 text-center text-xs text-muted-foreground"
                        >
                            Không có dữ liệu đăng ký mới
                        </div>
                    </div>
                </div>
            </div>

            <!-- Phân bổ gói dịch vụ hoạt động -->
            <div
                class="flex flex-col justify-between rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm"
            >
                <div>
                    <div
                        class="flex items-center justify-between border-b border-border/50 pb-2"
                    >
                        <div class="space-y-1">
                            <h3
                                class="flex items-center gap-2 text-sm leading-none font-semibold tracking-tight text-foreground"
                            >
                                <BarChart3 class="size-4 text-emerald-500" />
                                Phân bổ gói dịch vụ hoạt động
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Tỷ lệ sử dụng các gói cước của các tenant đang
                                hoạt động
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3.5">
                        <div
                            v-for="plan in paginatedPlanDistribution"
                            :key="plan.code"
                            class="space-y-1.5"
                        >
                            <div
                                class="flex items-center justify-between text-xs font-medium"
                            >
                                <span
                                    class="flex items-center gap-1.5 text-foreground"
                                >
                                    <Crown
                                        v-if="
                                            plan.code === 'pro' ||
                                            plan.code === 'enterprise'
                                        "
                                        class="size-3.5 text-amber-500"
                                    />
                                    {{ plan.name }}
                                    <span
                                        class="font-mono text-[10px] text-muted-foreground uppercase"
                                        >({{ plan.code }})</span
                                    >
                                </span>
                                <span class="text-muted-foreground tabular-nums"
                                    >{{ plan.count }} nhà hàng</span
                                >
                            </div>
                            <div
                                class="h-2 w-full overflow-hidden rounded-full bg-secondary"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="{
                                        'bg-slate-400': plan.code === 'free',
                                        'bg-sky-500': plan.code === 'starter',
                                        'bg-purple-500': plan.code === 'pro',
                                        'bg-emerald-500':
                                            plan.code === 'enterprise',
                                    }"
                                    :style="{
                                        width: `${(plan.count / (stats?.active || 1)) * 100}%`,
                                    }"
                                ></div>
                            </div>
                        </div>

                        <!-- Pagination Controls -->
                        <div
                            v-if="totalPlanPages > 1"
                            class="mt-4 flex items-center justify-between border-t border-border/40 pt-3 text-xs font-semibold"
                        >
                            <span class="text-muted-foreground">
                                Trang {{ planPage }} / {{ totalPlanPages }}
                            </span>
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="size-7 rounded-lg"
                                    :disabled="planPage === 1"
                                    @click="prevPlanPage"
                                >
                                    <ChevronLeft class="size-4" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="size-7 rounded-lg"
                                    :disabled="planPage === totalPlanPages"
                                    @click="nextPlanPage"
                                >
                                    <ChevronRight class="size-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Đánh giá & Đề xuất Hậu mãi (AI Insights) -->
        <div v-if="aiInsights" class="grid gap-6 lg:grid-cols-12">
            <!-- Cột trái: Đánh giá Sức khỏe Hệ thống -->
            <div
                class="flex flex-col justify-between rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm lg:col-span-5"
            >
                <div>
                    <div
                        class="flex items-center justify-between border-b border-border/50 pb-2"
                    >
                        <div class="space-y-1">
                            <h3
                                class="flex items-center gap-2 text-sm leading-none font-semibold tracking-tight text-foreground"
                            >
                                <Activity
                                    class="size-4 animate-pulse text-rose-500"
                                />
                                Đánh giá Sức khỏe SaaS
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Phân tích hành vi hoạt động và dự báo doanh thu
                                định kỳ
                            </p>
                        </div>
                    </div>

                    <!-- Health Score Gauge -->
                    <div class="mt-5 flex items-center gap-5">
                        <div
                            class="relative flex size-20 shrink-0 items-center justify-center rounded-full border-4 border-muted"
                            :style="{
                                borderColor:
                                    aiInsights.overall_health.color === 'green'
                                        ? '#10b981'
                                        : aiInsights.overall_health.color ===
                                            'yellow'
                                          ? '#f59e0b'
                                          : '#ef4444',
                            }"
                        >
                            <span
                                class="text-xl font-black text-foreground tabular-nums"
                                >{{ aiInsights.overall_health.score }}%</span
                            >
                        </div>
                        <div class="flex-1 space-y-1">
                            <p
                                class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Điểm sức khỏe
                            </p>
                            <p class="text-sm font-extrabold text-foreground">
                                {{ aiInsights.overall_health.label }}
                            </p>
                            <p class="text-[11px] text-muted-foreground/90">
                                Tính toán dựa trên tần suất đơn hàng 30 ngày, tỷ
                                lệ gia hạn gói cước và tần suất sử dụng hệ
                                thống.
                            </p>
                        </div>
                    </div>

                    <!-- Segment Cards -->
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div
                            class="flex flex-col justify-between rounded-lg border border-border/40 bg-secondary/35 p-3"
                        >
                            <span
                                class="text-[10px] font-bold text-muted-foreground uppercase"
                                >Đang dùng thử</span
                            >
                            <span
                                class="mt-1 text-lg font-black text-foreground tabular-nums"
                                >{{ aiInsights.segments.trial_active }}</span
                            >
                        </div>
                        <div
                            class="flex flex-col justify-between rounded-lg border border-border/40 bg-secondary/35 p-3"
                        >
                            <span
                                class="text-[10px] font-bold text-muted-foreground uppercase"
                                >Free không hoạt động</span
                            >
                            <span
                                class="mt-1 text-lg font-black text-rose-500 tabular-nums dark:text-rose-400"
                                >{{ aiInsights.segments.free_inactive }}</span
                            >
                        </div>
                        <div
                            class="flex flex-col justify-between rounded-lg border border-border/40 bg-secondary/35 p-3"
                        >
                            <span
                                class="text-[10px] font-bold text-muted-foreground uppercase"
                                >Rủi ro rời bỏ (At Risk)</span
                            >
                            <span
                                class="mt-1 text-lg font-black text-amber-500 tabular-nums"
                                >{{ aiInsights.segments.at_risk }}</span
                            >
                        </div>
                        <div
                            class="flex flex-col justify-between rounded-lg border border-border/40 bg-secondary/35 p-3"
                        >
                            <span
                                class="text-[10px] font-bold text-muted-foreground uppercase"
                                >Mới tạo (30 ngày)</span
                            >
                            <span
                                class="mt-1 text-lg font-black text-sky-500 tabular-nums"
                                >{{ aiInsights.segments.new }}</span
                            >
                        </div>
                    </div>

                    <!-- MRR Forecast -->
                    <div
                        class="mt-5 rounded-lg border border-border/40 bg-secondary/20 p-3.5"
                    >
                        <h4
                            class="mb-2 flex items-center gap-1.5 text-xs font-bold text-foreground"
                        >
                            <TrendingUp class="size-3.5 text-primary" /> Dự báo
                            MRR (3 tháng tới)
                        </h4>
                        <div
                            class="flex items-center justify-between border-t border-border/30 pt-2 text-xs"
                            v-for="forecast in aiInsights.mrr_forecast"
                            :key="forecast.month"
                        >
                            <span class="text-muted-foreground">{{
                                forecast.month
                            }}</span>
                            <span
                                class="font-extrabold text-foreground tabular-nums"
                                >{{
                                    Number(
                                        forecast.predicted_mrr,
                                    ).toLocaleString()
                                }}
                                VND</span
                            >
                            <span
                                class="flex items-center gap-0.5 text-[10px] font-bold text-emerald-500 dark:text-emerald-400"
                            >
                                ▲ +{{
                                    forecast.trend === 'up' ? 'Tăng' : 'Ổn định'
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cột phải: Cảnh báo rời bỏ & Đề xuất CSKH -->
            <div
                class="flex flex-col justify-between rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm lg:col-span-7"
            >
                <div>
                    <div
                        class="mb-4 flex items-center justify-between border-b border-border/50 pb-2"
                    >
                        <div class="space-y-1">
                            <h3
                                class="flex items-center gap-2 text-sm leading-none font-semibold tracking-tight text-foreground"
                            >
                                <ShieldAlert class="size-4 text-amber-500" />
                                Cảnh báo Rủi ro Churn & Gợi ý CSKH
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Phát hiện tự động các tenant có nguy cơ ngưng sử
                                dụng hoặc hết hạn cước
                            </p>
                        </div>
                    </div>

                    <div class="max-h-[360px] space-y-4 overflow-y-auto pr-1.5">
                        <div
                            v-for="risk in aiInsights.churn_risks"
                            :key="risk.restaurant_id"
                            class="rounded-xl border p-4 transition-all duration-300"
                            :class="{
                                'border-rose-500/30 bg-rose-500/5 hover:bg-rose-500/10':
                                    risk.risk_level === 'high',
                                'border-amber-500/30 bg-amber-500/5 hover:bg-amber-500/10':
                                    risk.risk_level === 'medium',
                                'border-slate-500/20 bg-slate-500/5 hover:bg-slate-500/10':
                                    risk.risk_level === 'low',
                            }"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 space-y-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <Link
                                            :href="`/super-admin/restaurants/${risk.restaurant_id}`"
                                            class="text-sm font-bold text-foreground hover:underline"
                                        >
                                            {{ risk.name }}
                                        </Link>
                                        <span
                                            class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-bold"
                                            :class="{
                                                'bg-rose-500/15 text-rose-600 dark:text-rose-400':
                                                    risk.risk_level === 'high',
                                                'bg-amber-500/15 text-amber-600 dark:text-amber-400':
                                                    risk.risk_level ===
                                                    'medium',
                                                'bg-slate-500/15 text-slate-600 dark:text-slate-400':
                                                    risk.risk_level === 'low',
                                            }"
                                        >
                                            Rủi ro: {{ risk.risk_score }}%
                                        </span>
                                    </div>

                                    <!-- Reasons -->
                                    <div class="mt-2 space-y-0.5">
                                        <div
                                            v-for="(
                                                reason, idx
                                            ) in risk.reasons"
                                            :key="idx"
                                            class="flex items-start gap-1 text-xs text-muted-foreground"
                                        >
                                            <span
                                                class="font-bold text-destructive"
                                                >•</span
                                            >
                                            <span>{{ reason }}</span>
                                        </div>
                                    </div>

                                    <!-- Recommendations -->
                                    <div
                                        class="mt-3 border-t border-border/30 pt-3"
                                    >
                                        <p
                                            class="mb-1.5 flex items-center gap-1 text-[11px] font-bold text-foreground"
                                        >
                                            <Sparkles
                                                class="size-3.5 text-primary"
                                            />
                                            Đề xuất hành động CSKH:
                                        </p>
                                        <div class="space-y-1.5">
                                            <div
                                                v-for="(
                                                    action, idx
                                                ) in risk.actions"
                                                :key="idx"
                                                class="flex items-start gap-1 rounded bg-primary/5 px-2.5 py-1 text-xs font-medium text-primary"
                                            >
                                                <ThumbsUp
                                                    class="mt-0.5 size-3 shrink-0 text-primary"
                                                />
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
                <Search
                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Tìm tên, mã, mã thuế..."
                    class="pl-9"
                />
            </div>
            <Select v-model="status" @update:model-value="applyFilter">
                <SelectTrigger class="w-[170px]">
                    <SelectValue placeholder="Tất cả trạng thái" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tất cả trạng thái</SelectItem>
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
                    <SelectItem value="all">Tất cả gói</SelectItem>
                    <SelectItem
                        v-for="p in plans"
                        :key="p.code"
                        :value="p.code"
                        >{{ p.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Select v-model="flaggedFilter" @update:model-value="applyFilter">
                <SelectTrigger class="w-[210px]">
                    <SelectValue placeholder="Tất cả hoạt động" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tất cả hoạt động</SelectItem>
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
                <div class="flex flex-wrap items-center gap-1.5">
                    <p class="font-medium">{{ row.name }}</p>
                    <span
                        v-if="row.is_inactive_flagged"
                        class="inline-flex items-center gap-1 rounded-md border border-rose-500/20 bg-rose-500/10 px-1.5 py-0.5 text-[9px] font-bold text-rose-700 dark:text-rose-300"
                    >
                        🚩 Cần hậu mãi
                    </span>
                    <span
                        v-if="getChurnRisk(row.id)?.risk_level === 'high'"
                        class="inline-flex items-center gap-1 rounded-md border border-amber-500/20 bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-bold text-amber-700 dark:text-amber-300"
                        title="AI cảnh báo rủi ro rời bỏ cao"
                    >
                        ⚠️ Rủi ro Churn
                    </span>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ row.owner_email }}
                </p>
                <p class="font-mono text-xs text-muted-foreground">
                    {{ row.code }}
                </p>
                <p class="mt-0.5 text-[10px] text-muted-foreground/70">
                    Hoạt động cuối: {{ row.last_active_at }}
                </p>
            </template>

            <template #cell-plan="{ row }">
                <span
                    :class="[
                        'flex items-center gap-1 text-xs font-medium',
                        row.plan_code === 'PRO'
                            ? 'text-purple-600 dark:text-purple-400'
                            : 'text-muted-foreground',
                    ]"
                >
                    <Crown v-if="row.plan_code === 'PRO'" class="size-3" />
                    {{ row.plan }}
                </span>
            </template>

            <template #cell-quota="{ row }">
                <div class="min-w-[140px] space-y-1.5">
                    <div class="flex items-center gap-2">
                        <span
                            class="w-7 font-mono text-[10px] text-muted-foreground"
                            >CN</span
                        >
                        <ProgressBar
                            :value="row.branches_count"
                            :max="row.max_branches ?? 999"
                            class="flex-1"
                        />
                        <span
                            class="font-mono text-[10px] text-muted-foreground tabular-nums"
                            >{{
                                formatQuota(
                                    row.branches_count,
                                    row.max_branches,
                                )
                            }}</span
                        >
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="w-7 font-mono text-[10px] text-muted-foreground"
                            >NV</span
                        >
                        <ProgressBar
                            :value="row.employees_count"
                            :max="row.max_users ?? 999"
                            class="flex-1"
                        />
                        <span
                            class="font-mono text-[10px] text-muted-foreground tabular-nums"
                            >{{
                                formatQuota(row.employees_count, row.max_users)
                            }}</span
                        >
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="w-7 font-mono text-[10px] text-muted-foreground"
                            >Bàn</span
                        >
                        <ProgressBar
                            :value="row.tables_count"
                            :max="row.max_tables ?? 999"
                            class="flex-1"
                        />
                        <span
                            class="font-mono text-[10px] text-muted-foreground tabular-nums"
                            >{{
                                formatQuota(row.tables_count, row.max_tables)
                            }}</span
                        >
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
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            title="Xem chi tiết"
                        >
                            <Eye class="size-4" />
                        </Button>
                    </Link>
                    <Button
                        v-if="row.owner_id"
                        variant="ghost"
                        size="icon-sm"
                        title="Sắm vai (Đăng nhập hộ)"
                        class="text-blue-600 hover:bg-blue-50 hover:text-blue-700 dark:text-blue-400 dark:hover:bg-blue-950/20"
                        @click="impersonateUser(row.owner_id)"
                    >
                        <UserCheck class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :title="
                            row.status === 'active' ? 'Tạm ngưng' : 'Kích hoạt'
                        "
                        @click="openStatus(row)"
                    >
                        <ShieldOff
                            v-if="row.status === 'active'"
                            class="size-4 text-amber-600 dark:text-amber-400"
                        />
                        <ShieldCheck
                            v-else
                            class="size-4 text-emerald-600 dark:text-emerald-400"
                        />
                    </Button>
                </div>
            </template>

            <template #pagination>
                <Pagination
                    v-if="restaurants.last_page > 1"
                    :links="restaurants.links"
                />
            </template>
        </DataTable>
    </div>

    <!-- Dialog Tạo nhà hàng -->
    <Dialog v-model:open="showCreate">
        <DialogContent
            class="max-w-lg overflow-hidden rounded-2xl border border-border/80 bg-background/95 p-0 shadow-2xl backdrop-blur-md"
        >
            <!-- Modal Header -->
            <DialogHeader class="border-b border-border/40 bg-muted/10 p-6">
                <DialogTitle
                    class="flex items-center gap-2 text-base font-bold"
                >
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-orange-500/20 bg-orange-500/10 text-orange-500"
                    >
                        <Store class="size-4.5" />
                    </div>
                    <span>Thêm nhà hàng mới</span>
                </DialogTitle>
            </DialogHeader>

            <!-- Tab Selector -->
            <div
                class="mx-6 mt-4 flex rounded-xl border-b border-border/40 bg-muted/40 p-1"
            >
                <button
                    type="button"
                    @click="activeCreateTab = 'info'"
                    class="flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg py-1.5 text-center text-xs font-bold transition-all"
                    :class="
                        activeCreateTab === 'info'
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                >
                    <Building2 class="size-3.5 text-orange-500" />
                    Thông tin nhà hàng
                </button>
                <button
                    type="button"
                    @click="activeCreateTab = 'owner'"
                    class="flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg py-1.5 text-center text-xs font-bold transition-all"
                    :class="
                        activeCreateTab === 'owner'
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                >
                    <User class="size-3.5 text-orange-500" />
                    Chủ sở hữu
                </button>
            </div>

            <form
                @submit.prevent="submitCreate"
                class="flex flex-col gap-4 p-6 pt-4"
            >
                <!-- Tab 1: Restaurant Info -->
                <div
                    v-show="activeCreateTab === 'info'"
                    class="animate-in space-y-4 duration-200 fade-in"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 grid gap-1.5">
                            <Label
                                class="flex items-center gap-1 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Tên nhà hàng
                                <span class="text-rose-500">*</span>
                            </Label>
                            <div class="relative flex items-center">
                                <div
                                    class="pointer-events-none absolute left-3 text-muted-foreground"
                                >
                                    <Store class="size-4 text-orange-500" />
                                </div>
                                <Input
                                    v-model="createForm.name"
                                    placeholder="VD: Nhà hàng Aventura Hải Phòng"
                                    class="rounded-xl border-border pl-9.5 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                    required
                                />
                            </div>
                            <p
                                v-if="createForm.errors.name"
                                class="text-xs font-semibold text-destructive"
                            >
                                {{ createForm.errors.name }}
                            </p>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                class="flex items-center gap-1 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Mã số thuế</Label
                            >
                            <div class="relative flex items-center">
                                <div
                                    class="pointer-events-none absolute left-3 text-muted-foreground"
                                >
                                    <Hash class="size-4 text-orange-500" />
                                </div>
                                <Input
                                    v-model="createForm.tax_code"
                                    placeholder="0123456789"
                                    class="rounded-xl border-border pl-9.5 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                class="flex items-center gap-1 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Số điện thoại</Label
                            >
                            <div class="relative flex items-center">
                                <div
                                    class="pointer-events-none absolute left-3 text-muted-foreground"
                                >
                                    <Phone class="size-4 text-orange-500" />
                                </div>
                                <Input
                                    v-model="createForm.phone"
                                    placeholder="0901234567"
                                    class="rounded-xl border-border pl-9.5 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                />
                            </div>
                        </div>

                        <div class="col-span-2 grid gap-1.5">
                            <Label
                                class="flex items-center gap-1 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Thư điện tử nhà hàng</Label
                            >
                            <div class="relative flex items-center">
                                <div
                                    class="pointer-events-none absolute left-3 text-muted-foreground"
                                >
                                    <Mail class="size-4 text-orange-500" />
                                </div>
                                <Input
                                    v-model="createForm.email"
                                    type="email"
                                    placeholder="contact@restaurant.com"
                                    class="rounded-xl border-border pl-9.5 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                />
                            </div>
                        </div>

                        <div class="col-span-2 grid gap-1.5">
                            <Label
                                class="flex items-center gap-1 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Địa chỉ</Label
                            >
                            <div class="relative flex items-center">
                                <div
                                    class="pointer-events-none absolute left-3 text-muted-foreground"
                                >
                                    <MapPin class="size-4 text-orange-500" />
                                </div>
                                <Input
                                    v-model="createForm.address"
                                    placeholder="123 Đường ABC, Quận 1..."
                                    class="rounded-xl border-border pl-9.5 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                />
                            </div>
                        </div>

                        <div class="col-span-2 grid gap-1.5">
                            <Label
                                class="flex items-center gap-1 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Gói dịch vụ <span class="text-rose-500">*</span>
                            </Label>
                            <Select v-model="createForm.plan_id">
                                <SelectTrigger
                                    class="rounded-xl border-border focus:border-orange-500 focus:ring-orange-500/20"
                                >
                                    <SelectValue placeholder="Chọn gói..." />
                                </SelectTrigger>
                                <SelectContent class="rounded-xl">
                                    <SelectItem
                                        v-for="p in plans"
                                        :key="p.id"
                                        :value="String(p.id)"
                                        >{{ p.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <p
                                v-if="createForm.errors.plan_id"
                                class="text-xs font-semibold text-destructive"
                            >
                                {{ createForm.errors.plan_id }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Owner Info -->
                <div
                    v-show="activeCreateTab === 'owner'"
                    class="animate-in space-y-4 duration-200 fade-in"
                >
                    <div class="grid gap-4">
                        <div class="grid gap-1.5">
                            <Label
                                class="flex items-center gap-1 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Tên chủ sở hữu
                                <span class="text-rose-500">*</span>
                            </Label>
                            <div class="relative flex items-center">
                                <div
                                    class="pointer-events-none absolute left-3 text-muted-foreground"
                                >
                                    <User class="size-4 text-orange-500" />
                                </div>
                                <Input
                                    v-model="createForm.owner_name"
                                    placeholder="Nguyễn Văn A"
                                    class="rounded-xl border-border pl-9.5 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                    required
                                />
                            </div>
                            <p
                                v-if="createForm.errors.owner_name"
                                class="text-xs font-semibold text-destructive"
                            >
                                {{ createForm.errors.owner_name }}
                            </p>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                class="flex items-center gap-1 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Email chủ sở hữu
                                <span class="text-rose-500">*</span>
                            </Label>
                            <div class="relative flex items-center">
                                <div
                                    class="pointer-events-none absolute left-3 text-muted-foreground"
                                >
                                    <Mail class="size-4 text-orange-500" />
                                </div>
                                <Input
                                    v-model="createForm.owner_email"
                                    type="email"
                                    placeholder="owner@email.com"
                                    class="rounded-xl border-border pl-9.5 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                    required
                                />
                            </div>
                            <p
                                v-if="createForm.errors.owner_email"
                                class="text-xs font-semibold text-destructive"
                            >
                                {{ createForm.errors.owner_email }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="mt-4 flex gap-3 border-t border-border/40 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        class="flex-1 cursor-pointer rounded-xl"
                        @click="showCreate = false"
                        >Hủy</Button
                    >
                    <Button
                        v-if="activeCreateTab === 'info'"
                        type="button"
                        class="flex-1 cursor-pointer rounded-xl bg-orange-500 font-bold text-white shadow-md transition-all hover:bg-orange-600"
                        @click="activeCreateTab = 'owner'"
                    >
                        Tiếp tục
                    </Button>
                    <Button
                        v-else
                        type="submit"
                        :disabled="createForm.processing"
                        class="flex-1 cursor-pointer rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 font-bold text-white shadow-md transition-all hover:from-orange-600 hover:to-amber-600"
                    >
                        <Check class="mr-1.5 size-4" />
                        {{
                            createForm.processing
                                ? 'Đang tạo...'
                                : 'Tạo nhà hàng'
                        }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Dialog Đổi trạng thái -->
    <Dialog v-model:open="showStatus">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle
                    >Đổi trạng thái: {{ selectedRestaurant?.name }}</DialogTitle
                >
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
                            <SelectItem value="suspended"
                                >⏸ Tạm ngưng</SelectItem
                            >
                            <SelectItem value="expired">❌ Hết hạn</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Lý do (tuỳ chọn)</Label>
                    <Input
                        v-model="statusForm.reason"
                        placeholder="Ghi chú lý do..."
                    />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showStatus = false"
                        >Hủy</Button
                    >
                    <Button type="submit" :disabled="statusForm.processing"
                        >Xác nhận</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
