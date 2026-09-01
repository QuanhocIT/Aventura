<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowLeft,
    CalendarDays,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    ClipboardCheck,
    FileText,
    MapPin,
    PackageCheck,
    Plus,
    RefreshCw,
    Search,
    ShieldAlert,
    Upload,
    Warehouse,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Location = {
    id: number;
    location_code: string;
    zone: string;
    rack: string | null;
    shelf: string | null;
    bin: string | null;
    is_cold_storage: boolean;
    is_quarantine: boolean;
};

type IngredientOption = {
    id: number;
    name: string;
    sku: string | null;
    average_cost: number | string;
    storage_type?: string | null;
    default_shelf_life_days?: number | null;
    unit?: { symbol: string } | null;
};

type VoucherItem = {
    id: number;
    ingredient_id: number;
    ingredient?: {
        name: string;
        sku?: string | null;
        unit?: { symbol: string } | null;
    } | null;
    unit_label?: string | null;
    expected_qty: number | string;
    actual_qty: number | string;
    unit_cost: number | string;
    item_status: 'ok' | 'short' | 'over' | string;
    discrepancy_reason: string | null;
    lot_number: string | null;
    expiry_date: string | null;
    manufactured_date?: string | null;
    batch_id: number | null;
    batch?: { batch_number?: string | null; status?: string | null } | null;
    location_id: number | null;
    location?: Location | null;
};

type Voucher = {
    id: number;
    voucher_code: string;
    status: string;
    received_at: string | null;
    verified_at: string | null;
    delivery_note_number: string | null;
    invoice_number: string | null;
    invoice_series?: string | null;
    invoice_date?: string | null;
    invoice_total_amount?: number | string | null;
    vat_amount?: number | string | null;
    receiving_dock?: string | null;
    quality_status: 'pending' | 'passed' | 'conditional' | 'failed' | string;
    quality_notes: string | null;
    three_way_match_status?: string | null;
    disposition?: string | null;
    total_expected_qty: number | string;
    total_actual_qty: number | string;
    total_discrepancy_qty: number | string;
    discrepancy_reason: string | null;
    evidence_paths: string[] | null;
    notes: string | null;
    rejection_reason?: string | null;
    putaway_started_at?: string | null;
    putaway_completed_at?: string | null;
    documents?: ReceivingDocument[];
    received_by?: { id?: number; name: string } | null;
    verified_by?: { id?: number; name: string } | null;
    verification_assigned_to?: { id?: number; name: string } | null;
    external_receipt_reason?: string | null;
    external_source_name?: string | null;
    external_reference?: string | null;
    items: VoucherItem[];
};

type ReceivingDocument = {
    id: number;
    document_type:
        | 'external_record'
        | 'qc'
        | 'receiving_photo'
        | 'other'
        | string;
    original_name: string;
    mime_type?: string | null;
    size_bytes?: number | null;
};

type GrnLine = {
    ingredient_id: number | null;
    unit_label: string;
    actual_qty: number;
    unit_cost: number;
    lot_number: string;
    expiry_date: string;
    manufactured_date: string;
    location_id: number | null;
};

const props = defineProps<{
    centralBranch: { id: number; name: string } | null;
    receivingVouchers: Voucher[];
    receivingSummary: Record<string, number>;
    inventorySummary: Record<string, number>;
    warehouseLocations: Location[];
    ingredients: IngredientOption[];
    warehouseStaff: Array<{ id: number; name: string; job_title?: string | null }>;
    canManageWarehouse: boolean;
    canCreateReceiving: boolean;
    currentUserId: number;
    currentUserName: string;
    canApproveOwnReceiving: boolean;
}>();

const vouchers = ref<Voucher[]>([...props.receivingVouchers]);
const summary = ref({ ...props.receivingSummary });
const inventory = ref({ ...props.inventorySummary });

watch(
    () => props.receivingVouchers,
    (newVal) => {
        if (newVal) {
            vouchers.value = [...newVal];
        }
    },
    { deep: true },
);

watch(
    () => props.receivingSummary,
    (newVal) => {
        if (newVal) {
            summary.value = { ...newVal };
        }
    },
    { deep: true },
);

let autoRefreshTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    autoRefreshTimer = setInterval(() => {
        if (
            !document.hidden &&
            !showGrnForm.value &&
            !confirming.value &&
            !rejecting.value &&
            !dispositionVoucher.value
        ) {
            router.reload({
                only: ['receivingVouchers', 'receivingSummary'],
                preserveScroll: true,
                preserveState: true,
            });
        }
    }, 4000);
});

onBeforeUnmount(() => {
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    }
});
const filter = ref('pending');
const search = ref('');
const expandedId = ref<number | null>(null);
const isProcessing = ref<number | null>(null);
const confirming = ref<Voucher | null>(null);
const rejecting = ref<Voucher | null>(null);
const rejectReason = ref('');
const isRejecting = ref(false);
const confirmNotes = ref('');
const confirmError = ref('');
const confirmQualityStatus = ref<'passed' | 'conditional' | 'failed'>('passed');
const confirmQualityNotes = ref('');
const confirmEvidence = ref<File[]>([]);
const dispositionVoucher = ref<Voucher | null>(null);
const dispositionKind = ref<'return_external' | 'destroy'>('return_external');
const dispositionReason = ref('');
const dispositionEvidence = ref<File[]>([]);
const isDisposing = ref(false);
const showGrnForm = ref(
    typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).get('create') === '1',
);
const isSubmittingGrn = ref(false);
const grnErrors = ref<string[]>([]);
const grnErrorSummary = ref<HTMLElement | null>(null);
const grnFiles = ref<File[]>([]);
const grnDocumentType = ref<
    'external_record' | 'qc' | 'receiving_photo' | 'other'
>('external_record');
const receivedAtPicker = ref<HTMLInputElement | null>(null);
const rowDatePickers = ref<Record<string, HTMLInputElement | null>>({});

const emptyLine = (): GrnLine => ({
    ingredient_id: null,
    unit_label: '',
    actual_qty: 0,
    unit_cost: 0,
    lot_number: '',
    expiry_date: '',
    manufactured_date: '',
    location_id: null,
});

const onManufacturedDateChange = (line: GrnLine) => {
    if (
        line.expiry_date &&
        line.manufactured_date &&
        line.expiry_date < line.manufactured_date
    ) {
        line.expiry_date = line.manufactured_date;
    }
};

const grnForm = ref({
    received_at: new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16),
    external_receipt_reason: 'other' as
        | 'external_donation'
        | 'external_return'
        | 'other',
    external_source_name: '',
    external_reference: '',
    verification_assigned_to: null as number | null,
    notes: '',
    receiving_dock: '',
    quality_status: 'pending',
    quality_notes: '',
    items: [emptyLine()],
});

const formatQuantity = (value: number | string | null | undefined) =>
    new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(
        Number(value || 0),
    );

const formatCurrency = (value: number | string | null | undefined) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));

const formatDate = (value: string | null | undefined) => {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleString('vi-VN', {
              dateStyle: 'short',
              timeStyle: 'short',
          });
};

const formatDateOnly = (value: string | null | undefined) => {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('vi-VN');
};

const statusLabel = (status: string, isExternal = false) => {
    if (isExternal && status === 'pending_review') {
        return 'Chờ nhân viên kiểm kê';
    }

    return status === 'pending_disposition'
        ? 'Chờ trả/hủy'
        : status === 'returned'
          ? 'Đã hoàn tất trả'
          : status === 'destroyed'
            ? 'Đã tiêu hủy'
            : status === 'rejected'
              ? 'Bị từ chối — cần sửa'
              : {
                    draft: 'Chờ xác minh',
                    discrepancy: 'Có chênh lệch (phiếu cũ)',
                    pending_review: 'Chờ xem xét',
                    confirmed: 'Đã nhập kho',
                    closed: 'Đã đóng',
                }[status] || status;
};

const statusClass = (status: string) =>
    status === 'pending_disposition'
        ? 'border-rose-400/30 bg-rose-500/10 text-rose-300'
        : status === 'returned'
          ? 'border-sky-400/30 bg-sky-500/10 text-sky-300'
          : status === 'destroyed'
            ? 'border-slate-400/30 bg-slate-500/10 text-slate-300'
            : status === 'rejected'
              ? 'border-red-400/30 bg-red-500/10 text-red-300'
              : {
                    draft: 'border-orange-400/30 bg-orange-500/10 text-orange-300',
                    discrepancy:
                        'border-rose-400/30 bg-rose-500/10 text-rose-300',
                    pending_review:
                        'border-amber-400/30 bg-amber-500/10 text-amber-300',
                    confirmed:
                        'border-emerald-400/30 bg-emerald-500/10 text-emerald-300',
                    closed: 'border-slate-400/30 bg-slate-500/10 text-slate-300',
                }[status] || 'border-border bg-muted text-muted-foreground';

const qualityLabel = (status: string | null | undefined) =>
    ({
        pending: 'Chưa kiểm tra',
        passed: 'Đạt',
        conditional: 'Đạt có điều kiện',
        failed: 'Không đạt',
    })[status || 'pending'] ||
    status ||
    'Chưa kiểm tra';

const externalReceiptReasonLabel = (reason: string | null | undefined) =>
    ({
        external_donation: 'Biếu tặng / hỗ trợ từ bên ngoài',
        external_return: 'Tiếp nhận hoàn từ bên ngoài',
        other: 'Nhập ngoài khác',
    })[reason || 'other'] || 'Nhập ngoài';

const ingredientUnit = (ingredientId: number | null) =>
    props.ingredients.find((item) => item.id === ingredientId)?.unit?.symbol ??
    'đv';

const lineTotal = (line: {
    actual_qty: number | string | null | undefined;
    unit_cost: number | string | null | undefined;
}) =>
    Math.max(0, Number(line.actual_qty || 0) * Number(line.unit_cost || 0));

