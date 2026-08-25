<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    Database,
    ListTodo,
    Mail,
    RefreshCw,
    RotateCcw,
    Server,
    Webhook,
    Wrench,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    PageHeader,
    SectionCard,
    StatCard,
    StatusBadge,
} from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Service = {
    service_key: string;
    name: string;
    status: string;
    latency?: number;
    last_checked_at?: string;
    error_message?: string;
    is_maintenance: boolean;
    host?: string;
    port?: number;
};

type FailedJob = {
    id: number;
    uuid: string;
    queue: string;
    job: string;
    exception: string;
    failed_at: string;
};

type Webhook = {
    id: number;
    event: string;
    status: string;
    attempts: number;
    response_code?: number;
    response_body?: string;
    restaurant_name?: string;
    created_at: string;
};

type ApiError = Error & {
    code?: string;
    redirect?: string;
};

const props = defineProps<{
    services: Service[];
    queue: {
        queued: number;
        by_queue: Record<string, number>;
        failed_count: number;
        failed_jobs: FailedJob[];
    };
    failedJobs: FailedJob[];
    webhooks: {
        pending: number;
        failed: number;
        success_24h: number;
        deliveries: Webhook[];
    };
    scheduler: {
        status: string;
        last_run_at?: string;
        task_count: number;
    };
    probes: {
        database: { status: string; latency_ms?: number; error?: string };
        cache: { status: string; latency_ms?: number; error?: string };
        email: { status: string; driver?: string };
    };
    maintenanceModes: Array<{
        id: number;
        scope: string;
        restaurant_id?: number;
        restaurant_name?: string;
        enabled: boolean;
        message?: string;
        starts_at?: string;
        ends_at?: string;
    }>;
    tenants: Array<{ id: number; name: string; lifecycle_status?: string }>;
    queueDriver: string;
    mailDriver: string;
}>();

const localFailedJobs = ref<FailedJob[]>([...props.failedJobs]);
const localWebhooks = ref<Webhook[]>([...props.webhooks.deliveries]);
const PAGE_SIZE = 5;
const failedJobPage = ref(1);
const webhookPage = ref(1);

const failedJobPageCount = computed(() =>
    Math.max(1, Math.ceil(localFailedJobs.value.length / PAGE_SIZE)),
);
const webhookPageCount = computed(() =>
    Math.max(1, Math.ceil(localWebhooks.value.length / PAGE_SIZE)),
);
const failedJobPageNumbers = computed(() =>
    Array.from({ length: failedJobPageCount.value }, (_, index) => index + 1),
);
const webhookPageNumbers = computed(() =>
    Array.from({ length: webhookPageCount.value }, (_, index) => index + 1),
);
const paginatedFailedJobs = computed(() => {
    const start = (failedJobPage.value - 1) * PAGE_SIZE;

    return localFailedJobs.value.slice(start, start + PAGE_SIZE);
});
const paginatedWebhooks = computed(() => {
    const start = (webhookPage.value - 1) * PAGE_SIZE;

    return localWebhooks.value.slice(start, start + PAGE_SIZE);
});

watch(localFailedJobs, () => {
    failedJobPage.value = Math.min(
        failedJobPage.value,
        failedJobPageCount.value,
    );
});

watch(localWebhooks, () => {
    webhookPage.value = Math.min(webhookPage.value, webhookPageCount.value);
});
const maintenance = reactive({
    scope: 'global',
    restaurant_id: null as number | null,
    enabled: true,
    message: '',
    starts_at: '',
    ends_at: '',
});

const serviceLabels: Record<string, string> = {
    mysql: 'Cơ sở dữ liệu MySQL',
    redis: 'Bộ nhớ đệm & hàng đợi Redis',
    reverb: 'WebSocket Laravel Reverb',
    meilisearch: 'Công cụ tìm kiếm Meilisearch',
    email_service: 'Dịch vụ thư điện tử',
};

