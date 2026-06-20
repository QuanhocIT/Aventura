<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import {
    Plus,
    Search,
    Truck,
    ShoppingCart,
    Clock,
    CheckCircle2,
    Trash2,
    Settings2,
    FileText,
    X,
    Loader2,
    Building2,
    Package,
    ChevronDown,
    ChevronUp,
} from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted } from 'vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import echo from '@/lib/echo';

defineOptions({ layout: AppLayout });

interface Unit {
    id: number;
    symbol: string;
}

interface Ingredient {
    id: number;
    name: string;
    packaging: string | null;
    price: number;
    unit: Unit | null;
}

interface Supplier {
    id: number;
    name: string;
    contact_name: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    notes: string | null;
    status: 'active' | 'inactive';
}

interface POItem {
    id: number;
    ingredient_name: string;
    quantity: number;
    unit_symbol: string;
    unit_cost: number;
}

interface PurchaseOrder {
    id: number;
    po_number: string;
    status: 'received' | 'preparing' | 'shipping' | 'delivered';
    total_amount: number;
    notes: string | null;
    invoice_file_url: string | null;
    created_at: string;
    creator_name: string;
    supplier_name: string;
    items_count: number;
    items: POItem[];
}

const props = defineProps<{
    suppliers: Supplier[];
    ingredients: Ingredient[];
    purchaseOrders: PurchaseOrder[];
}>();

const page = usePage();
const currentUser = computed(() => (page.props.auth as any)?.user);
const roles = computed(() => (page.props as any).roles ?? []);
const canManage = computed(
    () => roles.value.includes('owner') || roles.value.includes('manager'),
);

// Tabs
const activeTab = ref<'suppliers' | 'po'>('po');

// Search and Filtering
const supplierSearch = ref('');
const poSearch = ref('');

const filteredSuppliers = computed(() => {
    const q = supplierSearch.value.toLowerCase().trim();

    if (!q) {
return props.suppliers;
}

    return props.suppliers.filter(
        (s) =>
            s.name.toLowerCase().includes(q) ||
            (s.contact_name ?? '').toLowerCase().includes(q) ||
            (s.phone ?? '').toLowerCase().includes(q),
    );
});

const filteredPOs = computed(() => {
    const q = poSearch.value.toLowerCase().trim();

    if (!q) {
return localPOs.value;
}

    return localPOs.value.filter(
        (po) =>
            po.po_number.toLowerCase().includes(q) ||
            po.supplier_name.toLowerCase().includes(q) ||
            po.creator_name.toLowerCase().includes(q),
    );
});

// Local POs state to handle realtime updates dynamically
const localPOs = ref<PurchaseOrder[]>([]);
const highlightedPOs = ref<Record<number, boolean>>({});
const expandedPOs = ref<Record<number, boolean>>({});

// Audio Synthesizer Chimes
function playChime(type: 'new' | 'success' = 'new') {
    try {
        const audioCtx = new (
            window.AudioContext || (window as any).webkitAudioContext
        )();
        const now = audioCtx.currentTime;

        if (type === 'new') {
            // New PO Chime: E5 -> A5
            const osc1 = audioCtx.createOscillator();
            const gain1 = audioCtx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(659.25, now);
            gain1.gain.setValueAtTime(0.12, now);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.25);
            osc1.connect(gain1);
            gain1.connect(audioCtx.destination);
            osc1.start(now);
            osc1.stop(now + 0.25);

            const osc2 = audioCtx.createOscillator();
            const gain2 = audioCtx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(880, now + 0.1);
            gain2.gain.setValueAtTime(0.12, now + 0.1);
            gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.4);
            osc2.connect(gain2);
            gain2.connect(audioCtx.destination);
            osc2.start(now + 0.1);
            osc2.stop(now + 0.4);
        } else {
            // Success/Delivered Chime: C5 -> E5 -> G5 -> C6 arpeggio
            const notes = [523.25, 659.25, 783.99, 1046.5];
            notes.forEach((freq, idx) => {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, now + idx * 0.08);
                gain.gain.setValueAtTime(0.12, now + idx * 0.08);
                gain.gain.exponentialRampToValueAtTime(
                    0.001,
                    now + idx * 0.08 + 0.22,
                );
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start(now + idx * 0.08);
                osc.stop(now + idx * 0.08 + 0.22);
            });
        }
    } catch (e) {
        console.error('Failed to play audio notification', e);
    }
}

// Setup Echo Realtime listening
onMounted(() => {
    localPOs.value = [...props.purchaseOrders];

    if (echo && currentUser.value?.restaurant_id) {
        echo.private(`restaurant.${currentUser.value.restaurant_id}`)
            .listen('.purchase-order.created', (e: any) => {
                // Play notification sound
                playChime('new');

                // Add to list
                const newPO = e.purchase_order as PurchaseOrder;
                localPOs.value.unshift(newPO);

                // Highlight PO
                highlightedPOs.value[newPO.id] = true;
                setTimeout(() => {
                    delete highlightedPOs.value[newPO.id];
                }, 5000);

                toast.info(`Đơn PO đặt hàng mới: ${newPO.po_number}`, {
                    description: `Nhà cung cấp: ${newPO.supplier_name} - Tổng giá trị: ${vnd(newPO.total_amount)}`,
                    duration: 8000,
                });
            })
            .listen('.purchase-order.status-updated', (e: any) => {
                const poId = e.purchase_order_id;
                const newStatus = e.status;

                // Find and update local list
                const poIdx = localPOs.value.findIndex((po) => po.id === poId);

                if (poIdx !== -1) {
                    localPOs.value[poIdx].status = newStatus;

                    if (e.invoice_file_url) {
                        localPOs.value[poIdx].invoice_file_url =
                            e.invoice_file_url;
                    }

                    // Play success sound if delivered
                    if (newStatus === 'delivered') {
                        playChime('success');
                        toast.success(
                            `Đơn hàng ${localPOs.value[poIdx].po_number} đã được hạ hàng & cộng kho!`,
                        );
                    }

                    // Highlight PO
                    highlightedPOs.value[poId] = true;
                    setTimeout(() => {
                        delete highlightedPOs.value[poId];
                    }, 5000);
                }
            });
    }
});

