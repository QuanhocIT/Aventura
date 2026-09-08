<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BadgeDollarSign,
    Boxes,
    Calendar,
    ChevronDown,
    ChevronRight,
    ChevronUp,
    Download,
    Eye,
    Layers,
    Lock,
    PackageCheck,
    RefreshCw,
    Search,
    ShieldCheck,
    TrendingDown,
    TrendingUp,
    Truck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import WarehouseAiRecommendations from '@/components/WarehouseAiRecommendations.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface InventoryBatchItem {
    id: number;
    batch_number: string;
    supplier_name: string;
    unit_cost: number;
    quantity_remaining: number;
    purchased_at: string;
    raw_purchased_at?: string;
    expiry_date?: string | null;
    status: string;
    is_expired?: boolean;
    location_name?: string | null;
}

interface PriceTrend {
    diff: number;
    percent: number;
    trend: 'up' | 'down' | 'stable' | 'initial';
}

interface IngredientItem {
    id: number;
    name: string;
    sku?: string | null;
    unit?: {
        id?: number;
        name?: string;
        symbol?: string;
    } | null;
    latest_cost: number;
    previous_cost?: number | null;
    price_trend?: PriceTrend | null;
    latest_purchased_at?: string | null;
    latest_batch_number?: string | null;
    latest_supplier_name?: string | null;
    is_stale?: boolean;
    total_stock: number;
    active_batches_count: number;
    batches_count: number;
    batches: InventoryBatchItem[];
}

interface BatchGovernance {
    last_updated_at?: string | null;
    stale_count: number;
    large_change_count: number;
    pending_count: number;
    total_batches: number;
    price_up_count: number;
    price_down_count: number;
}

const props = defineProps<{
    ingredients: IngredientItem[];
    canManageWarehouse?: boolean;
    priceGovernance: BatchGovernance;
    centralWarehouseAi?: any;
}>();

const search = ref('');
const activeFilterTab = ref<'all' | 'price_up' | 'price_down' | 'large_change' | 'recent' | 'has_stock'>('all');
const expandedIngredientIds = ref<number[]>([]);

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(Number(value || 0));

const formatNumber = (value: number) =>
    new Intl.NumberFormat('vi-VN').format(Number(value || 0));

const totalIngredients = computed(() => props.ingredients?.length || 0);

const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();

    return (props.ingredients || []).filter((item) => {
        // Search filter
        if (q) {
            const matchesIngredient =
                item.name.toLowerCase().includes(q) ||
                (item.sku && item.sku.toLowerCase().includes(q)) ||
                (item.latest_supplier_name && item.latest_supplier_name.toLowerCase().includes(q));

            const matchesBatch = item.batches?.some(
                (b) =>
                    b.batch_number.toLowerCase().includes(q) ||
                    b.supplier_name.toLowerCase().includes(q),
            );

            if (!matchesIngredient && !matchesBatch) {
                return false;
            }
        }

        // Tab filter
        if (activeFilterTab.value === 'price_up') {
            return item.price_trend?.trend === 'up';
        }

        if (activeFilterTab.value === 'price_down') {
            return item.price_trend?.trend === 'down';
        }

        if (activeFilterTab.value === 'large_change') {
            return Math.abs(item.price_trend?.percent || 0) >= 10;
        }

        if (activeFilterTab.value === 'recent') {
            return !item.is_stale && item.latest_purchased_at;
        }

        if (activeFilterTab.value === 'has_stock') {
            return item.total_stock > 0;
        }

        return true;
    });
});

const isAllExpanded = computed(() => {
    if (filteredRows.value.length === 0) {
        return false;
    }

    return filteredRows.value.every((row) => expandedIngredientIds.value.includes(row.id));
});

const toggleExpandAll = () => {
    if (isAllExpanded.value) {
        expandedIngredientIds.value = [];
    } else {
        expandedIngredientIds.value = filteredRows.value.map((row) => row.id);
    }
};

const toggleExpandRow = (ingredientId: number) => {
    const idx = expandedIngredientIds.value.indexOf(ingredientId);

    if (idx > -1) {
        expandedIngredientIds.value.splice(idx, 1);
    } else {
        expandedIngredientIds.value.push(ingredientId);
    }
};

