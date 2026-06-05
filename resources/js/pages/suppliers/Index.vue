<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { 
    Plus, Edit2, Trash2, ShoppingBag, CheckCircle, 
    AlertTriangle, Sparkles, TrendingUp, TrendingDown,
    FileText, Upload, RefreshCw, X, Check
} from 'lucide-vue-next';

const props = defineProps<{
    suppliers: any[];
    ingredients: any[];
    purchaseOrders: any[];
    units: any[];
}>();

const page = usePage();
const roles = computed(() => {
    const raw = page.props.roles ?? [];
    return Array.isArray(raw) ? raw : Object.values(raw as Record<string, string>);
});
const isOwner = computed(() => roles.value.includes('owner'));

// Modals
const showAddModal = ref(false);
const showEditModal = ref(false);
const showPoModal = ref(false);
const showVerifyModal = ref(false);
const activeTab = ref('list'); // 'list' | 'pos' | 'analytics' | 'sla'

// Selected / Editing entities
const selectedSupplier = ref<any>(null);
const selectedIngredient = ref<any>(null);
const selectedPo = ref<any>(null);

// Forms
const supplierForm = useForm({
    id: null,
    name: '',
    contact_name: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
    status: 'active',
});

const poForm = useForm({
    items: [] as Array<{ ingredient_id: number; quantity: number }>,
    notes: '',
    delivery_due_date: '',
});

const verifyForm = useForm({
    items: [] as Array<{ ingredient_id: number; quantity_received: number; invoice_price: number }>,
    invoice_file: null as File | null,
    rating: 5,
    rating_notes: '',
});

// Analytics state
const analyticsData = ref<any>(null);
const loadingAnalytics = ref(false);

// SLA state
const slaData = ref<any>(null);
const loadingSla = ref(false);

const fetchSla = async () => {
    if (!selectedSupplier.value) return;
    loadingSla.value = true;
    try {
        const res = await fetch(route('suppliers.sla', selectedSupplier.value.id));
        slaData.value = await res.json();
    } catch (e) {
        console.error(e);
    } finally {
        loadingSla.value = false;
    }
};

const selectSupplierForSla = (sup: any) => {
    selectedSupplier.value = sup;
    fetchSla();
};

// Actions
const openAddModal = () => {
    supplierForm.reset();
    supplierForm.id = null;
    showAddModal.value = true;
};

const openEditModal = (supplier: any) => {
    supplierForm.id = supplier.id;
    supplierForm.name = supplier.name;
    supplierForm.contact_name = supplier.contact_name;
    supplierForm.phone = supplier.phone;
    supplierForm.email = supplier.email;
    supplierForm.address = supplier.address;
    supplierForm.notes = supplier.notes;
    supplierForm.status = supplier.status;
    showEditModal.value = true;
};

const saveSupplier = () => {
    if (supplierForm.id) {
        supplierForm.patch(route('suppliers.update', supplierForm.id), {
            onSuccess: () => {
                showEditModal.value = false;
                supplierForm.reset();
            }
        });
    } else {
        supplierForm.post(route('suppliers.store'), {
            onSuccess: () => {
                showAddModal.value = false;
                supplierForm.reset();
            }
        });
    }
};

const deleteSupplier = (supplier: any) => {
    if (confirm(`Bạn có chắc chắn muốn xóa nhà cung cấp "${supplier.name}"?`)) {
        router.delete(route('suppliers.destroy', supplier.id));
    }
};

const triggerAutoReplenish = () => {
    if (confirm('Bạn có chắc chắn muốn kích hoạt AI quét kho và tự động đề xuất PO nháp?')) {
        router.post(route('suppliers.auto-replenish'), {}, {
            onSuccess: () => {
                activeTab.value = 'pos';
            }
        });
    }
};

// Purchase Order creation
const openPoModal = (supplier: any) => {
    selectedSupplier.value = supplier;
    poForm.reset();
    poForm.items = [{ ingredient_id: props.ingredients.filter(i => i.supplier_id === supplier.id)[0]?.id || 0, quantity: 1 }];
    showPoModal.value = true;
};

const addPoItem = () => {
    const available = props.ingredients.filter(i => i.supplier_id === selectedSupplier.value.id);
    if (available.length > 0) {
        poForm.items.push({ ingredient_id: available[0].id, quantity: 1 });
    }
};

const removePoItem = (index: number | string) => {
    poForm.items.splice(Number(index), 1);
};

const submitPo = () => {
    poForm.post(route('suppliers.place-order', selectedSupplier.value.id), {
        onSuccess: () => {
            showPoModal.value = false;
            activeTab.value = 'pos';
        }
    });
};

const approvePo = (po: any) => {
    router.post(route('suppliers.orders.approve', po.id));
};

const releaseEscrow = (po: any) => {
    if (confirm(`Bạn có chắc chắn muốn giải ngân thủ công số tiền ${Number(po.total_amount).toLocaleString('vi-VN')}đ cho nhà cung cấp?`)) {
        router.post(route('suppliers.orders.release-escrow', po.id));
    }
};

const refundEscrow = (po: any) => {
    if (confirm(`Bạn có chắc chắn muốn hoàn trả số tiền ký quỹ ${Number(po.total_amount).toLocaleString('vi-VN')}đ về tài khoản của nhà hàng?`)) {
        router.post(route('suppliers.orders.refund-escrow', po.id));
    }
};

