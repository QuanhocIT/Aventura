<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
// @ts-ignore
import AppLogoIcon from '@/components/AppLogoIcon.vue';
// @ts-ignore
import InputError from '@/components/InputError.vue';
// @ts-ignore
import PasswordInput from '@/components/PasswordInput.vue';
// @ts-ignore
// @ts-ignore
import PlanCard from '@/components/PlanCard.vue';
import type { Plan } from '@/components/PlanCard.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home, register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    plans: Plan[];
}>();

const urlParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
const selectedPlan = ref(urlParams?.get('plan') || 'free');
const selectedCycle = ref(urlParams?.get('cycle') || 'monthly');

const maxDiscountPercent = computed(() => {
    if (!props.plans?.length) {
return 20;
}

    const percentages = props.plans.map((p: Plan) => 
        p.features?.yearly_discount_percent !== undefined ? Number(p.features.yearly_discount_percent) : 20
    );

    return Math.max(...percentages);
});
</script>

<template>
    <Head title="Đăng nhập · Aventura" />

    <div class="min-h-dvh grid-cols-1 flex flex-col lg:grid lg:grid-cols-[1.1fr_2fr]">

        <!-- LEFT: Form with a rich subtle gradient and highly defined glassmorphic container -->
        <div class="relative flex min-h-dvh flex-col overflow-hidden bg-gradient-to-b from-zinc-50 via-white to-zinc-50 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950 px-6 py-10 sm:px-10 md:px-12">
            <!-- Decorative dynamic glowing elements (clearly visible) -->
            <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent" />
            <div class="pointer-events-none absolute -bottom-20 -left-20 h-[400px] w-[400px] rounded-full bg-emerald-500/15 blur-[120px] animate-pulse duration-[8s]" />
            <div class="pointer-events-none absolute -right-20 top-1/4 h-[350px] w-[350px] rounded-full bg-blue-500/10 blur-[100px] animate-pulse duration-[6s]" />

            <!-- Logo (top) -->
            <Link :href="home()" class="flex items-center gap-2.5 transition-transform duration-300 hover:scale-[1.02] w-fit">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary shadow-md">
                    <AppLogoIcon class="size-5 fill-current text-primary-foreground" />
                </span>
                <span class="text-lg font-black tracking-tight bg-gradient-to-r from-zinc-900 to-zinc-650 dark:from-white dark:to-zinc-300 bg-clip-text text-transparent">Aventura</span>
            </Link>

            <!-- Form (middle — brought up to eliminate whitespace) -->
            <div class="flex flex-1 flex-col justify-start pt-6 sm:pt-8 relative z-10">
                <div class="animate-in fade-in slide-in-from-bottom-6 duration-500 fill-mode-both w-full max-w-md mx-auto backdrop-blur-xl bg-white/70 dark:bg-zinc-950/70 border border-white/80 dark:border-zinc-800/80 p-8 sm:p-10 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.06)] dark:shadow-[0_20px_50px_rgba(0,0,0,0.3)]">

                    <div class="mb-4 inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/5 dark:bg-emerald-500/10 px-4 py-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500/80" />
                        Đăng nhập an toàn
                    </div>
                    <h1 class="bg-gradient-to-r from-zinc-950 via-zinc-800 to-emerald-600 dark:from-white dark:via-zinc-200 dark:to-emerald-400 bg-clip-text text-3xl font-black tracking-tight text-transparent leading-none">Chào mừng trở lại</h1>
                    <p class="mt-2.5 text-sm text-muted-foreground">
                        Nhập thông tin để đăng nhập vào hệ thống.
                    </p>

                    <div v-if="status" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400 shadow-sm animate-in fade-in slide-in-from-top-2 duration-300">
                        {{ status }}
                    </div>

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="mt-6 space-y-5"
                    >
                        <input type="hidden" name="plan_code" :value="selectedPlan" />
                        <input type="hidden" name="cycle" :value="selectedCycle" />

                        <div class="grid gap-1.5">
                            <Label for="email" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">Địa chỉ email</Label>
                            <Input id="email" type="email" name="email" required autofocus
                                :tabindex="1" autocomplete="email" placeholder="email@example.com"
                                class="rounded-xl border-zinc-200 dark:border-zinc-800 transition-all duration-300 hover:border-zinc-300 dark:hover:border-zinc-700 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500 shadow-sm" />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-1.5">
                            <div class="flex items-center justify-between">
                                <Label for="password" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">Mật khẩu</Label>
                                <TextLink v-if="canResetPassword" :href="request()" class="text-xs text-muted-foreground hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors duration-250 font-medium" :tabindex="5">
                                    Quên mật khẩu?
                                </TextLink>
                            </div>
                            <PasswordInput id="password" name="password" required
                                :tabindex="2" autocomplete="current-password" placeholder="Mật khẩu"
                                class="rounded-xl border-zinc-200 dark:border-zinc-800 transition-all duration-300 hover:border-zinc-300 dark:hover:border-zinc-700 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500 shadow-sm" />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="flex items-center gap-2.5 py-1">
                            <Checkbox id="remember" name="remember" :tabindex="3" class="rounded border-zinc-300 dark:border-zinc-700 text-emerald-600 focus:ring-emerald-500/10" />
                            <Label for="remember" class="cursor-pointer text-sm font-semibold text-muted-foreground hover:text-foreground transition-colors duration-200 select-none">
                                Ghi nhớ đăng nhập
                            </Label>
                        </div>

                        <Button type="submit" class="w-full rounded-xl py-6 font-black uppercase tracking-wider text-xs bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white transition-all duration-300 shadow-[0_4px_20px_rgba(16,185,129,0.3)] hover:shadow-[0_4px_25px_rgba(16,185,129,0.45)] hover:-translate-y-0.5 active:scale-[0.98] border-none cursor-pointer" :tabindex="4" :disabled="processing">
                            <Spinner v-if="processing" class="text-white" />
                            {{ processing ? 'Đang đăng nhập...' : 'Đăng nhập' }}
                        </Button>
                    </Form>

                    <div class="relative my-6 flex items-center gap-3">
                        <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800/80" />
                        <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground/60">Hoặc</span>
                        <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800/80" />
                    </div>

                    <Button as="a" href="/auth/google" variant="outline" class="w-full rounded-xl py-5 transition-all duration-300 hover:bg-zinc-50 dark:hover:bg-zinc-900 border-zinc-200 dark:border-zinc-800 shadow-sm hover:shadow hover:-translate-y-0.5 active:scale-[0.98] cursor-pointer font-bold">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="size-4 shrink-0 mr-2 transition-transform duration-300">
                            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                            <path fill="none" d="M0 0h48v48H0z"/>
                        </svg>
                        Đăng nhập bằng Google
                    </Button>
                </div>
            </div>

            <!-- Footer (bottom) -->
            <p v-if="canRegister" class="text-center text-sm text-muted-foreground mt-6 relative z-10">
                Chưa có tài khoản?
                <TextLink :href="register.url({ query: { plan: selectedPlan, cycle: selectedCycle } })" :tabindex="6" class="font-bold text-emerald-600 dark:text-emerald-400 underline underline-offset-4 hover:text-emerald-500 transition-colors">
                    Đăng ký miễn phí
                </TextLink>
            </p>
        </div>

        <!-- RIGHT: Plans panel (desktop only) -->
        <div class="relative hidden flex-col overflow-hidden border-l border-zinc-900 bg-zinc-950 px-12 py-10 lg:flex lg:min-h-dvh justify-between">
            <!-- Dot-grid dynamic background -->
            <svg class="pointer-events-none absolute inset-0 size-full opacity-[0.06]" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="dots-login-panel" x="0" y="0" width="24" height="24" patternUnits="userSpaceOnUse">
                        <circle cx="1.5" cy="1.5" r="1.5" fill="white" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#dots-login-panel)" />
            </svg>
            <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(ellipse_80%_60%_at_50%_0%,rgba(255,255,255,0.08),transparent)]" />

            <!-- Ambient light blob that floats in the dark section (vividly visible) -->
            <div class="pointer-events-none absolute top-1/4 left-1/3 h-[500px] w-[500px] rounded-full bg-emerald-500/[0.12] blur-[130px] z-0 animate-pulse duration-[8s]" />
            <div class="pointer-events-none absolute bottom-1/4 right-1/4 h-[450px] w-[450px] rounded-full bg-violet-500/[0.10] blur-[120px] z-0 animate-pulse duration-[10s]" />

            <!-- Header (top) -->
            <div class="relative z-10 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div class="max-w-xl">
                    <div class="mb-3.5 inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3.5 py-1 text-xs text-zinc-400 shadow-sm backdrop-blur-md">
                        <svg class="size-3 text-emerald-400 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        Chọn gói phù hợp
                    </div>
                    <h2 class="bg-gradient-to-br from-white to-zinc-400 bg-clip-text text-3xl font-black tracking-tight text-transparent">Nâng cấp bất cứ lúc nào</h2>
                    <p class="mt-2 text-sm text-zinc-500 leading-relaxed">
                        Chọn gói rồi đăng nhập — hệ thống chuyển bạn đến trang thanh toán ngay.
                    </p>
                </div>

                <!-- Billing toggle -->
                <div class="flex items-center gap-1 rounded-xl border border-white/10 bg-white/5 p-1 text-xs shrink-0 self-start sm:self-auto backdrop-blur-md">
                    <button
                        type="button"
                        @click="selectedCycle = 'monthly'"
                        class="rounded-lg px-3 py-1.5 font-semibold transition-all cursor-pointer"
                        :class="selectedCycle === 'monthly' ? 'bg-zinc-800 shadow-sm text-white' : 'text-zinc-400 hover:text-white'"
                    >Tháng</button>
                    <button
                        type="button"
                        @click="selectedCycle = 'yearly'"
                        class="rounded-lg px-3 py-1.5 font-semibold transition-all flex items-center gap-1 cursor-pointer"
                        :class="selectedCycle === 'yearly' ? 'bg-zinc-800 shadow-sm text-white' : 'text-zinc-400 hover:text-white'"
                    >
                        Năm
                        <span class="text-[9px] font-bold bg-emerald-500 text-white px-1.5 py-0.5 rounded-full shadow-xs">-{{ maxDiscountPercent }}%</span>
                    </button>
                </div>
            </div>

            <!-- Plans (middle — flex-1 centered) -->
            <div class="relative z-10 flex flex-1 flex-col justify-start pt-2 my-4">
                <div class="animate-in fade-in slide-in-from-bottom-4 duration-700 fill-mode-both w-full">
                    <div class="grid grid-cols-4 gap-3">
                        <PlanCard
                            v-for="plan in plans"
                            :key="plan.code"
                            :plan="plan"
                            :selected="selectedPlan === plan.code"
                            :billing-cycle="selectedCycle === 'yearly' ? 'yearly' : 'monthly'"
                            @select="selectedPlan = $event"
                        />
                    </div>

                    <!-- Selected hint -->
                    <div class="mt-5 rounded-2xl border border-white/[0.08] bg-white/[0.04] backdrop-blur-md px-5 py-4 transition-all duration-300 shadow-md">
                        <p class="text-xs text-zinc-400 flex flex-wrap items-center gap-1.5 leading-relaxed">
                            <span class="inline-flex h-2 w-2 shrink-0 items-center justify-center rounded-full bg-emerald-500 animate-pulse" />
                            <span class="font-bold text-zinc-200">Đang chọn:</span>
                            <span class="font-black text-white bg-white/10 border border-white/20 rounded-md px-2 py-0.5 shadow-inner">
                                {{ plans.find(p => p.code === selectedPlan)?.name ?? 'Free' }}
                            </span>
                            <span class="text-zinc-500">
                                <template v-if="Number(plans.find(p => p.code === selectedPlan)?.price ?? 0) > 0">
                                    · Sau đăng nhập sẽ tự động chuyển đến trang thanh toán.
                                </template>
                                <template v-else>
                                    · Gói trải nghiệm miễn phí, dễ dàng nâng cấp bất kỳ lúc nào.
                                </template>
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer (bottom) -->
            <p class="relative z-10 text-center text-xs text-zinc-650 tracking-wide">
                14 ngày dùng thử miễn phí · Hủy bất cứ lúc nào · Không phí ẩn
            </p>
        </div>

    </div>
</template>
