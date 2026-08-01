<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    BellRing,
    BookOpenText,
    Check,
    CheckCircle2,
    CheckSquare2,
    ChevronRight,
    Clock,
    Cpu,
    Database,
    Download,
    Eye,
    Globe,
    Headset,
    LifeBuoy,
    MonitorSpeaker,
    Pencil,
    Play,
    PlusCircle,
    Radio,
    RefreshCcw,
    Send,
    Siren,
    Square,
    Ticket,
    Trash2,
    XCircle,
    Zap,
} from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import {
    PageHeader,
    StatusBadge,
    StatCard,
    Pagination,
    LedIndicator,
    ProgressBar,
} from '@/components/super-admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type PaginatorLink = { url: string | null; label: string; active: boolean };
type Paginator<T> = {
    data: T[];
    links: PaginatorLink[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
};

type TicketReplyRow = {
    id: number;
    user_name: string;
    is_internal: boolean;
    message: string;
    created_at: string;
};
type TicketRow = {
    id: number;
    code: string;
    restaurant: string;
    title: string;
    category: string;
    severity: string;
    priority: string;
    status: string;
    assignee: string | null;
    assigned_to: number | null;
    created_by: string;
    created_at: string;
    replies: TicketReplyRow[];
    description?: string;
    created_at_raw: string;
    sla_due_at: string | null;
    first_response_at: string | null;
    escalated_at: string | null;
    sla_status: 'fulfilled' | 'pending' | 'warning' | 'breached';
    restaurant_plan: string;
    restaurant_plan_code: string;
};
type AlertRow = {
    id: number;
    title: string;
    metric_key: string;
    metric_value: string | number | null;
    threshold: string | number | null;
    status: string;
    triggered_at: string | null;
};
type RuleRow = {
    id: number;
    name: string;
    metric_key: string;
    operator: string;
    threshold: number | string;
    cooldown_minutes: number;
    is_active: boolean;
    channels: string[];
};
type AnnouncementRow = {
    id: number;
    title: string;
    message: string;
    status: string;
    level: string;
    audience: string;
    published_at: string | null;
};
type ArticleRow = {
    id: number;
    title: string;
    category: string;
    is_published: boolean;
    view_count: number;
    video_url: string | null;
    summary?: string;
};
type TicketFormData = {
    restaurant_id: string | null;
    category: string;
    title: string;
    description: string;
};

const props = defineProps<{
    stats: Record<string, number>;
    monitoring: {
        failed_jobs: number;
        pending_jobs: number;
        queue_backlog: number;
        api_error_rate: number;
        api_error_total: number;
        api_request_total: number;
        slow_queries: number;
        pulse_exceptions: number;
        infra: { cpu: number | null; ram: number | null; source: string };
    };
    sla_stats: {
        total_checked: number;
        sla_breached: number;
        sla_warning: number;
        sla_fulfill_rate: number;
    };
    tickets: Paginator<TicketRow>;
    alerts: Paginator<AlertRow>;
    rules: Paginator<RuleRow>;
    announcements: Paginator<AnnouncementRow>;
    articles: Paginator<ArticleRow>;
    restaurants: Array<{ id: number; name: string }>;
    staff: Array<{ id: number; name: string }>;
    filters: { status?: string; severity?: string; restaurant_id?: string };
}>();

const activeTab = ref('monitoring');

const ticketForm = useForm<TicketFormData>({
    restaurant_id: 'system',
    category: 'realtime',
    title: '',
    description: '',
});
const replyForms = ref<
    Record<
        number,
        ReturnType<typeof useForm<{ message: string; is_internal: boolean }>>
    >
>({});
const expandedTicketId = ref<number | null>(null);

function toggleTicketDetail(id: number) {
    expandedTicketId.value = expandedTicketId.value === id ? null : id;
}

function replyForm(ticketId: number) {
    if (!replyForms.value[ticketId]) {
        replyForms.value[ticketId] = useForm({
            message: '',
            is_internal: false,
        });
    }

    return replyForms.value[ticketId];
}

function submitReply(ticketId: number) {
    const form = replyForm(ticketId);
    form.post(`/super-admin/support/tickets/${ticketId}/replies`, {
        preserveScroll: true,
        onSuccess: () => form.reset('message'),
    });
}

function assignTicket(id: number, assignedTo: string) {
    router.patch(
        `/super-admin/support/tickets/${id}`,
        { assigned_to: assignedTo === 'unassigned' ? null : assignedTo },
        { preserveScroll: true },
    );
}

const announcementForm = useForm({
    title: '',
    message: '',
    audience: 'all',
    level: 'warning',
    starts_at: '',
    ends_at: '',
    publish_now: true,
});
const articleForm = useForm({
    category: 'onboarding',
    title: '',
    summary: '',
    content: '',
    video_url: '',
    is_published: true,
});
const ruleForm = useForm({
    name: '',
    metric_key: 'api_error_rate',
    operator: '>',
    threshold: 5,
    cooldown_minutes: 15,
});

const severityBadge: Record<string, string> = {
    critical:
        'bg-red-500/10 text-red-600 border border-red-500/20 dark:text-red-400 dark:bg-red-500/5',
    high: 'bg-orange-500/10 text-orange-600 border border-orange-500/20 dark:text-orange-400 dark:bg-orange-500/5',
    medium: 'bg-amber-500/10 text-amber-600 border border-amber-500/20 dark:text-amber-400 dark:bg-amber-500/5',
    low: 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 dark:text-emerald-400 dark:bg-emerald-500/5',
};

const statusBadge: Record<string, string> = {
    open: 'bg-rose-500/10 text-rose-600 border border-rose-500/25 dark:text-rose-400',
    in_progress:
        'bg-sky-500/10 text-sky-600 border border-sky-500/25 dark:text-sky-400',
    waiting_restaurant:
        'bg-amber-500/10 text-amber-600 border border-amber-500/25 dark:text-amber-400',
    resolved:
        'bg-emerald-500/10 text-emerald-600 border border-emerald-500/25 dark:text-emerald-400',
    closed: 'bg-slate-500/10 text-slate-600 border border-slate-500/25 dark:text-slate-400',
    published:
        'bg-emerald-500/10 text-emerald-600 border border-emerald-500/25 dark:text-emerald-400',
    draft: 'bg-slate-500/10 text-slate-600 border border-slate-500/25 dark:text-slate-400',
};

const systemHealth = computed(() => {
    const rate = props.monitoring.api_error_rate;
    const failed = props.monitoring.failed_jobs;

    if (failed > 0 || rate > 5) {
        return {
            label: 'Cảnh báo hệ thống',
            color: 'text-rose-500 dark:text-rose-400',
            bg: 'bg-rose-500/10 border border-rose-500/20',
            dot: 'bg-rose-500 shadow-[0_0_12px_rgba(239,68,68,0.8)]',
        };
    }

    if (rate > 2) {
        return {
            label: 'Cần lưu ý',
            color: 'text-amber-500 dark:text-amber-400',
            bg: 'bg-amber-500/10 border border-amber-500/20',
            dot: 'bg-amber-500 shadow-[0_0_12px_rgba(245,158,11,0.8)]',
        };
    }

    return {
        label: 'Hoạt động ổn định',
        color: 'text-emerald-500 dark:text-emerald-400',
        bg: 'bg-emerald-500/10 border border-emerald-500/20',
        dot: 'bg-emerald-500 shadow-[0_0_12px_rgba(16,185,129,0.8)] animate-pulse-glow',
    };
});

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    if (props.filters.status) {
        params.set('status', props.filters.status);
    }

    if (props.filters.severity) {
        params.set('severity', props.filters.severity);
    }

    const qs = params.toString();

    return `/super-admin/support/export${qs ? '?' + qs : ''}`;
});

function runAlertCheck() {
    router.post(
        '/super-admin/support/alerts/run',
        {},
        { preserveScroll: true },
    );
}

function submitTicket() {
    ticketForm
        .transform((data: TicketFormData) => ({
            ...data,
            restaurant_id:
                data.restaurant_id === 'system' ? null : data.restaurant_id,
        }))
        .post('/super-admin/support/tickets', {
            preserveScroll: true,
            onSuccess: () => ticketForm.reset('title', 'description'),
        });
}

function updateTicket(id: number, status: string) {
    router.patch(
        `/super-admin/support/tickets/${id}`,
        { status },
        { preserveScroll: true },
    );
}

function submitAnnouncement() {
    announcementForm.post('/super-admin/support/announcements', {
        preserveScroll: true,
        onSuccess: () => announcementForm.reset('title', 'message'),
    });
}

function submitArticle() {
    articleForm.post('/super-admin/support/articles', {
        preserveScroll: true,
        onSuccess: () =>
            articleForm.reset('title', 'summary', 'content', 'video_url'),
    });
}

function submitRule() {
    ruleForm.post('/super-admin/support/rules', {
        preserveScroll: true,
        onSuccess: () => ruleForm.reset('name'),
    });
}

// ── Pagination navigation ─────────────────────────────────────
function goToTicketsPage(url: string | null) {
    if (!url) {
        return;
    }

    router.get(
        url,
        {},
        { preserveState: true, preserveScroll: true, only: ['tickets'] },
    );
}

function goToAlertsPage(url: string | null) {
    if (!url) {
        return;
    }

    router.get(
        url,
        {},
        { preserveState: true, preserveScroll: true, only: ['alerts'] },
    );
}

function goToRulesPage(url: string | null) {
    if (!url) {
        return;
    }

    router.get(
        url,
        {},
        { preserveState: true, preserveScroll: true, only: ['rules'] },
    );
}

function goToAnnouncementsPage(url: string | null) {
    if (!url) {
        return;
    }

    router.get(
        url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ['announcements', 'stats'],
        },
    );
}

function goToArticlesPage(url: string | null) {
    if (!url) {
        return;
    }

    router.get(
        url,
        {},
        { preserveState: true, preserveScroll: true, only: ['articles'] },
    );
}

// ── Bulk ticket actions ───────────────────────────────────────
const selectedTicketIds = ref<Set<number>>(new Set());

function toggleSelectTicket(id: number) {
    if (selectedTicketIds.value.has(id)) {
        selectedTicketIds.value.delete(id);
    } else {
        selectedTicketIds.value.add(id);
    }

    selectedTicketIds.value = new Set(selectedTicketIds.value);
}

function toggleSelectAll() {
    if (selectedTicketIds.value.size === props.tickets.data.length) {
        selectedTicketIds.value = new Set();
    } else {
        selectedTicketIds.value = new Set(props.tickets.data.map((t) => t.id));
    }
}

const bulkStatus = ref('');
const bulkAssigned = ref('');

function submitBulk() {
    if (selectedTicketIds.value.size === 0) {
        return;
    }

    router.post(
        '/super-admin/support/tickets/bulk',
        {
            ticket_ids: [...selectedTicketIds.value],
            status: bulkStatus.value || undefined,
            assigned_to:
                bulkAssigned.value === 'unassigned'
                    ? null
                    : bulkAssigned.value || undefined,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedTicketIds.value = new Set();
                bulkStatus.value = '';
                bulkAssigned.value = '';
            },
        },
    );
}

// ── Edit ticket dialog ────────────────────────────────────────
const editingTicket = ref<TicketRow | null>(null);
const editTicketForm = useForm({
    title: '',
    category: '',
    severity: 'medium',
    description: '',
});

function openEditTicket(ticket: TicketRow) {
    editingTicket.value = ticket;
    editTicketForm.title = ticket.title;
    editTicketForm.category = ticket.category;
    editTicketForm.severity = ticket.severity;
    editTicketForm.description = ticket.description ?? '';
}

function submitEditTicket() {
    if (!editingTicket.value) {
        return;
    }

    editTicketForm.patch(
        `/super-admin/support/tickets/${editingTicket.value.id}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                editingTicket.value = null;
            },
        },
    );
}

async function deleteTicket(ticket: TicketRow) {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Xóa ticket "${ticket.code}"? Tất cả phản hồi cũng sẽ bị xóa.`,
        }))
    ) {
        return;
    }

    router.delete(`/super-admin/support/tickets/${ticket.id}`, {
        preserveScroll: true,
    });
}

async function deleteReply(ticketId: number, replyId: number) {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: 'Xóa phản hồi này?',
        }))
    ) {
        return;
    }

    router.delete(
        `/super-admin/support/tickets/${ticketId}/replies/${replyId}`,
        { preserveScroll: true },
    );
}

// ── Unpublish announcement ────────────────────────────────────
async function unpublishAnnouncement(id: number) {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description:
                'Gỡ đăng thông báo này? Thông báo sẽ chuyển sang trạng thái nháp.',
            variant: 'default',
        }))
    ) {
        return;
    }

    router.patch(
        `/super-admin/support/announcements/${id}/unpublish`,
        {},
        {
            preserveScroll: true,
            only: ['announcements', 'stats'],
        },
    );
}

