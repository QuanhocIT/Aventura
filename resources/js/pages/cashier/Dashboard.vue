<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Utensils,
    Users,
    Clock,
    Search,
    Coffee,
    Sparkles,
    Calendar,
    User,
    ShoppingCart,
    Lock,
    Plus,
    Minus,
    Trash2,
    DollarSign,
    RefreshCw,
    X,
    FileText,
    AlertTriangle,
    CheckCircle2 as CheckIcon,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
    status: 'available' | 'occupied' | 'reserved' | 'cleaning' | 'inactive';
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
    paused_until?: string | null;
    out_of_stock_until?: string | null;
    is_paused?: boolean;
    is_out_of_stock?: boolean;
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
        total_orders?: number;
        channel_breakdown?: Record<string, { count: number; revenue: number }>;
    };
    qrOrders: any[];
    externalOrders: any[];
    completedHistory: any[];
    weeklySchedules: any[];
    activeShifts: any[];
    pendingLeaves: any[];
    colleagues: Array<{ id: number; full_name: string; job_title: string | null }>;
    employee: {
        id: number;
        full_name: string;
    } | null;
}>();

const page = usePage();

const can = (permission: string) => {
    const authUser = page.props.auth?.user as any;
    const userPermissions = authUser?.permissions ?? [];

    return userPermissions.includes(permission);
};

const restaurantId = computed(() => (page.props.auth?.user as any)?.restaurant_id as number | undefined);

// Toast Notifications
type ToastType = 'success' | 'error';
const toasts = ref<Array<{ id: number; message: string; type: ToastType }>>([]);
let _toastId = 0;
const toast = (message: string, type: ToastType = 'success') => {
    const id = ++_toastId;
    toasts.value.push({ id, message, type });
    setTimeout(() => {
 toasts.value = toasts.value.filter(t => t.id !== id); 
}, 3500);
};

// State Management
const activeTab = ref<'tables' | 'qr' | 'history' | 'schedules'>('tables');
const searchQuery = ref('');
const selectedArea = ref('all');

// Typed tab config (fixes TS2322 on inline array)
const mainTabs = [
    { id: 'tables' as const, label: 'Sơ đồ bàn phục vụ', icon: Utensils },
    { id: 'qr' as const, label: 'Đơn Ngoài & QR', icon: Sparkles },
    { id: 'history' as const, label: 'Lịch sử & Bếp', icon: Clock },
];
const paymentMethods = [
    { id: 'cash' as const, label: 'Tiền mặt' },
    { id: 'bank_transfer' as const, label: 'Chuyển khoản' },
    { id: 'card' as const, label: 'Thẻ ATM/Visa' },
    { id: 'ewallet' as const, label: 'Ví điện tử' },
    { id: 'debt' as const, label: 'Ghi nợ (VIP/B2B)' },
];
const selfServiceTabs = [
    { id: 'schedule' as const, label: 'Đăng ký lịch' },
    { id: 'leave' as const, label: 'Xin nghỉ phép' },
    { id: 'complaint' as const, label: 'Khiếu nại ẩn danh' },
];

// Cart State
const isCartOpen = ref(false);
const activeTable = ref<TableItem | null>(null);
const cartItems = ref<OrderItem[]>([]);
const cartNote = ref('');
const voucherCode = ref('');
const cartBounce = ref(false);
const selectedCategoryId = ref<number | null>(null);
const drawerStep = ref<'select' | 'confirm'>('select');
const isNotified = ref(false);
const isSubmitting = ref(false);
const isPaying = ref(false);

// AI Suggestions State
const showAiSuggestionModal = ref(false);
const aiSuggestion = ref('');
const aiUpsellItem = ref<string>('');
const confirmingOrderId = ref<number | null>(null);

// Split Order State
const showSplitModal = ref(false);
const splitItems = ref<OrderItem[]>([]);
const splitTableId = ref<number | null>(null);

const splitProjection = computed(() => {
    const order = activeTable.value?.active_order;

    if (!order) {
return null;
}

    const orderSub   = order.subtotal as number;
    const discount   = (order.discount_amount as number) ?? 0;
    const splitSub   = splitItems.value.filter(i => i.quantity > 0).reduce((s, i) => s + i.price * i.quantity, 0);
    const origSub    = orderSub - splitSub;
    const splitDisc  = discount > 0 && orderSub > 0 ? Math.round(discount * (splitSub / orderSub) * 100) / 100 : 0;
    const origDisc   = discount - splitDisc;

    return {
        splitSubtotal:  splitSub,
        splitDiscount:  splitDisc,
        splitTotal:     Math.max(0, splitSub - splitDisc),
        origSubtotal:   origSub,
        origDiscount:   origDisc,
        origTotal:      Math.max(0, origSub - origDisc),
        hasItems:       splitItems.value.some(i => i.quantity > 0),
    };
});

// Payment State
const showPaymentModal = ref(false);
const paymentMethod = ref<'cash' | 'bank_transfer' | 'card' | 'ewallet' | 'debt'>('cash');
const cashReceived = ref<number | undefined>(undefined);
const changeAmount = computed(() => {
    if (!activeTable.value?.active_order || paymentMethod.value !== 'cash' || !cashReceived.value) {
return 0;
}

    const total = activeTable.value.active_order.total_amount;

    return Math.max(0, cashReceived.value - total);
});

const searchCustomerPhone = ref('');
const foundCustomer = ref<any>(null);
const isSearchingCustomer = ref(false);

const searchCustomer = async () => {
    if (!searchCustomerPhone.value) {
return;
}

    isSearchingCustomer.value = true;
    foundCustomer.value = null;

    try {
        const response = await axios.get(`/api/customers/search?phone=${searchCustomerPhone.value}`);

        if (response.data.success) {
            foundCustomer.value = response.data.customer;
            toast('Đã tìm thấy khách hàng ' + response.data.customer.full_name);
        } else {
            toast(response.data.message || 'Không tìm thấy khách hàng.', 'error');
        }
    } catch (err: any) {
        toast('Lỗi tra cứu khách hàng.', 'error');
    } finally {
        isSearchingCustomer.value = false;
    }
};

