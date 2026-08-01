<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Building2,
    Calendar,
    CalendarDays,
    CheckCircle2,
    Eye,
    Mail,
    MessageSquare,
    Phone,
    Search,
    Sparkles,
    Store,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DataTable,
    FilterBar,
    PageHeader,
    Pagination,
    StatCard,
    StatusBadge,
} from '@/components/super-admin';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type BookingStatus = 'pending' | 'contacted' | 'completed' | 'cancelled';

interface Booking {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    restaurant_name: string;
    branch_count: number;
    preferred_date: string | null;
    preferred_time: string | null;
    notes: string | null;
    status: BookingStatus;
    contacted_at: string | null;
    contacted_by: { id: number; name: string; email: string } | null;
    admin_notes: string | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Stats {
    total: number;
    pending: number;
    contacted: number;
    completed: number;
}

const props = defineProps<{
    bookings: {
        data: Booking[];
        current_page: number;
        last_page: number;
        total: number;
        links: PaginationLink[];
    };
    stats: Stats;
    filters: {
        status: string | null;
        search: string | null;
    };
}>();

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const selectedBooking = ref<Booking | null>(null);
const isDetailModalOpen = ref(false);
const updateForm = ref({
    status: 'pending' as BookingStatus,
    admin_notes: '',
});

const columns = [
    { key: 'customer', label: 'Khách hàng', width: 'min-w-[190px]' },
    { key: 'contact', label: 'Liên hệ', width: 'min-w-[180px]' },
    { key: 'location', label: 'Nhà hàng / chi nhánh', width: 'min-w-[190px]' },
    { key: 'schedule', label: 'Lịch tư vấn', width: 'min-w-[150px]' },
    { key: 'status', label: 'Trạng thái', width: 'min-w-[130px]' },
    {
        key: 'actions',
        label: 'Thao tác',
        align: 'right' as const,
        width: 'w-[110px]',
    },
];

const statusLabels: Record<BookingStatus, string> = {
    pending: 'Chờ liên hệ',
    contacted: 'Đã liên hệ tư vấn',
    completed: 'Đã hoàn thành demo',
    cancelled: 'Đã hủy',
};

const paginationLinks = computed(() =>
    props.bookings.links.map((link) => ({
        ...link,
        label: link.label
            .replace(/Previous/gi, 'Trước')
            .replace(/Next/gi, 'Sau'),
    })),
);

function applyFilters() {
    router.get(
        '/super-admin/demo-bookings',
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

function openDetailModal(booking: Booking) {
    selectedBooking.value = booking;
    updateForm.value.status = booking.status;
    updateForm.value.admin_notes = booking.admin_notes || '';
    isDetailModalOpen.value = true;
}

function updateBookingStatus() {
    if (!selectedBooking.value) {
        return;
    }

    router.put(
        `/super-admin/demo-bookings/${selectedBooking.value.id}`,
        updateForm.value,
        {
            onSuccess: () => {
                isDetailModalOpen.value = false;
            },
        },
    );
}

function deleteBooking(id: number) {
    if (confirm('Bạn có chắc chắn muốn xóa đăng ký demo này không?')) {
        router.delete(`/super-admin/demo-bookings/${id}`);
    }
}

function getStatusLabel(status: string) {
    return statusLabels[status as BookingStatus] || status;
}
</script>

<template>
    <Head title="Lịch đăng ký demo" />

    <div class="space-y-6 px-6 py-5">
        <PageHeader
            title="Lịch đăng ký demo"
            subtitle="Quản lý khách hàng đăng ký trải nghiệm và tư vấn 1:1."
            :icon="Sparkles"
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Tổng đăng ký"
                :value="stats.total"
                :icon="CalendarDays"
                color="indigo"
            />
            <StatCard
                label="Chờ liên hệ"
                :value="stats.pending"
                :icon="Phone"
                color="amber"
            />
            <StatCard
                label="Đã liên hệ"
                :value="stats.contacted"
                :icon="MessageSquare"
                color="sky"
            />
            <StatCard
                label="Đã hoàn thành demo"
                :value="stats.completed"
                :icon="CheckCircle2"
                color="emerald"
            />
        </div>

        <FilterBar>
            <label class="min-w-[260px] flex-1 space-y-1.5">
                <span class="sr-only">Tìm kiếm đăng ký demo</span>
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Tìm theo tên, SĐT, email, nhà hàng..."
                        class="h-9 w-full rounded-lg border border-border bg-background pr-3 pl-9 text-sm transition outline-none focus:border-primary"
                        @keyup.enter="applyFilters"
                    />
                </div>
            </label>
            <label class="min-w-[180px] space-y-1.5">
                <span class="sr-only">Lọc theo trạng thái</span>
                <select
                    v-model="statusFilter"
                    class="h-9 w-full rounded-lg border border-border bg-background px-3 text-sm transition outline-none focus:border-primary"
                    @change="applyFilters"
                >
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending">Chờ liên hệ</option>
                    <option value="contacted">Đã liên hệ</option>
                    <option value="completed">Đã hoàn thành</option>
                    <option value="cancelled">Đã hủy</option>
                </select>
            </label>
            <Button class="shrink-0" @click="applyFilters">Lọc</Button>
        </FilterBar>

        <DataTable
            :columns="columns"
            :rows="bookings.data"
            :empty-icon="Calendar"
            empty-title="Không tìm thấy lịch demo"
            empty-description="Chưa có đăng ký nào phù hợp với bộ lọc hiện tại."
        >
            <template #cell-customer="{ row }">
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 font-semibold text-primary"
                    >
                        {{ row.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ row.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            Mã #{{ row.id }}
                        </p>
                    </div>
                </div>
            </template>

            <template #cell-contact="{ row }">
                <div class="space-y-1 text-xs">
                    <a
                        :href="`tel:${row.phone}`"
                        class="flex items-center gap-1.5 font-medium hover:text-primary"
                    >
                        <Phone class="size-3.5 text-primary" />
                        {{ row.phone }}
                    </a>
                    <p
                        v-if="row.email"
                        class="flex items-center gap-1.5 text-muted-foreground"
                    >
                        <Mail class="size-3.5" />
                        <span class="truncate">{{ row.email }}</span>
                    </p>
                    <p v-else class="text-muted-foreground">Chưa có email</p>
                </div>
            </template>

            <template #cell-location="{ row }">
                <div class="space-y-1 text-xs">
                    <p class="flex items-center gap-1.5 font-medium">
                        <Store class="size-3.5 text-primary" />
                        {{ row.restaurant_name }}
                    </p>
                    <p class="flex items-center gap-1.5 text-muted-foreground">
                        <Building2 class="size-3.5" />
                        {{ row.branch_count }} chi nhánh
                    </p>
                </div>
            </template>

            <template #cell-schedule="{ row }">
                <div class="space-y-1 text-xs">
                    <p
                        v-if="row.preferred_date"
                        class="flex items-center gap-1.5 font-medium"
                    >
                        <Calendar class="size-3.5 text-primary" />
                        {{ row.preferred_date }}
                    </p>
                    <p v-if="row.preferred_time" class="text-muted-foreground">
                        {{ row.preferred_time }}
                    </p>
                    <p
                        v-if="!row.preferred_date && !row.preferred_time"
                        class="text-muted-foreground"
                    >
                        Chưa chọn lịch
                    </p>
                </div>
            </template>

            <template #cell-status="{ row }">
                <StatusBadge :status="row.status">
                    {{ getStatusLabel(row.status) }}
                </StatusBadge>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="size-8 p-0"
                        title="Xem và cập nhật"
                        @click="openDetailModal(row)"
                    >
                        <Eye class="size-4" />
                        <span class="sr-only">Xem và cập nhật</span>
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="size-8 p-0 text-rose-600 hover:bg-rose-500/10 hover:text-rose-700"
                        title="Xóa đăng ký"
                        @click="deleteBooking(row.id)"
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only">Xóa đăng ký</span>
                    </Button>
                </div>
            </template>

            <template #pagination>
                <Pagination
                    :links="paginationLinks"
                    :current-page="bookings.current_page"
                    :last-page="bookings.last_page"
                    :total="bookings.total"
                />
            </template>
        </DataTable>
    </div>

    <Dialog v-model:open="isDetailModalOpen">
        <DialogContent
            v-if="selectedBooking"
            class="max-h-[90vh] overflow-y-auto sm:max-w-lg"
        >
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Sparkles class="size-4 text-primary" />
                    Chi tiết đăng ký demo #{{ selectedBooking.id }}
                </DialogTitle>
                <DialogDescription>
                    Xem thông tin khách hàng và cập nhật trạng thái xử lý.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div
                    class="grid gap-3 rounded-xl border border-border/60 bg-muted/20 p-4 text-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-muted-foreground">Khách hàng</span>
                        <span class="text-right font-semibold">{{
                            selectedBooking.name
                        }}</span>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-muted-foreground">Số điện thoại</span>
                        <a
                            :href="`tel:${selectedBooking.phone}`"
                            class="font-semibold text-primary hover:underline"
                        >
                            {{ selectedBooking.phone }}
                        </a>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-muted-foreground">Email</span>
                        <span class="text-right">{{
                            selectedBooking.email || 'Chưa có email'
                        }}</span>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-muted-foreground">Nhà hàng</span>
                        <span class="text-right font-semibold">{{
                            selectedBooking.restaurant_name
                        }}</span>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-muted-foreground">Quy mô</span>
                        <span
                            >{{ selectedBooking.branch_count }} chi nhánh</span
                        >
                    </div>
                </div>

                <div
                    v-if="selectedBooking.notes"
                    class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4"
                >
                    <p
                        class="flex items-center gap-2 text-sm font-semibold text-amber-700 dark:text-amber-300"
                    >
                        <MessageSquare class="size-4" />
                        Nhu cầu từ khách hàng
                    </p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ selectedBooking.notes }}
                    </p>
                </div>

                <div class="space-y-3">
                    <label class="space-y-1.5 text-sm">
                        <span class="font-medium">Cập nhật trạng thái</span>
                        <select
                            v-model="updateForm.status"
                            class="h-10 w-full rounded-lg border border-border bg-background px-3 text-sm"
                        >
                            <option value="pending">Chờ liên hệ</option>
                            <option value="contacted">Đã liên hệ tư vấn</option>
                            <option value="completed">
                                Đã hoàn thành demo
                            </option>
                            <option value="cancelled">Đã hủy</option>
                        </select>
                    </label>
                    <label class="space-y-1.5 text-sm">
                        <span class="font-medium">Ghi chú nội bộ</span>
                        <textarea
                            v-model="updateForm.admin_notes"
                            rows="4"
                            placeholder="Ghi lại kết quả cuộc gọi hoặc nhu cầu cụ thể của khách..."
                            class="w-full rounded-lg border border-border bg-background p-3 text-sm"
                        />
                    </label>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="isDetailModalOpen = false"
                    >Hủy</Button
                >
                <Button @click="updateBookingStatus">Lưu cập nhật</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
