<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import {
    LayoutGrid, Plus, Pencil, Trash2, X, QrCode,
    Users, MapPin, CheckCircle2, Clock, AlertCircle,
    Move, Eye, Settings2, Sparkles, RefreshCw
} from 'lucide-vue-next';
import { computed, ref, watch, onUnmounted } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Area  = { id: number; name: string; code: string; tables_count: number };
type PendingItem = { name: string; quantity: number; sent_at: string | null; is_late: boolean };
type ActiveOrder = { id: number; order_number: string; waiter_name: string; total_amount: number; elapsed_minutes: number; pending_items: PendingItem[] };
type Table = { 
    id: number; 
    restaurant_id: number; 
    name: string; 
    capacity: number; 
    status: string; // 'available', 'occupied', 'reserved', 'inactive', 'cleaning'
    x_pos: number;
    y_pos: number;
    area: { id: number; name: string } | null; 
    qr_code: string | null; 
    qr_token: string;
    active_order: ActiveOrder | null;
};

const props = defineProps<{
    areas:  Area[];
    tables: Table[];
}>();

const page = usePage();
const selectedArea   = ref<number | 'all'>('all');
const showAddArea    = ref(false);
const showAddTable   = ref(false);
const editingTable   = ref<Table | null>(null);
const deletingTable  = ref<Table | null>(null);
const selectedQrTable = ref<Table | null>(null);

// Floor plan / View states
const isFloorPlanView = ref(true);
const isEditLayout = ref(false);
const hoverTableId = ref<number | null>(null);
const tooltipCoords = ref({ x: 0, y: 0 });

// Drag and drop state
const activeDragTable = ref<Table | null>(null);
const dragOffset = ref({ x: 0, y: 0 });

const areaForm = useForm({ name: '' });
const tableForm = useForm({ name: '', area_id: props.areas[0]?.id ? String(props.areas[0].id) : '', capacity: '4' });
const editForm  = useForm({ name: '', capacity: '', status: '' });

const filteredTables = computed(() =>
    selectedArea.value === 'all'
        ? props.tables
        : props.tables.filter(t => t.area?.id === selectedArea.value)
);

// Pagination only used for Grid View
const currentPage = ref(1);
const itemsPerPage = 12;
const totalPages = computed(() => Math.ceil(filteredTables.value.length / itemsPerPage));
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

const statusConfig: Record<string, { label: string; color: string; dot: string; bg: string; border: string }> = {
    available: { 
        label: 'Trống', 
        color: 'bg-slate-100 text-slate-700 dark:bg-slate-900/60 dark:text-slate-400', 
        dot: 'bg-slate-400',
        bg: 'bg-slate-50 dark:bg-slate-900/20',
        border: 'border-slate-200 dark:border-slate-800'
    },
    occupied:  { 
        label: 'Có khách', 
        color: 'bg-emerald-100 text-emerald-755 dark:bg-emerald-950/40 dark:text-emerald-400', 
        dot: 'bg-emerald-500',
        bg: 'bg-emerald-50/40 dark:bg-emerald-950/10',
        border: 'border-emerald-200 dark:border-emerald-900/40'
    },
    reserved:  { 
        label: 'Đặt trước', 
        color: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400', 
        dot: 'bg-amber-500',
        bg: 'bg-amber-50/40 dark:bg-amber-950/10',
        border: 'border-amber-200 dark:border-amber-900/40'
    },
    cleaning:  { 
        label: 'Chờ dọn bàn', 
        color: 'bg-yellow-100 text-yellow-755 dark:bg-yellow-950/40 dark:text-yellow-400', 
        dot: 'bg-yellow-500',
        bg: 'bg-yellow-50/40 dark:bg-yellow-950/10',
        border: 'border-yellow-200 dark:border-yellow-900/40'
    },
    inactive:  { 
        label: 'Ngưng dùng', 
        color: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400', 
        dot: 'bg-slate-400',
        bg: 'bg-slate-100/50 dark:bg-slate-900/10 opacity-60',
        border: 'border-slate-200 dark:border-slate-800'
    },
};

// Check if a table is occupied and waiting for food for > 15 minutes
const isTableDelayed = (table: Table) => {
    if (table.status !== 'occupied' || !table.active_order) {
return false;
}

    return table.active_order.pending_items.some(item => item.is_late);
};

