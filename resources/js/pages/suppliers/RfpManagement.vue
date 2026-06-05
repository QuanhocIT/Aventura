<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { 
    Plus, ChevronDown, ChevronUp, Check, X, 
    Calendar, FileText, Gavel, Award, Building2, 
    Clock, ShieldAlert, AlertCircle, ShoppingCart
} from 'lucide-vue-next';

const props = defineProps<{
    rfps: any[];
}>();

const page = usePage();
const roles = computed(() => {
    const raw = page.props.roles ?? [];
    return Array.isArray(raw) ? raw : Object.values(raw as Record<string, string>);
});
const isOwner = computed(() => roles.value.includes('owner'));

// Modal states
const showCreateModal = ref(false);
const activeRfpId = ref<number | null>(null);
const activeBidId = ref<number | null>(null);

// Form for creating RFP
const rfpForm = useForm({
    title: '',
    description: '',
    due_date: '',
    items: [
        { ingredient_name: '', quantity_required: 1, unit_symbol: 'kg', notes: '' }
    ]
});

const addRfpItem = () => {
    rfpForm.items.push({ ingredient_name: '', quantity_required: 1, unit_symbol: 'kg', notes: '' });
};

const removeRfpItem = (index: number | string) => {
    rfpForm.items.splice(Number(index), 1);
};

const submitRfp = () => {
    rfpForm.post(route('rfps.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            rfpForm.reset();
        }
    });
};

const closeRfp = (rfpId: number) => {
    if (confirm('Bạn có chắc chắn muốn đóng thầu sớm? Các nhà cung cấp sẽ không thể gửi thêm báo giá.')) {
        router.post(route('rfps.close', rfpId));
    }
};

const acceptBid = (bidId: number) => {
    if (confirm('Bạn có chắc chắn chọn hồ sơ báo giá này làm nhà thầu chiến thắng? Hệ thống sẽ tự động tạo đơn hàng PO tương ứng và gửi đi.')) {
        router.post(route('rfps.bids.accept', bidId));
    }
};

const toggleRfpDetails = (id: number) => {
    activeRfpId.value = activeRfpId.value === id ? null : id;
};

const toggleBidDetails = (id: number) => {
    activeBidId.value = activeBidId.value === id ? null : id;
};

