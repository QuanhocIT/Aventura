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
import { computed, ref } from 'vue';
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

type SupplierOption = {
    id: number;
    name: string;
    code?: string | null;
    phone?: string | null;
};

type PurchaseOrderItem = {
    ingredient_id: number;
    ingredient_name: string | null;
    quantity_ordered: number;
    quantity_received: number;
    price_per_unit: number;
    unit: string | null;
};

type PurchaseOrderOption = {
    id: number;
    po_number: string;
    supplier_id: number;
    supplier_name: string | null;
    status: string;
    is_frozen: boolean;
    items: PurchaseOrderItem[];
};

type VoucherItem = {
    id: number;
    ingredient_id: number;
    ingredient?: {
        name: string;
        sku?: string | null;
        unit?: { symbol: string } | null;
    } | null;
    expected_qty: number | string;
    actual_qty: number | string;
    unit_cost: number | string;
    item_status: 'ok' | 'short' | 'over' | string;
    discrepancy_reason: string | null;
    lot_number: string | null;
    expiry_date: string | null;
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
    vehicle_number: string | null;
    seal_code: string | null;
    quality_status: 'pending' | 'passed' | 'conditional' | 'failed' | string;
    quality_notes: string | null;
    temperature_min_c?: number | string | null;
    temperature_max_c?: number | string | null;
    temperature_status?: string | null;
    three_way_match_status?: string | null;
    disposition?: string | null;
    total_expected_qty: number | string;
    total_actual_qty: number | string;
    total_discrepancy_qty: number | string;
    discrepancy_reason: string | null;
    evidence_paths: string[] | null;
    notes: string | null;
    received_by?: { name: string } | null;
    verified_by?: { name: string } | null;
    supplier?: SupplierOption | null;
    purchase_order?: { po_number: string; status: string } | null;
    items: VoucherItem[];
};

type GrnLine = {
    ingredient_id: number | null;
    expected_qty: number;
    actual_qty: number;
    unit_cost: number;
    lot_number: string;
    expiry_date: string;
    location_id: number | null;
    discrepancy_reason: string;
};

const props = defineProps<{
    centralBranch: { id: number; name: string } | null;
    receivingVouchers: Voucher[];
    receivingSummary: Record<string, number>;
    inventorySummary: Record<string, number>;
    warehouseLocations: Location[];
    ingredients: IngredientOption[];
    suppliers: SupplierOption[];
    purchaseOrders: PurchaseOrderOption[];
    canManageWarehouse: boolean;
    canCreateReceiving: boolean;
}>();

const vouchers = ref<Voucher[]>([...props.receivingVouchers]);
const summary = ref({ ...props.receivingSummary });
const inventory = ref({ ...props.inventorySummary });
const filter = ref('pending');
const search = ref('');
const expandedId = ref<number | null>(null);
const isProcessing = ref<number | null>(null);
const confirming = ref<Voucher | null>(null);
const confirmNotes = ref('');
const confirmError = ref('');
const confirmQualityStatus = ref<'passed' | 'conditional' | 'failed'>('passed');
const confirmQualityNotes = ref('');
const confirmTemperatureMin = ref<number | string | undefined>(undefined);
const confirmTemperatureMax = ref<number | string | undefined>(undefined);
const confirmEvidence = ref<File[]>([]);
const dispositionVoucher = ref<Voucher | null>(null);
const dispositionKind = ref<'return_supplier' | 'destroy'>('return_supplier');
const dispositionReason = ref('');
const dispositionEvidence = ref<File[]>([]);
const isDisposing = ref(false);
const showGrnForm = ref(false);
const isSubmittingGrn = ref(false);
const grnErrors = ref<string[]>([]);
const grnFiles = ref<File[]>([]);

const emptyLine = (): GrnLine => ({
    ingredient_id: null,
    expected_qty: 0,
    actual_qty: 0,
    unit_cost: 0,
    lot_number: '',
    expiry_date: '',
    location_id: null,
    discrepancy_reason: '',
});

