<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Lock, Zap, Check, ArrowRight, ChevronRight, Crown, Star, Shield } from 'lucide-vue-next';
import { FEATURE_LABELS } from '@/composables/useFeatureGate';
import type { FeatureKey } from '@/composables/useFeatureGate';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    feature: FeatureKey;
    feature_label: string;
    plan_name: string;
    required_plan: string;
}>();

const featureInfo = FEATURE_LABELS[props.feature] ?? { label: props.feature_label, required_plan: props.required_plan };

const PLANS = [
    {
        name: 'Miễn Phí',
        code: 'free',
        price: '0đ',
        icon: Shield,
        color: 'text-slate-500',
        bg: 'bg-slate-50 dark:bg-slate-900/40',
        border: 'border-slate-200 dark:border-slate-800',
        features: ['POS & Đơn hàng', 'Quản lý Menu', 'Sơ đồ Bàn', 'Khách hàng cơ bản'],
    },
    {
        name: 'Cơ Bản',
        code: 'starter',
        price: '299.000đ',
        icon: Zap,
        color: 'text-blue-600 dark:text-blue-400',
        bg: 'bg-blue-50/50 dark:bg-blue-950/20',
        border: 'border-blue-200 dark:border-blue-900/50',
        features: ['Màn hình Bếp & QR Order', 'Chấm công & Lịch', 'Quản lý Tồn kho', 'Giao hàng & Online', 'Realtime updates'],
    },
    {
        name: 'Chuyên Nghiệp',
        code: 'pro',
        price: '699.000đ',
        icon: Star,
        color: 'text-primary',
        bg: 'bg-primary/5',
        border: 'border-primary/30',
        features: ['Phân tích & Báo cáo AI', 'Lương & Nhân sự đầy đủ', 'Phát hiện Gian lận', 'Email tự động', 'AI Tư vấn chiến lược'],
    },
    {
        name: 'Doanh Nghiệp',
        code: 'enterprise',
        price: '1.499.000đ',
        icon: Crown,
        color: 'text-violet-600 dark:text-violet-400',
        bg: 'bg-violet-50/50 dark:bg-violet-950/20',
        border: 'border-violet-200 dark:border-violet-900/50',
        features: ['Cổng Nhà cung cấp', 'AI Dự báo tồn kho', 'API không giới hạn', 'Hỗ trợ ưu tiên P1'],
    },
];

const currentPlanIndex = PLANS.findIndex(p => p.name === props.plan_name);
const requiredPlanIndex = PLANS.findIndex(p => p.name === featureInfo.required_plan);
const requiredPlan = PLANS[requiredPlanIndex] ?? PLANS[2];
</script>

