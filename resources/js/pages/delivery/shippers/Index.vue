<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    Users, Plus, Pencil, Trash2, Truck, ArrowLeft,
    ToggleLeft, ToggleRight, Bike, Car, Package,
    CheckCircle2, XCircle, PhoneCall, ShieldCheck
} from 'lucide-vue-next';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppShell from '@/components/AppShell.vue';

interface Employee { id: number; name: string; phone: string | null }

interface Shipper {
    id: number; employee_id: number; name: string; phone: string | null;
    vehicle_type: string; vehicle_plate: string | null; is_active: boolean;
    max_orders_per_batch: number; max_capacity_kg: number;
}

const props = defineProps<{ shippers: Shipper[]; available_employees: Employee[] }>();

const showModal    = ref(false);
const editingShipper = ref<Shipper | null>(null);
const isSubmitting = ref(false);
const errors       = ref<Record<string, string>>({});
const deleteTarget = ref<Shipper | null>(null);
const isDeleting   = ref(false);

const form = ref({
    employee_id: null as number | null,
    vehicle_type: 'motorbike',
    vehicle_plate: '',
    max_orders_per_batch: 5,
    max_capacity_kg: 20,
});

// Stats
const activeCount   = computed(() => props.shippers.filter(s => s.is_active).length);
const inactiveCount = computed(() => props.shippers.filter(s => !s.is_active).length);

function initials(name: string) {
    return name.split(' ').map(w => w[0]).slice(-2).join('').toUpperCase();
}

function avatarColor(id: number) {
    const colors = [
        'bg-blue-500', 'bg-purple-500', 'bg-green-500',
        'bg-orange-500', 'bg-pink-500', 'bg-teal-500',
    ];
    return colors[id % colors.length];
}

function openCreate() {
    editingShipper.value = null;
    form.value = { employee_id: null, vehicle_type: 'motorbike', vehicle_plate: '', max_orders_per_batch: 5, max_capacity_kg: 20 };
    errors.value = {};
    showModal.value = true;
}

function openEdit(shipper: Shipper) {
    editingShipper.value = shipper;
    form.value = {
        employee_id: shipper.employee_id,
        vehicle_type: shipper.vehicle_type,
        vehicle_plate: shipper.vehicle_plate ?? '',
        max_orders_per_batch: shipper.max_orders_per_batch,
        max_capacity_kg: shipper.max_capacity_kg,
    };
    errors.value = {};
    showModal.value = true;
}

function getCsrf() {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

async function submitForm() {
    isSubmitting.value = true;
    errors.value = {};
    const url    = editingShipper.value ? `/delivery/shippers/${editingShipper.value.id}` : '/delivery/shippers';
    const method = editingShipper.value ? 'PATCH' : 'POST';
    try {
        const res  = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() }, body: JSON.stringify(form.value) });
        const data = await res.json();
        if (!res.ok) {
            errors.value = data.errors
                ? Object.fromEntries(Object.entries(data.errors).map(([k, v]) => [k, (v as string[])[0]]))
                : { general: data.message ?? 'Lỗi không xác định' };
            return;
        }
        showModal.value = false;
        router.reload({ only: ['shippers', 'available_employees'] });
    } finally { isSubmitting.value = false; }
}

async function confirmDelete() {
    if (!deleteTarget.value) return;
    isDeleting.value = true;
    const res  = await fetch(`/delivery/shippers/${deleteTarget.value.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': getCsrf() } });
    const data = await res.json();
    isDeleting.value = false;
    if (!res.ok) { alert(data.message); return; }
    deleteTarget.value = null;
    router.reload({ only: ['shippers', 'available_employees'] });
}

async function toggleActive(shipper: Shipper) {
    await fetch(`/delivery/shippers/${shipper.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        body: JSON.stringify({ is_active: !shipper.is_active }),
    });
    router.reload({ only: ['shippers'] });
}

const vehicleIcon: Record<string, any> = { bike: Bike, motorbike: Truck, car: Car };
const vehicleLabel: Record<string, string> = { bike: 'Xe đạp', motorbike: 'Xe máy', car: 'Ô tô' };
</script>

