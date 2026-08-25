<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Users, Plus, Search, Printer, Pencil, Mail, Phone, Clock, X, UserCheck, AlertCircle, Gift, ArrowUpDown } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Pagination } from '@/components/super-admin';
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
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Customer = {
    id: number;
    customer_code: string;
    full_name: string;
    phone: string;
    email: string | null;
    gender: 'male' | 'female' | 'other' | null;
    date_of_birth: string;
    notes: string | null;
    loyalty_points: number;
    last_order_at: string;
    created_at: string;
};

interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
    from: number | null;
    to: number | null;
    current_page: number;
    last_page: number;
}

const props = defineProps<{
    customers: Paginator<Customer>;
    stats: {
        total: number;
        total_points: number;
        new_this_month: number;
        retention_rate?: number;
        returning_30d?: number;
        new_customers_30d?: number;
        total_ordering_30d?: number;
    };
    search: string;
    segment: 'all' | 'vip' | 'regular' | 'new';
    sort: 'default' | 'points_desc' | 'points_asc' | 'recent';
    segmentCounts: { all: number; vip: number; regular: number; new: number };
    tierCounts: {
        gold: number;
        silver: number;
        bronze: number;
        normal: number;
        goldPct: number;
        silverPct: number;
        bronzePct: number;
        normalPct: number;
    };
    isOwner: boolean;
    hasRfmFeature: boolean;
}>();

// --- STATE ---
const searchQuery = ref(props.search);
const showAddModal = ref(false);
const editingCustomer = ref<Customer | null>(null);
const segmentFilter = ref(props.segment);
const sortBy = ref(props.sort);

// --- COMPUTED ---
const loyaltyTier = (
    pts: number,
): { label: string; cls: string; icon: string } | null => {
    if (pts >= 200) {
        return {
            label: 'Gold',
            cls: 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400',
            icon: '🥇',
        };
    }

    if (pts >= 100) {
        return {
            label: 'Silver',
            cls: 'bg-slate-100 text-slate-600 border-slate-300 dark:bg-slate-800 dark:text-slate-400',
            icon: '🥈',
        };
    }

    if (pts > 0) {
        return {
            label: 'Bronze',
            cls: 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-950/20 dark:text-orange-400',
            icon: '🥉',
        };
    }

    return null;
};

// segmentCounts/tierCounts giờ tính sẵn từ backend trên toàn bộ tập dữ liệu phù hợp bộ lọc
// tìm kiếm (không chỉ trang hiện tại) — dùng trực tiếp props.segmentCounts/props.tierCounts.

const form = useForm({
    full_name: '',
    phone: '',
    email: '',
    gender: 'male' as 'male' | 'female' | 'other',
    date_of_birth: '',
    notes: '',
});

const editForm = useForm({
    full_name: '',
    phone: '',
    email: '',
    gender: 'male' as 'male' | 'female' | 'other',
    date_of_birth: '',
    notes: '',
});

// --- ACTIONS ---
function applyFilters() {
    router.get(
        '/customers',
        {
            search: searchQuery.value || undefined,
            segment:
                segmentFilter.value !== 'all' ? segmentFilter.value : undefined,
            sort: sortBy.value !== 'default' ? sortBy.value : undefined,
        },
        { preserveState: true, replace: true },
    );
}

const handleSearch = applyFilters;

function setSegment(segment: typeof segmentFilter.value) {
    segmentFilter.value = segment;
    applyFilters();
}

function setSort(sort: typeof sortBy.value) {
    sortBy.value = sort;
    applyFilters();
}

const openAddModal = () => {
    form.reset();
    showAddModal.value = true;
};

const submitAdd = () => {
    form.post('/customers', {
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
        },
    });
};

const openEditModal = (c: Customer) => {
    editingCustomer.value = c;
    editForm.full_name = c.full_name;
    editForm.phone = c.phone;
    editForm.email = c.email || '';
    editForm.gender = (c.gender || 'male') as 'male' | 'female' | 'other';
    editForm.date_of_birth = c.date_of_birth || '';
    editForm.notes = c.notes || '';
};

