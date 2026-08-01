<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Tag,
    Plus,
    Search,
    Sparkles,
    ShieldAlert,
    Check,
    X,
    Percent,
    ShieldCheck,
    AlertTriangle,
    TrendingUp,
    BarChart3,
    Brain,
    ShoppingBag,
    Zap,
    Network,
    HelpCircle,
    Calendar,
    RefreshCw,
    UserCheck,
    Trash2,
    Pencil,
} from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
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
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Promotion = {
    id: number;
    name: string;
    code: string;
    type: 'percent' | 'fixed_amount';
    value: number;
    min_order_amount: number;
    max_discount_amount: number;
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
    is_approved: boolean;
    created_by_name: string;
    approved_by_name: string;
};

type FraudAlert = {
    id: string;
    employee_id: number;
    employee_name: string;
    violation_type: string;
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

const props = defineProps<{
    promotions: Promotion[];
    fraudAlerts: FraudAlert[];
    voucherLogs: VoucherLog[];
    products: ProductOption[];
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
const editingPromotion = ref<Promotion | null>(null);
const isAnalyzing = ref(false);
const analysisResults = ref<{
    total_orders: number;
    rules: ComboRule[];
    source: string;
} | null>(null);

// User check roles
const isOwner = props.auth.user.roles.includes('owner');
const isManager = props.auth.user.roles.includes('manager');

// Promotion Creation Form
const form = useForm({
    name: '',
    code: '',
    type: 'percent' as 'percent' | 'fixed_amount',
    value: 0,
    min_order_amount: 0,
    max_discount_amount: 0,
    start_date: '',
    end_date: '',
});

// Promotion Edit Form
const editForm = useForm({
    name: '',
    code: '',
    type: 'percent' as 'percent' | 'fixed_amount',
    value: 0,
    min_order_amount: 0,
    max_discount_amount: 0,
    start_date: '',
    end_date: '',
});

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

// --- ACTIONS ---
const submitAdd = () => {
    form.post('/promotions', {
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
    editForm.code = p.code;
    editForm.type = p.type;
    editForm.value = p.value;
    editForm.min_order_amount = p.min_order_amount;
    editForm.max_discount_amount = p.max_discount_amount;
    editForm.start_date = p.start_date ?? '';
    editForm.end_date = p.end_date ?? '';
    showEditModal.value = true;
};

const submitEdit = () => {
    if (!editingPromotion.value) {
        return;
    }

    editForm.put(`/promotions/${editingPromotion.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingPromotion.value = null;
            toast.success('Đã cập nhật chương trình khuyến mãi.');
        },
    });
};

const confirmDeletePromotion = async (p: Promotion) => {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Xóa chương trình khuyến mãi "${p.name}"? Hành động này không thể hoàn tác.`,
        }))
    ) {
        return;
    }

    router.delete(`/promotions/${p.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã xóa chương trình khuyến mãi.'),
    });
};

// Run Market Basket Analysis
const runBasketAnalysis = async () => {
    isAnalyzing.value = true;

    try {
        const res = await fetch('/api/promotions/basket-analysis');

        if (res.ok) {
            analysisResults.value = await res.json();
        } else {
            console.error('Lỗi khi chạy phân tích giỏ hàng');
        }
    } catch (e) {
        console.error(e);
    } finally {
        isAnalyzing.value = false;
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

    const totalOriginal = priceA + priceB;
    const discount = totalOriginal * 0.12; // Mặc định gợi ý giảm 12%

    comboForm.combo_price = Math.max(
        0,
        Math.round((totalOriginal - discount) / 1000) * 1000,
    );
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

const numberFormat = (val: number) => {
    return new Intl.NumberFormat('vi-VN').format(val);
};

onMounted(() => {
    // Tự động quét phân tích giỏ hàng lần đầu cho Owner/Manager
    runBasketAnalysis();
});
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

            <div class="flex items-center gap-2">
                <Button
                    v-if="isOwner || isManager"
                    @click="showAddModal = true"
                    class="flex h-10 items-center gap-1.5 bg-indigo-600 text-xs font-semibold text-white shadow-md shadow-indigo-500/10 transition-all hover:translate-y-[-1px] hover:bg-indigo-700"
                >
                    <Plus class="size-4" />
                    Tạo chương trình khuyến mãi
                </Button>
            </div>
        </div>

        <!-- TABS BAR -->
        <div class="-mt-2 flex border-b border-slate-200 dark:border-slate-800">
            <button
                @click="activeTab = 'promotions'"
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
                @click="activeTab = 'combo'"
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
                @click="activeTab = 'fraud'"
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
            <Card class="shadow-sm">
                <CardHeader class="border-b pb-3">
                    <CardTitle class="text-sm font-bold"
                        >Danh Sách Chương Trình Khuyến Mãi / Voucher</CardTitle
                    >
                    <CardDescription>
                        Chỉ có tài khoản Quản trị tối cao (Owner/Manager) mới
                        được quyền cấu hình và phê duyệt mã giảm giá để loại bỏ
                        nguy cơ cashier thông đồng áp mã vô tội vạ.
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
                            Không có chương trình khuyến mãi nào
                        </p>
                        <p class="max-w-sm text-xs">
                            Nhấn nút "Tạo chương trình khuyến mãi" ở góc trên
                            bên phải để bắt đầu thiết lập ưu đãi cho nhà hàng.
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
                                    <th class="p-4">Loại giảm</th>
                                    <th class="p-4">Giá trị giảm</th>
                                    <th class="p-4">Đơn hàng tối thiểu</th>
                                    <th class="p-4">Giảm tối đa</th>
                                    <th class="p-4">Thời gian hiệu lực</th>
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
                                    </td>
                                    <td class="p-4">
                                        <span
                                            class="rounded border border-indigo-100 bg-indigo-50 px-2.5 py-1 font-mono font-bold text-indigo-600 dark:border-indigo-900/40 dark:bg-indigo-950/40 dark:text-indigo-400"
                                        >
                                            {{ p.code }}
                                        </span>
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
                                    <td
                                        class="p-4 font-mono text-slate-600 dark:text-slate-400"
                                    >
                                        {{
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
                                    <td class="p-4 text-slate-500">
                                        {{ p.created_by_name }}
                                    </td>
                                    <td class="p-4">
                                        <button
                                            @click="toggleActive(p)"
                                            :class="[
                                                'rounded px-2 py-0.5 text-[10px] font-bold transition-all',
                                                p.is_active
                                                    ? 'border border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/30 dark:bg-emerald-950/20 dark:text-emerald-400'
                                                    : 'border border-slate-200 bg-slate-100 text-slate-400 dark:border-slate-800 dark:bg-slate-900/30 dark:text-slate-500',
                                            ]"
                                        >
                                            {{
                                                p.is_active
                                                    ? 'Hoạt động'
                                                    : 'Tắt'
                                            }}
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
                                                v-if="isOwner"
                                                @click="approvePromotion(p)"
                                                size="sm"
                                                class="h-7 bg-indigo-600 text-[10px] font-semibold text-white hover:bg-indigo-700"
                                            >
                                                Duyệt ngay
                                            </Button>
                                            <span
                                                v-else
                                                class="flex items-center gap-1 font-semibold text-amber-500"
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
                                            <button
                                                @click="openEditPromotion(p)"
                                                class="cursor-pointer rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/30"
                                                title="Chỉnh sửa"
                                            >
                                                <Pencil class="size-3.5" />
                                            </button>
                                            <button
                                                @click="
                                                    confirmDeletePromotion(p)
                                                "
                                                class="cursor-pointer rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30"
                                                title="Xóa"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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

                    <span
                        v-if="analysisResults"
                        class="rounded-full border border-indigo-100 bg-indigo-50 px-2.5 py-1 font-mono text-[10px] font-bold text-indigo-600"
                    >
                        Nguồn: {{ analysisResults.source }}
                    </span>
                </CardHeader>

                <CardContent class="p-6">
                    <div
                        v-if="!analysisResults"
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
                            {{
                                fraudAlerts.filter(
                                    (a) =>
                                        a.violation_type.includes('voucher') ||
                                        a.violation_type.includes('Voucher'),
                                ).length
                            }}
                        </span>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            sự cố áp mã bất thường được gắn cờ
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
                            💡 <strong>Quy tắc quét AI:</strong> Thuật toán tự
                            động giám sát và gắn cờ đỏ (Flagged) nếu tài khoản
                            cashier thực hiện áp mã giảm giá lớn hơn 20% liên
                            tiếp từ 3 lần trở lên trong vòng 15 phút. Hệ thống
                            lập tức bắn thông báo về máy Chủ nhà hàng.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- CRITICAL ALERTS LIST -->
            <div v-if="fraudAlerts.length > 0" class="space-y-3">
                <h3
                    class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-rose-600 uppercase"
                >
                    <ShieldAlert class="size-4 animate-pulse" /> Danh sách phát
                    hiện vi phạm nhạy cảm từ AI quét logs
                </h3>

                <div
                    v-for="alert in fraudAlerts"
                    :key="alert.id"
                    :class="[
                        'relative flex flex-col justify-between gap-4 overflow-hidden rounded-2xl border p-5 transition-all md:flex-row md:items-center',
                        alert.severity === 'critical'
                            ? 'animate-pulse border-rose-200 bg-rose-50/50 shadow-md shadow-rose-500/5 dark:border-rose-900/40 dark:bg-rose-950/20'
                            : 'border-amber-200 bg-amber-50/40 dark:border-amber-900/30 dark:bg-amber-950/15',
                    ]"
                >
                    <div class="max-w-3xl space-y-1.5">
                        <div class="flex items-center gap-2">
                            <span
                                :class="[
                                    'rounded border px-2 py-0.5 font-mono text-[8px] font-black tracking-wider uppercase',
                                    alert.severity === 'critical'
                                        ? 'border-rose-200 bg-rose-100 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-400'
                                        : 'border-amber-200 bg-amber-100 text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-400',
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
        <div
            v-if="showAddModal"
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

                <CardContent class="pt-4">
                    <form @submit.prevent="submitAdd" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="promo-name"
                                >Tên chương trình khuyến mãi
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                id="promo-name"
                                v-model="form.name"
                                placeholder="Chào hè 2026, Tri ân khách hàng..."
                                required
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="promo-code"
                                    >Mã Voucher (Code)
                                    <span class="text-[10px] text-slate-400"
                                        >(Có thể trống)</span
                                    ></Label
                                >
                                <Input
                                    id="promo-code"
                                    v-model="form.code"
                                    placeholder="GIAM20, HE2026..."
                                />
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="promo-type"
                                    >Loại giảm giá
                                    <span class="text-rose-500">*</span></Label
                                >
                                <select
                                    id="promo-type"
                                    v-model="form.type"
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
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
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="promo-val"
                                    type="number"
                                    v-model="form.value"
                                    min="0"
                                    required
                                />
                            </div>
                            <div class="col-span-2 grid gap-1.5">
                                <Label for="promo-min"
                                    >Đơn tối thiểu cần đạt (đ)</Label
                                >
                                <Input
                                    id="promo-min"
                                    type="number"
                                    v-model="form.min_order_amount"
                                    min="0"
                                />
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
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="promo-start">Ngày bắt đầu</Label>
                                <Input
                                    id="promo-start"
                                    type="datetime-local"
                                    v-model="form.start_date"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="promo-end">Ngày kết thúc</Label>
                                <Input
                                    id="promo-end"
                                    type="datetime-local"
                                    v-model="form.end_date"
                                />
                            </div>
                        </div>

                        <div
                            class="flex items-start gap-2 rounded-xl border border-amber-100 bg-amber-50/50 p-3 text-[10px] text-amber-700 dark:bg-amber-950/20 dark:text-amber-400"
                        >
                            <ShieldCheck
                                class="mt-0.5 size-4 shrink-0 text-amber-600"
                            />
                            <p>
                                <strong>Lưu ý quyền hạn:</strong> Nếu là tài
                                khoản **Quản lý (Manager)**, chương trình sau
                                khi tạo sẽ cần **Chủ nhà hàng (Owner)** duyệt để
                                chính thức có hiệu lực sử dụng.
                            </p>
                        </div>

                        <div class="flex justify-end gap-2 border-t pt-2">
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

        <!-- MODAL: EDIT PROMOTION -->
        <div
            v-if="showEditModal && editingPromotion"
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

                <CardContent class="pt-4">
                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="edit-promo-name"
                                >Tên chương trình khuyến mãi
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                id="edit-promo-name"
                                v-model="editForm.name"
                                placeholder="Chào hè 2026, Tri ân khách hàng..."
                                required
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="edit-promo-code"
                                    >Mã Voucher (Code)
                                    <span class="text-[10px] text-slate-400"
                                        >(Có thể trống)</span
                                    ></Label
                                >
                                <Input
                                    id="edit-promo-code"
                                    v-model="editForm.code"
                                    placeholder="GIAM20, HE2026..."
                                />
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="edit-promo-type"
                                    >Loại giảm giá
                                    <span class="text-rose-500">*</span></Label
                                >
                                <select
                                    id="edit-promo-type"
                                    v-model="editForm.type"
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
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
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="edit-promo-val"
                                    type="number"
                                    v-model="editForm.value"
                                    min="0"
                                    required
                                />
                            </div>
                            <div class="col-span-2 grid gap-1.5">
                                <Label for="edit-promo-min"
                                    >Đơn tối thiểu cần đạt (đ)</Label
                                >
                                <Input
                                    id="edit-promo-min"
                                    type="number"
                                    v-model="editForm.min_order_amount"
                                    min="0"
                                />
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
                            </div>
                        </div>

                        <div
                            class="flex items-start gap-2 rounded-xl border border-amber-100 bg-amber-50/50 p-3 text-[10px] text-amber-700 dark:bg-amber-950/20 dark:text-amber-400"
                        >
                            <ShieldCheck
                                class="mt-0.5 size-4 shrink-0 text-amber-600"
                            />
                            <p>
                                <strong>Lưu ý quyền hạn:</strong> Nếu là tài
                                khoản **Quản lý (Manager)**, chương trình sau
                                khi sửa sẽ cần **Chủ nhà hàng (Owner)** duyệt
                                lại để chính thức có hiệu lực.
                            </p>
                        </div>

                        <div class="flex justify-end gap-2 border-t pt-2">
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

        <!-- MODAL: QUICK COMBO CREATOR -->
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
                    <form @submit.prevent="createQuickCombo" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label
                                >Tên gói Combo mới
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input v-model="comboForm.name" required />
                        </div>

                        <p
                            v-if="!comboForm.item_a_id || !comboForm.item_b_id"
                            class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-600"
                        >
                            Không tìm thấy món tương ứng trong thực đơn hiện tại
                            — không thể tạo combo.
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
                                    v-model="comboForm.discount_percent"
                                    min="0"
                                    max="100"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Giá bán Combo (đ)</Label>
                                <Input
                                    type="number"
                                    v-model="comboForm.combo_price"
                                />
                            </div>
                        </div>

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
