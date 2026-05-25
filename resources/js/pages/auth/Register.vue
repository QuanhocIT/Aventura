<script setup lang="ts">
import { computed, ref } from 'vue';
import { Form, Head, Link } from '@inertiajs/vue3';
// @ts-ignore
import AppLogoIcon from '@/components/AppLogoIcon.vue';
// @ts-ignore
import InputError from '@/components/InputError.vue';
// @ts-ignore
import PasswordInput from '@/components/PasswordInput.vue';
// @ts-ignore
import TextLink from '@/components/TextLink.vue';
// @ts-ignore
import PlanCard from '@/components/PlanCard.vue';
import type { Plan } from '@/components/PlanCard.vue';
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
    if (!p) return { score: 0, label: '', color: '' };
    let score = 0;
    if (p.length >= 8) score++;
    if (p.length >= 12) score++;
    if (/[A-Z]/.test(p)) score++;
    if (/[0-9]/.test(p)) score++;
    if (/[^A-Za-z0-9]/.test(p)) score++;
    if (score <= 1) return { score, label: 'Yếu', color: 'bg-red-500' };
    if (score <= 2) return { score, label: 'Trung bình', color: 'bg-amber-400' };
    if (score <= 3) return { score, label: 'Khá', color: 'bg-yellow-400' };
    return { score, label: 'Mạnh', color: 'bg-emerald-500' };
});
</script>

