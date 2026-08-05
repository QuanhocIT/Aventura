<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    Crown,
    ExternalLink,
    LayoutGrid,
    ReceiptText,
    RefreshCcw,
    ShieldAlert,
    Table2,
    UserCheck,
    Users,
    WalletCards,
    Activity,
    AlertTriangle,
    HardDrive,
    Tag,
    Calendar,
    Trash2,
    CheckCircle2,
    Plus,
    Clock,
    MessageSquare,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    StatusBadge,
    AlertBanner,
} from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    restaurant: {
        id: number;
        name: string;
        code: string;
        slug: string;
        tax_code: string;
        phone: string;
        email: string;
        address: string;
        status: string;
        timezone: string;
        currency: string;
        trial_ends_at: string;
        subscription_ends_at: string;
        created_at: string;
        last_active_at?: string;
        is_inactive_flagged?: boolean;
        inactive_flagged_at?: string;
        custom_storage_limit_mb?: number | null;
        today_activity?: {
            orders_count: number;
            dishes_prepared_count: number;
            revenue: number;
        };
        owner: { id?: number; name: string; email: string };
        plan: { id: number; name: string; code: string };
    };
    quota: {
        plan: string;
        plan_code: string;
        resources: Record<
            string,
            {
                used: number;
                limit: number;
                unlimited: boolean;
                percentage: number;
                can_add: boolean;
            }
        >;
        features: Record<string, boolean>;
        rate_limit: number;
    };
    subscriptions: Array<{
        id: number;
        plan: string;
        status: string;
        started_at: string;
        ended_at: string;
        price: string;
    }>;
    invoices: Array<{
        id: number;
        invoice_number: string;
        type: string;
        status: string;
        total: string;
        currency: string;
        due_on: string;
        sent_at: string;
    }>;
    adjustments: Array<{
        id: number;
        type: string;
        days: number;
        discount_amount: string;
        reason: string;
        created_at: string;
        creator: string;
    }>;
    webhooks: Array<{
        id: number;
        provider: string;
        status: string;
        event_type: string;
        transaction_code: string;
        processed_at: string;
    }>;
    plans: Array<{ id: number; code: string; name: string }>;
    crm_notes: Array<{
        id: number;
        note: string;
        created_at: string;
        user: { name: string };
    }>;
    crm_tags: Array<{ id: number; name: string; color: string }>;
    crm_followups: Array<{
        id: number;
        note: string;
        remind_at: string;
        status: string;
        assigned_user: { name: string };
    }>;
    admins: Array<{ id: number; name: string }>;
    activity_timeline: Array<{
        id: number;
        event: string;
        action: string;
        user_name: string;
        created_at: string;
    }>;
    anomalies: Array<{
        type: string;
        severity: string;
        title: string;
        message: string;
    }>;
    features_map: {
        menu: boolean;
        ordering: boolean;
        shifts: boolean;
        reservations: boolean;
        chatbot: boolean;
    };
}>();

const statusForm = useForm({ status: props.restaurant.status, reason: '' });
function updateStatus() {
    statusForm.patch(`/super-admin/restaurants/${props.restaurant.id}/status`);
}

const planForm = useForm({ plan_id: String(props.restaurant.plan.id) });
function updatePlan() {
    planForm.patch(`/super-admin/restaurants/${props.restaurant.id}/plan`);
}

const storageForm = useForm({
    custom_storage_limit_mb: props.restaurant.custom_storage_limit_mb ?? '',
});
function updateStorageQuota() {
    storageForm.patch(
        `/super-admin/restaurants/${props.restaurant.id}/storage-quota`,
        {
            preserveScroll: true,
        },
    );
}

const overrideForm = useForm({
    type: 'extend',
    days: 30,
    discount_amount: 0,
    reason: '',
    coupon_code: '',
    password: '',
});
function submitOverride() {
    overrideForm.post(
        `/super-admin/restaurants/${props.restaurant.id}/billing-overrides`,
        {
            preserveScroll: true,
            onSuccess: () => {
                overrideForm.password = '';
            },
        },
    );
}

const customPlanForm = useForm({
    name: '',
    price: 0,
    billing_cycle: 'monthly',
    max_branches: -1,
    max_users: -1,
    max_tables: -1,
    max_dishes: -1,
    max_areas: -1,
    max_storage_mb: 500,
    api_rate_limit: 60,
    password: '',
    // features
    kitchen_display: false,
    qr_ordering: false,
    inventory_basic: false,
    hr_timekeeping: false,
    hr_full: false,
    advanced_analytics: false,
    realtime: false,
    fraud_detection: false,
    email_reports: false,
    ai_advisor: false,
    supplier_portal: false,
    ai_forecasting: false,
    api_access: false,
    rfm_ai_analysis: false,
    priority_support: false,
});

function loadDefaultsFromCurrentPlan() {
    customPlanForm.name = `Gói Custom - ${props.restaurant.name}`;

    const branches = props.quota.resources.branches;
    customPlanForm.max_branches = branches.unlimited ? -1 : branches.limit;

    const tables = props.quota.resources.tables;
    customPlanForm.max_tables = tables.unlimited ? -1 : tables.limit;

    const employees = props.quota.resources.employees;
    customPlanForm.max_users = employees.unlimited ? -1 : employees.limit;

    const dishes = props.quota.resources.dishes;
    customPlanForm.max_dishes = dishes
        ? dishes.unlimited
            ? -1
            : dishes.limit
        : -1;

    const areas = props.quota.resources.areas;
    customPlanForm.max_areas = areas
        ? areas.unlimited
            ? -1
            : areas.limit
        : -1;

    const storage = props.quota.resources.storage_mb;
    customPlanForm.max_storage_mb = storage.unlimited ? 10240 : storage.limit;

    customPlanForm.api_rate_limit = props.quota.rate_limit || 60;

    customPlanForm.kitchen_display = !!props.quota.features.kitchen_display;
    customPlanForm.qr_ordering = !!props.quota.features.qr_ordering;
    customPlanForm.inventory_basic = !!props.quota.features.inventory_basic;
    customPlanForm.hr_timekeeping = !!props.quota.features.hr_timekeeping;
    customPlanForm.hr_full = !!props.quota.features.hr_full;
    customPlanForm.advanced_analytics =
        !!props.quota.features.advanced_analytics;
    customPlanForm.realtime = !!props.quota.features.realtime;
    customPlanForm.fraud_detection = !!props.quota.features.fraud_detection;
    customPlanForm.email_reports = !!props.quota.features.email_reports;
    customPlanForm.ai_advisor = !!props.quota.features.ai_advisor;
    customPlanForm.supplier_portal = !!props.quota.features.supplier_portal;
    customPlanForm.ai_forecasting = !!props.quota.features.ai_forecasting;
    customPlanForm.api_access = !!props.quota.features.api_access;
    customPlanForm.rfm_ai_analysis = !!props.quota.features.rfm_ai_analysis;
    customPlanForm.priority_support = !!props.quota.features.priority_support;
}

function submitCustomPlan() {
    customPlanForm.post(
        `/super-admin/restaurants/${props.restaurant.id}/custom-plan`,
        {
            preserveScroll: true,
            onSuccess: () => {
                customPlanForm.password = '';
            },
        },
    );
}

