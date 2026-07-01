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
    Eye
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageHeader, FilterBar, StatusBadge, Pagination, EmptyState } from '@/components/super-admin';
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
    filters: { restaurant_id?: string; event?: string; action?: string; from?: string; to?: string };
    total: number;
    stats: Stats;
}>();

const restaurantFilter = ref(props.filters.restaurant_id ?? '');
const eventFilter = ref(props.filters.event ?? '');
const actionFilter = ref(props.filters.action ?? '');
const fromFilter = ref(props.filters.from ?? '');
const toFilter = ref(props.filters.to ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(actionFilter, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilter, 500);
});

function applyFilter() {
    router.get('/super-admin/audit-logs', {
        restaurant_id: restaurantFilter.value || undefined,
        event: eventFilter.value || undefined,
        action: actionFilter.value || undefined,
        from: fromFilter.value || undefined,
        to: toFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

function resetFilters() {
    restaurantFilter.value = '';
    eventFilter.value = '';
    actionFilter.value = '';
    fromFilter.value = '';
    toFilter.value = '';
    applyFilter();
}

const expandedRow = ref<number | null>(null);
function toggleExpand(id: number) {
    expandedRow.value = expandedRow.value === id ? null : id;
}

const eventLabel: Record<string, string> = {
    created: 'Tạo mới', updated: 'Cập nhật', deleted: 'Xóa',
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
    restaurantFilter.value || eventFilter.value ||
    actionFilter.value || fromFilter.value || toFilter.value;

// Dynamic donut slices for Event structure
const donutSlices = computed(() => {
    const totalEvents = props.stats.created_count + props.stats.updated_count + props.stats.deleted_count;
    if (totalEvents === 0) return [];

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
            value: props.stats.created_count
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
            value: props.stats.updated_count
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
            value: props.stats.deleted_count
        });
    }

    return slices;
});

// Max action count for Top Actions progress bar scale
const maxActionCount = computed(() => {
    return Math.max(...props.stats.top_actions.map(a => a.count), 1);
});

// Security insights calculations
const securityInsights = computed(() => {
    const alerts = [];
    
    if (props.stats.after_hours_count > 0) {
        alerts.push({
            type: 'warning',
            title: 'Hoạt động ngoài giờ',
            desc: `Phát hiện ${props.stats.after_hours_count} thao tác ngoài giờ hành chính (23:00 - 05:00). Hãy kiểm tra xem đây là hoạt động hợp lệ hay truy cập trái phép.`
        });
    }

    if (props.stats.deleted_count > 5) {
        alerts.push({
            type: 'danger',
            title: 'Tần suất xóa dữ liệu cao',
            desc: `Đã ghi nhận ${props.stats.deleted_count} thao tác XÓA tài sản/dữ liệu. Cần giám sát chặt chẽ tránh thất thoát dữ liệu nhạy cảm.`
        });
    }

    if (props.stats.unique_ips_count > 3) {
        alerts.push({
            type: 'info',
            title: 'Nhiều địa chỉ IP',
            desc: `Ghi nhận truy cập kiểm toán từ ${props.stats.unique_ips_count} IP khác nhau. Khuyến nghị đảm bảo các admin đều đang sử dụng VPN an toàn.`
        });
    }

    if (alerts.length === 0) {
        alerts.push({
            type: 'success',
            title: 'Trạng thái an toàn',
            desc: 'Không phát hiện hoạt động bất thường hoặc hành vi đáng ngờ nào ngoài giờ. Hệ thống hoạt động an toàn.'
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
                    <Button variant="outline" size="sm" class="rounded-xl shadow-xs font-semibold cursor-pointer">
                        Quản lý tài khoản →
                    </Button>
                </Link>
            </template>
        </PageHeader>

        <!-- KPI Widgets (Responsive 5 Columns Glassmorphism style) -->
        <div class="grid gap-4 grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
            <!-- Total Logs -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 hover:shadow-md transition-all">
                <CardContent class="p-4 text-center space-y-1.5">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-wider">Tổng số bản ghi</p>
                    <div class="text-2xl font-black text-slate-800 dark:text-slate-100 font-mono">
                        {{ total.toLocaleString() }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">Toàn bộ thời gian</p>
                </CardContent>
            </Card>

            <!-- Today's Operations -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 hover:shadow-md transition-all">
                <CardContent class="p-4 text-center space-y-1.5">
                    <p class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Hoạt động hôm nay</p>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono flex items-center justify-center gap-1">
                        <Activity class="size-5 text-emerald-500" />
                        {{ stats.total_today }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">Cập nhật trong ngày</p>
                </CardContent>
            </Card>

            <!-- Total Deletes -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 hover:shadow-md transition-all" :class="{ 'border-rose-500/20 bg-rose-500/[0.02]': stats.deleted_count > 0 }">
                <CardContent class="p-4 text-center space-y-1.5">
                    <p class="text-[10px] font-black text-rose-600 dark:text-rose-400 uppercase tracking-wider">Hành động xóa (Deleted)</p>
                    <div class="text-2xl font-black text-rose-600 dark:text-rose-400 font-mono flex items-center justify-center gap-1">
                        <Trash2 class="size-5 text-rose-500" />
                        {{ stats.deleted_count }}
                    </div>
                    <p class="text-[9px] text-rose-500/80 font-bold uppercase">Cần theo dõi sát</p>
                </CardContent>
            </Card>

            <!-- After-hours Activities -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 hover:shadow-md transition-all" :class="{ 'border-amber-500/20 bg-amber-500/[0.02]': stats.after_hours_count > 0 }">
                <CardContent class="p-4 text-center space-y-1.5">
                    <p class="text-[10px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-wider">Thao tác ngoài giờ</p>
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400 font-mono flex items-center justify-center gap-1">
                        <Clock class="size-5 text-amber-500" />
                        {{ stats.after_hours_count }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">Khung giờ 23:00 - 05:00</p>
                </CardContent>
            </Card>

            <!-- Unique IP Count -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 hover:shadow-md transition-all">
                <CardContent class="p-4 text-center space-y-1.5">
                    <p class="text-[10px] font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Địa chỉ IP duy nhất</p>
                    <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 font-mono flex items-center justify-center gap-1">
                        <Globe class="size-5 text-indigo-500" />
                        {{ stats.unique_ips_count }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">Phân bổ nguồn truy cập</p>
                </CardContent>
            </Card>
        </div>

        <!-- Visual Charts and Analytics Insights Grid (Donut Chart + Top Actions + Security Warnings) -->
        <div class="grid gap-4 md:grid-cols-3">
            <!-- Event Type Distribution Chart (Donut) -->
            <Card class="bg-card/50 backdrop-blur-md border border-border/40 shadow-xs">
                <CardContent class="p-5 flex flex-col items-center justify-center gap-4 h-full min-h-[190px]">
                    <div class="w-full text-left">
                        <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1">
                            <BarChart2 class="size-4 text-primary" />
                            Cơ cấu Sự kiện kiểm toán
                        </h3>
                    </div>
                    <div class="flex items-center justify-between w-full gap-4">
                        <div class="space-y-1.5 text-xs font-semibold">
                            <div v-for="slice in donutSlices" :key="slice.label" class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full" :class="slice.color.replace('stroke-', 'bg-')"></span>
                                <span class="text-slate-500">{{ slice.label }}:</span>
                                <span class="font-mono text-slate-800 dark:text-slate-200 font-bold">{{ slice.value }}</span>
                            </div>
                        </div>
                        <!-- Donut SVG -->
                        <div class="relative size-24 shrink-0 flex items-center justify-center">
                            <svg viewBox="0 0 42 42" class="size-full transform -rotate-90">
                                <circle cx="21" cy="21" r="15.915" fill="transparent" stroke="rgba(226, 232, 240, 0.4)" stroke-width="4"></circle>
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
                            <div class="absolute text-center flex flex-col justify-center items-center">
                                <span class="text-base font-black text-slate-800 dark:text-slate-100 font-mono">
                                    {{ stats.created_count + stats.updated_count + stats.deleted_count }}
                                </span>
                                <span class="text-[7px] uppercase font-bold text-slate-400">Checked</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Top Active Action Operations -->
            <Card class="bg-card/50 backdrop-blur-md border border-border/40 shadow-xs">
                <CardContent class="p-5 space-y-3 h-full min-h-[190px]">
                    <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1">
                        <Activity class="size-4 text-blue-500" />
                        Top 5 Thao tác Phổ biến
                    </h3>
                    <div class="space-y-2.5 pt-1">
                        <div v-for="action in stats.top_actions" :key="action.action" class="text-xs space-y-1">
                            <div class="flex justify-between font-semibold">
                                <span class="text-slate-600 dark:text-slate-300 font-mono truncate max-w-[200px]" :title="action.action">
                                    {{ formatAction(action.action) }}
                                </span>
                                <span class="font-mono text-slate-500 font-bold shrink-0">{{ action.count }} lần</span>
                            </div>
                            <div class="h-1.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                <div 
                                    :style="{ width: `${(action.count / maxActionCount) * 100}%` }"
                                    class="h-full bg-blue-500 rounded-full transition-all duration-500"
                                ></div>
                            </div>
                        </div>
                        <div v-if="!stats.top_actions?.length" class="text-center text-muted-foreground italic py-6">
                            Chưa có dữ liệu thống kê hành động.
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- System Security Warnings & Suggestions -->
            <Card class="bg-card/50 backdrop-blur-md border border-border/40 shadow-xs">
                <CardContent class="p-5 space-y-3 h-full min-h-[190px] overflow-y-auto">
                    <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1">
                        <ShieldAlert class="size-4 text-rose-500" />
                        Phân tích Bảo mật hệ thống
                    </h3>
                    <div class="space-y-2 pt-1">
                        <div 
                            v-for="(insight, index) in securityInsights" 
                            :key="index"
                            class="p-2.5 rounded-lg border text-xs leading-relaxed"
                            :class="[
                                insight.type === 'danger' ? 'bg-rose-500/[0.04] border-rose-500/20 text-rose-800 dark:text-rose-300' :
                                insight.type === 'warning' ? 'bg-amber-500/[0.04] border-amber-500/20 text-amber-800 dark:text-amber-300' :
                                insight.type === 'info' ? 'bg-blue-500/[0.04] border-blue-500/20 text-blue-800 dark:text-blue-300' :
                                'bg-emerald-500/[0.04] border-emerald-500/20 text-emerald-800 dark:text-emerald-300'
                            ]"
                        >
                            <div class="flex items-center gap-1 font-bold mb-0.5">
                                <AlertTriangle v-if="insight.type === 'danger' || insight.type === 'warning'" class="size-3.5" />
                                <CheckCircle2 v-else-if="insight.type === 'success'" class="size-3.5" />
                                <Info v-else class="size-3.5" />
                                {{ insight.title }}
                            </div>
                            <p class="text-[10px] font-medium leading-relaxed">{{ insight.desc }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters Block -->
        <FilterBar>
            <Select v-model="restaurantFilter" @update:model-value="applyFilter">
                <SelectTrigger class="w-[180px]">
                    <SelectValue placeholder="Tất cả nhà hàng" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả nhà hàng</SelectItem>
                    <SelectItem value="system">— Hệ thống —</SelectItem>
                    <SelectItem v-for="r in restaurants" :key="r.id" :value="String(r.id)">{{ r.name }}</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="eventFilter" @update:model-value="applyFilter">
                <SelectTrigger class="w-[160px]">
                    <SelectValue placeholder="Tất cả sự kiện" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả sự kiện</SelectItem>
                    <SelectItem value="created">Tạo mới</SelectItem>
                    <SelectItem value="updated">Cập nhật</SelectItem>
                    <SelectItem value="deleted">Xóa</SelectItem>
                </SelectContent>
            </Select>
            <div class="relative min-w-40 flex-1">
                <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="actionFilter" placeholder="Tìm hành động..." class="pl-9" />
            </div>
            <Input v-model="fromFilter" type="date" class="w-[150px]" @change="applyFilter" />
            <Input v-model="toFilter" type="date" class="w-[150px]" @change="applyFilter" />
            <template v-if="hasActiveFilter()" #actions>
                <Button variant="ghost" size="sm" @click="resetFilters" class="text-xs text-muted-foreground cursor-pointer">Xoá lọc</Button>
            </template>
        </FilterBar>

        <!-- Audit Logs Table -->
        <Card class="stagger-2 overflow-hidden border border-border/60">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-border/60 bg-muted/20 backdrop-blur-sm">
                            <tr>
                                <th class="w-8 px-4 py-3" />
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Thời gian</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Nhà hàng</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Người thực hiện</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Sự kiện</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Hành động</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Đối tượng</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40 text-xs">
                            <template v-for="(log, idx) in logs.data" :key="log.id">
                                <tr
                                    class="cursor-pointer border-b border-border/30 transition-all duration-200 hover:-translate-y-px hover:bg-muted/20 hover:shadow-[0_2px_12px_rgba(0,0,0,0.04)] dark:hover:shadow-[0_2px_12px_rgba(0,0,0,0.15)]"
                                    :class="[expandedRow === log.id ? 'bg-muted/30 font-semibold' : '', idx % 2 !== 0 ? 'bg-muted/[0.06]' : '']"
                                    :style="{ animationDelay: `${idx * 30}ms` }"
                                    @click="toggleExpand(log.id)"
                                >
                                    <td class="px-4 py-3.5 text-muted-foreground">
                                        <ChevronDown v-if="expandedRow === log.id" class="size-4 transition-transform" />
                                        <ChevronRight v-else class="size-4 transition-transform" />
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-xs text-muted-foreground font-mono">{{ log.created_at }}</td>
                                    <td class="px-4 py-3.5 text-xs">
                                        <span v-if="log.restaurant" class="font-bold text-slate-800 dark:text-slate-200">{{ log.restaurant }}</span>
                                        <span v-else class="italic text-muted-foreground font-medium">— Hệ thống —</span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-1.5">
                                            <UserCheck class="size-3.5 text-slate-400" />
                                            <div>
                                                <p class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ log.user_name }}</p>
                                                <p v-if="log.user_email" class="text-[10px] text-muted-foreground font-mono">{{ log.user_email }}</p>
                                            </div>
                                        </div>
                                        <span
                                            v-if="log.user_role"
                                            class="inline-block mt-1 rounded-full bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[9px] font-black uppercase text-slate-500 tracking-wider"
                                        >
                                            {{ log.user_role }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <StatusBadge :status="log.event">
                                            {{ eventLabel[log.event] ?? log.event }}
                                        </StatusBadge>
                                    </td>
                                    <td class="px-4 py-3.5 font-mono text-xs text-slate-800 dark:text-slate-200">{{ formatAction(log.action) }}</td>
                                    <td class="px-4 py-3.5 text-xs text-muted-foreground font-semibold">
                                        <span v-if="log.subject_type">
                                            {{ log.subject_type }}
                                            <span v-if="log.subject_id" class="font-mono text-[10px] text-slate-400">#{{ log.subject_id }}</span>
                                        </span>
                                        <span v-else class="italic text-slate-400">—</span>
                                    </td>
                                    <td class="px-4 py-3.5 font-mono text-xs text-muted-foreground">{{ log.ip_address ?? '—' }}</td>
                                </tr>

                                <!-- Expanded row: terminal-style diff viewer -->
                                <tr v-if="expandedRow === log.id" class="bg-muted/10">
                                    <td colspan="8" class="p-4">
                                        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 p-5 shadow-xl">
                                            <div class="mb-3 flex items-center justify-between">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="size-2.5 rounded-full bg-rose-500/90 shadow-[0_0_6px_#f43f5e]" />
                                                    <span class="size-2.5 rounded-full bg-amber-500/90 shadow-[0_0_6px_#f59e0b]" />
                                                    <span class="size-2.5 rounded-full bg-emerald-500/90 shadow-[0_0_6px_#10b981]" />
                                                    <span class="ml-2 font-mono text-[9px] font-extrabold uppercase tracking-wider text-slate-500 flex items-center gap-1">
                                                        <Terminal class="size-3" /> Trình xem thay đổi thuộc tính (Diff Viewer)
                                                    </span>
                                                </div>
                                                <div class="text-[9px] font-bold text-slate-500 font-mono">BẢN GHI: #{{ log.id }}</div>
                                            </div>
                                            
                                            <!-- Render Side-by-side or Single View based on data presence -->
                                            <div v-if="log.old_values && log.new_values" class="grid gap-4 md:grid-cols-2">
                                                <div>
                                                    <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-rose-400 flex items-center gap-1">
                                                        <Flame class="size-3" /> Dữ liệu trước thay đổi (Old Values)
                                                    </p>
                                                    <pre class="max-h-56 overflow-auto rounded-lg bg-rose-950/20 border border-rose-500/10 p-3.5 font-mono text-[11px] text-rose-300 leading-relaxed">{{ JSON.stringify(log.old_values, null, 2) }}</pre>
                                                </div>
                                                <div>
                                                    <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-emerald-400 flex items-center gap-1">
                                                        <CheckCircle2 class="size-3" /> Dữ liệu sau thay đổi (New Values)
                                                    </p>
                                                    <pre class="max-h-56 overflow-auto rounded-lg bg-emerald-950/20 border border-emerald-500/10 p-3.5 font-mono text-[11px] text-emerald-300 leading-relaxed">{{ JSON.stringify(log.new_values, null, 2) }}</pre>
                                                </div>
                                            </div>

                                            <div v-else-if="log.new_values" class="w-full">
                                                <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-emerald-400 flex items-center gap-1">
                                                    <Plus class="size-3" /> Dữ liệu được ghi nhận mới (Recorded Values)
                                                </p>
                                                <pre class="max-h-56 overflow-auto rounded-lg bg-emerald-950/20 border border-emerald-500/10 p-3.5 font-mono text-[11px] text-emerald-300 leading-relaxed">{{ JSON.stringify(log.new_values, null, 2) }}</pre>
                                            </div>

                                            <div v-else-if="log.old_values" class="w-full">
                                                <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-rose-400 flex items-center gap-1">
                                                    <Trash2 class="size-3" /> Dữ liệu đã xóa bỏ (Deleted Values)
                                                </p>
                                                <pre class="max-h-56 overflow-auto rounded-lg bg-rose-950/20 border border-rose-500/10 p-3.5 font-mono text-[11px] text-rose-300 leading-relaxed">{{ JSON.stringify(log.old_values, null, 2) }}</pre>
                                            </div>

                                            <div v-else class="text-center py-6 text-slate-500 font-mono text-xs italic">
                                                Không ghi nhận dữ liệu thuộc tính thay đổi chi tiết (Metadata logs).
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
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
