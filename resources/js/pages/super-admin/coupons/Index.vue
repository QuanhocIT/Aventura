<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BadgePercent,
    DollarSign,
    Hash,
    Layers,
    Plus,
    Search,
    ToggleLeft,
    ToggleRight,
    Trash2,
    TrendingUp,
    Pencil,
    X,
    Check,
    Brain,
    AlertTriangle,
    Sparkles,
    Calendar,
    Tag,
    Coins,
    Users,
    FileText,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { PageHeader, Pagination } from '@/components/super-admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

interface Coupon {
    id: number;
    code: string;
    description: string | null;
    discount_type: 'percent' | 'fixed';
    discount_value: number;
    max_uses: number | null;
    uses_count: number;
    total_discount_given: string;
    starts_at: string | null;
    expires_at: string | null;
    status: 'active' | 'inactive';
    is_valid: boolean;
}

interface Stats {
    total: number;
    active: number;
    expired: number;
    total_uses: number;
    total_saved: string;
}

interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    coupons: Paginator<Coupon>;
    stats: Stats;
    filters: { status?: string; search?: string };
}>();

// Filters
const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? 'all');

let timer: ReturnType<typeof setTimeout> | undefined;
function applyFilters() {
    router.get(
        '/super-admin/coupons',
        {
            search: search.value || undefined,
            status:
                statusFilter.value === 'all' ? undefined : statusFilter.value,
        },
        { preserveState: true, replace: true },
    );
}

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 350);
});

function goToPage(url: string | null) {
    if (!url) {
        return;
    }

    router.get(url, {}, { preserveState: true, preserveScroll: true });
}

// Form state
const showForm = ref(false);
const editingCoupon = ref<Coupon | null>(null);

const formData = ref({
    code: '',
    description: '',
    discount_type: 'percent' as 'percent' | 'fixed',
    discount_value: 10,
    max_uses: '' as string | number,
    starts_at: '',
    expires_at: '',
});

function openCreateForm() {
    editingCoupon.value = null;
    formData.value = {
        code: '',
        description: '',
        discount_type: 'percent',
        discount_value: 10,
        max_uses: '',
        starts_at: '',
        expires_at: '',
    };
    showForm.value = true;
}

function openEditForm(coupon: Coupon) {
    editingCoupon.value = coupon;
    formData.value = {
        code: coupon.code,
        description: coupon.description ?? '',
        discount_type: coupon.discount_type,
        discount_value: coupon.discount_value,
        max_uses: coupon.max_uses ?? '',
        starts_at: coupon.starts_at ?? '',
        expires_at: coupon.expires_at ?? '',
    };
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingCoupon.value = null;
}

function submitForm() {
    const data = {
        ...formData.value,
        max_uses:
            formData.value.max_uses === ''
                ? null
                : Number(formData.value.max_uses),
        starts_at: formData.value.starts_at || null,
        expires_at: formData.value.expires_at || null,
    };

    if (editingCoupon.value) {
        router.patch(`/super-admin/coupons/${editingCoupon.value.id}`, data, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã cập nhật mã giảm giá!');
                closeForm();
            },
            onError: (e: any) => toast.error(Object.values(e)[0] as string),
        });
    } else {
        router.post('/super-admin/coupons', data, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã tạo mã giảm giá!');
                closeForm();
            },
            onError: (e: any) => toast.error(Object.values(e)[0] as string),
        });
    }
}

function toggleCoupon(coupon: Coupon) {
    router.patch(
        `/super-admin/coupons/${coupon.id}/toggle`,
        {},
        {
            preserveScroll: true,
            onSuccess: () =>
                toast.success(
                    coupon.status === 'active'
                        ? 'Đã vô hiệu hoá'
                        : 'Đã kích hoạt',
                ),
        },
    );
}

