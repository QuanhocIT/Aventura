<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Building2,
    Grid,
    Sparkles,
    Users2,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SubscriptionUpgradeModal from '@/components/SubscriptionUpgradeModal.vue';

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
const availablePlans = computed(
    () => (page.props.available_plans as AvailablePlan[]) ?? [],
);
const roles = computed(() => (page.props as any).roles ?? []);
const canManageBilling = computed(
    () =>
        tenant.value != null &&
        (roles.value.includes('owner') ||
            roles.value.includes('admin') ||
            roles.value.includes('super_admin')),
);

const currentPlanCode = computed(
    () => tenant.value?.plan?.code?.toLowerCase() ?? 'free',
);

// Plan rank = index in price-ordered list (0 = cheapest)
const planRank = computed(() => {
    if (!currentPlanCode.value) {
        return 0;
    }

    const idx = availablePlans.value.findIndex(
        (p) => p.code === currentPlanCode.value,
    );

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

    return new Date(d).toLocaleDateString('vi-VN', {
        day: 'numeric',
        month: 'numeric',
        year: 'numeric',
    });
});

const resources = computed(() => {
    const s = tenant.value?.quota_summary?.resources ?? {};

    return [
        {
            key: 'branches',
            label: 'Chi nhánh',
            icon: Building2,
            used: s.branches?.used ?? 0,
            limit: s.branches?.limit,
            unlimited: s.branches?.unlimited ?? false,
            pct: s.branches?.percentage ?? 0,
        },
        {
            key: 'tables',
            label: 'Bàn tối đa',
            icon: Grid,
            used: s.tables?.used ?? 0,
            limit: s.tables?.limit,
            unlimited: s.tables?.unlimited ?? false,
            pct: s.tables?.percentage ?? 0,
        },
        {
            key: 'employees',
            label: 'Nhân viên',
            icon: Users2,
            used: s.employees?.used ?? 0,
            limit: s.employees?.limit,
            unlimited: s.employees?.unlimited ?? false,
            pct: s.employees?.percentage ?? 0,
        },
    ];
});

// Plans that are strictly higher than current plan → can upgrade to (only if user can manage billing)
const upgradablePlans = computed(() =>
    canManageBilling.value
        ? availablePlans.value.filter((_, i) => i > planRank.value)
        : [],
);

// Selected plan to upgrade to (defaults to next plan above current)
const selectedPlanCode = ref<string | null>(null);

const selectedPlan = computed(() => {
    if (selectedPlanCode.value) {
        return (
            upgradablePlans.value.find(
                (p) => p.code === selectedPlanCode.value,
            ) ??
            upgradablePlans.value[0] ??
            null
        );
    }

    return upgradablePlans.value[0] ?? null;
});

function goToCheckout(code: string) {
    window.location.href = `/billing/checkout?plan=${code}&cycle=monthly`;
}
</script>

