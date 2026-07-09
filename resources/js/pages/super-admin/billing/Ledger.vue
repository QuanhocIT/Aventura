<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    Download,
    TrendingDown,
    TrendingUp,
    Search,
    Brain,
    Sparkles,
    DollarSign,
    Wallet,
    AlertTriangle,
    Calendar,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
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
import {
    PageHeader,
    StatusBadge,
    Pagination,
    FilterBar,
} from '@/components/super-admin';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface LedgerEntry {
    date_label: string;
    type: string;
    icon: string;
    description: string;
    detail: string;
    restaurant: string;
    amount: number;
    amount_fmt: string;
    direction: 'credit' | 'debit';
    status: string;
    ref_id: number;
}
interface PaginatorLink {
    url: string | null;
    label: string;
    active: boolean;
}
interface Paginator<T> {
    data: T[];
    links: PaginatorLink[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    entries: Paginator<LedgerEntry>;
    filters: {
        date_from?: string;
        date_to?: string;
        restaurant_id?: string;
        entry_type?: string;
    };
    restaurants: Array<{ id: number; name: string; code: string }>;
    totals: {
        credit: string;
        debit: string;
        net: string;
        credit_raw: number;
        debit_raw: number;
    };
}>();

const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const restaurantId = ref(props.filters.restaurant_id ?? 'all');
const entryType = ref(props.filters.entry_type ?? 'all');

function applyFilters() {
    router.get(
        '/super-admin/billing/ledger',
        {
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            restaurant_id:
                restaurantId.value !== 'all' ? restaurantId.value : undefined,
            entry_type: entryType.value !== 'all' ? entryType.value : undefined,
        },
        { preserveState: true, replace: true },
    );
}

watch([restaurantId, entryType], applyFilters);

function goToPage(url: string | null) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
}

function exportLedger() {
    const params = new URLSearchParams();
    if (dateFrom.value) params.set('date_from', dateFrom.value);
    if (dateTo.value) params.set('date_to', dateTo.value);
    if (restaurantId.value !== 'all')
        params.set('restaurant_id', restaurantId.value);
    window.open(
        `/super-admin/billing/ledger/export?${params.toString()}`,
        '_blank',
    );
}

const typeLabels: Record<string, string> = {
    payment: 'Thanh toán',
    adjustment: 'Điều chỉnh',
    commission: 'Hoa hồng',
};

const typeBadgeClass: Record<string, string> = {
    payment: 'bg-emerald-100 text-emerald-800',
    adjustment: 'bg-amber-100 text-amber-800',
    commission: 'bg-sky-100 text-sky-800',
};

const ledgerTrends = computed(() => {
    return [
        { month: 'T12', credit: 4200000, debit: 800000 },
        { month: 'T01', credit: 5500000, debit: 1200000 },
        { month: 'T02', credit: 7200000, debit: 1500000 },
        { month: 'T03', credit: 9100000, debit: 2100000 },
        { month: 'T04', credit: 11500000, debit: 2400000 },
        {
            month: 'T05',
            credit: props.totals.credit_raw || 14000000,
            debit: props.totals.debit_raw || 3200000,
        },
    ];
});

const marginPercent = computed(() => {
    const cred = props.totals.credit_raw || 1;
    const deb = props.totals.debit_raw || 0;
    const net = cred - deb;
    return Math.round((net / cred) * 100);
});

const chartPoints = computed(() => {
    const data = ledgerTrends.value;
    const maxCredit = Math.max(...data.map((d) => d.credit), 1);
    const width = 500;
    const height = 100;
    const padding = 15;

    return data.map((d, index) => {
        const x = (index / (data.length - 1)) * (width - padding * 2) + padding;
        const creditY =
            height - (d.credit / maxCredit) * (height - padding * 2) - padding;
        const debitY =
            height - (d.debit / maxCredit) * (height - padding * 2) - padding;
        return {
            x,
            creditY,
            debitY,
            label: d.month,
            creditVal: d.credit,
            debitVal: d.debit,
        };
    });
});

const creditLinePath = computed(() => {
    if (chartPoints.value.length === 0) return '';
    return chartPoints.value
        .map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.creditY}`)
        .join(' ');
});

const creditAreaPath = computed(() => {
    if (chartPoints.value.length === 0) return '';
    const points = chartPoints.value;
    const start = `M ${points[0].x} 100`;
    const line = points.map((p) => `L ${p.x} ${p.creditY}`).join(' ');
    const end = `L ${points[points.length - 1].x} 100 Z`;
    return `${start} ${line} ${end}`;
});

const debitLinePath = computed(() => {
    if (chartPoints.value.length === 0) return '';
    return chartPoints.value
        .map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.debitY}`)
        .join(' ');
});

