<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    Search, Sparkles, ShoppingBag, Plus, Minus, Trash2,
    ChefHat, HelpCircle, UtensilsCrossed, CalendarDays, RefreshCw,
    AlertCircle, Lightbulb, Check, ChevronRight, User, AlertTriangle
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface Product {
    id: number;
    name: string;
    price: number;
    sku: string;
    category_id: number;
    category_name: string | null;
}

interface Category {
    id: number;
    name: string;
}

interface Table {
    id: number;
    name: string;
}

const props = defineProps<{
    products: Product[];
    categories: Category[];
    tables: Table[];
}>();

// --- STATE ---
const searchQuery = ref('');
const selectedCategoryId = ref<number | null>(null);
const selectedTableId = ref<number | null>(null);
const cartItems = ref<Array<{ product: Product; quantity: number; notes: string }>>([]);

// AI Upselling Widget state
const aiSuggestion = ref<{
    suggestion: string | null;
    recommended_item: string | null;
    source: string;
} | null>(null);
const aiLoading = ref(false);

// --- COMPUTED ---
// Filter products based on search and category
const filteredProducts = computed(() => {
    return props.products.filter(p => {
        const matchesSearch = p.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                             (p.sku && p.sku.toLowerCase().includes(searchQuery.value.toLowerCase()));
        const matchesCategory = selectedCategoryId.value === null || p.category_id === selectedCategoryId.value;
        return matchesSearch && matchesCategory;
    });
});

// Calculate total cart price
const subtotal = computed(() => {
    return cartItems.value.reduce((sum, item) => sum + (item.product.price * item.quantity), 0);
});

// --- ACTIONS ---
const selectCategory = (id: number | null) => {
    selectedCategoryId.value = id;
};

// Add product to cart
const addToCart = (product: Product) => {
    const existing = cartItems.value.find(item => item.product.id === product.id);
    if (existing) {
        existing.quantity += 1;
    } else {
        cartItems.value.push({
            product,
            quantity: 1,
            notes: ''
        });
    }
};

// Decrease quantity or remove from cart
const decreaseQuantity = (productId: number) => {
    const index = cartItems.value.findIndex(item => item.product.id === productId);
    if (index !== -1) {
        if (cartItems.value[index].quantity > 1) {
            cartItems.value[index].quantity -= 1;
        } else {
            cartItems.value.splice(index, 1);
        }
    }
};

// Remove item completely
const removeFromCart = (productId: number) => {
    const index = cartItems.value.findIndex(item => item.product.id === productId);
    if (index !== -1) {
        cartItems.value.splice(index, 1);
    }
};

// Form submit helper
const submitOrder = () => {
    if (cartItems.value.length === 0) {
        alert('Giỏ hàng trống! Hãy chọn ít nhất một món ăn trước khi chuyển bếp.');
        return;
    }

    const form = useForm({
        table_id: selectedTableId.value,
        note: '',
        items: cartItems.value.map(item => ({
            product_id: item.product.id,
            quantity: item.quantity,
            notes: item.notes
        }))
    });

    form.post('/orders', {
        onSuccess: () => {
            cartItems.value = [];
            selectedTableId.value = null;
        }
    });
};

// AI Suggestion caller
const fetchAiSuggestion = async () => {
    if (cartItems.value.length === 0) {
        aiSuggestion.value = null;
        return;
    }

    aiLoading.value = true;
    try {
        const itemNames = cartItems.value.map(item => item.product.name);
        const response = await fetch('/api/promotions/upsell-suggestion', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''
            },
            body: JSON.stringify({ items: itemNames })
        });

        if (response.ok) {
            aiSuggestion.value = await response.json();
        }
    } catch (e) {
        console.error('Không kết nối được với API Upselling:', e);
    } finally {
        aiLoading.value = false;
    }
};

// Add suggested item quickly
const addSuggestedItem = () => {
    if (!aiSuggestion.value || !aiSuggestion.value.recommended_item) return;
    
    const recommendedName = aiSuggestion.value.recommended_item;
    // Find the product in props.products
    const product = props.products.find(p => p.name === recommendedName);
    if (product) {
        addToCart(product);
    } else {
        alert(`Món gợi ý "${recommendedName}" hiện không hoạt động hoặc không có trong menu.`);
    }
};

// Format currency in VND
const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

// Listen to cart changes to call AI suggested Upselling
watch(
    () => cartItems.value.map(item => `${item.product.id}-${item.quantity}`),
    () => {
        fetchAiSuggestion();
    },
    { deep: true }
);