// Dual-Verification Modal
const openVerifyModal = (po: any) => {
    selectedPo.value = po;
    verifyForm.reset();
    verifyForm.items = po.items.map((item: any) => ({
        ingredient_id: item.ingredient_id || 0,
        quantity_received: item.quantity_ordered,
        invoice_price: item.price_per_unit,
        price_per_unit: item.price_per_unit,
        quantity_ordered: item.quantity_ordered,
        ingredient_name: item.ingredient_name
    }));
    showVerifyModal.value = true;
};

// Calculate discrepancies dynamically
const verifyDiscrepancies = computed(() => {
    if (!selectedPo.value || !verifyForm.items.length) return [];
    
    return verifyForm.items.map((item: any) => {
        const qtyMismatch = Math.abs(item.quantity_ordered - item.quantity_received) > 0.001;
        const priceMismatch = Math.abs(item.price_per_unit - item.invoice_price) > 0.01;
        return {
            ...item,
            qtyMismatch,
            priceMismatch,
            mismatch: qtyMismatch || priceMismatch
        };
    });
});

const hasMismatch = computed(() => {
    return verifyDiscrepancies.value.some((d: any) => d.mismatch);
});

const updateQuantityReceived = (idx: number | string, val: string) => {
    verifyForm.items[Number(idx)].quantity_received = Number(val);
};

const updateInvoicePrice = (idx: number | string, val: string) => {
    verifyForm.items[Number(idx)].invoice_price = Number(val);
};


const submitVerification = () => {
    const formData = new FormData();
    verifyForm.items.forEach((item: any, index: number) => {
        formData.append(`items[${index}][ingredient_id]`, String(item.ingredient_id));
        formData.append(`items[${index}][quantity_received]`, String(item.quantity_received));
        formData.append(`items[${index}][invoice_price]`, String(item.invoice_price));
    });
    
    if (verifyForm.invoice_file) {
        formData.append('invoice_file', verifyForm.invoice_file);
    }
    if (verifyForm.rating) {
        formData.append('rating', String(verifyForm.rating));
    }
    if (verifyForm.rating_notes) {
        formData.append('rating_notes', verifyForm.rating_notes);
    }

    router.post(route('suppliers.orders.verify', selectedPo.value.id), formData as any, {
        onSuccess: () => {
            showVerifyModal.value = false;
        }
    });
};

// Analytics handler
const fetchAnalytics = async () => {
    if (!selectedSupplier.value || !selectedIngredient.value) return;
    loadingAnalytics.value = true;
    try {
        const res = await fetch(route('suppliers.price-analytics', {
            supplier: selectedSupplier.value?.id || 0,
            ingredient: selectedIngredient.value?.id || 0
        }));
        analyticsData.value = await res.json();
    } catch (e) {
        console.error(e);
    } finally {
        loadingAnalytics.value = false;
    }
};

const selectSupplierForAnalytics = (sup: any) => {
    selectedSupplier.value = sup;
    selectedIngredient.value = props.ingredients.filter(i => i.supplier_id === sup.id)[0] || null;
    fetchAnalytics();
};

const selectIngredientForAnalytics = (ing: any) => {
    selectedIngredient.value = ing;
    fetchAnalytics();
};

const loadingOcr = ref(false);

const handleFileUpload = async (e: Event) => {
    const input = e.target as HTMLInputElement;
    if (!input.files || !input.files[0] || !selectedPo.value) return;
    
    const file = input.files[0];
    verifyForm.invoice_file = file;
    
    loadingOcr.value = true;
    try {
        const formData = new FormData();
        formData.append('invoice_file', file);
        
        // Pass PO items context to match OCR
        const contextItems = selectedPo.value.items.map((it: any) => ({
            ingredient_id: it.ingredient_id,
            ingredient_name: it.ingredient_name,
            quantity_ordered: it.quantity_ordered,
            price_per_unit: it.price_per_unit,
        }));
        formData.append('po_items', JSON.stringify(contextItems));
        
        const res = await fetch(route('suppliers.ocr-invoice'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            }
        });
        
        if (res.ok) {
            const data = await res.json();
            if (data.items && data.items.length > 0) {
                const parsedItems = data.items;
                verifyForm.items.forEach((formItem: any) => {
                    const matched = parsedItems.find((pi: any) => pi.ingredient_id === formItem.ingredient_id);
                    if (matched) {
                        formItem.quantity_received = matched.quantity;
                        formItem.invoice_price = matched.unit_price;
                    }
                });
                alert(`AI OCR: Đã quét hóa đơn số thành công! Khớp tự động ${parsedItems.length} nguyên vật liệu với độ tin cậy ${Math.round(data.confidence * 100)}%.`);
            }
        }
    } catch (err) {
        console.error('OCR scanning error:', err);
    } finally {
        loadingOcr.value = false;
    }
};
</script>