const isRowExpanded = (ingredientId: number) => {
    return expandedIngredientIds.value.includes(ingredientId);
};

const refreshPage = () => {
    router.reload({ preserveScroll: true });
};

// Export batch ledger to CSV
const exportBatchLedgerCSV = () => {
    const rowsToExport = filteredRows.value;

    if (!rowsToExport.length) {
        toast.warning('Không có dữ liệu để xuất file.');

        return;
    }

    const headers = [
        'Mã SKU',
        'Tên Nguyên Liệu',
        'Đơn Vị Tính',
        'Mã Lô / Phiếu Nhập',
        'Ngày Nhập',
        'Nhà Cung Cấp',
        'Đơn Giá Nhập (VND)',
        'Số Lượng Tồn Lô',
        'Hạn Sử Dụng',
        'Trạng Thái Lô',
    ];

    const csvLines: string[] = [headers.join(',')];

    rowsToExport.forEach((item) => {
        const sku = `"${(item.sku || '').replace(/"/g, '""')}"`;
        const name = `"${item.name.replace(/"/g, '""')}"`;
        const unit = `"${item.unit?.symbol || 'đv'}"`;

        if (!item.batches || item.batches.length === 0) {
            csvLines.push(
                [
                    sku,
                    name,
                    unit,
                    '"Chưa có lô"',
                    '""',
                    '""',
                    item.latest_cost,
                    item.total_stock,
                    '""',
                    '"Chưa nhập"',
                ].join(','),
            );
        } else {
            item.batches.forEach((batch) => {
                const batchNum = `"${batch.batch_number.replace(/"/g, '""')}"`;
                const date = `"${batch.purchased_at}"`;
                const supplier = `"${batch.supplier_name.replace(/"/g, '""')}"`;
                const cost = batch.unit_cost;
                const qty = batch.quantity_remaining;
                const expiry = `"${batch.expiry_date || '—'}"`;
                const status =
                    batch.status === 'active'
                        ? batch.quantity_remaining > 0
                            ? 'Còn hàng'
                            : 'Đã dùng hết'
                        : batch.status;

                csvLines.push(
                    [
                        sku,
                        name,
                        unit,
                        batchNum,
                        date,
                        supplier,
                        cost,
                        qty,
                        expiry,
                        `"${status}"`,
                    ].join(','),
                );
            });
        }
    });

    const bom = '\uFEFF';
    const blob = new Blob([bom + csvLines.join('\n')], {
        type: 'text/csv;charset=utf-8;',
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute(
        'download',
        `so_gia_nhap_tung_dot_${new Date().toISOString().slice(0, 10)}.csv`,
    );
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    toast.success('Đã tải xuống sổ theo dõi giá từng đợt nhập thành công!');
};
</script>

<template>
    <Head title="Sổ Giá Nhập Từng Đợt Kho Tổng" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
        <!-- ── Top Header Bar ─────────────────────────────────────────────── -->
        <div
            class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
        >
            <div class="flex items-center gap-3.5">
                <div
                    class="flex size-12 items-center justify-center rounded-2xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 shadow-inner dark:border-emerald-500/30 dark:bg-emerald-950/50 dark:text-emerald-400"
                >
                    <BadgeDollarSign class="size-6" />
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-white"
                        >
                            Sổ Giá Nhập Từng Đợt Kho Tổng
                        </h1>
                        <Badge
                            variant="outline"
                            class="border-emerald-500/30 bg-emerald-500/10 text-xs font-semibold text-emerald-700 dark:text-emerald-400"
                        >
                            <Lock class="mr-1 size-3" />
                            Khóa Bất Biến (Ledger Locked)
                        </Badge>
                        <Badge
                            variant="secondary"
                            class="text-xs font-medium text-slate-600 dark:text-slate-300"
                        >
                            Theo Từng Lô Hàng
                        </Badge>
                    </div>
                    <p
                        class="mt-0.5 text-xs text-slate-500 sm:text-sm dark:text-slate-400"
                    >
                        Theo dõi minh bạch đơn giá nhập thực tế của từng đợt gắn liền với hóa đơn và mã lô. Nghiêm cấm chỉnh sửa giá thủ công.
                    </p>
                </div>
            </div>

            <!-- Quick Header Actions -->
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    class="border-slate-200 text-xs dark:border-slate-800"
                >
                    <Link href="/inventory/central-warehouse" class="gap-1.5">
                        <ArrowLeft class="size-3.5" />
                        <span>Tổng quan Kho</span>
                    </Link>
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    @click="refreshPage"
                    class="gap-1.5 border-slate-200 text-xs dark:border-slate-800"
                >
                    <RefreshCw class="size-3.5" />
                    <span>Làm mới</span>
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    @click="exportBatchLedgerCSV"
                    class="gap-1.5 border-slate-200 text-xs text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800"
                    title="Tải sổ theo dõi giá từng đợt nhập hàng ra file CSV"
                >
                    <Download class="size-3.5 text-slate-500" />
                    <span>Xuất Sổ Giá CSV</span>
                </Button>
            </div>
        </div>

        <!-- ── Policy Assurance Banner ────────────────────────────────────── -->
        <div
            class="flex items-start gap-3.5 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-xs text-slate-700 sm:p-5 dark:border-emerald-500/30 dark:bg-emerald-950/20 dark:text-slate-300"
        >
            <div
                class="rounded-xl bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400"
            >
                <ShieldCheck class="size-5 shrink-0" />
            </div>
            <div class="space-y-1">
                <div class="flex items-center gap-2 font-semibold text-slate-900 dark:text-white">
                    <span>Nguyên Tắc Quản Trị Chi Phí Chuỗi (Batch-Level Accounting):</span>
                    <Badge variant="outline" class="border-emerald-600/30 text-[10px] text-emerald-700 dark:text-emerald-300">
                        Không Cào Bằng Trung Bình
                    </Badge>
                </div>
                <p class="leading-relaxed text-slate-600 dark:text-slate-400">
                    Đơn giá nguyên liệu không được điều chỉnh thủ công hay lấy trung bình cào bằng. Mỗi đợt nhập hàng sở hữu đơn giá riêng biệt theo đúng hóa đơn, mã lô và nhà cung cấp thực tế. Khi xuất kho cấp phát hoặc kiểm kê, chi phí được khấu trừ chính xác theo từng lô (FEFO/FIFO) đảm bảo độ chính xác tuyệt đối của báo cáo tài chính và COGS.
                </p>
            </div>
        </div>

        <!-- ── 4 KPI Summary Cards ────────────────────────────────────────── -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Card 1: Tổng Mặt Hàng -->
            <Card
                class="border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        Tổng Mặt Hàng Quản Lý
                    </CardTitle>
                    <div
                        class="rounded-lg bg-slate-100 p-1.5 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <Boxes class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-bold text-slate-900 dark:text-white"
                    >
                        {{ formatNumber(totalIngredients) }}
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Nguyên liệu trong catalog Kho Tổng
                    </p>
                </CardContent>
            </Card>

            <!-- Card 2: Tổng Đợt Nhập Hàng Đã Ghi Nhận -->
            <Card
                class="border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        Tổng Đợt Nhập (Lô Hàng)
                    </CardTitle>
                    <div
                        class="rounded-lg bg-indigo-500/10 p-1.5 text-indigo-600 dark:text-indigo-400"
                    >
                        <Layers class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-bold text-indigo-600 dark:text-indigo-400"
                    >
                        {{ formatNumber(priceGovernance.total_batches) }}
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Đợt nhập hàng đã ghi nhận vào sổ cái
                    </p>
                </CardContent>
            </Card>

            <!-- Card 3: Đợt Mới Tăng Giá -->
            <Card
                class="border-slate-200 bg-white shadow-sm transition-colors dark:border-slate-800 dark:bg-slate-900"
                :class="{
                    'border-rose-500/40 bg-rose-50/20 dark:border-rose-500/30 dark:bg-rose-950/10':
                        priceGovernance.price_up_count > 0,
                }"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        Đợt Mới Tăng Giá
                    </CardTitle>
                    <div
                        class="rounded-lg bg-rose-500/10 p-1.5 text-rose-600 dark:text-rose-400"
                    >
                        <TrendingUp class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-bold text-rose-600 dark:text-rose-400"
                    >
                        {{ priceGovernance.price_up_count }}
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{
                            priceGovernance.price_up_count > 0
                                ? 'Mặt hàng có giá đợt mới cao hơn đợt trước'
                                : 'Không có mặt hàng nào tăng giá'
                        }}
                    </p>
                </CardContent>
            </Card>

            <!-- Card 4: Đợt Mới Giảm Giá -->
            <Card
                class="border-slate-200 bg-white shadow-sm transition-colors dark:border-slate-800 dark:bg-slate-900"
                :class="{
                    'border-emerald-500/40 bg-emerald-50/20 dark:border-emerald-500/30 dark:bg-emerald-950/10':
                        priceGovernance.price_down_count > 0,
                }"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardTitle
                        class="text-xs font-medium text-slate-500 dark:text-slate-400"
                    >
                        Đợt Mới Giảm Giá
                    </CardTitle>
                    <div
                        class="rounded-lg bg-emerald-500/10 p-1.5 text-emerald-600 dark:text-emerald-400"
                    >
                        <TrendingDown class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"
                    >
                        {{ priceGovernance.price_down_count }}
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{
                            priceGovernance.price_down_count > 0
                                ? 'Mặt hàng tiết kiệm chi phí đầu vào'
                                : 'Đơn giá ổn định'
                        }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- ── AI Insights Component ──────────────────────────────────────── -->
        <WarehouseAiRecommendations
            v-if="centralWarehouseAi"
            :analysis="centralWarehouseAi"
        />

        <!-- ── Main Batch Ledger Table Card ───────────────────────────────── -->
        <Card class="border-slate-200 shadow-sm dark:border-slate-800">
            <CardHeader
                class="border-b border-slate-100 bg-slate-50/50 p-4 sm:p-6 dark:border-slate-800 dark:bg-slate-900/50"
            >
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <CardTitle
                                    class="text-base font-bold text-slate-900 dark:text-white"
                                >
                                    Bảng Theo Dõi Giá Từng Đợt Nhập
                                </CardTitle>
                                <Badge
                                    variant="secondary"
                                    class="font-mono text-xs"
                                >
                                    {{ filteredRows.length }} /
                                    {{ totalIngredients }} mặt hàng
                                </Badge>
                            </div>
                            <CardDescription
                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                            >
                                Đơn giá đợt gần nhất, so sánh với đợt trước và lịch sử chi tiết từng đợt nhập của mỗi nguyên liệu.
                            </CardDescription>
                        </div>

                        <!-- Search & Expand All -->
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="toggleExpandAll"
                                class="h-9 gap-1.5 border-slate-200 text-xs dark:border-slate-800"
                            >
                                <component :is="isAllExpanded ? ChevronUp : ChevronDown" class="size-3.5" />
                                <span>{{ isAllExpanded ? 'Thu gọn tất cả' : 'Mở rộng tất cả' }}</span>
                            </Button>

                            <div class="relative w-full sm:w-64">
                                <Search
                                    class="absolute top-2.5 left-3 size-4 text-slate-400"
                                />
                                <Input
                                    v-model="search"
                                    placeholder="Tìm nguyên liệu, SKU, mã lô, NCC..."
                                    class="h-9 pl-9 text-xs"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Smart Filter Tabs -->
                    <div class="flex flex-wrap items-center gap-2 border-t border-slate-200/60 pt-3 dark:border-slate-800/60">
                        <button
                            type="button"
                            @click="activeFilterTab = 'all'"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                            :class="
                                activeFilterTab === 'all'
                                    ? 'bg-slate-900 text-white shadow-xs dark:bg-slate-100 dark:text-slate-900'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700'
                            "
                        >
                            <span>Tất cả</span>
                            <span class="rounded-full bg-black/15 px-1.5 py-0.2 text-[10px] dark:bg-white/20">{{ totalIngredients }}</span>
                        </button>

                        <button
                            type="button"
                            @click="activeFilterTab = 'price_up'"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                            :class="
                                activeFilterTab === 'price_up'
                                    ? 'bg-rose-600 text-white shadow-xs dark:bg-rose-500 dark:text-white'
                                    : 'bg-rose-50 text-rose-800 hover:bg-rose-100 dark:bg-rose-950/30 dark:text-rose-300 dark:hover:bg-rose-900/40'
                            "
                        >
                            <TrendingUp class="size-3" />
                            <span>Đợt mới tăng giá</span>
                            <span class="rounded-full bg-rose-700/20 px-1.5 py-0.2 text-[10px] dark:bg-rose-300/20">{{ priceGovernance.price_up_count }}</span>
                        </button>

                        <button
                            type="button"
                            @click="activeFilterTab = 'price_down'"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                            :class="
                                activeFilterTab === 'price_down'
                                    ? 'bg-emerald-600 text-white shadow-xs dark:bg-emerald-500 dark:text-slate-900'
                                    : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-950/30 dark:text-emerald-300 dark:hover:bg-emerald-900/40'
                            "
                        >
                            <TrendingDown class="size-3" />
                            <span>Đợt mới giảm giá</span>
                            <span class="rounded-full bg-emerald-700/20 px-1.5 py-0.2 text-[10px] dark:bg-emerald-300/20">{{ priceGovernance.price_down_count }}</span>
                        </button>

                        <button
                            type="button"
                            @click="activeFilterTab = 'large_change'"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                            :class="
                                activeFilterTab === 'large_change'
                                    ? 'bg-amber-600 text-white shadow-xs dark:bg-amber-500 dark:text-slate-900'
                                    : 'bg-amber-50 text-amber-800 hover:bg-amber-100 dark:bg-amber-950/30 dark:text-amber-300 dark:hover:bg-amber-900/40'
                            "
                        >
                            <span>Biến động ≥ 10%</span>
                            <span class="rounded-full bg-amber-700/20 px-1.5 py-0.2 text-[10px] dark:bg-amber-300/20">{{ priceGovernance.large_change_count }}</span>
                        </button>

                        <button
                            type="button"
                            @click="activeFilterTab = 'recent'"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                            :class="
                                activeFilterTab === 'recent'
                                    ? 'bg-indigo-600 text-white shadow-xs dark:bg-indigo-500 dark:text-white'
                                    : 'bg-indigo-50 text-indigo-800 hover:bg-indigo-100 dark:bg-indigo-950/30 dark:text-indigo-300 dark:hover:bg-indigo-900/40'
                            "
                        >
                            <Calendar class="size-3" />
                            <span>Nhập ≤ 30 ngày qua</span>
                        </button>

                        <button
                            type="button"
                            @click="activeFilterTab = 'has_stock'"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                            :class="
                                activeFilterTab === 'has_stock'
                                    ? 'bg-teal-600 text-white shadow-xs dark:bg-teal-500 dark:text-white'
                                    : 'bg-teal-50 text-teal-800 hover:bg-teal-100 dark:bg-teal-950/30 dark:text-teal-300 dark:hover:bg-teal-900/40'
                            "
                        >
                            <PackageCheck class="size-3" />
                            <span>Đang có tồn kho</span>
                        </button>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-left text-xs">
                        <thead
                            class="border-b border-slate-200 bg-slate-100/75 text-[11px] font-semibold tracking-wider text-slate-600 uppercase dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-400"
                        >
                            <tr>
                                <th class="w-10 px-3 py-3 text-center"></th>
                                <th class="px-4 py-3">Nguyên Liệu</th>
                                <th class="px-4 py-3">Mã SKU</th>
                                <th class="px-4 py-3 text-center">ĐVT</th>
                                <th class="px-4 py-3 text-right">
                                    Đơn Giá Đợt Gần Nhất
                                </th>
                                <th class="px-4 py-3 text-right">
                                    Đơn Giá Đợt Trước
                                </th>
                                <th class="px-4 py-3 text-center">Biến Động Giữa 2 Đợt</th>
                                <th class="px-4 py-3 text-right">Tồn Kho (Theo Lô)</th>
                                <th class="px-4 py-3 text-center">Lịch Sử Các Đợt</th>
                            </tr>
                        </thead>

                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <!-- Empty State -->
                            <tr v-if="filteredRows.length === 0">
                                <td
                                    colspan="9"
                                    class="py-12 text-center text-slate-500 dark:text-slate-400"
                                >
                                    <div
                                        class="flex flex-col items-center justify-center gap-2"
                                    >
                                        <div
                                            class="rounded-full bg-slate-100 p-3 dark:bg-slate-800"
                                        >
                                            <Search
                                                class="size-5 text-slate-400"
                                            />
                                        </div>
                                        <div class="font-medium">
                                            Không tìm thấy nguyên liệu nào phù hợp
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            Thử thay đổi từ khóa tìm kiếm hoặc chọn bộ lọc "Tất cả".
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- Row Items -->
                            <template
                                v-for="row in filteredRows"
                                :key="row.id"
                            >
                                <tr
                                    class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                                    :class="{
                                        'bg-slate-50/50 dark:bg-slate-800/20': isRowExpanded(row.id),
                                    }"
                                >
                                    <!-- Expand Toggle -->
                                    <td class="px-3 py-3.5 text-center">
                                        <button
                                            type="button"
                                            @click="toggleExpandRow(row.id)"
                                            class="inline-flex size-6 items-center justify-center rounded-md text-slate-400 hover:bg-slate-200 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                                            :title="isRowExpanded(row.id) ? 'Thu gọn' : 'Xem các đợt nhập'"
                                        >
                                            <component
                                                :is="isRowExpanded(row.id) ? ChevronDown : ChevronRight"
                                                class="size-4 transition-transform"
                                            />
                                        </button>
                                    </td>

                                    <!-- Name -->
                                    <td
                                        class="px-4 py-3.5 font-medium text-slate-900 dark:text-white"
                                    >
                                        <div class="flex flex-col gap-0.5">
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold">{{ row.name }}</span>
                                                <Badge
                                                    v-if="row.batches_count > 1"
                                                    variant="outline"
                                                    class="border-indigo-500/20 bg-indigo-50/50 text-[10px] text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-950/30 dark:text-indigo-300"
                                                >
                                                    {{ row.batches_count }} đợt
                                                </Badge>
                                            </div>
                                            <span
                                                v-if="row.latest_supplier_name"
                                                class="flex items-center gap-1 text-[11px] text-slate-500 dark:text-slate-400"
                                            >
                                                <Truck class="size-3 text-slate-400" />
                                                {{ row.latest_supplier_name }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- SKU -->
                                    <td
                                        class="px-4 py-3.5 font-mono text-[11px] text-slate-500 dark:text-slate-400"
                                    >
                                        {{ row.sku || '—' }}
                                    </td>

                                    <!-- Unit -->
                                    <td class="px-4 py-3.5 text-center">
                                        <Badge
                                            variant="secondary"
                                            class="text-[11px] font-normal"
                                        >
                                            {{ row.unit?.symbol || 'đv' }}
                                        </Badge>
                                    </td>

                                    <!-- Latest Batch Cost -->
                                    <td
                                        class="px-4 py-3.5 text-right font-semibold text-slate-900 dark:text-white"
                                    >
                                        <div class="flex flex-col items-end gap-0.5">
                                            <span class="text-sm font-bold text-slate-900 dark:text-white">
                                                {{ formatCurrency(row.latest_cost) }}
                                            </span>
                                            <span
                                                v-if="row.latest_purchased_at"
                                                class="text-[10px] text-slate-400"
                                            >
                                                Nhập {{ row.latest_purchased_at }}
                                            </span>
                                            <span
                                                v-else
                                                class="text-[10px] text-slate-400 italic"
                                            >
                                                Chưa có lịch sử nhập
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Previous Batch Cost -->
                                    <td
                                        class="px-4 py-3.5 text-right font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        <div v-if="row.previous_cost !== null && row.previous_cost !== undefined" class="flex flex-col items-end gap-0.5">
                                            <span class="text-xs">
                                                {{ formatCurrency(row.previous_cost) }}
                                            </span>
                                            <span class="text-[10px] text-slate-400">
                                                Đợt trước đó
                                            </span>
                                        </div>
                                        <span v-else class="text-slate-300 dark:text-slate-600">
                                            —
                                        </span>
                                    </td>

                                    <!-- Variance Between Batches -->
                                    <td class="px-4 py-3.5 text-center">
                                        <template v-if="row.price_trend">
                                            <Badge
                                                v-if="row.price_trend.trend === 'up'"
                                                class="gap-1 border-rose-500/30 bg-rose-500/10 text-[10px] font-semibold text-rose-700 dark:text-rose-400"
                                            >
                                                <TrendingUp class="size-3" />
                                                +{{ row.price_trend.percent }}% (+{{ formatCurrency(row.price_trend.diff) }})
                                            </Badge>

                                            <Badge
                                                v-else-if="row.price_trend.trend === 'down'"
                                                class="gap-1 border-emerald-500/30 bg-emerald-500/10 text-[10px] font-semibold text-emerald-700 dark:text-emerald-400"
                                            >
                                                <TrendingDown class="size-3" />
                                                {{ row.price_trend.percent }}% ({{ formatCurrency(row.price_trend.diff) }})
                                            </Badge>

                                            <Badge
                                                v-else-if="row.price_trend.trend === 'stable'"
                                                variant="outline"
                                                class="border-slate-300 text-[10px] text-slate-600 dark:border-slate-700 dark:text-slate-400"
                                            >
                                                Bằng giá đợt trước
                                            </Badge>

                                            <Badge
                                                v-else-if="row.price_trend.trend === 'initial'"
                                                variant="secondary"
                                                class="text-[10px] text-slate-500 dark:text-slate-400"
                                            >
                                                Đợt đầu tiên
                                            </Badge>
                                        </template>
                                        <span v-else class="text-slate-300 dark:text-slate-600">—</span>
                                    </td>

                                    <!-- Total Stock (Across Batches) -->
                                    <td class="px-4 py-3.5 text-right font-medium text-slate-700 dark:text-slate-300">
                                        <div class="flex flex-col items-end gap-0.5">
                                            <span class="font-bold">
                                                {{ formatNumber(row.total_stock) }} {{ row.unit?.symbol || 'đv' }}
                                            </span>
                                            <span class="text-[10px] text-slate-400">
                                                {{ row.active_batches_count }} lô còn tồn
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Actions: Expand / View Batches -->
                                    <td class="px-4 py-3.5 text-center">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="toggleExpandRow(row.id)"
                                            class="h-7 gap-1 px-2.5 text-xs text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700 dark:text-indigo-400 dark:hover:bg-indigo-950/40"
                                        >
                                            <Eye class="size-3" />
                                            <span>{{ isRowExpanded(row.id) ? 'Đóng' : 'Xem các đợt' }}</span>
                                        </Button>
                                    </td>
                                </tr>

                                <!-- Expandable Sub-table for Batches of this Ingredient -->
                                <tr
                                    v-if="isRowExpanded(row.id)"
                                    class="bg-slate-50/70 dark:bg-slate-900/60"
                                >
                                    <td colspan="9" class="p-4 sm:px-6">
                                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-inner dark:border-slate-800 dark:bg-slate-950">
                                            <div class="mb-3 flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <Layers class="size-4 text-indigo-600 dark:text-indigo-400" />
                                                    <span class="text-xs font-bold text-slate-900 dark:text-white">
                                                        Lịch Sử Từng Đợt Nhập Hàng: {{ row.name }}
                                                    </span>
                                                    <Badge variant="outline" class="text-[10px]">
                                                        {{ row.batches?.length || 0 }} đợt nhập
                                                    </Badge>
                                                </div>
                                                <span class="text-[11px] text-slate-500 dark:text-slate-400">
                                                    Sắp xếp theo thứ tự nhập gần nhất trước (FEFO / FIFO audit)
                                                </span>
                                            </div>

                                            <div v-if="!row.batches || row.batches.length === 0" class="py-6 text-center text-xs text-slate-400">
                                                Chưa có dữ liệu lô hoặc phiếu nhập nào được ghi nhận cho mặt hàng này.
                                            </div>

                                            <div v-else class="overflow-x-auto">
                                                <table class="w-full text-left text-xs">
                                                    <thead class="border-b border-slate-100 bg-slate-50 text-[10px] font-semibold text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                                                        <tr>
                                                            <th class="px-3 py-2">Mã Lô / Phiếu Nhập</th>
                                                            <th class="px-3 py-2">Ngày Nhập</th>
                                                            <th class="px-3 py-2">Nhà Cung Cấp</th>
                                                            <th class="px-3 py-2 text-right">Đơn Giá Đợt Đó</th>
                                                            <th class="px-3 py-2 text-right">Tồn Còn Lại</th>
                                                            <th class="px-3 py-2 text-center">Hạn Sử Dụng</th>
                                                            <th class="px-3 py-2 text-center">Vị Trí Kho</th>
                                                            <th class="px-3 py-2 text-center">Trạng Thái</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                        <tr
                                                            v-for="(batch, bIdx) in row.batches"
                                                            :key="batch.id"
                                                            class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-900/80"
                                                            :class="{
                                                                'bg-emerald-50/20 dark:bg-emerald-950/10': bIdx === 0,
                                                            }"
                                                        >
                                                            <!-- Batch Code -->
                                                            <td class="px-3 py-2.5 font-mono text-[11px] font-medium text-slate-900 dark:text-white">
                                                                <div class="flex items-center gap-1.5">
                                                                    <span>{{ batch.batch_number }}</span>
                                                                    <Badge
                                                                        v-if="bIdx === 0"
                                                                        variant="outline"
                                                                        class="border-emerald-500/30 bg-emerald-500/10 text-[9px] text-emerald-700 dark:text-emerald-400"
                                                                    >
                                                                        Mới nhất
                                                                    </Badge>
                                                                </div>
                                                            </td>

                                                            <!-- Date -->
                                                            <td class="px-3 py-2.5 text-slate-600 dark:text-slate-300">
                                                                <div class="flex items-center gap-1">
                                                                    <Calendar class="size-3 text-slate-400" />
                                                                    <span>{{ batch.purchased_at }}</span>
                                                                </div>
                                                            </td>

                                                            <!-- Supplier -->
                                                            <td class="px-3 py-2.5 text-slate-700 dark:text-slate-300">
                                                                {{ batch.supplier_name }}
                                                            </td>

                                                            <!-- Unit Cost of Batch -->
                                                            <td class="px-3 py-2.5 text-right font-bold text-slate-900 dark:text-white">
                                                                {{ formatCurrency(batch.unit_cost) }}
                                                            </td>

                                                            <!-- Quantity Remaining -->
                                                            <td class="px-3 py-2.5 text-right font-medium text-slate-700 dark:text-slate-300">
                                                                {{ formatNumber(batch.quantity_remaining) }} {{ row.unit?.symbol || 'đv' }}
                                                            </td>

                                                            <!-- Expiry Date -->
                                                            <td class="px-3 py-2.5 text-center">
                                                                <span
                                                                    v-if="batch.expiry_date"
                                                                    :class="{
                                                                        'font-semibold text-rose-600 dark:text-rose-400': batch.is_expired,
                                                                        'text-slate-600 dark:text-slate-300': !batch.is_expired,
                                                                    }"
                                                                >
                                                                    {{ batch.expiry_date }}
                                                                </span>
                                                                <span v-else class="text-slate-300 dark:text-slate-600">—</span>
                                                            </td>

                                                            <!-- Warehouse Location -->
                                                            <td class="px-3 py-2.5 text-center text-slate-500 dark:text-slate-400">
                                                                {{ batch.location_name || 'Kho chính' }}
                                                            </td>

                                                            <!-- Status -->
                                                            <td class="px-3 py-2.5 text-center">
                                                                <Badge
                                                                    v-if="batch.is_expired"
                                                                    variant="destructive"
                                                                    class="text-[9px]"
                                                                >
                                                                    Hết hạn
                                                                </Badge>
                                                                <Badge
                                                                    v-else-if="batch.quantity_remaining > 0"
                                                                    variant="outline"
                                                                    class="border-emerald-500/30 bg-emerald-500/10 text-[9px] text-emerald-700 dark:text-emerald-400"
                                                                >
                                                                    Còn hàng
                                                                </Badge>
                                                                <Badge
                                                                    v-else
                                                                    variant="secondary"
                                                                    class="text-[9px] text-slate-400"
                                                                >
                                                                    Đã dùng hết
                                                                </Badge>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
