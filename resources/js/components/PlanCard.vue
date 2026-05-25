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
    features: {
        description?: string;
        ai_features?: boolean;
        realtime?: boolean;
        advanced_analytics?: boolean;
        api_rate_limit?: number;
        max_storage_mb?: number;
        max_areas?: number | null;
    } | null;
}
</script>

<script setup lang="ts">
import { BarChart3, Check, Crown, Sparkles, X, Zap } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    plan: Plan;
    selected: boolean;
    compact?: boolean;
}>();

const emit = defineEmits<{
    select: [code: string];
}>();

const code = computed(() => props.plan.code.toLowerCase());

const PlanIcon = computed(() => {
    switch (code.value) {
        case 'pro':   return Zap;
        case 'max':   return BarChart3;
        case 'ultra': return Crown;
        default:      return Sparkles;
    }
});

const accent = computed(() => {
    switch (code.value) {
        case 'pro': return {
            strip:      'from-emerald-500 to-emerald-400',
            borderOn:   'border-emerald-500/60',
            glow:       'shadow-[0_0_28px_rgba(16,185,129,0.18)]',
            check:      'text-emerald-400',
            icon:       'text-emerald-400',
            dot:        'bg-emerald-400',
            badgeCls:   'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
            badgeLabel: 'Phổ biến',
        };
        case 'max': return {
            strip:      'from-sky-500 to-sky-400',
            borderOn:   'border-sky-500/60',
            glow:       'shadow-[0_0_28px_rgba(14,165,233,0.18)]',
            check:      'text-sky-400',
            icon:       'text-sky-400',
            dot:        'bg-sky-400',
            badgeCls:   '',
            badgeLabel: '',
        };
        case 'ultra': return {
            strip:      'from-violet-500 to-violet-400',
            borderOn:   'border-violet-500/60',
            glow:       'shadow-[0_0_28px_rgba(139,92,246,0.18)]',
            check:      'text-violet-400',
            icon:       'text-violet-400',
            dot:        'bg-violet-400',
            badgeCls:   'bg-violet-500/15 text-violet-400 border-violet-500/30',
            badgeLabel: 'Enterprise',
        };
        default: return {
            strip:      'from-zinc-500 to-zinc-400',
            borderOn:   'border-zinc-400/50',
            glow:       'shadow-[0_0_28px_rgba(161,161,170,0.12)]',
            check:      'text-zinc-400',
            icon:       'text-zinc-400',
            dot:        'bg-zinc-400',
            badgeCls:   '',
            badgeLabel: '',
        };
    }
});

const defaultDescriptions: Record<string, string> = {
    free:  'Gói cơ bản trải nghiệm miễn phí.',
    pro:   'Tối ưu hiệu năng, chống thất thoát cho mô hình chuyên nghiệp.',
    max:   'Phù hợp cho chuỗi nhà hàng vừa và lớn.',
    ultra: 'Giải pháp tối thượng cho doanh nghiệp lớn & chuỗi rộng khắp.',
};

const description = computed(() =>
    props.plan.features?.description || defaultDescriptions[code.value] || ''
);

const priceDisplay = computed(() => {
    const p = Number(props.plan.price);
    if (p === 0) return { main: 'Miễn phí', sub: '' };
    return { main: p.toLocaleString('vi-VN') + 'đ', sub: '/tháng' };
});

const lim = (v: number | null, unit: string) =>
    v === null ? `Không giới hạn ${unit}` : `${v} ${unit}`;

const features = computed((): string[] => {
    const p = props.plan;
    const mb = p.features?.max_storage_mb ?? 500;
    const rate = p.features?.api_rate_limit ?? 60;
    const list = [
        lim(p.max_branches, 'chi nhánh'),
        lim(p.max_tables, 'bàn'),
        lim(p.max_users, 'nhân viên'),
        mb >= 1024 ? `${mb / 1024} GB lưu trữ` : `${mb} MB lưu trữ`,
        `Rate limit: ${rate.toLocaleString('vi-VN')}/phút`,
    ];
    if (p.features?.ai_features) list.push('AI dự báo nguyên liệu & tồn kho', 'Thuật toán AI phát hiện gian lận');
    if (p.features?.realtime) list.push('Realtime sync & Advanced Analytics');
    if (p.features?.advanced_analytics) list.push('Hệ thống Audit Log bảo mật');
    return list;
});