const csrf = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
        ?.content || '';

async function postJson(url: string, body?: Record<string, unknown>) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(
            data.message || 'Yêu cầu không thành công.',
        ) as ApiError;

        error.code = data.code;
        error.redirect = data.redirect;

        throw error;
    }

    return data;
}

function redirectToTwoFactorConfirmation(error: unknown): boolean {
    const apiError = error as ApiError;

    if (apiError?.code !== 'superadmin_2fa_required' || !apiError.redirect) {
        return false;
    }

    router.visit(apiError.redirect);

    return true;
}

function refresh() {
    router.reload({ preserveScroll: true });
}

async function retryJob(job: FailedJob) {
    try {
        await postJson(`/super-admin/operations/failed-jobs/${job.uuid}/retry`);
        localFailedJobs.value = localFailedJobs.value.filter(
            (item) => item.uuid !== job.uuid,
        );
        toast.success('Đã đưa công việc trở lại hàng đợi.');
    } catch (error) {
        if (redirectToTwoFactorConfirmation(error)) {
            return;
        }

        toast.error(
            error instanceof Error
                ? error.message
                : 'Không thể thử lại công việc lỗi.',
        );
    }
}

async function retryWebhook(webhook: Webhook) {
    try {
        await postJson(`/super-admin/operations/webhooks/${webhook.id}/retry`);
        localWebhooks.value = localWebhooks.value.filter(
            (item) => item.id !== webhook.id,
        );
        toast.success('Đã đưa webhook trở lại hàng đợi.');
    } catch (error) {
        if (redirectToTwoFactorConfirmation(error)) {
            return;
        }

        toast.error(
            error instanceof Error
                ? error.message
                : 'Không thể thử lại webhook.',
        );
    }
}

async function saveMaintenance() {
    try {
        await postJson('/super-admin/operations/maintenance', {
            ...maintenance,
            restaurant_id:
                maintenance.scope === 'tenant'
                    ? maintenance.restaurant_id
                    : null,
        });
        toast.success(
            maintenance.enabled
                ? 'Đã bật chế độ bảo trì.'
                : 'Đã tắt chế độ bảo trì.',
        );
        router.reload({ preserveScroll: true });
    } catch (error) {
        if (redirectToTwoFactorConfirmation(error)) {
            return;
        }

        toast.error(
            error instanceof Error
                ? error.message
                : 'Không thể cập nhật chế độ bảo trì.',
        );
    }
}

function normalizedStatus(status: string) {
    const value = status.toLowerCase();

    if (['online', 'healthy', 'configured', 'up'].includes(value)) {
        return 'healthy';
    }

    if (['offline', 'failed', 'down'].includes(value)) {
        return 'failed';
    }

    if (value === 'stale') {
        return 'warning';
    }

    if (value === 'maintenance') {
        return 'maintenance';
    }

    return 'warning';
}

function statusLabel(status: string) {
    const labels: Record<string, string> = {
        online: 'Hoạt động',
        healthy: 'Ổn định',
        configured: 'Đã cấu hình',
        up: 'Hoạt động',
        offline: 'Ngoại tuyến',
        failed: 'Đang lỗi',
        down: 'Không hoạt động',
        stale: 'Lỗi thời',
        maintenance: 'Bảo trì',
    };

    return labels[status.toLowerCase()] || status;
}

function serviceName(service: Service) {
    return serviceLabels[service.service_key] || service.name;
}

function serviceEndpoint(service: Service) {
    if (!service.host && !service.port) {
        return 'Không có thông tin kết nối';
    }

    return `${service.host || 'localhost'}:${service.port || '—'}`;
}
</script>

