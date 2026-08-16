<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowLeft, ClipboardCheck, PackageCheck, RefreshCw, Warehouse } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    centralBranch: any;
    receivingVouchers: Array<any>;
    receivingSummary: any;
    inventorySummary: any;
    warehouseLocations: Array<any>;
    canManageWarehouse: boolean;
}>();

const vouchers = ref<Array<any>>([...props.receivingVouchers]);
const isProcessing = ref<number | null>(null);
const filter = ref('pending');

const filteredVouchers = computed(() => {
    if (filter.value === 'pending') {
        return vouchers.value.filter((voucher) => ['discrepancy', 'pending_review', 'draft'].includes(voucher.status));
    }

    return vouchers.value;
});

const formatDate = (value: string | null | undefined) => value ? new Date(value).toLocaleString('vi-VN') : '-';
const formatQuantity = (value: number | string | null | undefined) => new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(Number(value || 0));

const statusLabel = (status: string) => ({
    draft: 'Nháp',
    discrepancy: 'Có chênh lệch',
    pending_review: 'Chờ xác minh',
    confirmed: 'Đã xác nhận',
})[status] ?? status;

const confirmVoucher = async (voucher: any) => {
    const notes = window.prompt('Ghi chú xác minh (có thể bỏ trống):', '') ?? '';
    isProcessing.value = voucher.id;

    try {
        await axios.post(`/api/warehouse/receiving-vouchers/${voucher.id}/confirm`, { notes });
        voucher.status = 'confirmed';
        voucher.verified_at = new Date().toISOString();
        toast.success(`Đã xác nhận phiếu ${voucher.voucher_code}. Tồn kho và lô hàng đã được cập nhật.`);
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Không thể xác nhận phiếu nhận hàng.');
    } finally {
        isProcessing.value = null;
    }
};

const reload = () => router.reload({ only: ['receivingVouchers', 'receivingSummary', 'inventorySummary'] });
</script>