// ── Edit rule dialog ──────────────────────────────────────────
const editingRule = ref<RuleRow | null>(null);
const editRuleForm = useForm({
    name: '',
    metric_key: '',
    operator: '>',
    threshold: 5,
    cooldown_minutes: 15,
    channels: [] as string[],
});

function openEditRule(rule: RuleRow) {
    editingRule.value = rule;
    editRuleForm.name = rule.name;
    editRuleForm.metric_key = rule.metric_key;
    editRuleForm.operator = rule.operator;
    editRuleForm.threshold = Number(rule.threshold);
    editRuleForm.cooldown_minutes = rule.cooldown_minutes;
    editRuleForm.channels = [...rule.channels];
}

function submitEditRule() {
    if (!editingRule.value) {
        return;
    }

    editRuleForm.patch(`/super-admin/support/rules/${editingRule.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingRule.value = null;
        },
    });
}

async function deleteRule(rule: RuleRow) {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Xóa rule "${rule.name}"?`,
        }))
    ) {
        return;
    }

    router.delete(`/super-admin/support/rules/${rule.id}`, {
        preserveScroll: true,
    });
}

function toggleRule(rule: RuleRow) {
    router.patch(
        `/super-admin/support/rules/${rule.id}/toggle`,
        {},
        { preserveScroll: true },
    );
}

// SLA & Escalation Monitor Client Logic
const currentTick = ref(Date.now());
let timerId: any = null;

onMounted(() => {
    timerId = setInterval(() => {
        currentTick.value = Date.now();
    }, 30000); // refresh every 30 seconds
});

onUnmounted(() => {
    if (timerId) {
        clearInterval(timerId);
    }
});

const isRecalculatingSla = ref(false);
function recalculateSla() {
    isRecalculatingSla.value = true;
    router.post(
        '/super-admin/support/sla/recalculate',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isRecalculatingSla.value = false;
            },
        },
    );
}

const isEscalatingTicket = ref<number | null>(null);
function escalateTicket(ticketId: number) {
    isEscalatingTicket.value = ticketId;
    router.post(
        `/super-admin/support/tickets/${ticketId}/escalate`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isEscalatingTicket.value = null;
            },
        },
    );
}

function getSlaRemainingTime(ticket: TicketRow) {
    if (ticket.first_response_at) {
        const firstResponse = new Date(ticket.first_response_at).getTime();
        const due = ticket.sla_due_at
            ? new Date(ticket.sla_due_at).getTime()
            : 0;

        if (due === 0) {
            return {
                label: 'N/A',
                status: 'pending',
                color: 'text-slate-500 font-semibold',
            };
        }

        const diffMs = due - firstResponse;
        const diffMins = Math.round(diffMs / 60000);

        if (diffMins >= 0) {
            return {
                label: `Phản hồi sớm ${formatMins(diffMins)}`,
                status: 'fulfilled',
                color: 'text-emerald-600 dark:text-emerald-400 font-bold',
            };
        } else {
            return {
                label: `Phản hồi trễ ${formatMins(Math.abs(diffMins))}`,
                status: 'breached',
                color: 'text-rose-600 dark:text-rose-400 font-bold',
            };
        }
    }

    if (!ticket.sla_due_at) {
        return {
            label: 'Chưa cấu hình SLA',
            status: 'pending',
            color: 'text-slate-400 font-semibold',
        };
    }

    const due = new Date(ticket.sla_due_at).getTime();
    const diffMs = due - currentTick.value;
    const diffMins = Math.round(diffMs / 60000);

    if (diffMins < 0) {
        return {
            label: `Trễ hạn ${formatMins(Math.abs(diffMins))}`,
            status: 'breached',
            color: 'text-rose-600 dark:text-rose-400 font-black animate-pulse',
        };
    }

    if (diffMins <= 60) {
        return {
            label: `Gần trễ hạn (${diffMins} phút)`,
            status: 'warning',
            color: 'text-amber-500 dark:text-amber-400 font-bold animate-pulse',
        };
    }

    return {
        label: `Còn ${formatMins(diffMins)}`,
        status: 'pending',
        color: 'text-slate-600 dark:text-slate-300 font-semibold',
    };
}

function formatMins(mins: number): string {
    if (mins < 60) {
        return `${mins} phút`;
    }

    const hours = Math.floor(mins / 60);
    const remainingMins = mins % 60;

    return remainingMins > 0
        ? `${hours} giờ ${remainingMins} phút`
        : `${hours} giờ`;
}
</script>

