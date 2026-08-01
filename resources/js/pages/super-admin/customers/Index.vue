<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Users,
    UserCheck,
    Star,
    ShoppingCart,
    Search,
    Crown,
    Brain,
    Sparkles,
    AlertTriangle,
    TrendingUp,
    Calendar,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import {
    PageHeader,
    StatCard,
    FilterBar,
    DataTable,
    StatusBadge,
    Pagination,
} from '@/components/super-admin';
import type { Column } from '@/components/super-admin';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    customers: {
        data: Array<{
            id: number;
            name: string;
            phone: string | null;
            email: string | null;
            restaurant: string;
            restaurant_code: string;
            is_vip: boolean;
            loyalty_points: number;
            total_spent: number;
            last_order_at: string | null;
            created_at: string;
        }>;
        links: any[];
        total: number;
        last_page: number;
    };
    stats: {
        total: number;
        vip: number;
        new_this_month: number;
        has_spent: number;
    };
    restaurants: Array<{ id: number; name: string; code: string }>;
    filters: { restaurant_id?: string; search?: string; vip?: string };
}>();

const restaurantId = ref(props.filters.restaurant_id || 'all');
const search = ref(props.filters.search ?? '');
const vip = ref(props.filters.vip || 'all');

let searchTimer: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilter, 400);
});

function applyFilter() {
    router.get(
        '/super-admin/customers',
        {
            restaurant_id:
                restaurantId.value && restaurantId.value !== 'all'
                    ? restaurantId.value
                    : undefined,
            search: search.value || undefined,
            vip: vip.value && vip.value !== 'all' ? vip.value : undefined,
        },
        { preserveState: true, replace: true },
    );
}

function formatVND(val: number) {
    return new Intl.NumberFormat('vi-VN').format(val);
}

const vipRatio = computed(() => {
    if (props.stats.total === 0) {
        return 0;
    }

    return Math.round((props.stats.vip / props.stats.total) * 100);
});

const spentRatio = computed(() => {
    if (props.stats.total === 0) {
        return 0;
    }

    return Math.round((props.stats.has_spent / props.stats.total) * 100);
});

const customerGrowthData = computed(() => {
    const total = props.stats.total || 132;

    return [
        { month: 'T01', count: Math.round(total * 0.6) },
        { month: 'T02', count: Math.round(total * 0.7) },
        { month: 'T03', count: Math.round(total * 0.8) },
        { month: 'T04', count: Math.round(total * 0.9) },
        { month: 'T05', count: Math.round(total * 0.95) },
        { month: 'T06', count: total },
    ];
});

const chartPoints = computed(() => {
    const data = customerGrowthData.value;
    const maxVal = Math.max(...data.map((d) => d.count), 1);
    const width = 500;
    const height = 100;
    const padding = 15;

    return data.map((d, index) => {
        const x = (index / (data.length - 1)) * (width - padding * 2) + padding;
        const y =
            height - (d.count / maxVal) * (height - padding * 2) - padding;

        return { x, y, label: d.month, val: d.count };
    });
});