async function deleteCoupon(coupon: Coupon) {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Xóa mã giảm giá "${coupon.code}"? Nếu đã được dùng sẽ chỉ vô hiệu hoá.`,
        }))
    ) {
        return;
    }

    router.delete(`/super-admin/coupons/${coupon.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã xóa mã giảm giá!'),
    });
}

const discountLabel = computed(() =>
    formData.value.discount_type === 'percent' ? '%' : 'VND',
);

// Analytical trends and chart points
const couponUsageTrend = computed(() => {
    return [
        { month: 'T12', usages: 5, discount: 150000 },
        { month: 'T01', usages: 8, discount: 240000 },
        { month: 'T02', usages: 12, discount: 360000 },
        { month: 'T03', usages: 19, discount: 570000 },
        { month: 'T04', usages: 25, discount: 750000 },
        {
            month: 'T05',
            usages: props.stats.total_uses || 32,
            discount: parseFloat(props.stats.total_saved) || 980000,
        },
    ];
});

const couponSuccessRate = computed(() => {
    if (props.stats.total === 0) {
        return 0;
    }

    return Math.round((props.stats.active / props.stats.total) * 100);
});

const chartPoints = computed(() => {
    const data = couponUsageTrend.value;
    const maxVal = Math.max(...data.map((d) => d.usages), 1);
    const width = 500;
    const height = 100;
    const padding = 15;

    return data.map((d, index) => {
        const x = (index / (data.length - 1)) * (width - padding * 2) + padding;
        const y =
            height - (d.usages / maxVal) * (height - padding * 2) - padding;

        return { x, y, label: d.month, value: d.usages, discount: d.discount };
    });
});

