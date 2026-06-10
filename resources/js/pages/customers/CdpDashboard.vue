<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Sparkles, ArrowUpDown, ChevronDown, Clock, Search, Mail, Phone,
    Plus, Users, Trash2, Pencil, Calendar, AlertCircle, RefreshCw,
    TrendingUp, Award, Zap, HelpCircle, Gift, Send, Play, CheckCircle2,
    Volume2, Compass, AlertTriangle, Eye, ShoppingCart, Info
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface CdpCustomer {
    id: number;
    customer_code: string;
    full_name: string;
    phone: string;
    email: string | null;
    loyalty_points: number;
    last_order_at: string;
    rfm_segment: 'VIP' | 'Loyal' | 'New' | 'SalesHunter' | 'AboutToLeave' | 'Lost' | 'Potential';
    rfm_score_code: string;
    monetary_amount: number;
    frequency_count: number;
    recency_days: number;
}

interface SegmentStat {
    count: number;
    revenue: number;
    avg_spend: number;
}

interface Metrics {
    total_customers: number;
    total_revenue: number;
    avg_clv: number;
    avg_frequency: number;
    segments: Record<string, SegmentStat>;
    recent_logs: Array<{
        id: number;
        session_id: string;
        customer_name: string;
        customer_phone: string | null;
        event_type: string;
        product_name: string | null;
        quantity: number;
        meta_data: any;
        created_at: string;
    }>;
}

const props = defineProps<{
    metrics: Metrics;
    customers: CdpCustomer[];
}>();

// --- STATE ---
const activeSubTab = ref<'analytics' | 'footprints' | 'campaigns'>('analytics');
const selectedSegment = ref<string>('all');
const searchQuery = ref('');
const isRecalculating = ref(false);

// Campaign states
const selectedCampaignSegment = ref('AboutToLeave');
const campaignType = ref<'sms' | 'zalo' | 'discount'>('discount');
const voucherCodeInput = ref('');
const messageText = ref('Chào anh/chị, lâu rồi anh/chị chưa ghé Aventura. Tặng anh/chị mã giảm 20% [VOUCHER] áp dụng cho đơn tiếp theo nhé!');
const showCampaignSuccessModal = ref(false);
const showCampaignSuccessDetails = ref<any>(null);

// --- HELPERS & DICTIONARIES ---
const segmentConfig = {
    VIP: {
        label: 'Khách VIP / Champions',
        desc: 'Mua thường xuyên nhất, chi tiêu nhiều nhất. Cần quà tri ân và chăm sóc đặc quyền.',
        color: 'indigo',
        badgeClass: 'bg-indigo-100 text-indigo-750 border-indigo-200 dark:bg-indigo-950/30 dark:text-indigo-400 dark:border-indigo-900/40',
        dotClass: 'bg-indigo-600',
        icon: '👑'
    },
    Loyal: {
        label: 'Khách hàng Trung thành',
        desc: 'Mua đều đặn, chi tiêu tốt. Cần giữ chân qua các chương trình tích điểm hội viên.',
        color: 'emerald',
        badgeClass: 'bg-emerald-100 text-emerald-750 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/40',
        dotClass: 'bg-emerald-600',
        icon: '🥈'
    },
    New: {
        label: 'Khách mới đăng ký',
        desc: 'Mới mua gần đây, tần suất thấp. Cần email/tin nhắn chào mừng và gợi ý món hot.',
        color: 'sky',
        badgeClass: 'bg-sky-100 text-sky-750 border-sky-200 dark:bg-sky-950/30 dark:text-sky-400 dark:border-sky-900/40',
        dotClass: 'bg-sky-500',
        icon: '🆕'
    },
    SalesHunter: {
        label: 'Khách hàng Săn Sale',
        desc: 'Chi tiêu thấp, mua nhiều hoặc có tỷ lệ áp mã giảm giá cao. Phù hợp combo giá tốt.',
        color: 'amber',
        badgeClass: 'bg-amber-100 text-amber-750 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900/40',
        dotClass: 'bg-amber-500',
        icon: '🎯'
    },
    AboutToLeave: {
        label: 'Khách hàng Sắp rời đi',
        desc: 'Đã lâu chưa quay lại mặc dù trước đó mua nhiều. Cần tung mã giảm sâu để lôi kéo.',
        color: 'orange',
        badgeClass: 'bg-orange-100 text-orange-750 border-orange-200 dark:bg-orange-950/30 dark:text-orange-400 dark:border-orange-900/40',
        dotClass: 'bg-orange-500',
        icon: '⚠️'
    },
    Lost: {
        label: 'Khách hàng Đã rời bỏ',
        desc: 'Quá hạn rất lâu không có giao dịch, chi tiêu thấp. Chi phí lôi kéo lại sẽ rất cao.',
        color: 'rose',
        badgeClass: 'bg-rose-100 text-rose-750 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/40',
        dotClass: 'bg-rose-500',
        icon: '🛑'
    },
    Potential: {
        label: 'Khách hàng Tiềm năng',
        desc: 'Mức mua trung bình, tần suất ổn. Cần upselling combo gia tăng giá trị đơn.',
        color: 'slate',
        badgeClass: 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700',
        dotClass: 'bg-slate-500',
        icon: '✨'
    }
};

