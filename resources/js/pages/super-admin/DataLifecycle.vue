<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Database, HardDrive, ShieldAlert, Trash2 } from 'lucide-vue-next';
import { PageHeader } from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Run = {
    id: number;
    action: string;
    status: string;
    dry_run: boolean;
    requested_by: string;
    approved_by: string | null;
    requested_at: string | null;
    finished_at: string | null;
    result: Record<string, unknown> | null;
    error_message: string | null;
};

const props = defineProps<{
    summary: {
        snapshot_date: string | null;
        database_size_mb: number;
        database_bytes: number;
        estimated_tenant_bytes: number;
        media_bytes: number;
        tenant_count: number;
        database_limit_gb: number | null;
        database_percent: number | null;
        legal_hold_tenants: number;
        pending_cleanup_runs: number;
        failed_cleanup_runs: number;
        scheduler: { healthy: boolean; last_run_at: string | null; minutes_since_run: number | null };
        top_tenants: Array<{ name: string; code: string | null; total_bytes: number; growth_bytes: number }>;
    };
    policies: Record<string, unknown>;
    runs: Run[];
}>();

const formatBytes = (bytes: number) => {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** index).toFixed(index > 1 ? 2 : 0)} ${units[index]}`;
};

function createPreview(action: string) {
    router.post('/super-admin/data-lifecycle/runs', { action }, { preserveScroll: true });
}

function approve(run: Run) {
    if (run.status !== 'pending') return;
    router.post(`/super-admin/data-lifecycle/runs/${run.id}/approve`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Vòng đời dữ liệu" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Vòng đời dữ liệu & Dung lượng"
            subtitle="Theo dõi tăng trưởng, tạo dry-run và phê duyệt các thao tác dọn dữ liệu."
            :icon="Database"
        />

        <div class="grid gap-4 md:grid-cols-4">
            <Card><CardHeader><CardTitle class="text-xs">Database thực tế</CardTitle></CardHeader><CardContent class="text-2xl font-bold">{{ formatBytes(summary.database_bytes) }}</CardContent></Card>
            <Card><CardHeader><CardTitle class="text-xs">Dữ liệu tenant ước tính</CardTitle></CardHeader><CardContent class="text-2xl font-bold">{{ formatBytes(summary.estimated_tenant_bytes) }}</CardContent></Card>
            <Card><CardHeader><CardTitle class="text-xs">File media</CardTitle></CardHeader><CardContent class="text-2xl font-bold">{{ formatBytes(summary.media_bytes) }}</CardContent></Card>
            <Card><CardHeader><CardTitle class="text-xs">Legal hold</CardTitle></CardHeader><CardContent class="text-2xl font-bold">{{ summary.legal_hold_tenants }}</CardContent></Card>
        </div>

        <Card>
            <CardHeader><CardTitle>Thao tác an toàn</CardTitle></CardHeader>
            <CardContent class="flex flex-wrap gap-2">
                <Button variant="outline" @click="createPreview('technical')"><HardDrive class="mr-2 size-4" />Technical cleanup</Button>
                <Button variant="outline" @click="createPreview('audit')"><ShieldAlert class="mr-2 size-4" />Audit archive</Button>
                <Button variant="outline" @click="createPreview('media')"><Trash2 class="mr-2 size-4" />File mồ côi</Button>
                <Button variant="outline" @click="createPreview('backups')">Backup retention</Button>
                <Button variant="destructive" @click="createPreview('orders-purge')">Tạo dry-run purge archive đơn hàng</Button>
                <Button variant="outline" @click="createPreview('all')">Tạo báo cáo toàn bộ</Button>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Tenant sử dụng nhiều dung lượng nhất</CardTitle></CardHeader>
            <CardContent>
                <div v-if="summary.top_tenants.length" class="divide-y">
                    <div v-for="tenant in summary.top_tenants" :key="tenant.code ?? tenant.name" class="flex items-center justify-between py-2 text-sm">
                        <span>{{ tenant.name }} <span class="text-muted-foreground">({{ tenant.code ?? '—' }})</span></span>
                        <span class="font-semibold">{{ formatBytes(tenant.total_bytes) }}</span>
                    </div>
                </div>
                <div v-else class="text-sm text-muted-foreground">Chưa có snapshot. Scheduler sẽ tạo snapshot tự động.</div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Cleanup runs</CardTitle></CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b"><th class="py-2">#</th><th>Action</th><th>Status</th><th>Người tạo</th><th>Thực hiện</th></tr></thead>
                    <tbody>
                        <tr v-for="run in runs" :key="run.id" class="border-b last:border-0">
                            <td class="py-2">{{ run.id }}</td><td>{{ run.action }}</td><td>{{ run.status }}</td><td>{{ run.requested_by }}</td>
                            <td><Button v-if="run.status === 'pending'" size="sm" @click="approve(run)">Phê duyệt & chạy</Button><span v-else>{{ run.finished_at ?? '—' }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
