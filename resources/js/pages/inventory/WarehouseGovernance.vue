<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Building2,
    CheckCheck,
    CheckCircle,
    DollarSign,
    Download,
    Eye,
    FileSpreadsheet,
    FileText,
    Gavel,
    Image as ImageIcon,
    Lock,
    MapPin,
    PenTool,
    Phone,
    Printer,
    RefreshCw,
    RotateCcw,
    Save,
    Search,
    ShieldAlert,
    ShieldCheck,
    Thermometer,
    UserCheck,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import WarehouseAiRecommendations from '@/components/WarehouseAiRecommendations.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    summary: any;
    rules: any;
    recentDisputes: Array<any>;
    employees: Array<any>;
    receivingReports: Array<any>;
}>();

const page = usePage();
const currentUser = computed(() => (page.props as any).auth?.user);

const activeTab = ref<'disputes' | 'rules'>('disputes');
const isProcessing = ref(false);
const selectedDispute = ref<any>(null);
const isResolveModalOpen = ref(false);
const receivingReportList = ref([...props.receivingReports]);

// Transporter Claim Collection Modal State
const isClaimModalOpen = ref(false);
const claimTargetDispute = ref<any>(null);
const claimForm = ref({
    collected_amount: 0,
    payment_method: 'cash' as 'cash' | 'bank',
    notes: '',
});

const openClaimCollectionModal = (dispute: any) => {
    claimTargetDispute.value = dispute;
    claimForm.value = {
        collected_amount: Number(dispute.penalty_amount || dispute.financial_loss_amount || 0),
        payment_method: 'cash',
        notes: `Thu tiền bồi thường giao nhận ${dispute.dispute_code}`,
    };
    isClaimModalOpen.value = true;
};

const submitClaimCollection = async () => {
    if (!claimTargetDispute.value) {
return;
}

    if (claimForm.value.collected_amount <= 0) {
        toast.error('Số tiền thu bồi thường phải lớn hơn 0.');

        return;
    }

    isProcessing.value = true;

    try {
        const response = await axios.post(
            `/api/warehouse-governance/disputes/${claimTargetDispute.value.id}/collect-claim`,
            claimForm.value
        );
        toast.success(response.data.message || 'Đã ghi nhận thu tiền bồi thường thành công.');
        isClaimModalOpen.value = false;
        router.reload({ only: ['summary', 'recentDisputes'] });
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi thu tiền bồi thường.');
    } finally {
        isProcessing.value = false;
    }
};

// Evidence Inspector Modal State
const isEvidenceModalOpen = ref(false);
const activeEvidence = ref<{
    title: string;
    code: string;
    photoUrl?: string | null;
    signatureUrl?: string | null;
    temperatureMin?: number | null;
    temperatureMax?: number | null;
    driverNotes?: string | null;
    receiverNotes?: string | null;
    confirmedAt?: string | null;
    branchName?: string | null;
    driverName?: string | null;
} | null>(null);

// Review Report Modal State (Thay thế prompt)
const isReviewReportModalOpen = ref(false);
const selectedReportForReview = ref<any>(null);
const reviewReportForm = ref({
    action_type: 'quarantine_destroy',
    notes: '',
});

// Search & Filter State
const searchQuery = ref('');
const statusFilter = ref<'all' | 'pending' | 'appealed' | 'penalized' | 'resolved'>('all');

// Governance Form State
const rulesForm = ref({
    max_auto_approve_variance_amount:
        props.rules?.max_auto_approve_variance_amount ?? 500000,
    max_auto_approve_variance_percent:
        props.rules?.max_auto_approve_variance_percent ?? 3,
    require_seal_code_on_dispatch:
        props.rules?.require_seal_code_on_dispatch ?? true,
    auto_dispute_on_discrepancy:
        props.rules?.auto_dispute_on_discrepancy ?? true,
    penalty_deduction_enabled: props.rules?.penalty_deduction_enabled ?? true,
});

// Resolution Form State
const resolutionForm = ref({
    responsible_type: 'warehouse_staff',
    responsible_user_id: null as number | null,
    penalty_mode: 'full' as 'full' | 'partial' | 'waived',
    penalty_amount: 0,
    write_off_inventory: true,
    claim_status: 'pending_collection',
    resolution_notes: '',
});

const formatMediaUrl = (path: string | null | undefined) => {
    if (!path) {
return '';
}

    if (path.startsWith('http') || path.startsWith('/') || path.startsWith('data:')) {
        return path;
    }

    return `/storage/${path}`;
};

const numberToVietnameseWords = (num: number): string => {
    if (!num || isNaN(num) || num <= 0) {
return 'Không đồng';
}

    const digits = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];

    const readTriple = (n: number, full: boolean) => {
        let res = '';
        const hundred = Math.floor(n / 100);
        const remainder = n % 100;
        const ten = Math.floor(remainder / 10);
        const unit = remainder % 10;

        if (full || hundred > 0) {
            res += digits[hundred] + ' trăm ';

            if (ten === 0 && unit > 0) {
res += 'lẻ ';
}
        }

        if (ten === 1) {
            res += 'mười ';

            if (unit === 5) {
res += 'lăm ';
} else if (unit > 0) {
res += digits[unit] + ' ';
}
        } else if (ten > 1) {
            res += digits[ten] + ' mươi ';

            if (unit === 1) {
res += 'mốt ';
} else if (unit === 5) {
res += 'lăm ';
} else if (unit > 0) {
res += digits[unit] + ' ';
}
        } else if (unit > 0 && !full && hundred === 0) {
            res += digits[unit] + ' ';
        } else if (unit > 0 && hundred > 0 && ten === 0) {
            res += digits[unit] + ' ';
        }

        return res.trim();
    };

    let n = Math.floor(num);
    const groups: number[] = [];

    while (n > 0) {
        groups.push(n % 1000);
        n = Math.floor(n / 1000);
    }

    const units = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ'];
    let words = '';

    for (let i = groups.length - 1; i >= 0; i--) {
        const val = groups[i];

        if (val > 0) {
            const part = readTriple(val, i < groups.length - 1);
            words += part + ' ' + units[i] + ' ';
        }
    }

    words = words.trim();

    if (!words) {
return 'Không đồng';
}

    return words.charAt(0).toUpperCase() + words.slice(1) + ' đồng';
};

const eligibleEmployees = computed(() => {
    const type = resolutionForm.value.responsible_type;
    const branchId = Number(
        selectedDispute.value?.supply_request?.to_branch_id ?? 0,
    );

    if (type === 'transporter' || type === 'unknown') {
        return [];
    }

    return props.employees.filter((employee) => {
        const roleNames = (employee.roles ?? []).map((role: any) => role.name);

        if (type === 'warehouse_staff') {
            return (
                roleNames.includes('warehouse_staff') &&
                employee.warehouse_staff_status !== 'inactive'
            );
        }

        return (
            roleNames.some((role: string) =>
                ['branch_staff', 'staff', 'manager'].includes(role),
            ) &&
            (!branchId || Number(employee.branch_id) === branchId)
        );
    });
});

const selectedEmployeeObj = computed(() => {
    if (!resolutionForm.value.responsible_user_id) {
return null;
}

    return props.employees.find((e) => Number(e.id) === Number(resolutionForm.value.responsible_user_id)) || null;
});

const filteredDisputes = computed(() => {
    let list = props.recentDisputes || [];

    // Filter by status
    if (statusFilter.value === 'pending') {
        list = list.filter((d) => d.status === 'open' || d.status === 'investigating');
    } else if (statusFilter.value === 'appealed') {
        list = list.filter((d) => d.status === 'appealed');
    } else if (statusFilter.value === 'penalized') {
        list = list.filter((d) => d.status === 'penalized');
    } else if (statusFilter.value === 'resolved') {
        list = list.filter((d) => d.status === 'resolved');
    }

    // Filter by search query
    const q = searchQuery.value.trim().toLowerCase();

    if (q) {
        list = list.filter((d) => {
            const code = (d.dispute_code || '').toLowerCase();
            const ingredient = (d.ingredient?.name || '').toLowerCase();
            const branch = (d.supply_request?.to_branch?.name || '').toLowerCase();
            const reqCode = (d.supply_request?.request_code || '').toLowerCase();
            const notes = (d.resolution_notes || '').toLowerCase();

            return code.includes(q) || ingredient.includes(q) || branch.includes(q) || reqCode.includes(q) || notes.includes(q);
        });
    }

    return list;
});

const canResolveDispute = (status: string) =>
    ['open', 'investigating', 'appealed'].includes(status);

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'appealed':
            return 'Đang khiếu nại';
        case 'penalized':
            return 'Đã khấu trừ lương';
        case 'resolved':
            return 'Đã giải quyết xong';
        case 'investigating':
        case 'open':
            return 'Chờ xử lý';
        default:
            return status;
    }
};

// Auto update penalty amount based on mode
watch(
    () => resolutionForm.value.penalty_mode,
    (mode) => {
        if (!selectedDispute.value) {
return;
}

        const loss = Number(selectedDispute.value.financial_loss_amount || 0);

        if (mode === 'full') {
            resolutionForm.value.penalty_amount = loss;
        } else if (mode === 'waived') {
            resolutionForm.value.penalty_amount = 0;
        } else if (mode === 'partial') {
            if (resolutionForm.value.penalty_amount <= 0 || resolutionForm.value.penalty_amount >= loss) {
                resolutionForm.value.penalty_amount = Math.round(loss * 0.5);
            }
        }
    },
);

watch(
    () => resolutionForm.value.responsible_type,
    () => {
        if (
            !eligibleEmployees.value.some(
                (employee) =>
                    employee.id === resolutionForm.value.responsible_user_id,
            )
        ) {
            resolutionForm.value.responsible_user_id = null;
        }
    },
);

const openResolveModal = (dispute: any) => {
    selectedDispute.value = dispute;
    const loss = Number(dispute.financial_loss_amount || 0);

    let defaultType = 'warehouse_staff';

    if (dispute.responsible_type && dispute.responsible_type !== 'unassigned') {
        defaultType = dispute.responsible_type;
    }

    resolutionForm.value = {
        responsible_type: defaultType,
        responsible_user_id: dispute.responsible_user_id || null,
        penalty_mode: 'full',
        penalty_amount: loss,
        write_off_inventory: !dispute.write_off_transaction_id,
        claim_status: dispute.claim_status || 'pending_collection',
        resolution_notes: '',
    };
    isResolveModalOpen.value = true;
};

const proceedToVoucherConfirmation = () => {
    if (!selectedDispute.value) {
return;
}

    if (
        (resolutionForm.value.responsible_type === 'warehouse_staff' ||
            resolutionForm.value.responsible_type === 'branch_staff') &&
        !resolutionForm.value.responsible_user_id
    ) {
        toast.error('Vui lòng chọn nhân viên chịu phạt lương trước khi lập phiếu.');

        return;
    }

    if (!resolutionForm.value.resolution_notes.trim()) {
        toast.error('Vui lòng nhập Kết luận điều tra & Lý do phạt.');

        return;
    }

    const loss = Number(selectedDispute.value.financial_loss_amount || 0);
    const penalty = Number(resolutionForm.value.penalty_amount || 0);

    if (penalty < 0 || penalty > loss) {
        toast.error(`Số tiền bồi thường phải từ 0 đến ${formatCurrency(loss)}.`);

        return;
    }

    isResolveModalOpen.value = false;
    isDocumentPreviewModalOpen.value = true;
};

