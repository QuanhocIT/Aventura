<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowUpCircle,
    Camera,
    CheckCircle2,
    ChevronDown,
    ClipboardCheck,
    Clock3,
    FileText,
    Flame,
    HeartPulse,
    Info,
    Lock,
    MapPin,
    Plus,
    Search,
    ShieldAlert,
    ShieldCheck,
    ShieldQuestion,
    Siren,
    TimerReset,
    UserRound,
    Users,
    Wrench,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type IncidentStatus = 'open' | 'investigating' | 'escalated' | 'resolved';
type SlaState = 'on_track' | 'acknowledged' | 'overdue' | 'breached' | 'met';

interface Incident {
    id: number;
    code: string;
    type: string;
    severity: 'low' | 'medium' | 'high' | 'critical';
    title: string;
    description: string;
    location: string | null;
    occurred_at_display: string | null;
    immediate_action: string | null;
    injured_count: number;
    needs_shift_cover: boolean;
    status: IncidentStatus;
    escalated: boolean;
    escalated_to_name: string | null;
    escalated_at_display: string | null;
    reported_by_name: string;
    branch_name: string | null;
    acknowledged_by_name: string | null;
    acknowledged_at_display: string | null;
    resolution_report: string | null;
    resolved_by_name: string | null;
    resolved_at_display: string | null;
    created_at_display: string | null;
    has_photo: boolean;
    photo_url: string | null;
    response_due_at_display: string | null;
    response_time_minutes: number | null;
    resolution_time_minutes: number | null;
    response_sla_minutes: number;
    sla_state: SlaState;
}

interface KpiCard {
    label: string;
    helper: string;
    value: number;
    icon: Component;
    iconClass: string;
    valueClass?: string;
}

const props = defineProps<{
    incidents: Incident[];
    stats: {
        open: number;
        awaiting_ack: number;
        escalated: number;
        resolved: number;
        critical: number;
        overdue: number;
        needs_shift_cover: number;
        last_24h: number;
    };
    canManage: boolean;
    activeBranchName: string | null;
}>();

const showReportForm = ref(false);
const activeFilter = ref<'active' | 'resolved' | 'all'>('active');
const searchQuery = ref('');
const severityFilter = ref('all');
const typeFilter = ref('all');
const sortMode = ref<'priority' | 'recent'>('priority');
const expandedId = ref<number | null>(null);

const reportForm = useForm({
    type: 'accident',
    severity: 'medium',
    title: '',
    description: '',
    location: '',
    occurred_at: new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16),
    immediate_action: '',
    injured_count: 0,
    needs_shift_cover: false,
    photo: null as File | null,
});

const showResolveModal = ref(false);
const selected = ref<Incident | null>(null);
const resolveForm = useForm({ resolution_report: '' });

const typeConfig: Record<
    string,
    { label: string; icon: Component; iconClass: string; dotClass: string }
> = {
    accident: {
        label: 'Tai nạn',
        icon: HeartPulse,
        iconClass: 'text-rose-500 dark:text-rose-400',
        dotClass: 'bg-rose-500',
    },
    food_poisoning: {
        label: 'Ngộ độc thực phẩm',
        icon: Siren,
        iconClass: 'text-orange-500 dark:text-orange-400',
        dotClass: 'bg-orange-500',
    },
    fire: {
        label: 'Cháy nổ',
        icon: Flame,
        iconClass: 'text-red-500 dark:text-red-400',
        dotClass: 'bg-red-500',
    },
    security: {
        label: 'An ninh',
        icon: ShieldAlert,
        iconClass: 'text-indigo-500 dark:text-indigo-400',
        dotClass: 'bg-indigo-500',
    },
    equipment_failure: {
        label: 'Hỏng thiết bị',
        icon: Wrench,
        iconClass: 'text-slate-500 dark:text-slate-400',
        dotClass: 'bg-slate-400',
    },
    theft: {
        label: 'Trộm cắp',
        icon: ShieldQuestion,
        iconClass: 'text-amber-500 dark:text-amber-400',
        dotClass: 'bg-amber-500',
    },
    other: {
        label: 'Khác',
        icon: ShieldQuestion,
        iconClass: 'text-slate-500 dark:text-slate-400',
        dotClass: 'bg-slate-400',
    },
};

const severityConfig: Record<
    string,
    { label: string; badgeClass: string; railClass: string; rank: number }
> = {
    low: {
        label: 'Thấp',
        badgeClass: 'border-slate-200/70 bg-slate-100/70 text-slate-700 backdrop-blur-xs dark:border-white/[0.08] dark:bg-white/[0.05] dark:text-slate-300',
        railClass: 'bg-slate-400',
        rank: 1,
    },
    medium: {
        label: 'Trung bình',
        badgeClass: 'border-amber-500/20 bg-amber-500/10 text-amber-700 backdrop-blur-xs dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-300',
        railClass: 'bg-amber-500',
        rank: 2,
    },
    high: {
        label: 'Cao',
        badgeClass: 'border-orange-500/20 bg-orange-500/10 text-orange-700 backdrop-blur-xs dark:border-orange-400/20 dark:bg-orange-400/10 dark:text-orange-300',
        railClass: 'bg-orange-500',
        rank: 3,
    },
    critical: {
        label: 'Nghiêm trọng',
        badgeClass: 'border-rose-500/20 bg-rose-500/10 text-rose-700 backdrop-blur-xs dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300',
        railClass: 'bg-rose-500',
        rank: 4,
    },
};

const statusConfig: Record<IncidentStatus, { label: string; class: string }> = {
    open: {
        label: 'Chờ tiếp nhận',
        class: 'border-blue-500/20 bg-blue-500/10 text-blue-700 backdrop-blur-xs dark:border-blue-400/20 dark:bg-blue-400/10 dark:text-blue-300',
    },
    investigating: {
        label: 'Đang xử lý',
        class: 'border-amber-500/20 bg-amber-500/10 text-amber-700 backdrop-blur-xs dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-300',
    },
    escalated: {
        label: 'Đã báo Chủ',
        class: 'border-purple-500/20 bg-purple-500/10 text-purple-700 backdrop-blur-xs dark:border-purple-400/20 dark:bg-purple-400/10 dark:text-purple-300',
    },
    resolved: {
        label: 'Đã đóng',
        class: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 backdrop-blur-xs dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300',
    },
};

const guidance: Record<
    string,
    { title: string; text: string; icon: Component }
> = {
    accident: {
        title: 'Ưu tiên an toàn con người',
        text: 'Sơ cứu trong khả năng, gọi 115 khi cần và cô lập khu vực nguy hiểm.',
        icon: HeartPulse,
    },
    food_poisoning: {
        title: 'Giữ lại mẫu và danh sách liên quan',
        text: 'Tạm dừng phục vụ món nghi ngờ, lưu mẫu và báo ngay cho quản lý.',
        icon: Siren,
    },
    fire: {
        title: 'Báo động và sơ tán trước',
        text: 'Kích hoạt báo cháy, gọi 114, cắt điện nếu an toàn và không quay lại khu vực.',
        icon: Flame,
    },
    security: {
        title: 'Bảo toàn hiện trường',
        text: 'Ưu tiên an toàn, không tự đối đầu và giữ lại camera/nhân chứng liên quan.',
        icon: ShieldAlert,
    },
    equipment_failure: {
        title: 'Cô lập thiết bị',
        text: 'Dừng sử dụng, ngắt nguồn nếu an toàn và ghi nhận mã thiết bị/sự cố.',
        icon: Wrench,
    },
    theft: {
        title: 'Không làm xáo trộn hiện trường',
        text: 'Báo quản lý, khóa khu vực và giữ nguyên dữ liệu camera hoặc chứng từ.',
        icon: ShieldQuestion,
    },
    other: {
        title: 'Mô tả đúng sự thật',
        text: 'Ghi nhận diễn biến, người liên quan và biện pháp đã thực hiện ngay tại chỗ.',
        icon: Info,
    },
};

