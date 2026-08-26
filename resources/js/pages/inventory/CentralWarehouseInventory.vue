<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Boxes,
    CalendarClock,
    Check,
    ChevronDown,
    ChevronRight,
    ClipboardList,
    FileDown,
    History,
    LockKeyhole,
    MinusCircle,
    PlusCircle,
    PackageCheck,
    PackageSearch,
    RotateCcw,
    Search,
    ShieldAlert,
    Truck,
    UnlockKeyhole,
    Warehouse,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import NegativeInventoryCases from '@/components/NegativeInventoryCases.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WarehouseAiRecommendations from '@/components/WarehouseAiRecommendations.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Batch = {
    id: number;
    batch_number: string;
    quantity_remaining: number;
    unit_cost: number;
    purchased_at: string | null;
    expiry_date: string | null;
    days_remaining: number | null;
    status: string;
    is_expired: boolean;
    is_expiring_soon: boolean;
    lock_reason: string | null;
    recall_note: string | null;
    locked_at: string | null;
};

type StockItem = {
    id: number;
    name: string;
    sku: string | null;
    category_name: string | null;
    storage_type_label: string;
    unit_symbol: string;
    on_hand: number;
    theoretical: number;
    variance: number;
    reserved: number;
    available: number;
    min_stock_level: number;
    reorder_level: number;
    average_cost: number;
    stock_value: number;
    status: 'out' | 'low' | 'expired' | 'expiring' | 'locked' | 'normal';
    last_counted_at: string | null;
    batches: Batch[];
    inventory_id: number | null;
};

const props = defineProps<{
    centralBranch: { id: number; name: string } | null;
    centralStockItems: StockItem[];
    inventorySummary: Record<string, number>;
    inventoryActivity: Array<{
        id: number;
        ingredient: string | null;
        unit: string;
        type: string;
        direction: 'in' | 'out';
        quantity: number;
        unit_cost: number;
        total_cost: number;
        reference_code: string | null;
        notes: string | null;
        performed_by: string | null;
        occurred_at: string | null;
    }>;
    warehouseLocations: Array<{
        id: number;
        location_code: string;
        zone: string;
        rack: string | null;
        shelf: string | null;
        bin: string | null;
        is_cold_storage: boolean;
        is_quarantine: boolean;
    }>;
    canManageWarehouse: boolean;
    canReconcile: boolean;
    canUnlockBatches: boolean;
    centralWarehouseAi?: any;
    negativeStockCases?: Array<{
        id: number;
        branch_name?: string | null;
        ingredient_name?: string | null;
        unit_symbol?: string | null;
        status: 'open' | 'in_progress' | 'pending_owner_approval' | 'pending_verification';
        negative_quantity: number;
        on_hand: number;
        estimated_value: number;
        detected_at?: string | null;
        auto_plan?: string | null;
        handling_plan?: string | null;
        responsible_user_name?: string | null;
        expected_restock_at?: string | null;
    }>;
}>();

const search = ref('');
const statusFilter = ref('all');
const categoryFilter = ref('all');
const expandedId = ref<number | null>(null);
const adjusting = ref<StockItem | null>(null);
const wasting = ref<StockItem | null>(null);
const batchAction = ref<{
    item: StockItem;
    batch: Batch;
    action: 'lock' | 'unlock' | 'recall';
} | null>(null);

const adjustForm = useForm({
    reconcile_items: [{ ingredient_id: 0, physical_qty: 0 }] as Array<{
        ingredient_id: number;
        physical_qty: number;
    }>,
    notes: '',
    is_opening_balance: false,
});

const wasteForm = useForm({
    ingredient_id: 0,
    quantity: 0,
    waste_category: 'spoilage',
    notes: '',
    photo: null as File | null,
});

const batchForm = useForm({
    reason: '',
    note: '',
});

const categories = computed(() => [
    'all',
    ...Array.from(
        new Set(
            props.centralStockItems.map((item) => item.category_name || 'Khác'),
        ),
    ).sort((a, b) => a.localeCompare(b, 'vi')),
]);

const filteredItems = computed(() => {
    const query = search.value.trim().toLowerCase();

    return [...props.centralStockItems]
        .filter((item) => {
            const matchesSearch =
                !query ||
                [item.name, item.sku, item.category_name]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase()
                    .includes(query) ||
                item.batches.some((batch) =>
                    batch.batch_number.toLowerCase().includes(query),
                );
            const matchesStatus =
                statusFilter.value === 'all' ||
                item.status === statusFilter.value;
            const matchesCategory =
                categoryFilter.value === 'all' ||
                (item.category_name || 'Khác') === categoryFilter.value;

            return matchesSearch && matchesStatus && matchesCategory;
        })
        .sort((a, b) => {
            const priority: Record<string, number> = {
                out: 0,
                expired: 1,
                low: 2,
                expiring: 3,
                locked: 4,
                normal: 5,
            };

            return (
                priority[a.status] - priority[b.status] ||
                a.name.localeCompare(b.name, 'vi')
            );
        });
});

const formatQuantity = (value: number | string | null | undefined) =>
    new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(
        Number(value || 0),
    );

const formatCurrency = (value: number | string | null | undefined) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));

