<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight, Building2, Check,
    Grid, Lock, Sparkles, Users2, X, Zap,
} from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';

interface AvailablePlan {
    id: number;
    code: string;
    name: string;
    price: number;
    billing_cycle: string;
    max_branches: number | null;
    max_tables: number | null;
    max_users: number | null;
    features: Record<string, unknown>;
}

const page = usePage();
const tenant = computed(() => page.props.tenant as any);
const availablePlans = computed(() => (page.props.available_plans as AvailablePlan[]) ?? []);
const roles = computed(() => (page.props as any).roles ?? []);
const canManageBilling = computed(() =>
    tenant.value != null &&
    (roles.value.includes('owner') ||
     roles.value.includes('admin') || roles.value.includes('super_admin'))
);

const isUpgradeModalOpen = ref(false);

const currentPlanCode = computed(() => tenant.value?.plan?.code?.toLowerCase() ?? 'free');

// Plan rank = index in price-ordered list (0 = cheapest)
const planRank = computed(() => {
    if (!currentPlanCode.value) {
return 0;
}

    const idx = availablePlans.value.findIndex(p => p.code === currentPlanCode.value);

    return idx === -1 ? 0 : idx;
});

const isFree = computed(() => currentPlanCode.value === 'free');
const isTrial = computed(() => tenant.value?.status === 'trial');

const daysRemaining = computed(() => {
    const d = tenant.value?.subscription_ends_at || tenant.value?.trial_ends_at;

    if (!d) {
return null;
}

    const diff = new Date(d).getTime() - new Date().setHours(0, 0, 0, 0);
    const days = Math.ceil(diff / 86400000);

    return days > 0 ? days : 0;
});

// Formatted expiry date
const expiryDate = computed(() => {
    const d = tenant.value?.subscription_ends_at || tenant.value?.trial_ends_at;

    if (!d) {
return null;
}

    return new Date(d).toLocaleDateString('vi-VN', { day: 'numeric', month: 'numeric', year: 'numeric' });
});

const resources = computed(() => {
    const s = tenant.value?.quota_summary?.resources ?? {};

    return [
        { key: 'branches',  label: 'Chi nhánh',  icon: Building2, used: s.branches?.used  ?? 0, limit: s.branches?.limit,  unlimited: s.branches?.unlimited  ?? false, pct: s.branches?.percentage  ?? 0 },
        { key: 'tables',    label: 'Bàn tối đa', icon: Grid,      used: s.tables?.used    ?? 0, limit: s.tables?.limit,    unlimited: s.tables?.unlimited    ?? false, pct: s.tables?.percentage    ?? 0 },
        { key: 'employees', label: 'Nhân viên',  icon: Users2,    used: s.employees?.used ?? 0, limit: s.employees?.limit, unlimited: s.employees?.unlimited ?? false, pct: s.employees?.percentage ?? 0 },
    ];
});

function getProgressColor(pct: number) {
    if (pct >= 90) {
return 'bg-rose-500';
}

    if (pct >= 70) {
return 'bg-amber-500';
}

    return 'bg-emerald-500';
}

// Plans that are strictly higher than current plan → can upgrade to (only if user can manage billing)
const upgradablePlans = computed(() =>
    canManageBilling.value
        ? availablePlans.value.filter((_, i) => i > planRank.value)
        : []
);

// Selected plan to upgrade to (defaults to next plan above current)
const selectedPlanCode = ref<string | null>(null);

const selectedPlan = computed(() => {
    if (selectedPlanCode.value) {
        return upgradablePlans.value.find(p => p.code === selectedPlanCode.value) ?? upgradablePlans.value[0] ?? null;
    }

    return upgradablePlans.value[0] ?? null;
});

// Next recommended plan (first plan above current)
const nextPlan = computed(() => upgradablePlans.value[0] ?? null);

function formatPrice(price: number) {
    if (price === 0) {
return 'Miễn phí';
}

    return price.toLocaleString('vi-VN') + 'đ/tháng';
}

