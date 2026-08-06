<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search, Sparkles, ShoppingBag, Plus, Minus, Trash2, ChefHat, RefreshCw, AlertCircle, Lightbulb } from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface Product {
    id: number;
    name: string;
    price: number;
    sku: string;
    category_id: number;
    category_name: string | null;
    available_portions?: number | null;
    is_out_of_stock?: boolean;
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
const cartItems = ref<
    Array<{ product: Product; quantity: number; notes: string }>
>([]);

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
    return props.products.filter((p) => {
        const matchesSearch =
            p.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            (p.sku &&
                p.sku.toLowerCase().includes(searchQuery.value.toLowerCase()));
        const matchesCategory =
            selectedCategoryId.value === null ||
            p.category_id === selectedCategoryId.value;

        return matchesSearch && matchesCategory;
    });
});

// Calculate total cart price
const subtotal = computed(() => {
    return cartItems.value.reduce(
        (sum, item) => sum + item.product.price * item.quantity,
        0,
    );
});

// --- ACTIONS ---
const selectCategory = (id: number | null) => {
    selectedCategoryId.value = id;
};

// Add product to cart
const addToCart = (product: Product) => {
    if (product.is_out_of_stock || product.available_portions === 0) {
        return;
    }

    const existing = cartItems.value.find(
        (item) => item.product.id === product.id,
    );

    if (existing) {
        existing.quantity += 1;
    } else {
        cartItems.value.push({
            product,
            quantity: 1,
            notes: '',
        });
    }
};

// Decrease quantity or remove from cart
const decreaseQuantity = (productId: number) => {
    const index = cartItems.value.findIndex(
        (item) => item.product.id === productId,
    );

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
    const index = cartItems.value.findIndex(
        (item) => item.product.id === productId,
    );

    if (index !== -1) {
        cartItems.value.splice(index, 1);
    }
};

const isSubmitting = ref(false);