async function createSandbox() {
    if (
        await confirmDialog({
            title: 'Nhân bản Sandbox',
            description: `Bạn có chắc muốn tạo một bản sao Sandbox thử nghiệm cho nhà hàng "${props.restaurant.name}" không? Quá trình này sẽ clone cấu trúc chi nhánh, bàn, thực đơn và nhân sự.`,
            variant: 'default',
        })
    ) {
        router.post(
            `/super-admin/restaurants/${props.restaurant.id}/sandbox`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Đang thực hiện nhân bản sandbox...');
                },
            },
        );
    }
}

async function impersonateUser() {
    if (!props.restaurant.owner?.id) {
        toast.error('Không tìm thấy tài khoản chủ sở hữu để sắm vai.');

        return;
    }

    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Bạn có chắc chắn muốn đăng nhập sắm vai dưới quyền của tài khoản chủ sở hữu "${props.restaurant.owner.name}" không?`,
            variant: 'default',
        })
    ) {
        router.post(`/super-admin/impersonate/${props.restaurant.owner.id}`);
    }
}

const unflagForm = useForm({});
async function unflagRestaurant() {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description:
                'Bạn có chắc chắn muốn gỡ gắn cờ cảnh báo và đặt lại mốc hoạt động cuối của nhà hàng này không?',
            variant: 'default',
        })
    ) {
        unflagForm.patch(
            `/super-admin/restaurants/${props.restaurant.id}/unflag`,
        );
    }
}

function formatCurrency(val: number) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(val);
}

function applyPreset(
    type: string,
    days: number,
    discount: number,
    reason: string,
) {
    overrideForm.type = type;
    overrideForm.days = days;
    overrideForm.discount_amount = discount;
    overrideForm.reason = reason;

    const pwdInput = document.getElementById('override-password');

    if (pwdInput) {
        pwdInput.focus();
    }
}

const statusColor: Record<string, string> = {
    active: 'bg-green-100 text-green-800',
    suspended: 'bg-amber-100 text-amber-800',
    expired: 'bg-rose-100 text-rose-800',
    generated: 'bg-blue-100 text-blue-800',
    sent: 'bg-emerald-100 text-emerald-800',
    pending: 'bg-slate-100 text-slate-800',
    processed: 'bg-emerald-100 text-emerald-800',
    orphaned: 'bg-rose-100 text-rose-800',
};
const statusLabel: Record<string, string> = {
    active: 'Hoạt động',
    suspended: 'Tạm ngừng',
    expired: 'Hết hạn',
    generated: 'Đã tạo tệp',
    sent: 'Đã gửi',
    pending: 'Đang chờ',
    processed: 'Đã xử lý',
    orphaned: 'Không khớp',
};

const resourceIcons: Record<string, any> = {
    branches: Building2,
    employees: Users,
    areas: LayoutGrid,
    tables: Table2,
    storage_mb: HardDrive,
    dishes: LayoutGrid,
};
const resourceLabels: Record<string, string> = {
    branches: 'Chi nhánh',
    employees: 'Nhân viên',
    areas: 'Khu vực',
    tables: 'Bàn ăn',
    storage_mb: 'Dung lượng (MB)',
    dishes: 'Món ăn',
};

function barColor(pct: number, canAdd: boolean) {
    if (!canAdd) {
        return 'bg-rose-500';
    }

    if (pct >= 80) {
        return 'bg-amber-500';
    }

    return 'bg-emerald-500';
}

function typeLabel(type: string) {
    const labels: Record<string, string> = {
        payment_success: 'Sau thanh toan',
        upcoming_renewal: 'Sap den han',
        extend: 'Gia han tay',
        discount: 'Giam gia',
        trial: 'Dùng thử miễn phí',
    };

    return labels[type] ?? type;
}

// ── Subscriptions history dialog ─────────────────────────────
interface SubsRow {
    id: number;
    plan: string;
    status: string;
    started_at: string;
    ended_at: string;
    price: string;
}

const showSubsDialog = ref(false);
const subsHistory = ref<SubsRow[]>([]);
const subsPage = ref(1);
const subsLastPage = ref(1);
const subsTotal = ref(0);
const subsLoading = ref(false);

async function loadSubsHistory(page = 1) {
    subsLoading.value = true;

    try {
        const res = await fetch(
            `/super-admin/restaurants/${props.restaurant.id}/subscriptions-history?page=${page}`,
            {
                headers: { Accept: 'application/json' },
            },
        );
        const json = await res.json();
        subsHistory.value = json.data;
        subsPage.value = json.current_page;
        subsLastPage.value = json.last_page;
        subsTotal.value = json.total;
    } finally {
        subsLoading.value = false;
    }
}

function openSubsDialog() {
    showSubsDialog.value = true;
    loadSubsHistory(1);
}

// CRM - Notes
const noteForm = useForm({ note: '' });
function addNote() {
    noteForm.post(`/super-admin/restaurants/${props.restaurant.id}/notes`, {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    });
}
async function deleteNote(noteId: number) {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: 'Bạn có chắc chắn muốn xóa ghi chú này?',
        })
    ) {
        router.delete(
            `/super-admin/restaurants/${props.restaurant.id}/notes/${noteId}`,
            {
                preserveScroll: true,
            },
        );
    }
}

// CRM - Tags
const tagForm = useForm({ name: '', color: 'slate' });
const tagPresets = [
    { name: 'VIP', color: 'amber' },
    { name: 'At Risk', color: 'rose' },
    { name: 'Referral', color: 'emerald' },
    { name: 'Chăm sóc', color: 'blue' },
];
function addTag(name: string, color: string) {
    tagForm.name = name;
    tagForm.color = color;
    tagForm.post(`/super-admin/restaurants/${props.restaurant.id}/tags`, {
        preserveScroll: true,
    });
}
async function removeTag(tagId: number) {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: 'Bạn có chắc chắn muốn gỡ nhãn này?',
            variant: 'default',
        })
    ) {
        router.delete(
            `/super-admin/restaurants/${props.restaurant.id}/tags/${tagId}`,
            {
                preserveScroll: true,
            },
        );
    }
}

// CRM - Followups
const followupForm = useForm({ note: '', remind_at: '', assigned_to: '' });
function addFollowup() {
    followupForm.post(
        `/super-admin/restaurants/${props.restaurant.id}/followups`,
        {
            preserveScroll: true,
            onSuccess: () => followupForm.reset(),
        },
    );
}
function markFollowupComplete(followupId: number) {
    router.patch(
        `/super-admin/restaurants/${props.restaurant.id}/followups/${followupId}/complete`,
        {},
        {
            preserveScroll: true,
        },
    );
}

// Helpers
const tagBgColors: Record<string, string> = {
    amber: 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900/50',
    rose: 'bg-rose-100 text-rose-800 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900/50',
    emerald:
        'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/50',
    blue: 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900/50',
    slate: 'bg-slate-100 text-slate-800 border-slate-200 dark:bg-slate-950/40 dark:text-slate-300 dark:border-slate-900/50',
};
</script>

<template>
    <Head :title="`${restaurant.name} - Trung tâm thanh toán`" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <div class="space-y-4">
            <div
                class="group flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="space-y-2">
                    <div
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Link
                            href="/super-admin/restaurants"
                            class="transition-colors hover:text-foreground"
                            >Nhà hàng</Link
                        >
                        <span>/</span>
                        <span class="font-medium text-foreground">{{
                            restaurant.name
                        }}</span>
                    </div>
                    <h1
                        class="flex items-center gap-2.5 text-2xl font-bold tracking-tight"
                    >
                        <div
                            class="flex size-8 items-center justify-center rounded-xl border border-primary/20 bg-primary/10"
                        >
                            <Building2
                                class="size-5 shrink-0 text-primary/80"
                            />
                        </div>
                        <span
                            class="bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent dark:from-white dark:to-slate-300"
                        >
                            {{ restaurant.name }}
                        </span>
                        <StatusBadge :status="restaurant.status">
                            {{
                                statusLabel[restaurant.status] ??
                                restaurant.status
                            }}
                        </StatusBadge>
                    </h1>
                    <p class="font-mono text-sm text-muted-foreground">
                        {{ restaurant.code }} · Trung tâm thanh toán
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            v-for="tag in crm_tags"
                            :key="tag.id"
                            :class="[
                                'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold shadow-sm transition-all',
                                tagBgColors[tag.color] || tagBgColors.slate,
                            ]"
                        >
                            <Tag class="size-3" />
                            {{ tag.name }}
                            <button
                                type="button"
                                class="ml-1 leading-none font-bold hover:text-rose-500 focus:outline-none"
                                @click="removeTag(tag.id)"
                            >
                                &times;
                            </button>
                        </span>
                        <span
                            class="self-center text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                            >Gắn nhãn:</span
                        >
                        <button
                            v-for="preset in tagPresets"
                            :key="preset.name"
                            class="inline-flex items-center gap-1 rounded-full border border-border/75 px-2 py-0.5 text-[10px] font-semibold transition-colors hover:bg-muted"
                            @click="addTag(preset.name, preset.color)"
                        >
                            <Plus class="size-2.5" /> {{ preset.name }}
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link href="/super-admin/restaurants">
                        <Button variant="outline" size="sm" class="gap-1.5"
                            ><ArrowLeft class="size-4" /> Quay lại</Button
                        >
                    </Link>
                    <Button
                        v-if="restaurant.owner && restaurant.owner.id"
                        variant="outline"
                        size="sm"
                        class="gap-2 border-blue-500/30 text-blue-600 hover:bg-blue-500/10 dark:text-blue-400"
                        @click="impersonateUser"
                    >
                        <UserCheck class="size-4" /> Sắm vai chủ nhà
                    </Button>
                </div>
            </div>
            <div
                class="h-px w-full bg-gradient-to-r from-transparent via-primary/20 to-transparent"
            />
        </div>

        <!-- Anomaly Alerts -->
        <AlertBanner
            v-for="anomaly in anomalies"
            :key="anomaly.type"
            :severity="anomaly.severity === 'danger' ? 'critical' : 'warning'"
            :title="anomaly.title"
            :message="anomaly.message"
            class=""
        />

        <div class="grid gap-6 lg:grid-cols-[1.5fr,0.9fr]">
            <div class="flex flex-col gap-6">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base"
                            >Tong quan doanh nghiep</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="grid gap-4 text-sm md:grid-cols-2">
                        <div>
                            <p class="text-muted-foreground">Chu so huu</p>
                            <p class="font-medium">
                                {{ restaurant.owner.name || '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">
                                Thư điện tử chủ sở hữu
                            </p>
                            <p class="font-medium">
                                {{ restaurant.owner.email || '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Goi hien tai</p>
                            <p class="font-medium">
                                {{ restaurant.plan.name || '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Het han dich vu</p>
                            <p class="font-medium">
                                {{ restaurant.subscription_ends_at || '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Hạn dùng thử</p>
                            <p class="font-medium">
                                {{ restaurant.trial_ends_at || '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Tien te</p>
                            <p class="font-medium">{{ restaurant.currency }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-muted-foreground">Dia chi</p>
                            <p class="font-medium">
                                {{ restaurant.address || '—' }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Crown class="size-4 text-amber-600" /> Han muc goi
                            dich vu
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2">
                        <div
                            v-for="(res, key) in quota.resources"
                            :key="key"
                            class="rounded-xl border border-border/70 bg-background/70 p-4"
                        >
                            <div
                                class="mb-2 flex items-center justify-between text-sm"
                            >
                                <span
                                    class="flex items-center gap-2 font-medium"
                                >
                                    <component
                                        :is="resourceIcons[key]"
                                        class="size-4 text-muted-foreground"
                                    />
                                    {{ resourceLabels[key] ?? key }}
                                </span>
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ res.used }} /
                                    {{ res.unlimited ? '∞' : res.limit }}
                                </span>
                            </div>
                            <div
                                class="h-2 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    v-if="!res.unlimited"
                                    :class="[
                                        'h-full rounded-full transition-all',
                                        barColor(res.percentage, res.can_add),
                                    ]"
                                    :style="{ width: `${res.percentage}%` }"
                                />
                                <div
                                    v-else
                                    class="h-full w-full rounded-full bg-emerald-500/30"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Feature Adoption Map -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Activity class="size-4.5 text-indigo-500" /> Bản đồ
                            độ phủ tính năng (Feature Adoption)
                        </CardTitle>
                    </CardHeader>
                    <CardContent
                        class="grid grid-cols-2 gap-3 text-center text-xs sm:grid-cols-5"
                    >
                        <div
                            v-for="(active, feat) in features_map"
                            :key="feat"
                            :class="[
                                'flex flex-col items-center gap-2 rounded-xl border p-3 shadow-sm transition-all',
                                active
                                    ? 'border-emerald-200 bg-emerald-50/50 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-300'
                                    : 'border-border/70 bg-slate-50 text-muted-foreground dark:bg-slate-900/20',
                            ]"
                        >
                            <span class="text-xl">{{
                                feat === 'menu'
                                    ? '🍔'
                                    : feat === 'ordering'
                                      ? '📦'
                                      : feat === 'shifts'
                                        ? '📅'
                                        : feat === 'reservations'
                                          ? '🛎️'
                                          : '🤖'
                            }}</span>
                            <span class="font-semibold">{{
                                feat === 'menu'
                                    ? 'Thực đơn'
                                    : feat === 'ordering'
                                      ? 'Đơn hàng'
                                      : feat === 'shifts'
                                        ? 'Ca làm việc'
                                        : feat === 'reservations'
                                          ? 'Đặt bàn'
                                          : 'Chatbot AI'
                            }}</span>
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-[10px] font-bold',
                                    active
                                        ? 'bg-emerald-200 text-emerald-900 dark:bg-emerald-900/50 dark:text-emerald-300'
                                        : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-400',
                                ]"
                            >
                                {{ active ? 'Đang dùng' : 'Chưa dùng' }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- CRM Ghi chú & Lịch hẹn chăm sóc -->
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- CRM Ghi chú -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle
                                class="flex items-center gap-2 text-base text-sky-600 dark:text-sky-400"
                            >
                                <MessageSquare class="size-4.5" /> Ghi chú nội
                                bộ (SuperAdmin Notes)
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <form @submit.prevent="addNote" class="flex gap-2">
                                <Input
                                    v-model="noteForm.note"
                                    placeholder="Thêm ghi chú nội bộ về nhà hàng..."
                                    required
                                    class="flex-1 rounded-xl border-border bg-background text-sm"
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    :disabled="noteForm.processing"
                                    class="shrink-0 rounded-xl bg-sky-600 hover:bg-sky-700"
                                    >Lưu</Button
                                >
                            </form>

                            <div
                                class="max-h-[300px] space-y-2 overflow-y-auto pr-1"
                            >
                                <div
                                    v-for="n in crm_notes"
                                    :key="n.id"
                                    class="group relative rounded-xl border border-border/70 bg-muted/30 p-3"
                                >
                                    <p class="pr-6 text-sm text-foreground">
                                        {{ n.note }}
                                    </p>
                                    <div
                                        class="mt-2 flex items-center justify-between text-xs text-muted-foreground"
                                    >
                                        <span
                                            >Bởi:
                                            <strong>{{
                                                n.user.name
                                            }}</strong></span
                                        >
                                        <span>{{ n.created_at }}</span>
                                    </div>
                                    <button
                                        @click="deleteNote(n.id)"
                                        class="absolute top-2 right-2 text-rose-500 opacity-0 transition-opacity group-hover:opacity-100 hover:text-rose-700"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                                <p
                                    v-if="!crm_notes.length"
                                    class="py-8 text-center text-xs text-muted-foreground"
                                >
                                    Chưa có ghi chú nội bộ nào.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- CRM Lịch hẹn chăm sóc -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle
                                class="flex items-center gap-2 text-base text-violet-600 dark:text-violet-400"
                            >
                                <Calendar class="size-4.5" /> Lịch hẹn chăm sóc
                                (Follow-ups)
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <form
                                @submit.prevent="addFollowup"
                                class="space-y-3 rounded-xl border bg-muted/20 p-3"
                            >
                                <div class="grid gap-2">
                                    <Input
                                        v-model="followupForm.note"
                                        placeholder="Nội dung hẹn gọi lại..."
                                        required
                                        class="border-border bg-background text-xs"
                                    />
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label
                                            class="text-[10px] text-muted-foreground"
                                            >Ngày nhắc:</label
                                        >
                                        <Input
                                            type="datetime-local"
                                            v-model="followupForm.remind_at"
                                            required
                                            class="h-8 border-border bg-background px-2 py-0 text-xs"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="text-[10px] text-muted-foreground"
                                            >Người xử lý:</label
                                        >
                                        <select
                                            v-model="followupForm.assigned_to"
                                            required
                                            class="h-8 w-full rounded-md border border-border bg-background px-2 text-xs"
                                        >
                                            <option value="">
                                                Chọn Admin...
                                            </option>
                                            <option
                                                v-for="adm in admins"
                                                :key="adm.id"
                                                :value="adm.id"
                                            >
                                                {{ adm.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <Button
                                    type="submit"
                                    size="sm"
                                    :disabled="followupForm.processing"
                                    class="h-8 w-full rounded-lg bg-violet-600 text-xs hover:bg-violet-700"
                                    >Đặt lịch</Button
                                >
                            </form>

                            <div
                                class="max-h-[220px] space-y-2 overflow-y-auto pr-1"
                            >
                                <div
                                    v-for="f in crm_followups"
                                    :key="f.id"
                                    class="flex items-start justify-between gap-2 rounded-xl border border-border/70 bg-muted/30 p-3"
                                >
                                    <div class="flex-1 space-y-1">
                                        <p
                                            :class="[
                                                'text-xs font-medium',
                                                f.status === 'completed'
                                                    ? 'text-muted-foreground line-through'
                                                    : 'text-foreground',
                                            ]"
                                        >
                                            {{ f.note }}
                                        </p>
                                        <div
                                            class="flex flex-wrap gap-2 text-[10px] text-muted-foreground"
                                        >
                                            <span
                                                class="flex items-center gap-1"
                                                ><Clock class="size-2.5" />
                                                {{ f.remind_at }}</span
                                            >
                                            <span
                                                >Nhận việc:
                                                <strong>{{
                                                    f.assigned_user.name
                                                }}</strong></span
                                            >
                                        </div>
                                    </div>
                                    <span
                                        v-if="f.status === 'completed'"
                                        class="shrink-0 rounded-full border border-emerald-200/50 bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                                        >Xong</span
                                    >
                                    <Button
                                        v-else
                                        @click="markFollowupComplete(f.id)"
                                        size="sm"
                                        variant="ghost"
                                        class="h-6 w-6 shrink-0 rounded-full p-0 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-800"
                                    >
                                        <CheckCircle2 class="size-4" />
                                    </Button>
                                </div>
                                <p
                                    v-if="!crm_followups.length"
                                    class="py-8 text-center text-xs text-muted-foreground"
                                >
                                    Chưa có lịch chăm sóc nào.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Activity Timeline -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Clock class="size-4.5 text-blue-500" /> Dòng thời
                            gian hoạt động thực tế (Activity Feed)
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            class="relative max-h-[300px] space-y-4 overflow-y-auto border-l border-border pr-2 pl-6"
                        >
                            <div
                                v-for="log in activity_timeline"
                                :key="log.id"
                                class="relative"
                            >
                                <!-- dot -->
                                <div
                                    class="absolute top-1 -left-[30px] size-3 rounded-full border-2 border-blue-500 bg-background"
                                />
                                <div
                                    class="flex items-start justify-between gap-3 text-xs"
                                >
                                    <div>
                                        <span
                                            class="mr-1.5 font-semibold text-foreground"
                                            >{{ log.user_name }}</span
                                        >
                                        <span class="text-muted-foreground"
                                            >đã thực hiện</span
                                        >
                                        <span
                                            class="ml-1 rounded border border-blue-100 bg-blue-50 px-1.5 py-0.5 font-mono text-[10px] text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-300"
                                            >{{ log.action }}</span
                                        >
                                    </div>
                                    <span
                                        class="shrink-0 text-muted-foreground"
                                        >{{ log.created_at }}</span
                                    >
                                </div>
                            </div>
                            <p
                                v-if="!activity_timeline.length"
                                class="py-8 text-center text-xs text-muted-foreground"
                            >
                                Không ghi nhận hoạt động nào gần đây.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Custom Plan Builder -->
                <Card class="border-indigo-500/20 shadow-md">
                    <CardHeader
                        class="flex flex-row items-center justify-between border-b bg-slate-50/50 pb-3 dark:bg-slate-900/20"
                    >
                        <CardTitle
                            class="flex items-center gap-2 text-base text-indigo-600 dark:text-indigo-400"
                        >
                            <Crown class="size-5" /> Trình thiết kế Gói dịch vụ
                            tùy chỉnh cho Doanh nghiệp (Enterprise Custom Plan
                            Builder)
                        </CardTitle>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="text-xs font-semibold"
                            @click="loadDefaultsFromCurrentPlan"
                        >
                            Tải nhanh thông số hiện tại
                        </Button>
                    </CardHeader>
                    <CardContent class="pt-6">
                        <form
                            @submit.prevent="submitCustomPlan"
                            class="space-y-6"
                        >
                            <!-- Section 1: Contract Setup -->
                            <div>
                                <h3
                                    class="mb-3 border-l-2 border-indigo-500 pl-2 text-sm font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    1. Thông tin hợp đồng & Giá
                                </h3>
                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="grid gap-1.5">
                                        <Label for="cp-name"
                                            >Tên gói đăng ký</Label
                                        >
                                        <Input
                                            id="cp-name"
                                            v-model="customPlanForm.name"
                                            placeholder="Gói Custom - Chuỗi Kichi..."
                                        />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-price"
                                            >Đơn giá hợp đồng (VND)</Label
                                        >
                                        <Input
                                            id="cp-price"
                                            type="number"
                                            v-model="customPlanForm.price"
                                            min="0"
                                            required
                                        />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-cycle"
                                            >Chu kỳ thanh toán</Label
                                        >
                                        <select
                                            id="cp-cycle"
                                            v-model="
                                                customPlanForm.billing_cycle
                                            "
                                            class="h-9 rounded-md border bg-background px-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                        >
                                            <option value="monthly">
                                                Theo tháng (1 tháng)
                                            </option>
                                            <option value="quarterly">
                                                Theo quý (3 tháng)
                                            </option>
                                            <option value="half_yearly">
                                                Nửa năm (6 tháng)
                                            </option>
                                            <option value="yearly">
                                                Theo năm (1 năm)
                                            </option>
                                            <option value="biennial">
                                                Hợp đồng 2 năm
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Quotas -->
                            <div>
                                <h3
                                    class="mb-3 border-l-2 border-indigo-500 pl-2 text-sm font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    2. Thiết lập hạn ngạch (Quotas)
                                </h3>
                                <p
                                    class="mb-3 text-xs font-medium text-muted-foreground"
                                >
                                    Nhập
                                    <code
                                        class="rounded bg-muted px-1.5 py-0.5 font-bold text-indigo-600"
                                        >-1</code
                                    >
                                    để thiết lập không giới hạn (∞) cho tài
                                    nguyên.
                                </p>
                                <div
                                    class="grid gap-4 sm:grid-cols-2 md:grid-cols-3"
                                >
                                    <div class="grid gap-1.5">
                                        <Label for="cp-branches"
                                            >Số chi nhánh tối đa</Label
                                        >
                                        <Input
                                            id="cp-branches"
                                            type="number"
                                            v-model="
                                                customPlanForm.max_branches
                                            "
                                            required
                                        />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-users"
                                            >Số nhân viên tối đa</Label
                                        >
                                        <Input
                                            id="cp-users"
                                            type="number"
                                            v-model="customPlanForm.max_users"
                                            required
                                        />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-tables"
                                            >Số bàn tối đa</Label
                                        >
                                        <Input
                                            id="cp-tables"
                                            type="number"
                                            v-model="customPlanForm.max_tables"
                                            required
                                        />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-dishes"
                                            >Số món ăn tối đa</Label
                                        >
                                        <Input
                                            id="cp-dishes"
                                            type="number"
                                            v-model="customPlanForm.max_dishes"
                                            required
                                        />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-areas"
                                            >Số khu vực tối đa</Label
                                        >
                                        <Input
                                            id="cp-areas"
                                            type="number"
                                            v-model="customPlanForm.max_areas"
                                            required
                                        />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-storage"
                                            >Dung lượng lưu trữ (MB)</Label
                                        >
                                        <Input
                                            id="cp-storage"
                                            type="number"
                                            v-model="
                                                customPlanForm.max_storage_mb
                                            "
                                            required
                                        />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-rate"
                                            >API Rate Limit (req/min)</Label
                                        >
                                        <Input
                                            id="cp-rate"
                                            type="number"
                                            v-model="
                                                customPlanForm.api_rate_limit
                                            "
                                            required
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Section 3: Add-on Modules -->
                            <div>
                                <h3
                                    class="mb-3 border-l-2 border-indigo-500 pl-2 text-sm font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    3. Tính năng nâng cao & Add-ons
                                </h3>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <!-- Group A: Custom Enterprise Add-ons -->
                                    <div
                                        class="space-y-3.5 rounded-xl border border-indigo-100 bg-indigo-50/30 p-4"
                                    >
                                        <h4
                                            class="text-xs font-bold tracking-wider text-indigo-700 uppercase"
                                        >
                                            Enterprise Add-ons
                                        </h4>
                                        <div class="flex items-start gap-2.5">
                                            <input
                                                type="checkbox"
                                                id="add-rfm"
                                                v-model="
                                                    customPlanForm.rfm_ai_analysis
                                                "
                                                class="mt-1 size-4 rounded accent-indigo-600"
                                            />
                                            <Label
                                                for="add-rfm"
                                                class="cursor-pointer text-xs font-semibold"
                                            >
                                                Phân tích AI khách hàng RFM
                                                <span
                                                    class="block text-[10px] font-normal text-muted-foreground"
                                                    >Kích hoạt phân cụm, chấm
                                                    điểm và đề xuất chiến dịch
                                                    CDP.</span
                                                >
                                            </Label>
                                        </div>
                                        <div class="flex items-start gap-2.5">
                                            <input
                                                type="checkbox"
                                                id="add-fraud"
                                                v-model="
                                                    customPlanForm.fraud_detection
                                                "
                                                class="mt-1 size-4 rounded accent-indigo-600"
                                            />
                                            <Label
                                                for="add-fraud"
                                                class="cursor-pointer text-xs font-semibold"
                                            >
                                                Phát hiện gian lận tự động (AI
                                                Audit)
                                                <span
                                                    class="block text-[10px] font-normal text-muted-foreground"
                                                    >Quét kiểm toán hóa đơn, sai
                                                    phạm và cảnh báo rủi ro thất
                                                    thoát.</span
                                                >
                                            </Label>
                                        </div>
                                        <div class="flex items-start gap-2.5">
                                            <input
                                                type="checkbox"
                                                id="add-support"
                                                v-model="
                                                    customPlanForm.priority_support
                                                "
                                                class="mt-1 size-4 rounded accent-indigo-600"
                                            />
                                            <Label
                                                for="add-support"
                                                class="cursor-pointer text-xs font-semibold"
                                            >
                                                Ưu tiên xử lý phiếu hỗ trợ (SLA
                                                VIP)
                                                <span
                                                    class="block text-[10px] font-normal text-muted-foreground"
                                                    >Đẩy vé hỗ trợ lên Critical
                                                    / P1 và phân phối phản hồi
                                                    nhanh nhất.</span
                                                >
                                            </Label>
                                        </div>
                                    </div>

                                    <!-- Group B: Standard Modules -->
                                    <div
                                        class="grid grid-cols-2 gap-3.5 rounded-xl border border-slate-100 bg-slate-50/30 p-4"
                                    >
                                        <h4
                                            class="col-span-2 text-xs font-bold tracking-wider text-slate-700 uppercase"
                                        >
                                            Standard Modules
                                        </h4>

                                        <div class="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                id="feat-kitchen"
                                                v-model="
                                                    customPlanForm.kitchen_display
                                                "
                                                class="size-4 rounded accent-indigo-600"
                                            />
                                            <Label
                                                for="feat-kitchen"
                                                class="cursor-pointer text-xs"
                                                >Màn hình Bếp (KDS)</Label
                                            >
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                id="feat-qr"
                                                v-model="
                                                    customPlanForm.qr_ordering
                                                "
                                                class="size-4 rounded accent-indigo-600"
                                            />
                                            <Label
                                                for="feat-qr"
                                                class="cursor-pointer text-xs"
                                                >Gọi món QR tại bàn</Label
                                            >
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                id="feat-inv"
                                                v-model="
                                                    customPlanForm.inventory_basic
                                                "
                                                class="size-4 rounded accent-indigo-600"
                                            />
                                            <Label
                                                for="feat-inv"
                                                class="cursor-pointer text-xs"
                                                >Quản lý Kho cơ bản</Label
                                            >
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                id="feat-supply"
                                                v-model="
                                                    customPlanForm.supplier_portal
                                                "
                                                class="size-4 rounded accent-indigo-600"
                                            />
                                            <Label
                                                for="feat-supply"
                                                class="cursor-pointer text-xs"
                                                >Cổng Nhà cung cấp</Label
                                            >
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                id="feat-shift"
                                                v-model="
                                                    customPlanForm.hr_timekeeping
                                                "
                                                class="size-4 rounded accent-indigo-600"
                                            />
                                            <Label
                                                for="feat-shift"
                                                class="cursor-pointer text-xs"
                                                >Xếp ca làm việc</Label
                                            >
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                id="feat-hr"
                                                v-model="customPlanForm.hr_full"
                                                class="size-4 rounded accent-indigo-600"
                                            />
                                            <Label
                                                for="feat-hr"
                                                class="cursor-pointer text-xs"
                                                >Quản lý Nhân sự / Lương</Label
                                            >
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                id="feat-realtime"
                                                v-model="
                                                    customPlanForm.realtime
                                                "
                                                class="size-4 rounded accent-indigo-600"
                                            />
                                            <Label
                                                for="feat-realtime"
                                                class="cursor-pointer text-xs"
                                                >Realtime Reverb</Label
                                            >
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                id="feat-api"
                                                v-model="
                                                    customPlanForm.api_access
                                                "
                                                class="size-4 rounded accent-indigo-600"
                                            />
                                            <Label
                                                for="feat-api"
                                                class="cursor-pointer text-xs"
                                                >API Integrations</Label
                                            >
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                id="feat-advisor"
                                                v-model="
                                                    customPlanForm.ai_advisor
                                                "
                                                class="size-4 rounded accent-indigo-600"
                                            />
                                            <Label
                                                for="feat-advisor"
                                                class="cursor-pointer text-xs"
                                                >Trợ lý Chiến lược AI</Label
                                            >
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                id="feat-forecast"
                                                v-model="
                                                    customPlanForm.ai_forecasting
                                                "
                                                class="size-4 rounded accent-indigo-600"
                                            />
                                            <Label
                                                for="feat-forecast"
                                                class="cursor-pointer text-xs"
                                                >AI Dự báo tồn kho</Label
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 4: Security Confirmation -->
                            <div
                                class="grid items-end gap-4 border-t pt-5 md:grid-cols-2"
                            >
                                <div class="grid gap-1.5">
                                    <Label
                                        for="cp-pwd"
                                        class="font-semibold text-rose-600 dark:text-rose-400"
                                        >Xác nhận mật khẩu quản trị cấp cao của
                                        bạn</Label
                                    >
                                    <Input
                                        id="cp-pwd"
                                        type="password"
                                        v-model="customPlanForm.password"
                                        placeholder="Nhập mật khẩu để phê duyệt gói ad-hoc"
                                        required
                                    />
                                    <span
                                        v-if="customPlanForm.errors.password"
                                        class="text-xs font-medium text-rose-600"
                                        >{{
                                            customPlanForm.errors.password
                                        }}</span
                                    >
                                </div>
                                <Button
                                    type="submit"
                                    :disabled="customPlanForm.processing"
                                    class="h-10 w-full justify-center bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                                >
                                    {{
                                        customPlanForm.processing
                                            ? 'Đang tạo và kích hoạt gói...'
                                            : 'Kích hoạt & Áp dụng gói tùy chỉnh này'
                                    }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div class="grid gap-6 xl:grid-cols-3">
                    <Card class="xl:col-span-2">
                        <CardHeader class="pb-3">
                            <CardTitle
                                class="flex items-center justify-between gap-2 text-base"
                            >
                                <span class="flex items-center gap-2"
                                    ><ReceiptText class="size-4 text-sky-600" />
                                    Hoa don gan day</span
                                >
                                <a
                                    :href="`/super-admin/billing?restaurant_id=${restaurant.id}`"
                                    class="flex items-center gap-1 text-xs font-normal text-sky-600 hover:underline dark:text-sky-400"
                                >
                                    Xem tất cả <ExternalLink class="size-3" />
                                </a>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-for="invoice in invoices"
                                :key="invoice.id"
                                class="rounded-xl border border-border/70 p-4"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <p class="font-medium">
                                            {{ invoice.invoice_number }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ typeLabel(invoice.type) }} · Han
                                            {{ invoice.due_on || '—' }}
                                        </p>
                                    </div>
                                    <span
                                        :class="[
                                            'rounded-full px-2.5 py-1 text-xs font-medium',
                                            statusColor[invoice.status] ||
                                                'bg-slate-100 text-slate-800',
                                        ]"
                                    >
                                        {{
                                            statusLabel[invoice.status] ??
                                            invoice.status
                                        }}
                                    </span>
                                </div>
                                <div
                                    class="mt-3 flex items-center justify-between text-sm"
                                >
                                    <span class="font-mono"
                                        >{{ invoice.total }}
                                        {{ invoice.currency }}</span
                                    >
                                    <span class="text-muted-foreground"
                                        >Gui luc:
                                        {{
                                            invoice.sent_at || 'Chua gui'
                                        }}</span
                                    >
                                </div>
                            </div>
                            <p
                                v-if="!invoices.length"
                                class="py-6 text-center text-sm text-muted-foreground"
                            >
                                Chua co hoa don nao.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle
                                class="flex items-center justify-between gap-2 text-base"
                            >
                                <span class="flex items-center gap-2"
                                    ><RefreshCcw
                                        class="size-4 text-violet-600"
                                    />
                                    Nhật ký tích hợp</span
                                >
                                <a
                                    :href="`/super-admin/billing?restaurant_id=${restaurant.id}`"
                                    class="flex items-center gap-1 text-xs font-normal text-violet-600 hover:underline dark:text-violet-400"
                                >
                                    Xem tất cả <ExternalLink class="size-3" />
                                </a>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-for="webhook in webhooks"
                                :key="webhook.id"
                                class="rounded-xl border border-border/70 p-3 text-sm"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span class="font-medium">{{
                                        webhook.provider
                                    }}</span>
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-0.5 text-xs font-medium',
                                            statusColor[webhook.status] ||
                                                'bg-slate-100 text-slate-800',
                                        ]"
                                    >
                                        {{
                                            statusLabel[webhook.status] ??
                                            webhook.status
                                        }}
                                    </span>
                                </div>
                                <p
                                    class="mt-2 text-xs break-all text-muted-foreground"
                                >
                                    {{
                                        webhook.transaction_code ||
                                        'Chưa có mã giao dịch'
                                    }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{
                                        webhook.event_type ||
                                        'Chưa có loại sự kiện'
                                    }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ webhook.processed_at || 'Chưa xử lý' }}
                                </p>
                            </div>
                            <p
                                v-if="!webhooks.length"
                                class="py-6 text-center text-sm text-muted-foreground"
                            >
                                Chưa có sự kiện tích hợp nào.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle
                                class="flex items-center gap-2 text-base"
                            >
                                <ShieldAlert class="size-4 text-rose-600" />
                                Điều chỉnh billing thủ công
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <!-- Nút điền nhanh Presets -->
                            <div
                                class="mb-4 flex flex-wrap gap-2 border-b pb-4"
                            >
                                <span
                                    class="mb-1 w-full text-xs text-muted-foreground"
                                    >Điền nhanh thiết lập:</span
                                >
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-auto border-blue-200 px-2.5 py-1 text-xs text-blue-600 hover:bg-blue-50 dark:border-blue-900/40 dark:text-blue-400 dark:hover:bg-blue-950/20"
                                    @click="
                                        applyPreset(
                                            'trial',
                                            14,
                                            0,
                                            'Tặng dùng thử 14 ngày',
                                        )
                                    "
                                >
                                    +14 ngày Dùng thử
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-auto border-purple-200 px-2.5 py-1 text-xs text-purple-600 hover:bg-purple-50 dark:border-purple-900/40 dark:text-purple-400 dark:hover:bg-purple-950/20"
                                    @click="
                                        applyPreset(
                                            'extend',
                                            30,
                                            0,
                                            'Gia hạn dịch vụ 30 ngày',
                                        )
                                    "
                                >
                                    +30 ngày Gia hạn
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-auto border-emerald-200 px-2.5 py-1 text-xs text-emerald-600 hover:bg-emerald-50 dark:border-emerald-900/40 dark:text-emerald-400 dark:hover:bg-emerald-950/20"
                                    @click="
                                        applyPreset(
                                            'extend',
                                            365,
                                            0,
                                            'Khuyến mãi đặc biệt 365 ngày',
                                        )
                                    "
                                >
                                    +1 năm Free
                                </Button>
                            </div>

                            <form
                                class="grid gap-4"
                                @submit.prevent="submitOverride"
                            >
                                <div class="grid gap-1.5">
                                    <Label>Loai thao tac</Label>
                                    <select
                                        v-model="overrideForm.type"
                                        class="h-9 rounded-md border bg-background px-3 text-sm"
                                    >
                                        <option value="extend">
                                            Gia han thu cong
                                        </option>
                                        <option value="trial">
                                            Tang them trial
                                        </option>
                                        <option value="discount">
                                            Ap ma giam gia dac biet
                                        </option>
                                    </select>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-1.5">
                                        <Label>So ngay cong them</Label>
                                        <Input
                                            v-model="overrideForm.days"
                                            type="number"
                                            min="0"
                                            max="365"
                                        />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label>So tien giam</Label>
                                        <Input
                                            v-model="
                                                overrideForm.discount_amount
                                            "
                                            type="number"
                                            min="0"
                                        />
                                    </div>
                                </div>

                                <div class="grid gap-1.5">
                                    <Label>Mã giảm giá</Label>
                                    <Input
                                        v-model="overrideForm.coupon_code"
                                        placeholder="PARTNER-VIP-2026"
                                    />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label>Ly do</Label>
                                    <Input
                                        v-model="overrideForm.reason"
                                        placeholder="Ho tro doi tac chien luoc / Free trial / Su co doi soat"
                                    />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label
                                        class="font-semibold text-rose-600 dark:text-rose-400"
                                        >Xác nhận mật khẩu của bạn</Label
                                    >
                                    <Input
                                        id="override-password"
                                        v-model="overrideForm.password"
                                        type="password"
                                        placeholder="Nhập mật khẩu Super Admin để xác nhận"
                                        required
                                    />
                                    <span
                                        v-if="overrideForm.errors.password"
                                        class="text-xs font-medium text-rose-600"
                                        >{{
                                            overrideForm.errors.password
                                        }}</span
                                    >
                                </div>

                                <Button
                                    type="submit"
                                    :disabled="overrideForm.processing"
                                    class="justify-center"
                                >
                                    {{
                                        overrideForm.processing
                                            ? 'Dang ap dung...'
                                            : 'Ap dung manual override'
                                    }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle
                                class="flex items-center justify-between gap-2 text-base"
                            >
                                <span class="flex items-center gap-2"
                                    ><WalletCards
                                        class="size-4 text-emerald-600"
                                    />
                                    Lịch sử điều chỉnh</span
                                >
                                <a
                                    :href="`/super-admin/billing?restaurant_id=${restaurant.id}`"
                                    class="flex items-center gap-1 text-xs font-normal text-emerald-600 hover:underline dark:text-emerald-400"
                                >
                                    Xem tất cả <ExternalLink class="size-3" />
                                </a>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-for="adjustment in adjustments"
                                :key="adjustment.id"
                                class="rounded-xl border border-border/70 p-4 text-sm"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span class="font-medium">{{
                                        typeLabel(adjustment.type)
                                    }}</span>
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ adjustment.created_at }}</span
                                    >
                                </div>
                                <p class="mt-2 text-muted-foreground">
                                    {{
                                        adjustment.reason || 'Khong co ghi chu'
                                    }}
                                </p>
                                <div
                                    class="mt-3 flex flex-wrap gap-3 text-xs text-muted-foreground"
                                >
                                    <span>+{{ adjustment.days }} ngay</span>
                                    <span
                                        >Giam
                                        {{ adjustment.discount_amount }}
                                        VND</span
                                    >
                                    <span>{{
                                        adjustment.creator || 'System'
                                    }}</span>
                                </div>
                            </div>
                            <p
                                v-if="!adjustments.length"
                                class="py-6 text-center text-sm text-muted-foreground"
                            >
                                Chua co dieu chinh billing nao.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div class="flex flex-col gap-6">
                <!-- Automated Validator Card -->
                <Card
                    :class="{
                        'border-rose-500/30': restaurant.is_inactive_flagged,
                    }"
                >
                    <CardHeader
                        class="flex-row items-center justify-between gap-2 space-y-0 pb-3"
                    >
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Activity class="size-4.5 shrink-0 text-rose-500" />
                            Trình Kiểm định
                        </CardTitle>
                        <span
                            v-if="restaurant.is_inactive_flagged"
                            class="rounded-full border border-rose-200/50 bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800 dark:bg-rose-950/40 dark:text-rose-300"
                        >
                            🚩 Bị gắn cờ
                        </span>
                        <span
                            v-else
                            class="rounded-full border border-emerald-200/50 bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                        >
                            Bình thường
                        </span>
                    </CardHeader>
                    <CardContent class="space-y-3 text-xs">
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-muted-foreground"
                                >Hoạt động cuối:</span
                            >
                            <span class="font-semibold">{{
                                restaurant.last_active_at
                            }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-muted-foreground"
                                >Trạng thái gắn cờ:</span
                            >
                            <span class="font-semibold">{{
                                restaurant.is_inactive_flagged
                                    ? 'Cần hậu mãi'
                                    : 'Không'
                            }}</span>
                        </div>
                        <div
                            v-if="restaurant.is_inactive_flagged"
                            class="flex justify-between border-b pb-2"
                        >
                            <span class="text-muted-foreground"
                                >Gắn cờ lúc:</span
                            >
                            <span class="font-semibold">{{
                                restaurant.inactive_flagged_at
                            }}</span>
                        </div>

                        <div class="space-y-1.5 pt-2">
                            <p
                                class="text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                            >
                                Chỉ số hôm nay:
                            </p>
                            <div class="flex justify-between">
                                <span>Đơn hàng mới:</span>
                                <span class="font-mono font-bold">{{
                                    restaurant.today_activity?.orders_count ?? 0
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Món ăn ra bếp:</span>
                                <span class="font-mono font-bold">{{
                                    restaurant.today_activity
                                        ?.dishes_prepared_count ?? 0
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Doanh thu ngày:</span>
                                <span
                                    class="font-mono font-bold text-emerald-600"
                                    >{{
                                        formatCurrency(
                                            restaurant.today_activity
                                                ?.revenue ?? 0,
                                        )
                                    }}</span
                                >
                            </div>
                        </div>

                        <div v-if="restaurant.is_inactive_flagged" class="pt-2">
                            <Button
                                @click="unflagRestaurant"
                                :disabled="unflagForm.processing"
                                variant="outline"
                                class="w-full gap-1.5 border-rose-200 text-xs text-rose-600 hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-400 dark:hover:bg-rose-950/20"
                            >
                                <AlertTriangle class="size-3.5" /> Đặt lại hoạt
                                động &amp; Gỡ cờ
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base"
                            >Quan tri trang thai</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <form
                            @submit.prevent="updateStatus"
                            class="flex flex-col gap-3"
                        >
                            <select
                                v-model="statusForm.status"
                                class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="active">Kich hoat</option>
                                <option value="expired">
                                    Read-only / Het han
                                </option>
                                <option value="suspended">
                                    Khoa hoan toan
                                </option>
                            </select>
                            <Input
                                v-model="statusForm.reason"
                                placeholder="Ly do cap nhat trang thai"
                            />
                            <Button
                                type="submit"
                                :disabled="statusForm.processing"
                                size="sm"
                                class="w-full"
                            >
                                {{
                                    statusForm.processing
                                        ? 'Dang luu...'
                                        : 'Cap nhat trang thai'
                                }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base"
                            >Chuyen goi dich vu</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <form
                            @submit.prevent="updatePlan"
                            class="flex flex-col gap-3"
                        >
                            <select
                                v-model="planForm.plan_id"
                                class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="p in plans"
                                    :key="p.id"
                                    :value="String(p.id)"
                                >
                                    {{ p.name }}
                                </option>
                            </select>
                            <Button
                                type="submit"
                                :disabled="planForm.processing"
                                size="sm"
                                variant="outline"
                                class="w-full"
                            >
                                {{
                                    planForm.processing
                                        ? 'Dang luu...'
                                        : 'Cap nhat goi'
                                }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base"
                            >Hạn ngạch lưu trữ tùy chỉnh</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <form
                            @submit.prevent="updateStorageQuota"
                            class="flex flex-col gap-3"
                        >
                            <div>
                                <Label
                                    for="custom_storage"
                                    class="text-xs text-muted-foreground"
                                    >Giới hạn dung lượng (MB)</Label
                                >
                                <Input
                                    id="custom_storage"
                                    type="number"
                                    v-model="
                                        storageForm.custom_storage_limit_mb
                                    "
                                    placeholder="Để trống để dùng mặc định"
                                    class="mt-1 rounded-xl border-border bg-background text-foreground"
                                />
                            </div>
                            <Button
                                type="submit"
                                :disabled="storageForm.processing"
                                size="sm"
                                variant="outline"
                                class="w-full"
                            >
                                {{
                                    storageForm.processing
                                        ? 'Đang lưu...'
                                        : 'Cập nhật dung lượng'
                                }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle
                            class="flex items-center justify-between gap-2 text-base"
                        >
                            <span>Lịch sử gói dịch vụ</span>
                            <button
                                class="flex items-center gap-1 text-xs font-normal text-amber-600 hover:underline dark:text-amber-400"
                                @click="openSubsDialog"
                            >
                                Xem tất cả <ExternalLink class="size-3" />
                            </button>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="s in subscriptions"
                            :key="s.id"
                            class="rounded-xl border border-border/70 p-3 text-sm"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span class="font-medium">{{ s.plan }}</span>
                                <span
                                    :class="[
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        statusColor[s.status] ||
                                            'bg-slate-100 text-slate-800',
                                    ]"
                                >
                                    {{ statusLabel[s.status] ?? s.status }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ s.started_at }} → {{ s.ended_at }}
                            </p>
                            <p class="mt-1 font-mono text-xs">
                                {{ s.price }} VND
                            </p>
                        </div>
                        <p
                            v-if="!subscriptions.length"
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            Chưa có lịch sử gói dịch vụ.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Database class="size-4 text-emerald-600" />
                            Portability & Sandbox
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p class="text-xs text-muted-foreground">
                            Sao lưu cấu hình hoặc tạo môi trường Sandbox thử
                            nghiệm biệt lập cho Restaurant này.
                        </p>
                        <div class="grid gap-2">
                            <a
                                :href="`/super-admin/restaurants/${restaurant.id}/export`"
                                class="w-full"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="w-full justify-center gap-1.5"
                                >
                                    <ExternalLink class="size-3.5" />
                                    Xuất dữ liệu cấu hình (JSON)
                                </Button>
                            </a>
                            <Button
                                variant="secondary"
                                size="sm"
                                class="w-full justify-center gap-1.5"
                                @click="createSandbox"
                            >
                                <RefreshCcw class="size-3.5" />
                                Tạo bản sao Sandbox
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <!-- Subscriptions History Dialog -->
    <Dialog
        :open="showSubsDialog"
        @update:open="
            (v) => {
                if (!v) showSubsDialog = false;
            }
        "
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle
                    >Lịch sử gói dịch vụ · {{ restaurant.name }}</DialogTitle
                >
            </DialogHeader>

            <div
                v-if="subsLoading"
                class="py-8 text-center text-sm text-muted-foreground"
            >
                Đang tải...
            </div>

            <div v-else class="space-y-2 py-2">
                <p class="text-xs text-muted-foreground">
                    Tổng {{ subsTotal }} bản ghi
                </p>
                <div
                    v-for="s in subsHistory"
                    :key="s.id"
                    class="rounded-xl border border-border/70 p-3 text-sm"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">{{ s.plan }}</span>
                        <span
                            :class="[
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                statusColor[s.status] ||
                                    'bg-slate-100 text-slate-800',
                            ]"
                        >
                            {{ statusLabel[s.status] ?? s.status }}
                        </span>
                    </div>
                    <p class="mt-1.5 text-xs text-muted-foreground">
                        {{ s.started_at }} → {{ s.ended_at }}
                    </p>
                    <p class="mt-0.5 font-mono text-xs">{{ s.price }} VND</p>
                </div>
                <p
                    v-if="!subsHistory.length"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    Không có dữ liệu.
                </p>

                <!-- Pagination -->
                <div
                    v-if="subsLastPage > 1"
                    class="flex items-center justify-center gap-2 pt-2"
                >
                    <button
                        :disabled="subsPage <= 1"
                        class="rounded px-3 py-1 text-xs hover:bg-muted disabled:opacity-40"
                        @click="loadSubsHistory(subsPage - 1)"
                    >
                        ← Trước
                    </button>
                    <span class="text-xs text-muted-foreground"
                        >{{ subsPage }} / {{ subsLastPage }}</span
                    >
                    <button
                        :disabled="subsPage >= subsLastPage"
                        class="rounded px-3 py-1 text-xs hover:bg-muted disabled:opacity-40"
                        @click="loadSubsHistory(subsPage + 1)"
                    >
                        Sau →
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
