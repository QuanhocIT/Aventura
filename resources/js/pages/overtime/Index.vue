<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    Banknote,
    CalendarDays,
    CheckCircle2,
    Clock3,
    Filter,
    Mail,
    Plus,
    Search,
    Settings2,
    ShieldCheck,
    UserRound,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Status = 'pending' | 'approved' | 'rejected';
type Action = 'approve' | 'reject' | 'accept' | 'decline';
type OvertimeType = 'normal' | 'night' | 'rest_day' | 'holiday';

type OvertimeRequest = {
    id: number;
    employee_id: number;
    employee_name: string | null;
    employee_code: string | null;
    scheduled_date: string;
    hours_requested: number;
    hours_approved: number;
    reason: string | null;
    status: Status;
    request_source: 'employee' | 'management';
    employee_response: string | null;
    rejection_reason: string | null;
    requester_name: string | null;
    overtime_type: OvertimeType;
    overtime_type_label: string;
    scheduled_start_at: string | null;
    scheduled_end_at: string | null;
    check_in_at: string | null;
    check_out_at: string | null;
    worked_hours: number;
    hourly_rate: number;
    overtime_multiplier: number;
    estimated_amount: number;
    actual_amount: number;
    payroll_status: string;
    workflow_status: string;
    manager_adjusted_hours: number;
    manager_adjusted_amount: number;
    attendance_verified_at: string | null;
    check_in_method: string | null;
    check_out_method: string | null;
    gps_distance_meters: number;
};

const props = defineProps<{
    requests: OvertimeRequest[];
    employees: Array<{
        id: number;
        full_name: string;
        employee_code: string;
        overtime_hourly_rate: number;
        compensation_type: string;
    }>;
    canManage: boolean;
    employeeId: number | null;
    currentEmployee: {
        id: number;
        full_name: string;
        employee_code: string;
        overtime_hourly_rate: number;
        compensation_type: string;
    } | null;
    policy: {
        types: Array<{
            value: OvertimeType;
            label: string;
            description: string;
            multiplier: number;
        }>;
        max_daily_hours: number;
        max_monthly_hours: number;
    };
    policySettings: {
        normal_multiplier: number;
        night_multiplier: number;
        rest_day_multiplier: number;
        holiday_multiplier: number;
        max_daily_hours: number;
        max_weekly_hours: number;
        max_monthly_hours: number;
        minimum_rest_hours: number;
        require_gps: boolean;
        require_qr: boolean;
        require_photo: boolean;
        employee_can_request: boolean;
        require_employee_acceptance: boolean;
    };
    report: {
        period: string;
        total_requests: number;
        approved_hours: number;
        worked_hours: number;
        estimated_amount: number;
        actual_amount: number;
        pending_reconciliation: number;
        by_type: Array<{ type: string; requests: number; hours: number; amount: number }>;
        by_employee: Array<{ employee_name: string | null; employee_code: string | null; hours: number; amount: number }>;
    } | null;
    attendanceSettings: { require_gps: boolean; require_qr: boolean; require_photo: boolean } | null;
    holidays: Array<{ id: number; holiday_date: string; name: string; multiplier: number }>;
}>();

const showForm = ref(false);
const searchQuery = ref('');
const statusFilter = ref<'all' | Status>('all');

const form = useForm({
    employee_id: (props.employees[0]?.id ?? '') as number | '',
    scheduled_date: new Date().toISOString().slice(0, 10),
    hours_requested: 2,
    start_time: '18:00',
    end_time: '20:00',
    overtime_type: 'normal' as OvertimeType,
    reason: '',
});

const policyForm = useForm({
    effective_from: new Date().toISOString().slice(0, 10),
    normal_multiplier: props.policySettings.normal_multiplier,
    night_multiplier: props.policySettings.night_multiplier,
    rest_day_multiplier: props.policySettings.rest_day_multiplier,
    holiday_multiplier: props.policySettings.holiday_multiplier,
    max_daily_hours: props.policySettings.max_daily_hours,
    max_weekly_hours: props.policySettings.max_weekly_hours,
    max_monthly_hours: props.policySettings.max_monthly_hours,
    minimum_rest_hours: props.policySettings.minimum_rest_hours,
    require_gps: props.policySettings.require_gps,
    require_qr: props.policySettings.require_qr,
    require_photo: props.policySettings.require_photo,
    employee_can_request: props.policySettings.employee_can_request,
    require_employee_acceptance: props.policySettings.require_employee_acceptance,
});

