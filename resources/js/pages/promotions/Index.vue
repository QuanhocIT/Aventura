<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Tag,
    Plus,
    Sparkles,
    ShieldAlert,
    Check,
    X,
    ShieldCheck,
    AlertTriangle,
    Brain,
    ShoppingBag,
    Zap,
    Network,
    RefreshCw,
    Trash2,
    Pencil,
    QrCode,
    BarChart3,
    Bell,
    Search,
    Printer,
    Download,
    Loader2,
    Gift,
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Pagination } from '@/components/super-admin';
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
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type PromotionStatus =
    | 'pending_approval'
    | 'paused'
    | 'scheduled'
    | 'running'
    | 'expired'
    | 'exhausted';

type PromotionConditions = {
    day_of_week?: number[];
    time_range?: { start: string; end: string };
    min_items?: number;
    first_order_only?: boolean;
};

type Promotion = {
    id: number;
    name: string;
    code: string | null;
    type: 'percent' | 'fixed_amount';
    value: number;
    min_order_amount: number;
    max_discount_amount: number;
    /** Chuỗi đã format để hiển thị (d/m/Y H:i). */
    start_date: string | null;
    end_date: string | null;
    /** Chuỗi ISO cho <input type="datetime-local">. */
    start_date_input: string | null;
    end_date_input: string | null;
    is_active: boolean;
    is_approved: boolean;
    status: PromotionStatus;
    branch_id: number | null;
    branch_name: string | null;
    budget_cap: number | null;
    budget_spent: number;
    auto_deactivate_on_budget: boolean;
    is_stackable: boolean;
    stacking_priority: number;
    stacking_group: string | null;
    conditions: PromotionConditions | null;
    usage_limit: number | null;
    usage_limit_per_customer: number | null;
    usage_count: number;
    usage_discount_total: number;
    created_by_name: string;
    approved_by_name: string;
    created_by_id: number | null;
};

type FraudAlert = {
    id: string;
    employee_id: number | null;
    employee_name: string;
    violation_type: string;
    category?: string;
    domain?: string;
    severity: 'low' | 'medium' | 'high' | 'critical';
    description: string;
    penalty_amount: number;
    occurred_at: string;
    risk_score: number;
    reason: string;
};

type VoucherLog = {
    id: number;
    user_name: string;
    user_role: string;
    event: string;
    action: string;
    action_label: string;
    subject_id: number;
    old_values: { discount_amount?: number; total_amount?: number };
    new_values: { discount_amount?: number; total_amount?: number };
    ip_address: string;
    user_agent: string;
    created_at: string;
};

type ComboRule = {
    item_a: string;
    item_b: string;
    support: number;
    confidence: number;
    lift: number;
    co_occurrence: number;
};

type ProductOption = {
    id: number;
    name: string;
    price: number;
};

type BranchOption = {
    id: number;
    name: string;
};

const props = defineProps<{
    promotions: Promotion[];
    summary: {
        total: number;
        expired: number;
        pending_approval: number;
        exhausted: number;
        running: number;
    };
    fraudAlerts: FraudAlert[];
    voucherLogs: VoucherLog[];
    products: ProductOption[];
    branches: BranchOption[];
    filters: { search: string; status: string; branch: string };
    printableIds: number[];
    pagination: {
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    canManagePrices: boolean;
    canCreatePromotions: boolean;
    canRunAnalytics: boolean;
    activeBranchId: number | null;
    auth: {
        user: {
            id: number;
            name: string;
            roles: string[];
        };
    };
}>();

// --- STATE ---
const activeTab = ref<'promotions' | 'combo' | 'fraud'>('promotions');
const showAddModal = ref(false);
const showQuickComboModal = ref(false);
const showEditModal = ref(false);
const showQrModal = ref(false);
const editingPromotion = ref<Promotion | null>(null);
const isAnalyzing = ref(false);
const hasRunAnalysis = ref(false);
const analysisError = ref<string | null>(null);
const analysisResults = ref<{
    total_orders: number;
    rules: ComboRule[];
    source: string;
} | null>(null);

// QR state
const qrPromotion = ref<Promotion | null>(null);
const qrSvg = ref<string>('');
const qrDownloadUrl = ref<string>('');
const isLoadingQr = ref(false);

// User check roles
const isOwner = props.auth.user.roles.includes('owner');

// --- FILTERS ---
const searchTerm = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? 'all');
const branchFilter = ref(props.filters.branch ?? 'all');

const STATUS_META: Record<
    PromotionStatus,
    { label: string; hint: string; class: string }
> = {
    running: {
        label: 'Đang chạy',
        hint: 'Đã duyệt, trong thời gian hiệu lực, còn ngân sách và còn lượt.',
        class: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-400',
    },
    scheduled: {
        label: 'Chờ tới ngày',
        hint: 'Đã duyệt nhưng chưa tới ngày bắt đầu.',
        class: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-400',
    },
    expired: {
        label: 'Hết hạn',
        hint: 'Đã qua ngày kết thúc — thu ngân áp mã sẽ bị từ chối.',
        class: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-400',
    },
    exhausted: {
        label: 'Hết ngân sách/lượt',
        hint: 'Đã tiêu hết ngân sách hoặc dùng hết số lượt cho phép.',
        class: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-400',
    },
    paused: {
        label: 'Tạm dừng',
        hint: 'Đã tắt thủ công.',
        class: 'border-slate-200 bg-slate-100 text-slate-500 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-400',
    },
    pending_approval: {
        label: 'Chờ duyệt',
        hint: 'Chưa được Chủ nhà hàng phê duyệt nên chưa áp được.',
        class: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-400',
    },
};

const STATUS_FILTER_OPTIONS: Array<{ value: string; label: string }> = [
    { value: 'all', label: 'Tất cả trạng thái' },
    { value: 'running', label: 'Đang chạy' },
    { value: 'scheduled', label: 'Chờ tới ngày' },
    { value: 'pending_approval', label: 'Chờ duyệt' },
    { value: 'exhausted', label: 'Hết ngân sách/lượt' },
    { value: 'expired', label: 'Hết hạn' },
    { value: 'paused', label: 'Tạm dừng' },
];

const statusMeta = (p: Promotion) =>
    STATUS_META[p.status] ?? STATUS_META.paused;

