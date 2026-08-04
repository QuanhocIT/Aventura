<script setup lang="ts">
import { Users, Utensils, AlertTriangle } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import type { TableItem } from '../types';

type TableStatusFilter = 'all' | 'occupied' | 'available';

const props = defineProps<{
    tables: TableItem[];
    areaList: string[];
    selectedArea: string;
    selectedStatus: TableStatusFilter;
    activeTableId?: number;
    compact?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:selectedArea', area: string): void;
    (e: 'update:selectedStatus', status: TableStatusFilter): void;
    (e: 'selectTable', table: TableItem): void;
}>();

const statusFilters: Array<{
    value: TableStatusFilter;
    label: string;
}> = [
    { value: 'all', label: 'Tất cả' },
    { value: 'occupied', label: 'Có khách' },
    { value: 'available', label: 'Bàn trống' },
];

const numberFormat = (val: number) =>
    new Intl.NumberFormat('vi-VN').format(val);

const getStatusBadge = (status: TableItem['status']) => {
    switch (status) {
        case 'available':
            return {
                label: 'Bàn Trống',
                class: 'bg-emerald-500/15 text-emerald-600 dark:bg-emerald-500/20',
            };
        case 'occupied':
            return {
                label: 'Có Khách',
                class: 'bg-rose-500/15 text-rose-600 dark:bg-rose-500/20',
            };
        case 'reserved':
            return {
                label: 'Đã Đặt',
                class: 'bg-amber-500/15 text-amber-600 dark:bg-amber-500/20',
            };
        default:
            return {
                label: 'Dọn dẹp/Tắt',
                class: 'bg-slate-500/15 text-slate-600 dark:bg-slate-500/20',
            };
    }
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Bộ lọc khu vực -->
        <div class="flex flex-wrap items-center gap-2">
            <button
                @click="emit('update:selectedArea', 'all')"
                class="rounded-xl px-4 py-2 text-xs font-bold transition-all"
                :class="
                    selectedArea === 'all'
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                        : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300'
                "
            >
                Tất cả khu vực
            </button>

            <button
                v-for="area in areaList"
                :key="area"
                @click="emit('update:selectedArea', area)"
                class="rounded-xl px-4 py-2 text-xs font-bold transition-all"
                :class="
                    selectedArea === area
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                        : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300'
                "
            >
                {{ area }}
            </button>
        </div>

        <div
            class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-slate-800"
        >
            <button
                v-for="filter in statusFilters"
                :key="filter.value"
                type="button"
                @click="emit('update:selectedStatus', filter.value)"
                class="rounded-xl px-4 py-2 text-xs font-bold transition-all"
                :class="
                    selectedStatus === filter.value
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                        : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300'
                "
            >
                {{ filter.label }}
            </button>
        </div>

        <!-- Grid sơ đồ bàn -->
        <div
            :class="
                props.compact
                    ? 'grid grid-cols-2 gap-3 sm:grid-cols-3'
                    : 'grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7'
            "
        >
            <div
                v-for="table in tables"
                :key="table.id"
                @click="emit('selectTable', table)"
                class="group relative flex cursor-pointer flex-col justify-between overflow-hidden rounded-2xl border p-4 transition-all duration-200 hover:-translate-y-1 hover:shadow-lg"
                :class="[
                    activeTableId === table.id
                        ? 'border-indigo-600 bg-indigo-50/50 shadow-md ring-2 ring-indigo-600/30 dark:bg-indigo-950/20'
                        : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900',
                ]"
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-9 items-center justify-center rounded-xl font-bold transition-colors"
                            :class="
                                table.status === 'occupied'
                                    ? 'bg-rose-100 text-rose-600 dark:bg-rose-950/40'
                                    : table.status === 'reserved'
                                      ? 'bg-amber-100 text-amber-600 dark:bg-amber-950/40'
                                      : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/40'
                            "
                        >
                            <Utensils class="size-4" />
                        </div>
                        <div class="text-left">
                            <h4
                                class="font-black text-slate-800 dark:text-slate-100"
                            >
                                Bàn {{ table.name }}
                            </h4>
                            <span class="text-[10px] text-muted-foreground">{{
                                table.area || 'Chung'
                            }}</span>
                        </div>
                    </div>

                    <Badge
                        variant="secondary"
                        class="border-0 text-[10px] font-bold"
                        :class="getStatusBadge(table.status).class"
                    >
                        {{ getStatusBadge(table.status).label }}
                    </Badge>
                </div>

                <div
                    class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs dark:border-slate-800"
                >
                    <div class="flex items-center gap-1 text-slate-500">
                        <Users class="size-3.5" />
                        <span class="text-[11px] font-medium"
                            >{{ table.capacity }} chỗ</span
                        >
                    </div>

                    <div v-if="table.active_order" class="text-right">
                        <span
                            class="block font-mono text-xs font-black text-indigo-600 dark:text-indigo-400"
                        >
                            {{ numberFormat(table.active_order.total_amount) }}đ
                        </span>
                    </div>
                </div>

                <!-- Split Order / Red Flag Badge -->
                <div
                    v-if="
                        table.active_order?.is_split ||
                        table.active_order?.is_red_flagged
                    "
                    class="absolute top-1.5 right-1.5 flex gap-1"
                >
                    <span
                        v-if="table.active_order.is_split"
                        class="rounded-full bg-indigo-100 px-1.5 py-0.5 text-[9px] font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                    >
                        Tách
                    </span>
                    <span
                        v-if="table.active_order.is_red_flagged"
                        class="flex items-center gap-0.5 rounded-full bg-rose-100 px-1.5 py-0.5 text-[9px] font-bold text-rose-700 dark:bg-rose-950 dark:text-rose-300"
                        title="Đơn hàng bất thường"
                    >
                        <AlertTriangle class="size-2.5" /> Chú ý
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
