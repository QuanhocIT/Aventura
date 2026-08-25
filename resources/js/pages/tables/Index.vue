<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Plus, Pencil, Trash2, X, QrCode, Users, MapPin, Clock, AlertCircle, Move } from 'lucide-vue-next';
import { computed, ref, watch, onUnmounted } from 'vue';
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
import { Label } from '@/components/ui/label';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Area = {
    id: number;
    name: string;
    code: string;
    tables_count: number;
    branch_id: number | null;
    branch_name?: string | null;
};
type PendingItem = {
    name: string;
    quantity: number;
    sent_at: string | null;
    is_late: boolean;
};
type ActiveOrder = {
    id: number;
    order_number: string;
    is_payment_requested?: boolean;
    waiter_name: string;
    total_amount: number;
    elapsed_minutes: number;
    pending_items: PendingItem[];
};
type Table = {
    id: number;
    restaurant_id: number;
    branch_id: number | null;
    branch_name?: string | null;
    name: string;
    capacity: number;
    status: string; // 'available', 'occupied', 'reserved', 'inactive', 'cleaning'
    is_payment_requested?: boolean;
    x_pos: number;
    y_pos: number;
    area: { id: number; name: string } | null;
    qr_code: string | null;
    qr_token: string;
    active_order: ActiveOrder | null;
};

const props = defineProps<{
    areas: Area[];
    tables: Table[];
    branches: Array<{ id: number; name: string }>;
    activeBranchId: number | null;
    branchScope: string;
}>();

const page = usePage();
const selectedArea = ref<number | 'all'>('all');
const showAddArea = ref(false);
const showAddTable = ref(false);
const editingTable = ref<Table | null>(null);
const deletingTable = ref<Table | null>(null);
const selectedQrTable = ref<Table | null>(null);

// Floor plan / View states
const isFloorPlanView = ref(true);
const isEditLayout = ref(false);
const isSnapToGrid = ref(true);
const hoverTableId = ref<number | null>(null);
const tooltipCoords = ref({ x: 0, y: 0 });

// Drag and drop state
const activeDragTable = ref<Table | null>(null);
const dragOffset = ref({ x: 0, y: 0 });

const areaForm = useForm({
    name: '',
    branch_id: props.activeBranchId ?? props.branches[0]?.id ?? '',
});
const tableForm = useForm({
    name: '',
    area_id: props.areas.find(
        (area) =>
            Number(area.branch_id) ===
            Number(props.activeBranchId ?? props.branches[0]?.id),
    )?.id
        ? String(
              props.areas.find(
                  (area) =>
                      Number(area.branch_id) ===
                      Number(props.activeBranchId ?? props.branches[0]?.id),
              )?.id,
          )
        : '',
    capacity: '4',
    branch_id: props.activeBranchId ?? props.branches[0]?.id ?? '',
});
const editForm = useForm({ name: '', capacity: '', status: '' });

const availableAreas = computed(() =>
    props.areas.filter(
        (area) => Number(area.branch_id) === Number(tableForm.branch_id),
    ),
);

watch(
    () => tableForm.branch_id,
    () => {
        if (
            !availableAreas.value.some(
                (area) => String(area.id) === String(tableForm.area_id),
            )
        ) {
            tableForm.area_id = availableAreas.value[0]?.id
                ? String(availableAreas.value[0].id)
                : '';
        }
    },
);

const filteredTables = computed(() =>
    selectedArea.value === 'all'
        ? props.tables
        : props.tables.filter((t) => t.area?.id === selectedArea.value),
);

// Pagination only used for Grid View
const currentPage = ref(1);
const itemsPerPage = 12;
const totalPages = computed(() =>
    Math.ceil(filteredTables.value.length / itemsPerPage),
);
const paginatedTables = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;

    return filteredTables.value.slice(start, start + itemsPerPage);
});

const visiblePages = computed(() => {
    const pages = [];
    const maxVisible = 5;
    let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2));
    const end = Math.min(totalPages.value, start + maxVisible - 1);

    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }

    return pages;
});

watch(selectedArea, () => {
    currentPage.value = 1;
});

const statusConfig: Record<
    string,
    { label: string; color: string; dot: string; bg: string; border: string }
> = {
    available: {
        label: 'Trống',
        color: 'bg-slate-100 text-slate-700 dark:bg-slate-900/60 dark:text-slate-400',
        dot: 'bg-slate-400',
        bg: 'bg-slate-50 dark:bg-slate-900/20',
        border: 'border-slate-200 dark:border-slate-800',
    },
    occupied: {
        label: 'Có khách',
        color: 'bg-emerald-100 text-emerald-755 dark:bg-emerald-950/40 dark:text-emerald-400',
        dot: 'bg-emerald-500',
        bg: 'bg-emerald-50/40 dark:bg-emerald-950/10',
        border: 'border-emerald-200 dark:border-emerald-900/40',
    },
    reserved: {
        label: 'Đặt trước',
        color: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400',
        dot: 'bg-amber-500',
        bg: 'bg-amber-50/40 dark:bg-amber-950/10',
        border: 'border-amber-200 dark:border-amber-900/40',
    },
    cleaning: {
        label: 'Chờ dọn bàn',
        color: 'bg-yellow-100 text-yellow-755 dark:bg-yellow-950/40 dark:text-yellow-400',
        dot: 'bg-yellow-500',
        bg: 'bg-yellow-50/40 dark:bg-yellow-950/10',
        border: 'border-yellow-200 dark:border-yellow-900/40',
    },
    inactive: {
        label: 'Ngưng dùng',
        color: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
        dot: 'bg-slate-400',
        bg: 'bg-slate-100/50 dark:bg-slate-900/10 opacity-60',
        border: 'border-slate-200 dark:border-slate-800',
    },
};

