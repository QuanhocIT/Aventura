<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import {
    LayoutGrid, Plus, Pencil, Trash2, X, QrCode,
    Users, MapPin, CheckCircle2, Clock, AlertCircle
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { toast } from 'vue-sonner';

defineOptions({ layout: AppLayout });

type Area  = { id: number; name: string; code: string; tables_count: number };
type Table = { id: number; name: string; capacity: number; status: string; area: { id: number; name: string } | null; qr_code: string | null; qr_token: string };

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

const areaForm = useForm({ name: '' });
const tableForm = useForm({ name: '', area_id: props.areas[0]?.id ? String(props.areas[0].id) : '', capacity: '4' });
const editForm  = useForm({ name: '', capacity: '', status: '' });

const filteredTables = computed(() =>
    selectedArea.value === 'all'
        ? props.tables
        : props.tables.filter(t => t.area?.id === selectedArea.value)
);

const currentPage = ref(1);
const itemsPerPage = 10;
const totalPages = computed(() => Math.ceil(filteredTables.value.length / itemsPerPage));
const paginatedTables = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredTables.value.slice(start, start + itemsPerPage);
});
const visiblePages = computed(() => {
    const pages = [];
    const maxVisible = 5;
    let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages.value, start + maxVisible - 1);
    
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

const statusConfig: Record<string, { label: string; color: string; dot: string }> = {
    available: { label: 'Trống', color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400', dot: 'bg-emerald-500' },
    occupied:  { label: 'Có khách', color: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400', dot: 'bg-rose-500' },
    reserved:  { label: 'Đặt trước', color: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400', dot: 'bg-amber-500' },
    inactive:  { label: 'Ngưng dùng', color: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400', dot: 'bg-slate-400' },
};

const submitArea = () => areaForm.post('/tables/areas', {
    onSuccess: () => {
 areaForm.reset(); showAddArea.value = false; 
}
});

const submitTable = () => tableForm.post('/tables', {
    onSuccess: () => {
 tableForm.reset('name'); showAddTable.value = false; 
}
});

const openEdit = (t: Table) => {
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
    if (!table) return '';
    const tenantId = (page.props.tenant as any)?.id;
    return window.location.origin + '/customer/order/' + tenantId + '/' + table.qr_token;
};

const showQrModal = (table: Table | null) => {
    if (!table) return;
    selectedQrTable.value = table;
};

const copyLink = (table: Table | null) => {
    if (!table) return;
    const url = getQrUrl(table);
    navigator.clipboard.writeText(url);
    toast.success('Đã sao chép liên kết đặt món tại bàn ' + table.name);
};

const printQrCode = (table: Table | null) => {
    if (!table) return;
    const qrUrl = getQrUrl(table);
    const qrImageUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrUrl)}`;
    
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;
    
    printWindow.document.write(`
        <html>
            <head>
                <title>Mã QR - ${table.name}</title>
                <style>
                    body {
                        font-family: system-ui, -apple-system, sans-serif;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        height: 90vh;
                        margin: 0;
                        text-align: center;
                    }
                    .container {
                        border: 2px dashed #0D9488;
                        padding: 30px;
                        border-radius: 20px;
                        background: #FAFAFA;
                    }
                    h1 {
                        font-size: 28px;
                        color: #0F172A;
                        margin-bottom: 5px;
                    }
                    p {
                        font-size: 16px;
                        color: #64748B;
                        margin-bottom: 25px;
                    }
                    img {
                        width: 250px;
                        height: 250px;
                    }
                    .footer {
                        margin-top: 25px;
                        font-size: 12px;
                        color: #94A3B8;
                    }
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

const regenerateQrCode = (table: Table | null) => {
    if (!table) return;
    if (confirm('Bạn có chắc chắn muốn tạo mới mã QR cho bàn "' + table.name + '"? Mã QR cũ sẽ không thể sử dụng để quét đặt món được nữa.')) {
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
</script>

<template>
    <Head title="Quản lý bàn" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-50 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400">
                    <LayoutGrid class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Quản Lý Bàn & Khu Vực</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Tạo sơ đồ bàn, phân khu vực và theo dõi trạng thái bàn theo thời gian thực.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" class="h-10 text-xs" @click="showAddArea = true">
                    <MapPin class="size-4 mr-2 text-teal-600" />Thêm khu vực
                </Button>
                <Button class="h-10 text-xs bg-teal-600 hover:bg-teal-700 text-white font-semibold" @click="showAddTable = true" :disabled="areas.length === 0">
                    <Plus class="size-4 mr-2" />Thêm bàn
                </Button>
            </div>
        </div>

        <!-- Add Area Modal -->
        <div v-if="showAddArea" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <Card class="max-w-sm w-full animate-in fade-in zoom-in-95 duration-150">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-base">Tạo khu vực mới</CardTitle>
                        <button @click="showAddArea = false" class="text-muted-foreground hover:text-foreground"><X class="size-4" /></button>
                    </div>
                    <CardDescription>Ví dụ: Phòng VIP, Sân thượng, Tầng 1...</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitArea" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label>Tên khu vực <span class="text-rose-500">*</span></Label>
                            <Input v-model="areaForm.name" placeholder="VD: Phòng VIP" required autofocus />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="showAddArea = false">Hủy</Button>
                            <Button type="submit" class="bg-teal-600 text-white" :disabled="areaForm.processing">
                                {{ areaForm.processing ? 'Đang tạo...' : 'Tạo khu vực' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Add Table Modal -->
        <div v-if="showAddTable" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <Card class="max-w-sm w-full animate-in fade-in zoom-in-95 duration-150">
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
                            <Input v-model="tableForm.name" placeholder="VD: Bàn 01, B1, VIP-1..." required />
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
                            <Button type="submit" class="bg-teal-600 text-white" :disabled="tableForm.processing">
                                {{ tableForm.processing ? 'Đang thêm...' : 'Thêm bàn' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Edit Table Modal -->
        <div v-if="editingTable" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <Card class="max-w-sm w-full animate-in fade-in zoom-in-95 duration-150">
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
                            <Button type="submit" class="bg-teal-600 text-white" :disabled="editForm.processing">
                                {{ editForm.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Delete Confirm -->
        <div v-if="deletingTable" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <Card class="max-w-sm w-full animate-in fade-in zoom-in-95 duration-150">
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
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl border border-teal-500/20">
                <CardHeader class="pb-3 border-b">
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-base flex items-center gap-2">
                            <QrCode class="size-5 text-teal-600 animate-pulse" />
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
                    <!-- QR Image Container -->
                    <div class="p-4 bg-white rounded-2xl border border-slate-100 shadow-inner flex items-center justify-center">
                        <img 
                            :src="'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(getQrUrl(selectedQrTable))" 
                            :alt="'Mã QR Bàn ' + selectedQrTable.name"
                            class="size-56 object-contain"
                        />
                    </div>

                    <!-- URL Info -->
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

                    <!-- Actions -->
                    <div class="w-full flex items-center gap-3 pt-3 border-t">
                        <Button 
                            class="flex-1 bg-teal-600 hover:bg-teal-700 text-white font-semibold"
                            @click="printQrCode(selectedQrTable)"
                        >
                            In mã QR
                        </Button>
                        <Button 
                            variant="outline" 
                            class="flex-1 border-slate-200 text-slate-600 hover:text-slate-800 hover:bg-slate-50"
                            @click="regenerateQrCode(selectedQrTable)"
                        >
                            Tạo lại mã
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Left: Area list -->
            <div class="lg:col-span-1">
                <Card class="shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-bold">Khu vực ({{ areas.length }})</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-1.5 p-3 pt-0">
                        <button
                            @click="selectedArea = 'all'"
                            class="p-3 rounded-xl border text-xs text-left transition-colors"
                            :class="selectedArea === 'all'
                                ? 'border-teal-300 bg-teal-50 dark:bg-teal-950/30 dark:border-teal-800'
                                : 'border-slate-100 bg-slate-50/50 dark:border-slate-800 hover:border-teal-200'"
                        >
                            <p class="font-bold">Tất cả khu vực</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">{{ tables.length }} bàn</p>
                        </button>
                        <button
                            v-for="area in areas" :key="area.id"
                            @click="selectedArea = area.id"
                            class="p-3 rounded-xl border text-xs text-left transition-colors"
                            :class="selectedArea === area.id
                                ? 'border-teal-300 bg-teal-50 dark:bg-teal-950/30 dark:border-teal-800'
                                : 'border-slate-100 bg-slate-50/50 dark:border-slate-800 hover:border-teal-200'"
                        >
                            <p class="font-bold">{{ area.name }}</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">{{ area.tables_count }} bàn</p>
                        </button>
                        <div v-if="areas.length === 0" class="text-center py-6 text-xs text-slate-400">
                            Chưa có khu vực. Thêm khu vực trước.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right: Table grid -->
            <div class="lg:col-span-3">
                <Card class="shadow-sm">
                    <CardHeader class="pb-3 border-b">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-base">
                                    Sơ đồ bàn
                                    <span class="text-muted-foreground font-normal text-sm ml-1">({{ filteredTables.length }} bàn)</span>
                                </CardTitle>
                                <CardDescription>Click vào bàn để sửa thông tin hoặc đổi trạng thái.</CardDescription>
                            </div>
                            <!-- Status legend -->
                            <div class="hidden sm:flex items-center gap-3 text-[10px] text-slate-500">
                                <span v-for="(cfg, key) in statusConfig" :key="key" class="flex items-center gap-1">
                                    <span class="size-2 rounded-full" :class="cfg.dot" />
                                    {{ cfg.label }}
                                </span>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-4">
                        <div v-if="filteredTables.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                            <div
                                v-for="t in paginatedTables" :key="t.id"
                                class="group relative rounded-xl border p-4 transition-all hover:shadow-md cursor-pointer"
                                :class="t.status === 'available'
                                    ? 'border-emerald-200 bg-emerald-50/40 dark:border-emerald-900/40 dark:bg-emerald-950/10'
                                    : t.status === 'occupied'
                                        ? 'border-rose-200 bg-rose-50/40 dark:border-rose-900/40 dark:bg-rose-950/10'
                                        : t.status === 'reserved'
                                            ? 'border-amber-200 bg-amber-50/40 dark:border-amber-900/40 dark:bg-amber-950/10'
                                            : 'border-slate-200 bg-slate-50/40 dark:border-slate-800 opacity-60'"
                                @click="openEdit(t)"
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="font-bold text-sm">{{ t.name }}</h4>
                                    <span class="size-2 rounded-full mt-1 shrink-0" :class="statusConfig[t.status]?.dot ?? 'bg-slate-400'" />
                                </div>
                                <div class="flex items-center gap-1 text-[10px] text-slate-500 mb-2">
                                    <Users class="size-3" />
                                    {{ t.capacity }} người
                                </div>
                                <span class="text-[9px] px-1.5 py-0.5 rounded-full font-semibold" :class="statusConfig[t.status]?.color">
                                    {{ statusConfig[t.status]?.label }}
                                </span>
                                <p v-if="t.area" class="text-[9px] text-slate-400 mt-1.5 truncate">{{ t.area.name }}</p>

                                <!-- QrCode button on hover -->
                                <button
                                    @click.stop="showQrModal(t)"
                                    class="absolute top-2 right-8 p-1 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 text-teal-600 opacity-0 group-hover:opacity-100 transition-opacity"
                                    title="Xem mã QR đặt món"
                                >
                                    <QrCode class="size-3" />
                                </button>

                                <!-- Delete button on hover -->
                                <button
                                    @click.stop="deletingTable = t"
                                    class="absolute top-2 right-2 p-1 rounded-md hover:bg-rose-100 dark:hover:bg-rose-950/40 text-rose-500 opacity-0 group-hover:opacity-100 transition-opacity"
                                >
                                    <Trash2 class="size-3" />
                                </button>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-14 text-center">
                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground/40 mb-4">
                                <LayoutGrid class="size-9" />
                            </div>
                            <p class="font-semibold text-sm">Chưa có bàn nào</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ areas.length === 0 ? 'Tạo khu vực trước, sau đó thêm bàn.' : 'Nhấn "+ Thêm bàn" để bắt đầu.' }}
                            </p>
                        </div>

                        <!-- Pagination -->
                        <div v-if="totalPages > 1" class="flex items-center justify-between border-t pt-4 mt-4 bg-slate-50/50 dark:bg-slate-900/30">
                            <div class="text-xs text-muted-foreground">
                                Hiển thị {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredTables.length) }} trong tổng số {{ filteredTables.length }} bàn
                            </div>
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="currentPage === 1"
                                    @click="currentPage--"
                                    class="h-8 text-xs"
                                >
                                    Trước
                                </Button>
                                <Button
                                    v-for="page in visiblePages"
                                    :key="page"
                                    variant="outline"
                                    size="sm"
                                    @click="currentPage = page"
                                    :class="['h-8 w-8 text-xs p-0', currentPage === page ? 'bg-teal-650 text-white font-semibold hover:bg-teal-700' : '']"
                                >
                                    {{ page }}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="currentPage === totalPages"
                                    @click="currentPage++"
                                    class="h-8 text-xs"
                                >
                                    Sau
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
