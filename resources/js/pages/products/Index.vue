<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    UtensilsCrossed,
    Plus,
    FolderPlus,
    Search,
    CheckCircle2,
    AlertCircle,
    Pencil,
    Trash2,
    X,
    ChevronDown,
    ChevronUp,
    ToggleLeft,
    ToggleRight,
    Brain,
    Sparkles,
    AlertTriangle,
    RefreshCw,
    Coffee,
    Dessert,
    Beef,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
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
import { useApiGet } from '@/composables/useApiCall';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Category = {
    id: number;
    name: string;
    description: string | null;
    branch_id?: number | null;
    branch_name?: string | null;
};
type Product = {
    id: number;
    code: string;
    name: string;
    price: number;
    earn_points?: number;
    redeem_points?: number;
    description: string | null;
    category: Category | null;
    is_available: boolean;
    is_processed?: boolean;
    has_recipes?: boolean;
    recipes_count?: number;
    image_url: string | null;
    branch_id?: number | null;
    branch_name?: string | null;
};

const props = defineProps<{
    categories: Category[];
    products: Product[];
    branches: Array<{ id: number; name: string }>;
    activeBranchId: number | null;
    branchScope: string;
    canCreateShared: boolean;
    canManagePrices: boolean;
}>();

// ── AI Menu Insights ──────────────────────────────────────────────────────────
type MenuInsight = {
    type: string;
    severity: string;
    product: string;
    product_id: number;
    message: string;
    suggestion: string;
    value: number;
    unit: string;
};
const showInsights = ref(false);
const insightsLoaded = ref(false);
const insightsLoading = ref(false);
const insights = ref<MenuInsight[]>([]);
const bcgData = ref<any[]>([]);
const selectedBcgProduct = ref<any>(null);
const insightTab = ref('matrix'); // 'matrix' | 'alerts' | 'combos'
const isBcgScatterView = ref(true);

const stars = computed(() =>
    bcgData.value.filter((p) => p.quadrant === 'star'),
);
const plowhorses = computed(() =>
    bcgData.value.filter((p) => p.quadrant === 'plowhorse'),
);
const puzzles = computed(() =>
    bcgData.value.filter((p) => p.quadrant === 'puzzle'),
);
const dogs = computed(() => bcgData.value.filter((p) => p.quadrant === 'dog'));

async function loadInsights() {
    if (insightsLoaded.value) {
        showInsights.value = !showInsights.value;

        return;
    }

    insightsLoading.value = true;
    showInsights.value = true;

    try {
        const res = await fetch('/api/products/menu-insights');
        const data = await res.json();
        insights.value = data.insights ?? [];
        bcgData.value = data.bcg ?? [];

        if (bcgData.value.length > 0) {
            selectedBcgProduct.value = bcgData.value[0];
        }

        insightsLoaded.value = true;
    } catch {
        insights.value = [];
        bcgData.value = [];
    } finally {
        insightsLoading.value = false;
    }
}

// ── Market Basket Analysis & Combo Creator ────────────────────────────────────
// Trước đây khối này dùng fetch() với `catch { console.error(e) }`. Hai vấn đề:
// fetch KHÔNG ném lỗi khi server trả 403/500, nên `data.rules ?? []` âm thầm
// gán mảng rỗng và người dùng thấy "chưa có gợi ý" thay vì "bạn thiếu quyền";
// còn khi mạng hỏng thật thì lỗi chỉ nằm trong console.
const basketAnalysis = useApiGet<{ rules: any[] }>({
    fallbackMessage: 'Không tải được gợi ý combo từ phân tích giỏ hàng.',
});

const rules = computed(() => basketAnalysis.data.value?.rules ?? []);
const rulesLoading = basketAnalysis.isLoading;

async function loadBasketAnalysis() {
    if (basketAnalysis.hasRun.value && !basketAnalysis.error.value) {
        return;
    }

    await basketAnalysis.get('/api/promotions/basket-analysis');
}

watch(insightTab, (newTab) => {
    if (newTab === 'combos') {
        loadBasketAnalysis();
    }
});

// Combo Form State
const showComboModal = ref(false);
const comboForm = useForm({
    name: '',
    item_a_id: null as number | null,
    item_b_id: null as number | null,
    item_a_name: '',
    item_b_name: '',
    price_a: 0,
    price_b: 0,
    combo_price: 0,
    notes: '',
});

const openComboModal = (rule: any) => {
    const prodA = props.products.find(
        (p) => p.name.toLowerCase() === rule.item_a.toLowerCase(),
    );
    const prodB = props.products.find(
        (p) => p.name.toLowerCase() === rule.item_b.toLowerCase(),
    );

    if (!prodA || !prodB) {
        toast.error(
            'Không tìm thấy sản phẩm tương ứng trong thực đơn để tạo combo.',
        );

        return;
    }

    comboForm.name = `Combo Tiết Kiệm: ${prodA.name} & ${prodB.name}`;
    comboForm.item_a_id = prodA.id;
    comboForm.item_b_id = prodB.id;
    comboForm.item_a_name = prodA.name;
    comboForm.item_b_name = prodB.name;
    comboForm.price_a = prodA.price;
    comboForm.price_b = prodB.price;

    const sumPrice = prodA.price + prodB.price;
    // Suggest 12% discount
    comboForm.combo_price = Math.max(
        0,
        Math.round((sumPrice * 0.88) / 1000) * 1000,
    );
    comboForm.notes = `Combo kết hợp khoa học từ phân tích giỏ hàng AI. Món '${prodA.name}' thường được mua kèm với '${prodB.name}' (Độ tin cậy: ${Math.round(rule.confidence * 100)}%).`;

    showComboModal.value = true;
};

const submitCombo = () => {
    comboForm.post('/promotions/combos', {
        onSuccess: () => {
            showComboModal.value = false;
            comboForm.reset();
            toast.success('Đã thêm combo vào thực đơn thành công.');
            router.reload({ only: ['products'] });
        },
        onError: (err: any) => {
            if (err.combo_price) {
                toast.error(err.combo_price);
            } else {
                toast.error('Lỗi khi thiết lập Combo.');
            }
        },
    });
};

const getDotPosition = (p: any) => {
    const maxQty = Math.max(...bcgData.value.map((x) => x.total_qty), 1);
    const maxMargin = Math.max(...bcgData.value.map((x) => x.margin), 1);
    const minMargin = Math.min(...bcgData.value.map((x) => x.margin), 0);

    let x = 50;

    if (p.total_qty >= p.median_qty) {
        const range = maxQty - p.median_qty;
        x = 55 + (range > 0 ? ((p.total_qty - p.median_qty) / range) * 35 : 15);
    } else {
        const range = p.median_qty;
        x = 10 + (range > 0 ? (p.total_qty / range) * 35 : 15);
    }

    let y = 50;

    if (p.margin >= p.median_margin) {
        const range = maxMargin - p.median_margin;
        y = 40 - (range > 0 ? ((p.margin - p.median_margin) / range) * 30 : 15);
    } else {
        const range = p.median_margin - minMargin;
        y = 60 + (range > 0 ? ((p.median_margin - p.margin) / range) * 30 : 15);
    }

    return { x: Math.round(x), y: Math.round(y) };
};

// ── UI state ──────────────────────────────────────────────────────────────────
const showAddCategory = ref(false);
const showAddProduct = ref(false);
const editingProduct = ref<Product | null>(null);
const deletingProduct = ref<Product | null>(null);
const searchQuery = ref('');
const selectedCategory = ref<number | ''>('');
const selectedBranchFilter = ref<number | '' | 'shared'>('');

// true khi đang ở chế độ xem toàn chuỗi
const isAllScope = computed(() => props.branchScope === 'all');

// ── Forms ──────────────────────────────────────────────────────────────────────
const categoryForm = useForm({
    name: '',
    description: '',
    scope: 'branch',
    branch_id: props.activeBranchId ?? props.branches[0]?.id ?? '',
});

const productForm = useForm({
    category_id: props.categories[0]?.id ? String(props.categories[0].id) : '',
    name: '',
    price: '',
    is_processed: true,
    earn_points: 0,
    redeem_points: 0,
    description: '',
    image: null as File | null,
    scope: 'branch',
    branch_id: props.activeBranchId ?? props.branches[0]?.id ?? '',
});

const editForm = useForm({
    name: '',
    price: '',
    is_processed: true,
    earn_points: 0,
    redeem_points: 0,
    category_id: '',
    description: '',
    image: null as File | null,
});

// ── Computed ───────────────────────────────────────────────────────────────────
const filteredProducts = computed(() => {
    let list = props.products;

    // Khi scope=all, lọc thêm theo chi nhánh được chọn trong banner
    if (isAllScope.value && selectedBranchFilter.value !== '') {
        if (selectedBranchFilter.value === 'shared') {
            // Chỉ sản phẩm dùng chung (branch_id === null)
            list = list.filter(
                (p) => p.branch_id === null || p.branch_id === undefined,
            );
        } else {
            // Sản phẩm của chi nhánh đó + sản phẩm dùng chung
            list = list.filter(
                (p) =>
                    p.branch_id === selectedBranchFilter.value ||
                    p.branch_id === null ||
                    p.branch_id === undefined,
            );
        }
    }

    if (selectedCategory.value !== '') {
        list = list.filter((p) => p.category?.id === selectedCategory.value);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(
            (p) =>
                p.name.toLowerCase().includes(q) ||
                p.code.toLowerCase().includes(q) ||
                (p.description ?? '').toLowerCase().includes(q),
        );
    }

    return list;
});

const currentPage = ref(1);
const itemsPerPage = 10;
const totalPages = computed(() =>
    Math.ceil(filteredProducts.value.length / itemsPerPage),
);
const paginatedProducts = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;

    return filteredProducts.value.slice(start, start + itemsPerPage);
});

const visiblePages = computed(() => {
    const pages = [];
    const maxVisible = 5;
    let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2));
    const end = Math.min(totalPages.value, start + maxVisible - 1);

    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }

    return pages;
});

watch([selectedCategory, searchQuery, selectedBranchFilter], () => {
    currentPage.value = 1;
});

