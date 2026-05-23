<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Building2,
    CheckCircle2,
    Clock,
    Crown,
    FileText,
    Gauge,
    ShieldCheck,
    Siren,
    SlidersHorizontal,
    TrendingUp,
    Users,
    XCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type GrowthPoint = {
    label: string;
    month: string;
    new_tenants: number;
    free_to_pro: number;
    conversion_rate: number;
};

type RankedTenant = {
    restaurant_id: number;
    name: string;
    code: string | null;
    orders_count?: number;
    storage_bytes?: number;
    files_count?: number;
};

const props = defineProps<{
    stats: {
        total_restaurants: number;
        active: number;
        suspended: number;
        expired: number;
        total_users: number;
        pro_plan: number;
    };
    saasMetrics: {
        mrr: number;
        arr: number;
        churn_rate: number;
        churned_this_month: number;
        active_subscriptions: number;
        paid_tenants: number;
    };
    tenantGrowth: GrowthPoint[];
    resourceInsights: {
        top_order_restaurants: RankedTenant[];
        top_storage_restaurants: RankedTenant[];
        totals: {
            orders_last_30_days: number;
            storage_bytes: number;
        };
    };
    recentRestaurants: Array<{
        id: number;
        name: string;
        status: string;
        plan: string;
        plan_code: string;
        owner: string;
        created_at: string;
    }>;
    planDistribution: Array<{ name: string; code: string; count: number }>;
    supportOverview: {
        monitoring: {
            failed_jobs: number;
            pending_jobs: number;
            api_error_rate: number;
            slow_queries: number;
        };
        stats: {
            tickets_open: number;
            alerts_open: number;
        };
    };
}>();

const statusColor: Record<string, string> = {
    active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
    suspended: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300',
    expired: 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300',
};

const statusLabel: Record<string, string> = {
    active: 'Hoat dong',
    suspended: 'Tam ngung',
    expired: 'Het han',
};

const formatCurrency = (value: number) => new Intl.NumberFormat('vi-VN').format(value) + ' VND';
const formatBytes = (bytes: number) => {
    if (bytes >= 1024 ** 3) {
return `${(bytes / 1024 ** 3).toFixed(1)} GB`;
}

    if (bytes >= 1024 ** 2) {
return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
}

    if (bytes >= 1024) {
return `${(bytes / 1024).toFixed(1)} KB`;
}

    return `${bytes} B`;
};

const statCards = computed(() => [
    { label: 'Tong nha hang', value: props.stats.total_restaurants, icon: Building2, color: 'text-sky-600' },
    { label: 'Dang hoat dong', value: props.stats.active, icon: CheckCircle2, color: 'text-emerald-600' },
    { label: 'Goi Pro', value: props.stats.pro_plan, icon: Crown, color: 'text-violet-600' },
    { label: 'Tong nguoi dung', value: props.stats.total_users, icon: Users, color: 'text-amber-600' },
    { label: 'MRR', value: formatCurrency(props.saasMetrics.mrr), icon: TrendingUp, color: 'text-cyan-600' },
    { label: 'ARR', value: formatCurrency(props.saasMetrics.arr), icon: Gauge, color: 'text-indigo-600' },
]);

const growthPath = computed(() => {
    const values = props.tenantGrowth.map((point) => point.new_tenants);
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const span = Math.max(max - min, 1);
    const width = 100;
    const height = 44;

    return props.tenantGrowth
        .map((point, index) => {
            const x = props.tenantGrowth.length <= 1 ? 0 : (index / (props.tenantGrowth.length - 1)) * width;
            const y = height - ((point.new_tenants - min) / span) * height;

            return `${index === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`;
        })
        .join(' ');
});

const freeToProPath = computed(() => {
    const values = props.tenantGrowth.map((point) => point.free_to_pro);
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const span = Math.max(max - min, 1);
    const width = 100;
    const height = 44;

    return props.tenantGrowth
        .map((point, index) => {
            const x = props.tenantGrowth.length <= 1 ? 0 : (index / (props.tenantGrowth.length - 1)) * width;
            const y = height - ((point.free_to_pro - min) / span) * height;

            return `${index === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`;
        })
        .join(' ');
});
</script>