const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'open':
            return 'bg-emerald-950/50 text-emerald-400 border border-emerald-900/50 animate-pulse';
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
    <Head title="Quản lý Đấu thầu Báo giá RFP" />

    <div class="px-6 py-8 max-w-7xl mx-auto space-y-6 text-slate-100">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-6">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-transparent">
                    Quản lý Đấu thầu Báo giá (RFP)
                </h1>
                <p class="text-sm text-slate-400 mt-1">
                    Đăng yêu cầu thu mua công khai, kêu gọi báo giá cạnh tranh từ các nhà cung cấp nhằm tối ưu hóa chi phí và triệt tiêu tiêu cực.
                </p>
            </div>
            <button 
                @click="showCreateModal = true"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white rounded-lg font-semibold shadow-lg shadow-emerald-950/30 transition-all active:scale-95 shrink-0"
            >
                <Plus class="w-5 h-5" />
                Tạo yêu cầu RFP mới
            </button>
        </div>

        <!-- RFP List -->
        <div class="space-y-4">
            <div 
                v-for="rfp in rfps" 
                :key="rfp.id" 
                class="bg-slate-900/40 border border-slate-850 rounded-xl overflow-hidden backdrop-blur-sm transition-all hover:border-slate-800"
            >
                <!-- RFP Accordion Header -->
                <div 
                    @click="toggleRfpDetails(rfp.id)"
                    class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 cursor-pointer hover:bg-slate-900/30"
                >
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span :class="['text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-wider', getStatusBadgeClass(rfp.status)]">
                                {{ getStatusLabel(rfp.status) }}
                            </span>
                            <h3 class="text-lg font-bold text-slate-200">#RFP-{{ rfp.id }}: {{ rfp.title }}</h3>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed max-w-3xl line-clamp-1">
                            {{ rfp.description || 'Không có mô tả chi tiết.' }}
                        </p>
                        <div class="flex items-center gap-4 text-[10px] text-slate-500 font-mono">
                            <span class="flex items-center gap-1">
                                <Calendar class="w-3.5 h-3.5 text-slate-500" />
                                Hạn nộp: {{ new Date(rfp.due_date).toLocaleString('vi-VN') }}
                            </span>
                            <span class="flex items-center gap-1">
                                <FileText class="w-3.5 h-3.5 text-slate-500" />
                                {{ rfp.items?.length || 0 }} mặt hàng
                            </span>
                            <span class="flex items-center gap-1 text-emerald-400">
                                <Gavel class="w-3.5 h-3.5" />
                                {{ rfp.bids?.length || 0 }} hồ sơ nộp thầu
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 self-end md:self-auto shrink-0">
                        <button 
                            v-if="rfp.status === 'open'"
                            @click.stop="closeRfp(rfp.id)"
                            class="px-3 py-1.5 bg-slate-950 border border-slate-800 text-xs font-semibold rounded hover:bg-slate-900 text-amber-400 hover:text-amber-300 transition-colors"
                        >
                            Đóng thầu sớm
                        </button>
                        <div class="p-1 text-slate-400 hover:text-slate-200 hover:bg-slate-800 rounded transition-colors">
                            <ChevronDown v-if="activeRfpId !== rfp.id" class="w-5 h-5" />
                            <ChevronUp v-else class="w-5 h-5" />
                        </div>
                    </div>
                </div>

                <!-- RFP Accordion Content -->
                <div v-if="activeRfpId === rfp.id" class="border-t border-slate-850 p-5 bg-slate-950/20 space-y-6">
                    <!-- Required Items -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                            <FileText class="w-4 h-4 text-emerald-400" />
                            Danh sách nguyên liệu yêu cầu
                        </h4>
                        <div class="border border-slate-850 rounded-lg overflow-hidden bg-slate-900/30">
                            <table class="w-full text-xs text-slate-300">
                                <thead class="bg-slate-950/50 text-slate-400 uppercase text-[10px] border-b border-slate-850">
                                    <tr>
                                        <th class="px-4 py-2 text-left">Tên nguyên liệu</th>
                                        <th class="px-4 py-2 text-center">Số lượng yêu cầu</th>
                                        <th class="px-4 py-2 text-left">Ghi chú quy cách</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-850">
                                    <tr v-for="item in rfp.items" :key="item.id">
                                        <td class="px-4 py-2.5 font-bold text-slate-250">{{ item.ingredient_name }}</td>
                                        <td class="px-4 py-2.5 text-center font-bold text-emerald-400">{{ Number(item.quantity_required).toLocaleString() }} {{ item.unit_symbol }}</td>
                                        <td class="px-4 py-2.5 text-slate-400">{{ item.notes || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Bids Section -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                            <Gavel class="w-4 h-4 text-emerald-400" />
                            Hồ sơ chào thầu đã nhận
                        </h4>
                        
                        <div v-if="rfp.bids && rfp.bids.length > 0" class="space-y-3">
                            <div 
                                v-for="bid in rfp.bids" 
                                :key="bid.id"
                                class="bg-slate-900/60 border border-slate-850 rounded-xl p-4 space-y-4"
                            >
                                <!-- Bid Summary -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 bg-emerald-950 text-emerald-400 border border-emerald-900/50 rounded-lg">
                                            <Building2 class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h5 class="text-sm font-bold text-slate-200">{{ bid.supplier?.name }}</h5>
                                                <span 
                                                    v-if="bid.status === 'accepted'"
                                                    class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide bg-blue-950 text-blue-400 border border-blue-900"
                                                >
                                                    <Award class="w-2.5 h-2.5" /> Thắng thầu (PO đã tạo)
                                                </span>
                                                <span 
                                                    v-else-if="bid.status === 'rejected'"
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide bg-slate-950 text-slate-500 border border-slate-850"
                                                >
                                                    Từ chối
                                                </span>
                                            </div>
                                            <p class="text-[10px] text-slate-400 mt-0.5 flex items-center gap-1">
                                                <Clock class="w-3.5 h-3.5 text-slate-500" />
                                                Cam kết giao: {{ new Date(bid.proposed_delivery_date).toLocaleDateString('vi-VN') }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4 self-end sm:self-auto">
                                        <div class="text-right">
                                            <span class="text-[10px] text-slate-500 block uppercase tracking-wider font-bold">Tổng tiền chào thầu</span>
                                            <span class="text-md font-black text-emerald-400">
                                                {{ Number(bid.total_amount).toLocaleString('vi-VN') }}đ
                                            </span>
                                        </div>

                                        <div class="flex gap-2">
                                            <button 
                                                @click="toggleBidDetails(bid.id)"
                                                class="px-2.5 py-1.5 bg-slate-950 border border-slate-800 text-xs font-semibold rounded hover:bg-slate-900 text-slate-300 hover:text-slate-100 transition-colors"
                                            >
                                                {{ activeBidId === bid.id ? 'Ẩn chi tiết' : 'Chi tiết giá' }}
                                            </button>
                                            <button 
                                                v-if="rfp.status !== 'completed' && isOwner"
                                                @click="acceptBid(bid.id)"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-xs font-bold rounded text-white shadow-md transition-all active:scale-95"
                                            >
                                                <Check class="w-3.5 h-3.5" /> Chọn Thắng Thầu
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bid details (mặt hàng chi tiết) -->
                                <div v-if="activeBidId === bid.id" class="border-t border-slate-800/60 pt-3 mt-3 animate-in fade-in slide-in-from-top-2 duration-150">
                                    <div class="bg-slate-950/40 rounded-lg border border-slate-850/60 overflow-hidden">
                                        <table class="w-full text-[11px] text-slate-300">
                                            <thead class="bg-slate-950/60 text-slate-500 uppercase text-[9px] border-b border-slate-850">
                                                <tr>
                                                    <th class="px-4 py-1.5 text-left">Tên nguyên liệu</th>
                                                    <th class="px-4 py-1.5 text-center">Số lượng</th>
                                                    <th class="px-4 py-1.5 text-right">Giá chào thầu / Đơn vị</th>
                                                    <th class="px-4 py-1.5 text-right">Tổng cộng</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-850/50">
                                                <tr v-for="bitem in bid.items" :key="bitem.id">
                                                    <td class="px-4 py-2 font-semibold text-slate-300">{{ bitem.rfp_item?.ingredient_name }}</td>
                                                    <td class="px-4 py-2 text-center text-slate-400 font-mono">{{ bitem.rfp_item?.quantity_required }} {{ bitem.rfp_item?.unit_symbol }}</td>
                                                    <td class="px-4 py-2 text-right font-mono text-emerald-400">{{ Number(bitem.proposed_price_per_unit).toLocaleString('vi-VN') }}đ</td>
                                                    <td class="px-4 py-2 text-right font-semibold font-mono text-slate-200">
                                                        {{ Number(bitem.rfp_item?.quantity_required * bitem.proposed_price_per_unit).toLocaleString('vi-VN') }}đ
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div v-if="bid.notes" class="mt-2.5 p-2.5 bg-slate-950/30 border border-slate-850/50 rounded-lg text-xs">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase block">Cam kết & Ghi chú từ đối tác</span>
                                        <p class="text-slate-300 mt-0.5 leading-relaxed">{{ bid.notes }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-8 bg-slate-900/10 border border-dashed border-slate-850 rounded-xl">
                            <AlertCircle class="w-8 h-8 text-slate-650 mx-auto mb-2" />
                            <p class="text-xs text-slate-500 font-medium">Chưa có nhà cung cấp nào nộp hồ sơ thầu.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty RFP State -->
            <div v-if="rfps.length === 0" class="py-16 text-center bg-slate-900/20 border border-dashed border-slate-800 rounded-2xl">
                <Gavel class="w-12 h-12 text-slate-600 mx-auto mb-3" />
                <p class="text-slate-400 font-medium">Chưa có yêu cầu chào thầu (RFP) nào được tạo.</p>
                <button @click="showCreateModal = true" class="mt-4 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg font-semibold transition-all">Tạo yêu cầu RFP đầu tiên</button>
            </div>
        </div>

        <!-- Create RFP Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 bg-slate-950 flex items-center justify-between border-b border-slate-800">
                    <h3 class="font-bold text-lg text-slate-200">Tạo yêu cầu chào thầu báo giá (RFP) mới</h3>
                    <button @click="showCreateModal = false" class="p-1 text-slate-400 hover:text-slate-200 rounded-md hover:bg-slate-800 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitRfp" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Tiêu đề yêu cầu thầu <span class="text-rose-500">*</span></label>
                            <input v-model="rfpForm.title" required type="text" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-250 focus:outline-none focus:border-emerald-500" placeholder="Ví dụ: Cung ứng Thịt & Hải sản tươi sống tháng 7/2026" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Mô tả chi tiết & Tiêu chí kỹ thuật</label>
                            <textarea v-model="rfpForm.description" rows="3" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-250 focus:outline-none focus:border-emerald-500" placeholder="Mô tả tiêu chuẩn chất lượng, quy cách đóng gói, tần suất giao hàng..."></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Thời hạn nộp hồ sơ thầu <span class="text-rose-500">*</span></label>
                            <input v-model="rfpForm.due_date" required type="datetime-local" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-250 focus:outline-none focus:border-emerald-500" />
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-800/80">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Danh mục mặt hàng cần chào giá <span class="text-rose-500">*</span></h4>
                            <button type="button" @click="addRfpItem" class="inline-flex items-center gap-1 text-xs text-emerald-450 hover:text-emerald-400 font-semibold">
                                <Plus class="w-4 h-4" /> Thêm mặt hàng
                            </button>
                        </div>

                        <!-- Dynamic Items Form -->
                        <div class="space-y-3">
                            <div 
                                v-for="(item, idx) in rfpForm.items" 
                                :key="idx" 
                                class="grid grid-cols-12 gap-3 p-3 bg-slate-950/40 border border-slate-850 rounded-xl relative group"
                            >
                                <div class="col-span-5">
                                    <label class="block text-[10px] text-slate-500 uppercase font-bold mb-1">Tên nguyên liệu</label>
                                    <input v-model="item.ingredient_name" required type="text" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-250 focus:outline-none focus:border-emerald-500" placeholder="Bột mì, Thịt bò Wagyu..." />
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-[10px] text-slate-500 uppercase font-bold mb-1">Số lượng</label>
                                    <input v-model="item.quantity_required" required type="number" step="0.001" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-250 focus:outline-none focus:border-emerald-500" />
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-[10px] text-slate-500 uppercase font-bold mb-1">Đơn vị</label>
                                    <input v-model="item.unit_symbol" required type="text" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-250 focus:outline-none focus:border-emerald-500" placeholder="kg, lít, hộp..." />
                                </div>
                                <div class="col-span-1 flex items-end justify-center pb-1.5">
                                    <button 
                                        type="button" 
                                        :disabled="rfpForm.items.length === 1"
                                        @click="removeRfpItem(idx)" 
                                        class="p-1.5 text-slate-500 hover:text-rose-450 hover:bg-rose-950/40 rounded transition-colors disabled:opacity-40"
                                    >
                                        <X class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="col-span-11">
                                    <label class="block text-[10px] text-slate-500 uppercase font-bold mb-1">Yêu cầu tiêu chuẩn kỹ thuật (Ghi chú)</label>
                                    <input v-model="item.notes" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-250 focus:outline-none focus:border-emerald-500" placeholder="Hàng loại 1, bảo quản mát dưới 4 độ, đóng khay xốp..." />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t border-slate-800 mt-6">
                        <button type="button" @click="showCreateModal = false" class="px-4 py-2 border border-slate-800 text-sm font-semibold rounded-lg text-slate-400 hover:text-slate-200 transition-colors">
                            Hủy bỏ
                        </button>
                        <button type="submit" :disabled="rfpForm.processing" class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-500 text-white rounded-lg text-sm font-semibold hover:from-emerald-500 hover:to-teal-400 transition-all shadow-md shadow-emerald-950/30">
                            {{ rfpForm.processing ? 'Đang gửi...' : 'Đăng tải thầu RFP' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
