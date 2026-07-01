<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Users, Plus, Search, Printer, Trash2, Pencil, Calendar, Mail, 
    Phone, Clock, X, Sparkles, UserCheck, ShieldCheck, AlertCircle, 
    Gift, ArrowUpDown, ChevronDown, Check
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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

const props = defineProps<{
    customers: Customer[];
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
    isOwner: boolean;
    hasRfmFeature: boolean;
}>();

// --- STATE ---
const searchQuery   = ref(props.search);
const showAddModal  = ref(false);
const editingCustomer = ref<Customer | null>(null);
const segmentFilter = ref<'all' | 'vip' | 'regular' | 'new'>('all');
const sortBy        = ref<'default' | 'points_desc' | 'points_asc' | 'recent'>('default');

// --- COMPUTED ---
const loyaltyTier = (pts: number): { label: string; cls: string; icon: string } | null => {
    if (pts >= 200) {
return { label: 'Gold',   cls: 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400',    icon: '🥇' };
}

    if (pts >= 100) {
return { label: 'Silver', cls: 'bg-slate-100 text-slate-600 border-slate-300 dark:bg-slate-800 dark:text-slate-400',       icon: '🥈' };
}

    if (pts > 0)    {
return { label: 'Bronze', cls: 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-950/20 dark:text-orange-400', icon: '🥉' };
}

    return null;
};

const segmentCounts = computed(() => ({
    all:     props.customers.length,
    vip:     props.customers.filter(c => c.loyalty_points >= 100).length,
    regular: props.customers.filter(c => c.loyalty_points > 0 && c.loyalty_points < 100).length,
    new:     props.customers.filter(c => c.loyalty_points === 0).length,
}));

const tierCounts = computed(() => {
    const total = props.customers.length || 1;
    const gold = props.customers.filter(c => c.loyalty_points >= 200).length;
    const silver = props.customers.filter(c => c.loyalty_points >= 100 && c.loyalty_points < 200).length;
    const bronze = props.customers.filter(c => c.loyalty_points > 0 && c.loyalty_points < 100).length;
    const normal = props.customers.filter(c => c.loyalty_points === 0).length;
    
    return {
        gold,
        silver,
        bronze,
        normal,
        goldPct: Math.round((gold / total) * 100),
        silverPct: Math.round((silver / total) * 100),
        bronzePct: Math.round((bronze / total) * 100),
        normalPct: Math.round((normal / total) * 100),
    };
});

const displayedCustomers = computed(() => {
    let list = [...props.customers];

    if (segmentFilter.value === 'vip')     {
list = list.filter(c => c.loyalty_points >= 100);
}

    if (segmentFilter.value === 'regular') {
list = list.filter(c => c.loyalty_points > 0 && c.loyalty_points < 100);
}

    if (segmentFilter.value === 'new')     {
list = list.filter(c => c.loyalty_points === 0);
}

    if (sortBy.value === 'points_desc') {
list.sort((a, b) => b.loyalty_points - a.loyalty_points);
}

    if (sortBy.value === 'points_asc')  {
list.sort((a, b) => a.loyalty_points - b.loyalty_points);
}

    if (sortBy.value === 'recent') {
        list.sort((a, b) => {
            if (!a.last_order_at || a.last_order_at === 'Chưa có') {
return 1;
}

            if (!b.last_order_at || b.last_order_at === 'Chưa có') {
return -1;
}

            return b.last_order_at.localeCompare(a.last_order_at);
        });
    }

    return list;
});

const currentPage = ref(1);
const itemsPerPage = 10;
const totalPages = computed(() => Math.ceil(displayedCustomers.value.length / itemsPerPage));
const paginatedCustomers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;

    return displayedCustomers.value.slice(start, start + itemsPerPage);
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
watch([segmentFilter, sortBy, searchQuery], () => {
    currentPage.value = 1;
});

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
const handleSearch = () => {
    router.get('/customers', { search: searchQuery.value }, { preserveState: true, replace: true });
};

const openAddModal = () => {
    form.reset();
    showAddModal.value = true;
};

const submitAdd = () => {
    form.post('/customers', {
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
        }
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
        }
    });
};

