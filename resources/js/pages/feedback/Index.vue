<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Star,
    CheckCircle2,
    Search,
    ShieldAlert,
    Award,
    MessageSquare,
    Coffee,
    Flame,
    Percent,
    Utensils,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface Feedback {
    id: number;
    submitted_by_name: string;
    submitted_by_phone: string | null;
    rating: number;
    content: string | null;
    sentiment: 'positive' | 'neutral' | 'negative' | null;
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
    items_rating?: {
        product_id: number;
        name: string;
        rating: number;
        comment: string;
    }[];
    staff_rating?: {
        employee_id: number;
        name: string;
        rating: number;
        comment: string;
    }[];
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
const searchQuery = ref('');
const dateFilter = ref<'all' | 'today' | 'week' | 'month'>('all');
const showResolveModal = ref(false);
const selectedFeedback = ref<Feedback | null>(null);

// Form handling
const resolveForm = useForm({
    compensation_voucher: '',
    resolution_notes: '',
    status: 'resolved' as 'reviewed' | 'resolved',
});

// --- COMPUTED ---
function isWithinDateFilter(dateStr: string): boolean {
    if (dateFilter.value === 'all') {
        return true;
    }

    const d = new Date(dateStr);
    const now = new Date();

    if (dateFilter.value === 'today') {
        return d.toDateString() === now.toDateString();
    }

    if (dateFilter.value === 'week') {
        const weekAgo = new Date(now);
        weekAgo.setDate(now.getDate() - 7);

        return d >= weekAgo;
    }

    if (dateFilter.value === 'month') {
        return (
            d.getMonth() === now.getMonth() &&
            d.getFullYear() === now.getFullYear()
        );
    }

    return true;
}

const filteredFeedbacks = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();

    return props.feedbacks.filter((fb) => {
        const matchFilter =
            activeFilter.value === 'all' ||
            (activeFilter.value === 'new' && fb.status === 'new') ||
            (activeFilter.value === 'resolved' &&
                (fb.status === 'resolved' || fb.status === 'reviewed')) ||
            (activeFilter.value === 'critical' && fb.rating <= 2);

        if (!matchFilter) {
            return false;
        }

        if (!isWithinDateFilter(fb.created_at)) {
            return false;
        }

        if (!q) {
            return true;
        }

        return (
            (fb.submitted_by_name ?? '').toLowerCase().includes(q) ||
            (fb.content ?? '').toLowerCase().includes(q) ||
            (fb.order_number ?? '').toLowerCase().includes(q) ||
            fb.table_name.toLowerCase().includes(q)
        );
    });
});

// AI Emergency Crisis Feedbacks (Unresolved 1-2 Stars feedbacks)
const crisisFeedbacks = computed(() => {
    return props.feedbacks.filter(
        (fb) => fb.rating <= 2 && fb.status === 'new',
    );
});

// Thống kê các món ăn bị đánh giá thấp (điểm trung bình <= 3.5)
const lowRatedItems = computed(() => {
    const itemRatings: Record<string, { totalRating: number; count: number }> =
        {};
    props.feedbacks.forEach((fb) => {
        if (fb.items_rating) {
            fb.items_rating.forEach((ir) => {
                if (!itemRatings[ir.name]) {
                    itemRatings[ir.name] = { totalRating: 0, count: 0 };
                }

                itemRatings[ir.name].totalRating += ir.rating;
                itemRatings[ir.name].count += 1;
            });
        }
    });

    return Object.entries(itemRatings)
        .map(([name, data]) => ({
            name,
            avgRating: Math.round((data.totalRating / data.count) * 10) / 10,
            count: data.count,
        }))
        .filter((item) => item.avgRating <= 3.5)
        .sort((a, b) => a.avgRating - b.avgRating);
});