const submitEdit = () => {
    if (!editingCustomer.value) {
        return;
    }

    editForm.patch(`/customers/${editingCustomer.value.id}`, {
        onSuccess: () => {
            editingCustomer.value = null;
            editForm.reset();
        },
    });
};

const triggerExport = () => {
    if (!props.isOwner) {
        toast.error(
            'Chỉ có Chủ nhà hàng mới có quyền xuất tệp dữ liệu khách hàng.',
        );

        return;
    }

    window.location.href = '/customers/export';
};

// --- HELPERS ---
const genderLabels = {
    male: 'Nam',
    female: 'Nữ',
    other: 'Khác',
};

const genderColors = {
    male: 'bg-blue-50 text-blue-600 border border-blue-100 dark:bg-blue-950/20 dark:text-blue-400 dark:border-blue-900/30',
    female: 'bg-rose-50 text-rose-600 border border-rose-100 dark:bg-rose-950/20 dark:text-rose-400 dark:border-rose-900/30',
    other: 'bg-slate-50 text-slate-600 border border-slate-200 dark:bg-slate-950/20 dark:text-slate-400 dark:border-slate-800',
};
</script>

<template>
    <Head title="Quản Lý Khách Hàng (CRM)" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- HEADER -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400"
                >
                    <Users class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Hệ Thống CRM & Dữ Liệu Khách Hàng
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Lưu trữ tập trung thông tin, điểm tích lũy thành viên và
                        quản trị bảo mật thông tin an toàn.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Spatie protected Export button in Frontend UI -->
                <Button
                    v-if="isOwner"
                    @click="triggerExport"
                    variant="outline"
                    class="flex h-10 items-center gap-1.5 border-indigo-200 text-xs font-semibold text-indigo-600 hover:bg-indigo-50"
                >
                    <Printer class="size-4" />
                    Xuất dữ liệu Excel/CSV
                </Button>

                <!-- Day 3 CRM Add customer button -->
                <Button
                    @click="openAddModal"
                    class="flex h-10 items-center gap-1.5 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                >
                    <Plus class="size-4" />
                    Thêm khách hàng mới
                </Button>
            </div>
        </div>

        <!-- CDP sub-navigation -->
        <div class="flex items-center gap-2 border-b pb-2">
            <button
                type="button"
                class="border-b-2 border-indigo-600 px-4 py-2 text-xs font-bold text-indigo-600 focus:outline-none"
            >
                👥 Hồ sơ CRM khách hàng
            </button>
            <button
                v-if="hasRfmFeature"
                type="button"
                @click="router.visit('/customers/cdp')"
                class="hover:border-slate-350 flex items-center gap-1.5 border-b-2 border-transparent px-4 py-2 text-xs font-bold text-slate-400 hover:text-slate-600 focus:outline-none"
            >
                ✨ Phân tích RFM & Hành vi (CDP)
            </button>
        </div>

        <!-- KPI STATS CARDS -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <!-- Total Customer Profiles -->
            <Card
                class="shadow-xs transition-transform hover:translate-y-[-2px]"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >Hồ sơ khách hàng</CardDescription
                    >
                    <Users class="size-4 text-slate-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-3xl font-black text-slate-800 dark:text-slate-100"
                        >{{ stats.total }}</span
                    >
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        khách hàng đăng ký thành viên
                    </p>
                </CardContent>
            </Card>

            <!-- Total Loyalty Points -->
            <Card
                class="border-indigo-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-indigo-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-indigo-500 uppercase"
                        >Điểm tích lũy thành viên</CardDescription
                    >
                    <Gift class="size-4 text-indigo-600 dark:text-indigo-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-3xl font-black text-indigo-600 dark:text-indigo-400"
                        >{{ stats.total_points }} pt</span
                    >
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        quy đổi ưu đãi & voucher
                    </p>
                </CardContent>
            </Card>

            <!-- New Customers This Month -->
            <Card
                class="border-emerald-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-emerald-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-emerald-500 uppercase"
                        >Thành viên mới tháng này</CardDescription
                    >
                    <UserCheck
                        class="size-4 text-emerald-600 dark:text-emerald-400"
                    />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-3xl font-black text-emerald-600 dark:text-emerald-400"
                        >+{{ stats.new_this_month }}</span
                    >
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        đóng đóng vào tăng trưởng F&B
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Retention Metrics Row -->
        <div
            v-if="stats.retention_rate !== undefined"
            class="grid grid-cols-1 gap-3 sm:grid-cols-5"
        >
            <!-- Donut chart: New vs Returning -->
            <div
                class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-4 sm:col-span-1 dark:border-slate-700 dark:bg-slate-900"
            >
                <svg width="80" height="80" viewBox="0 0 80 80">
                    <circle
                        cx="40"
                        cy="40"
                        r="28"
                        fill="none"
                        stroke="#e2e8f0"
                        stroke-width="12"
                        class="dark:stroke-slate-800"
                    />
                    <circle
                        cx="40"
                        cy="40"
                        r="28"
                        fill="none"
                        stroke="#6366f1"
                        stroke-width="12"
                        stroke-dasharray="175.9"
                        :stroke-dashoffset="
                            175.9 * (1 - (stats.retention_rate ?? 0) / 100)
                        "
                        stroke-linecap="round"
                        transform="rotate(-90 40 40)"
                    />
                    <text
                        x="40"
                        y="44"
                        text-anchor="middle"
                        class="fill-slate-700 text-xs font-black dark:fill-slate-200"
                        font-size="14"
                        font-weight="900"
                    >
                        {{ stats.retention_rate }}%
                    </text>
                </svg>
                <p
                    class="text-center text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                >
                    Tỉ lệ giữ chân
                </p>
            </div>

            <!-- Donut chart: Tiers -->
            <div
                class="group relative flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-4 sm:col-span-1 dark:border-slate-700 dark:bg-slate-900"
            >
                <svg
                    width="80"
                    height="80"
                    viewBox="0 0 80 80"
                    class="overflow-visible"
                >
                    <circle
                        cx="40"
                        cy="40"
                        r="28"
                        fill="none"
                        stroke="#e2e8f0"
                        stroke-width="12"
                        class="dark:stroke-slate-800"
                    />

                    <!-- Gold segment -->
                    <circle
                        v-if="tierCounts.gold > 0"
                        cx="40"
                        cy="40"
                        r="28"
                        fill="none"
                        stroke="#d97706"
                        stroke-width="12"
                        stroke-dasharray="175.9"
                        :stroke-dashoffset="
                            175.9 *
                            (1 - tierCounts.gold / (segmentCounts.all || 1))
                        "
                        transform="rotate(-90 40 40)"
                    />

                    <!-- Silver segment -->
                    <circle
                        v-if="tierCounts.silver > 0"
                        cx="40"
                        cy="40"
                        r="28"
                        fill="none"
                        stroke="#94a3b8"
                        stroke-width="12"
                        stroke-dasharray="175.9"
                        :stroke-dashoffset="
                            175.9 *
                            (1 - tierCounts.silver / (segmentCounts.all || 1))
                        "
                        :transform="`rotate(${-90 + (tierCounts.gold / (segmentCounts.all || 1)) * 360} 40 40)`"
                    />

                    <!-- Bronze segment -->
                    <circle
                        v-if="tierCounts.bronze > 0"
                        cx="40"
                        cy="40"
                        r="28"
                        fill="none"
                        stroke="#ea580c"
                        stroke-width="12"
                        stroke-dasharray="175.9"
                        :stroke-dashoffset="
                            175.9 *
                            (1 - tierCounts.bronze / (segmentCounts.all || 1))
                        "
                        :transform="`rotate(${-90 + ((tierCounts.gold + tierCounts.silver) / (segmentCounts.all || 1)) * 360} 40 40)`"
                    />

                    <!-- Normal segment -->
                    <circle
                        v-if="tierCounts.normal > 0"
                        cx="40"
                        cy="40"
                        r="28"
                        fill="none"
                        stroke="#cbd5e1"
                        stroke-width="12"
                        stroke-dasharray="175.9"
                        :stroke-dashoffset="
                            175.9 *
                            (1 - tierCounts.normal / (segmentCounts.all || 1))
                        "
                        :transform="`rotate(${-90 + ((tierCounts.gold + tierCounts.silver + tierCounts.bronze) / (segmentCounts.all || 1)) * 360} 40 40)`"
                        class="dark:stroke-slate-700"
                    />
                </svg>
                <p
                    class="text-center text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                >
                    Phân hạng hội viên
                </p>

                <!-- Floating tooltip on hover showing percentages -->
                <div
                    class="pointer-events-none invisible absolute bottom-full z-10 mb-2 flex w-28 flex-col gap-1 rounded-lg border border-slate-800 bg-slate-950/95 p-2 text-[9px] text-white shadow-lg backdrop-blur-xs transition-all duration-150 group-hover:visible"
                >
                    <div class="flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-amber-600"></span>
                        Gold: {{ tierCounts.goldPct }}%
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-slate-400"></span>
                        Silver: {{ tierCounts.silverPct }}%
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-orange-600"></span>
                        Bronze: {{ tierCounts.bronzePct }}%
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="bg-slate-350 size-2 rounded-full"></span>
                        Member: {{ tierCounts.normalPct }}%
                    </div>
                </div>
            </div>

            <!-- Stats breakdown -->
            <div class="grid grid-cols-3 gap-3 sm:col-span-3">
                <div
                    class="flex flex-col justify-center rounded-xl border border-indigo-200 bg-indigo-50 p-3 text-center dark:border-indigo-800/40 dark:bg-indigo-950/20"
                >
                    <p
                        class="text-2xl font-black text-indigo-700 dark:text-indigo-300"
                    >
                        {{ stats.returning_30d }}
                    </p>
                    <p
                        class="mt-0.5 text-[10px] font-bold tracking-wider text-indigo-500 uppercase"
                    >
                        Khách quay lại
                    </p>
                    <p class="text-[9px] text-muted-foreground">30 ngày qua</p>
                </div>
                <div
                    class="flex flex-col justify-center rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-center dark:border-emerald-800/40 dark:bg-emerald-950/20"
                >
                    <p
                        class="text-2xl font-black text-emerald-700 dark:text-emerald-300"
                    >
                        {{ stats.new_customers_30d }}
                    </p>
                    <p
                        class="mt-0.5 text-[10px] font-bold tracking-wider text-emerald-500 uppercase"
                    >
                        Khách mới
                    </p>
                    <p class="text-[9px] text-muted-foreground">30 ngày qua</p>
                </div>
                <div
                    class="flex flex-col justify-center rounded-xl border border-slate-200 bg-white p-3 text-center dark:border-slate-700 dark:bg-slate-900"
                >
                    <p
                        class="text-2xl font-black text-slate-700 dark:text-slate-300"
                    >
                        {{ stats.total_ordering_30d }}
                    </p>
                    <p
                        class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                    >
                        Tổng đặt hàng
                    </p>
                    <p class="text-[9px] text-muted-foreground">30 ngày qua</p>
                </div>
            </div>
        </div>

        <!-- CRM MAIN TABLE CARD -->
        <Card class="overflow-hidden shadow-sm">
            <!-- Segment tabs + search + sort -->
            <div
                class="flex flex-col gap-3 border-b bg-slate-50/50 p-4 dark:bg-slate-900/30"
            >
                <!-- Segment tabs -->
                <div
                    class="flex w-fit items-center gap-1.5 rounded-xl border border-slate-200/50 bg-slate-100 p-0.5 dark:border-slate-800 dark:bg-slate-900"
                >
                    <button
                        v-for="seg in [
                            { key: 'all', label: 'Tất cả', icon: '👥' },
                            { key: 'vip', label: 'VIP ≥100pt', icon: '🥇' },
                            {
                                key: 'regular',
                                label: 'Thường xuyên',
                                icon: '🥈',
                            },
                            { key: 'new', label: 'Mới (0pt)', icon: '🆕' },
                        ]"
                        :key="seg.key"
                        @click="setSegment(seg.key as any)"
                        :class="[
                            'flex items-center gap-1 rounded-lg px-3 py-1.5 text-[11px] font-bold whitespace-nowrap transition-colors',
                            segmentFilter === seg.key
                                ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
                                : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300',
                        ]"
                    >
                        {{ seg.icon }} {{ seg.label }}
                        <span
                            class="rounded-full bg-slate-200 px-1 text-[9px] dark:bg-slate-700"
                        >
                            {{
                                segmentCounts[
                                    seg.key as keyof typeof segmentCounts
                                ]
                            }}
                        </span>
                    </button>
                </div>

                <!-- Search + sort row -->
                <div class="flex items-center gap-2">
                    <div class="relative max-w-sm flex-1">
                        <Search
                            class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                        />
                        <Input
                            type="text"
                            placeholder="Tìm khách hàng theo SĐT, tên, email..."
                            v-model="searchQuery"
                            @keyup.enter="handleSearch"
                            class="h-9 bg-white pl-8 text-xs"
                        />
                    </div>
                    <Button
                        @click="handleSearch"
                        class="h-9 bg-slate-800 px-4 text-xs text-white hover:bg-slate-900"
                    >
                        Tìm kiếm
                    </Button>
                    <!-- Sort selector -->
                    <select
                        v-model="sortBy"
                        @change="applyFilters"
                        class="h-9 rounded-lg border border-slate-200 bg-white px-2 text-xs text-slate-700 focus:ring-2 focus:ring-indigo-400 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    >
                        <option value="default">Sắp xếp: Mặc định</option>
                        <option value="points_desc">Điểm: Cao → Thấp</option>
                        <option value="points_asc">Điểm: Thấp → Cao</option>
                        <option value="recent">Đơn gần nhất</option>
                    </select>
                </div>
            </div>

            <CardContent class="p-0">
                <div
                    v-if="customers.data.length === 0"
                    class="flex flex-col items-center gap-3 py-20 text-center text-muted-foreground"
                >
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40"
                    >
                        <Users class="size-7" />
                    </div>
                    <p class="font-bold text-slate-800 dark:text-slate-200">
                        Không tìm thấy khách hàng nào
                    </p>
                    <p class="mx-auto max-w-sm text-xs text-slate-500">
                        Vui lòng kiểm tra lại từ khóa tìm kiếm hoặc nhấn nút
                        "Thêm khách hàng mới" để đăng ký.
                    </p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b bg-slate-100 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                            >
                                <th class="p-3.5">Mã KH</th>
                                <th class="p-3.5">Khách hàng</th>
                                <th class="p-3.5">Số điện thoại</th>
                                <th class="p-3.5">Giới tính</th>
                                <th class="p-3.5">Ngày sinh</th>
                                <th
                                    class="cursor-pointer p-3.5 select-none"
                                    @click="
                                        setSort(
                                            sortBy === 'points_desc'
                                                ? 'points_asc'
                                                : 'points_desc',
                                        )
                                    "
                                >
                                    <span class="flex items-center gap-1">
                                        Điểm tích lũy
                                        <ArrowUpDown
                                            class="size-3 text-slate-400"
                                        />
                                    </span>
                                </th>
                                <th
                                    class="cursor-pointer p-3.5 select-none"
                                    @click="setSort('recent')"
                                >
                                    <span class="flex items-center gap-1">
                                        Đơn cuối lúc
                                        <Clock class="size-3 text-slate-400" />
                                    </span>
                                </th>
                                <th class="p-3.5">Ngày tham gia</th>
                                <th class="p-3.5 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr
                                v-for="c in customers.data"
                                :key="c.id"
                                class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                            >
                                <td
                                    class="p-3.5 font-mono font-bold text-indigo-600 dark:text-indigo-400"
                                >
                                    {{ c.customer_code }}
                                </td>
                                <td class="p-3.5">
                                    <div
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ c.full_name }}
                                    </div>
                                    <div
                                        v-if="c.email"
                                        class="mt-0.5 flex items-center gap-1 text-[10px] text-slate-400"
                                    >
                                        <Mail class="size-3 shrink-0" />
                                        {{ c.email }}
                                    </div>
                                </td>
                                <td
                                    class="p-3.5 font-mono font-bold text-slate-600 dark:text-slate-300"
                                >
                                    <span class="flex items-center gap-1">
                                        <Phone class="size-3 text-slate-400" />
                                        {{ c.phone }}
                                    </span>
                                </td>
                                <td class="p-3.5">
                                    <span
                                        v-if="c.gender"
                                        class="rounded px-2 py-0.5 text-[10px] font-bold"
                                        :class="genderColors[c.gender]"
                                    >
                                        {{ genderLabels[c.gender] }}
                                    </span>
                                    <span v-else class="text-slate-300">—</span>
                                </td>
                                <td
                                    class="p-3.5 font-mono text-slate-600 dark:text-slate-400"
                                >
                                    {{ c.date_of_birth || '—' }}
                                </td>
                                <td class="p-3.5">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="font-mono font-black text-indigo-600 dark:text-indigo-400"
                                            >{{ c.loyalty_points }}</span
                                        >
                                        <span class="text-[9px] text-slate-400"
                                            >pt</span
                                        >
                                        <span
                                            v-if="loyaltyTier(c.loyalty_points)"
                                            :class="[
                                                'rounded border px-1.5 py-0.5 text-[9px] font-extrabold',
                                                loyaltyTier(c.loyalty_points)!
                                                    .cls,
                                            ]"
                                        >
                                            {{
                                                loyaltyTier(c.loyalty_points)!
                                                    .icon
                                            }}
                                            {{
                                                loyaltyTier(c.loyalty_points)!
                                                    .label
                                            }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-3.5 font-mono text-slate-400">
                                    <span
                                        v-if="
                                            c.last_order_at &&
                                            c.last_order_at !== 'Chưa có'
                                        "
                                        class="text-slate-600 dark:text-slate-300"
                                        >{{ c.last_order_at }}</span
                                    >
                                    <span
                                        v-else
                                        class="text-slate-300 italic dark:text-slate-600"
                                        >Chưa có</span
                                    >
                                </td>
                                <td class="p-3.5 font-mono text-slate-500">
                                    {{ c.created_at }}
                                </td>
                                <td class="p-3.5 text-right">
                                    <button
                                        @click="openEditModal(c)"
                                        class="rounded-lg p-1.5 text-indigo-600 transition-colors hover:bg-indigo-50 dark:hover:bg-indigo-950/40"
                                        title="Chỉnh sửa thông tin"
                                    >
                                        <Pencil class="size-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="customers.last_page > 1"
                    class="flex items-center justify-between border-t bg-slate-50/50 p-4 dark:bg-slate-900/30"
                >
                    <div class="text-xs text-muted-foreground">
                        Hiển thị {{ customers.from ?? 0 }} -
                        {{ customers.to ?? 0 }} trong tổng số
                        {{ customers.total }} khách hàng
                    </div>
                    <Pagination :links="customers.links" class="border-0 p-0" />
                </div>
            </CardContent>
        </Card>

        <!-- MODAL: ADD CUSTOMER -->
        <Teleport to="body">
            <div
                v-if="showAddModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            >
                <Card
                    class="w-full max-w-md animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-base text-indigo-600"
                            >
                                <UserCheck class="size-5" />
                                Đăng Ký Khách Hàng CRM Mới
                            </CardTitle>
                            <CardDescription
                                >Khai báo hồ sơ khách hàng để tích lũy điểm thưởng
                                và phục vụ remarketing.</CardDescription
                            >
                        </div>
                        <button
                            @click="showAddModal = false"
                            class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            <X class="size-4" />
                        </button>
                    </CardHeader>

                    <CardContent class="space-y-4 pt-4">
                        <form @submit.prevent="submitAdd" class="space-y-4">
                            <div class="grid gap-1.5">
                                <Label for="cust-name"
                                    >Họ và tên khách hàng
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="cust-name"
                                    v-model="form.full_name"
                                    placeholder="Nguyễn Văn A..."
                                    required
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-1.5">
                                    <Label for="cust-phone"
                                        >Số điện thoại liên lạc
                                        <span class="text-rose-500">*</span></Label
                                    >
                                    <Input
                                        id="cust-phone"
                                        v-model="form.phone"
                                        placeholder="090..."
                                        required
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for="cust-email">Địa chỉ Email</Label>
                                    <Input
                                        id="cust-email"
                                        type="email"
                                        v-model="form.email"
                                        placeholder="example@..."
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-1.5">
                                    <Label for="cust-gender"
                                        >Giới tính thành viên</Label
                                    >
                                    <select
                                        id="cust-gender"
                                        v-model="form.gender"
                                        class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                    >
                                        <option value="male">Nam</option>
                                        <option value="female">Nữ</option>
                                        <option value="other">Khác</option>
                                    </select>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for="cust-dob">Ngày sinh nhật</Label>
                                    <Input
                                        id="cust-dob"
                                        type="date"
                                        v-model="form.date_of_birth"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="cust-notes"
                                    >Ghi chú sở thích/thói quen ăn uống</Label
                                >
                                <textarea
                                    id="cust-notes"
                                    v-model="form.notes"
                                    rows="3"
                                    placeholder="Ghi chú sở thích ăn uống của khách (ít cay, nhiều hành...)"
                                    class="w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                />
                            </div>

                            <div class="flex justify-end gap-2 border-t pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="showAddModal = false"
                                    >Hủy</Button
                                >
                                <Button
                                    type="submit"
                                    size="sm"
                                    class="bg-indigo-600 font-semibold text-white hover:bg-indigo-700"
                                    :disabled="form.processing"
                                >
                                    {{
                                        form.processing
                                            ? 'Đang lưu...'
                                            : 'Thêm khách hàng'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </Teleport>

        <!-- MODAL: EDIT CUSTOMER -->
        <Teleport to="body">
            <div
                v-if="editingCustomer"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            >
                <Card
                    class="w-full max-w-md animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-base text-indigo-600"
                            >
                                <Pencil class="size-4" />
                                Chỉnh Sửa Hồ Sơ Khách Hàng
                            </CardTitle>
                            <CardDescription
                                >Cập nhật lại thông tin cá nhân của khách hàng trên
                                hệ thống CRM.</CardDescription
                            >
                        </div>
                        <button
                            @click="editingCustomer = null"
                            class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            <X class="size-4" />
                        </button>
                    </CardHeader>

                    <CardContent class="space-y-4 pt-4">
                        <form @submit.prevent="submitEdit" class="space-y-4">
                            <div class="grid gap-1.5">
                                <Label
                                    >Họ và tên khách hàng
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input v-model="editForm.full_name" required />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-1.5">
                                    <Label
                                        >Số điện thoại liên lạc
                                        <span class="text-rose-500">*</span></Label
                                    >
                                    <Input v-model="editForm.phone" required />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Địa chỉ Email</Label>
                                    <Input type="email" v-model="editForm.email" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-1.5">
                                    <Label>Giới tính thành viên</Label>
                                    <select
                                        v-model="editForm.gender"
                                        class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                    >
                                        <option value="male">Nam</option>
                                        <option value="female">Nữ</option>
                                        <option value="other">Khác</option>
                                    </select>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Ngày sinh nhật</Label>
                                    <Input
                                        type="date"
                                        v-model="editForm.date_of_birth"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-1.5">
                                <Label>Ghi chú sở thích/thói quen ăn uống</Label>
                                <textarea
                                    v-model="editForm.notes"
                                    rows="3"
                                    class="w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                />
                            </div>

                            <div
                                class="flex items-start gap-2 rounded-xl border border-amber-100 bg-amber-50/50 p-3 text-[10px] text-amber-700 dark:bg-amber-950/20 dark:text-amber-400"
                            >
                                <AlertCircle
                                    class="mt-0.5 size-4 shrink-0 text-amber-600"
                                />
                                <p>
                                    <strong>Lưu ý bảo mật:</strong> Mọi chỉnh sửa dữ
                                    liệu khách hàng sẽ được ghi nhận và lưu vết đầy
                                    đủ trong lịch sử hoạt động để phòng chống rủi ro
                                    giả mạo thông tin điểm thưởng.
                                </p>
                            </div>

                            <div class="flex justify-end gap-2 border-t pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="editingCustomer = null"
                                    >Hủy</Button
                                >
                                <Button
                                    type="submit"
                                    size="sm"
                                    class="bg-indigo-600 font-semibold text-white hover:bg-indigo-700"
                                    :disabled="editForm.processing"
                                >
                                    {{
                                        editForm.processing
                                            ? 'Đang lưu...'
                                            : 'Lưu hồ sơ'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </Teleport>
    </div>
</template>
