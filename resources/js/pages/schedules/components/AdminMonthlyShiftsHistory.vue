<script setup lang="ts">
import {
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    Eye,
    Filter,
    HelpCircle,
    Search,
    ShieldAlert,
    UserCheck,
    UserX,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface ShiftAssignment {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_code?: string;
    job_title?: string;
    scheduled_date: string;
    status: string;
    check_in_at?: string | null;
    check_out_at?: string | null;
    check_in_time?: string | null;
    check_out_time?: string | null;
    duration_hours?: number;
    late_minutes?: number | null;
    shift_id?: number;
    shift_name: string;
    shift_start?: string;
    shift_end?: string;
    check_in_photo_path?: string | null;
    is_shift_leader?: boolean;
    notes?: string | null;
    pay_rate?: number;
}

interface ShiftClosing {
    id: number;
    shift_id: number;
    shift_name: string;
    closing_date: string;
    cashier_name?: string;
    expected_cash: number;
    actual_cash: number;
    cash_difference: number;
    transfer_amount?: number;
    other_expense_amount?: number;
    closed_at?: string | null;
    notes?: string | null;
}

const props = defineProps<{
    monthlyAssignments: ShiftAssignment[];
    monthlyShiftClosings?: ShiftClosing[];
    shifts?: Array<{ id: number; name: string; start: string; end: string }>;
}>();

// State
const searchQuery = ref('');
const selectedShiftFilter = ref('all');
const selectedStatusFilter = ref('all');
const selectedMonth = ref(new Date().toISOString().slice(0, 7)); // YYYY-MM

// Modal detail state
const selectedShiftGroup = ref<{
    key: string;
    date: string;
    formattedDate: string;
    shiftName: string;
    shiftTime: string;
    assignments: ShiftAssignment[];
    closing?: ShiftClosing | null;
    totalStaff: number;
    onTimeCount: number;
    lateCount: number;
    absentCount: number;
} | null>(null);

const showDetailModal = ref(false);

// Format helper currency
const formatVND = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount);
};

// Format date string to Vietnamese format (e.g., 05/08/2026 - Thứ Tư)
const formatDayLabel = (dateStr: string) => {
    if (!dateStr) {
        return '';
    }

    const d = new Date(dateStr);
    const dayNames = [
        'Chủ Nhật',
        'Thứ Hai',
        'Thứ Ba',
        'Thứ Tư',
        'Thứ Năm',
        'Thứ Sáu',
        'Thứ Bảy',
    ];
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();

    return `${dd}/${mm}/${yyyy} (${dayNames[d.getDay()]})`;
};

// Group assignments into unique shift occurrences (by Date + Shift)
const shiftGroups = computed(() => {
    const map = new Map<
        string,
        {
            key: string;
            date: string;
            formattedDate: string;
            shiftName: string;
            shiftTime: string;
            assignments: ShiftAssignment[];
            closing?: ShiftClosing | null;
            totalStaff: number;
            onTimeCount: number;
            lateCount: number;
            absentCount: number;
        }
    >();

    const closingsMap = new Map<string, ShiftClosing>();

    if (props.monthlyShiftClosings) {
        for (const c of props.monthlyShiftClosings) {
            const key = `${c.closing_date}_${c.shift_id}`;
            closingsMap.set(key, c);
        }
    }

    const assignments = props.monthlyAssignments || [];

    for (const a of assignments) {
        // Filter by selected month if set
        if (
            selectedMonth.value &&
            !a.scheduled_date.startsWith(selectedMonth.value)
        ) {
            continue;
        }

        const groupKey = `${a.scheduled_date}_${a.shift_name}`;

        if (!map.has(groupKey)) {
            let timeStr = '—';

            if (a.shift_start && a.shift_end) {
                timeStr = `${a.shift_start} - ${a.shift_end}`;
            }

            const closingKey = a.shift_id
                ? `${a.scheduled_date}_${a.shift_id}`
                : '';
            const closing = closingsMap.get(closingKey) || null;

            map.set(groupKey, {
                key: groupKey,
                date: a.scheduled_date,
                formattedDate: formatDayLabel(a.scheduled_date),
                shiftName: a.shift_name,
                shiftTime: timeStr,
                assignments: [],
                closing,
                totalStaff: 0,
                onTimeCount: 0,
                lateCount: 0,
                absentCount: 0,
            });
        }

        const group = map.get(groupKey)!;
        group.assignments.push(a);
        group.totalStaff++;

        if (a.status === 'completed' || a.status === 'checked_in') {
            if (a.late_minutes && a.late_minutes > 0) {
                group.lateCount++;
            } else {
                group.onTimeCount++;
            }
        } else if (a.status === 'absent') {
            group.absentCount++;
        }
    }

    return Array.from(map.values()).sort(
        (a, b) => new Date(b.date).getTime() - new Date(a.date).getTime(),
    );
});