onUnmounted(() => {
    if (echo && currentUser.value?.restaurant_id) {
        echo.leave(`restaurant.${currentUser.value.restaurant_id}`);
    }
});

// Modals states
const showAddSupplier = ref(false);
const showEditSupplier = ref(false);
const selectedSupplier = ref<Supplier | null>(null);

const showCreatePO = ref(false);

// Forms
const supplierForm = useForm({
    name: '',
    contact_name: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
    status: 'active' as 'active' | 'inactive',
});

const editSupplierForm = useForm({
    name: '',
    contact_name: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
    status: 'active' as 'active' | 'inactive',
});

// PO Form state (not using useForm directly for array tracking to make reactivity extremely clean)
const poSupplierId = ref('');
const poNotes = ref('');
interface LocalPOItemInput {
    ingredient_id: string;
    quantity: string;
    unit_cost: string;
}
const poItems = ref<LocalPOItemInput[]>([
    { ingredient_id: '', quantity: '', unit_cost: '' },
]);

const poGrandTotal = computed(() => {
    return poItems.value.reduce((sum, item) => {
        const qty = parseFloat(item.quantity) || 0;
        const cost = parseFloat(item.unit_cost) || 0;

        return sum + qty * cost;
    }, 0);
});

const addPOItemRow = () => {
    poItems.value.push({ ingredient_id: '', quantity: '', unit_cost: '' });
};

const removePOItemRow = (idx: number) => {
    if (poItems.value.length > 1) {
        poItems.value.splice(idx, 1);
    }
};

const handleIngredientChange = (idx: number) => {
    const ingId = parseInt(poItems.value[idx].ingredient_id);

    if (!ingId) {
return;
}

    const ing = props.ingredients.find((i) => i.id === ingId);

    if (ing) {
        poItems.value[idx].unit_cost = String(ing.price || 0);
        poItems.value[idx].quantity = '1';
    }
};

const isSubmittingPO = ref(false);

const submitPO = () => {
    // Validate inputs
    const validItems = poItems.value.filter(
        (item) =>
            item.ingredient_id &&
            parseFloat(item.quantity) > 0 &&
            parseFloat(item.unit_cost) >= 0,
    );

    if (validItems.length === 0) {
        toast.error(
            'Vui lòng chọn ít nhất một nguyên vật liệu hợp lệ với số lượng lớn hơn 0.',
        );

        return;
    }

    isSubmittingPO.value = true;
    router.post(
        '/purchase-orders',
        {
            supplier_id: poSupplierId.value || null,
            notes: poNotes.value,
            items: validItems.map((item) => ({
                ingredient_id: parseInt(item.ingredient_id),
                quantity: parseFloat(item.quantity),
                unit_cost: parseFloat(item.unit_cost),
            })),
        },
        {
            onSuccess: () => {
                showCreatePO.value = false;
                poSupplierId.value = '';
                poNotes.value = '';
                poItems.value = [
                    { ingredient_id: '', quantity: '', unit_cost: '' },
                ];
                toast.success('Đơn PO đã được gửi lên hệ thống Reverb!');
            },
            onError: (errs: any) => {
                const firstErr = Object.values(errs)[0];
                toast.error(
                    typeof firstErr === 'string'
                        ? firstErr
                        : 'Có lỗi khi tạo PO.',
                );
            },
            onFinish: () => {
                isSubmittingPO.value = false;
            },
        },
    );
};

// Workflow Advance Status
const activeUpdatingStatus = ref<Record<number, boolean>>({});

// E-Invoicing upload states
const showDeliverModal = ref(false);
const poToDeliver = ref<PurchaseOrder | null>(null);
const invoiceFile = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

const triggerDeliverPO = (po: PurchaseOrder) => {
    poToDeliver.value = po;
    invoiceFile.value = null;
    showDeliverModal.value = true;
};

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;

    if (target.files && target.files[0]) {
        const file = target.files[0];

        if (file.size > 2 * 1024 * 1024) {
            toast.error('Dung lượng tệp không được vượt quá 2MB.');

            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }

            return;
        }

        invoiceFile.value = file;
    }
};

const handleDeliverPO = () => {
    if (!poToDeliver.value) {
return;
}

    const po = poToDeliver.value;
    activeUpdatingStatus.value[po.id] = true;
    showDeliverModal.value = false;

    // Use router.post with FormData spoofing PATCH to upload file
    router.post(
        `/purchase-orders/${po.id}/status`,
        {
            _method: 'PATCH',
            status: 'delivered',
            invoice_file: invoiceFile.value,
        },
        {
            onSuccess: () => {
                toast.success(
                    `Đã hoàn thành đơn hàng ${po.po_number} và cộng kho!`,
                );
                poToDeliver.value = null;
                invoiceFile.value = null;
            },
            onError: (errs: any) => {
                const msg =
                    errs.status ||
                    errs.invoice_file ||
                    'Không thể chuyển đổi trạng thái.';
                toast.error(msg);
            },
            onFinish: () => {
                activeUpdatingStatus.value[po.id] = false;
            },
        },
    );
};

const advancePOStatus = (
    po: PurchaseOrder,
    targetStatus: 'preparing' | 'shipping' | 'delivered',
) => {
    if (targetStatus === 'delivered') {
        triggerDeliverPO(po);

        return;
    }

    activeUpdatingStatus.value[po.id] = true;
    router.patch(
        `/purchase-orders/${po.id}/status`,
        { status: targetStatus },
        {
            onSuccess: () => {
                toast.success(`Đã cập nhật trạng thái đơn ${po.po_number}`);
            },
            onError: (errs: any) => {
                const msg = errs.status || 'Không thể chuyển đổi trạng thái.';
                toast.error(msg);
            },
            onFinish: () => {
                activeUpdatingStatus.value[po.id] = false;
            },
        },
    );
};

