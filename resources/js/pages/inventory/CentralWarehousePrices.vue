<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowLeft,
    BadgeDollarSign,
    BadgePercent,
    Boxes,
    CheckCircle2,
    ClipboardList,
    Clock3,
    Filter,
    History,
    Info,
    RefreshCw,
    RotateCcw,
    Save,
    Search,
    Send,
    ShieldAlert,
    ShieldCheck,
    Timer,
    TrendingDown,
    TrendingUp,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import WarehouseAiRecommendations from '@/components/WarehouseAiRecommendations.vue';

defineOptions({ layout: AppLayout });

interface IngredientItem {
    id: number;
    name: string;
    sku?: string | null;
    average_cost?: number | string | null;
    unit?: {
        symbol?: string;
        name?: string;
    } | null;
}

interface PriceRow {
    ingredient_id: number;
    name: string;
    sku: string;
    unit_symbol: string;
    original_cost: number;
    average_cost: number;
}

interface PriceHistoryRow {
    id: number;
    ingredient_id: number;
    ingredient_name: string;
    ingredient_sku?: string | null;
    old_price: number;
    new_price: number;
    change_percent: number;
    status: string;
    reason?: string | null;
    changed_by: string;
    approved_by?: string | null;
    created_at?: string | null;
    approved_at?: string | null;
}

interface PendingPriceUpdate {
    id: number;
    status: string;
    requester_name: string;
    reason?: string | null;
    created_at?: string | null;
    items: Array<{
        ingredient_id: number;
        ingredient_name: string;
        proposed_price: number;
    }>;
}

const props = defineProps<{
    ingredients: IngredientItem[];
    canManageWarehouse: boolean;
    canProposePrices: boolean;
    pendingPriceUpdates: PendingPriceUpdate[];
    priceHistory: PriceHistoryRow[];
    priceGovernance: {
        last_updated_at?: string | null;
        stale_count: number;
        large_change_count: number;
        pending_count: number;
    };
    centralWarehouseAi?: any;
}>();

const page = usePage();
const currentUser = computed(() => (page.props.auth as any)?.user || {});
const isOwnerOrAdmin = computed(() => {
    const role = currentUser.value?.role;
    const roles = currentUser.value?.roles || [];

    if (Array.isArray(roles) && roles.length > 0) {
        return roles.some((r: any) => {
            const name = typeof r === 'string' ? r : r?.name;

            return ['owner', 'super_admin'].includes(name || '');
        });
    }

    return ['owner', 'super_admin'].includes(role || '');
});

const search = ref('');
const isSaving = ref(false);
const showOnlyModified = ref(false);
const showHistory = ref(false);
const historySearch = ref('');
const showSubmitDialog = ref(false);
const changeReason = ref('');

const initialRows: PriceRow[] = (props.ingredients || []).map((ingredient) => ({
    ingredient_id: ingredient.id,
    name: ingredient.name,
    sku: ingredient.sku || '',
    unit_symbol: ingredient.unit?.symbol || 'đv',
    original_cost: Number(ingredient.average_cost || 0),
    average_cost: Number(ingredient.average_cost || 0),
}));

const rows = ref<PriceRow[]>(JSON.parse(JSON.stringify(initialRows)));

const modifiedCount = computed(() => {
    return rows.value.filter((r) => r.average_cost !== r.original_cost).length;
});

const canEditPrices = computed(() => props.canProposePrices || isOwnerOrAdmin.value);

const changedRows = computed(() => rows.value.filter((row) => row.average_cost !== row.original_cost));

const totalAbsoluteDelta = computed(() =>
    changedRows.value.reduce((total, row) => total + Math.abs(row.average_cost - row.original_cost), 0),
);

const largestChangePercent = computed(() => {
    if (!changedRows.value.length) return 0;

    return Math.max(
        ...changedRows.value.map((row) =>
            row.original_cost > 0
                ? Math.abs((row.average_cost - row.original_cost) / row.original_cost) * 100
                : row.average_cost > 0
                  ? 100
                  : 0,
        ),
    );
});

const totalIngredients = computed(() => rows.value.length);

const averageCatalogCost = computed(() => {
    if (rows.value.length === 0) {
        return 0;
    }
    const sum = rows.value.reduce((acc, r) => acc + (Number(r.average_cost) || 0), 0);

    return sum / rows.value.length;
});

