<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle, BellRing, BookOpenText, Cpu, Eye, Headset,
    LifeBuoy, Radio, RefreshCcw, Send, Siren, Ticket,
    Activity, CheckCircle2, Clock, XCircle, Zap, Database,
    Globe, MonitorSpeaker, PlusCircle, ChevronRight,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type TicketFormData = { restaurant_id: string | null; category: string; title: string; description: string };
type TicketRow = { id: number; code: string; restaurant: string; title: string; category: string; severity: string; priority: string; status: string; assignee: string | null; created_by: string; created_at: string };
type AlertRow = { id: number; title: string; metric_key: string; metric_value: string | number | null; threshold: string | number | null; status: string; triggered_at: string | null };

const props = defineProps<{
    stats: Record<string, number>;
    monitoring: { failed_jobs: number; pending_jobs: number; queue_backlog: number; api_error_rate: number; api_error_total: number; api_request_total: number; slow_queries: number; pulse_exceptions: number; infra: { cpu: number | null; ram: number | null; source: string } };
    tickets: TicketRow[];
    alerts: AlertRow[];
    rules: Array<Record<string, any>>;
    announcements: Array<Record<string, any>>;
    articles: Array<Record<string, any>>;
    restaurants: Array<{ id: number; name: string }>;
    filters: { status?: string; severity?: string; restaurant_id?: string };
}>();

const activeTab = ref('monitoring');

const ticketForm = useForm<TicketFormData>({ restaurant_id: 'system', category: 'realtime', title: '', description: '' });
const announcementForm = useForm({ title: '', message: '', audience: 'all', level: 'warning', starts_at: '', ends_at: '', publish_now: true });
const articleForm = useForm({ category: 'onboarding', title: '', summary: '', content: '', video_url: '', is_published: true });
const ruleForm = useForm({ name: '', metric_key: 'api_error_rate', operator: '>', threshold: 5, cooldown_minutes: 15 });

const severityBadge: Record<string, string> = {
    critical: 'bg-red-100 text-red-700 border border-red-200',
    high: 'bg-orange-100 text-orange-700 border border-orange-200',
    medium: 'bg-amber-100 text-amber-700 border border-amber-200',
    low: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
};
const statusBadge: Record<string, string> = {
    open: 'bg-red-100 text-red-700 border border-red-200',
    in_progress: 'bg-blue-100 text-blue-700 border border-blue-200',
    waiting_restaurant: 'bg-amber-100 text-amber-700 border border-amber-200',
    resolved: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    closed: 'bg-slate-100 text-slate-600 border border-slate-200',
    published: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
    draft: 'bg-slate-100 text-slate-600 border border-slate-200',
};

const systemHealth = computed(() => {
    const rate = props.monitoring.api_error_rate;
    const failed = props.monitoring.failed_jobs;

    if (failed > 0 || rate > 5) {
return { label: 'Cảnh báo', color: 'text-amber-500', bg: 'bg-amber-50', dot: 'bg-amber-500' };
}

    if (rate > 2) {
return { label: 'Chú ý', color: 'text-orange-500', bg: 'bg-orange-50', dot: 'bg-orange-500' };
}

    return { label: 'Hoạt động tốt', color: 'text-emerald-600', bg: 'bg-emerald-50', dot: 'bg-emerald-500' };
});

function runAlertCheck() {
 router.post('/super-admin/support/alerts/run', {}, { preserveScroll: true }); 
}
function submitTicket() {
    ticketForm.transform((data: TicketFormData) => ({ ...data, restaurant_id: data.restaurant_id === 'system' ? null : data.restaurant_id }))
        .post('/super-admin/support/tickets', { preserveScroll: true, onSuccess: () => ticketForm.reset('title', 'description') });
}
function updateTicket(id: number, status: string) {
 router.patch(`/super-admin/support/tickets/${id}`, { status }, { preserveScroll: true }); 
}
function submitAnnouncement() {
 announcementForm.post('/super-admin/support/announcements', { preserveScroll: true, onSuccess: () => announcementForm.reset('title', 'message') }); 
}
function submitArticle() {
 articleForm.post('/super-admin/support/articles', { preserveScroll: true, onSuccess: () => articleForm.reset('title', 'summary', 'content', 'video_url') }); 
}
function submitRule() {
 ruleForm.post('/super-admin/support/rules', { preserveScroll: true, onSuccess: () => ruleForm.reset('name') }); 
}
</script>

