<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    Users,
    ClipboardList,
    CalendarCheck,
    Award,
    Plus,
    UserCheck,
    UserPlus,
    Search,
    ArrowRightLeft,
    PauseCircle,
    PlayCircle
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { assignSupervisor, toggleStatus } from '@/routes/warehouse/team';
import { assign, reassign } from '@/routes/warehouse/team/tasks';
import { approve } from '@/routes/warehouse/team/leave';
import WarehouseAiRecommendations from '@/components/WarehouseAiRecommendations.vue';

interface StaffMember {
    id: number;
    name: string;
    email: string;
    phone: string;
    avatar_url?: string;
    supervisor_id?: number;
    supervisor_name: string;
    warehouse_branch_id?: number;
    warehouse_branch_name: string;
    warehouse_staff_status: 'active' | 'paused' | 'inactive';
    active_tasks_count: number;
    overdue_tasks_count: number;
    kpi_score: number;
    completion_rate: number;
    on_time_rate: number;
    incidents_count: number;
}

interface Supervisor {
    id: number;
    name: string;
    email: string;
    avatar_url?: string;
}

interface TaskAssignment {
    id: number;
    task_type: string;
    status: string;
    priority: string;
    due_at?: string;
    notes?: string;
    assignee?: { id: number; name: string; avatar_url?: string };
    assigner?: { id: number; name: string };
    supply_request?: { id: number; to_branch?: { name: string } };
}

interface LeaveReq {
    id: number;
    user: { id: number; name: string; email: string; avatar_url?: string };
    leave_type: string;
    start_date: string;
    end_date: string;
    reason?: string;
    status: 'pending' | 'approved' | 'rejected';
}

interface KpiItem {
    id: number;
    name: string;
    rank: number;
    composite_score: number;
    completion_rate: number;
    on_time_rate: number;
    avg_duration_minutes: number;
    discrepancy_rate: number;
    incidents_count: number;
    handover_compliance_rate: number;
}

const props = defineProps<{
    centralBranch?: { id: number; name: string };
    staffMembers: StaffMember[];
    supervisors: Supervisor[];
    recentTasks: TaskAssignment[];
    leaveRequests: LeaveReq[];
    teamKpi: KpiItem[];
    taskTypes: { value: string; label: string }[];
    centralWarehouseAi?: any;
}>();

const activeTab = ref<'directory' | 'tasks' | 'shifts' | 'kpi'>('directory');
const searchQuery = ref('');

// Modals
const showSupervisorModal = ref(false);
const selectedStaffForSupervisor = ref<StaffMember | null>(null);
const supervisorForm = useForm({
    staff_user_id: 0,
    supervisor_user_id: '',
    notes: '',
});

const showAssignTaskModal = ref(false);
const taskForm = useForm({
    assigned_to: '',
    task_type: 'picking',
    priority: 'normal',
    due_at: '',
    notes: '',
});

const showReassignModal = ref(false);
const selectedTaskForReassign = ref<TaskAssignment | null>(null);
const reassignForm = useForm({
    new_assigned_to: '',
    reason: '',
});

const leaveForm = useForm({
    status: 'approved',
    response_notes: '',
});
const selectedLeaveId = ref<number | null>(null);

// Filtered Staff
const filteredStaff = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.staffMembers;
    }
    const q = searchQuery.value.toLowerCase();
    return props.staffMembers.filter(
        s => s.name.toLowerCase().includes(q) || s.email.toLowerCase().includes(q) || s.phone?.includes(q)
    );
});

function openSupervisorModal(staff: StaffMember) {
    selectedStaffForSupervisor.value = staff;
    supervisorForm.staff_user_id = staff.id;
    supervisorForm.supervisor_user_id = staff.supervisor_id ? String(staff.supervisor_id) : '';
    supervisorForm.notes = '';
    showSupervisorModal.value = true;
}

function submitSupervisorAssignment() {
    supervisorForm.post(assignSupervisor.url(), {
        onSuccess: () => {
            showSupervisorModal.value = false;
        },
    });
}

function toggleStaffStatus(staff: StaffMember) {
    const newStatus = staff.warehouse_staff_status === 'active' ? 'paused' : 'active';
    router.post(toggleStatus.url(), {
        staff_user_id: staff.id,
        status: newStatus,
    });
}

function openAssignTaskModal(staff?: StaffMember) {
    taskForm.reset();
    if (staff) {
        taskForm.assigned_to = String(staff.id);
    }
    showAssignTaskModal.value = true;
}

