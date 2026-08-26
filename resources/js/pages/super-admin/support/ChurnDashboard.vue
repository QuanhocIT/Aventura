<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ShieldAlert,
    AlertTriangle,
    CheckCircle2,
    Mail,
    Search,
    RefreshCw,
    SlidersHorizontal,
    Phone,
    User,
    TrendingDown,
    HelpCircle,
    Sparkles,
    Tag,
    Plus,
    Trash2,
    Clock,
    Check,
    Percent,
    AlertOctagon,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import {
    PageHeader,
    Pagination,
} from '@/components/super-admin';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface RestaurantBreakdown {
    days_since_login: number;
    current_week_orders: number;
    prev_weekly_avg: number;
    unresolved_tickets: number;
    drop_percentage: number;
}

interface CrmTag {
    id: number;
    name: string;
    color: string;
}

interface CrmNote {
    id: number;
    note: string;
    created_at: string;
    user_name: string;
}

interface CrmFollowup {
    id: number;
    note: string;
    remind_at: string;
    status: string;
    assigned_user_name: string;
}

interface RestaurantData {
    id: number;
    name: string;
    code: string;
    status: string;
    health_score: number;
    churn_risk_level: 'high' | 'medium' | 'low';
    churn_risk_reason: string;
    churn_risk_flagged_at: string | null;
    last_health_checked_at: string | null;
    plan_name: string;
    owner_name: string;
    owner_email: string;
    owner_phone: string;
    tags: CrmTag[];
    notes: CrmNote[];
    followups: CrmFollowup[];
    breakdown: RestaurantBreakdown;
}

const props = defineProps<{
    stats: {
        total_checked: number;
        avg_health_score: number;
        high_risk: number;
        medium_risk: number;
        low_risk: number;
        emails_sent: number;
        system_risk_ratio: number;
        urgent_action_required: number;
    };
    restaurants: {
        data: RestaurantData[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    plans: Array<{ id: number; name: string }>;
    admins: Array<{ id: number; name: string; email: string }>;
    filters: {
        risk_level?: string;
        plan_id?: string;
        search?: string;
    };
}>();

// Filter states
const search = ref(props.filters.search ?? '');
const riskLevel = ref(props.filters.risk_level ?? 'all');
const planId = ref(props.filters.plan_id ?? 'all');

const isRecalculating = ref(false);
const triggeringEmailId = ref<number | null>(null);

// Apply filters
const applyFilters = () => {
    router.get(
        '/super-admin/churn-prediction',
        {
            search: search.value || undefined,
            risk_level: riskLevel.value !== 'all' ? riskLevel.value : undefined,
            plan_id: planId.value !== 'all' ? planId.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

// Clear filters
const resetFilters = () => {
    search.value = '';
    riskLevel.value = 'all';
    planId.value = 'all';
    applyFilters();
};

// Recalculate health metrics
const recalculateHealth = () => {
    isRecalculating.value = true;
    router.post(
        '/super-admin/churn-prediction/recalculate',
        {},
        {
            onSuccess: (page: any) => {
                toast.success(
                    page.props.flash?.success ??
                        'Đã cập nhật chỉ số sức khỏe hệ thống thành công!',
                );
            },
            onError: () => {
                toast.error('Lỗi khi chạy quét sức khỏe doanh nghiệp.');
            },
            onFinish: () => {
                isRecalculating.value = false;
            },
        },
    );
};

// Send manual care email
const sendOutreachEmail = (restaurantId: number) => {
    triggeringEmailId.value = restaurantId;
    router.post(
        `/super-admin/churn-prediction/trigger-email/${restaurantId}`,
        {},
        {
            onSuccess: (page: any) => {
                toast.success(
                    page.props.flash?.success ??
                        'Đã gửi thư điện tử thành công!',
                );
            },
            onError: () => {
                toast.error(
                    'Không thể gửi thư điện tử. Vui lòng kiểm tra cấu hình SMTP.',
                );
            },
            onFinish: () => {
                triggeringEmailId.value = null;
            },
        },
    );
};

// Styling helper for Health Score Badge
const getScoreBadgeClass = (score: number) => {
    if (score >= 80) {
        return 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20';
    }

    if (score >= 50) {
        return 'bg-amber-500/10 text-amber-600 border border-amber-500/20';
    }

    return 'bg-rose-500/10 text-rose-600 border border-rose-500/20 animate-pulse';
};

// Styling helper for Risk Level Badge
const getRiskBadgeClass = (level: 'high' | 'medium' | 'low') => {
    if (level === 'low') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300';
    }

    if (level === 'medium') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300';
    }

    return 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300';
};

const getRiskLabel = (level: 'high' | 'medium' | 'low') => {
    if (level === 'low') {
        return 'Nguy cơ thấp';
    }

    if (level === 'medium') {
        return 'Nguy cơ TB';
    }

    return 'Nguy cơ cao (At-risk)';
};

// CRM Modal / details tab state
const selectedDetails = ref<RestaurantData | null>(null);
const activeTab = ref<'health' | 'crm' | 'followup'>('health');

// CRM Fields states
const newNote = ref('');
const isSavingNote = ref(false);

const newTagName = ref('');
const newTagColor = ref(
    'bg-blue-500/10 text-blue-600 border border-blue-500/20',
);
const isSavingTag = ref(false);

const tagPresets = [
    {
        label: 'Đỏ',
        value: 'bg-rose-500/10 text-rose-600 border border-rose-500/20',
    },
    {
        label: 'Cam',
        value: 'bg-orange-500/10 text-orange-600 border border-orange-500/20',
    },
    {
        label: 'Vàng',
        value: 'bg-amber-500/10 text-amber-600 border border-amber-500/20',
    },
    {
        label: 'Xanh dương',
        value: 'bg-blue-500/10 text-blue-600 border border-blue-500/20',
    },
    {
        label: 'Xanh lá',
        value: 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20',
    },
    {
        label: 'Tím',
        value: 'bg-purple-500/10 text-purple-600 border border-purple-500/20',
    },
];

const followupNote = ref('');
const remindAt = ref('');
const assignedTo = ref('');
const isSavingFollowup = ref(false);

const openDetails = (restaurant: RestaurantData) => {
    selectedDetails.value = restaurant;
    activeTab.value = 'health';
    // Clear states
    newNote.value = '';
    newTagName.value = '';
    followupNote.value = '';
    remindAt.value = '';
    assignedTo.value = '';
};

const closeDetails = () => {
    selectedDetails.value = null;
};

// CRM actions calling endpoints
const saveNote = (restaurantId: number) => {
    if (!newNote.value.trim()) {
        return;
    }

    isSavingNote.value = true;
    router.post(
        `/super-admin/restaurants/${restaurantId}/notes`,
        {
            note: newNote.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã lưu ghi chú nội bộ thành công!');
                newNote.value = '';
                // Refresh detailed view payload
                const updated = props.restaurants.data.find(
                    (r) => r.id === restaurantId,
                );

                if (updated) {
                    selectedDetails.value = updated;
                }
            },
            onFinish: () => {
                isSavingNote.value = false;
            },
        },
    );
};

const deleteNote = (restaurantId: number, noteId: number) => {
    router.delete(`/super-admin/restaurants/${restaurantId}/notes/${noteId}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã xóa ghi chú nội bộ.');
            const updated = props.restaurants.data.find(
                (r) => r.id === restaurantId,
            );

            if (updated) {
                selectedDetails.value = updated;
            }
        },
    });
};

const addTag = (restaurantId: number) => {
    if (!newTagName.value.trim()) {
        return;
    }

    isSavingTag.value = true;
    router.post(
        `/super-admin/restaurants/${restaurantId}/tags`,
        {
            name: newTagName.value,
            color: newTagColor.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã gắn nhãn thành công!');
                newTagName.value = '';
                const updated = props.restaurants.data.find(
                    (r) => r.id === restaurantId,
                );

                if (updated) {
                    selectedDetails.value = updated;
                }
            },
            onFinish: () => {
                isSavingTag.value = false;
            },
        },
    );
};

const deleteTag = (restaurantId: number, tagId: number) => {
    router.delete(`/super-admin/restaurants/${restaurantId}/tags/${tagId}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã gỡ nhãn.');
            const updated = props.restaurants.data.find(
                (r) => r.id === restaurantId,
            );

            if (updated) {
                selectedDetails.value = updated;
            }
        },
    });
};

