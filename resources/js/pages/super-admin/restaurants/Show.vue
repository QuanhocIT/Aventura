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
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    restaurant: {
        id: number; name: string; code: string; slug: string;
        tax_code: string; phone: string; email: string; address: string;
        status: string; timezone: string; currency: string;
        trial_ends_at: string; subscription_ends_at: string; created_at: string;
        last_active_at?: string;
        is_inactive_flagged?: boolean;
        inactive_flagged_at?: string;
        custom_storage_limit_mb?: number | null;
        today_activity?: { orders_count: number; dishes_prepared_count: number; revenue: number };
        owner: { id?: number; name: string; email: string };
        plan: { id: number; name: string; code: string };
    };
    quota: {
        plan: string; plan_code: string;
        resources: Record<string, { used: number; limit: number; unlimited: boolean; percentage: number; can_add: boolean }>;
        features: Record<string, boolean>;
        rate_limit: number;
    };
    subscriptions: Array<{ id: number; plan: string; status: string; started_at: string; ended_at: string; price: string }>;
    invoices: Array<{ id: number; invoice_number: string; type: string; status: string; total: string; currency: string; due_on: string; sent_at: string }>;
    adjustments: Array<{ id: number; type: string; days: number; discount_amount: string; reason: string; created_at: string; creator: string }>;
    webhooks: Array<{ id: number; provider: string; status: string; event_type: string; transaction_code: string; processed_at: string }>;
    plans: Array<{ id: number; code: string; name: string }>;
}>();

const statusForm = useForm({ status: props.restaurant.status, reason: '' });
function updateStatus() {
    statusForm.patch(`/super-admin/restaurants/${props.restaurant.id}/status`);
}

const planForm = useForm({ plan_id: String(props.restaurant.plan.id) });
function updatePlan() {
    planForm.patch(`/super-admin/restaurants/${props.restaurant.id}/plan`);
}

const storageForm = useForm({ custom_storage_limit_mb: props.restaurant.custom_storage_limit_mb ?? '' });
function updateStorageQuota() {
    storageForm.patch(`/super-admin/restaurants/${props.restaurant.id}/storage-quota`, {
        preserveScroll: true
    });
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
    overrideForm.post(`/super-admin/restaurants/${props.restaurant.id}/billing-overrides`, {
        preserveScroll: true,
        onSuccess: () => {
            overrideForm.password = '';
        },
    });
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
    customPlanForm.max_dishes = dishes ? (dishes.unlimited ? -1 : dishes.limit) : -1;
    
    const areas = props.quota.resources.areas;
    customPlanForm.max_areas = areas ? (areas.unlimited ? -1 : areas.limit) : -1;
    
    const storage = props.quota.resources.storage_mb;
    customPlanForm.max_storage_mb = storage.unlimited ? 10240 : storage.limit;

    customPlanForm.api_rate_limit = props.quota.rate_limit || 60;
    
    customPlanForm.kitchen_display = !!props.quota.features.kitchen_display;
    customPlanForm.qr_ordering = !!props.quota.features.qr_ordering;
    customPlanForm.inventory_basic = !!props.quota.features.inventory_basic;
    customPlanForm.hr_timekeeping = !!props.quota.features.hr_timekeeping;
    customPlanForm.hr_full = !!props.quota.features.hr_full;
    customPlanForm.advanced_analytics = !!props.quota.features.advanced_analytics;
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
    customPlanForm.post(`/super-admin/restaurants/${props.restaurant.id}/custom-plan`, {
        preserveScroll: true,
        onSuccess: () => {
            customPlanForm.password = '';
        },
    });
}

function impersonateUser() {
    if (!props.restaurant.owner?.id) {
        alert('Không tìm thấy tài khoản chủ sở hữu để sắm vai.');

        return;
    }

    if (confirm(`Bạn có chắc chắn muốn đăng nhập sắm vai dưới quyền của tài khoản chủ sở hữu "${props.restaurant.owner.name}" không?`)) {
        router.post(`/super-admin/impersonate/${props.restaurant.owner.id}`);
    }
}

