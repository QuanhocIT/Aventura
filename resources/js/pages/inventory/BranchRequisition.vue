<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowLeft,
    Building2,
    Camera,
    CheckCircle2,
    Clock,
    Eye,
    FileCheck2,
    FileSignature,
    Package,
    PackageCheck,
    Printer,
    Plus,
    Search,
    Send,
    ShoppingCart,
    Trash2,
    Truck,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    centralBranch: any;
    branches: Array<any>;
    activeBranchId: number;
    supplyRequests: Array<any>;
    ingredients: Array<any>;
    canCreateRequests: boolean;
    canReceiveRequests: boolean;
}>();

const isCreateModalOpen = ref(false);
const isDetailModalOpen = ref(false);
const isReceiveMode = ref(false);
const isProcessing = ref(false);
const searchQuery = ref('');
const selectedRequest = ref<any>(null);
const selectedReceivingReport = ref<any>(null);
const receivingStage = ref<'input' | 'report'>('input');
const receiveNotes = ref('');
const receiptPhoto = ref<File | null>(null);
const receiverSignature = ref<File | null>(null);
const receiveTemperatureMin = ref<number | string>('');
const receiveTemperatureMax = ref<number | string>('');
let livePollingTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    livePollingTimer = setInterval(() => {
        if (
            !document.hidden &&
            !isCreateModalOpen.value &&
            !isDetailModalOpen.value &&
            !isReceiveMode.value &&
            !isProcessing.value
        ) {
            router.reload({
                only: ['supplyRequests'],
                preserveScroll: true,
                preserveState: true,
            });
        }
    }, 3000);

    const onVisibility = () => {
        if (
            !document.hidden &&
            !isCreateModalOpen.value &&
            !isDetailModalOpen.value
        ) {
            router.reload({
                only: ['supplyRequests'],
                preserveScroll: true,
                preserveState: true,
            });
        }
    };

    document.addEventListener('visibilitychange', onVisibility);
});

onBeforeUnmount(() => {
    if (livePollingTimer) {
        clearInterval(livePollingTimer);
        livePollingTimer = null;
    }
});

// Create Form State
const newRequestForm = ref({
    to_branch_id: props.activeBranchId,
    requested_delivery_date: '',
    notes: '',
    items: [] as Array<{
        ingredient_id: number;
        name: string;
        unit_symbol: string;
        quantity: number;
        unit_cost: number;
    }>,
});

const activeBranch = computed(() =>
    props.branches?.find((b) => b.id === props.activeBranchId),
);

// KPI Stats
const stats = computed(() => {
    const list = props.supplyRequests || [];

    return {
        total: list.length,
        pending: list.filter((r) => r.status === 'pending').length,
        delivering: list.filter((r) =>
            ['dispatched', 'partial_received', 'disputed', 'receiving_review'].includes(r.status),
        ).length,
        completed: list.filter((r) => r.status === 'completed').length,
    };
});

// Search Filter
const filteredRequests = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();

    if (!q) {
        return props.supplyRequests || [];
    }

    return (props.supplyRequests || []).filter((req) => {
        return (
            req.request_code?.toLowerCase().includes(q) ||
            req.from_branch?.name?.toLowerCase().includes(q) ||
            req.creator?.name?.toLowerCase().includes(q)
        );
    });
});

const openCreateModal = () => {
    newRequestForm.value = {
        to_branch_id: props.activeBranchId,
        requested_delivery_date: new Date().toISOString().split('T')[0],
        notes: '',
        items: [],
    };
    addItemRow();
    isCreateModalOpen.value = true;
};

const addItemRow = () => {
    if (props.ingredients && props.ingredients.length > 0) {
        const firstIng = props.ingredients[0];

        newRequestForm.value.items.push({
            ingredient_id: firstIng.id,
            name: firstIng.name,
            unit_symbol: firstIng.unit?.symbol || 'kg',
            quantity: 1,
            unit_cost: firstIng.average_cost || 0,
        });
    }
};

const removeItemRow = (index: number) => {
    newRequestForm.value.items.splice(index, 1);
};

const onIngredientSelect = (index: number, ingId: number) => {
    const ing = props.ingredients?.find((i) => i.id === ingId);

    if (ing) {
        newRequestForm.value.items[index].name = ing.name;
        newRequestForm.value.items[index].unit_symbol =
            ing.unit?.symbol || 'kg';
        newRequestForm.value.items[index].unit_cost = ing.average_cost || 0;
    }
};

const calculatedFormTotal = computed(() => {
    return newRequestForm.value.items.reduce(
        (sum, item) => sum + item.quantity * item.unit_cost,
        0,
    );
});

