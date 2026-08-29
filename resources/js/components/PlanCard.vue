<script lang="ts">
export interface Plan {
    id: number;
    code: string;
    name: string;
    price: number | string;
    billing_cycle: string;
    max_branches: number | null;
    max_tables: number | null;
    max_users: number | null;
    features: Record<string, any> | null;
}
</script>

<script setup lang="ts">
import { Check, Crown, Star, Users, X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        plan: Plan;
        selected: boolean;
        compact?: boolean;
        billingCycle?: 'monthly' | 'yearly';
    }>(),
    {
        billingCycle: 'monthly',
    },
);

const emit = defineEmits<{
    select: [code: string];
}>();

const code = computed(() => props.plan.code.toLowerCase());

const PlanIcon = computed(() => {
    switch (code.value) {
        case 'starter':
            return Check;
        case 'pro':
            return Crown;
        case 'enterprise':
            return Users;
        default:
            return Star;
    }
});

const accent = computed(() => {
    switch (code.value) {
        case 'pro':
            return {
                strip: 'from-emerald-500 to-emerald-400',
                borderOn: 'border-emerald-400 border-2',
                glow: 'shadow-[0_0_35px_rgba(16,185,129,0.35)]',
                check: 'text-emerald-400',
                icon: 'text-emerald-400',
                dot: 'bg-emerald-400',
                badgeCls:
                    'bg-emerald-500/20 text-emerald-300 border-emerald-500/30 backdrop-blur-md',
                badgeLabel: 'Khuyến nghị',
                selectedBg: 'bg-emerald-950/25 border-emerald-400/80',
            };
        case 'starter':
            return {
                strip: 'from-sky-500 to-sky-400',
                borderOn: 'border-sky-400 border-2',
                glow: 'shadow-[0_0_35px_rgba(14,165,233,0.35)]',
                check: 'text-sky-400',
                icon: 'text-sky-400',
                dot: 'bg-sky-400',
                badgeCls:
                    'bg-sky-500/20 text-sky-300 border-sky-500/30 backdrop-blur-md',
                badgeLabel: '',
                selectedBg: 'bg-sky-950/25 border-sky-400/80',
            };
        case 'enterprise':
            return {
                strip: 'from-violet-500 to-violet-400',
                borderOn: 'border-violet-400 border-2',
                glow: 'shadow-[0_0_35px_rgba(139,92,246,0.35)]',
                check: 'text-violet-400',
                icon: 'text-violet-400',
                dot: 'bg-violet-400',
                badgeCls:
                    'bg-violet-500/20 text-violet-300 border-violet-500/30 backdrop-blur-md',
                badgeLabel: 'VIP',
                selectedBg: 'bg-violet-950/25 border-violet-400/80',
            };
        default:
            return {
                strip: 'from-zinc-500 to-zinc-400',
                borderOn: 'border-zinc-350 border-2',
                glow: 'shadow-[0_0_35px_rgba(255,255,255,0.15)]',
                check: 'text-zinc-300',
                icon: 'text-zinc-300',
                dot: 'bg-zinc-300',
                badgeCls:
                    'bg-zinc-500/20 text-zinc-300 border-zinc-500/30 backdrop-blur-md',
                badgeLabel: '',
                selectedBg: 'bg-zinc-900/40 border-zinc-400/80',
            };
    }
});

const defaultDescriptions: Record<string, string> = {
    free: 'Gói cơ bản, trải nghiệm POS miễn phí.',
    starter: 'Đầy đủ vận hành: bếp, QR, chấm công, tồn kho.',
    pro: 'Nâng cao toàn diện: AI, nhân sự, báo cáo, chống gian lận.',
    enterprise: 'Giải pháp doanh nghiệp: AI dự báo, API không giới hạn.',
};

const description = computed(
    () =>
        props.plan.features?.description ||
        defaultDescriptions[code.value] ||
        '',
);