// ── Category & Product Helpers ───────────────────────────────────────────────
function getCategoryTheme(catName: string | null) {
    const name = (catName ?? '').toLowerCase();

    if (
        name.includes('đồ uống') ||
        name.includes('uống') ||
        name.includes('nước') ||
        name.includes('bia') ||
        name.includes('cà phê') ||
        name.includes('sinh tố')
    ) {
        return {
            icon: Coffee,
            bg: 'bg-blue-500/10 text-blue-500 dark:bg-blue-950/30 dark:text-blue-400',
            dot: 'bg-blue-500',
        };
    }

    if (
        name.includes('tráng miệng') ||
        name.includes('bánh') ||
        name.includes('chè') ||
        name.includes('kem')
    ) {
        return {
            icon: Dessert,
            bg: 'bg-pink-500/10 text-pink-500 dark:bg-pink-950/30 dark:text-pink-400',
            dot: 'bg-pink-500',
        };
    }

    if (
        name.includes('món chính') ||
        name.includes('cơm') ||
        name.includes('phở') ||
        name.includes('bún') ||
        name.includes('thịt') ||
        name.includes('mỳ')
    ) {
        return {
            icon: Beef,
            bg: 'bg-orange-500/10 text-orange-500 dark:bg-orange-950/30 dark:text-orange-400',
            dot: 'bg-orange-500',
        };
    }

    return {
        icon: UtensilsCrossed,
        bg: 'bg-slate-500/10 text-slate-500 dark:bg-slate-800/60 dark:text-slate-400',
        dot: 'bg-slate-500',
    };
}

// ── Handlers ──────────────────────────────────────────────────────────────────
const submitCategory = () => {
    categoryForm.post('/product-categories', {
        onSuccess: () => {
            categoryForm.reset();
            showAddCategory.value = false;
        },
    });
};

const submitProduct = () => {
    const isProcessed = productForm.is_processed;

    productForm.post('/products', {
        onSuccess: () => {
            showAddProduct.value = false;

            if (isProcessed) {
                toast.success(
                    'Đã thêm món cần chế biến! Vui lòng cài đặt công thức định lượng nguyên liệu tại trang Kho.',
                    {
                        action: {
                            label: 'Cài định lượng',
                            onClick: () => router.visit('/inventory'),
                        },
                    },
                );
            } else {
                toast.success(
                    'Đã thêm món không cần chế biến vào thực đơn bán ngay!',
                );
            }

            productForm.reset();
        },
    });
};

const openEditModal = (p: Product) => {
    editingProduct.value = p;
    editForm.name = p.name;
    editForm.price = String(p.price);
    editForm.is_processed = p.is_processed ?? true;
    editForm.earn_points = p.earn_points ?? 0;
    editForm.redeem_points = p.redeem_points ?? 0;
    editForm.category_id = p.category ? String(p.category.id) : '';
    editForm.description = p.description ?? '';
};

const submitEdit = () => {
    if (!editingProduct.value) {
        return;
    }

    router.post(
        `/products/${editingProduct.value.id}`,
        {
            _method: 'PATCH',
            name: editForm.name,
            ...(props.canManagePrices
                ? {
                      price: editForm.price,
                      is_processed: editForm.is_processed,
                      earn_points: editForm.earn_points,
                      redeem_points: editForm.redeem_points,
                  }
                : {}),
            category_id: editForm.category_id,
            description: editForm.description,
            image: editForm.image,
        },
        {
            onSuccess: () => {
                editingProduct.value = null;
                editForm.reset();
            },
        },
    );
};

const confirmDelete = (p: Product) => {
    deletingProduct.value = p;
};

const submitDelete = () => {
    if (!deletingProduct.value) {
        return;
    }

    router.delete(`/products/${deletingProduct.value.id}`, {
        onSuccess: () => {
            deletingProduct.value = null;
        },
    });
};

const formatCurrency = (val: number) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(val);

const toggleAvailability = (p: Product) => {
    const originalState = p.is_available;
    p.is_available = !originalState;

    router.patch(
        `/products/${p.id}`,
        { is_available: p.is_available },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['products', 'flash'],
            onError: () => {
                p.is_available = originalState;
                toast.error('Không thể cập nhật trạng thái món ăn.');
            },
        },
    );
};
</script>

