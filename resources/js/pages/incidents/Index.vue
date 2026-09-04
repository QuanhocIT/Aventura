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
    cardClass: string;
    iconClass: string;
    valueClass: string;
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
        iconClass: 'text-rose-400',
        dotClass: 'bg-rose-500',
    },
    food_poisoning: {
        label: 'Ngộ độc thực phẩm',
        icon: Siren,
        iconClass: 'text-orange-400',
        dotClass: 'bg-orange-500',
    },
    fire: {
        label: 'Cháy nổ',
        icon: Flame,
        iconClass: 'text-red-400',
        dotClass: 'bg-red-500',
    },
    security: {
        label: 'An ninh',
        icon: ShieldAlert,
        iconClass: 'text-indigo-400',
        dotClass: 'bg-indigo-500',
    },
    equipment_failure: {
        label: 'Hỏng thiết bị',
        icon: Wrench,
        iconClass: 'text-slate-300',
        dotClass: 'bg-slate-500',
    },
    theft: {
        label: 'Trộm cắp',
        icon: ShieldQuestion,
        iconClass: 'text-amber-400',
        dotClass: 'bg-amber-500',
    },
    other: {
        label: 'Khác',
        icon: ShieldQuestion,
        iconClass: 'text-slate-400',
        dotClass: 'bg-slate-500',
    },
};

const severityConfig: Record<
    string,
    { label: string; badgeClass: string; railClass: string; rank: number }
> = {
    low: {
        label: 'Thấp',
        badgeClass: 'border-slate-300 bg-slate-100 text-slate-700 font-bold dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
        railClass: 'bg-slate-500',
        rank: 1,
    },
    medium: {
        label: 'Trung bình',
        badgeClass: 'border-amber-300 bg-amber-50 text-amber-800 font-bold dark:border-amber-900/80 dark:bg-amber-950/50 dark:text-amber-300',
        railClass: 'bg-amber-500',
        rank: 2,
    },
    high: {
        label: 'Cao',
        badgeClass: 'border-orange-300 bg-orange-50 text-orange-800 font-bold dark:border-orange-900/80 dark:bg-orange-950/50 dark:text-orange-300',
        railClass: 'bg-orange-500',
        rank: 3,
    },
    critical: {
        label: 'Nghiêm trọng',
        badgeClass: 'border-rose-300 bg-rose-50 text-rose-800 font-bold dark:border-rose-900/80 dark:bg-rose-950/60 dark:text-rose-300',
        railClass: 'bg-rose-500',
        rank: 4,
    },
};

