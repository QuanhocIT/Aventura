<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import {
    Utensils,
    Users,
    Clock,
    Search,
    Layers,
    Coffee,
    Sparkles,
    CheckCircle2,
    Calendar,
    User,
    ShoppingCart,
    Lock,
    Unlock,
    Plus,
    Minus,
    Trash2,
    DollarSign,
    RefreshCw,
    X,
    FileText,
    AlertTriangle,
    ShieldAlert,
    ChevronRight,
    TrendingUp,
    Send
} from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';

interface OrderItem {
    id?: number;
    product_id: number;
    product_name?: string;
    price: number;
    quantity: number;
    notes?: string;
    status?: string;
}

interface TableItem {
    id: number;
    name: string;
    area: string;
    capacity: number;
    status: 'available' | 'occupied' | 'reserved' | 'cleaning';
    active_order?: {
        id: number;
        order_number: string;
        status: string;
        subtotal: number;
        discount_amount: number;
        total_amount: number;
        note: string;
        is_split: boolean;
        is_red_flagged: boolean;
        items: OrderItem[];
    } | null;
}

interface ProductItem {
    id: number;
    name: string;
    price: number;
    category_id: number;
}

interface CategoryItem {
    id: number;
    name: string;
}

const props = defineProps<{
    tablesData: TableItem[];
    products: ProductItem[];
    categories: CategoryItem[];
    shiftInfo: {
        active_shift: {
            id: number;
            shift_name: string;
            check_in_at: string;
        } | null;
        shift_revenue: number;
    };
    qrOrders: any[];
    completedHistory: any[];
    weeklySchedules: any[];
    activeShifts: any[];
    employee: {
        id: number;
        full_name: string;
    } | null;
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user ?? null);

// State Management
const activeTab = ref<'tables' | 'qr' | 'history' | 'schedules'>('tables');
const searchQuery = ref('');
const selectedArea = ref('all');

// Cart State
const isCartOpen = ref(false);
const activeTable = ref<TableItem | null>(null);
const cartItems = ref<OrderItem[]>([]);
const cartNote = ref('');
const voucherCode = ref('');
const cartBounce = ref(false);
const drawerStep = ref<'select' | 'confirm'>('select');
const isNotified = ref(false);
const isSubmitting = ref(false);
const isPaying = ref(false);

// AI Suggestions State
const showAiSuggestionModal = ref(false);
const aiSuggestion = ref('');

// Split Order State
const showSplitModal = ref(false);
const splitItems = ref<OrderItem[]>([]);
const splitTableId = ref<number | null>(null);

// Payment State
const showPaymentModal = ref(false);
const paymentMethod = ref<'cash' | 'bank_transfer' | 'card' | 'ewallet'>('cash');
const cashReceived = ref<number | undefined>(undefined);
const changeAmount = computed(() => {
    if (!activeTable.value?.active_order || paymentMethod.value !== 'cash' || !cashReceived.value) return 0;
    const total = activeTable.value.active_order.total_amount;
    return Math.max(0, cashReceived.value - total);
});

// Self-Service State
const showSelfServiceModal = ref(false);
const selfServiceTab = ref<'schedule' | 'leave' | 'complaint'>('schedule');

// Schedule Registration Form
const regDay = ref('Monday');
const regShiftName = ref('');

// Leave Request Form
const leaveType = ref('annual');
const leaveStart = ref('');
const leaveEnd = ref('');
const leaveReason = ref('');

// Complaint Form
const complaintTargetId = ref<number | null>(null);
const complaintType = ref('');
const complaintDescription = ref('');

// Simulated 3rd Party Orders
const simulated3rdPartyOrders = ref([
    { id: 1, source: 'GrabFood', order_number: 'GF-8871', items: [{ name: 'Phở Bò Đặc Biệt', qty: 2 }, { name: 'Trà Chanh Sả Mật Ong', qty: 2 }], total: 180000, time: '3 phút trước' },
    { id: 2, source: 'ShopeeFood', order_number: 'SF-4903', items: [{ name: 'Cơm Sườn Nướng Lu', qty: 1 }], total: 55000, time: '8 phút trước' }
]);

// Live Clock
const currentTime = ref('');
const currentDate = ref('');
let timerId: any = null;

const updateTime = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    currentDate.value = now.toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
};

onMounted(() => {
    updateTime();
    timerId = setInterval(updateTime, 1000);
});

onUnmounted(() => {
    if (timerId) clearInterval(timerId);
});

// Filters
const uniqueAreas = computed(() => {
    const areas = new Set<string>();
    props.tablesData?.forEach(t => { if (t.area) areas.add(t.area); });
    return Array.from(areas);
});

const filteredTables = computed(() => {
    let list = props.tablesData ?? [];
    if (selectedArea.value !== 'all') {
        list = list.filter(t => t.area === selectedArea.value);
    }
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        list = list.filter(t => t.name.toLowerCase().includes(query));
    }
    return [...list].sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }));
});

// Cart Logic
const openTableOrder = (table: TableItem) => {
    activeTable.value = table;
    cartNote.value = table.active_order?.note ?? '';
    voucherCode.value = '';

    if (table.active_order) {
        // Edit existing order
        cartItems.value = table.active_order.items.map(item => ({ ...item }));
        drawerStep.value = 'confirm';
        isNotified.value = true;
    } else {
        // Start new order
        cartItems.value = [];
        drawerStep.value = 'select';
        isNotified.value = false;
    }
    isCartOpen.value = true;
};

const triggerCartBounce = () => {
    cartBounce.value = true;
    setTimeout(() => { cartBounce.value = false; }, 300);
};

const addToCart = (product: ProductItem) => {
    isNotified.value = false;
    triggerCartBounce();
    const existing = cartItems.value.find(item => item.product_id === product.id && !item.id);
    if (existing) {
        existing.quantity += 1;
    } else {
        cartItems.value.push({
            product_id: product.id,
            product_name: product.name,
            price: product.price,
            quantity: 1,
            notes: ''
        });
    }
};

const getCartItemQty = (productId: number) => {
    const items = cartItems.value.filter(item => item.product_id === productId && !item.id);
    return items.reduce((sum, item) => sum + item.quantity, 0);
};

const handleProductCardClick = (product: ProductItem) => {
    const qty = getCartItemQty(product.id);
    if (qty === 0) {
        addToCart(product);
    } else {
        increaseProductQty(product.id);
    }
};

const increaseProductQty = (productId: number) => {
    isNotified.value = false;
    const item = cartItems.value.find(item => item.product_id === productId && !item.id);
    if (item) {
        item.quantity += 1;
    } else {
        const product = props.products.find(p => p.id === productId);
        if (product) {
            addToCart(product);
        }
    }
};