const footprintEventConfig = {
    view_menu: { label: 'Xem Thực đơn', class: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400', icon: Eye },
    view_product: { label: 'Xem Món ăn', class: 'bg-blue-100 text-blue-750 dark:bg-blue-950/20 dark:text-blue-400', icon: Compass },
    add_to_cart: { label: 'Thêm Giỏ hàng', class: 'bg-amber-100 text-amber-750 dark:bg-amber-950/20 dark:text-amber-400', icon: ShoppingCart },
    remove_from_cart: { label: 'Xóa Giỏ hàng', class: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-450', icon: Trash2 },
    submit_order: { label: 'Gửi Đơn đệm', class: 'bg-indigo-100 text-indigo-750 dark:bg-indigo-950/20 dark:text-indigo-400', icon: Send },
    payment_request: { label: 'Gọi Thanh toán', class: 'bg-emerald-100 text-emerald-750 dark:bg-emerald-950/20 dark:text-emerald-400', icon: CheckCircle2 },
    call_staff: { label: 'Gọi Phục vụ', class: 'bg-rose-100 text-rose-750 dark:bg-rose-950/20 dark:text-rose-400', icon: Volume2 },
    feedback_submitted: { label: 'Đánh giá Bàn', class: 'bg-purple-100 text-purple-750 dark:bg-purple-950/20 dark:text-purple-400', icon: Award }
};

function numberFormat(val: number) {
    return val.toLocaleString('vi-VN');
}

// --- COMPUTED ---
const filteredCustomers = computed(() => {
    let list = [...props.customers];
    
    // Filter by segment
    if (selectedSegment.value !== 'all') {
        list = list.filter(c => c.rfm_segment === selectedSegment.value);
    }

    // Search query
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        list = list.filter(c => 
            c.full_name.toLowerCase().includes(query) ||
            c.phone.includes(query) ||
            (c.email && c.email.toLowerCase().includes(query)) ||
            c.customer_code.toLowerCase().includes(query)
        );
    }

    return list;
});

// Pagination state
const currentPage = ref(1);
const itemsPerPage = 8;
const totalPages = computed(() => Math.ceil(filteredCustomers.value.length / itemsPerPage));
const paginatedCustomers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredCustomers.value.slice(start, start + itemsPerPage);
});

watch([selectedSegment, searchQuery], () => {
    currentPage.value = 1;
});

// Chart parameters
const maxSegmentCount = computed(() => {
    const counts = Object.values(props.metrics.segments).map(s => s.count);
    return Math.max(...counts, 1);
});

const campaignTargetCount = computed(() => {
    if (selectedCampaignSegment.value === 'all') return props.customers.length;
    return props.customers.filter(c => c.rfm_segment === selectedCampaignSegment.value).length;
});

// --- ACTIONS ---
const handleRecalculate = () => {
    isRecalculating.value = true;
    router.post('/customers/cdp/recalculate', {}, {
        onSuccess: () => {
            isRecalculating.value = false;
        },
        onError: () => {
            isRecalculating.value = false;
        }
    });
};

const handleRunCampaign = () => {
    if (campaignTargetCount.value === 0) return;
    
    // Generate mock voucher code if needed
    const code = voucherCodeInput.value.trim() || ('VOUCHER-' + Math.random().toString(36).substring(2, 6).toUpperCase() + '-' + selectedCampaignSegment.value.substring(0, 3).toUpperCase());
    const finalMessage = messageText.value.replace('[VOUCHER]', code);

    showCampaignSuccessDetails.value = {
        segmentLabel: selectedCampaignSegment.value === 'all' ? 'Tất cả khách hàng' : segmentConfig[selectedCampaignSegment.value as keyof typeof segmentConfig]?.label,
        targetCount: campaignTargetCount.value,
        type: campaignType.value === 'sms' ? 'Tin nhắn SMS Brandname' : (campaignType.value === 'zalo' ? 'Tin nhắn Zalo ZNS' : 'Kịch bản Voucher điện tử'),
        message: finalMessage,
        voucherCode: code,
    };

    showCampaignSuccessModal.value = true;
};

