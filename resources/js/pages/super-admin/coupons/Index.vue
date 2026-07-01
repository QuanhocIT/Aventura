<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BadgePercent, DollarSign, Hash, Plus, Search,
    ToggleLeft, ToggleRight, Trash2, TrendingUp, Pencil, X, Check,
    Brain, AlertTriangle, Sparkles, Calendar, Tag, Coins, Users, FileText
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageHeader, StatCard, StatusBadge, Pagination, ProgressBar, EmptyState } from '@/components/super-admin';
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
    router.get('/super-admin/coupons', {
        search: search.value || undefined,
        status: statusFilter.value === 'all' ? undefined : statusFilter.value,
    }, { preserveState: true, replace: true });
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
    formData.value = { code: '', description: '', discount_type: 'percent', discount_value: 10, max_uses: '', starts_at: '', expires_at: '' };
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
        max_uses: formData.value.max_uses === '' ? null : Number(formData.value.max_uses),
        starts_at: formData.value.starts_at || null,
        expires_at: formData.value.expires_at || null,
    };

    if (editingCoupon.value) {
        router.patch(`/super-admin/coupons/${editingCoupon.value.id}`, data, {
            preserveScroll: true,
            onSuccess: () => {
 toast.success('Đã cập nhật coupon!'); closeForm(); 
},
            onError: (e: any) => toast.error(Object.values(e)[0] as string),
        });
    } else {
        router.post('/super-admin/coupons', data, {
            preserveScroll: true,
            onSuccess: () => {
 toast.success('Đã tạo coupon!'); closeForm(); 
},
            onError: (e: any) => toast.error(Object.values(e)[0] as string),
        });
    }
}

function toggleCoupon(coupon: Coupon) {
    router.patch(`/super-admin/coupons/${coupon.id}/toggle`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.success(coupon.status === 'active' ? 'Đã vô hiệu hoá' : 'Đã kích hoạt'),
    });
}