<template>
    <Head title="Quản lý Nhà cung cấp" />

    <div class="px-6 py-8 max-w-7xl mx-auto space-y-6 text-slate-100">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-6">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-transparent">
                    Portal Chuỗi cung ứng & Nhà cung cấp
                </h1>
                <p class="text-sm text-slate-400 mt-1">
                    Quản lý danh sách đối tác cung ứng, đặt hàng hàng ngày, đối soát chéo 3 bên triệt tiêu gian lận.
                </p>
            </div>
            <div class="flex gap-2">
                <button 
                    v-if="isOwner"
                    @click="triggerAutoReplenish" 
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-500 text-white rounded-lg font-semibold hover:from-purple-500 hover:to-indigo-400 shadow-lg shadow-purple-950/30 transition-all active:scale-95 border border-purple-500/30"
                >
                    <Sparkles class="w-5 h-5" />
                    AI Đề xuất Nhập hàng
                </button>
                <button 
                    @click="openAddModal" 
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-500 text-white rounded-lg font-semibold hover:from-emerald-500 hover:to-teal-400 shadow-lg shadow-emerald-950/30 transition-all active:scale-95"
                >
                    <Plus class="w-5 h-5" />
                    Thêm đối tác
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex border-b border-slate-800">
            <button 
                @click="activeTab = 'list'" 
                :class="['px-5 py-3 font-medium text-sm transition-all border-b-2', activeTab === 'list' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-slate-400 hover:text-slate-200']"
            >
                Danh sách đối tác
            </button>
            <button 
                @click="activeTab = 'pos'" 
                :class="['px-5 py-3 font-medium text-sm transition-all border-b-2', activeTab === 'pos' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-slate-400 hover:text-slate-200']"
            >
                Nhật ký đặt hàng (PO)
            </button>
            <button 
                @click="activeTab = 'analytics'" 
                :class="['px-5 py-3 font-medium text-sm transition-all border-b-2', activeTab === 'analytics' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-slate-400 hover:text-slate-200']"
            >
                AI Phân tích biến động giá
            </button>
            <button 
                @click="activeTab = 'sla'" 
                :class="['px-5 py-3 font-medium text-sm transition-all border-b-2', activeTab === 'sla' ? 'border-emerald-500 text-emerald-400' : 'border-transparent text-slate-400 hover:text-slate-200']"
            >
                Báo cáo SLA & Đánh giá
            </button>
        </div>

        <!-- Tab Content: Suppliers List -->
        <div v-if="activeTab === 'list'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div 
                v-for="sup in suppliers" 
                :key="sup.id" 
                class="bg-slate-900/60 border border-slate-800/80 rounded-xl p-5 hover:border-slate-700/80 transition-all flex flex-col justify-between shadow-md hover:shadow-lg backdrop-blur-sm"
            >
                <div class="space-y-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-slate-200">{{ sup.name }}</h3>
                            <span :class="['text-xs px-2 py-0.5 rounded font-semibold', sup.status === 'active' ? 'bg-emerald-950 text-emerald-400 border border-emerald-900' : 'bg-rose-950 text-rose-400 border border-rose-900']">
                                {{ sup.status === 'active' ? 'Đang hoạt động' : 'Tạm khóa' }}
                            </span>
                        </div>
                        <div class="flex gap-1">
                            <button @click="openEditModal(sup)" class="p-1.5 text-slate-400 hover:text-amber-400 rounded-md hover:bg-slate-800 transition-colors">
                                <Edit2 class="w-4 h-4" />
                            </button>
                            <button @click="deleteSupplier(sup)" class="p-1.5 text-slate-400 hover:text-rose-400 rounded-md hover:bg-slate-800 transition-colors">
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <div class="text-xs text-slate-400 space-y-1 pt-2">
                        <p v-if="sup.contact_name"><strong>Đại diện:</strong> {{ sup.contact_name }}</p>
                        <p v-if="sup.phone"><strong>Điện thoại:</strong> {{ sup.phone }}</p>
                        <p v-if="sup.email"><strong>Email:</strong> {{ sup.email }}</p>
                        <p v-if="sup.address"><strong>Địa chỉ:</strong> {{ sup.address }}</p>
                    </div>
                </div>

                <div class="flex gap-2 pt-5 border-t border-slate-800 mt-4">
                    <button 
                        @click="openPoModal(sup)" 
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold rounded-lg text-emerald-400 transition-colors border border-slate-700/50"
                    >
                        <ShoppingBag class="w-3.5 h-3.5" />
                        Đặt hàng hàng ngày
                    </button>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="suppliers.length === 0" class="col-span-full py-16 text-center bg-slate-900/20 border border-dashed border-slate-800 rounded-2xl">
                <AlertTriangle class="w-12 h-12 text-slate-600 mx-auto mb-3" />
                <p class="text-slate-400 font-medium">Chưa có nhà cung cấp nào được cấu hình.</p>
                <button @click="openAddModal" class="mt-4 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-500 transition-all">Thêm đối tác ngay</button>
            </div>
        </div>

        <!-- Tab Content: PO logs -->
        <div v-if="activeTab === 'pos'" class="bg-slate-900/40 border border-slate-800/80 rounded-xl overflow-hidden backdrop-blur-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-slate-300">
                    <thead class="bg-slate-950/60 text-slate-400 uppercase text-xs border-b border-slate-800">
                        <tr>
                            <th class="px-6 py-4">Mã đơn PO</th>
                            <th class="px-6 py-4">Đối tác</th>
                            <th class="px-6 py-4">Người đặt</th>
                            <th class="px-6 py-4">Tổng tiền gốc</th>
                            <th class="px-6 py-4">Thực tế hóa đơn</th>
                            <th class="px-6 py-4">Ký quỹ B2B</th>
                            <th class="px-6 py-4">Trạng thái</th>
                            <th class="px-6 py-4">Ngày đặt</th>
                            <th class="px-6 py-4 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <tr v-for="po in purchaseOrders" :key="po.id" class="hover:bg-slate-900/20 transition-colors">
                            <td class="px-6 py-4 font-mono font-bold text-emerald-400">{{ po.po_number }}</td>
                            <td class="px-6 py-4 font-semibold">{{ po.supplier_name }}</td>
                            <td class="px-6 py-4">{{ po.created_by_name }}</td>
                            <td class="px-6 py-4 font-semibold">{{ Number(po.total_amount).toLocaleString('vi-VN') }}đ</td>
                            <td class="px-6 py-4">
                                <span v-if="po.invoice_total_amount > 0" class="font-semibold text-slate-200">
                                    {{ Number(po.invoice_total_amount).toLocaleString('vi-VN') }}đ
                                </span>
                                <span v-else class="text-slate-500">—</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-0.5">
                                    <span v-if="po.payment_status === 'unpaid'" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-950 text-slate-400 border border-slate-850">
                                        Chưa thanh toán
                                    </span>
                                    <span v-else-if="po.payment_status === 'escrow_locked'" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-950 text-amber-400 border border-amber-900/50 animate-pulse">
                                        Ký quỹ (Khóa)
                                    </span>
                                    <span v-else-if="po.payment_status === 'paid'" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-950 text-emerald-400 border border-emerald-900/50">
                                        Giải ngân
                                    </span>
                                    <span v-else-if="po.payment_status === 'refunded'" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-950 text-rose-400 border border-rose-900/50">
                                        Đã hoàn trả
                                    </span>
                                    <p v-if="po.escrow_transaction_id" class="text-[9px] text-slate-500 font-mono mt-0.5 leading-none">{{ po.escrow_transaction_id }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="po.status === 'pending_approval'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-950 text-amber-400 border border-amber-900">
                                    Chờ phê duyệt
                                </span>
                                <span v-else-if="po.status === 'approved'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-950 text-blue-400 border border-blue-900">
                                    Đã duyệt/Chờ nhận đơn
                                </span>
                                <span v-else-if="po.status === 'preparing'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-purple-950 text-purple-400 border border-purple-900">
                                    Đang chuẩn bị hàng
                                </span>
                                <span v-else-if="po.status === 'shipping'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-indigo-950 text-indigo-400 border border-indigo-900">
                                    Đang giao hàng
                                </span>
                                <span v-else-if="po.status === 'delivered'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-950 text-emerald-400 border border-emerald-900">
                                    Đã hạ hàng tại kho
                                </span>
                                <span v-else-if="po.status === 'frozen'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-950 text-rose-400 border border-rose-900 animate-pulse">
                                    ĐÓNG BĂNG/LỆCH KHO
                                </span>
                                <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-800 text-slate-400">
                                    Đã hủy
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-400">{{ po.created_at }}</td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <!-- Owner Approval -->
                                <button 
                                    v-if="po.status === 'pending_approval' && isOwner" 
                                    @click="approvePo(po)"
                                    class="px-2.5 py-1 bg-emerald-700 hover:bg-emerald-600 text-xs font-bold rounded text-white transition-colors"
                                >
                                    Duyệt đặt
                                </button>
                                <!-- Warehouse verification -->
                                <button 
                                    v-if="['approved', 'preparing', 'shipping', 'delivered', 'frozen'].includes(po.status)" 
                                    @click="openVerifyModal(po)"
                                    class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-xs font-bold rounded text-emerald-400 transition-colors border border-slate-700"
                                >
                                    Đối soát & Giao nhận
                                </button>
                                <!-- Escrow Actions -->
                                <template v-if="po.payment_status === 'escrow_locked' && isOwner">
                                    <button 
                                        @click="releaseEscrow(po)"
                                        class="px-2.5 py-1 bg-emerald-700 hover:bg-emerald-600 text-xs font-bold rounded text-white transition-colors"
                                        title="Giải ngân tiền thầu thầu ký quỹ cho nhà cung cấp"
                                    >
                                        Giải ngân
                                    </button>
                                    <button 
                                        @click="refundEscrow(po)"
                                        class="px-2.5 py-1 bg-rose-700 hover:bg-rose-600 text-xs font-bold rounded text-white transition-colors"
                                        title="Hoàn tiền ký quỹ về tài khoản nhà hàng"
                                    >
                                        Hoàn trả
                                    </button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="purchaseOrders.length === 0">
                            <td colspan="8" class="text-center py-12 text-slate-500 font-medium">Chưa phát sinh đơn đặt hàng PO nào.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab Content: AI Price Analytics -->
        <div v-if="activeTab === 'analytics'" class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Left panel: Select supplier/material -->
            <div class="lg:col-span-1 bg-slate-900/60 border border-slate-800/80 rounded-xl p-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">1. Chọn nhà cung cấp</label>
                    <select 
                        v-model="selectedSupplier" 
                        @change="selectSupplierForAnalytics(selectedSupplier)"
                        class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500"
                    >
                        <option :value="null" disabled>-- Chọn đối tác --</option>
                        <option v-for="sup in suppliers" :key="sup.id" :value="sup">{{ sup.name }}</option>
                    </select>
                </div>

                <div v-if="selectedSupplier">
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">2. Nguyên vật liệu cung ứng</label>
                    <div class="space-y-1.5 max-h-80 overflow-y-auto">
                        <button 
                            v-for="ing in ingredients.filter(i => i.supplier_id === selectedSupplier.id)"
                            :key="ing.id"
                            @click="selectIngredientForAnalytics(ing)"
                            :class="['w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition-colors flex items-center justify-between', selectedIngredient?.id === ing.id ? 'bg-emerald-950/50 border border-emerald-900 text-emerald-400' : 'bg-slate-950 hover:bg-slate-900 text-slate-300 border border-transparent']"
                        >
                            <span>{{ ing.name }}</span>
                            <span class="text-slate-400 text-[10px] font-mono">{{ ing.sku }}</span>
                        </button>
                        <p v-if="ingredients.filter(i => i.supplier_id === selectedSupplier.id).length === 0" class="text-xs text-slate-500 py-4 text-center">
                            Không tìm thấy nguyên liệu của nhà cung cấp này.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right panel: Trend analysis -->
            <div class="lg:col-span-3 bg-slate-900/60 border border-slate-800/80 rounded-xl p-6 flex flex-col justify-between">
                <div v-if="loadingAnalytics" class="flex-1 flex flex-col items-center justify-center py-20 space-y-3">
                    <RefreshCw class="w-8 h-8 text-emerald-400 animate-spin" />
                    <p class="text-slate-400 text-sm font-medium">Đang gọi Python AI microservice phân tích dữ liệu...</p>
                </div>

                <div v-else-if="analyticsData" class="space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-800 pb-4">
                        <div>
                            <h3 class="text-xl font-bold text-slate-200">Xu hướng biến động giá: {{ selectedIngredient?.name }}</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Nhà cung cấp: {{ selectedSupplier?.name }}</p>
                        </div>
                        <div class="flex items-center gap-2 mt-2 sm:mt-0">
                            <span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold shadow-sm', analyticsData.trend === 'upward' ? 'bg-rose-950 text-rose-400 border border-rose-900' : analyticsData.trend === 'downward' ? 'bg-emerald-950 text-emerald-400 border border-emerald-900' : 'bg-slate-800 text-slate-300 border border-slate-700']">
                                <TrendingUp v-if="analyticsData.trend === 'upward'" class="w-4 h-4" />
                                <TrendingDown v-if="analyticsData.trend === 'downward'" class="w-4 h-4" />
                                Biên độ: {{ analyticsData.percentage_change }}%
                            </span>
                        </div>
                    </div>

                    <!-- AI Insights Box -->
                    <div class="bg-emerald-950/20 border border-emerald-900/60 rounded-xl p-4 flex gap-3.5">
                        <Sparkles class="w-6 h-6 text-emerald-400 shrink-0 mt-0.5" />
                        <div>
                            <h4 class="text-sm font-bold text-emerald-400">Khuyến nghị điều chỉnh giá vốn</h4>
                            <p class="text-xs text-slate-300 mt-1 leading-relaxed">{{ analyticsData.recommendation }}</p>
                        </div>
                    </div>

                    <!-- Mock Price Trend Chart using SVG -->
                    <div class="bg-slate-950/60 border border-slate-800 p-4 rounded-xl">
                        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4">Lịch sử giá trung bình theo tháng</h4>
                        <div class="relative h-48 w-full flex items-end justify-around gap-4 pt-6">
                            <!-- Draw simple grid lines -->
                            <div class="absolute inset-0 flex flex-col justify-between pointer-events-none text-[10px] text-slate-600 border-b border-slate-800/80">
                                <div class="border-b border-slate-800/30 w-full h-0"></div>
                                <div class="border-b border-slate-800/30 w-full h-0"></div>
                                <div class="border-b border-slate-800/30 w-full h-0"></div>
                            </div>

                            <div 
                                v-for="(item, idx) in analyticsData.monthly_averages" 
                                :key="idx" 
                                class="flex-1 flex flex-col items-center justify-end h-full z-10 group"
                            >
                                <span class="opacity-0 group-hover:opacity-100 transition-opacity bg-slate-900 text-slate-200 text-[10px] font-bold px-2 py-0.5 rounded border border-slate-700 absolute mb-16 shadow-lg">
                                    {{ Number(item.price).toLocaleString('vi-VN') }}đ
                                </span>
                                <div 
                                    class="w-full max-w-[40px] bg-gradient-to-t from-emerald-600/80 to-teal-400/80 rounded-t-md hover:from-emerald-500 hover:to-teal-300 transition-all"
                                    :style="{ height: `${Math.max(15, Math.min(100, (item.price / Math.max(...analyticsData.monthly_averages.map((m: any) => m.price))) * 90))}%` }"
                                ></div>
                                <span class="text-[10px] text-slate-400 font-medium mt-2">{{ item.period }}</span>
                            </div>

                            <p v-if="!analyticsData.monthly_averages || analyticsData.monthly_averages.length === 0" class="text-slate-500 text-xs py-10 w-full text-center">
                                Chưa có đủ dữ liệu lịch sử giá của nhiều tháng.
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="flex-1 flex flex-col items-center justify-center py-24 text-slate-500">
                    <FileText class="w-12 h-12 mb-3 text-slate-600" />
                    <p class="text-sm font-medium">Vui lòng chọn nhà cung cấp và nguyên liệu để phân tích biến động giá.</p>
                </div>
            </div>
        </div>

        <!-- Tab Content: SLA Scorecard -->
        <div v-if="activeTab === 'sla'" class="grid grid-cols-1 lg:grid-cols-4 gap-6 animate-in fade-in duration-200">
            <!-- Left panel: Select supplier -->
            <div class="lg:col-span-1 bg-slate-900/60 border border-slate-800/80 rounded-xl p-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Chọn nhà cung cấp đối soát</label>
                    <select 
                        v-model="selectedSupplier" 
                        @change="selectSupplierForSla(selectedSupplier)"
                        class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500"
                    >
                        <option :value="null" disabled>-- Chọn đối tác --</option>
                        <option v-for="sup in suppliers" :key="sup.id" :value="sup">{{ sup.name }}</option>
                    </select>
                </div>
            </div>

            <!-- Right panel: SLA report -->
            <div class="lg:col-span-3 bg-slate-900/60 border border-slate-800/80 rounded-xl p-6 flex flex-col justify-between">
                <div v-if="loadingSla" class="flex-1 flex flex-col items-center justify-center py-20 space-y-3">
                    <RefreshCw class="w-8 h-8 text-emerald-400 animate-spin" />
                    <p class="text-slate-400 text-sm font-medium">Đang tính toán chỉ số SLA...</p>
                </div>

                <div v-else-if="slaData" class="space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-800 pb-4">
                        <div>
                            <h3 class="text-xl font-bold text-slate-200">Báo cáo hiệu suất (SLA): {{ slaData.supplier_name }}</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Phân tích dựa trên {{ slaData.total_orders_analyzed }} đơn đặt hàng đã giao nhận.</p>
                        </div>
                        <div class="flex items-center gap-2 mt-2 sm:mt-0">
                            <span class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-950/60 text-emerald-400 border border-emerald-900 rounded-full text-xs font-bold">
                                Điểm trung bình: {{ slaData.average_rating }} / 5.0 ★
                            </span>
                        </div>
                    </div>

                    <!-- SLA Indicators -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-slate-950/60 border border-slate-800 p-4 rounded-xl space-y-2">
                            <div class="flex items-center justify-between text-xs text-slate-400">
                                <span>Tỷ lệ giao hàng đúng hạn (Punctuality)</span>
                                <span class="font-bold text-emerald-400 text-sm">{{ slaData.on_time_rate }}%</span>
                            </div>
                            <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
                                <div class="bg-emerald-500 h-full rounded-full transition-all" :style="{ width: `${slaData.on_time_rate}%` }"></div>
                            </div>
                        </div>

                        <div class="bg-slate-950/60 border border-slate-800 p-4 rounded-xl space-y-2">
                            <div class="flex items-center justify-between text-xs text-slate-400">
                                <span>Độ chính xác đối soát chéo (Accuracy)</span>
                                <span class="font-bold text-teal-400 text-sm">{{ slaData.accuracy_rate }}%</span>
                            </div>
                            <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
                                <div class="bg-teal-500 h-full rounded-full transition-all" :style="{ width: `${slaData.accuracy_rate}%` }"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Volatility details -->
                    <div class="bg-slate-950/40 border border-slate-800 rounded-xl p-4 space-y-3">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Chỉ số biến động giá vật tư (Price Volatility Index)</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left text-slate-300">
                                <thead class="bg-slate-950 text-slate-450 border-b border-slate-850">
                                    <tr>
                                        <th class="py-2">Tên vật tư</th>
                                        <th class="py-2 text-center">SKU</th>
                                        <th class="py-2 text-right">Giá hiện tại</th>
                                        <th class="py-2 text-center">Số lần thay đổi</th>
                                        <th class="py-2 text-right">Độ biến động (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in slaData.price_volatility" :key="item.sku" class="border-b border-slate-850 hover:bg-slate-900/10">
                                        <td class="py-2 font-medium">{{ item.ingredient_name }}</td>
                                        <td class="py-2 text-center font-mono">{{ item.sku }}</td>
                                        <td class="py-2 text-right">{{ Number(item.current_price).toLocaleString('vi-VN') }}đ</td>
                                        <td class="py-2 text-center font-mono">{{ item.price_history_count }}</td>
                                        <td class="py-2 text-right font-mono font-bold" :class="item.volatility_percent > 10 ? 'text-rose-400' : 'text-emerald-400'">
                                            {{ item.volatility_percent }}%
                                        </td>
                                    </tr>
                                    <tr v-if="!slaData.price_volatility || slaData.price_volatility.length === 0">
                                        <td colspan="5" class="text-center py-4 text-slate-500">Không có dữ liệu lịch sử biến động giá.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Ratings -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Đánh giá & Phản hồi gần đây</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div v-for="rate in slaData.recent_ratings.slice(0, 4)" :key="rate.po_number" class="bg-slate-950/50 border border-slate-855 p-3.5 rounded-xl space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-mono font-bold text-emerald-400">{{ rate.po_number }}</span>
                                    <span class="text-amber-400 text-xs font-bold">{{ '★'.repeat(rate.rating) + '☆'.repeat(5 - rate.rating) }}</span>
                                </div>
                                <p class="text-xs text-slate-300 italic">"{{ rate.rating_notes || 'Không có ghi chú thêm.' }}"</p>
                                <p class="text-[10px] text-slate-500 text-right">{{ rate.delivered_at }}</p>
                            </div>
                            <p v-if="!slaData.recent_ratings || slaData.recent_ratings.length === 0" class="col-span-full text-center py-4 text-slate-500 text-xs">Chưa có đánh giá sao nào cho nhà cung cấp này.</p>
                        </div>
                    </div>
                </div>

                <div v-else class="flex-1 flex flex-col items-center justify-center py-24 text-slate-500">
                    <FileText class="w-12 h-12 mb-3 text-slate-600" />
                    <p class="text-sm font-medium">Vui lòng chọn nhà cung cấp để hiển thị báo cáo SLA.</p>
                </div>
            </div>
        </div>

        <!-- Add/Edit Supplier Modals -->
        <div v-if="showAddModal || showEditModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 bg-slate-950 flex items-center justify-between border-b border-slate-800">
                    <h3 class="font-bold text-lg text-slate-200">{{ showAddModal ? 'Thêm nhà cung cấp mới' : 'Chỉnh sửa nhà cung cấp' }}</h3>
                    <button @click="showAddModal = false; showEditModal = false;" class="p-1 text-slate-400 hover:text-slate-200 rounded-md hover:bg-slate-800 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="saveSupplier" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Tên nhà cung cấp <span class="text-rose-500">*</span></label>
                            <input v-model="supplierForm.name" required type="text" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Người đại diện</label>
                            <input v-model="supplierForm.contact_name" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Điện thoại</label>
                            <input v-model="supplierForm.phone" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Email liên hệ</label>
                            <input v-model="supplierForm.email" type="email" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Địa chỉ</label>
                            <input v-model="supplierForm.address" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Ghi chú đối tác</label>
                            <textarea v-model="supplierForm.notes" rows="3" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500"></textarea>
                        </div>
                        <div v-if="showEditModal">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Trạng thái</label>
                            <select v-model="supplierForm.status" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500">
                                <option value="active">Đang hoạt động</option>
                                <option value="inactive">Tạm khóa</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t border-slate-800 mt-6">
                        <button type="button" @click="showAddModal = false; showEditModal = false;" class="px-4 py-2 border border-slate-800 text-sm font-semibold rounded-lg text-slate-400 hover:text-slate-200 transition-colors">
                            Hủy bỏ
                        </button>
                        <button type="submit" :disabled="supplierForm.processing" class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-500 text-white rounded-lg text-sm font-semibold hover:from-emerald-500 hover:to-teal-400 transition-all">
                            {{ supplierForm.processing ? 'Đang lưu...' : 'Lưu thông tin' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Place PO Modal -->
        <div v-if="showPoModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 bg-slate-950 flex items-center justify-between border-b border-slate-800">
                    <h3 class="font-bold text-lg text-slate-200">Đặt hàng nguyên liệu: {{ selectedSupplier?.name }}</h3>
                    <button @click="showPoModal = false" class="p-1 text-slate-400 hover:text-slate-200 rounded-md hover:bg-slate-800 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitPo" class="p-6 space-y-4">
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <div v-for="(item, idx) in poForm.items" :key="idx" class="flex gap-4 items-end bg-slate-950 p-3.5 rounded-xl border border-slate-850">
                            <div class="flex-1">
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Nguyên liệu niêm yết</label>
                                <select 
                                    v-model="item.ingredient_id" 
                                    required 
                                    class="w-full bg-slate-900 border border-slate-850 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500"
                                >
                                    <option 
                                        v-for="ing in ingredients.filter(i => i.supplier_id === selectedSupplier?.id)" 
                                        :key="ing.id" 
                                        :value="ing.id"
                                    >
                                        {{ ing.name }} (Giá niêm yết: {{ Number(ing.price).toLocaleString('vi-VN') }}đ/{{ ing.unit_symbol }})
                                    </option>
                                </select>
                            </div>
                            <div class="w-32">
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Số lượng mua</label>
                                <input v-model="item.quantity" required type="number" step="0.001" min="0.001" class="w-full bg-slate-900 border border-slate-850 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" />
                            </div>
                            <button type="button" @click="removePoItem(idx)" class="p-2 text-rose-500 hover:text-rose-400 rounded hover:bg-slate-900 transition-colors border border-transparent">
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>

                        <button type="button" @click="addPoItem" class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-400 hover:text-emerald-300 pt-2">
                            <Plus class="w-4 h-4" /> Thêm nguyên liệu
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Hạn giao hàng dự kiến (SLA)</label>
                            <input v-model="poForm.delivery_due_date" type="datetime-local" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Ghi chú giao nhận</label>
                            <input v-model="poForm.notes" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" placeholder="Yêu cầu đóng gói, giờ hạ hàng..." />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t border-slate-800 mt-6">
                        <button type="button" @click="showPoModal = false" class="px-4 py-2 border border-slate-800 text-sm font-semibold rounded-lg text-slate-400 hover:text-slate-200 transition-colors">
                            Hủy bỏ
                        </button>
                        <button type="submit" :disabled="poForm.processing" class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-500 text-white rounded-lg text-sm font-semibold hover:from-emerald-500 hover:to-teal-400 transition-all animate-shimmer">
                            {{ poForm.processing ? 'Đang gửi...' : 'Gửi đặt đơn PO' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Verify & Deliver Dual-Verification Modal -->
        <div v-if="showVerifyModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-3xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 bg-slate-950 flex items-center justify-between border-b border-slate-800">
                    <div>
                        <h3 class="font-bold text-lg text-slate-200">Đối soát kiểm đếm & Nhận hàng</h3>
                        <p class="text-[10px] text-slate-500 mt-0.5">Mã PO: {{ selectedPo?.po_number }} • Nhà cung cấp: {{ selectedPo?.supplier_name }}</p>
                    </div>
                    <button @click="showVerifyModal = false" class="p-1 text-slate-400 hover:text-slate-200 rounded-md hover:bg-slate-800 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitVerification" class="p-6 space-y-6">
                    <!-- Check alert box if mismatch is detected -->
                    <div v-if="hasMismatch" class="bg-rose-950/40 border border-rose-900 text-rose-300 p-4 rounded-xl flex gap-3 animate-pulse">
                        <AlertTriangle class="w-5 h-5 shrink-0 mt-0.5" />
                        <div class="text-xs space-y-1">
                            <h4 class="font-bold">CẢNH BÁO: Phát hiện sai lệch đối soát chéo!</h4>
                            <p class="leading-relaxed">Số lượng thực tế hoặc đơn giá hóa đơn không khớp với niêm yết ban đầu. Khi xác nhận, hệ thống sẽ **ĐÓNG BĂNG giao dịch**, khóa cập nhật kho tự động, ghi vết kiểm toán gian lận và gửi cảnh báo đỏ trực tiếp đến Owner.</p>
                        </div>
                    </div>

                    <!-- Items Verification Table -->
                    <div class="border border-slate-800 rounded-xl overflow-hidden">
                        <table class="w-full text-xs text-left text-slate-300">
                            <thead class="bg-slate-950/50 text-slate-400 uppercase text-[10px] border-b border-slate-800">
                                <tr>
                                    <th class="px-4 py-3">Tên nguyên liệu</th>
                                    <th class="px-4 py-3 text-center">Đặt (PO)</th>
                                    <th class="px-4 py-3 w-28">Thực nhận</th>
                                    <th class="px-4 py-3 text-center">Giá niêm yết</th>
                                    <th class="px-4 py-3 w-32">Giá hóa đơn</th>
                                    <th class="px-4 py-3 text-center">Khớp</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/40">
                                <tr v-for="(item, idx) in verifyDiscrepancies" :key="idx" class="hover:bg-slate-900/10">
                                    <td class="px-4 py-3 font-semibold">{{ item.ingredient_name }}</td>
                                    <td class="px-4 py-3 text-center font-semibold text-slate-400">{{ item.quantity_ordered }}</td>
                                    <td class="px-4 py-3">
                                        <input 
                                            v-model="item.quantity_received" 
                                            @input="updateQuantityReceived(idx, ($event.target as HTMLInputElement).value)"
                                            type="number" 
                                            step="0.001"
                                            class="w-full bg-slate-950 border border-slate-800 rounded px-2 py-1 text-xs focus:outline-none focus:border-emerald-500" 
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-center font-semibold text-slate-400">{{ Number(item.price_per_unit).toLocaleString('vi-VN') }}đ</td>
                                    <td class="px-4 py-3">
                                        <div class="relative flex items-center">
                                            <input 
                                                v-model="item.invoice_price" 
                                                @input="updateInvoicePrice(idx, ($event.target as HTMLInputElement).value)"
                                                type="number" 
                                                class="w-full bg-slate-950 border border-slate-800 rounded px-2 py-1 text-xs focus:outline-none focus:border-emerald-500" 
                                            />
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span v-if="!item.mismatch" class="text-emerald-400 bg-emerald-950/40 border border-emerald-900 p-1 rounded-full inline-block">
                                            <Check class="w-3.5 h-3.5" />
                                        </span>
                                        <span v-else class="text-rose-400 bg-rose-950/40 border border-rose-900 p-1 rounded-full inline-block animate-bounce">
                                            <X class="w-3.5 h-3.5" />
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Invoice Upload -->
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Đính kèm hóa đơn đối chiếu (Hóa đơn giấy/điện tử) <span class="text-rose-500">*</span></label>
                        <div class="border border-dashed border-slate-800 rounded-xl p-4 flex flex-col items-center justify-center bg-slate-950/50 hover:bg-slate-950/80 transition-colors relative min-h-[120px]">
                            <div v-if="loadingOcr" class="flex flex-col items-center justify-center space-y-2">
                                <RefreshCw class="w-8 h-8 text-emerald-400 animate-spin" />
                                <span class="text-xs text-emerald-400 font-bold animate-pulse">AI OCR: Đang phân tích và quét dữ liệu hóa đơn...</span>
                            </div>
                            <template v-else>
                                <Upload class="w-8 h-8 text-slate-500 mb-2" />
                                <span class="text-xs text-slate-400 font-semibold">{{ verifyForm.invoice_file ? verifyForm.invoice_file.name : 'Nhấp để chọn tệp chứng từ hoặc kéo thả vào đây' }}</span>
                                <span class="text-[10px] text-slate-500 mt-1">Hỗ trợ JPG, PNG, PDF tối đa 4MB. Tự động quét và điền dữ liệu bằng AI.</span>
                                <input type="file" @change="handleFileUpload" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                            </template>
                        </div>
                    </div>

                    <!-- Rating and feedback -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-950 p-4 rounded-xl border border-slate-800">
                        <div class="col-span-1">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Đánh giá nhà cung cấp (1-5★)</label>
                            <select v-model="verifyForm.rating" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500">
                                <option :value="5">5 ★★★★★ (Xuất sắc)</option>
                                <option :value="4">4 ★★★★☆ (Tốt)</option>
                                <option :value="3">3 ★★★☆☆ (Trung bình)</option>
                                <option :value="2">2 ★★☆☆☆ (Kém)</option>
                                <option :value="1">1 ★☆☆☆☆ (Rất kém)</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Ghi chú chất lượng hàng hóa / vận chuyển</label>
                            <input v-model="verifyForm.rating_notes" type="text" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" placeholder="Rau tươi sạch, giao đúng giờ..." />
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end gap-2 pt-4 border-t border-slate-800 mt-6">
                        <button type="button" @click="showVerifyModal = false" class="px-4 py-2 border border-slate-800 text-sm font-semibold rounded-lg text-slate-400 hover:text-slate-200 transition-colors">
                            Hủy bỏ
                        </button>
                        <button type="submit" :disabled="verifyForm.processing" class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-500 text-white rounded-lg text-sm font-semibold hover:from-emerald-500 hover:to-teal-400 transition-all">
                            {{ verifyForm.processing ? 'Đang gửi...' : (hasMismatch ? 'Xác nhận Đóng băng đơn hàng' : 'Hoàn tất bàn giao nhập kho') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</template>

<style scoped>
.animate-shimmer {
    background-size: 200% auto;
    animation: shine 1.5s linear infinite;
}

@keyframes shine {
    to { background-position: 200% center; }
}
</style>
