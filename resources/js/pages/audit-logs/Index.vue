<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ScrollText, Search, ChevronDown, ChevronUp, Filter,
    RefreshCw, User, Clock, Pencil, Plus, Trash2, Download, AlertTriangle
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type LogEntry = {
    id: number;
    user_name: string;
    user_email: string | null;
    user_role: string;
    event: string;
    action: string;
    subject_type: string | null;
    subject_id: number | null;
    ip_address: string | null;
    old_values: Record<string, any> | null;
    new_values: Record<string, any> | null;
    created_at: string;
    violation?: {
        id: number;
        type: string;
        severity: string;
        description: string;
    } | null;
};

const props = defineProps<{
    logs: { data: LogEntry[]; links: any[]; meta: any };
    filters: { action?: string; event?: string; user_role?: string; from?: string; to?: string };
    total: number;
}>();

// ── Filters ───────────────────────────────────────────────────────────────────
const actionFilter   = ref(props.filters.action   ?? '');
const eventFilter    = ref(props.filters.event    ?? '');
const roleFilter     = ref(props.filters.user_role ?? '');
const fromFilter     = ref(props.filters.from      ?? '');
const toFilter       = ref(props.filters.to        ?? '');

function applyFilter() {
    router.get('/audit-logs', {
        action:    actionFilter.value  || undefined,
        event:     eventFilter.value   || undefined,
        user_role: roleFilter.value    || undefined,
        from:      fromFilter.value    || undefined,
        to:        toFilter.value      || undefined,
    }, { preserveState: true, replace: true });
}

function resetFilters() {
    actionFilter.value = '';
    eventFilter.value  = '';
    roleFilter.value   = '';
    fromFilter.value   = '';
    toFilter.value     = '';
    applyFilter();
}

function setDateRange(range: 'today' | 'yesterday' | '7days' | 'month') {
    const today = new Date();
    const formatDate = (date: Date) => {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    };

    if (range === 'today') {
        fromFilter.value = formatDate(today);
        toFilter.value = formatDate(today);
    } else if (range === 'yesterday') {
        const yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);
        fromFilter.value = formatDate(yesterday);
        toFilter.value = formatDate(yesterday);
    } else if (range === '7days') {
        const last7 = new Date();
        last7.setDate(today.getDate() - 7);
        fromFilter.value = formatDate(last7);
        toFilter.value = formatDate(today);
    } else if (range === 'month') {
        const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        fromFilter.value = formatDate(startOfMonth);
        toFilter.value = formatDate(today);
    }
    applyFilter();
}

function exportCsv() {
    const params = new URLSearchParams();
    if (actionFilter.value) params.append('action', actionFilter.value);
    if (eventFilter.value) params.append('event', eventFilter.value);
    if (roleFilter.value) params.append('user_role', roleFilter.value);
    if (fromFilter.value) params.append('from', fromFilter.value);
    if (toFilter.value) params.append('to', toFilter.value);
    
    window.location.href = `/audit-logs/export?${params.toString()}`;
}

function seedDemo() {
    router.post('/audit-logs/seed-demo', {}, {
        onSuccess: () => {
            // Re-fetch triggers update automatically
        }
    });
}

// ── Expand row ────────────────────────────────────────────────────────────────
const expandedId = ref<number | null>(null);
const toggle = (id: number) => { expandedId.value = expandedId.value === id ? null : id; };