const statusMeta = (status: StockItem['status']) =>
    ({
        out: {
            label: 'Hết hàng',
            class: 'bg-rose-500/10 text-rose-300 border-rose-500/25',
        },
        low: {
            label: 'Dưới định mức',
            class: 'bg-amber-500/10 text-amber-300 border-amber-500/25',
        },
        expired: {
            label: 'CÃ³ lÃ´ háº¿t HSD',
            class: 'bg-rose-500/10 text-rose-300 border-rose-500/25',
        },
        expiring: {
            label: 'Sắp hết HSD',
            class: 'bg-orange-500/10 text-orange-300 border-orange-500/25',
        },
        locked: {
            label: 'Có lô cần xử lý',
            class: 'bg-slate-500/10 text-slate-300 border-slate-500/25',
        },
        normal: {
            label: 'Ổn định',
            class: 'bg-emerald-500/10 text-emerald-300 border-emerald-500/25',
        },
    })[status];

const toggleExpanded = (id: number) => {
    expandedId.value = expandedId.value === id ? null : id;
};

const setStatusFilter = (status: string) => {
    statusFilter.value = status;
};

const closeActions = () => {
    adjusting.value = null;
    wasting.value = null;
    batchAction.value = null;
};

const openAdjust = (item: StockItem) => {
    adjusting.value = item;
    adjustForm.reconcile_items = [
        { ingredient_id: item.id, physical_qty: item.on_hand },
    ];
    adjustForm.notes = '';
    adjustForm.is_opening_balance = false;
};

const submitAdjust = () => {
    if (!adjusting.value || adjustForm.processing) {
        return;
    }

    adjustForm.post('/inventory/reconcile', {
        preserveScroll: true,
        onSuccess: closeActions,
    });
};

const openWaste = (item: StockItem) => {
    wasting.value = item;
    wasteForm.ingredient_id = item.id;
    wasteForm.quantity = 0;
    wasteForm.waste_category = 'spoilage';
    wasteForm.notes = '';
    wasteForm.photo = null;
};

const submitWaste = () => {
    if (!wasting.value || wasteForm.processing) {
        return;
    }

    wasteForm.post('/inventory/waste', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeActions,
    });
};

const openBatchAction = (
    item: StockItem,
    batch: Batch,
    action: 'lock' | 'unlock' | 'recall',
) => {
    batchAction.value = { item, batch, action };
    batchForm.reset();
};

const submitBatchAction = () => {
    if (!batchAction.value || batchForm.processing) {
        return;
    }

    const { batch, action } = batchAction.value;
    const url = `/inventory/batches/${batch.id}/${action}`;

    batchForm.post(url, {
        preserveScroll: true,
        onSuccess: closeActions,
    });
};

const onWastePhotoChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    wasteForm.photo = input.files?.[0] ?? null;
};

const activityTypeLabel = (type: string) =>
    ({
        purchase: 'Nhập hàng',
        usage: 'Xuất sử dụng',
        waste: 'Hao hụt',
        adjustment: 'Điều chỉnh',
        stocktake: 'Kiểm kê',
        return: 'Hoàn trả',
    })[type] || type;
</script>

