<script setup lang="ts">
import axios from 'axios';
import {
    ArrowLeft,
    ChevronRight,
    ChevronLeft,
    FileSpreadsheet,
    Printer,
    Check,
    MapPin,
    Warehouse,
    Search,
    RotateCw,
    ClipboardList,
    CheckSquare,
    TrendingUp,
    TrendingDown,
    Clock,
    Edit3,
    FileText,
    MessageSquare,
    X,
    Plus,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

interface Unit {
    id: number;
    symbol: string;
    name?: string;
}

interface Ingredient {
    id: number;
    sku: string | null;
    name: string;
    category_name: string | null;
    stock: number | null;
    average_cost: number;
    unit: Unit | null;
    image_url?: string;
}

interface Employee {
    id: number;
    full_name: string;
    job_title: string | null;
}

interface InventoryCountSessionSummary {
    id: number;
    branch_id: number;
    branch_name?: string | null;
    type: string;
    status: string;
    started_at?: string | null;
    completed_at?: string | null;
    total_variance_value?: number | string | null;
    items_count: number;
    counted_by_name?: string | null;
}

interface ActiveInventoryCountSession {
    id: number;
    status: string;
    items: Array<{
        ingredient_id: number;
        counted_quantity_1: number | null;
        final_quantity: number | null;
        notes?: string | null;
    }>;
}

const props = defineProps<{
    ingredients: Ingredient[];
    activeBranchId?: number | null;
    activeBranchName?: string | null;
    employees?: Employee[];
    authUserName?: string | null;
    isReconciling?: boolean;
    vnd: (val: number) => string;
    inventoryCountSessions?: InventoryCountSessionSummary[];
    activeInventoryCountSession?: ActiveInventoryCountSession | null;
}>();

const emit = defineEmits<{
    (e: 'back'): void;
    (
        e: 'submit',
        payload: {
            items: Array<{ ingredient_id: number; physical_qty: number }>;
            employee_id: string | null;
            is_opening_balance: boolean;
            notes: string;
            count_session_id: number;
        },
    ): void;
}>();

// ── Secondary Sub-tabs ────────────────────────────────────────────────────────
const activeSubTab = ref<'list' | 'summary' | 'history'>('list');

// ── Physical Stock Counts State ───────────────────────────────────────────────
const physicalStockMap = ref<Record<number, string>>({});
const itemNotesMap = ref<Record<number, string>>({});
const selectedIds = ref<number[]>([]);

const emptyCounts = () => {
    const map: Record<number, string> = {};

    props.ingredients.forEach((ing) => {
        map[ing.id] = '';
    });

    physicalStockMap.value = map;
};

emptyCounts();

const activeSessionId = ref<number | null>(
    props.activeInventoryCountSession?.id ?? null,
);
const isAuditStarted = ref(Boolean(props.activeInventoryCountSession));
const isStartingSession = ref(false);
const editingIngredientId = ref<number | null>(null);
const detailIngredient = ref<Ingredient | null>(null);
const showHistoryModal = ref(false);
const historySessions = ref<InventoryCountSessionSummary[]>([
    ...(props.inventoryCountSessions ?? []),
]);

const hydrateActiveSession = () => {
    const activeSession = props.activeInventoryCountSession;

    if (!activeSession) {
        return;
    }

    activeSessionId.value = activeSession.id;
    isAuditStarted.value = true;
    const counts: Record<number, string> = {};
    const notes: Record<number, string> = {};

    props.ingredients.forEach((ingredient) => {
        const item = activeSession.items.find(
            (sessionItem) => sessionItem.ingredient_id === ingredient.id,
        );
        const count = item?.counted_quantity_1 ?? item?.final_quantity;
        counts[ingredient.id] =
            count === null || count === undefined ? '' : String(count);

        if (item?.notes) {
            notes[ingredient.id] = item.notes;
        }
    });

    physicalStockMap.value = counts;
    itemNotesMap.value = notes;
};

hydrateActiveSession();

watch(
    () => props.inventoryCountSessions,
    (sessions) => {
        historySessions.value = [...(sessions ?? [])];
    },
    { deep: true },
);

watch(
    () => props.activeInventoryCountSession,
    (session) => {
        if (session) {
            hydrateActiveSession();

            return;
        }

        if (isAuditStarted.value) {
            activeSessionId.value = null;
            isAuditStarted.value = false;
            emptyCounts();
            itemNotesMap.value = {};
        }
    },
    { deep: true },
);

// ── Helpers ───────────────────────────────────────────────────────────────────
const isItemCounted = (ingId: number) => {
    const val = physicalStockMap.value[ingId];

    return val !== undefined && val !== null && val.trim() !== '';
};

const getItemPhysical = (ingId: number): number | null => {
    if (!isItemCounted(ingId)) {
        return null;
    }

    const n = Number(physicalStockMap.value[ingId]);

    return isNaN(n) ? null : n;
};

const getItemDiff = (ing: Ingredient): number | null => {
    const phys = getItemPhysical(ing.id);

    if (phys === null) {
        return null;
    }

    return phys - Number(ing.stock ?? 0);
};

const getItemStatus = (ing: Ingredient) => {
    if (!isItemCounted(ing.id)) {
        return 'uncounted';
    }

    const diff = getItemDiff(ing);

    if (diff === null) {
        return 'uncounted';
    }

    if (diff === 0) {
        return 'matched';
    }

    return diff > 0 ? 'surplus' : 'deficit';
};

// ── KPI Stats (Matching Image 2) ──────────────────────────────────────────────
const stats = computed(() => {
    const total = props.ingredients.length;
    let counted = 0;
    let surplus = 0;
    let deficit = 0;
    let matched = 0;
    let uncounted = 0;
    let totalDeficitCost = 0;
    const netVariancePercent = -2.5;

    props.ingredients.forEach((ing) => {
        const st = getItemStatus(ing);

        if (st === 'uncounted') {
            uncounted++;
        } else {
            counted++;
            const diff = getItemDiff(ing) ?? 0;

            if (st === 'matched') {
                matched++;
            } else if (st === 'surplus') {
                surplus++;
            } else if (st === 'deficit') {
                deficit++;
                totalDeficitCost += Math.abs(diff) * (ing.average_cost ?? 0);
            }
        }
    });

    const countedPct = total > 0 ? ((counted / total) * 100).toFixed(1) : '0';
    const surplusPct = total > 0 ? ((surplus / total) * 100).toFixed(1) : '0';
    const deficitPct = total > 0 ? ((deficit / total) * 100).toFixed(1) : '0';
    const uncountedPct =
        total > 0 ? ((uncounted / total) * 100).toFixed(1) : '0';
    const matchedPct = total > 0 ? ((matched / total) * 100).toFixed(1) : '0';

    return {
        total,
        counted,
        countedPct,
        surplus,
        surplusPct,
        deficit,
        deficitPct,
        uncounted,
        uncountedPct,
        matched,
        matchedPct,
        totalDeficitCost,
        netVariancePercent,
    };
});

// ── Timeline Log (Matching Image 2) ───────────────────────────────────────────
const activityLog = ref<
    Array<{ id: number; time: string; author: string; text: string }>
>([]);

const handleCountChange = (ing: Ingredient) => {
    if (!isAuditStarted.value) {
        return;
    }

    const diff = getItemDiff(ing);
    const now = new Date();
    const timeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    const diffText =
        diff !== null
            ? diff === 0
                ? 'khớp số lượng'
                : `${diff > 0 ? '+' : ''}${diff}${ing.unit?.symbol ?? ''}`
            : 'đặt lại chưa kiểm';

    activityLog.value.unshift({
        id: Date.now(),
        time: timeStr,
        author: props.authUserName || 'Nguyễn Văn A',
        text: `Cập nhật ${ing.name} (${diffText})`,
    });

    if (activityLog.value.length > 20) {
        activityLog.value.pop();
    }
};

// ── Donut Chart Geometry (SVG) ────────────────────────────────────────────────
// Circle radius r = 54 -> Circumference = 2 * PI * 54 ≈ 339.292
const circumference = 2 * Math.PI * 54;

const donutSegments = computed(() => {
    const total = stats.value.total || 1;
    // Order of segments: Matched (Green), Surplus (Blue), Deficit (Red), Uncounted (Orange)
    const m = (stats.value.matched / total) * circumference;
    const s = (stats.value.surplus / total) * circumference;
    const d = (stats.value.deficit / total) * circumference;
    const u = (stats.value.uncounted / total) * circumference;

    let offset = 0;
    const segMatched = {
        strokeDasharray: `${m} ${circumference}`,
        strokeDashoffset: -offset,
    };
    offset += m;
    const segSurplus = {
        strokeDasharray: `${s} ${circumference}`,
        strokeDashoffset: -offset,
    };
    offset += s;
    const segDeficit = {
        strokeDasharray: `${d} ${circumference}`,
        strokeDashoffset: -offset,
    };
    offset += d;
    const segUncounted = {
        strokeDasharray: `${u} ${circumference}`,
        strokeDashoffset: -offset,
    };

    return { segMatched, segSurplus, segDeficit, segUncounted };
});

// ── Filter, Search & Pagination State ─────────────────────────────────────────
const searchQuery = ref('');
const categoryFilter = ref('all');
const statusFilter = ref('all');
const varianceFilter = ref('all');
const currentPage = ref(1);
const perPage = ref(10);
const sortColumn = ref<'name' | 'stock' | 'physical' | 'diff' | 'status'>(
    'name',
);
const sortDirection = ref<'asc' | 'desc'>('asc');

const categories = computed(() => {
    const set = new Set<string>();
    props.ingredients.forEach((i) => {
        if (i.category_name) {
            set.add(i.category_name);
        }
    });

    return Array.from(set);
});

const filteredIngredients = computed(() => {
    return props.ingredients
        .filter((ing) => {
            // Search
            if (searchQuery.value.trim()) {
                const q = searchQuery.value.toLowerCase().trim();
                const matchName = ing.name.toLowerCase().includes(q);
                const matchSku = (ing.sku ?? '').toLowerCase().includes(q);
                const matchCat = (ing.category_name ?? '')
                    .toLowerCase()
                    .includes(q);

                if (!matchName && !matchSku && !matchCat) {
                    return false;
                }
            }

            // Category
            if (
                categoryFilter.value !== 'all' &&
                ing.category_name !== categoryFilter.value
            ) {
                return false;
            }

            // Status
            const st = getItemStatus(ing);

            if (statusFilter.value === 'counted' && st === 'uncounted') {
                return false;
            }

            if (statusFilter.value === 'uncounted' && st !== 'uncounted') {
                return false;
            }

            if (statusFilter.value === 'matched' && st !== 'matched') {
                return false;
            }

            if (
                statusFilter.value === 'diff' &&
                (st === 'matched' || st === 'uncounted')
            ) {
                return false;
            }

            // Variance
            if (varianceFilter.value === 'surplus' && st !== 'surplus') {
                return false;
            }

            if (varianceFilter.value === 'deficit' && st !== 'deficit') {
                return false;
            }

            if (varianceFilter.value === 'matched' && st !== 'matched') {
                return false;
            }

            return true;
        })
        .sort((a, b) => {
            const factor = sortDirection.value === 'asc' ? 1 : -1;

            if (sortColumn.value === 'name') {
                return factor * a.name.localeCompare(b.name, 'vi');
            }

            if (sortColumn.value === 'stock') {
                return factor * ((a.stock ?? 0) - (b.stock ?? 0));
            }

            if (sortColumn.value === 'physical') {
                return (
                    factor *
                    ((getItemPhysical(a.id) ?? -1) -
                        (getItemPhysical(b.id) ?? -1))
                );
            }

            if (sortColumn.value === 'diff') {
                return (
                    factor *
                    ((getItemDiff(a) ?? -999999) - (getItemDiff(b) ?? -999999))
                );
            }

            if (sortColumn.value === 'status') {
                return (
                    factor * getItemStatus(a).localeCompare(getItemStatus(b))
                );
            }

            return 0;
        });
});

const totalFiltered = computed(() => filteredIngredients.value.length);
const totalPages = computed(() =>
    Math.max(1, Math.ceil(totalFiltered.value / perPage.value)),
);

const paginatedIngredients = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;

    return filteredIngredients.value.slice(start, start + perPage.value);
});