const submitRequisition = async () => {
    if (newRequestForm.value.items.length === 0) {
        toast.error('Vui lòng thêm ít nhất 1 nguyên liệu vào đơn đặt hàng.');

        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(
            '/api/supply-requests',
            newRequestForm.value,
        );

        if (res.data.success) {
            toast.success('Đã gửi yêu cầu cấp phát đến Kho Tổng thành công!');
            isCreateModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Có lỗi xảy ra khi gửi đơn.');
    } finally {
        isProcessing.value = false;
    }
};

const setupSelectedRequest = (req: any) => {
    selectedRequest.value = JSON.parse(JSON.stringify(req));
    receiveNotes.value = '';
    receiptPhoto.value = null;
    receiverSignature.value = null;
    receiveTemperatureMin.value = '';
    receiveTemperatureMax.value = '';
    selectedRequest.value.items?.forEach((item: any) => {
        item.received_good_quantity = Number(
            item.received_good_quantity ??
                item.received_quantity ??
                item.actual_dispatched_quantity ??
                item.approved_quantity ??
                item.requested_quantity ??
                0,
        );
        item.received_damaged_quantity = Number(
            item.received_damaged_quantity ?? 0,
        );
        item.received_expired_quantity = Number(
            item.received_expired_quantity ?? 0,
        );
        item.received_missing_quantity = Number(
            item.received_missing_quantity ?? 0,
        );
        item.received_wrong_item_quantity = Number(
            item.received_wrong_item_quantity ?? 0,
        );
        item.received_note = item.received_note ?? '';
    });
};

const openDetailModal = (req: any) => {
    if (req.receiving_report?.status === 'pending_branch_confirmation') {
        openReceivingReport(req);

        return;
    }

    setupSelectedRequest(req);
    receivingStage.value = 'input';
    selectedReceivingReport.value = null;
    isReceiveMode.value = false;
    isDetailModalOpen.value = true;
};

const openReceiveModal = (req: any) => {
    if (req.receiving_report?.status === 'pending_branch_confirmation') {
        openReceivingReport(req);

        return;
    }

    setupSelectedRequest(req);
    receivingStage.value = 'input';
    selectedReceivingReport.value = null;
    isReceiveMode.value = true;
    isDetailModalOpen.value = true;
};

const openReceivingReport = (req: any, report?: any) => {
    setupSelectedRequest(req);
    selectedReceivingReport.value = JSON.parse(
        JSON.stringify(report || req.receiving_report),
    );
    receivingStage.value = 'report';
    isReceiveMode.value = false;
    isDetailModalOpen.value = true;
};

const loadReportIntoReceiveForm = () => {
    const reportItems = selectedReceivingReport.value?.items || [];
    selectedRequest.value?.items?.forEach((item: any) => {
        const reportItem = reportItems.find(
            (row: any) => Number(row.supply_request_item_id) === Number(item.id),
        );

        if (!reportItem) {
            return;
        }

        item.received_good_quantity = Number(reportItem.submitted_good_quantity || 0);
        item.received_damaged_quantity = Number(reportItem.submitted_damaged_quantity || 0);
        item.received_expired_quantity = Number(reportItem.submitted_expired_quantity || 0);
        item.received_missing_quantity = Number(reportItem.submitted_shortage_quantity || 0);
        item.received_wrong_item_quantity = Number(reportItem.submitted_wrong_item_quantity || 0);
        item.received_note = reportItem.submitted_note || '';
    });
    receivingStage.value = 'input';
    isReceiveMode.value = true;
};

const confirmReceivingReport = async () => {
    if (!selectedReceivingReport.value) {
        return;
    }

    if (!window.confirm('Xác nhận biên bản lần cuối? Hàng đạt sẽ nhập kho, hàng lỗi sẽ được cách ly và số liệu sẽ khóa để truy vết.')) {
        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(
            `/api/supply-requests/${selectedRequest.value.id}/receiving-report/confirm`,
        );

        if (res.data.success) {
            toast.success(res.data.message || 'Đã xác nhận biên bản nhận hàng.');
            isDetailModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể xác nhận biên bản nhận hàng.');
    } finally {
        isProcessing.value = false;
    }
};

const isReceiveReady = (request: any) => {
    if (
        !request ||
        !['dispatched', 'partial_received', 'disputed'].includes(request.status)
    ) {
        return false;
    }

    if (!props.canReceiveRequests) {
        return false;
    }

    return Boolean(
        !request.transporter_id ||
            (request.delivery_confirmed_at &&
                request.delivery_task?.status === 'completed'),
    );
};

const setEvidenceFile = (type: 'photo' | 'signature', event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] || null;

    if (type === 'photo') {
        receiptPhoto.value = file;
    } else {
        receiverSignature.value = file;
    }
};

const getItemSum = (item: any): number => {
    return (
        Number(item.received_good_quantity || 0) +
        Number(item.received_damaged_quantity || 0) +
        Number(item.received_expired_quantity || 0) +
        Number(item.received_missing_quantity || 0) +
        Number(item.received_wrong_item_quantity || 0)
    );
};

const getItemMax = (item: any): number => {
    return Number(
        item.actual_dispatched_quantity ??
            item.approved_quantity ??
            item.requested_quantity ??
            0,
    );
};

const getItemDiff = (item: any): number => {
    return getItemSum(item) - getItemMax(item);
};

const isItemMismatch = (item: any): boolean => {
    return Math.abs(getItemDiff(item)) > 0.0005;
};

const hasAnyItemMismatch = computed(() => {
    if (!selectedRequest.value?.items) {
        return false;
    }

    return selectedRequest.value.items.some((item: any) => isItemMismatch(item));
});

const receiveGoods = async () => {
    if (!selectedRequest.value) {
        return;
    }

    if (hasAnyItemMismatch.value) {
        toast.error(
            'Tổng số lượng kiểm đếm (Đạt + Hỏng + Hết hạn + Thiếu) phải bằng chính xác số lượng Kho duyệt.',
        );

        return;
    }

    isProcessing.value = true;

    try {
        const payload = new FormData();

        selectedRequest.value.items?.forEach((item: any, index: number) => {
            payload.append(`items[${index}][id]`, String(item.id));
            const good = Number(
                item.received_good_quantity ?? 0,
            );
            const damaged = Number(item.received_damaged_quantity ?? 0);
            const expired = Number(item.received_expired_quantity ?? 0);
            const missing = Number(item.received_missing_quantity ?? 0);
            const wrong = Number(item.received_wrong_item_quantity ?? 0);
            const total = good + damaged + expired + wrong;

            payload.append(`items[${index}][received_quantity]`, String(total));
            payload.append(
                `items[${index}][received_good_quantity]`,
                String(good),
            );
            payload.append(
                `items[${index}][received_damaged_quantity]`,
                String(damaged),
            );
            payload.append(
                `items[${index}][received_expired_quantity]`,
                String(expired),
            );
            payload.append(
                `items[${index}][received_missing_quantity]`,
                String(missing),
            );
            payload.append(
                `items[${index}][received_wrong_item_quantity]`,
                String(wrong),
            );

            if (item.received_note?.trim()) {
                payload.append(
                    `items[${index}][received_note]`,
                    item.received_note.trim(),
                );
            }

            const dispatched = Number(
                item.actual_dispatched_quantity ??
                    item.approved_quantity ??
                    item.requested_quantity ??
                    0,
            );

            payload.append(
                `items[${index}][received_condition]`,
                damaged + expired + wrong > 0
                    ? 'damaged'
                    : (missing > 0 || total < dispatched)
                      ? 'shortage'
                      : 'good',
            );
        });

        if (receiveNotes.value.trim()) {
            payload.append('notes', receiveNotes.value.trim());
        }

        if (receiptPhoto.value) {
            payload.append('receipt_photo', receiptPhoto.value);
        }

        if (receiverSignature.value) {
            payload.append('receiver_signature', receiverSignature.value);
        }

        if (receiveTemperatureMin.value !== '') {
            payload.append(
                'received_temperature_min_c',
                String(receiveTemperatureMin.value),
            );
        }

        if (receiveTemperatureMax.value !== '') {
            payload.append(
                'received_temperature_max_c',
                String(receiveTemperatureMax.value),
            );
        }

        const res = await axios.post(
            `/api/supply-requests/${selectedRequest.value.id}/receive`,
            payload,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
            },
        );

        if (res.data.success) {
            if (res.data.requires_receiving_report) {
                toast.warning(
                    res.data.message || 'Đối soát có vấn đề, vui lòng lập biên bản trước khi nhập kho.',
                );
                selectedReceivingReport.value = res.data.data;
                receivingStage.value = 'report';
                isReceiveMode.value = false;
                router.reload({
                    only: ['supplyRequests'],
                    preserveScroll: true,
                    preserveState: true,
                });
            } else {
                toast.success(
                    res.data.message || 'Đã tự động nhập kho toàn bộ nguyên liệu đạt.',
                );
                isDetailModalOpen.value = false;
                router.reload();
            }
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Lỗi khi nhận hàng.');
    } finally {
        isProcessing.value = false;
    }
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount || 0);
};

const formatQuantity = (qty: any) => {
    if (qty === null || qty === undefined || qty === '') {
        return '0';
    }

    const num = Number(qty);

    if (isNaN(num)) {
        return '0';
    }

    return new Intl.NumberFormat('vi-VN', {
        maximumFractionDigits: 3,
    }).format(num);
};

const getReceivedTemperature = (request: any): string => {
    const item = request?.items?.find(
        (row: any) =>
            row.received_temperature_min_c !== null &&
            row.received_temperature_min_c !== undefined,
    );

    if (!item) {
        return 'Không đo';
    }

    const min = item.received_temperature_min_c;
    const max = item.received_temperature_max_c ?? min;

    return `${min}°C ~ ${max}°C`;
};

const isReportIssue = (item: any): boolean =>
    Number(item?.submitted_damaged_quantity || 0) > 0 ||
    Number(item?.submitted_expired_quantity || 0) > 0 ||
    Number(item?.submitted_wrong_item_quantity || 0) > 0 ||
    Number(item?.submitted_shortage_quantity || 0) > 0;

const getReceivingTransporterName = (request: any, report?: any): string =>
    request?.transporter?.name ||
    report?.transporter?.name ||
    report?.transporter_name_snapshot ||
    '---';

const printReceivingReport = () => {
    window.print();
};

