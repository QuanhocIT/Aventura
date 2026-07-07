<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Plus, Pencil, Trash2, ToggleLeft, ToggleRight,
    Bike, Car, Truck, UserCheck, Package, AlertCircle,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

// ─── Types ────────────────────────────────────────────────────────────────────
interface ShipperRow {
    id: number;
    employee_id: number | null;
    name: string;
    vehicle_type: 'bike' | 'motorbike' | 'car';
    vehicle_plate: string | null;
    is_active: boolean;
    max_orders_per_batch: number;
    max_capacity_kg: number;
    deleted_at: string | null;
}

interface EmployeeOption {
    id: number;
    full_name: string;
}

const props = defineProps<{
    shippers: ShipperRow[];
    employees: EmployeeOption[];
}>();

// ─── State ────────────────────────────────────────────────────────────────────
const shippers = ref<ShipperRow[]>(props.shippers);
const employees = ref<EmployeeOption[]>(props.employees);

const showModal   = ref(false);
const editId      = ref<number | null>(null);
const submitting  = ref(false);
const filterActive = ref<'all' | 'active' | 'inactive'>('all');

const form = ref({
    employee_id:          '' as string | number,
    vehicle_type:         'motorbike' as 'bike' | 'motorbike' | 'car',
    vehicle_plate:        '',
    max_orders_per_batch: 5,
    max_capacity_kg:      20,
    is_active:            true,
});

const formErrors = ref<Record<string, string>>({});

// ─── Computed ─────────────────────────────────────────────────────────────────
const filteredShippers = computed(() => {
    return shippers.value.filter(s => {
        if (filterActive.value === 'active')   {
return s.is_active && !s.deleted_at;
}

        if (filterActive.value === 'inactive') {
return !s.is_active || s.deleted_at;
}

        return true;
    });
});

const VEHICLE_LABELS: Record<string, string> = {
    bike: 'Xe đạp',
    motorbike: 'Xe máy',
    car: 'Ô tô',
};

const VEHICLE_ICONS: Record<string, any> = {
    bike: Bike,
    motorbike: Truck,
    car: Car,
};

// ─── Modal helpers ────────────────────────────────────────────────────────────
function openCreate() {
    editId.value = null;
    form.value = {
        employee_id: '',
        vehicle_type: 'motorbike',
        vehicle_plate: '',
        max_orders_per_batch: 5,
        max_capacity_kg: 20,
        is_active: true,
    };
    formErrors.value = {};
    showModal.value = true;
}

function openEdit(s: ShipperRow) {
    editId.value = s.id;
    form.value = {
        employee_id: s.employee_id ?? '',
        vehicle_type: s.vehicle_type,
        vehicle_plate: s.vehicle_plate ?? '',
        max_orders_per_batch: s.max_orders_per_batch,
        max_capacity_kg: s.max_capacity_kg,
        is_active: s.is_active,
    };
    formErrors.value = {};
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editId.value = null;
}

// ─── API calls ────────────────────────────────────────────────────────────────
async function submitForm() {
    if (!form.value.employee_id && !editId.value) {
        formErrors.value = { employee_id: 'Vui lòng chọn nhân viên' };

        return;
    }

    submitting.value = true;
    formErrors.value = {};

    try {
        if (editId.value) {
            const { data } = await axios.patch(`/delivery/shippers/${editId.value}`, {
                vehicle_type:         form.value.vehicle_type,
                vehicle_plate:        form.value.vehicle_plate || null,
                max_orders_per_batch: form.value.max_orders_per_batch,
                max_capacity_kg:      form.value.max_capacity_kg,
                is_active:            form.value.is_active,
            });
            const idx = shippers.value.findIndex(s => s.id === editId.value);

            if (idx >= 0) {
shippers.value[idx] = { ...shippers.value[idx], ...data };
}

            toast.success('Đã cập nhật thông tin shipper');
        } else {
            const { data } = await axios.post('/delivery/shippers', {
                employee_id:          form.value.employee_id,
                vehicle_type:         form.value.vehicle_type,
                vehicle_plate:        form.value.vehicle_plate || null,
                max_orders_per_batch: form.value.max_orders_per_batch,
                max_capacity_kg:      form.value.max_capacity_kg,
            });
            const newShipper: ShipperRow = {
                id: data.id,
                employee_id: data.employee_id,
                name: data.employee?.full_name ?? `Shipper #${data.id}`,
                vehicle_type: data.vehicle_type,
                vehicle_plate: data.vehicle_plate,
                is_active: data.is_active,
                max_orders_per_batch: data.max_orders_per_batch,
                max_capacity_kg: data.max_capacity_kg,
                deleted_at: null,
            };
            shippers.value.unshift(newShipper);
            // Remove from employee dropdown
            employees.value = employees.value.filter(e => e.id !== Number(form.value.employee_id));
            toast.success('Đã thêm shipper mới');
        }

        closeModal();
    } catch (err: any) {
        if (err.response?.status === 422) {
            const errs = err.response.data.errors ?? {};
            formErrors.value = Object.fromEntries(
                Object.entries(errs).map(([k, v]) => [k, (v as string[])[0]])
            );
        } else {
            toast.error('Lỗi kết nối. Vui lòng thử lại.');
        }
    } finally {
        submitting.value = false;
    }
}

