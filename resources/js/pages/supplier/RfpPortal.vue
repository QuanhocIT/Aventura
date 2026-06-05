<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { 
    Calendar, FileText, Gavel, Award, Building2, 
    Clock, CheckCircle, AlertCircle, Sparkles, X, Plus, Info
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    rfps: any[];
    supplier: any;
}>();

const activeRfpId = ref<number | null>(null);

// Form for bidding
const bidForm = useForm({
    proposed_delivery_date: '',
    notes: '',
    items: [] as Array<{ rfp_item_id: number; proposed_price: number; ingredient_name: string; quantity_required: number; unit_symbol: string }>
});

const openBidForm = (rfp: any) => {
    activeRfpId.value = rfp.id;
    
    // Check if supplier already submitted a bid
    const existingBid = rfp.bids && rfp.bids[0];
    
    bidForm.reset();
    bidForm.proposed_delivery_date = existingBid ? existingBid.proposed_delivery_date.split(' ')[0] : '';
    bidForm.notes = existingBid ? existingBid.notes || '' : '';
    
    bidForm.items = rfp.items.map((item: any) => {
        const existingBidItem = existingBid?.items?.find((bi: any) => bi.rfp_item_id === item.id);
        return {
            rfp_item_id: item.id,
            proposed_price: existingBidItem ? parseFloat(existingBidItem.proposed_price_per_unit) : 0,
            ingredient_name: item.ingredient_name,
            quantity_required: parseFloat(item.quantity_required),
            unit_symbol: item.unit_symbol
        };
    });
};

const calculateTotal = computed(() => {
    return bidForm.items.reduce((sum: number, item: any) => sum + (item.quantity_required * item.proposed_price), 0);
});

const submitBid = (rfpId: number) => {
    bidForm.post(route('supplier.rfps.bid', rfpId), {
        onSuccess: () => {
            activeRfpId.value = null;
            toast.success('Đã nộp hồ sơ báo giá thầu thành công.');
        }
    });
};

const isDueDatePassed = (dueDateString: string) => {
    return new Date() > new Date(dueDateString);
};

const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'open':
            return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-250 dark:border-emerald-900/50 animate-pulse';
        case 'closed':
            return 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-250 dark:border-amber-900/50';
        case 'completed':
            return 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-250 dark:border-blue-900/50';
        default:
            return 'bg-muted text-muted-foreground border border-border';
    }
};

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'open': return 'Đang mở thầu';
        case 'closed': return 'Đã đóng thầu';
        case 'completed': return 'Đã hoàn thành';
        default: return status;
    }
};
</script>