const unflagForm = useForm({});
function unflagRestaurant() {
    if (confirm('Bạn có chắc chắn muốn gỡ gắn cờ cảnh báo và đặt lại mốc hoạt động cuối của nhà hàng này không?')) {
        unflagForm.patch(`/super-admin/restaurants/${props.restaurant.id}/unflag`);
    }
}

function formatCurrency(val: number) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
}

function applyPreset(type: string, days: number, discount: number, reason: string) {
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
    active: 'Hoat dong',
    suspended: 'Tam ngung',
    expired: 'Het han',
    generated: 'Da sinh file',
    sent: 'Da gui',
    pending: 'Dang cho',
    processed: 'Da xu ly',
    orphaned: 'Khong khop',
};

const resourceIcons: Record<string, any> = {
    branches: Building2, employees: Users, areas: LayoutGrid, tables: Table2, storage_mb: HardDrive, dishes: LayoutGrid,
};
const resourceLabels: Record<string, string> = {
    branches: 'Chi nhánh', employees: 'Nhân viên', areas: 'Khu vực', tables: 'Bàn ăn', storage_mb: 'Dung lượng (MB)', dishes: 'Món ăn',
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
        trial: 'Tang trial',
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
        const res = await fetch(`/super-admin/restaurants/${props.restaurant.id}/subscriptions-history?page=${page}`, {
            headers: { Accept: 'application/json' },
        });
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
</script>

<template>
    <Head :title="`${restaurant.name} - Billing Center`" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center gap-4">
            <Link href="/super-admin/restaurants">
                <Button variant="ghost" size="icon-sm"><ArrowLeft class="size-4" /></Button>
            </Link>
            <div class="flex-1">
                <h1 class="text-2xl font-bold tracking-tight">{{ restaurant.name }}</h1>
                <p class="text-sm text-muted-foreground font-mono">{{ restaurant.code }} · Billing Center</p>
            </div>
            <Button
                v-if="restaurant.owner && restaurant.owner.id"
                variant="outline"
                size="sm"
                class="gap-2 text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-900/50 hover:bg-blue-50 dark:hover:bg-blue-950/20"
                @click="impersonateUser"
            >
                <UserCheck class="size-4" /> Sắm vai chủ nhà
            </Button>
            <span :class="['inline-flex rounded-full px-3 py-1 text-sm font-medium', statusColor[restaurant.status] || 'bg-slate-100 text-slate-800']">
                {{ statusLabel[restaurant.status] ?? restaurant.status }}
            </span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1.5fr,0.9fr]">
            <div class="flex flex-col gap-6">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Tong quan doanh nghiep</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2 text-sm">
                        <div><p class="text-muted-foreground">Chu so huu</p><p class="font-medium">{{ restaurant.owner.name || '—' }}</p></div>
                        <div><p class="text-muted-foreground">Email chu</p><p class="font-medium">{{ restaurant.owner.email || '—' }}</p></div>
                        <div><p class="text-muted-foreground">Goi hien tai</p><p class="font-medium">{{ restaurant.plan.name || '—' }}</p></div>
                        <div><p class="text-muted-foreground">Het han dich vu</p><p class="font-medium">{{ restaurant.subscription_ends_at || '—' }}</p></div>
                        <div><p class="text-muted-foreground">Het han trial</p><p class="font-medium">{{ restaurant.trial_ends_at || '—' }}</p></div>
                        <div><p class="text-muted-foreground">Tien te</p><p class="font-medium">{{ restaurant.currency }}</p></div>
                        <div class="md:col-span-2"><p class="text-muted-foreground">Dia chi</p><p class="font-medium">{{ restaurant.address || '—' }}</p></div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Crown class="size-4 text-amber-600" /> Han muc goi dich vu
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-2">
                        <div v-for="(res, key) in quota.resources" :key="key" class="rounded-xl border border-border/70 bg-background/70 p-4">
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2 font-medium">
                                    <component :is="resourceIcons[key]" class="size-4 text-muted-foreground" />
                                    {{ resourceLabels[key] ?? key }}
                                </span>
                                <span class="font-mono text-xs text-muted-foreground">
                                    {{ res.used }} / {{ res.unlimited ? '∞' : res.limit }}
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted">
                                <div v-if="!res.unlimited" :class="['h-full rounded-full transition-all', barColor(res.percentage, res.can_add)]" :style="{ width: `${res.percentage}%` }" />
                                <div v-else class="h-full w-full rounded-full bg-emerald-500/30" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Custom Plan Builder -->
                <Card class="border-indigo-500/20 shadow-md">
                    <CardHeader class="pb-3 border-b bg-slate-50/50 dark:bg-slate-900/20 flex flex-row items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-base text-indigo-600 dark:text-indigo-400">
                            <Crown class="size-5" /> Trình thiết kế Gói dịch vụ tùy chỉnh cho Doanh nghiệp (Enterprise Custom Plan Builder)
                        </CardTitle>
                        <Button type="button" size="sm" variant="outline" class="text-xs font-semibold" @click="loadDefaultsFromCurrentPlan">
                            Tải nhanh thông số hiện tại
                        </Button>
                    </CardHeader>
                    <CardContent class="pt-6">
                        <form @submit.prevent="submitCustomPlan" class="space-y-6">
                            <!-- Section 1: Contract Setup -->
                            <div>
                                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-3 border-l-2 border-indigo-500 pl-2">1. Thông tin hợp đồng & Giá</h3>
                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="grid gap-1.5">
                                        <Label for="cp-name">Tên gói đăng ký</Label>
                                        <Input id="cp-name" v-model="customPlanForm.name" placeholder="Gói Custom - Chuỗi Kichi..." />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-price">Đơn giá hợp đồng (VND)</Label>
                                        <Input id="cp-price" type="number" v-model="customPlanForm.price" min="0" required />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-cycle">Chu kỳ thanh toán</Label>
                                        <select id="cp-cycle" v-model="customPlanForm.billing_cycle" class="h-9 rounded-md border bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <option value="monthly">Theo tháng (1 tháng)</option>
                                            <option value="quarterly">Theo quý (3 tháng)</option>
                                            <option value="half_yearly">Nửa năm (6 tháng)</option>
                                            <option value="yearly">Theo năm (1 năm)</option>
                                            <option value="biennial">Hợp đồng 2 năm</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Quotas -->
                            <div>
                                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-3 border-l-2 border-indigo-500 pl-2">2. Thiết lập hạn ngạch (Quotas)</h3>
                                <p class="text-xs text-muted-foreground mb-3 font-medium">Nhập <code class="bg-muted px-1.5 py-0.5 rounded text-indigo-600 font-bold">-1</code> để thiết lập không giới hạn (∞) cho tài nguyên.</p>
                                <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                                    <div class="grid gap-1.5">
                                        <Label for="cp-branches">Số chi nhánh tối đa</Label>
                                        <Input id="cp-branches" type="number" v-model="customPlanForm.max_branches" required />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-users">Số nhân viên tối đa</Label>
                                        <Input id="cp-users" type="number" v-model="customPlanForm.max_users" required />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-tables">Số bàn tối đa</Label>
                                        <Input id="cp-tables" type="number" v-model="customPlanForm.max_tables" required />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-dishes">Số món ăn tối đa</Label>
                                        <Input id="cp-dishes" type="number" v-model="customPlanForm.max_dishes" required />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-areas">Số khu vực tối đa</Label>
                                        <Input id="cp-areas" type="number" v-model="customPlanForm.max_areas" required />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-storage">Dung lượng lưu trữ (MB)</Label>
                                        <Input id="cp-storage" type="number" v-model="customPlanForm.max_storage_mb" required />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="cp-rate">API Rate Limit (req/min)</Label>
                                        <Input id="cp-rate" type="number" v-model="customPlanForm.api_rate_limit" required />
                                    </div>
                                </div>
                            </div>

                            <!-- Section 3: Add-on Modules -->
                            <div>
                                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-3 border-l-2 border-indigo-500 pl-2">3. Tính năng nâng cao & Add-ons</h3>
                                
                                <div class="grid gap-4 md:grid-cols-2">
                                    <!-- Group A: Custom Enterprise Add-ons -->
                                    <div class="rounded-xl border border-indigo-100 bg-indigo-50/30 p-4 space-y-3.5">
                                        <h4 class="text-xs font-bold text-indigo-700 uppercase tracking-wider">Enterprise Add-ons</h4>
                                        <div class="flex items-start gap-2.5">
                                            <input type="checkbox" id="add-rfm" v-model="customPlanForm.rfm_ai_analysis" class="mt-1 size-4 accent-indigo-600 rounded" />
                                            <Label for="add-rfm" class="text-xs font-semibold cursor-pointer">
                                                Phân tích AI khách hàng RFM
                                                <span class="block text-[10px] text-muted-foreground font-normal">Kích hoạt phân cụm, chấm điểm và đề xuất chiến dịch CDP.</span>
                                            </Label>
                                        </div>
                                        <div class="flex items-start gap-2.5">
                                            <input type="checkbox" id="add-fraud" v-model="customPlanForm.fraud_detection" class="mt-1 size-4 accent-indigo-600 rounded" />
                                            <Label for="add-fraud" class="text-xs font-semibold cursor-pointer">
                                                Phát hiện gian lận tự động (AI Audit)
                                                <span class="block text-[10px] text-muted-foreground font-normal">Quét kiểm toán hóa đơn, sai phạm và cảnh báo rủi ro thất thoát.</span>
                                            </Label>
                                        </div>
                                        <div class="flex items-start gap-2.5">
                                            <input type="checkbox" id="add-support" v-model="customPlanForm.priority_support" class="mt-1 size-4 accent-indigo-600 rounded" />
                                            <Label for="add-support" class="text-xs font-semibold cursor-pointer">
                                                Ưu tiên xử lý ticket hỗ trợ (SLA VIP)
                                                <span class="block text-[10px] text-muted-foreground font-normal">Đẩy vé hỗ trợ lên Critical / P1 và phân phối phản hồi nhanh nhất.</span>
                                            </Label>
                                        </div>
                                    </div>

                                    <!-- Group B: Standard Modules -->
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/30 p-4 grid grid-cols-2 gap-3.5">
                                        <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider col-span-2">Standard Modules</h4>
                                        
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" id="feat-kitchen" v-model="customPlanForm.kitchen_display" class="size-4 accent-indigo-600 rounded" />
                                            <Label for="feat-kitchen" class="text-xs cursor-pointer">Màn hình Bếp (KDS)</Label>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" id="feat-qr" v-model="customPlanForm.qr_ordering" class="size-4 accent-indigo-600 rounded" />
                                            <Label for="feat-qr" class="text-xs cursor-pointer">Gọi món QR tại bàn</Label>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" id="feat-inv" v-model="customPlanForm.inventory_basic" class="size-4 accent-indigo-600 rounded" />
                                            <Label for="feat-inv" class="text-xs cursor-pointer">Quản lý Kho cơ bản</Label>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" id="feat-supply" v-model="customPlanForm.supplier_portal" class="size-4 accent-indigo-600 rounded" />
                                            <Label for="feat-supply" class="text-xs cursor-pointer">Cổng Nhà cung cấp</Label>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" id="feat-shift" v-model="customPlanForm.hr_timekeeping" class="size-4 accent-indigo-600 rounded" />
                                            <Label for="feat-shift" class="text-xs cursor-pointer">Xếp ca làm việc</Label>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" id="feat-hr" v-model="customPlanForm.hr_full" class="size-4 accent-indigo-600 rounded" />
                                            <Label for="feat-hr" class="text-xs cursor-pointer">Quản lý Nhân sự / Lương</Label>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" id="feat-realtime" v-model="customPlanForm.realtime" class="size-4 accent-indigo-600 rounded" />
                                            <Label for="feat-realtime" class="text-xs cursor-pointer">Realtime Reverb</Label>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" id="feat-api" v-model="customPlanForm.api_access" class="size-4 accent-indigo-600 rounded" />
                                            <Label for="feat-api" class="text-xs cursor-pointer">API Integrations</Label>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" id="feat-advisor" v-model="customPlanForm.ai_advisor" class="size-4 accent-indigo-600 rounded" />
                                            <Label for="feat-advisor" class="text-xs cursor-pointer">Trợ lý Chiến lược AI</Label>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" id="feat-forecast" v-model="customPlanForm.ai_forecasting" class="size-4 accent-indigo-600 rounded" />
                                            <Label for="feat-forecast" class="text-xs cursor-pointer">AI Dự báo tồn kho</Label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 4: Security Confirmation -->
                            <div class="border-t pt-5 grid gap-4 md:grid-cols-2 items-end">
                                <div class="grid gap-1.5">
                                    <Label for="cp-pwd" class="text-rose-600 dark:text-rose-400 font-semibold">Xác nhận mật khẩu Super Admin của bạn</Label>
                                    <Input id="cp-pwd" type="password" v-model="customPlanForm.password" placeholder="Nhập mật khẩu để phê duyệt gói ad-hoc" required />
                                    <span v-if="customPlanForm.errors.password" class="text-xs text-rose-600 font-medium">{{ customPlanForm.errors.password }}</span>
                                </div>
                                <Button type="submit" :disabled="customPlanForm.processing" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold h-10 w-full justify-center">
                                    {{ customPlanForm.processing ? 'Đang tạo và kích hoạt gói...' : 'Kích hoạt & Áp dụng gói tùy chỉnh này' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div class="grid gap-6 xl:grid-cols-3">
                    <Card class="xl:col-span-2">
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center justify-between gap-2 text-base">
                                <span class="flex items-center gap-2"><ReceiptText class="size-4 text-sky-600" /> Hoa don gan day</span>
                                <a :href="`/super-admin/billing?restaurant_id=${restaurant.id}`" class="flex items-center gap-1 text-xs font-normal text-sky-600 hover:underline dark:text-sky-400">
                                    Xem tất cả <ExternalLink class="size-3" />
                                </a>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-for="invoice in invoices" :key="invoice.id" class="rounded-xl border border-border/70 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium">{{ invoice.invoice_number }}</p>
                                        <p class="text-xs text-muted-foreground">{{ typeLabel(invoice.type) }} · Han {{ invoice.due_on || '—' }}</p>
                                    </div>
                                    <span :class="['rounded-full px-2.5 py-1 text-xs font-medium', statusColor[invoice.status] || 'bg-slate-100 text-slate-800']">
                                        {{ statusLabel[invoice.status] ?? invoice.status }}
                                    </span>
                                </div>
                                <div class="mt-3 flex items-center justify-between text-sm">
                                    <span class="font-mono">{{ invoice.total }} {{ invoice.currency }}</span>
                                    <span class="text-muted-foreground">Gui luc: {{ invoice.sent_at || 'Chua gui' }}</span>
                                </div>
                            </div>
                            <p v-if="!invoices.length" class="py-6 text-center text-sm text-muted-foreground">Chua co hoa don nao.</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center justify-between gap-2 text-base">
                                <span class="flex items-center gap-2"><RefreshCcw class="size-4 text-violet-600" /> Webhook log</span>
                                <a :href="`/super-admin/billing?restaurant_id=${restaurant.id}`" class="flex items-center gap-1 text-xs font-normal text-violet-600 hover:underline dark:text-violet-400">
                                    Xem tất cả <ExternalLink class="size-3" />
                                </a>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-for="webhook in webhooks" :key="webhook.id" class="rounded-xl border border-border/70 p-3 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium">{{ webhook.provider }}</span>
                                    <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', statusColor[webhook.status] || 'bg-slate-100 text-slate-800']">
                                        {{ statusLabel[webhook.status] ?? webhook.status }}
                                    </span>
                                </div>
                                <p class="mt-2 break-all text-xs text-muted-foreground">{{ webhook.transaction_code || 'No transaction code' }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ webhook.event_type || 'No event type' }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ webhook.processed_at || 'Chua xu ly' }}</p>
                            </div>
                            <p v-if="!webhooks.length" class="py-6 text-center text-sm text-muted-foreground">Chua co webhook nao.</p>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <ShieldAlert class="size-4 text-rose-600" /> Điều chỉnh billing thủ công
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <!-- Nút điền nhanh Presets -->
                            <div class="mb-4 flex flex-wrap gap-2 border-b pb-4">
                                <span class="text-xs text-muted-foreground w-full mb-1">Điền nhanh thiết lập:</span>
                                <Button
                                    type="button" variant="outline"
                                    class="text-xs py-1 px-2.5 h-auto text-blue-600 border-blue-200 hover:bg-blue-50 dark:text-blue-400 dark:border-blue-900/40 dark:hover:bg-blue-950/20"
                                    @click="applyPreset('trial', 14, 0, 'Tặng dùng thử 14 ngày')"
                                >
                                    +14 ngày Dùng thử
                                </Button>
                                <Button
                                    type="button" variant="outline"
                                    class="text-xs py-1 px-2.5 h-auto text-purple-600 border-purple-200 hover:bg-purple-50 dark:text-purple-400 dark:border-purple-900/40 dark:hover:bg-purple-950/20"
                                    @click="applyPreset('extend', 30, 0, 'Gia hạn dịch vụ 30 ngày')"
                                >
                                    +30 ngày Gia hạn
                                </Button>
                                <Button
                                    type="button" variant="outline"
                                    class="text-xs py-1 px-2.5 h-auto text-emerald-600 border-emerald-200 hover:bg-emerald-50 dark:text-emerald-400 dark:border-emerald-900/40 dark:hover:bg-emerald-950/20"
                                    @click="applyPreset('extend', 365, 0, 'Khuyến mãi đặc biệt 365 ngày')"
                                >
                                    +1 năm Free
                                </Button>
                            </div>

                            <form class="grid gap-4" @submit.prevent="submitOverride">
                                <div class="grid gap-1.5">
                                    <Label>Loai thao tac</Label>
                                    <select v-model="overrideForm.type" class="h-9 rounded-md border bg-background px-3 text-sm">
                                        <option value="extend">Gia han thu cong</option>
                                        <option value="trial">Tang them trial</option>
                                        <option value="discount">Ap ma giam gia dac biet</option>
                                    </select>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-1.5">
                                        <Label>So ngay cong them</Label>
                                        <Input v-model="overrideForm.days" type="number" min="0" max="365" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label>So tien giam</Label>
                                        <Input v-model="overrideForm.discount_amount" type="number" min="0" />
                                    </div>
                                </div>

                                <div class="grid gap-1.5">
                                    <Label>Ma coupon</Label>
                                    <Input v-model="overrideForm.coupon_code" placeholder="PARTNER-VIP-2026" />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label>Ly do</Label>
                                    <Input v-model="overrideForm.reason" placeholder="Ho tro doi tac chien luoc / Free trial / Su co doi soat" />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label class="text-rose-600 dark:text-rose-400 font-semibold">Xác nhận mật khẩu của bạn</Label>
                                    <Input id="override-password" v-model="overrideForm.password" type="password" placeholder="Nhập mật khẩu Super Admin để xác nhận" required />
                                    <span v-if="overrideForm.errors.password" class="text-xs text-rose-600 font-medium">{{ overrideForm.errors.password }}</span>
                                </div>

                                <Button type="submit" :disabled="overrideForm.processing" class="justify-center">
                                    {{ overrideForm.processing ? 'Dang ap dung...' : 'Ap dung manual override' }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center justify-between gap-2 text-base">
                                <span class="flex items-center gap-2"><WalletCards class="size-4 text-emerald-600" /> Lich su dieu chinh</span>
                                <a :href="`/super-admin/billing?restaurant_id=${restaurant.id}`" class="flex items-center gap-1 text-xs font-normal text-emerald-600 hover:underline dark:text-emerald-400">
                                    Xem tất cả <ExternalLink class="size-3" />
                                </a>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-for="adjustment in adjustments" :key="adjustment.id" class="rounded-xl border border-border/70 p-4 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-medium">{{ typeLabel(adjustment.type) }}</span>
                                    <span class="text-xs text-muted-foreground">{{ adjustment.created_at }}</span>
                                </div>
                                <p class="mt-2 text-muted-foreground">{{ adjustment.reason || 'Khong co ghi chu' }}</p>
                                <div class="mt-3 flex flex-wrap gap-3 text-xs text-muted-foreground">
                                    <span>+{{ adjustment.days }} ngay</span>
                                    <span>Giam {{ adjustment.discount_amount }} VND</span>
                                    <span>{{ adjustment.creator || 'System' }}</span>
                                </div>
                            </div>
                            <p v-if="!adjustments.length" class="py-6 text-center text-sm text-muted-foreground">Chua co dieu chinh billing nao.</p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div class="flex flex-col gap-6">
                <!-- Automated Validator Card -->
                <Card :class="{'border-rose-500/30': restaurant.is_inactive_flagged}">
                    <CardHeader class="pb-3 flex-row items-center justify-between space-y-0 gap-2">
                        <CardTitle class="text-base flex items-center gap-2">
                            <Activity class="size-4.5 text-rose-500 shrink-0" /> Trình Kiểm định
                        </CardTitle>
                        <span v-if="restaurant.is_inactive_flagged" class="rounded-full bg-rose-100 dark:bg-rose-950/40 px-2 py-0.5 text-[10px] font-bold text-rose-800 dark:text-rose-300 border border-rose-200/50">
                            🚩 Bị gắn cờ
                        </span>
                        <span v-else class="rounded-full bg-emerald-100 dark:bg-emerald-950/40 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:text-emerald-300 border border-emerald-200/50">
                            Bình thường
                        </span>
                    </CardHeader>
                    <CardContent class="space-y-3 text-xs">
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-muted-foreground">Hoạt động cuối:</span>
                            <span class="font-semibold">{{ restaurant.last_active_at }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-muted-foreground">Trạng thái gắn cờ:</span>
                            <span class="font-semibold">{{ restaurant.is_inactive_flagged ? 'Cần hậu mãi' : 'Không' }}</span>
                        </div>
                        <div v-if="restaurant.is_inactive_flagged" class="flex justify-between border-b pb-2">
                            <span class="text-muted-foreground">Gắn cờ lúc:</span>
                            <span class="font-semibold">{{ restaurant.inactive_flagged_at }}</span>
                        </div>

                        <div class="pt-2 space-y-1.5">
                            <p class="text-[10px] font-black text-muted-foreground uppercase tracking-wider">Chỉ số hôm nay:</p>
                            <div class="flex justify-between">
                                <span>Đơn hàng mới:</span>
                                <span class="font-bold font-mono">{{ restaurant.today_activity?.orders_count ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Món ăn ra bếp:</span>
                                <span class="font-bold font-mono">{{ restaurant.today_activity?.dishes_prepared_count ?? 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Doanh thu ngày:</span>
                                <span class="font-bold text-emerald-600 font-mono">{{ formatCurrency(restaurant.today_activity?.revenue ?? 0) }}</span>
                            </div>
                        </div>

                        <div v-if="restaurant.is_inactive_flagged" class="pt-2">
                            <Button @click="unflagRestaurant" :disabled="unflagForm.processing" variant="outline" class="w-full text-xs gap-1.5 border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-400 dark:hover:bg-rose-950/20">
                                <AlertTriangle class="size-3.5" /> Đặt lại hoạt động &amp; Gỡ cờ
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Quan tri trang thai</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="updateStatus" class="flex flex-col gap-3">
                            <select v-model="statusForm.status" class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                                <option value="active">Kich hoat</option>
                                <option value="expired">Read-only / Het han</option>
                                <option value="suspended">Khoa hoan toan</option>
                            </select>
                            <Input v-model="statusForm.reason" placeholder="Ly do cap nhat trang thai" />
                            <Button type="submit" :disabled="statusForm.processing" size="sm" class="w-full">
                                {{ statusForm.processing ? 'Dang luu...' : 'Cap nhat trang thai' }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Chuyen goi dich vu</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="updatePlan" class="flex flex-col gap-3">
                            <select v-model="planForm.plan_id" class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                                <option v-for="p in plans" :key="p.id" :value="String(p.id)">
                                    {{ p.name }}
                                </option>
                            </select>
                            <Button type="submit" :disabled="planForm.processing" size="sm" variant="outline" class="w-full">
                                {{ planForm.processing ? 'Dang luu...' : 'Cap nhat goi' }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Hạn ngạch lưu trữ tùy chỉnh</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="updateStorageQuota" class="flex flex-col gap-3">
                            <div>
                                <Label for="custom_storage" class="text-xs text-muted-foreground">Giới hạn dung lượng (MB)</Label>
                                <Input id="custom_storage" type="number" v-model="storageForm.custom_storage_limit_mb" placeholder="Để trống để dùng mặc định" class="mt-1 border-border bg-background text-foreground rounded-xl" />
                            </div>
                            <Button type="submit" :disabled="storageForm.processing" size="sm" variant="outline" class="w-full">
                                {{ storageForm.processing ? 'Đang lưu...' : 'Cập nhật dung lượng' }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center justify-between gap-2 text-base">
                            <span>Lich su subscription</span>
                            <button
                                class="flex items-center gap-1 text-xs font-normal text-amber-600 hover:underline dark:text-amber-400"
                                @click="openSubsDialog"
                            >
                                Xem tất cả <ExternalLink class="size-3" />
                            </button>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div v-for="s in subscriptions" :key="s.id" class="rounded-xl border border-border/70 p-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium">{{ s.plan }}</span>
                                <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', statusColor[s.status] || 'bg-slate-100 text-slate-800']">
                                    {{ statusLabel[s.status] ?? s.status }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">{{ s.started_at }} → {{ s.ended_at }}</p>
                            <p class="mt-1 font-mono text-xs">{{ s.price }} VND</p>
                        </div>
                        <p v-if="!subscriptions.length" class="py-6 text-center text-sm text-muted-foreground">Chua co lich su subscription.</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <!-- Subscriptions History Dialog -->
    <Dialog :open="showSubsDialog" @update:open="(v) => { if (!v) showSubsDialog = false; }">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Lịch sử Subscription · {{ restaurant.name }}</DialogTitle>
            </DialogHeader>

            <div v-if="subsLoading" class="py-8 text-center text-sm text-muted-foreground">Đang tải...</div>

            <div v-else class="space-y-2 py-2">
                <p class="text-xs text-muted-foreground">Tổng {{ subsTotal }} bản ghi</p>
                <div v-for="s in subsHistory" :key="s.id" class="rounded-xl border border-border/70 p-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">{{ s.plan }}</span>
                        <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', statusColor[s.status] || 'bg-slate-100 text-slate-800']">
                            {{ statusLabel[s.status] ?? s.status }}
                        </span>
                    </div>
                    <p class="mt-1.5 text-xs text-muted-foreground">{{ s.started_at }} → {{ s.ended_at }}</p>
                    <p class="mt-0.5 font-mono text-xs">{{ s.price }} VND</p>
                </div>
                <p v-if="!subsHistory.length" class="py-6 text-center text-sm text-muted-foreground">Không có dữ liệu.</p>

                <!-- Pagination -->
                <div v-if="subsLastPage > 1" class="flex items-center justify-center gap-2 pt-2">
                    <button
                        :disabled="subsPage <= 1"
                        class="rounded px-3 py-1 text-xs hover:bg-muted disabled:opacity-40"
                        @click="loadSubsHistory(subsPage - 1)"
                    >
                        ← Trước
                    </button>
                    <span class="text-xs text-muted-foreground">{{ subsPage }} / {{ subsLastPage }}</span>
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
