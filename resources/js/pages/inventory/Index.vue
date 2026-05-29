<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    Package, Plus, Settings2, Scale, Info, Beaker, X,
    TrendingDown, ShoppingCart, AlertTriangle, Trash2, ArrowDownToLine,
    ChevronDown, ChevronUp, Sparkles, Upload
} from 'lucide-vue-next';
import { computed, ref, onMounted, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Ingredient = {
    id: number; sku: string | null; name: string; category_name: string | null;
    average_cost: number; unit: { id: number; symbol: string } | null;
    stock: number | null; last_cost: number | null;
};
type RecipeItem = { id: number; ingredient_name: string; quantity: number; unit_symbol: string; waste_rate: number };
type Product    = { id: number; name: string; code: string; price: number; recipes: RecipeItem[] };
type Unit       = { id: number; name: string; symbol: string };
type Supplier   = { id: number; name: string };
type Purchase   = {
    id: number; ingredient_name: string; quantity: number; unit_cost: number;
    total_cost: number; supplier_name: string; occurred_at: string | null; notes: string | null;
};
type Employee   = { id: number; full_name: string; job_title: string | null };

const props = defineProps<{
    ingredients:     Ingredient[];
    products:        Product[];
    units:           Unit[];
    suppliers:       Supplier[];
    recentPurchases: Purchase[];
    employees:       Employee[];
}>();

// ── Tabs ──────────────────────────────────────────────────────────────────────
const activeTab = ref<'stock' | 'purchase' | 'waste'>('stock');

// ── Modals (stock tab) ────────────────────────────────────────────────────────
const showAddRecipe     = ref(false);
const showAddIngredient = ref(false);
const activeProduct     = ref<Product | null>(null);

// ── Forms ─────────────────────────────────────────────────────────────────────
const ingredientForm = useForm({
    name: '', unit_id: props.units[0]?.id ? String(props.units[0].id) : '', category: '',
});

const recipeForm = useForm({
    product_id: '', ingredient_id: props.ingredients[0]?.id ? String(props.ingredients[0].id) : '',
    quantity: '', waste_rate: '0',
});

const purchaseForm = useForm({
    ingredient_id: '',
    quantity:      '',
    unit_cost:     '',
    supplier_id:   '',
    notes:         '',
    occurred_at:   new Date().toISOString().slice(0, 10),
    invoice_file:  null as File | null,
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
    toast.success(`Đã áp dụng đề xuất AI cho ${item.ingredient_name}: ${item.suggested_purchase} ${item.unit_symbol}`);
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
    quantity:      '',
    employee_id:   '',
    notes:         '',
});

// ── Computed ──────────────────────────────────────────────────────────────────
const zeroCostIngredients = computed(() =>
    props.ingredients.filter(i => (i.average_cost ?? 0) === 0 &&
        props.products.some(p => p.recipes.some(r => r.ingredient_name === i.name))
    )
);

const selectedPurchaseIngredient = computed(() =>
    props.ingredients.find(i => i.id === Number(purchaseForm.ingredient_id)) ?? null
);

const estimatedWasteCost = computed(() => {
    const ing = props.ingredients.find(i => i.id === Number(wasteForm.ingredient_id));
    if (!ing || !wasteForm.quantity) return 0;
    return Number(wasteForm.quantity) * (ing.average_cost ?? 0);
});

// ── Helpers ───────────────────────────────────────────────────────────────────
const page  = usePage();
const vnd = (v: number) => new Intl.NumberFormat('vi-VN').format(v) + 'đ';
const flashSuccess = () => (page.props.flash as any)?.success ?? null;

// ── Submit handlers ───────────────────────────────────────────────────────────
const submitIngredient = () => {
    ingredientForm.post('/inventory/ingredients', {
        onSuccess: () => { ingredientForm.reset(); showAddIngredient.value = false; },
        onError:   () => toast.error('Có lỗi khi thêm nguyên liệu.'),
    });
};

const openAddRecipeModal = (prod: Product) => {
    activeProduct.value = prod;
    recipeForm.product_id = String(prod.id);
    showAddRecipe.value = true;
};

const submitRecipe = () => {
    recipeForm.post('/inventory/recipes', {
        onSuccess: () => { recipeForm.reset('quantity', 'waste_rate'); showAddRecipe.value = false; },
        onError:   () => toast.error('Có lỗi khi lưu công thức.'),
    });
};

const submitPurchase = () => {
    purchaseForm.post('/inventory/purchases', {
        onSuccess: () => {
            toast.success(flashSuccess() ?? 'Đã ghi nhận nhập hàng!');
            purchaseForm.reset('ingredient_id', 'quantity', 'unit_cost', 'supplier_id', 'notes');
            purchaseForm.occurred_at = new Date().toISOString().slice(0, 10);
        },
        onError: () => toast.error('Có lỗi khi ghi nhận nhập hàng.'),
    });
};

const submitWaste = () => {
    wasteForm.post('/inventory/waste', {
        onSuccess: () => { toast.success(flashSuccess() ?? 'Đã ghi nhận hao hụt!'); wasteForm.reset(); },
        onError:   () => toast.error('Có lỗi khi ghi nhận hao hụt.'),
    });
};
</script>

<template>
    <Head title="Kho & Định lượng nguyên liệu" />

    <div class="flex flex-col gap-6 p-4 lg:p-6 max-w-7xl mx-auto w-full">

        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-border pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-500">
                    <Package class="size-6" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Kho Nguyên Liệu & Công Thức</h1>
                    <p class="text-sm text-muted-foreground">Quản lý nhập hàng, tồn kho và công thức định lượng</p>
                </div>
            </div>
            <Button v-if="activeTab === 'stock'" @click="showAddIngredient = true" variant="outline" class="h-9 text-xs border-indigo-200 text-indigo-700 dark:border-indigo-800 dark:text-indigo-400">
                <Plus class="size-4 mr-1.5" />Thêm nguyên liệu
            </Button>
        </div>

        <!-- Zero-cost warning -->
        <div v-if="zeroCostIngredients.length > 0" class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300">
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            <div>
                <span class="font-semibold">{{ zeroCostIngredients.length }} nguyên liệu chưa có giá vốn:</span>
                {{ zeroCostIngredients.map(i => i.name).join(', ') }}. COGS sẽ bằng 0 cho các món dùng nguyên liệu này. Hãy nhập hàng để cập nhật giá.
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex items-center gap-1 rounded-xl border border-border bg-muted p-1 self-start">
            <button @click="activeTab = 'stock'"
                class="cursor-pointer rounded-lg px-4 py-1.5 text-xs font-semibold transition-all flex items-center gap-1.5"
                :class="activeTab === 'stock' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'">
                <Package class="size-3.5" />Tồn kho & Công thức
            </button>
            <button @click="activeTab = 'purchase'"
                class="cursor-pointer rounded-lg px-4 py-1.5 text-xs font-semibold transition-all flex items-center gap-1.5"
                :class="activeTab === 'purchase' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'">
                <ShoppingCart class="size-3.5" />Nhập hàng
            </button>
            <button @click="activeTab = 'waste'"
                class="cursor-pointer rounded-lg px-4 py-1.5 text-xs font-semibold transition-all flex items-center gap-1.5"
                :class="activeTab === 'waste' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'">
                <Trash2 class="size-3.5" />Hao hụt
            </button>
        </div>

        <!-- ══ TAB: TỒN KHO & CÔNG THỨC ══════════════════════════════════════ -->
        <template v-if="activeTab === 'stock'">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: Ingredient list -->
                <div class="lg:col-span-1">
                    <Card class="shadow-sm h-full">
                        <CardHeader class="pb-3 border-b border-border">
                            <CardTitle class="text-sm font-bold">Tồn Kho Nguyên Liệu ({{ ingredients.length }})</CardTitle>
                            <CardDescription class="text-[11px]">
                                <span class="text-rose-500 font-semibold">{{ ingredients.filter(i => i.stock !== null && i.stock < 5).length }}</span> sắp hết ·
                                <span class="text-amber-500 font-semibold">{{ ingredients.filter(i => i.stock !== null && i.stock >= 5 && i.stock < 20).length }}</span> cần nhập thêm
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-0 divide-y divide-border">
                            <div v-if="ingredients.length">
                                <div v-for="ing in ingredients" :key="ing.id"
                                    class="p-3 flex justify-between items-center text-xs hover:bg-muted/30 transition-colors">
                                    <div class="flex-1 min-w-0 mr-2">
                                        <div class="flex items-center gap-1.5">
                                            <p class="font-bold truncate">{{ ing.name }}</p>
                                            <span v-if="(ing.average_cost ?? 0) === 0"
                                                class="shrink-0 text-[9px] bg-amber-100 text-amber-700 px-1 rounded dark:bg-amber-900/40 dark:text-amber-400">
                                                0đ
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-muted-foreground mt-0.5">
                                            {{ ing.sku ?? 'SKU-NONE' }} · {{ ing.category_name ?? 'Nguyên liệu' }}
                                        </p>
                                        <p v-if="ing.average_cost > 0" class="text-[10px] text-indigo-500 mt-0.5 font-semibold">
                                            TB: {{ vnd(ing.average_cost) }}/{{ ing.unit?.symbol ?? 'đv' }}
                                        </p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="flex items-center gap-1.5 justify-end">
                                            <TrendingDown v-if="ing.stock !== null && ing.stock < 5" class="size-3 text-rose-500" />
                                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full"
                                                :class="ing.stock === null ? 'bg-muted text-muted-foreground'
                                                    : ing.stock < 5 ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-400'
                                                    : ing.stock < 20 ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400'
                                                    : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400'">
                                                {{ ing.stock !== null ? ing.stock.toFixed(1) : '—' }} {{ ing.unit?.symbol ?? '' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-12 text-muted-foreground text-xs">
                                Chưa có nguyên liệu. Nhấn "Thêm nguyên liệu" để bắt đầu.
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right: Product recipes -->
                <div class="lg:col-span-2">
                    <Card class="shadow-sm">
                        <CardHeader class="pb-3 border-b border-border">
                            <CardTitle class="text-base flex items-center gap-1.5">
                                <Scale class="size-5 text-indigo-500" />Công Thức Định Lượng
                            </CardTitle>
                            <CardDescription>Thiết lập nguyên liệu cho từng món để hệ thống tự tính COGS.</CardDescription>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div v-if="products.length" class="divide-y divide-border">
                                <div v-for="p in products" :key="p.id" class="p-5 flex flex-col gap-3 hover:bg-muted/20 transition-colors">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h4 class="font-bold text-sm">{{ p.name }}</h4>
                                            <p class="text-[10px] text-muted-foreground">{{ p.code }}</p>
                                        </div>
                                        <Button @click="openAddRecipeModal(p)" size="sm" variant="outline"
                                            class="btn-set-recipe h-8 text-xs border-indigo-200 text-indigo-600 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-400">
                                            <Settings2 class="size-3.5 mr-1" />Thiết lập định lượng
                                        </Button>
                                    </div>
                                    <div class="bg-muted/40 p-3 rounded-xl border border-border">
                                        <div v-if="p.recipes.length" class="flex flex-wrap gap-2">
                                            <span v-for="r in p.recipes" :key="r.id"
                                                class="text-[10px] bg-card border px-2.5 py-1.5 rounded-lg flex items-center gap-1 font-medium">
                                                <strong>{{ r.ingredient_name }}:</strong>
                                                <span class="font-mono text-indigo-500 font-bold">{{ r.quantity }}</span>
                                                <span class="text-muted-foreground">{{ r.unit_symbol }}</span>
                                                <span v-if="r.waste_rate > 0" class="text-[9px] bg-amber-50 text-amber-700 px-1 rounded border border-amber-200 dark:bg-amber-900/30 dark:text-amber-400">
                                                    +{{ r.waste_rate }}% hao
                                                </span>
                                            </span>
                                        </div>
                                        <div v-else class="text-[11px] text-muted-foreground flex items-center gap-1">
                                            <AlertTriangle class="size-3.5 text-amber-400" />
                                            Chưa có công thức — COGS sẽ bằng 0 cho món này!
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-12 text-muted-foreground text-xs">
                                Chưa có món ăn nào.
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </template>

        <!-- ══ TAB: NHẬP HÀNG ═════════════════════════════════════════════════ -->
        <template v-else-if="activeTab === 'purchase'">
            <div class="grid gap-6 lg:grid-cols-5 animate-in fade-in duration-200">
                <!-- Form -->
                <Card class="lg:col-span-2 shadow-sm border-slate-200/80">
                    <CardHeader class="border-b border-border pb-3">
                        <CardTitle class="text-sm font-bold flex items-center gap-2">
                            <ArrowDownToLine class="size-4 text-indigo-500" />Ghi nhận nhập hàng
                        </CardTitle>
                        <CardDescription class="text-[11px]">Cập nhật tồn kho và tính toán giá vốn bình quân.</CardDescription>
                    </CardHeader>
                    <CardContent class="pt-5">
                        <form @submit.prevent="submitPurchase" class="space-y-4">
                            <!-- Nguyên liệu -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">Nguyên liệu <span class="text-rose-500">*</span></Label>
                                <select v-model="purchaseForm.ingredient_id" required
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                    :class="{'border-rose-400': purchaseForm.errors.ingredient_id}">
                                    <option value="" disabled>Chọn nguyên liệu...</option>
                                    <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
                                        {{ ing.name }} (tồn: {{ ing.stock?.toFixed(1) ?? '—' }} {{ ing.unit?.symbol ?? '' }})
                                    </option>
                                </select>
                                <p v-if="selectedPurchaseIngredient" class="text-[11px] text-indigo-500">
                                    Giá vốn TB hiện tại: <strong>{{ vnd(selectedPurchaseIngredient.average_cost) }}/{{ selectedPurchaseIngredient.unit?.symbol ?? 'đv' }}</strong>
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <!-- Số lượng -->
                                <div class="space-y-1.5">
                                    <Label class="text-xs">Số lượng <span class="text-rose-500">*</span></Label>
                                    <div class="relative">
                                        <Input v-model="purchaseForm.quantity" type="number" step="0.001" min="0.001"
                                            placeholder="0" required
                                            class="pr-10"
                                            :class="{'border-rose-400': purchaseForm.errors.quantity}" />
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground font-medium">
                                            {{ selectedPurchaseIngredient?.unit?.symbol ?? 'đv' }}
                                        </span>
                                    </div>
                                </div>
                                <!-- Đơn giá -->
                                <div class="space-y-1.5">
                                    <Label class="text-xs">Đơn giá (đ) <span class="text-rose-500">*</span></Label>
                                    <Input v-model="purchaseForm.unit_cost" type="number" step="1" min="0"
                                        placeholder="0" required
                                        :class="{'border-rose-400': purchaseForm.errors.unit_cost}" />
                                </div>
                            </div>

                            <!-- Thành tiền preview -->
                            <div v-if="purchaseForm.quantity && purchaseForm.unit_cost"
                                class="rounded-xl bg-indigo-500/10 border border-indigo-500/20 px-4 py-2.5 flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Thành tiền</span>
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ vnd(Number(purchaseForm.quantity) * Number(purchaseForm.unit_cost)) }}
                                </span>
                            </div>

                            <!-- Nhà cung cấp -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">Nhà cung cấp</Label>
                                <select v-model="purchaseForm.supplier_id"
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                    <option value="">Không chọn</option>
                                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>

                            <!-- Ngày nhập -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">Ngày nhập</Label>
                                <Input v-model="purchaseForm.occurred_at" type="date" />
                            </div>

                            <!-- Ghi chú -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">Ghi chú</Label>
                                <Input v-model="purchaseForm.notes" placeholder="Số hóa đơn, ghi chú..." />
                            </div>

                            <!-- Tải ảnh hóa đơn cứng bắt buộc -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Hóa đơn cứng (Ảnh chụp chứng từ) <span class="text-rose-500 font-bold">*</span>
                                </Label>
                                <input type="file" @change="(e) => purchaseForm.invoice_file = (e.target as HTMLInputElement).files?.[0] || null"
                                    accept="image/*,.pdf" required
                                    class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-slate-200 rounded-xl p-2 bg-background focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />
                                <p class="text-[10px] text-muted-foreground">Chấp nhận JPG, PNG, PDF tối đa 2MB.</p>
                            </div>

                            <div class="flex items-start gap-2 text-[11px] text-muted-foreground">
                                <Info class="size-3.5 mt-0.5 shrink-0 text-indigo-400" />
                                <span>Nhân viên kho nhập hàng sẽ được Chủ doanh nghiệp kiểm duyệt chéo và phê duyệt trước khi cộng kho.</span>
                            </div>

                            <Button type="submit" class="w-full bg-indigo-600 text-white hover:bg-indigo-700 rounded-xl"
                                :disabled="purchaseForm.processing">
                                <ArrowDownToLine class="size-4 mr-2" />
                                {{ purchaseForm.processing ? 'Đang gửi phê duyệt...' : 'Gửi yêu cầu nhập hàng' }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <!-- AI Forecast Recommendations -->
                <Card class="lg:col-span-3 shadow-sm border-indigo-200 dark:border-indigo-900/60 bg-gradient-to-br from-indigo-50/20 via-background to-background animate-in slide-in-from-right duration-250">
                    <CardHeader class="border-b border-border pb-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-sm font-bold flex items-center gap-1.5 text-indigo-700 dark:text-indigo-400">
                                    <Sparkles class="size-4 animate-pulse text-indigo-500" />
                                    🔮 Đề xuất nhập hàng AI (7 ngày tới)
                                </CardTitle>
                                <CardDescription class="text-[11px]">AI phân tích xu hướng và đề xuất số lượng nhập tối ưu.</CardDescription>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 animate-bounce">
                                Độ tin cậy > 92%
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0 divide-y divide-border">
                        <div v-if="loadingForecast" class="p-16 text-center text-xs text-muted-foreground">
                            <span class="animate-spin inline-block mr-1">🔄</span> Đang tính toán phân tích dữ liệu...
                        </div>
                        <div v-else-if="aiForecasts.length === 0" class="p-16 text-center text-xs text-muted-foreground">
                            Không tìm thấy dữ liệu đề xuất.
                        </div>
                        <div v-else class="max-h-[500px] overflow-y-auto divide-y divide-border">
                            <div v-for="item in aiForecasts" :key="item.ingredient_id" class="p-4 hover:bg-muted/10 transition-colors flex justify-between items-start gap-4 text-xs">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ item.ingredient_name }}</span>
                                        <span class="text-[9px] bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 px-1.5 py-0.5 rounded-md font-bold">
                                            Độ tin cậy: {{ item.confidence_score }}%
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-muted-foreground mt-1 leading-relaxed">
                                        {{ item.reason }}
                                    </p>
                                    <div class="flex items-center gap-4 mt-2 text-[10px] text-slate-500">
                                        <span>Tồn hiện tại: <strong class="font-mono text-slate-700 dark:text-slate-300">{{ item.current_stock }} {{ item.unit_symbol }}</strong></span>
                                        <span>Dự báo 7 ngày tới: <strong class="font-mono text-slate-700 dark:text-slate-300">{{ item.predicted_usage_next_7_days }} {{ item.unit_symbol }}</strong></span>
                                    </div>
                                </div>
                                <div class="text-right shrink-0 flex flex-col items-end gap-1.5">
                                    <div class="font-bold text-indigo-600 dark:text-indigo-400">
                                        Cần mua: <span class="font-mono text-sm">{{ item.suggested_purchase }}</span> {{ item.unit_symbol }}
                                    </div>
                                    <Button size="sm" variant="outline" type="button" class="h-7 text-[10px] border-indigo-200 text-indigo-600 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-400" @click="applyForecast(item)">
                                        Áp dụng đề xuất
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent purchases -->
                <Card class="lg:col-span-5 shadow-sm">
                    <CardHeader class="border-b border-border pb-3">
                        <CardTitle class="text-sm font-bold">Lịch sử nhập hàng gần đây</CardTitle>
                        <CardDescription class="text-[11px]">20 giao dịch nhập kho mới nhất</CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="recentPurchases.length === 0"
                            class="flex flex-col items-center gap-2 py-16 text-muted-foreground text-sm">
                            <ShoppingCart class="size-10 opacity-30" />
                            <p>Chưa có lần nhập hàng nào</p>
                        </div>
                        <div v-else>
                            <div class="grid grid-cols-[1fr_auto_auto_auto] gap-3 bg-muted/40 px-4 py-2.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground border-b border-border">
                                <div>Nguyên liệu / Nhà cung cấp</div>
                                <div class="text-right">Số lượng</div>
                                <div class="text-right">Đơn giá</div>
                                <div class="text-right">Thành tiền</div>
                            </div>
                            <div v-for="p in recentPurchases" :key="p.id"
                                class="grid grid-cols-[1fr_auto_auto_auto] gap-3 px-4 py-3 text-sm border-b border-border hover:bg-muted/20 transition-colors last:border-0">
                                <div class="min-w-0">
                                    <p class="font-medium truncate">{{ p.ingredient_name }}</p>
                                    <p class="text-[10px] text-muted-foreground mt-0.5">
                                        {{ p.supplier_name !== '—' ? p.supplier_name : 'Không có NCC' }}
                                        <span v-if="p.occurred_at"> · {{ p.occurred_at }}</span>
                                        <span v-if="p.notes" class="italic"> · {{ p.notes }}</span>
                                    </p>
                                </div>
                                <div class="text-right font-mono text-sm">{{ p.quantity.toFixed(3) }}</div>
                                <div class="text-right text-muted-foreground text-xs">{{ vnd(p.unit_cost) }}</div>
                                <div class="text-right font-semibold text-emerald-600 dark:text-emerald-400">{{ vnd(p.total_cost) }}</div>
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
                <Card class="lg:col-span-2 shadow-sm">
                    <CardHeader class="border-b border-border pb-3">
                        <CardTitle class="text-sm font-bold flex items-center gap-2">
                            <Trash2 class="size-4 text-rose-500" />Ghi nhận hao hụt
                        </CardTitle>
                        <CardDescription class="text-[11px]">
                            Khi có nhân viên chịu trách nhiệm, hệ thống sẽ tự tạo khoản khấu trừ lương.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="pt-5">
                        <form @submit.prevent="submitWaste" class="space-y-4">
                            <!-- Nguyên liệu -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">Nguyên liệu <span class="text-rose-500">*</span></Label>
                                <select v-model="wasteForm.ingredient_id" required
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400">
                                    <option value="" disabled>Chọn nguyên liệu...</option>
                                    <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
                                        {{ ing.name }} (tồn: {{ ing.stock?.toFixed(1) ?? '—' }} {{ ing.unit?.symbol ?? '' }})
                                    </option>
                                </select>
                            </div>

                            <!-- Số lượng -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">Số lượng hao hụt <span class="text-rose-500">*</span></Label>
                                <Input v-model="wasteForm.quantity" type="number" step="0.001" min="0.001"
                                    placeholder="0" required />
                            </div>

                            <!-- Chi phí ước tính -->
                            <div v-if="estimatedWasteCost > 0"
                                class="rounded-xl bg-rose-500/10 border border-rose-500/20 px-4 py-2.5 flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Chi phí thiệt hại ước tính</span>
                                <span class="font-bold text-rose-600 dark:text-rose-400">{{ vnd(estimatedWasteCost) }}</span>
                            </div>

                            <!-- Nhân viên chịu trách nhiệm -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">Nhân viên chịu trách nhiệm (nếu có)</Label>
                                <select v-model="wasteForm.employee_id"
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400">
                                    <option value="">Không quy trách nhiệm</option>
                                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                                        {{ emp.full_name }}{{ emp.job_title ? ' — ' + emp.job_title : '' }}
                                    </option>
                                </select>
                                <p class="text-[11px] text-muted-foreground">
                                    Nếu chọn nhân viên, hệ thống sẽ tự tạo khoản khấu trừ lương tháng này.
                                </p>
                            </div>

                            <!-- Ghi chú -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">Ghi chú / lý do</Label>
                                <Input v-model="wasteForm.notes" placeholder="Ví dụ: Hư hỏng trong quá trình chế biến..." />
                            </div>

                            <Button type="submit" class="w-full bg-rose-600 text-white hover:bg-rose-700"
                                :disabled="wasteForm.processing">
                                <Trash2 class="size-4 mr-2" />
                                {{ wasteForm.processing ? 'Đang lưu...' : 'Xác nhận hao hụt' }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <!-- Info panel -->
                <div class="lg:col-span-3 space-y-4">
                    <Card class="shadow-sm border-rose-200 dark:border-rose-900">
                        <CardContent class="pt-5 space-y-3 text-sm text-muted-foreground">
                            <div class="flex items-start gap-3">
                                <Info class="size-4 shrink-0 mt-0.5 text-rose-500" />
                                <div>
                                    <p class="font-semibold text-foreground mb-1">Cơ chế khấu trừ lương</p>
                                    <p class="text-xs">Khi chọn nhân viên chịu trách nhiệm, hệ thống sẽ tự động tạo một khoản <strong>khấu trừ lương</strong> (inventory_loss) trong tháng hiện tại với số tiền bằng: <strong>số lượng × giá vốn bình quân</strong> của nguyên liệu.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <Info class="size-4 shrink-0 mt-0.5 text-amber-500" />
                                <div>
                                    <p class="font-semibold text-foreground mb-1">Ảnh hưởng đến COGS</p>
                                    <p class="text-xs">Hao hụt sẽ không ảnh hưởng trực tiếp đến COGS trong báo cáo (COGS chỉ tính từ đơn hàng đã hoàn thành). Tuy nhiên số lượng tồn kho sẽ được giảm để phản ánh thực tế.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </template>
    </div>

    <!-- ══ Modal: Thêm nguyên liệu ══════════════════════════════════════════════ -->
    <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
        leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="showAddIngredient" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="showAddIngredient = false">
            <Card class="max-w-md w-full">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-base flex items-center gap-2">
                            <Beaker class="size-5 text-indigo-500" />Thêm nguyên liệu thô mới
                        </CardTitle>
                        <button @click="showAddIngredient = false" class="cursor-pointer rounded-lg p-1.5 text-muted-foreground hover:bg-muted"><X class="size-4" /></button>
                    </div>
                    <CardDescription class="text-xs">Nguyên liệu này sẽ xuất hiện trong danh sách khi thiết lập công thức định lượng.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitIngredient" class="space-y-4">
                        <div class="space-y-1.5">
                            <Label class="text-xs">Tên nguyên liệu <span class="text-rose-500">*</span></Label>
                            <Input v-model="ingredientForm.name" placeholder="Ví dụ: Thịt bò, Bánh phở, Nước mắm..." required />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label class="text-xs">Đơn vị tính <span class="text-rose-500">*</span></Label>
                                <select v-model="ingredientForm.unit_id" required
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                    <option value="" disabled>Chọn đơn vị</option>
                                    <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }} ({{ u.symbol }})</option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs">Danh mục</Label>
                                <Input v-model="ingredientForm.category" placeholder="Thịt, Rau củ..." />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" size="sm" @click="showAddIngredient = false">Hủy</Button>
                            <Button type="submit" size="sm" class="bg-indigo-600 text-white" :disabled="ingredientForm.processing">
                                {{ ingredientForm.processing ? 'Đang lưu...' : 'Thêm nguyên liệu' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </Transition>

    <!-- ══ Modal: Thiết lập công thức ═══════════════════════════════════════════ -->
    <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
        leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="showAddRecipe && activeProduct" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="showAddRecipe = false">
            <Card class="max-w-md w-full">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-base flex items-center gap-2">
                            <Scale class="size-5 text-indigo-500" />Định lượng: {{ activeProduct.name }}
                        </CardTitle>
                        <button @click="showAddRecipe = false" class="cursor-pointer rounded-lg p-1.5 text-muted-foreground hover:bg-muted"><X class="size-4" /></button>
                    </div>
                    <CardDescription class="text-xs">Cài đặt khối lượng/thể tích nguyên liệu cho 1 khẩu phần.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitRecipe" class="space-y-4">
                        <div class="space-y-1.5">
                            <Label class="text-xs">Chọn Nguyên Liệu <span class="text-rose-500">*</span></Label>
                            <select v-model="recipeForm.ingredient_id" required
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                <option value="" disabled>Chọn nguyên liệu</option>
                                <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
                                    {{ ing.name }} ({{ ing.unit?.symbol ?? 'đơn vị' }})
                                </option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label class="text-xs">Số lượng định lượng <span class="text-rose-500">*</span></Label>
                                <div class="relative">
                                    <Input type="number" step="0.001" v-model="recipeForm.quantity" placeholder="150" required class="pr-10" />
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground font-medium">
                                        {{ ingredients.find(i => i.id === Number(recipeForm.ingredient_id))?.unit?.symbol ?? 'đv' }}
                                    </span>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs">Tỉ lệ hao hụt (%)</Label>
                                <Input type="number" v-model="recipeForm.waste_rate" placeholder="0" />
                            </div>
                        </div>
                        <div class="flex items-start gap-2 rounded-xl bg-indigo-500/5 border border-indigo-500/20 p-3 text-[11px] text-indigo-700 dark:text-indigo-400">
                            <Info class="size-3.5 shrink-0 mt-0.5" />
                            Hệ thống sẽ dùng công thức này để tính COGS khi đơn hàng hoàn thành.
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" size="sm" @click="showAddRecipe = false">Hủy</Button>
                            <Button type="submit" size="sm" class="bg-indigo-600 text-white" :disabled="recipeForm.processing">
                                {{ recipeForm.processing ? 'Đang lưu...' : 'Lưu công thức' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </Transition>
</template>