// CRUD handlers for Suppliers
const openEditSupplier = (s: Supplier) => {
    selectedSupplier.value = s;
    editSupplierForm.name = s.name;
    editSupplierForm.contact_name = s.contact_name ?? '';
    editSupplierForm.phone = s.phone ?? '';
    editSupplierForm.email = s.email ?? '';
    editSupplierForm.address = s.address ?? '';
    editSupplierForm.notes = s.notes ?? '';
    editSupplierForm.status = s.status;
    showEditSupplier.value = true;
};

const handleCreateSupplier = () => {
    supplierForm.post('/suppliers', {
        onSuccess: () => {
            showAddSupplier.value = false;
            supplierForm.reset();
            toast.success('Đã thêm nhà cung cấp.');
        },
    });
};

const handleUpdateSupplier = () => {
    if (!selectedSupplier.value) {
return;
}

    editSupplierForm.patch(`/suppliers/${selectedSupplier.value.id}`, {
        onSuccess: () => {
            showEditSupplier.value = false;
            selectedSupplier.value = null;
            toast.success('Đã cập nhật thông tin nhà cung cấp.');
        },
    });
};

const handleDeleteSupplier = (s: Supplier) => {
    if (confirm(`Bạn có chắc chắn muốn xóa nhà cung cấp ${s.name}?`)) {
        router.delete(`/suppliers/${s.id}`, {
            onSuccess: () => {
                toast.success('Đã xóa nhà cung cấp.');
            },
        });
    }
};

// Stats calculations for POs
const poStats = computed(() => {
    const total = localPOs.value.length;
    const received = localPOs.value.filter(
        (po) => po.status === 'received',
    ).length;
    const preparing = localPOs.value.filter(
        (po) => po.status === 'preparing',
    ).length;
    const shipping = localPOs.value.filter(
        (po) => po.status === 'shipping',
    ).length;
    const delivered = localPOs.value.filter(
        (po) => po.status === 'delivered',
    ).length;

    return { total, received, preparing, shipping, delivered };
});

const vnd = (v: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(v);
};

const toggleExpandPO = (id: number) => {
    expandedPOs.value[id] = !expandedPOs.value[id];
};

const statusLabels: Record<string, string> = {
    received: 'Đã tiếp nhận',
    preparing: 'Đang chuẩn bị',
    shipping: 'Đang vận chuyển',
    delivered: 'Đã hạ hàng (Cộng kho)',
};

const statusColors: Record<string, string> = {
    received:
        'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-900/30',
    preparing:
        'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900/30',
    shipping:
        'bg-indigo-100 text-indigo-800 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-400 dark:border-indigo-900/30',
    delivered:
        'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900/30',
};
</script>

