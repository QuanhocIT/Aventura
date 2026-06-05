<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { 
    Calendar, FileText, Gavel, Award, Building2, 
    Clock, CheckCircle, AlertCircle, Sparkles, X, Plus, Info
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

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
            return 'bg-emerald-950/50 text-emerald-400 border border-emerald-900/50';
        case 'closed':
            return 'bg-amber-950/50 text-amber-400 border border-amber-900/50';
        case 'completed':
            return 'bg-blue-950/50 text-blue-400 border border-blue-900/50';
        default:
            return 'bg-slate-800 text-slate-400';
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

    <div class="px-6 py-8 max-w-7xl mx-auto space-y-6 text-slate-100">
        <!-- Header -->
        <div class="border-b border-slate-800 pb-6">
            <h1 class="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-transparent">
                Cổng Đấu thầu Báo giá (RFP Portal)
            </h1>
            <p class="text-sm text-slate-400 mt-1">
                Xem các yêu cầu chào giá thu mua từ phía nhà hàng, nộp báo giá cạnh tranh để giành đơn hàng.
            </p>
        </div>

        <!-- RFP Bidding List -->
        <div class="grid grid-cols-1 gap-6">
            <div 
                v-for="rfp in rfps" 
                :key="rfp.id" 
                class="bg-slate-900/40 border border-slate-850 rounded-xl overflow-hidden backdrop-blur-sm p-6 space-y-6"
            >
                <!-- RFP Header Info -->
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-800/60 pb-5">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span :class="['text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-wider', getStatusBadgeClass(rfp.status)]">
                                {{ getStatusLabel(rfp.status) }}
                            </span>
                            <h3 class="text-lg font-bold text-slate-200">#RFP-{{ rfp.id }}: {{ rfp.title }}</h3>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed max-w-4xl">
                            {{ rfp.description || 'Không có mô tả chi tiết từ phía nhà hàng.' }}
                        </p>
                        <div class="flex items-center gap-4 text-[10px] text-slate-500 font-mono">
                            <span class="flex items-center gap-1">
                                <Calendar class="w-3.5 h-3.5 text-slate-500" />
                                Hạn nộp thầu: {{ new Date(rfp.due_date).toLocaleString('vi-VN') }}
                            </span>
                            <span class="flex items-center gap-1">
                                <FileText class="w-3.5 h-3.5 text-slate-500" />
                                {{ rfp.items?.length || 0 }} mặt hàng yêu cầu
                            </span>
                        </div>
                    </div>

                    <!-- Bid Status for this supplier -->
                    <div class="shrink-0 flex items-center gap-3">
                        <!-- If supplier has submitted a bid -->
                        <div v-if="rfp.bids && rfp.bids.length > 0" class="text-right">
                            <span class="text-[10px] text-slate-500 uppercase tracking-wider block font-bold">Hồ sơ chào thầu của bạn</span>
                            <div class="flex items-center gap-2 mt-1 justify-end">
                                <span class="text-sm font-black text-emerald-400">
                                    {{ Number(rfp.bids[0].total_amount).toLocaleString('vi-VN') }}đ
                                </span>
                                <span 
                                    v-if="rfp.bids[0].status === 'accepted'"
                                    class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-blue-950 text-blue-400 border border-blue-900"
                                >
                                    <Award class="w-3 h-3" /> Thắng thầu
                                </span>
                                <span 
                                    v-else-if="rfp.bids[0].status === 'rejected'"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-rose-950/60 text-rose-400 border border-rose-900/40"
                                >
                                    Từ chối
                                </span>
                                <span 
                                    v-else
                                    class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-slate-950 text-slate-400 border border-slate-850"
                                >
                                    Đã nộp/Đang xét duyệt
                                </span>
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <button 
                            v-if="rfp.status === 'open' && !isDueDatePassed(rfp.due_date)"
                            @click="openBidForm(rfp)"
                            class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white rounded-lg text-xs font-bold transition-all active:scale-95 shadow-md shadow-emerald-950/30"
                        >
                            {{ rfp.bids && rfp.bids.length > 0 ? 'Sửa báo giá thầu' : 'Nộp báo giá thầu' }}
                        </button>
                    </div>
                </div>

                <!-- Bidding Panel Form (Expanded) -->
                <div v-if="activeRfpId === rfp.id" class="bg-slate-950/35 border border-slate-850 p-5 rounded-xl space-y-5 animate-in fade-in slide-in-from-top-3 duration-200">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                        <h4 class="text-sm font-bold text-slate-200 flex items-center gap-1.5">
                            <Gavel class="w-4.5 h-4.5 text-emerald-400" />
                            Kê khai hồ sơ thầu & Đơn giá chào thầu
                        </h4>
                        <button @click="activeRfpId = null" class="p-1 text-slate-400 hover:text-slate-250 rounded hover:bg-slate-850 transition-colors">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form @submit.prevent="submitBid(rfp.id)" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Ngày cam kết giao hàng <span class="text-rose-500">*</span></label>
                                <input v-model="bidForm.proposed_delivery_date" required type="date" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-250 focus:outline-none focus:border-emerald-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Cam kết vận chuyển & Ghi chú thêm</label>
                                <input v-model="bidForm.notes" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-250 focus:outline-none focus:border-emerald-500" placeholder="Ví dụ: Đảm bảo giao trước 8h sáng, xe chuyên dụng..." />
                            </div>
                        </div>

                        <!-- Price grid -->
                        <div class="border border-slate-850 rounded-xl overflow-hidden">
                            <table class="w-full text-xs text-slate-300">
                                <thead class="bg-slate-950/60 text-slate-450 uppercase text-[10px] border-b border-slate-850">
                                    <tr>
                                        <th class="px-4 py-2 text-left">Tên nguyên liệu yêu cầu</th>
                                        <th class="px-4 py-2 text-center">Số lượng</th>
                                        <th class="px-4 py-2 text-right">Đơn giá chào thầu / Đơn vị <span class="text-rose-500">*</span></th>
                                        <th class="px-4 py-2 text-right">Thành tiền dự tính</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-850/50">
                                    <tr v-for="(item, idx) in bidForm.items" :key="idx" class="hover:bg-slate-950/20">
                                        <td class="px-4 py-3 font-semibold text-slate-200">{{ item.ingredient_name }}</td>
                                        <td class="px-4 py-3 text-center font-bold text-slate-400">{{ item.quantity_required }} {{ item.unit_symbol }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="inline-flex items-center bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 focus-within:border-emerald-500">
                                                <input v-model.number="item.proposed_price" required type="number" min="0" class="bg-transparent border-0 text-right w-24 text-xs font-bold text-emerald-400 focus:outline-none" />
                                                <span class="text-[10px] text-slate-500 ml-1">đ / {{ item.unit_symbol }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right font-black text-slate-300">
                                            {{ Number(item.quantity_required * item.proposed_price).toLocaleString('vi-VN') }}đ
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Submit Section -->
                        <div class="flex items-center justify-between pt-4 border-t border-slate-800">
                            <div>
                                <span class="text-xs text-slate-500 uppercase tracking-wider font-bold block">Tổng giá trị chào thầu:</span>
                                <span class="text-lg font-black text-emerald-450">
                                    {{ Number(calculateTotal).toLocaleString('vi-VN') }}đ
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="activeRfpId = null" class="px-4 py-2 border border-slate-800 text-sm font-semibold rounded-lg text-slate-400 hover:text-slate-200 transition-colors">
                                    Hủy bỏ
                                </button>
                                <button type="submit" :disabled="bidForm.processing" class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-500 text-white rounded-lg text-sm font-bold hover:from-emerald-500 hover:to-teal-400 transition-all shadow-md shadow-emerald-950/30">
                                    {{ bidForm.processing ? 'Đang gửi...' : 'Xác nhận Nộp thầu' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Bid Details for historical/closed/completed RFPs -->
                <div v-else-if="rfp.bids && rfp.bids.length > 0" class="bg-slate-950/20 border border-slate-850/60 p-4 rounded-xl space-y-3">
                    <div class="flex items-center gap-1 text-[11px] text-slate-400 font-bold uppercase tracking-wider">
                        <Info class="w-3.5 h-3.5 text-emerald-400" />
                        Chi tiết báo giá thầu đã gửi
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                        <div class="p-3 bg-slate-900/50 border border-slate-850/40 rounded-lg">
                            <span class="text-[10px] text-slate-550 block font-bold uppercase">Tổng giá thầu</span>
                            <span class="text-sm font-black text-emerald-400 mt-1 block">{{ Number(rfp.bids[0].total_amount).toLocaleString('vi-VN') }}đ</span>
                        </div>
                        <div class="p-3 bg-slate-900/50 border border-slate-850/40 rounded-lg">
                            <span class="text-[10px] text-slate-550 block font-bold uppercase">Ngày cam kết giao</span>
                            <span class="text-xs text-slate-300 font-semibold mt-1 block">{{ new Date(rfp.bids[0].proposed_delivery_date).toLocaleDateString('vi-VN') }}</span>
                        </div>
                        <div class="p-3 bg-slate-900/50 border border-slate-850/40 rounded-lg col-span-2">
                            <span class="text-[10px] text-slate-550 block font-bold uppercase">Ghi chú & Cam kết</span>
                            <span class="text-xs text-slate-350 mt-1 block italic">{{ rfp.bids[0].notes || 'Không đính kèm ghi chú.' }}</span>
                        </div>
                    </div>

                    <!-- Items prices -->
                    <div class="border border-slate-850/40 rounded-lg overflow-hidden mt-2">
                        <table class="w-full text-[11px] text-slate-400">
                            <thead class="bg-slate-950/40 text-slate-500 uppercase text-[9px] border-b border-slate-850/40">
                                <tr>
                                    <th class="px-4 py-1.5 text-left">Tên nguyên liệu</th>
                                    <th class="px-4 py-1.5 text-center">Số lượng</th>
                                    <th class="px-4 py-1.5 text-right">Giá chào thầu / Đơn vị</th>
                                    <th class="px-4 py-1.5 text-right">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-850/30">
                                <tr v-for="bitem in rfp.bids[0].items" :key="bitem.id">
                                    <td class="px-4 py-2 font-medium text-slate-300">{{ bitem.rfp_item?.ingredient_name }}</td>
                                    <td class="px-4 py-2 text-center font-bold text-slate-400">{{ bitem.rfp_item?.quantity_required }} {{ bitem.rfp_item?.unit_symbol }}</td>
                                    <td class="px-4 py-2 text-right font-semibold text-emerald-400">{{ Number(bitem.proposed_price_per_unit).toLocaleString('vi-VN') }}đ</td>
                                    <td class="px-4 py-2 text-right font-bold text-slate-200">
                                        {{ Number(bitem.rfp_item?.quantity_required * bitem.proposed_price_per_unit).toLocaleString('vi-VN') }}đ
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Empty RFP List -->
            <div v-if="rfps.length === 0" class="py-16 text-center bg-slate-900/20 border border-dashed border-slate-800 rounded-2xl">
                <Gavel class="w-12 h-12 text-slate-600 mx-auto mb-3" />
                <p class="text-slate-400 font-medium">Hiện không có yêu cầu chào thầu (RFP) nào khả dụng từ phía nhà hàng.</p>
            </div>
        </div>
    </div>
</template>
