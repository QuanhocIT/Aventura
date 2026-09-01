<script setup lang="ts">
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Package,
    Plus,
    Settings2,
    Scale,
    Info,
    Beaker,
    X,
    TrendingDown,
    ShoppingCart,
    AlertTriangle,
    Trash2,
    ArrowDownToLine,
    ChevronLeft,
    ChevronRight,
    Sparkles,
    Search,
    ClipboardCheck,
    Edit,
    MapPin,
    Layers,
    LockKeyhole,
    UnlockKeyhole,
    RefreshCw,
    CalendarCheck,
    Warehouse,
    Send,
    Minus,
} from 'lucide-vue-next';
import {
    computed,
    ref,
    onBeforeUnmount,
    onMounted,
    watch,
    nextTick,
} from 'vue';
import { toast } from 'vue-sonner';
import NegativeInventoryCases from '@/components/NegativeInventoryCases.vue';
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
import { apiErrorMessage } from '@/composables/useApiCall';
import AppLayout from '@/layouts/AppLayout.vue';
import IngredientModal from './components/IngredientModal.vue';

defineOptions({ layout: AppLayout });

type Ingredient = {
    id: number;
    branch_id?: number | null;
    branch_name?: string;
    sku: string | null;
    name: string;
    category_name: string | null;
    storage_type?: string;
    storage_type_label?: string;
    default_shelf_life_days?: number | null;
    storage_location?: string | null;
    expiry_warning_days?: number;
    auto_waste_end_of_day?: boolean;
    min_stock_level?: number;
    reorder_level?: number;
    supplier_id?: number | null;
    supplier_options?: Array<{
        supplier_id: number;
        supplier_name?: string | null;
        is_primary?: boolean;
    }>;
    safety_stock_quantity?: number;
    lead_time_days?: number;
    batch_tracking_required?: boolean;
    storage_temperature_min_c?: number | null;
    storage_temperature_max_c?: number | null;
    average_cost: number;
    unit: { id: number; symbol: string } | null;
    stock: number | null;
    last_cost: number | null;
    batches?: Array<{
        id: number;
        batch_number: string;
        quantity_remaining: number;
        unit_cost: number;
        purchased_at: string | null;
        expiry_date: string | null;
        raw_expiry: string | null;
        status?: 'active' | 'expired' | 'depleted' | 'locked' | 'recalled';
        days_remaining: number | null;
        is_expiring_soon: boolean;
        is_expired: boolean;
        is_locked?: boolean;
        is_recalled?: boolean;
        lock_reason?: string | null;
        locked_by_name?: string | null;
    }>;
};
type RecipeItem = {
    id: number;
    ingredient_id: number;
    ingredient_name: string;
    quantity: number;
    unit_id?: number;
    unit_symbol: string;
    waste_rate: number;
};
type Product = {
    id: number;
    branch_id?: number | null;
    branch_name?: string;
    name: string;
    code: string;
    price: number;
    recipes: RecipeItem[];
};
type Unit = {
    id: number;
    name: string;
    symbol: string;
    type?: string;
    conversion_factor_to_base?: number;
};
type Supplier = { id: number; name: string };
type Purchase = {
    id: number;
    ingredient_name: string;
    quantity: number;
    unit_cost: number;
    total_cost: number;
    supplier_name: string;
    batch_number?: string | null;
    occurred_at: string | null;
    notes: string | null;
};
type Employee = { id: number; full_name: string; job_title: string | null };
type WasteRecord = {
    id: number;
    is_approval: boolean;
    ingredient_name: string;
    quantity: number;
    unit_symbol: string;
    cost: number;
    notes: string | null;
    performed_by: string;
    employee_name: string;
    occurred_at: string;
    status: 'pending' | 'approved' | 'rejected';
    rejection_reason: string | null;
};

const props = defineProps<{
    ingredients: Ingredient[];
    products: Product[];
    units: Unit[];
    suppliers: Supplier[];
    recentPurchases: Purchase[];
    employees: Employee[];
    recentWastes: WasteRecord[];
    safety: {
        ready: boolean;
        products_without_recipes: number;
        negative_stocks: number;
        negative_cases_open?: number;
        opening_balance_pending: number;
        legacy_batches_pending: number;
    };
    negativeStockCases?: Array<{
        id: number;
        branch_name?: string | null;
        ingredient_name?: string | null;
        unit_symbol?: string | null;
        status:
            | 'open'
            | 'in_progress'
            | 'pending_owner_approval'
            | 'pending_verification';
        negative_quantity: number;
        on_hand: number;
        estimated_value: number;
        detected_at?: string | null;
        auto_plan?: string | null;
        handling_plan?: string | null;
        responsible_user_name?: string | null;
        expected_restock_at?: string | null;
    }>;
    activeBranchId?: number | null;
    activeBranchName?: string | null;
    centralBranch?: { id: number; name: string } | null;
    centralIngredients?: Array<{
        id: number;
        name: string;
        sku?: string | null;
        category_name?: string | null;
        stock: number;
        unit_cost: number;
        unit_symbol: string;
    }>;
    branchReplenishmentSuggestions?: Array<{
        ingredient_id: number;
        name: string;
        sku?: string | null;
        category_name?: string | null;
        unit_symbol: string;
        current_stock: number;
        min_stock_level: number;
        reorder_level: number;
        average_daily_usage: number;
        forecast_7d: number;
        suggested_quantity: number;
        estimated_cost: number;
        priority: 'urgent' | 'recommended' | 'stable';
        reason: string;
    }>;
    canCreateSupplyRequests?: boolean;
    view?: 'inventory' | 'recipes';
}>();

const isRecipesPage = computed(() => props.view === 'recipes');

// ── Tabs ──────────────────────────────────────────────────────────────────────
const activeTab = ref<
    'stock' | 'purchase' | 'central' | 'waste' | 'reconcile' | 'planning'
>('stock');

// ── Storage Type Filter & Ingredient Modal ──────────────────────────────────
const selectedStorageTypeFilter = ref<string>('all');
const showIngredientModal = ref(false);
const editingIngredient = ref<any | null>(null);

const openAddIngredientModal = () => {
    editingIngredient.value = null;
    showIngredientModal.value = true;
};

const openEditIngredientModal = (ing: any) => {
    editingIngredient.value = ing;
    showIngredientModal.value = true;
};

const getStorageBadgeClass = (type?: string) => {
    switch (type) {
        case 'fresh':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300';
        case 'daily':
            return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300';
        case 'short_shelf':
            return 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300';
        case 'canned_packaged':
            return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300';
    }
};

const getStorageLabel = (ingredient: Ingredient) => {
    const label = ingredient.storage_type_label?.trim();

    return label
        ? label.replace(/^[^\p{L}\p{N}]+/u, '').trim()
        : 'Đồ khô & Gia vị';
};

// ── Pagination & Search (công thức định lượng) ─────────────────────────
const recipeSearch = ref('');
const recipeCurrentPage = ref(1);
const recipePerPage = 5;

const filteredRecipeProducts = computed(() => {
    if (!props.products) {
        return [];
    }

    const q = recipeSearch.value.trim().toLowerCase();

    if (!q) {
        return props.products;
    }

    return props.products.filter(
        (p) =>
            p.name.toLowerCase().includes(q) ||
            p.code.toLowerCase().includes(q),
    );
});

const paginatedProducts = computed(() => {
    const start = (recipeCurrentPage.value - 1) * recipePerPage;
    const end = start + recipePerPage;

    return filteredRecipeProducts.value.slice(start, end);
});

const totalRecipePages = computed(() => {
    return Math.ceil(filteredRecipeProducts.value.length / recipePerPage) || 0;
});

const visibleRecipePages = computed(() => {
    const pages = [];
    const total = totalRecipePages.value;
    const current = recipeCurrentPage.value;

    if (total <= 5) {
        for (let i = 1; i <= total; i++) {
            pages.push(i);
        }
    } else {
        pages.push(1);

        if (current > 3) {
            pages.push('...');
        }

        const start = Math.max(2, current - 1);
        const end = Math.min(total - 1, current + 1);

        for (let i = start; i <= end; i++) {
            pages.push(i);
        }

        if (current < total - 2) {
            pages.push('...');
        }

        pages.push(total);
    }

    return pages;
});

watch(recipeSearch, () => {
    recipeCurrentPage.value = 1;
});

watch(totalRecipePages, (total) => {
    if (total === 0) {
        recipeCurrentPage.value = 1;
    } else if (recipeCurrentPage.value > total) {
        recipeCurrentPage.value = total;
    }
});

// ── Modals (stock tab) ────────────────────────────────────────────────────────
const showAddRecipe = ref(false);
const showAddIngredient = ref(false);
const activeProduct = ref<Product | null>(null);

// ── Forms ─────────────────────────────────────────────────────────────────────
const ingredientForm = useForm({
    name: '',
    unit_id: props.units[0]?.id ? String(props.units[0].id) : '',
    category: '',
});

const recipeForm = useForm({
    product_id: '',
    items: [] as Array<{
        ingredient_id: string;
        unit_id: string;
        quantity: string;
        waste_rate: string;
    }>,
});

const purchaseForm = useForm({
    ingredient_id: '',
    quantity: '',
    unit_cost: '',
    supplier_id: '',
    batch_number: '',
    notes: '',
    occurred_at: new Date().toISOString().slice(0, 10),
    expiry_date: '',
    invoice_file: null as File | null,
    items: [
        {
            ingredient_id: '',
            quantity: '',
            unit_cost: '',
            batch_number: '',
            expiry_date: '',
        },
    ] as Array<{
        ingredient_id: string;
        quantity: string;
        unit_cost: string;
        batch_number?: string;
        expiry_date?: string;
        notes?: string;
    }>,
});

const addPurchaseItemRow = (ingredientId = '', qty = '', unitCost = '') => {
    purchaseForm.items.push({
        ingredient_id: ingredientId,
        quantity: qty,
        unit_cost: unitCost,
        batch_number: '',
        expiry_date: '',
    });
};

const removePurchaseItemRow = (index: number) => {
    if (purchaseForm.items.length > 1) {
        purchaseForm.items.splice(index, 1);
    } else {
        purchaseForm.items[0] = {
            ingredient_id: '',
            quantity: '',
            unit_cost: '',
            batch_number: '',
            expiry_date: '',
        };
    }
};

const totalPurchaseReceiptCost = computed(() => {
    return purchaseForm.items.reduce((sum: number, item: any) => {
        const qty = Number(item.quantity) || 0;
        const cost = Number(item.unit_cost) || 0;

        return sum + qty * cost;
    }, 0);
});

const onPurchaseIngredientChange = (item: any) => {
    if (item.ingredient_id) {
        const ing = props.ingredients?.find(
            (i) => String(i.id) === String(item.ingredient_id),
        );

        if (ing && (!item.unit_cost || Number(item.unit_cost) === 0)) {
            item.unit_cost = String(ing.average_cost ?? ing.last_cost ?? 0);
        }
    }
};

const centralIngredientSearch = ref('');
const centralIngredientCategory = ref('all');
const centralRequestStep = ref<'select' | 'details'>('select');
const isSubmittingCentralRequest = ref(false);
const centralRequestForm = ref({
    requested_delivery_date: new Date().toISOString().slice(0, 10),
    notes: '',
    items: [] as Array<{ ingredient_id: number; quantity: number }>,
});

const filteredCentralIngredients = computed(() => {
    const query = centralIngredientSearch.value.trim().toLowerCase();

    return (props.centralIngredients ?? []).filter((ingredient) => {
        if (
            centralIngredientCategory.value !== 'all' &&
            (ingredient.category_name || 'Khác') !==
                centralIngredientCategory.value
        ) {
            return false;
        }

        if (!query) {
            return true;
        }

        return [ingredient.name, ingredient.sku, ingredient.category_name]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(query);
    });
});

const centralIngredientCategories = computed(() => [
    'all',
    ...Array.from(
        new Set(
            (props.centralIngredients ?? []).map(
                (ingredient) => ingredient.category_name || 'Khác',
            ),
        ),
    ),
]);

const getReplenishmentSuggestion = (ingredientId: number) =>
    (props.branchReplenishmentSuggestions ?? []).find(
        (suggestion) => suggestion.ingredient_id === ingredientId,
    );

const selectedCentralItems = computed(() =>
    centralRequestForm.value.items
        .map((item) => ({
            ...item,
            ingredient: (props.centralIngredients ?? []).find(
                (ingredient) => ingredient.id === item.ingredient_id,
            ),
        }))
        .filter((item) => item.ingredient),
);

const centralRequestTotal = computed(() =>
    selectedCentralItems.value.reduce(
        (total, item) =>
            total + item.quantity * (item.ingredient?.unit_cost ?? 0),
        0,
    ),
);

const isCentralIngredientSelected = (ingredientId: number) =>
    centralRequestForm.value.items.some(
        (item) => item.ingredient_id === ingredientId,
    );

const getCentralIngredient = (ingredientId: number) =>
    (props.centralIngredients ?? []).find(
        (ingredient) => ingredient.id === ingredientId,
    );

const addCentralIngredient = (
    ingredient: NonNullable<typeof props.centralIngredients>[number],
) => {
    if (ingredient.stock <= 0) {
        toast.warning(`${ingredient.name} hiện đã hết hàng tại Kho Tổng.`);

        return;
    }

    if (isCentralIngredientSelected(ingredient.id)) {
        return;
    }

    centralRequestForm.value.items.push({
        ingredient_id: ingredient.id,
        quantity: Math.min(
            getReplenishmentSuggestion(ingredient.id)?.suggested_quantity || 1,
            ingredient.stock,
        ),
    });
};

const addSuggestedIngredient = (
    suggestion: NonNullable<
        typeof props.branchReplenishmentSuggestions
    >[number],
) => {
    const ingredient = getCentralIngredient(suggestion.ingredient_id);

    if (!ingredient) {
        toast.error(
            `${suggestion.name} hiện chưa có tồn kho tại Kho Tổng để giao.`,
        );

        return;
    }

    addCentralIngredient(ingredient);
};

const confirmCentralSelection = () => {
    if (!centralRequestForm.value.items.length) {
        toast.error('Vui lòng chọn ít nhất một nguyên liệu từ Kho Tổng.');

        return;
    }

    centralRequestStep.value = 'details';
};

const returnToCentralSelection = () => {
    centralRequestStep.value = 'select';
};

const removeCentralIngredient = (ingredientId: number) => {
    centralRequestForm.value.items = centralRequestForm.value.items.filter(
        (item) => item.ingredient_id !== ingredientId,
    );
};

const clearCentralRequest = () => {
    centralRequestStep.value = 'select';
    centralIngredientSearch.value = '';
    centralIngredientCategory.value = 'all';
    centralRequestForm.value = {
        requested_delivery_date: new Date().toISOString().slice(0, 10),
        notes: '',
        items: [],
    };
};

const submitCentralRequest = async () => {
    if (!props.activeBranchId) {
        toast.error('Vui lòng chọn chi nhánh nhận hàng trước khi gửi yêu cầu.');

        return;
    }

    if (!centralRequestForm.value.items.length) {
        toast.error('Vui lòng chọn ít nhất một nguyên liệu từ Tổng kho.');

        return;
    }

    const invalidItem = selectedCentralItems.value.find(
        (item) =>
            item.quantity <= 0 || item.quantity > (item.ingredient?.stock ?? 0),
    );

    if (invalidItem) {
        toast.error(
            `Số lượng ${invalidItem.ingredient?.name} vượt quá tồn kho Tổng.`,
        );

        return;
    }

    isSubmittingCentralRequest.value = true;

    try {
        const response = await axios.post('/api/supply-requests', {
            to_branch_id: props.activeBranchId,
            requested_delivery_date:
                centralRequestForm.value.requested_delivery_date,
            notes: centralRequestForm.value.notes || null,
            items: centralRequestForm.value.items,
        });

        if (response.data.success) {
            toast.success(
                'Đã gửi danh sách nguyên liệu cho Tổng kho giao hàng.',
            );
            clearCentralRequest();
            router.reload();
        }
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Không thể gửi yêu cầu cấp hàng.',
        );
    } finally {
        isSubmittingCentralRequest.value = false;
    }
};