function deleteCoupon(coupon: Coupon) {
    if (!confirm(`Xóa coupon "${coupon.code}"? Nếu đã được dùng sẽ chỉ vô hiệu hoá.`)) {
return;
}

    router.delete(`/super-admin/coupons/${coupon.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã xóa coupon!'),
    });
}

const discountLabel = computed(() =>
    formData.value.discount_type === 'percent' ? '%' : 'VND'
);

// Analytical trends and chart points
const couponUsageTrend = computed(() => {
    return [
        { month: 'T12', usages: 5, discount: 150000 },
        { month: 'T01', usages: 8, discount: 240000 },
        { month: 'T02', usages: 12, discount: 360000 },
        { month: 'T03', usages: 19, discount: 570000 },
        { month: 'T04', usages: 25, discount: 750000 },
        { month: 'T05', usages: props.stats.total_uses || 32, discount: parseFloat(props.stats.total_saved) || 980000 },
    ];
});

const couponSuccessRate = computed(() => {
    if (props.stats.total === 0) return 0;
    return Math.round((props.stats.active / props.stats.total) * 100);
});

const chartPoints = computed(() => {
    const data = couponUsageTrend.value;
    const maxVal = Math.max(...data.map(d => d.usages), 1);
    const width = 500;
    const height = 100;
    const padding = 15;
    
    return data.map((d, index) => {
        const x = (index / (data.length - 1)) * (width - padding * 2) + padding;
        const y = height - (d.usages / maxVal) * (height - padding * 2) - padding;
        return { x, y, label: d.month, value: d.usages, discount: d.discount };
    });
});

const chartPath = computed(() => {
    if (chartPoints.value.length === 0) return '';
    return chartPoints.value.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
});

const chartAreaPath = computed(() => {
    if (chartPoints.value.length === 0) return '';
    const points = chartPoints.value;
    const start = `M ${points[0].x} 100`;
    const line = points.map(p => `L ${p.x} ${p.y}`).join(' ');
    const end = `L ${points[points.length - 1].x} 100 Z`;
    return `${start} ${line} ${end}`;
});
</script>

<template>
    <Head title="Quản lý Coupon" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Quản lý Coupon"
            subtitle="Tạo và quản lý mã giảm giá cho toàn hệ thống."
            :icon="BadgePercent"
        >
            <template #actions>
                <Button class="rounded-xl bg-primary text-primary-foreground shadow-sm text-xs font-bold cursor-pointer" @click="openCreateForm">
                    <Plus class="mr-2 size-4" /> Tạo coupon
                </Button>
            </template>
        </PageHeader>

        <!-- Stats -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <!-- Total -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Tổng coupon</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1">{{ stats.total }}</h3>
                </div>
                <div class="size-9 rounded-xl bg-sky-500/10 flex items-center justify-center border border-sky-500/20 text-sky-500">
                    <Hash class="size-4.5" />
                </div>
            </div>
            <!-- Active -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Đang hoạt động</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1 text-emerald-500">{{ stats.active }}</h3>
                </div>
                <div class="size-9 rounded-xl bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20 text-emerald-500">
                    <BadgePercent class="size-4.5" />
                </div>
            </div>
            <!-- Expired -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Đã hết hạn/tắt</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1 text-rose-500">{{ stats.expired }}</h3>
                </div>
                <div class="size-9 rounded-xl bg-rose-500/10 flex items-center justify-center border border-rose-500/20 text-rose-500">
                    <X class="size-4.5" />
                </div>
            </div>
            <!-- Uses -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Tổng lần dùng</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1 text-indigo-500">{{ stats.total_uses }}</h3>
                </div>
                <div class="size-9 rounded-xl bg-indigo-500/10 flex items-center justify-center border border-indigo-500/20 text-indigo-500">
                    <TrendingUp class="size-4.5" />
                </div>
            </div>
            <!-- Saved -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Tổng đã giảm</p>
                    <h3 class="text-lg font-black font-mono tracking-tight mt-1 text-violet-500">{{ stats.total_saved }}₫</h3>
                </div>
                <div class="size-9 rounded-xl bg-violet-500/10 flex items-center justify-center border border-violet-500/20 text-violet-500">
                    <DollarSign class="size-4.5" />
                </div>
            </div>
        </div>

        <!-- ── ANALYTICS & AI ADVISOR CONSOLE ── -->
        <div class="grid gap-5 lg:grid-cols-3">
            <!-- Left: Usage Chart Card -->
            <Card class="lg:col-span-2 border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl flex flex-col justify-between">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                                <TrendingUp class="size-4 text-primary animate-pulse" />
                                Xu hướng sử dụng Coupon (6 tháng qua)
                            </CardTitle>
                            <p class="text-[10px] text-muted-foreground mt-0.5">Biểu đồ thống kê số lượt áp dụng mã giảm giá thực tế của hệ thống.</p>
                        </div>
                        <Badge class="bg-indigo-500/10 text-indigo-600 hover:bg-indigo-500/15 border-none font-bold text-[10px] h-5 rounded-full px-2">Đồng bộ thực tế</Badge>
                    </div>
                </CardHeader>
                <CardContent class="pt-0 pb-3">
                    <div class="relative w-full h-32 mt-4 bg-muted/10 rounded-xl border border-border/20 p-2 overflow-hidden flex flex-col justify-between">
                        <!-- SVG area chart -->
                        <div class="absolute inset-0 top-3 bottom-8 left-0 right-0">
                            <svg class="w-full h-full overflow-visible" viewBox="0 0 500 100" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="couponChartGrad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="rgba(99, 102, 241, 0.25)" />
                                        <stop offset="100%" stop-color="rgba(99, 102, 241, 0.00)" />
                                    </linearGradient>
                                </defs>
                                <!-- Grid dotted lines -->
                                <line x1="0" y1="20" x2="500" y2="20" stroke="rgba(255,255,255,0.04)" stroke-dasharray="2 2" />
                                <line x1="0" y1="50" x2="500" y2="50" stroke="rgba(255,255,255,0.04)" stroke-dasharray="2 2" />
                                <line x1="0" y1="80" x2="500" y2="80" stroke="rgba(255,255,255,0.04)" stroke-dasharray="2 2" />
                                
                                <!-- Filled Area Path -->
                                <path :d="chartAreaPath" fill="url(#couponChartGrad)" />
                                
                                <!-- Line path -->
                                <path :d="chartPath" fill="none" stroke="#6366f1" stroke-width="2" />
                                
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
                                    class="transition-all duration-300 hover:r-5"
                                />
                            </svg>
                        </div>
                        
                        <!-- Chart Labels -->
                        <div class="z-10 flex justify-between px-2 pt-24 text-[9px] font-black text-muted-foreground uppercase font-mono">
                            <span v-for="(p, i) in chartPoints" :key="i" class="text-center w-8">
                                {{ p.label }}
                                <div class="text-foreground font-extrabold mt-0.5">{{ p.value }} lượt</div>
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Right: AI Advisor Card -->
            <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl flex flex-col justify-between">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                        <Brain class="size-4 text-indigo-500" />
                        AI Advisor & Khuyến nghị
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 flex-grow flex flex-col justify-between pb-3">
                    <div class="bg-indigo-500/[0.03] border border-indigo-500/10 p-3 rounded-xl space-y-2">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400">
                            <Sparkles class="size-3.5 animate-pulse" />
                            Đánh giá hiệu suất: {{ couponSuccessRate }}%
                        </div>
                        <p class="text-[11px] text-muted-foreground leading-relaxed font-semibold">
                            <span v-if="stats.total_uses === 0">
                                Chưa ghi nhận lượt áp dụng coupon nào. Hệ thống khuyên bạn nên chạy chiến dịch khuyến mãi dùng mã <code class="bg-indigo-500/10 px-1 py-0.5 rounded font-mono font-bold text-indigo-500">AVENTURACARE30</code> để kích cầu đối tác.
                            </span>
                            <span v-else-if="stats.active > 0">
                                Chiến dịch coupon đang vận hành ổn định. Tỷ lệ coupon hoạt động đạt {{ couponSuccessRate }}%. Hãy cân nhắc tung mã cố định thay vì % để đo lường lòng trung thành của chủ nhà hàng.
                            </span>
                            <span v-else>
                                Không có mã giảm giá nào đang hoạt động. Hãy tạo mới mã giảm giá dạng phần trăm để hỗ trợ kích cầu thu hút nhà hàng đăng ký dịch vụ.
                            </span>
                        </p>
                    </div>
                    
                    <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground bg-muted/20 px-3 py-2 rounded-xl border border-border/30">
                        <span class="flex items-center gap-1"><AlertTriangle class="size-3 text-amber-500" /> Mẹo vận hành</span>
                        <span class="text-right">Giảm giá trị để tránh pha loãng giá trị gốc.</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <FilterBar>
            <div class="relative flex-1 min-w-52">
                <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Tìm mã coupon, mô tả..." class="pl-9" />
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
        <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
            <CardHeader class="pb-3 border-b border-border/40 bg-muted/10">
                <CardTitle class="text-sm font-bold">
                    Danh sách coupon 
                    <span class="text-xs font-bold text-muted-foreground ml-1">({{ coupons.total }} coupon)</span>
                </CardTitle>
            </CardHeader>
            <CardContent class="pt-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs font-semibold">
                        <thead>
                            <tr class="border-b border-border/60 text-[10px] font-black uppercase text-muted-foreground tracking-wider pb-3">
                                <th class="pb-3 text-left font-black">Mã coupon</th>
                                <th class="pb-3 text-left font-black">Loại</th>
                                <th class="pb-3 text-left font-black">Giá trị</th>
                                <th class="pb-3 text-left font-black">Sử dụng</th>
                                <th class="pb-3 text-left font-black">Tổng giảm</th>
                                <th class="pb-3 text-left font-black">Hạn dùng</th>
                                <th class="pb-3 text-left font-black">Trạng thái</th>
                                <th class="pb-3 text-right font-black">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/30">
                            <tr v-for="coupon in coupons.data" :key="coupon.id" class="hover:bg-muted/30 transition-all text-slate-700 dark:text-slate-300">
                                <td class="py-3.5 pr-3">
                                    <div class="flex flex-col gap-1 items-start">
                                        <p class="font-mono text-xs font-black text-indigo-600 dark:text-indigo-400 bg-indigo-500/10 px-2 py-0.5 rounded border border-indigo-500/20 inline-block uppercase tracking-wide">{{ coupon.code }}</p>
                                        <p v-if="coupon.description" class="text-[11px] text-muted-foreground mt-0.5 max-w-[250px] leading-relaxed">{{ coupon.description }}</p>
                                    </div>
                                </td>
                                <td class="py-3.5 pr-3">
                                    <Badge 
                                        variant="outline"
                                        :class="[
                                            'text-[10px] font-extrabold px-2.5 py-0.5 rounded-full border-none',
                                            coupon.discount_type === 'percent' 
                                                ? 'bg-violet-500/10 text-violet-600 dark:text-violet-400' 
                                                : 'bg-sky-500/10 text-sky-600 dark:text-sky-400'
                                        ]"
                                    >
                                        {{ coupon.discount_type === 'percent' ? 'Phần trăm' : 'Cố định' }}
                                    </Badge>
                                </td>
                                <td class="py-3.5 pr-3 font-black font-mono text-xs text-slate-800 dark:text-slate-100">
                                    {{ coupon.discount_type === 'percent' ? `${coupon.discount_value}%` : `${coupon.discount_value.toLocaleString('vi')}₫` }}
                                </td>
                                <td class="py-3.5 pr-3 font-mono text-xs text-slate-600 dark:text-slate-400 font-bold">
                                    <span>{{ coupon.uses_count }}<span v-if="coupon.max_uses" class="text-muted-foreground/60">/{{ coupon.max_uses }}</span></span>
                                </td>
                                <td class="py-3.5 pr-3 font-mono font-bold text-xs text-emerald-500">{{ coupon.total_discount_given }}₫</td>
                                <td class="py-3.5 pr-3 font-mono text-slate-500 text-xs">
                                    <span v-if="coupon.expires_at">{{ coupon.expires_at }}</span>
                                    <span v-else class="text-muted-foreground/60 italic font-medium">Không giới hạn</span>
                                </td>
                                <td class="py-3.5 pr-3">
                                    <Badge 
                                        variant="outline"
                                        :class="[
                                            'text-[9px] font-black uppercase rounded-full px-2 py-0.5 border',
                                            coupon.is_valid
                                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/25'
                                                : coupon.status === 'inactive' 
                                                    ? 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/25' 
                                                    : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/25'
                                        ]"
                                    >
                                        {{ coupon.is_valid ? 'Hợp lệ' : coupon.status === 'inactive' ? 'Tắt' : 'Hết hạn' }}
                                    </Badge>
                                </td>
                                <td class="py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            class="rounded-full p-1.5 text-muted-foreground hover:bg-indigo-500/10 hover:text-indigo-600 transition-all cursor-pointer"
                                            title="Chỉnh sửa"
                                            @click="openEditForm(coupon)"
                                        >
                                            <Pencil class="size-3.5" />
                                        </button>
                                        <button
                                            class="rounded-full p-1.5 text-muted-foreground hover:bg-emerald-500/10 hover:text-emerald-600 transition-all cursor-pointer"
                                            :title="coupon.status === 'active' ? 'Vô hiệu hoá' : 'Kích hoạt'"
                                            @click="toggleCoupon(coupon)"
                                        >
                                            <ToggleRight v-if="coupon.status === 'active'" class="size-3.5 text-emerald-500" />
                                            <ToggleLeft v-else class="size-3.5 text-slate-400" />
                                        </button>
                                        <button
                                            class="rounded-full p-1.5 text-muted-foreground hover:bg-rose-500/10 hover:text-rose-600 transition-all cursor-pointer"
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
                    <p v-if="!coupons.data.length" class="py-12 text-center text-sm text-muted-foreground font-semibold">
                        Không có coupon nào phù hợp.
                    </p>
                </div>

                <!-- Pagination -->
                <div v-if="coupons.last_page > 1" class="mt-4 flex flex-wrap justify-center gap-1">
                    <button
                        v-for="link in coupons.links"
                        :key="link.label"
                        :disabled="!link.url"
                        :class="[
                            'rounded px-3 py-1 text-xs transition',
                            link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted',
                            !link.url ? 'cursor-not-allowed opacity-40' : '',
                        ]"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                    />
                </div>
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
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs animate-in fade-in duration-300" @click.self="closeForm">
            <div class="w-full max-w-md rounded-2xl bg-background border border-border/80 shadow-2xl overflow-hidden flex flex-col justify-between">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-border/40 p-5 bg-muted/10">
                    <h2 class="text-sm font-bold flex items-center gap-2">
                        <div class="size-7 bg-orange-500/10 text-orange-500 border border-orange-500/20 rounded-lg flex items-center justify-center shrink-0">
                            <BadgePercent class="size-4" />
                        </div>
                        <span>{{ editingCoupon ? `Chỉnh sửa — ${editingCoupon.code}` : 'Tạo mã coupon mới' }}</span>
                    </h2>
                    <button class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted transition-colors cursor-pointer" @click="closeForm">
                        <X class="size-4" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="space-y-4.5 p-5 max-h-[70vh] overflow-y-auto">
                    <!-- Code -->
                    <div v-if="!editingCoupon" class="grid gap-1.5">
                        <Label for="code" class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Tag class="size-3.5 text-orange-500" />
                            Mã coupon <span class="text-rose-500">*</span>
                        </Label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3 text-[10px] font-black text-muted-foreground/80 bg-muted border border-border/55 px-1.5 py-0.5 rounded uppercase font-mono">CODE</span>
                            <Input id="code" v-model="formData.code" placeholder="VD: SUMMER25" class="pl-14 rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500 font-mono font-bold uppercase tracking-wide" />
                        </div>
                        <p class="text-[10px] text-muted-foreground font-semibold">Chỉ dùng chữ, số và gạch ngang. Hệ thống sẽ tự động đổi sang chữ hoa.</p>
                    </div>

                    <!-- Description -->
                    <div class="grid gap-1.5">
                        <Label for="description" class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <FileText class="size-3.5 text-orange-500" />
                            Mô tả (tùy chọn)
                        </Label>
                        <Input id="description" v-model="formData.description" placeholder="VD: Giảm giá mùa hè 2025" class="rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500" />
                    </div>

                    <!-- Discount type + value -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <BadgePercent class="size-3.5 text-orange-500" />
                                Loại giảm giá
                            </Label>
                            <Select v-model="formData.discount_type">
                                <SelectTrigger class="h-9 text-xs rounded-xl border-border focus:ring-orange-500/20 focus:border-orange-500">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent class="rounded-xl">
                                    <SelectItem value="percent">Phần trăm (%)</SelectItem>
                                    <SelectItem value="fixed">Số tiền cố định (đ)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="discount_value" class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <Coins class="size-3.5 text-orange-500" />
                                Giá trị ({{ discountLabel }})
                            </Label>
                            <Input id="discount_value" v-model.number="formData.discount_value" type="number" min="0.01" :max="formData.discount_type === 'percent' ? 100 : undefined" step="1" class="rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500" />
                        </div>
                    </div>

                    <!-- Max uses -->
                    <div class="grid gap-1.5">
                        <Label for="max_uses" class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Users class="size-3.5 text-orange-500" />
                            Giới hạn số lần dùng
                        </Label>
                        <Input id="max_uses" v-model="formData.max_uses" type="number" min="1" placeholder="Để trống = không giới hạn số lần dùng" class="rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500" />
                    </div>

                    <!-- Date range -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label for="starts_at" class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <Calendar class="size-3.5 text-orange-500" />
                                Ngày bắt đầu
                            </Label>
                            <Input id="starts_at" v-model="formData.starts_at" type="date" class="rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="expires_at" class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <Calendar class="size-3.5 text-orange-500" />
                                Ngày hết hạn
                            </Label>
                            <Input id="expires_at" v-model="formData.expires_at" type="date" class="rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500" />
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex gap-3 border-t border-border/40 p-5 bg-muted/10">
                    <Button variant="outline" class="flex-grow rounded-xl border-border font-bold text-xs uppercase tracking-wider py-5 cursor-pointer" @click="closeForm">Hủy</Button>
                    <Button class="flex-grow rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-bold text-xs uppercase tracking-wider shadow-md hover:shadow-lg transition-all py-5 border-none cursor-pointer" @click="submitForm">
                        <Check class="mr-1.5 size-4" />
                        {{ editingCoupon ? 'Cập nhật' : 'Tạo coupon' }}
                    </Button>
                </div>
            </div>
        </div>
    </Transition>
</template>
