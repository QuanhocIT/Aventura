<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle, CheckCircle2, Loader2, Plus, Settings, Trash2, Wrench,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    equipment: any[];
    recentLogs: any[];
    stats: { total: number; active: number; broken: number; pending_maintenance: number; total_cost_ytd: number };
}>();

const page = usePage();
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
});

const activeTab = ref<'list' | 'maintenance'>('list');

const catLabel: Record<string, string> = {
    kitchen: 'Bếp gas/Nồi', refrigeration: 'Tủ lạnh/Đông', cleaning: 'Máy rửa/Vệ sinh',
    pos: 'POS/Camera', hvac: 'Điều hòa/Quạt', furniture: 'Bàn ghế', other: 'Khác',
};
const statusLabel: Record<string, string> = { active: 'Hoạt động', maintenance: 'Đang bảo trì', broken: 'Hỏng', retired: 'Thanh lý' };
const statusColor: Record<string, string> = {
    active: 'bg-green-100 text-green-800', maintenance: 'bg-amber-100 text-amber-800',
    broken: 'bg-red-100 text-red-800', retired: 'bg-gray-100 text-gray-600',
};
const logTypeLabel: Record<string, string> = { scheduled: 'Định kỳ', repair: 'Sửa chữa', inspection: 'Kiểm tra', replacement: 'Thay thế' };

// Add equipment
const showAddDialog = ref(false);
const addForm = useForm({
    name: '', category: 'kitchen' as string, brand: '', model_number: '',
    serial_number: '', purchase_date: '', purchase_cost: 0, warranty_months: 12, location: '', notes: '',
});
function submitAdd() {
    addForm.post('/equipment', { onSuccess: () => { showAddDialog.value = false; addForm.reset(); } });
}

// Report issue
const showReportDialog = ref(false);
const reportForm = useForm({
    equipment_id: null as number | null, title: '', description: '',
    type: 'repair' as string, cost: 0, scheduled_date: '',
});
function openReport(eqId: number) { reportForm.reset(); reportForm.equipment_id = eqId; reportForm.type = 'repair'; showReportDialog.value = true; }
function submitReport() {
    reportForm.post('/equipment/report-issue', { onSuccess: () => { showReportDialog.value = false; } });
}

function completeLog(logId: number) {
    router.post(`/equipment/logs/${logId}/complete`, {});
}
</script>

