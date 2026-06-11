<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    Search,
    Sparkles,
    ShoppingBag,
    Plus,
    Minus,
    Trash2,
    ChefHat,
    HelpCircle,
    UtensilsCrossed,
    CalendarDays,
    RefreshCw,
    AlertCircle,
    Lightbulb,
    Check,
    ChevronRight,
    User,
    AlertTriangle,
    LayoutGrid,
    ArrowLeft,
} from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
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
    status: string;
    capacity: number;
}

const props = defineProps<{
    products: Product[];
    categories: Category[];
    tables: Table[];
}>();

// --- STATE ---
const searchQuery = ref('');
const selectedCategoryId = ref<number | null>(null);
const selectedTableId = ref<number | 'takeaway' | null>(null);
const cartItems = ref<
    Array<{ product: Product; quantity: number; notes: string }>
>([]);
const isCartBouncing = ref(false);
const showConfirmModal = ref(false);

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

const totalCartQuantity = computed(() => {
    return cartItems.value.reduce((s, i) => s + i.quantity, 0);
});

const currentTableName = computed(() => {
    if (selectedTableId.value === 'takeaway') return 'Mang về';
    const tbl = props.tables.find((t) => t.id === selectedTableId.value);
    return tbl ? tbl.name : '';
});

// Watch cart total quantity to trigger bouncing animation
watch(totalCartQuantity, (newVal, oldVal) => {
    if (newVal !== oldVal) {
        isCartBouncing.value = true;
        setTimeout(() => {
            isCartBouncing.value = false;
        }, 300);
    }
});

// --- ACTIONS ---
const selectCategory = (id: number | null) => {
    selectedCategoryId.value = id;
};

