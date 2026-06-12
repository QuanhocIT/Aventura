<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';
import {
    Utensils,
    ShoppingCart,
    Plus,
    Minus,
    Bell,
    CreditCard,
    Star,
    Check,
    Loader2,
    X,
    Clock,
    ChevronDown,
    MessageSquare,
    AlertTriangle,
    ThumbsUp,
    Store,
    HeartHandshake
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

interface Product {
    id: number;
    name: string;
    description: string | null;
    price: number;
    image_url: string | null;
    sku: string;
    category_id: number;
    in_stock: boolean;
    paused_until: string | null;
    out_of_stock_until: string | null;
    is_kitchen_paused: boolean;
    is_kitchen_out_of_stock: boolean;
}

interface Category {
    id: number;
    name: string;
    slug: string;
}

interface CartDataItem {
    product_id: number;
    quantity: number;
    name: string;
    notes: string | null;
}

interface ActiveOrder {
    id: number;
    status: 'waiting_verification' | 'escalated' | 'confirmed' | 'cancelled';
    total_amount: number;
    cart_data: CartDataItem[];
    order_id: number | null;
    order_number: string | null;
    order_status: string | null;
    payment_status: string | null;
    items_status: { name: string; quantity: number; status: string }[];
    created_at: string;
}

interface Staff {
    employee_id: number;
    name: string;
    role: string;
}

const props = defineProps<{
    restaurant: { id: number; name: string; logo_url: string | null };
    table: { id: number; name: string; capacity: number; area_id: number; area_name: string; qr_token: string };
    categories: Category[];
    products: Product[];
    activeTempOrders: ActiveOrder[];
    staffList: Staff[];
}>();

// App State
const selectedCategoryId = ref<number | null>(props.categories[0]?.id ?? null);
const cart = ref<{ product: Product; quantity: number; notes: string }[]>([]);
const isCartOpen = ref(false);
const isOrdering = ref(false);
const customerName = ref('');
const customerPhone = ref('');

const customerLoyalty = ref<any>(null);
const isSearchingLoyalty = ref(false);

const lookupCustomerLoyalty = async () => {
    const phone = customerPhone.value.trim();
    if (phone.length < 10) {
        customerLoyalty.value = null;
        return;
    }
    isSearchingLoyalty.value = true;
    try {
        const res = await axios.get(`/api/customers/search?phone=${phone}`);
        if (res.data.success) {
            customerLoyalty.value = res.data.customer;
            if (!customerName.value.trim() && res.data.customer.full_name) {
                customerName.value = res.data.customer.full_name;
            }
            toast.success(`Đã nhận diện thành viên: ${res.data.customer.full_name}`);
        } else {
            customerLoyalty.value = null;
        }
    } catch (e) {
        customerLoyalty.value = null;
    } finally {
        isSearchingLoyalty.value = false;
    }
};

watch(customerPhone, () => {
    if (customerPhone.value.trim().length >= 10) {
        lookupCustomerLoyalty();
    } else {
        customerLoyalty.value = null;
    }
});

// Behavior Tracking
const sessionToken = ref('');

function getOrGenerateSessionToken() {
    let token = sessionStorage.getItem('cdp_session_token');
    if (!token) {
        token = 'sess_' + Math.random().toString(36).substring(2, 15) + '_' + Date.now();
        sessionStorage.setItem('cdp_session_token', token);
    }
    sessionToken.value = token;
}

async function trackBehavior(eventType: string, productId: number | null = null, quantity: number | null = null, extraMeta: Record<string, any> | null = null) {
    try {
        await axios.post('/api/customer/track-behavior', {
            restaurant_id: props.restaurant.id,
            session_id: sessionToken.value,
            event_type: eventType,
            product_id: productId,
            quantity: quantity,
            meta_data: {
                url: window.location.href,
                table_name: props.table.name,
                ...extraMeta
            },
            customer_phone: customerPhone.value.trim() || null
        });
    } catch (err) {
        console.error('Tracking failed:', err);
    }
}

// Item detail modal (notes & quantity selection)
const isItemModalOpen = ref(false);
const modalProduct = ref<Product | null>(null);
const modalQuantity = ref(1);
const modalNotes = ref('');

// Interactions status
const isCallingStaff = ref(false);
const isRequestingPayment = ref(false);

// VietQR Payment status
const isQrPaymentModalOpen = ref(false);
const paymentQrUrl = ref('');
const paymentQrOrder = ref<any>(null);
const paymentSuccess = ref(false);
const paymentTimer = ref<any>(null);
const isSimulatingPayment = ref(false);

const openQrPaymentModal = async (order: any) => {
    paymentQrOrder.value = order;
    paymentQrUrl.value = '';
    paymentSuccess.value = false;
    isQrPaymentModalOpen.value = true;
    
    try {
        const res = await axios.get(`/api/orders/${order.order_id}/payment-qr`);
        if (res.data.success) {
            paymentQrUrl.value = res.data.qr_url;
            startPaymentPolling(order.order_id);
        } else {
            toast.error('Không thể tạo mã QR thanh toán.');
        }
    } catch (e) {
        toast.error('Lỗi kết nối khi sinh mã QR.');
    }
};

const closeQrPaymentModal = () => {
    isQrPaymentModalOpen.value = false;
    if (paymentTimer.value) {
        clearInterval(paymentTimer.value);
        paymentTimer.value = null;
    }
};

const startPaymentPolling = (orderId: number) => {
    if (paymentTimer.value) clearInterval(paymentTimer.value);
    
    paymentTimer.value = setInterval(async () => {
        try {
            const res = await axios.get(`/api/orders/${orderId}/payment-status`);
            if (res.data.success && res.data.is_paid) {
                paymentSuccess.value = true;
                toast.success('Thanh toán thành công!');
                clearInterval(paymentTimer.value);
                paymentTimer.value = null;
                
                setTimeout(() => {
                    closeQrPaymentModal();
                    refetchActiveOrders();
                }, 2000);
            }
        } catch (e) {
            console.error('Error polling status:', e);
        }
    }, 3000);
};

const simulatePaymentSuccess = async () => {
    if (!paymentQrOrder.value) return;
    isSimulatingPayment.value = true;
    try {
        const res = await axios.post('/api/webhooks/payments/vietqr', {
            description: `AVTORD${paymentQrOrder.value.order_id}`
        });
        if (res.data.success) {
            toast.success('Gửi webhook giả lập thành công!');
        } else {
            toast.error(res.data.message || 'Lỗi giả lập thanh toán.');
        }
    } catch (e) {
        toast.error('Lỗi khi gọi API webhook giả lập.');
    } finally {
        isSimulatingPayment.value = false;
    }
};


// Feedback rating state
const showFeedbackSection = ref(false);
const feedbackRating = ref(5);
const feedbackContent = ref('');
const itemsRating = ref<Record<number, { rating: number; comment: string }>>({});
const staffRating = ref<Record<number, { rating: number; comment: string }>>({});
const isSubmittingFeedback = ref(false);
const feedbackSubmittedSuccessfully = ref(false);

// Filtered products
const filteredProducts = computed(() => {
    if (!selectedCategoryId.value) return props.products;
    return props.products.filter(p => p.category_id === selectedCategoryId.value);
});

// Cart computed
const cartTotalItems = computed(() => cart.value.reduce((acc, item) => acc + item.quantity, 0));
const cartTotalPrice = computed(() => cart.value.reduce((acc, item) => acc + (item.product.price * item.quantity), 0));

// Helpers
function formatCurrency(val: number) {
    return val.toLocaleString('vi-VN') + 'đ';
}

function selectCategory(id: number) {
    selectedCategoryId.value = id;
    const el = document.getElementById(`category-section-${id}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
}

// Add/Update Cart Functions
function openItemModal(product: Product) {
    if (!product.in_stock) return;
    modalProduct.value = product;
    
    // Check if item already exists in cart to preload
    const existing = cart.value.find(item => item.product.id === product.id);
    if (existing) {
        modalQuantity.value = existing.quantity;
        modalNotes.value = existing.notes;
    } else {
        modalQuantity.value = 1;
        modalNotes.value = '';
    }
    
    isItemModalOpen.value = true;
    trackBehavior('view_product', product.id);
}

function addToCart() {
    if (!modalProduct.value) return;
    
    const existingIndex = cart.value.findIndex(item => item.product.id === modalProduct.value!.id);
    if (existingIndex > -1) {
        cart.value[existingIndex].quantity = modalQuantity.value;
        cart.value[existingIndex].notes = modalNotes.value;
    } else {
        cart.value.push({
            product: modalProduct.value,
            quantity: modalQuantity.value,
            notes: modalNotes.value
        });
    }
    
    isItemModalOpen.value = false;
    toast.success(`Đã thêm ${modalProduct.value.name} vào giỏ hàng`);
    trackBehavior('add_to_cart', modalProduct.value.id, modalQuantity.value);
}

function updateCartQuantity(productId: number, delta: number) {
    const idx = cart.value.findIndex(item => item.product.id === productId);
    if (idx > -1) {
        const newQty = cart.value[idx].quantity + delta;
        if (newQty <= 0) {
            cart.value.splice(idx, 1);
            toast.info('Đã xóa món ăn khỏi giỏ hàng');
            trackBehavior('remove_from_cart', productId, 1);
        } else {
            cart.value[idx].quantity = newQty;
            if (delta > 0) {
                trackBehavior('add_to_cart', productId, delta);
            } else {
                trackBehavior('remove_from_cart', productId, Math.abs(delta));
            }
        }
    }
}

// APIs submission
async function submitOrder() {
    if (cart.value.length === 0) return;
    isOrdering.value = true;
    
    try {
        const payload = {
            customer_name: customerName.value.trim() || 'Khách tại bàn',
            customer_phone: customerPhone.value.trim() || null,
            session_id: sessionToken.value,
            items: cart.value.map(item => ({
                product_id: item.product.id,
                quantity: item.quantity,
                notes: item.notes || null
            }))
        };
        
        const response = await axios.post(`/customer/order/${props.restaurant.id}/${props.table.qr_token}`, payload);
        
        if (response.data.success) {
            cart.value = [];
            isCartOpen.value = false;
            toast.success(response.data.message);
            
            // Reload page state through Inertia to fetch activeTempOrders
            router.reload({ only: ['activeTempOrders'] });
        }
    } catch (err: any) {
        console.error(err);
        const errMsg = err.response?.data?.message || 'Không thể gửi yêu cầu đặt món. Vui lòng thử lại.';
        toast.error(errMsg);
    } finally {
        isOrdering.value = false;
    }
}

async function callStaff() {
    if (isCallingStaff.value) return;
    isCallingStaff.value = true;
    
    try {
        const response = await axios.post(`/customer/order/call-staff/${props.restaurant.id}`, {
            table_id: props.table.id,
            message: 'Khách yêu cầu phục vụ tại bàn'
        });
        toast.success(response.data.message);
        trackBehavior('call_staff');
    } catch (err) {
        toast.error('Có lỗi xảy ra. Vui lòng gọi trực tiếp nhân viên.');
    } finally {
        isCallingStaff.value = false;
    }
}

async function requestPayment() {
    if (isRequestingPayment.value) return;
    isRequestingPayment.value = true;
    
    try {
        const response = await axios.post(`/customer/order/payment-request/${props.restaurant.id}`, {
            table_id: props.table.id
        });
        toast.success(response.data.message);
        trackBehavior('payment_request');
    } catch (err: any) {
        toast.error(err.response?.data?.message || 'Có lỗi xảy ra. Vui lòng gọi trực tiếp nhân viên.');
    } finally {
        isRequestingPayment.value = false;
    }
}

// Feedback Rating submission
function initializeFeedback() {
    showFeedbackSection.value = true;
    feedbackSubmittedSuccessfully.value = false;
    feedbackRating.value = 5;
    feedbackContent.value = '';
    
    // Auto populate items from confirmed orders
    itemsRating.value = {};
    props.activeTempOrders.forEach(o => {
        if (o.status === 'confirmed') {
            o.cart_data.forEach(item => {
                itemsRating.value[item.product_id] = { rating: 5, comment: '' };
            });
        }
    });

    // Auto populate staff list
    staffRating.value = {};
    props.staffList.forEach(s => {
        staffRating.value[s.employee_id] = { rating: 5, comment: '' };
    });
}

async function submitFeedback() {
    isSubmittingFeedback.value = true;
    
    // Find first confirmed order_id if available to link
    const confirmedOrder = props.activeTempOrders.find(o => o.status === 'confirmed');
    
    const payload = {
        table_id: props.table.id,
        order_id: confirmedOrder?.order_id ?? null,
        rating: feedbackRating.value,
        content: feedbackContent.value,
        is_anonymous: customerName.value.trim() === '',
        submitted_by_name: customerName.value.trim() || null,
        submitted_by_phone: customerPhone.value.trim() || null,
        items_rating: Object.entries(itemsRating.value).map(([pId, r]) => ({
            product_id: parseInt(pId),
            rating: r.rating,
            comment: r.comment
        })),
        staff_rating: Object.entries(staffRating.value).map(([empId, r]) => ({
            employee_id: parseInt(empId),
            rating: r.rating,
            comment: r.comment
        }))
    };
    
    try {
        await axios.post(`/customer/order/feedback/${props.restaurant.id}`, payload);
        feedbackSubmittedSuccessfully.value = true;
        toast.success('Gửi đánh giá thành công! Cảm ơn ý kiến của bạn.');
        setTimeout(() => {
            showFeedbackSection.value = false;
        }, 2500);
    } catch (err) {
        toast.error('Có lỗi xảy ra khi gửi đánh giá.');
    } finally {
        isSubmittingFeedback.value = false;
    }
}

// Background refreshing
function refetchActiveOrders() {
    router.reload({ only: ['activeTempOrders'] });
}

function refetchMenuOnly() {
    router.reload({ only: ['products'] });
}

const now = ref(new Date());
let countdownInterval: ReturnType<typeof setInterval> | null = null;

function getProductRemainingSeconds(untilTimeStr: string | null) {
    if (!untilTimeStr) return 0;
    const diffMs = new Date(untilTimeStr).getTime() - now.value.getTime();
    return Math.max(0, Math.floor(diffMs / 1000));
}

function formatProductCountdown(untilTimeStr: string | null) {
    const totalSecs = getProductRemainingSeconds(untilTimeStr);
    if (totalSecs <= 0) return '00:00';
    const hours = Math.floor(totalSecs / 3600);
    const minutes = Math.floor((totalSecs % 3600) / 60);
    const seconds = totalSecs % 60;
    
    const pad = (n: number) => n.toString().padStart(2, '0');
    if (hours > 0) {
        return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
    }
    return `${pad(minutes)}:${pad(seconds)}`;
}

onMounted(() => {
    getOrGenerateSessionToken();
    trackBehavior('view_menu');

    countdownInterval = setInterval(() => {
        now.value = new Date();
        
        let expired = false;
        props.products.forEach(p => {
            const timeStr = p.paused_until || p.out_of_stock_until;
            if (timeStr) {
                const diff = new Date(timeStr).getTime() - now.value.getTime();
                if (diff <= 0 && (p.is_kitchen_paused || p.is_kitchen_out_of_stock)) {
                    expired = true;
                }
            }
        });
        if (expired) {
            refetchMenuOnly();
        }
    }, 1000);

    // Listen to live temporary order status updates
    if (window.Echo) {
        window.Echo.channel(`table.${props.table.id}`)
            .listen('.temporary_order.updated', (e: any) => {
                const order = props.activeTempOrders.find(o => o.id === e.id);
                if (order || e.table_id === props.table.id) {
                    refetchActiveOrders();
                    if (e.status === 'confirmed') {
                        toast.success('Đơn hàng của bạn đã được nhân viên xác nhận và gửi xuống bếp!');
                    } else if (e.status === 'cancelled') {
                        toast.error('Đơn hàng của bạn đã bị từ chối/hủy. Vui lòng liên hệ nhân viên.');
                    }
                }
            })
            .listen('.order.paid', (e: any) => {
                if (paymentQrOrder.value && e.order_id === paymentQrOrder.value.order_id) {
                    paymentSuccess.value = true;
                    if (paymentTimer.value) {
                        clearInterval(paymentTimer.value);
                        paymentTimer.value = null;
                    }
                    toast.success('Thanh toán thành công qua chuyển khoản!');
                    setTimeout(() => {
                        closeQrPaymentModal();
                        refetchActiveOrders();
                    }, 2000);
                } else {
                    refetchActiveOrders();
                }
            });
            
        // Listen to stock changes
        window.Echo.channel(`restaurant.${props.restaurant.id}`)
            .listen('.product.stock_updated', () => {
                refetchMenuOnly();
            });
    }
});

onUnmounted(() => {
    if (countdownInterval) clearInterval(countdownInterval);
    if (paymentTimer.value) clearInterval(paymentTimer.value);
    if (window.Echo) {
        window.Echo.leaveChannel(`table.${props.table.id}`);
        window.Echo.leaveChannel(`restaurant.${props.restaurant.id}`);
    }
});
</script>

<template>
    <Head :title="`Gọi Món Tại Bàn - ${restaurant.name}`" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans max-w-md mx-auto relative border-x border-slate-900 shadow-2xl">
        <!-- ── Top bar Header ────────────────────────────────────────────── -->
        <header class="sticky top-0 z-30 bg-slate-950/80 backdrop-blur-md border-b border-slate-900 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="size-8 rounded-full bg-amber-500 flex items-center justify-center text-slate-950 font-bold overflow-hidden shadow-lg shadow-amber-500/20">
                    <img v-if="restaurant.logo_url" :src="restaurant.logo_url" :alt="restaurant.name" class="size-full object-cover" />
                    <span v-else>{{ restaurant.name[0] }}</span>
                </div>
                <div>
                    <h1 class="text-sm font-bold text-slate-200 line-clamp-1">{{ restaurant.name }}</h1>
                    <p class="text-xxs text-amber-400 font-semibold">{{ table.name }} • {{ table.area_name }}</p>
                </div>
            </div>
            
            <div class="flex items-center gap-1.5">
                <button 
                    @click="callStaff" 
                    :disabled="isCallingStaff"
                    class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-300 hover:text-amber-400 active:scale-95 transition-all disabled:opacity-50"
                    title="Gọi phục vụ"
                >
                    <Loader2 v-if="isCallingStaff" class="size-4 animate-spin" />
                    <Bell v-else class="size-4" />
                </button>
                <button 
                    @click="requestPayment" 
                    :disabled="isRequestingPayment"
                    class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-300 hover:text-amber-400 active:scale-95 transition-all disabled:opacity-50"
                    title="Yêu cầu thanh toán"
                >
                    <Loader2 v-if="isRequestingPayment" class="size-4 animate-spin" />
                    <CreditCard v-else class="size-4" />
                </button>
            </div>
        </header>

        <!-- ── Active Order Tracker ────────────────────────────────────────── -->
        <div v-if="activeTempOrders.length" class="p-4 bg-slate-950 border-b border-slate-900 shrink-0">
            <h3 class="text-xs font-bold text-slate-400 mb-2.5 uppercase tracking-wider flex items-center gap-1.5">
                <Clock class="size-3.5 text-amber-500" /> Tiến độ món ăn của bạn
            </h3>
            
            <div class="space-y-3">
                <div v-for="order in activeTempOrders" :key="order.id" class="p-3 bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-850 rounded-xl relative overflow-hidden">
                    <!-- Progress Timeline UI -->
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xxs text-slate-500">Mã yêu cầu: #{{ order.id }}</span>
                        
                        <span v-if="order.status === 'waiting_verification'" class="text-xs font-bold text-yellow-500 animate-pulse bg-yellow-950/40 px-2 py-0.5 rounded-full border border-yellow-900/30">
                            Chờ xác thực...
                        </span>
                        <span v-else-if="order.status === 'escalated'" class="text-xs font-bold text-red-500 animate-pulse bg-red-950/40 px-2 py-0.5 rounded-full border border-red-900/30">
                            Đang giục nhân viên...
                        </span>
                        <span v-else-if="order.status === 'confirmed'" class="text-xs font-bold text-emerald-500 bg-emerald-950/40 px-2 py-0.5 rounded-full border border-emerald-900/30">
                            Đã xác nhận
                        </span>
                        <span v-else-if="order.status === 'cancelled'" class="text-xs font-bold text-slate-500 bg-slate-900 px-2 py-0.5 rounded-full">
                            Bị từ chối
                        </span>
                    </div>

                    <!-- Progress Bar Steps -->
                    <div class="grid grid-cols-3 gap-2 mb-3 text-center">
                        <div class="flex flex-col items-center">
                            <div :class="['size-6 rounded-full flex items-center justify-center text-xs font-bold transition-all', 
                                         ['waiting_verification', 'escalated', 'confirmed'].includes(order.status) ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'bg-slate-800 text-slate-500']">
                                <Check v-if="['confirmed'].includes(order.status)" class="size-3.5" />
                                <span v-else>1</span>
                            </div>
                            <span class="text-xxs font-medium mt-1 text-slate-400">Gửi đơn đệm</span>
                        </div>
                        <div class="flex flex-col items-center relative">
                            <!-- Link bar left -->
                            <div :class="['h-0.5 absolute w-full -left-1/2 top-3 -z-10', order.status === 'confirmed' ? 'bg-amber-500' : 'bg-slate-800']"></div>
                            
                            <div :class="['size-6 rounded-full flex items-center justify-center text-xs font-bold transition-all', 
                                         order.status === 'confirmed' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'bg-slate-800 text-slate-500']">
                                <Check v-if="order.order_status === 'completed' || order.items_status.every(i => i.status === 'served')" class="size-3.5" />
                                <span v-else>2</span>
                            </div>
                            <span class="text-xxs font-medium mt-1 text-slate-400">Bếp nấu</span>
                        </div>
                        <div class="flex flex-col items-center relative">
                            <!-- Link bar right -->
                            <div :class="['h-0.5 absolute w-full -left-1/2 top-3 -z-10', (order.order_status === 'completed' || order.items_status.every(i => i.status === 'served')) && order.status === 'confirmed' ? 'bg-amber-500' : 'bg-slate-800']"></div>
                            
                            <div :class="['size-6 rounded-full flex items-center justify-center text-xs font-bold transition-all', 
                                         order.status === 'confirmed' && (order.order_status === 'completed' || order.items_status.every(i => i.status === 'served')) ? 'bg-emerald-500 text-slate-950 shadow-md shadow-emerald-500/20' : 'bg-slate-800 text-slate-500']">
                                3
                            </div>
                            <span class="text-xxs font-medium mt-1 text-slate-400">Lên món</span>
                        </div>
                    </div>

                    <!-- Items List / Detail Statuses -->
                    <div v-if="order.items_status.length" class="space-y-1 bg-slate-950/50 p-2 rounded-lg text-xxs text-slate-400 border border-slate-900">
                        <div v-for="(item, idx) in order.items_status" :key="idx" class="flex justify-between items-center">
                            <span>{{ item.quantity }}x {{ item.name }}</span>
                            <span :class="['px-1.5 py-0.5 rounded font-semibold', 
                                           item.status === 'served' ? 'bg-emerald-950 text-emerald-400' : 
                                           item.status === 'preparing' ? 'bg-amber-950 text-amber-400 animate-pulse' : 'bg-slate-900 text-slate-400']">
                                {{ item.status === 'served' ? 'Đã lên món' : item.status === 'preparing' ? 'Đang chế biến' : 'Chờ nấu' }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="text-xxs text-slate-500 italic px-2">
                        Giỏ hàng: {{ order.cart_data.map(i => `${i.quantity}x ${i.name}`).join(', ') }}
                    </div>
                    
                    <!-- Nút Thanh toán VietQR động -->
                    <button
                        v-if="order.status === 'confirmed' && order.order_id && order.payment_status === 'unpaid'"
                        @click="openQrPaymentModal(order)"
                        class="mt-2.5 w-full py-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold rounded-xl text-xxs flex items-center justify-center gap-1.5 shadow-md shadow-amber-500/10 active:scale-98 transition-all"
                    >
                        <CreditCard class="size-3.5" /> Thanh toán QR trực tuyến (VietQR)
                    </button>
                    <!-- Báo đã thanh toán -->
                    <div
                        v-else-if="order.status === 'confirmed' && order.payment_status === 'paid'"
                        class="mt-2.5 w-full py-1.5 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-bold rounded-xl text-xxs flex items-center justify-center gap-1.5"
                    >
                        <Check class="size-3.5" /> Đã thanh toán hóa đơn
                    </div>
                </div>
            </div>

            <!-- Rate experience button -->
            <div v-if="activeTempOrders.some(o => o.status === 'confirmed')" class="mt-3 flex justify-end">
                <button 
                    @click="initializeFeedback"
                    class="text-xxs font-bold text-amber-400 hover:text-amber-300 flex items-center gap-1 bg-amber-500/10 px-3 py-1.5 rounded-lg border border-amber-500/20"
                >
                    <HeartHandshake class="size-3.5" /> Đánh giá phục vụ & Món ăn
                </button>
            </div>
        </div>

        <!-- ── Category Tabs (Horizontal Scrollable) ────────────────────────── -->
        <nav class="sticky top-[57px] z-20 bg-slate-950/90 backdrop-blur-sm border-b border-slate-900 py-2.5 px-4 overflow-x-auto scrollbar-none flex gap-2 shrink-0">
            <button
                v-for="cat in categories"
                :key="cat.id"
                @click="selectCategory(cat.id)"
                :class="['px-4 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all border',
                         selectedCategoryId === cat.id 
                             ? 'bg-amber-500 border-amber-500 text-slate-950 shadow-md shadow-amber-500/15 font-bold' 
                             : 'bg-slate-900 border-slate-850 text-slate-400 hover:text-slate-200']"
            >
                {{ cat.name }}
            </button>
        </nav>

        <!-- ── Products List ───────────────────────────────────────────── -->
        <main class="flex-1 overflow-y-auto px-4 py-4 space-y-8 pb-24">
            <div 
                v-for="cat in categories" 
                :key="cat.id" 
                :id="`category-section-${cat.id}`"
                class="space-y-3"
            >
                <h2 class="text-sm font-bold text-slate-300 border-l-2 border-amber-500 pl-2">
                    {{ cat.name }}
                </h2>
                
                <div class="grid grid-cols-1 gap-3">
                    <div
                        v-for="product in products.filter(p => p.category_id === cat.id)"
                        :key="product.id"
                        :class="['p-3 bg-gradient-to-br from-slate-900 to-slate-950 border rounded-2xl flex gap-3 transition-all relative overflow-hidden',
                                 product.in_stock ? 'border-slate-900 hover:border-slate-800' : 'border-slate-950 opacity-60']"
                    >
                        <!-- Image Container -->
                        <div class="size-20 rounded-xl bg-slate-950 flex items-center justify-center text-slate-800 font-bold overflow-hidden shrink-0 border border-slate-850 relative">
                            <img v-if="product.image_url" :src="product.image_url" :alt="product.name" class="size-full object-cover" />
                            <Utensils v-else class="size-6 text-slate-800" />
                            
                            <!-- Out of Stock Overlay -->
                            <div v-if="!product.in_stock" class="absolute inset-0 bg-slate-950/80 backdrop-blur-xxs flex flex-col items-center justify-center text-center p-1">
                                <span v-if="product.is_kitchen_paused" class="text-[9px] font-black text-amber-500 uppercase border border-amber-500/50 px-1 rounded leading-tight">Tạm Dừng</span>
                                <span v-else-if="product.is_kitchen_out_of_stock" class="text-[9px] font-black text-orange-500 uppercase border border-orange-500/50 px-1 rounded leading-tight">Hết Món</span>
                                <span v-else class="text-[9px] font-black text-red-500 uppercase border border-red-500/50 px-1.5 py-0.5 rounded leading-tight">Hết Hàng</span>
                            </div>
                        </div>

                        <!-- Product info -->
                        <div class="flex-1 flex flex-col justify-between min-w-0">
                            <div>
                                <h3 class="text-xs font-bold text-slate-200 line-clamp-1">{{ product.name }}</h3>
                                <p class="text-[10px] text-slate-500 mt-0.5 line-clamp-2">{{ product.description || 'Chưa cập nhật mô tả nguyên liệu & hương vị của món ăn này.' }}</p>
                                
                                <!-- Kitchen pause/out of stock countdown banner -->
                                <div v-if="product.is_kitchen_paused || product.is_kitchen_out_of_stock" class="mt-1.5 inline-flex items-center gap-1 bg-amber-500/10 border border-amber-500/20 text-amber-400 px-2 py-0.5 rounded-lg text-[9px] font-bold">
                                    <Clock class="size-2.5 animate-pulse text-amber-550" />
                                    <span>Bán lại sau: {{ formatProductCountdown(product.paused_until || product.out_of_stock_until) }}</span>
                                </div>
                            </div>
                            
                            <div class="flex items-end justify-between mt-2">
                                <span class="text-sm font-extrabold text-amber-400">{{ formatCurrency(product.price) }}</span>
                                
                                <button
                                    @click="openItemModal(product)"
                                    :disabled="!product.in_stock"
                                    class="size-7 rounded-lg bg-amber-500 hover:bg-amber-400 active:scale-95 text-slate-950 flex items-center justify-center font-bold disabled:bg-slate-900 disabled:text-slate-700 transition-all shadow-md shadow-amber-500/10"
                                >
                                    <Plus class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- ── Floating Shopping Cart Button ──────────────────────────────── -->
        <div 
            v-if="cart.length > 0"
            class="fixed bottom-4 left-1/2 -translate-x-1/2 w-[calc(100vw-32px)] max-w-[380px] z-40"
        >
            <button
                @click="isCartOpen = true"
                class="w-full h-12 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 flex items-center justify-between px-5 font-bold shadow-lg shadow-amber-500/25 active:scale-98 transition-all"
            >
                <div class="flex items-center gap-2">
                    <div class="bg-slate-950 text-amber-500 text-xs size-5.5 rounded-lg flex items-center justify-center">
                        {{ cartTotalItems }}
                    </div>
                    <span class="text-xs">Xem giỏ hàng</span>
                </div>
                <div class="flex items-center gap-1 text-xs">
                    <span>Tổng:</span>
                    <span class="text-sm font-extrabold">{{ formatCurrency(cartTotalPrice) }}</span>
                </div>
            </button>
        </div>

        <!-- ── Cart Drawer Slide Up ────────────────────────────────────────── -->
        <div v-if="isCartOpen" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-end justify-center">
            <!-- Overlay click closer -->
            <div class="absolute inset-0" @click="isCartOpen = false"></div>
            
            <div class="bg-slate-900 w-full max-w-md rounded-t-3xl border-t border-slate-800 z-10 flex flex-col max-h-[85vh] animate-slide-up relative">
                <header class="p-4 border-b border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <ShoppingCart class="size-4 text-amber-500" />
                        <h2 class="text-sm font-bold text-slate-200">Giỏ hàng của bạn</h2>
                    </div>
                    <button @click="isCartOpen = false" class="p-1 rounded-lg bg-slate-850 hover:bg-slate-800 text-slate-400">
                        <X class="size-4" />
                    </button>
                </header>
                
                <!-- Cart items list -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <div v-for="item in cart" :key="item.product.id" class="flex gap-3 pb-3 border-b border-slate-850">
                        <div class="size-12 rounded-lg bg-slate-950 overflow-hidden border border-slate-850">
                            <img v-if="item.product.image_url" :src="item.product.image_url" :alt="item.product.name" class="size-full object-cover" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <h4 class="text-xs font-bold text-slate-200 line-clamp-1">{{ item.product.name }}</h4>
                                <span class="text-xs font-bold text-amber-400">{{ formatCurrency(item.product.price * item.quantity) }}</span>
                            </div>
                            <p v-if="item.notes" class="text-xxs text-slate-500 italic mt-0.5 line-clamp-1">Ghi chú: {{ item.notes }}</p>
                            
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-[10px] text-slate-500">{{ formatCurrency(item.product.price) }} / món</span>
                                
                                <div class="flex items-center gap-2 bg-slate-950 p-1 rounded-lg border border-slate-850">
                                    <button @click="updateCartQuantity(item.product.id, -1)" class="p-1 hover:text-amber-500 text-slate-400">
                                        <Minus class="size-3" />
                                    </button>
                                    <span class="text-xs font-bold w-4 text-center text-slate-200">{{ item.quantity }}</span>
                                    <button @click="updateCartQuantity(item.product.id, 1)" class="p-1 hover:text-amber-500 text-slate-400">
                                        <Plus class="size-3" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Information Form -->
                    <div class="pt-4 border-t border-slate-850 space-y-3">
                        <h3 class="text-xs font-bold text-slate-300">Thông tin nhận đơn đệm</h3>
                        
                        <div class="space-y-2.5">
                            <div>
                                <label class="text-[10px] font-bold text-slate-500 block mb-1">Tên của bạn (Tùy chọn)</label>
                                <input 
                                    v-model="customerName" 
                                    type="text" 
                                    placeholder="Ví dụ: Anh Quân" 
                                    class="w-full h-10 px-3 rounded-lg bg-slate-950 border border-slate-850 text-xs text-slate-300 focus:outline-none focus:border-amber-500"
                                />
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500 block mb-1">Số điện thoại (Tùy chọn)</label>
                                <input 
                                    v-model="customerPhone" 
                                    type="text" 
                                    placeholder="Để tích điểm hoặc liên hệ" 
                                    class="w-full h-10 px-3 rounded-lg bg-slate-950 border border-slate-850 text-xs text-slate-300 focus:outline-none focus:border-amber-500"
                                />
                            </div>
                        </div>
                        
                        <!-- Customer Loyalty Display Card -->
                        <div v-if="customerLoyalty" class="mt-2.5 p-3.5 bg-slate-950 border border-amber-500/20 rounded-xl text-xxs text-left text-slate-400 space-y-1.5 animate-in fade-in duration-200">
                            <p class="font-black text-slate-200 text-xs flex items-center gap-1">✨ Hội viên: {{ customerLoyalty.full_name }}</p>
                            <div class="flex items-center justify-between mt-1 border-t border-slate-900 pt-1.5">
                                <span>Hạng thẻ:</span>
                                <span v-if="customerLoyalty.membership_level === 'diamond'" class="text-indigo-400 font-black">💎 Kim Cương (Giảm 10%)</span>
                                <span v-else-if="customerLoyalty.membership_level === 'gold'" class="text-amber-400 font-black">⭐ Vàng (Giảm 5%)</span>
                                <span v-else class="text-slate-400 font-bold">🥈 Bạc (Tích điểm)</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Điểm tích lũy:</span>
                                <span class="text-amber-500 font-extrabold">{{ customerLoyalty.loyalty_points }} pt</span>
                            </div>
                            <p v-if="customerLoyalty.membership_level !== 'silver'" class="text-emerald-400 text-[10px] font-bold italic text-center mt-1">
                                * Tự động giảm giá {{ customerLoyalty.membership_level === 'diamond' ? '10%' : '5%' }} khi đơn hàng được nhân viên xác nhận!
                            </p>
                        </div>
                    </div>
                </div>
                
                <footer class="p-4 border-t border-slate-800 bg-slate-950/50">
                    <div class="flex justify-between items-center mb-4 text-slate-300">
                        <span class="text-xs">Tạm tính:</span>
                        <span class="text-base font-extrabold text-amber-400">{{ formatCurrency(cartTotalPrice) }}</span>
                    </div>
                    
                    <button
                        @click="submitOrder"
                        :disabled="isOrdering || cart.length === 0"
                        class="w-full h-11 rounded-xl bg-amber-500 hover:bg-amber-400 disabled:bg-slate-800 disabled:text-slate-600 text-slate-950 text-xs font-bold flex items-center justify-center gap-2 shadow-lg shadow-amber-500/10"
                    >
                        <Loader2 v-if="isOrdering" class="size-4 animate-spin" />
                        <span v-else>Gửi Yêu Cầu Đặt Món (Đệm)</span>
                    </button>
                    <p class="text-center text-[10px] text-slate-500 mt-2.5">
                        * Hệ thống ghi nhận đệm để nhân viên duyệt, tránh đơn hàng ảo.
                    </p>
                </footer>
            </div>
        </div>

        <!-- ── Product Detail / Customize Notes Modal ───────────────────────── -->
        <div v-if="isItemModalOpen && modalProduct" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-xs overflow-hidden animate-zoom-in relative">
                <header class="p-4 border-b border-slate-850 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-200 line-clamp-1">{{ modalProduct.name }}</h3>
                    <button @click="isItemModalOpen = false" class="p-1 rounded-lg bg-slate-850 hover:bg-slate-800 text-slate-400">
                        <X class="size-3.5" />
                    </button>
                </header>
                
                <div class="p-4 space-y-4">
                    <!-- Qty selection -->
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400 font-semibold">Số lượng</span>
                        <div class="flex items-center gap-3 bg-slate-950 p-1.5 rounded-lg border border-slate-850">
                            <button @click="modalQuantity = Math.max(1, modalQuantity - 1)" class="p-1 text-slate-400 hover:text-amber-500">
                                <Minus class="size-3.5" />
                            </button>
                            <span class="text-sm font-bold w-6 text-center text-slate-200">{{ modalQuantity }}</span>
                            <button @click="modalQuantity = modalQuantity + 1" class="p-1 text-slate-400 hover:text-amber-500">
                                <Plus class="size-3.5" />
                            </button>
                        </div>
                    </div>
                    
                    <!-- Notes selection -->
                    <div>
                        <label class="text-[10px] font-bold text-slate-500 block mb-1">Ghi chú (Ví dụ: Không cay, ít đá,...)</label>
                        <textarea 
                            v-model="modalNotes" 
                            rows="2"
                            placeholder="Nhập ghi chú cho nhà bếp..." 
                            class="w-full p-2.5 rounded-lg bg-slate-950 border border-slate-850 text-xs text-slate-300 focus:outline-none focus:border-amber-500 resize-none"
                        ></textarea>
                    </div>
                </div>
                
                <footer class="p-4 border-t border-slate-850 bg-slate-950/20 flex gap-2">
                    <button @click="isItemModalOpen = false" class="flex-1 h-9 rounded-lg border border-slate-800 text-slate-400 text-xs font-bold hover:bg-slate-850">
                        Hủy
                    </button>
                    <button @click="addToCart" class="flex-1 h-9 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-lg text-xs font-bold shadow-md shadow-amber-500/10">
                        Xác nhận
                    </button>
                </footer>
            </div>
        </div>

        <!-- ── Detailed Feedback Modal ─────────────────────────────────────── -->
        <div v-if="showFeedbackSection" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-end justify-center">
            <div class="bg-slate-900 w-full max-w-md rounded-t-3xl border-t border-slate-800 z-10 flex flex-col max-h-[90vh] animate-slide-up relative">
                <header class="p-4 border-b border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <HeartHandshake class="size-4 text-amber-500" />
                        <h2 class="text-sm font-bold text-slate-200">Đánh giá trải nghiệm tại bàn</h2>
                    </div>
                    <button @click="showFeedbackSection = false" class="p-1 rounded-lg bg-slate-850 hover:bg-slate-800 text-slate-400">
                        <X class="size-4" />
                    </button>
                </header>
                
                <div class="flex-1 overflow-y-auto p-4 space-y-5">
                    <div v-if="feedbackSubmittedSuccessfully" class="py-12 text-center space-y-3">
                        <div class="size-12 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-500 flex items-center justify-center mx-auto text-xl font-bold animate-bounce">
                            ✓
                        </div>
                        <h3 class="text-sm font-bold text-slate-200">Gửi đánh giá thành công!</h3>
                        <p class="text-xs text-slate-400">Aventura chân thành cảm ơn ý kiến đóng góp của bạn.</p>
                    </div>
                    
                    <div v-else class="space-y-5">
                        <!-- Step 1: Overall Experience -->
                        <div class="space-y-2">
                            <h3 class="text-xs font-bold text-slate-300">1. Trải nghiệm chung</h3>
                            <div class="flex items-center gap-2 justify-center py-2 bg-slate-950 rounded-xl border border-slate-855">
                                <button 
                                    v-for="star in 5" 
                                    :key="star" 
                                    @click="feedbackRating = star"
                                    class="p-1"
                                >
                                    <Star :class="['size-7 transition-all', star <= feedbackRating ? 'fill-amber-500 text-amber-500' : 'text-slate-650']" />
                                </button>
                            </div>
                            <textarea 
                                v-model="feedbackContent"
                                rows="2"
                                placeholder="Góp ý thêm về không gian, chất lượng phục vụ..."
                                class="w-full p-2.5 rounded-lg bg-slate-950 border border-slate-850 text-xs text-slate-350 focus:outline-none focus:border-amber-500 resize-none"
                            ></textarea>
                        </div>
                        
                        <!-- Step 2: Dish Feedback -->
                        <div v-if="Object.keys(itemsRating).length > 0" class="space-y-2">
                            <h3 class="text-xs font-bold text-slate-300">2. Đánh giá món ăn</h3>
                            
                            <div class="space-y-3">
                                <div 
                                    v-for="[pId, val] in Object.entries(itemsRating)" 
                                    :key="pId"
                                    class="p-3 bg-slate-950 rounded-xl border border-slate-850 space-y-2"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-slate-300 line-clamp-1">
                                            {{ products.find(p => p.id === parseInt(pId))?.name || 'Món ăn' }}
                                        </span>
                                        
                                        <!-- Mini Stars -->
                                        <div class="flex items-center gap-1">
                                            <button 
                                                v-for="star in 5" 
                                                :key="star"
                                                @click="itemsRating[parseInt(pId)].rating = star"
                                                class="p-0.5"
                                            >
                                                <Star :class="['size-4.5', star <= val.rating ? 'fill-amber-500 text-amber-500' : 'text-slate-700']" />
                                            </button>
                                        </div>
                                    </div>
                                    <input 
                                        v-model="itemsRating[parseInt(pId)].comment"
                                        type="text" 
                                        placeholder="Ý kiến về món ăn này (ví dụ: ngon, mặn, nguội...)" 
                                        class="w-full h-8 px-2.5 rounded-lg bg-slate-900 border border-slate-800 text-xxs text-slate-300 focus:outline-none focus:border-amber-500"
                                    />
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 3: Staff Feedback -->
                        <div v-if="props.staffList.length > 0" class="space-y-2">
                            <h3 class="text-xs font-bold text-slate-300">3. Đánh giá nhân viên ca trực</h3>
                            
                            <div class="space-y-3">
                                <div 
                                    v-for="staff in props.staffList" 
                                    :key="staff.employee_id"
                                    class="p-3 bg-slate-950 rounded-xl border border-slate-850 space-y-2"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="text-xs font-bold text-slate-300">{{ staff.name }}</span>
                                            <span class="text-[9px] font-semibold text-slate-500 ml-1.5 px-1 bg-slate-900 rounded border border-slate-850">{{ staff.role }}</span>
                                        </div>
                                        
                                        <!-- Mini Stars -->
                                        <div class="flex items-center gap-1">
                                            <button 
                                                v-for="star in 5" 
                                                :key="star"
                                                @click="staffRating[staff.employee_id].rating = star"
                                                class="p-0.5"
                                            >
                                                <Star :class="['size-4.5', star <= staffRating[staff.employee_id].rating ? 'fill-amber-500 text-amber-500' : 'text-slate-700']" />
                                            </button>
                                        </div>
                                    </div>
                                    <input 
                                        v-model="staffRating[staff.employee_id].comment"
                                        type="text" 
                                        placeholder="Ý kiến về nhân viên này (ví dụ: nhiệt tình, chậm chạp...)" 
                                        class="w-full h-8 px-2.5 rounded-lg bg-slate-900 border border-slate-800 text-xxs text-slate-300 focus:outline-none focus:border-amber-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <footer v-if="!feedbackSubmittedSuccessfully" class="p-4 border-t border-slate-800 bg-slate-950/30">
                    <button
                        @click="submitFeedback"
                        :disabled="isSubmittingFeedback"
                        class="w-full h-11 rounded-xl bg-amber-500 hover:bg-amber-400 disabled:bg-slate-800 text-slate-950 text-xs font-bold flex items-center justify-center gap-2"
                    >
                        <Loader2 v-if="isSubmittingFeedback" class="size-4 animate-spin" />
                        <span v-else>Gửi Đánh Giá Trải Nghiệm</span>
                    </button>
                </footer>
            </div>
        </div>

        <!-- ── VietQR Payment Modal ────────────────────────────────────────── -->
        <div v-if="isQrPaymentModalOpen && paymentQrOrder" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-sm overflow-hidden animate-zoom-in relative shadow-2xl">
                <header class="p-4 border-b border-slate-850 flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-bold text-slate-200">Thanh toán chuyển khoản VietQR</h3>
                        <p class="text-[10px] text-slate-500">Mã đơn: {{ paymentQrOrder.order_number }}</p>
                    </div>
                    <button @click="closeQrPaymentModal" class="p-1 rounded-lg bg-slate-850 hover:bg-slate-800 text-slate-400">
                        <X class="size-4" />
                    </button>
                </header>
                
                <div class="p-5 space-y-4 text-center">
                    <!-- Success State -->
                    <div v-if="paymentSuccess" class="py-6 space-y-3">
                        <div class="size-16 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-500 flex items-center justify-center mx-auto text-3xl font-bold animate-bounce">
                            ✓
                        </div>
                        <h4 class="text-sm font-bold text-slate-100">Thanh toán thành công!</h4>
                        <p class="text-xs text-slate-400">Đơn hàng của quý khách đã được hoàn tất thanh toán. Cảm ơn quý khách!</p>
                    </div>
                    
                    <!-- Standard QR / Loading State -->
                    <div v-else class="space-y-4">
                        <div class="bg-white p-3 rounded-2xl inline-block shadow-lg relative">
                            <div v-if="!paymentQrUrl" class="size-48 flex items-center justify-center">
                                <Loader2 class="size-8 text-amber-500 animate-spin" />
                            </div>
                            <img v-else :src="paymentQrUrl" alt="VietQR Payment Code" class="size-48 object-contain" />
                        </div>
                        
                        <!-- Account Details -->
                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-850 text-left space-y-1.5 text-xxs">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Số tiền:</span>
                                <span class="text-amber-400 font-extrabold text-sm">{{ formatCurrency(paymentQrOrder.total_amount) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Nội dung chuyển khoản:</span>
                                <span class="text-slate-200 font-bold">AVTORD{{ paymentQrOrder.order_id }}</span>
                            </div>
                        </div>

                        <!-- Waiting Spinner -->
                        <div class="flex items-center justify-center gap-2 text-xxs text-amber-400 font-semibold py-1 bg-amber-500/5 rounded-lg border border-amber-500/10">
                            <Loader2 class="size-3.5 animate-spin" />
                            <span>Đang chờ chuyển khoản từ ứng dụng ngân hàng...</span>
                        </div>

                        <p class="text-[10px] text-slate-500 text-left leading-relaxed">
                            💡 **Hướng dẫn**: Mở ứng dụng ngân hàng quét mã QR trên để chuyển khoản tự động. Vui lòng giữ nguyên nội dung chuyển khoản để hệ thống ghi nhận ngay lập tức.
                        </p>
                    </div>
                </div>
                
                <!-- Bottom controls: Simulator button -->
                <footer class="p-4 border-t border-slate-850 bg-slate-950/40 flex flex-col gap-2">
                    <button 
                        v-if="!paymentSuccess"
                        @click="simulatePaymentSuccess"
                        :disabled="isSimulatingPayment"
                        class="w-full h-9 bg-slate-800 hover:bg-slate-700 disabled:opacity-50 text-amber-400 rounded-lg text-xxs font-bold border border-slate-700/60 flex items-center justify-center gap-1.5"
                    >
                        <Loader2 v-if="isSimulatingPayment" class="size-3 animate-spin" />
                        <span>⚡ Giả lập thanh toán thành công (Test)</span>
                    </button>
                    <button @click="closeQrPaymentModal" class="w-full h-9 bg-slate-900 border border-slate-800 text-slate-400 text-xxs font-bold hover:bg-slate-850 rounded-lg">
                        {{ paymentSuccess ? 'Đóng' : 'Hủy' }}
                    </button>
                </footer>
            </div>
        </div>
    </div>
</template>

<style>
/* Smooth custom animations */
@keyframes slideUp {
    from {
        transform: translateY(100%);
    }
    to {
        transform: translateY(0);
    }
}

@keyframes zoomIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.animate-slide-up {
    animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-zoom-in {
    animation: zoomIn 0.15s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

/* Hide scrollbars for chrome, safari & firefox */
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
.scrollbar-none {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}
.backdrop-blur-xxs {
    backdrop-filter: blur(2px);
}
.text-xxs {
    font-size: 0.65rem;
}
.text-xxxs {
    font-size: 0.55rem;
}
.size-5\.5 {
    width: 1.375rem;
    height: 1.375rem;
}
.size-4\.5 {
    width: 1.125rem;
    height: 1.125rem;
}
.border-slate-850 {
    border-color: rgba(30, 41, 59, 0.5);
}
</style>
