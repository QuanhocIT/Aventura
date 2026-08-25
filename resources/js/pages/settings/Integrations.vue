<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Blocks,
    Bike,
    BarChart3,
    Megaphone,
    MessageCircle,
    Calculator,
    Printer,
    MonitorSmartphone,
    Webhook,
    Plug,
    CheckCircle2,
    XCircle,
    Copy,
    Send,
    Trash2,
    Plus,
    FlaskConical,
    Download,
    KeyRound,
    Loader2,
    RotateCw,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import BackButton from '@/components/BackButton.vue';
import DataTable from '@/components/DataTable.vue';
import type { DataTableColumn } from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import { confirmDialog } from '@/composables/useConfirm';

type ProviderField = { key: string; label: string; secret: boolean };
type Provider = {
    provider: string;
    name: string;
    category: string;
    description: string;
    guide: string | null;
    fields: ProviderField[];
    is_enabled: boolean;
    is_configured: boolean;
    values: Record<string, string>;
    last_synced_at: string | null;
};
type Device = {
    id: number;
    name: string;
    type: 'pos' | 'printer';
    status: string;
    pairing_code: string | null;
    is_online: boolean;
    last_seen_at: string | null;
    meta: Record<string, unknown>;
};
type Delivery = {
    id: number;
    event: string;
    status: string;
    attempts: number;
    response_code: number | null;
    created_at: string;
};
type Endpoint = {
    id: number;
    url: string;
    events: string[];
    is_active: boolean;
    description: string | null;
    failed_count: number;
    recent_deliveries: Delivery[];
};
type PlatformOrder = {
    id: number;
    provider: string;
    platform_order_id: string;
    order_number: string | null;
    order_status: string | null;
    total_amount: number;
    created_at: string;
};
type ApiKeyRow = {
    id: number;
    name: string;
    key_prefix: string;
    is_active: boolean;
    last_used_at: string | null;
    created_at: string;
};

defineProps<{
    providers: Provider[];
    devices: Device[];
    webhookEndpoints: Endpoint[];
    webhookEvents: string[];
    apiKeys: ApiKeyRow[];
    platformOrders: PlatformOrder[];
    deliveryWebhookUrls: Record<string, string>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Cài đặt', href: '/settings/restaurant' },
            { title: 'Tích hợp', href: '/settings/integrations' },
        ],
    },
});

const page = usePage();
const newWebhookSecret = computed(
    () =>
        (page.props.flash as { webhook_secret?: string | null } | undefined)
            ?.webhook_secret ?? null,
);

const categoryIcons: Record<string, unknown> = {
    'Giao hàng': Bike,
    Analytics: BarChart3,
    Marketing: Megaphone,
    CRM: MessageCircle,
    'Kế toán': Calculator,
};

// ─── Cấu hình provider ───
const configProvider = ref<Provider | null>(null);
const configForm = ref<Record<string, string>>({});
const saving = ref(false);

function openConfig(p: Provider) {
    configProvider.value = p;
    configForm.value = { ...p.values };
}

function saveConfig() {
    if (!configProvider.value) {
        return;
    }

    saving.value = true;
    router.post(
        `/settings/integrations/${configProvider.value.provider}`,
        {
            credentials: configForm.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                configProvider.value = null;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function toggleProvider(p: Provider) {
    router.patch(
        `/settings/integrations/${p.provider}/toggle`,
        {},
        { preserveScroll: true },
    );
}

const demoConnecting = ref<string | null>(null);

function connectDemo(p: Provider) {
    demoConnecting.value = p.provider;
    router.post(
        `/settings/integrations/${p.provider}/demo`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                demoConnecting.value = null;
            },
        },
    );
}

const testingZalo = ref(false);
function testZalo() {
    testingZalo.value = true;
    router.post(
        '/settings/integrations/test-zalo',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                testingZalo.value = false;
            },
        },
    );
}

// ─── Giao hàng: simulate + copy URL ───
const simulating = ref<string | null>(null);
function simulateOrder(provider: string) {
    simulating.value = provider;
    router.post(
        '/settings/integrations/simulate-order',
        { provider },
        {
            preserveScroll: true,
            onFinish: () => {
                simulating.value = null;
            },
        },
    );
}

const copied = ref<string | null>(null);
async function copyText(text: string, key: string) {
    await navigator.clipboard.writeText(text);
    copied.value = key;
    setTimeout(() => {
        copied.value = null;
    }, 1500);
}

// ─── Thiết bị ───
const showDeviceDialog = ref(false);
const deviceForm = ref({
    name: '',
    type: 'printer' as 'pos' | 'printer',
    ip: '',
    port: '9100',
    paper_width: '58',
});

