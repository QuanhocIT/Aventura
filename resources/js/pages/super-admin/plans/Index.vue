<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Activity,
    Building2,
    Check,
    Coins,
    Crown,
    Database,
    Edit2,
    FileText,
    Globe,
    HardDrive,
    Layers,
    Lock,
    Plus,
    Save,
    Sparkles,
    Star,
    Table,
    Tag,
    Users,
    X,
    Zap,
    Percent,
    Search,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
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
    DialogDescription,
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    PageHeader,
    StatusBadge,
    ProgressBar,
    DataTable,
} from '@/components/super-admin';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface Plan {
    id: number;
    code: string;
    name: string;
    price: number;
    billing_cycle: string;
    max_branches: number;
    max_tables: number;
    max_users: number;
    features: Record<string, any>;
    status: string;
    restaurants_count: number;
}

defineProps<{ plans: Plan[] }>();

const billingPeriod = ref<'monthly' | 'yearly'>('monthly');
const showComparison = ref(false);
const restaurantSearch = ref('');

const editingId = ref<number | null>(null);
const editingPlanCode = ref<string>('');
const isEditing = ref(false);
const activeTab = ref<'info' | 'features'>('info');

const isCreating = ref(false);
const activeCreateTab = ref<'info' | 'features'>('info');
const createForm = useForm({
    code: '',
    name: '',
    description: '',
    billing_cycle: 'monthly',
    price: 0,
    max_branches: 1,
    max_tables: 15,
    max_users: 5,
    max_areas: 2,
    max_storage_mb: 500,
    api_rate_limit: 30,
    yearly_discount_percent: 20,
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
});

function submitCreate() {
    createForm.post(route('superadmin.plans.store'), {
        onSuccess: () => {
            isCreating.value = false;
            createForm.reset();
        },
    });
}

const selectedPlanForRestaurants = ref<Plan | null>(null);
const restaurants = ref<any[]>([]);
const isLoadingRestaurants = ref(false);

const form = useForm({
    name: '',
    description: '',
    price: 0,
    max_branches: 1,
    max_tables: 15,
    max_users: 5,
    max_areas: 2,
    max_storage_mb: 500,
    api_rate_limit: 30,
    yearly_discount_percent: 20,
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
});

const toForm = (v: number | null) => (v === null ? -1 : v);

function startEdit(plan: Plan) {
    editingId.value = plan.id;
    editingPlanCode.value = plan.code;
    isEditing.value = true;
    activeTab.value = 'info';
    form.name = plan.name;
    form.description = plan.features?.description ?? planNotes[plan.code] ?? '';
    form.price = plan.price;
    form.max_branches = toForm(plan.max_branches);
    form.max_tables = toForm(plan.max_tables);
    form.max_users = toForm(plan.max_users);
    form.max_areas = plan.features?.max_areas ?? 2;
    form.max_storage_mb = plan.features?.max_storage_mb ?? 500;
    form.api_rate_limit = plan.features?.api_rate_limit ?? 30;
    form.yearly_discount_percent = plan.features?.yearly_discount_percent ?? 20;
    form.kitchen_display = plan.features?.kitchen_display ?? false;
    form.qr_ordering = plan.features?.qr_ordering ?? false;
    form.inventory_basic = plan.features?.inventory_basic ?? false;
    form.hr_timekeeping = plan.features?.hr_timekeeping ?? false;
    form.hr_full = plan.features?.hr_full ?? false;
    form.advanced_analytics = plan.features?.advanced_analytics ?? false;
    form.realtime = plan.features?.realtime ?? false;
    form.fraud_detection = plan.features?.fraud_detection ?? false;
    form.email_reports = plan.features?.email_reports ?? false;
    form.ai_advisor = plan.features?.ai_advisor ?? false;
    form.supplier_portal = plan.features?.supplier_portal ?? false;
    form.ai_forecasting = plan.features?.ai_forecasting ?? false;
    form.api_access = plan.features?.api_access ?? false;
}

function save(planId: number) {
    form.patch(`/super-admin/plans/${planId}`, {
        onSuccess: () => {
            editingId.value = null;
            isEditing.value = false;
        },
    });
}

async function showRestaurants(plan: Plan) {
    selectedPlanForRestaurants.value = plan;
    isLoadingRestaurants.value = true;
    restaurants.value = [];

    try {
        const response = await fetch(
            `/super-admin/plans/${plan.id}/restaurants`,
        );
        const data = await response.json();
        restaurants.value = data.restaurants || [];
    } catch (e) {
        console.error('Error fetching plan restaurants:', e);
    } finally {
        isLoadingRestaurants.value = false;
    }
}

const filteredRestaurants = computed(() => {
    const q = restaurantSearch.value.trim().toLowerCase();
    if (!q) return restaurants.value;
    return restaurants.value.filter(
        (r) =>
            r.name?.toLowerCase().includes(q) ||
            r.code?.toLowerCase().includes(q) ||
            r.owner_name?.toLowerCase().includes(q) ||
            r.owner_email?.toLowerCase().includes(q),
    );
});

function getInitials(name: string): string {
    return name
        ? name
              .split(' ')
              .map((n) => n[0])
              .slice(0, 2)
              .join('')
              .toUpperCase()
        : 'HT';
}

const avatarGradients = [
    'from-sky-400 to-blue-500 shadow-sky-500/10',
    'from-emerald-400 to-teal-500 shadow-emerald-500/10',
    'from-amber-400 to-orange-500 shadow-amber-500/10',
    'from-violet-400 to-indigo-500 shadow-violet-500/10',
    'from-rose-400 to-pink-500 shadow-rose-500/10',
];
function getGradientForName(name: string): string {
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const index = Math.abs(hash) % avatarGradients.length;
    return avatarGradients[index];
}

const ALL_FEATURES: { key: string; label: string }[] = [
    { key: 'kitchen_display', label: 'Màn hình Bếp (Kitchen Display)' },
    { key: 'qr_ordering', label: 'Đặt món qua QR' },
    { key: 'inventory_basic', label: 'Quản lý Tồn kho' },
    { key: 'hr_timekeeping', label: 'Chấm công & Lịch làm việc' },
    { key: 'hr_full', label: 'Lương & Nhân sự đầy đủ' },
    { key: 'advanced_analytics', label: 'Báo cáo Nâng cao' },
    { key: 'realtime', label: 'Cập nhật thời gian thực' },
    { key: 'fraud_detection', label: 'Phát hiện Gian lận' },
    { key: 'email_reports', label: 'Email Báo cáo tự động' },
    { key: 'ai_advisor', label: 'AI Tư vấn chiến lược' },
    { key: 'supplier_portal', label: 'Cổng Nhà cung cấp (Supplier)' },
    { key: 'ai_forecasting', label: 'AI Dự báo Tồn kho' },
    { key: 'api_access', label: 'Truy cập API' },
];