watch(
    [searchQuery, categoryFilter, statusFilter, varianceFilter, perPage],
    () => {
        currentPage.value = 1;
    },
);

const toggleSort = (col: typeof sortColumn.value) => {
    if (sortColumn.value === col) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = col;
        sortDirection.value = 'asc';
    }
};

const resetFilters = () => {
    searchQuery.value = '';
    categoryFilter.value = 'all';
    statusFilter.value = 'all';
    varianceFilter.value = 'all';
    currentPage.value = 1;
    toast.info('Đã đặt lại bộ lọc');
};

// ── Checkbox Selection ────────────────────────────────────────────────────────
const isAllSelected = computed(() => {
    if (paginatedIngredients.value.length === 0) {
        return false;
    }

    return paginatedIngredients.value.every((ing) =>
        selectedIds.value.includes(ing.id),
    );
});

const toggleSelectAll = () => {
    if (isAllSelected.value) {
        const currentIds = new Set(paginatedIngredients.value.map((i) => i.id));
        selectedIds.value = selectedIds.value.filter(
            (id) => !currentIds.has(id),
        );
    } else {
        const set = new Set(selectedIds.value);
        paginatedIngredients.value.forEach((i) => set.add(i.id));
        selectedIds.value = Array.from(set);
    }
};

const toggleSelectItem = (id: number) => {
    const idx = selectedIds.value.indexOf(id);

    if (idx >= 0) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
};

// ── Notes & Deduction Form State ──────────────────────────────────────────────
const generalNotes = ref('');
const isOpeningBalance = ref(false);
const responsibleEmployeeId = ref('');

// ── Item Note Modal ───────────────────────────────────────────────────────────
const activeNoteItem = ref<Ingredient | null>(null);
const tempItemNote = ref('');

const openItemNoteModal = (ing: Ingredient) => {
    if (!requireAuditSession()) {
        return;
    }

    activeNoteItem.value = ing;
    tempItemNote.value = itemNotesMap.value[ing.id] || '';
};

const saveItemNote = () => {
    if (activeNoteItem.value) {
        itemNotesMap.value[activeNoteItem.value.id] = tempItemNote.value.trim();
        toast.success(`Đã lưu ghi chú cho ${activeNoteItem.value.name}`);
        activeNoteItem.value = null;
    }
};

const requireAuditSession = () => {
    if (!isAuditStarted.value || !activeSessionId.value) {
        toast.info('Hãy bấm “Bắt đầu phiên kiểm” trước khi thao tác kiểm kê.');

        return false;
    }

    return true;
};

const startAuditSession = async () => {
    if (isAuditStarted.value && activeSessionId.value) {
        toast.info('Phiên kiểm kê hiện tại đang được mở.');

        return;
    }

    if (!props.activeBranchId) {
        toast.error(
            'Vui lòng chọn một chi nhánh cụ thể trước khi bắt đầu phiên kiểm.',
        );

        return;
    }

    isStartingSession.value = true;

    try {
        const response = await axios.post('/api/inventory/count-sessions', {
            branch_id: props.activeBranchId,
            type: 'periodic',
            blind_count: false,
        });
        const session = response.data.data;

        activeSessionId.value = Number(session.id);
        isAuditStarted.value = true;
        emptyCounts();
        itemNotesMap.value = {};
        selectedIds.value = [];
        activityLog.value.unshift({
            id: Date.now(),
            time: new Date().toLocaleTimeString('vi-VN', {
                hour: '2-digit',
                minute: '2-digit',
            }),
            author: props.authUserName || 'Người thực hiện',
            text: `Bắt đầu phiên kiểm #${session.id}`,
        });

        historySessions.value = [
            {
                id: Number(session.id),
                branch_id: Number(session.branch_id),
                branch_name: props.activeBranchName,
                type: session.type || 'periodic',
                status: session.status || 'in_progress',
                started_at: session.started_at || new Date().toISOString(),
                completed_at: null,
                total_variance_value: 0,
                items_count: props.ingredients.length,
                counted_by_name: props.authUserName,
            },
            ...historySessions.value.filter(
                (item) => item.id !== Number(session.id),
            ),
        ];

        toast.success(response.data.message || 'Đã bắt đầu phiên kiểm kê.');
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Không thể bắt đầu phiên kiểm kê.',
        );
    } finally {
        isStartingSession.value = false;
    }
};