const formatDate = (dt: string) => {
    if (!dt) {
        return '-';
    }

    return new Date(dt).toLocaleString('vi-VN', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const getStatusBadge = (status: string, req?: any) => {
    switch (status) {
        case 'pending':
            return {
                label: 'Chờ duyệt đơn',
                color: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
            };
        case 'approved':
            return {
                label: 'Đã duyệt (Chờ soạn)',
                color: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
            };
        case 'preparing':
            return {
                label: 'Kho đang soạn',
                color: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20',
            };
        case 'prepared':
        case 'dispatch_pending_approval':
            return {
                label: 'Chờ duyệt xuất',
                color: 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border-cyan-500/20',
            };
        case 'dispatched':
            if (req?.transporter_id && !req?.delivery_confirmed_at) {
                return {
                    label: 'Đang vận chuyển giao hàng',
                    color: 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20',
                };
            }

            return {
                label: 'Đã giao tới (Chờ nhận)',
                color: 'bg-teal-500/10 text-teal-600 dark:text-teal-400 border-teal-500/20',
            };
        case 'completed':
            return {
                label: 'Đã nhận hàng',
                color: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
            };
        case 'partial_received':
            return {
                label: 'Đã nhận một phần',
                color: 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20',
            };
        case 'disputed':
            return {
                label: 'Đang tranh chấp',
                color: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
            };
        case 'receiving_review':
            return {
                label: 'Chờ xác nhận biên bản nhận hàng',
                color: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
            };
        case 'rejected':
            return {
                label: 'Kho từ chối',
                color: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
            };
        case 'cancelled':
            return {
                label: 'Đã hủy',
                color: 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20',
            };
        default:
            return {
                label: status,
                color: 'bg-muted text-muted-foreground border-border',
            };
    }
};

const isReadyForReceiving = (req: any): boolean => {
    if (!props.canReceiveRequests) {
        return false;
    }

    if (['partial_received', 'disputed'].includes(req.status)) {
        return true;
    }

    if (req.status === 'dispatched') {
        // Nếu có tài xế/nhân viên giao hàng, phải chờ họ bấm "Giao hàng thành công" (đã có delivery_confirmed_at)
        if (req.transporter_id) {
            return !!req.delivery_confirmed_at;
        }

        return true;
    }

    return false;
};

const hasPendingReceivingReport = (req: any): boolean =>
    req?.receiving_report?.status === 'pending_branch_confirmation';
</script>

<template>
    <Head title="Đặt Hàng & Nhận Hàng Kho Tổng" />

    <div class="w-full space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- Modern Header -->
        <div
            class="flex flex-col gap-4 rounded-3xl border border-border/80 bg-card p-5 shadow-xs sm:p-6 md:flex-row md:items-center md:justify-between"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-sm shadow-primary/20"
                >
                    <ShoppingCart class="size-6" />
                </div>
                <div>
                    <h1
                        class="text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                    >
                        Đặt Hàng & Nhận Hàng Kho Tổng
                    </h1>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Gửi đơn yêu cầu bổ sung nguyên liệu daily đến Kho Tổng và kiểm đếm nhận hàng
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div
                    class="flex items-center gap-2 rounded-xl border border-border bg-muted/40 px-3.5 py-2 text-xs font-semibold text-foreground shadow-2xs backdrop-blur-sm"
                >
                    <Building2 class="size-4 text-primary" />
                    <span>
                        Chi nhánh:
                        <strong class="text-foreground">{{
                            activeBranch?.name || 'Tất cả'
                        }}</strong>
                    </span>
                </div>

                <Button
                    v-if="canCreateRequests"
                    @click="openCreateModal"
                    class="gap-2 rounded-xl bg-emerald-600 font-semibold text-white shadow-sm hover:bg-emerald-700"
                >
                    <Plus class="size-4" />
                    Lập Đơn Đặt Hàng Mới
                </Button>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <Card class="border-border shadow-2xs">
                <CardContent class="p-4 sm:p-5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-muted-foreground">TỔNG ĐƠN ĐẶT</span>
                        <div class="flex size-8 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-500">
                            <Package class="size-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-foreground">
                        {{ stats.total }}
                    </div>
                    <div class="mt-1 text-[11px] text-muted-foreground">
                        Toàn bộ yêu cầu cấp phát
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border shadow-2xs">
                <CardContent class="p-4 sm:p-5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-muted-foreground">CHỜ KHO DUYỆT</span>
                        <div class="flex size-8 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                            <Clock class="size-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
                        {{ stats.pending }}
                    </div>
                    <div class="mt-1 text-[11px] text-muted-foreground">
                        Chờ Trưởng kho duyệt
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border shadow-2xs">
                <CardContent class="p-4 sm:p-5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-muted-foreground">ĐANG GIAO / CHỜ NHẬN</span>
                        <div class="flex size-8 items-center justify-center rounded-xl bg-purple-500/10 text-purple-500">
                            <Truck class="size-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-purple-600 dark:text-purple-400">
                        {{ stats.delivering }}
                    </div>
                    <div class="mt-1 text-[11px] text-muted-foreground">
                        Hàng đang trên đường vận chuyển
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border shadow-2xs">
                <CardContent class="p-4 sm:p-5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-muted-foreground">ĐÃ NHẬN HÀNG</span>
                        <div class="flex size-8 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500">
                            <CheckCircle2 class="size-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ stats.completed }}
                    </div>
                    <div class="mt-1 text-[11px] text-muted-foreground">
                        Đã kiểm đếm & nhập kho chi nhánh
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Orders Table Card -->
        <Card class="border-border shadow-sm">
            <CardHeader class="border-b border-border bg-muted/20 px-4 py-4 sm:px-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle class="text-base font-bold text-foreground">
                            Nhận Hàng & Lịch Sử Đơn Kho Tổng
                        </CardTitle>
                        <CardDescription class="mt-0.5 text-xs">
                            Theo dõi tiến độ duyệt, giao vận và xác nhận kiểm đếm nhận hàng
                        </CardDescription>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full sm:w-72">
                        <Search class="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Tìm mã đơn, người tạo..."
                            class="h-9 rounded-xl border-input bg-background pl-9 pr-7 text-xs shadow-xs focus-visible:ring-2 focus-visible:ring-primary/20"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute top-2.5 right-2.5 flex size-4 items-center justify-center rounded-full text-[10px] text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            ✕
                        </button>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-border bg-muted/50 font-semibold text-muted-foreground">
                            <tr>
                                <th class="p-3 pl-4 sm:pl-6">Mã Đơn</th>
                                <th class="p-3">Kho Xuất</th>
                                <th class="p-3">Số Mặt Hàng</th>
                                <th class="p-3">Tổng Tiền Dự Kiến</th>
                                <th class="p-3">Trạng Thái Kho</th>
                                <th class="p-3">Ngày Lập</th>
                                <th class="p-3 pr-4 text-right sm:pr-6 whitespace-nowrap">
                                    Thao Tác
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="filteredRequests.length === 0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <Package class="size-8 text-muted-foreground/40" />
                                        <span>Không tìm thấy đơn hàng nào</span>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="req in filteredRequests"
                                :key="req.id"
                                class="transition hover:bg-muted/40"
                            >
                                <td class="p-3 pl-4 font-mono font-bold text-primary sm:pl-6">
                                    {{ req.request_code }}
                                </td>
                                <td class="p-3 font-medium text-foreground">
                                    {{ req.from_branch?.name || 'Kho Tổng độc lập' }}
                                </td>
                                <td class="p-3 text-muted-foreground">
                                    {{ req.items?.length || 0 }} nguyên liệu
                                </td>
                                <td class="p-3 font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ formatCurrency(req.total_amount) }}
                                </td>
                                <td class="p-3">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold',
                                            getStatusBadge(req.status, req).color,
                                        ]"
                                    >
                                        {{ getStatusBadge(req.status, req).label }}
                                    </span>
                                </td>
                                <td class="p-3 text-muted-foreground">
                                    {{ formatDate(req.created_at) }}
                                </td>
                                <td class="p-3 pr-4 text-right sm:pr-6">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Nút Xem Chi Tiết riêng biệt -->
                                        <Button
                                            @click="openDetailModal(req)"
                                            size="sm"
                                            variant="outline"
                                            class="h-8 gap-1.5 rounded-lg border-border px-2.5 text-xs font-semibold hover:bg-accent"
                                        >
                                            <Eye class="size-3.5 text-muted-foreground" />
                                            <span>Chi tiết</span>
                                        </Button>

                                        <Button
                                            v-if="hasPendingReceivingReport(req)"
                                            @click="openReceivingReport(req)"
                                            size="sm"
                                            class="h-8 gap-1.5 rounded-lg bg-amber-600 px-2.5 text-xs font-bold text-white shadow-xs hover:bg-amber-700"
                                        >
                                            <FileCheck2 class="size-3.5" />
                                            <span>Lập biên bản</span>
                                        </Button>

                                        <!-- Nút Nhận Hàng tách riêng, chỉ hiển thị khi tài xế đã giao hàng tới nơi thành công -->
                                        <Button
                                            v-if="isReadyForReceiving(req)"
                                            @click="openReceiveModal(req)"
                                            size="sm"
                                            class="h-8 gap-1.5 rounded-lg bg-emerald-600 px-2.5 text-xs font-bold text-white shadow-xs hover:bg-emerald-700"
                                        >
                                            <PackageCheck class="size-3.5" />
                                            <span>Nhận hàng</span>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Create Requisition Modal -->
        <Teleport to="body">
            <div
                v-if="isCreateModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-md"
            >
                <div
                    class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl border border-border bg-card shadow-2xl"
                >
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between border-b border-border bg-muted/40 p-5">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-xs">
                                <ShoppingCart class="size-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-foreground">
                                    Lập Đơn Đặt Hàng Gửi Kho Tổng
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    Kho Tổng sẽ duyệt và giao nguyên liệu tới Chi nhánh {{ activeBranch?.name }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="isCreateModalOpen = false"
                            class="flex size-8 items-center justify-center rounded-xl text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            <X class="size-5" />
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="flex-1 space-y-5 overflow-y-auto p-6 text-xs">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="mb-1.5 block font-semibold text-foreground">
                                    Ngày cần giao hàng
                                </label>
                                <Input
                                    v-model="newRequestForm.requested_delivery_date"
                                    type="date"
                                    class="rounded-xl border-input bg-background text-xs"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block font-semibold text-foreground">
                                    Ghi chú cho Kho Tổng
                                </label>
                                <Input
                                    v-model="newRequestForm.notes"
                                    placeholder="VD: Giao trước 8h sáng..."
                                    class="rounded-xl border-input bg-background text-xs"
                                />
                            </div>
                        </div>

                        <!-- Items selection table -->
                        <div>
                            <div class="mb-2.5 flex items-center justify-between">
                                <h4 class="font-bold text-foreground">
                                    Danh Sách Nguyên Liệu Cần Nhập
                                </h4>
                                <Button
                                    @click="addItemRow"
                                    size="sm"
                                    variant="outline"
                                    class="h-8 gap-1.5 rounded-xl border-border text-xs font-semibold"
                                >
                                    <Plus class="size-3.5" />
                                    Thêm Nguyên Liệu
                                </Button>
                            </div>

                            <div class="overflow-hidden rounded-2xl border border-border">
                                <table class="w-full text-left">
                                    <thead class="border-b border-border bg-muted/50 font-semibold text-muted-foreground">
                                        <tr>
                                            <th class="p-3 pl-3.5">Chọn Nguyên Liệu</th>
                                            <th class="p-3 text-center">Đơn Vị</th>
                                            <th class="p-3 text-right">Số Lượng Đặt</th>
                                            <th class="p-3 text-right">Đơn Giá Kho</th>
                                            <th class="p-3 text-right">Thành Tiền</th>
                                            <th class="w-12 p-3 text-center">Xóa</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr
                                            v-for="(item, idx) in newRequestForm.items"
                                            :key="idx"
                                            class="hover:bg-muted/30"
                                        >
                                            <td class="p-3 pl-3.5">
                                                <select
                                                    v-model="item.ingredient_id"
                                                    @change="onIngredientSelect(idx, Number(($event.target as HTMLSelectElement).value))"
                                                    class="w-full rounded-xl border border-input bg-background px-3 py-1.5 font-medium text-foreground focus:ring-2 focus:ring-primary/20 focus:outline-none"
                                                >
                                                    <option
                                                        v-for="ing in ingredients"
                                                        :key="ing.id"
                                                        :value="ing.id"
                                                    >
                                                        {{ ing.name }} ({{ ing.sku || 'No SKU' }})
                                                    </option>
                                                </select>
                                            </td>
                                            <td class="p-3 text-center font-mono text-muted-foreground">
                                                {{ item.unit_symbol }}
                                            </td>
                                            <td class="p-3 text-right">
                                                <input
                                                    type="number"
                                                    step="1"
                                                    min="1"
                                                    v-model.number="item.quantity"
                                                    class="w-24 rounded-xl border border-input bg-background px-3 py-1 text-right font-bold text-primary [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none focus:ring-2 focus:ring-primary/20 focus:outline-none"
                                                />
                                            </td>
                                            <td class="p-3 text-right text-muted-foreground">
                                                {{ formatCurrency(item.unit_cost) }}
                                            </td>
                                            <td class="p-3 text-right font-bold text-emerald-600 dark:text-emerald-400">
                                                {{ formatCurrency(item.quantity * item.unit_cost) }}
                                            </td>
                                            <td class="p-3 text-center">
                                                <button
                                                    @click="removeItemRow(idx)"
                                                    class="flex size-7 items-center justify-center rounded-lg text-rose-500 hover:bg-rose-500/10 hover:text-rose-600"
                                                >
                                                    <Trash2 class="size-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex items-center justify-between border-t border-border bg-muted/30 p-4 px-6">
                        <div class="text-xs text-muted-foreground">
                            Tổng tiền dự kiến:
                            <strong class="ml-1 text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                {{ formatCurrency(calculatedFormTotal) }}
                            </strong>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                @click="isCreateModalOpen = false"
                                variant="ghost"
                                size="sm"
                                class="rounded-xl text-xs font-semibold"
                            >
                                Hủy
                            </Button>
                            <Button
                                @click="submitRequisition"
                                size="sm"
                                :disabled="isProcessing"
                                class="gap-1.5 rounded-xl bg-primary text-xs font-bold text-primary-foreground hover:bg-primary/90"
                            >
                                <Send class="size-3.5" />
                                Gửi Đơn Đến Kho Tổng
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Detail / Receive Goods Modal -->
        <Teleport to="body">
            <div
                v-if="isDetailModalOpen && selectedRequest"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-md"
                @click.self="isDetailModalOpen = false"
            >
                <div
                    class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-border/80 bg-card shadow-2xl"
                >
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between border-b border-border bg-muted/40 p-5 px-6">
                        <div class="flex flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="font-mono text-lg font-extrabold tracking-tight text-primary">
                                    {{ selectedRequest.request_code }}
                                </span>
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full border px-3 py-0.5 text-xs font-bold shadow-2xs',
                                        getStatusBadge(selectedRequest.status, selectedRequest).color,
                                    ]"
                                >
                                    {{ getStatusBadge(selectedRequest.status, selectedRequest).label }}
                                </span>
                                <span
                                    v-if="isReceiveMode"
                                    class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-bold text-emerald-600 dark:text-emerald-400"
                                >
                                    Nghiệm Thu & Nhập Kho
                                </span>
                                <span
                                    v-else-if="receivingStage !== 'report'"
                                    class="rounded-full border border-primary/30 bg-primary/10 px-2.5 py-0.5 text-[11px] font-bold text-primary"
                                >
                                    Xem Chi Tiết Đơn
                                </span>
                                <span
                                    v-else
                                    class="rounded-md border border-slate-300 bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    Lập Biên Bản Đối Soát
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span>Xuất từ: <strong class="text-foreground">{{ selectedRequest.from_branch?.name || 'Kho Tổng Sai Gon Diner' }}</strong></span>
                                <span v-if="selectedRequest.created_at">• Ngày tạo: {{ formatDate(selectedRequest.created_at) }}</span>
                                <span v-if="getReceivingTransporterName(selectedRequest, selectedReceivingReport) !== '---'">• Tài xế: <strong class="text-foreground">{{ getReceivingTransporterName(selectedRequest, selectedReceivingReport) }}</strong></span>
                            </div>
                        </div>
                        <button
                            @click="isDetailModalOpen = false"
                            class="flex size-9 items-center justify-center rounded-2xl text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        >
                            <X class="size-5" />
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="flex-1 space-y-6 overflow-y-auto p-6 text-xs">
                        <div
                            v-if="receivingStage === 'report' && selectedReceivingReport"
                            class="space-y-5"
                        >
                            <div class="report-print-sheet overflow-hidden rounded-xl border border-slate-300 bg-white text-slate-900 shadow-sm">
                                <div class="report-print-actions flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
                                    <div>
                                        <div class="text-sm font-bold">Phiếu biên bản nhận hàng</div>
                                        <div class="mt-0.5 text-[11px] text-slate-500">Số {{ selectedReceivingReport.report_code }}</div>
                                    </div>
                                    <Button
                                        @click="printReceivingReport"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 gap-1.5 border-slate-300 bg-white text-xs text-slate-700 hover:bg-slate-100"
                                    >
                                        <Printer class="size-3.5" /> In phiếu
                                    </Button>
                                </div>

                                <div class="p-5 sm:p-8">
                                    <div class="grid gap-4 border-b border-slate-800 pb-4 sm:grid-cols-2">
                                        <div>
                                            <div class="text-sm font-bold uppercase tracking-wide">Công ty TNHH Aventura</div>
                                            <div class="mt-1 text-xs text-slate-600">Phiếu kiểm nhận nguyên liệu từ Kho Tổng</div>
                                        </div>
                                        <div class="text-left sm:text-right">
                                            <div class="text-base font-bold uppercase">Biên bản đối soát nhận hàng</div>
                                            <div class="mt-1 text-xs">Số: <strong>{{ selectedReceivingReport.report_code }}</strong></div>
                                        </div>
                                    </div>

                                    <div class="grid gap-x-6 gap-y-2 py-4 text-xs sm:grid-cols-2">
                                        <div><span class="text-slate-500">Mã đơn:</span> <strong>{{ selectedRequest.request_code }}</strong></div>
                                        <div><span class="text-slate-500">Ngày lập:</span> <strong>{{ formatDate(selectedReceivingReport.submitted_at || selectedRequest.created_at) }}</strong></div>
                                        <div><span class="text-slate-500">Kho xuất:</span> <strong>{{ selectedRequest.from_branch?.name || 'Kho Tổng' }}</strong></div>
                                        <div><span class="text-slate-500">Chi nhánh nhận:</span> <strong>{{ selectedRequest.to_branch?.name || '---' }}</strong></div>
                                        <div><span class="text-slate-500">Nhân viên giao:</span> <strong>{{ getReceivingTransporterName(selectedRequest, selectedReceivingReport) }}</strong></div>
                                        <div><span class="text-slate-500">Mã niêm phong:</span> <strong>{{ selectedRequest.seal_code || '---' }}</strong></div>
                                        <div><span class="text-slate-500">Nhiệt độ:</span> <strong>{{ selectedReceivingReport.temperature_min_c != null ? `${selectedReceivingReport.temperature_min_c}°C ~ ${selectedReceivingReport.temperature_max_c ?? selectedReceivingReport.temperature_min_c}°C` : 'Không đo' }}</strong></div>
                                        <div><span class="text-slate-500">Trạng thái:</span> <strong>Chờ xác nhận biên bản</strong></div>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="w-full min-w-[760px] border-collapse text-xs">
                                            <thead>
                                                <tr class="bg-slate-100 text-left font-bold">
                                                    <th class="border border-slate-300 px-2 py-2 text-center">STT</th>
                                                    <th class="border border-slate-300 px-2 py-2">Nguyên liệu</th>
                                                    <th class="border border-slate-300 px-2 py-2">ĐVT</th>
                                                    <th class="border border-slate-300 px-2 py-2 text-right">Kho Tổng xuất</th>
                                                    <th class="border border-slate-300 px-2 py-2 text-right">Đạt</th>
                                                    <th class="border border-slate-300 px-2 py-2 text-right">Hỏng</th>
                                                    <th class="border border-slate-300 px-2 py-2 text-right">Hết hạn</th>
                                                    <th class="border border-slate-300 px-2 py-2 text-right">Sai hàng</th>
                                                    <th class="border border-slate-300 px-2 py-2 text-right">Thiếu</th>
                                                    <th class="border border-slate-300 px-2 py-2">Ghi chú</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="(item, index) in selectedReceivingReport.items || []"
                                                    :key="item.id"
                                                    :class="isReportIssue(item) ? 'bg-slate-50' : ''"
                                                >
                                                    <td class="border border-slate-300 px-2 py-2 text-center">{{ Number(index) + 1 }}</td>
                                                    <td class="border border-slate-300 px-2 py-2 font-semibold">{{ item.ingredient?.name || item.ingredient_name_snapshot }}</td>
                                                    <td class="border border-slate-300 px-2 py-2">{{ item.unit_symbol_snapshot || '---' }}</td>
                                                    <td class="border border-slate-300 px-2 py-2 text-right">{{ formatQuantity(item.dispatched_quantity) }}</td>
                                                    <td class="border border-slate-300 px-2 py-2 text-right">{{ formatQuantity(item.submitted_good_quantity) }}</td>
                                                    <td class="border border-slate-300 px-2 py-2 text-right">{{ formatQuantity(item.submitted_damaged_quantity) }}</td>
                                                    <td class="border border-slate-300 px-2 py-2 text-right">{{ formatQuantity(item.submitted_expired_quantity) }}</td>
                                                    <td class="border border-slate-300 px-2 py-2 text-right">{{ formatQuantity(item.submitted_wrong_item_quantity) }}</td>
                                                    <td class="border border-slate-300 px-2 py-2 text-right">{{ formatQuantity(item.submitted_shortage_quantity) }}</td>
                                                    <td class="border border-slate-300 px-2 py-2">{{ item.submitted_note || (isReportIssue(item) ? 'Cần xử lý' : '') }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-4 border border-slate-300 p-3 text-xs leading-relaxed">
                                        <div class="font-bold">Kết quả xử lý dự kiến</div>
                                        <div class="mt-1">Số đạt được nhập vào tồn kho Chi nhánh. Số hỏng, hết hạn, sai hàng được nhập lô cách ly để Trưởng Kho Tổng xử lý theo quy định.</div>
                                        <div v-if="selectedReceivingReport.notes" class="mt-2"><strong>Ghi chú:</strong> {{ selectedReceivingReport.notes }}</div>
                                    </div>

                                    <div class="mt-8 grid gap-8 text-center text-xs sm:grid-cols-3">
                                        <div>
                                            <div class="font-bold">ĐẠI DIỆN CHI NHÁNH NHẬN</div>
                                            <div class="mt-1 text-slate-500">Ký, ghi rõ họ tên</div>
                                            <div class="mt-12 border-b border-dashed border-slate-500"></div>
                                        </div>
                                        <div>
                                            <div class="font-bold">NHÂN VIÊN GIAO</div>
                                            <div class="mt-1 text-slate-500">{{ getReceivingTransporterName(selectedRequest, selectedReceivingReport) === '---' ? 'Ký, ghi rõ họ tên' : getReceivingTransporterName(selectedRequest, selectedReceivingReport) }}</div>
                                            <div class="mt-12 border-b border-dashed border-slate-500"></div>
                                        </div>
                                        <div>
                                            <div class="font-bold">TRƯỞNG KHO TỔNG / CHỦ DN</div>
                                            <div class="mt-1 text-slate-500">Ký, ghi rõ họ tên</div>
                                            <div class="mt-12 border-b border-dashed border-slate-500"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="report-legacy rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-amber-700 dark:text-amber-300">
                                <div class="flex items-center gap-2 font-bold">
                                    <FileCheck2 class="size-4" />
                                    <span>Biên bản {{ selectedReceivingReport.report_code }}</span>
                                </div>
                                <p class="mt-2 leading-relaxed">
                                    Lần đối soát vừa rồi có nguyên liệu chưa đạt. Kiểm tra lại các dòng dưới đây trước khi xác nhận lần cuối. Hàng đạt sẽ nhập tồn, hàng lỗi sẽ được cách ly; số liệu sau xác nhận không thể sửa trực tiếp.
                                </p>
                            </div>

                            <div class="report-legacy rounded-2xl border border-border overflow-hidden">
                                <div class="border-b border-border bg-muted/40 p-3 font-bold text-foreground">
                                    Nguyên liệu gặp vấn đề
                                </div>
                                <div class="divide-y divide-border">
                                    <div
                                        v-for="item in (selectedReceivingReport.items || []).filter((row: any) => Number(row.submitted_damaged_quantity || 0) + Number(row.submitted_expired_quantity || 0) + Number(row.submitted_wrong_item_quantity || 0) + Number(row.submitted_shortage_quantity || 0) > 0)"
                                        :key="item.id"
                                        class="space-y-2 p-4"
                                    >
                                        <div class="flex items-center justify-between gap-3">
                                            <strong class="text-foreground">{{ item.ingredient?.name || item.ingredient_name_snapshot }}</strong>
                                            <span class="font-mono text-muted-foreground">Xuất {{ formatQuantity(item.dispatched_quantity) }} {{ item.unit_symbol_snapshot || '' }}</span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 text-[11px] sm:grid-cols-4">
                                            <span class="rounded-lg bg-emerald-500/10 px-2 py-1 text-emerald-700 dark:text-emerald-300">Đạt: {{ formatQuantity(item.submitted_good_quantity) }}</span>
                                            <span class="rounded-lg bg-rose-500/10 px-2 py-1 text-rose-700 dark:text-rose-300">Hỏng: {{ formatQuantity(item.submitted_damaged_quantity) }}</span>
                                            <span class="rounded-lg bg-amber-500/10 px-2 py-1 text-amber-700 dark:text-amber-300">Hết hạn: {{ formatQuantity(item.submitted_expired_quantity) }}</span>
                                            <span class="rounded-lg bg-indigo-500/10 px-2 py-1 text-indigo-700 dark:text-indigo-300">Thiếu/sai: {{ formatQuantity(Number(item.submitted_shortage_quantity || 0) + Number(item.submitted_wrong_item_quantity || 0)) }}</span>
                                        </div>
                                        <p v-if="item.submitted_note" class="text-muted-foreground">Ghi chú: {{ item.submitted_note }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="report-legacy rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300">
                                Sau khi xác nhận: nguyên liệu đạt được nhập kho Chi nhánh; nguyên liệu hỏng/hết hạn/sai hàng tạo lô khóa và hồ sơ cách ly để Trưởng Kho Tổng xử lý. Biên bản sẽ gửi cho tài xế, Chủ doanh nghiệp và Trưởng Kho Tổng.
                            </div>
                        </div>
                        <template v-else>
                        <!-- Rejection reason -->
                        <div
                            v-if="selectedRequest.status === 'rejected'"
                            class="rounded-2xl border border-rose-500/20 bg-rose-500/10 p-4 text-rose-600 dark:text-rose-400"
                        >
                            <div class="flex items-center gap-2 font-bold">
                                <AlertTriangle class="size-4" />
                                <span>Lý do Kho Tổng từ chối:</span>
                            </div>
                            <p class="mt-1 text-xs">{{ selectedRequest.rejection_reason }}</p>
                        </div>

                        <!-- Detail Mode: General Information Summary Card -->
                        <div
                            v-if="!isReceiveMode"
                            class="rounded-2xl border border-border bg-muted/20 p-4 space-y-3"
                        >
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div class="space-y-0.5">
                                    <span class="text-[10px] font-medium text-muted-foreground uppercase">Kho xuất hàng</span>
                                    <p class="font-bold text-foreground text-xs">{{ selectedRequest.from_branch?.name || 'Kho Tổng Sai Gon Diner' }}</p>
                                </div>
                                <div class="space-y-0.5">
                                    <span class="text-[10px] font-medium text-muted-foreground uppercase">Chi nhánh nhận</span>
                                    <p class="font-bold text-foreground text-xs">{{ selectedRequest.to_branch?.name || 'Chi nhánh hiện tại' }}</p>
                                </div>
                                <div class="space-y-0.5">
                                    <span class="text-[10px] font-medium text-muted-foreground uppercase">Người tạo đơn</span>
                                    <p class="font-bold text-foreground text-xs">{{ selectedRequest.creator?.name || '---' }}</p>
                                </div>
                                <div class="space-y-0.5">
                                    <span class="text-[10px] font-medium text-muted-foreground uppercase">Người duyệt</span>
                                    <p class="font-bold text-foreground text-xs">{{ selectedRequest.approver?.name || (selectedRequest.status === 'pending' ? 'Đang chờ duyệt' : '---') }}</p>
                                </div>
                                <div class="space-y-0.5">
                                    <span class="text-[10px] font-medium text-muted-foreground uppercase">Người xuất kho</span>
                                    <p class="font-bold text-foreground text-xs">{{ selectedRequest.dispatcher?.name || (['pending', 'approved'].includes(selectedRequest.status) ? 'Chưa xuất' : '---') }}</p>
                                </div>
                                <div class="space-y-0.5">
                                    <span class="text-[10px] font-medium text-muted-foreground uppercase">Tài xế vận chuyển</span>
                                    <p class="font-bold text-foreground text-xs">{{ selectedRequest.transporter?.name || 'Chưa điều phối' }}</p>
                                </div>
                                <div class="space-y-0.5">
                                    <span class="text-[10px] font-medium text-muted-foreground uppercase">Mã Seal niêm phong</span>
                                    <p class="font-mono font-bold text-foreground text-xs">{{ selectedRequest.seal_code || 'Không có' }}</p>
                                </div>
                                <div class="space-y-0.5">
                                    <span class="text-[10px] font-medium text-muted-foreground uppercase">Ngày tạo đơn</span>
                                    <p class="font-bold text-foreground text-xs">{{ formatDate(selectedRequest.created_at) }}</p>
                                </div>
                            </div>
                            <div v-if="selectedRequest.notes" class="border-t border-border/60 pt-2 text-xs">
                                <span class="font-semibold text-muted-foreground">Ghi chú đặt hàng: </span>
                                <span class="text-foreground">{{ selectedRequest.notes }}</span>
                            </div>
                        </div>

                        <!-- Detail Mode: If already received, show inspection result card -->
                        <div
                            v-if="!isReceiveMode && (selectedRequest.received_at || ['completed', 'partial_received', 'disputed'].includes(selectedRequest.status))"
                            class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4 space-y-2.5"
                        >
                            <div class="flex items-center gap-2 font-bold text-emerald-700 dark:text-emerald-300 text-xs">
                                <CheckCircle2 class="size-4" />
                                <span>Kết Quả Nghiệm Thu & Nhận Hàng</span>
                            </div>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 text-xs">
                                <div>
                                    <span class="text-[10px] text-muted-foreground uppercase block">Người nhận</span>
                                    <strong class="text-foreground">{{ selectedRequest.receiver?.name || '---' }}</strong>
                                </div>
                                <div>
                                    <span class="text-[10px] text-muted-foreground uppercase block">Thời gian nhận</span>
                                    <strong class="text-foreground">{{ formatDate(selectedRequest.received_at) }}</strong>
                                </div>
                                <div>
                                    <span class="text-[10px] text-muted-foreground uppercase block">Nhiệt độ (°C)</span>
                                    <strong class="text-foreground">
                                        {{ getReceivedTemperature(selectedRequest) }}
                                    </strong>
                                </div>
                                <div>
                                    <span class="text-[10px] text-muted-foreground uppercase block">Ghi chú nhận</span>
                                    <strong class="text-foreground">{{ selectedRequest.receiving_notes || 'Không có ghi chú' }}</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Receive Mode: Waiting Delivery Confirmation Notice -->
                        <div
                            v-if="isReceiveMode && !isReceiveReady(selectedRequest)"
                            class="rounded-2xl border border-amber-500/20 bg-amber-500/10 p-4 text-amber-700 dark:text-amber-300"
                        >
                            <div class="flex items-center gap-2 font-bold">
                                <AlertTriangle class="size-4" />
                                <span>Đang trên đường vận chuyển - Chờ tài xế xác nhận giao tới</span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                <span v-if="selectedRequest.transporter">
                                    Tài xế: <strong>{{ selectedRequest.transporter.name }}</strong>
                                </span>
                                Nhân viên giao hàng cần nhấn "Giao hàng thành công" khi đến nơi trước khi Chi nhánh tiến hành nghiệm thu & nhập kho.
                            </p>
                        </div>

                        <!-- Receive Mode: Receive Evidence & Inspection Form -->
                        <div
                            v-if="isReceiveMode && isReceiveReady(selectedRequest)"
                            class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5 text-foreground space-y-4 shadow-xs"
                        >
                            <div class="flex items-center gap-2 font-bold text-emerald-700 dark:text-emerald-300 text-sm">
                                <FileCheck2 class="size-4" />
                                <span>Biên bản nghiệm thu & Thông tin nhận hàng</span>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-foreground">
                                    Ghi chú nhận hàng / Giải trình chênh lệch (nếu có)
                                </label>
                                <textarea
                                    v-model="receiveNotes"
                                    rows="2"
                                    class="w-full rounded-xl border border-input bg-background p-3 text-xs text-foreground shadow-2xs focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                                    placeholder="Ghi chú tình trạng kiện hàng, số lượng thực nhận, niêm phong seal..."
                                />
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <!-- Ảnh thực nhận / Biên bản -->
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-foreground">
                                        Ảnh thực nhận / Biên bản
                                    </label>
                                    <label
                                        class="group flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-border bg-background p-3.5 text-center transition hover:border-emerald-500 hover:bg-emerald-50/20 dark:hover:bg-emerald-950/20"
                                    >
                                        <input
                                            type="file"
                                            accept="image/*,.pdf"
                                            class="sr-only"
                                            @change="setEvidenceFile('photo', $event)"
                                        />
                                        <div class="flex size-8 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400">
                                            <Camera class="size-4" />
                                        </div>
                                        <span class="mt-1.5 text-[11px] font-semibold text-foreground line-clamp-1">
                                            {{ receiptPhoto ? receiptPhoto.name : 'Tải ảnh biên bản / hàng hóa' }}
                                        </span>
                                        <span class="text-[10px] text-muted-foreground">
                                            {{ receiptPhoto ? 'Nhấn để đổi ảnh' : 'PNG, JPG, PDF (Tối đa 10MB)' }}
                                        </span>
                                    </label>
                                </div>

                                <!-- Chữ ký người nhận -->
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-foreground">
                                        Ảnh chữ ký người nhận
                                    </label>
                                    <label
                                        class="group flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-border bg-background p-3.5 text-center transition hover:border-emerald-500 hover:bg-emerald-50/20 dark:hover:bg-emerald-950/20"
                                    >
                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="sr-only"
                                            @change="setEvidenceFile('signature', $event)"
                                        />
                                        <div class="flex size-8 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400">
                                            <FileSignature class="size-4" />
                                        </div>
                                        <span class="mt-1.5 text-[11px] font-semibold text-foreground line-clamp-1">
                                            {{ receiverSignature ? receiverSignature.name : 'Tải ảnh chữ ký người nhận' }}
                                        </span>
                                        <span class="text-[10px] text-muted-foreground">
                                            {{ receiverSignature ? 'Nhấn để đổi chữ ký' : 'Ảnh chụp hoặc chữ ký số' }}
                                        </span>
                                    </label>
                                </div>

                                <!-- Nhiệt độ kiểm tra -->
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-foreground">
                                        Nhiệt độ kiểm tra (°C)
                                    </label>
                                    <div class="flex h-[94px] flex-col justify-center rounded-xl border border-border bg-background p-3">
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1">
                                                <span class="text-[10px] font-semibold text-muted-foreground">Min</span>
                                                <input
                                                    v-model="receiveTemperatureMin"
                                                    type="number"
                                                    step="0.1"
                                                    placeholder="0"
                                                    class="mt-0.5 w-full rounded-lg border border-input bg-card px-2.5 py-1 text-xs font-bold text-foreground [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                                />
                                            </div>
                                            <span class="mt-4 font-bold text-muted-foreground">-</span>
                                            <div class="flex-1">
                                                <span class="text-[10px] font-semibold text-muted-foreground">Max</span>
                                                <input
                                                    v-model="receiveTemperatureMax"
                                                    type="number"
                                                    step="0.1"
                                                    placeholder="8"
                                                    class="mt-0.5 w-full rounded-lg border border-input bg-card px-2.5 py-1 text-xs font-bold text-foreground [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Goods Items Breakdown -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-bold text-foreground">
                                    Chi Tiết Hàng Hóa Cấp Phát
                                </h4>
                                <span v-if="isReceiveMode && isReceiveReady(selectedRequest)" class="text-[11px] font-medium text-muted-foreground">
                                    Kiểm đếm theo các trạng thái: <span class="font-bold text-emerald-600">Đạt</span> • <span class="font-bold text-rose-500">Hỏng</span> • <span class="font-bold text-amber-500">Hết hạn</span> • <span class="font-bold text-indigo-500">Thiếu hàng</span>
                                </span>
                                <span v-else class="text-[11px] font-medium text-muted-foreground">
                                    Danh sách các mặt hàng nguyên liệu trong đơn
                                </span>
                            </div>

                            <div class="overflow-x-auto rounded-2xl border border-border">
                                <table class="w-full text-left text-xs">
                                    <thead class="border-b border-border bg-muted/60 font-semibold text-muted-foreground">
                                        <tr>
                                            <th class="p-3 pl-4">Nguyên Liệu</th>
                                            <th class="p-3 text-center">Đơn Vị</th>
                                            <th class="p-3 text-right">Số Lượng Đặt</th>
                                            <th class="p-3 text-right">Kho Duyệt</th>
                                            <th class="p-3" :class="isReceiveMode && isReceiveReady(selectedRequest) ? 'text-center min-w-[320px]' : 'text-right'">
                                                {{ isReceiveMode && isReceiveReady(selectedRequest) ? 'Kiểm Đếm Thực Nhận' : 'Số Lượng Nhận' }}
                                            </th>
                                            <th class="p-3 pr-4 text-right">Đơn Giá</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr
                                            v-for="item in selectedRequest.items"
                                            :key="item.id"
                                            class="hover:bg-muted/30"
                                        >
                                            <td class="p-3 pl-4 font-bold text-foreground">
                                                {{ item.ingredient?.name }}
                                            </td>
                                            <td class="p-3 text-center font-mono font-semibold text-muted-foreground">
                                                {{ item.unit_symbol || 'kg' }}
                                            </td>
                                            <td class="p-3 text-right font-medium text-foreground">
                                                {{ formatQuantity(item.requested_quantity) }}
                                            </td>
                                            <td class="p-3 text-right font-bold text-primary">
                                                {{ formatQuantity(item.approved_quantity ?? item.requested_quantity) }}
                                            </td>
                                            <td class="p-3" :class="isReceiveMode && isReceiveReady(selectedRequest) ? 'text-center' : 'text-right'">
                                                <!-- Khi đang nhận hàng: hiển thị 4 ô kiểm đếm -->
                                                <div
                                                    v-if="isReceiveMode && isReceiveReady(selectedRequest)"
                                                    class="max-w-sm mx-auto space-y-1"
                                                >
                                                    <div class="grid grid-cols-4 gap-1.5">
                                                        <div class="space-y-0.5">
                                                            <span class="block text-[9px] font-bold text-emerald-600 dark:text-emerald-400 uppercase">Đạt</span>
                                                            <input
                                                                type="number"
                                                                step="1"
                                                                min="0"
                                                                v-model.number="item.received_good_quantity"
                                                                :class="[
                                                                    'w-full rounded-lg border px-2 py-1 text-center font-bold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none focus:outline-none transition',
                                                                    isItemMismatch(item)
                                                                        ? (getItemDiff(item) > 0
                                                                            ? 'border-rose-500 bg-rose-500/10 text-rose-600 dark:text-rose-400 focus:ring-1 focus:ring-rose-500'
                                                                            : 'border-amber-500 bg-amber-500/10 text-amber-600 dark:text-amber-400 focus:ring-1 focus:ring-amber-500')
                                                                        : 'border-emerald-500/50 bg-emerald-500/5 text-emerald-600 dark:text-emerald-400 focus:ring-1 focus:ring-emerald-500',
                                                                ]"
                                                                placeholder="0"
                                                            />
                                                        </div>
                                                        <div class="space-y-0.5">
                                                            <span class="block text-[9px] font-bold text-rose-500 uppercase">Hỏng</span>
                                                            <input
                                                                type="number"
                                                                step="1"
                                                                min="0"
                                                                v-model.number="item.received_damaged_quantity"
                                                                :class="[
                                                                    'w-full rounded-lg border px-2 py-1 text-center font-bold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none focus:outline-none transition',
                                                                    isItemMismatch(item)
                                                                        ? (getItemDiff(item) > 0
                                                                            ? 'border-rose-500 bg-rose-500/10 text-rose-600 dark:text-rose-400 focus:ring-1 focus:ring-rose-500'
                                                                            : 'border-amber-500 bg-amber-500/10 text-amber-600 dark:text-amber-400 focus:ring-1 focus:ring-amber-500')
                                                                        : 'border-rose-500/50 bg-rose-500/5 text-rose-600 dark:text-rose-400 focus:ring-1 focus:ring-rose-500',
                                                                ]"
                                                                placeholder="0"
                                                            />
                                                        </div>
                                                        <div class="space-y-0.5">
                                                            <span class="block text-[9px] font-bold text-amber-500 uppercase">Hết hạn</span>
                                                            <input
                                                                type="number"
                                                                step="1"
                                                                min="0"
                                                                v-model.number="item.received_expired_quantity"
                                                                :class="[
                                                                    'w-full rounded-lg border px-2 py-1 text-center font-bold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none focus:outline-none transition',
                                                                    isItemMismatch(item)
                                                                        ? (getItemDiff(item) > 0
                                                                            ? 'border-rose-500 bg-rose-500/10 text-rose-600 dark:text-rose-400 focus:ring-1 focus:ring-rose-500'
                                                                            : 'border-amber-500 bg-amber-500/10 text-amber-600 dark:text-amber-400 focus:ring-1 focus:ring-amber-500')
                                                                        : 'border-amber-500/50 bg-amber-500/5 text-amber-600 dark:text-amber-400 focus:ring-1 focus:ring-amber-500',
                                                                ]"
                                                                placeholder="0"
                                                            />
                                                        </div>
                                                        <div class="space-y-0.5">
                                                            <span class="block text-[9px] font-bold text-indigo-500 dark:text-indigo-400 uppercase">Thiếu</span>
                                                            <input
                                                                type="number"
                                                                step="1"
                                                                min="0"
                                                                v-model.number="item.received_missing_quantity"
                                                                :class="[
                                                                    'w-full rounded-lg border px-2 py-1 text-center font-bold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none focus:outline-none transition',
                                                                    isItemMismatch(item)
                                                                        ? (getItemDiff(item) > 0
                                                                            ? 'border-rose-500 bg-rose-500/10 text-rose-600 dark:text-rose-400 focus:ring-1 focus:ring-rose-500'
                                                                            : 'border-amber-500 bg-amber-500/10 text-amber-600 dark:text-amber-400 focus:ring-1 focus:ring-amber-500')
                                                                        : 'border-indigo-500/50 bg-indigo-500/5 text-indigo-600 dark:text-indigo-400 focus:ring-1 focus:ring-indigo-500',
                                                                ]"
                                                                placeholder="0"
                                                            />
                                                        </div>
                                                    </div>

                                                    <!-- Cảnh báo khi tổng kiểm đếm không khớp với số lượng kho duyệt -->
                                                    <div
                                                        v-if="isItemMismatch(item)"
                                                        class="flex items-center justify-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-bold"
                                                        :class="getItemDiff(item) > 0 ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400'"
                                                    >
                                                        <AlertTriangle class="size-3 shrink-0" />
                                                        <span v-if="getItemDiff(item) > 0">
                                                            Tổng ({{ formatQuantity(getItemSum(item)) }}) vượt duyệt ({{ formatQuantity(getItemMax(item)) }}) +{{ formatQuantity(getItemDiff(item)) }}
                                                        </span>
                                                        <span v-else>
                                                            Tổng ({{ formatQuantity(getItemSum(item)) }}) chưa đủ mức duyệt ({{ formatQuantity(getItemMax(item)) }}) (còn {{ formatQuantity(Math.abs(getItemDiff(item))) }})
                                                        </span>
                                                    </div>
                                                </div>
                                                <!-- Khi xem chi tiết đơn: chỉ hiển thị số lượng nhận dạng text gọn gàng -->
                                                <span
                                                    v-else
                                                    class="font-bold text-emerald-600 dark:text-emerald-400"
                                                >
                                                    {{ formatQuantity(item.received_quantity ?? item.approved_quantity ?? item.requested_quantity) }}
                                                </span>
                                            </td>
                                            <td class="p-3 pr-4 text-right font-medium text-muted-foreground">
                                                {{ formatCurrency(item.unit_cost) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        </template>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex items-center justify-between border-t border-border bg-muted/40 p-4 px-6">
                        <div class="text-xs text-muted-foreground">
                            Tổng tiền đơn:
                            <strong class="ml-1 text-base font-extrabold text-emerald-600 dark:text-emerald-400">
                                {{ formatCurrency(selectedRequest.total_amount) }}
                            </strong>
                        </div>

                        <div class="flex items-center gap-2">
                            <span
                                v-if="isReceiveMode && hasAnyItemMismatch"
                                class="hidden sm:inline-flex items-center gap-1 text-xs font-bold text-amber-600 dark:text-amber-400"
                            >
                                <AlertTriangle class="size-3.5" />
                                Tổng kiểm đếm chưa khớp mức duyệt
                            </span>

                            <Button
                                @click="isDetailModalOpen = false"
                                variant="outline"
                                size="sm"
                                class="rounded-xl border-border px-4 text-xs font-semibold hover:bg-accent"
                            >
                                Đóng
                            </Button>

                            <template v-if="receivingStage === 'report'">
                                <Button
                                    @click="loadReportIntoReceiveForm"
                                    variant="outline"
                                    size="sm"
                                    class="gap-1.5 rounded-lg border-slate-300 px-4 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                                >
                                    <ArrowLeft class="size-4" /> Quay lại sửa kiểm đếm
                                </Button>
                                <Button
                                    @click="confirmReceivingReport"
                                    size="sm"
                                    :disabled="isProcessing"
                                    class="gap-1.5 rounded-lg bg-slate-900 px-4 text-xs font-bold text-white shadow-sm hover:bg-slate-700 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white"
                                >
                                    <FileCheck2 class="size-4" /> Xác nhận biên bản & Nhập kho
                                </Button>
                            </template>

                            <!-- Nút Xác nhận Nhận hàng (khi ở chế độ Nhận hàng) -->
                            <Button
                                v-if="receivingStage === 'input' && isReceiveMode && isReceiveReady(selectedRequest)"
                                @click="receiveGoods"
                                size="sm"
                                :disabled="isProcessing || hasAnyItemMismatch"
                                class="gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <PackageCheck class="size-4" />
                                Xác Nhận Đã Nhận Hàng & Nhập Kho
                            </Button>

                            <!-- Nút Chuyển sang Nhận hàng (khi đang xem Chi tiết nhưng đơn đã sẵn sàng nhận) -->
                            <Button
                                v-else-if="!isReceiveMode && isReadyForReceiving(selectedRequest)"
                                @click="openReceiveModal(selectedRequest)"
                                size="sm"
                                class="gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white shadow-sm hover:bg-emerald-700"
                            >
                                <PackageCheck class="size-4" />
                                Nhận Hàng
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
/* Ẩn hoàn toàn thanh cuộn / nút mũi tên tăng giảm số mặc định của trình duyệt */
input[type='number']::-webkit-outer-spin-button,
input[type='number']::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type='number'] {
    -moz-appearance: textfield;
    appearance: textfield;
}

.report-legacy {
    display: none;
}

@media print {
    @page {
        size: A4;
        margin: 12mm;
    }

    :global(body) {
        background: #ffffff !important;
    }

    :global(body *) {
        visibility: hidden !important;
    }

    .report-print-sheet,
    .report-print-sheet * {
        visibility: visible !important;
    }

    .report-print-sheet {
        position: fixed !important;
        inset: 0 !important;
        width: 100% !important;
        overflow: visible !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    .report-print-actions {
        display: none !important;
    }
}
</style>
