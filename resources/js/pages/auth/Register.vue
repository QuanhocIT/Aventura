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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home, login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
    plans: Plan[];
}>();

const selectedPlan = ref('free');

const passwordValue = ref('');

const passwordStrength = computed((): { score: number; label: string; color: string } => {
    const p = passwordValue.value;

    if (!p) {
return { score: 0, label: '', color: '' };
}

    let score = 0;

    if (p.length >= 8) {
score++;
}

    if (p.length >= 12) {
score++;
}

    if (/[A-Z]/.test(p)) {
score++;
}

    if (/[0-9]/.test(p)) {
score++;
}

    if (/[^A-Za-z0-9]/.test(p)) {
score++;
}

    if (score <= 1) {
return { score, label: 'Yếu', color: 'bg-red-500' };
}

    if (score <= 2) {
return { score, label: 'Trung bình', color: 'bg-amber-400' };
}

    if (score <= 3) {
return { score, label: 'Khá', color: 'bg-yellow-400' };
}

    return { score, label: 'Mạnh', color: 'bg-emerald-500' };
});
</script>

<template>
    <Head title="Đăng ký · Aventura" />

    <div class="min-h-dvh grid-cols-1 flex flex-col lg:grid lg:grid-cols-[1.1fr_2fr]">

        <!-- LEFT: Form with a rich subtle gradient and highly defined glassmorphic container -->
        <div class="relative flex min-h-dvh flex-col overflow-hidden bg-gradient-to-b from-zinc-50 via-white to-zinc-50 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950 px-6 py-10 sm:px-10 md:px-12">
            <!-- Decorative dynamic glowing elements (clearly visible) -->
            <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent" />
            <div class="pointer-events-none absolute -bottom-20 -left-20 h-[400px] w-[400px] rounded-full bg-emerald-50/15 blur-[120px] animate-pulse duration-[8s]" />
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

                    <div class="mb-4 inline-flex items-center gap-1.5 rounded-full border border-emerald-500/15 bg-emerald-500/[0.03] px-3.5 py-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400/80" />
                        Miễn phí 14 ngày
                    </div>
                    <h1 class="bg-gradient-to-r from-zinc-950 via-zinc-800 to-emerald-600 dark:from-white dark:via-zinc-200 dark:to-emerald-400 bg-clip-text text-3xl font-black tracking-tight text-transparent leading-none">Tạo tài khoản doanh nghiệp</h1>
                    <p class="mt-2.5 text-sm text-muted-foreground">
                        Điền thông tin bên dưới — hệ thống sẵn sàng trong dưới 3 giây.
                    </p>

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password', 'password_confirmation']"
                        v-slot="{ errors, processing }"
                        class="mt-6 space-y-4"
                    >
                        <input type="hidden" name="plan_code" :value="selectedPlan" />

                        <div class="grid gap-1.5">
                            <Label for="restaurant_name" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">Tên nhà hàng</Label>
                            <Input id="restaurant_name" type="text" name="restaurant_name"
                                required autofocus :tabindex="1"
                                autocomplete="organization" placeholder="Phở Việt, Quán Ăn 24h..."
                                class="rounded-xl border-zinc-200 dark:border-zinc-800 transition-all duration-300 hover:border-zinc-300 dark:hover:border-zinc-700 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500 shadow-sm" />
                            <InputError :message="errors.restaurant_name" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="name" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">Họ và tên chủ tài khoản</Label>
                            <Input id="name" type="text" name="name"
                                required :tabindex="2"
                                autocomplete="name" placeholder="Nguyễn Văn A"
                                class="rounded-xl border-zinc-200 dark:border-zinc-800 transition-all duration-300 hover:border-zinc-300 dark:hover:border-zinc-700 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500 shadow-sm" />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid grid-cols-2 gap-3.5">
                            <div class="grid gap-1.5">
                                <Label for="email" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">Email</Label>
                                <Input id="email" type="email" name="email"
                                    required :tabindex="3"
                                    autocomplete="email" placeholder="owner@example.com"
                                    class="rounded-xl border-zinc-200 dark:border-zinc-800 transition-all duration-300 hover:border-zinc-300 dark:hover:border-zinc-700 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500 shadow-sm" />
                                <InputError :message="errors.email" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="phone" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">Số điện thoại</Label>
                                <Input id="phone" type="tel" name="phone"
                                    :tabindex="4"
                                    autocomplete="tel" placeholder="0900 000 000"
                                    class="rounded-xl border-zinc-200 dark:border-zinc-800 transition-all duration-300 hover:border-zinc-300 dark:hover:border-zinc-700 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500 shadow-sm" />
                                <InputError :message="errors.phone" />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="password" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">Mật khẩu</Label>
                            <PasswordInput id="password" name="password"
                                :modelValue="passwordValue"
                                @update:modelValue="passwordValue = String($event)"
                                required :tabindex="5"
                                autocomplete="new-password" placeholder="Tối thiểu 8 ký tự"
                                :passwordrules="passwordRules"
                                class="rounded-xl border-zinc-200 dark:border-zinc-800 transition-all duration-300 hover:border-zinc-300 dark:hover:border-zinc-700 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500 shadow-sm" />
                            
                            <!-- Segmented Glowing Password Strength Indicator -->
                            <div v-if="passwordValue" class="mt-1.5 space-y-1.5 animate-in fade-in slide-in-from-top-1 duration-300">
                                <div class="flex gap-1.5 h-1 w-full">
                                    <div
                                        v-for="step in 5"
                                        :key="step"
                                        class="h-full flex-1 rounded-full transition-all duration-300"
                                        :class="[
                                            step <= passwordStrength.score 
                                                ? passwordStrength.color + ' opacity-100'
                                                : 'bg-zinc-200 dark:bg-zinc-800 opacity-80'
                                        ]"
                                        :style="step <= passwordStrength.score ? { boxShadow: passwordStrength.score <= 1 ? '0 0 6px rgba(239,68,68,0.5)' : passwordStrength.score === 2 ? '0 0 6px rgba(251,191,36,0.5)' : passwordStrength.score === 3 ? '0 0 6px rgba(234,179,8,0.5)' : '0 0 6px rgba(16,185,129,0.5)' } : {}"
                                    />
                                </div>
                                <p class="text-[11px] font-bold tracking-wide transition-colors duration-300" :class="{
                                    'text-red-500': passwordStrength.score <= 1,
                                    'text-amber-500': passwordStrength.score === 2,
                                    'text-yellow-600': passwordStrength.score === 3,
                                    'text-emerald-500': passwordStrength.score >= 4,
                                }">Trạng thái: {{ passwordStrength.label }}</p>
                            </div>
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="password_confirmation" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">Xác nhận mật khẩu</Label>
                            <PasswordInput id="password_confirmation" name="password_confirmation"
                                required :tabindex="6"
                                autocomplete="new-password" placeholder="Nhập lại mật khẩu"
                                :passwordrules="passwordRules"
                                class="rounded-xl border-zinc-200 dark:border-zinc-800 transition-all duration-300 hover:border-zinc-300 dark:hover:border-zinc-700 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500 shadow-sm" />
                            <InputError :message="errors.password_confirmation" />
                        </div>

                        <Button type="submit" class="w-full rounded-xl py-6 font-black uppercase tracking-wider text-xs bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white transition-all duration-300 shadow-[0_4px_20px_rgba(16,185,129,0.3)] hover:shadow-[0_4px_25px_rgba(16,185,129,0.45)] hover:-translate-y-0.5 active:scale-[0.98] border-none mt-2 cursor-pointer" :tabindex="7" :disabled="processing">
                            <Spinner v-if="processing" class="text-white" />
                            {{ processing ? 'Đang khởi tạo hệ thống...' : 'Tạo doanh nghiệp ngay' }}
                        </Button>
                    </Form>
                </div>
            </div>

            <!-- Footer (bottom) -->
            <p class="text-center text-sm text-muted-foreground mt-6 relative z-10">
                Đã có tài khoản?
                <TextLink :href="login()" :tabindex="8" class="font-bold text-emerald-600 dark:text-emerald-400 underline underline-offset-4 hover:text-emerald-500 transition-colors">
                    Đăng nhập
                </TextLink>
            </p>
        </div>

        <!-- RIGHT: Plans panel (desktop only) -->
        <div class="relative hidden flex-col overflow-hidden border-l border-zinc-900 bg-zinc-950 px-12 py-10 lg:flex lg:min-h-dvh justify-between">
            <!-- Dot-grid dynamic background -->
            <svg class="pointer-events-none absolute inset-0 size-full opacity-[0.06]" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="dots-register-panel" x="0" y="0" width="24" height="24" patternUnits="userSpaceOnUse">
                        <circle cx="1.5" cy="1.5" r="1.5" fill="white" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#dots-register-panel)" />
            </svg>
            <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(ellipse_80%_60%_at_50%_0%,rgba(255,255,255,0.08),transparent)]" />

            <!-- Ambient light blob that floats in the dark section (vividly visible) -->
            <div class="pointer-events-none absolute top-1/4 left-1/3 h-[500px] w-[500px] rounded-full bg-emerald-500/[0.12] blur-[130px] z-0 animate-pulse duration-[8s]" />
            <div class="pointer-events-none absolute bottom-1/4 right-1/4 h-[450px] w-[450px] rounded-full bg-violet-500/[0.10] blur-[120px] z-0 animate-pulse duration-[10s]" />

            <!-- Header (top) -->
            <div class="relative z-10">
                <div class="mb-3.5 inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3.5 py-1 text-xs text-zinc-400 shadow-sm backdrop-blur-md">
                    <svg class="size-3 text-emerald-400 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    Chọn gói để bắt đầu
                </div>
                <h2 class="bg-gradient-to-br from-white to-zinc-400 bg-clip-text text-3xl font-black tracking-tight text-transparent">14 ngày dùng thử miễn phí</h2>
                <p class="mt-2 text-sm text-zinc-500 max-w-md leading-relaxed">
                    Nếu không thanh toán, tài khoản sẽ tự động chuyển về gói
                    <span class="font-black text-zinc-300">Free</span> — hoàn toàn không mất gì cả.
                </p>
            </div>

            <!-- Plans (middle — flex-1 centered) -->
            <div class="relative z-10 flex flex-1 flex-col justify-center my-6">
                <div class="animate-in fade-in slide-in-from-bottom-4 duration-700 fill-mode-both w-full">
                    <div class="grid grid-cols-4 gap-3">
                        <PlanCard
                            v-for="plan in plans"
                            :key="plan.code"
                            :plan="plan"
                            :selected="selectedPlan === plan.code"
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
                                    · Sau khi đăng ký thành công bạn sẽ được tự động chuyển đến trang thanh toán.
                                </template>
                                <template v-else>
                                    · Gói cơ bản hoàn toàn miễn phí, dễ dàng nâng cấp bất cứ lúc nào.
                                </template>
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer (bottom) -->
            <p class="relative z-10 text-center text-xs text-zinc-650 tracking-wide">
                Không ràng buộc hợp đồng · Hủy bất cứ lúc nào · Hỗ trợ khách hàng 24/7
            </p>
        </div>

    </div>
</template>
