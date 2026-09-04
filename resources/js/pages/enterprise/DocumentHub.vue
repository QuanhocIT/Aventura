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
    type: 'shift_closing' | 'stock_transfer' | 'supply_request' | 'receiving_report' | 'purchase_order' | 'inventory_count';
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

const typeTabs = [
    { key: 'all', label: 'Tất cả phiếu' },
    { key: 'shift_closing', label: 'Phiếu Chốt Ca', icon: ScrollText },
    { key: 'stock_transfer', label: 'Phiếu Điều Chuyển', icon: ArrowLeftRight },
    { key: 'supply_request', label: 'Phiếu Xuất Kho Tổng', icon: PackageCheck },
    { key: 'receiving_report', label: 'Biên Bản Đối Soát', icon: FileCheck2 },
    { key: 'purchase_order', label: 'Phiếu Đặt Hàng NCC', icon: ShoppingCart },
    { key: 'inventory_count', label: 'Phiếu Kiểm Kê', icon: ClipboardCheck },
];

const getTypeIcon = (type: string) => {
    switch (type) {
        case 'shift_closing': return ScrollText;
        case 'stock_transfer': return ArrowLeftRight;
        case 'supply_request': return PackageCheck;
        case 'receiving_report': return FileCheck2;
        case 'purchase_order': return ShoppingCart;
        case 'inventory_count': return ClipboardCheck;
        default: return ScrollText;
    }
};

const getTypeBadgeColor = (type: string) => {
    switch (type) {
        case 'shift_closing': return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20';
        case 'stock_transfer': return 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20';
        case 'supply_request': return 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20';
        case 'receiving_report': return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20';
        case 'purchase_order': return 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20';
        case 'inventory_count': return 'bg-teal-500/10 text-teal-600 dark:text-teal-400 border-teal-500/20';
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

                            <!-- 2. MẪU: PHIẾU ĐIỀU CHUYỂN NGUYÊN LIỆU (Matching User Image 1) -->
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

                            <!-- 3. MẪU CHUNG CHO CÁC PHIẾU KHÁC (Xuất Kho Tổng / Đối Soát / Mua Hàng) -->
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