// Form submit helper
const submitOrder = () => {
    if (isSubmitting.value) {
        return;
    }

    if (cartItems.value.length === 0) {
        toast.error(
            'Giỏ hàng trống! Hãy chọn ít nhất một món ăn trước khi chuyển bếp.',
        );

        return;
    }

    isSubmitting.value = true;

    router.post(
        '/orders',
        {
            table_id: selectedTableId.value,
            note: '',
            items: cartItems.value.map((item) => ({
                product_id: item.product.id,
                quantity: item.quantity,
                notes: item.notes,
            })),
        },
        {
            onSuccess: () => {
                cartItems.value = [];
                selectedTableId.value = null;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

// AI Suggestion caller
const fetchAiSuggestion = async () => {
    if (cartItems.value.length === 0) {
        aiSuggestion.value = null;

        return;
    }

    aiLoading.value = true;

    try {
        const itemNames = cartItems.value.map((item) => item.product.name);
        const response = await fetch('/api/promotions/upsell-suggestion', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content || '',
            },
            body: JSON.stringify({ items: itemNames }),
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
    if (!aiSuggestion.value || !aiSuggestion.value.recommended_item) {
        return;
    }

    const recommendedName = aiSuggestion.value.recommended_item;
    // Find the product in props.products
    const product = props.products.find((p) => p.name === recommendedName);

    if (product) {
        addToCart(product);
    } else {
        toast.error(
            `Món gợi ý "${recommendedName}" hiện không hoạt động hoặc không có trong menu.`,
        );
    }
};

// Format currency in VND
const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(val);
};

// Listen to cart changes to call AI suggested Upselling
watch(
    () => cartItems.value.map((item) => `${item.product.id}-${item.quantity}`),
    () => {
        fetchAiSuggestion();
    },
    { deep: true },
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

    <div
        class="mx-auto flex h-[calc(100vh-4rem)] w-full max-w-[1600px] flex-col gap-4 overflow-hidden p-4"
    >
        <!-- POS Top Area -->
        <div
            class="flex shrink-0 flex-col items-start justify-between gap-2 border-b pb-3 sm:flex-row sm:items-center"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 text-violet-600 dark:bg-violet-950/60 dark:text-violet-400"
                >
                    <ChefHat class="size-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        POS Bán Hàng & Smart Upselling
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Nhận order, gợi ý món bán kèm thời gian thực thông qua
                        Trợ lý AI.
                    </p>
                </div>
            </div>
            <!-- Tables picker and visual info -->
            <div class="flex w-full items-center gap-3 sm:w-auto">
                <div class="flex w-full items-center gap-2">
                    <span
                        class="text-xs font-semibold whitespace-nowrap text-slate-600 dark:text-slate-400"
                        >Chọn bàn:</span
                    >
                    <select
                        v-model="selectedTableId"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:w-44"
                    >
                        <option :value="null">Mang về (Takeaway)</option>
                        <option
                            v-for="table in tables"
                            :key="table.id"
                            :value="table.id"
                        >
                            {{ table.name }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- POS Workspace (Two Columns) -->
        <div
            class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden lg:flex-row"
        >
            <!-- LEFT COLUMN: MENU PRODUCTS (2/3 Width) -->
            <div
                class="flex min-h-0 flex-1 flex-col rounded-2xl border bg-slate-50 p-4 dark:bg-slate-900/40"
            >
                <!-- Search and Categories Filter -->
                <div class="mb-4 flex shrink-0 flex-col gap-3">
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground"
                        />
                        <Input
                            type="text"
                            v-model="searchQuery"
                            placeholder="Tìm nhanh món ăn theo tên hoặc mã SKU..."
                            class="h-9 border-slate-200 pl-9 dark:border-slate-800"
                        />
                    </div>

                    <!-- Horizontal categories scroll list -->
                    <div
                        class="flex scrollbar-thin scrollbar-thumb-slate-200 scrollbar-track-transparent gap-2 overflow-x-auto pb-1"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            @click="selectCategory(null)"
                            :class="[
                                'shrink-0 rounded-full border-slate-200 text-xs font-medium transition-all dark:border-slate-800',
                                selectedCategoryId === null
                                    ? 'border-violet-600 bg-violet-600 text-white dark:border-violet-500 dark:bg-violet-500'
                                    : 'bg-white hover:bg-slate-100 dark:bg-slate-950 dark:hover:bg-slate-900',
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
                                'shrink-0 rounded-full border-slate-200 text-xs font-medium transition-all dark:border-slate-800',
                                selectedCategoryId === cat.id
                                    ? 'border-violet-600 bg-violet-600 text-white dark:border-violet-500 dark:bg-violet-500'
                                    : 'bg-white hover:bg-slate-100 dark:bg-slate-950 dark:hover:bg-slate-900',
                            ]"
                        >
                            {{ cat.name }}
                        </Button>
                    </div>
                </div>

                <!-- Products Grid -->
                <div
                    class="min-h-0 flex-1 scrollbar-thin scrollbar-thumb-slate-200 overflow-y-auto"
                >
                    <div
                        v-if="filteredProducts.length > 0"
                        class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4"
                    >
                        <Card
                            v-for="product in filteredProducts"
                            :key="product.id"
                            @click="addToCart(product)"
                            :class="[
                                'group relative flex flex-col justify-between overflow-hidden rounded-xl border border-slate-200 bg-card transition-all duration-300 select-none dark:border-slate-800',
                                product.is_out_of_stock || product.available_portions === 0
                                    ? 'cursor-not-allowed opacity-50'
                                    : 'cursor-pointer hover:-translate-y-0.5 hover:shadow-md active:translate-y-0',
                            ]"
                        >
                            <CardHeader class="p-3.5 pb-2">
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span
                                        class="font-mono text-[10px] font-semibold tracking-wider text-slate-400 dark:text-slate-500"
                                    >
                                        {{ product.sku }}
                                    </span>
                                    <span
                                        class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500 dark:bg-slate-800"
                                    >
                                        {{ product.category_name }}
                                    </span>
                                </div>
                                <CardTitle
                                    class="mt-2 line-clamp-2 min-h-[2.5rem] text-sm font-semibold text-slate-800 dark:text-slate-100"
                                >
                                    {{ product.name }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent
                                class="flex items-center justify-between p-3.5 pt-0"
                            >
                                <span
                                    class="text-sm font-bold text-violet-600 dark:text-violet-400"
                                >
                                    {{ formatCurrency(product.price) }}
                                </span>
                                <span
                                    v-if="product.available_portions !== null && product.available_portions !== undefined"
                                    class="text-[10px] font-semibold text-emerald-600"
                                >
                                    {{ product.available_portions > 0 ? `Còn ${product.available_portions} suất` : 'Hết món' }}
                                </span>
                                <div
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-50 text-violet-600 transition-colors group-hover:bg-violet-600 group-hover:text-white dark:bg-violet-950/50 dark:text-violet-400 dark:group-hover:bg-violet-500"
                                >
                                    <Plus class="size-4" />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center justify-center gap-2 py-20 text-center"
                    >
                        <AlertCircle
                            class="size-10 text-slate-300 dark:text-slate-700"
                        />
                        <h3
                            class="text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            Không tìm thấy món ăn
                        </h3>
                        <p
                            class="max-w-[250px] text-xs text-slate-400 dark:text-slate-500"
                        >
                            Hãy thử tìm kiếm từ khóa khác hoặc chuyển danh mục
                            hiển thị.
                        </p>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: CART & AI SUGGESTION WIDGET (1/3 Width) -->
            <div
                class="flex min-h-0 w-full shrink-0 flex-col overflow-hidden rounded-2xl border bg-white lg:w-96 dark:bg-slate-950"
            >
                <!-- Cart Header -->
                <div
                    class="flex shrink-0 items-center justify-between border-b bg-slate-50 p-4 dark:bg-slate-900/20"
                >
                    <div class="flex items-center gap-2">
                        <ShoppingBag class="size-4 text-violet-600" />
                        <span
                            class="text-sm font-bold text-slate-800 dark:text-slate-100"
                            >Chi tiết giỏ hàng</span
                        >
                        <span
                            class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-semibold text-violet-700 dark:bg-violet-900/60 dark:text-violet-300"
                        >
                            {{ cartItems.reduce((s, i) => s + i.quantity, 0) }}
                        </span>
                    </div>
                    <Button
                        v-if="cartItems.length > 0"
                        variant="ghost"
                        size="sm"
                        @click="cartItems = []"
                        class="h-7 text-xs text-rose-500 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/20"
                    >
                        Xóa tất cả
                    </Button>
                </div>

                <!-- Cart Items List -->
                <div
                    class="flex min-h-0 flex-1 scrollbar-thin scrollbar-thumb-slate-200 flex-col gap-3 overflow-y-auto p-4"
                >
                    <div
                        v-if="cartItems.length > 0"
                        class="flex flex-col gap-3"
                    >
                        <div
                            v-for="item in cartItems"
                            :key="item.product.id"
                            class="group flex flex-col gap-1.5 border-b border-slate-100 pb-3 last:border-b-0 dark:border-slate-800/80"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1 pr-2">
                                    <h4
                                        class="line-clamp-2 text-xs font-semibold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ item.product.name }}
                                    </h4>
                                    <p
                                        class="mt-0.5 text-[10px] font-bold text-slate-400 dark:text-slate-500"
                                    >
                                        {{ formatCurrency(item.product.price) }}
                                    </p>
                                </div>
                                <div
                                    class="border-slate-150 flex shrink-0 items-center gap-1.5 rounded-lg border bg-slate-50 p-0.5 dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="
                                            decreaseQuantity(item.product.id)
                                        "
                                        class="h-6 w-6 rounded-md hover:bg-white dark:hover:bg-slate-800"
                                    >
                                        <Minus class="size-3" />
                                    </Button>
                                    <span
                                        class="w-6 text-center text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
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
                            <div
                                class="mt-1 flex items-center justify-between gap-2"
                            >
                                <input
                                    type="text"
                                    v-model="item.notes"
                                    placeholder="Ghi chú món (ít ngọt, không cay...)"
                                    class="h-6 flex-1 rounded border border-slate-100 bg-slate-50/50 px-2 py-0.5 text-[10px] focus:ring-1 focus:ring-violet-500 focus:outline-none dark:border-slate-800 dark:bg-slate-900/30"
                                />
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="removeFromCart(item.product.id)"
                                    class="h-6 w-6 shrink-0 rounded-md text-slate-400 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/20"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex flex-1 flex-col items-center justify-center gap-2 py-16 text-center"
                    >
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full border bg-slate-50 text-slate-400 dark:bg-slate-900 dark:text-slate-600"
                        >
                            <ShoppingBag class="size-5" />
                        </div>
                        <h3
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            Giỏ hàng rỗng
                        </h3>
                        <p
                            class="max-w-[200px] text-[10px] text-slate-400 dark:text-slate-500"
                        >
                            Hãy click chọn các món bên menu để bắt đầu order.
                        </p>
                    </div>
                </div>

                <!-- AI SMART UPSELLING ASSISTANT BOX -->
                <div
                    class="shrink-0 border-t bg-slate-50/50 p-4 dark:bg-slate-950/60"
                >
                    <!-- Loading state -->
                    <div
                        v-if="aiLoading"
                        class="flex items-center justify-center gap-3 rounded-xl border border-violet-200 bg-white p-3.5 shadow-sm select-none dark:border-violet-800/60 dark:bg-slate-900"
                    >
                        <div
                            class="relative flex h-6 w-6 shrink-0 items-center justify-center"
                        >
                            <span
                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-violet-400 opacity-75"
                            ></span>
                            <span
                                class="relative inline-flex h-4 w-4 rounded-full bg-violet-600"
                            ></span>
                        </div>
                        <span
                            class="animate-pulse text-xs font-bold text-violet-600 dark:text-violet-400"
                        >
                            AI đang phân tích và tìm cơ hội upselling...
                        </span>
                    </div>

                    <!-- Has Suggestion state -->
                    <div
                        v-else-if="aiSuggestion && aiSuggestion.suggestion"
                        class="group/ai relative overflow-hidden rounded-xl border border-violet-200 bg-gradient-to-br from-violet-50 to-indigo-50/50 p-3.5 shadow-sm transition-all duration-300 hover:border-violet-300 dark:border-violet-800/80 dark:from-violet-950/20 dark:to-indigo-950/10 dark:hover:border-violet-700"
                    >
                        <!-- Sparkles floating top-right -->
                        <Sparkles
                            class="absolute top-2.5 right-2.5 size-4 animate-pulse text-violet-400 dark:text-violet-500"
                        />

                        <div
                            class="mb-1.5 flex items-center gap-1.5 font-bold text-violet-700 select-none dark:text-violet-300"
                        >
                            <Lightbulb class="size-3.5 shrink-0" />
                            <span class="text-[11px] tracking-wider uppercase"
                                >Trợ lý AI Upselling</span
                            >
                        </div>

                        <p
                            class="text-xs leading-relaxed font-medium text-slate-700 dark:text-slate-300"
                        >
                            "{{ aiSuggestion.suggestion }}"
                        </p>

                        <!-- Extra details if recommended item is found -->
                        <div
                            v-if="aiSuggestion.recommended_item"
                            class="mt-3 flex items-center justify-between gap-2 border-t border-violet-100 pt-2.5 dark:border-violet-900/60"
                        >
                            <div class="flex items-center gap-1">
                                <span
                                    class="rounded-full bg-violet-100 px-1.5 py-0.5 text-[9px] font-semibold text-violet-700 select-none dark:bg-violet-900/60 dark:text-violet-300"
                                >
                                    {{
                                        aiSuggestion.source.split('(')[0].trim()
                                    }}
                                </span>
                            </div>
                            <Button
                                size="sm"
                                @click="addSuggestedItem"
                                class="flex h-7 items-center gap-1 rounded-lg border-0 bg-gradient-to-r from-violet-600 to-indigo-600 text-xs font-bold text-white shadow-sm transition-all duration-200 group-hover/ai:scale-105 hover:from-violet-700 hover:to-indigo-700 dark:from-violet-500 dark:to-indigo-500"
                            >
                                <Plus class="size-3.5" />
                                Thêm nhanh
                            </Button>
                        </div>
                    </div>

                    <!-- Default welcome / empty cart state -->
                    <div
                        v-else
                        class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 text-slate-500 select-none dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-400 dark:bg-slate-800"
                        >
                            <Sparkles class="size-4" />
                        </div>
                        <div class="flex-1">
                            <h5
                                class="text-xs font-bold text-slate-700 dark:text-slate-300"
                            >
                                Gợi ý Bán kèm Thông minh
                            </h5>
                            <p
                                class="text-[10px] text-slate-400 dark:text-slate-500"
                            >
                                Mời thêm món vào giỏ hàng để AI tự động đề xuất
                                combo tăng doanh số.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Cart Total and Submit -->
                <div
                    class="flex shrink-0 flex-col gap-3 border-t bg-slate-50 p-4 dark:bg-slate-900/40"
                >
                    <div
                        class="flex items-center justify-between text-sm font-bold text-slate-800 dark:text-slate-100"
                    >
                        <span>Tạm tính (Gồm VAT):</span>
                        <span
                            class="text-lg text-violet-600 dark:text-violet-400"
                        >
                            {{ formatCurrency(subtotal) }}
                        </span>
                    </div>

                    <Button
                        @click="submitOrder"
                        :disabled="cartItems.length === 0 || isSubmitting"
                        class="flex h-10 w-full items-center justify-center gap-2 rounded-xl border-0 bg-gradient-to-r from-violet-600 to-indigo-600 text-sm font-bold text-white shadow-lg shadow-violet-200 transition-all duration-300 select-none hover:from-violet-700 hover:to-indigo-700 hover:shadow-xl dark:from-violet-500 dark:to-indigo-500 dark:shadow-none"
                    >
                        <ChefHat v-if="!isSubmitting" class="size-4" />
                        <RefreshCw v-else class="size-4 animate-spin" />
                        {{
                            isSubmitting
                                ? 'Đang chuyển bếp...'
                                : 'Xác nhận & Chuyển bếp'
                        }}
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
