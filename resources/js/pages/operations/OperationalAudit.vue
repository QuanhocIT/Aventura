<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertOctagon,
    Ban,
    BarChart3,
    CalendarClock,
    CalendarPlus,
    CheckCheck,
    CheckCircle2,
    ClipboardCheck,
    ClipboardList,
    Clock,
    DollarSign,
    Download,
    FileCheck2,
    Filter,
    Gavel,
    Plus,
    Play,
    RotateCcw,
    Search,
    ShieldAlert,
    Upload,
    UserCog,
    UserCheck,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    reports: Array<any>;
    policies: Array<any>;
    branches: Array<any>;
    employees: Array<any>;
    reportStats: Record<string, any>;
    auditTrail: Record<string, any[]>;
    currentUserId: number;
    isOwner: boolean;
    isInspector: boolean;
    canManageRemediation: boolean;
    canReinspect: boolean;
    canAcknowledge: boolean;
    inspectionPlans: Array<any>;
    planStats: Record<string, any>;
    branchInsights: Array<any>;
    trend: Array<any>;
    inspectors: Array<any>;
    canManagePlans: boolean;
    branchContext?: { scope: string; active_branch_id: number | null };
    isOverview?: boolean;
}>();

const isOverview = computed(() => Boolean(props.isOverview));
const isBranchScoped = computed(() => props.branchContext?.scope === 'branch');

const isCreateModalOpen = ref(false);
const isDetailModalOpen = ref(false);
const isProcessing = ref(false);
const selectedReport = ref<any>(null);
const ownerNotes = ref('');
const searchQuery = ref('');
const statusFilter = ref('all');
const severityFilter = ref('all');
const branchFilter = ref('all');
const isPlanModalOpen = ref(false);
const planForm = ref({
    branch_id: (props.branchContext?.active_branch_id ?? null) as number | null,
    title: '',
    inspection_type: 'routine',
    scheduled_date: new Date().toISOString().split('T')[0],
    due_date: '',
    lead_inspector_id: props.inspectors?.[0]?.id || null,
    scope: '',
});
const remediationForm = ref({
    assigned_to: null as number | null,
    remediation_deadline: '',
    remediation_plan: '',
    remediation_notes: '',
    remediation_proof: null as File | null,
});
const remediationFileName = ref('');
const reinspectionResult = ref<'pass' | 'fail'>('pass');
const reinspectionNotes = ref('');
const reinspectionProof = ref<File | null>(null);
const reinspectionFileName = ref('');
const initialQuery = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
const initialInspectionId = Number(initialQuery?.get('inspection_id') || 0) || null;
const initialBranchId = Number(initialQuery?.get('branch_id') || 0) || null;
const initialPlanId = Number(initialQuery?.get('inspection_plan_id') || 0) || null;

const reportForm = ref({
    branch_id: initialBranchId || props.branchContext?.active_branch_id || props.branches[0]?.id || null,
    policy_id: null as number | null,
    offender_user_id: null as number | null,
    infringement_date: new Date().toISOString().split('T')[0],
    description: '',
    severity_level: 'moderate',
    proof_photo_url: '',
    proof_photo: null as File | null,
    penalty_amount: 0,
    remediation_deadline: '',
    remediation_plan: '',
    inspection_plan_id: initialPlanId as number | null,
    operational_inspection_id: initialInspectionId,
    finding_category: '',
    requirement_reference: '',
    observed_condition: '',
    root_cause: '',
    corrective_action: '',
    preventive_action: '',
});
const proofFileName = ref('');

const onPolicySelect = () => {
    if (!reportForm.value.policy_id) {
        return;
    }

    const found = props.policies.find(
        (p) => p.id === reportForm.value.policy_id,
    );

    if (found) {
        reportForm.value.penalty_amount = found.suggested_fine_amount || 0;
    }
};

const openCreateModal = () => {
    reportForm.value = {
        branch_id: initialBranchId || props.branchContext?.active_branch_id || props.branches[0]?.id || null,
        policy_id: null,
        offender_user_id: null,
        infringement_date: new Date().toISOString().split('T')[0],
        description: '',
        severity_level: 'moderate',
        proof_photo_url: '',
        proof_photo: null,
        penalty_amount: 0,
        remediation_deadline: '',
        remediation_plan: '',
        inspection_plan_id: initialPlanId,
        operational_inspection_id: initialInspectionId,
        finding_category: '',
        requirement_reference: '',
        observed_condition: '',
        root_cause: '',
        corrective_action: '',
        preventive_action: '',
    };
    proofFileName.value = '';
    isCreateModalOpen.value = true;
};

const openDetailModal = (r: any) => {
    selectedReport.value = r;
    ownerNotes.value = r.owner_notes || '';
    remediationForm.value = {
        assigned_to: r.assignee?.id ?? null,
        remediation_deadline: r.remediation_deadline || '',
        remediation_plan: r.remediation_plan || '',
        remediation_notes: r.remediation_notes || '',
        remediation_proof: null,
    };
    remediationFileName.value = '';
    reinspectionResult.value = 'pass';
    reinspectionNotes.value = '';
    reinspectionProof.value = null;
    reinspectionFileName.value = '';
    isDetailModalOpen.value = true;
};

const submitReport = async () => {
    if (!reportForm.value.branch_id || !reportForm.value.description.trim()) {
        toast.error(
            'Vui lòng chọn Chi nhánh và Nhập mô tả chi tiết hành vi vi phạm.',
        );

        return;
    }

    isProcessing.value = true;

    try {
        const payload = new FormData();
        payload.append('branch_id', String(reportForm.value.branch_id));
        payload.append('infringement_date', reportForm.value.infringement_date);
        payload.append('description', reportForm.value.description);
        payload.append('severity_level', reportForm.value.severity_level);
        payload.append(
            'penalty_amount',
            String(reportForm.value.penalty_amount || 0),
        );

        if (reportForm.value.inspection_plan_id) {
            payload.append('inspection_plan_id', String(reportForm.value.inspection_plan_id));
        }

        if (reportForm.value.operational_inspection_id) {
            payload.append('operational_inspection_id', String(reportForm.value.operational_inspection_id));
        }

        if (reportForm.value.policy_id) {
            payload.append('policy_id', String(reportForm.value.policy_id));
        }

        if (reportForm.value.offender_user_id) {
            payload.append(
                'offender_user_id',
                String(reportForm.value.offender_user_id),
            );
        }

        if (reportForm.value.proof_photo_url) {
            payload.append('proof_photo_url', reportForm.value.proof_photo_url);
        }

        if (reportForm.value.proof_photo) {
            payload.append('proof_photo', reportForm.value.proof_photo);
        }

        if (reportForm.value.remediation_deadline) {
            payload.append(
                'remediation_deadline',
                reportForm.value.remediation_deadline,
            );
        }

        if (reportForm.value.remediation_plan.trim()) {
            payload.append('remediation_plan', reportForm.value.remediation_plan);
        }

        for (const field of ['finding_category', 'requirement_reference', 'observed_condition', 'root_cause', 'corrective_action', 'preventive_action'] as const) {
            if (reportForm.value[field].trim()) {
payload.append(field, reportForm.value[field]);
}
        }

        const res = await axios.post(
            '/api/operational-audit/reports',
            payload,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            },
        );

        if (res.data.success) {
            toast.success(
                'Đã lập Biên bản Vi phạm và gửi trình Chủ doanh nghiệp phê duyệt!',
            );
            isCreateModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Có lỗi xảy ra khi tạo biên bản.',
        );
    } finally {
        isProcessing.value = false;
    }
};

onMounted(() => {
    if (initialInspectionId) {
isCreateModalOpen.value = true;
}
});

const onProofPhotoChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    reportForm.value.proof_photo = file;
    proofFileName.value = file?.name ?? '';
};

const openPlanModal = () => {
    planForm.value = {
        branch_id: props.branchContext?.active_branch_id ?? null,
        title: '',
        inspection_type: 'routine',
        scheduled_date: new Date().toISOString().split('T')[0],
        due_date: '',
        lead_inspector_id: props.inspectors?.[0]?.id || null,
        scope: '',
    };
    isPlanModalOpen.value = true;
};

