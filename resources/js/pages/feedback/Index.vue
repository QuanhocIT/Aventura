<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    Heart, Star, AlertTriangle, ShieldCheck, CheckCircle2,
    Calendar, RefreshCw, Filter, Search, ShieldAlert,
    ChevronRight, ArrowUpRight, Award, MessageSquare, Coffee,
    UserX, Clock, Users, Flame, Percent
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface Feedback {
    id: number;
    submitted_by_name: string;
    submitted_by_phone: string | null;
    rating: number;
    content: string | null;
    status: 'new' | 'reviewed' | 'resolved';
    is_anonymous: boolean;
    order_id: number | null;
    order_number: string | null;
    table_name: string;
    created_at: string;
    items: string[];
    responsible_shift: string;
    responsible_staff: string[];
    compensation_voucher: string | null;
    resolution_notes: string | null;
}

interface Voucher {
    id: number;
    name: string;
    code: string;
    type: 'percent' | 'fixed_amount';
    value: number;
}

interface Stats {
    total: number;
    new: number;
    average: number;
    distribution: Record<number, number>;
}

const props = defineProps<{
    feedbacks: Feedback[];
    vouchers: Voucher[];
    stats: Stats;
}>();

// --- STATE ---
const activeFilter = ref<'all' | 'new' | 'resolved' | 'critical'>('all');
const showResolveModal = ref(false);
const selectedFeedback = ref<Feedback | null>(null);

// Form handling
const resolveForm = useForm({
    compensation_voucher: '',
    resolution_notes: '',
    status: 'resolved' as 'reviewed' | 'resolved',
});

// --- COMPUTED ---
// Filter feedbacks in frontend
const filteredFeedbacks = computed(() => {
    return props.feedbacks.filter(fb => {
        if (activeFilter.value === 'new') return fb.status === 'new';
        if (activeFilter.value === 'resolved') return fb.status === 'resolved' || fb.status === 'reviewed';
        if (activeFilter.value === 'critical') return fb.rating <= 2;
        return true;
    });
});

// AI Emergency Crisis Feedbacks (Unresolved 1-2 Stars feedbacks)
const crisisFeedbacks = computed(() => {
    return props.feedbacks.filter(fb => fb.rating <= 2 && fb.status === 'new');
});

// --- ACTIONS ---
const openResolveModal = (fb: Feedback) => {
    selectedFeedback.value = fb;
    resolveForm.compensation_voucher = fb.compensation_voucher || '';
    resolveForm.resolution_notes = fb.resolution_notes || '';
    resolveForm.status = 'resolved';
    showResolveModal.value = true;
};