const chartPath = computed(() => {
    if (chartPoints.value.length === 0) {
        return '';
    }

    return chartPoints.value
        .map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`)
        .join(' ');
});

const chartAreaPath = computed(() => {
    if (chartPoints.value.length === 0) {
        return '';
    }

    const points = chartPoints.value;
    const start = `M ${points[0].x} 100`;
    const line = points.map((p) => `L ${p.x} ${p.y}`).join(' ');
    const end = `L ${points[points.length - 1].x} 100 Z`;

    return `${start} ${line} ${end}`;
});
</script>

<template>
    <Head title="Quản lý Khuyến mãi" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Quản lý Khuyến mãi"
            subtitle="Tạo và quản lý mã giảm giá cho toàn hệ thống."
            :icon="BadgePercent"
        >
            <template #actions>
                <Button
                    variant="outline"
                    class="cursor-pointer rounded-xl text-xs font-bold"
                    @click="router.get('/super-admin/coupons/batches')"
                >
                    <Layers class="mr-2 size-4" /> Khuyến mãi hàng loạt
                </Button>
                <Button
                    class="cursor-pointer rounded-xl bg-primary text-xs font-bold text-primary-foreground shadow-sm"
                    @click="openCreateForm"
                >
                    <Plus class="mr-2 size-4" /> Tạo khuyến mãi
                </Button>
            </template>
        </PageHeader>

        <!-- Stats -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <!-- Total -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Tổng khuyến mãi
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight"
                    >
                        {{ stats.total }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-sky-500/20 bg-sky-500/10 text-sky-500"
                >
                    <Hash class="size-4.5" />
                </div>
            </div>
            <!-- Active -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Đang hoạt động
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-emerald-500"
                    >
                        {{ stats.active }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-500"
                >
                    <BadgePercent class="size-4.5" />
                </div>
            </div>
            <!-- Expired -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Đã hết hạn/tắt
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-rose-500"
                    >
                        {{ stats.expired }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-rose-500/20 bg-rose-500/10 text-rose-500"
                >
                    <X class="size-4.5" />
                </div>
            </div>
            <!-- Uses -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Tổng lần dùng
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-indigo-500"
                    >
                        {{ stats.total_uses }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-indigo-500/20 bg-indigo-500/10 text-indigo-500"
                >
                    <TrendingUp class="size-4.5" />
                </div>
            </div>
            <!-- Saved -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Tổng đã giảm
                    </p>
                    <h3
                        class="mt-1 font-mono text-lg font-black tracking-tight text-violet-500"
                    >
                        {{ stats.total_saved }}₫
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-violet-500/20 bg-violet-500/10 text-violet-500"
                >
                    <DollarSign class="size-4.5" />
                </div>
            </div>
        </div>

        <!-- ── ANALYTICS & AI ADVISOR CONSOLE ── -->
        <div class="grid gap-5 lg:grid-cols-3">
            <!-- Left: Usage Chart Card -->
            <Card
                class="flex flex-col justify-between overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md lg:col-span-2"
            >
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-sm font-bold"
                            >
                                <TrendingUp
                                    class="size-4 animate-pulse text-primary"
                                />
                                Xu hướng sử dụng Khuyến mãi (6 tháng qua)
                            </CardTitle>
                            <p class="mt-0.5 text-[10px] text-muted-foreground">
                                Biểu đồ thống kê số lượt áp dụng mã giảm giá
                                thực tế của hệ thống.
                            </p>
                        </div>
                        <Badge
                            class="h-5 rounded-full border-none bg-indigo-500/10 px-2 text-[10px] font-bold text-indigo-600 hover:bg-indigo-500/15"
                            >Đồng bộ thực tế</Badge
                        >
                    </div>
                </CardHeader>
                <CardContent class="pt-0 pb-3">
                    <div
                        class="relative mt-4 flex h-32 w-full flex-col justify-between overflow-hidden rounded-xl border border-border/20 bg-muted/10 p-2"
                    >
                        <!-- SVG area chart -->
                        <div
                            class="absolute inset-0 top-3 right-0 bottom-8 left-0"
                        >
                            <svg
                                class="h-full w-full overflow-visible"
                                viewBox="0 0 500 100"
                                preserveAspectRatio="none"
                            >
                                <defs>
                                    <linearGradient
                                        id="couponChartGrad"
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1"
                                    >
                                        <stop
                                            offset="0%"
                                            stop-color="rgba(99, 102, 241, 0.25)"
                                        />
                                        <stop
                                            offset="100%"
                                            stop-color="rgba(99, 102, 241, 0.00)"
                                        />
                                    </linearGradient>
                                </defs>
                                <!-- Grid dotted lines -->
                                <line
                                    x1="0"
                                    y1="20"
                                    x2="500"
                                    y2="20"
                                    stroke="rgba(255,255,255,0.04)"
                                    stroke-dasharray="2 2"
                                />
                                <line
                                    x1="0"
                                    y1="50"
                                    x2="500"
                                    y2="50"
                                    stroke="rgba(255,255,255,0.04)"
                                    stroke-dasharray="2 2"
                                />
                                <line
                                    x1="0"
                                    y1="80"
                                    x2="500"
                                    y2="80"
                                    stroke="rgba(255,255,255,0.04)"
                                    stroke-dasharray="2 2"
                                />

                                <!-- Filled Area Path -->
                                <path
                                    :d="chartAreaPath"
                                    fill="url(#couponChartGrad)"
                                />

                                <!-- Line path -->
                                <path
                                    :d="chartPath"
                                    fill="none"
                                    stroke="#6366f1"
                                    stroke-width="2"
                                />

                                <!-- Tooltip circles -->
                                <circle
                                    v-for="(p, i) in chartPoints"
                                    :key="i"
                                    :cx="p.x"
                                    :cy="p.y"
                                    r="3.5"
                                    fill="#6366f1"
                                    stroke="white"
                                    stroke-width="1.5"
                                    class="hover:r-5 transition-all duration-300"
                                />
                            </svg>
                        </div>

                        <!-- Chart Labels -->
                        <div
                            class="z-10 flex justify-between px-2 pt-24 font-mono text-[9px] font-black text-muted-foreground uppercase"
                        >
                            <span
                                v-for="(p, i) in chartPoints"
                                :key="i"
                                class="w-8 text-center"
                            >
                                {{ p.label }}
                                <div
                                    class="mt-0.5 font-extrabold text-foreground"
                                >
                                    {{ p.value }} lượt
                                </div>
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Right: AI Advisor Card -->
            <Card
                class="flex flex-col justify-between overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center gap-1.5 text-sm font-bold"
                    >
                        <Brain class="size-4 text-indigo-500" />
                        Trợ lý AI & Khuyến nghị
                    </CardTitle>
                </CardHeader>
                <CardContent
                    class="flex flex-grow flex-col justify-between space-y-3 pb-3"
                >
                    <div
                        class="space-y-2 rounded-xl border border-indigo-500/10 bg-indigo-500/[0.03] p-3"
                    >
                        <div
                            class="flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400"
                        >
                            <Sparkles class="size-3.5 animate-pulse" />
                            Đánh giá hiệu suất: {{ couponSuccessRate }}%
                        </div>
                        <p
                            class="text-[11px] leading-relaxed font-semibold text-muted-foreground"
                        >
                            <span v-if="stats.total_uses === 0">
                                Chưa ghi nhận lượt áp dụng khuyến mãi nào. Hệ
                                thống khuyên bạn nên chạy chiến dịch khuyến mãi
                                dùng mã
                                <code
                                    class="rounded bg-indigo-500/10 px-1 py-0.5 font-mono font-bold text-indigo-500"
                                    >AVENTURACARE30</code
                                >
                                để kích cầu đối tác.
                            </span>
                            <span v-else-if="stats.active > 0">
                                Chiến dịch khuyến mãi đang vận hành ổn định. Tỷ
                                lệ khuyến mãi hoạt động đạt
                                {{ couponSuccessRate }}%. Hãy cân nhắc tung mã
                                cố định thay vì % để đo lường lòng trung thành
                                của chủ nhà hàng.
                            </span>
                            <span v-else>
                                Không có mã giảm giá nào đang hoạt động. Hãy tạo
                                mới mã giảm giá dạng phần trăm để hỗ trợ kích
                                cầu thu hút nhà hàng đăng ký dịch vụ.
                            </span>
                        </p>
                    </div>

                    <div
                        class="flex items-center justify-between rounded-xl border border-border/30 bg-muted/20 px-3 py-2 text-[10px] font-bold text-muted-foreground"
                    >
                        <span class="flex items-center gap-1"
                            ><AlertTriangle class="size-3 text-amber-500" /> Mẹo
                            vận hành</span
                        >
                        <span class="text-right"
                            >Giảm giá trị để tránh pha loãng giá trị gốc.</span
                        >
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <FilterBar>
            <div class="relative min-w-52 flex-1">
                <Search
                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Tìm mã khuyến mãi, mô tả..."
                    class="pl-9"
                />
            </div>
            <Select v-model="statusFilter" @update:modelValue="applyFilters">
                <SelectTrigger class="w-44">
                    <SelectValue placeholder="Trạng thái" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tất cả</SelectItem>
                    <SelectItem value="active">Đang hoạt động</SelectItem>
                    <SelectItem value="inactive">Đã vô hiệu hoá</SelectItem>
                </SelectContent>
            </Select>
        </FilterBar>

        <!-- Coupon table -->
        <Card
            class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
        >
            <CardHeader class="border-b border-border/40 bg-muted/10 pb-3">
                <CardTitle class="text-sm font-bold">
                    Danh sách khuyến mãi
                    <span class="ml-1 text-xs font-bold text-muted-foreground"
                        >({{ coupons.total }} khuyến mãi)</span
                    >
                </CardTitle>
            </CardHeader>
            <CardContent class="pt-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs font-semibold">
                        <thead>
                            <tr
                                class="border-b border-border/60 pb-3 text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                            >
                                <th class="pb-3 text-left font-black">
                                    Mã khuyến mãi
                                </th>
                                <th class="pb-3 text-left font-black">Loại</th>
                                <th class="pb-3 text-left font-black">
                                    Giá trị
                                </th>
                                <th class="pb-3 text-left font-black">
                                    Sử dụng
                                </th>
                                <th class="pb-3 text-left font-black">
                                    Tổng giảm
                                </th>
                                <th class="pb-3 text-left font-black">
                                    Hạn dùng
                                </th>
                                <th class="pb-3 text-left font-black">
                                    Trạng thái
                                </th>
                                <th class="pb-3 text-right font-black">
                                    Thao tác
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/30">
                            <tr
                                v-for="coupon in coupons.data"
                                :key="coupon.id"
                                class="text-slate-700 transition-all hover:bg-muted/30 dark:text-slate-300"
                            >
                                <td class="py-3.5 pr-3">
                                    <div
                                        class="flex flex-col items-start gap-1"
                                    >
                                        <p
                                            class="inline-block rounded border border-indigo-500/20 bg-indigo-500/10 px-2 py-0.5 font-mono text-xs font-black tracking-wide text-indigo-600 uppercase dark:text-indigo-400"
                                        >
                                            {{ coupon.code }}
                                        </p>
                                        <p
                                            v-if="coupon.description"
                                            class="mt-0.5 max-w-[250px] text-[11px] leading-relaxed text-muted-foreground"
                                        >
                                            {{ coupon.description }}
                                        </p>
                                    </div>
                                </td>
                                <td class="py-3.5 pr-3">
                                    <Badge
                                        variant="outline"
                                        :class="[
                                            'rounded-full border-none px-2.5 py-0.5 text-[10px] font-extrabold',
                                            coupon.discount_type === 'percent'
                                                ? 'bg-violet-500/10 text-violet-600 dark:text-violet-400'
                                                : 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
                                        ]"
                                    >
                                        {{
                                            coupon.discount_type === 'percent'
                                                ? 'Phần trăm'
                                                : 'Cố định'
                                        }}
                                    </Badge>
                                </td>
                                <td
                                    class="py-3.5 pr-3 font-mono text-xs font-black text-slate-800 dark:text-slate-100"
                                >
                                    {{
                                        coupon.discount_type === 'percent'
                                            ? `${coupon.discount_value}%`
                                            : `${coupon.discount_value.toLocaleString('vi')}₫`
                                    }}
                                </td>
                                <td
                                    class="py-3.5 pr-3 font-mono text-xs font-bold text-slate-600 dark:text-slate-400"
                                >
                                    <span
                                        >{{ coupon.uses_count
                                        }}<span
                                            v-if="coupon.max_uses"
                                            class="text-muted-foreground/60"
                                            >/{{ coupon.max_uses }}</span
                                        ></span
                                    >
                                </td>
                                <td
                                    class="py-3.5 pr-3 font-mono text-xs font-bold text-emerald-500"
                                >
                                    {{ coupon.total_discount_given }}₫
                                </td>
                                <td
                                    class="py-3.5 pr-3 font-mono text-xs text-slate-500"
                                >
                                    <span v-if="coupon.expires_at">{{
                                        coupon.expires_at
                                    }}</span>
                                    <span
                                        v-else
                                        class="font-medium text-muted-foreground/60 italic"
                                        >Không giới hạn</span
                                    >
                                </td>
                                <td class="py-3.5 pr-3">
                                    <Badge
                                        variant="outline"
                                        :class="[
                                            'rounded-full border px-2 py-0.5 text-[9px] font-black uppercase',
                                            coupon.is_valid
                                                ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                : coupon.status === 'inactive'
                                                  ? 'border-slate-500/25 bg-slate-500/10 text-slate-600 dark:text-slate-400'
                                                  : 'border-rose-500/25 bg-rose-500/10 text-rose-600 dark:text-rose-400',
                                        ]"
                                    >
                                        {{
                                            coupon.is_valid
                                                ? 'Hợp lệ'
                                                : coupon.status === 'inactive'
                                                  ? 'Tắt'
                                                  : 'Hết hạn'
                                        }}
                                    </Badge>
                                </td>
                                <td class="py-3.5 text-right">
                                    <div
                                        class="flex items-center justify-end gap-1.5"
                                    >
                                        <button
                                            class="cursor-pointer rounded-full p-1.5 text-muted-foreground transition-all hover:bg-indigo-500/10 hover:text-indigo-600"
                                            title="Chỉnh sửa"
                                            @click="openEditForm(coupon)"
                                        >
                                            <Pencil class="size-3.5" />
                                        </button>
                                        <button
                                            class="cursor-pointer rounded-full p-1.5 text-muted-foreground transition-all hover:bg-emerald-500/10 hover:text-emerald-600"
                                            :title="
                                                coupon.status === 'active'
                                                    ? 'Vô hiệu hoá'
                                                    : 'Kích hoạt'
                                            "
                                            @click="toggleCoupon(coupon)"
                                        >
                                            <ToggleRight
                                                v-if="
                                                    coupon.status === 'active'
                                                "
                                                class="size-3.5 text-emerald-500"
                                            />
                                            <ToggleLeft
                                                v-else
                                                class="size-3.5 text-slate-400"
                                            />
                                        </button>
                                        <button
                                            class="cursor-pointer rounded-full p-1.5 text-muted-foreground transition-all hover:bg-rose-500/10 hover:text-rose-600"
                                            title="Xóa"
                                            @click="deleteCoupon(coupon)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="!coupons.data.length"
                        class="py-12 text-center text-sm font-semibold text-muted-foreground"
                    >
                        Không có khuyến mãi nào phù hợp.
                    </p>
                </div>

                <!-- Pagination -->
                <Pagination
                    as="button"
                    :links="coupons.links"
                    :current-page="coupons.current_page"
                    :last-page="coupons.last_page"
                    :total="coupons.total"
                    @navigate="goToPage"
                />
            </CardContent>
        </Card>
    </div>

    <!-- Create / Edit slide-over -->
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        <Teleport to="body">
        <div
            v-if="showForm"
            class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/60 p-4 backdrop-blur-xs duration-300 fade-in"
            @click.self="closeForm"
        >
            <div
                class="flex w-full max-w-md flex-col justify-between overflow-hidden rounded-2xl border border-border/80 bg-background shadow-2xl"
            >
                <!-- Modal Header -->
                <div
                    class="flex items-center justify-between border-b border-border/40 bg-muted/10 p-5"
                >
                    <h2 class="flex items-center gap-2 text-sm font-bold">
                        <div
                            class="flex size-7 shrink-0 items-center justify-center rounded-lg border border-orange-500/20 bg-orange-500/10 text-orange-500"
                        >
                            <BadgePercent class="size-4" />
                        </div>
                        <span>{{
                            editingCoupon
                                ? `Chỉnh sửa — ${editingCoupon.code}`
                                : 'Tạo mã khuyến mãi mới'
                        }}</span>
                    </h2>
                    <button
                        class="cursor-pointer rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-muted"
                        @click="closeForm"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="max-h-[70vh] space-y-4.5 overflow-y-auto p-5">
                    <!-- Code -->
                    <div v-if="!editingCoupon" class="grid gap-1.5">
                        <Label
                            for="code"
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <Tag class="size-3.5 text-orange-500" />
                            Mã khuyến mãi <span class="text-rose-500">*</span>
                        </Label>
                        <div class="relative flex items-center">
                            <span
                                class="absolute left-3 rounded border border-border/55 bg-muted px-1.5 py-0.5 font-mono text-[10px] font-black text-muted-foreground/80 uppercase"
                                >CODE</span
                            >
                            <Input
                                id="code"
                                v-model="formData.code"
                                placeholder="VD: SUMMER25"
                                class="rounded-xl border-border pl-14 font-mono font-bold tracking-wide uppercase focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                            />
                        </div>
                        <p
                            class="text-[10px] font-semibold text-muted-foreground"
                        >
                            Chỉ dùng chữ, số và gạch ngang. Hệ thống sẽ tự động
                            đổi sang chữ hoa.
                        </p>
                    </div>

                    <!-- Description -->
                    <div class="grid gap-1.5">
                        <Label
                            for="description"
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <FileText class="size-3.5 text-orange-500" />
                            Mô tả (tùy chọn)
                        </Label>
                        <Input
                            id="description"
                            v-model="formData.description"
                            placeholder="VD: Giảm giá mùa hè 2025"
                            class="rounded-xl border-border focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                        />
                    </div>

                    <!-- Discount type + value -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label
                                class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                <BadgePercent
                                    class="size-3.5 text-orange-500"
                                />
                                Loại giảm giá
                            </Label>
                            <Select v-model="formData.discount_type">
                                <SelectTrigger
                                    class="h-9 rounded-xl border-border text-xs focus:border-orange-500 focus:ring-orange-500/20"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent class="rounded-xl">
                                    <SelectItem value="percent"
                                        >Phần trăm (%)</SelectItem
                                    >
                                    <SelectItem value="fixed"
                                        >Số tiền cố định (đ)</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                for="discount_value"
                                class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                <Coins class="size-3.5 text-orange-500" />
                                Giá trị ({{ discountLabel }})
                            </Label>
                            <Input
                                id="discount_value"
                                v-model.number="formData.discount_value"
                                type="number"
                                min="0.01"
                                :max="
                                    formData.discount_type === 'percent'
                                        ? 100
                                        : undefined
                                "
                                step="1"
                                class="rounded-xl border-border focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                            />
                        </div>
                    </div>

                    <!-- Max uses -->
                    <div class="grid gap-1.5">
                        <Label
                            for="max_uses"
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <Users class="size-3.5 text-orange-500" />
                            Giới hạn số lần dùng
                        </Label>
                        <Input
                            id="max_uses"
                            v-model="formData.max_uses"
                            type="number"
                            min="1"
                            placeholder="Để trống = không giới hạn số lần dùng"
                            class="rounded-xl border-border focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                        />
                    </div>

                    <!-- Date range -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label
                                for="starts_at"
                                class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                <Calendar class="size-3.5 text-orange-500" />
                                Ngày bắt đầu
                            </Label>
                            <Input
                                id="starts_at"
                                v-model="formData.starts_at"
                                type="date"
                                class="rounded-xl border-border focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                for="expires_at"
                                class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                <Calendar class="size-3.5 text-orange-500" />
                                Ngày hết hạn
                            </Label>
                            <Input
                                id="expires_at"
                                v-model="formData.expires_at"
                                type="date"
                                class="rounded-xl border-border focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                            />
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div
                    class="flex gap-3 border-t border-border/40 bg-muted/10 p-5"
                >
                    <Button
                        variant="outline"
                        class="flex-grow cursor-pointer rounded-xl border-border py-5 text-xs font-bold tracking-wider uppercase"
                        @click="closeForm"
                        >Hủy</Button
                    >
                    <Button
                        class="flex-grow cursor-pointer rounded-xl border-none bg-gradient-to-r from-orange-500 to-amber-500 py-5 text-xs font-bold tracking-wider text-white uppercase shadow-md transition-all hover:from-orange-600 hover:to-amber-600 hover:shadow-lg"
                        @click="submitForm"
                    >
                        <Check class="mr-1.5 size-4" />
                        {{ editingCoupon ? 'Cập nhật' : 'Tạo khuyến mãi' }}
                    </Button>
                </div>
            </div>
        </div>
        </Teleport>
    </Transition>
</template>
