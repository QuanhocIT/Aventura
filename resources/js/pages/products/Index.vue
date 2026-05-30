<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    UtensilsCrossed, Plus, FolderPlus, Search,
    CheckCircle2, AlertCircle, Pencil, Trash2, X, ChevronDown, ChevronUp,
    ToggleLeft, ToggleRight, Brain, Sparkles, TrendingDown, AlertTriangle,
} from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Category = { id: number; name: string; description: string | null };
type Product   = { id: number; code: string; name: string; price: number; description: string | null; category: Category | null; is_available: boolean };

const props = defineProps<{
    categories: Category[];
    products:   Product[];
}>();

// ── AI Menu Insights ──────────────────────────────────────────────────────────
type MenuInsight = { type: string; severity: string; product: string; product_id: number; message: string; suggestion: string; value: number; unit: string };
const showInsights   = ref(false);
const insightsLoaded = ref(false);
const insightsLoading = ref(false);
const insights       = ref<MenuInsight[]>([]);

async function loadInsights() {
    if (insightsLoaded.value) { showInsights.value = !showInsights.value; return; }
    insightsLoading.value = true;
    showInsights.value    = true;
    try {
        const res = await fetch('/api/products/menu-insights');
        const data = await res.json();
        insights.value = data.insights ?? [];
        insightsLoaded.value = true;
    } catch { insights.value = []; }
    finally { insightsLoading.value = false; }
}

// ── UI state ──────────────────────────────────────────────────────────────────
const showAddCategory   = ref(false);
const showAddProduct    = ref(false);
const editingProduct    = ref<Product | null>(null);
const deletingProduct   = ref<Product | null>(null);
const searchQuery       = ref('');
const selectedCategory  = ref<number | ''>('');

// ── Forms ──────────────────────────────────────────────────────────────────────
const categoryForm = useForm({ name: '', description: '' });

const productForm = useForm({
    category_id: props.categories[0]?.id ? String(props.categories[0].id) : '',
    name: '', price: '', description: ''
});

const editForm = useForm({
    name: '', price: '', category_id: '', description: ''
});

// ── Computed ───────────────────────────────────────────────────────────────────
const filteredProducts = computed(() => {
    let list = props.products;

    if (selectedCategory.value !== '') {
        list = list.filter(p => p.category?.id === selectedCategory.value);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(p =>
            p.name.toLowerCase().includes(q) ||
            p.code.toLowerCase().includes(q) ||
            (p.description ?? '').toLowerCase().includes(q)
        );
    }

    return list;
});

// ── Handlers ──────────────────────────────────────────────────────────────────
const submitCategory = () => {
    categoryForm.post('/product-categories', {
        onSuccess: () => {
 categoryForm.reset(); showAddCategory.value = false; 
}
    });
};

const submitProduct = () => {
    productForm.post('/products', {
        onSuccess: () => {
 productForm.reset(); showAddProduct.value = false; 
}
    });
};

const openEditModal = (p: Product) => {
    editingProduct.value = p;
    editForm.name        = p.name;
    editForm.price       = String(p.price);
    editForm.category_id = p.category ? String(p.category.id) : '';
    editForm.description = p.description ?? '';
};

const submitEdit = () => {
    if (!editingProduct.value) {
return;
}

    editForm.patch(`/products/${editingProduct.value.id}`, {
        onSuccess: () => {
 editingProduct.value = null; editForm.reset(); 
}
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
}
    });
};

const formatCurrency = (val: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);

