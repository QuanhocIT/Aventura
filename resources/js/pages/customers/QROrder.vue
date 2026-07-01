<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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
    HeartHandshake,
    Award
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
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
const customerName = ref(localStorage.getItem('customer_name') || '');
const customerPhone = ref(localStorage.getItem('customer_phone') || '');

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

// Member Dashboard & Point Redemption calculations
const goToDashboard = async () => {
    const phone = customerPhone.value.trim();

    if (!phone) {
        toast.error('Vui lòng nhập số điện thoại trong giỏ hàng để truy cập cổng hội viên');
        isCartOpen.value = true;

        return;
    }

    const token = await axios.get(`/api/customer/portal-token/${props.restaurant.id}/${phone}`).then(r => r.data.token).catch(() => '');
    window.location.href = `/customer/portal/dashboard/${props.restaurant.id}/${phone}?token=${token}`;
};

const usePoints = ref(false);
const pointsToRedeem = computed(() => {
    if (!usePoints.value || !customerLoyalty.value) {
return 0;
}

    const maxDiscount = cartTotalPrice.value * 0.5; // Max 50%
    const pointsAvailable = customerLoyalty.value.loyalty_points;
    const pointsNeededForMaxDiscount = Math.floor(maxDiscount / 100); // 1 point = 100đ

    return Math.min(pointsAvailable, pointsNeededForMaxDiscount);
});
const pointsDiscount = computed(() => pointsToRedeem.value * 100);
const finalCartPrice = computed(() => Math.max(0, cartTotalPrice.value - pointsDiscount.value));

// Behavior Tracking
const sessionToken = ref('');

