<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Ban,
    CalendarCheck,
    CalendarDays,
    Check,
    CheckCircle2,
    Clock,
    Copy,
    FileText,
    Filter,
    Info,
    Loader2,
    Mail,
    MapPin,
    Phone,
    Plus,
    RefreshCw,
    Search,
    Table2,
    UserCheck,
    Users,
    Utensils,
    WandSparkles,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { confirmDialog } from '@/composables/useConfirm';

type ReservationStatus =
    | 'pending'
    | 'confirmed'
    | 'seated'
    | 'completed'
    | 'cancelled'
    | 'no_show';

type Reservation = {
    id: number;
    guest_name: string;
    guest_phone: string;
    guest_email?: string | null;
    reservation_date: string;
    reservation_time: string;
    party_size: number;
    status: ReservationStatus;
    status_label: string;
    status_color: string;
    special_requests?: string | null;
    internal_notes?: string | null;
    table_name?: string | null;
    branch_id?: number | null;
    branch_name?: string | null;
    confirmed_by_name?: string | null;
    source?: string | null;
    deposit_amount?: number;
    deposit_status?: string;
};

type Table = {
    id: number;
    name: string;
    capacity: number;
    branch_id: number | null;
    status: 'available' | 'reserved' | 'occupied' | 'inactive';
};

const props = defineProps<{
    reservations: Reservation[];
    todayStats: Record<string, number | string>;
    tableStats: Record<string, number | string>;
    availableTables: Table[];
    tables: Table[];
    branches: { id: number; name: string }[];
    filters: {
        date: string;
        status: string;
        search: string;
        branch_id: number | null;
    };
}>();

const dateFilter = ref(props.filters.date);
const statusFilter = ref(props.filters.status);
const searchFilter = ref(props.filters.search ?? '');
const selected = ref<Reservation | null>(props.reservations[0] ?? null);
const showConfirmModal = ref(false);
const showCancelModal = ref(false);
const showCreateModal = ref(false);
const isProcessing = ref(false);

watch(
    () => props.reservations,
    (reservations) => {
        const current = selected.value;
        const refreshed = current
            ? reservations.find((reservation) => reservation.id === current.id)
            : null;

        selected.value = refreshed ?? reservations[0] ?? null;
    },
);

const confirmForm = useForm({
    table_id: null as number | null,
    internal_notes: '',
});
const cancelForm = useForm({ reason: '' });
const createForm = useForm({
    branch_id: props.filters.branch_id ?? props.branches[0]?.id ?? null,
    guest_name: '',
    guest_phone: '',
    guest_email: '',
    reservation_date: props.filters.date,
    reservation_time: '',
    party_size: 2,
    special_requests: '',
    internal_notes: 'Khách gọi điện đặt bàn.',
});

const statusOptions = [
    { value: 'all', label: 'Tất cả' },
    { value: 'pending', label: 'Chờ xác nhận' },
    { value: 'confirmed', label: 'Đã xác nhận' },
    { value: 'seated', label: 'Đang phục vụ' },
    { value: 'completed', label: 'Hoàn thành' },
    { value: 'cancelled', label: 'Đã hủy' },
    { value: 'no_show', label: 'Không đến' },
];

const statusStyles: Record<string, string> = {
    pending: 'border-amber-400/40 bg-amber-500/10 text-amber-300',
    confirmed: 'border-emerald-400/40 bg-emerald-500/10 text-emerald-300',
    seated: 'border-sky-400/40 bg-sky-500/10 text-sky-300',
    completed: 'border-teal-400/40 bg-teal-500/10 text-teal-300',
    cancelled: 'border-rose-400/40 bg-rose-500/10 text-rose-300',
    no_show: 'border-slate-400/40 bg-slate-500/10 text-slate-300',
};

const sourceLabels: Record<string, string> = {
    phone: 'Điện thoại',
    qr: 'Mã QR',
    website: 'Website',
    walk_in: 'Tại quầy',
};