const priceDisplay = computed(() => {
    const p = Number(props.plan.price);

    if (p === 0) {
        return { main: 'Miễn phí', sub: '' };
    }

    if (props.billingCycle === 'yearly') {
        const discountPercent =
            props.plan.features?.yearly_discount_percent !== undefined
                ? Number(props.plan.features.yearly_discount_percent)
                : 20;
        const yearlyMonthly = Math.round(p * (1 - discountPercent / 100));

        return {
            main: yearlyMonthly.toLocaleString('vi-VN') + 'đ',
            sub: '/tháng (thanh toán năm)',
        };
    }

    return { main: p.toLocaleString('vi-VN') + 'đ', sub: '/tháng' };
});

const lim = (v: number | null, unit: string) =>
    v === null || v === -1 ? `Không giới hạn ${unit}` : `${v} ${unit}`;

const ALL_FEATURES = [
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
    { key: 'ai_forecasting', label: 'AI Dự báo Tồn kho' },
    { key: 'api_access', label: 'Truy cập API' },
];

const features = computed((): string[] => {
    const p = props.plan;
    const mb = p.features?.max_storage_mb ?? 500;
    const rate = p.features?.api_rate_limit ?? 30;
    const list = [
        lim(p.max_branches, 'chi nhánh'),
        lim(p.max_tables, 'bàn'),
        lim(p.max_users, 'nhân viên'),
        mb >= 1024 ? `${mb / 1024} GB lưu trữ` : `${mb} MB lưu trữ`,
        `API: ${rate.toLocaleString('vi-VN')} req/phút`,
    ];

    for (const f of ALL_FEATURES) {
        if (p.features?.[f.key]) {
            list.push(f.label);
        }
    }

    return list;
});

const unsupported = computed((): string[] => {
    const p = props.plan;
    const list: string[] = [];

    for (const f of ALL_FEATURES) {
        if (!p.features?.[f.key]) {
            list.push(f.label);
        }
    }

    return list;
});
</script>