<template>
    <Head title="Nhà Cung Cấp & Tiếp Nhận PO" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-500"
                >
                    <Truck class="size-6 animate-pulse" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        Nhà Cung Cấp & PO Fulfillment
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Tiếp nhận yêu cầu mua nguyên liệu hàng ngày & xử lý đơn
                        vận PO thời gian thực
                    </p>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center gap-2">
                <Button
                    v-if="activeTab === 'suppliers' && canManage"
                    @click="showAddSupplier = true"
                    class="h-9 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                >
                    <Plus class="mr-1.5 size-4" /> Thêm nhà cung cấp
                </Button>
                <Button
                    v-if="activeTab === 'po'"
                    @click="showCreatePO = true"
                    class="h-9 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                >
                    <Plus class="mr-1.5 size-4" /> Tạo yêu cầu PO
                </Button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div
            class="flex items-center gap-1 self-start rounded-xl border border-border bg-muted p-1"
        >
            <button
                @click="activeTab = 'po'"
                class="flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold transition-all"
                :class="
                    activeTab === 'po'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                <ShoppingCart class="size-3.5" /> Tiếp nhận & Xử lý PO
                <span
                    v-if="poStats.received > 0"
                    class="ml-1 flex h-4 w-4 animate-bounce items-center justify-center rounded-full bg-rose-500 text-[9px] font-black text-white"
                >
                    {{ poStats.received }}
                </span>
            </button>
            <button
                @click="activeTab = 'suppliers'"
                class="flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold transition-all"
                :class="
                    activeTab === 'suppliers'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                <Building2 class="size-3.5" /> Danh sách đối tác
            </button>
        </div>

        <!-- ── TAB 1: PO fulfillment ── -->
        <template v-if="activeTab === 'po'">
            <!-- Stats Dashboard Banner -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                <Card
                    class="shadow-xs transition-transform hover:-translate-y-0.5"
                >
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >Tổng Đơn PO</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                            >{{ poStats.total }}</span
                        >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800"
                        >
                            <Clock class="size-4" />
                        </div>
                    </CardContent>
                </Card>

                <Card
                    class="border-blue-100 shadow-xs transition-transform hover:-translate-y-0.5 dark:border-blue-950/20"
                >
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-[10px] font-bold tracking-wider text-blue-500 uppercase"
                            >Đã tiếp nhận</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span
                            class="text-2xl font-black text-blue-600 dark:text-blue-400"
                            >{{ poStats.received }}</span
                        >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/40"
                        >
                            <ShoppingCart class="size-4 animate-pulse" />
                        </div>
                    </CardContent>
                </Card>

                <Card
                    class="border-amber-100 shadow-xs transition-transform hover:-translate-y-0.5 dark:border-amber-950/20"
                >
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-[10px] font-bold tracking-wider text-amber-500 uppercase"
                            >Đang chuẩn bị</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span
                            class="text-2xl font-black text-amber-600 dark:text-amber-400"
                            >{{ poStats.preparing }}</span
                        >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/40"
                        >
                            <Package class="size-4" />
                        </div>
                    </CardContent>
                </Card>

                <Card
                    class="border-indigo-100 shadow-xs transition-transform hover:-translate-y-0.5 dark:border-indigo-950/20"
                >
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-[10px] font-bold tracking-wider text-indigo-500 uppercase"
                            >Đang vận chuyển</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span
                            class="text-2xl font-black text-indigo-600 dark:text-indigo-400"
                            >{{ poStats.shipping }}</span
                        >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40"
                        >
                            <Truck class="size-4 animate-bounce" />
                        </div>
                    </CardContent>
                </Card>

                <Card
                    class="border-emerald-100 shadow-xs transition-transform hover:-translate-y-0.5 dark:border-emerald-950/20"
                >
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-[10px] font-bold tracking-wider text-emerald-500 uppercase"
                            >Đã hạ hàng</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span
                            class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                            >{{ poStats.delivered }}</span
                        >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40"
                        >
                            <CheckCircle2 class="size-4" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- List Search PO -->
            <Card class="shadow-sm">
                <CardHeader
                    class="flex flex-row flex-wrap items-center justify-between gap-4 border-b border-border pb-3"
                >
                    <div>
                        <CardTitle class="text-sm font-bold"
                            >Lịch Sử & Nhật Ký Đơn Đặt Hàng PO</CardTitle
                        >
                        <CardDescription class="text-xs"
                            >Theo dõi và đồng bộ hóa Reverb thời gian thực với
                            đối tác</CardDescription
                        >
                    </div>
                    <div class="relative w-full max-w-xs">
                        <Search
                            class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                        />
                        <Input
                            v-model="poSearch"
                            placeholder="Tìm mã đơn, nhà cung cấp..."
                            class="h-9 pl-9 text-xs"
                        />
                    </div>
                </CardHeader>

                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b bg-muted/40 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <th class="p-3.5">Mã đơn PO</th>
                                    <th class="p-3.5">Thời gian yêu cầu</th>
                                    <th class="p-3.5">Người tạo</th>
                                    <th class="p-3.5">Nhà cung cấp</th>
                                    <th class="p-3.5">Trạng thái</th>
                                    <th class="p-3.5 text-right">
                                        Tổng giá trị
                                    </th>
                                    <th class="p-3.5 text-center">Chi tiết</th>
                                    <th class="p-3.5 text-right">
                                        Quy trình vận đơn
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <template
                                    v-for="po in filteredPOs"
                                    :key="po.id"
                                >
                                    <tr
                                        :class="[
                                            'transition-colors duration-500 hover:bg-muted/20',
                                            highlightedPOs[po.id]
                                                ? 'bg-indigo-50/50 dark:bg-indigo-950/20'
                                                : '',
                                        ]"
                                    >
                                        <td
                                            class="p-3.5 font-mono font-bold text-slate-800 dark:text-slate-200"
                                        >
                                            {{ po.po_number }}
                                        </td>
                                        <td class="p-3.5 text-muted-foreground">
                                            {{ po.created_at }}
                                        </td>
                                        <td
                                            class="p-3.5 font-medium text-slate-700 dark:text-slate-300"
                                        >
                                            {{ po.creator_name }}
                                        </td>
                                        <td
                                            class="p-3.5 font-bold text-indigo-600 dark:text-indigo-400"
                                        >
                                            {{ po.supplier_name }}
                                        </td>
                                        <td class="p-3.5">
                                            <span
                                                class="rounded-full border px-2 py-0.5 text-[10px] font-bold"
                                                :class="statusColors[po.status]"
                                            >
                                                {{ statusLabels[po.status] }}
                                            </span>
                                        </td>
                                        <td
                                            class="p-3.5 text-right font-bold text-slate-900 dark:text-slate-50"
                                        >
                                            {{ vnd(po.total_amount) }}
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <button
                                                @click="toggleExpandPO(po.id)"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg border text-slate-500 transition-colors hover:bg-muted"
                                            >
                                                <ChevronUp
                                                    v-if="expandedPOs[po.id]"
                                                    class="size-4"
                                                />
                                                <ChevronDown
                                                    v-else
                                                    class="size-4"
                                                />
                                            </button>
                                        </td>
                                        <td class="p-3.5 text-right">
                                            <div
                                                class="flex items-center justify-end gap-1.5"
                                            >
                                                <Button
                                                    v-if="
                                                        po.status === 'received'
                                                    "
                                                    @click="
                                                        advancePOStatus(
                                                            po,
                                                            'preparing',
                                                        )
                                                    "
                                                    :disabled="
                                                        activeUpdatingStatus[
                                                            po.id
                                                        ]
                                                    "
                                                    class="h-7 bg-amber-500 px-2.5 text-[10px] font-bold text-white hover:bg-amber-600"
                                                >
                                                    <Loader2
                                                        v-if="
                                                            activeUpdatingStatus[
                                                                po.id
                                                            ]
                                                        "
                                                        class="mr-1 size-3 animate-spin"
                                                    />
                                                    Chuẩn bị hàng
                                                </Button>

                                                <Button
                                                    v-else-if="
                                                        po.status ===
                                                        'preparing'
                                                    "
                                                    @click="
                                                        advancePOStatus(
                                                            po,
                                                            'shipping',
                                                        )
                                                    "
                                                    :disabled="
                                                        activeUpdatingStatus[
                                                            po.id
                                                        ]
                                                    "
                                                    class="h-7 bg-indigo-500 px-2.5 text-[10px] font-bold text-white hover:bg-indigo-600"
                                                >
                                                    <Loader2
                                                        v-if="
                                                            activeUpdatingStatus[
                                                                po.id
                                                            ]
                                                        "
                                                        class="mr-1 size-3 animate-spin"
                                                    />
                                                    Bắt đầu giao
                                                </Button>

                                                <Button
                                                    v-else-if="
                                                        po.status === 'shipping'
                                                    "
                                                    @click="
                                                        advancePOStatus(
                                                            po,
                                                            'delivered',
                                                        )
                                                    "
                                                    :disabled="
                                                        activeUpdatingStatus[
                                                            po.id
                                                        ]
                                                    "
                                                    class="h-7 bg-emerald-600 px-2.5 text-[10px] font-bold text-white hover:bg-emerald-700"
                                                >
                                                    <Loader2
                                                        v-if="
                                                            activeUpdatingStatus[
                                                                po.id
                                                            ]
                                                        "
                                                        class="mr-1 size-3 animate-spin"
                                                    />
                                                    Đã hạ hàng (Cộng kho)
                                                </Button>

                                                <span
                                                    v-else
                                                    class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600"
                                                >
                                                    <CheckCircle2
                                                        class="size-3.5"
                                                    />
                                                    Hoàn thành
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Collapsible Item Details -->
                                    <tr v-if="expandedPOs[po.id]">
                                        <td
                                            colspan="8"
                                            class="bg-slate-50/50 p-4 dark:bg-slate-900/10"
                                        >
                                            <div
                                                class="max-w-3xl rounded-xl border bg-white p-4 dark:bg-slate-950"
                                            >
                                                <h4
                                                    class="mb-3 text-xs font-bold tracking-wide text-slate-700 uppercase dark:text-slate-300"
                                                >
                                                    Chi tiết sản phẩm nhập kho:
                                                </h4>
                                                <table class="w-full text-left">
                                                    <thead>
                                                        <tr
                                                            class="border-b text-[10px] font-bold text-muted-foreground uppercase"
                                                        >
                                                            <th class="pb-2">
                                                                Nguyên vật liệu
                                                            </th>
                                                            <th
                                                                class="pb-2 text-right"
                                                            >
                                                                Số lượng nhập
                                                            </th>
                                                            <th
                                                                class="pb-2 text-right"
                                                            >
                                                                Đơn giá niêm yết
                                                            </th>
                                                            <th
                                                                class="pb-2 text-right"
                                                            >
                                                                Thành tiền
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody
                                                        class="divide-y divide-slate-100 dark:divide-slate-800"
                                                    >
                                                        <tr
                                                            v-for="item in po.items"
                                                            :key="item.id"
                                                            class="text-xs"
                                                        >
                                                            <td
                                                                class="py-2 font-medium text-slate-800 dark:text-slate-200"
                                                            >
                                                                {{
                                                                    item.ingredient_name
                                                                }}
                                                            </td>
                                                            <td
                                                                class="py-2 text-right font-mono"
                                                            >
                                                                {{
                                                                    item.quantity
                                                                }}
                                                                {{
                                                                    item.unit_symbol
                                                                }}
                                                            </td>
                                                            <td
                                                                class="py-2 text-right font-mono text-muted-foreground"
                                                            >
                                                                {{
                                                                    vnd(
                                                                        item.unit_cost,
                                                                    )
                                                                }}
                                                            </td>
                                                            <td
                                                                class="py-2 text-right font-mono font-bold text-slate-950 dark:text-slate-50"
                                                            >
                                                                {{
                                                                    vnd(
                                                                        item.quantity *
                                                                            item.unit_cost,
                                                                    )
                                                                }}
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <div
                                                    v-if="po.notes"
                                                    class="mt-4 border-t pt-3 text-[11px] text-muted-foreground"
                                                >
                                                    <span
                                                        class="font-bold text-slate-600"
                                                        >Ghi chú đơn:</span
                                                    >
                                                    {{ po.notes }}
                                                </div>
                                                <div
                                                    v-if="po.invoice_file_url"
                                                    class="mt-3 flex items-center gap-2 border-t pt-3 text-xs"
                                                >
                                                    <span
                                                        class="font-bold text-slate-600 dark:text-slate-400"
                                                        >Chứng từ hoá đơn
                                                        (E-Invoice):</span
                                                    >
                                                    <a
                                                        :href="
                                                            po.invoice_file_url
                                                        "
                                                        target="_blank"
                                                        class="inline-flex items-center gap-1.5 font-semibold text-indigo-600 hover:text-indigo-800 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                                                    >
                                                        <FileText
                                                            class="size-3.5"
                                                        />
                                                        Xem chứng từ giao nhận
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="filteredPOs.length === 0">
                                    <td
                                        colspan="8"
                                        class="p-8 text-center text-muted-foreground"
                                    >
                                        Không tìm thấy đơn đặt hàng PO nào.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </template>

        <!-- ── TAB 2: Suppliers CRUD list ── -->
        <template v-else>
            <Card class="shadow-sm">
                <CardHeader
                    class="flex flex-row flex-wrap items-center justify-between gap-4 border-b border-border pb-3"
                >
                    <div>
                        <CardTitle class="text-sm font-bold"
                            >Danh Sách Đối Tác Cung Ứng</CardTitle
                        >
                        <CardDescription class="text-xs"
                            >Thông tin liên lạc và quản lý danh mục nhà cung ứng
                            vật tư</CardDescription
                        >
                    </div>
                    <div class="relative w-full max-w-xs">
                        <Search
                            class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                        />
                        <Input
                            v-model="supplierSearch"
                            placeholder="Tìm tên, liên hệ, SĐT..."
                            class="h-9 pl-9 text-xs"
                        />
                    </div>
                </CardHeader>

                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b bg-muted/40 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <th class="p-3.5">Nhà cung cấp</th>
                                    <th class="p-3.5">Người liên hệ</th>
                                    <th class="p-3.5">Số điện thoại</th>
                                    <th class="p-3.5">Email</th>
                                    <th class="p-3.5">Địa chỉ</th>
                                    <th class="p-3.5">Trạng thái</th>
                                    <th
                                        class="p-3.5 text-right"
                                        v-if="canManage"
                                    >
                                        Thao tác
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="s in filteredSuppliers"
                                    :key="s.id"
                                    class="transition-colors hover:bg-muted/10"
                                >
                                    <td
                                        class="p-3.5 font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ s.name }}
                                        <p
                                            v-if="s.notes"
                                            class="mt-0.5 text-[10px] font-normal text-muted-foreground italic"
                                        >
                                            {{ s.notes }}
                                        </p>
                                    </td>
                                    <td
                                        class="p-3.5 text-slate-700 dark:text-slate-300"
                                    >
                                        {{ s.contact_name || '—' }}
                                    </td>
                                    <td
                                        class="p-3.5 font-mono text-slate-600 dark:text-slate-400"
                                    >
                                        {{ s.phone || '—' }}
                                    </td>
                                    <td
                                        class="p-3.5 text-slate-600 dark:text-slate-400"
                                    >
                                        {{ s.email || '—' }}
                                    </td>
                                    <td
                                        class="max-w-xs truncate p-3.5 text-muted-foreground"
                                        :title="s.address || ''"
                                    >
                                        {{ s.address || '—' }}
                                    </td>
                                    <td class="p-3.5">
                                        <span
                                            class="rounded px-1.5 py-0.5 text-[9px] font-bold tracking-wider uppercase"
                                            :class="
                                                s.status === 'active'
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400'
                                                    : 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-400'
                                            "
                                        >
                                            {{
                                                s.status === 'active'
                                                    ? 'Hoạt động'
                                                    : 'Tạm ngưng'
                                            }}
                                        </span>
                                    </td>
                                    <td
                                        class="p-3.5 text-right"
                                        v-if="canManage"
                                    >
                                        <div
                                            class="flex items-center justify-end gap-1.5"
                                        >
                                            <button
                                                @click="openEditSupplier(s)"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg border text-slate-500 transition-colors hover:bg-muted hover:text-indigo-600"
                                                title="Sửa thông tin"
                                            >
                                                <Settings2 class="size-3.5" />
                                            </button>
                                            <button
                                                @click="handleDeleteSupplier(s)"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg border text-slate-500 transition-colors hover:bg-rose-50 hover:text-rose-600"
                                                title="Xóa nhà cung cấp"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredSuppliers.length === 0">
                                    <td
                                        colspan="7"
                                        class="p-8 text-center text-muted-foreground"
                                    >
                                        Không tìm thấy nhà cung cấp nào.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </template>

        <!-- ── MODAL: CREATE PO REQUEST ── -->
        <div
            v-if="showCreatePO"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="flex max-h-[85vh] w-full max-w-2xl animate-in flex-col bg-white shadow-2xl duration-150 zoom-in-95 fade-in dark:bg-slate-900"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between border-b pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-base text-indigo-600 dark:text-indigo-400"
                        >
                            <ShoppingCart class="size-5" /> Khởi tạo yêu cầu mua
                            nguyên liệu PO
                        </CardTitle>
                        <CardDescription
                            >Chọn đối tác cung ứng và thêm các nguyên liệu cần
                            nhập kho.</CardDescription
                        >
                    </div>
                    <button
                        @click="showCreatePO = false"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </CardHeader>

                <CardContent class="flex-1 space-y-4 overflow-y-auto pt-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label
                                for="po-supplier"
                                class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                                >Đối tác cung cấp</Label
                            >
                            <select
                                id="po-supplier"
                                v-model="poSupplierId"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-xs shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option value="">
                                    -- Tự do / Chưa chọn --
                                </option>
                                <option
                                    v-for="s in suppliers"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Items rows selection -->
                    <div class="space-y-2 border-t pt-3">
                        <Label
                            class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                            >Danh mục nguyên liệu cần mua</Label
                        >

                        <div
                            v-for="(item, idx) in poItems"
                            :key="idx"
                            class="flex flex-wrap items-end gap-2 border-b pb-2 sm:flex-nowrap"
                        >
                            <div class="min-w-[200px] flex-1">
                                <Label
                                    class="mb-1 block text-[10px] font-semibold text-slate-400"
                                    >Chọn nguyên liệu</Label
                                >
                                <select
                                    v-model="item.ingredient_id"
                                    @change="handleIngredientChange(idx)"
                                    class="h-8 w-full rounded-md border border-input bg-background px-3 py-1 text-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <option value="">
                                        -- Chọn nguyên liệu --
                                    </option>
                                    <option
                                        v-for="ing in ingredients"
                                        :key="ing.id"
                                        :value="ing.id"
                                    >
                                        {{ ing.name }} ({{
                                            ing.packaging || 'Mặc định'
                                        }})
                                    </option>
                                </select>
                            </div>

                            <div class="w-24">
                                <Label
                                    class="mb-1 block text-[10px] font-semibold text-slate-400"
                                    >Số lượng mua</Label
                                >
                                <Input
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    v-model="item.quantity"
                                    placeholder="Qty"
                                    class="h-8 font-mono text-xs"
                                />
                            </div>

                            <div class="w-32">
                                <Label
                                    class="mb-1 block text-[10px] font-semibold text-slate-400"
                                    >Giá nhập đề xuất</Label
                                >
                                <div class="relative">
                                    <Input
                                        type="number"
                                        min="0"
                                        v-model="item.unit_cost"
                                        placeholder="Giá nhập"
                                        class="h-8 pr-6 font-mono text-xs"
                                    />
                                    <span
                                        class="absolute top-2 right-2 font-mono text-[9px] text-muted-foreground"
                                        >đ</span
                                    >
                                </div>
                            </div>

                            <!-- Line total -->
                            <div class="w-32 self-center pt-4 text-right">
                                <span
                                    class="block text-[10px] text-muted-foreground"
                                    >Thành tiền:</span
                                >
                                <span
                                    class="font-mono text-xs font-black text-slate-700 dark:text-slate-200"
                                >
                                    {{
                                        vnd(
                                            (parseFloat(item.quantity) || 0) *
                                                (parseFloat(item.unit_cost) ||
                                                    0),
                                        )
                                    }}
                                </span>
                            </div>

                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                @click="removePOItemRow(idx)"
                                :disabled="poItems.length === 1"
                                class="h-8 w-8 text-rose-500 hover:bg-rose-50 hover:text-rose-700"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addPOItemRow"
                            class="mt-1 h-7 border-indigo-200 text-[10px] font-bold text-indigo-600 hover:bg-indigo-50"
                        >
                            <Plus class="mr-1 size-3" /> Thêm dòng nguyên liệu
                        </Button>
                    </div>

                    <!-- Notes -->
                    <div class="grid gap-1.5 border-t pt-3">
                        <Label
                            for="po-notes"
                            class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                            >Ghi chú vận đơn</Label
                        >
                        <textarea
                            id="po-notes"
                            v-model="poNotes"
                            placeholder="Mô tả lý do mua, yêu cầu giao gấp..."
                            class="min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-xs shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        ></textarea>
                    </div>
                </CardContent>

                <!-- Footer Summary & Button -->
                <div
                    class="flex flex-row items-center justify-between rounded-b-2xl border-t bg-slate-50 p-4 dark:bg-slate-900/50"
                >
                    <div>
                        <span
                            class="block text-[10px] font-bold text-muted-foreground uppercase"
                            >Tổng Giá Trị Đơn PO:</span
                        >
                        <span
                            class="font-mono text-lg font-black text-indigo-600 dark:text-indigo-400"
                        >
                            {{ vnd(poGrandTotal) }}
                        </span>
                    </div>

                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="showCreatePO = false"
                            class="text-xs"
                        >
                            Hủy
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            @click="submitPO"
                            :disabled="isSubmittingPO"
                            class="bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                        >
                            <Loader2
                                v-if="isSubmittingPO"
                                class="mr-1.5 size-3.5 animate-spin"
                            />
                            Gửi yêu cầu PO (Reverb)
                        </Button>
                    </div>
                </div>
            </Card>
        </div>

        <!-- ── MODAL: CONFIRM DELIVERY & UPLOAD INVOICE ── -->
        <div
            v-if="showDeliverModal && poToDeliver"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-md animate-in bg-white shadow-2xl duration-150 zoom-in-95 fade-in dark:bg-slate-900"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between border-b pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-base text-emerald-600 dark:text-emerald-400"
                        >
                            <CheckCircle2 class="size-5" /> Xác nhận hạ hàng &
                            Cộng kho
                        </CardTitle>
                        <CardDescription
                            >Đơn hàng:
                            <span class="font-mono font-bold">{{
                                poToDeliver.po_number
                            }}</span></CardDescription
                        >
                    </div>
                    <button
                        @click="showDeliverModal = false"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </CardHeader>

                <CardContent class="space-y-4 pt-4">
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        Bạn đang thực hiện hạ hàng tại kho và tự động cộng tồn
                        kho cho các nguyên vật liệu của đơn hàng này. Vui lòng
                        đính kèm hình ảnh hóa đơn giấy hoặc tải lên tệp hóa đơn
                        điện tử (nếu có) để phục vụ công tác đối soát và liên
                        kết kiểm toán.
                    </p>

                    <div class="grid gap-1.5">
                        <Label
                            for="invoice-file"
                            class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                            >Đính kèm hóa đơn (Không bắt buộc)</Label
                        >
                        <div
                            class="flex flex-col gap-2 rounded-xl border border-dashed border-slate-300 p-4 text-center transition-colors hover:bg-slate-50/50 dark:border-slate-800 dark:hover:bg-slate-900/20"
                        >
                            <input
                                id="invoice-file"
                                type="file"
                                ref="fileInputRef"
                                accept="image/*,application/pdf"
                                @change="handleFileChange"
                                class="hidden"
                            />
                            <Label
                                for="invoice-file"
                                class="flex cursor-pointer flex-col items-center gap-1.5 text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                            >
                                <FileText class="mb-1 size-8 text-slate-400" />
                                <span class="font-bold"
                                    >Chọn tệp tin hoá đơn</span
                                >
                                <span class="text-[10px] text-muted-foreground"
                                    >Chấp nhận JPG, PNG, WEBP, PDF (Tối đa
                                    2MB)</span
                                >
                            </Label>

                            <div
                                v-if="invoiceFile"
                                class="mt-2 flex items-center justify-between rounded-lg bg-indigo-50/50 p-2 text-[11px] dark:bg-indigo-950/20"
                            >
                                <span
                                    class="max-w-[200px] truncate font-medium text-indigo-700 dark:text-indigo-300"
                                >
                                    {{ invoiceFile.name }}
                                </span>
                                <button
                                    type="button"
                                    @click="invoiceFile = null"
                                    class="font-bold text-rose-500 hover:text-rose-700"
                                >
                                    Xóa
                                </button>
                            </div>
                        </div>
                    </div>
                </CardContent>

                <div
                    class="flex justify-end gap-2 rounded-b-2xl border-t bg-slate-50 p-4 dark:bg-slate-900/50"
                >
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="showDeliverModal = false"
                        class="text-xs"
                    >
                        Hủy
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        @click="handleDeliverPO"
                        class="bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700"
                    >
                        Xác nhận & Cộng kho
                    </Button>
                </div>
            </Card>
        </div>

        <!-- ── MODAL: ADD SUPPLIER ── -->
        <div
            v-if="showAddSupplier"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-lg animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between border-b pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-base text-indigo-600"
                        >
                            <Plus class="size-5" /> Thêm nhà cung cấp mới
                        </CardTitle>
                        <CardDescription
                            >Cung cấp thông tin để niêm yết và giao dịch mua
                            hàng.</CardDescription
                        >
                    </div>
                    <button
                        @click="showAddSupplier = false"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </CardHeader>

                <form @submit.prevent="handleCreateSupplier">
                    <CardContent class="space-y-4 pt-4">
                        <div class="grid gap-1.5">
                            <Label
                                for="s-name"
                                class="text-xs font-bold text-slate-500 uppercase"
                                >Tên nhà cung cấp *</Label
                            >
                            <Input
                                id="s-name"
                                v-model="supplierForm.name"
                                required
                                class="h-9 text-xs"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label
                                    for="s-contact"
                                    class="text-xs font-bold text-slate-500 uppercase"
                                    >Người liên hệ</Label
                                >
                                <Input
                                    id="s-contact"
                                    v-model="supplierForm.contact_name"
                                    class="h-9 text-xs"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    for="s-phone"
                                    class="text-xs font-bold text-slate-500 uppercase"
                                    >Số điện thoại</Label
                                >
                                <Input
                                    id="s-phone"
                                    v-model="supplierForm.phone"
                                    class="h-9 font-mono text-xs"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label
                                    for="s-email"
                                    class="text-xs font-bold text-slate-500 uppercase"
                                    >Email</Label
                                >
                                <Input
                                    id="s-email"
                                    type="email"
                                    v-model="supplierForm.email"
                                    class="h-9 text-xs"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    for="s-status"
                                    class="text-xs font-bold text-slate-500 uppercase"
                                    >Trạng thái</Label
                                >
                                <select
                                    id="s-status"
                                    v-model="supplierForm.status"
                                    class="h-9 rounded-md border border-input px-3 py-1 text-xs"
                                >
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Tạm ngưng</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="s-addr"
                                class="text-xs font-bold text-slate-500 uppercase"
                                >Địa chỉ</Label
                            >
                            <Input
                                id="s-addr"
                                v-model="supplierForm.address"
                                class="h-9 text-xs"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="s-notes"
                                class="text-xs font-bold text-slate-500 uppercase"
                                >Ghi chú bổ sung</Label
                            >
                            <textarea
                                id="s-notes"
                                v-model="supplierForm.notes"
                                class="min-h-[60px] rounded-md border border-input p-2 text-xs"
                            ></textarea>
                        </div>
                    </CardContent>

                    <div
                        class="flex justify-end gap-2 rounded-b-2xl border-t bg-slate-50 p-4 dark:bg-slate-900/50"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="showAddSupplier = false"
                            class="text-xs"
                        >
                            Hủy
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="supplierForm.processing"
                            class="bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                        >
                            Lưu nhà cung cấp
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- ── MODAL: EDIT SUPPLIER ── -->
        <div
            v-if="showEditSupplier"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-lg animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between border-b pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-base text-indigo-600"
                        >
                            <Settings2 class="size-5" /> Cập nhật nhà cung cấp
                        </CardTitle>
                        <CardDescription
                            >Cập nhật thông tin chi tiết nhà cung
                            cấp.</CardDescription
                        >
                    </div>
                    <button
                        @click="showEditSupplier = false"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </CardHeader>

                <form @submit.prevent="handleUpdateSupplier">
                    <CardContent class="space-y-4 pt-4">
                        <div class="grid gap-1.5">
                            <Label
                                for="edit-name"
                                class="text-xs font-bold text-slate-500 uppercase"
                                >Tên nhà cung cấp *</Label
                            >
                            <Input
                                id="edit-name"
                                v-model="editSupplierForm.name"
                                required
                                class="h-9 text-xs"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label
                                    for="edit-contact"
                                    class="text-xs font-bold text-slate-500 uppercase"
                                    >Người liên hệ</Label
                                >
                                <Input
                                    id="edit-contact"
                                    v-model="editSupplierForm.contact_name"
                                    class="h-9 text-xs"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    for="edit-phone"
                                    class="text-xs font-bold text-slate-500 uppercase"
                                    >Số điện thoại</Label
                                >
                                <Input
                                    id="edit-phone"
                                    v-model="editSupplierForm.phone"
                                    class="h-9 font-mono text-xs"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label
                                    for="edit-email"
                                    class="text-xs font-bold text-slate-500 uppercase"
                                    >Email</Label
                                >
                                <Input
                                    id="edit-email"
                                    type="email"
                                    v-model="editSupplierForm.email"
                                    class="h-9 text-xs"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    for="edit-status"
                                    class="text-xs font-bold text-slate-500 uppercase"
                                    >Trạng thái</Label
                                >
                                <select
                                    id="edit-status"
                                    v-model="editSupplierForm.status"
                                    class="h-9 rounded-md border border-input px-3 py-1 text-xs"
                                >
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Tạm ngưng</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="edit-addr"
                                class="text-xs font-bold text-slate-500 uppercase"
                                >Địa chỉ</Label
                            >
                            <Input
                                id="edit-addr"
                                v-model="editSupplierForm.address"
                                class="h-9 text-xs"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                for="edit-notes"
                                class="text-xs font-bold text-slate-500 uppercase"
                                >Ghi chú bổ sung</Label
                            >
                            <textarea
                                id="edit-notes"
                                v-model="editSupplierForm.notes"
                                class="min-h-[60px] rounded-md border border-input p-2 text-xs"
                            ></textarea>
                        </div>
                    </CardContent>

                    <div
                        class="flex justify-end gap-2 rounded-b-2xl border-t bg-slate-50 p-4 dark:bg-slate-900/50"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="showEditSupplier = false"
                            class="text-xs"
                        >
                            Hủy
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="editSupplierForm.processing"
                            class="bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                        >
                            Cập nhật thông tin
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    </div>
</template>
