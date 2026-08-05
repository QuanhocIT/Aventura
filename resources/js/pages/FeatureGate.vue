<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Lock,
    Zap,
    Check,
    ArrowRight,
    Crown,
    Star,
    Shield,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { FEATURE_LABELS } from '@/composables/useFeatureGate';
import type { FeatureKey } from '@/composables/useFeatureGate';

const props = defineProps<{
    feature: FeatureKey;
    feature_label: string;
    plan_name: string;
    required_plan: string;
}>();

const featureInfo = FEATURE_LABELS[props.feature] ?? {
    label: props.feature_label,
    required_plan: props.required_plan,
};

const isAnnual = ref(true);

const PLANS = [
    {
        name: 'Miễn Phí',
        code: 'free',
        priceMonthly: '0đ',
        priceAnnual: '0đ',
        icon: Shield,
        color: 'text-slate-500',
        bg: 'bg-slate-50 dark:bg-slate-900/40',
        border: 'border-slate-200 dark:border-slate-800',
        features: [
            'POS & Đơn hàng',
            'Quản lý Menu',
            'Sơ đồ Bàn',
            'Khách hàng cơ bản',
        ],
    },
    {
        name: 'Cơ Bản',
        code: 'starter',
        priceMonthly: '299.000đ',
        priceAnnual: '239.000đ',
        icon: Zap,
        color: 'text-blue-600 dark:text-blue-400',
        bg: 'bg-blue-50/50 dark:bg-blue-950/20',
        border: 'border-blue-200 dark:border-blue-900/50',
        features: [
            'Màn hình Bếp & QR Order',
            'Chấm công & Lịch',
            'Quản lý Tồn kho',
            'Giao hàng & Online',
            'Realtime updates',
        ],
    },
    {
        name: 'Chuyên Nghiệp',
        code: 'pro',
        priceMonthly: '699.000đ',
        priceAnnual: '559.000đ',
        icon: Star,
        color: 'text-primary',
        bg: 'bg-primary/5',
        border: 'border-primary/30',
        features: [
            'Phân tích & Báo cáo AI',
            'Lương & Nhân sự đầy đủ',
            'Phát hiện Gian lận',
            'Email tự động',
            'AI Tư vấn chiến lược',
        ],
    },
    {
        name: 'Doanh Nghiệp',
        code: 'enterprise',
        priceMonthly: '1.499.000đ',
        priceAnnual: '1.199.000đ',
        icon: Crown,
        color: 'text-violet-600 dark:text-violet-400',
        bg: 'bg-violet-50/50 dark:bg-violet-950/20',
        border: 'border-violet-200 dark:border-violet-900/50',
        features: [
            'Cổng Nhà cung cấp',
            'AI Dự báo tồn kho',
            'API không giới hạn',
            'Hỗ trợ ưu tiên P1',
        ],
    },
];

const currentPlanIndex = PLANS.findIndex((p) => p.name === props.plan_name);
const requiredPlanIndex = PLANS.findIndex(
    (p) => p.name === featureInfo.required_plan,
);
const requiredPlan = PLANS[requiredPlanIndex] ?? PLANS[2];

function goBack() {
    window.history.back();
}
</script>