const FEATURE_CATEGORIES = [
    {
        name: 'Vận hành & POS',
        icon: 'Activity',
        keys: [
            'kitchen_display',
            'qr_ordering',
            'inventory_basic',
            'supplier_portal',
        ],
    },
    {
        name: 'Nhân sự & Chấm công',
        icon: 'Users',
        keys: ['hr_timekeeping', 'hr_full'],
    },
    {
        name: 'Phân tích & AI',
        icon: 'Sparkles',
        keys: [
            'advanced_analytics',
            'fraud_detection',
            'ai_advisor',
            'ai_forecasting',
        ],
    },
    {
        name: 'Hệ thống & Tích hợp',
        icon: 'Globe',
        keys: ['realtime', 'email_reports', 'api_access'],
    },
];

const categoryIcon: Record<string, any> = {
    Activity: Activity,
    Users: Users,
    Sparkles: Sparkles,
    Globe: Globe,
};

function getFeatureLabel(key: string): string {
    return ALL_FEATURES.find((f) => f.key === key)?.label || key;
}

function planFeatures(plan: Plan): string[] {
    const lim = (v: number | null, unit: string) =>
        v === null || v === -1 ? `Không giới hạn ${unit}` : `${v} ${unit}`;
    const mb = plan.features?.max_storage_mb ?? 500;
    const rate = plan.features?.api_rate_limit ?? 30;

    const list = [
        lim(plan.max_branches, 'chi nhánh'),
        lim(plan.max_tables, 'bàn'),
        lim(plan.max_users, 'nhân viên'),
        mb >= 1024 ? `${mb / 1024} GB lưu trữ` : `${mb} MB lưu trữ`,
        `API: ${new Intl.NumberFormat('vi-VN').format(rate)} req/phút`,
    ];

    for (const f of ALL_FEATURES) {
        if (plan.features?.[f.key]) {
            list.push(f.label);
        }
    }

    return list;
}

function planUnsupported(plan: Plan): string[] {
    return ALL_FEATURES.filter((f) => !plan.features?.[f.key]).map(
        (f) => f.label,
    );
}

function formatVnd(v: number) {
    return v === 0 ? '0đ' : new Intl.NumberFormat('vi-VN').format(v) + 'đ';
}

const formatLimit = (v: number | null) =>
    v === null || v === -1 ? 'Vô hạn' : v;
const formatStorage = (mb: number) =>
    mb >= 1024 ? `${mb / 1024} GB` : `${mb} MB`;
const formatRate = (r: number) => new Intl.NumberFormat('vi-VN').format(r);

const planNotes: Record<string, string> = {
    free: 'Gói cơ bản, trải nghiệm POS miễn phí.',
    starter: 'Đầy đủ vận hành: bếp, QR, chấm công, tồn kho.',
    pro: 'Nâng cao toàn diện: AI, nhân sự, báo cáo, chống gian lận.',
    enterprise:
        'Giải pháp doanh nghiệp: nhà cung cấp, AI dự báo, API không giới hạn.',
};

const planIcon: Record<string, any> = {
    free: Star,
    starter: Zap,
    pro: Crown,
    enterprise: Sparkles,
};
</script>