// Check if a table is occupied and waiting for food for > 15 minutes
const isTableDelayed = (table: Table) => {
    if (table.status !== 'occupied' || !table.active_order) {
        return false;
    }

    return table.active_order.pending_items.some((item) => item.is_late);
};

const submitArea = () =>
    areaForm.post('/tables/areas', {
        onSuccess: () => {
            areaForm.reset();
            showAddArea.value = false;
        },
    });

const submitTable = () =>
    tableForm.post('/tables', {
        onSuccess: () => {
            tableForm.reset('name');
            showAddTable.value = false;
        },
    });

const openEdit = (t: Table) => {
    if (isEditLayout.value) {
        return;
    } // ignore edit modal click in dragging mode

    editingTable.value = t;
    editForm.name = t.name;
    editForm.capacity = String(t.capacity);
    editForm.status = t.status;
};

const submitEdit = () => {
    if (!editingTable.value) {
        return;
    }

    editForm.patch(`/tables/${editingTable.value.id}`, {
        onSuccess: () => {
            editingTable.value = null;
        },
    });
};

const submitDelete = () => {
    if (!deletingTable.value) {
        return;
    }

    if (deletingTable.value.status === 'occupied' || deletingTable.value.active_order) {
        toast.error(`Không thể xóa bàn ${deletingTable.value.name} vì bàn đang có đơn hàng chưa hoàn tất. Vui lòng thanh toán hoặc hủy đơn trước.`);
        deletingTable.value = null;

        return;
    }

    router.delete(`/tables/${deletingTable.value.id}`, {
        onSuccess: () => {
            deletingTable.value = null;
            toast.success('Đã xóa bàn thành công.');
        },
        onError: (errs: any) => {
            toast.error(String(Object.values(errs)[0] ?? 'Có lỗi khi xóa bàn.'));
        },
    });
};

const getAreaTablesCount = (area: Area) => {
    return props.tables.filter((t) => t.area?.id === area.id).length;
};

const submitDeleteArea = async (area: Area) => {
    const activeTablesCount = getAreaTablesCount(area);

    if (activeTablesCount > 0) {
        toast.error(
            `Không thể xóa khu vực "${area.name}" vì vẫn còn ${activeTablesCount} bàn. Vui lòng chuyển hoặc xóa hết bàn trước.`,
        );

        return;
    }

    if (
        await confirmDialog({
            title: 'Xác nhận xóa khu vực',
            description: `Bạn có chắc chắn muốn xóa khu vực "${area.name}"? Khu vực này sẽ được ẩn tạm thời (xóa mềm).`,
            variant: 'destructive',
            confirmText: 'Xóa khu vực',
        })
    ) {
        router.delete(`/tables/areas/${area.id}`, {
            onSuccess: () => {
                if (selectedArea.value === area.id) {
                    selectedArea.value = 'all';
                }

                toast.success(`Đã xóa khu vực "${area.name}" thành công.`);
            },
            onError: (errors: any) => {
                toast.error(errors?.message || 'Không thể xóa khu vực.');
            },
        });
    }
};

const getQrUrl = (table: Table | null) => {
    if (!table) {
        return '';
    }

    const tenantId = table.restaurant_id || (page.props.tenant as any)?.id;

    return (
        window.location.origin +
        '/customer/order/' +
        tenantId +
        '/' +
        table.qr_token
    );
};

const showQrModal = (table: Table | null) => {
    if (!table) {
        return;
    }

    selectedQrTable.value = table;
};

const copyLink = (table: Table | null) => {
    if (!table) {
        return;
    }

    const url = getQrUrl(table);
    navigator.clipboard.writeText(url);
    toast.success('Đã sao chép liên kết đặt món tại bàn ' + table.name);
};