<template>
    <AppShell>
        <Head title="Quản lý Shipper" />

        <div class="flex flex-col gap-6 p-6 max-w-6xl mx-auto w-full">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" class="shrink-0" @click="router.get('/delivery')">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight flex items-center gap-2">
                            <Users class="h-6 w-6 text-primary" />
                            Quản lý Shipper
                        </h1>
                        <p class="text-sm text-muted-foreground mt-0.5">
                            Gán nhân viên làm shipper và cấu hình năng lực giao hàng
                        </p>
                    </div>
                </div>
                <Button @click="openCreate" :disabled="available_employees.length === 0" class="shrink-0 gap-2">
                    <Plus class="h-4 w-4" /> Thêm Shipper
                </Button>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <Card class="border-0 bg-muted/40">
                    <CardContent class="p-4 flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-primary/10">
                            <Users class="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ shippers.length }}</p>
                            <p class="text-xs text-muted-foreground">Tổng shipper</p>
                        </div>
                    </CardContent>
                </Card>
                <Card class="border-0 bg-muted/40">
                    <CardContent class="p-4 flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-green-500/10">
                            <CheckCircle2 class="h-5 w-5 text-green-500" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-green-600">{{ activeCount }}</p>
                            <p class="text-xs text-muted-foreground">Đang hoạt động</p>
                        </div>
                    </CardContent>
                </Card>
                <Card class="border-0 bg-muted/40">
                    <CardContent class="p-4 flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-muted">
                            <XCircle class="h-5 w-5 text-muted-foreground" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-muted-foreground">{{ inactiveCount }}</p>
                            <p class="text-xs text-muted-foreground">Tạm nghỉ</p>
                        </div>
                    </CardContent>
                </Card>
                <Card class="border-0 bg-muted/40">
                    <CardContent class="p-4 flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-blue-500/10">
                            <Package class="h-5 w-5 text-blue-500" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold">{{ available_employees.length }}</p>
                            <p class="text-xs text-muted-foreground">NV chưa gán</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty state -->
            <div v-if="shippers.length === 0"
                class="flex flex-col items-center justify-center py-20 text-center border border-dashed rounded-xl">
                <div class="p-4 rounded-full bg-muted mb-4">
                    <Truck class="h-10 w-10 text-muted-foreground" />
                </div>
                <h3 class="font-semibold text-lg mb-1">Chưa có shipper nào</h3>
                <p class="text-sm text-muted-foreground mb-4 max-w-xs">
                    Thêm nhân viên làm shipper để bắt đầu điều phối giao hàng
                </p>
                <Button @click="openCreate" :disabled="available_employees.length === 0">
                    <Plus class="h-4 w-4 mr-1" /> Thêm Shipper đầu tiên
                </Button>
                <p v-if="available_employees.length === 0" class="text-xs text-muted-foreground mt-2">
                    (Cần có nhân viên trong hệ thống trước)
                </p>
            </div>

            <!-- Shipper cards grid -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                <Card
                    v-for="shipper in shippers"
                    :key="shipper.id"
                    class="relative overflow-hidden transition-shadow hover:shadow-md"
                    :class="!shipper.is_active ? 'opacity-60' : ''"
                >
                    <!-- Active indicator strip -->
                    <div
                        class="absolute top-0 left-0 right-0 h-0.5"
                        :class="shipper.is_active ? 'bg-green-500' : 'bg-muted'"
                    />

                    <CardContent class="p-5">
                        <!-- Top row: avatar + name + toggle -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <!-- Avatar -->
                                <div
                                    class="w-11 h-11 rounded-full flex items-center justify-center text-white font-semibold text-sm shrink-0"
                                    :class="avatarColor(shipper.id)"
                                >
                                    {{ initials(shipper.name) }}
                                </div>
                                <div>
                                    <p class="font-semibold leading-tight">{{ shipper.name }}</p>
                                    <p class="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
                                        <PhoneCall class="h-3 w-3" />
                                        {{ shipper.phone ?? 'Chưa có SĐT' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Active toggle -->
                            <button
                                @click="toggleActive(shipper)"
                                class="flex items-center gap-1 text-xs shrink-0 mt-0.5 hover:opacity-80 transition-opacity"
                                :title="shipper.is_active ? 'Nhấn để tắt' : 'Nhấn để bật'"
                            >
                                <ToggleRight v-if="shipper.is_active" class="h-6 w-6 text-green-500" />
                                <ToggleLeft v-else class="h-6 w-6 text-muted-foreground" />
                            </button>
                        </div>

                        <!-- Divider -->
                        <div class="border-t my-4" />

                        <!-- Info grid -->
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <!-- Vehicle -->
                            <div class="flex items-center gap-2">
                                <component :is="vehicleIcon[shipper.vehicle_type]" class="h-4 w-4 text-muted-foreground shrink-0" />
                                <div>
                                    <p class="text-xs text-muted-foreground">Phương tiện</p>
                                    <p class="font-medium">{{ vehicleLabel[shipper.vehicle_type] }}</p>
                                </div>
                            </div>

                            <!-- Plate -->
                            <div class="flex items-center gap-2">
                                <ShieldCheck class="h-4 w-4 text-muted-foreground shrink-0" />
                                <div>
                                    <p class="text-xs text-muted-foreground">Biển số</p>
                                    <p class="font-medium font-mono">{{ shipper.vehicle_plate ?? '—' }}</p>
                                </div>
                            </div>

                            <!-- Max orders -->
                            <div class="flex items-center gap-2">
                                <Package class="h-4 w-4 text-muted-foreground shrink-0" />
                                <div>
                                    <p class="text-xs text-muted-foreground">Đơn tối đa</p>
                                    <p class="font-medium">{{ shipper.max_orders_per_batch }} đơn/batch</p>
                                </div>
                            </div>

                            <!-- Capacity -->
                            <div class="flex items-center gap-2">
                                <Truck class="h-4 w-4 text-muted-foreground shrink-0" />
                                <div>
                                    <p class="text-xs text-muted-foreground">Tải tối đa</p>
                                    <p class="font-medium">{{ shipper.max_capacity_kg }} kg</p>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom: status badge + actions -->
                        <div class="flex items-center justify-between mt-4 pt-3 border-t">
                            <Badge
                                :class="shipper.is_active
                                    ? 'bg-green-500/10 text-green-600 border-green-500/20'
                                    : 'bg-muted text-muted-foreground border-border'"
                                variant="outline"
                                class="text-xs font-medium"
                            >
                                {{ shipper.is_active ? '● Đang hoạt động' : '○ Tạm nghỉ' }}
                            </Badge>

                            <div class="flex items-center gap-1">
                                <Button
                                    variant="ghost" size="icon"
                                    class="h-8 w-8 hover:bg-primary/10 hover:text-primary"
                                    @click="openEdit(shipper)"
                                    title="Chỉnh sửa"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                    variant="ghost" size="icon"
                                    class="h-8 w-8 hover:bg-destructive/10 hover:text-destructive"
                                    @click="deleteTarget = shipper"
                                    title="Xóa shipper"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Add / Edit Modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                    <div class="bg-background rounded-2xl shadow-2xl w-full max-w-md border">
                        <!-- Modal header -->
                        <div class="flex items-center justify-between p-6 pb-4 border-b">
                            <div class="flex items-center gap-2">
                                <div class="p-2 rounded-lg bg-primary/10">
                                    <Truck class="h-4 w-4 text-primary" />
                                </div>
                                <h2 class="font-semibold text-base">
                                    {{ editingShipper ? 'Chỉnh sửa Shipper' : 'Thêm Shipper mới' }}
                                </h2>
                            </div>
                            <button @click="showModal = false" class="text-muted-foreground hover:text-foreground transition-colors text-lg leading-none">✕</button>
                        </div>

                        <!-- Modal body -->
                        <div class="p-6 space-y-4">
                            <div v-if="errors.general" class="p-3 rounded-lg bg-destructive/10 text-destructive text-sm flex items-center gap-2">
                                <XCircle class="h-4 w-4 shrink-0" /> {{ errors.general }}
                            </div>

                            <!-- Employee select (only on create) -->
                            <div v-if="!editingShipper" class="space-y-1.5">
                                <label class="text-sm font-medium">Nhân viên <span class="text-destructive">*</span></label>
                                <select
                                    v-model="form.employee_id"
                                    class="w-full border rounded-lg px-3 py-2.5 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary/30"
                                    :class="errors.employee_id ? 'border-destructive' : ''"
                                >
                                    <option :value="null" disabled>— Chọn nhân viên —</option>
                                    <option v-for="e in available_employees" :key="e.id" :value="e.id">
                                        {{ e.name }}{{ e.phone ? ` · ${e.phone}` : '' }}
                                    </option>
                                </select>
                                <p v-if="errors.employee_id" class="text-xs text-destructive">{{ errors.employee_id }}</p>
                            </div>

                            <!-- Vehicle type -->
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium">Phương tiện <span class="text-destructive">*</span></label>
                                <div class="grid grid-cols-3 gap-2">
                                    <label
                                        v-for="v in [{val:'bike',icon:'🚲',label:'Xe đạp'},{val:'motorbike',icon:'🛵',label:'Xe máy'},{val:'car',icon:'🚗',label:'Ô tô'}]"
                                        :key="v.val"
                                        class="flex flex-col items-center gap-1 p-3 border rounded-lg cursor-pointer text-sm text-center transition-all hover:border-primary/50"
                                        :class="form.vehicle_type === v.val ? 'border-primary bg-primary/5 font-medium' : ''"
                                    >
                                        <input type="radio" :value="v.val" v-model="form.vehicle_type" class="sr-only" />
                                        <span class="text-xl">{{ v.icon }}</span>
                                        <span class="text-xs">{{ v.label }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Plate -->
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium">Biển số xe</label>
                                <input
                                    v-model="form.vehicle_plate"
                                    type="text"
                                    placeholder="VD: 51F1-12345"
                                    class="w-full border rounded-lg px-3 py-2.5 text-sm bg-background font-mono focus:outline-none focus:ring-2 focus:ring-primary/30"
                                />
                            </div>

                            <!-- Capacity row -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1.5">
                                    <label class="text-sm font-medium">Đơn tối đa / batch</label>
                                    <div class="relative">
                                        <input
                                            v-model.number="form.max_orders_per_batch"
                                            type="number" min="1" max="20"
                                            class="w-full border rounded-lg px-3 py-2.5 pr-12 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary/30"
                                        />
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">đơn</span>
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-sm font-medium">Tải tối đa</label>
                                    <div class="relative">
                                        <input
                                            v-model.number="form.max_capacity_kg"
                                            type="number" min="1"
                                            class="w-full border rounded-lg px-3 py-2.5 pr-8 text-sm bg-background focus:outline-none focus:ring-2 focus:ring-primary/30"
                                        />
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">kg</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal footer -->
                        <div class="flex gap-2 p-6 pt-0">
                            <Button variant="outline" class="flex-1" @click="showModal = false">Hủy</Button>
                            <Button class="flex-1" :disabled="isSubmitting" @click="submitForm">
                                <span v-if="isSubmitting" class="flex items-center gap-2">
                                    <span class="h-3 w-3 rounded-full border-2 border-white border-t-transparent animate-spin" />
                                    Đang lưu...
                                </span>
                                <span v-else>{{ editingShipper ? 'Lưu thay đổi' : 'Thêm Shipper' }}</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Delete confirm modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                    <div class="bg-background rounded-2xl shadow-2xl w-full max-w-sm border p-6 text-center">
                        <div class="mx-auto w-12 h-12 rounded-full bg-destructive/10 flex items-center justify-center mb-4">
                            <Trash2 class="h-5 w-5 text-destructive" />
                        </div>
                        <h3 class="font-semibold text-base mb-1">Xóa shipper?</h3>
                        <p class="text-sm text-muted-foreground mb-6">
                            Bạn sắp xóa shipper <span class="font-medium text-foreground">{{ deleteTarget.name }}</span>.
                            Hành động này không thể hoàn tác.
                        </p>
                        <div class="flex gap-2">
                            <Button variant="outline" class="flex-1" @click="deleteTarget = null">Giữ lại</Button>
                            <Button variant="destructive" class="flex-1" :disabled="isDeleting" @click="confirmDelete">
                                {{ isDeleting ? 'Đang xóa...' : 'Xác nhận xóa' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppShell>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