const submitResolve = () => {
    if (!selectedFeedback.value) return;

    resolveForm.post(`/feedback/${selectedFeedback.value.id}/resolve`, {
        onSuccess: () => {
            showResolveModal.value = false;
            selectedFeedback.value = null;
            resolveForm.reset();
        }
    });
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const ratingStarsConfig: Record<number, { text: string; bg: string; text_color: string }> = {
    5: { text: 'Xuất sắc', bg: 'bg-emerald-50 dark:bg-emerald-950/20', text_color: 'text-emerald-600 dark:text-emerald-400' },
    4: { text: 'Tốt', bg: 'bg-teal-50 dark:bg-teal-950/20', text_color: 'text-teal-600 dark:text-teal-400' },
    3: { text: 'Tạm ổn', bg: 'bg-amber-50 dark:bg-amber-950/20', text_color: 'text-amber-600 dark:text-amber-400' },
    2: { text: 'Tệ', bg: 'bg-orange-50 dark:bg-orange-950/20', text_color: 'text-orange-600 dark:text-orange-400' },
    1: { text: 'Rất tệ', bg: 'bg-rose-50 dark:bg-rose-950/20', text_color: 'text-rose-600 dark:text-rose-400' },
};
</script>

<template>
    <Head title="Quản lý Phản hồi Khách hàng & AI Crisis Control" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- POS Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 shadow-sm">
                    <MessageSquare class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Xử Lý Phản Hồi & Dập Lửa Khủng Hoảng</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Thu thập đánh giá thời gian thực từ QR bàn ăn, phát hiện khủng hoảng và giải quyết đền bù voucher ngay lập tức.
                    </p>
                </div>
            </div>
            <!-- Sync indicators -->
            <div class="flex items-center gap-2">
                <div class="text-[10px] font-bold px-2.5 py-1 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-full border border-emerald-100 dark:border-emerald-900/60 animate-pulse">
                    Đồng bộ QR Bàn Ăn: Hoạt động
                </div>
            </div>
        </div>

        <!-- AI EMERGENCY CRISIS ALERTS PANEL -->
        <div v-if="crisisFeedbacks.length > 0" class="flex flex-col gap-3">
            <div class="rounded-2xl border border-rose-200 bg-rose-50/70 dark:border-rose-900/40 dark:bg-rose-950/20 p-4 shadow-sm relative overflow-hidden animate-pulse-slow">
                <div class="absolute right-4 top-4 opacity-10">
                    <Flame class="size-20 text-rose-500" />
                </div>

                <div class="flex items-center gap-2 text-rose-700 dark:text-rose-400 font-extrabold text-sm uppercase tracking-wider mb-3">
                    <ShieldAlert class="size-4 animate-bounce shrink-0" />
                    <span>Cảnh Báo Khủng Hoảng Khẩn Cấp (AI Crisis Alert)</span>
                </div>

                <div class="flex flex-col gap-2.5 max-h-48 overflow-y-auto">
                    <div 
                        v-for="alert in crisisFeedbacks" 
                        :key="alert.id"
                        class="flex justify-between items-start gap-4 p-3 bg-white dark:bg-slate-900/80 rounded-xl border border-rose-100 dark:border-rose-950 shadow-sm"
                    >
                        <div class="flex-1">
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                Phát hiện đánh giá cực tệ <span class="text-rose-500 font-black">({{ alert.rating }} Sao)</span> tại 
                                <span class="text-violet-600 dark:text-violet-400 font-black">{{ alert.table_name }}</span> 
                                (Hóa đơn: {{ alert.order_number ?? 'N/A' }})
                            </p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 italic leading-relaxed">
                                "{{ alert.content ?? 'Khách không để lại ý kiến đóng góp' }}"
                            </p>
                            <!-- Context breakdown -->
                            <div class="flex flex-wrap gap-x-3 gap-y-1 mt-2 text-[10px] text-slate-400 font-bold">
                                <span>Ca chịu trách nhiệm: {{ alert.responsible_shift }}</span>
                                <span>•</span>
                                <span>Các món trong đơn: {{ alert.items.join(', ') || 'N/A' }}</span>
                            </div>
                        </div>
                        <Button 
                            size="sm" 
                            variant="destructive"
                            @click="openResolveModal(alert)"
                            class="h-8 text-[10px] font-extrabold bg-gradient-to-r from-rose-600 to-orange-600 border-0 shadow-md flex items-center gap-1.5 shrink-0 rounded-lg"
                        >
                            <Flame class="size-3.5" />
                            Xử lý & Đền bù ngay
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- STATS / KPIS DASHBOARD -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Score Card -->
            <Card class="rounded-2xl border-slate-200 dark:border-slate-800">
                <CardHeader class="p-4 pb-2">
                    <CardTitle class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        Điểm Đánh Giá Trung Bình
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0 flex items-center justify-between">
                    <div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold text-slate-800 dark:text-slate-100">{{ stats.average }}</span>
                            <span class="text-sm font-bold text-slate-400">/ 5.0</span>
                        </div>
                        <div class="flex items-center gap-0.5 mt-1 select-none">
                            <Star 
                                v-for="s in 5" 
                                :key="s"
                                class="size-4"
                                :class="[
                                    s <= Math.round(stats.average) 
                                        ? 'text-amber-400 fill-amber-400' 
                                        : 'text-slate-200 dark:text-slate-800'
                                ]"
                            />
                        </div>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-500 flex items-center justify-center">
                        <Award class="size-6" />
                    </div>
                </CardContent>
            </Card>

            <!-- Total Feedbacks -->
            <Card class="rounded-2xl border-slate-200 dark:border-slate-800">
                <CardHeader class="p-4 pb-2">
                    <CardTitle class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        Tổng Số Phản Hồi
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0 flex items-center justify-between">
                    <div>
                        <div class="text-4xl font-extrabold text-slate-800 dark:text-slate-100">{{ stats.total }}</div>
                        <p class="text-[10px] font-bold text-slate-400 mt-1.5 flex items-center gap-1">
                            <span class="h-2 w-2 rounded-full bg-violet-500 animate-ping"></span>
                            <span>{{ stats.new }} phản hồi mới chưa xem xét</span>
                        </p>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-violet-50 dark:bg-violet-950/40 text-violet-500 flex items-center justify-center">
                        <MessageSquare class="size-6" />
                    </div>
                </CardContent>
            </Card>

            <!-- Distribution chart -->
            <Card class="rounded-2xl border-slate-200 dark:border-slate-800">
                <CardHeader class="p-4 pb-2">
                    <CardTitle class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        Phân Phối Điểm Số (Số lượng)
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0 flex flex-col gap-1.5 justify-center">
                    <div 
                        v-for="s in [5, 4, 3, 2, 1]" 
                        :key="s"
                        class="flex items-center gap-2 text-[10px] font-bold text-slate-600 dark:text-slate-400"
                    >
                        <span class="w-3 text-right">{{ s }}★</span>
                        <!-- Bar -->
                        <div class="flex-1 h-2 bg-slate-100 dark:bg-slate-850 rounded-full overflow-hidden">
                            <div 
                                class="h-full rounded-full transition-all duration-300"
                                :class="[
                                    s >= 4 ? 'bg-emerald-500' : 
                                    s === 3 ? 'bg-amber-400' : 'bg-rose-500'
                                ]"
                                :style="{ width: `${stats.total > 0 ? (stats.distribution[s] / stats.total) * 100 : 0}%` }"
                            ></div>
                        </div>
                        <span class="w-6 text-right font-mono">{{ stats.distribution[s] }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- MAIN TABLE & LIST WORKSPACE -->
        <Card class="rounded-2xl border-slate-200 dark:border-slate-800 overflow-hidden">
            <!-- Filter Bar -->
            <div class="p-4 border-b bg-slate-50/50 dark:bg-slate-900/20 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-900 rounded-xl p-0.5 border border-slate-200/50 dark:border-slate-800">
                    <button 
                        v-for="filter in [
                            { key: 'all', label: 'Tất cả' },
                            { key: 'new', label: 'Mới gửi' },
                            { key: 'resolved', label: 'Đã giải quyết' },
                            { key: 'critical', label: 'Khủng hoảng (1-2★)' },
                        ]"
                        :key="filter.key"
                        type="button"
                        @click="activeFilter = filter.key as any"
                        :class="[
                            'px-3.5 py-1.5 text-[11px] font-bold rounded-lg transition-colors whitespace-nowrap',
                            activeFilter === filter.key 
                                ? 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 shadow-sm border border-slate-200/20' 
                                : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'
                        ]"
                    >
                        {{ filter.label }}
                    </button>
                </div>
                <div class="text-[10px] text-slate-400 font-bold">
                    Hiển thị: {{ filteredFeedbacks.length }} / {{ stats.total }} phản hồi
                </div>
            </div>

            <!-- Feedbacks Listing Workspace -->
            <div class="divide-y dark:divide-slate-850">
                <div 
                    v-for="fb in filteredFeedbacks" 
                    :key="fb.id"
                    class="p-5 flex flex-col md:flex-row justify-between gap-5 transition-all duration-300 hover:bg-slate-50/30 dark:hover:bg-slate-900/10"
                    :class="[
                        fb.status === 'new' && fb.rating <= 2 ? 'border-l-4 border-rose-500 bg-rose-50/10 dark:bg-rose-950/5' : ''
                    ]"
                >
                    <!-- Left: Star, Content and Client contact info -->
                    <div class="flex-1 flex gap-4 items-start">
                        <!-- Rating Circle badge -->
                        <div 
                            class="h-10 w-10 shrink-0 rounded-2xl flex flex-col items-center justify-center shadow-inner select-none"
                            :class="ratingStarsConfig[fb.rating]?.bg"
                        >
                            <span class="text-base font-extrabold" :class="ratingStarsConfig[fb.rating]?.text_color">
                                {{ fb.rating }}
                            </span>
                            <span class="text-[7px] -mt-1 uppercase tracking-wider font-extrabold" :class="ratingStarsConfig[fb.rating]?.text_color">
                                Sao
                            </span>
                        </div>

                        <div class="flex-grow flex flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                    {{ fb.submitted_by_name }}
                                </span>
                                <span v-if="fb.submitted_by_phone" class="text-[10px] text-slate-400 font-bold bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                                    SĐT: {{ fb.submitted_by_phone }}
                                </span>
                                <span class="text-[9px] text-slate-400 font-medium">
                                    {{ fb.created_at }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-350 leading-relaxed mt-1">
                                "{{ fb.content ?? 'Khách không để lại ý kiến đóng góp chi tiết.' }}"
                            </p>

                            <!-- Resolution Context if Resolved -->
                            <div 
                                v-if="fb.status === 'resolved' || fb.status === 'reviewed'" 
                                class="mt-3 bg-emerald-50/50 dark:bg-emerald-950/10 rounded-xl border border-emerald-100/60 dark:border-emerald-900/20 p-3 flex flex-col gap-1.5 animate-fadeIn"
                            >
                                <div class="flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400 text-[10px] font-bold uppercase tracking-wider">
                                    <CheckCircle2 class="size-3.5 shrink-0" />
                                    <span>Đã giải quyết đền bù thành công</span>
                                </div>
                                <p class="text-xs text-slate-700 dark:text-slate-300 font-medium">
                                    Phương án: {{ fb.resolution_notes ?? 'Nhân viên đã trực tiếp gặp xin lỗi khách' }}
                                </p>
                                <div v-if="fb.compensation_voucher" class="flex items-center gap-1 text-[10px] text-violet-600 dark:text-violet-400 font-bold mt-1 select-none">
                                    <Percent class="size-3" />
                                    <span>Mã đền bù: {{ fb.compensation_voucher }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Incident Context and Action Resolver -->
                    <div class="w-full md:w-80 shrink-0 flex flex-col gap-3 border-t md:border-t-0 pt-4 md:pt-0 border-slate-100 dark:border-slate-850 justify-between items-start md:items-end">
                        <!-- Attributed Crisis context info -->
                        <div class="flex flex-col gap-1.5 w-full md:text-right select-none text-[10px] font-bold text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/40 border border-slate-150/40 dark:border-slate-800 p-3 rounded-2xl">
                            <div>
                                <span class="text-[9px] uppercase tracking-wider text-slate-400 block mb-0.5">Vị trí & Đơn hàng</span>
                                <span class="text-slate-800 dark:text-slate-200 text-xs">{{ fb.table_name }}</span>
                                <span v-if="fb.order_number" class="text-slate-400 font-medium font-mono text-[10px] block mt-0.5">Hóa đơn: {{ fb.order_number }}</span>
                            </div>
                            <div class="border-t border-slate-200/50 dark:border-slate-800 pt-1.5 mt-1.5">
                                <span class="text-[9px] uppercase tracking-wider text-slate-400 block mb-0.5">Ca Trực Chịu Trách Nhiệm</span>
                                <span class="text-violet-600 dark:text-violet-400 text-[11px] block">{{ fb.responsible_shift }}</span>
                                <span v-if="fb.responsible_staff.length > 0" class="text-[10px] text-slate-500 dark:text-slate-400 font-medium block mt-0.5">
                                    Nhân sự trực: {{ fb.responsible_staff.join(', ') }}
                                </span>
                            </div>
                            <div v-if="fb.items.length > 0" class="border-t border-slate-200/50 dark:border-slate-800 pt-1.5 mt-1.5">
                                <span class="text-[9px] uppercase tracking-wider text-slate-400 block mb-0.5">Món ăn trong đơn</span>
                                <span class="text-slate-700 dark:text-slate-300 font-medium font-mono block leading-relaxed">{{ fb.items.join(', ') }}</span>
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div v-if="fb.status === 'new'" class="w-full flex justify-end">
                            <Button 
                                size="sm" 
                                @click="openResolveModal(fb)"
                                class="w-full md:w-auto h-9 text-xs font-bold bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 dark:from-violet-500 dark:to-indigo-500 text-white rounded-xl shadow-md border-0 flex items-center justify-center gap-1.5 select-none"
                            >
                                <Award class="size-4" />
                                Giải quyết đền bù
                            </Button>
                        </div>
                        <div v-else class="w-full flex justify-end select-none">
                            <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-100/50 dark:bg-emerald-950/30 px-3 py-1 rounded-full border border-emerald-200/30">
                                Đã xử lý khủng hoảng
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="filteredFeedbacks.length === 0" class="flex flex-col items-center justify-center py-20 text-center gap-2 select-none">
                    <CheckCircle2 class="size-10 text-slate-350 dark:text-slate-700" />
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Không có phản hồi nào</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500 max-w-[250px]">Toàn bộ danh sách sạch sẽ, chất lượng dịch vụ hiện tại rất tuyệt vời!</p>
                </div>
            </div>
        </Card>
    </div>

    <!-- CRISIS RESOLUTION MODAL -->
    <div 
        v-if="showResolveModal && selectedFeedback" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm animate-fadeIn"
    >
        <div class="w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/60 dark:border-slate-800 shadow-2xl p-6 relative overflow-hidden">
            <div class="flex items-center gap-2 text-violet-700 dark:text-violet-400 font-extrabold text-sm uppercase tracking-wider mb-4 border-b pb-3 select-none">
                <Award class="size-4.5" />
                <span>Phương án Đền bù Khủng hoảng (Crisis Resolution)</span>
            </div>

            <!-- Empathy message about feedback context -->
            <div class="rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100/50 p-3.5 mb-4 text-xs select-none">
                <div class="font-bold text-slate-800 dark:text-slate-200">
                    Khách hàng: {{ selectedFeedback.submitted_by_name }} ({{ selectedFeedback.table_name }})
                </div>
                <div class="text-slate-500 dark:text-slate-400 mt-1 italic">
                    "{{ selectedFeedback.content ?? 'Khách không để lại ý kiến đóng góp chi tiết.' }}"
                </div>
            </div>

            <form @submit.prevent="submitResolve" class="flex flex-col gap-4">
                <!-- Voucher selection -->
                <div class="flex flex-col gap-1.5">
                    <Label for="voucher" class="text-xs font-bold text-slate-600 dark:text-slate-400">
                        Chọn Voucher Đền Bù giảm giá cho khách:
                    </Label>
                    <select 
                        id="voucher"
                        v-model="resolveForm.compensation_voucher"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    >
                        <option value="">-- Không tặng Voucher (Gặp xin lỗi trực tiếp) --</option>
                        <option v-for="v in vouchers" :key="v.id" :value="v.code">
                            {{ v.name }} [Mã: {{ v.code }} - Giảm: {{ v.type === 'percent' ? v.value + '%' : formatCurrency(v.value) }}]
                        </option>
                    </select>
                </div>

                <!-- Notes for Resolution action -->
                <div class="flex flex-col gap-1.5">
                    <Label for="notes" class="text-xs font-bold text-slate-600 dark:text-slate-400">
                        Nội dung phương án đền bù / giải quyết sự cố: <span class="text-rose-500 font-bold">*</span>
                    </Label>
                    <textarea 
                        id="notes"
                        v-model="resolveForm.resolution_notes"
                        placeholder="Ví dụ: Đã ra bàn xin lỗi khách vì súp gà bị nguội, trực tiếp đổi tô súp nóng mới và tặng voucher giảm giá 20% cho lần ăn tiếp theo..."
                        rows="3"
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500 transition-all resize-none"
                    ></textarea>
                </div>

                <!-- Action buttons -->
                <div class="flex justify-end gap-2.5 border-t pt-4 mt-2">
                    <Button 
                        type="button" 
                        variant="outline" 
                        @click="showResolveModal = false"
                        class="h-9 text-xs rounded-xl"
                    >
                        Hủy
                    </Button>
                    <Button 
                        type="submit" 
                        :disabled="resolveForm.processing"
                        class="h-9 text-xs font-bold bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white rounded-xl shadow-md border-0"
                    >
                        Xác nhận Giải quyết
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}
.animate-fadeIn {
    animation: fadeIn 0.2s ease-out forwards;
}

@keyframes pulseSlow {
    0%, 100% { border-color: rgb(254 205 211); }
    50% { border-color: rgb(244 63 94); }
}
.animate-pulse-slow {
    animation: pulseSlow 2s infinite ease-in-out;
}
.dark .animate-pulse-slow {
    @keyframes pulseDark {
        0%, 100% { border-color: rgba(225, 29, 72, 0.2); }
        50% { border-color: rgba(225, 29, 72, 0.7); }
    }
    animation: pulseDark 2s infinite ease-in-out;
}
</style>