const triggerAutoVoucherCode = () => {
    voucherCodeInput.value = 'AVEN' + selectedCampaignSegment.value.substring(0, 4).toUpperCase() + '20';
};
</script>

<template>
    <Head title="Nền tảng CDP & Phân tích RFM" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- HEADER -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <Sparkles class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Nền tảng CDP & Phân tích RFM Chuyên sâu</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Thu thập "dấu chân" kỹ thuật số trên hệ sinh thái và phân nhóm khách hàng tự động để tối ưu tiếp thị số.</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button 
                    @click="handleRecalculate" 
                    variant="outline"
                    class="h-10 text-xs border-indigo-200 text-indigo-600 font-semibold hover:bg-indigo-50 flex items-center gap-1.5"
                    :disabled="isRecalculating"
                >
                    <RefreshCw class="size-4" :class="isRecalculating ? 'animate-spin' : ''" />
                    Làm mới Chấm điểm RFM
                </Button>
            </div>
        </div>

        <!-- CRM / CDP NAVIGATION TOGGLE -->
        <div class="flex items-center gap-2 border-b pb-2">
            <button 
                type="button"
                @click="router.visit('/customers')"
                class="px-4 py-2 text-xs font-bold border-b-2 border-transparent text-slate-400 hover:text-slate-600 hover:border-slate-350 flex items-center gap-1.5 focus:outline-none"
            >
                👥 Hồ sơ CRM khách hàng
            </button>
            <button 
                type="button"
                class="px-4 py-2 text-xs font-bold border-b-2 border-indigo-600 text-indigo-600 focus:outline-none"
            >
                ✨ Phân tích RFM & Hành vi (CDP)
            </button>
        </div>

        <!-- CDP CORE SUB-TABS -->
        <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-900 rounded-xl p-0.5 border border-slate-200/50 dark:border-slate-800 w-fit">
            <button
                @click="activeSubTab = 'analytics'"
                :class="[
                    'px-4 py-1.5 text-xs font-bold rounded-lg transition-all focus:outline-none',
                    activeSubTab === 'analytics'
                        ? 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 shadow-sm'
                        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                ]"
            >
                📊 Phân nhóm RFM
            </button>
            <button
                @click="activeSubTab = 'footprints'"
                :class="[
                    'px-4 py-1.5 text-xs font-bold rounded-lg transition-all focus:outline-none',
                    activeSubTab === 'footprints'
                        ? 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 shadow-sm'
                        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                ]"
            >
                👣 Dấu chân Hành vi (Real-time)
            </button>
            <button
                @click="activeSubTab = 'campaigns'"
                :class="[
                    'px-4 py-1.5 text-xs font-bold rounded-lg transition-all focus:outline-none',
                    activeSubTab === 'campaigns'
                        ? 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 shadow-sm'
                        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
                ]"
            >
                🚀 Chiến dịch Retargeting
            </button>
        </div>

        <!-- ── VIEW 1: RFM ANALYTICS ── -->
        <div v-if="activeSubTab === 'analytics'" class="space-y-6">
            <!-- CDP KPI STATS -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card class="shadow-xs hover:translate-y-[-2px] transition-all bg-gradient-to-br from-indigo-50/20 to-white dark:from-indigo-950/10 dark:to-slate-900 border-indigo-100/30">
                    <CardHeader class="pb-2 flex flex-row items-center justify-between">
                        <CardDescription class="text-[10px] font-black uppercase tracking-wider text-indigo-500">Doanh thu CDP Tích Lũy</CardDescription>
                        <TrendingUp class="size-4 text-indigo-600" />
                    </CardHeader>
                    <CardContent class="pb-3">
                        <span class="text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ numberFormat(metrics.total_revenue) }}đ</span>
                        <p class="mt-1 text-xxs text-muted-foreground">Phát sinh từ tệp khách hàng định danh</p>
                    </CardContent>
                </Card>

                <Card class="shadow-xs hover:translate-y-[-2px] transition-all">
                    <CardHeader class="pb-2 flex flex-row items-center justify-between">
                        <CardDescription class="text-[10px] font-black uppercase tracking-wider text-slate-400">Giá trị Vòng đời KH (CLV)</CardDescription>
                        <Award class="size-4 text-slate-450" />
                    </CardHeader>
                    <CardContent class="pb-3">
                        <span class="text-2xl font-black text-slate-850 dark:text-slate-100">{{ numberFormat(metrics.avg_clv) }}đ</span>
                        <p class="mt-1 text-xxs text-muted-foreground">Chi tiêu trung bình trên mỗi khách hàng</p>
                    </CardContent>
                </Card>

                <Card class="shadow-xs hover:translate-y-[-2px] transition-all">
                    <CardHeader class="pb-2 flex flex-row items-center justify-between">
                        <CardDescription class="text-[10px] font-black uppercase tracking-wider text-slate-400">Tần suất đặt hàng</CardDescription>
                        <Clock class="size-4 text-slate-450" />
                    </CardHeader>
                    <CardContent class="pb-3">
                        <span class="text-2xl font-black text-slate-850 dark:text-slate-100">{{ metrics.avg_frequency }} lần</span>
                        <p class="mt-1 text-xxs text-muted-foreground">Tần suất giao dịch bình quân/khách</p>
                    </CardContent>
                </Card>

                <Card class="shadow-xs hover:translate-y-[-2px] transition-all border-emerald-100/30 bg-gradient-to-br from-emerald-50/10 to-white dark:from-emerald-950/10 dark:to-slate-900">
                    <CardHeader class="pb-2 flex flex-row items-center justify-between">
                        <CardDescription class="text-[10px] font-black uppercase tracking-wider text-emerald-500">Nhóm Khách VIP / Loyal</CardDescription>
                        <Users class="size-4 text-emerald-600" />
                    </CardHeader>
                    <CardContent class="pb-3">
                        <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                            {{ (metrics.segments.VIP?.count ?? 0) + (metrics.segments.Loyal?.count ?? 0) }} KH
                        </span>
                        <p class="mt-1 text-xxs text-muted-foreground">
                            Đóng góp {{ Math.round(((metrics.segments.VIP?.revenue ?? 0) + (metrics.segments.Loyal?.revenue ?? 0)) / (metrics.total_revenue || 1) * 100) }}% doanh số F&B
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- RFM DISTRIBUTION CHART & BREAKDOWN -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- CHART CARD (SVG Visualizer) -->
                <Card class="lg:col-span-1 shadow-sm overflow-hidden flex flex-col justify-between">
                    <CardHeader class="border-b bg-slate-50/50 dark:bg-slate-900/10">
                        <CardTitle class="text-sm flex items-center gap-1.5 font-bold">
                            <TrendingUp class="size-4 text-indigo-500" />
                            Phân bố Tệp Khách hàng theo RFM
                        </CardTitle>
                        <CardDescription>Biểu đồ phân chia số lượng khách hàng theo các cụm hành vi chi tiêu.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-6 flex-1 flex flex-col justify-center gap-6">
                        <!-- Custom HTML/SVG Bar Chart -->
                        <div class="space-y-3">
                            <div 
                                v-for="(cfg, key) in segmentConfig" 
                                :key="key" 
                                class="flex flex-col gap-1 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-900 p-1.5 rounded-lg transition-colors"
                                @click="selectedSegment = key"
                            >
                                <div class="flex justify-between items-center text-xs">
                                    <span class="font-bold flex items-center gap-1 text-slate-700 dark:text-slate-300">
                                        <span>{{ cfg.icon }}</span>
                                        <span>{{ cfg.label.split(' / ')[0] }}</span>
                                    </span>
                                    <span class="font-mono font-bold text-slate-500">
                                        {{ metrics.segments[key]?.count ?? 0 }} KH ({{ Math.round((metrics.segments[key]?.count ?? 0) / (metrics.total_customers || 1) * 100) }}%)
                                    </span>
                                </div>
                                <div class="h-2.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                    <div 
                                        class="h-full rounded-full transition-all duration-500" 
                                        :class="cfg.dotClass"
                                        :style="`width: ${(metrics.segments[key]?.count ?? 0) / maxSegmentCount * 100}%`"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <!-- Mini Info Alert -->
                        <div class="p-3 bg-amber-50/50 border border-amber-100 rounded-xl flex items-start gap-2 text-[10px] text-amber-700 dark:bg-amber-950/20 dark:border-amber-900/40 dark:text-amber-400">
                            <Info class="size-4 shrink-0 text-amber-600 mt-0.5" />
                            <p><strong>Bí kíp tiếp thị:</strong> Khách hàng nhóm <strong>Sắp rời đi</strong> nên được kích hoạt bằng chiến dịch giảm giá đặc biệt trước khi họ chuyển hẳn sang nhóm <strong>Đã rời bỏ</strong>.</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- LIST CARD -->
                <Card class="lg:col-span-2 shadow-sm overflow-hidden flex flex-col justify-between">
                    <CardHeader class="border-b bg-slate-50/50 dark:bg-slate-900/10 flex flex-row items-center justify-between gap-4">
                        <div>
                            <CardTitle class="text-sm flex items-center gap-1.5 font-bold">
                                <Users class="size-4 text-indigo-500" />
                                Danh sách Khách hàng Phân cụm RFM
                            </CardTitle>
                            <CardDescription>Bấm chọn bộ lọc nhóm cụm hoặc tìm kiếm thông tin khách hàng.</CardDescription>
                        </div>

                        <!-- Segment Switcher -->
                        <select
                            v-model="selectedSegment"
                            class="h-9 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 text-slate-700 dark:text-slate-350 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        >
                            <option value="all">Tất cả phân cụm</option>
                            <option v-for="(cfg, key) in segmentConfig" :key="key" :value="key">
                                {{ cfg.icon }} {{ cfg.label.split(' / ')[0] }}
                            </option>
                        </select>
                    </CardHeader>
                    
                    <CardContent class="p-0 flex-1 flex flex-col justify-between">
                        <!-- Search and Segment Desc -->
                        <div class="p-4 border-b bg-slate-50/20 flex flex-col gap-3">
                            <div v-if="selectedSegment !== 'all'" class="p-3 bg-slate-100/50 dark:bg-slate-850 border rounded-xl text-xs text-slate-600 dark:text-slate-400 flex items-start gap-2">
                                <span class="text-lg leading-none">{{ segmentConfig[selectedSegment as keyof typeof segmentConfig]?.icon }}</span>
                                <div>
                                    <span class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ segmentConfig[selectedSegment as keyof typeof segmentConfig]?.label }}
                                    </span>
                                    <p class="mt-0.5 text-[11px]">{{ segmentConfig[selectedSegment as keyof typeof segmentConfig]?.desc }}</p>
                                </div>
                            </div>

                            <div class="relative max-w-sm">
                                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                                <Input
                                    type="text"
                                    placeholder="Tìm theo mã KH, SĐT, Tên..."
                                    v-model="searchQuery"
                                    class="h-9 text-xs pl-8 bg-white"
                                />
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto flex-1">
                            <table class="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-100 dark:bg-slate-950 border-b text-[10px] uppercase font-bold tracking-wider text-slate-500">
                                        <th class="p-3">Mã KH</th>
                                        <th class="p-3">Khách hàng</th>
                                        <th class="p-3">Số điện thoại</th>
                                        <th class="p-3 text-center">RFM Score</th>
                                        <th class="p-3 text-right">Tổng chi tiêu</th>
                                        <th class="p-3 text-center">Số đơn</th>
                                        <th class="p-3 text-center">Giao dịch cuối</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-if="paginatedCustomers.length === 0">
                                        <td colspan="7" class="p-10 text-center text-slate-400">Không tìm thấy khách hàng nào thỏa mãn bộ lọc.</td>
                                    </tr>
                                    <tr v-for="c in paginatedCustomers" :key="c.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                        <td class="p-3 font-bold font-mono text-indigo-600 dark:text-indigo-450">{{ c.customer_code }}</td>
                                        <td class="p-3">
                                            <div class="font-bold text-slate-800 dark:text-slate-200">{{ c.full_name }}</div>
                                            <span 
                                                class="px-1.5 py-0.5 rounded text-[9px] font-bold border mt-0.5 inline-block"
                                                :class="segmentConfig[c.rfm_segment]?.badgeClass"
                                            >
                                                {{ segmentConfig[c.rfm_segment]?.icon }} {{ segmentConfig[c.rfm_segment]?.label.split(' / ')[0] }}
                                            </span>
                                        </td>
                                        <td class="p-3 font-mono font-bold">{{ c.phone }}</td>
                                        <td class="p-3 text-center">
                                            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono font-black border text-slate-700 dark:text-slate-300">
                                                {{ c.rfm_score_code }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-right font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                            {{ numberFormat(c.monetary_amount) }}đ
                                        </td>
                                        <td class="p-3 text-center font-mono">{{ c.frequency_count }} đơn</td>
                                        <td class="p-3 text-center text-slate-500 font-mono">
                                            <span v-if="c.recency_days === 999" class="italic text-slate-300">Chưa có</span>
                                            <span v-else>{{ c.recency_days }} ngày trước</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="totalPages > 1" class="flex items-center justify-between border-t p-4 bg-slate-50/50 dark:bg-slate-900/30">
                            <div class="text-[10px] text-muted-foreground">
                                Hiển thị {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredCustomers.length) }} trong tổng số {{ filteredCustomers.length }} khách hàng
                            </div>
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="currentPage === 1"
                                    @click="currentPage--"
                                    class="h-7 text-[10px] px-2.5"
                                >
                                    Trước
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="currentPage === totalPages"
                                    @click="currentPage++"
                                    class="h-7 text-[10px] px-2.5"
                                >
                                    Sau
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- ── VIEW 2: REAL-TIME FOOTPRINTS ── -->
        <div v-if="activeSubTab === 'footprints'" class="space-y-6">
            <Card class="shadow-sm overflow-hidden">
                <CardHeader class="border-b bg-slate-50/50 dark:bg-slate-900/10">
                    <CardTitle class="text-sm flex items-center gap-1.5 font-bold">
                        <Clock class="size-4 text-indigo-500" />
                        Dòng sự kiện Dấu chân hành vi khách hàng F&B
                    </CardTitle>
                    <CardDescription>Hệ thống tự động ghi nhận theo thời gian thực (Real-time tracking) hoạt động xem menu, thêm giỏ hàng, và đặt món.</CardDescription>
                </CardHeader>
                <CardContent class="p-6">
                    <div v-if="metrics.recent_logs.length === 0" class="text-center py-20 text-slate-400">
                        Chưa có dữ liệu "dấu chân" hành vi nào được ghi nhận. Quét QR gọi món tại bàn để khởi tạo luồng sự kiện!
                    </div>
                    <div v-else class="relative border-l border-slate-200 dark:border-slate-800 ml-4 space-y-6">
                        <div 
                            v-for="log in metrics.recent_logs" 
                            :key="log.id" 
                            class="relative pl-6 hover:translate-x-1 transition-transform"
                        >
                            <!-- Dot timeline -->
                            <div class="absolute -left-2.5 top-1 h-5 w-5 rounded-full border-2 bg-white dark:bg-slate-950 flex items-center justify-center border-slate-200 dark:border-slate-800">
                                <div class="size-2 rounded-full bg-indigo-500"></div>
                            </div>

                            <!-- Timeline Item Body -->
                            <div class="p-4 bg-slate-50/50 dark:bg-slate-900/30 border rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-start gap-3 text-left">
                                    <!-- Event Badge Icon -->
                                    <div 
                                        class="size-8 rounded-xl flex items-center justify-center"
                                        :class="footprintEventConfig[log.event_type as keyof typeof footprintEventConfig]?.class ?? 'bg-slate-100'"
                                    >
                                        <component 
                                            :is="footprintEventConfig[log.event_type as keyof typeof footprintEventConfig]?.icon ?? HelpCircle" 
                                            class="size-4.5"
                                        />
                                    </div>

                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-black text-xs text-slate-800 dark:text-slate-200">
                                                {{ log.customer_name }}
                                            </span>
                                            <span v-if="log.customer_phone" class="text-[10px] font-mono text-slate-400">
                                                ({{ log.customer_phone }})
                                            </span>
                                            <span 
                                                class="px-2 py-0.5 rounded text-[9px] font-bold border"
                                                :class="footprintEventConfig[log.event_type as keyof typeof footprintEventConfig]?.class"
                                            >
                                                {{ footprintEventConfig[log.event_type as keyof typeof footprintEventConfig]?.label ?? log.event_type }}
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                            <!-- Contextual text based on event -->
                                            <span v-if="log.event_type === 'view_menu'">Truy cập liên kết thực đơn điện tử</span>
                                            <span v-else-if="log.event_type === 'view_product'">Xem chi tiết món ăn: <strong class="text-indigo-650 dark:text-indigo-400">{{ log.product_name }}</strong></span>
                                            <span v-else-if="log.event_type === 'add_to_cart'">Thêm <strong class="text-indigo-650 dark:text-indigo-400">{{ log.quantity }}x {{ log.product_name }}</strong> vào giỏ hàng</span>
                                            <span v-else-if="log.event_type === 'remove_from_cart'">Xóa/giảm món <strong class="text-slate-650 dark:text-slate-400">{{ log.product_name }}</strong> khỏi giỏ hàng</span>
                                            <span v-else-if="log.event_type === 'submit_order'">Gửi yêu cầu đặt món QR (#{{ log.meta_data?.temporary_order_id }}) trị giá <strong class="text-indigo-600">{{ numberFormat(log.meta_data?.amount ?? 0) }}đ</strong></span>
                                            <span v-else-if="log.event_type === 'payment_request'">Bấm chuông gọi nhân viên thanh toán hóa đơn</span>
                                            <span v-else-if="log.event_type === 'call_staff'">Báo chuông yêu cầu phục vụ tại bàn</span>
                                            <span v-else-if="log.event_type === 'feedback_submitted'">Gửi biểu mẫu đánh giá bàn phục vụ ({{ log.meta_data?.rating }}⭐)</span>
                                            <span v-else>Hành động {{ log.event_type }}</span>
                                        </p>
                                        <div class="text-[9px] text-slate-400 mt-1 font-mono">
                                            Session ID: {{ log.session_id.substring(0, 16) }}... • URL: {{ log.meta_data?.url ? log.meta_data.url.split('www')[1] || '/' : '/' }}
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right flex flex-col justify-between items-end shrink-0">
                                    <span class="text-[10px] font-mono text-slate-400 flex items-center gap-1">
                                        <Clock class="size-3" /> {{ log.created_at }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ── VIEW 3: RETARGETING CAMPAIGNS ── -->
        <div v-if="activeSubTab === 'campaigns'" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Campaign configuration form -->
                <Card class="shadow-sm overflow-hidden text-left">
                    <CardHeader class="border-b bg-slate-50/50 dark:bg-slate-900/10">
                        <CardTitle class="text-sm flex items-center gap-1.5 font-bold text-indigo-600">
                            <Zap class="size-5 animate-pulse" />
                            Khởi chạy chiến dịch Bám đuổi khách hàng (Retargeting)
                        </CardTitle>
                        <CardDescription>Cấu hình chương trình tri ân/kích cầu gửi tự động qua SMS/Zalo hoặc phát hành voucher cho tệp khách hàng RFM mục tiêu.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-6 space-y-4">
                        <!-- Step 1: Choose segment target -->
                        <div class="grid gap-1.5">
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-350">1. Tệp khách hàng mục tiêu</label>
                            <select
                                v-model="selectedCampaignSegment"
                                @change="triggerAutoVoucherCode"
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            >
                                <option value="all">Tất cả khách hàng ({{ props.customers.length }} KH)</option>
                                <option v-for="(cfg, key) in segmentConfig" :key="key" :value="key">
                                    {{ cfg.icon }} {{ cfg.label }} ({{ props.customers.filter(c => c.rfm_segment === key).length }} KH)
                                </option>
                            </select>
                        </div>

                        <!-- Step 2: Choose campaign channel type -->
                        <div class="grid gap-1.5">
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-350">2. Kênh phân phối</label>
                            <div class="grid grid-cols-3 gap-2">
                                <Button
                                    variant="outline"
                                    class="rounded-xl text-xs h-10"
                                    :class="campaignType === 'discount' ? 'border-indigo-600 bg-indigo-50/50 text-indigo-650 font-bold' : ''"
                                    @click="campaignType = 'discount'"
                                >
                                    🎫 Voucher điện tử
                                </Button>
                                <Button
                                    variant="outline"
                                    class="rounded-xl text-xs h-10"
                                    :class="campaignType === 'sms' ? 'border-indigo-600 bg-indigo-50/50 text-indigo-650 font-bold' : ''"
                                    @click="campaignType = 'sms'"
                                >
                                    💬 SMS Brandname
                                </Button>
                                <Button
                                    variant="outline"
                                    class="rounded-xl text-xs h-10"
                                    :class="campaignType === 'zalo' ? 'border-indigo-600 bg-indigo-50/50 text-indigo-650 font-bold' : ''"
                                    @click="campaignType = 'zalo'"
                                >
                                    📱 Zalo ZNS
                                </Button>
                            </div>
                        </div>

                        <!-- Step 3: Voucher Code input -->
                        <div class="grid gap-1.5">
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-350">3. Mã Voucher khuyến mãi</label>
                            <div class="flex gap-2">
                                <Input 
                                    v-model="voucherCodeInput" 
                                    placeholder="Nhập mã ví dụ: AVENVIP20..." 
                                    class="h-9 rounded-xl text-xs" 
                                />
                                <Button 
                                    size="sm" 
                                    variant="outline" 
                                    class="h-9 rounded-xl text-[10px]" 
                                    @click="triggerAutoVoucherCode"
                                >
                                    Tạo tự động
                                </Button>
                            </div>
                        </div>

                        <!-- Step 4: Campaign Message content -->
                        <div class="grid gap-1.5">
                            <label class="text-xs font-bold text-slate-700 dark:text-slate-350">4. Nội dung thông điệp chiến dịch</label>
                            <textarea
                                v-model="messageText"
                                rows="3"
                                class="w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2 text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            ></textarea>
                            <p class="text-[10px] text-muted-foreground">* Từ khóa <strong>[VOUCHER]</strong> sẽ tự động chuyển thành mã voucher khi gửi đi.</p>
                        </div>

                        <!-- Submit button -->
                        <div class="pt-3 border-t">
                            <Button 
                                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-1.5"
                                :disabled="campaignTargetCount === 0"
                                @click="handleRunCampaign"
                            >
                                <Play class="size-4" /> Khởi chạy chiến dịch (gửi tới {{ campaignTargetCount }} khách hàng)
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Target preview / Explanation card -->
                <div class="flex flex-col gap-6">
                    <Card class="shadow-sm text-left flex-1">
                        <CardHeader class="border-b bg-slate-50/50 dark:bg-slate-900/10">
                            <CardTitle class="text-sm font-bold">Thuyết minh thông số & Tỉ lệ chuyển đổi ước tính</CardTitle>
                        </CardHeader>
                        <CardContent class="p-6 space-y-4 text-xs text-slate-600 dark:text-slate-400">
                            <div class="p-3 bg-indigo-50/20 rounded-xl border border-indigo-100 flex flex-col gap-1.5">
                                <span class="font-bold text-indigo-700 dark:text-indigo-400">Phân tích tệp chọn:</span>
                                <p>Phân nhóm <strong>{{ selectedCampaignSegment === 'all' ? 'Tất cả khách hàng' : segmentConfig[selectedCampaignSegment as keyof typeof segmentConfig]?.label }}</strong> gồm có <strong>{{ campaignTargetCount }} khách hàng</strong> đăng ký.</p>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center pb-2 border-b">
                                    <span class="font-semibold">Tỉ lệ mở đọc trung bình:</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ campaignType === 'sms' ? '98%' : (campaignType === 'zalo' ? '85%' : '45%') }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center pb-2 border-b">
                                    <span class="font-semibold">Tỉ lệ click áp voucher ước tính:</span>
                                    <span class="font-bold text-emerald-600">
                                        {{ selectedCampaignSegment === 'VIP' ? '25%' : (selectedCampaignSegment === 'AboutToLeave' ? '18%' : '10%') }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center pb-2 border-b">
                                    <span class="font-semibold">Doanh số thu hồi ước tính:</span>
                                    <span class="font-bold text-indigo-600">
                                        {{ numberFormat(campaignTargetCount * (selectedCampaignSegment === 'VIP' ? 350000 : 180000) * 0.15) }}đ
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold">Chi phí chiến dịch:</span>
                                    <span class="font-mono text-slate-500">
                                        {{ numberFormat(campaignTargetCount * (campaignType === 'sms' ? 700 : (campaignType === 'zalo' ? 350 : 0))) }}đ
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Campaign instructions warning card -->
                    <div class="p-4 rounded-3xl border border-indigo-200 bg-indigo-50/20 text-xs text-indigo-800 dark:bg-indigo-950/20 dark:border-indigo-900/40 dark:text-indigo-400 text-left flex items-start gap-3">
                        <AlertTriangle class="size-5 text-indigo-500 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold">Chính sách gửi tin an toàn CDP</span>
                            <p class="mt-1 leading-relaxed">Để tránh spam khách hàng và duy trì điểm số tin cậy Brandname, hệ thống giới hạn tối đa 1 chiến dịch tự động gửi đến cùng 1 nhóm khách hàng trong vòng 7 ngày. Hãy chắc chắn nội dung thông điệp mang lại lợi ích thực tế cho thực khách.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── MODAL: SUCCESSFUL CAMPAIGN LAUNCH (MOCK) ── -->
        <div v-if="showCampaignSuccessModal && showCampaignSuccessDetails" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
                <CardHeader class="pb-3 border-b text-center">
                    <div class="size-12 rounded-full bg-emerald-100 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-2 text-xl font-bold">
                        ✓
                    </div>
                    <CardTitle class="text-base text-emerald-600">Chiến dịch Tiếp thị đã Khởi chạy thành công!</CardTitle>
                    <CardDescription>Hệ thống CDP & SMS Gateway đã tiếp nhận kịch bản gửi hàng loạt.</CardDescription>
                </CardHeader>
                <CardContent class="pt-4 space-y-4 text-xs text-left">
                    <div class="space-y-2.5 bg-slate-50 dark:bg-slate-950 p-4 rounded-2xl border border-slate-100">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Đối tượng nhận:</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ showCampaignSuccessDetails.segmentLabel }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Tổng số khách hàng gửi:</span>
                            <span class="font-bold text-indigo-600">{{ showCampaignSuccessDetails.targetCount }} KH</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Kênh truyền thông:</span>
                            <span class="font-bold text-slate-850 dark:text-slate-200">{{ showCampaignSuccessDetails.type }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Mã voucher phát hành:</span>
                            <span class="font-mono font-bold text-emerald-600">{{ showCampaignSuccessDetails.voucherCode }}</span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <span class="font-bold text-slate-700 dark:text-slate-350">Nội dung tin nhắn mẫu gửi đi:</span>
                        <div class="p-3 bg-slate-100 dark:bg-slate-800 rounded-xl italic font-medium leading-relaxed">
                            "{{ showCampaignSuccessDetails.message }}"
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t">
                        <Button type="button" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold" @click="showCampaignSuccessModal = false">
                            Tôi hiểu rồi (Hoàn tất)
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
