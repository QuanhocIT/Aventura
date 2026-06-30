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
    Bell,
    CreditCard,
    Star,
    MessageSquare,
    User,
    Clock,
    Activity,
    Info,
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import echo from '@/lib/echo';

interface Product {
    id: number;
    name: string;
    price: number;
    sku: string;
    description: string | null;
    category_id: number;
    category_name: string | null;
    is_available: boolean;
    is_out_of_stock: boolean;
    ingredients: string[];
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
    qr_token: string;
}

interface ActiveOrderItem {
    id: number;
    product_name: string;
    quantity: number;
    status: string;
    notes: string | null;
}

interface ActiveOrder {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    total_amount: number;
    created_at: string;
    items: ActiveOrderItem[];
}

interface Staff {
    employee_id: number;
    name: string;
    role: string;
}

const props = defineProps<{
    restaurant: Restaurant;
    table: Table;
    products: Product[];
    categories: Category[];
    initialActiveOrders: ActiveOrder[];
    onDutyStaff: Staff[];
}>();

// Navigation State
const currentTab = ref<'menu' | 'tracking' | 'feedback'>('menu');

// Products and Filters State
const searchQuery = ref('');
const activeCategory = ref<number | null>(null);
const localProducts = ref<Product[]>([...props.products]);
const selectedProductDetails = ref<Product | null>(null);

// Cart State
const cart = ref<{ product: Product; quantity: number; notes: string }[]>([]);
const orderNote = ref('');
const isSubmitting = ref(false);
const showCartDrawer = ref(false);

// Real-time Active Orders
const activeOrders = ref<ActiveOrder[]>([...props.initialActiveOrders]);

// Staff Calls & Payments Loading states
const isCallingStaff = ref(false);
const isRequestingPayment = ref(false);

// Feedback Form State
const feedbackForm = ref({
    rating: 5,
    content: '',
    is_anonymous: false,
    submitted_by_name: '',
    submitted_by_phone: '',
    employee_id: null as number | null,
    employee_rating: 5,
    items: [] as { product_id: number; product_name: string; rating: number; comment: string }[],
});
const isSubmittingFeedback = ref(false);
const feedbackSuccess = ref(false);