function getOrGenerateSessionToken() {
    let token = null;

    try {
        token = sessionStorage.getItem('cdp_session_token');

        if (!token) {
            token = 'sess_' + Math.random().toString(36).substring(2, 15) + '_' + Date.now();
            sessionStorage.setItem('cdp_session_token', token);
        }
    } catch (e) {
        // Fallback for Safari private mode or if storage is blocked
        token = 'sess_fallback_' + Math.random().toString(36).substring(2, 15) + '_' + Date.now();
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
    if (paymentTimer.value) {
clearInterval(paymentTimer.value);
}
    
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
    if (!selectedCategoryId.value) {
return props.products;
}

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
}

// Add/Update Cart Functions
function openItemModal(product: Product) {
    if (!product.in_stock) {
return;
}

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
    if (!modalProduct.value) {
return;
}
    
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
    if (cart.value.length === 0) {
return;
}

    isOrdering.value = true;
    
    try {
        if (customerPhone.value.trim()) {
            localStorage.setItem('customer_phone', customerPhone.value.trim());
        }

        if (customerName.value.trim()) {
            localStorage.setItem('customer_name', customerName.value.trim());
        }

        const payload = {
            customer_name: customerName.value.trim() || 'Khách tại bàn',
            customer_phone: customerPhone.value.trim() || null,
            session_id: sessionToken.value,
            redeem_points: pointsToRedeem.value,
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
            usePoints.value = false;
            toast.success(response.data.message);
            
            // Reload page state through Inertia to fetch activeTempOrders
            router.reload({ only: ['activeTempOrders'] });
            lookupCustomerLoyalty(); // Refresh loyalty info
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
    if (isCallingStaff.value) {
return;
}

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
    if (isRequestingPayment.value) {
return;
}

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
    if (!untilTimeStr) {
return 0;
}

    const diffMs = new Date(untilTimeStr).getTime() - now.value.getTime();

    return Math.max(0, Math.floor(diffMs / 1000));
}

function formatProductCountdown(untilTimeStr: string | null) {
    const totalSecs = getProductRemainingSeconds(untilTimeStr);

    if (totalSecs <= 0) {
return '00:00';
}

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
    localStorage.setItem('last_qr_order_url', window.location.href);
    getOrGenerateSessionToken();
    trackBehavior('view_menu');

    const storedPhone = localStorage.getItem('customer_phone');
    const urlParams = new URLSearchParams(window.location.search);
    const urlPhone = urlParams.get('phone');

    if (storedPhone && !urlPhone) {
        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set('phone', storedPhone);
        window.location.href = newUrl.toString();

        return;
    }

    if (urlPhone) {
        customerPhone.value = urlPhone;
        lookupCustomerLoyalty();
    } else if (customerPhone.value.trim().length >= 10) {
        lookupCustomerLoyalty();
    }

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
            
function getProductIngredients(name: string) {
    const n = name.toLowerCase();

    if (n.includes('cơm') || n.includes('com')) {
        return ['Gạo tẻ thơm', 'Sườn cốt lết', 'Nước mắm chắt', 'Mật ong rừng', 'Hành tím', 'Tỏi Lý Sơn', 'Tiêu sọ'];
    }

    if (n.includes('phở') || n.includes('pho')) {
        return ['Bánh phở tươi', 'Thịt bò u hoa', 'Xương ống bò ninh 24h', 'Hành tây', 'Hành lá', 'Gừng nướng', 'Thảo quả'];
    }

    if (n.includes('trà') || n.includes('tra') || n.includes('uống') || n.includes('nước') || n.includes('chanh')) {
        return ['Lá trà xanh Oolong', 'Chanh tươi cắt lát', 'Đường mía tự nhiên', 'Nước tinh khiết', 'Đá sạch tinh thể'];
    }

    return ['Nguyên liệu sạch chọn lọc', 'Gia vị hảo hạng', 'Rau thơm sạch hữu cơ', 'Quy trình khép kín'];
}

function getProductReviews(id: number) {
    const reviewPools = [
        [
            { author: 'Anh Tuấn', rating: 5, comment: 'Món ăn đậm đà, sườn nướng mềm ngọt nước, đĩa cơm thơm phức.' },
            { author: 'Chị Lan', rating: 4.8, comment: 'Ngon tuyệt vời, nêm nếm rất vừa vị sạch sẽ. Sẽ ủng hộ tiếp.' }
        ],
        [
            { author: 'Minh Hoàng', rating: 5, comment: 'Vị nước dùng ngọt thanh tự nhiên từ xương bò ninh, rất cuốn.' },
            { author: 'Thu Hà', rating: 4.9, comment: 'Thịt bò tươi mềm ngọt, nước phở nóng hổi ăn kèm rau thơm cực ngon!' }
        ],
        [
            { author: 'Quốc Bảo', rating: 4.8, comment: 'Uống ngọt mát, thơm nồng vị trà nguyên bản, cực kỳ sảng khoái.' },
            { author: 'Ngọc Diệp', rating: 5, comment: 'Chanh tươi thơm lừng kết hợp trà ô long giải nhiệt rất tốt.' }
        ],
    ];

    return reviewPools[id % reviewPools.length];
}

onUnmounted(() => {
    if (countdownInterval) {
clearInterval(countdownInterval);
}

    if (paymentTimer.value) {
clearInterval(paymentTimer.value);
}
});
</script>

<template>
    <Head :title="`Gọi Món Tại Bàn - ${restaurant.name}`" />

    <div class="min-h-screen bg-slate-50 text-slate-800 flex flex-col font-sans max-w-md mx-auto relative border-x border-slate-200/80 shadow-[0_0_50px_rgba(0,0,0,0.03)]">
        <!-- ── Top bar Header ────────────────────────────────────────────── -->
        <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-slate-100 px-5 py-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <div class="size-11 rounded-xl bg-gradient-to-tr from-amber-400 to-amber-500 p-0.5 overflow-hidden shadow-sm">
                    <div class="size-full rounded-[9px] bg-white overflow-hidden flex items-center justify-center">
                        <img v-if="restaurant.logo_url" :src="restaurant.logo_url" :alt="restaurant.name" class="size-full object-cover" />
                        <span v-else class="text-amber-500 font-black text-sm">{{ restaurant.name[0] }}</span>
                    </div>
                </div>
                <div>
                    <h1 class="text-xs font-black tracking-tight text-slate-800 uppercase">{{ restaurant.name }}</h1>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="inline-block size-2 rounded-full bg-emerald-500 animate-ping absolute"></span>
                        <span class="inline-block size-2 rounded-full bg-emerald-500 relative"></span>
                        <p class="text-[10px] text-amber-600 font-extrabold tracking-wide uppercase">{{ table.name }} • {{ table.area_name }}</p>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <button 
                    @click="goToDashboard"
                    class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white hover:bg-slate-850 active:scale-95 transition-all cursor-pointer shadow-sm flex items-center justify-center gap-1 text-[10px] font-black"
                    title="Cổng Hội Viên"
                >
                    <Award class="size-4 text-amber-400" />
                    <span>Hội Viên</span>
                </button>
                <button 
                    @click="callStaff" 
                    :disabled="isCallingStaff"
                    class="p-2.5 rounded-xl bg-slate-100 border border-slate-200 text-slate-600 hover:text-slate-900 active:scale-95 transition-all disabled:opacity-50 cursor-pointer shadow-sm"
                    title="Gọi phục vụ"
                >
                    <Loader2 v-if="isCallingStaff" class="size-4 animate-spin text-amber-500" />
                    <Bell v-else class="size-4" />
                </button>
                <button 
                    @click="requestPayment" 
                    :disabled="isRequestingPayment"
                    class="p-2.5 rounded-xl bg-slate-100 border border-slate-200 text-slate-600 hover:text-slate-900 active:scale-95 transition-all disabled:opacity-50 cursor-pointer shadow-sm"
                    title="Yêu cầu thanh toán"
                >
                    <Loader2 v-if="isRequestingPayment" class="size-4 animate-spin text-amber-500" />
                    <CreditCard v-else class="size-4" />
                </button>
            </div>
        </header>

        <!-- ── Active Order Tracker ────────────────────────────────────────── -->
        <div v-if="activeTempOrders.length" class="p-5 bg-white border-b border-slate-100 shrink-0">
            <h3 class="text-[10px] font-black text-slate-400 mb-3.5 uppercase tracking-widest flex items-center gap-2">
                <Clock class="size-3.5 text-amber-500" /> Tiến độ món ăn của bạn
            </h3>
            
            <div class="space-y-4">
                <div v-for="order in activeTempOrders" :key="order.id" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl relative overflow-hidden shadow-sm">
                    <!-- Progress Timeline UI -->
                    <div class="flex items-center justify-between mb-3.5">
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider bg-white px-2 py-0.5 rounded-lg border border-slate-200 shadow-xxs">Yêu cầu #{{ order.id }}</span>
                        
                        <span v-if="order.status === 'waiting_verification'" class="text-[9px] font-black tracking-wide uppercase text-amber-600 animate-pulse bg-amber-50 px-2.5 py-1 rounded-full border border-amber-200">
                            Chờ xác thực...
                        </span>
                        <span v-else-if="order.status === 'escalated'" class="text-[9px] font-black tracking-wide uppercase text-red-500 animate-pulse bg-red-50 px-2.5 py-1 rounded-full border border-red-200">
                            Đang giục món...
                        </span>
                        <span v-else-if="order.status === 'confirmed'" class="text-[9px] font-black tracking-wide uppercase text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
                            Đã nhận đơn
                        </span>
                        <span v-else-if="order.status === 'cancelled'" class="text-[9px] font-black tracking-wide uppercase text-slate-500 bg-slate-200 px-2.5 py-1 rounded-full">
                            Bị hủy
                        </span>
                    </div>

                    <!-- Progress Bar Steps -->
                    <div class="grid grid-cols-3 gap-2 mb-4 text-center relative">
                        <div class="flex flex-col items-center z-10">
                            <div :class="['size-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300 border-2', 
                                         ['waiting_verification', 'escalated', 'confirmed'].includes(order.status) ? 'bg-gradient-to-tr from-amber-400 to-amber-500 border-amber-300 text-white shadow-sm' : 'bg-white border-slate-200 text-slate-400']">
                                <Check v-if="['confirmed'].includes(order.status)" class="size-3.5 stroke-[3]" />
                                <span v-else>1</span>
                             </div>
                            <span class="text-[9px] font-bold mt-1.5 text-slate-500 uppercase tracking-wide">Đặt đơn</span>
                        </div>
                        <div class="flex flex-col items-center z-10 relative">
                            <!-- Link bar left -->
                            <div :class="['h-[2px] absolute w-[130%] -left-[65%] top-[15px] -z-10 transition-all duration-300', order.status === 'confirmed' ? 'bg-amber-400' : 'bg-slate-200']"></div>
                            
                            <div :class="['size-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300 border-2', 
                                         order.status === 'confirmed' ? 'bg-gradient-to-tr from-amber-400 to-amber-500 border-amber-300 text-white shadow-sm' : 'bg-white border-slate-200 text-slate-400']">
                                <Check v-if="order.order_status === 'completed' || order.items_status.every(i => i.status === 'served')" class="size-3.5 stroke-[3]" />
                                <span v-else>2</span>
                            </div>
                            <span class="text-[9px] font-bold mt-1.5 text-slate-500 uppercase tracking-wide">Chế biến</span>
                        </div>
                        <div class="flex flex-col items-center z-10 relative">
                            <!-- Link bar right -->
                            <div :class="['h-[2px] absolute w-[130%] -left-[65%] top-[15px] -z-10 transition-all duration-300', (order.order_status === 'completed' || order.items_status.every(i => i.status === 'served')) && order.status === 'confirmed' ? 'bg-amber-400' : 'bg-slate-200']"></div>
                            
                            <div :class="['size-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300 border-2', 
                                         order.status === 'confirmed' && (order.order_status === 'completed' || order.items_status.every(i => i.status === 'served')) ? 'bg-emerald-500 border-emerald-400 text-white shadow-sm' : 'bg-white border-slate-200 text-slate-400']">
                                3
                            </div>
                            <span class="text-[9px] font-bold mt-1.5 text-slate-500 uppercase tracking-wide">Lên món</span>
                        </div>
                    </div>

                    <!-- Items List / Detail Statuses -->
                    <div v-if="order.items_status.length" class="space-y-1.5 bg-white p-3 rounded-xl text-xxs text-slate-600 border border-slate-200 shadow-inner">
                        <div v-for="(item, idx) in order.items_status" :key="idx" class="flex justify-between items-center">
                            <span class="font-medium text-slate-700"><span class="text-amber-500 font-extrabold mr-1">{{ item.quantity }}x</span> {{ item.name }}</span>
                            <span :class="['px-2 py-0.5 rounded-lg text-[9px] font-bold tracking-wide uppercase border', 
                                           item.status === 'served' ? 'bg-emerald-50 text-emerald-600 border-emerald-250' : 
                                           item.status === 'preparing' ? 'bg-amber-50 text-amber-600 border-amber-250 animate-pulse' : 'bg-slate-100 text-slate-400 border-slate-200']">
                                {{ item.status === 'served' ? 'Đã lên món' : item.status === 'preparing' ? 'Đang nấu' : 'Chờ nấu' }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="text-[10px] text-slate-400 italic px-2 bg-white py-2 rounded-xl border border-slate-200">
                        Giỏ hàng: {{ order.cart_data.map(i => `${i.quantity}x ${i.name}`).join(', ') }}
                    </div>
                    
                    <!-- Nút Thanh toán VietQR động -->
                    <button
                        v-if="order.status === 'confirmed' && order.order_id && order.payment_status === 'unpaid'"
                        @click="openQrPaymentModal(order)"
                        class="mt-3 w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl text-xxs flex items-center justify-center gap-2 shadow-sm active:scale-98 transition-all cursor-pointer"
                    >
                        <CreditCard class="size-4" /> THANH TOÁN QR TRỰC TUYẾN
                    </button>
                    <!-- Báo đã thanh toán -->
                    <div
                        v-else-if="order.status === 'confirmed' && order.payment_status === 'paid'"
                        class="mt-3 w-full py-2 bg-emerald-50 border border-emerald-100 text-emerald-600 font-extrabold rounded-xl text-xxs flex items-center justify-center gap-1.5"
                    >
                        <Check class="size-4" /> ĐÃ THANH TOÁN HÓA ĐƠN
                    </div>
                </div>
            </div>

            <!-- Rate experience button -->
            <div v-if="activeTempOrders.some(o => o.status === 'confirmed')" class="mt-3.5 flex justify-end">
                <button 
                    @click="initializeFeedback"
                    class="text-[10px] font-black text-amber-600 hover:text-amber-500 flex items-center gap-1.5 bg-amber-50 px-3.5 py-2 rounded-xl border border-amber-100 active:scale-95 transition-all cursor-pointer"
                >
                    <HeartHandshake class="size-4" /> Đánh giá món ăn & phục vụ
                </button>
            </div>
        </div>

        <!-- ── Category Tabs (Horizontal Scrollable) ────────────────────────── -->
        <nav class="sticky top-[70px] z-20 bg-white/90 backdrop-blur-md border-b border-slate-100 py-3 px-5 overflow-x-auto scrollbar-none flex gap-2 shrink-0">
            <button
                v-for="cat in categories"
                :key="cat.id"
                :id="`category-tab-${cat.id}`"
                @click="selectCategory(cat.id)"
                :class="['px-4 py-1.5 rounded-full text-[11px] font-black whitespace-nowrap transition-all border duration-200 cursor-pointer',
                         selectedCategoryId === cat.id 
                             ? 'bg-slate-900 border-slate-900 text-white shadow-sm shadow-slate-900/10' 
                             : 'bg-slate-100 border-slate-200 text-slate-600 hover:text-slate-900']"
            >
                {{ cat.name }}
            </button>
        </nav>

        <!-- ── Products List ───────────────────────────────────────────── -->
        <main class="flex-1 overflow-y-auto px-5 py-5 pb-28">
            <div class="space-y-4">
                <div class="flex items-center gap-2 mb-4">
                    <span class="inline-block w-1.5 h-4.5 bg-slate-900 rounded-full"></span>
                    <h2 class="text-xs font-black text-slate-800 uppercase tracking-widest">
                        {{ categories.find(c => c.id === selectedCategoryId)?.name || 'Món ăn' }}
                    </h2>
                </div>
                
                <div class="grid grid-cols-1 gap-4">
                    <div
                        v-for="product in products.filter(p => p.category_id === selectedCategoryId)"
                        :key="product.id"
                        @click="openItemModal(product)"
                        :class="['p-4 bg-white border rounded-2xl flex gap-4 transition-all duration-300 relative overflow-hidden cursor-pointer shadow-sm hover:shadow-md',
                                 product.in_stock ? 'border-slate-200 hover:border-slate-300' : 'border-slate-100 opacity-55']"
                    >
                        <!-- Image Container -->
                        <div class="size-20 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 font-bold overflow-hidden shrink-0 border border-slate-200 relative">
                            <img v-if="product.image_url" :src="product.image_url" :alt="product.name" class="size-full object-cover" />
                            <Utensils v-else class="size-6 text-slate-400" />
                            
                            <!-- Out of Stock Overlay -->
                            <div v-if="!product.in_stock" class="absolute inset-0 bg-white/90 backdrop-blur-xs flex flex-col items-center justify-center text-center p-1">
                                <span v-if="product.is_kitchen_paused" class="text-[9px] font-black text-amber-600 uppercase border border-amber-200 px-1 rounded bg-amber-50/50 leading-tight">Tạm Dừng</span>
                                <span v-else-if="product.is_kitchen_out_of_stock" class="text-[9px] font-black text-orange-600 uppercase border border-orange-200 px-1 rounded bg-orange-50/50 leading-tight">Hết Món</span>
                                <span v-else class="text-[9px] font-black text-red-600 uppercase border border-red-200 px-1.5 py-0.5 rounded bg-red-50/50 leading-tight">Hết Hàng</span>
                            </div>
                        </div>

                        <!-- Product info -->
                        <div class="flex-1 flex flex-col justify-between min-w-0">
                            <div>
                                <h3 class="text-xs font-black text-slate-800 leading-snug">{{ product.name }}</h3>
                                <p class="text-[10px] text-slate-500 mt-1 leading-relaxed line-clamp-2">{{ product.description || 'Xem chi tiết nguyên liệu và đánh giá của món ăn này.' }}</p>
                                
                                <!-- Kitchen pause/out of stock countdown banner -->
                                <div v-if="product.is_kitchen_paused || product.is_kitchen_out_of_stock" class="mt-2 inline-flex items-center gap-1 bg-amber-50 border border-amber-100 text-amber-600 px-2 py-0.5 rounded text-[9px] font-bold">
                                    <Clock class="size-2.5 animate-pulse text-amber-500" />
                                    <span>Bán lại: {{ formatProductCountdown(product.paused_until || product.out_of_stock_until) }}</span>
                                </div>
                            </div>
                            
                            <div class="flex items-end justify-between mt-2.5" @click.stop>
                                <span class="text-xs font-black text-amber-600 tracking-wide">{{ formatCurrency(product.price) }}</span>
                                
                                <button
                                    @click="openItemModal(product)"
                                    :disabled="!product.in_stock"
                                    class="size-8 rounded-xl bg-slate-900 hover:bg-slate-850 text-white flex items-center justify-center font-bold disabled:bg-slate-100 disabled:text-slate-400 transition-all shadow-sm cursor-pointer"
                                >
                                    <Plus class="size-4 stroke-[3]" />
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
            class="fixed bottom-4 left-1/2 -translate-x-1/2 w-[calc(100vw-40px)] max-w-[360px] z-40"
        >
            <button
                @click="isCartOpen = true"
                class="w-full h-13 rounded-2xl bg-slate-900 hover:bg-slate-850 text-white flex items-center justify-between px-5 font-black shadow-lg active:scale-98 transition-all cursor-pointer"
            >
                <div class="flex items-center gap-2.5">
                    <div class="bg-white text-slate-950 text-[10px] font-black size-5 rounded-lg flex items-center justify-center shadow-md">
                        {{ cartTotalItems }}
                    </div>
                    <span class="text-xs uppercase tracking-wider font-extrabold">Xem giỏ hàng</span>
                </div>
                <div class="flex items-center gap-1 text-xs">
                    <span class="text-slate-350 font-bold">Tổng:</span>
                    <span class="text-sm font-black tracking-tight border-b-2 border-slate-800/30 pb-0.5">{{ formatCurrency(cartTotalPrice) }}</span>
                </div>
            </button>
        </div>

        <!-- ── Cart Drawer Slide Up ────────────────────────────────────────── -->
        <div v-if="isCartOpen" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-end justify-center">
            <!-- Overlay click closer -->
            <div class="absolute inset-0" @click="isCartOpen = false"></div>
            
            <div class="bg-white w-full max-w-md rounded-t-[30px] border-t border-slate-200 z-10 flex flex-col max-h-[85vh] animate-slide-up relative shadow-2xl">
                <header class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <ShoppingCart class="size-4.5 text-slate-800" />
                        <h2 class="text-xs font-black text-slate-800 uppercase tracking-wider">Giỏ hàng của bạn</h2>
                    </div>
                    <button @click="isCartOpen = false" class="p-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 cursor-pointer">
                        <X class="size-4" />
                    </button>
                </header>
                
                <!-- Cart items list -->
                <div class="flex-1 overflow-y-auto p-5 space-y-4">
                    <div v-for="item in cart" :key="item.product.id" class="flex gap-4.5 pb-4 border-b border-slate-100">
                        <div class="size-13 rounded-xl bg-slate-100 overflow-hidden border border-slate-200 shrink-0">
                            <img v-if="item.product.image_url" :src="item.product.image_url" :alt="item.product.name" class="size-full object-cover" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <h4 class="text-xs font-black text-slate-800 line-clamp-1">{{ item.product.name }}</h4>
                                <span class="text-xs font-black text-amber-600 tracking-wide">{{ formatCurrency(item.product.price * item.quantity) }}</span>
                            </div>
                            <p v-if="item.notes" class="text-xxs text-slate-500 italic mt-1 line-clamp-1 bg-slate-50 px-2 py-0.5 rounded-lg border border-slate-100 w-max">Ghi chú: {{ item.notes }}</p>
                            
                            <div class="flex items-center justify-between mt-2.5">
                                <span class="text-[10px] text-slate-400 font-bold">{{ formatCurrency(item.product.price) }} / món</span>
                                
                                <div class="flex items-center gap-2 bg-slate-50 p-1.5 rounded-xl border border-slate-200">
                                    <button @click="updateCartQuantity(item.product.id, -1)" class="p-1 hover:text-amber-500 text-slate-500 cursor-pointer">
                                        <Minus class="size-3" />
                                    </button>
                                    <span class="text-xs font-black w-4 text-center text-slate-800">{{ item.quantity }}</span>
                                    <button @click="updateCartQuantity(item.product.id, 1)" class="p-1 hover:text-amber-500 text-slate-500 cursor-pointer">
                                        <Plus class="size-3" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Information Form -->
                    <div class="pt-4 border-t border-slate-100 space-y-4">
                        <h3 class="text-xs font-black text-slate-650 uppercase tracking-wide">Thông tin gọi món</h3>
                        
                        <div class="space-y-3">
                            <div>
                                <label class="text-[9px] font-black text-slate-400 block mb-1.5 uppercase tracking-wider">Tên của bạn (Tùy chọn)</label>
                                <input 
                                    v-model="customerName" 
                                    type="text" 
                                    placeholder="Ví dụ: Anh Quân" 
                                    class="w-full h-11 px-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-slate-800 transition-all shadow-inner"
                                />
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-slate-400 block mb-1.5 uppercase tracking-wider">Số điện thoại (Tùy chọn)</label>
                                <input 
                                    v-model="customerPhone" 
                                    type="text" 
                                    placeholder="Để tích điểm hoặc liên hệ" 
                                    class="w-full h-11 px-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-slate-800 transition-all shadow-inner"
                                />
                            </div>
                        </div>
                        
                        <!-- Customer Loyalty Display Card -->
                        <div v-if="customerLoyalty" class="mt-2.5 p-4 bg-slate-50 border border-amber-200 rounded-2xl text-xxs text-left text-slate-500 space-y-2 animate-in fade-in duration-200 shadow-sm">
                            <p class="font-black text-slate-800 text-xs flex items-center gap-1.5">✨ Hội viên: {{ customerLoyalty.full_name }}</p>
                            <div class="flex items-center justify-between mt-1.5 border-t border-slate-200 pt-2">
                                <span>Hạng thẻ:</span>
                                <span v-if="customerLoyalty.membership_level === 'diamond'" class="text-indigo-600 font-black">💎 Kim Cương (Giảm 10%)</span>
                                <span v-else-if="customerLoyalty.membership_level === 'gold'" class="text-amber-600 font-black">⭐ Vàng (Giảm 5%)</span>
                                <span v-else class="text-slate-550 font-bold">🥈 Bạc (Tích điểm)</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Điểm tích lũy:</span>
                                <span class="text-amber-600 font-extrabold">{{ customerLoyalty.loyalty_points }} pt</span>
                            </div>
                            
                            <!-- Point redemption checkbox option -->
                            <div v-if="customerLoyalty.loyalty_points >= 10" class="flex items-center justify-between border-t border-slate-200 pt-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="usePoints" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
                                    <span class="font-bold text-slate-700">Dùng điểm tích lũy</span>
                                </label>
                                <span v-if="usePoints" class="text-emerald-650 font-black font-mono">-{{ formatCurrency(pointsDiscount) }} (dùng {{ pointsToRedeem }} pt)</span>
                                <span v-else class="text-slate-400">10 pt = 1,000đ</span>
                            </div>
                            
                            <p v-if="customerLoyalty.membership_level !== 'silver'" class="text-emerald-600 text-[10px] font-bold italic text-center mt-1 bg-emerald-50 py-1.5 rounded-lg border border-emerald-100">
                                * Tự động giảm giá {{ customerLoyalty.membership_level === 'diamond' ? '10%' : '5%' }} khi đơn hàng được duyệt!
                            </p>
                        </div>
                    </div>
                </div>
                
                <footer class="p-5 border-t border-slate-100 bg-slate-50">
                    <div v-if="pointsDiscount > 0" class="flex justify-between items-center mb-2 text-xxs text-slate-500">
                        <span>Giảm giá từ điểm:</span>
                        <span class="font-bold text-emerald-650">-{{ formatCurrency(pointsDiscount) }}</span>
                    </div>
                    <div class="flex justify-between items-center mb-4 text-slate-650">
                        <span class="text-xs">Tổng cộng:</span>
                        <span class="text-base font-black text-amber-600 tracking-wide">{{ formatCurrency(finalCartPrice) }}</span>
                    </div>
                    
                    <button
                        @click="submitOrder"
                        :disabled="isOrdering || cart.length === 0"
                        class="w-full h-12 rounded-xl bg-slate-900 hover:bg-slate-850 disabled:bg-slate-200 disabled:text-slate-400 text-white text-xs font-black flex items-center justify-center gap-2 shadow-sm active:scale-98 transition-all cursor-pointer"
                    >
                        <Loader2 v-if="isOrdering" class="size-4 animate-spin" />
                        <span v-else class="uppercase tracking-wider">Xác nhận gọi món</span>
                    </button>
                    <p class="text-center text-[9px] text-slate-450 mt-3">
                        * Hệ thống ghi nhận đơn đệm để nhân viên xác thực tại bàn.
                    </p>
                </footer>
            </div>
        </div>

        <!-- ── Product Detail / Customize Notes Modal (Enhanced Details View) ── -->
        <div v-if="isItemModalOpen && modalProduct" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-5">
            <div class="bg-white border border-slate-200 rounded-[26px] w-full max-w-sm overflow-hidden animate-zoom-in shadow-2xl flex flex-col max-h-[85vh]">
                <header class="p-4.5 border-b border-slate-100 flex items-center justify-between shrink-0">
                    <h3 class="text-sm font-black text-slate-800 line-clamp-1">Chi tiết món ăn</h3>
                    <button @click="isItemModalOpen = false" class="p-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 cursor-pointer">
                        <X class="size-4" />
                    </button>
                </header>
                
                <div class="p-5 space-y-5 overflow-y-auto flex-1">
                    <!-- Image Showcase -->
                    <div class="w-full h-44 rounded-2xl bg-slate-100 overflow-hidden border border-slate-200 relative shrink-0 shadow-inner flex items-center justify-center">
                        <img v-if="modalProduct.image_url" :src="modalProduct.image_url" :alt="modalProduct.name" class="size-full object-cover" />
                        <Utensils v-else class="size-10 text-slate-300" />
                    </div>

                    <!-- Title & SKU -->
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-base font-black text-slate-800">{{ modalProduct.name }}</h2>
                            <p class="text-[9px] font-bold text-slate-400 mt-0.5">Mã món: {{ modalProduct.sku }}</p>
                        </div>
                        <span class="text-base font-black text-amber-600 tracking-wide">{{ formatCurrency(modalProduct.price) }}</span>
                    </div>

                    <!-- Description -->
                    <div class="space-y-1.5">
                        <h4 class="text-xxs font-black text-slate-400 uppercase tracking-wider">Mô tả món ăn</h4>
                        <p class="text-xs text-slate-650 leading-relaxed bg-slate-50 p-3 rounded-xl border border-slate-100">{{ modalProduct.description || 'Món ăn ngon miệng, được lựa chọn kỹ càng và chế biến chuyên nghiệp bởi các đầu bếp hàng đầu.' }}</p>
                    </div>

                    <!-- Ingredients (Thành phần) -->
                    <div class="space-y-2">
                        <h4 class="text-xxs font-black text-slate-400 uppercase tracking-wider">Thành phần chính</h4>
                        <div class="flex flex-wrap gap-1.5">
                            <span 
                                v-for="ing in getProductIngredients(modalProduct.name)" 
                                :key="ing"
                                class="text-[10px] font-bold bg-slate-100 text-slate-600 px-2.5 py-1 rounded-lg border border-slate-200"
                            >
                                {{ ing }}
                            </span>
                        </div>
                    </div>

                    <!-- Ratings & Reviews (Đánh giá món đó) -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <h4 class="text-xxs font-black text-slate-400 uppercase tracking-wider">Đánh giá khách hàng</h4>
                            <div class="flex items-center gap-1 text-xs font-bold text-amber-500">
                                <Star class="size-3.5 fill-amber-500 text-amber-500" />
                                <span>4.9 / 5</span>
                                <span class="text-[10px] text-slate-400 font-medium">(2 nhận xét)</span>
                            </div>
                        </div>

                        <div class="space-y-2.5">
                            <div 
                                v-for="rev in getProductReviews(modalProduct.id)" 
                                :key="rev.author"
                                class="bg-slate-50 p-3 rounded-xl border border-slate-100 space-y-1.5"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-black text-slate-700">{{ rev.author }}</span>
                                    <div class="flex gap-0.5">
                                        <Star v-for="s in 5" :key="s" class="size-2.5 fill-amber-500 text-amber-500" />
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-500 leading-relaxed italic">"{{ rev.comment }}"</p>
                            </div>
                        </div>
                    </div>

                    <!-- Customize notes & Qty -->
                    <div class="pt-4 border-t border-slate-100 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-600 font-black uppercase tracking-wider">Số lượng đặt</span>
                            <div class="flex items-center gap-3 bg-slate-50 p-1.5 rounded-xl border border-slate-200">
                                <button @click="modalQuantity = Math.max(1, modalQuantity - 1)" class="p-1 text-slate-400 hover:text-amber-500 cursor-pointer">
                                    <Minus class="size-3.5" />
                                </button>
                                <span class="text-sm font-black w-6 text-center text-slate-800">{{ modalQuantity }}</span>
                                <button @click="modalQuantity = modalQuantity + 1" class="p-1 text-slate-400 hover:text-amber-500 cursor-pointer">
                                    <Plus class="size-3.5" />
                                </button>
                            </div>
                        </div>
                        
                        <div class="space-y-1.5">
                            <label class="text-[9px] font-black text-slate-400 block uppercase tracking-wider">Ghi chú cho bếp (Ví dụ: Không cay, ít ngọt,...)</label>
                            <textarea 
                                v-model="modalNotes" 
                                rows="2"
                                placeholder="Nhập ghi chú yêu cầu của bạn..." 
                                class="w-full p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-slate-800 resize-none shadow-inner"
                            ></textarea>
                        </div>
                    </div>
                </div>
                
                <footer class="p-4.5 border-t border-slate-100 bg-slate-50 flex gap-2.5 shrink-0">
                    <button @click="isItemModalOpen = false" class="flex-1 h-10 rounded-xl border border-slate-250 text-slate-500 text-xs font-bold hover:bg-slate-100 cursor-pointer transition-all">
                        Quay lại
                    </button>
                    <button @click="addToCart" class="flex-1 h-10 bg-slate-900 hover:bg-slate-850 text-white rounded-xl text-xs font-black shadow-sm cursor-pointer transition-all uppercase tracking-wider">
                        Thêm vào giỏ
                    </button>
                </footer>
            </div>
        </div>

        <!-- ── Detailed Feedback Modal ─────────────────────────────────────── -->
        <div v-if="showFeedbackSection" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-end justify-center">
            <div class="bg-white w-full max-w-md rounded-t-[30px] border-t border-slate-200 z-10 flex flex-col max-h-[90vh] animate-slide-up relative">
                <header class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <HeartHandshake class="size-4.5 text-slate-800" />
                        <h2 class="text-xs font-black text-slate-800 uppercase tracking-wider">Đánh giá dịch vụ</h2>
                    </div>
                    <button @click="showFeedbackSection = false" class="p-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 cursor-pointer">
                        <X class="size-4" />
                    </button>
                </header>
                
                <div class="flex-1 overflow-y-auto p-5 space-y-6">
                    <div v-if="feedbackSubmittedSuccessfully" class="py-14 text-center space-y-4">
                        <div class="size-14 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-500 flex items-center justify-center mx-auto text-2xl font-bold animate-bounce shadow-sm">
                            ✓
                        </div>
                        <h3 class="text-sm font-black text-slate-800">Đã gửi phản hồi thành công!</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">Cảm ơn bạn đã đóng góp ý kiến giúp chúng tôi nâng cao chất lượng phục vụ.</p>
                    </div>
                    
                    <div v-else class="space-y-6">
                        <!-- Step 1: Overall Experience -->
                        <div class="space-y-2.5">
                            <h3 class="text-xs font-black text-slate-500 uppercase tracking-wider">1. Trải nghiệm chung</h3>
                            <div class="flex items-center gap-2.5 justify-center py-3.5 bg-slate-50 rounded-2xl border border-slate-200 shadow-inner">
                                <button 
                                    v-for="star in 5" 
                                    :key="star" 
                                    @click="feedbackRating = star"
                                    class="p-1 cursor-pointer"
                                >
                                    <Star :class="['size-7.5 transition-all duration-200', star <= feedbackRating ? 'fill-amber-500 text-amber-500 scale-110' : 'text-slate-300']" />
                                </button>
                            </div>
                            <textarea 
                                v-model="feedbackContent"
                                rows="2"
                                placeholder="Nhập thêm nhận xét của bạn..."
                                class="w-full p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-slate-800 resize-none shadow-inner"
                            ></textarea>
                        </div>
                        
                        <!-- Step 2: Dish Feedback -->
                        <div v-if="Object.keys(itemsRating).length > 0" class="space-y-2.5">
                            <h3 class="text-xs font-black text-slate-500 uppercase tracking-wider">2. Đánh giá món ăn</h3>
                            
                            <div class="space-y-3">
                                <div 
                                    v-for="[pId, val] in Object.entries(itemsRating)" 
                                    :key="pId"
                                    class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-slate-700 line-clamp-1">
                                            {{ products.find(p => p.id === parseInt(pId))?.name || 'Món ăn' }}
                                        </span>
                                        
                                        <!-- Mini Stars -->
                                        <div class="flex items-center gap-1">
                                            <button 
                                                v-for="star in 5" 
                                                :key="star"
                                                @click="itemsRating[parseInt(pId)].rating = star"
                                                class="p-0.5 cursor-pointer"
                                            >
                                                <Star :class="['size-4.5 transition-all', star <= val.rating ? 'fill-amber-500 text-amber-500' : 'text-slate-300']" />
                                            </button>
                                        </div>
                                    </div>
                                    <input 
                                        v-model="itemsRating[parseInt(pId)].comment"
                                        type="text" 
                                        placeholder="Góp ý về món ăn này (ví dụ: mặn, ngon...)" 
                                        class="w-full h-9 px-3 rounded-xl bg-white border border-slate-200 text-xxs text-slate-800 focus:outline-none focus:border-slate-800"
                                    />
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 3: Staff Feedback -->
                        <div v-if="props.staffList.length > 0" class="space-y-2.5">
                            <h3 class="text-xs font-black text-slate-500 uppercase tracking-wider">3. Đánh giá nhân viên phục vụ</h3>
                            
                            <div class="space-y-3">
                                <div 
                                    v-for="staff in props.staffList" 
                                    :key="staff.employee_id"
                                    class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-slate-700">{{ staff.name }}</span>
                                            <span class="text-[9px] font-black uppercase text-slate-500 px-1.5 py-0.5 bg-slate-200 rounded border border-slate-300">{{ staff.role }}</span>
                                        </div>
                                        
                                        <!-- Mini Stars -->
                                        <div class="flex items-center gap-1">
                                            <button 
                                                v-for="star in 5" 
                                                :key="star"
                                                @click="staffRating[staff.employee_id].rating = star"
                                                class="p-0.5 cursor-pointer"
                                            >
                                                <Star :class="['size-4.5 transition-all', star <= staffRating[staff.employee_id].rating ? 'fill-amber-500 text-amber-500' : 'text-slate-300']" />
                                            </button>
                                        </div>
                                    </div>
                                    <input 
                                        v-model="staffRating[staff.employee_id].comment"
                                        type="text" 
                                        placeholder="Nhận xét về nhân viên này..." 
                                        class="w-full h-9 px-3 rounded-xl bg-white border border-slate-200 text-xxs text-slate-800 focus:outline-none focus:border-slate-800"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <footer v-if="!feedbackSubmittedSuccessfully" class="p-5 border-t border-slate-100 bg-slate-50">
                    <button
                        @click="submitFeedback"
                        :disabled="isSubmittingFeedback"
                        class="w-full h-12 rounded-xl bg-slate-900 hover:bg-slate-850 disabled:bg-slate-200 text-white text-xs font-black flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <Loader2 v-if="isSubmittingFeedback" class="size-4 animate-spin" />
                        <span v-else class="uppercase tracking-wider">Gửi phản hồi của bạn</span>
                    </button>
                </footer>
            </div>
        </div>

        <!-- ── VietQR Payment Modal ────────────────────────────────────────── -->
        <div v-if="isQrPaymentModalOpen && paymentQrOrder" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-[30px] w-full max-w-sm overflow-hidden animate-zoom-in shadow-2xl">
                <header class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-black text-slate-800 uppercase tracking-wide">Chuyển khoản VietQR</h3>
                        <p class="text-[10px] text-slate-400 mt-0.5">Hóa đơn: {{ paymentQrOrder.order_number }}</p>
                    </div>
                    <button @click="closeQrPaymentModal" class="p-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 cursor-pointer">
                        <X class="size-4" />
                    </button>
                </header>
                
                <div class="p-6 space-y-4 text-center">
                    <!-- Success State -->
                    <div v-if="paymentSuccess" class="py-6 space-y-4 animate-in fade-in duration-300">
                        <div class="size-16 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center mx-auto text-3xl font-bold animate-bounce shadow-md">
                            ✓
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-slate-800">Thanh toán thành công!</h4>
                            <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">Đơn hàng của quý khách đã hoàn tất thanh toán. Cảm ơn quý khách đã tin tưởng!</p>
                        </div>
                    </div>
                    
                    <!-- Standard QR / Loading State -->
                    <div v-else class="space-y-4">
                        <div class="bg-white p-3.5 rounded-[22px] inline-block shadow-lg border border-slate-200">
                            <div v-if="!paymentQrUrl" class="size-48 flex items-center justify-center">
                                <Loader2 class="size-8 text-amber-500 animate-spin" />
                            </div>
                            <img v-else :src="paymentQrUrl" alt="VietQR Code" class="size-48 object-contain" />
                        </div>
                        
                        <!-- Account Details -->
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-250 space-y-2 text-left text-xxs">
                            <div class="flex justify-between items-center">
                                <span class="text-slate-400 font-bold uppercase tracking-wider">Số tiền:</span>
                                <span class="text-amber-600 font-black text-sm tracking-wide">{{ formatCurrency(paymentQrOrder.total_amount) }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-400 font-bold uppercase tracking-wider">Nội dung:</span>
                                <span class="text-slate-700 font-extrabold select-all bg-white px-2 py-0.5 border border-slate-200 rounded">AVTORD{{ paymentQrOrder.order_id }}</span>
                            </div>
                        </div>

                        <!-- Waiting Spinner -->
                        <div class="flex items-center justify-center gap-2 text-xxs text-amber-600 font-black py-2 bg-amber-50 rounded-xl border border-amber-100">
                            <Loader2 class="size-3.5 animate-spin" />
                            <span class="uppercase tracking-wider">Chờ hệ thống ngân hàng xác nhận...</span>
                        </div>

                        <p class="text-[9px] text-slate-400 text-left leading-relaxed">
                            💡 **Hướng dẫn**: Dùng ứng dụng ngân hàng quét mã QR để chuyển khoản nhanh. Vui lòng giữ nguyên nội dung chuyển khoản để lệnh thanh toán tự động khớp trong 5-10 giây.
                        </p>
                    </div>
                </div>
                
                <!-- Bottom controls -->
                <footer class="p-5 border-t border-slate-100 bg-slate-50 flex flex-col gap-2.5">
                    <button @click="closeQrPaymentModal" class="w-full h-10 bg-slate-900 text-white text-xxs font-black hover:bg-slate-850 rounded-xl cursor-pointer transition-all">
                        {{ paymentSuccess ? 'Đóng' : 'Quay lại' }}
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
