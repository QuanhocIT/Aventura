<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Lock, Zap, Check, ArrowRight, ChevronRight, Crown, Star, Shield } from 'lucide-vue-next';
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

const featureInfo = FEATURE_LABELS[props.feature] ?? { label: props.feature_label, required_plan: props.required_plan };

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
        features: ['POS & Đơn hàng', 'Quản lý Menu', 'Sơ đồ Bàn', 'Khách hàng cơ bản'],
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
        features: ['Màn hình Bếp & QR Order', 'Chấm công & Lịch', 'Quản lý Tồn kho', 'Giao hàng & Online', 'Realtime updates'],
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
        features: ['Phân tích & Báo cáo AI', 'Lương & Nhân sự đầy đủ', 'Phát hiện Gian lận', 'Email tự động', 'AI Tư vấn chiến lược'],
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
        features: ['Cổng Nhà cung cấp', 'AI Dự báo tồn kho', 'API không giới hạn', 'Hỗ trợ ưu tiên P1'],
    },
];

const currentPlanIndex = PLANS.findIndex(p => p.name === props.plan_name);
const requiredPlanIndex = PLANS.findIndex(p => p.name === featureInfo.required_plan);
const requiredPlan = PLANS[requiredPlanIndex] ?? PLANS[2];

function goBack() {
    window.history.back();
}
</script>