const decreaseProductQty = (productId: number) => {
    isNotified.value = false;
    const itemIndex = cartItems.value.findIndex(i => i.product_id === productId && !i.id);
    if (itemIndex !== -1) {
        const item = cartItems.value[itemIndex];
        if (item.quantity > 1) {
            item.quantity -= 1;
        } else {
            cartItems.value.splice(itemIndex, 1);
        }
    }
};

const increaseQty = (item: OrderItem) => {
    isNotified.value = false;
    item.quantity += 1;
};

const decreaseQty = (item: OrderItem) => {
    if (activeTable.value?.active_order && activeTable.value.active_order.status !== 'pending' && item.id) {
        // Locked
        return;
    }
    isNotified.value = false;
    if (item.quantity > 1) {
        item.quantity -= 1;
    } else {
        removeItem(item);
    }
};

const removeItem = (item: OrderItem) => {
    if (activeTable.value?.active_order && activeTable.value.active_order.status !== 'pending' && item.id) {
        // Locked
        return;
    }
    isNotified.value = false;
    cartItems.value = cartItems.value.filter(i => i !== item);
};

const totalCartAmount = computed(() => {
    return cartItems.value.reduce((sum, item) => sum + (item.price * item.quantity), 0);
});

// Send Kitchen / Notify
const submitOrder = () => {
    if (isSubmitting.value) return;
    isSubmitting.value = true;
    console.log('✅ submitOrder clicked');
    console.log('activeTable:', activeTable.value);
    console.log('cartItems:', cartItems.value);
    console.log('drawerStep:', drawerStep.value);
    console.log('isNotified:', isNotified.value);

    if (!activeTable.value) {
        alert('❌ Vui lòng chọn một bàn!');
        isSubmitting.value = false;
        return;
    }

    if (cartItems.value.length === 0) {
        alert('❌ Vui lòng thêm ít nhất một món ăn!');
        isSubmitting.value = false;
        return;
    }

    const requestData = {
        note: cartNote.value,
        items: cartItems.value.map(item => ({
            id: item.id || null,
            product_id: item.product_id,
            quantity: item.quantity,
            unit_price: item.price,
            notes: item.notes || ''
        }))
    };

    console.log('📤 Request data:', JSON.stringify(requestData, null, 2));

    if (activeTable.value.active_order) {
        // Update existing order
        console.log('📨 Updating order ID:', activeTable.value.active_order.id);
        router.patch(`/orders/${activeTable.value.active_order.id}`, requestData, {
            preserveState: true,
            onSuccess: (page: any) => {
                console.log('✅ Order updated successfully', page);
                isNotified.value = true;
                setTimeout(() => {
                    const updated = props.tablesData.find(t => t.id === activeTable.value!.id);
                    if (updated) {
                        activeTable.value = updated;
                        cartItems.value = updated.active_order?.items.map(item => ({ ...item })) ?? [];
                    }
                }, 200);
                alert('✅ Đã gửi thông báo bổ sung món xuống nhà bếp thành công!');
            },
            onError: (errors: any) => {
                console.error('❌ Order update error:', errors);
                const errorMessage = Object.values(errors).flat().join('\n') || 'Có lỗi xảy ra khi cập nhật đơn hàng!';
                alert('❌ Lỗi cập nhật đơn:\n' + errorMessage);
            },
            onFinish: () => {
                isSubmitting.value = false;
            }
        });
    } else {
        // Create new order
        const createData = {
            table_id: activeTable.value.id,
            ...requestData
        };
        console.log('📨 Creating new order:', JSON.stringify(createData, null, 2));
        router.post('/orders', createData, {
            preserveState: true,
            onSuccess: (page: any) => {
                console.log('✅ Order created successfully', page);
                isNotified.value = true;
                setTimeout(() => {
                    const updated = props.tablesData.find(t => t.id === activeTable.value!.id);
                    if (updated) {
                        activeTable.value = updated;
                        cartItems.value = updated.active_order?.items.map(item => ({ ...item })) ?? [];
                    }
                }, 200);
                alert('✅ Đã tạo đơn mới thành công!');
            },
            onError: (errors: any) => {
                console.error('❌ Order creation error:', errors);
                const errorMessage = Object.values(errors).flat().join('\n') || 'Có lỗi xảy ra khi tạo đơn hàng!';
                alert('❌ Lỗi tạo đơn:\n' + errorMessage);
            },
            onFinish: () => {
                isSubmitting.value = false;
            }
        });
    }
};

// Send Order to Kitchen (Locks status from pending to confirmed)
const sendToKitchen = () => {
    if (!activeTable.value?.active_order) return;
    router.patch(`/orders/${activeTable.value.active_order.id}/status`, {
        status: 'confirmed'
    }, {
        onSuccess: () => {
            isCartOpen.value = false;
            alert('Đơn hàng đã được chính thức đẩy xuống bếp và KHÓA để chống gian lận!');
        }
    });
};

// Confirm QR Code Order
const confirmQrOrder = (orderId: number) => {
    axios.post(`/orders/${orderId}/confirm-qr`).then(res => {
        if (res.data.success) {
            aiSuggestion.value = res.data.upsell.suggestion || 'Hãy gợi ý khách chọn thêm đồ uống giải nhiệt!';
            showAiSuggestionModal.value = true;
            router.reload({ only: ['qrOrders', 'tablesData'] });
        }
    }).catch(err => {
        alert(err.response?.data?.message || 'Có lỗi xảy ra khi xác thực đơn QR.');
    });
};

// 3rd Party Order Acceptance
const accept3rdPartyOrder = (simulatedOrder: any) => {
    const items = simulatedOrder.items.map((si: any) => {
        const prod = props.products.find(p => p.name === si.name);
        return {
            product_id: prod ? prod.id : props.products[0].id,
            quantity: si.qty,
            notes: `Đơn hàng từ ${simulatedOrder.source}`
        };
    });

    router.post('/orders', {
        table_id: null,
        note: `Tiếp nhận từ ${simulatedOrder.source} (${simulatedOrder.order_number})`,
        items: items
    }, {
        onSuccess: () => {
            simulated3rdPartyOrders.value = simulated3rdPartyOrders.value.filter(o => o.id !== simulatedOrder.id);
            alert(`Đã tiếp nhận thành công đơn hàng từ ${simulatedOrder.source}!`);
        }
    });
};

