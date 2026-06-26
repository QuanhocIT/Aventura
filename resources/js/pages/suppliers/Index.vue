<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { 
    Plus, Edit2, Trash2, ShoppingBag, CheckCircle, 
    AlertTriangle, Sparkles, TrendingUp, TrendingDown,
    FileText, Upload, RefreshCw, X, Check, ArrowLeftRight, History,
    Gauge, BarChart3, Package, Star, Award, ShieldCheck
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
const activeTab = ref('list'); // 'list' | 'cockpit' | 'pos' | 'analytics' | 'sla' | 'transfers'

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

// Cockpit state
const cockpitRecommendations = ref<any[]>([]);
const loadingCockpit = ref(false);
const selectedCockpitIds = ref<number[]>([]);

const fetchCockpitData = async () => {
    loadingCockpit.value = true;
    try {
        const res = await fetch(route('suppliers.replenish-cockpit'));
        const data = await res.json();
        cockpitRecommendations.value = data.recommendations || [];
        // By default, select all recommendations that have a supplier
        selectedCockpitIds.value = cockpitRecommendations.value
            .filter(r => r.optimal_supplier)
            .map(r => r.ingredient_id);
    } catch (e) {
        console.error(e);
    } finally {
        loadingCockpit.value = false;
    }
};

const toggleSelectAllCockpit = () => {
    const validRecs = cockpitRecommendations.value.filter(r => r.optimal_supplier);
    if (selectedCockpitIds.value.length === validRecs.length) {
        selectedCockpitIds.value = [];
    } else {
        selectedCockpitIds.value = validRecs.map(r => r.ingredient_id);
    }
};

const submitBulkDraftPo = () => {
    if (selectedCockpitIds.value.length === 0) return;

    const itemsToSend = cockpitRecommendations.value
        .filter(r => selectedCockpitIds.value.includes(r.ingredient_id))
        .map(r => ({
            ingredient_id: r.ingredient_id,
            quantity: r.suggested_quantity,
            supplier_id: r.optimal_supplier?.id,
            price: r.optimal_price
        }));

    const missingSupplier = itemsToSend.some(it => !it.supplier_id);
    if (missingSupplier) {
        alert("Không thể tạo đơn PO cho các mặt hàng chưa xác định nhà cung cấp.");
        return;
    }

    if (confirm(`Bạn có chắc chắn muốn tự động tạo PO nháp cho ${itemsToSend.length} mặt hàng đã chọn?`)) {
        router.post(route('suppliers.draft-po-bulk'), {
            items: itemsToSend
        }, {
            onSuccess: () => {
                activeTab.value = 'pos';
                selectedCockpitIds.value = [];
            }
        });
    }
};

// SLA Dashboard state
const slaDashboardData = ref<any>(null);
const loadingSlaDashboard = ref(false);

const fetchSlaDashboard = async () => {
    loadingSlaDashboard.value = true;
    selectedSupplier.value = null;
    try {
        const res = await fetch(route('suppliers.sla-dashboard'));
        slaDashboardData.value = await res.json();
    } catch (e) {
        console.error(e);
    } finally {
        loadingSlaDashboard.value = false;
    }
};

const getSupplierGrade = (onTime: number, accuracy: number, rating: number) => {
    const normalizedRating = (rating / 5.0) * 100;
    const score = (onTime * 0.4) + (accuracy * 0.4) + (normalizedRating * 0.2);
    
    if (score >= 90) return { label: 'Hạng A (Xuất sắc)', color: 'bg-emerald-500 text-white' };
    if (score >= 75) return { label: 'Hạng B (Tốt)', color: 'bg-blue-500 text-white' };
    if (score >= 55) return { label: 'Hạng C (Trung bình)', color: 'bg-amber-500 text-white' };
    return { label: 'Hạng D (Kém)', color: 'bg-rose-500 text-white' };
};

// SLA state
const slaData = ref<any>(null);
const loadingSla = ref(false);

const fetchSla = async () => {
    if (!selectedSupplier.value) {
return;
}

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
    if (!selectedPo.value || !verifyForm.items.length) {
return [];
}
    
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
    if (!selectedSupplier.value || !selectedIngredient.value) {
return;
}

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

    if (!input.files || !input.files[0] || !selectedPo.value) {
return;
}
    
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

// Internal Transfers State & Methods
const transferRecommendations = ref<any[]>([]);
const transferLogs = ref<any[]>([]);
const branches = ref<any[]>([]);
const inventories = ref<any[]>([]);
const loadingTransfers = ref(false);
const showManualTransferModal = ref(false);

const transferForm = useForm({
    from_branch_id: '',
    to_branch_id: '',
    ingredient_id: '',
    quantity: 0,
    notes: '',
});

const fetchTransfers = async () => {
    loadingTransfers.value = true;

    try {
        const [recRes, logRes] = await Promise.all([
            fetch(route('inventory.transfer-recommendations')),
            fetch(route('inventory.internal-transfers.list'))
        ]);
        const recData = await recRes.json();
        const logData = await logRes.json();
        transferRecommendations.value = recData.recommendations || [];
        branches.value = recData.branches || [];
        inventories.value = recData.inventories || [];
        transferLogs.value = logData.transfers || [];
    } catch (e) {
        console.error("Failed to fetch transfer data", e);
    } finally {
        loadingTransfers.value = false;
    }
};

const executeTransfer = (rec: any) => {
    if (!confirm(`Bạn có chắc chắn muốn thực hiện lệnh luân chuyển kho nội bộ: chuyển ${rec.suggested_quantity} ${rec.unit_symbol} "${rec.ingredient_name}" từ chi nhánh "${rec.from_branch_name}" sang "${rec.to_branch_name}"?`)) {
        return;
    }
    
    router.post(route('inventory.internal-transfers'), {
        from_branch_id: rec.from_branch_id,
        to_branch_id: rec.to_branch_id,
        ingredient_id: rec.ingredient_id,
        quantity: rec.suggested_quantity,
        notes: rec.reason
    }, {
        onSuccess: () => {
            fetchTransfers();
        }
    });
};

const openManualTransferModal = () => {
    transferForm.reset();

    if (branches.value.length > 0) {
        transferForm.from_branch_id = branches.value[0].id;
        transferForm.to_branch_id = branches.value[1]?.id || '';
    }

    if (props.ingredients.length > 0) {
        transferForm.ingredient_id = props.ingredients[0].id;
    }

    showManualTransferModal.value = true;
};

const submitManualTransfer = () => {
    transferForm.post(route('inventory.internal-transfers'), {
        onSuccess: () => {
            showManualTransferModal.value = false;
            transferForm.reset();
            fetchTransfers();
        }
    });
};

const selectedSourceInventory = computed(() => {
    if (!transferForm.from_branch_id || !transferForm.ingredient_id) {
return null;
}

    return inventories.value.find(
        (i) => i.branch_id === Number(transferForm.from_branch_id) && i.ingredient_id === Number(transferForm.ingredient_id)
    ) || null;
});

const selectedIngredientDetail = computed(() => {
    if (!transferForm.ingredient_id) {
return null;
}

    return props.ingredients.find((i) => i.id === Number(transferForm.ingredient_id)) || null;
});

const sourceStockOnHand = computed(() => {
    return selectedSourceInventory.value ? Number(selectedSourceInventory.value.quantity_on_hand) : 0;
});

const transferQuantityWarning = computed(() => {
    if (!transferForm.quantity || transferForm.quantity <= 0) {
return null;
}

    const qty = Number(transferForm.quantity);
    const stock = sourceStockOnHand.value;
    
    if (qty > stock) {
        return {
            type: 'error',
            message: `Chi nhánh xuất không đủ tồn kho thực tế để chuyển (Tồn hiện có: ${stock.toFixed(3)}).`
        };
    }
    
    const minStock = selectedIngredientDetail.value ? Number(selectedIngredientDetail.value.min_stock_level) : 0;

    if (stock - qty < minStock) {
        return {
            type: 'warning',
            message: `Sau khi chuyển, tồn kho tại chi nhánh nguồn (${(stock - qty).toFixed(3)}) sẽ tụt dưới mức tồn tối thiểu (${minStock.toFixed(3)}).`
        };
    }
    
    return null;
});
</script>

<template>
    <Head title="Quản lý Nhà cung cấp" />

    <div class="flex flex-col gap-6 p-4 lg:p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-500">
                    <ShoppingBag class="size-6" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Portal Chuỗi cung ứng & Nhà cung cấp</h1>
                    <p class="text-sm text-muted-foreground">Quản lý danh sách đối tác cung ứng, đặt hàng hàng ngày, đối soát chéo 3 bên triệt tiêu gian lận.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <Button 
                    v-if="isOwner"
                    @click="triggerAutoReplenish" 
                    class="h-9 text-xs bg-gradient-to-r from-purple-600 to-indigo-500 hover:from-purple-500 hover:to-indigo-400 text-white font-semibold flex items-center gap-1.5 shadow-md"
                >
                    <Sparkles class="w-4 h-4" />
                    AI Đề xuất Nhập hàng
                </Button>
                <Button 
                    @click="openAddModal" 
                    class="h-9 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-semibold"
                >
                    <Plus class="w-4 h-4 mr-1.5" />
                    Thêm đối tác
                </Button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-1 rounded-xl border border-border bg-muted p-1 self-start flex-wrap">
            <button @click="activeTab = 'list'"
                class="cursor-pointer rounded-lg px-4 py-1.5 text-xs font-semibold transition-all flex items-center gap-1.5"
                :class="activeTab === 'list' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'">
                Danh sách đối tác
            </button>
            <button @click="activeTab = 'cockpit'; fetchCockpitData()"
                class="cursor-pointer rounded-lg px-4 py-1.5 text-xs font-semibold transition-all flex items-center gap-1.5"
                :class="activeTab === 'cockpit' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'">
                <Gauge class="w-3.5 h-3.5" />
                Cockpit Nhập hàng
            </button>
            <button @click="activeTab = 'pos'"
                class="cursor-pointer rounded-lg px-4 py-1.5 text-xs font-semibold transition-all flex items-center gap-1.5"
                :class="activeTab === 'pos' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'">
                Nhật ký đặt hàng (PO)
            </button>
            <button @click="activeTab = 'analytics'"
                class="cursor-pointer rounded-lg px-4 py-1.5 text-xs font-semibold transition-all flex items-center gap-1.5"
                :class="activeTab === 'analytics' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'">
                AI Phân tích biến động giá
            </button>
            <button @click="activeTab = 'sla'"
                class="cursor-pointer rounded-lg px-4 py-1.5 text-xs font-semibold transition-all flex items-center gap-1.5"
                :class="activeTab === 'sla' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'">
                Báo cáo SLA & Đánh giá
            </button>
            <button @click="activeTab = 'sla-dashboard'; fetchSlaDashboard()"
                class="cursor-pointer rounded-lg px-4 py-1.5 text-xs font-semibold transition-all flex items-center gap-1.5"
                :class="activeTab === 'sla-dashboard' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'">
                <Award class="w-3.5 h-3.5" />
                Bảng xếp hạng NCC
            </button>
            <button @click="activeTab = 'transfers'; fetchTransfers()"
                class="cursor-pointer rounded-lg px-4 py-1.5 text-xs font-semibold transition-all flex items-center gap-1.5"
                :class="activeTab === 'transfers' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'">
                <ArrowLeftRight class="w-3.5 h-3.5" />
                AI Điều phối Liên chi nhánh
            </button>
        </div>

        <!-- Tab Content: Suppliers List -->
        <div v-if="activeTab === 'list'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <Card 
                v-for="sup in suppliers" 
                :key="sup.id" 
                class="hover:shadow-md transition-all flex flex-col justify-between"
            >
                <CardHeader class="pb-3 flex flex-row items-start justify-between space-y-0">
                    <div class="space-y-1">
                        <CardTitle class="text-base font-bold">{{ sup.name }}</CardTitle>
                        <div class="pt-0.5">
                            <span :class="['text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider', sup.status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400']">
                                {{ sup.status === 'active' ? 'Đang hoạt động' : 'Tạm khóa' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-1 shrink-0">
                        <Button variant="ghost" size="icon" @click="openEditModal(sup)" class="h-8 w-8 text-muted-foreground hover:text-amber-500">
                            <Edit2 class="w-4 h-4" />
                        </Button>
                        <Button variant="ghost" size="icon" @click="deleteSupplier(sup)" class="h-8 w-8 text-muted-foreground hover:text-rose-500">
                            <Trash2 class="w-4 h-4" />
                        </Button>
                    </div>
                </CardHeader>

                <CardContent class="pb-4">
                    <div class="text-xs text-muted-foreground space-y-1.5">
                        <p v-if="sup.contact_name"><strong>Đại diện:</strong> {{ sup.contact_name }}</p>
                        <p v-if="sup.phone"><strong>Điện thoại:</strong> {{ sup.phone }}</p>
                        <p v-if="sup.email"><strong>Email:</strong> {{ sup.email }}</p>
                        <p v-if="sup.address"><strong>Địa chỉ:</strong> {{ sup.address }}</p>
                    </div>
                    
                    <div class="flex gap-2 pt-4 border-t border-border mt-4">
                        <Button 
                            @click="openPoModal(sup)" 
                            variant="outline"
                            class="w-full h-8 text-xs border-emerald-200 text-emerald-600 hover:bg-emerald-50 dark:border-emerald-850 dark:text-emerald-400 font-semibold"
                        >
                            <ShoppingBag class="w-3.5 h-3.5 mr-1.5" />
                            Đặt hàng hàng ngày
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Empty State -->
            <div v-if="suppliers.length === 0" class="col-span-full py-16 text-center border border-dashed border-border rounded-2xl bg-muted/20">
                <AlertTriangle class="w-12 h-12 text-muted-foreground mx-auto mb-3" />
                <p class="text-muted-foreground font-medium text-sm">Chưa có nhà cung cấp nào được cấu hình.</p>
                <Button @click="openAddModal" class="mt-4 bg-emerald-600 text-white hover:bg-emerald-700">Thêm đối tác ngay</Button>
            </div>
        </div>

        <!-- Tab Content: Supply Chain Cockpit -->
        <div v-if="activeTab === 'cockpit'" class="space-y-6">
            <!-- Cockpit Header -->
            <div class="flex items-center justify-between border-b pb-4">
                <div>
                    <h3 class="text-lg font-bold flex items-center gap-2">
                        <Gauge class="w-5 h-5 text-indigo-500" />
                        Cockpit Tự Động Nhập Hàng
                    </h3>
                    <p class="text-xs text-muted-foreground mt-0.5">AI quét kho tự động, phát hiện tồn kho dưới ngưỡng và soạn PO nháp gửi nhà cung cấp tối ưu nhất (giá thấp nhất).</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button 
                        @click="fetchCockpitData" 
                        variant="outline"
                        :disabled="loadingCockpit"
                        class="h-9 text-xs font-semibold flex items-center gap-1.5"
                    >
                        <RefreshCw class="w-4 h-4" :class="loadingCockpit ? 'animate-spin' : ''" />
                        Quét lại kho
                    </Button>
                    <Button 
                        v-if="selectedCockpitIds.length > 0"
                        @click="submitBulkDraftPo" 
                        class="h-9 text-xs bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold flex items-center gap-1.5 shadow-lg"
                    >
                        <Package class="w-4 h-4" />
                        Tạo PO nháp ({{ selectedCockpitIds.length }} mặt hàng)
                    </Button>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loadingCockpit" class="flex flex-col items-center justify-center py-20 space-y-3">
                <RefreshCw class="w-8 h-8 text-indigo-500 animate-spin" />
                <p class="text-muted-foreground text-sm font-medium">Đang quét tồn kho và tìm nhà cung cấp tối ưu...</p>
            </div>

            <template v-else>
                <!-- Summary Stats -->
                <div v-if="cockpitRecommendations.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card class="p-4 border-l-4 border-l-rose-500 bg-gradient-to-r from-rose-50/50 to-transparent dark:from-rose-950/20">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">Mặt hàng thiếu hụt</p>
                                <p class="text-2xl font-extrabold text-rose-600 dark:text-rose-400 mt-1">{{ cockpitRecommendations.length }}</p>
                            </div>
                            <AlertTriangle class="w-8 h-8 text-rose-400 opacity-60" />
                        </div>
                    </Card>
                    <Card class="p-4 border-l-4 border-l-indigo-500 bg-gradient-to-r from-indigo-50/50 to-transparent dark:from-indigo-950/20">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">Đã chọn tạo PO</p>
                                <p class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ selectedCockpitIds.length }}</p>
                            </div>
                            <ShoppingBag class="w-8 h-8 text-indigo-400 opacity-60" />
                        </div>
                    </Card>
                    <Card class="p-4 border-l-4 border-l-emerald-500 bg-gradient-to-r from-emerald-50/50 to-transparent dark:from-emerald-950/20">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">Có NCC tối ưu</p>
                                <p class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ cockpitRecommendations.filter(r => r.optimal_supplier).length }}</p>
                            </div>
                            <CheckCircle class="w-8 h-8 text-emerald-400 opacity-60" />
                        </div>
                    </Card>
                </div>

                <!-- Recommendations Table -->
                <Card class="overflow-hidden">
                    <div class="p-4 border-b flex items-center justify-between">
                        <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                            <Sparkles class="w-4 h-4 text-indigo-500" />
                            Danh sách đề xuất nhập hàng tự động
                        </h4>
                        <button 
                            v-if="cockpitRecommendations.length > 0"
                            @click="toggleSelectAllCockpit"
                            class="text-xs text-indigo-600 dark:text-indigo-400 font-bold hover:underline cursor-pointer"
                        >
                            {{ selectedCockpitIds.length === cockpitRecommendations.filter(r => r.optimal_supplier).length ? 'Bỏ chọn tất cả' : 'Chọn tất cả' }}
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-foreground">
                            <thead class="bg-muted/40 text-muted-foreground uppercase text-[10px] font-semibold tracking-wider border-b border-border">
                                <tr>
                                    <th class="px-4 py-3 w-10">
                                        <input 
                                            type="checkbox" 
                                            :checked="selectedCockpitIds.length === cockpitRecommendations.filter(r => r.optimal_supplier).length && cockpitRecommendations.length > 0"
                                            @change="toggleSelectAllCockpit"
                                            class="rounded border-input accent-indigo-600"
                                        />
                                    </th>
                                    <th class="px-4 py-3">Nguyên liệu</th>
                                    <th class="px-4 py-3 text-center">Tồn hiện tại</th>
                                    <th class="px-4 py-3 text-center">Tồn tối thiểu</th>
                                    <th class="px-4 py-3 text-center">Thiếu hụt</th>
                                    <th class="px-4 py-3 text-center">SL đề xuất (×1.2)</th>
                                    <th class="px-4 py-3">NCC tối ưu</th>
                                    <th class="px-4 py-3 text-right">Giá tối ưu</th>
                                    <th class="px-4 py-3 text-right">Thành tiền dự kiến</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr 
                                    v-for="rec in cockpitRecommendations" 
                                    :key="rec.ingredient_id"
                                    class="hover:bg-muted/10 transition-colors"
                                    :class="selectedCockpitIds.includes(rec.ingredient_id) ? 'bg-indigo-50/30 dark:bg-indigo-950/10' : ''"
                                >
                                    <td class="px-4 py-3">
                                        <input 
                                            type="checkbox" 
                                            :value="rec.ingredient_id"
                                            v-model="selectedCockpitIds"
                                            :disabled="!rec.optimal_supplier"
                                            class="rounded border-input accent-indigo-600"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>
                                            <p class="font-bold text-sm">{{ rec.ingredient_name }}</p>
                                            <p class="text-[10px] text-muted-foreground font-mono">{{ rec.sku }}</p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-semibold" :class="rec.current_stock <= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400'">
                                            {{ Number(rec.current_stock).toFixed(2) }}
                                        </span>
                                        <span class="text-[10px] text-muted-foreground ml-0.5">{{ rec.unit_symbol }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-muted-foreground font-semibold">
                                        {{ Number(rec.min_stock).toFixed(2) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50">
                                            -{{ Number(rec.deficit).toFixed(2) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center font-extrabold text-indigo-600 dark:text-indigo-400">
                                        {{ Number(rec.suggested_quantity).toFixed(2) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span v-if="rec.optimal_supplier" class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                            {{ rec.optimal_supplier.name }}
                                        </span>
                                        <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50">
                                            <AlertTriangle class="w-3 h-3 mr-1" />
                                            Chưa có NCC
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold">
                                        <span v-if="rec.optimal_price">{{ Number(rec.optimal_price).toLocaleString('vi-VN') }}đ</span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold">
                                        <span v-if="rec.optimal_price">{{ (Number(rec.optimal_price) * Number(rec.suggested_quantity)).toLocaleString('vi-VN') }}đ</span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                </tr>
                                <tr v-if="cockpitRecommendations.length === 0">
                                    <td colspan="9" class="text-center py-16">
                                        <CheckCircle class="w-10 h-10 text-emerald-500 mx-auto mb-3 opacity-60" />
                                        <p class="text-muted-foreground font-medium text-sm">Toàn bộ kho hàng đang ở mức an toàn.</p>
                                        <p class="text-muted-foreground text-xs mt-1">Không có mặt hàng nào cần nhập thêm.</p>
                                    </td>
                                </tr>
                            </tbody>
                            <!-- Total row -->
                            <tfoot v-if="selectedCockpitIds.length > 0">
                                <tr class="bg-indigo-50/50 dark:bg-indigo-950/20 border-t-2 border-indigo-200 dark:border-indigo-900">
                                    <td colspan="8" class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                        Tổng giá trị đơn PO nháp:
                                    </td>
                                    <td class="px-4 py-3 text-right font-extrabold text-indigo-700 dark:text-indigo-300 text-base">
                                        {{ cockpitRecommendations
                                            .filter(r => selectedCockpitIds.includes(r.ingredient_id) && r.optimal_price)
                                            .reduce((sum, r) => sum + (Number(r.optimal_price) * Number(r.suggested_quantity)), 0)
                                            .toLocaleString('vi-VN') }}đ
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </Card>
            </template>
        </div>

        <!-- Tab Content: PO logs -->
        <Card v-if="activeTab === 'pos'" class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-foreground">
                    <thead class="bg-muted/40 text-muted-foreground uppercase text-[10px] font-semibold tracking-wider border-b border-border">
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
                    <tbody class="divide-y divide-border">
                        <tr v-for="po in purchaseOrders" :key="po.id" class="hover:bg-muted/10 transition-colors">
                            <td class="px-6 py-4 font-mono font-bold text-emerald-600 dark:text-emerald-450">{{ po.po_number }}</td>
                            <td class="px-6 py-4 font-semibold">{{ po.supplier_name }}</td>
                            <td class="px-6 py-4 text-xs text-muted-foreground">{{ po.created_by_name }}</td>
                            <td class="px-6 py-4 font-semibold">{{ Number(po.total_amount).toLocaleString('vi-VN') }}đ</td>
                            <td class="px-6 py-4">
                                <span v-if="po.invoice_total_amount > 0" class="font-semibold">
                                    {{ Number(po.invoice_total_amount).toLocaleString('vi-VN') }}đ
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-0.5">
                                    <span v-if="po.payment_status === 'unpaid'" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-muted text-muted-foreground border">
                                        Chưa thanh toán
                                    </span>
                                    <span v-else-if="po.payment_status === 'escrow_locked'" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50 animate-pulse">
                                        Ký quỹ (Khóa)
                                    </span>
                                    <span v-else-if="po.payment_status === 'paid'" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50">
                                        Giải ngân
                                    </span>
                                    <span v-else-if="po.payment_status === 'refunded'" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-450 border border-rose-200 dark:border-rose-900/50">
                                        Đã hoàn trả
                                    </span>
                                    <p v-if="po.escrow_transaction_id" class="text-[9px] text-muted-foreground font-mono mt-0.5 leading-none">{{ po.escrow_transaction_id }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="po.status === 'pending_approval'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50">
                                    Chờ phê duyệt
                                </span>
                                <span v-else-if="po.status === 'approved'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50">
                                    Chờ nhận đơn
                                </span>
                                <span v-else-if="po.status === 'preparing'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-400 border border-purple-200 dark:border-purple-900/50">
                                    Chuẩn bị hàng
                                </span>
                                <span v-else-if="po.status === 'shipping'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-900/50">
                                    Đang giao hàng
                                </span>
                                <span v-else-if="po.status === 'delivered'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50">
                                    Đã hạ hàng
                                </span>
                                <span v-else-if="po.status === 'frozen'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-450 border border-rose-200 dark:border-rose-900/50 animate-pulse">
                                    ĐÓNG BĂNG
                                </span>
                                <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-muted text-muted-foreground border">
                                    Đã hủy
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-muted-foreground">{{ po.created_at }}</td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <!-- Owner Approval -->
                                <Button 
                                    v-if="po.status === 'pending_approval' && isOwner" 
                                    @click="approvePo(po)"
                                    size="sm"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold h-7 text-xs px-2.5"
                                >
                                    Duyệt đặt
                                </Button>
                                <!-- Warehouse verification -->
                                <Button 
                                    v-if="['approved', 'preparing', 'shipping', 'delivered', 'frozen'].includes(po.status)" 
                                    @click="openVerifyModal(po)"
                                    variant="outline"
                                    size="sm"
                                    class="h-7 text-xs px-2.5 border-emerald-200 text-emerald-600 hover:bg-emerald-50 dark:border-emerald-850 dark:text-emerald-400 font-bold"
                                >
                                    Đối soát & Giao nhận
                                </Button>
                                <!-- Escrow Actions -->
                                <template v-if="po.payment_status === 'escrow_locked' && isOwner">
                                    <Button 
                                        @click="releaseEscrow(po)"
                                        size="sm"
                                        class="h-7 text-xs px-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold"
                                        title="Giải ngân tiền thầu thầu ký quỹ cho nhà cung cấp"
                                    >
                                        Giải ngân
                                    </Button>
                                    <Button 
                                        @click="refundEscrow(po)"
                                        size="sm"
                                        class="h-7 text-xs px-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold"
                                        title="Hoàn tiền ký quỹ về tài khoản nhà hàng"
                                    >
                                        Hoàn trả
                                    </Button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="purchaseOrders.length === 0">
                            <td colspan="9" class="text-center py-12 text-muted-foreground font-medium">Chưa phát sinh đơn đặt hàng PO nào.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Tab Content: AI Price Analytics -->
        <div v-if="activeTab === 'analytics'" class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Left panel: Select supplier/material -->
            <Card class="lg:col-span-1 p-5 space-y-4">
                <div>
                    <Label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">1. Chọn nhà cung cấp</Label>
                    <select 
                        v-model="selectedSupplier" 
                        @change="selectSupplierForAnalytics(selectedSupplier)"
                        class="w-full bg-background border border-input rounded-xl px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                    >
                        <option :value="null" disabled>-- Chọn đối tác --</option>
                        <option v-for="sup in suppliers" :key="sup.id" :value="sup">{{ sup.name }}</option>
                    </select>
                </div>

                <div v-if="selectedSupplier">
                    <Label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">2. Nguyên vật liệu cung ứng</Label>
                    <div class="space-y-1.5 max-h-80 overflow-y-auto">
                        <button 
                            v-for="ing in ingredients.filter(i => i.supplier_id === selectedSupplier.id)"
                            :key="ing.id"
                            @click="selectIngredientForAnalytics(ing)"
                            :class="['w-full text-left px-3 py-2 rounded-lg text-xs font-medium transition-colors flex items-center justify-between border', selectedIngredient?.id === ing.id ? 'bg-emerald-50 border-emerald-250 text-emerald-700 dark:bg-emerald-950/30 dark:border-emerald-800 dark:text-emerald-400' : 'bg-background hover:bg-muted text-muted-foreground border-transparent']"
                        >
                            <span>{{ ing.name }}</span>
                            <span class="text-muted-foreground text-[10px] font-mono">{{ ing.sku }}</span>
                        </button>
                        <p v-if="ingredients.filter(i => i.supplier_id === selectedSupplier.id).length === 0" class="text-xs text-muted-foreground py-4 text-center">
                            Không tìm thấy nguyên liệu của nhà cung cấp này.
                        </p>
                    </div>
                </div>
            </Card>

            <!-- Right panel: Trend analysis -->
            <Card class="lg:col-span-3 p-6 flex flex-col justify-between">
                <div v-if="loadingAnalytics" class="flex-1 flex flex-col items-center justify-center py-20 space-y-3">
                    <RefreshCw class="w-8 h-8 text-emerald-500 animate-spin" />
                    <p class="text-muted-foreground text-sm font-medium">Đang gọi Python AI microservice phân tích dữ liệu...</p>
                </div>

                <div v-else-if="analyticsData" class="space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b pb-4">
                        <div>
                            <h3 class="text-xl font-bold">Xu hướng biến động giá: {{ selectedIngredient?.name }}</h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Nhà cung cấp: {{ selectedSupplier?.name }}</p>
                        </div>
                        <div class="flex items-center gap-2 mt-2 sm:mt-0">
                            <span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border', analyticsData.trend === 'upward' ? 'bg-rose-50 text-rose-700 border-rose-250 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-900/50' : analyticsData.trend === 'downward' ? 'bg-emerald-50 text-emerald-700 border-emerald-250 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900/50' : 'bg-muted text-muted-foreground border-border']">
                                <TrendingUp v-if="analyticsData.trend === 'upward'" class="w-4 h-4" />
                                <TrendingDown v-if="analyticsData.trend === 'downward'" class="w-4 h-4" />
                                Biên độ: {{ analyticsData.percentage_change }}%
                            </span>
                        </div>
                    </div>

                    <!-- AI Box -->
                    <div class="bg-emerald-50/50 border border-emerald-200 dark:bg-emerald-950/20 dark:border-emerald-900/60 rounded-xl p-4 flex gap-3.5">
                        <Sparkles class="w-6 h-6 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
                        <div>
                            <h4 class="text-sm font-bold text-emerald-750 dark:text-emerald-400">Khuyến nghị điều chỉnh giá vốn</h4>
                            <p class="text-xs text-slate-700 dark:text-slate-300 mt-1 leading-relaxed">{{ analyticsData.recommendation }}</p>
                        </div>
                    </div>

                    <!-- Chart -->
                    <div class="bg-muted/30 border p-4 rounded-xl">
                        <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-4">Lịch sử giá trung bình theo tháng</h4>
                        <div class="relative h-48 w-full flex items-end justify-around gap-4 pt-6">
                            <!-- simple grid lines -->
                            <div class="absolute inset-0 flex flex-col justify-between pointer-events-none text-[10px] text-muted-foreground/60 border-b">
                                <div class="border-b border-border/30 w-full h-0"></div>
                                <div class="border-b border-border/30 w-full h-0"></div>
                                <div class="border-b border-border/30 w-full h-0"></div>
                            </div>

                            <div 
                                v-for="(item, idx) in analyticsData.monthly_averages" 
                                :key="idx" 
                                class="flex-1 flex flex-col items-center justify-end h-full z-10 group"
                            >
                                <span class="opacity-0 group-hover:opacity-100 transition-opacity bg-popover text-popover-foreground text-[10px] font-bold px-2 py-0.5 rounded border shadow-lg absolute mb-16">
                                    {{ Number(item.price).toLocaleString('vi-VN') }}đ
                                </span>
                                <div 
                                    class="w-full max-w-[40px] bg-gradient-to-t from-emerald-600 to-teal-400 hover:from-emerald-500 hover:to-teal-300 transition-all rounded-t-md"
                                    :style="{ height: `${Math.max(15, Math.min(100, (item.price / Math.max(...analyticsData.monthly_averages.map((m: any) => m.price))) * 90))}%` }"
                                ></div>
                                <span class="text-[10px] text-muted-foreground font-medium mt-2">{{ item.period }}</span>
                            </div>

                            <p v-if="!analyticsData.monthly_averages || analyticsData.monthly_averages.length === 0" class="text-muted-foreground text-xs py-10 w-full text-center">
                                Chưa có đủ dữ liệu lịch sử giá của nhiều tháng.
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="flex-1 flex flex-col items-center justify-center py-24 text-muted-foreground">
                    <FileText class="w-12 h-12 mb-3 opacity-40" />
                    <p class="text-sm font-medium">Vui lòng chọn nhà cung cấp và nguyên liệu để phân tích biến động giá.</p>
                </div>
            </Card>
        </div>

        <!-- Tab Content: SLA Scorecard -->
        <div v-if="activeTab === 'sla'" class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Left panel: Select supplier -->
            <Card class="lg:col-span-1 p-5 space-y-4">
                <div>
                    <Label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">Chọn nhà cung cấp đối soát</Label>
                    <select 
                        v-model="selectedSupplier" 
                        @change="selectSupplierForSla(selectedSupplier)"
                        class="w-full bg-background border border-input rounded-xl px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                    >
                        <option :value="null" disabled>-- Chọn đối tác --</option>
                        <option v-for="sup in suppliers" :key="sup.id" :value="sup">{{ sup.name }}</option>
                    </select>
                </div>
            </Card>

            <!-- Right panel: SLA report -->
            <Card class="lg:col-span-3 p-6 flex flex-col justify-between">
                <div v-if="loadingSla" class="flex-1 flex flex-col items-center justify-center py-20 space-y-3">
                    <RefreshCw class="w-8 h-8 text-emerald-500 animate-spin" />
                    <p class="text-muted-foreground text-sm font-medium">Đang tính toán chỉ số SLA...</p>
                </div>

                <div v-else-if="slaData" class="space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b pb-4">
                        <div>
                            <h3 class="text-xl font-bold">Báo cáo hiệu suất (SLA): {{ slaData.supplier_name }}</h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Phân tích dựa trên {{ slaData.total_orders_analyzed }} đơn đặt hàng đã giao nhận.</p>
                        </div>
                        <div class="flex items-center gap-2 mt-2 sm:mt-0">
                            <span class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-250 rounded-full text-xs font-bold">
                                Điểm trung bình: {{ slaData.average_rating }} / 5.0 ★
                            </span>
                        </div>
                    </div>

                    <!-- SLA Indicators -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-muted/30 border p-4 rounded-xl space-y-2">
                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Tỷ lệ giao hàng đúng hạn (Punctuality)</span>
                                <span class="font-bold text-emerald-650 dark:text-emerald-400 text-sm">{{ slaData.on_time_rate }}%</span>
                            </div>
                            <div class="w-full bg-muted h-2 rounded-full overflow-hidden">
                                <div class="bg-emerald-500 h-full rounded-full transition-all" :style="{ width: `${slaData.on_time_rate}%` }"></div>
                            </div>
                        </div>

                        <div class="bg-muted/30 border p-4 rounded-xl space-y-2">
                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Độ chính xác đối soát chéo (Accuracy)</span>
                                <span class="font-bold text-teal-650 dark:text-teal-400 text-sm">{{ slaData.accuracy_rate }}%</span>
                            </div>
                            <div class="w-full bg-muted h-2 rounded-full overflow-hidden">
                                <div class="bg-teal-500 h-full rounded-full transition-all" :style="{ width: `${slaData.accuracy_rate}%` }"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Volatility details -->
                    <div class="bg-muted/20 border rounded-xl p-4 space-y-3">
                        <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Chỉ số biến động giá vật tư (Price Volatility Index)</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left text-foreground">
                                <thead class="bg-muted/40 text-muted-foreground border-b uppercase text-[10px] font-semibold tracking-wider">
                                    <tr>
                                        <th class="px-4 py-2">Tên vật tư</th>
                                        <th class="px-4 py-2 text-center">SKU</th>
                                        <th class="px-4 py-2 text-right">Giá hiện tại</th>
                                        <th class="px-4 py-2 text-center">Số lần thay đổi</th>
                                        <th class="px-4 py-2 text-right">Độ biến động (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in slaData.price_volatility" :key="item.sku" class="border-b border-border/50 hover:bg-muted/10">
                                        <td class="px-4 py-2 font-medium">{{ item.ingredient_name }}</td>
                                        <td class="px-4 py-2 text-center font-mono text-muted-foreground">{{ item.sku }}</td>
                                        <td class="px-4 py-2 text-right">{{ Number(item.current_price).toLocaleString('vi-VN') }}đ</td>
                                        <td class="px-4 py-2 text-center font-mono text-muted-foreground">{{ item.price_history_count }}</td>
                                        <td class="px-4 py-2 text-right font-mono font-bold" :class="item.volatility_percent > 10 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'">
                                            {{ item.volatility_percent }}%
                                        </td>
                                    </tr>
                                    <tr v-if="!slaData.price_volatility || slaData.price_volatility.length === 0">
                                        <td colspan="5" class="text-center py-4 text-muted-foreground">Không có dữ liệu lịch sử biến động giá.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Ratings -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Đánh giá & Phản hồi gần đây</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div v-for="rate in slaData.recent_ratings.slice(0, 4)" :key="rate.po_number" class="bg-muted/30 border p-3.5 rounded-xl space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-450">{{ rate.po_number }}</span>
                                    <span class="text-amber-500 text-xs font-bold">{{ '★'.repeat(rate.rating) + '☆'.repeat(5 - rate.rating) }}</span>
                                </div>
                                <p class="text-xs text-foreground italic">"{{ rate.rating_notes || 'Không có ghi chú thêm.' }}"</p>
                                <p class="text-[10px] text-muted-foreground text-right">{{ rate.delivered_at }}</p>
                            </div>
                            <p v-if="!slaData.recent_ratings || slaData.recent_ratings.length === 0" class="col-span-full text-center py-4 text-muted-foreground text-xs">Chưa có đánh giá sao nào cho nhà cung cấp này.</p>
                        </div>
                    </div>
                </div>

                <div v-else class="flex-1 flex flex-col items-center justify-center py-24 text-muted-foreground">
                    <FileText class="w-12 h-12 mb-3 opacity-40" />
                    <p class="text-sm font-medium">Vui lòng chọn nhà cung cấp để hiển thị báo cáo SLA.</p>
                </div>
            </Card>
        </div>

        <!-- Tab Content: Aggregate SLA Dashboard -->
        <div v-if="activeTab === 'sla-dashboard'" class="space-y-6">
            <!-- Dashboard Header -->
            <div class="flex items-center justify-between border-b pb-4">
                <div>
                    <h3 class="text-lg font-bold flex items-center gap-2">
                        <Award class="w-5 h-5 text-amber-500" />
                        Bảng Xếp Hạng Nhà Cung Cấp (SLA Dashboard)
                    </h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Chấm điểm tổng hợp dựa trên: Giao đúng hạn (40%), Độ chính xác (40%), Chất lượng đánh giá (20%).</p>
                </div>
                <Button 
                    @click="fetchSlaDashboard" 
                    variant="outline"
                    :disabled="loadingSlaDashboard"
                    class="h-9 text-xs font-semibold flex items-center gap-1.5"
                >
                    <RefreshCw class="w-4 h-4" :class="loadingSlaDashboard ? 'animate-spin' : ''" />
                    Làm mới dữ liệu
                </Button>
            </div>

            <!-- Loading -->
            <div v-if="loadingSlaDashboard" class="flex flex-col items-center justify-center py-20 space-y-3">
                <RefreshCw class="w-8 h-8 text-amber-500 animate-spin" />
                <p class="text-muted-foreground text-sm font-medium">Đang tổng hợp dữ liệu SLA toàn bộ nhà cung cấp...</p>
            </div>

            <template v-else-if="slaDashboardData">
                <!-- Aggregate Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card class="p-4 border-l-4 border-l-emerald-500 bg-gradient-to-r from-emerald-50/50 to-transparent dark:from-emerald-950/20">
                        <p class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">Tổng nhà cung cấp</p>
                        <p class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ slaDashboardData.suppliers?.length || 0 }}</p>
                    </Card>
                    <Card class="p-4 border-l-4 border-l-blue-500 bg-gradient-to-r from-blue-50/50 to-transparent dark:from-blue-950/20">
                        <p class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">TB Giao đúng hạn</p>
                        <p class="text-2xl font-extrabold text-blue-600 dark:text-blue-400 mt-1">
                            {{ slaDashboardData.suppliers?.length > 0 
                                ? (slaDashboardData.suppliers.reduce((s, sup) => s + Number(sup.on_time_rate || 0), 0) / slaDashboardData.suppliers.length).toFixed(1) 
                                : '—' }}%
                        </p>
                    </Card>
                    <Card class="p-4 border-l-4 border-l-teal-500 bg-gradient-to-r from-teal-50/50 to-transparent dark:from-teal-950/20">
                        <p class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">TB Độ chính xác</p>
                        <p class="text-2xl font-extrabold text-teal-600 dark:text-teal-400 mt-1">
                            {{ slaDashboardData.suppliers?.length > 0 
                                ? (slaDashboardData.suppliers.reduce((s, sup) => s + Number(sup.accuracy_rate || 0), 0) / slaDashboardData.suppliers.length).toFixed(1) 
                                : '—' }}%
                        </p>
                    </Card>
                    <Card class="p-4 border-l-4 border-l-amber-500 bg-gradient-to-r from-amber-50/50 to-transparent dark:from-amber-950/20">
                        <p class="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">TB Đánh giá sao</p>
                        <p class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">
                            {{ slaDashboardData.suppliers?.length > 0 
                                ? (slaDashboardData.suppliers.reduce((s, sup) => s + Number(sup.average_rating || 0), 0) / slaDashboardData.suppliers.length).toFixed(2) 
                                : '—' }} ★
                        </p>
                    </Card>
                </div>

                <!-- Supplier Ranking Table -->
                <Card class="overflow-hidden">
                    <div class="p-4 border-b">
                        <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                            <BarChart3 class="w-4 h-4 text-amber-500" />
                            Xếp hạng nhà cung cấp theo chỉ số SLA tổng hợp
                        </h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-foreground">
                            <thead class="bg-muted/40 text-muted-foreground uppercase text-[10px] font-semibold tracking-wider border-b border-border">
                                <tr>
                                    <th class="px-5 py-3 w-12">#</th>
                                    <th class="px-5 py-3">Nhà cung cấp</th>
                                    <th class="px-5 py-3 text-center">Tổng PO</th>
                                    <th class="px-5 py-3 text-center">Đúng hạn (%)</th>
                                    <th class="px-5 py-3 text-center">Chính xác (%)</th>
                                    <th class="px-5 py-3 text-center">Đánh giá (★)</th>
                                    <th class="px-5 py-3 text-center">Điểm tổng hợp</th>
                                    <th class="px-5 py-3 text-center">Hạng</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr 
                                    v-for="(sup, idx) in slaDashboardData.suppliers" 
                                    :key="sup.supplier_id"
                                    class="hover:bg-muted/10 transition-colors"
                                    :class="idx === 0 ? 'bg-amber-50/30 dark:bg-amber-950/10' : ''"
                                >
                                    <td class="px-5 py-3 font-extrabold text-lg">
                                        <span v-if="idx === 0" class="text-amber-500">🥇</span>
                                        <span v-else-if="idx === 1" class="text-slate-400">🥈</span>
                                        <span v-else-if="idx === 2" class="text-amber-700">🥉</span>
                                        <span v-else class="text-muted-foreground text-sm">{{ idx + 1 }}</span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="font-bold">{{ sup.supplier_name }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-center font-semibold text-muted-foreground">{{ sup.total_orders_analyzed }}</td>
                                    <td class="px-5 py-3 text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <span class="font-bold" :class="Number(sup.on_time_rate) >= 80 ? 'text-emerald-600 dark:text-emerald-400' : Number(sup.on_time_rate) >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400'">
                                                {{ sup.on_time_rate }}%
                                            </span>
                                            <div class="w-16 bg-muted h-1.5 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full transition-all" :class="Number(sup.on_time_rate) >= 80 ? 'bg-emerald-500' : Number(sup.on_time_rate) >= 60 ? 'bg-amber-500' : 'bg-rose-500'" :style="{ width: `${sup.on_time_rate}%` }"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <span class="font-bold" :class="Number(sup.accuracy_rate) >= 80 ? 'text-teal-600 dark:text-teal-400' : Number(sup.accuracy_rate) >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400'">
                                                {{ sup.accuracy_rate }}%
                                            </span>
                                            <div class="w-16 bg-muted h-1.5 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full transition-all" :class="Number(sup.accuracy_rate) >= 80 ? 'bg-teal-500' : Number(sup.accuracy_rate) >= 60 ? 'bg-amber-500' : 'bg-rose-500'" :style="{ width: `${sup.accuracy_rate}%` }"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span class="text-amber-500 font-bold">{{ sup.average_rating }} ★</span>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span class="text-lg font-extrabold" :class="getSupplierGrade(Number(sup.on_time_rate), Number(sup.accuracy_rate), Number(sup.average_rating)).color.includes('emerald') ? 'text-emerald-600 dark:text-emerald-400' : getSupplierGrade(Number(sup.on_time_rate), Number(sup.accuracy_rate), Number(sup.average_rating)).color.includes('blue') ? 'text-blue-600 dark:text-blue-400' : getSupplierGrade(Number(sup.on_time_rate), Number(sup.accuracy_rate), Number(sup.average_rating)).color.includes('amber') ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400'">
                                            {{ ((Number(sup.on_time_rate) * 0.4) + (Number(sup.accuracy_rate) * 0.4) + ((Number(sup.average_rating) / 5) * 100 * 0.2)).toFixed(1) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span :class="['inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider', getSupplierGrade(Number(sup.on_time_rate), Number(sup.accuracy_rate), Number(sup.average_rating)).color]">
                                            {{ getSupplierGrade(Number(sup.on_time_rate), Number(sup.accuracy_rate), Number(sup.average_rating)).label }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!slaDashboardData.suppliers || slaDashboardData.suppliers.length === 0">
                                    <td colspan="8" class="text-center py-16">
                                        <ShieldCheck class="w-10 h-10 text-muted-foreground mx-auto mb-3 opacity-40" />
                                        <p class="text-muted-foreground font-medium text-sm">Chưa có dữ liệu SLA để xếp hạng.</p>
                                        <p class="text-muted-foreground text-xs mt-1">Hãy giao nhận ít nhất một đơn PO để hệ thống bắt đầu chấm điểm.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </template>

            <!-- Empty / Not loaded yet -->
            <div v-else class="flex flex-col items-center justify-center py-24 text-muted-foreground">
                <Award class="w-12 h-12 mb-3 opacity-40" />
                <p class="text-sm font-medium">Nhấn "Làm mới dữ liệu" để tải bảng xếp hạng nhà cung cấp.</p>
            </div>
        </div>

        <!-- Tab Content: Internal Transfers -->
        <div v-if="activeTab === 'transfers'" class="space-y-6">
            <!-- Header Actions -->
            <div class="flex items-center justify-between border-b pb-4">
                <div>
                    <h3 class="text-lg font-bold">Điều phối & Luân chuyển Kho Liên chi nhánh</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Tối ưu hóa tồn kho toàn chuỗi bằng AI: Tự động phát hiện chi nhánh thiếu hụt và chuyển hàng từ chi nhánh thừa.</p>
                </div>
                <Button 
                    v-if="isOwner"
                    @click="openManualTransferModal"
                    class="h-9 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-semibold flex items-center gap-1.5 shadow-md"
                >
                    <Plus class="w-4 h-4" />
                    Tạo lệnh luân chuyển thủ công
                </Button>
            </div>

            <!-- Loading State -->
            <div v-if="loadingTransfers" class="flex flex-col items-center justify-center py-20 space-y-3">
                <RefreshCw class="w-8 h-8 text-emerald-500 animate-spin" />
                <p class="text-muted-foreground text-sm font-medium">Đang đồng bộ dữ liệu tồn kho toàn chuỗi...</p>
            </div>

            <template v-else>
                <!-- AI Recommendations Section -->
                <div class="space-y-4">
                    <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                        <Sparkles class="w-4 h-4 text-purple-500" />
                        Đề xuất tối ưu tồn kho bằng AI
                    </h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Card 
                            v-for="(rec, idx) in transferRecommendations" 
                            :key="idx"
                            class="hover:shadow-md transition-all border-l-4 border-l-purple-500 overflow-hidden relative"
                        >
                            <CardHeader class="pb-3 flex flex-row items-start justify-between space-y-0">
                                <div>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-400">
                                        Đề xuất AI #{{ idx + 1 }}
                                    </span>
                                    <CardTitle class="text-base font-bold mt-1.5">{{ rec.ingredient_name }}</CardTitle>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-muted-foreground">Lượng đề xuất chuyển</p>
                                    <p class="text-lg font-extrabold text-purple-600 dark:text-purple-400">
                                        {{ rec.suggested_quantity }} <span class="text-xs font-normal text-muted-foreground">{{ rec.unit_symbol }}</span>
                                    </p>
                                </div>
                            </CardHeader>

                            <CardContent class="space-y-3">
                                <!-- Transfer flow diagram -->
                                <div class="flex items-center justify-between bg-muted/30 p-2.5 rounded-xl text-xs">
                                    <div class="text-left">
                                        <p class="text-[9px] text-muted-foreground uppercase font-bold">Từ chi nhánh</p>
                                        <p class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">{{ rec.from_branch_name }}</p>
                                    </div>
                                    <ArrowLeftRight class="w-4 h-4 text-muted-foreground" />
                                    <div class="text-right">
                                        <p class="text-[9px] text-muted-foreground uppercase font-bold">Sang chi nhánh</p>
                                        <p class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">{{ rec.to_branch_name }}</p>
                                    </div>
                                </div>

                                <!-- AI Reason description -->
                                <div class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed border-l-2 border-slate-250 dark:border-slate-800 pl-3">
                                    {{ rec.reason }}
                                </div>

                                <div class="flex gap-2 pt-2 border-t border-border mt-2">
                                    <Button 
                                        @click="executeTransfer(rec)"
                                        class="w-full h-8 text-xs bg-purple-600 hover:bg-purple-700 text-white font-bold flex items-center justify-center gap-1"
                                    >
                                        <CheckCircle class="w-3.5 h-3.5" />
                                        Chấp nhận luân chuyển
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <div v-if="transferRecommendations.length === 0" class="col-span-full py-10 text-center border border-dashed border-border rounded-2xl bg-muted/20">
                            <Check class="w-8 h-8 text-emerald-500 mx-auto mb-2" />
                            <p class="text-muted-foreground font-medium text-xs">Tồn kho toàn chuỗi đang ở mức an toàn. Không có đề xuất luân chuyển nào.</p>
                        </div>
                    </div>
                </div>

                <!-- Historical Logs Section -->
                <Card class="overflow-hidden mt-6">
                    <div class="p-4 border-b">
                        <h4 class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                            <History class="w-4 h-4" />
                            Nhật ký điều phối kho liên chi nhánh
                        </h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left text-foreground">
                            <thead class="bg-muted/40 text-muted-foreground uppercase text-[10px] font-semibold tracking-wider border-b border-border">
                                <tr>
                                    <th class="px-6 py-3">ID</th>
                                    <th class="px-6 py-3">Vật tư</th>
                                    <th class="px-6 py-3">Số lượng</th>
                                    <th class="px-6 py-3">Từ chi nhánh</th>
                                    <th class="px-6 py-3">Đến chi nhánh</th>
                                    <th class="px-6 py-3">Người thực hiện</th>
                                    <th class="px-6 py-3">Trạng thái</th>
                                    <th class="px-6 py-3">Ghi chú</th>
                                    <th class="px-6 py-3">Thời điểm hoàn tất</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="log in transferLogs" :key="log.id" class="hover:bg-muted/10 transition-colors">
                                    <td class="px-6 py-3 font-mono font-bold">#{{ log.id }}</td>
                                    <td class="px-6 py-3 font-bold">{{ log.ingredient?.name || '—' }}</td>
                                    <td class="px-6 py-3 font-semibold text-slate-800 dark:text-slate-200">
                                        {{ log.quantity }} {{ log.ingredient?.unit?.symbol }}
                                    </td>
                                    <td class="px-6 py-3 font-medium">{{ log.from_branch?.name || '—' }}</td>
                                    <td class="px-6 py-3 font-medium">{{ log.to_branch?.name || '—' }}</td>
                                    <td class="px-6 py-3 text-muted-foreground">{{ log.creator?.name || '—' }}</td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-250">
                                            Hoàn tất
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-muted-foreground max-w-xs truncate" :title="log.notes">{{ log.notes }}</td>
                                    <td class="px-6 py-3 text-muted-foreground">{{ log.completed_at ? new Date(log.completed_at).toLocaleString('vi-VN') : '—' }}</td>
                                </tr>
                                <tr v-if="transferLogs.length === 0">
                                    <td colspan="9" class="text-center py-8 text-muted-foreground">Chưa có lịch sử lệnh luân chuyển kho nội bộ nào.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </template>
        </div>

        <!-- Manual Transfer Modal -->
        <div v-if="showManualTransferModal" class="fixed inset-0 bg-black/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <Card class="w-full max-w-lg overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <CardHeader class="flex flex-row items-center justify-between border-b pb-4">
                    <CardTitle class="text-lg font-bold">Tạo Lệnh Luân Chuyển Kho Thủ Công</CardTitle>
                    <Button variant="ghost" size="icon" @click="showManualTransferModal = false" class="h-8 w-8 text-muted-foreground">
                        <X class="w-5 h-5" />
                    </Button>
                </CardHeader>

                <form @submit.prevent="submitManualTransfer" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <!-- From Branch -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Từ chi nhánh (Xuất)</Label>
                            <select 
                                v-model="transferForm.from_branch_id"
                                required
                                class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                            >
                                <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                                    {{ branch.name }}
                                </option>
                            </select>
                        </div>

                        <!-- To Branch -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Sang chi nhánh (Nhập)</Label>
                            <select 
                                v-model="transferForm.to_branch_id"
                                required
                                class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                            >
                                <option v-for="branch in branches.filter(b => b.id !== Number(transferForm.from_branch_id))" :key="branch.id" :value="branch.id">
                                    {{ branch.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Ingredient -->
                        <div class="col-span-2 space-y-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nguyên vật liệu luân chuyển</Label>
                            <select 
                                v-model="transferForm.ingredient_id"
                                required
                                class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                            >
                                <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
                                    {{ ing.name }} (SKU: {{ ing.sku }})
                                </option>
                            </select>
                        </div>

                        <!-- Quantity -->
                        <div class="col-span-2 space-y-1.5">
                            <div class="flex items-center justify-between">
                                <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Số lượng cần chuyển</Label>
                                <span class="text-xs text-muted-foreground font-semibold">
                                    Tồn hiện có: <strong class="text-indigo-650 text-indigo-600 dark:text-indigo-400 font-extrabold">{{ sourceStockOnHand.toFixed(3) }}</strong>
                                </span>
                            </div>
                            <Input 
                                v-model.number="transferForm.quantity"
                                required
                                type="number"
                                step="0.001"
                                min="0.001"
                            />
                            <!-- Inline warnings/errors -->
                            <div v-if="transferQuantityWarning" class="mt-1.5 rounded-xl border p-3 text-xs font-medium"
                                 :class="transferQuantityWarning.type === 'error' ? 'bg-rose-50/50 border-rose-250 text-rose-700 dark:bg-rose-950/20 dark:border-rose-900/50 dark:text-rose-400' : 'bg-amber-50/50 border-amber-250 text-amber-700 dark:bg-amber-950/20 dark:border-amber-900/50 dark:text-amber-400'">
                                <div class="flex items-start gap-2">
                                    <AlertTriangle class="size-4 shrink-0 mt-0.5" :class="transferQuantityWarning.type === 'error' ? 'text-rose-500' : 'text-amber-500'" />
                                    <span>{{ transferQuantityWarning.message }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="col-span-2 space-y-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Lý do / Ghi chú</Label>
                            <textarea 
                                v-model="transferForm.notes"
                                rows="3"
                                placeholder="Ghi chú lý do luân chuyển kho..."
                                class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t mt-6">
                        <Button type="button" variant="outline" @click="showManualTransferModal = false">
                            Hủy bỏ
                        </Button>
                        <Button 
                            type="submit" 
                            :disabled="transferForm.processing || (transferQuantityWarning && transferQuantityWarning.type === 'error')"
                            class="bg-emerald-600 text-white hover:bg-emerald-700 font-bold"
                        >
                            {{ transferForm.processing ? 'Đang thực hiện...' : 'Thực hiện chuyển kho' }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- Add/Edit Supplier Modals -->
        <div v-if="showAddModal || showEditModal" class="fixed inset-0 bg-black/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <Card class="w-full max-w-lg overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <CardHeader class="flex flex-row items-center justify-between border-b pb-4">
                    <CardTitle class="text-lg font-bold">{{ showAddModal ? 'Thêm nhà cung cấp mới' : 'Chỉnh sửa nhà cung cấp' }}</CardTitle>
                    <Button variant="ghost" size="icon" @click="showAddModal = false; showEditModal = false;" class="h-8 w-8 text-muted-foreground">
                        <X class="w-5 h-5" />
                    </Button>
                </CardHeader>

                <form @submit.prevent="saveSupplier" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 space-y-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Tên nhà cung cấp <span class="text-rose-500">*</span></Label>
                            <Input v-model="supplierForm.name" required type="text" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Người đại diện</Label>
                            <Input v-model="supplierForm.contact_name" type="text" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Điện thoại</Label>
                            <Input v-model="supplierForm.phone" type="text" />
                        </div>
                        <div class="col-span-2 space-y-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Email liên hệ</Label>
                            <Input v-model="supplierForm.email" type="email" />
                        </div>
                        <div class="col-span-2 space-y-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Địa chỉ</Label>
                            <Input v-model="supplierForm.address" type="text" />
                        </div>
                        <div class="col-span-2 space-y-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Ghi chú đối tác</Label>
                            <textarea v-model="supplierForm.notes" rows="3" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"></textarea>
                        </div>
                        <div v-if="showEditModal" class="col-span-2 space-y-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Trạng thái</Label>
                            <select v-model="supplierForm.status" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                <option value="active">Đang hoạt động</option>
                                <option value="inactive">Tạm khóa</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t mt-6">
                        <Button type="button" variant="outline" @click="showAddModal = false; showEditModal = false;">
                            Hủy bỏ
                        </Button>
                        <Button type="submit" :disabled="supplierForm.processing" class="bg-emerald-600 text-white hover:bg-emerald-700">
                            {{ supplierForm.processing ? 'Đang lưu...' : 'Lưu thông tin' }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- Place PO Modal -->
        <div v-if="showPoModal" class="fixed inset-0 bg-black/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <Card class="w-full max-w-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <CardHeader class="flex flex-row items-center justify-between border-b pb-4">
                    <CardTitle class="text-lg font-bold">Đặt hàng nguyên liệu: {{ selectedSupplier?.name }}</CardTitle>
                    <Button variant="ghost" size="icon" @click="showPoModal = false" class="h-8 w-8 text-muted-foreground">
                        <X class="w-5 h-5" />
                    </Button>
                </CardHeader>

                <form @submit.prevent="submitPo" class="p-6 space-y-4">
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <div v-for="(item, idx) in poForm.items" :key="idx" class="flex gap-4 items-end bg-muted/40 p-3.5 rounded-xl border border-border">
                            <div class="flex-1 space-y-1">
                                <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nguyên liệu niêm yết</Label>
                                <select 
                                    v-model="item.ingredient_id" 
                                    required 
                                    class="w-full bg-background border border-input rounded-xl px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
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
                            <div class="w-32 space-y-1">
                                <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Số lượng mua</Label>
                                <Input v-model="item.quantity" required type="number" step="0.001" min="0.001" />
                            </div>
                            <Button type="button" variant="ghost" size="icon" @click="removePoItem(idx)" class="text-rose-500 hover:text-rose-600 hover:bg-rose-50 h-9 w-9 border border-transparent shrink-0">
                                <Trash2 class="w-4 h-4" />
                            </Button>
                        </div>

                        <Button type="button" variant="ghost" @click="addPoItem" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 px-0 h-auto">
                            <Plus class="w-4 h-4 mr-1" /> Thêm nguyên liệu
                        </Button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Hạn giao hàng dự kiến (SLA)</Label>
                            <Input v-model="poForm.delivery_due_date" type="datetime-local" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Ghi chú giao nhận</Label>
                            <Input v-model="poForm.notes" type="text" placeholder="Yêu cầu đóng gói, giờ hạ hàng..." />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t mt-6">
                        <Button type="button" variant="outline" @click="showPoModal = false">
                            Hủy bỏ
                        </Button>
                        <Button type="submit" :disabled="poForm.processing" class="bg-emerald-600 text-white hover:bg-emerald-700">
                            {{ poForm.processing ? 'Đang gửi...' : 'Gửi đặt đơn PO' }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- Verify & Deliver Dual-Verification Modal -->
        <div v-if="showVerifyModal" class="fixed inset-0 bg-black/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <Card class="w-full max-w-3xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <CardHeader class="flex flex-row items-center justify-between border-b pb-4">
                    <div>
                        <CardTitle class="text-lg font-bold">Đối soát kiểm đếm & Nhận hàng</CardTitle>
                        <CardDescription class="text-[10px] mt-0.5">Mã PO: {{ selectedPo?.po_number }} • Nhà cung cấp: {{ selectedPo?.supplier_name }}</CardDescription>
                    </div>
                    <Button variant="ghost" size="icon" @click="showVerifyModal = false" class="h-8 w-8 text-muted-foreground">
                        <X class="w-5 h-5" />
                    </Button>
                </CardHeader>

                <form @submit.prevent="submitVerification" class="p-6 space-y-6">
                    <!-- Check alert box if mismatch is detected -->
                    <div v-if="hasMismatch" class="bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:border-rose-900 border border-rose-250 p-4 rounded-xl flex gap-3 animate-pulse">
                        <AlertTriangle class="w-5 h-5 shrink-0 mt-0.5 text-rose-600 dark:text-rose-400" />
                        <div class="text-xs space-y-1">
                            <h4 class="font-bold">CẢNH BÁO: Phát hiện sai lệch đối soát chéo!</h4>
                            <p class="leading-relaxed text-rose-700 dark:text-rose-350">Số lượng thực tế hoặc đơn giá hóa đơn không khớp với niêm yết ban đầu. Khi xác nhận, hệ thống sẽ **ĐÓNG BĂNG giao dịch**, khóa cập nhật kho tự động, ghi vết kiểm toán gian lận và gửi cảnh báo đỏ trực tiếp đến Owner.</p>
                        </div>
                    </div>

                    <!-- Items Verification Table -->
                    <div class="border rounded-xl overflow-hidden">
                        <table class="w-full text-xs text-left text-foreground">
                            <thead class="bg-muted/40 text-muted-foreground uppercase text-[10px] font-semibold tracking-wider border-b">
                                <tr>
                                    <th class="px-4 py-3">Tên nguyên liệu</th>
                                    <th class="px-4 py-3 text-center">Đặt (PO)</th>
                                    <th class="px-4 py-3 w-28">Thực nhận</th>
                                    <th class="px-4 py-3 text-center">Giá niêm yết</th>
                                    <th class="px-4 py-3 w-32">Giá hóa đơn</th>
                                    <th class="px-4 py-3 text-center">Khớp</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="(item, idx) in verifyDiscrepancies" :key="idx" class="hover:bg-muted/10">
                                    <td class="px-4 py-3 font-semibold">{{ item.ingredient_name }}</td>
                                    <td class="px-4 py-3 text-center font-semibold text-muted-foreground">{{ item.quantity_ordered }}</td>
                                    <td class="px-4 py-3">
                                        <input 
                                            v-model="item.quantity_received" 
                                            @input="updateQuantityReceived(idx, ($event.target as HTMLInputElement).value)"
                                            type="number" 
                                            step="0.001"
                                            class="w-full bg-background border border-input rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-emerald-500" 
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-center font-semibold text-muted-foreground">{{ Number(item.price_per_unit).toLocaleString('vi-VN') }}đ</td>
                                    <td class="px-4 py-3">
                                        <div class="relative flex items-center">
                                            <input 
                                                v-model="item.invoice_price" 
                                                @input="updateInvoicePrice(idx, ($event.target as HTMLInputElement).value)"
                                                type="number" 
                                                class="w-full bg-background border border-input rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-emerald-500" 
                                            />
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span v-if="!item.mismatch" class="text-emerald-600 bg-emerald-50 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-450 dark:border-emerald-900 p-1 rounded-full inline-block">
                                            <Check class="w-3.5 h-3.5" />
                                        </span>
                                        <span v-else class="text-rose-600 bg-rose-50 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-450 dark:border-rose-900 p-1 rounded-full inline-block animate-bounce">
                                            <X class="w-3.5 h-3.5" />
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Invoice Upload -->
                    <div class="space-y-2">
                        <Label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider">Đính kèm hóa đơn đối chiếu (Hóa đơn giấy/điện tử) <span class="text-rose-500">*</span></Label>
                        <div class="border border-dashed border-border rounded-xl p-4 flex flex-col items-center justify-center bg-muted/20 hover:bg-muted/40 transition-colors relative min-h-[120px]">
                            <div v-if="loadingOcr" class="flex flex-col items-center justify-center space-y-2">
                                <RefreshCw class="w-8 h-8 text-emerald-500 animate-spin" />
                                <span class="text-xs text-emerald-600 font-bold animate-pulse">AI OCR: Đang phân tích và quét dữ liệu hóa đơn...</span>
                            </div>
                            <template v-else>
                                <Upload class="w-8 h-8 text-muted-foreground opacity-60 mb-2" />
                                <span class="text-xs text-muted-foreground font-semibold">{{ verifyForm.invoice_file ? verifyForm.invoice_file.name : 'Nhấp để chọn tệp chứng từ hoặc kéo thả vào đây' }}</span>
                                <span class="text-[10px] text-muted-foreground mt-1">Hỗ trợ JPG, PNG, PDF tối đa 4MB. Tự động quét và điền dữ liệu bằng AI.</span>
                                <input type="file" @change="handleFileUpload" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                            </template>
                        </div>
                    </div>

                    <!-- Rating and feedback -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-muted/30 p-4 rounded-xl border border-border">
                        <div class="col-span-1 space-y-1">
                            <Label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider">Đánh giá nhà cung cấp (1-5★)</Label>
                            <select v-model="verifyForm.rating" class="w-full bg-background border border-input rounded-xl px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                                <option :value="5">5 ★★★★★ (Xuất sắc)</option>
                                <option :value="4">4 ★★★★☆ (Tốt)</option>
                                <option :value="3">3 ★★★☆☆ (Trung bình)</option>
                                <option :value="2">2 ★★☆☆☆ (Kém)</option>
                                <option :value="1">1 ★☆☆☆☆ (Rất kém)</option>
                            </select>
                        </div>
                        <div class="col-span-2 space-y-1">
                            <Label class="block text-xs font-semibold text-muted-foreground uppercase tracking-wider">Ghi chú chất lượng hàng hóa / vận chuyển</Label>
                            <Input v-model="verifyForm.rating_notes" type="text" placeholder="Rau tươi sạch, giao đúng giờ..." />
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end gap-2 pt-4 border-t mt-6">
                        <Button type="button" variant="outline" @click="showVerifyModal = false">
                            Hủy bỏ
                        </Button>
                        <Button type="submit" :disabled="verifyForm.processing" class="bg-emerald-600 text-white hover:bg-emerald-700">
                            {{ verifyForm.processing ? 'Đang gửi...' : (hasMismatch ? 'Xác nhận Đóng băng đơn hàng' : 'Hoàn tất bàn giao nhập kho') }}
                        </Button>
                    </div>
                </form>
            </Card>
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