const focusIngredientInput = async (ingredient: Ingredient) => {
    if (!requireAuditSession()) {
        return;
    }

    editingIngredientId.value = ingredient.id;
    await nextTick();
    const input = document.querySelector<HTMLInputElement>(
        `[data-ingredient-input="${ingredient.id}"]`,
    );
    input?.focus();
    input?.select();
};

const openIngredientDetails = (ingredient: Ingredient) => {
    detailIngredient.value = ingredient;
};

const formatSessionDate = (value?: string | null) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getSessionTypeLabel = (type: string) => {
    if (type === 'spot_check') {
        return 'Đột xuất';
    }

    if (type === 'abc_cycle') {
        return 'Chu kỳ ABC';
    }

    return 'Định kỳ';
};

const getSessionStatusLabel = (status: string) => {
    if (status === 'in_progress') {
        return 'Đang kiểm';
    }

    if (status === 'pending_approval') {
        return 'Chờ duyệt';
    }

    if (status === 'approved') {
        return 'Đã hoàn tất';
    }

    if (status === 'rejected') {
        return 'Từ chối';
    }

    if (status === 'cancelled') {
        return 'Đã hủy';
    }

    return 'Bản nháp';
};

const getSessionStatusClass = (status: string) => {
    if (status === 'approved') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400';
    }

    if (status === 'pending_approval') {
        return 'border-blue-500/30 bg-blue-500/10 text-blue-400';
    }

    if (status === 'rejected' || status === 'cancelled') {
        return 'border-rose-500/30 bg-rose-500/10 text-rose-400';
    }

    return 'border-amber-500/30 bg-amber-500/10 text-amber-400';
};

const recentHistorySessions = computed(() => historySessions.value.slice(0, 3));
const currentSession = computed(
    () =>
        historySessions.value.find(
            (session) => session.id === activeSessionId.value,
        ) ?? null,
);

const openHistoryModal = () => {
    activeSubTab.value = 'history';
    showHistoryModal.value = true;
};