<template>
    <Head title="DevOps & Support Portal" />

    <div class="flex flex-col gap-6 p-6">

        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100">
                    <Headset class="size-5 text-violet-600" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">DevOps & Support Portal</h1>
                    <p class="text-sm text-muted-foreground">Giám sát hạ tầng · Ticket hỗ trợ · Broadcast realtime · Knowledge base</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div :class="['flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium', systemHealth.bg, systemHealth.color]">
                    <span :class="['size-2 rounded-full', systemHealth.dot]" />
                    {{ systemHealth.label }}
                </div>
                <Button variant="outline" size="sm" @click="runAlertCheck">
                    <Siren class="mr-2 size-4" /> Quét cảnh báo
                </Button>
                <Button variant="outline" size="sm" @click="router.reload({ only: ['stats', 'monitoring', 'tickets', 'alerts'] })">
                    <RefreshCcw class="mr-2 size-4" /> Làm mới
                </Button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
            <Card class="border-0 bg-gradient-to-br from-sky-50 to-sky-100/60">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-sky-600">Ticket mở</p>
                        <p class="mt-1 text-3xl font-bold text-sky-900">{{ stats.tickets_open ?? 0 }}</p>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-200/60">
                        <Ticket class="size-5 text-sky-600" />
                    </div>
                </CardContent>
            </Card>
            <Card class="border-0 bg-gradient-to-br from-red-50 to-red-100/60">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-red-600">Nguy cấp</p>
                        <p class="mt-1 text-3xl font-bold text-red-900">{{ stats.tickets_critical ?? 0 }}</p>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-200/60">
                        <LifeBuoy class="size-5 text-red-600" />
                    </div>
                </CardContent>
            </Card>
            <Card class="border-0 bg-gradient-to-br from-amber-50 to-amber-100/60">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-amber-600">Alert mở</p>
                        <p class="mt-1 text-3xl font-bold text-amber-900">{{ stats.alerts_open ?? 0 }}</p>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-200/60">
                        <AlertTriangle class="size-5 text-amber-600" />
                    </div>
                </CardContent>
            </Card>
            <Card class="border-0 bg-gradient-to-br from-violet-50 to-violet-100/60">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-violet-600">Broadcast live</p>
                        <p class="mt-1 text-3xl font-bold text-violet-900">{{ stats.announcements_live ?? 0 }}</p>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-200/60">
                        <Radio class="size-5 text-violet-600" />
                    </div>
                </CardContent>
            </Card>
            <Card class="border-0 bg-gradient-to-br from-emerald-50 to-emerald-100/60">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-emerald-600">Bài KB</p>
                        <p class="mt-1 text-3xl font-bold text-emerald-900">{{ stats.kb_published ?? 0 }}</p>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-200/60">
                        <BookOpenText class="size-5 text-emerald-600" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Tabs -->
        <Tabs v-model="activeTab" class="w-full">
            <TabsList class="grid w-full grid-cols-5">
                <TabsTrigger value="monitoring" class="gap-1.5">
                    <Activity class="size-4" /><span class="hidden sm:inline">Giám sát</span>
                </TabsTrigger>
                <TabsTrigger value="tickets" class="gap-1.5">
                    <Ticket class="size-4" /><span class="hidden sm:inline">Ticket</span>
                    <span v-if="(stats.tickets_open ?? 0) > 0" class="ml-1 rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ stats.tickets_open }}</span>
                </TabsTrigger>
                <TabsTrigger value="broadcast" class="gap-1.5">
                    <Radio class="size-4" /><span class="hidden sm:inline">Broadcast</span>
                </TabsTrigger>
                <TabsTrigger value="alerts" class="gap-1.5">
                    <Siren class="size-4" /><span class="hidden sm:inline">Alert Rules</span>
                </TabsTrigger>
                <TabsTrigger value="kb" class="gap-1.5">
                    <BookOpenText class="size-4" /><span class="hidden sm:inline">Knowledge Base</span>
                </TabsTrigger>
            </TabsList>

            <!-- ============================================================ -->
            <!-- TAB 1: MONITORING                                            -->
            <!-- ============================================================ -->
            <TabsContent value="monitoring" class="mt-6 space-y-6">
                <!-- Monitoring Grid -->
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardContent class="p-5">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-muted-foreground">Failed Jobs</p>
                                <XCircle :class="['size-5', monitoring.failed_jobs > 0 ? 'text-red-500' : 'text-slate-300']" />
                            </div>
                            <p :class="['mt-3 text-3xl font-bold', monitoring.failed_jobs > 0 ? 'text-red-600' : 'text-foreground']">
                                {{ monitoring.failed_jobs }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">Jobs thất bại trong queue</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-5">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-muted-foreground">Pending Jobs</p>
                                <Clock class="size-5 text-amber-400" />
                            </div>
                            <p class="mt-3 text-3xl font-bold">{{ monitoring.pending_jobs }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Đang chờ xử lý</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-5">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-muted-foreground">API Error Rate</p>
                                <Globe :class="['size-5', monitoring.api_error_rate > 5 ? 'text-red-500' : 'text-emerald-500']" />
                            </div>
                            <p :class="['mt-3 text-3xl font-bold', monitoring.api_error_rate > 5 ? 'text-red-600' : 'text-foreground']">
                                {{ monitoring.api_error_rate }}%
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ monitoring.api_error_total }}/{{ monitoring.api_request_total }} requests · SLA &lt; 5%
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-5">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-muted-foreground">Slow Queries</p>
                                <Database :class="['size-5', monitoring.slow_queries > 10 ? 'text-amber-500' : 'text-slate-300']" />
                            </div>
                            <p :class="['mt-3 text-3xl font-bold', monitoring.slow_queries > 10 ? 'text-amber-600' : 'text-foreground']">
                                {{ monitoring.slow_queries }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">Truy vấn &gt; 1000ms</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-5">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-muted-foreground">Queue Backlog</p>
                                <Activity class="size-5 text-sky-400" />
                            </div>
                            <p class="mt-3 text-3xl font-bold">{{ monitoring.queue_backlog }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Tổng tồn đọng hàng đợi</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-5">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-muted-foreground">Pulse Exceptions</p>
                                <Zap class="size-5 text-violet-400" />
                            </div>
                            <p class="mt-3 text-3xl font-bold">{{ monitoring.pulse_exceptions }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Ngoại lệ ghi nhận bởi Pulse</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-5">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-muted-foreground">CPU / RAM</p>
                                <Cpu class="size-5 text-slate-400" />
                            </div>
                            <p class="mt-3 text-2xl font-bold">
                                {{ monitoring.infra.cpu ?? 'N/A' }} / {{ monitoring.infra.ram ?? 'N/A' }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ monitoring.infra.source }}</p>
                        </CardContent>
                    </Card>
                    <Card class="border-dashed bg-muted/20">
                        <CardContent class="flex h-full flex-col items-center justify-center p-5 text-center">
                            <CheckCircle2 class="size-8 text-emerald-400" />
                            <p class="mt-2 text-sm font-semibold">SLA cam kết</p>
                            <p class="text-xs text-muted-foreground">API Response &lt; 2s</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Recent Alerts -->
                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-base">Cảnh báo gần đây</CardTitle>
                                <CardDescription>Các alert được kích hoạt bởi rule engine</CardDescription>
                            </div>
                            <Button variant="outline" size="sm" @click="activeTab = 'alerts'">
                                Quản lý rules <ChevronRight class="ml-1 size-4" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="alerts.length" class="space-y-2">
                            <div
                                v-for="alert in alerts"
                                :key="alert.id"
                                class="flex items-center justify-between rounded-lg border px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <AlertTriangle :class="['size-4 shrink-0', alert.status === 'open' ? 'text-red-500' : 'text-slate-300']" />
                                    <div>
                                        <p class="text-sm font-medium">{{ alert.title }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ alert.metric_key }}: <span class="font-mono font-semibold">{{ alert.metric_value }}</span>
                                            / threshold <span class="font-mono">{{ alert.threshold }}</span>
                                            · {{ alert.triggered_at }}
                                        </p>
                                    </div>
                                </div>
                                <Badge :class="statusBadge[alert.status] || 'bg-slate-100 text-slate-600'">
                                    {{ alert.status }}
                                </Badge>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center gap-2 py-8 text-center text-muted-foreground">
                            <CheckCircle2 class="size-10 text-emerald-400" />
                            <p class="text-sm font-medium">Không có cảnh báo nào</p>
                            <p class="text-xs">Hệ thống đang hoạt động bình thường</p>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- ============================================================ -->
            <!-- TAB 2: TICKET SYSTEM                                         -->
            <!-- ============================================================ -->
            <TabsContent value="tickets" class="mt-6">
                <div class="grid gap-6 xl:grid-cols-5">
                    <!-- Form tạo ticket -->
                    <Card class="xl:col-span-2">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <PlusCircle class="size-4 text-sky-500" /> Tạo Ticket Hỗ Trợ
                            </CardTitle>
                            <CardDescription>Tiếp nhận sự cố, báo cáo lỗi từ nhà hàng</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form class="space-y-4" @submit.prevent="submitTicket">
                                <div class="grid gap-1.5">
                                    <Label>Nhà hàng</Label>
                                    <Select v-model="ticketForm.restaurant_id">
                                        <SelectTrigger><SelectValue placeholder="Chọn nhà hàng" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="system">Hệ thống / Chưa gán</SelectItem>
                                            <SelectItem v-for="r in restaurants" :key="r.id" :value="String(r.id)">{{ r.name }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Danh mục</Label>
                                    <Select v-model="ticketForm.category">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="realtime">Realtime / Màn hình bếp</SelectItem>
                                            <SelectItem value="queue">Queue / Job thất bại</SelectItem>
                                            <SelectItem value="billing">Billing / Hoá đơn</SelectItem>
                                            <SelectItem value="ui">UI / Nội dung</SelectItem>
                                            <SelectItem value="performance">Hiệu năng</SelectItem>
                                            <SelectItem value="other">Khác</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Tiêu đề <span class="text-red-500">*</span></Label>
                                    <Input v-model="ticketForm.title" placeholder="Màn hình bếp không nhận realtime" />
                                    <p v-if="ticketForm.errors.title" class="text-xs text-red-500">{{ ticketForm.errors.title }}</p>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Mô tả chi tiết <span class="text-red-500">*</span></Label>
                                    <textarea
                                        v-model="ticketForm.description"
                                        rows="4"
                                        placeholder="Mô tả sự cố, bước tái hiện, ảnh hưởng..."
                                        class="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    />
                                    <p v-if="ticketForm.errors.description" class="text-xs text-red-500">{{ ticketForm.errors.description }}</p>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Hệ thống sẽ tự động phân loại mức độ: <strong>Nguy cấp / Cao / Trung bình / Thấp</strong>
                                </p>
                                <Button type="submit" class="w-full" :disabled="ticketForm.processing">
                                    <Send class="mr-2 size-4" />
                                    {{ ticketForm.processing ? 'Đang tạo...' : 'Tạo Ticket' }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Danh sách ticket -->
                    <div class="xl:col-span-3">
                        <Card class="h-full">
                            <CardHeader class="pb-3">
                                <CardTitle class="text-base">Danh sách Ticket ({{ tickets.length }})</CardTitle>
                                <CardDescription>12 ticket gần nhất · tự động phân loại theo mức độ nghiêm trọng</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div v-if="tickets.length" class="space-y-3">
                                    <div
                                        v-for="ticket in tickets"
                                        :key="ticket.id"
                                        class="rounded-xl border p-4 transition-colors hover:bg-muted/30"
                                    >
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-mono text-xs text-muted-foreground">{{ ticket.code }}</span>
                                                    <Badge :class="severityBadge[ticket.severity] || 'bg-slate-100 text-slate-600'" class="text-[10px]">{{ ticket.severity }}</Badge>
                                                    <Badge :class="statusBadge[ticket.status] || 'bg-slate-100 text-slate-600'" class="text-[10px]">{{ ticket.status }}</Badge>
                                                </div>
                                                <p class="mt-1 font-medium text-sm truncate">{{ ticket.title }}</p>
                                                <p class="text-xs text-muted-foreground">
                                                    {{ ticket.restaurant }} · {{ ticket.category }} · {{ ticket.created_at }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <Button
                                                v-if="ticket.status === 'open'"
                                                size="sm" variant="outline"
                                                class="h-7 text-xs"
                                                @click="updateTicket(ticket.id, 'in_progress')"
                                            >Nhận xử lý</Button>
                                            <Button
                                                v-if="['open', 'in_progress', 'waiting_restaurant'].includes(ticket.status)"
                                                size="sm" variant="outline"
                                                class="h-7 text-xs text-emerald-600 hover:text-emerald-700"
                                                @click="updateTicket(ticket.id, 'resolved')"
                                            >✓ Đánh dấu resolved</Button>
                                            <Button
                                                v-if="ticket.status === 'resolved'"
                                                size="sm" variant="outline"
                                                class="h-7 text-xs"
                                                @click="updateTicket(ticket.id, 'closed')"
                                            >Đóng</Button>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col items-center gap-2 py-12 text-center text-muted-foreground">
                                    <Ticket class="size-10 text-slate-300" />
                                    <p class="text-sm font-medium">Không có ticket nào</p>
                                    <p class="text-xs">Chưa có sự cố nào được ghi nhận</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </TabsContent>

            <!-- ============================================================ -->
            <!-- TAB 3: BROADCAST PORTAL                                      -->
            <!-- ============================================================ -->
            <TabsContent value="broadcast" class="mt-6">
                <div class="grid gap-6 xl:grid-cols-5">
                    <!-- Form tạo thông báo -->
                    <Card class="xl:col-span-2">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <BellRing class="size-4 text-violet-500" /> Tạo Thông Báo Broadcast
                            </CardTitle>
                            <CardDescription>Popup realtime qua Laravel Reverb · Gửi đến Thu ngân, Chủ quán, Bếp</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form class="space-y-4" @submit.prevent="submitAnnouncement">
                                <div class="grid gap-1.5">
                                    <Label>Tiêu đề</Label>
                                    <Input v-model="announcementForm.title" placeholder="Bảo trì hệ thống 23:00-24:00" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Nội dung</Label>
                                    <textarea
                                        v-model="announcementForm.message"
                                        rows="4"
                                        placeholder="Chi tiết thông báo..."
                                        class="w-full rounded-md border bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    />
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-1.5">
                                        <Label>Đối tượng nhận</Label>
                                        <Select v-model="announcementForm.audience">
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Tất cả</SelectItem>
                                                <SelectItem value="cashier">Thu ngân</SelectItem>
                                                <SelectItem value="owner">Chủ quán</SelectItem>
                                                <SelectItem value="kitchen">Bếp</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label>Mức độ</Label>
                                        <Select v-model="announcementForm.level">
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="info">ℹ Info</SelectItem>
                                                <SelectItem value="warning">⚠ Warning</SelectItem>
                                                <SelectItem value="critical">🚨 Critical</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 p-3">
                                    <input id="publish_now" v-model="announcementForm.publish_now" type="checkbox" class="size-4 rounded border-violet-300 accent-violet-600" />
                                    <label for="publish_now" class="text-sm font-medium text-violet-800">Phát ngay lập tức (Reverb broadcast)</label>
                                </div>
                                <Button type="submit" class="w-full" :disabled="announcementForm.processing">
                                    <Radio class="mr-2 size-4" />
                                    {{ announcementForm.processing ? 'Đang phát...' : 'Lưu & Broadcast' }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Lịch sử thông báo -->
                    <div class="xl:col-span-3">
                        <Card class="h-full">
                            <CardHeader class="pb-3">
                                <CardTitle class="text-base">Lịch sử thông báo</CardTitle>
                                <CardDescription>6 thông báo gần nhất</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div v-if="announcements.length" class="space-y-3">
                                    <div
                                        v-for="a in announcements"
                                        :key="a.id"
                                        class="rounded-xl border p-4"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span v-if="a.level === 'critical'" class="text-base">🚨</span>
                                                    <span v-else-if="a.level === 'warning'" class="text-base">⚠️</span>
                                                    <span v-else class="text-base">ℹ️</span>
                                                    <p class="font-medium text-sm">{{ a.title }}</p>
                                                </div>
                                                <p class="mt-1 text-xs text-muted-foreground">
                                                    Đối tượng: <strong>{{ a.audience }}</strong>
                                                    · {{ a.published_at ?? 'Chưa phát' }}
                                                </p>
                                            </div>
                                            <Badge :class="statusBadge[String(a.status)] || 'bg-slate-100 text-slate-600'">
                                                {{ a.status }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col items-center gap-2 py-12 text-center text-muted-foreground">
                                    <MonitorSpeaker class="size-10 text-slate-300" />
                                    <p class="text-sm font-medium">Chưa có thông báo nào</p>
                                    <p class="text-xs">Tạo thông báo để broadcast đến tất cả màn hình</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </TabsContent>

            <!-- ============================================================ -->
            <!-- TAB 4: ALERT RULES                                           -->
            <!-- ============================================================ -->
            <TabsContent value="alerts" class="mt-6">
                <div class="grid gap-6 xl:grid-cols-5">
                    <!-- Form tạo rule -->
                    <Card class="xl:col-span-2">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Siren class="size-4 text-amber-500" /> Tạo Alert Rule
                            </CardTitle>
                            <CardDescription>Hệ thống tự gửi cảnh báo qua Telegram/Discord khi vượt ngưỡng</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form class="space-y-4" @submit.prevent="submitRule">
                                <div class="grid gap-1.5">
                                    <Label>Tên rule</Label>
                                    <Input v-model="ruleForm.name" placeholder="API Error Rate > 5%" />
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-1.5">
                                        <Label>Metric</Label>
                                        <Select v-model="ruleForm.metric_key">
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="api_error_rate">api_error_rate (%)</SelectItem>
                                                <SelectItem value="slow_queries">slow_queries (count)</SelectItem>
                                                <SelectItem value="failed_jobs">failed_jobs (count)</SelectItem>
                                                <SelectItem value="queue_backlog">queue_backlog (count)</SelectItem>
                                                <SelectItem value="pulse_exceptions">pulse_exceptions</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label>Operator</Label>
                                        <Select v-model="ruleForm.operator">
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value=">">&#62; lớn hơn</SelectItem>
                                                <SelectItem value=">=">&#62;= lớn hơn bằng</SelectItem>
                                                <SelectItem value="<">&#60; nhỏ hơn</SelectItem>
                                                <SelectItem value="<=">&#60;= nhỏ hơn bằng</SelectItem>
                                                <SelectItem value="=">=  bằng</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-1.5">
                                        <Label>Threshold</Label>
                                        <Input v-model="ruleForm.threshold" type="number" step="0.01" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label>Cooldown (phút)</Label>
                                        <Input v-model="ruleForm.cooldown_minutes" type="number" min="1" />
                                    </div>
                                </div>
                                <Button type="submit" class="w-full" :disabled="ruleForm.processing">
                                    <PlusCircle class="mr-2 size-4" />
                                    {{ ruleForm.processing ? 'Đang tạo...' : 'Tạo Alert Rule' }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Danh sách rules -->
                    <div class="xl:col-span-3">
                        <Card class="h-full">
                            <CardHeader class="pb-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <CardTitle class="text-base">Alert Rules ({{ rules.length }})</CardTitle>
                                        <CardDescription>Rules kích hoạt gửi cảnh báo tự động</CardDescription>
                                    </div>
                                    <Button variant="outline" size="sm" @click="runAlertCheck">
                                        <Siren class="mr-2 size-4" /> Quét ngay
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div v-if="rules.length" class="space-y-3">
                                    <div
                                        v-for="rule in rules"
                                        :key="rule.id"
                                        class="flex items-center justify-between rounded-xl border px-4 py-3"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div :class="['size-2 rounded-full', rule.is_active ? 'bg-emerald-500' : 'bg-slate-300']" />
                                            <div>
                                                <p class="text-sm font-medium">{{ rule.name }}</p>
                                                <p class="text-xs text-muted-foreground font-mono">
                                                    {{ rule.metric_key }} {{ rule.operator }} {{ rule.threshold }}
                                                    · cooldown {{ rule.cooldown_minutes }}m
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span v-for="ch in (rule.channels ?? [])" :key="ch" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ ch }}</span>
                                            <Badge :class="rule.is_active ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'">
                                                {{ rule.is_active ? 'Active' : 'Inactive' }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col items-center gap-2 py-12 text-center text-muted-foreground">
                                    <Siren class="size-10 text-slate-300" />
                                    <p class="text-sm font-medium">Chưa có rule nào</p>
                                    <p class="text-xs">Tạo rule để hệ thống tự cảnh báo khi vượt ngưỡng</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </TabsContent>

            <!-- ============================================================ -->
            <!-- TAB 5: KNOWLEDGE BASE                                        -->
            <!-- ============================================================ -->
            <TabsContent value="kb" class="mt-6">
                <div class="grid gap-6 xl:grid-cols-5">
                    <!-- Form tạo bài viết -->
                    <Card class="xl:col-span-2">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <BookOpenText class="size-4 text-emerald-500" /> Thêm Bài Viết
                            </CardTitle>
                            <CardDescription>Tài liệu hướng dẫn tự phục vụ cho nhà hàng</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form class="space-y-4" @submit.prevent="submitArticle">
                                <div class="grid gap-1.5">
                                    <Label>Danh mục</Label>
                                    <Input v-model="articleForm.category" placeholder="onboarding / billing / kitchen / realtime" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Tiêu đề</Label>
                                    <Input v-model="articleForm.title" placeholder="Hướng dẫn cấu hình màn hình bếp" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Tóm tắt</Label>
                                    <Input v-model="articleForm.summary" placeholder="Mô tả ngắn (hiển thị trong danh sách)" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Nội dung</Label>
                                    <textarea
                                        v-model="articleForm.content"
                                        rows="5"
                                        placeholder="Nội dung hướng dẫn chi tiết..."
                                        class="w-full rounded-md border bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Video hướng dẫn (URL)</Label>
                                    <Input v-model="articleForm.video_url" placeholder="https://youtube.com/..." />
                                </div>
                                <div class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                                    <input id="is_published" v-model="articleForm.is_published" type="checkbox" class="size-4 rounded border-emerald-300 accent-emerald-600" />
                                    <label for="is_published" class="text-sm font-medium text-emerald-800">Xuất bản ngay cho nhà hàng xem</label>
                                </div>
                                <Button type="submit" class="w-full" :disabled="articleForm.processing">
                                    <BookOpenText class="mr-2 size-4" />
                                    {{ articleForm.processing ? 'Đang lưu...' : 'Thêm bài viết' }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Danh sách bài viết -->
                    <div class="xl:col-span-3">
                        <Card class="h-full">
                            <CardHeader class="pb-3">
                                <CardTitle class="text-base">Kho tài liệu ({{ articles.length }})</CardTitle>
                                <CardDescription>Self-service knowledge base · Nhà hàng tự tra cứu và giải quyết sự cố</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div v-if="articles.length" class="space-y-3">
                                    <div
                                        v-for="article in articles"
                                        :key="article.id"
                                        class="flex items-center justify-between rounded-xl border px-4 py-3 transition-colors hover:bg-muted/30"
                                    >
                                        <div class="flex items-center gap-3 min-w-0 flex-1">
                                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100">
                                                <BookOpenText class="size-4 text-emerald-600" />
                                            </div>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium">{{ article.title }}</p>
                                                <p class="text-xs text-muted-foreground">
                                                    {{ article.category }} · {{ article.view_count }} lượt xem
                                                    <span v-if="article.video_url"> · 🎬 video</span>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 ml-3">
                                            <a
                                                v-if="article.video_url"
                                                :href="article.video_url"
                                                target="_blank"
                                                class="text-muted-foreground hover:text-foreground"
                                            >
                                                <Eye class="size-4" />
                                            </a>
                                            <Badge :class="article.is_published ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'">
                                                {{ article.is_published ? 'Published' : 'Draft' }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col items-center gap-2 py-12 text-center text-muted-foreground">
                                    <BookOpenText class="size-10 text-slate-300" />
                                    <p class="text-sm font-medium">Chưa có bài viết nào</p>
                                    <p class="text-xs">Thêm tài liệu hướng dẫn để nhà hàng tự tra cứu</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
