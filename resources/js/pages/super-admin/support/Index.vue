<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BellRing,
    BookOpenText,
    Cpu,
    Eye,
    Headset,
    LifeBuoy,
    Radio,
    RefreshCcw,
    Send,
    Siren,
    Ticket,
    Activity,
    CheckCircle2,
    Clock,
    XCircle,
    Zap,
    Database,
    Globe,
    MonitorSpeaker,
    PlusCircle,
    ChevronRight,
    Play,
    Check,
    ShieldAlert,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
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
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type TicketFormData = {
    restaurant_id: string | null;
    category: string;
    title: string;
    description: string;
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
    created_by: string;
    created_at: string;
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
    tickets: TicketRow[];
    alerts: AlertRow[];
    rules: Array<Record<string, any>>;
    announcements: Array<Record<string, any>>;
    articles: Array<Record<string, any>>;
    restaurants: Array<{ id: number; name: string }>;
    filters: { status?: string; severity?: string; restaurant_id?: string };
}>();

const activeTab = ref('monitoring');

const ticketForm = useForm<TicketFormData>({
    restaurant_id: 'system',
    category: 'realtime',
    title: '',
    description: '',
});
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
</script>

<template>
    <Head title="DevOps & Support Portal" />

    <div
        class="anim-fade-in mx-auto flex w-full max-w-[1600px] flex-col gap-8 p-6"
    >
        <!-- ============================================================ -->
        <!-- HEADER SECTION (Glassmorphism + Neon accents)               -->
        <!-- ============================================================ -->
        <div
            class="relative flex flex-wrap items-center justify-between gap-6 rounded-2xl border border-slate-100 bg-white/40 p-6 shadow-xs backdrop-blur-md transition-all duration-300 dark:border-slate-800/80 dark:bg-slate-900/40"
        >
            <div
                class="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-r from-violet-500/5 via-transparent to-indigo-500/5"
            />
            <div class="relative z-10 flex items-center gap-4">
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 shadow-md shadow-indigo-500/10"
                >
                    <Headset class="animate-bounce-gentle size-6 text-white" />
                </div>
                <div>
                    <h1
                        class="bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 bg-clip-text text-2xl font-black tracking-tight text-transparent dark:from-white dark:to-slate-300"
                    >
                        DevOps & Support Portal
                    </h1>
                    <p
                        class="mt-1 flex flex-wrap items-center gap-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400"
                    >
                        <span>Giám sát hạ tầng</span>
                        <span class="text-slate-300 dark:text-slate-700"
                            >•</span
                        >
                        <span>Ticket hỗ trợ</span>
                        <span class="text-slate-300 dark:text-slate-700"
                            >•</span
                        >
                        <span>Broadcast Realtime</span>
                        <span class="text-slate-300 dark:text-slate-700"
                            >•</span
                        >
                        <span>Knowledge Base</span>
                    </p>
                </div>
            </div>

            <div class="relative z-10 flex flex-wrap items-center gap-3">
                <div
                    :class="[
                        'flex items-center gap-2 rounded-full px-4 py-1.5 text-xs font-bold transition-all duration-300',
                        systemHealth.bg,
                        systemHealth.color,
                    ]"
                >
                    <span
                        :class="['size-2.5 rounded-full', systemHealth.dot]"
                    />
                    {{ systemHealth.label }}
                </div>

                <Button
                    variant="outline"
                    size="sm"
                    @click="runAlertCheck"
                    class="flex h-9 items-center gap-2 rounded-xl border-amber-500/20 px-4 font-semibold text-amber-600 transition-all hover:bg-amber-500/5 hover:text-amber-500"
                >
                    <Siren class="size-4 animate-pulse" />
                    Quét Cảnh Báo
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    @click="
                        router.reload({
                            only: ['stats', 'monitoring', 'tickets', 'alerts'],
                        })
                    "
                    class="flex h-9 items-center gap-2 rounded-xl border-indigo-500/20 px-4 font-semibold text-indigo-600 transition-all hover:bg-indigo-500/5 hover:text-indigo-500"
                >
                    <RefreshCcw
                        class="size-4 transition-transform duration-500 hover:rotate-180"
                    />
                    Làm Mới
                </Button>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- KPI STATS CARDS WITH VIBRANT NEON GRADIENTS                  -->
        <!-- ============================================================ -->
        <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
        >
            <!-- Ticket mở -->
            <Card
                class="group relative overflow-hidden rounded-2xl border-0 border-t border-sky-500/20 bg-gradient-to-br from-sky-500/5 to-indigo-500/10 transition-all duration-300 hover:shadow-lg hover:shadow-sky-500/5 dark:from-sky-950/20 dark:to-indigo-950/10"
            >
                <div
                    class="absolute -right-4 -bottom-4 size-24 rounded-full bg-sky-500/10 blur-xl transition-transform duration-500 group-hover:scale-125"
                />
                <CardContent
                    class="relative z-10 flex items-center justify-between p-5"
                >
                    <div>
                        <p
                            class="text-xs font-bold tracking-wider text-sky-600 uppercase dark:text-sky-400"
                        >
                            Ticket đang mở
                        </p>
                        <p
                            class="mt-2 text-3xl font-black text-slate-800 dark:text-white"
                        >
                            {{ stats.tickets_open ?? 0 }}
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300"
                    >
                        <Ticket class="size-5" />
                    </div>
                </CardContent>
            </Card>

            <!-- Nguy cấp -->
            <Card
                class="group relative overflow-hidden rounded-2xl border-0 border-t border-rose-500/20 bg-gradient-to-br from-rose-500/5 to-red-500/10 transition-all duration-300 hover:shadow-lg hover:shadow-rose-500/5 dark:from-rose-950/20 dark:to-red-950/10"
            >
                <div
                    class="absolute -right-4 -bottom-4 size-24 rounded-full bg-rose-500/10 blur-xl transition-transform duration-500 group-hover:scale-125"
                />
                <CardContent
                    class="relative z-10 flex items-center justify-between p-5"
                >
                    <div>
                        <p
                            class="text-xs font-bold tracking-wider text-rose-600 uppercase dark:text-rose-400"
                        >
                            Nguy cấp (Sự cố)
                        </p>
                        <p
                            class="mt-2 text-3xl font-black text-rose-600 dark:text-rose-400"
                        >
                            {{ stats.tickets_critical ?? 0 }}
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300"
                    >
                        <LifeBuoy class="size-5" />
                    </div>
                </CardContent>
            </Card>

            <!-- Alert mở -->
            <Card
                class="group relative overflow-hidden rounded-2xl border-0 border-t border-amber-500/20 bg-gradient-to-br from-amber-500/5 to-orange-500/10 transition-all duration-300 hover:shadow-lg hover:shadow-amber-500/5 dark:from-amber-950/20 dark:to-orange-950/10"
            >
                <div
                    class="absolute -right-4 -bottom-4 size-24 rounded-full bg-amber-500/10 blur-xl transition-transform duration-500 group-hover:scale-125"
                />
                <CardContent
                    class="relative z-10 flex items-center justify-between p-5"
                >
                    <div>
                        <p
                            class="text-xs font-bold tracking-wider text-amber-600 uppercase dark:text-amber-400"
                        >
                            Cảnh báo kích hoạt
                        </p>
                        <p
                            class="mt-2 text-3xl font-black text-slate-800 dark:text-white"
                        >
                            {{ stats.alerts_open ?? 0 }}
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300"
                    >
                        <AlertTriangle class="size-5" />
                    </div>
                </CardContent>
            </Card>

            <!-- Broadcast live -->
            <Card
                class="group relative overflow-hidden rounded-2xl border-0 border-t border-violet-500/20 bg-gradient-to-br from-violet-500/5 to-purple-500/10 transition-all duration-300 hover:shadow-lg hover:shadow-violet-500/5 dark:from-violet-950/20 dark:to-purple-950/10"
            >
                <div
                    class="absolute -right-4 -bottom-4 size-24 rounded-full bg-violet-500/10 blur-xl transition-transform duration-500 group-hover:scale-125"
                />
                <CardContent
                    class="relative z-10 flex items-center justify-between p-5"
                >
                    <div>
                        <p
                            class="text-xs font-bold tracking-wider text-violet-600 uppercase dark:text-violet-400"
                        >
                            Broadcast Realtime
                        </p>
                        <p
                            class="mt-2 text-3xl font-black text-slate-800 dark:text-white"
                        >
                            {{ stats.announcements_live ?? 0 }}
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:bg-violet-500/20 dark:text-violet-300"
                    >
                        <Radio class="size-5 animate-pulse" />
                    </div>
                </CardContent>
            </Card>

            <!-- Bài KB -->
            <Card
                class="group relative overflow-hidden rounded-2xl border-0 border-t border-emerald-500/20 bg-gradient-to-br from-emerald-500/5 to-teal-500/10 transition-all duration-300 hover:shadow-lg hover:shadow-emerald-500/5 dark:from-emerald-950/20 dark:to-teal-950/10"
            >
                <div
                    class="absolute -right-4 -bottom-4 size-24 rounded-full bg-emerald-500/10 blur-xl transition-transform duration-500 group-hover:scale-125"
                />
                <CardContent
                    class="relative z-10 flex items-center justify-between p-5"
                >
                    <div>
                        <p
                            class="text-xs font-bold tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                        >
                            Tài liệu HD (KB)
                        </p>
                        <p
                            class="mt-2 text-3xl font-black text-slate-800 dark:text-white"
                        >
                            {{ stats.kb_published ?? 0 }}
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300"
                    >
                        <BookOpenText class="size-5" />
                    </div>
                </CardContent>
            </Card>
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
                        <span>Hạ Tầng</span>
                    </TabsTrigger>
                    <TabsTrigger
                        value="tickets"
                        class="relative flex h-10 items-center gap-2 rounded-xl px-5 text-xs font-bold transition-all data-[state=active]:bg-white data-[state=active]:text-indigo-600 data-[state=active]:shadow-sm dark:data-[state=active]:bg-slate-800 dark:data-[state=active]:text-white"
                    >
                        <Ticket class="size-4 shrink-0" />
                        <span>Hỗ Trợ</span>
                        <span
                            v-if="(stats.tickets_open ?? 0) > 0"
                            class="ml-1 flex h-5 items-center justify-center rounded-full bg-rose-500 px-2 py-0.5 text-[9px] font-black text-white shadow-sm shadow-rose-500/30"
                        >
                            {{ stats.tickets_open }}
                        </span>
                    </TabsTrigger>
                    <TabsTrigger
                        value="broadcast"
                        class="flex h-10 items-center gap-2 rounded-xl px-5 text-xs font-bold transition-all data-[state=active]:bg-white data-[state=active]:text-indigo-600 data-[state=active]:shadow-sm dark:data-[state=active]:bg-slate-800 dark:data-[state=active]:text-white"
                    >
                        <Radio class="size-4 shrink-0" />
                        <span>Broadcast</span>
                    </TabsTrigger>
                    <TabsTrigger
                        value="alerts"
                        class="flex h-10 items-center gap-2 rounded-xl px-5 text-xs font-bold transition-all data-[state=active]:bg-white data-[state=active]:text-indigo-600 data-[state=active]:shadow-sm dark:data-[state=active]:bg-slate-800 dark:data-[state=active]:text-white"
                    >
                        <Siren class="size-4 shrink-0" />
                        <span>Cảnh Báo Rules</span>
                    </TabsTrigger>
                    <TabsTrigger
                        value="kb"
                        class="flex h-10 items-center gap-2 rounded-xl px-5 text-xs font-bold transition-all data-[state=active]:bg-white data-[state=active]:text-indigo-600 data-[state=active]:shadow-sm dark:data-[state=active]:bg-slate-800 dark:data-[state=active]:text-white"
                    >
                        <BookOpenText class="size-4 shrink-0" />
                        <span>Knowledge Base</span>
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
                                    Failed Jobs
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
                                    Pending Jobs
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
                                    API Error Rate
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
                                    Slow Queries
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
                            v-if="alerts.length"
                            class="relative space-y-4 border-l border-slate-200 pl-6 dark:border-slate-800"
                        >
                            <div
                                v-for="alert in alerts"
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
                                                >Billing / Hóa đơn & Gói dịch
                                                vụ</SelectItem
                                            >
                                            <SelectItem value="ui"
                                                >UI / Trải nghiệm & Bố
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
                                        >Mô Tả Chi Tiết & Ảnh Hưởng
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
                                    Danh Sách Phiếu Hỗ Trợ ({{
                                        tickets.length
                                    }})
                                </CardTitle>
                                <CardDescription class="text-xs font-semibold">
                                    Danh sách yêu cầu xử lý từ nhà hàng, sắp xếp
                                    theo mức độ nghiêm trọng
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="p-6">
                                <div
                                    v-if="tickets.length"
                                    class="max-h-[640px] space-y-4 overflow-y-auto pr-1"
                                >
                                    <div
                                        v-for="ticket in tickets"
                                        :key="ticket.id"
                                        class="rounded-xl border border-slate-100 bg-white p-5 shadow-2xs transition-all duration-200 hover:shadow-xs dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <div
                                            class="flex flex-wrap items-start justify-between gap-4"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="mb-2 flex flex-wrap items-center gap-2"
                                                >
                                                    <span
                                                        class="font-mono text-xs font-bold text-slate-400 dark:text-slate-500"
                                                        >{{ ticket.code }}</span
                                                    >
                                                    <Badge
                                                        :class="[
                                                            'rounded px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase',
                                                            severityBadge[
                                                                ticket.severity
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
                                                                ticket.status
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

                                        <!-- Interactive control buttons based on status -->
                                        <div
                                            class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3 dark:border-slate-800/80"
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
                            </CardContent>
                        </Card>
                    </div>
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
                                    v-if="announcements.length"
                                    class="max-h-[640px] space-y-4 overflow-y-auto pr-1"
                                >
                                    <div
                                        v-for="a in announcements"
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

                                            <div class="shrink-0">
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
                                                    >&#62; lớn hơn</SelectItem
                                                >
                                                <SelectItem value=">="
                                                    >&#62;= lớn hơn hoặc
                                                    bằng</SelectItem
                                                >
                                                <SelectItem value="<"
                                                    >&#60; nhỏ hơn</SelectItem
                                                >
                                                <SelectItem value="<="
                                                    >&#60;= nhỏ hơn hoặc
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
                                                rules.length
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
                                    v-if="rules.length"
                                    class="max-h-[640px] space-y-3 overflow-y-auto pr-1"
                                >
                                    <div
                                        v-for="rule in rules"
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
                                                    {{
                                                        rule.cooldown_minutes
                                                    }}
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
                                chủ quán & thu ngân
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
                                        articles.length
                                    }})
                                </CardTitle>
                                <CardDescription class="text-xs font-semibold">
                                    Các bài viết hướng dẫn giúp nhà hàng tự khắc
                                    phục sự cố
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="p-6">
                                <div
                                    v-if="articles.length"
                                    class="grid max-h-[640px] gap-4 overflow-y-auto pr-1 sm:grid-cols-2"
                                >
                                    <div
                                        v-for="article in articles"
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
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </TabsContent>
        </Tabs>
    </div>
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