const statusConfig: Record<IncidentStatus, { label: string; class: string }> = {
    open: {
        label: 'Chờ tiếp nhận',
        class: 'border-blue-300 bg-blue-50 text-blue-800 font-bold dark:border-blue-900/60 dark:bg-blue-950/50 dark:text-blue-300',
    },
    investigating: {
        label: 'Đang xử lý',
        class: 'border-amber-300 bg-amber-50 text-amber-800 font-bold dark:border-amber-900/60 dark:bg-amber-950/50 dark:text-amber-300',
    },
    escalated: {
        label: 'Đã báo Chủ',
        class: 'border-rose-300 bg-rose-50 text-rose-800 font-bold dark:border-rose-900/60 dark:bg-rose-950/50 dark:text-rose-300',
    },
    resolved: {
        label: 'Đã đóng',
        class: 'border-emerald-300 bg-emerald-50 text-emerald-800 font-bold dark:border-emerald-900/60 dark:bg-emerald-950/50 dark:text-emerald-300',
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
        cardClass: 'border-blue-300/80 bg-blue-50/70 shadow-xs shadow-blue-100/50 dark:border-blue-900/40 dark:bg-blue-950/20 dark:shadow-none',
        iconClass: 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
        valueClass: 'text-blue-900 dark:text-blue-300',
    },
    {
        label: 'Chờ tiếp nhận',
        helper: 'Cần quản lý xác nhận',
        value: props.stats.awaiting_ack,
        icon: ClipboardCheck,
        cardClass: 'border-amber-300/80 bg-amber-50/70 shadow-xs shadow-amber-100/50 dark:border-amber-900/40 dark:bg-amber-950/20 dark:shadow-none',
        iconClass: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
        valueClass: 'text-amber-900 dark:text-amber-300',
    },
    {
        label: 'Quá SLA',
        helper: 'Chưa phản hồi đúng hạn',
        value: props.stats.overdue,
        icon: TimerReset,
        cardClass: 'border-rose-300/80 bg-rose-50/70 shadow-xs shadow-rose-100/50 dark:border-rose-900/50 dark:bg-rose-950/20 dark:shadow-none',
        iconClass: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        valueClass: 'text-rose-900 dark:text-rose-300',
    },
    {
        label: 'Đã báo Chủ',
        helper: 'Đang ở cấp khẩn cấp',
        value: props.stats.escalated,
        icon: ArrowUpCircle,
        cardClass: 'border-fuchsia-300/80 bg-fuchsia-50/70 shadow-xs shadow-fuchsia-100/50 dark:border-fuchsia-900/40 dark:bg-fuchsia-950/20 dark:shadow-none',
        iconClass: 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/15 dark:text-fuchsia-300',
        valueClass: 'text-fuchsia-900 dark:text-fuchsia-300',
    },
    {
        label: 'Cần thay ca',
        helper: 'Nhân sự chưa thể tiếp tục',
        value: props.stats.needs_shift_cover,
        icon: Users,
        cardClass: 'border-cyan-300/80 bg-cyan-50/70 shadow-xs shadow-cyan-100/50 dark:border-cyan-900/40 dark:bg-cyan-950/20 dark:shadow-none',
        iconClass: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-500/15 dark:text-cyan-300',
        valueClass: 'text-cyan-900 dark:text-cyan-300',
    },
    {
        label: 'Đã đóng',
        helper: `${props.stats.last_24h} phát sinh trong 24 giờ`,
        value: props.stats.resolved,
        icon: ShieldCheck,
        cardClass: 'border-emerald-300/80 bg-emerald-50/70 shadow-xs shadow-emerald-100/50 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:shadow-none',
        iconClass: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        valueClass: 'text-emerald-900 dark:text-emerald-300',
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

        return (
            matchesTab &&
            matchesSeverity &&
            matchesType &&
            (!query || haystack.includes(query))
        );
    });

    return result.sort((a, b) => {
        if (sortMode.value === 'recent') {
            return b.id - a.id;
        }

        const severityGap =
            (severityConfig[b.severity]?.rank ?? 0) -
            (severityConfig[a.severity]?.rank ?? 0);

        if (severityGap !== 0) {
            return severityGap;
        }

        if (a.sla_state === 'overdue' && b.sla_state !== 'overdue') {
            return -1;
        }

        if (b.sla_state === 'overdue' && a.sla_state !== 'overdue') {
            return 1;
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
        ? 'border-rose-300 bg-rose-50 text-rose-800 font-bold dark:border-rose-900/60 dark:bg-rose-950/50 dark:text-rose-300'
        : state === 'met' || state === 'acknowledged'
          ? 'border-emerald-300 bg-emerald-50 text-emerald-800 font-bold dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300'
          : 'border-sky-300 bg-sky-50 text-sky-800 font-bold dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-300';
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

    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-5 p-4 sm:p-6 lg:p-8"
    >
        <section
            class="relative overflow-hidden rounded-[28px] border border-rose-200/90 bg-gradient-to-r from-rose-50/90 via-slate-50/80 to-white p-5 text-slate-800 shadow-sm shadow-rose-100/50 sm:p-7 dark:border-rose-900/50 dark:bg-gradient-to-br dark:from-rose-950 dark:via-slate-950 dark:to-slate-950 dark:text-white dark:shadow-xl"
        >
            <div
                class="pointer-events-none absolute -top-24 right-0 size-64 rounded-full bg-rose-500/10 blur-3xl"
            />
            <div
                class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex size-14 shrink-0 items-center justify-center rounded-2xl border border-rose-200 bg-rose-100/80 text-rose-700 shadow-xs dark:border-rose-700/70 dark:bg-rose-500/15 dark:text-rose-300 dark:shadow-lg dark:shadow-rose-950/30"
                    >
                        <Siren class="size-7" />
                    </div>
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full border border-rose-200 bg-rose-100/70 px-2.5 py-1 text-[10px] font-bold tracking-[0.16em] text-rose-800 uppercase dark:border-rose-800/70 dark:bg-rose-950/60 dark:text-rose-300"
                                >Safety Operations</span
                            ><span
                                class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                                ><MapPin class="size-3.5 text-rose-600 dark:text-rose-400" />{{
                                    props.activeBranchName || 'Toàn nhà hàng'
                                }}</span
                            >
                        </div>
                        <h1
                            class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl dark:text-white"
                        >
                            Trung tâm Điều phối Sự cố
                        </h1>
                        <p
                            class="mt-2 max-w-2xl text-sm leading-relaxed font-medium text-slate-600 dark:text-slate-300"
                        >
                            Tiếp nhận, phân loại, phản hồi và đóng sự cố có kiểm
                            soát — mọi hành động đều để lại dấu vết vận hành.
                        </p>
                    </div>
                </div>
                <div
                    class="flex shrink-0 flex-col gap-3 sm:flex-row sm:items-center"
                >
                    <div
                        class="rounded-2xl border border-rose-200/80 bg-white/90 px-4 py-3 shadow-xs dark:border-white/10 dark:bg-white/5"
                    >
                        <div
                            class="flex items-center gap-2 text-[10px] font-black tracking-wider text-rose-700 uppercase dark:text-rose-300"
                        >
                            <AlertTriangle class="size-3.5" /> Ưu tiên hôm nay
                        </div>
                        <div class="mt-1 text-sm font-bold text-slate-900 dark:text-white">
                            {{ props.stats.critical }} nghiêm trọng ·
                            {{ props.stats.overdue }} quá SLA
                        </div>
                    </div>
                    <Button
                        @click="showReportForm = !showReportForm"
                        class="h-11 gap-2 rounded-xl border-0 bg-rose-600 px-5 font-bold text-white shadow-sm hover:bg-rose-700 dark:bg-rose-500 dark:shadow-lg dark:shadow-rose-950/40 dark:hover:bg-rose-400"
                        ><Plus class="size-4" />{{
                            showReportForm ? 'Đóng biểu mẫu' : 'Báo sự cố'
                        }}</Button
                    >
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <div
                v-for="kpi in kpis"
                :key="kpi.label"
                class="rounded-2xl border p-4 transition hover:-translate-y-0.5 shadow-2xs"
                :class="kpi.cardClass"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div
                            class="text-2xl font-black"
                            :class="kpi.valueClass"
                        >
                            {{ kpi.value }}
                        </div>
                        <div
                            class="mt-1 text-xs font-black text-slate-800 dark:text-slate-200"
                        >
                            {{ kpi.label }}
                        </div>
                        <div
                            class="mt-1 text-[10px] leading-relaxed font-semibold text-slate-600 dark:text-slate-400"
                        >
                            {{ kpi.helper }}
                        </div>
                    </div>
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl"
                        :class="kpi.iconClass"
                    >
                        <component :is="kpi.icon" class="size-4" />
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="props.stats.critical > 0 || props.stats.overdue > 0"
            class="flex flex-col gap-3 rounded-2xl border border-rose-300/80 bg-rose-50/80 p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between dark:border-rose-900/60 dark:bg-rose-950/25"
        >
            <div class="flex items-start gap-3">
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-rose-200/80 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300"
                >
                    <Zap class="size-4" />
                </div>
                <div>
                    <div
                        class="text-sm font-bold text-rose-950 dark:text-rose-200"
                    >
                        Hàng đợi cần ưu tiên
                    </div>
                    <p
                        class="mt-1 text-xs leading-relaxed font-medium text-rose-800 dark:text-rose-200/70"
                    >
                        Có sự cố nghiêm trọng hoặc chưa được phản hồi đúng SLA.
                        Hãy tiếp nhận trước khi xử lý tác vụ thường ngày.
                    </p>
                </div>
            </div>
            <button
                type="button"
                class="rounded-lg border border-rose-300 bg-white px-3 py-2 text-xs font-bold text-rose-800 shadow-2xs transition hover:bg-rose-100 dark:border-rose-800/70 dark:bg-transparent dark:text-rose-200"
                @click="
                    activeFilter = 'active';
                    sortMode = 'priority';
                "
            >
                Xem hàng đợi ưu tiên
            </button>
        </section>

        <section
            v-if="showReportForm"
            class="rounded-3xl border border-slate-200/90 bg-white p-5 shadow-xl sm:p-6 dark:border-rose-900/50 dark:bg-slate-950"
        >
            <div
                class="mb-5 flex flex-col gap-2 border-b border-slate-200/90 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10"
            >
                <div>
                    <div
                        class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white"
                    >
                        <Plus class="size-4 text-rose-600 dark:text-rose-400" />
                        Ghi nhận sự cố mới
                    </div>
                    <p class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                        Ghi nhận ngay cả khi chưa đủ thông tin; quản lý sẽ bổ
                        sung trong quá trình điều tra.
                    </p>
                </div>
                <span
                    class="rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-[10px] font-bold tracking-wider text-rose-700 uppercase dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300"
                    >Bước 1 · Tiếp nhận</span
                >
            </div>
            <form @submit.prevent="submitReport" class="flex flex-col gap-5">
                <div class="grid gap-4 lg:grid-cols-4">
                    <div class="flex flex-col gap-1.5">
                        <Label
                            class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                            >Loại sự cố</Label
                        ><select
                            v-model="reportForm.type"
                            class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-800 shadow-xs outline-none focus:border-rose-500 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                        >
                            <option value="accident">Tai nạn</option>
                            <option value="food_poisoning">
                                Ngộ độc thực phẩm
                            </option>
                            <option value="fire">Cháy nổ</option>
                            <option value="security">An ninh</option>
                            <option value="equipment_failure">
                                Hỏng thiết bị
                            </option>
                            <option value="theft">Trộm cắp</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label
                            class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                            >Mức độ rủi ro</Label
                        ><select
                            v-model="reportForm.severity"
                            class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-800 shadow-xs outline-none focus:border-rose-500 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                        >
                            <option value="low">Thấp · SLA 8 giờ</option>
                            <option value="medium">
                                Trung bình · SLA 2 giờ
                            </option>
                            <option value="high">Cao · SLA 30 phút</option>
                            <option value="critical">
                                Nghiêm trọng · SLA 15 phút
                            </option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label
                            class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                            >Thời điểm xảy ra</Label
                        ><Input
                            v-model="reportForm.occurred_at"
                            type="datetime-local"
                            class="h-10 border-slate-300 bg-white text-slate-800 shadow-xs dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label
                            class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                            >Vị trí</Label
                        ><Input
                            v-model="reportForm.location"
                            placeholder="Bếp, sảnh, kho..."
                            class="h-10 border-slate-300 bg-white text-slate-800 placeholder:text-slate-400 shadow-xs dark:border-white/10 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-600"
                        />
                    </div>
                </div>
                <div class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label
                                class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                                >Tiêu đề
                                <span class="text-rose-500 dark:text-rose-400"
                                    >*</span
                                ></Label
                            ><Input
                                v-model="reportForm.title"
                                required
                                placeholder="Ví dụ: Khách trượt ngã tại khu vực lễ tân"
                                class="h-10 border-slate-300 bg-white text-slate-800 placeholder:text-slate-400 shadow-xs dark:border-white/10 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-600"
                            />
                            <p
                                v-if="reportForm.errors.title"
                                class="text-[11px] font-bold text-rose-600 dark:text-rose-400"
                            >
                                {{ reportForm.errors.title }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label
                                class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                                >Mô tả diễn biến
                                <span class="text-rose-500 dark:text-rose-400"
                                    >*</span
                                ></Label
                            ><textarea
                                v-model="reportForm.description"
                                rows="4"
                                required
                                minlength="10"
                                placeholder="Điều gì đã xảy ra? Ai bị ảnh hưởng? Khu vực nào cần cô lập?"
                                class="w-full resize-none rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-xs outline-none placeholder:text-slate-400 focus:border-rose-500 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-600"
                            ></textarea>
                            <p
                                v-if="reportForm.errors.description"
                                class="text-[11px] font-bold text-rose-600 dark:text-rose-400"
                            >
                                {{ reportForm.errors.description }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-4">
                        <div
                            class="rounded-2xl border border-sky-300/80 bg-sky-50/80 p-4 shadow-xs dark:border-sky-900/60 dark:bg-sky-950/30"
                        >
                            <div class="flex items-start gap-3">
                                <component
                                    :is="activeGuidance.icon"
                                    class="mt-0.5 size-5 shrink-0 text-sky-700 dark:text-sky-300"
                                />
                                <div>
                                    <div
                                        class="text-xs font-bold text-sky-950 dark:text-sky-200"
                                    >
                                        {{ activeGuidance.title }}
                                    </div>
                                    <p
                                        class="mt-1 text-[11px] leading-relaxed font-medium text-sky-800 dark:text-sky-200/70"
                                    >
                                        {{ activeGuidance.text }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <Label
                                    class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                                    >Người bị thương</Label
                                ><Input
                                    v-model="reportForm.injured_count"
                                    type="number"
                                    min="0"
                                    class="h-10 border-slate-300 bg-white text-slate-800 shadow-xs dark:border-white/10 dark:bg-slate-900 dark:text-slate-100"
                                />
                            </div>
                            <label
                                class="flex cursor-pointer items-end gap-2 pb-2 text-xs font-bold text-slate-700 dark:text-slate-300"
                                ><input
                                    v-model="reportForm.needs_shift_cover"
                                    type="checkbox"
                                    class="size-4 rounded border-slate-300 bg-white text-rose-600 dark:border-white/20 dark:bg-slate-900"
                                />
                                Cần thay ca gấp</label
                            >
                        </div>
                    </div>
                </div>
                <div class="grid gap-4 lg:grid-cols-[1fr_1fr_0.8fr]">
                    <div class="flex flex-col gap-1.5">
                        <Label
                            class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                            >Xử lý ngay tại chỗ</Label
                        ><textarea
                            v-model="reportForm.immediate_action"
                            rows="3"
                            placeholder="Đã sơ cứu, ngắt điện, gọi 115/114, cô lập khu vực..."
                            class="w-full resize-none rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-xs outline-none placeholder:text-slate-400 focus:border-rose-500 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-600"
                        ></textarea>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label
                            class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                            >Ảnh hiện trường</Label
                        ><label
                            class="flex min-h-[82px] cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 text-xs font-semibold text-slate-700 shadow-2xs transition hover:border-rose-500 hover:text-rose-600 dark:border-white/15 dark:bg-slate-900 dark:text-slate-400 dark:hover:text-rose-300"
                            ><Camera class="size-4 text-slate-500" /><span>{{
                                reportForm.photo?.name || 'Chọn ảnh bằng chứng'
                            }}</span
                            ><input
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @input="
                                    reportForm.photo =
                                        ($event.target as HTMLInputElement)
                                            .files?.[0] ?? null
                                "
                        /></label>
                    </div>
                    <div
                        class="flex flex-col justify-end gap-2 sm:flex-row lg:flex-col"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="showReportForm = false"
                            class="h-10 rounded-xl border-slate-300 bg-white font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:bg-transparent dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white"
                            >Hủy</Button
                        ><Button
                            type="submit"
                            :disabled="reportForm.processing"
                            class="h-10 rounded-xl border-0 bg-rose-600 font-bold text-white shadow-sm hover:bg-rose-700 dark:bg-rose-500 dark:hover:bg-rose-400"
                            >Ghi nhận sự cố</Button
                        >
                    </div>
                </div>
                <div
                    class="flex items-start gap-2 rounded-xl border border-amber-300/80 bg-amber-50/90 p-3 text-[11px] leading-relaxed font-semibold text-amber-900 shadow-2xs dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-200/80"
                >
                    <Info
                        class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-300"
                    />Cháy nổ, ngộ độc, tai nạn, mức Cao/Nghiêm trọng hoặc có
                    người bị thương sẽ tự động báo Chủ nhà hàng.
                </div>
            </form>
        </section>

        <div
            class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.45fr)_360px]"
        >
            <section
                class="min-w-0 rounded-3xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5 dark:border-white/10 dark:bg-slate-950 dark:shadow-xl"
            >
                <div
                    class="flex flex-col gap-4 border-b border-slate-200 pb-4 dark:border-white/10"
                >
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div>
                            <div
                                class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                            >
                                <ClipboardCheck
                                    class="size-5 text-rose-500 dark:text-rose-400"
                                />Sổ điều phối sự cố
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ filtered.length }} kết quả · ưu tiên theo mức
                                độ và SLA
                            </p>
                        </div>
                        <div
                            class="flex rounded-xl border border-slate-200 bg-slate-100 p-1 dark:border-white/10 dark:bg-slate-900"
                        >
                            <button
                                v-for="tab in [
                                    { key: 'active', label: 'Đang xử lý' },
                                    { key: 'resolved', label: 'Đã đóng' },
                                    { key: 'all', label: 'Tất cả' },
                                ]"
                                :key="tab.key"
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-[11px] font-bold transition"
                                :class="
                                    activeFilter === tab.key
                                        ? 'border border-slate-200/60 bg-white text-slate-900 shadow-xs dark:border-transparent dark:bg-slate-700 dark:text-white'
                                        : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-300'
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
                    <div class="grid gap-2 md:grid-cols-[1fr_auto_auto_auto]">
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400 dark:text-slate-600"
                            /><Input
                                v-model="searchQuery"
                                placeholder="Tìm mã, tiêu đề, vị trí, người báo..."
                                class="h-10 border-slate-300 bg-white pl-9 text-xs text-slate-800 placeholder:text-slate-400 shadow-xs focus:border-rose-500 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-600"
                            />
                        </div>
                        <select
                            v-model="severityFilter"
                            class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-medium text-slate-800 shadow-xs outline-none focus:border-rose-500 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300"
                        >
                            <option value="all">Mọi mức độ</option>
                            <option value="critical">Nghiêm trọng</option>
                            <option value="high">Cao</option>
                            <option value="medium">Trung bình</option>
                            <option value="low">Thấp</option></select
                        ><select
                            v-model="typeFilter"
                            class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-medium text-slate-800 shadow-xs outline-none focus:border-rose-500 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300"
                        >
                            <option value="all">Mọi loại sự cố</option>
                            <option
                                v-for="(config, key) in typeConfig"
                                :key="key"
                                :value="key"
                            >
                                {{ config.label }}
                            </option></select
                        ><select
                            v-model="sortMode"
                            class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-medium text-slate-800 shadow-xs outline-none focus:border-rose-500 dark:border-white/10 dark:bg-slate-900 dark:text-slate-300"
                        >
                            <option value="priority">Ưu tiên xử lý</option>
                            <option value="recent">Mới nhất</option>
                        </select>
                    </div>
                </div>
                <div
                    v-if="filtered.length === 0"
                    class="flex min-h-[360px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 p-8 text-center dark:border-white/10"
                >
                    <div
                        class="flex size-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                    >
                        <ShieldCheck class="size-7" />
                    </div>
                    <h2 class="mt-4 text-sm font-bold text-slate-900 dark:text-white">
                        Không có sự cố trong nhóm này
                    </h2>
                    <p
                        class="mt-1 max-w-sm text-xs leading-relaxed font-medium text-slate-500 dark:text-slate-400"
                    >
                        Hàng đợi đang sạch hoặc bộ lọc hiện tại không có kết quả
                        phù hợp.
                    </p>
                    <Button
                        @click="
                            showReportForm = true;
                            activeFilter = 'all';
                            searchQuery = '';
                            severityFilter = 'all';
                            typeFilter = 'all';
                        "
                        class="mt-5 h-9 gap-2 rounded-xl border-0 bg-rose-600 text-xs font-bold text-white shadow-sm hover:bg-rose-700 dark:bg-rose-500 dark:hover:bg-rose-400"
                        ><Plus class="size-3.5" />Báo sự cố mới</Button
                    >
                </div>
                <div v-else class="mt-4 flex flex-col gap-3">
                    <article
                        v-for="incident in filtered"
                        :key="incident.id"
                        class="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white transition hover:border-slate-300 shadow-2xs dark:border-white/10 dark:bg-slate-900/60 dark:hover:border-white/20"
                    >
                        <div
                            class="absolute inset-y-0 left-0 w-1"
                            :class="
                                severityConfig[incident.severity]?.railClass
                            "
                        />
                        <div class="p-4 pl-5 sm:p-5 sm:pl-6">
                            <div
                                class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div class="flex min-w-0 items-start gap-3">
                                    <div
                                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800"
                                    >
                                        <component
                                            :is="
                                                typeConfig[incident.type]
                                                    ?.icon ?? ShieldAlert
                                            "
                                            class="size-5"
                                            :class="
                                                typeConfig[incident.type]
                                                    ?.iconClass
                                            "
                                        />
                                    </div>
                                    <div class="min-w-0">
                                        <div
                                            class="mb-1.5 flex flex-wrap items-center gap-1.5"
                                        >
                                            <span
                                                class="font-mono text-[10px] font-bold text-slate-600 dark:text-slate-500"
                                                >{{ incident.code }}</span
                                            ><span
                                                class="flex items-center gap-1 text-[10px] font-bold text-slate-600 dark:text-slate-400"
                                                ><span
                                                    class="size-1.5 rounded-full"
                                                    :class="
                                                        typeConfig[
                                                            incident.type
                                                        ]?.dotClass
                                                    "
                                                />{{
                                                    typeConfig[incident.type]
                                                        ?.label
                                                }}</span
                                            ><span
                                                class="rounded-md border px-1.5 py-0.5 text-[9px] font-black"
                                                :class="
                                                    severityConfig[
                                                        incident.severity
                                                    ]?.badgeClass
                                                "
                                                >{{
                                                    severityConfig[
                                                        incident.severity
                                                    ]?.label
                                                }}</span
                                            ><span
                                                v-if="
                                                    incident.injured_count > 0
                                                "
                                                class="rounded-md border border-rose-300 bg-rose-50 px-1.5 py-0.5 text-[9px] font-bold text-rose-800 dark:border-red-900/70 dark:bg-red-950/50 dark:text-red-300"
                                                >{{
                                                    incident.injured_count
                                                }}
                                                người bị thương</span
                                            ><span
                                                v-if="
                                                    incident.needs_shift_cover
                                                "
                                                class="rounded-md border border-cyan-300 bg-cyan-50 px-1.5 py-0.5 text-[9px] font-bold text-cyan-800 dark:border-cyan-900/70 dark:bg-cyan-950/50 dark:text-cyan-300"
                                                >Cần thay ca</span
                                            >
                                        </div>
                                        <h3
                                            class="truncate text-sm font-bold text-slate-900 sm:text-base dark:text-white"
                                        >
                                            {{ incident.title }}
                                        </h3>
                                        <p
                                            class="mt-1 text-xs leading-relaxed font-medium text-slate-600 dark:text-slate-400"
                                        >
                                            {{ incident.description }}
                                        </p>
                                        <div
                                            class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[10px] font-semibold text-slate-500"
                                        >
                                            <span
                                                v-if="incident.location"
                                                class="flex items-center gap-1"
                                                ><MapPin class="size-3 text-slate-400" />{{
                                                    incident.location
                                                }}</span
                                            ><span
                                                class="flex items-center gap-1"
                                                ><Clock3 class="size-3 text-slate-400" />{{
                                                    incident.occurred_at_display
                                                }}</span
                                            ><span
                                                class="flex items-center gap-1"
                                                ><UserRound class="size-3 text-slate-400" />{{
                                                    incident.reported_by_name
                                                }}</span
                                            ><span
                                                v-if="incident.branch_name"
                                                >{{
                                                    incident.branch_name
                                                }}</span
                                            ><a
                                                v-if="incident.photo_url"
                                                :href="incident.photo_url"
                                                target="_blank"
                                                class="flex items-center gap-1 font-bold text-sky-600 hover:underline dark:text-sky-400 dark:hover:text-sky-300"
                                                ><Camera class="size-3" />Ảnh
                                                bằng chứng</a
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="flex shrink-0 items-center gap-2 lg:flex-col lg:items-end"
                                >
                                    <span
                                        class="rounded-lg border px-2 py-1 text-[10px] font-bold"
                                        :class="
                                            statusConfig[incident.status].class
                                        "
                                        >{{
                                            statusConfig[incident.status].label
                                        }}</span
                                    ><span
                                        v-if="incident.escalated"
                                        class="flex items-center gap-1 rounded-md border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-800 dark:border-transparent dark:bg-transparent dark:text-rose-300"
                                        ><ArrowUpCircle class="size-3.5" />Đã
                                        báo Chủ</span
                                    >
                                </div>
                            </div>
                            <div
                                class="mt-4 grid gap-2 border-y border-slate-200/80 py-3 sm:grid-cols-3 dark:border-white/10"
                            >
                                <div class="flex items-center gap-2">
                                    <TimerReset
                                        class="size-4"
                                        :class="
                                            incident.sla_state === 'overdue' ||
                                            incident.sla_state === 'breached'
                                                ? 'text-rose-600 dark:text-rose-300'
                                                : 'text-sky-600 dark:text-sky-300'
                                        "
                                    />
                                    <div>
                                        <div
                                            class="text-[9px] font-black tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Phản hồi
                                        </div>
                                        <div
                                            class="text-[11px] font-bold"
                                            :class="
                                                incident.sla_state ===
                                                    'overdue' ||
                                                incident.sla_state ===
                                                    'breached'
                                                    ? 'text-rose-700 dark:text-rose-300'
                                                    : 'text-slate-800 dark:text-slate-300'
                                            "
                                        >
                                            {{
                                                incident.response_time_minutes !==
                                                null
                                                    ? formatMinutes(
                                                           incident.response_time_minutes,
                                                       )
                                                    : 'Chưa tiếp nhận'
                                            }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Clock3 class="size-4 text-slate-400" />
                                    <div>
                                        <div
                                            class="text-[9px] font-black tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                        >
                                            Hạn phản hồi
                                        </div>
                                        <div
                                            class="text-[11px] font-bold text-slate-800 dark:text-slate-300"
                                        >
                                            {{
                                                incident.response_due_at_display ||
                                                '—'
                                            }}
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="flex items-center justify-start gap-2 sm:justify-end"
                                >
                                    <span
                                        class="rounded-lg border px-2 py-1 text-[10px] font-bold"
                                        :class="slaClass(incident.sla_state)"
                                        >{{
                                            slaLabel(incident.sla_state)
                                        }}</span
                                    >
                                </div>
                            </div>
                            <div
                                class="flex flex-wrap items-center justify-between gap-2 pt-3"
                            >
                                <button
                                    type="button"
                                    class="flex items-center gap-1.5 text-[11px] font-bold text-slate-600 transition hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                                    @click="toggleExpanded(incident)"
                                >
                                    <ChevronDown
                                        class="size-3.5 transition"
                                        :class="
                                            expandedId === incident.id
                                                ? 'rotate-180'
                                                : ''
                                        "
                                    />{{
                                        expandedId === incident.id
                                            ? 'Thu gọn'
                                            : 'Xem chi tiết & nhật ký'
                                    }}
                                </button>
                                <div
                                    v-if="
                                        props.canManage &&
                                        incident.status !== 'resolved'
                                    "
                                    class="flex flex-wrap gap-2"
                                >
                                    <Button
                                        v-if="incident.status === 'open'"
                                        size="sm"
                                        variant="outline"
                                        @click="doAcknowledge(incident)"
                                        class="h-8 gap-1.5 rounded-lg border-slate-300 bg-slate-100 font-bold text-[11px] text-slate-800 hover:bg-slate-200 dark:border-white/10 dark:bg-transparent dark:text-slate-200 dark:hover:bg-white/5"
                                        ><ClipboardCheck class="size-3.5 text-blue-600" />Tiếp
                                        nhận</Button
                                    ><Button
                                        v-if="!incident.escalated"
                                        size="sm"
                                        variant="outline"
                                        @click="doEscalate(incident)"
                                        class="h-8 gap-1.5 rounded-lg border-rose-300 bg-rose-50 font-bold text-[11px] text-rose-700 hover:bg-rose-100 dark:border-rose-900/70 dark:bg-transparent dark:text-rose-300 dark:hover:bg-rose-950/40"
                                        ><ArrowUpCircle class="size-3.5 text-rose-600" />Báo
                                        Chủ</Button
                                    ><Button
                                        size="sm"
                                        @click="openResolve(incident)"
                                        class="h-8 gap-1.5 rounded-lg border-0 bg-emerald-600 text-[11px] font-bold text-white shadow-xs hover:bg-emerald-500"
                                        ><CheckCircle2 class="size-3.5" />Đóng
                                        sự cố</Button
                                    >
                                </div>
                                <div
                                    v-else-if="
                                        !props.canManage &&
                                        incident.status !== 'resolved'
                                    "
                                    class="flex items-center gap-1.5 text-[10px] font-semibold text-slate-500"
                                >
                                    <Lock class="size-3.5" />Chỉ quản lý/Chủ
                                    được xử lý
                                </div>
                            </div>
                            <div
                                v-if="expandedId === incident.id"
                                class="mt-4 grid gap-4 border-t border-slate-200/80 pt-4 lg:grid-cols-[1fr_1fr] dark:border-white/10"
                            >
                                <div class="space-y-3">
                                    <div>
                                        <div
                                            class="mb-1 text-[10px] font-black tracking-wider text-slate-600 uppercase dark:text-slate-500"
                                        >
                                            Xử lý ngay tại chỗ
                                        </div>
                                        <p
                                            class="text-xs leading-relaxed font-medium text-slate-700 dark:text-slate-300"
                                        >
                                            {{
                                                incident.immediate_action ||
                                                'Chưa ghi nhận hành động ban đầu.'
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="
                                            incident.status === 'resolved' &&
                                            incident.resolution_report
                                        "
                                    >
                                        <div
                                            class="mb-1 flex items-center gap-1.5 text-[10px] font-black tracking-wider text-emerald-800 uppercase dark:text-emerald-300"
                                        >
                                            <FileText class="size-3.5" />Báo cáo
                                            đóng sự cố
                                        </div>
                                        <p
                                            class="text-xs leading-relaxed font-medium text-slate-700 dark:text-slate-300"
                                        >
                                            {{ incident.resolution_report }}
                                        </p>
                                        <p
                                            class="mt-1 text-[10px] font-medium text-slate-500 dark:text-slate-400"
                                        >
                                            {{ incident.resolved_by_name }} ·
                                            {{ incident.resolved_at_display }} ·
                                            Thời gian xử lý
                                            {{
                                                formatMinutes(
                                                    incident.resolution_time_minutes,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="rounded-xl border border-slate-200/90 bg-slate-50/80 p-3 dark:border-white/10 dark:bg-slate-950/60"
                                >
                                    <div
                                        class="mb-3 text-[10px] font-black tracking-wider text-slate-600 uppercase dark:text-slate-500"
                                    >
                                        Nhật ký trạng thái
                                    </div>
                                    <div
                                        class="grid grid-cols-1 gap-3 text-[10px]"
                                    >
                                        <div class="flex items-start gap-2">
                                            <span
                                                class="mt-0.5 size-2 rounded-full bg-blue-500"
                                            />
                                            <div>
                                                <div
                                                    class="font-bold text-slate-900 dark:text-slate-300"
                                                >
                                                    Đã báo ·
                                                    {{
                                                        incident.occurred_at_display
                                                    }}
                                                </div>
                                                <div class="font-medium text-slate-500 dark:text-slate-600">
                                                    {{
                                                        incident.reported_by_name
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-2">
                                            <span
                                                class="mt-0.5 size-2 rounded-full"
                                                :class="
                                                    incident.acknowledged_at_display
                                                        ? 'bg-amber-500'
                                                        : 'bg-slate-300 dark:bg-slate-700'
                                                "
                                            />
                                            <div>
                                                <div
                                                    class="font-bold text-slate-900 dark:text-slate-300"
                                                >
                                                    {{
                                                        incident.acknowledged_at_display
                                                            ? `Đã tiếp nhận · ${incident.acknowledged_at_display}`
                                                            : 'Chưa tiếp nhận'
                                                    }}
                                                </div>
                                                <div class="font-medium text-slate-500 dark:text-slate-600">
                                                    {{
                                                        incident.acknowledged_by_name ||
                                                        'Đang chờ quản lý'
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            v-if="incident.escalated"
                                            class="flex items-start gap-2"
                                        >
                                            <span
                                                class="mt-0.5 size-2 rounded-full bg-rose-500"
                                            />
                                            <div>
                                                <div
                                                    class="font-bold text-slate-900 dark:text-slate-300"
                                                >
                                                    Đã báo Chủ ·
                                                    {{
                                                        incident.escalated_at_display ||
                                                        'Đã ghi nhận'
                                                    }}
                                                </div>
                                                <div class="font-medium text-slate-500 dark:text-slate-600">
                                                    {{
                                                        incident.escalated_to_name ||
                                                        'Chủ nhà hàng'
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            v-if="
                                                incident.status === 'resolved'
                                            "
                                            class="flex items-start gap-2"
                                        >
                                            <span
                                                class="mt-0.5 size-2 rounded-full bg-emerald-500"
                                            />
                                            <div>
                                                <div
                                                    class="font-bold text-slate-900 dark:text-slate-300"
                                                >
                                                    Đã đóng ·
                                                    {{
                                                        incident.resolved_at_display
                                                    }}
                                                </div>
                                                <div class="font-medium text-slate-500 dark:text-slate-600">
                                                    {{
                                                        incident.resolved_by_name
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <aside class="flex flex-col gap-5">
                <section
                    class="rounded-3xl border border-rose-200/90 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950 dark:shadow-xl"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <div
                                class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white"
                            >
                                <AlertTriangle
                                    class="size-4 text-rose-600 dark:text-rose-400"
                                />Cần hành động
                            </div>
                            <p class="mt-1 text-[11px] text-slate-500">
                                Ưu tiên theo SLA và mức độ
                            </p>
                        </div>
                        <span
                            class="rounded-full border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700 dark:border-transparent dark:bg-rose-500/10 dark:text-rose-300"
                            >{{ priorityQueue.length }}</span
                        >
                    </div>
                    <div v-if="priorityQueue.length" class="mt-4 space-y-2">
                        <button
                            v-for="incident in priorityQueue"
                            :key="incident.id"
                            type="button"
                            class="group flex w-full items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-left transition hover:border-rose-300 hover:bg-rose-50/50 dark:border-white/10 dark:bg-slate-900/70 dark:hover:border-rose-900/70 dark:hover:bg-rose-950/20"
                            @click="
                                activeFilter = 'active';
                                searchQuery = incident.code;
                                expandedId = incident.id;
                            "
                        >
                            <span
                                class="mt-1.5 size-2 shrink-0 rounded-full"
                                :class="
                                    severityConfig[incident.severity]?.railClass
                                "
                            /><span class="min-w-0 flex-1"
                                ><span
                                    class="block truncate text-xs font-bold text-slate-800 group-hover:text-rose-700 dark:text-slate-200 dark:group-hover:text-white"
                                    >{{ incident.title }}</span
                                ><span
                                    class="mt-1 block text-[10px] text-slate-500"
                                    >{{ incident.code }} ·
                                    {{ actionSummary(incident) }}</span
                                ></span
                            ><ArrowUpCircle
                                v-if="incident.escalated"
                                class="size-3.5 shrink-0 text-rose-600 dark:text-rose-400"
                            />
                        </button>
                    </div>
                    <div
                        v-else
                        class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/50 p-4 text-center text-[11px] text-slate-500 dark:border-white/10 dark:bg-transparent dark:text-slate-600"
                    >
                        Không có sự cố cần ưu tiên.
                    </div>
                </section>
                <section
                    class="rounded-3xl border border-slate-200/90 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-950 dark:shadow-xl"
                >
                    <div
                        class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white"
                    >
                        <ShieldCheck class="size-4 text-emerald-600 dark:text-emerald-400" />Quy trình
                        phản ứng
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500">
                        Mỗi sự cố đi qua 4 bước bắt buộc
                    </p>
                    <div class="mt-5 space-y-4">
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
                                class="flex size-7 shrink-0 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-[10px] font-black text-rose-700 dark:border-white/10 dark:bg-slate-900 dark:text-rose-300"
                            >
                                0{{ index + 1 }}
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                    {{ step.title }}
                                </div>
                                <p
                                    class="mt-1 text-[10px] leading-relaxed text-slate-500"
                                >
                                    {{ step.text }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
                <section
                    class="rounded-3xl border border-sky-200 bg-sky-50/70 p-5 dark:border-sky-900/50 dark:bg-sky-950/20"
                >
                    <div class="flex items-start gap-3">
                        <Info class="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-300" />
                        <div>
                            <div class="text-xs font-bold text-sky-900 dark:text-sky-200">
                                Nguyên tắc an toàn
                            </div>
                            <p
                                class="mt-1 text-[11px] leading-relaxed text-sky-800/90 dark:text-sky-200/70"
                            >
                                An toàn con người luôn trước tài sản. Không tự
                                xử lý tình huống vượt quá thẩm quyền; báo quản
                                lý hoặc cơ quan khẩn cấp phù hợp.
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="!props.canManage"
                        class="mt-4 flex items-center gap-2 border-t border-sky-200 pt-3 text-[10px] text-sky-700 dark:border-sky-900/40 dark:text-sky-200/60"
                    >
                        <Lock class="size-3.5" />Bạn có thể báo và theo dõi,
                        không thể tự đóng sự cố.
                    </div>
                </section>
            </aside>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="showResolveModal && selected"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-xl rounded-3xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6 dark:border-white/10 dark:bg-slate-950"
            >
                <div
                    class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4 dark:border-white/10"
                >
                    <div>
                        <div
                            class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white"
                        >
                            <CheckCircle2 class="size-5 text-emerald-600 dark:text-emerald-400" />Đóng
                            sự cố kèm báo cáo
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ selected.code }} · {{ selected.title }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-500 dark:hover:bg-white/5 dark:hover:text-white"
                        @click="showResolveModal = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 dark:border-transparent dark:bg-slate-900">
                        <div
                            class="text-[9px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-600"
                        >
                            Mức độ
                        </div>
                        <div class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-300">
                            {{ severityConfig[selected.severity]?.label }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 dark:border-transparent dark:bg-slate-900">
                        <div
                            class="text-[9px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-600"
                        >
                            Phản hồi
                        </div>
                        <div class="mt-1 text-xs font-bold text-slate-800 dark:text-slate-200">
                            {{ formatMinutes(selected.response_time_minutes) }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 dark:border-transparent dark:bg-slate-900">
                        <div
                            class="text-[9px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-600"
                        >
                            Người báo
                        </div>
                        <div
                            class="mt-1 truncate text-xs font-bold text-slate-800 dark:text-slate-200"
                        >
                            {{ selected.reported_by_name }}
                        </div>
                    </div>
                </div>
                <form
                    @submit.prevent="submitResolve"
                    class="mt-5 flex flex-col gap-3"
                >
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300"
                            >Báo cáo xử lý
                            <span class="text-rose-500 dark:text-rose-400">*</span></Label
                        ><textarea
                            v-model="resolveForm.resolution_report"
                            rows="6"
                            required
                            minlength="20"
                            placeholder="Nguyên nhân, biện pháp đã thực hiện, kết quả và cách phòng ngừa tái diễn (tối thiểu 20 ký tự)..."
                            class="w-full resize-none rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-emerald-500 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-600"
                        ></textarea>
                        <p
                            v-if="resolveForm.errors.resolution_report"
                            class="text-[11px] font-semibold text-rose-600 dark:text-rose-400"
                        >
                            {{ resolveForm.errors.resolution_report }}
                        </p>
                    </div>
                    <div
                        class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-white/10"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="showResolveModal = false"
                            class="rounded-xl border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:bg-transparent dark:text-slate-300 dark:hover:bg-white/5"
                            >Hủy</Button
                        ><Button
                            type="submit"
                            :disabled="resolveForm.processing"
                            class="rounded-xl border-0 bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-500"
                            ><CheckCircle2 class="mr-1.5 size-3.5" />Đóng sự
                            cố</Button
                        >
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
