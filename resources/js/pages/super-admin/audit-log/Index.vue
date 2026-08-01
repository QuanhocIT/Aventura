<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronRight,
    FileSearch2,
    Search,
    ShieldAlert,
    Clock,
    Activity,
    Terminal,
    CheckCircle2,
    AlertTriangle,
    Info,
    Globe,
    UserCheck,
    BarChart2,
    Trash2,
    Calendar,
    X,
    Flame,
    Eye,
    ArchiveRestore,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import {
    PageHeader,
    FilterBar,
    StatusBadge,
    Pagination,
    EmptyState,
} from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface AuditLogItem {
    id: number;
    restaurant: string | null;
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
}

interface TopAction {
    action: string;
    count: number;
}

interface Stats {
    total_today: number;
    deleted_count: number;
    created_count: number;
    updated_count: number;
    unique_ips_count: number;
    after_hours_count: number;
    dangerous_count?: number;
    retention_months?: number;
    retention_cutoff?: string;
    top_actions: TopAction[];
}

const props = defineProps<{
    logs: {
        data: AuditLogItem[];
        links: any[];
        total: number;
        last_page: number;
    };
    restaurants: Array<{ id: number; name: string }>;
    filters: {
        restaurant_id?: string;
        event?: string;
        action?: string;
        from?: string;
        to?: string;
    };
    total: number;
    stats: Stats;
}>();

const restaurantFilter = ref(props.filters.restaurant_id || 'all');
const eventFilter = ref(props.filters.event || 'all');
const actionFilter = ref(props.filters.action ?? '');
const fromFilter = ref(props.filters.from ?? '');
const toFilter = ref(props.filters.to ?? '');
const retentionMonths = ref(props.stats.retention_months ?? 6);

let timer: ReturnType<typeof setTimeout>;
watch(actionFilter, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilter, 500);
});

