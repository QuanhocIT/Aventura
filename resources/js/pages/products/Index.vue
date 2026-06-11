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
    TrendingDown,
    AlertTriangle,
} from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';
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

type Category = { id: number; name: string; description: string | null };
type Product = {
    id: number;
    code: string;
    name: string;
    price: number;
    description: string | null;
    category: Category | null;
    is_available: boolean;
};

const props = defineProps<{
    categories: Category[];
    products: Product[];
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
        insightsLoaded.value = true;
    } catch {
        insights.value = [];
    } finally {
        insightsLoading.value = false;
    }
}

// ── UI state ──────────────────────────────────────────────────────────────────
const showAddCategory = ref(false);
const showAddProduct = ref(false);
const editingProduct = ref<Product | null>(null);
const deletingProduct = ref<Product | null>(null);
const searchQuery = ref('');
const selectedCategory = ref<number | ''>('');

// ── Forms ──────────────────────────────────────────────────────────────────────
const categoryForm = useForm({ name: '', description: '' });

const productForm = useForm({
    category_id: props.categories[0]?.id ? String(props.categories[0].id) : '',
    name: '',
    price: '',
    description: '',
});

const editForm = useForm({
    name: '',
    price: '',
    category_id: '',
    description: '',
});

// ── Computed ───────────────────────────────────────────────────────────────────
const filteredProducts = computed(() => {
    let list = props.products;

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
    productForm.post('/products', {
        onSuccess: () => {
            productForm.reset();
            showAddProduct.value = false;
        },
    });
};

const openEditModal = (p: Product) => {
    editingProduct.value = p;
    editForm.name = p.name;
    editForm.price = String(p.price);
    editForm.category_id = p.category ? String(p.category.id) : '';
    editForm.description = p.description ?? '';
};

const submitEdit = () => {
    if (!editingProduct.value) {
        return;
    }

    editForm.patch(`/products/${editingProduct.value.id}`, {
        onSuccess: () => {
            editingProduct.value = null;
            editForm.reset();
        },
    });
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
    router.patch(
        `/products/${p.id}`,
        { is_available: !p.is_available },
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Thực đơn & Món" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400"
                >
                    <UtensilsCrossed class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Thực Đơn & Món Ăn
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
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
                    class="flex h-10 items-center gap-1.5 border-indigo-200 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/20"
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
                    class="h-10 border-slate-200 text-xs"
                >
                    <FolderPlus class="mr-2 size-4 text-indigo-600" />Thêm nhóm
                    món
                </Button>
                <Button
                    id="btn-add-product"
                    @click="showAddProduct = true"
                    class="h-10 bg-rose-600 text-xs font-semibold text-white hover:bg-rose-700"
                >
                    <Plus class="mr-2 size-4" />Thêm món ăn
                </Button>
            </div>
        </div>

        <!-- AI Menu Insights Accordion -->
        <div
            v-if="showInsights"
            class="overflow-hidden rounded-2xl border border-indigo-200 bg-indigo-50/50 dark:border-indigo-800/40 dark:bg-indigo-950/10"
        >
            <div
                class="flex items-center gap-2 border-b border-indigo-100 px-4 py-3 dark:border-indigo-800/30"
            >
                <Sparkles class="size-4 text-indigo-500" />
                <span
                    class="text-sm font-bold text-indigo-700 dark:text-indigo-300"
                    >AI Phân tích Menu — 30 ngày gần nhất</span
                >
            </div>
            <!-- Loading -->
            <div
                v-if="insightsLoading"
                class="flex items-center justify-center gap-2 py-8 text-indigo-500"
            >
                <Brain class="size-5 animate-pulse" />
                <span class="text-sm">Đang phân tích dữ liệu...</span>
            </div>
            <!-- No insights -->
            <div
                v-else-if="!insights.length"
                class="flex flex-col items-center py-8 text-center text-slate-400"
            >
                <CheckCircle2 class="mb-2 size-8 text-emerald-400" />
                <p
                    class="text-sm font-semibold text-slate-600 dark:text-slate-300"
                >
                    Menu đang hoạt động tốt!
                </p>
                <p class="mt-1 text-xs">
                    Không phát hiện vấn đề cần cải thiện trong 30 ngày qua.
                </p>
            </div>
            <!-- Insights list -->
            <div
                v-else
                class="divide-y divide-indigo-100 dark:divide-indigo-800/30"
            >
                <div
                    v-for="(insight, i) in insights"
                    :key="i"
                    :class="[
                        'flex items-start gap-3 px-4 py-3 text-xs',
                        insight.severity === 'critical'
                            ? 'bg-rose-50/80 dark:bg-rose-950/10'
                            : insight.severity === 'warning'
                              ? 'bg-amber-50/80 dark:bg-amber-950/10'
                              : '',
                    ]"
                >
                    <span class="mt-0.5 shrink-0 text-base">
                        {{
                            insight.severity === 'critical'
                                ? '🔴'
                                : insight.severity === 'warning'
                                  ? '🟡'
                                  : '🔵'
                        }}
                    </span>
                    <div class="flex-1">
                        <p
                            class="font-semibold text-slate-800 dark:text-slate-200"
                            v-html="insight.message"
                        />
                        <p
                            class="mt-0.5 flex items-center gap-1 text-slate-500 dark:text-slate-400"
                        >
                            <AlertTriangle
                                class="size-3 shrink-0 text-amber-500"
                            />
                            {{ insight.suggestion }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'shrink-0 rounded-full border px-2 py-0.5 text-[9px] font-bold',
                            insight.severity === 'critical'
                                ? 'border-rose-200 bg-rose-100 text-rose-700 dark:bg-rose-950/30 dark:text-rose-400'
                                : insight.severity === 'warning'
                                  ? 'border-amber-200 bg-amber-100 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400'
                                  : 'border-blue-200 bg-blue-100 text-blue-700 dark:bg-blue-950/30 dark:text-blue-400',
                        ]"
                    >
                        {{ insight.value }}{{ insight.unit }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Add Category Modal -->
        <div
            v-if="showAddCategory"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-md animate-in duration-150 zoom-in-95 fade-in"
            >
                <CardHeader>
                    <CardTitle class="text-base"
                        >Tạo nhóm thực đơn mới</CardTitle
                    >
                    <CardDescription
                        >Phân loại món ăn giúp khách hàng order nhanh
                        hơn.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitCategory" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="cat-name"
                                >Tên nhóm món
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                id="cat-name"
                                v-model="categoryForm.name"
                                placeholder="Ví dụ: Món nướng, Trà sữa, Ăn vặt..."
                                required
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="cat-desc">Mô tả nhóm</Label>
                            <textarea
                                id="cat-desc"
                                v-model="categoryForm.description"
                                rows="2"
                                placeholder="Ghi chú mô tả danh mục..."
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="showAddCategory = false"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                class="bg-indigo-600 text-white"
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

        <!-- Add Product Modal -->
        <div
            v-if="showAddProduct"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-md animate-in duration-150 zoom-in-95 fade-in"
            >
                <CardHeader>
                    <CardTitle class="text-base"
                        >Thêm món ăn mới vào thực đơn</CardTitle
                    >
                    <CardDescription
                        >Nhập thông tin chi tiết về sản phẩm để phục vụ bán
                        hàng.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitProduct" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="prod-cat">Thuộc nhóm món</Label>
                            <select
                                id="prod-cat"
                                v-model="productForm.category_id"
                                required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            >
                                <option value="" disabled>Chọn một nhóm</option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="prod-name"
                                >Tên món ăn
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                id="prod-name"
                                v-model="productForm.name"
                                placeholder="Ví dụ: Phở bò tái lăn..."
                                required
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="prod-price"
                                >Giá bán (VND)
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                id="prod-price"
                                type="number"
                                v-model="productForm.price"
                                placeholder="Ví dụ: 45000"
                                required
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <div class="flex items-center justify-between">
                                <Label
                                    for="prod-desc"
                                    class="font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    Đặc điểm & Hương vị món ăn
                                    <span class="font-bold text-rose-500"
                                        >*</span
                                    >
                                </Label>
                                <span
                                    class="rounded-md bg-rose-50 px-1.5 py-0.5 text-[10px] font-medium text-rose-500 dark:bg-rose-950/40"
                                    >Bắt buộc</span
                                >
                            </div>
                            <textarea
                                id="prod-desc"
                                v-model="productForm.description"
                                rows="3"
                                required
                                placeholder="Mô tả hương vị (chua cay, béo ngậy, ngọt dịu...) để nhân viên dễ tư vấn khách."
                                class="w-full rounded-xl border border-slate-200 bg-background px-3 py-2 text-sm transition-all duration-150 focus-visible:border-rose-500 focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:outline-none"
                            />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="showAddProduct = false"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                class="bg-indigo-600 text-white"
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

        <!-- Edit Product Modal -->
        <div
            v-if="editingProduct"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-md animate-in duration-150 zoom-in-95 fade-in"
            >
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Pencil class="size-4 text-indigo-600" />Sửa món ăn
                        </CardTitle>
                        <button
                            @click="editingProduct = null"
                            class="text-slate-400 hover:text-slate-600"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label>Nhóm món</Label>
                            <select
                                v-model="editForm.category_id"
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
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
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                >Tên món ăn
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input v-model="editForm.name" required />
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                >Giá bán (VND)
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                type="number"
                                v-model="editForm.price"
                                required
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <div class="flex items-center justify-between">
                                <Label
                                    class="font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    Đặc điểm & Hương vị món ăn
                                    <span class="font-bold text-rose-500"
                                        >*</span
                                    >
                                </Label>
                                <span
                                    class="rounded-md bg-rose-50 px-1.5 py-0.5 text-[10px] font-medium text-rose-500 dark:bg-rose-950/40"
                                    >Bắt buộc</span
                                >
                            </div>
                            <textarea
                                v-model="editForm.description"
                                rows="3"
                                required
                                placeholder="Mô tả hương vị (chua cay, béo ngậy, ngọt dịu...) để nhân viên dễ tư vấn khách."
                                class="w-full rounded-xl border border-slate-200 bg-background px-3 py-2 text-sm transition-all duration-150 focus-visible:border-rose-500 focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:outline-none"
                            />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="editingProduct = null"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                class="bg-indigo-600 text-white"
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

        <!-- Delete Confirmation Modal -->
        <div
            v-if="deletingProduct"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-sm animate-in duration-150 zoom-in-95 fade-in"
            >
                <CardContent class="space-y-4 pt-6 text-center">
                    <div
                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-950"
                    >
                        <Trash2 class="size-7 text-rose-600" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold">Xóa món ăn này?</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            "{{ deletingProduct.name }}" sẽ bị xóa vĩnh viễn
                            khỏi thực đơn.
                        </p>
                    </div>
                    <div class="flex justify-center gap-3">
                        <Button
                            variant="outline"
                            @click="deletingProduct = null"
                            >Hủy</Button
                        >
                        <Button
                            class="bg-rose-600 text-white hover:bg-rose-700"
                            @click="submitDelete"
                            >Xóa vĩnh viễn</Button
                        >
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <!-- Left: Categories -->
            <div class="flex flex-col gap-4 lg:col-span-1">
                <Card class="shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-bold"
                            >Nhóm Món Ăn</CardTitle
                        >
                        <CardDescription class="text-[11px]"
                            >Cơ cấu thực đơn</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2">
                        <!-- All filter -->
                        <button
                            @click="selectedCategory = ''"
                            class="rounded-xl border p-3 text-left text-xs transition-colors"
                            :class="
                                selectedCategory === ''
                                    ? 'border-rose-300 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/30'
                                    : 'border-slate-100 bg-slate-50/50 hover:border-rose-200 dark:border-slate-800 dark:bg-slate-900'
                            "
                        >
                            <p
                                class="font-bold text-slate-800 dark:text-slate-200"
                            >
                                Tất cả món
                            </p>
                            <p class="mt-0.5 text-[10px] text-slate-400">
                                {{ products.length }} món
                            </p>
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
                                class="group/cat relative cursor-pointer rounded-xl border p-3 text-xs transition-colors"
                                :class="
                                    selectedCategory === cat.id
                                        ? 'border-rose-300 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/30'
                                        : 'border-slate-100 bg-slate-50/50 hover:border-rose-200 dark:border-slate-800 dark:bg-slate-900'
                                "
                            >
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="font-bold text-slate-800 dark:text-slate-200"
                                        >
                                            {{ cat.name }}
                                        </p>
                                        <p
                                            class="mt-0.5 line-clamp-1 text-[10px] text-slate-400"
                                        >
                                            {{
                                                cat.description ??
                                                'Không có mô tả.'
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex shrink-0 items-center gap-1"
                                    >
                                        <span
                                            class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold dark:bg-slate-800"
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
                                            class="rounded-md p-1 text-rose-500 opacity-0 transition-opacity group-hover/cat:opacity-100 hover:bg-rose-100 dark:hover:bg-rose-950/40"
                                            title="Xóa nhóm (chỉ xóa được nhóm rỗng)"
                                        >
                                            <Trash2 class="size-3" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="py-6 text-center text-xs text-slate-400"
                        >
                            <AlertCircle
                                class="mx-auto mb-1 size-6 text-slate-300"
                            />
                            Chưa có nhóm món nào.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right: Products -->
            <div class="lg:col-span-3">
                <Card class="h-full shadow-sm">
                    <CardHeader class="border-b pb-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <CardTitle class="text-base">
                                    Danh sách món ăn
                                    <span
                                        class="ml-1 text-sm font-normal text-muted-foreground"
                                        >({{ filteredProducts.length }}/{{
                                            products.length
                                        }})</span
                                    >
                                </CardTitle>
                                <CardDescription
                                    >Quét mã QR và hóa đơn sẽ đồng bộ với thực
                                    đơn này.</CardDescription
                                >
                            </div>
                            <!-- Search -->
                            <div class="relative w-52 shrink-0">
                                <Search
                                    class="absolute top-2.5 left-3 size-3.5 text-muted-foreground"
                                />
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Tìm món ăn..."
                                    class="h-9 pl-9 text-xs"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            v-if="filteredProducts.length"
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <div
                                v-for="p in filteredProducts"
                                :key="p.id"
                                class="group flex items-center justify-between p-4 transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/40"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex size-10 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-500 dark:bg-slate-800"
                                    >
                                        {{
                                            p.name.substring(0, 2).toUpperCase()
                                        }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4
                                                class="text-sm font-bold text-slate-800 dark:text-slate-200"
                                            >
                                                {{ p.name }}
                                            </h4>
                                            <span
                                                class="rounded-full bg-rose-50 px-2 py-0.5 text-[9px] font-bold tracking-wider text-rose-700 uppercase dark:bg-rose-950 dark:text-rose-400"
                                            >
                                                {{
                                                    p.category?.name ??
                                                    'Chưa gán'
                                                }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-400">
                                            {{ p.code }} ·
                                            {{
                                                p.description ??
                                                'Không có mô tả.'
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="text-right">
                                        <p
                                            class="font-mono text-sm font-bold text-rose-600 dark:text-rose-400"
                                        >
                                            {{ formatCurrency(p.price) }}
                                        </p>
                                        <span
                                            class="mt-1 inline-block rounded-md px-1.5 py-0.5 text-[9px] font-bold"
                                            :class="
                                                p.is_available
                                                    ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400'
                                                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                            "
                                        >
                                            {{
                                                p.is_available
                                                    ? 'Đang bán'
                                                    : 'Tạm ngưng'
                                            }}
                                        </span>
                                    </div>
                                    <!-- Action buttons (hiện khi hover) -->
                                    <div
                                        class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        <button
                                            @click="toggleAvailability(p)"
                                            class="rounded-lg p-1.5 transition-colors"
                                            :class="
                                                p.is_available
                                                    ? 'text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40'
                                                    : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'
                                            "
                                            :title="
                                                p.is_available
                                                    ? 'Tạm ngưng bán'
                                                    : 'Mở bán lại'
                                            "
                                        >
                                            <ToggleRight
                                                v-if="p.is_available"
                                                class="size-4"
                                            />
                                            <ToggleLeft v-else class="size-4" />
                                        </button>
                                        <button
                                            @click="openEditModal(p)"
                                            class="rounded-lg p-1.5 text-indigo-600 transition-colors hover:bg-indigo-50 dark:hover:bg-indigo-950/40"
                                            title="Sửa món ăn"
                                        >
                                            <Pencil class="size-3.5" />
                                        </button>
                                        <button
                                            @click="confirmDelete(p)"
                                            class="rounded-lg p-1.5 text-rose-600 transition-colors hover:bg-rose-50 dark:hover:bg-rose-950/40"
                                            title="Xóa món ăn"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Empty state: no results from search/filter -->
                        <div
                            v-else-if="products.length"
                            class="flex flex-col items-center justify-center p-10 text-center"
                        >
                            <Search class="mb-3 size-10 text-slate-300" />
                            <p class="text-sm font-semibold">
                                Không tìm thấy món ăn nào
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Thử thay đổi từ khóa hoặc chọn nhóm khác.
                            </p>
                            <Button
                                class="mt-3"
                                size="sm"
                                variant="outline"
                                @click="
                                    searchQuery = '';
                                    selectedCategory = '';
                                "
                                >Xóa bộ lọc</Button
                            >
                        </div>
                        <!-- Empty state: no products at all -->
                        <div
                            v-else
                            class="flex flex-col items-center justify-center p-14 text-center"
                        >
                            <div
                                class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground/40"
                            >
                                <UtensilsCrossed class="size-9" />
                            </div>
                            <p class="text-base font-semibold text-foreground">
                                Thực đơn trống
                            </p>
                            <p
                                class="mt-1.5 max-w-xs text-sm text-muted-foreground"
                            >
                                Thêm món ăn đầu tiên để khách hàng có thể đặt
                                hàng qua QR.
                            </p>
                            <Button
                                class="mt-4"
                                size="sm"
                                @click="showAddProduct = true"
                            >
                                <Plus class="mr-1.5 size-4" />Thêm món ăn đầu
                                tiên
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