<template>
    <Head title="Cổng Đấu thầu B2B (RFP Portal)" />

    <div class="flex flex-col gap-6 p-4 lg:p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-500">
                    <Gavel class="size-6" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Cổng Đấu thầu Báo giá (RFP Portal)</h1>
                    <p class="text-sm text-muted-foreground">Xem các yêu cầu chào giá thu mua từ phía nhà hàng, nộp báo giá cạnh tranh để giành đơn hàng.</p>
                </div>
            </div>
        </div>

        <!-- RFP Bidding List -->
        <div class="grid grid-cols-1 gap-6">
            <Card 
                v-for="rfp in rfps" 
                :key="rfp.id" 
                class="overflow-hidden hover:shadow-sm transition-all"
            >
                <CardContent class="p-6 space-y-6">
                    <!-- RFP Header Info -->
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b pb-5">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <span :class="['text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-wider', getStatusBadgeClass(rfp.status)]">
                                    {{ getStatusLabel(rfp.status) }}
                                </span>
                                <h3 class="text-base font-bold">#RFP-{{ rfp.id }}: {{ rfp.title }}</h3>
                            </div>
                            <p class="text-xs text-muted-foreground leading-relaxed max-w-4xl">
                                {{ rfp.description || 'Không có mô tả chi tiết từ phía nhà hàng.' }}
                            </p>
                            <div class="flex items-center gap-4 text-[10px] text-muted-foreground font-mono">
                                <span class="flex items-center gap-1">
                                    <Calendar class="w-3.5 h-3.5 text-muted-foreground" />
                                    Hạn nộp thầu: {{ new Date(rfp.due_date).toLocaleString('vi-VN') }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <FileText class="w-3.5 h-3.5 text-muted-foreground" />
                                    {{ rfp.items?.length || 0 }} mặt hàng yêu cầu
                                </span>
                            </div>
                        </div>

                        <!-- Bid Status for this supplier -->
                        <div class="shrink-0 flex items-center gap-3">
                            <!-- If supplier has submitted a bid -->
                            <div v-if="rfp.bids && rfp.bids.length > 0" class="text-right">
                                <span class="text-[10px] text-muted-foreground uppercase tracking-wider block font-bold">Hồ sơ chào thầu của bạn</span>
                                <div class="flex items-center gap-2 mt-1 justify-end">
                                    <span class="text-sm font-black text-emerald-600 dark:text-emerald-400">
                                        {{ Number(rfp.bids[0].total_amount).toLocaleString('vi-VN') }}đ
                                    </span>
                                    <span 
                                        v-if="rfp.bids[0].status === 'accepted'"
                                        class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-450 border border-blue-200 dark:border-blue-900/50"
                                    >
                                        <Award class="w-3 h-3" /> Thắng thầu
                                    </span>
                                    <span 
                                        v-else-if="rfp.bids[0].status === 'rejected'"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-450 border border-rose-200 dark:border-rose-900/50"
                                    >
                                        Từ chối
                                    </span>
                                    <span 
                                        v-else
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-muted text-muted-foreground border border-border"
                                    >
                                        Đã nộp/Đang xét duyệt
                                    </span>
                                </div>
                            </div>

                            <!-- Action buttons -->
                            <Button 
                                v-if="rfp.status === 'open' && !isDueDatePassed(rfp.due_date)"
                                @click="openBidForm(rfp)"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold h-8 text-xs px-3"
                            >
                                {{ rfp.bids && rfp.bids.length > 0 ? 'Sửa báo giá thầu' : 'Nộp báo giá thầu' }}
                            </Button>
                        </div>
                    </div>

                    <!-- Bidding Panel Form (Expanded) -->
                    <div v-if="activeRfpId === rfp.id" class="bg-muted/30 border p-5 rounded-xl space-y-5 animate-in fade-in slide-in-from-top-3 duration-200">
                        <div class="flex items-center justify-between border-b pb-3">
                            <h4 class="text-sm font-bold flex items-center gap-1.5">
                                <Gavel class="w-4.5 h-4.5 text-emerald-600 dark:text-emerald-400" />
                                Kê khai hồ sơ thầu & Đơn giá chào thầu
                            </h4>
                            <Button variant="ghost" size="icon" @click="activeRfpId = null" class="h-8 w-8 text-muted-foreground">
                                <X class="w-4 h-4" />
                            </Button>
                        </div>

                        <form @submit.prevent="submitBid(rfp.id)" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-1.5">
                                    <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Ngày cam kết giao hàng <span class="text-rose-500">*</span></Label>
                                    <Input v-model="bidForm.proposed_delivery_date" required type="date" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Cam kết vận chuyển & Ghi chú thêm</Label>
                                    <Input v-model="bidForm.notes" type="text" placeholder="Ví dụ: Đảm bảo giao trước 8h sáng, xe chuyên dụng..." />
                                </div>
                            </div>

                            <!-- Price grid -->
                            <div class="border rounded-xl overflow-hidden bg-card">
                                <table class="w-full text-xs text-foreground">
                                    <thead class="bg-muted/40 text-muted-foreground uppercase text-[10px] font-semibold border-b">
                                        <tr>
                                            <th class="px-4 py-2 text-left">Tên nguyên liệu yêu cầu</th>
                                            <th class="px-4 py-2 text-center">Số lượng</th>
                                            <th class="px-4 py-2 text-right">Đơn giá chào thầu / Đơn vị <span class="text-rose-500">*</span></th>
                                            <th class="px-4 py-2 text-right">Thành tiền dự tính</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr v-for="(item, idx) in bidForm.items" :key="idx" class="hover:bg-muted/10">
                                            <td class="px-4 py-3 font-semibold">{{ item.ingredient_name }}</td>
                                            <td class="px-4 py-3 text-center font-bold text-muted-foreground">{{ item.quantity_required }} {{ item.unit_symbol }}</td>
                                            <td class="px-4 py-3 text-right">
                                                <div class="inline-flex items-center bg-background border border-input rounded-lg px-2 py-1 focus-within:ring-1 focus-within:ring-emerald-500">
                                                    <input v-model.number="item.proposed_price" required type="number" min="0" class="bg-transparent border-0 text-right w-24 text-xs font-bold text-emerald-600 dark:text-emerald-400 focus:outline-none" />
                                                    <span class="text-[10px] text-muted-foreground ml-1 font-semibold">đ / {{ item.unit_symbol }}</span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-right font-black">
                                                {{ Number(item.quantity_required * item.proposed_price).toLocaleString('vi-VN') }}đ
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Submit Section -->
                            <div class="flex items-center justify-between pt-4 border-t">
                                <div>
                                    <span class="text-xs text-muted-foreground uppercase tracking-wider font-bold block">Tổng giá trị chào thầu:</span>
                                    <span class="text-lg font-black text-emerald-650 dark:text-emerald-400">
                                        {{ Number(calculateTotal).toLocaleString('vi-VN') }}đ
                                    </span>
                                </div>
                                <div class="flex gap-2">
                                    <Button type="button" variant="outline" @click="activeRfpId = null">
                                        Hủy bỏ
                                    </Button>
                                    <Button type="submit" :disabled="bidForm.processing" class="bg-emerald-600 text-white hover:bg-emerald-700 font-bold">
                                        {{ bidForm.processing ? 'Đang nộp...' : 'Xác nhận Nộp thầu' }}
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Bid Details for historical/closed/completed RFPs -->
                    <div v-else-if="rfp.bids && rfp.bids.length > 0" class="bg-muted/20 border p-4 rounded-xl space-y-3">
                        <div class="flex items-center gap-1 text-[11px] text-muted-foreground font-bold uppercase tracking-wider">
                            <Info class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                            Chi tiết báo giá thầu đã gửi
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                            <div class="p-3 bg-card border rounded-lg">
                                <span class="text-[10px] text-muted-foreground block font-bold uppercase">Tổng giá thầu</span>
                                <span class="text-sm font-black text-emerald-600 dark:text-emerald-400 mt-1 block">{{ Number(rfp.bids[0].total_amount).toLocaleString('vi-VN') }}đ</span>
                            </div>
                            <div class="p-3 bg-card border rounded-lg">
                                <span class="text-[10px] text-muted-foreground block font-bold uppercase">Ngày cam kết giao</span>
                                <span class="text-xs font-semibold mt-1 block">{{ new Date(rfp.bids[0].proposed_delivery_date).toLocaleDateString('vi-VN') }}</span>
                            </div>
                            <div class="p-3 bg-card border rounded-lg col-span-2">
                                <span class="text-[10px] text-muted-foreground block font-bold uppercase">Ghi chú & Cam kết</span>
                                <span class="text-xs text-foreground mt-1 block italic">{{ rfp.bids[0].notes || 'Không đính kèm ghi chú.' }}</span>
                            </div>
                        </div>

                        <!-- Items prices -->
                        <div class="border rounded-lg overflow-hidden mt-2 bg-card">
                            <table class="w-full text-[11px] text-foreground">
                                <thead class="bg-muted/40 text-muted-foreground uppercase text-[9px] font-semibold border-b">
                                    <tr>
                                        <th class="px-4 py-1.5 text-left">Tên nguyên liệu</th>
                                        <th class="px-4 py-1.5 text-center">Số lượng</th>
                                        <th class="px-4 py-1.5 text-right">Giá chào thầu / Đơn vị</th>
                                        <th class="px-4 py-1.5 text-right">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-for="bitem in rfp.bids[0].items" :key="bitem.id">
                                        <td class="px-4 py-2 font-medium">{{ bitem.rfp_item?.ingredient_name }}</td>
                                        <td class="px-4 py-2 text-center text-muted-foreground font-bold">{{ bitem.rfp_item?.quantity_required }} {{ bitem.rfp_item?.unit_symbol }}</td>
                                        <td class="px-4 py-2 text-right font-semibold text-emerald-600 dark:text-emerald-400">{{ Number(bitem.proposed_price_per_unit).toLocaleString('vi-VN') }}đ</td>
                                        <td class="px-4 py-2 text-right font-bold">
                                            {{ Number(bitem.rfp_item?.quantity_required * bitem.proposed_price_per_unit).toLocaleString('vi-VN') }}đ
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Empty RFP List -->
            <div v-if="rfps.length === 0" class="py-16 text-center border border-dashed border-border rounded-2xl bg-muted/20">
                <Gavel class="w-12 h-12 text-muted-foreground opacity-60 mx-auto mb-3" />
                <p class="text-muted-foreground font-medium text-sm">Hiện không có yêu cầu chào thầu (RFP) nào khả dụng từ phía nhà hàng.</p>
            </div>
        </div>
    </div>
</template>
