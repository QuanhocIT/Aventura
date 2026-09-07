<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ScrollText,
    ArrowLeftRight,
    PackageCheck,
    FileCheck2,
    Search,
    Printer,
    CheckCircle2,
    AlertTriangle,
    X,
    Eye,
    TrendingUp,
    ShieldAlert,
    Clock,
    RefreshCw,
    ShoppingCart,
    ClipboardCheck,
    Warehouse,
    UtensilsCrossed,
    Wallet,
    Utensils,
    Heart,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface DocumentItem {
    id: string;
    raw_id: number | string;
    type: 'shift_closing' | 'warehouse_closing' | 'stock_transfer' | 'supply_request' | 'receiving_report' | 'purchase_order' | 'inventory_count' | 'payslip';
    type_label: string;
    code: string;
    title: string;
    branch_id?: number | null;
    branch_name: string;
    created_by_name: string;
    created_at: string;
    date_formatted: string;
    total_amount: number;
    status: string;
    status_label: {
        label: string;
        color: string;
    };
    has_discrepancy: boolean;
    discrepancy_note?: string | null;
    payload: any;
}

interface Branch {
    id: number;
    name: string;
    code: string;
    address?: string;
    phone?: string;
}

const props = defineProps<{
    documents: DocumentItem[];
    branches: Branch[];
    kpi: {
        total_documents: number;
        pending_review: number;
        discrepancies: number;
        total_value: number;
    };
    filters: {
        type: string;
        branch_id?: string | number | null;
        status: string;
        search: string;
        date_preset: string;
        start_date?: string | null;
        end_date?: string | null;
    };
}>();

// Reactive Filters
const currentType = ref(props.filters.type || 'all');
const currentBranch = ref(props.filters.branch_id ? String(props.filters.branch_id) : '');
const currentStatus = ref(props.filters.status || 'all');
const currentSearch = ref(props.filters.search || '');
const currentDatePreset = ref(props.filters.date_preset || 'this_month');

// Modal State
const isViewerOpen = ref(false);
const activeDocument = ref<DocumentItem | null>(null);
const acknowledgeNote = ref('');
const isSubmitting = ref(false);

const applyFilters = () => {
    router.get(
        '/enterprise/documents',
        {
            type: currentType.value,
            branch_id: currentBranch.value || undefined,
            status: currentStatus.value,
            search: currentSearch.value || undefined,
            date_preset: currentDatePreset.value,
        },
        { preserveState: true, replace: true },
    );
};

const resetFilters = () => {
    currentType.value = 'all';
    currentBranch.value = '';
    currentStatus.value = 'all';
    currentSearch.value = '';
    currentDatePreset.value = 'this_month';
    applyFilters();
};

const viewDocument = (doc: DocumentItem) => {
    activeDocument.value = doc;
    acknowledgeNote.value = '';
    isViewerOpen.value = true;
};

const closeViewer = () => {
    isViewerOpen.value = false;
    activeDocument.value = null;
};

const printDocument = () => {
    window.print();
};

const acknowledgeDocument = () => {
    if (!activeDocument.value) {
        return;
    }

    isSubmitting.value = true;
    router.post(
        '/enterprise/documents/acknowledge',
        {
            document_id: activeDocument.value.id,
            note: acknowledgeNote.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
                closeViewer();
            },
        },
    );
};

// Format Helpers
const formatCurrency = (val: number | string | null | undefined): string => {
    const num = Number(val || 0);

    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num);
};

const formatQuantity = (val: number | string | null | undefined): string => {
    const num = Number(val || 0);

    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(num);
};

const formatMoney = (val: number | string | null | undefined): string => {
    const num = Math.round(Number(val || 0));

    return new Intl.NumberFormat('en-US').format(num);
};

const currentDate = new Date();
const todayDay = currentDate.getDate() < 10 ? `0${currentDate.getDate()}` : `${currentDate.getDate()}`;
const todayMonth = currentDate.getMonth() + 1 < 10 ? `0${currentDate.getMonth() + 1}` : `${currentDate.getMonth() + 1}`;
const todayYear = `${currentDate.getFullYear()}`.slice(-2);

const getTimeFormula = (p: any): string => {
    if (!p) {
        return '';
    }

    if (p.compensation_type === 'hourly') {
        return `${p.breakdown?.regular_hours ?? 0}h × ${formatMoney(p.pay_rate)} đ/h`;
    }

    if (p.compensation_type === 'shift') {
        return `${p.breakdown?.completed_shifts_count ?? p.actual_work_days} ca × ${formatMoney(p.pay_rate)} đ/ca`;
    }

    const days = (p.actual_work_days || 0) + (p.paid_leave_days || 0);
    const standard = p.standard_days || 26;
    const base = p.contract_base_salary || p.base_salary || 0;

    return `${days} / ${standard} ngày × ${formatMoney(base)}`;
};

const calculateTotalIncome = (p: any): number => {
    if (!p) {
        return 0;
    }

    const base = Number(p.time_based_salary ?? p.base_salary ?? 0);
    const allow = Number(p.allowances ?? p.allowance_amount ?? 0);
    const ot = Number(p.overtime_salary ?? p.overtime_amount ?? 0);
    const night = Number(p.night_shift_amount ?? 0);
    const bonus = Number(p.bonuses ?? p.bonus_amount ?? p.kpi_salary ?? 0);

    return base + allow + ot + night + bonus;
};

const calculateTotalDeduction = (p: any): number => {
    if (!p) {
        return 0;
    }

    const ins = Number(p.insurance_deduction ?? 0);
    const tax = Number(p.tax_deduction ?? 0);
    const other = Number(p.other_deductions ?? p.deduction_amount ?? 0);
    const adv = Number(p.advance_payment ?? p.advance_amount ?? 0);
    const total = ins + tax + other + adv;

    return total > 0 ? total : Number(p.deduction_amount ?? 0) + Number(p.advance_amount ?? 0);
};

const typeTabs = [
    { key: 'all', label: 'Tất cả phiếu' },
    { key: 'shift_closing', label: 'Phiếu Chốt Ca', icon: ScrollText },
    { key: 'warehouse_closing', label: 'Phiếu Chốt Kho', icon: Warehouse },
    { key: 'stock_transfer', label: 'Phiếu Điều Chuyển', icon: ArrowLeftRight },
    { key: 'supply_request', label: 'Phiếu Xuất Kho Tổng', icon: PackageCheck },
    { key: 'receiving_report', label: 'Biên Bản Đối Soát', icon: FileCheck2 },
    { key: 'purchase_order', label: 'Phiếu Đặt Hàng NCC', icon: ShoppingCart },
    { key: 'inventory_count', label: 'Phiếu Kiểm Kê', icon: ClipboardCheck },
    { key: 'payslip', label: 'Phiếu Lương', icon: Wallet },
];

const getTypeIcon = (type: string) => {
    switch (type) {
        case 'shift_closing': return ScrollText;
        case 'warehouse_closing': return Warehouse;
        case 'stock_transfer': return ArrowLeftRight;
        case 'supply_request': return PackageCheck;
        case 'receiving_report': return FileCheck2;
        case 'purchase_order': return ShoppingCart;
        case 'inventory_count': return ClipboardCheck;
        case 'payslip': return Wallet;
        default: return ScrollText;
    }
};

const getTypeBadgeColor = (type: string) => {
    switch (type) {
        case 'shift_closing': return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20';
        case 'warehouse_closing': return 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20';
        case 'stock_transfer': return 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20';
        case 'supply_request': return 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20';
        case 'receiving_report': return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20';
        case 'purchase_order': return 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20';
        case 'inventory_count': return 'bg-teal-500/10 text-teal-600 dark:text-teal-400 border-teal-500/20';
        case 'payslip': return 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20';
        default: return 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20';
    }
};
</script>

