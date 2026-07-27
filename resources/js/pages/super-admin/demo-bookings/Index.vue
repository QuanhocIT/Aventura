<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Calendar,
    Clock,
    Search,
    Phone,
    Mail,
    Building2,
    Store,
    CheckCircle2,
    XCircle,
    AlertCircle,
    MessageSquare,
    Eye,
    Trash2,
    Sparkles,
    Filter,
} from 'lucide-vue-next';
import { ref } from 'vue';

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
    status: 'pending' | 'contacted' | 'completed' | 'cancelled';
    contacted_at: string | null;
    contacted_by: { id: number; name: string; email: string } | null;
    admin_notes: string | null;
    created_at: string;
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
    status: 'pending' as 'pending' | 'contacted' | 'completed' | 'cancelled',
    admin_notes: '',
});

function applyFilters() {
    router.get(
        '/super-admin/demo-bookings',
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
        },
        { preserveState: true, replace: true }
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
        }
    );
}

function deleteBooking(id: number) {
    if (confirm('Bạn có chắc chắn muốn xóa đơn đăng ký demo này không?')) {
        router.delete(`/super-admin/demo-bookings/${id}`);
    }
}

function getStatusBadgeClass(status: string) {
    switch (status) {
        case 'pending':
            return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
        case 'contacted':
            return 'bg-sky-500/10 text-sky-400 border-sky-500/20';
        case 'completed':
            return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
        case 'cancelled':
            return 'bg-rose-500/10 text-rose-400 border-rose-500/20';
        default:
            return 'bg-slate-500/10 text-slate-400 border-slate-500/20';
    }
}

function getStatusLabel(status: string) {
    switch (status) {
        case 'pending':
            return 'Chờ liên hệ';
        case 'contacted':
            return 'Đã liên hệ';
        case 'completed':
            return 'Đã demo xong';
        case 'cancelled':
            return 'Đã hủy';
        default:
            return status;
    }
}
</script>