const saveFollowup = (restaurantId: number) => {
    if (!followupNote.value.trim() || !remindAt.value || !assignedTo.value) {
        toast.error('Vui lòng nhập đầy đủ thông tin lịch hẹn.');

        return;
    }

    isSavingFollowup.value = true;
    router.post(
        `/super-admin/restaurants/${restaurantId}/followups`,
        {
            note: followupNote.value,
            remind_at: remindAt.value,
            assigned_to: assignedTo.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã đặt lịch hẹn chăm sóc thành công!');
                followupNote.value = '';
                remindAt.value = '';
                assignedTo.value = '';
                const updated = props.restaurants.data.find(
                    (r) => r.id === restaurantId,
                );

                if (updated) {
                    selectedDetails.value = updated;
                }
            },
            onFinish: () => {
                isSavingFollowup.value = false;
            },
        },
    );
};

const completeFollowup = (restaurantId: number, followupId: number) => {
    router.patch(
        `/super-admin/restaurants/${restaurantId}/followups/${followupId}/complete`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã đánh dấu hoàn thành cuộc hẹn!');
                const updated = props.restaurants.data.find(
                    (r) => r.id === restaurantId,
                );

                if (updated) {
                    selectedDetails.value = updated;
                }
            },
        },
    );
};

// Data-driven AI recommendations engine
const getRecommendations = (restaurant: RestaurantData) => {
    const recs = [];
    const b = restaurant.breakdown;

    if (restaurant.health_score < 50) {
        recs.push({
            type: 'danger',
            title: 'Hành động khẩn cấp',
            desc: 'Khách hàng đang ở mức rủi ro cao nhất. Khuyến nghị Admin liên hệ trực tiếp bằng điện thoại ngay hôm nay để lắng nghe khó khăn của họ.',
        });
    }

    if (b.days_since_login >= 7) {
        recs.push({
            type: 'warning',
            title: 'Kích hoạt đăng nhập',
            desc: `Đã ${b.days_since_login} ngày hệ thống không phát sinh lượt đăng nhập quản trị. Gửi tài liệu hướng dẫn nhanh các tính năng hoặc chủ động gọi điện tư vấn cài đặt.`,
        });
    }

    if (b.drop_percentage >= 30) {
        recs.push({
            type: 'warning',
            title: 'Sụt giảm kinh doanh',
            desc: `Số đơn hàng giảm mạnh ${b.drop_percentage}% so với trung bình các tuần trước. Khuyến nghị gửi mã giảm giá ưu đãi 30% gia hạn và kích cầu kinh doanh.`,
        });
    }

    if (b.unresolved_tickets > 0) {
        recs.push({
            type: 'info',
            title: 'Hỗ trợ kỹ thuật',
            desc: `Có ${b.unresolved_tickets} khiếu nại kỹ thuật chưa giải quyết xong. Ưu tiên nhân sự Dev/Hỗ trợ xử lý triệt để trong ngày hôm nay để nâng cao sự hài lòng.`,
        });
    }

    if (recs.length === 0) {
        recs.push({
            type: 'success',
            title: 'Vận hành tối ưu',
            desc: 'Các chỉ số sức khỏe của nhà hàng đang ở mức xuất sắc. Nên đề xuất nâng cấp gói dịch vụ hoặc gia hạn gói năm để tối ưu chi phí.',
        });
    }

    return recs;
};

// SVG-based donut charts computation
const donutSlices = computed(() => {
    const total =
        props.stats.high_risk + props.stats.medium_risk + props.stats.low_risk;

    if (total === 0) {
        return [];
    }

    const highPct = (props.stats.high_risk / total) * 100;
    const medPct = (props.stats.medium_risk / total) * 100;
    const lowPct = (props.stats.low_risk / total) * 100;

    let currentOffset = 0;
    const slices = [];

    if (highPct > 0) {
        slices.push({
            percentage: highPct,
            dashArray: `${highPct} ${100 - highPct}`,
            dashOffset: 100 - currentOffset,
            color: 'stroke-rose-500',
            label: 'Nguy cơ cao',
            value: props.stats.high_risk,
        });
        currentOffset += highPct;
    }

    if (medPct > 0) {
        slices.push({
            percentage: medPct,
            dashArray: `${medPct} ${100 - medPct}`,
            dashOffset: 100 - currentOffset,
            color: 'stroke-amber-500',
            label: 'Nguy cơ TB',
            value: props.stats.medium_risk,
        });
        currentOffset += medPct;
    }

    if (lowPct > 0) {
        slices.push({
            percentage: lowPct,
            dashArray: `${lowPct} ${100 - lowPct}`,
            dashOffset: 100 - currentOffset,
            color: 'stroke-emerald-500',
            label: 'Khỏe mạnh',
            value: props.stats.low_risk,
        });
    }

    return slices;
});