const purchaseFormCard = ref<HTMLElement | null>(null);
const aiForecastCardHeight = ref<number | null>(null);
let purchaseCardResizeObserver: ResizeObserver | null = null;

const syncAiForecastCardHeight = () => {
    const height = purchaseFormCard.value?.getBoundingClientRect().height;

    if (height) {
        aiForecastCardHeight.value = Math.round(height);
    }
};

const observePurchaseFormCard = () => {
    purchaseCardResizeObserver?.disconnect();
    purchaseCardResizeObserver = null;

    if (!purchaseFormCard.value) {
        aiForecastCardHeight.value = null;

        return;
    }

    syncAiForecastCardHeight();
    purchaseCardResizeObserver = new ResizeObserver(syncAiForecastCardHeight);
    purchaseCardResizeObserver.observe(purchaseFormCard.value);
};

// ── AI Forecast ──────────────────────────────────────────────────────────────
const aiForecasts = ref<any[]>([]);
const loadingForecast = ref(false);

const fetchAiForecast = async () => {
    loadingForecast.value = true;

    try {
        const response = await fetch('/api/inventory/ai-forecast');

        // fetch() không ném lỗi khi server trả 403/500 — nếu không kiểm ở đây,
        // JSON của trang lỗi sẽ lọt qua nhánh data.success và im lặng bỏ qua.
        if (!response.ok) {
            throw new Error(
                response.status === 403
                    ? 'Tài khoản của bạn không có quyền xem dự báo tồn kho.'
                    : `Máy chủ trả về mã lỗi ${response.status}.`,
            );
        }

        const data = await response.json();

        if (data.success) {
            aiForecasts.value = data.forecast;
        }
    } catch (error) {
        toast.error(apiErrorMessage(error, 'Không tải được dự báo tồn kho.'));
    } finally {
        loadingForecast.value = false;
    }
};

const applyForecast = (item: any) => {
    const qty = Number(item.suggested_purchase ?? 0);

    if (qty <= 0) {
        toast.warning(
            `Mặt hàng ${item.ingredient_name} hiện đã đủ tồn kho, không cần nhập thêm.`,
        );

        return;
    }

    const ingModel = props.ingredients?.find(
        (i) => String(i.id) === String(item.ingredient_id),
    );
    const avgCost = ingModel?.average_cost
        ? String(ingModel.average_cost)
        : '0';

    const existingIndex = purchaseForm.items.findIndex(
        (row: any) => String(row.ingredient_id) === String(item.ingredient_id),
    );

    if (existingIndex >= 0) {
        purchaseForm.items[existingIndex].quantity = String(qty);

        if (
            !purchaseForm.items[existingIndex].unit_cost ||
            Number(purchaseForm.items[existingIndex].unit_cost) === 0
        ) {
            purchaseForm.items[existingIndex].unit_cost = avgCost;
        }
    } else {
        if (
            purchaseForm.items.length === 1 &&
            !purchaseForm.items[0].ingredient_id
        ) {
            purchaseForm.items[0] = {
                ingredient_id: String(item.ingredient_id),
                quantity: String(qty),
                unit_cost: avgCost,
                batch_number: '',
                expiry_date: '',
            };
        } else {
            addPurchaseItemRow(
                String(item.ingredient_id),
                String(qty),
                avgCost,
            );
        }
    }

    toast.success(
        `Đã thêm ${item.ingredient_name} (${qty} ${item.unit_symbol}) vào phiếu nhập kho!`,
    );
};

const submitPurchaseForm = () => {
    if (!purchaseForm.invoice_file) {
        toast.error('Vui lòng tải lên ảnh chụp hóa đơn / chứng từ cứng.');

        return;
    }

    const invalidItem = purchaseForm.items.find(
        (item: any) => !item.ingredient_id || Number(item.quantity) <= 0,
    );

    if (invalidItem) {
        toast.error(
            'Vui lòng chọn nguyên liệu và nhập số lượng > 0 cho tất cả các dòng trong phiếu nhập.',
        );

        return;
    }

    purchaseForm.post('/inventory/purchases', {
        onSuccess: () => {
            toast.success(
                `Đã lưu phiếu nhập kho thành công cho ${purchaseForm.items.length} nguyên liệu!`,
            );
            purchaseForm.reset();
            purchaseForm.items = [
                {
                    ingredient_id: '',
                    quantity: '',
                    unit_cost: '',
                    batch_number: '',
                    expiry_date: '',
                },
            ];
            router.reload();
        },
        onError: () => {
            toast.error('Vui lòng kiểm tra lại thông tin trên phiếu nhập kho.');
        },
    });
};

onMounted(() => {
    if (!isRecipesPage.value) {
        fetchAiForecast();
    }

    if (activeTab.value === 'purchase') {
        void nextTick(observePurchaseFormCard);
    }
});

onBeforeUnmount(() => {
    purchaseCardResizeObserver?.disconnect();
});

watch(activeTab, (tab) => {
    if (tab === 'purchase') {
        void nextTick(observePurchaseFormCard);
    } else {
        purchaseCardResizeObserver?.disconnect();
        purchaseCardResizeObserver = null;
        aiForecastCardHeight.value = null;
    }
});

const isGeneratingAutoPo = ref(false);
const handleAutoPo = () => {
    isGeneratingAutoPo.value = true;
    router.post(
        '/inventory/auto-po/generate',
        {},
        {
            onSuccess: () => {
                toast.success(
                    'Đã tự động tạo Đơn mua hàng nháp (Auto PO) thành công!',
                );
            },
            onError: () => {
                toast.error('Lỗi khi tạo đơn mua hàng tự động.');
            },
            onFinish: () => {
                isGeneratingAutoPo.value = false;
            },
        },
    );
};

// ── Reconcile State ────────────────────────────────────────────────────────────
const reconcileSearch = ref('');
const reconcileNotes = ref('');
const reconcileEmployeeId = ref('');
const physicalStockMap = ref<Record<number, string>>({});
const isReconciling = ref(false);
const reconcileOpeningBalance = ref(false);

const initPhysicalStockMap = () => {
    props.ingredients.forEach((ing) => {
        if (physicalStockMap.value[ing.id] === undefined) {
            physicalStockMap.value[ing.id] = String(ing.stock ?? 0);
        }
    });
};

watch(
    activeTab,
    (newTab) => {
        if (newTab === 'purchase') {
            fetchAiForecast();
        } else if (newTab === 'reconcile') {
            initPhysicalStockMap();
        }
    },
    { immediate: true },
);

const getDiff = (ing: Ingredient) =>
    Number(physicalStockMap.value[ing.id] ?? ing.stock ?? 0) -
    Number(ing.stock ?? 0);

const reconcileStats = computed(() => {
    let matched = 0;
    let deficitCount = 0;
    let surplusCount = 0;
    let totalDeficitCost = 0;

    props.ingredients.forEach((ing) => {
        const diff = getDiff(ing);

        if (diff === 0) {
            matched++;
        } else if (diff < 0) {
            deficitCount++;
            totalDeficitCost += Math.abs(diff) * (ing.average_cost ?? 0);
        } else {
            surplusCount++;
        }
    });

    return { matched, deficitCount, surplusCount, totalDeficitCost };
});

const filteredReconcileIngredients = computed(() => {
    const q = reconcileSearch.value.trim().toLowerCase();

    if (!q) {
        return props.ingredients;
    }

    return props.ingredients.filter(
        (i) =>
            i.name.toLowerCase().includes(q) ||
            (i.category_name ?? '').toLowerCase().includes(q) ||
            (i.sku ?? '').toLowerCase().includes(q),
    );
});

const submitReconcile = () => {
    if (!props.activeBranchId) {
        toast.error(
            'Phạm vi Toàn chuỗi chỉ dùng để xem tổng hợp. Hãy chọn một chi nhánh trước khi cân bằng tồn kho.',
        );

        return;
    }

    isReconciling.value = true;
    const items = props.ingredients.map((ing) => ({
        ingredient_id: ing.id,
        physical_qty: Number(physicalStockMap.value[ing.id] ?? ing.stock ?? 0),
    }));

    router.post(
        '/inventory/reconcile',
        {
            reconcile_items: items,
            employee_id: reconcileEmployeeId.value || null,
            is_opening_balance: reconcileOpeningBalance.value,
            notes: reconcileNotes.value || 'Kiểm kê & Cân bằng tồn kho định kỳ',
        },
        {
            onSuccess: () => {
                toast.success(
                    'Đã hoàn tất kiểm kê & cân bằng tồn kho thành công!',
                );
            },
            onError: () => {
                toast.error('Có lỗi xảy ra khi cân bằng tồn kho.');
            },
            onFinish: () => {
                isReconciling.value = false;
            },
        },
    );
};

// ── Search & low-stock ────────────────────────────────────────────────────────
const ingredientSearch = ref('');

const filteredIngredients = computed(() => {
    const q = ingredientSearch.value.trim().toLowerCase();

    if (!q) {
        return props.ingredients;
    }

    return props.ingredients.filter(
        (i) =>
            i.name.toLowerCase().includes(q) ||
            (i.category_name ?? '').toLowerCase().includes(q),
    );
});

const filteredIngredientsByStorage = computed(() => {
    const storageType = selectedStorageTypeFilter.value;

    if (storageType === 'all') {
        return filteredIngredients.value;
    }

    return filteredIngredients.value.filter(
        (ingredient) => (ingredient.storage_type ?? 'dry') === storageType,
    );
});

const ingredientCurrentPage = ref(1);
const ingredientPerPage = 5;

const totalIngredientPages = computed(() =>
    Math.ceil(filteredIngredientsByStorage.value.length / ingredientPerPage),
);

const paginatedIngredients = computed(() => {
    const start = (ingredientCurrentPage.value - 1) * ingredientPerPage;

    return filteredIngredientsByStorage.value.slice(
        start,
        start + ingredientPerPage,
    );
});

const visibleIngredientPages = computed(() => {
    const total = totalIngredientPages.value;
    const current = ingredientCurrentPage.value;
    const pages: Array<number | string> = [];

    if (total <= 5) {
        for (let page = 1; page <= total; page++) {
            pages.push(page);
        }

        return pages;
    }

    pages.push(1);

    if (current > 3) {
        pages.push('...');
    }

    for (
        let page = Math.max(2, current - 1);
        page <= Math.min(total - 1, current + 1);
        page++
    ) {
        pages.push(page);
    }

    if (current < total - 2) {
        pages.push('...');
    }

    pages.push(total);

    return pages;
});

watch([ingredientSearch, selectedStorageTypeFilter], () => {
    ingredientCurrentPage.value = 1;
});

watch(totalIngredientPages, (total) => {
    if (total === 0) {
        ingredientCurrentPage.value = 1;
    } else if (ingredientCurrentPage.value > total) {
        ingredientCurrentPage.value = total;
    }
});

const lowStockIngredients = computed(() =>
    props.ingredients.filter(
        (i) =>
            i.stock !== null && i.stock < (i.min_stock_level || 5),
    ),
);

const getStockStatus = (ingredient: Ingredient) => {
    if (ingredient.stock === null) {
        return {
            label: 'Chưa cập nhật',
            classes:
                'border-slate-200 bg-slate-100 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
        };
    }

    if (ingredient.stock < (ingredient.min_stock_level || 5)) {
        return {
            label: 'Sắp hết',
            classes:
                'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/70 dark:bg-rose-950/40 dark:text-rose-300',
        };
    }

    if (ingredient.stock < (ingredient.reorder_level || 20)) {
        return {
            label: 'Cần nhập',
            classes:
                'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-300',
        };
    }

    return {
        label: 'Ổn định',
        classes:
            'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-300',
    };
};

// ── Computed ──────────────────────────────────────────────────────────────────
const zeroCostIngredients = computed(() =>
    props.ingredients.filter(
        (i) =>
            (i.average_cost ?? 0) === 0 &&
            props.products.some((p) =>
                p.recipes.some((r) => r.ingredient_name === i.name),
            ),
    ),
);

// ── Helpers ───────────────────────────────────────────────────────────────────
const page = usePage();
const vnd = (v: number) => new Intl.NumberFormat('vi-VN').format(v) + 'đ';

// ── Submit handlers ───────────────────────────────────────────────────────────
const submitIngredient = () => {
    ingredientForm.post('/inventory/ingredients', {
        onSuccess: () => {
            ingredientForm.reset();
            showAddIngredient.value = false;
        },
        onError: () => toast.error('Có lỗi khi thêm nguyên liệu.'),
    });
};

const activeProductRecipes = computed(() => {
    if (!activeProduct.value) {
        return [];
    }

    const updatedProduct = props.products.find(
        (p) => p.id === activeProduct.value!.id,
    );

    return updatedProduct ? updatedProduct.recipes : [];
});
void activeProductRecipes.value;