const grnForm = ref({
    received_at: new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16),
    supplier_id: null as number | null,
    purchase_order_id: null as number | null,
    notes: '',
    delivery_note_number: '',
    invoice_number: '',
    vehicle_number: '',
    seal_code: '',
    quality_status: 'pending',
    quality_notes: '',
    temperature_min_c: '' as string | number,
    temperature_max_c: '' as string | number,
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

const statusLabel = (status: string) =>
    status === 'pending_disposition' ? 'Chờ trả/hủy' :
    status === 'returned' ? 'Đã trả NCC' :
    status === 'destroyed' ? 'Đã tiêu hủy' :
    ({
        draft: 'Chờ xác minh',
        discrepancy: 'Có chênh lệch',
        pending_review: 'Chờ xem xét',
        confirmed: 'Đã nhập kho',
        closed: 'Đã đóng',
    })[status] || status;

const statusClass = (status: string) =>
    status === 'pending_disposition' ? 'border-rose-400/30 bg-rose-500/10 text-rose-300' :
    status === 'returned' ? 'border-sky-400/30 bg-sky-500/10 text-sky-300' :
    status === 'destroyed' ? 'border-slate-400/30 bg-slate-500/10 text-slate-300' :
    ({
        draft: 'border-orange-400/30 bg-orange-500/10 text-orange-300',
        discrepancy: 'border-rose-400/30 bg-rose-500/10 text-rose-300',
        pending_review: 'border-amber-400/30 bg-amber-500/10 text-amber-300',
        confirmed: 'border-emerald-400/30 bg-emerald-500/10 text-emerald-300',
        closed: 'border-slate-400/30 bg-slate-500/10 text-slate-300',
    })[status] || 'border-border bg-muted text-muted-foreground';

const qualityLabel = (status: string | null | undefined) =>
    ({
        pending: 'Chưa kiểm tra',
        passed: 'Đạt',
        conditional: 'Đạt có điều kiện',
        failed: 'Không đạt',
    })[status || 'pending'] ||
    status ||
    'Chưa kiểm tra';

const itemStatusLabel = (status: string) =>
    ({ ok: 'Khớp', short: 'Thiếu', over: 'Thừa' })[status] || status;
const itemStatusClass = (status: string) =>
    ({
        ok: 'text-emerald-300',
        short: 'text-rose-300',
        over: 'text-amber-300',
    })[status] || 'text-muted-foreground';
const ingredientUnit = (ingredientId: number | null) =>
    props.ingredients.find((item) => item.id === ingredientId)?.unit?.symbol ??
    'đv';

const selectedPurchaseOrder = computed(() =>
    props.purchaseOrders.find(
        (order) => order.id === grnForm.value.purchase_order_id,
    ),
);

const filteredVouchers = computed(() => {
    const query = search.value.trim().toLowerCase();

    return vouchers.value.filter((voucher) => {
        const matchesFilter =
            filter.value === 'all' ||
            (filter.value === 'pending' &&
                ['draft', 'discrepancy', 'pending_review', 'pending_disposition'].includes(
                    voucher.status,
                )) ||
            (filter.value === 'discrepancy' &&
                ['discrepancy', 'pending_review'].includes(voucher.status)) ||
            (filter.value === 'confirmed' &&
                ['confirmed', 'closed'].includes(voucher.status));
        const haystack = [
            voucher.voucher_code,
            voucher.supplier?.name,
            voucher.purchase_order?.po_number,
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
    confirmTemperatureMin.value = voucher.temperature_min_c ?? undefined;
    confirmTemperatureMax.value = voucher.temperature_max_c ?? undefined;
    confirmEvidence.value = [];
};

const closeConfirm = () => {
    confirming.value = null;
    confirmNotes.value = '';
    confirmError.value = '';
    confirmQualityStatus.value = 'passed';
    confirmQualityNotes.value = '';
    confirmTemperatureMin.value = undefined;
    confirmTemperatureMax.value = undefined;
    confirmEvidence.value = [];
};

const handleConfirmEvidence = (event: Event) => {
    const files = (event.target as HTMLInputElement).files;

    if (files) {
        confirmEvidence.value = [...confirmEvidence.value, ...Array.from(files)];
    }
};

const handleDispositionEvidence = (event: Event) => {
    const files = (event.target as HTMLInputElement).files;

    if (files) {
        dispositionEvidence.value = [...dispositionEvidence.value, ...Array.from(files)];
    }
};

const openDisposition = (voucher: Voucher) => {
    dispositionVoucher.value = voucher;
    dispositionKind.value = 'return_supplier';
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
            'Phiếu có chênh lệch, bắt buộc nhập giải trình trước khi xác minh.';

        return;
    }

    if (false && confirmQualityStatus.value === 'failed') {
        confirmError.value =
            'Chất lượng không đạt không thể nhập kho. Hãy lập biên bản trả hàng hoặc xử lý cách ly.';

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
        const payload = confirmQualityStatus.value === 'failed'
            ? (() => {
                const formData = new FormData();
                formData.append('notes', confirmNotes.value.trim());
                formData.append('quality_status', confirmQualityStatus.value);
                formData.append('quality_notes', confirmQualityNotes.value.trim());

                if (confirmTemperatureMin.value !== null && confirmTemperatureMin.value !== '') {
                    formData.append('temperature_min_c', String(confirmTemperatureMin.value));
                }

                if (confirmTemperatureMax.value !== null && confirmTemperatureMax.value !== '') {
                    formData.append('temperature_max_c', String(confirmTemperatureMax.value));
                }

                confirmEvidence.value.forEach((file) => formData.append('evidence[]', file));

                return formData;
            })()
            : {
                notes: confirmNotes.value.trim(),
                quality_status: confirmQualityStatus.value,
                quality_notes: confirmQualityNotes.value.trim(),
                temperature_min_c: confirmTemperatureMin.value,
                temperature_max_c: confirmTemperatureMax.value,
            };
        await axios.post(`/api/warehouse/receiving-vouchers/${voucher.id}/confirm`, payload);
        voucher.status = 'confirmed';
        voucher.verified_at = new Date().toISOString();
        voucher.quality_status = confirmQualityStatus.value;
        voucher.quality_notes = confirmQualityNotes.value.trim();
        voucher.temperature_min_c = confirmTemperatureMin.value;
        voucher.temperature_max_c = confirmTemperatureMax.value;
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
            toast.warning('Lô không đạt đã được cách ly. Hãy ghi nhận trả NCC hoặc tiêu hủy.');

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
    if (!dispositionVoucher.value || !dispositionReason.value.trim() || isDisposing.value) {
        return;
    }

    if (dispositionKind.value === 'destroy' && dispositionEvidence.value.length === 0) {
        toast.error('Tiêu hủy phải có ảnh/biên bản làm bằng chứng.');

        return;
    }

    isDisposing.value = true;
    const formData = new FormData();
    formData.append('disposition', dispositionKind.value);
    formData.append('reason', dispositionReason.value.trim());
    dispositionEvidence.value.forEach((file) => formData.append('evidence[]', file));

    try {
        await axios.post(`/api/warehouse/receiving-vouchers/${dispositionVoucher.value.id}/dispose`, formData);
        dispositionVoucher.value.status = dispositionKind.value === 'destroy' ? 'destroyed' : 'returned';
        summary.value.pending_review = Math.max(0, (summary.value.pending_review ?? 1) - 1);
        closeDisposition();
        toast.success('Đã ghi nhận xử lý lô không đạt.');
    } catch (error: any) {
        toast.error(error.response?.data?.message ?? 'Không thể ghi nhận xử lý lệnh.');
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

const onIngredientChange = (line: GrnLine) => {
    const ingredient = props.ingredients.find(
        (item) => item.id === line.ingredient_id,
    );

    if (!ingredient) {
        return;
    }

    line.unit_cost = Number(ingredient.average_cost || 0);

    if (!line.expiry_date) {
        line.expiry_date = expiryAfter(ingredient.default_shelf_life_days);
    }
};

const onPurchaseOrderChange = () => {
    const order = selectedPurchaseOrder.value;

    if (!order) {
        grnForm.value.supplier_id = null;
        grnForm.value.items = [emptyLine()];

        return;
    }

    grnForm.value.supplier_id = order.supplier_id;
    grnForm.value.items = order.items
        .map((item) => ({
            ingredient_id: item.ingredient_id,
            expected_qty: Math.max(
                0,
                item.quantity_ordered - item.quantity_received,
            ),
            actual_qty: Math.max(
                0,
                item.quantity_ordered - item.quantity_received,
            ),
            unit_cost: item.price_per_unit,
            lot_number: '',
            expiry_date: '',
            location_id: null,
            discrepancy_reason: '',
        }))
        .filter((item) => item.expected_qty > 0);

    if (grnForm.value.items.length === 0) {
        grnForm.value.items = [emptyLine()];
    }
};

const handleGrnFiles = (event: Event) => {
    const files = (event.target as HTMLInputElement).files;

    if (files) {
        grnFiles.value = [...grnFiles.value, ...Array.from(files)];
    }
};
const removeGrnFile = (index: number) => {
    grnFiles.value.splice(index, 1);
};

const validateGrn = () => {
    const errors: string[] = [];

    if (grnForm.value.items.length === 0) {
        errors.push('Phiếu phải có ít nhất một dòng nguyên liệu.');
    }

    grnForm.value.items.forEach((line, index) => {
        const lineNo = index + 1;

        if (!line.ingredient_id) {
            errors.push(`Dòng ${lineNo}: chưa chọn nguyên liệu.`);
        }

        if (line.expected_qty <= 0) {
            errors.push(`Dòng ${lineNo}: số lượng dự kiến phải lớn hơn 0.`);
        }

        if (line.actual_qty < 0) {
            errors.push(`Dòng ${lineNo}: số lượng thực nhận không được âm.`);
        }

        if (line.actual_qty > 0 && !line.location_id) {
            errors.push(
                `Dòng ${lineNo}: phải chọn vị trí cất hàng để truy vết lô.`,
            );
        }

        if (
            Math.abs(line.actual_qty - line.expected_qty) > 0.001 &&
            !line.discrepancy_reason.trim()
        ) {
            errors.push(`Dòng ${lineNo}: phải ghi lý do thiếu/thừa.`);
        }
    });

    if (
        grnForm.value.temperature_min_c !== null &&
        grnForm.value.temperature_max_c !== null &&
        Number(grnForm.value.temperature_min_c) > Number(grnForm.value.temperature_max_c)
    ) {
        errors.push('Nhiệt độ tối thiểu không được lớn hơn nhiệt độ tối đa.');
    }

    grnErrors.value = errors;

    return errors.length === 0;
};

const submitGrn = async () => {
    if (isSubmittingGrn.value || !validateGrn()) {
        return;
    }

    isSubmittingGrn.value = true;
    const formData = new FormData();
    formData.append('received_at', grnForm.value.received_at);

    if (grnForm.value.supplier_id) {
        formData.append('supplier_id', String(grnForm.value.supplier_id));
    }

    if (grnForm.value.purchase_order_id) {
        formData.append(
            'purchase_order_id',
            String(grnForm.value.purchase_order_id),
        );
    }

    if (grnForm.value.notes.trim()) {
        formData.append('notes', grnForm.value.notes.trim());
    }

    if (grnForm.value.delivery_note_number.trim()) {
        formData.append(
            'delivery_note_number',
            grnForm.value.delivery_note_number.trim(),
        );
    }

    if (grnForm.value.invoice_number.trim()) {
        formData.append('invoice_number', grnForm.value.invoice_number.trim());
    }

    if (grnForm.value.vehicle_number.trim()) {
        formData.append('vehicle_number', grnForm.value.vehicle_number.trim());
    }

    if (grnForm.value.seal_code.trim()) {
        formData.append('seal_code', grnForm.value.seal_code.trim());
    }

    if (grnForm.value.temperature_min_c !== null && grnForm.value.temperature_min_c !== '') {
        formData.append('temperature_min_c', String(grnForm.value.temperature_min_c));
    }

    if (grnForm.value.temperature_max_c !== null && grnForm.value.temperature_max_c !== '') {
        formData.append('temperature_max_c', String(grnForm.value.temperature_max_c));
    }

    grnForm.value.items.forEach((line, index) => {
        formData.append(
            `items[${index}][ingredient_id]`,
            String(line.ingredient_id),
        );
        formData.append(
            `items[${index}][expected_qty]`,
            String(line.expected_qty),
        );
        formData.append(`items[${index}][actual_qty]`, String(line.actual_qty));
        formData.append(`items[${index}][unit_cost]`, String(line.unit_cost));

        if (line.lot_number.trim()) {
            formData.append(
                `items[${index}][lot_number]`,
                line.lot_number.trim(),
            );
        }

        if (line.expiry_date) {
            formData.append(`items[${index}][expiry_date]`, line.expiry_date);
        }

        if (line.location_id) {
            formData.append(
                `items[${index}][location_id]`,
                String(line.location_id),
            );
        }

        if (line.discrepancy_reason.trim()) {
            formData.append(
                `items[${index}][discrepancy_reason]`,
                line.discrepancy_reason.trim(),
            );
        }
    });
    grnFiles.value.forEach((file) => formData.append('evidence[]', file));

    try {
        const { data } = await axios.post(
            '/api/warehouse/receiving-vouchers',
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );
        const supplier = props.suppliers.find(
            (item) => item.id === grnForm.value.supplier_id,
        );
        const purchaseOrder = props.purchaseOrders.find(
            (item) => item.id === grnForm.value.purchase_order_id,
        );
        vouchers.value.unshift({
            ...data.voucher,
            supplier,
            purchase_order: purchaseOrder,
        });
        summary.value.pending_review = (summary.value.pending_review ?? 0) + 1;
        summary.value.today = (summary.value.today ?? 0) + 1;
        toast.success(data.message ?? 'Đã tạo phiếu nhận hàng.');
        showGrnForm.value = false;
        grnForm.value = {
            received_at: new Date(
                Date.now() - new Date().getTimezoneOffset() * 60000,
            )
                .toISOString()
                .slice(0, 16),
            supplier_id: null,
            purchase_order_id: null,
            notes: '',
            delivery_note_number: '',
            invoice_number: '',
            vehicle_number: '',
            seal_code: '',
            quality_status: 'pending',
            quality_notes: '',
            temperature_min_c: '',
            temperature_max_c: '',
            items: [emptyLine()],
        };
        grnFiles.value = [];
        grnErrors.value = [];
    } catch (error: any) {
        const responseErrors = error.response?.data?.errors;
        grnErrors.value = responseErrors
            ? Object.values(responseErrors).flat().map(String)
            : [
                  error.response?.data?.message ??
                      'Không thể tạo phiếu nhận hàng.',
              ];
    } finally {
        isSubmittingGrn.value = false;
    }
};

const evidenceUrl = (path: string) =>
    path.startsWith('http://') || path.startsWith('https://')
        ? path
        : `/storage/${path}`;
</script>

<template>
    <Head title="Nhận hàng & GRN Kho Tổng" />
    <div class="mx-auto w-full max-w-[1500px] space-y-5 p-4 sm:p-6">
        <section
            class="rounded-3xl border border-orange-500/20 bg-gradient-to-br from-slate-950 via-orange-950/80 to-slate-900 p-6 text-white shadow-xl sm:p-8"
        >
            <div
                class="flex flex-col justify-between gap-5 lg:flex-row lg:items-center"
            >
                <div>
                    <Link
                        href="/inventory/central-warehouse"
                        class="mb-3 inline-flex items-center gap-1 text-xs text-orange-200 hover:text-white"
                        ><ArrowLeft class="size-3.5" /> Tổng quan Kho Tổng</Link
                    >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-12 items-center justify-center rounded-2xl bg-orange-500/20 text-orange-200"
                        >
                            <ClipboardCheck class="size-7" />
                        </div>
                        <div>
                            <h1
                                class="text-2xl font-black tracking-tight sm:text-3xl"
                            >
                                Nhận hàng & GRN
                            </h1>
                            <p class="mt-1 text-sm text-orange-100/75">
                                Kiểm soát chứng từ, số lượng thực nhận, lô, HSD
                                và vị trí trước khi nhập Kho Tổng.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="canCreateReceiving"
                        class="gap-2 bg-orange-600 text-white hover:bg-orange-700"
                        @click="showGrnForm = true"
                        ><Plus class="size-4" /> Tạo phiếu GRN</Button
                    ><Link href="/inventory/staff-portal"
                        ><Button
                            variant="outline"
                            class="gap-2 border-white/25 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                            ><PackageCheck class="size-4" /> Cổng nhân
                            viên</Button
                        ></Link
                    ><Button
                        variant="outline"
                        class="gap-2 border-white/25 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                        @click="reload"
                        ><RefreshCw class="size-4" /> Làm mới</Button
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
                        Phiếu lệch
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
                        Chênh lệch
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
                            nhận hàng tại
                            {{ centralBranch?.name || 'Kho Tổng' }}</CardTitle
                        ><CardDescription class="mt-1 text-xs"
                            >Mở chi tiết từng phiếu để kiểm tra dòng hàng, lý do
                            chênh lệch, lô và vị trí trước khi xác
                            minh.</CardDescription
                        >
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <div class="relative min-w-[220px]">
                            <Search
                                class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                            /><Input
                                v-model="search"
                                class="h-9 pl-9 text-xs"
                                placeholder="Tìm mã GRN, nhà cung cấp..."
                            />
                        </div>
                        <select
                            v-model="filter"
                            class="h-9 rounded-md border border-input bg-background px-3 text-xs text-foreground"
                        >
                            <option value="pending">Chờ xử lý</option>
                            <option value="discrepancy">Có chênh lệch</option>
                            <option value="confirmed">Đã nhập kho</option>
                            <option value="all">Tất cả phiếu</option>
                        </select>
                    </div>
                </div></CardHeader
            >
            <CardContent class="p-0"
                ><div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-left text-xs">
                        <thead
                            class="border-b border-border bg-muted/50 text-muted-foreground"
                        >
                            <tr>
                                <th class="w-8 p-3"></th>
                                <th class="p-3">Mã GRN / Đơn mua</th>
                                <th class="p-3">Nhà cung cấp</th>
                                <th class="p-3">Người nhận / Thời gian</th>
                                <th class="p-3 text-right">Dòng hàng</th>
                                <th class="p-3 text-right">Thực nhận</th>
                                <th class="p-3 text-right">Chênh lệch</th>
                                <th class="p-3">Trạng thái</th>
                                <th class="p-3 text-right">Xử lý</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="filteredVouchers.length === 0">
                                <td
                                    colspan="9"
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
                                        <p
                                            v-if="
                                                voucher.purchase_order
                                                    ?.po_number
                                            "
                                            class="mt-1 text-[10px] text-muted-foreground"
                                        >
                                            PO:
                                            {{
                                                voucher.purchase_order.po_number
                                            }}
                                        </p>
                                    </td>
                                    <td class="p-3">
                                        <p
                                            class="font-semibold text-foreground"
                                        >
                                            {{
                                                voucher.supplier?.name ||
                                                'Chưa gắn nhà cung cấp'
                                            }}
                                        </p>
                                        <p
                                            v-if="voucher.supplier?.code"
                                            class="mt-1 text-[10px] text-muted-foreground"
                                        >
                                            {{ voucher.supplier.code }}
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
                                            {{
                                                formatDate(voucher.received_at)
                                            }}
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
                                    <td
                                        class="p-3 text-right font-bold"
                                        :class="
                                            Number(
                                                voucher.total_discrepancy_qty,
                                            ) === 0
                                                ? 'text-emerald-300'
                                                : 'text-rose-300'
                                        "
                                    >
                                        {{
                                            formatQuantity(
                                                Math.abs(
                                                    Number(
                                                        voucher.total_discrepancy_qty ||
                                                            0,
                                                    ),
                                                ),
                                            )
                                        }}
                                    </td>
                                    <td class="p-3">
                                        <span
                                            class="rounded-full border px-2 py-1 text-[10px] font-semibold"
                                            :class="statusClass(voucher.status)"
                                            >{{
                                                statusLabel(voucher.status)
                                            }}</span
                                        >
                                    </td>
                                    <td class="p-3 text-right" @click.stop>
                                        <div class="flex justify-end gap-1.5">
                                            <Button
                                                v-if="
                                                    canManageWarehouse &&
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
                                    <td colspan="9" class="p-4 sm:p-5">
                                        <div
                                            class="grid gap-4 xl:grid-cols-[0.7fr_1.3fr]"
                                        >
                                            <div class="space-y-3">
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
                                                                Người nhận
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
                                                                Xác minh bởi
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
                                                                Tổng dự kiến
                                                            </dt>
                                                            <dd
                                                                class="text-right text-foreground"
                                                            >
                                                                {{
                                                                    formatQuantity(
                                                                        voucher.total_expected_qty,
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
                                                                Tổng thực nhận
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
                                                    </dl>
                                                    <div
                                                        class="mt-3 grid grid-cols-2 gap-2 border-t border-border pt-3 text-[11px]"
                                                    >
                                                        <p>
                                                            <span
                                                                class="text-muted-foreground"
                                                                >Phiếu
                                                                giao:</span
                                                            >
                                                            {{
                                                                voucher.delivery_note_number ||
                                                                '-'
                                                            }}
                                                        </p>
                                                        <p>
                                                            <span
                                                                class="text-muted-foreground"
                                                                >Hóa đơn:</span
                                                            >
                                                            {{
                                                                voucher.invoice_number ||
                                                                '-'
                                                            }}
                                                        </p>
                                                        <p>
                                                            <span
                                                                class="text-muted-foreground"
                                                                >Xe:</span
                                                            >
                                                            {{
                                                                voucher.vehicle_number ||
                                                                '-'
                                                            }}
                                                        </p>
                                                        <p>
                                                            <span
                                                                class="text-muted-foreground"
                                                                >Niêm
                                                                phong:</span
                                                            >
                                                            {{
                                                                voucher.seal_code ||
                                                                '-'
                                                            }}
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
                                                    </div>
                                                </div>
                                                <div
                                                    v-if="
                                                        voucher.notes ||
                                                        voucher.discrepancy_reason ||
                                                        voucher.quality_notes
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
                                                            voucher.discrepancy_reason
                                                        "
                                                        class="mt-2 text-amber-100"
                                                    >
                                                        {{
                                                            voucher.discrepancy_reason
                                                        }}
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
                                                            class="size-4 text-indigo-300"
                                                        />
                                                        Chứng từ đính kèm
                                                    </p>
                                                    <a
                                                        v-for="(
                                                            path, index
                                                        ) in voucher.evidence_paths"
                                                        :key="path"
                                                        :href="
                                                            evidenceUrl(path)
                                                        "
                                                        target="_blank"
                                                        class="block truncate text-indigo-300 hover:text-indigo-200"
                                                        >Tệp {{ index + 1 }} ·
                                                        {{ path }}</a
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                class="rounded-xl border border-border bg-background/50 p-4"
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
                                                        Đối chiếu từng dòng hàng
                                                    </p>
                                                    <span
                                                        class="text-[10px] text-muted-foreground"
                                                        >{{
                                                            voucher.items.length
                                                        }}
                                                        dòng</span
                                                    >
                                                </div>
                                                <div class="overflow-x-auto">
                                                    <table
                                                        class="w-full min-w-[760px] text-xs"
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
                                                                <th
                                                                    class="p-2 text-right"
                                                                >
                                                                    Dự kiến
                                                                </th>
                                                                <th
                                                                    class="p-2 text-right"
                                                                >
                                                                    Thực nhận
                                                                </th>
                                                                <th
                                                                    class="p-2 text-right"
                                                                >
                                                                    Lệch
                                                                </th>
                                                                <th class="p-2">
                                                                    Lô / HSD
                                                                </th>
                                                                <th class="p-2">
                                                                    Vị trí
                                                                </th>
                                                                <th class="p-2">
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
                                                                        class="font-semibold text-foreground"
                                                                    >
                                                                        {{
                                                                            item
                                                                                .ingredient
                                                                                ?.name ||
                                                                            `Nguyên liệu #${item.ingredient_id}`
                                                                        }}
                                                                    </p>
                                                                    <p
                                                                        class="mt-1 text-[10px] text-muted-foreground"
                                                                    >
                                                                        {{
                                                                            item
                                                                                .ingredient
                                                                                ?.unit
                                                                                ?.symbol ||
                                                                            'đv'
                                                                        }}
                                                                        ·
                                                                        {{
                                                                            formatCurrency(
                                                                                item.unit_cost,
                                                                            )
                                                                        }}
                                                                    </p>
                                                                </td>
                                                                <td
                                                                    class="p-2 text-right"
                                                                >
                                                                    {{
                                                                        formatQuantity(
                                                                            item.expected_qty,
                                                                        )
                                                                    }}
                                                                </td>
                                                                <td
                                                                    class="p-2 text-right font-bold text-sky-300"
                                                                >
                                                                    {{
                                                                        formatQuantity(
                                                                            item.actual_qty,
                                                                        )
                                                                    }}
                                                                </td>
                                                                <td
                                                                    class="p-2 text-right font-bold"
                                                                    :class="
                                                                        itemStatusClass(
                                                                            item.item_status,
                                                                        )
                                                                    "
                                                                >
                                                                    {{
                                                                        Number(
                                                                            item.actual_qty,
                                                                        ) -
                                                                            Number(
                                                                                item.expected_qty,
                                                                            ) >
                                                                        0
                                                                            ? '+'
                                                                            : ''
                                                                    }}{{
                                                                        formatQuantity(
                                                                            Number(
                                                                                item.actual_qty,
                                                                            ) -
                                                                                Number(
                                                                                    item.expected_qty,
                                                                                ),
                                                                        )
                                                                    }}
                                                                </td>
                                                                <td class="p-2">
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
                                                                        class="mt-1 text-[10px] text-muted-foreground"
                                                                    >
                                                                        HSD:
                                                                        {{
                                                                            formatDateOnly(
                                                                                item.expiry_date,
                                                                            )
                                                                        }}
                                                                    </p>
                                                                </td>
                                                                <td class="p-2">
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
                                                                        gán vị
                                                                        trí</span
                                                                    >
                                                                </td>
                                                                <td class="p-2">
                                                                    <span
                                                                        class="font-semibold"
                                                                        :class="
                                                                            itemStatusClass(
                                                                                item.item_status,
                                                                            )
                                                                        "
                                                                        >{{
                                                                            itemStatusLabel(
                                                                                item.item_status,
                                                                            )
                                                                        }}</span
                                                                    >
                                                                    <p
                                                                        v-if="
                                                                            item.discrepancy_reason
                                                                        "
                                                                        class="mt-1 max-w-[160px] text-[10px] text-muted-foreground"
                                                                    >
                                                                        {{
                                                                            item.discrepancy_reason
                                                                        }}
                                                                    </p>
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
                                                    canManageWarehouse &&
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
                        Xác minh GRN
                    </p>
                    <h2 class="mt-1 text-xl font-black">
                        {{ confirming.voucher_code }} · Nhập vào Kho Tổng
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Sau khi xác nhận, hệ thống tạo giao dịch nhập, cập nhật
                        giá vốn bình quân và tạo lô hàng.
                    </p>
                </div>
                <Button variant="ghost" size="icon" @click="closeConfirm"
                    ><X class="size-4"
                /></Button>
            </div>
            <div class="grid grid-cols-3 gap-2 text-xs">
                <div class="rounded-xl bg-muted/30 p-3">
                    <p class="text-muted-foreground">Dự kiến</p>
                    <strong class="mt-1 block text-foreground">{{
                        formatQuantity(confirming.total_expected_qty)
                    }}</strong>
                </div>
                <div class="rounded-xl bg-sky-500/10 p-3">
                    <p class="text-sky-200/70">Thực nhận</p>
                    <strong class="mt-1 block text-sky-200">{{
                        formatQuantity(confirming.total_actual_qty)
                    }}</strong>
                </div>
                <div class="rounded-xl bg-rose-500/10 p-3">
                    <p class="text-rose-200/70">Chênh lệch</p>
                    <strong class="mt-1 block text-rose-200">{{
                        formatQuantity(
                            Math.abs(
                                Number(confirming.total_discrepancy_qty || 0),
                            ),
                        )
                    }}</strong>
                </div>
            </div>
            <div
                v-if="discrepancyItems.length"
                class="mt-4 rounded-xl border border-rose-400/25 bg-rose-500/5 p-4 text-xs"
            >
                <p class="flex items-center gap-2 font-bold text-rose-200">
                    <AlertTriangle class="size-4" /> Phiếu có
                    {{ discrepancyItems.length }} dòng chênh lệch
                </p>
                <p class="mt-1 text-muted-foreground">
                    Cần ghi rõ nguyên nhân và trách nhiệm xử lý trước khi hạch
                    toán.
                </p>
                <ul class="mt-3 space-y-1 text-rose-100">
                    <li v-for="item in discrepancyItems" :key="item.id">
                        •
                        {{
                            item.ingredient?.name ||
                            `Nguyên liệu #${item.ingredient_id}`
                        }}: {{ itemStatusLabel(item.item_status) }}
                    </li>
                </ul>
            </div>
            <form class="mt-5 space-y-4" @submit.prevent="confirmVoucher">
                <div class="rounded-xl border border-sky-400/20 bg-sky-500/5 p-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label>Nhiệt độ thấp nhất (°C)</Label>
                            <Input v-model="confirmTemperatureMin" type="number" step="0.1" placeholder="Ví dụ 2" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>Nhiệt độ cao nhất (°C)</Label>
                            <Input v-model="confirmTemperatureMax" type="number" step="0.1" placeholder="Ví dụ 6" />
                        </div>
                    </div>
                    <p class="mt-2 text-[11px] text-muted-foreground">Bắt buộc với hàng tươi, kho lạnh hoặc nguyên liệu có cài ngưỡng.</p>
                </div>
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
                            placeholder="Nhiệt độ, bao bì, ngoại quan..."
                        />
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>Bằng chứng xử lý chất lượng (nếu không đạt)</Label>
                    <input type="file" multiple accept="image/*,.pdf" class="h-10 rounded-md border border-input bg-background px-3 py-2 text-xs" @change="handleConfirmEvidence" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>{{
                        discrepancyItems.length
                            ? 'Giải trình bắt buộc'
                            : 'Ghi chú xác minh'
                    }}</Label
                    ><textarea
                        v-model="confirmNotes"
                        rows="4"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        :placeholder="
                            discrepancyItems.length
                                ? 'Nêu nguyên nhân thiếu/thừa, biên bản, hướng xử lý...'
                                : 'Ghi nhận tình trạng thực tế khi nhập hàng...'
                        "
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
        v-if="dispositionVoucher"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
        @click.self="closeDisposition"
    >
        <div class="w-full max-w-xl rounded-3xl border border-rose-400/25 bg-background p-6 shadow-2xl">
            <div class="mb-5 flex items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-rose-400">Xử lý lô không đạt</p>
                    <h2 class="mt-1 text-xl font-black">{{ dispositionVoucher.voucher_code }}</h2>
                    <p class="mt-1 text-xs text-muted-foreground">Lô này chưa được cộng vào tồn kho.</p>
                </div>
                <Button variant="ghost" size="icon" @click="closeDisposition"><X class="size-4" /></Button>
            </div>
            <form class="space-y-4" @submit.prevent="disposeReceiving">
                <div class="flex flex-col gap-1.5">
                    <Label>Hướng xử lý</Label>
                    <select v-model="dispositionKind" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                        <option value="return_supplier">Trả nhà cung cấp</option>
                        <option value="destroy">Tiêu hủy</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>Lý do / biên bản</Label>
                    <textarea v-model="dispositionReason" rows="4" required class="rounded-md border border-input bg-background px-3 py-2 text-sm" placeholder="Nêu rõ lý do, số biên bản, người bàn giao..." />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label>Bằng chứng {{ dispositionKind === 'destroy' ? '(bắt buộc)' : '(khuyến nghị)' }}</Label>
                    <input type="file" multiple accept="image/*,.pdf" class="h-10 rounded-md border border-input bg-background px-3 py-2 text-xs" @change="handleDispositionEvidence" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="closeDisposition">Hủy</Button>
                    <Button type="submit" class="bg-rose-600 text-white hover:bg-rose-700" :disabled="isDisposing">Ghi nhận xử lý</Button>
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
                        Phiếu nhận hàng mới
                    </p>
                    <h2 class="mt-1 text-2xl font-black">
                        Tạo GRN và ghi nhận hàng thực nhận
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Mỗi dòng cần số lượng thực nhận, lô/HSD nếu có và vị trí
                        cất hàng. Chênh lệch phải có lý do.
                    </p>
                </div>
                <Button variant="ghost" size="icon" @click="showGrnForm = false"
                    ><X class="size-4"
                /></Button>
            </div>
            <form class="space-y-5" @submit.prevent="submitGrn">
                <div class="grid gap-3 md:grid-cols-4">
                    <div class="flex flex-col gap-1.5">
                        <Label>Thời điểm nhận</Label
                        ><Input
                            v-model="grnForm.received_at"
                            type="datetime-local"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Nhà cung cấp</Label
                        ><select
                            v-model="grnForm.supplier_id"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option :value="null">Chọn nhà cung cấp</option>
                            <option
                                v-for="supplier in suppliers"
                                :key="supplier.id"
                                :value="supplier.id"
                            >
                                {{ supplier.name
                                }}{{
                                    supplier.code ? ` · ${supplier.code}` : ''
                                }}
                            </option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5 md:col-span-2">
                        <Label>Đối chiếu đơn mua hàng (khuyến nghị)</Label
                        ><select
                            v-model="grnForm.purchase_order_id"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            @change="onPurchaseOrderChange"
                        >
                            <option :value="null">Không gắn PO</option>
                            <option
                                v-for="order in purchaseOrders"
                                :key="order.id"
                                :value="order.id"
                            >
                                {{ order.po_number }} ·
                                {{ order.supplier_name || 'Chưa rõ NCC' }} ·
                                {{ order.status }}
                            </option>
                        </select>
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-4">
                    <div class="flex flex-col gap-1.5">
                        <Label>Số phiếu giao hàng</Label
                        ><Input
                            v-model="grnForm.delivery_note_number"
                            placeholder="DN-..."
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Số hóa đơn</Label
                        ><Input
                            v-model="grnForm.invoice_number"
                            placeholder="INV-..."
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Biển số xe</Label
                        ><Input
                            v-model="grnForm.vehicle_number"
                            placeholder="51A-..."
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Mã niêm phong</Label
                        ><Input
                            v-model="grnForm.seal_code"
                            placeholder="SEAL-..."
                        />
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-4">
                    <div class="flex flex-col gap-1.5">
                        <Label>Nhiệt độ thấp nhất (°C)</Label>
                        <Input v-model="grnForm.temperature_min_c" type="number" step="0.1" placeholder="Ví dụ 2" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Nhiệt độ cao nhất (°C)</Label>
                        <Input v-model="grnForm.temperature_max_c" type="number" step="0.1" placeholder="Ví dụ 6" />
                    </div>
                    <div class="flex items-end md:col-span-2">
                        <p class="rounded-lg border border-sky-400/20 bg-sky-500/5 p-3 text-[11px] text-muted-foreground">Hàng tươi/kho lạnh sẽ không được xác minh nếu thiếu nhiệt độ nhận hàng.</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-border">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/20 p-4"
                    >
                        <div>
                            <p
                                class="flex items-center gap-2 text-sm font-bold"
                            >
                                <PackageCheck class="size-4 text-orange-300" />
                                Dòng hàng thực nhận
                            </p>
                            <p class="mt-1 text-[11px] text-muted-foreground">
                                Nếu giao thiếu/thừa, nhập số thực tế và lý do
                                ngay trên dòng.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="gap-1.5"
                            @click="addGrnLine"
                            ><Plus class="size-3.5" /> Thêm dòng</Button
                        >
                    </div>
                    <div class="overflow-x-auto p-3">
                        <table class="w-full min-w-[1250px] text-xs">
                            <thead class="text-[10px] text-muted-foreground">
                                <tr>
                                    <th class="p-2 text-left">Nguyên liệu</th>
                                    <th class="w-28 p-2 text-right">Dự kiến</th>
                                    <th class="w-28 p-2 text-right">
                                        Thực nhận
                                    </th>
                                    <th class="w-32 p-2 text-right">Đơn giá</th>
                                    <th class="w-36 p-2">Số lô</th>
                                    <th class="w-36 p-2">HSD</th>
                                    <th class="w-52 p-2">Vị trí cất</th>
                                    <th class="w-56 p-2">Lý do chênh lệch</th>
                                    <th class="w-12 p-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="(line, index) in grnForm.items"
                                    :key="index"
                                >
                                    <td class="p-2">
                                        <select
                                            v-model="line.ingredient_id"
                                            class="h-9 w-full rounded-md border border-input bg-background px-2 text-xs"
                                            @change="onIngredientChange(line)"
                                        >
                                            <option :value="null">
                                                Chọn nguyên liệu
                                            </option>
                                            <option
                                                v-for="ingredient in ingredients"
                                                :key="ingredient.id"
                                                :value="ingredient.id"
                                            >
                                                {{ ingredient.name
                                                }}{{
                                                    ingredient.sku
                                                        ? ` · ${ingredient.sku}`
                                                        : ''
                                                }}
                                            </option></select
                                        ><span
                                            class="mt-1 block text-[10px] text-muted-foreground"
                                            >Đơn vị:
                                            {{
                                                ingredientUnit(
                                                    line.ingredient_id,
                                                )
                                            }}</span
                                        >
                                    </td>
                                    <td class="p-2">
                                        <Input
                                            v-model="line.expected_qty"
                                            type="number"
                                            min="0"
                                            step="0.001"
                                            class="h-9 text-right text-xs"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <Input
                                            v-model="line.actual_qty"
                                            type="number"
                                            min="0"
                                            step="0.001"
                                            class="h-9 text-right text-xs"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <Input
                                            v-model="line.unit_cost"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="h-9 text-right text-xs"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <Input
                                            v-model="line.lot_number"
                                            class="h-9 text-xs"
                                            placeholder="LOT-..."
                                        />
                                    </td>
                                    <td class="p-2">
                                        <Input
                                            v-model="line.expiry_date"
                                            type="date"
                                            class="h-9 text-xs"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <select
                                            v-model="line.location_id"
                                            class="h-9 w-full rounded-md border border-input bg-background px-2 text-xs"
                                        >
                                            <option :value="null">
                                                Chọn vị trí
                                            </option>
                                            <option
                                                v-for="location in warehouseLocations"
                                                :key="location.id"
                                                :value="location.id"
                                            >
                                                {{ location.location_code }} ·
                                                {{ location.zone
                                                }}{{
                                                    location.is_quarantine
                                                        ? ' · Cách ly'
                                                        : ''
                                                }}
                                            </option>
                                        </select>
                                    </td>
                                    <td class="p-2">
                                        <Input
                                            v-model="line.discrepancy_reason"
                                            class="h-9 text-xs"
                                            placeholder="Bắt buộc nếu lệch"
                                        />
                                    </td>
                                    <td class="p-2 text-right">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-rose-300"
                                            title="Xóa dòng"
                                            @click="removeGrnLine(index)"
                                            ><X class="size-4"
                                        /></Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="grid gap-4 lg:grid-cols-[1fr_1fr]">
                    <div class="flex flex-col gap-1.5">
                        <Label>Ghi chú phiếu</Label
                        ><textarea
                            v-model="grnForm.notes"
                            rows="3"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Tình trạng xe, niêm phong, nhiệt độ, biên bản giao nhận..."
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label>Hóa đơn / ảnh giao nhận</Label
                        ><input
                            type="file"
                            multiple
                            accept="image/*,.pdf"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-xs"
                            @change="handleGrnFiles"
                        />
                        <div
                            v-if="grnFiles.length"
                            class="mt-1 flex flex-wrap gap-1.5"
                        >
                            <span
                                v-for="(file, index) in grnFiles"
                                :key="`${file.name}-${index}`"
                                class="inline-flex max-w-full items-center gap-1 rounded-full bg-muted px-2 py-1 text-[10px] text-foreground"
                                ><span class="max-w-[220px] truncate">{{
                                    file.name
                                }}</span
                                ><button
                                    type="button"
                                    class="text-rose-300"
                                    @click="removeGrnFile(index)"
                                >
                                    <X class="size-3" /></button
                            ></span>
                        </div>
                    </div>
                </div>
                <div
                    v-if="grnErrors.length"
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
                    <p class="text-[11px] text-muted-foreground">
                        Phiếu có chênh lệch sẽ ở trạng thái chờ xác minh, chưa
                        cộng vào tồn kho.
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
                                    : 'Tạo phiếu GRN'
                            }}</Button
                        >
                    </div>
                </div>
            </form>
        </div>
    </div>
    </Teleport>
</template>