const triggerExport = () => {
    if (!props.isOwner) {
        alert('Chỉ có Chủ nhà hàng mới có quyền xuất tệp dữ liệu khách hàng.');

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

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- HEADER -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <Users class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Hệ Thống CRM & Dữ Liệu Khách Hàng</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Lưu trữ tập trung thông tin, điểm tích lũy thành viên và quản trị bảo mật thông tin an toàn.</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Spatie protected Export button in Frontend UI -->
                <Button 
                    v-if="isOwner"
                    @click="triggerExport"
                    variant="outline"
                    class="h-10 text-xs text-indigo-600 border-indigo-200 hover:bg-indigo-50 font-semibold flex items-center gap-1.5"
                >
                    <Printer class="size-4" />
                    Xuất dữ liệu Excel/CSV
                </Button>

                <!-- Day 3 CRM Add customer button -->
                <Button 
                    @click="openAddModal" 
                    class="h-10 text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold flex items-center gap-1.5"
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
                class="px-4 py-2 text-xs font-bold border-b-2 border-indigo-600 text-indigo-600 focus:outline-none"
            >
                👥 Hồ sơ CRM khách hàng
            </button>
            <button 
                v-if="hasRfmFeature"
                type="button"
                @click="router.visit('/customers/cdp')"
                class="px-4 py-2 text-xs font-bold border-b-2 border-transparent text-slate-400 hover:text-slate-600 hover:border-slate-350 flex items-center gap-1.5 focus:outline-none"
            >
                ✨ Phân tích RFM & Hành vi (CDP)
            </button>
        </div>

        <!-- KPI STATS CARDS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Total Customer Profiles -->
            <Card class="shadow-xs hover:translate-y-[-2px] transition-transform">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-slate-400">Hồ sơ khách hàng</CardDescription>
                    <Users class="size-4 text-slate-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span class="text-3xl font-black text-slate-800 dark:text-slate-100">{{ stats.total }}</span>
                    <p class="mt-0.5 text-xs text-muted-foreground">khách hàng đăng ký thành viên</p>
                </CardContent>
            </Card>

            <!-- Total Loyalty Points -->
            <Card class="shadow-xs border-indigo-100 dark:border-indigo-950/20 hover:translate-y-[-2px] transition-transform">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-indigo-500">Điểm tích lũy thành viên</CardDescription>
                    <Gift class="size-4 text-indigo-600 dark:text-indigo-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span class="text-3xl font-black text-indigo-600 dark:text-indigo-400">{{ stats.total_points }} pt</span>
                    <p class="mt-0.5 text-xs text-muted-foreground">quy đổi ưu đãi & voucher</p>
                </CardContent>
            </Card>

            <!-- New Customers This Month -->
            <Card class="shadow-xs border-emerald-100 dark:border-emerald-950/20 hover:translate-y-[-2px] transition-transform">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-emerald-500">Thành viên mới tháng này</CardDescription>
                    <UserCheck class="size-4 text-emerald-600 dark:text-emerald-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400">+{{ stats.new_this_month }}</span>
                    <p class="mt-0.5 text-xs text-muted-foreground">đóng đóng vào tăng trưởng F&B</p>
                </CardContent>
            </Card>
        </div>

        <!-- Retention Metrics Row -->
        <div v-if="stats.retention_rate !== undefined" class="grid grid-cols-1 sm:grid-cols-5 gap-3">
            <!-- Donut chart: New vs Returning -->
            <div class="sm:col-span-1 flex flex-col items-center justify-center rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4 gap-2">
                <svg width="80" height="80" viewBox="0 0 80 80">
                    <circle cx="40" cy="40" r="28" fill="none" stroke="#e2e8f0" stroke-width="12" class="dark:stroke-slate-800" />
                    <circle cx="40" cy="40" r="28" fill="none" stroke="#6366f1" stroke-width="12"
                        stroke-dasharray="175.9"
                        :stroke-dashoffset="175.9 * (1 - (stats.retention_rate ?? 0) / 100)"
                        stroke-linecap="round" transform="rotate(-90 40 40)" />
                    <text x="40" y="44" text-anchor="middle" class="text-xs font-black fill-slate-700 dark:fill-slate-200" font-size="14" font-weight="900">
                        {{ stats.retention_rate }}%
                    </text>
                </svg>
                <p class="text-[10px] text-center font-bold text-slate-500 uppercase tracking-wider">Tỉ lệ giữ chân</p>
            </div>

            <!-- Donut chart: Tiers -->
            <div class="sm:col-span-1 flex flex-col items-center justify-center rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4 gap-2 relative group cursor-pointer">
                <svg width="80" height="80" viewBox="0 0 80 80" class="overflow-visible">
                    <circle cx="40" cy="40" r="28" fill="none" stroke="#e2e8f0" stroke-width="12" class="dark:stroke-slate-800" />
                    
                    <!-- Gold segment -->
                    <circle v-if="tierCounts.gold > 0" cx="40" cy="40" r="28" fill="none" stroke="#d97706" stroke-width="12"
                        stroke-dasharray="175.9"
                        :stroke-dashoffset="175.9 * (1 - tierCounts.gold / (customers.length || 1))"
                        transform="rotate(-90 40 40)" />
                        
                    <!-- Silver segment -->
                    <circle v-if="tierCounts.silver > 0" cx="40" cy="40" r="28" fill="none" stroke="#94a3b8" stroke-width="12"
                        stroke-dasharray="175.9"
                        :stroke-dashoffset="175.9 * (1 - tierCounts.silver / (customers.length || 1))"
                        :transform="`rotate(${-90 + (tierCounts.gold / (customers.length || 1)) * 360} 40 40)`" />
                        
                    <!-- Bronze segment -->
                    <circle v-if="tierCounts.bronze > 0" cx="40" cy="40" r="28" fill="none" stroke="#ea580c" stroke-width="12"
                        stroke-dasharray="175.9"
                        :stroke-dashoffset="175.9 * (1 - tierCounts.bronze / (customers.length || 1))"
                        :transform="`rotate(${-90 + ((tierCounts.gold + tierCounts.silver) / (customers.length || 1)) * 360} 40 40)`" />
                        
                    <!-- Normal segment -->
                    <circle v-if="tierCounts.normal > 0" cx="40" cy="40" r="28" fill="none" stroke="#cbd5e1" stroke-width="12"
                        stroke-dasharray="175.9"
                        :stroke-dashoffset="175.9 * (1 - tierCounts.normal / (customers.length || 1))"
                        :transform="`rotate(${-90 + ((tierCounts.gold + tierCounts.silver + tierCounts.bronze) / (customers.length || 1)) * 360} 40 40)`" class="dark:stroke-slate-700" />
                </svg>
                <p class="text-[10px] text-center font-bold text-slate-500 uppercase tracking-wider">Phân hạng hội viên</p>
                
                <!-- Floating tooltip on hover showing percentages -->
                <div class="absolute invisible group-hover:visible bottom-full mb-2 bg-slate-950/95 backdrop-blur-xs text-white p-2 rounded-lg text-[9px] shadow-lg flex flex-col gap-1 z-10 w-28 border border-slate-800 pointer-events-none transition-all duration-150">
                    <div class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-amber-600"></span> Gold: {{ tierCounts.goldPct }}%</div>
                    <div class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-slate-400"></span> Silver: {{ tierCounts.silverPct }}%</div>
                    <div class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-orange-600"></span> Bronze: {{ tierCounts.bronzePct }}%</div>
                    <div class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-slate-350"></span> Member: {{ tierCounts.normalPct }}%</div>
                </div>
            </div>

            <!-- Stats breakdown -->
            <div class="sm:col-span-3 grid grid-cols-3 gap-3">
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 dark:bg-indigo-950/20 dark:border-indigo-800/40 p-3 text-center flex flex-col justify-center">
                    <p class="text-2xl font-black text-indigo-700 dark:text-indigo-300">{{ stats.returning_30d }}</p>
                    <p class="text-[10px] font-bold text-indigo-500 mt-0.5 uppercase tracking-wider">Khách quay lại</p>
                    <p class="text-[9px] text-muted-foreground">30 ngày qua</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-950/20 dark:border-emerald-800/40 p-3 text-center flex flex-col justify-center">
                    <p class="text-2xl font-black text-emerald-700 dark:text-emerald-300">{{ stats.new_customers_30d }}</p>
                    <p class="text-[10px] font-bold text-emerald-500 mt-0.5 uppercase tracking-wider">Khách mới</p>
                    <p class="text-[9px] text-muted-foreground">30 ngày qua</p>
                </div>
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3 text-center flex flex-col justify-center">
                    <p class="text-2xl font-black text-slate-700 dark:text-slate-300">{{ stats.total_ordering_30d }}</p>
                    <p class="text-[10px] font-bold text-slate-500 mt-0.5 uppercase tracking-wider">Tổng đặt hàng</p>
                    <p class="text-[9px] text-muted-foreground">30 ngày qua</p>
                </div>
            </div>
        </div>

        <!-- CRM MAIN TABLE CARD -->
        <Card class="shadow-sm overflow-hidden">
            <!-- Segment tabs + search + sort -->
            <div class="p-4 bg-slate-50/50 dark:bg-slate-900/30 border-b flex flex-col gap-3">

                <!-- Segment tabs -->
                <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-900 rounded-xl p-0.5 border border-slate-200/50 dark:border-slate-800 w-fit">
                    <button
                        v-for="seg in [
                            { key: 'all',     label: 'Tất cả',    icon: '👥' },
                            { key: 'vip',     label: 'VIP ≥100pt', icon: '🥇' },
                            { key: 'regular', label: 'Thường xuyên', icon: '🥈' },
                            { key: 'new',     label: 'Mới (0pt)',  icon: '🆕' },
                        ]"
                        :key="seg.key"
                        @click="segmentFilter = seg.key as any"
                        :class="[
                            'px-3 py-1.5 text-[11px] font-bold rounded-lg transition-colors whitespace-nowrap flex items-center gap-1',
                            segmentFilter === seg.key
                                ? 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 shadow-sm'
                                : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'
                        ]"
                    >
                        {{ seg.icon }} {{ seg.label }}
                        <span class="text-[9px] bg-slate-200 dark:bg-slate-700 px-1 rounded-full">
                            {{ segmentCounts[seg.key as keyof typeof segmentCounts] }}
                        </span>
                    </button>
                </div>

                <!-- Search + sort row -->
                <div class="flex items-center gap-2">
                    <div class="relative flex-1 max-w-sm">
                        <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                        <Input
                            type="text"
                            placeholder="Tìm khách hàng theo SĐT, tên, email..."
                            v-model="searchQuery"
                            @keyup.enter="handleSearch"
                            class="h-9 text-xs pl-8 bg-white"
                        />
                    </div>
                    <Button @click="handleSearch" class="h-9 text-xs bg-slate-800 hover:bg-slate-900 text-white px-4">
                        Tìm kiếm
                    </Button>
                    <!-- Sort selector -->
                    <select
                        v-model="sortBy"
                        class="h-9 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                    >
                        <option value="default">Sắp xếp: Mặc định</option>
                        <option value="points_desc">Điểm: Cao → Thấp</option>
                        <option value="points_asc">Điểm: Thấp → Cao</option>
                        <option value="recent">Đơn gần nhất</option>
                    </select>
                </div>
            </div>

            <CardContent class="p-0">
                <div v-if="displayedCustomers.length === 0" class="flex flex-col items-center gap-3 py-20 text-center text-muted-foreground">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600">
                        <Users class="size-7" />
                    </div>
                    <p class="font-bold text-slate-800 dark:text-slate-200">Không tìm thấy khách hàng nào</p>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">Vui lòng kiểm tra lại từ khóa tìm kiếm hoặc nhấn nút "Thêm khách hàng mới" để đăng ký.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 dark:bg-slate-950 border-b text-[10px] uppercase font-bold tracking-wider text-slate-500">
                                <th class="p-3.5">Mã KH</th>
                                <th class="p-3.5">Khách hàng</th>
                                <th class="p-3.5">Số điện thoại</th>
                                <th class="p-3.5">Giới tính</th>
                                <th class="p-3.5">Ngày sinh</th>
                                <th class="p-3.5 cursor-pointer select-none" @click="sortBy = sortBy === 'points_desc' ? 'points_asc' : 'points_desc'">
                                    <span class="flex items-center gap-1">
                                        Điểm tích lũy
                                        <ArrowUpDown class="size-3 text-slate-400" />
                                    </span>
                                </th>
                                <th class="p-3.5 cursor-pointer select-none" @click="sortBy = 'recent'">
                                    <span class="flex items-center gap-1">
                                        Đơn cuối lúc
                                        <Clock class="size-3 text-slate-400" />
                                    </span>
                                </th>
                                <th class="p-3.5">Ngày tham gia</th>
                                <th class="p-3.5 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="c in paginatedCustomers" :key="c.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                <td class="p-3.5 font-bold font-mono text-indigo-600 dark:text-indigo-400">{{ c.customer_code }}</td>
                                <td class="p-3.5">
                                    <div class="font-bold text-slate-800 dark:text-slate-200">{{ c.full_name }}</div>
                                    <div v-if="c.email" class="text-[10px] text-slate-400 mt-0.5 flex items-center gap-1">
                                        <Mail class="size-3 shrink-0" /> {{ c.email }}
                                    </div>
                                </td>
                                <td class="p-3.5 font-bold font-mono text-slate-600 dark:text-slate-300">
                                    <span class="flex items-center gap-1">
                                        <Phone class="size-3 text-slate-400" />
                                        {{ c.phone }}
                                    </span>
                                </td>
                                <td class="p-3.5">
                                    <span v-if="c.gender" class="px-2 py-0.5 rounded text-[10px] font-bold" :class="genderColors[c.gender]">
                                        {{ genderLabels[c.gender] }}
                                    </span>
                                    <span v-else class="text-slate-300">—</span>
                                </td>
                                <td class="p-3.5 font-mono text-slate-600 dark:text-slate-400">{{ c.date_of_birth || '—' }}</td>
                                <td class="p-3.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-black font-mono text-indigo-600 dark:text-indigo-400">{{ c.loyalty_points }}</span>
                                        <span class="text-[9px] text-slate-400">pt</span>
                                        <span
                                            v-if="loyaltyTier(c.loyalty_points)"
                                            :class="['px-1.5 py-0.5 rounded text-[9px] font-extrabold border', loyaltyTier(c.loyalty_points)!.cls]"
                                        >
                                            {{ loyaltyTier(c.loyalty_points)!.icon }} {{ loyaltyTier(c.loyalty_points)!.label }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-3.5 font-mono text-slate-400">
                                    <span v-if="c.last_order_at && c.last_order_at !== 'Chưa có'" class="text-slate-600 dark:text-slate-300">{{ c.last_order_at }}</span>
                                    <span v-else class="text-slate-300 dark:text-slate-600 italic">Chưa có</span>
                                </td>
                                <td class="p-3.5 font-mono text-slate-500">{{ c.created_at }}</td>
                                <td class="p-3.5 text-right">
                                    <button
                                        @click="openEditModal(c)"
                                        class="p-1.5 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-950/40 text-indigo-600 transition-colors"
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
                <div v-if="totalPages > 1" class="flex items-center justify-between border-t p-4 bg-slate-50/50 dark:bg-slate-900/30">
                    <div class="text-xs text-muted-foreground">
                        Hiển thị {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, displayedCustomers.length) }} trong tổng số {{ displayedCustomers.length }} khách hàng
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
                            :class="['h-8 w-8 text-xs p-0', currentPage === page ? 'bg-indigo-650 text-white font-semibold hover:bg-indigo-700' : '']"
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

        <!-- MODAL: ADD CUSTOMER -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                    <div>
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                            <UserCheck class="size-5" />
                            Đăng Ký Khách Hàng CRM Mới
                        </CardTitle>
                        <CardDescription>Khai báo hồ sơ khách hàng để tích lũy điểm thưởng và phục vụ remarketing.</CardDescription>
                    </div>
                    <button @click="showAddModal = false" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground">
                        <X class="size-4" />
                    </button>
                </CardHeader>

                <CardContent class="pt-4 space-y-4">
                    <form @submit.prevent="submitAdd" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="cust-name">Họ và tên khách hàng <span class="text-rose-500">*</span></Label>
                            <Input id="cust-name" v-model="form.full_name" placeholder="Nguyễn Văn A..." required />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="cust-phone">Số điện thoại liên lạc <span class="text-rose-500">*</span></Label>
                                <Input id="cust-phone" v-model="form.phone" placeholder="090..." required />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="cust-email">Địa chỉ Email</Label>
                                <Input id="cust-email" type="email" v-model="form.email" placeholder="example@..." />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="cust-gender">Giới tính thành viên</Label>
                                <select 
                                    id="cust-gender"
                                    v-model="form.gender"
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                >
                                    <option value="male">Nam</option>
                                    <option value="female">Nữ</option>
                                    <option value="other">Khác</option>
                                </select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="cust-dob">Ngày sinh nhật</Label>
                                <Input id="cust-dob" type="date" v-model="form.date_of_birth" />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="cust-notes">Ghi chú sở thích/thói quen ăn uống</Label>
                            <textarea 
                                id="cust-notes"
                                v-model="form.notes" 
                                rows="3" 
                                placeholder="Ghi chú sở thích ăn uống của khách (ít cay, nhiều hành...)"
                                class="w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            />
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t">
                            <Button type="button" variant="outline" size="sm" @click="showAddModal = false">Hủy</Button>
                            <Button 
                                type="submit" 
                                size="sm" 
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? 'Đang lưu...' : 'Thêm khách hàng' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- MODAL: EDIT CUSTOMER -->
        <div v-if="editingCustomer" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                    <div>
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                            <Pencil class="size-4" />
                            Chỉnh Sửa Hồ Sơ Khách Hàng
                        </CardTitle>
                        <CardDescription>Cập nhật lại thông tin cá nhân của khách hàng trên hệ thống CRM.</CardDescription>
                    </div>
                    <button @click="editingCustomer = null" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground">
                        <X class="size-4" />
                    </button>
                </CardHeader>

                <CardContent class="pt-4 space-y-4">
                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label>Họ và tên khách hàng <span class="text-rose-500">*</span></Label>
                            <Input v-model="editForm.full_name" required />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label>Số điện thoại liên lạc <span class="text-rose-500">*</span></Label>
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
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                >
                                    <option value="male">Nam</option>
                                    <option value="female">Nữ</option>
                                    <option value="other">Khác</option>
                                </select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Ngày sinh nhật</Label>
                                <Input type="date" v-model="editForm.date_of_birth" />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label>Ghi chú sở thích/thói quen ăn uống</Label>
                            <textarea 
                                v-model="editForm.notes" 
                                rows="3" 
                                class="w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            />
                        </div>

                        <div class="p-3 bg-amber-50/50 dark:bg-amber-950/20 border border-amber-100 rounded-xl flex items-start gap-2 text-[10px] text-amber-700 dark:text-amber-400">
                            <AlertCircle class="size-4 shrink-0 text-amber-600 mt-0.5" />
                            <p><strong>Lưu ý bảo mật:</strong> Mọi chỉnh sửa dữ liệu khách hàng sẽ được ghi nhận và lưu vết đầy đủ trong lịch sử hoạt động để phòng chống rủi ro giả mạo thông tin điểm thưởng.</p>
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t">
                            <Button type="button" variant="outline" size="sm" @click="editingCustomer = null">Hủy</Button>
                            <Button 
                                type="submit" 
                                size="sm" 
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold"
                                :disabled="editForm.processing"
                            >
                                {{ editForm.processing ? 'Đang lưu...' : 'Lưu hồ sơ' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