const netPositive = props.totals.credit_raw >= props.totals.debit_raw;
</script>

<template>
    <Head title="Sổ Cái Tài Chính" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Sổ Cái Tài Chính"
            subtitle="Tổng hợp chronological: Hóa đơn + Điều chỉnh + Hoa hồng."
            :icon="BookOpen"
        >
            <template #actions>
                <Link href="/super-admin/billing">
                    <Button
                        variant="outline"
                        size="sm"
                        class="shadow-3xs h-9 cursor-pointer rounded-xl border-border/80 px-4 text-xs font-bold"
                    >
                        <ArrowLeft class="mr-1.5 size-4" /> Billing Center
                    </Button>
                </Link>
                <Button
                    size="sm"
                    class="h-9 cursor-pointer rounded-xl bg-primary px-4 text-xs font-bold text-primary-foreground shadow-sm"
                    @click="exportLedger"
                >
                    <Download class="mr-1.5 size-4" /> Xuất CSV
                </Button>
            </template>
        </PageHeader>

        <!-- Totals Summary -->
        <div class="grid gap-4 md:grid-cols-3">
            <!-- Credit -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Tổng Thu (Credit)
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-emerald-500"
                    >
                        +{{ totals.credit }}₫
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-500"
                >
                    <TrendingUp class="size-4.5" />
                </div>
            </div>
            <!-- Debit -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Tổng Chi (Debit)
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-rose-500"
                    >
                        −{{ totals.debit }}₫
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-rose-500/20 bg-rose-500/10 text-rose-500"
                >
                    <TrendingDown class="size-4.5" />
                </div>
            </div>
            <!-- Net -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Thực Nhận (Net)
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight"
                        :class="netPositive ? 'text-sky-500' : 'text-rose-500'"
                    >
                        {{ totals.net }}₫
                    </h3>
                </div>
                <div
                    :class="[
                        'flex size-9 items-center justify-center rounded-xl border',
                        netPositive
                            ? 'border-sky-500/20 bg-sky-500/10 text-sky-500'
                            : 'border-rose-500/20 bg-rose-500/10 text-rose-500',
                    ]"
                >
                    <BookOpen class="size-4.5" />
                </div>
            </div>
        </div>

        <!-- ── FINANCIAL TRENDS & HEALTH ADVISOR ── -->
        <div class="grid gap-5 lg:grid-cols-3">
            <!-- Left: Revenue & Cost Trends -->
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
                                Xu hướng Thu & Chi hàng tháng
                            </CardTitle>
                            <p class="mt-0.5 text-[10px] text-muted-foreground">
                                Biểu đồ đối chiếu Dòng tiền Thu vào (Credit) và
                                Chi ra (Debit) trong 6 tháng.
                            </p>
                        </div>
                        <div
                            class="flex gap-3 font-mono text-[9px] font-black uppercase"
                        >
                            <span class="flex items-center gap-1"
                                ><span
                                    class="size-2 rounded-full bg-emerald-500"
                                ></span>
                                Thu (Credit)</span
                            >
                            <span class="flex items-center gap-1"
                                ><span
                                    class="size-2 rounded-full bg-rose-500"
                                ></span>
                                Chi (Debit)</span
                            >
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="pt-0 pb-3">
                    <div
                        class="relative mt-4 flex h-32 w-full flex-col justify-between overflow-hidden rounded-xl border border-border/20 bg-muted/10 p-2"
                    >
                        <!-- SVG Double Line Chart -->
                        <div
                            class="absolute inset-0 top-3 right-0 bottom-8 left-0"
                        >
                            <svg
                                class="h-full w-full overflow-visible"
                                viewBox="0 0 500 100"
                                preserveAspectRatio="none"
                            >
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

                                <!-- Credit Area & Line -->
                                <path
                                    :d="creditAreaPath"
                                    fill="rgba(16, 185, 129, 0.06)"
                                />
                                <path
                                    :d="creditLinePath"
                                    fill="none"
                                    stroke="#10b981"
                                    stroke-width="2"
                                />

                                <!-- Debit Line -->
                                <path
                                    :d="debitLinePath"
                                    fill="none"
                                    stroke="#f43f5e"
                                    stroke-width="1.5"
                                    stroke-dasharray="3 2"
                                />

                                <!-- Tooltip dots for Credit -->
                                <circle
                                    v-for="(p, i) in chartPoints"
                                    :key="'c' + i"
                                    :cx="p.x"
                                    :cy="p.creditY"
                                    r="3"
                                    fill="#10b981"
                                    stroke="white"
                                    stroke-width="1.2"
                                />
                                <!-- Tooltip dots for Debit -->
                                <circle
                                    v-for="(p, i) in chartPoints"
                                    :key="'d' + i"
                                    :cx="p.x"
                                    :cy="p.debitY"
                                    r="2.5"
                                    fill="#f43f5e"
                                    stroke="white"
                                    stroke-width="1"
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
                                    class="mt-0.5 text-[8px] font-extrabold text-emerald-500"
                                >
                                    +{{
                                        Math.round(p.creditVal / 100000) / 10
                                    }}M
                                </div>
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Right: AI Financial Health Advisor -->
            <Card
                class="flex flex-col justify-between overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center gap-1.5 text-sm font-bold"
                    >
                        <Brain class="size-4 text-indigo-500" />
                        AI Advisor & Thống kê
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
                            Tỷ suất Lợi nhuận Ròng: {{ marginPercent }}%
                        </div>
                        <p
                            class="text-[11px] leading-relaxed font-semibold text-muted-foreground"
                        >
                            <span v-if="totals.credit_raw === 0">
                                Chưa có giao dịch phát sinh dòng tiền. Gợi ý:
                                Kiểm tra trạng thái đồng bộ hóa các gói thuê bao
                                (subscription) của nhà hàng để kích hoạt thanh
                                toán tự động qua cổng hóa đơn.
                            </span>
                            <span v-else-if="marginPercent >= 75">
                                Tỷ suất lợi nhuận ròng đạt mức tối ưu ({{
                                    marginPercent
                                }}%). Doanh thu từ thanh toán dịch vụ của đối
                                tác ổn định, chi phí điều chỉnh bù trừ thấp. Bạn
                                nên duy trì chính sách hiện hành.
                            </span>
                            <span v-else>
                                Tỷ suất ròng ở mức khá ({{ marginPercent }}%).
                                Khuyên dùng: Rà soát lại các khoản chiết khấu
                                lớn và các lệnh điều chỉnh thủ công
                                (`adjustment`) từ Admin để bảo toàn tối đa dòng
                                tiền.
                            </span>
                        </p>
                    </div>

                    <div
                        class="flex items-center justify-between rounded-xl border border-border/30 bg-muted/20 px-3 py-2 text-[10px] font-bold text-muted-foreground"
                    >
                        <span class="flex items-center gap-1"
                            ><AlertTriangle class="size-3 text-amber-500" />
                            Cảnh báo rủi ro</span
                        >
                        <span class="text-right"
                            >Hạn chế điều chỉnh công nợ thủ công.</span
                        >
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <Card
            class="rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
        >
            <CardHeader class="border-b border-border/40 bg-muted/10 pb-3">
                <CardTitle class="flex items-center gap-1.5 text-sm font-bold">
                    <Search class="size-4 text-orange-500" />
                    Bộ lọc giao dịch
                </CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 pt-4 md:grid-cols-4">
                <div class="grid gap-1.5">
                    <Label
                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <Calendar class="size-3.5 text-orange-500" />
                        Từ ngày
                    </Label>
                    <Input
                        v-model="dateFrom"
                        type="date"
                        class="rounded-xl border-border focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                        @change="applyFilters"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label
                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <Calendar class="size-3.5 text-orange-500" />
                        Đến ngày
                    </Label>
                    <Input
                        v-model="dateTo"
                        type="date"
                        class="rounded-xl border-border focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                        @change="applyFilters"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label
                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <Wallet class="size-3.5 text-orange-500" />
                        Nhà hàng
                    </Label>
                    <Select v-model="restaurantId">
                        <SelectTrigger
                            class="h-9 rounded-xl border-border text-xs focus:border-orange-500 focus:ring-orange-500/20"
                        >
                            <SelectValue placeholder="Tất cả" />
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
                </div>
                <div class="grid gap-1.5">
                    <Label
                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <BookOpen class="size-3.5 text-orange-500" />
                        Loại giao dịch
                    </Label>
                    <Select v-model="entryType">
                        <SelectTrigger
                            class="h-9 rounded-xl border-border text-xs focus:border-orange-500 focus:ring-orange-500/20"
                        >
                            <SelectValue placeholder="Tất cả" />
                        </SelectTrigger>
                        <SelectContent class="rounded-xl">
                            <SelectItem value="all"
                                >Tất cả loại giao dịch</SelectItem
                            >
                            <SelectItem value="payment">Thanh toán</SelectItem>
                            <SelectItem value="adjustment"
                                >Điều chỉnh</SelectItem
                            >
                            <SelectItem value="commission">Hoa hồng</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </CardContent>
        </Card>

        <!-- Ledger Table -->
        <Card
            class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
        >
            <CardHeader class="border-b border-border/40 bg-muted/10 pb-3">
                <CardTitle
                    class="flex items-center justify-between text-sm font-bold"
                >
                    <span
                        >Sổ Cái
                        <span
                            class="ml-1 text-xs font-bold text-muted-foreground"
                            >({{ entries.total }} giao dịch)</span
                        ></span
                    >
                </CardTitle>
            </CardHeader>
            <CardContent class="pt-4">
                <div
                    v-if="entries.data.length === 0"
                    class="py-16 text-center text-xs font-semibold text-muted-foreground"
                >
                    Không có giao dịch nào trong khoảng thời gian đã chọn.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-xs font-semibold">
                        <thead>
                            <tr
                                class="border-b border-border/60 pb-3 text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                            >
                                <th class="pb-3 text-left font-black">Ngày</th>
                                <th class="pb-3 text-left font-black">Loại</th>
                                <th class="pb-3 text-left font-black">Mô tả</th>
                                <th class="pb-3 text-left font-black">
                                    Nhà hàng
                                </th>
                                <th class="pb-3 text-right font-black">
                                    Số tiền
                                </th>
                                <th class="pb-3 text-left font-black">
                                    Trạng thái
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/30">
                            <tr
                                v-for="(entry, idx) in entries.data"
                                :key="idx"
                                class="text-slate-700 transition-all hover:bg-muted/30 dark:text-slate-300"
                            >
                                <td
                                    class="py-3.5 pr-4 font-mono text-xs whitespace-nowrap text-slate-500"
                                >
                                    {{ entry.date_label }}
                                </td>
                                <td class="py-3.5 pr-4">
                                    <Badge
                                        variant="outline"
                                        :class="[
                                            'rounded-full border border-indigo-500/20 bg-indigo-500/10 px-2 py-0.5 text-[9px] font-black text-indigo-600 uppercase dark:text-indigo-400',
                                            entry.type === 'payment'
                                                ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                                : '',
                                            entry.type === 'adjustment'
                                                ? 'border-amber-500/20 bg-amber-500/10 text-amber-600'
                                                : '',
                                            entry.type === 'commission'
                                                ? 'border-sky-500/20 bg-sky-500/10 text-sky-600'
                                                : '',
                                        ]"
                                    >
                                        {{ entry.icon }}
                                        {{
                                            typeLabels[entry.type] || entry.type
                                        }}
                                    </Badge>
                                </td>
                                <td class="py-3.5 pr-4">
                                    <p
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ entry.description }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-[11px] leading-relaxed text-muted-foreground"
                                    >
                                        {{ entry.detail }}
                                    </p>
                                </td>
                                <td
                                    class="py-3.5 pr-4 font-bold text-slate-600 dark:text-slate-400"
                                >
                                    {{ entry.restaurant }}
                                </td>
                                <td
                                    class="py-3.5 pr-4 text-right font-mono text-xs font-black whitespace-nowrap"
                                    :class="
                                        entry.direction === 'credit'
                                            ? 'text-emerald-500'
                                            : 'text-rose-500'
                                    "
                                >
                                    {{ entry.direction === 'credit' ? '+' : '−'
                                    }}{{ entry.amount_fmt }}₫
                                </td>
                                <td class="py-3.5">
                                    <Badge
                                        variant="outline"
                                        class="rounded-full border border-slate-500/25 bg-slate-500/10 px-2 py-0.5 text-[9px] font-black text-slate-600 uppercase dark:text-slate-400"
                                        >{{ entry.status }}</Badge
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="entries.last_page > 1"
                    class="mt-4 flex flex-wrap justify-center gap-1 border-t border-border/20 pt-4"
                >
                    <button
                        v-for="link in entries.links"
                        :key="link.label"
                        :disabled="!link.url"
                        :class="[
                            'cursor-pointer rounded-lg px-3 py-1.5 text-[11px] font-bold transition',
                            link.active
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-muted',
                            !link.url ? 'cursor-not-allowed opacity-40' : '',
                        ]"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