const submitPlan = async () => {
    if (!planForm.value.title.trim() || planForm.value.scope.trim().length < 10) {
        toast.error('Vui lòng nhập tên và phạm vi kiểm tra đủ chi tiết.');

        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post('/api/operational-audit/inspection-plans', planForm.value);

        if (res.data.success) {
            toast.success(res.data.message);
            isPlanModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể tạo kế hoạch kiểm tra.');
    } finally {
        isProcessing.value = false;
    }
};

const updatePlanStatus = async (plan: any, action: 'start' | 'complete' | 'cancel') => {
    let body: Record<string, string> = {};

    if (action === 'complete') {
        const notes = window.prompt('Tóm tắt kết quả và các tồn tại cần theo dõi:', plan.notes || '');

        if (!notes) {
            return;
        }

        body = { notes };
    }

    if (action === 'cancel') {
        const reason = window.prompt('Lý do hủy kế hoạch:', 'Điều chỉnh lịch kiểm tra');

        if (!reason) {
            return;
        }

        body = { reason };
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(`/api/operational-audit/inspection-plans/${plan.id}/${action}`, body);

        if (res.data.success) {
            toast.success(res.data.message);
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể cập nhật kế hoạch.');
    } finally {
        isProcessing.value = false;
    }
};

const exportReports = () => {
    const params = new URLSearchParams();

    if (statusFilter.value !== 'all') {
params.set('status', statusFilter.value);
}

    if (severityFilter.value !== 'all') {
params.set('severity', severityFilter.value);
}

    if (branchFilter.value !== 'all') {
params.set('branch_id', branchFilter.value);
}

    window.location.href = `/api/operational-audit/export?${params.toString()}`;
};

const approveReport = async () => {
    if (!selectedReport.value) {
        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(
            `/api/operational-audit/reports/${selectedReport.value.id}/approve`,
            {
                owner_notes: ownerNotes.value,
            },
        );

        if (res.data.success) {
            toast.success('Đã phê duyệt Biên bản phạt!');
            isDetailModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Có lỗi xảy ra khi duyệt biên bản.',
        );
    } finally {
        isProcessing.value = false;
    }
};

const rejectReport = async () => {
    if (!selectedReport.value) {
        return;
    }

    if (!ownerNotes.value.trim()) {
        toast.error('Vui lòng nhập lý do từ chối để giữ dấu vết kiểm soát.');

        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(
            `/api/operational-audit/reports/${selectedReport.value.id}/reject`,
            {
                owner_notes: ownerNotes.value,
            },
        );

        if (res.data.success) {
            toast.success('Đã từ chối biên bản phạt.');
            isDetailModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Có lỗi xảy ra khi từ chối.');
    } finally {
        isProcessing.value = false;
    }
};

const submitAssignment = async () => {
    if (!selectedReport.value) {
        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(
            `/api/operational-audit/reports/${selectedReport.value.id}/assign`,
            {
                assigned_to: remediationForm.value.assigned_to,
                remediation_deadline: remediationForm.value.remediation_deadline,
                remediation_plan: remediationForm.value.remediation_plan,
            },
        );

        if (res.data.success) {
            toast.success('Đã giao trách nhiệm và kích hoạt SLA khắc phục.');
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể giao xử lý hồ sơ.');
    } finally {
        isProcessing.value = false;
    }
};

const acceptAssignment = async () => {
    if (!selectedReport.value) {
return;
}

    try {
        const res = await axios.post(`/api/operational-audit/reports/${selectedReport.value.id}/assignment/accept`);
        toast.success(res.data.message || 'Đã nhận việc.');
        router.reload();
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể xác nhận nhận việc.');
    }
};

const rejectAssignment = async () => {
    if (!selectedReport.value) {
return;
}

    const reason = window.prompt('Lý do từ chối nhận việc (bắt buộc):', 'Không thuộc phạm vi phụ trách hoặc thiếu nguồn lực.');

    if (!reason?.trim()) {
return;
}

    try {
        const res = await axios.post(`/api/operational-audit/reports/${selectedReport.value.id}/assignment/reject`, { reason });
        toast.success(res.data.message || 'Đã gửi lý do từ chối.');
        router.reload();
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể từ chối nhận việc.');
    }
};

const acknowledgeReport = async () => {
    if (!selectedReport.value) {
return;
}

    const responseText = window.prompt('Phản hồi của chi nhánh và cam kết xử lý:', 'Đã tiếp nhận, sẽ xử lý theo hạn SLA và cập nhật bằng chứng.');

    if (!responseText?.trim()) {
return;
}

    try {
        const res = await axios.post(`/api/operational-audit/reports/${selectedReport.value.id}/acknowledge`, { response: responseText });
        toast.success(res.data.message || 'Đã xác nhận hồ sơ.');
        router.reload();
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể xác nhận hồ sơ.');
    }
};

const submitRemediation = async () => {
    if (!selectedReport.value || !remediationForm.value.remediation_notes.trim()) {
        toast.error('Vui lòng ghi rõ kết quả khắc phục.');

        return;
    }

    isProcessing.value = true;

    try {
        const payload = new FormData();
        payload.append('remediation_notes', remediationForm.value.remediation_notes);

        if (remediationForm.value.remediation_proof) {
            payload.append('remediation_proof', remediationForm.value.remediation_proof);
        }

        const res = await axios.post(
            `/api/operational-audit/reports/${selectedReport.value.id}/remediation`,
            payload,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );

        if (res.data.success) {
            toast.success('Đã nộp khắc phục và chuyển hồ sơ sang chờ tái kiểm.');
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể nộp kết quả khắc phục.');
    } finally {
        isProcessing.value = false;
    }
};

const submitReinspection = async () => {
    if (!selectedReport.value || !reinspectionNotes.value.trim()) {
        toast.error('Vui lòng ghi nhận xét tái kiểm.');

        return;
    }

    isProcessing.value = true;

    try {
        const payload = new FormData();
        payload.append('result', reinspectionResult.value);
        payload.append('reinspection_notes', reinspectionNotes.value);

        if (reinspectionProof.value) {
payload.append('reinspection_proof', reinspectionProof.value);
}

        const res = await axios.post(`/api/operational-audit/reports/${selectedReport.value.id}/reinspect`, payload, { headers: { 'Content-Type': 'multipart/form-data' } });

        if (res.data.success) {
            toast.success(res.data.message);
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể ghi nhận kết quả tái kiểm.');
    } finally {
        isProcessing.value = false;
    }
};

const onReinspectionProofChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    reinspectionProof.value = input.files?.[0] ?? null;
    reinspectionFileName.value = reinspectionProof.value?.name ?? '';
};

const onRemediationProofChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    remediationForm.value.remediation_proof = file;
    remediationFileName.value = file?.name ?? '';
};

const pendingCount = computed(() => {
    return props.reports.filter((r) => r.status === 'pending_owner_approval')
        .length;
});

const filteredReports = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return props.reports.filter((report) => {
        const matchesQuery = !query
            || [
                report.report_code,
                report.branch?.name,
                report.policy?.title,
                report.offender?.name,
                report.assignee?.name,
            ]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(query));
        const matchesStatus = statusFilter.value === 'all' || report.status === statusFilter.value;
        const matchesSeverity = severityFilter.value === 'all' || report.severity_level === severityFilter.value;
        const matchesBranch = branchFilter.value === 'all' || String(report.branch?.id) === branchFilter.value;

        return matchesQuery && matchesStatus && matchesSeverity && matchesBranch;
    });
});

const eligibleAssignees = computed(() => {
    const branchId = selectedReport.value?.branch?.id;

    return props.employees.filter(
        (employee) => !branchId || Number(employee.branch_id) === Number(branchId),
    );
});

const availablePlansForReport = computed(() => {
    const branchId = reportForm.value.branch_id;

    return (props.inspectionPlans || []).filter((plan) =>
        !['completed', 'cancelled'].includes(plan.status)
        && (!plan.branch?.id || Number(plan.branch.id) === Number(branchId)),
    );
});

const activityForSelected = computed(() => {
    if (!selectedReport.value) {
        return [];
    }

    return props.auditTrail?.[String(selectedReport.value.id)]
        || props.auditTrail?.[selectedReport.value.id]
        || [];
});

const totalPenaltyApproved = computed(() => {
    return Number(props.reportStats?.approved_penalty ?? props.reports
        .filter((r) => ['approved', 'remediation_in_progress', 'reinspection_pending', 'closed'].includes(r.status))
        .reduce((sum, r) => sum + Number(r.penalty_amount || 0), 0));
});

const trendMax = computed(() => Math.max(1, ...(props.trend || []).map((item) => Number(item.total || 0))));

const rejectedCount = computed(
    () => props.reports.filter((r) => r.status === 'rejected').length,
);

const getStatusMeta = (status: string) => {
    switch (status) {
        case 'pending_owner_approval':
            return {
                label: 'Chờ duyệt',
                className:
                    'border-amber-200/70 bg-amber-500/10 text-amber-700 dark:border-amber-500/20 dark:text-amber-300',
            };
        case 'approved':
            return {
                label: 'Đã duyệt',
                className:
                    'border-emerald-200/70 bg-emerald-500/10 text-emerald-700 dark:border-emerald-500/20 dark:text-emerald-300',
            };
        case 'remediation_in_progress':
            return {
                label: 'Đang khắc phục',
                className:
                    'border-indigo-200/70 bg-indigo-500/10 text-indigo-700 dark:border-indigo-500/20 dark:text-indigo-300',
            };
        case 'reinspection_pending':
            return {
                label: 'Chờ tái kiểm',
                className:
                    'border-sky-200/70 bg-sky-500/10 text-sky-700 dark:border-sky-500/20 dark:text-sky-300',
            };
        case 'closed':
            return {
                label: 'Đã đóng',
                className:
                    'border-emerald-200/70 bg-emerald-500/10 text-emerald-700 dark:border-emerald-500/20 dark:text-emerald-300',
            };
        default:
            return {
                label: 'Đã từ chối',
                className: 'border-border bg-muted text-muted-foreground',
            };
    }
};

const getSeverityMeta = (severity: string) => {
    switch (severity) {
        case 'critical':
            return { label: 'Nghiêm trọng', className: 'text-rose-600 dark:text-rose-300' };
        case 'severe':
            return { label: 'Cao', className: 'text-orange-600 dark:text-orange-300' };
        case 'minor':
            return { label: 'Nhẹ', className: 'text-slate-500 dark:text-slate-400' };
        default:
            return { label: 'Trung bình', className: 'text-amber-600 dark:text-amber-300' };
    }
};

const getPlanStatusMeta = (status: string) => {
    const map: Record<string, { label: string; className: string }> = {
        planned: { label: 'Đã lập kế hoạch', className: 'text-amber-700 dark:text-amber-300' },
        in_progress: { label: 'Đang thực hiện', className: 'text-indigo-700 dark:text-indigo-300' },
        completed: { label: 'Đã hoàn tất', className: 'text-emerald-700 dark:text-emerald-300' },
        cancelled: { label: 'Đã hủy', className: 'text-muted-foreground' },
    };

    return map[status] || map.planned;
};

const getPlanTypeLabel = (type: string) => ({
    routine: 'Định kỳ',
    thematic: 'Chuyên đề',
    surprise: 'Đột xuất',
    follow_up: 'Tái kiểm',
}[type] || type);

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount || 0);
};