const submitArea = () => areaForm.post('/tables/areas', {
    onSuccess: () => {
        areaForm.reset(); 
        showAddArea.value = false; 
    }
});

const submitTable = () => tableForm.post('/tables', {
    onSuccess: () => {
        tableForm.reset('name'); 
        showAddTable.value = false; 
    }
});

const openEdit = (t: Table) => {
    if (isEditLayout.value) {
return;
} // ignore edit modal click in dragging mode

    editingTable.value = t;
    editForm.name     = t.name;
    editForm.capacity = String(t.capacity);
    editForm.status   = t.status;
};

const submitEdit = () => {
    if (!editingTable.value) {
return;
}

    editForm.patch(`/tables/${editingTable.value.id}`, {
        onSuccess: () => {
            editingTable.value = null; 
        }
    });
};

const submitDelete = () => {
    if (!deletingTable.value) {
return;
}

    router.delete(`/tables/${deletingTable.value.id}`, {
        onSuccess: () => {
            deletingTable.value = null; 
        }
    });
};

const getQrUrl = (table: Table | null) => {
    if (!table) {
return '';
}

    const tenantId = table.restaurant_id || (page.props.tenant as any)?.id;

    return window.location.origin + '/customer/order/' + tenantId + '/' + table.qr_token;
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

    if ((await confirmDialog({ title: 'Xác nhận thao tác', description: 'Bạn có chắc chắn muốn tạo mới mã QR cho bàn "' + table.name + '"? Mã QR cũ sẽ không thể sử dụng để quét đặt món được nữa.', variant: 'default' }))) {
        router.post(`/tables/${table.id}/regenerate-qr`, {}, {
            onSuccess: () => {
                toast.success('Đã tạo mới mã QR cho bàn ' + table.name);

                if (selectedQrTable.value && selectedQrTable.value.id === table.id) {
                    const updatedTable = props.tables.find(t => t.id === table.id);

                    if (updatedTable) {
selectedQrTable.value = updatedTable;
}
                }
            }
        });
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
    
    // Drag offsets relative to percentage
    dragOffset.value = {
        x: event.clientX,
        y: event.clientY
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
    const xPercent = Math.min(92, Math.max(2, Math.round((x / rect.width) * 100)));
    const yPercent = Math.min(92, Math.max(2, Math.round((y / rect.height) * 100)));
    
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
    router.patch(`/tables/${table.id}`, {
        x_pos: table.x_pos,
        y_pos: table.y_pos
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Đã lưu vị trí mới cho bàn ${table.name}`);
        },
        onError: () => {
            toast.error('Lỗi khi lưu vị trí bàn.');
        }
    });

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
        y: event.clientY - 15
    };
};

const vnd = (value: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
};
</script>

<template>
    <Head title="Sơ đồ bàn & Thiết kế" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Header with View Toggle -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-50 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400">
                    <LayoutGrid class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Sơ Đồ Bàn & Phòng Chờ</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Thiết kế vị trí bàn trực quan bằng cách kéo thả và giám sát nhiệt độ bàn ăn thực tế.</p>
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <!-- Toggle View Mode -->
                <div class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                    <button 
                        @click="isFloorPlanView = true"
                        :class="['inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition', isFloorPlanView ? 'bg-white shadow-sm text-slate-900 dark:bg-slate-900 dark:text-white' : 'text-slate-500 hover:text-slate-900']"
                    >
                        <MapPin class="size-3.5" /> Sơ đồ bàn
                    </button>
                    <button 
                        @click="isFloorPlanView = false; isEditLayout = false"
                        :class="['inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition', !isFloorPlanView ? 'bg-white shadow-sm text-slate-900 dark:bg-slate-900 dark:text-white' : 'text-slate-500 hover:text-slate-900']"
                    >
                        <LayoutGrid class="size-3.5" /> Danh sách lưới
                    </button>
                </div>

                <Button variant="outline" class="h-10 text-xs rounded-xl" @click="showAddArea = true">
                    <Plus class="size-4 mr-1 text-teal-650" /> Thêm khu vực
                </Button>
                <Button class="h-10 text-xs bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl" @click="showAddTable = true" :disabled="areas.length === 0">
                    <Plus class="size-4 mr-1" /> Thêm bàn
                </Button>
            </div>
        </div>

        <!-- Add Area Modal -->
        <div v-if="showAddArea" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <Card class="max-w-sm w-full animate-in fade-in zoom-in-95 duration-150 rounded-2xl">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-base">Tạo khu vực mới</CardTitle>
                        <button @click="showAddArea = false" class="text-muted-foreground hover:text-foreground"><X class="size-4" /></button>
                    </div>
                    <CardDescription>Ví dụ: Tầng trệt, Phòng VIP 1, Ban công...</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitArea" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label>Tên khu vực <span class="text-rose-500">*</span></Label>
                            <Input v-model="areaForm.name" placeholder="VD: Phòng VIP" required autofocus />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="showAddArea = false">Hủy</Button>
                            <Button type="submit" class="bg-teal-650 text-white" :disabled="areaForm.processing">
                                {{ areaForm.processing ? 'Đang tạo...' : 'Tạo khu vực' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Add Table Modal -->
        <div v-if="showAddTable" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <Card class="max-w-sm w-full animate-in fade-in zoom-in-95 duration-150 rounded-2xl">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-base">Thêm bàn mới</CardTitle>
                        <button @click="showAddTable = false" class="text-muted-foreground hover:text-foreground"><X class="size-4" /></button>
                    </div>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitTable" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label>Tên bàn <span class="text-rose-500">*</span></Label>
                            <Input v-model="tableForm.name" placeholder="VD: Bàn 01, B2..." required />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label>Khu vực <span class="text-rose-500">*</span></Label>
                                <select v-model="tableForm.area_id" required
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                                    <option value="" disabled>Chọn khu vực</option>
                                    <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
                                </select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Sức chứa (người)</Label>
                                <Input type="number" v-model="tableForm.capacity" min="1" max="100" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="showAddTable = false">Hủy</Button>
                            <Button type="submit" class="bg-teal-650 text-white" :disabled="tableForm.processing">
                                {{ tableForm.processing ? 'Đang thêm...' : 'Thêm bàn' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Edit Table Modal -->
        <div v-if="editingTable" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <Card class="max-w-sm w-full animate-in fade-in zoom-in-95 duration-150 rounded-2xl">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-base flex items-center gap-2"><Pencil class="size-4 text-teal-600" />Sửa bàn</CardTitle>
                        <button @click="editingTable = null" class="text-muted-foreground hover:text-foreground"><X class="size-4" /></button>
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
                                <Input type="number" v-model="editForm.capacity" min="1" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Trạng thái</Label>
                                <select v-model="editForm.status"
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                                    <option value="available">Trống</option>
                                    <option value="occupied">Có khách</option>
                                    <option value="reserved">Đặt trước</option>
                                    <option value="cleaning">Chờ dọn bàn</option>
                                    <option value="inactive">Ngưng dùng</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-1.5 pt-2 border-t mt-4">
                            <Label>QR Code gọi món</Label>
                            <Button type="button" variant="outline" @click="showQrModal(editingTable!)" class="w-full flex items-center gap-1.5 text-xs h-9">
                                <QrCode class="size-4 text-teal-600" /> Xem mã QR & Link đặt món
                            </Button>
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="editingTable = null">Hủy</Button>
                            <Button type="submit" class="bg-teal-650 text-white" :disabled="editForm.processing">
                                {{ editForm.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Delete Confirm -->
        <div v-if="deletingTable" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <Card class="max-w-sm w-full animate-in fade-in zoom-in-95 duration-150 rounded-2xl">
                <CardContent class="pt-6 text-center space-y-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-950 mx-auto">
                        <Trash2 class="size-7 text-rose-600" />
                    </div>
                    <div>
                        <p class="font-semibold text-sm">Xóa bàn "{{ deletingTable.name }}"?</p>
                        <p class="text-xs text-muted-foreground mt-1">Bàn này sẽ bị xóa vĩnh viễn.</p>
                    </div>
                    <div class="flex justify-center gap-3">
                        <Button variant="outline" @click="deletingTable = null">Hủy</Button>
                        <Button class="bg-rose-600 hover:bg-rose-700 text-white" @click="submitDelete">Xóa</Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- QR Code Modal -->
        <div v-if="selectedQrTable" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl border border-teal-500/20 rounded-2xl">
                <CardHeader class="pb-3 border-b">
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-base flex items-center gap-2">
                            <QrCode class="size-5 text-teal-650 animate-pulse" />
                            Mã QR Gọi Món - {{ selectedQrTable.name }}
                        </CardTitle>
                        <button @click="selectedQrTable = null" class="text-muted-foreground hover:text-foreground">
                            <X class="size-4" />
                        </button>
                    </div>
                    <CardDescription>
                        Khu vực: {{ selectedQrTable.area?.name || 'Mặc định' }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="pt-6 flex flex-col items-center space-y-5">
                    <div class="p-4 bg-white rounded-2xl border border-slate-100 shadow-inner flex items-center justify-center">
                        <img 
                            :src="'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(getQrUrl(selectedQrTable))" 
                            :alt="'Mã QR Bàn ' + selectedQrTable.name"
                            class="size-56 object-contain"
                        />
                    </div>

                    <div class="w-full space-y-2">
                        <Label class="text-xs font-semibold text-slate-500">Liên kết đặt món của bàn:</Label>
                        <div class="flex items-center gap-2">
                            <Input 
                                readonly 
                                :value="getQrUrl(selectedQrTable)" 
                                class="bg-slate-50 dark:bg-slate-900 border-slate-200 text-xs font-mono select-all"
                            />
                            <Button size="sm" variant="outline" @click="copyLink(selectedQrTable)">
                                Sao chép
                            </Button>
                        </div>
                    </div>

                    <div class="w-full flex items-center gap-3 pt-3 border-t">
                        <Button 
                            class="flex-1 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl"
                            @click="printQrCode(selectedQrTable)"
                        >
                            In mã QR
                        </Button>
                        <Button 
                            variant="outline" 
                            class="flex-1 border-slate-200 text-slate-600 hover:text-slate-800 hover:bg-slate-50 rounded-xl"
                            @click="regenerateQrCode(selectedQrTable)"
                        >
                            Tạo lại mã
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Left: Area list selection -->
            <div class="lg:col-span-1">
                <Card class="shadow-sm rounded-2xl">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                            <MapPin class="size-4 text-teal-650" />
                            Khu vực ({{ areas.length }})
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-1.5 p-3 pt-0">
                        <button
                            @click="selectedArea = 'all'"
                            class="p-3 rounded-xl border text-xs text-left transition-all"
                            :class="selectedArea === 'all'
                                ? 'border-teal-350 bg-teal-50/50 dark:bg-teal-950/20 dark:border-teal-800 font-semibold'
                                : 'border-slate-100 bg-slate-50/50 dark:border-slate-800 hover:border-teal-200'"
                        >
                            <p class="font-bold">Tất cả khu vực</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">{{ tables.length }} bàn</p>
                        </button>
                        <button
                            v-for="area in areas" :key="area.id"
                            @click="selectedArea = area.id"
                            class="p-3 rounded-xl border text-xs text-left transition-all"
                            :class="selectedArea === area.id
                                ? 'border-teal-355 bg-teal-50/50 dark:bg-teal-950/20 dark:border-teal-800 font-semibold'
                                : 'border-slate-100 bg-slate-50/50 dark:border-slate-800 hover:border-teal-200'"
                        >
                            <p class="font-bold">{{ area.name }}</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">{{ area.tables_count }} bàn</p>
                        </button>
                        <div v-if="areas.length === 0" class="text-center py-6 text-xs text-slate-400">
                            Chưa có khu vực. Hãy nhấn Thêm khu vực.
                        </div>
                    </CardContent>
                </Card>

                <!-- Status Legend / Help -->
                <Card class="mt-4 shadow-sm rounded-2xl">
                    <CardContent class="p-4 space-y-3.5">
                        <h3 class="text-xs font-bold text-slate-700 dark:text-slate-350 uppercase tracking-wider">Trạng thái nhiệt bàn</h3>
                        <div class="flex flex-col gap-2.5 text-xs">
                            <span v-for="(cfg, key) in statusConfig" :key="key" class="flex items-center gap-2">
                                <span class="size-3.5 rounded-full border border-slate-200/50 dark:border-slate-700" :class="cfg.dot" />
                                <span class="font-medium text-slate-650 dark:text-slate-400">{{ cfg.label }}</span>
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="size-3.5 rounded-full bg-rose-500 animate-ping" />
                                <span class="font-bold text-rose-600 dark:text-rose-400">Đợi món lâu (>15 phút)</span>
                            </span>
                        </div>

                        <div v-if="isFloorPlanView" class="border-t pt-3 mt-3 text-[11px] text-slate-400">
                            <p class="flex items-start gap-1">
                                <AlertCircle class="size-3.5 text-teal-650 shrink-0 mt-0.5" />
                                <span>Bật nút <strong>"Chỉnh vị trí"</strong> ở góc phải để bắt đầu thiết lập bố cục bàn bằng cách kéo thả.</span>
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Area Layout / Grid -->
            <div class="lg:col-span-3">
                <Card class="shadow-sm rounded-2xl overflow-hidden">
                    <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-base flex items-center gap-2">
                                    {{ isFloorPlanView ? 'Thiết Kế Sơ Đồ Mặt Bằng' : 'Danh Sách Lưới Bàn' }}
                                    <span class="text-muted-foreground font-normal text-xs">({{ filteredTables.length }} bàn)</span>
                                </CardTitle>
                                <CardDescription>
                                    {{ isFloorPlanView ? 'Kéo thả các bàn để bố trí phòng. Hover trên bàn có khách để xem chi tiết.' : 'Click vào bàn để thay đổi trạng thái hoặc xóa.' }}
                                </CardDescription>
                            </div>
                            
                            <!-- Drag Switch for Floor Plan -->
                            <div v-if="isFloorPlanView && selectedArea !== 'all'" class="flex items-center gap-2">
                                <Label for="edit-layout-toggle" class="text-xs font-semibold text-slate-500 cursor-pointer select-none">
                                    {{ isEditLayout ? 'Chế độ Chỉnh vị trí (Đang bật)' : 'Bật Chỉnh vị trí' }}
                                </Label>
                                <button 
                                    id="edit-layout-toggle"
                                    @click="isEditLayout = !isEditLayout"
                                    :class="['relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none', isEditLayout ? 'bg-teal-600' : 'bg-slate-200 dark:bg-slate-700']"
                                >
                                    <span :class="['pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out', isEditLayout ? 'translate-x-5' : 'translate-x-0']" />
                                </button>
                            </div>
                            <div v-else-if="isFloorPlanView && selectedArea === 'all'" class="text-xs text-amber-600 bg-amber-50 dark:bg-amber-950/20 dark:text-amber-400 px-2 py-1 rounded-lg flex items-center gap-1">
                                <AlertCircle class="size-3.5" />
                                Chọn một khu vực cụ thể để di chuyển bàn
                            </div>
                        </div>
                    </CardHeader>
                    
                    <CardContent class="p-4 bg-slate-50/30 dark:bg-slate-900/10 min-h-[450px]">
                        <!-- ════ FLOOR PLAN VIEW ════ -->
                        <div 
                            v-if="isFloorPlanView" 
                            class="relative w-full h-[520px] rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden grid-bg shadow-inner transition-colors duration-300"
                        >
                            <!-- Empty State inside Canvas -->
                            <div v-if="filteredTables.length === 0" class="absolute inset-0 flex flex-col items-center justify-center text-center text-slate-400 p-6">
                                <LayoutGrid class="size-12 opacity-25 text-teal-600 mb-3" />
                                <p class="font-bold text-sm">Khu vực này chưa có bàn</p>
                                <p class="text-xs text-slate-400 max-w-xs mt-1">Nhấn "+ Thêm bàn" ở phía trên để bắt đầu thêm bàn vào khu vực này.</p>
                            </div>

                            <!-- Tables Nodes -->
                            <div 
                                v-for="t in filteredTables" 
                                :key="t.id"
                                :id="`table-card-${t.id}`"
                                :style="{ left: `${t.x_pos}%`, top: `${t.y_pos}%` }"
                                @mousedown="onDragStart(t, $event)"
                                @mouseenter="handleMouseEnter(t, $event)"
                                @mousemove="handleMouseMove($event)"
                                @mouseleave="handleMouseLeave"
                                @click="openEdit(t)"
                                :class="[
                                    'absolute size-20 rounded-2xl border-2 shadow-sm transition-shadow flex flex-col items-center justify-center select-none',
                                    isEditLayout ? 'cursor-move ring-2 ring-teal-500/20 hover:shadow-lg' : 'hover:shadow-md cursor-pointer',
                                    statusConfig[t.status]?.bg,
                                    isTableDelayed(t) ? 'border-rose-500 animate-pulse bg-rose-50/50 dark:bg-rose-950/20' : statusConfig[t.status]?.border
                                ]"
                            >
                                <!-- Flashing indicator for delayed orders -->
                                <span v-if="isTableDelayed(t)" class="absolute -top-1 -right-1 flex h-3 w-3">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-450 opacity-75"></span>
                                    <span class="relative inline-block rounded-full h-3 w-3 bg-rose-500"></span>
                                </span>

                                <Move v-if="isEditLayout" class="size-3.5 text-teal-600 absolute top-1.5 left-1.5 opacity-50" />
                                
                                <span class="size-2 rounded-full absolute top-2 right-2 border border-white/50" :class="statusConfig[t.status]?.dot" />
                                
                                <p class="font-bold text-sm text-slate-800 dark:text-slate-200 mt-1">{{ t.name }}</p>
                                <span class="text-[9px] text-slate-500 dark:text-slate-400 flex items-center gap-0.5 mt-0.5">
                                    <Users class="size-3 shrink-0" />
                                    {{ t.capacity }}
                                </span>
                                
                                <span class="text-[8px] font-semibold mt-1 px-1 py-0.2 rounded-md scale-90 uppercase tracking-wide" :class="statusConfig[t.status]?.color">
                                    {{ statusConfig[t.status]?.label }}
                                </span>

                                <!-- Quick controls inside table node when editing -->
                                <div v-if="isEditLayout" class="absolute -bottom-2 flex gap-1">
                                    <button 
                                        @click.stop="deletingTable = t" 
                                        class="bg-rose-600 text-white rounded-full p-1 shadow-md hover:bg-rose-700 active:scale-90"
                                        title="Xóa bàn"
                                    >
                                        <Trash2 class="size-2.5" />
                                    </button>
                                    <button 
                                        @click.stop="showQrModal(t)" 
                                        class="bg-teal-600 text-white rounded-full p-1 shadow-md hover:bg-teal-700 active:scale-90"
                                        title="Mã QR"
                                    >
                                        <QrCode class="size-2.5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- ════ GRID LIST VIEW ════ -->
                        <div v-else class="space-y-4">
                            <div v-if="filteredTables.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                                <div
                                    v-for="t in paginatedTables" :key="t.id"
                                    class="group relative rounded-2xl border p-4 bg-white dark:bg-slate-900 transition-all hover:shadow-md cursor-pointer border-slate-100 dark:border-slate-800"
                                    @click="openEdit(t)"
                                >
                                    <div class="flex items-start justify-between mb-2">
                                        <h4 class="font-bold text-sm">{{ t.name }}</h4>
                                        <span class="size-2 rounded-full mt-1 shrink-0" :class="statusConfig[t.status]?.dot" />
                                    </div>
                                    
                                    <div class="flex items-center gap-1.5 text-[10px] text-slate-500 mb-2">
                                        <Users class="size-3.5" />
                                        {{ t.capacity }} người
                                    </div>

                                    <div class="flex items-center justify-between mt-2.5">
                                        <span class="text-[9px] px-2 py-0.5 rounded-full font-semibold" :class="statusConfig[t.status]?.color">
                                            {{ statusConfig[t.status]?.label }}
                                        </span>
                                        <span v-if="t.area" class="text-[9px] text-slate-400 truncate max-w-[80px]">{{ t.area.name }}</span>
                                    </div>

                                    <!-- QR & Delete floating controls -->
                                    <div class="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button
                                            @click.stop="showQrModal(t)"
                                            class="p-1.5 rounded-lg bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-teal-650"
                                            title="Xem mã QR đặt món"
                                        >
                                            <QrCode class="size-3.5" />
                                        </button>
                                        <button
                                            @click.stop="deletingTable = t"
                                            class="p-1.5 rounded-lg bg-slate-50 dark:bg-slate-800 hover:bg-rose-50 dark:hover:bg-rose-950/20 text-rose-500"
                                            title="Xóa bàn"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-4 dark:bg-slate-800">
                                    <LayoutGrid class="size-9" />
                                </div>
                                <p class="font-semibold text-sm">Chưa có bàn nào trong khu vực này</p>
                                <p class="mt-1 text-xs text-muted-foreground">Nhấn "+ Thêm bàn" để tạo bàn mới.</p>
                            </div>

                            <!-- Pagination for Grid View -->
                            <div v-if="totalPages > 1" class="flex items-center justify-between border-t pt-4 mt-4 bg-white dark:bg-slate-900 px-4 py-3 rounded-2xl">
                                <div class="text-xs text-muted-foreground">
                                    Hiển thị {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredTables.length) }} trong tổng số {{ filteredTables.length }} bàn
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button variant="outline" size="sm" :disabled="currentPage === 1" @click="currentPage--" class="h-8 text-xs rounded-lg">Trước</Button>
                                    <Button
                                        v-for="page in visiblePages"
                                        :key="page"
                                        variant="outline"
                                        size="sm"
                                        @click="currentPage = page"
                                        :class="['h-8 w-8 text-xs p-0 rounded-lg', currentPage === page ? 'bg-teal-650 text-white font-semibold' : '']"
                                    >
                                        {{ page }}
                                    </Button>
                                    <Button variant="outline" size="sm" :disabled="currentPage === totalPages" @click="currentPage++" class="h-8 text-xs rounded-lg">Sau</Button>
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
            :style="{ left: `${tooltipCoords.x}px`, top: `${tooltipCoords.y}px` }"
            class="fixed z-50 w-72 pointer-events-none rounded-2xl border border-slate-200 bg-white p-4 shadow-xl dark:border-slate-800 dark:bg-slate-950 animate-in fade-in zoom-in-95 duration-100"
        >
            <div class="flex items-center justify-between border-b pb-2 mb-2">
                <div>
                    <h4 class="font-bold text-sm text-slate-800 dark:text-slate-200">
                        {{ props.tables.find(t => t.id === hoverTableId)?.name }}
                    </h4>
                    <p class="text-[10px] text-teal-650 dark:text-teal-400 font-mono">
                        {{ props.tables.find(t => t.id === hoverTableId)?.active_order?.order_number }}
                    </p>
                </div>
                <div class="flex items-center gap-1 text-[11px] font-semibold text-slate-500">
                    <Clock class="size-3.5" />
                    Đã ngồi {{ props.tables.find(t => t.id === hoverTableId)?.active_order?.elapsed_minutes }} phút
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex justify-between text-xs">
                    <span class="text-slate-400">Người phục vụ:</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-350">
                        {{ props.tables.find(t => t.id === hoverTableId)?.active_order?.waiter_name }}
                    </span>
                </div>

                <!-- Pending Items list -->
                <div class="mt-2">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center justify-between">
                        <span>Món ăn chưa ra ({{ props.tables.find(t => t.id === hoverTableId)?.active_order?.pending_items?.length || 0 }})</span>
                        <span class="text-rose-500 animate-pulse text-[9px] font-black" v-if="props.tables.find(t => t.id === hoverTableId)?.active_order?.pending_items?.some(i => i.is_late)">
                            ĐỢI LÂU!
                        </span>
                    </p>
                    <ul class="text-xs space-y-1 text-slate-650 dark:text-slate-450 max-h-32 overflow-y-auto pr-1">
                        <li 
                            v-for="(item, idx) in props.tables.find(t => t.id === hoverTableId)?.active_order?.pending_items"
                            :key="idx"
                            class="flex items-center justify-between py-1 border-b border-slate-100 last:border-0 dark:border-slate-900"
                        >
                            <span :class="['truncate max-w-[160px]', item.is_late ? 'text-rose-500 font-bold' : '']">
                                {{ item.name }}
                            </span>
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold">x{{ item.quantity }}</span>
                                <span v-if="item.is_late" class="size-2 rounded-full bg-rose-500 animate-ping" />
                            </div>
                        </li>
                        <li v-if="!props.tables.find(t => t.id === hoverTableId)?.active_order?.pending_items?.length" class="text-xs text-slate-400 italic py-1 text-center bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                            Tất cả món đã ra đủ
                        </li>
                    </ul>
                </div>

                <div class="flex justify-between items-center border-t pt-2 mt-2 text-xs">
                    <span class="text-slate-400 font-bold">Tổng thanh toán:</span>
                    <span class="text-sm font-black text-rose-600 dark:text-rose-400">
                        {{ vnd(props.tables.find(t => t.id === hoverTableId)?.active_order?.total_amount || 0) }}
                    </span>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.grid-bg {
    background-size: 24px 24px;
    background-image: radial-gradient(circle, rgba(13, 148, 136, 0.08) 1px, transparent 1px);
}
.dark .grid-bg {
    background-image: radial-gradient(circle, rgba(13, 148, 136, 0.15) 1px, transparent 1px);
}
</style>