const activeGuidance = computed(
    () => guidance[reportForm.type] ?? guidance.other,
);

const kpis = computed<KpiCard[]>(() => [
    {
        label: 'Đang mở',
        helper: 'Tất cả sự cố chưa đóng',
        value: props.stats.open,
        icon: Activity,
        iconClass: 'border-blue-500/20 bg-blue-500/10 text-blue-500 dark:border-blue-400/20 dark:bg-blue-400/10 dark:text-blue-400',
        valueClass: props.stats.open > 0 ? 'text-blue-600 dark:text-blue-400' : '',
    },
    {
        label: 'Chờ tiếp nhận',
        helper: 'Cần quản lý xác nhận',
        value: props.stats.awaiting_ack,
        icon: ClipboardCheck,
        iconClass: 'border-amber-500/20 bg-amber-500/10 text-amber-500 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-400',
        valueClass: props.stats.awaiting_ack > 0 ? 'text-amber-600 dark:text-amber-400' : '',
    },
    {
        label: 'Quá SLA',
        helper: 'Chưa phản hồi đúng hạn',
        value: props.stats.overdue,
        icon: TimerReset,
        iconClass: 'border-rose-500/20 bg-rose-500/10 text-rose-500 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-400',
        valueClass: props.stats.overdue > 0 ? 'text-rose-600 dark:text-rose-400' : '',
    },
    {
        label: 'Đã báo Chủ',
        helper: 'Đang ở cấp khẩn cấp',
        value: props.stats.escalated,
        icon: ArrowUpCircle,
        iconClass: 'border-purple-500/20 bg-purple-500/10 text-purple-500 dark:border-purple-400/20 dark:bg-purple-400/10 dark:text-purple-400',
        valueClass: props.stats.escalated > 0 ? 'text-purple-600 dark:text-purple-400' : '',
    },
    {
        label: 'Cần thay ca',
        helper: 'Nhân sự chưa thể tiếp tục',
        value: props.stats.needs_shift_cover,
        icon: Users,
        iconClass: 'border-sky-500/20 bg-sky-500/10 text-sky-500 dark:border-sky-400/20 dark:bg-sky-400/10 dark:text-sky-400',
        valueClass: props.stats.needs_shift_cover > 0 ? 'text-sky-600 dark:text-sky-400' : '',
    },
    {
        label: 'Đã đóng',
        helper: `${props.stats.last_24h} phát sinh 24 giờ qua`,
        value: props.stats.resolved,
        icon: ShieldCheck,
        iconClass: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-500 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-400',
        valueClass: '',
    },
]);

const filtered = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const result = props.incidents.filter((incident) => {
        const matchesTab =
            activeFilter.value === 'all' ||
            (activeFilter.value === 'resolved'
                ? incident.status === 'resolved'
                : incident.status !== 'resolved');
        const matchesSeverity =
            severityFilter.value === 'all' ||
            incident.severity === severityFilter.value;
        const matchesType =
            typeFilter.value === 'all' || incident.type === typeFilter.value;
        const haystack = [
            incident.code,
            incident.title,
            incident.description,
            incident.location,
            incident.reported_by_name,
            incident.branch_name,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
        const matchesSearch = !query || haystack.includes(query);

        return matchesTab && matchesSeverity && matchesType && matchesSearch;
    });

    return [...result].sort((a, b) => {
        if (sortMode.value === 'recent') {
            return b.id - a.id;
        }

        const aOverdue = a.sla_state === 'overdue' ? 1 : 0;
        const bOverdue = b.sla_state === 'overdue' ? 1 : 0;

        if (aOverdue !== bOverdue) {
            return bOverdue - aOverdue;
        }

        const aRank = severityConfig[a.severity]?.rank ?? 0;
        const bRank = severityConfig[b.severity]?.rank ?? 0;

        if (aRank !== bRank) {
            return bRank - aRank;
        }

        if (a.status === 'open' && b.status !== 'open') {
            return -1;
        }

        if (b.status === 'open' && a.status !== 'open') {
            return 1;
        }

        return b.id - a.id;
    });
});

const priorityQueue = computed(() =>
    props.incidents
        .filter((incident) => incident.status !== 'resolved')
        .sort((a, b) => {
            const aScore =
                (a.sla_state === 'overdue' ? 100 : 0) +
                (severityConfig[a.severity]?.rank ?? 0);
            const bScore =
                (b.sla_state === 'overdue' ? 100 : 0) +
                (severityConfig[b.severity]?.rank ?? 0);

            return bScore - aScore || b.id - a.id;
        })
        .slice(0, 4),
);

const openResolve = (incident: Incident) => {
    selected.value = incident;
    resolveForm.reset();
    resolveForm.clearErrors();
    showResolveModal.value = true;
};

const submitResolve = () => {
    if (!selected.value || resolveForm.processing) {
        return;
    }

    resolveForm.post(`/incidents/${selected.value.id}/resolve`, {
        preserveScroll: true,
        onSuccess: () => {
            showResolveModal.value = false;
            resolveForm.reset();
            selected.value = null;
        },
    });
};

const submitReport = () => {
    if (reportForm.processing) {
        return;
    }

    reportForm.post('/incidents', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            reportForm.reset();
            showReportForm.value = false;
        },
    });
};

const doAcknowledge = (incident: Incident) =>
    useForm({}).post(`/incidents/${incident.id}/acknowledge`, {
        preserveScroll: true,
    });
const doEscalate = (incident: Incident) =>
    useForm({}).post(`/incidents/${incident.id}/escalate`, {
        preserveScroll: true,
    });
const toggleExpanded = (incident: Incident) => {
    expandedId.value = expandedId.value === incident.id ? null : incident.id;
};

const formatMinutes = (minutes: number | null) => {
    if (minutes === null || minutes === undefined) {
        return '—';
    }

    if (minutes < 60) {
        return `${minutes} phút`;
    }

    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;

    return remaining ? `${hours}g ${remaining}p` : `${hours} giờ`;
};

const slaLabel = (state: SlaState) =>
    state === 'overdue'
        ? 'Quá SLA'
        : state === 'breached'
          ? 'Phản hồi trễ'
          : state === 'met'
            ? 'Đã đạt SLA'
            : state === 'acknowledged'
              ? 'Đã tiếp nhận'
              : 'Trong SLA';
const slaClass = (state: SlaState) =>
    state === 'overdue' || state === 'breached'
        ? 'border-rose-500/20 bg-rose-500/10 text-rose-700 backdrop-blur-xs dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300'
        : state === 'met' || state === 'acknowledged'
          ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 backdrop-blur-xs dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300'
          : 'border-sky-500/20 bg-sky-500/10 text-sky-700 backdrop-blur-xs dark:border-sky-400/20 dark:bg-sky-400/10 dark:text-sky-300';
