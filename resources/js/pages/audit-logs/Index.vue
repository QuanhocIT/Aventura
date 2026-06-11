<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ScrollText,
    Search,
    ChevronDown,
    ChevronUp,
    Filter,
    RefreshCw,
    User,
    Clock,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
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
    filters: {
        action?: string;
        user_role?: string;
        from?: string;
        to?: string;
    };
    total: number;
}>();

// ── Filters ───────────────────────────────────────────────────────────────────
const actionFilter = ref(props.filters.action ?? '');
const roleFilter = ref(props.filters.user_role ?? '');
const fromFilter = ref(props.filters.from ?? '');
const toFilter = ref(props.filters.to ?? '');

let debounce: ReturnType<typeof setTimeout>;
watch(actionFilter, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilter, 400);
});

function applyFilter() {
    router.get(
        '/audit-logs',
        {
            action: actionFilter.value || undefined,
            user_role: roleFilter.value || undefined,
            from: fromFilter.value || undefined,
            to: toFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function resetFilters() {
    actionFilter.value = '';
    roleFilter.value = '';
    fromFilter.value = '';
    toFilter.value = '';
    applyFilter();
}

// ── Expand row ────────────────────────────────────────────────────────────────
const expandedId = ref<number | null>(null);
const toggle = (id: number) => {
    expandedId.value = expandedId.value === id ? null : id;
};

// ── Display helpers ───────────────────────────────────────────────────────────
const eventConfig: Record<string, { label: string; cls: string; icon: any }> = {
    created: {
        label: 'Tạo mới',
        cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400',
        icon: Plus,
    },
    updated: {
        label: 'Cập nhật',
        cls: 'bg-blue-100 text-blue-700 dark:bg-blue-950/30 dark:text-blue-400',
        icon: Pencil,
    },
    deleted: {
        label: 'Xóa',
        cls: 'bg-rose-100 text-rose-700 dark:bg-rose-950/30 dark:text-rose-400',
        icon: Trash2,
    },
};

const roleLabel: Record<string, string> = {
    owner: 'Chủ',
    manager: 'QL',
    cashier: 'Thu ngân',
    kitchen: 'Bếp',
    inventory_staff: 'Kho',
};

function actionLabel(action: string): string {
    const map: Record<string, string> = {
        order_created: 'Tạo đơn hàng',
        order_updated: 'Cập nhật đơn',
        order_cancelled: 'Huỷ đơn hàng',
        order_split: 'Tách đơn hàng',
        order_split_override: 'Xác nhận tách đơn',
        price_modified: 'Sửa đơn giá',
        discount_applied: 'Áp dụng giảm giá',
        violation_reported: 'Báo cáo vi phạm',
        violation_resolved: 'Xử lý vi phạm',
        test_data_seeded: 'Seed dữ liệu test',
        seed_demo_order: 'Seed đơn demo',
    };

    return map[action] ?? action;
}

function formatJson(obj: Record<string, any> | null): string {
    if (!obj) {
        return '—';
    }

    return JSON.stringify(obj, null, 2);
}
</script>

<template>
    <Head title="Nhật Ký Hoạt Động" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-600 shadow-sm dark:bg-slate-800 dark:text-slate-400"
                >
                    <ScrollText class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Nhật Ký Hoạt Động
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Lịch sử toàn bộ hành động của nhân viên — minh bạch &
                        không thể chỉnh sửa.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="rounded-full border bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-500 dark:bg-slate-800"
                >
                    {{ total.toLocaleString('vi-VN') }} bản ghi
                </span>
            </div>
        </div>

        <!-- Filters -->
        <Card class="rounded-2xl border-slate-200 dark:border-slate-800">
            <CardHeader class="border-b pb-3">
                <CardTitle class="flex items-center gap-1.5 text-sm font-bold">
                    <Filter class="size-4 text-slate-500" />
                    Bộ lọc tìm kiếm
                </CardTitle>
            </CardHeader>
            <CardContent class="p-4">
                <div
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <!-- Action search -->
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-2.5 size-3.5 text-slate-400"
                        />
                        <Input
                            v-model="actionFilter"
                            placeholder="Tìm theo hành động..."
                            class="h-9 pl-8 text-xs"
                        />
                    </div>

                    <!-- Role filter -->
                    <select
                        v-model="roleFilter"
                        @change="applyFilter"
                        class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs focus:ring-2 focus:ring-indigo-400/40 focus:outline-none dark:border-slate-700 dark:bg-slate-800"
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
                        class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs focus:ring-2 focus:ring-indigo-400/40 focus:outline-none dark:border-slate-700 dark:bg-slate-800"
                        placeholder="Từ ngày"
                    />

                    <!-- Date to -->
                    <div class="flex items-center gap-2">
                        <input
                            v-model="toFilter"
                            type="date"
                            @change="applyFilter"
                            class="h-9 flex-1 rounded-lg border border-slate-200 bg-white px-3 text-xs focus:ring-2 focus:ring-indigo-400/40 focus:outline-none dark:border-slate-700 dark:bg-slate-800"
                            placeholder="Đến ngày"
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            @click="resetFilters"
                            class="h-9 px-3 text-xs"
                        >
                            <RefreshCw class="size-3.5" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Log list -->
        <Card
            class="overflow-hidden rounded-2xl border-slate-200 dark:border-slate-800"
        >
            <!-- Empty state -->
            <div
                v-if="logs.data.length === 0"
                class="flex flex-col items-center justify-center gap-3 py-20 text-slate-400"
            >
                <div class="rounded-2xl bg-slate-100 p-4 dark:bg-slate-800">
                    <ScrollText class="size-10 opacity-40" />
                </div>
                <p
                    class="text-sm font-semibold text-slate-600 dark:text-slate-300"
                >
                    Không tìm thấy bản ghi nào
                </p>
                <p class="text-xs text-slate-400">
                    Thử thay đổi bộ lọc hoặc khoảng thời gian
                </p>
            </div>

            <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                <div v-for="log in logs.data" :key="log.id">
                    <!-- Row -->
                    <div
                        class="flex cursor-pointer items-center gap-3 px-4 py-3 transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/20"
                        @click="toggle(log.id)"
                    >
                        <!-- Event badge -->
                        <div
                            :class="[
                                'inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold',
                                eventConfig[log.event]?.cls ??
                                    'bg-slate-100 text-slate-600',
                            ]"
                        >
                            <component
                                :is="eventConfig[log.event]?.icon ?? Clock"
                                class="size-2.5"
                            />
                            {{ eventConfig[log.event]?.label ?? log.event }}
                        </div>

                        <!-- Action + subject -->
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-xs font-semibold text-slate-800 dark:text-slate-200"
                            >
                                {{ actionLabel(log.action) }}
                                <span
                                    v-if="log.subject_type"
                                    class="font-normal text-slate-400"
                                >
                                    · {{ log.subject_type }}
                                    <span v-if="log.subject_id"
                                        >#{{ log.subject_id }}</span
                                    >
                                </span>
                            </p>
                            <div
                                class="mt-0.5 flex flex-wrap items-center gap-2"
                            >
                                <span
                                    class="flex items-center gap-1 text-[10px] text-slate-500"
                                >
                                    <User class="size-3" />
                                    {{ log.user_name }}
                                </span>
                                <span
                                    v-if="log.user_role"
                                    class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-500 dark:bg-slate-800"
                                >
                                    {{
                                        roleLabel[log.user_role] ??
                                        log.user_role
                                    }}
                                </span>
                                <span
                                    class="flex items-center gap-1 text-[10px] text-slate-400"
                                >
                                    <Clock class="size-3" />
                                    {{ log.created_at }}
                                </span>
                                <span
                                    v-if="log.ip_address"
                                    class="font-mono text-[9px] text-slate-400"
                                >
                                    IP: {{ log.ip_address }}
                                </span>
                            </div>
                        </div>

                        <!-- Expand toggle -->
                        <button class="shrink-0 text-slate-400">
                            <ChevronDown
                                v-if="expandedId !== log.id"
                                class="size-4"
                            />
                            <ChevronUp v-else class="size-4" />
                        </button>
                    </div>

                    <!-- Expanded: show old/new values -->
                    <div
                        v-if="
                            expandedId === log.id &&
                            (log.old_values || log.new_values)
                        "
                        class="border-t border-slate-100 bg-slate-50 px-4 pt-2 pb-4 dark:border-slate-800 dark:bg-slate-900/30"
                    >
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <!-- Old values -->
                            <div
                                v-if="log.old_values"
                                class="overflow-hidden rounded-xl border border-rose-100 dark:border-rose-900/30"
                            >
                                <div
                                    class="border-b border-rose-100 bg-rose-50 px-3 py-1.5 text-[10px] font-bold tracking-wider text-rose-600 uppercase dark:border-rose-900/30 dark:bg-rose-950/20 dark:text-rose-400"
                                >
                                    Trước khi thay đổi
                                </div>
                                <pre
                                    class="overflow-x-auto p-3 font-mono text-[10px] break-words whitespace-pre-wrap text-slate-600 dark:text-slate-300"
                                    >{{ formatJson(log.old_values) }}</pre
                                >
                            </div>
                            <!-- New values -->
                            <div
                                v-if="log.new_values"
                                class="overflow-hidden rounded-xl border border-emerald-100 dark:border-emerald-900/30"
                            >
                                <div
                                    class="border-b border-emerald-100 bg-emerald-50 px-3 py-1.5 text-[10px] font-bold tracking-wider text-emerald-600 uppercase dark:border-emerald-900/30 dark:bg-emerald-950/20 dark:text-emerald-400"
                                >
                                    Sau khi thay đổi
                                </div>
                                <pre
                                    class="overflow-x-auto p-3 font-mono text-[10px] break-words whitespace-pre-wrap text-slate-600 dark:text-slate-300"
                                    >{{ formatJson(log.new_values) }}</pre
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Card>

        <!-- Pagination -->
        <div
            v-if="logs.meta && logs.meta.last_page > 1"
            class="flex flex-wrap items-center justify-center gap-1"
        >
            <component
                :is="link.url ? 'a' : 'span'"
                v-for="(link, i) in logs.links"
                :key="i"
                :href="link.url ?? undefined"
                v-html="link.label"
                @click.prevent="link.url && router.visit(link.url)"
                :class="[
                    'cursor-pointer rounded-lg border px-3 py-1.5 text-xs transition-colors',
                    link.active
                        ? 'border-indigo-600 bg-indigo-600 text-white'
                        : link.url
                          ? 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                          : 'cursor-default border-slate-100 bg-slate-50 text-slate-300 dark:bg-slate-900 dark:text-slate-600',
                ]"
            />
        </div>
    </div>
</template>
