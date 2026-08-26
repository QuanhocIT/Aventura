<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowLeft, CheckCircle2, ClipboardCheck, RefreshCw, ShieldAlert, UserPlus } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface Ingredient {
    id: number;
    name: string;
    sku?: string;
    average_cost?: number;
    unit?: { symbol?: string; name?: string };
}

interface ClosingItem {
    id: number;
    ingredient_id: number;
    opening_quantity: number;
    inbound_quantity: number;
    outbound_quantity: number;
    inbound_value: number;
    outbound_value: number;
    unit_cost: number;
    expected_quantity: number;
    expected_value: number;
    counted_quantity_1: number | null;
    counted_quantity_2: number | null;
    final_quantity: number | null;
    variance_quantity: number;
    variance_percent: number;
    variance_value: number;
    reconciliation_status?: string;
    reconciliation_notes?: string | null;
    notes?: string | null;
    ingredient?: Ingredient;
}

interface ClosingSession {
    id: number;
    branch_id: number;
    type: string;
    status: string;
    period_start: string;
    period_end: string;
    total_expected_quantity: number;
    total_counted_quantity: number;
    total_expected_value: number;
    total_counted_value: number;
    total_shortage_quantity: number;
    total_surplus_quantity: number;
    total_shortage_value: number;
    total_surplus_value: number;
    total_variance_value: number;
    counted_by: number;
    second_counted_by?: number | null;
    countedBy?: { id: number; name: string } | null;
    secondCountedBy?: { id: number; name: string } | null;
    approver?: { id: number; name: string } | null;
    items?: ClosingItem[];
    notes?: string | null;
    created_at?: string;
}

interface CounterCandidate {
    id: number;
    name: string;
    email?: string;
    job_title?: string;
}

interface ClosingTask {
    id: number;
    count_session_id: number;
    status: string;
    priority: string;
    due_at?: string | null;
    assigned_to?: number | null;
    assignee?: { id: number; name: string } | null;
    notes?: string | null;
}

const props = defineProps<{
    centralBranch: { id: number; name: string; code?: string };
    sessions: ClosingSession[];
    tasks: ClosingTask[];
    counterCandidates: CounterCandidate[];
    authUserId: number;
    canManage: boolean;
    canApprove: boolean;
    isWarehouseStaff: boolean;
    scopeMessage: string;
}>();

const today = new Date().toISOString().slice(0, 10);
const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1)
    .toISOString()
    .slice(0, 10);

const periodForm = ref({ from_date: firstOfMonth, to_date: today });
const selectedSession = ref<ClosingSession | null>(null);
const showCreate = ref(false);
const showAssign = ref(false);
const isSubmitting = ref(false);
const search = ref('');
const assignForm = ref({ assigned_to: '', priority: 'normal', due_at: '', notes: '' });
const countRows = ref<Array<{ id: number; counted_quantity: string; notes: string }>>([]);

const filteredSessions = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.sessions.filter((session) => {
        if (!query) {
return true;
}

        return (
            `#${session.id}`.includes(query) ||
            session.period_start?.includes(query) ||
            session.period_end?.includes(query) ||
            session.status?.toLowerCase().includes(query)
        );
    });
});

const activeTask = computed(() =>
    selectedSession.value
        ? props.tasks.find((task) => task.count_session_id === selectedSession.value?.id)
        : undefined,
);

function taskFor(sessionId: number) {
    return props.tasks.find((task) => task.count_session_id === sessionId);
}

const canEditSelectedCounts = computed(() => {
    const session = selectedSession.value;

    if (!session || session.status !== 'in_progress') {
return false;
}

    return props.canManage || Number(session.second_counted_by) === Number(props.authUserId);
});

function formatNumber(value: number | string | null | undefined, digits = 3) {
    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: digits }).format(Number(value || 0));
}

function formatCurrency(value: number | string | null | undefined) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(value || 0));
}


function statusLabel(status: string) {
    return {
        in_progress: 'Đang đối chiếu',
        pending_approval: 'Chờ phê duyệt',
        approved: 'Đã phê duyệt',
        rejected: 'Bị từ chối',
        cancelled: 'Đã hủy',
    }[status] || status;
}