const toggleAvailability = (p: Product) => {
    router.patch(`/products/${p.id}`, { is_available: !p.is_available }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Thực đơn & Món" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                    <UtensilsCrossed class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Thực Đơn & Món Ăn</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Quản lý cấu trúc thực đơn, nhóm món, giá bán sản phẩm thực tế của quán.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <!-- AI Insights button -->
                <Button @click="loadInsights" variant="outline"
                    class="h-10 text-xs border-indigo-200 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/20 font-semibold flex items-center gap-1.5">
                    <Brain class="size-4" />
                    AI Phân tích Menu
                    <component :is="showInsights ? ChevronUp : ChevronDown" class="size-3.5" />
                </Button>
                <Button id="btn-add-category" @click="showAddCategory = true" variant="outline" class="h-10 text-xs border-slate-200">
                    <FolderPlus class="size-4 mr-2 text-indigo-600" />Thêm nhóm món
                </Button>
                <Button id="btn-add-product" @click="showAddProduct = true" class="h-10 text-xs bg-rose-600 hover:bg-rose-700 text-white font-semibold">
                    <Plus class="size-4 mr-2" />Thêm món ăn
                </Button>
            </div>
        </div>

        <!-- AI Menu Insights Accordion -->
        <div v-if="showInsights" class="rounded-2xl border border-indigo-200 dark:border-indigo-800/40 bg-indigo-50/50 dark:bg-indigo-950/10 overflow-hidden">
            <div class="px-4 py-3 border-b border-indigo-100 dark:border-indigo-800/30 flex items-center gap-2">
                <Sparkles class="size-4 text-indigo-500" />
                <span class="text-sm font-bold text-indigo-700 dark:text-indigo-300">AI Phân tích Menu — 30 ngày gần nhất</span>
            </div>
            <!-- Loading -->
            <div v-if="insightsLoading" class="flex items-center justify-center py-8 gap-2 text-indigo-500">
                <Brain class="size-5 animate-pulse" />
                <span class="text-sm">Đang phân tích dữ liệu...</span>
            </div>
            <!-- No insights -->
            <div v-else-if="!insights.length" class="flex flex-col items-center py-8 text-center text-slate-400">
                <CheckCircle2 class="size-8 text-emerald-400 mb-2" />
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Menu đang hoạt động tốt!</p>
                <p class="text-xs mt-1">Không phát hiện vấn đề cần cải thiện trong 30 ngày qua.</p>
            </div>
            <!-- Insights list -->
            <div v-else class="divide-y divide-indigo-100 dark:divide-indigo-800/30">
                <div v-for="(insight, i) in insights" :key="i"
                    :class="[
                        'flex items-start gap-3 px-4 py-3 text-xs',
                        insight.severity === 'critical' ? 'bg-rose-50/80 dark:bg-rose-950/10' :
                        insight.severity === 'warning'  ? 'bg-amber-50/80 dark:bg-amber-950/10' : ''
                    ]"
                >
                    <span class="text-base shrink-0 mt-0.5">
                        {{ insight.severity === 'critical' ? '🔴' : insight.severity === 'warning' ? '🟡' : '🔵' }}
                    </span>
                    <div class="flex-1">
                        <p class="font-semibold text-slate-800 dark:text-slate-200" v-html="insight.message" />
                        <p class="text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-1">
                            <AlertTriangle class="size-3 text-amber-500 shrink-0" />
                            {{ insight.suggestion }}
                        </p>
                    </div>
                    <span :class="[
                        'shrink-0 px-2 py-0.5 rounded-full text-[9px] font-bold border',
                        insight.severity === 'critical' ? 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400' :
                        insight.severity === 'warning'  ? 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400' :
                                                          'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-950/30 dark:text-blue-400'
                    ]">
                        {{ insight.value }}{{ insight.unit }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Add Category Modal -->
        <div v-if="showAddCategory" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150">
                <CardHeader>
                    <CardTitle class="text-base">Tạo nhóm thực đơn mới</CardTitle>
                    <CardDescription>Phân loại món ăn giúp khách hàng order nhanh hơn.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitCategory" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="cat-name">Tên nhóm món <span class="text-rose-500">*</span></Label>
                            <Input id="cat-name" v-model="categoryForm.name" placeholder="Ví dụ: Món nướng, Trà sữa, Ăn vặt..." required />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="cat-desc">Mô tả nhóm</Label>
                            <textarea id="cat-desc" v-model="categoryForm.description" rows="2"
                                placeholder="Ghi chú mô tả danh mục..."
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="showAddCategory = false">Hủy</Button>
                            <Button type="submit" class="bg-indigo-600 text-white" :disabled="categoryForm.processing">
                                {{ categoryForm.processing ? 'Đang tạo...' : 'Tạo nhóm món' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Add Product Modal -->
        <div v-if="showAddProduct" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150">
                <CardHeader>
                    <CardTitle class="text-base">Thêm món ăn mới vào thực đơn</CardTitle>
                    <CardDescription>Nhập thông tin chi tiết về sản phẩm để phục vụ bán hàng.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitProduct" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="prod-cat">Thuộc nhóm món</Label>
                            <select id="prod-cat" v-model="productForm.category_id" required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                <option value="" disabled>Chọn một nhóm</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="prod-name">Tên món ăn <span class="text-rose-500">*</span></Label>
                            <Input id="prod-name" v-model="productForm.name" placeholder="Ví dụ: Phở bò tái lăn..." required />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="prod-price">Giá bán (VND) <span class="text-rose-500">*</span></Label>
                            <Input id="prod-price" type="number" v-model="productForm.price" placeholder="Ví dụ: 45000" required />
                        </div>
                        <div class="grid gap-1.5">
                            <div class="flex items-center justify-between">
                                <Label for="prod-desc" class="font-semibold text-slate-700 dark:text-slate-300">
                                    Đặc điểm & Hương vị món ăn <span class="text-rose-500 font-bold">*</span>
                                </Label>
                                <span class="text-[10px] text-rose-500 font-medium bg-rose-50 dark:bg-rose-950/40 px-1.5 py-0.5 rounded-md">Bắt buộc</span>
                            </div>
                            <textarea id="prod-desc" v-model="productForm.description" rows="3" required
                                placeholder="Mô tả hương vị (chua cay, béo ngậy, ngọt dịu...) để nhân viên dễ tư vấn khách."
                                class="w-full rounded-xl border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:border-rose-500 transition-all duration-150" />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="showAddProduct = false">Hủy</Button>
                            <Button type="submit" class="bg-indigo-600 text-white" :disabled="productForm.processing">
                                {{ productForm.processing ? 'Đang thêm...' : 'Thêm món ăn' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Edit Product Modal -->
        <div v-if="editingProduct" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-base flex items-center gap-2">
                            <Pencil class="size-4 text-indigo-600" />Sửa món ăn
                        </CardTitle>
                        <button @click="editingProduct = null" class="text-slate-400 hover:text-slate-600">
                            <X class="size-4" />
                        </button>
                    </div>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label>Nhóm món</Label>
                            <select v-model="editForm.category_id"
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                <option value="">Chưa gán nhóm</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Tên món ăn <span class="text-rose-500">*</span></Label>
                            <Input v-model="editForm.name" required />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Giá bán (VND) <span class="text-rose-500">*</span></Label>
                            <Input type="number" v-model="editForm.price" required />
                        </div>
                        <div class="grid gap-1.5">
                            <div class="flex items-center justify-between">
                                <Label class="font-semibold text-slate-700 dark:text-slate-300">
                                    Đặc điểm & Hương vị món ăn <span class="text-rose-500 font-bold">*</span>
                                </Label>
                                <span class="text-[10px] text-rose-500 font-medium bg-rose-50 dark:bg-rose-950/40 px-1.5 py-0.5 rounded-md">Bắt buộc</span>
                            </div>
                            <textarea v-model="editForm.description" rows="3" required
                                placeholder="Mô tả hương vị (chua cay, béo ngậy, ngọt dịu...) để nhân viên dễ tư vấn khách."
                                class="w-full rounded-xl border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:border-rose-500 transition-all duration-150" />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="editingProduct = null">Hủy</Button>
                            <Button type="submit" class="bg-indigo-600 text-white" :disabled="editForm.processing">
                                {{ editForm.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="deletingProduct" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
            <Card class="max-w-sm w-full animate-in fade-in zoom-in-95 duration-150">
                <CardContent class="pt-6 text-center space-y-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-950 mx-auto">
                        <Trash2 class="size-7 text-rose-600" />
                    </div>
                    <div>
                        <p class="font-semibold text-sm">Xóa món ăn này?</p>
                        <p class="text-xs text-muted-foreground mt-1">"{{ deletingProduct.name }}" sẽ bị xóa vĩnh viễn khỏi thực đơn.</p>
                    </div>
                    <div class="flex justify-center gap-3">
                        <Button variant="outline" @click="deletingProduct = null">Hủy</Button>
                        <Button class="bg-rose-600 hover:bg-rose-700 text-white" @click="submitDelete">Xóa vĩnh viễn</Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Left: Categories -->
            <div class="lg:col-span-1 flex flex-col gap-4">
                <Card class="shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-bold">Nhóm Món Ăn</CardTitle>
                        <CardDescription class="text-[11px]">Cơ cấu thực đơn</CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2">
                        <!-- All filter -->
                        <button
                            @click="selectedCategory = ''"
                            class="p-3 rounded-xl border text-xs text-left transition-colors"
                            :class="selectedCategory === '' ? 'border-rose-300 bg-rose-50 dark:bg-rose-950/30 dark:border-rose-800' : 'border-slate-100 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-900 hover:border-rose-200'"
                        >
                            <p class="font-bold text-slate-800 dark:text-slate-200">Tất cả món</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">{{ products.length }} món</p>
                        </button>
                        <div v-if="categories.length" class="flex flex-col gap-1.5">
                            <div
                                v-for="cat in categories" :key="cat.id"
                                @click="selectedCategory = selectedCategory === cat.id ? '' : cat.id"
                                class="p-3 rounded-xl border cursor-pointer transition-colors text-xs group/cat relative"
                                :class="selectedCategory === cat.id ? 'border-rose-300 bg-rose-50 dark:bg-rose-950/30 dark:border-rose-800' : 'border-slate-100 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-900 hover:border-rose-200'"
                            >
                                <div class="flex justify-between items-start gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-slate-800 dark:text-slate-200">{{ cat.name }}</p>
                                        <p class="text-[10px] text-slate-400 mt-0.5 line-clamp-1">{{ cat.description ?? 'Không có mô tả.' }}</p>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <span class="text-[10px] bg-slate-200 dark:bg-slate-800 px-2 py-0.5 rounded-full font-semibold">
                                            {{ products.filter(p => p.category?.id === cat.id).length }}
                                        </span>
                                        <button
                                            v-if="products.filter(p => p.category?.id === cat.id).length === 0"
                                            @click.stop="router.delete(`/product-categories/${cat.id}`)"
                                            class="p-1 rounded-md hover:bg-rose-100 dark:hover:bg-rose-950/40 text-rose-500 opacity-0 group-hover/cat:opacity-100 transition-opacity"
                                            title="Xóa nhóm (chỉ xóa được nhóm rỗng)"
                                        >
                                            <Trash2 class="size-3" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-6 text-slate-400 text-xs">
                            <AlertCircle class="size-6 text-slate-300 mx-auto mb-1" />
                            Chưa có nhóm món nào.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right: Products -->
            <div class="lg:col-span-3">
                <Card class="shadow-sm h-full">
                    <CardHeader class="pb-3 border-b">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <CardTitle class="text-base">
                                    Danh sách món ăn
                                    <span class="text-muted-foreground font-normal text-sm ml-1">({{ filteredProducts.length }}/{{ products.length }})</span>
                                </CardTitle>
                                <CardDescription>Quét mã QR và hóa đơn sẽ đồng bộ với thực đơn này.</CardDescription>
                            </div>
                            <!-- Search -->
                            <div class="relative shrink-0 w-52">
                                <Search class="absolute left-3 top-2.5 size-3.5 text-muted-foreground" />
                                <Input v-model="searchQuery" placeholder="Tìm món ăn..." class="pl-9 h-9 text-xs" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="filteredProducts.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div
                                v-for="p in filteredProducts" :key="p.id"
                                class="p-4 flex items-center justify-between hover:bg-slate-50/50 dark:hover:bg-slate-900/40 transition-colors group"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="size-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center font-bold text-xs text-slate-500">
                                        {{ p.name.substring(0,2).toUpperCase() }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-sm text-slate-800 dark:text-slate-200">{{ p.name }}</h4>
                                            <span class="text-[9px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-400">
                                                {{ p.category?.name ?? 'Chưa gán' }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-400 mt-1">{{ p.code }} · {{ p.description ?? 'Không có mô tả.' }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="text-right">
                                        <p class="font-mono font-bold text-sm text-rose-600 dark:text-rose-400">{{ formatCurrency(p.price) }}</p>
                                        <span
                                            class="text-[9px] font-bold px-1.5 py-0.5 rounded-md mt-1 inline-block"
                                            :class="p.is_available
                                                ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950 dark:text-emerald-400'
                                                : 'text-slate-500 bg-slate-100 dark:bg-slate-800 dark:text-slate-400'"
                                        >
                                            {{ p.is_available ? 'Đang bán' : 'Tạm ngưng' }}
                                        </span>
                                    </div>
                                    <!-- Action buttons (hiện khi hover) -->
                                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button
                                            @click="toggleAvailability(p)"
                                            class="p-1.5 rounded-lg transition-colors"
                                            :class="p.is_available
                                                ? 'hover:bg-amber-50 dark:hover:bg-amber-950/40 text-amber-600'
                                                : 'hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-emerald-600'"
                                            :title="p.is_available ? 'Tạm ngưng bán' : 'Mở bán lại'"
                                        >
                                            <ToggleRight v-if="p.is_available" class="size-4" />
                                            <ToggleLeft v-else class="size-4" />
                                        </button>
                                        <button
                                            @click="openEditModal(p)"
                                            class="p-1.5 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-950/40 text-indigo-600 transition-colors"
                                            title="Sửa món ăn"
                                        >
                                            <Pencil class="size-3.5" />
                                        </button>
                                        <button
                                            @click="confirmDelete(p)"
                                            class="p-1.5 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 text-rose-600 transition-colors"
                                            title="Xóa món ăn"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Empty state: no results from search/filter -->
                        <div v-else-if="products.length" class="flex flex-col items-center justify-center p-10 text-center">
                            <Search class="size-10 text-slate-300 mb-3" />
                            <p class="text-sm font-semibold">Không tìm thấy món ăn nào</p>
                            <p class="mt-1 text-xs text-muted-foreground">Thử thay đổi từ khóa hoặc chọn nhóm khác.</p>
                            <Button class="mt-3" size="sm" variant="outline" @click="searchQuery = ''; selectedCategory = ''">Xóa bộ lọc</Button>
                        </div>
                        <!-- Empty state: no products at all -->
                        <div v-else class="flex flex-col items-center justify-center p-14 text-center">
                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground/40 mb-4">
                                <UtensilsCrossed class="size-9" />
                            </div>
                            <p class="text-base font-semibold text-foreground">Thực đơn trống</p>
                            <p class="mt-1.5 max-w-xs text-sm text-muted-foreground">Thêm món ăn đầu tiên để khách hàng có thể đặt hàng qua QR.</p>
                            <Button class="mt-4" size="sm" @click="showAddProduct = true">
                                <Plus class="size-4 mr-1.5" />Thêm món ăn đầu tiên
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