<template>
    <Head title="Hỗ trợ kỹ thuật" />

    <div class="anim-fade-in space-y-6 px-6 py-5">
        <!-- ============================================================ -->
        <!-- HEADER SECTION (Glassmorphism + Neon accents)               -->
        <!-- ============================================================ -->
        <PageHeader
            title="Hỗ trợ kỹ thuật"
            subtitle="Giám sát hạ tầng, ticket hỗ trợ, thông báo hệ thống và kho tài liệu."
            :icon="Headset"
        >
            <template #actions>
                <span
                    :class="[
                        'inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold',
                        systemHealth.bg,
                        systemHealth.color,
                    ]"
                >
                    <span :class="['size-2 rounded-full', systemHealth.dot]" />
                    {{ systemHealth.label }}
                </span>
                <Button as="a" :href="exportUrl" variant="outline">
                    <Download class="mr-2 size-4" />
                    Xuất CSV
                </Button>
                <Button variant="outline" @click="runAlertCheck">
                    <Siren class="mr-2 size-4" />
                    Quét cảnh báo
                </Button>
                <Button
                    variant="outline"
                    @click="
                        router.reload({
                            only: ['stats', 'monitoring', 'tickets', 'alerts'],
                        })
                    "
                >
                    <RefreshCcw class="mr-2 size-4" />
                    Làm mới
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <StatCard
                label="Ticket đang mở"
                :value="stats.tickets_open ?? 0"
                :icon="Ticket"
                color="sky"
            />
            <StatCard
                label="Sự cố nghiêm trọng"
                :value="stats.tickets_critical ?? 0"
                :icon="LifeBuoy"
                color="rose"
            />
            <StatCard
                label="Cảnh báo đang hoạt động"
                :value="stats.alerts_open ?? 0"
                :icon="AlertTriangle"
                color="amber"
            />
            <StatCard
                label="Thông báo trực tuyến"
                :value="stats.announcements_live ?? 0"
                :icon="Radio"
                color="violet"
            />
            <StatCard
                label="Tài liệu hướng dẫn"
                :value="stats.kb_published ?? 0"
                :icon="BookOpenText"
                color="emerald"
            />
        </div>

        <!-- ============================================================ -->
        <!-- FLOATING TABS CONTROLLER WITH PREMIUM DOCK DESIGN            -->
        <!-- ============================================================ -->
        <Tabs v-model="activeTab" class="flex w-full flex-col gap-6">
            <div class="flex justify-center sm:justify-start">
                <TabsList
                    class="flex h-auto flex-wrap rounded-2xl border border-slate-200/50 bg-slate-100 p-1.5 shadow-inner dark:border-slate-800/80 dark:bg-slate-900"
                >
                    <TabsTrigger
                        value="monitoring"
                        class="flex h-10 items-center gap-2 rounded-xl px-5 text-xs font-bold transition-all data-[state=active]:bg-white data-[state=active]:text-indigo-600 data-[state=active]:shadow-sm dark:data-[state=active]:bg-slate-800 dark:data-[state=active]:text-white"
                    >
                        <Activity class="size-4 shrink-0" />
                        <span>Hạ tầng</span>
                    </TabsTrigger>
                    <TabsTrigger
                        value="tickets"
                        class="relative flex h-10 items-center gap-2 rounded-xl px-5 text-xs font-bold transition-all data-[state=active]:bg-white data-[state=active]:text-indigo-600 data-[state=active]:shadow-sm dark:data-[state=active]:bg-slate-800 dark:data-[state=active]:text-white"
                    >
                        <Ticket class="size-4 shrink-0" />
                        <span>Hỗ trợ</span>
                        <span
                            v-if="(stats.tickets_open ?? 0) > 0"
                            class="ml-1 flex h-5 items-center justify-center rounded-full bg-rose-500 px-2 py-0.5 text-[9px] font-black text-white shadow-sm shadow-rose-500/30"
                        >
                            {{ stats.tickets_open }}
                        </span>
                    </TabsTrigger>
                    <TabsTrigger
                        value="sla"
                        class="relative flex h-10 items-center gap-2 rounded-xl px-5 text-xs font-bold transition-all data-[state=active]:bg-white data-[state=active]:text-indigo-600 data-[state=active]:shadow-sm dark:data-[state=active]:bg-slate-800 dark:data-[state=active]:text-white"
                    >
                        <Clock class="size-4 shrink-0" />
                        <span>Giám sát SLA</span>
                        <span
                            v-if="(sla_stats.sla_warning ?? 0) > 0"
                            class="ml-1 flex h-5 animate-pulse items-center justify-center rounded-full bg-amber-500 px-2 py-0.5 text-[9px] font-black text-white shadow-sm shadow-amber-500/30"
                        >
                            {{ sla_stats.sla_warning }}
                        </span>
                        <span
                            v-if="(sla_stats.sla_breached ?? 0) > 0"
                            class="ml-1 flex h-5 items-center justify-center rounded-full bg-rose-500 px-2 py-0.5 text-[9px] font-black text-white shadow-sm shadow-rose-500/30"
                        >
                            {{ sla_stats.sla_breached }}
                        </span>
                    </TabsTrigger>
                    <TabsTrigger
                        value="broadcast"
                        class="flex h-10 items-center gap-2 rounded-xl px-5 text-xs font-bold transition-all data-[state=active]:bg-white data-[state=active]:text-indigo-600 data-[state=active]:shadow-sm dark:data-[state=active]:bg-slate-800 dark:data-[state=active]:text-white"
                    >
                        <Radio class="size-4 shrink-0" />
                        <span>Thông báo trực tuyến</span>
                    </TabsTrigger>
                    <TabsTrigger
                        value="alerts"
                        class="flex h-10 items-center gap-2 rounded-xl px-5 text-xs font-bold transition-all data-[state=active]:bg-white data-[state=active]:text-indigo-600 data-[state=active]:shadow-sm dark:data-[state=active]:bg-slate-800 dark:data-[state=active]:text-white"
                    >
                        <Siren class="size-4 shrink-0" />
                        <span>Quy tắc cảnh báo</span>
                    </TabsTrigger>
                    <TabsTrigger
                        value="kb"
                        class="flex h-10 items-center gap-2 rounded-xl px-5 text-xs font-bold transition-all data-[state=active]:bg-white data-[state=active]:text-indigo-600 data-[state=active]:shadow-sm dark:data-[state=active]:bg-slate-800 dark:data-[state=active]:text-white"
                    >
                        <BookOpenText class="size-4 shrink-0" />
                        <span>Kho tài liệu</span>
                    </TabsTrigger>
                </TabsList>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 1: MONITORING (INFRASTRUCTURE MONITORING)               -->
            <!-- ============================================================ -->
            <TabsContent
                value="monitoring"
                class="anim-slide-up space-y-6 outline-none"
            >
                <!-- Metrics Grid -->
                <div
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <!-- Failed Jobs -->
                    <Card
                        class="relative overflow-hidden rounded-2xl border border-slate-100 bg-white/50 backdrop-blur-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/50"
                    >
                        <CardContent class="p-5">
                            <div class="flex items-center justify-between">
                                <p
                                    class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Công việc lỗi
                                </p>
                                <div
                                    :class="[
                                        'rounded-xl p-2',
                                        monitoring.failed_jobs > 0
                                            ? 'bg-rose-500/10 text-rose-500'
                                            : 'bg-slate-100 text-slate-400 dark:bg-slate-800',
                                    ]"
                                >
                                    <XCircle class="size-4" />
                                </div>
                            </div>
                            <p
                                :class="[
                                    'mt-3 text-3xl font-black',
                                    monitoring.failed_jobs > 0
                                        ? 'text-rose-600'
                                        : 'text-slate-800 dark:text-white',
                                ]"
                            >
                                {{ monitoring.failed_jobs }}
                            </p>
                            <p
                                class="mt-2 text-xs font-semibold text-slate-500"
                            >
                                Công việc thất bại trong hàng đợi
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Pending Jobs -->
                    <Card
                        class="rounded-2xl border border-slate-100 bg-white/50 backdrop-blur-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/50"
                    >
                        <CardContent class="p-5">
                            <div class="flex items-center justify-between">
                                <p
                                    class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Công việc đang chờ
                                </p>
                                <div
                                    class="rounded-xl bg-amber-500/10 p-2 text-amber-500"
                                >
                                    <Clock class="size-4" />
                                </div>
                            </div>
                            <p
                                class="mt-3 text-3xl font-black text-slate-800 dark:text-white"
                            >
                                {{ monitoring.pending_jobs }}
                            </p>
                            <p
                                class="mt-2 text-xs font-semibold text-slate-500"
                            >
                                Công việc đang chờ xử lý
                            </p>
                        </CardContent>
                    </Card>

                    <!-- API Error Rate with horizontal metric indicator -->
                    <Card
                        class="rounded-2xl border border-slate-100 bg-white/50 backdrop-blur-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/50"
                    >
                        <CardContent class="p-5">
                            <div class="flex items-center justify-between">
                                <p
                                    class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Tỷ lệ lỗi API
                                </p>
                                <div
                                    :class="[
                                        'rounded-xl p-2',
                                        monitoring.api_error_rate > 5
                                            ? 'bg-rose-500/10 text-rose-500'
                                            : 'bg-emerald-500/10 text-emerald-500',
                                    ]"
                                >
                                    <Globe class="size-4" />
                                </div>
                            </div>

                            <div class="mt-3 flex items-baseline gap-2">
                                <p
                                    :class="[
                                        'text-3xl font-black',
                                        monitoring.api_error_rate > 5
                                            ? 'text-rose-600'
                                            : 'text-slate-800 dark:text-white',
                                    ]"
                                >
                                    {{ monitoring.api_error_rate }}%
                                </p>
                                <span
                                    class="text-[10px] font-bold text-slate-400"
                                    >SLA &lt; 5%</span
                                >
                            </div>

                            <!-- SLA Linear Gauge -->
                            <div
                                class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                            >
                                <div
                                    :class="[
                                        'h-full rounded-full transition-all duration-500',
                                        monitoring.api_error_rate > 5
                                            ? 'bg-gradient-to-r from-red-500 to-rose-600'
                                            : 'bg-gradient-to-r from-emerald-400 to-teal-500',
                                    ]"
                                    :style="{
                                        width: `${Math.min(monitoring.api_error_rate * 10, 100)}%`,
                                    }"
                                />
                            </div>

                            <p
                                class="mt-2.5 font-mono text-[10px] font-bold text-slate-400"
                            >
                                {{ monitoring.api_error_total }}/{{
                                    monitoring.api_request_total
                                }}
                                requests
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Slow Queries -->
                    <Card
                        class="rounded-2xl border border-slate-100 bg-white/50 backdrop-blur-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/50"
                    >
                        <CardContent class="p-5">
                            <div class="flex items-center justify-between">
                                <p
                                    class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Truy vấn chậm
                                </p>
                                <div
                                    :class="[
                                        'rounded-xl p-2',
                                        monitoring.slow_queries > 10
                                            ? 'bg-amber-500/10 text-amber-500'
                                            : 'bg-slate-100 text-slate-400 dark:bg-slate-800',
                                    ]"
                                >
                                    <Database class="size-4" />
                                </div>
                            </div>
                            <p
                                :class="[
                                    'mt-3 text-3xl font-black',
                                    monitoring.slow_queries > 10
                                        ? 'text-amber-600'
                                        : 'text-slate-800 dark:text-white',
                                ]"
                            >
                                {{ monitoring.slow_queries }}
                            </p>
                            <p
                                class="mt-2 text-xs font-semibold text-slate-500"
                            >
                                Truy vấn cơ sở dữ liệu &gt; 1000ms
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Queue Backlog -->
                    <Card
                        class="rounded-2xl border border-slate-100 bg-white/50 backdrop-blur-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/50"
                    >
                        <CardContent class="p-5">
                            <div class="flex items-center justify-between">
                                <p
                                    class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Queue Backlog
                                </p>
                                <div
                                    class="rounded-xl bg-indigo-500/10 p-2 text-indigo-500"
                                >
                                    <Activity class="size-4" />
                                </div>
                            </div>
                            <p
                                class="mt-3 text-3xl font-black text-slate-800 dark:text-white"
                            >
                                {{ monitoring.queue_backlog }}
                            </p>
                            <p
                                class="mt-2 text-xs font-semibold text-slate-500"
                            >
                                Tổng số công việc tồn đọng
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Pulse Exceptions -->
                    <Card
                        class="rounded-2xl border border-slate-100 bg-white/50 backdrop-blur-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/50"
                    >
                        <CardContent class="p-5">
                            <div class="flex items-center justify-between">
                                <p
                                    class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Pulse Exceptions
                                </p>
                                <div
                                    class="rounded-xl bg-purple-500/10 p-2 text-purple-500"
                                >
                                    <Zap class="size-4" />
                                </div>
                            </div>
                            <p
                                class="mt-3 text-3xl font-black text-slate-800 dark:text-white"
                            >
                                {{ monitoring.pulse_exceptions }}
                            </p>
                            <p
                                class="mt-2 text-xs font-semibold text-slate-500"
                            >
                                Ngoại lệ phát sinh (Laravel Pulse)
                            </p>
                        </CardContent>
                    </Card>

                    <!-- CPU / RAM Hardware gauges -->
                    <Card
                        class="rounded-2xl border border-slate-100 bg-white/50 backdrop-blur-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/50"
                    >
                        <CardContent
                            class="flex h-full flex-col justify-between p-5"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <p
                                        class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                                    >
                                        CPU / RAM Usage
                                    </p>
                                    <div
                                        class="rounded-xl bg-slate-100 p-2 text-slate-400 dark:bg-slate-800"
                                    >
                                        <Cpu class="size-4" />
                                    </div>
                                </div>

                                <div class="mt-3 grid grid-cols-2 gap-4">
                                    <!-- CPU indicator -->
                                    <div class="space-y-1">
                                        <span
                                            class="text-[10px] font-black text-slate-400"
                                            >CPU</span
                                        >
                                        <p
                                            class="text-base font-black text-slate-800 dark:text-white"
                                        >
                                            {{
                                                monitoring.infra.cpu !== null
                                                    ? `${monitoring.infra.cpu}%`
                                                    : 'N/A'
                                            }}
                                        </p>
                                        <div
                                            class="h-1 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                        >
                                            <div
                                                class="h-full rounded-full bg-indigo-500"
                                                :style="{
                                                    width:
                                                        monitoring.infra.cpu !==
                                                        null
                                                            ? `${monitoring.infra.cpu}%`
                                                            : '0%',
                                                }"
                                            />
                                        </div>
                                    </div>

                                    <!-- RAM indicator -->
                                    <div class="space-y-1">
                                        <span
                                            class="text-[10px] font-black text-slate-400"
                                            >RAM</span
                                        >
                                        <p
                                            class="text-base font-black text-slate-800 dark:text-white"
                                        >
                                            {{
                                                monitoring.infra.ram !== null
                                                    ? `${monitoring.infra.ram}%`
                                                    : 'N/A'
                                            }}
                                        </p>
                                        <div
                                            class="h-1 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                        >
                                            <div
                                                class="h-full rounded-full bg-violet-500"
                                                :style="{
                                                    width:
                                                        monitoring.infra.ram !==
                                                        null
                                                            ? `${monitoring.infra.ram}%`
                                                            : '0%',
                                                }"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <p
                                class="mt-3 truncate font-mono text-[10px] font-bold text-slate-400 italic"
                            >
                                Nguồn: {{ monitoring.infra.source }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- SLA Cam kết shield indicator -->
                    <Card
                        class="group relative flex flex-col items-center justify-center overflow-hidden rounded-2xl border-0 border-t border-emerald-500/20 bg-gradient-to-br from-emerald-500/10 to-teal-500/5 p-5 text-center dark:from-emerald-950/20 dark:to-slate-900"
                    >
                        <div
                            class="absolute -right-4 -bottom-4 size-24 rounded-full bg-emerald-500/5 blur-xl"
                        />
                        <div
                            class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/20 text-emerald-500 shadow-sm shadow-emerald-500/10"
                        >
                            <CheckCircle2 class="animate-pulse-glow size-6" />
                        </div>
                        <p
                            class="text-sm font-black text-slate-800 dark:text-white"
                        >
                            SLA Cam Kết Hệ Thống
                        </p>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            API Response &lt; 2s (Đạt 99.8%)
                        </p>
                    </Card>
                </div>

                <!-- Recent Alerts list view -->
                <Card
                    class="overflow-hidden rounded-2xl border border-slate-100 bg-white/40 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-900/40"
                >
                    <CardHeader
                        class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800/80 dark:bg-slate-900/50"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-4"
                        >
                            <div>
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-black"
                                >
                                    <Siren class="size-4 text-rose-500" />
                                    Cảnh Báo Gần Đây
                                </CardTitle>
                                <CardDescription
                                    class="text-xs font-semibold text-slate-400"
                                >
                                    Các cảnh báo hệ thống kích hoạt tự động theo
                                    thời gian thực
                                </CardDescription>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                @click="activeTab = 'alerts'"
                                class="flex h-8 items-center gap-1 rounded-lg text-xs font-bold"
                            >
                                <span>Cấu hình Alerts</span>
                                <ChevronRight class="size-3.5" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="p-6">
                        <div
                            v-if="alerts.data.length"
                            class="relative space-y-4 border-l border-slate-200 pl-6 dark:border-slate-800"
                        >
                            <div
                                v-for="alert in alerts.data"
                                :key="alert.id"
                                class="relative flex flex-col justify-between gap-4 rounded-xl border border-slate-100 bg-white p-4 shadow-2xs transition-all duration-200 hover:shadow-xs md:flex-row md:items-center dark:border-slate-800 dark:bg-slate-900"
                            >
                                <!-- Circle dot marker on timeline line -->
                                <div
                                    class="absolute top-5 -left-[31px] flex h-4 w-4 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-900"
                                >
                                    <span
                                        :class="[
                                            'h-2 w-2 rounded-full',
                                            alert.status === 'open'
                                                ? 'animate-ping bg-rose-500'
                                                : 'bg-slate-300',
                                        ]"
                                    />
                                </div>

                                <div class="flex items-start gap-3">
                                    <AlertTriangle
                                        :class="[
                                            'mt-0.5 size-5 shrink-0',
                                            alert.status === 'open'
                                                ? 'text-rose-500'
                                                : 'text-slate-400',
                                        ]"
                                    />
                                    <div>
                                        <p
                                            class="text-sm font-bold text-slate-800 dark:text-white"
                                        >
                                            {{ alert.title }}
                                        </p>
                                        <div
                                            class="mt-1.5 flex flex-wrap items-center gap-2 font-mono text-xs font-semibold text-slate-500"
                                        >
                                            <span
                                                class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] dark:bg-slate-800"
                                                >{{ alert.metric_key }}</span
                                            >
                                            <span
                                                >giá trị:
                                                <strong
                                                    class="text-slate-700 dark:text-slate-300"
                                                    >{{
                                                        alert.metric_value
                                                    }}</strong
                                                ></span
                                            >
                                            <span
                                                >/ ngưỡng:
                                                <strong
                                                    class="text-slate-700 dark:text-slate-300"
                                                    >{{
                                                        alert.threshold
                                                    }}</strong
                                                ></span
                                            >
                                            <span
                                                class="text-slate-300 dark:text-slate-700"
                                                >•</span
                                            >
                                            <span
                                                class="font-sans text-[10px] font-medium"
                                                >{{ alert.triggered_at }}</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <Badge
                                        :class="[
                                            'rounded-full px-3 py-1 text-[10px] font-bold tracking-wider uppercase',
                                            statusBadge[alert.status],
                                        ]"
                                    >
                                        {{ alert.status }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center justify-center gap-2 py-12 text-center text-slate-400"
                        >
                            <div
                                class="mb-2 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500"
                            >
                                <CheckCircle2 class="size-8" />
                            </div>
                            <p
                                class="text-sm font-black text-slate-700 dark:text-slate-300"
                            >
                                Hệ thống an toàn
                            </p>
                            <p class="text-xs font-semibold text-slate-500">
                                Tất cả các chỉ số hệ thống đều nằm trong mức cam
                                kết SLA
                            </p>
                        </div>

                        <!-- Alerts pagination -->
                        <Pagination
                            as="button"
                            :links="alerts.links"
                            :current-page="alerts.current_page"
                            :last-page="alerts.last_page"
                            :total="alerts.total"
                            @navigate="goToAlertsPage"
                        />
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- ============================================================ -->
            <!-- TAB 2: TICKET SYSTEM (SUPPORT TICKETS)                       -->
            <!-- ============================================================ -->
            <TabsContent value="tickets" class="anim-slide-up outline-none">
                <div class="grid items-start gap-6 xl:grid-cols-5">
                    <!-- Form tạo ticket -->
                    <Card
                        class="overflow-hidden rounded-2xl border border-slate-100 bg-white/40 backdrop-blur-md xl:col-span-2 dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <CardHeader
                            class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/50"
                        >
                            <CardTitle
                                class="flex items-center gap-2 text-base font-black"
                            >
                                <PlusCircle class="size-5 text-indigo-500" />
                                Tạo Ticket Hỗ Trợ Gấp
                            </CardTitle>
                            <CardDescription class="text-xs font-semibold">
                                Ghi nhận sự cố, báo lỗi kỹ thuật từ đối tác nhà
                                hàng
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-6">
                            <form
                                class="space-y-4"
                                @submit.prevent="submitTicket"
                            >
                                <div class="grid gap-1.5">
                                    <Label
                                        class="text-xs font-bold text-slate-500"
                                        >Đối Tượng Nhà Hàng</Label
                                    >
                                    <Select v-model="ticketForm.restaurant_id">
                                        <SelectTrigger class="h-10 rounded-xl"
                                            ><SelectValue
                                                placeholder="Chọn nhà hàng"
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="system"
                                                >Hệ thống / Ghi nhận
                                                chung</SelectItem
                                            >
                                            <SelectItem
                                                v-for="r in restaurants"
                                                :key="r.id"
                                                :value="String(r.id)"
                                                >{{ r.name }}</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label
                                        class="text-xs font-bold text-slate-500"
                                        >Phân Loại Sự Cố</Label
                                    >
                                    <Select v-model="ticketForm.category">
                                        <SelectTrigger class="h-10 rounded-xl"
                                            ><SelectValue
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="realtime"
                                                >Realtime / Màn hình
                                                bếp</SelectItem
                                            >
                                            <SelectItem value="queue"
                                                >Queue / Job thất
                                                bại</SelectItem
                                            >
                                            <SelectItem value="billing"
                                                >Billing / Hóa đơn &amp; Gói
                                                dịch vụ</SelectItem
                                            >
                                            <SelectItem value="ui"
                                                >UI / Trải nghiệm &amp; Bố
                                                cục</SelectItem
                                            >
                                            <SelectItem value="performance"
                                                >Hiệu năng / Tốc độ phản
                                                hồi</SelectItem
                                            >
                                            <SelectItem value="other"
                                                >Các vấn đề khác</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label
                                        class="text-xs font-bold text-slate-500"
                                        >Tiêu Đề Lỗi
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <Input
                                        v-model="ticketForm.title"
                                        placeholder="Màn hình bếp chi nhánh A không cập nhật đơn hàng"
                                        class="h-10 rounded-xl"
                                    />
                                    <p
                                        v-if="ticketForm.errors.title"
                                        class="mt-1 text-xs font-semibold text-rose-500"
                                    >
                                        {{ ticketForm.errors.title }}
                                    </p>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label
                                        class="text-xs font-bold text-slate-500"
                                        >Mô Tả Chi Tiết &amp; Ảnh Hưởng
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <textarea
                                        v-model="ticketForm.description"
                                        rows="4"
                                        placeholder="Nhập thông tin lỗi chi tiết, mã nhà hàng, các bước tái hiện lỗi để lập trình viên xử lý nhanh nhất..."
                                        class="min-h-[120px] w-full rounded-xl border bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500/50 focus-visible:outline-none"
                                    />
                                    <p
                                        v-if="ticketForm.errors.description"
                                        class="mt-1 text-xs font-semibold text-rose-500"
                                    >
                                        {{ ticketForm.errors.description }}
                                    </p>
                                </div>

                                <div
                                    class="flex items-start gap-2 rounded-xl border border-indigo-500/10 bg-indigo-500/5 p-3.5"
                                >
                                    <CheckCircle2
                                        class="mt-0.5 size-4 shrink-0 text-indigo-500"
                                    />
                                    <p
                                        class="text-[11px] font-semibold text-slate-500 dark:text-slate-400"
                                    >
                                        Sau khi gửi, hệ thống tự động quét và
                                        phân cấp độ khẩn cấp:
                                        <strong
                                            >Nguy cấp / Cao / Trung bình /
                                            Thấp</strong
                                        >
                                        để bàn giao kỹ thuật viên.
                                    </p>
                                </div>

                                <Button
                                    type="submit"
                                    class="flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                                    :disabled="ticketForm.processing"
                                >
                                    <Send class="size-4" />
                                    {{
                                        ticketForm.processing
                                            ? 'Đang Khởi Tạo...'
                                            : 'Gửi Yêu Cầu Hỗ Trợ'
                                    }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Danh sách ticket -->
                    <div class="xl:col-span-3">
                        <Card
                            class="h-full overflow-hidden rounded-2xl border border-slate-100 bg-white/40 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/40"
                        >
                            <CardHeader
                                class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/50"
                            >
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-black"
                                >
                                    <Ticket class="size-5 text-indigo-500" />
                                    Danh Sách Phiếu Hỗ Trợ ({{ tickets.total }})
                                </CardTitle>
                                <CardDescription class="text-xs font-semibold">
                                    Danh sách yêu cầu xử lý từ nhà hàng, sắp xếp
                                    theo mức độ nghiêm trọng
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="p-6">
                                <!-- Bulk action bar -->
                                <div
                                    v-if="selectedTicketIds.size > 0"
                                    class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-indigo-500/20 bg-indigo-500/5 px-4 py-3"
                                >
                                    <span
                                        class="text-xs font-bold text-indigo-600 dark:text-indigo-400"
                                        >{{ selectedTicketIds.size }} đã
                                        chọn</span
                                    >
                                    <Select v-model="bulkStatus">
                                        <SelectTrigger
                                            class="h-8 w-[160px] rounded-lg text-xs font-bold"
                                            ><SelectValue
                                                placeholder="Đổi trạng thái"
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="open"
                                                >Mở</SelectItem
                                            >
                                            <SelectItem value="in_progress"
                                                >Đang xử lý</SelectItem
                                            >
                                            <SelectItem
                                                value="waiting_restaurant"
                                                >Chờ nhà hàng</SelectItem
                                            >
                                            <SelectItem value="resolved"
                                                >Đã xong</SelectItem
                                            >
                                            <SelectItem value="closed"
                                                >Đóng</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                    <Select v-model="bulkAssigned">
                                        <SelectTrigger
                                            class="h-8 w-[160px] rounded-lg text-xs font-bold"
                                            ><SelectValue
                                                placeholder="Phân công"
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="unassigned"
                                                >Bỏ phân công</SelectItem
                                            >
                                            <SelectItem
                                                v-for="s in staff"
                                                :key="s.id"
                                                :value="String(s.id)"
                                                >{{ s.name }}</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        size="sm"
                                        class="h-8 rounded-lg bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                                        @click="submitBulk"
                                        >Áp dụng</Button
                                    >
                                    <button
                                        class="ml-auto text-xs text-slate-400 hover:text-slate-600"
                                        @click="selectedTicketIds = new Set()"
                                    >
                                        Bỏ chọn
                                    </button>
                                </div>

                                <div
                                    v-if="tickets.data.length"
                                    class="max-h-[640px] space-y-4 overflow-y-auto pr-1"
                                >
                                    <!-- Select all -->
                                    <div
                                        class="mb-3 flex items-center gap-2 text-xs"
                                    >
                                        <button
                                            class="flex items-center gap-1.5 font-semibold text-slate-500 hover:text-indigo-600"
                                            @click="toggleSelectAll"
                                        >
                                            <CheckSquare2
                                                v-if="
                                                    selectedTicketIds.size ===
                                                        tickets.data.length &&
                                                    tickets.data.length > 0
                                                "
                                                class="size-4 text-indigo-600"
                                            />
                                            <Square v-else class="size-4" />
                                            Chọn tất cả
                                        </button>
                                    </div>

                                    <div
                                        v-for="ticket in tickets.data"
                                        :key="ticket.id"
                                        :class="[
                                            'rounded-xl border bg-white p-5 shadow-2xs transition-all duration-200 hover:shadow-xs dark:bg-slate-900',
                                            selectedTicketIds.has(ticket.id)
                                                ? 'border-indigo-400 dark:border-indigo-600'
                                                : 'border-slate-100 dark:border-slate-800',
                                        ]"
                                    >
                                        <div
                                            class="flex flex-wrap items-start justify-between gap-4"
                                        >
                                            <div
                                                class="flex min-w-0 flex-1 items-start gap-3"
                                            >
                                                <!-- Checkbox -->
                                                <button
                                                    class="mt-0.5 shrink-0"
                                                    @click="
                                                        toggleSelectTicket(
                                                            ticket.id,
                                                        )
                                                    "
                                                >
                                                    <CheckSquare2
                                                        v-if="
                                                            selectedTicketIds.has(
                                                                ticket.id,
                                                            )
                                                        "
                                                        class="size-4 text-indigo-600"
                                                    />
                                                    <Square
                                                        v-else
                                                        class="size-4 text-slate-400"
                                                    />
                                                </button>
                                                <div class="min-w-0 flex-1">
                                                    <div
                                                        class="mb-2 flex flex-wrap items-center gap-2"
                                                    >
                                                        <span
                                                            class="font-mono text-xs font-bold text-slate-400 dark:text-slate-500"
                                                            >{{
                                                                ticket.code
                                                            }}</span
                                                        >
                                                        <Badge
                                                            :class="[
                                                                'rounded px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase',
                                                                severityBadge[
                                                                    ticket
                                                                        .severity
                                                                ] ||
                                                                    'bg-slate-100 text-slate-600',
                                                            ]"
                                                            >{{
                                                                ticket.severity
                                                            }}</Badge
                                                        >
                                                        <Badge
                                                            :class="[
                                                                'rounded px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase',
                                                                statusBadge[
                                                                    ticket
                                                                        .status
                                                                ] ||
                                                                    'bg-slate-100 text-slate-600',
                                                            ]"
                                                            >{{
                                                                ticket.status
                                                            }}</Badge
                                                        >
                                                    </div>
                                                    <p
                                                        class="truncate text-sm font-bold text-slate-800 dark:text-white"
                                                        :title="ticket.title"
                                                    >
                                                        {{ ticket.title }}
                                                    </p>

                                                    <div
                                                        class="mt-2 flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-400"
                                                    >
                                                        <span
                                                            class="text-indigo-600 dark:text-indigo-400"
                                                            >{{
                                                                ticket.restaurant
                                                            }}</span
                                                        >
                                                        <span
                                                            class="text-slate-300 dark:text-slate-700"
                                                            >•</span
                                                        >
                                                        <span
                                                            class="rounded bg-slate-50 px-1.5 py-0.5 text-[10px] font-bold dark:bg-slate-800"
                                                            >{{
                                                                ticket.category
                                                            }}</span
                                                        >
                                                        <span
                                                            class="text-slate-300 dark:text-slate-700"
                                                            >•</span
                                                        >
                                                        <span
                                                            class="font-mono text-[10px] font-normal"
                                                            >{{
                                                                ticket.created_at
                                                            }}</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Interactive control buttons based on status -->
                                        <div
                                            class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-slate-800/80"
                                        >
                                            <Button
                                                v-if="ticket.status === 'open'"
                                                size="sm"
                                                variant="outline"
                                                class="h-8 rounded-lg border-indigo-500/20 text-xs font-bold text-indigo-600 hover:bg-indigo-50 dark:hover:bg-slate-800"
                                                @click="
                                                    updateTicket(
                                                        ticket.id,
                                                        'in_progress',
                                                    )
                                                "
                                            >
                                                Nhận Xử Lý
                                            </Button>

                                            <Button
                                                v-if="
                                                    [
                                                        'open',
                                                        'in_progress',
                                                        'waiting_restaurant',
                                                    ].includes(ticket.status)
                                                "
                                                size="sm"
                                                variant="outline"
                                                class="flex h-8 items-center gap-1 rounded-lg border-emerald-500/20 text-xs font-bold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-slate-800"
                                                @click="
                                                    updateTicket(
                                                        ticket.id,
                                                        'resolved',
                                                    )
                                                "
                                            >
                                                <Check class="size-3.5" />
                                                ✓ Đánh Dấu Đã Xong
                                            </Button>

                                            <Button
                                                v-if="
                                                    ticket.status === 'resolved'
                                                "
                                                size="sm"
                                                variant="outline"
                                                class="h-8 rounded-lg border-slate-500/20 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800"
                                                @click="
                                                    updateTicket(
                                                        ticket.id,
                                                        'closed',
                                                    )
                                                "
                                            >
                                                Đóng Ticket
                                            </Button>

                                            <Select
                                                :model-value="
                                                    ticket.assigned_to
                                                        ? String(
                                                              ticket.assigned_to,
                                                          )
                                                        : 'unassigned'
                                                "
                                                @update:model-value="
                                                    (v) =>
                                                        assignTicket(
                                                            ticket.id,
                                                            String(v),
                                                        )
                                                "
                                            >
                                                <SelectTrigger
                                                    class="h-8 w-[180px] rounded-lg text-xs font-bold"
                                                >
                                                    <SelectValue
                                                        placeholder="Phân công xử lý"
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        value="unassigned"
                                                        >Chưa phân
                                                        công</SelectItem
                                                    >
                                                    <SelectItem
                                                        v-for="s in staff"
                                                        :key="s.id"
                                                        :value="String(s.id)"
                                                        >{{
                                                            s.name
                                                        }}</SelectItem
                                                    >
                                                </SelectContent>
                                            </Select>

                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                class="ml-auto flex h-8 items-center gap-1 rounded-lg text-xs font-bold text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800"
                                                @click="
                                                    toggleTicketDetail(
                                                        ticket.id,
                                                    )
                                                "
                                            >
                                                <ChevronRight
                                                    :class="[
                                                        'size-3.5 transition-transform',
                                                        expandedTicketId ===
                                                        ticket.id
                                                            ? 'rotate-90'
                                                            : '',
                                                    ]"
                                                />
                                                {{
                                                    expandedTicketId ===
                                                    ticket.id
                                                        ? 'Ẩn phản hồi'
                                                        : `Phản hồi (${ticket.replies.length})`
                                                }}
                                            </Button>

                                            <!-- Edit + Delete ticket -->
                                            <button
                                                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-indigo-500/10 hover:text-indigo-600"
                                                title="Sửa ticket"
                                                @click="openEditTicket(ticket)"
                                            >
                                                <Pencil class="size-3.5" />
                                            </button>
                                            <button
                                                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-500/10 hover:text-rose-600"
                                                title="Xóa ticket"
                                                @click="deleteTicket(ticket)"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>

                                        <!-- Reply thread + compose -->
                                        <div
                                            v-if="
                                                expandedTicketId === ticket.id
                                            "
                                            class="mt-3 space-y-3 border-t border-slate-100 pt-3 dark:border-slate-800/80"
                                        >
                                            <div
                                                v-if="ticket.replies.length"
                                                class="max-h-64 space-y-2 overflow-y-auto pr-1"
                                            >
                                                <div
                                                    v-for="reply in ticket.replies"
                                                    :key="reply.id"
                                                    :class="[
                                                        'rounded-lg border p-3 text-xs',
                                                        reply.is_internal
                                                            ? 'border-amber-200 bg-amber-50/60 dark:border-amber-500/20 dark:bg-amber-500/5'
                                                            : 'border-slate-100 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/40',
                                                    ]"
                                                >
                                                    <div
                                                        class="mb-1 flex items-center justify-between gap-2"
                                                    >
                                                        <span
                                                            class="font-bold text-slate-700 dark:text-slate-200"
                                                            >{{
                                                                reply.user_name
                                                            }}</span
                                                        >
                                                        <div
                                                            class="flex items-center gap-2"
                                                        >
                                                            <Badge
                                                                v-if="
                                                                    reply.is_internal
                                                                "
                                                                class="rounded border border-amber-500/20 bg-amber-500/10 px-1.5 py-0 text-[9px] font-bold text-amber-600 uppercase"
                                                                >Ghi chú nội
                                                                bộ</Badge
                                                            >
                                                            <span
                                                                class="font-mono text-[10px] text-slate-400"
                                                                >{{
                                                                    reply.created_at
                                                                }}</span
                                                            >
                                                            <button
                                                                class="rounded p-0.5 text-slate-300 transition hover:text-rose-500"
                                                                title="Xóa phản hồi"
                                                                @click="
                                                                    deleteReply(
                                                                        ticket.id,
                                                                        reply.id,
                                                                    )
                                                                "
                                                            >
                                                                <Trash2
                                                                    class="size-3"
                                                                />
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <p
                                                        class="whitespace-pre-wrap text-slate-600 dark:text-slate-300"
                                                    >
                                                        {{ reply.message }}
                                                    </p>
                                                </div>
                                            </div>
                                            <p
                                                v-else
                                                class="text-xs font-semibold text-slate-400"
                                            >
                                                Chưa có phản hồi nào cho ticket
                                                này.
                                            </p>

                                            <form
                                                class="space-y-2"
                                                @submit.prevent="
                                                    submitReply(ticket.id)
                                                "
                                            >
                                                <textarea
                                                    v-model="
                                                        replyForm(ticket.id)
                                                            .message
                                                    "
                                                    rows="2"
                                                    placeholder="Nhập nội dung phản hồi cho nhà hàng hoặc ghi chú nội bộ..."
                                                    class="min-h-[64px] w-full rounded-xl border bg-background px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-indigo-500/50 focus-visible:outline-none"
                                                />
                                                <p
                                                    v-if="
                                                        replyForm(ticket.id)
                                                            .errors.message
                                                    "
                                                    class="text-xs font-semibold text-rose-500"
                                                >
                                                    {{
                                                        replyForm(ticket.id)
                                                            .errors.message
                                                    }}
                                                </p>
                                                <div
                                                    class="flex items-center justify-between gap-2"
                                                >
                                                    <label
                                                        class="flex cursor-pointer items-center gap-1.5 text-[11px] font-semibold text-slate-500"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            v-model="
                                                                replyForm(
                                                                    ticket.id,
                                                                ).is_internal
                                                            "
                                                            class="rounded border-slate-300"
                                                        />
                                                        Ghi chú nội bộ (không
                                                        gửi cho nhà hàng)
                                                    </label>
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        class="flex h-8 items-center gap-1.5 rounded-lg bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                                                        :disabled="
                                                            replyForm(ticket.id)
                                                                .processing ||
                                                            !replyForm(
                                                                ticket.id,
                                                            ).message.trim()
                                                        "
                                                    >
                                                        <Send
                                                            class="size-3.5"
                                                        />
                                                        Gửi phản hồi
                                                    </Button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="flex flex-col items-center justify-center gap-2 py-16 text-center text-slate-400"
                                >
                                    <div
                                        class="mb-2 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-300 dark:bg-slate-800"
                                    >
                                        <Ticket class="size-8" />
                                    </div>
                                    <p
                                        class="text-sm font-black text-slate-700 dark:text-slate-300"
                                    >
                                        Không có ticket nào
                                    </p>
                                    <p
                                        class="text-xs font-semibold text-slate-500"
                                    >
                                        Chưa ghi nhận sự cố hỗ trợ nào của nhà
                                        hàng.
                                    </p>
                                </div>

                                <!-- Tickets pagination -->
                                <Pagination
                                    as="button"
                                    :links="tickets.links"
                                    :current-page="tickets.current_page"
                                    :last-page="tickets.last_page"
                                    :total="tickets.total"
                                    @navigate="goToTicketsPage"
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </TabsContent>

            <!-- ============================================================ -->
            <!-- TAB: SLA MONITORING                                          -->
            <!-- ============================================================ -->
            <TabsContent
                value="sla"
                class="anim-slide-up space-y-6 outline-none"
            >
                <!-- SLA KPI Stats Cards -->
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <!-- Fulfill Rate -->
                    <Card
                        class="group relative overflow-hidden rounded-2xl border-0 border-t border-emerald-500/20 bg-gradient-to-br from-emerald-500/5 to-teal-500/10 transition-all duration-300 hover:shadow-lg"
                    >
                        <div
                            class="absolute -right-4 -bottom-4 size-24 rounded-full bg-emerald-500/10 blur-xl"
                        />
                        <CardContent
                            class="relative z-10 flex items-center justify-between p-5"
                        >
                            <div>
                                <p
                                    class="text-xs font-bold tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                                >
                                    Tỉ lệ đạt SLA
                                </p>
                                <p
                                    class="mt-2 text-3xl font-black text-slate-800 dark:text-white"
                                >
                                    {{ sla_stats.sla_fulfill_rate }}%
                                </p>
                            </div>
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300"
                            >
                                <CheckCircle2 class="size-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Warning Count -->
                    <Card
                        class="group relative overflow-hidden rounded-2xl border-0 border-t border-amber-500/20 bg-gradient-to-br from-amber-500/5 to-orange-500/10 transition-all duration-300 hover:shadow-lg"
                    >
                        <div
                            class="absolute -right-4 -bottom-4 size-24 rounded-full bg-amber-500/10 blur-xl"
                        />
                        <CardContent
                            class="relative z-10 flex items-center justify-between p-5"
                        >
                            <div>
                                <p
                                    class="text-xs font-bold tracking-wider text-amber-600 uppercase dark:text-amber-400"
                                >
                                    Cảnh báo SLA
                                </p>
                                <p
                                    class="mt-2 text-3xl font-black text-amber-600 dark:text-amber-400"
                                >
                                    {{ sla_stats.sla_warning }}
                                </p>
                            </div>
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300"
                            >
                                <Clock class="size-5 animate-pulse" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Breached Count -->
                    <Card
                        class="group relative overflow-hidden rounded-2xl border-0 border-t border-rose-500/20 bg-gradient-to-br from-rose-500/5 to-red-500/10 transition-all duration-300 hover:shadow-lg"
                    >
                        <div
                            class="absolute -right-4 -bottom-4 size-24 rounded-full bg-rose-500/10 blur-xl"
                        />
                        <CardContent
                            class="relative z-10 flex items-center justify-between p-5"
                        >
                            <div>
                                <p
                                    class="text-xs font-bold tracking-wider text-rose-600 uppercase dark:text-rose-400"
                                >
                                    Ticket Trễ Hạn (SLA Breach)
                                </p>
                                <p
                                    class="mt-2 text-3xl font-black text-rose-600 dark:text-rose-400"
                                >
                                    {{ sla_stats.sla_breached }}
                                </p>
                            </div>
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300"
                            >
                                <AlertTriangle
                                    class="animate-bounce-gentle size-5"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Total Checked -->
                    <Card
                        class="group relative overflow-hidden rounded-2xl border-0 border-t border-indigo-500/20 bg-gradient-to-br from-indigo-500/5 to-purple-500/10 transition-all duration-300 hover:shadow-lg"
                    >
                        <div
                            class="absolute -right-4 -bottom-4 size-24 rounded-full bg-indigo-500/10 blur-xl"
                        />
                        <CardContent
                            class="relative z-10 flex items-center justify-between p-5"
                        >
                            <div>
                                <p
                                    class="text-xs font-bold tracking-wider text-indigo-600 uppercase dark:text-indigo-400"
                                >
                                    Tổng ticket kiểm tra
                                </p>
                                <p
                                    class="mt-2 text-3xl font-black text-slate-800 dark:text-white"
                                >
                                    {{ sla_stats.total_checked }}
                                </p>
                            </div>
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300"
                            >
                                <Ticket class="size-5" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Main SLA Content layout -->
                <div class="grid items-start gap-6 xl:grid-cols-4">
                    <!-- Left column: SLA Rules Configuration Sidecard -->
                    <Card
                        class="overflow-hidden rounded-2xl border border-slate-100 bg-white/40 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <CardHeader
                            class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/50"
                        >
                            <CardTitle
                                class="flex items-center gap-2 text-base font-black"
                            >
                                <Clock class="size-5 text-indigo-500" />
                                Quy Định SLA Cấp Gói
                            </CardTitle>
                            <CardDescription
                                class="text-xs font-semibold text-slate-400"
                            >
                                Cam kết thời gian phản hồi đầu tiên (First
                                Response SLA)
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4 p-5">
                            <div class="flex flex-col gap-3.5">
                                <!-- Enterprise -->
                                <div
                                    class="flex flex-col gap-1 rounded-xl border border-rose-500/10 bg-rose-500/5 p-3 dark:bg-rose-950/10"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <p
                                            class="text-xs font-black text-rose-700 dark:text-rose-400"
                                        >
                                            Doanh Nghiệp (Enterprise)
                                        </p>
                                        <Badge
                                            class="bg-rose-500 text-xs font-bold text-white hover:bg-rose-600"
                                            >2 Giờ</Badge
                                        >
                                    </div>
                                    <p
                                        class="mt-0.5 text-[10px] font-semibold text-slate-500"
                                    >
                                        Tự động Leo thang cảnh báo khẩn sau 1
                                        giờ
                                    </p>
                                </div>
                                <!-- Pro -->
                                <div
                                    class="flex flex-col gap-1 rounded-xl border border-amber-500/10 bg-amber-500/5 p-3 dark:bg-amber-950/10"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <p
                                            class="text-xs font-black text-amber-700 dark:text-amber-400"
                                        >
                                            Chuyên Nghiệp (Pro)
                                        </p>
                                        <Badge
                                            class="bg-amber-500 text-xs font-bold text-white hover:bg-amber-600"
                                            >12 Giờ</Badge
                                        >
                                    </div>
                                    <p
                                        class="mt-0.5 text-[10px] font-semibold text-slate-500"
                                    >
                                        Hỗ trợ nhanh ưu tiên phản hồi tiêu chuẩn
                                    </p>
                                </div>
                                <!-- Starter -->
                                <div
                                    class="flex flex-col gap-1 rounded-xl border border-indigo-500/10 bg-indigo-500/5 p-3 dark:bg-indigo-950/10"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <p
                                            class="text-xs font-black text-indigo-700 dark:text-indigo-400"
                                        >
                                            Cơ Bản (Starter)
                                        </p>
                                        <Badge
                                            class="bg-indigo-500 text-xs font-bold text-white hover:bg-indigo-600"
                                            >24 Giờ</Badge
                                        >
                                    </div>
                                    <p
                                        class="mt-0.5 text-[10px] font-semibold text-slate-500"
                                    >
                                        Phản hồi tiêu chuẩn thông thường
                                    </p>
                                </div>
                                <!-- Free -->
                                <div
                                    class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <p
                                            class="text-xs font-black text-slate-700 dark:text-slate-300"
                                        >
                                            Miễn Phí (Free)
                                        </p>
                                        <Badge
                                            class="bg-slate-500 text-xs font-bold text-white hover:bg-slate-600"
                                            >48 Giờ</Badge
                                        >
                                    </div>
                                    <p
                                        class="mt-0.5 text-[10px] font-semibold text-slate-500"
                                    >
                                        Hỗ trợ cộng đồng &amp; tài liệu HD
                                    </p>
                                </div>
                            </div>

                            <div
                                class="border-t border-slate-100 pt-4 dark:border-slate-800"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="flex h-10 w-full items-center justify-center gap-2 rounded-xl border-indigo-500/20 font-bold text-indigo-600 hover:bg-indigo-500/5"
                                    :disabled="isRecalculatingSla"
                                    @click="recalculateSla"
                                >
                                    <RefreshCcw
                                        :class="[
                                            'size-4',
                                            isRecalculatingSla &&
                                                'animate-spin',
                                        ]"
                                    />
                                    <span>Tính Toán Lại SLA</span>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Right column: Real-time SLA Table -->
                    <Card
                        class="overflow-hidden rounded-2xl border border-slate-100 bg-white/40 backdrop-blur-md xl:col-span-3 dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <CardHeader
                            class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/50"
                        >
                            <CardTitle
                                class="flex items-center gap-2 text-base font-black"
                            >
                                <Activity
                                    class="size-5 animate-pulse text-indigo-500"
                                />
                                Bảng Theo Dõi SLA Thời Gian Thực
                            </CardTitle>
                            <CardDescription
                                class="text-xs font-semibold text-slate-400"
                            >
                                Danh sách các ticket hỗ trợ kèm thời gian đếm
                                ngược cam kết phản hồi
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse text-left">
                                    <thead>
                                        <tr
                                            class="border-b border-slate-100 bg-slate-50/20 text-xs font-bold text-slate-500 dark:border-slate-800 dark:bg-slate-900/10"
                                        >
                                            <th class="p-4">Mã</th>
                                            <th class="p-4">Nhà Hàng</th>
                                            <th class="p-4">Gói dịch vụ</th>
                                            <th class="p-4">Tiêu Đề</th>
                                            <th class="p-4">
                                                Thời Gian Còn Lại
                                            </th>
                                            <th class="p-4 text-center">
                                                Leo Thang
                                            </th>
                                            <th class="p-4 text-right">
                                                Hành Động
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-slate-100 dark:divide-slate-800"
                                    >
                                        <tr
                                            v-for="ticket in tickets.data.filter(
                                                (t) => t.sla_due_at !== null,
                                            )"
                                            :key="ticket.id"
                                            class="animate-fade-in text-xs font-medium transition-all duration-200 hover:bg-slate-50/30 dark:hover:bg-slate-900/20"
                                        >
                                            <td class="p-4 font-mono font-bold">
                                                {{ ticket.code }}
                                            </td>
                                            <td class="p-4">
                                                <div
                                                    class="font-bold text-slate-800 dark:text-slate-200"
                                                >
                                                    {{ ticket.restaurant }}
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <Badge
                                                    :class="[
                                                        'rounded-full px-2 py-0.5 text-[9px] font-black tracking-wider uppercase',
                                                        ticket.restaurant_plan_code ===
                                                        'enterprise'
                                                            ? 'border border-rose-500/20 bg-rose-500/10 text-rose-600 dark:text-rose-400'
                                                            : ticket.restaurant_plan_code ===
                                                                'pro'
                                                              ? 'border border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                              : ticket.restaurant_plan_code ===
                                                                  'starter'
                                                                ? 'border border-indigo-500/20 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400'
                                                                : 'border border-slate-500/20 bg-slate-500/10 text-slate-600 dark:text-slate-400',
                                                    ]"
                                                >
                                                    {{ ticket.restaurant_plan }}
                                                </Badge>
                                            </td>
                                            <td class="p-4">
                                                <div
                                                    class="max-w-[200px] truncate font-bold text-slate-700 dark:text-slate-300"
                                                    :title="ticket.title"
                                                >
                                                    {{ ticket.title }}
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <div
                                                    :class="
                                                        getSlaRemainingTime(
                                                            ticket,
                                                        ).color
                                                    "
                                                >
                                                    {{
                                                        getSlaRemainingTime(
                                                            ticket,
                                                        ).label
                                                    }}
                                                </div>
                                            </td>
                                            <td class="p-4 text-center">
                                                <Badge
                                                    v-if="ticket.escalated_at"
                                                    class="rounded-full border border-rose-500/20 bg-rose-500/10 text-[9px] font-black tracking-wide text-rose-600"
                                                    title="Đã kích hoạt cảnh báo leo thang khẩn cấp"
                                                >
                                                    ĐÃ LEO THANG
                                                </Badge>
                                                <span
                                                    v-else
                                                    class="text-[10px] text-slate-400"
                                                    >Chưa</span
                                                >
                                            </td>
                                            <td class="p-4 text-right">
                                                <div
                                                    class="flex items-center justify-end gap-2"
                                                >
                                                    <!-- Manual Escalation Trigger for Enterprise -->
                                                    <Button
                                                        v-if="
                                                            ticket.restaurant_plan_code ===
                                                                'enterprise' &&
                                                            !ticket.first_response_at &&
                                                            !ticket.escalated_at
                                                        "
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-7 rounded-lg border border-rose-500/20 px-2.5 font-bold text-rose-600 hover:bg-rose-500/5"
                                                        :disabled="
                                                            isEscalatingTicket ===
                                                            ticket.id
                                                        "
                                                        @click="
                                                            escalateTicket(
                                                                ticket.id,
                                                            )
                                                        "
                                                    >
                                                        <Zap
                                                            class="size-3 shrink-0"
                                                        />
                                                        <span>Leo thang</span>
                                                    </Button>

                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-7 rounded-lg border border-indigo-500/20 px-2.5 font-bold text-indigo-600 hover:bg-indigo-500/5"
                                                        @click="
                                                            activeTab =
                                                                'tickets';
                                                            toggleTicketDetail(
                                                                ticket.id,
                                                            );
                                                        "
                                                    >
                                                        <Eye
                                                            class="size-3 shrink-0"
                                                        />
                                                        <span
                                                            >Xem chi tiết</span
                                                        >
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr
                                            v-if="
                                                tickets.data.filter(
                                                    (t) =>
                                                        t.sla_due_at !== null,
                                                ).length === 0
                                            "
                                        >
                                            <td
                                                colspan="7"
                                                class="p-8 text-center text-slate-400"
                                            >
                                                Không có dữ liệu SLA nào.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </TabsContent>

            <!-- ============================================================ -->
            <!-- TAB 3: BROADCAST PORTAL                                      -->
            <!-- ============================================================ -->
            <TabsContent value="broadcast" class="anim-slide-up outline-none">
                <div class="grid items-start gap-6 xl:grid-cols-5">
                    <!-- Form tạo thông báo -->
                    <Card
                        class="overflow-hidden rounded-2xl border border-slate-100 bg-white/40 backdrop-blur-md xl:col-span-2 dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <CardHeader
                            class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/50"
                        >
                            <CardTitle
                                class="flex items-center gap-2 text-base font-black"
                            >
                                <BellRing
                                    class="size-5 animate-pulse text-violet-500"
                                />
                                Phát Thông Báo Realtime
                            </CardTitle>
                            <CardDescription class="text-xs font-semibold">
                                Gửi thông báo khẩn cấp đến toàn bộ thu ngân, chủ
                                nhà bếp qua Reverb
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-6">
                            <form
                                class="space-y-4"
                                @submit.prevent="submitAnnouncement"
                            >
                                <div class="grid gap-1.5">
                                    <Label
                                        class="text-xs font-bold text-slate-500"
                                        >Tiêu Đề Thông Báo</Label
                                    >
                                    <Input
                                        v-model="announcementForm.title"
                                        placeholder="Bảo trì cụm máy chủ Kitchen từ 23:00 - 24:00"
                                        class="h-10 rounded-xl"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label
                                        class="text-xs font-bold text-slate-500"
                                        >Nội Dung Chi Tiết</Label
                                    >
                                    <textarea
                                        v-model="announcementForm.message"
                                        rows="4"
                                        placeholder="Nhập nội dung hiển thị trên popup của màn hình bếp và thu ngân..."
                                        class="min-h-[120px] w-full rounded-xl border bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500/50 focus-visible:outline-none"
                                    />
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-1.5">
                                        <Label
                                            class="text-xs font-bold text-slate-500"
                                            >Đối Tượng Nhận</Label
                                        >
                                        <Select
                                            v-model="announcementForm.audience"
                                        >
                                            <SelectTrigger
                                                class="h-10 rounded-xl"
                                                ><SelectValue
                                            /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all"
                                                    >Tất cả người
                                                    dùng</SelectItem
                                                >
                                                <SelectItem value="cashier"
                                                    >Thu ngân
                                                    (Cashier)</SelectItem
                                                >
                                                <SelectItem value="owner"
                                                    >Chủ nhà hàng
                                                    (Owners)</SelectItem
                                                >
                                                <SelectItem value="kitchen"
                                                    >Màn hình bếp
                                                    (Kitchens)</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label
                                            class="text-xs font-bold text-slate-500"
                                            >Mức Độ Cảnh Báo</Label
                                        >
                                        <Select
                                            v-model="announcementForm.level"
                                        >
                                            <SelectTrigger
                                                class="h-10 rounded-xl"
                                                ><SelectValue
                                            /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="info"
                                                    >ℹ Info / Thông tin
                                                    chung</SelectItem
                                                >
                                                <SelectItem value="warning"
                                                    >⚠️ Warning / Lưu ý hệ
                                                    thống</SelectItem
                                                >
                                                <SelectItem value="critical"
                                                    >🚨 Critical / Khẩn
                                                    cấp</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center gap-3 rounded-xl border border-violet-500/20 bg-violet-500/5 p-4 transition-all duration-300"
                                >
                                    <input
                                        id="publish_now"
                                        v-model="announcementForm.publish_now"
                                        type="checkbox"
                                        class="size-4.5 rounded border-violet-300 accent-violet-600"
                                    />
                                    <label
                                        for="publish_now"
                                        class="cursor-pointer text-xs font-bold text-violet-950 select-none dark:text-violet-300"
                                    >
                                        Phát sóng ngay lập tức qua WebSocket
                                        (Laravel Reverb)
                                    </label>
                                </div>

                                <Button
                                    type="submit"
                                    class="flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-violet-600 font-bold text-white hover:bg-violet-700"
                                    :disabled="announcementForm.processing"
                                >
                                    <Radio class="size-4" />
                                    {{
                                        announcementForm.processing
                                            ? 'Đang Phát Sóng...'
                                            : 'Lưu & Broadcast Ngay'
                                    }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Lịch sử thông báo -->
                    <div class="xl:col-span-3">
                        <Card
                            class="h-full overflow-hidden rounded-2xl border border-slate-100 bg-white/40 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/40"
                        >
                            <CardHeader
                                class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/50"
                            >
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-black"
                                >
                                    <MonitorSpeaker
                                        class="size-5 text-indigo-500"
                                    />
                                    Lịch Sử Phát Sóng Gần Đây
                                </CardTitle>
                                <CardDescription class="text-xs font-semibold">
                                    Danh sách các thông báo đã được gửi qua
                                    WebSocket
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="p-6">
                                <div
                                    v-if="announcements.data.length"
                                    class="max-h-[640px] space-y-4 overflow-y-auto pr-1"
                                >
                                    <div
                                        v-for="a in announcements.data"
                                        :key="a.id"
                                        class="rounded-xl border border-slate-100 bg-white p-5 shadow-2xs transition-all duration-200 hover:shadow-xs dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <div
                                            class="flex items-start justify-between gap-4"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="mb-2 flex flex-wrap items-center gap-2"
                                                >
                                                    <span
                                                        v-if="
                                                            a.level ===
                                                            'critical'
                                                        "
                                                        class="text-base"
                                                        >🚨 Khẩn cấp</span
                                                    >
                                                    <span
                                                        v-else-if="
                                                            a.level ===
                                                            'warning'
                                                        "
                                                        class="text-base"
                                                        >⚠️ Cảnh báo</span
                                                    >
                                                    <span
                                                        v-else
                                                        class="text-base"
                                                        >ℹ️ Thông tin</span
                                                    >

                                                    <span
                                                        class="text-slate-300 dark:text-slate-700"
                                                        >•</span
                                                    >
                                                    <span
                                                        class="text-xs font-bold text-slate-400"
                                                        >Gửi đến:
                                                        {{ a.audience }}</span
                                                    >
                                                </div>
                                                <p
                                                    class="text-sm font-bold text-slate-800 dark:text-white"
                                                >
                                                    {{ a.title }}
                                                </p>
                                                <p
                                                    class="mt-2 text-xs leading-relaxed font-semibold text-slate-500"
                                                >
                                                    {{ a.message }}
                                                </p>

                                                <p
                                                    class="mt-3 font-mono text-[10px] text-slate-400"
                                                >
                                                    Thời điểm:
                                                    {{
                                                        a.published_at ??
                                                        'Chưa phát sóng'
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                class="flex shrink-0 flex-col items-end gap-2"
                                            >
                                                <Badge
                                                    :class="[
                                                        'rounded-full px-3 py-1 text-[10px] font-bold tracking-wider uppercase',
                                                        statusBadge[
                                                            String(a.status)
                                                        ] ||
                                                            'bg-slate-100 text-slate-600',
                                                    ]"
                                                >
                                                    {{ a.status }}
                                                </Badge>
                                                <button
                                                    v-if="
                                                        a.status === 'published'
                                                    "
                                                    class="rounded-lg px-2 py-1 text-[10px] font-bold text-rose-500 transition hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-500/10"
                                                    @click="
                                                        unpublishAnnouncement(
                                                            a.id,
                                                        )
                                                    "
                                                >
                                                    Gỡ đăng
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="flex flex-col items-center justify-center gap-2 py-16 text-center text-slate-400"
                                >
                                    <div
                                        class="mb-2 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-300 dark:bg-slate-800"
                                    >
                                        <MonitorSpeaker class="size-8" />
                                    </div>
                                    <p
                                        class="text-sm font-black text-slate-700 dark:text-slate-300"
                                    >
                                        Chưa có thông báo nào
                                    </p>
                                    <p
                                        class="text-xs font-semibold text-slate-500"
                                    >
                                        Tạo thông báo bên trái để phát sóng
                                        realtime qua Laravel Reverb.
                                    </p>
                                </div>

                                <!-- Announcements pagination -->
                                <Pagination
                                    as="button"
                                    :links="announcements.links"
                                    :current-page="announcements.current_page"
                                    :last-page="announcements.last_page"
                                    :total="announcements.total"
                                    @navigate="goToAnnouncementsPage"
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </TabsContent>

            <!-- ============================================================ -->
            <!-- TAB 4: ALERT RULES (THRESHOLD CONTROLS)                      -->
            <!-- ============================================================ -->
            <TabsContent value="alerts" class="anim-slide-up outline-none">
                <div class="grid items-start gap-6 xl:grid-cols-5">
                    <!-- Form tạo rule -->
                    <Card
                        class="overflow-hidden rounded-2xl border border-slate-100 bg-white/40 backdrop-blur-md xl:col-span-2 dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <CardHeader
                            class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/50"
                        >
                            <CardTitle
                                class="flex items-center gap-2 text-base font-black"
                            >
                                <Siren
                                    class="size-5 animate-pulse text-amber-500"
                                />
                                Cấu Hình Ngưỡng Giám Sát
                            </CardTitle>
                            <CardDescription class="text-xs font-semibold">
                                Đặt luật cảnh báo tự động gửi về kênh
                                Telegram/Discord của DevOps
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-6">
                            <form
                                class="space-y-4"
                                @submit.prevent="submitRule"
                            >
                                <div class="grid gap-1.5">
                                    <Label
                                        class="text-xs font-bold text-slate-500"
                                        >Tên Rule Giám Sát</Label
                                    >
                                    <Input
                                        v-model="ruleForm.name"
                                        placeholder="API Error Rate > 5%"
                                        class="h-10 rounded-xl"
                                    />
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-1.5">
                                        <Label
                                            class="text-xs font-bold text-slate-500"
                                            >Chọn Chỉ Số Metric</Label
                                        >
                                        <Select v-model="ruleForm.metric_key">
                                            <SelectTrigger
                                                class="h-10 rounded-xl"
                                                ><SelectValue
                                            /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    value="api_error_rate"
                                                    >api_error_rate
                                                    (%)</SelectItem
                                                >
                                                <SelectItem value="slow_queries"
                                                    >slow_queries
                                                    (lượt)</SelectItem
                                                >
                                                <SelectItem value="failed_jobs"
                                                    >failed_jobs
                                                    (lượt)</SelectItem
                                                >
                                                <SelectItem
                                                    value="queue_backlog"
                                                    >queue_backlog
                                                    (lượt)</SelectItem
                                                >
                                                <SelectItem
                                                    value="pulse_exceptions"
                                                    >pulse_exceptions
                                                    (lượt)</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label
                                            class="text-xs font-bold text-slate-500"
                                            >Phép Toán So Sánh</Label
                                        >
                                        <Select v-model="ruleForm.operator">
                                            <SelectTrigger
                                                class="h-10 rounded-xl"
                                                ><SelectValue
                                            /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value=">"
                                                    >&gt; lớn hơn</SelectItem
                                                >
                                                <SelectItem value=">="
                                                    >&gt;= lớn hơn hoặc
                                                    bằng</SelectItem
                                                >
                                                <SelectItem value="<"
                                                    >&lt; nhỏ hơn</SelectItem
                                                >
                                                <SelectItem value="<="
                                                    >&lt;= nhỏ hơn hoặc
                                                    bằng</SelectItem
                                                >
                                                <SelectItem value="="
                                                    >= bằng</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-1.5">
                                        <Label
                                            class="text-xs font-bold text-slate-500"
                                            >Giá Trị Ngưỡng (Threshold)</Label
                                        >
                                        <Input
                                            v-model="ruleForm.threshold"
                                            type="number"
                                            step="0.01"
                                            class="h-10 rounded-xl"
                                        />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label
                                            class="text-xs font-bold text-slate-500"
                                            >Thời Gian Nghỉ (Cooldown -
                                            phút)</Label
                                        >
                                        <Input
                                            v-model="ruleForm.cooldown_minutes"
                                            type="number"
                                            min="1"
                                            class="h-10 rounded-xl"
                                        />
                                    </div>
                                </div>
                                <Button
                                    type="submit"
                                    class="flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                                    :disabled="ruleForm.processing"
                                >
                                    <PlusCircle class="size-4" />
                                    {{
                                        ruleForm.processing
                                            ? 'Đang Đăng Ký...'
                                            : 'Kích Hoạt Alert Rule'
                                    }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Danh sách rules -->
                    <div class="xl:col-span-3">
                        <Card
                            class="h-full overflow-hidden rounded-2xl border border-slate-100 bg-white/40 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/40"
                        >
                            <CardHeader
                                class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/50"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-4"
                                >
                                    <div>
                                        <CardTitle
                                            class="flex items-center gap-2 text-base font-black"
                                        >
                                            <Siren
                                                class="size-5 text-indigo-500"
                                            />
                                            Các Bộ Chỉ Số Cảnh Báo ({{
                                                rules.total
                                            }})
                                        </CardTitle>
                                        <CardDescription
                                            class="text-xs font-semibold"
                                        >
                                            Các ngưỡng giám sát tự động kích
                                            hoạt cảnh báo thông tin
                                        </CardDescription>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="runAlertCheck"
                                        class="flex h-8 items-center gap-1.5 rounded-lg border-amber-500/20 text-xs font-bold text-amber-600 hover:bg-amber-500/5"
                                    >
                                        <RefreshCcw class="size-3.5" />
                                        Chạy Quét Ngay
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent class="p-6">
                                <div
                                    v-if="rules.data.length"
                                    class="max-h-[640px] space-y-3 overflow-y-auto pr-1"
                                >
                                    <div
                                        v-for="rule in rules.data"
                                        :key="rule.id"
                                        class="flex items-center justify-between rounded-xl border border-slate-100 bg-white px-5 py-4 shadow-2xs transition-all duration-200 hover:shadow-xs dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div
                                                :class="[
                                                    'size-3 shrink-0 rounded-full',
                                                    rule.is_active
                                                        ? 'animate-pulse bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]'
                                                        : 'bg-slate-300 dark:bg-slate-700',
                                                ]"
                                            />
                                            <div>
                                                <p
                                                    class="text-sm font-bold text-slate-800 dark:text-white"
                                                >
                                                    {{ rule.name }}
                                                </p>
                                                <p
                                                    class="mt-1 font-mono text-xs text-slate-400"
                                                >
                                                    {{ rule.metric_key }}
                                                    <strong
                                                        class="text-indigo-600 dark:text-indigo-400"
                                                        >{{
                                                            rule.operator
                                                        }}</strong
                                                    >
                                                    {{ rule.threshold }}
                                                    <span
                                                        class="mx-1.5 text-slate-300 dark:text-slate-700"
                                                        >•</span
                                                    >
                                                    cooldown:
                                                    {{ rule.cooldown_minutes }}
                                                    phút
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <!-- Kênh thông báo -->
                                            <div
                                                class="flex items-center gap-1"
                                            >
                                                <span
                                                    v-for="ch in rule.channels ??
                                                    []"
                                                    :key="ch"
                                                    class="rounded-full bg-slate-50 px-2.5 py-0.5 text-[9px] font-bold text-slate-500 uppercase dark:bg-slate-800"
                                                >
                                                    {{ ch }}
                                                </span>
                                            </div>

                                            <Badge
                                                :class="[
                                                    rule.is_active
                                                        ? 'border border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                                        : 'border border-slate-200 bg-slate-100 text-slate-500',
                                                ]"
                                            >
                                                {{
                                                    rule.is_active
                                                        ? 'Đang giám sát'
                                                        : 'Tạm dừng'
                                                }}
                                            </Badge>

                                            <!-- Rule actions -->
                                            <button
                                                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-indigo-500/10 hover:text-indigo-600"
                                                title="Sửa rule"
                                                @click="openEditRule(rule)"
                                            >
                                                <Pencil class="size-3.5" />
                                            </button>
                                            <button
                                                :class="[
                                                    'rounded-lg p-1.5 transition',
                                                    rule.is_active
                                                        ? 'text-emerald-500 hover:bg-emerald-500/10'
                                                        : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800',
                                                ]"
                                                :title="
                                                    rule.is_active
                                                        ? 'Tạm dừng rule'
                                                        : 'Kích hoạt rule'
                                                "
                                                @click="toggleRule(rule)"
                                            >
                                                <Activity class="size-3.5" />
                                            </button>
                                            <button
                                                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-500/10 hover:text-rose-600"
                                                title="Xóa rule"
                                                @click="deleteRule(rule)"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="flex flex-col items-center justify-center gap-2 py-16 text-center text-slate-400"
                                >
                                    <div
                                        class="mb-2 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-300 dark:bg-slate-800"
                                    >
                                        <Siren class="size-8" />
                                    </div>
                                    <p
                                        class="text-sm font-black text-slate-700 dark:text-slate-300"
                                    >
                                        Không có rule cảnh báo
                                    </p>
                                    <p
                                        class="text-xs font-semibold text-slate-500"
                                    >
                                        Tạo rule cảnh báo bên trái để hệ thống
                                        tự giám sát hạ tầng.
                                    </p>
                                </div>

                                <!-- Rules pagination -->
                                <Pagination
                                    as="button"
                                    :links="rules.links"
                                    :current-page="rules.current_page"
                                    :last-page="rules.last_page"
                                    :total="rules.total"
                                    @navigate="goToRulesPage"
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </TabsContent>

            <!-- ============================================================ -->
            <!-- TAB 5: KNOWLEDGE BASE (SELF-SERVICE SYSTEM)                 -->
            <!-- ============================================================ -->
            <TabsContent value="kb" class="anim-slide-up outline-none">
                <div class="grid items-start gap-6 xl:grid-cols-5">
                    <!-- Form tạo bài viết -->
                    <Card
                        class="overflow-hidden rounded-2xl border border-slate-100 bg-white/40 backdrop-blur-md xl:col-span-2 dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <CardHeader
                            class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/50"
                        >
                            <CardTitle
                                class="flex items-center gap-2 text-base font-black"
                            >
                                <BookOpenText class="size-5 text-emerald-500" />
                                Thêm Hướng Dẫn Kỹ Thuật
                            </CardTitle>
                            <CardDescription class="text-xs font-semibold">
                                Viết tài liệu tự khắc phục sự cố, hướng dẫn cho
                                chủ quán &amp; thu ngân
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-6">
                            <form
                                class="space-y-4"
                                @submit.prevent="submitArticle"
                            >
                                <div class="grid gap-1.5">
                                    <Label
                                        class="text-xs font-bold text-slate-500"
                                        >Danh Mục Bài Viết</Label
                                    >
                                    <Input
                                        v-model="articleForm.category"
                                        placeholder="onboarding / billing / kitchen / realtime"
                                        class="h-10 rounded-xl"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label
                                        class="text-xs font-bold text-slate-500"
                                        >Tiêu Đề Hướng Dẫn</Label
                                    >
                                    <Input
                                        v-model="articleForm.title"
                                        placeholder="Cách thiết lập lại màn hình bếp khi mất realtime"
                                        class="h-10 rounded-xl"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label
                                        class="text-xs font-bold text-slate-500"
                                        >Tóm Tắt Ngắn</Label
                                    >
                                    <Input
                                        v-model="articleForm.summary"
                                        placeholder="Khắc phục nhanh tình trạng màn hình bếp không nhận đơn"
                                        class="h-10 rounded-xl"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label
                                        class="text-xs font-bold text-slate-500"
                                        >Nội Dung Chi Tiết</Label
                                    >
                                    <textarea
                                        v-model="articleForm.content"
                                        rows="5"
                                        placeholder="Nhập nội dung hướng dẫn chi tiết từng bước 1, 2, 3..."
                                        class="min-h-[140px] w-full rounded-xl border bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500/50 focus-visible:outline-none"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label
                                        class="text-xs font-bold text-slate-500"
                                        >Link Video Hướng Dẫn (Youtube
                                        URL)</Label
                                    >
                                    <Input
                                        v-model="articleForm.video_url"
                                        placeholder="https://youtube.com/watch?v=..."
                                        class="h-10 rounded-xl"
                                    />
                                </div>

                                <div
                                    class="flex items-center gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4 transition-all duration-300"
                                >
                                    <input
                                        id="is_published"
                                        v-model="articleForm.is_published"
                                        type="checkbox"
                                        class="size-4.5 rounded border-emerald-300 accent-emerald-600"
                                    />
                                    <label
                                        for="is_published"
                                        class="cursor-pointer text-xs font-bold text-emerald-950 select-none dark:text-emerald-300"
                                    >
                                        Xuất bản ngay cho tất cả nhà hàng xem
                                        được
                                    </label>
                                </div>

                                <Button
                                    type="submit"
                                    class="flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                                    :disabled="articleForm.processing"
                                >
                                    <BookOpenText class="size-4" />
                                    {{
                                        articleForm.processing
                                            ? 'Đang Lưu...'
                                            : 'Thêm Bài Viết Hướng Dẫn'
                                    }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Danh sách bài viết -->
                    <div class="xl:col-span-3">
                        <Card
                            class="h-full overflow-hidden rounded-2xl border border-slate-100 bg-white/40 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/40"
                        >
                            <CardHeader
                                class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/50"
                            >
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-black"
                                >
                                    <BookOpenText
                                        class="size-5 text-indigo-500"
                                    />
                                    Thư Viện Hướng Dẫn Tự Phục Vụ ({{
                                        articles.total
                                    }})
                                </CardTitle>
                                <CardDescription class="text-xs font-semibold">
                                    Các bài viết hướng dẫn giúp nhà hàng tự khắc
                                    phục sự cố
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="p-6">
                                <div
                                    v-if="articles.data.length"
                                    class="grid max-h-[640px] gap-4 overflow-y-auto pr-1 sm:grid-cols-2"
                                >
                                    <div
                                        v-for="article in articles.data"
                                        :key="article.id"
                                        class="flex flex-col justify-between rounded-xl border border-slate-100 bg-white p-5 shadow-2xs transition-all duration-200 hover:border-slate-200 hover:shadow-xs dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <div>
                                            <div
                                                class="mb-2.5 flex items-center gap-2"
                                            >
                                                <div
                                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600"
                                                >
                                                    <BookOpenText
                                                        class="size-4"
                                                    />
                                                </div>
                                                <Badge
                                                    class="rounded border border-slate-200/50 bg-slate-100 px-2 py-0.5 text-[9px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-800"
                                                >
                                                    {{ article.category }}
                                                </Badge>
                                            </div>

                                            <p
                                                class="text-sm leading-snug font-bold text-slate-800 dark:text-white"
                                            >
                                                {{ article.title }}
                                            </p>
                                            <p
                                                class="mt-2 line-clamp-2 text-xs leading-relaxed font-semibold text-slate-500"
                                                :title="article.summary"
                                            >
                                                {{ article.summary }}
                                            </p>
                                        </div>

                                        <div
                                            class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3.5 dark:border-slate-800/80"
                                        >
                                            <span
                                                class="flex items-center gap-1 font-mono text-[10px] font-bold text-slate-400"
                                            >
                                                <Eye class="size-3.5" />
                                                {{ article.view_count }} lượt
                                                xem
                                            </span>

                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <a
                                                    v-if="article.video_url"
                                                    :href="article.video_url"
                                                    target="_blank"
                                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-red-500/10 text-red-600 shadow-2xs transition-all hover:bg-red-500 hover:text-white"
                                                    title="Xem video hướng dẫn"
                                                >
                                                    <Play
                                                        class="size-3.5 fill-current"
                                                    />
                                                </a>

                                                <Badge
                                                    :class="[
                                                        article.is_published
                                                            ? 'border border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                                            : 'border border-slate-200 bg-slate-100 text-slate-500',
                                                    ]"
                                                >
                                                    {{
                                                        article.is_published
                                                            ? 'Đang đăng'
                                                            : 'Bản nháp'
                                                    }}
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="flex flex-col items-center justify-center gap-2 py-16 text-center text-slate-400"
                                >
                                    <div
                                        class="mb-2 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-300 dark:bg-slate-800"
                                    >
                                        <BookOpenText class="size-8" />
                                    </div>
                                    <p
                                        class="text-sm font-black text-slate-700 dark:text-slate-300"
                                    >
                                        Thư viện trống
                                    </p>
                                    <p
                                        class="text-xs font-semibold text-slate-500"
                                    >
                                        Tạo tài liệu hướng dẫn kỹ thuật đầu tiên
                                        để đối tác tự phục vụ.
                                    </p>
                                </div>

                                <!-- Articles pagination -->
                                <Pagination
                                    as="button"
                                    :links="articles.links"
                                    :current-page="articles.current_page"
                                    :last-page="articles.last_page"
                                    :total="articles.total"
                                    @navigate="goToArticlesPage"
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </TabsContent>
        </Tabs>
    </div>

    <!-- Edit Ticket Dialog -->
    <Dialog
        :open="!!editingTicket"
        @update:open="
            (v) => {
                if (!v) editingTicket = null;
            }
        "
    >
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Sửa Ticket {{ editingTicket?.code }}</DialogTitle>
            </DialogHeader>
            <div class="space-y-3 py-2">
                <div>
                    <Label class="text-xs font-bold text-slate-500"
                        >Tiêu đề</Label
                    >
                    <Input v-model="editTicketForm.title" class="mt-1" />
                    <p
                        v-if="editTicketForm.errors.title"
                        class="mt-1 text-xs text-rose-500"
                    >
                        {{ editTicketForm.errors.title }}
                    </p>
                </div>
                <div>
                    <Label class="text-xs font-bold text-slate-500"
                        >Danh mục</Label
                    >
                    <Input v-model="editTicketForm.category" class="mt-1" />
                </div>
                <div>
                    <Label class="text-xs font-bold text-slate-500"
                        >Mức độ nghiêm trọng</Label
                    >
                    <Select v-model="editTicketForm.severity">
                        <SelectTrigger class="mt-1"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="low">Low</SelectItem>
                            <SelectItem value="medium">Medium</SelectItem>
                            <SelectItem value="high">High</SelectItem>
                            <SelectItem value="critical">Critical</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <Label class="text-xs font-bold text-slate-500"
                        >Mô tả</Label
                    >
                    <textarea
                        v-model="editTicketForm.description"
                        rows="3"
                        class="mt-1 min-h-[72px] w-full rounded-xl border bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500/50 focus-visible:outline-none"
                    />
                </div>
            </div>
            <DialogFooter class="gap-2">
                <Button variant="ghost" @click="editingTicket = null"
                    >Huỷ</Button
                >
                <Button
                    :disabled="editTicketForm.processing"
                    class="bg-indigo-600 text-white hover:bg-indigo-700"
                    @click="submitEditTicket"
                >
                    {{
                        editTicketForm.processing
                            ? 'Đang lưu...'
                            : 'Lưu thay đổi'
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Edit Rule Dialog -->
    <Dialog
        :open="!!editingRule"
        @update:open="
            (v) => {
                if (!v) editingRule = null;
            }
        "
    >
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Sửa Rule Cảnh Báo</DialogTitle>
            </DialogHeader>
            <div class="space-y-3 py-2">
                <div>
                    <Label class="text-xs font-bold text-slate-500"
                        >Tên rule</Label
                    >
                    <Input v-model="editRuleForm.name" class="mt-1" />
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <Label class="text-xs font-bold text-slate-500"
                            >Metric key</Label
                        >
                        <Input
                            v-model="editRuleForm.metric_key"
                            class="mt-1 font-mono text-xs"
                        />
                    </div>
                    <div>
                        <Label class="text-xs font-bold text-slate-500"
                            >Toán tử</Label
                        >
                        <Select v-model="editRuleForm.operator">
                            <SelectTrigger class="mt-1"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value=">">&gt;</SelectItem>
                                <SelectItem value=">=">&gt;=</SelectItem>
                                <SelectItem value="<">&lt;</SelectItem>
                                <SelectItem value="<=">&lt;=</SelectItem>
                                <SelectItem value="=">=</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label class="text-xs font-bold text-slate-500"
                            >Ngưỡng</Label
                        >
                        <Input
                            v-model.number="editRuleForm.threshold"
                            type="number"
                            class="mt-1"
                        />
                    </div>
                </div>
                <div>
                    <Label class="text-xs font-bold text-slate-500"
                        >Cooldown (phút)</Label
                    >
                    <Input
                        v-model.number="editRuleForm.cooldown_minutes"
                        type="number"
                        min="1"
                        class="mt-1"
                    />
                </div>
            </div>
            <DialogFooter class="gap-2">
                <Button variant="ghost" @click="editingRule = null">Huỷ</Button>
                <Button
                    :disabled="editRuleForm.processing"
                    class="bg-indigo-600 text-white hover:bg-indigo-700"
                    @click="submitEditRule"
                >
                    {{
                        editRuleForm.processing ? 'Đang lưu...' : 'Lưu thay đổi'
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/* Micro-animations and premium UI visual additions */
.anim-fade-in {
    animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-slide-up {
    animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-pulse-glow {
    animation: pulseGlow 2s infinite ease-in-out;
}

@keyframes pulseGlow {
    0%,
    100% {
        box-shadow: 0 0 6px rgba(16, 185, 129, 0.4);
    }
    50% {
        box-shadow: 0 0 16px rgba(16, 185, 129, 0.8);
    }
}

.animate-bounce-gentle {
    animation: bounceGentle 3s infinite ease-in-out;
}

@keyframes bounceGentle {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-4px);
    }
}

/* Custom scrollbar matching tailwind design rules */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.3);
    border-radius: 9999px;
}
::-webkit-scrollbar-thumb:hover {
    background: rgba(148, 163, 184, 0.5);
}
</style>