<template>
    <div v-if="tenant" class="px-3 py-2">
        <!-- ── Compact widget ─────────────────────────────────── -->
        <div
            class="relative overflow-hidden rounded-xl border border-border/60 bg-gradient-to-b from-card to-background/50 p-4 shadow-sm"
        >
            <div
                v-if="!isFree"
                class="pointer-events-none absolute -top-8 -right-8 h-20 w-20 rounded-full bg-primary/10 blur-2xl"
            />

            <!-- Plan name + status dot -->
            <div class="mb-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2 w-2">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                            :class="isFree ? 'bg-amber-400' : 'bg-emerald-400'"
                        />
                        <span
                            class="relative inline-flex h-2 w-2 rounded-full"
                            :class="isFree ? 'bg-amber-500' : 'bg-emerald-500'"
                        />
                    </span>
                    <span
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Gói {{ tenant?.plan?.name ?? 'Free' }}
                    </span>
                </div>
                <span
                    v-if="!isFree"
                    class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary"
                >
                    <Sparkles class="size-3" />
                    {{ currentPlanCode.toUpperCase() }}
                </span>
            </div>

            <!-- Trial warning -->
            <div
                v-if="isTrial && daysRemaining !== null"
                class="mb-3 rounded-lg border border-amber-500/20 bg-amber-500/10 p-2 text-xs text-amber-600 dark:text-amber-400"
            >
                <p class="flex items-center gap-1.5 font-medium">
                    <Sparkles class="size-3 animate-pulse" />
                    Đang dùng thử · còn {{ daysRemaining }} ngày
                </p>
            </div>

            <!-- Expiry -->
            <p
                v-if="expiryDate && !isTrial"
                class="mb-3 text-[10px] text-muted-foreground"
            >
                Hết hạn: {{ expiryDate }}
            </p>

            <!-- Resource stats (flat, no progress bar to save space) -->
            <div class="space-y-1.5">
                <div
                    v-for="res in resources"
                    :key="res.key"
                    class="flex items-center justify-between text-xs"
                >
                    <span class="text-muted-foreground">{{ res.label }}</span>
                    <span
                        class="font-semibold"
                        :class="
                            res.pct >= 90
                                ? 'text-rose-500'
                                : res.pct >= 70
                                  ? 'text-amber-500'
                                  : 'text-foreground'
                        "
                    >
                        <template v-if="res.unlimited">∞</template>
                        <template v-else>{{ res.limit }}</template>
                    </span>
                </div>
                <!-- AI & Realtime indicator -->
                <div class="flex items-center justify-between text-xs">
                    <span class="text-muted-foreground">AI & Realtime</span>
                    <span
                        class="font-semibold"
                        :class="
                            tenant?.plan?.features?.ai_advisor ||
                            tenant?.plan?.features?.realtime
                                ? 'text-emerald-500'
                                : 'text-muted-foreground'
                        "
                    >
                        {{
                            tenant?.plan?.features?.ai_advisor ||
                            tenant?.plan?.features?.realtime
                                ? 'Có'
                                : 'Không'
                        }}
                    </span>
                </div>
            </div>

            <!-- CTA -->
            <div class="mt-4 space-y-2 border-t border-border/40 pt-3">
                <!-- Plan chips — only upgradable plans -->
                <div
                    v-if="upgradablePlans.length"
                    class="flex flex-col gap-1.5"
                >
                    <p
                        class="mb-0.5 text-[10px] font-medium tracking-wider text-muted-foreground uppercase"
                    >
                        Chọn gói nâng cấp
                    </p>
                    <button
                        v-for="p in upgradablePlans"
                        :key="p.code"
                        @click="selectedPlanCode = p.code"
                        class="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-xs font-semibold transition-all duration-150"
                        :class="
                            selectedPlan?.code === p.code
                                ? 'border-violet-500/70 bg-violet-500/10 text-violet-700 dark:text-violet-300'
                                : 'border-border bg-muted/30 text-muted-foreground hover:border-border/80 hover:bg-muted/60 hover:text-foreground'
                        "
                    >
                        <span class="flex items-center gap-1.5">
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="
                                    selectedPlan?.code === p.code
                                        ? 'bg-violet-500'
                                        : 'bg-border'
                                "
                            />
                            {{ p.name }}
                        </span>
                        <span class="font-mono text-[10px]"
                            >{{ p.price.toLocaleString('vi-VN') }}đ</span
                        >
                    </button>
                </div>

                <!-- Upgrade button for selected plan -->
                <button
                    v-if="selectedPlan"
                    @click="goToCheckout(selectedPlan.code)"
                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-gradient-to-r from-violet-600 to-indigo-600 px-3 py-2 text-xs font-semibold text-white shadow-sm shadow-violet-500/15 transition-all hover:from-violet-500 hover:to-indigo-500"
                >
                    <Zap class="size-3.5" />
                    Nâng lên {{ selectedPlan.name }}
                    <ArrowUpRight class="ml-auto size-3.5" />
                </button>

                <!-- Already on top plan -->
                <div
                    v-if="!upgradablePlans.length"
                    class="py-1 text-center text-xs text-muted-foreground"
                >
                    <Sparkles class="mr-1 inline size-3 text-violet-500" />
                    Bạn đang dùng gói cao nhất
                </div>

                <p
                    v-if="upgradablePlans.length"
                    class="text-center text-[10px] text-muted-foreground"
                >
                    Không mất dữ liệu khi nâng gói
                </p>
            </div>
        </div>

        <SubscriptionUpgradeModal />
    </div>
</template>