// Filtered shift groups based on search and filters
const filteredShiftGroups = computed(() => {
    return shiftGroups.value.filter((g) => {
        // Shift type filter
        if (
            selectedShiftFilter.value !== 'all' &&
            g.shiftName !== selectedShiftFilter.value
        ) {
            return false;
        }

        // Status filter
        if (selectedStatusFilter.value === 'ontime_only' && g.lateCount > 0) {
            return false;
        }

        if (selectedStatusFilter.value === 'has_late' && g.lateCount === 0) {
            return false;
        }

        if (
            selectedStatusFilter.value === 'has_absent' &&
            g.absentCount === 0
        ) {
            return false;
        }

        if (selectedStatusFilter.value === 'has_closing' && !g.closing) {
            return false;
        }

        if (
            selectedStatusFilter.value === 'cash_diff' &&
            (!g.closing || g.closing.cash_difference === 0)
        ) {
            return false;
        }

        // Text search
        if (searchQuery.value.trim()) {
            const q = searchQuery.value.toLowerCase().trim();
            const matchDate = g.formattedDate.toLowerCase().includes(q);
            const matchShift = g.shiftName.toLowerCase().includes(q);
            const matchStaff = g.assignments.some(
                (a) =>
                    a.employee_name.toLowerCase().includes(q) ||
                    (a.employee_code &&
                        a.employee_code.toLowerCase().includes(q)),
            );

            if (!matchDate && !matchShift && !matchStaff) {
                return false;
            }
        }

        return true;
    });
});

// Overall summary statistics
const statsOverview = computed(() => {
    const groups = shiftGroups.value;
    const totalGroups = groups.length;
    let totalAssignments = 0;
    let totalOnTime = 0;
    let totalLate = 0;
    let totalAbsent = 0;
    let totalCashClosing = 0;
    let totalDiscrepancies = 0;

    for (const g of groups) {
        totalAssignments += g.totalStaff;
        totalOnTime += g.onTimeCount;
        totalLate += g.lateCount;
        totalAbsent += g.absentCount;

        if (g.closing) {
            totalCashClosing += g.closing.actual_cash || 0;

            if (g.closing.cash_difference !== 0) {
                totalDiscrepancies++;
            }
        }
    }

    const onTimeRate =
        totalAssignments > 0
            ? Math.round((totalOnTime / totalAssignments) * 100)
            : 0;

    return {
        totalGroups,
        totalAssignments,
        totalOnTime,
        totalLate,
        totalAbsent,
        onTimeRate,
        totalCashClosing,
        totalDiscrepancies,
    };
});

const openDetailModal = (group: (typeof shiftGroups.value)[0]) => {
    selectedShiftGroup.value = group;
    showDetailModal.value = true;
};
</script>

