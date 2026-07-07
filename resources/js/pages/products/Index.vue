<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    UtensilsCrossed, Plus, FolderPlus, Search,
    CheckCircle2, AlertCircle, Pencil, Trash2, X, ChevronDown, ChevronUp,
    ToggleLeft, ToggleRight, Brain, Sparkles, AlertTriangle, RefreshCw,
    Coffee, Dessert, Beef, Grid, LayoutGrid, Check, Store, HelpCircle,
    ArrowRight, Download, Clock, User, Shield, Star
} from 'lucide-vue-next';
import { computed, ref, onMounted, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Category = { id: number; name: string; description: string | null };
type Product   = { id: number; code: string; name: string; price: number; description: string | null; category: Category | null; is_available: boolean; image_url: string | null };

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
const bcgData        = ref<any[]>([]);
const selectedBcgProduct = ref<any>(null);
const insightTab     = ref('matrix'); // 'matrix' | 'alerts' | 'combos'
const isBcgScatterView = ref(true);

const stars = computed(() => bcgData.value.filter(p => p.quadrant === 'star'));
const plowhorses = computed(() => bcgData.value.filter(p => p.quadrant === 'plowhorse'));
const puzzles = computed(() => bcgData.value.filter(p => p.quadrant === 'puzzle'));
const dogs = computed(() => bcgData.value.filter(p => p.quadrant === 'dog'));

async function loadInsights() {
    if (insightsLoaded.value) {
        showInsights.value = !showInsights.value;

        return; 
    }

    insightsLoading.value = true;
    showInsights.value    = true;

    try {
        const res = await fetch('/api/products/menu-insights');
        const data = await res.json();
        insights.value = data.insights ?? [];
        bcgData.value = data.bcg ?? [];

        if (bcgData.value.length > 0) {
            selectedBcgProduct.value = bcgData.value[0];
        }

        insightsLoaded.value = true;
    } catch (e) {
        insights.value = [];
        bcgData.value = [];
    } finally {
        insightsLoading.value = false;
    }
}

// ── Market Basket Analysis & Combo Creator ────────────────────────────────────
const rules = ref<any[]>([]);
const rulesLoading = ref(false);
const rulesLoaded = ref(false);

async function loadBasketAnalysis() {
    if (rulesLoaded.value) {
return;
}

    rulesLoading.value = true;

    try {
        const res = await fetch('/api/promotions/basket-analysis');
        const data = await res.json();
        rules.value = data.rules ?? [];
        rulesLoaded.value = true;
    } catch (e) {
        console.error(e);
    } finally {
        rulesLoading.value = false;
    }
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
    notes: ''
});

const openComboModal = (rule: any) => {
    const prodA = props.products.find(p => p.name.toLowerCase() === rule.item_a.toLowerCase());
    const prodB = props.products.find(p => p.name.toLowerCase() === rule.item_b.toLowerCase());
    
    if (!prodA || !prodB) {
        toast.error('Không tìm thấy sản phẩm tương ứng trong thực đơn để tạo combo.');

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
    comboForm.combo_price = Math.max(0, Math.round((sumPrice * 0.88) / 1000) * 1000);
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
        }
    });
};

const getDotPosition = (p: any) => {
    const maxQty = Math.max(...bcgData.value.map(x => x.total_qty), 1);
    const maxMargin = Math.max(...bcgData.value.map(x => x.margin), 1);
    const minMargin = Math.min(...bcgData.value.map(x => x.margin), 0);
    
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
    name: '', price: '', description: '', image: null as File | null
});