const backToEditResolution = () => {
    isDocumentPreviewModalOpen.value = false;
    isResolveModalOpen.value = true;
};

const openExistingVoucherPreview = (disp: any) => {
    selectedDispute.value = disp;
    const loss = Number(disp.financial_loss_amount || 0);
    const penalty = Number(disp.penalty_amount ?? (disp.status === 'penalized' ? loss : 0));

    resolutionForm.value = {
        responsible_type: disp.responsible_type || 'warehouse_staff',
        responsible_user_id: disp.responsible_user_id || null,
        penalty_mode: penalty === loss ? 'full' : (penalty === 0 ? 'waived' : 'partial'),
        penalty_amount: penalty,
        write_off_inventory: !!disp.write_off_transaction_id,
        claim_status: disp.claim_status || 'pending_collection',
        resolution_notes: disp.resolution_notes || '',
    };
    isDocumentPreviewModalOpen.value = true;
};

const printDocument = () => {
    window.print();
};

const openEvidenceInspector = (dispute: any) => {
    const report = dispute.supply_request?.receiving_report;
    const req = dispute.supply_request;

    activeEvidence.value = {
        title: `Bằng chứng biên bản ${dispute.dispute_code}`,
        code: dispute.dispute_code,
        photoUrl: formatMediaUrl(report?.receipt_photo_path || req?.receipt_photo_path),
        signatureUrl: formatMediaUrl(report?.receiver_signature_path || req?.receiver_signature_path),
        temperatureMin: report?.temperature_min_c ?? null,
        temperatureMax: report?.temperature_max_c ?? null,
        driverNotes: report?.driver_confirmation_notes || req?.received_notes || null,
        receiverNotes: report?.notes || dispute.dispute_reason || null,
        confirmedAt: report?.confirmed_at || report?.created_at || req?.received_at || null,
        branchName: req?.to_branch?.name || null,
        driverName: req?.transporter?.name || null,
    };
    isEvidenceModalOpen.value = true;
};

const openReportEvidence = (report: any) => {
    const req = report.supply_request;
    activeEvidence.value = {
        title: `Bằng chứng biên bản nhận hàng ${report.report_code}`,
        code: report.report_code,
        photoUrl: formatMediaUrl(report.receipt_photo_path || req?.receipt_photo_path),
        signatureUrl: formatMediaUrl(report.receiver_signature_path || req?.receiver_signature_path),
        temperatureMin: report.temperature_min_c ?? null,
        temperatureMax: report.temperature_max_c ?? null,
        driverNotes: report.driver_confirmation_notes || null,
        receiverNotes: report.notes || null,
        confirmedAt: report.confirmed_at || report.created_at || null,
        branchName: req?.to_branch?.name || null,
        driverName: req?.transporter?.name || null,
    };
    isEvidenceModalOpen.value = true;
};

const saveRules = async () => {
    isProcessing.value = true;

    try {
        const res = await axios.post(
            '/api/warehouse-governance/rules',
            rulesForm.value,
        );

        if (res.data.success) {
            toast.success(
                'Đã lưu Cấu hình Bộ Quy Tắc Siết Chặt Quản Lý Kho thành công!',
            );
            router.reload();
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Không thể lưu cấu hình quy tắc.',
        );
    } finally {
        isProcessing.value = false;
    }
};

const submitResolution = async () => {
    if (!selectedDispute.value) {
        return;
    }

    if (!resolutionForm.value.resolution_notes.trim()) {
        toast.error('Vui lòng nhập Kết luận điều tra & Biện pháp xử lý.');

        return;
    }

    const loss = Number(selectedDispute.value.financial_loss_amount || 0);
    const penalty = Number(resolutionForm.value.penalty_amount || 0);

    if (penalty < 0 || penalty > loss) {
        toast.error(`Số tiền bồi thường phải từ 0 đến ${formatCurrency(loss)}.`);

        return;
    }

    isProcessing.value = true;

    try {
        const payload = {
            responsible_type: resolutionForm.value.responsible_type,
            responsible_user_id: resolutionForm.value.responsible_user_id,
            resolution_notes: resolutionForm.value.resolution_notes.trim(),
            penalty_amount: penalty,
            write_off_inventory: resolutionForm.value.write_off_inventory,
            claim_status: resolutionForm.value.responsible_type === 'transporter' ? resolutionForm.value.claim_status : null,
        };

        const res = await axios.post(
            `/api/warehouse-governance/disputes/${selectedDispute.value.id}/resolve`,
            payload,
        );

        if (res.data.success) {
            toast.success(
                'Đã ký duyệt & ban hành quyết định quy trách nhiệm bồi thường thành công!',
            );
            isDocumentPreviewModalOpen.value = false;
            isResolveModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Có lỗi xảy ra khi xử lý biên bản.',
        );
    } finally {
        isProcessing.value = false;
    }
};

const openReviewReportModal = (report: any) => {
    selectedReportForReview.value = report;
    reviewReportForm.value = {
        action_type: 'quarantine_destroy',
        notes: '',
    };
    isReviewReportModalOpen.value = true;
};

const submitReviewReport = async () => {
    if (!selectedReportForReview.value) {
return;
}

    if (!reviewReportForm.value.notes.trim()) {
        toast.error('Vui lòng nhập nội dung chi tiết kết luận xử lý.');

        return;
    }

    const report = selectedReportForReview.value;
    const actionLabel = {
        quarantine_destroy: 'Tiêu hủy hàng lỗi tại khu cách ly',
        return_supplier: 'Hoàn trả lại nhà cung cấp / đối tác',
        dispute_penalty: 'Lập hồ sơ yêu cầu đền bù thiệt hại',
        other: 'Phương án xử lý nội bộ khác',
    }[reviewReportForm.value.action_type] || 'Xử lý';

    const formattedNotes = `[${actionLabel}]: ${reviewReportForm.value.notes.trim()}`;

    isProcessing.value = true;

    try {
        const { data } = await axios.post(
            `/api/receiving-reports/${report.id}/review`,
            { notes: formattedNotes },
        );
        toast.success(data.message || 'Đã ghi nhận kết luận xử lý biên bản nhận hàng.');
        receivingReportList.value = receivingReportList.value.map((item) =>
            item.id === report.id
                ? { ...item, status: 'resolved', review_notes: formattedNotes, reviewed_at: new Date().toISOString() }
                : item,
        );
        isReviewReportModalOpen.value = false;
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể xử lý biên bản nhận hàng.');
    } finally {
        isProcessing.value = false;
    }
};

const exportCsv = () => {
    window.location.href = '/inventory/warehouse-governance/export';
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount || 0);
};

const getResponsibleLabel = (type: string) => {
    switch (type) {
        case 'warehouse_staff':
            return 'Nhân viên xuất Kho Tổng';
        case 'transporter':
            return 'Đơn vị Vận chuyển / Tài xế';
        case 'branch_staff':
            return 'Nhân viên nhận Kho Chi nhánh';
        case 'unassigned':
            return 'Chưa phân công';
        default:
            return 'Chưa xác định';
    }
};

const hasEvidence = (disp: any) => {
    const report = disp.supply_request?.receiving_report;
    const req = disp.supply_request;

    return !!(
        report?.receipt_photo_path ||
        report?.receiver_signature_path ||
        report?.temperature_min_c !== null ||
        report?.driver_confirmation_notes ||
        req?.receipt_photo_path ||
        req?.receiver_signature_path
    );
};