// Apply Voucher Code
const applyVoucher = () => {
    if (!activeTable.value?.active_order || !voucherCode.value) return;
    axios.post('/api/promotions/apply', {
        order_id: activeTable.value.active_order.id,
        code: voucherCode.value
    }).then(res => {
        alert(res.data.message);
        router.reload({ only: ['tablesData'] });
        // Refresh local active order to show updated total
        if (activeTable.value) {
            setTimeout(() => {
                const updated = props.tablesData.find(t => t.id === activeTable.value!.id);
                if (updated) openTableOrder(updated);
            }, 300);
        }
    }).catch(err => {
        alert(err.response?.data?.message || 'Mã giảm giá không hợp lệ.');
    });
};

// Payment Dialog
const openPayment = () => {
    cashReceived.value = activeTable.value?.active_order?.total_amount ?? 0;
    showPaymentModal.value = true;
};

const processPayment = () => {
    if (!activeTable.value?.active_order || isPaying.value) return;
    isPaying.value = true;
    axios.post(`/orders/${activeTable.value.active_order.id}/pay`, {
        payment_method: paymentMethod.value,
        cash_received: cashReceived.value,
        change_amount: changeAmount.value
    }).then(res => {
        showPaymentModal.value = false;
        isCartOpen.value = false;
        alert('Đã thanh toán hóa đơn và in biên lai thành công. Bàn đã chuyển sang trạng thái trống.');
        router.reload({ only: ['tablesData', 'shiftInfo', 'completedHistory'] });
    }).catch(err => {
        alert(err.response?.data?.message || 'Lỗi xử lý thanh toán.');
    }).finally(() => {
        isPaying.value = false;
    });
};

// Split Order dialog
const openSplitOrder = () => {
    if (!activeTable.value?.active_order) return;
    splitItems.value = activeTable.value.active_order.items.map(i => ({ ...i, quantity: 1 }));
    splitTableId.value = null;
    showSplitModal.value = true;
};

