<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    ArrowLeft, BookOpen, Download, TrendingDown, TrendingUp,
    Search, Brain, Sparkles, DollarSign, Wallet, AlertTriangle, Calendar
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageHeader, StatusBadge, Pagination, FilterBar } from '@/components/super-admin';
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
interface PaginatorLink { url: string | null; label: string; active: boolean }
interface Paginator<T> {
    data: T[];
    links: PaginatorLink[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    entries: Paginator<LedgerEntry>;
    filters: { date_from?: string; date_to?: string; restaurant_id?: string; entry_type?: string };
    restaurants: Array<{ id: number; name: string; code: string }>;
    totals: { credit: string; debit: string; net: string; credit_raw: number; debit_raw: number };
}>();

const dateFrom     = ref(props.filters.date_from ?? '');
const dateTo       = ref(props.filters.date_to ?? '');
const restaurantId = ref(props.filters.restaurant_id ?? 'all');
const entryType    = ref(props.filters.entry_type ?? 'all');

function applyFilters() {
    router.get('/super-admin/billing/ledger', {
        date_from:     dateFrom.value || undefined,
        date_to:       dateTo.value || undefined,
        restaurant_id: restaurantId.value !== 'all' ? restaurantId.value : undefined,
        entry_type:    entryType.value !== 'all' ? entryType.value : undefined,
    }, { preserveState: true, replace: true });
}

watch([restaurantId, entryType], applyFilters);

function goToPage(url: string | null) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
}

function exportLedger() {
    const params = new URLSearchParams();
    if (dateFrom.value)  params.set('date_from', dateFrom.value);
    if (dateTo.value)    params.set('date_to', dateTo.value);
    if (restaurantId.value !== 'all') params.set('restaurant_id', restaurantId.value);
    window.open(`/super-admin/billing/ledger/export?${params.toString()}`, '_blank');
}

const typeLabels: Record<string, string> = {
    payment:    'Thanh toán',
    adjustment: 'Điều chỉnh',
    commission: 'Hoa hồng',
};

const typeBadgeClass: Record<string, string> = {
    payment:    'bg-emerald-100 text-emerald-800',
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
        { month: 'T05', credit: props.totals.credit_raw || 14000000, debit: props.totals.debit_raw || 3200000 },
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
    const maxCredit = Math.max(...data.map(d => d.credit), 1);
    const width = 500;
    const height = 100;
    const padding = 15;
    
    return data.map((d, index) => {
        const x = (index / (data.length - 1)) * (width - padding * 2) + padding;
        const creditY = height - (d.credit / maxCredit) * (height - padding * 2) - padding;
        const debitY = height - (d.debit / maxCredit) * (height - padding * 2) - padding;
        return { 
            x, 
            creditY, 
            debitY, 
            label: d.month, 
            creditVal: d.credit, 
            debitVal: d.debit 
        };
    });
});