<template>
    <div class="space-y-6">
        <!-- Monthly Header & Controls -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h3
                    class="flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-white"
                >
                    <Calendar
                        class="size-5 text-indigo-600 dark:text-indigo-400"
                    />
                    Báo cáo Các Ca Làm Đã Diễn Ra Trong Tháng
                </h3>
                <p class="text-xs text-muted-foreground">
                    Theo dõi chi tiết lịch sử ca trực, tình trạng chấm công,
                    nhân sự và chốt két của từng ca làm việc.
                </p>
            </div>

            <!-- Month Selector -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-muted-foreground"
                    >Chọn Tháng:</span
                >
                <Input
                    type="month"
                    v-model="selectedMonth"
                    class="h-9 w-40 text-xs font-medium"
                />
            </div>
        </div>

        <!-- Summary KPI Cards -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
            <!-- Total Shifts -->
            <Card
                class="border-indigo-100 bg-gradient-to-br from-indigo-50/50 to-white dark:border-indigo-900/40 dark:from-indigo-950/20 dark:to-slate-900"
            >
                <CardHeader class="p-3 pb-1">
                    <CardTitle
                        class="flex items-center justify-between text-[11px] font-semibold text-indigo-600 dark:text-indigo-400"
                    >
                        <span>Tổng Ca Đã Diễn Ra</span>
                        <Calendar class="size-4 opacity-70" />
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-3 pt-0">
                    <div
                        class="text-xl font-bold text-slate-900 dark:text-white"
                    >
                        {{ statsOverview.totalGroups }}
                        <span class="text-xs font-normal text-muted-foreground"
                            >ca</span
                        >
                    </div>
                    <div class="text-[10px] text-muted-foreground">
                        {{ statsOverview.totalAssignments }} lượt phân ca
                    </div>
                </CardContent>
            </Card>

            <!-- On-time Rate -->
            <Card
                class="border-emerald-100 bg-gradient-to-br from-emerald-50/50 to-white dark:border-emerald-900/40 dark:from-emerald-950/20 dark:to-slate-900"
            >
                <CardHeader class="p-3 pb-1">
                    <CardTitle
                        class="flex items-center justify-between text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                    >
                        <span>Tỷ Lệ Đúng Giờ</span>
                        <CheckCircle2 class="size-4 opacity-70" />
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-3 pt-0">
                    <div
                        class="text-xl font-bold text-emerald-600 dark:text-emerald-400"
                    >
                        {{ statsOverview.onTimeRate }}%
                    </div>
                    <div class="text-[10px] text-muted-foreground">
                        {{ statsOverview.totalOnTime }} lượt đúng giờ
                    </div>
                </CardContent>
            </Card>

            <!-- Late & Absent -->
            <Card
                class="border-amber-100 bg-gradient-to-br from-amber-50/50 to-white dark:border-amber-900/40 dark:from-amber-950/20 dark:to-slate-900"
            >
                <CardHeader class="p-3 pb-1">
                    <CardTitle
                        class="flex items-center justify-between text-[11px] font-semibold text-amber-600 dark:text-amber-400"
                    >
                        <span>Đi Trễ / Vắng Mặt</span>
                        <Clock class="size-4 opacity-70" />
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-3 pt-0">
                    <div
                        class="text-xl font-bold text-amber-600 dark:text-amber-400"
                    >
                        {{ statsOverview.totalLate }}
                        <span class="text-xs font-normal text-rose-500"
                            >/ {{ statsOverview.totalAbsent }} vắng</span
                        >
                    </div>
                    <div class="text-[10px] text-muted-foreground">
                        Cần lưu ý nhắc nhở
                    </div>
                </CardContent>
            </Card>

            <!-- Cash Closing Total -->
            <Card
                class="border-blue-100 bg-gradient-to-br from-blue-50/50 to-white dark:border-blue-900/40 dark:from-blue-950/20 dark:to-slate-900"
            >
                <CardHeader class="p-3 pb-1">
                    <CardTitle
                        class="flex items-center justify-between text-[11px] font-semibold text-blue-600 dark:text-blue-400"
                    >
                        <span>Doanh Thu Két Ca</span>
                        <DollarSign class="size-4 opacity-70" />
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-3 pt-0">
                    <div
                        class="truncate text-lg font-bold text-blue-600 dark:text-blue-400"
                    >
                        {{ formatVND(statsOverview.totalCashClosing) }}
                    </div>
                    <div class="text-[10px] text-muted-foreground">
                        Tổng két chốt ca trong tháng
                    </div>
                </CardContent>
            </Card>

            <!-- Discrepancy Shifts -->
            <Card
                class="border-rose-100 bg-gradient-to-br from-rose-50/50 to-white dark:border-rose-900/40 dark:from-rose-950/20 dark:to-slate-900"
            >
                <CardHeader class="p-3 pb-1">
                    <CardTitle
                        class="flex items-center justify-between text-[11px] font-semibold text-rose-600 dark:text-rose-400"
                    >
                        <span>Ca Lệch Tiền Két</span>
                        <ShieldAlert class="size-4 opacity-70" />
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-3 pt-0">
                    <div
                        class="text-xl font-bold text-rose-600 dark:text-rose-400"
                    >
                        {{ statsOverview.totalDiscrepancies }}
                        <span class="text-xs font-normal text-muted-foreground"
                            >ca</span
                        >
                    </div>
                    <div class="text-[10px] text-muted-foreground">
                        Cần đối soát két chốt ca
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filter Bar -->
        <Card class="border-border/60 shadow-xs">
            <CardContent class="p-4">
                <div
                    class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                >
                    <!-- Search Input -->
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            placeholder="Tìm kiếm theo ngày, tên ca, tên nhân viên..."
                            class="h-9 pl-9 text-xs"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Shift Type Filter -->
                        <div class="flex items-center gap-1.5">
                            <Filter class="size-3.5 text-muted-foreground" />
                            <select
                                v-model="selectedShiftFilter"
                                class="h-9 rounded-md border border-input bg-background px-3 py-1 text-xs shadow-2xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option value="all">Tất cả ca làm</option>
                                <option
                                    v-for="s in shifts"
                                    :key="s.id"
                                    :value="s.name"
                                >
                                    {{ s.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <select
                            v-model="selectedStatusFilter"
                            class="h-9 rounded-md border border-input bg-background px-3 py-1 text-xs shadow-2xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="all">Tất cả trạng thái</option>
                            <option value="ontime_only">100% Đúng giờ</option>
                            <option value="has_late">Có đi trễ</option>
                            <option value="has_absent">Có vắng mặt</option>
                            <option value="has_closing">Đã chốt két</option>
                            <option value="cash_diff">Có lệch két tiền</option>
                        </select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Main Shifts History Table -->
        <Card class="overflow-hidden border-border/60 shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="bg-muted/50 font-semibold text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3">Ngày Diễn Ra</th>
                            <th class="px-4 py-3">Tên Ca & Khung Giờ</th>
                            <th class="px-4 py-3">Nhân Sự Trong Ca</th>
                            <th class="px-4 py-3">Tình Trạng Chấm Công</th>
                            <th class="px-4 py-3">Trạng Thái Chốt Két</th>
                            <th class="px-4 py-3 text-right">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/50">
                        <tr
                            v-for="g in filteredShiftGroups"
                            :key="g.key"
                            class="transition-colors hover:bg-muted/40"
                        >
                            <!-- Date -->
                            <td
                                class="px-4 py-3 font-semibold text-slate-900 dark:text-white"
                            >
                                {{ g.formattedDate }}
                            </td>

                            <!-- Shift & Time -->
                            <td class="px-4 py-3">
                                <div
                                    class="font-bold text-indigo-600 dark:text-indigo-400"
                                >
                                    {{ g.shiftName }}
                                </div>
                                <div
                                    class="font-mono text-[10px] text-muted-foreground"
                                >
                                    {{ g.shiftTime }}
                                </div>
                            </td>

                            <!-- Staff List Badges -->
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-1">
                                    <span
                                        v-for="a in g.assignments"
                                        :key="a.id"
                                        class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-medium text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                        :title="`${a.employee_name} (${a.job_title || 'Nhân sự'})`"
                                    >
                                        <span
                                            v-if="a.is_shift_leader"
                                            class="font-bold text-amber-500"
                                            title="Ca trưởng"
                                            >★</span
                                        >
                                        {{ a.employee_name }}
                                        <span
                                            v-if="a.late_minutes"
                                            class="font-semibold text-amber-600"
                                            >(+{{ a.late_minutes }}p)</span
                                        >
                                    </span>
                                </div>
                            </td>

                            <!-- Attendance Breakdown -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <Badge
                                        v-if="g.onTimeCount > 0"
                                        variant="outline"
                                        class="border-emerald-200 bg-emerald-50 text-[10px] text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300"
                                    >
                                        <UserCheck class="mr-1 size-3" />
                                        {{ g.onTimeCount }} Đúng giờ
                                    </Badge>

                                    <Badge
                                        v-if="g.lateCount > 0"
                                        variant="outline"
                                        class="border-amber-200 bg-amber-50 text-[10px] text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300"
                                    >
                                        <Clock class="mr-1 size-3" />
                                        {{ g.lateCount }} Trễ
                                    </Badge>

                                    <Badge
                                        v-if="g.absentCount > 0"
                                        variant="outline"
                                        class="border-rose-200 bg-rose-50 text-[10px] text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300"
                                    >
                                        <UserX class="mr-1 size-3" />
                                        {{ g.absentCount }} Vắng
                                    </Badge>
                                </div>
                            </td>

                            <!-- Cash Closing Status -->
                            <td class="px-4 py-3">
                                <div v-if="g.closing">
                                    <div
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ formatVND(g.closing.actual_cash) }}
                                    </div>
                                    <div class="text-[10px]">
                                        <span
                                            v-if="
                                                g.closing.cash_difference === 0
                                            "
                                            class="font-semibold text-emerald-600"
                                            >✓ Khớp 100%</span
                                        >
                                        <span
                                            v-else
                                            class="font-bold text-rose-600"
                                        >
                                            Lệch
                                            {{
                                                formatVND(
                                                    g.closing.cash_difference,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                                <span
                                    v-else
                                    class="text-[11px] text-muted-foreground italic"
                                >
                                    Chưa chốt két
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3 text-right">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="openDetailModal(g)"
                                    class="h-7 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/40"
                                >
                                    <Eye class="mr-1 size-3.5" /> Chi tiết
                                </Button>
                            </td>
                        </tr>

                        <!-- Empty state -->
                        <tr v-if="filteredShiftGroups.length === 0">
                            <td
                                colspan="6"
                                class="py-12 text-center text-xs text-muted-foreground"
                            >
                                <HelpCircle
                                    class="mx-auto mb-2 size-8 opacity-40"
                                />
                                Không tìm thấy dữ liệu ca làm việc nào phù hợp
                                với bộ lọc trong tháng.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Audit Details Modal -->
        <Teleport to="body">
            <div
                v-if="showDetailModal && selectedShiftGroup"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-xs"
            >
                <Card
                    class="w-full max-w-3xl animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between border-b pb-3"
                    >
                        <div>
                            <CardTitle
                                class="text-base text-indigo-600 dark:text-indigo-400"
                            >
                                Chi Tiết Ca Làm:
                                {{ selectedShiftGroup.shiftName }} ({{
                                    selectedShiftGroup.shiftTime
                                }})
                            </CardTitle>
                            <p class="text-xs text-muted-foreground">
                                {{ selectedShiftGroup.formattedDate }}
                            </p>
                        </div>
                        <button
                            @click="showDetailModal = false"
                            class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            <X class="size-4" />
                        </button>
                    </CardHeader>

                    <CardContent
                        class="max-h-[75vh] space-y-6 overflow-y-auto p-4"
                    >
                        <!-- Employee Roster & Attendance Details -->
                        <div>
                            <h4
                                class="mb-3 text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-300"
                            >
                                Danh Sách Nhân Sự & Giờ Chấm Công
                            </h4>
                            <div
                                class="divide-y divide-border/60 rounded-lg border"
                            >
                                <div
                                    v-for="a in selectedShiftGroup.assignments"
                                    :key="a.id"
                                    class="flex items-center justify-between p-3"
                                >
                                    <div class="flex items-center gap-3">
                                        <!-- Photo or Avatar -->
                                        <img
                                            v-if="a.check_in_photo_path"
                                            :src="a.check_in_photo_path"
                                            class="size-10 rounded-full border border-indigo-200 object-cover shadow-2xs"
                                            alt="Check-in photo"
                                        />
                                        <div
                                            v-else
                                            class="flex size-10 items-center justify-center rounded-full bg-indigo-100 font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                                        >
                                            {{ a.employee_name.charAt(0) }}
                                        </div>

                                        <div>
                                            <div
                                                class="font-bold text-slate-900 dark:text-white"
                                            >
                                                {{ a.employee_name }}
                                                <span
                                                    v-if="a.is_shift_leader"
                                                    class="ml-1 rounded-sm bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold text-amber-700"
                                                    >Ca Trưởng</span
                                                >
                                            </div>
                                            <div
                                                class="text-[10px] text-muted-foreground"
                                            >
                                                {{ a.employee_code || '—' }} •
                                                {{ a.job_title || 'Nhân viên' }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <!-- Attendance status -->
                                        <div class="text-xs font-semibold">
                                            <span
                                                v-if="
                                                    a.status === 'completed' ||
                                                    a.status === 'checked_in'
                                                "
                                                class="font-bold text-emerald-600"
                                            >
                                                Check-in:
                                                {{
                                                    a.check_in_time || 'Chưa rõ'
                                                }}
                                                <template
                                                    v-if="a.check_out_time"
                                                >
                                                    →
                                                    {{
                                                        a.check_out_time
                                                    }}</template
                                                >
                                            </span>
                                            <span
                                                v-else-if="
                                                    a.status === 'absent'
                                                "
                                                class="font-bold text-rose-600"
                                                >Vắng mặt</span
                                            >
                                            <span v-else class="text-slate-400"
                                                >Chưa check-in</span
                                            >
                                        </div>

                                        <div
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            <span
                                                v-if="a.late_minutes"
                                                class="font-semibold text-amber-600"
                                                >Trễ
                                                {{ a.late_minutes }} phút</span
                                            >
                                            <span v-else-if="a.duration_hours"
                                                >Tổng giờ:
                                                {{ a.duration_hours }}h</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cashier Closing Info (if available) -->
                        <div v-if="selectedShiftGroup.closing">
                            <h4
                                class="mb-3 text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-300"
                            >
                                Thông Tin Chốt Két Ca Làm
                            </h4>
                            <div
                                class="grid grid-cols-2 gap-3 rounded-lg border bg-muted/30 p-3 text-xs sm:grid-cols-4"
                            >
                                <div>
                                    <div
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Người Chốt Két
                                    </div>
                                    <div
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{
                                            selectedShiftGroup.closing
                                                .cashier_name
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Tiền Kỳ Vọng
                                    </div>
                                    <div
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{
                                            formatVND(
                                                selectedShiftGroup.closing
                                                    .expected_cash,
                                            )
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Tiền Thực Tế
                                    </div>
                                    <div
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{
                                            formatVND(
                                                selectedShiftGroup.closing
                                                    .actual_cash,
                                            )
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Chênh Lệch Két
                                    </div>
                                    <div
                                        class="font-bold"
                                        :class="
                                            selectedShiftGroup.closing
                                                .cash_difference === 0
                                                ? 'text-emerald-600'
                                                : 'text-rose-600'
                                        "
                                    >
                                        {{
                                            formatVND(
                                                selectedShiftGroup.closing
                                                    .cash_difference,
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </Teleport>
    </div>
</template>