const holidayForm = useForm({ holiday_date: '', name: '', multiplier: 3 });

const selectedEmployee = computed(() =>
    props.canManage
        ? props.employees.find((employee) => employee.id === Number(form.employee_id)) ?? null
        : props.currentEmployee,
);

const selectedPolicy = computed(() =>
    props.policy.types.find((item) => item.value === form.overtime_type) ?? props.policy.types[0],
);

const estimatedAmount = computed(() =>
    Math.round(
        Number(form.hours_requested || 0) *
            Number(selectedEmployee.value?.overtime_hourly_rate || 0) *
            Number(selectedPolicy.value?.multiplier || 0),
    ),
);

const summary = computed(() => ({
    total: props.requests.length,
    pending: props.requests.filter((item) => item.status === 'pending').length,
    approvedHours: props.requests
        .filter((item) => item.status === 'approved')
        .reduce((total, item) => total + Number(item.hours_approved || 0), 0),
    approvedAmount: props.requests
        .filter((item) => item.status === 'approved')
        .reduce((total, item) => total + Number(item.estimated_amount || 0), 0),
    waitingEmployee: props.requests.filter(
        (item) => item.status === 'pending' && item.request_source === 'management' && item.employee_response === 'pending',
    ).length,
}));

const vnd = (amount: number) => `${Math.round(amount).toLocaleString('vi-VN')}đ`;

const today = () => new Date().toLocaleDateString('en-CA');

const canCheckIn = (item: OvertimeRequest) =>
    !props.canManage &&
    item.employee_id === props.employeeId &&
    item.status === 'approved' &&
    item.scheduled_date === today() &&
    !item.check_in_at;

const canCheckOut = (item: OvertimeRequest) =>
    !props.canManage &&
    item.employee_id === props.employeeId &&
    item.status === 'approved' &&
    Boolean(item.check_in_at) &&
    !item.check_out_at;

const filteredRequests = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase('vi-VN');

    return props.requests.filter((item) => {
        const matchesStatus = statusFilter.value === 'all' || item.status === statusFilter.value;
        const haystack = `${item.employee_name ?? ''} ${item.employee_code ?? ''} ${item.reason ?? ''}`.toLocaleLowerCase('vi-VN');

        return matchesStatus && (!query || haystack.includes(query));
    });
});

const formatDate = (date: string) => {
    const parsed = new Date(`${date}T00:00:00`);

    return Number.isNaN(parsed.getTime()) ? date : parsed.toLocaleDateString('vi-VN');
};

const statusLabel = (status: Status) =>
    status === 'approved' ? 'Đã duyệt' : status === 'rejected' ? 'Từ chối' : 'Chờ xử lý';

const sourceLabel = (source: OvertimeRequest['request_source']) =>
    source === 'management' ? 'Quản lý yêu cầu' : 'Nhân viên xin tăng ca';

const responseLabel = (item: OvertimeRequest) => {
    if (item.request_source !== 'management') {
return '';
}

    if (item.employee_response === 'accepted') {
return 'Nhân viên đã xác nhận';
}

    if (item.employee_response === 'declined') {
return 'Nhân viên đã từ chối';
}

    return 'Đang chờ nhân viên xác nhận';
};

const openCreate = () => {
    form.employee_id = props.employees[0]?.id ?? '';
    form.scheduled_date = new Date().toISOString().slice(0, 10);
    form.hours_requested = 2;
    form.start_time = '18:00';
    form.end_time = '20:00';
    form.overtime_type = 'normal';
    form.reason = '';
    form.clearErrors();
    showForm.value = true;
};

const submit = () => {
    form.post('/overtime-requests', {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset('hours_requested', 'start_time', 'end_time', 'overtime_type', 'reason');
        },
    });
};

const attendance = (id: number, action: 'check-in' | 'check-out') => {
    const send = (location?: GeolocationPosition) => {
        router.post(`/overtime-requests/${id}/${action}`, location ? {
            latitude: location.coords.latitude,
            longitude: location.coords.longitude,
            accuracy: location.coords.accuracy,
        } : {}, { preserveScroll: true });
    };

    if (props.attendanceSettings?.require_gps && navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(send, () => send(), { enableHighAccuracy: true, timeout: 10000 });
    } else {
        send();
    }
};

