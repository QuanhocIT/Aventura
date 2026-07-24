<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import {
    Package,
    Plus,
    Settings2,
    Scale,
    Info,
    Beaker,
    X,
    TrendingDown,
    ShoppingCart,
    AlertTriangle,
    Trash2,
    ArrowDownToLine,
    ChevronDown,
    ChevronUp,
    ChevronLeft,
    ChevronRight,
    Sparkles,
    Upload,
    Search,
} from 'lucide-vue-next';
import { computed, ref, onMounted, watch } from 'vue';
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

type Ingredient = {
    id: number;
    sku: string | null;
    name: string;
    category_name: string | null;
    average_cost: number;
    unit: { id: number; symbol: string } | null;
    stock: number | null;
    last_cost: number | null;
};
type RecipeItem = {
    id: number;
    ingredient_id: number;
    ingredient_name: string;
    quantity: number;
    unit_symbol: string;
    waste_rate: number;
};
type Product = {
    id: number;
    name: string;
    code: string;
    price: number;
    recipes: RecipeItem[];
};
type Unit = { id: number; name: string; symbol: string };
type Supplier = { id: number; name: string };
type Purchase = {
    id: number;
    ingredient_name: string;
    quantity: number;
    unit_cost: number;
    total_cost: number;
    supplier_name: string;
    occurred_at: string | null;
    notes: string | null;
};
type Employee = { id: number; full_name: string; job_title: string | null };
type WasteRecord = {
    id: number;
    is_approval: boolean;
    ingredient_name: string;
    quantity: number;
    unit_symbol: string;
    cost: number;
    notes: string | null;
    performed_by: string;
    employee_name: string;
    occurred_at: string;
    status: 'pending' | 'approved' | 'rejected';
    rejection_reason: string | null;
};

const props = defineProps<{
    ingredients: Ingredient[];
    products: Product[];
    units: Unit[];
    suppliers: Supplier[];
    recentPurchases: Purchase[];
    employees: Employee[];
    recentWastes: WasteRecord[];
}>();

// ── Tabs ──────────────────────────────────────────────────────────────────────
const activeTab = ref<'stock' | 'purchase' | 'waste'>('stock');

// ── Pagination (công thức định lượng) ──────────────────────────────────────────
const recipeCurrentPage = ref(1);
const recipePerPage = 5;

const paginatedProducts = computed(() => {
    const start = (recipeCurrentPage.value - 1) * recipePerPage;
    const end = start + recipePerPage;

    return props.products ? props.products.slice(start, end) : [];
});

const totalRecipePages = computed(() => {
    return props.products ? Math.ceil(props.products.length / recipePerPage) : 0;
});

const visibleRecipePages = computed(() => {
    const pages = [];
    const total = totalRecipePages.value;
    const current = recipeCurrentPage.value;
    
    if (total <= 5) {
        for (let i = 1; i <= total; i++) {
pages.push(i);
}
    } else {
        pages.push(1);

        if (current > 3) {
pages.push('...');
}
        
        const start = Math.max(2, current - 1);
        const end = Math.min(total - 1, current + 1);

        for (let i = start; i <= end; i++) {
pages.push(i);
}
        
        if (current < total - 2) {
pages.push('...');
}

        pages.push(total);
    }

    return pages;
});

// ── Modals (stock tab) ────────────────────────────────────────────────────────
const showAddRecipe = ref(false);
const showAddIngredient = ref(false);
const activeProduct = ref<Product | null>(null);

// ── Forms ─────────────────────────────────────────────────────────────────────
const ingredientForm = useForm({
    name: '',
    unit_id: props.units[0]?.id ? String(props.units[0].id) : '',
    category: '',
});

const recipeForm = useForm({
    product_id: '',
    items: [] as Array<{
        ingredient_id: string;
        quantity: string;
        waste_rate: string;
    }>,
});

const purchaseForm = useForm({
    ingredient_id: '',
    quantity: '',
    unit_cost: '',
    supplier_id: '',
    notes: '',
    occurred_at: new Date().toISOString().slice(0, 10),
    invoice_file: null as File | null,
});

// ── AI Forecast ──────────────────────────────────────────────────────────────
const aiForecasts = ref<any[]>([]);
const loadingForecast = ref(false);

const fetchAiForecast = async () => {
    loadingForecast.value = true;

    try {
        const response = await fetch('/api/inventory/ai-forecast');
        const data = await response.json();

        if (data.success) {
            aiForecasts.value = data.forecast;
        }
    } catch (error) {
        console.error('Lỗi khi tải AI Forecast:', error);
    } finally {
        loadingForecast.value = false;
    }
};

const applyForecast = (item: any) => {
    purchaseForm.ingredient_id = String(item.ingredient_id);
    purchaseForm.quantity = String(item.suggested_purchase);
    toast.success(
        `Đã áp dụng đề xuất AI cho ${item.ingredient_name}: ${item.suggested_purchase} ${item.unit_symbol}`,
    );
};

onMounted(() => {
    fetchAiForecast();
});

watch(activeTab, (newTab) => {
    if (newTab === 'purchase') {
        fetchAiForecast();
    }
});

const wasteForm = useForm({
    ingredient_id: '',
    quantity: '',
    employee_id: '',
    notes: '',
});

// ── Search & low-stock ────────────────────────────────────────────────────────
const ingredientSearch = ref('');

const filteredIngredients = computed(() => {
    const q = ingredientSearch.value.trim().toLowerCase();

    if (!q) {
        return props.ingredients;
    }

    return props.ingredients.filter(
        (i) =>
            i.name.toLowerCase().includes(q) ||
            (i.category_name ?? '').toLowerCase().includes(q),
    );
});

const lowStockIngredients = computed(() =>
    props.ingredients.filter((i) => i.stock !== null && i.stock < 5),
);

// ── Computed ──────────────────────────────────────────────────────────────────
const zeroCostIngredients = computed(() =>
    props.ingredients.filter(
        (i) =>
            (i.average_cost ?? 0) === 0 &&
            props.products.some((p) =>
                p.recipes.some((r) => r.ingredient_name === i.name),
            ),
    ),
);

const selectedPurchaseIngredient = computed(
    () =>
        props.ingredients.find(
            (i) => i.id === Number(purchaseForm.ingredient_id),
        ) ?? null,
);