function submitAssignTask() {
    taskForm.post(assign.url(), {
        onSuccess: () => {
            showAssignTaskModal.value = false;
        },
    });
}

function openReassignModal(task: TaskAssignment) {
    selectedTaskForReassign.value = task;
    reassignForm.new_assigned_to = task.assignee ? String(task.assignee.id) : '';
    reassignForm.reason = '';
    showReassignModal.value = true;
}

function submitReassign() {
    if (!selectedTaskForReassign.value) {
        return;
    }
    reassignForm.post(reassign.url(selectedTaskForReassign.value.id), {
        onSuccess: () => {
            showReassignModal.value = false;
        },
    });
}

function handleLeaveProcess(leaveId: number, status: 'approved' | 'rejected') {
    selectedLeaveId.value = leaveId;
    leaveForm.status = status;
    leaveForm.post(approve.url(leaveId));
}

function getTaskTypeLabel(type: string) {
    return props.taskTypes.find(t => t.value === type)?.label || type;
}

function getPriorityBadge(priority: string) {
    switch (priority) {
        case 'urgent': return 'bg-rose-500/10 text-rose-600 border-rose-200 dark:border-rose-900';
        case 'high': return 'bg-amber-500/10 text-amber-600 border-amber-200 dark:border-amber-900';
        case 'low': return 'bg-slate-500/10 text-slate-600 border-slate-200 dark:border-slate-800';
        default: return 'bg-blue-500/10 text-blue-600 border-blue-200 dark:border-blue-900';
    }
}
</script>