async function toggleActive(s: ShipperRow) {
    try {
        await axios.patch(`/delivery/shippers/${s.id}`, { is_active: !s.is_active });
        const idx = shippers.value.findIndex(sh => sh.id === s.id);

        if (idx >= 0) {
shippers.value[idx].is_active = !s.is_active;
}

        toast.success(s.is_active ? 'Đã tạm ngưng shipper' : 'Đã kích hoạt shipper');
    } catch {
        toast.error('Không thể thay đổi trạng thái');
    }
}

async function deleteShipper(s: ShipperRow) {
    if (!(await confirmDialog({ title: 'Xác nhận thao tác', description: `Xác nhận vô hiệu hóa shipper "${s.name}"?` }))) {
return;
}

    try {
        await axios.delete(`/delivery/shippers/${s.id}`);
        const idx = shippers.value.findIndex(sh => sh.id === s.id);

        if (idx >= 0) {
            shippers.value[idx].is_active = false;
            shippers.value[idx].deleted_at = new Date().toISOString();
        }

        toast.success('Đã vô hiệu hóa shipper');
    } catch {
        toast.error('Không thể xóa shipper');
    }
}

function shipperStatus(s: ShipperRow): 'deleted' | 'inactive' | 'active' {
    if (s.deleted_at) {
return 'deleted';
}

    if (!s.is_active) {
return 'inactive';
}

    return 'active';
}
</script>