<template>
    <Head title="Đăng ký · Aventura" />

    <div class="min-h-dvh grid-cols-1 flex flex-col lg:grid lg:grid-cols-[1fr_2fr]">

        <!-- LEFT: Form -->
        <div class="relative flex min-h-dvh flex-col overflow-hidden bg-background px-10 py-10">
            <!-- Decorative elements -->
            <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent" />
            <div class="pointer-events-none absolute -bottom-32 -left-24 h-64 w-64 rounded-full bg-primary/[0.07] blur-3xl" />
            <div class="pointer-events-none absolute -right-16 top-1/3 h-48 w-48 rounded-full bg-primary/[0.04] blur-3xl" />

            <!-- Logo (top) -->
            <Link :href="home()" class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary">
                    <AppLogoIcon class="size-5 fill-current text-primary-foreground" />
                </span>
                <span class="text-base font-semibold tracking-tight">Aventura</span>
            </Link>

            <!-- Form (middle — flex-1 centered) -->
            <div class="flex flex-1 flex-col justify-center">
                <div class="animate-in fade-in slide-in-from-bottom-4 duration-500 fill-mode-both w-full max-w-sm mx-auto">

                    <div class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/5 px-3 py-1 text-xs font-medium text-emerald-400/80">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400/70" />
                        Miễn phí 14 ngày
                    </div>
                    <h1 class="bg-gradient-to-br from-foreground to-foreground/60 bg-clip-text text-2xl font-bold tracking-tight text-transparent">Tạo tài khoản doanh nghiệp</h1>
                    <p class="mt-1.5 text-sm text-muted-foreground">
                        Điền thông tin bên dưới — hệ thống sẵn sàng trong dưới 3 giây.
                    </p>

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password', 'password_confirmation']"
                        v-slot="{ errors, processing }"
                        class="mt-6 space-y-3.5"
                    >
                        <input type="hidden" name="plan_code" :value="selectedPlan" />

                        <div class="grid gap-1.5">
                            <Label for="restaurant_name">Tên nhà hàng</Label>
                            <Input id="restaurant_name" type="text" name="restaurant_name"
                                required autofocus :tabindex="1"
                                autocomplete="organization" placeholder="Phở Việt, Quán Ăn 24h..." />
                            <InputError :message="errors.restaurant_name" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="name">Họ và tên chủ tài khoản</Label>
                            <Input id="name" type="text" name="name"
                                required :tabindex="2"
                                autocomplete="name" placeholder="Nguyễn Văn A" />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="email">Email</Label>
                                <Input id="email" type="email" name="email"
                                    required :tabindex="3"
                                    autocomplete="email" placeholder="owner@example.com" />
                                <InputError :message="errors.email" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="phone">Số điện thoại</Label>
                                <Input id="phone" type="tel" name="phone"
                                    :tabindex="4"
                                    autocomplete="tel" placeholder="0900 000 000" />
                                <InputError :message="errors.phone" />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="password">Mật khẩu</Label>
                            <PasswordInput id="password" name="password"
                                :modelValue="passwordValue"
                                @update:modelValue="passwordValue = String($event)"
                                required :tabindex="5"
                                autocomplete="new-password" placeholder="Tối thiểu 8 ký tự"
                                :passwordrules="passwordRules" />
                            <div v-if="passwordValue" class="mt-0.5 space-y-1">
                                <div class="h-1 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full transition-all duration-300"
                                        :class="passwordStrength.color"
                                        :style="{ width: `${(passwordStrength.score / 5) * 100}%` }"
                                    />
                                </div>
                                <p class="text-xs" :class="{
                                    'text-red-500': passwordStrength.score <= 1,
                                    'text-amber-500': passwordStrength.score === 2,
                                    'text-yellow-600': passwordStrength.score === 3,
                                    'text-emerald-600': passwordStrength.score >= 4,
                                }">{{ passwordStrength.label }}</p>
                            </div>
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="password_confirmation">Xác nhận mật khẩu</Label>
                            <PasswordInput id="password_confirmation" name="password_confirmation"
                                required :tabindex="6"
                                autocomplete="new-password" placeholder="Nhập lại mật khẩu"
                                :passwordrules="passwordRules" />
                            <InputError :message="errors.password_confirmation" />
                        </div>

                        <Button type="submit" class="w-full" :tabindex="7" :disabled="processing">
                            <Spinner v-if="processing" />
                            {{ processing ? 'Đang khởi tạo hệ thống...' : 'Tạo doanh nghiệp ngay' }}
                        </Button>
                    </Form>
                </div>
            </div>

            <!-- Footer (bottom) -->
            <p class="text-center text-sm text-muted-foreground">
                Đã có tài khoản?
                <TextLink :href="login()" :tabindex="8" class="underline underline-offset-4">
                    Đăng nhập
                </TextLink>
            </p>
        </div>

        <!-- RIGHT: Plans panel (desktop only) -->
        <div class="relative hidden flex-col overflow-hidden border-l border-border bg-zinc-950 px-10 py-10 lg:flex lg:min-h-dvh">
            <!-- Dot-grid -->
            <svg class="pointer-events-none absolute inset-0 size-full opacity-[0.05]" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="dots-register-panel" x="0" y="0" width="24" height="24" patternUnits="userSpaceOnUse">
                        <circle cx="1.5" cy="1.5" r="1.5" fill="white" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#dots-register-panel)" />
            </svg>
            <div class="pointer-events-none absolute inset-x-0 top-0 h-48 bg-[radial-gradient(ellipse_80%_100%_at_50%_0%,rgba(255,255,255,0.05),transparent)]" />

            <!-- Header (top) -->
            <div class="relative z-10">
                <div class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-zinc-400">
                    <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    Chọn gói để bắt đầu
                </div>
                <h2 class="bg-gradient-to-br from-white to-zinc-400 bg-clip-text text-xl font-bold text-transparent">14 ngày dùng thử miễn phí</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    Nếu không thanh toán, tài khoản về gói
                    <span class="font-medium text-zinc-300">Free</span> — không mất gì cả.
                </p>
            </div>

            <!-- Plans (middle — flex-1 centered) -->
            <div class="relative z-10 flex flex-1 flex-col justify-center">
                <div class="animate-in fade-in slide-in-from-bottom-4 duration-700 fill-mode-both w-full">
                    <div class="grid grid-cols-2 gap-3">
                        <PlanCard
                            v-for="plan in plans"
                            :key="plan.code"
                            :plan="plan"
                            :selected="selectedPlan === plan.code"
                            @select="selectedPlan = $event"
                        />
                    </div>

                    <!-- Selected hint -->
                    <div class="mt-3 rounded-xl border border-white/[0.07] bg-white/[0.03] px-4 py-2.5">
                        <p class="text-xs text-zinc-500">
                            Đang chọn:
                            <span class="font-semibold text-zinc-200">
                                {{ plans.find(p => p.code === selectedPlan)?.name ?? 'Free' }}
                            </span>
                            <template v-if="Number(plans.find(p => p.code === selectedPlan)?.price ?? 0) > 0">
                                · Sau đăng ký bạn sẽ được chuyển đến trang thanh toán.
                            </template>
                            <template v-else>
                                · Hoàn toàn miễn phí, nâng cấp bất cứ lúc nào.
                            </template>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer (bottom) -->
            <p class="relative z-10 text-center text-xs text-zinc-700">
                Không ràng buộc hợp đồng · Hủy bất cứ lúc nào · Hỗ trợ 24/7
            </p>
        </div>

    </div>
</template>