// ── Display helpers ───────────────────────────────────────────────────────────
const eventConfig: Record<string, { label: string; cls: string; icon: any }> = {
    created: { label: 'Tạo mới',    cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400', icon: Plus },
    updated: { label: 'Cập nhật',   cls: 'bg-blue-100 text-blue-700 dark:bg-blue-950/30 dark:text-blue-400',           icon: Pencil },
    deleted: { label: 'Xóa',        cls: 'bg-rose-100 text-rose-700 dark:bg-rose-950/30 dark:text-rose-400',           icon: Trash2 },
};

const roleLabel: Record<string, string> = {
    owner: 'Chủ', manager: 'QL', cashier: 'Thu ngân', kitchen: 'Bếp', inventory_staff: 'Kho',
};

function actionLabel(action: string): string {
    const map: Record<string, string> = {
        order_created:        'Tạo đơn hàng',
        order_updated:        'Cập nhật đơn',
        order_cancelled:      'Huỷ đơn hàng',
        order_split:          'Tách đơn hàng',
        order_split_override: 'Xác nhận tách đơn',
        price_modified:       'Sửa đơn giá',
        discount_applied:     'Áp dụng giảm giá',
        violation_reported:   'Báo cáo vi phạm',
        violation_resolved:   'Xử lý vi phạm',
        test_data_seeded:     'Seed dữ liệu test',
        seed_demo_order:      'Seed đơn demo',
    };
    return map[action] ?? action;
}

const fieldLabels: Record<string, string> = {
    status: 'Trạng thái',
    total_amount: 'Tổng tiền',
    subtotal_amount: 'Tiền trước thuế',
    discount_amount: 'Tiền giảm giá',
    price: 'Giá món',
    quantity: 'Số lượng',
    notes: 'Ghi chú',
    split_from_order_id: 'Tách từ đơn hàng #',
};

function getDiff(oldVal: Record<string, any> | null, newVal: Record<string, any> | null) {
    const oldV = oldVal || {};
    const newV = newVal || {};
    const allKeys = Array.from(new Set([...Object.keys(oldV), ...Object.keys(newV)]));

    return allKeys.map(key => {
        const oVal = oldV[key];
        const nVal = newV[key];
        const isChanged = JSON.stringify(oVal) !== JSON.stringify(nVal);

        return {
            key,
            label: fieldLabels[key] ?? key,
            oldVal: oVal,
            newVal: nVal,
            isChanged
        };
    }).filter(item => item.isChanged);
}
</script>

<template>
    <Head title="Nhật Ký Hoạt Động" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">

        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 shadow-sm">
                    <ScrollText class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Nhật Ký Hoạt Động</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Lịch sử toàn bộ hành động của nhân viên — minh bạch & không thể chỉnh sửa.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-500 bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-full border">
                    {{ total.toLocaleString('vi-VN') }} bản ghi
                </span>
            </div>
        </div>

        <!-- Filters -->
        <Card class="rounded-2xl border-slate-200 dark:border-slate-800">
            <CardHeader class="pb-3 border-b">
                <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                    <Filter class="size-4 text-slate-500" />
                    Bộ lọc tìm kiếm
                </CardTitle>
            </CardHeader>
            <CardContent class="p-4">
                <div class="flex flex-col gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <!-- Action filter select -->
                        <select
                            v-model="actionFilter"
                            @change="applyFilter"
                            class="h-9 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 focus:outline-none focus:ring-2 focus:ring-indigo-400/40"
                        >
                            <option value="">Tất cả hành động</option>
                            <option value="order_created">Tạo đơn hàng</option>
                            <option value="order_updated">Cập nhật đơn</option>
                            <option value="order_cancelled">Hủy đơn hàng</option>
                            <option value="order_split">Tách đơn hàng</option>
                            <option value="order_split_override">Xác nhận tách đơn</option>
                            <option value="price_modified">Sửa đơn giá</option>
                            <option value="discount_applied">Áp dụng giảm giá</option>
                            <option value="violation_reported">Báo cáo vi phạm</option>
                            <option value="violation_resolved">Xử lý vi phạm</option>
                            <option value="test_data_seeded">Seed dữ liệu test</option>
                            <option value="seed_demo_order">Seed đơn demo</option>
                        </select>

                        <!-- Event filter select -->
                        <select
                            v-model="eventFilter"
                            @change="applyFilter"
                            class="h-9 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 focus:outline-none focus:ring-2 focus:ring-indigo-400/40"
                        >
                            <option value="">Tất cả sự kiện</option>
                            <option value="created">Tạo mới</option>
                            <option value="updated">Cập nhật</option>
                            <option value="deleted">Xóa</option>
                        </select>

                        <!-- Role filter -->
                        <select
                            v-model="roleFilter"
                            @change="applyFilter"
                            class="h-9 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 focus:outline-none focus:ring-2 focus:ring-indigo-400/40"
                        >
                            <option value="">Tất cả vai trò</option>
                            <option value="owner">Chủ nhà hàng</option>
                            <option value="manager">Quản lý</option>
                            <option value="cashier">Thu ngân</option>
                            <option value="kitchen">Nhân viên bếp</option>
                            <option value="inventory_staff">Quản lý kho</option>
                        </select>

                        <!-- Date from -->
                        <input
                            v-model="fromFilter"
                            type="date"
                            @change="applyFilter"
                            class="h-9 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 focus:outline-none focus:ring-2 focus:ring-indigo-400/40"
                            placeholder="Từ ngày"
                        />

                        <!-- Date to -->
                        <div class="flex items-center gap-2">
                            <input
                                v-model="toFilter"
                                type="date"
                                @change="applyFilter"
                                class="h-9 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 focus:outline-none focus:ring-2 focus:ring-indigo-400/40 flex-1"
                                placeholder="Đến ngày"
                            />
                            <Button variant="outline" size="sm" @click="resetFilters" class="h-9 px-3 text-xs" title="Đặt lại bộ lọc">
                                <RefreshCw class="size-3.5" />
                            </Button>
                        </div>
                    </div>

                    <!-- Date range shortcuts & Export -->
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t pt-3 mt-1">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-[10px] font-bold text-slate-400 mr-1 uppercase">Lọc nhanh ngày:</span>
                            <Button variant="ghost" size="sm" @click="setDateRange('today')" class="h-7 px-2.5 text-[10px] font-bold hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300">
                                Hôm nay
                            </Button>
                            <Button variant="ghost" size="sm" @click="setDateRange('yesterday')" class="h-7 px-2.5 text-[10px] font-bold hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300">
                                Hôm qua
                            </Button>
                            <Button variant="ghost" size="sm" @click="setDateRange('7days')" class="h-7 px-2.5 text-[10px] font-bold hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300">
                                7 ngày qua
                            </Button>
                            <Button variant="ghost" size="sm" @click="setDateRange('month')" class="h-7 px-2.5 text-[10px] font-bold hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300">
                                Tháng này
                            </Button>
                        </div>
                        
                        <Button @click="exportCsv" variant="outline" size="sm" class="h-8 gap-1.5 text-xs bg-indigo-50/50 hover:bg-indigo-50 border-indigo-100 dark:border-indigo-950 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 font-semibold shadow-none">
                            <Download class="size-3.5" />
                            Xuất CSV
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Log list -->
        <Card class="rounded-2xl border-slate-200 dark:border-slate-800 overflow-hidden">

            <!-- Empty state -->
            <div v-if="logs.data.length === 0" class="flex flex-col items-center justify-center py-20 gap-3 text-slate-400">
                <div class="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800">
                    <ScrollText class="size-10 opacity-40" />
                </div>
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Không tìm thấy bản ghi nào</p>
                <p class="text-xs text-slate-400">Thử thay đổi bộ lọc hoặc khoảng thời gian</p>
                
                <Button 
                    @click="seedDemo" 
                    variant="outline" 
                    size="sm" 
                    class="mt-2 text-xs font-semibold gap-1 border-indigo-200 text-indigo-600 dark:border-indigo-900/50 dark:text-indigo-400 hover:bg-indigo-50/50"
                >
                    <Plus class="size-3.5" />
                    Khởi tạo dữ liệu mẫu
                </Button>
            </div>

            <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                <div v-for="log in logs.data" :key="log.id">
                    <!-- Row -->
                    <div
                        class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50/50 dark:hover:bg-slate-900/20 cursor-pointer transition-colors"
                        @click="toggle(log.id)"
                    >
                        <!-- Event badge -->
                        <div :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0', eventConfig[log.event]?.cls ?? 'bg-slate-100 text-slate-600']">
                            <component :is="eventConfig[log.event]?.icon ?? Clock" class="size-2.5" />
                            {{ eventConfig[log.event]?.label ?? log.event }}
                        </div>

                        <!-- Action + subject -->
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate flex items-center gap-1.5 flex-wrap">
                                {{ actionLabel(log.action) }}
                                <span v-if="log.subject_type" class="text-slate-400 font-normal">
                                    · {{ log.subject_type }}
                                    <span v-if="log.subject_id">#{{ log.subject_id }}</span>
                                </span>

                                <!-- AI Alert Badge -->
                                <span 
                                    v-if="log.violation" 
                                    class="inline-flex items-center gap-1 bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400 text-[9px] font-bold px-1.5 py-0.5 rounded border border-red-100 dark:border-red-900/30 animate-pulse cursor-help"
                                    :title="log.violation.description"
                                >
                                    <AlertTriangle class="size-2.5 text-red-500 shrink-0" />
                                    AI: Rủi ro
                                </span>
                            </p>
                            <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                <span class="flex items-center gap-1 text-[10px] text-slate-500">
                                    <User class="size-3" />
                                    {{ log.user_name }}
                                </span>
                                <span v-if="log.user_role" class="text-[9px] bg-slate-100 dark:bg-slate-800 text-slate-500 px-1.5 py-0.5 rounded font-bold">
                                    {{ roleLabel[log.user_role] ?? log.user_role }}
                                </span>
                                <span class="flex items-center gap-1 text-[10px] text-slate-400">
                                    <Clock class="size-3" />
                                    {{ log.created_at }}
                                </span>
                                <span v-if="log.ip_address" class="text-[9px] font-mono text-slate-400">
                                    IP: {{ log.ip_address }}
                                </span>
                            </div>
                        </div>

                        <!-- Expand toggle -->
                        <button class="text-slate-400 shrink-0">
                            <ChevronDown v-if="expandedId !== log.id" class="size-4" />
                            <ChevronUp  v-else                        class="size-4" />
                        </button>
                    </div>

                    <!-- Expanded: show Visual Diff and AI Risk warnings -->
                    <div
                        v-if="expandedId === log.id"
                        class="px-4 pb-4 pt-3 bg-slate-50 dark:bg-slate-900/30 border-t border-slate-100 dark:border-slate-800"
                    >
                        <div class="flex flex-col gap-3 max-w-4xl">
                            <!-- AI Anomaly Alert Box -->
                            <div 
                                v-if="log.violation" 
                                class="flex gap-2.5 items-start p-3 bg-red-50/50 dark:bg-red-950/10 border border-red-100 dark:border-red-900/20 rounded-xl text-xs"
                            >
                                <AlertTriangle class="size-4 text-red-500 mt-0.5 shrink-0" />
                                <div>
                                    <p class="font-bold text-red-700 dark:text-red-400">Cảnh báo rủi ro gian lận (AI Anomaly)</p>
                                    <p class="text-red-600/90 dark:text-red-300/90 mt-0.5 leading-relaxed">{{ log.violation.description }}</p>
                                    <a 
                                        href="/violations" 
                                        class="inline-block mt-2 text-[10px] font-bold text-red-700 hover:text-red-800 dark:text-red-400 underline transition-colors"
                                    >
                                        Đến trang Quản lý vi phạm để xử lý →
                                    </a>
                                </div>
                            </div>

                            <!-- Visual Diff comparison -->
                            <div v-if="log.old_values || log.new_values">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                                    Chi tiết thay đổi dữ liệu
                                </p>
                                <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                                        <thead class="bg-slate-50/50 dark:bg-slate-900/40">
                                            <tr class="text-left font-bold text-slate-500">
                                                <th class="px-4 py-2 font-medium">Trường thay đổi</th>
                                                <th class="px-4 py-2 font-medium">Trước (Cũ)</th>
                                                <th class="px-4 py-2 font-medium">Sau (Mới)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                            <tr v-for="item in getDiff(log.old_values, log.new_values)" :key="item.key">
                                                <td class="px-4 py-2 font-semibold text-slate-700 dark:text-slate-300">
                                                    {{ item.label }}
                                                </td>
                                                <td class="px-4 py-2 bg-rose-50/30 dark:bg-rose-950/10 text-rose-600 dark:text-rose-400 line-through font-mono">
                                                    {{ item.oldVal !== undefined ? (typeof item.oldVal === 'object' ? JSON.stringify(item.oldVal) : item.oldVal) : '—' }}
                                                </td>
                                                <td class="px-4 py-2 bg-emerald-50/30 dark:bg-emerald-950/10 text-emerald-600 dark:text-emerald-400 font-bold font-mono">
                                                    {{ item.newVal !== undefined ? (typeof item.newVal === 'object' ? JSON.stringify(item.newVal) : item.newVal) : '—' }}
                                                </td>
                                            </tr>
                                            <tr v-if="getDiff(log.old_values, log.new_values).length === 0">
                                                <td colspan="3" class="px-4 py-3 text-center text-slate-400 italic">
                                                    Không phát hiện thuộc tính cụ thể nào bị sửa đổi
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-400 italic">
                                Không có chi tiết thay đổi thuộc tính nào cho sự kiện này.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Card>

        <!-- Pagination -->
        <div v-if="logs.meta && logs.meta.last_page > 1" class="flex items-center justify-center gap-1 flex-wrap">
            <component
                :is="link.url ? 'a' : 'span'"
                v-for="(link, i) in logs.links"
                :key="i"
                :href="link.url ?? undefined"
                v-html="link.label"
                @click.prevent="link.url && router.visit(link.url)"
                :class="[
                    'px-3 py-1.5 text-xs rounded-lg border transition-colors cursor-pointer',
                    link.active
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : link.url
                            ? 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-50'
                            : 'bg-slate-50 dark:bg-slate-900 text-slate-300 dark:text-slate-600 border-slate-100 cursor-default'
                ]"
            />
        </div>
    </div>
</template>