const processSplit = () => {
    if (!activeTable.value?.active_order || !splitTableId.value || isSubmitting.value) return;
    isSubmitting.value = true;

    // Build items to split
    const itemsToSplit = splitItems.value
        .filter(si => si.quantity > 0)
        .map(si => ({
            order_item_id: si.id,
            quantity: si.quantity
        }));

    router.post(`/orders/${activeTable.value.active_order.id}/split`, {
        table_id: splitTableId.value,
        items: itemsToSplit
    }, {
        onSuccess: () => {
            showSplitModal.value = false;
            isCartOpen.value = false;
            alert('Đã tách đơn sang bàn trống thành công. Quản lý và Chủ nhà hàng đã nhận được cảnh báo gian lận realtime!');
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
};

// Self Service Handlers
const openSelfService = (tab: 'schedule' | 'leave' | 'complaint') => {
    selfServiceTab.value = tab;
    showSelfServiceModal.value = true;
};

const handleRegisterSchedule = () => {
    if (!props.employee) return;
    router.post('/employees/schedules', {
        day: regDay.value,
        employee_name: props.employee.full_name,
        shift_name: regShiftName.value
    }, {
        onSuccess: () => {
            alert('Đăng ký lịch làm việc thành công!');
            router.reload({ only: ['weeklySchedules'] });
        }
    });
};

const handleLeaveRequest = () => {
    if (!props.employee) return;
    router.post('/employees/leaves', {
        employee_id: props.employee.id,
        leave_type: leaveType.value,
        start_date: leaveStart.value,
        end_date: leaveEnd.value,
        reason: leaveReason.value
    }, {
        onSuccess: () => {
            alert('Nộp đơn xin nghỉ thành công! Chờ cấp trên phê duyệt.');
            leaveReason.value = '';
        }
    });
};

const handleComplaint = () => {
    if (!complaintTargetId.value || !complaintType.value || !complaintDescription.value) return;
    router.post('/violations', {
        employee_id: complaintTargetId.value,
        violation_type: complaintType.value,
        description: complaintDescription.value,
        is_anonymous: true,
        occurred_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
    }, {
        onSuccess: () => {
            alert('Gửi khiếu nại ẩn danh thành công! Thông tin của bạn được bảo mật tuyệt đối.');
            complaintDescription.value = '';
        }
    });
};

const number_format = (value: number | string) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return isNaN(num) ? '0' : num.toLocaleString('vi-VN');
};

const getTableStatusInfo = (status: TableItem['status']) => {
    switch (status) {
        case 'available':
            return {
                label: 'Bàn trống',
                class: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400',
                dotClass: 'bg-emerald-500 animate-pulse',
                cardBorder: 'hover:border-emerald-500/50'
            };
        case 'occupied':
            return {
                label: 'Có khách',
                class: 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/20 dark:text-indigo-400',
                dotClass: 'bg-indigo-500',
                cardBorder: 'hover:border-indigo-500/50'
            };
        case 'reserved':
            return {
                label: 'Đã đặt',
                class: 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-950/20 dark:text-violet-400',
                dotClass: 'bg-violet-500',
                cardBorder: 'hover:border-violet-500/50'
            };
        case 'cleaning':
            return {
                label: 'Đang dọn',
                class: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/20 dark:text-amber-400',
                dotClass: 'bg-amber-500',
                cardBorder: 'hover:border-amber-500/50'
            };
    }
};
</script>

<template>
    <Head title="POS Thu Ngân & Vận Hành Tiền Sảnh" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full min-h-screen bg-slate-50/50 dark:bg-slate-900/40">

        <!-- ── HEADER VÀ THỜI GIAN THỰC ──────────────────────────────── -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/70 dark:bg-slate-900/80 backdrop-blur-md p-6 rounded-3xl border border-slate-200/50 dark:border-slate-800/80 shadow-sm">
            <div>
                <h1 class="text-2xl font-black text-slate-800 dark:text-slate-100 flex items-center gap-2.5">
                    <Coffee class="size-6 text-indigo-600" />
                    BepsoViet Operational POS
                </h1>
                <p class="text-xs text-muted-foreground mt-1 font-medium flex flex-wrap items-center gap-2">
                    <span>Nhân viên phục vụ: <span class="font-bold text-slate-700 dark:text-slate-300">{{ props.employee?.full_name ?? 'Chưa cập nhật' }}</span></span>
                    <span v-if="props.shiftInfo?.active_shift" class="text-slate-300 dark:text-slate-700">|</span>
                    <span v-if="props.shiftInfo?.active_shift">Ca làm việc: <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ props.shiftInfo.active_shift.shift_name }}</span></span>
                    <span class="text-slate-300 dark:text-slate-700">|</span>
                    <span>Doanh thu ca này: <span class="font-black text-emerald-600 dark:text-emerald-400">{{ number_format(props.shiftInfo?.shift_revenue ?? 0) }}đ</span></span>
                </p>
            </div>

            <div class="flex items-center gap-3 bg-slate-100 dark:bg-slate-800 px-4 py-2.5 rounded-2xl">
                <Clock class="size-4 text-slate-500" />
                <div class="text-left font-mono">
                    <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ currentTime }}</span>
                    <span class="text-[10px] text-muted-foreground ml-2">{{ currentDate }}</span>
                </div>
            </div>
        </div>



        <!-- ── TAB ĐIỀU HƯỚNG CHÍNH ────────────────────────────────────── -->
        <div class="flex border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="t in [
                    { id: 'tables', label: 'Sơ đồ bàn phục vụ', icon: Utensils, count: 0 },
                    { id: 'qr', label: 'Đơn QR & App thứ 3', icon: Sparkles, count: props.qrOrders.length },
                    { id: 'history', label: 'Lịch sử & Bếp', icon: Clock, count: 0 }
                ]"
                :key="t.id"
                @click="activeTab = t.id"
                class="px-6 py-3.5 font-bold text-xs flex items-center gap-2 border-b-2 transition-all relative"
                :class="activeTab === t.id
                    ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 bg-white/40 dark:bg-slate-900/40 rounded-t-xl'
                    : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-100/50'"
            >
                <component :is="t.icon" class="size-4" />
                {{ t.label }}
                <span v-if="t.count" class="bg-rose-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full animate-bounce">
                    {{ t.count }}
                </span>
            </button>
        </div>

        <!-- ── TAB 1: SƠ ĐỒ BÀN ──────────────────────────────────────── -->
        <div v-if="activeTab === 'tables'" class="flex flex-col gap-6">
            <!-- Bộ lọc khu vực -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="flex flex-wrap items-center gap-1.5">
                    <Button
                        size="sm"
                        variant="outline"
                        @click="selectedArea = 'all'"
                        class="rounded-xl px-4 text-xs"
                        :class="selectedArea === 'all' ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-white text-slate-600'"
                    >
                        Tất cả khu vực
                    </Button>
                    <Button
                        v-for="area in uniqueAreas"
                        :key="area"
                        size="sm"
                        variant="outline"
                        @click="selectedArea = area"
                        class="rounded-xl px-4 text-xs"
                        :class="selectedArea === area ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-white text-slate-600'"
                    >
                        {{ area }}
                    </Button>
                </div>

                <div class="relative w-full sm:w-64">
                    <Search class="absolute left-3 top-2.5 size-4 text-slate-400" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Tìm tên bàn..."
                        class="pl-9 rounded-xl text-xs"
                    />
                </div>
            </div>

            <!-- Grid danh sách bàn -->
            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 gap-4">
                <Card
                    v-for="table in filteredTables"
                    :key="table.id"
                    @click="openTableOrder(table)"
                    class="bg-white dark:bg-slate-900 border shadow-sm hover:shadow-md cursor-pointer transition-all duration-200 hover:-translate-y-0.5 rounded-2xl flex flex-col justify-between"
                    :class="[getTableStatusInfo(table.status).cardBorder, table.active_order?.is_red_flagged ? 'border-rose-400 bg-rose-50/20' : '']"
                >
                    <div class="p-4 flex flex-col gap-2">
                        <div class="flex justify-between items-center">
                            <span class="text-[9px] font-bold text-slate-400 tracking-wider uppercase truncate max-w-[65%]">
                                {{ table.area }}
                            </span>
                            <span class="flex h-2 w-2 relative">
                                <span :class="['animate-ping absolute inline-flex h-full w-full rounded-full opacity-75', getTableStatusInfo(table.status).dotClass]"></span>
                                <span :class="['relative inline-flex rounded-full h-2 w-2', getTableStatusInfo(table.status).dotClass]"></span>
                            </span>
                        </div>

                        <div class="flex justify-between items-end mt-1">
                            <h4 class="text-lg font-black text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                                <Utensils class="size-4.5 text-slate-500" />
                                Bàn {{ table.name }}
                            </h4>
                            <span class="text-[10px] text-muted-foreground font-semibold flex items-center gap-0.5">
                                <Users class="size-3" />
                                {{ table.capacity }}
                            </span>
                        </div>
                    </div>

                    <!-- Footer bàn -->
                    <div class="px-4 py-2.5 bg-slate-50/50 dark:bg-slate-950/20 border-t flex items-center justify-between rounded-b-2xl">
                        <span class="text-[10px] font-bold font-mono text-indigo-600" v-if="table.active_order">
                            {{ number_format(table.active_order.total_amount) }}đ
                        </span>
                        <span class="text-[10px] text-slate-400 font-semibold" v-else>Trống</span>

                        <Badge variant="outline" class="text-[9px] font-extrabold px-1.5 py-0" :class="getTableStatusInfo(table.status).class">
                            {{ getTableStatusInfo(table.status).label }}
                        </Badge>
                    </div>
                </Card>
            </div>
        </div>

        <!-- ── TAB 2: ĐƠN QR VÀ BÊN THỨ 3 ─────────────────────────────── -->
        <div v-if="activeTab === 'qr'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Danh sách QR Orders -->
            <Card class="rounded-3xl shadow-sm">
                <CardHeader>
                    <CardTitle class="text-sm font-black flex items-center gap-2">
                        <Sparkles class="size-5 text-indigo-600" />
                        Đơn đặt QR từ Khách tại bàn (Chờ xác nhận)
                    </CardTitle>
                    <CardDescription class="text-xs">
                        Nhân viên cần di chuyển tới bàn kiểm tra trước khi xác nhận đẩy xuống bếp.
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-6 pt-0 flex flex-col gap-3">
                    <div v-if="props.qrOrders.length === 0" class="text-center py-8 text-xs text-muted-foreground">
                        Không có đơn hàng QR nào đang chờ xác nhận.
                    </div>
                    <div
                        v-for="order in props.qrOrders"
                        :key="order.id"
                        class="p-4 bg-slate-50 dark:bg-slate-900 border rounded-2xl flex flex-col gap-2.5"
                    >
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-xs text-indigo-600 font-mono">Bàn {{ order.table_name }} ({{ order.order_number }})</span>
                            <span class="text-[10px] text-muted-foreground">{{ order.created_at }}</span>
                        </div>
                        <div class="text-xs text-slate-600 dark:text-slate-300">
                            <div v-for="item in order.items" :key="item.product_name" class="flex justify-between">
                                <span>- {{ item.product_name }}</span>
                                <span class="font-bold">x{{ item.quantity }}</span>
                            </div>
                        </div>
                        <Separator class="my-1" />
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold font-mono">Tổng: {{ number_format(order.total_amount) }}đ</span>
                            <Button size="sm" class="rounded-xl h-8 text-xs" @click="confirmQrOrder(order.id)">
                                Xác nhận tại bàn
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Đơn hàng 3rd Party (Grab, ShopeeFood) -->
            <Card class="rounded-3xl shadow-sm">
                <CardHeader>
                    <CardTitle class="text-sm font-black flex items-center gap-2">
                        <Users class="size-5 text-emerald-600" />
                        Đơn đặt từ Đối tác 3rd Party (Simulated Live)
                    </CardTitle>
                    <CardDescription class="text-xs font-medium">
                        Xem và bấm Tiếp nhận để tạo đơn hàng delivery chính thức trên hệ thống.
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-6 pt-0 flex flex-col gap-3">
                    <div v-if="simulated3rdPartyOrders.length === 0" class="text-center py-8 text-xs text-muted-foreground">
                        Hết đơn hàng đối tác chưa nhận.
                    </div>
                    <div
                        v-for="order in simulated3rdPartyOrders"
                        :key="order.id"
                        class="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 rounded-2xl flex flex-col gap-2"
                    >
                        <div class="flex justify-between items-center">
                            <Badge variant="outline" class="bg-emerald-50 text-emerald-700 border-emerald-200 font-extrabold text-[9px]">
                                {{ order.source }}
                            </Badge>
                            <span class="text-[10px] text-muted-foreground">{{ order.time }}</span>
                        </div>
                        <div class="text-xs text-slate-700 dark:text-slate-300 font-bold">
                            Mã đơn: {{ order.order_number }}
                        </div>
                        <div class="text-xs text-slate-500">
                            <div v-for="item in order.items" :key="item.name" class="flex justify-between">
                                <span>{{ item.name }}</span>
                                <span class="font-bold">x{{ item.qty }}</span>
                            </div>
                        </div>
                        <Separator class="my-1" />
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold font-mono">Tổng: {{ number_format(order.total) }}đ</span>
                            <Button size="sm" variant="outline" class="rounded-xl border-indigo-200 text-indigo-600 h-8 text-xs hover:bg-indigo-50" @click="accept3rdPartyOrder(order)">
                                Tiếp nhận đơn
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ── TAB 3: LỊCH SỬ & KITCHEN PROGRESS ────────────────────────── -->
        <div v-if="activeTab === 'history'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Bếp Progress -->
            <Card class="rounded-3xl shadow-sm">
                <CardHeader>
                    <CardTitle class="text-sm font-black flex items-center gap-2">
                        <RefreshCw class="size-5 text-amber-600 animate-spin-slow" />
                        Theo dõi tiến độ làm món của Bếp
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-6 pt-0 flex flex-col gap-3">
                    <div class="flex flex-col gap-3">
                        <div v-for="table in props.tablesData.filter(t => t.active_order)" :key="table.id" class="p-3 border rounded-2xl flex flex-col gap-1.5 bg-white dark:bg-slate-900">
                            <div class="flex justify-between items-center">
                                <span class="font-black text-xs">Bàn {{ table.name }} ({{ table.active_order?.order_number }})</span>
                                <Badge variant="outline" class="text-[9px] font-bold uppercase" :class="table.active_order?.status === 'preparing' ? 'bg-amber-50 text-amber-700' : 'bg-indigo-50 text-indigo-700'">
                                    {{ table.active_order?.status }}
                                </Badge>
                            </div>
                            <div class="text-[11px] text-slate-500">
                                <div v-for="item in table.active_order?.items" :key="item.id" class="flex justify-between">
                                    <span>{{ item.product_name }} x{{ item.quantity }}</span>
                                    <span class="font-bold text-slate-700 dark:text-slate-400 capitalize">{{ item.status }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Lịch sử thanh toán -->
            <Card class="rounded-3xl shadow-sm">
                <CardHeader>
                    <CardTitle class="text-sm font-black flex items-center gap-2">
                        <FileText class="size-5 text-indigo-600" />
                        Hóa đơn đã thanh toán gần đây (Ca làm này)
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-6 pt-0 flex flex-col gap-3">
                    <div v-if="props.completedHistory.length === 0" class="text-center py-8 text-xs text-muted-foreground">
                        Chưa ghi nhận hóa đơn thanh toán nào trong ca này.
                    </div>
                    <div
                        v-for="h in props.completedHistory"
                        :key="h.id"
                        class="p-3.5 bg-slate-50 dark:bg-slate-900 border rounded-2xl flex justify-between items-center"
                    >
                        <div class="text-left">
                            <span class="font-black text-xs text-slate-800 dark:text-slate-200">{{ h.order_number }}</span>
                            <p class="text-[10px] text-muted-foreground mt-0.5">Bàn: {{ h.table_name }} | Thời gian: {{ h.completed_at }}</p>
                        </div>
                        <span class="font-mono font-bold text-xs text-emerald-600">+{{ number_format(h.total_amount) }}đ</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ── TAB 4: LỊCH TRỰC CÁ NHÂN (FALLBACK DISPLAY) ───────────────── -->
        <div v-if="activeTab === 'schedules'" class="flex flex-col gap-6">
            <Card class="rounded-3xl shadow-sm">
                <CardHeader>
                    <CardTitle class="text-sm font-black flex items-center gap-2">
                        <Calendar class="size-5 text-indigo-600" />
                        Lịch trực tuần này của tôi
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-6 pt-0 flex flex-col gap-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <Card v-for="sch in props.weeklySchedules" :key="sch.id" class="border shadow-none rounded-2xl">
                            <CardContent class="p-4 flex flex-col gap-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ sch.date }}</span>
                                    <Badge variant="outline" class="text-[9px] uppercase font-black" :class="sch.status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-indigo-50 text-indigo-700'">
                                        {{ sch.status }}
                                    </Badge>
                                </div>
                                <Separator />
                                <div class="text-xs text-slate-600 dark:text-slate-300">
                                    <span class="font-bold">{{ sch.shift_name }}</span>
                                    <p class="text-[10px] text-muted-foreground mt-1">{{ sch.shift_time }}</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ── GIỎ HÀNG / DRAWER GỌI MÓN (SHEET PANEL SLIDE-OVER) ─────────── -->
        <div v-if="isCartOpen && activeTable" class="fixed inset-y-0 right-0 z-50 w-full sm:max-w-lg bg-white dark:bg-slate-950 shadow-2xl flex flex-col justify-between border-l border-slate-200 dark:border-slate-800 animate-slide-in">
            <!-- Header Drawer -->
            <div class="p-6 border-b flex justify-between items-center bg-slate-50/50 dark:bg-slate-900/50">
                <div>
                    <h3 class="font-black text-lg text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        Bàn {{ activeTable.name }}
                        <Badge variant="outline" class="text-[9px]" :class="activeTable.active_order ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700'">
                            {{ activeTable.active_order ? 'Đang có khách' : 'Bàn trống' }}
                        </Badge>
                    </h3>
                    <p class="text-[10px] text-muted-foreground" v-if="activeTable.active_order">
                        Mã đơn: {{ activeTable.active_order.order_number }} ({{ activeTable.active_order.status }})
                    </p>
                </div>
                <Button variant="ghost" size="icon" class="rounded-xl" @click="isCartOpen = false">
                    <X class="size-5" />
                </Button>
            </div>

            <!-- Body Drawer -->
            <div class="flex-1 overflow-y-auto p-6 flex flex-col gap-6">
                <!-- BƯỚC 2: DANH SÁCH MÓN ĐÃ CHỌN (CHỈ HIỂN THỊ KHI Ở BƯỚC CONFIRM) -->
                <div v-if="drawerStep === 'confirm'" class="flex flex-col gap-3">
                    <h4 class="text-xs font-black uppercase text-slate-400 tracking-wider">Danh sách món đã chọn</h4>
                    <div v-if="cartItems.length === 0" class="text-center py-10 text-xs text-muted-foreground border-2 border-dashed rounded-2xl">
                        Chưa chọn món nào. Hãy click "Thêm món" để chọn món.
                    </div>

                    <div v-for="item in cartItems" :key="item.id ? 'exist-' + item.id : 'new-' + item.product_id" class="flex justify-between items-center p-3 border rounded-2xl bg-slate-50/50 dark:bg-slate-900/20">
                        <div class="text-left max-w-[60%]">
                            <span class="font-bold text-xs text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                <Lock v-if="item.id" class="size-3 text-amber-500" />
                                {{ item.product_name }}
                            </span>
                            <p class="text-[10px] text-muted-foreground font-mono mt-0.5">{{ number_format(item.price) }}đ</p>
                        </div>

                        <!-- Bộ điều khiển số lượng -->
                        <div class="flex items-center gap-2">
                            <Button
                                size="icon"
                                variant="outline"
                                class="h-7 w-7 rounded-lg"
                                :disabled="!!item.id"
                                @click="decreaseQty(item)"
                            >
                                <Minus class="size-3" />
                            </Button>
                            <span class="text-xs font-mono font-bold w-6 text-center">{{ item.quantity }}</span>
                            <Button
                                size="icon"
                                variant="outline"
                                class="h-7 w-7 rounded-lg"
                                @click="increaseQty(item)"
                            >
                                <Plus class="size-3" />
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="h-7 w-7 rounded-lg text-rose-500 hover:text-rose-600"
                                :disabled="!!item.id"
                                @click="removeItem(item)"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- BƯỚC 1: MENU MÓN ĂN ĐỂ CLICK THÊM VỚI SỐ LƯỢNG (CHỈ HIỂN THỊ KHI Ở BƯỚC SELECT) -->
                <div v-if="drawerStep === 'select'" class="flex flex-col gap-4">
                    <h4 class="text-xs font-black uppercase text-slate-400 tracking-wider">Thực đơn nhà hàng</h4>

                    <div class="grid grid-cols-2 gap-3">
                        <div
                            v-for="prod in props.products"
                            :key="prod.id"
                            class="p-3 border rounded-2xl bg-white dark:bg-slate-900 shadow-sm transition-all text-left flex flex-col justify-between relative"
                            :class="getCartItemQty(prod.id) > 0 ? 'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50/10' : 'hover:border-indigo-500/50 hover:bg-slate-50/50'"
                        >
                            <!-- Click card to select/add -->
                            <div class="flex flex-col gap-1 cursor-pointer w-full" @click="handleProductCardClick(prod)">
                                <span class="font-bold text-xs truncate">{{ prod.name }}</span>
                                <span class="text-[10px] font-mono text-indigo-600 font-bold mt-2">{{ number_format(prod.price) }}đ</span>
                            </div>

                            <!-- Quantity Controls -->
                            <div v-if="getCartItemQty(prod.id) > 0" class="flex items-center justify-between mt-3 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded-xl w-full">
                                <button
                                    type="button"
                                    class="text-slate-500 hover:text-slate-800 dark:hover:text-white p-0.5"
                                    @click.stop="decreaseProductQty(prod.id)"
                                >
                                    <Minus class="size-3" />
                                </button>
                                <span class="text-xs font-mono font-black text-slate-800 dark:text-slate-100">
                                    {{ getCartItemQty(prod.id) }}
                                </span>
                                <button
                                    type="button"
                                    class="text-slate-500 hover:text-slate-800 dark:hover:text-white p-0.5"
                                    @click.stop="increaseProductQty(prod.id)"
                                >
                                    <Plus class="size-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- THÔNG TIN VOUCHER / NOTE -->
                <div class="flex flex-col gap-3" v-if="drawerStep === 'confirm' && activeTable.active_order">
                    <Separator />
                    <h4 class="text-xs font-black uppercase text-slate-400 tracking-wider">Áp dụng mã Voucher</h4>
                    <div class="flex gap-2">
                        <Input v-model="voucherCode" placeholder="Nhập mã khuyến mãi..." class="h-9 rounded-xl text-xs" />
                        <Button size="sm" class="rounded-xl" @click="applyVoucher">Áp dụng</Button>
                    </div>
                </div>
            </div>

            <!-- Footer Drawer -->
            <div class="p-6 border-t bg-slate-50/50 dark:bg-slate-900/50 flex flex-col gap-4">
                <div class="flex justify-between items-center">
                    <span class="text-xs text-muted-foreground font-bold">Tổng số tiền:</span>
                    <span class="text-xl font-mono font-black text-indigo-600" v-if="activeTable.active_order && isNotified">
                        {{ number_format(activeTable.active_order.total_amount) }}đ
                    </span>
                    <span class="text-xl font-mono font-black text-indigo-600" v-else>
                        {{ number_format(totalCartAmount) }}đ
                    </span>
                </div>

                <!-- Footer buttons for Select step -->
                <div class="flex gap-2" v-if="drawerStep === 'select'">
                    <Button variant="outline" class="flex-1 rounded-xl text-xs" @click="isCartOpen = false">
                        Đóng
                    </Button>
                    <Button
                        class="flex-1 rounded-xl text-xs bg-indigo-600 hover:bg-indigo-700"
                        :disabled="cartItems.length === 0"
                        @click="() => { drawerStep = 'confirm'; console.log('✅ Changed to confirm step'); }"
                    >
                        Xác nhận đặt món
                    </Button>
                </div>

                <!-- Footer buttons for Confirm step -->
                <div class="flex gap-2" v-if="drawerStep === 'confirm'">
                    <Button variant="outline" class="flex-1 rounded-xl text-xs border-indigo-200 text-indigo-600 hover:bg-indigo-50" @click="() => { drawerStep = 'select'; console.log('✅ Changed to select step'); }">
                        Thêm món
                    </Button>
                    <Button
                        v-if="!isNotified"
                        class="flex-1 rounded-xl text-xs bg-indigo-600 hover:bg-indigo-700"
                        :disabled="isSubmitting"
                        @click="() => { console.log('🎯 Thông báo clicked'); submitOrder(); }"
                    >
                        {{ isSubmitting ? 'Đang gửi...' : 'Thông báo' }}
                    </Button>
                    <Button
                        v-else
                        class="flex-1 rounded-xl text-xs bg-emerald-600 hover:bg-emerald-700"
                        @click="openPayment"
                    >
                        Thanh toán
                    </Button>
                </div>

                <!-- NHÓM PHÍM BỔ SUNG KHI BÀN CÓ KHÁCH & ĐÃ THÔNG BÁO -->
                <div class="flex gap-2" v-if="drawerStep === 'confirm' && activeTable.active_order && isNotified">
                    <!-- Báo bếp khóa đơn -->
                    <Button variant="outline" class="flex-1 rounded-xl text-xs border-amber-200 text-amber-600 hover:bg-amber-50" v-if="activeTable.active_order.status === 'pending'" @click="sendToKitchen">
                        Khóa đơn & Báo Bếp
                    </Button>
                    <!-- Tách đơn -->
                    <Button variant="outline" class="flex-1 rounded-xl text-xs border-rose-200 text-rose-600 hover:bg-rose-50" @click="openSplitOrder">
                        Tách đơn
                    </Button>
                </div>
            </div>
        </div>

        <!-- ── DIALOG THANH TOÁN (PAYMENT DIALOG) ───────────────────────── -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl border shadow-2xl max-w-md w-full overflow-hidden p-6 flex flex-col gap-6 animate-fade-in">
                <div class="flex justify-between items-center">
                    <h3 class="font-black text-base text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <DollarSign class="size-5 text-emerald-600" />
                        Xác nhận Thanh toán hóa đơn
                    </h3>
                    <Button variant="ghost" size="icon" class="rounded-xl" @click="showPaymentModal = false">
                        <X class="size-5" />
                    </Button>
                </div>

                <!-- Nội dung thanh toán -->
                <div class="flex flex-col gap-4 text-left">
                    <div class="bg-slate-50 dark:bg-slate-950 p-4 rounded-2xl border">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-slate-500">Mã hóa đơn:</span>
                            <span class="font-bold">{{ activeTable?.active_order?.order_number }}</span>
                        </div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-slate-500">Bàn:</span>
                            <span class="font-bold">Bàn {{ activeTable?.name }}</span>
                        </div>
                        <Separator class="my-2" />
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-black">Số tiền cần thanh toán:</span>
                            <span class="text-lg font-mono font-black text-indigo-600">{{ number_format(activeTable?.active_order?.total_amount ?? 0) }}đ</span>
                        </div>
                    </div>

                    <!-- Phương thức thanh toán -->
                    <div class="flex flex-col gap-2">
                        <span class="text-xs font-bold text-slate-500">Phương thức thanh toán:</span>
                        <div class="grid grid-cols-2 gap-2">
                            <Button
                                v-for="m in [
                                    { id: 'cash', label: 'Tiền mặt' },
                                    { id: 'bank_transfer', label: 'Chuyển khoản' },
                                    { id: 'card', label: 'Thẻ ATM/Visa' },
                                    { id: 'ewallet', label: 'Ví điện tử' }
                                ]"
                                :key="m.id"
                                variant="outline"
                                class="rounded-xl text-xs h-10"
                                :class="paymentMethod === m.id ? 'border-indigo-600 bg-indigo-50 text-indigo-600' : ''"
                                @click="paymentMethod = m.id"
                            >
                                {{ m.label }}
                            </Button>
                        </div>
                    </div>

                    <!-- Nhập tiền khách đưa nếu là Tiền mặt -->
                    <div class="flex flex-col gap-2" v-if="paymentMethod === 'cash'">
                        <span class="text-xs font-bold text-slate-500">Số tiền khách đưa:</span>
                        <Input type="number" v-model="cashReceived" class="rounded-xl text-xs h-10 font-mono font-bold" />

                        <div class="flex justify-between text-xs text-emerald-600 font-bold mt-1">
                            <span>Tiền thối lại:</span>
                            <span class="font-mono">{{ number_format(changeAmount) }}đ</span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" class="flex-1 rounded-xl text-xs" @click="showPaymentModal = false">
                        Hủy
                    </Button>
                    <Button class="flex-1 rounded-xl text-xs bg-emerald-600 hover:bg-emerald-700" :disabled="isPaying" @click="processPayment">
                        {{ isPaying ? 'Đang xử lý...' : 'Hoàn tất & In hóa đơn' }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- ── DIALOG TÁCH ĐƠN (SPLIT ORDER DIALOG) ───────────────────────── -->
        <div v-if="showSplitModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl border shadow-2xl max-w-md w-full overflow-hidden p-6 flex flex-col gap-6 animate-fade-in">
                <div class="flex justify-between items-center">
                    <h3 class="font-black text-base text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <AlertTriangle class="size-5 text-rose-500" />
                        Tách đơn sang bàn trống mới
                    </h3>
                    <Button variant="ghost" size="icon" class="rounded-xl" @click="showSplitModal = false">
                        <X class="size-5" />
                    </Button>
                </div>

                <div class="flex flex-col gap-4 text-left">
                    <span class="text-xs text-muted-foreground">Chọn bàn trống để chuyển bớt món ăn sang:</span>

                    <!-- Bàn trống đích -->
                    <select v-model="splitTableId" class="w-full border rounded-xl p-2.5 text-xs bg-card">
                        <option :value="null">-- Chọn bàn trống --</option>
                        <option v-for="t in props.tablesData.filter(t => t.status === 'available')" :key="t.id" :value="t.id">
                            Bàn {{ t.name }} ({{ t.area }})
                        </option>
                    </select>

                    <Separator />

                    <!-- Chọn món tách -->
                    <span class="text-xs font-bold text-slate-500">Điều chỉnh số lượng món tách:</span>
                    <div class="flex flex-col gap-2.5 max-h-48 overflow-y-auto">
                        <div v-for="item in splitItems" :key="item.id" class="flex justify-between items-center p-2 border rounded-xl bg-slate-50/50">
                            <span class="text-xs font-bold truncate max-w-[50%]">{{ item.product_name }}</span>

                            <div class="flex items-center gap-1.5">
                                <Button size="icon" variant="outline" class="h-6 w-6 rounded-lg" @click="item.quantity > 0 ? item.quantity-- : null">
                                    <Minus class="size-2.5" />
                                </Button>
                                <span class="text-xs font-mono font-bold w-5 text-center">{{ item.quantity }}</span>
                                <Button size="icon" variant="outline" class="h-6 w-6 rounded-lg" @click="item.quantity++">
                                    <Plus class="size-2.5" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" class="flex-1 rounded-xl text-xs" @click="showSplitModal = false">
                        Hủy
                    </Button>
                    <Button class="flex-1 rounded-xl text-xs bg-rose-600 hover:bg-rose-700" :disabled="!splitTableId || isSubmitting" @click="processSplit">
                        {{ isSubmitting ? 'Đang xử lý...' : 'Xác nhận Tách đơn' }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- ── MODAL TRỢ LÝ AI GỢI Ý (AI UPSELL SUGGESTION MODAL) ──────────── -->
        <div v-if="showAiSuggestionModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 animate-fade-in">
            <div class="bg-gradient-to-br from-indigo-900 to-indigo-950 text-white rounded-3xl border border-indigo-500/30 shadow-2xl max-w-md w-full overflow-hidden p-6 flex flex-col gap-6">
                <div class="flex justify-between items-center border-b border-indigo-500/20 pb-3">
                    <h3 class="font-black text-sm text-indigo-300 flex items-center gap-2">
                        <Sparkles class="size-5 text-indigo-400 animate-pulse" />
                        Trợ lý Kích Cầu AI gợi ý (Upselling)
                    </h3>
                    <Button variant="ghost" size="icon" class="rounded-xl text-white/50 hover:text-white" @click="showAiSuggestionModal = false">
                        <X class="size-5" />
                    </Button>
                </div>

                <div class="text-left py-2">
                    <p class="text-xs text-indigo-200/80 uppercase tracking-wider font-semibold">Lời khuyên của trí tuệ nhân tạo BepsoViet:</p>
                    <div class="mt-3 p-4.5 bg-white/5 border border-indigo-500/20 rounded-2xl text-sm leading-relaxed font-medium">
                        "{{ aiSuggestion }}"
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Button class="rounded-xl text-xs bg-indigo-600 hover:bg-indigo-700" @click="showAiSuggestionModal = false">
                        Tôi hiểu rồi (Mời khách)
                    </Button>
                </div>
            </div>
        </div>

        <!-- ── MODAL HÀNH CHÍNH TỰ PHỤC VỤ (SELF-SERVICE MODAL) ────────────── -->
        <div v-if="showSelfServiceModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl border shadow-2xl max-w-lg w-full overflow-hidden p-6 flex flex-col gap-6 animate-fade-in">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="font-black text-base text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <User class="size-5 text-indigo-600" />
                        Hành chính & Tự phục vụ nhân sự
                    </h3>
                    <Button variant="ghost" size="icon" class="rounded-xl" @click="showSelfServiceModal = false">
                        <X class="size-5" />
                    </Button>
                </div>

                <!-- Tab hành chính -->
                <div class="flex gap-2 border-b">
                    <button
                        v-for="s in [
                            { id: 'schedule', label: 'Đăng ký ca làm' },
                            { id: 'leave', label: 'Xin nghỉ phép' }
                        ]"
                        :key="s.id"
                        @click="selfServiceTab = s.id"
                        class="px-4 py-2 font-bold text-xs border-b-2 transition-all"
                        :class="selfServiceTab === s.id ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500'"
                    >
                        {{ s.label }}
                    </button>
                </div>

                <!-- Tab 1: Đăng ký lịch làm -->
                <div v-if="selfServiceTab === 'schedule'" class="flex flex-col gap-4 text-left">
                    <div class="flex flex-col gap-2">
                        <span class="text-xs font-bold text-slate-500">Chọn thứ trong tuần:</span>
                        <select v-model="regDay" class="border rounded-xl p-2.5 text-xs bg-card">
                            <option value="Monday">Thứ hai</option>
                            <option value="Tuesday">Thứ ba</option>
                            <option value="Wednesday">Thứ tư</option>
                            <option value="Thursday">Thứ năm</option>
                            <option value="Friday">Thứ sáu</option>
                            <option value="Saturday">Thứ bảy</option>
                            <option value="Sunday">Chủ nhật</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <span class="text-xs font-bold text-slate-500">Chọn ca trực hoạt động:</span>
                        <select v-model="regShiftName" class="border rounded-xl p-2.5 text-xs bg-card">
                            <option value="">-- Chọn ca làm việc --</option>
                            <option v-for="s in props.activeShifts" :key="s.id" :value="s.name">
                                {{ s.name }}
                            </option>
                        </select>
                    </div>

                    <Button class="rounded-xl text-xs bg-indigo-600 hover:bg-indigo-700" :disabled="!regShiftName" @click="handleRegisterSchedule">
                        Gửi Đăng ký ca
                    </Button>
                </div>

                <!-- Tab 2: Xin nghỉ phép -->
                <div v-if="selfServiceTab === 'leave'" class="flex flex-col gap-4 text-left">
                    <div class="flex flex-col gap-2">
                        <span class="text-xs font-bold text-slate-500">Loại đơn:</span>
                        <select v-model="leaveType" class="border rounded-xl p-2.5 text-xs bg-card">
                            <option value="annual">Nghỉ phép năm</option>
                            <option value="sick">Nghỉ ốm đau</option>
                            <option value="unpaid">Nghỉ không lương</option>
                            <option value="emergency">Xin nghỉ khẩn cấp</option>
                            <option value="resignation">Đơn thôi việc / xin nghỉ việc</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-2">
                            <span class="text-xs font-bold text-slate-500">Ngày bắt đầu:</span>
                            <Input type="date" v-model="leaveStart" class="rounded-xl text-xs" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <span class="text-xs font-bold text-slate-500">Ngày kết thúc:</span>
                            <Input type="date" v-model="leaveEnd" class="rounded-xl text-xs" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <span class="text-xs font-bold text-slate-500">Lý do:</span>
                        <textarea v-model="leaveReason" placeholder="Vui lòng ghi rõ lý do chi tiết..." class="border rounded-xl p-2.5 text-xs min-h-20 bg-card"></textarea>
                    </div>

                    <Button class="rounded-xl text-xs bg-emerald-600 hover:bg-emerald-700" :disabled="!leaveStart || !leaveEnd || !leaveReason" @click="handleLeaveRequest">
                        Nộp Đơn Lên Cấp Trên
                    </Button>
                </div>


            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes slide-in {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}
@keyframes fade-in {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.animate-slide-in {
    animation: slide-in 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.animate-fade-in {
    animation: fade-in 0.2s ease-out forwards;
}
@keyframes spin-slow {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.animate-spin-slow {
    animation: spin-slow 10s linear infinite;
}
</style>