<template>
    <Head title="Super Admin Analytics" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Dashboard phan tich SaaS</h1>
                <p class="text-sm text-muted-foreground">Trung tam du lieu vi mo cho Super Admin</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link href="/super-admin/restaurants" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium hover:bg-muted transition-colors">
                    <Building2 class="size-4" /> Tenants
                </Link>
                <Link href="/super-admin/support" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium hover:bg-muted transition-colors">
                    <Siren class="size-4" /> Support
                </Link>
                <Link href="/super-admin/accounts" class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium hover:bg-muted transition-colors">
                    <ShieldCheck class="size-4" /> Accounts
                </Link>
                <Link href="/super-admin/audit-logs" class="inline-flex items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors">
                    <FileText class="size-4" /> Audit
                </Link>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Card v-for="card in statCards" :key="card.label">
                <CardContent class="flex items-center justify-between p-5">
                    <div>
                        <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                        <p class="text-2xl font-semibold tracking-tight">{{ card.value }}</p>
                    </div>
                    <component :is="card.icon" :class="['size-9', card.color]" />
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
            <Card>
                <CardHeader class="flex-row items-center justify-between gap-4 pb-2">
                    <div>
                        <CardTitle class="text-base">Tenant growth & conversion</CardTitle>
                        <p class="text-sm text-muted-foreground">New tenant registrations vs Free to Pro conversion</p>
                    </div>
                    <Badge variant="secondary" class="gap-1">
                        <TrendingUp class="size-3.5" /> {{ tenantGrowth.length }} mo
                    </Badge>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-lg border p-4">
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="font-medium">Nha hang moi</span>
                                <span class="text-muted-foreground">{{ tenantGrowth.at(-1)?.new_tenants ?? 0 }}</span>
                            </div>
                            <svg viewBox="0 0 100 44" class="h-24 w-full">
                                <path :d="growthPath" fill="none" class="stroke-sky-500" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div class="rounded-lg border p-4">
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="font-medium">Free sang Pro</span>
                                <span class="text-muted-foreground">{{ tenantGrowth.at(-1)?.free_to_pro ?? 0 }}</span>
                            </div>
                            <svg viewBox="0 0 100 44" class="h-24 w-full">
                                <path :d="freeToProPath" fill="none" class="stroke-violet-500" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Thang</th>
                                    <th class="px-4 py-3 font-medium">New tenant</th>
                                    <th class="px-4 py-3 font-medium">Free to Pro</th>
                                    <th class="px-4 py-3 font-medium">Conversion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="point in tenantGrowth" :key="point.month" class="border-t">
                                    <td class="px-4 py-3 font-medium">{{ point.label }}</td>
                                    <td class="px-4 py-3">{{ point.new_tenants }}</td>
                                    <td class="px-4 py-3">{{ point.free_to_pro }}</td>
                                    <td class="px-4 py-3">{{ point.conversion_rate }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <div class="space-y-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">SaaS health</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex items-center justify-between text-sm"><span class="text-muted-foreground">Active subscription</span><span class="font-semibold">{{ saasMetrics.active_subscriptions }}</span></div>
                        <div class="flex items-center justify-between text-sm"><span class="text-muted-foreground">Paid tenants</span><span class="font-semibold">{{ saasMetrics.paid_tenants }}</span></div>
                        <div class="flex items-center justify-between text-sm"><span class="text-muted-foreground">Churn rate</span><span class="font-semibold">{{ saasMetrics.churn_rate }}%</span></div>
                        <div class="flex items-center justify-between text-sm"><span class="text-muted-foreground">Churned this month</span><span class="font-semibold">{{ saasMetrics.churned_this_month }}</span></div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">Resource usage</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex items-center justify-between text-sm"><span class="text-muted-foreground">Orders 30 days</span><span class="font-semibold">{{ resourceInsights.totals.orders_last_30_days }}</span></div>
                        <div class="flex items-center justify-between text-sm"><span class="text-muted-foreground">Cloud storage</span><span class="font-semibold">{{ formatBytes(resourceInsights.totals.storage_bytes) }}</span></div>
                        <div class="flex items-center justify-between text-sm"><span class="text-muted-foreground">MRR</span><span class="font-semibold">{{ formatCurrency(saasMetrics.mrr) }}</span></div>
                        <div class="flex items-center justify-between text-sm"><span class="text-muted-foreground">ARR</span><span class="font-semibold">{{ formatCurrency(saasMetrics.arr) }}</span></div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Top tenant by order volume</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-for="item in resourceInsights.top_order_restaurants" :key="item.restaurant_id" class="flex items-center justify-between rounded-lg border px-3 py-2">
                        <div>
                            <p class="font-medium">{{ item.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ item.code ?? 'tenant' }}</p>
                        </div>
                        <Badge variant="secondary">{{ item.orders_count }} orders</Badge>
                    </div>
                    <p v-if="!resourceInsights.top_order_restaurants.length" class="text-sm text-muted-foreground">Chua co du lieu order.</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">Top tenant by cloud storage</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-for="item in resourceInsights.top_storage_restaurants" :key="item.restaurant_id" class="flex items-center justify-between rounded-lg border px-3 py-2">
                        <div>
                            <p class="font-medium">{{ item.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ item.files_count ?? 0 }} files</p>
                        </div>
                        <Badge variant="secondary">{{ formatBytes(item.storage_bytes ?? 0) }}</Badge>
                    </div>
                    <p v-if="!resourceInsights.top_storage_restaurants.length" class="text-sm text-muted-foreground">Chua co du lieu storage.</p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
            <Card>
                <CardHeader class="flex-row items-center justify-between pb-2">
                    <CardTitle class="text-base">Nha hang moi nhat</CardTitle>
                    <Link href="/super-admin/restaurants" class="text-xs text-muted-foreground hover:text-foreground">Xem tat ca</Link>
                </CardHeader>
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead class="border-b text-left text-xs text-muted-foreground">
                            <tr>
                                <th class="px-6 py-3 font-medium">Ten</th>
                                <th class="px-4 py-3 font-medium">Goi</th>
                                <th class="px-4 py-3 font-medium">Trang thai</th>
                                <th class="px-4 py-3 font-medium">Ngay tao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in recentRestaurants" :key="r.id" class="border-b last:border-0 hover:bg-muted/50">
                                <td class="px-6 py-3">
                                    <Link :href="`/super-admin/restaurants/${r.id}`" class="font-medium hover:underline">{{ r.name }}</Link>
                                    <p class="text-xs text-muted-foreground">{{ r.owner }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="r.plan_code?.toLowerCase() === 'pro' ? 'font-semibold text-violet-600' : 'text-muted-foreground'">{{ r.plan }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', statusColor[r.status]]">{{ statusLabel[r.status] ?? r.status }}</span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ r.created_at }}</td>
                            </tr>
                            <tr v-if="!recentRestaurants.length">
                                <td colspan="4" class="px-6 py-8 text-center text-muted-foreground">Chua co nha hang nao</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <div class="space-y-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">Plan distribution</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div v-for="plan in planDistribution" :key="plan.code" class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Crown v-if="plan.code?.toLowerCase() === 'pro'" class="size-4 text-violet-500" />
                                <SlidersHorizontal v-else class="size-4 text-sky-500" />
                                <span class="text-sm font-medium">{{ plan.name }}</span>
                            </div>
                            <span class="text-sm font-semibold">{{ plan.count }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">System signals</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div class="flex items-center justify-between"><span class="flex items-center gap-1.5 text-muted-foreground"><CheckCircle2 class="size-4 text-emerald-500" /> Active</span><span class="font-semibold">{{ stats.active }}</span></div>
                        <div class="flex items-center justify-between"><span class="flex items-center gap-1.5 text-muted-foreground"><Clock class="size-4 text-amber-500" /> Suspended</span><span class="font-semibold">{{ stats.suspended }}</span></div>
                        <div class="flex items-center justify-between"><span class="flex items-center gap-1.5 text-muted-foreground"><XCircle class="size-4 text-rose-500" /> Expired</span><span class="font-semibold">{{ stats.expired }}</span></div>
                        <div class="mt-2 border-t pt-2 text-xs text-muted-foreground">Queue fail: {{ supportOverview.monitoring.failed_jobs }} | Pending: {{ supportOverview.monitoring.pending_jobs }}</div>
                        <div class="text-xs text-muted-foreground">API error: {{ supportOverview.monitoring.api_error_rate }}% | Slow query: {{ supportOverview.monitoring.slow_queries }}</div>
                        <div class="text-xs text-muted-foreground">Ticket open: {{ supportOverview.stats.tickets_open }} | Alert open: {{ supportOverview.stats.alerts_open }}</div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>