function getPlanFeatures(plan: AvailablePlan): string[] {
    const lim = (v: number | null, unit: string) =>
        v === null ? `Không giới hạn ${unit}` : `${v} ${unit}`;
    const mb = (plan.features?.max_storage_mb as number) ?? 500;
    const rate = (plan.features?.api_rate_limit as number) ?? 60;
    const list = [
        lim(plan.max_branches, 'chi nhánh'),
        lim(plan.max_tables, 'bàn'),
        lim(plan.max_users, 'nhân viên'),
        mb >= 1024 ? `${mb / 1024} GB lưu trữ` : `${mb} MB lưu trữ`,
        `Rate limit: ${rate.toLocaleString('vi-VN')}/phút`,
    ];

    if (plan.features?.ai_features) {
list.push('AI dự báo nguyên liệu & tồn kho', 'Thuật toán AI phát hiện gian lận');
}

    if (plan.features?.realtime) {
list.push('Realtime sync & Advanced Analytics');
}

    if (plan.features?.advanced_analytics) {
list.push('Hệ thống Audit Log bảo mật');
}

    return list;
}

function getPlanUnsupported(plan: AvailablePlan): string[] {
    const list: string[] = [];

    if (!plan.features?.ai_features) {
list.push('AI dự báo nguyên liệu & tồn kho', 'Thuật toán AI phát hiện gian lận');
}

    if (!plan.features?.realtime) {
list.push('Realtime sync & Advanced Analytics');
}

    if (!plan.features?.advanced_analytics) {
list.push('Hệ thống Audit Log bảo mật');
}

    return list;
}

const defaultDescriptions: Record<string, string> = {
    free:  'Gói cơ bản trải nghiệm miễn phí.',
    pro:   'Tối ưu hiệu năng, chống thất thoát cho mô hình chuyên nghiệp.',
    max:   'Phù hợp cho chuỗi nhà hàng vừa và lớn.',
    ultra: 'Giải pháp tối thượng cho doanh nghiệp lớn & chuỗi rộng khắp.',
};

function planDescription(plan: AvailablePlan) {
    return (plan.features?.description as string | undefined) || defaultDescriptions[plan.code] || '';
}

function planAccent(code: string) {
    switch (code) {
        case 'pro':   return { border: 'border-emerald-500/60', glow: 'shadow-emerald-500/10', badge: 'bg-emerald-500/10 text-emerald-600', btn: 'bg-emerald-600 hover:bg-emerald-700 text-white', check: 'text-emerald-500' };
        case 'max':   return { border: 'border-sky-500/60',     glow: 'shadow-sky-500/10',     badge: 'bg-sky-500/10 text-sky-600',         btn: 'bg-sky-600 hover:bg-sky-700 text-white',         check: 'text-sky-500' };
        case 'ultra': return { border: 'border-violet-500/60',  glow: 'shadow-violet-500/10',  badge: 'bg-violet-500/10 text-violet-600',   btn: 'bg-violet-600 hover:bg-violet-700 text-white',   check: 'text-violet-500' };
        default:      return { border: 'border-border',         glow: '',                       badge: 'bg-muted text-muted-foreground',     btn: 'bg-muted hover:bg-muted/80 text-foreground',     check: 'text-muted-foreground' };
    }
}

const isYearly = ref(false);

const maxDiscountPercent = computed(() => {
    if (!availablePlans.value.length) return 20;
    const percentages = availablePlans.value.map(p => 
        p.features?.yearly_discount_percent !== undefined ? Number(p.features.yearly_discount_percent) : 20
    );
    return Math.max(...percentages);
});

function getDisplayPrice(plan: AvailablePlan) {
    if (plan.price === 0) return 'Miễn phí';
    if (isYearly.value) {
        const discountPercent = plan.features?.yearly_discount_percent !== undefined
            ? Number(plan.features.yearly_discount_percent)
            : 20;
        const yearlyPrice = Math.round(plan.price * 12 * (1 - discountPercent / 100));
        return yearlyPrice.toLocaleString('vi-VN') + 'đ/năm';
    }
    return plan.price.toLocaleString('vi-VN') + 'đ/tháng';
}