const editForm = useForm({
    name: '', price: '', category_id: '', description: '', image: null as File | null
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

const currentPage = ref(1);
const itemsPerPage = 10;
const totalPages = computed(() => Math.ceil(filteredProducts.value.length / itemsPerPage));
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

watch([selectedCategory, searchQuery], () => {
    currentPage.value = 1;
});

// ── Category & Product Helpers ───────────────────────────────────────────────
function getCategoryTheme(catName: string | null) {
    const name = (catName ?? '').toLowerCase();

    if (name.includes('đồ uống') || name.includes('uống') || name.includes('nước') || name.includes('bia') || name.includes('cà phê') || name.includes('sinh tố')) {
        return {
            icon: Coffee,
            bg: 'bg-blue-500/10 text-blue-500 dark:bg-blue-950/30 dark:text-blue-400',
            dot: 'bg-blue-500'
        };
    }

    if (name.includes('tráng miệng') || name.includes('bánh') || name.includes('chè') || name.includes('kem')) {
        return {
            icon: Dessert,
            bg: 'bg-pink-500/10 text-pink-500 dark:bg-pink-950/30 dark:text-pink-400',
            dot: 'bg-pink-500'
        };
    }

    if (name.includes('món chính') || name.includes('cơm') || name.includes('phở') || name.includes('bún') || name.includes('thịt') || name.includes('mỳ')) {
        return {
            icon: Beef,
            bg: 'bg-orange-500/10 text-orange-500 dark:bg-orange-950/30 dark:text-orange-400',
            dot: 'bg-orange-500'
        };
    }

    return {
        icon: UtensilsCrossed,
        bg: 'bg-slate-500/10 text-slate-500 dark:bg-slate-800/60 dark:text-slate-400',
        dot: 'bg-slate-500'
    };
}

// ── Handlers ──────────────────────────────────────────────────────────────────
const submitCategory = () => {
    categoryForm.post('/product-categories', {
        onSuccess: () => {
            categoryForm.reset(); 
            showAddCategory.value = false; 
        }
    });
};

const submitProduct = () => {
    productForm.post('/products', {
        onSuccess: () => {
            productForm.reset(); 
            showAddProduct.value = false; 
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

    router.post(`/products/${editingProduct.value.id}`, {
        _method: 'PATCH',
        name: editForm.name,
        price: editForm.price,
        category_id: editForm.category_id,
        description: editForm.description,
        image: editForm.image,
    }, {
        onSuccess: () => {
            editingProduct.value = null;
            editForm.reset();
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

    <div class="flex flex-col gap-6 p-4 lg:p-8 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-border/80 pb-6">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 dark:text-rose-400 ring-4 ring-rose-500/5">
                    <UtensilsCrossed class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-foreground flex items-center gap-2">
                        Thực Đơn & Món Ăn
                    </h1>
                    <p class="text-sm text-muted-foreground">Quản lý cấu trúc thực đơn, nhóm món, giá bán sản phẩm thực tế của quán.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <!-- AI Insights button -->
                <Button @click="loadInsights" variant="outline"
                    class="h-10 text-xs border-indigo-200 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/20 font-bold flex items-center gap-1.5 rounded-xl cursor-pointer">
                    <Brain class="size-4" />
                    AI Phân tích Menu
                    <component :is="showInsights ? ChevronUp : ChevronDown" class="size-3.5" />
                </Button>
                <Button id="btn-add-category" @click="showAddCategory = true" variant="outline" class="h-10 text-xs border-border rounded-xl font-bold cursor-pointer">
                    <FolderPlus class="size-4 mr-2 text-indigo-600" />Thêm nhóm món
                </Button>
                <Button id="btn-add-product" @click="showAddProduct = true" class="h-10 text-xs bg-rose-650 hover:bg-rose-700 text-white font-bold rounded-xl cursor-pointer shadow-sm">
                    <Plus class="size-4 mr-2" />Thêm món ăn
                </Button>
            </div>
        </div>

        <!-- AI Menu Insights Accordion -->
        <div v-if="showInsights" class="rounded-2xl border border-indigo-200 dark:border-indigo-800/40 bg-indigo-50/50 dark:bg-indigo-950/10 overflow-hidden shadow-lg animate-in fade-in duration-200">
            <!-- Header and Tab Selection -->
            <div class="px-5 py-3 border-b border-indigo-100 dark:border-indigo-800/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-muted/20">
                <div class="flex items-center gap-2">
                    <Sparkles class="size-4 text-indigo-500 animate-pulse" />
                    <span class="text-sm font-bold text-indigo-700 dark:text-indigo-300">AI Kỹ nghệ thực đơn (Menu Engineering)</span>
                </div>
                <div class="flex items-center gap-1 bg-muted p-0.5 rounded-lg border border-border text-xs self-start sm:self-auto">
                    <button 
                        type="button"
                        @click="insightTab = 'matrix'"
                        :class="['px-3 py-1.5 rounded-md font-bold transition-all cursor-pointer', insightTab === 'matrix' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground']"
                    >
                        Ma trận Boston (BCG)
                    </button>
                    <button 
                        type="button"
                        @click="insightTab = 'combos'"
                        :class="['px-3 py-1.5 rounded-md font-bold transition-all cursor-pointer', insightTab === 'combos' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground']"
                    >
                        Gợi ý Combo
                    </button>
                    <button 
                        type="button"
                        @click="insightTab = 'alerts'"
                        :class="['px-3 py-1.5 rounded-md font-bold transition-all cursor-pointer', insightTab === 'alerts' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground']"
                    >
                        Cảnh báo
                    </button>
                </div>
            </div>

            <!-- Tab: BCG Matrix -->
            <div v-if="insightTab === 'matrix'" class="p-5 grid grid-cols-1 lg:grid-cols-3 gap-6 animate-in fade-in duration-200">
                <!-- Left: Matrix View Toggle and the Visualizations -->
                <div class="lg:col-span-2 space-y-4">
                    <!-- View Toggle Bar -->
                    <div class="flex items-center justify-between border-b border-border/80 pb-3">
                        <span class="text-xs font-bold text-slate-650 dark:text-slate-400">
                            Bố cục hiển thị Ma trận BCG:
                        </span>
                        <div class="inline-flex rounded-lg bg-slate-100 p-0.5 dark:bg-slate-800 text-[10px]">
                            <button 
                                type="button"
                                @click="isBcgScatterView = true"
                                :class="['inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 font-extrabold transition cursor-pointer', isBcgScatterView ? 'bg-white shadow-xs text-slate-900 dark:bg-slate-900 dark:text-white' : 'text-slate-500 hover:text-slate-900']"
                            >
                                🎯 Sơ đồ ma trận 2D
                            </button>
                            <button 
                                type="button"
                                @click="isBcgScatterView = false"
                                :class="['inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 font-extrabold transition cursor-pointer', !isBcgScatterView ? 'bg-white shadow-xs text-slate-900 dark:bg-slate-900 dark:text-white' : 'text-slate-500 hover:text-slate-900']"
                            >
                                📋 Danh sách nhóm
                            </button>
                        </div>
                    </div>

                    <!-- 2D SCATTER QUADRANT MAP -->
                    <div 
                        v-if="isBcgScatterView" 
                        class="relative w-full h-[340px] rounded-2xl border border-border bg-card overflow-hidden shadow-inner"
                    >
                        <!-- Empty State inside Canvas -->
                        <div v-if="bcgData.length === 0" class="absolute inset-0 flex flex-col items-center justify-center text-center text-muted-foreground p-6">
                            <Sparkles class="size-8 opacity-20 text-indigo-500 mb-2" />
                            <p class="font-bold text-xs">Không có dữ liệu món ăn trong ma trận</p>
                        </div>

                        <template v-else>
                            <!-- Quadrant Background Glows -->
                            <div class="absolute top-0 right-1/2 bottom-1/2 left-0 bg-purple-500/[0.02] dark:bg-purple-500/[0.01] pointer-events-none"></div>
                            <div class="absolute top-0 left-1/2 bottom-1/2 right-0 bg-emerald-500/[0.03] dark:bg-emerald-500/[0.01] pointer-events-none"></div>
                            <div class="absolute bottom-0 right-1/2 top-1/2 left-0 bg-rose-500/[0.03] dark:bg-rose-500/[0.01] pointer-events-none"></div>
                            <div class="absolute bottom-0 left-1/2 top-1/2 right-0 bg-amber-500/[0.03] dark:bg-amber-500/[0.01] pointer-events-none"></div>

                            <!-- Horizontal Axis (Margin median line) -->
                            <div class="absolute top-1/2 left-0 right-0 h-0.5 border-t border-dashed border-border pointer-events-none" />
                            <span class="absolute right-2 top-[calc(50%-18px)] text-[9px] font-bold text-muted-foreground bg-background/90 px-1.5 py-0.5 rounded border border-border/80 pointer-events-none">
                                Margin trung vị ({{ bcgData[0]?.median_margin }}%)
                            </span>

                            <!-- Vertical Axis (Qty median line) -->
                            <div class="absolute left-1/2 top-0 bottom-0 w-0.5 border-l border-dashed border-border pointer-events-none" />
                            <span class="absolute top-2 left-[calc(50%+8px)] text-[9px] font-bold text-muted-foreground bg-background/90 px-1.5 py-0.5 rounded border border-border/80 pointer-events-none">
                                Sản lượng trung vị ({{ bcgData[0]?.median_qty }})
                            </span>

                            <!-- Quadrant Labels -->
                            <div class="absolute top-3 left-4 text-[9px] font-black text-purple-500/80 uppercase tracking-widest pointer-events-none select-none">
                                🧩 Puzzles (Lợi nhuận cao - Bán chậm)
                            </div>
                            <div class="absolute top-3 right-4 text-[9px] font-black text-emerald-500/80 uppercase tracking-widest pointer-events-none select-none text-right">
                                ⭐ Stars (Chủ lực - Bán chạy & Lợi cao)
                            </div>
                            <div class="absolute bottom-3 left-4 text-[9px] font-black text-rose-500/80 uppercase tracking-widest pointer-events-none select-none">
                                🐶 Dogs (Yếu kém - Bán chậm & Lợi thấp)
                            </div>
                            <div class="absolute bottom-3 right-4 text-[9px] font-black text-amber-500/80 uppercase tracking-widest pointer-events-none select-none text-right">
                                🐎 Plowhorses (Bò sữa - Bán chạy & Lợi thấp)
                            </div>

                            <!-- Points -->
                            <button 
                                v-for="p in bcgData" 
                                :key="p.product_id"
                                type="button"
                                @click="selectedBcgProduct = p"
                                :style="{ left: `${getDotPosition(p).x}%`, top: `${getDotPosition(p).y}%` }"
                                class="absolute -translate-x-1/2 -translate-y-1/2 w-4 h-4 rounded-full border border-card shadow-md flex items-center justify-center hover:scale-125 transition-transform duration-200 cursor-pointer group z-20"
                                :class="[
                                    p.quadrant === 'star' ? 'bg-emerald-500' :
                                    p.quadrant === 'plowhorse' ? 'bg-amber-500' :
                                    p.quadrant === 'puzzle' ? 'bg-purple-500' :
                                                              'bg-rose-500',
                                    selectedBcgProduct?.product_id === p.product_id ? 'ring-4 ring-indigo-500/30 dark:ring-indigo-400/40 ring-offset-1 scale-120 font-bold' : ''
                                ]"
                            >
                                <!-- Selected Dot Ping -->
                                <span 
                                    v-if="selectedBcgProduct?.product_id === p.product_id"
                                    class="absolute inset-0 rounded-full animate-ping opacity-75"
                                    :class="[
                                        p.quadrant === 'star' ? 'bg-emerald-400' :
                                        p.quadrant === 'plowhorse' ? 'bg-amber-400' :
                                        p.quadrant === 'puzzle' ? 'bg-purple-400' : 'bg-rose-400'
                                    ]"
                                />
                                
                                <!-- Tooltip on hover -->
                                <span class="absolute bottom-6 left-1/2 -translate-x-1/2 scale-0 group-hover:scale-100 transition-all bg-slate-900 dark:bg-slate-800 text-white text-[10px] rounded-lg px-2.5 py-1.5 font-bold whitespace-nowrap z-50 shadow-lg pointer-events-none flex flex-col gap-0.5 items-center">
                                    <span class="font-extrabold">{{ p.name }}</span>
                                    <span class="text-[8px] opacity-75 font-normal">Margin: {{ p.margin }}% · Đã bán: {{ p.total_qty }} món</span>
                                </span>
                            </button>
                        </template>
                    </div>

                    <!-- QUADRANTS LIST GRID -->
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Top-Left: Puzzles -->
                        <div class="bg-purple-50/20 dark:bg-purple-950/5 border border-purple-200/50 rounded-xl p-4 min-h-[160px] flex flex-col justify-between hover:shadow-xs transition-shadow">
                            <div>
                                <div class="flex items-center justify-between border-b border-purple-100 dark:border-purple-900/40 pb-2 mb-3">
                                    <h5 class="text-xs font-bold text-purple-700 dark:text-purple-400 uppercase tracking-wider flex items-center gap-1.5">
                                        <span>🧩</span> Puzzles (Lợi nhuận cao)
                                    </h5>
                                    <span class="text-[9px] text-muted-foreground">Volume thấp, Margin cao</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button 
                                        type="button"
                                        v-for="p in puzzles" 
                                        :key="p.product_id"
                                        @click="selectedBcgProduct = p"
                                        :class="['px-2.5 py-1 rounded-lg text-xs font-bold border transition-all cursor-pointer', selectedBcgProduct?.product_id === p.product_id ? 'bg-purple-650 text-white border-purple-650 scale-105' : 'bg-background hover:bg-purple-50/50 dark:hover:bg-purple-950/30 text-purple-750 border-purple-200']"
                                    >
                                        {{ p.name }}
                                    </button>
                                    <p v-if="puzzles.length === 0" class="text-[11px] text-muted-foreground italic py-2">Chưa có món ăn phân loại này.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Top-Right: Stars -->
                        <div class="bg-emerald-50/20 dark:bg-emerald-950/5 border border-emerald-200/50 rounded-xl p-4 min-h-[160px] flex flex-col justify-between hover:shadow-xs transition-shadow">
                            <div>
                                <div class="flex items-center justify-between border-b border-emerald-100 dark:border-emerald-900/40 pb-2 mb-3">
                                    <h5 class="text-xs font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider flex items-center gap-1.5">
                                        <span>⭐</span> Stars (Ngôi sao chủ lực)
                                    </h5>
                                    <span class="text-[9px] text-muted-foreground">Volume cao, Margin cao</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button 
                                        type="button"
                                        v-for="p in stars" 
                                        :key="p.product_id"
                                        @click="selectedBcgProduct = p"
                                        :class="['px-2.5 py-1 rounded-lg text-xs font-bold border transition-all cursor-pointer', selectedBcgProduct?.product_id === p.product_id ? 'bg-emerald-600 text-white border-emerald-600 scale-105' : 'bg-background hover:bg-emerald-50/50 dark:hover:bg-emerald-950/30 text-emerald-750 border-emerald-200']"
                                    >
                                        {{ p.name }}
                                    </button>
                                    <p v-if="stars.length === 0" class="text-[11px] text-muted-foreground italic py-2">Chưa có món ăn phân loại này.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom-Left: Dogs -->
                        <div class="bg-rose-50/20 dark:bg-rose-950/5 border border-rose-200/50 rounded-xl p-4 min-h-[160px] flex flex-col justify-between hover:shadow-xs transition-shadow">
                            <div>
                                <div class="flex items-center justify-between border-b border-rose-100 dark:border-rose-900/40 pb-2 mb-3">
                                    <h5 class="text-xs font-bold text-rose-700 dark:text-rose-400 uppercase tracking-wider flex items-center gap-1.5">
                                        <span>🐶</span> Dogs (Yếu kém / Cần lọc)
                                    </h5>
                                    <span class="text-[9px] text-muted-foreground">Volume thấp, Margin thấp</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button 
                                        type="button"
                                        v-for="p in dogs" 
                                        :key="p.product_id"
                                        @click="selectedBcgProduct = p"
                                        :class="['px-2.5 py-1 rounded-lg text-xs font-bold border transition-all cursor-pointer', selectedBcgProduct?.product_id === p.product_id ? 'bg-rose-600 text-white border-rose-600 scale-105' : 'bg-background hover:bg-rose-50/50 dark:hover:bg-rose-950/30 text-rose-750 border-rose-200']"
                                    >
                                        {{ p.name }}
                                    </button>
                                    <p v-if="dogs.length === 0" class="text-[11px] text-muted-foreground italic py-2">Chưa có món ăn phân loại này.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom-Right: Plowhorses -->
                        <div class="bg-amber-50/20 dark:bg-amber-950/5 border border-amber-200/50 rounded-xl p-4 min-h-[160px] flex flex-col justify-between hover:shadow-xs transition-shadow">
                            <div>
                                <div class="flex items-center justify-between border-b border-amber-100 dark:border-amber-900/40 pb-2 mb-3">
                                    <h5 class="text-xs font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                                        <span>🐎</span> Plowhorses (Bò sữa)
                                    </h5>
                                    <span class="text-[9px] text-muted-foreground">Volume cao, Margin thấp</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button 
                                        type="button"
                                        v-for="p in plowhorses" 
                                        :key="p.product_id"
                                        @click="selectedBcgProduct = p"
                                        :class="['px-2.5 py-1 rounded-lg text-xs font-bold border transition-all cursor-pointer', selectedBcgProduct?.product_id === p.product_id ? 'bg-amber-600 text-white border-amber-600 scale-105' : 'bg-background hover:bg-amber-50/50 dark:hover:bg-amber-950/30 text-amber-750 border-amber-200']"
                                    >
                                        {{ p.name }}
                                    </button>
                                    <p v-if="plowhorses.length === 0" class="text-[11px] text-muted-foreground italic py-2">Chưa có món ăn phân loại này.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Detail Analysis (Right) -->
                <Card class="bg-card border shadow-xs flex flex-col justify-between">
                    <div>
                        <CardHeader class="pb-3 border-b border-border bg-muted/10">
                            <CardTitle class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                                Chi tiết đề xuất món ăn
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-4 space-y-4">
                            <div v-if="selectedBcgProduct" class="space-y-4">
                                <div>
                                    <h4 class="text-base font-extrabold text-foreground leading-tight">{{ selectedBcgProduct.name }}</h4>
                                    <div class="flex items-center gap-2 mt-2">
                                        <Badge :class="[
                                            'text-[9px] font-extrabold px-2 py-0.5 rounded-full border-0 tracking-wider',
                                            selectedBcgProduct.quadrant === 'star' ? 'bg-emerald-500/10 text-emerald-500' :
                                            selectedBcgProduct.quadrant === 'plowhorse' ? 'bg-amber-500/10 text-amber-500' :
                                            selectedBcgProduct.quadrant === 'puzzle' ? 'bg-purple-500/10 text-purple-500' :
                                                                                       'bg-rose-500/10 text-rose-500'
                                        ]">
                                            {{
                                                selectedBcgProduct.quadrant === 'star' ? '⭐ Ngôi sao' :
                                                selectedBcgProduct.quadrant === 'plowhorse' ? '🐎 Bò sữa' :
                                                selectedBcgProduct.quadrant === 'puzzle' ? '🧩 Câu đố' :
                                                                                            '🐶 Thú cưng'
                                            }}
                                        </Badge>
                                    </div>
                                </div>

                                <!-- Financial Metrics Grid -->
                                <div class="grid grid-cols-2 gap-3 text-[10px] bg-muted/20 p-3 rounded-xl border border-border/60">
                                    <div>
                                        <p class="text-muted-foreground font-semibold">Doanh thu bán</p>
                                        <p class="font-extrabold text-foreground mt-0.5 text-xs">
                                            {{ formatCurrency(selectedBcgProduct.total_revenue) }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-muted-foreground font-semibold">Sản lượng (30D)</p>
                                        <p class="font-extrabold text-foreground mt-0.5 text-xs">
                                            {{ selectedBcgProduct.total_qty }} món (Median: {{ selectedBcgProduct.median_qty }})
                                        </p>
                                    </div>
                                    <div class="border-t border-border/80 pt-2 mt-1">
                                        <p class="text-muted-foreground font-semibold">Giá vốn / Giá bán</p>
                                        <p class="font-extrabold text-foreground mt-0.5 text-xs">
                                            {{ formatCurrency(selectedBcgProduct.cost_price) }} / {{ formatCurrency(selectedBcgProduct.price) }}
                                        </p>
                                    </div>
                                    <div class="border-t border-border/80 pt-2 mt-1">
                                        <p class="text-muted-foreground font-semibold">Lợi nhuận %</p>
                                        <p class="font-black text-indigo-500 dark:text-indigo-400 mt-0.5 text-xs">
                                            {{ selectedBcgProduct.margin }}% (Median: {{ selectedBcgProduct.median_margin }}%)
                                        </p>
                                    </div>
                                </div>

                                <!-- AI Recommendation Box -->
                                <div class="bg-indigo-500/5 border border-indigo-500/10 dark:border-indigo-500/20 rounded-xl p-4 flex gap-3">
                                    <Sparkles class="w-5 h-5 text-indigo-500 shrink-0 mt-0.5" />
                                    <div class="space-y-1">
                                        <h5 class="text-xs font-bold text-indigo-550 dark:text-indigo-400">Khuyến nghị AI</h5>
                                        <p class="text-[11px] text-slate-650 dark:text-slate-350 leading-relaxed">
                                            {{ selectedBcgProduct.ai_recommendation }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-10 text-muted-foreground text-xs">
                                Chọn một món ăn trên sơ đồ ma trận để phân tích.
                            </div>
                        </CardContent>
                    </div>
                </Card>
            </div>

            <!-- Tab: Gợi ý Combo (Market Basket Analysis) -->
            <div v-if="insightTab === 'combos'" class="p-5 space-y-4 animate-in fade-in duration-200">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-indigo-100 dark:border-indigo-800/30 pb-3 mb-2">
                    <div>
                        <h4 class="text-xs font-extrabold text-indigo-750 dark:text-indigo-400 uppercase tracking-wider">
                            Gợi Ý Thiết Lập Combo Món Ăn Bán Kèm
                        </h4>
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            Cặp sản phẩm xuất hiện cùng nhau nhiều nhất trong các giao dịch thực tế (Apriori Market Basket).
                        </p>
                    </div>
                    <span v-if="rules.length" class="text-[9px] px-2.5 py-0.5 rounded-full bg-indigo-500/15 text-indigo-550 dark:text-indigo-400 font-bold self-start sm:self-auto">
                        Tự động cập nhật
                    </span>
                </div>

                <!-- Loading -->
                <div v-if="rulesLoading" class="flex items-center justify-center py-16 gap-2 text-indigo-500">
                    <RefreshCw class="size-5 animate-spin" />
                    <span class="text-xs font-bold">AI đang quét dữ liệu hóa đơn...</span>
                </div>

                <template v-else>
                    <!-- Empty State -->
                    <div v-if="rules.length === 0" class="flex flex-col items-center py-12 text-center text-slate-400 bg-card rounded-2xl border border-dashed border-border/80">
                        <CheckCircle2 class="size-8 text-indigo-400 mb-2" />
                        <p class="text-xs font-bold text-foreground/80">Chưa tìm thấy gợi ý Combo nào</p>
                        <p class="text-[10px] mt-1 max-w-xs text-muted-foreground">Nhà hàng cần tích lũy thêm các đơn hàng chứa từ 2 món trở lên để chạy phân tích giỏ hàng.</p>
                    </div>

                    <!-- Grid -->
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div 
                            v-for="(rule, idx) in rules.slice(0, 10)" 
                            :key="idx"
                            class="rounded-xl border border-border bg-card p-4 hover:shadow-md hover:border-indigo-500/35 transition duration-150 flex flex-col justify-between gap-4 group"
                        >
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-[9px] font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                                        <span class="w-5 h-5 rounded-full bg-muted flex items-center justify-center font-bold text-[9px] text-foreground">{{ idx + 1 }}</span>
                                        Liên kết mua kèm
                                    </span>
                                    <Badge variant="secondary" class="font-mono text-[9px] font-black border-0 bg-emerald-500/10 text-emerald-500">
                                        Lift: {{ rule.lift }}
                                    </Badge>
                                </div>

                                <!-- A -> B Visual -->
                                <div class="bg-muted/30 p-2.5 rounded-lg flex items-center justify-between text-xs gap-3 border border-border/40">
                                    <div class="text-center flex-1 min-w-0">
                                        <p class="text-[9px] text-muted-foreground font-semibold">Khách gọi món</p>
                                        <p class="font-bold text-foreground truncate mt-0.5">{{ rule.item_a }}</p>
                                    </div>
                                    <Sparkles class="size-3.5 text-indigo-500 shrink-0" />
                                    <div class="text-center flex-1 min-w-0">
                                        <p class="text-[9px] text-muted-foreground font-semibold">Thường mua kèm</p>
                                        <p class="font-bold text-foreground truncate mt-0.5">{{ rule.item_b }}</p>
                                    </div>
                                </div>

                                <!-- Support and Confidence -->
                                <div class="grid grid-cols-2 gap-2 text-center text-[10px]">
                                    <div class="bg-muted/40 p-2 rounded-lg">
                                        <p class="text-muted-foreground font-semibold">Độ tin cậy (Confidence)</p>
                                        <p class="font-black text-foreground mt-0.5">{{ Math.round(rule.confidence * 100) }}%</p>
                                    </div>
                                    <div class="bg-muted/40 p-2 rounded-lg">
                                        <p class="text-muted-foreground font-semibold">Số lần gọi chung</p>
                                        <p class="font-black text-foreground mt-0.5">{{ rule.co_occurrence }} đơn</p>
                                    </div>
                                </div>
                            </div>

                            <Button 
                                size="sm"
                                type="button"
                                class="w-full h-8 text-[10px] bg-slate-800 hover:bg-slate-900 text-white font-bold group-hover:bg-indigo-600 transition-colors rounded-xl cursor-pointer"
                                @click="openComboModal(rule)"
                            >
                                <Plus class="size-3 mr-1" /> Thiết lập Combo đề xuất
                            </Button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Tab: Existing alerts -->
            <div v-if="insightTab === 'alerts'" class="divide-y divide-indigo-100 dark:divide-indigo-800/30 animate-in fade-in duration-200">
                <div v-if="!insights.length" class="flex flex-col items-center py-12 text-center text-slate-400">
                    <CheckCircle2 class="size-8 text-emerald-400 mb-2" />
                    <p class="text-sm font-semibold text-foreground">Menu đang hoạt động tốt!</p>
                    <p class="text-xs text-muted-foreground mt-1">Không phát hiện vấn đề cần cảnh báo trong 30 ngày qua.</p>
                </div>

                <div v-else>
                    <div v-for="(item, i) in insights" :key="i"
                        :class="[
                            'flex items-start gap-3 px-4 py-3.5 text-xs transition-colors hover:bg-indigo-50/20',
                            item.severity === 'critical' ? 'bg-rose-500/5 dark:bg-rose-950/10' :
                            item.severity === 'warning'  ? 'bg-amber-500/5 dark:bg-amber-950/10' : ''
                        ]"
                    >
                        <span class="text-base shrink-0 mt-0.5">
                            {{ item.severity === 'critical' ? '🔴' : item.severity === 'warning' ? '🟡' : '🔵' }}
                        </span>
                        <div class="flex-1">
                            <p class="font-bold text-foreground" v-html="item.message" />
                            <p class="text-muted-foreground mt-1 flex items-center gap-1">
                                <AlertTriangle class="size-3 text-amber-500 shrink-0" />
                                {{ item.suggestion }}
                            </p>
                        </div>
                        <Badge :class="[
                            'shrink-0 text-[9px] font-bold border rounded-full px-2 py-0.5',
                            item.severity === 'critical' ? 'bg-rose-500/10 text-rose-500 border-rose-500/20' :
                            item.severity === 'warning'  ? 'bg-amber-500/10 text-amber-500 border-amber-500/20' :
                                                            'bg-blue-500/10 text-blue-500 border-blue-500/20'
                        ]">
                            {{ item.value }}{{ item.unit }}
                        </Badge>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Left: Categories -->
            <div class="lg:col-span-1 flex flex-col gap-4">
                <Card class="shadow-sm border-border bg-card rounded-2xl">
                    <CardHeader class="pb-3 border-b border-border/60">
                        <CardTitle class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Nhóm Món Ăn</CardTitle>
                    </CardHeader>
                    <CardContent class="p-3 flex flex-col gap-2">
                        <!-- All filter -->
                        <button
                            @click="selectedCategory = ''"
                            class="p-3 rounded-xl border text-xs text-left transition-colors cursor-pointer w-full"
                            :class="selectedCategory === '' ? 'border-rose-500/35 bg-rose-500/5 font-bold shadow-xs' : 'border-border/60 bg-muted/20 text-muted-foreground hover:border-rose-500/20'"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-foreground">Tất cả món</p>
                                    <p class="text-[10px] text-muted-foreground mt-0.5">{{ products.length }} món ăn</p>
                                </div>
                                <span class="h-6 w-6 rounded-lg bg-muted flex items-center justify-center shrink-0 border text-[10px] font-bold text-foreground/80">
                                    {{ products.length }}
                                </span>
                            </div>
                        </button>
                        
                        <div v-if="categories.length" class="flex flex-col gap-1.5">
                            <div
                                v-for="cat in categories" :key="cat.id"
                                @click="selectedCategory = selectedCategory === cat.id ? '' : cat.id"
                                class="p-3 rounded-xl border cursor-pointer transition-colors text-xs group/cat relative w-full"
                                :class="selectedCategory === cat.id ? 'border-rose-500/35 bg-rose-500/5 font-bold shadow-xs' : 'border-border/60 bg-muted/20 text-muted-foreground hover:border-rose-500/20'"
                            >
                                <div class="flex justify-between items-center gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-foreground">{{ cat.name }}</p>
                                        <p class="text-[10px] text-muted-foreground mt-0.5 truncate">{{ cat.description ?? 'Không có mô tả.' }}</p>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0 z-10">
                                        <span class="h-6 w-6 rounded-lg bg-muted flex items-center justify-center text-[10px] font-bold text-foreground/80 border">
                                            {{ products.filter(p => p.category?.id === cat.id).length }}
                                        </span>
                                        <button
                                            v-if="products.filter(p => p.category?.id === cat.id).length === 0"
                                            @click.stop="router.delete(`/product-categories/${cat.id}`)"
                                            class="p-1 rounded-md hover:bg-rose-500/15 text-rose-500 opacity-0 group-hover/cat:opacity-100 transition-opacity cursor-pointer shrink-0"
                                            title="Xóa nhóm này"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div v-else class="text-center py-8 text-muted-foreground/60 border border-dashed border-border/80 rounded-xl bg-muted/5">
                            <AlertCircle class="size-6 text-muted-foreground/30 mx-auto mb-1.5" />
                            Chưa có nhóm món nào.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right: Products List -->
            <div class="lg:col-span-3">
                <Card class="shadow-sm border-border bg-card rounded-2xl h-full flex flex-col justify-between overflow-hidden">
                    <div>
                        <CardHeader class="pb-3 border-b border-border/60">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <CardTitle class="text-base font-bold flex items-center gap-2">
                                        Danh sách món ăn
                                        <Badge variant="secondary" class="text-[10px] px-2 py-0 border-0 bg-muted text-foreground/80 font-bold shrink-0">
                                            {{ filteredProducts.length }}/{{ products.length }}
                                        </Badge>
                                    </CardTitle>
                                    <CardDescription>Quét mã QR và hóa đơn sẽ đồng bộ với thực đơn này.</CardDescription>
                                </div>
                                <!-- Search -->
                                <div class="relative shrink-0 w-full sm:w-56">
                                    <Search class="absolute left-3 top-2.5 size-3.5 text-muted-foreground" />
                                    <Input v-model="searchQuery" placeholder="Tìm món ăn..." class="pl-9 h-9 text-xs rounded-xl" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div v-if="filteredProducts.length" class="divide-y divide-border/60">
                                <div
                                    v-for="p in paginatedProducts" :key="p.id"
                                    class="p-4 flex items-center justify-between hover:bg-muted/10 transition-colors group gap-4"
                                >
                                    <div class="flex items-start gap-4 min-w-0">
                                        <!-- Thumbnail image / SVG category themed placeholder -->
                                        <div class="size-12 rounded-xl border border-border overflow-hidden shrink-0 flex items-center justify-center font-bold text-xs select-none">
                                            <img v-if="p.image_url" :src="p.image_url" class="h-full w-full object-cover" />
                                            <div v-else :class="['h-full w-full flex flex-col items-center justify-center', getCategoryTheme(p.category?.name ?? null).bg]">
                                                <component :is="getCategoryTheme(p.category?.name ?? null).icon" class="size-5" />
                                            </div>
                                        </div>
                                        
                                        <div class="space-y-1 min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <h4 class="font-extrabold text-sm text-foreground leading-snug truncate max-w-[200px] md:max-w-xs">{{ p.name }}</h4>
                                                <Badge variant="outline" class="text-[9px] font-bold px-2 py-0 border-rose-500/20 bg-rose-500/5 text-rose-500 font-mono shrink-0">
                                                    {{ p.category?.name ?? 'Chưa gán' }}
                                                </Badge>
                                            </div>
                                            <p class="text-xs text-muted-foreground truncate leading-normal">
                                                <span class="font-mono text-[10px] font-bold text-muted-foreground/80 bg-muted px-1 rounded mr-1.5">{{ p.code }}</span>
                                                {{ p.description ?? 'Không có mô tả chi tiết hương vị.' }}
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-4 shrink-0">
                                        <div class="text-right">
                                            <p class="font-mono font-black text-sm text-rose-500 dark:text-rose-400">{{ formatCurrency(p.price) }}</p>
                                            <span
                                                class="text-[9px] font-bold px-2 py-0.5 rounded-full mt-1.5 inline-flex items-center gap-1 font-mono uppercase"
                                                :class="p.is_available
                                                    ? 'text-emerald-500 bg-emerald-500/10'
                                                    : 'text-muted-foreground bg-muted'"
                                            >
                                                <span :class="['w-1.5 h-1.5 rounded-full', p.is_available ? 'bg-emerald-500 animate-pulse' : 'bg-muted-foreground']"></span>
                                                {{ p.is_available ? 'Đang bán' : 'Tạm ngưng' }}
                                            </span>
                                        </div>
                                        
                                        <!-- Action buttons -->
                                        <div class="flex items-center gap-1">
                                            <button
                                                @click="toggleAvailability(p)"
                                                class="p-1.5 rounded-lg border border-border bg-card hover:bg-muted text-muted-foreground transition-all shrink-0 cursor-pointer"
                                                :class="p.is_available ? 'text-emerald-500 hover:text-emerald-600' : 'text-amber-500 hover:text-amber-600'"
                                                :title="p.is_available ? 'Tạm ngưng bán món này' : 'Mở bán món này trở lại'"
                                            >
                                                <ToggleRight v-if="p.is_available" class="size-4.5" />
                                                <ToggleLeft v-else class="size-4.5" />
                                            </button>
                                            
                                            <button
                                                @click="openEditModal(p)"
                                                class="p-1.5 rounded-lg border border-border bg-card hover:bg-muted text-muted-foreground hover:text-indigo-500 transition-all shrink-0 cursor-pointer"
                                                title="Chỉnh sửa thông tin món ăn"
                                            >
                                                <Pencil class="size-3.5" />
                                            </button>
                                            
                                            <button
                                                @click="confirmDelete(p)"
                                                class="p-1.5 rounded-lg border border-border bg-card hover:bg-rose-500/10 text-muted-foreground hover:text-rose-500 transition-all shrink-0 cursor-pointer"
                                                title="Xóa món ăn khỏi thực đơn"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Empty states -->
                            <div v-else-if="products.length" class="flex flex-col items-center justify-center p-10 text-center">
                                <Search class="size-10 text-muted-foreground/30 mb-3" />
                                <p class="text-sm font-semibold text-foreground/80">Không tìm thấy món ăn nào</p>
                                <p class="mt-1 text-xs text-muted-foreground">Thử thay đổi từ khóa hoặc chọn nhóm món khác.</p>
                                <Button class="mt-3 rounded-xl cursor-pointer" size="sm" variant="outline" @click="searchQuery = ''; selectedCategory = ''">Xóa bộ lọc</Button>
                            </div>
                            
                            <div v-else class="flex flex-col items-center justify-center p-14 text-center">
                                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground/30 mb-4 border border-dashed border-border">
                                    <UtensilsCrossed class="size-8" />
                                </div>
                                <p class="text-base font-extrabold text-foreground">Thực đơn rỗng</p>
                                <p class="mt-1.5 max-w-xs text-xs text-muted-foreground">Thêm món ăn đầu tiên để khách hàng có thể đặt hàng qua QR hoặc thu ngân tạo hóa đơn.</p>
                                <Button class="mt-4 rounded-xl font-bold bg-rose-650 hover:bg-rose-700 text-white cursor-pointer shadow-xs" size="sm" @click="showAddProduct = true">
                                    <Plus class="size-4 mr-1.5" />Thêm món ăn đầu tiên
                                </Button>
                            </div>
                        </CardContent>
                    </div>

                    <!-- Pagination -->
                    <div v-if="totalPages > 1" class="flex items-center justify-between border-t border-border/80 p-4 bg-muted/20">
                        <div class="text-[10px] font-semibold text-muted-foreground">
                            Hiển thị {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredProducts.length) }} trong tổng số {{ filteredProducts.length }} món ăn
                        </div>
                        <div class="flex items-center gap-1.5">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="currentPage === 1"
                                @click="currentPage--"
                                class="h-8 text-xs rounded-xl cursor-pointer"
                            >
                                Trước
                            </Button>
                            <Button
                                v-for="page in visiblePages"
                                :key="page"
                                variant="outline"
                                size="sm"
                                @click="currentPage = page"
                                :class="['h-8 w-8 text-xs p-0 rounded-xl cursor-pointer font-bold', currentPage === page ? 'bg-rose-650 text-white hover:bg-rose-700 border-0 shadow-xs' : '']"
                            >
                                {{ page }}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="currentPage === totalPages"
                                @click="currentPage++"
                                class="h-8 text-xs rounded-xl cursor-pointer"
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
    <div v-if="showAddCategory" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4 backdrop-blur-xs">
        <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 rounded-2xl border-border">
            <CardHeader class="pb-3 border-b border-border/60">
                <CardTitle class="text-base font-extrabold flex items-center gap-2">
                    <FolderPlus class="size-5 text-indigo-500" />
                    Thêm Nhóm Món Ăn Mới
                </CardTitle>
            </CardHeader>
            <CardContent class="p-5">
                <form @submit.prevent="submitCategory" class="space-y-4">
                    <div class="grid gap-1.5">
                        <Label for="cat-name" class="text-xs font-bold text-foreground">Tên nhóm món <span class="text-rose-500 font-bold">*</span></Label>
                        <Input id="cat-name" v-model="categoryForm.name" placeholder="Ví dụ: Đồ ăn kèm, Khai vị..." required class="rounded-xl" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cat-desc" class="text-xs font-bold text-foreground">Mô tả nhóm món</Label>
                        <textarea id="cat-desc" v-model="categoryForm.description" rows="2"
                            placeholder="Ghi chú mô tả danh mục..."
                            class="w-full rounded-xl border border-border bg-card text-foreground px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-amber-500/20" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-border/60">
                        <Button type="button" variant="outline" @click="showAddCategory = false" class="rounded-xl">Hủy</Button>
                        <Button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl cursor-pointer" :disabled="categoryForm.processing">
                            {{ categoryForm.processing ? 'Đang tạo...' : 'Tạo nhóm món' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>

    <!-- Add Product Modal -->
    <div v-if="showAddProduct" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4 backdrop-blur-xs">
        <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 rounded-2xl border-border">
            <CardHeader class="pb-3 border-b border-border/60">
                <CardTitle class="text-base font-extrabold flex items-center gap-2">
                    <Plus class="size-5 text-rose-500" />
                    Thêm Món Ăn Mới Vào Thực Đơn
                </CardTitle>
                <CardDescription>Nhập thông tin chi tiết về sản phẩm để phục vụ bán hàng.</CardDescription>
            </CardHeader>
            <CardContent class="p-5">
                <form @submit.prevent="submitProduct" class="space-y-4">
                    <div class="grid gap-1.5">
                        <Label for="prod-cat" class="text-xs font-bold text-foreground">Thuộc nhóm món <span class="text-rose-500 font-bold">*</span></Label>
                        <div class="relative">
                            <select id="prod-cat" v-model="productForm.category_id" required
                                class="w-full h-10 pl-3 pr-8 text-xs font-bold rounded-xl border border-border bg-card text-foreground appearance-none focus:outline-none focus:ring-2 focus:ring-amber-500/20 cursor-pointer"
                            >
                                <option value="" disabled>Chọn một nhóm món</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="prod-name" class="text-xs font-bold text-foreground">Tên món ăn <span class="text-rose-500 font-bold">*</span></Label>
                        <Input id="prod-name" v-model="productForm.name" placeholder="Ví dụ: Phở bò tái lăn..." required class="rounded-xl" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="prod-price" class="text-xs font-bold text-foreground">Giá bán (VND) <span class="text-rose-500 font-bold">*</span></Label>
                        <Input id="prod-price" type="number" v-model="productForm.price" placeholder="Ví dụ: 45000" required class="rounded-xl" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="prod-image" class="text-xs font-bold text-foreground">Ảnh món ăn (Menu Image)</Label>
                        <Input id="prod-image" type="file" accept="image/*" @change="(e: any) => productForm.image = e.target.files[0]" class="rounded-xl text-xs" />
                    </div>
                    <div class="grid gap-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="prod-desc" class="text-xs font-bold text-foreground">
                                Đặc điểm & Hương vị món ăn <span class="text-rose-500 font-bold">*</span>
                            </Label>
                        </div>
                        <textarea id="prod-desc" v-model="productForm.description" rows="3" required
                            placeholder="Mô tả hương vị (chua cay, béo ngậy, ngọt dịu...) để nhân viên dễ tư vấn khách."
                            class="w-full rounded-xl border border-border bg-card text-foreground px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500/30" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-border/60">
                        <Button type="button" variant="outline" @click="showAddProduct = false" class="rounded-xl">Hủy</Button>
                        <Button type="submit" class="bg-rose-650 hover:bg-rose-700 text-white font-bold rounded-xl cursor-pointer" :disabled="productForm.processing">
                            {{ productForm.processing ? 'Đang thêm...' : 'Thêm món ăn' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>

    <!-- Edit Product Modal -->
    <div v-if="editingProduct" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4 backdrop-blur-xs">
        <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 rounded-2xl border-border">
            <CardHeader class="pb-3 border-b border-border/60">
                <div class="flex items-center justify-between">
                    <CardTitle class="text-base font-extrabold flex items-center gap-2">
                        <Pencil class="size-4 text-indigo-600" />Chỉnh Sửa Món Ăn
                    </CardTitle>
                    <button @click="editingProduct = null" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <X class="size-4" />
                    </button>
                </div>
            </CardHeader>
            <CardContent class="p-5">
                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold text-foreground">Nhóm món</Label>
                        <div class="relative">
                            <select v-model="editForm.category_id"
                                class="w-full h-10 pl-3 pr-8 text-xs font-bold rounded-xl border border-border bg-card text-foreground appearance-none focus:outline-none focus:ring-2 focus:ring-amber-500/20 cursor-pointer"
                            >
                                <option value="">Chưa gán nhóm</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold text-foreground">Tên món ăn <span class="text-rose-500 font-bold">*</span></Label>
                        <Input v-model="editForm.name" required class="rounded-xl" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold text-foreground">Giá bán (VND) <span class="text-rose-500 font-bold">*</span></Label>
                        <Input type="number" v-model="editForm.price" required class="rounded-xl" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="edit-image" class="text-xs font-bold text-foreground">Thay đổi ảnh món ăn</Label>
                        <div class="flex items-center gap-3">
                            <div v-if="editingProduct?.image_url" class="size-12 rounded-xl overflow-hidden border border-border bg-muted shrink-0 shadow-xs">
                                <img :src="editingProduct.image_url" class="h-full w-full object-cover" />
                            </div>
                            <Input id="edit-image" type="file" accept="image/*" @change="(e: any) => editForm.image = e.target.files[0]" class="flex-1 rounded-xl text-xs" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <div class="flex items-center justify-between">
                            <Label class="text-xs font-bold text-foreground">
                                Đặc điểm & Hương vị món ăn <span class="text-rose-500 font-bold">*</span>
                            </Label>
                        </div>
                        <textarea v-model="editForm.description" rows="3" required
                            placeholder="Mô tả hương vị (chua cay, béo ngậy, ngọt dịu...) để nhân viên dễ tư vấn khách."
                            class="w-full rounded-xl border border-border bg-card text-foreground px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500/30" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-border/60">
                        <Button type="button" variant="outline" @click="editingProduct = null" class="rounded-xl">Hủy</Button>
                        <Button type="submit" class="bg-indigo-650 hover:bg-indigo-700 text-white font-bold rounded-xl cursor-pointer" :disabled="editForm.processing">
                            {{ editForm.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="deletingProduct" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4 backdrop-blur-xs">
        <Card class="max-w-sm w-full animate-in fade-in zoom-in-95 duration-150 rounded-2xl border-border shadow-lg">
            <CardContent class="pt-6 text-center space-y-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-rose-500/10 text-rose-600 mx-auto animate-pulse">
                    <Trash2 class="size-6" />
                </div>
                <div>
                    <h3 class="font-extrabold text-sm text-foreground">Xóa món ăn này?</h3>
                    <p class="text-xs text-muted-foreground mt-1.5">"{{ deletingProduct.name }}" sẽ bị xóa vĩnh viễn khỏi danh sách thực đơn.</p>
                </div>
                <div class="flex justify-center gap-3 pt-2 border-t border-border/60">
                    <Button variant="outline" @click="deletingProduct = null" class="rounded-xl">Hủy</Button>
                    <Button class="bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl cursor-pointer" @click="submitDelete">Xóa vĩnh viễn</Button>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Quick Combo Creator Modal -->
    <div v-if="showComboModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4 backdrop-blur-xs">
        <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 rounded-2xl border-border">
            <CardHeader class="pb-3 border-b border-border/60">
                <div class="flex items-center justify-between">
                    <CardTitle class="text-base font-extrabold flex items-center gap-2">
                        <Sparkles class="size-5 text-indigo-500" />Thiết lập Combo đề xuất
                    </CardTitle>
                    <button @click="showComboModal = false" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <X class="size-4" />
                    </button>
                </div>
                <CardDescription>
                    Tạo nhanh sản phẩm Combo từ gợi ý phân tích giỏ hàng của AI.
                </CardDescription>
            </CardHeader>
            <CardContent class="p-5">
                <form @submit.prevent="submitCombo" class="space-y-4">
                    <div class="grid gap-1.5">
                        <Label for="combo-name" class="text-xs font-bold text-foreground">Tên Combo sản phẩm <span class="text-rose-500 font-bold">*</span></Label>
                        <Input id="combo-name" v-model="comboForm.name" required placeholder="Ví dụ: Combo ăn sáng siêu rẻ..." class="rounded-xl" />
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 bg-muted/40 p-3 rounded-xl border border-border text-xs">
                        <div>
                            <p class="text-muted-foreground font-semibold">Món thứ nhất</p>
                            <p class="font-extrabold text-foreground mt-0.5 truncate">{{ comboForm.item_a_name }}</p>
                            <p class="font-mono font-bold text-rose-500 mt-0.5">{{ formatCurrency(comboForm.price_a) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground font-semibold">Món thứ hai (bán kèm)</p>
                            <p class="font-extrabold text-foreground mt-0.5 truncate">{{ comboForm.item_b_name }}</p>
                            <p class="font-mono font-bold text-rose-500 mt-0.5">{{ formatCurrency(comboForm.price_b) }}</p>
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <div class="flex justify-between items-center">
                            <Label for="combo-price" class="text-xs font-bold text-foreground">Giá bán Combo (VND) <span class="text-rose-500 font-bold">*</span></Label>
                            <span class="text-[10px] text-muted-foreground font-semibold">
                                Tổng bán lẻ: {{ formatCurrency(comboForm.price_a + comboForm.price_b) }}
                            </span>
                        </div>
                        <Input id="combo-price" type="number" v-model="comboForm.combo_price" required class="rounded-xl" />
                        <p class="text-[10px] text-muted-foreground">
                            Đề xuất giảm ~12% so với tổng mua lẻ. Giá combo phải nhỏ hơn tổng bán lẻ.
                        </p>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="combo-notes" class="text-xs font-bold text-foreground">Ghi chú / Mô tả combo</Label>
                        <textarea id="combo-notes" v-model="comboForm.notes" rows="3"
                            class="w-full rounded-xl border border-border bg-card text-foreground px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500/25"
                            placeholder="Mô tả ưu đãi combo..." />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/60">
                        <Button type="button" variant="outline" @click="showComboModal = false" class="rounded-xl">Hủy</Button>
                        <Button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl cursor-pointer" :disabled="comboForm.processing">
                            {{ comboForm.processing ? 'Đang tạo...' : 'Tạo Combo' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
