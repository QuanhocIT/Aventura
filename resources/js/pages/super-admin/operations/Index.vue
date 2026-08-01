<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity, AlertTriangle, Database, Mail, RefreshCw, RotateCcw, Server, Wrench } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Service = { service_key: string; name: string; status: string; latency?: number; last_checked_at?: string; error_message?: string; is_maintenance: boolean; host?: string; port?: number };
type FailedJob = { id: number; uuid: string; queue: string; job: string; exception: string; failed_at: string };
type Webhook = { id: number; event: string; status: string; attempts: number; response_code?: number; response_body?: string; restaurant_name?: string; created_at: string };

const props = defineProps<{
    services: Service[];
    queue: { queued: number; by_queue: Record<string, number>; failed_count: number; failed_jobs: FailedJob[] };
    failedJobs: FailedJob[];
    webhooks: { pending: number; failed: number; success_24h: number; deliveries: Webhook[] };
    scheduler: { status: string; last_run_at?: string; task_count: number };
    probes: { database: { status: string; latency_ms?: number; error?: string }; cache: { status: string; latency_ms?: number; error?: string }; email: { status: string; driver?: string } };
    maintenanceModes: Array<{ id: number; scope: string; restaurant_id?: number; restaurant_name?: string; enabled: boolean; message?: string; starts_at?: string; ends_at?: string }>;
    tenants: Array<{ id: number; name: string; lifecycle_status?: string }>;
    queueDriver: string;
    mailDriver: string;
}>();

const localFailedJobs = ref([...props.failedJobs]);
const localWebhooks = ref([...props.webhooks.deliveries]);
const maintenance = reactive({ scope: 'global', restaurant_id: null as number | null, enabled: true, message: '', starts_at: '', ends_at: '' });
const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

async function postJson(url: string, body?: Record<string, unknown>) {
    const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' }, body: body ? JSON.stringify(body) : undefined });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || 'Request failed');
    return data;
}

async function refresh() {
    window.location.reload();
}

async function retryJob(job: FailedJob) {
    try {
        await postJson(`/super-admin/operations/failed-jobs/${job.uuid}/retry`);
        localFailedJobs.value = localFailedJobs.value.filter((item: FailedJob) => item.uuid !== job.uuid);
        toast.success('Đã đưa job trở lại queue.');
    } catch (error) { toast.error(error instanceof Error ? error.message : 'Không thể retry failed job.'); }
}

async function retryWebhook(webhook: Webhook) {
    try {
        await postJson(`/super-admin/operations/webhooks/${webhook.id}/retry`);
        localWebhooks.value = localWebhooks.value.filter((item: Webhook) => item.id !== webhook.id);
        toast.success('Đã đưa webhook trở lại queue.');
    } catch (error) { toast.error(error instanceof Error ? error.message : 'Không thể retry webhook.'); }
}

async function saveMaintenance() {
    try {
        await postJson('/super-admin/operations/maintenance', {
            ...maintenance,
            restaurant_id: maintenance.scope === 'tenant' ? maintenance.restaurant_id : null,
        });
        toast.success(maintenance.enabled ? 'Đã bật maintenance mode.' : 'Đã tắt maintenance mode.');
        window.location.reload();
    } catch (error) { toast.error(error instanceof Error ? error.message : 'Không thể cập nhật maintenance mode.'); }
}

const statusClass = (status: string) => status === 'online' || status === 'healthy' || status === 'configured' ? 'text-emerald-600' : status === 'offline' || status === 'stale' ? 'text-rose-600' : 'text-amber-600';
</script>