const printQrCode = (table: Table | null) => {
    if (!table) {
        return;
    }

    const qrUrl = getQrUrl(table);
    const qrImageUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrUrl)}`;
    const printWindow = window.open('', '_blank');

    if (!printWindow) {
        return;
    }

    printWindow.document.write(`
        <html>
            <head>
                <title>Mã QR - ${table.name}</title>
                <style>
                    body { font-family: system-ui, -apple-system, sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 90vh; margin: 0; text-align: center; }
                    .container { border: 2px dashed #0D9488; padding: 30px; border-radius: 20px; background: #FAFAFA; }
                    h1 { font-size: 28px; color: #0F172A; margin-bottom: 5px; }
                    p { font-size: 16px; color: #64748B; margin-bottom: 25px; }
                    img { width: 250px; height: 250px; }
                    .footer { margin-top: 25px; font-size: 12px; color: #94A3B8; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>MÃ QR ĐẶT MÓN</h1>
                    <p>Bàn: <strong>${table.name}</strong> - Khu vực: <strong>${table.area?.name || 'Mặc định'}</strong></p>
                    <img src="${qrImageUrl}" alt="Mã QR Bàn ${table.name}" />
                    <p class="footer">Quét mã QR để xem menu và gọi món</p>
                </div>
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() { window.close(); }, 500);
                    };
                <\/script>
            </body>
        </html>
    `);
    printWindow.document.close();
};

const regenerateQrCode = async (table: Table | null) => {
    if (!table) {
        return;
    }

    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description:
                'Bạn có chắc chắn muốn tạo mới mã QR cho bàn "' +
                table.name +
                '"? Mã QR cũ sẽ không thể sử dụng để quét đặt món được nữa.',
            variant: 'default',
        })
    ) {
        router.post(
            `/tables/${table.id}/regenerate-qr`,
            {},
            {
                onSuccess: () => {
                    toast.success('Đã tạo mới mã QR cho bàn ' + table.name);

                    if (
                        selectedQrTable.value &&
                        selectedQrTable.value.id === table.id
                    ) {
                        const updatedTable = props.tables.find(
                            (t) => t.id === table.id,
                        );

                        if (updatedTable) {
                            selectedQrTable.value = updatedTable;
                        }
                    }
                },
            },
        );
    }
};

// Custom interactive drag positions
const onDragStart = (table: Table, event: MouseEvent) => {
    if (!isEditLayout.value) {
        return;
    }

    event.preventDefault();
    activeDragTable.value = table;

    // Calculate initial click offset relative to the table center
    const cardEl = event.currentTarget as HTMLElement;
    const parentEl = cardEl.parentElement as HTMLElement;
    const parentRect = parentEl.getBoundingClientRect();
void parentRect;

    // Drag offsets relative to percentage
    dragOffset.value = {
        x: event.clientX,
        y: event.clientY,
    };

    window.addEventListener('mousemove', onDragMove);
    window.addEventListener('mouseup', onDragEnd);
};

const onDragMove = (event: MouseEvent) => {
    if (!activeDragTable.value) {
        return;
    }

    // Find the canvas container for the table's area
    const tableId = activeDragTable.value.id;
    const cardEl = document.getElementById(`table-card-${tableId}`);

    if (!cardEl) {
        return;
    }

    const parentEl = cardEl.parentElement;

    if (!parentEl) {
        return;
    }

    const rect = parentEl.getBoundingClientRect();

    // Compute current position relative to container bounding box
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;

    // Convert to percentage and clamp between 2 and 92 (to keep inside bounds)
    let xPercent = Math.min(
        92,
        Math.max(2, Math.round((x / rect.width) * 100)),
    );
    let yPercent = Math.min(
        92,
        Math.max(2, Math.round((y / rect.height) * 100)),
    );

    if (isSnapToGrid.value) {
        xPercent = Math.min(92, Math.max(2, Math.round(xPercent / 5) * 5));
        yPercent = Math.min(92, Math.max(2, Math.round(yPercent / 5) * 5));
    }

    // Update local reactiveness immediately
    activeDragTable.value.x_pos = xPercent;
    activeDragTable.value.y_pos = yPercent;
};

const onDragEnd = () => {
    if (!activeDragTable.value) {
        return;
    }

    const table = activeDragTable.value;

    // Save coordinate positions in backend
    router.patch(
        `/tables/${table.id}`,
        {
            x_pos: table.x_pos,
            y_pos: table.y_pos,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Đã lưu vị trí mới cho bàn ${table.name}`);
            },
            onError: () => {
                toast.error('Lỗi khi lưu vị trí bàn.');
            },
        },
    );

    activeDragTable.value = null;
    window.removeEventListener('mousemove', onDragMove);
    window.removeEventListener('mouseup', onDragEnd);
};

onUnmounted(() => {
    window.removeEventListener('mousemove', onDragMove);
    window.removeEventListener('mouseup', onDragEnd);
});

// Tooltip helpers on hover
const handleMouseEnter = (table: Table, event: MouseEvent) => {
    if (isEditLayout.value || table.status !== 'occupied') {
        return;
    }

    hoverTableId.value = table.id;
    updateTooltipPosition(event);
};

const handleMouseMove = (event: MouseEvent) => {
    if (hoverTableId.value === null) {
        return;
    }

    updateTooltipPosition(event);
};

const handleMouseLeave = () => {
    hoverTableId.value = null;
};

const updateTooltipPosition = (event: MouseEvent) => {
    // Offset tooltip slightly above and to the right of the cursor
    tooltipCoords.value = {
        x: event.clientX + 15,
        y: event.clientY - 15,
    };
};

const vnd = (value: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(value);
};
</script>