const formatDate = (dt: string) => {
    if (!dt) {
        return '-';
    }

    return new Date(dt).toLocaleDateString('vi-VN');
};
</script>

<template>
    <Head :title="isOverview ? 'Tổng quan thanh tra' : 'Giám sát vận hành & Biên bản phạt'" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <section
            class="relative overflow-hidden rounded-3xl border border-rose-200/70 bg-gradient-to-br from-rose-50 via-background to-indigo-50 px-5 py-5 shadow-sm md:px-7 md:py-6 dark:border-rose-500/20 dark:from-rose-950/50 dark:via-background dark:to-indigo-950/20"
        >
            <div
                class="pointer-events-none absolute -top-24 -right-20 size-64 rounded-full bg-rose-500/10 blur-3xl dark:bg-rose-500/20"
            />
            <div
                class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-rose-600 text-white shadow-lg shadow-rose-600/20"
                    >
                        <ShieldAlert class="size-6" />
                    </div>
                    <div>
                        <p
                            class="mb-1 text-[10px] font-bold tracking-[0.18em] text-rose-600 uppercase dark:text-rose-300"
                        >
                            Kiểm soát tuân thủ
                        </p>
                        <h1
                            class="text-2xl font-black tracking-tight text-foreground md:text-3xl"
                        >
                            {{ isOverview ? 'Tổng quan thanh tra' : 'Giám Sát Vận Hành & Biên Bản Phạt' }}
                        </h1>
                        <p
                            class="mt-1 max-w-2xl text-sm leading-6 text-muted-foreground"
                        >
                            <template v-if="isOverview">
                                Tổng hợp rủi ro, xu hướng vi phạm và tiến độ kiểm tra tại toàn bộ chi nhánh.
                            </template>
                            <template v-else>
                                Theo dõi vi phạm, SLA khắc phục, bằng chứng và kết quả tái kiểm
                                tại toàn bộ chi nhánh.
                            </template>
                        </p>
                    </div>
                </div>

                <Button
                    v-if="isInspector"
                    @click="openCreateModal"
                    class="h-10 shrink-0 gap-2 rounded-xl bg-rose-600 px-4 text-xs font-bold text-white shadow-md shadow-rose-600/20 hover:bg-rose-700"
                >
                    <Plus class="size-4" /> Lập biên bản mới
                </Button>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1.35fr_1fr]">
            <Card class="overflow-hidden border-border/70">
                <CardHeader class="flex flex-row items-start justify-between gap-4 border-b border-border/60 bg-muted/15">
                    <div>
                        <p class="text-[10px] font-bold tracking-[0.16em] text-rose-600 uppercase dark:text-rose-300">Điểm nóng theo chi nhánh</p>
                        <h2 class="mt-1 text-lg font-bold text-foreground">Bảng điều hành rủi ro</h2>
                        <CardDescription class="mt-1 text-xs">Ưu tiên xử lý dựa trên hồ sơ mở, quá SLA và mức độ nghiêm trọng.</CardDescription>
                    </div>
                    <BarChart3 class="mt-1 size-5 text-rose-600 dark:text-rose-300" />
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="!branchInsights?.length" class="p-6 text-center text-xs text-muted-foreground">Chưa có dữ liệu chi nhánh.</div>
                    <div v-for="branch in branchInsights" :key="branch.id" class="flex items-center gap-3 border-b border-border/50 px-5 py-3 last:border-0">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted text-xs font-black text-foreground">{{ branch.risk_score }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-3">
                                <p class="truncate text-xs font-bold text-foreground">{{ branch.name }}</p>
                                <span :class="['text-[10px] font-bold', branch.risk_level === 'critical' ? 'text-rose-600 dark:text-rose-300' : branch.risk_level === 'warning' ? 'text-amber-600 dark:text-amber-300' : 'text-emerald-600 dark:text-emerald-300']">
                                    {{ branch.risk_level === 'critical' ? 'Ưu tiên cao' : branch.risk_level === 'warning' ? 'Cần theo dõi' : 'Ổn định' }}
                                </span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full" :class="branch.risk_level === 'critical' ? 'bg-rose-500' : branch.risk_level === 'warning' ? 'bg-amber-500' : 'bg-emerald-500'" :style="{ width: `${Math.max(branch.risk_score, 4)}%` }" /></div>
                            <p class="mt-1 text-[10px] text-muted-foreground">{{ branch.open_reports }} hồ sơ mở · {{ branch.overdue_reports }} quá SLA · {{ branch.active_plans }} kế hoạch đang theo dõi</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="overflow-hidden border-border/70">
                <CardHeader class="border-b border-border/60 bg-muted/15">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold tracking-[0.16em] text-indigo-600 uppercase dark:text-indigo-300">Xu hướng</p>
                            <h2 class="mt-1 text-lg font-bold text-foreground">6 tháng gần nhất</h2>
                        </div>
                        <span class="rounded-full bg-indigo-500/10 px-2.5 py-1 text-[10px] font-bold text-indigo-700 dark:text-indigo-300">{{ reportStats.total }} hồ sơ</span>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3 p-5">
                    <div v-for="month in trend" :key="month.label" class="grid grid-cols-[42px_1fr_28px] items-center gap-2 text-[10px]">
                        <span class="font-semibold text-muted-foreground">{{ month.label }}</span>
                        <div class="h-2 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-indigo-500" :style="{ width: `${Math.max((month.total / trendMax) * 100, month.total ? 5 : 0)}%` }" /></div>
                        <span class="text-right font-bold text-foreground">{{ month.total }}</span>
                    </div>
                    <div class="flex items-center gap-4 border-t border-border/60 pt-3 text-[10px] text-muted-foreground"><span><i class="mr-1 inline-block size-2 rounded-full bg-indigo-500" />Tổng hồ sơ</span><span><i class="mr-1 inline-block size-2 rounded-full bg-rose-500" />Nghiêm trọng: {{ trend.reduce((sum, item) => sum + Number(item.critical || 0), 0) }}</span></div>
                </CardContent>
            </Card>
        </section>

        <section v-if="canManagePlans || inspectionPlans?.length" class="overflow-hidden rounded-3xl border border-border/70 bg-card/90 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-border/60 bg-muted/15 px-5 py-4 sm:flex-row sm:items-center sm:justify-between md:px-6">
                <div>
                    <div class="flex items-center gap-2"><CalendarPlus class="size-5 text-indigo-600 dark:text-indigo-300" /><h2 class="text-lg font-bold text-foreground">Kế hoạch kiểm tra</h2></div>
                    <p class="mt-1 text-xs text-muted-foreground">Chủ động lập phạm vi, đầu mối và thời hạn; biên bản phát sinh sẽ được gom vào đúng kế hoạch.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-amber-500/10 px-2.5 py-1 text-[10px] font-bold text-amber-700 dark:text-amber-300">{{ planStats.planned || 0 }} chờ thực hiện</span>
                    <span class="rounded-full bg-indigo-500/10 px-2.5 py-1 text-[10px] font-bold text-indigo-700 dark:text-indigo-300">{{ planStats.in_progress || 0 }} đang thực hiện</span>
                    <Button v-if="canManagePlans" @click="openPlanModal" size="sm" class="h-8 gap-1.5 rounded-lg bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"><CalendarPlus class="size-3.5" /> Tạo kế hoạch</Button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-xs">
                    <thead class="border-b border-border/60 bg-muted/35 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"><tr><th class="px-5 py-3">Kế hoạch</th><th class="px-3 py-3">Phạm vi</th><th class="px-3 py-3">Lịch & đầu mối</th><th class="px-3 py-3">Hồ sơ</th><th class="px-3 py-3">Trạng thái</th><th class="px-5 py-3 text-right">Xử lý</th></tr></thead>
                    <tbody class="divide-y divide-border/50">
                        <tr v-if="!inspectionPlans?.length"><td colspan="6" class="px-5 py-10 text-center text-muted-foreground">Chưa có kế hoạch kiểm tra. Hãy lập kế hoạch định kỳ đầu tiên.</td></tr>
                        <tr v-for="plan in inspectionPlans" :key="plan.id" class="hover:bg-muted/20">
                            <td class="px-5 py-3"><p class="font-mono font-bold text-indigo-600 dark:text-indigo-300">{{ plan.plan_code }}</p><p class="mt-1 max-w-[230px] truncate font-semibold text-foreground">{{ plan.title }}</p><p class="mt-1 text-[10px] text-muted-foreground">{{ getPlanTypeLabel(plan.inspection_type) }}</p></td>
                            <td class="max-w-[220px] px-3 py-3"><p class="font-semibold text-foreground">{{ plan.branch?.name || 'Toàn chuỗi' }}</p><p class="mt-1 truncate text-[10px] text-muted-foreground">{{ plan.scope }}</p></td>
                            <td class="px-3 py-3 text-muted-foreground"><p>{{ formatDate(plan.scheduled_date) }}<span v-if="plan.due_date"> → {{ formatDate(plan.due_date) }}</span></p><p class="mt-1 text-[10px]">{{ plan.lead_inspector?.name || 'Chưa phân công' }}</p></td>
                            <td class="px-3 py-3"><p class="font-bold text-foreground">{{ plan.reports_count }} biên bản</p><p v-if="plan.open_reports_count" class="mt-1 text-[10px] text-amber-600 dark:text-amber-300">{{ plan.open_reports_count }} đang mở</p></td>
                            <td class="px-3 py-3"><span :class="['rounded-full border border-border bg-muted/30 px-2.5 py-1 text-[10px] font-bold', getPlanStatusMeta(plan.status).className]">{{ getPlanStatusMeta(plan.status).label }}</span><p v-if="plan.is_overdue" class="mt-1 text-[10px] font-bold text-rose-600 dark:text-rose-300">Quá hạn kế hoạch</p></td>
                            <td class="px-5 py-3 text-right"><div v-if="canManagePlans && ['planned', 'in_progress'].includes(plan.status)" class="flex justify-end gap-1.5"><Button v-if="plan.status === 'planned'" @click="updatePlanStatus(plan, 'start')" size="sm" variant="outline" class="h-7 gap-1 rounded-lg px-2 text-[10px]"><Play class="size-3" /> Bắt đầu</Button><Button v-if="plan.status === 'in_progress'" @click="updatePlanStatus(plan, 'complete')" size="sm" class="h-7 gap-1 rounded-lg bg-emerald-600 px-2 text-[10px] font-bold text-white hover:bg-emerald-700"><CheckCheck class="size-3" /> Hoàn tất</Button><Button @click="updatePlanStatus(plan, 'cancel')" size="sm" variant="ghost" class="h-7 gap-1 rounded-lg px-2 text-[10px] text-rose-600 hover:bg-rose-500/10 dark:text-rose-300"><Ban class="size-3" /> Hủy</Button></div><span v-else class="text-[10px] text-muted-foreground">{{ plan.completed_at || plan.notes || '—' }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-7">
            <Card class="border-amber-200/60 dark:border-amber-500/20">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-[11px] font-bold tracking-wider text-amber-600 uppercase dark:text-amber-300"
                        >Chờ chủ duyệt</CardDescription
                    >
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-300"
                    >
                        <Clock class="size-4" />
                    </div>
                </CardHeader>
                <CardContent class="pb-5"
                    ><p class="text-3xl font-black text-foreground">
                        {{ pendingCount }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        cần được xử lý
                    </p></CardContent
                >
            </Card>
            <Card class="border-rose-200/60 dark:border-rose-500/20">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-[11px] font-bold tracking-wider text-rose-600 uppercase dark:text-rose-300"
                        >Tổng tiền phạt</CardDescription
                    >
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-300"
                    >
                        <DollarSign class="size-4" />
                    </div>
                </CardHeader>
                <CardContent class="pb-5"
                    ><p class="truncate text-2xl font-black text-foreground">
                        {{ formatCurrency(totalPenaltyApproved) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        đã được phê duyệt
                    </p></CardContent
                >
            </Card>
            <Card class="border-indigo-200/60 dark:border-indigo-500/20">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-[11px] font-bold tracking-wider text-indigo-600 uppercase dark:text-indigo-300"
                        >Tổng biên bản</CardDescription
                    >
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300"
                    >
                        <Gavel class="size-4" />
                    </div>
                </CardHeader>
                <CardContent class="pb-5"
                    ><p class="text-3xl font-black text-foreground">
                        {{ reports.length }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        đã ghi nhận trong hệ thống
                    </p></CardContent
                >
            </Card>
            <Card class="border-slate-200/70 dark:border-slate-700/50">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                        >Đã từ chối</CardDescription
                    >
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                    >
                        <XCircle class="size-4" />
                    </div>
                </CardHeader>
                <CardContent class="pb-5"
                    ><p class="text-3xl font-black text-foreground">
                        {{ rejectedCount }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        biên bản không được duyệt
                    </p></CardContent
                >
            </Card>
            <Card class="border-indigo-200/60 dark:border-indigo-500/20">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-[11px] font-bold tracking-wider text-indigo-600 uppercase dark:text-indigo-300">Đang khắc phục</CardDescription>
                    <UserCog class="size-4 text-indigo-600 dark:text-indigo-300" />
                </CardHeader>
                <CardContent class="pb-5">
                    <p class="text-3xl font-black text-foreground">{{ reportStats.in_remediation ?? 0 }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">đang chạy SLA xử lý</p>
                </CardContent>
            </Card>
            <Card class="border-sky-200/60 dark:border-sky-500/20">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-[11px] font-bold tracking-wider text-sky-600 uppercase dark:text-sky-300">Chờ tái kiểm</CardDescription>
                    <ClipboardCheck class="size-4 text-sky-600 dark:text-sky-300" />
                </CardHeader>
                <CardContent class="pb-5">
                    <p class="text-3xl font-black text-foreground">{{ reportStats.reinspection_pending ?? 0 }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">cần kết luận đạt / không đạt</p>
                </CardContent>
            </Card>
            <Card class="border-red-200/70 dark:border-red-500/20">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-[11px] font-bold tracking-wider text-red-600 uppercase dark:text-red-300">Quá SLA</CardDescription>
                    <AlertOctagon class="size-4 text-red-600 dark:text-red-300" />
                </CardHeader>
                <CardContent class="pb-5">
                    <p class="text-3xl font-black text-foreground">{{ reportStats.overdue ?? 0 }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">cần ưu tiên xử lý</p>
                </CardContent>
            </Card>
        </div>

        <section
            class="overflow-hidden rounded-3xl border border-border/70 bg-card/90 shadow-sm"
        >
            <div
                class="flex flex-col gap-1 border-b border-border/60 bg-muted/15 px-5 py-4 sm:flex-row sm:items-end sm:justify-between md:px-6"
            >
                <div>
                    <h2
                        class="text-lg font-bold tracking-tight text-foreground"
                    >
                        Danh sách biên bản vi phạm
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Theo dõi xuyên suốt từ lập biên bản, phê duyệt, giao khắc phục,
                        nộp bằng chứng đến tái kiểm và đóng hồ sơ.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button @click="exportReports" size="sm" variant="outline" class="h-8 gap-1.5 rounded-lg text-xs"><Download class="size-3.5" /> Xuất CSV</Button>
                    <span class="text-xs font-semibold text-muted-foreground"
                        >{{ filteredReports.length }} / {{ reports.length }} biên bản</span
                    >
                </div>
            </div>
            <div class="grid gap-3 border-b border-border/60 bg-muted/10 px-5 py-4 md:grid-cols-[1.5fr_1fr_1fr_1fr] md:px-6">
                <div class="relative">
                    <Search class="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                    <Input v-model="searchQuery" class="h-9 pl-9 text-xs" placeholder="Tìm mã biên bản, chi nhánh, người xử lý..." />
                </div>
                <div class="flex items-center gap-2">
                    <Filter class="size-4 shrink-0 text-muted-foreground" />
                    <select v-model="statusFilter" class="h-9 w-full rounded-lg border border-input bg-background px-3 text-xs text-foreground">
                        <option value="all">Tất cả trạng thái</option>
                        <option value="pending_owner_approval">Chờ Chủ duyệt</option>
                        <option value="approved">Đã duyệt</option>
                        <option value="remediation_in_progress">Đang khắc phục</option>
                        <option value="reinspection_pending">Chờ tái kiểm</option>
                        <option value="closed">Đã đóng</option>
                        <option value="rejected">Từ chối</option>
                    </select>
                </div>
                <select v-model="severityFilter" class="h-9 rounded-lg border border-input bg-background px-3 text-xs text-foreground">
                    <option value="all">Tất cả mức độ</option>
                    <option value="critical">Nghiêm trọng</option>
                    <option value="severe">Cao</option>
                    <option value="moderate">Trung bình</option>
                    <option value="minor">Nhẹ</option>
                </select>
                <select v-model="branchFilter" class="h-9 rounded-lg border border-input bg-background px-3 text-xs text-foreground">
                    <option value="all">Tất cả chi nhánh</option>
                    <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-left text-xs">
                    <thead
                        class="border-b border-border/60 bg-muted/35 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3">Mã biên bản</th>
                            <th class="px-3 py-3">Chi nhánh</th>
                            <th class="px-3 py-3">Quy định vi phạm</th>
                            <th class="px-3 py-3">Đối tượng</th>
                            <th class="px-3 py-3">Ngày vi phạm</th>
                            <th class="px-3 py-3 text-right">Tiền phạt</th>
                            <th class="px-3 py-3">Trạng thái</th>
                            <th class="px-5 py-3 text-right">Hành động</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/50">
                        <tr v-if="filteredReports.length === 0">
                            <td colspan="8" class="px-5 py-16 text-center">
                                <div
                                    class="mx-auto flex max-w-sm flex-col items-center"
                                >
                                    <div
                                        class="mb-3 flex size-12 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 dark:text-rose-300"
                                    >
                                        <ShieldAlert class="size-6" />
                                    </div>
                                    <p class="font-semibold text-foreground">
                                        Chưa có biên bản vi phạm
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Các biên bản được lập tại chi nhánh sẽ
                                        hiển thị ở đây.
                                    </p>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-for="r in filteredReports"
                            :key="r.id"
                            class="transition hover:bg-muted/25"
                        >
                            <td
                                class="px-5 py-4 font-mono font-bold text-rose-600 dark:text-rose-300"
                            >
                                {{ r.report_code }}
                            </td>
                            <td class="px-3 py-4 font-semibold text-foreground">
                                {{ r.branch?.name }}
                            </td>
                            <td
                                class="max-w-[220px] truncate px-3 py-4 text-muted-foreground"
                            >
                                <p class="truncate">{{ r.policy ? r.policy.title : 'Vi phạm tổng hợp' }}</p>
                                <p :class="['mt-1 text-[10px] font-bold', getSeverityMeta(r.severity_level).className]">
                                    {{ getSeverityMeta(r.severity_level).label }}
                                </p>
                            </td>
                            <td class="px-3 py-4 text-muted-foreground">
                                {{
                                    r.offender
                                        ? r.offender.name
                                        : 'Tập thể chi nhánh'
                                }}
                            </td>
                            <td
                                class="px-3 py-4 whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDate(r.infringement_date) }}
                            </td>
                            <td
                                class="px-3 py-4 text-right font-mono font-bold whitespace-nowrap text-rose-600 dark:text-rose-300"
                            >
                                {{ formatCurrency(r.penalty_amount) }}
                            </td>
                            <td class="p-3">
                                <span
                                    :class="[
                                        'inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold',
                                        getStatusMeta(r.status).className,
                                    ]"
                                    >{{ getStatusMeta(r.status).label }}</span
                                >
                                <p v-if="r.remediation_deadline" :class="['mt-1 text-[10px] font-semibold', r.is_overdue ? 'text-rose-600 dark:text-rose-300' : 'text-muted-foreground']">
                                    {{ r.is_overdue ? 'Quá SLA · ' : 'Hạn ' }}{{ formatDate(r.remediation_deadline) }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <Button
                                    @click="openDetailModal(r)"
                                    size="sm"
                                    variant="outline"
                                    class="h-8 gap-1 rounded-lg text-xs"
                                >
                                    Xem chi tiết
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Create Inspection Plan Modal -->
        <Teleport to="body">
        <div v-if="isPlanModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
            <div class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-3xl border border-border bg-background text-foreground shadow-2xl">
                <div class="flex items-center justify-between border-b border-border bg-muted/30 p-5"><div class="flex items-center gap-2"><div class="flex size-9 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300"><CalendarPlus class="size-5" /></div><div><h3 class="text-sm font-bold">Lập kế hoạch kiểm tra</h3><p class="mt-0.5 text-[11px] text-muted-foreground">Xác định rõ phạm vi, thời hạn và người chịu trách nhiệm.</p></div></div><button @click="isPlanModalOpen = false" class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"><X class="size-5" /></button></div>
                <div class="space-y-4 overflow-y-auto p-6 text-xs">
                    <div><label class="mb-1.5 block font-semibold text-foreground">Tên kế hoạch (*)</label><Input v-model="planForm.title" placeholder="Ví dụ: Kiểm tra an toàn thực phẩm tháng 9" class="text-xs" /></div>
                    <div class="grid gap-4 sm:grid-cols-2"><div><label class="mb-1.5 block font-semibold text-foreground">Loại kiểm tra</label><select v-model="planForm.inspection_type" class="w-full rounded-xl border border-input bg-background p-2.5 text-foreground"><option value="routine">Định kỳ</option><option value="thematic">Theo chuyên đề</option><option value="surprise">Đột xuất</option><option value="follow_up">Tái kiểm</option></select></div><div><label class="mb-1.5 block font-semibold text-foreground">Chi nhánh</label><select v-model="planForm.branch_id" class="w-full rounded-xl border border-input bg-background p-2.5 text-foreground"><option v-if="!isBranchScoped" :value="null">Toàn chuỗi</option><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option></select></div></div>
                    <div class="grid gap-4 sm:grid-cols-2"><div><label class="mb-1.5 block font-semibold text-foreground">Ngày dự kiến</label><Input v-model="planForm.scheduled_date" type="date" class="text-xs" /></div><div><label class="mb-1.5 block font-semibold text-foreground">Hạn hoàn tất</label><Input v-model="planForm.due_date" type="date" class="text-xs" /></div></div>
                    <div><label class="mb-1.5 block font-semibold text-foreground">Đầu mối thanh tra</label><select v-model="planForm.lead_inspector_id" class="w-full rounded-xl border border-input bg-background p-2.5 text-foreground"><option v-for="inspector in inspectors" :key="inspector.id" :value="inspector.id">{{ inspector.name }} · {{ inspector.email }}</option></select></div>
                    <div><label class="mb-1.5 block font-semibold text-foreground">Phạm vi & tiêu chí kiểm tra (*)</label><textarea v-model="planForm.scope" rows="5" placeholder="Nêu khu vực, ca làm, quy trình cần đối chiếu, hồ sơ cần thu thập và tiêu chí kết luận..." class="min-h-28 w-full rounded-xl border border-input bg-background p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-indigo-500/30"></textarea></div>
                </div>
                <div class="flex items-center justify-between border-t border-border bg-muted/20 p-4"><Button @click="isPlanModalOpen = false" variant="ghost" size="sm" class="text-xs">Hủy</Button><Button @click="submitPlan" size="sm" :disabled="isProcessing" class="gap-1.5 rounded-xl bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"><CalendarPlus class="size-4" /> Lưu kế hoạch</Button></div>
            </div>
        </div>
        </Teleport>

        <!-- Create Report Modal (Inspector) -->
        <Teleport to="body">
        <div
            v-if="isCreateModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-3xl border border-border bg-background text-foreground shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-border bg-muted/30 p-5"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-9 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-300"
                        >
                            <AlertOctagon class="size-5" />
                        </div>
                        <h3 class="text-sm font-bold">
                            Lập biên bản vi phạm & phạt vận hành
                        </h3>
                    </div>
                    <button
                        @click="isCreateModalOpen = false"
                        class="rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div class="max-h-[75vh] space-y-5 overflow-y-auto p-6 text-xs">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="mb-1.5 block font-semibold text-foreground"
                                >Chi nhánh vi phạm (*)</label
                            >
                            <select
                                v-model="reportForm.branch_id"
                                class="w-full rounded-xl border border-input bg-background p-2.5 font-medium text-foreground outline-none focus:ring-2 focus:ring-rose-500/30"
                            >
                                <option
                                    v-for="b in branches"
                                    :key="b.id"
                                    :value="b.id"
                                >
                                    {{ b.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block font-semibold text-foreground"
                                >Ngày phát hiện vi phạm</label
                            >
                            <Input
                                v-model="reportForm.infringement_date"
                                type="date"
                                class="text-xs"
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 block font-semibold text-foreground">Mức độ rủi ro (*)</label>
                            <select
                                v-model="reportForm.severity_level"
                                class="w-full rounded-xl border border-input bg-background p-2.5 font-medium text-foreground outline-none focus:ring-2 focus:ring-rose-500/30"
                            >
                                <option value="minor">Nhẹ</option>
                                <option value="moderate">Trung bình</option>
                                <option value="severe">Cao</option>
                                <option value="critical">Nghiêm trọng</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="reportForm.operational_inspection_id" class="rounded-xl border border-indigo-200/70 bg-indigo-500/5 p-3 text-[11px] text-indigo-700 dark:border-indigo-500/30 dark:text-indigo-300">
                        Biên bản này được lập từ phiên kiểm tra hiện trường <span class="font-mono font-bold">#{{ reportForm.operational_inspection_id }}</span>; hệ thống sẽ liên kết hai hồ sơ để truy nguyên.
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Điều khoản / quy định vi phạm (nếu có)</label
                        >
                        <div v-if="availablePlansForReport.length">
                            <label class="mb-1.5 block font-semibold text-foreground">Gắn vào kế hoạch kiểm tra</label>
                            <select v-model="reportForm.inspection_plan_id" class="w-full rounded-xl border border-input bg-background p-2.5 font-medium text-foreground outline-none focus:ring-2 focus:ring-indigo-500/30">
                                <option :value="null">-- Không thuộc kế hoạch --</option>
                                <option v-for="plan in availablePlansForReport" :key="plan.id" :value="plan.id">[{{ plan.plan_code }}] {{ plan.title }}</option>
                            </select>
                            <p class="mt-1 text-[10px] text-muted-foreground">Kế hoạch sẽ tự chuyển sang “Đang thực hiện” khi biên bản đầu tiên được gửi.</p>
                        </div>

                        <select
                            v-model="reportForm.policy_id"
                            @change="onPolicySelect"
                            class="w-full rounded-xl border border-input bg-background p-2.5 font-medium text-foreground outline-none focus:ring-2 focus:ring-rose-500/30"
                        >
                            <option :value="null">
                                -- Lỗi vi phạm thực tế tự do --
                            </option>
                            <option
                                v-for="p in policies"
                                :key="p.id"
                                :value="p.id"
                            >
                                [{{ p.policy_code }}] {{ p.title }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Cá nhân vi phạm
                            <span class="font-normal text-muted-foreground"
                                >(để trống nếu phạt tập thể chi nhánh)</span
                            ></label
                        >
                        <select
                            v-model="reportForm.offender_user_id"
                            class="w-full rounded-xl border border-input bg-background p-2.5 font-medium text-foreground outline-none focus:ring-2 focus:ring-rose-500/30"
                        >
                            <option :value="null">
                                -- Phạt Tập Thể Chi Nhánh --
                            </option>
                            <option
                                v-for="emp in employees"
                                :key="emp.id"
                                :value="emp.id"
                            >
                                {{ emp.name }} ({{ emp.email }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Mô tả chi tiết hành vi vi phạm (*)</label
                        >
                        <textarea
                            v-model="reportForm.description"
                            rows="4"
                            placeholder="Mô tả cụ thể diễn biến vi phạm, hình ảnh ghi nhận và các yếu tố liên quan..."
                            class="min-h-28 w-full rounded-xl border border-input bg-background p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-rose-500/30"
                        ></textarea>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="mb-1.5 block font-semibold text-foreground">Nhóm phát hiện</label><Input v-model="reportForm.finding_category" placeholder="ATTP, PCCC, tài sản, quy trình..." class="text-xs" /></div>
                        <div><label class="mb-1.5 block font-semibold text-foreground">Điều khoản tham chiếu</label><Input v-model="reportForm.requirement_reference" placeholder="Mã quy định / mục checklist..." class="text-xs" /></div>
                        <div class="sm:col-span-2"><label class="mb-1.5 block font-semibold text-foreground">Hiện trạng quan sát</label><textarea v-model="reportForm.observed_condition" rows="2" placeholder="Ghi nhận khách quan tại hiện trường..." class="w-full rounded-xl border border-input bg-background p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-rose-500/30"></textarea></div>
                        <div><label class="mb-1.5 block font-semibold text-foreground">Nguyên nhân gốc</label><textarea v-model="reportForm.root_cause" rows="2" placeholder="Vì sao sai lệch xảy ra?" class="w-full rounded-xl border border-input bg-background p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-rose-500/30"></textarea></div>
                        <div><label class="mb-1.5 block font-semibold text-foreground">Hành động khắc phục</label><textarea v-model="reportForm.corrective_action" rows="2" placeholder="Việc cần làm để sửa lỗi..." class="w-full rounded-xl border border-input bg-background p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-rose-500/30"></textarea></div>
                        <div class="sm:col-span-2"><label class="mb-1.5 block font-semibold text-foreground">Phòng ngừa tái diễn</label><textarea v-model="reportForm.preventive_action" rows="2" placeholder="Cập nhật quy trình, đào tạo, kiểm soát..." class="w-full rounded-xl border border-input bg-background p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-rose-500/30"></textarea></div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1.5 block font-semibold text-foreground">Hạn khắc phục (SLA)</label>
                            <Input v-model="reportForm.remediation_deadline" type="date" class="text-xs" />
                        </div>
                        <div class="flex items-end pb-2 text-[11px] text-muted-foreground">
                            Vi phạm nghiêm trọng bắt buộc có kế hoạch và bằng chứng khắc phục.
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block font-semibold text-foreground">Kế hoạch khắc phục ban đầu</label>
                        <textarea
                            v-model="reportForm.remediation_plan"
                            rows="3"
                            placeholder="Nêu biện pháp sửa lỗi, người phối hợp và tiêu chí đạt..."
                            class="min-h-20 w-full rounded-xl border border-input bg-background p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-rose-500/30"
                        ></textarea>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Ảnh bằng chứng vi phạm</label
                        >
                        <input
                            type="file"
                            accept="image/*"
                            @change="onProofPhotoChange"
                            class="block w-full cursor-pointer rounded-xl border border-input bg-background p-2.5 text-xs text-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-rose-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-rose-700"
                        />
                        <p
                            v-if="proofFileName"
                            class="mt-1.5 text-[11px] font-medium text-muted-foreground"
                        >
                            Đã chọn: {{ proofFileName }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Mức phạt đề xuất trình chủ doanh nghiệp
                            (VNĐ)</label
                        >
                        <Input
                            v-model.number="reportForm.penalty_amount"
                            type="number"
                            step="50000"
                            class="text-xs font-bold text-rose-600 dark:text-rose-300"
                        />
                    </div>
                </div>

                <div
                    class="flex items-center justify-between border-t border-border bg-muted/20 p-4"
                >
                    <Button
                        @click="isCreateModalOpen = false"
                        variant="ghost"
                        size="sm"
                        class="text-xs"
                        >Hủy</Button
                    >
                    <Button
                        @click="submitReport"
                        size="sm"
                        :disabled="isProcessing"
                        class="gap-1.5 rounded-xl bg-rose-600 text-xs font-semibold text-white hover:bg-rose-700"
                    >
                        <Gavel class="size-4" /> Gửi trình duyệt
                    </Button>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- Detail / Approve Report Modal -->
        <Teleport to="body">
        <div
            v-if="isDetailModalOpen && selectedReport"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-3xl border border-border bg-background text-foreground shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-border bg-muted/30 p-5"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-9 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-300"
                        >
                            <Gavel class="size-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold">
                                Chi tiết biên bản vi phạm
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Mã biên bản:
                                <span
                                    class="font-mono font-bold text-rose-600 dark:text-rose-300"
                                    >{{ selectedReport.report_code }}</span
                                >
                            </p>
                        </div>
                    </div>
                    <button
                        @click="isDetailModalOpen = false"
                        class="rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div class="max-h-[75vh] space-y-5 overflow-y-auto p-6 text-xs">
                    <div
                        class="space-y-2 rounded-2xl border border-border bg-muted/20 p-4 text-muted-foreground"
                    >
                        <div>
                            <strong>Chi nhánh vi phạm:</strong>
                            {{ selectedReport.branch?.name }}
                        </div>
                        <div v-if="selectedReport.inspection_plan">
                            <strong>Kế hoạch kiểm tra:</strong>
                            <span class="ml-1 font-mono font-bold text-indigo-600 dark:text-indigo-300">{{ selectedReport.inspection_plan.plan_code }}</span>
                            <span class="ml-1">· {{ selectedReport.inspection_plan.title }}</span>
                        </div>
                        <div>
                            <strong>Giám sát viên lập:</strong>
                            {{ selectedReport.inspector?.name }}
                        </div>
                        <div>
                            <strong>Đối tượng vi phạm:</strong>
                            {{
                                selectedReport.offender
                                    ? selectedReport.offender.name
                                    : 'Tập thể Chi nhánh'
                            }}
                        </div>
                        <div>
                            <strong>Điều khoản vi phạm:</strong>
                            {{
                                selectedReport.policy
                                    ? selectedReport.policy.title
                                    : 'Lỗi vận hành thực tế'
                            }}
                        </div>
                        <div>
                            <strong>Mức phạt đề xuất:</strong>
                            <strong
                                class="ml-1 font-mono text-sm font-bold text-rose-600 dark:text-rose-300"
                                >{{
                                    formatCurrency(
                                        selectedReport.penalty_amount,
                                    )
                                }}</strong
                            >
                        </div>
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <span class="rounded-full border border-border bg-background px-2.5 py-1 text-[10px] font-bold" :class="getSeverityMeta(selectedReport.severity_level).className">
                                Mức độ: {{ getSeverityMeta(selectedReport.severity_level).label }}
                            </span>
                            <span :class="['rounded-full border px-2.5 py-1 text-[10px] font-bold', getStatusMeta(selectedReport.status).className]">
                                {{ getStatusMeta(selectedReport.status).label }}
                            </span>
                        </div>
                        <div v-if="selectedReport.assignee || selectedReport.remediation_deadline">
                            <strong>Kiểm soát khắc phục:</strong>
                            {{ selectedReport.assignee?.name || 'Chưa giao người phụ trách' }}
                            <span v-if="selectedReport.assignment_status" class="ml-1 rounded-full bg-indigo-500/10 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:text-indigo-300">{{ selectedReport.assignment_status }}</span>
                            <span v-if="selectedReport.remediation_deadline" :class="selectedReport.is_overdue ? 'font-bold text-rose-600 dark:text-rose-300' : ''">
                                · Hạn {{ formatDate(selectedReport.remediation_deadline) }}{{ selectedReport.is_overdue ? ' · Quá SLA' : '' }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Mô tả diễn biến vi phạm</label
                        >
                        <div
                            class="rounded-xl border border-border bg-muted/30 p-3 leading-relaxed whitespace-pre-line text-foreground"
                        >
                            {{ selectedReport.description }}
                        </div>
                    </div>

                    <div v-if="selectedReport.proof_photo_url">
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Bằng chứng hình ảnh</label
                        >
                        <a
                            :href="selectedReport.proof_photo_url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex rounded-xl border border-rose-200/70 bg-rose-500/10 px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-500/15 dark:border-rose-500/20 dark:text-rose-300"
                        >
                            Xem ảnh bằng chứng
                        </a>
                    </div>

                    <div v-if="selectedReport.assignment_status === 'assigned' && selectedReport.assignee?.id === currentUserId" class="flex flex-wrap items-center gap-2 rounded-2xl border border-amber-200/70 bg-amber-500/5 p-4 dark:border-amber-500/20">
                        <div class="mr-auto text-xs text-amber-800 dark:text-amber-200">Hồ sơ đang chờ bạn xác nhận nhận việc.</div>
                        <Button size="sm" class="bg-emerald-600 text-xs text-white hover:bg-emerald-700" @click="acceptAssignment">Xác nhận nhận việc</Button>
                        <Button size="sm" variant="outline" class="text-xs text-rose-600" @click="rejectAssignment">Từ chối có lý do</Button>
                    </div>

                    <div v-if="canAcknowledge && !selectedReport.branch_acknowledged_at && selectedReport.status !== 'rejected'" class="flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200/70 bg-amber-500/5 p-4 dark:border-amber-500/20">
                        <div class="mr-auto text-xs text-amber-800 dark:text-amber-200">Chi nhánh cần xác nhận đã tiếp nhận phát hiện và nêu cam kết xử lý.</div>
                        <Button size="sm" class="bg-amber-600 text-xs text-white hover:bg-amber-700" @click="acknowledgeReport">Xác nhận tiếp nhận</Button>
                    </div>

                    <div v-if="selectedReport.remediation_plan" class="rounded-2xl border border-indigo-200/70 bg-indigo-500/5 p-4 dark:border-indigo-500/20">
                        <div class="flex items-center gap-2 font-semibold text-indigo-700 dark:text-indigo-300">
                            <CalendarClock class="size-4" /> Kế hoạch khắc phục
                        </div>
                        <p class="mt-2 whitespace-pre-line leading-relaxed text-foreground">{{ selectedReport.remediation_plan }}</p>
                    </div>

                    <div v-if="selectedReport.remediation_notes" class="rounded-2xl border border-emerald-200/70 bg-emerald-500/5 p-4 dark:border-emerald-500/20">
                        <div class="flex items-center gap-2 font-semibold text-emerald-700 dark:text-emerald-300">
                            <FileCheck2 class="size-4" /> Kết quả khắc phục
                        </div>
                        <p class="mt-2 whitespace-pre-line leading-relaxed text-foreground">{{ selectedReport.remediation_notes }}</p>
                        <a v-if="selectedReport.remediation_proof_url" :href="selectedReport.remediation_proof_url" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 hover:underline dark:text-emerald-300">
                            <Upload class="size-3" /> Xem bằng chứng khắc phục
                        </a>
                        <p v-if="selectedReport.remediation_submitted_at" class="mt-1 text-[10px] text-muted-foreground">Nộp lúc {{ selectedReport.remediation_submitted_at }}</p>
                    </div>

                    <div v-if="canManageRemediation && ['approved', 'remediation_in_progress'].includes(selectedReport.status)" class="space-y-3 rounded-2xl border border-indigo-200/70 bg-indigo-500/5 p-4 dark:border-indigo-500/20">
                        <div class="flex items-center gap-2 font-semibold text-indigo-700 dark:text-indigo-300">
                            <UserCog class="size-4" /> Giao khắc phục & thiết lập SLA
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block font-semibold text-foreground">Người phụ trách</label>
                                <select v-model="remediationForm.assigned_to" class="w-full rounded-xl border border-input bg-background p-2.5 text-xs text-foreground">
                                    <option :value="null">-- Chưa chỉ định --</option>
                                    <option v-for="employee in eligibleAssignees" :key="employee.id" :value="employee.id">{{ employee.name }} · {{ employee.email }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block font-semibold text-foreground">Hạn hoàn thành</label>
                                <Input v-model="remediationForm.remediation_deadline" type="date" class="text-xs" />
                            </div>
                        </div>
                        <textarea v-model="remediationForm.remediation_plan" rows="3" placeholder="Kế hoạch, tiêu chí đạt và cách xác minh..." class="min-h-20 w-full rounded-xl border border-input bg-background p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-indigo-500/30"></textarea>
                        <Button @click="submitAssignment" size="sm" :disabled="isProcessing" class="gap-1.5 rounded-xl bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700">
                            <UserCog class="size-4" /> Lưu phân công
                        </Button>
                    </div>

                    <div v-if="selectedReport.can_submit_remediation && ['approved', 'remediation_in_progress'].includes(selectedReport.status)" class="space-y-3 rounded-2xl border border-emerald-200/70 bg-emerald-500/5 p-4 dark:border-emerald-500/20">
                        <div class="flex items-center gap-2 font-semibold text-emerald-700 dark:text-emerald-300">
                            <CheckCircle2 class="size-4" /> Nộp kết quả khắc phục
                        </div>
                        <textarea v-model="remediationForm.remediation_notes" rows="3" placeholder="Mô tả việc đã làm, kết quả đo được và phần còn tồn..." class="min-h-20 w-full rounded-xl border border-input bg-background p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-emerald-500/30"></textarea>
                        <input type="file" accept="image/*" @change="onRemediationProofChange" class="block w-full cursor-pointer rounded-xl border border-input bg-background p-2 text-xs text-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white" />
                        <p v-if="remediationFileName" class="text-[11px] text-muted-foreground">Đã chọn: {{ remediationFileName }}</p>
                        <Button @click="submitRemediation" size="sm" :disabled="isProcessing" class="gap-1.5 rounded-xl bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700">
                            <FileCheck2 class="size-4" /> Gửi chờ tái kiểm
                        </Button>
                    </div>

                    <div v-if="canReinspect && selectedReport.status === 'reinspection_pending'" class="space-y-3 rounded-2xl border border-sky-200/70 bg-sky-500/5 p-4 dark:border-sky-500/20">
                        <div class="flex items-center gap-2 font-semibold text-sky-700 dark:text-sky-300">
                            <ClipboardCheck class="size-4" /> Kết luận tái kiểm
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-xs" :class="reinspectionResult === 'pass' ? 'border-emerald-500 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'border-border'">
                                <input v-model="reinspectionResult" type="radio" value="pass" /> Đạt, đóng hồ sơ
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-xs" :class="reinspectionResult === 'fail' ? 'border-rose-500 bg-rose-500/10 text-rose-700 dark:text-rose-300' : 'border-border'">
                                <input v-model="reinspectionResult" type="radio" value="fail" /> Chưa đạt, trả lại
                            </label>
                        </div>
                        <textarea v-model="reinspectionNotes" rows="3" placeholder="Nêu tiêu chí đã kiểm, bằng chứng đối chiếu và kết luận..." class="min-h-20 w-full rounded-xl border border-input bg-background p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-sky-500/30"></textarea>
                        <input type="file" accept="image/*" @change="onReinspectionProofChange" class="block w-full cursor-pointer rounded-xl border border-input bg-background p-2 text-xs text-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-sky-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white" />
                        <p v-if="reinspectionFileName" class="text-[11px] text-muted-foreground">Đã chọn: {{ reinspectionFileName }}</p>
                        <Button @click="submitReinspection" size="sm" :disabled="isProcessing" class="gap-1.5 rounded-xl bg-sky-600 text-xs font-semibold text-white hover:bg-sky-700">
                            <ClipboardCheck class="size-4" /> Ghi nhận tái kiểm
                        </Button>
                    </div>

                    <div v-if="selectedReport.reinspection_notes" class="rounded-2xl border border-sky-200/70 bg-sky-500/5 p-4 dark:border-sky-500/20">
                        <div class="flex items-center gap-2 font-semibold text-sky-700 dark:text-sky-300">
                            <RotateCcw class="size-4" /> Kết quả tái kiểm: {{ selectedReport.reinspection_result === 'pass' ? 'Đạt' : 'Chưa đạt' }}
                        </div>
                        <p class="mt-2 whitespace-pre-line leading-relaxed text-foreground">{{ selectedReport.reinspection_notes }}</p>
                        <a v-if="selectedReport.reinspection_proof_url" :href="selectedReport.reinspection_proof_url" target="_blank" rel="noopener" class="mt-2 inline-flex text-[11px] font-bold text-sky-700 hover:underline dark:text-sky-300">Xem bằng chứng tái kiểm</a>
                        <p v-if="selectedReport.reinspected_at" class="mt-1 text-[10px] text-muted-foreground">Thực hiện lúc {{ selectedReport.reinspected_at }} · {{ selectedReport.reinspector?.name }}</p>
                    </div>

                    <div v-if="selectedReport.actions?.length" class="space-y-2 rounded-2xl border border-violet-200/70 bg-violet-500/5 p-4 dark:border-violet-500/20">
                        <div class="flex items-center gap-2 font-semibold text-violet-700 dark:text-violet-300"><ClipboardList class="size-4" /> CAPA liên quan</div>
                        <div v-for="action in selectedReport.actions" :key="action.id" class="rounded-xl border border-border/70 bg-background/60 p-3 text-xs"><div class="flex flex-wrap items-center justify-between gap-2"><span class="font-bold text-foreground">{{ action.title }}</span><span class="rounded-full bg-muted px-2 py-1 text-[10px] font-bold">{{ action.status }}</span></div><p class="mt-1 text-muted-foreground">{{ action.assignee?.name || 'Chưa phân công' }} · Hạn {{ action.due_date || 'Chưa đặt' }}</p></div>
                    </div>

                    <div
                        v-if="
                            isOwner &&
                            selectedReport.status === 'pending_owner_approval'
                        "
                    >
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Ý kiến & chỉ đạo của chủ doanh nghiệp</label
                        >
                        <textarea
                            v-model="ownerNotes"
                            rows="3"
                            placeholder="Nhập ghi chú chỉ đạo hoặc lý do từ chối biên bản phạt..."
                            class="min-h-24 w-full rounded-xl border border-input bg-background p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-rose-500/30"
                        ></textarea>
                    </div>

                    <div
                        v-else-if="selectedReport.owner_notes"
                        class="rounded-xl border border-indigo-200/60 bg-indigo-500/5 p-3 text-indigo-700 dark:border-indigo-500/20 dark:text-indigo-300"
                    >
                        <strong>Ý kiến Chủ doanh nghiệp:</strong>
                        {{ selectedReport.owner_notes }}
                    </div>

                    <div v-if="activityForSelected.length" class="space-y-2">
                        <div class="flex items-center gap-2 font-semibold text-foreground">
                            <Clock class="size-4 text-muted-foreground" /> Nhật ký xử lý hồ sơ
                        </div>
                        <div v-for="activity in activityForSelected" :key="activity.id" class="flex items-start gap-3 border-l-2 border-border pl-3 text-[11px]">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-foreground">{{ activity.user_name }} · {{ activity.action }}</p>
                                <p class="text-muted-foreground">{{ activity.created_at }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between border-t border-border bg-muted/20 p-4"
                >
                    <Button
                        @click="isDetailModalOpen = false"
                        variant="ghost"
                        size="sm"
                        class="text-xs"
                        >Đóng</Button
                    >

                    <div
                        v-if="
                            isOwner &&
                            selectedReport.status === 'pending_owner_approval'
                        "
                        class="flex gap-2"
                    >
                        <Button
                            @click="rejectReport"
                            size="sm"
                            variant="outline"
                            :disabled="isProcessing"
                            class="gap-1 rounded-xl border-rose-200/70 text-xs text-rose-600 hover:bg-rose-500/10 hover:text-rose-700 dark:border-rose-500/20 dark:text-rose-300"
                        >
                            <XCircle class="size-4" /> Từ chối
                        </Button>
                        <Button
                            @click="approveReport"
                            size="sm"
                            :disabled="isProcessing"
                            class="gap-1.5 rounded-xl bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700"
                        >
                            <UserCheck class="size-4" /> Phê duyệt
                        </Button>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>
    </div>
</template>