function statusClass(status: string) {
    if (status === 'approved') {
return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400';
}

    if (status === 'pending_approval') {
return 'border-sky-500/30 bg-sky-500/10 text-sky-400';
}

    if (status === 'in_progress') {
return 'border-amber-500/30 bg-amber-500/10 text-amber-400';
}

    if (status === 'rejected') {
return 'border-rose-500/30 bg-rose-500/10 text-rose-400';
}

    return 'border-slate-500/30 bg-slate-500/10 text-slate-400';
}

function varianceLabel(item: ClosingItem) {
    if (item.reconciliation_status === 'pending') {
return 'Cần đếm lại';
}

    if (item.final_quantity === null) {
return 'Chưa đếm';
}

    if (Number(item.variance_quantity) < -0.0005) {
return 'Thiếu';
}

    if (Number(item.variance_quantity) > 0.0005) {
return 'Thừa';
}

    return 'Khớp';
}

function varianceClass(item: ClosingItem) {
    if (item.reconciliation_status === 'pending' || Number(item.variance_quantity) < -0.0005) {
        return 'text-rose-400';
    }

    if (Number(item.variance_quantity) > 0.0005) {
return 'text-amber-400';
}

    return 'text-emerald-400';
}

function sessionShortage(session: ClosingSession) {
    return Number(session.total_shortage_value || 0);
}

function openSession(session: ClosingSession) {
    selectedSession.value = session;
    countRows.value = (session.items || []).map((item) => ({
        id: item.id,
        counted_quantity: item.final_quantity !== null
            ? String(item.final_quantity)
            : (props.isWarehouseStaff && item.counted_quantity_2 !== null
                ? String(item.counted_quantity_2)
                : (item.counted_quantity_1 !== null ? String(item.counted_quantity_1) : '')),
        notes: item.notes || '',
    }));
}

function openFromQuery() {
    const id = Number(new URLSearchParams(window.location.search).get('session'));

    if (id) {
        const session = props.sessions.find((item) => item.id === id);

        if (session) {
openSession(session);
}
    }
}

async function createClosing() {
    if (!periodForm.value.from_date || !periodForm.value.to_date) {
        toast.error('Vui lòng chọn đủ ngày bắt đầu và ngày kết thúc.');

        return;
    }

    isSubmitting.value = true;

    try {
        const response = await axios.post('/api/inventory/central-warehouse/material-closing', {
            branch_id: props.centralBranch.id,
            ...periodForm.value,
        });
        toast.success(response.data.message || 'Đã tạo kỳ chốt.');
        showCreate.value = false;
        await router.reload();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Không thể tạo kỳ chốt nguyên liệu.');
    } finally {
        isSubmitting.value = false;
    }
}

function openAssign(session: ClosingSession) {
    selectedSession.value = session;
    assignForm.value = {
        assigned_to: session.second_counted_by ? String(session.second_counted_by) : '',
        priority: 'normal',
        due_at: '',
        notes: 'Đối chiếu thực tế và ghi nhận số lượng từng nguyên liệu trong kỳ chốt.',
    };
    showAssign.value = true;
}

async function assignCounter() {
    if (!selectedSession.value || !assignForm.value.assigned_to) {
        toast.error('Vui lòng chọn nhân viên đối chiếu.');

        return;
    }

    isSubmitting.value = true;

    try {
        const response = await axios.post(
            `/api/inventory/central-warehouse/material-closing/${selectedSession.value.id}/assign`,
            assignForm.value,
        );
        toast.success(response.data.message || 'Đã giao việc đối chiếu.');
        showAssign.value = false;
        await router.reload();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Không thể giao việc đối chiếu.');
    } finally {
        isSubmitting.value = false;
    }
}

