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
        case 'pro':   return 'bg-gradient-to-r from-teal-500 to-emerald-500 hover:from-teal-600 hover:to-emerald-600 text-white shadow-sm border-0';
        case 'max':   return 'bg-gradient-to-r from-sky-500 to-indigo-500 hover:from-sky-600 hover:to-indigo-600 text-white shadow-sm border-0';
        case 'ultra': return 'bg-gradient-to-r from-violet-600 to-fuchsia-600 hover:from-violet-750 hover:to-fuchsia-750 text-white shadow-lg shadow-fuchsia-500/25 animate-pulse border-0';
        default:      return 'bg-slate-200 text-slate-800 dark:bg-slate-800 dark:text-slate-200 border-0';
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
    { key: 'branches',  label: 'Chi nhánh', icon: Building2, colorClass: 'text-rose-500 bg-rose-500/10 dark:bg-rose-500/20' },
    { key: 'employees', label: 'Nhân viên',  icon: Users, colorClass: 'text-violet-500 bg-violet-500/10 dark:bg-violet-500/20' },
    { key: 'tables',    label: 'Bàn ăn',     icon: BarChart3, colorClass: 'text-teal-500 bg-teal-500/10 dark:bg-teal-500/20' },
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
    <div class="relative overflow-hidden">
        <!-- Background Gradient Mesh -->
        <div class="absolute inset-0 bg-gradient-to-b from-teal-500/5 via-violet-500/3 to-transparent dark:from-teal-500/10 dark:via-violet-500/5 dark:to-transparent pointer-events-none" />
        <div class="absolute -top-40 -left-40 size-96 bg-teal-500/10 dark:bg-teal-500/20 rounded-full blur-[100px] pointer-events-none" />
        <div class="absolute -top-40 -right-40 size-96 bg-violet-500/10 dark:bg-violet-500/25 rounded-full blur-[100px] pointer-events-none" />

        <!-- ── Trial Countdown Banner ────────────────────────────── -->
        <div v-if="isOnTrial" class="relative z-10 border-b border-amber-200/60 dark:border-amber-900/30 bg-gradient-to-r from-amber-500/15 via-orange-500/10 to-amber-500/15 backdrop-blur-md px-4 py-3 lg:px-8">
            <div class="mx-auto max-w-7xl flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 text-sm">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white text-[10px] font-bold shadow-sm animate-bounce">!</span>
                    <span class="text-amber-800 dark:text-amber-200 font-semibold tracking-wide">
                        Tài khoản đang dùng thử —
                        <strong class="text-amber-600 dark:text-amber-400">còn {{ trialDaysLeft }} ngày</strong>.
                        Nâng cấp để không gián đoạn vận hành hệ thống của bạn.
                    </span>
                </div>
                <Link :href="nextPlan ? `/billing/checkout?plan=${nextPlan.code}` : '/billing/checkout'"
                    class="shrink-0 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white text-xs font-bold px-4.5 py-2 transition-all hover:scale-[1.03] active:scale-95 shadow-md shadow-orange-500/25">
                    Nâng cấp ngay →
                </Link>
            </div>
        </div>

        <!-- ── Welcome header ───────────────────────────────────── -->
        <section class="relative z-10 border-b border-slate-100 dark:border-slate-800 bg-white/30 dark:bg-slate-950/20 backdrop-blur-lg px-4 py-8 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold text-muted-foreground uppercase tracking-widest">Xin chào trở lại,</p>
                        <h1 class="mt-1 text-3xl font-extrabold tracking-tight bg-gradient-to-r from-slate-900 via-slate-800 to-slate-700 dark:from-slate-100 dark:via-slate-200 dark:to-slate-350 bg-clip-text text-transparent">{{ user?.name }}</h1>
                        
                        <div class="mt-3 flex flex-wrap items-center gap-2.5">
                            <span v-if="tenant?.name" class="flex items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800/60 px-3 py-1 rounded-lg">
                                <Building2 class="size-3.5 text-teal-500" />
                                {{ tenant.name }}
                            </span>
                            <Badge v-if="plan" :class="planBadgeClass" class="gap-1 px-3 py-1 rounded-lg text-xs font-bold tracking-wide">
                                <component v-if="planIcon" :is="planIcon" class="size-3.5" />
                                Gói {{ plan.name }}
                            </Badge>
                            <Badge v-else variant="secondary" class="px-3 py-1 rounded-lg text-xs font-bold">Gói Free</Badge>
                        </div>
                    </div>

                    <Button v-if="nextPlan" as-child size="default" class="shrink-0 rounded-xl bg-gradient-to-r from-teal-500 to-emerald-500 hover:from-teal-600 hover:to-emerald-600 text-white font-bold transition-all hover:scale-[1.02] active:scale-95 shadow-md shadow-emerald-500/25">
                        <Link :href="`/billing/checkout?plan=${nextPlan.code}`" class="flex items-center gap-1.5">
                            <Zap class="size-4 animate-pulse text-amber-300" />
                            Nâng lên gói {{ nextPlan.name }}
                        </Link>
                    </Button>
                </div>

                <!-- Quota stats row -->
                <div v-if="quota && plan" class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div
                        v-for="stat in quotaStats"
                        :key="stat.key"
                        class="group relative rounded-2xl border border-slate-100/80 dark:border-slate-800/80 bg-white/60 dark:bg-slate-900/60 backdrop-blur-md p-4 transition-all duration-300 hover:shadow-lg hover:border-slate-200 dark:hover:border-slate-700/80 hover:translate-y-[-2px]"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <div class="p-1.5 rounded-lg" :class="stat.colorClass">
                                    <component :is="stat.icon" class="size-4" />
                                </div>
                                {{ stat.label }}
                            </div>
                            <!-- Percentage badge -->
                            <span 
                                v-if="!resourceData(stat.key)?.unlimited"
                                class="text-[10px] font-extrabold px-2 py-0.5 rounded-full"
                                :class="(resourceData(stat.key)?.percentage ?? 0) >= 90 
                                    ? 'bg-rose-50 text-rose-600 dark:bg-rose-950/30 dark:text-rose-400' 
                                    : 'bg-teal-50 text-teal-600 dark:bg-teal-950/30 dark:text-teal-400'"
                            >
                                {{ Math.round(resourceData(stat.key)?.percentage ?? 0) }}%
                            </span>
                            <span v-else class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-850 dark:text-slate-400">Vô hạn</span>
                        </div>

                        <div class="mt-3 flex items-baseline gap-1.5">
                            <p class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                                {{ resourceData(stat.key)?.used ?? 0 }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                / {{ resourceData(stat.key)?.unlimited ? '∞' : formatLimit(resourceData(stat.key)?.limit ?? null) }} mục đã dùng
                            </p>
                        </div>
                        
                        <!-- Progress bar -->
                        <div class="mt-3 h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                            <div
                                v-if="!resourceData(stat.key)?.unlimited"
                                class="h-full rounded-full transition-all duration-500"
                                :class="(resourceData(stat.key)?.percentage ?? 0) >= 90 
                                    ? 'bg-gradient-to-r from-rose-500 to-red-600' 
                                    : 'bg-gradient-to-r from-teal-400 to-emerald-500'"
                                :style="`width: ${resourceData(stat.key)?.percentage ?? 0}%`"
                            />
                            <div
                                v-else
                                class="h-full w-full rounded-full bg-gradient-to-r from-slate-200 via-slate-150 to-slate-200 dark:from-slate-800 dark:via-slate-700 dark:to-slate-800"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
