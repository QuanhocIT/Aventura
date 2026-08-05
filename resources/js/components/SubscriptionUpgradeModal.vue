<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Check, Lock, Sparkles, X, Zap } from 'lucide-vue-next';
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
const availablePlans = computed(
    () => (page.props.available_plans as AvailablePlan[]) ?? [],
);

const isUpgradeModalOpen = ref(false);

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

function formatPrice(price: number) {
    if (price === 0) {
        return 'Miễn phí';
    }

    return price.toLocaleString('vi-VN') + 'đ/tháng';
}
void formatPrice;

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
        list.push(
            'AI dự báo nguyên liệu & tồn kho',
            'Thuật toán AI phát hiện gian lận',
        );
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
        list.push(
            'AI dự báo nguyên liệu & tồn kho',
            'Thuật toán AI phát hiện gian lận',
        );
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
    free: 'Gói cơ bản trải nghiệm miễn phí.',
    pro: 'Tối ưu hiệu năng, chống thất thoát cho mô hình chuyên nghiệp.',
    max: 'Phù hợp cho chuỗi nhà hàng vừa và lớn.',
    ultra: 'Giải pháp tối thượng cho doanh nghiệp lớn & chuỗi rộng khắp.',
};

function planDescription(plan: AvailablePlan) {
    return (
        (plan.features?.description as string | undefined) ||
        defaultDescriptions[plan.code] ||
        ''
    );
}

function planAccent(code: string) {
    switch (code) {
        case 'pro':
            return {
                border: 'border-emerald-500/60',
                glow: 'shadow-emerald-500/10',
                badge: 'bg-emerald-500/10 text-emerald-600',
                btn: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                check: 'text-emerald-500',
            };
        case 'max':
            return {
                border: 'border-sky-500/60',
                glow: 'shadow-sky-500/10',
                badge: 'bg-sky-500/10 text-sky-600',
                btn: 'bg-sky-600 hover:bg-sky-700 text-white',
                check: 'text-sky-500',
            };
        case 'ultra':
            return {
                border: 'border-violet-500/60',
                glow: 'shadow-violet-500/10',
                badge: 'bg-violet-500/10 text-violet-600',
                btn: 'bg-violet-600 hover:bg-violet-700 text-white',
                check: 'text-violet-500',
            };
        default:
            return {
                border: 'border-border',
                glow: '',
                badge: 'bg-muted text-muted-foreground',
                btn: 'bg-muted hover:bg-muted/80 text-foreground',
                check: 'text-muted-foreground',
            };
    }
}

const isYearly = ref(false);

const maxDiscountPercent = computed(() => {
    if (!availablePlans.value.length) {
        return 20;
    }

    const percentages = availablePlans.value.map((p) =>
        p.features?.yearly_discount_percent !== undefined
            ? Number(p.features.yearly_discount_percent)
            : 20,
    );

    return Math.max(...percentages);
});

function getDisplayPrice(plan: AvailablePlan) {
    if (plan.price === 0) {
        return 'Miễn phí';
    }

    if (isYearly.value) {
        const discountPercent =
            plan.features?.yearly_discount_percent !== undefined
                ? Number(plan.features.yearly_discount_percent)
                : 20;
        const yearlyPrice = Math.round(
            plan.price * 12 * (1 - discountPercent / 100),
        );

        return yearlyPrice.toLocaleString('vi-VN') + 'đ/năm';
    }

    return plan.price.toLocaleString('vi-VN') + 'đ/tháng';
}