<template>
    <Head title="Quản Lý Đặt Lịch Demo" />

    <div class="min-h-screen bg-slate-950 p-6 text-slate-100 font-sans">
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Header section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-slate-950 shadow-md">
                            <Sparkles class="size-5" />
                        </div>
                        <h1 class="text-2xl font-bold text-white tracking-tight">
                            Quản Lý Đăng Ký Demo Vấn
                        </h1>
                    </div>
                    <p class="text-xs text-slate-400">
                        Danh sách khách hàng đăng ký trải nghiệm & tư vấn giải pháp Aventura 1:1
                    </p>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-5 shadow-lg">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Tổng Đơn Đăng Ký</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-400">
                            <Calendar class="size-4" />
                        </div>
                    </div>
                    <p class="mt-3 text-2xl font-extrabold text-white">{{ stats.total }}</p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-5 shadow-lg">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-amber-400 uppercase tracking-wider">Chờ Liên Hệ</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-400">
                            <AlertCircle class="size-4" />
                        </div>
                    </div>
                    <p class="mt-3 text-2xl font-extrabold text-amber-400">{{ stats.pending }}</p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-5 shadow-lg">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-sky-400 uppercase tracking-wider">Đã Liên Hệ</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-500/10 text-sky-400">
                            <Phone class="size-4" />
                        </div>
                    </div>
                    <p class="mt-3 text-2xl font-extrabold text-sky-400">{{ stats.contacted }}</p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-5 shadow-lg">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-emerald-400 uppercase tracking-wider">Đã Hoàn Thành Demo</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-400">
                            <CheckCircle2 class="size-4" />
                        </div>
                    </div>
                    <p class="mt-3 text-2xl font-extrabold text-emerald-400">{{ stats.completed }}</p>
                </div>
            </div>

            <!-- Search & Filter Bar -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-2xl border border-white/10 bg-slate-900/80 p-4 shadow-lg">
                <div class="relative flex-1 max-w-md">
                    <Search class="absolute left-3 top-2.5 size-4 text-slate-500" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm theo tên, SĐT, email, nhà hàng..."
                        @keyup.enter="applyFilters"
                        class="w-full rounded-xl border border-white/10 bg-slate-800/80 pl-9 pr-3 py-2 text-xs text-white placeholder-slate-500 focus:border-amber-400 focus:outline-none"
                    />
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <Filter class="size-4 text-slate-400" />
                        <select
                            v-model="statusFilter"
                            @change="applyFilters"
                            class="rounded-xl border border-white/10 bg-slate-800/80 px-3 py-2 text-xs text-white focus:border-amber-400 focus:outline-none"
                        >
                            <option value="">Tất cả trạng thái</option>
                            <option value="pending">Chờ liên hệ</option>
                            <option value="contacted">Đã liên hệ</option>
                            <option value="completed">Đã hoàn thành</option>
                            <option value="cancelled">Đã hủy</option>
                        </select>
                    </div>

                    <button
                        @click="applyFilters"
                        class="rounded-xl bg-amber-500 px-4 py-2 text-xs font-semibold text-slate-950 hover:bg-amber-400 transition"
                    >
                        Lọc
                    </button>
                </div>
            </div>

            <!-- Table section -->
            <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900 shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-white/10 bg-slate-800/50 text-slate-400 uppercase font-semibold">
                            <tr>
                                <th class="px-4 py-3">Khách hàng</th>
                                <th class="px-4 py-3">Số điện thoại / Email</th>
                                <th class="px-4 py-3">Nhà hàng / Chi nhánh</th>
                                <th class="px-4 py-3">Ngày & Giờ tư vấn</th>
                                <th class="px-4 py-3">Trạng thái</th>
                                <th class="px-4 py-3 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 text-slate-300">
                            <tr v-if="bookings.data.length === 0">
                                <td colspan="6" class="px-4 py-12 text-center text-slate-500">
                                    Không tìm thấy dữ liệu đặt lịch demo nào.
                                </td>
                            </tr>
                            <tr v-for="item in bookings.data" :key="item.id" class="hover:bg-white/5 transition">
                                <td class="px-4 py-3 font-medium text-white">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-800 text-amber-400 font-bold">
                                            {{ item.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-white">{{ item.name }}</p>
                                            <p class="text-[10px] text-slate-500">Mã: #{{ item.id }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3 space-y-0.5">
                                    <div class="flex items-center gap-1.5 text-slate-200">
                                        <Phone class="size-3 text-amber-400" />
                                        <span>{{ item.phone }}</span>
                                    </div>
                                    <div v-if="item.email" class="flex items-center gap-1.5 text-slate-400 text-[11px]">
                                        <Mail class="size-3 text-slate-500" />
                                        <span>{{ item.email }}</span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 space-y-0.5">
                                    <div class="flex items-center gap-1.5 font-medium text-white">
                                        <Store class="size-3.5 text-orange-400" />
                                        <span>{{ item.restaurant_name }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-slate-400 text-[11px]">
                                        <Building2 class="size-3 text-slate-500" />
                                        <span>{{ item.branch_count }} chi nhánh</span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 space-y-0.5">
                                    <div v-if="item.preferred_date" class="flex items-center gap-1.5 text-amber-300">
                                        <Calendar class="size-3 text-amber-400" />
                                        <span>{{ item.preferred_date }}</span>
                                    </div>
                                    <div v-if="item.preferred_time" class="flex items-center gap-1.5 text-slate-400 text-[11px]">
                                        <Clock class="size-3 text-slate-500" />
                                        <span>{{ item.preferred_time }}</span>
                                    </div>
                                </td>

                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                                        :class="getStatusBadgeClass(item.status)"
                                    >
                                        {{ getStatusLabel(item.status) }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="openDetailModal(item)"
                                            class="flex h-7 w-7 items-center justify-center rounded-lg border border-white/10 bg-slate-800 text-slate-300 hover:bg-slate-700 hover:text-white transition"
                                            title="Xem chi tiết & Cập nhật"
                                        >
                                            <Eye class="size-3.5" />
                                        </button>
                                        <button
                                            @click="deleteBooking(item.id)"
                                            class="flex h-7 w-7 items-center justify-center rounded-lg border border-rose-500/20 bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 transition"
                                            title="Xóa đơn"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Chi Tiết & Xử Lý Lịch Demo -->
    <Teleport to="body">
        <div
            v-if="isDetailModalOpen && selectedBooking"
            class="fixed inset-0 z-[99999] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-md"
        >
            <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-white/10 bg-slate-900 shadow-2xl text-slate-100">
                <div class="flex items-center justify-between border-b border-white/10 bg-slate-800/80 p-4">
                    <h3 class="font-bold text-white text-sm flex items-center gap-2">
                        <Sparkles class="size-4 text-amber-400" />
                        <span>Chi Tiết Đơn Đăng Ký Demo #{{ selectedBooking.id }}</span>
                    </h3>
                    <button @click="isDetailModalOpen = false" class="text-slate-400 hover:text-white">
                        <XCircle class="size-5" />
                    </button>
                </div>

                <div class="p-5 space-y-4 text-xs">
                    <!-- Thông tin khách -->
                    <div class="rounded-xl border border-white/10 bg-slate-800/40 p-3 space-y-2">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Khách hàng:</span>
                            <span class="font-bold text-white">{{ selectedBooking.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Số điện thoại:</span>
                            <a :href="'tel:' + selectedBooking.phone" class="font-semibold text-amber-400 hover:underline">{{ selectedBooking.phone }}</a>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Email:</span>
                            <span class="text-slate-200">{{ selectedBooking.email || 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Tên thương hiệu:</span>
                            <span class="font-semibold text-orange-400">{{ selectedBooking.restaurant_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Quy mô:</span>
                            <span class="text-slate-200">{{ selectedBooking.branch_count }} chi nhánh</span>
                        </div>
                    </div>

                    <!-- Ghi chú nhu cầu từ khách -->
                    <div v-if="selectedBooking.notes" class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-3 space-y-1">
                        <p class="font-semibold text-amber-300 flex items-center gap-1.5">
                            <MessageSquare class="size-3.5" /> Nhu cầu từ khách hàng:
                        </p>
                        <p class="text-slate-300 text-[11px] leading-relaxed">{{ selectedBooking.notes }}</p>
                    </div>

                    <!-- Form cập nhật trạng thái của chuyên gia -->
                    <div class="space-y-3 pt-2">
                        <div>
                            <label class="mb-1 block font-semibold text-slate-300">Cập nhật trạng thái xử lý:</label>
                            <select
                                v-model="updateForm.status"
                                class="w-full rounded-xl border border-white/10 bg-slate-800 p-2.5 text-xs text-white focus:border-amber-400 focus:outline-none"
                            >
                                <option value="pending">Chờ liên hệ (Pending)</option>
                                <option value="contacted">Đã liên hệ tư vấn (Contacted)</option>
                                <option value="completed">Đã hoàn thành Demo (Completed)</option>
                                <option value="cancelled">Đã hủy (Cancelled)</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block font-semibold text-slate-300">Ghi chú nội bộ chuyên gia:</label>
                            <textarea
                                v-model="updateForm.admin_notes"
                                rows="3"
                                placeholder="Ghi chú lại kết quả cuộc gọi, nhu cầu cụ thể của khách..."
                                class="w-full rounded-xl border border-white/10 bg-slate-800 p-2.5 text-xs text-white focus:border-amber-400 focus:outline-none"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-white/10 bg-slate-800/40 p-4">
                    <button
                        @click="isDetailModalOpen = false"
                        class="rounded-xl border border-white/10 px-4 py-2 text-xs font-medium text-slate-300 hover:bg-white/10"
                    >
                        Hủy
                    </button>
                    <button
                        @click="updateBookingStatus"
                        class="rounded-xl bg-amber-500 px-5 py-2 text-xs font-bold text-slate-950 hover:bg-amber-400 transition"
                    >
                        Lưu Cập Nhật
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