<template>
    <Head :title="`Nâng cấp — ${featureInfo.label}`" />

    <div class="min-h-screen relative overflow-hidden flex items-center justify-center bg-background p-4 lg:p-8">
        <!-- Floating Glow Blobs -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-[40%] -left-[20%] w-[80%] h-[80%] rounded-full bg-gradient-to-tr from-primary/10 to-violet-500/10 blur-3xl float-glow"></div>
            <div class="absolute -bottom-[30%] -right-[20%] w-[70%] h-[70%] rounded-full bg-gradient-to-tr from-emerald-500/5 to-primary/10 blur-3xl float-glow" style="animation-delay: -3s;"></div>
        </div>

        <div class="w-full max-w-4xl space-y-8 relative z-10">

            <!-- Header -->
            <div class="text-center space-y-4 animate-enter stagger-1">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl glass glow-primary border-primary/20 text-primary relative mb-2">
                    <div class="absolute inset-0 rounded-3xl bg-primary/10 animate-ping opacity-25"></div>
                    <Lock class="size-9 relative z-10" />
                </div>
                <div>
                    <Badge variant="outline" class="mb-2 px-2.5 py-0.5 border-primary/20 text-primary bg-primary/5 text-xs font-semibold tracking-wide uppercase">
                        Tính năng cao cấp
                    </Badge>
                    <h1 class="text-3xl lg:text-4xl font-extrabold text-gradient-brand heading-section leading-tight">
                        {{ featureInfo.label }}
                    </h1>
                    <p class="mt-3 text-muted-foreground text-sm max-w-md mx-auto leading-relaxed">
                        Tính năng này yêu cầu gói
                        <span class="font-bold" :class="requiredPlan.color">{{ featureInfo.required_plan }}</span>
                        trở lên. Vui lòng nâng cấp tài khoản để bắt đầu sử dụng.
                    </p>
                </div>
            </div>

            <!-- Billing Cycle Switcher -->
            <div class="flex items-center justify-center gap-3 my-6 animate-enter stagger-2">
                <span class="text-xs font-semibold uppercase tracking-wider" :class="!isAnnual ? 'text-foreground' : 'text-muted-foreground'">Thanh toán tháng</span>
                <button
                    @click="isAnnual = !isAnnual"
                    type="button"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-muted hover:bg-muted/80"
                    :class="isAnnual ? 'bg-primary hover:bg-primary/90' : 'bg-slate-200 dark:bg-slate-800'"
                >
                    <span
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"
                        :class="isAnnual ? 'translate-x-5' : 'translate-x-0'"
                    ></span>
                </button>
                <span class="text-xs font-semibold uppercase tracking-wider flex items-center gap-1.5" :class="isAnnual ? 'text-foreground' : 'text-muted-foreground'">
                    Thanh toán năm
                    <Badge variant="outline" class="text-[9px] px-1 bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 border-emerald-500/20">Tiết kiệm 20%</Badge>
                </span>
            </div>

            <!-- Current vs Required -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-2xl mx-auto animate-enter stagger-3">
                <!-- Current Plan -->
                <Card :class="['glass-card border border-border/50 relative overflow-hidden opacity-75 backdrop-blur-sm shadow-premium flex flex-col justify-between transition-all duration-300 hover:opacity-90', PLANS[currentPlanIndex]?.border ?? 'border-slate-200 dark:border-slate-800']">
                    <CardContent class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <Badge variant="secondary" class="mb-2 text-[10px] uppercase font-bold tracking-wider">Gói hiện tại</Badge>
                                <h3 class="text-xl font-bold text-foreground">{{ props.plan_name }}</h3>
                            </div>
                            <component :is="PLANS[currentPlanIndex]?.icon ?? Shield" class="size-6 text-muted-foreground" />
                        </div>
                        <ul class="space-y-2.5 mt-4">
                            <li v-for="f in PLANS[currentPlanIndex]?.features ?? []" :key="f" class="flex items-center gap-2 text-xs text-muted-foreground/90">
                                <div class="bg-emerald-500/10 p-0.5 rounded-full">
                                    <Check class="size-3 text-emerald-500 shrink-0" />
                                </div>
                                {{ f }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Required Plan (Gradient Border Upgrade) -->
                <div class="gradient-border p-[2px] rounded-2xl shadow-elevated transition-all duration-300 hover:scale-[1.02] relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full hover:animate-[shimmer_1.5s_infinite] pointer-events-none"></div>
                    
                    <div class="bg-card dark:bg-zinc-950 rounded-[14px] p-6 h-full relative z-10 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <Badge class="text-[10px] uppercase font-bold tracking-wider bg-primary hover:bg-primary text-white">Khuyến nghị</Badge>
                                <span class="text-2xl font-black text-gradient-vibrant">
                                    {{ isAnnual ? requiredPlan.priceAnnual : requiredPlan.priceMonthly }}
                                    <span class="text-xs font-normal text-muted-foreground">/tháng</span>
                                </span>
                            </div>
                            <h3 class="text-xl font-bold text-foreground flex items-center gap-2">
                                <component :is="requiredPlan.icon" class="size-6" :class="requiredPlan.color" />
                                {{ featureInfo.required_plan }}
                            </h3>
                            
                            <ul class="mt-4 space-y-2.5">
                                <li v-for="f in requiredPlan.features" :key="f" class="flex items-center gap-2 text-xs text-foreground font-medium">
                                    <div class="bg-primary/10 p-0.5 rounded-full">
                                        <Check class="size-3 text-primary shrink-0" />
                                    </div>
                                    {{ f }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center animate-enter stagger-4">
                <Button as-child size="lg" class="animated-gradient text-white rounded-xl px-8 py-6 shadow-md hover:shadow-xl hover:scale-[1.02] active:scale-[0.98] transition-all cursor-pointer border-0">
                    <Link href="/billing/history" class="flex items-center gap-2 font-bold tracking-wide">
                        <Zap class="size-4 animate-bounce" />
                        Nâng cấp lên {{ featureInfo.required_plan }}
                        <ArrowRight class="size-4 transition-transform group-hover:translate-x-1" />
                    </Link>
                </Button>
                <Button 
                    variant="outline" 
                    size="lg" 
                    class="rounded-xl px-8 py-6 border-border/80 hover:bg-muted/50 transition-colors" 
                    @click="goBack"
                >
                    Quay lại
                </Button>
            </div>

            <!-- Savings hint -->
            <p class="text-center text-xs text-muted-foreground animate-enter stagger-4">
                Thanh toán năm tiết kiệm <span class="font-bold text-primary">20%</span> · Không ảnh hưởng dữ liệu hiện tại · Hủy bất cứ lúc nào
            </p>

            <!-- Full plan comparison -->
            <div class="pt-4 border-t border-border animate-enter stagger-5">
                <h3 class="text-xs font-bold text-center text-muted-foreground mb-6 uppercase tracking-widest">So sánh tất cả gói</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div
                        v-for="(plan, idx) in PLANS"
                        :key="plan.code"
                        :class="[
                            'rounded-2xl border p-5 transition-all duration-300 relative overflow-hidden flex flex-col justify-between',
                            plan.border, plan.bg,
                            idx === requiredPlanIndex ? 'ring-2 ring-primary/45 shadow-lg scale-[1.03] z-10' : 'hover:scale-[1.02] hover:shadow-md backdrop-blur-sm',
                            idx <= currentPlanIndex ? 'opacity-60 bg-muted/20 border-border/40' : ''
                        ]"
                    >
                        <div v-if="idx === requiredPlanIndex" class="absolute top-0 right-0">
                            <span class="bg-primary text-white text-[8px] font-bold px-2 py-0.5 rounded-bl-lg uppercase tracking-wider">Tối ưu nhất</span>
                        </div>
                        
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <div class="p-1 rounded-lg bg-card border border-border/50">
                                    <component :is="plan.icon" class="size-4" :class="plan.color" />
                                </div>
                                <span class="text-xs font-bold uppercase tracking-wider" :class="plan.color">{{ plan.name }}</span>
                            </div>
                            
                            <p class="text-lg font-black text-foreground flex items-baseline gap-0.5">
                                {{ isAnnual ? plan.priceAnnual : plan.priceMonthly }}
                                <span class="text-[9px] font-normal text-muted-foreground">/th</span>
                            </p>
                            
                            <ul class="mt-3.5 space-y-2">
                                <li v-for="f in plan.features.slice(0, 3)" :key="f" class="text-[10px] text-muted-foreground flex items-center gap-1.5 leading-relaxed">
                                    <Check class="size-3 text-emerald-500 shrink-0" />
                                    {{ f }}
                                </li>
                                <li v-if="plan.features.length > 3" class="text-[10px] text-muted-foreground pl-4.5 italic">
                                    +{{ plan.features.length - 3 }} tính năng khác
                                </li>
                            </ul>
                        </div>
                        
                        <div class="mt-5">
                            <div v-if="idx === currentPlanIndex" class="text-center text-[10px] font-semibold text-muted-foreground py-2 rounded-lg bg-muted/50 border border-border/40">
                                Gói của bạn
                            </div>
                            <div v-else-if="idx < currentPlanIndex" class="text-center text-[10px] font-semibold text-muted-foreground/40 py-2">
                                Gói thấp hơn
                            </div>
                            <Link
                                v-else
                                href="/billing/history"
                                class="block text-center text-[10px] font-bold py-2 rounded-lg border transition-all duration-200"
                                :class="idx === requiredPlanIndex ? 'bg-primary text-white border-primary hover:bg-primary/90 shadow-sm hover:shadow-primary/20' : 'text-foreground border-border hover:bg-muted'"
                            >
                                {{ idx === requiredPlanIndex ? 'Chọn gói này' : 'Nâng cấp' }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