<template>
    <div
        role="button"
        tabindex="0"
        @click="emit('select', plan.code)"
        @keydown.enter="emit('select', plan.code)"
        @keydown.space.prevent="emit('select', plan.code)"
        :class="[
            'group relative w-full overflow-hidden rounded-2xl border text-left backdrop-blur-md transition-all duration-300 outline-none select-none focus-visible:ring-2 focus-visible:ring-ring',
            compact
                ? 'flex cursor-pointer items-center gap-3 px-4 py-3.5'
                : 'block cursor-pointer p-6',
            selected
                ? [
                      accent.borderOn,
                      accent.glow,
                      accent.selectedBg,
                      'z-10 scale-[1.01]',
                  ]
                : 'border-zinc-800/80 bg-zinc-950/40 shadow-md hover:-translate-y-0.5 hover:border-zinc-700/60 hover:bg-zinc-900/40 hover:shadow-lg',
        ]"
    >
        <!-- Top accent strip with interactive pulse glow -->
        <div
            :class="[
                'absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r opacity-80 transition-all duration-300 group-hover:opacity-100',
                accent.strip,
            ]"
        />

        <!-- ── COMPACT layout (horizontal row) ── -->
        <template v-if="compact">
            <component
                :is="PlanIcon"
                class="size-4 shrink-0 transition-transform duration-300 group-hover:scale-110"
                :class="accent.icon"
            />

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span
                        class="text-sm leading-tight font-semibold transition-colors duration-200"
                        :class="
                            selected
                                ? 'text-white'
                                : 'text-zinc-300 group-hover:text-white'
                        "
                    >
                        {{ plan.name }}
                    </span>
                    <span
                        v-if="accent.badgeLabel"
                        :class="[
                            'rounded-full border px-1.5 py-px text-[10px] font-semibold tracking-wide uppercase',
                            accent.badgeCls,
                        ]"
                    >
                        {{ accent.badgeLabel }}
                    </span>
                </div>
                <p
                    class="mt-0.5 text-xs transition-colors duration-200"
                    :class="
                        selected
                            ? 'text-zinc-400'
                            : 'text-zinc-500 group-hover:text-zinc-400'
                    "
                >
                    {{ priceDisplay.main
                    }}<span v-if="priceDisplay.sub" class="opacity-60">
                        {{ priceDisplay.sub }}</span
                    >
                </p>
            </div>

            <!-- Radio indicator -->
            <div
                class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 transition-all duration-300"
                :class="
                    selected
                        ? accent.borderOn
                        : 'border-zinc-700 group-hover:border-zinc-600'
                "
            >
                <div
                    v-if="selected"
                    :class="[
                        'h-1.5 w-1.5 scale-100 rounded-full transition-transform duration-300',
                        accent.dot,
                    ]"
                />
            </div>
        </template>

        <!-- ── FULL layout (card) ── -->
        <template v-else>
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2">
                    <component
                        :is="PlanIcon"
                        class="size-4 shrink-0 transition-transform duration-300 group-hover:scale-110"
                        :class="accent.icon"
                    />
                    <span
                        class="text-sm font-bold transition-colors duration-200"
                        :class="
                            selected
                                ? 'text-white'
                                : 'text-zinc-200 group-hover:text-white'
                        "
                        >{{ plan.name }}</span
                    >
                </div>
                <span
                    v-if="accent.badgeLabel"
                    :class="[
                        'shrink-0 rounded-full border px-2 py-0.5 text-[9px] font-bold tracking-wider uppercase',
                        accent.badgeCls,
                    ]"
                >
                    {{ accent.badgeLabel }}
                </span>
            </div>

            <div class="mt-4 flex flex-wrap items-baseline gap-1.5">
                <span
                    class="text-2xl leading-none font-black tracking-tight transition-colors duration-200"
                    :class="
                        selected
                            ? 'text-white'
                            : 'text-zinc-200 group-hover:text-white'
                    "
                >
                    {{ priceDisplay.main }}
                </span>
                <span
                    v-if="priceDisplay.sub"
                    class="text-xs font-medium text-zinc-500"
                    >{{ priceDisplay.sub }}</span
                >
                <span
                    v-if="billingCycle === 'yearly' && Number(plan.price) > 0"
                    class="inline-flex rounded-full border border-emerald-500/20 bg-emerald-500/10 px-1.5 py-0.5 text-[9px] font-bold text-emerald-400"
                >
                    Giảm
                    {{
                        plan.features?.yearly_discount_percent !== undefined
                            ? plan.features.yearly_discount_percent
                            : 20
                    }}%
                </span>
            </div>

            <p
                v-if="description"
                class="mt-2.5 text-[11px] leading-snug text-zinc-500 transition-colors duration-200 group-hover:text-zinc-400"
            >
                {{ description }}
            </p>

            <div
                class="my-4 h-px bg-zinc-800/80 transition-colors duration-300 group-hover:bg-zinc-800"
            />

            <div class="custom-scrollbar max-h-[180px] overflow-y-auto pr-1.5">
                <ul class="space-y-2">
                    <li
                        v-for="f in features"
                        :key="f"
                        class="flex items-start gap-2"
                    >
                        <Check
                            class="mt-0.5 size-3.5 shrink-0 transition-transform duration-300 group-hover:scale-110"
                            :class="selected ? accent.check : 'text-zinc-500'"
                        />
                        <span
                            class="text-[11px] leading-snug transition-colors duration-200"
                            :class="
                                selected
                                    ? 'text-zinc-300'
                                    : 'text-zinc-400 group-hover:text-zinc-300'
                            "
                            >{{ f }}</span
                        >
                    </li>
                    <li
                        v-for="f in unsupported"
                        :key="f"
                        class="flex items-start gap-2 opacity-35 transition-all duration-300 group-hover:opacity-40"
                    >
                        <X class="text-zinc-650 mt-0.5 size-3.5 shrink-0" />
                        <span
                            class="text-zinc-650 text-[11px] leading-snug line-through decoration-zinc-800/80"
                            >{{ f }}</span
                        >
                    </li>
                </ul>
            </div>

            <div
                v-if="selected"
                :class="[
                    'absolute right-3.5 bottom-3.5 h-2 w-2 animate-pulse rounded-full',
                    accent.dot,
                ]"
            />
        </template>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    display: none;
    width: 0;
    height: 0;
}
.custom-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