// Category filter + search logic
const filteredProducts = computed(() => {
    return localProducts.value.filter((p) => {
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

// Unique dishes ordered in active orders to let user rate
const orderedDishes = computed(() => {
    const dishesMap = new Map<number, string>();
    activeOrders.value.forEach((order) => {
        order.items.forEach((item) => {
            // Check if product exists in initial list
            const matchedProduct = props.products.find(p => p.name === item.product_name);
            if (matchedProduct) {
                dishesMap.set(matchedProduct.id, item.product_name);
            }
        });
    });

    return Array.from(dishesMap.entries()).map(([id, name]) => ({
        id,
        name,
    }));
});

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(value);
};

const addToCart = (product: Product) => {
    if (product.is_out_of_stock) {
        toast.error('Món ăn này đã hết hàng!');
        return;
    }

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

// Open Product Detail Modal
const openProductDetails = (product: Product) => {
    selectedProductDetails.value = product;
};

// Submit Order Request
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

        const response = await axios.post(`/order/${props.restaurant.id}/${props.table.qr_token}`, payload);

        if (response.data.success) {
            cart.value = [];
            orderNote.value = '';
            showCartDrawer.value = false;

            // Push to local active orders
            if (response.data.order) {
                activeOrders.value.unshift(response.data.order);
            }

            toast.success('Gửi yêu cầu đặt món thành công!');
            // Switch to tracking tab to follow real-time status
            currentTab.value = 'tracking';
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

// Call Staff API
const callStaff = async () => {
    if (isCallingStaff.value) return;
    isCallingStaff.value = true;

    try {
        const res = await axios.post(`/order/${props.restaurant.id}/${props.table.qr_token}/call-staff`);
        if (res.data.success) {
            toast.success(res.data.message);
        }
    } catch (err: any) {
        toast.error('Không thể gửi yêu cầu gọi nhân viên. Vui lòng thử lại.');
    } finally {
        isCallingStaff.value = false;
    }
};

// Request Payment API
const requestPayment = async () => {
    if (isRequestingPayment.value) return;
    isRequestingPayment.value = true;

    try {
        const res = await axios.post(`/order/${props.restaurant.id}/${props.table.qr_token}/request-payment`);
        if (res.data.success) {
            toast.success(res.data.message);
        }
    } catch (err: any) {
        toast.error('Không thể gửi yêu cầu thanh toán. Vui lòng thử lại.');
    } finally {
        isRequestingPayment.value = false;
    }
};

// Initialize Dish Ratings Array when tab changes to feedback
const initDishFeedback = () => {
    feedbackForm.value.items = orderedDishes.value.map((dish) => ({
        product_id: dish.id,
        product_name: dish.name,
        rating: 5,
        comment: '',
    }));
};

// Submit Feedback
const submitFeedback = async () => {
    isSubmittingFeedback.value = true;

    try {
        const payload = {
            rating: feedbackForm.value.rating,
            content: feedbackForm.value.content,
            is_anonymous: feedbackForm.value.is_anonymous,
            submitted_by_name: feedbackForm.value.is_anonymous ? null : feedbackForm.value.submitted_by_name,
            submitted_by_phone: feedbackForm.value.is_anonymous ? null : feedbackForm.value.submitted_by_phone,
            restaurant_id: props.restaurant.id,
            table_id: props.table.id,
            order_id: activeOrders.value[0]?.id || null, // Associate with latest active order if any
            employee_id: feedbackForm.value.employee_id,
            employee_rating: feedbackForm.value.employee_id ? feedbackForm.value.employee_rating : null,
            items_feedback: feedbackForm.value.items.length > 0 ? feedbackForm.value.items : null,
        };

        const res = await axios.post('/feedback', payload);

        if (res.data.success) {
            feedbackSuccess.value = true;
            toast.success('Cảm ơn bạn đã gửi đánh giá!');
            // Reset form
            feedbackForm.value = {
                rating: 5,
                content: '',
                is_anonymous: false,
                submitted_by_name: '',
                submitted_by_phone: '',
                employee_id: null,
                employee_rating: 5,
                items: [],
            };
        }
    } catch (err: any) {
        const errorMsg = err.response?.data?.message || 'Không thể gửi đánh giá. Vui lòng thử lại.';
        toast.error(errorMsg);
    } finally {
        isSubmittingFeedback.value = false;
    }
};

// Translate Item Statuses to readable text with colors
const translateItemStatus = (status: string) => {
    switch (status) {
        case 'pending':
            return { text: 'Chờ duyệt', color: 'text-amber-500 bg-amber-50 border-amber-200 dark:bg-amber-950/20 dark:text-amber-400' };
        case 'sent':
        case 'preparing':
            return { text: 'Đang chế biến', color: 'text-blue-500 bg-blue-50 border-blue-200 dark:bg-blue-950/20 dark:text-blue-400' };
        case 'served':
            return { text: 'Đã lên món', color: 'text-emerald-500 bg-emerald-50 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400' };
        case 'cancelled':
            return { text: 'Đã hủy', color: 'text-rose-500 bg-rose-50 border-rose-200 dark:bg-rose-950/20 dark:text-rose-400' };
        default:
            return { text: status, color: 'text-slate-500 bg-slate-50 border-slate-200' };
    }
};

const translateOrderStatus = (status: string) => {
    switch (status) {
        case 'pending':
            return { text: 'Đơn hàng chờ nhân viên duyệt', color: 'text-amber-600 dark:text-amber-400' };
        case 'confirmed':
            return { text: 'Đơn hàng đã được xác nhận', color: 'text-sky-600 dark:text-sky-400' };
        case 'preparing':
            return { text: 'Bếp đang chế biến món ăn', color: 'text-blue-600 dark:text-blue-400' };
        case 'completed':
            return { text: 'Đã hoàn thành', color: 'text-emerald-600 dark:text-emerald-400' };
        case 'cancelled':
            return { text: 'Đã hủy bỏ', color: 'text-rose-600 dark:text-rose-400' };
        default:
            return { text: status, color: 'text-slate-600 dark:text-slate-400' };
    }
};

// WebSockets Setup
onMounted(() => {
    if (echo) {
        // 1. Listen to public stock update channel
        echo.channel(`restaurant-public.${props.restaurant.id}`)
            .listen('.product-stock.updated', (data: { product_id: number; branch_id: number; is_available: boolean }) => {
                const product = localProducts.value.find(p => p.id === data.product_id);
                if (product) {
                    product.is_available = data.is_available;
                    product.is_out_of_stock = !data.is_available;
                    toast.info(`Món '${product.name}' vừa cập nhật trạng thái kho: ${data.is_available ? 'Còn hàng' : 'Hết hàng'}`);
                }
            });

        // 2. Listen to table channel for order status updates
        echo.channel(`table.${props.table.id}`)
            .listen('.order.status-updated', (data: { order: any }) => {
                const index = activeOrders.value.findIndex(o => o.id === data.order.id);
                if (index > -1) {
                    if (data.order.status === 'completed' || data.order.status === 'cancelled' || data.order.payment_status === 'paid') {
                        activeOrders.value.splice(index, 1);
                        toast.success(`Đơn hàng ${data.order.order_number} đã hoàn thành hoặc thanh toán.`);
                    } else {
                        activeOrders.value[index] = data.order;
                        toast.info(`Trạng thái đơn hàng ${data.order.order_number} vừa thay đổi.`);
                    }
                } else {
                    if (data.order.status !== 'completed' && data.order.status !== 'cancelled' && data.order.payment_status !== 'paid') {
                        activeOrders.value.unshift(data.order);
                        toast.success(`Có cập nhật đơn hàng mới tại bàn.`);
                    }
                }
            })
            .listen('.table.interaction', (data: { type: string; message: string }) => {
                if (data.type === 'call_staff') {
                    toast.info('Nhân viên đã nhận được tín hiệu gọi bàn của bạn.');
                } else if (data.type === 'request_payment') {
                    toast.info('Yêu cầu thanh toán của bạn đang được xử lý.');
                }
            });
    }
});

onUnmounted(() => {
    if (echo) {
        echo.leave(`restaurant-public.${props.restaurant.id}`);
        echo.leave(`table.${props.table.id}`);
    }
});
</script>

<template>
    <Head :title="`Thực đơn gọi món - ${restaurant.name}`" />

    <div class="min-h-screen bg-slate-50 pb-24 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <!-- Main Layout Container -->
        <div class="relative mx-auto min-h-screen max-w-lg bg-white shadow-lg dark:bg-slate-900">
            <!-- Restaurant & Table Header -->
            <div class="sticky top-0 z-30 overflow-hidden rounded-b-[2rem] bg-gradient-to-r from-violet-600 to-indigo-700 p-5 text-white shadow-md">
                <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-xl"></div>
                <div class="absolute -bottom-10 -left-10 h-32 w-32 rounded-full bg-white/10 blur-xl"></div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/20 backdrop-blur-md">
                            <Utensils class="size-5 text-white" />
                        </div>
                        <div>
                            <h1 class="text-lg leading-tight font-bold tracking-tight">
                                {{ restaurant.name }}
                            </h1>
                            <div class="mt-0.5 flex items-center gap-2">
                                <span class="inline-block rounded-full bg-white/25 px-2 py-0.5 text-[10px] font-bold backdrop-blur-sm">
                                    Bàn: {{ table.name }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick interaction action buttons -->
                    <div class="flex gap-1.5">
                        <Button
                            size="sm"
                            variant="secondary"
                            @click="callStaff"
                            :disabled="isCallingStaff"
                            class="h-8 rounded-lg bg-white/15 px-2 text-xs font-semibold text-white backdrop-blur-xs hover:bg-white/25 border-0"
                        >
                            <span v-if="isCallingStaff" class="h-3 w-3 animate-spin rounded-full border border-white border-t-transparent"></span>
                            <Bell v-else class="mr-1 size-3.5" />
                            Gọi phục vụ
                        </Button>
                        <Button
                            size="sm"
                            variant="secondary"
                            @click="requestPayment"
                            :disabled="isRequestingPayment"
                            class="h-8 rounded-lg bg-white/15 px-2 text-xs font-semibold text-white backdrop-blur-xs hover:bg-white/25 border-0"
                        >
                            <span v-if="isRequestingPayment" class="h-3 w-3 animate-spin rounded-full border border-white border-t-transparent"></span>
                            <CreditCard v-else class="mr-1 size-3.5" />
                            Thanh toán
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="px-4 py-4">

                <!-- TAB 1: THỰC ĐƠN -->
                <div v-if="currentTab === 'menu'" class="space-y-4">
                    <!-- Search bar -->
                    <div class="relative">
                        <Search class="absolute top-3 left-3 size-4 text-slate-400" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Tìm kiếm món ăn ngon hôm nay..."
                            class="h-10 rounded-xl border-slate-200 bg-slate-50 pl-9 focus-visible:ring-violet-500 dark:border-slate-800 dark:bg-slate-950"
                        />
                    </div>

                    <!-- Categories list -->
                    <div class="flex scrollbar-none gap-2 overflow-x-auto pb-1">
                        <button
                            @click="activeCategory = null"
                            class="shrink-0 rounded-full border px-4 py-1.5 text-xs font-semibold transition-all"
                            :class="activeCategory === null
                                ? 'border-violet-600 bg-violet-600 text-white shadow-sm shadow-violet-500/20'
                                : 'border-slate-200 bg-slate-100 text-slate-600 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                        >
                            Tất cả
                        </button>
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            @click="activeCategory = cat.id"
                            class="shrink-0 rounded-full border px-4 py-1.5 text-xs font-semibold transition-all"
                            :class="activeCategory === cat.id
                                ? 'border-violet-600 bg-violet-600 text-white shadow-sm shadow-violet-500/20'
                                : 'border-slate-200 bg-slate-100 text-slate-600 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                        >
                            {{ cat.name }}
                        </button>
                    </div>

                    <!-- Products Grid -->
                    <div class="space-y-3 pt-2">
                        <div v-if="filteredProducts.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-400">
                            <ShoppingBag class="mb-2 size-10 stroke-[1.5] text-slate-300" />
                            <p class="text-sm">Không tìm thấy món ăn nào</p>
                        </div>

                        <Card
                            v-for="product in filteredProducts"
                            :key="product.id"
                            @click="openProductDetails(product)"
                            class="cursor-pointer overflow-hidden border-slate-100 transition-all hover:border-violet-200 hover:shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                        >
                            <CardContent class="flex gap-4 p-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">
                                            {{ product.name }}
                                        </h3>
                                        <span v-if="product.is_out_of_stock" class="rounded bg-rose-100 px-1.5 py-0.5 text-[9px] font-bold text-rose-600 dark:bg-rose-950/30 dark:text-rose-400">
                                            Hết hàng
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-slate-400">
                                        {{ product.category_name }}
                                    </p>
                                    <p class="mt-1.5 line-clamp-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                        {{ product.description || 'Chưa có mô tả chi tiết cho món ăn này.' }}
                                    </p>
                                    <p class="mt-2 font-mono text-base font-bold text-violet-600 dark:text-violet-400">
                                        {{ formatCurrency(product.price) }}
                                    </p>
                                </div>

                                <div class="flex flex-col justify-end" @click.stop>
                                    <Button
                                        size="sm"
                                        @click="addToCart(product)"
                                        :disabled="product.is_out_of_stock"
                                        class="h-8 rounded-lg bg-violet-600 px-3 text-xs text-white shadow-sm hover:bg-violet-700 disabled:bg-slate-100 disabled:text-slate-400 dark:disabled:bg-slate-800 dark:disabled:text-slate-600"
                                    >
                                        <Plus class="mr-1 size-3.5" /> Thêm
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>


                <!-- TAB 2: THEO DÕI MÓN -->
                <div v-else-if="currentTab === 'tracking'" class="space-y-4">
                    <div class="flex items-center gap-2 border-b border-slate-100 pb-3 dark:border-slate-850">
                        <Clock class="size-5 text-violet-600" />
                        <h2 class="text-lg font-bold">Danh sách món đang theo dõi</h2>
                    </div>

                    <div v-if="activeOrders.length === 0" class="flex flex-col items-center justify-center py-16 text-slate-400">
                        <Activity class="mb-3 size-12 stroke-[1.5] text-slate-300 animate-pulse" />
                        <p class="text-sm font-medium">Bàn ăn hiện chưa gửi yêu cầu đặt món nào</p>
                        <Button size="sm" @click="currentTab = 'menu'" class="mt-4 rounded-xl bg-violet-600 text-white">
                            Xem thực đơn & Gọi món
                        </Button>
                    </div>

                    <!-- Orders List -->
                    <div v-else class="space-y-5">
                        <div
                            v-for="order in activeOrders"
                            :key="order.id"
                            class="overflow-hidden rounded-2xl border border-slate-150 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
                        >
                            <!-- Order Info Bar -->
                            <div class="bg-slate-50 px-4 py-3 dark:bg-slate-950/40 flex items-center justify-between border-b border-slate-100 dark:border-slate-850">
                                <div>
                                    <p class="text-xs font-mono font-bold text-slate-500">
                                        Mã: {{ order.order_number }}
                                    </p>
                                    <p class="mt-0.5 text-[10px] text-slate-400">
                                        Thời gian gửi: {{ order.created_at }}
                                    </p>
                                </div>
                                <span class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold text-violet-600 dark:bg-violet-950 dark:text-violet-400">
                                    {{ order.payment_status === 'paid' ? 'Đã trả' : 'Chưa trả' }}
                                </span>
                            </div>

                            <!-- List of dishes in this order -->
                            <div class="p-4 space-y-3.5">
                                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                    {{ translateOrderStatus(order.status).text }}
                                </div>

                                <div class="space-y-2">
                                    <div
                                        v-for="item in order.items"
                                        :key="item.id"
                                        class="flex items-center justify-between rounded-lg border border-slate-50 bg-slate-50/50 p-2.5 dark:border-slate-850 dark:bg-slate-950/30"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5">
                                                <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                                    {{ item.product_name }}
                                                </h4>
                                                <span class="text-xs text-slate-400">x{{ item.quantity }}</span>
                                            </div>
                                            <p v-if="item.notes" class="mt-0.5 text-[10px] text-slate-400 italic">
                                                Ghi chú: "{{ item.notes }}"
                                            </p>
                                        </div>

                                        <span
                                            class="rounded border px-2 py-0.5 text-[10px] font-bold transition-colors"
                                            :class="translateItemStatus(item.status).color"
                                        >
                                            {{ translateItemStatus(item.status).text }}
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-2 pt-2 border-t border-slate-100 dark:border-slate-850 flex items-center justify-between">
                                    <span class="text-xs font-medium text-slate-500">Tổng cộng</span>
                                    <span class="font-mono text-sm font-bold text-violet-600 dark:text-violet-400">
                                        {{ formatCurrency(order.total_amount) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- TAB 3: ĐÁNH GIÁ -->
                <div v-else-if="currentTab === 'feedback'" class="space-y-4">
                    <div class="flex items-center gap-2 border-b border-slate-100 pb-3 dark:border-slate-850">
                        <Star class="size-5 text-violet-600" />
                        <h2 class="text-lg font-bold">Gửi phản hồi & Đánh giá</h2>
                    </div>

                    <!-- Success Screen -->
                    <div v-if="feedbackSuccess" class="rounded-2xl border border-slate-100 bg-white p-6 text-center shadow-xs dark:border-slate-800 dark:bg-slate-900 space-y-4">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-500 dark:bg-emerald-950 dark:text-emerald-400">
                            <CheckCircle2 class="size-8" />
                        </div>
                        <h3 class="text-base font-bold">Gửi đánh giá thành công!</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Cảm ơn quý khách đã dành thời gian đóng góp ý kiến. Phản hồi của quý khách đã được chuyển trực tiếp tới Ban Quản lý để cải thiện chất lượng dịch vụ.
                        </p>
                        <Button size="sm" @click="feedbackSuccess = false" variant="outline" class="rounded-xl mt-2">
                            Gửi đánh giá mới
                        </Button>
                    </div>

                    <!-- Feedback form -->
                    <form v-else @submit.prevent="submitFeedback" class="space-y-5">
                        <!-- Overall Rating -->
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">1. Đánh giá trải nghiệm chung</label>
                            <div class="flex gap-2">
                                <button
                                    v-for="star in 5"
                                    :key="star"
                                    type="button"
                                    @click="feedbackForm.rating = star"
                                    class="p-1 hover:scale-110 transition-transform"
                                >
                                    <Star
                                        class="size-8"
                                        :class="star <= feedbackForm.rating
                                            ? 'fill-amber-400 stroke-amber-400'
                                            : 'stroke-slate-300 dark:stroke-slate-700'"
                                    />
                                </button>
                            </div>
                        </div>

                        <!-- Employee rating on current shift -->
                        <div v-if="onDutyStaff.length > 0" class="space-y-2">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">2. Đánh giá nhân viên phục vụ</label>
                            <p class="text-[10px] text-slate-400 italic">Hệ thống hiển thị tự động nhân viên trực bàn hôm nay.</p>
                            
                            <div class="grid grid-cols-1 gap-2 mt-1.5">
                                <div
                                    v-for="staff in onDutyStaff"
                                    :key="staff.employee_id"
                                    @click="feedbackForm.employee_id = (feedbackForm.employee_id === staff.employee_id ? null : staff.employee_id)"
                                    class="cursor-pointer flex items-center justify-between p-3 rounded-xl border transition-all"
                                    :class="feedbackForm.employee_id === staff.employee_id
                                        ? 'border-violet-600 bg-violet-50/50 dark:bg-violet-950/20'
                                        : 'border-slate-100 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900/40'"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800">
                                            <User class="size-4" />
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-bold">{{ staff.name }}</h4>
                                            <p class="text-[10px] text-slate-400">{{ staff.role }}</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Selection Indicator & Sub star rating -->
                                    <div v-if="feedbackForm.employee_id === staff.employee_id" class="flex gap-1" @click.stop>
                                        <button
                                            v-for="star in 5"
                                            :key="star"
                                            type="button"
                                            @click="feedbackForm.employee_rating = star"
                                            class="p-0.5"
                                        >
                                            <Star
                                                class="size-4"
                                                :class="star <= feedbackForm.employee_rating
                                                    ? 'fill-amber-400 stroke-amber-400'
                                                    : 'stroke-slate-300 dark:stroke-slate-700'"
                                            />
                                        </button>
                                    </div>
                                    <div v-else class="text-[10px] text-slate-400 font-medium">Nhấn để chọn</div>
                                </div>
                            </div>
                        </div>

                        <!-- Dish Level Rating -->
                        <div v-if="orderedDishes.length > 0" class="space-y-2">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">3. Đánh giá món ăn đã gọi</label>
                            
                            <div class="space-y-3">
                                <div
                                    v-for="(item, index) in feedbackForm.items"
                                    :key="item.product_id"
                                    class="p-3 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-900/20"
                                >
                                    <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                        {{ item.product_name }}
                                    </h4>
                                    
                                    <div class="flex items-center gap-1.5 mt-2">
                                        <button
                                            v-for="star in 5"
                                            :key="star"
                                            type="button"
                                            @click="item.rating = star"
                                            class="p-0.5"
                                        >
                                            <Star
                                                class="size-4.5"
                                                :class="star <= item.rating
                                                    ? 'fill-amber-400 stroke-amber-400'
                                                    : 'stroke-slate-300 dark:stroke-slate-700'"
                                            />
                                        </button>
                                    </div>
                                    
                                    <input
                                        v-model="item.comment"
                                        placeholder="Nhận xét riêng cho món này (tùy chọn)..."
                                        class="w-full mt-2 border-b border-slate-200 bg-transparent py-1 text-xs focus:border-violet-500 focus:outline-none dark:border-slate-800"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Customer Info details -->
                        <div class="space-y-3 border-t border-slate-100 pt-3 dark:border-slate-850">
                            <!-- Anonymous toggle -->
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Gửi ẩn danh</span>
                                <input
                                    type="checkbox"
                                    v-model="feedbackForm.is_anonymous"
                                    class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                                />
                            </div>

                            <div v-if="!feedbackForm.is_anonymous" class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase">Họ và tên</label>
                                    <Input
                                        v-model="feedbackForm.submitted_by_name"
                                        placeholder="Nguyễn Văn A"
                                        class="h-8 rounded-lg text-xs"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase">Số điện thoại</label>
                                    <Input
                                        v-model="feedbackForm.submitted_by_phone"
                                        placeholder="09xx..."
                                        class="h-8 rounded-lg text-xs"
                                    />
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-400 uppercase">Ý kiến đóng góp chung</label>
                                <textarea
                                    v-model="feedbackForm.content"
                                    rows="3"
                                    placeholder="Nội dung ý kiến phản hồi về món ăn, không gian, hoặc phục vụ..."
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 p-2.5 text-xs focus:border-violet-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950"
                                ></textarea>
                            </div>
                        </div>

                        <Button
                            type="submit"
                            :disabled="isSubmittingFeedback"
                            class="w-full h-11 rounded-xl bg-violet-600 font-bold text-white hover:bg-violet-700"
                        >
                            <span v-if="isSubmittingFeedback" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                            <MessageSquare v-else class="mr-1.5 size-4" />
                            Gửi đánh giá dịch vụ
                        </Button>
                    </form>
                </div>

            </div>

            <!-- Floating Cart Indicator -->
            <div
                v-if="cart.length > 0 && currentTab === 'menu'"
                class="fixed bottom-18 left-1/2 z-40 flex w-[90%] max-w-md -translate-x-1/2 items-center justify-between rounded-2xl border border-white/10 bg-slate-900/90 p-4 text-white shadow-2xl backdrop-blur-md dark:bg-slate-950/95"
            >
                <div class="flex items-center gap-3">
                    <div class="relative animate-bounce rounded-xl bg-violet-600 p-2.5">
                        <ShoppingCart class="size-5" />
                        <span class="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full border border-slate-900 bg-rose-500 text-[10px] font-bold text-white">
                            {{ cartTotalItems }}
                        </span>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold tracking-wide text-white/60 uppercase">Tạm tính</p>
                        <p class="font-mono text-base font-bold">{{ formatCurrency(cartTotalPrice) }}</p>
                    </div>
                </div>

                <Button
                    @click="showCartDrawer = true"
                    class="h-9 rounded-xl bg-white px-4 font-bold text-slate-950 shadow-sm hover:bg-slate-100 text-xs"
                >
                    Xem giỏ hàng
                </Button>
            </div>

            <!-- Cart Drawer Backdrop -->
            <div v-if="showCartDrawer" @click="showCartDrawer = false" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs transition-opacity"></div>

            <!-- Cart Drawer Sheet -->
            <div
                class="fixed right-0 bottom-0 left-0 z-50 mx-auto flex max-h-[80vh] max-w-lg transform flex-col overflow-y-auto rounded-t-[2rem] bg-white shadow-2xl transition-transform duration-300 dark:bg-slate-900"
                :class="showCartDrawer ? 'translate-y-0' : 'translate-y-full'"
            >
                <div class="flex items-center justify-between border-b border-slate-100 p-5 dark:border-slate-850">
                    <div class="flex items-center gap-2">
                        <ShoppingCart class="size-5 text-violet-600" />
                        <h2 class="text-base font-bold">Chi tiết giỏ hàng</h2>
                    </div>
                    <button @click="showCartDrawer = false" class="rounded-full p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">
                        <Minus class="size-4" />
                    </button>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto p-5">
                    <div class="space-y-2.5">
                        <div
                            v-for="item in cart"
                            :key="item.product.id"
                            class="flex flex-col gap-2 rounded-xl border border-slate-100 bg-slate-50/50 p-3 dark:border-slate-800 dark:bg-slate-950/20"
                        >
                            <div class="flex items-start justify-between">
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                        {{ item.product.name }}
                                    </h4>
                                    <p class="mt-0.5 font-mono text-[11px] text-slate-500">
                                        {{ formatCurrency(item.product.price) }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="updateQuantity(item.product.id, -1)"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg border bg-white shadow-xs hover:bg-slate-100 dark:bg-slate-850 dark:hover:bg-slate-800"
                                    >
                                        <Minus class="size-3" />
                                    </button>
                                    <span class="w-6 text-center font-mono text-xs font-bold">{{ item.quantity }}</span>
                                    <button
                                        @click="updateQuantity(item.product.id, 1)"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg border bg-white shadow-xs hover:bg-slate-100 dark:bg-slate-850 dark:hover:bg-slate-800"
                                    >
                                        <Plus class="size-3" />
                                    </button>
                                </div>
                            </div>

                            <div class="mt-1 flex items-center gap-1.5">
                                <Notebook class="size-3 text-slate-400 shrink-0" />
                                <input
                                    v-model="item.notes"
                                    placeholder="Ghi chú (Ví dụ: Ít cay, không hành...)"
                                    class="w-full border-b border-transparent bg-transparent py-0.5 text-[11px] focus:border-slate-200 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- General Note -->
                    <div class="space-y-1.5 pt-2">
                        <label class="text-[10px] font-semibold text-slate-500 uppercase">Ghi chú cho bếp (chung)</label>
                        <Input v-model="orderNote" placeholder="Ví dụ: Giao các món nước trước..." class="h-9 rounded-lg" />
                    </div>

                    <!-- Total Cost -->
                    <div class="flex items-center justify-between rounded-xl bg-violet-50 p-4 dark:bg-violet-950/20">
                        <div>
                            <p class="text-[10px] font-semibold text-violet-600/70 dark:text-violet-400/70 uppercase">TỔNG HÓA ĐƠN</p>
                            <p class="font-mono text-base font-bold text-violet-700 dark:text-violet-400">{{ formatCurrency(cartTotalPrice) }}</p>
                        </div>
                        <div class="text-right text-xs text-slate-500 dark:text-slate-400">
                            {{ cartTotalItems }} phần ăn
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-150 bg-slate-50/50 p-5 dark:border-slate-800 dark:bg-slate-900/50">
                    <Button
                        @click="submitOrder"
                        :disabled="isSubmitting"
                        class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-violet-600 text-sm font-bold text-white shadow-md hover:bg-violet-700"
                    >
                        <span v-if="isSubmitting" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                        <Sparkles v-else class="size-4" />
                        Gửi yêu cầu gọi món
                    </Button>
                </div>
            </div>

            <!-- Product Detail Modal -->
            <div v-if="selectedProductDetails" @click="selectedProductDetails = null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
                <Card @click.stop class="w-full max-w-sm overflow-hidden border-slate-100 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                    <CardContent class="p-6 space-y-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="rounded bg-violet-100 px-2 py-0.5 text-[10px] font-bold text-violet-600 dark:bg-violet-950 dark:text-violet-400">
                                    {{ selectedProductDetails.category_name }}
                                </span>
                                <h3 class="mt-1.5 text-lg font-bold text-slate-800 dark:text-slate-100">
                                    {{ selectedProductDetails.name }}
                                </h3>
                            </div>
                            <span v-if="selectedProductDetails.is_out_of_stock" class="rounded bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-600 dark:bg-rose-950/30 dark:text-rose-400">
                                Hết hàng
                            </span>
                        </div>

                        <!-- Product info -->
                        <div class="space-y-3">
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-400 uppercase">Mô tả chi tiết</label>
                                <p class="text-xs text-slate-500 leading-relaxed">
                                    {{ selectedProductDetails.description || 'Chưa có mô tả chi tiết cho món ăn này.' }}
                                </p>
                            </div>

                            <!-- dynamic ingredients list -->
                            <div v-if="selectedProductDetails.ingredients.length > 0" class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-400 uppercase">Thành phần nguyên liệu</label>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="ing in selectedProductDetails.ingredients"
                                        :key="ing"
                                        class="rounded bg-slate-100 px-2 py-0.5 text-[10px] text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        {{ ing }}
                                    </span>
                                </div>
                            </div>

                            <!-- Mock Flavor details for detail richness -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-400 uppercase">Hương vị & Đặc trưng</label>
                                <p class="text-xs text-slate-500">Hương vị nguyên bản đậm đà, chế biến sạch trong ngày.</p>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 dark:border-slate-850 flex items-center justify-between">
                            <span class="font-mono text-lg font-bold text-violet-600 dark:text-violet-400">
                                {{ formatCurrency(selectedProductDetails.price) }}
                            </span>
                            <div class="flex gap-2">
                                <Button size="sm" variant="outline" @click="selectedProductDetails = null" class="rounded-lg text-xs">
                                    Đóng
                                </Button>
                                <Button
                                    size="sm"
                                    @click="addToCart(selectedProductDetails); selectedProductDetails = null"
                                    :disabled="selectedProductDetails.is_out_of_stock"
                                    class="rounded-lg bg-violet-600 text-white shadow-sm text-xs"
                                >
                                    Thêm vào giỏ
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Sticky Bottom Navigation Bar (Tab Selector) -->
            <div class="sticky bottom-0 z-40 flex w-full max-w-lg items-center justify-around border-t border-slate-100 bg-white/95 py-3 text-slate-500 shadow-2xl backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/95">
                <button
                    @click="currentTab = 'menu'"
                    class="flex flex-col items-center gap-1 transition-colors"
                    :class="currentTab === 'menu' ? 'text-violet-600 font-bold' : 'hover:text-slate-800 dark:hover:text-slate-200'"
                >
                    <Utensils class="size-5" />
                    <span class="text-[10px]">Thực đơn</span>
                </button>

                <button
                    @click="currentTab = 'tracking'"
                    class="flex flex-col items-center gap-1 transition-colors"
                    :class="currentTab === 'tracking' ? 'text-violet-600 font-bold' : 'hover:text-slate-800 dark:hover:text-slate-200'"
                >
                    <div class="relative">
                        <Clock class="size-5" />
                        <span v-if="activeOrders.length > 0" class="absolute -top-1.5 -right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-violet-600 text-[8px] font-bold text-white">
                            {{ activeOrders.length }}
                        </span>
                    </div>
                    <span class="text-[10px]">Theo dõi món</span>
                </button>

                <button
                    @click="currentTab = 'feedback'; initDishFeedback();"
                    class="flex flex-col items-center gap-1 transition-colors"
                    :class="currentTab === 'feedback' ? 'text-violet-600 font-bold' : 'hover:text-slate-800 dark:hover:text-slate-200'"
                >
                    <Star class="size-5" />
                    <span class="text-[10px]">Đánh giá</span>
                </button>
            </div>

        </div>
    </div>
</template>

<style scoped>
/* Hidden scrollbar */
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
.scrollbar-none {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