const deleteRecipe = (recipeId: number) => {
    if (confirm('Bạn có chắc chắn muốn xóa nguyên liệu này khỏi công thức?')) {
        router.delete(`/inventory/recipes/${recipeId}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã xóa nguyên liệu khỏi công thức.');
            },
            onError: () => {
                toast.error('Có lỗi xảy ra khi xóa.');
            },
        });
    }
};
void deleteRecipe;

const addRecipeRow = () => {
    recipeForm.items.push({
        ingredient_id: '',
        unit_id: '',
        quantity: '',
        waste_rate: '0',
    });
};

const recipeUnitsFor = (ingredientId: string) => {
    const ingredient = props.ingredients.find(
        (item) => String(item.id) === ingredientId,
    );

    if (!ingredient?.unit) {
        return props.units;
    }

    const ingredientUnit = props.units.find(
        (unit) => unit.id === ingredient.unit?.id,
    );

    return ingredientUnit?.type
        ? props.units.filter(
              (unit) => !unit.type || unit.type === ingredientUnit.type,
          )
        : props.units;
};

const recipeUnitFor = (unitId: string) =>
    props.units.find((unit) => String(unit.id) === String(unitId));

const recipeQuantityIsInteger = (unitId: string) =>
    recipeUnitFor(unitId)?.symbol.toLowerCase() === 'g';

const recipeQuantityStep = (unitId: string) =>
    recipeQuantityIsInteger(unitId) ? '1' : '0.001';

const normalizeRecipeQuantity = (item: {
    unit_id: string;
    quantity: string;
}) => {
    if (!recipeQuantityIsInteger(item.unit_id)) {
        return;
    }

    const wholeNumber = String(item.quantity ?? '').match(/^\d+/)?.[0] ?? '';
    item.quantity = wholeNumber;
};

const syncRecipeUnit = (item: { ingredient_id: string; unit_id: string }) => {
    if (!item.unit_id) {
        item.unit_id = String(
            props.ingredients.find(
                (ingredient) => String(ingredient.id) === item.ingredient_id,
            )?.unit?.id ?? '',
        );
    }
};

const removeRecipeRow = (index: number) => {
    recipeForm.items.splice(index, 1);
};

const openAddRecipeModal = (prod: Product) => {
    activeProduct.value = prod;
    recipeForm.product_id = String(prod.id);

    if (prod.recipes && prod.recipes.length > 0) {
        recipeForm.items = prod.recipes.map((r) => ({
            ingredient_id: String(r.ingredient_id),
            unit_id: String(r.unit_id ?? ''),
            quantity: String(r.quantity),
            waste_rate: String(r.waste_rate ?? 0),
        }));
    } else {
        recipeForm.items = [
            {
                ingredient_id: '',
                unit_id: '',
                quantity: '',
                waste_rate: '0',
            },
        ];
    }

    showAddRecipe.value = true;
};

const submitRecipe = () => {
    recipeForm.post('/inventory/recipes', {
        preserveScroll: true,
        onSuccess: () => {
            showAddRecipe.value = false;
            toast.success('Đã lưu công thức định lượng món ăn thành công.');
        },
        onError: () => toast.error('Có lỗi khi lưu công thức.'),
    });
};

// ── Khóa lô / thu hồi ──────────────────────────────────────────────────────────
const isOwnerRole = computed(() => {
    const roles = ((page.props.auth as any)?.user?.roles ?? []) as string[];

    return roles.includes('owner') || roles.includes('super_admin');
});

const lockBatch = (batchId: number) => {
    const reason = window.prompt(
        'Lý do khóa lô này (không cho dùng chế biến):',
        '',
    );

    if (reason === null) {
        return;
    }

    if (reason.trim().length < 5) {
        toast.error('Lý do khóa phải từ 5 ký tự.');

        return;
    }

    router.post(
        `/inventory/batches/${batchId}/lock`,
        { reason: reason.trim() },
        { preserveScroll: true, onSuccess: () => toast.success('Đã khóa lô.') },
    );
};

const unlockBatch = (batchId: number) => {
    router.post(
        `/inventory/batches/${batchId}/unlock`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã mở khóa lô.'),
        },
    );
};

const recallBatch = (batchId: number) => {
    const note =
        window.prompt('Ghi chú yêu cầu thu hồi (gửi Chủ & Trưởng kho):', '') ??
        '';
    router.post(
        `/inventory/batches/${batchId}/recall`,
        { note: note.trim() },
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã gửi yêu cầu thu hồi.'),
        },
    );
};
</script>

<template>
    <Head :title="isRecipesPage ? 'Công thức định lượng' : 'Kho nguyên liệu'" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-500 ring-1 ring-indigo-500/15"
                >
                    <component
                        :is="isRecipesPage ? Scale : Package"
                        class="size-6"
                    />
                </div>
                <div>
                    <p
                        class="mb-1 text-[10px] font-bold tracking-[0.16em] text-indigo-500 uppercase"
                    >
                        {{
                            isRecipesPage
                                ? 'Danh mục công thức'
                                : 'Vận hành kho'
                        }}
                    </p>
                    <h1 class="text-xl font-bold tracking-tight">
                        {{
                            isRecipesPage
                                ? 'Công thức định lượng'
                                : 'Kho nguyên liệu'
                        }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            isRecipesPage
                                ? 'Chuẩn hóa định lượng để tính giá vốn và trừ kho chính xác'
                                : 'Theo dõi nguyên liệu, lô hàng và mức tồn theo chi nhánh'
                        }}
                    </p>
                    <div
                        class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground"
                    >
                        <span
                            class="inline-flex items-center gap-1 rounded-full border border-indigo-500/15 bg-indigo-500/5 px-2 py-1 font-medium text-indigo-600 dark:text-indigo-300"
                        >
                            <MapPin class="size-3" />
                            {{
                                isRecipesPage
                                    ? `${products.length} món đang quản lý`
                                    : `Phạm vi: ${activeBranchName ?? 'Toàn chuỗi'}`
                            }}
                        </span>
                        <span
                            v-if="!isRecipesPage"
                            class="text-muted-foreground/70"
                        >
                            Số liệu theo chi nhánh đang chọn
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div
                    aria-label="Chuyển khu vực quản lý"
                    role="navigation"
                    class="flex items-center gap-1 rounded-xl border border-border bg-muted p-1"
                >
                    <Link
                        href="/inventory"
                        :aria-current="!isRecipesPage ? 'page' : undefined"
                        class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                        :class="
                            !isRecipesPage
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        <Package class="size-3.5" />Tồn kho
                    </Link>
                    <Link
                        href="/inventory/recipes"
                        :aria-current="isRecipesPage ? 'page' : undefined"
                        class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all"
                        :class="
                            isRecipesPage
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        <Scale class="size-3.5" />Công thức
                    </Link>
                </div>
                <Button
                    v-if="!isRecipesPage && activeTab === 'stock'"
                    @click="openAddIngredientModal()"
                    class="h-9 rounded-lg bg-indigo-600 px-3.5 text-xs font-bold text-white shadow-sm shadow-indigo-600/20 hover:bg-indigo-700"
                >
                    <Plus class="mr-1.5 size-4" />Thêm nguyên liệu
                </Button>
            </div>
        </div>

        <!-- Low-stock alert banner -->
        <div
            v-if="!isRecipesPage && lowStockIngredients.length > 0"
            class="flex flex-col gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 md:flex-row md:items-center md:justify-between dark:border-rose-900 dark:bg-rose-900/20 dark:text-rose-300"
        >
            <div class="flex items-start gap-3">
                <AlertTriangle class="mt-0.5 size-4 shrink-0 text-rose-500" />
                <div>
                    <span class="font-semibold"
                        >{{ lowStockIngredients.length }} nguyên liệu đang dưới
                        ngưỡng an toàn.</span
                    >
                    {{
                        lowStockIngredients
                            .map(
                                (i) =>
                                    `${i.name} (còn ${i.stock} ${i.unit?.symbol ?? ''})`,
                            )
                            .join(' · ')
                    }}. Nên kiểm tra và bổ sung trong kỳ nhập gần nhất.
                </div>
            </div>
            <Button
                size="sm"
                variant="outline"
                class="shrink-0 rounded-xl border-rose-300 bg-white text-xs font-bold text-rose-700 hover:bg-rose-100 dark:bg-slate-900 dark:text-rose-400"
                :disabled="isGeneratingAutoPo"
                @click="handleAutoPo"
            >
                <ShoppingCart class="mr-1.5 size-3.5" />
                {{
                    isGeneratingAutoPo
                        ? 'Đang tạo đơn...'
                        : 'Tạo đơn nhập đề xuất'
                }}
            </Button>
        </div>

        <!-- Operational safety gate -->
        <div
            v-if="!isRecipesPage && !safety.ready"
            class="flex items-start gap-3 rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            <div>
                <p class="font-semibold">
                    Một số hạng mục cần hoàn tất trước khi mở bán
                </p>
                <p class="mt-1 text-xs">
                    <span v-if="safety.products_without_recipes > 0"
                        >{{ safety.products_without_recipes }} món chưa có công
                        thức;
                    </span>
                    <span v-if="safety.negative_stocks > 0"
                        >{{ safety.negative_stocks }} nguyên liệu đang âm tồn;
                    </span>
                    <span v-if="(safety.negative_cases_open ?? 0) > 0"
                        >{{ safety.negative_cases_open }} hồ sơ âm tồn chưa
                        đóng;
                    </span>
                    <span v-if="safety.opening_balance_pending > 0"
                        >{{ safety.opening_balance_pending }} nguyên liệu chưa
                        đối soát số dư đầu kỳ;
                    </span>
                    <span v-if="safety.legacy_batches_pending > 0"
                        >{{ safety.legacy_batches_pending }} lô LEGACY-* chưa
                        được đối soát.</span
                    >
                </p>
                <Link
                    v-if="safety.products_without_recipes > 0"
                    href="/inventory/recipes"
                    class="mt-2 inline-flex items-center text-xs font-semibold text-rose-700 underline-offset-2 hover:underline dark:text-rose-300"
                >
                    Mở danh mục công thức →
                </Link>
            </div>
        </div>

        <NegativeInventoryCases
            v-if="!isRecipesPage"
            :cases="negativeStockCases"
            title="Âm nguyên liệu tại chi nhánh"
        />

        <!-- Zero-cost warning -->
        <div
            v-if="!isRecipesPage && zeroCostIngredients.length > 0"
            class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            <div>
                <span class="font-semibold"
                    >{{ zeroCostIngredients.length }} nguyên liệu chưa được cập
                    nhật giá vốn:</span
                >
                {{ zeroCostIngredients.map((i) => i.name).join(', ') }}. Giá vốn
                món có thể chưa chính xác cho đến khi bổ sung đơn giá.
            </div>
        </div>

        <!-- Tabs -->
        <div
            v-if="!isRecipesPage"
            class="flex flex-wrap items-center gap-1 self-start rounded-xl border border-border/80 bg-card/80 p-1 shadow-sm"
        >
            <button
                @click="activeTab = 'stock'"
                class="flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold transition-all"
                :class="
                    activeTab === 'stock'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                <Package class="size-3.5" />Tồn kho
            </button>
            <button
                @click="activeTab = 'purchase'"
                class="flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold transition-all"
                :class="
                    activeTab === 'purchase'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                <ShoppingCart class="size-3.5" />Nhập kho
            </button>
            <button
                @click="activeTab = 'central'"
                class="flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold transition-all"
                :class="
                    activeTab === 'central'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                <Warehouse class="size-3.5 text-emerald-500" />Nhận từ Kho Tổng
            </button>
            <button
                @click="activeTab = 'reconcile'"
                class="flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold transition-all"
                :class="
                    activeTab === 'reconcile'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                <ClipboardCheck class="size-3.5 text-emerald-500" />Kiểm kê &
                Đối soát kho
            </button>
            <button
                @click="activeTab = 'planning'"
                class="flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold transition-all"
                :class="
                    activeTab === 'planning'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                <CalendarCheck class="size-3.5 text-indigo-500" />Kế hoạch & Dự
                báo nhập
            </button>
        </div>

        <!-- ══ TỒN KHO / CÔNG THỨC (tách theo route) ══════════════════════════ -->
        <template v-if="activeTab === 'stock'">
            <div class="grid grid-cols-1 gap-6">
                <!-- Left: Ingredient list -->
                <div v-if="!isRecipesPage" class="lg:col-span-1">
                    <Card
                        class="h-full overflow-hidden border-slate-200/80 shadow-sm dark:border-slate-800"
                    >
                        <CardHeader
                            class="border-b border-border px-6 py-6 sm:px-8"
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <div class="flex items-center gap-3">
                                        <CardTitle
                                            class="text-lg font-extrabold tracking-tight text-foreground sm:text-xl"
                                            >Tồn kho nguyên liệu ({{
                                                filteredIngredientsByStorage.length
                                            }}/{{ ingredients.length }})</CardTitle
                                        >
                                    </div>
                                    <div
                                        class="mt-2 flex flex-wrap items-center gap-2 text-xs"
                                    >
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-rose-500/20 bg-rose-500/10 px-2.5 py-1 font-bold text-rose-600 dark:text-rose-400"
                                        >
                                            <span class="size-1.5 animate-pulse rounded-full bg-rose-500"></span>
                                            {{ lowStockIngredients.length }} sắp hết
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 font-bold text-amber-600 dark:text-amber-400"
                                        >
                                            <span class="size-1.5 rounded-full bg-amber-500"></span>
                                            {{
                                                ingredients.filter(
                                                    (i) =>
                                                        i.stock !== null &&
                                                        i.stock >=
                                                            (i.min_stock_level ||
                                                                5) &&
                                                        i.stock <
                                                            (i.reorder_level ||
                                                                20),
                                                ).length
                                            }} cần nhập bổ sung
                                        </span>
                                    </div>
                                </div>
                                <Button
                                    size="sm"
                                    class="h-10 rounded-xl bg-indigo-600 px-4 text-xs font-bold text-white shadow-sm shadow-indigo-600/25 hover:bg-indigo-700"
                                    @click="openAddIngredientModal()"
                                >
                                    <Plus class="mr-1.5 size-4" /> Thêm nguyên liệu
                                </Button>
                            </div>
                            <!-- Search -->
                            <div class="relative mt-5">
                                <Search
                                    class="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <input
                                    v-model="ingredientSearch"
                                    type="text"
                                    aria-label="Tìm kiếm nguyên liệu"
                                    placeholder="Tìm theo tên nguyên liệu, mã SKU hoặc danh mục..."
                                    class="h-11 w-full rounded-xl border border-input bg-background pr-4 pl-10 text-sm font-medium placeholder:text-muted-foreground focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                />
                            </div>
                            <!-- Storage Filter Pills -->
                            <div
                                class="flex flex-wrap items-center gap-2 border-t border-border/60 pt-4"
                            >
                                <button
                                    v-for="st in [
                                        { key: 'all', label: 'Tất cả' },
                                        { key: 'fresh', label: 'Tươi sống' },
                                        {
                                            key: 'daily',
                                            label: 'Trong ngày',
                                        },
                                        {
                                            key: 'canned_packaged',
                                            label: 'Đóng gói',
                                        },
                                        { key: 'dry', label: 'Đồ khô' },
                                    ]"
                                    :key="st.key"
                                    type="button"
                                    :aria-pressed="
                                        selectedStorageTypeFilter === st.key
                                    "
                                    @click="selectedStorageTypeFilter = st.key"
                                    :class="[
                                        'cursor-pointer rounded-xl border px-3.5 py-1.5 text-xs font-bold transition-all',
                                        selectedStorageTypeFilter === st.key
                                            ? 'border-indigo-500/40 bg-indigo-500/15 text-indigo-600 shadow-xs dark:border-indigo-500/30 dark:bg-indigo-500/20 dark:text-indigo-300'
                                            : 'border-transparent bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground',
                                    ]"
                                >
                                    {{ st.label }}
                                </button>
                            </div>
                        </CardHeader>
                        <CardContent class="divide-y divide-border p-0">
                            <div v-if="filteredIngredientsByStorage.length">
                                <div
                                    v-for="ing in paginatedIngredients"
                                    :key="ing.id"
                                    class="p-5 text-sm transition-colors hover:bg-muted/20 sm:p-6"
                                >
                                    <div
                                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <p
                                                    class="text-base font-bold tracking-tight text-foreground sm:text-lg"
                                                >
                                                    {{ ing.name }}
                                                </p>
                                                <button
                                                    type="button"
                                                    @click="
                                                        openEditIngredientModal(
                                                            ing,
                                                        )
                                                    "
                                                    class="cursor-pointer rounded-lg p-1 text-muted-foreground transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/40"
                                                    title="Chỉnh sửa thông tin nguyên liệu"
                                                    aria-label="Chỉnh sửa thông tin nguyên liệu"
                                                >
                                                    <Edit class="size-3.5" />
                                                </button>
                                                <span
                                                    v-if="
                                                        (ing.average_cost ??
                                                            0) === 0
                                                    "
                                                    class="shrink-0 rounded-lg border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-xs font-bold text-amber-600 dark:text-amber-400"
                                                    title="Chưa cập nhật giá vốn"
                                                >
                                                    Chưa có giá vốn
                                                </span>
                                            </div>

                                            <div
                                                class="mt-2.5 flex flex-wrap items-center gap-2"
                                            >
                                                <span
                                                    :class="[
                                                        'rounded-lg border px-2.5 py-0.5 text-xs font-bold',
                                                        getStorageBadgeClass(
                                                            ing.storage_type,
                                                        ),
                                                    ]"
                                                >
                                                    {{ getStorageLabel(ing) }}
                                                </span>

                                                <span
                                                    v-if="ing.storage_location"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-border bg-muted/60 px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                                                >
                                                    <MapPin class="size-3 text-indigo-500" />
                                                    {{ ing.storage_location }}
                                                </span>
                                            </div>

                                            <div
                                                class="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                                            >
                                                <span class="rounded-md border border-border bg-muted/80 px-2 py-0.5 font-mono text-xs font-bold text-foreground">
                                                    {{ ing.sku ?? 'Chưa có SKU' }}
                                                </span>
                                                <span>·</span>
                                                <span class="font-medium text-foreground/80">
                                                    {{
                                                        ing.category_name ??
                                                        'Nguyên liệu'
                                                    }}
                                                </span>
                                                <span>·</span>
                                                <span class="inline-flex items-center gap-1 font-medium text-foreground/80">
                                                    <MapPin class="size-3 text-muted-foreground" />
                                                    {{
                                                        ing.branch_name ??
                                                        'Chưa xác định'
                                                    }}
                                                </span>
                                                <span
                                                    v-if="ing.average_cost > 0"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-indigo-500/20 bg-indigo-500/10 px-2.5 py-0.5 font-bold text-indigo-600 dark:text-indigo-400"
                                                >
                                                    Giá vốn: {{ vnd(ing.average_cost) }}/{{
                                                        ing.unit?.symbol ?? 'đv'
                                                    }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="flex flex-row items-center justify-between border-t border-border/50 pt-3 sm:flex-col sm:items-end sm:justify-start sm:border-0 sm:pt-0">
                                            <span
                                                class="mb-1 block text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >
                                                Tồn hiện tại
                                            </span>
                                            <div
                                                class="flex items-center justify-end gap-2"
                                            >
                                                <TrendingDown
                                                    v-if="
                                                        ing.stock !== null &&
                                                        ing.stock <
                                                            (ing.min_stock_level ||
                                                                5)
                                                    "
                                                    class="size-4 text-rose-500"
                                                />
                                                <span
                                                    class="rounded-xl border px-3.5 py-1.5 font-mono text-base font-black tracking-tight sm:text-xl"
                                                    :class="
                                                        ing.stock === null
                                                            ? 'border-border bg-muted text-muted-foreground'
                                                            : ing.stock <
                                                                (ing.min_stock_level ||
                                                                    5)
                                                              ? 'border-rose-500/30 bg-rose-500/15 text-rose-600 dark:text-rose-400'
                                                              : ing.stock <
                                                                  (ing.reorder_level ||
                                                                      20)
                                                                ? 'border-amber-500/30 bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                                                : 'border-emerald-500/30 bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                                    "
                                                >
                                                    {{
                                                        ing.stock !== null
                                                            ? ing.stock.toFixed(
                                                                  1,
                                                              )
                                                            : '—'
                                                    }}
                                                    {{ ing.unit?.symbol ?? '' }}
                                                </span>
                                            </div>
                                            <span
                                                class="mt-1.5 inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-bold"
                                                :class="
                                                    getStockStatus(ing).classes
                                                "
                                            >
                                                {{ getStockStatus(ing).label }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Active Batches (FEFO Breakdown) -->
                                    <div
                                        v-if="
                                            ing.batches &&
                                            ing.batches.length > 0
                                        "
                                        class="mt-4 space-y-2.5 rounded-2xl border border-border/80 bg-muted/40 p-4 sm:p-5"
                                    >
                                        <div
                                            class="flex items-center justify-between text-xs font-bold tracking-wide text-muted-foreground"
                                        >
                                            <span
                                                class="flex items-center gap-1.5 text-foreground"
                                            >
                                                <Layers
                                                    class="size-4 text-indigo-500"
                                                />
                                                Theo dõi lô (FEFO):
                                                <span class="rounded-md bg-indigo-500/10 px-2 py-0.5 font-bold text-indigo-600 dark:text-indigo-400">
                                                    {{ ing.batches.length }} lô
                                                </span>
                                            </span>
                                            <span class="uppercase tracking-wider">Số lượng còn</span>
                                        </div>

                                        <div
                                            v-for="b in ing.batches"
                                            :key="b.id"
                                            class="flex flex-col gap-2.5 border-t border-border/60 pt-3 text-xs sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div
                                                class="flex flex-wrap items-center gap-2 font-mono"
                                            >
                                                <span
                                                    class="rounded-md bg-background px-2 py-0.5 text-xs font-bold text-foreground ring-1 ring-border"
                                                    >{{ b.batch_number }}</span
                                                >
                                                <span class="text-xs text-muted-foreground"
                                                    >· Nhập
                                                    {{
                                                        b.purchased_at || '—'
                                                    }}</span
                                                >
                                                <span
                                                    v-if="b.expiry_date"
                                                    :class="[
                                                        'rounded-md border px-2 py-0.5 text-xs font-bold',
                                                        b.is_expired
                                                            ? 'border-rose-500/20 bg-rose-500/15 text-rose-600 dark:text-rose-300'
                                                            : b.is_expiring_soon
                                                              ? 'border-amber-500/20 bg-amber-500/15 text-amber-600 dark:text-amber-300'
                                                              : 'border-emerald-500/20 bg-emerald-500/15 text-emerald-600 dark:text-emerald-300',
                                                    ]"
                                                >
                                                    {{
                                                        b.is_expired
                                                            ? 'Hết hạn · ' +
                                                              b.expiry_date
                                                            : b.is_expiring_soon
                                                              ? 'Sắp hết hạn · ' +
                                                                b.expiry_date
                                                              : 'HSD: ' +
                                                                b.expiry_date
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex items-center justify-between gap-3 sm:justify-end"
                                            >
                                                <span
                                                    v-if="b.is_locked"
                                                    class="rounded-md border border-orange-500/20 bg-orange-500/15 px-2 py-0.5 text-xs font-bold text-orange-600 dark:text-orange-400"
                                                    :title="b.lock_reason ?? ''"
                                                    >Đã khóa</span
                                                >
                                                <span
                                                    v-else-if="b.is_recalled"
                                                    class="rounded-md border border-purple-500/20 bg-purple-500/15 px-2 py-0.5 text-xs font-bold text-purple-600 dark:text-purple-400"
                                                    >Đã thu hồi</span
                                                >
                                                <span
                                                    class="font-mono text-sm font-black text-foreground sm:text-base"
                                                >
                                                    {{ b.quantity_remaining }}
                                                    {{ ing.unit?.symbol ?? '' }}
                                                </span>
                                                <!-- Hành động khóa/thu hồi -->
                                                <div
                                                    v-if="
                                                        b.status !== 'recalled'
                                                    "
                                                    class="flex items-center gap-1.5"
                                                >
                                                    <button
                                                        v-if="!b.is_locked"
                                                        type="button"
                                                        @click="lockBatch(b.id)"
                                                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-orange-500/30 bg-orange-500/10 px-3 text-xs font-bold text-orange-600 transition-colors hover:bg-orange-500/20 dark:text-orange-400"
                                                        title="Khóa lô này"
                                                        aria-label="Khóa lô này"
                                                    >
                                                        <LockKeyhole
                                                            class="size-3.5"
                                                        />Khóa
                                                    </button>
                                                    <button
                                                        v-if="
                                                            b.is_locked &&
                                                            isOwnerRole
                                                        "
                                                        type="button"
                                                        @click="
                                                            unlockBatch(b.id)
                                                        "
                                                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 text-xs font-bold text-emerald-600 transition-colors hover:bg-emerald-500/20 dark:text-emerald-400"
                                                        title="Mở khóa (chỉ Chủ)"
                                                        aria-label="Mở khóa lô này"
                                                    >
                                                        <UnlockKeyhole
                                                            class="size-3.5"
                                                        />Mở khóa
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="
                                                            recallBatch(b.id)
                                                        "
                                                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-purple-500/30 bg-purple-500/10 px-3 text-xs font-bold text-purple-600 transition-colors hover:bg-purple-500/20 dark:text-purple-400"
                                                        title="Yêu cầu kho thu hồi"
                                                        aria-label="Yêu cầu kho thu hồi lô này"
                                                    >
                                                        <RefreshCw
                                                            class="size-3.5"
                                                        />Thu hồi
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="totalIngredientPages > 1"
                                class="flex items-center justify-between border-t border-border bg-slate-50/50 p-3 dark:bg-slate-900/10"
                            >
                                <span
                                    class="text-[11px] font-medium text-muted-foreground"
                                >
                                    Trang {{ ingredientCurrentPage }} /
                                    {{ totalIngredientPages }} · tổng
                                    {{ filteredIngredientsByStorage.length }}
                                    nguyên liệu
                                </span>
                                <div class="flex items-center gap-1">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        :disabled="ingredientCurrentPage === 1"
                                        @click="ingredientCurrentPage--"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg p-0"
                                    >
                                        <ChevronLeft class="size-3.5" />
                                    </Button>

                                    <template
                                        v-for="(
                                            page, idx
                                        ) in visibleIngredientPages"
                                        :key="idx"
                                    >
                                        <span
                                            v-if="page === '...'"
                                            class="px-1 text-xs font-bold text-muted-foreground select-none"
                                            >...</span
                                        >
                                        <Button
                                            v-else
                                            size="sm"
                                            :variant="
                                                ingredientCurrentPage === page
                                                    ? 'default'
                                                    : 'outline'
                                            "
                                            @click="
                                                ingredientCurrentPage =
                                                    Number(page)
                                            "
                                            class="h-7 min-w-[28px] rounded-lg px-1 text-xs font-bold"
                                        >
                                            {{ page }}
                                        </Button>
                                    </template>

                                    <Button
                                        size="sm"
                                        variant="outline"
                                        :disabled="
                                            ingredientCurrentPage ===
                                            totalIngredientPages
                                        "
                                        @click="ingredientCurrentPage++"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg p-0"
                                    >
                                        <ChevronRight class="size-3.5" />
                                    </Button>
                                </div>
                            </div>
                            <div
                                v-if="!filteredIngredientsByStorage.length"
                                class="py-12 text-center text-xs text-muted-foreground"
                            >
                                Chưa có nguyên liệu trong phạm vi này.
                                <span
                                    class="mt-1 block text-muted-foreground/70"
                                    >Thêm nguyên liệu để bắt đầu theo dõi tồn
                                    kho.</span
                                >
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Recipe catalog on /inventory/recipes -->
                <div v-if="isRecipesPage" class="lg:col-span-1">
                    <Card
                        class="overflow-hidden border-slate-200/80 shadow-sm dark:border-slate-800"
                    >
                        <CardHeader
                            class="border-b border-border px-5 py-5 sm:px-6"
                        >
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <CardTitle
                                        class="flex items-center gap-2 text-lg tracking-tight"
                                    >
                                        <Scale
                                            class="size-5 text-indigo-500"
                                        />Công thức định lượng món ăn
                                    </CardTitle>
                                    <CardDescription class="text-xs">
                                        Khai báo nguyên liệu cấu thành cho từng
                                        món để tính giá vốn và trừ kho chính
                                        xác.
                                    </CardDescription>
                                </div>
                                <div class="relative w-full sm:w-72">
                                    <Search
                                        class="absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400"
                                    />
                                    <input
                                        v-model="recipeSearch"
                                        type="text"
                                        aria-label="Tìm kiếm món ăn"
                                        placeholder="Tìm theo tên món hoặc mã món..."
                                        class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/80 pr-3 pl-9 text-xs font-medium placeholder:text-slate-400 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/15 focus:outline-none dark:border-slate-700 dark:bg-slate-900/70"
                                    />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div
                                v-if="paginatedProducts.length"
                                class="divide-y divide-border"
                            >
                                <div
                                    v-for="p in paginatedProducts"
                                    :key="p.id"
                                    class="flex flex-col gap-4 p-5 transition-colors hover:bg-muted/25 sm:px-6"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div>
                                            <h4
                                                class="text-base font-bold tracking-tight"
                                            >
                                                {{ p.name }}
                                            </h4>
                                            <p
                                                class="text-[10px] text-muted-foreground"
                                            >
                                                Mã món: {{ p.code }}
                                            </p>
                                        </div>
                                        <Button
                                            @click="openAddRecipeModal(p)"
                                            size="sm"
                                            variant="outline"
                                            class="btn-set-recipe h-9 rounded-lg border-indigo-200 px-3 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-300 dark:hover:bg-indigo-950/40"
                                        >
                                            <Settings2
                                                class="mr-1 size-3.5"
                                            />{{
                                                p.recipes.length
                                                    ? 'Chỉnh sửa công thức'
                                                    : 'Thiết lập công thức'
                                            }}
                                        </Button>
                                    </div>
                                    <div
                                        class="rounded-xl border border-border/80 bg-muted/30 p-3.5"
                                    >
                                        <div
                                            v-if="p.recipes.length"
                                            class="flex flex-wrap gap-2"
                                        >
                                            <span
                                                v-for="r in p.recipes"
                                                :key="r.id"
                                                class="flex items-center gap-1 rounded-lg border bg-card px-2.5 py-1.5 text-xs font-medium"
                                            >
                                                <strong
                                                    >{{
                                                        r.ingredient_name
                                                    }}:</strong
                                                >
                                                <span
                                                    class="font-mono font-bold text-indigo-500"
                                                    >{{ r.quantity }}</span
                                                >
                                                <span
                                                    class="text-muted-foreground"
                                                    >{{ r.unit_symbol }}</span
                                                >
                                                <span
                                                    v-if="r.waste_rate > 0"
                                                    class="rounded border border-amber-200 bg-amber-50 px-1 text-[10px] text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                                                >
                                                    +{{ r.waste_rate }}% hao
                                                </span>
                                            </span>
                                        </div>
                                        <div
                                            v-else
                                            class="flex items-center gap-1 text-[11px] text-muted-foreground"
                                        >
                                            <AlertTriangle
                                                class="size-3.5 text-amber-400"
                                            />
                                            Chưa khai báo công thức · món này
                                            chưa được tính giá vốn.
                                        </div>
                                    </div>
                                </div>

                                <!-- Pagination controls for recipes list -->
                                <div
                                    v-if="totalRecipePages > 1"
                                    class="flex items-center justify-between border-t border-border bg-slate-50/50 p-4 dark:bg-slate-900/10"
                                >
                                    <span
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        Trang {{ recipeCurrentPage }} /
                                        {{ totalRecipePages }} (Tổng
                                        {{ filteredRecipeProducts.length }} món)
                                    </span>
                                    <div class="flex items-center gap-1.5">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            :disabled="recipeCurrentPage === 1"
                                            @click="recipeCurrentPage--"
                                            class="flex h-7 w-7 items-center justify-center rounded-lg p-0"
                                        >
                                            <ChevronLeft class="size-3.5" />
                                        </Button>

                                        <template
                                            v-for="(
                                                page, idx
                                            ) in visibleRecipePages"
                                            :key="idx"
                                        >
                                            <span
                                                v-if="page === '...'"
                                                class="px-1.5 text-xs font-bold text-muted-foreground select-none"
                                                >...</span
                                            >
                                            <Button
                                                v-else
                                                size="sm"
                                                :variant="
                                                    recipeCurrentPage === page
                                                        ? 'default'
                                                        : 'outline'
                                                "
                                                @click="
                                                    recipeCurrentPage =
                                                        Number(page)
                                                "
                                                class="h-7 min-w-[28px] rounded-lg px-1 text-xs font-bold"
                                            >
                                                {{ page }}
                                            </Button>
                                        </template>

                                        <Button
                                            size="sm"
                                            variant="outline"
                                            :disabled="
                                                recipeCurrentPage ===
                                                totalRecipePages
                                            "
                                            @click="recipeCurrentPage++"
                                            class="flex h-7 w-7 items-center justify-center rounded-lg p-0"
                                        >
                                            <ChevronRight class="size-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-else-if="products.length"
                                class="py-12 text-center text-xs text-muted-foreground"
                            >
                                Không tìm thấy món phù hợp với từ khóa.
                            </div>
                            <div
                                v-else
                                class="py-12 text-center text-xs text-muted-foreground"
                            >
                                Chưa có món ăn cần thiết lập công thức.
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </template>

        <!-- ══ TAB: NHẬP HÀNG ═════════════════════════════════════════════════ -->
        <template v-else-if="activeTab === 'purchase'">
            <div
                class="grid animate-in gap-6 duration-200 fade-in lg:grid-cols-5"
            >
                <!-- Form nhập hàng lô (nhiều nguyên liệu trên 1 hóa đơn) -->
                <div ref="purchaseFormCard" class="h-fit lg:col-span-2">
                    <Card class="border-slate-200/80 shadow-sm">
                        <CardHeader class="border-b border-border pb-3">
                            <CardTitle
                                class="flex items-center gap-2 text-sm font-bold"
                            >
                                <ArrowDownToLine
                                    class="size-4 text-indigo-500"
                                />
                                Ghi nhận nhập kho theo hóa đơn
                            </CardTitle>
                            <CardDescription class="text-[11px]">
                                Nhập danh sách nhiều nguyên liệu trên cùng 1
                                chứng từ/hóa đơn nhập kho.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="pt-4">
                            <form
                                @submit.prevent="submitPurchaseForm"
                                class="space-y-4"
                            >
                                <!-- Thông tin chung hóa đơn -->
                                <div
                                    class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                                >
                                    <div class="space-y-1.5">
                                        <Label class="text-xs">Ngày nhập</Label>
                                        <Input
                                            v-model="purchaseForm.occurred_at"
                                            type="date"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-xs"
                                            >Ghi chú / Số hóa đơn</Label
                                        >
                                        <Input
                                            v-model="purchaseForm.notes"
                                            placeholder="VD: HD-00123 / Nhập kho định kỳ"
                                        />
                                    </div>
                                </div>

                                <!-- Ảnh chứng từ / Hóa đơn cứng -->
                                <div class="space-y-1.5">
                                    <Label
                                        class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                    >
                                        Hóa đơn cứng (Ảnh chụp chứng từ)
                                        <span class="font-bold text-rose-500"
                                            >*</span
                                        >
                                    </Label>
                                    <input
                                        type="file"
                                        @change="
                                            (e) =>
                                                (purchaseForm.invoice_file =
                                                    (
                                                        e.target as HTMLInputElement
                                                    ).files?.[0] || null)
                                        "
                                        accept="image/*,.pdf"
                                        required
                                        class="w-full rounded-xl border border-slate-200 bg-background p-2 text-xs text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                    />
                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Tải lên 1 ảnh hóa đơn dùng chung cho tất
                                        cả các dòng trong phiếu.
                                    </p>
                                </div>

                                <!-- Table các dòng nguyên liệu nhập -->
                                <div class="space-y-2 border-t pt-3">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <Label
                                            class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                        >
                                            Danh sách nguyên liệu nhập kho ({{
                                                purchaseForm.items.length
                                            }})
                                        </Label>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            @click="addPurchaseItemRow()"
                                            class="h-7 border-indigo-200 text-[11px] font-semibold text-indigo-600 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-400"
                                        >
                                            <Plus class="mr-1 size-3.5" /> Thêm
                                            nguyên liệu
                                        </Button>
                                    </div>

                                    <div class="space-y-3">
                                        <div
                                            v-for="(
                                                item, idx
                                            ) in purchaseForm.items"
                                            :key="idx"
                                            class="relative space-y-2.5 rounded-xl border border-slate-200 bg-slate-50/50 p-3 dark:border-slate-800 dark:bg-slate-900/40"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-2 border-b pb-1.5 dark:border-slate-800"
                                            >
                                                <span
                                                    class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400"
                                                >
                                                    #{{ Number(idx) + 1 }}
                                                </span>
                                                <button
                                                    type="button"
                                                    @click="
                                                        removePurchaseItemRow(
                                                            Number(idx),
                                                        )
                                                    "
                                                    class="rounded-lg p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40"
                                                    title="Xóa dòng này"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                </button>
                                            </div>

                                            <!-- Nguyên liệu select -->
                                            <div class="space-y-1">
                                                <Label
                                                    class="text-[11px] font-medium"
                                                    >Nguyên liệu
                                                    <span class="text-rose-500"
                                                        >*</span
                                                    ></Label
                                                >
                                                <select
                                                    v-model="item.ingredient_id"
                                                    @change="
                                                        onPurchaseIngredientChange(
                                                            item,
                                                        )
                                                    "
                                                    required
                                                    class="w-full rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs font-semibold focus:border-indigo-500 focus:outline-none"
                                                >
                                                    <option value="" disabled>
                                                        Chọn nguyên liệu...
                                                    </option>
                                                    <option
                                                        v-for="ing in ingredients"
                                                        :key="ing.id"
                                                        :value="String(ing.id)"
                                                    >
                                                        {{ ing.name }} (tồn:
                                                        {{
                                                            ing.stock?.toFixed(
                                                                1,
                                                            ) ?? '—'
                                                        }}
                                                        {{
                                                            ing.unit?.symbol ??
                                                            ''
                                                        }})
                                                    </option>
                                                </select>
                                            </div>

                                            <!-- Số lượng & Đơn giá -->
                                            <div class="grid grid-cols-2 gap-2">
                                                <div class="space-y-1">
                                                    <Label
                                                        class="text-[11px] font-medium"
                                                        >Số lượng
                                                        <span
                                                            class="text-rose-500"
                                                            >*</span
                                                        ></Label
                                                    >
                                                    <Input
                                                        v-model="item.quantity"
                                                        type="number"
                                                        step="0.001"
                                                        min="0.001"
                                                        placeholder="0"
                                                        required
                                                        class="h-8 font-mono text-xs"
                                                    />
                                                </div>
                                                <div class="space-y-1">
                                                    <Label
                                                        class="text-[11px] font-medium"
                                                        >Đơn giá (đ)
                                                        <span
                                                            class="text-rose-500"
                                                            >*</span
                                                        ></Label
                                                    >
                                                    <Input
                                                        v-model="item.unit_cost"
                                                        type="number"
                                                        step="1"
                                                        min="0"
                                                        placeholder="0"
                                                        required
                                                        class="h-8 font-mono text-xs"
                                                    />
                                                </div>
                                            </div>

                                            <!-- Mã lô & Hạn sử dụng (optional) -->
                                            <div
                                                class="grid grid-cols-2 gap-2 pt-1"
                                            >
                                                <div class="space-y-1">
                                                    <Label
                                                        class="text-[10px] text-muted-foreground"
                                                        >Mã lô (tùy chọn)</Label
                                                    >
                                                    <Input
                                                        v-model="
                                                            item.batch_number
                                                        "
                                                        placeholder="Tự tạo nếu trống"
                                                        class="h-7 text-[11px]"
                                                    />
                                                </div>
                                                <div class="space-y-1">
                                                    <Label
                                                        class="text-[10px] text-amber-600 dark:text-amber-400"
                                                        >Hạn sử dụng</Label
                                                    >
                                                    <Input
                                                        v-model="
                                                            item.expiry_date
                                                        "
                                                        type="date"
                                                        class="h-7 text-[11px]"
                                                    />
                                                </div>
                                            </div>

                                            <!-- Subtotal row preview -->
                                            <div
                                                v-if="
                                                    Number(item.quantity) > 0 &&
                                                    Number(item.unit_cost) >= 0
                                                "
                                                class="flex justify-end text-[11px] font-semibold text-slate-600 dark:text-slate-300"
                                            >
                                                Thành tiền:
                                                <span
                                                    class="ml-1.5 font-mono text-indigo-600 dark:text-indigo-400"
                                                    >{{
                                                        vnd(
                                                            Number(
                                                                item.quantity,
                                                            ) *
                                                                Number(
                                                                    item.unit_cost,
                                                                ),
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Total Receipt Summary KPI Card -->
                                <div
                                    class="flex items-center justify-between rounded-xl border border-indigo-500/20 bg-indigo-500/10 px-4 py-3 text-sm font-bold"
                                >
                                    <span
                                        class="text-indigo-900 dark:text-indigo-200"
                                        >Tổng cộng phiếu nhập:</span
                                    >
                                    <span
                                        class="font-mono text-base text-indigo-700 dark:text-indigo-300"
                                        >{{
                                            vnd(totalPurchaseReceiptCost)
                                        }}</span
                                    >
                                </div>

                                <Button
                                    type="submit"
                                    class="w-full rounded-xl bg-indigo-600 font-semibold text-white hover:bg-indigo-700"
                                    :disabled="purchaseForm.processing"
                                >
                                    <ArrowDownToLine class="mr-2 size-4" />
                                    {{
                                        purchaseForm.processing
                                            ? 'Đang gửi phê duyệt...'
                                            : `Lưu phiếu nhập kho (${purchaseForm.items.length} mặt hàng)`
                                    }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <!-- AI Forecast Recommendations -->
                <Card
                    :style="
                        aiForecastCardHeight
                            ? { height: `${aiForecastCardHeight}px` }
                            : undefined
                    "
                    class="h-full min-h-0 animate-in overflow-hidden border-indigo-200 bg-gradient-to-br from-indigo-50/20 via-background to-background shadow-sm duration-250 slide-in-from-right lg:col-span-3 dark:border-indigo-900/60"
                >
                    <CardHeader class="border-b border-border pb-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle
                                    class="flex items-center gap-1.5 text-sm font-bold text-indigo-700 dark:text-indigo-400"
                                >
                                    <Sparkles
                                        class="size-4 animate-pulse text-indigo-500"
                                    />
                                    🔮 Đề xuất nhập hàng AI (7 ngày tới)
                                </CardTitle>
                                <CardDescription class="text-[11px]"
                                    >AI phân tích xu hướng và đề xuất số lượng
                                    nhập tối ưu.</CardDescription
                                >
                            </div>
                            <span
                                class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300"
                            >
                                Tối ưu tự động
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent
                        class="flex min-h-0 flex-1 flex-col divide-y divide-border overflow-hidden p-0"
                    >
                        <div
                            v-if="loadingForecast"
                            class="p-16 text-center text-xs text-muted-foreground"
                        >
                            <span class="mr-1 inline-block animate-spin"
                                >🔄</span
                            >
                            Đang tính toán phân tích dữ liệu...
                        </div>
                        <div
                            v-else-if="aiForecasts.length === 0"
                            class="p-16 text-center text-xs text-muted-foreground"
                        >
                            Không tìm thấy dữ liệu đề xuất.
                        </div>
                        <div
                            v-else
                            class="ai-forecast-scroll min-h-0 flex-1 divide-y divide-border overflow-y-auto"
                        >
                            <div
                                v-for="item in aiForecasts"
                                :key="item.ingredient_id"
                                class="flex items-start justify-between gap-4 p-4 text-xs transition-colors hover:bg-muted/10"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="font-bold text-slate-800 dark:text-slate-200"
                                            >{{ item.ingredient_name }}</span
                                        >
                                        <span
                                            v-if="
                                                item.confidence_score !==
                                                    null &&
                                                item.confidence_score !==
                                                    undefined
                                            "
                                            :class="[
                                                'rounded-md px-1.5 py-0.5 text-[9px] font-bold',
                                                item.confidence_score >= 80
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                                    : item.confidence_score >=
                                                        60
                                                      ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                                                      : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                                            ]"
                                        >
                                            Độ tin cậy:
                                            {{ item.confidence_score }}%
                                        </span>
                                        <span
                                            v-else
                                            class="rounded-md border border-amber-200/60 bg-amber-50 px-1.5 py-0.5 text-[9px] font-semibold text-amber-700 dark:border-amber-900/30 dark:bg-amber-950/40 dark:text-amber-400"
                                        >
                                            Chờ thêm dữ liệu
                                        </span>
                                    </div>
                                    <p
                                        class="mt-1 text-[10px] leading-relaxed text-muted-foreground"
                                    >
                                        {{ item.reason }}
                                    </p>
                                    <div
                                        class="mt-2 flex items-center gap-4 text-[10px] text-slate-500"
                                    >
                                        <span
                                            >Tồn hiện tại:
                                            <strong
                                                class="font-mono text-slate-700 dark:text-slate-300"
                                                >{{ item.current_stock }}
                                                {{ item.unit_symbol }}</strong
                                            ></span
                                        >
                                        <span
                                            >Dự báo 7 ngày tới:
                                            <strong
                                                class="font-mono text-slate-700 dark:text-slate-300"
                                                >{{
                                                    item.predicted_usage_next_7_days
                                                }}
                                                {{ item.unit_symbol }}</strong
                                            ></span
                                        >
                                    </div>
                                </div>
                                <div
                                    class="flex shrink-0 flex-col items-end gap-1.5 text-right"
                                >
                                    <div
                                        class="font-bold text-indigo-600 dark:text-indigo-400"
                                    >
                                        Cần mua:
                                        <span class="font-mono text-sm">{{
                                            item.suggested_purchase
                                        }}</span>
                                        {{ item.unit_symbol }}
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        type="button"
                                        :disabled="
                                            !item.suggested_purchase ||
                                            Number(item.suggested_purchase) <= 0
                                        "
                                        class="h-7 border-indigo-200 text-[10px] text-indigo-600 hover:bg-indigo-50 disabled:opacity-50 dark:border-indigo-800 dark:text-indigo-400"
                                        @click="applyForecast(item)"
                                    >
                                        {{
                                            Number(item.suggested_purchase) > 0
                                                ? 'Áp dụng đề xuất'
                                                : 'Đủ tồn kho'
                                        }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent purchases -->
                <Card class="shadow-sm lg:col-span-5">
                    <CardHeader class="border-b border-border pb-3">
                        <CardTitle class="text-sm font-bold"
                            >Lịch sử nhập hàng gần đây</CardTitle
                        >
                        <CardDescription class="text-[11px]"
                            >20 giao dịch nhập kho mới nhất</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            v-if="recentPurchases.length === 0"
                            class="flex flex-col items-center gap-2 py-16 text-sm text-muted-foreground"
                        >
                            <ShoppingCart class="size-10 opacity-30" />
                            <p>Chưa có lần nhập hàng nào</p>
                        </div>
                        <div v-else>
                            <div
                                class="grid grid-cols-[1fr_auto_auto_auto] gap-3 border-b border-border bg-muted/40 px-4 py-2.5 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                <div>Nguyên liệu / Nhà cung cấp</div>
                                <div class="text-right">Số lượng</div>
                                <div class="text-right">Đơn giá</div>
                                <div class="text-right">Thành tiền</div>
                            </div>
                            <div
                                v-for="p in recentPurchases"
                                :key="p.id"
                                class="grid grid-cols-[1fr_auto_auto_auto] gap-3 border-b border-border px-4 py-3 text-sm transition-colors last:border-0 hover:bg-muted/20"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ p.ingredient_name }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-muted-foreground"
                                    >
                                        {{
                                            p.supplier_name !== '—'
                                                ? p.supplier_name
                                                : 'Không có NCC'
                                        }}
                                        <span v-if="p.occurred_at">
                                            · {{ p.occurred_at }}</span
                                        >
                                        <span
                                            v-if="p.batch_number"
                                            class="font-semibold text-indigo-500"
                                        >
                                            · Lô {{ p.batch_number }}</span
                                        >
                                        <span v-if="p.notes" class="italic">
                                            · {{ p.notes }}</span
                                        >
                                    </p>
                                </div>
                                <div class="text-right font-mono text-sm">
                                    {{ p.quantity.toFixed(3) }}
                                </div>
                                <div
                                    class="text-right text-xs text-muted-foreground"
                                >
                                    {{ vnd(p.unit_cost) }}
                                </div>
                                <div
                                    class="text-right font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{ vnd(p.total_cost) }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </template>

        <!-- ══ TAB: NHẬP TỪ TỔNG KHO ══════════════════════════════════════════ -->
        <template v-else-if="activeTab === 'central'">
            <template v-if="centralRequestStep === 'select'">
                <div
                    class="grid animate-in gap-6 duration-200 fade-in lg:grid-cols-5"
                >
                    <Card class="lg:col-span-3">
                        <CardHeader class="border-b border-border pb-4">
                            <div
                                class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"
                            >
                                <div>
                                    <CardTitle
                                        class="flex items-center gap-2 text-base font-bold"
                                    >
                                        <Warehouse
                                            class="size-5 text-emerald-500"
                                        />
                                        Chọn nguyên liệu cần cấp
                                    </CardTitle>
                                    <CardDescription class="mt-1 text-xs">
                                        Chọn nguyên liệu từ menu như khi nhân
                                        viên chọn món. Sau đó xác nhận để nhập
                                        thông tin gửi Kho Tổng.
                                    </CardDescription>
                                </div>
                                <a
                                    href="/inventory/branch-requisition"
                                    class="shrink-0 text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                >
                                    Nhận hàng Kho Tổng / Theo dõi đơn →
                                </a>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4 pt-5">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div
                                    class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3"
                                >
                                    <p
                                        class="text-[10px] font-bold tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                                    >
                                        Kho xuất hàng
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-bold text-foreground"
                                    >
                                        Kho Tổng độc lập
                                    </p>
                                    <p
                                        class="mt-1 text-[10px] text-muted-foreground"
                                    >
                                        Không thuộc danh sách chi nhánh.
                                    </p>
                                </div>
                                <div
                                    class="rounded-xl border border-indigo-500/20 bg-indigo-500/10 p-3"
                                >
                                    <p
                                        class="text-[10px] font-bold tracking-wide text-indigo-700 uppercase dark:text-indigo-300"
                                    >
                                        Đề xuất nhập hàng AI
                                    </p>
                                    <p
                                        class="mt-1 text-sm font-bold text-foreground"
                                    >
                                        {{
                                            (
                                                branchReplenishmentSuggestions ??
                                                []
                                            ).length
                                        }}
                                        nguyên liệu cần xem
                                    </p>
                                    <p
                                        class="mt-1 text-[10px] text-muted-foreground"
                                    >
                                        Dựa trên tồn và mức sử dụng của
                                        {{
                                            activeBranchName ||
                                            'chi nhánh hiện tại'
                                        }}.
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="
                                    (branchReplenishmentSuggestions ?? [])
                                        .length > 0
                                "
                                class="rounded-2xl border border-violet-500/20 bg-violet-500/5 p-4"
                            >
                                <div
                                    class="mb-3 flex items-center justify-between gap-3"
                                >
                                    <div>
                                        <p
                                            class="flex items-center gap-2 text-xs font-bold text-violet-700 dark:text-violet-300"
                                        >
                                            <Sparkles class="size-4" /> Đề xuất
                                            AI cho
                                            {{
                                                activeBranchName ||
                                                'chi nhánh hiện tại'
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-[10px] text-muted-foreground"
                                        >
                                            Ưu tiên nguyên liệu đang dưới định
                                            mức hoặc có tốc độ sử dụng cao.
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full bg-violet-500/10 px-2 py-1 text-[10px] font-semibold text-violet-600"
                                        >{{
                                            (
                                                branchReplenishmentSuggestions ??
                                                []
                                            ).length
                                        }}
                                        gợi ý</span
                                    >
                                </div>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <button
                                        v-for="suggestion in (
                                            branchReplenishmentSuggestions ?? []
                                        ).slice(0, 6)"
                                        :key="suggestion.ingredient_id"
                                        type="button"
                                        :disabled="
                                            (getCentralIngredient(
                                                suggestion.ingredient_id,
                                            )?.stock ?? 0) <= 0
                                        "
                                        :class="[
                                            'flex items-center justify-between gap-3 rounded-xl border p-3 text-left transition',
                                            (getCentralIngredient(
                                                suggestion.ingredient_id,
                                            )?.stock ?? 0) <= 0
                                                ? 'cursor-not-allowed border-slate-200 bg-slate-100/60 opacity-50 grayscale dark:border-slate-800 dark:bg-slate-900/30'
                                                : 'border-border bg-background/60 hover:border-violet-400 hover:bg-violet-500/10',
                                        ]"
                                        @click="
                                            addSuggestedIngredient(suggestion)
                                        "
                                    >
                                        <span class="min-w-0">
                                            <span
                                                class="block truncate text-xs font-bold text-foreground"
                                                >{{ suggestion.name }}</span
                                            >
                                            <span
                                                class="mt-1 block text-[10px] text-muted-foreground"
                                                >Tồn chi nhánh:
                                                {{ suggestion.current_stock }}
                                                {{ suggestion.unit_symbol }} ·
                                                Nên nhập
                                                {{
                                                    suggestion.suggested_quantity
                                                }}
                                                {{
                                                    suggestion.unit_symbol
                                                }}</span
                                            >
                                        </span>
                                        <span
                                            v-if="
                                                (getCentralIngredient(
                                                    suggestion.ingredient_id,
                                                )?.stock ?? 0) <= 0
                                            "
                                            class="shrink-0 text-[10px] font-bold text-rose-500"
                                        >
                                            Hết
                                        </span>
                                        <Plus
                                            v-else
                                            class="size-4 shrink-0 text-violet-500"
                                        />
                                    </button>
                                </div>
                            </div>

                            <div class="relative">
                                <Search
                                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                                />
                                <Input
                                    v-model="centralIngredientSearch"
                                    placeholder="Tìm nguyên liệu trong Tổng kho..."
                                    class="h-9 pl-9 text-xs"
                                />
                            </div>

                            <div
                                v-if="!canCreateSupplyRequests"
                                class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-200"
                            >
                                Tài khoản hiện tại chưa có quyền lập yêu cầu cấp
                                hàng từ Tổng kho.
                            </div>

                            <div
                                v-else-if="!centralBranch"
                                class="rounded-xl border border-dashed border-rose-300 bg-rose-50 p-6 text-center text-xs text-rose-700 dark:border-rose-900 dark:bg-rose-950/20 dark:text-rose-300"
                            >
                                Kho Tổng độc lập chưa sẵn sàng nhận yêu cầu. Vui
                                lòng liên hệ quản trị hệ thống.
                            </div>

                            <div
                                v-else-if="
                                    (centralIngredients ?? []).length === 0
                                "
                                class="rounded-xl border border-dashed border-border bg-muted/20 p-8 text-center text-xs text-muted-foreground"
                            >
                                Tổng kho hiện chưa có nguyên liệu khả dụng để
                                giao.
                            </div>

                            <div v-else class="space-y-3">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="category in centralIngredientCategories"
                                        :key="category"
                                        type="button"
                                        class="rounded-full border px-3 py-1.5 text-[11px] font-semibold transition"
                                        :class="
                                            centralIngredientCategory ===
                                            category
                                                ? 'border-indigo-500 bg-indigo-500 text-white'
                                                : 'border-border bg-background text-muted-foreground hover:border-indigo-400 hover:text-foreground'
                                        "
                                        @click="
                                            centralIngredientCategory = category
                                        "
                                    >
                                        {{
                                            category === 'all'
                                                ? 'Tất cả'
                                                : category
                                        }}
                                    </button>
                                </div>
                                <div
                                    class="grid max-h-[420px] gap-3 overflow-y-auto pr-1 sm:grid-cols-2 lg:grid-cols-3"
                                >
                                    <div
                                        v-for="ingredient in filteredCentralIngredients"
                                        :key="ingredient.id"
                                        :class="[
                                            'group relative rounded-2xl border p-3 text-left transition',
                                            ingredient.stock <= 0
                                                ? 'border-slate-200 bg-slate-100/60 opacity-50 grayscale dark:border-slate-800 dark:bg-slate-900/30'
                                                : 'border-border bg-background/40 hover:border-emerald-500/50 hover:bg-emerald-500/5',
                                        ]"
                                    >
                                        <button
                                            type="button"
                                            class="w-full text-left"
                                            :disabled="
                                                ingredient.stock <= 0 ||
                                                isCentralIngredientSelected(
                                                    ingredient.id,
                                                )
                                            "
                                            @click="
                                                addCentralIngredient(ingredient)
                                            "
                                        >
                                            <span
                                                :class="[
                                                    'mb-3 flex h-10 w-10 items-center justify-center rounded-xl text-lg font-bold',
                                                    ingredient.stock <= 0
                                                        ? 'bg-slate-200 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                                        : 'bg-emerald-500/10 text-emerald-600',
                                                ]"
                                                >{{
                                                    ingredient.name
                                                        .charAt(0)
                                                        .toUpperCase()
                                                }}</span
                                            >
                                            <span
                                                class="block truncate text-sm font-bold text-foreground"
                                                >{{ ingredient.name }}</span
                                            >
                                            <span
                                                class="mt-1 block text-[10px] text-muted-foreground"
                                                >{{
                                                    ingredient.category_name ||
                                                    'Khác'
                                                }}
                                                ·
                                                {{
                                                    ingredient.sku ||
                                                    'Chưa có mã'
                                                }}</span
                                            >
                                            <span
                                                :class="[
                                                    'mt-2 block text-xs font-semibold',
                                                    ingredient.stock <= 0
                                                        ? 'font-bold text-rose-600 dark:text-rose-400'
                                                        : 'text-emerald-600 dark:text-emerald-400',
                                                ]"
                                                >Tồn Kho Tổng:
                                                {{
                                                    ingredient.stock <= 0
                                                        ? '0 (Hết hàng)'
                                                        : `${ingredient.stock} ${ingredient.unit_symbol}`
                                                }}</span
                                            >
                                        </button>
                                        <Button
                                            v-if="ingredient.stock <= 0"
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            disabled
                                            class="pointer-events-none absolute top-3 right-3 h-7 border-rose-200 bg-rose-50 px-2 text-[10px] font-bold text-rose-600 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-400"
                                        >
                                            Hết
                                        </Button>
                                        <Button
                                            v-else-if="
                                                isCentralIngredientSelected(
                                                    ingredient.id,
                                                )
                                            "
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="pointer-events-none absolute top-3 right-3 h-7 border-emerald-500/30 bg-emerald-500/10 px-2 text-[10px] text-emerald-600"
                                        >
                                            Đã chọn
                                        </Button>
                                    </div>
                                    <p
                                        v-if="
                                            filteredCentralIngredients.length ===
                                            0
                                        "
                                        class="py-8 text-center text-xs text-muted-foreground"
                                    >
                                        Không tìm thấy nguyên liệu phù hợp.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="h-fit lg:col-span-2">
                        <CardHeader class="border-b border-border pb-4">
                            <CardTitle
                                class="flex items-center gap-2 text-base font-bold"
                            >
                                <Send class="size-4 text-indigo-500" />
                                Danh sách gửi Kho Tổng
                            </CardTitle>
                            <CardDescription class="text-xs">
                                {{ centralRequestForm.items.length }} nguyên
                                liệu được chọn
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4 pt-5">
                            <div
                                v-if="centralRequestForm.items.length === 0"
                                class="rounded-xl border border-dashed border-border bg-muted/20 p-8 text-center text-xs text-muted-foreground"
                            >
                                Chọn nguyên liệu ở bên trái để thêm vào danh
                                sách giao hàng.
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    class="max-h-[360px] space-y-2 overflow-y-auto pr-1"
                                >
                                    <div
                                        v-for="line in centralRequestForm.items"
                                        :key="line.ingredient_id"
                                        class="rounded-xl border border-border bg-background/50 p-3"
                                    >
                                        <div
                                            class="flex items-start justify-between gap-2"
                                        >
                                            <div class="min-w-0">
                                                <p
                                                    class="truncate text-xs font-bold text-foreground"
                                                >
                                                    {{
                                                        getCentralIngredient(
                                                            line.ingredient_id,
                                                        )?.name
                                                    }}
                                                </p>
                                                <p
                                                    class="mt-0.5 text-[10px] text-muted-foreground"
                                                >
                                                    Tồn Tổng:
                                                    {{
                                                        getCentralIngredient(
                                                            line.ingredient_id,
                                                        )?.stock
                                                    }}
                                                    {{
                                                        getCentralIngredient(
                                                            line.ingredient_id,
                                                        )?.unit_symbol
                                                    }}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                class="rounded-md p-1 text-muted-foreground hover:bg-rose-500/10 hover:text-rose-500"
                                                title="Bỏ nguyên liệu"
                                                @click="
                                                    removeCentralIngredient(
                                                        line.ingredient_id,
                                                    )
                                                "
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                        <div
                                            class="mt-2 flex items-center gap-2"
                                        >
                                            <Minus
                                                class="size-3.5 text-muted-foreground"
                                            />
                                            <Input
                                                v-model.number="line.quantity"
                                                type="number"
                                                min="0.001"
                                                step="0.001"
                                                :max="
                                                    getCentralIngredient(
                                                        line.ingredient_id,
                                                    )?.stock
                                                "
                                                class="h-8 text-xs"
                                            />
                                            <span
                                                class="shrink-0 text-[11px] text-muted-foreground"
                                            >
                                                {{
                                                    getCentralIngredient(
                                                        line.ingredient_id,
                                                    )?.unit_symbol
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center justify-between gap-2 border-t border-border pt-4"
                                >
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="text-xs text-muted-foreground hover:text-rose-500"
                                        @click="clearCentralRequest"
                                    >
                                        Xóa tất cả
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        class="bg-indigo-600 text-xs font-bold text-white shadow-sm hover:bg-indigo-700"
                                        @click="confirmCentralSelection"
                                    >
                                        <Send class="mr-1.5 size-3.5" />
                                        Xác nhận gửi yêu cầu ({{
                                            centralRequestForm.items.length
                                        }})
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </template>

            <template v-else>
                <Card class="animate-in duration-200 fade-in">
                    <CardHeader class="border-b border-border pb-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-bold"
                                >
                                    <Send class="size-5 text-indigo-500" />
                                    Xác nhận thông tin gửi Kho Tổng
                                </CardTitle>
                                <CardDescription class="mt-1 text-xs">
                                    Kiểm tra số lượng trước khi gửi yêu cầu cấp
                                    hàng cho
                                    {{
                                        activeBranchName ||
                                        'chi nhánh đang chọn'
                                    }}.
                                </CardDescription>
                            </div>
                            <span
                                class="rounded-full bg-indigo-500/10 px-3 py-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400"
                            >
                                Bước 2/2
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent class="grid gap-6 pt-5 lg:grid-cols-3">
                        <div class="space-y-3 lg:col-span-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p
                                        class="text-sm font-bold text-foreground"
                                    >
                                        Nguyên liệu đã chọn
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Có thể điều chỉnh số lượng theo nhu cầu
                                        thực tế.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="text-xs"
                                    @click="returnToCentralSelection"
                                >
                                    Chọn thêm
                                </Button>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div
                                    v-for="line in centralRequestForm.items"
                                    :key="line.ingredient_id"
                                    class="rounded-xl border border-border bg-background/50 p-3"
                                >
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-xs font-bold text-foreground"
                                            >
                                                {{
                                                    getCentralIngredient(
                                                        line.ingredient_id,
                                                    )?.name
                                                }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-[10px] text-muted-foreground"
                                            >
                                                Tồn Kho Tổng:
                                                {{
                                                    getCentralIngredient(
                                                        line.ingredient_id,
                                                    )?.stock
                                                }}
                                                {{
                                                    getCentralIngredient(
                                                        line.ingredient_id,
                                                    )?.unit_symbol
                                                }}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="rounded-md p-1 text-muted-foreground hover:bg-rose-500/10 hover:text-rose-500"
                                            title="Bỏ nguyên liệu"
                                            @click="
                                                removeCentralIngredient(
                                                    line.ingredient_id,
                                                )
                                            "
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                    <div class="mt-2 flex items-center gap-2">
                                        <Label class="shrink-0 text-[10px]"
                                            >Số lượng</Label
                                        >
                                        <Input
                                            v-model.number="line.quantity"
                                            type="number"
                                            min="0.001"
                                            step="0.001"
                                            :max="
                                                getCentralIngredient(
                                                    line.ingredient_id,
                                                )?.stock
                                            "
                                            class="h-8 text-xs"
                                        />
                                        <span
                                            class="shrink-0 text-[11px] text-muted-foreground"
                                            >{{
                                                getCentralIngredient(
                                                    line.ingredient_id,
                                                )?.unit_symbol
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="space-y-4 rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-4"
                        >
                            <div>
                                <p class="text-sm font-bold text-foreground">
                                    Thông tin giao hàng
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Kho Tổng độc lập sẽ tiếp nhận và giao về chi
                                    nhánh.
                                </p>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs">Ngày dự kiến nhận</Label>
                                <Input
                                    v-model="
                                        centralRequestForm.requested_delivery_date
                                    "
                                    type="date"
                                    class="h-9 text-xs"
                                />
                            </div>
                            <div
                                class="rounded-xl border border-indigo-500/20 bg-background/50 p-3 text-right"
                            >
                                <p class="text-[10px] text-muted-foreground">
                                    Giá trị dự kiến
                                </p>
                                <p
                                    class="mt-1 text-lg font-bold text-indigo-600 dark:text-indigo-400"
                                >
                                    {{ vnd(centralRequestTotal) }}
                                </p>
                            </div>
                            <textarea
                                v-model="centralRequestForm.notes"
                                rows="4"
                                placeholder="Ghi chú cho Kho Tổng: thời gian cần hàng, lý do, yêu cầu đóng gói..."
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-xs text-foreground outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                            ></textarea>
                            <div
                                class="flex items-center justify-between gap-2 border-t border-border pt-4"
                            >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-xs"
                                    @click="returnToCentralSelection"
                                    >Quay lại</Button
                                >
                                <Button
                                    type="button"
                                    size="sm"
                                    class="bg-emerald-600 text-xs text-white hover:bg-emerald-700"
                                    :disabled="
                                        isSubmittingCentralRequest ||
                                        !centralRequestForm.items.length ||
                                        !canCreateSupplyRequests
                                    "
                                    @click="submitCentralRequest"
                                >
                                    <Send class="mr-1.5 size-3.5" />
                                    {{
                                        isSubmittingCentralRequest
                                            ? 'Đang gửi...'
                                            : 'Gửi Kho Tổng'
                                    }}
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </template>

        <!-- ══ TAB: KIỂM KÊ & ĐỐI SOÁT KHO ══════════════════════════════════════ -->
        <template v-if="activeTab === 'reconcile'">
            <div class="space-y-6">
                <div
                    v-if="!activeBranchId"
                    class="rounded-xl border border-indigo-200 bg-indigo-50/70 px-4 py-3 text-xs text-indigo-800 dark:border-indigo-900 dark:bg-indigo-950/30 dark:text-indigo-200"
                >
                    Đang xem số liệu tồn kho đã cộng dồn của toàn bộ chi nhánh.
                    Vui lòng chọn một chi nhánh cụ thể để nhập số đếm và cân
                    bằng tồn kho.
                </div>

                <!-- Top Stats Cards -->
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <Card class="shadow-sm">
                        <CardContent class="p-4">
                            <p
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                Tổng mặt hàng kiểm kê
                            </p>
                            <p class="mt-1 text-2xl font-black text-foreground">
                                {{ ingredients.length }}
                                <span
                                    class="text-xs font-normal text-muted-foreground"
                                    >nguyên liệu</span
                                >
                            </p>
                        </CardContent>
                    </Card>

                    <Card class="shadow-sm">
                        <CardContent class="p-4">
                            <p
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                Khớp tuyệt đối (0 chênh lệch)
                            </p>
                            <p
                                class="mt-1 text-2xl font-black text-emerald-600 dark:text-emerald-400"
                            >
                                {{ reconcileStats.matched }}
                                <span
                                    class="text-xs font-normal text-muted-foreground"
                                    >nguyên liệu</span
                                >
                            </p>
                        </CardContent>
                    </Card>

                    <Card class="shadow-sm">
                        <CardContent class="p-4">
                            <p
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                Hàng chênh lệch thiếu (Lệch -)
                            </p>
                            <p
                                class="mt-1 text-2xl font-black text-amber-600 dark:text-amber-400"
                            >
                                {{ reconcileStats.deficitCount }}
                                <span
                                    class="text-xs font-normal text-muted-foreground"
                                    >nguyên liệu</span
                                >
                            </p>
                        </CardContent>
                    </Card>

                    <Card class="shadow-sm">
                        <CardContent class="p-4">
                            <p
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                Tổng giá trị tổn thất thiếu kho
                            </p>
                            <p
                                class="mt-1 text-2xl font-black text-rose-600 dark:text-rose-400"
                            >
                                {{ vnd(reconcileStats.totalDeficitCost) }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Audit Table Card -->
                <Card class="shadow-sm">
                    <CardHeader class="border-b border-border pb-3">
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-extrabold"
                                >
                                    <ClipboardCheck
                                        class="size-5 text-emerald-600"
                                    />
                                    Bảng Đối Soát Tồn Kho Lý Thuyết vs Tồn Thực
                                    Tế
                                </CardTitle>
                                <CardDescription class="text-xs">
                                    Nhập số lượng đếm được thực tế tại kho. Hệ
                                    thống tự động đối chiếu với số lượng tồn
                                    tính toán từ hóa đơn bán hàng POS.
                                </CardDescription>
                            </div>

                            <div class="relative w-full sm:w-64">
                                <Search
                                    class="absolute top-2.5 left-2.5 size-4 text-slate-400"
                                />
                                <input
                                    v-model="reconcileSearch"
                                    type="text"
                                    placeholder="Lọc nguyên liệu đối soát..."
                                    class="w-full rounded-xl border border-border bg-card py-1.5 pr-3 pl-8 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead
                                    class="border-b border-border bg-muted/40 text-[10px] font-bold text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="p-3">Nguyên liệu</th>
                                        <th class="p-3 text-right">
                                            Tồn Lý Thuyết (Hệ thống)
                                        </th>
                                        <th class="p-3 text-center">
                                            Tồn Thực Tế (Đếm tại kho)
                                        </th>
                                        <th class="p-3 text-right">
                                            Chênh lệch (+/-)
                                        </th>
                                        <th class="p-3 text-right">
                                            Thành tiền chênh lệch
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="ing in filteredReconcileIngredients"
                                        :key="ing.id"
                                        class="hover:bg-muted/20"
                                    >
                                        <td class="p-3">
                                            <p
                                                class="font-bold text-foreground"
                                            >
                                                {{ ing.name }}
                                            </p>
                                            <p
                                                class="text-[10px] text-muted-foreground"
                                            >
                                                {{ ing.sku ?? 'SKU-NONE' }} ·
                                                Giá vốn:
                                                {{ vnd(ing.average_cost) }}/{{
                                                    ing.unit?.symbol ?? 'đv'
                                                }}
                                            </p>
                                        </td>
                                        <td
                                            class="p-3 text-right font-mono font-bold text-foreground"
                                        >
                                            {{ ing.stock ?? 0 }}
                                            {{ ing.unit?.symbol ?? 'đv' }}
                                        </td>
                                        <td class="p-3 text-center">
                                            <div
                                                class="inline-flex items-center gap-1.5"
                                            >
                                                <Input
                                                    type="number"
                                                    step="0.001"
                                                    min="0"
                                                    v-model="
                                                        physicalStockMap[ing.id]
                                                    "
                                                    :disabled="!activeBranchId"
                                                    class="h-8 w-28 text-center font-mono font-bold"
                                                />
                                                <span
                                                    class="text-[11px] font-medium text-muted-foreground"
                                                    >{{
                                                        ing.unit?.symbol ?? 'đv'
                                                    }}</span
                                                >
                                            </div>
                                        </td>
                                        <td
                                            class="p-3 text-right font-mono font-bold"
                                        >
                                            <span
                                                :class="[
                                                    'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-bold',
                                                    getDiff(ing) === 0
                                                        ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                                        : getDiff(ing) < 0
                                                          ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'
                                                          : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
                                                ]"
                                            >
                                                {{ getDiff(ing) > 0 ? '+' : ''
                                                }}{{ getDiff(ing) }}
                                                {{ ing.unit?.symbol ?? 'đv' }}
                                            </span>
                                        </td>
                                        <td
                                            class="p-3 text-right font-mono font-bold"
                                        >
                                            <span
                                                :class="
                                                    getDiff(ing) < 0
                                                        ? 'text-rose-600 dark:text-rose-400'
                                                        : getDiff(ing) > 0
                                                          ? 'text-emerald-600 dark:text-emerald-400'
                                                          : 'text-muted-foreground'
                                                "
                                            >
                                                {{
                                                    vnd(
                                                        getDiff(ing) *
                                                            ing.average_cost,
                                                    )
                                                }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Footer Audit Action Bar -->
                        <div
                            class="flex flex-col gap-3 border-t border-border bg-muted/20 p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div
                                class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center"
                            >
                                <div class="flex items-center gap-2">
                                    <Label class="shrink-0 text-xs font-bold"
                                        >Ghi chú đối soát:</Label
                                    >
                                    <Input
                                        v-model="reconcileNotes"
                                        placeholder="Ví dụ: Kiểm kê định kỳ cuối ca..."
                                        class="h-9 text-xs sm:w-64"
                                    />
                                </div>

                                <label
                                    class="flex items-center gap-2 text-xs font-bold text-indigo-700 dark:text-indigo-300"
                                >
                                    <input
                                        v-model="reconcileOpeningBalance"
                                        type="checkbox"
                                        :disabled="!activeBranchId"
                                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    Đây là đối soát số dư đầu kỳ
                                </label>

                                <div
                                    v-if="reconcileStats.deficitCount > 0"
                                    class="flex items-center gap-2"
                                >
                                    <Label
                                        class="shrink-0 text-xs font-bold text-rose-600 dark:text-rose-400"
                                    >
                                        Quy trách nhiệm thất thoát ({{
                                            vnd(
                                                reconcileStats.totalDeficitCost,
                                            )
                                        }}):
                                    </Label>
                                    <select
                                        v-model="reconcileEmployeeId"
                                        :disabled="!activeBranchId"
                                        class="h-9 rounded-xl border border-rose-300 bg-card px-3 text-xs font-medium text-foreground focus:ring-2 focus:ring-rose-500/20 focus:outline-none dark:border-rose-800"
                                    >
                                        <option value="">
                                            Không khấu trừ lương (Hao hụt quán
                                            tự chịu)
                                        </option>
                                        <option
                                            v-for="emp in employees"
                                            :key="emp.id"
                                            :value="emp.id"
                                        >
                                            Khấu trừ lương: {{ emp.full_name
                                            }}{{
                                                emp.job_title
                                                    ? ' (' + emp.job_title + ')'
                                                    : ''
                                            }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <Button
                                @click="submitReconcile"
                                class="h-9 cursor-pointer rounded-xl bg-emerald-600 font-bold text-white shadow-sm hover:bg-emerald-700"
                                :disabled="isReconciling || !activeBranchId"
                            >
                                <ClipboardCheck class="mr-1.5 size-4" />
                                {{
                                    isReconciling
                                        ? 'Đang cân bằng kho...'
                                        : activeBranchId
                                          ? 'Xác nhận & Cân bằng tồn kho'
                                          : 'Chọn chi nhánh để cân bằng kho'
                                }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </template>

        <!-- ══ TAB: KẾ HOẠCH & DỰ BÁO NHẬP ══════════════════════════════════════ -->
        <template v-if="activeTab === 'planning'">
            <div class="animate-in space-y-6 duration-200 fade-in">
                <!-- Header Summary Banner -->
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <Card
                        class="border-emerald-200 bg-emerald-50/40 dark:border-emerald-900/50 dark:bg-emerald-950/20"
                    >
                        <CardContent
                            class="flex items-center justify-between p-4"
                        >
                            <div>
                                <p
                                    class="text-xs font-bold text-emerald-800 dark:text-emerald-300"
                                >
                                    🥬 Hàng Tươi Sống
                                </p>
                                <h3
                                    class="text-xl font-black text-emerald-700 dark:text-emerald-400"
                                >
                                    {{
                                        ingredients.filter(
                                            (i) => i.storage_type === 'fresh',
                                        ).length
                                    }}
                                    loại
                                </h3>
                                <p class="text-[10px] text-emerald-600">
                                    HSD 1-3 ngày · Tiêu thụ ưu tiên FEFO
                                </p>
                            </div>
                            <span
                                class="rounded-xl bg-emerald-100 p-2.5 text-emerald-700 dark:bg-emerald-900/40"
                                >🥬</span
                            >
                        </CardContent>
                    </Card>

                    <Card
                        class="border-amber-200 bg-amber-50/40 dark:border-amber-900/50 dark:bg-amber-950/20"
                    >
                        <CardContent
                            class="flex items-center justify-between p-4"
                        >
                            <div>
                                <p
                                    class="text-xs font-bold text-amber-800 dark:text-amber-300"
                                >
                                    🥖 Bán Trong Ngày
                                </p>
                                <h3
                                    class="text-xl font-black text-amber-700 dark:text-amber-400"
                                >
                                    {{
                                        ingredients.filter(
                                            (i) => i.storage_type === 'daily',
                                        ).length
                                    }}
                                    loại
                                </h3>
                                <p class="text-[10px] text-amber-600">
                                    Kiểm soát dùng hết cuối ca / Cuối ngày
                                </p>
                            </div>
                            <span
                                class="rounded-xl bg-amber-100 p-2.5 text-amber-700 dark:bg-amber-900/40"
                                >🥖</span
                            >
                        </CardContent>
                    </Card>

                    <Card
                        class="border-blue-200 bg-blue-50/40 dark:border-blue-900/50 dark:bg-blue-950/20"
                    >
                        <CardContent
                            class="flex items-center justify-between p-4"
                        >
                            <div>
                                <p
                                    class="text-xs font-bold text-blue-800 dark:text-blue-300"
                                >
                                    🥫 Đóng Hộp / Đóng Gói
                                </p>
                                <h3
                                    class="text-xl font-black text-blue-700 dark:text-blue-400"
                                >
                                    {{
                                        ingredients.filter(
                                            (i) =>
                                                i.storage_type ===
                                                'canned_packaged',
                                        ).length
                                    }}
                                    loại
                                </h3>
                                <p class="text-[10px] text-blue-600">
                                    Hạn trung & dài · Quản lý mở nắp
                                </p>
                            </div>
                            <span
                                class="rounded-xl bg-blue-100 p-2.5 text-blue-700 dark:bg-blue-900/40"
                                >🥫</span
                            >
                        </CardContent>
                    </Card>

                    <Card
                        class="border-slate-200 bg-slate-50/40 dark:border-slate-800 dark:bg-slate-900/20"
                    >
                        <CardContent
                            class="flex items-center justify-between p-4"
                        >
                            <div>
                                <p
                                    class="text-xs font-bold text-slate-800 dark:text-slate-300"
                                >
                                    🌾 Đồ Khô & Gia Vị
                                </p>
                                <h3
                                    class="text-xl font-black text-slate-700 dark:text-slate-400"
                                >
                                    {{
                                        ingredients.filter(
                                            (i) =>
                                                !i.storage_type ||
                                                i.storage_type === 'dry',
                                        ).length
                                    }}
                                    loại
                                </h3>
                                <p class="text-[10px] text-slate-500">
                                    Bảo quản kho khô · Mua gom định kỳ
                                </p>
                            </div>
                            <span
                                class="rounded-xl bg-slate-200 p-2.5 text-slate-700 dark:bg-slate-800"
                                >🌾</span
                            >
                        </CardContent>
                    </Card>
                </div>

                <!-- Planning Table: Master Matrix & Reorder Recommendations -->
                <Card class="shadow-sm">
                    <CardHeader
                        class="flex flex-row items-center justify-between border-b border-border pb-3"
                    >
                        <div>
                            <CardTitle
                                class="text-base font-bold text-slate-900 dark:text-slate-100"
                            >
                                📋 Kế Hoạch Đặt Hàng & Kiểm Soát Tồn Kho Chuẩn
                            </CardTitle>
                            <CardDescription class="text-xs">
                                Tính toán nhu cầu dựa trên Tồn thực tế, Tồn tối
                                thiểu (Min Stock), Điểm đặt hàng lại (Reorder
                                Level) và Hạn sử dụng.
                            </CardDescription>
                        </div>
                        <Button
                            size="sm"
                            class="rounded-xl bg-indigo-600 text-xs text-white"
                            @click="openAddIngredientModal()"
                        >
                            <Plus class="mr-1 size-3.5" /> Thêm nguyên liệu mới
                        </Button>
                    </CardHeader>
                    <CardContent class="overflow-x-auto p-0">
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b bg-slate-100 text-[10px] font-bold text-slate-500 uppercase dark:bg-slate-950"
                                >
                                    <th class="p-3">Nguyên liệu</th>
                                    <th class="p-3">Loại bảo quản</th>
                                    <th class="p-3">Vị trí kho</th>
                                    <th class="p-3 text-right">Tồn hiện tại</th>
                                    <th class="p-3 text-right">
                                        Mức tối thiểu (Min)
                                    </th>
                                    <th class="p-3 text-right">
                                        Định mức đặt (Reorder)
                                    </th>
                                    <th class="p-3 text-center">
                                        HSD Tiêu chuẩn
                                    </th>
                                    <th class="p-3 text-center">
                                        Trạng thái đặt hàng
                                    </th>
                                    <th class="p-3 text-right">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr
                                    v-for="ing in ingredients"
                                    :key="ing.id"
                                    class="transition hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                                >
                                    <td
                                        class="p-3 font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ ing.name }}
                                        <span
                                            class="block text-[10px] font-normal text-slate-400"
                                        >
                                            {{ ing.sku }} ·
                                            {{ ing.unit?.symbol }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <span
                                            :class="[
                                                'rounded border px-1.5 py-0.5 text-[10px] font-bold',
                                                getStorageBadgeClass(
                                                    ing.storage_type,
                                                ),
                                            ]"
                                        >
                                            {{ getStorageLabel(ing) }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-slate-500">
                                        {{ ing.storage_location || '—' }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-mono font-bold"
                                    >
                                        <span
                                            :class="[
                                                (ing.stock ?? 0) <
                                                (ing.min_stock_level || 5)
                                                    ? 'text-rose-600 dark:text-rose-400'
                                                    : 'text-slate-700 dark:text-slate-300',
                                            ]"
                                        >
                                            {{ (ing.stock ?? 0).toFixed(1) }}
                                            {{ ing.unit?.symbol }}
                                        </span>
                                    </td>
                                    <td
                                        class="p-3 text-right font-mono text-slate-500"
                                    >
                                        {{
                                            (ing.min_stock_level || 0).toFixed(
                                                1,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-mono text-slate-500"
                                    >
                                        {{
                                            (ing.reorder_level || 0).toFixed(1)
                                        }}
                                    </td>
                                    <td
                                        class="p-3 text-center font-mono text-slate-600 dark:text-slate-400"
                                    >
                                        {{
                                            ing.default_shelf_life_days
                                                ? ing.default_shelf_life_days +
                                                  ' ngày'
                                                : 'Chưa cài'
                                        }}
                                    </td>
                                    <td class="p-3 text-center">
                                        <span
                                            v-if="
                                                (ing.stock ?? 0) <
                                                (ing.min_stock_level || 5)
                                            "
                                            class="rounded-full border border-rose-200 bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-950 dark:text-rose-300"
                                        >
                                            🔴 Cần nhập ngay
                                        </span>
                                        <span
                                            v-else-if="
                                                (ing.stock ?? 0) <
                                                (ing.reorder_level || 20)
                                            "
                                            class="rounded-full border border-amber-200 bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                                        >
                                            🟡 Gom đơn đặt
                                        </span>
                                        <span
                                            v-else
                                            class="rounded-full border border-emerald-200 bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                        >
                                            🟢 Đủ cơ số
                                        </span>
                                    </td>
                                    <td class="space-x-1 p-3 text-right">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="h-7 px-2 text-[10px] font-semibold"
                                            @click="
                                                openEditIngredientModal(ing)
                                            "
                                        >
                                            <Edit class="mr-1 size-3" /> Cấu
                                            hình
                                        </Button>
                                        <Button
                                            size="sm"
                                            class="h-7 bg-indigo-600 px-2 text-[10px] font-semibold text-white"
                                            @click="
                                                activeTab = 'purchase';
                                                purchaseForm.ingredient_id =
                                                    String(ing.id);
                                                purchaseForm.quantity = String(
                                                    Math.max(
                                                        1,
                                                        (ing.reorder_level ||
                                                            10) -
                                                            (ing.stock ?? 0),
                                                    ),
                                                );
                                            "
                                        >
                                            <ShoppingCart class="mr-1 size-3" />
                                            Nhập hàng
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </template>
    </div>

    <!-- ══ Modal: Thêm nguyên liệu ══════════════════════════════════════════════ -->
    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <Teleport to="body">
            <div
                v-if="showAddIngredient"
                class="fixed inset-0 z-50 overflow-y-auto bg-black/60 p-4 backdrop-blur-sm"
                @click.self="showAddIngredient = false"
            >
                <div
                    class="flex min-h-full items-start justify-center py-6 sm:items-center sm:py-10"
                    @click.self="showAddIngredient = false"
                >
                    <Card class="w-full max-w-md">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle
                                    class="flex items-center gap-2 text-base"
                                >
                                    <Beaker
                                        class="size-5 text-indigo-500"
                                    />Thêm nguyên liệu thô mới
                                </CardTitle>
                                <button
                                    @click="showAddIngredient = false"
                                    class="cursor-pointer rounded-lg p-1.5 text-muted-foreground hover:bg-muted"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                            <CardDescription class="text-xs"
                                >Nguyên liệu này sẽ xuất hiện trong danh sách
                                khi thiết lập công thức định
                                lượng.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <form
                                @submit.prevent="submitIngredient"
                                class="space-y-4"
                            >
                                <div class="space-y-1.5">
                                    <Label class="text-xs"
                                        >Tên nguyên liệu
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <Input
                                        v-model="ingredientForm.name"
                                        placeholder="Ví dụ: Thịt bò, Bánh phở, Nước mắm..."
                                        required
                                    />
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <Label class="text-xs"
                                            >Đơn vị tính
                                            <span class="text-rose-500"
                                                >*</span
                                            ></Label
                                        >
                                        <select
                                            v-model="ingredientForm.unit_id"
                                            required
                                            class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                        >
                                            <option value="" disabled>
                                                Chọn đơn vị
                                            </option>
                                            <option
                                                v-for="u in units"
                                                :key="u.id"
                                                :value="u.id"
                                            >
                                                {{ u.name }} ({{ u.symbol }})
                                            </option>
                                        </select>
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label class="text-xs">Danh mục</Label>
                                        <Input
                                            v-model="ingredientForm.category"
                                            placeholder="Thịt, Rau củ..."
                                        />
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 pt-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="showAddIngredient = false"
                                        >Hủy</Button
                                    >
                                    <Button
                                        type="submit"
                                        size="sm"
                                        class="bg-indigo-600 text-white"
                                        :disabled="ingredientForm.processing"
                                    >
                                        {{
                                            ingredientForm.processing
                                                ? 'Đang lưu...'
                                                : 'Thêm nguyên liệu'
                                        }}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </Teleport>
    </Transition>

    <!-- ══ Modal: Thiết lập công thức ═══════════════════════════════════════════ -->
    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <Teleport to="body">
            <div
                v-if="showAddRecipe && activeProduct"
                class="fixed inset-0 z-50 overflow-y-auto bg-black/60 p-4 backdrop-blur-sm"
                @click.self="showAddRecipe = false"
            >
                <div
                    class="flex min-h-full items-start justify-center py-6 sm:items-center sm:py-10"
                    @click.self="showAddRecipe = false"
                >
                    <Card
                        class="flex max-h-[calc(100vh-4rem)] w-full max-w-2xl flex-col overflow-hidden"
                    >
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle
                                    class="flex items-center gap-2 text-base"
                                >
                                    <Scale class="size-5 text-indigo-500" />Định
                                    lượng công thức:
                                    {{ activeProduct.name }}
                                </CardTitle>
                                <button
                                    @click="showAddRecipe = false"
                                    class="cursor-pointer rounded-lg p-1.5 text-muted-foreground hover:bg-muted"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                            <CardDescription class="text-xs"
                                >Thiết lập đầy đủ các nguyên liệu và khối
                                lượng/thể tích cấu thành nên món ăn
                                này.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <form
                                @submit.prevent="submitRecipe"
                                class="space-y-4"
                            >
                                <div class="space-y-3">
                                    <!-- Table Headers -->
                                    <div
                                        class="flex items-center justify-between border-b border-border pb-1.5 text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        <span class="w-[35%]"
                                            >Nguyên liệu
                                            <span class="text-rose-500"
                                                >*</span
                                            ></span
                                        >
                                        <span class="w-[20%]"
                                            >Định lượng
                                            <span class="text-rose-500"
                                                >*</span
                                            ></span
                                        >
                                        <span class="w-[20%]">Hao hụt (%)</span>
                                        <span class="w-[10%] text-center"
                                            >Xóa</span
                                        >
                                    </div>

                                    <!-- Recipe rows -->
                                    <div
                                        v-if="recipeForm.items.length"
                                        class="max-h-[300px] space-y-2.5 overflow-y-auto pr-1"
                                    >
                                        <div
                                            v-for="(
                                                item, index
                                            ) in recipeForm.items"
                                            :key="index"
                                            class="flex items-center gap-3"
                                        >
                                            <!-- Ingredient Select -->
                                            <div class="w-[35%]">
                                                <select
                                                    v-model="item.ingredient_id"
                                                    @change="
                                                        syncRecipeUnit(item)
                                                    "
                                                    required
                                                    class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                                >
                                                    <option value="" disabled>
                                                        Chọn nguyên liệu
                                                    </option>
                                                    <option
                                                        v-for="ing in ingredients"
                                                        :key="ing.id"
                                                        :value="String(ing.id)"
                                                        :disabled="
                                                            recipeForm.items.some(
                                                                (
                                                                    x: any,
                                                                    idx: number,
                                                                ) =>
                                                                    x.ingredient_id ===
                                                                        String(
                                                                            ing.id,
                                                                        ) &&
                                                                    idx !==
                                                                        index,
                                                            )
                                                        "
                                                    >
                                                        {{ ing.name }} ({{
                                                            ing.unit?.symbol ??
                                                            'đơn vị'
                                                        }})
                                                    </option>
                                                </select>
                                            </div>

                                            <!-- Recipe unit -->
                                            <div class="w-[20%]">
                                                <select
                                                    v-model="item.unit_id"
                                                    @change="
                                                        normalizeRecipeQuantity(
                                                            item,
                                                        )
                                                    "
                                                    required
                                                    class="w-full rounded-xl border border-border bg-background px-2 py-2 text-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                                >
                                                    <option
                                                        v-for="unit in recipeUnitsFor(
                                                            item.ingredient_id,
                                                        )"
                                                        :key="unit.id"
                                                        :value="String(unit.id)"
                                                    >
                                                        {{ unit.symbol }}
                                                    </option>
                                                </select>
                                            </div>

                                            <!-- Quantity Input -->
                                            <div class="relative w-[20%]">
                                                <Input
                                                    type="number"
                                                    :step="
                                                        recipeQuantityStep(
                                                            item.unit_id,
                                                        )
                                                    "
                                                    :min="
                                                        recipeQuantityIsInteger(
                                                            item.unit_id,
                                                        )
                                                            ? 1
                                                            : 0.001
                                                    "
                                                    v-model="item.quantity"
                                                    @input="
                                                        normalizeRecipeQuantity(
                                                            item,
                                                        )
                                                    "
                                                    placeholder="150"
                                                    required
                                                    class="h-9 pr-10 text-xs"
                                                />
                                                <span
                                                    class="absolute top-1/2 right-2.5 -translate-y-1/2 text-[10px] font-bold text-slate-400 select-none"
                                                >
                                                    {{
                                                        units.find(
                                                            (unit) =>
                                                                String(
                                                                    unit.id,
                                                                ) ===
                                                                item.unit_id,
                                                        )?.symbol ?? 'đv'
                                                    }}
                                                </span>
                                            </div>

                                            <!-- Waste Rate Input -->
                                            <div class="w-[15%]">
                                                <Input
                                                    type="number"
                                                    v-model="item.waste_rate"
                                                    placeholder="0"
                                                    class="h-9 text-xs"
                                                />
                                            </div>

                                            <!-- Delete Row Button -->
                                            <div
                                                class="flex w-[10%] justify-center"
                                            >
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-8 w-8 p-0 text-rose-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30"
                                                    @click="
                                                        removeRecipeRow(
                                                            Number(index),
                                                        )
                                                    "
                                                >
                                                    <Trash2 class="size-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Empty State inside form -->
                                    <div
                                        v-else
                                        class="rounded-xl border border-dashed border-border bg-muted/10 p-6 text-center text-xs text-muted-foreground"
                                    >
                                        Chưa thêm nguyên liệu nào. Nhấn nút
                                        "Thêm nguyên liệu" bên dưới để bắt đầu
                                        thiết lập.
                                    </div>

                                    <!-- Add Row Button -->
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="addRecipeRow"
                                        class="text-indigo-650 dark:border-indigo-850 mt-2 flex items-center gap-1.5 border-indigo-200 text-xs hover:bg-indigo-50 dark:text-indigo-400"
                                    >
                                        <Plus class="size-3.5" /> Thêm nguyên
                                        liệu
                                    </Button>
                                </div>

                                <!-- Footer Info Banner -->
                                <div
                                    class="flex items-start gap-2 rounded-xl border border-indigo-500/20 bg-indigo-500/5 p-3 text-[11px] text-indigo-700 dark:text-indigo-400"
                                >
                                    <Info class="mt-0.5 size-3.5 shrink-0" />
                                    Hệ thống sẽ dùng toàn bộ các định lượng này
                                    để tự động tính tổng COGS (giá vốn) cho món
                                    ăn khi hoàn thành đơn.
                                </div>

                                <!-- Modal Footer Action Buttons -->
                                <div class="flex justify-end gap-2 pt-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="showAddRecipe = false"
                                    >
                                        Hủy
                                    </Button>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        class="bg-indigo-600 text-white"
                                        :disabled="recipeForm.processing"
                                    >
                                        {{
                                            recipeForm.processing
                                                ? 'Đang lưu...'
                                                : 'Lưu công thức'
                                        }}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </Teleport>
    </Transition>

    <!-- Ingredient Creation & Edit Modal -->
    <IngredientModal
        :is-open="showIngredientModal"
        :ingredient="editingIngredient"
        :units="units"
        :suppliers="suppliers"
        @close="showIngredientModal = false"
    />
</template>

<style scoped>
.ai-forecast-scroll {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.ai-forecast-scroll::-webkit-scrollbar {
    display: none;
}
</style>