const act = (id: number, action: Action) => {
    router.patch(
        `/overtime-requests/${id}/${action}`,
        action === 'reject' || action === 'decline'
            ? { rejection_reason: 'Không phù hợp nhu cầu vận hành.' }
            : {},
        { preserveScroll: true },
    );
};

const withdraw = (id: number) => {
    if (window.confirm('Bạn có chắc muốn rút đơn OT này?')) {
        router.patch(`/overtime-requests/${id}/withdraw`, {}, { preserveScroll: true });
    }
};

const cancelRequest = (id: number) => {
    const reason = window.prompt('Lý do huỷ yêu cầu OT:');

    if (reason) {
router.patch(`/overtime-requests/${id}/cancel`, { cancel_reason: reason }, { preserveScroll: true });
}
};

const reconcile = (item: OvertimeRequest) => {
    const hours = window.prompt('Số giờ OT thực tế:', String(item.worked_hours || item.hours_approved));

    if (hours === null) {
return;
}

    const reason = window.prompt('Lý do đối soát / điều chỉnh:', 'Đã kiểm tra chấm công OT');

    if (!reason) {
return;
}

    router.patch(`/overtime-requests/${item.id}/reconcile`, { worked_hours: Number(hours), adjustment_reason: reason }, { preserveScroll: true });
};

const savePolicy = () => {
    policyForm.post('/overtime-policies', { preserveScroll: true });
};

const saveHoliday = () => {
    holidayForm.post('/overtime-holidays', { preserveScroll: true, onSuccess: () => holidayForm.reset() });
};

const deleteHoliday = (id: number) => {
    if (window.confirm('Tắt ngày lễ này khỏi lịch tính OT?')) {
router.delete(`/overtime-holidays/${id}`, { preserveScroll: true });
}
};

const exportReport = () => {
    window.location.href = '/overtime-requests/export';
};
</script>