<template>
    <Head title="Operations Center" />
    <div class="space-y-6 p-6">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="text-2xl font-bold tracking-tight">Operations Center</h1><p class="text-muted-foreground">Theo dõi queue, scheduler, webhook, service health và maintenance mode.</p></div><Button variant="outline" @click="refresh"><RefreshCw class="mr-2 h-4 w-4" /> Làm mới</Button></div>

        <div class="grid gap-4 md:grid-cols-4">
            <Card><CardHeader class="pb-2"><CardDescription>Jobs đang chờ</CardDescription><CardTitle class="text-2xl">{{ queue.queued }}</CardTitle></CardHeader><CardContent class="text-xs text-muted-foreground">Driver: {{ queueDriver }}</CardContent></Card>
            <Card><CardHeader class="pb-2"><CardDescription>Failed jobs</CardDescription><CardTitle class="text-2xl text-rose-600">{{ queue.failed_count }}</CardTitle></CardHeader><CardContent class="text-xs text-muted-foreground">Retry trực tiếp từ danh sách</CardContent></Card>
            <Card><CardHeader class="pb-2"><CardDescription>Webhook lỗi / chờ</CardDescription><CardTitle class="text-2xl">{{ webhooks.failed }} / {{ webhooks.pending }}</CardTitle></CardHeader><CardContent class="text-xs text-muted-foreground">{{ webhooks.success_24h }} thành công trong 24h</CardContent></Card>
            <Card><CardHeader class="pb-2"><CardDescription>Scheduler</CardDescription><CardTitle :class="statusClass(scheduler.status)" class="text-2xl">{{ scheduler.status }}</CardTitle></CardHeader><CardContent class="text-xs text-muted-foreground">{{ scheduler.task_count }} tasks · {{ scheduler.last_run_at || 'Chưa có heartbeat' }}</CardContent></Card>
        </div>

        <Card><CardHeader><CardTitle class="flex items-center gap-2"><Server class="h-4 w-4" /> Service health</CardTitle><CardDescription>MySQL, Redis/Queue, Reverb, Meilisearch và email service.</CardDescription></CardHeader><CardContent class="grid gap-3 md:grid-cols-2 xl:grid-cols-5"><div v-for="service in services" :key="service.service_key" class="rounded-lg border p-3"><div class="flex items-center justify-between gap-2"><p class="font-medium">{{ service.name }}</p><span :class="statusClass(service.status)" class="text-xs font-semibold">{{ service.status }}</span></div><p class="mt-1 text-xs text-muted-foreground">{{ service.host }}:{{ service.port }} · {{ service.latency || 0 }}ms</p><p class="text-xs text-muted-foreground">{{ service.last_checked_at || 'Chưa kiểm tra' }}</p><p v-if="service.error_message" class="mt-1 line-clamp-2 text-xs text-rose-600">{{ service.error_message }}</p><p v-if="service.is_maintenance" class="mt-1 text-xs text-amber-600">Đang maintenance</p></div></CardContent></Card>

        <div class="grid gap-6 md:grid-cols-3"><Card><CardHeader><CardTitle class="flex items-center gap-2"><Database class="h-4 w-4" /> Database</CardTitle></CardHeader><CardContent><p :class="statusClass(probes.database.status)" class="font-semibold">{{ probes.database.status }}</p><p class="text-xs text-muted-foreground">{{ probes.database.latency_ms || 0 }}ms</p></CardContent></Card><Card><CardHeader><CardTitle class="flex items-center gap-2"><Activity class="h-4 w-4" /> Cache</CardTitle></CardHeader><CardContent><p :class="statusClass(probes.cache.status)" class="font-semibold">{{ probes.cache.status }}</p><p class="text-xs text-muted-foreground">{{ probes.cache.latency_ms || 0 }}ms</p></CardContent></Card><Card><CardHeader><CardTitle class="flex items-center gap-2"><Mail class="h-4 w-4" /> Email</CardTitle></CardHeader><CardContent><p :class="statusClass(probes.email.status)" class="font-semibold">{{ probes.email.status }}</p><p class="text-xs text-muted-foreground">Driver: {{ mailDriver }}</p></CardContent></Card></div>

        <div class="grid gap-6 xl:grid-cols-2">
            <Card><CardHeader><CardTitle class="flex items-center gap-2"><AlertTriangle class="h-4 w-4 text-rose-500" /> Failed jobs</CardTitle><CardDescription>{{ localFailedJobs.length }} job gần nhất trong failed_jobs.</CardDescription></CardHeader><CardContent class="space-y-3"><div v-for="job in localFailedJobs" :key="job.uuid" class="rounded-lg border p-3"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate font-medium">{{ job.job }}</p><p class="text-xs text-muted-foreground">{{ job.queue }} · {{ job.failed_at }}</p><p class="mt-1 line-clamp-2 text-xs text-rose-600">{{ job.exception }}</p></div><Button variant="outline" size="sm" class="shrink-0" @click="retryJob(job)"><RotateCcw class="mr-1 h-3.5 w-3.5" /> Retry</Button></div></div><p v-if="!localFailedJobs.length" class="py-5 text-center text-sm text-muted-foreground">Không có failed job.</p></CardContent></Card>
            <Card><CardHeader><CardTitle class="flex items-center gap-2"><RefreshCw class="h-4 w-4 text-amber-500" /> Webhook deliveries</CardTitle><CardDescription>Retry các delivery pending/failed từ giao diện.</CardDescription></CardHeader><CardContent class="space-y-3"><div v-for="webhook in localWebhooks" :key="webhook.id" class="rounded-lg border p-3"><div class="flex items-start justify-between gap-3"><div><p class="font-medium">{{ webhook.event }}</p><p class="text-xs text-muted-foreground">{{ webhook.restaurant_name || 'Tenant' }} · HTTP {{ webhook.response_code || '—' }} · {{ webhook.attempts }} attempts</p><p class="text-xs text-muted-foreground">{{ webhook.created_at }}</p></div><Button variant="outline" size="sm" @click="retryWebhook(webhook)"><RotateCcw class="mr-1 h-3.5 w-3.5" /> Retry</Button></div></div><p v-if="!localWebhooks.length" class="py-5 text-center text-sm text-muted-foreground">Không có webhook cần xử lý.</p></CardContent></Card>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <Card><CardHeader><CardTitle class="flex items-center gap-2"><Wrench class="h-4 w-4" /> Maintenance mode</CardTitle><CardDescription>Bật toàn hệ thống hoặc chỉ một tenant. Thao tác được audit trước/sau.</CardDescription></CardHeader><CardContent class="space-y-3"><div class="grid gap-3 sm:grid-cols-2"><label class="text-sm">Phạm vi<select v-model="maintenance.scope" class="mt-1 h-9 w-full rounded-md border bg-background px-2"><option value="global">Toàn hệ thống</option><option value="tenant">Một tenant</option></select></label><label v-if="maintenance.scope === 'tenant'" class="text-sm">Tenant<select v-model.number="maintenance.restaurant_id" class="mt-1 h-9 w-full rounded-md border bg-background px-2"><option :value="null">Chọn tenant</option><option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">{{ tenant.name }}</option></select></label></div><label class="flex items-center gap-2 text-sm"><input v-model="maintenance.enabled" type="checkbox" /> Bật maintenance mode</label><label class="text-sm">Thông báo<textarea v-model="maintenance.message" rows="2" class="mt-1 w-full rounded-md border bg-background p-2" placeholder="Hệ thống đang bảo trì..."></textarea></label><Button variant="destructive" @click="saveMaintenance">Lưu maintenance mode</Button></CardContent></Card>
            <Card><CardHeader><CardTitle>Maintenance đang cấu hình</CardTitle><CardDescription>Trạng thái global và theo tenant.</CardDescription></CardHeader><CardContent class="space-y-2"><div v-for="mode in maintenanceModes" :key="mode.id" class="flex items-center justify-between rounded border p-3"><div><p class="font-medium">{{ mode.scope === 'global' ? 'Toàn hệ thống' : mode.restaurant_name }}</p><p class="text-xs text-muted-foreground">{{ mode.message || 'Không có thông báo' }}</p></div><span :class="mode.enabled ? 'text-amber-600' : 'text-muted-foreground'" class="text-xs font-semibold">{{ mode.enabled ? 'ENABLED' : 'OFF' }}</span></div><p v-if="!maintenanceModes.length" class="py-5 text-center text-sm text-muted-foreground">Chưa cấu hình maintenance mode.</p></CardContent></Card>
        </div>
    </div>
</template>