// Thống kê nhân sự bị đánh giá thấp (điểm trung bình <= 3.5)
const lowRatedStaff = computed(() => {
    const staffRatings: Record<string, { totalRating: number; count: number }> =
        {};
    props.feedbacks.forEach((fb) => {
        if (fb.staff_rating) {
            fb.staff_rating.forEach((sr) => {
                if (!staffRatings[sr.name]) {
                    staffRatings[sr.name] = { totalRating: 0, count: 0 };
                }

                staffRatings[sr.name].totalRating += sr.rating;
                staffRatings[sr.name].count += 1;
            });
        }
    });

    return Object.entries(staffRatings)
        .map(([name, data]) => ({
            name,
            avgRating: Math.round((data.totalRating / data.count) * 10) / 10,
            count: data.count,
        }))
        .filter((s) => s.avgRating <= 3.5)
        .sort((a, b) => a.avgRating - b.avgRating);
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
    if (!selectedFeedback.value) {
        return;
    }

    resolveForm.post(`/feedback/${selectedFeedback.value.id}/resolve`, {
        onSuccess: () => {
            showResolveModal.value = false;
            selectedFeedback.value = null;
            resolveForm.reset();
        },
    });
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(val);
};

const ratingStarsConfig: Record<
    number,
    { text: string; bg: string; text_color: string }
> = {
    5: {
        text: 'Xuất sắc',
        bg: 'bg-emerald-50 dark:bg-emerald-950/20',
        text_color: 'text-emerald-600 dark:text-emerald-400',
    },
    4: {
        text: 'Tốt',
        bg: 'bg-teal-50 dark:bg-teal-950/20',
        text_color: 'text-teal-600 dark:text-teal-400',
    },
    3: {
        text: 'Tạm ổn',
        bg: 'bg-amber-50 dark:bg-amber-950/20',
        text_color: 'text-amber-600 dark:text-amber-400',
    },
    2: {
        text: 'Tệ',
        bg: 'bg-orange-50 dark:bg-orange-950/20',
        text_color: 'text-orange-600 dark:text-orange-400',
    },
    1: {
        text: 'Rất tệ',
        bg: 'bg-rose-50 dark:bg-rose-950/20',
        text_color: 'text-rose-600 dark:text-rose-400',
    },
};

const sentimentConfig: Record<string, { text: string; class: string }> = {
    positive: {
        text: '😊 Tích cực',
        class: 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400',
    },
    neutral: {
        text: '😐 Trung tính',
        class: 'bg-slate-100 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400',
    },
    negative: {
        text: '😟 Tiêu cực',
        class: 'bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400',
    },
};
</script>

<template>
    <Head title="Quản lý Phản hồi Khách hàng & AI Crisis Control" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- POS Header -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 shadow-sm dark:bg-indigo-950/60 dark:text-indigo-400"
                >
                    <MessageSquare class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Xử Lý Phản Hồi & Dập Lửa Khủng Hoảng
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Thu thập đánh giá thời gian thực từ QR bàn ăn, phát hiện
                        khủng hoảng và giải quyết đền bù voucher ngay lập tức.
                    </p>
                </div>
            </div>
            <!-- Sync indicators -->
            <div class="flex items-center gap-2">
                <div
                    class="animate-pulse rounded-full border border-emerald-100 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-600 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-400"
                >
                    Đồng bộ QR Bàn Ăn: Hoạt động
                </div>
            </div>
        </div>

        <!-- AI EMERGENCY CRISIS ALERTS PANEL -->
        <div v-if="crisisFeedbacks.length > 0" class="flex flex-col gap-3">
            <div
                class="animate-pulse-slow relative overflow-hidden rounded-2xl border border-rose-200 bg-rose-50/70 p-4 shadow-sm dark:border-rose-900/40 dark:bg-rose-950/20"
            >
                <div class="absolute top-4 right-4 opacity-10">
                    <Flame class="size-20 text-rose-500" />
                </div>

                <div
                    class="mb-3 flex items-center gap-2 text-sm font-extrabold tracking-wider text-rose-700 uppercase dark:text-rose-400"
                >
                    <ShieldAlert class="size-4 shrink-0 animate-bounce" />
                    <span>Cảnh Báo Khủng Hoảng Khẩn Cấp (AI Crisis Alert)</span>
                </div>

                <div class="flex max-h-48 flex-col gap-2.5 overflow-y-auto">
                    <div
                        v-for="alert in crisisFeedbacks"
                        :key="alert.id"
                        class="flex items-start justify-between gap-4 rounded-xl border border-rose-100 bg-white p-3 shadow-sm dark:border-rose-950 dark:bg-slate-900/80"
                    >
                        <div class="flex-1">
                            <p
                                class="text-xs font-bold text-slate-800 dark:text-slate-200"
                            >
                                Phát hiện đánh giá cực tệ
                                <span class="font-black text-rose-500"
                                    >({{ alert.rating }} Sao)</span
                                >
                                tại
                                <span
                                    class="font-black text-violet-600 dark:text-violet-400"
                                    >{{ alert.table_name }}</span
                                >
                                (Hóa đơn: {{ alert.order_number ?? 'N/A' }})
                            </p>
                            <p
                                class="mt-1 text-[11px] leading-relaxed text-slate-500 italic dark:text-slate-400"
                            >
                                "{{
                                    alert.content ??
                                    'Khách không để lại ý kiến đóng góp'
                                }}"
                            </p>
                            <!-- Context breakdown -->
                            <div
                                class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[10px] font-bold text-slate-400"
                            >
                                <span
                                    >Ca chịu trách nhiệm:
                                    {{ alert.responsible_shift }}</span
                                >
                                <span>•</span>
                                <span
                                    >Các món trong đơn:
                                    {{ alert.items.join(', ') || 'N/A' }}</span
                                >
                            </div>

                            <!-- Bad items or bad staff highlights -->
                            <div
                                v-if="
                                    (alert.items_rating &&
                                        alert.items_rating.some(
                                            (i) => i.rating <= 2,
                                        )) ||
                                    (alert.staff_rating &&
                                        alert.staff_rating.some(
                                            (s) => s.rating <= 2,
                                        ))
                                "
                                class="mt-2 flex flex-col gap-1 rounded-lg border border-rose-100/40 bg-rose-50/50 p-2 text-[10px] dark:border-rose-900/40 dark:bg-rose-950/40"
                            >
                                <div
                                    v-if="
                                        alert.items_rating &&
                                        alert.items_rating.some(
                                            (i) => i.rating <= 2,
                                        )
                                    "
                                    class="flex flex-wrap items-center gap-1.5"
                                >
                                    <span
                                        class="font-extrabold text-rose-600 dark:text-rose-400"
                                        >Lỗi món ăn:</span
                                    >
                                    <span
                                        v-for="ir in alert.items_rating.filter(
                                            (i) => i.rating <= 2,
                                        )"
                                        :key="ir.product_id"
                                        class="dark:text-rose-350 rounded bg-rose-100 px-1.5 py-0.5 text-rose-700 dark:bg-rose-950"
                                    >
                                        {{ ir.name }} ({{ ir.rating }}★)
                                        <span
                                            v-if="ir.comment"
                                            class="font-normal opacity-80"
                                        >
                                            - {{ ir.comment }}</span
                                        >
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        alert.staff_rating &&
                                        alert.staff_rating.some(
                                            (s) => s.rating <= 2,
                                        )
                                    "
                                    class="mt-1 flex flex-wrap items-center gap-1.5"
                                >
                                    <span
                                        class="font-extrabold text-rose-600 dark:text-rose-400"
                                        >Lỗi phục vụ:</span
                                    >
                                    <span
                                        v-for="sr in alert.staff_rating.filter(
                                            (s) => s.rating <= 2,
                                        )"
                                        :key="sr.employee_id"
                                        class="dark:text-rose-350 rounded bg-rose-100 px-1.5 py-0.5 text-rose-700 dark:bg-rose-950"
                                    >
                                        {{ sr.name }} ({{ sr.rating }}★)
                                        <span
                                            v-if="sr.comment"
                                            class="font-normal opacity-80"
                                        >
                                            - {{ sr.comment }}</span
                                        >
                                    </span>
                                </div>
                            </div>
                        </div>
                        <Button
                            size="sm"
                            variant="destructive"
                            @click="openResolveModal(alert)"
                            class="flex h-8 shrink-0 items-center gap-1.5 rounded-lg border-0 bg-gradient-to-r from-rose-600 to-orange-600 text-[10px] font-extrabold shadow-md"
                        >
                            <Flame class="size-3.5" />
                            Xử lý & Đền bù ngay
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- STATS / KPIS DASHBOARD -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <!-- Score Card -->
            <Card class="rounded-2xl border-slate-200 dark:border-slate-800">
                <CardHeader class="p-4 pb-2">
                    <CardTitle
                        class="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                    >
                        Điểm Đánh Giá Trung Bình
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex items-center justify-between p-4 pt-0">
                    <div>
                        <div class="flex items-baseline gap-1">
                            <span
                                class="text-4xl font-extrabold text-slate-800 dark:text-slate-100"
                                >{{ stats.average }}</span
                            >
                            <span class="text-sm font-bold text-slate-400"
                                >/ 5.0</span
                            >
                        </div>
                        <div class="mt-1 flex items-center gap-0.5 select-none">
                            <Star
                                v-for="s in 5"
                                :key="s"
                                class="size-4"
                                :class="[
                                    s <= Math.round(stats.average)
                                        ? 'fill-amber-400 text-amber-400'
                                        : 'text-slate-200 dark:text-slate-800',
                                ]"
                            />
                        </div>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 text-amber-500 dark:bg-amber-950/40"
                    >
                        <Award class="size-6" />
                    </div>
                </CardContent>
            </Card>

            <!-- Total Feedbacks -->
            <Card class="rounded-2xl border-slate-200 dark:border-slate-800">
                <CardHeader class="p-4 pb-2">
                    <CardTitle
                        class="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                    >
                        Tổng Số Phản Hồi
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex items-center justify-between p-4 pt-0">
                    <div>
                        <div
                            class="text-4xl font-extrabold text-slate-800 dark:text-slate-100"
                        >
                            {{ stats.total }}
                        </div>
                        <p
                            class="mt-1.5 flex items-center gap-1 text-[10px] font-bold text-slate-400"
                        >
                            <span
                                class="h-2 w-2 animate-ping rounded-full bg-violet-500"
                            ></span>
                            <span
                                >{{ stats.new }} phản hồi mới chưa xem xét</span
                            >
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-50 text-violet-500 dark:bg-violet-950/40"
                    >
                        <MessageSquare class="size-6" />
                    </div>
                </CardContent>
            </Card>

            <!-- Distribution chart -->
            <Card class="rounded-2xl border-slate-200 dark:border-slate-800">
                <CardHeader class="p-4 pb-2">
                    <CardTitle
                        class="text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                    >
                        Phân Phối Điểm Số (Số lượng)
                    </CardTitle>
                </CardHeader>
                <CardContent
                    class="flex flex-col justify-center gap-1.5 p-4 pt-0"
                >
                    <div
                        v-for="s in [5, 4, 3, 2, 1]"
                        :key="s"
                        class="flex items-center gap-2 text-[10px] font-bold text-slate-600 dark:text-slate-400"
                    >
                        <span class="w-3 text-right">{{ s }}★</span>
                        <!-- Bar -->
                        <div
                            class="dark:bg-slate-850 h-2 flex-1 overflow-hidden rounded-full bg-slate-100"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-300"
                                :class="[
                                    s >= 4
                                        ? 'bg-emerald-500'
                                        : s === 3
                                          ? 'bg-amber-400'
                                          : 'bg-rose-500',
                                ]"
                                :style="{
                                    width: `${stats.total > 0 ? (stats.distribution[s] / stats.total) * 100 : 0}%`,
                                }"
                            ></div>
                        </div>
                        <span class="w-6 text-right font-mono">{{
                            stats.distribution[s]
                        }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ALERT KPIS FOR LOW-RATED PRODUCTS & STAFF -->
        <div
            v-if="lowRatedItems.length > 0 || lowRatedStaff.length > 0"
            class="grid grid-cols-1 gap-4 md:grid-cols-2"
        >
            <!-- Low Rated Dishes Card -->
            <Card
                v-if="lowRatedItems.length > 0"
                class="rounded-2xl border-slate-200 dark:border-slate-800"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between p-4 pb-2"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-rose-500 uppercase"
                        >
                            <Utensils class="size-4" /> Món Ăn Bị Phàn Nàn (Đánh
                            giá thấp)
                        </CardTitle>
                        <CardDescription class="mt-0.5 text-[10px]"
                            >Điểm trung bình của món từ 3.5★ trở
                            xuống</CardDescription
                        >
                    </div>
                </CardHeader>
                <CardContent class="p-4 pt-2">
                    <div class="max-h-40 space-y-2 overflow-y-auto">
                        <div
                            v-for="item in lowRatedItems"
                            :key="item.name"
                            class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-2.5 dark:border-slate-800/60 dark:bg-slate-900"
                        >
                            <span
                                class="dark:text-slate-350 text-xs font-bold text-slate-700"
                                >{{ item.name }}</span
                            >
                            <div class="flex items-center gap-2">
                                <span
                                    class="font-mono text-[10px] text-slate-400"
                                    >Đánh giá: {{ item.count }} lần</span
                                >
                                <span
                                    class="flex items-center gap-0.5 rounded border border-rose-100/30 bg-rose-50 px-2 py-0.5 text-xs font-extrabold text-rose-500 dark:bg-rose-950/40"
                                >
                                    {{ item.avgRating }}
                                    <Star class="size-3 fill-current" />
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Low Rated Staff Card -->
            <Card
                v-if="lowRatedStaff.length > 0"
                class="rounded-2xl border-slate-200 dark:border-slate-800"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between p-4 pb-2"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-rose-500 uppercase"
                        >
                            <Coffee class="size-4" /> Nhân Sự Cần Chỉnh Đốn Phục
                            Vụ
                        </CardTitle>
                        <CardDescription class="mt-0.5 text-[10px]"
                            >Điểm phục vụ trung bình từ 3.5★ trở
                            xuống</CardDescription
                        >
                    </div>
                </CardHeader>
                <CardContent class="p-4 pt-2">
                    <div class="max-h-40 space-y-2 overflow-y-auto">
                        <div
                            v-for="staff in lowRatedStaff"
                            :key="staff.name"
                            class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-2.5 dark:border-slate-800/60 dark:bg-slate-900"
                        >
                            <span
                                class="dark:text-slate-350 text-xs font-bold text-slate-700"
                                >{{ staff.name }}</span
                            >
                            <div class="flex items-center gap-2">
                                <span
                                    class="font-mono text-[10px] text-slate-400"
                                    >Đánh giá: {{ staff.count }} lần</span
                                >
                                <span
                                    class="flex items-center gap-0.5 rounded border border-rose-100/30 bg-rose-50 px-2 py-0.5 text-xs font-extrabold text-rose-500 dark:bg-rose-950/40"
                                >
                                    {{ staff.avgRating }}
                                    <Star class="size-3 fill-current" />
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- MAIN TABLE & LIST WORKSPACE -->
        <Card
            class="overflow-hidden rounded-2xl border-slate-200 dark:border-slate-800"
        >
            <!-- Filter Bar -->
            <div
                class="flex flex-col gap-3 border-b bg-slate-50/50 p-4 dark:bg-slate-900/20"
            >
                <div
                    class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center"
                >
                    <!-- Status filter tabs -->
                    <div
                        class="flex items-center gap-1.5 rounded-xl border border-slate-200/50 bg-slate-100 p-0.5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <button
                            v-for="filter in [
                                { key: 'all', label: 'Tất cả' },
                                { key: 'new', label: 'Mới gửi' },
                                { key: 'resolved', label: 'Đã giải quyết' },
                                { key: 'critical', label: '🔥 Khủng hoảng' },
                            ]"
                            :key="filter.key"
                            type="button"
                            @click="activeFilter = filter.key as any"
                            :class="[
                                'rounded-lg px-3.5 py-1.5 text-[11px] font-bold whitespace-nowrap transition-colors',
                                activeFilter === filter.key
                                    ? 'border border-slate-200/20 bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
                                    : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300',
                            ]"
                        >
                            {{ filter.label }}
                        </button>
                    </div>

                    <!-- Date filter -->
                    <div
                        class="flex items-center gap-1.5 rounded-xl border border-slate-200/50 bg-slate-100 p-0.5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <button
                            v-for="df in [
                                { key: 'all', label: 'Mọi ngày' },
                                { key: 'today', label: 'Hôm nay' },
                                { key: 'week', label: '7 ngày' },
                                { key: 'month', label: 'Tháng này' },
                            ]"
                            :key="df.key"
                            type="button"
                            @click="dateFilter = df.key as any"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-[11px] font-bold whitespace-nowrap transition-colors',
                                dateFilter === df.key
                                    ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
                                    : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300',
                            ]"
                        >
                            {{ df.label }}
                        </button>
                    </div>
                </div>

                <!-- Search + count -->
                <div class="flex items-center gap-2">
                    <div class="relative max-w-sm flex-1">
                        <Search
                            class="absolute top-2 left-2.5 size-3.5 text-slate-400"
                        />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Tìm theo tên khách, nội dung, bàn, mã đơn..."
                            class="w-full rounded-lg border border-slate-200 bg-white py-1.5 pr-3 pl-7 text-xs focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/40 focus:outline-none dark:border-slate-700 dark:bg-slate-900"
                        />
                    </div>
                    <span class="shrink-0 text-[10px] font-bold text-slate-400">
                        {{ filteredFeedbacks.length }} / {{ stats.total }} phản
                        hồi
                    </span>
                </div>
            </div>

            <!-- Feedbacks Listing Workspace -->
            <div class="dark:divide-slate-850 divide-y">
                <div
                    v-for="fb in filteredFeedbacks"
                    :key="fb.id"
                    class="flex flex-col justify-between gap-5 p-5 transition-all duration-300 hover:bg-slate-50/30 md:flex-row dark:hover:bg-slate-900/10"
                    :class="[
                        fb.status === 'new' && fb.rating <= 2
                            ? 'border-l-4 border-rose-500 bg-rose-50/10 dark:bg-rose-950/5'
                            : '',
                    ]"
                >
                    <!-- Left: Star, Content and Client contact info -->
                    <div class="flex flex-1 items-start gap-4">
                        <!-- Rating Circle badge -->
                        <div
                            class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-2xl shadow-inner select-none"
                            :class="ratingStarsConfig[fb.rating]?.bg"
                        >
                            <span
                                class="text-base font-extrabold"
                                :class="
                                    ratingStarsConfig[fb.rating]?.text_color
                                "
                            >
                                {{ fb.rating }}
                            </span>
                            <span
                                class="-mt-1 text-[7px] font-extrabold tracking-wider uppercase"
                                :class="
                                    ratingStarsConfig[fb.rating]?.text_color
                                "
                            >
                                Sao
                            </span>
                        </div>

                        <div class="flex flex-grow flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ fb.submitted_by_name }}
                                </span>
                                <span
                                    v-if="fb.submitted_by_phone"
                                    class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-400 dark:bg-slate-800"
                                >
                                    SĐT: {{ fb.submitted_by_phone }}
                                </span>
                                <span
                                    class="text-[9px] font-medium text-slate-400"
                                >
                                    {{ fb.created_at }}
                                </span>
                            </div>
                            <p
                                class="dark:text-slate-350 mt-1 text-xs leading-relaxed text-slate-600"
                            >
                                "{{
                                    fb.content ??
                                    'Khách không để lại ý kiến đóng góp chi tiết.'
                                }}"
                                <span
                                    v-if="fb.sentiment"
                                    class="ml-1.5 inline-flex items-center rounded-full px-1.5 py-0.5 align-middle text-[9px] font-bold"
                                    :class="
                                        sentimentConfig[fb.sentiment]?.class
                                    "
                                >
                                    {{ sentimentConfig[fb.sentiment]?.text }}
                                </span>
                            </p>

                            <!-- Detailed Ratings (Dish & Staff) -->
                            <div
                                v-if="
                                    (fb.items_rating &&
                                        fb.items_rating.length > 0) ||
                                    (fb.staff_rating &&
                                        fb.staff_rating.length > 0)
                                "
                                class="mt-2.5 flex flex-col gap-2 rounded-xl border border-slate-100/60 bg-slate-50/60 p-3 dark:border-slate-800/60 dark:bg-slate-900/40"
                            >
                                <!-- Dish ratings -->
                                <div
                                    v-if="
                                        fb.items_rating &&
                                        fb.items_rating.length > 0
                                    "
                                    class="space-y-1.5"
                                >
                                    <span
                                        class="flex items-center gap-1 text-[9px] font-extrabold tracking-wider text-slate-400 uppercase"
                                    >
                                        <Utensils class="size-3" /> Đánh giá món
                                        ăn
                                    </span>
                                    <div class="grid gap-1.5 sm:grid-cols-2">
                                        <div
                                            v-for="ir in fb.items_rating"
                                            :key="ir.product_id"
                                            class="border-slate-150/40 flex flex-col gap-0.5 rounded-lg border bg-white p-2 dark:border-slate-800 dark:bg-slate-900/60"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-2"
                                            >
                                                <span
                                                    class="line-clamp-1 text-[11px] font-bold text-slate-700 dark:text-slate-300"
                                                    >{{ ir.name }}</span
                                                >
                                                <span
                                                    class="flex shrink-0 items-center gap-0.5 text-[10px] font-bold"
                                                    :class="
                                                        ir.rating <= 2
                                                            ? 'text-rose-500'
                                                            : ir.rating === 3
                                                              ? 'text-amber-500'
                                                              : 'text-emerald-500'
                                                    "
                                                >
                                                    {{ ir.rating
                                                    }}<Star
                                                        class="size-3 fill-current"
                                                    />
                                                </span>
                                            </div>
                                            <p
                                                v-if="ir.comment"
                                                class="text-[10px] text-slate-500 italic dark:text-slate-400"
                                            >
                                                "{{ ir.comment }}"
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Staff ratings -->
                                <div
                                    v-if="
                                        fb.staff_rating &&
                                        fb.staff_rating.length > 0
                                    "
                                    class="mt-1 space-y-1.5 border-t border-slate-200/50 pt-2.5 dark:border-slate-800/50"
                                >
                                    <span
                                        class="flex items-center gap-1 text-[9px] font-extrabold tracking-wider text-slate-400 uppercase"
                                    >
                                        <Coffee class="size-3" /> Đánh giá phục
                                        vụ
                                    </span>
                                    <div class="grid gap-1.5 sm:grid-cols-2">
                                        <div
                                            v-for="sr in fb.staff_rating"
                                            :key="sr.employee_id"
                                            class="border-slate-150/40 flex flex-col gap-0.5 rounded-lg border bg-white p-2 dark:border-slate-800 dark:bg-slate-900/60"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-2"
                                            >
                                                <span
                                                    class="line-clamp-1 text-[11px] font-bold text-slate-700 dark:text-slate-300"
                                                    >{{ sr.name }}</span
                                                >
                                                <span
                                                    class="flex shrink-0 items-center gap-0.5 text-[10px] font-bold"
                                                    :class="
                                                        sr.rating <= 2
                                                            ? 'text-rose-500'
                                                            : sr.rating === 3
                                                              ? 'text-amber-500'
                                                              : 'text-emerald-500'
                                                    "
                                                >
                                                    {{ sr.rating
                                                    }}<Star
                                                        class="size-3 fill-current"
                                                    />
                                                </span>
                                            </div>
                                            <p
                                                v-if="sr.comment"
                                                class="text-[10px] text-slate-500 italic dark:text-slate-400"
                                            >
                                                "{{ sr.comment }}"
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Resolution Context if Resolved -->
                            <div
                                v-if="
                                    fb.status === 'resolved' ||
                                    fb.status === 'reviewed'
                                "
                                class="animate-fadeIn mt-3 flex flex-col gap-1.5 rounded-xl border border-emerald-100/60 bg-emerald-50/50 p-3 dark:border-emerald-900/20 dark:bg-emerald-950/10"
                            >
                                <div
                                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-emerald-700 uppercase dark:text-emerald-400"
                                >
                                    <CheckCircle2 class="size-3.5 shrink-0" />
                                    <span>Đã giải quyết đền bù thành công</span>
                                </div>
                                <p
                                    class="text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Phương án:
                                    {{
                                        fb.resolution_notes ??
                                        'Nhân viên đã trực tiếp gặp xin lỗi khách'
                                    }}
                                </p>
                                <div
                                    v-if="fb.compensation_voucher"
                                    class="mt-1 flex items-center gap-1 text-[10px] font-bold text-violet-600 select-none dark:text-violet-400"
                                >
                                    <Percent class="size-3" />
                                    <span
                                        >Mã đền bù:
                                        {{ fb.compensation_voucher }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Incident Context and Action Resolver -->
                    <div
                        class="dark:border-slate-850 flex w-full shrink-0 flex-col items-start justify-between gap-3 border-t border-slate-100 pt-4 md:w-80 md:items-end md:border-t-0 md:pt-0"
                    >
                        <!-- Attributed Crisis context info -->
                        <div
                            class="border-slate-150/40 flex w-full flex-col gap-1.5 rounded-2xl border bg-slate-50 p-3 text-[10px] font-bold text-slate-500 select-none md:text-right dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-400"
                        >
                            <div>
                                <span
                                    class="mb-0.5 block text-[9px] tracking-wider text-slate-400 uppercase"
                                    >Vị trí & Đơn hàng</span
                                >
                                <span
                                    class="text-xs text-slate-800 dark:text-slate-200"
                                    >{{ fb.table_name }}</span
                                >
                                <span
                                    v-if="fb.order_number"
                                    class="mt-0.5 block font-mono text-[10px] font-medium text-slate-400"
                                    >Hóa đơn: {{ fb.order_number }}</span
                                >
                            </div>
                            <div
                                class="mt-1.5 border-t border-slate-200/50 pt-1.5 dark:border-slate-800"
                            >
                                <span
                                    class="mb-0.5 block text-[9px] tracking-wider text-slate-400 uppercase"
                                    >Ca Trực Chịu Trách Nhiệm</span
                                >
                                <span
                                    class="block text-[11px] text-violet-600 dark:text-violet-400"
                                    >{{ fb.responsible_shift }}</span
                                >
                                <span
                                    v-if="fb.responsible_staff.length > 0"
                                    class="mt-0.5 block text-[10px] font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Nhân sự trực:
                                    {{ fb.responsible_staff.join(', ') }}
                                </span>
                            </div>
                            <div
                                v-if="fb.items.length > 0"
                                class="mt-1.5 border-t border-slate-200/50 pt-1.5 dark:border-slate-800"
                            >
                                <span
                                    class="mb-0.5 block text-[9px] tracking-wider text-slate-400 uppercase"
                                    >Món ăn trong đơn</span
                                >
                                <span
                                    class="block font-mono leading-relaxed font-medium text-slate-700 dark:text-slate-300"
                                    >{{ fb.items.join(', ') }}</span
                                >
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div
                            v-if="fb.status === 'new'"
                            class="flex w-full justify-end"
                        >
                            <Button
                                size="sm"
                                @click="openResolveModal(fb)"
                                class="flex h-9 w-full items-center justify-center gap-1.5 rounded-xl border-0 bg-gradient-to-r from-violet-600 to-indigo-600 text-xs font-bold text-white shadow-md select-none hover:from-violet-700 hover:to-indigo-700 md:w-auto dark:from-violet-500 dark:to-indigo-500"
                            >
                                <Award class="size-4" />
                                Giải quyết đền bù
                            </Button>
                        </div>
                        <div v-else class="flex w-full justify-end select-none">
                            <span
                                class="rounded-full border border-emerald-200/30 bg-emerald-100/50 px-3 py-1 text-[10px] font-bold text-emerald-600 dark:bg-emerald-950/30 dark:text-emerald-400"
                            >
                                Đã xử lý khủng hoảng
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    v-if="filteredFeedbacks.length === 0"
                    class="flex flex-col items-center justify-center gap-2 py-20 text-center select-none"
                >
                    <CheckCircle2
                        class="text-slate-350 size-10 dark:text-slate-700"
                    />
                    <h3
                        class="text-sm font-semibold text-slate-700 dark:text-slate-300"
                    >
                        Không có phản hồi nào
                    </h3>
                    <p
                        class="max-w-[250px] text-xs text-slate-400 dark:text-slate-500"
                    >
                        Toàn bộ danh sách sạch sẽ, chất lượng dịch vụ hiện tại
                        rất tuyệt vời!
                    </p>
                </div>
            </div>
        </Card>
    </div>

    <!-- CRISIS RESOLUTION MODAL -->
    <Teleport to="body">
    <div
        v-if="showResolveModal && selectedFeedback"
        class="animate-fadeIn fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm dark:bg-slate-950/80"
    >
        <div
            class="relative w-full max-w-lg overflow-hidden rounded-3xl border border-slate-200/60 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="mb-4 flex items-center gap-2 border-b pb-3 text-sm font-extrabold tracking-wider text-violet-700 uppercase select-none dark:text-violet-400"
            >
                <Award class="size-4.5" />
                <span>Phương án Đền bù Khủng hoảng (Crisis Resolution)</span>
            </div>

            <!-- Empathy message about feedback context -->
            <div
                class="mb-4 rounded-2xl border border-indigo-100/50 bg-indigo-50/50 p-3.5 text-xs select-none dark:bg-indigo-950/20"
            >
                <div class="font-bold text-slate-800 dark:text-slate-200">
                    Khách hàng: {{ selectedFeedback.submitted_by_name }} ({{
                        selectedFeedback.table_name
                    }})
                </div>
                <div class="mt-1 text-slate-500 italic dark:text-slate-400">
                    "{{
                        selectedFeedback.content ??
                        'Khách không để lại ý kiến đóng góp chi tiết.'
                    }}"
                </div>

                <!-- Detailed ratings context inside modal -->
                <div
                    v-if="
                        (selectedFeedback.items_rating &&
                            selectedFeedback.items_rating.length > 0) ||
                        (selectedFeedback.staff_rating &&
                            selectedFeedback.staff_rating.length > 0)
                    "
                    class="mt-2.5 flex flex-col gap-1.5 border-t border-indigo-100/30 pt-2.5 text-[10px] text-slate-600 dark:border-slate-800/80 dark:text-slate-400"
                >
                    <div
                        v-if="
                            selectedFeedback.items_rating &&
                            selectedFeedback.items_rating.length > 0
                        "
                        class="flex flex-wrap items-center gap-1.5"
                    >
                        <span class="font-bold">Món ăn: </span>
                        <span
                            v-for="ir in selectedFeedback.items_rating"
                            :key="ir.product_id"
                            class="rounded bg-indigo-100/20 px-1.5 py-0.5 text-indigo-700 dark:bg-slate-900 dark:text-indigo-300"
                        >
                            {{ ir.name }} ({{ ir.rating }}★)<span
                                v-if="ir.comment"
                                class="font-normal opacity-80"
                            >
                                - {{ ir.comment }}</span
                            >
                        </span>
                    </div>
                    <div
                        v-if="
                            selectedFeedback.staff_rating &&
                            selectedFeedback.staff_rating.length > 0
                        "
                        class="mt-1 flex flex-wrap items-center gap-1.5"
                    >
                        <span class="font-bold">Nhân sự: </span>
                        <span
                            v-for="sr in selectedFeedback.staff_rating"
                            :key="sr.employee_id"
                            class="rounded bg-indigo-100/20 px-1.5 py-0.5 text-indigo-700 dark:bg-slate-900 dark:text-indigo-300"
                        >
                            {{ sr.name }} ({{ sr.rating }}★)<span
                                v-if="sr.comment"
                                class="font-normal opacity-80"
                            >
                                - {{ sr.comment }}</span
                            >
                        </span>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submitResolve" class="flex flex-col gap-4">
                <!-- Voucher selection -->
                <div class="flex flex-col gap-1.5">
                    <Label
                        for="voucher"
                        class="text-xs font-bold text-slate-600 dark:text-slate-400"
                    >
                        Chọn Voucher Đền Bù giảm giá cho khách:
                    </Label>
                    <select
                        id="voucher"
                        v-model="resolveForm.compensation_voucher"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                    >
                        <option value="">
                            -- Không tặng Voucher (Gặp xin lỗi trực tiếp) --
                        </option>
                        <option
                            v-for="v in vouchers"
                            :key="v.id"
                            :value="v.code"
                        >
                            {{ v.name }} [Mã: {{ v.code }} - Giảm:
                            {{
                                v.type === 'percent'
                                    ? v.value + '%'
                                    : formatCurrency(v.value)
                            }}]
                        </option>
                    </select>
                </div>

                <!-- Notes for Resolution action -->
                <div class="flex flex-col gap-1.5">
                    <Label
                        for="notes"
                        class="text-xs font-bold text-slate-600 dark:text-slate-400"
                    >
                        Nội dung phương án đền bù / giải quyết sự cố:
                        <span class="font-bold text-rose-500">*</span>
                    </Label>
                    <textarea
                        id="notes"
                        v-model="resolveForm.resolution_notes"
                        placeholder="Ví dụ: Đã ra bàn xin lỗi khách vì súp gà bị nguội, trực tiếp đổi tô súp nóng mới và tặng voucher giảm giá 20% cho lần ăn tiếp theo..."
                        rows="3"
                        required
                        class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs transition-all focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 focus:outline-none dark:border-slate-800 dark:bg-slate-900"
                    ></textarea>
                </div>

                <!-- Action buttons -->
                <div class="mt-2 flex justify-end gap-2.5 border-t pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="showResolveModal = false"
                        class="h-9 rounded-xl text-xs"
                    >
                        Hủy
                    </Button>
                    <Button
                        type="submit"
                        :disabled="resolveForm.processing"
                        class="h-9 rounded-xl border-0 bg-gradient-to-r from-violet-600 to-indigo-600 text-xs font-bold text-white shadow-md hover:from-violet-700 hover:to-indigo-700"
                    >
                        Xác nhận Giải quyết
                    </Button>
                </div>
            </form>
        </div>
    </div>
    </Teleport>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.98);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
.animate-fadeIn {
    animation: fadeIn 0.2s ease-out forwards;
}

@keyframes pulseSlow {
    0%,
    100% {
        border-color: rgb(254 205 211);
    }
    50% {
        border-color: rgb(244 63 94);
    }
}
.animate-pulse-slow {
    animation: pulseSlow 2s infinite ease-in-out;
}
@keyframes pulseDark {
    0%,
    100% {
        border-color: rgba(225, 29, 72, 0.2);
    }
    50% {
        border-color: rgba(225, 29, 72, 0.7);
    }
}
.dark .animate-pulse-slow {
    animation: pulseDark 2s infinite ease-in-out;
}
</style>
