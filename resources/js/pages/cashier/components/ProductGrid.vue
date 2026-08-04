<script setup lang="ts">
import { Search, Plus, Minus, Lock } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import type { ProductItem, CategoryItem } from '../types';

const props = defineProps<{
    products: ProductItem[];
    categories: CategoryItem[];
    getCartItemQty: (productId: number) => number;
    compact?: boolean;
}>();

const emit = defineEmits<{
    (e: 'clickProduct', product: ProductItem): void;
    (e: 'increaseQty', productId: number): void;
    (e: 'decreaseQty', productId: number): void;
}>();

const searchQuery = ref('');
const selectedCategoryId = ref<number | 'all'>('all');

const filteredProducts = computed(() => {
    return props.products.filter((p) => {
        const matchesCategory =
            selectedCategoryId.value === 'all' ||
            p.category_id === selectedCategoryId.value;
        const matchesSearch =
            !searchQuery.value.trim() ||
            p.name
                .toLowerCase()
                .includes(searchQuery.value.toLowerCase().trim());

        return matchesCategory && matchesSearch;
    });
});

const numberFormat = (val: number) =>
    new Intl.NumberFormat('vi-VN').format(val);
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Thanh tìm kiếm & lọc danh mục -->
        <div
            :class="
                props.compact
                    ? 'flex flex-col gap-3'
                    : 'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'
            "
        >
            <div class="relative flex-1">
                <Search
                    class="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400"
                />
                <Input
                    type="text"
                    placeholder="Tìm kiếm món ăn, đồ uống..."
                    v-model="searchQuery"
                    class="h-10 rounded-2xl bg-white pl-10 text-xs shadow-sm dark:bg-slate-900"
                />
            </div>

            <div
                class="flex flex-wrap items-center gap-1.5 overflow-x-auto pb-1"
            >
                <button
                    @click="selectedCategoryId = 'all'"
                    class="rounded-xl px-3.5 py-2 text-xs font-bold transition-all"
                    :class="
                        selectedCategoryId === 'all'
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300'
                    "
                >
                    Tất cả
                </button>

                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    @click="selectedCategoryId = cat.id"
                    class="rounded-xl px-3.5 py-2 text-xs font-bold transition-all"
                    :class="
                        selectedCategoryId === cat.id
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                            : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300'
                    "
                >
                    {{ cat.name }}
                </button>
            </div>
        </div>

        <!-- Grid danh sách món ăn -->
        <div
            :class="
                props.compact
                    ? 'grid grid-cols-2 gap-3'
                    : 'grid grid-cols-2 gap-3.5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7'
            "
        >
            <div
                v-for="product in filteredProducts"
                :key="product.id"
                @click="
                    !product.is_paused &&
                    !product.is_out_of_stock &&
                    emit('clickProduct', product)
                "
                class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                :class="{
                    'cursor-not-allowed opacity-60':
                        product.is_paused || product.is_out_of_stock,
                    'cursor-pointer':
                        !product.is_paused && !product.is_out_of_stock,
                }"
            >
                <div class="flex flex-col text-left">
                    <h5
                        class="line-clamp-2 text-xs font-bold text-slate-800 dark:text-slate-100"
                    >
                        {{ product.name }}
                    </h5>
                    <span
                        class="mt-1 font-mono text-sm font-black text-indigo-600 dark:text-indigo-400"
                    >
                        {{ numberFormat(product.price) }}đ
                    </span>
                </div>

                <!-- Tạm ngưng / Hết hàng Overlay -->
                <div
                    v-if="product.is_paused || product.is_out_of_stock"
                    class="mt-2 flex items-center justify-center gap-1 rounded-xl bg-slate-100 py-1.5 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                >
                    <Lock class="size-3 text-amber-500" />
                    {{
                        product.is_out_of_stock
                            ? 'Tạm hết món'
                            : 'Tạm ngưng bán'
                    }}
                </div>

                <!-- Nút cộng giảm số lượng khi món đang có trong giỏ -->
                <div v-else class="mt-3 flex items-center justify-between">
                    <template v-if="getCartItemQty(product.id) > 0">
                        <div
                            class="flex items-center gap-1.5 rounded-xl bg-indigo-50 p-1 dark:bg-indigo-950/40"
                        >
                            <button
                                @click.stop="emit('decreaseQty', product.id)"
                                class="flex size-6 items-center justify-center rounded-lg bg-white text-indigo-600 shadow-sm transition-transform active:scale-95 dark:bg-slate-800"
                            >
                                <Minus class="size-3" />
                            </button>
                            <span
                                class="w-4 text-center font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400"
                            >
                                {{ getCartItemQty(product.id) }}
                            </span>
                            <button
                                @click.stop="emit('increaseQty', product.id)"
                                class="flex size-6 items-center justify-center rounded-lg bg-indigo-600 text-white shadow-sm transition-transform active:scale-95"
                            >
                                <Plus class="size-3" />
                            </button>
                        </div>
                    </template>

                    <template v-else>
                        <button
                            class="ml-auto flex size-7 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 transition-colors group-hover:bg-indigo-600 group-hover:text-white dark:bg-slate-800"
                        >
                            <Plus class="size-4" />
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