<template>
    <Head :title="`Nâng cấp — ${featureInfo.label}`" />

    <div
        class="relative flex min-h-screen items-center justify-center overflow-hidden bg-background p-4 lg:p-8"
    >
        <!-- Floating Glow Blobs -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div
                class="float-glow absolute -top-[40%] -left-[20%] h-[80%] w-[80%] rounded-full bg-gradient-to-tr from-primary/10 to-violet-500/10 blur-3xl"
            ></div>
            <div
                class="float-glow absolute -right-[20%] -bottom-[30%] h-[70%] w-[70%] rounded-full bg-gradient-to-tr from-emerald-500/5 to-primary/10 blur-3xl"
                style="animation-delay: -3s"
            ></div>
        </div>

        <div class="relative z-10 w-full max-w-4xl space-y-8">
            <!-- Header -->
            <div class="animate-enter stagger-1 space-y-4 text-center">
                <div
                    class="glass glow-primary relative mb-2 inline-flex h-20 w-20 items-center justify-center rounded-3xl border-primary/20 text-primary"
                >
                    <div
                        class="absolute inset-0 animate-ping rounded-3xl bg-primary/10 opacity-25"
                    ></div>
                    <Lock class="relative z-10 size-9" />
                </div>
                <div>
                    <Badge
                        variant="outline"
                        class="mb-2 border-primary/20 bg-primary/5 px-2.5 py-0.5 text-xs font-semibold tracking-wide text-primary uppercase"
                    >
                        Tính năng cao cấp
                    </Badge>
                    <h1
                        class="text-gradient-brand heading-section text-3xl leading-tight font-extrabold lg:text-4xl"
                    >
                        {{ featureInfo.label }}
                    </h1>
                    <p
                        class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-muted-foreground"
                    >
                        Tính năng này yêu cầu gói
                        <span class="font-bold" :class="requiredPlan.color">{{
                            featureInfo.required_plan
                        }}</span>
                        trở lên. Vui lòng nâng cấp tài khoản để bắt đầu sử dụng.
                    </p>
                </div>
            </div>

            <!-- Billing Cycle Switcher -->
            <div
                class="animate-enter stagger-2 my-6 flex items-center justify-center gap-3"
            >
                <span
                    class="text-xs font-semibold tracking-wider uppercase"
                    :class="
                        !isAnnual ? 'text-foreground' : 'text-muted-foreground'
                    "
                    >Thanh toán tháng</span
                >
                <button
                    @click="isAnnual = !isAnnual"
                    type="button"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent bg-muted transition-colors duration-200 ease-in-out hover:bg-muted/80 focus:outline-none"
                    :class="
                        isAnnual
                            ? 'bg-primary hover:bg-primary/90'
                            : 'bg-slate-200 dark:bg-slate-800'
                    "
                >
                    <span
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"
                        :class="isAnnual ? 'translate-x-5' : 'translate-x-0'"
                    ></span>
                </button>
                <span
                    class="flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase"
                    :class="
                        isAnnual ? 'text-foreground' : 'text-muted-foreground'
                    "
                >
                    Thanh toán năm
                    <Badge
                        variant="outline"
                        class="border-emerald-500/20 bg-emerald-500/10 px-1 text-[9px] text-emerald-500 dark:text-emerald-400"
                        >Tiết kiệm 20%</Badge
                    >
                </span>
            </div>

            <!-- Current vs Required -->
            <div
                class="animate-enter stagger-3 mx-auto grid max-w-2xl grid-cols-1 gap-6 md:grid-cols-2"
            >
                <!-- Current Plan -->
                <Card
                    :class="[
                        'glass-card shadow-premium relative flex flex-col justify-between overflow-hidden border border-border/50 opacity-75 backdrop-blur-sm transition-all duration-300 hover:opacity-90',
                        PLANS[currentPlanIndex]?.border ??
                            'border-slate-200 dark:border-slate-800',
                    ]"
                >
                    <CardContent class="p-6">
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <Badge
                                    variant="secondary"
                                    class="mb-2 text-[10px] font-bold tracking-wider uppercase"
                                    >Gói hiện tại</Badge
                                >
                                <h3 class="text-xl font-bold text-foreground">
                                    {{ props.plan_name }}
                                </h3>
                            </div>
                            <component
                                :is="PLANS[currentPlanIndex]?.icon ?? Shield"
                                class="size-6 text-muted-foreground"
                            />
                        </div>
                        <ul class="mt-4 space-y-2.5">
                            <li
                                v-for="f in PLANS[currentPlanIndex]?.features ??
                                []"
                                :key="f"
                                class="flex items-center gap-2 text-xs text-muted-foreground/90"
                            >
                                <div
                                    class="rounded-full bg-emerald-500/10 p-0.5"
                                >
                                    <Check
                                        class="size-3 shrink-0 text-emerald-500"
                                    />
                                </div>
                                {{ f }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Required Plan (Gradient Border Upgrade) -->
                <div
                    class="gradient-border shadow-elevated relative overflow-hidden rounded-2xl p-[2px] transition-all duration-300 hover:scale-[1.02]"
                >
                    <div
                        class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/10 to-transparent hover:animate-[shimmer_1.5s_infinite]"
                    ></div>

                    <div
                        class="relative z-10 flex h-full flex-col justify-between rounded-[14px] bg-card p-6 dark:bg-zinc-950"
                    >
                        <div>
                            <div class="mb-4 flex items-center justify-between">
                                <Badge
                                    class="bg-primary text-[10px] font-bold tracking-wider text-white uppercase hover:bg-primary"
                                    >Khuyến nghị</Badge
                                >
                                <span
                                    class="text-gradient-vibrant text-2xl font-black"
                                >
                                    {{
                                        isAnnual
                                            ? requiredPlan.priceAnnual
                                            : requiredPlan.priceMonthly
                                    }}
                                    <span
                                        class="text-xs font-normal text-muted-foreground"
                                        >/tháng</span
                                    >
                                </span>
                            </div>
                            <h3
                                class="flex items-center gap-2 text-xl font-bold text-foreground"
                            >
                                <component
                                    :is="requiredPlan.icon"
                                    class="size-6"
                                    :class="requiredPlan.color"
                                />
                                {{ featureInfo.required_plan }}
                            </h3>

                            <ul class="mt-4 space-y-2.5">
                                <li
                                    v-for="f in requiredPlan.features"
                                    :key="f"
                                    class="flex items-center gap-2 text-xs font-medium text-foreground"
                                >
                                    <div
                                        class="rounded-full bg-primary/10 p-0.5"
                                    >
                                        <Check
                                            class="size-3 shrink-0 text-primary"
                                        />
                                    </div>
                                    {{ f }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA -->
            <div
                class="animate-enter stagger-4 flex flex-col items-center justify-center gap-4 sm:flex-row"
            >
                <Button
                    as-child
                    size="lg"
                    class="animated-gradient cursor-pointer rounded-xl border-0 px-8 py-6 text-white shadow-md transition-all hover:scale-[1.02] hover:shadow-xl active:scale-[0.98]"
                >
                    <Link
                        href="/billing/history"
                        class="flex items-center gap-2 font-bold tracking-wide"
                    >
                        <Zap class="size-4 animate-bounce" />
                        Nâng cấp lên {{ featureInfo.required_plan }}
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-1"
                        />
                    </Link>
                </Button>
                <Button
                    variant="outline"
                    size="lg"
                    class="rounded-xl border-border/80 px-8 py-6 transition-colors hover:bg-muted/50"
                    @click="goBack"
                >
                    Quay lại
                </Button>
            </div>

            <!-- Savings hint -->
            <p
                class="animate-enter stagger-4 text-center text-xs text-muted-foreground"
            >
                Thanh toán năm tiết kiệm
                <span class="font-bold text-primary">20%</span> · Không ảnh
                hưởng dữ liệu hiện tại · Hủy bất cứ lúc nào
            </p>

            <!-- Full plan comparison -->
            <div class="animate-enter stagger-5 border-t border-border pt-4">
                <h3
                    class="mb-6 text-center text-xs font-bold tracking-widest text-muted-foreground uppercase"
                >
                    So sánh tất cả gói
                </h3>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div
                        v-for="(plan, idx) in PLANS"
                        :key="plan.code"
                        :class="[
                            'relative flex flex-col justify-between overflow-hidden rounded-2xl border p-5 transition-all duration-300',
                            plan.border,
                            plan.bg,
                            idx === requiredPlanIndex
                                ? 'z-10 scale-[1.03] shadow-lg ring-2 ring-primary/45'
                                : 'backdrop-blur-sm hover:scale-[1.02] hover:shadow-md',
                            idx <= currentPlanIndex
                                ? 'border-border/40 bg-muted/20 opacity-60'
                                : '',
                        ]"
                    >
                        <div
                            v-if="idx === requiredPlanIndex"
                            class="absolute top-0 right-0"
                        >
                            <span
                                class="rounded-bl-lg bg-primary px-2 py-0.5 text-[8px] font-bold tracking-wider text-white uppercase"
                                >Tối ưu nhất</span
                            >
                        </div>

                        <div>
                            <div class="mb-3 flex items-center gap-2">
                                <div
                                    class="rounded-lg border border-border/50 bg-card p-1"
                                >
                                    <component
                                        :is="plan.icon"
                                        class="size-4"
                                        :class="plan.color"
                                    />
                                </div>
                                <span
                                    class="text-xs font-bold tracking-wider uppercase"
                                    :class="plan.color"
                                    >{{ plan.name }}</span
                                >
                            </div>

                            <p
                                class="flex items-baseline gap-0.5 text-lg font-black text-foreground"
                            >
                                {{
                                    isAnnual
                                        ? plan.priceAnnual
                                        : plan.priceMonthly
                                }}
                                <span
                                    class="text-[9px] font-normal text-muted-foreground"
                                    >/th</span
                                >
                            </p>

                            <ul class="mt-3.5 space-y-2">
                                <li
                                    v-for="f in plan.features.slice(0, 3)"
                                    :key="f"
                                    class="flex items-center gap-1.5 text-[10px] leading-relaxed text-muted-foreground"
                                >
                                    <Check
                                        class="size-3 shrink-0 text-emerald-500"
                                    />
                                    {{ f }}
                                </li>
                                <li
                                    v-if="plan.features.length > 3"
                                    class="pl-4.5 text-[10px] text-muted-foreground italic"
                                >
                                    +{{ plan.features.length - 3 }} tính năng
                                    khác
                                </li>
                            </ul>
                        </div>

                        <div class="mt-5">
                            <div
                                v-if="idx === currentPlanIndex"
                                class="rounded-lg border border-border/40 bg-muted/50 py-2 text-center text-[10px] font-semibold text-muted-foreground"
                            >
                                Gói của bạn
                            </div>
                            <div
                                v-else-if="idx < currentPlanIndex"
                                class="py-2 text-center text-[10px] font-semibold text-muted-foreground/40"
                            >
                                Gói thấp hơn
                            </div>
                            <Link
                                v-else
                                href="/billing/history"
                                class="block rounded-lg border py-2 text-center text-[10px] font-bold transition-all duration-200"
                                :class="
                                    idx === requiredPlanIndex
                                        ? 'border-primary bg-primary text-white shadow-sm hover:bg-primary/90 hover:shadow-primary/20'
                                        : 'border-border text-foreground hover:bg-muted'
                                "
                            >
                                {{
                                    idx === requiredPlanIndex
                                        ? 'Chọn gói này'
                                        : 'Nâng cấp'
                                }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