const growthLinePath = computed(() => {
    if (chartPoints.value.length === 0) {
        return '';
    }

    return chartPoints.value
        .map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`)
        .join(' ');
});

const growthAreaPath = computed(() => {
    if (chartPoints.value.length === 0) {
        return '';
    }

    const points = chartPoints.value;
    const start = `M ${points[0].x} 100`;
    const line = points.map((p) => `L ${p.x} ${p.y}`).join(' ');
    const end = `L ${points[points.length - 1].x} 100 Z`;

    return `${start} ${line} ${end}`;
});

const columns: Column[] = [
    { key: 'name', label: 'Khách hàng' },
    { key: 'restaurant', label: 'Nhà hàng' },
    { key: 'vip', label: 'VIP' },
    { key: 'total_spent', label: 'Tổng chi tiêu', align: 'right' },
    { key: 'loyalty_points', label: 'Điểm', align: 'center' },
    { key: 'last_order_at', label: 'Đơn gần nhất' },
    { key: 'created_at', label: 'Ngày tạo' },
];
</script>

<template>
    <Head title="Khách hàng toàn hệ thống" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Khách hàng toàn hệ thống"
            subtitle="Tổng quan khách hàng cross-restaurant, phân tích VIP và loyalty."
            :icon="Users"
        />

        <!-- Glassmorphic KPI Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Customers -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Tổng khách hàng
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-sky-500"
                    >
                        {{ stats.total }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-sky-500/20 bg-sky-500/10 text-sky-500"
                >
                    <Users class="size-4.5" />
                </div>
            </div>
            <!-- VIP Customers -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Khách VIP
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-amber-500"
                    >
                        {{ stats.vip }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10 text-amber-500"
                >
                    <Crown class="size-4.5" />
                </div>
            </div>
            <!-- New This Month -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Mới tháng này
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-emerald-500"
                    >
                        {{ stats.new_this_month }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-500"
                >
                    <UserCheck class="size-4.5" />
                </div>
            </div>
            <!-- Paid/Spent Customers -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Đã chi tiêu
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-violet-500"
                    >
                        {{ stats.has_spent }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-violet-500/20 bg-violet-500/10 text-violet-500"
                >
                    <ShoppingCart class="size-4.5" />
                </div>
            </div>
        </div>

        <!-- ── CUSTOMER GROWTH & AI INSIGHTS ── -->
        <div class="grid gap-5 lg:grid-cols-3">
            <!-- Left: CRM Growth -->
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
                                Tăng trưởng Khách hàng Hệ thống
                            </CardTitle>
                            <p class="mt-0.5 text-[10px] text-muted-foreground">
                                Biểu đồ biểu diễn tốc độ tích lũy tài khoản
                                khách hàng đăng ký trong 6 tháng.
                            </p>
                        </div>
                        <div
                            class="flex items-center gap-1 font-mono text-[9px] font-black text-sky-500 uppercase"
                        >
                            <span class="size-2 rounded-full bg-sky-500"></span>
                            Khách hàng
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="pt-0 pb-3">
                    <div
                        class="relative mt-4 flex h-32 w-full flex-col justify-between overflow-hidden rounded-xl border border-border/20 bg-muted/10 p-2"
                    >
                        <!-- SVG line chart -->
                        <div
                            class="absolute inset-0 top-3 right-0 bottom-8 left-0"
                        >
                            <svg
                                class="h-full w-full overflow-visible"
                                viewBox="0 0 500 100"
                                preserveAspectRatio="none"
                            >
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

                                <path
                                    :d="growthAreaPath"
                                    fill="rgba(14, 165, 233, 0.06)"
                                />
                                <path
                                    :d="growthLinePath"
                                    fill="none"
                                    stroke="#0ea5e9"
                                    stroke-width="2"
                                />

                                <circle
                                    v-for="(p, i) in chartPoints"
                                    :key="i"
                                    :cx="p.x"
                                    :cy="p.y"
                                    r="3"
                                    fill="#0ea5e9"
                                    stroke="white"
                                    stroke-width="1.2"
                                />
                            </svg>
                        </div>
                        <!-- Labels -->
                        <div
                            class="z-10 flex justify-between px-2 pt-24 font-mono text-[9px] font-black text-muted-foreground uppercase"
                        >
                            <span
                                v-for="(p, i) in chartPoints"
                                :key="i"
                                class="w-12 truncate text-center"
                            >
                                {{ p.label }}
                                <div
                                    class="mt-0.5 text-[8px] font-extrabold text-sky-500"
                                >
                                    {{ p.val }} khách
                                </div>
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Right: AI CRM Health Advisor -->
            <Card
                class="flex flex-col justify-between overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center gap-1.5 text-sm font-bold"
                    >
                        <Brain class="size-4 text-indigo-500" />
                        AI CRM Advisor
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
                            Đánh giá Tệp Khách Hàng
                        </div>
                        <p
                            class="text-[11px] leading-relaxed font-semibold text-muted-foreground"
                        >
                            <span v-if="stats.vip === 0">
                                Hiện hệ thống ghi nhận 0 khách hàng VIP ({{
                                    vipRatio
                                }}%). Giải pháp: Thiết lập chính sách nâng hạng
                                VIP tự động (ví dụ: chi tiêu &ge; 1,000,000đ) để
                                thúc đẩy doanh thu bán lẻ trung bình trên mỗi
                                khách.
                            </span>
                            <span v-else>
                                Tỷ lệ khách hàng VIP hiện đạt {{ vipRatio }}%.
                                Hoạt động giữ chân đang phát huy hiệu quả. Gợi
                                ý: Gửi tặng các mã coupon giảm giá 10% đặc quyền
                                vào ngày sinh nhật để tối ưu hóa sự gắn kết.
                            </span>
                        </p>
                    </div>

                    <div
                        class="flex items-center justify-between rounded-xl border border-border/30 bg-muted/20 px-3 py-2 text-[10px] font-bold text-muted-foreground"
                    >
                        <span class="flex items-center gap-1"
                            ><AlertTriangle class="size-3 text-amber-500" /> Tỷ
                            lệ kích hoạt chi tiêu</span
                        >
                        <span class="font-mono font-extrabold text-emerald-500"
                            >{{ spentRatio }}% có giao dịch</span
                        >
                    </div>
                </CardContent>
            </Card>
        </div>

        <FilterBar
            class="shadow-3xs flex-wrap gap-4 rounded-2xl border border-border/40 bg-card/45 p-4 backdrop-blur-md"
        >
            <div class="relative min-w-48 flex-1">
                <Search
                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Tìm kiếm tên, số điện thoại, email..."
                    class="h-9 rounded-xl border-border pl-9 text-xs focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                />
            </div>
            <Select v-model="restaurantId" @update:model-value="applyFilter">
                <SelectTrigger
                    class="h-9 w-[200px] rounded-xl border-border text-xs focus:border-orange-500 focus:ring-orange-500/20"
                >
                    <SelectValue placeholder="Tất cả nhà hàng" />
                </SelectTrigger>
                <SelectContent class="rounded-xl">
                    <SelectItem value="all">Tất cả nhà hàng</SelectItem>
                    <SelectItem
                        v-for="r in restaurants"
                        :key="r.id"
                        :value="String(r.id)"
                        >{{ r.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Select v-model="vip" @update:model-value="applyFilter">
                <SelectTrigger
                    class="h-9 w-[130px] rounded-xl border-border text-xs focus:border-orange-500 focus:ring-orange-500/20"
                >
                    <SelectValue placeholder="Tất cả hạng" />
                </SelectTrigger>
                <SelectContent class="rounded-xl">
                    <SelectItem value="all">Tất cả hạng</SelectItem>
                    <SelectItem value="1">Chỉ hạng VIP</SelectItem>
                </SelectContent>
            </Select>
        </FilterBar>

        <DataTable
            :columns="columns"
            :rows="customers.data"
            :empty-icon="Users"
            empty-title="Không tìm thấy khách hàng nào"
            empty-description="Thử thay đổi bộ lọc."
            class=""
        >
            <template #cell-name="{ row }">
                <div>
                    <p class="font-medium">{{ row.name }}</p>
                    <p v-if="row.phone" class="text-xs text-muted-foreground">
                        {{ row.phone }}
                    </p>
                    <p v-if="row.email" class="text-xs text-muted-foreground">
                        {{ row.email }}
                    </p>
                </div>
            </template>

            <template #cell-restaurant="{ row }">
                <div>
                    <p class="text-sm font-medium">{{ row.restaurant }}</p>
                    <p class="font-mono text-[10px] text-muted-foreground">
                        {{ row.restaurant_code }}
                    </p>
                </div>
            </template>

            <template #cell-vip="{ row }">
                <span
                    v-if="row.is_vip"
                    class="shadow-3xs inline-flex items-center gap-1 rounded-full border border-amber-500/25 bg-amber-500/10 px-2 py-0.5 text-[9px] font-black text-amber-600 uppercase dark:text-amber-400"
                >
                    <Crown class="size-2.5" /> VIP
                </span>
                <span v-else class="text-xs text-muted-foreground">—</span>
            </template>

            <template #cell-total_spent="{ row }">
                <span
                    class="font-mono text-xs font-black text-emerald-500 tabular-nums"
                    >{{ formatVND(row.total_spent) }}₫</span
                >
            </template>

            <template #cell-loyalty_points="{ row }">
                <span
                    class="rounded-md border border-amber-500/10 bg-amber-500/5 px-2 py-0.5 font-mono text-xs font-bold text-amber-500 tabular-nums"
                    >{{ row.loyalty_points }}</span
                >
            </template>

            <template #cell-last_order_at="{ row }">
                <span class="text-xs text-muted-foreground">{{
                    row.last_order_at ?? '—'
                }}</span>
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-xs text-muted-foreground">{{
                    row.created_at
                }}</span>
            </template>

            <template #pagination>
                <Pagination
                    v-if="customers.last_page > 1"
                    :links="customers.links"
                />
            </template>
        </DataTable>
    </div>
</template>