async function submitCounts() {
    if (!selectedSession.value) {
return;
}

    const invalid = countRows.value.some((row) => row.counted_quantity === '' || Number(row.counted_quantity) < 0);

    if (invalid) {
        toast.error('Vui lòng nhập số lượng thực tế cho tất cả nguyên liệu.');

        return;
    }

    isSubmitting.value = true;

    try {
        const response = await axios.post(
            `/api/inventory/central-warehouse/material-closing/${selectedSession.value.id}/counts`,
            {
                items: countRows.value.map((row) => ({
                    id: row.id,
                    counted_quantity: Number(row.counted_quantity),
                    notes: row.notes || null,
                })),
            },
        );
        toast.success(response.data.message || 'Đã lưu kết quả đối chiếu.');
        await router.reload();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Không thể lưu kết quả đối chiếu.');
    } finally {
        isSubmitting.value = false;
    }
}

async function submitForApproval() {
    if (!selectedSession.value) {
return;
}

    isSubmitting.value = true;

    try {
        const response = await axios.post(`/api/inventory/count-sessions/${selectedSession.value.id}/submit-approval`, {
            notes: 'Kết quả chốt nguyên liệu đã được đối chiếu trên hệ thống.',
        });
        toast.success(response.data.message || 'Đã gửi kỳ chốt chờ phê duyệt.');
        await router.reload();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Chưa thể gửi phê duyệt.');
    } finally {
        isSubmitting.value = false;
    }
}

async function approveSession() {
    if (!selectedSession.value || !window.confirm('Phê duyệt sẽ ghi điều chỉnh thiếu/thừa vào tồn kho. Tiếp tục?')) {
return;
}

    isSubmitting.value = true;

    try {
        const response = await axios.post(`/api/inventory/count-sessions/${selectedSession.value.id}/approve`);
        toast.success(response.data.message || 'Đã phê duyệt và cập nhật tồn kho.');
        await router.reload();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Không thể phê duyệt kỳ chốt.');
    } finally {
        isSubmitting.value = false;
    }
}

async function cancelSession() {
    if (!selectedSession.value) {
return;
}

    const reason = window.prompt('Nhập lý do hủy kỳ chốt:', 'Tạo nhầm kỳ hoặc cần mở lại kỳ khác');

    if (reason === null || !reason.trim()) {
return;
}

    isSubmitting.value = true;

    try {
        await axios.post(`/api/inventory/count-sessions/${selectedSession.value.id}/cancel`, { reason });
        toast.success('Đã hủy kỳ chốt.');
        await router.reload();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Không thể hủy kỳ chốt.');
    } finally {
        isSubmitting.value = false;
    }
}

async function reconcileItem(item: ClosingItem) {
    if (!selectedSession.value || item.reconciliation_status !== 'pending') {
return;
}

    const finalQuantity = window.prompt(
        `Nhập số lượng cuối cùng cho ${item.ingredient?.name || 'nguyên liệu'}:`,
        String(item.counted_quantity_2 ?? item.counted_quantity_1 ?? ''),
    );

    if (finalQuantity === null) {
return;
}

    const notes = window.prompt('Ghi chú bắt buộc cho việc đồng đếm:', 'Đã kiểm tra lại thực tế tại Kho Tổng');

    if (notes === null || !notes.trim()) {
return;
}

    try {
        await axios.post(
            `/api/inventory/count-sessions/${selectedSession.value.id}/items/${item.id}/reconcile`,
            { final_quantity: Number(finalQuantity), notes },
        );
        toast.success('Đã chốt dòng cần đồng đếm.');
        await router.reload();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Không thể chốt dòng đối chiếu.');
    }
}

onMounted(openFromQuery);
</script>