const applyFilters = (page?: number) => {
    router.get(
        '/promotions',
        {
            search: searchTerm.value,
            status: statusFilter.value,
            branch: branchFilter.value,
            page,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const resetFilters = () => {
    searchTerm.value = '';
    statusFilter.value = 'all';
    branchFilter.value = 'all';
    applyFilters();
};

const goToPage = (url: string) => {
    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

// Đọc từ `summary` do server tính trên tập CHƯA lọc. Nếu đếm trên
// props.promotions thì banner sẽ về 0 ngay khi người dùng chọn một bộ lọc khác.
const expiredCount = computed(() => props.summary.expired);
const pendingApprovalCount = computed(() => props.summary.pending_approval);
const exhaustedCount = computed(() => props.summary.exhausted);

// Cảnh báo gian lận liên quan tới voucher — lọc theo `category` do backend gắn,
// không so khớp chuỗi hiển thị (chuỗi tiếng Việt không bao giờ chứa "voucher"
// nên bộ đếm cũ luôn bằng 0 dù badge trên tab hiện hàng chục cảnh báo).
const voucherAlertCount = computed(
    () => props.fraudAlerts.filter((a) => a.category === 'voucher').length,
);

// Cảnh báo đối soát mua hàng thuộc module Kho — trước đây bị trộn thẳng vào
// danh sách vi phạm của trang Khuyến mãi, gây nhiễu cho người đọc.
const salesAlerts = computed(() =>
    props.fraudAlerts.filter((a) => a.domain !== 'purchasing'),
);

const purchasingAlerts = computed(() =>
    props.fraudAlerts.filter((a) => a.domain === 'purchasing'),
);

const SEVERITY_STYLES: Record<string, string> = {
    critical:
        'border-rose-300 bg-rose-50/60 shadow-md shadow-rose-500/5 dark:border-rose-900/50 dark:bg-rose-950/25',
    high: 'border-orange-200 bg-orange-50/50 dark:border-orange-900/40 dark:bg-orange-950/20',
    medium: 'border-amber-200 bg-amber-50/40 dark:border-amber-900/30 dark:bg-amber-950/15',
    low: 'border-slate-200 bg-slate-50/60 dark:border-slate-800 dark:bg-slate-900/25',
};

const SEVERITY_BADGES: Record<string, string> = {
    critical:
        'border-rose-200 bg-rose-100 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-400',
    high: 'border-orange-200 bg-orange-100 text-orange-700 dark:border-orange-900/50 dark:bg-orange-950/40 dark:text-orange-400',
    medium: 'border-amber-200 bg-amber-100 text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-400',
    low: 'border-slate-200 bg-slate-100 text-slate-600 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-400',
};

const severityStyle = (a: FraudAlert) =>
    SEVERITY_STYLES[a.severity] ?? SEVERITY_STYLES.medium;

const severityBadge = (a: FraudAlert) =>
    SEVERITY_BADGES[a.severity] ?? SEVERITY_BADGES.medium;

const WEEKDAYS: Array<{ value: number; label: string }> = [
    { value: 1, label: 'T2' },
    { value: 2, label: 'T3' },
    { value: 3, label: 'T4' },
    { value: 4, label: 'T5' },
    { value: 5, label: 'T6' },
    { value: 6, label: 'T7' },
    { value: 7, label: 'CN' },
];

/** Tóm tắt điều kiện áp dụng để hiển thị gọn trong bảng. */
const conditionSummary = (p: Promotion): string[] => {
    const c = p.conditions;

    if (!c) {
        return [];
    }

    const parts: string[] = [];

    if (c.day_of_week?.length) {
        parts.push(
            c.day_of_week
                .map((d) => WEEKDAYS.find((w) => w.value === d)?.label ?? d)
                .join(', '),
        );
    }

    if (c.time_range) {
        parts.push(`${c.time_range.start}–${c.time_range.end}`);
    }

    if (c.min_items) {
        parts.push(`từ ${c.min_items} món`);
    }

    if (c.first_order_only) {
        parts.push('đơn đầu tiên');
    }

    return parts;
};

// --- FORM DEFAULTS ---
const blankPromotion = () => ({
    name: '',
    code: '',
    type: 'percent' as 'percent' | 'fixed_amount',
    value: 0,
    min_order_amount: 0,
    max_discount_amount: 0,
    start_date: '',
    end_date: '',
    branch_id: null as number | null,
    budget_cap: null as number | null,
    auto_deactivate_on_budget: false,
    usage_limit: null as number | null,
    usage_limit_per_customer: null as number | null,
    is_stackable: false,
    stacking_priority: 0,
    stacking_group: '',
    // 4 điều kiện dưới đây đã được PromotionStackingService::validateConditions
    // xử lý từ đầu nhưng chưa từng có ô nhập nào trên giao diện.
    condition_days: [] as number[],
    condition_time_start: '',
    condition_time_end: '',
    condition_min_items: null as number | null,
    condition_first_order_only: false,
});

const form = useForm(blankPromotion());
const editForm = useForm(blankPromotion());

// Quick Combo Form
const comboForm = useForm({
    name: '',
    item_a_id: null as number | null,
    item_b_id: null as number | null,
    item_a: '',
    item_b: '',
    original_price_a: 0,
    original_price_b: 0,
    combo_price: 0,
    discount_percent: 10,
    notes: '',
});

const comboOriginalTotal = computed(
    () => comboForm.original_price_a + comboForm.original_price_b,
);

// Ô "Tỷ lệ giảm Combo (%)" trước đây hoàn toàn trơ: không watch, không computed,
// và bị transform() loại bỏ khi submit. Giờ nó thực sự điều khiển giá bán.
const syncingCombo = ref(false);

watch(
    () => comboForm.discount_percent,
    (percent) => {
        if (syncingCombo.value || comboOriginalTotal.value <= 0) {
            return;
        }

        const pct = Math.min(100, Math.max(0, Number(percent) || 0));
        syncingCombo.value = true;
        comboForm.combo_price = Math.max(
            0,
            Math.round((comboOriginalTotal.value * (1 - pct / 100)) / 1000) *
                1000,
        );
        void Promise.resolve().then(() => (syncingCombo.value = false));
    },
);

watch(
    () => comboForm.combo_price,
    (price) => {
        if (syncingCombo.value || comboOriginalTotal.value <= 0) {
            return;
        }

        syncingCombo.value = true;
        comboForm.discount_percent = Math.round(
            ((comboOriginalTotal.value - (Number(price) || 0)) /
                comboOriginalTotal.value) *
                100,
        );
        void Promise.resolve().then(() => (syncingCombo.value = false));
    },
);

const comboSavings = computed(
    () => comboOriginalTotal.value - (Number(comboForm.combo_price) || 0),
);

// --- NUMBER HELPERS ---
const numberFormat = (val: number | null | undefined) =>
    new Intl.NumberFormat('vi-VN').format(Number(val) || 0);

/** Ép chuỗi rỗng từ <input type="number"> về null để backend nhận `nullable`. */
const nullableNumber = (val: unknown): number | null => {
    if (val === '' || val === null || val === undefined) {
        return null;
    }

    const parsed = Number(val);

    return Number.isFinite(parsed) ? parsed : null;
};

const promotionPayload = (data: ReturnType<typeof blankPromotion>) => ({
    ...data,
    code: data.code?.trim() || null,
    branch_id: data.branch_id === null ? null : Number(data.branch_id),
    budget_cap: nullableNumber(data.budget_cap),
    usage_limit: nullableNumber(data.usage_limit),
    usage_limit_per_customer: nullableNumber(data.usage_limit_per_customer),
    stacking_group: data.stacking_group?.trim() || null,
    conditions: {
        day_of_week: data.condition_days,
        time_range:
            data.condition_time_start && data.condition_time_end
                ? {
                      start: data.condition_time_start,
                      end: data.condition_time_end,
                  }
                : null,
        min_items: nullableNumber(data.condition_min_items),
        first_order_only: data.condition_first_order_only,
    },
    start_date: data.start_date || null,
    end_date: data.end_date || null,
    // Giảm tối đa chỉ có nghĩa với loại %; gửi kèm ở loại tiền mặt chỉ tạo ra
    // con số rác hiển thị trên bảng.
    max_discount_amount:
        data.type === 'percent'
            ? (nullableNumber(data.max_discount_amount) ?? 0)
            : 0,
});

// --- ACTIONS ---
const openAddModal = () => {
    form.reset();
    form.clearErrors();
    // Mặc định theo phạm vi đang xem, nhưng người dùng NHÌN THẤY và đổi được —
    // trước đây chi nhánh bị trait tự gán ngầm ở backend.
    form.branch_id = props.activeBranchId;
    showAddModal.value = true;
};

const submitAdd = () => {
    form.transform(promotionPayload).post('/promotions', {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
        },
    });
};

const toggleActive = (p: Promotion) => {
    router.patch(`/promotions/${p.id}/toggle`, {}, { preserveScroll: true });
};

const approvePromotion = (p: Promotion) => {
    router.post(`/promotions/${p.id}/approve`, {}, { preserveScroll: true });
};

// Open Edit Promotion Modal
const openEditPromotion = (p: Promotion) => {
    editingPromotion.value = p;
    editForm.clearErrors();
    editForm.name = p.name;
    editForm.code = p.code ?? '';
    editForm.type = p.type;
    editForm.value = p.value;
    editForm.min_order_amount = p.min_order_amount;
    editForm.max_discount_amount = p.max_discount_amount;
    // Dùng bản *_input (Y-m-d\TH:i). Bản hiển thị d/m/Y H:i không được
    // <input type="datetime-local"> chấp nhận, khiến ô ngày trống và request
    // luôn rớt validate `date` ở backend.
    editForm.start_date = p.start_date_input ?? '';
    editForm.end_date = p.end_date_input ?? '';
    editForm.branch_id = p.branch_id;
    editForm.budget_cap = p.budget_cap;
    editForm.auto_deactivate_on_budget = p.auto_deactivate_on_budget;
    editForm.usage_limit = p.usage_limit;
    editForm.usage_limit_per_customer = p.usage_limit_per_customer;
    editForm.is_stackable = p.is_stackable;
    editForm.stacking_priority = p.stacking_priority;
    editForm.stacking_group = p.stacking_group ?? '';
    editForm.condition_days = p.conditions?.day_of_week ?? [];
    editForm.condition_time_start = p.conditions?.time_range?.start ?? '';
    editForm.condition_time_end = p.conditions?.time_range?.end ?? '';
    editForm.condition_min_items = p.conditions?.min_items ?? null;
    editForm.condition_first_order_only =
        p.conditions?.first_order_only ?? false;
    showEditModal.value = true;
};

const submitEdit = () => {
    if (!editingPromotion.value) {
        return;
    }

    editForm
        .transform(promotionPayload)
        .put(`/promotions/${editingPromotion.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showEditModal.value = false;
                editingPromotion.value = null;
                toast.success('Đã cập nhật chương trình khuyến mãi.');
            },
        });
};

const confirmDeletePromotion = async (p: Promotion) => {
    const usedWarning =
        p.usage_count > 0
            ? `\n\nChương trình đã phát sinh ${p.usage_count} lượt áp mã — hệ thống sẽ từ chối xóa để giữ lịch sử đối soát. Hãy dùng nút Tạm dừng.`
            : '';

    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Xóa chương trình khuyến mãi "${p.name}"? Hành động này không thể hoàn tác.${usedWarning}`,
        }))
    ) {
        return;
    }

    router.delete(`/promotions/${p.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã xóa chương trình khuyến mãi.'),
    });
};

// --- QR CODE ---
const openQrModal = async (p: Promotion) => {
    if (!p.code) {
        toast.error(
            'Chương trình này chưa có mã voucher nên không tạo được QR.',
        );

        return;
    }

    qrPromotion.value = p;
    qrSvg.value = '';
    qrDownloadUrl.value = '';
    showQrModal.value = true;
    isLoadingQr.value = true;

    try {
        const res = await fetch(`/promotions/${p.id}/qr`, {
            headers: { Accept: 'application/json' },
        });

        if (!res.ok) {
            const body = await res.json().catch(() => ({}));

            throw new Error(body.message || 'Không tạo được mã QR.');
        }

        const data = await res.json();
        qrSvg.value = data.svg ?? '';
        qrDownloadUrl.value = data.download_url ?? '';
    } catch (e) {
        toast.error(e instanceof Error ? e.message : 'Không tạo được mã QR.');
        showQrModal.value = false;
    } finally {
        isLoadingQr.value = false;
    }
};

const printQrSheet = () => {
    // Server trả về id của MỌI mã khớp bộ lọc; props.promotions chỉ là 20 dòng
    // của trang đang xem nên không dùng làm nguồn được.
    const ids = props.printableIds;

    if (ids.length === 0) {
        toast.error('Chưa có chương trình nào có mã voucher để in.');

        return;
    }

    // POST tới endpoint in — mở tab mới bằng form ẩn để giữ CSRF token.
    const token =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';

    const printForm = document.createElement('form');
    printForm.method = 'POST';
    printForm.action = '/promotions/print-qr-sheet';
    printForm.target = '_blank';

    const appendField = (name: string, value: string) => {
        const field = document.createElement('input');
        field.type = 'hidden';
        field.name = name;
        field.value = value;
        printForm.appendChild(field);
    };

    appendField('_token', token);
    ids.forEach((id) => appendField('ids[]', String(id)));

    document.body.appendChild(printForm);
    printForm.submit();
    document.body.removeChild(printForm);
};

// --- MARKET BASKET ANALYSIS ---
const runBasketAnalysis = async () => {
    if (!props.canRunAnalytics) {
        analysisError.value =
            'Tài khoản của bạn không có quyền Xem báo cáo nên không chạy được phân tích giỏ hàng.';
        toast.error(analysisError.value);

        return;
    }

    isAnalyzing.value = true;
    analysisError.value = null;

    try {
        const res = await fetch('/api/promotions/basket-analysis', {
            headers: { Accept: 'application/json' },
        });

        if (!res.ok) {
            // Trước đây lỗi chỉ đi vào console.error: người dùng thấy nút quay
            // xong rồi màn hình đứng im ở "Hệ thống AI đã sẵn sàng" mãi mãi.
            analysisError.value =
                res.status === 403
                    ? 'Bạn không có quyền chạy phân tích giỏ hàng (cần quyền Xem báo cáo).'
                    : `Không chạy được phân tích giỏ hàng (mã lỗi ${res.status}).`;
            toast.error(analysisError.value);

            return;
        }

        analysisResults.value = await res.json();
        hasRunAnalysis.value = true;
    } catch (e) {
        analysisError.value =
            e instanceof Error
                ? e.message
                : 'Không kết nối được dịch vụ phân tích.';
        toast.error(analysisError.value);
    } finally {
        isAnalyzing.value = false;
    }
};

// Chỉ quét khi người dùng thực sự mở tab Combo. Trước đây onMounted luôn gọi
// ngay lúc vào trang, quét tới 1000 đơn hàng kể cả khi chỉ muốn xem bảng voucher.
const selectTab = (tab: 'promotions' | 'combo' | 'fraud') => {
    activeTab.value = tab;

    if (tab === 'combo' && !hasRunAnalysis.value && !isAnalyzing.value) {
        void runBasketAnalysis();
    }
};

// Open Quick Combo Creator Modal
const openQuickCombo = (rule: ComboRule) => {
    const productA = props.products.find((p) => p.name === rule.item_a);
    const productB = props.products.find((p) => p.name === rule.item_b);

    comboForm.reset();
    comboForm.clearErrors();
    comboForm.name = `Combo Tiết Kiệm: ${rule.item_a} & ${rule.item_b}`;
    comboForm.item_a = rule.item_a;
    comboForm.item_b = rule.item_b;
    comboForm.item_a_id = productA?.id ?? null;
    comboForm.item_b_id = productB?.id ?? null;

    const priceA = productA?.price ?? 0;
    const priceB = productB?.price ?? 0;

    comboForm.original_price_a = priceA;
    comboForm.original_price_b = priceB;

    // Đặt % trước, watcher sẽ tự tính ra giá bán tương ứng.
    syncingCombo.value = true;
    comboForm.combo_price = Math.max(
        0,
        Math.round(((priceA + priceB) * 0.88) / 1000) * 1000,
    );
    void Promise.resolve().then(() => (syncingCombo.value = false));
    comboForm.discount_percent = 12;

    comboForm.notes = `Combo kết hợp khoa học từ phân tích giỏ hàng AI. Món '${rule.item_a}' thường được gọi kèm với '${rule.item_b}'.`;

    showQuickComboModal.value = true;
};

// Submit Quick Combo — tạo món Combo thật trong thực đơn
const createQuickCombo = () => {
    if (!comboForm.item_a_id || !comboForm.item_b_id) {
        toast.error(
            'Không tìm thấy món ăn tương ứng trong thực đơn để tạo combo. Vui lòng kiểm tra lại tên món.',
        );

        return;
    }

    if (comboForm.combo_price >= comboOriginalTotal.value) {
        toast.error(
            'Giá combo phải rẻ hơn tổng giá bán lẻ của hai món thành phần.',
        );

        return;
    }

    comboForm
        .transform((data: typeof comboForm.data) => ({
            name: data.name,
            item_a_id: data.item_a_id,
            item_b_id: data.item_b_id,
            combo_price: data.combo_price,
            notes: data.notes,
        }))
        .post('/promotions/combos', {
            preserveScroll: true,
            onSuccess: () => {
                showQuickComboModal.value = false;
                comboForm.reset();
            },
        });
};
</script>

<template>
    <Head title="Quản Lý Khuyến Mãi & AI Combo" />

    <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col gap-6 p-6">
        <!-- HEADER -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400"
                >
                    <Tag class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Chiến Lược Khuyến Mãi & Combo Thông Minh
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Số hóa các chương trình Marketing, loại bỏ hoàn toàn lỗ
                        hổng gian lận tiền mặt và tự động đề xuất combo tăng
                        trưởng bằng khoa học giỏ hàng.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Hai trang này trước đây không có bất kỳ đường dẫn nào trỏ
                     tới, kể cả từ sidebar — chỉ vào được bằng cách gõ tay URL. -->
                <Button
                    variant="outline"
                    class="flex h-10 items-center gap-1.5 text-xs font-semibold"
                    @click="router.get('/promotions/analytics')"
                >
                    <BarChart3 class="size-4 text-indigo-600" />
                    Hiệu quả khuyến mãi
                </Button>
                <Button
                    variant="outline"
                    class="flex h-10 items-center gap-1.5 text-xs font-semibold"
                    @click="router.get('/promotions/triggers')"
                >
                    <Bell class="size-4 text-indigo-600" />
                    Trigger tự động
                </Button>
                <!-- Voucher, điểm tích lũy và ưu đãi hạng hội viên cùng cộng
                     dồn trên một hóa đơn nhưng ba màn hình trước đây không hề
                     tham chiếu lẫn nhau. -->
                <Button
                    variant="outline"
                    class="flex h-10 items-center gap-1.5 text-xs font-semibold"
                    @click="router.get('/loyalty')"
                >
                    <Gift class="size-4 text-indigo-600" />
                    Khách hàng thân thiết
                </Button>
                <Button
                    variant="outline"
                    class="flex h-10 items-center gap-1.5 text-xs font-semibold"
                    @click="router.get('/customers')"
                >
                    <Users class="size-4 text-indigo-600" />
                    Khách hàng
                </Button>
                <Button
                    v-if="canManagePrices"
                    variant="outline"
                    class="flex h-10 items-center gap-1.5 text-xs font-semibold"
                    @click="printQrSheet"
                >
                    <Printer class="size-4 text-indigo-600" />
                    In phiếu QR
                </Button>
                <Button
                    v-if="canCreatePromotions"
                    @click="openAddModal"
                    class="flex h-10 items-center gap-1.5 bg-indigo-600 text-xs font-semibold text-white shadow-md shadow-indigo-500/10 transition-all hover:translate-y-[-1px] hover:bg-indigo-700"
                >
                    <Plus class="size-4" />
                    Tạo chương trình khuyến mãi
                </Button>
            </div>
        </div>

        <!-- BANNER: nhắc việc cần xử lý ngay -->
        <div
            v-if="
                expiredCount > 0 ||
                pendingApprovalCount > 0 ||
                exhaustedCount > 0
            "
            class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-xl border border-amber-200 bg-amber-50/60 px-4 py-3 text-xs text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-300"
        >
            <span class="flex items-center gap-1.5 font-bold">
                <AlertTriangle class="size-4" /> Cần xử lý
            </span>
            <span v-if="expiredCount > 0">
                <strong>{{ expiredCount }}</strong> chương trình đã quá hạn kết
                thúc — thu ngân áp mã sẽ bị từ chối.
            </span>
            <span v-if="pendingApprovalCount > 0">
                <strong>{{ pendingApprovalCount }}</strong> chương trình đang
                chờ Chủ nhà hàng phê duyệt.
            </span>
            <span v-if="exhaustedCount > 0">
                <strong>{{ exhaustedCount }}</strong> chương trình đã cạn ngân
                sách hoặc hết lượt dùng.
            </span>
        </div>

        <!-- TABS BAR -->
        <div class="-mt-2 flex border-b border-slate-200 dark:border-slate-800">
            <button
                @click="selectTab('promotions')"
                :class="[
                    'flex items-center gap-2 border-b-2 px-4 py-3 text-xs font-semibold transition-all',
                    activeTab === 'promotions'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400',
                ]"
            >
                <Tag class="size-4" />
                Quản lý Khuyến mãi & Voucher
            </button>
            <button
                @click="selectTab('combo')"
                :class="[
                    'flex items-center gap-2 border-b-2 px-4 py-3 text-xs font-semibold transition-all',
                    activeTab === 'combo'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400',
                ]"
            >
                <Brain class="size-4" />
                AI Combo Suggestion (Giỏ hàng)
                <span
                    class="animate-pulse rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300"
                    >AI Active</span
                >
            </button>
            <button
                @click="selectTab('fraud')"
                :class="[
                    'flex items-center gap-2 border-b-2 px-4 py-3 text-xs font-semibold transition-all',
                    activeTab === 'fraud'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400',
                ]"
            >
                <ShieldAlert class="size-4" />
                AI Fraud Auditing
                <span
                    v-if="fraudAlerts.length > 0"
                    class="rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-950/60 dark:text-rose-300"
                >
                    {{ fraudAlerts.length }} Cảnh báo
                </span>
            </button>
        </div>

        <!-- TAB 1: PROMOTIONS LIST -->
        <div
            v-if="activeTab === 'promotions'"
            class="animate-fade-in space-y-6"
        >
            <!-- BỘ LỌC -->
            <div
                class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3 sm:flex-row sm:items-center dark:border-slate-800 dark:bg-slate-900/30"
            >
                <div class="relative flex-1">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                    />
                    <Input
                        v-model="searchTerm"
                        placeholder="Tìm theo tên chương trình hoặc mã voucher..."
                        class="h-9 pl-9 text-xs"
                        @keyup.enter="applyFilters()"
                    />
                </div>
                <select
                    v-model="statusFilter"
                    class="h-9 rounded-md border border-slate-200 bg-background px-3 text-xs focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none dark:border-slate-800"
                    @change="applyFilters()"
                >
                    <option
                        v-for="opt in STATUS_FILTER_OPTIONS"
                        :key="opt.value"
                        :value="opt.value"
                    >
                        {{ opt.label }}
                    </option>
                </select>
                <select
                    v-model="branchFilter"
                    class="h-9 rounded-md border border-slate-200 bg-background px-3 text-xs focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none dark:border-slate-800"
                    @change="applyFilters()"
                >
                    <option value="all">Mọi phạm vi</option>
                    <option value="chain">Chỉ mã toàn chuỗi</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">
                        {{ b.name }}
                    </option>
                </select>
                <div class="flex items-center gap-2">
                    <Button
                        size="sm"
                        class="h-9 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                        @click="applyFilters()"
                    >
                        Lọc
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        class="h-9 text-xs"
                        @click="resetFilters"
                    >
                        Xóa lọc
                    </Button>
                </div>
            </div>

            <Card class="shadow-sm">
                <CardHeader class="border-b pb-3">
                    <CardTitle class="text-sm font-bold"
                        >Danh Sách Chương Trình Khuyến Mãi / Voucher</CardTitle
                    >
                    <CardDescription>
                        Quản lý tạo được chương trình nhưng phải chờ Chủ nhà
                        hàng phê duyệt mới có hiệu lực. Chỉ Chủ nhà hàng được
                        bật/tắt, xóa và phê duyệt — nhằm loại bỏ nguy cơ thu
                        ngân thông đồng áp mã vô tội vạ.
                    </CardDescription>
                </CardHeader>

                <CardContent class="p-0">
                    <div
                        v-if="promotions.length === 0"
                        class="flex flex-col items-center gap-3 py-20 text-center text-slate-500"
                    >
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40"
                        >
                            <Tag class="size-7" />
                        </div>
                        <p class="font-bold text-slate-800 dark:text-slate-200">
                            {{
                                filters.search || filters.status !== 'all'
                                    ? 'Không có chương trình nào khớp bộ lọc'
                                    : 'Không có chương trình khuyến mãi nào'
                            }}
                        </p>
                        <p class="max-w-sm text-xs">
                            {{
                                filters.search || filters.status !== 'all'
                                    ? 'Thử xóa bộ lọc để xem toàn bộ danh sách.'
                                    : 'Nhấn nút "Tạo chương trình khuyến mãi" ở góc trên bên phải để bắt đầu thiết lập ưu đãi cho nhà hàng.'
                            }}
                        </p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b bg-slate-100 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                                >
                                    <th class="p-4">Tên chương trình</th>
                                    <th class="p-4">Mã Voucher</th>
                                    <th class="p-4">Phạm vi</th>
                                    <th class="p-4">Loại giảm</th>
                                    <th class="p-4">Giá trị giảm</th>
                                    <th class="p-4">Đơn hàng tối thiểu</th>
                                    <th class="p-4">Giảm tối đa</th>
                                    <th class="p-4">Thời gian hiệu lực</th>
                                    <th class="p-4">Lượt dùng / Ngân sách</th>
                                    <th class="p-4">Người tạo</th>
                                    <th class="p-4">Trạng thái</th>
                                    <th class="p-4">Phê duyệt</th>
                                    <th class="p-4">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr
                                    v-for="p in promotions"
                                    :key="p.id"
                                    class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                                >
                                    <td class="p-4">
                                        <div
                                            class="font-bold text-slate-800 dark:text-slate-200"
                                        >
                                            {{ p.name }}
                                        </div>
                                        <div
                                            v-if="p.is_stackable"
                                            class="mt-0.5 text-[10px] font-semibold text-sky-600 dark:text-sky-400"
                                        >
                                            Cộng dồn được
                                            <span v-if="p.stacking_group"
                                                >· nhóm
                                                {{ p.stacking_group }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="conditionSummary(p).length"
                                            class="mt-1 flex flex-wrap gap-1"
                                        >
                                            <span
                                                v-for="(
                                                    part, i
                                                ) in conditionSummary(p)"
                                                :key="i"
                                                class="rounded border border-violet-200 bg-violet-50 px-1.5 py-0.5 text-[9px] font-bold text-violet-700 dark:border-violet-900/40 dark:bg-violet-950/30 dark:text-violet-300"
                                            >
                                                {{ part }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span
                                            v-if="p.code"
                                            class="rounded border border-indigo-100 bg-indigo-50 px-2.5 py-1 font-mono font-bold text-indigo-600 dark:border-indigo-900/40 dark:bg-indigo-950/40 dark:text-indigo-400"
                                        >
                                            {{ p.code }}
                                        </span>
                                        <span v-else class="text-slate-400"
                                            >— (không mã)</span
                                        >
                                    </td>
                                    <!-- Cột này trước đây không tồn tại: chi nhánh
                                         bị trait gán ngầm nên Owner không hề biết
                                         mã của mình chỉ chạy ở một chi nhánh. -->
                                    <td class="p-4">
                                        <span
                                            v-if="p.branch_id"
                                            class="rounded border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-300"
                                        >
                                            {{
                                                p.branch_name ||
                                                'Chi nhánh #' + p.branch_id
                                            }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-[10px] font-semibold text-slate-500"
                                            >Toàn chuỗi</span
                                        >
                                    </td>
                                    <td class="p-4">
                                        <span
                                            v-if="p.type === 'percent'"
                                            class="text-slate-600 dark:text-slate-300"
                                            >Giảm theo %</span
                                        >
                                        <span
                                            v-else
                                            class="text-slate-600 dark:text-slate-300"
                                            >Khấu trừ tiền mặt</span
                                        >
                                    </td>
                                    <td
                                        class="p-4 font-bold text-slate-800 dark:text-slate-100"
                                    >
                                        {{
                                            p.type === 'percent'
                                                ? `${p.value}%`
                                                : `${numberFormat(p.value)}đ`
                                        }}
                                    </td>
                                    <td
                                        class="p-4 font-mono text-slate-600 dark:text-slate-400"
                                    >
                                        {{ numberFormat(p.min_order_amount) }}đ
                                    </td>
                                    <!-- Chỉ hiển thị với loại %; với loại tiền mặt
                                         logic tính tiền không dùng tới trường này. -->
                                    <td
                                        class="p-4 font-mono text-slate-600 dark:text-slate-400"
                                    >
                                        {{
                                            p.type === 'percent' &&
                                            p.max_discount_amount > 0
                                                ? `${numberFormat(p.max_discount_amount)}đ`
                                                : '—'
                                        }}
                                    </td>
                                    <td
                                        class="p-4 font-mono text-slate-500 dark:text-slate-400"
                                    >
                                        <div v-if="p.start_date || p.end_date">
                                            <div>
                                                Từ: {{ p.start_date || '—' }}
                                            </div>
                                            <div class="mt-0.5">
                                                Đến: {{ p.end_date || '—' }}
                                            </div>
                                        </div>
                                        <span v-else>Không giới hạn</span>
                                    </td>
                                    <td class="p-4">
                                        <div
                                            class="font-mono font-bold text-slate-700 dark:text-slate-200"
                                        >
                                            {{ p.usage_count }}
                                            <span
                                                v-if="p.usage_limit"
                                                class="text-slate-400"
                                                >/ {{ p.usage_limit }}</span
                                            >
                                            lượt
                                        </div>
                                        <div
                                            class="mt-0.5 font-mono text-[10px] text-slate-500"
                                        >
                                            Đã giảm:
                                            {{
                                                numberFormat(
                                                    p.usage_discount_total,
                                                )
                                            }}đ
                                            <template v-if="p.budget_cap">
                                                /
                                                {{
                                                    numberFormat(p.budget_cap)
                                                }}đ
                                            </template>
                                        </div>
                                    </td>
                                    <td class="p-4 text-slate-500">
                                        {{ p.created_by_name }}
                                    </td>
                                    <!-- Badge đọc `status` do backend tính (đối
                                         chiếu cả ngày hết hạn, ngân sách và lượt),
                                         không còn chỉ đọc mỗi cờ is_active — cờ đó
                                         chỉ được cron hạ mỗi ngày một lần nên mã đã
                                         hết hạn vẫn hiện xanh "Hoạt động". -->
                                    <td class="p-4">
                                        <button
                                            :class="[
                                                'rounded border px-2 py-0.5 text-[10px] font-bold transition-all',
                                                statusMeta(p).class,
                                                canManagePrices
                                                    ? 'cursor-pointer'
                                                    : 'cursor-default',
                                            ]"
                                            :title="statusMeta(p).hint"
                                            :disabled="!canManagePrices"
                                            @click="
                                                canManagePrices &&
                                                toggleActive(p)
                                            "
                                        >
                                            {{ statusMeta(p).label }}
                                        </button>
                                    </td>
                                    <td class="p-4">
                                        <div
                                            v-if="p.is_approved"
                                            class="flex items-center gap-1 font-semibold text-emerald-600"
                                        >
                                            <ShieldCheck
                                                class="size-4 shrink-0"
                                            />
                                            Đã duyệt
                                        </div>
                                        <div v-else>
                                            <Button
                                                v-if="
                                                    isOwner &&
                                                    p.created_by_id !==
                                                        auth.user.id
                                                "
                                                @click="approvePromotion(p)"
                                                size="sm"
                                                class="h-7 bg-indigo-600 text-[10px] font-semibold text-white hover:bg-indigo-700"
                                            >
                                                Duyệt ngay
                                            </Button>
                                            <span
                                                v-else
                                                class="flex items-center gap-1 font-semibold text-amber-500"
                                                :title="
                                                    isOwner
                                                        ? 'Không thể tự duyệt chương trình do chính mình tạo.'
                                                        : 'Chờ Chủ nhà hàng phê duyệt.'
                                                "
                                            >
                                                <AlertTriangle
                                                    class="size-3.5"
                                                />
                                                Chờ duyệt
                                            </span>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-1.5">
                                            <!-- Nút QR: trước đây 2 route QR tồn
                                                 tại nhưng không có nút nào gọi. -->
                                            <button
                                                v-if="p.code"
                                                @click="openQrModal(p)"
                                                class="cursor-pointer rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-sky-50 hover:text-sky-600 dark:hover:bg-sky-950/30"
                                                title="Mã QR voucher"
                                            >
                                                <QrCode class="size-3.5" />
                                            </button>
                                            <!-- Sửa: mở cho mọi tài khoản có
                                                 quyền manage_orders, đúng bằng
                                                 gate của route PUT ở backend.
                                                 Trước đây UI chỉ cho Owner nên
                                                 Quản lý tạo xong không sửa được. -->
                                            <button
                                                v-if="canCreatePromotions"
                                                @click="openEditPromotion(p)"
                                                class="cursor-pointer rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/30"
                                                title="Chỉnh sửa"
                                            >
                                                <Pencil class="size-3.5" />
                                            </button>
                                            <button
                                                v-if="canManagePrices"
                                                @click="
                                                    confirmDeletePromotion(p)
                                                "
                                                class="cursor-pointer rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30"
                                                :title="
                                                    p.usage_count > 0
                                                        ? 'Đã phát sinh giao dịch — hãy tạm dừng thay vì xóa'
                                                        : 'Xóa'
                                                "
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- index() trước đây ->get() toàn bộ, đổ mọi chương trình
                         vào một bảng duy nhất. -->
                    <div v-if="pagination.last_page > 1" class="border-t p-3">
                        <Pagination
                            as="button"
                            :links="pagination.links"
                            :current-page="pagination.current_page"
                            :last-page="pagination.last_page"
                            :total="pagination.total"
                            class="border-0 p-0"
                            @navigate="goToPage"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- TAB 2: AI COMBO SUGGESTION -->
        <div v-if="activeTab === 'combo'" class="animate-fade-in space-y-6">
            <!-- INTRO AI CARD -->
            <div
                class="relative flex flex-col justify-between gap-6 overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-900 via-indigo-950 to-slate-900 p-6 text-white shadow-xl md:flex-row md:items-center"
            >
                <div
                    class="absolute top-0 right-0 translate-x-12 translate-y-[-10px] opacity-10"
                >
                    <Brain class="size-60" />
                </div>

                <div class="relative z-10 max-w-2xl space-y-2">
                    <span
                        class="flex w-max items-center gap-1.5 rounded-full border border-indigo-400/30 bg-indigo-500/20 px-2.5 py-0.5 text-[10px] font-bold tracking-widest text-indigo-300 uppercase"
                    >
                        <Sparkles class="size-3" /> Trí Tuệ Nhân Tạo Analytics
                    </span>
                    <h2 class="text-xl font-bold tracking-tight">
                        Thuật Toán Phân Tích Giỏ Hàng (Market Basket Analysis)
                    </h2>
                    <p class="text-xs text-indigo-200">
                        Định kỳ, hệ thống sẽ đẩy bất đồng bộ dữ liệu sạch từ
                        bảng đơn hàng sang Python Microservice. Tại đây, thư
                        viện Pandas & Scikit-learn (Association Rules / Apriori)
                        sẽ liên kết chéo các món ăn thường được mua cùng nhau để
                        gợi ý các gói Combo mang lại biên lợi nhuận thực tế cao
                        nhất.
                    </p>
                </div>

                <div class="relative z-10 shrink-0">
                    <Button
                        @click="runBasketAnalysis"
                        :disabled="isAnalyzing"
                        class="flex h-11 items-center gap-2 bg-white px-5 font-bold text-indigo-950 shadow-lg transition-all hover:bg-slate-100 active:scale-95"
                    >
                        <RefreshCw
                            class="size-4 animate-spin"
                            v-if="isAnalyzing"
                        />
                        <Brain class="size-4 text-indigo-600" v-else />
                        {{
                            isAnalyzing
                                ? 'Đang phân tích dữ liệu...'
                                : 'Bắt đầu quét giỏ hàng AI'
                        }}
                    </Button>
                </div>
            </div>

            <!-- RESULTS LIST -->
            <Card class="shadow-sm">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                >
                    <div>
                        <CardTitle class="text-sm font-bold"
                            >Đề Xuất Combo Tối Ưu Doanh Số & Kích Cầu Trúng
                            Đích</CardTitle
                        >
                        <CardDescription>
                            Dưới đây là các cặp sản phẩm có mối liên kết hữu cơ
                            mạnh nhất được tìm thấy từ dữ liệu giao dịch thực
                            tế.
                        </CardDescription>
                    </div>

                    <div
                        v-if="analysisResults"
                        class="flex shrink-0 flex-col items-end gap-1"
                    >
                        <span
                            class="rounded-full border border-indigo-100 bg-indigo-50 px-2.5 py-1 font-mono text-[10px] font-bold text-indigo-600"
                        >
                            Nguồn: {{ analysisResults.source }}
                        </span>
                        <span class="font-mono text-[10px] text-slate-400">
                            Đã phân tích
                            {{ numberFormat(analysisResults.total_orders) }} đơn
                        </span>
                    </div>
                </CardHeader>

                <CardContent class="p-6">
                    <!-- Lỗi phân tích trước đây chỉ đi vào console.error nên
                         người dùng không hiểu vì sao màn hình đứng im. -->
                    <div
                        v-if="analysisError"
                        class="flex flex-col items-center gap-3 py-20 text-center"
                    >
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-950/40"
                        >
                            <AlertTriangle class="size-7" />
                        </div>
                        <p class="font-bold text-rose-700 dark:text-rose-400">
                            Không chạy được phân tích giỏ hàng
                        </p>
                        <p class="max-w-sm text-xs text-slate-500">
                            {{ analysisError }}
                        </p>
                        <Button
                            v-if="canRunAnalytics"
                            size="sm"
                            variant="outline"
                            class="mt-1 h-8 text-xs"
                            @click="runBasketAnalysis"
                        >
                            <RefreshCw class="mr-1 size-3.5" /> Thử lại
                        </Button>
                    </div>

                    <div
                        v-else-if="isAnalyzing && !analysisResults"
                        class="flex flex-col items-center gap-3 py-20 text-center text-slate-500"
                    >
                        <Loader2 class="size-8 animate-spin text-indigo-600" />
                        <p class="text-xs">
                            Đang quét dữ liệu giỏ hàng, vui lòng đợi...
                        </p>
                    </div>

                    <div
                        v-else-if="!analysisResults"
                        class="flex flex-col items-center gap-3 py-20 text-center text-slate-500"
                    >
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40"
                        >
                            <Network class="size-7 animate-pulse" />
                        </div>
                        <p class="font-bold text-slate-800 dark:text-slate-200">
                            Hệ thống AI đã sẵn sàng
                        </p>
                        <p class="max-w-sm text-xs">
                            Nhấn nút "Bắt đầu quét giỏ hàng AI" để hệ thống tính
                            toán các chỉ số liên kết sản phẩm.
                        </p>
                    </div>

                    <div
                        v-else-if="analysisResults.rules.length === 0"
                        class="py-20 text-center text-slate-500"
                    >
                        <p class="font-bold text-slate-800 dark:text-slate-200">
                            Không đủ dữ liệu phân tích
                        </p>
                        <p class="text-xs">
                            Cần có tối thiểu các đơn hàng hoàn thành chứa từ 2
                            món trở lên để tính toán luật liên kết.
                        </p>
                    </div>

                    <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <Card
                            v-for="(rule, idx) in analysisResults.rules"
                            :key="idx"
                            class="group overflow-hidden border-slate-100 shadow-xs transition-all hover:border-indigo-100 hover:shadow-md dark:border-slate-800"
                        >
                            <div
                                class="flex h-full flex-col justify-between gap-4 p-5"
                            >
                                <div class="space-y-3">
                                    <!-- Association Title -->
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-50 text-xs font-bold text-indigo-600 dark:bg-indigo-950/60"
                                                >{{ idx + 1 }}</span
                                            >
                                            <span
                                                class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                                >Hành vi liên kết món</span
                                            >
                                        </div>

                                        <span
                                            class="rounded-full border border-emerald-100 bg-emerald-50 px-2 py-0.5 font-mono text-[9px] font-black text-emerald-600 dark:border-emerald-900/30 dark:bg-emerald-950/20 dark:text-emerald-400"
                                        >
                                            Lift: {{ rule.lift }}
                                        </span>
                                    </div>

                                    <!-- Mon A -> Mon B visual connection -->
                                    <div
                                        class="flex items-center justify-between gap-4 rounded-xl border border-slate-100 bg-slate-50 p-3.5 dark:border-slate-800 dark:bg-slate-900/40"
                                    >
                                        <div class="flex-1 text-center">
                                            <p
                                                class="text-[10px] font-bold text-slate-400"
                                            >
                                                Nếu khách gọi
                                            </p>
                                            <p
                                                class="mt-1 text-xs font-black text-indigo-600 dark:text-indigo-400"
                                            >
                                                {{ rule.item_a }}
                                            </p>
                                        </div>

                                        <Zap
                                            class="size-4 shrink-0 animate-pulse text-amber-500"
                                        />

                                        <div class="flex-1 text-center">
                                            <p
                                                class="text-[10px] font-bold text-slate-400"
                                            >
                                                Họ sẽ gọi thêm
                                            </p>
                                            <p
                                                class="mt-1 text-xs font-black text-indigo-600 dark:text-indigo-400"
                                            >
                                                {{ rule.item_b }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Association stats -->
                                    <div
                                        class="grid grid-cols-3 gap-2 pt-2 text-center"
                                    >
                                        <div
                                            class="rounded-lg bg-slate-50/50 p-2 dark:bg-slate-900/20"
                                        >
                                            <p
                                                class="text-[9px] font-bold text-slate-400 uppercase"
                                            >
                                                Độ tin cậy
                                            </p>
                                            <p
                                                class="mt-0.5 text-xs font-extrabold text-slate-800 dark:text-slate-200"
                                            >
                                                {{
                                                    Math.round(
                                                        rule.confidence * 100,
                                                    )
                                                }}%
                                            </p>
                                        </div>
                                        <div
                                            class="rounded-lg bg-slate-50/50 p-2 dark:bg-slate-900/20"
                                        >
                                            <p
                                                class="text-[9px] font-bold text-slate-400 uppercase"
                                            >
                                                Độ phổ biến
                                            </p>
                                            <p
                                                class="mt-0.5 text-xs font-extrabold text-slate-800 dark:text-slate-200"
                                            >
                                                {{
                                                    Math.round(
                                                        rule.support * 100,
                                                    )
                                                }}%
                                            </p>
                                        </div>
                                        <div
                                            class="rounded-lg bg-slate-50/50 p-2 dark:bg-slate-900/20"
                                        >
                                            <p
                                                class="text-[9px] font-bold text-slate-400 uppercase"
                                            >
                                                Số đơn cùng gọi
                                            </p>
                                            <p
                                                class="mt-0.5 text-xs font-extrabold text-slate-800 dark:text-slate-200"
                                            >
                                                {{ rule.co_occurrence }} đơn
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action button -->
                                <Button
                                    v-if="canManagePrices"
                                    @click="openQuickCombo(rule)"
                                    class="flex h-8 w-full items-center justify-center gap-1.5 bg-slate-800 text-[10px] font-bold text-white transition-all group-hover:bg-indigo-600 group-hover:text-white hover:bg-slate-900"
                                >
                                    <ShoppingBag class="size-3.5" />
                                    Thiết lập Combo đẩy số ngay
                                </Button>
                            </div>
                        </Card>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- TAB 3: AUDITING & FRAUD -->
        <div v-if="activeTab === 'fraud'" class="animate-fade-in space-y-6">
            <!-- WARNING ROW -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- RED FLAGS COUNTER -->
                <Card
                    class="overflow-hidden border-rose-100 bg-rose-50/20 shadow-sm dark:border-rose-950/20 dark:bg-rose-950/10"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardDescription
                            class="text-xs font-bold tracking-wider text-rose-500 uppercase"
                            >Cảnh báo gian lận AI</CardDescription
                        >
                        <ShieldAlert
                            class="size-5 animate-bounce text-rose-600"
                        />
                    </CardHeader>
                    <CardContent class="pb-3">
                        <span
                            class="text-3xl font-black text-rose-600 dark:text-rose-400"
                        >
                            {{ voucherAlertCount }}
                        </span>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            sự cố áp mã bất thường được gắn cờ
                            <span class="text-slate-400"
                                >({{ fraudAlerts.length }} cảnh báo mọi
                                loại)</span
                            >
                        </p>
                    </CardContent>
                </Card>

                <!-- FRAUD RULE TIPS -->
                <Card class="shadow-sm lg:col-span-2">
                    <CardHeader
                        class="border-b bg-slate-50 py-3 dark:bg-slate-900/30"
                    >
                        <CardTitle
                            class="flex items-center gap-1.5 text-xs font-bold text-indigo-600"
                        >
                            <Brain class="size-4 text-indigo-600" /> Thuật toán
                            giám sát Cashier áp mã giảm giá
                        </CardTitle>
                    </CardHeader>
                    <CardContent
                        class="space-y-2 p-4 text-xs text-slate-600 dark:text-slate-400"
                    >
                        <p>
                            <strong>Cơ chế phòng ngừa thông đồng:</strong> Thu
                            ngân thường cấu kết với người ngoài, cố tình áp mã
                            giảm giá ảo/voucher vào hóa đơn của khách thanh toán
                            tiền mặt để bỏ túi riêng khoản chênh lệch.
                        </p>
                        <p>
                            💡
                            <strong
                                >Chặn ngay khi áp mã (thời gian thực):</strong
                            >
                            hệ thống yêu cầu mã phê duyệt của Chủ nhà hàng nếu
                            cùng một thu ngân áp mã từ
                            <strong>3 lần trở lên trong 5 phút</strong>, hoặc
                            nếu khách hàng đó đã có đơn được giảm giá trong
                            <strong>10 phút</strong>
                            trước đó.
                        </p>
                        <p>
                            🚩 <strong>Gắn cờ hậu kiểm:</strong> một lượt áp mã
                            bị đưa vào danh sách vi phạm bên dưới khi mức giảm
                            <strong>vượt 20% giá trị hóa đơn</strong>, hoặc khi
                            tài khoản đó áp mã
                            <strong>từ 3 lần trong 15 phút</strong>. Các lượt áp
                            mã bình thường chỉ được lưu ở bảng nhật ký kiểm toán
                            phía dưới, không tính là vi phạm.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- CRITICAL ALERTS LIST -->
            <div v-if="salesAlerts.length > 0" class="space-y-3">
                <h3
                    class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-rose-600 uppercase"
                >
                    <ShieldAlert class="size-4" /> Danh sách phát hiện vi phạm
                    nhạy cảm từ AI quét logs
                </h3>

                <!-- 4 mức độ giờ có 4 kiểu hiển thị riêng; trước đây 'high' và
                     'medium' trông giống hệt nhau nên không phân biệt được. -->
                <div
                    v-for="alert in salesAlerts"
                    :key="alert.id"
                    :class="[
                        'relative flex flex-col justify-between gap-4 overflow-hidden rounded-2xl border p-5 transition-all md:flex-row md:items-center',
                        severityStyle(alert),
                    ]"
                >
                    <div class="max-w-3xl space-y-1.5">
                        <div class="flex items-center gap-2">
                            <span
                                :class="[
                                    'rounded border px-2 py-0.5 font-mono text-[8px] font-black tracking-wider uppercase',
                                    severityBadge(alert),
                                ]"
                            >
                                Cảnh báo: {{ alert.violation_type }}
                            </span>

                            <span class="font-mono text-[10px] text-slate-400"
                                >Thời gian: {{ alert.occurred_at }}</span
                            >
                        </div>

                        <p
                            class="text-xs font-extrabold text-slate-800 dark:text-slate-200"
                        >
                            {{ alert.description }}
                        </p>
                        <p
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            <strong>Nguyên nhân quét AI:</strong>
                            {{ alert.reason }}
                        </p>
                    </div>

                    <div
                        class="flex shrink-0 flex-row items-center justify-between gap-2 border-t border-rose-100 pt-2.5 text-right md:flex-col md:items-end md:justify-center md:border-t-0 md:pt-0"
                    >
                        <div>
                            <p
                                class="text-[9px] font-bold text-slate-400 uppercase"
                            >
                                Chỉ số rủi ro
                            </p>
                            <p
                                class="text-lg leading-tight font-black text-rose-600 dark:text-rose-400"
                            >
                                {{ alert.risk_score }}%
                            </p>
                        </div>
                        <div
                            class="rounded bg-rose-100 px-2 py-0.5 font-mono text-[10px] font-black text-rose-700 dark:bg-rose-950/40 dark:text-rose-400"
                        >
                            Thất thoát:
                            {{ numberFormat(alert.penalty_amount) }}đ
                        </div>
                    </div>
                </div>
            </div>

            <!-- CẢNH BÁO THUỘC MODULE KHÁC -->
            <div
                v-if="purchasingAlerts.length > 0"
                class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
            >
                <h3
                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                >
                    <ShieldAlert class="size-3.5" />
                    {{ purchasingAlerts.length }} cảnh báo thuộc module Kho
                    &amp; Mua hàng
                </h3>
                <p class="mt-1 text-[11px] text-slate-500">
                    Các cảnh báo đối soát nhập hàng không liên quan tới khuyến
                    mãi. Chúng được gom riêng ở đây để không gây nhiễu cho danh
                    sách vi phạm bán hàng phía trên.
                </p>
                <ul class="mt-2 space-y-1">
                    <li
                        v-for="alert in purchasingAlerts"
                        :key="alert.id"
                        class="text-[11px] text-slate-600 dark:text-slate-400"
                    >
                        • {{ alert.description }}
                    </li>
                </ul>
            </div>

            <!-- VOUCHER APPLIED AUDIT LOGS -->
            <Card class="shadow-sm">
                <CardHeader class="border-b pb-3">
                    <CardTitle class="text-sm font-bold"
                        >Nhật Ký Kiểm Toán Áp Mã Giảm Giá (Voucher
                        Logs)</CardTitle
                    >
                    <CardDescription>
                        Toàn bộ các thao tác áp dụng giảm giá được Model
                        Observers bắt sự kiện và đẩy ngầm ghi log, bảo lưu thông
                        tin IP thu ngân làm bằng chứng đối soát.
                    </CardDescription>
                </CardHeader>

                <CardContent class="p-0">
                    <div
                        v-if="voucherLogs.length === 0"
                        class="flex flex-col items-center gap-2 py-16 text-center text-slate-500"
                    >
                        <Tag class="size-6 text-slate-300" />
                        <p
                            class="text-xs font-bold text-slate-800 dark:text-slate-200"
                        >
                            Không có giao dịch áp voucher nào được ghi nhận gần
                            đây
                        </p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b bg-slate-100 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                                >
                                    <th class="p-3.5">Thời gian</th>
                                    <th class="p-3.5">Đơn hàng</th>
                                    <th class="p-3.5">Thu ngân (Cashier)</th>
                                    <th class="p-3.5">Vai trò</th>
                                    <th class="p-3.5">Giá trị cũ</th>
                                    <th class="p-3.5">Giá trị mới</th>
                                    <th class="p-3.5">Mức giảm</th>
                                    <th class="p-3.5">Địa chỉ IP</th>
                                    <th class="p-3.5">Thiết bị (User Agent)</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr
                                    v-for="log in voucherLogs"
                                    :key="log.id"
                                    class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                                >
                                    <td class="p-3.5 font-mono text-slate-500">
                                        {{ log.created_at }}
                                    </td>
                                    <td
                                        class="p-3.5 font-mono font-bold text-indigo-600 dark:text-indigo-400"
                                    >
                                        #ORD-{{ log.subject_id }}
                                    </td>
                                    <td
                                        class="p-3.5 font-extrabold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ log.user_name }}
                                    </td>
                                    <td
                                        class="p-3.5 text-[9px] font-bold text-slate-500 uppercase"
                                    >
                                        {{ log.user_role }}
                                    </td>
                                    <td
                                        class="p-3.5 font-mono text-slate-600 dark:text-slate-400"
                                    >
                                        {{
                                            numberFormat(
                                                log.old_values.total_amount ??
                                                    0,
                                            )
                                        }}đ
                                    </td>
                                    <td
                                        class="p-3.5 font-mono text-slate-600 dark:text-slate-400"
                                    >
                                        {{
                                            numberFormat(
                                                log.new_values.total_amount ??
                                                    0,
                                            )
                                        }}đ
                                    </td>
                                    <td
                                        class="p-3.5 font-mono font-bold text-rose-600"
                                    >
                                        -{{
                                            numberFormat(
                                                log.new_values
                                                    .discount_amount ?? 0,
                                            )
                                        }}đ
                                    </td>
                                    <td
                                        class="p-3.5 font-mono text-slate-600 dark:text-slate-400"
                                    >
                                        {{ log.ip_address }}
                                    </td>
                                    <td
                                        class="max-w-[180px] truncate p-3.5 text-slate-400"
                                        :title="log.user_agent"
                                    >
                                        {{ log.user_agent }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- MODAL: ADD PROMOTION -->
        <Teleport to="body">
            <div
                v-if="showAddModal"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-xs"
            >
                <Card
                    class="my-8 w-full max-w-2xl animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-base text-indigo-600"
                            >
                                <Tag class="size-5" />
                                Thiết Lập Chương Trình Khuyến Mãi Mới
                            </CardTitle>
                            <CardDescription
                                >Cấu hình mã giảm giá, voucher linh hoạt cho nhà
                                hàng của bạn.</CardDescription
                            >
                        </div>
                        <button
                            @click="showAddModal = false"
                            class="rounded-lg p-1 text-slate-400 hover:bg-muted hover:text-slate-700"
                        >
                            <X class="size-4" />
                        </button>
                    </CardHeader>

                    <CardContent class="max-h-[70vh] overflow-y-auto pt-4">
                        <form @submit.prevent="submitAdd" class="space-y-5">
                            <!-- ── Thông tin cơ bản ─────────────────────── -->
                            <div class="space-y-4">
                                <div class="grid gap-1.5">
                                    <Label for="promo-name"
                                        >Tên chương trình khuyến mãi
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <Input
                                        id="promo-name"
                                        v-model="form.name"
                                        placeholder="Chào hè 2026, Tri ân khách hàng..."
                                        required
                                    />
                                    <p
                                        v-if="form.errors.name"
                                        class="text-[11px] font-semibold text-rose-600"
                                    >
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="promo-code"
                                            >Mã Voucher (Code)
                                            <span
                                                class="text-[10px] text-slate-400"
                                                >(Có thể trống)</span
                                            ></Label
                                        >
                                        <Input
                                            id="promo-code"
                                            v-model="form.code"
                                            placeholder="GIAM20, HE2026..."
                                        />
                                        <p
                                            v-if="form.errors.code"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ form.errors.code }}
                                        </p>
                                    </div>

                                    <div class="grid gap-1.5">
                                        <Label for="promo-type"
                                            >Loại giảm giá
                                            <span class="text-rose-500"
                                                >*</span
                                            ></Label
                                        >
                                        <select
                                            id="promo-type"
                                            v-model="form.type"
                                            class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none dark:border-slate-800"
                                        >
                                            <option value="percent">
                                                Giảm theo phần trăm (%)
                                            </option>
                                            <option value="fixed_amount">
                                                Khấu trừ tiền mặt cố định (đ)
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-3">
                                    <div class="grid gap-1.5">
                                        <Label for="promo-val"
                                            >Giá trị giảm
                                            <span class="text-rose-500"
                                                >*</span
                                            ></Label
                                        >
                                        <Input
                                            id="promo-val"
                                            type="number"
                                            v-model="form.value"
                                            min="0"
                                            required
                                        />
                                        <p
                                            v-if="form.errors.value"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ form.errors.value }}
                                        </p>
                                    </div>
                                    <div class="col-span-2 grid gap-1.5">
                                        <Label for="promo-min"
                                            >Đơn tối thiểu cần đạt (đ)
                                            <span
                                                v-if="
                                                    form.type === 'fixed_amount'
                                                "
                                                class="text-rose-500"
                                                >*</span
                                            ></Label
                                        >
                                        <Input
                                            id="promo-min"
                                            type="number"
                                            v-model="form.min_order_amount"
                                            min="0"
                                        />
                                        <p
                                            v-if="form.errors.min_order_amount"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ form.errors.min_order_amount }}
                                        </p>
                                        <p
                                            v-if="form.type === 'fixed_amount'"
                                            class="text-[10px] text-slate-400"
                                        >
                                            Voucher tiền mặt bắt buộc có đơn tối
                                            thiểu, và số tiền khấu trừ phải nhỏ
                                            hơn mức này để đơn không về 0đ.
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="grid gap-1.5"
                                    v-if="form.type === 'percent'"
                                >
                                    <Label for="promo-max"
                                        >Số tiền giảm tối đa (đ)
                                        <span class="text-[10px] text-slate-400"
                                            >(Để trống nếu không giới hạn)</span
                                        ></Label
                                    >
                                    <Input
                                        id="promo-max"
                                        type="number"
                                        v-model="form.max_discount_amount"
                                        min="0"
                                    />
                                    <p
                                        v-if="form.errors.max_discount_amount"
                                        class="text-[11px] font-semibold text-rose-600"
                                    >
                                        {{ form.errors.max_discount_amount }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="promo-start"
                                            >Ngày bắt đầu</Label
                                        >
                                        <Input
                                            id="promo-start"
                                            type="datetime-local"
                                            v-model="form.start_date"
                                        />
                                        <p
                                            v-if="form.errors.start_date"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ form.errors.start_date }}
                                        </p>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="promo-end"
                                            >Ngày kết thúc</Label
                                        >
                                        <Input
                                            id="promo-end"
                                            type="datetime-local"
                                            v-model="form.end_date"
                                        />
                                        <p
                                            v-if="form.errors.end_date"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ form.errors.end_date }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Chi nhánh: trước đây bị trait gán ngầm theo
                                     thanh "Phạm vi dữ liệu", không ai nhìn thấy. -->
                                <div class="grid gap-1.5">
                                    <Label for="promo-branch"
                                        >Phạm vi áp dụng</Label
                                    >
                                    <select
                                        id="promo-branch"
                                        v-model="form.branch_id"
                                        class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none dark:border-slate-800"
                                    >
                                        <option :value="null">
                                            Toàn chuỗi (mọi chi nhánh)
                                        </option>
                                        <option
                                            v-for="b in branches"
                                            :key="b.id"
                                            :value="b.id"
                                        >
                                            Chỉ chi nhánh: {{ b.name }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="form.errors.branch_id"
                                        class="text-[11px] font-semibold text-rose-600"
                                    >
                                        {{ form.errors.branch_id }}
                                    </p>
                                </div>
                            </div>

                            <!-- ── Kiểm soát chi phí ────────────────────── -->
                            <div
                                class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <p
                                    class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Kiểm soát chi phí &amp; lượt dùng
                                </p>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="promo-budget"
                                            >Ngân sách tối đa (đ)
                                            <span
                                                class="text-[10px] text-slate-400"
                                                >(Trống = không giới hạn)</span
                                            ></Label
                                        >
                                        <Input
                                            id="promo-budget"
                                            type="number"
                                            v-model="form.budget_cap"
                                            min="0"
                                        />
                                        <p
                                            v-if="form.errors.budget_cap"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ form.errors.budget_cap }}
                                        </p>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="promo-limit"
                                            >Tổng số lượt dùng
                                            <span
                                                class="text-[10px] text-slate-400"
                                                >(Trống = không giới hạn)</span
                                            ></Label
                                        >
                                        <Input
                                            id="promo-limit"
                                            type="number"
                                            v-model="form.usage_limit"
                                            min="1"
                                        />
                                        <p
                                            v-if="form.errors.usage_limit"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ form.errors.usage_limit }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-1.5">
                                    <Label for="promo-limit-cust"
                                        >Số lượt tối đa mỗi khách hàng
                                        <span class="text-[10px] text-slate-400"
                                            >(Trống = không giới hạn)</span
                                        ></Label
                                    >
                                    <Input
                                        id="promo-limit-cust"
                                        type="number"
                                        v-model="form.usage_limit_per_customer"
                                        min="1"
                                    />
                                    <p
                                        v-if="
                                            form.errors.usage_limit_per_customer
                                        "
                                        class="text-[11px] font-semibold text-rose-600"
                                    >
                                        {{
                                            form.errors.usage_limit_per_customer
                                        }}
                                    </p>
                                </div>

                                <label
                                    class="flex cursor-pointer items-center gap-2 text-xs text-slate-600 dark:text-slate-300"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="form.auto_deactivate_on_budget"
                                        class="size-4 rounded border-slate-300 accent-indigo-600"
                                    />
                                    Tự động tắt chương trình ngay khi tiêu hết
                                    ngân sách
                                </label>
                            </div>

                            <!-- ── Điều kiện áp dụng ────────────────────── -->
                            <div
                                class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <p
                                    class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Điều kiện áp dụng (Happy hour, khung giờ
                                    vàng)
                                </p>

                                <div class="grid gap-1.5">
                                    <Label>Chỉ áp dụng các thứ</Label>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button
                                            v-for="d in WEEKDAYS"
                                            :key="d.value"
                                            type="button"
                                            :class="[
                                                'h-8 w-11 rounded-md border text-[11px] font-bold transition-all',
                                                form.condition_days.includes(
                                                    d.value,
                                                )
                                                    ? 'border-indigo-600 bg-indigo-600 text-white'
                                                    : 'border-slate-200 bg-background text-slate-500 hover:border-indigo-300 dark:border-slate-800',
                                            ]"
                                            @click="
                                                form.condition_days.includes(
                                                    d.value,
                                                )
                                                    ? form.condition_days.splice(
                                                          form.condition_days.indexOf(
                                                              d.value,
                                                          ),
                                                          1,
                                                      )
                                                    : form.condition_days.push(
                                                          d.value,
                                                      )
                                            "
                                        >
                                            {{ d.label }}
                                        </button>
                                    </div>
                                    <p class="text-[10px] text-slate-400">
                                        Không chọn thứ nào = áp dụng mọi ngày.
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="promo-time-start"
                                            >Khung giờ — từ</Label
                                        >
                                        <Input
                                            id="promo-time-start"
                                            type="time"
                                            v-model="form.condition_time_start"
                                        />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="promo-time-end"
                                            >Khung giờ — đến</Label
                                        >
                                        <Input
                                            id="promo-time-end"
                                            type="time"
                                            v-model="form.condition_time_end"
                                        />
                                    </div>
                                </div>

                                <div class="grid gap-1.5">
                                    <Label for="promo-min-items"
                                        >Số món tối thiểu trên đơn
                                        <span class="text-[10px] text-slate-400"
                                            >(Trống = không yêu cầu)</span
                                        ></Label
                                    >
                                    <Input
                                        id="promo-min-items"
                                        type="number"
                                        min="1"
                                        v-model="form.condition_min_items"
                                    />
                                </div>

                                <label
                                    class="flex cursor-pointer items-center gap-2 text-xs text-slate-600 dark:text-slate-300"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="
                                            form.condition_first_order_only
                                        "
                                        class="size-4 rounded border-slate-300 accent-indigo-600"
                                    />
                                    Chỉ dành cho đơn hàng đầu tiên của khách
                                </label>
                            </div>
                            <!-- ── Cộng dồn khuyến mãi ──────────────────── -->
                            <div
                                class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <p
                                    class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Cộng dồn khuyến mãi (Stacking)
                                </p>

                                <label
                                    class="flex cursor-pointer items-center gap-2 text-xs text-slate-600 dark:text-slate-300"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="form.is_stackable"
                                        class="size-4 rounded border-slate-300 accent-indigo-600"
                                    />
                                    Cho phép áp chung với khuyến mãi khác trên
                                    cùng hóa đơn
                                </label>

                                <div
                                    v-if="form.is_stackable"
                                    class="grid grid-cols-2 gap-4"
                                >
                                    <div class="grid gap-1.5">
                                        <Label for="promo-priority"
                                            >Độ ưu tiên
                                            <span
                                                class="text-[10px] text-slate-400"
                                                >(cao = áp trước)</span
                                            ></Label
                                        >
                                        <Input
                                            id="promo-priority"
                                            type="number"
                                            v-model="form.stacking_priority"
                                            min="0"
                                        />
                                        <p
                                            v-if="form.errors.stacking_priority"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ form.errors.stacking_priority }}
                                        </p>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="promo-group"
                                            >Nhóm loại trừ
                                            <span
                                                class="text-[10px] text-slate-400"
                                                >(cùng nhóm chỉ áp 1 mã)</span
                                            ></Label
                                        >
                                        <Input
                                            id="promo-group"
                                            v-model="form.stacking_group"
                                            placeholder="VD: khai-truong"
                                        />
                                        <p
                                            v-if="form.errors.stacking_group"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ form.errors.stacking_group }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="!canManagePrices"
                                class="flex items-start gap-2 rounded-xl border border-amber-100 bg-amber-50/50 p-3 text-[10px] text-amber-700 dark:bg-amber-950/20 dark:text-amber-400"
                            >
                                <ShieldCheck
                                    class="mt-0.5 size-4 shrink-0 text-amber-600"
                                />
                                <p>
                                    <strong>Lưu ý quyền hạn:</strong> tài khoản
                                    <strong>Quản lý (Manager)</strong> — chương
                                    trình sau khi tạo sẽ cần
                                    <strong>Chủ nhà hàng (Owner)</strong> duyệt
                                    để chính thức có hiệu lực sử dụng.
                                </p>
                            </div>

                            <div class="flex justify-end gap-2 border-t pt-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="showAddModal = false"
                                    >Hủy</Button
                                >
                                <Button
                                    type="submit"
                                    size="sm"
                                    class="bg-indigo-600 font-semibold text-white hover:bg-indigo-700"
                                    :disabled="form.processing"
                                >
                                    {{
                                        form.processing
                                            ? 'Đang tạo...'
                                            : 'Tạo khuyến mãi'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </Teleport>

        <!-- MODAL: EDIT PROMOTION -->
        <Teleport to="body">
            <div
                v-if="showEditModal && editingPromotion"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-xs"
            >
                <Card
                    class="my-8 w-full max-w-2xl animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-base text-indigo-600"
                            >
                                <Pencil class="size-5" />
                                Chỉnh Sửa Chương Trình Khuyến Mãi
                            </CardTitle>
                            <CardDescription
                                >Cập nhật thông tin cho "{{
                                    editingPromotion.name
                                }}".</CardDescription
                            >
                        </div>
                        <button
                            @click="showEditModal = false"
                            class="rounded-lg p-1 text-slate-400 hover:bg-muted hover:text-slate-700"
                        >
                            <X class="size-4" />
                        </button>
                    </CardHeader>

                    <CardContent class="max-h-[70vh] overflow-y-auto pt-4">
                        <form @submit.prevent="submitEdit" class="space-y-5">
                            <!-- ── Thông tin cơ bản ─────────────────────── -->
                            <div class="space-y-4">
                                <div class="grid gap-1.5">
                                    <Label for="edit-promo-name"
                                        >Tên chương trình khuyến mãi
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <Input
                                        id="edit-promo-name"
                                        v-model="editForm.name"
                                        placeholder="Chào hè 2026, Tri ân khách hàng..."
                                        required
                                    />
                                    <p
                                        v-if="editForm.errors.name"
                                        class="text-[11px] font-semibold text-rose-600"
                                    >
                                        {{ editForm.errors.name }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="edit-promo-code"
                                            >Mã Voucher (Code)
                                            <span
                                                class="text-[10px] text-slate-400"
                                                >(Có thể trống)</span
                                            ></Label
                                        >
                                        <Input
                                            id="edit-promo-code"
                                            v-model="editForm.code"
                                            placeholder="GIAM20, HE2026..."
                                        />
                                        <p
                                            v-if="editForm.errors.code"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ editForm.errors.code }}
                                        </p>
                                    </div>

                                    <div class="grid gap-1.5">
                                        <Label for="edit-promo-type"
                                            >Loại giảm giá
                                            <span class="text-rose-500"
                                                >*</span
                                            ></Label
                                        >
                                        <select
                                            id="edit-promo-type"
                                            v-model="editForm.type"
                                            class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none dark:border-slate-800"
                                        >
                                            <option value="percent">
                                                Giảm theo phần trăm (%)
                                            </option>
                                            <option value="fixed_amount">
                                                Khấu trừ tiền mặt cố định (đ)
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-3">
                                    <div class="grid gap-1.5">
                                        <Label for="edit-promo-val"
                                            >Giá trị giảm
                                            <span class="text-rose-500"
                                                >*</span
                                            ></Label
                                        >
                                        <Input
                                            id="edit-promo-val"
                                            type="number"
                                            v-model="editForm.value"
                                            min="0"
                                            required
                                        />
                                        <p
                                            v-if="editForm.errors.value"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ editForm.errors.value }}
                                        </p>
                                    </div>
                                    <div class="col-span-2 grid gap-1.5">
                                        <Label for="edit-promo-min"
                                            >Đơn tối thiểu cần đạt (đ)
                                            <span
                                                v-if="
                                                    editForm.type ===
                                                    'fixed_amount'
                                                "
                                                class="text-rose-500"
                                                >*</span
                                            ></Label
                                        >
                                        <Input
                                            id="edit-promo-min"
                                            type="number"
                                            v-model="editForm.min_order_amount"
                                            min="0"
                                        />
                                        <p
                                            v-if="
                                                editForm.errors.min_order_amount
                                            "
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{
                                                editForm.errors.min_order_amount
                                            }}
                                        </p>
                                        <p
                                            v-if="
                                                editForm.type === 'fixed_amount'
                                            "
                                            class="text-[10px] text-slate-400"
                                        >
                                            Voucher tiền mặt bắt buộc có đơn tối
                                            thiểu, và số tiền khấu trừ phải nhỏ
                                            hơn mức này để đơn không về 0đ.
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="grid gap-1.5"
                                    v-if="editForm.type === 'percent'"
                                >
                                    <Label for="edit-promo-max"
                                        >Số tiền giảm tối đa (đ)
                                        <span class="text-[10px] text-slate-400"
                                            >(Để trống nếu không giới hạn)</span
                                        ></Label
                                    >
                                    <Input
                                        id="edit-promo-max"
                                        type="number"
                                        v-model="editForm.max_discount_amount"
                                        min="0"
                                    />
                                    <p
                                        v-if="
                                            editForm.errors.max_discount_amount
                                        "
                                        class="text-[11px] font-semibold text-rose-600"
                                    >
                                        {{
                                            editForm.errors.max_discount_amount
                                        }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="edit-promo-start"
                                            >Ngày bắt đầu</Label
                                        >
                                        <Input
                                            id="edit-promo-start"
                                            type="datetime-local"
                                            v-model="editForm.start_date"
                                        />
                                        <p
                                            v-if="editForm.errors.start_date"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ editForm.errors.start_date }}
                                        </p>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="edit-promo-end"
                                            >Ngày kết thúc</Label
                                        >
                                        <Input
                                            id="edit-promo-end"
                                            type="datetime-local"
                                            v-model="editForm.end_date"
                                        />
                                        <p
                                            v-if="editForm.errors.end_date"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ editForm.errors.end_date }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Chi nhánh: trước đây bị trait gán ngầm theo
                                     thanh "Phạm vi dữ liệu", không ai nhìn thấy. -->
                                <div class="grid gap-1.5">
                                    <Label for="edit-promo-branch"
                                        >Phạm vi áp dụng</Label
                                    >
                                    <select
                                        id="edit-promo-branch"
                                        v-model="editForm.branch_id"
                                        class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none dark:border-slate-800"
                                    >
                                        <option :value="null">
                                            Toàn chuỗi (mọi chi nhánh)
                                        </option>
                                        <option
                                            v-for="b in branches"
                                            :key="b.id"
                                            :value="b.id"
                                        >
                                            Chỉ chi nhánh: {{ b.name }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="editForm.errors.branch_id"
                                        class="text-[11px] font-semibold text-rose-600"
                                    >
                                        {{ editForm.errors.branch_id }}
                                    </p>
                                </div>
                            </div>

                            <!-- ── Kiểm soát chi phí ────────────────────── -->
                            <div
                                class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <p
                                    class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Kiểm soát chi phí &amp; lượt dùng
                                </p>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="edit-promo-budget"
                                            >Ngân sách tối đa (đ)
                                            <span
                                                class="text-[10px] text-slate-400"
                                                >(Trống = không giới hạn)</span
                                            ></Label
                                        >
                                        <Input
                                            id="edit-promo-budget"
                                            type="number"
                                            v-model="editForm.budget_cap"
                                            min="0"
                                        />
                                        <p
                                            v-if="editForm.errors.budget_cap"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ editForm.errors.budget_cap }}
                                        </p>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="edit-promo-limit"
                                            >Tổng số lượt dùng
                                            <span
                                                class="text-[10px] text-slate-400"
                                                >(Trống = không giới hạn)</span
                                            ></Label
                                        >
                                        <Input
                                            id="edit-promo-limit"
                                            type="number"
                                            v-model="editForm.usage_limit"
                                            min="1"
                                        />
                                        <p
                                            v-if="editForm.errors.usage_limit"
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ editForm.errors.usage_limit }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-1.5">
                                    <Label for="edit-promo-limit-cust"
                                        >Số lượt tối đa mỗi khách hàng
                                        <span class="text-[10px] text-slate-400"
                                            >(Trống = không giới hạn)</span
                                        ></Label
                                    >
                                    <Input
                                        id="edit-promo-limit-cust"
                                        type="number"
                                        v-model="
                                            editForm.usage_limit_per_customer
                                        "
                                        min="1"
                                    />
                                    <p
                                        v-if="
                                            editForm.errors
                                                .usage_limit_per_customer
                                        "
                                        class="text-[11px] font-semibold text-rose-600"
                                    >
                                        {{
                                            editForm.errors
                                                .usage_limit_per_customer
                                        }}
                                    </p>
                                </div>

                                <label
                                    class="flex cursor-pointer items-center gap-2 text-xs text-slate-600 dark:text-slate-300"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="
                                            editForm.auto_deactivate_on_budget
                                        "
                                        class="size-4 rounded border-slate-300 accent-indigo-600"
                                    />
                                    Tự động tắt chương trình ngay khi tiêu hết
                                    ngân sách
                                </label>
                            </div>

                            <!-- ── Điều kiện áp dụng ────────────────────── -->
                            <div
                                class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <p
                                    class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Điều kiện áp dụng (Happy hour, khung giờ
                                    vàng)
                                </p>

                                <div class="grid gap-1.5">
                                    <Label>Chỉ áp dụng các thứ</Label>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button
                                            v-for="d in WEEKDAYS"
                                            :key="d.value"
                                            type="button"
                                            :class="[
                                                'h-8 w-11 rounded-md border text-[11px] font-bold transition-all',
                                                editForm.condition_days.includes(
                                                    d.value,
                                                )
                                                    ? 'border-indigo-600 bg-indigo-600 text-white'
                                                    : 'border-slate-200 bg-background text-slate-500 hover:border-indigo-300 dark:border-slate-800',
                                            ]"
                                            @click="
                                                editForm.condition_days.includes(
                                                    d.value,
                                                )
                                                    ? editForm.condition_days.splice(
                                                          editForm.condition_days.indexOf(
                                                              d.value,
                                                          ),
                                                          1,
                                                      )
                                                    : editForm.condition_days.push(
                                                          d.value,
                                                      )
                                            "
                                        >
                                            {{ d.label }}
                                        </button>
                                    </div>
                                    <p class="text-[10px] text-slate-400">
                                        Không chọn thứ nào = áp dụng mọi ngày.
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="edit-promo-time-start"
                                            >Khung giờ — từ</Label
                                        >
                                        <Input
                                            id="edit-promo-time-start"
                                            type="time"
                                            v-model="
                                                editForm.condition_time_start
                                            "
                                        />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="edit-promo-time-end"
                                            >Khung giờ — đến</Label
                                        >
                                        <Input
                                            id="edit-promo-time-end"
                                            type="time"
                                            v-model="
                                                editForm.condition_time_end
                                            "
                                        />
                                    </div>
                                </div>

                                <div class="grid gap-1.5">
                                    <Label for="edit-promo-min-items"
                                        >Số món tối thiểu trên đơn
                                        <span class="text-[10px] text-slate-400"
                                            >(Trống = không yêu cầu)</span
                                        ></Label
                                    >
                                    <Input
                                        id="edit-promo-min-items"
                                        type="number"
                                        min="1"
                                        v-model="editForm.condition_min_items"
                                    />
                                </div>

                                <label
                                    class="flex cursor-pointer items-center gap-2 text-xs text-slate-600 dark:text-slate-300"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="
                                            editForm.condition_first_order_only
                                        "
                                        class="size-4 rounded border-slate-300 accent-indigo-600"
                                    />
                                    Chỉ dành cho đơn hàng đầu tiên của khách
                                </label>
                            </div>
                            <!-- ── Cộng dồn khuyến mãi ──────────────────── -->
                            <div
                                class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <p
                                    class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Cộng dồn khuyến mãi (Stacking)
                                </p>

                                <label
                                    class="flex cursor-pointer items-center gap-2 text-xs text-slate-600 dark:text-slate-300"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="editForm.is_stackable"
                                        class="size-4 rounded border-slate-300 accent-indigo-600"
                                    />
                                    Cho phép áp chung với khuyến mãi khác trên
                                    cùng hóa đơn
                                </label>

                                <div
                                    v-if="editForm.is_stackable"
                                    class="grid grid-cols-2 gap-4"
                                >
                                    <div class="grid gap-1.5">
                                        <Label for="edit-promo-priority"
                                            >Độ ưu tiên
                                            <span
                                                class="text-[10px] text-slate-400"
                                                >(cao = áp trước)</span
                                            ></Label
                                        >
                                        <Input
                                            id="edit-promo-priority"
                                            type="number"
                                            v-model="editForm.stacking_priority"
                                            min="0"
                                        />
                                        <p
                                            v-if="
                                                editForm.errors
                                                    .stacking_priority
                                            "
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{
                                                editForm.errors
                                                    .stacking_priority
                                            }}
                                        </p>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="edit-promo-group"
                                            >Nhóm loại trừ
                                            <span
                                                class="text-[10px] text-slate-400"
                                                >(cùng nhóm chỉ áp 1 mã)</span
                                            ></Label
                                        >
                                        <Input
                                            id="edit-promo-group"
                                            v-model="editForm.stacking_group"
                                            placeholder="VD: khai-truong"
                                        />
                                        <p
                                            v-if="
                                                editForm.errors.stacking_group
                                            "
                                            class="text-[11px] font-semibold text-rose-600"
                                        >
                                            {{ editForm.errors.stacking_group }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="!canManagePrices"
                                class="flex items-start gap-2 rounded-xl border border-amber-100 bg-amber-50/50 p-3 text-[10px] text-amber-700 dark:bg-amber-950/20 dark:text-amber-400"
                            >
                                <ShieldCheck
                                    class="mt-0.5 size-4 shrink-0 text-amber-600"
                                />
                                <p>
                                    <strong>Lưu ý quyền hạn:</strong> tài khoản
                                    <strong>Quản lý (Manager)</strong> — chương
                                    trình sau khi sửa sẽ cần
                                    <strong>Chủ nhà hàng (Owner)</strong> duyệt
                                    lại để chính thức có hiệu lực.
                                </p>
                            </div>

                            <div class="flex justify-end gap-2 border-t pt-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="showEditModal = false"
                                    >Hủy</Button
                                >
                                <Button
                                    type="submit"
                                    size="sm"
                                    class="bg-indigo-600 font-semibold text-white hover:bg-indigo-700"
                                    :disabled="editForm.processing"
                                >
                                    {{
                                        editForm.processing
                                            ? 'Đang lưu...'
                                            : 'Lưu thay đổi'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </Teleport>

        <!-- MODAL: QR VOUCHER -->
        <Teleport to="body">
            <div
                v-if="showQrModal && qrPromotion"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            >
                <Card
                    class="w-full max-w-sm animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-base text-indigo-600"
                            >
                                <QrCode class="size-5" />
                                Mã QR Voucher
                            </CardTitle>
                            <CardDescription>{{
                                qrPromotion.name
                            }}</CardDescription>
                        </div>
                        <button
                            @click="showQrModal = false"
                            class="rounded-lg p-1 text-slate-400 hover:bg-muted hover:text-slate-700"
                        >
                            <X class="size-4" />
                        </button>
                    </CardHeader>

                    <CardContent class="space-y-4 pt-4 text-center">
                        <div
                            v-if="isLoadingQr"
                            class="flex h-[300px] items-center justify-center"
                        >
                            <Loader2
                                class="size-8 animate-spin text-indigo-600"
                            />
                        </div>

                        <div
                            v-else
                            class="mx-auto w-max rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700"
                            v-html="qrSvg"
                        />

                        <div>
                            <p
                                class="font-mono text-lg font-black tracking-widest text-indigo-600 dark:text-indigo-400"
                            >
                                {{ qrPromotion.code }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{
                                    qrPromotion.type === 'percent'
                                        ? `Giảm ${qrPromotion.value}%`
                                        : `Giảm ${numberFormat(qrPromotion.value)}đ`
                                }}
                                · Đơn tối thiểu
                                {{
                                    numberFormat(qrPromotion.min_order_amount)
                                }}đ
                            </p>
                        </div>

                        <p
                            class="rounded-lg border border-slate-100 bg-slate-50 p-2.5 text-left text-[10px] leading-relaxed text-slate-500 dark:border-slate-800 dark:bg-slate-900/40"
                        >
                            Mã QR chứa trực tiếp chuỗi mã voucher. Máy quét của
                            POS đọc thẳng vào ô nhập mã khi thanh toán — dùng
                            cho poster, tent card để bàn hoặc tờ rơi.
                        </p>

                        <div class="flex justify-center gap-2 border-t pt-3">
                            <Button
                                v-if="qrDownloadUrl"
                                size="sm"
                                variant="outline"
                                class="h-8 text-xs"
                                as="a"
                                :href="qrDownloadUrl"
                                target="_blank"
                            >
                                <Download class="mr-1 size-3.5" /> Tải ảnh PNG
                            </Button>
                            <Button
                                size="sm"
                                class="h-8 bg-indigo-600 text-xs text-white hover:bg-indigo-700"
                                @click="showQrModal = false"
                            >
                                Đóng
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </Teleport>

        <!-- MODAL: QUICK COMBO CREATOR -->
        <Teleport to="body">
            <div
                v-if="showQuickComboModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            >
                <Card
                    class="w-full max-w-md animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-base text-indigo-600"
                            >
                                <Brain class="size-5" />
                                Thiết Lập Nhanh Combo AI
                            </CardTitle>
                            <CardDescription
                                >Cấu hình nhanh gói Combo món ăn khoa học đẩy số
                                thông minh.</CardDescription
                            >
                        </div>
                        <button
                            @click="showQuickComboModal = false"
                            class="rounded-lg p-1 text-slate-400 hover:bg-muted hover:text-slate-700"
                        >
                            <X class="size-4" />
                        </button>
                    </CardHeader>

                    <CardContent class="pt-4">
                        <form
                            @submit.prevent="createQuickCombo"
                            class="space-y-4"
                        >
                            <div class="grid gap-1.5">
                                <Label
                                    >Tên gói Combo mới
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input v-model="comboForm.name" required />
                            </div>

                            <p
                                v-if="
                                    !comboForm.item_a_id || !comboForm.item_b_id
                                "
                                class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-600"
                            >
                                Không tìm thấy món tương ứng trong thực đơn hiện
                                tại — không thể tạo combo.
                            </p>

                            <div
                                class="space-y-2 rounded-xl border border-slate-100 bg-slate-50 p-3.5 dark:bg-slate-900"
                            >
                                <p
                                    class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                >
                                    Cấu trúc combo
                                </p>

                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span
                                        class="font-extrabold text-slate-700 dark:text-slate-300"
                                        >1. {{ comboForm.item_a }}</span
                                    >
                                    <span class="font-mono text-slate-500"
                                        >{{
                                            numberFormat(
                                                comboForm.original_price_a,
                                            )
                                        }}đ</span
                                    >
                                </div>

                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span
                                        class="font-extrabold text-slate-700 dark:text-slate-300"
                                        >2. {{ comboForm.item_b }}</span
                                    >
                                    <span class="font-mono text-slate-500"
                                        >{{
                                            numberFormat(
                                                comboForm.original_price_b,
                                            )
                                        }}đ</span
                                    >
                                </div>

                                <div
                                    class="flex items-center justify-between border-t pt-2 text-xs font-black"
                                >
                                    <span>Tổng giá mua lẻ</span>
                                    <span class="font-mono"
                                        >{{
                                            numberFormat(
                                                comboForm.original_price_a +
                                                    comboForm.original_price_b,
                                            )
                                        }}đ</span
                                    >
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-1.5">
                                    <Label>Tỷ lệ giảm Combo (%)</Label>
                                    <Input
                                        type="number"
                                        v-model.number="
                                            comboForm.discount_percent
                                        "
                                        min="0"
                                        max="100"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Giá bán Combo (đ)</Label>
                                    <Input
                                        type="number"
                                        v-model.number="comboForm.combo_price"
                                        min="0"
                                    />
                                </div>
                            </div>

                            <!-- Hai ô trên giờ liên động hai chiều. Trước đây ô "%"
                             hoàn toàn trơ: không watcher, không computed, và bị
                             transform() loại bỏ khi submit. -->
                            <div
                                class="flex items-center justify-between rounded-xl border px-3.5 py-2.5 text-xs"
                                :class="
                                    comboSavings > 0
                                        ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/40 dark:bg-emerald-950/20'
                                        : 'border-rose-200 bg-rose-50/60 dark:border-rose-900/40 dark:bg-rose-950/20'
                                "
                            >
                                <span
                                    class="font-bold text-slate-600 dark:text-slate-300"
                                    >Khách tiết kiệm được</span
                                >
                                <span
                                    class="font-mono font-black"
                                    :class="
                                        comboSavings > 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-rose-600 dark:text-rose-400'
                                    "
                                >
                                    {{ numberFormat(comboSavings) }}đ
                                </span>
                            </div>
                            <p
                                v-if="comboSavings <= 0"
                                class="text-[11px] font-semibold text-rose-600"
                            >
                                Giá combo phải rẻ hơn tổng giá bán lẻ, nếu không
                                hệ thống sẽ từ chối tạo.
                            </p>
                            <p
                                v-if="comboForm.errors.combo_price"
                                class="text-[11px] font-semibold text-rose-600"
                            >
                                {{ comboForm.errors.combo_price }}
                            </p>

                            <div class="grid gap-1.5">
                                <Label>Mô tả Combo / Ghi chú</Label>
                                <textarea
                                    v-model="comboForm.notes"
                                    rows="3"
                                    class="w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                />
                            </div>

                            <div class="flex justify-end gap-2 border-t pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="showQuickComboModal = false"
                                    >Hủy</Button
                                >
                                <Button
                                    type="submit"
                                    size="sm"
                                    class="flex items-center gap-1 bg-indigo-600 font-semibold text-white hover:bg-indigo-700"
                                    :disabled="comboForm.processing"
                                >
                                    <Check class="size-4" />
                                    {{
                                        comboForm.processing
                                            ? 'Đang tạo...'
                                            : 'Tạo Combo vào thực đơn'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}
</style>