<template>
    <Head title="Thiết bị & Bảo trì" />

    <div class="flex flex-col gap-6 p-6 max-w-6xl mx-auto">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600">
                    <Settings class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold">Quản Lý Thiết Bị & Bảo Trì</h1>
                    <p class="text-sm text-muted-foreground">Danh sách thiết bị, lịch bảo trì, báo hỏng, chi phí sửa chữa.</p>
                </div>
            </div>
            <Button @click="showAddDialog = true" class="gap-1.5"><Plus class="size-4" /> Thêm thiết bị</Button>
        </div>

        <!-- KPI -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Tổng thiết bị</p><p class="text-2xl font-bold">{{ stats.total }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Hoạt động</p><p class="text-2xl font-bold text-green-600">{{ stats.active }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Hỏng</p><p class="text-2xl font-bold text-red-600">{{ stats.broken }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Chờ bảo trì</p><p class="text-2xl font-bold text-amber-600">{{ stats.pending_maintenance }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Chi phí BT (năm)</p><p class="text-2xl font-bold">{{ stats.total_cost_ytd.toLocaleString() }}đ</p></CardContent></Card>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 border-b pb-2">
            <button v-for="tab in [{key:'list',label:'Danh sách thiết bị'},{key:'maintenance',label:'Nhật ký bảo trì'}]" :key="tab.key"
                @click="activeTab = tab.key as any"
                :class="['px-4 py-2 rounded-lg text-sm font-semibold', activeTab === tab.key ? 'bg-slate-700 text-white dark:bg-slate-600' : 'hover:bg-muted']"
            >{{ tab.label }}</button>
        </div>

        <!-- Equipment list -->
        <div v-if="activeTab === 'list'">
            <Card>
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr class="text-left text-xs text-muted-foreground">
                                <th class="px-4 py-3">Thiết bị</th>
                                <th class="px-4 py-3">Loại</th>
                                <th class="px-4 py-3">Trạng thái</th>
                                <th class="px-4 py-3 text-right">Giá mua</th>
                                <th class="px-4 py-3 text-right">Chi phí BT</th>
                                <th class="px-4 py-3">Bảo hành</th>
                                <th class="px-4 py-3 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in equipment" :key="e.id" class="border-b last:border-0 hover:bg-muted/30">
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ e.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ e.brand }} {{ e.model_number }} · {{ e.location || '—' }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs">{{ catLabel[e.category] ?? e.category }}</td>
                                <td class="px-4 py-3"><Badge :class="statusColor[e.status]" class="text-xs">{{ statusLabel[e.status] }}</Badge></td>
                                <td class="px-4 py-3 text-right text-xs">{{ Number(e.purchase_cost).toLocaleString() }}đ</td>
                                <td class="px-4 py-3 text-right text-xs font-medium">{{ e.total_maintenance_cost.toLocaleString() }}đ</td>
                                <td class="px-4 py-3 text-xs">
                                    <span v-if="e.under_warranty" class="text-green-600">Còn BH</span>
                                    <span v-else-if="e.warranty_expiry" class="text-red-500">Hết BH</span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Button variant="ghost" size="sm" class="gap-1 text-xs text-amber-600" @click="openReport(e.id)">
                                        <Wrench class="size-3" /> Báo hỏng
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!equipment.length" class="text-center text-muted-foreground py-12">Chưa có thiết bị nào.</p>
                </CardContent>
            </Card>
        </div>

        <!-- Maintenance logs -->
        <div v-if="activeTab === 'maintenance'">
            <Card>
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr class="text-left text-xs text-muted-foreground">
                                <th class="px-4 py-3">Thiết bị</th>
                                <th class="px-4 py-3">Loại</th>
                                <th class="px-4 py-3">Mô tả</th>
                                <th class="px-4 py-3">Trạng thái</th>
                                <th class="px-4 py-3 text-right">Chi phí</th>
                                <th class="px-4 py-3 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in recentLogs" :key="log.id" class="border-b last:border-0">
                                <td class="px-4 py-3 font-medium">{{ log.equipment?.name ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs">{{ logTypeLabel[log.type] }}</td>
                                <td class="px-4 py-3 text-xs max-w-xs truncate">{{ log.title }}</td>
                                <td class="px-4 py-3">
                                    <Badge :variant="log.status === 'completed' ? 'default' : 'secondary'" class="text-xs">
                                        {{ log.status === 'completed' ? 'Xong' : log.status === 'pending' ? 'Chờ' : log.status }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right text-xs font-medium">{{ Number(log.cost).toLocaleString() }}đ</td>
                                <td class="px-4 py-3 text-right">
                                    <Button v-if="log.status !== 'completed'" variant="ghost" size="sm" class="gap-1 text-xs text-green-600" @click="completeLog(log.id)">
                                        <CheckCircle2 class="size-3" /> Xong
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!recentLogs.length" class="text-center text-muted-foreground py-12">Chưa có nhật ký bảo trì.</p>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Add equipment dialog -->
    <Dialog v-model:open="showAddDialog">
        <DialogContent class="max-w-lg max-h-[80vh] overflow-y-auto">
            <DialogHeader><DialogTitle>Thêm Thiết Bị</DialogTitle></DialogHeader>
            <form @submit.prevent="submitAdd" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5"><Label>Tên thiết bị</Label><Input v-model="addForm.name" required /></div>
                    <div class="grid gap-1.5">
                        <Label>Loại</Label>
                        <select v-model="addForm.category" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="kitchen">Bếp gas/Nồi</option><option value="refrigeration">Tủ lạnh/Đông</option>
                            <option value="cleaning">Máy rửa</option><option value="pos">POS/Camera</option>
                            <option value="hvac">Điều hòa</option><option value="furniture">Bàn ghế</option><option value="other">Khác</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5"><Label>Hãng</Label><Input v-model="addForm.brand" /></div>
                    <div class="grid gap-1.5"><Label>Model</Label><Input v-model="addForm.model_number" /></div>
                    <div class="grid gap-1.5"><Label>Serial</Label><Input v-model="addForm.serial_number" /></div>
                    <div class="grid gap-1.5"><Label>Vị trí</Label><Input v-model="addForm.location" placeholder="Bếp chính, Quầy bar..." /></div>
                    <div class="grid gap-1.5"><Label>Ngày mua</Label><Input type="date" v-model="addForm.purchase_date" /></div>
                    <div class="grid gap-1.5"><Label>Giá mua (VND)</Label><Input type="number" v-model="addForm.purchase_cost" /></div>
                    <div class="grid gap-1.5"><Label>Bảo hành (tháng)</Label><Input type="number" v-model="addForm.warranty_months" /></div>
                </div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="showAddDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="addForm.processing">{{ addForm.processing ? 'Đang thêm...' : 'Thêm thiết bị' }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Report issue dialog -->
    <Dialog v-model:open="showReportDialog">
        <DialogContent class="max-w-md">
            <DialogHeader><DialogTitle class="flex items-center gap-2"><AlertTriangle class="size-5 text-amber-500" /> Báo Hỏng / Bảo Trì</DialogTitle></DialogHeader>
            <form @submit.prevent="submitReport" class="space-y-4">
                <div class="grid gap-1.5"><Label>Tiêu đề</Label><Input v-model="reportForm.title" placeholder="Tủ lạnh không lạnh..." required /></div>
                <div class="grid gap-1.5"><Label>Mô tả chi tiết</Label><Input v-model="reportForm.description" /></div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Loại</Label>
                        <select v-model="reportForm.type" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="repair">Sửa chữa</option><option value="scheduled">Bảo trì định kỳ</option>
                            <option value="inspection">Kiểm tra</option><option value="replacement">Thay thế</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5"><Label>Chi phí dự kiến</Label><Input type="number" v-model="reportForm.cost" /></div>
                </div>
                <div class="grid gap-1.5"><Label>Ngày lên lịch</Label><Input type="date" v-model="reportForm.scheduled_date" /></div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="showReportDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="reportForm.processing">Gửi báo cáo</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