function goToCheckout(code: string) {
    window.location.href = `/billing/checkout?plan=${code}` + (isYearly.value ? '&cycle=yearly' : '&cycle=monthly');
}

function handleOpenModal() {
    isUpgradeModalOpen.value = true;
}

onMounted(() => {
    window.addEventListener('open-upgrade-modal', handleOpenModal);
});

onUnmounted(() => {
    window.removeEventListener('open-upgrade-modal', handleOpenModal);
});
</script>

<template>
    <div v-if="tenant" class="px-3 py-2">
        <!-- ── Compact widget ─────────────────────────────────── -->
        <div class="relative overflow-hidden rounded-xl border border-border/60 bg-gradient-to-b from-card to-background/50 p-4 shadow-sm">
            <div v-if="!isFree" class="pointer-events-none absolute -right-8 -top-8 h-20 w-20 rounded-full bg-primary/10 blur-2xl" />

            <!-- Plan name + status dot -->
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" :class="isFree ? 'bg-amber-400' : 'bg-emerald-400'" />
                        <span class="relative inline-flex h-2 w-2 rounded-full" :class="isFree ? 'bg-amber-500' : 'bg-emerald-500'" />
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Gói {{ tenant?.plan?.name ?? 'Free' }}
                    </span>
                </div>
                <span v-if="!isFree" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                    <Sparkles class="size-3" /> {{ currentPlanCode.toUpperCase() }}
                </span>
            </div>

            <!-- Trial warning -->
            <div v-if="isTrial && daysRemaining !== null" class="mb-3 rounded-lg bg-amber-500/10 border border-amber-500/20 p-2 text-xs text-amber-600 dark:text-amber-400">
                <p class="font-medium flex items-center gap-1.5">
                    <Sparkles class="size-3 animate-pulse" />
                    Đang dùng thử · còn {{ daysRemaining }} ngày
                </p>
            </div>

            <!-- Expiry -->
            <p v-if="expiryDate && !isTrial" class="mb-3 text-[10px] text-muted-foreground">
                Hết hạn: {{ expiryDate }}
            </p>

            <!-- Resource stats (flat, no progress bar to save space) -->
            <div class="space-y-1.5">
                <div v-for="res in resources" :key="res.key" class="flex items-center justify-between text-xs">
                    <span class="text-muted-foreground">{{ res.label }}</span>
                    <span class="font-semibold" :class="res.pct >= 90 ? 'text-rose-500' : res.pct >= 70 ? 'text-amber-500' : 'text-foreground'">
                        <template v-if="res.unlimited">∞</template>
                        <template v-else>{{ res.limit }}</template>
                    </span>
                </div>
                <!-- AI & Realtime indicator -->
                <div class="flex items-center justify-between text-xs">
                    <span class="text-muted-foreground">AI & Realtime</span>
                    <span class="font-semibold" :class="tenant?.plan?.features?.ai_features ? 'text-emerald-500' : 'text-muted-foreground'">
                        {{ tenant?.plan?.features?.ai_features ? 'Có' : 'Không' }}
                    </span>
                </div>
            </div>

            <!-- CTA -->
            <div class="mt-4 pt-3 border-t border-border/40 space-y-2">
                <!-- Plan chips — only upgradable plans -->
                <div v-if="upgradablePlans.length" class="flex flex-col gap-1.5">
                    <p class="text-[10px] text-muted-foreground font-medium uppercase tracking-wider mb-0.5">Chọn gói nâng cấp</p>
                    <button
                        v-for="p in upgradablePlans"
                        :key="p.code"
                        @click="selectedPlanCode = p.code"
                        class="w-full flex items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold border transition-all duration-150"
                        :class="(selectedPlan?.code === p.code)
                            ? 'border-violet-500/70 bg-violet-500/10 text-violet-700 dark:text-violet-300'
                            : 'border-border bg-muted/30 text-muted-foreground hover:border-border/80 hover:text-foreground hover:bg-muted/60'"
                    >
                        <span class="flex items-center gap-1.5">
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="selectedPlan?.code === p.code ? 'bg-violet-500' : 'bg-border'"
                            />
                            {{ p.name }}
                        </span>
                        <span class="font-mono text-[10px]">{{ p.price.toLocaleString('vi-VN') }}đ</span>
                    </button>
                </div>

                <!-- Upgrade button for selected plan -->
                <button
                    v-if="selectedPlan"
                    @click="goToCheckout(selectedPlan.code)"
                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 transition-all shadow-sm shadow-violet-500/15"
                >
                    <Zap class="size-3.5" />
                    Nâng lên {{ selectedPlan.name }}
                    <ArrowUpRight class="size-3.5 ml-auto" />
                </button>

                <!-- Already on top plan -->
                <div v-if="!upgradablePlans.length" class="text-center text-xs text-muted-foreground py-1">
                    <Sparkles class="size-3 inline mr-1 text-violet-500" />
                    Bạn đang dùng gói cao nhất
                </div>

                <p v-if="upgradablePlans.length" class="text-center text-[10px] text-muted-foreground">
                    Không mất dữ liệu khi nâng gói
                </p>
            </div>
        </div>

        <!-- ── Plan selection modal ───────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="isUpgradeModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                @click.self="isUpgradeModalOpen = false"
            >
                <div class="relative w-full max-w-5xl max-h-[90vh] overflow-y-auto rounded-2xl border border-border bg-card shadow-2xl">

                    <!-- Close -->
                    <button
                        @click="isUpgradeModalOpen = false"
                        class="absolute top-4 right-4 z-10 rounded-full p-1.5 text-muted-foreground hover:bg-muted transition-colors"
                    >
                        <X class="size-5" />
                    </button>

                    <!-- Header -->
                    <div class="px-6 pt-8 pb-5 text-center border-b border-border">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                            <Sparkles class="size-6 text-primary animate-pulse" />
                        </div>
                        <h3 class="text-xl font-bold tracking-tight">Chọn gói nâng cấp</h3>
                        <p class="mt-1.5 text-sm text-muted-foreground">
                            Đang dùng <strong>{{ tenant?.plan?.name ?? 'Free' }}</strong> · Chỉ có thể nâng lên gói cao hơn
                        </p>

                        <!-- Toggle cycle -->
                        <div class="mt-4 flex items-center justify-center gap-3">
                            <span class="text-xs" :class="!isYearly ? 'font-bold text-foreground' : 'text-muted-foreground'">Thanh toán Hàng tháng</span>
                            <button 
                                @click="isYearly = !isYearly"
                                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 bg-muted"
                                :class="isYearly ? 'bg-primary' : 'bg-input'"
                            >
                                <span 
                                    class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-background shadow ring-0 transition duration-200 ease-in-out"
                                    :class="isYearly ? 'translate-x-4' : 'translate-x-0'"
                                />
                            </button>
                            <span class="text-xs flex items-center gap-1.5" :class="isYearly ? 'font-bold text-foreground' : 'text-muted-foreground'">
                                Thanh toán Hàng năm
                                <span class="inline-flex rounded-full bg-emerald-500/15 border border-emerald-500/30 px-1.5 py-0.5 text-[10px] font-bold text-emerald-600">
                                    Tiết kiệm tới {{ maxDiscountPercent }}%
                                </span>
                            </span>
                        </div>
                    </div>

                    <!-- Plan grid -->
                    <div class="px-6 py-6 grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="(plan, idx) in availablePlans"
                            :key="plan.code"
                            class="relative flex flex-col rounded-xl border p-4 transition-all duration-200"
                            :class="[
                                idx === planRank
                                    ? 'border-2 border-emerald-500/60 bg-emerald-500/5'
                                    : idx < planRank
                                        ? 'border-border bg-muted/20 opacity-50 cursor-not-allowed'
                                        : ['border-2', planAccent(plan.code).border, 'bg-card hover:shadow-md', planAccent(plan.code).glow, 'cursor-pointer'],
                            ]"
                        >
                            <!-- Badge -->
                            <div class="absolute top-3 right-3">
                                <span v-if="idx === planRank" class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[9px] font-bold text-emerald-600 uppercase">
                                    Hiện tại
                                </span>
                                <span v-else-if="idx === planRank + 1 && idx < availablePlans.length" class="rounded-full bg-primary/10 px-2 py-0.5 text-[9px] font-bold text-primary uppercase">
                                    Đề xuất
                                </span>
                            </div>

                            <!-- Plan info -->
                            <div class="flex-1">
                                <h4 class="text-sm font-bold pr-16">{{ plan.name }}</h4>
                                <p class="mt-0.5 text-[11px] text-muted-foreground leading-snug min-h-[30px]">
                                    {{ planDescription(plan) }}
                                </p>

                                <div class="mt-3 flex items-baseline gap-1.5 flex-wrap">
                                    <span class="text-xl font-extrabold" :class="idx < planRank ? 'text-muted-foreground' : 'text-foreground'">
                                        {{ getDisplayPrice(plan) }}
                                    </span>
                                    <span 
                                        v-if="isYearly && plan.price > 0 && (plan.features?.yearly_discount_percent !== undefined ? Number(plan.features.yearly_discount_percent) : 20) > 0"
                                        class="inline-flex rounded-full bg-emerald-500/15 border border-emerald-500/30 px-1.5 py-0.5 text-[9px] font-bold text-emerald-600 self-center"
                                    >
                                        Tiết kiệm {{ plan.features?.yearly_discount_percent !== undefined ? plan.features.yearly_discount_percent : 20 }}%
                                    </span>
                                </div>

                                <div class="my-3 h-px bg-border" />

                                <!-- Features list -->
                                <ul class="space-y-1.5 text-[11px]">
                                    <li v-for="f in getPlanFeatures(plan)" :key="f" class="flex items-start gap-1.5">
                                        <Check class="mt-px size-3 shrink-0" :class="idx <= planRank ? 'text-muted-foreground' : planAccent(plan.code).check" />
                                        <span :class="idx < planRank ? 'text-muted-foreground' : 'text-foreground'">{{ f }}</span>
                                    </li>
                                    <li v-for="f in getPlanUnsupported(plan)" :key="f" class="flex items-start gap-1.5 opacity-40">
                                        <X class="mt-px size-3 shrink-0 text-muted-foreground" />
                                        <span class="text-muted-foreground">{{ f }}</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Action button -->
                            <div class="mt-4">
                                <!-- Current plan -->
                                <button v-if="idx === planRank" disabled class="w-full rounded-lg border border-border bg-muted px-3 py-2 text-xs font-semibold text-muted-foreground cursor-not-allowed">
                                    Gói hiện tại
                                </button>
                                <!-- Lower plan — cannot downgrade -->
                                <button v-else-if="idx < planRank" disabled class="w-full rounded-lg border border-border/40 bg-muted/30 px-3 py-2 text-xs font-semibold text-muted-foreground/50 cursor-not-allowed flex items-center justify-center gap-1.5">
                                    <Lock class="size-3" /> Không khả dụng
                                </button>
                                <!-- Higher plan — can upgrade -->
                                <button
                                    v-else
                                    @click="goToCheckout(plan.code)"
                                    class="w-full rounded-lg px-3 py-2 text-xs font-semibold transition-colors flex items-center justify-center gap-1.5"
                                    :class="planAccent(plan.code).btn"
                                >
                                    <Zap class="size-3.5" />
                                    Nâng cấp ngay
                                    <ArrowUpRight class="size-3.5 ml-auto" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-border bg-muted/30 flex flex-col sm:flex-row gap-2 items-center justify-between text-xs text-muted-foreground">
                        <span class="flex items-center gap-1.5">
                            <Lock class="size-3.5" />
                            Giao dịch được mã hóa an toàn · Không mất dữ liệu khi nâng gói
                        </span>
                        <Button variant="outline" size="sm" @click="isUpgradeModalOpen = false">Đóng</Button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