const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();

    return rows.value.filter((row) => {
        const matchesSearch = !q || `${row.name} ${row.sku}`.toLowerCase().includes(q);
        const matchesModified = !showOnlyModified.value || row.average_cost !== row.original_cost;

        return matchesSearch && matchesModified;
    });
});

const filteredHistory = computed(() => {
    const q = historySearch.value.trim().toLowerCase();

    return (props.priceHistory || []).filter((entry) => {
        const haystack = `${entry.ingredient_name} ${entry.ingredient_sku || ''} ${entry.changed_by} ${entry.reason || ''}`.toLowerCase();

        return !q || haystack.includes(q);
    });
});

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(value || 0));

const formatNumber = (value: number) =>
    new Intl.NumberFormat('vi-VN').format(Number(value || 0));

const resetChanges = () => {
    rows.value = JSON.parse(JSON.stringify(initialRows));
    toast.info('Đã hoàn tác toàn bộ thay đổi.');
};

const resetSingleRow = (row: PriceRow) => {
    row.average_cost = row.original_cost;
    toast.info(`Đã khôi phục giá gốc cho: ${row.name}`);
};

const applyQuickIncrease = (row: PriceRow, percent: number) => {
    row.average_cost = Math.round(row.average_cost * (1 + percent / 100));
};

const savePrices = () => {
    if (modifiedCount.value === 0) {
        toast.info('Không có thay đổi nào về đơn giá.');

        return;
    }

    changeReason.value = '';
    showSubmitDialog.value = true;
};

const submitPrices = async () => {
    if (changeReason.value.trim().length < 5) {
        toast.error('Vui lòng ghi rõ lý do thay đổi (tối thiểu 5 ký tự).');

        return;
    }

    isSaving.value = true;

    try {
        const payload = rows.value
            .filter((row) => row.average_cost !== row.original_cost)
            .map((row) => ({
                ingredient_id: row.ingredient_id,
                average_cost: Math.max(0, Number(row.average_cost || 0)),
            }));

        const endpoint = isOwnerOrAdmin.value
            ? '/api/warehouse/ingredient-prices'
            : '/api/warehouse/ingredient-prices/propose';

        const response = await axios.post(endpoint, {
            prices: payload,
            reason: changeReason.value.trim(),
        });

        toast.success(
            response.data.message ||
                (isOwnerOrAdmin.value
                    ? 'Đã cập nhật bảng giá nguyên liệu thành công.'
                    : 'Đã gửi đề xuất cập nhật đơn giá tới Chủ nhà hàng phê duyệt!')
        );

        if (isOwnerOrAdmin.value) {
            rows.value.forEach((r) => {
                r.original_cost = r.average_cost;
            });
        } else {
            rows.value = JSON.parse(JSON.stringify(initialRows));
        }
        showSubmitDialog.value = false;
        changeReason.value = '';
        await router.reload({ preserveScroll: true });
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Không thể lưu hoặc gửi đề xuất bảng giá.');
    } finally {
        isSaving.value = false;
    }
};

const refreshPage = () => {
    router.reload({ preserveScroll: true });
};
</script>

