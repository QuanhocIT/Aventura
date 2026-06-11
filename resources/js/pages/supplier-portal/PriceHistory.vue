<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { TrendingUp, TrendingDown, Minus, X } from 'lucide-vue-next';

interface HistoryEntry {
    id: number;
    item_name: string;
    unit: string | null;
    old_price: number;
    new_price: number;
    change_pct: number | null;
    note: string | null;
    effective_at: string;
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

interface CatalogItem { id: number; name: string }

interface Props {
    histories: Paginator<HistoryEntry>;
    items: CatalogItem[];
    filters: { item_id: number | null; from: string; to: string };
    supplier: { id: number; name: string };
}

const props = defineProps<Props>();

const itemFilter = ref<number | ''>(props.filters.item_id ?? '');
const fromFilter = ref(props.filters.from ?? '');
const toFilter   = ref(props.filters.to ?? '');

const applyFilters = () => {
    router.get('/supplier-portal/price-history', {
        item_id: itemFilter.value || undefined,
        from:    fromFilter.value || undefined,
        to:      toFilter.value || undefined,
    }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    itemFilter.value = '';
    fromFilter.value = '';
    toFilter.value   = '';
    applyFilters();
};

const formatCurrency = (val: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);

const formatDate = (iso: string) =>
    new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso));
</script>

<template>
    <Head title="Lịch sử biến động giá - Cổng nhà cung cấp" />

    <template #header>
        <div>
            <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Lịch sử biến động giá</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ props.histories.total }} bản ghi</p>
        </div>
    </template>

    <div class="space-y-4">
        <!-- Filters -->
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Mặt hàng</label>
                <select
                    v-model="itemFilter"
                    class="px-3 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @change="applyFilters"
                >
                    <option value="">Tất cả mặt hàng</option>
                    <option v-for="item in props.items" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Từ ngày</label>
                <input
                    v-model="fromFilter"
                    type="date"
                    class="px-3 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @change="applyFilters"
                />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Đến ngày</label>
                <input
                    v-model="toFilter"
                    type="date"
                    class="px-3 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @change="applyFilters"
                />
            </div>
            <button
                v-if="itemFilter || fromFilter || toFilter"
                @click="clearFilters"
                class="flex items-center gap-1 px-3 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded-lg"
            >
                <X class="size-3.5" /> Xoá bộ lọc
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Mặt hàng</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Giá cũ</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Giá mới</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">% Thay đổi</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Ngày hiệu lực</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-if="props.histories.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">Chưa có lịch sử biến động giá.</td>
                        </tr>
                        <tr
                            v-for="entry in props.histories.data"
                            :key="entry.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900 dark:text-white">{{ entry.item_name }}</p>
                                <p v-if="entry.unit" class="text-xs text-gray-400">{{ entry.unit }}</p>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-500 line-through text-xs">
                                {{ formatCurrency(entry.old_price) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">
                                {{ formatCurrency(entry.new_price) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    v-if="entry.change_pct !== null"
                                    class="inline-flex items-center gap-0.5 font-medium text-xs"
                                    :class="entry.change_pct > 0 ? 'text-red-500' : entry.change_pct < 0 ? 'text-green-500' : 'text-gray-400'"
                                >
                                    <TrendingUp v-if="entry.change_pct > 0" class="size-3" />
                                    <TrendingDown v-else-if="entry.change_pct < 0" class="size-3" />
                                    <Minus v-else class="size-3" />
                                    {{ entry.change_pct > 0 ? '+' : '' }}{{ entry.change_pct }}%
                                </span>
                                <span v-else class="text-gray-400 text-xs">—</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300 text-xs whitespace-nowrap">
                                {{ formatDate(entry.effective_at) }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs max-w-48 truncate">
                                {{ entry.note ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="props.histories.last_page > 1" class="px-4 py-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs text-gray-500">
                <span>Trang {{ props.histories.current_page }} / {{ props.histories.last_page }}</span>
                <div class="flex gap-1">
                    <a
                        v-for="link in props.histories.links"
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
</template>
