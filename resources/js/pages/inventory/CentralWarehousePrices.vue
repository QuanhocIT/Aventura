<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowLeft, DollarSign, Save, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    ingredients: Array<any>;
    canManageWarehouse: boolean;
}>();

const search = ref('');
const isSaving = ref(false);
const rows = ref(props.ingredients.map((ingredient) => ({
    ingredient_id: ingredient.id,
    name: ingredient.name,
    sku: ingredient.sku,
    unit_symbol: ingredient.unit?.symbol || 'đv',
    average_cost: Number(ingredient.average_cost || 0),
})));

const filteredRows = computed(() => rows.value.filter((row) => `${row.name} ${row.sku || ''}`.toLowerCase().includes(search.value.toLowerCase())));

const formatCurrency = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(value || 0));

const savePrices = async () => {
    isSaving.value = true;
    try {
        const response = await axios.post('/api/warehouse/ingredient-prices', {
            prices: rows.value.map((row) => ({ ingredient_id: row.ingredient_id, average_cost: Number(row.average_cost || 0) })),
        });
        toast.success(response.data.message || 'Đã lưu bảng giá nguyên liệu.');
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Không thể lưu bảng giá nguyên liệu.');
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <Head title="Bảng giá nguyên liệu Kho Tổng" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-col justify-between gap-4 rounded-2xl bg-gradient-to-r from-slate-950 via-emerald-950 to-slate-900 p-6 text-white shadow-xl md:flex-row md:items-center">
            <div><Link href="/inventory/central-warehouse" class="mb-3 inline-flex items-center gap-1 text-xs text-emerald-200 hover:text-white"><ArrowLeft class="h-3.5 w-3.5" /> Tổng quan Kho Tổng</Link><div class="flex items-center gap-3"><DollarSign class="h-8 w-8 text-emerald-300" /><div><h1 class="text-2xl font-bold">Bảng giá nguyên liệu</h1><p class="mt-1 text-sm text-emerald-100/75">Đơn giá dùng chung để tính giá trị cấp phát cho các chi nhánh.</p></div></div></div>
            <Button v-if="canManageWarehouse" @click="savePrices" :disabled="isSaving" class="gap-2 bg-emerald-500 text-slate-950 hover:bg-emerald-400"><Save class="h-4 w-4" /> {{ isSaving ? 'Đang lưu...' : 'Lưu bảng giá' }}</Button>
        </div>

        <Card class="border-border shadow-sm">
            <CardHeader class="border-b border-border bg-muted/20 py-4"><div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><CardTitle class="text-base">Danh mục giá Kho Tổng</CardTitle><CardDescription class="mt-1 text-xs">Thay đổi đơn giá sẽ ảnh hưởng tới giá trị các đơn cấp phát mới.</CardDescription></div><div class="relative w-full sm:w-72"><Search class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" /><Input v-model="search" placeholder="Tìm nguyên liệu hoặc SKU..." class="pl-9 text-xs" /></div></div></CardHeader>
            <CardContent class="p-0"><div class="overflow-x-auto"><table class="w-full min-w-[680px] text-left text-xs"><thead class="border-b border-border bg-muted/50 text-muted-foreground"><tr><th class="p-3">Nguyên liệu</th><th class="p-3">SKU</th><th class="p-3 text-center">Đơn vị</th><th class="p-3 text-right">Đơn giá hiện tại</th><th class="p-3 text-right">Giá trị hiển thị</th></tr></thead><tbody class="divide-y divide-border"><tr v-if="filteredRows.length === 0"><td colspan="5" class="p-10 text-center text-muted-foreground">Không tìm thấy nguyên liệu.</td></tr><tr v-for="row in filteredRows" :key="row.ingredient_id" class="hover:bg-muted/20"><td class="p-3 font-semibold text-foreground">{{ row.name }}</td><td class="p-3 font-mono text-muted-foreground">{{ row.sku || '-' }}</td><td class="p-3 text-center text-muted-foreground">{{ row.unit_symbol }}</td><td class="p-3"><Input v-if="canManageWarehouse" v-model.number="row.average_cost" type="number" min="0" step="1000" class="ml-auto h-8 max-w-[180px] text-right text-xs font-bold text-emerald-300" /><span v-else class="block text-right font-semibold">{{ formatCurrency(row.average_cost) }}</span></td><td class="p-3 text-right text-emerald-400">{{ formatCurrency(row.average_cost) }}</td></tr></tbody></table></div></CardContent>
        </Card>
    </div>
</template>
