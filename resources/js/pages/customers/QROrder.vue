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
    Award,
    Sparkles,
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
    table: {
        id: number;
        name: string;
        capacity: number;
        area_id: number;
        area_name: string;
        qr_token: string;
    };
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

const allergenFilter = ref<'all' | 'vegetarian' | 'no-seafood' | 'no-nuts'>('all');

const crossSellProducts = computed(() => {
    const cartProductIds = cart.value.map((item) => item.product.id);

    return props.products
        .filter((p) => p.in_stock && !cartProductIds.includes(p.id))
        .filter((p) => {
            const name = p.name.toLowerCase();

            return name.includes('coca') || name.includes('trà') || name.includes('nước') || name.includes('bia') || name.includes('kem') || name.includes('bánh') || name.includes('ép');
        })
        .slice(0, 3);
});

const addCrossSellItem = (product: Product) => {
    const idx = cart.value.findIndex((item) => item.product.id === product.id);

    if (idx > -1) {
        cart.value[idx].quantity++;
    } else {
        cart.value.push({ product, quantity: 1, notes: '' });
    }

    toast.success(`Đã thêm ${product.name} vào giỏ hàng!`);
};

const isCallStaffHubOpen = ref(false);
const isCallingStaffCustom = ref(false);

const staffCallPresets = [
    { label: '🛎️ Gọi hỗ trợ chung', message: 'Yêu cầu nhân viên hỗ trợ tại bàn' },
    { label: '🧊 Xin thêm đá lạnh', message: 'Yêu cầu thêm đá lạnh' },
    { label: '🥢 Xin thêm bát đũa / thìa', message: 'Yêu cầu thêm bát đũa, thìa ăn' },
    { label: '🧻 Xin khăn giấy', message: 'Yêu cầu thêm khăn giấy' },
    { label: '💵 Gọi thanh toán Tiền mặt', message: 'Yêu cầu thanh toán bằng Tiền mặt' },
    { label: '💳 Gọi thanh toán Chuyển khoản', message: 'Yêu cầu thanh toán qua Chuyển khoản/VietQR' },
];

const callStaffWithMessage = async (message: string) => {
    isCallingStaffCustom.value = true;

    try {
        const response = await axios.post(
            `/customer/order/call-staff/${props.restaurant.id}`,
            {
                table_id: props.table.id,
                message: message,
            },
        );
        toast.success(response.data.message);
        isCallStaffHubOpen.value = false;
        trackBehavior('call_staff');
    } catch (err) {
        toast.error('Có lỗi xảy ra. Vui lòng gọi trực tiếp nhân viên.');
    } finally {
        isCallingStaffCustom.value = false;
    }
};

const staffTip = ref<Record<number, number>>({});
const toggleStaffTip = (employeeId: number, amount: number) => {
    if (staffTip.value[employeeId] === amount) {
        staffTip.value[employeeId] = 0;
    } else {
        staffTip.value[employeeId] = amount;
    }
};

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

            toast.success(
                `Đã nhận diện thành viên: ${res.data.customer.full_name}`,
            );
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
        toast.error(
            'Vui lòng nhập số điện thoại trong giỏ hàng để truy cập cổng hội viên',
        );
        isCartOpen.value = true;

        return;
    }

    try {
        await axios.post(
            `/customer/portal/request-link/${props.restaurant.id}`,
            { phone },
        );
        toast.success(
            'Nếu số điện thoại đã là hội viên, link truy cập cổng hội viên đã được gửi qua SMS/Zalo của bạn.',
        );
    } catch {
        toast.error('Không gửi được link truy cập. Vui lòng thử lại sau.');
    }
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
const finalCartPrice = computed(() =>
    Math.max(0, cartTotalPrice.value - pointsDiscount.value),
);

// Behavior Tracking
const sessionToken = ref('');

function getOrGenerateSessionToken() {
    let token = null;

    try {
        token = sessionStorage.getItem('cdp_session_token');

        if (!token) {
            token =
                'sess_' +
                Math.random().toString(36).substring(2, 15) +
                '_' +
                Date.now();
            sessionStorage.setItem('cdp_session_token', token);
        }
    } catch (e) {
        // Fallback for Safari private mode or if storage is blocked
        token =
            'sess_fallback_' +
            Math.random().toString(36).substring(2, 15) +
            '_' +
            Date.now();
    }

    sessionToken.value = token;
}