function goToCheckout(code: string) {
    window.location.href =
        `/billing/checkout?plan=${code}` +
        (isYearly.value ? '&cycle=yearly' : '&cycle=monthly');
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
    <Teleport to="body">
        <div
            v-if="isUpgradeModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="isUpgradeModalOpen = false"
        >
            <div
                class="relative max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-2xl border border-border bg-card shadow-2xl"
            >
                <!-- Close -->
                <button
                    @click="isUpgradeModalOpen = false"
                    class="absolute top-4 right-4 z-10 rounded-full p-1.5 text-muted-foreground transition-colors hover:bg-muted"
                >
                    <X class="size-5" />
                </button>

                <!-- Header -->
                <div class="border-b border-border px-6 pt-8 pb-5 text-center">
                    <div
                        class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10"
                    >
                        <Sparkles class="size-6 animate-pulse text-primary" />
                    </div>
                    <h3 class="text-xl font-bold tracking-tight">
                        Chọn gói nâng cấp
                    </h3>
                    <p class="mt-1.5 text-sm text-muted-foreground">
                        Đang dùng
                        <strong>{{ tenant?.plan?.name ?? 'Free' }}</strong> ·
                        Chỉ có thể nâng lên gói cao hơn
                    </p>

                    <!-- Toggle cycle -->
                    <div class="mt-4 flex items-center justify-center gap-3">
                        <span
                            class="text-xs"
                            :class="
                                !isYearly
                                    ? 'font-bold text-foreground'
                                    : 'text-muted-foreground'
                            "
                            >Thanh toán Hàng tháng</span
                        >
                        <button
                            @click="isYearly = !isYearly"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent bg-muted transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
                            :class="isYearly ? 'bg-primary' : 'bg-input'"
                        >
                            <span
                                class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-background shadow ring-0 transition duration-200 ease-in-out"
                                :class="
                                    isYearly ? 'translate-x-4' : 'translate-x-0'
                                "
                            />
                        </button>
                        <span
                            class="flex items-center gap-1.5 text-xs"
                            :class="
                                isYearly
                                    ? 'font-bold text-foreground'
                                    : 'text-muted-foreground'
                            "
                        >
                            Thanh toán Hàng năm
                            <span
                                class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-bold text-emerald-600"
                            >
                                Tiết kiệm tới {{ maxDiscountPercent }}%
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Plan grid -->
                <div
                    class="grid grid-cols-1 gap-4 px-6 py-6 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        v-for="(plan, idx) in availablePlans"
                        :key="plan.code"
                        class="relative flex flex-col rounded-xl border p-4 transition-all duration-200"
                        :class="[
                            idx === planRank
                                ? 'border-2 border-emerald-500/60 bg-emerald-500/5'
                                : idx < planRank
                                  ? 'cursor-not-allowed border-border bg-muted/20 opacity-50'
                                  : [
                                        'border-2',
                                        planAccent(plan.code).border,
                                        'bg-card hover:shadow-md',
                                        planAccent(plan.code).glow,
                                        'cursor-pointer',
                                    ],
                        ]"
                    >
                        <!-- Badge -->
                        <div class="absolute top-3 right-3">
                            <span
                                v-if="idx === planRank"
                                class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[9px] font-bold text-emerald-600 uppercase"
                            >
                                Hiện tại
                            </span>
                            <span
                                v-else-if="
                                    idx === planRank + 1 &&
                                    idx < availablePlans.length
                                "
                                class="rounded-full bg-primary/10 px-2 py-0.5 text-[9px] font-bold text-primary uppercase"
                            >
                                Đề xuất
                            </span>
                        </div>

                        <!-- Plan info -->
                        <div class="flex-1">
                            <h4 class="pr-16 text-sm font-bold">
                                {{ plan.name }}
                            </h4>
                            <p
                                class="mt-0.5 min-h-[30px] text-[11px] leading-snug text-muted-foreground"
                            >
                                {{ planDescription(plan) }}
                            </p>

                            <div
                                class="mt-3 flex flex-wrap items-baseline gap-1.5"
                            >
                                <span
                                    class="text-xl font-extrabold"
                                    :class="
                                        idx < planRank
                                            ? 'text-muted-foreground'
                                            : 'text-foreground'
                                    "
                                >
                                    {{ getDisplayPrice(plan) }}
                                </span>
                                <span
                                    v-if="
                                        isYearly &&
                                        plan.price > 0 &&
                                        (plan.features
                                            ?.yearly_discount_percent !==
                                        undefined
                                            ? Number(
                                                  plan.features
                                                      .yearly_discount_percent,
                                              )
                                            : 20) > 0
                                    "
                                    class="inline-flex self-center rounded-full border border-emerald-500/30 bg-emerald-500/15 px-1.5 py-0.5 text-[9px] font-bold text-emerald-600"
                                >
                                    Tiết kiệm
                                    {{
                                        plan.features
                                            ?.yearly_discount_percent !==
                                        undefined
                                            ? plan.features
                                                  .yearly_discount_percent
                                            : 20
                                    }}%
                                </span>
                            </div>

                            <div class="my-3 h-px bg-border" />

                            <!-- Features list -->
                            <ul class="space-y-1.5 text-[11px]">
                                <li
                                    v-for="f in getPlanFeatures(plan)"
                                    :key="f"
                                    class="flex items-start gap-1.5"
                                >
                                    <Check
                                        class="mt-px size-3 shrink-0"
                                        :class="
                                            idx <= planRank
                                                ? 'text-muted-foreground'
                                                : planAccent(plan.code).check
                                        "
                                    />
                                    <span
                                        :class="
                                            idx < planRank
                                                ? 'text-muted-foreground'
                                                : 'text-foreground'
                                        "
                                        >{{ f }}</span
                                    >
                                </li>
                                <li
                                    v-for="f in getPlanUnsupported(plan)"
                                    :key="f"
                                    class="flex items-start gap-1.5 opacity-40"
                                >
                                    <X
                                        class="mt-px size-3 shrink-0 text-muted-foreground"
                                    />
                                    <span class="text-muted-foreground">{{
                                        f
                                    }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Action button -->
                        <div class="mt-4">
                            <!-- Current plan -->
                            <button
                                v-if="idx === planRank"
                                disabled
                                class="w-full cursor-not-allowed rounded-lg border border-border bg-muted px-3 py-2 text-xs font-semibold text-muted-foreground"
                            >
                                Gói hiện tại
                            </button>
                            <!-- Lower plan — cannot downgrade -->
                            <button
                                v-else-if="idx < planRank"
                                disabled
                                class="flex w-full cursor-not-allowed items-center justify-center gap-1.5 rounded-lg border border-border/40 bg-muted/30 px-3 py-2 text-xs font-semibold text-muted-foreground/50"
                            >
                                <Lock class="size-3" /> Không khả dụng
                            </button>
                            <!-- Higher plan — can upgrade -->
                            <button
                                v-else
                                @click="goToCheckout(plan.code)"
                                class="flex w-full items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold transition-colors"
                                :class="planAccent(plan.code).btn"
                            >
                                <Zap class="size-3.5" />
                                Nâng cấp ngay
                                <ArrowUpRight class="ml-auto size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div
                    class="flex flex-col items-center justify-between gap-2 border-t border-border bg-muted/30 px-6 py-4 text-xs text-muted-foreground sm:flex-row"
                >
                    <span class="flex items-center gap-1.5">
                        <Lock class="size-3.5" />
                        Giao dịch được mã hóa an toàn · Không mất dữ liệu khi
                        nâng gói
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="isUpgradeModalOpen = false"
                        >Đóng</Button
                    >
                </div>
            </div>
        </div>
    </Teleport>
</template>