<template>
    <Head title="Quản Lý Đội Ngũ Kho Tổng" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 border-b border-slate-200/80 pb-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div class="flex size-12 items-center justify-center rounded-2xl border border-indigo-100 bg-indigo-50 text-indigo-600 shadow-sm dark:border-indigo-900/30 dark:bg-indigo-950/60 dark:text-indigo-400">
                    <Users class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100">
                        Đội Ngũ Kho Tổng
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ centralBranch?.name || 'Kho Tổng' }} • Quản lý nhân sự, Trưởng kho trực tiếp, xếp ca và chỉ số KPI.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Link href="/employees">
                    <Button variant="outline" class="gap-2 border-indigo-200 text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-300 dark:hover:bg-indigo-950">
                        <UserPlus class="size-4" />
                        Tạo Nhân Viên Kho
                    </Button>
                </Link>
                <Button @click="openAssignTaskModal()" class="gap-2 bg-indigo-600 font-semibold text-white hover:bg-indigo-700 dark:bg-indigo-500">
                    <Plus class="size-4" />
                    Giao Nhiệm Vụ Mới
                </Button>
            </div>
        </div>

        <!-- Metric Summary Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card class="border-indigo-100 shadow-sm dark:border-indigo-950/30">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-indigo-500">Nhân Viên Kho</CardDescription>
                    <Users class="size-4 text-indigo-500" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-black text-indigo-600 tabular-nums dark:text-indigo-400">{{ staffMembers.length }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">thuộc phạm vi Kho Tổng</p>
                </CardContent>
            </Card>

            <Card class="border-emerald-100 shadow-sm dark:border-emerald-950/30">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-emerald-500">Nhiệm Vụ Đang Làm</CardDescription>
                    <ClipboardList class="size-4 text-emerald-500" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-black text-emerald-600 tabular-nums dark:text-emerald-400">
                        {{ recentTasks.filter(t => ['assigned', 'in_progress'].includes(t.status)).length }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">nhiệm vụ trong phiên</p>
                </CardContent>
            </Card>

            <Card class="border-amber-100 shadow-sm dark:border-amber-950/30">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-amber-500">Đơn Xin Nghỉ Phép</CardDescription>
                    <CalendarCheck class="size-4 text-amber-500" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-black text-amber-600 tabular-nums dark:text-amber-400">
                        {{ leaveRequests.filter(l => l.status === 'pending').length }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">đang chờ duyệt</p>
                </CardContent>
            </Card>

            <Card class="border-purple-100 shadow-sm dark:border-purple-950/30">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-purple-500">KPI Đội Ngũ</CardDescription>
                    <Award class="size-4 text-purple-500" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-black text-purple-600 tabular-nums dark:text-purple-400">
                        {{ teamKpi.length ? (teamKpi.reduce((acc, curr) => acc + curr.composite_score, 0) / teamKpi.length).toFixed(1) : '100' }}/100
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">điểm trung bình toàn đội</p>
                </CardContent>
            </Card>
        </div>

        <WarehouseAiRecommendations :initial-ai="props.centralWarehouseAi" context="team" :max="3" />

        <!-- Main Navigation Tabs -->
        <div class="flex items-center border-b border-slate-200 dark:border-slate-800">
            <button
                @click="activeTab = 'directory'"
                :class="[
                    'flex items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-bold transition-all',
                    activeTab === 'directory'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400'
                ]"
            >
                <Users class="size-4" />
                Đội Ngũ Nhân Viên
            </button>

            <button
                @click="activeTab = 'tasks'"
                :class="[
                    'flex items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-bold transition-all',
                    activeTab === 'tasks'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400'
                ]"
            >
                <ClipboardList class="size-4" />
                Phân Công Nhiệm Vụ
            </button>

            <button
                @click="activeTab = 'shifts'"
                :class="[
                    'flex items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-bold transition-all',
                    activeTab === 'shifts'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400'
                ]"
            >
                <CalendarCheck class="size-4" />
                Duyệt Nghỉ Phép & Lịch Ca
            </button>

            <button
                @click="activeTab = 'kpi'"
                :class="[
                    'flex items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-bold transition-all',
                    activeTab === 'kpi'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400'
                ]"
            >
                <Award class="size-4" />
                Báo Cáo KPI & Hiệu Suất
            </button>
        </div>

        <!-- TAB 1: STAFF DIRECTORY -->
        <div v-if="activeTab === 'directory'" class="flex flex-col gap-4">
            <div class="flex items-center justify-between gap-3">
                <div class="relative w-full max-w-sm">
                    <Search class="absolute left-3 top-2.5 size-4 text-slate-400" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Tìm theo tên, email, SĐT..."
                        class="h-9 rounded-xl pl-9 text-xs"
                    />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="staff in filteredStaff"
                    :key="staff.id"
                    :class="[
                        'overflow-hidden shadow-sm transition-all hover:-translate-y-0.5',
                        staff.warehouse_staff_status === 'paused' ? 'border-amber-300 opacity-80 dark:border-amber-900/50' : ''
                    ]"
                >
                    <CardHeader class="border-b bg-slate-50/50 pb-3 dark:bg-slate-900/50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-full bg-indigo-100 font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                    {{ staff.name.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ staff.name }}</h3>
                                    <p class="text-[11px] text-slate-500">{{ staff.email }}</p>
                                </div>
                            </div>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold border',
                                    staff.warehouse_staff_status === 'active'
                                        ? 'bg-emerald-500/10 text-emerald-600 border-emerald-200 dark:border-emerald-900'
                                        : 'bg-amber-500/10 text-amber-600 border-amber-200 dark:border-amber-900'
                                ]"
                            >
                                <span class="size-1.5 rounded-full" :class="staff.warehouse_staff_status === 'active' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                {{ staff.warehouse_staff_status === 'active' ? 'Đang Nhận Việc' : 'Tạm Dừng' }}
                            </span>
                        </div>
                    </CardHeader>

                    <CardContent class="flex flex-col gap-3 p-4 text-xs">
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 p-2 dark:bg-slate-800/40">
                            <span class="text-slate-500">Trưởng Kho trực tiếp:</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ staff.supervisor_name }}</span>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-lg border p-2 dark:border-slate-800">
                                <p class="text-[10px] uppercase font-bold text-slate-400">Nhiệm vụ đang làm</p>
                                <p class="text-base font-bold text-indigo-600 dark:text-indigo-400">{{ staff.active_tasks_count }}</p>
                            </div>
                            <div class="rounded-lg border p-2 dark:border-slate-800">
                                <p class="text-[10px] uppercase font-bold text-slate-400">Việc quá hạn</p>
                                <p class="text-base font-bold" :class="staff.overdue_tasks_count > 0 ? 'text-rose-600' : 'text-slate-700 dark:text-slate-300'">
                                    {{ staff.overdue_tasks_count }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Điểm KPI tổng hợp:</span>
                            <span class="font-bold text-purple-600 dark:text-purple-400">{{ staff.kpi_score }}/100</span>
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t pt-3">
                            <Button @click="toggleStaffStatus(staff)" variant="outline" size="sm" class="h-8 gap-1 text-[11px]">
                                <PauseCircle v-if="staff.warehouse_staff_status === 'active'" class="size-3.5 text-amber-600" />
                                <PlayCircle v-else class="size-3.5 text-emerald-600" />
                                {{ staff.warehouse_staff_status === 'active' ? 'Tạm Dừng' : 'Mở Lại' }}
                            </Button>

                            <Button @click="openSupervisorModal(staff)" variant="outline" size="sm" class="h-8 gap-1 text-[11px]">
                                <UserCheck class="size-3.5 text-indigo-600" />
                                Đổi Trưởng Kho
                            </Button>

                            <Button @click="openAssignTaskModal(staff)" size="sm" class="h-8 gap-1 bg-indigo-600 text-[11px] text-white hover:bg-indigo-700">
                                <Plus class="size-3.5" />
                                Giao Việc
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- TAB 2: TASK ASSIGNMENTS -->
        <div v-if="activeTab === 'tasks'" class="flex flex-col gap-4">
            <Card class="overflow-hidden shadow-sm">
                <CardHeader class="flex flex-col gap-3 border-b bg-slate-50/50 pb-4 lg:flex-row lg:items-center lg:justify-between dark:bg-slate-900/50">
                    <div>
                        <CardTitle class="text-base font-bold">Danh Sách Nhiệm Vụ Kho Tổng</CardTitle>
                        <CardDescription>Theo dõi tiến độ, người phụ trách và điều chuyển công việc.</CardDescription>
                    </div>
                    <Button @click="openAssignTaskModal()" size="sm" class="gap-1.5 bg-indigo-600 text-xs text-white hover:bg-indigo-700">
                        <Plus class="size-4" />
                        Giao Việc Mới
                    </Button>
                </CardHeader>

                <CardContent class="p-0">
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        <div
                            v-for="task in recentTasks"
                            :key="task.id"
                            class="flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-slate-50/60 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-slate-900/30"
                        >
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 flex size-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400">
                                    <ClipboardList class="size-4" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-bold text-slate-800 dark:text-slate-200">#{{ task.id }} - {{ getTaskTypeLabel(task.task_type) }}</span>
                                        <span :class="['rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase', getPriorityBadge(task.priority)]">
                                            {{ task.priority }}
                                        </span>
                                        <span :class="[
                                            'rounded-full px-2 py-0.5 text-[10px] font-bold',
                                            task.status === 'completed' ? 'bg-emerald-100 text-emerald-700' :
                                            task.status === 'in_progress' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700'
                                        ]">
                                            {{ task.status }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500">
                                        Phụ trách: <strong class="text-slate-700 dark:text-slate-300">{{ task.assignee?.name || 'Chưa nhận' }}</strong> •
                                        Người giao: {{ task.assigner?.name || 'Hệ thống' }}
                                    </p>
                                    <p v-if="task.notes" class="text-xs italic text-slate-400">{{ task.notes }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 sm:self-center">
                                <Button @click="openReassignModal(task)" variant="outline" size="sm" class="h-8 gap-1 text-xs">
                                    <ArrowRightLeft class="size-3.5 text-indigo-600" />
                                    Điều Chuyển
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- TAB 3: SHIFTS & LEAVE APPROVAL -->
        <div v-if="activeTab === 'shifts'" class="flex flex-col gap-4">
            <Card class="overflow-hidden shadow-sm">
                <CardHeader class="border-b bg-slate-50/50 pb-4 dark:bg-slate-900/50">
                    <CardTitle class="text-base font-bold">Duyệt Đơn Nghỉ Phép & Đổi Ca</CardTitle>
                    <CardDescription>Trưởng kho xem xét và phê duyệt nghỉ phép cho nhân viên thuộc kho của mình.</CardDescription>
                </CardHeader>

                <CardContent class="p-0">
                    <div v-if="leaveRequests.length === 0" class="py-12 text-center text-xs text-slate-400">
                        Chưa có đơn xin nghỉ phép nào.
                    </div>
                    <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                        <div
                            v-for="leave in leaveRequests"
                            :key="leave.id"
                            class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ leave.user.name }}</span>
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        {{ leave.leave_type }}
                                    </span>
                                    <span :class="[
                                        'rounded-full px-2 py-0.5 text-[10px] font-bold',
                                        leave.status === 'approved' ? 'bg-emerald-100 text-emerald-700' :
                                        leave.status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'
                                    ]">
                                        {{ leave.status }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500">
                                    Thời gian: {{ leave.start_date }} đến {{ leave.end_date }}
                                </p>
                                <p v-if="leave.reason" class="text-xs text-slate-600 dark:text-slate-400">
                                    Lý do: {{ leave.reason }}
                                </p>
                            </div>

                            <div v-if="leave.status === 'pending'" class="flex items-center gap-2">
                                <Button @click="handleLeaveProcess(leave.id, 'rejected')" variant="outline" size="sm" class="h-8 border-rose-200 text-xs text-rose-600 hover:bg-rose-50">
                                    Từ Chối
                                </Button>
                                <Button @click="handleLeaveProcess(leave.id, 'approved')" size="sm" class="h-8 bg-emerald-600 text-xs text-white hover:bg-emerald-700">
                                    Duyệt Nghỉ
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- TAB 4: KPI & PERFORMANCE REPORT -->
        <div v-if="activeTab === 'kpi'" class="flex flex-col gap-4">
            <Card class="overflow-hidden shadow-sm">
                <CardHeader class="border-b bg-slate-50/50 pb-4 dark:bg-slate-900/50">
                    <CardTitle class="text-base font-bold">Bảng Xếp Hạng & Báo Cáo KPI Đội Ngũ</CardTitle>
                    <CardDescription>Đánh giá tổng hợp dựa trên tỷ lệ hoàn thành, đúng hạn, sai lệch nhận hàng và quy trình bàn giao.</CardDescription>
                </CardHeader>

                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[11px] uppercase text-slate-500 dark:bg-slate-900">
                                <tr>
                                    <th class="px-4 py-3">Hạng</th>
                                    <th class="px-4 py-3">Nhân Viên</th>
                                    <th class="px-4 py-3 text-center">Tỷ Lệ Hoàn Thành</th>
                                    <th class="px-4 py-3 text-center">Đúng Hạn</th>
                                    <th class="px-4 py-3 text-center">Thời Gian Trung Bình</th>
                                    <th class="px-4 py-3 text-center">Tỷ Lệ Sai Lệch</th>
                                    <th class="px-4 py-3 text-right">Điểm KPI Composite</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="item in teamKpi" :key="item.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30">
                                    <td class="px-4 py-3 font-black text-slate-700 dark:text-slate-300">#{{ item.rank }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">{{ item.name }}</td>
                                    <td class="px-4 py-3 text-center font-bold text-emerald-600">{{ item.completion_rate }}%</td>
                                    <td class="px-4 py-3 text-center font-bold text-indigo-600">{{ item.on_time_rate }}%</td>
                                    <td class="px-4 py-3 text-center text-slate-600 dark:text-slate-400">{{ item.avg_duration_minutes }} phút</td>
                                    <td class="px-4 py-3 text-center font-bold" :class="item.discrepancy_rate > 0 ? 'text-rose-600' : 'text-slate-600'">
                                        {{ item.discrepancy_rate }}%
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-purple-600 dark:text-purple-400">{{ item.composite_score }}/100</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- MODAL: BỔ NHIỆM TRƯỞNG KHO TRỰC TIẾP -->
        <Teleport to="body">
        <div v-if="showSupervisorModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Bổ Nhiệm Trưởng Kho Trực Tiếp</h3>
                <p class="mt-1 text-xs text-slate-500">Gán Trưởng kho quản lý trực tiếp cho nhân viên <strong>{{ selectedStaffForSupervisor?.name }}</strong>.</p>

                <form @submit.prevent="submitSupervisorAssignment" class="mt-4 flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold">Chọn Trưởng kho quản lý</Label>
                        <select
                            v-model="supervisorForm.supervisor_user_id"
                            class="h-9 rounded-xl border border-slate-200 bg-background px-3 text-xs focus:outline-none dark:border-slate-800"
                        >
                            <option value="">-- Chưa bổ nhiệm --</option>
                            <option v-for="sup in supervisors" :key="sup.id" :value="String(sup.id)">
                                {{ sup.name }} ({{ sup.email }})
                            </option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold">Ghi chú bổ nhiệm</Label>
                        <Input v-model="supervisorForm.notes" placeholder="Lý do bổ nhiệm / phạm vi phụ trách..." class="text-xs" />
                    </div>

                    <div class="flex justify-end gap-2 border-t pt-3">
                        <Button type="button" variant="outline" @click="showSupervisorModal = false" class="text-xs">Hủy</Button>
                        <Button type="submit" :disabled="supervisorForm.processing" class="bg-indigo-600 text-xs text-white hover:bg-indigo-700">Lưu Bổ Nhiệm</Button>
                    </div>
                </form>
            </div>
        </div>
        </Teleport>

        <!-- MODAL: GIAO NHIỆM VỤ MỚI -->
        
        <div v-if="showAssignTaskModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Giao Nhiệm Vụ Kho Tổng</h3>
                <p class="mt-1 text-xs text-slate-500">Chỉ cho phép giao nhiệm vụ cho nhân viên có vai trò warehouse_staff đang hoạt động.</p>

                <form @submit.prevent="submitAssignTask" class="mt-4 flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold">Nhân viên thực hiện <span class="text-rose-500">*</span></Label>
                        <select
                            v-model="taskForm.assigned_to"
                            class="h-9 rounded-xl border border-slate-200 bg-background px-3 text-xs focus:outline-none dark:border-slate-800"
                        >
                            <option value="">-- Chọn nhân viên kho --</option>
                            <option
                                v-for="staff in staffMembers.filter(s => s.warehouse_staff_status === 'active')"
                                :key="staff.id"
                                :value="String(staff.id)"
                            >
                                {{ staff.name }} (Đang làm {{ staff.active_tasks_count }} việc)
                            </option>
                        </select>
                        <p v-if="taskForm.errors.assigned_to" class="text-[11px] font-semibold text-rose-500">{{ taskForm.errors.assigned_to }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold">Loại nhiệm vụ</Label>
                            <select
                                v-model="taskForm.task_type"
                                class="h-9 rounded-xl border border-slate-200 bg-background px-3 text-xs focus:outline-none dark:border-slate-800"
                            >
                                <option v-for="t in taskTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold">Mức ưu tiên</Label>
                            <select
                                v-model="taskForm.priority"
                                class="h-9 rounded-xl border border-slate-200 bg-background px-3 text-xs focus:outline-none dark:border-slate-800"
                            >
                                <option value="low">Thấp</option>
                                <option value="normal">Bình thường</option>
                                <option value="high">Cao</option>
                                <option value="urgent">Khẩn cấp</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold">Thời hạn hoàn thành (Due date)</Label>
                        <Input type="datetime-local" v-model="taskForm.due_at" class="text-xs" />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold">Ghi chú & Chỉ dẫn</Label>
                        <Input v-model="taskForm.notes" placeholder="Mô tả chi tiết nguyên liệu / vị trí / yêu cầu đặc biệt..." class="text-xs" />
                    </div>

                    <div class="flex justify-end gap-2 border-t pt-3">
                        <Button type="button" variant="outline" @click="showAssignTaskModal = false" class="text-xs">Hủy</Button>
                        <Button type="submit" :disabled="taskForm.processing" class="bg-indigo-600 text-xs text-white hover:bg-indigo-700">Xác Nhận Giao Việc</Button>
                    </div>
                </form>
            </div>
        </div>
        

        <!-- MODAL: ĐIỀU CHUYỂN NHIỆM VỤ -->
        <Teleport to="body">
        <div v-if="showReassignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Điều Chuyển Nhiệm Vụ #{{ selectedTaskForReassign?.id }}</h3>

                <form @submit.prevent="submitReassign" class="mt-4 flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold">Chuyển cho nhân viên khác</Label>
                        <select
                            v-model="reassignForm.new_assigned_to"
                            class="h-9 rounded-xl border border-slate-200 bg-background px-3 text-xs focus:outline-none dark:border-slate-800"
                        >
                            <option v-for="staff in staffMembers.filter(s => s.warehouse_staff_status === 'active')" :key="staff.id" :value="String(staff.id)">
                                {{ staff.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold">Lý do điều chuyển</Label>
                        <Input v-model="reassignForm.reason" placeholder="Quá tải, bận ca khác, hỗ trợ gấp..." class="text-xs" />
                    </div>

                    <div class="flex justify-end gap-2 border-t pt-3">
                        <Button type="button" variant="outline" @click="showReassignModal = false" class="text-xs">Hủy</Button>
                        <Button type="submit" :disabled="reassignForm.processing" class="bg-indigo-600 text-xs text-white hover:bg-indigo-700">Điều Chuyển</Button>
                    </div>
                </form>
            </div>
        </div>
        </Teleport>
    </div>
</template>