<template>
    <Head :title="`Nâng cấp — ${featureInfo.label}`" />

    <div class="min-h-screen flex items-center justify-center bg-background p-4 lg:p-8">
        <div class="w-full max-w-4xl space-y-8">

            <!-- Header -->
            <div class="text-center space-y-4">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary/10 text-primary">
                    <Lock class="size-8" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-foreground">{{ featureInfo.label }}</h1>
                    <p class="mt-2 text-muted-foreground text-sm max-w-md mx-auto">
                        Tính năng này yêu cầu gói
                        <span class="font-bold" :class="requiredPlan.color">{{ featureInfo.required_plan }}</span>
                        trở lên. Nâng cấp để mở khoá ngay.
                    </p>
                </div>
            </div>

            <!-- Current vs Required -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-2xl mx-auto">
                <!-- Current Plan -->
                <Card :class="['border-2 opacity-60', PLANS[currentPlanIndex]?.border ?? 'border-slate-200 dark:border-slate-800']">
                    <CardContent class="p-5">
                        <Badge variant="secondary" class="mb-3 text-[10px]">Gói hiện tại</Badge>
                        <h3 class="text-lg font-bold text-foreground">{{ props.plan_name }}</h3>
                        <ul class="mt-3 space-y-1.5">
                            <li v-for="f in PLANS[currentPlanIndex]?.features ?? []" :key="f" class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Check class="size-3.5 text-emerald-500 shrink-0" />
                                {{ f }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Required Plan -->
                <Card :class="['border-2 shadow-lg relative overflow-hidden', requiredPlan.border]">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-primary to-amber-500"></div>
                    <CardContent class="p-5">
                        <div class="flex items-center justify-between mb-3">
                            <Badge class="text-[10px]">Khuyến nghị</Badge>
                            <span class="text-lg font-black" :class="requiredPlan.color">{{ requiredPlan.price }}<span class="text-xs font-normal text-muted-foreground">/tháng</span></span>
                        </div>
                        <h3 class="text-lg font-bold text-foreground flex items-center gap-2">
                            <component :is="requiredPlan.icon" class="size-5" :class="requiredPlan.color" />
                            {{ featureInfo.required_plan }}
                        </h3>
                        <ul class="mt-3 space-y-1.5">
                            <li v-for="f in requiredPlan.features" :key="f" class="flex items-center gap-2 text-xs text-foreground font-medium">
                                <Check class="size-3.5 text-primary shrink-0" />
                                {{ f }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <!-- CTA -->
            <div class="flex flex-col sm:flex-row gap-3 justify-center items-center">
                <Button as-child variant="brand" size="lg" class="rounded-xl px-8">
                    <Link href="/billing/history" class="flex items-center gap-2">
                        <Zap class="size-4" />
                        Nâng cấp lên {{ featureInfo.required_plan }}
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
                <Button variant="outline" size="lg" class="rounded-xl" @click="$router?.back?.() || window.history.back()">
                    Quay lại
                </Button>
            </div>

            <!-- Savings hint -->
            <p class="text-center text-xs text-muted-foreground">
                Thanh toán năm giảm <span class="font-bold text-primary">20%</span> · Không mất dữ liệu khi nâng gói · Hủy bất cứ lúc nào
            </p>

            <!-- Full plan comparison -->
            <div class="pt-4 border-t border-border">
                <h3 class="text-sm font-bold text-center text-muted-foreground mb-6">So sánh tất cả gói</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div
                        v-for="(plan, idx) in PLANS"
                        :key="plan.code"
                        :class="[
                            'rounded-xl border p-4 transition-all',
                            plan.border, plan.bg,
                            idx === requiredPlanIndex ? 'ring-2 ring-primary/30 shadow-md' : '',
                            idx <= currentPlanIndex ? 'opacity-50' : ''
                        ]"
                    >
                        <div class="flex items-center gap-1.5 mb-2">
                            <component :is="plan.icon" class="size-4" :class="plan.color" />
                            <span class="text-xs font-bold" :class="plan.color">{{ plan.name }}</span>
                        </div>
                        <p class="text-sm font-black text-foreground">{{ plan.price }}<span class="text-[10px] font-normal text-muted-foreground">/th</span></p>
                        <ul class="mt-2 space-y-1">
                            <li v-for="f in plan.features.slice(0, 3)" :key="f" class="text-[10px] text-muted-foreground flex items-center gap-1">
                                <Check class="size-2.5 shrink-0" />
                                {{ f }}
                            </li>
                            <li v-if="plan.features.length > 3" class="text-[10px] text-muted-foreground">
                                +{{ plan.features.length - 3 }} tính năng khác
                            </li>
                        </ul>
                        <Link
                            v-if="idx > currentPlanIndex"
                            href="/billing/history"
                            class="mt-3 block text-center text-[10px] font-bold py-1.5 rounded-lg border transition-colors"
                            :class="idx === requiredPlanIndex ? 'bg-primary text-white border-primary hover:bg-primary/90' : 'text-foreground border-border hover:bg-muted'"
                        >
                            {{ idx === requiredPlanIndex ? 'Chọn gói này' : 'Xem chi tiết' }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
