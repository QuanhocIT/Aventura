<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Search,
    Utensils,
    ShoppingCart,
    Plus,
    Minus,
    Trash2,
    Notebook,
    Sparkles,
    CheckCircle2,
    ArrowLeft,
    ShoppingBag,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface Product {
    id: number;
    name: string;
    price: number;
    sku: string;
    description: string | null;
    category_id: number;
    category_name: string | null;
}

interface Category {
    id: number;
    name: string;
}

interface Restaurant {
    id: number;
    name: string;
}

interface Table {
    id: number;
    name: string;
    capacity: number;
}

const props = defineProps<{
    restaurant: Restaurant;
    table: Table;
    products: Product[];
    categories: Category[];
}>();

// States
const searchQuery = ref('');
const activeCategory = ref<number | null>(null);
const cart = ref<{ product: Product; quantity: number; notes: string }[]>([]);
const orderNote = ref('');
const isSubmitting = ref(false);
const showCartDrawer = ref(false);
const orderPlacedSuccess = ref(false);
const submittedOrderInfo = ref<{ order_number: string } | null>(null);

// Category filter + search logic
const filteredProducts = computed(() => {
    return props.products.filter((p) => {
        const matchesCategory =
            activeCategory.value === null ||
            p.category_id === activeCategory.value;
        const matchesSearch =
            p.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            (p.description &&
                p.description
                    .toLowerCase()
                    .includes(searchQuery.value.toLowerCase()));

        return matchesCategory && matchesSearch;
    });
});

// Cart operations
const cartTotalItems = computed(() => {
    return cart.value.reduce((acc, item) => acc + item.quantity, 0);
});

const cartTotalPrice = computed(() => {
    return cart.value.reduce(
        (acc, item) => acc + item.product.price * item.quantity,
        0,
    );
});

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(value);
};

const addToCart = (product: Product) => {
    const existingIndex = cart.value.findIndex(
        (item) => item.product.id === product.id,
    );

    if (existingIndex > -1) {
        cart.value[existingIndex].quantity++;
    } else {
        cart.value.push({
            product,
            quantity: 1,
            notes: '',
        });
    }

    toast.success(`Đã thêm ${product.name} vào giỏ hàng`);
};

const updateQuantity = (productId: number, delta: number) => {
    const index = cart.value.findIndex((item) => item.product.id === productId);

    if (index > -1) {
        cart.value[index].quantity += delta;

        if (cart.value[index].quantity <= 0) {
            cart.value.splice(index, 1);
            toast.info('Đã xóa món ăn khỏi giỏ hàng');
        }
    }
};

const removeItem = (productId: number) => {
    const index = cart.value.findIndex((item) => item.product.id === productId);

    if (index > -1) {
        cart.value.splice(index, 1);
        toast.info('Đã xóa món ăn khỏi giỏ hàng');
    }
};

