<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Plus,
    Edit2,
    Trash2,
    ShoppingBag,
    CheckCircle,
    AlertTriangle,
    Sparkles,
    TrendingUp,
    TrendingDown,
    FileText,
    Upload,
    RefreshCw,
    X,
    Check,
    ArrowLeftRight,
    History,
    Gauge,
    BarChart3,
    Package,
    Award,
    ShieldCheck,
    Search,
    Mail,
    Phone,
    MapPin,
    User,
    Users,
    Info,
    ClipboardList,
    Building,
    ChevronLeft,
    ChevronRight,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
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
import { confirmDialog } from '@/composables/useConfirm';
import {
    autoReplenish as autoReplenishRoute,
    draftPoBulk,
    ocrInvoice,
    placeOrder as placeSupplierOrder,
    priceAnalytics as supplierPriceAnalytics,
    replenishCockpit,
    sla as supplierSlaRoute,
    slaDashboard,
    store as storeSupplier,
    update as updateSupplier,
    destroy as destroySupplier,
} from '@/routes/suppliers';
import {
    approve as approvePurchaseOrder,
    releaseEscrow as releaseEscrowRoute,
    refundEscrow as refundEscrowRoute,
    verify as verifyPurchaseOrder,
} from '@/routes/suppliers/orders';
import {
    internalTransfers as createInternalTransfer,
    transferRecommendations as transferRecommendationsRoute,
} from '@/routes/inventory';
import { list as listInternalTransfers } from '@/routes/inventory/internal-transfers';

const props = defineProps<{
    suppliers: any[];
    ingredients: any[];
    purchaseOrders: any[];
    units: any[];
}>();

const page = usePage();
const roles = computed(() => {
    const raw = page.props.roles ?? [];

    return Array.isArray(raw)
        ? raw
        : Object.values(raw as Record<string, string>);
});
const isOwner = computed(() => roles.value.includes('owner'));

// Search & Filters
const searchQuery = ref('');
const statusFilter = ref('all'); // 'all' | 'active' | 'inactive'
const quickFilterChip = ref<
    'all' | 'frozen' | 'pending' | 'unpaid' | 'top_rated'
>('all');

const filteredSuppliers = computed(() => {
    return props.suppliers.filter((sup) => {
        const matchesSearch =
            !searchQuery.value ||
            sup.name?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            sup.contact_name
                ?.toLowerCase()
                .includes(searchQuery.value.toLowerCase()) ||
            sup.phone
                ?.toLowerCase()
                .includes(searchQuery.value.toLowerCase()) ||
            sup.email
                ?.toLowerCase()
                .includes(searchQuery.value.toLowerCase()) ||
            sup.tax_code
                ?.toLowerCase()
                .includes(searchQuery.value.toLowerCase()) ||
            sup.address
                ?.toLowerCase()
                .includes(searchQuery.value.toLowerCase());

        const matchesStatus =
            statusFilter.value === 'all' || sup.status === statusFilter.value;

        const matchesChip =
            quickFilterChip.value === 'all' ||
            (quickFilterChip.value === 'top_rated' && sup.status === 'active');

        return matchesSearch && matchesStatus && matchesChip;
    });
});

const filteredPurchaseOrders = computed(() => {
    let list = props.purchaseOrders || [];

    if (quickFilterChip.value === 'frozen') {
        list = list.filter((po: any) => po.is_frozen || po.status === 'frozen');
    } else if (quickFilterChip.value === 'pending') {
        list = list.filter((po: any) => po.status === 'pending_approval');
    } else if (quickFilterChip.value === 'unpaid') {
        list = list.filter((po: any) => po.payment_status !== 'paid');
    }

    return list;
});

const kpis = computed(() => {
    const total = props.suppliers.length;
    const active = props.suppliers.filter((s) => s.status === 'active').length;
    const inactive = total - active;
    const totalIngredients = props.ingredients.length;
    const totalPOs = props.purchaseOrders.length;

    return {
        total,
        active,
        inactive,
        totalIngredients,
        totalPOs,
    };
});

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
    tax_code: '',
    bank_name: '',
    bank_account_number: '',
    bank_account_holder: '',
    payment_terms: 'cod',
    category: 'fresh_food',
});

const poForm = useForm({
    items: [] as Array<{ ingredient_id: number; quantity: number }>,
    notes: '',
    delivery_due_date: '',
    payment_method: 'banking',
    discount_percent: 0,
    shipping_method: 'supplier_delivery',
});

const verifyForm = useForm({
    items: [] as Array<{
        ingredient_id: number;
        quantity_received: number;
        invoice_price: number;
    }>,
    invoice_file: null as File | null,
    rating: 5,
    rating_notes: '',
    mismatch_reason: 'Khối lượng giao không đủ',
    resolution_action: 'Trừ trực tiếp vào công nợ đơn sau',
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
        const res = await fetch(replenishCockpit.url());
        const data = await res.json();
        cockpitRecommendations.value = data.recommendations || [];
        // By default, select all recommendations that have a supplier
        selectedCockpitIds.value = cockpitRecommendations.value
            .filter((r) => r.optimal_supplier)
            .map((r) => r.ingredient_id);
    } catch (e) {
        console.error(e);
    } finally {
        loadingCockpit.value = false;
    }
};

const toggleSelectAllCockpit = () => {
    const validRecs = cockpitRecommendations.value.filter(
        (r) => r.optimal_supplier,
    );

    if (selectedCockpitIds.value.length === validRecs.length) {
        selectedCockpitIds.value = [];
    } else {
        selectedCockpitIds.value = validRecs.map((r) => r.ingredient_id);
    }
};

const submitBulkDraftPo = async () => {
    if (selectedCockpitIds.value.length === 0) {
        return;
    }

    const itemsToSend = cockpitRecommendations.value
        .filter((r) => selectedCockpitIds.value.includes(r.ingredient_id))
        .map((r) => ({
            ingredient_id: r.ingredient_id,
            quantity: r.suggested_quantity,
            supplier_id: r.optimal_supplier?.id,
            price: r.optimal_price,
        }));

    const missingSupplier = itemsToSend.some((it) => !it.supplier_id);

    if (missingSupplier) {
        toast.error(
            'Không thể tạo đơn PO cho các mặt hàng chưa xác định nhà cung cấp.',
        );

        return;
    }

    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Bạn có chắc chắn muốn tự động tạo PO nháp cho ${itemsToSend.length} mặt hàng đã chọn?`,
            variant: 'default',
        })
    ) {
        router.post(
            draftPoBulk.url(),
            {
                items: itemsToSend,
            },
            {
                onSuccess: () => {
                    activeTab.value = 'pos';
                    selectedCockpitIds.value = [];
                },
            },
        );
    }
};

// SLA Dashboard state
const slaDashboardData = ref<any>(null);
const loadingSlaDashboard = ref(false);

const fetchSlaDashboard = async () => {
    loadingSlaDashboard.value = true;
    selectedSupplier.value = null;

    try {
        const res = await fetch(slaDashboard.url());
        slaDashboardData.value = await res.json();
    } catch (e) {
        console.error(e);
    } finally {
        loadingSlaDashboard.value = false;
    }
};

const getSupplierGrade = (onTime: number, accuracy: number, rating: number) => {
    const normalizedRating = (rating / 5.0) * 100;
    const score = onTime * 0.4 + accuracy * 0.4 + normalizedRating * 0.2;

    if (score >= 90) {
        return {
            label: 'Hạng A (Xuất sắc)',
            color: 'bg-emerald-500 text-white',
        };
    }

    if (score >= 75) {
        return { label: 'Hạng B (Tốt)', color: 'bg-blue-500 text-white' };
    }

    if (score >= 55) {
        return {
            label: 'Hạng C (Trung bình)',
            color: 'bg-amber-500 text-white',
        };
    }

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
        const res = await fetch(
            supplierSlaRoute.url(selectedSupplier.value.id),
        );
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
    supplierForm.tax_code = supplier.tax_code || '';
    supplierForm.bank_name = supplier.bank_name || '';
    supplierForm.bank_account_number = supplier.bank_account_number || '';
    supplierForm.bank_account_holder = supplier.bank_account_holder || '';
    supplierForm.payment_terms = supplier.payment_terms || 'cod';
    supplierForm.category = supplier.category || 'fresh_food';
    showEditModal.value = true;
};

const saveSupplier = () => {
    if (supplierForm.id) {
        supplierForm.patch(updateSupplier.url(supplierForm.id), {
            onSuccess: () => {
                showEditModal.value = false;
                supplierForm.reset();
            },
        });
    } else {
        supplierForm.post(storeSupplier.url(), {
            onSuccess: () => {
                showAddModal.value = false;
                supplierForm.reset();
            },
        });
    }
};

const deleteSupplier = async (supplier: any) => {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Bạn có chắc chắn muốn xóa nhà cung cấp "${supplier.name}"?`,
        })
    ) {
        router.delete(destroySupplier.url(supplier.id));
    }
};

const triggerAutoReplenish = async () => {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description:
                'Bạn có chắc chắn muốn kích hoạt AI quét kho và tự động đề xuất PO nháp?',
            variant: 'default',
        })
    ) {
        router.post(
            autoReplenishRoute.url(),
            {},
            {
                onSuccess: () => {
                    activeTab.value = 'pos';
                },
            },
        );
    }
};

// Purchase Order creation (2-step workflow with Full Catalog Menu)
const poStep = ref<1 | 2>(1);
const menuSearchQuery = ref('');
const selectedQuantities = ref<Record<number, number>>({});

const currentUser = computed(() => (page.props as any).auth?.user);
const currentRestaurant = computed(
    () =>
        (page.props as any).restaurant ||
        (page.props as any).auth?.user?.restaurant,
);

const supplierIngredients = computed(() => {
    if (!selectedSupplier.value) {
        return [];
    }

    return props.ingredients.filter(
        (i) => i.supplier_id === selectedSupplier.value.id,
    );
});

const filteredSupplierIngredients = computed(() => {
    if (!menuSearchQuery.value.trim()) {
        return supplierIngredients.value;
    }

    const q = menuSearchQuery.value.toLowerCase().trim();

    return supplierIngredients.value.filter((i) =>
        i.name.toLowerCase().includes(q),
    );
});

const openPoModal = (supplier: any) => {
    selectedSupplier.value = supplier;
    poStep.value = 1;
    menuSearchQuery.value = '';
    poForm.reset();

    const initialQtys: Record<number, number> = {};
    const supsIngs = props.ingredients.filter(
        (i) => i.supplier_id === supplier.id,
    );
    supsIngs.forEach((ing) => {
        initialQtys[ing.id] = 0;
    });
    selectedQuantities.value = initialQtys;
    showPoModal.value = true;
};

const updateItemQuantity = (ingredientId: number, qty: number) => {
    selectedQuantities.value[ingredientId] = Math.max(0, isNaN(qty) ? 0 : qty);
};

const incrementQuantity = (ingredientId: number, step = 1) => {
    const current = selectedQuantities.value[ingredientId] || 0;
    selectedQuantities.value[ingredientId] = Number(
        (current + step).toFixed(3),
    );
};

const decrementQuantity = (ingredientId: number, step = 1) => {
    const current = selectedQuantities.value[ingredientId] || 0;
    selectedQuantities.value[ingredientId] = Math.max(
        0,
        Number((current - step).toFixed(3)),
    );
};

const goToPoStep2 = () => {
    const itemsToOrder: Array<{ ingredient_id: number; quantity: number }> = [];

    for (const [ingIdStr, qty] of Object.entries(selectedQuantities.value)) {
        const ingId = Number(ingIdStr);
        const numericQty = Number(qty);

        if (numericQty > 0) {
            itemsToOrder.push({ ingredient_id: ingId, quantity: numericQty });
        }
    }

    if (itemsToOrder.length === 0) {
        toast.error('Vui lòng chọn ít nhất 1 sản phẩm với số lượng > 0.');

        return;
    }

    poForm.items = itemsToOrder;
    poStep.value = 2;
};

const selectedPoItemsDetailed = computed(() => {
    const list: Array<{
        id: number;
        name: string;
        unit_symbol: string;
        price: number;
        quantity: number;
        subtotal: number;
    }> = [];

    for (const [ingIdStr, qty] of Object.entries(selectedQuantities.value)) {
        const numericQty = Number(qty);

        if (numericQty > 0) {
            const ingId = Number(ingIdStr);
            const ing = props.ingredients.find((i) => i.id === ingId);
            const price = Number(ing?.price || 0);
            list.push({
                id: ingId,
                name: ing?.name || 'Nguyên liệu',
                unit_symbol: ing?.unit_symbol || '',
                price: price,
                quantity: numericQty,
                subtotal: price * numericQty,
            });
        }
    }

    return list;
});

const poSubtotalAmount = computed(() => {
    return selectedPoItemsDetailed.value.reduce(
        (sum: number, i: any) => sum + i.subtotal,
        0,
    );
});

const totalPoEstimatedAmount = computed(() => {
    const subtotal = poSubtotalAmount.value;
    const discount = Number(poForm.discount_percent || 0);

    if (discount > 0) {
        return Math.max(0, subtotal * (1 - discount / 100));
    }

    return subtotal;
});

const addPoItem = () => {
    const available = props.ingredients.filter(
        (i) => i.supplier_id === selectedSupplier.value.id,
    );

    if (available.length > 0) {
        poForm.items.push({ ingredient_id: available[0].id, quantity: 1 });
    }
};
void addPoItem;

const removePoItem = (index: number | string) => {
    poForm.items.splice(Number(index), 1);
};
void removePoItem;

const submitPo = () => {
    poForm.post(placeSupplierOrder.url(selectedSupplier.value.id), {
        onSuccess: () => {
            showPoModal.value = false;
            activeTab.value = 'pos';
        },
    });
};

const approvePo = (po: any) => {
    router.post(approvePurchaseOrder.url(po.id));
};

const releaseEscrow = async (po: any) => {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Bạn có chắc chắn muốn giải ngân thủ công số tiền ${Number(po.total_amount).toLocaleString('vi-VN')}đ cho nhà cung cấp?`,
            variant: 'default',
        })
    ) {
        router.post(releaseEscrowRoute.url(po.id));
    }
};

const refundEscrow = async (po: any) => {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Bạn có chắc chắn muốn hoàn trả số tiền ký quỹ ${Number(po.total_amount).toLocaleString('vi-VN')}đ về tài khoản của nhà hàng?`,
            variant: 'default',
        })
    ) {
        router.post(refundEscrowRoute.url(po.id));
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
        ingredient_name: item.ingredient_name,
    }));
    showVerifyModal.value = true;
};