// SVG Bar Chart Distribution computation
const healthScoreDistribution = computed(() => {
    let bandA = 0; // 0 - 30
    let bandB = 0; // 31 - 50
    let bandC = 0; // 51 - 80
    let bandD = 0; // 81 - 100

    props.restaurants.data.forEach((r) => {
        if (r.health_score <= 30) {
            bandA++;
        } else if (r.health_score <= 50) {
            bandB++;
        } else if (r.health_score <= 80) {
            bandC++;
        } else {
            bandD++;
        }
    });

    const maxVal = Math.max(bandA, bandB, bandC, bandD, 1);

    return [
        {
            label: '0-30 Score',
            count: bandA,
            heightPct: (bandA / maxVal) * 85 + 10,
            color: 'bg-rose-500',
        },
        {
            label: '31-50 Score',
            count: bandB,
            heightPct: (bandB / maxVal) * 85 + 10,
            color: 'bg-orange-400',
        },
        {
            label: '51-80 Score',
            count: bandC,
            heightPct: (bandC / maxVal) * 85 + 10,
            color: 'bg-amber-400',
        },
        {
            label: '81-100 Score',
            count: bandD,
            heightPct: (bandD / maxVal) * 85 + 10,
            color: 'bg-emerald-500',
        },
    ];
});
</script>