const actionSummary = (incident: Incident) =>
    incident.status === 'open'
        ? 'Cần quản lý tiếp nhận'
        : incident.sla_state === 'overdue'
          ? 'Cần xử lý ngay — đã quá SLA'
          : incident.escalated
            ? 'Đang theo dõi cấp khẩn cấp'
            : 'Đang điều tra và khắc phục';
</script>

<template>
    <Head title="Sự cố khẩn cấp" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
        <!-- ── Top Header Bar (Translucent Glass Command Bar) ─────────────── -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-slate-200/60 bg-white/40 p-4 backdrop-blur-xl shadow-xs sm:p-5 md:flex-row md:items-center md:justify-between dark:border-white/[0.08] dark:bg-white/[0.025]"
        >
            <div class="flex items-center gap-3.5">
                <div
                    class="flex size-11 items-center justify-center rounded-xl border border-rose-500/20 bg-rose-500/10 text-rose-600 shadow-xs dark:border-rose-500/30 dark:bg-rose-500/15 dark:text-rose-400"
                >
                    <Siren class="size-5" />
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-white">
                            Trung tâm Điều phối Sự cố
                        </h1>
                        <Badge
                            variant="outline"
                            class="border-rose-500/30 bg-rose-500/10 text-xs font-semibold text-rose-700 backdrop-blur-xs dark:text-rose-400"
                        >
                            Safety Operations
                        </Badge>
                        <Badge
                            variant="secondary"
                            class="border border-slate-200/60 bg-white/60 text-xs font-medium text-slate-600 backdrop-blur-xs dark:border-white/[0.08] dark:bg-white/[0.05] dark:text-slate-300"
                        >
                            <MapPin class="mr-1 size-3 text-slate-400" />
                            {{ props.activeBranchName || 'Toàn nhà hàng' }}
                        </Badge>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500 sm:text-sm dark:text-slate-400">
                        Tiếp nhận, phân loại, phản hồi và đóng sự cố có kiểm soát — mọi hành động đều để lại dấu vết vận hành.
                    </p>
                </div>
            </div>

            <!-- Quick Header Actions -->
            <div class="flex flex-wrap items-center gap-2.5">
                <div
                    v-if="props.stats.critical > 0 || props.stats.overdue > 0"
                    class="flex items-center gap-2 rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-1.5 text-xs font-semibold text-rose-700 backdrop-blur-xs dark:border-rose-500/30 dark:bg-rose-500/15 dark:text-rose-300"
                >
                    <AlertTriangle class="size-3.5 text-rose-600 dark:text-rose-400" />
                    <span>{{ props.stats.critical }} nghiêm trọng · {{ props.stats.overdue }} quá SLA</span>
                </div>

                <Button
                    @click="showReportForm = !showReportForm"
                    class="h-10 gap-1.5 rounded-xl border border-rose-500/30 bg-rose-600 px-4 text-xs font-semibold text-white shadow-xs hover:bg-rose-700 dark:bg-rose-600 dark:hover:bg-rose-500"
                >
                    <Plus class="size-4" />
                    <span>{{ showReportForm ? 'Đóng biểu mẫu' : 'Báo sự cố' }}</span>
                </Button>
            </div>
        </div>

        <!-- ── Policy / High Priority Alert Banner ───────────────────────── -->
        <div
            v-if="props.stats.critical > 0 || props.stats.overdue > 0"
            class="flex items-start gap-3.5 rounded-2xl border border-rose-500/20 bg-rose-500/5 p-4 text-xs text-slate-700 backdrop-blur-md sm:p-5 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-slate-300"
        >
            <div class="rounded-xl border border-rose-500/20 bg-rose-500/10 p-2 text-rose-600 dark:border-rose-500/30 dark:bg-rose-500/15 dark:text-rose-400">
                <AlertTriangle class="size-5 shrink-0" />
            </div>
            <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2 font-semibold text-slate-900 dark:text-white">
                        <span>Hàng đợi sự cố cần ưu tiên xử lý</span>
                        <Badge variant="outline" class="border-rose-500/30 bg-rose-500/10 text-[10px] text-rose-700 backdrop-blur-xs dark:text-rose-400">
                            Cần xử lý ngay
                        </Badge>
                    </div>
                    <p class="mt-0.5 leading-relaxed text-slate-600 dark:text-slate-400">
                        Đang có sự cố nghiêm trọng hoặc chưa được phản hồi đúng hạn cam kết SLA. Vui lòng tiếp nhận trước khi xử lý các tác vụ thường ngày.
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    @click="
                        activeFilter = 'active';
                        sortMode = 'priority';
                    "
                    class="shrink-0 border-rose-300/60 bg-white/40 text-xs font-semibold text-rose-700 backdrop-blur-xs hover:bg-rose-50 dark:border-rose-800/60 dark:bg-white/[0.04] dark:text-rose-300 dark:hover:bg-rose-950/40"
                >
                    Xem hàng đợi ưu tiên
                </Button>
            </div>
        </div>

        <!-- ── 6 KPI Summary Cards (Translucent Frosted Glass) ────────────── -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            <div
                v-for="kpi in kpis"
                :key="kpi.label"
                class="group rounded-2xl border border-slate-200/60 bg-white/40 p-4 backdrop-blur-xl shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300/80 hover:bg-white/70 dark:border-white/[0.08] dark:bg-white/[0.025] dark:hover:border-white/[0.15] dark:hover:bg-white/[0.05]"
            >
                <div class="flex items-center justify-between gap-2 pb-1.5">
                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        {{ kpi.label }}
                    </span>
                    <div
                        class="flex size-7 items-center justify-center rounded-lg border backdrop-blur-xs"
                        :class="kpi.iconClass"
                    >
                        <component :is="kpi.icon" class="size-3.5" />
                    </div>
                </div>
                <div class="mt-1">
                    <div
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white"
                        :class="kpi.valueClass"
                    >
                        {{ kpi.value }}
                    </div>
                    <p class="mt-1 text-[11px] leading-tight text-slate-500 dark:text-slate-400">
                        {{ kpi.helper }}
                    </p>
                </div>
            </div>
        </div>

        <!-- ── New Incident Form (Collapsible) ────────────────────────────── -->
        <div
            v-if="showReportForm"
            class="rounded-2xl border border-slate-200/70 bg-white/80 p-5 backdrop-blur-xl shadow-lg sm:p-6 dark:border-white/[0.1] dark:bg-slate-900/80"
        >
            <div
                class="mb-5 flex flex-col gap-2 border-b border-slate-200/50 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.08]"
            >
                <div>
                    <div
                        class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white"
                    >
                        <Plus class="size-4 text-rose-600 dark:text-rose-400" />
                        Ghi nhận sự cố mới
                    </div>
                    <p class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                        Ghi nhận ngay cả khi chưa đủ thông tin; quản lý sẽ bổ sung trong quá trình điều tra.
                    </p>
                </div>
                <Badge
                    variant="outline"
                    class="border-rose-500/30 bg-rose-500/10 text-xs font-semibold text-rose-700 backdrop-blur-xs dark:text-rose-400"
                >
                    Bước 1 · Tiếp nhận
                </Badge>
            </div>
            <form @submit.prevent="submitReport" class="flex flex-col gap-5">
                <div class="grid gap-4 lg:grid-cols-4">
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Loại sự cố
                        </Label>
                        <select
                            v-model="reportForm.type"
                            class="h-10 rounded-xl border border-slate-200/70 bg-white/70 px-3 text-xs text-slate-800 shadow-xs backdrop-blur-sm outline-none transition focus:border-rose-500/70 dark:border-white/[0.09] dark:bg-white/[0.04] dark:text-slate-100"
                        >
                            <option value="accident" class="dark:bg-slate-900 dark:text-slate-200">Tai nạn</option>
                            <option value="food_poisoning" class="dark:bg-slate-900 dark:text-slate-200">Ngộ độc thực phẩm</option>
                            <option value="fire" class="dark:bg-slate-900 dark:text-slate-200">Cháy nổ</option>
                            <option value="security" class="dark:bg-slate-900 dark:text-slate-200">An ninh</option>
                            <option value="equipment_failure" class="dark:bg-slate-900 dark:text-slate-200">Hỏng thiết bị</option>
                            <option value="theft" class="dark:bg-slate-900 dark:text-slate-200">Trộm cắp</option>
                            <option value="other" class="dark:bg-slate-900 dark:text-slate-200">Khác</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Mức độ rủi ro
                        </Label>
                        <select
                            v-model="reportForm.severity"
                            class="h-10 rounded-xl border border-slate-200/70 bg-white/70 px-3 text-xs text-slate-800 shadow-xs backdrop-blur-sm outline-none transition focus:border-rose-500/70 dark:border-white/[0.09] dark:bg-white/[0.04] dark:text-slate-100"
                        >
                            <option value="low" class="dark:bg-slate-900 dark:text-slate-200">Thấp · SLA 8 giờ</option>
                            <option value="medium" class="dark:bg-slate-900 dark:text-slate-200">Trung bình · SLA 2 giờ</option>
                            <option value="high" class="dark:bg-slate-900 dark:text-slate-200">Cao · SLA 30 phút</option>
                            <option value="critical" class="dark:bg-slate-900 dark:text-slate-200">Nghiêm trọng · SLA 15 phút</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Thời điểm xảy ra
                        </Label>
                        <Input
                            v-model="reportForm.occurred_at"
                            type="datetime-local"
                            class="h-10 border-slate-200/70 bg-white/70 text-xs shadow-xs backdrop-blur-sm dark:border-white/[0.09] dark:bg-white/[0.04]"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Vị trí
                        </Label>
                        <Input
                            v-model="reportForm.location"
                            placeholder="Bếp, sảnh, kho..."
                            class="h-10 border-slate-200/70 bg-white/70 text-xs placeholder:text-slate-400 shadow-xs backdrop-blur-sm dark:border-white/[0.09] dark:bg-white/[0.04] dark:placeholder:text-slate-500"
                        />
                    </div>
                </div>
                <div class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Tiêu đề
                                <span class="text-rose-500">*</span>
                            </Label>
                            <Input
                                v-model="reportForm.title"
                                required
                                placeholder="Ví dụ: Khách trượt ngã tại khu vực lễ tân"
                                class="h-10 border-slate-200/70 bg-white/70 text-xs placeholder:text-slate-400 shadow-xs backdrop-blur-sm dark:border-white/[0.09] dark:bg-white/[0.04] dark:placeholder:text-slate-500"
                            />
                            <p
                                v-if="reportForm.errors.title"
                                class="text-[11px] font-semibold text-rose-600 dark:text-rose-400"
                            >
                                {{ reportForm.errors.title }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Diễn biến sự việc
                                <span class="text-rose-500">*</span>
                            </Label>
                            <textarea
                                v-model="reportForm.description"
                                required
                                rows="3"
                                placeholder="Chi tiết diễn biến, người phát hiện, những ai liên quan..."
                                class="w-full resize-none rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2.5 text-xs text-slate-800 shadow-xs backdrop-blur-sm outline-none placeholder:text-slate-400 focus:border-rose-500/70 dark:border-white/[0.09] dark:bg-white/[0.04] dark:text-slate-100 dark:placeholder:text-slate-500"
                            ></textarea>
                            <p
                                v-if="reportForm.errors.description"
                                class="text-[11px] font-semibold text-rose-600 dark:text-rose-400"
                            >
                                {{ reportForm.errors.description }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-4">
                        <div
                            class="rounded-xl border border-slate-200/60 bg-slate-50/70 p-3.5 backdrop-blur-xs dark:border-white/[0.08] dark:bg-white/[0.02]"
                        >
                            <div class="flex items-center gap-2 text-xs font-semibold text-slate-800 dark:text-slate-200">
                                <component :is="activeGuidance.icon" class="size-4 text-rose-600 dark:text-rose-400" />
                                {{ activeGuidance.title }}
                            </div>
                            <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                {{ activeGuidance.text }}
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Người bị thương
                                </Label>
                                <Input
                                    v-model="reportForm.injured_count"
                                    type="number"
                                    min="0"
                                    class="h-10 border-slate-200/70 bg-white/70 text-xs shadow-xs backdrop-blur-sm dark:border-white/[0.09] dark:bg-white/[0.04]"
                                />
                            </div>
                            <label
                                class="flex cursor-pointer items-end gap-2 pb-2.5 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <input
                                    v-model="reportForm.needs_shift_cover"
                                    type="checkbox"
                                    class="size-4 rounded border-slate-300 text-rose-600 dark:border-slate-600 dark:bg-slate-800"
                                />
                                Cần thay ca gấp
                            </label>
                        </div>
                    </div>
                </div>
                <div class="grid gap-4 lg:grid-cols-[1fr_1fr_0.8fr]">
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Xử lý ngay tại chỗ
                        </Label>
                        <textarea
                            v-model="reportForm.immediate_action"
                            rows="3"
                            placeholder="Đã sơ cứu, ngắt điện, gọi 115/114, cô lập khu vực..."
                            class="w-full resize-none rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2 text-xs text-slate-800 shadow-xs backdrop-blur-sm outline-none placeholder:text-slate-400 focus:border-rose-500/70 dark:border-white/[0.09] dark:bg-white/[0.04] dark:text-slate-100 dark:placeholder:text-slate-500"
                        ></textarea>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Ảnh hiện trường
                        </Label>
                        <label
                            class="flex min-h-[76px] cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200/70 bg-slate-50/60 px-3 text-xs font-medium text-slate-600 backdrop-blur-xs transition hover:border-slate-400 hover:text-slate-900 dark:border-white/[0.09] dark:bg-white/[0.02] dark:text-slate-400 dark:hover:text-slate-200"
                        >
                            <Camera class="size-4 text-slate-400" />
                            <span>{{ reportForm.photo?.name || 'Chọn ảnh bằng chứng' }}</span>
                            <input
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @input="
                                    reportForm.photo =
                                        ($event.target as HTMLInputElement)
                                            .files?.[0] ?? null
                                "
                            />
                        </label>
                    </div>
                    <div class="flex flex-col justify-end gap-2 sm:flex-row lg:flex-col">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showReportForm = false"
                            class="h-10 rounded-xl border-slate-200/70 bg-white/50 text-xs font-semibold text-slate-700 backdrop-blur-xs hover:bg-slate-100 dark:border-white/[0.08] dark:bg-white/[0.03] dark:text-slate-300 dark:hover:bg-white/[0.08]"
                        >
                            Hủy
                        </Button>
                        <Button
                            type="submit"
                            :disabled="reportForm.processing"
                            class="h-10 rounded-xl border border-rose-500/30 bg-rose-600 text-xs font-semibold text-white shadow-xs hover:bg-rose-700 dark:bg-rose-600 dark:hover:bg-rose-500"
                        >
                            Ghi nhận sự cố
                        </Button>
                    </div>
                </div>
                <div
                    class="flex items-start gap-2 rounded-xl border border-amber-500/20 bg-amber-500/10 p-3 text-[11px] leading-relaxed text-amber-800 backdrop-blur-xs dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-300"
                >
                    <Info class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                    Cháy nổ, ngộ độc, tai nạn, mức Cao/Nghiêm trọng hoặc có người bị thương sẽ tự động báo Chủ nhà hàng.
                </div>
            </form>
        </div>

        <!-- ── Main Workspace Layout ──────────────────────────────────────── -->
        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <!-- Left Column: Sổ điều phối sự cố -->
            <div
                class="min-w-0 rounded-2xl border border-slate-200/60 bg-white/40 p-5 backdrop-blur-xl shadow-xs sm:p-6 dark:border-white/[0.08] dark:bg-white/[0.025]"
            >
                <!-- Title & Filter Toolbar -->
                <div class="flex flex-col gap-4 border-b border-slate-200/40 pb-4 dark:border-white/[0.06]">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white">
                                <ClipboardCheck class="size-5 text-rose-500 dark:text-rose-400" />
                                <span>Sổ điều phối sự cố</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ filtered.length }} kết quả · sắp xếp theo mức độ rủi ro và cam kết SLA
                            </p>
                        </div>
                        <div
                            class="flex rounded-xl border border-slate-200/60 bg-slate-100/50 p-1 backdrop-blur-sm dark:border-white/[0.08] dark:bg-white/[0.03]"
                        >
                            <button
                                v-for="tab in [
                                    { key: 'active', label: 'Đang xử lý' },
                                    { key: 'resolved', label: 'Đã đóng' },
                                    { key: 'all', label: 'Tất cả' },
                                ]"
                                :key="tab.key"
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                                :class="
                                    activeFilter === tab.key
                                        ? 'border border-slate-200/60 bg-white text-slate-900 shadow-xs dark:border-white/[0.1] dark:bg-white/[0.1] dark:text-white'
                                        : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
                                "
                                @click="
                                    activeFilter = tab.key as
                                        | 'active'
                                        | 'resolved'
                                        | 'all'
                                "
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Search & Select Filters -->
                    <div class="grid gap-2.5 md:grid-cols-[1fr_auto_auto_auto]">
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="searchQuery"
                                placeholder="Tìm theo mã, tiêu đề, vị trí, người báo..."
                                class="h-10 w-full rounded-xl border border-slate-200/60 bg-white/60 pl-9 pr-3 text-xs text-slate-900 placeholder:text-slate-400 shadow-xs backdrop-blur-sm outline-none transition focus:border-rose-500/60 dark:border-white/[0.08] dark:bg-white/[0.035] dark:text-white dark:placeholder:text-slate-500 dark:focus:border-rose-400/60"
                            />
                        </div>
                        <select
                            v-model="severityFilter"
                            class="h-10 rounded-xl border border-slate-200/60 bg-white/60 px-3 text-xs font-medium text-slate-800 shadow-xs backdrop-blur-sm outline-none transition focus:border-rose-500/60 dark:border-white/[0.08] dark:bg-white/[0.035] dark:text-slate-200 dark:focus:border-rose-400/60"
                        >
                            <option value="all" class="dark:bg-slate-900 dark:text-slate-200">Mọi mức độ</option>
                            <option value="critical" class="dark:bg-slate-900 dark:text-slate-200">Nghiêm trọng</option>
                            <option value="high" class="dark:bg-slate-900 dark:text-slate-200">Cao</option>
                            <option value="medium" class="dark:bg-slate-900 dark:text-slate-200">Trung bình</option>
                            <option value="low" class="dark:bg-slate-900 dark:text-slate-200">Thấp</option>
                        </select>
                        <select
                            v-model="typeFilter"
                            class="h-10 rounded-xl border border-slate-200/60 bg-white/60 px-3 text-xs font-medium text-slate-800 shadow-xs backdrop-blur-sm outline-none transition focus:border-rose-500/60 dark:border-white/[0.08] dark:bg-white/[0.035] dark:text-slate-200 dark:focus:border-rose-400/60"
                        >
                            <option value="all" class="dark:bg-slate-900 dark:text-slate-200">Mọi loại sự cố</option>
                            <option
                                v-for="(config, key) in typeConfig"
                                :key="key"
                                :value="key"
                                class="dark:bg-slate-900 dark:text-slate-200"
                            >
                                {{ config.label }}
                            </option>
                        </select>
                        <select
                            v-model="sortMode"
                            class="h-10 rounded-xl border border-slate-200/60 bg-white/60 px-3 text-xs font-medium text-slate-800 shadow-xs backdrop-blur-sm outline-none transition focus:border-rose-500/60 dark:border-white/[0.08] dark:bg-white/[0.035] dark:text-slate-200 dark:focus:border-rose-400/60"
                        >
                            <option value="priority" class="dark:bg-slate-900 dark:text-slate-200">Ưu tiên xử lý</option>
                            <option value="recent" class="dark:bg-slate-900 dark:text-slate-200">Mới nhất</option>
                        </select>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="filtered.length === 0"
                    class="my-8 flex min-h-[280px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200/70 bg-slate-50/30 p-8 text-center backdrop-blur-xs dark:border-white/[0.08] dark:bg-white/[0.015]"
                >
                    <div
                        class="flex size-12 items-center justify-center rounded-2xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-500 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-400"
                    >
                        <ShieldCheck class="size-6" />
                    </div>
                    <h2 class="mt-3 text-sm font-bold text-slate-900 dark:text-white">
                        Không có sự cố trong nhóm này
                    </h2>
                    <p class="mt-1 max-w-sm text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        Hàng đợi đang sạch hoặc bộ lọc hiện tại không có kết quả phù hợp.
                    </p>
                    <Button
                        @click="
                            showReportForm = true;
                            activeFilter = 'all';
                            searchQuery = '';
                            severityFilter = 'all';
                            typeFilter = 'all';
                        "
                        class="mt-4 h-9 gap-1.5 rounded-xl border border-rose-500/30 bg-rose-600 px-4 text-xs font-semibold text-white shadow-xs hover:bg-rose-700 dark:bg-rose-600 dark:hover:bg-rose-500"
                    >
                        <Plus class="size-3.5" />
                        <span>Báo sự cố mới</span>
                    </Button>
                </div>

                <!-- Incident List -->
                <div v-else class="mt-4 flex flex-col gap-3">
                    <article
                        v-for="incident in filtered"
                        :key="incident.id"
                        class="relative overflow-hidden rounded-xl border border-slate-200/60 bg-white/60 backdrop-blur-sm transition-all duration-200 hover:border-slate-300/80 hover:bg-white/80 hover:shadow-xs dark:border-white/[0.07] dark:bg-white/[0.02] dark:hover:border-white/[0.14] dark:hover:bg-white/[0.04]"
                    >
                        <!-- Severity rail indicator -->
                        <div
                            class="absolute inset-y-0 left-0 w-1"
                            :class="severityConfig[incident.severity]?.railClass"
                        />
                        <div class="p-4 pl-5 sm:p-5 sm:pl-6">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="flex min-w-0 items-start gap-3">
                                    <div
                                        class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-slate-200/60 bg-slate-100/70 text-slate-700 backdrop-blur-xs dark:border-white/[0.08] dark:bg-white/[0.04] dark:text-slate-300"
                                    >
                                        <component
                                            :is="typeConfig[incident.type]?.icon ?? ShieldAlert"
                                            class="size-5"
                                            :class="typeConfig[incident.type]?.iconClass"
                                        />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="mb-1.5 flex flex-wrap items-center gap-1.5">
                                            <span class="font-mono text-[10px] font-bold text-slate-500">
                                                {{ incident.code }}
                                            </span>
                                            <span class="flex items-center gap-1 text-[10px] font-semibold text-slate-600 dark:text-slate-400">
                                                <span
                                                    class="size-1.5 rounded-full"
                                                    :class="typeConfig[incident.type]?.dotClass"
                                                />
                                                {{ typeConfig[incident.type]?.label }}
                                            </span>
                                            <span
                                                class="rounded-md border px-1.5 py-0.5 text-[9px] font-semibold"
                                                :class="severityConfig[incident.severity]?.badgeClass"
                                            >
                                                {{ severityConfig[incident.severity]?.label }}
                                            </span>
                                            <span
                                                v-if="incident.injured_count > 0"
                                                class="rounded-md border border-rose-500/20 bg-rose-500/10 px-1.5 py-0.5 text-[9px] font-semibold text-rose-700 backdrop-blur-xs dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300"
                                            >
                                                {{ incident.injured_count }} người bị thương
                                            </span>
                                            <span
                                                v-if="incident.needs_shift_cover"
                                                class="rounded-md border border-sky-500/20 bg-sky-500/10 px-1.5 py-0.5 text-[9px] font-semibold text-sky-700 backdrop-blur-xs dark:border-sky-400/20 dark:bg-sky-400/10 dark:text-sky-300"
                                            >
                                                Cần thay ca
                                            </span>
                                        </div>
                                        <h3 class="truncate text-sm font-bold text-slate-900 sm:text-base dark:text-white">
                                            {{ incident.title }}
                                        </h3>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                                            {{ incident.description }}
                                        </p>
                                        <div class="mt-2.5 flex flex-wrap gap-x-3.5 gap-y-1 text-[11px] text-slate-500 dark:text-slate-400">
                                            <span v-if="incident.location" class="flex items-center gap-1">
                                                <MapPin class="size-3 text-slate-400" />
                                                {{ incident.location }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <Clock3 class="size-3 text-slate-400" />
                                                {{ incident.occurred_at_display }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <UserRound class="size-3 text-slate-400" />
                                                {{ incident.reported_by_name }}
                                            </span>
                                            <span v-if="incident.branch_name" class="font-medium text-slate-600 dark:text-slate-300">
                                                {{ incident.branch_name }}
                                            </span>
                                            <a
                                                v-if="incident.photo_url"
                                                :href="incident.photo_url"
                                                target="_blank"
                                                class="flex items-center gap-1 font-semibold text-sky-600 hover:underline dark:text-sky-400"
                                            >
                                                <Camera class="size-3" />
                                                Ảnh bằng chứng
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-2 lg:flex-col lg:items-end">
                                    <span
                                        class="rounded-lg border px-2 py-0.5 text-[10px] font-semibold"
                                        :class="statusConfig[incident.status].class"
                                    >
                                        {{ statusConfig[incident.status].label }}
                                    </span>
                                    <span
                                        v-if="incident.escalated"
                                        class="flex items-center gap-1 rounded-md border border-rose-500/20 bg-rose-500/10 px-2 py-0.5 text-[10px] font-semibold text-rose-700 backdrop-blur-xs dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300"
                                    >
                                        <ArrowUpCircle class="size-3" />
                                        Đã báo Chủ
                                    </span>
                                </div>
                            </div>

                            <!-- SLA info bar -->
                            <div class="mt-4 grid gap-2 border-y border-slate-200/40 py-2.5 sm:grid-cols-3 dark:border-white/[0.06]">
                                <div class="flex items-center gap-2">
                                    <TimerReset
                                        class="size-4"
                                        :class="
                                            incident.sla_state === 'overdue' ||
                                            incident.sla_state === 'breached'
                                                ? 'text-rose-600 dark:text-rose-400'
                                                : 'text-sky-600 dark:text-sky-400'
                                        "
                                    />
                                    <div>
                                        <div class="text-[9px] font-semibold tracking-wider text-slate-400 uppercase">
                                            Phản hồi
                                        </div>
                                        <div
                                            class="text-xs font-semibold"
                                            :class="
                                                incident.sla_state === 'overdue' ||
                                                incident.sla_state === 'breached'
                                                    ? 'text-rose-600 dark:text-rose-400'
                                                    : 'text-slate-800 dark:text-slate-200'
                                            "
                                        >
                                            {{
                                                incident.response_time_minutes !== null
                                                    ? formatMinutes(incident.response_time_minutes)
                                                    : 'Chưa tiếp nhận'
                                            }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Clock3 class="size-4 text-slate-400" />
                                    <div>
                                        <div class="text-[9px] font-semibold tracking-wider text-slate-400 uppercase">
                                            Hạn phản hồi
                                        </div>
                                        <div class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                            {{ incident.response_due_at_display || '—' }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-start gap-2 sm:justify-end">
                                    <span
                                        class="rounded-lg border px-2 py-0.5 text-[10px] font-semibold"
                                        :class="slaClass(incident.sla_state)"
                                    >
                                        {{ slaLabel(incident.sla_state) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Actions & Toggle -->
                            <div class="flex flex-wrap items-center justify-between gap-2 pt-3">
                                <button
                                    type="button"
                                    class="flex items-center gap-1.5 text-xs font-semibold text-slate-600 transition hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                                    @click="toggleExpanded(incident)"
                                >
                                    <ChevronDown
                                        class="size-3.5 transition"
                                        :class="expandedId === incident.id ? 'rotate-180' : ''"
                                    />
                                    {{ expandedId === incident.id ? 'Thu gọn' : 'Xem chi tiết & nhật ký' }}
                                </button>
                                <div
                                    v-if="props.canManage && incident.status !== 'resolved'"
                                    class="flex flex-wrap gap-2"
                                >
                                    <Button
                                        v-if="incident.status === 'open'"
                                        size="sm"
                                        variant="outline"
                                        @click="doAcknowledge(incident)"
                                        class="h-8 gap-1.5 rounded-lg border-slate-200/60 bg-white/50 text-xs font-semibold text-slate-700 backdrop-blur-xs hover:bg-slate-100 dark:border-white/[0.08] dark:bg-white/[0.03] dark:text-slate-300 dark:hover:bg-white/[0.08]"
                                    >
                                        <ClipboardCheck class="size-3.5 text-blue-600 dark:text-blue-400" />
                                        <span>Tiếp nhận</span>
                                    </Button>
                                    <Button
                                        v-if="!incident.escalated"
                                        size="sm"
                                        variant="outline"
                                        @click="doEscalate(incident)"
                                        class="h-8 gap-1.5 rounded-lg border-rose-500/20 bg-rose-500/10 text-xs font-semibold text-rose-700 backdrop-blur-xs hover:bg-rose-500/20 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300 dark:hover:bg-rose-400/20"
                                    >
                                        <ArrowUpCircle class="size-3.5 text-rose-600 dark:text-rose-400" />
                                        <span>Báo Chủ</span>
                                    </Button>
                                    <Button
                                        size="sm"
                                        @click="openResolve(incident)"
                                        class="h-8 gap-1.5 rounded-lg border border-emerald-500/30 bg-emerald-600 text-xs font-semibold text-white shadow-xs hover:bg-emerald-500"
                                    >
                                        <CheckCircle2 class="size-3.5" />
                                        <span>Đóng sự cố</span>
                                    </Button>
                                </div>
                                <div
                                    v-else-if="!props.canManage && incident.status !== 'resolved'"
                                    class="flex items-center gap-1.5 text-xs text-slate-500"
                                >
                                    <Lock class="size-3.5" />
                                    <span>Chỉ quản lý/Chủ được xử lý</span>
                                </div>
                            </div>

                            <!-- Expanded Drawer Details -->
                            <div
                                v-if="expandedId === incident.id"
                                class="mt-4 grid gap-4 border-t border-slate-200/40 pt-4 lg:grid-cols-[1fr_1fr] dark:border-white/[0.06]"
                            >
                                <div class="space-y-3">
                                    <div>
                                        <div class="mb-1 text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                                            Xử lý ngay tại chỗ
                                        </div>
                                        <p class="text-xs leading-relaxed text-slate-700 dark:text-slate-300">
                                            {{ incident.immediate_action || 'Chưa ghi nhận hành động ban đầu.' }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="incident.status === 'resolved' && incident.resolution_report"
                                    >
                                        <div class="mb-1 flex items-center gap-1.5 text-[10px] font-semibold tracking-wider text-emerald-700 uppercase dark:text-emerald-400">
                                            <FileText class="size-3.5" />
                                            Báo cáo đóng sự cố
                                        </div>
                                        <p class="text-xs leading-relaxed text-slate-700 dark:text-slate-300">
                                            {{ incident.resolution_report }}
                                        </p>
                                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                            {{ incident.resolved_by_name }} · {{ incident.resolved_at_display }} · Thời gian xử lý {{ formatMinutes(incident.resolution_time_minutes) }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="rounded-xl border border-slate-200/60 bg-slate-50/60 p-3.5 backdrop-blur-xs dark:border-white/[0.08] dark:bg-white/[0.02]"
                                >
                                    <div class="mb-2.5 text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                                        Nhật ký trạng thái
                                    </div>
                                    <div class="grid grid-cols-1 gap-2.5 text-xs">
                                        <div class="flex items-start gap-2">
                                            <span class="mt-1 size-2 rounded-full bg-blue-500" />
                                            <div>
                                                <div class="font-medium text-slate-900 dark:text-slate-200">
                                                    Đã báo · {{ incident.occurred_at_display }}
                                                </div>
                                                <div class="text-[11px] text-slate-500">
                                                    Người báo: {{ incident.reported_by_name }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-2">
                                            <span
                                                class="mt-1 size-2 rounded-full"
                                                :class="incident.acknowledged_at_display ? 'bg-amber-500' : 'bg-slate-300 dark:bg-slate-700'"
                                            />
                                            <div>
                                                <div class="font-medium text-slate-900 dark:text-slate-200">
                                                    {{ incident.acknowledged_at_display ? `Đã tiếp nhận · ${incident.acknowledged_at_display}` : 'Chưa tiếp nhận' }}
                                                </div>
                                                <div class="text-[11px] text-slate-500">
                                                    {{ incident.acknowledged_by_name || 'Đang chờ quản lý' }}
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="incident.escalated" class="flex items-start gap-2">
                                            <span class="mt-1 size-2 rounded-full bg-rose-500" />
                                            <div>
                                                <div class="font-medium text-slate-900 dark:text-slate-200">
                                                    Đã báo Chủ · {{ incident.escalated_at_display || 'Đã ghi nhận' }}
                                                </div>
                                                <div class="text-[11px] text-slate-500">
                                                    {{ incident.escalated_to_name || 'Chủ nhà hàng' }}
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="incident.status === 'resolved'" class="flex items-start gap-2">
                                            <span class="mt-1 size-2 rounded-full bg-emerald-500" />
                                            <div>
                                                <div class="font-medium text-slate-900 dark:text-slate-200">
                                                    Đã đóng · {{ incident.resolved_at_display }}
                                                </div>
                                                <div class="text-[11px] text-slate-500">
                                                    Người đóng: {{ incident.resolved_by_name }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <!-- Right Column: Sidebar Widgets -->
            <aside class="flex flex-col gap-5">
                <!-- Widget 1: Priority Queue -->
                <div
                    class="rounded-2xl border border-slate-200/60 bg-white/40 p-5 backdrop-blur-xl shadow-xs dark:border-white/[0.08] dark:bg-white/[0.025]"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                                <AlertTriangle class="size-4 text-rose-600 dark:text-rose-400" />
                                <span>Cần hành động</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                Ưu tiên theo SLA và mức độ
                            </p>
                        </div>
                        <Badge variant="secondary" class="border border-slate-200/60 bg-white/60 font-semibold backdrop-blur-xs dark:border-white/[0.08] dark:bg-white/[0.05]">
                            {{ priorityQueue.length }}
                        </Badge>
                    </div>
                    <div v-if="priorityQueue.length" class="mt-3.5 space-y-2">
                        <button
                            v-for="incident in priorityQueue"
                            :key="incident.id"
                            type="button"
                            class="group flex w-full items-start gap-3 rounded-xl border border-slate-200/60 bg-white/50 p-3 text-left backdrop-blur-xs transition hover:border-slate-300/80 hover:bg-white/80 dark:border-white/[0.06] dark:bg-white/[0.02] dark:hover:border-white/[0.12] dark:hover:bg-white/[0.05]"
                            @click="
                                activeFilter = 'active';
                                searchQuery = incident.code;
                                expandedId = incident.id;
                            "
                        >
                            <span
                                class="mt-1.5 size-2 shrink-0 rounded-full"
                                :class="severityConfig[incident.severity]?.railClass"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-xs font-bold text-slate-800 group-hover:text-slate-900 dark:text-slate-200 dark:group-hover:text-white">
                                    {{ incident.title }}
                                </span>
                                <span class="mt-0.5 block text-[11px] text-slate-500">
                                    {{ incident.code }} · {{ actionSummary(incident) }}
                                </span>
                            </span>
                            <ArrowUpCircle
                                v-if="incident.escalated"
                                class="size-3.5 shrink-0 text-rose-600 dark:text-rose-400"
                            />
                        </button>
                    </div>
                    <div
                        v-else
                        class="mt-3.5 rounded-xl border border-dashed border-slate-200/70 bg-slate-50/30 p-4 text-center text-xs text-slate-500 backdrop-blur-xs dark:border-white/[0.08] dark:bg-transparent dark:text-slate-400"
                    >
                        Không có sự cố cần ưu tiên.
                    </div>
                </div>

                <!-- Widget 2: Standard Reaction Process -->
                <div
                    class="rounded-2xl border border-slate-200/60 bg-white/40 p-5 backdrop-blur-xl shadow-xs dark:border-white/[0.08] dark:bg-white/[0.025]"
                >
                    <div class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                        <ShieldCheck class="size-4 text-emerald-600 dark:text-emerald-400" />
                        <span>Quy trình phản ứng</span>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        Mỗi sự cố đi qua 4 bước bắt buộc
                    </p>
                    <div class="mt-4 space-y-3.5">
                        <div
                            v-for="(step, index) in [
                                {
                                    title: 'Tiếp nhận',
                                    text: 'Ghi nhận sự thật, vị trí và bằng chứng.',
                                },
                                {
                                    title: 'Phân loại',
                                    text: 'Xác định mức độ và hạn phản hồi SLA.',
                                },
                                {
                                    title: 'Điều phối',
                                    text: 'Quản lý tiếp nhận, báo Chủ khi cần.',
                                },
                                {
                                    title: 'Đóng & học lại',
                                    text: 'Bắt buộc báo cáo nguyên nhân và phòng ngừa.',
                                },
                            ]"
                            :key="step.title"
                            class="flex gap-3"
                        >
                            <div
                                class="flex size-7 shrink-0 items-center justify-center rounded-lg border border-slate-200/60 bg-slate-100/60 text-[10px] font-bold text-slate-700 backdrop-blur-xs dark:border-white/[0.08] dark:bg-white/[0.04] dark:text-slate-300"
                            >
                                0{{ index + 1 }}
                            </div>
                            <div>
                                <div class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                    {{ step.title }}
                                </div>
                                <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                                    {{ step.text }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Widget 3: Safety Principle Banner -->
                <div
                    class="rounded-2xl border border-slate-200/60 bg-slate-50/50 p-4 text-xs backdrop-blur-md dark:border-white/[0.08] dark:bg-white/[0.02]"
                >
                    <div class="flex items-start gap-2.5">
                        <Info class="mt-0.5 size-4 shrink-0 text-slate-500 dark:text-slate-400" />
                        <div>
                            <div class="font-semibold text-slate-800 dark:text-slate-200">
                                Nguyên tắc an toàn
                            </div>
                            <p class="mt-0.5 leading-relaxed text-slate-500 dark:text-slate-400">
                                An toàn con người luôn trước tài sản. Không tự xử lý tình huống vượt quá thẩm quyền; báo quản lý hoặc cơ quan khẩn cấp phù hợp.
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="!props.canManage"
                        class="mt-3 flex items-center gap-1.5 border-t border-slate-200/50 pt-2.5 text-[11px] text-slate-500 dark:border-white/[0.06] dark:text-slate-400"
                    >
                        <Lock class="size-3" />
                        <span>Bạn có thể báo và theo dõi, không thể tự đóng sự cố.</span>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <!-- ── Resolve Modal ──────────────────────────────────────────────── -->
    <Teleport to="body">
        <div
            v-if="showResolveModal && selected"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-xl rounded-2xl border border-slate-200/80 bg-white/95 p-5 backdrop-blur-xl shadow-2xl sm:p-6 dark:border-white/[0.1] dark:bg-slate-900/90"
            >
                <div
                    class="flex items-start justify-between gap-4 border-b border-slate-100/80 pb-4 dark:border-white/[0.08]"
                >
                    <div>
                        <div class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white">
                            <CheckCircle2 class="size-5 text-emerald-600 dark:text-emerald-400" />
                            <span>Đóng sự cố kèm báo cáo</span>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ selected.code }} · {{ selected.title }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-white/[0.08] dark:hover:text-white"
                        @click="showResolveModal = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200/60 bg-slate-50/70 p-3 backdrop-blur-xs dark:border-white/[0.08] dark:bg-white/[0.03]">
                        <div class="text-[9px] font-semibold tracking-wider text-slate-400 uppercase">
                            Mức độ
                        </div>
                        <div class="mt-0.5 text-xs font-bold text-rose-600 dark:text-rose-400">
                            {{ severityConfig[selected.severity]?.label }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200/60 bg-slate-50/70 p-3 backdrop-blur-xs dark:border-white/[0.08] dark:bg-white/[0.03]">
                        <div class="text-[9px] font-semibold tracking-wider text-slate-400 uppercase">
                            Phản hồi
                        </div>
                        <div class="mt-0.5 text-xs font-semibold text-slate-800 dark:text-slate-200">
                            {{ formatMinutes(selected.response_time_minutes) }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200/60 bg-slate-50/70 p-3 backdrop-blur-xs dark:border-white/[0.08] dark:bg-white/[0.03]">
                        <div class="text-[9px] font-semibold tracking-wider text-slate-400 uppercase">
                            Người báo
                        </div>
                        <div class="mt-0.5 truncate text-xs font-semibold text-slate-800 dark:text-slate-200">
                            {{ selected.reported_by_name }}
                        </div>
                    </div>
                </div>
                <form
                    @submit.prevent="submitResolve"
                    class="mt-5 flex flex-col gap-3"
                >
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Báo cáo xử lý
                            <span class="text-rose-500">*</span>
                        </Label>
                        <textarea
                            v-model="resolveForm.resolution_report"
                            rows="5"
                            required
                            minlength="20"
                            placeholder="Nguyên nhân, biện pháp đã thực hiện, kết quả và cách phòng ngừa tái diễn (tối thiểu 20 ký tự)..."
                            class="w-full resize-none rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2.5 text-xs text-slate-800 shadow-xs backdrop-blur-sm outline-none placeholder:text-slate-400 focus:border-emerald-500/70 dark:border-white/[0.09] dark:bg-white/[0.04] dark:text-slate-100 dark:placeholder:text-slate-500"
                        ></textarea>
                        <p
                            v-if="resolveForm.errors.resolution_report"
                            class="text-[11px] font-semibold text-rose-600 dark:text-rose-400"
                        >
                            {{ resolveForm.errors.resolution_report }}
                        </p>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100/80 pt-4 dark:border-white/[0.08]">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showResolveModal = false"
                            class="rounded-xl border-slate-200/70 bg-white/50 text-xs font-semibold text-slate-700 backdrop-blur-xs hover:bg-slate-100 dark:border-white/[0.08] dark:bg-white/[0.03] dark:text-slate-300 dark:hover:bg-white/[0.08]"
                        >
                            Hủy
                        </Button>
                        <Button
                            type="submit"
                            :disabled="resolveForm.processing"
                            class="rounded-xl border border-emerald-500/30 bg-emerald-600 text-xs font-semibold text-white shadow-xs hover:bg-emerald-500"
                        >
                            <CheckCircle2 class="mr-1.5 size-3.5" />
                            Đóng sự cố
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