// Add product to cart
const addToCart = (product: Product) => {
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

// Open order review modal
const openConfirm = () => {
    if (cartItems.value.length === 0) {
        alert(
            'Giỏ hàng trống! Hãy chọn ít nhất một món ăn trước khi xác nhận.',
        );
        return;
    }
    showConfirmModal.value = true;
};

// Form submit helper
const submitOrder = () => {
    if (cartItems.value.length === 0) {
        alert(
            'Giỏ hàng trống! Hãy chọn ít nhất một món ăn trước khi chuyển bếp.',
        );
        return;
    }

    const form = useForm({
        table_id:
            selectedTableId.value === 'takeaway' ? null : selectedTableId.value,
        note: '',
        items: cartItems.value.map((item) => ({
            product_id: item.product.id,
            quantity: item.quantity,
            notes: item.notes,
        })),
    });

    form.post('/orders', {
        onSuccess: () => {
            cartItems.value = [];
            selectedTableId.value = null;
            showConfirmModal.value = false;
        },
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
    if (!aiSuggestion.value || !aiSuggestion.value.recommended_item) return;

    const recommendedName = aiSuggestion.value.recommended_item;
    // Find the product in props.products
    const product = props.products.find((p) => p.name === recommendedName);
    if (product) {
        addToCart(product);
    } else {
        alert(
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

// Handle table selection
const handleTableSelect = (table: Table) => {
    if (table.status !== 'available') {
        alert(
            'Bàn này đang có khách hoặc ngưng sử dụng. Vui lòng chọn bàn đang trống.',
        );
        return;
    }
    selectedTableId.value = table.id;
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
    selectedTableId.value = null; // Start with Table Map view
});
</script>

<template>
    <Head title="Tạo Đơn Hàng & Sơ Đồ Bàn" />

    <div
        class="mx-auto flex h-[calc(100vh-4rem)] w-full max-w-[1600px] flex-col gap-4 overflow-hidden p-4"
    >
        <!-- VIEW 1: TABLE MAP -->
        <div
            v-if="!selectedTableId"
            class="flex min-h-0 flex-1 flex-col items-center justify-center gap-6 overflow-y-auto rounded-2xl border bg-slate-50 p-6 dark:bg-slate-900/40"
        >
            <div class="max-w-xl space-y-2 text-center">
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-teal-50 text-teal-600 shadow-sm dark:bg-teal-950/60 dark:text-teal-400"
                >
                    <LayoutGrid class="size-7" />
                </div>
                <h2
                    class="text-2xl font-black tracking-tight text-slate-800 dark:text-slate-100"
                >
                    SƠ ĐỒ BÀN KHU VỰC CHÍNH
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Hệ thống hiển thị trạng thái thực tế của các bàn. Chọn bàn
                    trống (màu xanh) để tiến hành lên đơn gọi món.
                </p>
            </div>

            <!-- Legends -->
            <div
                class="flex flex-wrap items-center justify-center gap-6 rounded-2xl border bg-white px-5 py-3 text-xs font-semibold text-slate-600 shadow-xs dark:bg-slate-900 dark:text-slate-400"
            >
                <span class="flex items-center gap-2">
                    <span class="size-3 rounded-full bg-emerald-500" />
                    Bàn Trống (Mở đơn)
                </span>
                <span class="flex items-center gap-2">
                    <span class="size-3 rounded-full bg-rose-500" />
                    Có Khách (Đang dùng)
                </span>
                <span class="flex items-center gap-2">
                    <span class="size-3 rounded-full bg-slate-400" />
                    Ngưng Dùng
                </span>
            </div>

            <!-- 20 Tables Alphabetical Grid -->
            <div
                class="grid w-full max-w-4xl grid-cols-2 gap-4 p-2 sm:grid-cols-4 md:grid-cols-5"
            >
                <div
                    v-for="table in tables"
                    :key="table.id"
                    @click="handleTableSelect(table)"
                    :class="[
                        'relative flex h-32 flex-col items-center justify-between rounded-2xl border p-4 shadow-sm transition-all duration-300 select-none',
                        table.status === 'available'
                            ? 'scale-100 cursor-pointer border-emerald-200 bg-emerald-50/40 hover:-translate-y-1 hover:border-emerald-400 hover:bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/10 dark:hover:bg-emerald-950/20'
                            : table.status === 'occupied'
                              ? 'border-rose-250 cursor-not-allowed bg-rose-50/30 opacity-90 dark:border-rose-950/40 dark:bg-rose-950/10'
                              : 'cursor-not-allowed border-slate-200 bg-slate-100 opacity-40 dark:bg-slate-800/50',
                    ]"
                >
                    <div class="flex w-full items-center justify-between">
                        <span
                            class="text-[9px] font-extrabold tracking-wider"
                            :class="
                                table.status === 'available'
                                    ? 'text-emerald-700 dark:text-emerald-400'
                                    : 'text-slate-400'
                            "
                        >
                            {{
                                table.status === 'available'
                                    ? 'TRỐNG'
                                    : table.status === 'occupied'
                                      ? 'CÓ KHÁCH'
                                      : 'K.DÙNG'
                            }}
                        </span>
                        <span
                            class="size-2.5 rounded-full"
                            :class="
                                table.status === 'available'
                                    ? 'animate-pulse bg-emerald-500'
                                    : table.status === 'occupied'
                                      ? 'bg-rose-500'
                                      : 'bg-slate-400'
                            "
                        />
                    </div>

                    <div class="my-1 text-center">
                        <span
                            class="text-3xl font-black tracking-tight text-slate-800 dark:text-slate-100"
                        >
                            {{ table.name }}
                        </span>
                        <span
                            class="mt-0.5 block text-[10px] font-bold text-slate-400 dark:text-slate-500"
                        >
                            {{ table.capacity }} CHỖ
                        </span>
                    </div>

                    <span
                        v-if="table.status === 'available'"
                        class="text-[9px] font-black text-emerald-600 uppercase dark:text-emerald-400"
                    >
                        Vào Đơn
                    </span>
                    <span
                        v-else
                        class="text-[9px] font-bold text-slate-400 uppercase"
                    >
                        Khóa
                    </span>
                </div>
            </div>

            <!-- Takeaway options -->
            <div
                class="flex w-full max-w-4xl justify-center border-t border-slate-200 pt-5 dark:border-slate-800"
            >
                <button
                    @click="selectedTableId = 'takeaway'"
                    class="flex items-center gap-2 rounded-xl border border-dashed bg-white px-8 py-3 text-xs font-bold text-slate-500 shadow-sm transition-all hover:border-violet-400 hover:bg-violet-50/10 hover:text-violet-600 dark:bg-slate-900 dark:text-slate-400"
                >
                    <ShoppingBag class="size-4 text-violet-500" />
                    Tạo đơn mang về (Takeaway - Không chọn bàn)
                </button>
            </div>
        </div>

        <!-- VIEW 2: POS MENU SELECTION -->
        <div v-else class="flex flex-1 flex-col gap-4 overflow-hidden">
            <!-- POS Top Area -->
            <div
                class="flex shrink-0 flex-col items-start justify-between gap-3 border-b pb-3 sm:flex-row sm:items-center"
            >
                <div class="flex items-center gap-3">
                    <button
                        @click="selectedTableId = null"
                        class="group rounded-xl border p-2 transition-colors hover:bg-slate-100 dark:hover:bg-slate-900"
                        title="Quay lại sơ đồ bàn"
                    >
                        <ArrowLeft
                            class="size-4 text-slate-600 transition-transform group-hover:-translate-x-0.5 dark:text-slate-300"
                        />
                    </button>
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-100 text-violet-600 dark:bg-violet-950/60 dark:text-violet-400"
                    >
                        <ChefHat class="size-4.5" />
                    </div>
                    <div>
                        <h1
                            class="flex items-center gap-2 text-lg font-black tracking-tight"
                        >
                            Menu Gọi Món —
                            <span
                                class="rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-extrabold text-violet-700 dark:bg-violet-900 dark:text-violet-300"
                            >
                                BÀN {{ currentTableName }}
                            </span>
                        </h1>
                        <p
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            Thêm đồ ăn nước uống vào giỏ hàng và chuyển xuống
                            bếp.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="selectedTableId = null"
                        class="h-9 text-xs font-bold text-slate-600 dark:text-slate-300"
                    >
                        <LayoutGrid class="mr-1.5 size-3.5" /> Sơ đồ bàn
                    </Button>
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
                                class="h-9 border-slate-200 bg-white pl-9 dark:border-slate-800"
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
                                    'dark:border-slate-850 shrink-0 rounded-full border-slate-200 text-xs font-semibold transition-all',
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
                                    'dark:border-slate-850 shrink-0 rounded-full border-slate-200 text-xs font-semibold transition-all',
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
                                class="group relative flex cursor-pointer flex-col justify-between overflow-hidden rounded-xl border border-slate-200 bg-card transition-all duration-300 select-none hover:-translate-y-0.5 hover:shadow-md active:translate-y-0 dark:border-slate-800"
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
                                Hãy thử tìm kiếm từ khóa khác hoặc chuyển danh
                                mục hiển thị.
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
                                class="badge-cart rounded-full bg-violet-100 px-2 py-0.5 text-xs font-semibold text-violet-700 dark:bg-violet-900/60 dark:text-violet-300"
                                :class="{ 'animate-cart-pop': isCartBouncing }"
                            >
                                {{ totalCartQuantity }}
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
                                            {{
                                                formatCurrency(
                                                    item.product.price,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="border-slate-150 flex shrink-0 items-center gap-1.5 rounded-lg border bg-slate-50 p-0.5 dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="
                                                decreaseQuantity(
                                                    item.product.id,
                                                )
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
                                Hãy click chọn các món bên menu để bắt đầu
                                order.
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
                                <span
                                    class="text-[11px] tracking-wider uppercase"
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
                                            aiSuggestion.source
                                                .split('(')[0]
                                                .trim()
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
                                    Mời thêm món vào giỏ hàng để AI tự động đề
                                    xuất combo tăng doanh số.
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
                            @click="openConfirm"
                            :disabled="cartItems.length === 0"
                            class="flex h-10 w-full items-center justify-center gap-2 rounded-xl border-0 bg-gradient-to-r from-violet-600 to-indigo-600 text-sm font-extrabold text-white shadow-lg shadow-violet-200 transition-all duration-300 select-none hover:from-violet-700 hover:to-indigo-700 hover:shadow-xl dark:from-violet-500 dark:to-indigo-500 dark:shadow-none"
                        >
                            <ChefHat class="size-4" />
                            Xác nhận đơn hàng
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- DIALOG/MODAL: ORDER CONFIRMATION -->
        <div
            v-if="showConfirmModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-2xl animate-in overflow-hidden border border-slate-200 shadow-2xl duration-200 zoom-in-95 fade-in dark:border-slate-800"
            >
                <CardHeader
                    class="border-b bg-gradient-to-r from-slate-50 to-slate-100 pb-4 dark:from-slate-900 dark:to-slate-900/60"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <UtensilsCrossed
                                class="size-5 text-violet-600 dark:text-violet-400"
                            />
                            <CardTitle
                                class="text-base font-black text-slate-800 dark:text-slate-100"
                            >
                                XÁC NHẬN GỬI ĐƠN HÀNG — BÀN
                                {{ currentTableName }}
                            </CardTitle>
                        </div>
                        <button
                            @click="showConfirmModal = false"
                            class="rounded-lg p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                    <CardDescription class="text-xs"
                        >Rà soát lại danh sách món và chỉnh sửa trước khi chuyển
                        bếp chế biến.</CardDescription
                    >
                </CardHeader>

                <CardContent
                    class="max-h-[60vh] scrollbar-thin space-y-4 overflow-y-auto p-6"
                >
                    <div
                        class="overflow-hidden rounded-xl border bg-white dark:bg-slate-950"
                    >
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b bg-slate-50 text-[10px] font-extrabold tracking-wider text-slate-500 uppercase dark:bg-slate-900"
                                >
                                    <th class="p-3">Món Ăn / Đồ Uống</th>
                                    <th class="p-3 text-right">Đơn Giá</th>
                                    <th class="w-28 p-3 text-center">
                                        Số Lượng
                                    </th>
                                    <th class="p-3 text-right">Thành Tiền</th>
                                    <th class="p-3 text-center">Xóa</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr
                                    v-for="item in cartItems"
                                    :key="item.product.id"
                                    class="hover:bg-slate-50/50 dark:hover:bg-slate-900/10"
                                >
                                    <td class="p-3">
                                        <p
                                            class="font-bold text-slate-800 dark:text-slate-200"
                                        >
                                            {{ item.product.name }}
                                        </p>
                                        <span
                                            v-if="item.product.sku"
                                            class="dark:text-slate-550 font-mono text-[9px] font-semibold text-slate-400"
                                            >{{ item.product.sku }}</span
                                        >
                                        <p
                                            v-if="item.notes"
                                            class="mt-0.5 text-[10px] font-semibold text-violet-600 italic dark:text-violet-400"
                                        >
                                            Ghi chú: {{ item.notes }}
                                        </p>
                                    </td>
                                    <td
                                        class="text-slate-650 dark:text-slate-350 p-3 text-right font-semibold"
                                    >
                                        {{ formatCurrency(item.product.price) }}
                                    </td>
                                    <td class="p-3">
                                        <div
                                            class="flex items-center justify-center gap-1.5 rounded-lg border bg-slate-50 p-0.5 dark:bg-slate-900"
                                        >
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                @click="
                                                    decreaseQuantity(
                                                        item.product.id,
                                                    )
                                                "
                                                class="h-6 w-6 shrink-0 rounded-md hover:bg-white dark:hover:bg-slate-800"
                                            >
                                                <Minus class="size-3" />
                                            </Button>
                                            <span
                                                class="text-slate-850 w-6 text-center text-xs font-bold dark:text-slate-200"
                                            >
                                                {{ item.quantity }}
                                            </span>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                @click="addToCart(item.product)"
                                                class="h-6 w-6 shrink-0 rounded-md hover:bg-white dark:hover:bg-slate-800"
                                            >
                                                <Plus class="size-3" />
                                            </Button>
                                        </div>
                                    </td>
                                    <td
                                        class="text-slate-850 p-3 text-right font-extrabold dark:text-slate-100"
                                    >
                                        {{
                                            formatCurrency(
                                                item.product.price *
                                                    item.quantity,
                                            )
                                        }}
                                    </td>
                                    <td class="p-3 text-center">
                                        <button
                                            @click="
                                                removeFromCart(item.product.id)
                                            "
                                            class="rounded-lg p-1.5 text-rose-500 transition-colors hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/20"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Total summary -->
                    <div class="flex flex-col gap-1 border-t pt-4 text-right">
                        <div
                            class="flex items-center justify-between text-xs font-bold text-slate-500"
                        >
                            <span>Tạm tính món:</span>
                            <span
                                class="font-semibold text-slate-800 dark:text-slate-200"
                                >{{ formatCurrency(subtotal) }}</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-between text-xs font-bold text-slate-500"
                        >
                            <span>Thuế VAT (10%):</span>
                            <span
                                class="font-semibold text-slate-800 dark:text-slate-200"
                                >{{ formatCurrency(0) }}</span
                            >
                        </div>
                        <div
                            class="dark:text-slate-150 mt-1 flex items-center justify-between border-t pt-2 text-sm font-black text-slate-800"
                        >
                            <span class="tracking-wider uppercase"
                                >Tổng tiền hóa đơn:</span
                            >
                            <span
                                class="text-violet-650 text-xl font-extrabold dark:text-violet-400"
                            >
                                {{ formatCurrency(subtotal) }}
                            </span>
                        </div>
                    </div>
                </CardContent>

                <!-- Actions -->
                <div
                    class="flex shrink-0 justify-end gap-3 border-t bg-slate-50 p-6 dark:bg-slate-900/40"
                >
                    <Button
                        type="button"
                        variant="outline"
                        @click="showConfirmModal = false"
                        class="h-10 px-5 text-xs font-bold"
                    >
                        Hủy quay lại
                    </Button>
                    <Button
                        @click="submitOrder"
                        class="h-10 rounded-xl border-0 bg-gradient-to-r from-violet-600 to-indigo-600 px-6 text-xs font-extrabold text-white shadow-md hover:from-violet-700 hover:to-indigo-700"
                    >
                        <UtensilsCrossed class="mr-2 size-3.5" />
                        Xác nhận & Chuyển bếp
                    </Button>
                </div>
            </Card>
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

/* Cart Badge Bounce Animation */
@keyframes cart-pop {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.4);
    }
    100% {
        transform: scale(1);
    }
}
.animate-cart-pop {
    animation: cart-pop 0.3s ease-in-out;
}
</style>