<template>
    <Head title="Chăm sóc khách hàng & dự đoán rời bỏ" />

    <div class="dashboard-shell flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Dự Đoán Rời Bỏ & Chăm Sóc Khách Hàng"
            subtitle="Chủ động phát hiện nhà hàng sắp ngừng sử dụng qua điểm sức khỏe nhà hàng."
            :icon="ShieldAlert"
            back-href="/super-admin/dashboard"
        >
            <template #actions>
                <button
                    type="button"
                    @click="recalculateHealth"
                    :disabled="isRecalculating"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-bold text-primary-foreground shadow-md transition-all hover:bg-primary/90 disabled:opacity-50"
                >
                    <RefreshCw
                        :class="[
                            'size-3.5',
                            { 'animate-spin': isRecalculating },
                        ]"
                    />
                    Tính toán lại toàn bộ chỉ số
                </button>
            </template>
        </PageHeader>

        <!-- Overviews Metric Cards (Responsive Grid 7 Columns) -->
        <div class="dashboard-kpi-grid">
            <!-- Avg health score -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-2 p-4 text-center">
                    <p
                        class="text-[10px] font-bold tracking-wider text-teal-600 uppercase dark:text-teal-400"
                    >
                        Điểm sức khỏe TB
                    </p>
                    <div
                        class="font-mono text-2xl font-black text-teal-600 dark:text-teal-400"
                    >
                        {{ stats.avg_health_score }}%
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        Trung bình {{ stats.total_checked }} nhà hàng
                    </p>
                </CardContent>
            </Card>

            <!-- High Risk -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-2 p-4 text-center">
                    <p
                        class="text-[10px] font-bold tracking-wider text-rose-600 uppercase dark:text-rose-400"
                    >
                        Nguy cơ rời bỏ cao
                    </p>
                    <div
                        class="flex items-center justify-center gap-1 font-mono text-2xl font-black text-rose-600 dark:text-rose-400"
                    >
                        <AlertTriangle class="size-5 text-rose-500" />
                        {{ stats.high_risk }}
                    </div>
                    <p class="text-[9px] font-bold text-rose-600/80 uppercase">
                        Cần hỗ trợ gấp
                    </p>
                </CardContent>
            </Card>

            <!-- Med Risk -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-2 p-4 text-center">
                    <p
                        class="text-[10px] font-bold tracking-wider text-amber-600 uppercase dark:text-amber-400"
                    >
                        Nguy cơ trung bình
                    </p>
                    <div
                        class="font-mono text-2xl font-black text-amber-600 dark:text-amber-400"
                    >
                        {{ stats.medium_risk }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        Theo dõi suy giảm
                    </p>
                </CardContent>
            </Card>

            <!-- Healthy -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-2 p-4 text-center">
                    <p
                        class="text-[10px] font-bold tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                    >
                        Vận hành khỏe mạnh
                    </p>
                    <div
                        class="flex items-center justify-center gap-1 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400"
                    >
                        <CheckCircle2 class="size-5 text-emerald-500" />
                        {{ stats.low_risk }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        Sử dụng đều đặn
                    </p>
                </CardContent>
            </Card>

            <!-- System risk ratio (NEW) -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-2 p-4 text-center">
                    <p
                        class="text-[10px] font-bold tracking-wider text-orange-600 uppercase dark:text-orange-400"
                    >
                        Tỷ lệ rủi ro hệ thống
                    </p>
                    <div
                        class="flex items-center justify-center gap-0.5 font-mono text-2xl font-black text-orange-600 dark:text-orange-400"
                    >
                        <Percent class="size-4 text-orange-500" />
                        {{ stats.system_risk_ratio }}%
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        (Cao + TB) / Tổng
                    </p>
                </CardContent>
            </Card>

            <!-- Urgent action required (NEW) -->
            <Card
                class="border border-red-500/20 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-2 p-4 text-center">
                    <p
                        class="text-[10px] font-bold tracking-wider text-red-600 uppercase dark:text-red-400"
                    >
                        Hành động khẩn cấp
                    </p>
                    <div
                        class="flex items-center justify-center gap-1 font-mono text-2xl font-black text-red-600 dark:text-red-400"
                    >
                        <AlertOctagon
                            class="size-5 animate-pulse text-red-500"
                        />
                        {{ stats.urgent_action_required }}
                    </div>
                    <p class="text-[9px] font-bold text-red-600/80 uppercase">
                        Nhà hàng cần gọi điện
                    </p>
                </CardContent>
            </Card>

            <!-- Emails sent -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-2 p-4 text-center">
                    <p
                        class="text-[10px] font-bold tracking-wider text-blue-600 uppercase dark:text-blue-400"
                    >
                        Đã gửi thư điện tử
                    </p>
                    <div
                        class="flex items-center justify-center gap-1 font-mono text-2xl font-black text-blue-600 dark:text-blue-400"
                    >
                        <Mail class="size-5 text-blue-500" />
                        {{ stats.emails_sent }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        Tự động tặng mã giảm giá 30%
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Visual Analytics Charts Row (Donut Risk Distribution + Band Distribution Chart) -->
        <div class="dashboard-grid">
            <!-- Pie/Donut Risk Distribution -->
            <Card
                class="border border-border/40 bg-card/50 shadow-xs backdrop-blur-md"
            >
                <CardContent
                    class="flex flex-col items-center justify-around gap-6 p-5 sm:flex-row"
                >
                    <div class="space-y-2 text-center sm:text-left">
                        <h3
                            class="flex items-center gap-1 text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-200"
                        >
                            <Sparkles
                                class="size-4 animate-pulse text-primary"
                            />
                            Phân bổ Mức độ Nguy cơ
                        </h3>
                        <p class="text-[11px] text-muted-foreground">
                            Cơ cấu rủi ro của toàn bộ nhà hàng hệ thống
                        </p>

                        <div class="space-y-1.5 pt-3">
                            <div
                                v-for="slice in donutSlices"
                                :key="slice.label"
                                class="flex items-center gap-2 text-xs font-semibold"
                            >
                                <span
                                    class="size-2.5 rounded-full"
                                    :class="
                                        slice.color.replace('stroke-', 'bg-')
                                    "
                                ></span>
                                <span class="text-slate-600 dark:text-slate-300"
                                    >{{ slice.label }}:</span
                                >
                                <span
                                    class="font-mono text-slate-800 dark:text-slate-100"
                                    >{{ slice.value }} ({{
                                        Math.round(slice.percentage)
                                    }}%)</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Donut SVG widget -->
                    <div
                        class="relative flex size-28 items-center justify-center"
                    >
                        <svg
                            viewBox="0 0 42 42"
                            class="size-full -rotate-90 transform"
                        >
                            <circle
                                cx="21"
                                cy="21"
                                r="15.915"
                                fill="transparent"
                                stroke="rgba(226, 232, 240, 0.4)"
                                stroke-width="4"
                            ></circle>
                            <circle
                                v-for="(slice, index) in donutSlices"
                                :key="index"
                                cx="21"
                                cy="21"
                                r="15.915"
                                fill="transparent"
                                :class="slice.color"
                                stroke-width="4"
                                :stroke-dasharray="slice.dashArray"
                                :stroke-dashoffset="slice.dashOffset"
                                class="transition-all duration-700"
                            ></circle>
                        </svg>
                        <div
                            class="absolute flex flex-col items-center justify-center text-center"
                        >
                            <span
                                class="font-mono text-lg font-black text-slate-800 dark:text-slate-100"
                                >{{ stats.total_checked }}</span
                            >
                            <span
                                class="text-[8px] font-bold tracking-wider text-slate-400 uppercase"
                                >Tổng số</span
                            >
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Health Score Range Distribution -->
            <Card
                class="border border-border/40 bg-card/50 shadow-xs backdrop-blur-md"
            >
                <CardContent class="space-y-3 p-5">
                    <h3
                        class="flex items-center gap-1 text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-200"
                    >
                        <TrendingDown class="size-4 text-rose-500" />
                        Dải Điểm Sức Khỏe của Danh Sách Hiện Tại
                    </h3>
                    <p class="text-[11px] text-muted-foreground">
                        Phân bổ nhà hàng theo thang điểm sức khỏe 0 - 100
                    </p>

                    <!-- SVG-based Horizontal/Vertical Bar Distribution -->
                    <div
                        class="flex h-24 items-end justify-between border-b border-border/30 px-2 pt-4"
                    >
                        <div
                            v-for="band in healthScoreDistribution"
                            :key="band.label"
                            class="group flex w-1/4 flex-col items-center gap-1.5"
                        >
                            <!-- Tooltip count -->
                            <span
                                class="rounded bg-slate-900 px-1.5 py-0.5 font-mono text-[10px] font-black text-white opacity-0 shadow-sm transition-opacity group-hover:opacity-100"
                            >
                                {{ band.count }} nhà hàng
                            </span>
                            <!-- Bar -->
                            <div
                                :style="{ height: `${band.heightPct}%` }"
                                class="w-8 cursor-pointer rounded-t-md shadow-sm transition-all duration-500 hover:opacity-85"
                                :class="band.color"
                            ></div>
                            <span
                                class="mt-1 text-[9px] font-bold text-slate-500"
                                >{{ band.label }}</span
                            >
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters Block -->
        <Card class="bg-card/50">
            <CardContent
                class="flex flex-col items-center gap-3 p-4 sm:flex-row"
            >
                <div class="relative w-full sm:flex-1">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm theo tên nhà hàng, mã, hoặc thư điện tử/sđt chủ..."
                        @keyup.enter="applyFilters"
                        class="w-full rounded-xl border border-border/80 py-2 pr-4 pl-9 text-xs focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none dark:bg-slate-900"
                    />
                </div>

                <div class="flex w-full flex-wrap items-center gap-3 sm:w-auto">
                    <!-- Risk Filter -->
                    <div class="w-full sm:w-[150px]">
                        <Select
                            v-model="riskLevel"
                            @update:modelValue="applyFilters"
                        >
                            <SelectTrigger class="h-9 rounded-xl text-xs">
                                <SelectValue placeholder="Mức độ nguy cơ" />
                            </SelectTrigger>
                            <SelectContent class="text-xs">
                                <SelectItem value="all"
                                    >Tất cả nguy cơ</SelectItem
                                >
                                <SelectItem value="high"
                                    >Nguy cơ cao</SelectItem
                                >
                                <SelectItem value="medium"
                                    >Nguy cơ trung bình</SelectItem
                                >
                                <SelectItem value="low"
                                    >Nguy cơ thấp</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Plan Filter -->
                    <div class="w-full sm:w-[150px]">
                        <Select
                            v-model="planId"
                            @update:modelValue="applyFilters"
                        >
                            <SelectTrigger class="h-9 rounded-xl text-xs">
                                <SelectValue placeholder="Gói dịch vụ" />
                            </SelectTrigger>
                            <SelectContent class="text-xs">
                                <SelectItem value="all"
                                    >Tất cả các gói</SelectItem
                                >
                                <SelectItem
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    :value="plan.id.toString()"
                                >
                                    {{ plan.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Actions -->
                    <button
                        type="button"
                        @click="applyFilters"
                        class="inline-flex h-9 cursor-pointer items-center justify-center rounded-xl bg-muted px-4 text-xs font-bold transition-all hover:bg-muted/80"
                    >
                        Lọc kết quả
                    </button>
                    <button
                        v-if="search || riskLevel !== 'all' || planId !== 'all'"
                        type="button"
                        @click="resetFilters"
                        class="cursor-pointer text-xs font-semibold text-muted-foreground hover:underline"
                    >
                        Xóa bộ lọc
                    </button>
                </div>
            </CardContent>
        </Card>

        <!-- Tenants List Table -->
        <Card class="overflow-hidden border border-border/60 shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr
                            class="border-b border-border/60 bg-muted/40 text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                        >
                            <th class="w-[240px] p-4">Nhà hàng</th>
                            <th class="w-[160px] p-4">Chủ sở hữu</th>
                            <th class="w-[100px] p-4 text-center">Gói cước</th>
                            <th class="w-[120px] p-4 text-center">
                                Sức khỏe (Score)
                            </th>
                            <th class="w-[150px] p-4 text-center">
                                Tình trạng rủi ro
                            </th>
                            <th class="w-[160px] p-4 text-center">
                                Tự động gửi thư điện tử
                            </th>
                            <th class="p-4 pr-6 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40 text-xs">
                        <tr
                            v-for="restaurant in restaurants.data"
                            :key="restaurant.id"
                            class="transition-all hover:bg-muted/20"
                        >
                            <!-- Restaurant Column (with CRM Tags inline) -->
                            <td class="p-4 font-medium">
                                <div
                                    class="font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ restaurant.name }}
                                </div>
                                <div
                                    class="mt-0.5 flex items-center gap-1 font-mono text-[10px] text-muted-foreground"
                                >
                                    <span>Code: {{ restaurant.code }}</span>
                                    <span
                                        v-if="restaurant.status === 'suspended'"
                                        class="ml-1 font-bold text-rose-500 uppercase"
                                        >(Tạm khóa)</span
                                    >
                                </div>
                                <!-- Inline CRM Tags -->
                                <div
                                    v-if="restaurant.tags?.length"
                                    class="mt-1.5 flex flex-wrap gap-1"
                                >
                                    <span
                                        v-for="tag in restaurant.tags"
                                        :key="tag.id"
                                        class="rounded border px-1.5 py-0.5 text-[8px] font-bold uppercase"
                                        :class="tag.color"
                                    >
                                        {{ tag.name }}
                                    </span>
                                </div>
                            </td>

                            <!-- Owner Contact -->
                            <td class="p-4 text-muted-foreground">
                                <div
                                    class="flex items-center gap-1 font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    <User class="size-3 text-slate-400" />
                                    {{ restaurant.owner_name }}
                                </div>
                                <div
                                    class="mt-0.5 flex flex-col text-[10px] leading-tight"
                                >
                                    <span>{{ restaurant.owner_email }}</span>
                                    <span
                                        class="mt-0.5 flex items-center gap-0.5 font-mono text-slate-400"
                                    >
                                        <Phone class="size-2.5" />
                                        {{ restaurant.owner_phone }}
                                    </span>
                                </div>
                            </td>

                            <!-- Plan -->
                            <td class="p-4 text-center">
                                <Badge
                                    variant="outline"
                                    class="rounded-lg text-[10px] font-semibold"
                                >
                                    {{ restaurant.plan_name }}
                                </Badge>
                            </td>

                            <!-- Health Score -->
                            <td class="p-4 text-center">
                                <div
                                    class="flex items-center justify-center gap-2"
                                >
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-0.5 font-mono text-[11px] font-black',
                                            getScoreBadgeClass(
                                                restaurant.health_score,
                                            ),
                                        ]"
                                    >
                                        {{ restaurant.health_score }}/100
                                    </span>
                                    <button
                                        type="button"
                                        @click="openDetails(restaurant)"
                                        class="cursor-pointer text-muted-foreground transition-all hover:text-primary"
                                        title="Xem chi tiết hành vi"
                                    >
                                        <HelpCircle class="size-3.5" />
                                    </button>
                                </div>
                            </td>

                            <!-- Churn Risk Level -->
                            <td class="p-4 text-center">
                                <span
                                    :class="[
                                        'rounded-full border border-border/20 px-2 py-0.5 text-[10px] font-bold',
                                        getRiskBadgeClass(
                                            restaurant.churn_risk_level,
                                        ),
                                    ]"
                                >
                                    {{
                                        getRiskLabel(
                                            restaurant.churn_risk_level,
                                        )
                                    }}
                                </span>
                            </td>

                            <!-- Auto-outreach status -->
                            <td
                                class="p-4 text-center text-[10px] text-muted-foreground"
                            >
                                <div
                                    v-if="restaurant.churn_risk_flagged_at"
                                    class="flex flex-col items-center gap-0.5 text-blue-600 dark:text-blue-400"
                                >
                                    <span
                                        class="flex items-center gap-1 font-bold"
                                    >
                                        <Mail class="size-3" /> Đã gửi thư điện
                                        tử
                                    </span>
                                    <span
                                        class="font-mono text-[9px] text-slate-400"
                                        >{{
                                            restaurant.churn_risk_flagged_at
                                        }}</span
                                    >
                                </div>
                                <div
                                    v-else
                                    class="font-semibold text-slate-400 italic"
                                >
                                    Chưa gửi
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="p-4 pr-6 text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <!-- Detail popup trigger -->
                                    <button
                                        type="button"
                                        @click="openDetails(restaurant)"
                                        class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-border/80 px-2.5 py-1 text-[11px] font-bold transition-all hover:bg-muted/70"
                                    >
                                        Chi tiết sức khỏe
                                    </button>

                                    <!-- Send Manual Campaign Outreach Email -->
                                    <button
                                        type="button"
                                        @click="
                                            sendOutreachEmail(restaurant.id)
                                        "
                                        :disabled="
                                            triggeringEmailId === restaurant.id
                                        "
                                        class="inline-flex cursor-pointer items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1 text-[11px] font-bold text-white transition-all hover:bg-blue-700 disabled:opacity-50"
                                    >
                                        <Mail
                                            :class="[
                                                'size-3',
                                                {
                                                    'animate-spin':
                                                        triggeringEmailId ===
                                                        restaurant.id,
                                                },
                                            ]"
                                        />
                                        Gửi thư điện tử tri ân
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="restaurants.data.length === 0">
                            <td
                                colspan="7"
                                class="p-8 text-center font-medium text-muted-foreground italic"
                            >
                                Không tìm thấy dữ liệu nhà hàng nào tương ứng
                                với bộ lọc.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination links -->
            <div
                v-if="restaurants.last_page > 1"
                class="flex items-center justify-between border-t border-border/60 bg-muted/20 p-4"
            >
                <p class="text-xs text-muted-foreground">
                    Đang hiển thị trang
                    <strong>{{ restaurants.current_page }}</strong> trên tổng số
                    <strong>{{ restaurants.last_page }}</strong> trang (Tổng số
                    {{ restaurants.total }} nhà hàng)
                </p>
                <Pagination :links="restaurants.links" class="border-t-0 p-0" />
            </div>
        </Card>

        <!-- CRM Dialog (Health Analysis, Internal Notes & Tags, Outreach Reminders) -->
        <Dialog :open="selectedDetails !== null" @update:open="closeDetails">
            <DialogContent
                class="flex max-h-[92vh] flex-col overflow-hidden p-6 sm:max-w-[520px]"
            >
                <DialogHeader class="shrink-0">
                    <DialogTitle
                        class="flex items-center gap-2 text-slate-800 dark:text-slate-100"
                    >
                        <ShieldAlert class="size-5 text-rose-500" />
                        Chỉ số &amp; CRM: {{ selectedDetails?.name }}
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Chi tiết phân tích rủi ro kèm lịch sử và hoạt động chăm
                        sóc khách hàng.
                    </DialogDescription>
                </DialogHeader>

                <!-- Navigation Tabs inside Modal -->
                <div class="mt-4 flex shrink-0 border-b border-border/40">
                    <button
                        v-for="tab in ['health', 'crm', 'followup'] as const"
                        :key="tab"
                        @click="activeTab = tab"
                        class="flex-1 cursor-pointer border-b-2 pb-2.5 text-center text-xs font-bold transition-all"
                        :class="
                            activeTab === tab
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-slate-700'
                        "
                    >
                        {{
                            tab === 'health'
                                ? 'Sức khỏe & Khuyến nghị'
                                : tab === 'crm'
                                  ? 'Nhãn & Ghi chú (CRM)'
                                  : 'Đặt lịch chăm sóc'
                        }}
                    </button>
                </div>

                <!-- Tabs Content (Scrollable Container) -->
                <div
                    v-if="selectedDetails"
                    class="flex-1 space-y-4 overflow-y-auto pt-4 pr-1"
                >
                    <!-- TAB 1: HEALTH ANALYSIS & DATA SUGGESTIONS -->
                    <div v-if="activeTab === 'health'" class="space-y-4">
                        <!-- Health Score Summary Card -->
                        <div
                            class="flex items-center justify-between rounded-xl border border-border/40 bg-muted/50 p-3.5"
                        >
                            <div>
                                <span
                                    class="text-xs font-bold text-slate-500 dark:text-slate-400"
                                    >Điểm sức khỏe hiện tại:</span
                                >
                                <p
                                    class="mt-0.5 text-[10px] text-muted-foreground"
                                >
                                    Thời gian cập nhật:
                                    {{
                                        selectedDetails.last_health_checked_at ??
                                        '-'
                                    }}
                                </p>
                            </div>
                            <span
                                :class="[
                                    'rounded-full px-3 py-1 font-mono text-sm font-black',
                                    getScoreBadgeClass(
                                        selectedDetails.health_score,
                                    ),
                                ]"
                            >
                                {{ selectedDetails.health_score }} / 100
                            </span>
                        </div>

                        <!-- Behavior breakdown grid -->
                        <div class="space-y-3">
                            <h4
                                class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Chỉ số thành phần chi tiết
                            </h4>

                            <!-- Login Inactivity -->
                            <div
                                class="flex items-start justify-between border-b border-border/40 py-2 text-xs"
                            >
                                <div>
                                    <p
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Thời gian đăng nhập gần nhất
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-muted-foreground"
                                    >
                                        Ngưỡng cảnh báo: Lớn hơn 3 ngày
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono font-bold"
                                        >{{
                                            selectedDetails.breakdown
                                                .days_since_login
                                        }}
                                        ngày trước</span
                                    >
                                    <p
                                        class="text-[9px] font-bold"
                                        :class="
                                            selectedDetails.breakdown
                                                .days_since_login > 3
                                                ? 'text-rose-500'
                                                : 'text-emerald-500'
                                        "
                                    >
                                        {{
                                            selectedDetails.breakdown
                                                .days_since_login > 3
                                                ? 'Không hoạt động'
                                                : 'Tốt'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <!-- Order frequency drop -->
                            <div
                                class="flex items-start justify-between border-b border-border/40 py-2 text-xs"
                            >
                                <div>
                                    <p
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Tần suất tạo đơn hàng trong tuần
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-muted-foreground"
                                    >
                                        Tuần này:
                                        {{
                                            selectedDetails.breakdown
                                                .current_week_orders
                                        }}
                                        đơn · TB 3 tuần trước:
                                        {{
                                            selectedDetails.breakdown
                                                .prev_weekly_avg
                                        }}
                                        đơn/tuần
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span
                                        class="font-mono font-bold text-rose-500"
                                        v-if="
                                            selectedDetails.breakdown
                                                .drop_percentage > 0
                                        "
                                    >
                                        Giảm -{{
                                            selectedDetails.breakdown
                                                .drop_percentage
                                        }}%
                                    </span>
                                    <span
                                        class="font-mono font-bold text-emerald-500"
                                        v-else
                                    >
                                        Ổn định (0%)
                                    </span>
                                    <p
                                        class="text-[9px] font-bold"
                                        :class="
                                            selectedDetails.breakdown
                                                .drop_percentage >= 30
                                                ? 'text-rose-500'
                                                : 'text-slate-400'
                                        "
                                    >
                                        {{
                                            selectedDetails.breakdown
                                                .drop_percentage >= 30
                                                ? 'Sụt giảm mạnh'
                                                : 'Bình thường'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <!-- Unresolved Tickets -->
                            <div
                                class="flex items-start justify-between border-b border-border/40 py-2 text-xs"
                            >
                                <div>
                                    <p
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Ticket khiếu nại chưa xử lý
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-muted-foreground"
                                    >
                                        Mục tiêu: Hoàn thành nhanh chóng để lấy
                                        lại sự hài lòng
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono font-bold"
                                        >{{
                                            selectedDetails.breakdown
                                                .unresolved_tickets
                                        }}
                                        ticket</span
                                    >
                                    <p
                                        class="text-[9px] font-bold"
                                        :class="
                                            selectedDetails.breakdown
                                                .unresolved_tickets > 0
                                                ? 'text-amber-500'
                                                : 'text-emerald-500'
                                        "
                                    >
                                        {{
                                            selectedDetails.breakdown
                                                .unresolved_tickets > 0
                                                ? 'Tồn đọng'
                                                : 'Tốt'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Data-driven suggestions section -->
                        <div class="space-y-2">
                            <h4
                                class="flex items-center gap-1 text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >
                                <Sparkles class="size-4 text-primary" />
                                Gợi ý Hành Động dựa trên Dữ Liệu
                            </h4>

                            <div class="space-y-2">
                                <div
                                    v-for="(rec, index) in getRecommendations(
                                        selectedDetails,
                                    )"
                                    :key="index"
                                    class="rounded-lg border p-3 text-xs"
                                    :class="[
                                        rec.type === 'danger'
                                            ? 'border-rose-500/20 bg-rose-500/[0.04] text-rose-800 dark:text-rose-300'
                                            : rec.type === 'warning'
                                              ? 'border-amber-500/20 bg-amber-500/[0.04] text-amber-800 dark:text-amber-300'
                                              : rec.type === 'info'
                                                ? 'border-blue-500/20 bg-blue-500/[0.04] text-blue-800 dark:text-blue-300'
                                                : 'border-emerald-500/20 bg-emerald-500/[0.04] text-emerald-800 dark:text-emerald-300',
                                    ]"
                                >
                                    <div
                                        class="mb-0.5 flex items-center gap-1.5 font-bold"
                                    >
                                        <AlertTriangle
                                            v-if="
                                                rec.type === 'danger' ||
                                                rec.type === 'warning'
                                            "
                                            class="size-3.5"
                                        />
                                        <CheckCircle2 v-else class="size-3.5" />
                                        {{ rec.title }}
                                    </div>
                                    <p
                                        class="text-[11px] leading-relaxed font-medium"
                                    >
                                        {{ rec.desc }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: CRM TAGS & INTERNAL NOTES -->
                    <div v-if="activeTab === 'crm'" class="space-y-4">
                        <!-- Tags management section -->
                        <div class="space-y-2 border-b border-border/40 pb-3">
                            <h4
                                class="flex items-center gap-1 text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >
                                <Tag class="size-3.5" /> Thẻ Nhãn (CRM Tags)
                            </h4>

                            <!-- Display current tags -->
                            <div
                                class="flex min-h-[30px] flex-wrap items-center gap-1.5"
                            >
                                <span
                                    v-for="tag in selectedDetails.tags"
                                    :key="tag.id"
                                    class="inline-flex items-center gap-1 rounded border px-2.5 py-0.5 text-[9px] font-bold uppercase"
                                    :class="tag.color"
                                >
                                    {{ tag.name }}
                                    <button
                                        type="button"
                                        @click="
                                            deleteTag(
                                                selectedDetails.id,
                                                tag.id,
                                            )
                                        "
                                        class="ml-1 cursor-pointer font-extrabold transition-colors hover:text-red-500"
                                    >
                                        ×
                                    </button>
                                </span>
                                <span
                                    v-if="!selectedDetails.tags?.length"
                                    class="text-xs text-muted-foreground italic"
                                >
                                    Chưa gắn nhãn phân loại nào.
                                </span>
                            </div>

                            <!-- Form to add tag -->
                            <div
                                class="flex flex-col gap-2 rounded-lg border border-border/40 bg-muted/30 p-2.5 pt-2"
                            >
                                <div class="flex gap-2">
                                    <input
                                        v-model="newTagName"
                                        type="text"
                                        placeholder="Nhập tên nhãn mới..."
                                        class="flex-1 rounded-lg border border-border/80 px-3 py-1.5 text-xs focus:outline-none dark:bg-slate-900"
                                        @keyup.enter="
                                            addTag(selectedDetails.id)
                                        "
                                    />
                                    <button
                                        type="button"
                                        @click="addTag(selectedDetails.id)"
                                        :disabled="
                                            isSavingTag || !newTagName.trim()
                                        "
                                        class="cursor-pointer rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-primary-foreground hover:bg-primary/95 disabled:opacity-50"
                                    >
                                        <Plus class="size-3.5" />
                                    </button>
                                </div>
                                <div
                                    class="flex flex-wrap justify-start gap-1.5"
                                >
                                    <button
                                        v-for="preset in tagPresets"
                                        :key="preset.value"
                                        type="button"
                                        @click="newTagColor = preset.value"
                                        class="cursor-pointer rounded border px-2 py-0.5 text-[9px] font-bold uppercase transition-all"
                                        :class="[
                                            preset.value,
                                            newTagColor === preset.value
                                                ? 'scale-105 ring-2 ring-primary ring-offset-1'
                                                : 'opacity-65',
                                        ]"
                                    >
                                        {{ preset.label }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Internal CRM Notes Section -->
                        <div class="space-y-2">
                            <h4
                                class="flex items-center gap-1 text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >
                                <SlidersHorizontal class="size-3.5" /> Ghi Chú
                                Chăm Sóc (CRM Notes)
                            </h4>

                            <!-- Notes scrollable list -->
                            <div
                                class="max-h-[160px] space-y-2 overflow-y-auto rounded-lg border border-border/40 bg-slate-50/50 p-2 pr-1 dark:bg-slate-900/30"
                            >
                                <div
                                    v-for="note in selectedDetails.notes"
                                    :key="note.id"
                                    class="rounded-md border border-border/20 bg-white p-2 text-xs dark:bg-slate-950"
                                >
                                    <div
                                        class="flex items-start justify-between"
                                    >
                                        <span
                                            class="font-bold text-slate-700 dark:text-slate-300"
                                            >{{ note.user_name }}</span
                                        >
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="font-mono text-[9px] text-slate-400"
                                                >{{ note.created_at }}</span
                                            >
                                            <button
                                                type="button"
                                                @click="
                                                    deleteNote(
                                                        selectedDetails.id,
                                                        note.id,
                                                    )
                                                "
                                                class="cursor-pointer text-slate-400 transition-colors hover:text-rose-500"
                                            >
                                                <Trash2 class="size-3" />
                                            </button>
                                        </div>
                                    </div>
                                    <p
                                        class="mt-1 text-[11px] leading-relaxed whitespace-pre-wrap text-slate-600 dark:text-slate-400"
                                    >
                                        {{ note.note }}
                                    </p>
                                </div>
                                <div
                                    v-if="!selectedDetails.notes?.length"
                                    class="py-5 text-center text-xs text-muted-foreground italic"
                                >
                                    Chưa có ghi chú nội bộ nào.
                                </div>
                            </div>

                            <!-- Add CRM Note form -->
                            <div class="flex flex-col gap-2 pt-1">
                                <textarea
                                    v-model="newNote"
                                    placeholder="Viết nội dung chăm sóc, phản hồi của khách hàng sau khi gọi điện..."
                                    rows="2"
                                    class="w-full resize-none rounded-lg border border-border/80 p-2.5 text-xs focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none dark:bg-slate-900"
                                ></textarea>
                                <div class="flex justify-end">
                                    <button
                                        type="button"
                                        @click="saveNote(selectedDetails.id)"
                                        :disabled="
                                            isSavingNote || !newNote.trim()
                                        "
                                        class="cursor-pointer rounded-lg bg-primary px-4 py-1.5 text-xs font-bold text-primary-foreground hover:bg-primary/95 disabled:opacity-50"
                                    >
                                        Lưu Ghi Chú
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: SCHEDULE OUTREACH REMINDERS -->
                    <div v-if="activeTab === 'followup'" class="space-y-4">
                        <div class="space-y-2">
                            <h4
                                class="flex items-center gap-1 text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >
                                <Clock class="size-3.5" /> Lịch Hẹn &amp; Gọi
                                Chăm Sóc Đang Chờ
                            </h4>

                            <!-- Followups list -->
                            <div
                                class="max-h-[170px] space-y-2 overflow-y-auto rounded-lg border border-border/40 bg-slate-50/50 p-2 pr-1 dark:bg-slate-900/30"
                            >
                                <div
                                    v-for="f in selectedDetails.followups"
                                    :key="f.id"
                                    class="rounded-md border border-border/20 bg-white p-2.5 text-xs dark:bg-slate-950"
                                >
                                    <div
                                        class="flex items-start justify-between gap-1"
                                    >
                                        <span
                                            class="font-bold text-slate-700 dark:text-slate-300"
                                            >Phụ trách:
                                            {{ f.assigned_user_name }}</span
                                        >
                                        <div
                                            class="flex shrink-0 items-center gap-1.5"
                                        >
                                            <span
                                                class="rounded px-1.5 py-0.5 text-[8px] font-bold uppercase"
                                                :class="
                                                    f.status === 'completed'
                                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
                                                "
                                            >
                                                {{
                                                    f.status === 'completed'
                                                        ? 'Đã liên hệ'
                                                        : 'Chờ xử lý'
                                                }}
                                            </span>
                                            <button
                                                v-if="f.status !== 'completed'"
                                                type="button"
                                                @click="
                                                    completeFollowup(
                                                        selectedDetails.id,
                                                        f.id,
                                                    )
                                                "
                                                class="cursor-pointer font-extrabold text-emerald-500 hover:text-emerald-700"
                                                title="Đánh dấu đã hoàn thành"
                                            >
                                                <Check class="size-4" />
                                            </button>
                                        </div>
                                    </div>
                                    <p
                                        class="mt-1.5 text-[11px] leading-relaxed text-slate-600 dark:text-slate-400"
                                    >
                                        {{ f.note }}
                                    </p>
                                    <div
                                        class="mt-1.5 flex items-center gap-1 font-mono text-[9px] text-slate-400"
                                    >
                                        <Clock class="size-2.5" /> Hẹn lúc:
                                        {{ f.remind_at }}
                                    </div>
                                </div>
                                <div
                                    v-if="!selectedDetails.followups?.length"
                                    class="py-5 text-center text-xs text-muted-foreground italic"
                                >
                                    Chưa lên lịch hẹn chăm sóc nào.
                                </div>
                            </div>
                        </div>

                        <!-- Schedule new followup form -->
                        <div
                            class="space-y-3 rounded-lg border border-border/40 bg-muted/40 p-3.5"
                        >
                            <h4
                                class="text-xs font-bold text-slate-800 dark:text-slate-200"
                            >
                                Đặt lịch hẹn mới
                            </h4>

                            <!-- Input Description -->
                            <div class="space-y-1">
                                <label
                                    class="text-[10px] font-bold text-slate-500 uppercase"
                                    >Nội dung cuộc gọi / công việc</label
                                >
                                <input
                                    v-model="followupNote"
                                    type="text"
                                    placeholder="Ví dụ: Gọi điện kiểm tra lý do 5 ngày không có đơn..."
                                    class="w-full rounded-lg border border-border/80 px-3 py-1.5 text-xs focus:outline-none dark:bg-slate-900"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <!-- DateTime selector -->
                                <div class="space-y-1">
                                    <label
                                        class="text-[10px] font-bold text-slate-500 uppercase"
                                        >Thời gian nhắc nhở</label
                                    >
                                    <input
                                        v-model="remindAt"
                                        type="datetime-local"
                                        class="w-full rounded-lg border border-border/80 px-3 py-1.5 font-mono text-xs focus:outline-none dark:bg-slate-900"
                                    />
                                </div>

                                <!-- Agent selector -->
                                <div class="space-y-1">
                                    <label
                                        class="text-[10px] font-bold text-slate-500 uppercase"
                                        >Nhân sự chăm sóc</label
                                    >
                                    <select
                                        v-model="assignedTo"
                                        class="w-full rounded-lg border border-border/80 px-3 py-1.5 text-xs focus:outline-none dark:bg-slate-900"
                                    >
                                        <option value="" disabled selected>
                                            Chọn admin...
                                        </option>
                                        <option
                                            v-for="admin in admins"
                                            :key="admin.id"
                                            :value="admin.id"
                                        >
                                            {{ admin.name }} ({{ admin.email }})
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-end pt-1">
                                <button
                                    type="button"
                                    @click="saveFollowup(selectedDetails.id)"
                                    :disabled="
                                        isSavingFollowup ||
                                        !followupNote.trim() ||
                                        !remindAt ||
                                        !assignedTo
                                    "
                                    class="cursor-pointer rounded-lg bg-blue-600 px-4 py-1.5 text-xs font-bold text-primary-foreground hover:bg-blue-700 disabled:opacity-50"
                                >
                                    Lên Lịch Nhắc
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer actions inside modal (Shrink-0) -->
                <div
                    class="mt-4 flex shrink-0 justify-end gap-2 border-t border-border/50 pt-4"
                >
                    <button
                        type="button"
                        @click="closeDetails"
                        class="inline-flex h-9 cursor-pointer items-center justify-center rounded-xl border border-border px-4 text-xs font-bold transition-all hover:bg-muted/50"
                    >
                        Đóng cửa sổ
                    </button>
                    <button
                        type="button"
                        @click="
                            sendOutreachEmail(selectedDetails!.id);
                            closeDetails();
                        "
                        :disabled="triggeringEmailId === selectedDetails?.id"
                        class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-xl bg-blue-600 px-4 text-xs font-bold text-white shadow-md transition-all hover:bg-blue-700 disabled:opacity-50"
                    >
                        <Mail
                            :class="[
                                'size-3.5',
                                {
                                    'animate-spin':
                                        triggeringEmailId ===
                                        selectedDetails?.id,
                                },
                            ]"
                        />
                        Gửi thư điện tử tri ân chăm sóc
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.donut-segment {
    transform-origin: center;
}
</style>