<template>
    <Head title="Quản lý Shipper" />

    <div class="p-6 space-y-6 max-w-5xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Đội ngũ Shipper</h1>
                <p class="text-sm text-muted-foreground mt-1">Quản lý nhân viên giao hàng</p>
            </div>
            <Button @click="openCreate" class="gap-2">
                <Plus class="h-4 w-4" />
                Thêm shipper
            </Button>
        </div>

        <!-- Stats row -->
        <div class="grid grid-cols-3 gap-4">
            <Card>
                <CardContent class="pt-4">
                    <p class="text-xs text-muted-foreground">Tổng cộng</p>
                    <p class="text-2xl font-bold">{{ shippers.length }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-4">
                    <p class="text-xs text-muted-foreground">Đang hoạt động</p>
                    <p class="text-2xl font-bold text-green-600">{{ shippers.filter(s => s.is_active && !s.deleted_at).length }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-4">
                    <p class="text-xs text-muted-foreground">Không hoạt động</p>
                    <p class="text-2xl font-bold text-muted-foreground">{{ shippers.filter(s => !s.is_active || s.deleted_at).length }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- Filter tabs -->
        <div class="flex gap-2 border-b">
            <button
                v-for="f in [['all','Tất cả'], ['active','Đang hoạt động'], ['inactive','Không hoạt động']] as const"
                :key="f[0]"
                class="pb-2 px-1 text-sm border-b-2 transition-colors"
                :class="filterActive === f[0]
                    ? 'border-primary text-primary font-medium'
                    : 'border-transparent text-muted-foreground hover:text-foreground'"
                @click="filterActive = f[0]"
            >{{ f[1] }}</button>
        </div>

        <!-- Shipper table -->
        <Card>
            <CardContent class="p-0">
                <div v-if="filteredShippers.length === 0" class="py-16 text-center text-muted-foreground">
                    <UserCheck class="mx-auto mb-3 h-10 w-10 opacity-30" />
                    <p>Chưa có shipper nào</p>
                    <Button variant="outline" size="sm" class="mt-3" @click="openCreate">Thêm shipper đầu tiên</Button>
                </div>

                <table v-else class="w-full text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="py-3 pl-4 text-left font-medium text-muted-foreground">Nhân viên</th>
                            <th class="py-3 px-4 text-left font-medium text-muted-foreground">Phương tiện</th>
                            <th class="py-3 px-4 text-left font-medium text-muted-foreground">Tải tối đa</th>
                            <th class="py-3 px-4 text-left font-medium text-muted-foreground">Trạng thái</th>
                            <th class="py-3 pr-4 text-right font-medium text-muted-foreground">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="s in filteredShippers"
                            :key="s.id"
                            class="hover:bg-muted/20 transition-colors"
                            :class="{ 'opacity-50': s.deleted_at }"
                        >
                            <td class="py-3 pl-4">
                                <div class="font-medium">{{ s.name }}</div>
                                <div class="text-xs text-muted-foreground">ID #{{ s.id }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2">
                                    <component :is="VEHICLE_ICONS[s.vehicle_type]" class="h-4 w-4 text-muted-foreground" />
                                    <span>{{ VEHICLE_LABELS[s.vehicle_type] }}</span>
                                </div>
                                <div v-if="s.vehicle_plate" class="text-xs text-muted-foreground mt-0.5">{{ s.vehicle_plate }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-xs space-y-0.5">
                                    <div class="flex items-center gap-1">
                                        <Package class="h-3 w-3 text-muted-foreground" />
                                        {{ s.max_orders_per_batch }} đơn
                                    </div>
                                    <div class="text-muted-foreground">{{ s.max_capacity_kg }} kg</div>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <Badge
                                    :variant="shipperStatus(s) === 'active' ? 'default' : 'secondary'"
                                    :class="shipperStatus(s) === 'deleted' ? 'line-through opacity-60' : ''"
                                >
                                    {{ shipperStatus(s) === 'active' ? 'Hoạt động'
                                       : shipperStatus(s) === 'inactive' ? 'Tạm ngưng'
                                       : 'Đã xóa' }}
                                </Badge>
                            </td>
                            <td class="py-3 pr-4">
                                <div class="flex items-center justify-end gap-1">
                                    <Button
                                        v-if="!s.deleted_at"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        :title="s.is_active ? 'Tạm ngưng' : 'Kích hoạt'"
                                        @click="toggleActive(s)"
                                    >
                                        <ToggleRight v-if="s.is_active" class="h-4 w-4 text-green-600" />
                                        <ToggleLeft v-else class="h-4 w-4 text-muted-foreground" />
                                    </Button>
                                    <Button
                                        v-if="!s.deleted_at"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        title="Chỉnh sửa"
                                        @click="openEdit(s)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        v-if="!s.deleted_at"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-destructive hover:text-destructive"
                                        title="Vô hiệu hóa"
                                        @click="deleteShipper(s)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                    <span v-if="s.deleted_at" class="text-xs text-muted-foreground">Đã vô hiệu</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>

    <!-- ── Modal thêm/sửa shipper ──────────────────────────────────────────── -->
    <Teleport to="body">
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="closeModal"
        >
            <div class="bg-background rounded-xl shadow-2xl w-full max-w-md mx-4 p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">
                        {{ editId ? 'Chỉnh sửa Shipper' : 'Thêm Shipper mới' }}
                    </h2>
                    <button class="text-muted-foreground hover:text-foreground" @click="closeModal">✕</button>
                </div>

                <!-- Employee select (only on create) -->
                <div v-if="!editId" class="space-y-1.5">
                    <Label>Nhân viên <span class="text-destructive">*</span></Label>
                    <select
                        v-model="form.employee_id"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                    >
                        <option value="" disabled>Chọn nhân viên...</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
                    </select>
                    <p v-if="formErrors.employee_id" class="text-xs text-destructive flex items-center gap-1">
                        <AlertCircle class="h-3 w-3" /> {{ formErrors.employee_id }}
                    </p>
                    <p v-if="employees.length === 0" class="text-xs text-amber-600">
                        Tất cả nhân viên đã được đăng ký làm shipper.
                    </p>
                </div>

                <!-- Vehicle type -->
                <div class="space-y-1.5">
                    <Label>Loại phương tiện</Label>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="v in [['bike','🚲 Xe đạp'], ['motorbike','🏍 Xe máy'], ['car','🚗 Ô tô']] as const"
                            :key="v[0]"
                            type="button"
                            class="rounded-lg border py-2 text-sm text-center transition-colors"
                            :class="form.vehicle_type === v[0]
                                ? 'border-primary bg-primary/10 text-primary font-medium'
                                : 'border-border hover:border-muted-foreground'"
                            @click="form.vehicle_type = v[0]"
                        >{{ v[1] }}</button>
                    </div>
                </div>

                <!-- Plate -->
                <div class="space-y-1.5">
                    <Label>Biển số xe</Label>
                    <Input v-model="form.vehicle_plate" placeholder="VD: 51F1-12345" />
                </div>

                <!-- Max orders -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <Label>Đơn tối đa / batch</Label>
                        <Input v-model.number="form.max_orders_per_batch" type="number" min="1" max="20" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Tải tối đa (kg)</Label>
                        <Input v-model.number="form.max_capacity_kg" type="number" min="1" max="500" />
                    </div>
                </div>

                <!-- is_active (only on edit) -->
                <div v-if="editId" class="flex items-center gap-3">
                    <button
                        type="button"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                        :class="form.is_active ? 'bg-primary' : 'bg-muted'"
                        @click="form.is_active = !form.is_active"
                    >
                        <span
                            class="inline-block h-4 w-4 rounded-full bg-white shadow transition-transform"
                            :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"
                        />
                    </button>
                    <Label>{{ form.is_active ? 'Đang hoạt động' : 'Tạm ngưng' }}</Label>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 pt-2">
                    <Button variant="outline" class="flex-1" @click="closeModal">Hủy</Button>
                    <Button class="flex-1" :disabled="submitting" @click="submitForm">
                        {{ submitting ? 'Đang lưu...' : editId ? 'Cập nhật' : 'Thêm shipper' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