const totalForDate = computed(() =>
    Object.values(props.todayStats).reduce<number>(
        (sum, value) => sum + Number(value),
        0,
    ),
);
const pendingCount = computed(() => Number(props.todayStats.pending ?? 0));
const confirmedCount = computed(() => Number(props.todayStats.confirmed ?? 0));
const seatedCount = computed(() => Number(props.todayStats.seated ?? 0));
const noShowCount = computed(() => Number(props.todayStats.no_show ?? 0));
const availableTableCount = computed(() =>
    Number(props.tableStats.available ?? 0),
);
const reservedTableCount = computed(() =>
    Number(props.tableStats.reserved ?? 0),
);
const occupiedTableCount = computed(() =>
    Number(props.tableStats.occupied ?? 0),
);
const availableTablesForSelectedReservation = computed(() => {
    return tablesForSelectedReservation.value.filter(
        (table) =>
            table.status === 'available' &&
            (!selected.value || table.capacity >= selected.value.party_size),
    );
});
const tablesForSelectedReservation = computed(() => {
    if (!selected.value?.branch_id) {
        return props.tables;
    }

    return props.tables.filter(
        (table) => table.branch_id === selected.value?.branch_id,
    );
});
const tableStatusLabels: Record<Table['status'], string> = {
    available: 'Trống',
    reserved: 'Đã giữ',
    occupied: 'Có khách',
    inactive: 'Ngưng dùng',
};
const tableStatusStyles: Record<Table['status'], string> = {
    available:
        'border-emerald-500/30 bg-emerald-500/[0.06] hover:border-indigo-500/60 hover:bg-indigo-500/[0.08]',
    reserved: 'border-amber-500/30 bg-amber-500/[0.06]',
    occupied: 'border-sky-500/30 bg-sky-500/[0.06]',
    inactive: 'border-border bg-muted/30 opacity-60',
};
const formattedDate = computed(() => {
    const date = new Date(`${dateFilter.value}T12:00:00`);

    return new Intl.DateTimeFormat('vi-VN', {
        weekday: 'long',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
});
const isToday = computed(
    () => dateFilter.value === new Date().toISOString().slice(0, 10),
);

function formatTime(time: string): string {
    return time?.slice(0, 5) ?? '--:--';
}

function sourceLabel(source?: string | null): string {
    return sourceLabels[source ?? ''] ?? 'Khác';
}

function applyFilter() {
    router.get(
        '/reservations',
        {
            date: dateFilter.value,
            status: statusFilter.value,
            search: searchFilter.value.trim() || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

function setStatus(status: string) {
    statusFilter.value = status;
    applyFilter();
}

function shiftDate(days: number) {
    const date = new Date(`${dateFilter.value}T12:00:00`);
    date.setDate(date.getDate() + days);
    dateFilter.value = date.toISOString().slice(0, 10);
    applyFilter();
}

function selectReservation(reservation: Reservation) {
    selected.value = reservation;
}

function refresh() {
    router.reload({
        only: [
            'reservations',
            'todayStats',
            'tableStats',
            'availableTables',
            'tables',
        ],
    });
}

function openCreateModal() {
    createForm.reset();
    createForm.branch_id =
        props.filters.branch_id ?? props.branches[0]?.id ?? null;
    createForm.reservation_date = dateFilter.value;
    createForm.internal_notes = 'Khách gọi điện đặt bàn.';
    showCreateModal.value = true;
}

function closeNativeDatePicker(event: Event) {
    const target = event.target;

    // Keep the native calendar open while the date input itself is being used.
    if (target instanceof Element && target.closest('input[type="date"]')) {
        return;
    }

    const activeElement = document.activeElement;

    if (
        activeElement instanceof HTMLInputElement &&
        activeElement.type === 'date'
    ) {
        activeElement.blur();
    }
}

function submitCreate() {
    createForm.post('/reservations', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            dateFilter.value = createForm.reservation_date;
            statusFilter.value = 'all';
        },
    });
}

function openConfirmModal(reservation: Reservation) {
    selected.value = reservation;
    confirmForm.reset();
    showConfirmModal.value = true;
}

function openConfirmWithTable(reservation: Reservation, table: Table) {
    if (
        reservation.status !== 'pending' ||
        table.status !== 'available' ||
        table.capacity < reservation.party_size
    ) {
        return;
    }

    openConfirmModal(reservation);
    confirmForm.table_id = table.id;
}

function submitConfirm() {
    if (!selected.value) {
        return;
    }

    confirmForm.post(`/reservations/${selected.value.id}/confirm`, {
        preserveScroll: true,
        onSuccess: () => {
            showConfirmModal.value = false;
        },
    });
}

function autoAssign(reservation: Reservation | null = selected.value) {
    if (!reservation || isProcessing.value) {
        return;
    }

    isProcessing.value = true;
    router.post(
        `/reservations/${reservation.id}/auto-assign`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showConfirmModal.value = false;
            },
            onFinish: () => {
                isProcessing.value = false;
            },
        },
    );
}

async function seat(reservation: Reservation) {
    if (isProcessing.value) {
        return;
    }

    const accepted = await confirmDialog({
        title: 'Dẫn khách vào bàn?',
        description: `Xác nhận khách ${reservation.guest_name} đã đến nhà hàng?`,
        variant: 'default',
    });

    if (!accepted) {
        return;
    }

    isProcessing.value = true;
    router.post(
        `/reservations/${reservation.id}/seat`,
        {},
        { preserveScroll: true, onFinish: () => (isProcessing.value = false) },
    );
}

async function complete(reservation: Reservation) {
    if (isProcessing.value) {
        return;
    }

    const accepted = await confirmDialog({
        title: 'Hoàn tất lượt khách?',
        description: `Bàn của khách ${reservation.guest_name} sẽ được trả về trạng thái trống.`,
        variant: 'default',
    });

    if (!accepted) {
        return;
    }

    isProcessing.value = true;
    router.post(
        `/reservations/${reservation.id}/complete`,
        {},
        { preserveScroll: true, onFinish: () => (isProcessing.value = false) },
    );
}

function openCancelModal(reservation: Reservation) {
    selected.value = reservation;
    cancelForm.reset();
    showCancelModal.value = true;
}

function submitCancel() {
    if (!selected.value) {
        return;
    }

    cancelForm.post(`/reservations/${selected.value.id}/cancel`, {
        preserveScroll: true,
        onSuccess: () => {
            showCancelModal.value = false;
        },
    });
}

async function noShow(reservation: Reservation) {
    if (isProcessing.value) {
        return;
    }

    const accepted = await confirmDialog({
        title: 'Đánh dấu khách không đến?',
        description: `Đặt bàn của ${reservation.guest_name} sẽ chuyển sang không đến.`,
        variant: 'default',
    });

    if (!accepted) {
        return;
    }

    isProcessing.value = true;
    router.post(
        `/reservations/${reservation.id}/no-show`,
        {},
        { preserveScroll: true, onFinish: () => (isProcessing.value = false) },
    );
}

async function copyPhone(phone: string) {
    try {
        await navigator.clipboard.writeText(phone);
        toast.success('Đã sao chép số điện thoại');
    } catch {
        toast.error('Không thể sao chép số điện thoại');
    }
}
</script>

<template>
    <Head title="Quản lý đặt bàn · Aventura" />

    <div class="min-h-full w-full">
        <div class="mx-auto w-full max-w-7xl p-4 lg:p-6">
            <div
                class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_380px]"
            >
                <div class="min-w-0 space-y-6">
                    <section class="border-b border-border pb-5">
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex items-start gap-4">
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-500"
                                >
                                    <CalendarCheck class="size-6" />
                                </div>
                                <div>
                                    <div
                                        class="mb-1 flex flex-wrap items-center gap-2"
                                    >
                                        <h1
                                            class="text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                                        >
                                            Quản lý đặt bàn
                                        </h1>
                                        <Badge
                                            class="border-indigo-200 bg-transparent text-indigo-700 dark:border-indigo-800 dark:text-indigo-400"
                                            >Điều hành nhà hàng</Badge
                                        >
                                    </div>
                                    <p
                                        class="max-w-2xl text-sm text-muted-foreground"
                                    >
                                        Tiếp nhận khách gọi điện, xác nhận bàn
                                        và theo dõi toàn bộ lượt khách trong
                                        ngày.
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <Button
                                    variant="outline"
                                    class="h-9 border-border text-muted-foreground hover:bg-muted hover:text-foreground"
                                    @click="refresh"
                                    ><RefreshCw class="mr-2 size-4" />Làm
                                    mới</Button
                                ><Button
                                    variant="outline"
                                    class="h-9 border-indigo-200 text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-400 dark:hover:bg-indigo-950/30"
                                    @click="openCreateModal"
                                    ><Plus class="mr-2 size-4" />Ghi nhận đặt
                                    bàn</Button
                                >
                            </div>
                        </div>
                        <div
                            class="mt-5 grid gap-3 border-t border-border pt-4 text-sm text-muted-foreground sm:grid-cols-3"
                        >
                            <div class="flex items-center gap-2">
                                <CalendarDays
                                    class="size-4 text-indigo-500"
                                /><span class="capitalize">{{
                                    formattedDate
                                }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <MapPin
                                    class="size-4 text-emerald-500"
                                /><span>{{
                                    filters.branch_id
                                        ? 'Đang xem chi nhánh được chọn'
                                        : 'Đang xem toàn bộ chi nhánh'
                                }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Table2 class="size-4 text-amber-500" /><span
                                    >{{ availableTableCount }} bàn đang trống ·
                                    {{ reservedTableCount }} bàn đã giữ</span
                                >
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <div
                            class="rounded-xl border border-border bg-background p-4"
                        >
                            <div
                                class="mb-3 flex items-center justify-between text-muted-foreground"
                            >
                                <span
                                    class="text-xs font-semibold tracking-wider uppercase"
                                    >Lịch trong ngày</span
                                ><CalendarDays class="size-4 text-indigo-500" />
                            </div>
                            <p class="text-3xl font-bold text-foreground">
                                {{ totalForDate }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ isToday ? 'Hôm nay' : 'Ngày đang xem' }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border bg-background p-4"
                        >
                            <div
                                class="mb-3 flex items-center justify-between text-amber-500"
                            >
                                <span
                                    class="text-xs font-semibold tracking-wider uppercase"
                                    >Chờ xác nhận</span
                                ><Clock class="size-4" />
                            </div>
                            <p class="text-3xl font-bold text-foreground">
                                {{ pendingCount }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Cần xử lý sớm
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border bg-background p-4"
                        >
                            <div
                                class="mb-3 flex items-center justify-between text-emerald-500"
                            >
                                <span
                                    class="text-xs font-semibold tracking-wider uppercase"
                                    >Đã xác nhận</span
                                ><CheckCircle2 class="size-4" />
                            </div>
                            <p class="text-3xl font-bold text-foreground">
                                {{ confirmedCount }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Đã giữ lịch
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border bg-background p-4"
                        >
                            <div
                                class="mb-3 flex items-center justify-between text-sky-500"
                            >
                                <span
                                    class="text-xs font-semibold tracking-wider uppercase"
                                    >Đang phục vụ</span
                                ><Users class="size-4" />
                            </div>
                            <p class="text-3xl font-bold text-foreground">
                                {{ seatedCount }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Bàn đang có khách
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border bg-background p-4 sm:col-span-2 xl:col-span-1"
                        >
                            <div
                                class="mb-3 flex items-center justify-between text-muted-foreground"
                            >
                                <span
                                    class="text-xs font-semibold tracking-wider uppercase"
                                    >Không đến</span
                                ><AlertTriangle class="size-4 text-rose-300" />
                            </div>
                            <p class="text-3xl font-bold text-foreground">
                                {{ noShowCount }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ occupiedTableCount }} bàn đang phục vụ
                            </p>
                        </div>
                    </section>

                    <section
                        class="rounded-xl border border-border bg-background p-4 sm:p-5"
                    >
                        <div
                            class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2
                                        class="text-lg font-semibold text-foreground"
                                    >
                                        Lịch đặt bàn
                                    </h2>
                                    <span
                                        class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                                        >{{ reservations.length }} kết quả</span
                                    >
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Chọn một lượt đặt bàn để xem đầy đủ thông
                                    tin và thao tác.
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground"
                                    @click="shiftDate(-1)"
                                    ><ArrowLeft
                                        class="mr-1.5 size-3.5"
                                    />Trước</Button
                                ><Button
                                    v-if="!isToday"
                                    variant="outline"
                                    size="sm"
                                    class="border-indigo-200 bg-transparent text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-400 dark:hover:bg-indigo-950/30"
                                    @click="
                                        dateFilter = new Date()
                                            .toISOString()
                                            .slice(0, 10);
                                        applyFilter();
                                    "
                                    >Hôm nay</Button
                                ><input
                                    v-model="dateFilter"
                                    type="date"
                                    class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground ring-indigo-400/30 outline-none focus:ring-2"
                                    @change="applyFilter"
                                /><Button
                                    variant="outline"
                                    size="sm"
                                    class="border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground"
                                    @click="shiftDate(1)"
                                    >Sau<ArrowRight class="ml-1.5 size-3.5"
                                /></Button>
                            </div>
                        </div>
                        <div
                            class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center"
                        >
                            <div class="relative min-w-0 flex-1">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                /><input
                                    v-model="searchFilter"
                                    type="search"
                                    placeholder="Tìm theo tên, số điện thoại hoặc email..."
                                    class="h-10 w-full rounded-lg border border-border bg-background pr-3 pl-10 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/20"
                                    @keyup.enter="applyFilter"
                                />
                            </div>
                            <Button
                                class="h-10 bg-indigo-600 px-5 hover:bg-indigo-500"
                                @click="applyFilter"
                                ><Filter class="mr-2 size-4" />Lọc dữ
                                liệu</Button
                            >
                        </div>
                        <div class="mt-4 flex gap-2 overflow-x-auto pb-1">
                            <button
                                v-for="option in statusOptions"
                                :key="option.value"
                                type="button"
                                class="shrink-0 rounded-lg border px-3 py-2 text-xs font-semibold transition-colors"
                                :class="
                                    statusFilter === option.value
                                        ? 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950/30 dark:text-indigo-400'
                                        : 'border-border bg-background text-muted-foreground hover:border-indigo-200 hover:text-foreground dark:hover:border-indigo-800'
                                "
                                @click="setStatus(option.value)"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </section>

                    <section
                        class="overflow-hidden rounded-xl border border-border bg-background"
                    >
                        <div
                            class="flex flex-col gap-3 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="rounded-lg bg-indigo-500/10 p-2 text-indigo-500"
                                >
                                    <CalendarDays class="size-4" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-foreground">
                                        {{ formattedDate }}
                                    </h3>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            reservations.length
                                                ? 'Các lượt khách theo giờ đến'
                                                : 'Chưa có lịch trong ngày này'
                                        }}
                                    </p>
                                </div>
                            </div>
                            <span class="text-xs text-muted-foreground"
                                >Cập nhật theo thời gian thực khi thao tác</span
                            >
                        </div>
                        <div
                            v-if="reservations.length === 0"
                            class="flex min-h-[260px] flex-col items-center justify-center px-6 py-12 text-center"
                        >
                            <div
                                class="mb-3 rounded-lg bg-muted p-3 text-muted-foreground"
                            >
                                <CalendarDays class="size-8" />
                            </div>
                            <h3 class="font-semibold text-foreground">
                                Không có đặt bàn phù hợp
                            </h3>
                            <p
                                class="mt-1 max-w-md text-sm text-muted-foreground"
                            >
                                Thử đổi ngày, trạng thái hoặc từ khóa tìm kiếm.
                                Bạn cũng có thể ghi nhận một cuộc gọi mới.
                            </p>
                            <Button
                                size="sm"
                                class="mt-4 bg-indigo-600 hover:bg-indigo-500"
                                @click="openCreateModal"
                                ><Plus class="mr-2 size-4" />Ghi nhận đặt
                                bàn</Button
                            >
                        </div>
                        <div v-else class="divide-y divide-border">
                            <article
                                v-for="reservation in reservations"
                                :key="reservation.id"
                                class="group cursor-pointer border-l-2 border-transparent p-4 transition-colors hover:bg-muted/40 sm:p-5"
                                :class="
                                    selected?.id === reservation.id
                                        ? 'border-indigo-500 bg-indigo-500/[0.04]'
                                        : ''
                                "
                                @click="selectReservation(reservation)"
                            >
                                <div
                                    class="flex flex-col gap-4 lg:flex-row lg:items-center"
                                >
                                    <div
                                        class="flex shrink-0 items-center gap-3 lg:w-28 lg:flex-col lg:items-start lg:gap-1"
                                    >
                                        <div
                                            class="text-2xl font-bold tracking-tight text-foreground"
                                        >
                                            {{
                                                formatTime(
                                                    reservation.reservation_time,
                                                )
                                            }}
                                        </div>
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >{{
                                                reservation.party_size
                                            }}
                                            khách</span
                                        >
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <h4
                                                class="font-semibold text-foreground"
                                            >
                                                {{ reservation.guest_name }}
                                            </h4>
                                            <span
                                                class="rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                                :class="
                                                    statusStyles[
                                                        reservation.status
                                                    ]
                                                "
                                                >{{
                                                    reservation.status_label
                                                }}</span
                                            ><span
                                                class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground"
                                                >{{
                                                    sourceLabel(
                                                        reservation.source,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1.5"
                                                ><Phone
                                                    class="size-3.5 text-indigo-500"
                                                />{{
                                                    reservation.guest_phone
                                                }}</span
                                            ><span
                                                v-if="reservation.branch_name"
                                                class="inline-flex items-center gap-1.5"
                                                ><MapPin
                                                    class="size-3.5 text-emerald-500"
                                                />{{
                                                    reservation.branch_name
                                                }}</span
                                            ><span
                                                v-if="reservation.table_name"
                                                class="inline-flex items-center gap-1.5"
                                                ><Utensils
                                                    class="size-3.5 text-amber-500"
                                                />{{
                                                    reservation.table_name
                                                }}</span
                                            >
                                        </div>
                                        <p
                                            v-if="reservation.special_requests"
                                            class="mt-2 line-clamp-2 text-xs text-amber-200/80"
                                        >
                                            <span class="mr-1">✦</span
                                            >{{ reservation.special_requests }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex flex-wrap items-center gap-2 lg:justify-end"
                                    >
                                        <Button
                                            v-if="
                                                reservation.status === 'pending'
                                            "
                                            size="sm"
                                            class="border border-emerald-600/40 bg-transparent text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/20"
                                            @click.stop="
                                                openConfirmModal(reservation)
                                            "
                                            ><Check
                                                class="mr-1.5 size-3.5"
                                            />Xác nhận</Button
                                        ><Button
                                            v-if="
                                                reservation.status ===
                                                'confirmed'
                                            "
                                            size="sm"
                                            variant="outline"
                                            class="border-sky-600/40 text-sky-700 hover:bg-sky-50 dark:text-sky-400 dark:hover:bg-sky-950/20"
                                            @click.stop="seat(reservation)"
                                            ><UserCheck
                                                class="mr-1.5 size-3.5"
                                            />Vào bàn</Button
                                        ><Button
                                            v-if="
                                                ['pending', 'confirmed'].includes(
                                                    reservation.status,
                                                )
                                            "
                                            size="sm"
                                            variant="outline"
                                            class="border-rose-500/30 text-rose-600 hover:border-rose-500/60 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/20"
                                            @click.stop="
                                                openCancelModal(reservation)
                                            "
                                            ><Ban
                                                class="mr-1.5 size-3.5"
                                            />Hủy</Button
                                        ><Button
                                            v-if="
                                                reservation.status === 'seated'
                                            "
                                            size="sm"
                                            class="border border-teal-600/40 bg-transparent text-teal-700 hover:bg-teal-50 dark:text-teal-400 dark:hover:bg-teal-950/20"
                                            @click.stop="complete(reservation)"
                                            ><CheckCircle2
                                                class="mr-1.5 size-3.5"
                                            />Hoàn tất</Button
                                        ><button
                                            type="button"
                                            class="rounded-lg border border-slate-700 p-2 text-slate-400 transition-colors hover:border-indigo-400/50 hover:text-indigo-200"
                                            aria-label="Chọn lượt đặt để xem chi tiết và gán bàn"
                                            @click.stop="
                                                selectReservation(reservation)
                                            "
                                        >
                                            <ArrowRight class="size-4" />
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 xl:sticky xl:top-4">
                    <section
                        v-if="selected"
                        class="overflow-hidden rounded-xl border border-border bg-background shadow-none"
                    >
                        <div
                            class="flex items-start justify-between border-b border-border p-5"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold tracking-wider text-indigo-500 uppercase"
                                >
                                    Chi tiết đặt bàn #{{ selected.id }}
                                </p>
                                <h3
                                    class="mt-1 text-xl font-bold text-foreground"
                                >
                                    {{ selected.guest_name }}
                                </h3>
                                <span
                                    class="mt-2 inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
                                    :class="statusStyles[selected.status]"
                                    >{{ selected.status_label }}</span
                                >
                            </div>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                aria-label="Bỏ chọn"
                                @click="selected = null"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <div class="space-y-4 p-5">
                            <div class="grid grid-cols-2 gap-3">
                                <div
                                    class="rounded-lg border border-border bg-muted/30 p-3"
                                >
                                    <p
                                        class="text-[11px] tracking-wide text-muted-foreground uppercase"
                                    >
                                        Giờ đến
                                    </p>
                                    <p
                                        class="mt-1 text-lg font-bold text-foreground"
                                    >
                                        {{
                                            formatTime(
                                                selected.reservation_time,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-lg border border-border bg-muted/30 p-3"
                                >
                                    <p
                                        class="text-[11px] tracking-wide text-muted-foreground uppercase"
                                    >
                                        Số khách
                                    </p>
                                    <p
                                        class="mt-1 text-lg font-bold text-foreground"
                                    >
                                        {{ selected.party_size }} người
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-2.5 text-sm">
                                <a
                                    :href="`tel:${selected.guest_phone}`"
                                    class="flex items-center gap-3 rounded-lg p-2 text-foreground transition-colors hover:bg-muted"
                                    ><Phone
                                        class="size-4 text-indigo-300"
                                    /><span class="flex-1">{{
                                        selected.guest_phone
                                    }}</span
                                    ><span class="text-xs text-indigo-300"
                                        >Gọi</span
                                    ></a
                                ><button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-lg p-2 text-left text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    @click="copyPhone(selected.guest_phone)"
                                >
                                    <Copy
                                        class="size-4 text-muted-foreground"
                                    /><span
                                        >Sao chép số điện thoại</span
                                    ></button
                                ><a
                                    v-if="selected.guest_email"
                                    :href="`mailto:${selected.guest_email}`"
                                    class="flex items-center gap-3 rounded-lg p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
                                    ><Mail
                                        class="size-4 text-muted-foreground"
                                    /><span class="truncate">{{
                                        selected.guest_email
                                    }}</span></a
                                >
                                <div
                                    v-if="selected.branch_name"
                                    class="flex items-center gap-3 rounded-lg p-2 text-muted-foreground"
                                >
                                    <MapPin
                                        class="size-4 text-emerald-300"
                                    /><span>{{ selected.branch_name }}</span>
                                </div>
                                <div
                                    class="flex items-center gap-3 rounded-lg p-2 text-muted-foreground"
                                >
                                    <Table2
                                        class="size-4 text-amber-300"
                                    /><span>{{
                                        selected.table_name ?? 'Chưa gán bàn'
                                    }}</span>
                                </div>
                            </div>
                            <div
                                v-if="
                                    selected.special_requests ||
                                    selected.internal_notes
                                "
                                class="space-y-2 rounded-lg border border-border bg-muted/30 p-3"
                            >
                                <div v-if="selected.special_requests">
                                    <p
                                        class="mb-1 flex items-center gap-1.5 text-xs font-semibold text-amber-300"
                                    >
                                        <Info class="size-3.5" />Yêu cầu của
                                        khách
                                    </p>
                                    <p class="text-sm text-slate-300">
                                        {{ selected.special_requests }}
                                    </p>
                                </div>
                                <div
                                    v-if="selected.internal_notes"
                                    class="border-t border-border pt-2"
                                >
                                    <p
                                        class="mb-1 flex items-center gap-1.5 text-xs font-semibold text-slate-400"
                                    >
                                        <FileText class="size-3.5" />Ghi chú nội
                                        bộ
                                    </p>
                                    <p class="text-sm text-slate-400">
                                        {{ selected.internal_notes }}
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-2 border-t border-border pt-4">
                                <Button
                                    v-if="selected.status === 'pending'"
                                    class="w-full border border-emerald-600/40 bg-transparent text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/20"
                                    @click="openConfirmModal(selected)"
                                    ><Check class="mr-2 size-4" />Xác nhận đặt
                                    bàn</Button
                                ><Button
                                    v-if="selected.status === 'confirmed'"
                                    class="w-full border border-sky-600/40 bg-transparent text-sky-700 hover:bg-sky-50 dark:text-sky-400 dark:hover:bg-sky-950/20"
                                    @click="seat(selected)"
                                    ><UserCheck class="mr-2 size-4" />Dẫn khách
                                    vào bàn</Button
                                ><Button
                                    v-if="selected.status === 'seated'"
                                    class="w-full border border-teal-600/40 bg-transparent text-teal-700 hover:bg-teal-50 dark:text-teal-400 dark:hover:bg-teal-950/20"
                                    @click="complete(selected)"
                                    ><CheckCircle2 class="mr-2 size-4" />Hoàn
                                    tất và trả bàn</Button
                                >
                                <div
                                    v-if="
                                        ['pending', 'confirmed'].includes(
                                            selected.status,
                                        )
                                    "
                                    class="grid grid-cols-2 gap-2"
                                >
                                    <Button
                                        variant="outline"
                                        class="border-rose-400/30 text-rose-300 hover:bg-rose-500/10"
                                        @click="openCancelModal(selected)"
                                        ><Ban
                                            class="mr-1.5 size-4"
                                        />Hủy</Button
                                    ><Button
                                        v-if="selected.status === 'confirmed'"
                                        variant="outline"
                                        class="border-border text-muted-foreground hover:bg-muted hover:text-foreground"
                                        @click="noShow(selected)"
                                        ><AlertTriangle
                                            class="mr-1.5 size-4"
                                        />Không đến</Button
                                    ><Button
                                        v-else
                                        variant="outline"
                                        class="border-indigo-400/30 text-indigo-200 hover:bg-indigo-500/10"
                                        @click="autoAssign(selected)"
                                        ><WandSparkles
                                            class="mr-1.5 size-4"
                                        />Xếp bàn tự động</Button
                                    >
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        class="flex max-h-[560px] flex-col overflow-hidden rounded-xl border border-border bg-background p-5"
                    >
                        <div
                            class="mb-4 flex shrink-0 items-start justify-between gap-3"
                        >
                            <div>
                                <h3
                                    class="flex items-center gap-2 font-semibold text-foreground"
                                >
                                    <Table2 class="size-4 text-indigo-500" />
                                    Sơ đồ bàn
                                </h3>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{
                                        selected?.status === 'pending'
                                            ? 'Chọn bàn đủ chỗ để gán cho lượt đặt này.'
                                            : 'Chọn một lượt đặt đang chờ để gán bàn.'
                                    }}
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-muted px-2 py-1 text-[11px] font-medium text-muted-foreground"
                            >
                                {{ tablesForSelectedReservation.length }} bàn
                            </span>
                        </div>
                        <div
                            class="min-h-0 [scrollbar-color:var(--border)_transparent] overflow-y-auto pr-1"
                        >
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="table in tablesForSelectedReservation"
                                    :key="table.id"
                                    type="button"
                                    class="rounded-lg border p-2.5 text-left transition-colors"
                                    :class="tableStatusStyles[table.status]"
                                    :disabled="
                                        selected?.status !== 'pending' ||
                                        table.status !== 'available' ||
                                        table.capacity <
                                            (selected?.party_size ?? 0)
                                    "
                                    :title="
                                        selected?.status === 'pending' &&
                                        table.status === 'available' &&
                                        table.capacity >=
                                            (selected?.party_size ?? 0)
                                            ? `Gán ${table.name} cho ${selected.guest_name}`
                                            : `${table.name} · ${tableStatusLabels[table.status]}`
                                    "
                                    @click="
                                        selected
                                            ? openConfirmWithTable(
                                                  selected,
                                                  table,
                                              )
                                            : undefined
                                    "
                                >
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <span
                                            class="font-semibold text-foreground"
                                        >
                                            {{ table.name }}
                                        </span>
                                        <span
                                            class="size-2 shrink-0 rounded-full"
                                            :class="{
                                                'bg-emerald-500':
                                                    table.status ===
                                                    'available',
                                                'bg-amber-500':
                                                    table.status === 'reserved',
                                                'bg-sky-500':
                                                    table.status === 'occupied',
                                                'bg-muted-foreground':
                                                    table.status === 'inactive',
                                            }"
                                        />
                                    </div>
                                    <div
                                        class="mt-1 flex items-center justify-between gap-2 text-[11px] text-muted-foreground"
                                    >
                                        <span>{{ table.capacity }} chỗ</span>
                                        <span>{{
                                            tableStatusLabels[table.status]
                                        }}</span>
                                    </div>
                                    <span
                                        v-if="
                                            selected?.status === 'pending' &&
                                            table.status === 'available' &&
                                            table.capacity >=
                                                (selected?.party_size ?? 0)
                                        "
                                        class="mt-2 block text-[11px] font-semibold text-indigo-500"
                                    >
                                        Nhấn để gán bàn
                                    </span>
                                </button>
                            </div>
                        </div>
                        <div
                            class="mt-4 flex shrink-0 flex-wrap gap-x-3 gap-y-1 border-t border-border pt-3 text-[11px] text-muted-foreground"
                        >
                            <span class="inline-flex items-center gap-1.5">
                                <span
                                    class="size-2 rounded-full bg-emerald-500"
                                />
                                Trống {{ availableTableCount }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span
                                    class="size-2 rounded-full bg-amber-500"
                                />
                                Đã giữ {{ reservedTableCount }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="size-2 rounded-full bg-sky-500" />
                                Có khách {{ occupiedTableCount }}
                            </span>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="showCreateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm"
            @click.self="showCreateModal = false"
        >
            <div
                class="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-border bg-background shadow-2xl"
                @mousedown.capture="closeNativeDatePicker"
                @focusin.capture="closeNativeDatePicker"
            >
                <div
                    class="flex items-start justify-between border-b border-border bg-muted/10 px-6 py-5"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-500"
                        >
                            <CalendarCheck class="size-5" />
                        </div>
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-indigo-500 uppercase"
                            >
                                Cuộc gọi đến nhà hàng
                            </p>
                            <h3
                                class="mt-1 text-xl font-semibold text-foreground"
                            >
                                Ghi nhận đặt bàn mới
                            </h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Lưu thông tin khách để tiếp tục xác nhận bàn.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        @click="showCreateModal = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <div class="min-h-0 overflow-y-auto px-6 py-5">
                    <div class="space-y-5">
                        <section
                            class="rounded-xl border border-border bg-muted/10 p-4"
                        >
                            <div class="mb-4 flex items-center gap-2">
                                <Users class="size-4 text-indigo-500" />
                                <div>
                                    <h4
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Thông tin khách hàng
                                    </h4>
                                    <p class="text-xs text-muted-foreground">
                                        Dùng để liên hệ và xác nhận đặt bàn.
                                    </p>
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="sm:col-span-2">
                                    <span
                                        class="mb-1.5 block text-sm font-medium text-foreground"
                                        >Tên khách
                                        <b class="text-rose-500">*</b></span
                                    >
                                    <input
                                        v-model="createForm.guest_name"
                                        type="text"
                                        placeholder="Nguyễn Văn Khách"
                                        autocomplete="name"
                                        class="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                                    />
                                    <small
                                        v-if="createForm.errors.guest_name"
                                        class="mt-1 block text-xs text-rose-500"
                                        >{{
                                            createForm.errors.guest_name
                                        }}</small
                                    >
                                </label>
                                <label>
                                    <span
                                        class="mb-1.5 block text-sm font-medium text-foreground"
                                        >Số điện thoại
                                        <b class="text-rose-500">*</b></span
                                    >
                                    <div class="relative">
                                        <Phone
                                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        />
                                        <input
                                            v-model="createForm.guest_phone"
                                            type="tel"
                                            placeholder="0901 234 567"
                                            autocomplete="tel"
                                            class="w-full rounded-lg border border-border bg-background py-2.5 pr-3 pl-9 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                                        />
                                    </div>
                                    <small
                                        v-if="createForm.errors.guest_phone"
                                        class="mt-1 block text-xs text-rose-500"
                                        >{{
                                            createForm.errors.guest_phone
                                        }}</small
                                    >
                                </label>
                                <label>
                                    <span
                                        class="mb-1.5 block text-sm font-medium text-foreground"
                                        >Email</span
                                    >
                                    <div class="relative">
                                        <Mail
                                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        />
                                        <input
                                            v-model="createForm.guest_email"
                                            type="email"
                                            placeholder="khach@email.com"
                                            autocomplete="email"
                                            class="w-full rounded-lg border border-border bg-background py-2.5 pr-3 pl-9 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                                        />
                                    </div>
                                </label>
                            </div>
                        </section>

                        <section
                            class="rounded-xl border border-border bg-muted/10 p-4"
                        >
                            <div class="mb-4 flex items-center gap-2">
                                <CalendarDays class="size-4 text-indigo-500" />
                                <div>
                                    <h4
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Thời gian và quy mô
                                    </h4>
                                    <p class="text-xs text-muted-foreground">
                                        Chọn thời điểm khách dự kiến đến nhà
                                        hàng.
                                    </p>
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label>
                                    <span
                                        class="mb-1.5 block text-sm font-medium text-foreground"
                                        >Ngày đến
                                        <b class="text-rose-500">*</b></span
                                    >
                                    <input
                                        v-model="createForm.reservation_date"
                                        type="date"
                                        class="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                                    />
                                </label>
                                <label>
                                    <span
                                        class="mb-1.5 block text-sm font-medium text-foreground"
                                        >Giờ đến
                                        <b class="text-rose-500">*</b></span
                                    >
                                    <input
                                        v-model="createForm.reservation_time"
                                        type="time"
                                        class="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                                    />
                                    <small
                                        v-if="
                                            createForm.errors.reservation_time
                                        "
                                        class="mt-1 block text-xs text-rose-500"
                                        >{{
                                            createForm.errors.reservation_time
                                        }}</small
                                    >
                                </label>
                                <label>
                                    <span
                                        class="mb-1.5 block text-sm font-medium text-foreground"
                                        >Số khách
                                        <b class="text-rose-500">*</b></span
                                    >
                                    <div class="relative">
                                        <Users
                                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        />
                                        <input
                                            v-model.number="
                                                createForm.party_size
                                            "
                                            type="number"
                                            min="1"
                                            max="50"
                                            class="w-full rounded-lg border border-border bg-background py-2.5 pr-3 pl-9 text-sm text-foreground outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                                        />
                                    </div>
                                </label>
                                <label v-if="branches.length > 1">
                                    <span
                                        class="mb-1.5 block text-sm font-medium text-foreground"
                                        >Chi nhánh
                                        <b class="text-rose-500">*</b></span
                                    >
                                    <div class="relative">
                                        <MapPin
                                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        />
                                        <select
                                            v-model="createForm.branch_id"
                                            class="w-full appearance-none rounded-lg border border-border bg-background py-2.5 pr-3 pl-9 text-sm text-foreground outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
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
                                </label>
                            </div>
                        </section>

                        <section
                            class="rounded-xl border border-border bg-muted/10 p-4"
                        >
                            <div class="mb-3 flex items-center gap-2">
                                <FileText class="size-4 text-indigo-500" />
                                <div>
                                    <h4
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Ghi chú phục vụ
                                    </h4>
                                    <p class="text-xs text-muted-foreground">
                                        Thông tin đặc biệt nhân viên cần biết
                                        trước.
                                    </p>
                                </div>
                            </div>
                            <textarea
                                v-model="createForm.special_requests"
                                rows="3"
                                placeholder="Sinh nhật, trẻ em, dị ứng thực phẩm..."
                                class="w-full resize-none rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                            />
                        </section>
                    </div>
                </div>
                <div
                    class="flex flex-col-reverse gap-2 border-t border-border bg-muted/10 px-6 py-4 sm:flex-row sm:justify-end"
                >
                    <Button
                        variant="outline"
                        class="border-border text-muted-foreground hover:bg-muted hover:text-foreground"
                        @click="showCreateModal = false"
                        >Đóng</Button
                    ><Button
                        class="bg-indigo-600 text-white hover:bg-indigo-500"
                        :disabled="createForm.processing"
                        @click="submitCreate"
                        ><Loader2
                            v-if="createForm.processing"
                            class="mr-2 size-4 animate-spin"
                        /><Plus v-else class="mr-2 size-4" />{{
                            createForm.processing
                                ? 'Đang lưu...'
                                : 'Lưu đặt bàn'
                        }}</Button
                    >
                </div>
            </div>
        </div>

        <div
            v-if="showConfirmModal && selected"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm"
            @click.self="showConfirmModal = false"
        >
            <div
                class="w-full max-w-lg rounded-xl border border-border bg-white p-6 shadow-xl dark:bg-slate-900"
            >
                <div class="mb-5 flex items-start justify-between">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wider text-emerald-600 uppercase dark:text-emerald-300"
                        >
                            Sẵn sàng xác nhận
                        </p>
                        <h3 class="mt-1 text-xl font-bold text-slate-800 dark:text-white">
                            {{ selected.guest_name }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ formatTime(selected.reservation_time) }} ·
                            {{ selected.party_size }} khách
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-white"
                        @click="showConfirmModal = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <div class="space-y-4">
                    <label
                        ><span
                            class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
                            >Gán bàn
                            <span class="text-xs font-normal text-slate-400 dark:text-slate-500"
                                >(tuỳ chọn)</span
                            ></span
                        ><select
                            v-model="confirmForm.table_id"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/20"
                        >
                            <option :value="null">Chưa gán bàn</option>
                            <option
                                v-for="table in availableTablesForSelectedReservation"
                                :key="table.id"
                                :value="table.id"
                            >
                                {{ table.name }} · {{ table.capacity }} chỗ
                            </option></select
                        ><small
                            v-if="confirmForm.errors.table_id"
                            class="mt-1 block text-xs text-rose-500"
                            >{{ confirmForm.errors.table_id }}</small
                        ></label
                    ><Button
                        variant="outline"
                        class="w-full border-indigo-400/30 text-indigo-600 hover:bg-indigo-50 dark:text-indigo-200 dark:hover:bg-indigo-500/10"
                        :disabled="isProcessing"
                        @click="autoAssign(selected)"
                        ><WandSparkles class="mr-2 size-4" />Xếp bàn tự động
                        theo sức chứa</Button
                    ><label
                        ><span
                            class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
                            >Ghi chú nội bộ</span
                        ><textarea
                            v-model="confirmForm.internal_notes"
                            rows="3"
                            placeholder="Ghi chú cho nhân viên phục vụ..."
                            class="w-full resize-none rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-600 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/20"
                        />
                    </label>
                </div>
                <div
                    class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <Button
                        variant="outline"
                        class="border-slate-300 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        @click="showConfirmModal = false"
                        >Đóng</Button
                    ><Button
                        class="bg-indigo-600 hover:bg-indigo-500"
                        :disabled="confirmForm.processing"
                        @click="submitConfirm"
                        ><Loader2
                            v-if="confirmForm.processing"
                            class="mr-2 size-4 animate-spin"
                        /><Check v-else class="mr-2 size-4" />Xác nhận đặt
                        bàn</Button
                    >
                </div>
            </div>
        </div>


        <div
            v-if="showCancelModal && selected"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm"
            @click.self="showCancelModal = false"
        >
            <div
                class="w-full max-w-md rounded-xl border border-rose-400/30 bg-background p-6 shadow-xl"
            >
                <div class="mb-5 flex items-start justify-between">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wider text-rose-300 uppercase"
                        >
                            Huỷ lịch đặt bàn
                        </p>
                        <h3 class="mt-1 text-xl font-bold text-white">
                            {{ selected.guest_name }}
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-500 hover:bg-slate-800 hover:text-white"
                        @click="showCancelModal = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <label
                    ><span
                        class="mb-1.5 block text-sm font-medium text-slate-300"
                        >Lý do huỷ <b class="text-rose-400">*</b></span
                    ><textarea
                        v-model="cancelForm.reason"
                        rows="4"
                        placeholder="Ví dụ: Khách gọi điện xin huỷ..."
                        class="w-full resize-none rounded-lg border border-slate-700 bg-slate-900 px-3 py-2.5 text-sm text-slate-200 outline-none placeholder:text-slate-600 focus:border-rose-400 focus:ring-2 focus:ring-rose-400/20"
                    /><small
                        v-if="cancelForm.errors.reason"
                        class="mt-1 block text-xs text-rose-400"
                        >{{ cancelForm.errors.reason }}</small
                    ></label
                >
                <div
                    class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <Button
                        variant="outline"
                        class="border-slate-700 text-slate-300 hover:bg-slate-800"
                        @click="showCancelModal = false"
                        >Đóng</Button
                    ><Button
                        variant="destructive"
                        :disabled="cancelForm.processing"
                        @click="submitCancel"
                        ><Loader2
                            v-if="cancelForm.processing"
                            class="mr-2 size-4 animate-spin"
                        /><XCircle v-else class="mr-2 size-4" />Xác nhận
                        huỷ</Button
                    >
                </div>
            </div>
        </div>
    </Teleport>
</template>