<template>
    <Head title="Sơ đồ bàn & Thiết kế" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- Header with View Toggle -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-50 text-teal-600 dark:bg-teal-950/60 dark:text-teal-400"
                >
                    <LayoutGrid class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Sơ Đồ Bàn & Phòng Chờ
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Thiết kế vị trí bàn trực quan bằng cách kéo thả và giám
                        sát nhiệt độ bàn ăn thực tế.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Toggle View Mode -->
                <div
                    class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800"
                >
                    <button
                        @click="isFloorPlanView = true"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                            isFloorPlanView
                                ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-white'
                                : 'text-slate-500 hover:text-slate-900',
                        ]"
                    >
                        <MapPin class="size-3.5" /> Sơ đồ bàn
                    </button>
                    <button
                        @click="
                            isFloorPlanView = false;
                            isEditLayout = false;
                        "
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                            !isFloorPlanView
                                ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-white'
                                : 'text-slate-500 hover:text-slate-900',
                        ]"
                    >
                        <LayoutGrid class="size-3.5" /> Danh sách lưới
                    </button>
                </div>

                <Button
                    variant="outline"
                    class="h-10 rounded-xl text-xs"
                    @click="showAddArea = true"
                >
                    <Plus class="text-teal-650 mr-1 size-4" /> Thêm khu vực
                </Button>
                <Button
                    class="h-10 rounded-xl bg-teal-600 text-xs font-semibold text-white hover:bg-teal-700"
                    @click="showAddTable = true"
                    :disabled="areas.length === 0"
                >
                    <Plus class="mr-1 size-4" /> Thêm bàn
                </Button>
            </div>
        </div>

        <!-- Add Area Modal -->
        <Teleport to="body">
        <div
            v-if="showAddArea"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4 backdrop-blur-xs"
        >
            <div class="flex min-h-full items-center justify-center">
                <Card
                    class="w-full max-w-sm animate-in rounded-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-base"
                                >Tạo khu vực mới</CardTitle
                            >
                            <button
                                @click="showAddArea = false"
                                class="text-muted-foreground hover:text-foreground"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <CardDescription
                            >Ví dụ: Tầng trệt, Phòng VIP 1, Ban
                            công...</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitArea" class="space-y-4">
                            <div class="grid gap-1.5">
                                <Label
                                    >Tên khu vực
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    v-model="areaForm.name"
                                    placeholder="VD: Phòng VIP"
                                    required
                                    autofocus
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    >Chi nhánh
                                    <span class="text-rose-500">*</span></Label
                                >
                                <select
                                    v-model="areaForm.branch_id"
                                    required
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:outline-none"
                                >
                                    <option
                                        v-for="branch in branches"
                                        :key="branch.id"
                                        :value="branch.id"
                                    >
                                        {{ branch.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="showAddArea = false"
                                    >Hủy</Button
                                >
                                <Button
                                    type="submit"
                                    class="bg-teal-650 text-white"
                                    :disabled="areaForm.processing"
                                >
                                    {{
                                        areaForm.processing
                                            ? 'Đang tạo...'
                                            : 'Tạo khu vực'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
        </Teleport>

        <!-- Add Table Modal -->
        
        <div
            v-if="showAddTable"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4 backdrop-blur-xs"
        >
            <div class="flex min-h-full items-center justify-center">
                <Card
                    class="w-full max-w-sm animate-in rounded-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-base"
                                >Thêm bàn mới</CardTitle
                            >
                            <button
                                @click="showAddTable = false"
                                class="text-muted-foreground hover:text-foreground"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitTable" class="space-y-4">
                            <div class="grid gap-1.5">
                                <Label
                                    >Tên bàn
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    v-model="tableForm.name"
                                    placeholder="VD: Bàn 01, B2..."
                                    required
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    >Chi nhánh
                                    <span class="text-rose-500">*</span></Label
                                >
                                <select
                                    v-model="tableForm.branch_id"
                                    required
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:outline-none"
                                >
                                    <option
                                        v-for="branch in branches"
                                        :key="branch.id"
                                        :value="branch.id"
                                    >
                                        {{ branch.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-1.5">
                                    <Label
                                        >Khu vực
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <select
                                        v-model="tableForm.area_id"
                                        required
                                        class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:outline-none"
                                    >
                                        <option value="" disabled>
                                            Chọn khu vực
                                        </option>
                                        <option
                                            v-for="a in availableAreas"
                                            :key="a.id"
                                            :value="a.id"
                                        >
                                            {{ a.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Sức chứa (người)</Label>
                                    <Input
                                        type="number"
                                        v-model="tableForm.capacity"
                                        min="1"
                                        max="100"
                                    />
                                </div>
                            </div>
                            <div class="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="showAddTable = false"
                                    >Hủy</Button
                                >
                                <Button
                                    type="submit"
                                    class="bg-teal-650 text-white"
                                    :disabled="tableForm.processing"
                                >
                                    {{
                                        tableForm.processing
                                            ? 'Đang thêm...'
                                            : 'Thêm bàn'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
        

        <!-- Edit Table Modal -->
        
        <div
            v-if="editingTable"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4 backdrop-blur-xs"
        >
            <div class="flex min-h-full items-center justify-center">
                <Card
                    class="w-full max-w-sm animate-in rounded-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-base"
                                ><Pencil class="size-4 text-teal-600" />Sửa
                                bàn</CardTitle
                            >
                            <button
                                @click="editingTable = null"
                                class="text-muted-foreground hover:text-foreground"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitEdit" class="space-y-4">
                            <div class="grid gap-1.5">
                                <Label>Tên bàn</Label>
                                <Input v-model="editForm.name" required />
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-1.5">
                                    <Label>Sức chứa</Label>
                                    <Input
                                        type="number"
                                        v-model="editForm.capacity"
                                        min="1"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Trạng thái</Label>
                                    <select
                                        v-model="editForm.status"
                                        class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:outline-none"
                                    >
                                        <option value="available">Trống</option>
                                        <option value="occupied">
                                            Có khách
                                        </option>
                                        <option value="reserved">
                                            Đặt trước
                                        </option>
                                        <option value="cleaning">
                                            Chờ dọn bàn
                                        </option>
                                        <option value="inactive">
                                            Ngưng dùng
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-4 grid gap-1.5 border-t pt-2">
                                <Label>QR Code gọi món</Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="showQrModal(editingTable!)"
                                    class="flex h-9 w-full items-center gap-1.5 text-xs"
                                >
                                    <QrCode class="size-4 text-teal-600" /> Xem
                                    mã QR & Link đặt món
                                </Button>
                            </div>
                            <div class="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="editingTable = null"
                                    >Hủy</Button
                                >
                                <Button
                                    type="submit"
                                    class="bg-teal-650 text-white"
                                    :disabled="editForm.processing"
                                >
                                    {{
                                        editForm.processing
                                            ? 'Đang lưu...'
                                            : 'Lưu thay đổi'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
        

        <!-- Delete Confirm -->
        
        <div
            v-if="deletingTable"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-sm animate-in rounded-2xl duration-150 zoom-in-95 fade-in"
            >
                <CardContent class="space-y-4 pt-6 text-center">
                    <div
                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-950"
                    >
                        <Trash2 class="size-7 text-rose-600" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold">
                            Xóa bàn "{{ deletingTable.name }}"?
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Bàn này sẽ bị xóa vĩnh viễn.
                        </p>
                    </div>
                    <div class="flex justify-center gap-3">
                        <Button variant="outline" @click="deletingTable = null"
                            >Hủy</Button
                        >
                        <Button
                            class="bg-rose-600 text-white hover:bg-rose-700"
                            @click="submitDelete"
                            >Xóa</Button
                        >
                    </div>
                </CardContent>
            </Card>
        </div>
        

        <!-- QR Code Modal -->
        
        <div
            v-if="selectedQrTable"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4 backdrop-blur-xs"
        >
            <div class="flex min-h-full items-center justify-center">
                <Card
                    class="w-full max-w-md animate-in rounded-2xl border border-teal-500/20 shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader class="border-b pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle
                                class="flex items-center gap-2 text-base"
                            >
                                <QrCode
                                    class="text-teal-650 size-5 animate-pulse"
                                />
                                Mã QR Gọi Món - {{ selectedQrTable.name }}
                            </CardTitle>
                            <button
                                @click="selectedQrTable = null"
                                class="text-muted-foreground hover:text-foreground"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <CardDescription>
                            Khu vực:
                            {{ selectedQrTable.area?.name || 'Mặc định' }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent
                        class="flex flex-col items-center space-y-5 pt-6"
                    >
                        <div
                            class="flex items-center justify-center rounded-2xl border border-slate-100 bg-white p-4 shadow-inner"
                        >
                            <img
                                :src="
                                    'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' +
                                    encodeURIComponent(
                                        getQrUrl(selectedQrTable),
                                    )
                                "
                                :alt="'Mã QR Bàn ' + selectedQrTable.name"
                                class="size-56 object-contain"
                            />
                        </div>

                        <div class="w-full space-y-2">
                            <Label class="text-xs font-semibold text-slate-500"
                                >Liên kết đặt món của bàn:</Label
                            >
                            <div class="flex items-center gap-2">
                                <Input
                                    readonly
                                    :value="getQrUrl(selectedQrTable)"
                                    class="border-slate-200 bg-slate-50 font-mono text-xs select-all dark:bg-slate-900"
                                />
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="copyLink(selectedQrTable)"
                                >
                                    Sao chép
                                </Button>
                            </div>
                        </div>

                        <div
                            class="flex w-full items-center gap-3 border-t pt-3"
                        >
                            <Button
                                class="flex-1 rounded-xl bg-teal-600 font-semibold text-white hover:bg-teal-700"
                                @click="printQrCode(selectedQrTable)"
                            >
                                In mã QR
                            </Button>
                            <Button
                                variant="outline"
                                class="flex-1 rounded-xl border-slate-200 text-slate-600 hover:bg-slate-50 hover:text-slate-800"
                                @click="regenerateQrCode(selectedQrTable)"
                            >
                                Tạo lại mã
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
        

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <!-- Left: Area list selection -->
            <div class="lg:col-span-1">
                <Card class="rounded-2xl shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle
                            class="flex items-center gap-1.5 text-sm font-bold"
                        >
                            <MapPin class="text-teal-650 size-4" />
                            Khu vực ({{ areas.length }})
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-1.5 p-3 pt-0">
                        <button
                            @click="selectedArea = 'all'"
                            class="rounded-xl border p-3 text-left text-xs transition-all"
                            :class="
                                selectedArea === 'all'
                                    ? 'border-teal-350 bg-teal-50/50 font-semibold dark:border-teal-800 dark:bg-teal-950/20'
                                    : 'border-slate-100 bg-slate-50/50 hover:border-teal-200 dark:border-slate-800'
                            "
                        >
                            <p class="font-bold">Tất cả khu vực</p>
                            <p class="mt-0.5 text-[10px] text-slate-400">
                                {{ tables.length }} bàn
                            </p>
                        </button>
                        <div
                            v-for="area in areas"
                            :key="area.id"
                            @click="selectedArea = area.id"
                            class="group relative flex cursor-pointer items-center justify-between rounded-xl border p-3 text-left text-xs transition-all"
                            :class="
                                selectedArea === area.id
                                    ? 'border-teal-300 bg-teal-50/50 font-semibold dark:border-teal-800 dark:bg-teal-950/20'
                                    : 'border-slate-100 bg-slate-50/50 hover:border-teal-200 dark:border-slate-800'
                            "
                        >
                            <div>
                                <p class="font-bold">{{ area.name }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-400">
                                    {{ getAreaTablesCount(area) }} bàn
                                </p>
                            </div>
                            <button
                                type="button"
                                @click.stop="submitDeleteArea(area)"
                                :title="
                                    getAreaTablesCount(area) > 0
                                        ? 'Không thể xóa khu vực đang chứa bàn'
                                        : 'Xóa khu vực'
                                "
                                class="rounded-lg p-1.5 text-slate-400 transition-all hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/50"
                                :class="
                                    getAreaTablesCount(area) > 0
                                        ? 'cursor-not-allowed opacity-30 hover:bg-transparent hover:text-slate-400'
                                        : 'opacity-0 group-hover:opacity-100 focus:opacity-100'
                                "
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                        <div
                            v-if="areas.length === 0"
                            class="py-6 text-center text-xs text-slate-400"
                        >
                            Chưa có khu vực. Hãy nhấn Thêm khu vực.
                        </div>
                    </CardContent>
                </Card>

                <!-- Status Legend / Help -->
                <Card class="mt-4 rounded-2xl shadow-sm">
                    <CardContent class="space-y-3.5 p-4">
                        <h3
                            class="dark:text-slate-350 text-xs font-bold tracking-wider text-slate-700 uppercase"
                        >
                            Trạng thái nhiệt bàn
                        </h3>
                        <div class="flex flex-col gap-2.5 text-xs">
                            <span
                                v-for="(cfg, key) in statusConfig"
                                :key="key"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="size-3.5 rounded-full border border-slate-200/50 dark:border-slate-700"
                                    :class="cfg.dot"
                                />
                                <span
                                    class="text-slate-650 font-medium dark:text-slate-400"
                                    >{{ cfg.label }}</span
                                >
                            </span>
                            <span class="flex items-center gap-2">
                                <span
                                    class="size-3.5 animate-ping rounded-full bg-rose-500"
                                />
                                <span
                                    class="font-bold text-rose-600 dark:text-rose-400"
                                    >Đợi món lâu (>15 phút)</span
                                >
                            </span>
                        </div>

                        <div
                            v-if="isFloorPlanView"
                            class="mt-3 border-t pt-3 text-[11px] text-slate-400"
                        >
                            <p class="flex items-start gap-1">
                                <AlertCircle
                                    class="text-teal-650 mt-0.5 size-3.5 shrink-0"
                                />
                                <span
                                    >Bật nút <strong>"Chỉnh vị trí"</strong> ở
                                    góc phải để bắt đầu thiết lập bố cục bàn
                                    bằng cách kéo thả.</span
                                >
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Area Layout / Grid -->
            <div class="lg:col-span-3">
                <Card class="overflow-hidden rounded-2xl shadow-sm">
                    <CardHeader
                        class="border-b border-slate-100 pb-3 dark:border-slate-800"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle
                                    class="flex items-center gap-2 text-base"
                                >
                                    {{
                                        isFloorPlanView
                                            ? 'Thiết Kế Sơ Đồ Mặt Bằng'
                                            : 'Danh Sách Lưới Bàn'
                                    }}
                                    <span
                                        class="text-xs font-normal text-muted-foreground"
                                        >({{ filteredTables.length }} bàn)</span
                                    >
                                </CardTitle>
                                <CardDescription>
                                    {{
                                        isFloorPlanView
                                            ? 'Kéo thả các bàn để bố trí phòng. Hover trên bàn có khách để xem chi tiết.'
                                            : 'Click vào bàn để thay đổi trạng thái hoặc xóa.'
                                    }}
                                </CardDescription>
                            </div>

                            <!-- Drag Switch for Floor Plan -->
                            <div
                                v-if="isFloorPlanView && selectedArea !== 'all'"
                                class="flex items-center gap-2"
                            >
                                <!-- Snap to grid toggle shown in edit layout mode -->
                                <div
                                    v-if="isEditLayout"
                                    class="border-slate-250 mr-3 flex items-center gap-2 border-r pr-3 dark:border-slate-800"
                                >
                                    <Label
                                        for="snap-grid-toggle"
                                        class="cursor-pointer text-xs font-semibold text-slate-500 select-none"
                                    >
                                        Hút dính lưới
                                    </Label>
                                    <button
                                        id="snap-grid-toggle"
                                        @click="isSnapToGrid = !isSnapToGrid"
                                        :class="[
                                            'relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none',
                                            isSnapToGrid
                                                ? 'bg-teal-650'
                                                : 'bg-slate-200 dark:bg-slate-700',
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                'pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out',
                                                isSnapToGrid
                                                    ? 'translate-x-4'
                                                    : 'translate-x-0',
                                            ]"
                                        />
                                    </button>
                                </div>

                                <Label
                                    for="edit-layout-toggle"
                                    class="cursor-pointer text-xs font-semibold text-slate-500 select-none"
                                >
                                    {{
                                        isEditLayout
                                            ? 'Chế độ Chỉnh vị trí (Đang bật)'
                                            : 'Bật Chỉnh vị trí'
                                    }}
                                </Label>
                                <button
                                    id="edit-layout-toggle"
                                    @click="isEditLayout = !isEditLayout"
                                    :class="[
                                        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none',
                                        isEditLayout
                                            ? 'bg-teal-600'
                                            : 'bg-slate-200 dark:bg-slate-700',
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out',
                                            isEditLayout
                                                ? 'translate-x-5'
                                                : 'translate-x-0',
                                        ]"
                                    />
                                </button>
                            </div>
                            <div
                                v-else-if="
                                    isFloorPlanView && selectedArea === 'all'
                                "
                                class="flex items-center gap-1 rounded-lg bg-amber-50 px-2 py-1 text-xs text-amber-600 dark:bg-amber-950/20 dark:text-amber-400"
                            >
                                <AlertCircle class="size-3.5" />
                                Chọn một khu vực cụ thể để di chuyển bàn
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent
                        class="min-h-[450px] bg-slate-50/30 p-4 dark:bg-slate-900/10"
                    >
                        <!-- ════ FLOOR PLAN VIEW ════ -->
                        <div
                            v-if="isFloorPlanView"
                            class="relative h-[520px] w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-inner transition-colors duration-300 dark:border-slate-800 dark:bg-slate-950"
                            :class="{ 'grid-bg': isEditLayout }"
                        >
                            <!-- Empty State inside Canvas -->
                            <div
                                v-if="filteredTables.length === 0"
                                class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center text-slate-400"
                            >
                                <LayoutGrid
                                    class="mb-3 size-12 text-teal-600 opacity-25"
                                />
                                <p class="text-sm font-bold">
                                    Khu vực này chưa có bàn
                                </p>
                                <p class="mt-1 max-w-xs text-xs text-slate-400">
                                    Nhấn "+ Thêm bàn" ở phía trên để bắt đầu
                                    thêm bàn vào khu vực này.
                                </p>
                            </div>

                            <!-- Tables Nodes -->
                            <div
                                v-for="t in filteredTables"
                                :key="t.id"
                                :id="`table-card-${t.id}`"
                                :style="{
                                    left: `${t.x_pos}%`,
                                    top: `${t.y_pos}%`,
                                }"
                                @mousedown="onDragStart(t, $event)"
                                @mouseenter="handleMouseEnter(t, $event)"
                                @mousemove="handleMouseMove($event)"
                                @mouseleave="handleMouseLeave"
                                @click="openEdit(t)"
                                :class="[
                                    'absolute flex size-20 flex-col items-center justify-center rounded-2xl border-2 shadow-sm transition-shadow select-none',
                                    isEditLayout
                                        ? 'cursor-move ring-2 ring-teal-500/20 hover:shadow-lg'
                                        : 'cursor-pointer hover:shadow-md',
                                    statusConfig[t.status]?.bg,
                                    isTableDelayed(t)
                                        ? 'animate-pulse border-rose-500 bg-rose-50/50 dark:bg-rose-950/20'
                                        : statusConfig[t.status]?.border,
                                ]"
                            >
                                <!-- Flashing indicator for delayed orders -->
                                <span
                                    v-if="isTableDelayed(t)"
                                    class="absolute -top-1 -right-1 flex h-3 w-3"
                                >
                                    <span
                                        class="bg-rose-450 absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                                    ></span>
                                    <span
                                        class="relative inline-block h-3 w-3 rounded-full bg-rose-500"
                                    ></span>
                                </span>

                                <Move
                                    v-if="isEditLayout"
                                    class="absolute top-1.5 left-1.5 size-3.5 text-teal-600 opacity-50"
                                />

                                <span
                                    class="absolute top-2 right-2 size-2 rounded-full border border-white/50"
                                    :class="statusConfig[t.status]?.dot"
                                />

                                <p
                                    class="mt-1 text-sm font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ t.name }}
                                </p>
                                <span
                                    class="mt-0.5 flex items-center gap-0.5 text-[9px] text-slate-500 dark:text-slate-400"
                                >
                                    <Users class="size-3 shrink-0" />
                                    {{ t.capacity }}
                                </span>

                                <span
                                    class="py-0.2 mt-1 scale-90 rounded-md px-1 text-[8px] font-semibold tracking-wide uppercase"
                                    :class="statusConfig[t.status]?.color"
                                >
                                    {{ statusConfig[t.status]?.label }}
                                </span>

                                <!-- Quick controls inside table node when editing -->
                                <div
                                    v-if="isEditLayout"
                                    class="absolute -bottom-2 flex gap-1"
                                >
                                    <button
                                        @click.stop="deletingTable = t"
                                        class="rounded-full bg-rose-600 p-1 text-white shadow-md hover:bg-rose-700 active:scale-90"
                                        title="Xóa bàn"
                                    >
                                        <Trash2 class="size-2.5" />
                                    </button>
                                    <button
                                        @click.stop="showQrModal(t)"
                                        class="rounded-full bg-teal-600 p-1 text-white shadow-md hover:bg-teal-700 active:scale-90"
                                        title="Mã QR"
                                    >
                                        <QrCode class="size-2.5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- ════ GRID LIST VIEW ════ -->
                        <div v-else class="space-y-4">
                            <div
                                v-if="filteredTables.length"
                                class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4"
                            >
                                <div
                                    v-for="t in paginatedTables"
                                    :key="t.id"
                                    class="group relative cursor-pointer rounded-2xl border p-4 transition-all hover:shadow-md"
                                    :class="[
                                        t.is_payment_requested || t.active_order?.is_payment_requested
                                            ? 'border-amber-500 bg-amber-50/60 ring-2 ring-amber-500/40 shadow-md shadow-amber-500/10 dark:bg-amber-950/30'
                                            : editingTable?.id === t.id
                                              ? 'border-teal-500 bg-teal-50/20 ring-2 ring-teal-500/30'
                                              : 'border-slate-100 bg-white hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900',
                                    ]"
                                    @click="openEdit(t)"
                                >
                                    <div
                                        class="mb-2 flex items-start justify-between"
                                    >
                                        <h4 class="text-sm font-bold">
                                            {{ t.name }}
                                        </h4>
                                        <span
                                            class="mt-1 size-2 shrink-0 rounded-full"
                                            :class="
                                                t.is_payment_requested || t.active_order?.is_payment_requested
                                                    ? 'bg-amber-500 animate-ping'
                                                    : statusConfig[t.status]?.dot
                                            "
                                        />
                                    </div>

                                    <div
                                        class="mb-2 flex items-center gap-1.5 text-[10px] text-slate-500"
                                    >
                                        <Users class="size-3.5" />
                                        {{ t.capacity }} người
                                    </div>

                                    <div
                                        class="mt-2.5 flex items-center justify-between"
                                    >
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[9px] font-semibold"
                                            :class="
                                                t.is_payment_requested || t.active_order?.is_payment_requested
                                                    ? 'bg-amber-500 text-white font-black animate-pulse shadow-xs shadow-amber-500/40'
                                                    : statusConfig[t.status]?.color
                                            "
                                        >
                                            {{
                                                t.is_payment_requested || t.active_order?.is_payment_requested
                                                    ? '💳 Chờ thanh toán'
                                                    : statusConfig[t.status]?.label
                                            }}
                                        </span>
                                        <span
                                            v-if="t.area"
                                            class="max-w-[80px] truncate text-[9px] text-slate-400"
                                            >{{ t.area.name }}</span
                                        >
                                    </div>

                                    <!-- QR & Delete floating controls -->
                                    <div
                                        class="absolute top-2 right-2 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        <button
                                            @click.stop="showQrModal(t)"
                                            class="text-teal-650 rounded-lg bg-slate-50 p-1.5 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700"
                                            title="Xem mã QR đặt món"
                                        >
                                            <QrCode class="size-3.5" />
                                        </button>
                                        <button
                                            @click.stop="deletingTable = t"
                                            class="rounded-lg bg-slate-50 p-1.5 text-rose-500 hover:bg-rose-50 dark:bg-slate-800 dark:hover:bg-rose-950/20"
                                            title="Xóa bàn"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="flex flex-col items-center justify-center py-16 text-center"
                            >
                                <div
                                    class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800"
                                >
                                    <LayoutGrid class="size-9" />
                                </div>
                                <p class="text-sm font-semibold">
                                    Chưa có bàn nào trong khu vực này
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Nhấn "+ Thêm bàn" để tạo bàn mới.
                                </p>
                            </div>

                            <!-- Pagination for Grid View -->
                            <div
                                v-if="totalPages > 1"
                                class="mt-4 flex items-center justify-between rounded-2xl border-t bg-white px-4 py-3 pt-4 dark:bg-slate-900"
                            >
                                <div class="text-xs text-muted-foreground">
                                    Hiển thị
                                    {{ (currentPage - 1) * itemsPerPage + 1 }} -
                                    {{
                                        Math.min(
                                            currentPage * itemsPerPage,
                                            filteredTables.length,
                                        )
                                    }}
                                    trong tổng số
                                    {{ filteredTables.length }} bàn
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="currentPage === 1"
                                        @click="currentPage--"
                                        class="h-8 rounded-lg text-xs"
                                        >Trước</Button
                                    >
                                    <Button
                                        v-for="page in visiblePages"
                                        :key="page"
                                        variant="outline"
                                        size="sm"
                                        @click="currentPage = page"
                                        :class="[
                                            'h-8 w-8 rounded-lg p-0 text-xs',
                                            currentPage === page
                                                ? 'bg-teal-650 font-semibold text-white'
                                                : '',
                                        ]"
                                    >
                                        {{ page }}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="currentPage === totalPages"
                                        @click="currentPage++"
                                        class="h-8 rounded-lg text-xs"
                                        >Sau</Button
                                    >
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <!-- Floating Live Heatmap Tooltip for occupied tables -->
    <Teleport to="body">
        <div
            v-if="hoverTableId !== null"
            :style="{
                left: `${tooltipCoords.x}px`,
                top: `${tooltipCoords.y}px`,
            }"
            class="pointer-events-none fixed z-50 w-72 animate-in rounded-2xl border border-slate-200 bg-white p-4 shadow-xl duration-100 zoom-in-95 fade-in dark:border-slate-800 dark:bg-slate-950"
        >
            <div class="mb-2 flex items-center justify-between border-b pb-2">
                <div>
                    <h4
                        class="text-sm font-bold text-slate-800 dark:text-slate-200"
                    >
                        {{
                            props.tables.find((t) => t.id === hoverTableId)
                                ?.name
                        }}
                    </h4>
                    <p
                        class="text-teal-650 font-mono text-[10px] dark:text-teal-400"
                    >
                        {{
                            props.tables.find((t) => t.id === hoverTableId)
                                ?.active_order?.order_number
                        }}
                    </p>
                </div>
                <div
                    class="flex items-center gap-1 text-[11px] font-semibold text-slate-500"
                >
                    <Clock class="size-3.5" />
                    Đã ngồi
                    {{
                        props.tables.find((t) => t.id === hoverTableId)
                            ?.active_order?.elapsed_minutes
                    }}
                    phút
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex justify-between text-xs">
                    <span class="text-slate-400">Người phục vụ:</span>
                    <span
                        class="dark:text-slate-350 font-semibold text-slate-700"
                    >
                        {{
                            props.tables.find((t) => t.id === hoverTableId)
                                ?.active_order?.waiter_name
                        }}
                    </span>
                </div>

                <!-- Pending Items list -->
                <div class="mt-2">
                    <p
                        class="mb-1 flex items-center justify-between text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                    >
                        <span
                            >Món ăn chưa ra ({{
                                props.tables.find((t) => t.id === hoverTableId)
                                    ?.active_order?.pending_items?.length || 0
                            }})</span
                        >
                        <span
                            class="animate-pulse text-[9px] font-black text-rose-500"
                            v-if="
                                props.tables
                                    .find((t) => t.id === hoverTableId)
                                    ?.active_order?.pending_items?.some(
                                        (i) => i.is_late,
                                    )
                            "
                        >
                            ĐỢI LÂU!
                        </span>
                    </p>
                    <ul
                        class="text-slate-650 dark:text-slate-450 max-h-32 space-y-1 overflow-y-auto pr-1 text-xs"
                    >
                        <li
                            v-for="(item, idx) in props.tables.find(
                                (t) => t.id === hoverTableId,
                            )?.active_order?.pending_items"
                            :key="idx"
                            class="flex items-center justify-between border-b border-slate-100 py-1 last:border-0 dark:border-slate-900"
                        >
                            <span
                                :class="[
                                    'max-w-[160px] truncate',
                                    item.is_late
                                        ? 'font-bold text-rose-500'
                                        : '',
                                ]"
                            >
                                {{ item.name }}
                            </span>
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold"
                                    >x{{ item.quantity }}</span
                                >
                                <span
                                    v-if="item.is_late"
                                    class="size-2 animate-ping rounded-full bg-rose-500"
                                />
                            </div>
                        </li>
                        <li
                            v-if="
                                !props.tables.find((t) => t.id === hoverTableId)
                                    ?.active_order?.pending_items?.length
                            "
                            class="rounded-lg bg-slate-50 py-1 text-center text-xs text-slate-400 italic dark:bg-slate-900/50"
                        >
                            Tất cả món đã ra đủ
                        </li>
                    </ul>
                </div>

                <div
                    class="mt-2 flex items-center justify-between border-t pt-2 text-xs"
                >
                    <span class="font-bold text-slate-400"
                        >Tổng thanh toán:</span
                    >
                    <span
                        class="text-sm font-black text-rose-600 dark:text-rose-400"
                    >
                        {{
                            vnd(
                                props.tables.find((t) => t.id === hoverTableId)
                                    ?.active_order?.total_amount || 0,
                            )
                        }}
                    </span>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.grid-bg {
    background-size: 24px 24px;
    background-image: radial-gradient(
        circle,
        rgba(13, 148, 136, 0.08) 1px,
        transparent 1px
    );
}
.dark .grid-bg {
    background-image: radial-gradient(
        circle,
        rgba(13, 148, 136, 0.15) 1px,
        transparent 1px
    );
}
</style>
