<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Plus, Pencil, Trash2, ToggleLeft, ToggleRight, DollarSign, Search, X } from 'lucide-vue-next';

interface Unit { id: number; name: string; symbol: string }
interface Ingredient { id: number; name: string }
interface CatalogItem {
    id: number;
    name: string;
    sku: string | null;
    packaging_spec: string | null;
    description: string | null;
    current_price: number;
    status: 'active' | 'inactive';
    unit: Unit | null;
    ingredient: Ingredient | null;
    created_at: string;
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    items: Paginator<CatalogItem>;
    units: Unit[];
    filters: { search: string; status: string };
    supplier: { id: number; name: string };
}

const props = defineProps<Props>();

// ── Search filter ──────────────────────────────────────────────
const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');

const applyFilters = () => {
    router.get('/supplier-portal/catalog', { search: search.value, status: statusFilter.value }, { preserveState: true, replace: true });
};
const clearFilters = () => { search.value = ''; statusFilter.value = ''; applyFilters(); };

// ── Add item form ──────────────────────────────────────────────
const showAddForm = ref(false);
const addForm = useForm({
    name: '',
    sku: '',
    unit_id: '',
    packaging_spec: '',
    description: '',
    current_price: '',
});
const submitAdd = () => addForm.post('/supplier-portal/catalog', {
    onSuccess: () => { addForm.reset(); showAddForm.value = false; },
});

// ── Edit item form ─────────────────────────────────────────────
const editingItem = ref<CatalogItem | null>(null);
const editForm = useForm({ name: '', sku: '', unit_id: '', packaging_spec: '', description: '' });
const startEdit = (item: CatalogItem) => {
    editingItem.value = item;
    editForm.name = item.name;
    editForm.sku = item.sku ?? '';
    editForm.unit_id = String(item.unit?.id ?? '');
    editForm.packaging_spec = item.packaging_spec ?? '';
    editForm.description = item.description ?? '';
};
const submitEdit = () => {
    if (!editingItem.value) return;
    editForm.patch(`/supplier-portal/catalog/${editingItem.value.id}`, {
        onSuccess: () => { editingItem.value = null; },
    });
};

// ── Update price form ──────────────────────────────────────────
const pricingItem = ref<CatalogItem | null>(null);
const priceForm = useForm({ new_price: '', note: '', effective_at: '' });
const startPriceUpdate = (item: CatalogItem) => {
    pricingItem.value = item;
    priceForm.new_price = String(item.current_price);
    priceForm.note = '';
    priceForm.effective_at = '';
};
const submitPrice = () => {
    if (!pricingItem.value) return;
    priceForm.post(`/supplier-portal/catalog/${pricingItem.value.id}/update-price`, {
        onSuccess: () => { pricingItem.value = null; },
    });
};

// ── Helpers ────────────────────────────────────────────────────
const formatCurrency = (val: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);

const toggleStatus = (item: CatalogItem) =>
    router.patch(`/supplier-portal/catalog/${item.id}/toggle-status`);

const deleteItem = (item: CatalogItem) => {
    if (!confirm(`Xoá mặt hàng "${item.name}"?`)) return;
    router.delete(`/supplier-portal/catalog/${item.id}`);
};
</script>