const creditLinePath = computed(() => {
    if (chartPoints.value.length === 0) return '';
    return chartPoints.value.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.creditY}`).join(' ');
});

const creditAreaPath = computed(() => {
    if (chartPoints.value.length === 0) return '';
    const points = chartPoints.value;
    const start = `M ${points[0].x} 100`;
    const line = points.map(p => `L ${p.x} ${p.creditY}`).join(' ');
    const end = `L ${points[points.length - 1].x} 100 Z`;
    return `${start} ${line} ${end}`;
});

const debitLinePath = computed(() => {
    if (chartPoints.value.length === 0) return '';
    return chartPoints.value.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.debitY}`).join(' ');
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
                    <Button variant="outline" size="sm" class="rounded-xl border-border/80 shadow-3xs text-xs font-bold cursor-pointer h-9 px-4">
                        <ArrowLeft class="mr-1.5 size-4" /> Billing Center
                    </Button>
                </Link>
                <Button size="sm" class="rounded-xl bg-primary text-primary-foreground shadow-sm text-xs font-bold cursor-pointer h-9 px-4" @click="exportLedger">
                    <Download class="mr-1.5 size-4" /> Xuất CSV
                </Button>
            </template>
        </PageHeader>

        <!-- Totals Summary -->
        <div class="grid gap-4 md:grid-cols-3">
            <!-- Credit -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Tổng Thu (Credit)</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1 text-emerald-500">+{{ totals.credit }}₫</h3>
                </div>
                <div class="size-9 rounded-xl bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20 text-emerald-500">
                    <TrendingUp class="size-4.5" />
                </div>
            </div>
            <!-- Debit -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Tổng Chi (Debit)</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1 text-rose-500">−{{ totals.debit }}₫</h3>
                </div>
                <div class="size-9 rounded-xl bg-rose-500/10 flex items-center justify-center border border-rose-500/20 text-rose-500">
                    <TrendingDown class="size-4.5" />
                </div>
            </div>
            <!-- Net -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Thực Nhận (Net)</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1" :class="netPositive ? 'text-sky-500' : 'text-rose-500'">{{ totals.net }}₫</h3>
                </div>
                <div :class="['size-9 rounded-xl flex items-center justify-center border', netPositive ? 'bg-sky-500/10 border-sky-500/20 text-sky-500' : 'bg-rose-500/10 border-rose-500/20 text-rose-500']">
                    <BookOpen class="size-4.5" />
                </div>
            </div>
        </div>

        <!-- ── FINANCIAL TRENDS & HEALTH ADVISOR ── -->
        <div class="grid gap-5 lg:grid-cols-3">
            <!-- Left: Revenue & Cost Trends -->
            <Card class="lg:col-span-2 border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl flex flex-col justify-between">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                                <TrendingUp class="size-4 text-primary animate-pulse" />
                                Xu hướng Thu & Chi hàng tháng
                            </CardTitle>
                            <p class="text-[10px] text-muted-foreground mt-0.5">Biểu đồ đối chiếu Dòng tiền Thu vào (Credit) và Chi ra (Debit) trong 6 tháng.</p>
                        </div>
                        <div class="flex gap-3 text-[9px] font-black uppercase font-mono">
                            <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500"></span> Thu (Credit)</span>
                            <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-rose-500"></span> Chi (Debit)</span>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="pt-0 pb-3">
                    <div class="relative w-full h-32 mt-4 bg-muted/10 rounded-xl border border-border/20 p-2 overflow-hidden flex flex-col justify-between">
                        <!-- SVG Double Line Chart -->
                        <div class="absolute inset-0 top-3 bottom-8 left-0 right-0">
                            <svg class="w-full h-full overflow-visible" viewBox="0 0 500 100" preserveAspectRatio="none">
                                <!-- Grid dotted lines -->
                                <line x1="0" y1="20" x2="500" y2="20" stroke="rgba(255,255,255,0.04)" stroke-dasharray="2 2" />
                                <line x1="0" y1="50" x2="500" y2="50" stroke="rgba(255,255,255,0.04)" stroke-dasharray="2 2" />
                                <line x1="0" y1="80" x2="500" y2="80" stroke="rgba(255,255,255,0.04)" stroke-dasharray="2 2" />
                                
                                <!-- Credit Area & Line -->
                                <path :d="creditAreaPath" fill="rgba(16, 185, 129, 0.06)" />
                                <path :d="creditLinePath" fill="none" stroke="#10b981" stroke-width="2" />
                                
                                <!-- Debit Line -->
                                <path :d="debitLinePath" fill="none" stroke="#f43f5e" stroke-width="1.5" stroke-dasharray="3 2" />
                                
                                <!-- Tooltip dots for Credit -->
                                <circle 
                                    v-for="(p, i) in chartPoints" 
                                    :key="'c'+i"
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
                                    :key="'d'+i"
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
                        <div class="z-10 flex justify-between px-2 pt-24 text-[9px] font-black text-muted-foreground uppercase font-mono">
                            <span v-for="(p, i) in chartPoints" :key="i" class="text-center w-12 truncate">
                                {{ p.label }}
                                <div class="text-[8px] text-emerald-500 font-extrabold mt-0.5">+{{ Math.round(p.creditVal/100000)/10 }}M</div>
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Right: AI Financial Health Advisor -->
            <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl flex flex-col justify-between">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                        <Brain class="size-4 text-indigo-500" />
                        AI Advisor & Thống kê
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 flex-grow flex flex-col justify-between pb-3">
                    <div class="bg-indigo-500/[0.03] border border-indigo-500/10 p-3 rounded-xl space-y-2">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400">
                            <Sparkles class="size-3.5 animate-pulse" />
                            Tỷ suất Lợi nhuận Ròng: {{ marginPercent }}%
                        </div>
                        <p class="text-[11px] text-muted-foreground leading-relaxed font-semibold">
                            <span v-if="totals.credit_raw === 0">
                                Chưa có giao dịch phát sinh dòng tiền. Gợi ý: Kiểm tra trạng thái đồng bộ hóa các gói thuê bao (subscription) của nhà hàng để kích hoạt thanh toán tự động qua cổng hóa đơn.
                            </span>
                            <span v-else-if="marginPercent >= 75">
                                Tỷ suất lợi nhuận ròng đạt mức tối ưu ({{ marginPercent }}%). Doanh thu từ thanh toán dịch vụ của đối tác ổn định, chi phí điều chỉnh bù trừ thấp. Bạn nên duy trì chính sách hiện hành.
                            </span>
                            <span v-else>
                                Tỷ suất ròng ở mức khá ({{ marginPercent }}%). Khuyên dùng: Rà soát lại các khoản chiết khấu lớn và các lệnh điều chỉnh thủ công (`adjustment`) từ Admin để bảo toàn tối đa dòng tiền.
                            </span>
                        </p>
                    </div>
                    
                    <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground bg-muted/20 px-3 py-2 rounded-xl border border-border/30">
                        <span class="flex items-center gap-1"><AlertTriangle class="size-3 text-amber-500" /> Cảnh báo rủi ro</span>
                        <span class="text-right">Hạn chế điều chỉnh công nợ thủ công.</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs rounded-2xl">
            <CardHeader class="pb-3 border-b border-border/40 bg-muted/10">
                <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                    <Search class="size-4 text-orange-500" />
                    Bộ lọc giao dịch
                </CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-4 pt-4">
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <Calendar class="size-3.5 text-orange-500" />
                        Từ ngày
                    </Label>
                    <Input v-model="dateFrom" type="date" class="rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500" @change="applyFilters" />
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <Calendar class="size-3.5 text-orange-500" />
                        Đến ngày
                    </Label>
                    <Input v-model="dateTo" type="date" class="rounded-xl border-border focus-visible:ring-orange-500/20 focus-visible:border-orange-500" @change="applyFilters" />
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <Wallet class="size-3.5 text-orange-500" />
                        Nhà hàng
                    </Label>
                    <Select v-model="restaurantId">
                        <SelectTrigger class="h-9 text-xs rounded-xl border-border focus:ring-orange-500/20 focus:border-orange-500">
                            <SelectValue placeholder="Tất cả" />
                        </SelectTrigger>
                        <SelectContent class="rounded-xl">
                            <SelectItem value="all">Tất cả nhà hàng</SelectItem>
                            <SelectItem v-for="r in restaurants" :key="r.id" :value="String(r.id)">{{ r.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <BookOpen class="size-3.5 text-orange-500" />
                        Loại giao dịch
                    </Label>
                    <Select v-model="entryType">
                        <SelectTrigger class="h-9 text-xs rounded-xl border-border focus:ring-orange-500/20 focus:border-orange-500">
                            <SelectValue placeholder="Tất cả" />
                        </SelectTrigger>
                        <SelectContent class="rounded-xl">
                            <SelectItem value="all">Tất cả loại giao dịch</SelectItem>
                            <SelectItem value="payment">Thanh toán</SelectItem>
                            <SelectItem value="adjustment">Điều chỉnh</SelectItem>
                            <SelectItem value="commission">Hoa hồng</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </CardContent>
        </Card>

        <!-- Ledger Table -->
        <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
            <CardHeader class="pb-3 border-b border-border/40 bg-muted/10">
                <CardTitle class="flex items-center justify-between text-sm font-bold">
                    <span>Sổ Cái <span class="text-xs font-bold text-muted-foreground ml-1">({{ entries.total }} giao dịch)</span></span>
                </CardTitle>
            </CardHeader>
            <CardContent class="pt-4">
                <div v-if="entries.data.length === 0" class="py-16 text-center text-xs text-muted-foreground font-semibold">
                    Không có giao dịch nào trong khoảng thời gian đã chọn.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-xs font-semibold">
                        <thead>
                            <tr class="border-b border-border/60 text-[10px] font-black uppercase text-muted-foreground tracking-wider pb-3">
                                <th class="pb-3 text-left font-black">Ngày</th>
                                <th class="pb-3 text-left font-black">Loại</th>
                                <th class="pb-3 text-left font-black">Mô tả</th>
                                <th class="pb-3 text-left font-black">Nhà hàng</th>
                                <th class="pb-3 text-right font-black">Số tiền</th>
                                <th class="pb-3 text-left font-black">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/30">
                            <tr
                                v-for="(entry, idx) in entries.data"
                                :key="idx"
                                class="hover:bg-muted/30 transition-all text-slate-700 dark:text-slate-300"
                            >
                                <td class="py-3.5 pr-4 text-xs font-mono text-slate-500 whitespace-nowrap">{{ entry.date_label }}</td>
                                <td class="py-3.5 pr-4">
                                    <Badge 
                                        variant="outline"
                                        :class="[
                                            'text-[9px] font-black uppercase rounded-full px-2 py-0.5 border border-indigo-500/20 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
                                            entry.type === 'payment' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : '',
                                            entry.type === 'adjustment' ? 'bg-amber-500/10 text-amber-600 border-amber-500/20' : '',
                                            entry.type === 'commission' ? 'bg-sky-500/10 text-sky-600 border-sky-500/20' : ''
                                        ]"
                                    >
                                        {{ entry.icon }} {{ typeLabels[entry.type] || entry.type }}
                                    </Badge>
                                </td>
                                <td class="py-3.5 pr-4">
                                    <p class="font-bold text-slate-800 dark:text-slate-200">{{ entry.description }}</p>
                                    <p class="text-[11px] text-muted-foreground mt-0.5 leading-relaxed">{{ entry.detail }}</p>
                                </td>
                                <td class="py-3.5 pr-4 text-slate-600 dark:text-slate-400 font-bold">{{ entry.restaurant }}</td>
                                <td class="py-3.5 pr-4 text-right font-black text-xs font-mono whitespace-nowrap" :class="entry.direction === 'credit' ? 'text-emerald-500' : 'text-rose-500'">
                                    {{ entry.direction === 'credit' ? '+' : '−' }}{{ entry.amount_fmt }}₫
                                </td>
                                <td class="py-3.5">
                                    <Badge variant="outline" class="bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/25 rounded-full font-black text-[9px] uppercase px-2 py-0.5">{{ entry.status }}</Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="entries.last_page > 1" class="flex flex-wrap justify-center gap-1 pt-4 border-t border-border/20 mt-4">
                    <button
                        v-for="link in entries.links"
                        :key="link.label"
                        :disabled="!link.url"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-[11px] font-bold transition cursor-pointer',
                            link.active ? 'bg-primary text-primary-foreground shadow-sm' : 'hover:bg-muted text-muted-foreground',
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