<template>
    <Head title="Tồn kho Kho Tổng" />

    <div class="mx-auto w-full max-w-[1500px] space-y-5 p-4 sm:p-6">
        <section
            class="rounded-3xl border border-indigo-500/20 bg-gradient-to-br from-slate-950 via-indigo-950/90 to-slate-900 p-6 text-white shadow-xl sm:p-8"
        >
            <div
                class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end"
            >
                <div>
                    <Link
                        href="/inventory/central-warehouse"
                        class="mb-3 inline-flex items-center gap-1 text-xs text-indigo-200 hover:text-white"
                        >← Tổng quan Kho Tổng</Link
                    >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-500/20 text-indigo-200"
                        >
                            <Warehouse class="h-6 w-6" />
                        </div>
                        <div>
                            <h1
                                class="text-2xl font-bold tracking-tight sm:text-3xl"
                            >
                                Tồn kho Kho Tổng
                            </h1>
                            <p class="mt-1 text-sm text-indigo-100/75">
                                Theo dõi tồn thực tế, tồn khả dụng và lô hàng
                                tại kho nguồn.
                            </p>
                        </div>
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm backdrop-blur-sm"
                >
                    <p
                        class="text-[10px] font-semibold tracking-[0.16em] text-indigo-200/70 uppercase"
                    >
                        Phạm vi dữ liệu
                    </p>
                    <p class="mt-1 font-semibold text-white">
                        {{ centralBranch?.name || 'Kho Tổng chưa thiết lập' }}
                    </p>
                    <p class="mt-1 text-xs text-indigo-100/60">
                        Không phụ thuộc chi nhánh đang chọn trên header
                    </p>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <Card class="border-indigo-500/20 bg-indigo-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-indigo-300 uppercase">
                        Mặt hàng
                    </p>
                    <p class="mt-2 text-2xl font-bold text-indigo-100">
                        {{ inventorySummary.ingredient_count || 0 }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Trong catalog Kho Tổng
                    </p></CardContent
                ></Card
            >
            <Card class="border-sky-500/20 bg-sky-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-sky-300 uppercase">
                        Tồn thực tế
                    </p>
                    <p class="mt-2 text-2xl font-bold text-sky-100">
                        {{ formatQuantity(inventorySummary.on_hand_quantity) }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Đơn vị hàng hóa
                    </p></CardContent
                ></Card
            >
            <Card class="border-emerald-500/20 bg-emerald-950/10"
                ><CardContent class="p-4"
                    ><p
                        class="text-[11px] font-bold text-emerald-300 uppercase"
                    >
                        Tồn khả dụng
                    </p>
                    <p class="mt-2 text-2xl font-bold text-emerald-100">
                        {{
                            formatQuantity(inventorySummary.available_quantity)
                        }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Sau khi trừ giữ chỗ
                    </p></CardContent
                ></Card
            >
            <Card class="border-violet-500/20 bg-violet-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-violet-300 uppercase">
                        Giá trị tồn
                    </p>
                    <p class="mt-2 text-lg font-bold text-violet-100">
                        {{ formatCurrency(inventorySummary.on_hand_value) }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Theo giá vốn bình quân
                    </p></CardContent
                ></Card
            >
            <Card class="border-amber-500/20 bg-amber-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-amber-300 uppercase">
                        Cần bổ sung
                    </p>
                    <p class="mt-2 text-2xl font-bold text-amber-100">
                        {{ inventorySummary.low_stock_count || 0 }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Hết hàng hoặc dưới mức tối thiểu
                    </p></CardContent
                ></Card
            >
            <Card class="border-orange-500/20 bg-orange-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-orange-300 uppercase">
                        Lô sắp hết hạn
                    </p>
                    <p class="mt-2 text-2xl font-bold text-orange-100">
                        {{ inventorySummary.expiring_soon_count || 0 }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Trong 3 ngày tới
                    </p></CardContent
                ></Card
            >
        </section>

        <WarehouseAiRecommendations :initial-ai="props.centralWarehouseAi" context="stock" :max="3" />

        <NegativeInventoryCases
            :cases="negativeStockCases"
            title="Âm nguyên liệu tại Kho Tổng"
        />

        <section class="grid gap-3 md:grid-cols-3">
            <button
                type="button"
                class="flex items-center justify-between rounded-2xl border border-rose-500/20 bg-rose-500/5 px-4 py-3 text-left transition hover:border-rose-400/50 hover:bg-rose-500/10"
                @click="setStatusFilter('expired')"
            >
                <span>
                    <span
                        class="block text-[10px] font-bold tracking-wider text-rose-300 uppercase"
                        >Lô cần cách ly</span
                    >
                    <span class="mt-1 block text-xs text-muted-foreground"
                        >Đã hết hạn, không được cấp phát</span
                    >
                </span>
                <strong class="text-xl text-rose-200">{{
                    inventorySummary.expired_batch_count || 0
                }}</strong>
            </button>
            <button
                type="button"
                class="flex items-center justify-between rounded-2xl border border-amber-500/20 bg-amber-500/5 px-4 py-3 text-left transition hover:border-amber-400/50 hover:bg-amber-500/10"
                @click="setStatusFilter('locked')"
            >
                <span>
                    <span
                        class="block text-[10px] font-bold tracking-wider text-amber-300 uppercase"
                        >Lô bị khóa / thu hồi</span
                    >
                    <span class="mt-1 block text-xs text-muted-foreground"
                        >Cần xử lý chất lượng hoặc hồ sơ</span
                    >
                </span>
                <strong class="text-xl text-amber-200">{{
                    inventorySummary.locked_batch_count || 0
                }}</strong>
            </button>
            <div
                class="flex items-center justify-between rounded-2xl border border-indigo-500/20 bg-indigo-500/5 px-4 py-3"
            >
                <span>
                    <span
                        class="block text-[10px] font-bold tracking-wider text-indigo-300 uppercase"
                        >Chênh lệch kiểm kê</span
                    >
                    <span class="mt-1 block text-xs text-muted-foreground"
                        >Tồn lý thuyết trừ tồn thực tế</span
                    >
                </span>
                <strong class="text-xl text-indigo-200">{{
                    formatQuantity(inventorySummary.variance_quantity)
                }}</strong>
            </div>
        </section>

        <Card class="border-border shadow-sm">
            <CardHeader class="border-b border-border bg-muted/20 py-4">
                <div
                    class="flex flex-col justify-between gap-3 lg:flex-row lg:items-center"
                >
                    <div>
                        <CardTitle class="flex items-center gap-2 text-base"
                            ><PackageSearch class="h-5 w-5 text-indigo-300" />
                            Danh sách tồn Kho Tổng</CardTitle
                        >
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ filteredItems.length }}/{{
                                centralStockItems.length
                            }}
                            mặt hàng · Tồn khả dụng luôn đã trừ các đơn cấp phát
                            đang giữ chỗ.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link href="/inventory/count-sessions"
                            ><Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5 text-xs"
                                ><ClipboardList class="h-3.5 w-3.5" /> Kiểm
                                kê</Button
                            ></Link
                        >
                        <Link href="/inventory/transfers"
                            ><Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5 text-xs"
                                ><Truck class="h-3.5 w-3.5" /> Điều
                                chuyển</Button
                            ></Link
                        >
                        <Link href="/inventory/central-warehouse/requests"
                            ><Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5 text-xs"
                                ><Truck class="h-3.5 w-3.5" /> Đơn cấp
                                phát</Button
                            ></Link
                        >
                        <Link href="/inventory/central-warehouse/receiving"
                            ><Button
                                size="sm"
                                class="gap-1.5 bg-orange-600 text-xs text-white hover:bg-orange-700"
                                ><PackageCheck class="h-3.5 w-3.5" /> Nhận hàng
                                & GRN</Button
                            ></Link
                        >
                        <a
                            href="/inventory/central-warehouse/export"
                            class="inline-flex"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5 text-xs"
                            >
                                <FileDown class="h-3.5 w-3.5" /> Xuất báo cáo
                            </Button>
                        </a>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4 p-4">
                <div
                    class="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px_180px]"
                >
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground"
                        /><Input
                            v-model="search"
                            placeholder="Tìm tên, SKU hoặc nhóm nguyên liệu..."
                            class="h-9 pl-9 text-xs"
                        />
                    </div>
                    <select
                        v-model="statusFilter"
                        class="h-9 rounded-md border border-input bg-background px-3 text-xs text-foreground"
                    >
                        <option value="expired">CÃ³ lÃ´ háº¿t HSD</option>
                        <option value="all">Tất cả trạng thái</option>
                        <option value="out">Hết hàng</option>
                        <option value="low">Dưới định mức</option>
                        <option value="expiring">Sắp hết HSD</option>
                        <option value="locked">Có lô cần xử lý</option>
                        <option value="normal">Ổn định</option>
                    </select>
                    <select
                        v-model="categoryFilter"
                        class="h-9 rounded-md border border-input bg-background px-3 text-xs text-foreground"
                    >
                        <option
                            v-for="category in categories"
                            :key="category"
                            :value="category"
                        >
                            {{ category === 'all' ? 'Tất cả nhóm' : category }}
                        </option>
                    </select>
                </div>

                <div
                    v-if="!centralBranch"
                    class="rounded-xl border border-dashed border-rose-500/30 bg-rose-500/5 p-8 text-center text-sm text-rose-300"
                >
                    Chưa thiết lập Kho Tổng cho nhà hàng.
                </div>
                <div
                    v-else-if="filteredItems.length === 0"
                    class="rounded-xl border border-dashed border-border bg-muted/20 p-10 text-center text-sm text-muted-foreground"
                >
                    Không có mặt hàng phù hợp với bộ lọc hiện tại.
                </div>
                <div
                    v-else
                    class="overflow-x-auto rounded-xl border border-border"
                >
                    <table class="w-full min-w-[980px] text-left text-xs">
                        <thead
                            class="border-b border-border bg-muted/40 text-muted-foreground"
                        >
                            <tr>
                                <th class="w-8 p-3"></th>
                                <th class="p-3 text-right">Thao tác</th>
                                <th class="p-3">Nguyên liệu</th>
                                <th class="p-3">Trạng thái</th>
                                <th class="p-3 text-right">Tồn thực</th>
                                <th class="p-3 text-right">Giữ chỗ</th>
                                <th class="p-3 text-right">Khả dụng</th>
                                <th class="p-3 text-right">Tối thiểu</th>
                                <th class="p-3 text-right">Giá trị tồn</th>
                                <th class="p-3">Lô gần nhất</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template
                                v-for="item in filteredItems"
                                :key="item.id"
                            >
                                <tr
                                    class="cursor-pointer transition hover:bg-muted/20"
                                    @click="toggleExpanded(item.id)"
                                >
                                    <td class="p-3 text-muted-foreground">
                                        <ChevronDown
                                            v-if="expandedId === item.id"
                                            class="h-4 w-4"
                                        /><ChevronRight
                                            v-else
                                            class="h-4 w-4"
                                        />
                                    </td>
                                    <td class="p-3 text-right" @click.stop>
                                        <div class="flex justify-end gap-1">
                                            <Button
                                                v-if="canReconcile"
                                                size="icon"
                                                variant="ghost"
                                                class="size-8 text-indigo-300 hover:bg-indigo-500/10 hover:text-indigo-200"
                                                title="Kiểm kê nhanh"
                                                @click="openAdjust(item)"
                                            >
                                                <ClipboardList class="size-4" />
                                            </Button>
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                class="size-8 text-rose-300 hover:bg-rose-500/10 hover:text-rose-200"
                                                title="Ghi hao hụt"
                                                @click="openWaste(item)"
                                            >
                                                <MinusCircle class="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <p class="font-bold text-foreground">
                                            {{ item.name }}
                                        </p>
                                        <p
                                            class="mt-1 text-[10px] text-muted-foreground"
                                        >
                                            {{ item.sku || 'Chưa có SKU' }} ·
                                            {{ item.category_name || 'Khác' }} ·
                                            {{ item.storage_type_label }}
                                        </p>
                                    </td>
                                    <td class="p-3">
                                        <span
                                            class="rounded-full border px-2 py-1 text-[10px] font-semibold"
                                            :class="
                                                statusMeta(item.status).class
                                            "
                                            >{{
                                                statusMeta(item.status).label
                                            }}</span
                                        >
                                    </td>
                                    <td
                                        class="p-3 text-right font-semibold text-foreground"
                                    >
                                        {{ formatQuantity(item.on_hand) }}
                                        <span
                                            class="text-[10px] text-muted-foreground"
                                            >{{ item.unit_symbol }}</span
                                        >
                                    </td>
                                    <td class="p-3 text-right text-amber-300">
                                        {{ formatQuantity(item.reserved) }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-bold text-emerald-300"
                                    >
                                        {{ formatQuantity(item.available) }}
                                    </td>
                                    <td
                                        class="p-3 text-right text-muted-foreground"
                                    >
                                        {{
                                            formatQuantity(item.min_stock_level)
                                        }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-semibold text-sky-300"
                                    >
                                        {{ formatCurrency(item.stock_value) }}
                                    </td>
                                    <td class="p-3 text-muted-foreground">
                                        {{
                                            item.batches[0]?.expiry_date ||
                                            'Không có lô HSD'
                                        }}<span
                                            v-if="item.batches.length"
                                            class="mt-1 block text-[10px]"
                                            >{{ item.batches.length }} lô đang
                                            theo dõi</span
                                        >
                                    </td>
                                </tr>
                                <tr
                                    v-if="expandedId === item.id"
                                    class="bg-muted/10"
                                >
                                    <td colspan="10" class="p-4">
                                        <div
                                            class="grid gap-4 lg:grid-cols-[0.8fr_1.2fr]"
                                        >
                                            <div
                                                class="rounded-xl border border-border bg-background/40 p-4"
                                            >
                                                <div
                                                    class="mb-3 flex items-center gap-2 text-xs font-bold text-foreground"
                                                >
                                                    <Boxes
                                                        class="h-4 w-4 text-indigo-300"
                                                    />
                                                    Đối soát nhanh
                                                </div>
                                                <div
                                                    class="grid grid-cols-2 gap-3 text-xs"
                                                >
                                                    <div>
                                                        <p
                                                            class="text-muted-foreground"
                                                        >
                                                            Tồn lý thuyết
                                                        </p>
                                                        <p
                                                            class="mt-1 font-bold text-foreground"
                                                        >
                                                            {{
                                                                formatQuantity(
                                                                    item.theoretical,
                                                                )
                                                            }}
                                                            {{
                                                                item.unit_symbol
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-muted-foreground"
                                                        >
                                                            Chênh lệch
                                                        </p>
                                                        <p
                                                            class="mt-1 font-bold"
                                                            :class="
                                                                item.variance ===
                                                                0
                                                                    ? 'text-emerald-300'
                                                                    : 'text-amber-300'
                                                            "
                                                        >
                                                            {{
                                                                formatQuantity(
                                                                    item.variance,
                                                                )
                                                            }}
                                                            {{
                                                                item.unit_symbol
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-muted-foreground"
                                                        >
                                                            Mức đặt lại
                                                        </p>
                                                        <p
                                                            class="mt-1 font-bold text-foreground"
                                                        >
                                                            {{
                                                                formatQuantity(
                                                                    item.reorder_level,
                                                                )
                                                            }}
                                                            {{
                                                                item.unit_symbol
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-muted-foreground"
                                                        >
                                                            Kiểm kê gần nhất
                                                        </p>
                                                        <p
                                                            class="mt-1 font-bold text-foreground"
                                                        >
                                                            {{
                                                                item.last_counted_at ||
                                                                'Chưa kiểm kê'
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                class="rounded-xl border border-border bg-background/40 p-4"
                                            >
                                                <div
                                                    class="mb-3 flex items-center gap-2 text-xs font-bold text-foreground"
                                                >
                                                    <CalendarClock
                                                        class="h-4 w-4 text-orange-300"
                                                    />
                                                    Chi tiết lô hàng
                                                </div>
                                                <div
                                                    v-if="!item.batches.length"
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Chưa có lô hàng khả dụng cho
                                                    mặt hàng này.
                                                </div>
                                                <div
                                                    v-else
                                                    class="grid gap-2 sm:grid-cols-2"
                                                >
                                                    <div
                                                        v-for="batch in item.batches"
                                                        :key="batch.id"
                                                        class="rounded-lg border border-border bg-muted/20 p-3 text-xs"
                                                    >
                                                        <div
                                                            class="flex items-start justify-between gap-2"
                                                        >
                                                            <span
                                                                class="font-mono font-bold text-foreground"
                                                                >{{
                                                                    batch.batch_number
                                                                }}</span
                                                            ><span
                                                                class="rounded-full px-2 py-0.5 text-[10px]"
                                                                :class="
                                                                    batch.is_expired
                                                                        ? 'bg-rose-500/10 text-rose-300'
                                                                        : batch.is_expiring_soon
                                                                          ? 'bg-orange-500/10 text-orange-300'
                                                                          : 'bg-emerald-500/10 text-emerald-300'
                                                                "
                                                                >{{
                                                                    batch.is_expired
                                                                        ? 'Đã quá hạn'
                                                                        : batch.expiry_date
                                                                          ? `Còn ${batch.days_remaining} ngày`
                                                                          : 'Không HSD'
                                                                }}</span
                                                            >
                                                        </div>
                                                        <div
                                                            v-if="
                                                                batch.lock_reason ||
                                                                batch.recall_note
                                                            "
                                                            class="mt-2 rounded-lg border border-amber-400/20 bg-amber-400/5 px-2.5 py-2 text-[10px] text-amber-100"
                                                        >
                                                            <p
                                                                v-if="
                                                                    batch.lock_reason
                                                                "
                                                            >
                                                                <strong
                                                                    >Khóa:</strong
                                                                >
                                                                {{
                                                                    batch.lock_reason
                                                                }}
                                                            </p>
                                                            <p
                                                                v-if="
                                                                    batch.recall_note
                                                                "
                                                            >
                                                                <strong
                                                                    >Thu
                                                                    hồi:</strong
                                                                >
                                                                {{
                                                                    batch.recall_note
                                                                }}
                                                            </p>
                                                            <p
                                                                v-if="
                                                                    batch.locked_at
                                                                "
                                                                class="mt-1 text-amber-200/70"
                                                            >
                                                                Cập nhật
                                                                {{
                                                                    batch.locked_at
                                                                }}
                                                            </p>
                                                        </div>
                                                        <div
                                                            class="mt-2 flex flex-wrap gap-1.5"
                                                        >
                                                            <Button
                                                                v-if="
                                                                    canManageWarehouse &&
                                                                    [
                                                                        'active',
                                                                        'expired',
                                                                    ].includes(
                                                                        batch.status,
                                                                    )
                                                                "
                                                                size="sm"
                                                                variant="outline"
                                                                class="h-7 gap-1 text-[10px] text-amber-300"
                                                                @click="
                                                                    openBatchAction(
                                                                        item,
                                                                        batch,
                                                                        'lock',
                                                                    )
                                                                "
                                                                ><LockKeyhole
                                                                    class="size-3"
                                                                />
                                                                Khóa lô</Button
                                                            >
                                                            <Button
                                                                v-if="
                                                                    canManageWarehouse &&
                                                                    batch.status !==
                                                                        'recalled' &&
                                                                    batch.status !==
                                                                        'depleted'
                                                                "
                                                                size="sm"
                                                                variant="outline"
                                                                class="h-7 gap-1 text-[10px] text-rose-300"
                                                                @click="
                                                                    openBatchAction(
                                                                        item,
                                                                        batch,
                                                                        'recall',
                                                                    )
                                                                "
                                                                ><RotateCcw
                                                                    class="size-3"
                                                                />
                                                                Thu hồi</Button
                                                            >
                                                            <Button
                                                                v-if="
                                                                    canUnlockBatches &&
                                                                    batch.status ===
                                                                        'locked'
                                                                "
                                                                size="sm"
                                                                variant="outline"
                                                                class="h-7 gap-1 text-[10px] text-emerald-300"
                                                                @click="
                                                                    openBatchAction(
                                                                        item,
                                                                        batch,
                                                                        'unlock',
                                                                    )
                                                                "
                                                                ><UnlockKeyhole
                                                                    class="size-3"
                                                                />
                                                                Mở khóa</Button
                                                            >
                                                        </div>
                                                        <p
                                                            class="mt-2 text-muted-foreground"
                                                        >
                                                            Còn
                                                            <strong
                                                                class="text-foreground"
                                                                >{{
                                                                    formatQuantity(
                                                                        batch.quantity_remaining,
                                                                    )
                                                                }}
                                                                {{
                                                                    item.unit_symbol
                                                                }}</strong
                                                            >
                                                            ·
                                                            {{
                                                                formatCurrency(
                                                                    batch.unit_cost,
                                                                )
                                                            }}/{{
                                                                item.unit_symbol
                                                            }}
                                                        </p>
                                                        <p
                                                            class="mt-1 text-[10px] text-muted-foreground"
                                                        >
                                                            Nhập
                                                            {{
                                                                batch.purchased_at ||
                                                                '-'
                                                            }}
                                                            · HSD
                                                            {{
                                                                batch.expiry_date ||
                                                                '-'
                                                            }}
                                                            · {{ batch.status }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-wrap items-center gap-4 border-t border-border pt-3 text-[11px] text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1.5"
                        ><ShieldAlert class="h-3.5 w-3.5 text-amber-300" /> Các
                        cảnh báo cần xử lý được xếp lên đầu.</span
                    ><span class="inline-flex items-center gap-1.5"
                        ><ClipboardList class="h-3.5 w-3.5 text-indigo-300" />
                        Cấp phát làm giảm tồn khả dụng qua cơ chế giữ chỗ.</span
                    ><span class="inline-flex items-center gap-1.5"
                        ><AlertTriangle class="h-3.5 w-3.5 text-orange-300" />
                        Hạn dùng được tính theo ngày hiện tại.</span
                    >
                </div>
            </CardContent>
        </Card>
        <section class="grid gap-5 xl:grid-cols-[1.35fr_0.65fr]">
            <Card class="border-border shadow-sm">
                <CardHeader class="border-b border-border bg-muted/20 py-4">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <History class="size-5 text-indigo-300" /> Biến động tồn
                        kho gần đây
                    </CardTitle>
                    <p class="text-xs text-muted-foreground">
                        Các giao dịch tại Kho Tổng, dùng để truy vết trước khi
                        điều chỉnh hoặc kiểm kê.
                    </p>
                </CardHeader>
                <CardContent class="p-0">
                    <div
                        v-if="!inventoryActivity.length"
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        Chưa có biến động tồn kho.
                    </div>
                    <div v-else class="divide-y divide-border">
                        <div
                            v-for="activity in inventoryActivity.slice(0, 12)"
                            :key="activity.id"
                            class="flex flex-wrap items-center gap-3 px-4 py-3 text-xs"
                        >
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-full"
                                :class="
                                    activity.direction === 'in'
                                        ? 'bg-emerald-500/10 text-emerald-300'
                                        : 'bg-rose-500/10 text-rose-300'
                                "
                            >
                                <PlusCircle
                                    v-if="activity.direction === 'in'"
                                    class="size-4"
                                /><MinusCircle v-else class="size-4" />
                            </span>
                            <div class="min-w-[150px] flex-1">
                                <p class="font-bold text-foreground">
                                    {{ activity.ingredient || 'Nguyên liệu' }}
                                </p>
                                <p
                                    class="mt-0.5 text-[10px] text-muted-foreground"
                                >
                                    {{ activityTypeLabel(activity.type) }} ·
                                    {{ activity.occurred_at || '—' }} ·
                                    {{ activity.performed_by || 'Hệ thống' }}
                                </p>
                            </div>
                            <span
                                class="font-black"
                                :class="
                                    activity.direction === 'in'
                                        ? 'text-emerald-300'
                                        : 'text-rose-300'
                                "
                                >{{ activity.direction === 'in' ? '+' : '-'
                                }}{{ formatQuantity(activity.quantity) }}
                                {{ activity.unit }}</span
                            >
                            <span
                                class="max-w-[280px] truncate text-[10px] text-muted-foreground"
                                :title="activity.notes || undefined"
                                >{{
                                    activity.reference_code ||
                                    activity.notes ||
                                    '—'
                                }}</span
                            >
                        </div>
                    </div>
                </CardContent>
            </Card>
            <Card class="border-border shadow-sm">
                <CardHeader class="border-b border-border bg-muted/20 py-4"
                    ><CardTitle class="flex items-center gap-2 text-base"
                        ><Warehouse class="size-5 text-sky-300" /> Năng lực lưu
                        trữ</CardTitle
                    ></CardHeader
                >
                <CardContent class="space-y-3 p-4 text-xs">
                    <div
                        class="flex items-center justify-between rounded-xl bg-muted/30 p-3"
                    >
                        <span class="text-muted-foreground"
                            >Vị trí đang dùng</span
                        ><strong class="text-foreground">{{
                            warehouseLocations.length
                        }}</strong>
                    </div>
                    <div
                        class="flex items-center justify-between rounded-xl bg-muted/30 p-3"
                    >
                        <span class="text-muted-foreground">Kho lạnh</span
                        ><strong class="text-sky-300">{{
                            warehouseLocations.filter(
                                (location) => location.is_cold_storage,
                            ).length
                        }}</strong>
                    </div>
                    <div
                        class="flex items-center justify-between rounded-xl bg-rose-500/5 p-3"
                    >
                        <span class="text-muted-foreground">Vị trí cách ly</span
                        ><strong class="text-rose-300">{{
                            warehouseLocations.filter(
                                (location) => location.is_quarantine,
                            ).length
                        }}</strong>
                    </div>
                    <p class="pt-1 leading-5 text-muted-foreground">
                        Khi nhập hàng hoặc soạn cấp phát, hãy chọn đúng vị trí
                        lưu trữ và tách riêng hàng cách ly/hàng lỗi để tránh
                        cộng nhầm vào tồn khả dụng.
                    </p>
                </CardContent>
            </Card>
        </section>
    </div>
    <Teleport to="body">
    <div
        v-if="adjusting || wasting || batchAction"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
        @click.self="closeActions"
    >
        <div
            class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-border bg-background p-5 shadow-2xl sm:p-6"
        >
            <template v-if="adjusting">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-indigo-400 uppercase"
                        >
                            Kiểm kê nhanh
                        </p>
                        <h2 class="mt-1 text-xl font-black">
                            Đối chiếu {{ adjusting.name }}
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Tồn hệ thống:
                            {{ formatQuantity(adjusting.on_hand) }}
                            {{ adjusting.unit_symbol }} · Tồn lý thuyết:
                            {{ formatQuantity(adjusting.theoretical) }}
                            {{ adjusting.unit_symbol }}
                        </p>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeActions"
                        ><X class="size-4"
                    /></Button>
                </div>
                <form class="space-y-4" @submit.prevent="submitAdjust">
                    <div
                        class="rounded-xl border border-indigo-400/20 bg-indigo-950/20 p-3 text-xs text-indigo-100"
                    >
                        Kiểm kê nhanh sẽ ghi nhận lại tồn thực tế và tạo giao
                        dịch kiểm kê. Nếu chênh lệch lớn, hệ thống vẫn lưu audit
                        để truy vết.
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Số lượng thực tế</Label
                        ><Input
                            v-model="adjustForm.reconcile_items[0].physical_qty"
                            type="number"
                            min="0"
                            step="0.001"
                            required
                        />
                        <p class="text-[11px] text-muted-foreground">
                            Đơn vị: {{ adjusting.unit_symbol }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Ghi chú kiểm kê</Label
                        ><textarea
                            v-model="adjustForm.notes"
                            rows="3"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Vị trí kiểm, nguyên nhân chênh lệch, người chứng kiến..."
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeActions"
                            >Hủy</Button
                        ><Button
                            type="submit"
                            :disabled="adjustForm.processing"
                            class="bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                            ><Check class="size-4" /> Ghi nhận kiểm kê</Button
                        >
                    </div>
                </form>
            </template>

            <template v-else-if="wasting">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-rose-400 uppercase"
                        >
                            Kiểm soát hao hụt
                        </p>
                        <h2 class="mt-1 text-xl font-black">
                            Ghi hao hụt · {{ wasting.name }}
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Tồn khả dụng hiện tại:
                            {{ formatQuantity(wasting.available) }}
                            {{ wasting.unit_symbol }}
                        </p>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeActions"
                        ><X class="size-4"
                    /></Button>
                </div>
                <form class="space-y-4" @submit.prevent="submitWaste">
                    <div class="flex flex-col gap-1.5">
                        <Label>Số lượng hao hụt</Label
                        ><Input
                            v-model="wasteForm.quantity"
                            type="number"
                            min="0.001"
                            step="0.001"
                            :max="wasting.available"
                            required
                        />
                        <p
                            v-if="wasteForm.errors.quantity"
                            class="text-xs text-rose-500"
                        >
                            {{ wasteForm.errors.quantity }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Nguyên nhân</Label
                        ><select
                            v-model="wasteForm.waste_category"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="spoilage">Hư hỏng</option>
                            <option value="expired">Hết hạn</option>
                            <option value="damaged">Hàng lỗi</option>
                            <option value="cooking_loss">
                                Hao hụt chế biến
                            </option>
                            <option value="theft">Thất thoát</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Biên bản / ghi chú</Label
                        ><textarea
                            v-model="wasteForm.notes"
                            rows="3"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Bắt buộc ghi rõ khi chọn nguyên nhân Khác..."
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Ảnh bằng chứng bắt buộc</Label
                        ><Input
                            type="file"
                            accept="image/*"
                            required
                            @change="onWastePhotoChange"
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeActions"
                            >Hủy</Button
                        ><Button
                            type="submit"
                            :disabled="wasteForm.processing"
                            class="bg-rose-600 font-bold text-white hover:bg-rose-700"
                            ><MinusCircle class="size-4" /> Gửi ghi nhận hao
                            hụt</Button
                        >
                    </div>
                </form>
            </template>

            <template v-else-if="batchAction">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-amber-400 uppercase"
                        >
                            Quản trị lô hàng
                        </p>
                        <h2 class="mt-1 text-xl font-black">
                            {{
                                batchAction.action === 'lock'
                                    ? 'Khóa lô'
                                    : batchAction.action === 'recall'
                                      ? 'Yêu cầu thu hồi lô'
                                      : 'Mở khóa lô'
                            }}
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ batchAction.item.name }} ·
                            {{ batchAction.batch.batch_number }} · còn
                            {{
                                formatQuantity(
                                    batchAction.batch.quantity_remaining,
                                )
                            }}
                            {{ batchAction.item.unit_symbol }}
                        </p>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeActions"
                        ><X class="size-4"
                    /></Button>
                </div>
                <form class="space-y-4" @submit.prevent="submitBatchAction">
                    <div
                        class="rounded-xl border border-amber-400/20 bg-amber-950/20 p-3 text-xs text-amber-100"
                    >
                        {{
                            batchAction.action === 'unlock'
                                ? 'Mở khóa sẽ đưa lô về trạng thái có thể được FEFO sử dụng. Chỉ Owner/Super Admin được thực hiện.'
                                : 'Thao tác này ảnh hưởng trực tiếp đến khả năng xuất dùng của lô và được ghi vào audit log.'
                        }}
                    </div>
                    <div
                        v-if="batchAction.action === 'lock'"
                        class="flex flex-col gap-1.5"
                    >
                        <Label>Lý do khóa lô</Label
                        ><textarea
                            v-model="batchForm.reason"
                            rows="4"
                            required
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Ví dụ: nghi ngờ chất lượng, chờ kiểm nghiệm..."
                        />
                    </div>
                    <div
                        v-if="batchAction.action === 'recall'"
                        class="flex flex-col gap-1.5"
                    >
                        <Label>Ghi chú thu hồi</Label
                        ><textarea
                            v-model="batchForm.note"
                            rows="4"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Nhà cung cấp, số biên bản, hướng xử lý..."
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeActions"
                            >Hủy</Button
                        ><Button
                            type="submit"
                            :disabled="batchForm.processing"
                            class="bg-amber-600 font-bold text-white hover:bg-amber-700"
                            >Xác nhận thao tác</Button
                        >
                    </div>
                </form>
            </template>
        </div>
    </div>
    </Teleport>
</template>