const voucherItemTotal = (item: Pick<VoucherItem, 'actual_qty' | 'unit_cost'>) =>
    lineTotal(item);

const totalReceiptValue = computed(() =>
    grnForm.value.items.reduce((total, line) => total + lineTotal(line), 0),
);

const voucherTotal = (voucher: Voucher) => {
    const recordedTotal = Number(voucher.invoice_total_amount ?? 0);

    return recordedTotal > 0
        ? recordedTotal
        : voucher.items.reduce((total, item) => total + voucherItemTotal(item), 0);
};

const canReview = (voucher: Voucher) =>
    props.canManageWarehouse &&
    !voucher.external_receipt_reason &&
    (props.canApproveOwnReceiving ||
        voucher.received_by?.id !== props.currentUserId);


const filteredVouchers = computed(() => {
    const query = search.value.trim().toLowerCase();

    return vouchers.value.filter((voucher) => {
        const matchesFilter =
            filter.value === 'all' ||
            (filter.value === 'pending' &&
                [
                    'draft',
                    'discrepancy',
                    'pending_review',
                    'pending_disposition',
                    'rejected',
                ].includes(voucher.status)) ||
            (filter.value === 'discrepancy' &&
                ['discrepancy', 'pending_review'].includes(voucher.status)) ||
            (filter.value === 'confirmed' &&
                ['confirmed', 'closed'].includes(voucher.status));
        const haystack = [
            voucher.voucher_code,
            voucher.external_source_name,
            voucher.external_reference,
            voucher.received_by?.name,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return matchesFilter && (!query || haystack.includes(query));
    });
});

const pendingCount = computed(() => summary.value.pending_review ?? 0);
const discrepancyCount = computed(
    () => summary.value.discrepancy_vouchers ?? 0,
);
const missingPutawayCount = computed(
    () =>
        vouchers.value.filter(
            (voucher) =>
                ['draft', 'discrepancy', 'pending_review'].includes(
                    voucher.status,
                ) &&
                voucher.items.some(
                    (item) => Number(item.actual_qty) > 0 && !item.location_id,
                ),
        ).length,
);
const discrepancyItems = computed(
    () =>
        confirming.value?.items.filter((item) => item.item_status !== 'ok') ??
        [],
);

const reload = () => {
    router.reload({
        only: ['receivingVouchers', 'receivingSummary', 'inventorySummary'],
        onSuccess: (page: any) => {
            const nextProps = page.props as unknown as {
                receivingVouchers: Voucher[];
                receivingSummary: Record<string, number>;
                inventorySummary: Record<string, number>;
            };
            vouchers.value = [...(nextProps.receivingVouchers ?? [])];
            summary.value = { ...(nextProps.receivingSummary ?? {}) };
            inventory.value = { ...(nextProps.inventorySummary ?? {}) };
        },
    });
};

const toggleDetails = (id: number) => {
    expandedId.value = expandedId.value === id ? null : id;
};

const openConfirm = (voucher: Voucher) => {
    confirming.value = voucher;
    confirmNotes.value = '';
    confirmError.value = '';
    confirmQualityStatus.value =
        voucher.quality_status === 'conditional' ? 'conditional' : 'passed';
    confirmQualityNotes.value = voucher.quality_notes ?? '';
    confirmEvidence.value = [];
};

const closeConfirm = () => {
    confirming.value = null;
    confirmNotes.value = '';
    confirmError.value = '';
    confirmQualityStatus.value = 'passed';
    confirmQualityNotes.value = '';
    confirmEvidence.value = [];
};

const openReject = (voucher: Voucher) => {
    rejecting.value = voucher;
    rejectReason.value = '';
};

const closeReject = () => {
    rejecting.value = null;
    rejectReason.value = '';
};

const rejectVoucher = async () => {
    if (!rejecting.value || !rejectReason.value.trim() || isRejecting.value) {
        return;
    }

    isRejecting.value = true;

    try {
        await axios.post(
            `/api/warehouse/receiving-vouchers/${rejecting.value.id}/reject`,
            {
                reason: rejectReason.value.trim(),
            },
        );
        rejecting.value.status = 'rejected';
        rejecting.value.rejection_reason = rejectReason.value.trim();
        closeReject();
        toast.success('Đã từ chối phiếu. Phiếu chưa làm thay đổi tồn kho.');
    } catch (error: any) {
        toast.error(
            error.response?.data?.message ?? 'Không thể từ chối phiếu.',
        );
    } finally {
        isRejecting.value = false;
    }
};

const handleConfirmEvidence = (event: Event) => {
    const files = (event.target as HTMLInputElement).files;

    if (files) {
        confirmEvidence.value = [
            ...confirmEvidence.value,
            ...Array.from(files),
        ];
    }
};

const handleDispositionEvidence = (event: Event) => {
    const files = (event.target as HTMLInputElement).files;

    if (files) {
        dispositionEvidence.value = [
            ...dispositionEvidence.value,
            ...Array.from(files),
        ];
    }
};

const openDisposition = (voucher: Voucher) => {
    dispositionVoucher.value = voucher;
    dispositionKind.value = 'return_external';
    dispositionReason.value = '';
    dispositionEvidence.value = [];
};

const closeDisposition = () => {
    dispositionVoucher.value = null;
    dispositionReason.value = '';
    dispositionEvidence.value = [];
};

const confirmVoucher = async () => {
    if (!confirming.value || isProcessing.value) {
        return;
    }

    if (discrepancyItems.value.length > 0 && !confirmNotes.value.trim()) {
        confirmError.value =
            'Phiếu cũ có chênh lệch theo đơn mua hàng, bắt buộc nhập giải trình trước khi xác minh.';

        return;
    }

    if (
        confirmQualityStatus.value === 'conditional' &&
        !confirmQualityNotes.value.trim()
    ) {
        confirmError.value =
            'Hàng đạt có điều kiện phải có ghi chú xử lý chất lượng hoặc thời hạn cách ly.';

        return;
    }

    const voucher = confirming.value;
    isProcessing.value = voucher.id;

    try {
        const payload =
            confirmQualityStatus.value === 'failed'
                ? (() => {
                      const formData = new FormData();
                      formData.append('notes', confirmNotes.value.trim());
                      formData.append(
                          'quality_status',
                          confirmQualityStatus.value,
                      );
                      formData.append(
                          'quality_notes',
                          confirmQualityNotes.value.trim(),
                      );

                      confirmEvidence.value.forEach((file) =>
                          formData.append('evidence[]', file),
                      );

                      return formData;
                  })()
                : {
                      notes: confirmNotes.value.trim(),
                      quality_status: confirmQualityStatus.value,
                      quality_notes: confirmQualityNotes.value.trim(),
                  };
        await axios.post(
            `/api/warehouse/receiving-vouchers/${voucher.id}/confirm`,
            payload,
        );
        voucher.status = 'confirmed';
        voucher.verified_at = new Date().toISOString();
        voucher.quality_status = confirmQualityStatus.value;
        voucher.quality_notes = confirmQualityNotes.value.trim();
        summary.value.pending_review = Math.max(
            0,
            (summary.value.pending_review ?? 1) - 1,
        );
        summary.value.confirmed = (summary.value.confirmed ?? 0) + 1;

        if (confirmQualityStatus.value === 'passed') {
            inventory.value.on_hand_quantity =
                Number(inventory.value.on_hand_quantity ?? 0) +
                Number(voucher.total_actual_qty ?? 0);
        }

        toast.success(
            `Đã xác minh ${voucher.voucher_code}; tồn kho và lô hàng đã được cập nhật.`,
        );
        closeConfirm();
    } catch (error: any) {
        if (error.response?.data?.requires_disposition) {
            voucher.status = 'pending_disposition';
            closeConfirm();
            openDisposition(voucher);
            toast.warning(
            'Lô không đạt đã được cách ly. Hãy ghi nhận trả bên giao hoặc tiêu hủy.',
            );

            return;
        }

        confirmError.value =
            error.response?.data?.message ??
            'Không thể xác minh phiếu nhận hàng.';
    } finally {
        isProcessing.value = null;
    }
};

const disposeReceiving = async () => {
    if (
        !dispositionVoucher.value ||
        !dispositionReason.value.trim() ||
        isDisposing.value
    ) {
        return;
    }

    if (
        dispositionKind.value === 'destroy' &&
        dispositionEvidence.value.length === 0
    ) {
        toast.error('Tiêu hủy phải có ảnh/biên bản làm bằng chứng.');

        return;
    }

    isDisposing.value = true;
    const formData = new FormData();
    formData.append('disposition', dispositionKind.value);
    formData.append('reason', dispositionReason.value.trim());
    dispositionEvidence.value.forEach((file) =>
        formData.append('evidence[]', file),
    );

    try {
        await axios.post(
            `/api/warehouse/receiving-vouchers/${dispositionVoucher.value.id}/dispose`,
            formData,
        );
        dispositionVoucher.value.status =
            dispositionKind.value === 'destroy' ? 'destroyed' : 'returned';
        summary.value.pending_review = Math.max(
            0,
            (summary.value.pending_review ?? 1) - 1,
        );
        closeDisposition();
        toast.success('Đã ghi nhận xử lý lô không đạt.');
    } catch (error: any) {
        toast.error(
            error.response?.data?.message ?? 'Không thể ghi nhận xử lý lệnh.',
        );
    } finally {
        isDisposing.value = false;
    }
};

const addGrnLine = () => {
    grnForm.value.items.push(emptyLine());
};
const removeGrnLine = (index: number) => {
    if (grnForm.value.items.length === 1) {
        grnForm.value.items[0] = emptyLine();

        return;
    }

    grnForm.value.items.splice(index, 1);
};

const expiryAfter = (days: number | null | undefined) => {
    if (!days || days <= 0) {
        return '';
    }

    const date = new Date();
    date.setDate(date.getDate() + days);

    return date.toISOString().slice(0, 10);
};

const normalizeDate = (value: string) => {
    const trimmed = value.trim();
    const match = /^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$/.exec(trimmed);

    if (!match) {
        return trimmed;
    }

    return `${match[3]}-${match[2].padStart(2, '0')}-${match[1].padStart(2, '0')}`;
};

const normalizeDateTime = (value: string) => {
    const trimmed = value.trim();
    const match = /^(\d{1,2})[/-](\d{1,2})[/-](\d{4})(?:[ T]+)(\d{1,2}):(\d{2})$/.exec(trimmed);

    if (!match) {
        return trimmed.replace('T', ' ');
    }

    return `${match[3]}-${match[2].padStart(2, '0')}-${match[1].padStart(2, '0')} ${match[4].padStart(2, '0')}:${match[5]}`;
};

const displayDateValue = (value: string) => value.replace('T', ' ');

const setRowDatePickerRef = (key: string, element: unknown | null) => {
    rowDatePickers.value[key] = element as HTMLInputElement | null;
};

const openDatePicker = (picker: HTMLInputElement | null | undefined) => {
    if (!picker) {
        return;
    }

    const pickerWithShowPicker = picker as HTMLInputElement & {
        showPicker?: () => void;
    };

    try {
        if (typeof pickerWithShowPicker.showPicker === 'function') {
            pickerWithShowPicker.showPicker();

            return;
        }
    } catch {
        // Fall back to the native input click on browsers that restrict showPicker().
    }

    picker.click();
};

const openRowDatePicker = (key: string) => {
    openDatePicker(rowDatePickers.value[key]);
};

const onIngredientChange = (line: GrnLine) => {
    const ingredient = props.ingredients.find(
        (item) => item.id === line.ingredient_id,
    );

    if (!ingredient) {
        return;
    }

    if (!line.unit_label.trim()) {
        line.unit_label = ingredient.unit?.symbol ?? '';
    }

    line.unit_cost = Number(ingredient.average_cost || 0);

    if (!line.expiry_date) {
        line.expiry_date = expiryAfter(ingredient.default_shelf_life_days);
    }
};


const handleGrnFiles = (event: Event) => {
    const files = (event.target as HTMLInputElement).files;

    if (files) {
        const incoming = Array.from(files);
        const existingKeys = new Set(
            grnFiles.value.map((f) => `${f.name}-${f.size}-${f.lastModified}`),
        );
        const uniqueFiles = incoming.filter(
            (f) => !existingKeys.has(`${f.name}-${f.size}-${f.lastModified}`),
        );
        grnFiles.value = [...grnFiles.value, ...uniqueFiles];
    }
};
const removeGrnFile = (index: number) => {
    grnFiles.value.splice(index, 1);
};

const validateGrn = () => {
    const errors: string[] = [];

    if (!grnForm.value.verification_assigned_to) {
        errors.push('Phải phân công một nhân viên Kho Tổng đi kiểm kê và xác nhận.');
    }

    if (!grnForm.value.external_receipt_reason) {
        errors.push('Phải chọn lý do nhập ngoài.');
    }

    if (!grnForm.value.external_source_name.trim()) {
        errors.push('Phải ghi rõ bên giao hoặc nguồn bên ngoài; không nhập tên nhà cung cấp vào trường này.');
    }

    if (grnForm.value.items.length === 0) {
        errors.push('Phiếu phải có ít nhất một dòng nguyên liệu.');
    }

    grnForm.value.items.forEach((line, index) => {
        const lineNo = index + 1;

        if (!line.ingredient_id) {
            errors.push(`Dòng ${lineNo}: chưa chọn nguyên liệu.`);
        }

        if (line.actual_qty <= 0) {
            errors.push(`Dòng ${lineNo}: số lượng nhập phải lớn hơn 0.`);
        }

        if (line.actual_qty > 0 && !line.unit_label.trim()) {
            errors.push(`Dòng ${lineNo}: phải nhập đơn vị tính.`);
        }

        if (line.actual_qty > 0 && !line.lot_number.trim()) {
            errors.push(
                `Dòng ${lineNo}: phải nhập số lô để truy xuất nguồn gốc.`,
            );
        }

        if (line.manufactured_date && line.expiry_date) {
            if (line.expiry_date < line.manufactured_date) {
                errors.push(
                    `Dòng ${lineNo}: Hạn sử dụng (HSD: ${line.expiry_date}) không được nhỏ hơn Ngày sản xuất (NSX: ${line.manufactured_date}).`,
                );
            }
        }
    });

    grnErrors.value = errors;

    if (errors.length > 0) {
        requestAnimationFrame(() => {
            grnErrorSummary.value?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
        });
    }

    return errors.length === 0;
};

const submitGrn = async () => {
    if (isSubmittingGrn.value) {
        return;
    }

    if (!validateGrn()) {
        const issueCount = grnErrors.value.length;
        toast.error(
            issueCount === 1
                ? `Chưa thể tạo phiếu: ${grnErrors.value[0]}`
                : `Chưa thể tạo phiếu. Vui lòng bổ sung hoặc sửa ${issueCount} mục trong khung cảnh báo.`,
        );

        return;
    }

    isSubmittingGrn.value = true;
    const formData = new FormData();
    formData.append('received_at', normalizeDateTime(grnForm.value.received_at));

    formData.append('submit_for_review', '1');
    formData.append('external_receipt_reason', grnForm.value.external_receipt_reason);
    formData.append('external_source_name', grnForm.value.external_source_name.trim());
    formData.append('verification_assigned_to', String(grnForm.value.verification_assigned_to));
    formData.append('invoice_total_amount', String(totalReceiptValue.value));

    if (grnForm.value.external_reference.trim()) {
        formData.append('external_reference', grnForm.value.external_reference.trim());
    }

    if (grnForm.value.notes.trim()) {
        formData.append('notes', grnForm.value.notes.trim());
    }

    if (grnForm.value.receiving_dock.trim()) {
        formData.append('receiving_dock', grnForm.value.receiving_dock.trim());
    }

    grnForm.value.items.forEach((line, index) => {
        formData.append(
            `items[${index}][ingredient_id]`,
            String(line.ingredient_id),
        );
        formData.append(`items[${index}][unit_label]`, line.unit_label.trim());
        formData.append(`items[${index}][actual_qty]`, String(line.actual_qty));
        formData.append(`items[${index}][unit_cost]`, String(line.unit_cost));

        if (line.lot_number.trim()) {
            formData.append(
                `items[${index}][lot_number]`,
                line.lot_number.trim(),
            );
        }

        if (line.expiry_date) {
            formData.append(
                `items[${index}][expiry_date]`,
                normalizeDate(line.expiry_date),
            );
        }

        if (line.manufactured_date) {
            formData.append(
                `items[${index}][manufactured_date]`,
                normalizeDate(line.manufactured_date),
            );
        }

        if (line.location_id) {
            formData.append(
                `items[${index}][location_id]`,
                String(line.location_id),
            );
        }

    });
    grnFiles.value.forEach((file, index) => {
        formData.append('evidence[]', file);
        formData.append(`evidence_types[${index}]`, grnDocumentType.value);
    });

    try {
        const { data } = await axios.post(
            '/api/warehouse/receiving-vouchers',
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );
        vouchers.value.unshift({
            ...data.voucher,
        });
        summary.value.pending_review = (summary.value.pending_review ?? 0) + 1;
        summary.value.today = (summary.value.today ?? 0) + 1;
        toast.success(data.message ?? 'Đã tạo phiếu nhập ngoài.');
        showGrnForm.value = false;
        grnForm.value = {
            received_at: new Date(
                Date.now() - new Date().getTimezoneOffset() * 60000,
            )
                .toISOString()
                .slice(0, 16),
            external_receipt_reason: 'other',
            external_source_name: '',
            external_reference: '',
            verification_assigned_to: null,
            notes: '',
            receiving_dock: '',
            quality_status: 'pending',
            quality_notes: '',
            items: [emptyLine()],
        };
        grnFiles.value = [];
        grnDocumentType.value = 'external_record';
        grnErrors.value = [];
    } catch (error: any) {
        const responseErrors = error.response?.data?.errors;
        grnErrors.value = responseErrors
            ? Object.values(responseErrors).flat().map(String)
            : [
                  error.response?.data?.message ??
                      'Không thể tạo phiếu nhập ngoài.',
              ];
        toast.error(
            grnErrors.value[0] ?? 'Không thể tạo phiếu nhập ngoài.',
        );
    } finally {
        isSubmittingGrn.value = false;
    }
};

const evidenceUrl = (voucher: Voucher, path: string) =>
    path.startsWith('http://') || path.startsWith('https://')
        ? path
        : `/api/warehouse/receiving-vouchers/${voucher.id}/evidence/${Math.max(0, voucher.evidence_paths?.indexOf(path) ?? 0)}`;

const documentUrl = (voucher: Voucher, document: ReceivingDocument) =>
    `/api/warehouse/receiving-vouchers/${voucher.id}/documents/${document.id}`;

const documentTypeLabel = (type: string) =>
    ({
        external_record: 'Biên bản nguồn bên ngoài',
        invoice: 'Chứng từ cũ',
        delivery_note: 'Chứng từ cũ',
        qc: 'Biên bản QC',
        receiving_photo: 'Ảnh giao nhận',
        other: 'Chứng từ khác',
    })[type] || 'Chứng từ';
</script>

<template>
    <Head title="Nhập nguyên liệu vào Kho Tổng" />
    <div class="mx-auto w-full max-w-[1500px] space-y-5 p-4 sm:p-6">
        <section
            class="rounded-2xl border border-orange-100/90 bg-gradient-to-r from-orange-50/90 via-slate-50 to-amber-50/60 p-4 text-slate-900 shadow-xs backdrop-blur-md sm:p-5 dark:border-slate-800 dark:bg-black/80 dark:from-[#0b0804] dark:via-black dark:to-[#0b0804] dark:text-white"
        >
            <div
                class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
            >
                <div>
                    <Link
                        href="/inventory/central-warehouse"
                        class="mb-2 inline-flex items-center gap-1 text-xs text-orange-600 hover:text-orange-700 dark:text-orange-400 dark:hover:text-orange-300"
                        ><ArrowLeft class="size-3.5" /> Tổng quan Kho Tổng</Link
                    >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-orange-500 text-white shadow-sm shadow-orange-500/20 backdrop-blur-md dark:border dark:border-orange-500/30 dark:bg-orange-500/25 dark:text-orange-300"
                        >
                            <ClipboardCheck class="size-5" />
                        </div>
                        <div>
                            <h1
                                class="text-lg font-black tracking-tight text-slate-900 md:text-xl lg:text-2xl dark:text-white"
                            >
                                Nhập ngoài vào Kho Tổng
                            </h1>
                            <p
                                class="mt-0.5 text-xs leading-normal text-slate-600 dark:text-slate-400"
                            >
                                Ghi nhận nguồn bên ngoài, số lượng, lô, HSD và vị trí
                                trước khi cập nhật tồn Kho Tổng.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="canCreateReceiving"
                        class="h-9 gap-1.5 rounded-xl bg-orange-500 px-4 text-xs font-bold text-white shadow-xs hover:bg-orange-600 active:translate-y-0"
                        @click="showGrnForm = true"
                        ><Plus class="size-3.5" /> Nhập nguyên liệu vào Kho
                        Tổng</Button
                    ><Link href="/inventory/staff-portal"
                        ><Button
                            variant="outline"
                            class="h-9 gap-1.5 rounded-xl border-slate-200 bg-white/90 px-3.5 text-xs font-bold text-slate-800 shadow-2xs hover:bg-slate-100 dark:border-white/10 dark:bg-black/50 dark:text-slate-200 dark:hover:bg-white/10"
                            ><PackageCheck class="size-3.5" /> Cổng nhân
                            viên</Button
                        ></Link
                    ><Button
                        variant="outline"
                        class="h-9 gap-1.5 rounded-xl border-slate-200 bg-white/90 px-3.5 text-xs font-bold text-slate-800 shadow-2xs hover:bg-slate-100 dark:border-white/10 dark:bg-black/50 dark:text-slate-200 dark:hover:bg-white/10"
                        @click="reload"
                        ><RefreshCw class="size-3.5" /> Làm mới</Button
                    >
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <Card class="border-orange-500/20 bg-orange-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-orange-300 uppercase">
                        Chờ xử lý
                    </p>
                    <p class="mt-2 text-2xl font-black text-orange-100">
                        {{ pendingCount }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Chưa được hạch toán tồn
                    </p></CardContent
                ></Card
            >
            <Card class="border-emerald-500/20 bg-emerald-950/10"
                ><CardContent class="p-4"
                    ><p
                        class="text-[11px] font-bold text-emerald-300 uppercase"
                    >
                        Đã nhập kho
                    </p>
                    <p class="mt-2 text-2xl font-black text-emerald-100">
                        {{ summary.confirmed ?? 0 }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Đã tạo giao dịch và lô
                    </p></CardContent
                ></Card
            >
            <Card class="border-rose-500/20 bg-rose-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-rose-300 uppercase">
                        Phiếu lệch (phiếu cũ)
                    </p>
                    <p class="mt-2 text-2xl font-black text-rose-100">
                        {{ discrepancyCount }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Cần giải trình trước khi đóng
                    </p></CardContent
                ></Card
            >
            <Card class="border-amber-500/20 bg-amber-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-amber-300 uppercase">
                        Chênh lệch (phiếu cũ)
                    </p>
                    <p class="mt-2 text-2xl font-black text-amber-100">
                        {{ formatQuantity(summary.discrepancy_quantity) }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Tổng số lượng lệch
                    </p></CardContent
                ></Card
            >
            <Card class="border-sky-500/20 bg-sky-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-sky-300 uppercase">
                        Tồn hiện tại
                    </p>
                    <p class="mt-2 text-2xl font-black text-sky-100">
                        {{ formatQuantity(inventory.on_hand_quantity) }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Sau các lần nhập đã xác nhận
                    </p></CardContent
                ></Card
            >
            <Card class="border-indigo-500/20 bg-indigo-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-indigo-300 uppercase">
                        Thiếu vị trí cất
                    </p>
                    <p class="mt-2 text-2xl font-black text-indigo-100">
                        {{ missingPutawayCount }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Phiếu cần bổ sung truy vết
                    </p></CardContent
                ></Card
            >
        </section>

        <section
            v-if="pendingCount > 0 || missingPutawayCount > 0"
            class="grid gap-3 lg:grid-cols-2"
        >
            <div
                v-if="pendingCount > 0"
                class="flex items-start gap-3 rounded-2xl border border-orange-400/20 bg-orange-500/5 p-4 text-sm"
            >
                <ShieldAlert class="mt-0.5 size-5 shrink-0 text-orange-300" />
                <div>
                    <p class="font-bold text-orange-100">
                        Có {{ pendingCount }} phiếu chưa được xác minh
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Chỉ sau khi xác minh, hệ thống mới ghi giao dịch nhập,
                        cập nhật tồn bình quân và tạo lô.
                    </p>
                </div>
            </div>
            <div
                v-if="missingPutawayCount > 0"
                class="flex items-start gap-3 rounded-2xl border border-indigo-400/20 bg-indigo-500/5 p-4 text-sm"
            >
                <MapPin class="mt-0.5 size-5 shrink-0 text-indigo-300" />
                <div>
                    <p class="font-bold text-indigo-100">
                        {{ missingPutawayCount }} phiếu chưa có vị trí cất hàng
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Không nên đóng phiếu nếu chưa biết lô đang nằm ở
                        khu/kệ/ngăn nào.
                    </p>
                </div>
            </div>
        </section>

        <Card class="border-border shadow-sm">
            <CardHeader class="border-b border-border bg-muted/20 py-4"
                ><div
                    class="flex flex-col justify-between gap-3 lg:flex-row lg:items-center"
                >
                    <div>
                        <CardTitle class="flex items-center gap-2 text-base"
                            ><Warehouse class="size-5 text-orange-300" /> Phiếu
                            nhập ngoài tại
                            {{ centralBranch?.name || 'Kho Tổng' }}</CardTitle
                        ><CardDescription class="mt-1 text-xs"
                            >Mở chi tiết để kiểm tra nguồn nhập, dòng nguyên liệu,
                            lô và vị trí trước khi xác minh.</CardDescription
                        >
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <div class="relative min-w-[220px]">
                            <Search
                                class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                            /><Input
                                v-model="search"
                                class="h-9 pl-9 text-xs"
                                placeholder="Tìm mã phiếu, nguồn nhập hoặc người nhập..."
                            />
                        </div>
                        <select
                            v-model="filter"
                            class="h-9 rounded-md border border-input bg-background px-3 text-xs text-foreground"
                        >
                            <option value="pending">Chờ xử lý</option>
                            <option value="discrepancy">Có chênh lệch (phiếu cũ)</option>
                            <option value="confirmed">Đã nhập kho</option>
                            <option value="all">Tất cả phiếu</option>
                        </select>
                    </div>
                </div></CardHeader
            >
            <CardContent class="p-0"
                ><div class="overflow-x-auto">
                    <table class="w-full min-w-[960px] text-left text-xs">
                        <thead
                            class="border-b border-border bg-muted/50 text-muted-foreground"
                        >
                            <tr>
                                <th class="w-8 p-3"></th>
                                <th class="p-3 whitespace-nowrap">Mã phiếu</th>
                                <th class="p-3">Người nhập / kiểm nhận</th>
                                <th class="p-3 text-right whitespace-nowrap">Dòng hàng</th>
                                <th class="p-3 text-right whitespace-nowrap">Số lượng nhập</th>
                                <th class="p-3 text-right whitespace-nowrap">Tổng giá trị</th>
                                <th class="p-3 whitespace-nowrap">Trạng thái</th>
                                <th class="p-3 text-right whitespace-nowrap">Xử lý</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="filteredVouchers.length === 0">
                                <td
                                    colspan="8"
                                    class="p-12 text-center text-muted-foreground"
                                >
                                    Không có phiếu phù hợp với bộ lọc hiện tại.
                                </td>
                            </tr>
                            <template
                                v-for="voucher in filteredVouchers"
                                :key="voucher.id"
                                ><tr
                                    class="cursor-pointer transition hover:bg-muted/20"
                                    @click="toggleDetails(voucher.id)"
                                >
                                    <td class="p-3 text-muted-foreground">
                                        <ChevronDown
                                            v-if="expandedId === voucher.id"
                                            class="size-4"
                                        /><ChevronRight v-else class="size-4" />
                                    </td>
                                    <td class="p-3">
                                        <p
                                            class="font-mono font-bold text-orange-300"
                                        >
                                            {{ voucher.voucher_code }}
                                        </p>
                                    </td>
                                    <td class="p-3">
                                        <p class="text-foreground">
                                            {{
                                                voucher.received_by?.name || '-'
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-[10px] text-muted-foreground"
                                        >
                                            Kiểm nhận:
                                            {{ voucher.verified_by?.name || 'Chưa xác minh' }}
                                        </p>
                                        <p
                                            class="mt-1 text-[10px] text-muted-foreground"
                                        >
                                            {{
                                                formatDate(voucher.received_at)
                                            }}
                                        </p>
                                        <p class="mt-1 text-[10px] text-indigo-300">
                                            Kiểm kê: {{ voucher.verification_assigned_to?.name || 'Chưa phân công' }}
                                        </p>
                                    </td>
                                    <td
                                        class="p-3 text-right font-semibold text-foreground"
                                    >
                                        {{ voucher.items.length }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-bold text-sky-300"
                                    >
                                        {{
                                            formatQuantity(
                                                voucher.total_actual_qty,
                                            )
                                        }}
                                    </td>
                                    <td class="p-3 text-right font-bold text-orange-300">
                                        {{ formatCurrency(voucherTotal(voucher)) }}
                                    </td>
                                    <td class="p-3">
                                        <span
                                            class="rounded-full border px-2 py-1 text-[10px] font-semibold"
                                            :class="statusClass(voucher.status)"
                                            >{{
                                                statusLabel(voucher.status, !!voucher.external_receipt_reason)
                                            }}</span
                                        >
                                    </td>
                                    <td class="p-3 text-right" @click.stop>
                                        <div class="flex justify-end gap-1.5">
                                            <Button
                                                v-if="
                                                    canReview(voucher) &&
                                                    [
                                                        'draft',
                                                        'discrepancy',
                                                        'pending_review',
                                                    ].includes(voucher.status)
                                                "
                                                size="sm"
                                                class="h-7 gap-1 bg-orange-600 text-[10px] text-white hover:bg-orange-700"
                                                :disabled="
                                                    isProcessing === voucher.id
                                                "
                                                @click="openConfirm(voucher)"
                                                ><CheckCircle2 class="size-3" />
                                                Xác minh</Button
                                            ><Button
                                                v-if="
                                                    canReview(voucher) &&
                                                    [
                                                        'draft',
                                                        'discrepancy',
                                                        'pending_review',
                                                    ].includes(voucher.status)
                                                "
                                                size="sm"
                                                variant="outline"
                                                class="h-7 border-red-400/30 text-[10px] text-red-300 hover:bg-red-500/10"
                                                @click="openReject(voucher)"
                                                >Từ chối</Button
                                            ><Button
                                                size="icon"
                                                variant="ghost"
                                                class="size-8"
                                                title="Xem chi tiết"
                                                @click="
                                                    toggleDetails(voucher.id)
                                                "
                                                ><FileText class="size-4"
                                            /></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-if="expandedId === voucher.id"
                                    class="bg-muted/10"
                                >
                                    <td colspan="8" class="p-3 sm:p-4">
                                        <div
                                            class="grid gap-4 min-w-0 lg:grid-cols-12"
                                        >
                                            <div class="space-y-3 min-w-0 lg:col-span-4 xl:col-span-4">
                                                <div
                                                    class="rounded-xl border border-border bg-background/50 p-4"
                                                >
                                                    <p
                                                        class="mb-3 flex items-center gap-2 text-xs font-bold text-foreground"
                                                    >
                                                        <FileText
                                                            class="size-4 text-orange-300"
                                                        />
                                                        Thông tin chứng từ
                                                    </p>
                                                    <dl
                                                        class="space-y-2 text-xs"
                                                    >
                                                        <div
                                                            class="flex justify-between gap-3"
                                                        >
                                                            <dt
                                                                class="text-muted-foreground"
                                                            >
                                                                Người nhập
                                                            </dt>
                                                            <dd
                                                                class="text-right text-foreground"
                                                            >
                                                                {{
                                                                    voucher
                                                                        .received_by
                                                                        ?.name ||
                                                                    '-'
                                                                }}
                                                            </dd>
                                                        </div>
                                                        <div
                                                            class="flex justify-between gap-3"
                                                        >
                                                            <dt
                                                                class="text-muted-foreground"
                                                            >
                                                                Người kiểm nhận
                                                            </dt>
                                                            <dd
                                                                class="text-right text-foreground"
                                                            >
                                                                {{
                                                                    voucher
                                                                        .verified_by
                                                                        ?.name ||
                                                                    'Chưa xác minh'
                                                                }}
                                                            </dd>
                                                        </div>
                                                        <div
                                                            class="flex justify-between gap-3"
                                                        >
                                                            <dt
                                                                class="text-muted-foreground"
                                                            >
                                                                Nhận lúc
                                                            </dt>
                                                            <dd
                                                                class="text-right text-foreground"
                                                            >
                                                                {{
                                                                    formatDate(
                                                                        voucher.received_at,
                                                                    )
                                                                }}
                                                            </dd>
                                                        </div>
                                                        <div
                                                            class="flex justify-between gap-3"
                                                        >
                                                            <dt
                                                                class="text-muted-foreground"
                                                            >
                                                                Tổng số lượng nhập
                                                            </dt>
                                                            <dd
                                                                class="text-right font-bold text-sky-300"
                                                            >
                                                                {{
                                                                    formatQuantity(
                                                                        voucher.total_actual_qty,
                                                                    )
                                                                }}
                                                            </dd>
                                                        </div>
                                                        <div
                                                            class="flex justify-between gap-3"
                                                        >
                                                            <dt class="text-muted-foreground">Tổng hóa đơn / giá trị</dt>
                                                            <dd class="text-right font-bold text-orange-300">
                                                                {{ formatCurrency(voucherTotal(voucher)) }}
                                                                <span class="ml-2 text-indigo-300">· Kiểm kê: {{ voucher.verification_assigned_to?.name || 'Chưa phân công' }}</span>
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                    <div
                                                        class="mt-3 grid grid-cols-2 gap-2 border-t border-border pt-3 text-[11px]"
                                                    >
                                                        <p>
                                                            <span class="text-muted-foreground">Lý do nhập:</span>
                                                            {{ externalReceiptReasonLabel(voucher.external_receipt_reason) }}
                                                        </p>
                                                        <p>
                                                            <span class="text-muted-foreground">Bên giao / nguồn:</span>
                                                            {{ voucher.external_source_name || '-' }}
                                                        </p>
                                                        <p>
                                                            <span class="text-muted-foreground">Tham chiếu:</span>
                                                            {{ voucher.external_reference || '-' }}
                                                        </p>
                                                        <p class="col-span-2">
                                                            <span
                                                                class="text-muted-foreground"
                                                                >Chất
                                                                lượng:</span
                                                            >
                                                            {{
                                                                qualityLabel(
                                                                    voucher.quality_status,
                                                                )
                                                            }}
                                                        </p>
                                                        <p>
                                                            <span
                                                                class="text-muted-foreground"
                                                                >Cửa nhận:</span
                                                            >
                                                            {{
                                                                voucher.receiving_dock ||
                                                                '-'
                                                            }}
                                                        </p>
                                                        <p>
                                                            <span
                                                                class="text-muted-foreground"
                                                                >Bắt đầu
                                                                cất:</span
                                                            >
                                                            {{
                                                                voucher.putaway_started_at
                                                                    ? formatDate(
                                                                          voucher.putaway_started_at,
                                                                      )
                                                                    : 'Chưa có'
                                                            }}
                                                        </p>
                                                        <p>
                                                            <span
                                                                class="text-muted-foreground"
                                                                >Hoàn tất
                                                                cất:</span
                                                            >
                                                            {{
                                                                voucher.putaway_completed_at
                                                                    ? formatDate(
                                                                          voucher.putaway_completed_at,
                                                                      )
                                                                    : 'Chưa hoàn tất'
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div
                                                    v-if="
                                                        voucher.notes ||
                                                        voucher.quality_notes ||
                                                        voucher.rejection_reason
                                                    "
                                                    class="rounded-xl border border-amber-400/20 bg-amber-500/5 p-4 text-xs"
                                                >
                                                    <p
                                                        class="mb-2 font-bold text-amber-200"
                                                    >
                                                        Ghi chú / giải trình
                                                    </p>
                                                    <p
                                                        v-if="voucher.notes"
                                                        class="text-muted-foreground"
                                                    >
                                                        {{ voucher.notes }}
                                                    </p>
                                                    <p
                                                        v-if="
                                                            voucher.quality_notes
                                                        "
                                                        class="mt-2 text-indigo-200"
                                                    >
                                                        Chất lượng:
                                                        {{
                                                            voucher.quality_notes
                                                        }}
                                                    </p>
                                                    <p
                                                        v-if="
                                                            voucher.rejection_reason
                                                        "
                                                        class="mt-2 text-red-300"
                                                    >
                                                        Từ chối:
                                                        {{
                                                            voucher.rejection_reason
                                                        }}
                                                    </p>
                                                </div>
                                                <div
                                                    v-if="
                                                        voucher.documents
                                                            ?.length
                                                    "
                                                    class="rounded-xl border border-indigo-400/20 bg-indigo-500/5 p-4 text-xs"
                                                >
                                                    <p
                                                        class="mb-2 flex items-center gap-2 font-bold text-foreground"
                                                    >
                                                        <FileText
                                                            class="size-4 text-indigo-300 shrink-0"
                                                        />
                                                        Chứng từ đã phân loại
                                                    </p>
                                                    <div class="space-y-1.5">
                                                        <a
                                                            v-for="document in voucher.documents"
                                                            :key="document.id"
                                                            :href="
                                                                documentUrl(
                                                                    voucher,
                                                                    document,
                                                                )
                                                            "
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            class="flex items-start gap-1.5 rounded-lg border border-indigo-400/10 bg-indigo-500/10 p-2 text-indigo-300 hover:bg-indigo-500/20 hover:text-indigo-200 transition"
                                                        >
                                                            <FileText class="size-3.5 mt-0.5 shrink-0" />
                                                            <span class="break-all leading-relaxed">
                                                                <span class="font-medium">{{
                                                                    documentTypeLabel(
                                                                        document.document_type,
                                                                    )
                                                                }}</span>
                                                                <span class="text-muted-foreground"> · </span>
                                                                <span>{{
                                                                    document.original_name
                                                                }}</span>
                                                            </span>
                                                        </a>
                                                    </div>
                                                </div>
                                                <div
                                                    v-if="
                                                        voucher.evidence_paths
                                                            ?.length
                                                    "
                                                    class="rounded-xl border border-border bg-background/50 p-4 text-xs"
                                                >
                                                    <p
                                                        class="mb-2 flex items-center gap-2 font-bold text-foreground"
                                                    >
                                                        <Upload
                                                            class="size-4 text-indigo-300 shrink-0"
                                                        />
                                                        Chứng từ đính kèm
                                                    </p>
                                                    <div class="space-y-1.5">
                                                        <a
                                                            v-for="(
                                                                path, index
                                                            ) in voucher.evidence_paths"
                                                            :key="path"
                                                            :href="
                                                                evidenceUrl(
                                                                    voucher,
                                                                    path,
                                                                )
                                                            "
                                                            target="_blank"
                                                            class="flex items-start gap-1.5 rounded-lg border border-border bg-muted/20 p-2 text-indigo-300 hover:bg-muted/40 hover:text-indigo-200 transition"
                                                        >
                                                            <Upload class="size-3.5 mt-0.5 shrink-0" />
                                                            <span class="break-all leading-relaxed">Tệp {{ index + 1 }} · {{ path }}</span>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                class="min-w-0 rounded-xl border border-border bg-background/50 p-4 lg:col-span-8 xl:col-span-8"
                                            >
                                                <div
                                                    class="mb-3 flex items-center justify-between gap-3"
                                                >
                                                    <p
                                                        class="flex items-center gap-2 text-xs font-bold text-foreground"
                                                    >
                                                        <PackageCheck
                                                            class="size-4 text-orange-300"
                                                        />
                                                        Chi tiết từng dòng hàng
                                                    </p>
                                                    <span
                                                        class="text-[10px] text-muted-foreground"
                                                        >{{
                                                            voucher.items.length
                                                        }}
                                                        dòng</span
                                                    >
                                                </div>
                                                <div class="overflow-x-auto min-w-0">
                                                    <table
                                                        class="w-full min-w-[640px] text-xs"
                                                    >
                                                        <thead
                                                            class="border-b border-border text-[10px] text-muted-foreground"
                                                        >
                                                            <tr>
                                                                <th
                                                                    class="p-2 text-left"
                                                                >
                                                                    Nguyên liệu
                                                                </th>
                                                                <th class="p-2 text-left whitespace-nowrap">ĐVT</th>
                                                                <th class="p-2 text-right whitespace-nowrap">SL nhập</th>
                                                                <th class="p-2 text-right whitespace-nowrap">Đơn giá</th>
                                                                <th class="p-2 text-right whitespace-nowrap">Thành tiền</th>
                                                                <th class="p-2 text-left whitespace-nowrap">
                                                                    Lô / HSD
                                                                </th>
                                                                <th class="p-2 text-left whitespace-nowrap">
                                                                    Vị trí
                                                                </th>
                                                                <th class="p-2 text-left whitespace-nowrap">
                                                                    Trạng thái
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody
                                                            class="divide-y divide-border"
                                                        >
                                                            <tr
                                                                v-for="item in voucher.items"
                                                                :key="item.id"
                                                            >
                                                                <td class="p-2">
                                                                    <p
                                                                        class="font-semibold text-foreground break-words"
                                                                    >
                                                                        {{
                                                                            item
                                                                                .ingredient
                                                                                ?.name ||
                                                                            `Nguyên liệu #${item.ingredient_id}`
                                                                        }}
                                                                    </p>
                                                                </td>
                                                                <td class="p-2 text-foreground whitespace-nowrap">
                                                                    {{ item.unit_label || item.ingredient?.unit?.symbol || 'đv' }}
                                                                </td>
                                                                <td class="p-2 text-right font-bold text-sky-300 whitespace-nowrap">
                                                                    {{
                                                                        formatQuantity(
                                                                            item.actual_qty,
                                                                        )
                                                                    }}
                                                                </td>
                                                                <td class="p-2 text-right text-foreground whitespace-nowrap">
                                                                    {{ formatCurrency(item.unit_cost) }}
                                                                </td>
                                                                <td class="p-2 text-right font-bold text-orange-300 whitespace-nowrap">
                                                                    {{ formatCurrency(voucherItemTotal(item)) }}
                                                                </td>
                                                                <td class="p-2 whitespace-nowrap">
                                                                    <p
                                                                        class="font-mono text-foreground"
                                                                    >
                                                                        {{
                                                                            item.lot_number ||
                                                                            item
                                                                                .batch
                                                                                ?.batch_number ||
                                                                            'Tự sinh khi xác minh'
                                                                        }}
                                                                    </p>
                                                                    <p
                                                                        class="mt-0.5 text-[10px] text-muted-foreground"
                                                                    >
                                                                        HSD:
                                                                        {{
                                                                            formatDateOnly(
                                                                                item.expiry_date,
                                                                            )
                                                                        }}
                                                                    </p>
                                                                </td>
                                                                <td class="p-2 whitespace-nowrap">
                                                                    <span
                                                                        v-if="
                                                                            item
                                                                                .location
                                                                                ?.location_code
                                                                        "
                                                                        class="inline-flex items-center gap-1 text-indigo-300"
                                                                        ><MapPin
                                                                            class="size-3"
                                                                        />{{
                                                                            item
                                                                                .location
                                                                                .location_code
                                                                        }}</span
                                                                    ><span
                                                                        v-else
                                                                        class="text-rose-300"
                                                                        >Chưa
                                                                        gán</span
                                                                    >
                                                                </td>
                                                                <td class="p-2 whitespace-nowrap">
                                                                    <span class="font-semibold text-emerald-300">Đã ghi nhận</span>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4"
                                        >
                                            <div
                                                class="flex flex-wrap gap-3 text-[11px] text-muted-foreground"
                                            >
                                                <span
                                                    class="inline-flex items-center gap-1.5"
                                                    ><CalendarDays
                                                        class="size-3.5 text-orange-300"
                                                    />
                                                    Kiểm đủ số lượng và chất
                                                    lượng</span
                                                ><span
                                                    class="inline-flex items-center gap-1.5"
                                                    ><MapPin
                                                        class="size-3.5 text-indigo-300"
                                                    />
                                                    Gắn vị trí để truy vết</span
                                                >
                                            </div>
                                            <Button
                                                v-if="
                                                    canReview(voucher) &&
                                                    [
                                                        'draft',
                                                        'discrepancy',
                                                        'pending_review',
                                                    ].includes(voucher.status)
                                                "
                                                class="gap-1.5 bg-orange-600 text-white hover:bg-orange-700"
                                                @click="openConfirm(voucher)"
                                                ><Check class="size-4" /> Xác
                                                minh & nhập kho</Button
                                            >
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div></CardContent
            >
        </Card>
    </div>

    <Teleport to="body">
        <div
            v-if="confirming"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm"
            @click.self="closeConfirm"
        >
            <div
                class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-3xl border border-border bg-background p-5 shadow-2xl sm:p-6"
            >
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-orange-400 uppercase"
                        >
                            Xác minh phiếu nhập ngoài
                        </p>
                        <h2 class="mt-1 text-xl font-black">
                            {{ confirming.voucher_code }} · Cập nhật Kho Tổng
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Sau khi xác nhận, hệ thống ghi giao dịch nhập ngoài,
                            cập nhật giá vốn quy đổi và tạo lô hàng. Không phát sinh
                            đối chiếu nhà cung cấp hay công nợ mua hàng.
                        </p>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeConfirm"
                        ><X class="size-4"
                    /></Button>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-xl bg-muted/30 p-3">
                        <p class="text-muted-foreground">Số dòng nguyên liệu</p>
                        <strong class="mt-1 block text-foreground">{{
                            confirming.items.length
                        }}</strong>
                    </div>
                    <div class="rounded-xl bg-sky-500/10 p-3">
                        <p class="text-sky-200/70">Tổng số lượng nhập</p>
                        <strong class="mt-1 block text-sky-200">{{
                            formatQuantity(confirming.total_actual_qty)
                        }}</strong>
                    </div>
                </div>
                <form class="mt-5 space-y-4" @submit.prevent="confirmVoucher">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label>Kết quả kiểm tra chất lượng</Label>
                            <select
                                v-model="confirmQualityStatus"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="passed">
                                    Đạt — cho phép sử dụng
                                </option>
                                <option value="conditional">
                                    Đạt có điều kiện — khóa lô chờ xử lý
                                </option>
                                <option value="failed">
                                    Không đạt — không nhập kho
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>Ghi chú chất lượng</Label>
                            <Input
                                v-model="confirmQualityNotes"
                                placeholder="Bao bì, ngoại quan, chất lượng..."
                            />
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label
                            >Bằng chứng xử lý chất lượng (nếu không đạt)</Label
                        >
                        <input
                            type="file"
                            multiple
                            accept="image/*,.pdf"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-xs"
                            @change="handleConfirmEvidence"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Ghi chú xác minh</Label
                        ><textarea
                            v-model="confirmNotes"
                            rows="4"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Ghi nhận tình trạng thực tế, kết quả kiểm tra và hướng xử lý nếu có..."
                        />
                    </div>
                    <p
                        v-if="confirmError"
                        class="rounded-lg bg-rose-500/10 p-3 text-xs text-rose-300"
                    >
                        {{ confirmError }}
                    </p>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeConfirm"
                            >Hủy</Button
                        ><Button
                            type="submit"
                            class="gap-1.5 bg-orange-600 text-white hover:bg-orange-700"
                            :disabled="isProcessing === confirming.id"
                            ><CheckCircle2 class="size-4" />
                            {{
                                isProcessing === confirming.id
                                    ? 'Đang hạch toán...'
                                    : 'Xác minh & nhập kho'
                            }}</Button
                        >
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <Teleport to="body">
        <div
            v-if="rejecting"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
            @click.self="closeReject"
        >
            <div
                class="w-full max-w-xl rounded-3xl border border-red-400/25 bg-background p-6 shadow-2xl"
            >
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-red-400 uppercase"
                        >
                            Từ chối phiếu nhập
                        </p>
                        <h2 class="mt-1 text-xl font-black">
                            {{ rejecting.voucher_code }}
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Phiếu bị từ chối sẽ không tạo giao dịch, lô hàng
                            hoặc cộng tồn kho.
                        </p>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeReject"
                        ><X class="size-4"
                    /></Button>
                </div>
                <form class="space-y-4" @submit.prevent="rejectVoucher">
                    <div class="flex flex-col gap-1.5">
                        <Label>Lý do từ chối / yêu cầu bổ sung</Label>
                        <textarea
                            v-model="rejectReason"
                            rows="5"
                            required
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Thiếu biên bản nguồn, sai số lô, thông tin bàn giao chưa đầy đủ..."
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeReject"
                            >Hủy</Button
                        >
                        <Button
                            type="submit"
                            class="bg-red-600 text-white hover:bg-red-700"
                            :disabled="isRejecting"
                            >{{
                                isRejecting ? 'Đang lưu...' : 'Từ chối phiếu'
                            }}</Button
                        >
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <Teleport to="body">
        <div
            v-if="dispositionVoucher"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
            @click.self="closeDisposition"
        >
            <div
                class="w-full max-w-xl rounded-3xl border border-rose-400/25 bg-background p-6 shadow-2xl"
            >
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-rose-400 uppercase"
                        >
                            Xử lý lô không đạt
                        </p>
                        <h2 class="mt-1 text-xl font-black">
                            {{ dispositionVoucher.voucher_code }}
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Lô này chưa được cộng vào tồn kho.
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="closeDisposition"
                        ><X class="size-4"
                    /></Button>
                </div>
                <form class="space-y-4" @submit.prevent="disposeReceiving">
                    <div class="flex flex-col gap-1.5">
                        <Label>Hướng xử lý</Label>
                        <select
                            v-model="dispositionKind"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="return_external">
                                Trả lại bên giao bên ngoài
                            </option>
                            <option value="destroy">Tiêu hủy</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Lý do / biên bản</Label>
                        <textarea
                            v-model="dispositionReason"
                            rows="4"
                            required
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Nêu rõ lý do, số biên bản, người bàn giao..."
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label
                            >Bằng chứng
                            {{
                                dispositionKind === 'destroy'
                                    ? '(bắt buộc)'
                                    : '(khuyến nghị)'
                            }}</Label
                        >
                        <input
                            type="file"
                            multiple
                            accept="image/*,.pdf"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-xs"
                            @change="handleDispositionEvidence"
                        />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeDisposition"
                            >Hủy</Button
                        >
                        <Button
                            type="submit"
                            class="bg-rose-600 text-white hover:bg-rose-700"
                            :disabled="isDisposing"
                            >Ghi nhận xử lý</Button
                        >
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <Teleport to="body">
        <div
            v-if="showGrnForm"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 p-3 backdrop-blur-sm sm:p-5"
            @click.self="showGrnForm = false"
        >
            <div
                class="max-h-[95vh] w-full max-w-[1400px] overflow-y-auto rounded-3xl border border-border bg-background p-5 shadow-2xl sm:p-7"
            >
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-orange-400 uppercase"
                        >
                            Phiếu nhập ngoài mới
                        </p>
                        <h2 class="mt-1 text-2xl font-black">
                            Tạo phiếu nhập nguyên liệu vào Kho Tổng
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Đây là luồng nhập từ bên ngoài, độc lập với nhà cung cấp và
                            đơn mua hàng. Phiếu chỉ ghi nhận số lượng thực tế sau khi
                            được Trưởng kho xác minh.
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="showGrnForm = false"
                        ><X class="size-4"
                    /></Button>
                </div>
                <form class="space-y-6" @submit.prevent="submitGrn">
                    <!-- Nhóm 1: Nguồn nhập ngoài và tiếp nhận -->
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="flex flex-col gap-1.5 sm:col-span-1 lg:col-span-1">
                            <Label class="text-xs font-bold text-foreground">Thời điểm nhận</Label>
                            <div class="relative">
                                <Input
                                    :model-value="displayDateValue(grnForm.received_at)"
                                    type="text"
                                    readonly
                                    class="cursor-default pr-10 text-xs font-medium"
                                    placeholder="YYYY-MM-DD HH:mm"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 z-10 flex w-9 items-center justify-center text-muted-foreground transition-colors hover:text-orange-400"
                                    title="Chọn thời điểm nhận"
                                    aria-label="Chọn thời điểm nhận"
                                    @click="openDatePicker(receivedAtPicker)"
                                >
                                    <CalendarDays class="size-4" />
                                </button>
                                <input
                                    ref="receivedAtPicker"
                                    v-model="grnForm.received_at"
                                    type="datetime-local"
                                    tabindex="-1"
                                    aria-hidden="true"
                                    class="pointer-events-none absolute top-0 right-0 h-9 w-9 opacity-0"
                                />
                            </div>
                        </div>

                        <div class="flex flex-col gap-1.5 sm:col-span-1 lg:col-span-2">
                            <Label class="text-xs font-bold text-foreground">Bên giao / nguồn bên ngoài *</Label>
                            <Input
                                v-model="grnForm.external_source_name"
                                class="text-xs"
                                placeholder="Ví dụ: Đơn vị tặng, đối tác, người giao..."
                            />
                        </div>

                        <div class="flex flex-col gap-1.5 sm:col-span-1 lg:col-span-1">
                            <Label class="text-xs font-bold text-foreground">Lý do nhập ngoài *</Label>
                            <select
                                v-model="grnForm.external_receipt_reason"
                                class="h-9 rounded-md border border-input bg-background px-3 text-xs text-foreground"
                            >
                                <option value="external_donation">Biếu tặng / hỗ trợ</option>
                                <option value="external_return">Tiếp nhận hoàn trả</option>
                                <option value="other">Nhập ngoài khác</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1.5 sm:col-span-1 lg:col-span-2">
                            <Label class="text-xs font-bold text-foreground">Số tham chiếu / Giấy tờ</Label>
                            <Input
                                v-model="grnForm.external_reference"
                                class="text-xs font-mono"
                                placeholder="Số biên bản / giấy tờ nếu có"
                            />
                        </div>

                        <div class="flex flex-col gap-1.5 sm:col-span-1 lg:col-span-2">
                            <Label class="text-xs font-bold text-foreground">Cửa / khu tiếp nhận</Label>
                            <Input
                                v-model="grnForm.receiving_dock"
                                class="text-xs"
                                placeholder="Cửa nhập số 1"
                            />
                        </div>
                    </div>

                    <!-- Nhóm 2: Người lập và người kiểm nhận -->
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-orange-400/20 bg-orange-500/5 p-3">
                            <p class="text-[10px] font-bold tracking-wide text-orange-300 uppercase">Người nhập</p>
                            <p class="mt-1 text-sm font-bold text-foreground">{{ currentUserName }}</p>
                            <p class="mt-1 text-[11px] text-muted-foreground">Tài khoản đang lập phiếu sẽ được ghi tự động.</p>
                        </div>
                        <div class="rounded-xl border border-indigo-400/20 bg-indigo-500/5 p-3">
                            <p class="text-[10px] font-bold tracking-wide text-indigo-300 uppercase">Phân công người kiểm kê *</p>
                            <select
                                v-model="grnForm.verification_assigned_to"
                                class="mt-1 h-9 w-full rounded-lg border border-input bg-background px-2.5 text-xs text-foreground"
                            >
                                <option :value="null">Chọn nhân viên Kho Tổng</option>
                                <option
                                    v-for="staff in warehouseStaff"
                                    :key="staff.id"
                                    :value="staff.id"
                                >
                                    {{ staff.name }}{{ staff.job_title ? ` · ${staff.job_title}` : '' }}
                                </option>
                            </select>
                            <p class="mt-1 text-[11px] text-muted-foreground">Nhân viên được chọn sẽ kiểm đếm độc lập; phiếu chưa được cộng tồn kho trước khi họ xác nhận.</p>
                        </div>
                        <div class="hidden rounded-xl border border-indigo-400/20 bg-indigo-500/5 p-3">
                            <p class="text-[10px] font-bold tracking-wide text-indigo-300 uppercase">Người kiểm nhận</p>
                            <p class="mt-1 text-sm font-bold text-foreground">Trưởng kho xác minh</p>
                            <p class="mt-1 text-[11px] text-muted-foreground">Hệ thống tự ghi nhận người thực hiện lúc xác minh.</p>
                        </div>
                    </div>

                    <!-- Nhóm 3: Bảng Dòng Hàng Thực Nhận -->
                    <div class="rounded-2xl border border-border bg-card shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/30 p-4">
                            <div>
                                <p class="flex items-center gap-2 text-sm font-bold text-foreground">
                                    <PackageCheck class="size-4 text-orange-400" />
                                    Dòng nguyên liệu nhập
                                </p>
                                <p class="mt-0.5 text-[11px] text-muted-foreground">
                                    Mỗi dòng là một nguyên liệu/lô nhập từ bên ngoài; số lượng bên dưới là số thực tế đưa vào Kho Tổng. Nếu chưa biết vị trí, hệ thống sẽ tạo task cất hàng sau khi duyệt.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="gap-1.5 rounded-xl border-border text-xs font-semibold hover:bg-accent"
                                @click="addGrnLine"
                            >
                                <Plus class="size-3.5" /> Thêm dòng
                            </Button>
                        </div>

                        <div class="overflow-x-auto p-3">
                            <table class="w-full min-w-[1080px] text-xs">
                                <thead class="text-[11px] font-bold text-muted-foreground">
                                    <tr class="border-b border-border">
                                        <th class="w-56 min-w-[200px] p-2 text-left">Nguyên liệu</th>
                                        <th class="w-24 min-w-[85px] p-2 text-left">ĐVT (nhập)</th>
                                        <th class="w-24 min-w-[85px] p-2 text-right">Số lượng</th>
                                        <th class="w-28 min-w-[100px] p-2 text-right">Đơn giá (đ)</th>
                                        <th class="w-28 min-w-[100px] p-2 text-right">Thành tiền (đ)</th>
                                        <th class="w-28 min-w-[95px] p-2 text-left">Số lô *</th>
                                        <th class="w-32 min-w-[120px] p-2 text-left">NSX</th>
                                        <th class="w-32 min-w-[120px] p-2 text-left">HSD</th>
                                        <th class="w-36 min-w-[130px] p-2 text-left">Vị trí cất</th>
                                        <th class="w-10 p-2 text-center"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="(line, index) in grnForm.items"
                                        :key="index"
                                        class="hover:bg-muted/20"
                                    >
                                        <td class="p-2">
                                            <select
                                                v-model="line.ingredient_id"
                                                class="h-9 w-full rounded-lg border border-input bg-background px-2.5 text-xs focus:ring-1 focus:ring-primary focus:outline-none"
                                                @change="onIngredientChange(line)"
                                            >
                                                <option :value="null">Chọn nguyên liệu</option>
                                                <option
                                                    v-for="ingredient in ingredients"
                                                    :key="ingredient.id"
                                                    :value="ingredient.id"
                                                >
                                                    {{ ingredient.name }}{{ ingredient.sku ? ` · ${ingredient.sku}` : '' }}
                                                </option>
                                            </select>
                                            <span class="mt-1 block text-[10px] text-muted-foreground">
                                                Đơn vị chuẩn: <strong class="text-foreground">{{ ingredientUnit(line.ingredient_id) }}</strong>
                                            </span>
                                        </td>
                                        <td class="p-2">
                                            <Input
                                                v-model="line.unit_label"
                                                class="h-9 text-xs"
                                                placeholder="kg, túi..."
                                            />
                                        </td>
                                        <td class="p-2">
                                            <Input
                                                v-model="line.actual_qty"
                                                type="number"
                                                min="0.001"
                                                step="0.001"
                                                class="h-9 text-right text-xs font-bold text-primary [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                            />
                                        </td>
                                        <td class="p-2">
                                            <Input
                                                v-model="line.unit_cost"
                                                type="number"
                                                min="0"
                                                step="1"
                                                class="h-9 text-right text-xs font-medium [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                            />
                                        </td>
                                        <td class="p-2 text-right font-semibold text-foreground">
                                            {{ formatCurrency(lineTotal(line)) }}
                                        </td>
                                        <td class="p-2">
                                            <Input
                                                v-model="line.lot_number"
                                                class="h-9 text-xs font-mono"
                                                placeholder="LOT-..."
                                            />
                                        </td>
                                        <td class="p-2">
                                            <div class="relative flex items-center">
                                                <Input
                                                    :model-value="displayDateValue(line.manufactured_date)"
                                                    type="text"
                                                    readonly
                                                    placeholder="YYYY-MM-DD"
                                                    class="h-9 w-full cursor-default pr-8 pl-2 text-xs font-mono"
                                                />
                                                <button
                                                    type="button"
                                                    class="absolute inset-y-0 right-0 z-10 flex w-8 items-center justify-center text-muted-foreground transition-colors hover:text-orange-400"
                                                    title="Chọn ngày sản xuất"
                                                    aria-label="Chọn ngày sản xuất"
                                                    @click="openRowDatePicker(`manufactured-${index}`)"
                                                >
                                                    <CalendarDays class="size-3.5" />
                                                </button>
                                                <input
                                                    :ref="(element) => setRowDatePickerRef(`manufactured-${index}`, element)"
                                                    v-model="line.manufactured_date"
                                                    type="date"
                                                    :max="line.expiry_date || undefined"
                                                    tabindex="-1"
                                                    aria-hidden="true"
                                                    class="pointer-events-none absolute top-0 right-0 h-9 w-8 opacity-0"
                                                    @change="onManufacturedDateChange(line)"
                                                />
                                            </div>
                                        </td>
                                        <td class="p-2">
                                            <div class="relative flex items-center">
                                                <Input
                                                    :model-value="displayDateValue(line.expiry_date)"
                                                    type="text"
                                                    readonly
                                                    placeholder="YYYY-MM-DD"
                                                    class="h-9 w-full cursor-default pr-8 pl-2 text-xs font-mono"
                                                />
                                                <button
                                                    type="button"
                                                    class="absolute inset-y-0 right-0 z-10 flex w-8 items-center justify-center text-muted-foreground transition-colors hover:text-orange-400"
                                                    title="Chọn hạn sử dụng"
                                                    aria-label="Chọn hạn sử dụng"
                                                    @click="openRowDatePicker(`expiry-${index}`)"
                                                >
                                                    <CalendarDays class="size-3.5" />
                                                </button>
                                                <input
                                                    :ref="(element) => setRowDatePickerRef(`expiry-${index}`, element)"
                                                    v-model="line.expiry_date"
                                                    type="date"
                                                    :min="line.manufactured_date || undefined"
                                                    tabindex="-1"
                                                    aria-hidden="true"
                                                    class="pointer-events-none absolute top-0 right-0 h-9 w-8 opacity-0"
                                                />
                                            </div>
                                        </td>
                                        <td class="p-2">
                                            <select
                                                v-model="line.location_id"
                                                class="h-9 w-full rounded-lg border border-input bg-background px-2 text-xs focus:ring-1 focus:ring-primary focus:outline-none"
                                            >
                                                <option :value="null">Chọn vị trí</option>
                                                <option
                                                    v-for="location in warehouseLocations"
                                                    :key="location.id"
                                                    :value="location.id"
                                                >
                                                    {{ location.location_code }} · {{ location.zone }}{{ location.is_quarantine ? ' · Cách ly' : '' }}
                                                </option>
                                            </select>
                                        </td>
                                        <td class="p-2 text-center">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                class="size-8 text-rose-400 hover:bg-rose-500/10 hover:text-rose-500"
                                                title="Xóa dòng"
                                                @click="removeGrnLine(index)"
                                            >
                                                <X class="size-4" />
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-orange-400/20 bg-orange-500/5 font-bold">
                                        <td colspan="4" class="p-3 text-right text-foreground">Tổng hóa đơn / giá trị nhập ngoài</td>
                                        <td class="p-3 text-right text-base text-orange-300">{{ formatCurrency(totalReceiptValue) }}</td>
                                        <td colspan="5" class="p-3 text-right text-xs text-muted-foreground">Tổng số lượng: {{ formatQuantity(grnForm.items.reduce((total, line) => total + Number(line.actual_qty || 0), 0)) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Nhóm 5: Ghi chú & bằng chứng nguồn nhập -->
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold text-foreground">Ghi chú nghiệp vụ</Label>
                            <textarea
                                v-model="grnForm.notes"
                                rows="3"
                                class="rounded-xl border border-input bg-background px-3 py-2 text-xs focus:ring-2 focus:ring-primary/20 focus:outline-none"
                                placeholder="Mô tả lý do, tình trạng nguyên liệu, cách định giá hoặc thông tin bàn giao..."
                            />
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold text-foreground">Bằng chứng nguồn nhập</Label>
                            <div class="flex gap-2">
                                <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-input bg-background p-2 text-xs font-semibold text-muted-foreground transition hover:border-primary hover:text-primary">
                                    <Upload class="size-4" />
                                    <span>Chọn biên bản / ảnh / PDF nếu có</span>
                                    <input
                                        type="file"
                                        multiple
                                        accept="image/*,.pdf"
                                        class="hidden"
                                        @change="handleGrnFiles"
                                    />
                                </label>
                                <select
                                    v-model="grnDocumentType"
                                    class="h-10 w-40 rounded-xl border border-input bg-background px-3 text-xs focus:ring-2 focus:ring-primary/20 focus:outline-none"
                                >
                                    <option value="external_record">Biên bản nguồn bên ngoài</option>
                                    <option value="qc">Biên bản QC</option>
                                    <option value="receiving_photo">Ảnh giao nhận</option>
                                    <option value="other">Chứng từ khác</option>
                                </select>
                            </div>

                            <div
                                v-if="grnFiles.length"
                                class="mt-1 flex flex-wrap gap-1.5"
                            >
                                <span
                                    v-for="(file, index) in grnFiles"
                                    :key="`${file.name}-${index}`"
                                    class="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-border bg-muted/50 px-2.5 py-1 text-[11px] font-medium text-foreground"
                                >
                                    <span class="max-w-[200px] truncate">{{ file.name }}</span>
                                    <button
                                        type="button"
                                        class="text-rose-400 hover:text-rose-600"
                                        @click="removeGrnFile(index)"
                                    >
                                        <X class="size-3" />
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="grnErrors.length"
                        ref="grnErrorSummary"
                        class="rounded-xl border border-rose-400/25 bg-rose-500/5 p-4 text-xs text-rose-200"
                    >
                        <p class="mb-2 flex items-center gap-2 font-bold">
                            <AlertTriangle class="size-4" /> Chưa thể tạo phiếu
                        </p>
                        <ul class="list-disc space-y-1 pl-5">
                            <li v-for="error in grnErrors" :key="error">
                                {{ error }}
                            </li>
                        </ul>
                    </div>
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4"
                    >
                        <p class="max-w-xl text-[11px] text-muted-foreground">
                            Phiếu sẽ chờ Trưởng kho xác minh; trước thời điểm đó
                            chưa cộng vào tồn kho. Sau khi xác minh, hệ thống tạo
                            giao dịch nhập ngoài, lô hàng và cập nhật tồn Kho Tổng.
                        </p>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="showGrnForm = false"
                                >Hủy</Button
                            ><Button
                                type="submit"
                                class="gap-1.5 bg-orange-600 text-white hover:bg-orange-700"
                                :disabled="isSubmittingGrn"
                                ><ClipboardCheck class="size-4" />
                                {{
                                    isSubmittingGrn
                                        ? 'Đang lưu...'
                                        : 'Tạo phiếu nhập ngoài'
                                }}</Button
                            >
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