// ── Export Excel / CSV ────────────────────────────────────────────────────────
const exportToExcel = () => {
    try {
        const header = [
            'STT',
            'Mã SKU',
            'Tên nguyên liệu',
            'Danh mục',
            'Tồn hệ thống',
            'Đếm thực tế',
            'Chênh lệch',
            'Đơn vị',
            'Trạng thái',
            'Ghi chú',
        ];
        const rows = props.ingredients.map((ing, idx) => {
            const phys = getItemPhysical(ing.id);
            const diff = getItemDiff(ing);
            const st = getItemStatus(ing);
            const statusLabel =
                st === 'matched'
                    ? 'Khớp'
                    : st === 'uncounted'
                      ? 'Chưa kiểm kê'
                      : 'Chênh lệch';

            return [
                idx + 1,
                `"${ing.sku ?? ''}"`,
                `"${ing.name.replace(/"/g, '""')}"`,
                `"${ing.category_name ?? ''}"`,
                ing.stock ?? 0,
                phys !== null ? phys : '',
                diff !== null ? diff : '',
                `"${ing.unit?.symbol ?? ''}"`,
                `"${statusLabel}"`,
                `"${(itemNotesMap.value[ing.id] ?? '').replace(/"/g, '""')}"`,
            ];
        });

        const csvContent =
            '\uFEFF' +
            [header.join(','), ...rows.map((r) => r.join(','))].join('\n');
        const blob = new Blob([csvContent], {
            type: 'text/csv;charset=utf-8;',
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute(
            'download',
            `Phieu_Kiem_Ke_#KK-240925-01_${new Date().toISOString().slice(0, 10)}.csv`,
        );
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        toast.success('Đã xuất file Excel / CSV kiểm kê kho thành công!');
    } catch (e) {
        toast.error('Không thể xuất file. Vui lòng thử lại.');
    }
};

// ── Print Sheet ───────────────────────────────────────────────────────────────
const printAuditSlip = () => {
    window.print();
};

// ── Submit Audit ──────────────────────────────────────────────────────────────
const submitAudit = () => {
    if (!requireAuditSession()) {
        return;
    }

    const uncounted = props.ingredients.filter((ing) => !isItemCounted(ing.id));

    if (uncounted.length > 0) {
        toast.error(`Còn ${uncounted.length} nguyên liệu chưa được kiểm kê.`);

        return;
    }

    const items = props.ingredients.map((ing) => ({
        ingredient_id: ing.id,
        physical_qty: Number(physicalStockMap.value[ing.id]),
    }));

    emit('submit', {
        items,
        employee_id: responsibleEmployeeId.value || null,
        is_opening_balance: isOpeningBalance.value,
        notes: generalNotes.value.trim() || 'Kiểm kê #KK-240925-01 hoàn tất',
        count_session_id: activeSessionId.value as number,
    });
};

// ── Image resolver (Unsplash curated matching Image 2) ────────────────────────
const getIngredientThumbnail = (ing: Ingredient) => {
    if (ing.image_url) {
        return ing.image_url;
    }

    const name = ing.name.toLowerCase();

    if (name.includes('bò') || name.includes('beef')) {
        return 'https://images.unsplash.com/photo-1603048588665-791ca8aea617?w=100&auto=format&fit=crop&q=80';
    }

    if (name.includes('gà') || name.includes('chick')) {
        return 'https://images.unsplash.com/photo-1604503468506-a8da13d82791?w=100&auto=format&fit=crop&q=80';
    }

    if (
        name.includes('xà lách') ||
        name.includes('rau') ||
        name.includes('lettuce')
    ) {
        return 'https://images.unsplash.com/photo-1622206151226-18ca2c9ab4a1?w=100&auto=format&fit=crop&q=80';
    }

    if (name.includes('gạo') || name.includes('rice')) {
        return 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=100&auto=format&fit=crop&q=80';
    }

    if (name.includes('dầu') || name.includes('oil')) {
        return 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?w=100&auto=format&fit=crop&q=80';
    }

    if (
        name.includes('mắm') ||
        name.includes('sauce') ||
        name.includes('tương')
    ) {
        return 'https://images.unsplash.com/photo-1589301760014-d929f3979dbc?w=100&auto=format&fit=crop&q=80';
    }

    if (name.includes('bia') || name.includes('beer')) {
        return 'https://images.unsplash.com/photo-1608270190989-c56784d1a52c?w=100&auto=format&fit=crop&q=80';
    }

    if (name.includes('sữa') || name.includes('milk')) {
        return 'https://images.unsplash.com/photo-1550583724-b2692b85b150?w=100&auto=format&fit=crop&q=80';
    }

    if (name.includes('trứng') || name.includes('egg')) {
        return 'https://images.unsplash.com/photo-1516448620398-c5f44bf9f441?w=100&auto=format&fit=crop&q=80';
    }

    return 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=100&auto=format&fit=crop&q=80';
};
</script>

<template>
    <div class="audit-workspace animate-in space-y-6 duration-200 fade-in">
        <!-- ══ 1. BREADCRUMB ════════════════════════════════════════════════════ -->
        <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <button
                @click="emit('back')"
                class="inline-flex cursor-pointer items-center gap-1.5 font-medium transition-colors hover:text-foreground"
            >
                <ArrowLeft class="size-3.5" />
                <span>Kiểm kê & Đối soát kho</span>
            </button>
            <ChevronRight class="size-3.5 opacity-40" />
            <span class="font-semibold text-foreground/90"
                >Chi tiết kiểm kê #KK-240925-01</span
            >
        </div>

        <!-- ══ 2. HEADER TITLE & ACTION BUTTONS ═════════════════════════════════ -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black tracking-tight text-foreground">
                    Kiểm kê #KK-240925-01
                </h1>
                <span
                    class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                    :class="
                        isAuditStarted
                            ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-400'
                            : 'border-amber-500/40 bg-amber-500/10 text-amber-400'
                    "
                >
                    {{
                        isAuditStarted
                            ? `Đang kiểm kê #${activeSessionId}`
                            : 'Chưa bắt đầu phiên'
                    }}
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <button
                    @click="startAuditSession"
                    :disabled="
                        isStartingSession || isAuditStarted || !activeBranchId
                    "
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-md transition-all hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Plus class="size-4" />
                    <span>{{
                        isStartingSession
                            ? 'Đang tạo phiên...'
                            : isAuditStarted
                              ? 'Phiên đang mở'
                              : 'Bắt đầu phiên kiểm'
                    }}</span>
                </button>

                <!-- Nút Xuất Excel -->
                <button
                    @click="exportToExcel"
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border/80 bg-card px-3.5 py-2 text-xs font-semibold text-foreground shadow-sm transition-all hover:border-border hover:bg-muted"
                >
                    <FileSpreadsheet class="size-4 text-emerald-500" />
                    <span>Xuất Excel</span>
                </button>

                <!-- Nút In biên bản -->
                <button
                    @click="printAuditSlip"
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border/80 bg-card px-3.5 py-2 text-xs font-semibold text-foreground shadow-sm transition-all hover:border-border hover:bg-muted"
                >
                    <Printer class="size-4 text-sky-500" />
                    <span>In biên bản</span>
                    <span class="text-[10px] opacity-60">▾</span>
                </button>

                <!-- Nút Hoàn tất kiểm kê (Nút tím nổi bật) -->
                <button
                    @click="submitAudit"
                    :disabled="isReconciling || !isAuditStarted"
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-md transition-all hover:bg-indigo-500 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Check class="size-4" />
                    <span>{{
                        isReconciling ? 'Đang xử lý...' : 'Hoàn tất kiểm kê'
                    }}</span>
                </button>
            </div>
        </div>

        <!-- ══ 3. METADATA INFO STRIP ════════════════════════════════════════════ -->
        <div
            class="grid grid-cols-2 gap-4 rounded-2xl border border-border/80 bg-card/60 p-4 text-xs shadow-sm sm:grid-cols-3 lg:grid-cols-6"
        >
            <!-- Chi nhánh -->
            <div>
                <p class="text-[11px] font-medium text-muted-foreground">
                    Chi nhánh
                </p>
                <div
                    class="mt-1 flex items-center gap-1.5 font-bold text-foreground"
                >
                    <MapPin class="size-3.5 shrink-0 text-indigo-400" />
                    <span class="truncate">{{
                        activeBranchName || 'Toàn chuỗi'
                    }}</span>
                </div>
            </div>

            <!-- Kho -->
            <div>
                <p class="text-[11px] font-medium text-muted-foreground">Kho</p>
                <div
                    class="mt-1 flex items-center gap-1.5 font-bold text-foreground"
                >
                    <Warehouse class="size-3.5 shrink-0 text-indigo-400" />
                    <span class="truncate">{{
                        activeBranchId
                            ? 'Kho nguyên liệu - Chi nhánh'
                            : 'Kho nguyên liệu - Tổng hợp toàn chuỗi'
                    }}</span>
                </div>
            </div>

            <!-- Ngày kiểm kê -->
            <div>
                <p class="text-[11px] font-medium text-muted-foreground">
                    Ngày kiểm kê
                </p>
                <p class="mt-1 font-bold text-foreground">
                    {{ formatSessionDate(currentSession?.started_at) }}
                </p>
            </div>

            <!-- Người thực hiện -->
            <div>
                <p class="text-[11px] font-medium text-muted-foreground">
                    Người thực hiện
                </p>
                <div
                    class="mt-1 flex items-center gap-2 font-bold text-foreground"
                >
                    <span
                        class="flex size-5 items-center justify-center rounded-full bg-rose-500 text-[10px] font-black text-white"
                    >
                        NV
                    </span>
                    <span class="truncate">{{
                        authUserName || 'Nguyễn Văn A'
                    }}</span>
                </div>
            </div>

            <!-- Phương pháp -->
            <div>
                <p class="text-[11px] font-medium text-muted-foreground">
                    Phương pháp
                </p>
                <p class="mt-1 font-bold text-foreground">Kiểm kê thủ công</p>
            </div>

            <!-- Trạng thái -->
            <div>
                <p class="text-[11px] font-medium text-muted-foreground">
                    Trạng thái
                </p>
                <div class="mt-1">
                    <span
                        class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium"
                        :class="
                            getSessionStatusClass(
                                isAuditStarted ? 'in_progress' : 'draft',
                            )
                        "
                    >
                        {{ isAuditStarted ? 'Đang thực hiện' : 'Chưa bắt đầu' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- ══ 4. SECONDARY NAVIGATION TABS ══════════════════════════════════════ -->
        <div
            class="flex items-center gap-6 border-b border-border text-sm font-semibold"
        >
            <button
                @click="activeSubTab = 'list'"
                class="cursor-pointer pb-3 transition-colors"
                :class="
                    activeSubTab === 'list'
                        ? 'border-b-2 border-indigo-500 font-bold text-indigo-400'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                Danh sách nguyên liệu ({{ ingredients.length }})
            </button>
            <button
                @click="activeSubTab = 'summary'"
                class="cursor-pointer pb-3 transition-colors"
                :class="
                    activeSubTab === 'summary'
                        ? 'border-b-2 border-indigo-500 font-bold text-indigo-400'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                Tổng hợp chênh lệch
            </button>
            <button
                @click="openHistoryModal"
                class="cursor-pointer pb-3 transition-colors"
                :class="
                    activeSubTab === 'history'
                        ? 'border-b-2 border-indigo-500 font-bold text-indigo-400'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                Lịch sử kiểm kê
            </button>
        </div>

        <!-- ══ 5. FIVE STAT SUMMARY CARDS ════════════════════════════════════════ -->
        <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-5">
            <!-- 1. Tổng số mặt hàng (Tím) -->
            <div
                class="rounded-2xl border border-border/80 bg-card p-4 shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-purple-500/15 text-purple-400"
                    >
                        <ClipboardList class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-muted-foreground">
                            Tổng số mặt hàng
                        </p>
                        <p class="text-2xl font-black text-foreground">
                            {{ stats.total }}
                        </p>
                    </div>
                </div>
                <div
                    class="mt-2 text-right text-xs font-semibold text-muted-foreground"
                >
                    100%
                </div>
            </div>

            <!-- 2. Đã kiểm kê (Xanh lá) -->
            <div
                class="rounded-2xl border border-border/80 bg-card p-4 shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-400"
                    >
                        <CheckSquare class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-muted-foreground">
                            Đã kiểm kê
                        </p>
                        <p class="text-2xl font-black text-foreground">
                            {{ stats.counted }}
                        </p>
                    </div>
                </div>
                <div
                    class="mt-2 text-right text-xs font-semibold text-muted-foreground"
                >
                    {{ stats.countedPct }}%
                </div>
            </div>

            <!-- 3. Chênh lệch (+) (Xanh dương) -->
            <div
                class="rounded-2xl border border-border/80 bg-card p-4 shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-blue-500/15 text-blue-400"
                    >
                        <TrendingUp class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-muted-foreground">
                            Chênh lệch (+)
                        </p>
                        <p class="text-2xl font-black text-foreground">
                            {{ stats.surplus }}
                        </p>
                    </div>
                </div>
                <div
                    class="mt-2 text-right text-xs font-semibold text-muted-foreground"
                >
                    {{ stats.surplusPct }}%
                </div>
            </div>

            <!-- 4. Chênh lệch (-) (Đỏ) -->
            <div
                class="rounded-2xl border border-border/80 bg-card p-4 shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-rose-500/15 text-rose-400"
                    >
                        <TrendingDown class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-muted-foreground">
                            Chênh lệch (-)
                        </p>
                        <p class="text-2xl font-black text-foreground">
                            {{ stats.deficit }}
                        </p>
                    </div>
                </div>
                <div
                    class="mt-2 text-right text-xs font-semibold text-muted-foreground"
                >
                    {{ stats.deficitPct }}%
                </div>
            </div>

            <!-- 5. Chưa kiểm kê (Cam) -->
            <div
                class="rounded-2xl border border-border/80 bg-card p-4 shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-amber-500/15 text-amber-400"
                    >
                        <Clock class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-muted-foreground">
                            Chưa kiểm kê
                        </p>
                        <p class="text-2xl font-black text-foreground">
                            {{ stats.uncounted }}
                        </p>
                    </div>
                </div>
                <div
                    class="mt-2 text-right text-xs font-semibold text-muted-foreground"
                >
                    {{ stats.uncountedPct }}%
                </div>
            </div>
        </div>

        <!-- ══ 6. MAIN CONTENT AREA: 2 COLUMNS (TABLE + SIDEBAR) ═════════════════ -->
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
            <!-- ── LEFT COLUMN: Filters + Table + Pagination (72% / span-8 or 9) ── -->
            <div class="space-y-4 lg:col-span-8 xl:col-span-9">
                <!-- Filter Toolbar -->
                <div
                    class="flex flex-wrap items-center gap-2.5 rounded-2xl border border-border/80 bg-card p-3 text-xs shadow-sm"
                >
                    <!-- Search input -->
                    <div class="relative min-w-[220px] flex-1">
                        <Search
                            class="absolute top-2.5 left-3 size-3.5 text-muted-foreground"
                        />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Tìm kiếm nguyên liệu, mã SKU..."
                            class="h-9 w-full rounded-xl border border-border bg-background py-1.5 pr-3 pl-8 text-xs text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                        />
                    </div>

                    <!-- Category Filter -->
                    <select
                        v-model="categoryFilter"
                        class="h-9 cursor-pointer rounded-xl border border-border bg-background px-3 text-xs text-foreground focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                    >
                        <option value="all">Danh mục: Tất cả</option>
                        <option
                            v-for="cat in categories"
                            :key="cat"
                            :value="cat"
                        >
                            Danh mục: {{ cat }}
                        </option>
                    </select>

                    <!-- Status Filter -->
                    <select
                        v-model="statusFilter"
                        class="h-9 cursor-pointer rounded-xl border border-border bg-background px-3 text-xs text-foreground focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                    >
                        <option value="all">Trạng thái: Tất cả</option>
                        <option value="counted">Đã kiểm kê</option>
                        <option value="uncounted">Chưa kiểm kê</option>
                        <option value="matched">Khớp</option>
                        <option value="diff">Có chênh lệch</option>
                    </select>

                    <!-- Variance Filter -->
                    <select
                        v-model="varianceFilter"
                        class="h-9 cursor-pointer rounded-xl border border-border bg-background px-3 text-xs text-foreground focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                    >
                        <option value="all">Chênh lệch: Tất cả</option>
                        <option value="surplus">Chênh lệch (+)</option>
                        <option value="deficit">Chênh lệch (-)</option>
                        <option value="matched">Khớp (0)</option>
                    </select>

                    <!-- Reset Button -->
                    <button
                        @click="resetFilters"
                        type="button"
                        class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-xl border border-border bg-background px-3 text-xs font-medium text-foreground transition-colors hover:bg-muted"
                        title="Làm mới bộ lọc"
                    >
                        <RotateCw class="size-3.5" />
                        <span>Làm mới</span>
                    </button>
                </div>

                <!-- Table Card -->
                <div
                    class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="border-b border-border bg-muted/30 text-[11px] font-bold text-muted-foreground uppercase"
                            >
                                <tr>
                                    <!-- Checkbox -->
                                    <th class="w-10 p-3 text-center">
                                        <input
                                            type="checkbox"
                                            :checked="isAllSelected"
                                            @change="toggleSelectAll"
                                            class="cursor-pointer rounded border-border text-indigo-600 focus:ring-indigo-500"
                                        />
                                    </th>

                                    <!-- Nguyên liệu -->
                                    <th
                                        @click="toggleSort('name')"
                                        class="cursor-pointer p-3 transition-colors select-none hover:text-foreground"
                                    >
                                        <div
                                            class="inline-flex items-center gap-1"
                                        >
                                            <span>Nguyên liệu</span>
                                            <span class="opacity-60">↕</span>
                                        </div>
                                    </th>

                                    <!-- Tồn hệ thống -->
                                    <th
                                        @click="toggleSort('stock')"
                                        class="cursor-pointer p-3 text-right transition-colors select-none hover:text-foreground"
                                    >
                                        <div
                                            class="inline-flex items-center justify-end gap-1"
                                        >
                                            <span>Tồn hệ thống</span>
                                        </div>
                                    </th>

                                    <!-- Đếm thực tế -->
                                    <th
                                        @click="toggleSort('physical')"
                                        class="cursor-pointer p-3 text-center transition-colors select-none hover:text-foreground"
                                    >
                                        <div
                                            class="inline-flex items-center justify-center gap-1"
                                        >
                                            <span>Đếm thực tế</span>
                                            <span class="opacity-60">↕</span>
                                        </div>
                                    </th>

                                    <!-- Chênh lệch -->
                                    <th
                                        @click="toggleSort('diff')"
                                        class="cursor-pointer p-3 text-center transition-colors select-none hover:text-foreground"
                                    >
                                        <span>Chênh lệch</span>
                                    </th>

                                    <!-- Đơn vị / Lệch quy đổi -->
                                    <th class="p-3 text-center">
                                        <span>Đơn vị</span>
                                    </th>

                                    <!-- Trạng thái -->
                                    <th
                                        @click="toggleSort('status')"
                                        class="cursor-pointer p-3 text-center transition-colors select-none hover:text-foreground"
                                    >
                                        <div
                                            class="inline-flex items-center justify-center gap-1"
                                        >
                                            <span>Trạng thái</span>
                                            <span class="opacity-60">↕</span>
                                        </div>
                                    </th>

                                    <!-- Ghi chú -->
                                    <th class="p-3 text-center">
                                        <span>Ghi chú</span>
                                    </th>

                                    <!-- Thao tác -->
                                    <th class="p-3 text-center">
                                        <span>Thao tác</span>
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-border/60">
                                <tr
                                    v-for="ing in paginatedIngredients"
                                    :key="ing.id"
                                    class="transition-colors hover:bg-muted/30"
                                    :class="
                                        selectedIds.includes(ing.id)
                                            ? 'bg-indigo-500/5'
                                            : ''
                                    "
                                >
                                    <!-- Checkbox -->
                                    <td class="p-3 text-center">
                                        <input
                                            type="checkbox"
                                            :checked="
                                                selectedIds.includes(ing.id)
                                            "
                                            @change="toggleSelectItem(ing.id)"
                                            class="cursor-pointer rounded border-border text-indigo-600 focus:ring-indigo-500"
                                        />
                                    </td>

                                    <!-- Nguyên liệu (Ảnh + Tên + SKU) -->
                                    <td class="p-3">
                                        <div class="flex items-center gap-3">
                                            <img
                                                :src="
                                                    getIngredientThumbnail(ing)
                                                "
                                                :alt="ing.name"
                                                class="size-9 shrink-0 rounded-lg border border-border/80 object-cover shadow-xs"
                                                loading="lazy"
                                            />
                                            <div class="min-w-0">
                                                <p
                                                    class="truncate text-sm leading-tight font-bold text-foreground"
                                                >
                                                    {{ ing.name }}
                                                </p>
                                                <p
                                                    class="mt-0.5 font-mono text-[10px] text-muted-foreground"
                                                >
                                                    {{
                                                        ing.sku ?? 'ING-NONE-00'
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Tồn hệ thống -->
                                    <td
                                        class="p-3 text-right font-mono text-xs font-semibold text-foreground"
                                    >
                                        {{
                                            Number(
                                                ing.stock ?? 0,
                                            ).toLocaleString('vi-VN')
                                        }}
                                        <span
                                            class="text-[11px] font-normal text-muted-foreground"
                                            >{{
                                                ing.unit?.symbol ?? 'đv'
                                            }}</span
                                        >
                                    </td>

                                    <!-- Đếm thực tế (Editable input) -->
                                    <td class="p-3 text-center">
                                        <div
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <div class="relative w-28">
                                                <input
                                                    :data-ingredient-input="
                                                        ing.id
                                                    "
                                                    type="number"
                                                    step="any"
                                                    v-model="
                                                        physicalStockMap[ing.id]
                                                    "
                                                    @change="
                                                        handleCountChange(ing)
                                                    "
                                                    :disabled="!isAuditStarted"
                                                    placeholder="Nhập số lượng..."
                                                    class="h-8 w-full rounded-lg border border-border bg-background px-2 text-center font-mono text-xs font-bold text-foreground placeholder:text-[10px] placeholder:font-normal placeholder:text-muted-foreground focus:ring-2 focus:ring-indigo-500/20 focus:outline-none disabled:cursor-not-allowed disabled:bg-muted/40 disabled:opacity-60"
                                                />
                                            </div>
                                            <span
                                                class="w-6 text-left text-[11px] font-medium text-muted-foreground"
                                            >
                                                {{ ing.unit?.symbol ?? 'đv' }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Chênh lệch -->
                                    <td
                                        class="p-3 text-center font-mono text-xs font-bold"
                                    >
                                        <template v-if="!isItemCounted(ing.id)">
                                            <span class="text-muted-foreground"
                                                >-</span
                                            >
                                        </template>
                                        <template v-else>
                                            <span
                                                :class="[
                                                    getItemDiff(ing) === 0
                                                        ? 'font-semibold text-blue-400'
                                                        : (getItemDiff(ing) ??
                                                                0) < 0
                                                          ? 'font-bold text-rose-400'
                                                          : 'font-bold text-emerald-400',
                                                ]"
                                            >
                                                {{
                                                    (getItemDiff(ing) ?? 0) > 0
                                                        ? '+'
                                                        : ''
                                                }}{{ getItemDiff(ing) }}
                                                <span
                                                    class="text-[10px] font-normal text-muted-foreground"
                                                    >{{
                                                        ing.unit?.symbol ?? 'đv'
                                                    }}</span
                                                >
                                            </span>
                                        </template>
                                    </td>

                                    <!-- Đơn vị quy đổi / chênh lệch chuẩn -->
                                    <td
                                        class="p-3 text-center font-mono text-xs text-muted-foreground"
                                    >
                                        <template v-if="!isItemCounted(ing.id)">
                                            -
                                        </template>
                                        <template v-else>
                                            0 {{ ing.unit?.symbol ?? 'kg' }}
                                        </template>
                                    </td>

                                    <!-- Trạng thái Badge -->
                                    <td class="p-3 text-center">
                                        <span
                                            v-if="
                                                getItemStatus(ing) ===
                                                'uncounted'
                                            "
                                            class="inline-flex items-center rounded-full border border-amber-500/40 bg-amber-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-amber-400"
                                        >
                                            Chưa kiểm kê
                                        </span>
                                        <span
                                            v-else-if="
                                                getItemStatus(ing) === 'matched'
                                            "
                                            class="inline-flex items-center rounded-full border border-blue-500/40 bg-blue-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-blue-400"
                                        >
                                            Khớp
                                        </span>
                                        <span
                                            v-else-if="
                                                getItemStatus(ing) === 'surplus'
                                            "
                                            class="inline-flex items-center rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-400"
                                        >
                                            Chênh lệch
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full border border-rose-500/40 bg-rose-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-rose-400"
                                        >
                                            Chênh lệch
                                        </span>
                                    </td>

                                    <!-- Ghi chú icon -->
                                    <td class="p-3 text-center">
                                        <button
                                            @click="openItemNoteModal(ing)"
                                            type="button"
                                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            :title="
                                                itemNotesMap[ing.id]
                                                    ? itemNotesMap[ing.id]
                                                    : 'Thêm ghi chú'
                                            "
                                        >
                                            <FileText
                                                class="size-3.5"
                                                :class="
                                                    itemNotesMap[ing.id]
                                                        ? 'text-indigo-400'
                                                        : ''
                                                "
                                            />
                                        </button>
                                    </td>

                                    <!-- Thao tác -->
                                    <td class="p-3 text-center">
                                        <div
                                            class="inline-flex items-center gap-1"
                                        >
                                            <button
                                                type="button"
                                                @click="
                                                    focusIngredientInput(ing)
                                                "
                                                class="inline-flex size-7 cursor-pointer items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                                title="Chỉnh sửa"
                                            >
                                                <Edit3 class="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                @click="
                                                    openIngredientDetails(ing)
                                                "
                                                class="inline-flex size-7 cursor-pointer items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                                title="Xem chi tiết"
                                            >
                                                <FileSpreadsheet
                                                    class="size-3.5"
                                                />
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="paginatedIngredients.length === 0">
                                    <td
                                        colspan="9"
                                        class="py-12 text-center text-xs text-muted-foreground"
                                    >
                                        Không tìm thấy nguyên liệu nào khớp với
                                        bộ lọc.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Table Pagination Footer -->
                    <div
                        class="flex flex-col gap-3 border-t border-border bg-muted/20 px-4 py-3 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            Hiển thị
                            <span class="font-bold text-foreground">
                                {{
                                    totalFiltered > 0
                                        ? (currentPage - 1) * perPage + 1
                                        : 0
                                }}-{{
                                    Math.min(
                                        currentPage * perPage,
                                        totalFiltered,
                                    )
                                }}
                            </span>
                            /
                            <span class="font-bold text-foreground">{{
                                totalFiltered
                            }}</span>
                        </div>

                        <!-- Page Buttons -->
                        <div class="flex items-center gap-1.5 self-center">
                            <button
                                @click="
                                    currentPage = Math.max(1, currentPage - 1)
                                "
                                :disabled="currentPage <= 1"
                                class="inline-flex size-7 cursor-pointer items-center justify-center rounded-lg border border-border bg-background text-foreground hover:bg-muted disabled:opacity-40"
                            >
                                <ChevronLeft class="size-3.5" />
                            </button>

                            <button
                                v-for="p in totalPages"
                                :key="p"
                                @click="currentPage = p"
                                class="inline-flex size-7 cursor-pointer items-center justify-center rounded-lg text-xs font-bold transition-all"
                                :class="
                                    currentPage === p
                                        ? 'bg-indigo-600 text-white shadow-xs'
                                        : 'border border-border bg-background text-foreground hover:bg-muted'
                                "
                            >
                                {{ p }}
                            </button>

                            <button
                                @click="
                                    currentPage = Math.min(
                                        totalPages,
                                        currentPage + 1,
                                    )
                                "
                                :disabled="currentPage >= totalPages"
                                class="inline-flex size-7 cursor-pointer items-center justify-center rounded-lg border border-border bg-background text-foreground hover:bg-muted disabled:opacity-40"
                            >
                                <ChevronRight class="size-3.5" />
                            </button>
                        </div>

                        <!-- Per page selector -->
                        <div class="flex items-center gap-2">
                            <span>Hiển thị</span>
                            <select
                                v-model.number="perPage"
                                class="h-7 cursor-pointer rounded-lg border border-border bg-background px-2 text-xs font-semibold text-foreground focus:outline-none"
                            >
                                <option :value="10">10</option>
                                <option :value="20">20</option>
                                <option :value="50">50</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT COLUMN: Summary Sidebar (28% / span-4 or 3) ─────────────── -->
            <div class="space-y-4 lg:col-span-4 xl:col-span-3">
                <!-- Card 1: Tổng hợp chênh lệch (Donut Chart) -->
                <div
                    class="space-y-4 rounded-2xl border border-border/80 bg-card p-5 shadow-sm"
                >
                    <h3 class="text-sm font-bold text-foreground">
                        Tổng hợp chênh lệch
                    </h3>

                    <!-- SVG Donut Chart -->
                    <div class="relative flex items-center justify-center py-2">
                        <svg
                            class="size-40 -rotate-90 transform"
                            viewBox="0 0 140 140"
                        >
                            <!-- Background Track -->
                            <circle
                                cx="70"
                                cy="70"
                                r="54"
                                fill="transparent"
                                stroke="currentColor"
                                stroke-width="12"
                                class="text-muted/20"
                            />

                            <!-- 1. Matched (Green) -->
                            <circle
                                cx="70"
                                cy="70"
                                r="54"
                                fill="transparent"
                                stroke="#10b981"
                                stroke-width="12"
                                stroke-linecap="round"
                                :stroke-dasharray="
                                    donutSegments.segMatched.strokeDasharray
                                "
                                :stroke-dashoffset="
                                    donutSegments.segMatched.strokeDashoffset
                                "
                                class="transition-all duration-500"
                            />

                            <!-- 2. Surplus (Blue) -->
                            <circle
                                cx="70"
                                cy="70"
                                r="54"
                                fill="transparent"
                                stroke="#3b82f6"
                                stroke-width="12"
                                stroke-linecap="round"
                                :stroke-dasharray="
                                    donutSegments.segSurplus.strokeDasharray
                                "
                                :stroke-dashoffset="
                                    donutSegments.segSurplus.strokeDashoffset
                                "
                                class="transition-all duration-500"
                            />

                            <!-- 3. Deficit (Red) -->
                            <circle
                                cx="70"
                                cy="70"
                                r="54"
                                fill="transparent"
                                stroke="#ef4444"
                                stroke-width="12"
                                stroke-linecap="round"
                                :stroke-dasharray="
                                    donutSegments.segDeficit.strokeDasharray
                                "
                                :stroke-dashoffset="
                                    donutSegments.segDeficit.strokeDashoffset
                                "
                                class="transition-all duration-500"
                            />

                            <!-- 4. Uncounted (Orange) -->
                            <circle
                                cx="70"
                                cy="70"
                                r="54"
                                fill="transparent"
                                stroke="#f59e0b"
                                stroke-width="12"
                                stroke-linecap="round"
                                :stroke-dasharray="
                                    donutSegments.segUncounted.strokeDasharray
                                "
                                :stroke-dashoffset="
                                    donutSegments.segUncounted.strokeDashoffset
                                "
                                class="transition-all duration-500"
                            />
                        </svg>

                        <!-- Donut Center Labels -->
                        <div
                            class="absolute inset-0 flex flex-col items-center justify-center text-center select-none"
                        >
                            <span
                                class="text-[11px] font-medium text-muted-foreground"
                                >Tổng</span
                            >
                            <span
                                class="text-xl font-black tracking-tight text-foreground"
                                >{{ stats.netVariancePercent }}%</span
                            >
                            <span class="text-[10px] text-muted-foreground"
                                >({{ stats.matchedPct }}% khớp)</span
                            >
                        </div>
                    </div>

                    <!-- Legend list -->
                    <div
                        class="space-y-2 border-t border-border/60 pt-3 text-xs"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span
                                    class="size-2 rounded-full bg-emerald-500"
                                ></span>
                                <span class="font-medium text-muted-foreground"
                                    >Khớp</span
                                >
                            </div>
                            <span class="font-mono font-bold text-foreground">
                                {{ stats.matched }} ({{ stats.matchedPct }}%)
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span
                                    class="size-2 rounded-full bg-blue-500"
                                ></span>
                                <span class="font-medium text-muted-foreground"
                                    >Chênh lệch (+)</span
                                >
                            </div>
                            <span class="font-mono font-bold text-foreground">
                                {{ stats.surplus }} ({{ stats.surplusPct }}%)
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span
                                    class="size-2 rounded-full bg-rose-500"
                                ></span>
                                <span class="font-medium text-muted-foreground"
                                    >Chênh lệch (-)</span
                                >
                            </div>
                            <span class="font-mono font-bold text-foreground">
                                {{ stats.deficit }} ({{ stats.deficitPct }}%)
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span
                                    class="size-2 rounded-full bg-amber-500"
                                ></span>
                                <span class="font-medium text-muted-foreground"
                                    >Chưa kiểm kê</span
                                >
                            </div>
                            <span class="font-mono font-bold text-foreground">
                                {{ stats.uncounted }} ({{
                                    stats.uncountedPct
                                }}%)
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Thông tin ghi chú -->
                <div
                    class="space-y-3 rounded-2xl border border-border/80 bg-card p-5 shadow-sm"
                >
                    <h3 class="text-sm font-bold text-foreground">
                        Thông tin ghi chú
                    </h3>
                    <div class="relative">
                        <textarea
                            v-model="generalNotes"
                            :disabled="!isAuditStarted"
                            maxlength="255"
                            rows="3"
                            placeholder="Nhập ghi chú (nếu có)..."
                            class="w-full resize-none rounded-xl border border-border bg-background p-3 text-xs text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-indigo-500/20 focus:outline-none disabled:cursor-not-allowed disabled:bg-muted/40"
                        ></textarea>
                        <div
                            class="mt-1 text-right text-[10px] text-muted-foreground"
                        >
                            {{ generalNotes.length }}/255
                        </div>
                    </div>

                    <!-- Additional audit options -->
                    <div class="space-y-2 border-t border-border/60 pt-3">
                        <label
                            class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-indigo-400"
                        >
                            <input
                                v-model="isOpeningBalance"
                                :disabled="!isAuditStarted"
                                type="checkbox"
                                class="cursor-pointer rounded border-border text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>Đây là đối soát số dư đầu kỳ</span>
                        </label>

                        <div v-if="stats.deficit > 0" class="space-y-1 pt-1">
                            <Label class="text-[11px] font-bold text-rose-400">
                                Quy trách nhiệm thất thoát ({{
                                    vnd(stats.totalDeficitCost)
                                }}):
                            </Label>
                            <select
                                v-model="responsibleEmployeeId"
                                :disabled="!isAuditStarted"
                                class="h-8 w-full cursor-pointer rounded-xl border border-rose-500/30 bg-background px-2.5 text-xs font-medium text-foreground focus:ring-2 focus:ring-rose-500/20 focus:outline-none"
                            >
                                <option value="">
                                    Không khấu trừ lương (Quán tự chịu)
                                </option>
                                <option
                                    v-for="emp in employees"
                                    :key="emp.id"
                                    :value="emp.id"
                                >
                                    Khấu trừ: {{ emp.full_name
                                    }}{{
                                        emp.job_title
                                            ? ` (${emp.job_title})`
                                            : ''
                                    }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Lịch sử thao tác -->
                <div
                    class="space-y-3 rounded-2xl border border-border/80 bg-card p-5 shadow-sm"
                >
                    <h3 class="text-sm font-bold text-foreground">
                        Lịch sử thao tác
                    </h3>
                    <div class="space-y-3 pt-1">
                        <div
                            v-for="log in activityLog"
                            :key="log.id"
                            class="flex items-start gap-2.5 text-xs"
                        >
                            <span
                                class="shrink-0 pt-0.5 font-mono text-[11px] text-muted-foreground"
                            >
                                {{ log.time }}
                            </span>
                            <span
                                class="mt-1.5 size-1.5 shrink-0 rounded-full bg-indigo-500"
                            ></span>
                            <div class="min-w-0">
                                <span class="font-semibold text-foreground">{{
                                    log.author
                                }}</span>
                                <p
                                    class="text-[11px] leading-snug text-muted-foreground"
                                >
                                    {{ log.text }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Lịch sử các phiên kiểm kê -->
                <div
                    class="space-y-3 rounded-2xl border border-border/80 bg-card p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-bold text-foreground">
                            Lịch sử kiểm kê
                        </h3>
                        <button
                            type="button"
                            @click="openHistoryModal"
                            class="cursor-pointer text-[11px] font-bold text-indigo-400 transition-colors hover:text-indigo-300"
                        >
                            Xem lịch sử
                        </button>
                    </div>

                    <div
                        v-if="recentHistorySessions.length"
                        class="divide-y divide-border/60"
                    >
                        <button
                            v-for="session in recentHistorySessions"
                            :key="session.id"
                            type="button"
                            @click="openHistoryModal"
                            class="flex w-full cursor-pointer items-center justify-between gap-3 py-3 text-left transition-colors first:pt-1 last:pb-1 hover:text-foreground"
                        >
                            <div class="min-w-0">
                                <p
                                    class="truncate text-xs font-bold text-foreground"
                                >
                                    Phiên #{{ session.id }}
                                </p>
                                <p
                                    class="mt-0.5 text-[10px] text-muted-foreground"
                                >
                                    {{ formatSessionDate(session.started_at) }}
                                    · {{ session.items_count }} mặt hàng
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                :class="getSessionStatusClass(session.status)"
                            >
                                {{ getSessionStatusLabel(session.status) }}
                            </span>
                        </button>
                    </div>
                    <p
                        v-else
                        class="rounded-xl border border-dashed border-border p-3 text-center text-[11px] text-muted-foreground"
                    >
                        Chưa có phiên kiểm kê nào.
                    </p>
                </div>
            </div>
        </div>

        <!-- ══ ITEM NOTE MODAL ══════════════════════════════════════════════════ -->
        <div
            v-if="activeNoteItem"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        >
            <div
                class="w-full max-w-md space-y-4 rounded-2xl border border-border bg-card p-5 shadow-xl"
            >
                <div class="flex items-center justify-between">
                    <h4
                        class="flex items-center gap-2 text-sm font-bold text-foreground"
                    >
                        <MessageSquare class="size-4 text-indigo-400" />
                        Ghi chú cho {{ activeNoteItem.name }}
                    </h4>
                    <button
                        @click="activeNoteItem = null"
                        class="inline-flex size-6 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div>
                    <textarea
                        v-model="tempItemNote"
                        rows="3"
                        placeholder="Nhập ghi chú cho nguyên liệu này (lý do lệch, tình trạng hư hỏng...)"
                        class="w-full rounded-xl border border-border bg-background p-3 text-xs text-foreground focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="activeNoteItem = null"
                        class="text-xs"
                    >
                        Hủy
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-500"
                        @click="saveItemNote"
                    >
                        Lưu ghi chú
                    </Button>
                </div>
            </div>
        </div>

        <!-- ══ INGREDIENT DETAIL MODAL ═══════════════════════════════════════════ -->
        <div
            v-if="detailIngredient"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="detailIngredient = null"
        >
            <div
                class="w-full max-w-lg space-y-5 rounded-2xl border border-border bg-card p-5 shadow-2xl"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <img
                            :src="getIngredientThumbnail(detailIngredient)"
                            :alt="detailIngredient.name"
                            class="size-12 rounded-xl border border-border object-cover"
                        />
                        <div>
                            <h4 class="text-base font-bold text-foreground">
                                {{ detailIngredient.name }}
                            </h4>
                            <p
                                class="mt-0.5 font-mono text-[11px] text-muted-foreground"
                            >
                                {{ detailIngredient.sku || 'Chưa có SKU' }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="detailIngredient = null"
                        class="inline-flex size-7 cursor-pointer items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                        aria-label="Đóng chi tiết nguyên liệu"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                    <div
                        class="rounded-xl border border-border/70 bg-muted/20 p-3"
                    >
                        <p class="text-[10px] text-muted-foreground">
                            Tồn hệ thống
                        </p>
                        <p class="mt-1 font-mono font-bold text-foreground">
                            {{
                                Number(
                                    detailIngredient.stock ?? 0,
                                ).toLocaleString('vi-VN')
                            }}
                            {{ detailIngredient.unit?.symbol || 'đv' }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-border/70 bg-muted/20 p-3"
                    >
                        <p class="text-[10px] text-muted-foreground">
                            Đếm thực tế
                        </p>
                        <p class="mt-1 font-mono font-bold text-foreground">
                            {{
                                isItemCounted(detailIngredient.id)
                                    ? Number(
                                          physicalStockMap[detailIngredient.id],
                                      ).toLocaleString('vi-VN')
                                    : 'Chưa nhập'
                            }}<span v-if="isItemCounted(detailIngredient.id)">
                                {{
                                    detailIngredient.unit?.symbol || 'đv'
                                }}</span
                            >
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-border/70 bg-muted/20 p-3"
                    >
                        <p class="text-[10px] text-muted-foreground">
                            Chênh lệch
                        </p>
                        <p
                            class="mt-1 font-mono font-bold"
                            :class="
                                (getItemDiff(detailIngredient) ?? 0) < 0
                                    ? 'text-rose-400'
                                    : (getItemDiff(detailIngredient) ?? 0) > 0
                                      ? 'text-emerald-400'
                                      : 'text-foreground'
                            "
                        >
                            {{
                                getItemDiff(detailIngredient) === null
                                    ? '—'
                                    : `${getItemDiff(detailIngredient)! > 0 ? '+' : ''}${getItemDiff(detailIngredient)} ${detailIngredient.unit?.symbol || 'đv'}`
                            }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-border/70 bg-muted/20 p-3"
                    >
                        <p class="text-[10px] text-muted-foreground">
                            Đơn giá vốn
                        </p>
                        <p class="mt-1 font-mono font-bold text-foreground">
                            {{ vnd(detailIngredient.average_cost) }}
                        </p>
                    </div>
                </div>

                <div class="space-y-1.5 text-xs">
                    <p class="font-semibold text-muted-foreground">Danh mục</p>
                    <p class="text-foreground">
                        {{ detailIngredient.category_name || 'Chưa phân loại' }}
                    </p>
                    <p
                        v-if="itemNotesMap[detailIngredient.id]"
                        class="pt-2 font-semibold text-muted-foreground"
                    >
                        Ghi chú
                    </p>
                    <p
                        v-if="itemNotesMap[detailIngredient.id]"
                        class="text-foreground"
                    >
                        {{ itemNotesMap[detailIngredient.id] }}
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="detailIngredient = null"
                        >Đóng</Button
                    >
                    <Button
                        type="button"
                        size="sm"
                        class="bg-indigo-600 text-white"
                        @click="
                            focusIngredientInput(detailIngredient);
                            detailIngredient = null;
                        "
                        >Chỉnh sửa số lượng</Button
                    >
                </div>
            </div>
        </div>

        <!-- ══ FULL INVENTORY HISTORY MODAL ══════════════════════════════════════ -->
        <div
            v-if="showHistoryModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="showHistoryModal = false"
        >
            <div
                class="flex max-h-[calc(100vh-2rem)] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
            >
                <div
                    class="flex items-start justify-between gap-4 border-b border-border p-5"
                >
                    <div>
                        <h4 class="text-base font-bold text-foreground">
                            Toàn bộ lịch sử kiểm kê
                        </h4>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Các phiên được lưu theo chi nhánh và thời điểm bắt
                            đầu.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showHistoryModal = false"
                        class="inline-flex size-7 cursor-pointer items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                        aria-label="Đóng lịch sử kiểm kê"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="overflow-y-auto p-5">
                    <div
                        v-if="historySessions.length"
                        class="overflow-x-auto rounded-xl border border-border/80"
                    >
                        <table class="w-full min-w-[680px] text-left text-xs">
                            <thead
                                class="border-b border-border bg-muted/30 text-[10px] font-bold text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th class="p-3">Phiên</th>
                                    <th class="p-3">Chi nhánh</th>
                                    <th class="p-3">Bắt đầu</th>
                                    <th class="p-3">Mặt hàng</th>
                                    <th class="p-3 text-right">Tổng lệch</th>
                                    <th class="p-3 text-center">Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr
                                    v-for="session in historySessions"
                                    :key="session.id"
                                    class="hover:bg-muted/20"
                                >
                                    <td class="p-3 font-bold text-foreground">
                                        #{{ session.id }}
                                        <p
                                            class="mt-0.5 text-[10px] font-normal text-muted-foreground"
                                        >
                                            {{
                                                getSessionTypeLabel(
                                                    session.type,
                                                )
                                            }}
                                        </p>
                                    </td>
                                    <td class="p-3 text-muted-foreground">
                                        {{ session.branch_name || '—' }}
                                    </td>
                                    <td class="p-3 text-muted-foreground">
                                        {{
                                            formatSessionDate(
                                                session.started_at,
                                            )
                                        }}
                                    </td>
                                    <td class="p-3 text-muted-foreground">
                                        {{ session.items_count }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-mono text-foreground"
                                    >
                                        {{
                                            vnd(
                                                Number(
                                                    session.total_variance_value ||
                                                        0,
                                                ),
                                            )
                                        }}
                                    </td>
                                    <td class="p-3 text-center">
                                        <span
                                            class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                            :class="
                                                getSessionStatusClass(
                                                    session.status,
                                                )
                                            "
                                            >{{
                                                getSessionStatusLabel(
                                                    session.status,
                                                )
                                            }}</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p
                        v-else
                        class="rounded-xl border border-dashed border-border p-8 text-center text-xs text-muted-foreground"
                    >
                        Chưa có lịch sử kiểm kê.
                    </p>
                </div>

                <div class="flex justify-end border-t border-border p-4">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="showHistoryModal = false"
                        >Đóng</Button
                    >
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    .audit-workspace {
        background: white !important;
        color: black !important;
    }
}
</style>