// Document Computed Values for A4 Voucher
const documentDateFormatted = computed(() => {
    const d = new Date();

    return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
});
const documentMonthYear = computed(() => {
    const d = new Date();

    return `${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
});
const documentHeaderDate = computed(() => {
    const d = new Date();

    return `Hà Nội, ngày ${String(d.getDate()).padStart(2, '0')} tháng ${String(d.getMonth() + 1).padStart(2, '0')} năm ${d.getFullYear()}`;
});

const docViolatorName = computed(() => {
    if (selectedEmployeeObj.value) {
return selectedEmployeeObj.value.name;
}

    if (selectedDispute.value?.responsible_user) {
return selectedDispute.value.responsible_user.name;
}

    if (resolutionForm.value.responsible_type === 'transporter') {
        return selectedDispute.value?.supply_request?.transporter?.name || 'Đơn vị Vận chuyển / Tài xế';
    }

    return 'Chưa xác định cá nhân (Hao hụt hệ thống)';
});

const docViolatorDept = computed(() => {
    if (resolutionForm.value.responsible_type === 'warehouse_staff') {
return 'Kho Tổng';
}

    if (resolutionForm.value.responsible_type === 'branch_staff') {
        return selectedDispute.value?.supply_request?.to_branch?.name || 'Kho Chi nhánh';
    }

    if (resolutionForm.value.responsible_type === 'transporter') {
return 'Đối tác Vận chuyển';
}

    return 'Toàn chuỗi';
});

const docViolatorRole = computed(() => {
    if (resolutionForm.value.responsible_type === 'warehouse_staff') {
return 'Nhân viên kho';
}

    if (resolutionForm.value.responsible_type === 'branch_staff') {
return 'Nhân viên kho chi nhánh';
}

    if (resolutionForm.value.responsible_type === 'transporter') {
return 'Tài xế giao nhận';
}

    return 'Hao hụt rủi ro';
});

const docLossAmount = computed(() => Number(selectedDispute.value?.financial_loss_amount || 0));
const docPenaltyAmount = computed(() => Number(resolutionForm.value.penalty_amount || 0));
const docWaivedAmount = computed(() => Math.max(0, docLossAmount.value - docPenaltyAmount.value));
const docPenaltyPercent = computed(() => {
    if (docLossAmount.value <= 0) {
return 0;
}

    return Math.round((docPenaltyAmount.value / docLossAmount.value) * 100);
});
</script>

<template>
    <Head title="Quản Trị Siết Chặt Kho & Quy Trách Nhiệm" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-rose-200/80 bg-gradient-to-r from-rose-50/90 via-slate-50 to-amber-50/60 p-4 text-slate-900 shadow-xs backdrop-blur-md sm:p-5 md:flex-row md:items-center md:justify-between dark:border-slate-800 dark:bg-black/80 dark:from-[#100606] dark:via-black dark:to-[#100606] dark:text-white"
        >
            <div class="flex items-center gap-3.5">
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-rose-600 text-white shadow-md shadow-rose-600/20 backdrop-blur-md dark:border dark:border-rose-500/30 dark:bg-rose-600/25 dark:text-rose-300"
                >
                    <ShieldAlert class="size-6" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1
                            class="text-lg font-black tracking-tight text-slate-900 md:text-xl lg:text-2xl dark:text-white"
                        >
                            Quản Trị Siết Chặt Kho & Quy Trách Nhiệm
                        </h1>
                        <span
                            class="hidden rounded-full border border-rose-300 bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700 sm:inline-block dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300"
                        >
                            Governance V2
                        </span>
                    </div>
                    <p
                        class="mt-0.5 text-xs leading-normal text-slate-600 dark:text-slate-400"
                    >
                        Bộ quy tắc siết chặt tài chính, soi bằng chứng đối soát, bồi thường linh hoạt & hạch toán sổ cái
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    @click="exportCsv"
                    variant="outline"
                    size="sm"
                    class="h-9 gap-1.5 border-border bg-background/80 text-xs font-semibold shadow-xs hover:bg-muted"
                >
                    <Download class="size-3.5" /> Xuất Báo Cáo CSV
                </Button>
                <span
                    class="flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-100/80 px-3 py-1 text-[10px] font-extrabold text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300"
                >
                    <Lock class="size-3.5" /> Phân Hệ Trưởng Kho
                </span>
            </div>
        </div>

        <!-- Metrics Overview -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Card class="border-rose-500/30 bg-rose-950/10 shadow-sm">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-semibold text-rose-500 dark:text-rose-300">
                            Biên Bản Chờ Xử Lý
                        </p>
                        <p class="mt-1 text-2xl font-black text-rose-600 dark:text-rose-100">
                            {{ summary.open_disputes_count }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            Cần thẩm định & kết luận
                        </p>
                    </div>
                    <div class="rounded-xl bg-rose-500/10 p-2.5 text-rose-500 dark:bg-rose-950/50 dark:text-rose-300">
                        <AlertTriangle class="size-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-amber-500/30 bg-amber-950/10 shadow-sm">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-semibold text-amber-600 dark:text-amber-300">
                            Thiệt Hại Bất Đồng
                        </p>
                        <p class="mt-1 text-xl font-black text-amber-700 dark:text-amber-200">
                            {{ formatCurrency(summary.total_discrepancy_loss) }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            Tổng tiền hàng thiếu/hỏng
                        </p>
                    </div>
                    <div class="rounded-xl bg-amber-500/10 p-2.5 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300">
                        <DollarSign class="size-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-emerald-500/30 bg-emerald-950/10 shadow-sm">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-300">
                            Đã Phạt Lương / Thu Hồi
                        </p>
                        <p class="mt-1 text-xl font-black text-emerald-700 dark:text-emerald-200">
                            {{ formatCurrency(summary.total_penalized_amount || 0) }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            Đã khấu trừ lương nhân sự
                        </p>
                    </div>
                    <div class="rounded-xl bg-emerald-500/10 p-2.5 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                        <CheckCheck class="size-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-blue-500/30 bg-blue-950/10 shadow-sm">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-semibold text-blue-600 dark:text-blue-300">
                            Công Ty Miễn / Hỗ Trợ
                        </p>
                        <p class="mt-1 text-xl font-black text-blue-700 dark:text-blue-200">
                            {{ formatCurrency(summary.total_waived_amount || 0) }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            Miễn trừ rủi ro bất khả kháng
                        </p>
                    </div>
                    <div class="rounded-xl bg-blue-500/10 p-2.5 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                        <RotateCcw class="size-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-indigo-500/30 bg-indigo-950/10 shadow-sm">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-semibold text-indigo-600 dark:text-indigo-300">
                            Tổng Thất Thoát
                        </p>
                        <p class="mt-1 text-xl font-black text-indigo-700 dark:text-indigo-200">
                            {{ formatCurrency(summary.total_combined_loss) }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            Giao nhận + Hủy kho
                        </p>
                    </div>
                    <div class="rounded-xl bg-indigo-500/10 p-2.5 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                        <Gavel class="size-5" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <WarehouseAiRecommendations context="receiving" :max="3" />

        <!-- Navigation Tabs -->
        <Card class="border-border">
            <CardContent class="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex gap-2">
                    <button
                        @click="activeTab = 'disputes'"
                        :class="[
                            'flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition',
                            activeTab === 'disputes'
                                ? 'bg-indigo-600 text-white shadow'
                                : 'text-muted-foreground hover:bg-muted',
                        ]"
                    >
                        <AlertTriangle class="size-4 text-rose-400" /> Biên Bản Bất Đồng Giao Nhận ({{ props.recentDisputes.length }})
                    </button>
                    <button
                        @click="activeTab = 'rules'"
                        :class="[
                            'flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition',
                            activeTab === 'rules'
                                ? 'bg-indigo-600 text-white shadow'
                                : 'text-muted-foreground hover:bg-muted',
                        ]"
                    >
                        <ShieldCheck class="size-4 text-indigo-400" /> Bộ Quy Tắc & Hạn Mức Kiểm Soát
                    </button>
                </div>
            </CardContent>
        </Card>

        <!-- Receiving Reports Review Section -->
        <Card v-if="receivingReportList.length" class="border-amber-500/30 bg-amber-950/10 shadow-sm">
            <CardHeader class="border-b border-amber-500/20 bg-amber-950/20 py-4">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle class="text-base font-bold text-amber-200">
                            Biên Bản Nhận Hàng Kho Tổng Chờ Xử Lý ({{ receivingReportList.filter(r => r.status !== 'resolved').length }})
                        </CardTitle>
                        <CardDescription class="text-xs text-amber-100/70">
                            Xem bằng chứng ảnh nhận hàng, đối soát tài xế & đưa ra kết luận xử lý hàng lỗi cách ly
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-3 p-4">
                <div
                    v-for="report in receivingReportList"
                    :key="report.id"
                    class="rounded-xl border border-amber-500/20 bg-background/70 p-4 transition hover:border-amber-500/40"
                >
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-1.5 text-xs">
                            <div class="flex flex-wrap items-center gap-2 font-bold text-foreground">
                                <span class="font-mono text-amber-400">{{ report.report_code }}</span>
                                <span>·</span>
                                <span>Đơn {{ report.supply_request?.request_code }}</span>
                                <span>·</span>
                                <span class="text-indigo-400">{{ report.supply_request?.to_branch?.name || 'Chi nhánh nhận' }}</span>
                            </div>
                            <div class="text-muted-foreground">
                                Tài xế: <span class="font-semibold text-foreground">{{ report.supply_request?.transporter?.name || '---' }}</span>
                                <span v-if="report.temperature_min_c !== null" class="ml-2 inline-flex items-center gap-1 font-semibold text-sky-400">
                                    <Thermometer class="size-3" /> {{ report.temperature_min_c }}°C - {{ report.temperature_max_c }}°C
                                </span>
                                <span class="ml-2">· Trạng thái:</span>
                                <span class="ml-1 font-bold text-amber-400">{{ report.status }}</span>
                            </div>
                            <div class="mt-2 grid gap-1.5 text-muted-foreground sm:grid-cols-2">
                                <div
                                    v-for="item in (report.items || []).filter((row: any) => Number(row.submitted_damaged_quantity || 0) + Number(row.submitted_expired_quantity || 0) + Number(row.submitted_wrong_item_quantity || 0) + Number(row.submitted_shortage_quantity || 0) > 0)"
                                    :key="item.id"
                                    class="rounded-md bg-muted/40 px-2 py-1"
                                >
                                    <span class="font-bold text-foreground">{{ item.ingredient?.name || item.ingredient_name_snapshot }}</span>:
                                    đạt <span class="text-emerald-400 font-semibold">{{ item.submitted_good_quantity }}</span>,
                                    hỏng/lỗi <span class="text-rose-400 font-semibold">{{ Number(item.submitted_damaged_quantity || 0) + Number(item.submitted_expired_quantity || 0) + Number(item.submitted_wrong_item_quantity || 0) }}</span>,
                                    thiếu <span class="text-amber-400 font-semibold">{{ item.submitted_shortage_quantity }}</span>
                                </div>
                            </div>
                            <div v-if="report.driver_confirmation_notes" class="rounded-md bg-indigo-950/30 p-2 text-indigo-300">
                                <strong>Ghi chú tài xế:</strong> {{ report.driver_confirmation_notes }}
                            </div>
                            <div v-if="report.review_notes" class="rounded-md bg-emerald-950/30 p-2 text-emerald-300">
                                <strong>Kết luận Trưởng Kho:</strong> {{ report.review_notes }}
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <!-- Nút xem bằng chứng ảnh & chữ ký -->
                            <Button
                                v-if="report.receipt_photo_path || report.receiver_signature_path || report.temperature_min_c !== null"
                                size="sm"
                                variant="outline"
                                class="h-8 gap-1 border-amber-500/30 text-xs font-semibold text-amber-300 hover:bg-amber-500/10"
                                @click="openReportEvidence(report)"
                            >
                                <Eye class="size-3.5" /> Soi Bằng Chứng
                            </Button>

                            <!-- Nút ghi nhận xử lý mở Modal chuyên nghiệp -->
                            <Button
                                v-if="report.status !== 'resolved'"
                                size="sm"
                                class="h-8 gap-1.5 bg-amber-600 text-xs font-bold text-white hover:bg-amber-700"
                                @click="openReviewReportModal(report)"
                            >
                                <UserCheck class="size-3.5" /> Ghi Nhận Xử Lý
                            </Button>
                            <span v-else class="flex items-center gap-1 text-xs font-bold text-emerald-400">
                                <CheckCircle class="size-3.5" /> Đã xử lý
                            </span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Tab 1: Disputes List with Filters -->
        <Card v-if="activeTab === 'disputes'" class="border-border shadow-sm">
            <CardHeader class="border-b border-border bg-muted/20 py-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <CardTitle class="text-base font-bold text-foreground">
                            Danh Sách Biên Bản Bất Đồng & Sai Lệch Giao Nhận
                        </CardTitle>
                        <CardDescription class="text-xs">
                            Truy vết trách nhiệm giữa Kho Tổng, Đơn vị vận chuyển và Chi nhánh với bằng chứng hình ảnh minh bạch
                        </CardDescription>
                    </div>

                    <!-- Filter Tabs & Search Bar -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative w-64">
                            <Search class="absolute left-2.5 top-2.5 size-3.5 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Tìm mã, món, chi nhánh..."
                                class="h-8 pl-8 text-xs"
                            />
                        </div>

                        <div class="flex rounded-lg border border-border bg-background p-0.5 text-xs font-semibold">
                            <button
                                @click="statusFilter = 'all'"
                                :class="['rounded-md px-2.5 py-1 transition', statusFilter === 'all' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground']"
                            >
                                Tất cả ({{ props.recentDisputes.length }})
                            </button>
                            <button
                                @click="statusFilter = 'pending'"
                                :class="['rounded-md px-2.5 py-1 transition', statusFilter === 'pending' ? 'bg-rose-600 text-white' : 'text-muted-foreground hover:text-foreground']"
                            >
                                Chờ xử lý
                            </button>
                            <button
                                @click="statusFilter = 'appealed'"
                                :class="['rounded-md px-2.5 py-1 transition', statusFilter === 'appealed' ? 'bg-amber-600 text-white' : 'text-muted-foreground hover:text-foreground']"
                            >
                                Khiếu nại
                            </button>
                            <button
                                @click="statusFilter = 'penalized'"
                                :class="['rounded-md px-2.5 py-1 transition', statusFilter === 'penalized' ? 'bg-emerald-600 text-white' : 'text-muted-foreground hover:text-foreground']"
                            >
                                Đã phạt
                            </button>
                            <button
                                @click="statusFilter = 'resolved'"
                                :class="['rounded-md px-2.5 py-1 transition', statusFilter === 'resolved' ? 'bg-slate-700 text-white' : 'text-muted-foreground hover:text-foreground']"
                            >
                                Đã chốt
                            </button>
                        </div>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="border-b border-border bg-muted/50 font-semibold text-muted-foreground"
                        >
                            <tr>
                                <th class="p-3 pl-4">Mã Biên Bản</th>
                                <th class="p-3">Nguyên Liệu</th>
                                <th class="p-3 text-right">Xuất Kho</th>
                                <th class="p-3 text-right">Thực Nhận</th>
                                <th class="p-3 text-right">Chênh Lệch</th>
                                <th class="p-3 text-right">Thiệt Hại (VNĐ)</th>
                                <th class="p-3">Bằng Chứng</th>
                                <th class="p-3">Bồi Thường & Trách Nhiệm</th>
                                <th class="p-3">Trạng Thái</th>
                                <th class="p-3 pr-4 text-right">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="filteredDisputes.length === 0">
                                <td
                                    colspan="10"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    Không có biên bản bất đồng nào khớp với tiêu chí tìm kiếm.
                                </td>
                            </tr>
                            <tr
                                v-for="disp in filteredDisputes"
                                :key="disp.id"
                                class="transition hover:bg-muted/30"
                            >
                                <td class="p-3 pl-4">
                                    <div class="font-mono font-bold text-rose-600 dark:text-rose-400">
                                        {{ disp.dispute_code }}
                                    </div>
                                    <div class="text-[11px] text-muted-foreground">
                                        {{ disp.supply_request?.request_code || '---' }}
                                        <span v-if="disp.supply_request?.to_branch">
                                            · {{ disp.supply_request.to_branch.name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <div class="font-bold text-foreground">
                                        {{ disp.ingredient?.name }}
                                    </div>
                                    <div class="text-[11px] text-muted-foreground">
                                        ĐVT: {{ disp.ingredient?.unit?.symbol || 'Đơn vị' }}
                                    </div>
                                </td>
                                <td class="p-3 text-right font-medium text-muted-foreground">
                                    {{ disp.dispatched_quantity }}
                                </td>
                                <td class="p-3 text-right font-medium text-muted-foreground">
                                    {{ disp.received_quantity }}
                                </td>
                                <td class="p-3 text-right font-bold text-rose-600">
                                    -{{ disp.discrepancy_quantity }}
                                </td>
                                <td class="p-3 text-right font-bold text-amber-600 dark:text-amber-400">
                                    {{ formatCurrency(disp.financial_loss_amount) }}
                                </td>

                                <!-- Cột Bằng Chứng Trực Quan -->
                                <td class="p-3">
                                    <Button
                                        v-if="hasEvidence(disp)"
                                        size="sm"
                                        variant="outline"
                                        class="h-7 gap-1 border-indigo-500/30 px-2 text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-500/10"
                                        @click="openEvidenceInspector(disp)"
                                    >
                                        <Eye class="size-3" /> Soi bằng chứng
                                    </Button>
                                    <span v-else class="text-[11px] text-muted-foreground italic">
                                        Chưa có ảnh
                                    </span>
                                </td>

                                <!-- Cột Bồi Thường & Trách Nhiệm -->
                                <td class="p-3">
                                    <div class="font-medium text-foreground">
                                        {{
                                            disp.responsible_user
                                                ? disp.responsible_user.name
                                                : getResponsibleLabel(disp.responsible_type)
                                        }}
                                    </div>
                                    <div v-if="disp.penalty_amount !== null && disp.status !== 'open'" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold">
                                        Phạt: {{ formatCurrency(disp.penalty_amount) }}
                                        <span v-if="Number(disp.waived_amount || 0) > 0" class="text-muted-foreground">
                                            (Miễn: {{ formatCurrency(disp.waived_amount) }})
                                        </span>
                                    </div>
                                    <div v-if="disp.write_off_transaction_id" class="text-[10px] text-sky-500 font-medium">
                                        ✓ Đã xuất sổ hao hụt
                                    </div>
                                </td>

                                <!-- Trạng Thái -->
                                <td class="p-3">
                                    <span
                                        v-if="disp.status === 'open' || disp.status === 'investigating'"
                                        class="inline-flex rounded-full border border-rose-300 bg-rose-100 px-2.5 py-0.5 text-[11px] font-semibold text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300"
                                    >
                                        Chờ Xử Lý
                                    </span>
                                    <span
                                        v-else-if="disp.status === 'appealed'"
                                        class="inline-flex rounded-full border border-amber-300 bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300"
                                    >
                                        Đang Khiếu Nại
                                    </span>
                                    <span
                                        v-else-if="disp.status === 'penalized'"
                                        class="inline-flex rounded-full border border-emerald-300 bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300"
                                    >
                                        Đã Phạt Lương
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex rounded-full border border-slate-300 bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-800 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                    >
                                        {{ getStatusLabel(disp.status) }}
                                    </span>
                                </td>

                                <!-- Hành Động -->
                                <td class="p-3 pr-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Nút Thu Tiền ĐVVC cho các biên bản quy trách nhiệm vận chuyển -->
                                        <Button
                                            v-if="disp.responsible_type === 'transporter' && (disp.claim_status === 'pending_collection' || (!disp.claim_status && Number(disp.penalty_amount) > 0))"
                                            @click="openClaimCollectionModal(disp)"
                                            size="sm"
                                            class="h-8 gap-1 text-[11px] font-bold bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm"
                                        >
                                            <DollarSign class="size-3.5" /> Thu ĐVVC
                                        </Button>
                                        <span
                                            v-else-if="disp.responsible_type === 'transporter' && disp.claim_status === 'collected'"
                                            class="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300"
                                        >
                                            <CheckCircle class="size-3" /> Đã thu ĐVVC
                                        </span>

                                        <!-- Nút Xem Phiếu cho các biên bản đã chốt/phạt -->
                                        <Button
                                            v-if="disp.status === 'penalized' || disp.status === 'resolved'"
                                            @click="openExistingVoucherPreview(disp)"
                                            size="sm"
                                            variant="ghost"
                                            class="h-8 gap-1 text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-500/10"
                                        >
                                            <FileText class="size-3.5" /> Xem Phiếu
                                        </Button>

                                        <Button
                                            v-if="canResolveDispute(disp.status)"
                                            @click="openResolveModal(disp)"
                                            size="sm"
                                            variant="outline"
                                            class="h-8 gap-1.5 border-rose-500/30 text-xs font-semibold text-rose-600 dark:text-rose-300 hover:bg-rose-500/10"
                                        >
                                            <Gavel class="size-3.5" /> Quy Trách Nhiệm
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Tab 2: Rules Governance Config -->
        <Card
            v-if="activeTab === 'rules'"
            class="mx-auto max-w-4xl border-border shadow-sm"
        >
            <CardHeader class="border-b border-border bg-muted/20 py-4">
                <CardTitle class="text-base font-bold text-foreground">
                    Thiết Lập Bộ Quy Tắc & Hạn Mức Kiểm Soát Hàng Hóa
                </CardTitle>
                <CardDescription class="text-xs">
                    Cấu hình các rào cản tự động ngăn chặn thất thoát tài chính và gian lận trong kho
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6 p-6 text-xs">
                <!-- Rule 1: Threshold Amount -->
                <div class="space-y-2 rounded-xl border border-border bg-muted/20 p-4">
                    <label class="block text-sm font-bold text-foreground">
                        1. Hạn Mức Tiền Chênh Lệch Tối Đa Tự Chốt (VNĐ)
                    </label>
                    <p class="text-muted-foreground">
                        Phiếu kiểm kê hoặc hủy hàng có giá trị chênh lệch vượt ngưỡng này sẽ bị KHÓA TỰ ĐỘNG và bắt buộc Trưởng Kho Tổng duyệt.
                    </p>
                    <Input
                        v-model.number="rulesForm.max_auto_approve_variance_amount"
                        type="number"
                        step="50000"
                        class="max-w-xs text-xs font-bold text-indigo-400"
                    />
                </div>

                <!-- Rule 2: Threshold Percent -->
                <div class="space-y-2 rounded-xl border border-border bg-muted/20 p-4">
                    <label class="block text-sm font-bold text-foreground">
                        2. Tỷ Lệ % Sai Lệch Tối Đa Cho Phép (%)
                    </label>
                    <p class="text-muted-foreground">
                        Tỷ lệ sai lệch giữa Tồn thực tế vs Tồn lý thuyết vượt quá phần trăm này sẽ được cảnh báo rủi ro cao.
                    </p>
                    <Input
                        v-model.number="rulesForm.max_auto_approve_variance_percent"
                        type="number"
                        step="0.5"
                        class="max-w-xs text-xs font-bold text-indigo-400"
                    />
                </div>

                <!-- Rule 3: Seal Code Required -->
                <div class="flex items-center justify-between gap-4 rounded-xl border border-border bg-muted/20 p-4">
                    <div>
                        <label class="block text-sm font-bold text-foreground">
                            3. Bắt Buộc Mã Niêm Phong (Seal Code) Khi Xuất Kho
                        </label>
                        <p class="text-muted-foreground">
                            Yêu cầu Nhân viên Kho Tổng phải nhập Mã Seal niêm phong thùng hàng trước khi giao xe.
                        </p>
                    </div>
                    <input
                        type="checkbox"
                        v-model="rulesForm.require_seal_code_on_dispatch"
                        class="size-5 rounded accent-indigo-500"
                    />
                </div>

                <!-- Rule 4: Auto Dispute -->
                <div class="flex items-center justify-between gap-4 rounded-xl border border-border bg-muted/20 p-4">
                    <div>
                        <label class="block text-sm font-bold text-foreground">
                            4. Tự Động Khởi Tạo Biên Bản Bất Đồng Khi Chi Nhánh Nhận Thiếu
                        </label>
                        <p class="text-muted-foreground">
                            Hệ thống sẽ lập tức tạo Biên bản khiếu nại để điều tra khi số lượng thực nhận nhỏ hơn số lượng xuất.
                        </p>
                    </div>
                    <input
                        type="checkbox"
                        v-model="rulesForm.auto_dispute_on_discrepancy"
                        class="size-5 rounded accent-indigo-500"
                    />
                </div>

                <!-- Rule 5: Penalty Deduction -->
                <div class="flex items-center justify-between gap-4 rounded-xl border border-border bg-muted/20 p-4">
                    <div>
                        <label class="block text-sm font-bold text-foreground">
                            5. Cho Phép Phân Bổ Thiệt Hại Bồi Thường Vào Bảng Lương
                        </label>
                        <p class="text-muted-foreground">
                            Cho phép Trưởng Kho chỉ định Nhân viên chịu trách nhiệm và đẩy khoản tiền phạt bồi thường sang Phân hệ Lương.
                        </p>
                    </div>
                    <input
                        type="checkbox"
                        v-model="rulesForm.penalty_deduction_enabled"
                        class="size-5 rounded accent-indigo-500"
                    />
                </div>

                <div class="flex justify-end pt-4">
                    <Button
                        @click="saveRules"
                        size="sm"
                        :disabled="isProcessing"
                        class="gap-2 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                    >
                        <Save class="size-4" /> Lưu Bộ Quy Tắc Kiểm Soát
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- MODAL 1: EVIDENCE LIGHTBOX INSPECTOR -->
        <Teleport to="body">
            <div
                v-if="isEvidenceModalOpen && activeEvidence"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-md"
            >
                <div class="flex w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between border-b border-border bg-slate-950 p-5 text-white">
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 items-center justify-center rounded-lg bg-indigo-600 text-white">
                                <Eye class="size-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold">
                                    {{ activeEvidence.title }}
                                </h3>
                                <p class="text-xs text-slate-300">
                                    Chi nhánh: <span class="font-semibold text-indigo-300">{{ activeEvidence.branchName || 'Kho Chi nhánh' }}</span>
                                    <span v-if="activeEvidence.driverName"> · Tài xế: {{ activeEvidence.driverName }}</span>
                                </p>
                            </div>
                        </div>
                        <button
                            @click="isEvidenceModalOpen = false"
                            class="rounded-lg p-1 text-slate-400 hover:text-white"
                        >
                            <X class="size-6" />
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="max-h-[75vh] space-y-5 overflow-y-auto p-6 text-xs">
                        <!-- Evidence Photos & Signatures Grid -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Delivery Receipt Photo -->
                            <div class="space-y-2 rounded-xl border border-border bg-muted/20 p-3">
                                <div class="flex items-center gap-2 font-bold text-foreground">
                                    <ImageIcon class="size-4 text-indigo-400" />
                                    <span>Ảnh Chụp Thực Tế Hàng Nhận / Hàng Lỗi</span>
                                </div>
                                <div v-if="activeEvidence.photoUrl" class="overflow-hidden rounded-lg border border-border bg-black/40">
                                    <img
                                        :src="activeEvidence.photoUrl"
                                        alt="Ảnh biên bản"
                                        class="max-h-64 w-full object-contain transition hover:scale-105"
                                    />
                                </div>
                                <div v-else class="flex h-40 items-center justify-center rounded-lg border border-dashed border-border bg-muted/30 text-muted-foreground">
                                    Không có ảnh đính kèm
                                </div>
                            </div>

                            <!-- Signature -->
                            <div class="space-y-2 rounded-xl border border-border bg-muted/20 p-3">
                                <div class="flex items-center gap-2 font-bold text-foreground">
                                    <PenTool class="size-4 text-emerald-400" />
                                    <span>Chữ Ký Xác Nhận Người Nhận</span>
                                </div>
                                <div v-if="activeEvidence.signatureUrl" class="overflow-hidden rounded-lg border border-border bg-white p-2">
                                    <img
                                        :src="activeEvidence.signatureUrl"
                                        alt="Chữ ký người nhận"
                                        class="max-h-64 w-full object-contain"
                                    />
                                </div>
                                <div v-else class="flex h-40 items-center justify-center rounded-lg border border-dashed border-border bg-muted/30 text-muted-foreground">
                                    Không có chữ ký điện tử
                                </div>
                            </div>
                        </div>

                        <!-- Truck Temperature Log -->
                        <div v-if="activeEvidence.temperatureMin !== null" class="flex items-center gap-3 rounded-xl border border-sky-500/30 bg-sky-950/20 p-3.5 text-sky-200">
                            <Thermometer class="size-6 text-sky-400" />
                            <div>
                                <div class="font-bold">Nhiệt Độ Thùng Xe Giao Nhận</div>
                                <div class="text-xs">
                                    Ghi nhận dải nhiệt:
                                    <span class="font-mono font-bold text-sky-300">{{ activeEvidence.temperatureMin }}°C</span> đến
                                    <span class="font-mono font-bold text-sky-300">{{ activeEvidence.temperatureMax }}°C</span>
                                    (Kiểm soát chuỗi lạnh Cold Chain)
                                </div>
                            </div>
                        </div>

                        <!-- Notes & Observations -->
                        <div class="space-y-3">
                            <div v-if="activeEvidence.driverNotes" class="rounded-xl border border-indigo-500/30 bg-indigo-950/20 p-3 text-indigo-200">
                                <strong>Ghi chú từ Tài xế / Đơn vị giao hàng:</strong>
                                <p class="mt-1 text-slate-300">{{ activeEvidence.driverNotes }}</p>
                            </div>

                            <div v-if="activeEvidence.receiverNotes" class="rounded-xl border border-amber-500/30 bg-amber-950/20 p-3 text-amber-200">
                                <strong>Ý kiến Chi nhánh / Lý do bất đồng:</strong>
                                <p class="mt-1 text-slate-300">{{ activeEvidence.receiverNotes }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex justify-end border-t border-border bg-muted/20 p-4">
                        <Button
                            @click="isEvidenceModalOpen = false"
                            size="sm"
                            variant="outline"
                            class="text-xs"
                        >
                            Đóng
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- MODAL 2: RESOLVE DISPUTE & SPLIT LIABILITY MODAL (BƯỚC 1) -->
        <Teleport to="body">
            <div
                v-if="isResolveModalOpen && selectedDispute"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-md"
            >
                <div class="flex w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl">
                    <div class="flex items-center justify-between border-b border-border bg-slate-950 p-5 text-white">
                        <div class="flex items-center gap-2.5">
                            <div class="flex size-9 items-center justify-center rounded-lg bg-rose-600 text-white">
                                <Gavel class="size-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold">
                                    Quy Trách Nhiệm & Phân Bổ Bồi Thường
                                </h3>
                                <p class="text-xs text-slate-300">
                                    Biên bản: <span class="font-mono font-bold text-rose-300">{{ selectedDispute.dispute_code }}</span>
                                </p>
                            </div>
                        </div>
                        <button
                            @click="isResolveModalOpen = false"
                            class="rounded-lg p-1 text-slate-400 hover:text-white"
                        >
                            <X class="size-6" />
                        </button>
                    </div>

                    <div class="max-h-[75vh] space-y-4 overflow-y-auto p-6 text-xs">
                        <!-- Loss Summary Box -->
                        <div class="space-y-1.5 rounded-xl border border-rose-900/50 bg-rose-950/20 p-3.5 text-rose-200">
                            <div class="flex justify-between">
                                <span><strong>Nguyên liệu:</strong> {{ selectedDispute.ingredient?.name }}</span>
                                <span class="font-mono font-semibold">Đơn: {{ selectedDispute.supply_request?.request_code }}</span>
                            </div>
                            <div>
                                <strong>Số lượng chênh lệch:</strong> Thiếu {{ selectedDispute.discrepancy_quantity }}
                                (Xuất {{ selectedDispute.dispatched_quantity }} - Nhận {{ selectedDispute.received_quantity }})
                            </div>
                            <div class="flex items-center justify-between border-t border-rose-900/40 pt-1.5">
                                <span>Tổng giá trị thiệt hại tài chính:</span>
                                <strong class="text-base font-black text-rose-300">
                                    {{ formatCurrency(selectedDispute.financial_loss_amount) }}
                                </strong>
                            </div>
                        </div>

                        <!-- 1. Bên chịu trách nhiệm -->
                        <div>
                            <label class="mb-1 block font-bold text-foreground">
                                1. Xác Định Bên Chịu Trách Nhiệm (*)
                            </label>
                            <select
                                v-model="resolutionForm.responsible_type"
                                class="w-full rounded-lg border border-input bg-background p-2 font-medium text-foreground focus:outline-none"
                            >
                                <option value="warehouse_staff">
                                    Nhân viên xuất Kho Tổng (Đóng gói sai / Xuất thiếu)
                                </option>
                                <option value="transporter">
                                    Đơn vị Vận chuyển / Tài xế (Làm rơi, đổ vỡ, hư hỏng khi chở)
                                </option>
                                <option value="branch_staff">
                                    Nhân viên nhận Kho Chi nhánh (Khai báo sai / Làm hỏng khi nhận)
                                </option>
                                <option value="unknown">
                                    Hao hụt rủi ro bất khả kháng (Công ty chịu hoàn toàn)
                                </option>
                            </select>
                        </div>

                        <!-- 2. Gán nhân sự nội bộ (nếu là staff) -->
                        <div v-if="resolutionForm.responsible_type === 'warehouse_staff' || resolutionForm.responsible_type === 'branch_staff'">
                            <label class="mb-1 block font-bold text-foreground">
                                2. Chọn Nhân Viên Chịu Phạt Lương (*)
                            </label>
                            <select
                                v-model="resolutionForm.responsible_user_id"
                                class="w-full rounded-lg border border-input bg-background p-2 font-medium text-foreground focus:outline-none"
                            >
                                <option :value="null">-- Chọn nhân sự để khấu trừ lương --</option>
                                <option
                                    v-for="emp in eligibleEmployees"
                                    :key="emp.id"
                                    :value="emp.id"
                                >
                                    {{ emp.name }} ({{ emp.email }})
                                </option>
                            </select>
                        </div>

                        <!-- 2b. Trạng thái đòi bên vận chuyển (nếu là transporter) -->
                        <div v-if="resolutionForm.responsible_type === 'transporter'">
                            <label class="mb-1 block font-bold text-foreground">
                                2. Trạng Thái Đòi Bồi Thường Đơn Vị Vận Chuyển
                            </label>
                            <select
                                v-model="resolutionForm.claim_status"
                                class="w-full rounded-lg border border-input bg-background p-2 font-medium text-foreground focus:outline-none"
                            >
                                <option value="pending_collection">Chờ thu hồi tiền bồi thường từ tài xế / nhà xe</option>
                                <option value="collected">Đã thu hồi đủ tiền mặt / cấn trừ công nợ</option>
                                <option value="waived">Miễn trừ bồi thường cho tài xế</option>
                            </select>
                        </div>

                        <!-- 3. Phương thức phân bổ thiệt hại -->
                        <div v-if="resolutionForm.responsible_type !== 'unknown'" class="space-y-3 rounded-xl border border-border bg-muted/20 p-3.5">
                            <label class="block font-bold text-foreground">
                                3. Phân Bổ Mức Bồi Thường
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    @click="resolutionForm.penalty_mode = 'full'"
                                    :class="[
                                        'rounded-lg border p-2 text-center transition font-semibold text-xs',
                                        resolutionForm.penalty_mode === 'full'
                                            ? 'border-rose-500 bg-rose-500/10 text-rose-400'
                                            : 'border-border text-muted-foreground hover:bg-muted',
                                    ]"
                                >
                                    100% Toàn Bộ
                                </button>
                                <button
                                    type="button"
                                    @click="resolutionForm.penalty_mode = 'partial'"
                                    :class="[
                                        'rounded-lg border p-2 text-center transition font-semibold text-xs',
                                        resolutionForm.penalty_mode === 'partial'
                                            ? 'border-amber-500 bg-amber-500/10 text-amber-400'
                                            : 'border-border text-muted-foreground hover:bg-muted',
                                    ]"
                                >
                                    Tùy Chỉnh / Một Phần
                                </button>
                                <button
                                    type="button"
                                    @click="resolutionForm.penalty_mode = 'waived'"
                                    :class="[
                                        'rounded-lg border p-2 text-center transition font-semibold text-xs',
                                        resolutionForm.penalty_mode === 'waived'
                                            ? 'border-emerald-500 bg-emerald-500/10 text-emerald-400'
                                            : 'border-border text-muted-foreground hover:bg-muted',
                                    ]"
                                >
                                    0% Miễn Trừ
                                </button>
                            </div>

                            <div v-if="resolutionForm.penalty_mode === 'partial'" class="pt-1">
                                <label class="mb-1 block font-medium text-foreground">Số tiền cá nhân phải bồi thường (VNĐ):</label>
                                <Input
                                    v-model.number="resolutionForm.penalty_amount"
                                    type="number"
                                    min="0"
                                    :max="selectedDispute.financial_loss_amount"
                                    class="text-xs font-bold text-rose-400"
                                />
                            </div>

                            <!-- Real-time split preview -->
                            <div class="flex items-center justify-between rounded-lg bg-background/80 p-2 text-[11px]">
                                <span>Phạt cá nhân: <strong class="text-rose-400">{{ formatCurrency(resolutionForm.penalty_amount) }}</strong></span>
                                <span>Công ty hỗ trợ/chịu: <strong class="text-blue-400">{{ formatCurrency(Math.max(0, selectedDispute.financial_loss_amount - resolutionForm.penalty_amount)) }}</strong></span>
                            </div>
                        </div>

                        <!-- 4. Hạch toán cân đối tồn kho (Write-off) -->
                        <div class="flex items-center justify-between rounded-xl border border-indigo-500/30 bg-indigo-950/20 p-3">
                            <div class="space-y-0.5">
                                <div class="font-bold text-foreground">
                                    Tự Động Hạch Toán Xuất Kho Hao Hụt (Write-off)
                                </div>
                                <div class="text-muted-foreground text-[11px]">
                                    Tạo giao dịch xuất rác/hao hụt để số tồn trên phần mềm khớp với kiểm đếm thực tế
                                </div>
                            </div>
                            <input
                                type="checkbox"
                                v-model="resolutionForm.write_off_inventory"
                                class="size-5 rounded accent-indigo-500"
                            />
                        </div>

                        <!-- 5. Kết luận điều tra -->
                        <div>
                            <label class="mb-1 block font-bold text-foreground">
                                5. Kết Luận Điều Tra & Lý Do Phạt (*)
                            </label>
                            <textarea
                                v-model="resolutionForm.resolution_notes"
                                rows="3"
                                placeholder="Ghi nhận căn cứ ra quyết định (biên bản làm việc, hình ảnh, lời khai của tài xế/kho)..."
                                class="w-full rounded-lg border border-input bg-background p-2 text-xs text-foreground focus:outline-none"
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-border bg-muted/20 p-4">
                        <Button
                            @click="isResolveModalOpen = false"
                            variant="ghost"
                            size="sm"
                            class="text-xs"
                        >
                            Hủy
                        </Button>
                        <Button
                            @click="proceedToVoucherConfirmation"
                            size="sm"
                            class="gap-1.5 bg-rose-600 text-xs font-semibold text-white hover:bg-rose-700 shadow-sm"
                        >
                            <FileText class="size-4" /> Xác Nhận Quyết Định Phạt
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- MODAL 3: REVIEW RECEIVING REPORT (REPLACES PROMPT) -->
        <Teleport to="body">
            <div
                v-if="isReviewReportModalOpen && selectedReportForReview"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-md"
            >
                <div class="flex w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl">
                    <div class="flex items-center justify-between border-b border-border bg-slate-950 p-5 text-white">
                        <div class="flex items-center gap-2.5">
                            <div class="flex size-9 items-center justify-center rounded-lg bg-amber-600 text-white">
                                <UserCheck class="size-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold">
                                    Xử Lý Biên Bản Nhận Hàng Kho Tổng
                                </h3>
                                <p class="text-xs text-slate-300">
                                    Biên bản: <span class="font-mono font-bold text-amber-300">{{ selectedReportForReview.report_code }}</span>
                                </p>
                            </div>
                        </div>
                        <button
                            @click="isReviewReportModalOpen = false"
                            class="rounded-lg p-1 text-slate-400 hover:text-white"
                        >
                            <X class="size-6" />
                        </button>
                    </div>

                    <div class="space-y-4 p-6 text-xs">
                        <div class="rounded-xl border border-amber-500/30 bg-amber-950/20 p-3 text-amber-200">
                            <div><strong>Đơn cấp phát:</strong> {{ selectedReportForReview.supply_request?.request_code }}</div>
                            <div><strong>Chi nhánh:</strong> {{ selectedReportForReview.supply_request?.to_branch?.name || 'Chi nhánh nhận' }}</div>
                            <div><strong>Tài xế giao:</strong> {{ selectedReportForReview.supply_request?.transporter?.name || '---' }}</div>
                        </div>

                        <!-- Lựa chọn phương án xử lý -->
                        <div>
                            <label class="mb-2 block font-bold text-foreground">
                                Lựa chọn phương án xử lý hàng lỗi cách ly:
                            </label>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2.5 rounded-lg border border-border bg-background p-2.5 cursor-pointer hover:bg-muted/40">
                                    <input
                                        type="radio"
                                        value="quarantine_destroy"
                                        v-model="reviewReportForm.action_type"
                                        class="accent-amber-500"
                                    />
                                    <div>
                                        <div class="font-bold text-foreground">Tiêu hủy hàng lỗi tại khu cách ly</div>
                                        <div class="text-[11px] text-muted-foreground">Xuất hủy hàng hỏng/hết hạn không thể tái sử dụng</div>
                                    </div>
                                </label>

                                <label class="flex items-center gap-2.5 rounded-lg border border-border bg-background p-2.5 cursor-pointer hover:bg-muted/40">
                                    <input
                                        type="radio"
                                        value="return_supplier"
                                        v-model="reviewReportForm.action_type"
                                        class="accent-amber-500"
                                    />
                                    <div>
                                        <div class="font-bold text-foreground">Hoàn trả nhà cung cấp / đối tác</div>
                                        <div class="text-[11px] text-muted-foreground">Trả lại hàng sai quy cách/kém chất lượng</div>
                                    </div>
                                </label>

                                <label class="flex items-center gap-2.5 rounded-lg border border-border bg-background p-2.5 cursor-pointer hover:bg-muted/40">
                                    <input
                                        type="radio"
                                        value="dispute_penalty"
                                        v-model="reviewReportForm.action_type"
                                        class="accent-amber-500"
                                    />
                                    <div>
                                        <div class="font-bold text-foreground">Lập hồ sơ yêu cầu đền bù thiệt hại</div>
                                        <div class="text-[11px] text-muted-foreground">Chuyển sang biên bản bất đồng để khấu trừ lương/đòi tài xế</div>
                                    </div>
                                </label>

                                <label class="flex items-center gap-2.5 rounded-lg border border-border bg-background p-2.5 cursor-pointer hover:bg-muted/40">
                                    <input
                                        type="radio"
                                        value="other"
                                        v-model="reviewReportForm.action_type"
                                        class="accent-amber-500"
                                    />
                                    <div>
                                        <div class="font-bold text-foreground">Phương án xử lý khác</div>
                                        <div class="text-[11px] text-muted-foreground">Tự nhập nội dung kết luận cụ thể</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Chi tiết kết luận -->
                        <div>
                            <label class="mb-1 block font-bold text-foreground">
                                Chi tiết kết luận & Chỉ đạo xử lý (*):
                            </label>
                            <textarea
                                v-model="reviewReportForm.notes"
                                rows="3"
                                placeholder="Nhập ghi chú chi tiết, thời hạn xử lý hàng cách ly hoặc chỉ đạo cụ thể..."
                                class="w-full rounded-lg border border-input bg-background p-2 text-xs text-foreground focus:outline-none"
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-border bg-muted/20 p-4">
                        <Button
                            @click="isReviewReportModalOpen = false"
                            variant="ghost"
                            size="sm"
                            class="text-xs"
                        >
                            Hủy
                        </Button>
                        <Button
                            @click="submitReviewReport"
                            size="sm"
                            :disabled="isProcessing"
                            class="gap-1.5 bg-amber-600 text-xs font-semibold text-white hover:bg-amber-700"
                        >
                            <UserCheck class="size-4" /> Xác Nhận Kết Luận
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- MODAL 4: FORMAL A4 VOUCHER PREVIEW (PHIẾU QUY TRÁCH NHIỆM & PHÂN BỔ BỒI THƯỜNG) -->
        <Teleport to="body">
            <div
                v-if="isDocumentPreviewModalOpen && selectedDispute"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/85 p-2 sm:p-4 backdrop-blur-md overflow-y-auto"
            >
                <div class="flex w-full max-w-4xl flex-col rounded-2xl border border-slate-700 bg-card shadow-2xl my-auto">
                    <!-- Preview Bar Controls (Hidden in Print) -->
                    <div class="flex items-center justify-between border-b border-border bg-slate-900 p-4 text-white print:hidden">
                        <div class="flex items-center gap-2.5">
                            <div class="flex size-8 items-center justify-center rounded-lg bg-sky-600 text-white">
                                <FileText class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold sm:text-base">
                                    Xác Nhận Lại Phiếu Quyết Định Quy Trách Nhiệm
                                </h3>
                                <p class="text-[11px] text-slate-300">
                                    Kiểm tra văn bản hành chính trước khi chính thức ban hành hoặc in lưu trữ
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                @click="printDocument"
                                size="sm"
                                variant="outline"
                                class="h-8 gap-1.5 border-slate-700 bg-slate-800 text-xs text-white hover:bg-slate-700"
                            >
                                <Printer class="size-3.5" /> In Phiếu (A4)
                            </Button>
                            <button
                                @click="isDocumentPreviewModalOpen = false"
                                class="rounded-lg p-1 text-slate-400 hover:text-white"
                            >
                                <X class="size-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Scrollable Sheet Container -->
                    <div class="max-h-[80vh] overflow-y-auto p-3 sm:p-6 bg-slate-900/50">
                        <!-- THE FORMAL VOUCHER SHEET (Matching Image 2) -->
                        <div
                            id="printable-voucher-document"
                            class="mx-auto w-full max-w-[210mm] rounded-xl border-2 border-sky-800 bg-white p-6 sm:p-8 text-slate-900 shadow-xl font-sans"
                            style="font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;"
                        >
                            <!-- Header Info -->
                            <div class="flex flex-col gap-4 border-b-2 border-sky-800 pb-3 sm:flex-row sm:items-start sm:justify-between">
                                <!-- Company Left -->
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <div class="flex size-7 items-center justify-center rounded bg-sky-800 text-white">
                                            <Building2 class="size-4" />
                                        </div>
                                        <span class="text-sm font-black tracking-wide text-sky-900">
                                            CÔNG TY TNHH AVENTURA
                                        </span>
                                    </div>
                                    <p class="text-[11px] font-semibold text-slate-700">
                                        Chuỗi cung cấp thực phẩm & dịch vụ nhà hàng
                                    </p>
                                    <p class="flex items-center gap-1 text-[10px] text-slate-600">
                                        <MapPin class="size-3 text-sky-700" />
                                        Số 123 Nguyễn Văn Cừ, P. Bồ Đề, Q. Long Biên, Hà Nội
                                    </p>
                                    <p class="flex items-center gap-1 text-[10px] text-slate-600">
                                        <Phone class="size-3 text-sky-700" />
                                        Hotline: 024 1234 5678
                                    </p>
                                </div>

                                <!-- Republic Right -->
                                <div class="text-center sm:text-right space-y-0.5">
                                    <p class="text-[11px] font-bold tracking-wider text-sky-950 uppercase">
                                        CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM
                                    </p>
                                    <p class="text-[11px] font-bold text-sky-900">
                                        Độc lập - Tự do - Hạnh phúc
                                    </p>
                                    <p class="text-[10px] tracking-widest text-sky-800">
                                        ★★★
                                    </p>
                                    <p class="pt-1 text-[10px] italic text-slate-600">
                                        {{ documentHeaderDate }}
                                    </p>
                                </div>
                            </div>

                            <!-- Document Title -->
                            <div class="my-5 text-center space-y-1.5">
                                <h2 class="text-lg sm:text-xl font-black tracking-wide text-sky-900 uppercase">
                                    QUY TRÁCH NHIỆM & PHÂN BỔ BỒI THƯỜNG
                                </h2>
                                <div class="inline-block rounded border border-sky-800 px-4 py-0.5 text-xs font-bold text-sky-900">
                                    Số: {{ selectedDispute.dispute_code }}
                                </div>
                            </div>

                            <!-- 1. THÔNG TIN CHUNG -->
                            <div class="mb-4">
                                <h3 class="mb-1 text-xs font-bold text-sky-900 uppercase">
                                    1. THÔNG TIN CHUNG
                                </h3>
                                <div class="grid grid-cols-1 divide-y border border-sky-800 text-[11px] sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                                    <div class="divide-y divide-sky-100 p-0">
                                        <div class="flex p-1.5"><span class="w-32 font-bold text-slate-700">Ngày lập phiếu:</span> <span>{{ documentDateFormatted }}</span></div>
                                        <div class="flex p-1.5"><span class="w-32 font-bold text-slate-700">Vụ việc:</span> <span>Vi phạm quy trình – Thất thoát hàng hóa</span></div>
                                        <div class="flex p-1.5"><span class="w-32 font-bold text-slate-700">Địa điểm xảy ra:</span> <span>{{ selectedDispute.supply_request?.to_branch?.name || 'Kho Tổng' }}</span></div>
                                        <div class="flex p-1.5"><span class="w-32 font-bold text-slate-700">Bộ phận liên quan:</span> <span>Kho Tổng / Kế toán / Phòng Nhân sự</span></div>
                                    </div>
                                    <div class="divide-y divide-sky-100 p-0">
                                        <div class="flex p-1.5"><span class="w-32 font-bold text-slate-700">Người lập phiếu:</span> <span>{{ selectedDispute.resolver?.name || currentUser?.name || 'Trưởng Kho' }}</span></div>
                                        <div class="flex p-1.5"><span class="w-32 font-bold text-slate-700">Chức vụ:</span> <span>Trưởng bộ phận kho</span></div>
                                        <div class="flex p-1.5"><span class="w-32 font-bold text-slate-700">Số biên bản liên quan:</span> <span class="font-mono font-bold text-sky-800">{{ selectedDispute.dispute_code }}</span></div>
                                        <div class="flex p-1.5"><span class="w-32 font-bold text-slate-700">Người vi phạm:</span> <span class="font-bold text-rose-800">{{ docViolatorName }}</span></div>
                                        <div class="flex p-1.5"><span class="w-32 font-bold text-slate-700">Chức vụ:</span> <span>{{ docViolatorRole }}</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. NỘI DUNG VI PHẠM -->
                            <div class="mb-4">
                                <h3 class="mb-1 text-xs font-bold text-sky-900 uppercase">
                                    2. NỘI DUNG VI PHẠM
                                </h3>
                                <table class="w-full border border-sky-800 text-left text-[11px]">
                                    <thead class="bg-sky-50 font-bold text-sky-950 border-b border-sky-800">
                                        <tr>
                                            <th class="border-r border-sky-800 p-1.5 text-center w-10">STT</th>
                                            <th class="border-r border-sky-800 p-1.5 w-48">Hành vi vi phạm</th>
                                            <th class="border-r border-sky-800 p-1.5 text-center w-28">Thời gian xảy ra</th>
                                            <th class="p-1.5">Chi tiết</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-sky-800">
                                        <tr>
                                            <td class="border-r border-sky-800 p-1.5 text-center">1</td>
                                            <td class="border-r border-sky-800 p-1.5 font-medium">Thiếu trách nhiệm trong giao nhận / kiểm soát kho</td>
                                            <td class="border-r border-sky-800 p-1.5 text-center">{{ documentDateFormatted }}</td>
                                            <td class="p-1.5">
                                                Xuất chênh lệch thiếu {{ selectedDispute.discrepancy_quantity }} {{ selectedDispute.ingredient?.unit?.symbol || 'đơn vị' }}
                                                {{ selectedDispute.ingredient?.name }} (đơn cấp phát {{ selectedDispute.supply_request?.request_code }}).
                                            </td>
                                        </tr>
                                        <tr v-if="resolutionForm.resolution_notes">
                                            <td class="border-r border-sky-800 p-1.5 text-center">2</td>
                                            <td class="border-r border-sky-800 p-1.5 font-medium">Kết luận điều tra thực tế</td>
                                            <td class="border-r border-sky-800 p-1.5 text-center">{{ documentDateFormatted }}</td>
                                            <td class="p-1.5">{{ resolutionForm.resolution_notes }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Box Tổng Thiệt Hại Bằng Chữ (Like Image 2) -->
                                <div class="mt-1.5 flex border border-rose-600 bg-rose-50/60 text-xs">
                                    <div class="w-44 border-r border-rose-600 p-2 font-black text-rose-700 uppercase">
                                        Tổng giá trị thiệt hại:
                                    </div>
                                    <div class="flex-1 p-2 font-black text-rose-700">
                                        {{ formatCurrency(docLossAmount) }}
                                        <span class="ml-2 font-normal italic text-slate-700">
                                            ({{ numberToVietnameseWords(docLossAmount) }})
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. XÁC ĐỊNH TRÁCH NHIỆM -->
                            <div class="mb-4">
                                <h3 class="mb-1 text-xs font-bold text-sky-900 uppercase">
                                    3. XÁC ĐỊNH TRÁCH NHIỆM
                                </h3>
                                <table class="w-full border border-sky-800 text-left text-[11px]">
                                    <thead class="bg-sky-50 font-bold text-sky-950 border-b border-sky-800">
                                        <tr>
                                            <th class="border-r border-sky-800 p-1.5 text-center w-10">STT</th>
                                            <th class="border-r border-sky-800 p-1.5">Họ và tên</th>
                                            <th class="border-r border-sky-800 p-1.5">Bộ phận</th>
                                            <th class="border-r border-sky-800 p-1.5">Chức vụ</th>
                                            <th class="border-r border-sky-800 p-1.5 text-center w-36">Mức độ trách nhiệm</th>
                                            <th class="p-1.5">Ghi chú</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-sky-800">
                                        <tr>
                                            <td class="border-r border-sky-800 p-1.5 text-center">1</td>
                                            <td class="border-r border-sky-800 p-1.5 font-bold">{{ docViolatorName }}</td>
                                            <td class="border-r border-sky-800 p-1.5">{{ docViolatorDept }}</td>
                                            <td class="border-r border-sky-800 p-1.5">{{ docViolatorRole }}</td>
                                            <td class="border-r border-sky-800 p-1.5 text-center font-bold text-rose-700">
                                                {{ docPenaltyPercent }}%
                                            </td>
                                            <td class="p-1.5">Vi phạm quy trình, để thất thoát hàng hóa</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 4. PHÂN BỔ BỒI THƯỜNG -->
                            <div class="mb-4">
                                <h3 class="mb-1 text-xs font-bold text-sky-900 uppercase">
                                    4. PHÂN BỔ BỒI THƯỜNG
                                </h3>
                                <table class="w-full border border-sky-800 text-left text-[11px]">
                                    <thead class="bg-sky-50 font-bold text-sky-950 border-b border-sky-800">
                                        <tr>
                                            <th class="border-r border-sky-800 p-1.5 text-center w-10">STT</th>
                                            <th class="border-r border-sky-800 p-1.5">Họ và tên</th>
                                            <th class="border-r border-sky-800 p-1.5 text-right">Tổng giá trị thiệt hại (VNĐ)</th>
                                            <th class="border-r border-sky-800 p-1.5 text-center w-28">Tỷ lệ bồi thường</th>
                                            <th class="border-r border-sky-800 p-1.5 text-right">Số tiền bồi thường (VNĐ)</th>
                                            <th class="p-1.5">Ghi chú</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-sky-800">
                                        <tr>
                                            <td class="border-r border-sky-800 p-1.5 text-center">1</td>
                                            <td class="border-r border-sky-800 p-1.5 font-bold">{{ docViolatorName }}</td>
                                            <td class="border-r border-sky-800 p-1.5 text-right">{{ formatCurrency(docLossAmount) }}</td>
                                            <td class="border-r border-sky-800 p-1.5 text-center font-bold">{{ docPenaltyPercent }}%</td>
                                            <td class="border-r border-sky-800 p-1.5 text-right font-bold text-rose-700">{{ formatCurrency(docPenaltyAmount) }}</td>
                                            <td class="p-1.5">
                                                <span v-if="docPenaltyAmount > 0">Trừ vào lương tháng {{ documentMonthYear }}</span>
                                                <span v-else>Công ty hỗ trợ miễn trừ 100%</span>
                                                <span v-if="docWaivedAmount > 0 && docPenaltyAmount > 0" class="block text-[10px] text-slate-500">
                                                    (Công ty chịu {{ formatCurrency(docWaivedAmount) }})
                                                </span>
                                            </td>
                                        </tr>
                                        <tr class="bg-sky-50 font-bold text-sky-950">
                                            <td colspan="4" class="border-r border-sky-800 p-1.5 text-center uppercase tracking-wide">
                                                TỔNG CỘNG
                                            </td>
                                            <td class="border-r border-sky-800 p-1.5 text-right text-rose-700">
                                                {{ formatCurrency(docPenaltyAmount) }}
                                            </td>
                                            <td class="p-1.5"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 5. KẾT LUẬN & KIẾN NGHỊ -->
                            <div class="mb-5 text-xs text-slate-800 space-y-1">
                                <h3 class="mb-1 text-xs font-bold text-sky-900 uppercase">
                                    5. KẾT LUẬN & KIẾN NGHỊ
                                </h3>
                                <p class="pl-2">
                                    • Yêu cầu <strong>{{ docViolatorName }}</strong> nghiêm túc rút kinh nghiệm, chấp hành đầy đủ quy trình kiểm soát kho.
                                </p>
                                <p v-if="docPenaltyAmount > 0" class="pl-2">
                                    • Đề nghị Phòng Kế toán thực hiện khấu trừ <strong>{{ formatCurrency(docPenaltyAmount) }}</strong> vào lương tháng {{ documentMonthYear }} của <strong>{{ docViolatorName }}</strong>.
                                </p>
                                <p v-if="docWaivedAmount > 0" class="pl-2">
                                    • Phần chênh lệch miễn trừ <strong>{{ formatCurrency(docWaivedAmount) }}</strong> được hạch toán vào chi phí hao hụt rủi ro vận hành của Công ty.
                                </p>
                                <p v-if="resolutionForm.write_off_inventory" class="pl-2">
                                    • Bộ phận Kho thực hiện xuất kho hao hụt (Write-off) để cân đối số tồn thực tế trên phần mềm.
                                </p>
                            </div>

                            <!-- 6. CHỮ KÝ XÁC NHẬN (5 Columns) -->
                            <div class="mb-4">
                                <h3 class="mb-1 text-xs font-bold text-sky-900 uppercase">
                                    6. CHỮ KÝ XÁC NHẬN
                                </h3>
                                <div class="grid grid-cols-5 border border-sky-800 text-center text-[10px]">
                                    <!-- Col 1 -->
                                    <div class="border-r border-sky-800 p-1.5 flex flex-col justify-between h-32">
                                        <div class="bg-sky-50 p-1 font-bold text-sky-950">
                                            Người lập phiếu<br/><span class="font-normal italic text-[9px]">(Ký, ghi rõ họ tên)</span>
                                        </div>
                                        <div class="font-bold text-slate-800 pb-1">
                                            {{ selectedDispute.resolver?.name || currentUser?.name || '' }}
                                            <p class="text-[8px] font-normal italic text-slate-500">Ngày ...../...../20.....</p>
                                        </div>
                                    </div>
                                    <!-- Col 2 -->
                                    <div class="border-r border-sky-800 p-1.5 flex flex-col justify-between h-32">
                                        <div class="bg-sky-50 p-1 font-bold text-sky-950">
                                            Trưởng bộ phận kho<br/><span class="font-normal italic text-[9px]">(Ký, ghi rõ họ tên)</span>
                                        </div>
                                        <div class="font-bold text-slate-800 pb-1">
                                            <p class="text-[8px] font-normal italic text-slate-500">Ngày ...../...../20.....</p>
                                        </div>
                                    </div>
                                    <!-- Col 3 -->
                                    <div class="border-r border-sky-800 p-1.5 flex flex-col justify-between h-32">
                                        <div class="bg-sky-50 p-1 font-bold text-sky-950">
                                            Phòng Nhân sự<br/><span class="font-normal italic text-[9px]">(Ký, ghi rõ họ tên)</span>
                                        </div>
                                        <div class="font-bold text-slate-800 pb-1">
                                            <p class="text-[8px] font-normal italic text-slate-500">Ngày ...../...../20.....</p>
                                        </div>
                                    </div>
                                    <!-- Col 4 -->
                                    <div class="border-r border-sky-800 p-1.5 flex flex-col justify-between h-32">
                                        <div class="bg-sky-50 p-1 font-bold text-sky-950">
                                            Kế toán trưởng<br/><span class="font-normal italic text-[9px]">(Ký, ghi rõ họ tên)</span>
                                        </div>
                                        <div class="font-bold text-slate-800 pb-1">
                                            <p class="text-[8px] font-normal italic text-slate-500">Ngày ...../...../20.....</p>
                                        </div>
                                    </div>
                                    <!-- Col 5 -->
                                    <div class="p-1.5 flex flex-col justify-between h-32">
                                        <div class="bg-sky-50 p-1 font-bold text-sky-950">
                                            Giám đốc<br/><span class="font-normal italic text-[9px]">(Ký, ghi rõ họ tên)</span>
                                        </div>
                                        <div class="font-bold text-slate-800 pb-1">
                                            <p class="text-[8px] font-normal italic text-slate-500">Ngày ...../...../20.....</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Notes -->
                            <div class="text-[9px] italic text-slate-500 border-t border-slate-200 pt-2 space-y-0.5">
                                <p><strong>Ghi chú:</strong></p>
                                <p>• Mức bồi thường có thể điều chỉnh theo mức độ lỗi và quy định của Công ty.</p>
                                <p>• Phiếu này có hiệu lực kể từ ngày ký.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Actions Footer (Hidden in Print) -->
                    <div class="flex flex-wrap items-center justify-between border-t border-border bg-slate-900 p-4 text-xs print:hidden">
                        <Button
                            v-if="canResolveDispute(selectedDispute.status)"
                            @click="backToEditResolution"
                            variant="outline"
                            size="sm"
                            class="gap-1.5 border-slate-700 bg-slate-800 text-white hover:bg-slate-700"
                        >
                            <ArrowLeft class="size-3.5" /> Quay Lại Chỉnh Sửa
                        </Button>
                        <div v-else class="text-[11px] text-emerald-400 font-semibold flex items-center gap-1">
                            <CheckCircle class="size-4" /> Quyết định đã được ban hành chính thức
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                @click="printDocument"
                                size="sm"
                                variant="outline"
                                class="gap-1.5 border-slate-700 bg-slate-800 text-white hover:bg-slate-700"
                            >
                                <Printer class="size-3.5" /> In Phiếu
                            </Button>

                            <Button
                                v-if="canResolveDispute(selectedDispute.status)"
                                @click="submitResolution"
                                size="sm"
                                :disabled="isProcessing"
                                class="gap-1.5 bg-rose-600 font-bold text-white hover:bg-rose-700 shadow-md"
                            >
                                <CheckCheck class="size-4" /> Ký Duyệt & Ban Hành Quyết Định Phạt
                            </Button>

                            <Button
                                v-else
                                @click="isDocumentPreviewModalOpen = false"
                                size="sm"
                                class="bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                            >
                                Đóng
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- MODAL: THU TIỀN BỒI THƯỜNG TỪ ĐƠN VỊ VẬN CHUYỂN -->
        <Teleport to="body">
            <div
                v-if="isClaimModalOpen && claimTargetDispute"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-md"
            >
                <div class="flex w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl">
                    <div class="flex items-center justify-between border-b border-border bg-emerald-950 p-4 text-white">
                        <div class="flex items-center gap-2.5">
                            <div class="flex size-8 items-center justify-center rounded-lg bg-emerald-600 text-white">
                                <DollarSign class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold">
                                    Thu Tiền Bồi Thường Đơn Vị Vận Chuyển
                                </h3>
                                <p class="text-[11px] text-emerald-300">
                                    Biên bản: <span class="font-mono font-semibold text-white">{{ claimTargetDispute.dispute_code }}</span>
                                </p>
                            </div>
                        </div>
                        <button
                            @click="isClaimModalOpen = false"
                            class="rounded-lg p-1 text-slate-400 hover:text-white"
                        >
                            <X class="size-5" />
                        </button>
                    </div>

                    <div class="space-y-4 p-5 text-xs">
                        <div class="rounded-xl border border-border bg-muted/30 p-3 space-y-1.5">
                            <div class="flex justify-between text-muted-foreground">
                                <span>Mặt hàng tổn thất:</span>
                                <span class="font-bold text-foreground">{{ claimTargetDispute.ingredient?.name }}</span>
                            </div>
                            <div class="flex justify-between text-muted-foreground">
                                <span>Đơn vị vận chuyển / Tài xế:</span>
                                <span class="font-bold text-foreground">{{ claimTargetDispute.supply_request?.transporter?.name || 'Tài xế/ĐVVC' }}</span>
                            </div>
                            <div class="flex justify-between text-muted-foreground">
                                <span>Tổng thiệt hại xác định:</span>
                                <span class="font-bold text-rose-500">{{ formatCurrency(Number(claimTargetDispute.financial_loss_amount || 0)) }}</span>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block font-bold text-foreground">Số Tiền Thực Thu (VNĐ) <span class="text-rose-500">*</span></label>
                            <Input
                                v-model.number="claimForm.collected_amount"
                                type="number"
                                step="1000"
                                min="1"
                                class="text-sm font-bold text-emerald-600 dark:text-emerald-400"
                            />
                            <p class="text-[11px] text-muted-foreground">
                                Bằng chữ: <span class="font-semibold italic text-foreground">{{ numberToVietnameseWords(claimForm.collected_amount) }}</span>
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block font-bold text-foreground">Hình Thức Thu Tiền <span class="text-rose-500">*</span></label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="claimForm.payment_method = 'cash'"
                                    :class="['rounded-lg border p-2 text-center font-bold transition', claimForm.payment_method === 'cash' ? 'border-emerald-500 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'border-border bg-muted/20 text-muted-foreground']"
                                >
                                    Tiền mặt (TK 1111)
                                </button>
                                <button
                                    type="button"
                                    @click="claimForm.payment_method = 'bank'"
                                    :class="['rounded-lg border p-2 text-center font-bold transition', claimForm.payment_method === 'bank' ? 'border-emerald-500 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'border-border bg-muted/20 text-muted-foreground']"
                                >
                                    Chuyển khoản (TK 1121)
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block font-bold text-foreground">Ghi Chú Thu Tiền / Mã Giao Dịch</label>
                            <Input
                                v-model="claimForm.notes"
                                placeholder="Nhập ghi chú hoặc mã giao dịch ngân hàng..."
                                class="text-xs"
                            />
                        </div>

                        <div class="rounded-lg border border-emerald-500/30 bg-emerald-950/20 p-2.5 text-[11px] text-emerald-300">
                            <strong>Hạch toán kế toán tự động:</strong> Nợ TK {{ claimForm.payment_method === 'bank' ? '1121' : '1111' }} / Có TK 1388 ({{ formatCurrency(claimForm.collected_amount) }}).
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-border bg-muted/20 p-4">
                        <Button
                            @click="isClaimModalOpen = false"
                            variant="outline"
                            size="sm"
                            class="text-xs"
                        >
                            Hủy
                        </Button>
                        <Button
                            @click="submitClaimCollection"
                            size="sm"
                            :disabled="isProcessing"
                            class="gap-1.5 bg-emerald-600 font-bold text-white hover:bg-emerald-700 shadow-md"
                        >
                            <CheckCheck class="size-3.5" /> Xác Nhận Thu Tiền
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style>
@media print {
    body * {
        visibility: hidden !important;
    }
    #printable-voucher-document,
    #printable-voucher-document * {
        visibility: visible !important;
    }
    #printable-voucher-document {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 8mm !important;
        box-shadow: none !important;
        border: 2px solid #0369a1 !important;
        background: #ffffff !important;
        color: #000000 !important;
        z-index: 999999 !important;
    }
}
</style>
