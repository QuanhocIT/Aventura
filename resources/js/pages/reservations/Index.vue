<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    Users,
    Phone,
    CheckCircle2,
    XCircle,
    Clock,
    Utensils,
    AlertTriangle,
    Filter,
    UserCheck,
    Ban,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { confirmDialog } from '@/composables/useConfirm';

// ──────────────────────────────────────────────────────────────────────────────
// Props
// ──────────────────────────────────────────────────────────────────────────────
const props = defineProps<{
    reservations: any[];
    todayStats: Record<string, number>;
    availableTables: { id: number; name: string; capacity: number }[];
    filters: { date: string; status: string };
}>();

// ──────────────────────────────────────────────────────────────────────────────
// State
// ──────────────────────────────────────────────────────────────────────────────
const dateFilter   = ref(props.filters.date);
const statusFilter = ref(props.filters.status);
const selected     = ref<any | null>(null);
const showConfirmModal = ref(false);
const showCancelModal  = ref(false);

const confirmForm = useForm({ table_id: null as number | null, internal_notes: '' });
const cancelForm  = useForm({ reason: '' });

// ──────────────────────────────────────────────────────────────────────────────
// Computed
// ──────────────────────────────────────────────────────────────────────────────
const totalToday    = computed(() => Object.values(props.todayStats).reduce((a, b) => a + b, 0));
const pendingCount  = computed(() => props.todayStats['pending'] ?? 0);
const confirmedCount = computed(() => props.todayStats['confirmed'] ?? 0);
const seatedCount   = computed(() => props.todayStats['seated'] ?? 0);

const statusOptions = [
    { value: 'all',       label: 'Tất cả' },
    { value: 'pending',   label: 'Chờ xác nhận' },
    { value: 'confirmed', label: 'Đã xác nhận' },
    { value: 'seated',    label: 'Đang phục vụ' },
    { value: 'completed', label: 'Hoàn thành' },
    { value: 'cancelled', label: 'Đã hủy' },
    { value: 'no_show',   label: 'Không đến' },
];

const colorMap: Record<string, string> = {
    amber:   'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
    emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
    sky:     'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-300',
    teal:    'bg-teal-100 text-teal-800 dark:bg-teal-950/40 dark:text-teal-300',
    rose:    'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
    gray:    'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
};

// ──────────────────────────────────────────────────────────────────────────────
// Actions
// ──────────────────────────────────────────────────────────────────────────────
function applyFilter() {
    router.get('/reservations', {
        date:   dateFilter.value,
        status: statusFilter.value,
    }, { preserveState: true, replace: true });
}

function openConfirmModal(r: any) {
    selected.value = r;
    confirmForm.reset();
    showConfirmModal.value = true;
}

function submitConfirm() {
    confirmForm.post(`/reservations/${selected.value.id}/confirm`, {
        onSuccess: () => {
 showConfirmModal.value = false; 
},
    });
}

async function seat(r: any) {
    if (!(await confirmDialog({ title: 'Xác nhận thao tác', description: `Xác nhận dẫn khách ${r.guest_name} vào bàn?`, variant: 'default' }))) {
return;
}

    router.post(`/reservations/${r.id}/seat`);
}

function openCancelModal(r: any) {
    selected.value = r;
    cancelForm.reset();
    showCancelModal.value = true;
}

function submitCancel() {
    cancelForm.post(`/reservations/${selected.value.id}/cancel`, {
        onSuccess: () => {
 showCancelModal.value = false; 
},
    });
}

async function noShow(r: any) {
    if (!(await confirmDialog({ title: 'Xác nhận thao tác', description: `Đánh dấu khách ${r.guest_name} không đến?`, variant: 'default' }))) {
return;
}

    router.post(`/reservations/${r.id}/no-show`);
}
</script>