<template>
    <Head title="Nhận hàng & GRN Kho Tổng" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-col justify-between gap-4 rounded-2xl bg-gradient-to-r from-slate-950 via-orange-950 to-slate-900 p-6 text-white shadow-xl md:flex-row md:items-center">
            <div>
                <Link href="/inventory/central-warehouse" class="mb-3 inline-flex items-center gap-1 text-xs text-orange-200 hover:text-white"><ArrowLeft class="h-3.5 w-3.5" /> Tổng quan Kho Tổng</Link>
                <div class="flex items-center gap-3"><ClipboardCheck class="h-8 w-8 text-orange-300" /><div><h1 class="text-2xl font-bold">Nhận hàng & GRN</h1><p class="mt-1 text-sm text-orange-100/75">Đối chiếu hàng thực nhận trước khi ghi nhận vào Kho Tổng.</p></div></div>
            </div>
            <div class="flex flex-wrap gap-2"><Link href="/inventory/staff-portal"><Button variant="outline" class="gap-2 border-white/25 bg-white/10 text-white hover:bg-white/20 hover:text-white"><PackageCheck class="h-4 w-4" /> Mở cổng nhân viên nhận hàng</Button></Link><Button @click="reload" variant="outline" class="gap-2 border-white/25 bg-white/10 text-white hover:bg-white/20 hover:text-white"><RefreshCw class="h-4 w-4" /> Làm mới</Button></div>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <Card class="border-orange-500/20 bg-orange-950/10"><CardContent class="p-4"><p class="text-xs text-orange-300">Chờ xử lý</p><p class="mt-2 text-2xl font-bold text-orange-100">{{ receivingSummary.pending_review ?? 0 }}</p></CardContent></Card>
            <Card class="border-emerald-500/20 bg-emerald-950/10"><CardContent class="p-4"><p class="text-xs text-emerald-300">Đã xác nhận</p><p class="mt-2 text-2xl font-bold text-emerald-100">{{ receivingSummary.confirmed ?? 0 }}</p></CardContent></Card>
            <Card class="border-rose-500/20 bg-rose-950/10"><CardContent class="p-4"><p class="text-xs text-rose-300">Chênh lệch</p><p class="mt-2 text-2xl font-bold text-rose-100">{{ formatQuantity(receivingSummary.discrepancy_quantity) }}</p></CardContent></Card>
            <Card class="border-sky-500/20 bg-sky-950/10"><CardContent class="p-4"><p class="text-xs text-sky-300">Tồn sau nhập</p><p class="mt-2 text-2xl font-bold text-sky-100">{{ formatQuantity(inventorySummary.on_hand_quantity) }}</p></CardContent></Card>
            <Card class="border-indigo-500/20 bg-indigo-950/10"><CardContent class="p-4"><p class="text-xs text-indigo-300">Vị trí hoạt động</p><p class="mt-2 text-2xl font-bold text-indigo-100">{{ warehouseLocations.length }}</p></CardContent></Card>
        </div>

        <Card class="border-border shadow-sm">
            <CardHeader class="border-b border-border bg-muted/20 py-4"><div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><CardTitle class="flex items-center gap-2 text-base"><Warehouse class="h-5 w-5 text-orange-300" /> Phiếu nhận hàng tại {{ centralBranch?.name || 'Kho Tổng' }}</CardTitle><CardDescription class="mt-1 text-xs">Chỉ xác nhận sau khi đã đối chiếu số lượng, lô và hạn dùng.</CardDescription></div><select v-model="filter" class="rounded-lg border border-input bg-background px-3 py-2 text-xs text-foreground"><option value="pending">Chờ xử lý</option><option value="all">Tất cả phiếu</option></select></div></CardHeader>
            <CardContent class="p-0"><div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-xs"><thead class="border-b border-border bg-muted/50 text-muted-foreground"><tr><th class="p-3">Mã phiếu</th><th class="p-3">Người nhận</th><th class="p-3">Ngày nhận</th><th class="p-3 text-right">Thực nhận</th><th class="p-3 text-right">Chênh lệch</th><th class="p-3">Trạng thái</th><th class="p-3 text-right">Xử lý</th></tr></thead><tbody class="divide-y divide-border"><tr v-if="filteredVouchers.length === 0"><td colspan="7" class="p-10 text-center text-muted-foreground">Không có phiếu phù hợp.</td></tr><tr v-for="voucher in filteredVouchers" :key="voucher.id" class="hover:bg-muted/20"><td class="p-3 font-mono font-bold text-orange-300">{{ voucher.voucher_code }}</td><td class="p-3 font-medium text-foreground">{{ voucher.received_by?.name || '-' }}</td><td class="p-3 text-muted-foreground">{{ formatDate(voucher.received_at) }}</td><td class="p-3 text-right text-foreground">{{ formatQuantity(voucher.total_actual_qty) }}</td><td class="p-3 text-right font-bold" :class="Number(voucher.total_discrepancy_qty) === 0 ? 'text-emerald-400' : 'text-rose-400'">{{ formatQuantity(Math.abs(Number(voucher.total_discrepancy_qty || 0))) }}</td><td class="p-3"><span class="rounded-full bg-muted px-2 py-1 text-[10px] font-semibold text-muted-foreground">{{ statusLabel(voucher.status) }}</span></td><td class="p-3 text-right"><Button v-if="canManageWarehouse && ['draft', 'discrepancy', 'pending_review'].includes(voucher.status)" @click="confirmVoucher(voucher)" :disabled="isProcessing === voucher.id" size="sm" class="h-7 bg-orange-600 text-[10px] text-white hover:bg-orange-700">{{ isProcessing === voucher.id ? 'Đang lưu...' : 'Xác minh & nhập kho' }}</Button><span v-else class="text-[10px] text-muted-foreground">{{ voucher.verified_at ? 'Đã xác minh' : '-' }}</span></td></tr></tbody></table></div></CardContent>
        </Card>
    </div>
</template>