<template>
    <Head title="Trung tâm vận hành" />

    <div class="space-y-6 px-6 py-5">
        <PageHeader
            title="Trung tâm vận hành"
            subtitle="Theo dõi hàng đợi, bộ lập lịch, sự kiện tích hợp, sức khỏe dịch vụ và chế độ bảo trì."
            :icon="Server"
        >
            <template #actions>
                <Button variant="outline" @click="refresh">
                    <RefreshCw class="mr-2 size-4" />
                    Làm mới
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Công việc đang chờ"
                :value="queue.queued"
                :icon="ListTodo"
                color="sky"
                :change="`Bộ xử lý: ${queueDriver}`"
            />
            <StatCard
                label="Công việc lỗi"
                :value="queue.failed_count"
                :icon="AlertTriangle"
                color="rose"
                change="Có thể thử lại từ danh sách"
            />
            <StatCard
                label="Tích hợp lỗi / chờ"
                :value="`${webhooks.failed} / ${webhooks.pending}`"
                :icon="Webhook"
                color="amber"
                :change="`${webhooks.success_24h} thành công trong 24 giờ`"
            />
            <StatCard
                label="Bộ lập lịch"
                :value="statusLabel(scheduler.status)"
                :icon="CalendarClock"
                color="violet"
                :change="`${scheduler.task_count} tác vụ · ${scheduler.last_run_at || 'Chưa có lần chạy gần nhất'}`"
            />
        </div>

        <SectionCard accent-color="sky">
            <div class="flex items-start gap-3">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-sky-500/20 bg-sky-500/10 text-sky-600 dark:text-sky-400"
                >
                    <Server class="size-5" />
                </div>
                <div>
                    <h2 class="text-lg font-semibold">Sức khỏe dịch vụ</h2>
                    <p class="text-sm text-muted-foreground">
                        MySQL, Redis/hàng đợi, Reverb, Meilisearch và email.
                    </p>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <Card
                    v-for="service in services"
                    :key="service.service_key"
                    class="border-border/60 bg-background/60"
                >
                    <CardContent class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <p class="leading-snug font-semibold">
                                {{ serviceName(service) }}
                            </p>
                            <StatusBadge
                                :status="normalizedStatus(service.status)"
                                size="sm"
                            >
                                {{ statusLabel(service.status) }}
                            </StatusBadge>
                        </div>
                        <div class="space-y-1 text-xs text-muted-foreground">
                            <p>
                                {{ serviceEndpoint(service) }} ·
                                {{ service.latency || 0 }}ms
                            </p>
                            <p>
                                {{ service.last_checked_at || 'Chưa kiểm tra' }}
                            </p>
                        </div>
                        <p
                            v-if="service.error_message"
                            class="line-clamp-2 text-xs text-rose-600 dark:text-rose-400"
                        >
                            {{ service.error_message }}
                        </p>
                        <p
                            v-if="service.is_maintenance"
                            class="text-xs text-amber-600 dark:text-amber-400"
                        >
                            Đang trong chế độ bảo trì
                        </p>
                    </CardContent>
                </Card>
            </div>
        </SectionCard>

        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Database class="size-4 text-primary" />
                        Cơ sở dữ liệu
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <StatusBadge
                        :status="normalizedStatus(probes.database.status)"
                    >
                        {{ statusLabel(probes.database.status) }}
                    </StatusBadge>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Độ trễ: {{ probes.database.latency_ms || 0 }}ms
                    </p>
                    <p
                        v-if="probes.database.error"
                        class="mt-1 text-xs text-rose-600"
                    >
                        {{ probes.database.error }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Activity class="size-4 text-primary" />
                        Bộ nhớ đệm
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <StatusBadge
                        :status="normalizedStatus(probes.cache.status)"
                    >
                        {{ statusLabel(probes.cache.status) }}
                    </StatusBadge>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Độ trễ: {{ probes.cache.latency_ms || 0 }}ms
                    </p>
                    <p
                        v-if="probes.cache.error"
                        class="mt-1 text-xs text-rose-600"
                    >
                        {{ probes.cache.error }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Mail class="size-4 text-primary" />
                        Thư điện tử
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <StatusBadge
                        :status="normalizedStatus(probes.email.status)"
                    >
                        {{ statusLabel(probes.email.status) }}
                    </StatusBadge>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Bộ gửi:
                        {{
                            mailDriver || probes.email.driver || 'Chưa cấu hình'
                        }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <AlertTriangle class="size-4 text-rose-500" />
                        Công việc bị lỗi
                    </CardTitle>
                    <CardDescription>
                        {{ localFailedJobs.length }} công việc gần nhất trong
                        danh sách lỗi.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="job in paginatedFailedJobs"
                        :key="job.uuid"
                        class="rounded-xl border border-border/60 bg-muted/10 p-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium">
                                    {{ job.job }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ job.queue }} · {{ job.failed_at }}
                                </p>
                                <p
                                    class="mt-1 line-clamp-2 text-xs text-rose-600 dark:text-rose-400"
                                >
                                    {{ job.exception }}
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                class="shrink-0"
                                @click="retryJob(job)"
                            >
                                <RotateCcw class="mr-1 size-3.5" />
                                Thử lại
                            </Button>
                        </div>
                    </div>
                    <div
                        v-if="failedJobPageCount > 1"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-border/60 pt-3"
                    >
                        <span class="text-[11px] text-muted-foreground">
                            Trang {{ failedJobPage }} /
                            {{ failedJobPageCount }} ·
                            {{ localFailedJobs.length }} dòng
                        </span>
                        <div class="flex flex-wrap items-center gap-1">
                            <button
                                type="button"
                                class="inline-flex size-7 items-center justify-center rounded-md border border-border/60 text-muted-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="failedJobPage === 1"
                                aria-label="Trang công việc lỗi trước"
                                @click="failedJobPage--"
                            >
                                <ChevronLeft class="size-4" />
                            </button>
                            <button
                                v-for="page in failedJobPageNumbers"
                                :key="`failed-${page}`"
                                type="button"
                                class="inline-flex size-7 items-center justify-center rounded-md border text-xs transition-colors"
                                :class="
                                    page === failedJobPage
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border/60 text-muted-foreground hover:bg-muted'
                                "
                                :aria-label="`Đến trang công việc lỗi ${page}`"
                                @click="failedJobPage = page"
                            >
                                {{ page }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex size-7 items-center justify-center rounded-md border border-border/60 text-muted-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="failedJobPage === failedJobPageCount"
                                aria-label="Trang công việc lỗi sau"
                                @click="failedJobPage++"
                            >
                                <ChevronRight class="size-4" />
                            </button>
                        </div>
                    </div>
                    <p
                        v-if="!localFailedJobs.length"
                        class="py-5 text-center text-sm text-muted-foreground"
                    >
                        Không có công việc bị lỗi.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Webhook class="size-4 text-amber-500" />
                        Tích hợp cần xử lý
                    </CardTitle>
                    <CardDescription>
                        Thử lại các lần gửi đang chờ hoặc bị lỗi.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="webhook in paginatedWebhooks"
                        :key="webhook.id"
                        class="rounded-xl border border-border/60 bg-muted/10 p-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium">
                                    {{ webhook.event }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ webhook.restaurant_name || 'Nhà hàng' }}
                                    · HTTP {{ webhook.response_code || '—' }} ·
                                    {{ webhook.attempts }} lần thử
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ webhook.created_at }}
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                class="shrink-0"
                                @click="retryWebhook(webhook)"
                            >
                                <RotateCcw class="mr-1 size-3.5" />
                                Thử lại
                            </Button>
                        </div>
                    </div>
                    <div
                        v-if="webhookPageCount > 1"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-border/60 pt-3"
                    >
                        <span class="text-[11px] text-muted-foreground">
                            Trang {{ webhookPage }} / {{ webhookPageCount }} ·
                            {{ localWebhooks.length }} dòng
                        </span>
                        <div class="flex flex-wrap items-center gap-1">
                            <button
                                type="button"
                                class="inline-flex size-7 items-center justify-center rounded-md border border-border/60 text-muted-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="webhookPage === 1"
                                aria-label="Trang webhook trước"
                                @click="webhookPage--"
                            >
                                <ChevronLeft class="size-4" />
                            </button>
                            <button
                                v-for="page in webhookPageNumbers"
                                :key="`webhook-${page}`"
                                type="button"
                                class="inline-flex size-7 items-center justify-center rounded-md border text-xs transition-colors"
                                :class="
                                    page === webhookPage
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border/60 text-muted-foreground hover:bg-muted'
                                "
                                :aria-label="`Đến trang webhook ${page}`"
                                @click="webhookPage = page"
                            >
                                {{ page }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex size-7 items-center justify-center rounded-md border border-border/60 text-muted-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="webhookPage === webhookPageCount"
                                aria-label="Trang webhook sau"
                                @click="webhookPage++"
                            >
                                <ChevronRight class="size-4" />
                            </button>
                        </div>
                    </div>
                    <p
                        v-if="!localWebhooks.length"
                        class="py-5 text-center text-sm text-muted-foreground"
                    >
                        Không có sự kiện tích hợp cần xử lý.
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Wrench class="size-4 text-primary" />
                        Chế độ bảo trì
                    </CardTitle>
                    <CardDescription>
                        Bật cho toàn hệ thống hoặc một nhà hàng. Thao tác được
                        ghi nhật ký.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="space-y-1.5 text-sm">
                            <span>Phạm vi áp dụng</span>
                            <select
                                v-model="maintenance.scope"
                                class="h-9 w-full rounded-lg border border-border bg-background px-3 text-sm"
                            >
                                <option value="global">Toàn hệ thống</option>
                                <option value="tenant">Một nhà hàng</option>
                            </select>
                        </label>
                        <label
                            v-if="maintenance.scope === 'tenant'"
                            class="space-y-1.5 text-sm"
                        >
                            <span>Nhà hàng</span>
                            <select
                                v-model.number="maintenance.restaurant_id"
                                class="h-9 w-full rounded-lg border border-border bg-background px-3 text-sm"
                            >
                                <option :value="null">Chọn nhà hàng</option>
                                <option
                                    v-for="tenant in tenants"
                                    :key="tenant.id"
                                    :value="tenant.id"
                                >
                                    {{ tenant.name }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="maintenance.enabled" type="checkbox" />
                        Bật chế độ bảo trì
                    </label>
                    <label class="space-y-1.5 text-sm">
                        <span>Thông báo bảo trì</span>
                        <textarea
                            v-model="maintenance.message"
                            rows="2"
                            class="w-full rounded-lg border border-border bg-background p-3 text-sm"
                            placeholder="Hệ thống đang được bảo trì..."
                        />
                    </label>
                    <Button @click="saveMaintenance">Lưu cấu hình</Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Chế độ bảo trì đang cấu hình</CardTitle>
                    <CardDescription
                        >Trạng thái toàn hệ thống và từng nhà
                        hàng.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-2">
                    <div
                        v-for="mode in maintenanceModes"
                        :key="mode.id"
                        class="flex items-center justify-between gap-3 rounded-xl border border-border/60 bg-muted/10 p-3"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{
                                    mode.scope === 'global'
                                        ? 'Toàn hệ thống'
                                        : mode.restaurant_name
                                }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ mode.message || 'Không có thông báo' }}
                            </p>
                        </div>
                        <StatusBadge
                            :status="mode.enabled ? 'maintenance' : 'inactive'"
                        >
                            {{ mode.enabled ? 'Đang bật' : 'Đã tắt' }}
                        </StatusBadge>
                    </div>
                    <p
                        v-if="!maintenanceModes.length"
                        class="py-5 text-center text-sm text-muted-foreground"
                    >
                        Chưa cấu hình chế độ bảo trì.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