// Calculate discrepancies dynamically
const verifyDiscrepancies = computed(() => {
    if (!selectedPo.value || !verifyForm.items.length) {
        return [];
    }

    return verifyForm.items.map((item: any) => {
        const qtyMismatch =
            Math.abs(item.quantity_ordered - item.quantity_received) > 0.001;
        const priceMismatch =
            Math.abs(item.price_per_unit - item.invoice_price) > 0.01;

        return {
            ...item,
            qtyMismatch,
            priceMismatch,
            mismatch: qtyMismatch || priceMismatch,
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
        formData.append(
            `items[${index}][ingredient_id]`,
            String(item.ingredient_id),
        );
        formData.append(
            `items[${index}][quantity_received]`,
            String(item.quantity_received),
        );
        formData.append(
            `items[${index}][invoice_price]`,
            String(item.invoice_price),
        );
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

    router.post(
        verifyPurchaseOrder.url(selectedPo.value.id),
        formData as any,
        {
            onSuccess: () => {
                showVerifyModal.value = false;
            },
        },
    );
};

// Analytics handler
const fetchAnalytics = async () => {
    if (!selectedSupplier.value || !selectedIngredient.value) {
        return;
    }

    loadingAnalytics.value = true;

    try {
        const res = await fetch(
            supplierPriceAnalytics.url({
                supplier: selectedSupplier.value?.id || 0,
                ingredient: selectedIngredient.value?.id || 0,
            }),
        );
        analyticsData.value = await res.json();
    } catch (e) {
        console.error(e);
    } finally {
        loadingAnalytics.value = false;
    }
};

const selectSupplierForAnalytics = (sup: any) => {
    selectedSupplier.value = sup;
    selectedIngredient.value =
        props.ingredients.filter((i) => i.supplier_id === sup.id)[0] || null;
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

        const res = await fetch(ocrInvoice.url(), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content ?? '',
            },
        });

        if (res.ok) {
            const data = await res.json();

            if (data.items && data.items.length > 0) {
                const parsedItems = data.items;
                verifyForm.items.forEach((formItem: any) => {
                    const matched = parsedItems.find(
                        (pi: any) =>
                            pi.ingredient_id === formItem.ingredient_id,
                    );

                    if (matched) {
                        formItem.quantity_received = matched.quantity;
                        formItem.invoice_price = matched.unit_price;
                    }
                });
                toast.success(
                    `AI OCR: Đã quét hóa đơn số thành công! Khớp tự động ${parsedItems.length} nguyên vật liệu với độ tin cậy ${Math.round(data.confidence * 100)}%.`,
                );
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
            fetch(transferRecommendationsRoute.url()),
            fetch(listInternalTransfers.url()),
        ]);
        const recData = await recRes.json();
        const logData = await logRes.json();
        transferRecommendations.value = recData.recommendations || [];
        branches.value = recData.branches || [];
        inventories.value = recData.inventories || [];
        transferLogs.value = logData.transfers || [];
    } catch (e) {
        console.error('Failed to fetch transfer data', e);
    } finally {
        loadingTransfers.value = false;
    }
};

const executeTransfer = async (rec: any) => {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Bạn có chắc chắn muốn thực hiện lệnh luân chuyển kho nội bộ: chuyển ${rec.suggested_quantity} ${rec.unit_symbol} "${rec.ingredient_name}" từ chi nhánh "${rec.from_branch_name}" sang "${rec.to_branch_name}"?`,
            variant: 'default',
        }))
    ) {
        return;
    }

    router.post(
        createInternalTransfer.url(),
        {
            from_branch_id: rec.from_branch_id,
            to_branch_id: rec.to_branch_id,
            ingredient_id: rec.ingredient_id,
            quantity: rec.suggested_quantity,
            notes: rec.reason,
        },
        {
            onSuccess: () => {
                fetchTransfers();
            },
        },
    );
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
    if (transferForm.processing) {
        return;
    }

    transferForm.post(createInternalTransfer.url(), {
        onSuccess: () => {
            showManualTransferModal.value = false;
            transferForm.reset();
            fetchTransfers();
        },
    });
};

const selectedSourceInventory = computed(() => {
    if (!transferForm.from_branch_id || !transferForm.ingredient_id) {
        return null;
    }

    return (
        inventories.value.find(
            (i) =>
                i.branch_id === Number(transferForm.from_branch_id) &&
                i.ingredient_id === Number(transferForm.ingredient_id),
        ) || null
    );
});

const selectedIngredientDetail = computed(() => {
    if (!transferForm.ingredient_id) {
        return null;
    }

    return (
        props.ingredients.find(
            (i) => i.id === Number(transferForm.ingredient_id),
        ) || null
    );
});

const sourceStockOnHand = computed(() => {
    return selectedSourceInventory.value
        ? Number(selectedSourceInventory.value.quantity_on_hand)
        : 0;
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
            message: `Chi nhánh xuất không đủ tồn kho thực tế để chuyển (Tồn hiện có: ${stock.toFixed(3)}).`,
        };
    }

    const minStock = selectedIngredientDetail.value
        ? Number(selectedIngredientDetail.value.min_stock_level)
        : 0;

    if (stock - qty < minStock) {
        return {
            type: 'warning',
            message: `Sau khi chuyển, tồn kho tại chi nhánh nguồn (${(stock - qty).toFixed(3)}) sẽ tụt dưới mức tồn tối thiểu (${minStock.toFixed(3)}).`,
        };
    }

    return null;
});

// Horizontal Tab Scroll & Drag Navigation
const tabScrollContainerRef = ref<HTMLElement | null>(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

const updateScrollButtons = () => {
    const el = tabScrollContainerRef.value;

    if (!el) {
        return;
    }

    canScrollLeft.value = el.scrollLeft > 5;
    canScrollRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 5;
};

const scrollTabs = (direction: 'left' | 'right') => {
    const el = tabScrollContainerRef.value;

    if (!el) {
        return;
    }

    const distance = 260;
    el.scrollBy({
        left: direction === 'left' ? -distance : distance,
        behavior: 'smooth',
    });
};

const handleWheelScroll = (e: WheelEvent) => {
    const el = tabScrollContainerRef.value;

    if (!el) {
        return;
    }

    if (e.deltaY !== 0) {
        el.scrollLeft += e.deltaY;
        updateScrollButtons();
    }
};

let isDragging = false;
let startX = 0;
let scrollLeftStart = 0;

const startDrag = (e: MouseEvent) => {
    const el = tabScrollContainerRef.value;

    if (!el) {
        return;
    }

    isDragging = true;
    startX = e.pageX - el.offsetLeft;
    scrollLeftStart = el.scrollLeft;
};

const stopDrag = () => {
    isDragging = false;
};

const doDrag = (e: MouseEvent) => {
    if (!isDragging) {
        return;
    }

    const el = tabScrollContainerRef.value;

    if (!el) {
        return;
    }

    e.preventDefault();
    const x = e.pageX - el.offsetLeft;
    const walk = (x - startX) * 1.5;
    el.scrollLeft = scrollLeftStart - walk;
    updateScrollButtons();
};

// CSV Export Logic
const exportToCsv = (
    filename: string,
    headers: string[],
    rows: (string | number)[][],
) => {
    let csvContent = '\uFEFF';
    csvContent +=
        headers
            .map((h) => `"${String(h ?? '').replace(/"/g, '""')}"`)
            .join(';') + '\r\n';
    rows.forEach((row) => {
        csvContent +=
            row
                .map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`)
                .join(';') + '\r\n';
    });

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute(
        'download',
        `${filename}_${new Date().toISOString().slice(0, 10)}.csv`,
    );
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    toast.success(`Đã xuất báo cáo ${filename}.csv thành công!`);
};

const exportSuppliersReport = () => {
    const headers = [
        'Mã NCC',
        'Tên Nhà Cung Cấp',
        'Người Đại Diện',
        'Điện Thoại',
        'Email',
        'Mã Số Thuế',
        'Tên Ngân Hàng',
        'Số Tài Khoản',
        'Chủ Tài Khoản',
        'Điều Khoản Công Nợ',
        'Số Nguyên Liệu Niêm Yết',
        'Trạng Thái',
    ];
    const rows = props.suppliers.map((s: any) => [
        `NCC-${s.id}`,
        s.name,
        s.contact_name || '—',
        s.phone || '—',
        s.email || '—',
        s.tax_code || '—',
        s.bank_name || '—',
        s.bank_account_number || '—',
        s.bank_account_holder || '—',
        s.payment_terms || 'COD',
        s.ingredients_count || 0,
        s.status === 'active' ? 'Đang hoạt động' : 'Tạm khóa',
    ]);
    exportToCsv('Bao_Cao_Nha_Cung_Cap', headers, rows);
};

const exportPurchaseOrdersReport = () => {
    const headers = [
        'Mã Đơn PO',
        'Nhà Cung Cấp',
        'Trạng Thái',
        'Hình Thức Thanh Toán',
        'Chiết Khấu (%)',
        'Tổng Tiền Đặt (VNĐ)',
        'Tổng Hóa Đơn (VNĐ)',
        'Đã Đóng Băng',
        'Phát Hiện Sai Lệch',
        'Lý Do Sai Lệch',
        'Phương Án Xử Lý',
        'Người Đặt',
        'Ngày Đặt',
    ];
    const rows = props.purchaseOrders.map((po: any) => [
        po.po_number,
        po.supplier_name,
        po.status,
        po.payment_method || 'banking',
        po.discount_percent || 0,
        po.total_amount,
        po.invoice_total_amount || 0,
        po.is_frozen ? 'Có' : 'Không',
        po.is_discrepant ? 'Có' : 'Không',
        po.mismatch_reason || '—',
        po.resolution_action || '—',
        po.created_by_name || 'Hệ thống',
        po.created_at,
    ]);
    exportToCsv('Nhat_Ky_Dat_Hang_PO', headers, rows);
};

const exportSlaReport = () => {
    if (!slaDashboardData.value || !slaDashboardData.value.rankings) {
        toast.error('Chưa có dữ liệu Bảng xếp hạng SLA để xuất báo cáo.');

        return;
    }

    const headers = [
        'Xếp Hạng',
        'Tên Nhà Cung Cấp',
        'Điểm Tổng Hợp SLA',
        'Tỷ Lệ Đúng Hạn (%)',
        'Độ Chính Xác (%)',
        'Đánh Giá Chất Lượng (1-5★)',
        'Tổng Đơn PO',
        'Đơn Đóng Băng',
        'Hạng Đánh Giá',
    ];
    const rows = slaDashboardData.value.rankings.map(
        (item: any, idx: number) => {
            const grade = getSupplierGrade(
                item.on_time_rate,
                item.accuracy_rate,
                item.avg_rating,
            );

            return [
                idx + 1,
                item.supplier_name,
                item.overall_score || 0,
                item.on_time_rate || 0,
                item.accuracy_rate || 0,
                item.avg_rating || 5,
                item.total_pos || 0,
                item.frozen_pos || 0,
                grade.label,
            ];
        },
    );
    exportToCsv('Bao_Cao_Xep_Hang_SLA_NCC', headers, rows);
};

onMounted(() => {
    setTimeout(updateScrollButtons, 100);
    window.addEventListener('resize', updateScrollButtons);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateScrollButtons);
});
</script>

<template>
    <Head title="Quản lý Nhà cung cấp" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-500"
                >
                    <ShoppingBag class="size-6" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        Portal Chuỗi cung ứng & Nhà cung cấp
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Quản lý danh sách đối tác cung ứng, đặt hàng hàng ngày,
                        đối soát chéo 3 bên triệt tiêu gian lận.
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="isOwner"
                    @click="triggerAutoReplenish"
                    class="flex h-9 items-center gap-1.5 bg-gradient-to-r from-purple-600 to-indigo-500 text-xs font-semibold text-white shadow-md hover:from-purple-500 hover:to-indigo-400"
                >
                    <Sparkles class="h-4 w-4" />
                    AI Đề xuất Nhập hàng
                </Button>
                <Button
                    @click="openAddModal"
                    class="h-9 bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700"
                >
                    <Plus class="mr-1.5 h-4 w-4" />
                    Thêm đối tác
                </Button>
            </div>
        </div>

        <!-- Navigation Tabs (Interactive Horizontal Drag & Arrow Scroll Track) -->
        <div
            class="group relative max-w-full overflow-hidden rounded-2xl border border-border/60 bg-muted/30 p-1.5 shadow-xs backdrop-blur-md"
        >
            <!-- Left Arrow Scroll Button -->
            <button
                v-if="canScrollLeft"
                @click="scrollTabs('left')"
                class="absolute top-1/2 left-2 z-20 flex h-7 w-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-border bg-background/90 text-foreground shadow-md backdrop-blur-md transition-all duration-200 hover:scale-110 active:scale-95"
                title="Cuộn sang trái"
            >
                <ChevronLeft class="h-4 w-4" />
            </button>

            <!-- Scrollable Track Container -->
            <div
                ref="tabScrollContainerRef"
                @scroll="updateScrollButtons"
                @wheel="handleWheelScroll"
                @mousedown="startDrag"
                @mouseleave="stopDrag"
                @mouseup="stopDrag"
                @mousemove="doDrag"
                class="no-scrollbar flex cursor-grab items-center gap-1.5 overflow-x-auto scroll-smooth py-0.5 whitespace-nowrap select-none active:cursor-grabbing"
            >
                <button
                    @click="activeTab = 'list'"
                    class="group relative flex shrink-0 cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs transition-all duration-300"
                    :class="[
                        activeTab === 'list'
                            ? 'scale-[1.02] bg-gradient-to-r from-emerald-600 to-teal-600 font-extrabold text-white shadow-md shadow-emerald-600/20'
                            : 'font-semibold text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                    ]"
                >
                    <Users
                        class="h-4 w-4 transition-transform duration-300 group-hover:scale-110"
                    />
                    <span>Danh sách đối tác</span>
                    <span
                        v-if="kpis.total > 0"
                        :class="[
                            'py-0.2 ml-1 rounded-full px-1.5 text-[10px] font-extrabold',
                            activeTab === 'list'
                                ? 'bg-white/20 text-white'
                                : 'bg-muted-foreground/15 text-muted-foreground',
                        ]"
                    >
                        {{ kpis.total }}
                    </span>
                </button>

                <button
                    @click="
                        activeTab = 'cockpit';
                        fetchCockpitData();
                    "
                    class="group relative flex shrink-0 cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs transition-all duration-300"
                    :class="[
                        activeTab === 'cockpit'
                            ? 'scale-[1.02] bg-gradient-to-r from-emerald-600 to-teal-600 font-extrabold text-white shadow-md shadow-emerald-600/20'
                            : 'font-semibold text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                    ]"
                >
                    <Gauge
                        class="h-4 w-4 transition-transform duration-300 group-hover:scale-110"
                    />
                    <span>Cockpit Nhập hàng</span>
                </button>

                <button
                    @click="activeTab = 'pos'"
                    class="group relative flex shrink-0 cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs transition-all duration-300"
                    :class="[
                        activeTab === 'pos'
                            ? 'scale-[1.02] bg-gradient-to-r from-emerald-600 to-teal-600 font-extrabold text-white shadow-md shadow-emerald-600/20'
                            : 'font-semibold text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                    ]"
                >
                    <ClipboardList
                        class="h-4 w-4 transition-transform duration-300 group-hover:scale-110"
                    />
                    <span>Nhật ký đặt hàng (PO)</span>
                </button>

                <button
                    @click="activeTab = 'analytics'"
                    class="group relative flex shrink-0 cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs transition-all duration-300"
                    :class="[
                        activeTab === 'analytics'
                            ? 'scale-[1.02] bg-gradient-to-r from-purple-600 to-indigo-600 font-extrabold text-white shadow-md shadow-purple-600/25'
                            : 'font-semibold text-muted-foreground hover:bg-purple-500/10 hover:text-purple-500',
                    ]"
                >
                    <Sparkles class="h-4 w-4 animate-pulse text-purple-400" />
                    <span>AI Phân tích biến động giá</span>
                </button>

                <button
                    @click="activeTab = 'sla'"
                    class="group relative flex shrink-0 cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs transition-all duration-300"
                    :class="[
                        activeTab === 'sla'
                            ? 'scale-[1.02] bg-gradient-to-r from-emerald-600 to-teal-600 font-extrabold text-white shadow-md shadow-emerald-600/20'
                            : 'font-semibold text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                    ]"
                >
                    <ShieldCheck
                        class="h-4 w-4 transition-transform duration-300 group-hover:scale-110"
                    />
                    <span>Báo cáo SLA & Đánh giá</span>
                </button>

                <button
                    @click="
                        activeTab = 'sla-dashboard';
                        fetchSlaDashboard();
                    "
                    class="group relative flex shrink-0 cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs transition-all duration-300"
                    :class="[
                        activeTab === 'sla-dashboard'
                            ? 'scale-[1.02] bg-gradient-to-r from-emerald-600 to-teal-600 font-extrabold text-white shadow-md shadow-emerald-600/20'
                            : 'font-semibold text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                    ]"
                >
                    <Award
                        class="h-4 w-4 transition-transform duration-300 group-hover:scale-110"
                    />
                    <span>Bảng xếp hạng NCC</span>
                </button>

                <button
                    @click="
                        activeTab = 'transfers';
                        fetchTransfers();
                    "
                    class="group relative flex shrink-0 cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs transition-all duration-300"
                    :class="[
                        activeTab === 'transfers'
                            ? 'scale-[1.02] bg-gradient-to-r from-indigo-600 to-purple-600 font-extrabold text-white shadow-md shadow-indigo-600/25'
                            : 'font-semibold text-muted-foreground hover:bg-indigo-500/10 hover:text-indigo-500',
                    ]"
                >
                    <ArrowLeftRight
                        class="h-4 w-4 text-indigo-400 transition-transform duration-300 group-hover:scale-110"
                    />
                    <span>AI Điều phối Liên chi nhánh</span>
                </button>
            </div>

            <!-- Right Arrow Scroll Button -->
            <button
                v-if="canScrollRight"
                @click="scrollTabs('right')"
                class="absolute top-1/2 right-2 z-20 flex h-7 w-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-border bg-background/90 text-foreground shadow-md backdrop-blur-md transition-all duration-200 hover:scale-110 active:scale-95"
                title="Cuộn sang phải"
            >
                <ChevronRight class="h-4 w-4" />
            </button>
        </div>

        <!-- Tab Content: Suppliers List -->
        <div v-if="activeTab === 'list'" class="animate-fade-in space-y-6">
            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    class="group flex items-center justify-between rounded-2xl border border-border bg-card/40 p-5 backdrop-blur-md transition-all duration-300 hover:border-emerald-500/20 hover:shadow-md hover:shadow-emerald-500/[0.02]"
                >
                    <div class="space-y-1">
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Tổng đối tác
                        </p>
                        <h3
                            class="text-2xl font-extrabold tracking-tight transition-colors group-hover:text-emerald-500"
                        >
                            {{ kpis.total }}
                        </h3>
                    </div>
                    <div
                        class="rounded-xl bg-emerald-500/10 p-3 text-emerald-500 transition-transform duration-300 group-hover:scale-110"
                    >
                        <Users class="size-5" />
                    </div>
                </div>
                <div
                    class="group flex items-center justify-between rounded-2xl border border-border bg-card/40 p-5 backdrop-blur-md transition-all duration-300 hover:border-emerald-500/20 hover:shadow-md hover:shadow-emerald-500/[0.02]"
                >
                    <div class="space-y-1">
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Đang hoạt động
                        </p>
                        <h3
                            class="text-2xl font-extrabold tracking-tight text-emerald-500"
                        >
                            {{ kpis.active }}
                        </h3>
                    </div>
                    <div
                        class="rounded-xl bg-emerald-500/10 p-3 text-emerald-500 transition-transform duration-300 group-hover:scale-110"
                    >
                        <CheckCircle class="size-5" />
                    </div>
                </div>
                <div
                    class="group flex items-center justify-between rounded-2xl border border-border bg-card/40 p-5 backdrop-blur-md transition-all duration-300 hover:border-indigo-500/20 hover:shadow-md hover:shadow-indigo-500/[0.02]"
                >
                    <div class="space-y-1">
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Nguyên liệu cung ứng
                        </p>
                        <h3
                            class="text-2xl font-extrabold tracking-tight transition-colors group-hover:text-indigo-500"
                        >
                            {{ kpis.totalIngredients }}
                        </h3>
                    </div>
                    <div
                        class="rounded-xl bg-indigo-500/10 p-3 text-indigo-500 transition-transform duration-300 group-hover:scale-110"
                    >
                        <Package class="size-5" />
                    </div>
                </div>
                <div
                    class="group flex items-center justify-between rounded-2xl border border-border bg-card/40 p-5 backdrop-blur-md transition-all duration-300 hover:border-purple-500/20 hover:shadow-md hover:shadow-purple-500/[0.02]"
                >
                    <div class="space-y-1">
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Đơn hàng PO
                        </p>
                        <h3
                            class="text-2xl font-extrabold tracking-tight transition-colors group-hover:text-purple-500"
                        >
                            {{ kpis.totalPOs }}
                        </h3>
                    </div>
                    <div
                        class="rounded-xl bg-purple-500/10 p-3 text-purple-500 transition-transform duration-300 group-hover:scale-110"
                    >
                        <ClipboardList class="size-5" />
                    </div>
                </div>
            </div>

            <!-- Smart Quick Filter Chips & Export Controls -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border/60 bg-muted/20 p-3 backdrop-blur-md"
            >
                <div
                    class="flex flex-wrap items-center gap-1.5 text-xs font-semibold"
                >
                    <span
                        class="mr-1 text-[11px] font-bold text-muted-foreground"
                        >🔍 Lọc nhanh:</span
                    >
                    <button
                        @click="quickFilterChip = 'all'"
                        class="cursor-pointer rounded-xl px-3 py-1.5 transition-all"
                        :class="
                            quickFilterChip === 'all'
                                ? 'bg-emerald-600 font-extrabold text-white shadow-sm'
                                : 'bg-background text-muted-foreground hover:bg-muted'
                        "
                    >
                        Tất cả đối tác
                    </button>
                    <button
                        @click="quickFilterChip = 'frozen'"
                        class="flex cursor-pointer items-center gap-1 rounded-xl px-3 py-1.5 transition-all"
                        :class="
                            quickFilterChip === 'frozen'
                                ? 'bg-rose-600 font-extrabold text-white shadow-sm'
                                : 'bg-background text-rose-500 hover:bg-rose-500/10'
                        "
                    >
                        <span>🔴 Đang đóng băng</span>
                    </button>
                    <button
                        @click="quickFilterChip = 'pending'"
                        class="flex cursor-pointer items-center gap-1 rounded-xl px-3 py-1.5 transition-all"
                        :class="
                            quickFilterChip === 'pending'
                                ? 'bg-amber-500 font-extrabold text-white shadow-sm'
                                : 'bg-background text-amber-600 hover:bg-amber-500/10'
                        "
                    >
                        <span>⏳ PO Chờ duyệt</span>
                    </button>
                    <button
                        @click="quickFilterChip = 'unpaid'"
                        class="flex cursor-pointer items-center gap-1 rounded-xl px-3 py-1.5 transition-all"
                        :class="
                            quickFilterChip === 'unpaid'
                                ? 'bg-indigo-600 font-extrabold text-white shadow-sm'
                                : 'bg-background text-indigo-500 hover:bg-indigo-500/10'
                        "
                    >
                        <span>💵 Còn công nợ</span>
                    </button>
                    <button
                        @click="quickFilterChip = 'top_rated'"
                        class="flex cursor-pointer items-center gap-1 rounded-xl px-3 py-1.5 transition-all"
                        :class="
                            quickFilterChip === 'top_rated'
                                ? 'bg-teal-600 font-extrabold text-white shadow-sm'
                                : 'bg-background text-teal-500 hover:bg-teal-500/10'
                        "
                    >
                        <span>⭐ NCC Xuất sắc (>4.5★)</span>
                    </button>
                </div>

                <!-- Export Accounting Report Button -->
                <Button
                    @click="exportSuppliersReport"
                    variant="outline"
                    size="sm"
                    class="h-8.5 cursor-pointer gap-1.5 rounded-xl border-emerald-500/30 bg-emerald-500/10 font-bold text-emerald-600 hover:bg-emerald-500/20 dark:text-emerald-400"
                >
                    <FileText class="h-3.5 w-3.5" />
                    <span>Xuất Excel NCC (.csv)</span>
                </Button>
            </div>

            <!-- Search & Filters -->
            <div
                class="flex flex-col items-center justify-between gap-3 rounded-2xl border border-border bg-card/30 p-3.5 sm:flex-row"
            >
                <div class="relative w-full sm:max-w-md">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Tìm kiếm đối tác (tên, email, số điện thoại, đại diện)..."
                        class="h-9 rounded-xl border-border/60 bg-background pl-9 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20"
                    />
                    <X
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute top-1/2 right-3 size-4 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                    />
                </div>
                <div
                    class="flex w-full shrink-0 items-center gap-2 self-end sm:w-auto sm:self-auto"
                >
                    <span
                        class="hidden text-xs font-semibold text-muted-foreground md:inline"
                        >Trạng thái:</span
                    >
                    <select
                        v-model="statusFilter"
                        class="h-9 w-full rounded-xl border border-border bg-background px-3 py-1 text-xs font-medium text-foreground focus:ring-2 focus:ring-emerald-500/20 focus:outline-none sm:w-36"
                    >
                        <option value="all">Tất cả trạng thái</option>
                        <option value="active">Đang hoạt động</option>
                        <option value="inactive">Tạm khóa</option>
                    </select>
                </div>
            </div>

            <!-- Supplier Cards Grid -->
            <div
                v-if="filteredSuppliers.length > 0"
                class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
            >
                <Card
                    v-for="sup in filteredSuppliers"
                    :key="sup.id"
                    class="group relative flex flex-col justify-between overflow-hidden border-border bg-card/30 backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:border-emerald-500/20 hover:shadow-xl hover:shadow-emerald-500/[0.01]"
                >
                    <div
                        class="absolute -top-16 -right-16 h-32 w-32 rounded-full bg-emerald-500/5 blur-2xl transition-colors duration-300 group-hover:bg-emerald-500/10"
                    ></div>

                    <CardHeader
                        class="relative z-10 flex flex-row items-start justify-between space-y-0 pb-3"
                    >
                        <div class="flex gap-3">
                            <!-- Initials Avatar with custom gradient based on status -->
                            <div
                                :class="[
                                    'flex h-11 w-11 items-center justify-center rounded-xl text-sm font-extrabold text-white shadow-md shadow-black/10',
                                    sup.status === 'active'
                                        ? 'bg-gradient-to-tr from-emerald-500 to-teal-400 shadow-emerald-500/10'
                                        : 'bg-gradient-to-tr from-rose-500 to-orange-400 shadow-rose-500/10',
                                ]"
                            >
                                {{
                                    (sup.name || 'NCC')
                                        .split(' ')
                                        .map((n: string) => n[0])
                                        .join('')
                                        .substring(0, 2)
                                        .toUpperCase()
                                }}
                            </div>
                            <div class="space-y-1">
                                <CardTitle
                                    class="text-sm font-extrabold tracking-tight transition-colors duration-300 group-hover:text-emerald-400"
                                    >{{ sup.name }}</CardTitle
                                >
                                <div>
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-0.5 text-[9px] font-bold tracking-wider uppercase',
                                            sup.status === 'active'
                                                ? 'dark:text-emerald-450 bg-emerald-500/10 text-emerald-500 dark:bg-emerald-950/40'
                                                : 'dark:text-rose-455 bg-rose-500/10 text-rose-500 dark:bg-rose-950/40',
                                        ]"
                                    >
                                        {{
                                            sup.status === 'active'
                                                ? 'Đang hoạt động'
                                                : 'Tạm khóa'
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-0.5">
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="openEditModal(sup)"
                                class="h-8 w-8 rounded-lg text-muted-foreground hover:bg-emerald-500/10 hover:text-emerald-500"
                            >
                                <Edit2 class="h-4 w-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="deleteSupplier(sup)"
                                class="h-8 w-8 rounded-lg text-muted-foreground hover:bg-rose-500/10 hover:text-rose-500"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardHeader>

                    <CardContent
                        class="relative z-10 flex flex-1 flex-col justify-between pb-5"
                    >
                        <div>
                            <!-- Quick Statistics Badge Grid -->
                            <div
                                class="mb-3 grid grid-cols-2 gap-2 rounded-xl border border-border/40 bg-muted/40 p-2 text-[10px]"
                            >
                                <div
                                    class="flex items-center gap-1.5 font-semibold text-muted-foreground"
                                >
                                    <Package
                                        class="h-3.5 w-3.5 text-emerald-500"
                                    />
                                    <span>Nguyên liệu:</span>
                                    <span
                                        class="font-mono font-bold text-foreground"
                                        >{{ sup.ingredients_count ?? 0 }}</span
                                    >
                                </div>
                                <div
                                    class="flex items-center gap-1.5 font-semibold text-muted-foreground"
                                >
                                    <ClipboardList
                                        class="h-3.5 w-3.5 text-indigo-500"
                                    />
                                    <span>Đơn PO:</span>
                                    <span
                                        class="font-mono font-bold text-foreground"
                                        >{{
                                            sup.purchase_orders_count ?? 0
                                        }}</span
                                    >
                                </div>
                            </div>

                            <!-- Badges: Payment Terms & Tax Code -->
                            <div
                                class="mb-3 flex flex-wrap gap-1.5 text-[9px] font-bold"
                            >
                                <span
                                    v-if="sup.tax_code"
                                    class="rounded-md border border-border/50 bg-muted px-2 py-0.5 text-muted-foreground"
                                >
                                    MST: {{ sup.tax_code }}
                                </span>
                                <span
                                    v-if="sup.payment_terms"
                                    class="rounded-md border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-emerald-600 dark:text-emerald-400"
                                >
                                    {{ sup.payment_terms.toUpperCase() }}
                                </span>
                                <span
                                    v-if="sup.bank_name"
                                    class="rounded-md border border-blue-500/20 bg-blue-500/10 px-2 py-0.5 text-blue-600 dark:text-blue-400"
                                >
                                    💳 {{ sup.bank_name }} ({{
                                        sup.bank_account_number || 'STK'
                                    }})
                                </span>
                            </div>

                            <!-- Contact Detail List with modern icons -->
                            <div
                                class="mt-1 space-y-1.5 text-xs text-muted-foreground"
                            >
                                <div
                                    v-if="sup.contact_name"
                                    class="flex items-center gap-2.5"
                                >
                                    <User
                                        class="h-3.5 w-3.5 shrink-0 text-muted-foreground/60"
                                    />
                                    <span class="truncate"
                                        ><strong
                                            class="font-medium text-muted-foreground/80"
                                            >Đại diện:</strong
                                        >
                                        {{ sup.contact_name }}</span
                                    >
                                </div>
                                <div
                                    v-if="sup.phone"
                                    class="flex items-center gap-2.5"
                                >
                                    <Phone
                                        class="h-3.5 w-3.5 shrink-0 text-muted-foreground/60"
                                    />
                                    <span
                                        ><strong
                                            class="font-medium text-muted-foreground/80"
                                            >Điện thoại:</strong
                                        >
                                        {{ sup.phone }}</span
                                    >
                                </div>
                                <div
                                    v-if="sup.email"
                                    class="flex items-center gap-2.5"
                                >
                                    <Mail
                                        class="h-3.5 w-3.5 shrink-0 text-muted-foreground/60"
                                    />
                                    <span class="truncate"
                                        ><strong
                                            class="font-medium text-muted-foreground/80"
                                            >Email:</strong
                                        >
                                        {{ sup.email }}</span
                                    >
                                </div>
                                <div
                                    v-if="sup.address"
                                    class="flex items-start gap-2.5"
                                >
                                    <MapPin
                                        class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground/60"
                                    />
                                    <span class="line-clamp-2"
                                        ><strong
                                            class="font-medium text-muted-foreground/80"
                                            >Địa chỉ:</strong
                                        >
                                        {{ sup.address }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-4 flex gap-2 border-t border-border pt-4"
                        >
                            <Button
                                @click="openPoModal(sup)"
                                variant="outline"
                                class="h-8.5 w-full rounded-xl border-emerald-500/20 text-xs font-bold text-emerald-500 transition-all duration-300 hover:bg-emerald-500 hover:text-white dark:border-emerald-500/30 dark:hover:bg-emerald-500 dark:hover:text-black"
                            >
                                <ShoppingBag class="mr-1.5 h-3.5 w-3.5" />
                                Đặt hàng hàng ngày
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty / No matches State -->
            <div
                v-else
                class="animate-fade-in flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card/25 py-16 text-center backdrop-blur-xs"
            >
                <div
                    class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-muted/40 text-muted-foreground"
                >
                    <Search class="h-8 w-8" />
                </div>
                <h4 class="mb-1 text-base font-bold text-foreground">
                    Không tìm thấy nhà cung cấp nào
                </h4>
                <p class="max-w-sm text-xs text-muted-foreground">
                    Không tìm thấy đối tác nào phù hợp với bộ lọc tìm kiếm hiện
                    tại.
                </p>
                <Button
                    @click="
                        searchQuery = '';
                        statusFilter = 'all';
                    "
                    variant="outline"
                    class="mt-4 rounded-xl text-xs font-semibold"
                    >Xóa bộ lọc</Button
                >
            </div>
        </div>

        <!-- Tab Content: Supply Chain Cockpit -->
        <div v-if="activeTab === 'cockpit'" class="animate-fade-in space-y-6">
            <!-- Cockpit Header -->
            <div class="flex items-center justify-between border-b pb-4">
                <div>
                    <h3 class="flex items-center gap-2 text-lg font-bold">
                        <Gauge class="h-5 w-5 text-indigo-500" />
                        Cockpit Tự Động Nhập Hàng
                    </h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        AI quét kho tự động, phát hiện tồn kho dưới ngưỡng và
                        soạn PO nháp gửi nhà cung cấp tối ưu nhất (giá thấp
                        nhất).
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        @click="fetchCockpitData"
                        variant="outline"
                        :disabled="loadingCockpit"
                        class="flex h-9 items-center gap-1.5 text-xs font-semibold"
                    >
                        <RefreshCw
                            class="h-4 w-4"
                            :class="loadingCockpit ? 'animate-spin' : ''"
                        />
                        Quét lại kho
                    </Button>
                    <Button
                        v-if="selectedCockpitIds.length > 0"
                        @click="submitBulkDraftPo"
                        class="flex h-9 items-center gap-1.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-xs font-bold text-white shadow-lg hover:from-indigo-500 hover:to-purple-500"
                    >
                        <Package class="h-4 w-4" />
                        Tạo PO nháp ({{ selectedCockpitIds.length }} mặt hàng)
                    </Button>
                </div>
            </div>

            <!-- Loading State -->
            <div
                v-if="loadingCockpit"
                class="flex flex-col items-center justify-center space-y-3 py-20"
            >
                <RefreshCw class="h-8 w-8 animate-spin text-indigo-500" />
                <p class="text-sm font-medium text-muted-foreground">
                    Đang quét tồn kho và tìm nhà cung cấp tối ưu...
                </p>
            </div>

            <template v-else>
                <!-- Summary Stats -->
                <div
                    v-if="cockpitRecommendations.length > 0"
                    class="grid grid-cols-1 gap-4 md:grid-cols-3"
                >
                    <Card
                        class="border-l-4 border-l-rose-500 bg-gradient-to-r from-rose-50/50 to-transparent p-4 dark:from-rose-950/20"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Mặt hàng thiếu hụt
                                </p>
                                <p
                                    class="mt-1 text-2xl font-extrabold text-rose-600 dark:text-rose-400"
                                >
                                    {{ cockpitRecommendations.length }}
                                </p>
                            </div>
                            <AlertTriangle
                                class="h-8 w-8 text-rose-400 opacity-60"
                            />
                        </div>
                    </Card>
                    <Card
                        class="border-l-4 border-l-indigo-500 bg-gradient-to-r from-indigo-50/50 to-transparent p-4 dark:from-indigo-950/20"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Đã chọn tạo PO
                                </p>
                                <p
                                    class="mt-1 text-2xl font-extrabold text-indigo-600 dark:text-indigo-400"
                                >
                                    {{ selectedCockpitIds.length }}
                                </p>
                            </div>
                            <ShoppingBag
                                class="h-8 w-8 text-indigo-400 opacity-60"
                            />
                        </div>
                    </Card>
                    <Card
                        class="border-l-4 border-l-emerald-500 bg-gradient-to-r from-emerald-50/50 to-transparent p-4 dark:from-emerald-950/20"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Có NCC tối ưu
                                </p>
                                <p
                                    class="mt-1 text-2xl font-extrabold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{
                                        cockpitRecommendations.filter(
                                            (r) => r.optimal_supplier,
                                        ).length
                                    }}
                                </p>
                            </div>
                            <CheckCircle
                                class="h-8 w-8 text-emerald-400 opacity-60"
                            />
                        </div>
                    </Card>
                </div>

                <!-- Recommendations Table -->
                <Card class="overflow-hidden">
                    <div class="flex items-center justify-between border-b p-4">
                        <h4
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <Sparkles class="h-4 w-4 text-indigo-500" />
                            Danh sách đề xuất nhập hàng tự động
                        </h4>
                        <button
                            v-if="cockpitRecommendations.length > 0"
                            @click="toggleSelectAllCockpit"
                            class="cursor-pointer text-xs font-bold text-indigo-600 hover:underline dark:text-indigo-400"
                        >
                            {{
                                selectedCockpitIds.length ===
                                cockpitRecommendations.filter(
                                    (r) => r.optimal_supplier,
                                ).length
                                    ? 'Bỏ chọn tất cả'
                                    : 'Chọn tất cả'
                            }}
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-foreground">
                            <thead
                                class="border-b border-border bg-muted/40 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th class="w-10 px-4 py-3">
                                        <input
                                            type="checkbox"
                                            :checked="
                                                selectedCockpitIds.length ===
                                                    cockpitRecommendations.filter(
                                                        (r) =>
                                                            r.optimal_supplier,
                                                    ).length &&
                                                cockpitRecommendations.length >
                                                    0
                                            "
                                            @change="toggleSelectAllCockpit"
                                            class="rounded border-input accent-indigo-600"
                                        />
                                    </th>
                                    <th class="px-4 py-3">Nguyên liệu</th>
                                    <th class="px-4 py-3 text-center">
                                        Tồn hiện tại
                                    </th>
                                    <th class="px-4 py-3 text-center">
                                        Tồn tối thiểu
                                    </th>
                                    <th class="px-4 py-3 text-center">
                                        Thiếu hụt
                                    </th>
                                    <th class="px-4 py-3 text-center">
                                        SL đề xuất (×1.2)
                                    </th>
                                    <th class="px-4 py-3">NCC tối ưu</th>
                                    <th class="px-4 py-3 text-right">
                                        Giá tối ưu
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        Thành tiền dự kiến
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="rec in cockpitRecommendations"
                                    :key="rec.ingredient_id"
                                    class="transition-colors hover:bg-muted/10"
                                    :class="
                                        selectedCockpitIds.includes(
                                            rec.ingredient_id,
                                        )
                                            ? 'bg-indigo-50/30 dark:bg-indigo-950/10'
                                            : ''
                                    "
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
                                            <p class="text-sm font-bold">
                                                {{ rec.ingredient_name }}
                                            </p>
                                            <p
                                                class="font-mono text-[10px] text-muted-foreground"
                                            >
                                                {{ rec.sku }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="font-semibold"
                                            :class="
                                                rec.current_stock <= 0
                                                    ? 'text-rose-600 dark:text-rose-400'
                                                    : 'text-amber-600 dark:text-amber-400'
                                            "
                                        >
                                            {{
                                                Number(
                                                    rec.current_stock,
                                                ).toFixed(2)
                                            }}
                                        </span>
                                        <span
                                            class="ml-0.5 text-[10px] text-muted-foreground"
                                            >{{ rec.unit_symbol }}</span
                                        >
                                    </td>
                                    <td
                                        class="px-4 py-3 text-center font-semibold text-muted-foreground"
                                    >
                                        {{ Number(rec.min_stock).toFixed(2) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="inline-flex items-center rounded border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-400"
                                        >
                                            -{{
                                                Number(rec.deficit).toFixed(2)
                                            }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-center font-extrabold text-indigo-600 dark:text-indigo-400"
                                    >
                                        {{
                                            Number(
                                                rec.suggested_quantity,
                                            ).toFixed(2)
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            v-if="rec.optimal_supplier"
                                            class="text-xs font-semibold text-emerald-700 dark:text-emerald-400"
                                        >
                                            {{ rec.optimal_supplier.name }}
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-400"
                                        >
                                            <AlertTriangle
                                                class="mr-1 h-3 w-3"
                                            />
                                            Chưa có NCC
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-semibold"
                                    >
                                        <span v-if="rec.optimal_price"
                                            >{{
                                                Number(
                                                    rec.optimal_price,
                                                ).toLocaleString('vi-VN')
                                            }}đ</span
                                        >
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold">
                                        <span v-if="rec.optimal_price"
                                            >{{
                                                (
                                                    Number(rec.optimal_price) *
                                                    Number(
                                                        rec.suggested_quantity,
                                                    )
                                                ).toLocaleString('vi-VN')
                                            }}đ</span
                                        >
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="cockpitRecommendations.length === 0">
                                    <td colspan="9" class="py-16 text-center">
                                        <CheckCircle
                                            class="mx-auto mb-3 h-10 w-10 text-emerald-500 opacity-60"
                                        />
                                        <p
                                            class="text-sm font-medium text-muted-foreground"
                                        >
                                            Toàn bộ kho hàng đang ở mức an toàn.
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            Không có mặt hàng nào cần nhập thêm.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                            <!-- Total row -->
                            <tfoot v-if="selectedCockpitIds.length > 0">
                                <tr
                                    class="border-t-2 border-indigo-200 bg-indigo-50/50 dark:border-indigo-900 dark:bg-indigo-950/20"
                                >
                                    <td
                                        colspan="8"
                                        class="px-4 py-3 text-right text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Tổng giá trị đơn PO nháp:
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-base font-extrabold text-indigo-700 dark:text-indigo-300"
                                    >
                                        {{
                                            cockpitRecommendations
                                                .filter(
                                                    (r) =>
                                                        selectedCockpitIds.includes(
                                                            r.ingredient_id,
                                                        ) && r.optimal_price,
                                                )
                                                .reduce(
                                                    (sum, r) =>
                                                        sum +
                                                        Number(
                                                            r.optimal_price,
                                                        ) *
                                                            Number(
                                                                r.suggested_quantity,
                                                            ),
                                                    0,
                                                )
                                                .toLocaleString('vi-VN')
                                        }}đ
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </Card>
            </template>
        </div>

        <!-- Tab Content: PO logs -->
        <Card
            v-if="activeTab === 'pos'"
            class="animate-fade-in space-y-3 overflow-hidden p-4"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b pb-3"
            >
                <div>
                    <h3
                        class="flex items-center gap-2 text-base font-bold text-foreground"
                    >
                        <ClipboardList class="h-4 w-4 text-emerald-500" />
                        Nhật Ký Đặt Hàng & Quản Lý Công Nợ PO
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Theo dõi lịch sử đơn hàng, chiết khấu và trạng thái ký
                        quỹ thanh toán B2B.
                    </p>
                </div>
                <Button
                    @click="exportPurchaseOrdersReport"
                    variant="outline"
                    size="sm"
                    class="h-8.5 cursor-pointer gap-1.5 rounded-xl border-emerald-500/30 bg-emerald-500/10 font-bold text-emerald-600 hover:bg-emerald-500/20 dark:text-emerald-400"
                >
                    <FileText class="h-3.5 w-3.5" />
                    <span>Xuất Excel Nhật Ký PO (.csv)</span>
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-border/60">
                <table class="w-full text-left text-sm text-foreground">
                    <thead
                        class="border-b border-border bg-muted/40 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
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
                        <tr
                            v-for="po in filteredPurchaseOrders"
                            :key="po.id"
                            class="transition-colors hover:bg-muted/10"
                        >
                            <td
                                class="dark:text-emerald-450 px-6 py-4 font-mono font-bold text-emerald-600"
                            >
                                {{ po.po_number }}
                            </td>
                            <td class="px-6 py-4 font-semibold">
                                {{ po.supplier_name }}
                            </td>
                            <td class="px-6 py-4 text-xs text-muted-foreground">
                                {{ po.created_by_name }}
                            </td>
                            <td class="px-6 py-4 font-semibold">
                                {{
                                    Number(po.total_amount).toLocaleString(
                                        'vi-VN',
                                    )
                                }}đ
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    v-if="po.invoice_total_amount > 0"
                                    class="font-semibold"
                                >
                                    {{
                                        Number(
                                            po.invoice_total_amount,
                                        ).toLocaleString('vi-VN')
                                    }}đ
                                </span>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-0.5">
                                    <span
                                        v-if="po.payment_status === 'unpaid'"
                                        class="inline-flex items-center rounded border bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground"
                                    >
                                        Chưa thanh toán
                                    </span>
                                    <span
                                        v-else-if="
                                            po.payment_status ===
                                            'escrow_locked'
                                        "
                                        class="inline-flex animate-pulse items-center rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-400"
                                    >
                                        Ký quỹ (Khóa)
                                    </span>
                                    <span
                                        v-else-if="po.payment_status === 'paid'"
                                        class="inline-flex items-center rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-400"
                                    >
                                        Giải ngân
                                    </span>
                                    <span
                                        v-else-if="
                                            po.payment_status === 'refunded'
                                        "
                                        class="dark:text-rose-450 inline-flex items-center rounded border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40"
                                    >
                                        Đã hoàn trả
                                    </span>
                                    <p
                                        v-if="po.escrow_transaction_id"
                                        class="mt-0.5 font-mono text-[9px] leading-none text-muted-foreground"
                                    >
                                        {{ po.escrow_transaction_id }}
                                    </p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    v-if="po.status === 'pending_approval'"
                                    class="inline-flex items-center rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-400"
                                >
                                    Chờ phê duyệt
                                </span>
                                <span
                                    v-else-if="po.status === 'approved'"
                                    class="inline-flex items-center rounded border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-400"
                                >
                                    Chờ nhận đơn
                                </span>
                                <span
                                    v-else-if="po.status === 'preparing'"
                                    class="inline-flex items-center rounded border border-purple-200 bg-purple-50 px-2 py-0.5 text-xs font-semibold text-purple-700 dark:border-purple-900/50 dark:bg-purple-950/40 dark:text-purple-400"
                                >
                                    Chuẩn bị hàng
                                </span>
                                <span
                                    v-else-if="po.status === 'shipping'"
                                    class="inline-flex items-center rounded border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:border-indigo-900/50 dark:bg-indigo-950/40 dark:text-indigo-400"
                                >
                                    Đang giao hàng
                                </span>
                                <span
                                    v-else-if="po.status === 'delivered'"
                                    class="inline-flex items-center rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-400"
                                >
                                    Đã hạ hàng
                                </span>
                                <span
                                    v-else-if="po.status === 'frozen'"
                                    class="dark:text-rose-450 inline-flex animate-pulse items-center rounded border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40"
                                >
                                    ĐÓNG BĂNG
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded border bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground"
                                >
                                    Đã hủy
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-muted-foreground">
                                {{ po.created_at }}
                            </td>
                            <td class="space-x-2 px-6 py-4 text-right">
                                <!-- Owner Approval -->
                                <Button
                                    v-if="
                                        po.status === 'pending_approval' &&
                                        isOwner
                                    "
                                    @click="approvePo(po)"
                                    size="sm"
                                    class="h-7 bg-emerald-600 px-2.5 text-xs font-bold text-white hover:bg-emerald-700"
                                >
                                    Duyệt đặt
                                </Button>
                                <!-- Warehouse verification -->
                                <Button
                                    v-if="
                                        [
                                            'approved',
                                            'preparing',
                                            'shipping',
                                            'delivered',
                                            'frozen',
                                        ].includes(po.status)
                                    "
                                    @click="openVerifyModal(po)"
                                    variant="outline"
                                    size="sm"
                                    class="dark:border-emerald-850 h-7 border-emerald-200 px-2.5 text-xs font-bold text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400"
                                >
                                    Đối soát & Giao nhận
                                </Button>
                                <!-- Escrow Actions -->
                                <template
                                    v-if="
                                        po.payment_status === 'escrow_locked' &&
                                        isOwner
                                    "
                                >
                                    <Button
                                        @click="releaseEscrow(po)"
                                        size="sm"
                                        class="h-7 bg-emerald-600 px-2.5 text-xs font-bold text-white hover:bg-emerald-700"
                                        title="Giải ngân tiền thầu thầu ký quỹ cho nhà cung cấp"
                                    >
                                        Giải ngân
                                    </Button>
                                    <Button
                                        @click="refundEscrow(po)"
                                        size="sm"
                                        class="h-7 bg-rose-600 px-2.5 text-xs font-bold text-white hover:bg-rose-700"
                                        title="Hoàn tiền ký quỹ về tài khoản nhà hàng"
                                    >
                                        Hoàn trả
                                    </Button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="purchaseOrders.length === 0">
                            <td
                                colspan="9"
                                class="py-12 text-center font-medium text-muted-foreground"
                            >
                                Chưa phát sinh đơn đặt hàng PO nào.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Tab Content: AI Price Analytics -->
        <div
            v-if="activeTab === 'analytics'"
            class="animate-fade-in grid grid-cols-1 gap-6 lg:grid-cols-4"
        >
            <!-- Left panel: Select supplier/material -->
            <Card class="space-y-4 p-5 lg:col-span-1">
                <div>
                    <Label
                        class="mb-2 block text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >1. Chọn nhà cung cấp</Label
                    >
                    <select
                        v-model="selectedSupplier"
                        @change="selectSupplierForAnalytics(selectedSupplier)"
                        class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                    >
                        <option :value="null" disabled>
                            -- Chọn đối tác --
                        </option>
                        <option
                            v-for="sup in suppliers"
                            :key="sup.id"
                            :value="sup"
                        >
                            {{ sup.name }}
                        </option>
                    </select>
                </div>

                <div v-if="selectedSupplier">
                    <Label
                        class="mb-2 block text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >2. Nguyên vật liệu cung ứng</Label
                    >
                    <div class="max-h-80 space-y-1.5 overflow-y-auto">
                        <button
                            v-for="ing in ingredients.filter(
                                (i) => i.supplier_id === selectedSupplier.id,
                            )"
                            :key="ing.id"
                            @click="selectIngredientForAnalytics(ing)"
                            :class="[
                                'flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left text-xs font-medium transition-colors',
                                selectedIngredient?.id === ing.id
                                    ? 'border-emerald-250 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400'
                                    : 'border-transparent bg-background text-muted-foreground hover:bg-muted',
                            ]"
                        >
                            <span>{{ ing.name }}</span>
                            <span
                                class="font-mono text-[10px] text-muted-foreground"
                                >{{ ing.sku }}</span
                            >
                        </button>
                        <p
                            v-if="
                                ingredients.filter(
                                    (i) =>
                                        i.supplier_id === selectedSupplier.id,
                                ).length === 0
                            "
                            class="py-4 text-center text-xs text-muted-foreground"
                        >
                            Không tìm thấy nguyên liệu của nhà cung cấp này.
                        </p>
                    </div>
                </div>
            </Card>

            <!-- Right panel: Trend analysis -->
            <Card class="flex flex-col justify-between p-6 lg:col-span-3">
                <div
                    v-if="loadingAnalytics"
                    class="flex flex-1 flex-col items-center justify-center space-y-3 py-20"
                >
                    <RefreshCw class="h-8 w-8 animate-spin text-emerald-500" />
                    <p class="text-sm font-medium text-muted-foreground">
                        Đang gọi Python AI microservice phân tích dữ liệu...
                    </p>
                </div>

                <div v-else-if="analyticsData" class="space-y-6">
                    <div
                        class="flex flex-col border-b pb-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h3 class="text-xl font-bold">
                                Xu hướng biến động giá:
                                {{ selectedIngredient?.name }}
                            </h3>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Nhà cung cấp: {{ selectedSupplier?.name }}
                            </p>
                        </div>
                        <div class="mt-2 flex items-center gap-2 sm:mt-0">
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold',
                                    analyticsData.trend === 'upward'
                                        ? 'border-rose-250 bg-rose-50 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-400'
                                        : analyticsData.trend === 'downward'
                                          ? 'border-emerald-250 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-400'
                                          : 'border-border bg-muted text-muted-foreground',
                                ]"
                            >
                                <TrendingUp
                                    v-if="analyticsData.trend === 'upward'"
                                    class="h-4 w-4"
                                />
                                <TrendingDown
                                    v-if="analyticsData.trend === 'downward'"
                                    class="h-4 w-4"
                                />
                                Biên độ: {{ analyticsData.percentage_change }}%
                            </span>
                        </div>
                    </div>

                    <!-- AI Box -->
                    <div
                        class="flex gap-3.5 rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/20"
                    >
                        <Sparkles
                            class="mt-0.5 h-6 w-6 shrink-0 text-emerald-600 dark:text-emerald-400"
                        />
                        <div>
                            <h4
                                class="text-emerald-750 text-sm font-bold dark:text-emerald-400"
                            >
                                Khuyến nghị điều chỉnh giá vốn
                            </h4>
                            <p
                                class="mt-1 text-xs leading-relaxed text-slate-700 dark:text-slate-300"
                            >
                                {{ analyticsData.recommendation }}
                            </p>
                        </div>
                    </div>

                    <!-- Chart -->
                    <div class="rounded-xl border bg-muted/30 p-4">
                        <h4
                            class="mb-4 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Lịch sử giá trung bình theo tháng
                        </h4>
                        <div
                            class="relative flex h-48 w-full items-end justify-around gap-4 pt-6"
                        >
                            <!-- simple grid lines -->
                            <div
                                class="pointer-events-none absolute inset-0 flex flex-col justify-between border-b text-[10px] text-muted-foreground/60"
                            >
                                <div
                                    class="h-0 w-full border-b border-border/30"
                                ></div>
                                <div
                                    class="h-0 w-full border-b border-border/30"
                                ></div>
                                <div
                                    class="h-0 w-full border-b border-border/30"
                                ></div>
                            </div>

                            <div
                                v-for="(
                                    item, idx
                                ) in analyticsData.monthly_averages"
                                :key="idx"
                                class="group z-10 flex h-full flex-1 flex-col items-center justify-end"
                            >
                                <span
                                    class="absolute mb-16 rounded border bg-popover px-2 py-0.5 text-[10px] font-bold text-popover-foreground opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
                                >
                                    {{
                                        Number(item.price).toLocaleString(
                                            'vi-VN',
                                        )
                                    }}đ
                                </span>
                                <div
                                    class="w-full max-w-[40px] rounded-t-md bg-gradient-to-t from-emerald-600 to-teal-400 transition-all hover:from-emerald-500 hover:to-teal-300"
                                    :style="{
                                        height: `${Math.max(15, Math.min(100, (item.price / Math.max(...analyticsData.monthly_averages.map((m: any) => m.price))) * 90))}%`,
                                    }"
                                ></div>
                                <span
                                    class="mt-2 text-[10px] font-medium text-muted-foreground"
                                    >{{ item.period }}</span
                                >
                            </div>

                            <p
                                v-if="
                                    !analyticsData.monthly_averages ||
                                    analyticsData.monthly_averages.length === 0
                                "
                                class="w-full py-10 text-center text-xs text-muted-foreground"
                            >
                                Chưa có đủ dữ liệu lịch sử giá của nhiều tháng.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="flex flex-1 flex-col items-center justify-center py-24 text-muted-foreground"
                >
                    <FileText class="mb-3 h-12 w-12 opacity-40" />
                    <p class="text-sm font-medium">
                        Vui lòng chọn nhà cung cấp và nguyên liệu để phân tích
                        biến động giá.
                    </p>
                </div>
            </Card>
        </div>

        <!-- Tab Content: SLA Scorecard -->
        <div
            v-if="activeTab === 'sla'"
            class="animate-fade-in grid grid-cols-1 gap-6 lg:grid-cols-4"
        >
            <!-- Left panel: Select supplier -->
            <Card class="space-y-4 p-5 lg:col-span-1">
                <div>
                    <Label
                        class="mb-2 block text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >Chọn nhà cung cấp đối soát</Label
                    >
                    <select
                        v-model="selectedSupplier"
                        @change="selectSupplierForSla(selectedSupplier)"
                        class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                    >
                        <option :value="null" disabled>
                            -- Chọn đối tác --
                        </option>
                        <option
                            v-for="sup in suppliers"
                            :key="sup.id"
                            :value="sup"
                        >
                            {{ sup.name }}
                        </option>
                    </select>
                </div>
            </Card>

            <!-- Right panel: SLA report -->
            <Card class="flex flex-col justify-between p-6 lg:col-span-3">
                <div
                    v-if="loadingSla"
                    class="flex flex-1 flex-col items-center justify-center space-y-3 py-20"
                >
                    <RefreshCw class="h-8 w-8 animate-spin text-emerald-500" />
                    <p class="text-sm font-medium text-muted-foreground">
                        Đang tính toán chỉ số SLA...
                    </p>
                </div>

                <div v-else-if="slaData" class="space-y-6">
                    <div
                        class="flex flex-col border-b pb-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h3 class="text-xl font-bold">
                                Báo cáo hiệu suất (SLA):
                                {{ slaData.supplier_name }}
                            </h3>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Phân tích dựa trên
                                {{ slaData.total_orders_analyzed }} đơn đặt hàng
                                đã giao nhận.
                            </p>
                        </div>
                        <div class="mt-2 flex items-center gap-2 sm:mt-0">
                            <span
                                class="border-emerald-250 inline-flex items-center gap-1 rounded-full border bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"
                            >
                                Điểm trung bình: {{ slaData.average_rating }} /
                                5.0 ★
                            </span>
                        </div>
                    </div>

                    <!-- SLA Indicators -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div
                            class="space-y-2 rounded-xl border bg-muted/30 p-4"
                        >
                            <div
                                class="flex items-center justify-between text-xs text-muted-foreground"
                            >
                                <span
                                    >Tỷ lệ giao hàng đúng hạn
                                    (Punctuality)</span
                                >
                                <span
                                    class="text-emerald-650 text-sm font-bold dark:text-emerald-400"
                                    >{{ slaData.on_time_rate }}%</span
                                >
                            </div>
                            <div
                                class="h-2 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-emerald-500 transition-all"
                                    :style="{
                                        width: `${slaData.on_time_rate}%`,
                                    }"
                                ></div>
                            </div>
                        </div>

                        <div
                            class="space-y-2 rounded-xl border bg-muted/30 p-4"
                        >
                            <div
                                class="flex items-center justify-between text-xs text-muted-foreground"
                            >
                                <span
                                    >Độ chính xác đối soát chéo (Accuracy)</span
                                >
                                <span
                                    class="text-teal-650 text-sm font-bold dark:text-teal-400"
                                    >{{ slaData.accuracy_rate }}%</span
                                >
                            </div>
                            <div
                                class="h-2 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-teal-500 transition-all"
                                    :style="{
                                        width: `${slaData.accuracy_rate}%`,
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Volatility details -->
                    <div class="space-y-3 rounded-xl border bg-muted/20 p-4">
                        <h4
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Chỉ số biến động giá vật tư (Price Volatility Index)
                        </h4>
                        <div class="overflow-x-auto">
                            <table
                                class="w-full text-left text-xs text-foreground"
                            >
                                <thead
                                    class="border-b bg-muted/40 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="px-4 py-2">Tên vật tư</th>
                                        <th class="px-4 py-2 text-center">
                                            SKU
                                        </th>
                                        <th class="px-4 py-2 text-right">
                                            Giá hiện tại
                                        </th>
                                        <th class="px-4 py-2 text-center">
                                            Số lần thay đổi
                                        </th>
                                        <th class="px-4 py-2 text-right">
                                            Độ biến động (%)
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in slaData.price_volatility"
                                        :key="item.sku"
                                        class="border-b border-border/50 hover:bg-muted/10"
                                    >
                                        <td class="px-4 py-2 font-medium">
                                            {{ item.ingredient_name }}
                                        </td>
                                        <td
                                            class="px-4 py-2 text-center font-mono text-muted-foreground"
                                        >
                                            {{ item.sku }}
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            {{
                                                Number(
                                                    item.current_price,
                                                ).toLocaleString('vi-VN')
                                            }}đ
                                        </td>
                                        <td
                                            class="px-4 py-2 text-center font-mono text-muted-foreground"
                                        >
                                            {{ item.price_history_count }}
                                        </td>
                                        <td
                                            class="px-4 py-2 text-right font-mono font-bold"
                                            :class="
                                                item.volatility_percent > 10
                                                    ? 'text-rose-600 dark:text-rose-400'
                                                    : 'text-emerald-600 dark:text-emerald-400'
                                            "
                                        >
                                            {{ item.volatility_percent }}%
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            !slaData.price_volatility ||
                                            slaData.price_volatility.length ===
                                                0
                                        "
                                    >
                                        <td
                                            colspan="5"
                                            class="py-4 text-center text-muted-foreground"
                                        >
                                            Không có dữ liệu lịch sử biến động
                                            giá.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Ratings -->
                    <div class="space-y-3">
                        <h4
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Đánh giá & Phản hồi gần đây
                        </h4>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div
                                v-for="rate in slaData.recent_ratings.slice(
                                    0,
                                    4,
                                )"
                                :key="rate.po_number"
                                class="space-y-1.5 rounded-xl border bg-muted/30 p-3.5"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        class="dark:text-emerald-450 font-mono text-xs font-bold text-emerald-600"
                                        >{{ rate.po_number }}</span
                                    >
                                    <span
                                        class="text-xs font-bold text-amber-500"
                                        >{{
                                            '★'.repeat(
                                                Number(rate.rating || 5),
                                            ) +
                                            '☆'.repeat(
                                                5 - Number(rate.rating || 5),
                                            )
                                        }}</span
                                    >
                                </div>
                                <p class="text-xs text-foreground italic">
                                    "{{
                                        rate.rating_notes ||
                                        'Không có ghi chú thêm.'
                                    }}"
                                </p>
                                <p
                                    class="text-right text-[10px] text-muted-foreground"
                                >
                                    {{ rate.delivered_at }}
                                </p>
                            </div>
                            <p
                                v-if="
                                    !slaData.recent_ratings ||
                                    slaData.recent_ratings.length === 0
                                "
                                class="col-span-full py-4 text-center text-xs text-muted-foreground"
                            >
                                Chưa có đánh giá sao nào cho nhà cung cấp này.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="flex flex-1 flex-col items-center justify-center py-24 text-muted-foreground"
                >
                    <FileText class="mb-3 h-12 w-12 opacity-40" />
                    <p class="text-sm font-medium">
                        Vui lòng chọn nhà cung cấp để hiển thị báo cáo SLA.
                    </p>
                </div>
            </Card>
        </div>

        <!-- Tab Content: Aggregate SLA Dashboard -->
        <div
            v-if="activeTab === 'sla-dashboard'"
            class="animate-fade-in space-y-6"
        >
            <!-- Dashboard Header -->
            <div class="flex items-center justify-between border-b pb-4">
                <div>
                    <h3 class="flex items-center gap-2 text-lg font-bold">
                        <Award class="h-5 w-5 text-amber-500" />
                        Bảng Xếp Hạng Nhà Cung Cấp (SLA Dashboard)
                    </h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Chấm điểm tổng hợp dựa trên: Giao đúng hạn (40%), Độ
                        chính xác (40%), Chất lượng đánh giá (20%).
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        @click="exportSlaReport"
                        variant="outline"
                        class="flex h-9 cursor-pointer items-center gap-1.5 border-amber-500/30 bg-amber-500/10 text-xs font-semibold text-amber-600 hover:bg-amber-500/20 dark:text-amber-400"
                    >
                        <FileText class="h-4 w-4" />
                        Xuất Báo Cáo SLA (.csv)
                    </Button>
                    <Button
                        @click="fetchSlaDashboard"
                        variant="outline"
                        :disabled="loadingSlaDashboard"
                        class="flex h-9 cursor-pointer items-center gap-1.5 text-xs font-semibold"
                    >
                        <RefreshCw
                            class="h-4 w-4"
                            :class="loadingSlaDashboard ? 'animate-spin' : ''"
                        />
                        Làm mới dữ liệu
                    </Button>
                </div>
            </div>

            <!-- Loading -->
            <div
                v-if="loadingSlaDashboard"
                class="flex flex-col items-center justify-center space-y-3 py-20"
            >
                <RefreshCw class="h-8 w-8 animate-spin text-amber-500" />
                <p class="text-sm font-medium text-muted-foreground">
                    Đang tổng hợp dữ liệu SLA toàn bộ nhà cung cấp...
                </p>
            </div>

            <template v-else-if="slaDashboardData">
                <!-- Aggregate Summary Cards -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card
                        class="border-l-4 border-l-emerald-500 bg-gradient-to-r from-emerald-50/50 to-transparent p-4 dark:from-emerald-950/20"
                    >
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Tổng nhà cung cấp
                        </p>
                        <p
                            class="mt-1 text-2xl font-extrabold text-emerald-600 dark:text-emerald-400"
                        >
                            {{ slaDashboardData.suppliers?.length || 0 }}
                        </p>
                    </Card>
                    <Card
                        class="border-l-4 border-l-blue-500 bg-gradient-to-r from-blue-50/50 to-transparent p-4 dark:from-blue-950/20"
                    >
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            TB Giao đúng hạn
                        </p>
                        <p
                            class="mt-1 text-2xl font-extrabold text-blue-600 dark:text-blue-400"
                        >
                            {{
                                slaDashboardData.suppliers?.length > 0
                                    ? (
                                          slaDashboardData.suppliers.reduce(
                                              (s: number, sup: any) =>
                                                  s +
                                                  Number(sup.on_time_rate || 0),
                                              0,
                                          ) / slaDashboardData.suppliers.length
                                      ).toFixed(1)
                                    : '—'
                            }}%
                        </p>
                    </Card>
                    <Card
                        class="border-l-4 border-l-teal-500 bg-gradient-to-r from-teal-50/50 to-transparent p-4 dark:from-teal-950/20"
                    >
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            TB Độ chính xác
                        </p>
                        <p
                            class="mt-1 text-2xl font-extrabold text-teal-600 dark:text-teal-400"
                        >
                            {{
                                slaDashboardData.suppliers?.length > 0
                                    ? (
                                          slaDashboardData.suppliers.reduce(
                                              (s: number, sup: any) =>
                                                  s +
                                                  Number(
                                                      sup.accuracy_rate || 0,
                                                  ),
                                              0,
                                          ) / slaDashboardData.suppliers.length
                                      ).toFixed(1)
                                    : '—'
                            }}%
                        </p>
                    </Card>
                    <Card
                        class="border-l-4 border-l-amber-500 bg-gradient-to-r from-amber-50/50 to-transparent p-4 dark:from-amber-950/20"
                    >
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            TB Đánh giá sao
                        </p>
                        <p
                            class="mt-1 text-2xl font-extrabold text-amber-600 dark:text-amber-400"
                        >
                            {{
                                slaDashboardData.suppliers?.length > 0
                                    ? (
                                          slaDashboardData.suppliers.reduce(
                                              (s: number, sup: any) =>
                                                  s +
                                                  Number(
                                                      sup.average_rating || 0,
                                                  ),
                                              0,
                                          ) / slaDashboardData.suppliers.length
                                      ).toFixed(2)
                                    : '—'
                            }}
                            ★
                        </p>
                    </Card>
                </div>

                <!-- Supplier Ranking Table -->
                <Card class="overflow-hidden">
                    <div class="border-b p-4">
                        <h4
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <BarChart3 class="h-4 w-4 text-amber-500" />
                            Xếp hạng nhà cung cấp theo chỉ số SLA tổng hợp
                        </h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-foreground">
                            <thead
                                class="border-b border-border bg-muted/40 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th class="w-12 px-5 py-3">#</th>
                                    <th class="px-5 py-3">Nhà cung cấp</th>
                                    <th class="px-5 py-3 text-center">
                                        Tổng PO
                                    </th>
                                    <th class="px-5 py-3 text-center">
                                        Đúng hạn (%)
                                    </th>
                                    <th class="px-5 py-3 text-center">
                                        Chính xác (%)
                                    </th>
                                    <th class="px-5 py-3 text-center">
                                        Đánh giá (★)
                                    </th>
                                    <th class="px-5 py-3 text-center">
                                        Điểm tổng hợp
                                    </th>
                                    <th class="px-5 py-3 text-center">Hạng</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="(
                                        sup, idx
                                    ) in slaDashboardData.suppliers"
                                    :key="sup.supplier_id"
                                    class="transition-colors hover:bg-muted/10"
                                    :class="
                                        idx === 0
                                            ? 'bg-amber-50/30 dark:bg-amber-950/10'
                                            : ''
                                    "
                                >
                                    <td
                                        class="px-5 py-3 text-lg font-extrabold"
                                    >
                                        <span
                                            v-if="idx === 0"
                                            class="text-amber-500"
                                            >🥇</span
                                        >
                                        <span
                                            v-else-if="idx === 1"
                                            class="text-slate-400"
                                            >🥈</span
                                        >
                                        <span
                                            v-else-if="idx === 2"
                                            class="text-amber-700"
                                            >🥉</span
                                        >
                                        <span
                                            v-else
                                            class="text-sm text-muted-foreground"
                                            >{{ idx + 1 }}</span
                                        >
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="font-bold">
                                            {{ sup.supplier_name }}
                                        </p>
                                    </td>
                                    <td
                                        class="px-5 py-3 text-center font-semibold text-muted-foreground"
                                    >
                                        {{ sup.total_orders_analyzed }}
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <div
                                            class="flex flex-col items-center gap-1"
                                        >
                                            <span
                                                class="font-bold"
                                                :class="
                                                    Number(sup.on_time_rate) >=
                                                    80
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : Number(
                                                                sup.on_time_rate,
                                                            ) >= 60
                                                          ? 'text-amber-600 dark:text-amber-400'
                                                          : 'text-rose-600 dark:text-rose-400'
                                                "
                                            >
                                                {{ sup.on_time_rate }}%
                                            </span>
                                            <div
                                                class="h-1.5 w-16 overflow-hidden rounded-full bg-muted"
                                            >
                                                <div
                                                    class="h-full rounded-full transition-all"
                                                    :class="
                                                        Number(
                                                            sup.on_time_rate,
                                                        ) >= 80
                                                            ? 'bg-emerald-500'
                                                            : Number(
                                                                    sup.on_time_rate,
                                                                ) >= 60
                                                              ? 'bg-amber-500'
                                                              : 'bg-rose-500'
                                                    "
                                                    :style="{
                                                        width: `${sup.on_time_rate}%`,
                                                    }"
                                                ></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <div
                                            class="flex flex-col items-center gap-1"
                                        >
                                            <span
                                                class="font-bold"
                                                :class="
                                                    Number(sup.accuracy_rate) >=
                                                    80
                                                        ? 'text-teal-600 dark:text-teal-400'
                                                        : Number(
                                                                sup.accuracy_rate,
                                                            ) >= 60
                                                          ? 'text-amber-600 dark:text-amber-400'
                                                          : 'text-rose-600 dark:text-rose-400'
                                                "
                                            >
                                                {{ sup.accuracy_rate }}%
                                            </span>
                                            <div
                                                class="h-1.5 w-16 overflow-hidden rounded-full bg-muted"
                                            >
                                                <div
                                                    class="h-full rounded-full transition-all"
                                                    :class="
                                                        Number(
                                                            sup.accuracy_rate,
                                                        ) >= 80
                                                            ? 'bg-teal-500'
                                                            : Number(
                                                                    sup.accuracy_rate,
                                                                ) >= 60
                                                              ? 'bg-amber-500'
                                                              : 'bg-rose-500'
                                                    "
                                                    :style="{
                                                        width: `${sup.accuracy_rate}%`,
                                                    }"
                                                ></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span class="font-bold text-amber-500"
                                            >{{ sup.average_rating }} ★</span
                                        >
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span
                                            class="text-lg font-extrabold"
                                            :class="
                                                getSupplierGrade(
                                                    Number(sup.on_time_rate),
                                                    Number(sup.accuracy_rate),
                                                    Number(sup.average_rating),
                                                ).color.includes('emerald')
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : getSupplierGrade(
                                                            Number(
                                                                sup.on_time_rate,
                                                            ),
                                                            Number(
                                                                sup.accuracy_rate,
                                                            ),
                                                            Number(
                                                                sup.average_rating,
                                                            ),
                                                        ).color.includes('blue')
                                                      ? 'text-blue-600 dark:text-blue-400'
                                                      : getSupplierGrade(
                                                              Number(
                                                                  sup.on_time_rate,
                                                              ),
                                                              Number(
                                                                  sup.accuracy_rate,
                                                              ),
                                                              Number(
                                                                  sup.average_rating,
                                                              ),
                                                          ).color.includes(
                                                              'amber',
                                                          )
                                                        ? 'text-amber-600 dark:text-amber-400'
                                                        : 'text-rose-600 dark:text-rose-400'
                                            "
                                        >
                                            {{
                                                (
                                                    Number(sup.on_time_rate) *
                                                        0.4 +
                                                    Number(sup.accuracy_rate) *
                                                        0.4 +
                                                    (Number(
                                                        sup.average_rating,
                                                    ) /
                                                        5) *
                                                        100 *
                                                        0.2
                                                ).toFixed(1)
                                            }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span
                                            :class="[
                                                'inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-extrabold tracking-wider uppercase',
                                                getSupplierGrade(
                                                    Number(sup.on_time_rate),
                                                    Number(sup.accuracy_rate),
                                                    Number(sup.average_rating),
                                                ).color,
                                            ]"
                                        >
                                            {{
                                                getSupplierGrade(
                                                    Number(sup.on_time_rate),
                                                    Number(sup.accuracy_rate),
                                                    Number(sup.average_rating),
                                                ).label
                                            }}
                                        </span>
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        !slaDashboardData.suppliers ||
                                        slaDashboardData.suppliers.length === 0
                                    "
                                >
                                    <td colspan="8" class="py-16 text-center">
                                        <ShieldCheck
                                            class="mx-auto mb-3 h-10 w-10 text-muted-foreground opacity-40"
                                        />
                                        <p
                                            class="text-sm font-medium text-muted-foreground"
                                        >
                                            Chưa có dữ liệu SLA để xếp hạng.
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            Hãy giao nhận ít nhất một đơn PO để
                                            hệ thống bắt đầu chấm điểm.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </template>

            <!-- Empty / Not loaded yet -->
            <div
                v-else
                class="flex flex-col items-center justify-center py-24 text-muted-foreground"
            >
                <Award class="mb-3 h-12 w-12 opacity-40" />
                <p class="text-sm font-medium">
                    Nhấn "Làm mới dữ liệu" để tải bảng xếp hạng nhà cung cấp.
                </p>
            </div>
        </div>

        <!-- Tab Content: Internal Transfers -->
        <div v-if="activeTab === 'transfers'" class="space-y-6">
            <!-- Header Actions -->
            <div class="flex items-center justify-between border-b pb-4">
                <div>
                    <h3 class="text-lg font-bold">
                        Điều phối & Luân chuyển Kho Liên chi nhánh
                    </h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Tối ưu hóa tồn kho toàn chuỗi bằng AI: Tự động phát hiện
                        chi nhánh thiếu hụt và chuyển hàng từ chi nhánh thừa.
                    </p>
                </div>
                <Button
                    v-if="isOwner"
                    @click="openManualTransferModal"
                    class="flex h-9 items-center gap-1.5 bg-emerald-600 text-xs font-semibold text-white shadow-md hover:bg-emerald-700"
                >
                    <Plus class="h-4 w-4" />
                    Tạo lệnh luân chuyển thủ công
                </Button>
            </div>

            <!-- Loading State -->
            <div
                v-if="loadingTransfers"
                class="flex flex-col items-center justify-center space-y-3 py-20"
            >
                <RefreshCw class="h-8 w-8 animate-spin text-emerald-500" />
                <p class="text-sm font-medium text-muted-foreground">
                    Đang đồng bộ dữ liệu tồn kho toàn chuỗi...
                </p>
            </div>

            <template v-else>
                <!-- AI Recommendations Section -->
                <div class="space-y-4">
                    <h4
                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <Sparkles class="h-4 w-4 text-purple-500" />
                        Đề xuất tối ưu tồn kho bằng AI
                    </h4>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <Card
                            v-for="(rec, idx) in transferRecommendations"
                            :key="idx"
                            class="relative overflow-hidden border-l-4 border-l-purple-500 transition-all hover:shadow-md"
                        >
                            <CardHeader
                                class="flex flex-row items-start justify-between space-y-0 pb-3"
                            >
                                <div>
                                    <span
                                        class="rounded-full bg-purple-50 px-2 py-0.5 text-[10px] font-bold tracking-wider text-purple-700 uppercase dark:bg-purple-950/40 dark:text-purple-400"
                                    >
                                        Đề xuất AI #{{ idx + 1 }}
                                    </span>
                                    <CardTitle
                                        class="mt-1.5 text-base font-bold"
                                        >{{ rec.ingredient_name }}</CardTitle
                                    >
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-muted-foreground">
                                        Lượng đề xuất chuyển
                                    </p>
                                    <p
                                        class="text-lg font-extrabold text-purple-600 dark:text-purple-400"
                                    >
                                        {{ rec.suggested_quantity }}
                                        <span
                                            class="text-xs font-normal text-muted-foreground"
                                            >{{ rec.unit_symbol }}</span
                                        >
                                    </p>
                                </div>
                            </CardHeader>

                            <CardContent class="space-y-3">
                                <!-- Transfer flow diagram -->
                                <div
                                    class="flex items-center justify-between rounded-xl bg-muted/30 p-2.5 text-xs"
                                >
                                    <div class="text-left">
                                        <p
                                            class="text-[9px] font-bold text-muted-foreground uppercase"
                                        >
                                            Từ chi nhánh
                                        </p>
                                        <p
                                            class="mt-0.5 font-semibold text-slate-800 dark:text-slate-200"
                                        >
                                            {{ rec.from_branch_name }}
                                        </p>
                                    </div>
                                    <ArrowLeftRight
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <div class="text-right">
                                        <p
                                            class="text-[9px] font-bold text-muted-foreground uppercase"
                                        >
                                            Sang chi nhánh
                                        </p>
                                        <p
                                            class="mt-0.5 font-semibold text-slate-800 dark:text-slate-200"
                                        >
                                            {{ rec.to_branch_name }}
                                        </p>
                                    </div>
                                </div>

                                <!-- AI Reason description -->
                                <div
                                    class="border-slate-250 border-l-2 pl-3 text-xs leading-relaxed text-slate-600 dark:border-slate-800 dark:text-slate-400"
                                >
                                    {{ rec.reason }}
                                </div>

                                <div
                                    class="mt-2 flex gap-2 border-t border-border pt-2"
                                >
                                    <Button
                                        @click="executeTransfer(rec)"
                                        class="flex h-8 w-full items-center justify-center gap-1 bg-purple-600 text-xs font-bold text-white hover:bg-purple-700"
                                    >
                                        <CheckCircle class="h-3.5 w-3.5" />
                                        Chấp nhận luân chuyển
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <div
                            v-if="transferRecommendations.length === 0"
                            class="col-span-full rounded-2xl border border-dashed border-border bg-muted/20 py-10 text-center"
                        >
                            <Check
                                class="mx-auto mb-2 h-8 w-8 text-emerald-500"
                            />
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Tồn kho toàn chuỗi đang ở mức an toàn. Không có
                                đề xuất luân chuyển nào.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Historical Logs Section -->
                <Card class="mt-6 overflow-hidden">
                    <div class="border-b p-4">
                        <h4
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <History class="h-4 w-4" />
                            Nhật ký điều phối kho liên chi nhánh
                        </h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-foreground">
                            <thead
                                class="border-b border-border bg-muted/40 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th class="px-6 py-3">ID</th>
                                    <th class="px-6 py-3">Vật tư</th>
                                    <th class="px-6 py-3">Số lượng</th>
                                    <th class="px-6 py-3">Từ chi nhánh</th>
                                    <th class="px-6 py-3">Đến chi nhánh</th>
                                    <th class="px-6 py-3">Người thực hiện</th>
                                    <th class="px-6 py-3">Trạng thái</th>
                                    <th class="px-6 py-3">Ghi chú</th>
                                    <th class="px-6 py-3">
                                        Thời điểm hoàn tất
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="log in transferLogs"
                                    :key="log.id"
                                    class="transition-colors hover:bg-muted/10"
                                >
                                    <td class="px-6 py-3 font-mono font-bold">
                                        #{{ log.id }}
                                    </td>
                                    <td class="px-6 py-3 font-bold">
                                        {{ log.ingredient?.name || '—' }}
                                    </td>
                                    <td
                                        class="px-6 py-3 font-semibold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ log.quantity }}
                                        {{ log.ingredient?.unit?.symbol }}
                                    </td>
                                    <td class="px-6 py-3 font-medium">
                                        {{ log.from_branch?.name || '—' }}
                                    </td>
                                    <td class="px-6 py-3 font-medium">
                                        {{ log.to_branch?.name || '—' }}
                                    </td>
                                    <td class="px-6 py-3 text-muted-foreground">
                                        {{ log.creator?.name || '—' }}
                                    </td>
                                    <td class="px-6 py-3">
                                        <span
                                            class="border-emerald-250 inline-flex items-center rounded border bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"
                                        >
                                            Hoàn tất
                                        </span>
                                    </td>
                                    <td
                                        class="max-w-xs truncate px-6 py-3 text-muted-foreground"
                                        :title="log.notes"
                                    >
                                        {{ log.notes }}
                                    </td>
                                    <td class="px-6 py-3 text-muted-foreground">
                                        {{
                                            log.completed_at
                                                ? new Date(
                                                      log.completed_at,
                                                  ).toLocaleString('vi-VN')
                                                : '—'
                                        }}
                                    </td>
                                </tr>
                                <tr v-if="transferLogs.length === 0">
                                    <td
                                        colspan="9"
                                        class="py-8 text-center text-muted-foreground"
                                    >
                                        Chưa có lịch sử lệnh luân chuyển kho nội
                                        bộ nào.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </template>
        </div>

        <!-- Manual Transfer Modal -->
        <div
            v-if="showManualTransferModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4 backdrop-blur-xs"
        >
            <div class="flex min-h-full items-center justify-center">
                <Card
                    class="w-full max-w-lg animate-in overflow-hidden shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between border-b pb-4"
                    >
                        <CardTitle class="text-lg font-bold"
                            >Tạo Lệnh Luân Chuyển Kho Thủ Công</CardTitle
                        >
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="showManualTransferModal = false"
                            class="h-8 w-8 text-muted-foreground"
                        >
                            <X class="h-5 w-5" />
                        </Button>
                    </CardHeader>

                    <form
                        @submit.prevent="submitManualTransfer"
                        class="space-y-4 p-6"
                    >
                        <div class="grid grid-cols-2 gap-4">
                            <!-- From Branch -->
                            <div class="space-y-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Từ chi nhánh (Xuất)</Label
                                >
                                <select
                                    v-model="transferForm.from_branch_id"
                                    required
                                    class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                                >
                                    <option
                                        v-for="branch in branches"
                                        :key="branch.id"
                                        :value="branch.id"
                                    >
                                        {{ branch.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- To Branch -->
                            <div class="space-y-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Sang chi nhánh (Nhập)</Label
                                >
                                <select
                                    v-model="transferForm.to_branch_id"
                                    required
                                    class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                                >
                                    <option
                                        v-for="branch in branches.filter(
                                            (b) =>
                                                b.id !==
                                                Number(
                                                    transferForm.from_branch_id,
                                                ),
                                        )"
                                        :key="branch.id"
                                        :value="branch.id"
                                    >
                                        {{ branch.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Ingredient -->
                            <div class="col-span-2 space-y-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Nguyên vật liệu luân chuyển</Label
                                >
                                <select
                                    v-model="transferForm.ingredient_id"
                                    required
                                    class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                                >
                                    <option
                                        v-for="ing in ingredients"
                                        :key="ing.id"
                                        :value="ing.id"
                                    >
                                        {{ ing.name }} (SKU: {{ ing.sku }})
                                    </option>
                                </select>
                            </div>

                            <!-- Quantity -->
                            <div class="col-span-2 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <Label
                                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >Số lượng cần chuyển</Label
                                    >
                                    <span
                                        class="text-xs font-semibold text-muted-foreground"
                                    >
                                        Tồn hiện có:
                                        <strong
                                            class="text-indigo-650 font-extrabold text-indigo-600 dark:text-indigo-400"
                                            >{{
                                                sourceStockOnHand.toFixed(3)
                                            }}</strong
                                        >
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
                                <div
                                    v-if="transferQuantityWarning"
                                    class="mt-1.5 rounded-xl border p-3 text-xs font-medium"
                                    :class="
                                        transferQuantityWarning.type === 'error'
                                            ? 'border-rose-250 bg-rose-50/50 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400'
                                            : 'border-amber-250 bg-amber-50/50 text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-400'
                                    "
                                >
                                    <div class="flex items-start gap-2">
                                        <AlertTriangle
                                            class="mt-0.5 size-4 shrink-0"
                                            :class="
                                                transferQuantityWarning.type ===
                                                'error'
                                                    ? 'text-rose-500'
                                                    : 'text-amber-500'
                                            "
                                        />
                                        <span>{{
                                            transferQuantityWarning.message
                                        }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="col-span-2 space-y-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Lý do / Ghi chú</Label
                                >
                                <textarea
                                    v-model="transferForm.notes"
                                    rows="3"
                                    placeholder="Ghi chú lý do luân chuyển kho..."
                                    class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:outline-none"
                                ></textarea>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-2 border-t pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                @click="showManualTransferModal = false"
                            >
                                Hủy bỏ
                            </Button>
                            <Button
                                type="submit"
                                :disabled="
                                    transferForm.processing ||
                                    (transferQuantityWarning &&
                                        transferQuantityWarning.type ===
                                            'error')
                                "
                                class="bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                            >
                                {{
                                    transferForm.processing
                                        ? 'Đang thực hiện...'
                                        : 'Thực hiện chuyển kho'
                                }}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </div>

        <!-- Add/Edit Supplier Modals -->
        <div
            v-if="showAddModal || showEditModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/60 p-4 backdrop-blur-sm"
        >
            <div class="flex min-h-full items-center justify-center">
                <Card
                    class="relative w-full max-w-lg animate-in overflow-hidden rounded-2xl border-border bg-card/95 shadow-2xl backdrop-blur-md duration-150 zoom-in-95 fade-in"
                >
                    <!-- Top Accent Line -->
                    <div
                        class="absolute top-0 left-0 h-1 w-full bg-gradient-to-r from-emerald-500 to-teal-400"
                    ></div>

                    <CardHeader
                        class="flex flex-row items-center justify-between border-b border-border/60 pt-5 pb-4"
                    >
                        <div>
                            <CardTitle
                                class="text-base font-extrabold tracking-tight text-foreground"
                                >{{
                                    showAddModal
                                        ? 'Thêm nhà cung cấp mới'
                                        : 'Chỉnh sửa nhà cung cấp'
                                }}</CardTitle
                            >
                            <p class="mt-0.5 text-[10px] text-muted-foreground">
                                Vui lòng điền thông tin đối tác cung cấp nguyên
                                vật liệu.
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="
                                showAddModal = false;
                                showEditModal = false;
                            "
                            class="h-8 w-8 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </CardHeader>

                    <form @submit.prevent="saveSupplier" class="space-y-5 p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Supplier Name -->
                            <div class="col-span-2 space-y-1.5">
                                <Label
                                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <Building
                                        class="h-3.5 w-3.5 text-emerald-500"
                                    />
                                    Tên nhà cung cấp
                                    <span class="text-rose-500">*</span>
                                </Label>
                                <div class="relative">
                                    <span
                                        class="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground/60"
                                    >
                                        <Building class="h-4 w-4" />
                                    </span>
                                    <Input
                                        v-model="supplierForm.name"
                                        required
                                        type="text"
                                        placeholder="Nhập tên doanh nghiệp / hộ kinh doanh..."
                                        class="h-9.5 rounded-xl border-border/60 bg-background pl-9 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20"
                                    />
                                </div>
                            </div>

                            <!-- Contact Name -->
                            <div class="space-y-1.5">
                                <Label
                                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <User
                                        class="h-3.5 w-3.5 text-emerald-500"
                                    />
                                    Người đại diện
                                </Label>
                                <div class="relative">
                                    <span
                                        class="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground/60"
                                    >
                                        <User class="h-4 w-4" />
                                    </span>
                                    <Input
                                        v-model="supplierForm.contact_name"
                                        type="text"
                                        placeholder="Tên người liên hệ..."
                                        class="h-9.5 rounded-xl border-border/60 bg-background pl-9 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20"
                                    />
                                </div>
                            </div>

                            <!-- Phone -->
                            <div class="space-y-1.5">
                                <Label
                                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <Phone
                                        class="h-3.5 w-3.5 text-emerald-500"
                                    />
                                    Điện thoại
                                </Label>
                                <div class="relative">
                                    <span
                                        class="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground/60"
                                    >
                                        <Phone class="h-4 w-4" />
                                    </span>
                                    <Input
                                        v-model="supplierForm.phone"
                                        type="text"
                                        placeholder="Số điện thoại..."
                                        class="h-9.5 rounded-xl border-border/60 bg-background pl-9 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20"
                                    />
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="col-span-2 space-y-1.5">
                                <Label
                                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <Mail
                                        class="h-3.5 w-3.5 text-emerald-500"
                                    />
                                    Email liên hệ
                                </Label>
                                <div class="relative">
                                    <span
                                        class="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground/60"
                                    >
                                        <Mail class="h-4 w-4" />
                                    </span>
                                    <Input
                                        v-model="supplierForm.email"
                                        type="email"
                                        placeholder="example@supplier.com"
                                        class="h-9.5 rounded-xl border-border/60 bg-background pl-9 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20"
                                    />
                                </div>
                            </div>

                            <!-- Tax Code (MST) -->
                            <div class="space-y-1.5">
                                <Label
                                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <FileText
                                        class="h-3.5 w-3.5 text-emerald-500"
                                    />
                                    Mã số thuế (MST)
                                </Label>
                                <Input
                                    v-model="supplierForm.tax_code"
                                    type="text"
                                    placeholder="VD: 0312345678"
                                    class="h-9.5 rounded-xl border-border/60 bg-background focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20"
                                />
                            </div>

                            <!-- Category -->
                            <div class="space-y-1.5">
                                <Label
                                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <Package
                                        class="h-3.5 w-3.5 text-emerald-500"
                                    />
                                    Ngành hàng cung ứng
                                </Label>
                                <select
                                    v-model="supplierForm.category"
                                    class="h-9.5 w-full rounded-xl border border-border/60 bg-background px-3 py-1 text-sm font-medium text-foreground focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                                >
                                    <option value="fresh_food">
                                        🥬 Thực phẩm tươi sống
                                    </option>
                                    <option value="dry_goods">
                                        🧂 Gia vị & Thực phẩm khô
                                    </option>
                                    <option value="beverage">
                                        🥤 Đồ uống & Giải khát
                                    </option>
                                    <option value="packaging">
                                        📦 Bao bì & Vật tư F&B
                                    </option>
                                    <option value="other">📑 Khác</option>
                                </select>
                            </div>

                            <!-- Banking Information Header -->
                            <div
                                class="col-span-2 border-t border-border/50 pt-2"
                            >
                                <p
                                    class="flex items-center gap-1.5 text-[11px] font-extrabold tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                                >
                                    <CreditCard
                                        class="h-3.5 w-3.5"
                                        v-if="false"
                                    />
                                    💳 Thông tin Thanh toán VietQR & Công nợ
                                </p>
                            </div>

                            <!-- Bank Name -->
                            <div class="space-y-1.5">
                                <Label
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Tên ngân hàng
                                </Label>
                                <Input
                                    v-model="supplierForm.bank_name"
                                    type="text"
                                    placeholder="VD: Vietcombank, MBBank..."
                                    class="h-9.5 rounded-xl border-border/60 bg-background focus-visible:border-emerald-500"
                                />
                            </div>

                            <!-- Bank Account Number -->
                            <div class="space-y-1.5">
                                <Label
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Số tài khoản
                                </Label>
                                <Input
                                    v-model="supplierForm.bank_account_number"
                                    type="text"
                                    placeholder="STK nhận tiền..."
                                    class="h-9.5 rounded-xl border-border/60 bg-background focus-visible:border-emerald-500"
                                />
                            </div>

                            <!-- Bank Account Holder -->
                            <div class="space-y-1.5">
                                <Label
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Tên chủ tài khoản
                                </Label>
                                <Input
                                    v-model="supplierForm.bank_account_holder"
                                    type="text"
                                    placeholder="CHỦ TÀI KHOẢN..."
                                    class="h-9.5 rounded-xl border-border/60 bg-background focus-visible:border-emerald-500"
                                />
                            </div>

                            <!-- Payment Terms -->
                            <div class="space-y-1.5">
                                <Label
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Điều khoản thanh toán
                                </Label>
                                <select
                                    v-model="supplierForm.payment_terms"
                                    class="h-9.5 w-full rounded-xl border border-border/60 bg-background px-3 py-1 text-sm font-medium text-foreground focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                                >
                                    <option value="cod">
                                        💵 Thanh toán ngay khi nhận hàng (COD)
                                    </option>
                                    <option value="net7">
                                        📅 Công nợ 7 ngày (Net 7)
                                    </option>
                                    <option value="net15">
                                        📅 Công nợ 15 ngày (Net 15)
                                    </option>
                                    <option value="net30">
                                        📅 Công nợ 30 ngày (Net 30)
                                    </option>
                                    <option value="prepaid">
                                        💳 Trả trước 100% (Prepaid)
                                    </option>
                                </select>
                            </div>

                            <!-- Address -->
                            <div
                                class="col-span-2 space-y-1.5 border-t border-border/50 pt-2"
                            >
                                <Label
                                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <MapPin
                                        class="h-3.5 w-3.5 text-emerald-500"
                                    />
                                    Địa chỉ kho / Văn phòng
                                </Label>
                                <div class="relative">
                                    <span
                                        class="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground/60"
                                    >
                                        <MapPin class="h-4 w-4" />
                                    </span>
                                    <Input
                                        v-model="supplierForm.address"
                                        type="text"
                                        placeholder="Số nhà, tên đường, tỉnh/thành phố..."
                                        class="h-9.5 rounded-xl border-border/60 bg-background pl-9 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20"
                                    />
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="col-span-2 space-y-1.5">
                                <Label
                                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <ClipboardList
                                        class="h-3.5 w-3.5 text-emerald-500"
                                    />
                                    Ghi chú đối tác
                                </Label>
                                <div class="relative">
                                    <textarea
                                        v-model="supplierForm.notes"
                                        rows="2"
                                        placeholder="Ghi chú về năng lực cung ứng, lịch giao hàng..."
                                        class="w-full rounded-xl border border-border/60 bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground/50 focus-visible:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/20 focus-visible:outline-none"
                                    ></textarea>
                                </div>
                            </div>

                            <!-- Status Select (for edit mode) -->
                            <div
                                v-if="showEditModal"
                                class="col-span-2 space-y-1.5"
                            >
                                <Label
                                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <Info
                                        class="h-3.5 w-3.5 text-emerald-500"
                                    />
                                    Trạng thái hoạt động
                                </Label>
                                <select
                                    v-model="supplierForm.status"
                                    class="h-9.5 w-full rounded-xl border border-border/60 bg-background px-3 py-1 text-sm font-medium text-foreground focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                                >
                                    <option value="active">
                                        Đang hoạt động
                                    </option>
                                    <option value="inactive">Tạm khóa</option>
                                </select>
                            </div>
                        </div>

                        <div
                            class="mt-6 flex justify-end gap-2 border-t border-border/60 pt-4"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                @click="
                                    showAddModal = false;
                                    showEditModal = false;
                                "
                                class="h-9.5 rounded-xl border-border/80 px-4 text-xs font-semibold hover:bg-muted"
                            >
                                Hủy bỏ
                            </Button>
                            <Button
                                type="submit"
                                :disabled="supplierForm.processing"
                                class="flex h-9.5 items-center gap-1.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 px-4 text-xs font-extrabold text-white shadow-lg shadow-emerald-500/10 transition-all duration-300 hover:from-emerald-500 hover:to-teal-400"
                            >
                                <Sparkles
                                    class="h-3.5 w-3.5"
                                    v-if="!supplierForm.processing"
                                />
                                <RefreshCw
                                    class="h-3.5 w-3.5 animate-spin"
                                    v-else
                                />
                                {{
                                    supplierForm.processing
                                        ? 'Đang lưu...'
                                        : 'Lưu thông tin'
                                }}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </div>

        <!-- Place PO Modal (2-Step Workflow) -->
        <div
            v-if="showPoModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4 backdrop-blur-sm"
        >
            <div class="flex min-h-full items-center justify-center">
                <Card
                    class="w-full max-w-2xl animate-in overflow-hidden border-border shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between border-b bg-muted/20 pb-4"
                    >
                        <div>
                            <CardTitle class="text-lg font-bold">
                                Đặt hàng nguyên liệu:
                                {{ selectedSupplier?.name }}
                            </CardTitle>
                            <CardDescription
                                class="mt-0.5 text-xs text-muted-foreground"
                            >
                                {{
                                    poStep === 1
                                        ? 'Bước 1/2: Chọn menu nguyên liệu & số lượng cần mua'
                                        : 'Bước 2/2: Xác nhận danh sách đã chọn, người đặt & giao nhận'
                                }}
                            </CardDescription>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="showPoModal = false"
                            class="h-8 w-8 text-muted-foreground"
                        >
                            <X class="h-5 w-5" />
                        </Button>
                    </CardHeader>

                    <!-- BƯỚC 1: Danh sách toàn bộ sản phẩm/nguyên liệu từ Nhà sản xuất -->
                    <div v-if="poStep === 1" class="space-y-4 p-6">
                        <!-- Thanh tìm kiếm sản phẩm trong Menu -->
                        <div class="relative">
                            <Search
                                class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground"
                            />
                            <Input
                                v-model="menuSearchQuery"
                                placeholder="Tìm kiếm sản phẩm / nguyên liệu của nhà cung cấp..."
                                class="rounded-xl pl-9 text-xs"
                            />
                        </div>

                        <!-- Danh sách Catalog nguyên liệu của Nhà cung cấp -->
                        <div class="max-h-96 space-y-2.5 overflow-y-auto pr-1">
                            <div
                                v-if="filteredSupplierIngredients.length === 0"
                                class="rounded-xl border border-dashed p-4 py-12 text-center text-xs text-muted-foreground italic"
                            >
                                {{
                                    menuSearchQuery
                                        ? 'Không tìm thấy sản phẩm nào phù hợp với từ khóa.'
                                        : 'Nhà cung cấp này chưa có sản phẩm nào niêm yết.'
                                }}
                            </div>

                            <div
                                v-for="ing in filteredSupplierIngredients"
                                :key="ing.id"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3 transition-all duration-200"
                                :class="[
                                    (selectedQuantities[ing.id] || 0) > 0
                                        ? 'border-emerald-500/40 bg-emerald-500/5 dark:bg-emerald-950/20'
                                        : 'border-border/60 bg-muted/20 hover:bg-muted/40',
                                ]"
                            >
                                <!-- Tên sản phẩm & Đơn giá -->
                                <div class="min-w-[180px] flex-1 space-y-0.5">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-xs font-bold text-foreground"
                                        >
                                            {{ ing.name }}
                                        </span>
                                        <span
                                            v-if="
                                                (selectedQuantities[ing.id] ||
                                                    0) > 0
                                            "
                                            class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-[9px] font-bold text-emerald-600 dark:text-emerald-400"
                                        >
                                            Đã chọn
                                        </span>
                                    </div>
                                    <div
                                        class="font-mono text-[11px] text-muted-foreground"
                                    >
                                        Giá niêm yết:
                                        <strong class="text-foreground">
                                            {{
                                                Number(
                                                    ing.price,
                                                ).toLocaleString('vi-VN')
                                            }}đ
                                        </strong>
                                        / {{ ing.unit_symbol }}
                                    </div>
                                </div>

                                <!-- Bộ tăng/giảm số lượng (Quantity Counter) -->
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex items-center rounded-lg border border-border bg-background shadow-xs"
                                    >
                                        <button
                                            type="button"
                                            @click="decrementQuantity(ing.id)"
                                            class="flex h-8 w-8 items-center justify-center font-bold text-muted-foreground transition hover:bg-muted hover:text-foreground active:scale-90"
                                        >
                                            -
                                        </button>
                                        <input
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            :value="
                                                selectedQuantities[ing.id] || 0
                                            "
                                            @input="
                                                updateItemQuantity(
                                                    ing.id,
                                                    Number(
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    ),
                                                )
                                            "
                                            class="h-8 w-16 text-center font-mono text-xs font-bold text-foreground focus:outline-none"
                                        />
                                        <button
                                            type="button"
                                            @click="incrementQuantity(ing.id)"
                                            class="flex h-8 w-8 items-center justify-center font-bold text-muted-foreground transition hover:bg-muted hover:text-foreground active:scale-90"
                                        >
                                            +
                                        </button>
                                    </div>
                                    <span
                                        class="w-8 text-[11px] font-semibold text-muted-foreground"
                                    >
                                        {{ ing.unit_symbol }}
                                    </span>
                                </div>

                                <!-- Thành tiền cho từng món -->
                                <div
                                    class="w-24 text-right font-mono text-xs font-bold"
                                >
                                    <span
                                        :class="[
                                            (selectedQuantities[ing.id] || 0) >
                                            0
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-muted-foreground/40',
                                        ]"
                                    >
                                        {{
                                            (
                                                (selectedQuantities[ing.id] ||
                                                    0) * Number(ing.price || 0)
                                            ).toLocaleString('vi-VN')
                                        }}đ
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Tạm tính tiền & Số lượng món đã chọn -->
                        <div
                            class="flex items-center justify-between rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3.5"
                        >
                            <div class="space-y-0.5">
                                <span
                                    class="text-xs font-bold text-emerald-900 dark:text-emerald-300"
                                >
                                    Đã chọn
                                    {{ selectedPoItemsDetailed.length }} mặt
                                    hàng
                                </span>
                                <span
                                    class="block text-[10px] text-emerald-700/80 dark:text-emerald-400/80"
                                >
                                    Nhấp + / - hoặc nhập số lượng để chọn sản
                                    phẩm
                                </span>
                            </div>
                            <span
                                class="font-mono text-lg font-black text-emerald-600 dark:text-emerald-400"
                            >
                                {{
                                    totalPoEstimatedAmount.toLocaleString(
                                        'vi-VN',
                                    )
                                }}đ
                            </span>
                        </div>

                        <div class="mt-6 flex justify-end gap-2 border-t pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                @click="showPoModal = false"
                            >
                                Hủy bỏ
                            </Button>
                            <Button
                                type="button"
                                @click="goToPoStep2"
                                class="bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                            >
                                Xác nhận & Tiếp tục ({{
                                    selectedPoItemsDetailed.length
                                }}
                                món) ➔
                            </Button>
                        </div>
                    </div>

                    <!-- BƯỚC 2: Kiểm tra danh sách đã chọn & Thông tin người đặt, vị trí quán, SLA, Ghi chú -->
                    <form
                        v-else-if="poStep === 2"
                        @submit.prevent="submitPo"
                        class="space-y-5 p-6"
                    >
                        <!-- Tóm tắt danh sách món đã chọn -->
                        <div class="space-y-2">
                            <div
                                class="flex items-center justify-between text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                <span>📋 Danh sách nguyên liệu đã chọn</span>
                                <button
                                    type="button"
                                    @click="poStep = 1"
                                    class="cursor-pointer text-[11px] font-semibold text-emerald-600 hover:underline"
                                >
                                    ✏️ Đổi món / Sửa số lượng
                                </button>
                            </div>
                            <div
                                class="max-h-48 divide-y divide-border/60 overflow-y-auto rounded-xl border border-border/80"
                            >
                                <div
                                    v-for="item in selectedPoItemsDetailed"
                                    :key="item.id"
                                    class="flex items-center justify-between p-2.5 text-xs hover:bg-muted/20"
                                >
                                    <div class="font-semibold text-foreground">
                                        {{ item.name }}
                                    </div>
                                    <div class="text-right font-mono">
                                        <span
                                            class="mr-2 text-muted-foreground"
                                        >
                                            {{ item.quantity }}
                                            {{ item.unit_symbol }} ×
                                            {{
                                                item.price.toLocaleString(
                                                    'vi-VN',
                                                )
                                            }}đ
                                        </span>
                                        <strong
                                            class="text-emerald-600 dark:text-emerald-400"
                                        >
                                            {{
                                                item.subtotal.toLocaleString(
                                                    'vi-VN',
                                                )
                                            }}đ
                                        </strong>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="space-y-1.5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3.5 py-2.5"
                            >
                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span
                                        class="font-medium text-muted-foreground"
                                        >Tạm tính đơn hàng:</span
                                    >
                                    <span
                                        class="font-mono font-bold text-foreground"
                                        >{{
                                            poSubtotalAmount.toLocaleString(
                                                'vi-VN',
                                            )
                                        }}đ</span
                                    >
                                </div>
                                <div
                                    v-if="poForm.discount_percent > 0"
                                    class="flex items-center justify-between text-xs font-semibold text-rose-500"
                                >
                                    <span
                                        >Chiết khấu nhà cung cấp ({{
                                            poForm.discount_percent
                                        }}%):</span
                                    >
                                    <span class="font-mono"
                                        >-{{
                                            (
                                                (poSubtotalAmount *
                                                    poForm.discount_percent) /
                                                100
                                            ).toLocaleString('vi-VN')
                                        }}đ</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between border-t border-emerald-500/20 pt-1"
                                >
                                    <span
                                        class="text-xs font-bold text-emerald-900 dark:text-emerald-300"
                                    >
                                        TỔNG CỘNG THANH TOÁN:
                                    </span>
                                    <span
                                        class="font-mono text-lg font-black text-emerald-600 dark:text-emerald-400"
                                    >
                                        {{
                                            totalPoEstimatedAmount.toLocaleString(
                                                'vi-VN',
                                            )
                                        }}đ
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Thông tin người đặt & Vị trí quán -->
                        <div
                            class="grid grid-cols-1 gap-3 rounded-xl border border-border bg-muted/20 p-3.5 text-xs md:grid-cols-2"
                        >
                            <div class="space-y-1">
                                <span
                                    class="block text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >👤 Người đặt hàng</span
                                >
                                <div class="font-bold text-foreground">
                                    {{ currentUser?.name || 'Test Enterprise' }}
                                </div>
                                <div class="text-[11px] text-muted-foreground">
                                    {{
                                        currentUser?.email ||
                                        'enterprise@test.com'
                                    }}
                                </div>
                            </div>
                            <div class="space-y-1">
                                <span
                                    class="block text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >📍 Vị trí nhận hàng / Cơ sở quán</span
                                >
                                <div class="font-bold text-foreground">
                                    {{
                                        currentRestaurant?.name ||
                                        'Sai Gon Diner'
                                    }}
                                </div>
                                <div
                                    class="truncate text-[11px] text-muted-foreground"
                                >
                                    {{
                                        currentRestaurant?.address ||
                                        'Cơ sở chính nhà hàng'
                                    }}
                                </div>
                            </div>
                        </div>

                        <!-- Hình thức thanh toán, Chiết khấu %, Phương thức vận chuyển -->
                        <div
                            class="grid grid-cols-1 gap-3 rounded-xl border border-border/70 bg-background p-3.5 md:grid-cols-3"
                        >
                            <div class="space-y-1">
                                <Label
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    💳 Hình thức thanh toán
                                </Label>
                                <select
                                    v-model="poForm.payment_method"
                                    class="h-9 w-full rounded-xl border border-border/60 bg-background px-2.5 text-xs font-medium text-foreground focus:border-emerald-500 focus:outline-none"
                                >
                                    <option value="banking">
                                        🏦 Chuyển khoản Banking (VietQR)
                                    </option>
                                    <option value="cod">
                                        💵 Tiền mặt khi giao hàng (COD)
                                    </option>
                                    <option value="credit">
                                        📝 Ghi nhận Công nợ kỳ sau
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <Label
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    🏷️ Chiết khấu nhà cung cấp (%)
                                </Label>
                                <Input
                                    v-model.number="poForm.discount_percent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.5"
                                    placeholder="0%"
                                    class="h-9 rounded-xl border-border/60 bg-background font-mono text-xs font-bold"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    🚚 Phương thức giao hàng
                                </Label>
                                <select
                                    v-model="poForm.shipping_method"
                                    class="h-9 w-full rounded-xl border border-border/60 bg-background px-2.5 text-xs font-medium text-foreground focus:border-emerald-500 focus:outline-none"
                                >
                                    <option value="supplier_delivery">
                                        🚚 Nhà cung cấp tự giao tận nơi
                                    </option>
                                    <option value="self_pickup">
                                        🏬 Quán tự đến kho lấy hàng
                                    </option>
                                    <option value="express">
                                        🚀 Giao siêu tốc (Ahamove/Grab)
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Chọn thời gian giao hàng & Ghi chú -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    🕒 Hạn giao hàng dự kiến (SLA)
                                    <span class="text-rose-500">*</span>
                                </Label>
                                <Input
                                    v-model="poForm.delivery_due_date"
                                    type="datetime-local"
                                    required
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    📝 Ghi chú giao nhận
                                </Label>
                                <Input
                                    v-model="poForm.notes"
                                    type="text"
                                    placeholder="Yêu cầu đóng gói, giờ hạ hàng..."
                                />
                            </div>
                        </div>

                        <!-- Footer Bước 2 -->
                        <div class="mt-6 flex justify-between border-t pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                @click="poStep = 1"
                            >
                                ⬅ Quay lại chọn món
                            </Button>
                            <Button
                                type="submit"
                                :disabled="poForm.processing"
                                class="bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                            >
                                {{
                                    poForm.processing
                                        ? 'Đang gửi đơn PO...'
                                        : '🚀 Gửi đặt đơn PO'
                                }}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </div>

        <!-- Verify & Deliver Dual-Verification Modal -->
        <div
            v-if="showVerifyModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4 backdrop-blur-sm"
        >
            <div class="flex min-h-full items-center justify-center">
                <Card
                    class="w-full max-w-3xl animate-in overflow-hidden shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between border-b pb-4"
                    >
                        <div>
                            <CardTitle class="text-lg font-bold"
                                >Đối soát kiểm đếm & Nhận hàng</CardTitle
                            >
                            <CardDescription class="mt-0.5 text-[10px]"
                                >Mã PO: {{ selectedPo?.po_number }} • Nhà cung
                                cấp:
                                {{ selectedPo?.supplier_name }}</CardDescription
                            >
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="showVerifyModal = false"
                            class="h-8 w-8 text-muted-foreground"
                        >
                            <X class="h-5 w-5" />
                        </Button>
                    </CardHeader>

                    <form
                        @submit.prevent="submitVerification"
                        class="space-y-6 p-6"
                    >
                        <!-- Check alert box if mismatch is detected -->
                        <div
                            v-if="hasMismatch"
                            class="border-rose-250 flex animate-pulse gap-3 rounded-xl border bg-rose-50 p-4 text-rose-800 dark:border-rose-900 dark:bg-rose-950/40"
                        >
                            <AlertTriangle
                                class="mt-0.5 h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400"
                            />
                            <div class="space-y-1 text-xs">
                                <h4 class="font-bold">
                                    CẢNH BÁO: Phát hiện sai lệch đối soát chéo!
                                </h4>
                                <p
                                    class="dark:text-rose-350 leading-relaxed text-rose-700"
                                >
                                    Số lượng thực tế hoặc đơn giá hóa đơn không
                                    khớp với niêm yết ban đầu. Khi xác nhận, hệ
                                    thống sẽ **ĐÓNG BĂNG giao dịch**, khóa cập
                                    nhật kho tự động, ghi vết kiểm toán gian lận
                                    và gửi cảnh báo đỏ trực tiếp đến Owner.
                                </p>
                            </div>
                        </div>

                        <!-- Items Verification Table -->
                        <div class="overflow-hidden rounded-xl border">
                            <table
                                class="w-full text-left text-xs text-foreground"
                            >
                                <thead
                                    class="border-b bg-muted/40 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="px-4 py-3">
                                            Tên nguyên liệu
                                        </th>
                                        <th class="px-4 py-3 text-center">
                                            Đặt (PO)
                                        </th>
                                        <th class="w-28 px-4 py-3">
                                            Thực nhận
                                        </th>
                                        <th class="px-4 py-3 text-center">
                                            Giá niêm yết
                                        </th>
                                        <th class="w-32 px-4 py-3">
                                            Giá hóa đơn
                                        </th>
                                        <th class="px-4 py-3 text-center">
                                            Khớp
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="(
                                            item, idx
                                        ) in verifyDiscrepancies"
                                        :key="idx"
                                        class="hover:bg-muted/10"
                                    >
                                        <td class="px-4 py-3 font-semibold">
                                            {{ item.ingredient_name }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-center font-semibold text-muted-foreground"
                                        >
                                            {{ item.quantity_ordered }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <input
                                                v-model="item.quantity_received"
                                                @input="
                                                    updateQuantityReceived(
                                                        idx,
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    )
                                                "
                                                type="number"
                                                step="0.001"
                                                class="w-full rounded border border-input bg-background px-2 py-1 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                            />
                                        </td>
                                        <td
                                            class="px-4 py-3 text-center font-semibold text-muted-foreground"
                                        >
                                            {{
                                                Number(
                                                    item.price_per_unit,
                                                ).toLocaleString('vi-VN')
                                            }}đ
                                        </td>
                                        <td class="px-4 py-3">
                                            <div
                                                class="relative flex items-center"
                                            >
                                                <input
                                                    v-model="item.invoice_price"
                                                    @input="
                                                        updateInvoicePrice(
                                                            idx,
                                                            (
                                                                $event.target as HTMLInputElement
                                                            ).value,
                                                        )
                                                    "
                                                    type="number"
                                                    class="w-full rounded border border-input bg-background px-2 py-1 text-xs focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                                />
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span
                                                v-if="!item.mismatch"
                                                class="dark:text-emerald-450 inline-block rounded-full border border-emerald-200 bg-emerald-50 p-1 text-emerald-600 dark:border-emerald-900 dark:bg-emerald-950/40"
                                            >
                                                <Check class="h-3.5 w-3.5" />
                                            </span>
                                            <span
                                                v-else
                                                class="dark:text-rose-450 inline-block animate-bounce rounded-full border border-rose-200 bg-rose-50 p-1 text-rose-600 dark:border-rose-900 dark:bg-rose-950/40"
                                            >
                                                <X class="h-3.5 w-3.5" />
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Invoice Upload -->
                        <div class="space-y-2">
                            <Label
                                class="block text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >Đính kèm hóa đơn đối chiếu (Hóa đơn giấy/điện
                                tử) <span class="text-rose-500">*</span></Label
                            >
                            <div
                                class="relative flex min-h-[120px] flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/20 p-4 transition-colors hover:bg-muted/40"
                            >
                                <div
                                    v-if="loadingOcr"
                                    class="flex flex-col items-center justify-center space-y-2"
                                >
                                    <RefreshCw
                                        class="h-8 w-8 animate-spin text-emerald-500"
                                    />
                                    <span
                                        class="animate-pulse text-xs font-bold text-emerald-600"
                                        >AI OCR: Đang phân tích và quét dữ liệu
                                        hóa đơn...</span
                                    >
                                </div>
                                <template v-else>
                                    <Upload
                                        class="mb-2 h-8 w-8 text-muted-foreground opacity-60"
                                    />
                                    <span
                                        class="text-xs font-semibold text-muted-foreground"
                                        >{{
                                            verifyForm.invoice_file
                                                ? verifyForm.invoice_file.name
                                                : 'Nhấp để chọn tệp chứng từ hoặc kéo thả vào đây'
                                        }}</span
                                    >
                                    <span
                                        class="mt-1 text-[10px] text-muted-foreground"
                                        >Hỗ trợ JPG, PNG, PDF tối đa 4MB. Tự
                                        động quét và điền dữ liệu bằng AI.</span
                                    >
                                    <input
                                        type="file"
                                        @change="handleFileUpload"
                                        class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                    />
                                </template>
                            </div>
                        </div>

                        <!-- Mismatch Reason & Resolution Action (when discrepancies detected) -->
                        <div
                            v-if="hasMismatch"
                            class="grid grid-cols-1 gap-4 rounded-xl border border-rose-500/30 bg-rose-500/5 p-4 md:grid-cols-2"
                        >
                            <div class="space-y-1.5">
                                <Label
                                    class="block text-xs font-bold tracking-wider text-rose-600 uppercase dark:text-rose-400"
                                >
                                    ⚠️ Nguyên nhân phát sinh sai lệch
                                </Label>
                                <select
                                    v-model="verifyForm.mismatch_reason"
                                    class="w-full rounded-xl border border-rose-300 bg-background px-3 py-2 text-xs font-semibold text-foreground focus:ring-2 focus:ring-rose-500/20 focus:outline-none dark:border-rose-800"
                                >
                                    <option value="Khối lượng giao không đủ">
                                        📉 Khối lượng thực giao thiếu so với PO
                                    </option>
                                    <option value="Hàng hư hỏng / Dập nát">
                                        🥀 Hàng hư hỏng / Không đạt chất lượng
                                    </option>
                                    <option value="Sai giá niêm yết">
                                        💲 Đơn giá hóa đơn cao hơn niêm yết
                                    </option>
                                    <option value="Giao sai mặt hàng">
                                        ❌ Giao sai chủng loại nguyên liệu
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <Label
                                    class="block text-xs font-bold tracking-wider text-rose-600 uppercase dark:text-rose-400"
                                >
                                    🛠️ Phương án xử lý khắc phục
                                </Label>
                                <select
                                    v-model="verifyForm.resolution_action"
                                    class="w-full rounded-xl border border-rose-300 bg-background px-3 py-2 text-xs font-semibold text-foreground focus:ring-2 focus:ring-rose-500/20 focus:outline-none dark:border-rose-800"
                                >
                                    <option
                                        value="Trừ trực tiếp vào công nợ đơn sau"
                                    >
                                        📝 Trừ tiền vào công nợ kỳ sau
                                    </option>
                                    <option
                                        value="Yêu cầu giao bù ngay trong ngày"
                                    >
                                        🚚 Yêu cầu nhà cung cấp giao bù ngay
                                    </option>
                                    <option
                                        value="Đổi trả hàng lỗi cho nhà cung cấp"
                                    >
                                        📦 Trả lại toàn bộ lô hàng lỗi
                                    </option>
                                    <option
                                        value="Duyệt ngoại lệ (Chấp nhận chênh lệch)"
                                    >
                                        ✅ Duyệt ngoại lệ (Owner chấp nhận)
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Rating and feedback -->
                        <div
                            class="grid grid-cols-1 gap-4 rounded-xl border border-border bg-muted/30 p-4 md:grid-cols-3"
                        >
                            <div class="col-span-1 space-y-1">
                                <Label
                                    class="block text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Đánh giá nhà cung cấp (1-5★)</Label
                                >
                                <select
                                    v-model="verifyForm.rating"
                                    class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                                >
                                    <option :value="5">
                                        5 ★★★★★ (Xuất sắc)
                                    </option>
                                    <option :value="4">4 ★★★★☆ (Tốt)</option>
                                    <option :value="3">
                                        3 ★★★☆☆ (Trung bình)
                                    </option>
                                    <option :value="2">2 ★★☆☆☆ (Kém)</option>
                                    <option :value="1">
                                        1 ★☆☆☆☆ (Rất kém)
                                    </option>
                                </select>
                            </div>
                            <div class="col-span-2 space-y-1">
                                <Label
                                    class="block text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Ghi chú chất lượng hàng hóa / vận
                                    chuyển</Label
                                >
                                <Input
                                    v-model="verifyForm.rating_notes"
                                    type="text"
                                    placeholder="Rau tươi sạch, giao đúng giờ..."
                                />
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="mt-6 flex justify-end gap-2 border-t pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                @click="showVerifyModal = false"
                            >
                                Hủy bỏ
                            </Button>
                            <Button
                                type="submit"
                                :disabled="verifyForm.processing"
                                class="bg-emerald-600 text-white hover:bg-emerald-700"
                            >
                                {{
                                    verifyForm.processing
                                        ? 'Đang gửi...'
                                        : hasMismatch
                                          ? 'Xác nhận Đóng băng đơn hàng'
                                          : 'Hoàn tất bàn giao nhập kho'
                                }}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </div>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.animate-shimmer {
    background-size: 200% auto;
    animation: shine 1.5s linear infinite;
}

@keyframes shine {
    to {
        background-position: 200% center;
    }
}
</style>