const clearCustomerSelection = () => {
    foundCustomer.value = null;
    searchCustomerPhone.value = '';
};

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

    if (restaurantId.value) {
        window.Echo.channel(`restaurant.${restaurantId.value}`)
            .listen('.temporary_order.created', (data: any) => {
                toast(`Bàn ${data.table_name ?? '?'}: Khách vừa gọi đơn QR (${data.items?.length ?? 1} món)!`);
                router.reload({ only: ['qrOrders', 'tablesData'] });
            })
            .listen('.staff.called', (data: any) => {
                toast(`Bàn ${data.table_name ?? '?'}: ${data.message ?? 'Khách cần hỗ trợ!'}`, 'error');
            })
            .listen('.payment.requested', (data: any) => {
                toast(`Bàn ${data.table_name ?? '?'}: Khách yêu cầu thanh toán!`);
            })
            .listen('.temporary_order.escalated', (data: any) => {
                toast(`Bàn ${data.table_name ?? '?'}: Đơn QR chờ quá lâu — cần xử lý ngay!`, 'error');
                router.reload({ only: ['qrOrders'] });
            })
            .listen('.product.stock_updated', () => {
                toast('Trạng thái món ăn trên thực đơn vừa thay đổi!');
                router.reload({ only: ['products'] });
            })
            .listen('.order.paid', (data: any) => {
                toast(`Đơn hàng #${data.order_number} đã được thanh toán qua VietQR thành công!`);
                router.reload({ only: ['tablesData', 'qrOrders'] });
            });

        window.Echo.channel(`kitchen.${restaurantId.value}`)
            .listen('.kitchen.updated', () => {
                router.reload({ only: ['tablesData'] });
            });
    }

    window.addEventListener('shift-expired-save', handleShiftExpiredSave);

    // Restore draft cart if present
    const savedCart = localStorage.getItem('aventura_expired_cart');
    if (savedCart) {
        try {
            const parsed = JSON.parse(savedCart);
            if (parsed.activeTableId) {
                const matchTable = props.tablesData.find(t => t.id === parsed.activeTableId);
                if (matchTable) {
                    activeTable.value = matchTable;
                    isCartOpen.value = true;
                }
            }
            if (parsed.cartItems) {
                cartItems.value = parsed.cartItems;
            }
            if (parsed.cartNote) {
                cartNote.value = parsed.cartNote;
            }
            if (parsed.voucherCode) {
                voucherCode.value = parsed.voucherCode;
            }
            setTimeout(() => {
                toast('Đã khôi phục giỏ hàng nháp từ phiên làm việc trước!');
            }, 500);
        } catch(e) {}
        localStorage.removeItem('aventura_expired_cart');
    }
});

const handleShiftExpiredSave = () => {
    if (cartItems.value.length > 0) {
        localStorage.setItem('aventura_expired_cart', JSON.stringify({
            activeTableId: activeTable.value?.id,
            cartItems: cartItems.value,
            cartNote: cartNote.value,
            voucherCode: voucherCode.value
        }));
    }
};

onUnmounted(() => {
    window.removeEventListener('shift-expired-save', handleShiftExpiredSave);

    if (timerId) {
        clearInterval(timerId);
    }

    if (restaurantId.value) {
        window.Echo.leaveChannel(`restaurant.${restaurantId.value}`);
        window.Echo.leaveChannel(`kitchen.${restaurantId.value}`);
    }
});

// Filters
const tableStats = computed(() => {
    const all = props.tablesData ?? [];

    return [
        { label: 'bàn trống', count: all.filter(t => t.status === 'available').length, colorClass: 'text-emerald-700 border-emerald-200', dotClass: 'bg-emerald-500' },
        { label: 'có khách', count: all.filter(t => t.status === 'occupied').length, colorClass: 'text-indigo-700 border-indigo-200', dotClass: 'bg-indigo-500' },
        { label: 'đã đặt', count: all.filter(t => t.status === 'reserved').length, colorClass: 'text-violet-700 border-violet-200', dotClass: 'bg-violet-500' },
        { label: 'đang dọn', count: all.filter(t => t.status === 'cleaning').length, colorClass: 'text-amber-700 border-amber-200', dotClass: 'bg-amber-500' },
    ].filter(s => s.count > 0);
});

const filteredMenuProducts = computed(() => {
    if (!selectedCategoryId.value) {
        return props.products;
    }

    return props.products.filter(p => p.category_id === selectedCategoryId.value);
});