function applyFilter() {
    router.get(
        '/super-admin/audit-logs',
        {
            restaurant_id: restaurantFilter.value && restaurantFilter.value !== 'all' ? restaurantFilter.value : undefined,
            event: eventFilter.value && eventFilter.value !== 'all' ? eventFilter.value : undefined,
            action: actionFilter.value || undefined,
            from: fromFilter.value || undefined,
            to: toFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function resetFilters() {
    restaurantFilter.value = '';
    eventFilter.value = '';
    actionFilter.value = '';
    fromFilter.value = '';
    toFilter.value = '';
    applyFilter();
}

function updateRetention() {
    router.post('/super-admin/audit-logs/retention', { retention_months: retentionMonths.value }, { preserveScroll: true });
}

function pruneRetention() {
    if (!window.confirm(`Xóa vĩnh viễn Audit Log cũ hơn ${retentionMonths.value} tháng? Hành động này không thể hoàn tác.`)) return;
    router.post('/super-admin/audit-logs/retention/prune', {}, { preserveScroll: true });
}

const expandedRow = ref<number | null>(null);
function toggleExpand(id: number) {
    expandedRow.value = expandedRow.value === id ? null : id;
}

const eventLabel: Record<string, string> = {
    created: 'Tạo mới',
    updated: 'Cập nhật',
    deleted: 'Xóa',
};

const actionLabel: Record<string, string> = {
    reset_password: 'Reset mật khẩu',
    disable_2fa: 'Tắt 2FA',
    toggle_account_status: 'Đổi trạng thái TK',
    seed_demo_order: 'Seed đơn demo',
};

function formatAction(action: string): string {
    return actionLabel[action] ?? action.replace(/_/g, ' ');
}

const hasActiveFilter = () =>
    restaurantFilter.value ||
    eventFilter.value ||
    actionFilter.value ||
    fromFilter.value ||
    toFilter.value;

// Dynamic donut slices for Event structure
const donutSlices = computed(() => {
    const totalEvents =
        props.stats.created_count +
        props.stats.updated_count +
        props.stats.deleted_count;

    if (totalEvents === 0) {
return [];
}

    const createdPct = (props.stats.created_count / totalEvents) * 100;
    const updatedPct = (props.stats.updated_count / totalEvents) * 100;
    const deletedPct = (props.stats.deleted_count / totalEvents) * 100;

    let currentOffset = 0;
    const slices = [];

    if (createdPct > 0) {
        slices.push({
            percentage: createdPct,
            dashArray: `${createdPct} ${100 - createdPct}`,
            dashOffset: 100 - currentOffset,
            color: 'stroke-blue-500',
            label: 'Tạo mới',
            value: props.stats.created_count,
        });
        currentOffset += createdPct;
    }

    if (updatedPct > 0) {
        slices.push({
            percentage: updatedPct,
            dashArray: `${updatedPct} ${100 - updatedPct}`,
            dashOffset: 100 - currentOffset,
            color: 'stroke-amber-500',
            label: 'Cập nhật',
            value: props.stats.updated_count,
        });
        currentOffset += updatedPct;
    }

    if (deletedPct > 0) {
        slices.push({
            percentage: deletedPct,
            dashArray: `${deletedPct} ${100 - deletedPct}`,
            dashOffset: 100 - currentOffset,
            color: 'stroke-rose-500',
            label: 'Xóa dữ liệu',
            value: props.stats.deleted_count,
        });
    }

    return slices;
});

// Max action count for Top Actions progress bar scale
const maxActionCount = computed(() => {
    return Math.max(...props.stats.top_actions.map((a) => a.count), 1);
});

// Security insights calculations
const securityInsights = computed(() => {
    const alerts = [];

    if (props.stats.after_hours_count > 0) {
        alerts.push({
            type: 'warning',
            title: 'Hoạt động ngoài giờ',
            desc: `Phát hiện ${props.stats.after_hours_count} thao tác ngoài giờ hành chính (23:00 - 05:00). Hãy kiểm tra xem đây là hoạt động hợp lệ hay truy cập trái phép.`,
        });
    }

    if (props.stats.deleted_count > 5) {
        alerts.push({
            type: 'danger',
            title: 'Tần suất xóa dữ liệu cao',
            desc: `Đã ghi nhận ${props.stats.deleted_count} thao tác XÓA tài sản/dữ liệu. Cần giám sát chặt chẽ tránh thất thoát dữ liệu nhạy cảm.`,
        });
    }

    if (props.stats.unique_ips_count > 3) {
        alerts.push({
            type: 'info',
            title: 'Nhiều địa chỉ IP',
            desc: `Ghi nhận truy cập kiểm toán từ ${props.stats.unique_ips_count} IP khác nhau. Khuyến nghị đảm bảo các admin đều đang sử dụng VPN an toàn.`,
        });
    }

    if (alerts.length === 0) {
        alerts.push({
            type: 'success',
            title: 'Trạng thái an toàn',
            desc: 'Không phát hiện hoạt động bất thường hoặc hành vi đáng ngờ nào ngoài giờ. Hệ thống hoạt động an toàn.',
        });
    }

    return alerts;
});
</script>

<template>
    <Head title="Audit Log hệ thống" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Audit Log hệ thống"
            :subtitle="`Nhật ký thao tác cấp hệ thống · Tổng số ${total.toLocaleString()} bản ghi`"
            :icon="FileSearch2"
        >
            <template #actions>
                <Link href="/super-admin/accounts">
                    <Button
                        variant="outline"
                        size="sm"
                        class="cursor-pointer rounded-xl font-semibold shadow-xs"
                    >
                        Quản lý tài khoản →
                    </Button>
                </Link>
            </template>
        </PageHeader>

        <!-- KPI Widgets (Responsive 5 Columns Glassmorphism style) -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
            <!-- Total Logs -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-1.5 p-4 text-center">
                    <p
                        class="text-[10px] font-black tracking-wider text-slate-500 uppercase"
                    >
                        Tổng số bản ghi
                    </p>
                    <div
                        class="font-mono text-2xl font-black text-slate-800 dark:text-slate-100"
                    >
                        {{ total.toLocaleString() }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        Toàn bộ thời gian
                    </p>
                </CardContent>
            </Card>

            <!-- Today's Operations -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-1.5 p-4 text-center">
                    <p
                        class="text-[10px] font-black tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                    >
                        Hoạt động hôm nay
                    </p>
                    <div
                        class="flex items-center justify-center gap-1 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400"
                    >
                        <Activity class="size-5 text-emerald-500" />
                        {{ stats.total_today }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        Cập nhật trong ngày
                    </p>
                </CardContent>
            </Card>

            <!-- Total Deletes -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
                :class="{
                    'border-rose-500/20 bg-rose-500/[0.02]':
                        stats.deleted_count > 0,
                }"
            >
                <CardContent class="space-y-1.5 p-4 text-center">
                    <p
                        class="text-[10px] font-black tracking-wider text-rose-600 uppercase dark:text-rose-400"
                    >
                        Hành động xóa (Deleted)
                    </p>
                    <div
                        class="flex items-center justify-center gap-1 font-mono text-2xl font-black text-rose-600 dark:text-rose-400"
                    >
                        <Trash2 class="size-5 text-rose-500" />
                        {{ stats.deleted_count }}
                    </div>
                    <p class="text-[9px] font-bold text-rose-500/80 uppercase">
                        Cần theo dõi sát
                    </p>
                </CardContent>
            </Card>

            <!-- After-hours Activities -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
                :class="{
                    'border-amber-500/20 bg-amber-500/[0.02]':
                        stats.after_hours_count > 0,
                }"
            >
                <CardContent class="space-y-1.5 p-4 text-center">
                    <p
                        class="text-[10px] font-black tracking-wider text-amber-600 uppercase dark:text-amber-400"
                    >
                        Thao tác ngoài giờ
                    </p>
                    <div
                        class="flex items-center justify-center gap-1 font-mono text-2xl font-black text-amber-600 dark:text-amber-400"
                    >
                        <Clock class="size-5 text-amber-500" />
                        {{ stats.after_hours_count }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        Khung giờ 23:00 - 05:00
                    </p>
                </CardContent>
            </Card>

            <!-- Unique IP Count -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-1.5 p-4 text-center">
                    <p
                        class="text-[10px] font-black tracking-wider text-indigo-600 uppercase dark:text-indigo-400"
                    >
                        Địa chỉ IP duy nhất
                    </p>
                    <div
                        class="flex items-center justify-center gap-1 font-mono text-2xl font-black text-indigo-600 dark:text-indigo-400"
                    >
                        <Globe class="size-5 text-indigo-500" />
                        {{ stats.unique_ips_count }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        Phân bổ nguồn truy cập
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Visual Charts and Analytics Insights Grid (Donut Chart + Top Actions + Security Warnings) -->
        <div class="grid gap-4 md:grid-cols-3">
            <!-- Event Type Distribution Chart (Donut) -->
            <Card
                class="border border-border/40 bg-card/50 shadow-xs backdrop-blur-md"
            >
                <CardContent
                    class="flex h-full min-h-[190px] flex-col items-center justify-center gap-4 p-5"
                >
                    <div class="w-full text-left">
                        <h3
                            class="flex items-center gap-1 text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-200"
                        >
                            <BarChart2 class="size-4 text-primary" />
                            Cơ cấu Sự kiện kiểm toán
                        </h3>
                    </div>
                    <div class="flex w-full items-center justify-between gap-4">
                        <div class="space-y-1.5 text-xs font-semibold">
                            <div
                                v-for="slice in donutSlices"
                                :key="slice.label"
                                class="flex items-center gap-1.5"
                            >
                                <span
                                    class="size-2 rounded-full"
                                    :class="
                                        slice.color.replace('stroke-', 'bg-')
                                    "
                                ></span>
                                <span class="text-slate-500"
                                    >{{ slice.label }}:</span
                                >
                                <span
                                    class="font-mono font-bold text-slate-800 dark:text-slate-200"
                                    >{{ slice.value }}</span
                                >
                            </div>
                        </div>
                        <!-- Donut SVG -->
                        <div
                            class="relative flex size-24 shrink-0 items-center justify-center"
                        >
                            <svg
                                viewBox="0 0 42 42"
                                class="size-full -rotate-90 transform"
                            >
                                <circle
                                    cx="21"
                                    cy="21"
                                    r="15.915"
                                    fill="transparent"
                                    stroke="rgba(226, 232, 240, 0.4)"
                                    stroke-width="4"
                                ></circle>
                                <circle
                                    v-for="(slice, index) in donutSlices"
                                    :key="index"
                                    cx="21"
                                    cy="21"
                                    r="15.915"
                                    fill="transparent"
                                    :class="slice.color"
                                    stroke-width="4"
                                    :stroke-dasharray="slice.dashArray"
                                    :stroke-dashoffset="slice.dashOffset"
                                    class="transition-all duration-700"
                                ></circle>
                            </svg>
                            <div
                                class="absolute flex flex-col items-center justify-center text-center"
                            >
                                <span
                                    class="font-mono text-base font-black text-slate-800 dark:text-slate-100"
                                >
                                    {{
                                        stats.created_count +
                                        stats.updated_count +
                                        stats.deleted_count
                                    }}
                                </span>
                                <span
                                    class="text-[7px] font-bold text-slate-400 uppercase"
                                    >Checked</span
                                >
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Top Active Action Operations -->
            <Card
                class="border border-border/40 bg-card/50 shadow-xs backdrop-blur-md"
            >
                <CardContent class="h-full min-h-[190px] space-y-3 p-5">
                    <h3
                        class="flex items-center gap-1 text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-200"
                    >
                        <Activity class="size-4 text-blue-500" />
                        Top 5 Thao tác Phổ biến
                    </h3>
                    <div class="space-y-2.5 pt-1">
                        <div
                            v-for="action in stats.top_actions"
                            :key="action.action"
                            class="space-y-1 text-xs"
                        >
                            <div class="flex justify-between font-semibold">
                                <span
                                    class="max-w-[200px] truncate font-mono text-slate-600 dark:text-slate-300"
                                    :title="action.action"
                                >
                                    {{ formatAction(action.action) }}
                                </span>
                                <span
                                    class="shrink-0 font-mono font-bold text-slate-500"
                                    >{{ action.count }} lần</span
                                >
                            </div>
                            <div
                                class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                            >
                                <div
                                    :style="{
                                        width: `${(action.count / maxActionCount) * 100}%`,
                                    }"
                                    class="h-full rounded-full bg-blue-500 transition-all duration-500"
                                ></div>
                            </div>
                        </div>
                        <div
                            v-if="!stats.top_actions?.length"
                            class="py-6 text-center text-muted-foreground italic"
                        >
                            Chưa có dữ liệu thống kê hành động.
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- System Security Warnings & Suggestions -->
            <Card
                class="border border-border/40 bg-card/50 shadow-xs backdrop-blur-md"
            >
                <CardContent
                    class="h-full min-h-[190px] space-y-3 overflow-y-auto p-5"
                >
                    <h3
                        class="flex items-center gap-1 text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-200"
                    >
                        <ShieldAlert class="size-4 text-rose-500" />
                        Phân tích Bảo mật hệ thống
                    </h3>
                    <div class="space-y-2 pt-1">
                        <div
                            v-for="(insight, index) in securityInsights"
                            :key="index"
                            class="rounded-lg border p-2.5 text-xs leading-relaxed"
                            :class="[
                                insight.type === 'danger'
                                    ? 'border-rose-500/20 bg-rose-500/[0.04] text-rose-800 dark:text-rose-300'
                                    : insight.type === 'warning'
                                      ? 'border-amber-500/20 bg-amber-500/[0.04] text-amber-800 dark:text-amber-300'
                                      : insight.type === 'info'
                                        ? 'border-blue-500/20 bg-blue-500/[0.04] text-blue-800 dark:text-blue-300'
                                        : 'border-emerald-500/20 bg-emerald-500/[0.04] text-emerald-800 dark:text-emerald-300',
                            ]"
                        >
                            <div
                                class="mb-0.5 flex items-center gap-1 font-bold"
                            >
                                <AlertTriangle
                                    v-if="
                                        insight.type === 'danger' ||
                                        insight.type === 'warning'
                                    "
                                    class="size-3.5"
                                />
                                <CheckCircle2
                                    v-else-if="insight.type === 'success'"
                                    class="size-3.5"
                                />
                                <Info v-else class="size-3.5" />
                                {{ insight.title }}
                            </div>
                            <p class="text-[10px] leading-relaxed font-medium">
                                {{ insight.desc }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card class="border-amber-500/30 bg-amber-500/[0.03]">
            <CardContent class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div>
                    <p class="flex items-center gap-2 text-sm font-bold"><ArchiveRestore class="size-4 text-amber-600" /> Retention & thao tác nguy hiểm</p>
                    <p class="text-xs text-muted-foreground">{{ stats.dangerous_count ?? 0 }} thao tác nhạy cảm · dữ liệu đến {{ stats.retention_cutoff || '—' }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Input v-model.number="retentionMonths" type="number" min="1" max="120" class="h-8 w-24" />
                    <span class="text-xs text-muted-foreground">tháng</span>
                    <Button size="sm" variant="outline" @click="updateRetention">Lưu retention</Button>
                    <Button size="sm" variant="destructive" @click="pruneRetention">Prune log cũ</Button>
                </div>
            </CardContent>
        </Card>

        <!-- Filters Block -->
        <FilterBar>
            <Select
                v-model="restaurantFilter"
                @update:model-value="applyFilter"
            >
                <SelectTrigger class="w-[180px]">
                    <SelectValue placeholder="Tất cả nhà hàng" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tất cả nhà hàng</SelectItem>
                    <SelectItem value="system">— Hệ thống —</SelectItem>
                    <SelectItem
                        v-for="r in restaurants"
                        :key="r.id"
                        :value="String(r.id)"
                        >{{ r.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Select v-model="eventFilter" @update:model-value="applyFilter">
                <SelectTrigger class="w-[160px]">
                    <SelectValue placeholder="Tất cả sự kiện" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tất cả sự kiện</SelectItem>
                    <SelectItem value="created">Tạo mới</SelectItem>
                    <SelectItem value="updated">Cập nhật</SelectItem>
                    <SelectItem value="deleted">Xóa</SelectItem>
                </SelectContent>
            </Select>
            <div class="relative min-w-40 flex-1">
                <Search
                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                />
                <Input
                    v-model="actionFilter"
                    placeholder="Tìm hành động..."
                    class="pl-9"
                />
            </div>
            <Input
                v-model="fromFilter"
                type="date"
                class="w-[150px]"
                @change="applyFilter"
            />
            <Input
                v-model="toFilter"
                type="date"
                class="w-[150px]"
                @change="applyFilter"
            />
            <template v-if="hasActiveFilter()" #actions>
                <Button
                    variant="ghost"
                    size="sm"
                    @click="resetFilters"
                    class="cursor-pointer text-xs text-muted-foreground"
                    >Xoá lọc</Button
                >
            </template>
        </FilterBar>

        <!-- Audit Logs Table -->
        <Card class="stagger-2 overflow-hidden border border-border/60">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="border-b border-border/60 bg-muted/20 backdrop-blur-sm"
                        >
                            <tr>
                                <th class="w-8 px-4 py-3" />
                                <th
                                    class="px-4 py-3 text-left text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Thời gian
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Nhà hàng
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Người thực hiện
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Sự kiện
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Hành động
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Đối tượng
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    IP
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40 text-xs">
                            <template
                                v-for="(log, idx) in logs.data"
                                :key="log.id"
                            >
                                <tr
                                    class="cursor-pointer border-b border-border/30 transition-all duration-200 hover:-translate-y-px hover:bg-muted/20 hover:shadow-[0_2px_12px_rgba(0,0,0,0.04)] dark:hover:shadow-[0_2px_12px_rgba(0,0,0,0.15)]"
                                    :class="[
                                        expandedRow === log.id
                                            ? 'bg-muted/30 font-semibold'
                                            : '',
                                        idx % 2 !== 0 ? 'bg-muted/[0.06]' : '',
                                    ]"
                                    :style="{ animationDelay: `${idx * 30}ms` }"
                                    @click="toggleExpand(log.id)"
                                >
                                    <td
                                        class="px-4 py-3.5 text-muted-foreground"
                                    >
                                        <ChevronDown
                                            v-if="expandedRow === log.id"
                                            class="size-4 transition-transform"
                                        />
                                        <ChevronRight
                                            v-else
                                            class="size-4 transition-transform"
                                        />
                                    </td>
                                    <td
                                        class="px-4 py-3.5 font-mono text-xs whitespace-nowrap text-muted-foreground"
                                    >
                                        {{ log.created_at }}
                                    </td>
                                    <td class="px-4 py-3.5 text-xs">
                                        <span
                                            v-if="log.restaurant"
                                            class="font-bold text-slate-800 dark:text-slate-200"
                                            >{{ log.restaurant }}</span
                                        >
                                        <span
                                            v-else
                                            class="font-medium text-muted-foreground italic"
                                            >— Hệ thống —</span
                                        >
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-1.5">
                                            <UserCheck
                                                class="size-3.5 text-slate-400"
                                            />
                                            <div>
                                                <p
                                                    class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                                >
                                                    {{ log.user_name }}
                                                </p>
                                                <p
                                                    v-if="log.user_email"
                                                    class="font-mono text-[10px] text-muted-foreground"
                                                >
                                                    {{ log.user_email }}
                                                </p>
                                            </div>
                                        </div>
                                        <span
                                            v-if="log.user_role"
                                            class="mt-1 inline-block rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] font-black tracking-wider text-slate-500 uppercase dark:bg-slate-800"
                                        >
                                            {{ log.user_role }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <StatusBadge :status="log.event">
                                            {{
                                                eventLabel[log.event] ??
                                                log.event
                                            }}
                                        </StatusBadge>
                                    </td>
                                    <td
                                        class="px-4 py-3.5 font-mono text-xs text-slate-800 dark:text-slate-200"
                                    >
                                        {{ formatAction(log.action) }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs font-semibold text-muted-foreground"
                                    >
                                        <span v-if="log.subject_type">
                                            {{ log.subject_type }}
                                            <span
                                                v-if="log.subject_id"
                                                class="font-mono text-[10px] text-slate-400"
                                                >#{{ log.subject_id }}</span
                                            >
                                        </span>
                                        <span
                                            v-else
                                            class="text-slate-400 italic"
                                            >—</span
                                        >
                                    </td>
                                    <td
                                        class="px-4 py-3.5 font-mono text-xs text-muted-foreground"
                                    >
                                        {{ log.ip_address ?? '—' }}
                                    </td>
                                </tr>

                                <!-- Expanded row: terminal-style diff viewer -->
                                <tr
                                    v-if="expandedRow === log.id"
                                    class="bg-muted/10"
                                >
                                    <td colspan="8" class="p-4">
                                        <div
                                            class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 p-5 shadow-xl"
                                        >
                                            <div
                                                class="mb-3 flex items-center justify-between"
                                            >
                                                <div
                                                    class="flex items-center gap-1.5"
                                                >
                                                    <span
                                                        class="size-2.5 rounded-full bg-rose-500/90 shadow-[0_0_6px_#f43f5e]"
                                                    />
                                                    <span
                                                        class="size-2.5 rounded-full bg-amber-500/90 shadow-[0_0_6px_#f59e0b]"
                                                    />
                                                    <span
                                                        class="size-2.5 rounded-full bg-emerald-500/90 shadow-[0_0_6px_#10b981]"
                                                    />
                                                    <span
                                                        class="ml-2 flex items-center gap-1 font-mono text-[9px] font-extrabold tracking-wider text-slate-500 uppercase"
                                                    >
                                                        <Terminal
                                                            class="size-3"
                                                        />
                                                        Trình xem thay đổi thuộc
                                                        tính (Diff Viewer)
                                                    </span>
                                                </div>
                                                <div
                                                    class="font-mono text-[9px] font-bold text-slate-500"
                                                >
                                                    BẢN GHI: #{{ log.id }}
                                                </div>
                                            </div>

                                            <!-- Render Side-by-side or Single View based on data presence -->
                                            <div
                                                v-if="
                                                    log.old_values &&
                                                    log.new_values
                                                "
                                                class="grid gap-4 md:grid-cols-2"
                                            >
                                                <div>
                                                    <p
                                                        class="mb-2 flex items-center gap-1 text-[10px] font-bold tracking-widest text-rose-400 uppercase"
                                                    >
                                                        <Flame class="size-3" />
                                                        Dữ liệu trước thay đổi
                                                        (Old Values)
                                                    </p>
                                                    <pre
                                                        class="max-h-56 overflow-auto rounded-lg border border-rose-500/10 bg-rose-950/20 p-3.5 font-mono text-[11px] leading-relaxed text-rose-300"
                                                        >{{
                                                            JSON.stringify(
                                                                log.old_values,
                                                                null,
                                                                2,
                                                            )
                                                        }}</pre
                                                    >
                                                </div>
                                                <div>
                                                    <p
                                                        class="mb-2 flex items-center gap-1 text-[10px] font-bold tracking-widest text-emerald-400 uppercase"
                                                    >
                                                        <CheckCircle2
                                                            class="size-3"
                                                        />
                                                        Dữ liệu sau thay đổi
                                                        (New Values)
                                                    </p>
                                                    <pre
                                                        class="max-h-56 overflow-auto rounded-lg border border-emerald-500/10 bg-emerald-950/20 p-3.5 font-mono text-[11px] leading-relaxed text-emerald-300"
                                                        >{{
                                                            JSON.stringify(
                                                                log.new_values,
                                                                null,
                                                                2,
                                                            )
                                                        }}</pre
                                                    >
                                                </div>
                                            </div>

                                            <div
                                                v-else-if="log.new_values"
                                                class="w-full"
                                            >
                                                <p
                                                    class="mb-2 flex items-center gap-1 text-[10px] font-bold tracking-widest text-emerald-400 uppercase"
                                                >
                                                    <Plus class="size-3" /> Dữ
                                                    liệu được ghi nhận mới
                                                    (Recorded Values)
                                                </p>
                                                <pre
                                                    class="max-h-56 overflow-auto rounded-lg border border-emerald-500/10 bg-emerald-950/20 p-3.5 font-mono text-[11px] leading-relaxed text-emerald-300"
                                                    >{{
                                                        JSON.stringify(
                                                            log.new_values,
                                                            null,
                                                            2,
                                                        )
                                                    }}</pre
                                                >
                                            </div>

                                            <div
                                                v-else-if="log.old_values"
                                                class="w-full"
                                            >
                                                <p
                                                    class="mb-2 flex items-center gap-1 text-[10px] font-bold tracking-widest text-rose-400 uppercase"
                                                >
                                                    <Trash2 class="size-3" /> Dữ
                                                    liệu đã xóa bỏ (Deleted
                                                    Values)
                                                </p>
                                                <pre
                                                    class="max-h-56 overflow-auto rounded-lg border border-rose-500/10 bg-rose-950/20 p-3.5 font-mono text-[11px] leading-relaxed text-rose-300"
                                                    >{{
                                                        JSON.stringify(
                                                            log.old_values,
                                                            null,
                                                            2,
                                                        )
                                                    }}</pre
                                                >
                                            </div>

                                            <div
                                                v-else
                                                class="py-6 text-center font-mono text-xs text-slate-500 italic"
                                            >
                                                Không ghi nhận dữ liệu thuộc
                                                tính thay đổi chi tiết (Metadata
                                                logs).
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="!logs.data.length">
                                <td colspan="8">
                                    <EmptyState
                                        :icon="FileSearch2"
                                        title="Không có bản ghi nào phù hợp"
                                        description="Thử thay đổi bộ lọc hoặc mở rộng khoảng thời gian"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-if="logs.last_page > 1" :links="logs.links" />
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
.stagger-2 {
    animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