const estimatedWasteCost = computed(() => {
    const ing = props.ingredients.find(
        (i) => i.id === Number(wasteForm.ingredient_id),
    );

    if (!ing || !wasteForm.quantity) {
        return 0;
    }

    return Number(wasteForm.quantity) * (ing.average_cost ?? 0);
});

// ── Helpers ───────────────────────────────────────────────────────────────────
const page = usePage();
const vnd = (v: number) => new Intl.NumberFormat('vi-VN').format(v) + 'đ';
const flashSuccess = () => (page.props.flash as any)?.success ?? null;

// ── Submit handlers ───────────────────────────────────────────────────────────
const submitIngredient = () => {
    ingredientForm.post('/inventory/ingredients', {
        onSuccess: () => {
            ingredientForm.reset();
            showAddIngredient.value = false;
        },
        onError: () => toast.error('Có lỗi khi thêm nguyên liệu.'),
    });
};

const activeProductRecipes = computed(() => {
    if (!activeProduct.value) {
return [];
}

    const updatedProduct = props.products.find(p => p.id === activeProduct.value!.id);

    return updatedProduct ? updatedProduct.recipes : [];
});

const deleteRecipe = (recipeId: number) => {
    if (confirm('Bạn có chắc chắn muốn xóa nguyên liệu này khỏi công thức?')) {
        router.delete(`/inventory/recipes/${recipeId}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã xóa nguyên liệu khỏi công thức.');
            },
            onError: () => {
                toast.error('Có lỗi xảy ra khi xóa.');
            }
        });
    }
};

const addRecipeRow = () => {
    recipeForm.items.push({
        ingredient_id: '',
        quantity: '',
        waste_rate: '0',
    });
};

const removeRecipeRow = (index: number) => {
    recipeForm.items.splice(index, 1);
};

const openAddRecipeModal = (prod: Product) => {
    activeProduct.value = prod;
    recipeForm.product_id = String(prod.id);
    
    if (prod.recipes && prod.recipes.length > 0) {
        recipeForm.items = prod.recipes.map(r => ({
            ingredient_id: String(r.ingredient_id),
            quantity: String(r.quantity),
            waste_rate: String(r.waste_rate ?? 0),
        }));
    } else {
        recipeForm.items = [{
            ingredient_id: '',
            quantity: '',
            waste_rate: '0',
        }];
    }

    showAddRecipe.value = true;
};

const submitRecipe = () => {
    recipeForm.post('/inventory/recipes', {
        preserveScroll: true,
        onSuccess: () => {
            showAddRecipe.value = false;
            toast.success('Đã lưu công thức định lượng món ăn thành công.');
        },
        onError: () => toast.error('Có lỗi khi lưu công thức.'),
    });
};

const submitPurchase = () => {
    purchaseForm.post('/inventory/purchases', {
        onSuccess: () => {
            toast.success(flashSuccess() ?? 'Đã ghi nhận nhập hàng!');
            purchaseForm.reset(
                'ingredient_id',
                'quantity',
                'unit_cost',
                'supplier_id',
                'notes',
            );
            purchaseForm.occurred_at = new Date().toISOString().slice(0, 10);
        },
        onError: () => toast.error('Có lỗi khi ghi nhận nhập hàng.'),
    });
};

const submitWaste = () => {
    wasteForm.post('/inventory/waste', {
        onSuccess: () => {
            toast.success(flashSuccess() ?? 'Đã ghi nhận hao hụt!');
            wasteForm.reset();
        },
        onError: () => toast.error('Có lỗi khi ghi nhận hao hụt.'),
    });
};
</script>

<template>
    <Head title="Kho & Định lượng nguyên liệu" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-500"
                >
                    <Package class="size-6" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        Kho Nguyên Liệu & Công Thức
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Quản lý nhập hàng, tồn kho và công thức định lượng
                    </p>
                </div>
            </div>
            <Button
                v-if="activeTab === 'stock'"
                @click="showAddIngredient = true"
                variant="outline"
                class="h-9 border-indigo-200 text-xs text-indigo-700 dark:border-indigo-800 dark:text-indigo-400"
            >
                <Plus class="mr-1.5 size-4" />Thêm nguyên liệu
            </Button>
        </div>

        <!-- Low-stock alert banner -->
        <div
            v-if="lowStockIngredients.length > 0"
            class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-900/20 dark:text-rose-300"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0 text-rose-500" />
            <div>
                <span class="font-bold"
                    >{{ lowStockIngredients.length }} nguyên liệu SẮP HẾT HÀNG
                    (&lt;5 đơn vị):</span
                >
                {{
                    lowStockIngredients
                        .map(
                            (i) =>
                                `${i.name} (còn ${i.stock} ${i.unit?.symbol ?? ''})`,
                        )
                        .join(' · ')
                }}. Hãy nhập hàng ngay để tránh gián đoạn sản xuất.
            </div>
        </div>

        <!-- Zero-cost warning -->
        <div
            v-if="zeroCostIngredients.length > 0"
            class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            <div>
                <span class="font-semibold"
                    >{{ zeroCostIngredients.length }} nguyên liệu chưa có giá
                    vốn:</span
                >
                {{ zeroCostIngredients.map((i) => i.name).join(', ') }}. COGS sẽ
                bằng 0 cho các món dùng nguyên liệu này. Hãy nhập hàng để cập
                nhật giá.
            </div>
        </div>

        <!-- Tabs -->
        <div
            class="flex items-center gap-1 self-start rounded-xl border border-border bg-muted p-1"
        >
            <button
                @click="activeTab = 'stock'"
                class="flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold transition-all"
                :class="
                    activeTab === 'stock'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                <Package class="size-3.5" />Tồn kho & Công thức
            </button>
            <button
                @click="activeTab = 'purchase'"
                class="flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold transition-all"
                :class="
                    activeTab === 'purchase'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                <ShoppingCart class="size-3.5" />Nhập hàng
            </button>
            <button
                @click="activeTab = 'waste'"
                class="flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold transition-all"
                :class="
                    activeTab === 'waste'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                <Trash2 class="size-3.5" />Hao hụt ngoài ý muốn
            </button>
        </div>

        <!-- ══ TAB: TỒN KHO & CÔNG THỨC ══════════════════════════════════════ -->
        <template v-if="activeTab === 'stock'">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Left: Ingredient list -->
                <div class="lg:col-span-1">
                    <Card class="h-full shadow-sm">
                        <CardHeader class="border-b border-border pb-3">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <div>
                                    <CardTitle class="text-sm font-bold"
                                        >Tồn Kho Nguyên Liệu ({{
                                            filteredIngredients.length
                                        }}/{{ ingredients.length }})</CardTitle
                                    >
                                    <CardDescription class="text-[11px]">
                                        <span
                                            class="font-semibold text-rose-500"
                                            >{{
                                                lowStockIngredients.length
                                            }}</span
                                        >
                                        sắp hết ·
                                        <span
                                            class="font-semibold text-amber-500"
                                            >{{
                                                ingredients.filter(
                                                    (i) =>
                                                        i.stock !== null &&
                                                        i.stock >= 5 &&
                                                        i.stock < 20,
                                                ).length
                                            }}</span
                                        >
                                        cần nhập thêm
                                    </CardDescription>
                                </div>
                            </div>
                            <!-- Search -->
                            <div class="relative mt-2">
                                <Search
                                    class="absolute top-2 left-2.5 size-3.5 text-slate-400"
                                />
                                <input
                                    v-model="ingredientSearch"
                                    type="text"
                                    placeholder="Tìm nguyên liệu..."
                                    class="w-full rounded-lg border border-slate-200 bg-white py-1.5 pr-3 pl-7 text-xs focus:ring-1 focus:ring-indigo-400 focus:outline-none dark:border-slate-700 dark:bg-slate-800"
                                />
                            </div>
                        </CardHeader>
                        <CardContent class="divide-y divide-border p-0">
                            <div v-if="filteredIngredients.length">
                                <div
                                    v-for="ing in filteredIngredients"
                                    :key="ing.id"
                                    class="flex items-center justify-between p-3 text-xs transition-colors hover:bg-muted/30"
                                >
                                    <div class="mr-2 min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <p class="truncate font-bold">
                                                {{ ing.name }}
                                            </p>
                                            <span
                                                v-if="
                                                    (ing.average_cost ?? 0) ===
                                                    0
                                                "
                                                class="shrink-0 rounded bg-amber-100 px-1 text-[9px] text-amber-700 dark:bg-amber-900/40 dark:text-amber-400"
                                            >
                                                0đ
                                            </span>
                                        </div>
                                        <p
                                            class="mt-0.5 text-[10px] text-muted-foreground"
                                        >
                                            {{ ing.sku ?? 'SKU-NONE' }} ·
                                            {{
                                                ing.category_name ??
                                                'Nguyên liệu'
                                            }}
                                        </p>
                                        <p
                                            v-if="ing.average_cost > 0"
                                            class="mt-0.5 text-[10px] font-semibold text-indigo-500"
                                        >
                                            TB: {{ vnd(ing.average_cost) }}/{{
                                                ing.unit?.symbol ?? 'đv'
                                            }}
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div
                                            class="flex items-center justify-end gap-1.5"
                                        >
                                            <TrendingDown
                                                v-if="
                                                    ing.stock !== null &&
                                                    ing.stock < 5
                                                "
                                                class="size-3 text-rose-500"
                                            />
                                            <span
                                                class="rounded-full px-2 py-0.5 font-mono text-[10px] font-bold"
                                                :class="
                                                    ing.stock === null
                                                        ? 'bg-muted text-muted-foreground'
                                                        : ing.stock < 5
                                                          ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-400'
                                                          : ing.stock < 20
                                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400'
                                                            : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'
                                                "
                                            >
                                                {{
                                                    ing.stock !== null
                                                        ? ing.stock.toFixed(1)
                                                        : '—'
                                                }}
                                                {{ ing.unit?.symbol ?? '' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-else
                                class="py-12 text-center text-xs text-muted-foreground"
                            >
                                Chưa có nguyên liệu. Nhấn "Thêm nguyên liệu" để
                                bắt đầu.
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right: Product recipes -->
                <div class="lg:col-span-2">
                    <Card class="shadow-sm">
                        <CardHeader class="border-b border-border pb-3">
                            <CardTitle
                                class="flex items-center gap-1.5 text-base"
                            >
                                <Scale class="size-5 text-indigo-500" />Công
                                Thức Định Lượng
                            </CardTitle>
                            <CardDescription
                                >Thiết lập nguyên liệu cho từng món để hệ thống
                                tự tính COGS.</CardDescription
                            >
                        </CardHeader>
                        <CardContent class="p-0">
                            <div
                                v-if="products.length"
                                class="divide-y divide-border"
                            >
                                <div
                                    v-for="p in paginatedProducts"
                                    :key="p.id"
                                    class="flex flex-col gap-3 p-5 transition-colors hover:bg-muted/20"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div>
                                            <h4 class="text-sm font-bold">
                                                {{ p.name }}
                                            </h4>
                                            <p
                                                class="text-[10px] text-muted-foreground"
                                            >
                                                {{ p.code }}
                                            </p>
                                        </div>
                                        <Button
                                            @click="openAddRecipeModal(p)"
                                            size="sm"
                                            variant="outline"
                                            class="btn-set-recipe h-8 border-indigo-200 text-xs text-indigo-600 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-400"
                                        >
                                            <Settings2
                                                class="mr-1 size-3.5"
                                            />Thiết lập định lượng
                                        </Button>
                                    </div>
                                    <div
                                        class="rounded-xl border border-border bg-muted/40 p-3"
                                    >
                                        <div
                                            v-if="p.recipes.length"
                                            class="flex flex-wrap gap-2"
                                        >
                                            <span
                                                v-for="r in p.recipes"
                                                :key="r.id"
                                                class="flex items-center gap-1 rounded-lg border bg-card px-2.5 py-1.5 text-[10px] font-medium"
                                            >
                                                <strong
                                                    >{{
                                                        r.ingredient_name
                                                    }}:</strong
                                                >
                                                <span
                                                    class="font-mono font-bold text-indigo-500"
                                                    >{{ r.quantity }}</span
                                                >
                                                <span
                                                    class="text-muted-foreground"
                                                    >{{ r.unit_symbol }}</span
                                                >
                                                <span
                                                    v-if="r.waste_rate > 0"
                                                    class="rounded border border-amber-200 bg-amber-50 px-1 text-[9px] text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                                                >
                                                    +{{ r.waste_rate }}% hao
                                                </span>
                                            </span>
                                        </div>
                                        <div
                                            v-else
                                            class="flex items-center gap-1 text-[11px] text-muted-foreground"
                                        >
                                            <AlertTriangle
                                                class="size-3.5 text-amber-400"
                                            />
                                            Chưa có công thức — COGS sẽ bằng 0
                                            cho món này!
                                        </div>
                                    </div>
                                </div>

                                <!-- Pagination controls for recipes list -->
                                <div v-if="totalRecipePages > 1" class="flex items-center justify-between border-t border-border p-4 bg-slate-50/50 dark:bg-slate-900/10">
                                    <span class="text-xs text-muted-foreground font-medium">
                                        Trang {{ recipeCurrentPage }} / {{ totalRecipePages }} (Tổng {{ products.length }} món)
                                    </span>
                                    <div class="flex items-center gap-1.5">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            :disabled="recipeCurrentPage === 1"
                                            @click="recipeCurrentPage--"
                                            class="h-7 w-7 p-0 flex items-center justify-center rounded-lg"
                                        >
                                            <ChevronLeft class="size-3.5" />
                                        </Button>
                                        
                                        <template v-for="(page, idx) in visibleRecipePages" :key="idx">
                                            <span v-if="page === '...'" class="px-1.5 text-xs text-muted-foreground font-bold select-none">...</span>
                                            <Button
                                                v-else
                                                size="sm"
                                                :variant="recipeCurrentPage === page ? 'default' : 'outline'"
                                                @click="recipeCurrentPage = Number(page)"
                                                class="h-7 min-w-[28px] px-1 text-xs font-bold rounded-lg"
                                            >
                                                {{ page }}
                                            </Button>
                                        </template>

                                        <Button
                                            size="sm"
                                            variant="outline"
                                            :disabled="recipeCurrentPage === totalRecipePages"
                                            @click="recipeCurrentPage++"
                                            class="h-7 w-7 p-0 flex items-center justify-center rounded-lg"
                                        >
                                            <ChevronRight class="size-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-else
                                class="py-12 text-center text-xs text-muted-foreground"
                            >
                                Chưa có món ăn nào.
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </template>

        <!-- ══ TAB: NHẬP HÀNG ═════════════════════════════════════════════════ -->
        <template v-else-if="activeTab === 'purchase'">
            <div
                class="grid animate-in gap-6 duration-200 fade-in lg:grid-cols-5"
            >
                <!-- Form -->
                <Card class="border-slate-200/80 shadow-sm lg:col-span-2">
                    <CardHeader class="border-b border-border pb-3">
                        <CardTitle
                            class="flex items-center gap-2 text-sm font-bold"
                        >
                            <ArrowDownToLine
                                class="size-4 text-indigo-500"
                            />Ghi nhận nhập hàng
                        </CardTitle>
                        <CardDescription class="text-[11px]"
                            >Cập nhật tồn kho và tính toán giá vốn bình
                            quân.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="pt-5">
                        <form
                            @submit.prevent="submitPurchase"
                            class="space-y-4"
                        >
                            <!-- Nguyên liệu -->
                            <div class="space-y-1.5">
                                <Label class="text-xs"
                                    >Nguyên liệu
                                    <span class="text-rose-500">*</span></Label
                                >
                                <select
                                    v-model="purchaseForm.ingredient_id"
                                    required
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                    :class="{
                                        'border-rose-400':
                                            purchaseForm.errors.ingredient_id,
                                    }"
                                >
                                    <option value="" disabled>
                                        Chọn nguyên liệu...
                                    </option>
                                    <option
                                        v-for="ing in ingredients"
                                        :key="ing.id"
                                        :value="ing.id"
                                    >
                                        {{ ing.name }} (tồn:
                                        {{ ing.stock?.toFixed(1) ?? '—' }}
                                        {{ ing.unit?.symbol ?? '' }})
                                    </option>
                                </select>
                                <p
                                    v-if="selectedPurchaseIngredient"
                                    class="text-[11px] text-indigo-500"
                                >
                                    Giá vốn TB hiện tại:
                                    <strong
                                        >{{
                                            vnd(
                                                selectedPurchaseIngredient.average_cost,
                                            )
                                        }}/{{
                                            selectedPurchaseIngredient.unit
                                                ?.symbol ?? 'đv'
                                        }}</strong
                                    >
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <!-- Số lượng -->
                                <div class="space-y-1.5">
                                    <Label class="text-xs"
                                        >Số lượng
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <div class="relative">
                                        <Input
                                            v-model="purchaseForm.quantity"
                                            type="number"
                                            step="0.001"
                                            min="0.001"
                                            placeholder="0"
                                            required
                                            class="pr-10"
                                            :class="{
                                                'border-rose-400':
                                                    purchaseForm.errors
                                                        .quantity,
                                            }"
                                        />
                                        <span
                                            class="absolute top-1/2 right-3 -translate-y-1/2 text-xs font-medium text-muted-foreground"
                                        >
                                            {{
                                                selectedPurchaseIngredient?.unit
                                                    ?.symbol ?? 'đv'
                                            }}
                                        </span>
                                    </div>
                                </div>
                                <!-- Đơn giá -->
                                <div class="space-y-1.5">
                                    <Label class="text-xs"
                                        >Đơn giá (đ)
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <Input
                                        v-model="purchaseForm.unit_cost"
                                        type="number"
                                        step="1"
                                        min="0"
                                        placeholder="0"
                                        required
                                        :class="{
                                            'border-rose-400':
                                                purchaseForm.errors.unit_cost,
                                        }"
                                    />
                                </div>
                            </div>

                            <!-- Thành tiền preview -->
                            <div
                                v-if="
                                    purchaseForm.quantity &&
                                    purchaseForm.unit_cost
                                "
                                class="flex items-center justify-between rounded-xl border border-indigo-500/20 bg-indigo-500/10 px-4 py-2.5 text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Thành tiền</span
                                >
                                <span
                                    class="font-bold text-indigo-600 dark:text-indigo-400"
                                >
                                    {{
                                        vnd(
                                            Number(purchaseForm.quantity) *
                                                Number(purchaseForm.unit_cost),
                                        )
                                    }}
                                </span>
                            </div>

                            <!-- Nhà cung cấp -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">Nhà cung cấp</Label>
                                <select
                                    v-model="purchaseForm.supplier_id"
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                >
                                    <option value="">Không chọn</option>
                                    <option
                                        v-for="s in suppliers"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        {{ s.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Ngày nhập -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">Ngày nhập</Label>
                                <Input
                                    v-model="purchaseForm.occurred_at"
                                    type="date"
                                />
                            </div>

                            <!-- Ghi chú -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">Ghi chú</Label>
                                <Input
                                    v-model="purchaseForm.notes"
                                    placeholder="Số hóa đơn, ghi chú..."
                                />
                            </div>

                            <!-- Tải ảnh hóa đơn cứng bắt buộc -->
                            <div class="space-y-1.5">
                                <Label
                                    class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    Hóa đơn cứng (Ảnh chụp chứng từ)
                                    <span class="font-bold text-rose-500"
                                        >*</span
                                    >
                                </Label>
                                <input
                                    type="file"
                                    @change="
                                        (e) =>
                                            (purchaseForm.invoice_file =
                                                (e.target as HTMLInputElement)
                                                    .files?.[0] || null)
                                    "
                                    accept="image/*,.pdf"
                                    required
                                    class="w-full rounded-xl border border-slate-200 bg-background p-2 text-xs text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                                <p class="text-[10px] text-muted-foreground">
                                    Chấp nhận JPG, PNG, PDF tối đa 2MB.
                                </p>
                            </div>

                            <div
                                class="flex items-start gap-2 text-[11px] text-muted-foreground"
                            >
                                <Info
                                    class="mt-0.5 size-3.5 shrink-0 text-indigo-400"
                                />
                                <span
                                    >Nhân viên kho nhập hàng sẽ được Chủ doanh
                                    nghiệp kiểm duyệt chéo và phê duyệt trước
                                    khi cộng kho.</span
                                >
                            </div>

                            <Button
                                type="submit"
                                class="w-full rounded-xl bg-indigo-600 text-white hover:bg-indigo-700"
                                :disabled="purchaseForm.processing"
                            >
                                <ArrowDownToLine class="mr-2 size-4" />
                                {{
                                    purchaseForm.processing
                                        ? 'Đang gửi phê duyệt...'
                                        : 'Gửi yêu cầu nhập hàng'
                                }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <!-- AI Forecast Recommendations -->
                <Card
                    class="animate-in border-indigo-200 bg-gradient-to-br from-indigo-50/20 via-background to-background shadow-sm duration-250 slide-in-from-right lg:col-span-3 dark:border-indigo-900/60"
                >
                    <CardHeader class="border-b border-border pb-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle
                                    class="flex items-center gap-1.5 text-sm font-bold text-indigo-700 dark:text-indigo-400"
                                >
                                    <Sparkles
                                        class="size-4 animate-pulse text-indigo-500"
                                    />
                                    🔮 Đề xuất nhập hàng AI (7 ngày tới)
                                </CardTitle>
                                <CardDescription class="text-[11px]"
                                    >AI phân tích xu hướng và đề xuất số lượng
                                    nhập tối ưu.</CardDescription
                                >
                            </div>
                            <span
                                class="animate-bounce rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300"
                            >
                                Độ tin cậy > 92%
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent class="divide-y divide-border p-0">
                        <div
                            v-if="loadingForecast"
                            class="p-16 text-center text-xs text-muted-foreground"
                        >
                            <span class="mr-1 inline-block animate-spin"
                                >🔄</span
                            >
                            Đang tính toán phân tích dữ liệu...
                        </div>
                        <div
                            v-else-if="aiForecasts.length === 0"
                            class="p-16 text-center text-xs text-muted-foreground"
                        >
                            Không tìm thấy dữ liệu đề xuất.
                        </div>
                        <div
                            v-else
                            class="max-h-[500px] divide-y divide-border overflow-y-auto"
                        >
                            <div
                                v-for="item in aiForecasts"
                                :key="item.ingredient_id"
                                class="flex items-start justify-between gap-4 p-4 text-xs transition-colors hover:bg-muted/10"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="font-bold text-slate-800 dark:text-slate-200"
                                            >{{ item.ingredient_name }}</span
                                        >
                                        <span
                                            class="rounded-md bg-emerald-100 px-1.5 py-0.5 text-[9px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                        >
                                            Độ tin cậy:
                                            {{ item.confidence_score }}%
                                        </span>
                                    </div>
                                    <p
                                        class="mt-1 text-[10px] leading-relaxed text-muted-foreground"
                                    >
                                        {{ item.reason }}
                                    </p>
                                    <div
                                        class="mt-2 flex items-center gap-4 text-[10px] text-slate-500"
                                    >
                                        <span
                                            >Tồn hiện tại:
                                            <strong
                                                class="font-mono text-slate-700 dark:text-slate-300"
                                                >{{ item.current_stock }}
                                                {{ item.unit_symbol }}</strong
                                            ></span
                                        >
                                        <span
                                            >Dự báo 7 ngày tới:
                                            <strong
                                                class="font-mono text-slate-700 dark:text-slate-300"
                                                >{{
                                                    item.predicted_usage_next_7_days
                                                }}
                                                {{ item.unit_symbol }}</strong
                                            ></span
                                        >
                                    </div>
                                </div>
                                <div
                                    class="flex shrink-0 flex-col items-end gap-1.5 text-right"
                                >
                                    <div
                                        class="font-bold text-indigo-600 dark:text-indigo-400"
                                    >
                                        Cần mua:
                                        <span class="font-mono text-sm">{{
                                            item.suggested_purchase
                                        }}</span>
                                        {{ item.unit_symbol }}
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        type="button"
                                        class="h-7 border-indigo-200 text-[10px] text-indigo-600 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-400"
                                        @click="applyForecast(item)"
                                    >
                                        Áp dụng đề xuất
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent purchases -->
                <Card class="shadow-sm lg:col-span-5">
                    <CardHeader class="border-b border-border pb-3">
                        <CardTitle class="text-sm font-bold"
                            >Lịch sử nhập hàng gần đây</CardTitle
                        >
                        <CardDescription class="text-[11px]"
                            >20 giao dịch nhập kho mới nhất</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            v-if="recentPurchases.length === 0"
                            class="flex flex-col items-center gap-2 py-16 text-sm text-muted-foreground"
                        >
                            <ShoppingCart class="size-10 opacity-30" />
                            <p>Chưa có lần nhập hàng nào</p>
                        </div>
                        <div v-else>
                            <div
                                class="grid grid-cols-[1fr_auto_auto_auto] gap-3 border-b border-border bg-muted/40 px-4 py-2.5 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                <div>Nguyên liệu / Nhà cung cấp</div>
                                <div class="text-right">Số lượng</div>
                                <div class="text-right">Đơn giá</div>
                                <div class="text-right">Thành tiền</div>
                            </div>
                            <div
                                v-for="p in recentPurchases"
                                :key="p.id"
                                class="grid grid-cols-[1fr_auto_auto_auto] gap-3 border-b border-border px-4 py-3 text-sm transition-colors last:border-0 hover:bg-muted/20"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ p.ingredient_name }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-muted-foreground"
                                    >
                                        {{
                                            p.supplier_name !== '—'
                                                ? p.supplier_name
                                                : 'Không có NCC'
                                        }}
                                        <span v-if="p.occurred_at">
                                            · {{ p.occurred_at }}</span
                                        >
                                        <span v-if="p.notes" class="italic">
                                            · {{ p.notes }}</span
                                        >
                                    </p>
                                </div>
                                <div class="text-right font-mono text-sm">
                                    {{ p.quantity.toFixed(3) }}
                                </div>
                                <div
                                    class="text-right text-xs text-muted-foreground"
                                >
                                    {{ vnd(p.unit_cost) }}
                                </div>
                                <div
                                    class="text-right font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{ vnd(p.total_cost) }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </template>

        <!-- ══ TAB: HAO HỤT ═══════════════════════════════════════════════════ -->
        <template v-else-if="activeTab === 'waste'">
            <div class="grid gap-6 lg:grid-cols-5">
                <!-- Form -->
                <Card class="shadow-sm lg:col-span-2">
                    <CardHeader class="border-b border-border pb-3">
                        <CardTitle
                            class="flex items-center gap-2 text-sm font-bold"
                        >
                            <Trash2 class="size-4 text-rose-500" />Ghi nhận đổ
                            vỡ & hỏng hóc
                        </CardTitle>
                        <CardDescription class="text-[11px] leading-relaxed">
                            Khai báo các sự cố mất mát thực tế ngoài ý muốn (sữa
                            đổ, rau héo, cháy khét). Nguyên liệu bán hàng đã
                            được hệ thống tự động trừ theo món.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="pt-5">
                        <form @submit.prevent="submitWaste" class="space-y-4">
                            <!-- Nguyên liệu -->
                            <div class="space-y-1.5">
                                <Label class="text-xs"
                                    >Nguyên liệu
                                    <span class="text-rose-500">*</span></Label
                                >
                                <select
                                    v-model="wasteForm.ingredient_id"
                                    required
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:border-rose-400 focus:ring-2 focus:ring-rose-500/20 focus:outline-none"
                                >
                                    <option value="" disabled>
                                        Chọn nguyên liệu...
                                    </option>
                                    <option
                                        v-for="ing in ingredients"
                                        :key="ing.id"
                                        :value="ing.id"
                                    >
                                        {{ ing.name }} (tồn:
                                        {{ ing.stock?.toFixed(1) ?? '—' }}
                                        {{ ing.unit?.symbol ?? '' }})
                                    </option>
                                </select>
                            </div>

                            <!-- Số lượng -->
                            <div class="space-y-1.5">
                                <Label class="text-xs"
                                    >Số lượng hao hụt
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    v-model="wasteForm.quantity"
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    placeholder="0"
                                    required
                                />
                            </div>
                            <!-- Chi phí ước tính -->
                            <div
                                v-if="estimatedWasteCost > 0"
                                class="flex items-center justify-between rounded-xl border border-rose-500/20 bg-rose-500/10 px-4 py-2.5 text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Chi phí thiệt hại ước tính</span
                                >
                                <span
                                    class="font-bold text-rose-600 dark:text-rose-400"
                                    >{{ vnd(estimatedWasteCost) }}</span
                                >
                            </div>

                            <!-- Nhân viên chịu trách nhiệm -->
                            <div class="space-y-1.5">
                                <Label class="text-xs"
                                    >Nhân viên chịu trách nhiệm (nếu có)</Label
                                >
                                <select
                                    v-model="wasteForm.employee_id"
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:border-rose-400 focus:ring-2 focus:ring-rose-500/20 focus:outline-none"
                                >
                                    <option value="">
                                        Không quy trách nhiệm
                                    </option>
                                    <option
                                        v-for="emp in employees"
                                        :key="emp.id"
                                        :value="emp.id"
                                    >
                                        {{ emp.full_name
                                        }}{{
                                            emp.job_title
                                                ? ' — ' + emp.job_title
                                                : ''
                                        }}
                                    </option>
                                </select>
                                <p class="text-[11px] text-muted-foreground">
                                    Nếu chọn nhân viên, hệ thống sẽ tự tạo khoản
                                    khấu trừ lương tháng này.
                                </p>
                            </div>

                            <!-- Ghi chú -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">Ghi chú / lý do</Label>
                                <Input
                                    v-model="wasteForm.notes"
                                    placeholder="Ví dụ: Hư hỏng trong quá trình chế biến..."
                                />
                            </div>

                            <Button
                                type="submit"
                                class="w-full bg-rose-600 text-white hover:bg-rose-700"
                                :disabled="wasteForm.processing"
                            >
                                <Trash2 class="mr-2 size-4" />
                                {{
                                    wasteForm.processing
                                        ? 'Đang lưu...'
                                        : 'Xác nhận hao hụt'
                                }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <!-- Info panel & history -->
                <div class="space-y-4 lg:col-span-3">
                    <!-- Lịch sử hao hụt & trạng thái -->
                    <Card class="shadow-sm">
                        <CardHeader class="border-b border-border pb-3">
                            <CardTitle
                                class="flex items-center justify-between text-sm font-bold"
                            >
                                <span>Lịch sử hao hụt & Trạng thái duyệt</span>
                                <span
                                    class="text-xs font-normal text-muted-foreground"
                                    >Tối đa 15 giao dịch gần đây</span
                                >
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div
                                v-if="recentWastes.length === 0"
                                class="flex flex-col items-center gap-2 py-16 text-sm text-muted-foreground"
                            >
                                <Info
                                    class="size-8 text-muted-foreground opacity-30"
                                />
                                <p>Chưa có dữ liệu hao hụt nào</p>
                            </div>
                            <div
                                v-else
                                class="max-h-[380px] divide-y divide-border overflow-y-auto"
                            >
                                <div
                                    v-for="w in recentWastes"
                                    :key="w.id + '-' + w.is_approval"
                                    class="space-y-2 p-4 text-xs transition-colors hover:bg-muted/10"
                                >
                                    <div
                                        class="flex items-start justify-between gap-4"
                                    >
                                        <div class="min-w-0">
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <span
                                                    class="text-sm font-bold text-slate-800 dark:text-slate-200"
                                                >
                                                    {{ w.ingredient_name }}
                                                </span>
                                                <span
                                                    class="font-mono font-bold text-rose-600 dark:text-rose-400"
                                                >
                                                    -{{ w.quantity }}
                                                    {{ w.unit_symbol }}
                                                </span>
                                            </div>
                                            <p
                                                class="mt-1 text-[10px] text-muted-foreground"
                                            >
                                                <span
                                                    >Thời gian:
                                                    {{ w.occurred_at }}</span
                                                >
                                                <span class="mx-1.5">·</span>
                                                <span
                                                    >Người yêu cầu:
                                                    {{ w.performed_by }}</span
                                                >
                                            </p>
                                            <p
                                                class="text-[10px] text-muted-foreground"
                                            >
                                                <span
                                                    >Khấu trừ lương:
                                                    <strong>{{
                                                        w.employee_name
                                                    }}</strong></span
                                                >
                                                <span
                                                    v-if="w.notes"
                                                    class="italic"
                                                >
                                                    · Ghi chú: "{{
                                                        w.notes
                                                    }}"</span
                                                >
                                            </p>
                                            <p
                                                v-if="
                                                    w.rejection_reason &&
                                                    w.status === 'rejected'
                                                "
                                                class="mt-1 text-[10px] font-semibold text-rose-600"
                                            >
                                                Lý do từ chối: "{{
                                                    w.rejection_reason
                                                }}"
                                            </p>
                                        </div>
                                        <div
                                            class="flex shrink-0 flex-col items-end gap-1.5 text-right"
                                        >
                                            <span
                                                class="font-bold text-slate-800 dark:text-slate-200"
                                            >
                                                Thành tiền:
                                                <span
                                                    class="font-mono text-rose-600 dark:text-rose-400"
                                                    >{{ vnd(w.cost) }}</span
                                                >
                                            </span>
                                            <!-- Trạng thái badge -->
                                            <span
                                                v-if="w.status === 'pending'"
                                                class="rounded-full border border-amber-200 bg-amber-100 px-2 py-0.5 text-[9px] font-bold text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300"
                                            >
                                                Chờ duyệt
                                            </span>
                                            <span
                                                v-else-if="
                                                    w.status === 'approved'
                                                "
                                                class="rounded-full border border-emerald-200 bg-emerald-100 px-2 py-0.5 text-[9px] font-bold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300"
                                            >
                                                Đã duyệt
                                            </span>
                                            <span
                                                v-else-if="
                                                    w.status === 'rejected'
                                                "
                                                class="rounded-full border border-rose-200 bg-rose-100 px-2 py-0.5 text-[9px] font-bold text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300"
                                            >
                                                Bị từ chối
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Guideline card -->
                    <Card
                        class="border-indigo-200 bg-indigo-50/10 shadow-sm dark:border-indigo-900"
                    >
                        <CardContent
                            class="space-y-3.5 pt-4 text-xs text-muted-foreground"
                        >
                            <div class="flex items-start gap-2.5">
                                <Sparkles
                                    class="mt-0.5 size-3.5 shrink-0 animate-pulse text-indigo-500"
                                />
                                <div>
                                    <p
                                        class="mb-0.5 font-semibold text-indigo-700 dark:text-indigo-400"
                                    >
                                        🔮 Tự động trừ kho khi bán hàng
                                    </p>
                                    <p class="text-[11px] leading-relaxed">
                                        Bạn <strong>KHÔNG CẦN</strong> nhập thủ
                                        công nguyên liệu đã bán tại đây. Khi mỗi
                                        đơn hàng hoàn thành, hệ thống sẽ tự động
                                        nhân số lượng bán với định lượng công
                                        thức từng món để
                                        <strong>tự động trừ sạch</strong> lượng
                                        sữa, cà phê... tiêu thụ trong kho.
                                    </p>
                                </div>
                            </div>
                            <div class="h-px bg-border/60" />
                            <div class="flex items-start gap-3">
                                <Info
                                    class="mt-0.5 size-4 shrink-0 text-rose-500"
                                />
                                <div>
                                    <p
                                        class="mb-1 font-semibold text-foreground"
                                    >
                                        Cơ chế khấu trừ lương
                                    </p>
                                    <p class="text-xs">
                                        Khi chọn nhân viên chịu trách nhiệm cho
                                        các sự cố đổ vỡ, hệ thống sẽ tự động tạo
                                        một khoản
                                        <strong>khấu trừ lương</strong>
                                        (inventory_loss) trong tháng hiện tại
                                        bằng:
                                        <strong
                                            >số lượng × giá vốn bình
                                            quân</strong
                                        >
                                        của nguyên liệu.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <Info
                                    class="mt-0.5 size-4 shrink-0 text-amber-500"
                                />
                                <div>
                                    <p
                                        class="mb-1 font-semibold text-foreground"
                                    >
                                        Ảnh hưởng đến COGS
                                    </p>
                                    <p class="text-xs">
                                        Hao hụt sự cố sẽ không ảnh hưởng trực
                                        tiếp đến giá vốn hàng bán (COGS) trong
                                        báo cáo. Tuy nhiên, số lượng tồn kho vật
                                        lý sẽ được giảm để phản ánh chính xác
                                        thực tế tại cửa hàng.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </template>
    </div>

    <!-- ══ Modal: Thêm nguyên liệu ══════════════════════════════════════════════ -->
    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="showAddIngredient"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="showAddIngredient = false"
        >
            <Card class="w-full max-w-md">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Beaker class="size-5 text-indigo-500" />Thêm nguyên
                            liệu thô mới
                        </CardTitle>
                        <button
                            @click="showAddIngredient = false"
                            class="cursor-pointer rounded-lg p-1.5 text-muted-foreground hover:bg-muted"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <CardDescription class="text-xs"
                        >Nguyên liệu này sẽ xuất hiện trong danh sách khi thiết
                        lập công thức định lượng.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitIngredient" class="space-y-4">
                        <div class="space-y-1.5">
                            <Label class="text-xs"
                                >Tên nguyên liệu
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                v-model="ingredientForm.name"
                                placeholder="Ví dụ: Thịt bò, Bánh phở, Nước mắm..."
                                required
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label class="text-xs"
                                    >Đơn vị tính
                                    <span class="text-rose-500">*</span></Label
                                >
                                <select
                                    v-model="ingredientForm.unit_id"
                                    required
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                >
                                    <option value="" disabled>
                                        Chọn đơn vị
                                    </option>
                                    <option
                                        v-for="u in units"
                                        :key="u.id"
                                        :value="u.id"
                                    >
                                        {{ u.name }} ({{ u.symbol }})
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs">Danh mục</Label>
                                <Input
                                    v-model="ingredientForm.category"
                                    placeholder="Thịt, Rau củ..."
                                />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="showAddIngredient = false"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                size="sm"
                                class="bg-indigo-600 text-white"
                                :disabled="ingredientForm.processing"
                            >
                                {{
                                    ingredientForm.processing
                                        ? 'Đang lưu...'
                                        : 'Thêm nguyên liệu'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </Transition>

    <!-- ══ Modal: Thiết lập công thức ═══════════════════════════════════════════ -->
    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="showAddRecipe && activeProduct"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="showAddRecipe = false"
        >
            <Card class="w-full max-w-2xl">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Scale class="size-5 text-indigo-500" />Định lượng công thức:
                            {{ activeProduct.name }}
                        </CardTitle>
                        <button
                            @click="showAddRecipe = false"
                            class="cursor-pointer rounded-lg p-1.5 text-muted-foreground hover:bg-muted"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <CardDescription class="text-xs"
                        >Thiết lập đầy đủ các nguyên liệu và khối lượng/thể tích cấu thành nên món ăn này.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitRecipe" class="space-y-4">
                        <div class="space-y-3">
                            <!-- Table Headers -->
                            <div class="flex items-center justify-between text-[11px] font-bold text-muted-foreground uppercase tracking-wider pb-1.5 border-b border-border">
                                <span class="w-[45%]">Nguyên liệu <span class="text-rose-500">*</span></span>
                                <span class="w-[25%]">Định lượng <span class="text-rose-500">*</span></span>
                                <span class="w-[20%]">Hao hụt (%)</span>
                                <span class="w-[10%] text-center">Xóa</span>
                            </div>
                            
                            <!-- Recipe rows -->
                            <div v-if="recipeForm.items.length" class="space-y-2.5 max-h-[300px] overflow-y-auto pr-1">
                                <div v-for="(item, index) in recipeForm.items" :key="index" class="flex items-center gap-3">
                                    <!-- Ingredient Select -->
                                    <div class="w-[45%]">
                                        <select
                                            v-model="item.ingredient_id"
                                            required
                                            class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                        >
                                            <option value="" disabled>Chọn nguyên liệu</option>
                                            <option
                                                v-for="ing in ingredients"
                                                :key="ing.id"
                                                :value="String(ing.id)"
                                                :disabled="recipeForm.items.some((x: any, idx: number) => x.ingredient_id === String(ing.id) && idx !== index)"
                                            >
                                                {{ ing.name }} ({{ ing.unit?.symbol ?? 'đơn vị' }})
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Quantity Input -->
                                    <div class="w-[25%] relative">
                                        <Input
                                            type="number"
                                            step="0.001"
                                            v-model="item.quantity"
                                            placeholder="150"
                                            required
                                            class="h-9 pr-10 text-xs"
                                        />
                                        <span class="absolute top-1/2 right-2.5 -translate-y-1/2 text-[10px] font-bold text-slate-400 select-none">
                                            {{
                                                ingredients.find(i => String(i.id) === item.ingredient_id)?.unit?.symbol ?? 'đv'
                                            }}
                                        </span>
                                    </div>

                                    <!-- Waste Rate Input -->
                                    <div class="w-[20%]">
                                        <Input
                                            type="number"
                                            v-model="item.waste_rate"
                                            placeholder="0"
                                            class="h-9 text-xs"
                                        />
                                    </div>

                                    <!-- Delete Row Button -->
                                    <div class="w-[10%] flex justify-center">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 w-8 p-0 text-rose-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30"
                                            @click="removeRecipeRow(Number(index))"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Empty State inside form -->
                            <div v-else class="rounded-xl border border-dashed border-border bg-muted/10 p-6 text-center text-xs text-muted-foreground">
                                Chưa thêm nguyên liệu nào. Nhấn nút "Thêm nguyên liệu" bên dưới để bắt đầu thiết lập.
                            </div>

                            <!-- Add Row Button -->
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addRecipeRow"
                                class="mt-2 text-xs text-indigo-650 border-indigo-200 hover:bg-indigo-50 dark:border-indigo-850 dark:text-indigo-400 flex items-center gap-1.5"
                            >
                                <Plus class="size-3.5" /> Thêm nguyên liệu
                            </Button>
                        </div>

                        <!-- Footer Info Banner -->
                        <div class="flex items-start gap-2 rounded-xl border border-indigo-500/20 bg-indigo-500/5 p-3 text-[11px] text-indigo-700 dark:text-indigo-400">
                            <Info class="mt-0.5 size-3.5 shrink-0" />
                            Hệ thống sẽ dùng toàn bộ các định lượng này để tự động tính tổng COGS (giá vốn) cho món ăn khi hoàn thành đơn.
                        </div>

                        <!-- Modal Footer Action Buttons -->
                        <div class="flex justify-end gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="showAddRecipe = false"
                            >
                                Hủy
                            </Button>
                            <Button
                                type="submit"
                                size="sm"
                                class="bg-indigo-600 text-white"
                                :disabled="recipeForm.processing"
                            >
                                {{ recipeForm.processing ? 'Đang lưu...' : 'Lưu công thức' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </Transition>
</template>
