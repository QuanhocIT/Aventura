<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Sparkles,
    Clock,
    Search,
    Users,
    Trash2,
    RefreshCw,
    TrendingUp,
    Award,
    Zap,
    HelpCircle,
    Send,
    Play,
    CheckCircle2,
    Volume2,
    Compass,
    AlertTriangle,
    Eye,
    ShoppingCart,
    Info,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import Pagination from '@/components/super-admin/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    rfm_segment:
        | 'VIP'
        | 'Loyal'
        | 'New'
        | 'SalesHunter'
        | 'AboutToLeave'
        | 'Lost'
        | 'Potential';
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

interface Paginated<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    metrics: Metrics;
    customers: Paginated<CdpCustomer>;
    filters: { search: string; segment: string };
}>();

// --- STATE ---
const activeSubTab = ref<'analytics' | 'footprints' | 'campaigns'>('analytics');
const selectedSegment = ref<string>(props.filters.segment ?? 'all');
const searchQuery = ref(props.filters.search ?? '');
const isRecalculating = ref(false);

// Campaign states
const selectedCampaignSegment = ref('AboutToLeave');
const campaignType = ref<'sms' | 'zalo' | 'discount'>('discount');
const voucherCodeInput = ref('');
const messageText = ref(
    'Chào anh/chị, lâu rồi anh/chị chưa ghé Aventura. Tặng anh/chị mã giảm 20% [VOUCHER] áp dụng cho đơn tiếp theo nhé!',
);
const showCampaignSuccessModal = ref(false);
const showCampaignSuccessDetails = ref<any>(null);

// --- HELPERS & DICTIONARIES ---
const segmentConfig = {
    VIP: {
        label: 'Khách VIP / Champions',
        desc: 'Mua thường xuyên nhất, chi tiêu nhiều nhất. Cần quà tri ân và chăm sóc đặc quyền.',
        color: 'indigo',
        badgeClass:
            'bg-indigo-100 text-indigo-750 border-indigo-200 dark:bg-indigo-950/30 dark:text-indigo-400 dark:border-indigo-900/40',
        dotClass: 'bg-indigo-600',
        icon: '👑',
    },
    Loyal: {
        label: 'Khách hàng Trung thành',
        desc: 'Mua đều đặn, chi tiêu tốt. Cần giữ chân qua các chương trình tích điểm hội viên.',
        color: 'emerald',
        badgeClass:
            'bg-emerald-100 text-emerald-750 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/40',
        dotClass: 'bg-emerald-600',
        icon: '🥈',
    },
    New: {
        label: 'Khách mới đăng ký',
        desc: 'Mới mua gần đây, tần suất thấp. Cần email/tin nhắn chào mừng và gợi ý món hot.',
        color: 'sky',
        badgeClass:
            'bg-sky-100 text-sky-750 border-sky-200 dark:bg-sky-950/30 dark:text-sky-400 dark:border-sky-900/40',
        dotClass: 'bg-sky-500',
        icon: '🆕',
    },
    SalesHunter: {
        label: 'Khách hàng Săn Sale',
        desc: 'Chi tiêu thấp, mua nhiều hoặc có tỷ lệ áp mã giảm giá cao. Phù hợp combo giá tốt.',
        color: 'amber',
        badgeClass:
            'bg-amber-100 text-amber-750 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900/40',
        dotClass: 'bg-amber-500',
        icon: '🎯',
    },
    AboutToLeave: {
        label: 'Khách hàng Sắp rời đi',
        desc: 'Đã lâu chưa quay lại mặc dù trước đó mua nhiều. Cần tung mã giảm sâu để lôi kéo.',
        color: 'orange',
        badgeClass:
            'bg-orange-100 text-orange-750 border-orange-200 dark:bg-orange-950/30 dark:text-orange-400 dark:border-orange-900/40',
        dotClass: 'bg-orange-500',
        icon: '⚠️',
    },
    Lost: {
        label: 'Khách hàng Đã rời bỏ',
        desc: 'Quá hạn rất lâu không có giao dịch, chi tiêu thấp. Chi phí lôi kéo lại sẽ rất cao.',
        color: 'rose',
        badgeClass:
            'bg-rose-100 text-rose-750 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/40',
        dotClass: 'bg-rose-500',
        icon: '🛑',
    },
    Potential: {
        label: 'Khách hàng Tiềm năng',
        desc: 'Mức mua trung bình, tần suất ổn. Cần upselling combo gia tăng giá trị đơn.',
        color: 'slate',
        badgeClass:
            'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700',
        dotClass: 'bg-slate-500',
        icon: '✨',
    },
};

const footprintEventConfig = {
    view_menu: {
        label: 'Xem Thực đơn',
        class: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400',
        icon: Eye,
    },
    view_product: {
        label: 'Xem Món ăn',
        class: 'bg-blue-100 text-blue-750 dark:bg-blue-950/20 dark:text-blue-400',
        icon: Compass,
    },
    add_to_cart: {
        label: 'Thêm Giỏ hàng',
        class: 'bg-amber-100 text-amber-750 dark:bg-amber-950/20 dark:text-amber-400',
        icon: ShoppingCart,
    },
    remove_from_cart: {
        label: 'Xóa Giỏ hàng',
        class: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-450',
        icon: Trash2,
    },
    submit_order: {
        label: 'Gửi Đơn đệm',
        class: 'bg-indigo-100 text-indigo-750 dark:bg-indigo-950/20 dark:text-indigo-400',
        icon: Send,
    },
    payment_request: {
        label: 'Gọi Thanh toán',
        class: 'bg-emerald-100 text-emerald-750 dark:bg-emerald-950/20 dark:text-emerald-400',
        icon: CheckCircle2,
    },
    call_staff: {
        label: 'Gọi Phục vụ',
        class: 'bg-rose-100 text-rose-750 dark:bg-rose-950/20 dark:text-rose-400',
        icon: Volume2,
    },
    feedback_submitted: {
        label: 'Đánh giá Bàn',
        class: 'bg-purple-100 text-purple-750 dark:bg-purple-950/20 dark:text-purple-400',
        icon: Award,
    },
};