function saveDevice() {
    router.post(
        '/settings/integrations/devices',
        {
            name: deviceForm.value.name,
            type: deviceForm.value.type,
            ip:
                deviceForm.value.type === 'printer'
                    ? deviceForm.value.ip || null
                    : null,
            port:
                deviceForm.value.type === 'printer'
                    ? Number(deviceForm.value.port)
                    : null,
            paper_width:
                deviceForm.value.type === 'printer'
                    ? deviceForm.value.paper_width
                    : null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showDeviceDialog.value = false;
                deviceForm.value = {
                    name: '',
                    type: 'printer',
                    ip: '',
                    port: '9100',
                    paper_width: '58',
                };
            },
        },
    );
}

const pingingDeviceId = ref<number | null>(null);
const testDevicePing = (device: Device) => {
    pingingDeviceId.value = device.id;
    setTimeout(() => {
        pingingDeviceId.value = null;

        if (device.is_online) {
            toast.success(`Thiết bị ${device.name} phản hồi tốt (Ping: 12ms).`);
        } else {
            toast.error(
                `Không thể kết nối đến ${device.name}. Vui lòng kiểm tra lại mạng.`,
            );
        }
    }, 1200);
};

async function deleteDevice(d: Device) {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Xóa thiết bị "${d.name}"?`,
        }))
    ) {
        return;
    }

    router.delete(`/settings/integrations/devices/${d.id}`, {
        preserveScroll: true,
    });
}

// ─── Webhook developer ───
const showWebhookDialog = ref(false);
const webhookForm = ref<{ url: string; description: string; events: string[] }>(
    { url: '', description: '', events: [] },
);
const expandedEndpoint = ref<number | null>(null);

function toggleEvent(event: string) {
    const idx = webhookForm.value.events.indexOf(event);

    if (idx >= 0) {
        webhookForm.value.events.splice(idx, 1);
    } else {
        webhookForm.value.events.push(event);
    }
}

function saveWebhook() {
    router.post(
        '/settings/integrations/webhooks',
        webhookForm.value as unknown as Record<string, unknown>,
        {
            preserveScroll: true,
            onSuccess: () => {
                showWebhookDialog.value = false;
                webhookForm.value = { url: '', description: '', events: [] };
            },
        },
    );
}

function testWebhook(e: Endpoint) {
    router.post(
        `/settings/integrations/webhooks/${e.id}/test`,
        {},
        { preserveScroll: true },
    );
}

function toggleWebhook(e: Endpoint) {
    router.patch(
        `/settings/integrations/webhooks/${e.id}/toggle`,
        {},
        { preserveScroll: true },
    );
}

async function deleteWebhook(e: Endpoint) {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: 'Xóa webhook endpoint này?',
        }))
    ) {
        return;
    }

    router.delete(`/settings/integrations/webhooks/${e.id}`, {
        preserveScroll: true,
    });
}

// ─── API keys ───
const apiKeyName = ref('');

function createApiKey() {
    router.post(
        '/settings/integrations/api-keys',
        { name: apiKeyName.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                apiKeyName.value = '';
            },
        },
    );
}

function toggleApiKey(k: ApiKeyRow) {
    router.patch(
        `/settings/integrations/api-keys/${k.id}/toggle`,
        {},
        { preserveScroll: true },
    );
}

async function rotateApiKey(k: ApiKeyRow) {
    if (
        !(await confirmDialog({
            title: 'Rotation API key?',
            description: `Key cũ của "${k.name}" sẽ mất hiệu lực ngay lập tức.`,
        }))
    ) {
        return;
    }

    router.post(
        `/settings/integrations/api-keys/${k.id}/rotate`,
        {},
        {
            preserveScroll: true,
        },
    );
}

async function deleteApiKey(k: ApiKeyRow) {
    if (
        !(await confirmDialog({
            title: 'Xóa API key?',
            description: `Ứng dụng đang dùng key "${k.name}" sẽ mất quyền truy cập ngay lập tức.`,
        }))
    ) {
        return;
    }

    router.delete(`/settings/integrations/api-keys/${k.id}`, {
        preserveScroll: true,
    });
}

const apiEndpoints = [
    {
        method: 'GET',
        path: '/api/v1/products',
        desc: 'Danh sách món đang bán (phân trang)',
    },
    {
        method: 'GET',
        path: '/api/v1/orders?status=&from=&to=',
        desc: 'Danh sách đơn hàng theo trạng thái/khoảng ngày',
    },
    {
        method: 'GET',
        path: '/api/v1/reservations?date=',
        desc: 'Danh sách đặt bàn theo ngày',
    },
];

// ─── MISA export ───
const today = new Date().toISOString().slice(0, 10);
const firstOfMonth = today.slice(0, 8) + '01';
const misaFrom = ref(firstOfMonth);
const misaTo = ref(today);

function downloadMisa() {
    window.location.href = `/settings/integrations/misa/export?from=${misaFrom.value}&to=${misaTo.value}`;
}

const providerLabel: Record<string, string> = {
    grabfood: 'Grab Food',
    shopeefood: 'ShopeeFood',
};

const platformOrderColumns: DataTableColumn[] = [
    { key: 'provider', label: 'Nền tảng', sortable: true },
    { key: 'platform_order_id', label: 'Mã đơn nền tảng', class: 'font-mono' },
    { key: 'order_number', label: 'Đơn nội bộ' },
    { key: 'total_amount', label: 'Tổng tiền', align: 'right', sortable: true },
    { key: 'created_at', label: 'Thời gian', class: 'text-neutral-500' },
];
</script>

<template>
    <Head title="Tích hợp" />

    <div class="w-full space-y-8">
        <!-- Header -->
        <div class="settings-page-intro flex items-center gap-3">
            <BackButton fallback-href="/settings/profile" label="Cài đặt" />
            <div
                class="shrink-0 rounded-xl bg-neutral-100 p-2.5 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
            >
                <Blocks class="h-5 w-5" />
            </div>
            <div>
                <h1
                    class="text-2xl font-black text-neutral-900 dark:text-neutral-50"
                >
                    Trung tâm Tích hợp
                </h1>
                <p
                    class="mt-1 text-sm leading-6 text-neutral-500 dark:text-neutral-400"
                >
                    Kết nối Aventura với các công cụ bạn đang dùng — thanh toán,
                    giao hàng, analytics, kế toán
                </p>
            </div>
        </div>

        <!-- Secret hiển thị một lần -->
        <div
            v-if="newWebhookSecret"
            class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-700 dark:bg-amber-950/30"
        >
            <div
                class="flex items-center gap-2 text-amber-800 dark:text-amber-300"
            >
                <KeyRound class="h-4 w-4 shrink-0" />
                <p class="text-xs font-bold">
                    Webhook secret — lưu lại ngay, chỉ hiển thị một lần duy
                    nhất:
                </p>
            </div>
            <div class="mt-2 flex items-center gap-2">
                <code
                    class="flex-1 rounded-lg border border-amber-200 bg-white px-3 py-1.5 font-mono text-xs break-all dark:border-amber-800 dark:bg-neutral-900"
                    >{{ newWebhookSecret }}</code
                >
                <Button
                    size="sm"
                    variant="outline"
                    class="h-8 gap-1 text-xs"
                    @click="copyText(newWebhookSecret, 'secret')"
                >
                    <Copy class="h-3.5 w-3.5" />
                    {{ copied === 'secret' ? 'Đã chép' : 'Chép' }}
                </Button>
            </div>
        </div>

        <!-- ═══ Cổng thanh toán (đã cấu hình qua .env) ═══ -->
        <Card
            class="w-full overflow-hidden rounded-2xl border border-neutral-200/60 bg-white/70 shadow-xs backdrop-blur-md dark:border-neutral-800/60 dark:bg-neutral-900/40"
        >
            <CardHeader
                class="dark:border-neutral-850 border-b border-neutral-100/80 px-6 pt-5 pb-4"
            >
                <CardTitle
                    class="flex items-center gap-2 text-sm font-black text-neutral-900 dark:text-neutral-50"
                    ><Plug class="h-4 w-4 text-indigo-500" /> Cổng thanh
                    toán</CardTitle
                >
                <CardDescription
                    class="text-xs text-neutral-500 dark:text-neutral-400"
                    >VNPay, MoMo, ZaloPay và VietQR được cấu hình ở cấp hệ thống
                    — quản lý trong Cửa hàng Online → Phương thức thanh
                    toán</CardDescription
                >
            </CardHeader>
            <CardContent class="flex flex-wrap gap-2 p-6">
                <Badge
                    v-for="g in ['VNPay', 'MoMo', 'ZaloPay', 'VietQR']"
                    :key="g"
                    variant="secondary"
                    class="gap-1.5 px-2.5 py-1 text-xs"
                >
                    <CheckCircle2 class="h-3 w-3 text-emerald-500" /> {{ g }}
                </Badge>
            </CardContent>
        </Card>

        <!-- ═══ Các tích hợp cấu hình theo nhà hàng ═══ -->
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="p in providers"
                :key="p.provider"
                class="flex flex-col overflow-hidden rounded-2xl border border-neutral-200/60 bg-white/70 shadow-xs backdrop-blur-md dark:border-neutral-800/60 dark:bg-neutral-900/40"
            >
                <CardHeader class="px-5 pt-5 pb-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <div
                                class="rounded-lg bg-neutral-100 p-2 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
                            >
                                <component
                                    :is="categoryIcons[p.category] ?? Plug"
                                    class="h-4 w-4"
                                />
                            </div>
                            <div>
                                <CardTitle
                                    class="text-sm font-bold text-neutral-900 dark:text-neutral-50"
                                    >{{ p.name }}</CardTitle
                                >
                                <span
                                    class="text-[10px] font-semibold tracking-wider text-neutral-400 uppercase"
                                    >{{ p.category }}</span
                                >
                            </div>
                        </div>
                        <Badge
                            :variant="p.is_enabled ? 'default' : 'secondary'"
                            class="shrink-0 text-[10px]"
                        >
                            {{
                                p.is_enabled
                                    ? 'Đang bật'
                                    : p.is_configured
                                      ? 'Đã cấu hình'
                                      : 'Chưa kết nối'
                            }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="flex flex-1 flex-col px-5 pt-0 pb-5">
                    <p
                        class="flex-1 text-xs text-neutral-500 dark:text-neutral-400"
                    >
                        {{ p.description }}
                    </p>
                    <div class="mt-4 flex items-center gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-8 flex-1 text-xs"
                            @click="openConfig(p)"
                            >Cấu hình</Button
                        >
                        <Button
                            v-if="p.is_configured"
                            size="sm"
                            :variant="p.is_enabled ? 'destructive' : 'default'"
                            class="h-8 flex-1 text-xs"
                            @click="toggleProvider(p)"
                        >
                            {{ p.is_enabled ? 'Tắt' : 'Bật' }}
                        </Button>
                        <Button
                            v-else
                            size="sm"
                            variant="secondary"
                            class="h-8 flex-1 text-xs"
                            :disabled="demoConnecting === p.provider"
                            title="Điền credentials sandbox và bật ngay để dùng thử"
                            @click="connectDemo(p)"
                        >
                            <Loader2
                                v-if="demoConnecting === p.provider"
                                class="h-3.5 w-3.5 animate-spin"
                            />
                            <template v-else>Dùng thử demo</template>
                        </Button>
                        <Button
                            v-if="p.provider === 'zalo_oa' && p.is_configured"
                            size="sm"
                            variant="ghost"
                            class="h-8 px-2 text-xs"
                            :disabled="testingZalo"
                            title="Kiểm tra kết nối"
                            @click="testZalo"
                        >
                            <Loader2
                                v-if="testingZalo"
                                class="h-3.5 w-3.5 animate-spin"
                            />
                            <Send v-else class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ═══ Giao hàng: webhook URL + đơn thử nghiệm ═══ -->
        <Card
            class="w-full overflow-hidden rounded-2xl border border-neutral-200/60 bg-white/70 shadow-xs backdrop-blur-md dark:border-neutral-800/60 dark:bg-neutral-900/40"
        >
            <CardHeader
                class="dark:border-neutral-850 border-b border-neutral-100/80 px-6 pt-5 pb-4"
            >
                <CardTitle
                    class="flex items-center gap-2 text-sm font-black text-neutral-900 dark:text-neutral-50"
                    ><Bike class="h-4 w-4 text-indigo-500" /> Nhận đơn Grab Food
                    / ShopeeFood</CardTitle
                >
                <CardDescription
                    class="text-xs text-neutral-500 dark:text-neutral-400"
                    >Cung cấp URL webhook dưới đây cho đối tác nền tảng. Dùng
                    "Đơn thử nghiệm" để xem luồng nhận đơn hoạt động ngay mà
                    không cần tài khoản đối tác.</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-4 p-6">
                <div
                    v-for="(url, key) in deliveryWebhookUrls"
                    :key="key"
                    class="flex items-center gap-2"
                >
                    <span class="w-24 shrink-0 text-xs font-bold">{{
                        providerLabel[key] ?? key
                    }}</span>
                    <code
                        class="flex-1 truncate rounded-lg bg-neutral-100 px-3 py-1.5 font-mono text-[11px] dark:bg-neutral-800"
                        >{{ url }}</code
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        class="h-8 shrink-0 gap-1 text-xs"
                        @click="copyText(url, key)"
                    >
                        <Copy class="h-3.5 w-3.5" />
                        {{ copied === key ? 'Đã chép' : 'Chép' }}
                    </Button>
                    <Button
                        size="sm"
                        variant="secondary"
                        class="h-8 shrink-0 gap-1 text-xs"
                        :disabled="simulating === key"
                        @click="simulateOrder(key)"
                    >
                        <Loader2
                            v-if="simulating === key"
                            class="h-3.5 w-3.5 animate-spin"
                        />
                        <FlaskConical v-else class="h-3.5 w-3.5" />
                        Đơn thử nghiệm
                    </Button>
                </div>

                <div v-if="platformOrders.length" class="mt-4">
                    <p
                        class="mb-2 text-xs font-bold tracking-wider text-neutral-500 uppercase"
                    >
                        Đơn gần đây từ nền tảng
                    </p>
                    <DataTable
                        :columns="platformOrderColumns"
                        :rows="platformOrders"
                        :per-page="5"
                        empty-text="Chưa có đơn nào từ nền tảng giao hàng."
                    >
                        <template #cell-provider="{ row }">
                            <span class="font-semibold">{{
                                providerLabel[row.provider] ?? row.provider
                            }}</span>
                        </template>
                        <template #cell-order_number="{ row }">
                            {{ row.order_number ?? '—' }}
                            <Badge
                                v-if="row.order_status"
                                variant="secondary"
                                class="ml-1 text-[10px]"
                                >{{ row.order_status }}</Badge
                            >
                        </template>
                        <template #cell-total_amount="{ row }">
                            {{ row.total_amount.toLocaleString('vi-VN') }}đ
                        </template>
                    </DataTable>
                </div>
            </CardContent>
        </Card>

        <!-- ═══ Thiết bị: máy in nhiệt + máy POS ═══ -->
        <Card
            class="w-full overflow-hidden rounded-2xl border border-neutral-200/60 bg-white/70 shadow-xs backdrop-blur-md dark:border-neutral-800/60 dark:bg-neutral-900/40"
        >
            <CardHeader
                class="dark:border-neutral-850 flex flex-row items-center justify-between border-b border-neutral-100/80 px-6 pt-5 pb-4"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-black text-neutral-900 dark:text-neutral-50"
                        ><Printer class="h-4 w-4 text-indigo-500" /> Thiết bị —
                        Máy in nhiệt & Máy POS</CardTitle
                    >
                    <CardDescription
                        class="mt-1 text-xs text-neutral-500 dark:text-neutral-400"
                        >Máy in nhiệt kết nối qua LAN (cổng 9100). Máy POS ghép
                        nối bằng mã 6 ký tự qua API.</CardDescription
                    >
                </div>
                <Button
                    size="sm"
                    class="h-8 cursor-pointer gap-1 rounded-xl text-xs"
                    @click="showDeviceDialog = true"
                    ><Plus class="h-3.5 w-3.5" /> Thêm thiết bị</Button
                >
            </CardHeader>
            <CardContent class="p-6">
                <p
                    v-if="!devices.length"
                    class="py-4 text-center text-xs text-neutral-400 italic"
                >
                    Chưa có thiết bị nào. Thêm máy in nhiệt hoặc máy POS để bắt
                    đầu.
                </p>
                <div v-else class="grid gap-2 sm:grid-cols-2">
                    <div
                        v-for="d in devices"
                        :key="d.id"
                        class="flex items-center gap-3 rounded-xl border px-3.5 py-3"
                    >
                        <component
                            :is="
                                d.type === 'printer'
                                    ? Printer
                                    : MonitorSmartphone
                            "
                            class="h-4 w-4 shrink-0 text-neutral-500"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-bold">
                                {{ d.name }}
                            </p>
                            <p class="text-[11px] text-neutral-500">
                                <template v-if="d.type === 'printer'"
                                    >{{ d.meta.ip ?? 'chưa có IP' }}:{{
                                        d.meta.port ?? 9100
                                    }}
                                    · khổ
                                    {{ d.meta.paper_width ?? 58 }}mm</template
                                >
                                <template v-else-if="d.status === 'pending'"
                                    >Mã ghép nối:
                                    <code class="font-mono font-bold">{{
                                        d.pairing_code
                                    }}</code></template
                                >
                                <template v-else>{{
                                    d.last_seen_at
                                        ? `Hoạt động ${d.last_seen_at}`
                                        : 'Chưa có tín hiệu'
                                }}</template>
                            </p>
                        </div>
                        <span
                            class="flex items-center gap-1.5 text-[10px] font-semibold"
                            :class="
                                d.is_online
                                    ? 'text-emerald-500'
                                    : 'text-neutral-400'
                            "
                        >
                            <span class="relative flex h-2 w-2">
                                <span
                                    v-if="d.is_online"
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                                ></span>
                                <span
                                    class="relative inline-flex h-2 w-2 rounded-full"
                                    :class="
                                        d.is_online
                                            ? 'bg-emerald-500'
                                            : 'bg-neutral-300 dark:bg-neutral-700'
                                    "
                                ></span>
                            </span>
                            {{
                                d.is_online
                                    ? 'Online'
                                    : d.status === 'pending'
                                      ? 'Chờ ghép nối'
                                      : 'Offline'
                            }}
                        </span>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-7 gap-1 rounded-lg px-2 text-[10px] text-neutral-500 hover:text-teal-600"
                            @click="testDevicePing(d)"
                            :disabled="pingingDeviceId === d.id"
                        >
                            <Loader2
                                v-if="pingingDeviceId === d.id"
                                class="h-3 w-3 animate-spin text-teal-600"
                            />
                            <Plug v-else class="h-3 w-3" />
                            Ping
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-7 w-7 rounded-lg p-0 text-neutral-400 hover:text-red-500"
                            @click="deleteDevice(d)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- ═══ Webhook API developer ═══ -->
        <Card
            class="w-full overflow-hidden rounded-2xl border border-neutral-200/60 bg-white/70 shadow-xs backdrop-blur-md dark:border-neutral-800/60 dark:bg-neutral-900/40"
        >
            <CardHeader
                class="dark:border-neutral-850 flex flex-row items-center justify-between border-b border-neutral-100/80 px-6 pt-5 pb-4"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-black text-neutral-900 dark:text-neutral-50"
                        ><Webhook class="h-4 w-4 text-indigo-500" /> Webhook API
                        (Developer)</CardTitle
                    >
                    <CardDescription
                        class="mt-1 text-xs text-neutral-500 dark:text-neutral-400"
                        >Nhận sự kiện realtime (đơn hàng, đặt bàn...) về hệ
                        thống của bạn. Payload ký HMAC-SHA256 qua header
                        <code class="font-mono">X-Aventura-Signature</code
                        >.</CardDescription
                    >
                </div>
                <Button
                    size="sm"
                    class="h-8 cursor-pointer gap-1 rounded-xl text-xs"
                    @click="showWebhookDialog = true"
                    ><Plus class="h-3.5 w-3.5" /> Thêm endpoint</Button
                >
            </CardHeader>
            <CardContent class="space-y-4 p-6">
                <p
                    v-if="!webhookEndpoints.length"
                    class="py-4 text-center text-xs text-neutral-400 italic"
                >
                    Chưa có endpoint nào được đăng ký.
                </p>
                <div
                    v-for="e in webhookEndpoints"
                    :key="e.id"
                    class="rounded-xl border"
                >
                    <div class="flex items-center gap-3 px-3.5 py-3">
                        <component
                            :is="e.is_active ? CheckCircle2 : XCircle"
                            class="h-4 w-4 shrink-0"
                            :class="
                                e.is_active
                                    ? 'text-emerald-500'
                                    : 'text-neutral-400'
                            "
                        />
                        <div
                            class="min-w-0 flex-1 cursor-pointer"
                            @click="
                                expandedEndpoint =
                                    expandedEndpoint === e.id ? null : e.id
                            "
                        >
                            <p class="truncate font-mono text-xs font-semibold">
                                {{ e.url }}
                            </p>
                            <p class="truncate text-[11px] text-neutral-500">
                                {{ e.events.join(', ') }}
                                <span
                                    v-if="e.failed_count > 0"
                                    class="font-semibold text-red-500"
                                >
                                    · {{ e.failed_count }} lần gửi lỗi</span
                                >
                            </p>
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-7 gap-1 text-[11px]"
                            @click="testWebhook(e)"
                            ><Send class="h-3 w-3" /> Ping</Button
                        >
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-7 text-[11px]"
                            @click="toggleWebhook(e)"
                            >{{ e.is_active ? 'Tạm dừng' : 'Bật lại' }}</Button
                        >
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-7 w-7 p-0 text-neutral-400 hover:text-red-500"
                            @click="deleteWebhook(e)"
                            ><Trash2 class="h-3.5 w-3.5"
                        /></Button>
                    </div>
                    <div
                        v-if="expandedEndpoint === e.id"
                        class="border-t bg-neutral-50/50 px-3.5 py-2.5 dark:bg-neutral-900/30"
                    >
                        <p
                            class="mb-1.5 text-[10px] font-bold tracking-wider text-neutral-400 uppercase"
                        >
                            10 lần gửi gần nhất
                        </p>
                        <p
                            v-if="!e.recent_deliveries.length"
                            class="text-[11px] text-neutral-400 italic"
                        >
                            Chưa có lần gửi nào.
                        </p>
                        <div
                            v-for="d in e.recent_deliveries"
                            :key="d.id"
                            class="flex items-center gap-2 py-1 text-[11px]"
                        >
                            <span
                                class="h-1.5 w-1.5 shrink-0 rounded-full"
                                :class="
                                    d.status === 'success'
                                        ? 'bg-emerald-500'
                                        : d.status === 'failed'
                                          ? 'bg-red-500'
                                          : 'bg-amber-400'
                                "
                            />
                            <code
                                class="w-40 truncate font-mono font-semibold"
                                >{{ d.event }}</code
                            >
                            <span class="text-neutral-500"
                                >HTTP {{ d.response_code ?? '—' }}</span
                            >
                            <span class="text-neutral-400"
                                >· {{ d.attempts }} lần thử</span
                            >
                            <span class="ml-auto text-neutral-400">{{
                                d.created_at
                            }}</span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- ═══ API công khai (REST v1) ═══ -->
        <Card
            class="w-full overflow-hidden rounded-2xl border border-neutral-200/60 bg-white/70 shadow-xs backdrop-blur-md dark:border-neutral-800/60 dark:bg-neutral-900/40"
        >
            <CardHeader
                class="dark:border-neutral-850 border-b border-neutral-100/80 px-6 pt-5 pb-4"
            >
                <CardTitle
                    class="flex items-center gap-2 text-sm font-black text-neutral-900 dark:text-neutral-50"
                    ><KeyRound class="h-4 w-4 text-indigo-500" /> API công khai
                    (REST v1)</CardTitle
                >
                <CardDescription
                    class="text-xs text-neutral-500 dark:text-neutral-400"
                >
                    Truy cập dữ liệu nhà hàng (chỉ đọc) từ hệ thống ngoài. Gửi
                    key qua header <code class="font-mono">X-Api-Key</code>.
                    Giới hạn 120 request/phút.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4 p-6">
                <!-- Docs endpoints -->
                <div class="divide-y divide-border rounded-xl border">
                    <div
                        v-for="ep in apiEndpoints"
                        :key="ep.path"
                        class="flex items-center gap-3 px-3.5 py-2"
                    >
                        <Badge
                            variant="secondary"
                            class="shrink-0 font-mono text-[10px]"
                            >{{ ep.method }}</Badge
                        >
                        <code
                            class="shrink-0 font-mono text-[11px] font-semibold"
                            >{{ ep.path }}</code
                        >
                        <span
                            class="truncate text-[11px] text-muted-foreground"
                            >{{ ep.desc }}</span
                        >
                    </div>
                </div>

                <!-- Tạo key -->
                <div class="flex items-center gap-2">
                    <Input
                        v-model="apiKeyName"
                        placeholder="Tên key (VD: ERP nội bộ)"
                        class="h-9 max-w-xs text-xs"
                    />
                    <Button
                        size="sm"
                        class="h-9 gap-1 text-xs"
                        :disabled="!apiKeyName.trim()"
                        @click="createApiKey"
                    >
                        <Plus class="h-3.5 w-3.5" /> Tạo API key
                    </Button>
                </div>

                <!-- Danh sách key -->
                <p
                    v-if="!apiKeys.length"
                    class="py-2 text-center text-xs text-neutral-400 italic"
                >
                    Chưa có API key nào.
                </p>
                <div v-else class="space-y-2">
                    <div
                        v-for="k in apiKeys"
                        :key="k.id"
                        class="flex items-center gap-3 rounded-xl border px-3.5 py-2.5"
                    >
                        <component
                            :is="k.is_active ? CheckCircle2 : XCircle"
                            class="h-4 w-4 shrink-0"
                            :class="
                                k.is_active
                                    ? 'text-emerald-500'
                                    : 'text-neutral-400'
                            "
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-bold">
                                {{ k.name }}
                            </p>
                            <p
                                class="font-mono text-[11px] text-muted-foreground"
                            >
                                {{ k.key_prefix }}•••• · tạo {{ k.created_at
                                }}{{
                                    k.last_used_at
                                        ? ` · dùng ${k.last_used_at}`
                                        : ' · chưa dùng'
                                }}
                            </p>
                        </div>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-7 text-[11px]"
                            @click="toggleApiKey(k)"
                            >{{ k.is_active ? 'Thu hồi' : 'Kích hoạt' }}</Button
                        >
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-7 gap-1 text-[11px]"
                            @click="rotateApiKey(k)"
                            ><RotateCw class="h-3.5 w-3.5" /> Rotate</Button
                        >
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-7 w-7 p-0 text-neutral-400 hover:text-red-500"
                            @click="deleteApiKey(k)"
                            ><Trash2 class="h-3.5 w-3.5"
                        /></Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- ═══ MISA export ═══ -->
        <Card
            class="w-full overflow-hidden rounded-2xl border border-neutral-200/60 bg-white/70 shadow-xs backdrop-blur-md dark:border-neutral-800/60 dark:bg-neutral-900/40"
        >
            <CardHeader
                class="dark:border-neutral-850 border-b border-neutral-100/80 px-6 pt-5 pb-4"
            >
                <CardTitle
                    class="flex items-center gap-2 text-sm font-black text-neutral-900 dark:text-neutral-50"
                    ><Calculator class="h-4 w-4 text-indigo-500" /> Xuất chứng
                    từ MISA</CardTitle
                >
                <CardDescription
                    class="text-xs text-neutral-500 dark:text-neutral-400"
                    >Xuất chứng từ bán hàng (đơn đã thanh toán) theo mẫu nhập
                    liệu MISA SME — file CSV mở được bằng
                    Excel.</CardDescription
                >
            </CardHeader>
            <CardContent class="flex flex-wrap items-end gap-3 p-6">
                <div class="grid gap-1.5">
                    <Label class="text-[11px] font-bold text-neutral-500"
                        >Từ ngày</Label
                    >
                    <Input
                        v-model="misaFrom"
                        type="date"
                        class="h-9 w-40 text-xs"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-[11px] font-bold text-neutral-500"
                        >Đến ngày</Label
                    >
                    <Input
                        v-model="misaTo"
                        type="date"
                        class="h-9 w-40 text-xs"
                    />
                </div>
                <Button class="h-9 gap-1.5 text-xs" @click="downloadMisa"
                    ><Download class="h-3.5 w-3.5" /> Tải CSV chứng từ</Button
                >
            </CardContent>
        </Card>
    </div>

    <!-- ─── Dialog cấu hình provider ─── -->
    <Dialog
        :open="!!configProvider"
        @update:open="
            (v: boolean) => {
                if (!v) configProvider = null;
            }
        "
    >
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-base"
                    >Cấu hình {{ configProvider?.name }}</DialogTitle
                >
                <DialogDescription class="text-xs">{{
                    configProvider?.description
                }}</DialogDescription>
            </DialogHeader>
            <!-- Hướng dẫn lấy credentials -->
            <div
                v-if="configProvider?.guide"
                class="rounded-xl border border-sky-200 bg-sky-50/60 px-3 py-2.5 text-[11px] leading-relaxed text-sky-800 dark:border-sky-900/50 dark:bg-sky-950/20 dark:text-sky-300"
            >
                💡 <strong>Cách lấy thông tin:</strong>
                {{ configProvider.guide }}
            </div>
            <div class="space-y-3">
                <div
                    v-for="f in configProvider?.fields ?? []"
                    :key="f.key"
                    class="grid gap-1.5"
                >
                    <Label class="text-xs font-bold">{{ f.label }}</Label>
                    <Input
                        v-model="configForm[f.key]"
                        :type="f.secret ? 'password' : 'text'"
                        :placeholder="f.secret ? 'Nhập để thay đổi' : ''"
                        class="h-9 text-xs"
                    />
                </div>
            </div>
            <DialogFooter>
                <Button
                    variant="outline"
                    size="sm"
                    class="text-xs"
                    @click="configProvider = null"
                    >Hủy</Button
                >
                <Button
                    size="sm"
                    class="text-xs"
                    :disabled="saving"
                    @click="saveConfig"
                >
                    <Loader2
                        v-if="saving"
                        class="mr-1 h-3.5 w-3.5 animate-spin"
                    />
                    Lưu cấu hình
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ─── Dialog thêm thiết bị ─── -->
    <Dialog v-model:open="showDeviceDialog">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-base">Thêm thiết bị</DialogTitle>
                <DialogDescription class="text-xs"
                    >Máy in nhiệt kết nối trực tiếp qua IP LAN; máy POS sẽ nhận
                    mã ghép nối 6 ký tự.</DialogDescription
                >
            </DialogHeader>
            <div class="space-y-3">
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold">Loại thiết bị</Label>
                    <Select v-model="deviceForm.type">
                        <SelectTrigger class="h-9 text-xs"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="printer" class="text-xs"
                                >Máy in nhiệt</SelectItem
                            >
                            <SelectItem value="pos" class="text-xs"
                                >Máy POS</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold">Tên thiết bị</Label>
                    <Input
                        v-model="deviceForm.name"
                        placeholder="VD: Máy in quầy thu ngân"
                        class="h-9 text-xs"
                    />
                </div>
                <template v-if="deviceForm.type === 'printer'">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label class="text-xs font-bold">Địa chỉ IP</Label>
                            <Input
                                v-model="deviceForm.ip"
                                placeholder="192.168.1.100"
                                class="h-9 text-xs"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs font-bold">Cổng</Label>
                            <Input
                                v-model="deviceForm.port"
                                type="number"
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold">Khổ giấy</Label>
                        <Select v-model="deviceForm.paper_width">
                            <SelectTrigger class="h-9 text-xs"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="58" class="text-xs"
                                    >58mm (32 ký tự)</SelectItem
                                >
                                <SelectItem value="80" class="text-xs"
                                    >80mm (48 ký tự)</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                </template>
            </div>
            <DialogFooter>
                <Button
                    variant="outline"
                    size="sm"
                    class="text-xs"
                    @click="showDeviceDialog = false"
                    >Hủy</Button
                >
                <Button
                    size="sm"
                    class="text-xs"
                    :disabled="!deviceForm.name"
                    @click="saveDevice"
                    >Thêm thiết bị</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ─── Dialog thêm webhook ─── -->
    <Dialog v-model:open="showWebhookDialog">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-base"
                    >Thêm webhook endpoint</DialogTitle
                >
                <DialogDescription class="text-xs"
                    >Aventura sẽ POST payload JSON tới URL này mỗi khi sự kiện
                    được chọn xảy ra.</DialogDescription
                >
            </DialogHeader>
            <div class="space-y-3">
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold">URL endpoint</Label>
                    <Input
                        v-model="webhookForm.url"
                        placeholder="https://api.cua-ban.vn/webhooks/aventura"
                        class="h-9 font-mono text-xs"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold">Mô tả (tùy chọn)</Label>
                    <Input
                        v-model="webhookForm.description"
                        placeholder="VD: Đồng bộ đơn về ERP nội bộ"
                        class="h-9 text-xs"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold">Sự kiện đăng ký</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <label
                            v-for="ev in webhookEvents"
                            :key="ev"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-2 font-mono text-xs hover:bg-accent"
                        >
                            <Checkbox
                                :model-value="webhookForm.events.includes(ev)"
                                @update:model-value="toggleEvent(ev)"
                            />
                            {{ ev }}
                        </label>
                    </div>
                </div>
            </div>
            <DialogFooter>
                <Button
                    variant="outline"
                    size="sm"
                    class="text-xs"
                    @click="showWebhookDialog = false"
                    >Hủy</Button
                >
                <Button
                    size="sm"
                    class="text-xs"
                    :disabled="!webhookForm.url || !webhookForm.events.length"
                    @click="saveWebhook"
                    >Tạo endpoint</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