onMounted(() => {
    // Tự chọn bàn đầu tiên nếu có để tiện thao tác
    if (props.tables.length > 0) {
        selectedTableId.value = props.tables[0].id;
    }
});
</script>

<template>
    <Head title="POS Bán hàng & Trợ lý AI Gọi món" />

    <div class="flex flex-col h-[calc(100vh-4rem)] p-4 max-w-[1600px] mx-auto w-full gap-4 overflow-hidden">
        <!-- POS Top Area -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 border-b pb-3 shrink-0">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400">
                    <ChefHat class="size-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">POS Bán Hàng & Smart Upselling</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Nhận order, gợi ý món bán kèm thời gian thực thông qua Trợ lý AI.</p>
                </div>
            </div>
            <!-- Tables picker and visual info -->
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <div class="flex items-center gap-2 w-full">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-400 whitespace-nowrap">Chọn bàn:</span>
                    <select 
                        v-model="selectedTableId" 
                        class="h-9 w-full sm:w-44 rounded-md border border-input bg-background px-3 py-1.5 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <option :value="null">Mang về (Takeaway)</option>
                        <option v-for="table in tables" :key="table.id" :value="table.id">
                            {{ table.name }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- POS Workspace (Two Columns) -->
        <div class="flex flex-col lg:flex-row gap-4 flex-1 min-h-0 overflow-hidden">
            <!-- LEFT COLUMN: MENU PRODUCTS (2/3 Width) -->
            <div class="flex flex-col flex-1 min-h-0 bg-slate-50 dark:bg-slate-900/40 rounded-2xl border p-4">
                <!-- Search and Categories Filter -->
                <div class="flex flex-col gap-3 mb-4 shrink-0">
                    <div class="relative">
                        <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                        <Input 
                            type="text" 
                            v-model="searchQuery" 
                            placeholder="Tìm nhanh món ăn theo tên hoặc mã SKU..." 
                            class="pl-9 h-9 border-slate-200 dark:border-slate-800"
                        />
                    </div>

                    <!-- Horizontal categories scroll list -->
                    <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-thin scrollbar-thumb-slate-200 scrollbar-track-transparent">
                        <Button 
                            variant="outline" 
                            size="sm" 
                            @click="selectCategory(null)"
                            :class="[
                                'rounded-full text-xs font-medium border-slate-200 dark:border-slate-800 transition-all shrink-0',
                                selectedCategoryId === null ? 'bg-violet-600 text-white border-violet-600 dark:bg-violet-500 dark:border-violet-500' : 'bg-white hover:bg-slate-100 dark:bg-slate-950 dark:hover:bg-slate-900'
                            ]"
                        >
                            Tất cả món
                        </Button>
                        <Button 
                            v-for="cat in categories" 
                            :key="cat.id"
                            variant="outline" 
                            size="sm" 
                            @click="selectCategory(cat.id)"
                            :class="[
                                'rounded-full text-xs font-medium border-slate-200 dark:border-slate-800 transition-all shrink-0',
                                selectedCategoryId === cat.id ? 'bg-violet-600 text-white border-violet-600 dark:bg-violet-500 dark:border-violet-500' : 'bg-white hover:bg-slate-100 dark:bg-slate-950 dark:hover:bg-slate-900'
                            ]"
                        >
                            {{ cat.name }}
                        </Button>
                    </div>
                </div>

                <!-- Products Grid -->
                <div class="flex-1 min-h-0 overflow-y-auto scrollbar-thin scrollbar-thumb-slate-200">
                    <div v-if="filteredProducts.length > 0" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                        <Card 
                            v-for="product in filteredProducts" 
                            :key="product.id"
                            @click="addToCart(product)"
                            class="group relative overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-card hover:shadow-md cursor-pointer transition-all duration-300 hover:-translate-y-0.5 active:translate-y-0 select-none flex flex-col justify-between"
                        >
                            <CardHeader class="p-3.5 pb-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 tracking-wider font-mono">
                                        {{ product.sku }}
                                    </span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 font-medium">
                                        {{ product.category_name }}
                                    </span>
                                </div>
                                <CardTitle class="text-sm font-semibold text-slate-800 dark:text-slate-100 mt-2 line-clamp-2 min-h-[2.5rem]">
                                    {{ product.name }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="p-3.5 pt-0 flex items-center justify-between">
                                <span class="text-sm font-bold text-violet-600 dark:text-violet-400">
                                    {{ formatCurrency(product.price) }}
                                </span>
                                <div class="rounded-lg h-7 w-7 flex items-center justify-center bg-violet-50 group-hover:bg-violet-600 dark:bg-violet-950/50 dark:group-hover:bg-violet-500 transition-colors text-violet-600 group-hover:text-white dark:text-violet-400">
                                    <Plus class="size-4" />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center py-20 text-center gap-2">
                        <AlertCircle class="size-10 text-slate-300 dark:text-slate-700" />
                        <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300">Không tìm thấy món ăn</h3>
                        <p class="text-xs text-slate-400 dark:text-slate-500 max-w-[250px]">Hãy thử tìm kiếm từ khóa khác hoặc chuyển danh mục hiển thị.</p>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: CART & AI SUGGESTION WIDGET (1/3 Width) -->
            <div class="w-full lg:w-96 flex flex-col shrink-0 min-h-0 bg-white dark:bg-slate-950 rounded-2xl border overflow-hidden">
                <!-- Cart Header -->
                <div class="p-4 border-b shrink-0 flex items-center justify-between bg-slate-50 dark:bg-slate-900/20">
                    <div class="flex items-center gap-2">
                        <ShoppingBag class="size-4 text-violet-600" />
                        <span class="font-bold text-sm text-slate-800 dark:text-slate-100">Chi tiết giỏ hàng</span>
                        <span class="text-xs bg-violet-100 dark:bg-violet-900/60 text-violet-700 dark:text-violet-300 px-2 py-0.5 rounded-full font-semibold">
                            {{ cartItems.reduce((s, i) => s + i.quantity, 0) }}
                        </span>
                    </div>
                    <Button 
                        v-if="cartItems.length > 0" 
                        variant="ghost" 
                        size="sm" 
                        @click="cartItems = []"
                        class="text-xs h-7 text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/20"
                    >
                        Xóa tất cả
                    </Button>
                </div>

                <!-- Cart Items List -->
                <div class="flex-1 min-h-0 overflow-y-auto p-4 flex flex-col gap-3 scrollbar-thin scrollbar-thumb-slate-200">
                    <div v-if="cartItems.length > 0" class="flex flex-col gap-3">
                        <div 
                            v-for="item in cartItems" 
                            :key="item.product.id"
                            class="flex flex-col gap-1.5 pb-3 border-b last:border-b-0 border-slate-100 dark:border-slate-800/80 group"
                        >
                            <div class="flex justify-between items-start">
                                <div class="flex-1 pr-2">
                                    <h4 class="text-xs font-semibold text-slate-800 dark:text-slate-200 line-clamp-2">
                                        {{ item.product.name }}
                                    </h4>
                                    <p class="text-[10px] text-slate-400 dark:text-slate-500 font-bold mt-0.5">
                                        {{ formatCurrency(item.product.price) }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0 bg-slate-50 dark:bg-slate-900 rounded-lg p-0.5 border border-slate-150 dark:border-slate-800">
                                    <Button 
                                        variant="ghost" 
                                        size="icon" 
                                        @click="decreaseQuantity(item.product.id)"
                                        class="h-6 w-6 rounded-md hover:bg-white dark:hover:bg-slate-800"
                                    >
                                        <Minus class="size-3" />
                                    </Button>
                                    <span class="text-xs font-bold w-6 text-center text-slate-800 dark:text-slate-200">
                                        {{ item.quantity }}
                                    </span>
                                    <Button 
                                        variant="ghost" 
                                        size="icon" 
                                        @click="addToCart(item.product)"
                                        class="h-6 w-6 rounded-md hover:bg-white dark:hover:bg-slate-800"
                                    >
                                        <Plus class="size-3" />
                                    </Button>
                                </div>
                            </div>
                            <!-- Note field and remove button -->
                            <div class="flex justify-between items-center gap-2 mt-1">
                                <input 
                                    type="text" 
                                    v-model="item.notes" 
                                    placeholder="Ghi chú món (ít ngọt, không cay...)" 
                                    class="flex-1 h-6 rounded border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30 px-2 py-0.5 text-[10px] focus:outline-none focus:ring-1 focus:ring-violet-500"
                                />
                                <Button 
                                    variant="ghost" 
                                    size="icon" 
                                    @click="removeFromCart(item.product.id)"
                                    class="h-6 w-6 opacity-0 group-hover:opacity-100 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20 text-slate-400 shrink-0 transition-opacity rounded-md"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center py-16 text-center gap-2 flex-1">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 dark:bg-slate-900 border text-slate-400 dark:text-slate-600">
                            <ShoppingBag class="size-5" />
                        </div>
                        <h3 class="text-xs font-semibold text-slate-700 dark:text-slate-300">Giỏ hàng rỗng</h3>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500 max-w-[200px]">Hãy click chọn các món bên menu để bắt đầu order.</p>
                    </div>
                </div>

                <!-- AI SMART UPSELLING ASSISTANT BOX -->
                <div class="shrink-0 p-4 border-t bg-slate-50/50 dark:bg-slate-950/60">
                    <!-- Loading state -->
                    <div v-if="aiLoading" class="rounded-xl border border-violet-200 dark:border-violet-800/60 bg-white dark:bg-slate-900 p-3.5 flex items-center justify-center gap-3 shadow-sm select-none">
                        <div class="relative flex h-6 w-6 shrink-0 items-center justify-center">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-violet-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-4 w-4 bg-violet-600"></span>
                        </div>
                        <span class="text-xs font-bold text-violet-600 dark:text-violet-400 animate-pulse">
                            AI đang phân tích và tìm cơ hội upselling...
                        </span>
                    </div>

                    <!-- Has Suggestion state -->
                    <div 
                        v-else-if="aiSuggestion && aiSuggestion.suggestion" 
                        class="rounded-xl border border-violet-200 dark:border-violet-800/80 bg-gradient-to-br from-violet-50 to-indigo-50/50 dark:from-violet-950/20 dark:to-indigo-950/10 p-3.5 shadow-sm relative overflow-hidden group/ai transition-all duration-300 hover:border-violet-300 dark:hover:border-violet-700"
                    >
                        <!-- Sparkles floating top-right -->
                        <Sparkles class="absolute right-2.5 top-2.5 size-4 text-violet-400 dark:text-violet-500 animate-pulse" />
                        
                        <div class="flex items-center gap-1.5 text-violet-700 dark:text-violet-300 font-bold mb-1.5 select-none">
                            <Lightbulb class="size-3.5 shrink-0" />
                            <span class="text-[11px] uppercase tracking-wider">Trợ lý AI Upselling</span>
                        </div>
                        
                        <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed font-medium">
                            "{{ aiSuggestion.suggestion }}"
                        </p>

                        <!-- Extra details if recommended item is found -->
                        <div v-if="aiSuggestion.recommended_item" class="mt-3 flex items-center justify-between gap-2 border-t border-violet-100 dark:border-violet-900/60 pt-2.5">
                            <div class="flex items-center gap-1">
                                <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-violet-100 dark:bg-violet-900/60 text-violet-700 dark:text-violet-300 font-semibold select-none">
                                    {{ aiSuggestion.source.split('(')[0].trim() }}
                                </span>
                            </div>
                            <Button 
                                size="sm" 
                                @click="addSuggestedItem"
                                class="h-7 text-xs font-bold bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 dark:from-violet-500 dark:to-indigo-500 text-white rounded-lg shadow-sm flex items-center gap-1 group-hover/ai:scale-105 transition-all duration-200 border-0"
                            >
                                <Plus class="size-3.5" />
                                Thêm nhanh
                            </Button>
                        </div>
                    </div>

                    <!-- Default welcome / empty cart state -->
                    <div v-else class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-3.5 flex items-center gap-3 text-slate-500 select-none">
                        <div class="h-8 w-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 shrink-0">
                            <Sparkles class="size-4" />
                        </div>
                        <div class="flex-1">
                            <h5 class="text-xs font-bold text-slate-700 dark:text-slate-300">Gợi ý Bán kèm Thông minh</h5>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500">Mời thêm món vào giỏ hàng để AI tự động đề xuất combo tăng doanh số.</p>
                        </div>
                    </div>
                </div>

                <!-- Cart Total and Submit -->
                <div class="p-4 border-t bg-slate-50 dark:bg-slate-900/40 shrink-0 flex flex-col gap-3">
                    <div class="flex justify-between items-center text-sm font-bold text-slate-800 dark:text-slate-100">
                        <span>Tạm tính (Gồm VAT):</span>
                        <span class="text-lg text-violet-600 dark:text-violet-400">
                            {{ formatCurrency(subtotal) }}
                        </span>
                    </div>

                    <Button 
                        @click="submitOrder"
                        :disabled="cartItems.length === 0"
                        class="w-full h-10 font-bold text-sm bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 dark:from-violet-500 dark:to-indigo-500 text-white rounded-xl shadow-lg border-0 shadow-violet-200 dark:shadow-none hover:shadow-xl transition-all duration-300 select-none flex items-center justify-center gap-2"
                    >
                        <ChefHat class="size-4" />
                        Xác nhận & Chuyển bếp
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Scrollbar Styling for micro-POS SPA */
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
    height: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.dark .scrollbar-thin::-webkit-scrollbar-thumb {
    background: #334155;
}
</style>