<template>
    <Head title="Chốt nguyên liệu Kho Tổng" />

    <div class="min-h-screen bg-slate-950 px-4 py-6 text-slate-100 sm:px-6 lg:px-8">
        <div class="mx-auto flex max-w-[1500px] flex-col gap-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <Link href="/inventory/central-warehouse" class="mb-3 inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white">
                        <ArrowLeft class="size-4" /> Tổng quan Kho Tổng
                    </Link>
                    <div class="flex items-center gap-3">
                        <div class="rounded-2xl bg-amber-500/15 p-3 text-amber-300"><ClipboardCheck class="size-7" /></div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-300">Kho Tổng · Đối chiếu định kỳ</p>
                            <h1 class="mt-1 text-3xl font-black tracking-tight">Chốt nguyên liệu</h1>
                        </div>
                    </div>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-400">Chọn kỳ từ ngày đến ngày. Hệ thống khóa snapshot: tồn đầu kỳ + nhập − xuất = tồn phải còn, sau đó nhân viên đối chiếu số thực tế để nhận diện thiếu/thừa.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" class="gap-2 border-slate-700 bg-slate-900 text-slate-200 hover:bg-slate-800" @click="router.reload()">
                        <RefreshCw class="size-4" /> Làm mới
                    </Button>
                    <Button v-if="canManage" class="gap-2 bg-amber-500 font-bold text-slate-950 hover:bg-amber-400" @click="showCreate = true">
                        <ClipboardCheck class="size-4" /> Mở kỳ chốt mới
                    </Button>
                </div>
            </div>

            <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 px-4 py-3 text-sm text-amber-100/80">
                <span class="font-bold text-amber-300">Phạm vi an toàn:</span> {{ scopeMessage }}
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card class="border-slate-800 bg-slate-900/80"><CardContent class="p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Tổng kỳ chốt</p><p class="mt-2 text-3xl font-black">{{ props.sessions.length }}</p><p class="mt-1 text-xs text-slate-500">đã lưu snapshot</p></CardContent></Card>
                <Card class="border-amber-500/20 bg-amber-500/5"><CardContent class="p-5"><p class="text-xs font-bold uppercase tracking-wider text-amber-300">Đang đối chiếu</p><p class="mt-2 text-3xl font-black text-amber-200">{{ props.sessions.filter(s => s.status === 'in_progress').length }}</p><p class="mt-1 text-xs text-slate-500">có thể giao nhân viên</p></CardContent></Card>
                <Card class="border-sky-500/20 bg-sky-500/5"><CardContent class="p-5"><p class="text-xs font-bold uppercase tracking-wider text-sky-300">Chờ phê duyệt</p><p class="mt-2 text-3xl font-black text-sky-200">{{ props.sessions.filter(s => s.status === 'pending_approval').length }}</p><p class="mt-1 text-xs text-slate-500">chưa ghi điều chỉnh</p></CardContent></Card>
                <Card class="border-rose-500/20 bg-rose-500/5"><CardContent class="p-5"><p class="text-xs font-bold uppercase tracking-wider text-rose-300">Thiếu đã xác định</p><p class="mt-2 text-xl font-black text-rose-200">{{ formatCurrency(props.sessions.reduce((sum, s) => sum + sessionShortage(s), 0)) }}</p><p class="mt-1 text-xs text-slate-500">theo các kỳ đã đối chiếu</p></CardContent></Card>
            </div>

            <Card class="border-slate-800 bg-slate-900/80">
                <CardHeader class="flex flex-col gap-3 border-b border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                    <div><CardTitle class="text-lg">Các kỳ chốt nguyên liệu</CardTitle><CardDescription class="text-slate-400">Mỗi kỳ lưu lại số liệu để truy vết và đối chiếu, không phụ thuộc nhà cung cấp.</CardDescription></div>
                    <Input v-model="search" placeholder="Tìm mã kỳ / ngày / trạng thái" class="h-9 w-full border-slate-700 bg-slate-950 sm:w-64" />
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="filteredSessions.length === 0" class="p-12 text-center text-sm text-slate-500">Chưa có kỳ chốt nào trong phạm vi Kho Tổng.</div>
                    <div v-else class="divide-y divide-slate-800">
                        <button v-for="session in filteredSessions" :key="session.id" class="flex w-full flex-col gap-4 p-5 text-left transition hover:bg-slate-800/50 lg:flex-row lg:items-center lg:justify-between" @click="openSession(session)">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2"><span class="font-black">Kỳ chốt #{{ session.id }}</span><Badge variant="outline" :class="statusClass(session.status)">{{ statusLabel(session.status) }}</Badge></div>
                                <p class="mt-1 text-sm text-slate-400">{{ session.period_start }} → {{ session.period_end }} · {{ session.items?.length || 0 }} nguyên liệu</p>
                                <p class="mt-1 text-xs text-slate-500">Đếm 1: {{ session.countedBy?.name || 'Trưởng kho Tổng' }} <span v-if="session.secondCountedBy"> · Đối chiếu: {{ session.secondCountedBy.name }}</span></p>
                            </div>
                            <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-right text-xs sm:grid-cols-4">
                                <div><p class="text-slate-500">Phải còn</p><p class="font-bold text-slate-200">{{ formatNumber(session.total_expected_quantity) }}</p></div>
                                <div><p class="text-slate-500">Giá trị</p><p class="font-bold text-slate-200">{{ formatCurrency(session.total_expected_value) }}</p></div>
                                <div><p class="text-slate-500">Thiếu</p><p class="font-bold text-rose-400">{{ formatCurrency(session.total_shortage_value) }}</p></div>
                                <div><p class="text-slate-500">Task</p><p class="font-bold" :class="taskFor(session.id)?.status === 'completed' ? 'text-emerald-400' : 'text-amber-300'">{{ taskFor(session.id)?.status || 'Chưa giao' }}</p></div>
                            </div>
                        </button>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="selectedSession" class="border-amber-500/30 bg-slate-900/95 shadow-2xl shadow-amber-950/20">
                <CardHeader class="border-b border-slate-800">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div><div class="flex flex-wrap items-center gap-2"><CardTitle>Kỳ chốt #{{ selectedSession.id }}</CardTitle><Badge variant="outline" :class="statusClass(selectedSession.status)">{{ statusLabel(selectedSession.status) }}</Badge></div><CardDescription class="mt-1 text-slate-400">{{ selectedSession.period_start }} → {{ selectedSession.period_end }} · {{ centralBranch.name }}</CardDescription></div>
                        <div class="flex flex-wrap gap-2">
                            <Button v-if="canManage && selectedSession.status === 'in_progress'" variant="outline" class="gap-2 border-slate-700" @click="openAssign(selectedSession)"><UserPlus class="size-4" /> Giao đối chiếu</Button>
                            <Button v-if="canManage && selectedSession.status === 'in_progress'" variant="outline" class="border-rose-500/30 text-rose-300" @click="cancelSession">Hủy kỳ</Button>
                            <Button variant="ghost" class="text-slate-400" @click="selectedSession = null">Đóng</Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-5 p-5">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                        <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-3"><p class="text-[11px] uppercase text-slate-500">Tồn đầu kỳ</p><p class="mt-1 font-black">{{ formatNumber((selectedSession.items || []).reduce((sum, i) => sum + Number(i.opening_quantity || 0), 0)) }}</p></div>
                        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3"><p class="text-[11px] uppercase text-emerald-400/70">Nhập trong kỳ</p><p class="mt-1 font-black text-emerald-300">{{ formatNumber((selectedSession.items || []).reduce((sum, i) => sum + Number(i.inbound_quantity || 0), 0)) }}</p></div>
                        <div class="rounded-xl border border-orange-500/20 bg-orange-500/5 p-3"><p class="text-[11px] uppercase text-orange-400/70">Xuất trong kỳ</p><p class="mt-1 font-black text-orange-300">{{ formatNumber((selectedSession.items || []).reduce((sum, i) => sum + Number(i.outbound_quantity || 0), 0)) }}</p></div>
                        <div class="rounded-xl border border-sky-500/20 bg-sky-500/5 p-3"><p class="text-[11px] uppercase text-sky-400/70">Phải còn</p><p class="mt-1 font-black text-sky-300">{{ formatNumber(selectedSession.total_expected_quantity) }}</p></div>
                        <div class="rounded-xl border border-rose-500/20 bg-rose-500/5 p-3"><p class="text-[11px] uppercase text-rose-400/70">Thiếu</p><p class="mt-1 font-black text-rose-300">{{ formatCurrency(selectedSession.total_shortage_value) }}</p></div>
                        <div class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-3"><p class="text-[11px] uppercase text-amber-400/70">Thừa</p><p class="mt-1 font-black text-amber-300">{{ formatCurrency(selectedSession.total_surplus_value) }}</p></div>
                    </div>

                    <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4 text-sm text-slate-400">
                        <div class="flex flex-wrap items-center justify-between gap-2"><span>Người mở kỳ: <strong class="text-slate-200">{{ selectedSession.countedBy?.name || '—' }}</strong></span><span v-if="selectedSession.secondCountedBy">Nhân viên đối chiếu: <strong class="text-amber-300">{{ selectedSession.secondCountedBy.name }}</strong></span><span v-if="activeTask">Task: <strong class="text-slate-200">{{ activeTask.status }}</strong></span></div>
                        <p class="mt-2 text-xs text-slate-500">Số “Phải còn” là số hệ thống tính từ sổ giao dịch tại thời điểm mở kỳ. Số thực tế chỉ được ghi vào tồn kho sau bước phê duyệt.</p>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-slate-800">
                        <table class="w-full min-w-[1080px] text-left text-xs">
                            <thead class="bg-slate-950 text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-3 py-3">Nguyên liệu</th><th class="px-3 py-3 text-right">Tồn đầu</th><th class="px-3 py-3 text-right">Nhập</th><th class="px-3 py-3 text-right">Xuất</th><th class="px-3 py-3 text-right">Phải còn</th><th class="px-3 py-3 text-right">Giá vốn</th><th class="px-3 py-3 text-right">Thực tế</th><th class="px-3 py-3 text-right">Lệch</th><th class="px-3 py-3">Kết luận</th><th class="px-3 py-3"></th></tr></thead>
                            <tbody class="divide-y divide-slate-800">
                                <tr v-for="(item, index) in selectedSession.items" :key="item.id" class="align-top">
                                    <td class="px-3 py-3"><p class="font-bold text-slate-200">{{ item.ingredient?.name || `Nguyên liệu #${item.ingredient_id}` }}</p><p class="mt-1 text-slate-500">{{ item.ingredient?.sku || '—' }} · {{ item.ingredient?.unit?.symbol || '' }}</p></td>
                                    <td class="px-3 py-3 text-right text-slate-300">{{ formatNumber(item.opening_quantity) }}</td><td class="px-3 py-3 text-right text-emerald-300">{{ formatNumber(item.inbound_quantity) }}</td><td class="px-3 py-3 text-right text-orange-300">{{ formatNumber(item.outbound_quantity) }}</td><td class="px-3 py-3 text-right font-bold text-sky-300">{{ formatNumber(item.expected_quantity) }}</td><td class="px-3 py-3 text-right text-slate-300">{{ formatCurrency(item.unit_cost) }}</td>
                                    <td class="px-3 py-3 text-right">
                                        <Input v-if="canEditSelectedCounts" v-model="countRows[index].counted_quantity" type="number" min="0" step="0.001" class="h-8 w-28 border-slate-700 bg-slate-950 text-right text-xs" />
                                        <span v-else class="font-bold text-slate-200">{{ item.final_quantity === null ? '—' : formatNumber(item.final_quantity) }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right font-bold" :class="varianceClass(item)">{{ item.final_quantity === null ? '—' : formatNumber(item.variance_quantity) }}</td>
                                    <td class="px-3 py-3"><span class="font-bold" :class="varianceClass(item)">{{ varianceLabel(item) }}</span><p v-if="item.final_quantity !== null" class="mt-1 text-slate-500">{{ formatCurrency(item.variance_value) }}</p></td>
                                    <td class="px-3 py-3"><Button v-if="item.reconciliation_status === 'pending' && canManage" size="sm" variant="outline" class="border-rose-500/30 text-[11px] text-rose-300" @click.stop="reconcileItem(item)">Đồng đếm</Button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-800 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2 text-xs text-slate-500"><ShieldAlert class="size-4 text-amber-400" /> Chênh lệch âm là thiếu thực tế so với số hệ thống phải còn.</div>
                        <div class="flex flex-wrap gap-2">
                            <Button v-if="canEditSelectedCounts" :disabled="isSubmitting" class="gap-2 bg-amber-500 font-bold text-slate-950 hover:bg-amber-400" @click="submitCounts"><CheckCircle2 class="size-4" /> Lưu kết quả đối chiếu</Button>
                            <Button v-if="canManage && selectedSession.status === 'in_progress'" :disabled="isSubmitting" variant="outline" class="border-sky-500/30 text-sky-300" @click="submitForApproval">Gửi phê duyệt</Button>
                            <Button v-if="canApprove && selectedSession.status === 'pending_approval'" :disabled="isSubmitting" class="bg-emerald-600 font-bold text-white hover:bg-emerald-500" @click="approveSession">Phê duyệt & cập nhật tồn</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="showCreate = false">
            <Card class="w-full max-w-lg border-slate-700 bg-slate-900 shadow-2xl"><CardHeader><CardTitle>Mở kỳ chốt nguyên liệu</CardTitle><CardDescription class="text-slate-400">Không dùng nhà cung cấp. Hệ thống đọc sổ giao dịch Kho Tổng theo khoảng ngày bạn chọn.</CardDescription></CardHeader><CardContent class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2"><div class="space-y-2"><Label>Chốt từ ngày</Label><Input v-model="periodForm.from_date" type="date" class="border-slate-700 bg-slate-950" /></div><div class="space-y-2"><Label>Đến ngày</Label><Input v-model="periodForm.to_date" type="date" class="border-slate-700 bg-slate-950" /></div></div>
                <div class="rounded-xl border border-sky-500/20 bg-sky-500/5 p-3 text-xs leading-5 text-sky-200">Sau khi mở kỳ, hệ thống sẽ hiển thị từng nguyên liệu: tồn đầu kỳ, tổng nhập, tổng xuất, tồn phải còn và giá trị quy đổi. Trưởng kho có thể giao nhân viên đối chiếu thực tế.</div>
                <div class="flex justify-end gap-2"><Button variant="outline" class="border-slate-700" @click="showCreate = false">Hủy</Button><Button :disabled="isSubmitting" class="bg-amber-500 font-bold text-slate-950" @click="createClosing">Tạo kỳ chốt</Button></div>
            </CardContent></Card>
        </div>

        <div v-if="showAssign && selectedSession" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="showAssign = false">
            <Card class="w-full max-w-lg border-slate-700 bg-slate-900 shadow-2xl"><CardHeader><CardTitle>Giao việc đối chiếu #{{ selectedSession.id }}</CardTitle><CardDescription class="text-slate-400">Nhân viên sẽ nhập số thực tế cho toàn bộ nguyên liệu và kết quả được ghi vào lịch sử kỳ chốt.</CardDescription></CardHeader><CardContent class="space-y-4">
                <div class="space-y-2"><Label>Nhân viên Kho Tổng</Label><select v-model="assignForm.assigned_to" class="h-10 w-full rounded-md border border-slate-700 bg-slate-950 px-3 text-sm text-slate-200"><option value="">Chọn nhân viên</option><option v-for="candidate in counterCandidates" :key="candidate.id" :value="String(candidate.id)">{{ candidate.name }}{{ candidate.job_title ? ` · ${candidate.job_title}` : '' }}</option></select></div>
                <div class="grid gap-4 sm:grid-cols-2"><div class="space-y-2"><Label>Ưu tiên</Label><select v-model="assignForm.priority" class="h-10 w-full rounded-md border border-slate-700 bg-slate-950 px-3 text-sm text-slate-200"><option value="normal">Bình thường</option><option value="high">Cao</option><option value="urgent">Khẩn</option></select></div><div class="space-y-2"><Label>Hạn hoàn thành</Label><Input v-model="assignForm.due_at" type="datetime-local" class="border-slate-700 bg-slate-950" /></div></div>
                <div class="space-y-2"><Label>Hướng dẫn</Label><textarea v-model="assignForm.notes" rows="3" class="w-full rounded-md border border-slate-700 bg-slate-950 p-3 text-sm text-slate-200" /></div>
                <div class="flex justify-end gap-2"><Button variant="outline" class="border-slate-700" @click="showAssign = false">Hủy</Button><Button :disabled="isSubmitting" class="gap-2 bg-amber-500 font-bold text-slate-950" @click="assignCounter"><UserPlus class="size-4" /> Giao việc</Button></div>
            </CardContent></Card>
        </div>
    </div>
</template>