function numberFormat(val: number) {
    return val.toLocaleString('vi-VN');
}

// --- COMPUTED ---
// Lọc & phân trang chạy phía server (CdpController::index) — trang chỉ hiển thị
// đúng 20 khách của trang hiện tại thay vì tải toàn bộ danh sách về trình duyệt.
const paginatedCustomers = computed(() => props.customers.data);

// Gõ tìm kiếm thì hoãn 350ms rồi mới gọi server, tránh bắn request mỗi phím.
let searchTimer: ReturnType<typeof setTimeout> | undefined;

function applyFilters(immediate = false) {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    const go = () =>
        router.get(
            '/customers/cdp',
            { search: searchQuery.value, segment: selectedSegment.value },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    if (immediate) {
        go();
    } else {
        searchTimer = setTimeout(go, 350);
    }
}

watch(searchQuery, () => applyFilters());
watch(selectedSegment, () => applyFilters(true));

// Chart parameters
const maxSegmentCount = computed(() => {
    const counts = Object.values(props.metrics.segments).map((s) => s.count);

    return Math.max(...counts, 1);
});

// Đếm theo phân khúc lấy từ metrics (tính trên TOÀN BỘ khách), không đếm trên
// trang hiện tại — nếu không, số khách của chiến dịch sẽ sai khi có phân trang.
function segmentCount(key: string): number {
    return props.metrics.segments[key]?.count ?? 0;
}

const campaignTargetCount = computed(() =>
    selectedCampaignSegment.value === 'all'
        ? props.metrics.total_customers
        : segmentCount(selectedCampaignSegment.value),
);

// --- ACTIONS ---
const handleRecalculate = () => {
    isRecalculating.value = true;
    router.post(
        '/customers/cdp/recalculate',
        {},
        {
            onSuccess: () => {
                isRecalculating.value = false;
            },
            onError: () => {
                isRecalculating.value = false;
            },
        },
    );
};

const isRunningCampaign = ref(false);

const handleRunCampaign = () => {
    if (campaignTargetCount.value === 0 || isRunningCampaign.value) {
        return;
    }

    isRunningCampaign.value = true;
    const code =
        voucherCodeInput.value.trim() ||
        'VOUCHER-' +
            Math.random().toString(36).substring(2, 6).toUpperCase() +
            '-' +
            selectedCampaignSegment.value.substring(0, 3).toUpperCase();
    const finalMessage = messageText.value.replace('[VOUCHER]', code);
    const campaignName = `Chiến dịch ${selectedCampaignSegment.value === 'all' ? 'Tất cả' : selectedCampaignSegment.value} (${new Date().toLocaleDateString('vi-VN')})`;

    axios
        .post('/customers/cdp/campaigns', {
            name: campaignName,
            segment: selectedCampaignSegment.value,
            channel_type: campaignType.value,
            voucher_code: campaignType.value === 'discount' ? code : null,
            message_template: finalMessage,
        })
        .then((response) => {
            if (response.data.success) {
                showCampaignSuccessDetails.value = {
                    segmentLabel:
                        selectedCampaignSegment.value === 'all'
                            ? 'Tất cả khách hàng'
                            : segmentConfig[
                                  selectedCampaignSegment.value as keyof typeof segmentConfig
                              ]?.label || selectedCampaignSegment.value,
                    targetCount: response.data.campaign.target_count,
                    type:
                        campaignType.value === 'sms'
                            ? 'Tin nhắn SMS Brandname'
                            : campaignType.value === 'zalo'
                              ? 'Tin nhắn Zalo ZNS'
                              : 'Kịch bản Voucher điện tử',
                    message: finalMessage,
                    voucherCode: code,
                };
                showCampaignSuccessModal.value = true;
            }
        })
        .catch((error) => {
            console.error('Error running campaign:', error);
            toast.error(
                'Có lỗi xảy ra khi khởi chạy chiến dịch: ' +
                    (error.response?.data?.message || error.message),
            );
        })
        .finally(() => {
            isRunningCampaign.value = false;
        });
};

const triggerAutoVoucherCode = () => {
    voucherCodeInput.value =
        'AVEN' +
        selectedCampaignSegment.value.substring(0, 4).toUpperCase() +
        '20';
};
</script>

<template>
    <Head title="Nền tảng CDP & Phân tích RFM" />

    <div class="dashboard-shell mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- HEADER -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400"
                >
                    <Sparkles class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Nền tảng CDP & Phân tích RFM Chuyên sâu
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Thu thập "dấu chân" kỹ thuật số trên hệ sinh thái và
                        phân nhóm khách hàng tự động để tối ưu tiếp thị số.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    @click="handleRecalculate"
                    variant="outline"
                    class="flex h-10 items-center gap-1.5 border-indigo-200 text-xs font-semibold text-indigo-600 hover:bg-indigo-50"
                    :disabled="isRecalculating"
                >
                    <RefreshCw
                        class="size-4"
                        :class="isRecalculating ? 'animate-spin' : ''"
                    />
                    Làm mới Chấm điểm RFM
                </Button>
            </div>
        </div>

        <!-- CRM / CDP NAVIGATION TOGGLE -->
        <div class="flex items-center gap-2 border-b pb-2">
            <button
                type="button"
                @click="router.visit('/customers')"
                class="hover:border-slate-350 flex items-center gap-1.5 border-b-2 border-transparent px-4 py-2 text-xs font-bold text-slate-400 hover:text-slate-600 focus:outline-none"
            >
                👥 Hồ sơ CRM khách hàng
            </button>
            <button
                type="button"
                class="border-b-2 border-indigo-600 px-4 py-2 text-xs font-bold text-indigo-600 focus:outline-none"
            >
                ✨ Phân tích RFM & Hành vi (CDP)
            </button>
        </div>

        <!-- CDP CORE SUB-TABS -->
        <div
            class="flex w-fit items-center gap-1.5 rounded-xl border border-slate-200/50 bg-slate-100 p-0.5 dark:border-slate-800 dark:bg-slate-900"
        >
            <button
                @click="activeSubTab = 'analytics'"
                :class="[
                    'rounded-lg px-4 py-1.5 text-xs font-bold transition-all focus:outline-none',
                    activeSubTab === 'analytics'
                        ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
                        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300',
                ]"
            >
                📊 Phân nhóm RFM
            </button>
            <button
                @click="activeSubTab = 'footprints'"
                :class="[
                    'rounded-lg px-4 py-1.5 text-xs font-bold transition-all focus:outline-none',
                    activeSubTab === 'footprints'
                        ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
                        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300',
                ]"
            >
                👣 Dấu chân Hành vi (Real-time)
            </button>
            <button
                @click="activeSubTab = 'campaigns'"
                :class="[
                    'rounded-lg px-4 py-1.5 text-xs font-bold transition-all focus:outline-none',
                    activeSubTab === 'campaigns'
                        ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
                        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300',
                ]"
            >
                🚀 Chiến dịch Retargeting
            </button>
        </div>

        <!-- ── VIEW 1: RFM ANALYTICS ── -->
        <div v-if="activeSubTab === 'analytics'" class="space-y-6">
            <!-- CDP KPI STATS -->
            <div class="dashboard-kpi-grid">
                <Card
                    class="border-indigo-100/30 bg-gradient-to-br from-indigo-50/20 to-white shadow-xs transition-all hover:translate-y-[-2px] dark:from-indigo-950/10 dark:to-slate-900"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardDescription
                            class="text-[10px] font-black tracking-wider text-indigo-500 uppercase"
                            >Doanh thu CDP Tích Lũy</CardDescription
                        >
                        <TrendingUp class="size-4 text-indigo-600" />
                    </CardHeader>
                    <CardContent class="pb-3">
                        <span
                            class="text-2xl font-black text-indigo-600 dark:text-indigo-400"
                            >{{ numberFormat(metrics.total_revenue) }}đ</span
                        >
                        <p class="text-xxs mt-1 text-muted-foreground">
                            Phát sinh từ tệp khách hàng định danh
                        </p>
                    </CardContent>
                </Card>

                <Card class="shadow-xs transition-all hover:translate-y-[-2px]">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardDescription
                            class="text-[10px] font-black tracking-wider text-slate-400 uppercase"
                            >Giá trị Vòng đời KH (CLV)</CardDescription
                        >
                        <Award class="text-slate-450 size-4" />
                    </CardHeader>
                    <CardContent class="pb-3">
                        <span
                            class="text-slate-850 text-2xl font-black dark:text-slate-100"
                            >{{ numberFormat(metrics.avg_clv) }}đ</span
                        >
                        <p class="text-xxs mt-1 text-muted-foreground">
                            Chi tiêu trung bình trên mỗi khách hàng
                        </p>
                    </CardContent>
                </Card>

                <Card class="shadow-xs transition-all hover:translate-y-[-2px]">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardDescription
                            class="text-[10px] font-black tracking-wider text-slate-400 uppercase"
                            >Tần suất đặt hàng</CardDescription
                        >
                        <Clock class="text-slate-450 size-4" />
                    </CardHeader>
                    <CardContent class="pb-3">
                        <span
                            class="text-slate-850 text-2xl font-black dark:text-slate-100"
                            >{{ metrics.avg_frequency }} lần</span
                        >
                        <p class="text-xxs mt-1 text-muted-foreground">
                            Tần suất giao dịch bình quân/khách
                        </p>
                    </CardContent>
                </Card>

                <Card
                    class="border-emerald-100/30 bg-gradient-to-br from-emerald-50/10 to-white shadow-xs transition-all hover:translate-y-[-2px] dark:from-emerald-950/10 dark:to-slate-900"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardDescription
                            class="text-[10px] font-black tracking-wider text-emerald-500 uppercase"
                            >Nhóm Khách VIP / Loyal</CardDescription
                        >
                        <Users class="size-4 text-emerald-600" />
                    </CardHeader>
                    <CardContent class="pb-3">
                        <span
                            class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                        >
                            {{
                                (metrics.segments.VIP?.count ?? 0) +
                                (metrics.segments.Loyal?.count ?? 0)
                            }}
                            KH
                        </span>
                        <p class="text-xxs mt-1 text-muted-foreground">
                            Đóng góp
                            {{
                                Math.round(
                                    (((metrics.segments.VIP?.revenue ?? 0) +
                                        (metrics.segments.Loyal?.revenue ??
                                            0)) /
                                        (metrics.total_revenue || 1)) *
                                        100,
                                )
                            }}% doanh số F&B
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- RFM DISTRIBUTION CHART & BREAKDOWN -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- CHART CARD (SVG Visualizer) -->
                <Card
                    class="flex flex-col justify-between overflow-hidden shadow-sm lg:col-span-1"
                >
                    <CardHeader
                        class="border-b bg-slate-50/50 dark:bg-slate-900/10"
                    >
                        <CardTitle
                            class="flex items-center gap-1.5 text-sm font-bold"
                        >
                            <TrendingUp class="size-4 text-indigo-500" />
                            Phân bố Tệp Khách hàng theo RFM
                        </CardTitle>
                        <CardDescription
                            >Biểu đồ phân chia số lượng khách hàng theo các cụm
                            hành vi chi tiêu.</CardDescription
                        >
                    </CardHeader>
                    <CardContent
                        class="flex flex-1 flex-col justify-center gap-6 p-6"
                    >
                        <!-- Custom HTML/SVG Bar Chart -->
                        <div class="space-y-3">
                            <div
                                v-for="(cfg, key) in segmentConfig"
                                :key="key"
                                class="flex cursor-pointer flex-col gap-1 rounded-lg p-1.5 transition-colors hover:bg-slate-50 dark:hover:bg-slate-900"
                                @click="selectedSegment = key"
                            >
                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span
                                        class="flex items-center gap-1 font-bold text-slate-700 dark:text-slate-300"
                                    >
                                        <span>{{ cfg.icon }}</span>
                                        <span>{{
                                            cfg.label.split(' / ')[0]
                                        }}</span>
                                    </span>
                                    <span
                                        class="font-mono font-bold text-slate-500"
                                    >
                                        {{ metrics.segments[key]?.count ?? 0 }}
                                        KH ({{
                                            Math.round(
                                                ((metrics.segments[key]
                                                    ?.count ?? 0) /
                                                    (metrics.total_customers ||
                                                        1)) *
                                                    100,
                                            )
                                        }}%)
                                    </span>
                                </div>
                                <div
                                    class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="cfg.dotClass"
                                        :style="`width: ${((metrics.segments[key]?.count ?? 0) / maxSegmentCount) * 100}%`"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <!-- Mini Info Alert -->
                        <div
                            class="flex items-start gap-2 rounded-xl border border-amber-100 bg-amber-50/50 p-3 text-[10px] text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-400"
                        >
                            <Info
                                class="mt-0.5 size-4 shrink-0 text-amber-600"
                            />
                            <p>
                                <strong>Bí kíp tiếp thị:</strong> Khách hàng
                                nhóm <strong>Sắp rời đi</strong> nên được kích
                                hoạt bằng chiến dịch giảm giá đặc biệt trước khi
                                họ chuyển hẳn sang nhóm
                                <strong>Đã rời bỏ</strong>.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- LIST CARD -->
                <Card
                    class="flex flex-col justify-between overflow-hidden shadow-sm lg:col-span-2"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between gap-4 border-b bg-slate-50/50 dark:bg-slate-900/10"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-sm font-bold"
                            >
                                <Users class="size-4 text-indigo-500" />
                                Danh sách Khách hàng Phân cụm RFM
                            </CardTitle>
                            <CardDescription
                                >Bấm chọn bộ lọc nhóm cụm hoặc tìm kiếm thông
                                tin khách hàng.</CardDescription
                            >
                        </div>

                        <!-- Segment Switcher -->
                        <select
                            v-model="selectedSegment"
                            class="dark:text-slate-350 h-9 rounded-xl border border-slate-200 bg-white px-3 text-xs text-slate-700 focus:ring-2 focus:ring-indigo-400 focus:outline-none dark:border-slate-700 dark:bg-slate-800"
                        >
                            <option value="all">Tất cả phân cụm</option>
                            <option
                                v-for="(cfg, key) in segmentConfig"
                                :key="key"
                                :value="key"
                            >
                                {{ cfg.icon }} {{ cfg.label.split(' / ')[0] }}
                            </option>
                        </select>
                    </CardHeader>

                    <CardContent
                        class="flex flex-1 flex-col justify-between p-0"
                    >
                        <!-- Search and Segment Desc -->
                        <div
                            class="flex flex-col gap-3 border-b bg-slate-50/20 p-4"
                        >
                            <div
                                v-if="selectedSegment !== 'all'"
                                class="dark:bg-slate-850 flex items-start gap-2 rounded-xl border bg-slate-100/50 p-3 text-xs text-slate-600 dark:text-slate-400"
                            >
                                <span class="text-lg leading-none">{{
                                    segmentConfig[
                                        selectedSegment as keyof typeof segmentConfig
                                    ]?.icon
                                }}</span>
                                <div>
                                    <span
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{
                                            segmentConfig[
                                                selectedSegment as keyof typeof segmentConfig
                                            ]?.label
                                        }}
                                    </span>
                                    <p class="mt-0.5 text-[11px]">
                                        {{
                                            segmentConfig[
                                                selectedSegment as keyof typeof segmentConfig
                                            ]?.desc
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="relative max-w-sm">
                                <Search
                                    class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                                />
                                <Input
                                    type="text"
                                    placeholder="Tìm theo mã KH, SĐT, Tên..."
                                    v-model="searchQuery"
                                    class="h-9 bg-white pl-8 text-xs"
                                />
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="flex-1 overflow-x-auto">
                            <table
                                class="w-full border-collapse text-left text-xs"
                            >
                                <thead>
                                    <tr
                                        class="border-b bg-slate-100 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                                    >
                                        <th class="p-3">Mã KH</th>
                                        <th class="p-3">Khách hàng</th>
                                        <th class="p-3">Số điện thoại</th>
                                        <th class="p-3 text-center">
                                            RFM Score
                                        </th>
                                        <th class="p-3 text-right">
                                            Tổng chi tiêu
                                        </th>
                                        <th class="p-3 text-center">Số đơn</th>
                                        <th class="p-3 text-center">
                                            Giao dịch cuối
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-slate-800"
                                >
                                    <tr v-if="paginatedCustomers.length === 0">
                                        <td
                                            colspan="7"
                                            class="p-10 text-center text-slate-400"
                                        >
                                            Không tìm thấy khách hàng nào thỏa
                                            mãn bộ lọc.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="c in paginatedCustomers"
                                        :key="c.id"
                                        class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                                    >
                                        <td
                                            class="dark:text-indigo-450 p-3 font-mono font-bold text-indigo-600"
                                        >
                                            {{ c.customer_code }}
                                        </td>
                                        <td class="p-3">
                                            <div
                                                class="font-bold text-slate-800 dark:text-slate-200"
                                            >
                                                {{ c.full_name }}
                                            </div>
                                            <span
                                                class="mt-0.5 inline-block rounded border px-1.5 py-0.5 text-[9px] font-bold"
                                                :class="
                                                    segmentConfig[c.rfm_segment]
                                                        ?.badgeClass
                                                "
                                            >
                                                {{
                                                    segmentConfig[c.rfm_segment]
                                                        ?.icon
                                                }}
                                                {{
                                                    segmentConfig[
                                                        c.rfm_segment
                                                    ]?.label.split(' / ')[0]
                                                }}
                                            </span>
                                        </td>
                                        <td class="p-3 font-mono font-bold">
                                            {{ c.phone }}
                                        </td>
                                        <td class="p-3 text-center">
                                            <span
                                                class="rounded border bg-slate-100 px-2 py-0.5 font-mono font-black text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                            >
                                                {{ c.rfm_score_code }}
                                            </span>
                                        </td>
                                        <td
                                            class="p-3 text-right font-mono font-bold text-indigo-600 dark:text-indigo-400"
                                        >
                                            {{
                                                numberFormat(c.monetary_amount)
                                            }}đ
                                        </td>
                                        <td class="p-3 text-center font-mono">
                                            {{ c.frequency_count }} đơn
                                        </td>
                                        <td
                                            class="p-3 text-center font-mono text-slate-500"
                                        >
                                            <span
                                                v-if="c.recency_days === 999"
                                                class="text-slate-300 italic"
                                                >Chưa có</span
                                            >
                                            <span v-else
                                                >{{ c.recency_days }} ngày
                                                trước</span
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination (phía server, dùng component chung) -->
                        <div
                            v-if="customers.total > 0"
                            class="border-t bg-slate-50/50 dark:bg-slate-900/30"
                        >
                            <div
                                class="px-4 pt-3 text-[10px] text-muted-foreground"
                            >
                                Hiển thị {{ customers.from ?? 0 }} -
                                {{ customers.to ?? 0 }} trong tổng số
                                {{ customers.total }} khách hàng
                            </div>
                            <Pagination :links="customers.links" />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- ── VIEW 2: REAL-TIME FOOTPRINTS ── -->
        <div v-if="activeSubTab === 'footprints'" class="space-y-6">
            <Card class="overflow-hidden shadow-sm">
                <CardHeader
                    class="border-b bg-slate-50/50 dark:bg-slate-900/10"
                >
                    <CardTitle
                        class="flex items-center gap-1.5 text-sm font-bold"
                    >
                        <Clock class="size-4 text-indigo-500" />
                        Dòng sự kiện Dấu chân hành vi khách hàng F&B
                    </CardTitle>
                    <CardDescription
                        >Hệ thống tự động ghi nhận theo thời gian thực
                        (Real-time tracking) hoạt động xem menu, thêm giỏ hàng,
                        và đặt món.</CardDescription
                    >
                </CardHeader>
                <CardContent class="p-6">
                    <div
                        v-if="metrics.recent_logs.length === 0"
                        class="py-20 text-center text-slate-400"
                    >
                        Chưa có dữ liệu "dấu chân" hành vi nào được ghi nhận.
                        Quét QR gọi món tại bàn để khởi tạo luồng sự kiện!
                    </div>
                    <div
                        v-else
                        class="relative ml-4 space-y-6 border-l border-slate-200 dark:border-slate-800"
                    >
                        <div
                            v-for="log in metrics.recent_logs"
                            :key="log.id"
                            class="relative pl-6 transition-transform hover:translate-x-1"
                        >
                            <!-- Dot timeline -->
                            <div
                                class="absolute top-1 -left-2.5 flex h-5 w-5 items-center justify-center rounded-full border-2 border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950"
                            >
                                <div
                                    class="size-2 rounded-full bg-indigo-500"
                                ></div>
                            </div>

                            <!-- Timeline Item Body -->
                            <div
                                class="flex flex-col justify-between gap-4 rounded-2xl border bg-slate-50/50 p-4 sm:flex-row sm:items-center dark:bg-slate-900/30"
                            >
                                <div class="flex items-start gap-3 text-left">
                                    <!-- Event Badge Icon -->
                                    <div
                                        class="flex size-8 items-center justify-center rounded-xl"
                                        :class="
                                            footprintEventConfig[
                                                log.event_type as keyof typeof footprintEventConfig
                                            ]?.class ?? 'bg-slate-100'
                                        "
                                    >
                                        <component
                                            :is="
                                                footprintEventConfig[
                                                    log.event_type as keyof typeof footprintEventConfig
                                                ]?.icon ?? HelpCircle
                                            "
                                            class="size-4.5"
                                        />
                                    </div>

                                    <div>
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="text-xs font-black text-slate-800 dark:text-slate-200"
                                            >
                                                {{ log.customer_name }}
                                            </span>
                                            <span
                                                v-if="log.customer_phone"
                                                class="font-mono text-[10px] text-slate-400"
                                            >
                                                ({{ log.customer_phone }})
                                            </span>
                                            <span
                                                class="rounded border px-2 py-0.5 text-[9px] font-bold"
                                                :class="
                                                    footprintEventConfig[
                                                        log.event_type as keyof typeof footprintEventConfig
                                                    ]?.class
                                                "
                                            >
                                                {{
                                                    footprintEventConfig[
                                                        log.event_type as keyof typeof footprintEventConfig
                                                    ]?.label ?? log.event_type
                                                }}
                                            </span>
                                        </div>
                                        <p
                                            class="mt-1 text-[11px] text-slate-500 dark:text-slate-400"
                                        >
                                            <!-- Contextual text based on event -->
                                            <span
                                                v-if="
                                                    log.event_type ===
                                                    'view_menu'
                                                "
                                                >Truy cập liên kết thực đơn điện
                                                tử</span
                                            >
                                            <span
                                                v-else-if="
                                                    log.event_type ===
                                                    'view_product'
                                                "
                                                >Xem chi tiết món ăn:
                                                <strong
                                                    class="text-indigo-650 dark:text-indigo-400"
                                                    >{{
                                                        log.product_name
                                                    }}</strong
                                                ></span
                                            >
                                            <span
                                                v-else-if="
                                                    log.event_type ===
                                                    'add_to_cart'
                                                "
                                                >Thêm
                                                <strong
                                                    class="text-indigo-650 dark:text-indigo-400"
                                                    >{{ log.quantity }}x
                                                    {{
                                                        log.product_name
                                                    }}</strong
                                                >
                                                vào giỏ hàng</span
                                            >
                                            <span
                                                v-else-if="
                                                    log.event_type ===
                                                    'remove_from_cart'
                                                "
                                                >Xóa/giảm món
                                                <strong
                                                    class="text-slate-650 dark:text-slate-400"
                                                    >{{
                                                        log.product_name
                                                    }}</strong
                                                >
                                                khỏi giỏ hàng</span
                                            >
                                            <span
                                                v-else-if="
                                                    log.event_type ===
                                                    'submit_order'
                                                "
                                                >Gửi yêu cầu đặt món QR (#{{
                                                    log.meta_data
                                                        ?.temporary_order_id
                                                }}) trị giá
                                                <strong class="text-indigo-600"
                                                    >{{
                                                        numberFormat(
                                                            log.meta_data
                                                                ?.amount ?? 0,
                                                        )
                                                    }}đ</strong
                                                ></span
                                            >
                                            <span
                                                v-else-if="
                                                    log.event_type ===
                                                    'payment_request'
                                                "
                                                >Bấm chuông gọi nhân viên thanh
                                                toán hóa đơn</span
                                            >
                                            <span
                                                v-else-if="
                                                    log.event_type ===
                                                    'call_staff'
                                                "
                                                >Báo chuông yêu cầu phục vụ tại
                                                bàn</span
                                            >
                                            <span
                                                v-else-if="
                                                    log.event_type ===
                                                    'feedback_submitted'
                                                "
                                                >Gửi biểu mẫu đánh giá bàn phục
                                                vụ ({{
                                                    log.meta_data?.rating
                                                }}⭐)</span
                                            >
                                            <span v-else
                                                >Hành động
                                                {{ log.event_type }}</span
                                            >
                                        </p>
                                        <div
                                            class="mt-1 font-mono text-[9px] text-slate-400"
                                        >
                                            Session ID:
                                            {{
                                                log.session_id.substring(0, 16)
                                            }}... • URL:
                                            {{
                                                log.meta_data?.url
                                                    ? log.meta_data.url.split(
                                                          'www',
                                                      )[1] || '/'
                                                    : '/'
                                            }}
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="flex shrink-0 flex-col items-end justify-between text-right"
                                >
                                    <span
                                        class="flex items-center gap-1 font-mono text-[10px] text-slate-400"
                                    >
                                        <Clock class="size-3" />
                                        {{ log.created_at }}
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
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Campaign configuration form -->
                <Card class="overflow-hidden text-left shadow-sm">
                    <CardHeader
                        class="border-b bg-slate-50/50 dark:bg-slate-900/10"
                    >
                        <CardTitle
                            class="flex items-center gap-1.5 text-sm font-bold text-indigo-600"
                        >
                            <Zap class="size-5 animate-pulse" />
                            Khởi chạy chiến dịch Bám đuổi khách hàng
                            (Retargeting)
                        </CardTitle>
                        <CardDescription
                            >Cấu hình chương trình tri ân/kích cầu gửi tự động
                            qua SMS/Zalo hoặc phát hành voucher cho tệp khách
                            hàng RFM mục tiêu.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4 p-6">
                        <!-- Step 1: Choose segment target -->
                        <div class="grid gap-1.5">
                            <label
                                class="dark:text-slate-350 text-xs font-bold text-slate-700"
                                >1. Tệp khách hàng mục tiêu</label
                            >
                            <select
                                v-model="selectedCampaignSegment"
                                @change="triggerAutoVoucherCode"
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            >
                                <option value="all">
                                    Tất cả khách hàng ({{
                                        metrics.total_customers
                                    }}
                                    KH)
                                </option>
                                <option
                                    v-for="(cfg, key) in segmentConfig"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ cfg.icon }} {{ cfg.label }} ({{
                                        segmentCount(key)
                                    }}
                                    KH)
                                </option>
                            </select>
                        </div>

                        <!-- Step 2: Choose campaign channel type -->
                        <div class="grid gap-1.5">
                            <label
                                class="dark:text-slate-350 text-xs font-bold text-slate-700"
                                >2. Kênh phân phối</label
                            >
                            <div class="grid grid-cols-3 gap-2">
                                <Button
                                    variant="outline"
                                    class="h-10 rounded-xl text-xs"
                                    :class="
                                        campaignType === 'discount'
                                            ? 'text-indigo-650 border-indigo-600 bg-indigo-50/50 font-bold'
                                            : ''
                                    "
                                    @click="campaignType = 'discount'"
                                >
                                    🎫 Voucher điện tử
                                </Button>
                                <Button
                                    variant="outline"
                                    class="h-10 rounded-xl text-xs"
                                    :class="
                                        campaignType === 'sms'
                                            ? 'text-indigo-650 border-indigo-600 bg-indigo-50/50 font-bold'
                                            : ''
                                    "
                                    @click="campaignType = 'sms'"
                                >
                                    💬 SMS Brandname
                                </Button>
                                <Button
                                    variant="outline"
                                    class="h-10 rounded-xl text-xs"
                                    :class="
                                        campaignType === 'zalo'
                                            ? 'text-indigo-650 border-indigo-600 bg-indigo-50/50 font-bold'
                                            : ''
                                    "
                                    @click="campaignType = 'zalo'"
                                >
                                    📱 Zalo ZNS
                                </Button>
                            </div>
                        </div>

                        <!-- Step 3: Voucher Code input -->
                        <div class="grid gap-1.5">
                            <label
                                class="dark:text-slate-350 text-xs font-bold text-slate-700"
                                >3. Mã Voucher khuyến mãi</label
                            >
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
                            <label
                                class="dark:text-slate-350 text-xs font-bold text-slate-700"
                                >4. Nội dung thông điệp chiến dịch</label
                            >
                            <textarea
                                v-model="messageText"
                                rows="3"
                                class="w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            ></textarea>
                            <p class="text-[10px] text-muted-foreground">
                                * Từ khóa <strong>[VOUCHER]</strong> sẽ tự động
                                chuyển thành mã voucher khi gửi đi.
                            </p>
                        </div>

                        <!-- Submit button -->
                        <div class="border-t pt-3">
                            <Button
                                class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                                :disabled="
                                    campaignTargetCount === 0 ||
                                    isRunningCampaign
                                "
                                @click="handleRunCampaign"
                            >
                                <Play
                                    v-if="!isRunningCampaign"
                                    class="size-4"
                                />
                                <RefreshCw v-else class="size-4 animate-spin" />
                                {{
                                    isRunningCampaign
                                        ? 'Đang gửi tin...'
                                        : `Khởi chạy chiến dịch (gửi tới ${campaignTargetCount} khách hàng)`
                                }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Target preview / Explanation card -->
                <div class="flex flex-col gap-6">
                    <Card class="flex-1 text-left shadow-sm">
                        <CardHeader
                            class="border-b bg-slate-50/50 dark:bg-slate-900/10"
                        >
                            <CardTitle class="text-sm font-bold"
                                >Thuyết minh thông số & Tỉ lệ chuyển đổi ước
                                tính</CardTitle
                            >
                        </CardHeader>
                        <CardContent
                            class="space-y-4 p-6 text-xs text-slate-600 dark:text-slate-400"
                        >
                            <div
                                class="flex flex-col gap-1.5 rounded-xl border border-indigo-100 bg-indigo-50/20 p-3"
                            >
                                <span
                                    class="font-bold text-indigo-700 dark:text-indigo-400"
                                    >Phân tích tệp chọn:</span
                                >
                                <p>
                                    Phân nhóm
                                    <strong>{{
                                        selectedCampaignSegment === 'all'
                                            ? 'Tất cả khách hàng'
                                            : segmentConfig[
                                                  selectedCampaignSegment as keyof typeof segmentConfig
                                              ]?.label
                                    }}</strong>
                                    gồm có
                                    <strong
                                        >{{ campaignTargetCount }} khách
                                        hàng</strong
                                    >
                                    đăng ký.
                                </p>
                            </div>
                            <div class="space-y-3">
                                <div
                                    class="flex items-center justify-between border-b pb-2"
                                >
                                    <span class="font-semibold"
                                        >Tỉ lệ mở đọc trung bình:</span
                                    >
                                    <span
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{
                                            campaignType === 'sms'
                                                ? '98%'
                                                : campaignType === 'zalo'
                                                  ? '85%'
                                                  : '45%'
                                        }}
                                    </span>
                                </div>
                                <div
                                    class="flex items-center justify-between border-b pb-2"
                                >
                                    <span class="font-semibold"
                                        >Tỉ lệ click áp voucher ước tính:</span
                                    >
                                    <span class="font-bold text-emerald-600">
                                        {{
                                            selectedCampaignSegment === 'VIP'
                                                ? '25%'
                                                : selectedCampaignSegment ===
                                                    'AboutToLeave'
                                                  ? '18%'
                                                  : '10%'
                                        }}
                                    </span>
                                </div>
                                <div
                                    class="flex items-center justify-between border-b pb-2"
                                >
                                    <span class="font-semibold"
                                        >Doanh số thu hồi ước tính:</span
                                    >
                                    <span class="font-bold text-indigo-600">
                                        {{
                                            numberFormat(
                                                campaignTargetCount *
                                                    (selectedCampaignSegment ===
                                                    'VIP'
                                                        ? 350000
                                                        : 180000) *
                                                    0.15,
                                            )
                                        }}đ
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold"
                                        >Chi phí chiến dịch:</span
                                    >
                                    <span class="font-mono text-slate-500">
                                        {{
                                            numberFormat(
                                                campaignTargetCount *
                                                    (campaignType === 'sms'
                                                        ? 700
                                                        : campaignType ===
                                                            'zalo'
                                                          ? 350
                                                          : 0),
                                            )
                                        }}đ
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Campaign instructions warning card -->
                    <div
                        class="flex items-start gap-3 rounded-3xl border border-indigo-200 bg-indigo-50/20 p-4 text-left text-xs text-indigo-800 dark:border-indigo-900/40 dark:bg-indigo-950/20 dark:text-indigo-400"
                    >
                        <AlertTriangle
                            class="mt-0.5 size-5 shrink-0 text-indigo-500"
                        />
                        <div>
                            <span class="font-bold"
                                >Chính sách gửi tin an toàn CDP</span
                            >
                            <p class="mt-1 leading-relaxed">
                                Để tránh spam khách hàng và duy trì điểm số tin
                                cậy Brandname, hệ thống giới hạn tối đa 1 chiến
                                dịch tự động gửi đến cùng 1 nhóm khách hàng
                                trong vòng 7 ngày. Hãy chắc chắn nội dung thông
                                điệp mang lại lợi ích thực tế cho thực khách.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── MODAL: SUCCESSFUL CAMPAIGN LAUNCH (MOCK) ── -->
        <Teleport to="body">
            <div
                v-if="showCampaignSuccessModal && showCampaignSuccessDetails"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            >
                <Card
                    class="w-full max-w-md animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader class="border-b pb-3 text-center">
                        <div
                            class="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-emerald-100 text-xl font-bold text-emerald-600 dark:bg-emerald-950/30 dark:text-emerald-400"
                        >
                            ✓
                        </div>
                        <CardTitle class="text-base text-emerald-600"
                            >Chiến dịch Tiếp thị đã Khởi chạy thành công!</CardTitle
                        >
                        <CardDescription
                            >Hệ thống CDP & SMS Gateway đã tiếp nhận kịch bản gửi
                            hàng loạt.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4 pt-4 text-left text-xs">
                        <div
                            class="space-y-2.5 rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:bg-slate-950"
                        >
                            <div class="flex justify-between">
                                <span class="text-slate-500">Đối tượng nhận:</span>
                                <span
                                    class="font-bold text-slate-800 dark:text-slate-200"
                                    >{{
                                        showCampaignSuccessDetails.segmentLabel
                                    }}</span
                                >
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500"
                                    >Tổng số khách hàng gửi:</span
                                >
                                <span class="font-bold text-indigo-600"
                                    >{{
                                        showCampaignSuccessDetails.targetCount
                                    }}
                                    KH</span
                                >
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500"
                                    >Kênh truyền thông:</span
                                >
                                <span
                                    class="text-slate-850 font-bold dark:text-slate-200"
                                    >{{ showCampaignSuccessDetails.type }}</span
                                >
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500"
                                    >Mã voucher phát hành:</span
                                >
                                <span
                                    class="font-mono font-bold text-emerald-600"
                                    >{{
                                        showCampaignSuccessDetails.voucherCode
                                    }}</span
                                >
                            </div>
                        </div>

                        <div class="space-y-1">
                            <span
                                class="dark:text-slate-350 font-bold text-slate-700"
                                >Nội dung tin nhắn mẫu gửi đi:</span
                            >
                            <div
                                class="rounded-xl bg-slate-100 p-3 leading-relaxed font-medium italic dark:bg-slate-800"
                            >
                                "{{ showCampaignSuccessDetails.message }}"
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 border-t pt-2">
                            <Button
                                type="button"
                                class="bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                                @click="showCampaignSuccessModal = false"
                            >
                                Tôi hiểu rồi (Hoàn tất)
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </Teleport>
    </div>
</template>