<template>
    <Head title="Trung Tâm Chứng Từ & Phiếu Doanh Nghiệp" />

    <div class="w-full space-y-6 p-4 md:p-6 lg:p-8">
            <!-- Header Title -->
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <ScrollText class="size-5" />
                        </div>
                        <div>
                            <h1 class="text-xl font-black tracking-tight text-foreground md:text-2xl">
                                Trung Tâm Chứng Từ & Phiếu Doanh Nghiệp
                            </h1>
                            <p class="text-xs text-muted-foreground md:text-sm">
                                Nơi tiếp nhận, kiểm tra, phê duyệt và lưu trữ toàn bộ phiếu chốt ca, điều chuyển, xuất kho và biên bản nghiệp vụ.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        @click="applyFilters"
                        variant="outline"
                        size="sm"
                        class="gap-1.5 rounded-xl border-border hover:bg-accent text-xs font-semibold"
                    >
                        <RefreshCw class="size-3.5" />
                        Làm mới
                    </Button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Card class="rounded-2xl border-border shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                            Tổng Chứng Từ
                        </CardTitle>
                        <ScrollText class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-black text-foreground">{{ props.kpi.total_documents }}</div>
                        <p class="mt-1 text-[11px] text-muted-foreground">Đã phát sinh trong kỳ</p>
                    </CardContent>
                </Card>

                <Card class="rounded-2xl border-border shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                            Chờ Xử Lý / Duyệt
                        </CardTitle>
                        <Clock class="size-4 text-amber-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-black text-amber-600 dark:text-amber-400">
                            {{ props.kpi.pending_review }}
                        </div>
                        <p class="mt-1 text-[11px] text-muted-foreground">Cần Chủ DN / QL xác nhận</p>
                    </CardContent>
                </Card>

                <Card class="rounded-2xl border-border shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                            Có Sai Lệch / Cảnh Báo
                        </CardTitle>
                        <ShieldAlert class="size-4 text-rose-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-black text-rose-600 dark:text-rose-400">
                            {{ props.kpi.discrepancies }}
                        </div>
                        <p class="mt-1 text-[11px] text-muted-foreground">Lệch tiền chốt ca / thiếu hỏng hàng</p>
                    </CardContent>
                </Card>

                <Card class="rounded-2xl border-border shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                            Tổng Giá Trị Giao Dịch
                        </CardTitle>
                        <TrendingUp class="size-4 text-emerald-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-xl font-black text-emerald-600 dark:text-emerald-400 truncate">
                            {{ formatCurrency(props.kpi.total_value) }}
                        </div>
                        <p class="mt-1 text-[11px] text-muted-foreground">Giá trị hàng hóa & doanh thu</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters & Types Nav -->
            <div class="space-y-3">
                <!-- Type Tabs -->
                <div class="flex flex-wrap items-center gap-1.5 border-b border-border pb-3">
                    <button
                        v-for="tab in typeTabs"
                        :key="tab.key"
                        @click="currentType = tab.key; applyFilters()"
                        :class="[
                            'flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-bold transition',
                            currentType === tab.key
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground',
                        ]"
                    >
                        <component :is="tab.icon || ScrollText" class="size-3.5" />
                        <span>{{ tab.label }}</span>
                    </button>
                </div>

                <!-- Secondary Filter Bar -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Search Input -->
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="currentSearch"
                            @keyup.enter="applyFilters"
                            placeholder="Tìm số phiếu, người lập, địa điểm..."
                            class="h-9 rounded-xl pl-9 text-xs"
                        />
                    </div>

                    <!-- Branch Select -->
                    <div>
                        <select
                            v-model="currentBranch"
                            @change="applyFilters"
                            class="h-9 w-full rounded-xl border border-input bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        >
                            <option value="">Tất cả Chi nhánh</option>
                            <option v-for="b in props.branches" :key="b.id" :value="String(b.id)">
                                {{ b.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Status Select -->
                    <div>
                        <select
                            v-model="currentStatus"
                            @change="applyFilters"
                            class="h-9 w-full rounded-xl border border-input bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        >
                            <option value="all">Tất cả Trạng thái</option>
                            <option value="pending">Chờ duyệt / Chờ xử lý</option>
                            <option value="completed">Đã hoàn tất / Đã duyệt</option>
                            <option value="discrepancy">Có sai lệch / Cảnh báo</option>
                        </select>
                    </div>

                    <!-- Date Preset Select -->
                    <div class="flex items-center gap-2">
                        <select
                            v-model="currentDatePreset"
                            @change="applyFilters"
                            class="h-9 w-full rounded-xl border border-input bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        >
                            <option value="today">Hôm nay</option>
                            <option value="yesterday">Hôm qua</option>
                            <option value="7_days">7 ngày qua</option>
                            <option value="this_month">Tháng này</option>
                            <option value="last_month">Tháng trước</option>
                        </select>

                        <Button
                            v-if="currentType !== 'all' || currentBranch || currentStatus !== 'all' || currentSearch"
                            @click="resetFilters"
                            variant="ghost"
                            size="sm"
                            class="h-9 shrink-0 px-2.5 text-xs text-muted-foreground hover:text-foreground"
                            title="Xóa bộ lọc"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Documents Table -->
            <Card class="rounded-2xl border-border shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-border bg-muted/40 font-bold uppercase tracking-wider text-muted-foreground">
                                <th class="p-3.5 pl-5">Mã Phiếu / Chứng Từ</th>
                                <th class="p-3.5">Loại Phiếu</th>
                                <th class="p-3.5">Đơn Vị / Chi Nhánh</th>
                                <th class="p-3.5">Người Lập</th>
                                <th class="p-3.5">Thời Gian</th>
                                <th class="p-3.5 text-right">Tổng Tiền / Giá Trị</th>
                                <th class="p-3.5 text-center">Trạng Thái</th>
                                <th class="p-3.5 pr-5 text-right">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="doc in props.documents"
                                :key="doc.id"
                                class="hover:bg-muted/30 transition group"
                            >
                                <td class="p-3.5 pl-5 font-mono font-bold text-foreground">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-primary">{{ doc.code }}</span>
                                        <span
                                            v-if="doc.has_discrepancy"
                                            class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-black bg-rose-500/10 text-rose-600 dark:text-rose-400"
                                            title="Phát hiện sai lệch"
                                        >
                                            <AlertTriangle class="size-2.5 mr-0.5" /> Lệch
                                        </span>
                                    </div>
                                    <p class="text-[10px] font-sans font-normal text-muted-foreground truncate max-w-[200px]">
                                        {{ doc.title }}
                                    </p>
                                </td>

                                <td class="p-3.5">
                                    <Badge
                                        variant="outline"
                                        :class="['gap-1 rounded-lg px-2 py-0.5 text-[10px] font-bold border', getTypeBadgeColor(doc.type)]"
                                    >
                                        <component :is="getTypeIcon(doc.type)" class="size-3" />
                                        {{ doc.type_label }}
                                    </Badge>
                                </td>

                                <td class="p-3.5 font-medium text-foreground">
                                    {{ doc.branch_name }}
                                </td>

                                <td class="p-3.5 text-muted-foreground">
                                    {{ doc.created_by_name }}
                                </td>

                                <td class="p-3.5 text-muted-foreground whitespace-nowrap">
                                    {{ doc.date_formatted }}
                                </td>

                                <td class="p-3.5 text-right font-mono font-bold text-foreground whitespace-nowrap">
                                    {{ doc.total_amount > 0 ? formatCurrency(doc.total_amount) : '—' }}
                                </td>

                                <td class="p-3.5 text-center">
                                    <Badge
                                        variant="outline"
                                        :class="['rounded-lg px-2 py-0.5 text-[10px] font-bold border', doc.status_label.color]"
                                    >
                                        {{ doc.status_label.label }}
                                    </Badge>
                                </td>

                                <td class="p-3.5 pr-5 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <Button
                                            @click="viewDocument(doc)"
                                            size="sm"
                                            variant="outline"
                                            class="gap-1 rounded-xl border-border px-3 text-xs font-bold hover:bg-accent text-primary"
                                        >
                                            <Eye class="size-3.5" />
                                            Xem & In Phiếu
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.documents.length === 0">
                                <td colspan="8" class="p-12 text-center text-muted-foreground">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <ScrollText class="size-10 text-muted-foreground/40" />
                                        <p class="text-sm font-semibold">Chưa có chứng từ nào phù hợp với bộ lọc</p>
                                        <p class="text-xs text-muted-foreground">Hãy thay đổi khoảng thời gian hoặc bỏ bớt điều kiện lọc.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <!-- Universal A4 Document Viewer Modal -->
        <Teleport to="body">
            <div
                v-if="isViewerOpen && activeDocument"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-2 backdrop-blur-sm sm:p-4 print:p-0 print:bg-white"
            >
                <div
                    class="relative flex max-h-[95vh] w-full max-w-4xl flex-col rounded-2xl border border-border bg-card shadow-2xl overflow-hidden print:max-h-none print:w-full print:border-none print:shadow-none print:rounded-none"
                >
                    <!-- Modal Header (Hidden on Print) -->
                    <div class="flex items-center justify-between border-b border-border bg-muted/40 p-4 px-6 print:hidden">
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <component :is="getTypeIcon(activeDocument.type)" class="size-5" />
                            </div>
                            <div>
                                <h3 class="font-black text-foreground">
                                    {{ activeDocument.title }}
                                </h3>
                                <p class="text-xs font-mono text-muted-foreground">
                                    Mã phiếu: <strong class="text-foreground">{{ activeDocument.code }}</strong>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                @click="printDocument"
                                size="sm"
                                class="gap-1.5 rounded-xl bg-slate-900 px-4 text-xs font-bold text-white shadow-sm hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white"
                            >
                                <Printer class="size-4" />
                                In Phiếu (A4)
                            </Button>

                            <button
                                @click="closeViewer"
                                class="rounded-xl p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground transition"
                            >
                                <X class="size-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Modal Body (A4 Paper Document) -->
                    <div class="flex-1 overflow-y-auto p-4 sm:p-6 bg-slate-100 dark:bg-slate-950/50 print:p-0 print:bg-white">
                        <div
                            id="a4-document-sheet"
                            class="mx-auto max-w-[210mm] min-h-[297mm] bg-white text-black p-8 sm:p-10 shadow-lg print:shadow-none print:m-0 print:p-0 font-serif leading-relaxed"
                            style="font-family: 'Times New Roman', Times, serif;"
                        >
                            <!-- 1. MẪU: PHIẾU CHỐT CA (Matching User Image 2) -->
                            <template v-if="activeDocument.type === 'shift_closing'">
                                <!-- Header -->
                                <div class="flex items-start justify-between border-b-2 border-black pb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-12 items-center justify-center rounded-full border-2 border-black bg-neutral-100 font-bold">
                                            <UtensilsCrossed class="size-6 text-black" />
                                        </div>
                                        <div>
                                            <h4 class="font-black text-sm uppercase tracking-wider text-black">SAI GON DINER / AVENTURA</h4>
                                            <p class="text-[10px] text-neutral-800">Chuỗi nhà hàng Saigon Diner - Chi nhánh chính</p>
                                            <p class="text-[9px] text-neutral-600">Hotline: Chưa cập nhật</p>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <h2 class="text-lg font-black uppercase text-black">PHIẾU CHỐT CA</h2>
                                        <p class="mt-0.5 inline-block border border-black px-2 py-0.5 font-mono text-xs font-bold">
                                            Số: {{ activeDocument.code }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-2 text-right text-[10px] text-neutral-700">
                                    <p><span class="font-semibold">Nhà hàng:</span> {{ activeDocument.payload?.branch?.name || activeDocument.branch_name }}</p>
                                    <p><span class="font-semibold">Ngày:</span> {{ activeDocument.payload?.closing_date }} &nbsp;&nbsp; <span class="font-semibold">Ca:</span> [x] {{ activeDocument.payload?.shift_name || 'Ca Sáng' }}</p>
                                    <p><span class="font-semibold">Thời gian ca:</span> {{ activeDocument.payload?.period_start_at }} đến {{ activeDocument.payload?.period_end_at }}</p>
                                    <p><span class="font-semibold">Quản lý ca / Thu ngân:</span> {{ activeDocument.created_by_name }}</p>
                                </div>

                                <!-- 1. THÔNG TIN CA LÀM & 2. TỔNG QUAN DOANH THU (Grid 2 cols) -->
                                <div class="mt-4 grid grid-cols-2 gap-4 text-[11px]">
                                    <!-- Cột Trái: Thông tin ca làm -->
                                    <div class="border border-black p-2.5">
                                        <h5 class="font-bold uppercase tracking-wider text-[11px] border-b border-black pb-1 mb-2">1. THÔNG TIN CA LÀM</h5>
                                        <table class="w-full text-left text-[10.5px]">
                                            <tr class="border-b border-neutral-300">
                                                <td class="py-1 font-semibold w-1/3">Nhân viên chốt ca:</td>
                                                <td class="py-1 font-bold">{{ activeDocument.created_by_name }}</td>
                                                <td class="py-1 text-right font-mono text-[9px]">Mã: NV-{{ String(activeDocument.raw_id).padStart(4, '0') }}</td>
                                            </tr>
                                            <tr class="border-b border-neutral-300">
                                                <td class="py-1 font-semibold">Vị trí:</td>
                                                <td class="py-1" colspan="2">[x] Thu ngân / Quản lý &nbsp;&nbsp; Khu vực: <span class="font-semibold">Khu Vực Sảnh A</span></td>
                                            </tr>
                                            <tr class="border-b border-neutral-300">
                                                <td class="py-1 font-semibold">Nhân viên bàn giao:</td>
                                                <td class="py-1 italic">{{ activeDocument.created_by_name }}</td>
                                                <td class="py-1 text-right font-mono text-[9px]">Mã: NV002</td>
                                            </tr>
                                            <tr>
                                                <td class="py-1 font-semibold">Thời gian bàn giao:</td>
                                                <td class="py-1" colspan="2">Bắt đầu: {{ activeDocument.payload?.period_start_at }} - Kết thúc: {{ activeDocument.payload?.period_end_at }}</td>
                                            </tr>
                                        </table>
                                    </div>

                                    <!-- Cột Phải: Tổng quan doanh thu -->
                                    <div class="border border-black p-2.5">
                                        <h5 class="font-bold uppercase tracking-wider text-[11px] border-b border-black pb-1 mb-2">2. TỔNG QUAN DOANH THU</h5>
                                        <table class="w-full text-[10.5px]">
                                            <tr class="border-b border-neutral-300">
                                                <td class="py-1 font-semibold">Tổng doanh thu bán hàng:</td>
                                                <td class="py-1 text-right font-mono font-bold">{{ formatCurrency(activeDocument.payload?.total_sales) }}</td>
                                            </tr>
                                            <tr class="border-b border-neutral-300">
                                                <td class="py-1 font-semibold">Giảm giá / Khuyến mãi:</td>
                                                <td class="py-1 text-right font-mono text-rose-600">-{{ formatCurrency(activeDocument.payload?.discount_amount) }}</td>
                                            </tr>
                                            <tr class="border-b border-neutral-300">
                                                <td class="py-1 font-semibold">Phí dịch vụ:</td>
                                                <td class="py-1 text-right font-mono">-</td>
                                            </tr>
                                            <tr class="border-b-2 border-black bg-neutral-100 font-bold">
                                                <td class="py-1 uppercase">DOANH THU THUẦN:</td>
                                                <td class="py-1 text-right font-mono font-black">{{ formatCurrency(activeDocument.payload?.net_revenue) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="py-1 font-semibold">Số hóa đơn / Lượt khách:</td>
                                                <td class="py-1 text-right font-mono">{{ activeDocument.payload?.orders_count || 5 }} đơn / {{ activeDocument.payload?.customer_count || '—' }} khách</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- 3. CHI TIẾT THANH TOÁN -->
                                <div class="mt-4">
                                    <h5 class="mb-1 font-bold uppercase tracking-wider text-[11px]">3. CHI TIẾT THANH TOÁN</h5>
                                    <table class="w-full border-collapse border border-black text-center text-[10px]">
                                        <thead>
                                            <tr class="bg-neutral-100 font-bold">
                                                <th class="border border-black p-1 text-left">Hình thức thanh toán</th>
                                                <th class="border border-black p-1 text-right">Doanh thu hệ thống (VNĐ)</th>
                                                <th class="border border-black p-1 text-right">Thực thu kiểm đếm (VNĐ)</th>
                                                <th class="border border-black p-1 text-right">Chênh lệch (+/-)</th>
                                                <th class="border border-black p-1 text-left">Ghi chú</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="border border-black p-1.5 text-left font-semibold">Tiền mặt (Cash)</td>
                                                <td class="border border-black p-1.5 text-right font-mono">{{ formatCurrency(activeDocument.payload?.cash_sales_amount) }}</td>
                                                <td class="border border-black p-1.5 text-right font-mono font-bold">{{ formatCurrency(activeDocument.payload?.actual_cash) }}</td>
                                                <td class="border border-black p-1.5 text-right font-mono font-bold" :class="Number(activeDocument.payload?.cash_difference) < 0 ? 'text-rose-600' : ''">
                                                    {{ formatCurrency(activeDocument.payload?.cash_difference) }}
                                                </td>
                                                <td class="border border-black p-1.5 text-left text-[9px]">{{ activeDocument.payload?.notes || 'Đã nộp két an toàn' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="border border-black p-1.5 text-left font-semibold">Chuyển khoản (Bank Transfer)</td>
                                                <td class="border border-black p-1.5 text-right font-mono">{{ formatCurrency(activeDocument.payload?.transfer_amount) }}</td>
                                                <td class="border border-black p-1.5 text-right font-mono font-bold">{{ formatCurrency(activeDocument.payload?.actual_transfer_amount || activeDocument.payload?.transfer_amount) }}</td>
                                                <td class="border border-black p-1.5 text-right font-mono">0 đ</td>
                                                <td class="border border-black p-1.5 text-left text-[9px]">Khớp sao kê tài khoản ngân hàng</td>
                                            </tr>
                                            <tr>
                                                <td class="border border-black p-1.5 text-left font-semibold">Thẻ ngân hàng (POS/Card)</td>
                                                <td class="border border-black p-1.5 text-right font-mono">{{ formatCurrency(activeDocument.payload?.card_amount) }}</td>
                                                <td class="border border-black p-1.5 text-right font-mono font-bold">{{ formatCurrency(activeDocument.payload?.card_amount) }}</td>
                                                <td class="border border-black p-1.5 text-right font-mono">0 đ</td>
                                                <td class="border border-black p-1.5 text-left text-[9px]">Khớp bill POS quẹt thẻ</td>
                                            </tr>
                                            <tr class="bg-neutral-50 font-bold">
                                                <td class="border border-black p-1.5 text-left uppercase">TỔNG CỘNG THỰC THU</td>
                                                <td class="border border-black p-1.5 text-right font-mono">{{ formatCurrency(activeDocument.total_amount) }}</td>
                                                <td class="border border-black p-1.5 text-right font-mono font-black">{{ formatCurrency(activeDocument.total_amount) }}</td>
                                                <td class="border border-black p-1.5 text-right font-mono font-bold">{{ formatCurrency(activeDocument.payload?.cash_difference) }}</td>
                                                <td class="border border-black p-1.5 text-left text-[9px]"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Chữ ký xác nhận -->
                                <div class="mt-8 grid grid-cols-3 text-center text-[10.5px]">
                                    <div>
                                        <p class="font-bold uppercase">Thu ngân / Người chốt</p>
                                        <p class="text-[9px] italic text-neutral-600">(Ký, ghi rõ họ tên)</p>
                                        <div class="h-16"></div>
                                        <p class="font-bold">{{ activeDocument.created_by_name }}</p>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase">Quản lý chi nhánh</p>
                                        <p class="text-[9px] italic text-neutral-600">(Ký, ghi rõ họ tên)</p>
                                        <div class="h-16"></div>
                                        <p class="font-bold">Nguyễn Văn Quản Lý</p>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase">Chủ doanh nghiệp / Giám đốc</p>
                                        <p class="text-[9px] italic text-neutral-600">(Ký duyệt, đóng dấu)</p>
                                        <div class="h-16"></div>
                                        <p class="font-bold text-emerald-700">[Đã Phê Duyệt Trực Tuyến]</p>
                                    </div>
                                </div>
                            </template>

                            <!-- 2. MẪU: PHIẾU CHỐT KHO (KHO TỔNG / CHI NHÁNH / GIAO CA KHO) -->
                            <template v-else-if="activeDocument.type === 'warehouse_closing'">
                                <!-- A. Nếu là Biên bản giao ca kho tổng -->
                                <template v-if="activeDocument.payload?.is_shift_handover">
                                    <div class="flex items-start justify-between border-b-2 border-black pb-3">
                                        <div class="flex items-center gap-3">
                                            <div class="flex size-11 items-center justify-center rounded-full border-2 border-black bg-neutral-100 font-bold">
                                                <Warehouse class="size-6 text-black" />
                                            </div>
                                            <div>
                                                <h4 class="font-black text-sm uppercase tracking-wider text-black">AVENTURA CENTRAL LOGISTICS</h4>
                                                <p class="text-[10px] text-neutral-800">Trung Tâm Phân Phối & Tổng Kho Aventura</p>
                                                <p class="text-[9px] text-neutral-600">Địa điểm: {{ activeDocument.branch_name }}</p>
                                            </div>
                                        </div>

                                        <div class="text-right">
                                            <h2 class="text-base font-black uppercase text-black">BIÊN BẢN CHỐT & BÀN GIAO CA KHO</h2>
                                            <p class="mt-0.5 inline-block border border-black px-2 py-0.5 font-mono text-xs font-bold">
                                                Số: {{ activeDocument.code }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-2 text-right text-[10px] text-neutral-700">
                                        <p><span class="font-semibold">Kho áp dụng:</span> {{ activeDocument.branch_name }}</p>
                                        <p><span class="font-semibold">Ngày chốt ca:</span> {{ activeDocument.payload?.shift_date }} &nbsp;&nbsp; <span class="font-semibold">Ca trực:</span> {{ activeDocument.payload?.shift_label || 'Ca chính' }}</p>
                                        <p><span class="font-semibold">Thủ kho giao ca:</span> {{ activeDocument.created_by_name }} &nbsp;&nbsp; <span class="font-semibold">Thủ kho nhận ca:</span> {{ activeDocument.payload?.received_by?.name || 'Đang chờ nhận ca' }}</p>
                                    </div>

                                    <!-- 1. THÔNG TIN BÀN GIAO & 2. TỔNG HỢP CA TRỰC -->
                                    <div class="mt-3 grid grid-cols-2 gap-3 text-[10.5px]">
                                        <div class="border border-black p-2.5">
                                            <h5 class="font-bold uppercase tracking-wider text-[10.5px] border-b border-black pb-1 mb-2">1. THÔNG TIN NHÂN SỰ CA TRỰC</h5>
                                            <table class="w-full text-[10px]">
                                                <tr class="border-b border-neutral-300">
                                                    <td class="py-1 font-semibold w-2/5">Thủ kho giao:</td>
                                                    <td class="py-1 font-bold">{{ activeDocument.created_by_name }}</td>
                                                </tr>
                                                <tr class="border-b border-neutral-300">
                                                    <td class="py-1 font-semibold">Thủ kho nhận:</td>
                                                    <td class="py-1 font-bold">{{ activeDocument.payload?.received_by?.name || 'Chưa nhận bàn giao' }}</td>
                                                </tr>
                                                <tr class="border-b border-neutral-300">
                                                    <td class="py-1 font-semibold">Ca làm việc:</td>
                                                    <td class="py-1">{{ activeDocument.payload?.shift_label || 'Ca kho ngày' }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 font-semibold">Trạng thái bàn giao:</td>
                                                    <td class="py-1 font-semibold" :class="activeDocument.has_discrepancy ? 'text-rose-600' : 'text-emerald-700'">
                                                        {{ activeDocument.has_discrepancy ? 'Có lưu ý / Sự cố kho' : 'Bàn giao hoàn tất' }}
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>

                                        <div class="border border-black p-2.5">
                                            <h5 class="font-bold uppercase tracking-wider text-[10.5px] border-b border-black pb-1 mb-2">2. CHỈ TIÊU & GIÁ TRỊ TỒN KHO</h5>
                                            <table class="w-full text-[10px]">
                                                <tr class="border-b border-neutral-300">
                                                    <td class="py-1 font-semibold">Giá trị tồn đầu ca:</td>
                                                    <td class="py-1 text-right font-mono font-bold">{{ formatCurrency(activeDocument.payload?.starting_stock_value) }}</td>
                                                </tr>
                                                <tr class="border-b border-neutral-300 bg-neutral-100 font-bold">
                                                    <td class="py-1 uppercase">Giá trị tồn cuối ca:</td>
                                                    <td class="py-1 text-right font-mono font-black">{{ formatCurrency(activeDocument.payload?.ending_stock_value) }}</td>
                                                </tr>
                                                <tr class="border-b border-neutral-300">
                                                    <td class="py-1 font-semibold">Công việc tồn (Picking/Ship):</td>
                                                    <td class="py-1 text-right font-mono">{{ Number(activeDocument.payload?.pending_picks_count || 0) + Number(activeDocument.payload?.pending_deliveries_count || 0) }} task</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1 font-semibold">Lô hàng phong tỏa / Sự cố:</td>
                                                    <td class="py-1 text-right font-mono font-bold" :class="Number(activeDocument.payload?.locked_batches_count || activeDocument.payload?.open_incidents_count) > 0 ? 'text-rose-600' : ''">
                                                        {{ Number(activeDocument.payload?.locked_batches_count || 0) }} lô / {{ Number(activeDocument.payload?.open_incidents_count || 0) }} sự vụ
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Ghi chú & Sự cố phát sinh -->
                                    <div class="mt-3 border border-black p-2 text-[10.5px]">
                                        <h5 class="font-bold uppercase tracking-wider text-[10.5px] border-b border-black pb-1 mb-1.5">3. NHẬT KÝ & GHI CHÚ BÀN GIAO</h5>
                                        <p class="text-[10px] text-neutral-800 leading-relaxed italic">
                                            {{ activeDocument.payload?.notes || 'Trong ca hoạt động xuất nhập diễn ra bình thường, khu vực lưu kho ngăn nắp, nhiệt độ bảo quản đạt chuẩn.' }}
                                        </p>
                                    </div>

                                    <!-- Chữ ký 4 bên -->
                                    <div class="mt-6 grid grid-cols-4 text-center text-[10px]">
                                        <div>
                                            <p class="font-bold uppercase">Thủ kho giao ca</p>
                                            <p class="text-[8.5px] italic text-neutral-600">(Ký, ghi rõ họ tên)</p>
                                            <div class="h-14"></div>
                                            <p class="font-bold">{{ activeDocument.created_by_name }}</p>
                                        </div>
                                        <div>
                                            <p class="font-bold uppercase">Thủ kho nhận ca</p>
                                            <p class="text-[8.5px] italic text-neutral-600">(Ký, ghi rõ họ tên)</p>
                                            <div class="h-14"></div>
                                            <p class="font-bold">{{ activeDocument.payload?.received_by?.name || 'Đã ký nhận' }}</p>
                                        </div>
                                        <div>
                                            <p class="font-bold uppercase">Quản lý kho</p>
                                            <p class="text-[8.5px] italic text-neutral-600">(Ký, ghi rõ họ tên)</p>
                                            <div class="h-14"></div>
                                            <p class="font-bold">Trưởng Kho Tổng</p>
                                        </div>
                                        <div>
                                            <p class="font-bold uppercase">Ban Giám Đốc</p>
                                            <p class="text-[8.5px] italic text-neutral-600">(Ký duyệt, đóng dấu)</p>
                                            <div class="h-14"></div>
                                            <p class="font-bold text-emerald-700">[Đã Duyệt Điện Tử]</p>
                                        </div>
                                    </div>
                                </template>

                                <!-- B. Mẫu Tiêu Chuẩn: Phiếu Chốt Kho Chi Nhánh / Phiếu Chốt Nguyên Liệu Kho Tổng -->
                                <template v-else>
                                    <!-- Header Quốc Hiệu -->
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-amber-600 font-black text-base">⚡</span>
                                                <h4 class="font-black text-xs uppercase tracking-wider text-black">CÔNG TY TNHH AVENTURA</h4>
                                            </div>
                                            <p class="text-[9.5px] text-neutral-700">Hệ thống quản lý chuỗi nhà hàng & phân phối thực phẩm</p>
                                            <p class="text-[9px] text-neutral-600">Đơn vị: {{ activeDocument.branch_name }}</p>
                                        </div>

                                        <div class="text-center">
                                            <h4 class="font-bold text-xs uppercase text-black">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</h4>
                                            <p class="text-[10px] font-semibold">Độc lập – Tự do – Hạnh phúc</p>
                                            <p class="text-[8px]">★ ★ ★</p>
                                            <p class="text-[9.5px] italic text-neutral-600 mt-1">Hà Nội, {{ activeDocument.date_formatted }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 text-center">
                                        <h2 class="text-base font-black uppercase text-black">
                                            PHIẾU CHỐT KHO {{ activeDocument.payload?.is_central ? 'NGUYÊN LIỆU KHO TỔNG' : 'CHI NHÁNH' }}
                                        </h2>
                                        <p class="mt-0.5 inline-block border border-black px-3 py-0.5 font-mono text-xs font-bold">
                                            Số: {{ activeDocument.code }}
                                        </p>
                                    </div>

                                    <!-- 1. THÔNG TIN CHUNG -->
                                    <div class="mt-3 border border-black p-2.5 text-[10.5px]">
                                        <h5 class="font-bold uppercase tracking-wider text-[10.5px] border-b border-black pb-1 mb-1.5">1. THÔNG TIN CHUNG</h5>
                                        <div class="grid grid-cols-2 gap-x-6 gap-y-1">
                                            <p><span class="font-semibold">Kho / Chi nhánh:</span> <span class="font-bold uppercase">{{ activeDocument.branch_name }}</span></p>
                                            <p><span class="font-semibold">Người lập phiếu:</span> <span class="font-bold">{{ activeDocument.created_by_name }}</span></p>
                                            <p>
                                                <span class="font-semibold">Kỳ chốt kho:</span>
                                                Từ ngày {{ activeDocument.payload?.period_start || '01/08/2026' }} đến ngày {{ activeDocument.payload?.period_end || '31/08/2026' }}
                                            </p>
                                            <p><span class="font-semibold">Người cùng kiểm kê:</span> {{ activeDocument.payload?.second_counted_by?.name || 'Kế toán kho' }}</p>
                                            <p class="col-span-2">
                                                <span class="font-semibold">Lý do chốt:</span>
                                                <span class="ml-1 italic">{{ activeDocument.payload?.notes || 'Chốt tồn kho định kỳ, đối chiếu sổ cái và cân đối hao hụt thực tế.' }}</span>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- 2. TỔNG HỢP GIÁ TRỊ TỒN KHO & BIẾN ĐỘNG -->
                                    <div class="mt-2.5">
                                        <h5 class="mb-1 font-bold uppercase tracking-wider text-[10.5px]">2. TỔNG HỢP GIÁ TRỊ TỒN KHO & BIẾN ĐỘNG TRONG KỲ</h5>
                                        <table class="w-full border-collapse border border-black text-center text-[10px]">
                                            <thead>
                                                <tr class="bg-neutral-100 font-bold">
                                                    <th class="border border-black p-1.5 w-10">STT</th>
                                                    <th class="border border-black p-1.5 text-left">Chỉ tiêu cân đối kho</th>
                                                    <th class="border border-black p-1.5 text-right w-32">Tổng số lượng</th>
                                                    <th class="border border-black p-1.5 text-right w-44">Tổng giá trị (VNĐ)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td class="border border-black p-1 font-mono">1</td>
                                                    <td class="border border-black p-1 text-left font-semibold">Tồn đầu kỳ</td>
                                                    <td class="border border-black p-1 text-right font-mono">{{ formatQuantity(activeDocument.payload?.total_opening_qty) }}</td>
                                                    <td class="border border-black p-1 text-right font-mono font-semibold">{{ formatCurrency(activeDocument.payload?.total_opening_val) }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-black p-1 font-mono">2</td>
                                                    <td class="border border-black p-1 text-left font-semibold">Tổng nhập trong kỳ</td>
                                                    <td class="border border-black p-1 text-right font-mono text-emerald-700">+{{ formatQuantity(activeDocument.payload?.total_inbound_qty) }}</td>
                                                    <td class="border border-black p-1 text-right font-mono font-semibold text-emerald-700">+{{ formatCurrency(activeDocument.payload?.total_inbound_val) }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-black p-1 font-mono">3</td>
                                                    <td class="border border-black p-1 text-left font-semibold">Tổng xuất trong kỳ (Bán hàng / Điều chuyển)</td>
                                                    <td class="border border-black p-1 text-right font-mono text-rose-700">-{{ formatQuantity(activeDocument.payload?.total_outbound_qty) }}</td>
                                                    <td class="border border-black p-1 text-right font-mono font-semibold text-rose-700">-{{ formatCurrency(activeDocument.payload?.total_outbound_val) }}</td>
                                                </tr>
                                                <tr class="bg-neutral-50">
                                                    <td class="border border-black p-1 font-mono font-bold">4</td>
                                                    <td class="border border-black p-1 text-left font-bold">Tồn cuối kỳ theo sổ sách (1 + 2 - 3)</td>
                                                    <td class="border border-black p-1 text-right font-mono font-bold">{{ formatQuantity(activeDocument.payload?.total_expected_qty) }}</td>
                                                    <td class="border border-black p-1 text-right font-mono font-bold">{{ formatCurrency(activeDocument.payload?.total_expected_val) }}</td>
                                                </tr>
                                                <tr class="bg-neutral-100 font-bold">
                                                    <td class="border border-black p-1.5 font-mono">5</td>
                                                    <td class="border border-black p-1.5 text-left uppercase font-black">TỒN CUỐI KỲ THỰC TẾ (KIỂM ĐẾM CHỐT SỔ)</td>
                                                    <td class="border border-black p-1.5 text-right font-mono font-black text-blue-700">{{ formatQuantity(activeDocument.payload?.total_counted_qty) }}</td>
                                                    <td class="border border-black p-1.5 text-right font-mono font-black text-blue-700">{{ formatCurrency(activeDocument.payload?.total_counted_val) }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-black p-1 font-mono">6</td>
                                                    <td class="border border-black p-1 text-left font-semibold">Chênh lệch / Hao hụt thực tế (+/-)</td>
                                                    <td class="border border-black p-1 text-right font-mono font-bold" :class="Number(activeDocument.payload?.total_variance_qty) < 0 ? 'text-rose-600' : ''">
                                                        {{ formatQuantity(activeDocument.payload?.total_variance_qty) }}
                                                    </td>
                                                    <td class="border border-black p-1 text-right font-mono font-bold" :class="Number(activeDocument.payload?.total_variance_val) < 0 ? 'text-rose-600' : ''">
                                                        {{ formatCurrency(activeDocument.payload?.total_variance_val) }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- 3. DANH SÁCH NGUYÊN LIỆU CHỐT KHO -->
                                    <div class="mt-3">
                                        <h5 class="mb-1 font-bold uppercase tracking-wider text-[10.5px]">3. DANH SÁCH CHI TIẾT NGUYÊN LIỆU CHỐT KHO</h5>
                                        <table class="w-full border-collapse border border-black text-center text-[9.5px]">
                                            <thead>
                                                <tr class="bg-neutral-100 font-bold">
                                                    <th class="border border-black p-1">STT</th>
                                                    <th class="border border-black p-1">Mã NL</th>
                                                    <th class="border border-black p-1 text-left">Tên nguyên liệu</th>
                                                    <th class="border border-black p-1">ĐVT</th>
                                                    <th class="border border-black p-1">Tồn đầu</th>
                                                    <th class="border border-black p-1">Nhập</th>
                                                    <th class="border border-black p-1">Xuất</th>
                                                    <th class="border border-black p-1 font-bold">Tồn sổ sách</th>
                                                    <th class="border border-black p-1 font-black bg-neutral-200">Thực chốt</th>
                                                    <th class="border border-black p-1">Lệch</th>
                                                    <th class="border border-black p-1 text-right">Đơn giá (đ)</th>
                                                    <th class="border border-black p-1 text-right">Giá trị tồn (đ)</th>
                                                    <th class="border border-black p-1 text-left">Ghi chú</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="item in (activeDocument.payload?.items || [])" :key="item.stt">
                                                    <td class="border border-black p-1 font-mono">{{ item.stt }}</td>
                                                    <td class="border border-black p-1 font-mono font-bold">{{ item.sku }}</td>
                                                    <td class="border border-black p-1 text-left font-semibold">{{ item.name }}</td>
                                                    <td class="border border-black p-1">{{ item.unit }}</td>
                                                    <td class="border border-black p-1 font-mono">{{ formatQuantity(item.opening_quantity) }}</td>
                                                    <td class="border border-black p-1 font-mono text-emerald-700">+{{ formatQuantity(item.inbound_quantity) }}</td>
                                                    <td class="border border-black p-1 font-mono text-rose-700">-{{ formatQuantity(item.outbound_quantity) }}</td>
                                                    <td class="border border-black p-1 font-mono font-semibold">{{ formatQuantity(item.expected_quantity) }}</td>
                                                    <td class="border border-black p-1 font-mono font-black bg-neutral-50">{{ formatQuantity(item.final_quantity) }}</td>
                                                    <td class="border border-black p-1 font-mono font-bold" :class="Number(item.variance_quantity) < 0 ? 'text-rose-600' : ''">
                                                        {{ Number(item.variance_quantity) !== 0 ? formatQuantity(item.variance_quantity) : '0' }}
                                                    </td>
                                                    <td class="border border-black p-1 text-right font-mono">{{ formatCurrency(item.unit_cost) }}</td>
                                                    <td class="border border-black p-1 text-right font-mono font-bold">{{ formatCurrency(item.total_closing_value) }}</td>
                                                    <td class="border border-black p-1 text-left text-[8.5px] italic">{{ item.notes || '' }}</td>
                                                </tr>
                                                <tr class="bg-neutral-50 font-bold">
                                                    <td class="border border-black p-1 uppercase font-black" colspan="4">TỔNG CỘNG</td>
                                                    <td class="border border-black p-1 font-mono">{{ formatQuantity(activeDocument.payload?.total_opening_qty) }}</td>
                                                    <td class="border border-black p-1 font-mono text-emerald-700">+{{ formatQuantity(activeDocument.payload?.total_inbound_qty) }}</td>
                                                    <td class="border border-black p-1 font-mono text-rose-700">-{{ formatQuantity(activeDocument.payload?.total_outbound_qty) }}</td>
                                                    <td class="border border-black p-1 font-mono">{{ formatQuantity(activeDocument.payload?.total_expected_qty) }}</td>
                                                    <td class="border border-black p-1 font-mono font-black bg-neutral-200">{{ formatQuantity(activeDocument.payload?.total_counted_qty) }}</td>
                                                    <td class="border border-black p-1 font-mono" :class="Number(activeDocument.payload?.total_variance_qty) < 0 ? 'text-rose-600' : ''">
                                                        {{ formatQuantity(activeDocument.payload?.total_variance_qty) }}
                                                    </td>
                                                    <td class="border border-black p-1 text-right">—</td>
                                                    <td class="border border-black p-1 text-right font-mono font-black">{{ formatCurrency(activeDocument.total_amount) }}</td>
                                                    <td class="border border-black p-1"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- 4. CHỮ KÝ XÁC NHẬN 4 BÊN -->
                                    <div class="mt-6 grid grid-cols-4 text-center text-[10px]">
                                        <div>
                                            <p class="font-bold uppercase">Thủ kho lập phiếu</p>
                                            <p class="text-[8.5px] italic text-neutral-600">(Ký, ghi rõ họ tên)</p>
                                            <div class="h-14"></div>
                                            <p class="font-bold">{{ activeDocument.created_by_name }}</p>
                                        </div>
                                        <div>
                                            <p class="font-bold uppercase">Người cùng kiểm kê</p>
                                            <p class="text-[8.5px] italic text-neutral-600">(Ký, ghi rõ họ tên)</p>
                                            <div class="h-14"></div>
                                            <p class="font-bold">{{ activeDocument.payload?.second_counted_by?.name || 'Kế toán kho' }}</p>
                                        </div>
                                        <div>
                                            <p class="font-bold uppercase">Quản lý chi nhánh / Kho</p>
                                            <p class="text-[8.5px] italic text-neutral-600">(Ký, ghi rõ họ tên)</p>
                                            <div class="h-14"></div>
                                            <p class="font-bold">{{ activeDocument.payload?.approver?.name || 'Trưởng kho' }}</p>
                                        </div>
                                        <div>
                                            <p class="font-bold uppercase">Chủ doanh nghiệp / Giám đốc</p>
                                            <p class="text-[8.5px] italic text-neutral-600">(Ký duyệt, đóng dấu)</p>
                                            <div class="h-14"></div>
                                            <p class="font-bold text-emerald-700">[Đã Duyệt Điện Tử]</p>
                                        </div>
                                    </div>
                                </template>
                            </template>

                            <!-- 3. MẪU: PHIẾU ĐIỀU CHUYỂN NGUYÊN LIỆU (Matching User Image 1) -->
                            <template v-else-if="activeDocument.type === 'stock_transfer'">
                                <!-- Header Quốc Hiệu -->
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-amber-600 font-black text-base">⚡</span>
                                            <h4 class="font-black text-xs uppercase tracking-wider text-black">CÔNG TY TNHH AVENTURA</h4>
                                        </div>
                                        <p class="text-[9.5px] text-neutral-700">Chuỗi cung cấp thực phẩm & dịch vụ nhà hàng</p>
                                        <p class="text-[9px] text-neutral-600">Địa chỉ doanh nghiệp: Chưa cập nhật</p>
                                        <p class="text-[9px] text-neutral-600">Hotline: Chưa cập nhật</p>
                                    </div>

                                    <div class="text-center">
                                        <h4 class="font-bold text-xs uppercase text-black">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</h4>
                                        <p class="text-[10px] font-semibold">Độc lập – Tự do – Hạnh phúc</p>
                                        <p class="text-[8px]">★ ★ ★</p>
                                        <p class="text-[9.5px] italic text-neutral-600 mt-1">Hà Nội, {{ activeDocument.date_formatted }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 text-center">
                                    <h2 class="text-base font-black uppercase text-black">PHIẾU ĐIỀU CHUYỂN NGUYÊN LIỆU</h2>
                                    <p class="mt-0.5 inline-block border border-black px-3 py-0.5 font-mono text-xs font-bold">
                                        Số: {{ activeDocument.code }}
                                    </p>
                                </div>

                                <!-- 1. THÔNG TIN CHUNG -->
                                <div class="mt-3 border border-black p-2 text-[10.5px]">
                                    <h5 class="font-bold uppercase tracking-wider text-[10.5px]">1. THÔNG TIN CHUNG</h5>
                                    <div class="mt-1 grid grid-cols-2 gap-x-4 gap-y-0.5">
                                        <p><span class="font-semibold">Ngày lập phiếu:</span> {{ activeDocument.date_formatted }}</p>
                                        <p><span class="font-semibold">Người lập phiếu:</span> <span class="font-bold">{{ activeDocument.created_by_name }}</span></p>
                                        <p class="col-span-2">
                                            <span class="font-semibold">Lý do điều chuyển:</span>
                                            <span class="ml-1">{{ activeDocument.payload?.notes || 'Chưa cập nhật' }}</span>
                                        </p>
                                        <p><span class="font-semibold">Chức vụ:</span> Quản lý chi nhánh / Điều phối viên</p>
                                    </div>
                                </div>

                                <!-- 2 & 3: BÊN ĐIỀU CHUYỂN & BÊN NHẬN (Grid 2 cols) -->
                                <div class="mt-2 grid grid-cols-2 gap-2 text-[10.5px]">
                                    <div class="border border-black p-2">
                                        <h5 class="font-bold uppercase tracking-wider text-[10.5px]">2. THÔNG TIN BÊN ĐIỀU CHUYỂN (NƠI XUẤT)</h5>
                                        <div class="mt-1 space-y-0.5">
                                            <p><span class="font-semibold">Kho xuất:</span> {{ activeDocument.payload?.from_branch?.name || 'Chưa cập nhật' }}</p>
                                            <p><span class="font-semibold">Địa chỉ kho:</span> {{ activeDocument.payload?.from_branch?.address || 'Chưa cập nhật' }}</p>
                                            <p><span class="font-semibold">Người phụ trách kho:</span> {{ activeDocument.payload?.dispatched_by?.name || 'Chưa cập nhật' }}</p>
                                            <p><span class="font-semibold">SĐT:</span> {{ activeDocument.payload?.from_branch?.phone || 'Chưa cập nhật' }}</p>
                                        </div>
                                    </div>

                                    <div class="border border-black p-2">
                                        <h5 class="font-bold uppercase tracking-wider text-[10.5px]">3. THÔNG TIN BÊN NHẬN ĐIỀU CHUYỂN (NƠI NHẬN)</h5>
                                        <div class="mt-1 space-y-0.5">
                                            <p><span class="font-semibold">Kho nhận:</span> {{ activeDocument.payload?.to_branch?.name || 'Chưa cập nhật' }}</p>
                                            <p><span class="font-semibold">Địa chỉ kho:</span> {{ activeDocument.payload?.to_branch?.address || 'Chưa cập nhật' }}</p>
                                            <p><span class="font-semibold">Người phụ trách kho:</span> {{ activeDocument.payload?.received_by?.name || 'Chưa cập nhật' }}</p>
                                            <p><span class="font-semibold">SĐT:</span> {{ activeDocument.payload?.to_branch?.phone || 'Chưa cập nhật' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- 4. HÌNH THỨC VẬN CHUYỂN -->
                                <div class="mt-2 border border-black p-2 text-[10.5px]">
                                    <h5 class="font-bold uppercase tracking-wider text-[10.5px]">4. HÌNH THỨC VẬN CHUYỂN</h5>
                                    <div class="mt-1 flex items-center justify-between">
                                        <p>Phương thức: {{ activeDocument.payload?.transport_method || 'Chưa cập nhật' }}</p>
                                        <p><span class="font-semibold">Dự kiến giao đến:</span> Chưa xác định</p>
                                    </div>
                                    <div class="mt-0.5 flex items-center gap-6">
                                        <p><span class="font-semibold">Phương tiện vận chuyển:</span> {{ activeDocument.payload?.transport_method || 'Chưa cập nhật' }}</p>
                                        <p><span class="font-semibold">Biển số xe:</span> <span class="font-mono font-bold">{{ activeDocument.payload?.vehicle_number || 'Chưa cập nhật' }}</span></p>
                                    </div>
                                </div>

                                <!-- 5. DANH SÁCH NGUYÊN LIỆU ĐIỀU CHUYỂN -->
                                <div class="mt-2">
                                    <h5 class="mb-1 font-bold uppercase tracking-wider text-[10.5px]">5. DANH SÁCH NGUYÊN LIỆU ĐIỀU CHUYỂN</h5>
                                    <table class="w-full border-collapse border border-black text-center text-[10px]">
                                        <thead>
                                            <tr class="bg-neutral-100 font-bold">
                                                <th class="border border-black p-1" rowspan="2">STT</th>
                                                <th class="border border-black p-1" rowspan="2">Mã nguyên liệu</th>
                                                <th class="border border-black p-1 text-left" rowspan="2">Tên nguyên liệu</th>
                                                <th class="border border-black p-1" rowspan="2">Đơn vị tính</th>
                                                <th class="border border-black p-1" colspan="2">Số lượng điều chuyển</th>
                                                <th class="border border-black p-1" rowspan="2">Tồn kho lúc xuất</th>
                                                <th class="border border-black p-1 text-right" rowspan="2">Đơn giá (VNĐ)</th>
                                                <th class="border border-black p-1 text-right" rowspan="2">Thành tiền (VNĐ)</th>
                                                <th class="border border-black p-1 text-left" rowspan="2">Ghi chú</th>
                                            </tr>
                                            <tr class="bg-neutral-100 font-bold text-[9px]">
                                                <th class="border border-black p-0.5">Thực xuất</th>
                                                <th class="border border-black p-0.5">Thực nhận</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="item in (activeDocument.payload?.items || [])" :key="item.stt">
                                                <td class="border border-black p-1 font-mono">{{ item.stt }}</td>
                                                <td class="border border-black p-1 font-mono font-bold">{{ item.sku || 'Chưa cập nhật' }}</td>
                                                <td class="border border-black p-1 text-left font-semibold">{{ item.name || 'Chưa cập nhật' }}</td>
                                                <td class="border border-black p-1">{{ item.unit || 'Chưa cập nhật' }}</td>
                                                <td class="border border-black p-1 font-mono font-bold">{{ formatQuantity(item.dispatched_quantity) }}</td>
                                                <td class="border border-black p-1 font-mono text-neutral-600">{{ item.received_quantity !== null && item.received_quantity !== undefined ? formatQuantity(item.received_quantity) : '...........' }}</td>
                                                <td class="border border-black p-1 font-mono">{{ formatQuantity(item.current_stock) }}</td>
                                                <td class="border border-black p-1 text-right font-mono">{{ formatCurrency(item.unit_cost) }}</td>
                                                <td class="border border-black p-1 text-right font-mono font-bold">{{ formatCurrency(item.total_amount) }}</td>
                                                <td class="border border-black p-1 text-left text-[9px]">{{ item.notes }}</td>
                                            </tr>
                                            <tr class="bg-neutral-50 font-bold">
                                                <td class="border border-black p-1 uppercase" colspan="4">TỔNG CỘNG</td>
                                                <td class="border border-black p-1 font-mono font-bold">{{ formatQuantity(activeDocument.payload?.items?.reduce((s: number, i: any) => s + Number(i.dispatched_quantity || 0), 0)) }}</td>
                                                <td class="border border-black p-1"></td>
                                                <td class="border border-black p-1"></td>
                                                <td class="border border-black p-1 text-right">—</td>
                                                <td class="border border-black p-1 text-right font-mono font-black">{{ formatCurrency(activeDocument.total_amount) }}</td>
                                                <td class="border border-black p-1"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- 6. CHỮ KÝ 4 BÊN -->
                                <div class="mt-6 grid grid-cols-4 text-center text-[10px]">
                                    <div>
                                        <p class="font-bold uppercase">Người lập phiếu</p>
                                        <p class="text-[8.5px] italic text-neutral-600">(Ký, ghi rõ họ tên)</p>
                                        <div class="h-14"></div>
                                        <p class="font-bold">{{ activeDocument.created_by_name }}</p>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase">Thủ kho xuất</p>
                                        <p class="text-[8.5px] italic text-neutral-600">(Ký, ghi rõ họ tên)</p>
                                        <div class="h-14"></div>
                                        <p class="font-bold">{{ activeDocument.payload?.dispatched_by?.name || 'Chưa ký' }}</p>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase">Người vận chuyển</p>
                                        <p class="text-[8.5px] italic text-neutral-600">(Ký, ghi rõ họ tên)</p>
                                        <div class="h-14"></div>
                                        <p class="font-bold">Chưa ký</p>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase">Thủ kho nhận</p>
                                        <p class="text-[8.5px] italic text-neutral-600">(Ký, ghi rõ họ tên)</p>
                                        <div class="h-14"></div>
                                        <p class="font-bold">{{ activeDocument.payload?.received_by?.name || 'Chưa ký' }}</p>
                                    </div>
                                </div>
                            </template>

                            <!-- 3. MẪU TIÊU CHUẨN: PHIẾU LƯƠNG NHÂN VIÊN -->
                            <template v-else-if="activeDocument.type === 'payslip'">
                                <!-- Header: Logo, Tiêu ngữ, Thông tin phiếu -->
                                <div class="flex items-start justify-between border-b-2 border-slate-900 pb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-11 items-center justify-center rounded-xl border border-slate-300 bg-amber-500/10 text-amber-600 shadow-sm">
                                            <Utensils class="size-6 text-amber-600" />
                                        </div>
                                        <div>
                                            <h3 class="font-black text-sm uppercase tracking-wider text-slate-900">CÔNG TY TNHH AVENTURA</h3>
                                            <p class="text-[10px] text-slate-600">Hệ thống F&B Chuỗi Nhà Hàng & Phân Phối Thực Phẩm</p>
                                            <p class="text-[9px] italic text-slate-500">Hương vị từ sự tận tâm</p>
                                        </div>
                                    </div>

                                    <div class="text-center">
                                        <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-900">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</h4>
                                        <p class="text-[10px] font-semibold text-slate-800">Độc lập - Tự do - Hạnh phúc</p>
                                        <p class="text-[9px] text-slate-400">---o0o---</p>
                                    </div>

                                    <div class="text-right">
                                        <h2 class="text-base font-black uppercase text-[#1e3a5f]">PHIẾU LƯƠNG NHÂN VIÊN</h2>
                                        <p class="text-xs font-semibold text-slate-700">Kỳ lương: Tháng {{ activeDocument.payload?.period?.month || '...' }}/{{ activeDocument.payload?.period?.year || '...' }}</p>
                                        <p class="font-mono text-[11px] text-slate-500">Mã: {{ activeDocument.code }}</p>
                                        <span class="mt-1 inline-block rounded border border-emerald-600/30 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                            {{ activeDocument.status_label?.label || 'Đã thanh toán' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- 1. THÔNG TIN NHÂN VIÊN -->
                                <div class="mt-4">
                                    <div class="inline-block rounded-t-md bg-[#1e3a5f] px-3 py-1 text-xs font-bold text-white uppercase tracking-wide">
                                        1. THÔNG TIN NHÂN VIÊN
                                    </div>
                                    <div class="rounded-b-md rounded-tr-md border border-slate-300 bg-white p-3 text-xs leading-relaxed text-slate-800">
                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-y-2 gap-x-4">
                                            <div>
                                                <span class="font-medium text-slate-500">Mã nhân viên:</span>
                                                <p class="font-mono font-bold text-slate-900">{{ activeDocument.payload?.employee?.code || '---' }}</p>
                                            </div>
                                            <div>
                                                <span class="font-medium text-slate-500">Họ và tên:</span>
                                                <p class="font-bold text-slate-900">{{ activeDocument.payload?.employee?.name || '---' }}</p>
                                            </div>
                                            <div>
                                                <span class="font-medium text-slate-500">Phòng ban / Bộ phận:</span>
                                                <p class="font-semibold text-slate-800">{{ activeDocument.payload?.employee?.department || activeDocument.branch_name }}</p>
                                            </div>
                                            <div>
                                                <span class="font-medium text-slate-500">Chức vụ:</span>
                                                <p class="font-semibold text-slate-800">{{ activeDocument.payload?.employee?.position || 'Nhân viên' }}</p>
                                            </div>
                                            <div>
                                                <span class="font-medium text-slate-500">Số công chuẩn:</span>
                                                <p class="font-mono font-bold text-slate-900">{{ activeDocument.payload?.standard_days ?? 26 }} ngày</p>
                                            </div>
                                            <div>
                                                <span class="font-medium text-slate-500">Ngày làm thực tế:</span>
                                                <p class="font-mono font-bold text-blue-700">{{ activeDocument.payload?.actual_work_days ?? 0 }} ngày</p>
                                            </div>
                                            <div>
                                                <span class="font-medium text-slate-500">Nghỉ phép hưởng lương:</span>
                                                <p class="font-mono font-bold text-emerald-700">{{ activeDocument.payload?.paid_leave_days ?? 0 }} ngày</p>
                                            </div>
                                            <div>
                                                <span class="font-medium text-slate-500">Hình thức trả lương:</span>
                                                <p class="font-semibold text-slate-800">{{ activeDocument.payload?.payment_method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 2. CHI TIẾT THU NHẬP & KHẤU TRỪ -->
                                <div class="mt-4">
                                    <div class="inline-block rounded-t-md bg-[#1e3a5f] px-3 py-1 text-xs font-bold text-white uppercase tracking-wide">
                                        2. CHI TIẾT THU NHẬP & KHẤU TRỪ
                                    </div>
                                    <div class="overflow-x-auto rounded-b-md rounded-tr-md border border-slate-300">
                                        <table class="w-full border-collapse text-xs">
                                            <thead>
                                                <tr class="bg-[#1e3a5f] text-white">
                                                    <th class="border border-slate-600 px-2 py-1.5 text-center font-bold w-10">STT</th>
                                                    <th class="border border-slate-600 px-3 py-1.5 text-left font-bold w-52">Khoản mục</th>
                                                    <th class="border border-slate-600 px-3 py-1.5 text-left font-bold">Căn cứ tính</th>
                                                    <th class="border border-slate-600 px-3 py-1.5 text-right font-bold w-36">Thành tiền (VNĐ)</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-slate-800">
                                                <!-- Group A: CÁC KHOẢN THU NHẬP -->
                                                <tr class="bg-[#f8fafc] font-bold text-[#1e3a5f]">
                                                    <td class="border border-slate-300 px-2 py-1 text-center font-bold">A</td>
                                                    <td class="border border-slate-300 px-3 py-1 uppercase" colspan="3">CÁC KHOẢN THU NHẬP</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-slate-300 px-2 py-1 text-center">1</td>
                                                    <td class="border border-slate-300 px-3 py-1 font-medium">Lương cơ bản theo HĐ</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-slate-600">Theo hợp đồng lao động</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(activeDocument.payload?.contract_base_salary || activeDocument.payload?.base_salary) }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-slate-300 px-2 py-1 text-center">2</td>
                                                    <td class="border border-slate-300 px-3 py-1 font-medium">Lương thời gian</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-slate-600">{{ getTimeFormula(activeDocument.payload) }}</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-right font-mono font-bold">{{ formatMoney(activeDocument.payload?.time_based_salary ?? activeDocument.payload?.base_salary) }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-slate-300 px-2 py-1 text-center">3</td>
                                                    <td class="border border-slate-300 px-3 py-1 font-medium">Phụ cấp chức vụ / trách nhiệm</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-slate-600">Phụ cấp theo vị trí công việc</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(activeDocument.payload?.breakdown?.allowances?.responsibility || 0) }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-slate-300 px-2 py-1 text-center">4</td>
                                                    <td class="border border-slate-300 px-3 py-1 font-medium">Phụ cấp ca làm / ca đêm</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-slate-600">Phụ cấp làm thêm ca tối, ca đêm</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(activeDocument.payload?.night_shift_amount || 0) }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-slate-300 px-2 py-1 text-center">5</td>
                                                    <td class="border border-slate-300 px-3 py-1 font-medium">Phụ cấp ăn ca / xăng xe</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-slate-600">Hỗ trợ cơm trưa, đi lại</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(activeDocument.payload?.meal_allowance || activeDocument.payload?.allowances || 0) }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-slate-300 px-2 py-1 text-center">6</td>
                                                    <td class="border border-slate-300 px-3 py-1 font-medium">Thưởng doanh số / KPI</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-slate-600">Theo kết quả đánh giá tháng</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(activeDocument.payload?.kpi_salary || activeDocument.payload?.bonuses || 0) }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-slate-300 px-2 py-1 text-center">7</td>
                                                    <td class="border border-slate-300 px-3 py-1 font-medium">Thưởng chuyên cần / Tăng ca</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-slate-600">{{ activeDocument.payload?.overtime_salary > 0 ? (activeDocument.payload?.breakdown?.ot_hours || 0) + 'h OT được duyệt' : 'Đi làm đủ công' }}</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(activeDocument.payload?.overtime_salary || 0) }}</td>
                                                </tr>
                                                <!-- Total A -->
                                                <tr class="bg-[#f1f5f9] font-bold text-slate-900">
                                                    <td class="border border-slate-300 px-2 py-1.5 text-center"></td>
                                                    <td class="border border-slate-300 px-3 py-1.5 uppercase" colspan="2">TỔNG THU NHẬP (A)</td>
                                                    <td class="border border-slate-300 px-3 py-1.5 text-right font-mono text-sm font-black">{{ formatMoney(calculateTotalIncome(activeDocument.payload)) }}</td>
                                                </tr>

                                                <!-- Group B: CÁC KHOẢN KHẤU TRỪ -->
                                                <tr class="bg-[#fff1f2] font-bold text-[#991b1b]">
                                                    <td class="border border-slate-300 px-2 py-1 text-center font-bold">B</td>
                                                    <td class="border border-slate-300 px-3 py-1 uppercase" colspan="3">CÁC KHOẢN KHẤU TRỪ</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-slate-300 px-2 py-1 text-center">1</td>
                                                    <td class="border border-slate-300 px-3 py-1 font-medium">Bảo hiểm xã hội (BHXH)</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-slate-600">8% x Lương đóng BH</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(activeDocument.payload?.bhxh_amount || 0) }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-slate-300 px-2 py-1 text-center">2</td>
                                                    <td class="border border-slate-300 px-3 py-1 font-medium">Bảo hiểm y tế (BHYT)</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-slate-600">1.5% x Lương đóng BH</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(activeDocument.payload?.bhyt_amount || 0) }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-slate-300 px-2 py-1 text-center">3</td>
                                                    <td class="border border-slate-300 px-3 py-1 font-medium">Bảo hiểm thất nghiệp (BHTN)</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-slate-600">1% x Lương đóng BH</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(activeDocument.payload?.bhtn_amount || 0) }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-slate-300 px-2 py-1 text-center">4</td>
                                                    <td class="border border-slate-300 px-3 py-1 font-medium">Thuế thu nhập cá nhân (TNCN)</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-slate-600">Tạm khấu trừ theo quy định</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(activeDocument.payload?.tax_deduction || 0) }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="border border-slate-300 px-2 py-1 text-center">5</td>
                                                    <td class="border border-slate-300 px-3 py-1 font-medium">Khấu trừ khác</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-slate-600">Ứng lương / phạt / khác (nếu có)</td>
                                                    <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(Number(activeDocument.payload?.other_deductions || 0) + Number(activeDocument.payload?.advance_payment || 0)) }}</td>
                                                </tr>
                                                <!-- Total B -->
                                                <tr class="bg-[#fff1f2] font-bold text-[#991b1b]">
                                                    <td class="border border-slate-300 px-2 py-1.5 text-center"></td>
                                                    <td class="border border-slate-300 px-3 py-1.5 uppercase" colspan="2">TỔNG KHẤU TRỪ (B)</td>
                                                    <td class="border border-slate-300 px-3 py-1.5 text-right font-mono text-sm font-black">{{ formatMoney(calculateTotalDeduction(activeDocument.payload)) }}</td>
                                                </tr>

                                                <!-- Group C: THỰC LĨNH -->
                                                <tr class="bg-[#1e3a5f] text-white">
                                                    <td class="border border-slate-700 px-2 py-2 text-center text-sm font-black">C</td>
                                                    <td class="border border-slate-700 px-3 py-2 text-sm font-black uppercase tracking-wide" colspan="2">THỰC LĨNH (A - B)</td>
                                                    <td class="border border-slate-700 px-3 py-2 text-right font-mono text-base font-black">{{ formatMoney(activeDocument.payload?.net_salary ?? activeDocument.total_amount) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- 3 & 4: THÔNG TIN BỔ SUNG & GHI CHÚ -->
                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <div class="inline-block rounded-t-md bg-[#1e3a5f] px-3 py-1 text-xs font-bold text-white uppercase tracking-wide">
                                            3. THÔNG TIN BỔ SUNG
                                        </div>
                                        <div class="rounded-b-md rounded-tr-md border border-slate-300 bg-white p-3 text-xs leading-relaxed text-slate-800 space-y-1">
                                            <div class="flex justify-between">
                                                <span class="font-medium text-slate-600">Số tài khoản:</span>
                                                <span class="font-bold text-slate-900 font-mono">{{ activeDocument.payload?.employee?.bank_account_number || '1903 1234 5678' }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="font-medium text-slate-600">Ngân hàng:</span>
                                                <span class="font-semibold text-slate-900">{{ activeDocument.payload?.employee?.bank_name || 'Vietcombank' }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="font-medium text-slate-600">Chi nhánh / Đơn vị:</span>
                                                <span class="font-semibold text-slate-900">{{ activeDocument.branch_name }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="font-medium text-slate-600">Nội dung chuyển khoản:</span>
                                                <span class="font-bold text-indigo-700 font-mono">LUONG T{{ activeDocument.payload?.period?.month }}/{{ activeDocument.payload?.period?.year }} - {{ activeDocument.payload?.employee?.code }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="inline-block rounded-t-md bg-[#1e3a5f] px-3 py-1 text-xs font-bold text-white uppercase tracking-wide">
                                            4. GHI CHÚ
                                        </div>
                                        <div class="rounded-b-md rounded-tr-md border border-slate-300 bg-white p-3 text-xs leading-relaxed text-slate-700 space-y-1">
                                            <p class="flex items-start gap-1.5">
                                                <span class="mt-1 h-1 w-1 shrink-0 rounded-full bg-slate-500"></span>
                                                <span>Phiếu lương được lập căn cứ vào bảng chấm công, kết quả đánh giá hiệu suất và các quy định hiện hành của công ty.</span>
                                            </p>
                                            <p class="flex items-start gap-1.5">
                                                <span class="mt-1 h-1 w-1 shrink-0 rounded-full bg-slate-500"></span>
                                                <span>Nếu có thắc mắc, vui lòng liên hệ phòng Nhân sự trong vòng 07 ngày kể từ ngày nhận lương.</span>
                                            </p>
                                            <p class="flex items-center gap-1.5 font-medium text-indigo-900">
                                                <Heart class="size-3.5 fill-rose-500 text-rose-500 shrink-0" />
                                                <span>Cảm ơn bạn đã đồng hành cùng Aventura!</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- 5. CHỮ KÝ 4 BÊN -->
                                <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-center text-xs text-slate-800">
                                    <div>
                                        <p class="font-bold uppercase text-[11px]">NGƯỜI LẬP PHIẾU</p>
                                        <p class="text-[10px] italic text-slate-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="mt-12 border-b border-dotted border-slate-300"></div>
                                        <p class="mt-1 text-[10px] text-slate-500">Ngày {{ todayDay }}/{{ todayMonth }}/20{{ todayYear }}</p>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase text-[11px]">QUẢN LÝ TRỰC TIẾP</p>
                                        <p class="text-[10px] italic text-slate-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="mt-12 border-b border-dotted border-slate-300"></div>
                                        <p class="mt-1 text-[10px] text-slate-500">Ngày {{ todayDay }}/{{ todayMonth }}/20{{ todayYear }}</p>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase text-[11px]">PHÒNG NHÂN SỰ</p>
                                        <p class="text-[10px] italic text-slate-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="mt-12 border-b border-dotted border-slate-300"></div>
                                        <p class="mt-1 text-[10px] text-slate-500">Ngày {{ todayDay }}/{{ todayMonth }}/20{{ todayYear }}</p>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase text-[11px]">NGƯỜI NHẬN LƯƠNG</p>
                                        <p class="text-[10px] italic text-slate-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="mt-12 border-b border-dotted border-slate-300"></div>
                                        <p class="mt-1 text-[10px] text-slate-500">Ngày {{ todayDay }}/{{ todayMonth }}/20{{ todayYear }}</p>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div class="mt-6 flex items-center justify-between border-t border-slate-300 pt-3 text-xs text-slate-600">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold tracking-wider text-[#1e3a5f]">AVENTURA</span>
                                        <span>|</span>
                                        <span class="italic text-slate-500 text-[11px]">Cùng nhau tạo nên những bữa ăn ngon hơn mỗi ngày</span>
                                    </div>
                                    <div class="font-serif italic text-slate-700">
                                        <span>Thank you!</span>
                                    </div>
                                </div>
                            </template>

                            <!-- 4. MẪU CHUNG CHO CÁC PHIẾU KHÁC (Xuất Kho Tổng / Đối Soát / Mua Hàng) -->
                            <template v-else>
                                <div class="flex items-start justify-between border-b-2 border-black pb-3">
                                    <div>
                                        <h4 class="font-black text-xs uppercase tracking-wider text-black">CÔNG TY TNHH AVENTURA</h4>
                                        <p class="text-[9px] text-neutral-600">Hệ thống quản lý chuỗi cung ứng & kho vận trung tâm</p>
                                    </div>
                                    <div class="text-right">
                                        <h2 class="text-base font-black uppercase text-black">{{ activeDocument.title }}</h2>
                                        <p class="mt-0.5 inline-block border border-black px-2 py-0.5 font-mono text-xs font-bold">
                                            Mã: {{ activeDocument.code }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-3 text-[10.5px] space-y-1">
                                    <p><span class="font-semibold">Đơn vị / Chi nhánh:</span> <span class="font-bold uppercase">{{ activeDocument.branch_name }}</span></p>
                                    <p><span class="font-semibold">Người lập phiếu:</span> <span class="font-bold">{{ activeDocument.created_by_name }}</span> &nbsp;&nbsp; <span class="font-semibold">Thời gian:</span> {{ activeDocument.date_formatted }}</p>
                                    <p><span class="font-semibold">Tổng giá trị:</span> <span class="font-bold font-mono text-base">{{ formatCurrency(activeDocument.total_amount) }}</span></p>
                                    <p><span class="font-semibold">Trạng thái:</span> <span class="font-bold">{{ activeDocument.status_label.label }}</span></p>
                                </div>

                                <div v-if="activeDocument.payload?.items" class="mt-4">
                                    <h5 class="mb-1 font-bold uppercase tracking-wider text-[11px]">Danh Sách Chi Tiết</h5>
                                    <table class="w-full border-collapse border border-black text-center text-[10px]">
                                        <thead>
                                            <tr class="bg-neutral-100 font-bold">
                                                <th class="border border-black p-1">STT</th>
                                                <th class="border border-black p-1 text-left">Tên Hàng Hóa / Nguyên Liệu</th>
                                                <th class="border border-black p-1">ĐVT</th>
                                                <th class="border border-black p-1">Số Lượng</th>
                                                <th class="border border-black p-1 text-right">Đơn Giá (VNĐ)</th>
                                                <th class="border border-black p-1 text-right">Thành Tiền (VNĐ)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(item, idx) in (activeDocument.payload.items || [])" :key="item.id || idx">
                                                <td class="border border-black p-1 font-mono">{{ Number(idx) + 1 }}</td>
                                                <td class="border border-black p-1 text-left font-semibold">{{ item.ingredient?.name || item.name || 'Hàng hóa' }}</td>
                                                <td class="border border-black p-1">{{ item.unit_symbol || item.ingredient?.unit?.symbol || 'đv' }}</td>
                                                <td class="border border-black p-1 font-mono font-bold">{{ formatQuantity(item.actual_dispatched_quantity ?? item.approved_quantity ?? item.requested_quantity ?? item.quantity ?? 1) }}</td>
                                                <td class="border border-black p-1 text-right font-mono">{{ formatCurrency(item.unit_cost ?? item.ingredient?.average_cost ?? 0) }}</td>
                                                <td class="border border-black p-1 text-right font-mono font-bold">{{ formatCurrency(Number(item.actual_dispatched_quantity ?? item.approved_quantity ?? item.requested_quantity ?? item.quantity ?? 1) * Number(item.unit_cost ?? item.ingredient?.average_cost ?? 0)) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-8 grid grid-cols-2 text-center text-[10.5px]">
                                    <div>
                                        <p class="font-bold uppercase">Người lập phiếu</p>
                                        <p class="text-[9px] italic text-neutral-600">(Ký, ghi rõ họ tên)</p>
                                        <div class="h-16"></div>
                                        <p class="font-bold">{{ activeDocument.created_by_name }}</p>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase">Chủ doanh nghiệp / Giám đốc</p>
                                        <p class="text-[9px] italic text-neutral-600">(Ký duyệt, đóng dấu)</p>
                                        <div class="h-16"></div>
                                        <p class="font-bold text-emerald-700">[Đã Tiếp Nhận & Phê Duyệt]</p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Modal Footer (Hidden on Print) -->
                    <div class="flex items-center justify-between border-t border-border bg-muted/40 p-4 px-6 print:hidden">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground">
                                Trạng thái: <strong class="text-foreground">{{ activeDocument.status_label.label }}</strong>
                            </span>
                            <span
                                v-if="activeDocument.has_discrepancy"
                                class="inline-flex items-center gap-1 text-xs font-bold text-rose-600 dark:text-rose-400 ml-2"
                            >
                                <AlertTriangle class="size-3.5" />
                                {{ activeDocument.discrepancy_note || 'Có sai lệch cần lưu ý' }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                @click="closeViewer"
                                variant="outline"
                                size="sm"
                                class="rounded-xl border-border px-4 text-xs font-semibold hover:bg-accent"
                            >
                                Đóng
                            </Button>

                            <Button
                                @click="acknowledgeDocument"
                                size="sm"
                                :disabled="isSubmitting"
                                class="gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50"
                            >
                                <CheckCircle2 class="size-4" />
                                Xác Nhận Đã Tiếp Nhận & Ký Duyệt
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
</template>

<style scoped>
@media print {
    @page {
        size: A4;
        margin: 8mm 10mm;
    }

    :global(body) {
        background: #ffffff !important;
    }

    :global(body *) {
        visibility: hidden !important;
    }

    #a4-document-sheet,
    #a4-document-sheet * {
        visibility: visible !important;
    }

    #a4-document-sheet {
        position: fixed !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        box-shadow: none !important;
        background: #ffffff !important;
        color: #000000 !important;
        z-index: 999999 !important;
    }
}
</style>