<template>
    <Head title="Thực đơn & Món" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b border-border/80 pb-6 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 ring-4 ring-rose-500/5 dark:text-rose-400"
                >
                    <UtensilsCrossed class="size-6" />
                </div>
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-black tracking-tight text-foreground"
                    >
                        Thực Đơn & Món Ăn
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Quản lý cấu trúc thực đơn, nhóm món, giá bán sản phẩm
                        thực tế của quán.
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <!-- AI Insights button -->
                <Button
                    @click="loadInsights"
                    variant="outline"
                    class="flex h-10 cursor-pointer items-center gap-1.5 rounded-xl border-indigo-200 text-xs font-bold text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/20"
                >
                    <Brain class="size-4" />
                    AI Phân tích Menu
                    <component
                        :is="showInsights ? ChevronUp : ChevronDown"
                        class="size-3.5"
                    />
                </Button>
                <Button
                    id="btn-add-category"
                    @click="showAddCategory = true"
                    variant="outline"
                    class="h-10 cursor-pointer rounded-xl border-border text-xs font-bold"
                >
                    <FolderPlus class="mr-2 size-4 text-indigo-600" />Thêm nhóm
                    món
                </Button>
                <Button
                    id="btn-add-product"
                    v-if="canManagePrices"
                    @click="showAddProduct = true"
                    class="bg-rose-650 h-10 cursor-pointer rounded-xl text-xs font-bold text-white shadow-sm hover:bg-rose-700"
                >
                    <Plus class="mr-2 size-4" />Thêm món ăn
                </Button>
            </div>
        </div>

        <!-- Chain scope banner — chỉ hiện khi đang xem Toàn chuỗi -->
        <div
            v-if="isAllScope"
            class="flex flex-wrap items-center gap-3 rounded-2xl border border-blue-200/70 bg-blue-50/60 px-4 py-3 dark:border-blue-800/40 dark:bg-blue-950/20"
        >
            <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-lg"
            >
                🌐
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold text-blue-800 dark:text-blue-300">
                    Đang xem Toàn chuỗi —
                    <span class="font-black">{{ products.length }}</span>
                    sản phẩm từ tất cả chi nhánh
                </p>
                <p class="text-[10px] text-blue-600/80 dark:text-blue-400/80">
                    Bao gồm sản phẩm dùng chung và riêng từng chi nhánh. Khi
                    thêm mới, bạn cần chọn chi nhánh đích.
                </p>
            </div>
            <!-- Quick branch filter -->
            <select
                v-if="branches.length > 1"
                v-model="selectedBranchFilter"
                class="h-8 shrink-0 rounded-xl border border-blue-200 bg-white px-3 text-[11px] font-bold text-blue-800 focus:outline-none dark:border-blue-800/50 dark:bg-slate-900 dark:text-blue-300"
            >
                <option value="">Tất cả chi nhánh</option>
                <option value="shared">🔗 Dùng chung toàn chuỗi</option>
                <option
                    v-for="branch in branches"
                    :key="branch.id"
                    :value="branch.id"
                >
                    📍 {{ branch.name }}
                </option>
            </select>
        </div>

        <!-- AI Menu Insights Accordion -->
        <div
            v-if="showInsights"
            class="animate-in overflow-hidden rounded-2xl border border-indigo-200 bg-indigo-50/50 shadow-lg duration-200 fade-in dark:border-indigo-800/40 dark:bg-indigo-950/10"
        >
            <!-- Header and Tab Selection -->
            <div
                class="flex flex-col justify-between gap-3 border-b border-indigo-100 bg-muted/20 px-5 py-3 sm:flex-row sm:items-center dark:border-indigo-800/30"
            >
                <div class="flex items-center gap-2">
                    <Sparkles class="size-4 animate-pulse text-indigo-500" />
                    <span
                        class="text-sm font-bold text-indigo-700 dark:text-indigo-300"
                        >AI Kỹ nghệ thực đơn (Menu Engineering)</span
                    >
                </div>
                <div
                    class="flex items-center gap-1 self-start rounded-lg border border-border bg-muted p-0.5 text-xs sm:self-auto"
                >
                    <button
                        type="button"
                        @click="insightTab = 'matrix'"
                        :class="[
                            'cursor-pointer rounded-md px-3 py-1.5 font-bold transition-all',
                            insightTab === 'matrix'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        Ma trận Boston (BCG)
                    </button>
                    <button
                        type="button"
                        @click="insightTab = 'combos'"
                        :class="[
                            'cursor-pointer rounded-md px-3 py-1.5 font-bold transition-all',
                            insightTab === 'combos'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        Gợi ý Combo
                    </button>
                    <button
                        type="button"
                        @click="insightTab = 'alerts'"
                        :class="[
                            'cursor-pointer rounded-md px-3 py-1.5 font-bold transition-all',
                            insightTab === 'alerts'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        Cảnh báo
                    </button>
                </div>
            </div>

            <!-- Tab: BCG Matrix -->
            <div
                v-if="insightTab === 'matrix'"
                class="grid animate-in grid-cols-1 gap-6 p-5 duration-200 fade-in lg:grid-cols-3"
            >
                <!-- Left: Matrix View Toggle and the Visualizations -->
                <div class="space-y-4 lg:col-span-2">
                    <!-- View Toggle Bar -->
                    <div
                        class="flex items-center justify-between border-b border-border/80 pb-3"
                    >
                        <span
                            class="text-slate-650 text-xs font-bold dark:text-slate-400"
                        >
                            Bố cục hiển thị Ma trận BCG:
                        </span>
                        <div
                            class="inline-flex rounded-lg bg-slate-100 p-0.5 text-[10px] dark:bg-slate-800"
                        >
                            <button
                                type="button"
                                @click="isBcgScatterView = true"
                                :class="[
                                    'inline-flex cursor-pointer items-center gap-1 rounded-md px-2.5 py-1.5 font-extrabold transition',
                                    isBcgScatterView
                                        ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-white'
                                        : 'text-slate-500 hover:text-slate-900',
                                ]"
                            >
                                🎯 Sơ đồ ma trận 2D
                            </button>
                            <button
                                type="button"
                                @click="isBcgScatterView = false"
                                :class="[
                                    'inline-flex cursor-pointer items-center gap-1 rounded-md px-2.5 py-1.5 font-extrabold transition',
                                    !isBcgScatterView
                                        ? 'bg-white text-slate-900 shadow-xs dark:bg-slate-900 dark:text-white'
                                        : 'text-slate-500 hover:text-slate-900',
                                ]"
                            >
                                📋 Danh sách nhóm
                            </button>
                        </div>
                    </div>

                    <!-- 2D SCATTER QUADRANT MAP -->
                    <div
                        v-if="isBcgScatterView"
                        class="relative h-[340px] w-full overflow-hidden rounded-2xl border border-border bg-card shadow-inner"
                    >
                        <!-- Empty State inside Canvas -->
                        <div
                            v-if="bcgData.length === 0"
                            class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center text-muted-foreground"
                        >
                            <Sparkles
                                class="mb-2 size-8 text-indigo-500 opacity-20"
                            />
                            <p class="text-xs font-bold">
                                Không có dữ liệu món ăn trong ma trận
                            </p>
                        </div>

                        <template v-else>
                            <!-- Quadrant Background Glows -->
                            <div
                                class="pointer-events-none absolute top-0 right-1/2 bottom-1/2 left-0 bg-purple-500/[0.02] dark:bg-purple-500/[0.01]"
                            ></div>
                            <div
                                class="pointer-events-none absolute top-0 right-0 bottom-1/2 left-1/2 bg-emerald-500/[0.03] dark:bg-emerald-500/[0.01]"
                            ></div>
                            <div
                                class="pointer-events-none absolute top-1/2 right-1/2 bottom-0 left-0 bg-rose-500/[0.03] dark:bg-rose-500/[0.01]"
                            ></div>
                            <div
                                class="pointer-events-none absolute top-1/2 right-0 bottom-0 left-1/2 bg-amber-500/[0.03] dark:bg-amber-500/[0.01]"
                            ></div>

                            <!-- Horizontal Axis (Margin median line) -->
                            <div
                                class="pointer-events-none absolute top-1/2 right-0 left-0 h-0.5 border-t border-dashed border-border"
                            />
                            <span
                                class="pointer-events-none absolute top-[calc(50%-18px)] right-2 rounded border border-border/80 bg-background/90 px-1.5 py-0.5 text-[9px] font-bold text-muted-foreground"
                            >
                                Margin trung vị ({{
                                    bcgData[0]?.median_margin
                                }}%)
                            </span>

                            <!-- Vertical Axis (Qty median line) -->
                            <div
                                class="pointer-events-none absolute top-0 bottom-0 left-1/2 w-0.5 border-l border-dashed border-border"
                            />
                            <span
                                class="pointer-events-none absolute top-2 left-[calc(50%+8px)] rounded border border-border/80 bg-background/90 px-1.5 py-0.5 text-[9px] font-bold text-muted-foreground"
                            >
                                Sản lượng trung vị ({{
                                    bcgData[0]?.median_qty
                                }})
                            </span>

                            <!-- Quadrant Labels -->
                            <div
                                class="pointer-events-none absolute top-3 left-4 text-[9px] font-black tracking-widest text-purple-500/80 uppercase select-none"
                            >
                                🧩 Puzzles (Lợi nhuận cao - Bán chậm)
                            </div>
                            <div
                                class="pointer-events-none absolute top-3 right-4 text-right text-[9px] font-black tracking-widest text-emerald-500/80 uppercase select-none"
                            >
                                ⭐ Stars (Chủ lực - Bán chạy & Lợi cao)
                            </div>
                            <div
                                class="pointer-events-none absolute bottom-3 left-4 text-[9px] font-black tracking-widest text-rose-500/80 uppercase select-none"
                            >
                                🐶 Dogs (Yếu kém - Bán chậm & Lợi thấp)
                            </div>
                            <div
                                class="pointer-events-none absolute right-4 bottom-3 text-right text-[9px] font-black tracking-widest text-amber-500/80 uppercase select-none"
                            >
                                🐎 Plowhorses (Bò sữa - Bán chạy & Lợi thấp)
                            </div>

                            <!-- Points -->
                            <button
                                v-for="p in bcgData"
                                :key="p.product_id"
                                type="button"
                                @click="selectedBcgProduct = p"
                                :style="{
                                    left: `${getDotPosition(p).x}%`,
                                    top: `${getDotPosition(p).y}%`,
                                }"
                                class="group absolute z-20 flex h-4 w-4 -translate-x-1/2 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-card shadow-md transition-transform duration-200 hover:scale-125"
                                :class="[
                                    p.quadrant === 'star'
                                        ? 'bg-emerald-500'
                                        : p.quadrant === 'plowhorse'
                                          ? 'bg-amber-500'
                                          : p.quadrant === 'puzzle'
                                            ? 'bg-purple-500'
                                            : 'bg-rose-500',
                                    selectedBcgProduct?.product_id ===
                                    p.product_id
                                        ? 'scale-120 font-bold ring-4 ring-indigo-500/30 ring-offset-1 dark:ring-indigo-400/40'
                                        : '',
                                ]"
                            >
                                <!-- Selected Dot Ping -->
                                <span
                                    v-if="
                                        selectedBcgProduct?.product_id ===
                                        p.product_id
                                    "
                                    class="absolute inset-0 animate-ping rounded-full opacity-75"
                                    :class="[
                                        p.quadrant === 'star'
                                            ? 'bg-emerald-400'
                                            : p.quadrant === 'plowhorse'
                                              ? 'bg-amber-400'
                                              : p.quadrant === 'puzzle'
                                                ? 'bg-purple-400'
                                                : 'bg-rose-400',
                                    ]"
                                />

                                <!-- Tooltip on hover -->
                                <span
                                    class="pointer-events-none absolute bottom-6 left-1/2 z-50 flex -translate-x-1/2 scale-0 flex-col items-center gap-0.5 rounded-lg bg-slate-900 px-2.5 py-1.5 text-[10px] font-bold whitespace-nowrap text-white shadow-lg transition-all group-hover:scale-100 dark:bg-slate-800"
                                >
                                    <span class="font-extrabold">{{
                                        p.name
                                    }}</span>
                                    <span
                                        class="text-[8px] font-normal opacity-75"
                                        >Margin: {{ p.margin }}% · Đã bán:
                                        {{ p.total_qty }} món</span
                                    >
                                </span>
                            </button>
                        </template>
                    </div>

                    <!-- QUADRANTS LIST GRID -->
                    <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <!-- Top-Left: Puzzles -->
                        <div
                            class="flex min-h-[160px] flex-col justify-between rounded-xl border border-purple-200/50 bg-purple-50/20 p-4 transition-shadow hover:shadow-xs dark:bg-purple-950/5"
                        >
                            <div>
                                <div
                                    class="mb-3 flex items-center justify-between border-b border-purple-100 pb-2 dark:border-purple-900/40"
                                >
                                    <h5
                                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-purple-700 uppercase dark:text-purple-400"
                                    >
                                        <span>🧩</span> Puzzles (Lợi nhuận cao)
                                    </h5>
                                    <span
                                        class="text-[9px] text-muted-foreground"
                                        >Volume thấp, Margin cao</span
                                    >
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        v-for="p in puzzles"
                                        :key="p.product_id"
                                        @click="selectedBcgProduct = p"
                                        :class="[
                                            'cursor-pointer rounded-lg border px-2.5 py-1 text-xs font-bold transition-all',
                                            selectedBcgProduct?.product_id ===
                                            p.product_id
                                                ? 'bg-purple-650 border-purple-650 scale-105 text-white'
                                                : 'text-purple-750 border-purple-200 bg-background hover:bg-purple-50/50 dark:hover:bg-purple-950/30',
                                        ]"
                                    >
                                        {{ p.name }}
                                    </button>
                                    <p
                                        v-if="puzzles.length === 0"
                                        class="py-2 text-[11px] text-muted-foreground italic"
                                    >
                                        Chưa có món ăn phân loại này.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Top-Right: Stars -->
                        <div
                            class="flex min-h-[160px] flex-col justify-between rounded-xl border border-emerald-200/50 bg-emerald-50/20 p-4 transition-shadow hover:shadow-xs dark:bg-emerald-950/5"
                        >
                            <div>
                                <div
                                    class="mb-3 flex items-center justify-between border-b border-emerald-100 pb-2 dark:border-emerald-900/40"
                                >
                                    <h5
                                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-emerald-700 uppercase dark:text-emerald-400"
                                    >
                                        <span>⭐</span> Stars (Ngôi sao chủ lực)
                                    </h5>
                                    <span
                                        class="text-[9px] text-muted-foreground"
                                        >Volume cao, Margin cao</span
                                    >
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        v-for="p in stars"
                                        :key="p.product_id"
                                        @click="selectedBcgProduct = p"
                                        :class="[
                                            'cursor-pointer rounded-lg border px-2.5 py-1 text-xs font-bold transition-all',
                                            selectedBcgProduct?.product_id ===
                                            p.product_id
                                                ? 'scale-105 border-emerald-600 bg-emerald-600 text-white'
                                                : 'text-emerald-750 border-emerald-200 bg-background hover:bg-emerald-50/50 dark:hover:bg-emerald-950/30',
                                        ]"
                                    >
                                        {{ p.name }}
                                    </button>
                                    <p
                                        v-if="stars.length === 0"
                                        class="py-2 text-[11px] text-muted-foreground italic"
                                    >
                                        Chưa có món ăn phân loại này.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom-Left: Dogs -->
                        <div
                            class="flex min-h-[160px] flex-col justify-between rounded-xl border border-rose-200/50 bg-rose-50/20 p-4 transition-shadow hover:shadow-xs dark:bg-rose-950/5"
                        >
                            <div>
                                <div
                                    class="mb-3 flex items-center justify-between border-b border-rose-100 pb-2 dark:border-rose-900/40"
                                >
                                    <h5
                                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-rose-700 uppercase dark:text-rose-400"
                                    >
                                        <span>🐶</span> Dogs (Yếu kém / Cần lọc)
                                    </h5>
                                    <span
                                        class="text-[9px] text-muted-foreground"
                                        >Volume thấp, Margin thấp</span
                                    >
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        v-for="p in dogs"
                                        :key="p.product_id"
                                        @click="selectedBcgProduct = p"
                                        :class="[
                                            'cursor-pointer rounded-lg border px-2.5 py-1 text-xs font-bold transition-all',
                                            selectedBcgProduct?.product_id ===
                                            p.product_id
                                                ? 'scale-105 border-rose-600 bg-rose-600 text-white'
                                                : 'text-rose-750 border-rose-200 bg-background hover:bg-rose-50/50 dark:hover:bg-rose-950/30',
                                        ]"
                                    >
                                        {{ p.name }}
                                    </button>
                                    <p
                                        v-if="dogs.length === 0"
                                        class="py-2 text-[11px] text-muted-foreground italic"
                                    >
                                        Chưa có món ăn phân loại này.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom-Right: Plowhorses -->
                        <div
                            class="flex min-h-[160px] flex-col justify-between rounded-xl border border-amber-200/50 bg-amber-50/20 p-4 transition-shadow hover:shadow-xs dark:bg-amber-950/5"
                        >
                            <div>
                                <div
                                    class="mb-3 flex items-center justify-between border-b border-amber-100 pb-2 dark:border-amber-900/40"
                                >
                                    <h5
                                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-amber-700 uppercase dark:text-amber-400"
                                    >
                                        <span>🐎</span> Plowhorses (Bò sữa)
                                    </h5>
                                    <span
                                        class="text-[9px] text-muted-foreground"
                                        >Volume cao, Margin thấp</span
                                    >
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        v-for="p in plowhorses"
                                        :key="p.product_id"
                                        @click="selectedBcgProduct = p"
                                        :class="[
                                            'cursor-pointer rounded-lg border px-2.5 py-1 text-xs font-bold transition-all',
                                            selectedBcgProduct?.product_id ===
                                            p.product_id
                                                ? 'scale-105 border-amber-600 bg-amber-600 text-white'
                                                : 'text-amber-750 border-amber-200 bg-background hover:bg-amber-50/50 dark:hover:bg-amber-950/30',
                                        ]"
                                    >
                                        {{ p.name }}
                                    </button>
                                    <p
                                        v-if="plowhorses.length === 0"
                                        class="py-2 text-[11px] text-muted-foreground italic"
                                    >
                                        Chưa có món ăn phân loại này.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Detail Analysis (Right) -->
                <Card
                    class="flex flex-col justify-between border bg-card shadow-xs"
                >
                    <div>
                        <CardHeader
                            class="border-b border-border bg-muted/10 pb-3"
                        >
                            <CardTitle
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Chi tiết đề xuất món ăn
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4 p-4">
                            <div v-if="selectedBcgProduct" class="space-y-4">
                                <div>
                                    <h4
                                        class="text-base leading-tight font-extrabold text-foreground"
                                    >
                                        {{ selectedBcgProduct.name }}
                                    </h4>
                                    <div class="mt-2 flex items-center gap-2">
                                        <Badge
                                            :class="[
                                                'rounded-full border-0 px-2 py-0.5 text-[9px] font-extrabold tracking-wider',
                                                selectedBcgProduct.quadrant ===
                                                'star'
                                                    ? 'bg-emerald-500/10 text-emerald-500'
                                                    : selectedBcgProduct.quadrant ===
                                                        'plowhorse'
                                                      ? 'bg-amber-500/10 text-amber-500'
                                                      : selectedBcgProduct.quadrant ===
                                                          'puzzle'
                                                        ? 'bg-purple-500/10 text-purple-500'
                                                        : 'bg-rose-500/10 text-rose-500',
                                            ]"
                                        >
                                            {{
                                                selectedBcgProduct.quadrant ===
                                                'star'
                                                    ? '⭐ Ngôi sao'
                                                    : selectedBcgProduct.quadrant ===
                                                        'plowhorse'
                                                      ? '🐎 Bò sữa'
                                                      : selectedBcgProduct.quadrant ===
                                                          'puzzle'
                                                        ? '🧩 Câu đố'
                                                        : '🐶 Thú cưng'
                                            }}
                                        </Badge>
                                    </div>
                                </div>

                                <!-- Financial Metrics Grid -->
                                <div
                                    class="grid grid-cols-2 gap-3 rounded-xl border border-border/60 bg-muted/20 p-3 text-[10px]"
                                >
                                    <div>
                                        <p
                                            class="font-semibold text-muted-foreground"
                                        >
                                            Doanh thu bán
                                        </p>
                                        <p
                                            class="mt-0.5 text-xs font-extrabold text-foreground"
                                        >
                                            {{
                                                formatCurrency(
                                                    selectedBcgProduct.total_revenue,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="font-semibold text-muted-foreground"
                                        >
                                            Sản lượng (30D)
                                        </p>
                                        <p
                                            class="mt-0.5 text-xs font-extrabold text-foreground"
                                        >
                                            {{ selectedBcgProduct.total_qty }}
                                            món (Median:
                                            {{ selectedBcgProduct.median_qty }})
                                        </p>
                                    </div>
                                    <div
                                        class="mt-1 border-t border-border/80 pt-2"
                                    >
                                        <p
                                            class="font-semibold text-muted-foreground"
                                        >
                                            Giá vốn / Giá bán
                                        </p>
                                        <p
                                            class="mt-0.5 text-xs font-extrabold text-foreground"
                                        >
                                            {{
                                                formatCurrency(
                                                    selectedBcgProduct.cost_price,
                                                )
                                            }}
                                            /
                                            {{
                                                formatCurrency(
                                                    selectedBcgProduct.price,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="mt-1 border-t border-border/80 pt-2"
                                    >
                                        <p
                                            class="font-semibold text-muted-foreground"
                                        >
                                            Lợi nhuận %
                                        </p>
                                        <p
                                            class="mt-0.5 text-xs font-black text-indigo-500 dark:text-indigo-400"
                                        >
                                            {{ selectedBcgProduct.margin }}%
                                            (Median:
                                            {{
                                                selectedBcgProduct.median_margin
                                            }}%)
                                        </p>
                                    </div>
                                </div>

                                <!-- AI Recommendation Box -->
                                <div
                                    class="flex gap-3 rounded-xl border border-indigo-500/10 bg-indigo-500/5 p-4 dark:border-indigo-500/20"
                                >
                                    <Sparkles
                                        class="mt-0.5 h-5 w-5 shrink-0 text-indigo-500"
                                    />
                                    <div class="space-y-1">
                                        <h5
                                            class="text-indigo-550 text-xs font-bold dark:text-indigo-400"
                                        >
                                            Khuyến nghị AI
                                        </h5>
                                        <p
                                            class="text-slate-650 dark:text-slate-350 text-[11px] leading-relaxed"
                                        >
                                            {{
                                                selectedBcgProduct.ai_recommendation
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-else
                                class="py-10 text-center text-xs text-muted-foreground"
                            >
                                Chọn một món ăn trên sơ đồ ma trận để phân tích.
                            </div>
                        </CardContent>
                    </div>
                </Card>
            </div>

            <!-- Tab: Gợi ý Combo (Market Basket Analysis) -->
            <div
                v-if="insightTab === 'combos'"
                class="animate-in space-y-4 p-5 duration-200 fade-in"
            >
                <div
                    class="mb-2 flex flex-col justify-between gap-4 border-b border-indigo-100 pb-3 sm:flex-row sm:items-center dark:border-indigo-800/30"
                >
                    <div>
                        <h4
                            class="text-indigo-750 text-xs font-extrabold tracking-wider uppercase dark:text-indigo-400"
                        >
                            Gợi Ý Thiết Lập Combo Món Ăn Bán Kèm
                        </h4>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            Cặp sản phẩm xuất hiện cùng nhau nhiều nhất trong
                            các giao dịch thực tế (Apriori Market Basket).
                        </p>
                    </div>
                    <span
                        v-if="rules.length"
                        class="text-indigo-550 self-start rounded-full bg-indigo-500/15 px-2.5 py-0.5 text-[9px] font-bold sm:self-auto dark:text-indigo-400"
                    >
                        Tự động cập nhật
                    </span>
                </div>

                <!-- Loading -->
                <div
                    v-if="rulesLoading"
                    class="flex items-center justify-center gap-2 py-16 text-indigo-500"
                >
                    <RefreshCw class="size-5 animate-spin" />
                    <span class="text-xs font-bold"
                        >AI đang quét dữ liệu hóa đơn...</span
                    >
                </div>

                <!-- Error State -->
                <div
                    v-else-if="basketAnalysis.error.value"
                    class="flex flex-col items-center rounded-2xl border border-dashed border-rose-300 bg-card py-12 text-center dark:border-rose-900/50"
                >
                    <p
                        class="text-xs font-bold text-rose-600 dark:text-rose-400"
                    >
                        Không tải được gợi ý combo
                    </p>
                    <p class="mt-1 max-w-xs text-[10px] text-muted-foreground">
                        {{ basketAnalysis.error.value }}
                    </p>
                    <button
                        type="button"
                        class="mt-3 rounded-lg border border-border px-3 py-1.5 text-[10px] font-bold text-foreground/80 transition-colors hover:bg-muted"
                        @click="basketAnalysis.retry()"
                    >
                        Thử lại
                    </button>
                </div>

                <template v-else>
                    <!-- Empty State -->
                    <div
                        v-if="rules.length === 0"
                        class="flex flex-col items-center rounded-2xl border border-dashed border-border/80 bg-card py-12 text-center text-slate-400"
                    >
                        <CheckCircle2 class="mb-2 size-8 text-indigo-400" />
                        <p class="text-xs font-bold text-foreground/80">
                            Chưa tìm thấy gợi ý Combo nào
                        </p>
                        <p
                            class="mt-1 max-w-xs text-[10px] text-muted-foreground"
                        >
                            Nhà hàng cần tích lũy thêm các đơn hàng chứa từ 2
                            món trở lên để chạy phân tích giỏ hàng.
                        </p>
                    </div>

                    <!-- Grid -->
                    <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div
                            v-for="(rule, idx) in rules.slice(0, 10)"
                            :key="idx"
                            class="group flex flex-col justify-between gap-4 rounded-xl border border-border bg-card p-4 transition duration-150 hover:border-indigo-500/35 hover:shadow-md"
                        >
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span
                                        class="flex items-center gap-1.5 text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        <span
                                            class="flex h-5 w-5 items-center justify-center rounded-full bg-muted text-[9px] font-bold text-foreground"
                                            >{{ idx + 1 }}</span
                                        >
                                        Liên kết mua kèm
                                    </span>
                                    <Badge
                                        variant="secondary"
                                        class="border-0 bg-emerald-500/10 font-mono text-[9px] font-black text-emerald-500"
                                    >
                                        Lift: {{ rule.lift }}
                                    </Badge>
                                </div>

                                <!-- A -> B Visual -->
                                <div
                                    class="flex items-center justify-between gap-3 rounded-lg border border-border/40 bg-muted/30 p-2.5 text-xs"
                                >
                                    <div class="min-w-0 flex-1 text-center">
                                        <p
                                            class="text-[9px] font-semibold text-muted-foreground"
                                        >
                                            Khách gọi món
                                        </p>
                                        <p
                                            class="mt-0.5 truncate font-bold text-foreground"
                                        >
                                            {{ rule.item_a }}
                                        </p>
                                    </div>
                                    <Sparkles
                                        class="size-3.5 shrink-0 text-indigo-500"
                                    />
                                    <div class="min-w-0 flex-1 text-center">
                                        <p
                                            class="text-[9px] font-semibold text-muted-foreground"
                                        >
                                            Thường mua kèm
                                        </p>
                                        <p
                                            class="mt-0.5 truncate font-bold text-foreground"
                                        >
                                            {{ rule.item_b }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Support and Confidence -->
                                <div
                                    class="grid grid-cols-2 gap-2 text-center text-[10px]"
                                >
                                    <div class="rounded-lg bg-muted/40 p-2">
                                        <p
                                            class="font-semibold text-muted-foreground"
                                        >
                                            Độ tin cậy (Confidence)
                                        </p>
                                        <p
                                            class="mt-0.5 font-black text-foreground"
                                        >
                                            {{
                                                Math.round(
                                                    rule.confidence * 100,
                                                )
                                            }}%
                                        </p>
                                    </div>
                                    <div class="rounded-lg bg-muted/40 p-2">
                                        <p
                                            class="font-semibold text-muted-foreground"
                                        >
                                            Số lần gọi chung
                                        </p>
                                        <p
                                            class="mt-0.5 font-black text-foreground"
                                        >
                                            {{ rule.co_occurrence }} đơn
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <Button
                                size="sm"
                                type="button"
                                class="h-8 w-full cursor-pointer rounded-xl bg-slate-800 text-[10px] font-bold text-white transition-colors group-hover:bg-indigo-600 hover:bg-slate-900"
                                @click="openComboModal(rule)"
                            >
                                <Plus class="mr-1 size-3" /> Thiết lập Combo đề
                                xuất
                            </Button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Tab: Existing alerts -->
            <div
                v-if="insightTab === 'alerts'"
                class="animate-in divide-y divide-indigo-100 duration-200 fade-in dark:divide-indigo-800/30"
            >
                <div
                    v-if="!insights.length"
                    class="flex flex-col items-center py-12 text-center text-slate-400"
                >
                    <CheckCircle2 class="mb-2 size-8 text-emerald-400" />
                    <p class="text-sm font-semibold text-foreground">
                        Menu đang hoạt động tốt!
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Không phát hiện vấn đề cần cảnh báo trong 30 ngày qua.
                    </p>
                </div>

                <div v-else>
                    <div
                        v-for="(item, i) in insights"
                        :key="i"
                        :class="[
                            'flex items-start gap-3 px-4 py-3.5 text-xs transition-colors hover:bg-indigo-50/20',
                            item.severity === 'critical'
                                ? 'bg-rose-500/5 dark:bg-rose-950/10'
                                : item.severity === 'warning'
                                  ? 'bg-amber-500/5 dark:bg-amber-950/10'
                                  : '',
                        ]"
                    >
                        <span class="mt-0.5 shrink-0 text-base">
                            {{
                                item.severity === 'critical'
                                    ? '🔴'
                                    : item.severity === 'warning'
                                      ? '🟡'
                                      : '🔵'
                            }}
                        </span>
                        <div class="flex-1">
                            <p
                                class="font-bold text-foreground"
                                v-html="item.message"
                            />
                            <p
                                class="mt-1 flex items-center gap-1 text-muted-foreground"
                            >
                                <AlertTriangle
                                    class="size-3 shrink-0 text-amber-500"
                                />
                                {{ item.suggestion }}
                            </p>
                        </div>
                        <Badge
                            :class="[
                                'shrink-0 rounded-full border px-2 py-0.5 text-[9px] font-bold',
                                item.severity === 'critical'
                                    ? 'border-rose-500/20 bg-rose-500/10 text-rose-500'
                                    : item.severity === 'warning'
                                      ? 'border-amber-500/20 bg-amber-500/10 text-amber-500'
                                      : 'border-blue-500/20 bg-blue-500/10 text-blue-500',
                            ]"
                        >
                            {{ item.value }}{{ item.unit }}
                        </Badge>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <!-- Left: Categories -->
            <div class="flex flex-col gap-4 lg:col-span-1">
                <Card class="rounded-2xl border-border bg-card shadow-sm">
                    <CardHeader class="border-b border-border/60 pb-3">
                        <CardTitle
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >Nhóm Món Ăn</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2 p-3">
                        <!-- All filter -->
                        <button
                            @click="selectedCategory = ''"
                            class="w-full cursor-pointer rounded-xl border p-3 text-left text-xs transition-colors"
                            :class="
                                selectedCategory === ''
                                    ? 'border-rose-500/35 bg-rose-500/5 font-bold shadow-xs'
                                    : 'border-border/60 bg-muted/20 text-muted-foreground hover:border-rose-500/20'
                            "
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-foreground">
                                        Tất cả món
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-muted-foreground"
                                    >
                                        {{ products.length }} món ăn
                                    </p>
                                </div>
                                <span
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border bg-muted text-[10px] font-bold text-foreground/80"
                                >
                                    {{ products.length }}
                                </span>
                            </div>
                        </button>

                        <div
                            v-if="categories.length"
                            class="flex flex-col gap-1.5"
                        >
                            <div
                                v-for="cat in categories"
                                :key="cat.id"
                                @click="
                                    selectedCategory =
                                        selectedCategory === cat.id
                                            ? ''
                                            : cat.id
                                "
                                class="group/cat relative w-full cursor-pointer rounded-xl border p-3 text-xs transition-colors"
                                :class="
                                    selectedCategory === cat.id
                                        ? 'border-rose-500/35 bg-rose-500/5 font-bold shadow-xs'
                                        : 'border-border/60 bg-muted/20 text-muted-foreground hover:border-rose-500/20'
                                "
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold text-foreground">
                                            {{ cat.name }}
                                        </p>
                                        <!-- Label chi nhánh nguồn — chỉ hiện khi scope=all -->
                                        <span
                                            v-if="isAllScope && cat.branch_id"
                                            class="mt-0.5 block text-[9px] font-semibold text-blue-500/80 dark:text-blue-400/70"
                                        >
                                            📍
                                            {{
                                                cat.branch_name ??
                                                'Chi nhánh riêng'
                                            }}
                                        </span>
                                        <span
                                            v-else-if="
                                                isAllScope && !cat.branch_id
                                            "
                                            class="mt-0.5 block text-[9px] font-semibold text-blue-500/60 dark:text-blue-400/50"
                                        >
                                            🔗 Dùng chung
                                        </span>
                                        <p
                                            class="mt-0.5 truncate text-[10px] text-muted-foreground"
                                        >
                                            {{
                                                cat.description ??
                                                'Không có mô tả.'
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="z-10 flex shrink-0 items-center gap-1.5"
                                    >
                                        <span
                                            class="flex h-6 w-6 items-center justify-center rounded-lg border bg-muted text-[10px] font-bold text-foreground/80"
                                        >
                                            {{
                                                products.filter(
                                                    (p) =>
                                                        p.category?.id ===
                                                        cat.id,
                                                ).length
                                            }}
                                        </span>
                                        <button
                                            v-if="
                                                products.filter(
                                                    (p) =>
                                                        p.category?.id ===
                                                        cat.id,
                                                ).length === 0
                                            "
                                            @click.stop="
                                                router.delete(
                                                    `/product-categories/${cat.id}`,
                                                )
                                            "
                                            class="shrink-0 cursor-pointer rounded-md p-1 text-rose-500 opacity-0 transition-opacity group-hover/cat:opacity-100 hover:bg-rose-500/15"
                                            title="Xóa nhóm này"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="rounded-xl border border-dashed border-border/80 bg-muted/5 py-8 text-center text-muted-foreground/60"
                        >
                            <AlertCircle
                                class="mx-auto mb-1.5 size-6 text-muted-foreground/30"
                            />
                            Chưa có nhóm món nào.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right: Products List -->
            <div class="lg:col-span-3">
                <Card
                    class="flex h-full flex-col justify-between overflow-hidden rounded-2xl border-border bg-card shadow-sm"
                >
                    <div>
                        <CardHeader class="border-b border-border/60 pb-3">
                            <div
                                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
                            >
                                <div>
                                    <CardTitle
                                        class="flex items-center gap-2 text-base font-bold"
                                    >
                                        Danh sách món ăn
                                        <Badge
                                            variant="secondary"
                                            class="shrink-0 border-0 bg-muted px-2 py-0 text-[10px] font-bold text-foreground/80"
                                        >
                                            {{ filteredProducts.length }}/{{
                                                products.length
                                            }}
                                        </Badge>
                                    </CardTitle>
                                    <CardDescription
                                        >Quét mã QR và hóa đơn sẽ đồng bộ với
                                        thực đơn này.</CardDescription
                                    >
                                </div>
                                <!-- Search -->
                                <div class="relative w-full shrink-0 sm:w-56">
                                    <Search
                                        class="absolute top-2.5 left-3 size-3.5 text-muted-foreground"
                                    />
                                    <Input
                                        v-model="searchQuery"
                                        placeholder="Tìm món ăn..."
                                        class="h-9 rounded-xl pl-9 text-xs"
                                    />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div
                                v-if="filteredProducts.length"
                                class="divide-y divide-border/60"
                            >
                                <div
                                    v-for="p in paginatedProducts"
                                    :key="p.id"
                                    class="group flex items-center justify-between gap-4 p-4 transition-colors hover:bg-muted/10"
                                >
                                    <div class="flex min-w-0 items-start gap-4">
                                        <!-- Thumbnail image / SVG category themed placeholder -->
                                        <div
                                            class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border text-xs font-bold select-none"
                                        >
                                            <img
                                                v-if="p.image_url"
                                                :src="p.image_url"
                                                class="h-full w-full object-cover"
                                            />
                                            <div
                                                v-else
                                                :class="[
                                                    'flex h-full w-full flex-col items-center justify-center',
                                                    getCategoryTheme(
                                                        p.category?.name ??
                                                            null,
                                                    ).bg,
                                                ]"
                                            >
                                                <component
                                                    :is="
                                                        getCategoryTheme(
                                                            p.category?.name ??
                                                                null,
                                                        ).icon
                                                    "
                                                    class="size-5"
                                                />
                                            </div>
                                        </div>

                                        <div class="min-w-0 space-y-1">
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <h4
                                                    class="max-w-[200px] truncate text-sm leading-snug font-extrabold text-foreground md:max-w-xs"
                                                >
                                                    {{ p.name }}
                                                </h4>
                                                <Badge
                                                    variant="outline"
                                                    class="shrink-0 border-rose-500/20 bg-rose-500/5 px-2 py-0 font-mono text-[9px] font-bold text-rose-500"
                                                >
                                                    {{
                                                        p.category?.name ??
                                                        'Chưa gán'
                                                    }}
                                                </Badge>
                                                <!-- Badge chi nhánh — chỉ hiện khi scope=all -->
                                                <Badge
                                                    v-if="isAllScope"
                                                    variant="outline"
                                                    :class="[
                                                        'shrink-0 px-2 py-0 text-[9px] font-bold',
                                                        p.branch_id === null ||
                                                        p.branch_id ===
                                                            undefined
                                                            ? 'border-blue-500/20 bg-blue-500/5 text-blue-600 dark:text-blue-400'
                                                            : 'border-slate-300/40 bg-muted/50 text-muted-foreground',
                                                    ]"
                                                >
                                                    {{
                                                        p.branch_id === null ||
                                                        p.branch_id ===
                                                            undefined
                                                            ? '🌐 Toàn chuỗi'
                                                            : (p.branch_name ??
                                                              'Chi nhánh')
                                                    }}
                                                </Badge>
                                            </div>
                                            <p
                                                class="truncate text-xs leading-normal text-muted-foreground"
                                            >
                                                <span
                                                    class="mr-1.5 rounded bg-muted px-1 font-mono text-[10px] font-bold text-muted-foreground/80"
                                                    >{{ p.code }}</span
                                                >
                                                {{
                                                    p.description ??
                                                    'Không có mô tả chi tiết hương vị.'
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="flex shrink-0 items-center gap-4"
                                    >
                                        <div class="text-right">
                                            <p
                                                class="font-mono text-sm font-black text-rose-500 dark:text-rose-400"
                                            >
                                                {{ formatCurrency(p.price) }}
                                            </p>
                                            <span
                                                class="mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-mono text-[9px] font-bold uppercase"
                                                :class="
                                                    p.is_available
                                                        ? 'bg-emerald-500/10 text-emerald-500'
                                                        : 'bg-muted text-muted-foreground'
                                                "
                                            >
                                                <span
                                                    :class="[
                                                        'h-1.5 w-1.5 rounded-full',
                                                        p.is_available
                                                            ? 'animate-pulse bg-emerald-500'
                                                            : 'bg-muted-foreground',
                                                    ]"
                                                ></span>
                                                {{
                                                    p.is_available
                                                        ? 'Đang bán'
                                                        : 'Tạm ngưng'
                                                }}
                                            </span>
                                        </div>

                                        <!-- Action buttons -->
                                        <div class="flex items-center gap-1">
                                            <button
                                                @click="toggleAvailability(p)"
                                                class="shrink-0 cursor-pointer rounded-lg border border-border bg-card p-1.5 text-muted-foreground transition-all hover:bg-muted"
                                                :class="
                                                    p.is_available
                                                        ? 'text-emerald-500 hover:text-emerald-600'
                                                        : 'text-amber-500 hover:text-amber-600'
                                                "
                                                :title="
                                                    p.is_available
                                                        ? 'Tạm ngưng bán món này'
                                                        : 'Mở bán món này trở lại'
                                                "
                                            >
                                                <ToggleRight
                                                    v-if="p.is_available"
                                                    class="size-4.5"
                                                />
                                                <ToggleLeft
                                                    v-else
                                                    class="size-4.5"
                                                />
                                            </button>

                                            <button
                                                @click="openEditModal(p)"
                                                class="shrink-0 cursor-pointer rounded-lg border border-border bg-card p-1.5 text-muted-foreground transition-all hover:bg-muted hover:text-indigo-500"
                                                title="Chỉnh sửa thông tin món ăn"
                                            >
                                                <Pencil class="size-3.5" />
                                            </button>

                                            <button
                                                v-if="canManagePrices"
                                                @click="confirmDelete(p)"
                                                class="shrink-0 cursor-pointer rounded-lg border border-border bg-card p-1.5 text-muted-foreground transition-all hover:bg-rose-500/10 hover:text-rose-500"
                                                title="Xóa món ăn khỏi thực đơn"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty states -->
                            <div
                                v-else-if="products.length"
                                class="flex flex-col items-center justify-center p-10 text-center"
                            >
                                <Search
                                    class="mb-3 size-10 text-muted-foreground/30"
                                />
                                <p
                                    class="text-sm font-semibold text-foreground/80"
                                >
                                    Không tìm thấy món ăn nào
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Thử thay đổi từ khóa hoặc chọn nhóm món
                                    khác.
                                </p>
                                <Button
                                    class="mt-3 cursor-pointer rounded-xl"
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        searchQuery = '';
                                        selectedCategory = '';
                                    "
                                    >Xóa bộ lọc</Button
                                >
                            </div>

                            <div
                                v-else
                                class="flex flex-col items-center justify-center p-14 text-center"
                            >
                                <div
                                    class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border border-dashed border-border bg-muted text-muted-foreground/30"
                                >
                                    <UtensilsCrossed class="size-8" />
                                </div>
                                <p
                                    class="text-base font-extrabold text-foreground"
                                >
                                    Thực đơn rỗng
                                </p>
                                <p
                                    class="mt-1.5 max-w-xs text-xs text-muted-foreground"
                                >
                                    Thêm món ăn đầu tiên để khách hàng có thể
                                    đặt hàng qua QR hoặc thu ngân tạo hóa đơn.
                                </p>
                                <Button
                                    v-if="canManagePrices"
                                    class="bg-rose-650 mt-4 cursor-pointer rounded-xl font-bold text-white shadow-xs hover:bg-rose-700"
                                    size="sm"
                                    @click="showAddProduct = true"
                                >
                                    <Plus class="mr-1.5 size-4" />Thêm món ăn
                                    đầu tiên
                                </Button>
                            </div>
                        </CardContent>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="totalPages > 1"
                        class="flex items-center justify-between border-t border-border/80 bg-muted/20 p-4"
                    >
                        <div
                            class="text-[10px] font-semibold text-muted-foreground"
                        >
                            Hiển thị
                            {{ (currentPage - 1) * itemsPerPage + 1 }} -
                            {{
                                Math.min(
                                    currentPage * itemsPerPage,
                                    filteredProducts.length,
                                )
                            }}
                            trong tổng số {{ filteredProducts.length }} món ăn
                        </div>
                        <div class="flex items-center gap-1.5">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="currentPage === 1"
                                @click="currentPage--"
                                class="h-8 cursor-pointer rounded-xl text-xs"
                            >
                                Trước
                            </Button>
                            <Button
                                v-for="page in visiblePages"
                                :key="page"
                                variant="outline"
                                size="sm"
                                @click="currentPage = page"
                                :class="[
                                    'h-8 w-8 cursor-pointer rounded-xl p-0 text-xs font-bold',
                                    currentPage === page
                                        ? 'bg-rose-650 border-0 text-white shadow-xs hover:bg-rose-700'
                                        : '',
                                ]"
                            >
                                {{ page }}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="currentPage === totalPages"
                                @click="currentPage++"
                                class="h-8 cursor-pointer rounded-xl text-xs"
                            >
                                Sau
                            </Button>
                        </div>
                    </div>
                </Card>
            </div>
        </div>
    </div>

    <!-- Add Category Inline/Modal Card -->
    <Teleport to="body">
        <div
            v-if="showAddCategory"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/55 p-4 backdrop-blur-xs"
        >
            <div class="flex min-h-full items-center justify-center">
                <Card
                    class="w-full max-w-md animate-in rounded-2xl border-border duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader class="border-b border-border/60 pb-3">
                        <CardTitle
                            class="flex items-center gap-2 text-base font-extrabold"
                        >
                            <FolderPlus class="size-5 text-indigo-500" />
                            Thêm Nhóm Món Ăn Mới
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-5">
                        <form
                            @submit.prevent="submitCategory"
                            class="space-y-4"
                        >
                            <div class="grid gap-1.5">
                                <Label
                                    for="cat-name"
                                    class="text-xs font-bold text-foreground"
                                    >Tên nhóm món
                                    <span class="font-bold text-rose-500"
                                        >*</span
                                    ></Label
                                >
                                <Input
                                    id="cat-name"
                                    v-model="categoryForm.name"
                                    placeholder="Ví dụ: Đồ ăn kèm, Khai vị..."
                                    required
                                    class="rounded-xl"
                                />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-1.5">
                                    <Label
                                        class="text-xs font-bold text-foreground"
                                        >Phạm vi</Label
                                    >
                                    <select
                                        v-model="categoryForm.scope"
                                        class="h-10 w-full rounded-xl border border-border bg-card px-3 text-xs font-bold text-foreground"
                                    >
                                        <option value="branch">
                                            Theo chi nhánh
                                        </option>
                                        <option
                                            v-if="canCreateShared"
                                            value="shared"
                                        >
                                            Dùng chung toàn chuỗi
                                        </option>
                                    </select>
                                </div>
                                <div
                                    v-if="categoryForm.scope === 'branch'"
                                    class="grid gap-1.5"
                                >
                                    <Label
                                        class="text-xs font-bold text-foreground"
                                        >Chi nhánh
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <select
                                        v-model="categoryForm.branch_id"
                                        required
                                        class="h-10 w-full rounded-xl border border-border bg-card px-3 text-xs font-bold text-foreground"
                                    >
                                        <option
                                            v-for="branch in branches"
                                            :key="branch.id"
                                            :value="branch.id"
                                        >
                                            {{ branch.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    for="cat-desc"
                                    class="text-xs font-bold text-foreground"
                                    >Mô tả nhóm món</Label
                                >
                                <textarea
                                    id="cat-desc"
                                    v-model="categoryForm.description"
                                    rows="2"
                                    placeholder="Ghi chú mô tả danh mục..."
                                    class="w-full rounded-xl border border-border bg-card px-3 py-2 text-xs text-foreground focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
                                />
                            </div>
                            <div
                                class="flex justify-end gap-2 border-t border-border/60 pt-2"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="showAddCategory = false"
                                    class="rounded-xl"
                                    >Hủy</Button
                                >
                                <Button
                                    type="submit"
                                    class="cursor-pointer rounded-xl bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                                    :disabled="categoryForm.processing"
                                >
                                    {{
                                        categoryForm.processing
                                            ? 'Đang tạo...'
                                            : 'Tạo nhóm món'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </Teleport>

    <!-- Add Product Modal -->

    <div
        v-if="showAddProduct"
        class="fixed inset-0 z-50 overflow-y-auto bg-black/55 p-4 backdrop-blur-xs"
    >
        <div class="flex min-h-full items-center justify-center">
            <Card
                class="w-full max-w-md animate-in rounded-2xl border-border duration-150 zoom-in-95 fade-in"
            >
                <CardHeader class="border-b border-border/60 pb-3">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-extrabold"
                    >
                        <Plus class="size-5 text-rose-500" />
                        Thêm Món Ăn Mới Vào Thực Đơn
                    </CardTitle>
                    <CardDescription
                        >Nhập thông tin chi tiết về sản phẩm để phục vụ bán
                        hàng.</CardDescription
                    >
                </CardHeader>
                <CardContent class="p-5">
                    <form @submit.prevent="submitProduct" class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label class="text-xs font-bold text-foreground"
                                    >Phạm vi</Label
                                >
                                <select
                                    v-model="productForm.scope"
                                    class="h-10 w-full rounded-xl border border-border bg-card px-3 text-xs font-bold text-foreground"
                                >
                                    <option value="branch">
                                        Theo chi nhánh
                                    </option>
                                    <option
                                        v-if="canCreateShared"
                                        value="shared"
                                    >
                                        Dùng chung toàn chuỗi
                                    </option>
                                </select>
                            </div>
                            <div
                                v-if="productForm.scope === 'branch'"
                                class="grid gap-1.5"
                            >
                                <Label class="text-xs font-bold text-foreground"
                                    >Chi nhánh
                                    <span class="text-rose-500">*</span></Label
                                >
                                <select
                                    v-model="productForm.branch_id"
                                    required
                                    class="h-10 w-full rounded-xl border border-border bg-card px-3 text-xs font-bold text-foreground"
                                >
                                    <option
                                        v-for="branch in branches"
                                        :key="branch.id"
                                        :value="branch.id"
                                    >
                                        {{ branch.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                for="prod-cat"
                                class="text-xs font-bold text-foreground"
                                >Thuộc nhóm món
                                <span class="font-bold text-rose-500"
                                    >*</span
                                ></Label
                            >
                            <div class="relative">
                                <select
                                    id="prod-cat"
                                    v-model="productForm.category_id"
                                    required
                                    class="h-10 w-full cursor-pointer appearance-none rounded-xl border border-border bg-card pr-8 pl-3 text-xs font-bold text-foreground focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
                                >
                                    <option value="" disabled>
                                        Chọn một nhóm món
                                    </option>
                                    <option
                                        v-for="cat in categories"
                                        :key="cat.id"
                                        :value="cat.id"
                                    >
                                        {{ cat.name }}
                                    </option>
                                </select>
                                <ChevronDown
                                    class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs font-bold text-foreground"
                                >Phân loại Món & Công thức
                                <span class="font-bold text-rose-500"
                                    >*</span
                                ></Label
                            >
                            <div class="grid grid-cols-2 gap-3">
                                <div
                                    @click="productForm.is_processed = false"
                                    :class="[
                                        'cursor-pointer rounded-xl border p-3 transition-all',
                                        !productForm.is_processed
                                            ? 'border-blue-500 bg-blue-50/70 text-blue-900 ring-2 ring-blue-500/20 dark:bg-blue-950/40 dark:text-blue-200'
                                            : 'border-border bg-card hover:bg-muted/50',
                                    ]"
                                >
                                    <div
                                        class="flex items-center gap-1.5 text-xs font-bold"
                                    >
                                        <span>🥤 Bán sẵn (Không chế biến)</span>
                                    </div>
                                    <p
                                        class="mt-1 text-[10px] text-muted-foreground"
                                    >
                                        Đưa trực tiếp vào thực đơn bán ngay.
                                        Không bắt buộc cài công thức.
                                    </p>
                                </div>

                                <div
                                    @click="productForm.is_processed = true"
                                    :class="[
                                        'cursor-pointer rounded-xl border p-3 transition-all',
                                        productForm.is_processed
                                            ? 'border-amber-500 bg-amber-50/70 text-amber-900 ring-2 ring-amber-500/20 dark:bg-amber-950/40 dark:text-amber-200'
                                            : 'border-border bg-card hover:bg-muted/50',
                                    ]"
                                >
                                    <div
                                        class="flex items-center gap-1.5 text-xs font-bold"
                                    >
                                        <span
                                            >🍲 Cần chế biến & Định lượng</span
                                        >
                                    </div>
                                    <p
                                        class="mt-1 text-[10px] text-muted-foreground"
                                    >
                                        Yêu cầu tạo công thức nguyên liệu thô để
                                        tự động trừ kho & tính COGS.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="prod-name"
                                class="text-xs font-bold text-foreground"
                                >Tên món ăn
                                <span class="font-bold text-rose-500"
                                    >*</span
                                ></Label
                            >
                            <Input
                                id="prod-name"
                                v-model="productForm.name"
                                placeholder="Ví dụ: Phở bò tái lăn..."
                                required
                                class="rounded-xl"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                for="prod-price"
                                class="text-xs font-bold text-foreground"
                                >Giá bán (VND)
                                <span class="font-bold text-rose-500"
                                    >*</span
                                ></Label
                            >
                            <Input
                                id="prod-price"
                                type="number"
                                v-model="productForm.price"
                                placeholder="Ví dụ: 45000"
                                required
                                class="rounded-xl"
                            />
                        </div>
                        <div
                            class="grid grid-cols-2 gap-3 rounded-xl border border-amber-200/60 bg-amber-50/50 p-3 dark:border-amber-900/40 dark:bg-amber-950/20"
                        >
                            <div class="grid gap-1">
                                <Label
                                    class="text-[11px] font-bold text-amber-900 dark:text-amber-300"
                                >
                                    🎁 Tích điểm khi mua
                                </Label>
                                <Input
                                    type="number"
                                    min="0"
                                    v-model="productForm.earn_points"
                                    placeholder="Ví dụ: 30"
                                    class="h-8 rounded-lg bg-white text-xs dark:bg-slate-900"
                                />
                                <p
                                    class="text-[9px] text-amber-700/80 dark:text-amber-400"
                                >
                                    Điểm thưởng khi mua 1 món
                                </p>
                            </div>
                            <div class="grid gap-1">
                                <Label
                                    class="text-[11px] font-bold text-amber-900 dark:text-amber-300"
                                >
                                    🔄 Điểm để đổi món
                                </Label>
                                <Input
                                    type="number"
                                    min="0"
                                    v-model="productForm.redeem_points"
                                    placeholder="Ví dụ: 300"
                                    class="h-8 rounded-lg bg-white text-xs dark:bg-slate-900"
                                />
                                <p
                                    class="text-[9px] text-amber-700/80 dark:text-amber-400"
                                >
                                    Điểm cần có để đổi 1 món
                                </p>
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                for="prod-image"
                                class="text-xs font-bold text-foreground"
                                >Ảnh món ăn (Menu Image)</Label
                            >
                            <Input
                                id="prod-image"
                                type="file"
                                accept="image/*"
                                @change="
                                    (e: any) =>
                                        (productForm.image = e.target.files[0])
                                "
                                class="rounded-xl text-xs"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <div class="flex items-center justify-between">
                                <Label
                                    for="prod-desc"
                                    class="text-xs font-bold text-foreground"
                                >
                                    Đặc điểm & Hương vị món ăn
                                    <span class="font-bold text-rose-500"
                                        >*</span
                                    >
                                </Label>
                            </div>
                            <textarea
                                id="prod-desc"
                                v-model="productForm.description"
                                rows="3"
                                required
                                placeholder="Mô tả hương vị (chua cay, béo ngậy, ngọt dịu...) để nhân viên dễ tư vấn khách."
                                class="w-full rounded-xl border border-border bg-card px-3 py-2 text-xs text-foreground focus:ring-2 focus:ring-rose-500/30 focus:outline-none"
                            />
                        </div>
                        <div
                            class="flex justify-end gap-2 border-t border-border/60 pt-2"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                @click="showAddProduct = false"
                                class="rounded-xl"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                class="bg-rose-650 cursor-pointer rounded-xl font-bold text-white hover:bg-rose-700"
                                :disabled="productForm.processing"
                            >
                                {{
                                    productForm.processing
                                        ? 'Đang thêm...'
                                        : 'Thêm món ăn'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Edit Product Modal -->

    <div
        v-if="editingProduct"
        class="fixed inset-0 z-50 overflow-y-auto bg-black/55 p-4 backdrop-blur-xs"
    >
        <div class="flex min-h-full items-center justify-center">
            <Card
                class="w-full max-w-md animate-in rounded-2xl border-border duration-150 zoom-in-95 fade-in"
            >
                <CardHeader class="border-b border-border/60 pb-3">
                    <div class="flex items-center justify-between">
                        <CardTitle
                            class="flex items-center gap-2 text-base font-extrabold"
                        >
                            <Pencil class="size-4 text-indigo-600" />Chỉnh Sửa
                            Món Ăn
                        </CardTitle>
                        <button
                            @click="editingProduct = null"
                            class="cursor-pointer text-muted-foreground hover:text-foreground"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </CardHeader>
                <CardContent class="p-5">
                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label class="text-xs font-bold text-foreground"
                                >Phân loại Món & Công thức
                                <span class="font-bold text-rose-500"
                                    >*</span
                                ></Label
                            >
                            <div class="grid grid-cols-2 gap-3">
                                <div
                                    @click="
                                        canManagePrices &&
                                        (editForm.is_processed = false)
                                    "
                                    :class="[
                                        'cursor-pointer rounded-xl border p-3 transition-all',
                                        !editForm.is_processed
                                            ? 'border-blue-500 bg-blue-50/70 text-blue-900 ring-2 ring-blue-500/20 dark:bg-blue-950/40 dark:text-blue-200'
                                            : 'border-border bg-card hover:bg-muted/50',
                                    ]"
                                >
                                    <div
                                        class="flex items-center gap-1.5 text-xs font-bold"
                                    >
                                        <span>🥤 Bán sẵn (Không chế biến)</span>
                                    </div>
                                    <p
                                        class="mt-1 text-[10px] text-muted-foreground"
                                    >
                                        Đưa trực tiếp vào thực đơn bán ngay.
                                        Không bắt buộc cài công thức.
                                    </p>
                                </div>

                                <div
                                    @click="
                                        canManagePrices &&
                                        (editForm.is_processed = true)
                                    "
                                    :class="[
                                        'cursor-pointer rounded-xl border p-3 transition-all',
                                        editForm.is_processed
                                            ? 'border-amber-500 bg-amber-50/70 text-amber-900 ring-2 ring-amber-500/20 dark:bg-amber-950/40 dark:text-amber-200'
                                            : 'border-border bg-card hover:bg-muted/50',
                                    ]"
                                >
                                    <div
                                        class="flex items-center gap-1.5 text-xs font-bold"
                                    >
                                        <span
                                            >🍲 Cần chế biến & Định lượng</span
                                        >
                                    </div>
                                    <p
                                        class="mt-1 text-[10px] text-muted-foreground"
                                    >
                                        Yêu cầu tạo công thức nguyên liệu thô để
                                        tự động trừ kho & tính COGS.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label class="text-xs font-bold text-foreground"
                                >Nhóm món</Label
                            >
                            <div class="relative">
                                <select
                                    v-model="editForm.category_id"
                                    class="h-10 w-full cursor-pointer appearance-none rounded-xl border border-border bg-card pr-8 pl-3 text-xs font-bold text-foreground focus:ring-2 focus:ring-amber-500/20 focus:outline-none"
                                >
                                    <option value="">Chưa gán nhóm</option>
                                    <option
                                        v-for="cat in categories"
                                        :key="cat.id"
                                        :value="cat.id"
                                    >
                                        {{ cat.name }}
                                    </option>
                                </select>
                                <ChevronDown
                                    class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs font-bold text-foreground"
                                >Tên món ăn
                                <span class="font-bold text-rose-500"
                                    >*</span
                                ></Label
                            >
                            <Input
                                v-model="editForm.name"
                                required
                                class="rounded-xl"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs font-bold text-foreground"
                                >Giá bán (VND)
                                <span class="font-bold text-rose-500"
                                    >*</span
                                ></Label
                            >
                            <Input
                                type="number"
                                v-model="editForm.price"
                                required
                                :disabled="!canManagePrices"
                                class="rounded-xl"
                            />
                            <p
                                v-if="!canManagePrices"
                                class="text-[10px] text-amber-700 dark:text-amber-300"
                            >
                                Giá bán và dữ liệu ảnh hưởng giá vốn chỉ Chủ
                                doanh nghiệp được thay đổi.
                            </p>
                        </div>
                        <div
                            class="grid grid-cols-2 gap-3 rounded-xl border border-amber-200/60 bg-amber-50/50 p-3 dark:border-amber-900/40 dark:bg-amber-950/20"
                        >
                            <div class="grid gap-1">
                                <Label
                                    class="text-[11px] font-bold text-amber-900 dark:text-amber-300"
                                >
                                    🎁 Tích điểm khi mua
                                </Label>
                                <Input
                                    type="number"
                                    min="0"
                                    v-model="editForm.earn_points"
                                    placeholder="Ví dụ: 30"
                                    class="h-8 rounded-lg bg-white text-xs dark:bg-slate-900"
                                />
                                <p
                                    class="text-[9px] text-amber-700/80 dark:text-amber-400"
                                >
                                    Điểm thưởng khi mua 1 món
                                </p>
                            </div>
                            <div class="grid gap-1">
                                <Label
                                    class="text-[11px] font-bold text-amber-900 dark:text-amber-300"
                                >
                                    🔄 Điểm để đổi món
                                </Label>
                                <Input
                                    type="number"
                                    min="0"
                                    v-model="editForm.redeem_points"
                                    placeholder="Ví dụ: 300"
                                    class="h-8 rounded-lg bg-white text-xs dark:bg-slate-900"
                                />
                                <p
                                    class="text-[9px] text-amber-700/80 dark:text-amber-400"
                                >
                                    Điểm cần có để đổi 1 món
                                </p>
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                for="edit-image"
                                class="text-xs font-bold text-foreground"
                                >Thay đổi ảnh món ăn</Label
                            >
                            <div class="flex items-center gap-3">
                                <div
                                    v-if="editingProduct?.image_url"
                                    class="size-12 shrink-0 overflow-hidden rounded-xl border border-border bg-muted shadow-xs"
                                >
                                    <img
                                        :src="editingProduct.image_url"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                                <Input
                                    id="edit-image"
                                    type="file"
                                    accept="image/*"
                                    @change="
                                        (e: any) =>
                                            (editForm.image = e.target.files[0])
                                    "
                                    class="flex-1 rounded-xl text-xs"
                                />
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <div class="flex items-center justify-between">
                                <Label
                                    class="text-xs font-bold text-foreground"
                                >
                                    Đặc điểm & Hương vị món ăn
                                    <span class="font-bold text-rose-500"
                                        >*</span
                                    >
                                </Label>
                            </div>
                            <textarea
                                v-model="editForm.description"
                                rows="3"
                                required
                                placeholder="Mô tả hương vị (chua cay, béo ngậy, ngọt dịu...) để nhân viên dễ tư vấn khách."
                                class="w-full rounded-xl border border-border bg-card px-3 py-2 text-xs text-foreground focus:ring-2 focus:ring-rose-500/30 focus:outline-none"
                            />
                        </div>
                        <div
                            class="flex justify-end gap-2 border-t border-border/60 pt-2"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                @click="editingProduct = null"
                                class="rounded-xl"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                class="bg-indigo-650 cursor-pointer rounded-xl font-bold text-white hover:bg-indigo-700"
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
    </div>

    <!-- Delete Confirmation Modal -->

    <div
        v-if="deletingProduct"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4 backdrop-blur-xs"
    >
        <Card
            class="w-full max-w-sm animate-in rounded-2xl border-border shadow-lg duration-150 zoom-in-95 fade-in"
        >
            <CardContent class="space-y-4 pt-6 text-center">
                <div
                    class="mx-auto flex h-14 w-14 animate-pulse items-center justify-center rounded-full bg-rose-500/10 text-rose-600"
                >
                    <Trash2 class="size-6" />
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-foreground">
                        Xóa món ăn này?
                    </h3>
                    <p class="mt-1.5 text-xs text-muted-foreground">
                        "{{ deletingProduct.name }}" sẽ bị xóa vĩnh viễn khỏi
                        danh sách thực đơn.
                    </p>
                </div>
                <div
                    class="flex justify-center gap-3 border-t border-border/60 pt-2"
                >
                    <Button
                        variant="outline"
                        @click="deletingProduct = null"
                        class="rounded-xl"
                        >Hủy</Button
                    >
                    <Button
                        class="cursor-pointer rounded-xl bg-rose-600 font-bold text-white hover:bg-rose-700"
                        @click="submitDelete"
                        >Xóa vĩnh viễn</Button
                    >
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Quick Combo Creator Modal -->
    <Teleport to="body">
        <div
            v-if="showComboModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/55 p-4 backdrop-blur-xs"
        >
            <div class="flex min-h-full items-center justify-center">
                <Card
                    class="w-full max-w-md animate-in rounded-2xl border-border duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader class="border-b border-border/60 pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle
                                class="flex items-center gap-2 text-base font-extrabold"
                            >
                                <Sparkles class="size-5 text-indigo-500" />Thiết
                                lập Combo đề xuất
                            </CardTitle>
                            <button
                                @click="showComboModal = false"
                                class="cursor-pointer text-muted-foreground hover:text-foreground"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <CardDescription>
                            Tạo nhanh sản phẩm Combo từ gợi ý phân tích giỏ hàng
                            của AI.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-5">
                        <form @submit.prevent="submitCombo" class="space-y-4">
                            <div class="grid gap-1.5">
                                <Label
                                    for="combo-name"
                                    class="text-xs font-bold text-foreground"
                                    >Tên Combo sản phẩm
                                    <span class="font-bold text-rose-500"
                                        >*</span
                                    ></Label
                                >
                                <Input
                                    id="combo-name"
                                    v-model="comboForm.name"
                                    required
                                    placeholder="Ví dụ: Combo ăn sáng siêu rẻ..."
                                    class="rounded-xl"
                                />
                            </div>

                            <div
                                class="grid grid-cols-2 gap-4 rounded-xl border border-border bg-muted/40 p-3 text-xs"
                            >
                                <div>
                                    <p
                                        class="font-semibold text-muted-foreground"
                                    >
                                        Món thứ nhất
                                    </p>
                                    <p
                                        class="mt-0.5 truncate font-extrabold text-foreground"
                                    >
                                        {{ comboForm.item_a_name }}
                                    </p>
                                    <p
                                        class="mt-0.5 font-mono font-bold text-rose-500"
                                    >
                                        {{ formatCurrency(comboForm.price_a) }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="font-semibold text-muted-foreground"
                                    >
                                        Món thứ hai (bán kèm)
                                    </p>
                                    <p
                                        class="mt-0.5 truncate font-extrabold text-foreground"
                                    >
                                        {{ comboForm.item_b_name }}
                                    </p>
                                    <p
                                        class="mt-0.5 font-mono font-bold text-rose-500"
                                    >
                                        {{ formatCurrency(comboForm.price_b) }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between">
                                    <Label
                                        for="combo-price"
                                        class="text-xs font-bold text-foreground"
                                        >Giá bán Combo (VND)
                                        <span class="font-bold text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <span
                                        class="text-[10px] font-semibold text-muted-foreground"
                                    >
                                        Tổng bán lẻ:
                                        {{
                                            formatCurrency(
                                                comboForm.price_a +
                                                    comboForm.price_b,
                                            )
                                        }}
                                    </span>
                                </div>
                                <Input
                                    id="combo-price"
                                    type="number"
                                    v-model="comboForm.combo_price"
                                    required
                                    class="rounded-xl"
                                />
                                <p class="text-[10px] text-muted-foreground">
                                    Đề xuất giảm ~12% so với tổng mua lẻ. Giá
                                    combo phải nhỏ hơn tổng bán lẻ.
                                </p>
                            </div>

                            <div class="grid gap-1.5">
                                <Label
                                    for="combo-notes"
                                    class="text-xs font-bold text-foreground"
                                    >Ghi chú / Mô tả combo</Label
                                >
                                <textarea
                                    id="combo-notes"
                                    v-model="comboForm.notes"
                                    rows="3"
                                    class="w-full rounded-xl border border-border bg-card px-3 py-2 text-xs text-foreground focus:ring-2 focus:ring-indigo-500/25 focus:outline-none"
                                    placeholder="Mô tả ưu đãi combo..."
                                />
                            </div>

                            <div
                                class="flex justify-end gap-2 border-t border-border/60 pt-2"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="showComboModal = false"
                                    class="rounded-xl"
                                    >Hủy</Button
                                >
                                <Button
                                    type="submit"
                                    class="cursor-pointer rounded-xl bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                                    :disabled="comboForm.processing"
                                >
                                    {{
                                        comboForm.processing
                                            ? 'Đang tạo...'
                                            : 'Tạo Combo'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </Teleport>
</template>
