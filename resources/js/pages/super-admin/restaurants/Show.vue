<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Crown,
    CheckCircle2,
    XCircle,
    Building2,
    Users,
    LayoutGrid,
    Table2,
    ReceiptText,
    ShieldAlert,
    WalletCards,
    RefreshCcw,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout-clean.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    restaurant: {
        id: number; name: string; code: string; slug: string;
        tax_code: string; phone: string; email: string; address: string;
        status: string; timezone: string; currency: string;
        trial_ends_at: string; subscription_ends_at: string; created_at: string;
        owner: { name: string; email: string };
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

const overrideForm = useForm({
    type: 'extend',
    days: 30,
    discount_amount: 0,
    reason: '',
    coupon_code: '',
});
function submitOverride() {
    overrideForm.post(`/super-admin/restaurants/${props.restaurant.id}/billing-overrides`, {
        preserveScroll: true,
    });
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
    branches: Building2, employees: Users, areas: LayoutGrid, tables: Table2,
};
const resourceLabels: Record<string, string> = {
    branches: 'Chi nhanh', employees: 'Nhan vien', areas: 'Khu vuc', tables: 'Ban an',
};

function barColor(pct: number, canAdd: boolean) {
    if (!canAdd) return 'bg-rose-500';
    if (pct >= 80) return 'bg-amber-500';
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
                <p class="text-sm text-muted-foreground font-mono">{{ restaurant.code }} � Billing Center</p>
            </div>
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
                        <div><p class="text-muted-foreground">Chu so huu</p><p class="font-medium">{{ restaurant.owner.name || '�' }}</p></div>
                        <div><p class="text-muted-foreground">Email chu</p><p class="font-medium">{{ restaurant.owner.email || '�' }}</p></div>
                        <div><p class="text-muted-foreground">Goi hien tai</p><p class="font-medium">{{ restaurant.plan.name || '�' }}</p></div>
                        <div><p class="text-muted-foreground">Het han dich vu</p><p class="font-medium">{{ restaurant.subscription_ends_at || '�' }}</p></div>
                        <div><p class="text-muted-foreground">Het han trial</p><p class="font-medium">{{ restaurant.trial_ends_at || '�' }}</p></div>
                        <div><p class="text-muted-foreground">Tien te</p><p class="font-medium">{{ restaurant.currency }}</p></div>
                        <div class="md:col-span-2"><p class="text-muted-foreground">Dia chi</p><p class="font-medium">{{ restaurant.address || '�' }}</p></div>
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
                                    {{ res.used }} / {{ res.unlimited ? '8' : res.limit }}
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted">
                                <div v-if="!res.unlimited" :class="['h-full rounded-full transition-all', barColor(res.percentage, res.can_add)]" :style="{ width: `${res.percentage}%` }" />
                                <div v-else class="h-full w-full rounded-full bg-emerald-500/30" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="grid gap-6 xl:grid-cols-3">
                    <Card class="xl:col-span-2">
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <ReceiptText class="size-4 text-sky-600" /> Hoa don gan day
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-for="invoice in invoices" :key="invoice.id" class="rounded-xl border border-border/70 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium">{{ invoice.invoice_number }}</p>
                                        <p class="text-xs text-muted-foreground">{{ typeLabel(invoice.type) }} � Han {{ invoice.due_on || '�' }}</p>
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
                            <CardTitle class="flex items-center gap-2 text-base">
                                <RefreshCcw class="size-4 text-violet-600" /> Webhook log
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
                                <ShieldAlert class="size-4 text-rose-600" /> Dieu chinh billing thu cong
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
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

                                <Button type="submit" :disabled="overrideForm.processing" class="justify-center">
                                    {{ overrideForm.processing ? 'Dang ap dung...' : 'Ap dung manual override' }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <WalletCards class="size-4 text-emerald-600" /> Lich su dieu chinh
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
                        <CardTitle class="text-base">Lich su subscription</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div v-for="s in subscriptions" :key="s.id" class="rounded-xl border border-border/70 p-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium">{{ s.plan }}</span>
                                <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', statusColor[s.status] || 'bg-slate-100 text-slate-800']">
                                    {{ statusLabel[s.status] ?? s.status }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">{{ s.started_at }} ? {{ s.ended_at }}</p>
                            <p class="mt-1 font-mono text-xs">{{ s.price }} VND</p>
                        </div>
                        <p v-if="!subscriptions.length" class="py-6 text-center text-sm text-muted-foreground">Chua co lich su subscription.</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
