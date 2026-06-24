<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Building2,
    Zap,
    Crown,
    Star,
    Users,
    BarChart3
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    user: any;
    tenant: any;
    plan: any;
    quota: any;
    availablePlans: any[];
    roles: string[];
}>();

const canManageBilling = computed(() =>
    props.tenant != null && (props.roles.includes('owner') || props.roles.includes('admin') || props.roles.includes('super_admin'))
);

const currentPlanRank = computed(() => {
    if (!props.plan?.code) {
        return 0;
    }

    const idx = props.availablePlans.findIndex((p: any) => p.code === props.plan.code);

    return idx === -1 ? 0 : idx;
});

const nextPlan = computed(() =>
    canManageBilling.value ? (props.availablePlans[currentPlanRank.value + 1] ?? null) : null
);

const planBadgeClass = computed(() => {
    switch (props.plan?.code) {
        case 'pro':   return 'bg-primary text-primary-foreground hover:bg-primary/90';
        case 'max':   return 'bg-sky-600 text-white hover:bg-sky-700';
        case 'ultra': return 'bg-violet-600 text-white hover:bg-violet-700';
        default:      return '';
    }
});

const planIcon = computed(() => {
    switch (props.plan?.code) {
        case 'ultra': return Crown;
        case 'max':   return Zap;
        case 'pro':   return Star;
        default:      return null;
    }
});

// Trial countdown
const isOnTrial = computed(() => props.tenant?.status === 'trial');
const trialDaysLeft = computed(() => {
    if (!props.tenant?.trial_ends_at) {
        return 0;
    }

    const end  = new Date(props.tenant.trial_ends_at);
    const now  = new Date();

    return Math.max(0, Math.ceil((end.getTime() - now.getTime()) / (1000 * 60 * 60 * 24)));
});

const quotaStats = [
    { key: 'branches',  label: 'Chi nhánh', icon: Building2 },
    { key: 'employees', label: 'Nhân viên',  icon: Users },
    { key: 'tables',    label: 'Bàn',        icon: BarChart3 },
];

// Read usage from quota_summary.resources
function resourceData(key: string) {
    return props.quota?.resources?.[key] ?? null;
}

function formatLimit(v: number | null): string {
    return v === null ? '∞' : String(v);
}
</script>

<template>
    <div>
        <!-- ── Trial Countdown Banner ────────────────────────────── -->
        <div v-if="isOnTrial" class="border-b border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 lg:px-8">
            <div class="mx-auto max-w-7xl flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 text-sm">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white text-[10px] font-bold">!</span>
                    <span class="text-amber-800 dark:text-amber-200 font-medium">
                        Tài khoản đang dùng thử —
                        <strong>còn {{ trialDaysLeft }} ngày</strong>.
                        Nâng cấp để không gián đoạn vận hành.
                    </span>
                </div>
                <Link :href="nextPlan ? `/billing/checkout?plan=${nextPlan.code}` : '/billing/checkout'"
                    class="shrink-0 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold px-4 py-1.5 transition-colors">
                    Nâng cấp ngay →
                </Link>
            </div>
        </div>

        <!-- ── Welcome header ───────────────────────────────────── -->
        <section class="border-b border-border bg-muted/30 px-4 py-8 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-muted-foreground">Xin chào trở lại,</p>
                        <h1 class="mt-0.5 text-2xl font-bold tracking-tight">{{ user?.name }}</h1>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span v-if="tenant?.name" class="flex items-center gap-1 text-sm text-muted-foreground">
                                <Building2 class="size-3.5" />
                                {{ tenant.name }}
                            </span>
                            <Badge v-if="plan" :class="planBadgeClass" class="gap-1">
                                <component v-if="planIcon" :is="planIcon" class="size-3" />
                                Gói {{ plan.name }}
                            </Badge>
                            <Badge v-else variant="secondary">Gói Free</Badge>
                        </div>
                    </div>

                    <Button v-if="nextPlan" as-child size="sm" class="shrink-0">
                        <Link :href="`/billing/checkout?plan=${nextPlan.code}`" class="flex items-center gap-1.5">
                            <Zap class="size-3.5" />
                            Nâng lên {{ nextPlan.name }}
                        </Link>
                    </Button>
                </div>

                <!-- Quota stats row -->
                <div v-if="quota && plan" class="mt-6 grid grid-cols-3 gap-3">
                    <div
                        v-for="stat in quotaStats"
                        :key="stat.key"
                        class="rounded-lg border border-border bg-background p-3"
                    >
                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <component :is="stat.icon" class="size-3.5" />
                            {{ stat.label }}
                        </div>
                        <p class="mt-1 text-xl font-bold">
                            {{ resourceData(stat.key)?.used ?? 0 }}
                            <span class="text-sm font-normal text-muted-foreground">
                                / {{ resourceData(stat.key)?.unlimited ? '∞' : formatLimit(resourceData(stat.key)?.limit ?? null) }}
                            </span>
                        </p>
                        <!-- Progress bar -->
                        <div class="mt-2 h-1.5 rounded-full bg-muted">
                            <div
                                v-if="!resourceData(stat.key)?.unlimited"
                                class="h-full rounded-full transition-all"
                                :class="(resourceData(stat.key)?.percentage ?? 0) >= 90 ? 'bg-rose-500' : 'bg-primary'"
                                :style="`width: ${resourceData(stat.key)?.percentage ?? 0}%`"
                            />
                            <div
                                v-else
                                class="h-full w-1/3 rounded-full bg-gradient-to-r from-primary/50 to-primary/20"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