const uniqueAreas = computed(() => {
    const areas = new Set<string>();
    props.tablesData?.forEach(t => {
 if (t.area) {
areas.add(t.area);
} 
});

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
    selectedCategoryId.value = null;

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
    setTimeout(() => {
 cartBounce.value = false; 
}, 300);
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
    triggerCartBounce();
};

const decreaseQty = (item: OrderItem) => {
    if (item.id) {
        toast('Không thể giảm số lượng món đã gửi bếp.', 'error');

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
    if (item.id) {
        toast('Không thể xóa món đã gửi bếp.', 'error');

        return;
    }

    isNotified.value = false;
    cartItems.value = cartItems.value.filter(i => i !== item);
};

const totalCartAmount = computed(() => {
    return cartItems.value.reduce((sum, item) => sum + (item.price * item.quantity), 0);
});

const totalCartQty = computed(() => cartItems.value.reduce((s, i) => s + i.quantity, 0));

// Send Kitchen / Notify
const submitOrder = () => {
    if (isSubmitting.value) {
return;
}

    isSubmitting.value = true;

    if (!activeTable.value) {
        toast('Vui lòng chọn một bàn!', 'error');
        isSubmitting.value = false;

        return;
    }

    if (cartItems.value.length === 0) {
        toast('Vui lòng thêm ít nhất một món ăn!', 'error');
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

    if (activeTable.value.active_order) {
        router.patch(`/orders/${activeTable.value.active_order.id}`, requestData, {
            preserveState: true,
            onSuccess: () => {
                isNotified.value = true;
                setTimeout(() => {
                    const updated = props.tablesData.find(t => t.id === activeTable.value!.id);

                    if (updated) {
                        activeTable.value = updated;
                        cartItems.value = updated.active_order?.items.map(item => ({ ...item })) ?? [];
                    }
                }, 200);
                toast('Đã gửi bổ sung món xuống nhà bếp thành công!');
            },
            onError: (errors: any) => {
                const errorMessage = (Object.values(errors).flat() as string[]).join(', ') || 'Có lỗi xảy ra khi cập nhật đơn hàng!';
                toast('Lỗi cập nhật đơn: ' + errorMessage, 'error');
            },
            onFinish: () => {
                isSubmitting.value = false;
            }
        });
    } else {
        router.post('/orders', { table_id: activeTable.value.id, ...requestData }, {
            preserveState: true,
            onSuccess: () => {
                isNotified.value = true;
                setTimeout(() => {
                    const updated = props.tablesData.find(t => t.id === activeTable.value!.id);

                    if (updated) {
                        activeTable.value = updated;
                        cartItems.value = updated.active_order?.items.map(item => ({ ...item })) ?? [];
                    }
                }, 200);
                toast('Đã tạo đơn mới thành công!');
            },
            onError: (errors: any) => {
                const errorMessage = (Object.values(errors).flat() as string[]).join(', ') || 'Có lỗi xảy ra khi tạo đơn hàng!';
                toast('Lỗi tạo đơn: ' + errorMessage, 'error');
            },
            onFinish: () => {
                isSubmitting.value = false;
            }
        });
    }
};

// Send Order to Kitchen (Locks status from pending to confirmed)
const sendToKitchen = () => {
    if (!activeTable.value?.active_order) {
return;
}

    router.patch(`/orders/${activeTable.value.active_order.id}/status`, {
        status: 'confirmed'
    }, {
        onSuccess: () => {
            isCartOpen.value = false;
            toast('Đơn hàng đã đẩy xuống bếp và khóa thành công!');
        }
    });
};

// External (delivery/takeaway) order status management
const updatingExternalId = ref<number | null>(null);
const updateExternalOrderStatus = (orderId: number, status: string) => {
    updatingExternalId.value = orderId;
    router.patch(`/orders/${orderId}/status`, { status }, {
        preserveState: true,
        onSuccess: () => {
            const label = status === 'confirmed' ? 'Đã nhận đơn!' : status === 'preparing' ? 'Đang chuẩn bị...' : 'Đơn hoàn tất!';
            toast(label);
            router.reload({ only: ['externalOrders', 'tablesData', 'completedHistory', 'shiftInfo'] });
        },
        onError: () => toast('Không thể cập nhật trạng thái.', 'error'),
        onFinish: () => {
 updatingExternalId.value = null; 
},
    });
};

// Confirm QR Code Order
const confirmQrOrder = (orderId: number) => {
    confirmingOrderId.value = orderId;
    axios.post(`/orders/${orderId}/confirm-qr`).then(res => {
        if (res.data.success) {
            aiSuggestion.value = res.data.upsell?.suggestion || 'Hãy gợi ý khách chọn thêm đồ uống giải nhiệt!';
            aiUpsellItem.value = res.data.upsell?.recommended_item || '';
            showAiSuggestionModal.value = true;
            router.reload({ only: ['qrOrders', 'tablesData'] });
        }
    }).catch(err => {
        toast(err.response?.data?.message || 'Có lỗi xảy ra khi xác thực đơn QR.', 'error');
    }).finally(() => {
        confirmingOrderId.value = null;
    });
};

// Apply Voucher Code
const applyVoucher = () => {
    if (!activeTable.value?.active_order || !voucherCode.value) {
return;
}

    axios.post('/api/promotions/apply', {
        order_id: activeTable.value.active_order.id,
        code: voucherCode.value
    }).then(res => {
        toast(res.data.message);
        router.reload({ only: ['tablesData'] });

        // Refresh local active order to show updated total
        if (activeTable.value) {
            setTimeout(() => {
                const updated = props.tablesData.find(t => t.id === activeTable.value!.id);

                if (updated) {
openTableOrder(updated);
}
            }, 300);
        }
    }).catch(err => {
        toast(err.response?.data?.message || 'Mã giảm giá không hợp lệ.', 'error');
    });
};

// Payment Dialog
const openPayment = () => {
    paymentMethod.value = 'cash';
    cashReceived.value = activeTable.value?.active_order?.total_amount ?? 0;
    foundCustomer.value = null;
    searchCustomerPhone.value = '';
    showPaymentModal.value = true;
};

const processPayment = () => {
    if (!activeTable.value?.active_order || isPaying.value) {
return;
}

    isPaying.value = true;
    axios.post(`/orders/${activeTable.value.active_order.id}/pay`, {
        payment_method: paymentMethod.value,
        cash_received: cashReceived.value,
        change_amount: changeAmount.value,
        customer_id: foundCustomer.value ? foundCustomer.value.id : null,
    }).then(() => {
        showPaymentModal.value = false;
        isCartOpen.value = false;
        toast('Đã thanh toán hóa đơn thành công. Bàn đã chuyển sang trạng thái trống.');
        router.reload({ only: ['tablesData', 'shiftInfo', 'completedHistory'] });
    }).catch(err => {
        toast(err.response?.data?.message || 'Lỗi xử lý thanh toán.', 'error');
    }).finally(() => {
        isPaying.value = false;
    });
};

// Split Order dialog
const openSplitOrder = () => {
    if (!activeTable.value?.active_order) {
return;
}

    splitItems.value = activeTable.value.active_order.items.map(i => ({ ...i, quantity: 1 }));
    splitTableId.value = null;
    showSplitModal.value = true;
};

const processSplit = () => {
    if (!activeTable.value?.active_order || !splitTableId.value || isSubmitting.value) {
return;
}

    // Build items to split
    const itemsToSplit = splitItems.value
        .filter(si => si.quantity > 0)
        .map(si => ({
            order_item_id: si.id,
            quantity: si.quantity
        }));

    if (itemsToSplit.length === 0) {
        toast('Vui lòng chọn ít nhất 1 món để tách!', 'error');

        return;
    }

    isSubmitting.value = true;

    router.post(`/orders/${activeTable.value.active_order.id}/split`, {
        table_id: splitTableId.value,
        items: itemsToSplit
    }, {
        onSuccess: () => {
            showSplitModal.value = false;
            isCartOpen.value = false;
            toast('Đã tách đơn sang bàn trống thành công!');
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
    if (!props.employee) {
return;
}

    router.post('/employees/schedules', {
        day: regDay.value,
        employee_name: props.employee.full_name,
        shift_name: regShiftName.value
    }, {
        onSuccess: () => {
            toast('Đăng ký lịch làm việc thành công!');
            regShiftName.value = '';
            router.reload({ only: ['weeklySchedules'] });
        }
    });
};

const handleLeaveRequest = () => {
    if (!props.employee) {
return;
}

    router.post('/employees/leaves', {
        employee_id: props.employee.id,
        leave_type: leaveType.value,
        start_date: leaveStart.value,
        end_date: leaveEnd.value,
        reason: leaveReason.value
    }, {
        onSuccess: () => {
            toast('Nộp đơn xin nghỉ thành công! Chờ cấp trên phê duyệt.');
            leaveReason.value = '';
        }
    });
};

const handleComplaint = () => {
    if (!complaintTargetId.value || !complaintType.value || !complaintDescription.value) {
return;
}

    router.post('/violations', {
        employee_id: complaintTargetId.value,
        violation_type: complaintType.value,
        description: complaintDescription.value,
        is_anonymous: true,
        occurred_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
    }, {
        onSuccess: () => {
            toast('Gửi khiếu nại ẩn danh thành công! Thông tin của bạn được bảo mật tuyệt đối.');
            complaintTargetId.value = null;
            complaintType.value = '';
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
        case 'inactive':
        default:
            return {
                label: 'Ngừng HĐ',
                class: 'bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-800 dark:text-slate-500',
                dotClass: 'bg-slate-400',
                cardBorder: 'opacity-50 pointer-events-none'
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

            <div class="flex items-center gap-3">
                <Button variant="outline" class="rounded-2xl text-xs font-bold gap-2" @click="openSelfService('schedule')">
                    <User class="size-4 text-indigo-600" />
                    Hành chính & Tự phục vụ
                </Button>

                <div class="flex items-center gap-3 bg-slate-100 dark:bg-slate-800 px-4 py-2.5 rounded-2xl">
                    <Clock class="size-4 text-slate-500" />
                    <div class="text-left font-mono">
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ currentTime }}</span>
                        <span class="text-[10px] text-muted-foreground ml-2">{{ currentDate }}</span>
                    </div>
                </div>
            </div>
        </div>



        <!-- ── TAB ĐIỀU HƯỚNG CHÍNH ────────────────────────────────────── -->
        <div class="flex border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="t in mainTabs"
                :key="t.id"
                @click="activeTab = t.id"
                class="px-6 py-3.5 font-bold text-xs flex items-center gap-2 border-b-2 transition-all relative"
                :class="activeTab === t.id
                    ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 bg-white/40 dark:bg-slate-900/40 rounded-t-xl'
                    : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-100/50'"
            >
                <component :is="t.icon" class="size-4" />
                {{ t.label }}
                <span v-if="t.id === 'qr' && (props.qrOrders.length + props.externalOrders.length)" class="bg-rose-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full animate-bounce">
                    {{ props.qrOrders.length + props.externalOrders.length }}
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

            <!-- Thống kê nhanh trạng thái bàn -->
            <div v-if="tableStats.length" class="flex flex-wrap items-center gap-2">
                <div
                    v-for="stat in tableStats"
                    :key="stat.label"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border text-[10px] font-bold"
                    :class="stat.colorClass"
                >
                    <span class="w-2 h-2 rounded-full shrink-0" :class="stat.dotClass"></span>
                    {{ stat.count }} {{ stat.label }}
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

        <!-- ── TAB 2: ĐƠN NGOÀI & QR ────────────────────────────────────── -->
        <div v-if="activeTab === 'qr'" class="grid grid-cols-1 gap-6">

            <!-- Đơn giao hàng & mang về (bên thứ ba) -->
            <Card v-if="props.externalOrders.length > 0" class="rounded-3xl shadow-sm border-amber-200/60 dark:border-amber-900/40">
                <CardHeader>
                    <CardTitle class="text-sm font-black flex items-center gap-2">
                        <RefreshCw class="size-5 text-amber-600" />
                        Đơn Giao Hàng & Mang Về (Bên thứ ba)
                    </CardTitle>
                    <CardDescription class="text-xs">
                        Đơn từ ứng dụng đặt hàng hoặc nhận qua điện thoại — xác nhận và theo dõi tiến độ tại đây.
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-6 pt-0 flex flex-col gap-3">
                    <div
                        v-for="order in props.externalOrders"
                        :key="order.id"
                        class="p-4 border rounded-2xl flex flex-col gap-2.5"
                        :class="order.channel === 'delivery'
                            ? 'bg-amber-50/40 dark:bg-amber-950/10 border-amber-200/70 dark:border-amber-900/40'
                            : 'bg-emerald-50/40 dark:bg-emerald-950/10 border-emerald-200/70 dark:border-emerald-900/40'"
                    >
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <span
                                    class="text-[9px] font-extrabold px-2 py-0.5 rounded-full"
                                    :class="order.channel === 'delivery'
                                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'"
                                >
                                    {{ order.channel === 'delivery' ? 'Giao hàng' : 'Mang về' }}
                                </span>
                                <span class="font-bold text-xs text-slate-700 dark:text-slate-300 font-mono">{{ order.order_number }}</span>
                            </div>
                            <span class="text-[10px] text-muted-foreground">{{ order.created_at }}</span>
                        </div>

                        <div class="text-[11px] text-slate-500 flex flex-col gap-0.5">
                            <div v-for="(item, idx) in order.items" :key="idx">
                                - {{ item.product_name }} x{{ item.quantity }}
                            </div>
                        </div>

                        <Separator class="my-0.5" />

                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold font-mono">Tổng: {{ number_format(order.total_amount) }}đ</span>
                            <div class="flex gap-1.5">
                                <Button
                                    v-if="order.status === 'pending'"
                                    size="sm"
                                    class="rounded-xl h-8 text-xs bg-amber-500 hover:bg-amber-600 text-white"
                                    :disabled="updatingExternalId === order.id"
                                    @click="updateExternalOrderStatus(order.id, 'confirmed')"
                                >
                                    <RefreshCw v-if="updatingExternalId === order.id" class="size-3 mr-1 animate-spin" />
                                    {{ updatingExternalId === order.id ? '...' : 'Nhận đơn' }}
                                </Button>
                                <Button
                                    v-else-if="order.status === 'confirmed'"
                                    size="sm"
                                    class="rounded-xl h-8 text-xs bg-indigo-600 hover:bg-indigo-700"
                                    :disabled="updatingExternalId === order.id"
                                    @click="updateExternalOrderStatus(order.id, 'preparing')"
                                >
                                    <RefreshCw v-if="updatingExternalId === order.id" class="size-3 mr-1 animate-spin" />
                                    {{ updatingExternalId === order.id ? '...' : 'Đang làm' }}
                                </Button>
                                <Button
                                    v-else-if="order.status === 'preparing'"
                                    size="sm"
                                    class="rounded-xl h-8 text-xs bg-emerald-600 hover:bg-emerald-700"
                                    :disabled="updatingExternalId === order.id"
                                    @click="updateExternalOrderStatus(order.id, 'completed')"
                                >
                                    <RefreshCw v-if="updatingExternalId === order.id" class="size-3 mr-1 animate-spin" />
                                    {{ updatingExternalId === order.id ? '...' : 'Xong' }}
                                </Button>
                                <Badge variant="outline" class="text-[9px] font-bold h-8 px-2.5 capitalize"
                                    :class="order.status === 'pending' ? 'text-amber-600 border-amber-300' : order.status === 'confirmed' ? 'text-indigo-600 border-indigo-300' : 'text-emerald-600 border-emerald-300'">
                                    {{ order.status === 'pending' ? 'Chờ nhận' : order.status === 'confirmed' ? 'Đã nhận' : 'Đang làm' }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

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
                            <Button size="sm" class="rounded-xl h-8 text-xs" :disabled="confirmingOrderId === order.id" @click="confirmQrOrder(order.id)">
                                <RefreshCw v-if="confirmingOrderId === order.id" class="size-3 mr-1 animate-spin" />
                                {{ confirmingOrderId === order.id ? 'Đang xử lý...' : 'Xác nhận tại bàn' }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ── TAB 3: LỊCH SỬ & KITCHEN PROGRESS ────────────────────────── -->
        <div v-if="activeTab === 'history'" class="flex flex-col gap-6">

            <!-- Hiệu suất ca làm việc (chỉ hiện khi đang checkin) -->
            <div v-if="props.shiftInfo?.total_orders !== undefined" class="grid grid-cols-3 gap-3">
                <div class="bg-white dark:bg-slate-900 border rounded-2xl p-4 flex flex-col gap-1 text-center shadow-sm">
                    <span class="text-2xl font-black text-indigo-600">{{ props.shiftInfo.total_orders }}</span>
                    <span class="text-[10px] text-muted-foreground font-semibold">Đơn hoàn thành ca này</span>
                </div>
                <div class="bg-white dark:bg-slate-900 border rounded-2xl p-4 flex flex-col gap-1 text-center shadow-sm">
                    <span class="text-2xl font-black text-emerald-600">{{ number_format(props.shiftInfo.shift_revenue) }}đ</span>
                    <span class="text-[10px] text-muted-foreground font-semibold">Doanh thu ca này</span>
                </div>
                <div class="bg-white dark:bg-slate-900 border rounded-2xl p-4 flex flex-col gap-1 text-center shadow-sm">
                    <span class="text-2xl font-black text-violet-600">
                        {{ props.shiftInfo.total_orders > 0 ? number_format(Math.round(props.shiftInfo.shift_revenue / props.shiftInfo.total_orders)) : 0 }}đ
                    </span>
                    <span class="text-[10px] text-muted-foreground font-semibold">Trung bình / đơn</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                                <span class="font-black text-xs text-slate-800 dark:text-slate-200 flex items-center gap-1.5 flex-wrap">
                                    {{ h.order_number }}
                                    <span
                                        class="text-[9px] font-bold px-1.5 py-0.5 rounded-md"
                                        :class="h.channel === 'dine_in' ? 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400'
                                            : h.channel === 'qr' ? 'bg-violet-100 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400'
                                            : h.channel === 'delivery' ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'
                                            : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400'"
                                    >
                                        {{ h.channel === 'dine_in' ? 'Tại bàn' : h.channel === 'qr' ? 'QR' : h.channel === 'delivery' ? 'Giao hàng' : 'Mang về' }}
                                    </span>
                                </span>
                                <p class="text-[10px] text-muted-foreground mt-0.5">{{ h.table_name }} | {{ h.completed_at }}</p>
                            </div>
                            <span class="font-mono font-bold text-xs text-emerald-600">+{{ number_format(h.total_amount) }}đ</span>
                        </div>
                        <a href="/orders" class="text-[10px] text-indigo-600 font-bold hover:underline text-center block mt-1">
                            Xem toàn bộ lịch sử →
                        </a>
                    </CardContent>
                </Card>
            </div>
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
                        <span
                            v-if="drawerStep === 'select' && totalCartQty > 0"
                            :class="{ 'animate-bounce': cartBounce }"
                            class="inline-flex items-center gap-1 bg-indigo-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"
                        >
                            <ShoppingCart class="size-3" />
                            {{ totalCartQty }}
                        </span>
                    </h3>
                    <p class="text-[10px] text-muted-foreground" v-if="activeTable.active_order">
                        Mã đơn: {{ activeTable.active_order.order_number }} ({{ activeTable.active_order.status }})
                    </p>
                </div>
                <Button variant="ghost" size="icon" class="rounded-xl" @click="isCartOpen = false">
                    <X class="size-5" />
                </Button>
            </div>

            <!-- Red-flag Banner — đơn đã bị tách, chờ quản lý phê duyệt -->
            <div
                v-if="activeTable.active_order?.is_red_flagged"
                class="px-5 py-2.5 bg-rose-50 dark:bg-rose-950/20 border-b border-rose-200 dark:border-rose-900/50 flex items-center gap-2"
            >
                <AlertTriangle class="size-3.5 text-rose-600 shrink-0" />
                <span class="text-[10px] font-bold text-rose-700 dark:text-rose-400 leading-tight">
                    Đơn này đã bị tách — đang chờ quản lý phê duyệt
                </span>
            </div>

            <!-- Lock Banner — hiện khi có món đã gửi bếp -->
            <div
                v-if="isNotified && cartItems.some(i => i.id)"
                class="px-5 py-2.5 bg-amber-50 dark:bg-amber-950/20 border-b border-amber-200 dark:border-amber-900/50 flex items-center gap-2"
            >
                <Lock class="size-3.5 text-amber-600 shrink-0" />
                <span class="text-[10px] font-bold text-amber-700 dark:text-amber-400 leading-tight">
                    Đơn đã gửi bếp — món cũ bị khóa, chỉ có thể tăng hoặc thêm món mới
                </span>
            </div>

            <!-- Body Drawer -->
            <div class="flex-1 overflow-y-auto p-6 flex flex-col gap-6">
                <!-- BƯỚC 2: DANH SÁCH MÓN ĐÃ CHỌN (CHỈ HIỂN THỊ KHI Ở BƯỚC CONFIRM) -->
                <div v-if="drawerStep === 'confirm'" class="flex flex-col gap-3">
                    <h4 class="text-xs font-black uppercase text-slate-400 tracking-wider">Danh sách món đã chọn</h4>
                    <div v-if="cartItems.length === 0" class="text-center py-10 text-xs text-muted-foreground border-2 border-dashed rounded-2xl">
                        Chưa chọn món nào. Hãy click "Thêm món" để chọn món.
                    </div>

                    <div
                        v-for="item in cartItems"
                        :key="item.id ? 'exist-' + item.id : 'new-' + item.product_id"
                        class="flex justify-between items-center p-3 border rounded-2xl transition-colors"
                        :class="item.id
                            ? 'bg-amber-50/40 dark:bg-amber-950/10 border-amber-200/60 dark:border-amber-900/40'
                            : 'bg-slate-50/50 dark:bg-slate-900/20'"
                    >
                        <div class="text-left max-w-[60%]">
                            <span class="font-bold text-xs text-slate-800 dark:text-slate-200 flex items-center gap-1.5 flex-wrap">
                                {{ item.product_name }}
                                <span v-if="item.id" class="inline-flex items-center gap-0.5 text-[9px] font-bold text-amber-600 bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400 px-1.5 py-0.5 rounded-md">
                                    <Lock class="size-2.5" /> Đã gửi
                                </span>
                            </span>
                            <p class="text-[10px] text-muted-foreground font-mono mt-0.5">
                                {{ number_format(item.price) }}đ × {{ item.quantity }}
                                <span class="text-indigo-600 font-bold">= {{ number_format(item.price * item.quantity) }}đ</span>
                            </p>
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
                                :class="item.id ? 'border-indigo-400 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/30' : ''"
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

                    <div class="flex gap-1.5 overflow-x-auto pb-1 -mx-1 px-1">
                        <button
                            v-for="cat in [{ id: null, name: 'Tất cả' }, ...props.categories]"
                            :key="cat.id ?? 'all'"
                            type="button"
                            class="shrink-0 px-3 py-1.5 rounded-xl text-[10px] font-bold transition-all"
                            :class="selectedCategoryId === cat.id
                                ? 'bg-indigo-600 text-white'
                                : 'bg-slate-100 dark:bg-slate-800 text-slate-600 hover:bg-slate-200 dark:hover:bg-slate-700'"
                            @click="selectedCategoryId = cat.id"
                        >
                            {{ cat.name }}
                        </button>
                    </div>

                    <div v-if="filteredMenuProducts.length === 0" class="text-center py-10 text-xs text-muted-foreground border-2 border-dashed rounded-2xl">
                        Không có món nào trong danh mục này.
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div
                            v-for="prod in filteredMenuProducts"
                            :key="prod.id"
                            class="p-3 border rounded-2xl bg-white dark:bg-slate-900 shadow-sm transition-all text-left flex flex-col justify-between relative"
                            :class="[
                                getCartItemQty(prod.id) > 0 ? 'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50/10' : 'hover:border-indigo-500/50 hover:bg-slate-50/50',
                                (prod.is_paused || prod.is_out_of_stock) ? 'opacity-50 border-slate-200 dark:border-slate-800 bg-slate-100/50 dark:bg-slate-900/50' : ''
                            ]"
                        >
                            <!-- Click card to select/add -->
                            <div class="flex flex-col gap-1 w-full" :class="(prod.is_paused || prod.is_out_of_stock) ? 'pointer-events-none' : 'cursor-pointer'" @click="(prod.is_paused || prod.is_out_of_stock) ? null : handleProductCardClick(prod)">
                                <div class="flex justify-between items-start gap-1">
                                    <span class="font-bold text-xs truncate flex-1">{{ prod.name }}</span>
                                    <span v-if="prod.is_paused" class="bg-amber-100 text-amber-700 text-[8px] font-black uppercase px-1 rounded shrink-0">Tạm Dừng</span>
                                    <span v-else-if="prod.is_out_of_stock" class="bg-orange-100 text-orange-700 text-[8px] font-black uppercase px-1 rounded shrink-0">Hết Món</span>
                                </div>
                                <span class="text-[10px] font-mono text-indigo-600 font-bold mt-2">{{ number_format(prod.price) }}đ</span>
                            </div>

                            <!-- Quantity Controls -->
                            <div v-if="getCartItemQty(prod.id) > 0 && !prod.is_paused && !prod.is_out_of_stock" class="flex items-center justify-between mt-3 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded-xl w-full">
                                <button
                                    type="button"
                                    class="text-slate-550 hover:text-slate-800 dark:hover:text-white p-0.5"
                                    @click.stop="decreaseProductQty(prod.id)"
                                >
                                    <Minus class="size-3" />
                                </button>
                                <span class="text-xs font-mono font-black text-slate-800 dark:text-slate-100">
                                    {{ getCartItemQty(prod.id) }}
                                </span>
                                <button
                                    type="button"
                                    class="text-slate-550 hover:text-slate-800 dark:hover:text-white p-0.5"
                                    @click.stop="increaseProductQty(prod.id)"
                                >
                                    <Plus class="size-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GHI CHÚ ĐƠN HÀNG MỚI -->
                <div class="flex flex-col gap-3" v-if="drawerStep === 'confirm' && !activeTable.active_order">
                    <Separator />
                    <h4 class="text-xs font-black uppercase text-slate-400 tracking-wider">Ghi chú đơn hàng</h4>
                    <Input v-model="cartNote" placeholder="Ghi chú cho bếp, dị ứng, yêu cầu đặc biệt..." class="h-9 rounded-xl text-xs" />
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
                <!-- Discount breakdown khi đã áp voucher -->
                <div v-if="activeTable.active_order && isNotified && activeTable.active_order.discount_amount > 0" class="flex flex-col gap-1">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] text-muted-foreground">Tạm tính:</span>
                        <span class="text-xs font-mono text-slate-500">{{ number_format(activeTable.active_order.subtotal) }}đ</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] text-emerald-600 font-bold">Giảm giá voucher:</span>
                        <span class="text-xs font-mono font-bold text-emerald-600">-{{ number_format(activeTable.active_order.discount_amount) }}đ</span>
                    </div>
                </div>
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
                        @click="drawerStep = 'confirm'"
                    >
                        Xác nhận đặt món
                    </Button>
                </div>

                <!-- Footer buttons for Confirm step -->
                <div class="flex gap-2" v-if="drawerStep === 'confirm'">
                    <Button variant="outline" class="flex-1 rounded-xl text-xs border-indigo-200 text-indigo-600 hover:bg-indigo-50" @click="drawerStep = 'select'">
                        Thêm món
                    </Button>
                    <Button
                        v-if="!isNotified"
                        class="flex-1 rounded-xl text-xs bg-indigo-600 hover:bg-indigo-700"
                        :disabled="isSubmitting"
                        @click="submitOrder"
                    >
                        {{ isSubmitting ? 'Đang gửi...' : 'Thông báo' }}
                    </Button>
                    <Button
                        v-else-if="can('process_payments')"
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
                    <Button variant="outline" class="flex-1 rounded-xl text-xs border-rose-200 text-rose-600 hover:bg-rose-50" v-if="can('split_orders')" @click="openSplitOrder">
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
                        <!-- Discount breakdown khi đã áp voucher -->
                        <template v-if="activeTable?.active_order?.discount_amount && activeTable.active_order.discount_amount > 0">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-slate-500">Tạm tính:</span>
                                <span class="font-mono">{{ number_format(activeTable.active_order.subtotal) }}đ</span>
                            </div>
                            <div class="flex justify-between text-xs mb-2">
                                <span class="text-emerald-600 font-bold flex items-center gap-1">
                                    <CheckIcon class="size-3" /> Giảm giá voucher:
                                </span>
                                <span class="font-mono font-bold text-emerald-600">-{{ number_format(activeTable.active_order.discount_amount) }}đ</span>
                            </div>
                            <Separator class="mb-2" />
                        </template>
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-black">Số tiền cần thanh toán:</span>
                            <span class="text-lg font-mono font-black text-indigo-600">{{ number_format(activeTable?.active_order?.total_amount ?? 0) }}đ</span>
                        </div>
                    </div>

                    <!-- Tra cứu khách hàng tích điểm -->
                    <div class="flex flex-col gap-2 border-t pt-3 mt-1 text-left">
                        <span class="text-xs font-bold text-slate-500">Tích điểm thành viên:</span>
                        <div v-if="!foundCustomer" class="flex gap-2">
                            <Input 
                                type="text" 
                                placeholder="Nhập SĐT khách hàng..." 
                                v-model="searchCustomerPhone" 
                                @keyup.enter="searchCustomer"
                                class="rounded-xl text-xs h-9" 
                            />
                            <Button 
                                type="button"
                                size="sm" 
                                variant="outline" 
                                class="rounded-xl h-9" 
                                :disabled="isSearchingCustomer"
                                @click="searchCustomer"
                            >
                                <Search class="size-4 shrink-0 mr-1" />
                                Tìm
                            </Button>
                        </div>
                        <div v-else class="p-3 bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-150 rounded-xl flex items-center justify-between">
                            <div class="flex flex-col text-xs text-left">
                                <span class="font-bold text-slate-800 dark:text-slate-200">
                                    👤 {{ foundCustomer.full_name }}
                                </span>
                                <span class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold mt-0.5">
                                    SĐT: {{ foundCustomer.phone }} • Hạng: {{ foundCustomer.membership_level === 'diamond' ? '💎 Kim Cương (-10%)' : foundCustomer.membership_level === 'gold' ? '⭐ Vàng (-5%)' : '🥈 Bạc' }} • Điểm: {{ foundCustomer.loyalty_points }} pt
                                </span>
                                <span class="text-[9px] text-slate-400 mt-1">
                                    + Cộng thêm: {{ Math.floor((activeTable?.active_order?.total_amount ?? 0) / 10000) }} pt (10k = 1pt)
                                </span>
                            </div>
                            <Button 
                                type="button"
                                size="icon" 
                                variant="ghost" 
                                class="h-6 w-6 rounded-lg text-rose-500 hover:text-rose-600 hover:bg-rose-50"
                                @click="clearCustomerSelection"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Phương thức thanh toán -->
                    <div class="flex flex-col gap-2">
                        <span class="text-xs font-bold text-slate-500">Phương thức thanh toán:</span>
                        <div class="grid grid-cols-2 gap-2">
                            <Button
                                v-for="m in paymentMethods"
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

                    <!-- Thông tin ghi nợ VIP/B2B -->
                    <div class="flex flex-col gap-2 p-1 rounded-xl text-xs text-left" v-if="paymentMethod === 'debt'">
                        <div v-if="!foundCustomer" class="text-rose-500 font-bold bg-rose-50 dark:bg-rose-950/20 p-2.5 rounded-lg border border-rose-100 dark:border-rose-900/30">
                            ⚠️ Giao dịch ghi nợ yêu cầu chọn khách hàng trước.
                        </div>
                        <div v-else-if="!foundCustomer.is_vip && !foundCustomer.is_b2b" class="text-rose-500 font-bold bg-rose-50 dark:bg-rose-950/20 p-2.5 rounded-lg border border-rose-100 dark:border-rose-900/30">
                            ⚠️ Khách hàng này không được cấp quyền mua nợ (Không phải VIP/B2B).
                        </div>
                        <div v-else class="flex flex-col gap-1.5 bg-slate-50 dark:bg-slate-900/20 p-3 rounded-xl border">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Hạn mức nợ tối đa:</span>
                                <span class="font-mono font-bold">{{ number_format(foundCustomer.credit_limit) }}đ</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Dư nợ hiện tại:</span>
                                <span class="font-mono font-bold text-rose-500">{{ number_format(foundCustomer.current_debt) }}đ</span>
                            </div>
                            <div class="flex justify-between border-t pt-1.5 dark:border-slate-800">
                                <span class="text-slate-500 font-bold">Khả năng nợ còn lại:</span>
                                <span class="font-mono font-bold text-slate-800 dark:text-slate-200">
                                    {{ number_format(foundCustomer.credit_limit - foundCustomer.current_debt) }}đ
                                </span>
                            </div>
                            <div class="flex justify-between mt-1 text-[11px]" v-if="foundCustomer.credit_limit - foundCustomer.current_debt >= (activeTable?.active_order?.total_amount ?? 0)">
                                <span class="text-emerald-600 font-bold">✓ Đủ hạn mức tín dụng.</span>
                                <span class="text-slate-400">Còn lại: {{ number_format(foundCustomer.credit_limit - foundCustomer.current_debt - (activeTable?.active_order?.total_amount ?? 0)) }}đ</span>
                            </div>
                            <div v-else class="text-rose-500 font-bold mt-1 text-[11px]">
                                ❌ Vượt quá hạn mức nợ còn lại! Thiếu {{ number_format((activeTable?.active_order?.total_amount ?? 0) - (foundCustomer.credit_limit - foundCustomer.current_debt)) }}đ.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" class="flex-1 rounded-xl text-xs" @click="showPaymentModal = false">
                        Hủy
                    </Button>
                    <Button 
                        class="flex-1 rounded-xl text-xs bg-emerald-600 hover:bg-emerald-700" 
                        :disabled="isPaying || (paymentMethod === 'debt' && (!foundCustomer || (!foundCustomer.is_vip && !foundCustomer.is_b2b) || (foundCustomer.credit_limit - foundCustomer.current_debt < (activeTable?.active_order?.total_amount ?? 0))))" 
                        @click="processPayment"
                    >
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
                    <!-- Cảnh báo voucher -->
                    <div v-if="activeTable?.active_order?.discount_amount && activeTable.active_order.discount_amount > 0"
                         class="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-800">
                        <AlertTriangle class="size-4 text-amber-500 flex-shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold">Đơn có voucher giảm giá {{ number_format(activeTable.active_order.discount_amount) }}đ.</span>
                            <br>Giảm giá sẽ được phân bổ tự động theo tỷ lệ giá trị mỗi phần.
                        </div>
                    </div>

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

                    <!-- Dự tính tiền 2 đơn sau tách -->
                    <template v-if="splitProjection && splitProjection.hasItems">
                        <Separator />
                        <div class="grid grid-cols-2 gap-2 text-[10px]">
                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-2.5">
                                <div class="font-bold text-slate-600 mb-1.5">Đơn gốc (còn lại)</div>
                                <div class="flex justify-between"><span class="text-muted-foreground">Tạm tính:</span><span class="font-mono">{{ number_format(splitProjection.origSubtotal) }}đ</span></div>
                                <div v-if="splitProjection.origDiscount > 0" class="flex justify-between text-emerald-600"><span>Giảm giá:</span><span class="font-mono">-{{ number_format(splitProjection.origDiscount) }}đ</span></div>
                                <div class="flex justify-between font-black border-t mt-1 pt-1"><span>Tổng:</span><span class="font-mono text-rose-600">{{ number_format(splitProjection.origTotal) }}đ</span></div>
                            </div>
                            <div class="rounded-xl border border-rose-200 bg-rose-50/30 p-2.5">
                                <div class="font-bold text-rose-600 mb-1.5">Đơn tách mới</div>
                                <div class="flex justify-between"><span class="text-muted-foreground">Tạm tính:</span><span class="font-mono">{{ number_format(splitProjection.splitSubtotal) }}đ</span></div>
                                <div v-if="splitProjection.splitDiscount > 0" class="flex justify-between text-emerald-600"><span>Giảm giá:</span><span class="font-mono">-{{ number_format(splitProjection.splitDiscount) }}đ</span></div>
                                <div class="flex justify-between font-black border-t mt-1 pt-1"><span>Tổng:</span><span class="font-mono text-rose-600">{{ number_format(splitProjection.splitTotal) }}đ</span></div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" class="flex-1 rounded-xl text-xs" @click="showSplitModal = false">
                        Hủy
                    </Button>
                    <Button class="flex-1 rounded-xl text-xs bg-rose-600 hover:bg-rose-700" :disabled="!splitTableId || isSubmitting || !splitProjection?.hasItems" @click="processSplit">
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
                    <Button variant="ghost" size="icon" class="rounded-xl text-white/50 hover:text-white" @click="showAiSuggestionModal = false; aiUpsellItem = ''">
                        <X class="size-5" />
                    </Button>
                </div>

                <div class="text-left py-2">
                    <p class="text-xs text-indigo-200/80 uppercase tracking-wider font-semibold">Lời khuyên của trí tuệ nhân tạo BepsoViet:</p>
                    <div class="mt-3 p-4 bg-white/5 border border-indigo-500/20 rounded-2xl text-sm leading-relaxed font-medium">
                        "{{ aiSuggestion }}"
                    </div>
                    <div v-if="aiUpsellItem" class="mt-3 px-4 py-2.5 bg-indigo-600/20 border border-indigo-400/30 rounded-xl flex items-center gap-2">
                        <Sparkles class="size-4 text-indigo-300 shrink-0" />
                        <span class="text-xs font-bold text-indigo-100">
                            Gợi ý ngay: <span class="text-white underline underline-offset-2">{{ aiUpsellItem }}</span>
                        </span>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Button class="rounded-xl text-xs bg-indigo-600 hover:bg-indigo-700" @click="showAiSuggestionModal = false; aiUpsellItem = ''">
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
                        v-for="s in selfServiceTabs"
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
                    <!-- Lịch làm tuần này -->
                    <div>
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Lịch làm tuần này</span>
                        <div v-if="props.weeklySchedules.length === 0" class="mt-2 text-center py-4 text-[11px] text-muted-foreground border-2 border-dashed rounded-xl">
                            Chưa có lịch làm trong tuần này.
                        </div>
                        <div v-else class="mt-2 flex flex-col gap-1.5">
                            <div
                                v-for="sch in props.weeklySchedules"
                                :key="sch.id"
                                class="flex items-center justify-between px-3 py-2 rounded-xl border bg-slate-50/60 dark:bg-slate-900/40 text-xs"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-slate-700 dark:text-slate-300 min-w-[60px]">{{ sch.date }}</span>
                                    <span class="text-slate-600 dark:text-slate-400">{{ sch.shift_name }}</span>
                                    <span class="text-[10px] text-muted-foreground">{{ sch.shift_time }}</span>
                                </div>
                                <span
                                    class="text-[9px] font-bold px-1.5 py-0.5 rounded-md"
                                    :class="sch.status === 'completed' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                        : sch.status === 'checked_in' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400'
                                        : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ sch.status === 'completed' ? 'Hoàn thành' : sch.status === 'checked_in' ? 'Đang làm' : 'Đã đăng ký' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- Form đăng ký lịch -->
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Đăng ký ca mới</span>
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
                        <span v-if="props.activeShifts.length === 0" class="text-[10px] text-amber-600">
                            Chưa có ca làm việc nào được tạo — liên hệ quản lý để thêm ca.
                        </span>
                    </div>

                    <Button class="rounded-xl text-xs bg-indigo-600 hover:bg-indigo-700" :disabled="!regShiftName" @click="handleRegisterSchedule">
                        Gửi Đăng ký ca
                    </Button>
                </div>

                <!-- Tab 2: Xin nghỉ phép -->
                <div v-if="selfServiceTab === 'leave'" class="flex flex-col gap-4 text-left">
                    <!-- Đơn đã nộp gần đây -->
                    <div v-if="props.pendingLeaves.length > 0">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Đơn đã nộp gần đây</span>
                        <div class="mt-2 flex flex-col gap-1.5">
                            <div
                                v-for="lv in props.pendingLeaves"
                                :key="lv.id"
                                class="flex items-center justify-between px-3 py-2 rounded-xl border text-xs bg-slate-50/60 dark:bg-slate-900/40"
                            >
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-bold text-slate-700 dark:text-slate-300">
                                        {{ lv.leave_type === 'annual' ? 'Phép năm' : lv.leave_type === 'sick' ? 'Ốm đau' : lv.leave_type === 'unpaid' ? 'Không lương' : lv.leave_type === 'emergency' ? 'Khẩn cấp' : 'Thôi việc' }}
                                    </span>
                                    <span class="text-[10px] text-muted-foreground">{{ lv.start_date }} → {{ lv.end_date }}</span>
                                </div>
                                <span
                                    class="text-[9px] font-bold px-1.5 py-0.5 rounded-md shrink-0"
                                    :class="lv.status === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                        : lv.status === 'rejected' ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'
                                        : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'"
                                >
                                    {{ lv.status === 'approved' ? 'Đã duyệt' : lv.status === 'rejected' ? 'Từ chối' : 'Chờ duyệt' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <Separator v-if="props.pendingLeaves.length > 0" />

                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Nộp đơn mới</span>
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

                <!-- Tab 3: Khiếu nại ẩn danh -->
                <div v-if="selfServiceTab === 'complaint'" class="flex flex-col gap-4 text-left">
                    <p class="text-[11px] text-muted-foreground">
                        Thông tin của bạn được bảo mật tuyệt đối — quản lý và chủ nhà hàng chỉ thấy nội dung khiếu nại, không thấy người gửi.
                    </p>

                    <div class="flex flex-col gap-2">
                        <span class="text-xs font-bold text-slate-500">Đối tượng khiếu nại:</span>
                        <select v-model="complaintTargetId" class="border rounded-xl p-2.5 text-xs bg-card">
                            <option :value="null">-- Chọn nhân viên --</option>
                            <option v-for="c in props.colleagues" :key="c.id" :value="c.id">
                                {{ c.full_name }}<template v-if="c.job_title"> ({{ c.job_title }})</template>
                            </option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <span class="text-xs font-bold text-slate-500">Loại vi phạm:</span>
                        <select v-model="complaintType" class="border rounded-xl p-2.5 text-xs bg-card">
                            <option value="">-- Chọn loại vi phạm --</option>
                            <option value="thai_do">Thái độ làm việc</option>
                            <option value="gian_lan">Gian lận / thiếu trung thực</option>
                            <option value="quay_roi">Quấy rối / xúc phạm đồng nghiệp</option>
                            <option value="vi_pham_quy_dinh">Vi phạm quy định nhà hàng</option>
                            <option value="khac">Khác</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <span class="text-xs font-bold text-slate-500">Mô tả chi tiết:</span>
                        <textarea v-model="complaintDescription" placeholder="Vui lòng mô tả sự việc cụ thể, thời gian, địa điểm..." class="border rounded-xl p-2.5 text-xs min-h-20 bg-card"></textarea>
                    </div>

                    <Button class="rounded-xl text-xs bg-rose-600 hover:bg-rose-700" :disabled="!complaintTargetId || !complaintType || !complaintDescription" @click="handleComplaint">
                        Gửi Khiếu Nại Ẩn Danh
                    </Button>
                </div>

            </div>
        </div>
    </div>

    <!-- ── TOAST NOTIFICATIONS ──────────────────────────────────────────── -->
    <div class="fixed bottom-6 right-6 z-[70] flex flex-col gap-2 pointer-events-none">
        <transition-group name="toast">
            <div
                v-for="t in toasts"
                :key="t.id"
                class="flex items-center gap-2.5 px-4 py-3 rounded-2xl shadow-xl text-xs font-bold pointer-events-auto min-w-56 max-w-xs animate-fade-in"
                :class="t.type === 'success'
                    ? 'bg-emerald-600 text-white'
                    : 'bg-rose-600 text-white'"
            >
                <CheckIcon v-if="t.type === 'success'" class="size-4 shrink-0" />
                <XCircle v-else class="size-4 shrink-0" />
                <span class="leading-tight">{{ t.message }}</span>
            </div>
        </transition-group>
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
.toast-enter-active { animation: fade-in 0.2s ease-out; }
.toast-leave-active { animation: fade-in 0.2s ease-in reverse; }
.toast-move { transition: transform 0.2s ease; }
</style>