const submitOrder = async () => {
    if (cart.value.length === 0) {
        return;
    }

    isSubmitting.value = true;

    try {
        const payload = {
            note: orderNote.value,
            items: cart.value.map((item) => ({
                product_id: item.product.id,
                quantity: item.quantity,
                notes: item.notes || null,
            })),
        };

        // Dynamically build submit URL path
        const currentPath = window.location.pathname; // Should be /order/{restaurant}/{table_token}
        const response = await axios.post(currentPath, payload);

        if (response.data.success) {
            orderPlacedSuccess.value = true;
            submittedOrderInfo.value = response.data.order;
            cart.value = [];
            orderNote.value = '';
            showCartDrawer.value = false;
            toast.success(response.data.message);
        }
    } catch (err: any) {
        console.error(err);
        const errorMsg =
            err.response?.data?.message ||
            'Có lỗi xảy ra khi gửi đơn hàng. Vui lòng thử lại.';
        toast.error(errorMsg);
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Head :title="`Thực đơn gọi món - ${restaurant.name}`" />

    <div
        class="min-h-screen bg-slate-50 pb-28 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100"
    >
        <!-- Main Layout -->
        <div
            v-if="!orderPlacedSuccess"
            class="relative mx-auto min-h-screen max-w-lg bg-white pb-4 shadow-lg dark:bg-slate-900"
        >
            <!-- Restaurant & Table Header -->
            <div
                class="relative overflow-hidden rounded-b-[2rem] bg-gradient-to-r from-violet-600 to-indigo-700 p-6 text-white shadow-md"
            >
                <div
                    class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-xl"
                ></div>
                <div
                    class="absolute -bottom-10 -left-10 h-32 w-32 rounded-full bg-white/10 blur-xl"
                ></div>

                <div class="flex items-center gap-3">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-md"
                    >
                        <Utensils class="size-6 text-white" />
                    </div>
                    <div>
                        <h1
                            class="text-xl leading-tight font-bold tracking-tight"
                        >
                            {{ restaurant.name }}
                        </h1>
                        <div class="mt-1 flex items-center gap-2">
                            <span
                                class="inline-block rounded-full bg-white/25 px-2.5 py-0.5 text-xs font-semibold backdrop-blur-sm"
                            >
                                Bàn: {{ table.name }}
                            </span>
                            <span class="text-xs text-white/80">
                                Sức chứa: {{ table.capacity }} người
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="mt-6 space-y-4 px-4">
                <div class="relative">
                    <Search
                        class="absolute top-3 left-3 size-4 text-slate-400"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Tìm kiếm món ăn ngon hôm nay..."
                        class="h-11 rounded-xl border-slate-200/80 bg-slate-50 pl-9 focus-visible:ring-violet-500 dark:border-slate-800 dark:bg-slate-950"
                    />
                </div>

                <!-- Horizontal categories -->
                <div class="flex scrollbar-none gap-2 overflow-x-auto pb-2">
                    <button
                        @click="activeCategory = null"
                        class="shrink-0 rounded-full border px-4 py-2 text-xs font-semibold transition-all"
                        :class="
                            activeCategory === null
                                ? 'border-violet-600 bg-violet-600 text-white shadow-sm shadow-violet-500/20'
                                : 'border-slate-200 bg-slate-100 text-slate-600 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                        "
                    >
                        Tất cả
                    </button>
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        @click="activeCategory = cat.id"
                        class="shrink-0 rounded-full border px-4 py-2 text-xs font-semibold transition-all"
                        :class="
                            activeCategory === cat.id
                                ? 'border-violet-600 bg-violet-600 text-white shadow-sm shadow-violet-500/20'
                                : 'border-slate-200 bg-slate-100 text-slate-600 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                        "
                    >
                        {{ cat.name }}
                    </button>
                </div>
            </div>

            <!-- Products List -->
            <div class="mt-4 space-y-3 px-4">
                <div
                    v-if="filteredProducts.length === 0"
                    class="flex flex-col items-center justify-center py-12 text-slate-400"
                >
                    <ShoppingBag
                        class="mb-2 size-12 stroke-[1.5] text-slate-300"
                    />
                    <p class="text-sm">Không tìm thấy món ăn nào</p>
                </div>

                <Card
                    v-for="product in filteredProducts"
                    :key="product.id"
                    class="overflow-hidden border-slate-100 transition-shadow duration-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent class="flex gap-4 p-4">
                        <div class="min-w-0 flex-1">
                            <h3
                                class="text-base leading-snug font-bold text-slate-800 dark:text-slate-100"
                            >
                                {{ product.name }}
                            </h3>
                            <p class="mt-0.5 text-xs text-slate-400">
                                {{ product.category_name }}
                            </p>
                            <p
                                class="mt-1.5 line-clamp-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    product.description ||
                                    'Chưa có mô tả chi tiết cho món ăn này.'
                                }}
                            </p>
                            <p
                                class="mt-2.5 font-mono text-base font-bold text-violet-600 dark:text-violet-400"
                            >
                                {{ formatCurrency(product.price) }}
                            </p>
                        </div>

                        <div class="flex flex-col justify-end">
                            <Button
                                size="sm"
                                @click="addToCart(product)"
                                class="h-9 rounded-xl bg-violet-600 px-3 text-white shadow-sm transition-transform hover:scale-105 hover:bg-violet-700"
                            >
                                <Plus class="mr-1 size-4" /> Thêm
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Floating Cart Indicator -->
            <div
                v-if="cart.length > 0"
                class="fixed bottom-6 left-1/2 z-40 flex w-[90%] max-w-md -translate-x-1/2 items-center justify-between rounded-2xl border border-white/10 bg-slate-900/90 p-4 text-white shadow-2xl backdrop-blur-md dark:bg-slate-950/95"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="relative animate-bounce rounded-xl bg-violet-600 p-2.5"
                    >
                        <ShoppingCart class="size-5" />
                        <span
                            class="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full border border-slate-900 bg-rose-500 text-[10px] font-bold text-white"
                        >
                            {{ cartTotalItems }}
                        </span>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-semibold tracking-wide text-white/60 uppercase"
                        >
                            Tạm tính
                        </p>
                        <p class="font-mono text-base font-bold">
                            {{ formatCurrency(cartTotalPrice) }}
                        </p>
                    </div>
                </div>

                <Button
                    @click="showCartDrawer = true"
                    class="h-10 rounded-xl bg-white px-5 font-bold text-slate-950 shadow-sm hover:bg-slate-100"
                >
                    Xem giỏ hàng
                </Button>
            </div>

            <!-- Cart Drawer Backdrop -->
            <div
                v-if="showCartDrawer"
                @click="showCartDrawer = false"
                class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs transition-opacity"
            ></div>

            <!-- Cart Drawer Sheet -->
            <div
                class="fixed right-0 bottom-0 left-0 z-50 mx-auto flex max-h-[85vh] max-w-lg transform flex-col overflow-y-auto rounded-t-[2rem] bg-white shadow-2xl transition-transform duration-300 dark:bg-slate-900"
                :class="showCartDrawer ? 'translate-y-0' : 'translate-y-full'"
            >
                <!-- Drawer Header -->
                <div
                    class="flex items-center justify-between border-b border-slate-100 p-6 dark:border-slate-800"
                >
                    <div class="flex items-center gap-2">
                        <ShoppingCart class="size-5 text-violet-600" />
                        <h2 class="text-lg font-bold">Chi tiết giỏ hàng</h2>
                    </div>
                    <button
                        @click="showCartDrawer = false"
                        class="rounded-full p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800"
                    >
                        <Minus class="size-5" />
                    </button>
                </div>

                <!-- Drawer Content -->
                <div class="flex-1 space-y-4 overflow-y-auto p-6">
                    <div class="space-y-3.5">
                        <div
                            v-for="item in cart"
                            :key="item.product.id"
                            class="dark:border-slate-850 flex flex-col gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3 dark:bg-slate-950"
                        >
                            <div class="flex items-start justify-between">
                                <div class="min-w-0 flex-1">
                                    <h4
                                        class="text-sm leading-tight font-bold text-slate-800 dark:text-slate-100"
                                    >
                                        {{ item.product.name }}
                                    </h4>
                                    <p
                                        class="mt-0.5 font-mono text-xs text-slate-500"
                                    >
                                        {{ formatCurrency(item.product.price) }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="
                                            updateQuantity(item.product.id, -1)
                                        "
                                        class="flex h-7 w-7 items-center justify-center rounded-lg border bg-white shadow-sm hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700"
                                    >
                                        <Minus class="size-3.5" />
                                    </button>
                                    <span
                                        class="w-6 text-center font-mono text-sm font-bold"
                                        >{{ item.quantity }}</span
                                    >
                                    <button
                                        @click="
                                            updateQuantity(item.product.id, 1)
                                        "
                                        class="flex h-7 w-7 items-center justify-center rounded-lg border bg-white shadow-sm hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700"
                                    >
                                        <Plus class="size-3.5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Notes for kitchen -->
                            <div class="mt-1.5 flex items-center gap-2">
                                <Notebook
                                    class="size-3.5 shrink-0 text-slate-400"
                                />
                                <input
                                    v-model="item.notes"
                                    placeholder="Ghi chú món (Ví dụ: Ít cay, không hành...)"
                                    class="w-full border-b border-transparent bg-transparent py-0.5 text-xs hover:border-slate-200 focus:border-violet-500 focus:outline-none dark:hover:border-slate-800"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- General Note -->
                    <div class="space-y-1.5 pt-2">
                        <label
                            class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                            >Ghi chú cho nhà bếp (chung)</label
                        >
                        <Input
                            v-model="orderNote"
                            placeholder="Ví dụ: Giao các món nước trước..."
                            class="border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950"
                        />
                    </div>

                    <!-- Total box -->
                    <div
                        class="flex items-center justify-between rounded-xl bg-violet-50 p-4 dark:bg-violet-950/20"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold text-violet-600/70 dark:text-violet-400/70"
                            >
                                TỔNG HÓA ĐƠN
                            </p>
                            <p
                                class="font-mono text-lg font-bold text-violet-700 dark:text-violet-400"
                            >
                                {{ formatCurrency(cartTotalPrice) }}
                            </p>
                        </div>
                        <div
                            class="text-right text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ cartTotalItems }} phần ăn
                        </div>
                    </div>
                </div>

                <!-- Drawer Footer -->
                <div
                    class="border-t border-slate-100 bg-slate-50/50 p-6 dark:border-slate-800 dark:bg-slate-900/50"
                >
                    <Button
                        @click="submitOrder"
                        :disabled="isSubmitting"
                        class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-violet-600 text-base font-bold text-white shadow-md transition-all hover:bg-violet-700"
                    >
                        <span
                            v-if="isSubmitting"
                            class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                        ></span>
                        <Sparkles v-else class="size-5" />
                        {{
                            isSubmitting
                                ? 'Đang gửi yêu cầu...'
                                : 'Gửi yêu cầu gọi món'
                        }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Success Screen -->
        <div
            v-else
            class="mx-auto flex min-h-screen max-w-lg flex-col justify-between bg-white p-6 shadow-lg dark:bg-slate-900"
        >
            <div></div>
            <!-- Spacer -->

            <div
                class="flex flex-col items-center justify-center space-y-6 text-center"
            >
                <div
                    class="flex h-20 w-20 animate-pulse items-center justify-center rounded-full border border-emerald-100 bg-emerald-50 text-emerald-500 dark:border-emerald-900/30 dark:bg-emerald-950/40 dark:text-emerald-400"
                >
                    <CheckCircle2 class="size-12 stroke-[1.5]" />
                </div>

                <div class="space-y-2">
                    <h2
                        class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100"
                    >
                        Gửi đơn hàng thành công!
                    </h2>
                    <p
                        class="max-w-sm text-sm leading-relaxed text-slate-500 dark:text-slate-400"
                    >
                        Đơn đệm
                        <strong
                            class="rounded bg-slate-100 px-1.5 py-0.5 font-mono font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >{{ submittedOrderInfo?.order_number }}</strong
                        >
                        đã được gửi tới hệ thống.
                    </p>
                    <p
                        class="mt-2 text-sm font-semibold text-violet-600 dark:text-violet-400"
                    >
                        Nhân viên sẽ di chuyển ra bàn để đối chiếu trực tiếp.
                    </p>
                </div>

                <div
                    class="dark:border-slate-850 w-full max-w-xs rounded-2xl border border-slate-100 bg-slate-50 p-5 dark:bg-slate-950"
                >
                    <p class="text-xs font-medium text-slate-400">
                        BÀN ĂN CỦA BẠN
                    </p>
                    <p
                        class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100"
                    >
                        {{ table.name }}
                    </p>
                    <div
                        class="dark:bg-slate-850 my-3 h-px bg-slate-200/60"
                    ></div>
                    <p
                        class="text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                    >
                        Bạn có thể gọi thêm các món ăn khác bằng cách quét lại
                        mã QR tại bàn bất kỳ lúc nào.
                    </p>
                </div>
            </div>

            <div class="w-full">
                <Button
                    @click="orderPlacedSuccess = false"
                    variant="outline"
                    class="h-12 w-full rounded-xl border-slate-200 text-slate-600 dark:border-slate-800 dark:text-slate-400"
                >
                    <ArrowLeft class="mr-2 size-4" /> Quay lại thực đơn
                </Button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Remove scrollbars */
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
.scrollbar-none {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
