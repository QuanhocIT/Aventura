<script setup lang="ts">
import { ref } from 'vue';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home, register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    plans: Plan[];
}>();

const selectedPlan = ref('free');
</script>

<template>
    <Head title="Đăng nhập · Aventura" />

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

                    <div class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/5 px-3 py-1 text-xs font-medium text-primary/80">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-primary/70" />
                        Đăng nhập an toàn
                    </div>
                    <h1 class="bg-gradient-to-br from-foreground to-foreground/60 bg-clip-text text-2xl font-bold tracking-tight text-transparent">Chào mừng trở lại</h1>
                    <p class="mt-1.5 text-sm text-muted-foreground">
                        Nhập thông tin để đăng nhập vào hệ thống.
                    </p>

                    <div v-if="status" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400">
                        {{ status }}
                    </div>

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="mt-6 space-y-4"
                    >
                        <input type="hidden" name="plan_code" :value="selectedPlan" />

                        <div class="grid gap-1.5">
                            <Label for="email">Địa chỉ email</Label>
                            <Input id="email" type="email" name="email" required autofocus
                                :tabindex="1" autocomplete="email" placeholder="email@example.com" />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-1.5">
                            <div class="flex items-center justify-between">
                                <Label for="password">Mật khẩu</Label>
                                <TextLink v-if="canResetPassword" :href="request()" class="text-xs" :tabindex="5">
                                    Quên mật khẩu?
                                </TextLink>
                            </div>
                            <PasswordInput id="password" name="password" required
                                :tabindex="2" autocomplete="current-password" placeholder="Mật khẩu" />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox id="remember" name="remember" :tabindex="3" />
                            <Label for="remember" class="cursor-pointer text-sm font-normal">
                                Ghi nhớ đăng nhập
                            </Label>
                        </div>

                        <Button type="submit" class="w-full" :tabindex="4" :disabled="processing">
                            <Spinner v-if="processing" />
                            {{ processing ? 'Đang đăng nhập...' : 'Đăng nhập' }}
                        </Button>
                    </Form>

                    <div class="relative my-4 flex items-center gap-3">
                        <div class="h-px flex-1 bg-border" />
                        <span class="text-xs text-muted-foreground">Hoặc</span>
                        <div class="h-px flex-1 bg-border" />
                    </div>

                    <Button as="a" href="/auth/google" variant="outline" class="w-full">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="size-4 shrink-0">
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
            <p v-if="canRegister" class="text-center text-sm text-muted-foreground">
                Chưa có tài khoản?
                <TextLink :href="register()" :tabindex="6" class="underline underline-offset-4">
                    Đăng ký miễn phí
                </TextLink>
            </p>
        </div>

        <!-- RIGHT: Plans panel (desktop only) -->
        <div class="relative hidden flex-col overflow-hidden border-l border-border bg-zinc-950 px-10 py-10 lg:flex lg:min-h-dvh">
            <!-- Dot-grid -->
            <svg class="pointer-events-none absolute inset-0 size-full opacity-[0.05]" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="dots-login-panel" x="0" y="0" width="24" height="24" patternUnits="userSpaceOnUse">
                        <circle cx="1.5" cy="1.5" r="1.5" fill="white" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#dots-login-panel)" />
            </svg>
            <div class="pointer-events-none absolute inset-x-0 top-0 h-48 bg-[radial-gradient(ellipse_80%_100%_at_50%_0%,rgba(255,255,255,0.05),transparent)]" />

            <!-- Header (top) -->
            <div class="relative z-10">
                <div class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-zinc-400">
                    <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    Chọn gói phù hợp
                </div>
                <h2 class="bg-gradient-to-br from-white to-zinc-400 bg-clip-text text-xl font-bold text-transparent">Nâng cấp bất cứ lúc nào</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    Chọn gói rồi đăng nhập — hệ thống chuyển bạn đến trang thanh toán ngay.
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
                                · Sau đăng nhập sẽ chuyển đến trang thanh toán.
                            </template>
                            <template v-else>
                                · Gói miễn phí, nâng cấp bất cứ lúc nào.
                            </template>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer (bottom) -->
            <p class="relative z-10 text-center text-xs text-zinc-700">
                14 ngày dùng thử miễn phí · Không ràng buộc hợp đồng · Hủy bất cứ lúc nào
            </p>
        </div>

    </div>
</template>
