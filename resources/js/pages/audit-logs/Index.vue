<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ScrollText,
    ChevronDown,
    ChevronUp,
    Filter,
    Shield,
    RefreshCw,
    User,
    Clock,
    Pencil,
    Plus,
    Trash2,
    Download,
    AlertTriangle,
    ArrowRight,
    Activity,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Pagination } from '@/components/super-admin';
import { Badge } from '@/components/ui/badge';
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
    filters: {
        action?: string;
        event?: string;
        user_role?: string;
        from?: string;
        to?: string;
    };
    total: number;
}>();

// ── Filters ───────────────────────────────────────────────────────────────────
const actionFilter = ref(props.filters.action ?? '');
const eventFilter = ref(props.filters.event ?? '');
const roleFilter = ref(props.filters.user_role ?? '');
const fromFilter = ref(props.filters.from ?? '');
const toFilter = ref(props.filters.to ?? '');

function applyFilter() {
    router.get(
        '/audit-logs',
        {
            action: actionFilter.value || undefined,
            event: eventFilter.value || undefined,
            user_role: roleFilter.value || undefined,
            from: fromFilter.value || undefined,
            to: toFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function resetFilters() {
    actionFilter.value = '';
    eventFilter.value = '';
    roleFilter.value = '';
    fromFilter.value = '';
    toFilter.value = '';
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

    if (actionFilter.value) {
        params.append('action', actionFilter.value);
    }

    if (eventFilter.value) {
        params.append('event', eventFilter.value);
    }

    if (roleFilter.value) {
        params.append('user_role', roleFilter.value);
    }

    if (fromFilter.value) {
        params.append('from', fromFilter.value);
    }

    if (toFilter.value) {
        params.append('to', toFilter.value);
    }

    window.location.href = `/audit-logs/export?${params.toString()}`;
}

function seedDemo() {
    router.post(
        '/audit-logs/seed-demo',
        {},
        {
            onSuccess: () => {
                // Re-fetch triggers update automatically
            },
        },
    );
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
        cls: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20 dark:border-emerald-500/30',
        icon: Plus,
    },
    updated: {
        label: 'Cập nhật',
        cls: 'bg-blue-500/10 text-blue-500 border-blue-500/20 dark:border-blue-500/30',
        icon: Pencil,
    },
    deleted: {
        label: 'Xóa',
        cls: 'bg-rose-500/10 text-rose-500 border-rose-500/20 dark:border-rose-500/30',
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

function getDiff(
    oldVal: Record<string, any> | null,
    newVal: Record<string, any> | null,
) {
    const oldV = oldVal || {};
    const newV = newVal || {};
    const allKeys = Array.from(
        new Set([...Object.keys(oldV), ...Object.keys(newV)]),
    );

    return allKeys
        .map((key) => {
            const oVal = oldV[key];
            const nVal = newV[key];
            const isChanged = JSON.stringify(oVal) !== JSON.stringify(nVal);

            return {
                key,
                label: fieldLabels[key] ?? key,
                oldVal: oVal,
                newVal: nVal,
                isChanged,
            };
        })
        .filter((item) => item.isChanged);
}
</script>

<template>
    <Head title="Nhật Ký Hoạt Động" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div
            class="flex flex-col gap-4 border-b border-border/80 pb-6 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-600 shadow-sm ring-4 ring-slate-500/5 dark:bg-slate-800 dark:text-slate-400"
                >
                    <ScrollText class="size-6" />
                </div>
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-black tracking-tight text-foreground"
                    >
                        Nhật Ký Hoạt Động
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Lịch sử toàn bộ hành động của nhân viên — minh bạch,
                        chống gian lận & không thể chỉnh sửa.
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <span
                    class="rounded-xl border border-border bg-muted px-4 py-2 text-xs font-extrabold text-foreground shadow-xs"
                >
                    {{ total.toLocaleString('vi-VN') }} bản ghi
                </span>
            </div>
        </div>

        <!-- Filters -->
        <Card class="rounded-2xl border-border bg-card shadow-xs">
            <CardHeader class="border-b border-border/60 pb-3">
                <CardTitle
                    class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                >
                    <Filter class="size-4 text-slate-500" />
                    Bộ lọc tìm kiếm
                </CardTitle>
            </CardHeader>
            <CardContent class="p-5">
                <div class="flex flex-col gap-4">
                    <div
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5"
                    >
                        <!-- Action Select -->
                        <div class="relative">
                            <select
                                v-model="actionFilter"
                                @change="applyFilter"
                                class="h-9 w-full cursor-pointer appearance-none rounded-xl border border-border bg-card pr-8 pl-8 text-xs font-bold text-foreground focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
                            >
                                <option value="">Tất cả hành động</option>
                                <option value="order_created">
                                    Tạo đơn hàng
                                </option>
                                <option value="order_updated">
                                    Cập nhật đơn
                                </option>
                                <option value="order_cancelled">
                                    Hủy đơn hàng
                                </option>
                                <option value="order_split">
                                    Tách đơn hàng
                                </option>
                                <option value="order_split_override">
                                    Xác nhận tách đơn
                                </option>
                                <option value="price_modified">
                                    Sửa đơn giá
                                </option>
                                <option value="discount_applied">
                                    Áp dụng giảm giá
                                </option>
                                <option value="violation_reported">
                                    Báo cáo vi phạm
                                </option>
                                <option value="violation_resolved">
                                    Xử lý vi phạm
                                </option>
                                <option value="test_data_seeded">
                                    Seed dữ liệu test
                                </option>
                                <option value="seed_demo_order">
                                    Seed đơn demo
                                </option>
                            </select>
                            <Activity
                                class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <ChevronDown
                                class="pointer-events-none absolute top-1/2 right-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                            />
                        </div>

                        <!-- Event Select -->
                        <div class="relative">
                            <select
                                v-model="eventFilter"
                                @change="applyFilter"
                                class="h-9 w-full cursor-pointer appearance-none rounded-xl border border-border bg-card pr-8 pl-8 text-xs font-bold text-foreground focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
                            >
                                <option value="">Tất cả sự kiện</option>
                                <option value="created">Tạo mới</option>
                                <option value="updated">Cập nhật</option>
                                <option value="deleted">Xóa</option>
                            </select>
                            <Filter
                                class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <ChevronDown
                                class="pointer-events-none absolute top-1/2 right-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                            />
                        </div>

                        <!-- Role Select -->
                        <div class="relative">
                            <select
                                v-model="roleFilter"
                                @change="applyFilter"
                                class="h-9 w-full cursor-pointer appearance-none rounded-xl border border-border bg-card pr-8 pl-8 text-xs font-bold text-foreground focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
                            >
                                <option value="">Tất cả vai trò</option>
                                <option value="owner">Chủ nhà hàng</option>
                                <option value="manager">Quản lý</option>
                                <option value="cashier">Thu ngân</option>
                                <option value="kitchen">Nhân viên bếp</option>
                                <option value="inventory_staff">
                                    Quản lý kho
                                </option>
                            </select>
                            <Shield
                                class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <ChevronDown
                                class="pointer-events-none absolute top-1/2 right-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                            />
                        </div>

                        <!-- Date From -->
                        <div class="relative">
                            <input
                                v-model="fromFilter"
                                type="date"
                                @change="applyFilter"
                                class="h-9 w-full cursor-pointer rounded-xl border border-border bg-card pr-3 pl-8 text-xs font-bold text-foreground focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
                            />
                            <Clock
                                class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                        </div>

                        <!-- Date To & Reset -->
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <input
                                    v-model="toFilter"
                                    type="date"
                                    @change="applyFilter"
                                    class="h-9 w-full cursor-pointer rounded-xl border border-border bg-card pr-3 pl-8 text-xs font-bold text-foreground focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
                                />
                                <Clock
                                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                @click="resetFilters"
                                class="h-9 rounded-xl border border-border bg-card px-3 text-xs hover:bg-muted"
                                title="Đặt lại bộ lọc"
                            >
                                <RefreshCw class="size-3.5" />
                            </Button>
                        </div>
                    </div>

                    <!-- Date range shortcuts & Export -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-border/80 pt-4"
                    >
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span
                                class="mr-1.5 text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Lọc nhanh:</span
                            >
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="setDateRange('today')"
                                class="h-7 rounded-lg border border-border/40 px-3 text-[10px] font-bold text-foreground hover:bg-muted"
                            >
                                Hôm nay
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="setDateRange('yesterday')"
                                class="h-7 rounded-lg border border-border/40 px-3 text-[10px] font-bold text-foreground hover:bg-muted"
                            >
                                Hôm qua
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="setDateRange('7days')"
                                class="h-7 rounded-lg border border-border/40 px-3 text-[10px] font-bold text-foreground hover:bg-muted"
                            >
                                7 ngày qua
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="setDateRange('month')"
                                class="h-7 rounded-lg border border-border/40 px-3 text-[10px] font-bold text-foreground hover:bg-muted"
                            >
                                Tháng này
                            </Button>
                        </div>

                        <Button
                            @click="exportCsv"
                            variant="outline"
                            size="sm"
                            class="h-8 cursor-pointer gap-1.5 rounded-xl border-indigo-100 bg-indigo-50/50 text-xs font-bold text-indigo-600 shadow-xs hover:bg-indigo-50 dark:border-indigo-950 dark:bg-indigo-950/20 dark:text-indigo-400"
                        >
                            <Download class="size-3.5" />
                            Xuất báo cáo CSV
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Log List (Timeline Stream) -->
        <Card
            class="overflow-hidden rounded-2xl border-border bg-card shadow-xs"
        >
            <!-- Empty state -->
            <div
                v-if="logs.data.length === 0"
                class="m-5 flex flex-col items-center justify-center rounded-2xl border border-dashed border-border/80 bg-muted/5 px-4 py-20 text-center"
            >
                <div
                    class="relative mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-500 ring-8 ring-indigo-500/5"
                >
                    <ScrollText class="size-8" />
                    <!-- Scanning animation overlay -->
                    <div
                        class="absolute inset-0 animate-ping rounded-2xl border border-indigo-500 opacity-25"
                    ></div>
                </div>
                <h3 class="text-sm font-bold text-foreground">
                    Không tìm thấy bản ghi nhật ký nào
                </h3>
                <p class="mt-1.5 max-w-sm text-xs text-muted-foreground">
                    Hệ thống chưa ghi nhận hành động nào khớp với bộ lọc hiện
                    tại của bạn. Thử thay đổi khoảng thời gian hoặc bộ lọc sự
                    kiện.
                </p>
                <div class="mt-6 flex items-center gap-3">
                    <Button
                        @click="resetFilters"
                        variant="outline"
                        size="sm"
                        class="h-9 cursor-pointer rounded-xl text-xs font-bold"
                    >
                        <RefreshCw class="mr-1 size-3.5" /> Đặt lại bộ lọc
                    </Button>
                    <Button
                        @click="seedDemo"
                        variant="default"
                        size="sm"
                        class="h-9 cursor-pointer rounded-xl bg-indigo-600 text-xs font-bold text-white shadow-sm hover:bg-indigo-700"
                    >
                        <Plus class="mr-1 size-4" /> Khởi tạo dữ liệu mẫu
                    </Button>
                </div>
            </div>

            <!-- Timeline list -->
            <div v-else class="divide-y divide-border/60 py-3">
                <div
                    v-for="log in logs.data"
                    :key="log.id"
                    class="relative py-4 pr-5 pl-14 transition-all duration-200 hover:bg-muted/10"
                >
                    <!-- Timeline Vertical Axis line -->
                    <div
                        class="absolute top-0 bottom-0 left-7 w-0.5 bg-border/60"
                    ></div>

                    <!-- Timeline Node Dot -->
                    <div
                        :class="[
                            'absolute top-[18px] left-[18px] z-10 flex h-7 w-7 items-center justify-center rounded-full border transition-all duration-300',
                            log.event === 'created'
                                ? 'border-emerald-500 bg-emerald-500 text-white shadow-[0_0_8px_rgba(16,185,129,0.3)]'
                                : log.event === 'deleted'
                                  ? 'border-rose-500 bg-rose-500 text-white shadow-[0_0_8px_rgba(239,68,68,0.3)]'
                                  : 'border-blue-500 bg-blue-500 text-white shadow-[0_0_8px_rgba(59,130,246,0.3)]',
                        ]"
                    >
                        <!-- Custom icon inside the dot node -->
                        <component
                            :is="eventConfig[log.event]?.icon ?? Clock"
                            class="size-3.5 text-white"
                        />
                    </div>

                    <!-- Row Card Container -->
                    <div
                        @click="toggle(log.id)"
                        class="flex cursor-pointer items-center justify-between gap-4 select-none"
                    >
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-sm leading-snug font-bold text-foreground"
                                >
                                    {{ actionLabel(log.action) }}
                                </span>

                                <span
                                    v-if="log.subject_type"
                                    class="rounded border border-border/60 bg-muted px-2 py-0.5 text-[10px] font-bold text-muted-foreground"
                                >
                                    {{ log.subject_type }}
                                    <span
                                        v-if="log.subject_id"
                                        class="font-mono font-extrabold"
                                        >#{{ log.subject_id }}</span
                                    >
                                </span>

                                <!-- Event badge -->
                                <Badge
                                    :class="[
                                        eventConfig[log.event]?.cls ||
                                            'bg-slate-100',
                                        'rounded-full border-0 px-2.5 py-0.5 text-[9px] font-bold',
                                    ]"
                                >
                                    {{
                                        eventConfig[log.event]?.label ||
                                        log.event
                                    }}
                                </Badge>

                                <!-- AI Alert Warning Badge -->
                                <span
                                    v-if="log.violation"
                                    class="inline-flex animate-pulse cursor-help items-center gap-1 rounded-full border border-rose-500/20 bg-rose-500/10 px-2.5 py-0.5 text-[9px] font-bold text-rose-500 dark:border-rose-500/30"
                                    :title="log.violation.description"
                                >
                                    <AlertTriangle
                                        class="size-3 shrink-0 text-rose-500"
                                    />
                                    AI: Rủi ro gian lận
                                </span>
                            </div>

                            <!-- Metadata row -->
                            <div
                                class="flex flex-wrap items-center gap-3 text-[10px] font-semibold text-muted-foreground"
                            >
                                <span
                                    class="flex items-center gap-1 text-foreground/80"
                                >
                                    <User
                                        class="size-3.5 text-muted-foreground"
                                    />
                                    {{ log.user_name }}
                                    <span
                                        class="ml-1 shrink-0 rounded border border-border/80 bg-muted px-1.5 py-0.5 font-mono text-[9px] font-bold text-muted-foreground uppercase"
                                    >
                                        {{
                                            roleLabel[log.user_role] ??
                                            log.user_role
                                        }}
                                    </span>
                                </span>
                                <span>·</span>
                                <span class="flex items-center gap-1">
                                    <Clock class="size-3.5" />
                                    {{ log.created_at }}
                                </span>
                                <span
                                    v-if="log.ip_address"
                                    class="flex items-center gap-1 font-mono text-[9px]"
                                >
                                    IP: {{ log.ip_address }}
                                </span>
                            </div>
                        </div>

                        <!-- Action Button Toggle -->
                        <button
                            class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-border/60 text-muted-foreground hover:bg-muted"
                        >
                            <ChevronDown
                                v-if="expandedId !== log.id"
                                class="size-4 transition-transform duration-200"
                            />
                            <ChevronUp
                                v-else
                                class="size-4 transition-transform duration-200"
                            />
                        </button>
                    </div>

                    <!-- Expanded Diff Visual Area -->
                    <div
                        v-if="expandedId === log.id"
                        class="mt-4 max-w-5xl space-y-4 rounded-2xl border border-border/80 bg-muted/30 p-4"
                    >
                        <!-- AI Alert warning card -->
                        <div
                            v-if="log.violation"
                            class="flex items-start gap-3 rounded-xl border border-rose-500/20 bg-rose-500/5 p-4 text-xs dark:bg-rose-950/10"
                        >
                            <div
                                class="shrink-0 rounded-lg bg-rose-500/10 p-2 text-rose-500"
                            >
                                <AlertTriangle class="size-4" />
                            </div>
                            <div class="space-y-1">
                                <p
                                    class="font-extrabold text-rose-600 dark:text-rose-400"
                                >
                                    Cảnh báo rủi ro gian lận (AI Anomaly
                                    Detected)
                                </p>
                                <p
                                    class="text-[11px] leading-relaxed text-muted-foreground"
                                >
                                    {{ log.violation.description }}
                                </p>
                                <a
                                    href="/violations"
                                    class="mt-2 inline-flex items-center gap-1 text-[10px] font-bold text-rose-500 underline hover:text-rose-600"
                                >
                                    Đến trung tâm quản lý vi phạm xử lý
                                    <ArrowRight class="size-3" />
                                </a>
                            </div>
                        </div>

                        <!-- Diff details table -->
                        <div
                            v-if="log.old_values || log.new_values"
                            class="space-y-2"
                        >
                            <p
                                class="text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Chi tiết thay đổi dữ liệu (Data Diff)
                            </p>
                            <div
                                class="overflow-hidden rounded-xl border border-border bg-card shadow-xs"
                            >
                                <table
                                    class="min-w-full divide-y divide-border text-left text-[11px]"
                                >
                                    <thead class="bg-muted/40">
                                        <tr
                                            class="font-bold text-muted-foreground"
                                        >
                                            <th class="px-4 py-2.5 font-bold">
                                                Thuộc tính
                                            </th>
                                            <th class="px-4 py-2.5 font-bold">
                                                Giá trị trước
                                            </th>
                                            <th
                                                class="px-4 py-2.5 text-center font-bold"
                                            ></th>
                                            <th class="px-4 py-2.5 font-bold">
                                                Giá trị sau
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/60">
                                        <tr
                                            v-for="item in getDiff(
                                                log.old_values,
                                                log.new_values,
                                            )"
                                            :key="item.key"
                                            class="hover:bg-muted/5"
                                        >
                                            <td
                                                class="px-4 py-3 font-bold text-foreground"
                                            >
                                                {{ item.label }}
                                            </td>
                                            <!-- Old value column -->
                                            <td
                                                class="bg-rose-500/5 px-4 py-3 font-mono text-rose-500 line-through dark:bg-rose-950/10 dark:text-rose-400"
                                            >
                                                {{
                                                    item.oldVal !== undefined
                                                        ? typeof item.oldVal ===
                                                          'object'
                                                            ? JSON.stringify(
                                                                  item.oldVal,
                                                              )
                                                            : item.oldVal
                                                        : '—'
                                                }}
                                            </td>
                                            <!-- Visual Direction Arrow -->
                                            <td
                                                class="shrink-0 px-2 py-3 text-center text-muted-foreground"
                                            >
                                                <ArrowRight class="size-3.5" />
                                            </td>
                                            <!-- New value column -->
                                            <td
                                                class="bg-emerald-500/5 px-4 py-3 font-mono font-extrabold text-emerald-500 dark:bg-emerald-950/10 dark:text-emerald-400"
                                            >
                                                {{
                                                    item.newVal !== undefined
                                                        ? typeof item.newVal ===
                                                          'object'
                                                            ? JSON.stringify(
                                                                  item.newVal,
                                                              )
                                                            : item.newVal
                                                        : '—'
                                                }}
                                            </td>
                                        </tr>
                                        <tr
                                            v-if="
                                                getDiff(
                                                    log.old_values,
                                                    log.new_values,
                                                ).length === 0
                                            "
                                        >
                                            <td
                                                colspan="4"
                                                class="px-4 py-4 text-center text-muted-foreground italic"
                                            >
                                                Không có thuộc tính thay đổi cụ
                                                thể.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div
                            v-else
                            class="text-xs text-muted-foreground italic"
                        >
                            Không có chi tiết dữ liệu thay đổi.
                        </div>
                    </div>
                </div>
            </div>
        </Card>

        <!-- Pagination -->
        <Pagination
            as="button"
            :links="logs.links"
            :current-page="logs.meta?.current_page"
            :last-page="logs.meta?.last_page"
            :total="logs.meta?.total"
            class="border-t-0"
            @navigate="(url) => router.visit(url)"
        />
    </div>
</template>