<template>
    <Head title="Bảng giá nguyên liệu Kho Tổng" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
        <!-- ── Top Header Bar ─────────────────────────────────────────────── -->
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="flex size-12 items-center justify-center rounded-2xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 shadow-inner dark:border-emerald-500/30 dark:bg-emerald-950/50 dark:text-emerald-400"
                >
                    <BadgeDollarSign class="size-6" />
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-white">
                            Bảng Giá Nguyên Liệu Kho Tổng
                        </h1>
                        <Badge
                            variant="outline"
                            class="border-emerald-500/30 bg-emerald-500/10 text-xs font-semibold text-emerald-700 dark:text-emerald-400"
                        >
                            Giá Vốn Chuẩn Toàn Chuỗi
                        </Badge>
                    </div>
                    <p class="text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                        Đơn giá chuẩn áp dụng cho tính chi phí xuất kho & cấp phát đến các chi nhánh toàn chuỗi.
                    </p>
                </div>
            </div>

            <!-- Quick Header Actions -->
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" as-child class="border-slate-200 text-xs dark:border-slate-800">
                    <Link href="/inventory/central-warehouse" class="gap-1.5">
                        <ArrowLeft class="size-3.5" />
                        <span>Tổng quan Kho</span>
                    </Link>
                </Button>

                <Button variant="outline" size="sm" @click="refreshPage" class="gap-1.5 border-slate-200 text-xs dark:border-slate-800">
                    <RefreshCw class="size-3.5" />
                    <span>Làm mới</span>
                </Button>

                <Button v-if="priceGovernance.pending_count > 0" variant="outline" size="sm" as-child class="gap-1.5 border-indigo-500/30 text-xs text-indigo-700 dark:text-indigo-300">
                    <Link href="/approvals?status=open">
                        <ClipboardList class="size-3.5" />
                        <span>Duyệt giá ({{ priceGovernance.pending_count }})</span>
                    </Link>
                </Button>

                <Button
                    v-if="modifiedCount > 0"
                    variant="outline"
                    size="sm"
                    @click="resetChanges"
                    class="gap-1.5 border-slate-200 text-xs text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800"
                >
                    <RotateCcw class="size-3.5" />
                    <span>Đặt lại ({{ modifiedCount }})</span>
                </Button>

                <Button
                    v-if="canEditPrices"
                    @click="savePrices"
                    :disabled="isSaving || modifiedCount === 0"
                    class="gap-1.5 bg-emerald-600 text-xs font-medium text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50"
                >
                    <component :is="isOwnerOrAdmin ? Save : Send" class="size-3.5" />
                    <span>
                        {{
                            isSaving
                                ? 'Đang lưu...'
                                : isOwnerOrAdmin
                                  ? `Lưu Bảng Giá (${modifiedCount})`
                                  : `Gửi Đề Xuất Duyệt (${modifiedCount})`
                        }}
                    </span>
                </Button>
            </div>
        </div>

        <!-- ── 4 KPI Summary Cards ────────────────────────────────────────── -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Card 1: Tổng Mặt Hàng -->
            <Card class="border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        Tổng Danh Mục
                    </CardTitle>
                    <div class="rounded-lg bg-emerald-500/10 p-1.5 text-emerald-600 dark:text-emerald-400">
                        <Boxes class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ formatNumber(totalIngredients) }}
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Nguyên liệu thuộc danh mục Kho Tổng
                    </p>
                </CardContent>
            </Card>

            <!-- Card 2: Đang Điều Chỉnh -->
            <Card
                class="border-slate-200 bg-white shadow-sm transition-colors dark:border-slate-800 dark:bg-slate-900"
                :class="{ 'border-amber-500/40 bg-amber-50/20 dark:border-amber-500/30 dark:bg-amber-950/10': modifiedCount > 0 }"
            >
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        Đang Chờ Cập Nhật
                    </CardTitle>
                    <div class="rounded-lg bg-amber-500/10 p-1.5 text-amber-600 dark:text-amber-400">
                        <BadgePercent class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">
                        {{ modifiedCount }}
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ modifiedCount > 0 ? 'Mặt hàng có biến động đơn giá' : 'Chưa có thay đổi nào' }}
                    </p>
                </CardContent>
            </Card>

            <!-- Card 3: Quyền Quản Trị Giá -->
            <Card class="border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        Quyền Hạn Tài Chính
                    </CardTitle>
                    <div
                        class="rounded-lg p-1.5"
                        :class="isOwnerOrAdmin ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' : 'bg-sky-500/10 text-sky-600 dark:text-sky-400'"
                    >
                        <ShieldCheck v-if="isOwnerOrAdmin" class="size-4" />
                        <ShieldAlert v-else class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-base font-bold text-slate-900 dark:text-white">
                        {{ isOwnerOrAdmin ? 'Cập Nhật Trực Tiếp' : 'Đề Xuất Duyệt' }}
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ isOwnerOrAdmin ? 'Chủ sở hữu hệ thống / Super Admin' : 'Trưởng kho đề xuất sang Owner' }}
                    </p>
                </CardContent>
            </Card>

            <!-- Card 4: Giá Vốn Trung Bình -->
            <Card class="border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        Đơn Giá Trung Bình
                    </CardTitle>
                    <div class="rounded-lg bg-sky-500/10 p-1.5 text-sky-600 dark:text-sky-400">
                        <TrendingUp class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ formatCurrency(averageCatalogCost) }}
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Bình quân trên toàn bộ danh mục
                    </p>
                </CardContent>
            </Card>
        </div>

        <WarehouseAiRecommendations :initial-ai="props.centralWarehouseAi" context="prices" :max="3" />

        <!-- ── Policy / Approval Notice Banner ────────────────────────────── -->
        <section class="grid gap-4 lg:grid-cols-3">
            <Card class="border-indigo-500/20 bg-indigo-500/5 shadow-sm dark:bg-indigo-950/10">
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                        <ClipboardList class="size-4 text-indigo-500" /> Đề xuất đang chờ duyệt
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-300">{{ priceGovernance.pending_count }}</div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Owner cần xem xét trước khi áp dụng.</p>
                    <Link href="/approvals?status=open" class="mt-3 inline-flex text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-300">Mở hàng đợi phê duyệt →</Link>
                </CardContent>
            </Card>
            <Card class="border-amber-500/20 bg-amber-500/5 shadow-sm dark:bg-amber-950/10">
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                        <AlertTriangle class="size-4 text-amber-500" /> Cần rà soát
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-end gap-4">
                        <div><div class="text-2xl font-bold text-amber-600 dark:text-amber-300">{{ priceGovernance.stale_count }}</div><p class="text-[11px] text-slate-500 dark:text-slate-400">chưa cập nhật quá 30 ngày</p></div>
                        <div><div class="text-2xl font-bold text-rose-600 dark:text-rose-300">{{ priceGovernance.large_change_count }}</div><p class="text-[11px] text-slate-500 dark:text-slate-400">biến động từ 10%+</p></div>
                    </div>
                </CardContent>
            </Card>
            <Card class="border-sky-500/20 bg-sky-500/5 shadow-sm dark:bg-sky-950/10">
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                        <Clock3 class="size-4 text-sky-500" /> Dấu vết giá vốn
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-sm font-bold text-slate-900 dark:text-white">{{ priceGovernance.last_updated_at || 'Chưa có lịch sử' }}</div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Lần thay đổi được ghi nhận gần nhất.</p>
                    <button type="button" class="mt-3 text-xs font-semibold text-sky-600 hover:underline dark:text-sky-300" @click="showHistory = true">Xem nhật ký giá →</button>
                </CardContent>
            </Card>
        </section>

        <Card v-if="pendingPriceUpdates.length" class="border-indigo-500/20 shadow-sm dark:border-indigo-500/30">
            <CardHeader class="border-b border-indigo-500/10 bg-indigo-500/5 p-4 sm:p-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle class="text-base font-bold text-slate-900 dark:text-white">Phiếu đề xuất giá đang mở</CardTitle>
                        <CardDescription class="mt-1 text-xs">Theo dõi các đề xuất chưa được áp dụng để tránh gửi trùng hoặc dùng nhầm giá cũ.</CardDescription>
                    </div>
                    <Link href="/approvals?status=open" class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-300">Mở trung tâm duyệt</Link>
                </div>
            </CardHeader>
            <CardContent class="grid gap-3 p-4 sm:p-5 lg:grid-cols-2">
                <div v-for="proposal in pendingPriceUpdates.slice(0, 4)" :key="proposal.id" class="rounded-xl border border-indigo-500/15 bg-indigo-500/5 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-mono text-[11px] font-bold text-indigo-600 dark:text-indigo-300">APR-{{ String(proposal.id).padStart(5, '0') }}</span>
                        <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ proposal.created_at }}</span>
                    </div>
                    <p class="mt-2 text-xs font-semibold text-slate-900 dark:text-white">{{ proposal.items.length }} nguyên liệu · {{ proposal.requester_name }}</p>
                    <p v-if="proposal.reason" class="mt-1 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ proposal.reason }}</p>
                    <p class="mt-2 text-[11px] text-indigo-600 dark:text-indigo-300">{{ proposal.status === 'escalated' ? 'Đã chuyển Owner xử lý' : 'Đang chờ phê duyệt' }}</p>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="!isOwnerOrAdmin"
            class="flex items-start gap-3.5 rounded-2xl border border-sky-500/20 bg-sky-500/5 p-4 text-xs text-slate-700 sm:p-5 dark:border-sky-500/30 dark:bg-sky-950/20 dark:text-slate-300"
        >
            <div class="rounded-xl bg-sky-500/10 p-2 text-sky-600 dark:text-sky-400">
                <Info class="size-5 shrink-0" />
            </div>
            <div class="space-y-1">
                <div class="font-semibold text-slate-900 dark:text-white">
                    Quy Trình Kiểm Soát Giá Vốn Chuỗi:
                </div>
                <p class="leading-relaxed text-slate-600 dark:text-slate-400">
                    Bảng giá nguyên liệu Kho Tổng là căn cứ tài chính tính chi phí xuất kho và báo cáo COGS toàn hệ thống.
                    Khi Trưởng kho chỉnh sửa và nhấn <strong>"Gửi Đề Xuất Duyệt"</strong>, hệ thống sẽ tự động tạo phiếu đề xuất gửi Chủ nhà hàng (Owner) xét duyệt trước khi áp dụng chính thức.
                </p>
            </div>
        </div>

        <div
            v-else
            class="flex items-start gap-3.5 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-xs text-slate-700 sm:p-5 dark:border-emerald-500/30 dark:bg-emerald-950/20 dark:text-slate-300"
        >
            <div class="rounded-xl bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                <CheckCircle2 class="size-5 shrink-0" />
            </div>
            <div class="space-y-1">
                <div class="font-semibold text-slate-900 dark:text-white">
                    Chế Độ Quản Trị Trực Tiếp (Owner / Super Admin)
                </div>
                <p class="leading-relaxed text-slate-600 dark:text-slate-400">
                    Bạn có đặc quyền cập nhật giá vốn nguyên liệu Kho Tổng áp dụng tức thì cho toàn bộ các giao dịch xuất/nhập/cấp phát kho trong chuỗi.
                </p>
            </div>
        </div>

        <!-- ── Main Price Management Table Card ───────────────────────────── -->
        <Card class="border-slate-200 shadow-sm dark:border-slate-800">
            <CardHeader class="border-b border-slate-100 bg-slate-50/50 p-4 sm:p-6 dark:border-slate-800 dark:bg-slate-900/50">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <CardTitle class="text-base font-bold text-slate-900 dark:text-white">
                                Danh Mục Giá Nguyên Liệu
                            </CardTitle>
                            <Badge variant="secondary" class="font-mono text-xs">
                                {{ filteredRows.length }} / {{ totalIngredients }}
                            </Badge>
                        </div>
                        <CardDescription class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Nhập giá mới vào ô tương ứng hoặc sử dụng các nút tăng/giảm nhanh để cập nhật.
                        </CardDescription>
                    </div>

                    <!-- Search and Filters -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="showOnlyModified = !showOnlyModified"
                            class="gap-1.5 text-xs"
                            :class="showOnlyModified ? 'border-amber-500 bg-amber-500/10 text-amber-700 dark:text-amber-400' : 'border-slate-200 dark:border-slate-800'"
                        >
                            <Filter class="size-3.5" />
                            <span>Đã sửa ({{ modifiedCount }})</span>
                        </Button>

                        <div class="relative w-full sm:w-64">
                            <Search class="absolute top-2.5 left-3 size-4 text-slate-400" />
                            <Input
                                v-model="search"
                                placeholder="Tìm kiếm nguyên liệu, SKU..."
                                class="h-9 pl-9 text-xs"
                            />
                        </div>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-100/75 text-[11px] font-semibold text-slate-600 uppercase tracking-wider dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-400">
                            <tr>
                                <th class="py-3 px-4">Nguyên Liệu</th>
                                <th class="py-3 px-4">Mã SKU</th>
                                <th class="py-3 px-4 text-center">ĐVT</th>
                                <th class="py-3 px-4 text-right">Đơn Giá Hiện Tại</th>
                                <th class="py-3 px-4 text-right">Đơn Giá Mới / Đề Xuất</th>
                                <th class="py-3 px-4 text-center">Biến Động</th>
                                <th class="py-3 px-4 text-center">Thao Tác</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <!-- Empty State -->
                            <tr v-if="filteredRows.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <div class="rounded-full bg-slate-100 p-3 dark:bg-slate-800">
                                            <Search class="size-5 text-slate-400" />
                                        </div>
                                        <div class="font-medium">Không tìm thấy nguyên liệu nào</div>
                                        <div class="text-xs text-slate-400">
                                            Thử thay đổi từ khóa tìm kiếm hoặc bỏ bộ lọc "Đã sửa".
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- Row Items -->
                            <tr
                                v-for="row in filteredRows"
                                :key="row.ingredient_id"
                                class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                                :class="{ 'bg-amber-500/5 dark:bg-amber-500/10': row.average_cost !== row.original_cost }"
                            >
                                <!-- Name -->
                                <td class="py-3.5 px-4 font-medium text-slate-900 dark:text-white">
                                    <div class="flex items-center gap-2">
                                        <span>{{ row.name }}</span>
                                        <Badge
                                            v-if="row.average_cost !== row.original_cost"
                                            variant="outline"
                                            class="border-amber-500/30 bg-amber-500/10 text-[10px] text-amber-700 dark:text-amber-400"
                                        >
                                            Đã đổi
                                        </Badge>
                                    </div>
                                </td>

                                <!-- SKU -->
                                <td class="py-3.5 px-4 font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ row.sku || '—' }}
                                </td>

                                <!-- Unit -->
                                <td class="py-3.5 px-4 text-center">
                                    <Badge variant="secondary" class="text-[11px] font-normal">
                                        {{ row.unit_symbol }}
                                    </Badge>
                                </td>

                                <!-- Current Cost -->
                                <td class="py-3.5 px-4 text-right font-medium text-slate-600 dark:text-slate-300">
                                    {{ formatCurrency(row.original_cost) }}
                                </td>

                                <!-- New / Proposed Cost Input -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <div class="relative max-w-[160px]">
                                            <Input
                                                v-if="canEditPrices"
                                                v-model.number="row.average_cost"
                                                type="number"
                                                min="0"
                                                step="500"
                                                class="h-8 pr-7 text-right text-xs font-semibold"
                                                :class="
                                                    row.average_cost !== row.original_cost
                                                        ? 'border-amber-500 font-bold text-amber-700 ring-1 ring-amber-500/30 dark:text-amber-400'
                                                        : 'text-slate-800 dark:text-slate-200'
                                                "
                                            />
                                            <span
                                                v-if="canEditPrices"
                                                class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-[10px] text-slate-400"
                                            >
                                                đ
                                            </span>
                                            <span v-else class="block text-right font-semibold text-slate-900 dark:text-white">
                                                {{ formatCurrency(row.average_cost) }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Variance / % Change -->
                                <td class="py-3.5 px-4 text-center">
                                    <div v-if="row.average_cost !== row.original_cost" class="inline-flex items-center gap-1">
                                        <Badge
                                            v-if="row.average_cost > row.original_cost"
                                            class="gap-1 border-rose-500/30 bg-rose-500/10 text-[10px] font-medium text-rose-700 dark:text-rose-400"
                                        >
                                            <TrendingUp class="size-3" />
                                            +{{
                                                row.original_cost > 0
                                                    ? Math.round(((row.average_cost - row.original_cost) / row.original_cost) * 100)
                                                    : 100
                                            }}%
                                        </Badge>
                                        <Badge
                                            v-else
                                            class="gap-1 border-emerald-500/30 bg-emerald-500/10 text-[10px] font-medium text-emerald-700 dark:text-emerald-400"
                                        >
                                            <TrendingDown class="size-3" />
                                            {{
                                                row.original_cost > 0
                                                    ? Math.round(((row.average_cost - row.original_cost) / row.original_cost) * 100)
                                                    : -100
                                            }}%
                                        </Badge>
                                    </div>
                                    <span v-else class="text-slate-300 dark:text-slate-600">—</span>
                                </td>

                                <!-- Row Action (Quick shortcuts & Reset) -->
                                <td class="py-3.5 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Quick adjustments -->
                                        <template v-if="canEditPrices">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                title="Tăng 5%"
                                                @click="applyQuickIncrease(row, 5)"
                                                class="h-7 px-1.5 text-[10px] text-slate-500 hover:text-slate-900 dark:hover:text-white"
                                            >
                                                +5%
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                title="Tăng 10%"
                                                @click="applyQuickIncrease(row, 10)"
                                                class="h-7 px-1.5 text-[10px] text-slate-500 hover:text-slate-900 dark:hover:text-white"
                                            >
                                                +10%
                                            </Button>
                                        </template>

                                        <!-- Row Reset -->
                                        <Button
                                            v-if="row.average_cost !== row.original_cost"
                                            variant="ghost"
                                            size="sm"
                                            title="Khôi phục giá ban đầu"
                                            @click="resetSingleRow(row)"
                                            class="h-7 w-7 p-0 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400"
                                        >
                                            <RotateCcw class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- ── Sticky Floating Bottom Bar (appears when changes exist) ──────── -->
        <Card class="border-slate-200 shadow-sm dark:border-slate-800">
            <CardHeader class="p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <History class="size-4 text-slate-500" />
                        <div>
                            <CardTitle class="text-base font-bold text-slate-900 dark:text-white">Nhật ký thay đổi đơn giá</CardTitle>
                            <CardDescription class="mt-1 text-xs">Dùng để truy vết ai thay đổi, lý do và người phê duyệt.</CardDescription>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div v-if="showHistory" class="relative w-full sm:w-64">
                            <Search class="absolute top-2.5 left-3 size-4 text-slate-400" />
                            <Input v-model="historySearch" placeholder="Tìm nguyên liệu, người sửa..." class="h-9 pl-9 text-xs" />
                        </div>
                        <Button variant="outline" size="sm" class="gap-1.5 text-xs" @click="showHistory = !showHistory">
                            <History class="size-3.5" /> {{ showHistory ? 'Thu gọn' : 'Xem nhật ký' }}
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent v-if="showHistory" class="border-t border-slate-100 p-0 dark:border-slate-800">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-100/75 text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Thời điểm</th>
                                <th class="px-4 py-3">Nguyên liệu</th>
                                <th class="px-4 py-3 text-right">Từ</th>
                                <th class="px-4 py-3 text-right">Đến</th>
                                <th class="px-4 py-3 text-center">Biến động</th>
                                <th class="px-4 py-3">Người thực hiện</th>
                                <th class="px-4 py-3">Lý do</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="filteredHistory.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-xs text-slate-500">Chưa có bản ghi phù hợp.</td>
                            </tr>
                            <tr v-for="entry in filteredHistory" :key="entry.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">{{ entry.created_at || '—' }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ entry.ingredient_name }}<span v-if="entry.ingredient_sku" class="ml-2 font-mono text-[10px] font-normal text-slate-500">{{ entry.ingredient_sku }}</span></td>
                                <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ formatCurrency(entry.old_price) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-white">{{ formatCurrency(entry.new_price) }}</td>
                                <td class="px-4 py-3 text-center"><Badge :class="entry.change_percent >= 0 ? 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'">{{ entry.change_percent >= 0 ? '+' : '' }}{{ entry.change_percent.toFixed(1) }}%</Badge></td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ entry.changed_by }}<span v-if="entry.approved_by" class="block text-[10px] text-slate-400">Duyệt: {{ entry.approved_by }}</span></td>
                                <td class="max-w-[280px] px-4 py-3 text-slate-500 dark:text-slate-400">{{ entry.reason || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="modifiedCount > 0"
            class="sticky bottom-6 z-20 flex flex-col items-center justify-between gap-3 rounded-2xl border border-amber-500/30 bg-slate-900/95 p-4 text-white shadow-2xl backdrop-blur-md sm:flex-row sm:px-6 dark:border-amber-500/40 dark:bg-slate-950/95"
        >
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-500/20 text-amber-400">
                    <BadgePercent class="size-5" />
                </div>
                <div>
                    <div class="text-sm font-semibold">
                        Đang có {{ modifiedCount }} nguyên liệu được thay đổi đơn giá
                    </div>
                    <div class="text-xs text-slate-400">
                        {{
                            isOwnerOrAdmin
                                ? 'Nhấn "Lưu Bảng Giá" để áp dụng chính thức cho toàn hệ thống.'
                                : 'Nhấn "Gửi Đề Xuất Duyệt" để chuyển phiếu sang Chủ nhà hàng xét duyệt.'
                        }}
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    @click="resetChanges"
                    class="border-slate-700 bg-slate-800 text-xs text-slate-200 hover:bg-slate-700"
                >
                    <RotateCcw class="size-3.5 mr-1.5" />
                    Hủy thay đổi
                </Button>
                <Button
                    size="sm"
                    @click="savePrices"
                    :disabled="isSaving"
                    class="bg-emerald-600 text-xs font-semibold text-white shadow-md hover:bg-emerald-500 disabled:opacity-50"
                >
                    <component :is="isOwnerOrAdmin ? Save : Send" class="size-3.5 mr-1.5" />
                    {{
                        isSaving
                            ? 'Đang gửi...'
                            : isOwnerOrAdmin
                              ? `Lưu Bảng Giá (${modifiedCount})`
                              : `Gửi Đề Xuất Duyệt (${modifiedCount})`
                    }}
                </Button>
            </div>
        </div>

        <Teleport to="body">
        <div
            v-if="showSubmitDialog"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
            @click.self="showSubmitDialog = false"
        >
            <div class="w-full max-w-xl rounded-2xl border border-slate-700 bg-slate-950 p-5 text-white shadow-2xl sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-400">Kiểm soát thay đổi</p>
                        <h2 class="mt-1 text-xl font-bold">{{ isOwnerOrAdmin ? 'Xác nhận áp dụng bảng giá' : 'Gửi đề xuất thay đổi giá' }}</h2>
                        <p class="mt-1 text-xs text-slate-400">{{ isOwnerOrAdmin ? 'Thay đổi sẽ tác động ngay đến giá vốn xuất kho và báo cáo COGS toàn chuỗi.' : 'Đề xuất sẽ chờ Owner phê duyệt trước khi được áp dụng.' }}</p>
                    </div>
                    <Button variant="ghost" size="icon" class="text-slate-400 hover:text-white" @click="showSubmitDialog = false"><X class="size-4" /></Button>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl bg-white/5 p-3"><p class="text-[10px] uppercase tracking-wider text-slate-400">Mặt hàng</p><p class="mt-1 text-lg font-bold">{{ modifiedCount }}</p></div>
                    <div class="rounded-xl bg-white/5 p-3"><p class="text-[10px] uppercase tracking-wider text-slate-400">Tăng/giảm tuyệt đối</p><p class="mt-1 text-sm font-bold">{{ formatCurrency(totalAbsoluteDelta) }}</p></div>
                    <div class="rounded-xl bg-white/5 p-3"><p class="text-[10px] uppercase tracking-wider text-slate-400">Biến động lớn nhất</p><p class="mt-1 text-lg font-bold">{{ largestChangePercent.toFixed(1) }}%</p></div>
                    <div class="rounded-xl bg-amber-500/10 p-3"><p class="text-[10px] uppercase tracking-wider text-amber-300">Trạng thái</p><p class="mt-1 text-sm font-bold text-amber-200">{{ isOwnerOrAdmin ? 'Áp dụng ngay' : 'Chờ duyệt' }}</p></div>
                </div>

                <div v-if="largestChangePercent >= 10" class="mt-4 flex items-start gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-100">
                    <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-300" />
                    <span>Có mặt hàng biến động từ 10% trở lên. Hãy ghi rõ căn cứ như giá nhập mới, thay đổi nhà cung cấp hoặc điều chỉnh quy cách.</span>
                </div>

                <div class="mt-4">
                    <label class="text-xs font-semibold text-slate-200">Lý do / căn cứ thay đổi <span class="text-rose-300">*</span></label>
                    <textarea
                        v-model="changeReason"
                        rows="4"
                        required
                        class="mt-1.5 w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white outline-none transition focus:border-emerald-400"
                        placeholder="VD: Theo hóa đơn PO-2026-0812, nhà cung cấp tăng giá 8% từ ngày..."
                    />
                    <p class="mt-1 text-[11px] text-slate-400">Nội dung này được lưu vào nhật ký giá và đi cùng phiếu phê duyệt.</p>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <Button type="button" variant="outline" class="border-slate-700 bg-transparent text-slate-200 hover:bg-slate-800" @click="showSubmitDialog = false">Quay lại</Button>
                    <Button type="button" :disabled="isSaving" class="gap-1.5 bg-emerald-600 font-bold text-white hover:bg-emerald-500" @click="submitPrices">
                        <component :is="isOwnerOrAdmin ? Save : Send" class="size-4" /> {{ isOwnerOrAdmin ? 'Áp dụng bảng giá' : 'Gửi đề xuất' }}
                    </Button>
                </div>
            </div>
        </div>
        </Teleport>
    </div>
</template>
