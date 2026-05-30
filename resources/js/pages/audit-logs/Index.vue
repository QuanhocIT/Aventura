<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ScrollText, Search, ChevronDown, ChevronUp, Filter,
    RefreshCw, User, Clock, Pencil, Plus, Trash2,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
};

const props = defineProps<{
    logs: { data: LogEntry[]; links: any[]; meta: any };
    filters: { action?: string; user_role?: string; from?: string; to?: string };
    total: number;
}>();

// ── Filters ───────────────────────────────────────────────────────────────────
const actionFilter   = ref(props.filters.action   ?? '');
const roleFilter     = ref(props.filters.user_role ?? '');
const fromFilter     = ref(props.filters.from      ?? '');
const toFilter       = ref(props.filters.to        ?? '');

let debounce: ReturnType<typeof setTimeout>;
watch(actionFilter, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilter, 400);
});

function applyFilter() {
    router.get('/audit-logs', {
        action:    actionFilter.value  || undefined,
        user_role: roleFilter.value    || undefined,
        from:      fromFilter.value    || undefined,
        to:        toFilter.value      || undefined,
    }, { preserveState: true, replace: true });
}

function resetFilters() {
    actionFilter.value = '';
    roleFilter.value   = '';
    fromFilter.value   = '';
    toFilter.value     = '';
    applyFilter();
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

function formatJson(obj: Record<string, any> | null): string {
    if (!obj) return '—';
    return JSON.stringify(obj, null, 2);
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
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- Action search -->
                    <div class="relative">
                        <Search class="absolute left-2.5 top-2.5 size-3.5 text-slate-400" />
                        <Input
                            v-model="actionFilter"
                            placeholder="Tìm theo hành động..."
                            class="pl-8 h-9 text-xs"
                        />
                    </div>

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
                        <Button variant="outline" size="sm" @click="resetFilters" class="h-9 px-3 text-xs">
                            <RefreshCw class="size-3.5" />
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
                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">
                                {{ actionLabel(log.action) }}
                                <span v-if="log.subject_type" class="text-slate-400 font-normal">
                                    · {{ log.subject_type }}
                                    <span v-if="log.subject_id">#{{ log.subject_id }}</span>
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

                    <!-- Expanded: show old/new values -->
                    <div
                        v-if="expandedId === log.id && (log.old_values || log.new_values)"
                        class="px-4 pb-4 pt-2 bg-slate-50 dark:bg-slate-900/30 border-t border-slate-100 dark:border-slate-800"
                    >
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <!-- Old values -->
                            <div v-if="log.old_values" class="rounded-xl border border-rose-100 dark:border-rose-900/30 overflow-hidden">
                                <div class="px-3 py-1.5 bg-rose-50 dark:bg-rose-950/20 text-[10px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider border-b border-rose-100 dark:border-rose-900/30">
                                    Trước khi thay đổi
                                </div>
                                <pre class="text-[10px] font-mono text-slate-600 dark:text-slate-300 p-3 overflow-x-auto whitespace-pre-wrap break-words">{{ formatJson(log.old_values) }}</pre>
                            </div>
                            <!-- New values -->
                            <div v-if="log.new_values" class="rounded-xl border border-emerald-100 dark:border-emerald-900/30 overflow-hidden">
                                <div class="px-3 py-1.5 bg-emerald-50 dark:bg-emerald-950/20 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider border-b border-emerald-100 dark:border-emerald-900/30">
                                    Sau khi thay đổi
                                </div>
                                <pre class="text-[10px] font-mono text-slate-600 dark:text-slate-300 p-3 overflow-x-auto whitespace-pre-wrap break-words">{{ formatJson(log.new_values) }}</pre>
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