const unsupported = computed((): string[] => {
    const p = props.plan;
    const list: string[] = [];
    if (!p.features?.ai_features) list.push('AI dự báo nguyên liệu & tồn kho', 'Thuật toán AI phát hiện gian lận');
    if (!p.features?.realtime) list.push('Realtime sync & Advanced Analytics');
    if (!p.features?.advanced_analytics) list.push('Hệ thống Audit Log bảo mật');
    return list;
});
</script>

<template>
    <button
        type="button"
        @click="emit('select', plan.code)"
        :class="[
            'group relative w-full overflow-hidden rounded-xl border text-left transition-all duration-200',
            compact ? 'flex items-center gap-3 px-4 py-3.5' : 'block p-5',
            selected
                ? [accent.borderOn, accent.glow, 'bg-white/[0.06]']
                : 'border-zinc-800 bg-zinc-900/40 hover:border-zinc-700 hover:bg-zinc-900/70',
        ]"
    >
        <!-- Top accent strip -->
        <div :class="['absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r', accent.strip]" />

        <!-- ── COMPACT layout (horizontal row) ── -->
        <template v-if="compact">
            <component :is="PlanIcon" class="size-4 shrink-0" :class="accent.icon" />

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-sm font-semibold leading-tight" :class="selected ? 'text-white' : 'text-zinc-300'">
                        {{ plan.name }}
                    </span>
                    <span v-if="accent.badgeLabel"
                        :class="['rounded-full border px-1.5 py-px text-[10px] font-semibold', accent.badgeCls]">
                        {{ accent.badgeLabel }}
                    </span>
                </div>
                <p class="mt-0.5 text-xs" :class="selected ? 'text-zinc-400' : 'text-zinc-600'">
                    {{ priceDisplay.main }}<span v-if="priceDisplay.sub" class="opacity-60"> {{ priceDisplay.sub }}</span>
                </p>
            </div>

            <!-- Radio indicator -->
            <div class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 transition-all"
                :class="selected ? accent.borderOn : 'border-zinc-700'">
                <div v-if="selected" :class="['h-1.5 w-1.5 rounded-full', accent.dot]" />
            </div>
        </template>

        <!-- ── FULL layout (card) ── -->
        <template v-else>
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2">
                    <component :is="PlanIcon" class="size-4 shrink-0" :class="accent.icon" />
                    <span class="text-sm font-bold" :class="selected ? 'text-white' : 'text-zinc-200'">{{ plan.name }}</span>
                </div>
                <span v-if="accent.badgeLabel"
                    :class="['shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold', accent.badgeCls]">
                    {{ accent.badgeLabel }}
                </span>
            </div>

            <div class="mt-3 flex items-baseline gap-1">
                <span class="text-2xl font-bold leading-none" :class="selected ? 'text-white' : 'text-zinc-200'">
                    {{ priceDisplay.main }}
                </span>
                <span v-if="priceDisplay.sub" class="text-xs text-zinc-500">{{ priceDisplay.sub }}</span>
            </div>

            <p v-if="description" class="mt-2 text-[11px] leading-snug text-zinc-500">{{ description }}</p>

            <div class="my-3 h-px bg-zinc-800" />

            <ul class="space-y-1.5">
                <li v-for="f in features" :key="f" class="flex items-start gap-2">
                    <Check class="mt-px size-3 shrink-0" :class="selected ? accent.check : 'text-zinc-600'" />
                    <span class="text-[11px] leading-snug" :class="selected ? 'text-zinc-300' : 'text-zinc-500'">{{ f }}</span>
                </li>
                <li v-for="f in unsupported" :key="f" class="flex items-start gap-2 opacity-40">
                    <X class="mt-px size-3 shrink-0 text-zinc-500" />
                    <span class="text-[11px] leading-snug text-zinc-600">{{ f }}</span>
                </li>
            </ul>

            <div v-if="selected" :class="['absolute bottom-3.5 right-3.5 h-2 w-2 rounded-full', accent.dot]" />
        </template>
    </button>
</template>