<template>
    <Head title="Đặt Bàn Trước · Aventura" />

    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8 space-y-6">
        <Heading
            title="Quản Lý Đặt Bàn"
            description="Theo dõi và xử lý các yêu cầu đặt bàn trước của khách hàng."
        />

        <!-- ── KPI Stats ──────────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-xl border border-border bg-background px-4 py-3">
                <p class="text-2xl font-bold">{{ totalToday }}</p>
                <p class="text-xs text-muted-foreground mt-0.5">Tổng hôm nay</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-950/20 px-4 py-3">
                <p class="text-2xl font-bold text-amber-700 dark:text-amber-400">{{ pendingCount }}</p>
                <p class="text-xs text-amber-600/80 mt-0.5">Chờ xác nhận</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-950/20 px-4 py-3">
                <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ confirmedCount }}</p>
                <p class="text-xs text-emerald-600/80 mt-0.5">Đã xác nhận</p>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50 dark:bg-sky-950/20 px-4 py-3">
                <p class="text-2xl font-bold text-sky-700 dark:text-sky-400">{{ seatedCount }}</p>
                <p class="text-xs text-sky-600/80 mt-0.5">Đang phục vụ</p>
            </div>
        </div>

        <!-- ── Filters ───────────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-background p-4">
            <Filter class="size-4 text-muted-foreground" />
            <input
                v-model="dateFilter"
                type="date"
                class="rounded-lg border border-border bg-background px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            />
            <select
                v-model="statusFilter"
                class="rounded-lg border border-border bg-background px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            >
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                </option>
            </select>
            <Button size="sm" @click="applyFilter">Lọc</Button>
        </div>

        <!-- ── Reservations Table ────────────────────────────────────────── -->
        <div class="rounded-xl border border-border bg-background overflow-hidden">
            <div class="px-4 py-3 border-b border-border flex items-center gap-2">
                <CalendarDays class="size-4 text-muted-foreground" />
                <span class="text-sm font-semibold">Danh sách đặt bàn — {{ dateFilter }}</span>
                <Badge variant="secondary" class="ml-auto">{{ reservations.length }} đặt bàn</Badge>
            </div>

            <div v-if="reservations.length === 0" class="py-16 text-center text-muted-foreground">
                <CalendarDays class="size-12 mx-auto opacity-20 mb-3" />
                <p class="text-sm">Không có đặt bàn nào trong ngày này.</p>
            </div>

            <div v-else class="divide-y divide-border">
                <div
                    v-for="r in reservations"
                    :key="r.id"
                    class="px-4 py-4 hover:bg-muted/30 transition-colors"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold">{{ r.guest_name }}</span>
                                <span
                                    class="text-[11px] font-medium px-2 py-0.5 rounded-full"
                                    :class="colorMap[r.status_color]"
                                >
                                    {{ r.status_label }}
                                </span>
                                <!-- Trạng thái cọc giữ bàn -->
                                <span
                                    v-if="r.deposit_status && r.deposit_status !== 'none'"
                                    class="text-[11px] font-bold px-2 py-0.5 rounded-full"
                                    :class="r.deposit_status === 'paid'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                        : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400'"
                                >
                                    💰 Cọc {{ Number(r.deposit_amount).toLocaleString('vi-VN') }}đ — {{ r.deposit_status === 'paid' ? 'đã trả' : 'chờ trả' }}
                                </span>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span class="flex items-center gap-1">
                                    <Phone class="size-3" /> {{ r.guest_phone }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <Clock class="size-3" /> {{ r.reservation_time }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <Users class="size-3" /> {{ r.party_size }} khách
                                </span>
                                <span v-if="r.table_name" class="flex items-center gap-1">
                                    <Utensils class="size-3" /> {{ r.table_name }}
                                </span>
                            </div>
                            <p v-if="r.special_requests" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                ✨ {{ r.special_requests }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 shrink-0">
                            <!-- Xác nhận -->
                            <Button
                                v-if="r.status === 'pending'"
                                size="sm"
                                variant="outline"
                                class="border-emerald-300 text-emerald-700 hover:bg-emerald-50"
                                @click="openConfirmModal(r)"
                            >
                                <CheckCircle2 class="size-3.5 mr-1" />
                                Xác nhận
                            </Button>

                            <!-- Dẫn vào bàn -->
                            <Button
                                v-if="r.status === 'confirmed'"
                                size="sm"
                                variant="outline"
                                class="border-sky-300 text-sky-700 hover:bg-sky-50"
                                @click="seat(r)"
                            >
                                <UserCheck class="size-3.5 mr-1" />
                                Vào bàn
                            </Button>

                            <!-- Không đến -->
                            <Button
                                v-if="r.status === 'confirmed'"
                                size="sm"
                                variant="outline"
                                class="border-gray-300 text-gray-600 hover:bg-gray-50"
                                @click="noShow(r)"
                            >
                                <AlertTriangle class="size-3.5 mr-1" />
                                Không đến
                            </Button>

                            <!-- Hủy -->
                            <Button
                                v-if="['pending', 'confirmed'].includes(r.status)"
                                size="sm"
                                variant="ghost"
                                class="text-rose-600 hover:bg-rose-50"
                                @click="openCancelModal(r)"
                            >
                                <Ban class="size-3.5 mr-1" />
                                Hủy
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Modal Xác Nhận ───────────────────────────────────────────────── -->
    <Teleport to="body">
        <div
            v-if="showConfirmModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            @click.self="showConfirmModal = false"
        >
            <div class="w-full max-w-md rounded-2xl border border-border bg-background p-6 shadow-2xl">
                <h3 class="text-lg font-bold mb-1">Xác nhận đặt bàn</h3>
                <p class="text-sm text-muted-foreground mb-4">Khách: <strong>{{ selected?.guest_name }}</strong> — {{ selected?.party_size }} người lúc {{ selected?.reservation_time }}</p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Gán bàn (tuỳ chọn)</label>
                        <select
                            v-model="confirmForm.table_id"
                            class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option :value="null">— Chưa gán bàn —</option>
                            <option v-for="t in availableTables" :key="t.id" :value="t.id">
                                {{ t.name }} ({{ t.capacity }} chỗ)
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Ghi chú nội bộ</label>
                        <textarea
                            v-model="confirmForm.internal_notes"
                            rows="2"
                            placeholder="Ghi chú cho nhân viên phục vụ..."
                            class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                        />
                    </div>
                </div>

                <div class="mt-5 flex gap-3">
                    <Button class="flex-1" @click="submitConfirm" :disabled="confirmForm.processing">
                        <CheckCircle2 class="size-4 mr-1.5" />
                        Xác nhận đặt bàn
                    </Button>
                    <Button variant="outline" @click="showConfirmModal = false">Đóng</Button>
                </div>
            </div>
        </div>

        <!-- Modal Hủy -->
        <div
            v-if="showCancelModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            @click.self="showCancelModal = false"
        >
            <div class="w-full max-w-md rounded-2xl border border-border bg-background p-6 shadow-2xl">
                <h3 class="text-lg font-bold mb-1 text-rose-600">Hủy đặt bàn</h3>
                <p class="text-sm text-muted-foreground mb-4">Khách: <strong>{{ selected?.guest_name }}</strong></p>

                <div>
                    <label class="block text-sm font-medium mb-1">Lý do hủy <span class="text-rose-500">*</span></label>
                    <textarea
                        v-model="cancelForm.reason"
                        rows="3"
                        placeholder="Nhập lý do hủy đặt bàn..."
                        class="w-full rounded-lg border border-rose-300 bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300 resize-none"
                    />
                    <p v-if="cancelForm.errors.reason" class="mt-1 text-xs text-rose-500">{{ cancelForm.errors.reason }}</p>
                </div>

                <div class="mt-5 flex gap-3">
                    <Button variant="destructive" class="flex-1" @click="submitCancel" :disabled="cancelForm.processing">
                        <XCircle class="size-4 mr-1.5" />
                        Xác nhận hủy
                    </Button>
                    <Button variant="outline" @click="showCancelModal = false">Đóng</Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