async function trackBehavior(
    eventType: string,
    productId: number | null = null,
    quantity: number | null = null,
    extraMeta: Record<string, any> | null = null,
) {
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
                ...extraMeta,
            },
            customer_phone: customerPhone.value.trim() || null,
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
    if (paymentTimer.value) {
        clearInterval(paymentTimer.value);
    }

    paymentTimer.value = setInterval(async () => {
        try {
            const res = await axios.get(
                `/api/orders/${orderId}/payment-status`,
            );

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
    if (!paymentQrOrder.value) {
        return;
    }

    isSimulatingPayment.value = true;

    try {
        const res = await axios.post('/api/webhooks/payments/vietqr', {
            description: `AVTORD${paymentQrOrder.value.order_id}`,
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
const itemsRating = ref<Record<number, { rating: number; comment: string }>>(
    {},
);
const staffRating = ref<Record<number, { rating: number; comment: string }>>(
    {},
);
const isSubmittingFeedback = ref(false);
const feedbackSubmittedSuccessfully = ref(false);

// Filtered products
const filteredProducts = computed(() => {
    let list = props.products;

    if (selectedCategoryId.value) {
        list = list.filter((p) => p.category_id === selectedCategoryId.value);
    }

    if (allergenFilter.value === 'vegetarian') {
        list = list.filter((p) => {
            const name = p.name.toLowerCase();
            const desc = (p.description || '').toLowerCase();

            return name.includes('chay') || desc.includes('chay') || name.includes('rau') || name.includes('salad');
        });
    } else if (allergenFilter.value === 'no-seafood') {
        list = list.filter((p) => {
            const name = p.name.toLowerCase();
            const desc = (p.description || '').toLowerCase();

            return !name.includes('tôm') && !name.includes('cua') && !name.includes('cá') && !name.includes('mực') && !name.includes('nghêu') && !name.includes('hải sản') && !desc.includes('tôm') && !desc.includes('hải sản');
        });
    } else if (allergenFilter.value === 'no-nuts') {
        list = list.filter((p) => {
            const name = p.name.toLowerCase();
            const desc = (p.description || '').toLowerCase();

            return !name.includes('đậu phộng') && !name.includes('lạc') && !desc.includes('đậu phộng') && !desc.includes('hạt');
        });
    }

    return list;
});

// Cart computed
const cartTotalItems = computed(() =>
    cart.value.reduce((acc, item) => acc + item.quantity, 0),
);
const cartTotalPrice = computed(() =>
    cart.value.reduce(
        (acc, item) => acc + item.product.price * item.quantity,
        0,
    ),
);

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
    const existing = cart.value.find((item) => item.product.id === product.id);

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

    const existingIndex = cart.value.findIndex(
        (item) => item.product.id === modalProduct.value!.id,
    );

    if (existingIndex > -1) {
        cart.value[existingIndex].quantity = modalQuantity.value;
        cart.value[existingIndex].notes = modalNotes.value;
    } else {
        cart.value.push({
            product: modalProduct.value,
            quantity: modalQuantity.value,
            notes: modalNotes.value,
        });
    }

    isItemModalOpen.value = false;
    toast.success(`Đã thêm ${modalProduct.value.name} vào giỏ hàng`);
    trackBehavior('add_to_cart', modalProduct.value.id, modalQuantity.value);
}

function updateCartQuantity(productId: number, delta: number) {
    const idx = cart.value.findIndex((item) => item.product.id === productId);

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
            items: cart.value.map((item) => ({
                product_id: item.product.id,
                quantity: item.quantity,
                notes: item.notes || null,
            })),
        };

        const response = await axios.post(
            `/customer/order/${props.restaurant.id}/${props.table.qr_token}`,
            payload,
        );

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
        const errMsg =
            err.response?.data?.message ||
            'Không thể gửi yêu cầu đặt món. Vui lòng thử lại.';
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
        const response = await axios.post(
            `/customer/order/call-staff/${props.restaurant.id}`,
            {
                table_id: props.table.id,
                message: 'Khách yêu cầu phục vụ tại bàn',
            },
        );
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
        const response = await axios.post(
            `/customer/order/payment-request/${props.restaurant.id}`,
            {
                table_id: props.table.id,
            },
        );
        toast.success(response.data.message);
        trackBehavior('payment_request');
    } catch (err: any) {
        toast.error(
            err.response?.data?.message ||
                'Có lỗi xảy ra. Vui lòng gọi trực tiếp nhân viên.',
        );
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
    props.activeTempOrders.forEach((o) => {
        if (o.status === 'confirmed') {
            o.cart_data.forEach((item) => {
                itemsRating.value[item.product_id] = { rating: 5, comment: '' };
            });
        }
    });

    // Auto populate staff list
    staffRating.value = {};
    props.staffList.forEach((s) => {
        staffRating.value[s.employee_id] = { rating: 5, comment: '' };
    });
}

async function submitFeedback() {
    isSubmittingFeedback.value = true;

    // Find first confirmed order_id if available to link
    const confirmedOrder = props.activeTempOrders.find(
        (o) => o.status === 'confirmed',
    );

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
            comment: r.comment,
        })),
        staff_rating: Object.entries(staffRating.value).map(([empId, r]) => ({
            employee_id: parseInt(empId),
            rating: r.rating,
            comment: r.comment,
        })),
    };

    try {
        await axios.post(
            `/customer/order/feedback/${props.restaurant.id}`,
            payload,
        );
        feedbackSubmittedSuccessfully.value = true;
        const tipsSent = Object.entries(staffTip.value)
            .filter(([_, amt]) => amt > 0)
            .reduce((sum, [_, amt]) => sum + amt, 0);

        if (tipsSent > 0) {
            toast.success(`Cảm ơn bạn! Đã ghi nhận phản hồi và chuyển tiếp khoản tip ${tipsSent.toLocaleString()}đ tới nhân viên phục vụ.`);
        } else {
            toast.success('Gửi đánh giá thành công! Cảm ơn ý kiến của bạn.');
        }

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
        props.products.forEach((p) => {
            const timeStr = p.paused_until || p.out_of_stock_until;

            if (timeStr) {
                const diff = new Date(timeStr).getTime() - now.value.getTime();

                if (
                    diff <= 0 &&
                    (p.is_kitchen_paused || p.is_kitchen_out_of_stock)
                ) {
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
        window.Echo.channel(`table.${props.table.qr_token}`)
            .listen('.temporary_order.updated', (e: any) => {
                const order = props.activeTempOrders.find((o) => o.id === e.id);

                if (order || e.table_id === props.table.id) {
                    refetchActiveOrders();

                    if (e.status === 'confirmed') {
                        toast.success(
                            'Đơn hàng của bạn đã được nhân viên xác nhận và gửi xuống bếp!',
                        );
                    } else if (e.status === 'cancelled') {
                        toast.error(
                            'Đơn hàng của bạn đã bị từ chối/hủy. Vui lòng liên hệ nhân viên.',
                        );
                    }
                }
            })
            .listen('.order.paid', (e: any) => {
                if (
                    paymentQrOrder.value &&
                    e.order_id === paymentQrOrder.value.order_id
                ) {
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
        window.Echo.channel(`restaurant.${props.restaurant.id}`).listen(
            '.product.stock_updated',
            () => {
                refetchMenuOnly();
            },
        );
    }
});

function getProductIngredients(name: string) {
    const n = name.toLowerCase();

    if (n.includes('cơm') || n.includes('com')) {
        return [
            'Gạo tẻ thơm',
            'Sườn cốt lết',
            'Nước mắm chắt',
            'Mật ong rừng',
            'Hành tím',
            'Tỏi Lý Sơn',
            'Tiêu sọ',
        ];
    }

    if (n.includes('phở') || n.includes('pho')) {
        return [
            'Bánh phở tươi',
            'Thịt bò u hoa',
            'Xương ống bò ninh 24h',
            'Hành tây',
            'Hành lá',
            'Gừng nướng',
            'Thảo quả',
        ];
    }

    if (
        n.includes('trà') ||
        n.includes('tra') ||
        n.includes('uống') ||
        n.includes('nước') ||
        n.includes('chanh')
    ) {
        return [
            'Lá trà xanh Oolong',
            'Chanh tươi cắt lát',
            'Đường mía tự nhiên',
            'Nước tinh khiết',
            'Đá sạch tinh thể',
        ];
    }

    return [
        'Nguyên liệu sạch chọn lọc',
        'Gia vị hảo hạng',
        'Rau thơm sạch hữu cơ',
        'Quy trình khép kín',
    ];
}

function getProductReviews(id: number) {
    const reviewPools = [
        [
            {
                author: 'Anh Tuấn',
                rating: 5,
                comment:
                    'Món ăn đậm đà, sườn nướng mềm ngọt nước, đĩa cơm thơm phức.',
            },
            {
                author: 'Chị Lan',
                rating: 4.8,
                comment:
                    'Ngon tuyệt vời, nêm nếm rất vừa vị sạch sẽ. Sẽ ủng hộ tiếp.',
            },
        ],
        [
            {
                author: 'Minh Hoàng',
                rating: 5,
                comment:
                    'Vị nước dùng ngọt thanh tự nhiên từ xương bò ninh, rất cuốn.',
            },
            {
                author: 'Thu Hà',
                rating: 4.9,
                comment:
                    'Thịt bò tươi mềm ngọt, nước phở nóng hổi ăn kèm rau thơm cực ngon!',
            },
        ],
        [
            {
                author: 'Quốc Bảo',
                rating: 4.8,
                comment:
                    'Uống ngọt mát, thơm nồng vị trà nguyên bản, cực kỳ sảng khoái.',
            },
            {
                author: 'Ngọc Diệp',
                rating: 5,
                comment:
                    'Chanh tươi thơm lừng kết hợp trà ô long giải nhiệt rất tốt.',
            },
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

    if (window.Echo) {
        window.Echo.leaveChannel(`table.${props.table.qr_token}`);
        window.Echo.leaveChannel(`restaurant.${props.restaurant.id}`);
    }
});
</script>

<template>
    <Head :title="`Gọi Món Tại Bàn - ${restaurant.name}`" />

    <div
        class="relative mx-auto flex min-h-screen max-w-md flex-col border-x border-slate-200/80 bg-slate-50 font-sans text-slate-800 shadow-[0_0_50px_rgba(0,0,0,0.03)]"
    >
        <!-- ── Top bar Header ────────────────────────────────────────────── -->
        <header
            class="sticky top-0 z-30 flex items-center justify-between border-b border-slate-100 bg-white/80 px-5 py-4 shadow-sm backdrop-blur-md"
        >
            <div class="flex items-center gap-3">
                <div
                    class="size-11 overflow-hidden rounded-xl bg-gradient-to-tr from-amber-400 to-amber-500 p-0.5 shadow-sm"
                >
                    <div
                        class="flex size-full items-center justify-center overflow-hidden rounded-[9px] bg-white"
                    >
                        <img
                            v-if="restaurant.logo_url"
                            :src="restaurant.logo_url"
                            :alt="restaurant.name"
                            class="size-full object-cover"
                        />
                        <span
                            v-else
                            class="text-sm font-black text-amber-500"
                            >{{ restaurant.name[0] }}</span
                        >
                    </div>
                </div>
                <div>
                    <h1
                        class="text-xs font-black tracking-tight text-slate-800 uppercase"
                    >
                        {{ restaurant.name }}
                    </h1>
                    <div class="mt-0.5 flex items-center gap-1.5">
                        <span
                            class="absolute inline-block size-2 animate-ping rounded-full bg-emerald-500"
                        ></span>
                        <span
                            class="relative inline-block size-2 rounded-full bg-emerald-500"
                        ></span>
                        <p
                            class="text-[10px] font-extrabold tracking-wide text-amber-600 uppercase"
                        >
                            {{ table.name }} • {{ table.area_name }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    @click="goToDashboard"
                    class="hover:bg-slate-850 flex cursor-pointer items-center justify-center gap-1 rounded-xl border border-slate-800 bg-slate-900 p-2.5 text-[10px] font-black text-white shadow-sm transition-all active:scale-95"
                    title="Cổng Hội Viên"
                >
                    <Award class="size-4 text-amber-400" />
                    <span>Hội Viên</span>
                </button>
                <button
                    @click="isCallStaffHubOpen = true"
                    class="cursor-pointer rounded-xl border border-slate-200 bg-slate-100 p-2.5 text-slate-600 shadow-sm transition-all hover:text-slate-900 active:scale-95"
                    title="Gọi phục vụ"
                >
                    <Bell class="size-4" />
                </button>
                <button
                    @click="requestPayment"
                    :disabled="isRequestingPayment"
                    class="cursor-pointer rounded-xl border border-slate-200 bg-slate-100 p-2.5 text-slate-600 shadow-sm transition-all hover:text-slate-900 active:scale-95 disabled:opacity-50"
                    title="Yêu cầu thanh toán"
                >
                    <Loader2
                        v-if="isRequestingPayment"
                        class="size-4 animate-spin text-amber-500"
                    />
                    <CreditCard v-else class="size-4" />
                </button>
            </div>
        </header>

        <!-- ── Active Order Tracker ────────────────────────────────────────── -->
        <div
            v-if="activeTempOrders.length"
            class="shrink-0 border-b border-slate-100 bg-white p-5"
        >
            <h3
                class="mb-3.5 flex items-center gap-2 text-[10px] font-black tracking-widest text-slate-400 uppercase"
            >
                <Clock class="size-3.5 text-amber-500" /> Tiến độ món ăn của bạn
            </h3>

            <div class="space-y-4">
                <div
                    v-for="order in activeTempOrders"
                    :key="order.id"
                    class="relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm"
                >
                    <!-- Progress Timeline UI -->
                    <div class="mb-3.5 flex items-center justify-between">
                        <span
                            class="shadow-xxs rounded-lg border border-slate-200 bg-white px-2 py-0.5 text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                            >Yêu cầu #{{ order.id }}</span
                        >

                        <span
                            v-if="order.status === 'waiting_verification'"
                            class="animate-pulse rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[9px] font-black tracking-wide text-amber-600 uppercase"
                        >
                            Chờ xác thực...
                        </span>
                        <span
                            v-else-if="order.status === 'escalated'"
                            class="animate-pulse rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-[9px] font-black tracking-wide text-red-500 uppercase"
                        >
                            Đang giục món...
                        </span>
                        <span
                            v-else-if="order.status === 'confirmed'"
                            class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[9px] font-black tracking-wide text-emerald-600 uppercase"
                        >
                            Đã nhận đơn
                        </span>
                        <span
                            v-else-if="order.status === 'cancelled'"
                            class="rounded-full bg-slate-200 px-2.5 py-1 text-[9px] font-black tracking-wide text-slate-500 uppercase"
                        >
                            Bị hủy
                        </span>
                    </div>

                    <!-- Progress Bar Steps -->
                    <div
                        class="relative mb-4 grid grid-cols-3 gap-2 text-center"
                    >
                        <div class="z-10 flex flex-col items-center">
                            <div
                                :class="[
                                    'flex size-8 items-center justify-center rounded-full border-2 text-xs font-bold transition-all duration-300',
                                    [
                                        'waiting_verification',
                                        'escalated',
                                        'confirmed',
                                    ].includes(order.status)
                                        ? 'border-amber-300 bg-gradient-to-tr from-amber-400 to-amber-500 text-white shadow-sm'
                                        : 'border-slate-200 bg-white text-slate-400',
                                ]"
                            >
                                <Check
                                    v-if="['confirmed'].includes(order.status)"
                                    class="size-3.5 stroke-[3]"
                                />
                                <span v-else>1</span>
                            </div>
                            <span
                                class="mt-1.5 text-[9px] font-bold tracking-wide text-slate-500 uppercase"
                                >Đặt đơn</span
                            >
                        </div>
                        <div class="relative z-10 flex flex-col items-center">
                            <!-- Link bar left -->
                            <div
                                :class="[
                                    'absolute top-[15px] -left-[65%] -z-10 h-[2px] w-[130%] transition-all duration-300',
                                    order.status === 'confirmed'
                                        ? 'bg-amber-400'
                                        : 'bg-slate-200',
                                ]"
                            ></div>

                            <div
                                :class="[
                                    'flex size-8 items-center justify-center rounded-full border-2 text-xs font-bold transition-all duration-300',
                                    order.status === 'confirmed'
                                        ? 'border-amber-300 bg-gradient-to-tr from-amber-400 to-amber-500 text-white shadow-sm'
                                        : 'border-slate-200 bg-white text-slate-400',
                                ]"
                            >
                                <Check
                                    v-if="
                                        order.order_status === 'completed' ||
                                        order.items_status.every(
                                            (i) => i.status === 'served',
                                        )
                                    "
                                    class="size-3.5 stroke-[3]"
                                />
                                <span v-else>2</span>
                            </div>
                            <span
                                class="mt-1.5 text-[9px] font-bold tracking-wide text-slate-500 uppercase"
                                >Chế biến</span
                            >
                        </div>
                        <div class="relative z-10 flex flex-col items-center">
                            <!-- Link bar right -->
                            <div
                                :class="[
                                    'absolute top-[15px] -left-[65%] -z-10 h-[2px] w-[130%] transition-all duration-300',
                                    (order.order_status === 'completed' ||
                                        order.items_status.every(
                                            (i) => i.status === 'served',
                                        )) &&
                                    order.status === 'confirmed'
                                        ? 'bg-amber-400'
                                        : 'bg-slate-200',
                                ]"
                            ></div>

                            <div
                                :class="[
                                    'flex size-8 items-center justify-center rounded-full border-2 text-xs font-bold transition-all duration-300',
                                    order.status === 'confirmed' &&
                                    (order.order_status === 'completed' ||
                                        order.items_status.every(
                                            (i) => i.status === 'served',
                                        ))
                                        ? 'border-emerald-400 bg-emerald-500 text-white shadow-sm'
                                        : 'border-slate-200 bg-white text-slate-400',
                                ]"
                            >
                                3
                            </div>
                            <span
                                class="mt-1.5 text-[9px] font-bold tracking-wide text-slate-500 uppercase"
                                >Lên món</span
                            >
                        </div>
                    </div>

                    <!-- Items List / Detail Statuses -->
                    <div
                        v-if="order.items_status.length"
                        class="text-xxs space-y-1.5 rounded-xl border border-slate-200 bg-white p-3 text-slate-600 shadow-inner"
                    >
                        <div
                            v-for="(item, idx) in order.items_status"
                            :key="idx"
                            class="flex items-center justify-between"
                        >
                            <span class="font-medium text-slate-700"
                                ><span
                                    class="mr-1 font-extrabold text-amber-500"
                                    >{{ item.quantity }}x</span
                                >
                                {{ item.name }}</span
                            >
                            <span
                                :class="[
                                    'rounded-lg border px-2 py-0.5 text-[9px] font-bold tracking-wide uppercase',
                                    item.status === 'served'
                                        ? 'border-emerald-250 bg-emerald-50 text-emerald-600'
                                        : item.status === 'preparing'
                                          ? 'border-amber-250 animate-pulse bg-amber-50 text-amber-600'
                                          : 'border-slate-200 bg-slate-100 text-slate-400',
                                ]"
                            >
                                {{
                                    item.status === 'served'
                                        ? 'Đã lên món'
                                        : item.status === 'preparing'
                                          ? 'Đang nấu'
                                          : 'Chờ nấu'
                                }}
                            </span>
                        </div>
                    </div>
                    <div
                        v-else
                        class="rounded-xl border border-slate-200 bg-white px-2 py-2 text-[10px] text-slate-400 italic"
                    >
                        Giỏ hàng:
                        {{
                            order.cart_data
                                .map((i) => `${i.quantity}x ${i.name}`)
                                .join(', ')
                        }}
                    </div>

                    <!-- Nút Thanh toán VietQR động -->
                    <button
                        v-if="
                            order.status === 'confirmed' &&
                            order.order_id &&
                            order.payment_status === 'unpaid'
                        "
                        @click="openQrPaymentModal(order)"
                        class="text-xxs mt-3 flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-slate-900 py-2.5 font-black text-white shadow-sm transition-all hover:bg-slate-800 active:scale-98"
                    >
                        <CreditCard class="size-4" /> THANH TOÁN QR TRỰC TUYẾN
                    </button>
                    <!-- Báo đã thanh toán -->
                    <div
                        v-else-if="
                            order.status === 'confirmed' &&
                            order.payment_status === 'paid'
                        "
                        class="text-xxs mt-3 flex w-full items-center justify-center gap-1.5 rounded-xl border border-emerald-100 bg-emerald-50 py-2 font-extrabold text-emerald-600"
                    >
                        <Check class="size-4" /> ĐÃ THANH TOÁN HÓA ĐƠN
                    </div>
                </div>
            </div>

            <!-- Rate experience button -->
            <div
                v-if="activeTempOrders.some((o) => o.status === 'confirmed')"
                class="mt-3.5 flex justify-end"
            >
                <button
                    @click="initializeFeedback"
                    class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-100 bg-amber-50 px-3.5 py-2 text-[10px] font-black text-amber-600 transition-all hover:text-amber-500 active:scale-95"
                >
                    <HeartHandshake class="size-4" /> Đánh giá món ăn & phục vụ
                </button>
            </div>
        </div>

        <!-- ── Category Tabs (Horizontal Scrollable) ────────────────────────── -->
        <nav
            class="sticky top-[70px] z-20 flex shrink-0 scrollbar-none gap-2 overflow-x-auto border-b border-slate-100 bg-white/90 px-5 py-3 backdrop-blur-md"
        >
            <button
                v-for="cat in categories"
                :key="cat.id"
                :id="`category-tab-${cat.id}`"
                @click="selectCategory(cat.id)"
                :class="[
                    'cursor-pointer rounded-full border px-4 py-1.5 text-[11px] font-black whitespace-nowrap transition-all duration-200',
                    selectedCategoryId === cat.id
                        ? 'border-slate-900 bg-slate-900 text-white shadow-sm shadow-slate-900/10'
                        : 'border-slate-200 bg-slate-100 text-slate-600 hover:text-slate-900',
                ]"
            >
                {{ cat.name }}
            </button>
        </nav>

        <!-- ── Allergen & Dietary Filter Pills ── -->
        <div class="sticky top-[120px] z-20 flex gap-2 overflow-x-auto scrollbar-none px-5 py-2.5 bg-slate-50 border-b border-slate-100/80">
            <button
                @click="allergenFilter = 'all'"
                :class="[
                    'px-3.5 py-1 rounded-full text-[10px] font-extrabold border whitespace-nowrap transition-all active:scale-95 cursor-pointer',
                    allergenFilter === 'all'
                        ? 'border-indigo-650 bg-indigo-50 text-indigo-650'
                        : 'border-slate-200 bg-white text-slate-500 hover:text-slate-800'
                ]"
            >
                🍽️ Tất cả món
            </button>
            <button
                @click="allergenFilter = 'vegetarian'"
                :class="[
                    'px-3.5 py-1 rounded-full text-[10px] font-extrabold border whitespace-nowrap transition-all active:scale-95 cursor-pointer',
                    allergenFilter === 'vegetarian'
                        ? 'border-emerald-650 bg-emerald-50 text-emerald-650'
                        : 'border-slate-200 bg-white text-slate-500 hover:text-slate-800'
                ]"
            >
                🥬 Món Chay
            </button>
            <button
                @click="allergenFilter = 'no-seafood'"
                :class="[
                    'px-3.5 py-1 rounded-full text-[10px] font-extrabold border whitespace-nowrap transition-all active:scale-95 cursor-pointer',
                    allergenFilter === 'no-seafood'
                        ? 'border-rose-600 bg-rose-50 text-rose-600'
                        : 'border-slate-200 bg-white text-slate-500 hover:text-slate-800'
                ]"
            >
                🦞 Không hải sản
            </button>
            <button
                @click="allergenFilter = 'no-nuts'"
                :class="[
                    'px-3.5 py-1 rounded-full text-[10px] font-extrabold border whitespace-nowrap transition-all active:scale-95 cursor-pointer',
                    allergenFilter === 'no-nuts'
                        ? 'border-amber-600 bg-amber-50 text-amber-600'
                        : 'border-slate-200 bg-white text-slate-500 hover:text-slate-800'
                ]"
            >
                🥜 Không có hạt
            </button>
        </div>

        <!-- ── Products List ───────────────────────────────────────────── -->
        <main class="flex-1 overflow-y-auto px-5 py-5 pb-28">
            <div class="space-y-4">
                <div class="mb-4 flex items-center gap-2">
                    <span
                        class="inline-block h-4.5 w-1.5 rounded-full bg-slate-900"
                    ></span>
                    <h2
                        class="text-xs font-black tracking-widest text-slate-800 uppercase"
                    >
                        {{
                            categories.find((c) => c.id === selectedCategoryId)
                                ?.name || 'Món ăn'
                        }}
                    </h2>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    <div
                        v-for="product in filteredProducts"
                        :key="product.id"
                        @click="openItemModal(product)"
                        :class="[
                            'relative flex cursor-pointer gap-4 overflow-hidden rounded-2xl border bg-white p-4 shadow-sm transition-all duration-300 hover:shadow-md',
                            product.in_stock
                                ? 'border-slate-200 hover:border-slate-300'
                                : 'border-slate-100 opacity-55',
                        ]"
                    >
                        <!-- Image Container -->
                        <div
                            class="relative flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-100 font-bold text-slate-400"
                        >
                            <img
                                v-if="product.image_url"
                                :src="product.image_url"
                                :alt="product.name"
                                class="size-full object-cover"
                            />
                            <Utensils v-else class="size-6 text-slate-400" />

                            <!-- Out of Stock Overlay -->
                            <div
                                v-if="!product.in_stock"
                                class="absolute inset-0 flex flex-col items-center justify-center bg-white/90 p-1 text-center backdrop-blur-xs"
                            >
                                <span
                                    v-if="product.is_kitchen_paused"
                                    class="rounded border border-amber-200 bg-amber-50/50 px-1 text-[9px] leading-tight font-black text-amber-600 uppercase"
                                    >Tạm Dừng</span
                                >
                                <span
                                    v-else-if="product.is_kitchen_out_of_stock"
                                    class="rounded border border-orange-200 bg-orange-50/50 px-1 text-[9px] leading-tight font-black text-orange-600 uppercase"
                                    >Hết Món</span
                                >
                                <span
                                    v-else
                                    class="rounded border border-red-200 bg-red-50/50 px-1.5 py-0.5 text-[9px] leading-tight font-black text-red-600 uppercase"
                                    >Hết Hàng</span
                                >
                            </div>
                        </div>

                        <!-- Product info -->
                        <div
                            class="flex min-w-0 flex-1 flex-col justify-between"
                        >
                            <div>
                                <h3
                                    class="text-xs leading-snug font-black text-slate-800"
                                >
                                    {{ product.name }}
                                </h3>
                                <p
                                    class="mt-1 line-clamp-2 text-[10px] leading-relaxed text-slate-500"
                                >
                                    {{
                                        product.description ||
                                        'Xem chi tiết nguyên liệu và đánh giá của món ăn này.'
                                    }}
                                </p>

                                <!-- Kitchen pause/out of stock countdown banner -->
                                <div
                                    v-if="
                                        product.is_kitchen_paused ||
                                        product.is_kitchen_out_of_stock
                                    "
                                    class="mt-2 inline-flex items-center gap-1 rounded border border-amber-100 bg-amber-50 px-2 py-0.5 text-[9px] font-bold text-amber-600"
                                >
                                    <Clock
                                        class="size-2.5 animate-pulse text-amber-500"
                                    />
                                    <span
                                        >Bán lại:
                                        {{
                                            formatProductCountdown(
                                                product.paused_until ||
                                                    product.out_of_stock_until,
                                            )
                                        }}</span
                                    >
                                </div>
                            </div>

                            <div
                                class="mt-2.5 flex items-end justify-between"
                                @click.stop
                            >
                                <span
                                    class="text-xs font-black tracking-wide text-amber-600"
                                    >{{ formatCurrency(product.price) }}</span
                                >

                                <button
                                    @click="openItemModal(product)"
                                    :disabled="!product.in_stock"
                                    class="hover:bg-slate-850 flex size-8 cursor-pointer items-center justify-center rounded-xl bg-slate-900 font-bold text-white shadow-sm transition-all disabled:bg-slate-100 disabled:text-slate-400"
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
            class="fixed bottom-4 left-1/2 z-40 w-[calc(100vw-40px)] max-w-[360px] -translate-x-1/2"
        >
            <button
                @click="isCartOpen = true"
                class="hover:bg-slate-850 flex h-13 w-full cursor-pointer items-center justify-between rounded-2xl bg-slate-900 px-5 font-black text-white shadow-lg transition-all active:scale-98"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-5 items-center justify-center rounded-lg bg-white text-[10px] font-black text-slate-950 shadow-md"
                    >
                        {{ cartTotalItems }}
                    </div>
                    <span
                        class="text-xs font-extrabold tracking-wider uppercase"
                        >Xem giỏ hàng</span
                    >
                </div>
                <div class="flex items-center gap-1 text-xs">
                    <span class="text-slate-350 font-bold">Tổng:</span>
                    <span
                        class="border-b-2 border-slate-800/30 pb-0.5 text-sm font-black tracking-tight"
                        >{{ formatCurrency(cartTotalPrice) }}</span
                    >
                </div>
            </button>
        </div>

        <!-- ── Cart Drawer Slide Up ────────────────────────────────────────── -->
        <div
            v-if="isCartOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/60 backdrop-blur-sm"
        >
            <!-- Overlay click closer -->
            <div class="absolute inset-0" @click="isCartOpen = false"></div>

            <div
                class="animate-slide-up relative z-10 flex max-h-[85vh] w-full max-w-md flex-col rounded-t-[30px] border-t border-slate-200 bg-white shadow-2xl"
            >
                <header
                    class="flex items-center justify-between border-b border-slate-100 p-5"
                >
                    <div class="flex items-center gap-2">
                        <ShoppingCart class="size-4.5 text-slate-800" />
                        <h2
                            class="text-xs font-black tracking-wider text-slate-800 uppercase"
                        >
                            Giỏ hàng của bạn
                        </h2>
                    </div>
                    <button
                        @click="isCartOpen = false"
                        class="cursor-pointer rounded-xl bg-slate-100 p-1.5 text-slate-500 hover:bg-slate-200"
                    >
                        <X class="size-4" />
                    </button>
                </header>

                <!-- Cart items list -->
                <div class="flex-1 space-y-4 overflow-y-auto p-5">
                    <div
                        v-for="item in cart"
                        :key="item.product.id"
                        class="flex gap-4.5 border-b border-slate-100 pb-4"
                    >
                        <div
                            class="size-13 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-slate-100"
                        >
                            <img
                                v-if="item.product.image_url"
                                :src="item.product.image_url"
                                :alt="item.product.name"
                                class="size-full object-cover"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between">
                                <h4
                                    class="line-clamp-1 text-xs font-black text-slate-800"
                                >
                                    {{ item.product.name }}
                                </h4>
                                <span
                                    class="text-xs font-black tracking-wide text-amber-600"
                                    >{{
                                        formatCurrency(
                                            item.product.price * item.quantity,
                                        )
                                    }}</span
                                >
                            </div>
                            <p
                                v-if="item.notes"
                                class="text-xxs mt-1 line-clamp-1 w-max rounded-lg border border-slate-100 bg-slate-50 px-2 py-0.5 text-slate-500 italic"
                            >
                                Ghi chú: {{ item.notes }}
                            </p>

                            <div
                                class="mt-2.5 flex items-center justify-between"
                            >
                                <span
                                    class="text-[10px] font-bold text-slate-400"
                                    >{{ formatCurrency(item.product.price) }} /
                                    món</span
                                >

                                <div
                                    class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-1.5"
                                >
                                    <button
                                        @click="
                                            updateCartQuantity(
                                                item.product.id,
                                                -1,
                                            )
                                        "
                                        class="cursor-pointer p-1 text-slate-500 hover:text-amber-500"
                                    >
                                        <Minus class="size-3" />
                                    </button>
                                    <span
                                        class="w-4 text-center text-xs font-black text-slate-800"
                                        >{{ item.quantity }}</span
                                    >
                                    <button
                                        @click="
                                            updateCartQuantity(
                                                item.product.id,
                                                1,
                                            )
                                        "
                                        class="cursor-pointer p-1 text-slate-500 hover:text-amber-500"
                                    >
                                        <Plus class="size-3" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Smart Cross-selling Recommendations -->
                    <div v-if="crossSellProducts.length > 0" class="border-t border-slate-100 pt-4 text-left">
                        <h3 class="text-[10px] font-black tracking-wider text-slate-400 uppercase mb-2">
                            💡 Gợi ý mua kèm (Ngon hơn khi dùng chung)
                        </h3>
                        <div class="flex gap-3 overflow-x-auto scrollbar-none pb-2">
                            <div
                                v-for="p in crossSellProducts"
                                :key="p.id"
                                class="flex items-center gap-2 rounded-xl border border-slate-150 p-2 bg-slate-50/50 shrink-0 w-44 cursor-pointer hover:bg-slate-50 active:scale-95 transition-all text-left"
                                @click="addCrossSellItem(p)"
                            >
                                <div class="size-9 rounded-lg overflow-hidden border bg-slate-100 shrink-0">
                                    <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="size-full object-cover" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[10px] font-bold text-slate-800 truncate">{{ p.name }}</p>
                                    <p class="text-[10px] font-black text-amber-600 font-mono">{{ formatCurrency(p.price) }}</p>
                                </div>
                                <span class="text-slate-400 hover:text-indigo-600 font-extrabold text-xs shrink-0">+</span>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Information Form -->
                    <div class="space-y-4 border-t border-slate-100 pt-4">
                        <h3
                            class="text-slate-650 text-xs font-black tracking-wide uppercase"
                        >
                            Thông tin gọi món
                        </h3>

                        <div class="space-y-3">
                            <div>
                                <label
                                    class="mb-1.5 block text-[9px] font-black tracking-wider text-slate-400 uppercase"
                                    >Tên của bạn (Tùy chọn)</label
                                >
                                <input
                                    v-model="customerName"
                                    type="text"
                                    placeholder="Ví dụ: Anh Quân"
                                    class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 text-xs text-slate-800 shadow-inner transition-all focus:border-slate-800 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-[9px] font-black tracking-wider text-slate-400 uppercase"
                                    >Số điện thoại (Tùy chọn)</label
                                >
                                <input
                                    v-model="customerPhone"
                                    type="text"
                                    placeholder="Để tích điểm hoặc liên hệ"
                                    class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 text-xs text-slate-800 shadow-inner transition-all focus:border-slate-800 focus:outline-none"
                                />
                            </div>
                        </div>

                        <!-- Customer Loyalty Display Card -->
                        <div
                            v-if="customerLoyalty"
                            class="text-xxs mt-2.5 animate-in space-y-2 rounded-2xl border border-amber-200 bg-slate-50 p-4 text-left text-slate-500 shadow-sm duration-200 fade-in"
                        >
                            <p
                                class="flex items-center gap-1.5 text-xs font-black text-slate-800"
                            >
                                ✨ Hội viên: {{ customerLoyalty.full_name }}
                            </p>
                            <div
                                class="mt-1.5 flex items-center justify-between border-t border-slate-200 pt-2"
                            >
                                <span>Hạng thẻ:</span>
                                <span
                                    v-if="
                                        customerLoyalty.membership_level ===
                                        'diamond'
                                    "
                                    class="font-black text-indigo-600"
                                    >💎 Kim Cương (Giảm 10%)</span
                                >
                                <span
                                    v-else-if="
                                        customerLoyalty.membership_level ===
                                        'gold'
                                    "
                                    class="font-black text-amber-600"
                                    >⭐ Vàng (Giảm 5%)</span
                                >
                                <span v-else class="text-slate-550 font-bold"
                                    >🥈 Bạc (Tích điểm)</span
                                >
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Điểm tích lũy:</span>
                                <span class="font-extrabold text-amber-600"
                                    >{{
                                        customerLoyalty.loyalty_points
                                    }}
                                    pt</span
                                >
                            </div>

                            <!-- Progress milestone gauge -->
                            <div class="mt-2.5 space-y-1">
                                <div class="flex items-center justify-between text-[9px] font-bold text-slate-400">
                                    <span>Tiến trình thăng hạng:</span>
                                    <span>{{ customerLoyalty.loyalty_points }} / {{ customerLoyalty.membership_level === 'silver' ? '100 pt' : customerLoyalty.membership_level === 'gold' ? '500 pt' : 'Cực Đại' }}</span>
                                </div>
                                <div class="h-1.5 w-full rounded-full bg-slate-200 overflow-hidden">
                                    <div 
                                        class="h-full rounded-full bg-gradient-to-r from-amber-400 to-amber-500 transition-all"
                                        :style="{
                                            width: customerLoyalty.membership_level === 'silver' 
                                                ? Math.min(100, (customerLoyalty.loyalty_points / 100) * 100) + '%'
                                                : customerLoyalty.membership_level === 'gold'
                                                    ? Math.min(100, (customerLoyalty.loyalty_points / 500) * 100) + '%'
                                                    : '100%'
                                        }"
                                    ></div>
                                </div>
                                <p class="text-[9px] text-muted-foreground leading-normal mt-0.5">
                                    {{ 
                                        customerLoyalty.membership_level === 'silver'
                                            ? `Tích lũy thêm ${Math.max(0, 100 - customerLoyalty.loyalty_points)} điểm để thăng hạng Vàng (Hưởng giảm giá 5% hóa đơn).`
                                            : customerLoyalty.membership_level === 'gold'
                                                ? `Tích lũy thêm ${Math.max(0, 500 - customerLoyalty.loyalty_points)} điểm để thăng hạng Kim Cương (Hưởng giảm giá 10% hóa đơn).`
                                                : 'Bạn đã đạt cấp độ thành viên cao nhất! Xin cảm ơn quý khách.'
                                    }}
                                </p>
                            </div>

                            <!-- Point redemption checkbox option -->
                            <div
                                v-if="customerLoyalty.loyalty_points >= 10"
                                class="flex items-center justify-between border-t border-slate-200 pt-2"
                            >
                                <label
                                    class="flex cursor-pointer items-center gap-2"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="usePoints"
                                        class="rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                                    />
                                    <span class="font-bold text-slate-700"
                                        >Dùng điểm tích lũy</span
                                    >
                                </label>
                                <span
                                    v-if="usePoints"
                                    class="text-emerald-650 font-mono font-black"
                                    >-{{ formatCurrency(pointsDiscount) }} (dùng
                                    {{ pointsToRedeem }} pt)</span
                                >
                                <span v-else class="text-slate-400"
                                    >10 pt = 1,000đ</span
                                >
                            </div>

                            <p
                                v-if="
                                    customerLoyalty.membership_level !==
                                    'silver'
                                "
                                class="mt-1 rounded-lg border border-emerald-100 bg-emerald-50 py-1.5 text-center text-[10px] font-bold text-emerald-600 italic"
                            >
                                * Tự động giảm giá
                                {{
                                    customerLoyalty.membership_level ===
                                    'diamond'
                                        ? '10%'
                                        : '5%'
                                }}
                                khi đơn hàng được duyệt!
                            </p>
                        </div>
                    </div>
                </div>

                <footer class="border-t border-slate-100 bg-slate-50 p-5">
                    <div
                        v-if="pointsDiscount > 0"
                        class="text-xxs mb-2 flex items-center justify-between text-slate-500"
                    >
                        <span>Giảm giá từ điểm:</span>
                        <span class="text-emerald-650 font-bold"
                            >-{{ formatCurrency(pointsDiscount) }}</span
                        >
                    </div>
                    <div
                        class="text-slate-650 mb-4 flex items-center justify-between"
                    >
                        <span class="text-xs">Tổng cộng:</span>
                        <span
                            class="text-base font-black tracking-wide text-amber-600"
                            >{{ formatCurrency(finalCartPrice) }}</span
                        >
                    </div>

                    <button
                        @click="submitOrder"
                        :disabled="isOrdering || cart.length === 0"
                        class="hover:bg-slate-850 flex h-12 w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-slate-900 text-xs font-black text-white shadow-sm transition-all active:scale-98 disabled:bg-slate-200 disabled:text-slate-400"
                    >
                        <Loader2
                            v-if="isOrdering"
                            class="size-4 animate-spin"
                        />
                        <span v-else class="tracking-wider uppercase"
                            >Xác nhận gọi món</span
                        >
                    </button>
                    <p class="text-slate-450 mt-3 text-center text-[9px]">
                        * Hệ thống ghi nhận đơn đệm để nhân viên xác thực tại
                        bàn.
                    </p>
                </footer>
            </div>
        </div>

        <!-- ── Product Detail / Customize Notes Modal (Enhanced Details View) ── -->
        <div
            v-if="isItemModalOpen && modalProduct"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-5 backdrop-blur-sm"
        >
            <div
                class="animate-zoom-in flex max-h-[85vh] w-full max-w-sm flex-col overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-2xl"
            >
                <header
                    class="flex shrink-0 items-center justify-between border-b border-slate-100 p-4.5"
                >
                    <h3 class="line-clamp-1 text-sm font-black text-slate-800">
                        Chi tiết món ăn
                    </h3>
                    <button
                        @click="isItemModalOpen = false"
                        class="cursor-pointer rounded-xl bg-slate-100 p-1.5 text-slate-500 hover:bg-slate-200"
                    >
                        <X class="size-4" />
                    </button>
                </header>

                <div class="flex-1 space-y-5 overflow-y-auto p-5">
                    <!-- Image Showcase -->
                    <div
                        class="relative flex h-44 w-full shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 shadow-inner"
                    >
                        <img
                            v-if="modalProduct.image_url"
                            :src="modalProduct.image_url"
                            :alt="modalProduct.name"
                            class="size-full object-cover"
                        />
                        <Utensils v-else class="size-10 text-slate-300" />
                    </div>

                    <!-- Title & SKU -->
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-base font-black text-slate-800">
                                {{ modalProduct.name }}
                            </h2>
                            <p
                                class="mt-0.5 text-[9px] font-bold text-slate-400"
                            >
                                Mã món: {{ modalProduct.sku }}
                            </p>
                        </div>
                        <span
                            class="text-base font-black tracking-wide text-amber-600"
                            >{{ formatCurrency(modalProduct.price) }}</span
                        >
                    </div>

                    <!-- Description -->
                    <div class="space-y-1.5">
                        <h4
                            class="text-xxs font-black tracking-wider text-slate-400 uppercase"
                        >
                            Mô tả món ăn
                        </h4>
                        <p
                            class="text-slate-650 rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs leading-relaxed"
                        >
                            {{
                                modalProduct.description ||
                                'Món ăn ngon miệng, được lựa chọn kỹ càng và chế biến chuyên nghiệp bởi các đầu bếp hàng đầu.'
                            }}
                        </p>
                    </div>

                    <!-- Ingredients (Thành phần) -->
                    <div class="space-y-2">
                        <h4
                            class="text-xxs font-black tracking-wider text-slate-400 uppercase"
                        >
                            Thành phần chính
                        </h4>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="ing in getProductIngredients(
                                    modalProduct.name,
                                )"
                                :key="ing"
                                class="rounded-lg border border-slate-200 bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600"
                            >
                                {{ ing }}
                            </span>
                        </div>
                    </div>

                    <!-- Ratings & Reviews (Đánh giá món đó) -->
                    <div class="space-y-3">
                        <div
                            class="flex items-center justify-between border-b border-slate-100 pb-2"
                        >
                            <h4
                                class="text-xxs font-black tracking-wider text-slate-400 uppercase"
                            >
                                Đánh giá khách hàng
                            </h4>
                            <div
                                class="flex items-center gap-1 text-xs font-bold text-amber-500"
                            >
                                <Star
                                    class="size-3.5 fill-amber-500 text-amber-500"
                                />
                                <span>4.9 / 5</span>
                                <span
                                    class="text-[10px] font-medium text-slate-400"
                                    >(2 nhận xét)</span
                                >
                            </div>
                        </div>

                        <div class="space-y-2.5">
                            <div
                                v-for="rev in getProductReviews(
                                    modalProduct.id,
                                )"
                                :key="rev.author"
                                class="space-y-1.5 rounded-xl border border-slate-100 bg-slate-50 p-3"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-[10px] font-black text-slate-700"
                                        >{{ rev.author }}</span
                                    >
                                    <div class="flex gap-0.5">
                                        <Star
                                            v-for="s in 5"
                                            :key="s"
                                            class="size-2.5 fill-amber-500 text-amber-500"
                                        />
                                    </div>
                                </div>
                                <p
                                    class="text-[10px] leading-relaxed text-slate-500 italic"
                                >
                                    "{{ rev.comment }}"
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Customize notes & Qty -->
                    <div class="space-y-4 border-t border-slate-100 pt-4">
                        <div class="flex items-center justify-between">
                            <span
                                class="text-xs font-black tracking-wider text-slate-600 uppercase"
                                >Số lượng đặt</span
                            >
                            <div
                                class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-1.5"
                            >
                                <button
                                    @click="
                                        modalQuantity = Math.max(
                                            1,
                                            modalQuantity - 1,
                                        )
                                    "
                                    class="cursor-pointer p-1 text-slate-400 hover:text-amber-500"
                                >
                                    <Minus class="size-3.5" />
                                </button>
                                <span
                                    class="w-6 text-center text-sm font-black text-slate-800"
                                    >{{ modalQuantity }}</span
                                >
                                <button
                                    @click="modalQuantity = modalQuantity + 1"
                                    class="cursor-pointer p-1 text-slate-400 hover:text-amber-500"
                                >
                                    <Plus class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label
                                class="block text-[9px] font-black tracking-wider text-slate-400 uppercase"
                                >Ghi chú cho bếp (Ví dụ: Không cay, ít
                                ngọt,...)</label
                            >
                            <textarea
                                v-model="modalNotes"
                                rows="2"
                                placeholder="Nhập ghi chú yêu cầu của bạn..."
                                class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-800 shadow-inner focus:border-slate-800 focus:outline-none"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <footer
                    class="flex shrink-0 gap-2.5 border-t border-slate-100 bg-slate-50 p-4.5"
                >
                    <button
                        @click="isItemModalOpen = false"
                        class="border-slate-250 h-10 flex-1 cursor-pointer rounded-xl border text-xs font-bold text-slate-500 transition-all hover:bg-slate-100"
                    >
                        Quay lại
                    </button>
                    <button
                        @click="addToCart"
                        class="hover:bg-slate-850 h-10 flex-1 cursor-pointer rounded-xl bg-slate-900 text-xs font-black tracking-wider text-white uppercase shadow-sm transition-all"
                    >
                        Thêm vào giỏ
                    </button>
                </footer>
            </div>
        </div>

        <!-- ── Detailed Feedback Modal ─────────────────────────────────────── -->
        <div
            v-if="showFeedbackSection"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/60 backdrop-blur-sm"
        >
            <div
                class="animate-slide-up relative z-10 flex max-h-[90vh] w-full max-w-md flex-col rounded-t-[30px] border-t border-slate-200 bg-white"
            >
                <header
                    class="flex items-center justify-between border-b border-slate-100 p-5"
                >
                    <div class="flex items-center gap-2">
                        <HeartHandshake class="size-4.5 text-slate-800" />
                        <h2
                            class="text-xs font-black tracking-wider text-slate-800 uppercase"
                        >
                            Đánh giá dịch vụ
                        </h2>
                    </div>
                    <button
                        @click="showFeedbackSection = false"
                        class="cursor-pointer rounded-xl bg-slate-100 p-1.5 text-slate-500 hover:bg-slate-200"
                    >
                        <X class="size-4" />
                    </button>
                </header>

                <div class="flex-1 space-y-6 overflow-y-auto p-5">
                    <div
                        v-if="feedbackSubmittedSuccessfully"
                        class="space-y-4 py-14 text-center"
                    >
                        <div
                            class="mx-auto flex size-14 animate-bounce items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-2xl font-bold text-emerald-500 shadow-sm"
                        >
                            ✓
                        </div>
                        <h3 class="text-sm font-black text-slate-800">
                            Đã gửi phản hồi thành công!
                        </h3>
                        <p class="text-xs leading-relaxed text-slate-500">
                            Cảm ơn bạn đã đóng góp ý kiến giúp chúng tôi nâng
                            cao chất lượng phục vụ.
                        </p>
                    </div>

                    <div v-else class="space-y-6">
                        <!-- Step 1: Overall Experience -->
                        <div class="space-y-2.5">
                            <h3
                                class="text-xs font-black tracking-wider text-slate-500 uppercase"
                            >
                                1. Trải nghiệm chung
                            </h3>
                            <div
                                class="flex items-center justify-center gap-2.5 rounded-2xl border border-slate-200 bg-slate-50 py-3.5 shadow-inner"
                            >
                                <button
                                    v-for="star in 5"
                                    :key="star"
                                    @click="feedbackRating = star"
                                    class="cursor-pointer p-1"
                                >
                                    <Star
                                        :class="[
                                            'size-7.5 transition-all duration-200',
                                            star <= feedbackRating
                                                ? 'scale-110 fill-amber-500 text-amber-500'
                                                : 'text-slate-300',
                                        ]"
                                    />
                                </button>
                            </div>
                            <textarea
                                v-model="feedbackContent"
                                rows="2"
                                placeholder="Nhập thêm nhận xét của bạn..."
                                class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-800 shadow-inner focus:border-slate-800 focus:outline-none"
                            ></textarea>
                        </div>

                        <!-- Step 2: Dish Feedback -->
                        <div
                            v-if="Object.keys(itemsRating).length > 0"
                            class="space-y-2.5"
                        >
                            <h3
                                class="text-xs font-black tracking-wider text-slate-500 uppercase"
                            >
                                2. Đánh giá món ăn
                            </h3>

                            <div class="space-y-3">
                                <div
                                    v-for="[pId, val] in Object.entries(
                                        itemsRating,
                                    )"
                                    :key="pId"
                                    class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="line-clamp-1 text-xs font-bold text-slate-700"
                                        >
                                            {{
                                                products.find(
                                                    (p) =>
                                                        p.id === parseInt(pId),
                                                )?.name || 'Món ăn'
                                            }}
                                        </span>

                                        <!-- Mini Stars -->
                                        <div class="flex items-center gap-1">
                                            <button
                                                v-for="star in 5"
                                                :key="star"
                                                @click="
                                                    itemsRating[
                                                        parseInt(pId)
                                                    ].rating = star
                                                "
                                                class="cursor-pointer p-0.5"
                                            >
                                                <Star
                                                    :class="[
                                                        'size-4.5 transition-all',
                                                        star <= val.rating
                                                            ? 'fill-amber-500 text-amber-500'
                                                            : 'text-slate-300',
                                                    ]"
                                                />
                                            </button>
                                        </div>
                                    </div>
                                    <input
                                        v-model="
                                            itemsRating[parseInt(pId)].comment
                                        "
                                        type="text"
                                        placeholder="Góp ý về món ăn này (ví dụ: mặn, ngon...)"
                                        class="text-xxs h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-slate-800 focus:border-slate-800 focus:outline-none"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Staff Feedback -->
                        <div
                            v-if="props.staffList.length > 0"
                            class="space-y-2.5"
                        >
                            <h3
                                class="text-xs font-black tracking-wider text-slate-500 uppercase"
                            >
                                3. Đánh giá nhân viên phục vụ
                            </h3>

                            <div class="space-y-3">
                                <div
                                    v-for="staff in props.staffList"
                                    :key="staff.employee_id"
                                    class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-xs font-bold text-slate-700"
                                                >{{ staff.name }}</span
                                            >
                                            <span
                                                class="rounded border border-slate-300 bg-slate-200 px-1.5 py-0.5 text-[9px] font-black text-slate-500 uppercase"
                                                >{{ staff.role }}</span
                                            >
                                        </div>

                                        <!-- Mini Stars -->
                                        <div class="flex items-center gap-1">
                                            <button
                                                v-for="star in 5"
                                                :key="star"
                                                @click="
                                                    staffRating[
                                                        staff.employee_id
                                                    ].rating = star
                                                "
                                                class="cursor-pointer p-0.5"
                                            >
                                                <Star
                                                    :class="[
                                                        'size-4.5 transition-all',
                                                        star <=
                                                        staffRating[
                                                            staff.employee_id
                                                        ].rating
                                                            ? 'fill-amber-500 text-amber-500'
                                                            : 'text-slate-300',
                                                    ]"
                                                />
                                            </button>
                                        </div>
                                    </div>
                                    <input
                                        v-model="
                                            staffRating[staff.employee_id]
                                                .comment
                                        "
                                        type="text"
                                        placeholder="Nhận xét về nhân viên này..."
                                        class="text-xxs h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-slate-800 focus:border-slate-800 focus:outline-none"
                                    />

                                    <!-- Tip option -->
                                    <div class="mt-2.5 flex items-center justify-between">
                                        <span class="text-[10px] font-bold text-slate-500">Tặng tiền tip cho bạn này:</span>
                                        <div class="flex gap-1.5">
                                            <button
                                                v-for="tipVal in [10000, 20000, 50000]"
                                                :key="tipVal"
                                                type="button"
                                                class="px-2 py-0.5 rounded-lg border text-[9px] font-extrabold transition-all active:scale-95 cursor-pointer"
                                                :class="[
                                                    staffTip[staff.employee_id] === tipVal
                                                        ? 'border-emerald-600 bg-emerald-50 text-emerald-600'
                                                        : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'
                                                ]"
                                                @click="toggleStaffTip(staff.employee_id, tipVal)"
                                            >
                                                +{{ tipVal / 1000 }}k
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <footer
                    v-if="!feedbackSubmittedSuccessfully"
                    class="border-t border-slate-100 bg-slate-50 p-5"
                >
                    <button
                        @click="submitFeedback"
                        :disabled="isSubmittingFeedback"
                        class="hover:bg-slate-850 flex h-12 w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-slate-900 text-xs font-black text-white disabled:bg-slate-200"
                    >
                        <Loader2
                            v-if="isSubmittingFeedback"
                            class="size-4 animate-spin"
                        />
                        <span v-else class="tracking-wider uppercase"
                            >Gửi phản hồi của bạn</span
                        >
                    </button>
                </footer>
            </div>
        </div>

        <!-- ── Call Staff Hub Modal ────────────────────────────────────────── -->
        <div
            v-if="isCallStaffHubOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm animate-in fade-in duration-200"
            @click.self="isCallStaffHubOpen = false"
        >
            <div
                class="w-full max-w-sm overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-2xl animate-in zoom-in-95 duration-200"
            >
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div class="flex items-center gap-2">
                        <Bell class="size-4.5 text-indigo-650 animate-bounce" />
                        <h3 class="text-xs font-black tracking-wide text-slate-800 uppercase">
                            Gọi nhân viên phục vụ
                        </h3>
                    </div>
                    <button
                        @click="isCallStaffHubOpen = false"
                        class="cursor-pointer rounded-xl bg-slate-100 p-1 text-slate-500 hover:bg-slate-200"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="p-5 space-y-3">
                    <p class="text-xxs font-bold text-slate-400 text-left uppercase">Chọn yêu cầu cụ thể tại bàn {{ table.name }}:</p>
                    
                    <div class="grid grid-cols-1 gap-2.5">
                        <button
                            v-for="preset in staffCallPresets"
                            :key="preset.label"
                            type="button"
                            @click="callStaffWithMessage(preset.message)"
                            :disabled="isCallingStaffCustom"
                            class="flex w-full items-center justify-between rounded-2xl border border-slate-200 p-3.5 text-left text-xs font-black text-slate-700 transition-all hover:bg-slate-50 active:scale-98 disabled:opacity-50 cursor-pointer"
                        >
                            <span>{{ preset.label }}</span>
                            <span class="text-slate-350">➔</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── VietQR Payment Modal ────────────────────────────────────────── -->
        <div
            v-if="isQrPaymentModalOpen && paymentQrOrder"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
        >
            <div
                class="animate-zoom-in w-full max-w-sm overflow-hidden rounded-[30px] border border-slate-200 bg-white shadow-2xl"
            >
                <header
                    class="flex items-center justify-between border-b border-slate-100 p-5"
                >
                    <div>
                        <h3
                            class="text-xs font-black tracking-wide text-slate-800 uppercase"
                        >
                            Chuyển khoản VietQR
                        </h3>
                        <p class="mt-0.5 text-[10px] text-slate-400">
                            Hóa đơn: {{ paymentQrOrder.order_number }}
                        </p>
                    </div>
                    <button
                        @click="closeQrPaymentModal"
                        class="cursor-pointer rounded-xl bg-slate-100 p-1.5 text-slate-500 hover:bg-slate-200"
                    >
                        <X class="size-4" />
                    </button>
                </header>

                <div class="space-y-4 p-6 text-center">
                    <!-- Success State -->
                    <div
                        v-if="paymentSuccess"
                        class="animate-in space-y-4 py-6 duration-300 fade-in"
                    >
                        <div
                            class="mx-auto flex size-16 animate-bounce items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-3xl font-bold text-emerald-600 shadow-md"
                        >
                            ✓
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-slate-800">
                                Thanh toán thành công!
                            </h4>
                            <p
                                class="mt-1.5 text-xs leading-relaxed text-slate-500"
                            >
                                Đơn hàng của quý khách đã hoàn tất thanh toán.
                                Cảm ơn quý khách đã tin tưởng!
                            </p>
                        </div>
                    </div>

                    <!-- Standard QR / Loading State -->
                    <div v-else class="space-y-4">
                        <div
                            class="inline-block rounded-[22px] border border-slate-200 bg-white p-3.5 shadow-lg"
                        >
                            <div
                                v-if="!paymentQrUrl"
                                class="flex size-48 items-center justify-center"
                            >
                                <Loader2
                                    class="size-8 animate-spin text-amber-500"
                                />
                            </div>
                            <img
                                v-else
                                :src="paymentQrUrl"
                                alt="VietQR Code"
                                class="size-48 object-contain"
                            />
                        </div>

                        <!-- Account Details -->
                        <div
                            class="border-slate-250 text-xxs space-y-2 rounded-2xl border bg-slate-50 p-4 text-left"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="font-bold tracking-wider text-slate-400 uppercase"
                                    >Số tiền:</span
                                >
                                <span
                                    class="text-sm font-black tracking-wide text-amber-600"
                                    >{{
                                        formatCurrency(
                                            paymentQrOrder.total_amount,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="flex items-center justify-between">
                                <span
                                    class="font-bold tracking-wider text-slate-400 uppercase"
                                    >Nội dung:</span
                                >
                                <span
                                    class="rounded border border-slate-200 bg-white px-2 py-0.5 font-extrabold text-slate-700 select-all"
                                    >AVTORD{{ paymentQrOrder.order_id }}</span
                                >
                            </div>
                        </div>

                        <!-- Waiting Spinner -->
                        <div
                            class="text-xxs flex items-center justify-center gap-2 rounded-xl border border-amber-100 bg-amber-50 py-2 font-black text-amber-600"
                        >
                            <Loader2 class="size-3.5 animate-spin" />
                            <span class="tracking-wider uppercase"
                                >Chờ hệ thống ngân hàng xác nhận...</span
                            >
                        </div>

                        <p
                            class="text-left text-[9px] leading-relaxed text-slate-400"
                        >
                            💡 **Hướng dẫn**: Dùng ứng dụng ngân hàng quét mã QR
                            để chuyển khoản nhanh. Vui lòng giữ nguyên nội dung
                            chuyển khoản để lệnh thanh toán tự động khớp trong
                            5-10 giây.
                        </p>
                    </div>
                </div>

                <!-- Bottom controls: Simulator button -->
                <footer
                    class="flex flex-col gap-2.5 border-t border-slate-100 bg-slate-50 p-5"
                >
                    <button
                        v-if="!paymentSuccess"
                        @click="simulatePaymentSuccess"
                        :disabled="isSimulatingPayment"
                        class="text-xxs flex h-10 w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-100 font-black text-slate-800 transition-all hover:bg-slate-200 disabled:opacity-50"
                    >
                        <Loader2
                            v-if="isSimulatingPayment"
                            class="size-3.5 animate-spin"
                        />
                        <span>⚡ Giả lập thanh toán thành công (Test)</span>
                    </button>
                    <button
                        @click="closeQrPaymentModal"
                        class="text-xxs hover:bg-slate-850 h-10 w-full cursor-pointer rounded-xl bg-slate-900 font-black text-white transition-all"
                    >
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
    -ms-overflow-style: none; /* IE and Edge */
    scrollbar-width: none; /* Firefox */
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
