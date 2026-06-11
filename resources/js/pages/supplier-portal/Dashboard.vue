<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Package, PackageCheck, TrendingUp, TrendingDown, Minus } from 'lucide-vue-next';

interface PriceChange {
    id: number;
    item_name: string;
    old_price: number;
    new_price: number;
    note: string | null;
    changed_at: string;
}

interface Props {
    supplier: {
        id: number;
        name: string;
        contact_name: string | null;
        email: string | null;
        phone: string | null;
        status: string;
    };
    stats: {
        total_items: number;
        active_items: number;
        recent_price_changes: PriceChange[];
    };
}

const props = defineProps<Props>();

const formatCurrency = (val: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);

const formatDate = (iso: string) =>
    new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso));

const changePct = (oldP: number, newP: number) =>
    oldP > 0 ? (((newP - oldP) / oldP) * 100).toFixed(1) : null;
</script>

<template>
    <Head title="Tổng quan - Cổng nhà cung cấp" />

    <template #header>
        <div>
            <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Tổng quan</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ props.supplier.name }}</p>
        </div>
    </template>

    <div class="space-y-6">
        <!-- Stats cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 flex items-center gap-4">
                <div class="size-10 rounded-lg bg-blue-50 dark:bg-blue-950 flex items-center justify-center">
                    <Package class="size-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ props.stats.total_items }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tổng mặt hàng</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5 flex items-center gap-4">
                <div class="size-10 rounded-lg bg-green-50 dark:bg-green-950 flex items-center justify-center">
                    <PackageCheck class="size-5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ props.stats.active_items }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Đang kinh doanh</p>
                </div>
            </div>
        </div>

        <!-- Recent price changes -->
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Biến động giá gần đây</h2>
            </div>

            <div v-if="props.stats.recent_price_changes.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                Chưa có cập nhật giá nào.
            </div>

            <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                <li
                    v-for="change in props.stats.recent_price_changes"
                    :key="change.id"
                    class="px-5 py-3 flex items-center justify-between gap-4"
                >
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ change.item_name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(change.changed_at) }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="flex items-center gap-1 justify-end">
                            <span class="text-xs text-gray-400 line-through">{{ formatCurrency(change.old_price) }}</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(change.new_price) }}</span>
                        </div>
                        <div
                            class="flex items-center gap-0.5 justify-end text-xs font-medium"
                            :class="change.new_price > change.old_price ? 'text-red-500' : change.new_price < change.old_price ? 'text-green-500' : 'text-gray-400'"
                        >
                            <TrendingUp v-if="change.new_price > change.old_price" class="size-3" />
                            <TrendingDown v-else-if="change.new_price < change.old_price" class="size-3" />
                            <Minus v-else class="size-3" />
                            <span v-if="changePct(change.old_price, change.new_price)">
                                {{ changePct(change.old_price, change.new_price) }}%
                            </span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Supplier info -->
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Thông tin nhà cung cấp</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <div v-if="props.supplier.contact_name" class="flex gap-2">
                    <dt class="text-gray-500 shrink-0">Người liên hệ:</dt>
                    <dd class="text-gray-900 dark:text-white font-medium">{{ props.supplier.contact_name }}</dd>
                </div>
                <div v-if="props.supplier.phone" class="flex gap-2">
                    <dt class="text-gray-500 shrink-0">Điện thoại:</dt>
                    <dd class="text-gray-900 dark:text-white font-medium">{{ props.supplier.phone }}</dd>
                </div>
                <div v-if="props.supplier.email" class="flex gap-2">
                    <dt class="text-gray-500 shrink-0">Email:</dt>
                    <dd class="text-gray-900 dark:text-white font-medium">{{ props.supplier.email }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-gray-500 shrink-0">Trạng thái:</dt>
                    <dd>
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="props.supplier.status === 'active'
                                ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'"
                        >
                            {{ props.supplier.status === 'active' ? 'Đang hoạt động' : 'Tạm ngưng' }}
                        </span>
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</template>