<template>
    <Head title="Tăng ca" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <header class="flex flex-col gap-4 border-b border-border/70 pb-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-500">
                    <Clock3 class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Tăng ca</h1>
                    <p class="text-sm text-muted-foreground">Đăng ký, duyệt, chấm công và tính tiền OT theo đúng giờ thực tế.</p>
                </div>
            </div>
            <Button class="bg-indigo-600 text-white shadow-sm hover:bg-indigo-700" @click="openCreate">
                <Plus class="mr-2 size-4" />
                {{ props.canManage ? 'Gửi yêu cầu đột xuất' : 'Xin tăng ca' }}
            </Button>
        </header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border bg-card p-5 shadow-sm">
                <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Tổng yêu cầu</p>
                <p class="mt-2 text-3xl font-black">{{ summary.total }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Trong phạm vi dữ liệu hiện tại</p>
            </div>
            <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5">
                <p class="text-xs font-semibold tracking-wide text-amber-700 uppercase dark:text-amber-300">Đang chờ xử lý</p>
                <p class="mt-2 text-3xl font-black text-amber-600">{{ summary.pending }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Cần quản lý hoặc nhân viên phản hồi</p>
            </div>
            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5">
                <p class="text-xs font-semibold tracking-wide text-emerald-700 uppercase dark:text-emerald-300">Giờ OT đã duyệt</p>
                <p class="mt-2 text-3xl font-black text-emerald-600">{{ summary.approvedHours.toFixed(2) }}h</p>
                <p class="mt-1 text-xs text-muted-foreground">Sẽ tính vào bảng lương khi đủ chấm công</p>
            </div>
            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5">
                <p class="text-xs font-semibold tracking-wide text-emerald-700 uppercase dark:text-emerald-300">Tiền OT dự kiến</p>
                <p class="mt-2 text-2xl font-black text-emerald-600">{{ vnd(summary.approvedAmount) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Theo đơn giá và hệ số đã snapshot</p>
            </div>
            <div class="rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-5">
                <p class="text-xs font-semibold tracking-wide text-indigo-700 uppercase dark:text-indigo-300">Chờ nhân viên xác nhận</p>
                <p class="mt-2 text-3xl font-black text-indigo-600">{{ summary.waitingEmployee }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Yêu cầu đột xuất đã gửi email</p>
            </div>
        </div>

        <div class="flex flex-col gap-3 rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-4 text-sm sm:flex-row sm:items-center">
            <ShieldCheck class="size-5 shrink-0 text-indigo-500" />
            <p class="text-muted-foreground"><span class="font-semibold text-foreground">Cách tính:</span> đơn giá giờ quy đổi × hệ số OT × giờ thực tế (không vượt giờ được duyệt).</p>
            <span v-if="props.canManage" class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 sm:ml-auto"><Mail class="size-3.5" /> Yêu cầu đột xuất sẽ gửi Gmail</span>
            <span v-else class="text-xs font-semibold text-indigo-600 sm:ml-auto">Giới hạn: {{ props.policy.max_daily_hours }}h/ngày · {{ props.policy.max_monthly_hours }}h/tháng</span>
        </div>

        <section v-if="props.canManage && props.report" class="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
            <div class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold">Báo cáo chi phí OT · {{ props.report.period }}</h2>
                        <p class="text-xs text-muted-foreground">Dùng để đối soát trước khi chốt bảng lương.</p>
                    </div>
                    <Button size="sm" variant="outline" @click="exportReport">Xuất CSV</Button>
                </div>
                <div class="grid gap-3 sm:grid-cols-4">
                    <div class="rounded-xl bg-muted/40 p-3"><p class="text-xs text-muted-foreground">Đơn OT</p><p class="mt-1 text-xl font-black">{{ props.report.total_requests }}</p></div>
                    <div class="rounded-xl bg-muted/40 p-3"><p class="text-xs text-muted-foreground">Giờ thực tế</p><p class="mt-1 text-xl font-black">{{ props.report.worked_hours.toFixed(2) }}h</p></div>
                    <div class="rounded-xl bg-emerald-500/10 p-3"><p class="text-xs text-muted-foreground">Tiền OT</p><p class="mt-1 text-lg font-black text-emerald-600">{{ vnd(props.report.actual_amount) }}</p></div>
                    <div class="rounded-xl bg-amber-500/10 p-3"><p class="text-xs text-muted-foreground">Cần đối soát</p><p class="mt-1 text-xl font-black text-amber-600">{{ props.report.pending_reconciliation }}</p></div>
                </div>
                <div v-if="props.report.by_employee.length" class="mt-4 divide-y rounded-xl border">
                    <div v-for="row in props.report.by_employee" :key="(row.employee_code ?? row.employee_name) as string" class="flex items-center justify-between px-3 py-2 text-sm">
                        <span>{{ row.employee_name }} <span class="text-xs text-muted-foreground">({{ row.employee_code }})</span></span>
                        <span class="font-semibold">{{ row.hours.toFixed(2) }}h · {{ vnd(row.amount) }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2"><Settings2 class="size-4 text-indigo-500" /><h2 class="font-bold">Chính sách OT áp dụng từ</h2></div>
                <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="savePolicy">
                    <label class="flex items-center gap-2 text-xs sm:col-span-2"><input v-model="policyForm.require_qr" type="checkbox" /> Bắt buộc QR khi chấm công OT</label>
                    <div class="grid gap-1.5 sm:col-span-2"><Label>Ngày hiệu lực</Label><Input v-model="policyForm.effective_from" type="date" required /></div>
                    <div v-for="field in [{ key: 'normal_multiplier', label: 'Ngày thường' }, { key: 'night_multiplier', label: 'Ban đêm' }, { key: 'rest_day_multiplier', label: 'Ngày nghỉ' }, { key: 'holiday_multiplier', label: 'Ngày lễ' }]" :key="field.key" class="grid gap-1.5"><Label>{{ field.label }} (x)</Label><Input v-model.number="policyForm[field.key as keyof typeof policyForm]" type="number" min="1" max="10" step="0.1" /></div>
                    <div class="grid gap-1.5"><Label>Tối đa/ngày</Label><Input v-model.number="policyForm.max_daily_hours" type="number" min="0.25" step="0.25" /></div>
                    <div class="grid gap-1.5"><Label>Tối đa/tuần</Label><Input v-model.number="policyForm.max_weekly_hours" type="number" min="0.25" step="0.25" /></div>
                    <div class="grid gap-1.5"><Label>Tối đa/tháng</Label><Input v-model.number="policyForm.max_monthly_hours" type="number" min="0.25" step="0.25" /></div>
                    <div class="grid gap-1.5"><Label>Nghỉ tối thiểu (giờ)</Label><Input v-model.number="policyForm.minimum_rest_hours" type="number" min="0" step="0.5" /></div>
                    <label class="flex items-center gap-2 text-xs sm:col-span-2"><input v-model="policyForm.require_gps" type="checkbox" /> Bắt buộc GPS khi chấm công OT</label>
                    <label class="flex items-center gap-2 text-xs sm:col-span-2"><input v-model="policyForm.require_photo" type="checkbox" /> Bắt buộc ảnh xác nhận khi chấm công OT</label>
                    <Button type="submit" class="sm:col-span-2 bg-indigo-600 text-white hover:bg-indigo-700" :disabled="policyForm.processing">Lưu chính sách</Button>
                </form>
                <div class="mt-5 border-t pt-4">
                    <p class="mb-2 text-xs font-semibold text-muted-foreground">Lịch ngày lễ / ngày đặc biệt</p>
                    <form class="grid gap-2 sm:grid-cols-[1fr_1.5fr_0.7fr_auto]" @submit.prevent="saveHoliday">
                        <Input v-model="holidayForm.holiday_date" type="date" required />
                        <Input v-model="holidayForm.name" placeholder="Tên ngày lễ" required />
                        <Input v-model.number="holidayForm.multiplier" type="number" min="1" step="0.1" />
                        <Button size="sm" type="submit" :disabled="holidayForm.processing">Thêm</Button>
                    </form>
                    <div v-if="props.holidays.length" class="mt-3 space-y-1 text-xs">
                        <div v-for="holiday in props.holidays.slice(0, 5)" :key="holiday.id" class="flex items-center justify-between rounded-lg bg-muted/40 px-2 py-1.5">
                            <span>{{ formatDate(holiday.holiday_date) }} · {{ holiday.name }}</span><button type="button" class="text-rose-600" @click="deleteHoliday(holiday.id)">Tắt</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border bg-card shadow-sm">
            <div class="flex flex-col gap-3 border-b border-border/70 p-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="font-bold">Danh sách yêu cầu</h2>
                    <p class="text-xs text-muted-foreground">Duyệt, từ chối hoặc theo dõi phản hồi của nhân viên.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <div class="relative">
                        <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="searchQuery" class="w-full pl-9 sm:w-64" placeholder="Tìm nhân viên, lý do..." />
                    </div>
                    <div class="flex items-center gap-1 rounded-lg border bg-background p-1">
                        <Filter class="mx-1.5 size-3.5 text-muted-foreground" />
                        <button v-for="filter in [{ value: 'all', label: 'Tất cả' }, { value: 'pending', label: 'Chờ duyệt' }, { value: 'approved', label: 'Đã duyệt' }, { value: 'rejected', label: 'Từ chối' }]" :key="filter.value" type="button" class="rounded-md px-2 py-1.5 text-xs font-semibold transition-colors" :class="statusFilter === filter.value ? 'bg-indigo-600 text-white' : 'text-muted-foreground hover:bg-muted'" @click="statusFilter = filter.value as 'all' | Status">
                            {{ filter.label }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="filteredRequests.length === 0" class="p-12 text-center">
                <CalendarDays class="mx-auto size-10 text-muted-foreground/40" />
                <p class="mt-3 font-semibold">Chưa có yêu cầu phù hợp</p>
                <p class="mt-1 text-sm text-muted-foreground">Các đơn tăng ca mới sẽ xuất hiện tại đây.</p>
            </div>

            <div v-else class="divide-y divide-border/70">
                <article v-for="item in filteredRequests" :key="item.id" class="flex flex-col gap-4 p-5 transition-colors hover:bg-muted/20 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl" :class="item.status === 'approved' ? 'bg-emerald-500/10 text-emerald-600' : item.status === 'rejected' ? 'bg-rose-500/10 text-rose-600' : 'bg-amber-500/10 text-amber-600'">
                            <UserRound class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-bold">{{ item.employee_name ?? 'Không xác định' }}</p>
                                <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground">{{ item.employee_code }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="item.status === 'approved' ? 'bg-emerald-500/10 text-emerald-600' : item.status === 'rejected' ? 'bg-rose-500/10 text-rose-600' : 'bg-amber-500/10 text-amber-600'">{{ statusLabel(item.status) }}</span>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                <span>{{ formatDate(item.scheduled_date) }}</span>
                                <span>·</span>
                                <span>{{ item.scheduled_start_at && item.scheduled_end_at ? `${item.scheduled_start_at}–${item.scheduled_end_at}` : `${item.hours_requested} giờ` }}</span>
                                <span>·</span>
                                <span>{{ item.overtime_type_label }} · {{ item.overtime_multiplier ? `${item.overtime_multiplier}x` : 'Theo chính sách hiện tại' }}</span>
                                <span>·</span>
                                <span>{{ sourceLabel(item.request_source) }}</span>
                                <span v-if="item.requester_name">· Gửi bởi {{ item.requester_name }}</span>
                            </div>
                            <p v-if="item.reason" class="mt-2 text-sm text-muted-foreground">{{ item.reason }}</p>
                            <p v-if="responseLabel(item)" class="mt-2 text-xs font-semibold text-indigo-600">{{ responseLabel(item) }}</p>
                            <p v-if="item.rejection_reason" class="mt-2 text-xs font-medium text-rose-600">{{ item.rejection_reason }}</p>
                            <p v-if="item.check_in_at" class="mt-2 text-xs font-medium text-emerald-600">
                                <CheckCircle2 class="mr-1 inline size-3.5" />
                                Đã chấm {{ item.worked_hours.toFixed(2) }}h thực tế{{ item.payroll_status === 'ready' ? ', đủ điều kiện tính lương' : '' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col gap-3 lg:items-end">
                        <div class="text-left lg:text-right">
                            <p class="text-lg font-black text-indigo-600">{{ item.hours_requested }} giờ</p>
                            <p class="text-xs text-muted-foreground">{{ item.status === 'approved' ? `${item.hours_approved} giờ được duyệt` : 'Dự kiến tăng ca' }}</p>
                            <p v-if="item.estimated_amount" class="mt-1 text-sm font-bold text-emerald-600">{{ vnd(item.actual_amount || item.estimated_amount) }}</p>
                            <p v-if="item.estimated_amount" class="text-[11px] text-muted-foreground">{{ item.actual_amount ? 'Tiền theo giờ thực tế' : 'Tiền dự kiến nếu làm đủ' }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 lg:justify-end">
                            <template v-if="canCheckIn(item)">
                                <Button size="sm" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="attendance(item.id, 'check-in')">Check-in OT</Button>
                            </template>
                            <template v-if="canCheckOut(item)">
                                <Button size="sm" class="bg-emerald-600 text-white hover:bg-emerald-700" @click="attendance(item.id, 'check-out')">Check-out OT</Button>
                            </template>
                            <template v-if="props.canManage && item.status === 'pending' && (item.request_source === 'employee' || item.employee_response === 'accepted')">
                                <Button size="sm" class="bg-emerald-600 text-white hover:bg-emerald-700" @click="act(item.id, 'approve')">Duyệt</Button>
                                <Button size="sm" variant="outline" class="text-rose-600" @click="act(item.id, 'reject')">Từ chối</Button>
                            </template>
                            <template v-if="!props.canManage && item.status === 'pending' && item.request_source === 'management'">
                                <Button size="sm" class="bg-indigo-600 text-white hover:bg-indigo-700" @click="act(item.id, 'accept')">Chấp nhận</Button>
                                <Button size="sm" variant="outline" class="text-rose-600" @click="act(item.id, 'decline')">Từ chối</Button>
                            </template>
                            <Button v-if="!props.canManage && item.status === 'pending' && item.request_source === 'employee'" size="sm" variant="outline" class="text-amber-600" @click="withdraw(item.id)">Rút đơn</Button>
                            <Button v-if="props.canManage && item.status === 'approved' && !['included', 'paid'].includes(item.workflow_status)" size="sm" variant="outline" class="text-amber-600" @click="reconcile(item)">Đối soát</Button>
                            <Button v-if="props.canManage && ['pending', 'approved'].includes(item.status)" size="sm" variant="outline" class="text-rose-600" @click="cancelRequest(item.id)">Huỷ</Button>
                        </div>
                    </div>
                </article>
            </div>
        </section>
    </div>

    <Teleport to="body">
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm" @click.self="showForm = false">
            <div class="w-full max-w-xl rounded-2xl border bg-card p-6 shadow-2xl">
                <div class="mb-5 flex items-start justify-between border-b border-border/70 pb-4">
                    <div>
                        <div class="flex items-center gap-2 text-indigo-600">
                            <Clock3 class="size-5" />
                            <h2 class="font-bold">{{ props.canManage ? 'Gửi yêu cầu tăng ca đột xuất' : 'Gửi đơn xin tăng ca' }}</h2>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">{{ props.canManage ? 'Nhân viên sẽ nhận thông báo và Gmail để xác nhận.' : 'Đơn sẽ được chuyển tới quản lý để duyệt.' }}</p>
                    </div>
                    <button type="button" class="rounded-md p-1 text-muted-foreground hover:bg-muted" @click="showForm = false"><X class="size-4" /></button>
                </div>

                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                    <div v-if="!props.canManage && props.currentEmployee" class="flex items-center gap-3 rounded-xl border border-indigo-500/20 bg-indigo-500/5 p-3 sm:col-span-2">
                        <UserRound class="size-5 text-indigo-500" />
                        <div>
                            <p class="text-sm font-semibold">{{ props.currentEmployee.full_name }} ({{ props.currentEmployee.employee_code }})</p>
                            <p class="text-xs text-muted-foreground">Đơn giá OT quy đổi: {{ vnd(props.currentEmployee.overtime_hourly_rate) }}/giờ</p>
                        </div>
                    </div>
                    <div v-if="props.canManage" class="grid gap-1.5 sm:col-span-2">
                        <Label>Nhân viên</Label>
                        <select v-model="form.employee_id" required class="h-10 rounded-md border bg-background px-3 text-sm">
                            <option v-for="employee in props.employees" :key="employee.id" :value="employee.id">{{ employee.full_name }} ({{ employee.employee_code }})</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Ngày tăng ca</Label>
                        <Input v-model="form.scheduled_date" type="date" required />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Loại OT</Label>
                        <select v-model="form.overtime_type" required class="h-10 rounded-md border bg-background px-3 text-sm">
                            <option v-for="type in props.policy.types" :key="type.value" :value="type.value">{{ type.label }} · {{ type.multiplier }}x</option>
                        </select>
                        <p class="text-[11px] text-muted-foreground">{{ selectedPolicy?.description }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Bắt đầu OT</Label>
                        <Input v-model="form.start_time" type="time" required />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Kết thúc OT</Label>
                        <Input v-model="form.end_time" type="time" required />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Số giờ dự kiến</Label>
                        <Input v-model="form.hours_requested" type="number" min="0.25" :max="props.policy.max_daily_hours" step="0.25" required />
                        <p class="text-[11px] text-muted-foreground">Tối đa {{ props.policy.max_daily_hours }}h/ngày và {{ props.policy.max_monthly_hours }}h/tháng.</p>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3 sm:col-span-2">
                        <Banknote class="size-5 shrink-0 text-emerald-600" />
                        <div class="flex flex-1 items-center justify-between gap-3">
                            <div>
                                <p class="text-xs text-muted-foreground">Tiền OT dự kiến</p>
                                <p class="font-bold text-emerald-600">{{ vnd(estimatedAmount) }}</p>
                            </div>
                            <p class="text-right text-xs text-muted-foreground">{{ vnd(Number(selectedEmployee?.overtime_hourly_rate || 0)) }}/giờ × {{ selectedPolicy?.multiplier ?? 0 }}x × {{ Number(form.hours_requested || 0) }}h</p>
                        </div>
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Lý do</Label>
                        <textarea v-model="form.reason" rows="4" class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Thiếu nhân sự, đơn hàng tăng đột biến..." />
                    </div>
                    <div class="flex justify-end gap-2 border-t border-border/70 pt-4 sm:col-span-2">
                        <Button type="button" variant="outline" @click="showForm = false">Hủy</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-indigo-600 text-white hover:bg-indigo-700">Gửi yêu cầu</Button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