<template>
    <Head title="Quản lý gói dịch vụ" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Gói dịch vụ"
            subtitle="Thay đổi ở đây sẽ hiển thị ngay trên trang khách hàng"
            :icon="Layers"
        >
            <template #actions>
                <!-- Billing Period Toggle Switcher -->
                <div
                    class="shadow-3xs flex items-center rounded-xl border border-border/60 bg-muted/40 p-1 text-[11px] font-bold"
                >
                    <button
                        type="button"
                        @click="billingPeriod = 'monthly'"
                        :class="[
                            'cursor-pointer rounded-lg px-3 py-1.5 transition-all',
                            billingPeriod === 'monthly'
                                ? 'bg-background text-foreground shadow-2xs'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        Hàng tháng
                    </button>
                    <button
                        type="button"
                        @click="billingPeriod = 'yearly'"
                        :class="[
                            'flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 transition-all',
                            billingPeriod === 'yearly'
                                ? 'bg-background text-foreground shadow-2xs'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        Hàng năm
                        <Badge
                            class="h-4 rounded-sm border-none bg-emerald-500/10 px-1 text-[9px] leading-none font-black text-emerald-600 hover:bg-emerald-500/15"
                            >-20%</Badge
                        >
                    </button>
                </div>

                <Button
                    size="sm"
                    variant="outline"
                    class="shadow-3xs cursor-pointer rounded-xl border-border/80 text-xs font-bold"
                    @click="showComparison = true"
                >
                    <Table class="mr-1.5 size-4 text-indigo-500" />
                    So sánh gói
                </Button>

                <Button
                    size="sm"
                    class="cursor-pointer rounded-xl bg-primary text-xs font-bold text-primary-foreground shadow-sm"
                    @click="
                        isCreating = true;
                        activeCreateTab = 'info';
                    "
                >
                    <Plus class="mr-1.5 size-4" />
                    Tạo gói mới
                </Button>
                <StatusBadge status="active" variant="dot" size="sm"
                    >Đồng bộ trang khách</StatusBadge
                >
            </template>
        </PageHeader>

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="plan in plans"
                :key="plan.id"
                class="flex flex-col gap-0"
            >
                <Card
                    class="group flex h-full flex-col justify-between overflow-hidden rounded-2xl border border-border/40 bg-card/45 backdrop-blur-md transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl"
                    :class="{
                        'border-primary/50 bg-gradient-to-b from-primary/[0.03] to-transparent shadow-md ring-1 ring-primary/20':
                            plan.code === 'pro',
                        'border-violet-500/50 bg-gradient-to-b from-violet-500/[0.04] to-transparent shadow-md ring-1 ring-violet-500/20':
                            plan.code === 'enterprise',
                    }"
                >
                    <CardHeader class="pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <CardTitle
                                class="flex items-center gap-1.5 text-xl font-black"
                                :class="{
                                    'text-primary': plan.code === 'pro',
                                    'text-violet-500':
                                        plan.code === 'enterprise',
                                }"
                            >
                                <component
                                    :is="planIcon[plan.code] ?? Star"
                                    class="size-4.5 group-hover:animate-pulse"
                                />
                                {{ plan.name }}
                            </CardTitle>
                            <div class="flex items-center gap-1">
                                <Badge
                                    v-if="plan.code === 'free'"
                                    variant="secondary"
                                    class="rounded-full border border-slate-500/25 bg-slate-500/10 px-2 py-0 text-[9px] font-extrabold text-slate-500 uppercase"
                                    >Mặc định</Badge
                                >
                                <Badge
                                    v-else-if="plan.code === 'pro'"
                                    class="rounded-full border border-primary/25 bg-primary/10 px-2 py-0 text-[9px] font-extrabold text-primary uppercase"
                                    >Khuyến nghị</Badge
                                >
                                <Badge
                                    v-else-if="plan.code === 'enterprise'"
                                    class="rounded-full border border-violet-600/25 bg-violet-600/10 px-2 py-0 text-[9px] font-extrabold text-violet-600 uppercase"
                                    >VIP</Badge
                                >
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="ml-1 size-7 rounded-full hover:bg-muted/70"
                                    @click="startEdit(plan)"
                                >
                                    <Edit2
                                        class="size-3.5 text-muted-foreground"
                                    />
                                </Button>
                            </div>
                        </div>

                        <div class="mt-1 flex items-end gap-1">
                            <span
                                class="font-mono text-3xl font-black tracking-tight text-slate-800 dark:text-slate-100"
                                :class="{
                                    'bg-gradient-to-r from-primary to-orange-500 bg-clip-text text-transparent':
                                        plan.code === 'pro',
                                    'bg-gradient-to-r from-violet-500 to-indigo-500 bg-clip-text text-transparent':
                                        plan.code === 'enterprise',
                                }"
                            >
                                {{
                                    billingPeriod === 'monthly'
                                        ? formatVnd(plan.price)
                                        : formatVnd(
                                              plan.price === 0
                                                  ? 0
                                                  : Math.round(
                                                        plan.price *
                                                            (1 -
                                                                (plan.features
                                                                    ?.yearly_discount_percent ??
                                                                    20) /
                                                                    100),
                                                    ),
                                          )
                                }}
                            </span>
                            <span
                                class="pb-1 text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                            >
                                {{
                                    billingPeriod === 'monthly'
                                        ? '/tháng'
                                        : '/tháng (trả năm)'
                                }}
                            </span>
                        </div>
                        <!-- Yearly savings badge -->
                        <div
                            v-if="billingPeriod === 'yearly' && plan.price > 0"
                            class="mt-1 animate-in text-[10px] font-black tracking-wider text-emerald-500 uppercase duration-200 slide-in-from-top-1"
                        >
                            Tiết kiệm
                            {{
                                formatVnd(
                                    plan.price *
                                        12 *
                                        ((plan.features
                                            ?.yearly_discount_percent ?? 20) /
                                            100),
                                )
                            }}/năm
                        </div>

                        <CardDescription
                            class="mt-2 min-h-[36px] text-[11px] leading-relaxed font-semibold text-muted-foreground"
                        >
                            {{
                                plan.features?.description ||
                                planNotes[plan.code] ||
                                ''
                            }}
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex-grow space-y-4 pt-0">
                        <!-- Limits metrics grid -->
                        <div
                            class="grid grid-cols-2 gap-2 rounded-xl border border-border/30 bg-muted/40 p-2.5"
                        >
                            <!-- Branches -->
                            <div
                                class="shadow-3xs flex items-center gap-2 rounded-lg border border-border/20 bg-background/50 p-1.5"
                            >
                                <Building2
                                    class="size-3.5 shrink-0 text-muted-foreground/80"
                                />
                                <div class="min-w-0">
                                    <p
                                        class="text-[8px] leading-none font-black text-muted-foreground uppercase"
                                    >
                                        Chi nhánh
                                    </p>
                                    <p
                                        class="mt-1 truncate font-mono text-xs leading-none font-black"
                                    >
                                        {{ formatLimit(plan.max_branches) }}
                                    </p>
                                </div>
                            </div>
                            <!-- Tables -->
                            <div
                                class="shadow-3xs flex items-center gap-2 rounded-lg border border-border/20 bg-background/50 p-1.5"
                            >
                                <Table
                                    class="size-3.5 shrink-0 text-muted-foreground/80"
                                />
                                <div class="min-w-0">
                                    <p
                                        class="text-[8px] leading-none font-black text-muted-foreground uppercase"
                                    >
                                        Số bàn
                                    </p>
                                    <p
                                        class="mt-1 truncate font-mono text-xs leading-none font-black"
                                    >
                                        {{ formatLimit(plan.max_tables) }}
                                    </p>
                                </div>
                            </div>
                            <!-- Staff -->
                            <div
                                class="shadow-3xs flex items-center gap-2 rounded-lg border border-border/20 bg-background/50 p-1.5"
                            >
                                <Users
                                    class="size-3.5 shrink-0 text-muted-foreground/80"
                                />
                                <div class="min-w-0">
                                    <p
                                        class="text-[8px] leading-none font-black text-muted-foreground uppercase"
                                    >
                                        Nhân viên
                                    </p>
                                    <p
                                        class="mt-1 truncate font-mono text-xs leading-none font-black"
                                    >
                                        {{ formatLimit(plan.max_users) }}
                                    </p>
                                </div>
                            </div>
                            <!-- Storage -->
                            <div
                                class="shadow-3xs flex items-center gap-2 rounded-lg border border-border/20 bg-background/50 p-1.5"
                            >
                                <HardDrive
                                    class="size-3.5 shrink-0 text-muted-foreground/80"
                                />
                                <div class="min-w-0">
                                    <p
                                        class="text-[8px] leading-none font-black text-muted-foreground uppercase"
                                    >
                                        Lưu trữ
                                    </p>
                                    <p
                                        class="mt-1 truncate font-mono text-xs leading-none font-black text-slate-800 dark:text-slate-200"
                                    >
                                        {{
                                            formatStorage(
                                                plan.features?.max_storage_mb ??
                                                    500,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- API rate limit under the limits grid -->
                        <div
                            class="flex items-center justify-between rounded-lg border border-border/20 bg-muted/20 px-3 py-1.5 font-mono text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                        >
                            <span class="flex items-center gap-1"
                                ><Activity class="size-3 text-sky-500" /> API
                                Rate Limit</span
                            >
                            <span class="font-black text-foreground"
                                >{{
                                    formatRate(
                                        plan.features?.api_rate_limit ?? 30,
                                    )
                                }}
                                req/m</span
                            >
                        </div>

                        <!-- Features list checklist -->
                        <div
                            class="flex-grow space-y-2 border-t border-dashed border-border/40 pt-4"
                        >
                            <!-- Enabled boolean features -->
                            <div
                                v-for="feat in ALL_FEATURES.filter(
                                    (f) => plan.features?.[f.key],
                                )"
                                :key="feat.key"
                                class="flex items-start gap-2.5"
                            >
                                <span
                                    class="shadow-3xs mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                >
                                    <Check class="size-2.5 stroke-[3]" />
                                </span>
                                <span
                                    class="text-[11px] leading-snug font-bold text-slate-700 dark:text-slate-200"
                                    >{{ feat.label }}</span
                                >
                            </div>
                            <!-- Disabled boolean features -->
                            <div
                                v-for="unfeat in ALL_FEATURES.filter(
                                    (f) => !plan.features?.[f.key],
                                )"
                                :key="unfeat.key"
                                class="flex items-start gap-2.5 opacity-40"
                            >
                                <span
                                    class="shadow-3xs mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border border-border/60 bg-muted text-muted-foreground"
                                >
                                    <X class="size-2.5 stroke-[3]" />
                                </span>
                                <span
                                    class="text-[11px] leading-snug font-semibold text-muted-foreground line-through"
                                    >{{ unfeat.label }}</span
                                >
                            </div>
                        </div>
                    </CardContent>

                    <!-- Meta footer -->
                    <div
                        class="mt-3 border-t border-border/40 bg-muted/10 px-6 pt-2 pb-4"
                    >
                        <div
                            class="flex items-center justify-between text-xs text-muted-foreground"
                        >
                            <button
                                @click="showRestaurants(plan)"
                                class="shadow-3xs flex cursor-pointer items-center gap-1 rounded-full border border-border/50 bg-muted px-2.5 py-1 text-[11px] font-bold text-muted-foreground transition-all hover:bg-primary/5 hover:text-primary"
                            >
                                <span
                                    class="inline-block size-1.5 animate-pulse rounded-full bg-emerald-500"
                                ></span>
                                {{ plan.restaurants_count }} nhà hàng đang dùng
                            </button>
                            <span
                                class="rounded border border-slate-800 bg-slate-900 px-2 py-0.5 font-mono text-[9px] font-black tracking-wider text-slate-400 uppercase"
                                >{{ plan.code }}</span
                            >
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Info note -->
        <p class="text-center text-xs text-muted-foreground">
            Thay đổi giá hoặc tính năng sẽ ảnh hưởng ngay đến trang khách hàng —
            không cần deploy lại.
        </p>

        <!-- ── PLAN EDIT SHEET (DRAWER) ── -->
        <Sheet v-model:open="isEditing">
            <SheetContent
                class="overflow-y-auto sm:max-w-xl"
                @close="editingId = null"
            >
                <SheetHeader class="border-b border-border pb-4">
                    <SheetTitle
                        class="flex items-center justify-between text-xl font-bold"
                    >
                        <div class="flex items-center gap-2">
                            <component
                                :is="planIcon[editingPlanCode] ?? Edit2"
                                class="size-5 animate-pulse text-indigo-500"
                            />
                            Chỉnh sửa gói dịch vụ
                        </div>
                        <Badge
                            variant="outline"
                            class="rounded-full border-indigo-500/20 bg-muted px-2 py-0.5 font-mono text-[10px] tracking-wide text-indigo-600 uppercase"
                            >{{ editingPlanCode }}</Badge
                        >
                    </SheetTitle>
                    <SheetDescription
                        >Cập nhật thông tin chi tiết và hạn mức cho gói
                        {{ form.name }}</SheetDescription
                    >
                </SheetHeader>

                <!-- Tab Selector -->
                <div
                    class="my-4 flex rounded-xl border-b border-border/60 bg-muted/40 p-1"
                >
                    <button
                        type="button"
                        @click="activeTab = 'info'"
                        class="flex-1 cursor-pointer rounded-lg py-2 text-center text-xs font-bold transition-all"
                        :class="
                            activeTab === 'info'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        Thông tin & Hạn mức
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'features'"
                        class="flex-1 cursor-pointer rounded-lg py-2 text-center text-xs font-bold transition-all"
                        :class="
                            activeTab === 'features'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        Tính năng ({{
                            ALL_FEATURES.filter((f) => (form as any)[f.key])
                                .length
                        }})
                    </button>
                </div>

                <div class="flex flex-col gap-4 py-2">
                    <!-- Tab 1: Info & Limits -->
                    <div
                        v-show="activeTab === 'info'"
                        class="animate-in space-y-4 duration-200 fade-in"
                    >
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2 grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Tên gói</Label
                                >
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Tag class="size-4 text-indigo-500" />
                                    </div>
                                    <Input
                                        v-model="form.name"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="col-span-2 grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Mô tả ngắn (hiển thị trên trang
                                    khách)</Label
                                >
                                <div class="relative">
                                    <textarea
                                        v-model="form.description"
                                        rows="2"
                                        class="flex w-full resize-none rounded-xl border border-border border-input bg-background py-2.5 pr-3 pl-10 text-sm text-foreground focus-visible:border-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-500/20 focus-visible:ring-ring focus-visible:outline-none"
                                        placeholder="Mô tả ngắn về gói dịch vụ..."
                                    />
                                    <div
                                        class="pointer-events-none absolute top-3 left-3.5 text-muted-foreground"
                                    >
                                        <FileText
                                            class="size-4 text-indigo-500"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Giá (VND/tháng)</Label
                                >
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Coins class="size-4 text-indigo-500" />
                                    </div>
                                    <Input
                                        v-model.number="form.price"
                                        type="number"
                                        min="0"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Chiết khấu gói năm (%)</Label
                                >
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Percent
                                            class="size-4 text-indigo-500"
                                        />
                                    </div>
                                    <Input
                                        v-model.number="
                                            form.yearly_discount_percent
                                        "
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Rate limit (req/phút)</Label
                                >
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Activity
                                            class="size-4 text-indigo-500"
                                        />
                                    </div>
                                    <Input
                                        v-model.number="form.api_rate_limit"
                                        type="number"
                                        min="10"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between">
                                    <Label
                                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >Chi nhánh tối đa</Label
                                    >
                                    <Badge
                                        v-if="form.max_branches === -1"
                                        variant="outline"
                                        class="h-4 border-indigo-500/20 bg-indigo-500/10 px-1.5 py-0 text-[10px] font-bold text-indigo-600"
                                        >Vô hạn</Badge
                                    >
                                </div>
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Building2
                                            class="size-4 text-indigo-500"
                                        />
                                    </div>
                                    <Input
                                        v-model.number="form.max_branches"
                                        type="number"
                                        min="-1"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between">
                                    <Label
                                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >Bàn tối đa</Label
                                    >
                                    <Badge
                                        v-if="form.max_tables === -1"
                                        variant="outline"
                                        class="h-4 border-indigo-500/20 bg-indigo-500/10 px-1.5 py-0 text-[10px] font-bold text-indigo-600"
                                        >Vô hạn</Badge
                                    >
                                </div>
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Table class="size-4 text-indigo-500" />
                                    </div>
                                    <Input
                                        v-model.number="form.max_tables"
                                        type="number"
                                        min="-1"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between">
                                    <Label
                                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >Nhân viên tối đa</Label
                                    >
                                    <Badge
                                        v-if="form.max_users === -1"
                                        variant="outline"
                                        class="h-4 border-indigo-500/20 bg-indigo-500/10 px-1.5 py-0 text-[10px] font-bold text-indigo-600"
                                        >Vô hạn</Badge
                                    >
                                </div>
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Users class="size-4 text-indigo-500" />
                                    </div>
                                    <Input
                                        v-model.number="form.max_users"
                                        type="number"
                                        min="-1"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between">
                                    <Label
                                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >Khu vực tối đa</Label
                                    >
                                    <Badge
                                        v-if="form.max_areas === -1"
                                        variant="outline"
                                        class="h-4 border-indigo-500/20 bg-indigo-500/10 px-1.5 py-0 text-[10px] font-bold text-indigo-600"
                                        >Vô hạn</Badge
                                    >
                                </div>
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Layers
                                            class="size-4 text-indigo-500"
                                        />
                                    </div>
                                    <Input
                                        v-model.number="form.max_areas"
                                        type="number"
                                        min="-1"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="col-span-2 grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Dung lượng lưu trữ tối đa (MB)</Label
                                >
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <HardDrive
                                            class="size-4 text-indigo-500"
                                        />
                                    </div>
                                    <Input
                                        v-model.number="form.max_storage_mb"
                                        type="number"
                                        min="1"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Feature Toggles -->
                    <div
                        v-show="activeTab === 'features'"
                        class="animate-in space-y-4 duration-200 fade-in"
                    >
                        <div
                            v-for="category in FEATURE_CATEGORIES"
                            :key="category.name"
                            class="space-y-3.5 rounded-2xl border border-border bg-card p-4 shadow-xs"
                        >
                            <div
                                class="flex items-center gap-2 border-b border-border/50 pb-2"
                            >
                                <component
                                    :is="
                                        categoryIcon[category.icon] ?? Sparkles
                                    "
                                    class="size-4 text-indigo-500"
                                />
                                <h3
                                    class="text-xs font-bold tracking-wider text-foreground uppercase"
                                >
                                    {{ category.name }}
                                </h3>
                            </div>

                            <div class="grid grid-cols-1 gap-2">
                                <div
                                    v-for="key in category.keys"
                                    :key="key"
                                    class="flex items-center justify-between rounded-xl border border-border bg-muted/10 p-3 transition-all duration-200 hover:bg-muted/20"
                                    :class="{
                                        'border-indigo-500/20 bg-indigo-500/[0.02] shadow-2xs':
                                            (form as any)[key],
                                    }"
                                >
                                    <span
                                        class="pr-2 text-xs font-medium text-foreground"
                                        >{{ getFeatureLabel(key) }}</span
                                    >
                                    <label
                                        class="relative inline-flex shrink-0 cursor-pointer items-center"
                                    >
                                        <input
                                            type="checkbox"
                                            v-model="(form as any)[key]"
                                            class="peer sr-only"
                                        />
                                        <div
                                            class="peer h-4.5 w-8 rounded-full bg-zinc-200 peer-checked:bg-gradient-to-r peer-checked:from-indigo-600 peer-checked:to-violet-600 after:absolute after:top-[2px] after:left-[2px] after:h-3.5 after:w-3.5 after:rounded-full after:border after:border-zinc-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-3.5 peer-checked:after:border-white dark:bg-zinc-800"
                                        ></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-4 flex gap-3 border-t border-border pt-4">
                        <Button
                            size="lg"
                            @click="editingId && save(editingId)"
                            :disabled="form.processing"
                            class="flex-1 cursor-pointer rounded-xl border-none bg-gradient-to-r from-indigo-600 to-violet-600 py-6 text-xs font-bold tracking-wider text-white uppercase shadow-md transition-all hover:-translate-y-0.5 hover:from-indigo-500 hover:to-violet-500 hover:shadow-lg active:translate-y-0"
                        >
                            <Save class="mr-1.5 size-4" />
                            {{
                                form.processing ? 'Đang lưu...' : 'Lưu thay đổi'
                            }}
                        </Button>
                        <Button
                            size="lg"
                            variant="outline"
                            @click="isEditing = false"
                            class="rounded-xl border-border py-6 text-xs font-bold tracking-wider uppercase"
                        >
                            Hủy
                        </Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>

        <!-- ── CREATE PLAN DIALOG ── -->
        <Dialog
            :open="isCreating"
            @update:open="
                (val) => {
                    if (!val) {
                        isCreating = false;
                        createForm.reset();
                    }
                }
            "
        >
            <DialogContent
                class="max-h-[90vh] overflow-y-auto rounded-2xl sm:max-w-xl"
            >
                <DialogHeader class="border-b border-border pb-4">
                    <DialogTitle
                        class="flex items-center gap-2 text-xl font-bold"
                    >
                        <component
                            :is="planIcon[createForm.code] ?? Sparkles"
                            class="size-5 text-indigo-500"
                        />
                        Tạo gói dịch vụ mới
                    </DialogTitle>
                    <DialogDescription
                        >Gói mới sẽ xuất hiện ngay trên trang khách hàng sau khi
                        tạo.</DialogDescription
                    >
                </DialogHeader>

                <!-- Tab Selector for Create -->
                <div
                    class="my-4 flex rounded-xl border-b border-border/60 bg-muted/40 p-1"
                >
                    <button
                        type="button"
                        @click="activeCreateTab = 'info'"
                        class="flex-1 cursor-pointer rounded-lg py-2 text-center text-xs font-bold transition-all"
                        :class="
                            activeCreateTab === 'info'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        Thông tin & Hạn mức
                    </button>
                    <button
                        type="button"
                        @click="activeCreateTab = 'features'"
                        class="flex-1 cursor-pointer rounded-lg py-2 text-center text-xs font-bold transition-all"
                        :class="
                            activeCreateTab === 'features'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        Tính năng ({{
                            ALL_FEATURES.filter(
                                (f) => (createForm as any)[f.key],
                            ).length
                        }})
                    </button>
                </div>

                <form
                    class="flex flex-col gap-4 py-2"
                    @submit.prevent="submitCreate"
                >
                    <!-- Tab 1: Info & Limits -->
                    <div
                        v-show="activeCreateTab === 'info'"
                        class="animate-in space-y-4 duration-200 fade-in"
                    >
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Mã gói (code)
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Lock class="size-4 text-indigo-500" />
                                    </div>
                                    <Input
                                        v-model="createForm.code"
                                        placeholder="vd: starter"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                                <p
                                    v-if="createForm.errors.code"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ createForm.errors.code }}
                                </p>
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Chu kỳ thanh toán
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <Select v-model="createForm.billing_cycle">
                                    <SelectTrigger
                                        class="h-9 rounded-xl border-border text-sm focus:border-indigo-500 focus:ring-indigo-500/20"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent class="rounded-xl">
                                        <SelectItem value="monthly"
                                            >Hàng tháng</SelectItem
                                        >
                                        <SelectItem value="yearly"
                                            >Hàng năm</SelectItem
                                        >
                                        <SelectItem value="quarterly"
                                            >Hàng quý</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="col-span-2 grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Tên gói
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Tag class="size-4 text-indigo-500" />
                                    </div>
                                    <Input
                                        v-model="createForm.name"
                                        placeholder="vd: Gói Khởi Nghiệp"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                                <p
                                    v-if="createForm.errors.name"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ createForm.errors.name }}
                                </p>
                            </div>
                            <div class="col-span-2 grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Mô tả ngắn (hiển thị trang khách)</Label
                                >
                                <div class="relative">
                                    <textarea
                                        v-model="createForm.description"
                                        rows="2"
                                        class="flex w-full resize-none rounded-xl border border-border border-input bg-background py-2.5 pr-3 pl-10 text-sm text-foreground focus-visible:border-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-500/20 focus-visible:ring-ring focus-visible:outline-none"
                                        placeholder="Mô tả ngắn gọn về gói..."
                                    />
                                    <div
                                        class="pointer-events-none absolute top-3 left-3.5 text-muted-foreground"
                                    >
                                        <FileText
                                            class="size-4 text-indigo-500"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Giá (VND/tháng)
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                >
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Coins class="size-4 text-indigo-500" />
                                    </div>
                                    <Input
                                        v-model.number="createForm.price"
                                        type="number"
                                        min="0"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Chiết khấu gói năm (%)</Label
                                >
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Percent
                                            class="size-4 text-indigo-500"
                                        />
                                    </div>
                                    <Input
                                        v-model.number="
                                            createForm.yearly_discount_percent
                                        "
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Rate limit (req/phút)</Label
                                >
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Activity
                                            class="size-4 text-indigo-500"
                                        />
                                    </div>
                                    <Input
                                        v-model.number="
                                            createForm.api_rate_limit
                                        "
                                        type="number"
                                        min="10"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between">
                                    <Label
                                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >Chi nhánh tối đa</Label
                                    >
                                    <Badge
                                        v-if="createForm.max_branches === -1"
                                        variant="outline"
                                        class="h-4 border-indigo-500/20 bg-indigo-500/10 px-1.5 py-0 text-[10px] font-bold text-indigo-600"
                                        >Vô hạn</Badge
                                    >
                                </div>
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Building2
                                            class="size-4 text-indigo-500"
                                        />
                                    </div>
                                    <Input
                                        v-model.number="createForm.max_branches"
                                        type="number"
                                        min="-1"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between">
                                    <Label
                                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >Bàn tối đa</Label
                                    >
                                    <Badge
                                        v-if="createForm.max_tables === -1"
                                        variant="outline"
                                        class="h-4 border-indigo-500/20 bg-indigo-500/10 px-1.5 py-0 text-[10px] font-bold text-indigo-600"
                                        >Vô hạn</Badge
                                    >
                                </div>
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Table class="size-4 text-indigo-500" />
                                    </div>
                                    <Input
                                        v-model.number="createForm.max_tables"
                                        type="number"
                                        min="-1"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between">
                                    <Label
                                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >Nhân viên tối đa</Label
                                    >
                                    <Badge
                                        v-if="createForm.max_users === -1"
                                        variant="outline"
                                        class="h-4 border-indigo-500/20 bg-indigo-500/10 px-1.5 py-0 text-[10px] font-bold text-indigo-600"
                                        >Vô hạn</Badge
                                    >
                                </div>
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Users class="size-4 text-indigo-500" />
                                    </div>
                                    <Input
                                        v-model.number="createForm.max_users"
                                        type="number"
                                        min="-1"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <div class="flex items-center justify-between">
                                    <Label
                                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >Khu vực tối đa</Label
                                    >
                                    <Badge
                                        v-if="createForm.max_areas === -1"
                                        variant="outline"
                                        class="h-4 border-indigo-500/20 bg-indigo-500/10 px-1.5 py-0 text-[10px] font-bold text-indigo-600"
                                        >Vô hạn</Badge
                                    >
                                </div>
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <Layers
                                            class="size-4 text-indigo-500"
                                        />
                                    </div>
                                    <Input
                                        v-model.number="createForm.max_areas"
                                        type="number"
                                        min="-1"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                            <div class="col-span-2 grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                    >Lưu trữ (MB)</Label
                                >
                                <div class="relative flex items-center">
                                    <div
                                        class="pointer-events-none absolute left-3.5 text-muted-foreground"
                                    >
                                        <HardDrive
                                            class="size-4 text-indigo-500"
                                        />
                                    </div>
                                    <Input
                                        v-model.number="
                                            createForm.max_storage_mb
                                        "
                                        type="number"
                                        min="1"
                                        class="rounded-xl border-border pl-10 focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Feature Toggles for Create -->
                    <div
                        v-show="activeCreateTab === 'features'"
                        class="animate-in space-y-4 duration-200 fade-in"
                    >
                        <div
                            v-for="category in FEATURE_CATEGORIES"
                            :key="category.name"
                            class="space-y-3.5 rounded-2xl border border-border bg-card p-4 shadow-xs"
                        >
                            <div
                                class="flex items-center gap-2 border-b border-border/50 pb-2"
                            >
                                <component
                                    :is="
                                        categoryIcon[category.icon] ?? Sparkles
                                    "
                                    class="size-4 text-indigo-500"
                                />
                                <h3
                                    class="text-xs font-bold tracking-wider text-foreground uppercase"
                                >
                                    {{ category.name }}
                                </h3>
                            </div>

                            <div class="grid grid-cols-1 gap-2">
                                <div
                                    v-for="key in category.keys"
                                    :key="key"
                                    class="flex items-center justify-between rounded-xl border border-border bg-muted/10 p-3 transition-all duration-200 hover:bg-muted/20"
                                    :class="{
                                        'border-indigo-500/20 bg-indigo-500/[0.02] shadow-2xs':
                                            (createForm as any)[key],
                                    }"
                                >
                                    <span
                                        class="pr-2 text-xs font-medium text-foreground"
                                        >{{ getFeatureLabel(key) }}</span
                                    >
                                    <label
                                        class="relative inline-flex shrink-0 cursor-pointer items-center"
                                    >
                                        <input
                                            type="checkbox"
                                            v-model="(createForm as any)[key]"
                                            class="peer sr-only"
                                        />
                                        <div
                                            class="peer h-4.5 w-8 rounded-full bg-zinc-200 peer-checked:bg-gradient-to-r peer-checked:from-indigo-600 peer-checked:to-violet-600 after:absolute after:top-[2px] after:left-[2px] after:h-3.5 after:w-3.5 after:rounded-full after:border after:border-zinc-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-3.5 peer-checked:after:border-white dark:bg-zinc-800"
                                        ></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex gap-3 border-t border-border pt-4">
                        <Button
                            type="submit"
                            size="lg"
                            :disabled="createForm.processing"
                            class="flex-1 cursor-pointer rounded-xl border-none bg-gradient-to-r from-indigo-600 to-violet-600 py-6 text-xs font-bold tracking-wider text-white uppercase shadow-md transition-all hover:-translate-y-0.5 hover:from-indigo-500 hover:to-violet-500 hover:shadow-lg active:translate-y-0"
                        >
                            <Plus class="mr-1.5 size-4" />
                            {{
                                createForm.processing
                                    ? 'Đang tạo...'
                                    : 'Tạo gói'
                            }}
                        </Button>
                        <Button
                            type="button"
                            size="lg"
                            variant="outline"
                            @click="
                                isCreating = false;
                                createForm.reset();
                            "
                            class="rounded-xl border-border py-6 text-xs font-bold tracking-wider uppercase"
                        >
                            Hủy
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ── RESTAURANT DIRECTORY DIALOG ── -->
        <Dialog
            :open="!!selectedPlanForRestaurants"
            @update:open="
                (val) => {
                    if (!val) {
                        selectedPlanForRestaurants = null;
                        restaurantSearch = '';
                    }
                }
            "
        >
            <DialogContent
                class="max-h-[80vh] overflow-y-auto rounded-2xl sm:max-w-2xl"
            >
                <DialogHeader class="border-b border-border/40 pb-3">
                    <DialogTitle
                        class="flex items-center gap-2 text-base font-bold"
                    >
                        <Database class="size-5 text-indigo-500" />
                        <span
                            >Nhà hàng đang dùng gói:
                            {{ selectedPlanForRestaurants?.name }}</span
                        >
                    </DialogTitle>
                    <DialogDescription
                        >Danh sách các doanh nghiệp đang đăng ký sử dụng gói
                        dịch vụ này.</DialogDescription
                    >
                </DialogHeader>

                <div class="py-3">
                    <div
                        v-if="isLoadingRestaurants"
                        class="flex items-center justify-center py-12"
                    >
                        <span
                            class="inline-block size-6 animate-spin rounded-full border-2 border-primary border-t-transparent"
                        />
                    </div>
                    <div
                        v-else-if="restaurants.length === 0"
                        class="py-12 text-center text-sm font-semibold text-muted-foreground"
                    >
                        Không có nhà hàng nào đang sử dụng gói này.
                    </div>
                    <div
                        v-else
                        class="animate-in space-y-4 duration-200 fade-in"
                    >
                        <!-- Real-time search filter input -->
                        <div class="relative flex items-center">
                            <div
                                class="pointer-events-none absolute left-3.5 text-muted-foreground/80"
                            >
                                <Search class="size-4" />
                            </div>
                            <Input
                                v-model="restaurantSearch"
                                placeholder="Tìm kiếm nhanh tên nhà hàng, mã code, chủ sở hữu..."
                                class="h-9 rounded-xl border-border py-2 pr-4 pl-10 text-xs focus-visible:border-indigo-500 focus-visible:ring-indigo-500/20"
                            />
                            <button
                                v-if="restaurantSearch"
                                type="button"
                                @click="restaurantSearch = ''"
                                class="absolute right-3 cursor-pointer rounded-full p-0.5 text-muted-foreground hover:bg-muted/50 hover:text-foreground"
                            >
                                <X class="size-3" />
                            </button>
                        </div>

                        <!-- Results table -->
                        <div
                            v-if="filteredRestaurants.length > 0"
                            class="overflow-hidden rounded-xl border border-border bg-background/50"
                        >
                            <table
                                class="w-full border-collapse text-left text-[11px] font-semibold"
                            >
                                <thead>
                                    <tr
                                        class="border-b border-border bg-muted/50 font-black tracking-wider text-muted-foreground uppercase"
                                    >
                                        <th class="p-3">Tên Nhà Hàng</th>
                                        <th class="p-3">Mã Code</th>
                                        <th class="p-3">Chủ sở hữu</th>
                                        <th class="p-3">Ngày hết hạn</th>
                                        <th class="p-3 text-right">
                                            Trạng thái
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/40">
                                    <tr
                                        v-for="res in filteredRestaurants"
                                        :key="res.id"
                                        class="text-slate-700 transition-all hover:bg-muted/30 dark:text-slate-300"
                                    >
                                        <td class="p-3 font-semibold">
                                            <div
                                                class="flex items-center gap-2.5"
                                            >
                                                <!-- Initials round avatar with gradient shadow -->
                                                <div
                                                    :class="[
                                                        'flex size-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-tr text-[9px] font-black text-white shadow-xs',
                                                        getGradientForName(
                                                            res.name,
                                                        ),
                                                    ]"
                                                >
                                                    {{ getInitials(res.name) }}
                                                </div>
                                                <div class="min-w-0">
                                                    <a
                                                        :href="`/super-admin/restaurants/${res.id}`"
                                                        class="block truncate font-black text-primary hover:underline"
                                                    >
                                                        {{ res.name }}
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td
                                            class="p-3 font-mono text-[9px] text-slate-500"
                                        >
                                            {{ res.code }}
                                        </td>
                                        <td class="p-3 font-medium">
                                            <div
                                                class="font-bold text-slate-800 dark:text-slate-200"
                                            >
                                                {{ res.owner_name }}
                                            </div>
                                            <div
                                                class="max-w-[150px] truncate text-[9px] text-muted-foreground"
                                            >
                                                {{ res.owner_email }}
                                            </div>
                                        </td>
                                        <td class="p-3 font-mono">
                                            {{ res.subscription_ends_at }}
                                        </td>
                                        <td class="p-3 text-right">
                                            <Badge
                                                variant="outline"
                                                :class="[
                                                    'rounded-full px-2 py-0 text-[9px] font-black uppercase',
                                                    res.status === 'active'
                                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                                        : 'border-slate-500/20 bg-slate-500/10 text-slate-600',
                                                ]"
                                            >
                                                {{ res.status }}
                                            </Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Empty Search Results -->
                        <div
                            v-else
                            class="py-10 text-center text-xs font-semibold text-muted-foreground"
                        >
                            Không tìm thấy nhà hàng nào phù hợp với từ khóa tìm
                            kiếm.
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- ── FEATURE COMPARISON DIALOG ── -->
        <Dialog
            :open="showComparison"
            @update:open="(val) => (showComparison = val)"
        >
            <DialogContent
                class="max-h-[85vh] overflow-y-auto rounded-2xl sm:max-w-4xl"
            >
                <DialogHeader class="border-b border-border/40 pb-3">
                    <DialogTitle
                        class="flex items-center gap-2 text-base font-bold"
                    >
                        <Table class="size-5 text-indigo-500" />
                        <span>Bảng so sánh chi tiết các gói dịch vụ</span>
                    </DialogTitle>
                    <DialogDescription
                        >Đối chiếu hạn mức và quyền lợi tính năng đầy đủ giữa
                        các gói dịch vụ hiện hành.</DialogDescription
                    >
                </DialogHeader>

                <div class="overflow-x-auto py-3">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr class="border-b border-border/60 bg-muted/50">
                                <th
                                    class="w-[240px] p-3 font-black tracking-wider text-muted-foreground uppercase"
                                >
                                    Hạn mức & Tính năng
                                </th>
                                <th
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    class="w-[150px] p-3 text-center"
                                >
                                    <div
                                        class="mb-1 flex items-center justify-center gap-1 text-sm font-black text-foreground"
                                    >
                                        <component
                                            :is="planIcon[plan.code] ?? Star"
                                            class="size-3.5 text-indigo-500"
                                        />
                                        {{ plan.name }}
                                    </div>
                                    <div
                                        class="font-mono text-[10px] font-black text-muted-foreground"
                                    >
                                        {{ formatVnd(plan.price) }}/tháng
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <!-- Section: Limits -->
                            <tr
                                class="bg-muted/10 text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                            >
                                <td colspan="5" class="p-3">
                                    Hạn mức vận hành (Limits)
                                </td>
                            </tr>
                            <tr
                                class="font-medium transition-colors hover:bg-muted/20"
                            >
                                <td
                                    class="p-3 font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    Chi nhánh tối đa
                                </td>
                                <td
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    class="p-3 text-center font-mono font-bold"
                                >
                                    {{ formatLimit(plan.max_branches) }}
                                </td>
                            </tr>
                            <tr
                                class="font-medium transition-colors hover:bg-muted/20"
                            >
                                <td
                                    class="p-3 font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    Số bàn tối đa
                                </td>
                                <td
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    class="p-3 text-center font-mono font-bold"
                                >
                                    {{ formatLimit(plan.max_tables) }}
                                </td>
                            </tr>
                            <tr
                                class="font-medium transition-colors hover:bg-muted/20"
                            >
                                <td
                                    class="p-3 font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    Nhân viên tối đa
                                </td>
                                <td
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    class="p-3 text-center font-mono font-bold"
                                >
                                    {{ formatLimit(plan.max_users) }}
                                </td>
                            </tr>
                            <tr
                                class="font-medium transition-colors hover:bg-muted/20"
                            >
                                <td
                                    class="p-3 font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    Khu vực tối đa
                                </td>
                                <td
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    class="p-3 text-center font-mono font-bold"
                                >
                                    {{
                                        formatLimit(
                                            plan.features?.max_areas ?? 2,
                                        )
                                    }}
                                </td>
                            </tr>
                            <tr
                                class="font-medium transition-colors hover:bg-muted/20"
                            >
                                <td
                                    class="p-3 font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    Dung lượng lưu trữ
                                </td>
                                <td
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    class="p-3 text-center font-mono font-bold"
                                >
                                    {{
                                        formatStorage(
                                            plan.features?.max_storage_mb ??
                                                500,
                                        )
                                    }}
                                </td>
                            </tr>
                            <tr
                                class="font-medium transition-colors hover:bg-muted/20"
                            >
                                <td
                                    class="p-3 font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    Rate Limit (API req/phút)
                                </td>
                                <td
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    class="p-3 text-center font-mono font-bold"
                                >
                                    {{
                                        formatRate(
                                            plan.features?.api_rate_limit ?? 30,
                                        )
                                    }}
                                    req/m
                                </td>
                            </tr>
                            <tr
                                class="font-medium transition-colors hover:bg-muted/20"
                            >
                                <td
                                    class="p-3 font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    Chiết khấu gói năm
                                </td>
                                <td
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    class="p-3 text-center font-mono font-black font-bold text-emerald-500"
                                >
                                    {{
                                        plan.features
                                            ?.yearly_discount_percent ?? 20
                                    }}%
                                </td>
                            </tr>

                            <!-- Section: Features Checklist -->
                            <tr
                                class="bg-muted/10 text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                            >
                                <td colspan="5" class="p-3">
                                    Tính năng tích hợp (Features)
                                </td>
                            </tr>
                            <tr
                                v-for="feat in ALL_FEATURES"
                                :key="feat.key"
                                class="font-medium text-slate-700 transition-colors hover:bg-muted/20 dark:text-slate-300"
                            >
                                <td
                                    class="p-3 font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ feat.label }}
                                </td>
                                <td
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    class="p-3 text-center"
                                >
                                    <span
                                        v-if="plan.features?.[feat.key]"
                                        class="shadow-3xs inline-flex size-5 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                    >
                                        <Check class="size-3 stroke-[3]" />
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex size-5 shrink-0 items-center justify-center rounded-full border border-border bg-muted text-muted-foreground/30 opacity-40"
                                    >
                                        <X class="size-3 stroke-[3]" />
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