<template>
    <Head title="Danh mục hàng hoá - Cổng nhà cung cấp" />

    <template #header>
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Danh mục hàng hoá</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ props.items.total }} mặt hàng</p>
            </div>
            <button
                class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
                @click="showAddForm = true"
            >
                <Plus class="size-4" /> Thêm mặt hàng
            </button>
        </div>
    </template>

    <div class="space-y-4">
        <!-- Filters -->
        <div class="flex flex-wrap gap-3">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Tìm tên mặt hàng..."
                    class="pl-9 pr-4 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @keyup.enter="applyFilters"
                />
            </div>
            <select
                v-model="statusFilter"
                class="px-3 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="applyFilters"
            >
                <option value="">Tất cả trạng thái</option>
                <option value="active">Đang kinh doanh</option>
                <option value="inactive">Tạm ngưng</option>
            </select>
            <button v-if="search || statusFilter" @click="clearFilters" class="flex items-center gap-1 px-3 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded-lg">
                <X class="size-3.5" /> Xoá bộ lọc
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Tên mặt hàng</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">SKU / Quy cách</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Đơn vị</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Giá niêm yết</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Trạng thái</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-if="props.items.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">Chưa có mặt hàng nào.</td>
                        </tr>
                        <tr
                            v-for="item in props.items.data"
                            :key="item.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900 dark:text-white">{{ item.name }}</p>
                                <p v-if="item.ingredient" class="text-xs text-gray-400">→ {{ item.ingredient.name }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                <p v-if="item.sku">{{ item.sku }}</p>
                                <p v-if="item.packaging_spec" class="text-xs">{{ item.packaging_spec }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ item.unit ? `${item.unit.name} (${item.unit.symbol})` : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">
                                {{ formatCurrency(item.current_price) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button @click="toggleStatus(item)" class="inline-flex items-center gap-1 text-xs font-medium" :class="item.status === 'active' ? 'text-green-600' : 'text-gray-400'">
                                    <ToggleRight v-if="item.status === 'active'" class="size-4" />
                                    <ToggleLeft v-else class="size-4" />
                                    {{ item.status === 'active' ? 'Đang bán' : 'Tạm ngưng' }}
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="startPriceUpdate(item)" class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950 rounded-lg transition-colors" title="Cập nhật giá">
                                        <DollarSign class="size-4" />
                                    </button>
                                    <button @click="startEdit(item)" class="p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors" title="Chỉnh sửa">
                                        <Pencil class="size-4" />
                                    </button>
                                    <button @click="deleteItem(item)" class="p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-950 rounded-lg transition-colors" title="Xoá">
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="props.items.last_page > 1" class="px-4 py-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs text-gray-500">
                <span>Trang {{ props.items.current_page }} / {{ props.items.last_page }}</span>
                <div class="flex gap-1">
                    <a
                        v-for="link in props.items.links"
                        :key="link.label"
                        v-html="link.label"
                        :href="link.url ?? '#'"
                        class="px-2 py-1 rounded"
                        :class="link.active ? 'bg-blue-600 text-white' : link.url ? 'hover:bg-gray-100 dark:hover:bg-gray-800' : 'opacity-40 pointer-events-none'"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- ── Modal: Thêm mặt hàng ─────────────────────────────── -->
    <Teleport to="body">
        <div v-if="showAddForm" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-lg shadow-xl">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Thêm mặt hàng mới</h2>
                    <button @click="showAddForm = false" class="text-gray-400 hover:text-gray-600"><X class="size-5" /></button>
                </div>
                <form @submit.prevent="submitAdd" class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tên mặt hàng *</label>
                        <input v-model="addForm.name" type="text" required class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        <p v-if="addForm.errors.name" class="text-xs text-red-500 mt-1">{{ addForm.errors.name }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SKU</label>
                            <input v-model="addForm.sku" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Đơn vị *</label>
                            <select v-model="addForm.unit_id" required class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Chọn đơn vị</option>
                                <option v-for="unit in props.units" :key="unit.id" :value="unit.id">{{ unit.name }} ({{ unit.symbol }})</option>
                            </select>
                            <p v-if="addForm.errors.unit_id" class="text-xs text-red-500 mt-1">{{ addForm.errors.unit_id }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quy cách đóng gói</label>
                        <input v-model="addForm.packaging_spec" type="text" placeholder="VD: Thùng 24 lon, Bao 50kg..." class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Giá niêm yết (VND) *</label>
                        <input v-model="addForm.current_price" type="number" min="0" step="100" required class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        <p v-if="addForm.errors.current_price" class="text-xs text-red-500 mt-1">{{ addForm.errors.current_price }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mô tả</label>
                        <textarea v-model="addForm.description" rows="2" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" />
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showAddForm = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">Huỷ</button>
                        <button type="submit" :disabled="addForm.processing" class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-medium rounded-lg transition-colors">
                            {{ addForm.processing ? 'Đang lưu...' : 'Thêm mặt hàng' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- ── Modal: Chỉnh sửa mặt hàng ──────────────────────── -->
    <Teleport to="body">
        <div v-if="editingItem" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-lg shadow-xl">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Chỉnh sửa: {{ editingItem.name }}</h2>
                    <button @click="editingItem = null" class="text-gray-400 hover:text-gray-600"><X class="size-5" /></button>
                </div>
                <form @submit.prevent="submitEdit" class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tên mặt hàng *</label>
                        <input v-model="editForm.name" type="text" required class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SKU</label>
                            <input v-model="editForm.sku" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Đơn vị *</label>
                            <select v-model="editForm.unit_id" required class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option v-for="unit in props.units" :key="unit.id" :value="unit.id">{{ unit.name }} ({{ unit.symbol }})</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quy cách đóng gói</label>
                        <input v-model="editForm.packaging_spec" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mô tả</label>
                        <textarea v-model="editForm.description" rows="2" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" />
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="editingItem = null" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">Huỷ</button>
                        <button type="submit" :disabled="editForm.processing" class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-medium rounded-lg transition-colors">
                            {{ editForm.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- ── Modal: Cập nhật giá ─────────────────────────────── -->
    <Teleport to="body">
        <div v-if="pricingItem" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-900 rounded-xl w-full max-w-md shadow-xl">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Cập nhật giá: {{ pricingItem.name }}</h2>
                    <button @click="pricingItem = null" class="text-gray-400 hover:text-gray-600"><X class="size-5" /></button>
                </div>
                <form @submit.prevent="submitPrice" class="px-6 py-4 space-y-4">
                    <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg text-sm">
                        <span class="text-gray-500">Giá hiện tại:</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ formatCurrency(pricingItem.current_price) }}</span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Giá mới (VND) *</label>
                        <input v-model="priceForm.new_price" type="number" min="0" step="100" required class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        <p v-if="priceForm.errors.new_price" class="text-xs text-red-500 mt-1">{{ priceForm.errors.new_price }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ngày hiệu lực</label>
                        <input v-model="priceForm.effective_at" type="date" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ghi chú lý do thay đổi</label>
                        <textarea v-model="priceForm.note" rows="2" placeholder="VD: Điều chỉnh theo giá thị trường tháng 6..." class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" />
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="pricingItem = null" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">Huỷ</button>
                        <button type="submit" :disabled="priceForm.processing" class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-medium rounded-lg transition-colors">
                            {{ priceForm.processing ? 'Đang lưu...' : 'Cập nhật giá' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
