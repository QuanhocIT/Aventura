<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
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

    <div
        class="flex min-h-dvh grid-cols-1 flex-col lg:grid lg:grid-cols-[1.1fr_2fr]"
    >
        <!-- LEFT: Form with a rich subtle gradient and highly defined glassmorphic container -->
        <div
            class="relative flex min-h-dvh flex-col overflow-hidden bg-gradient-to-b from-zinc-50 via-white to-zinc-50 px-6 py-10 sm:px-10 md:px-12 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950"
        >
            <!-- Decorative dynamic glowing elements (clearly visible) -->
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent"
            />
            <div
                class="pointer-events-none absolute -bottom-20 -left-20 h-[400px] w-[400px] animate-pulse rounded-full bg-emerald-500/15 blur-[120px] duration-[8s]"
            />
            <div
                class="pointer-events-none absolute top-1/4 -right-20 h-[350px] w-[350px] animate-pulse rounded-full bg-blue-500/10 blur-[100px] duration-[6s]"
            />

            <!-- Logo (top) -->
            <Link
                :href="home()"
                class="flex w-fit items-center gap-2.5 transition-transform duration-300 hover:scale-[1.02]"
            >
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary shadow-md"
                >
                    <AppLogoIcon
                        class="size-5 fill-current text-primary-foreground"
                    />
                </span>
                <span
                    class="to-zinc-650 bg-gradient-to-r from-zinc-900 bg-clip-text text-lg font-black tracking-tight text-transparent transition-colors hover:from-emerald-600 hover:to-teal-500 dark:from-white dark:to-zinc-300"
                    >Aventura</span
                >
            </Link>

            <!-- Form (middle — brought up to eliminate whitespace) -->
            <div
                class="relative z-10 flex flex-1 flex-col justify-start pt-6 sm:pt-8"
            >
                <div
                    class="mx-auto w-full max-w-md animate-in rounded-3xl border border-white/80 bg-white/70 p-8 shadow-[0_20px_50px_rgba(0,0,0,0.06)] backdrop-blur-xl duration-500 fill-mode-both fade-in slide-in-from-bottom-6 sm:p-10 dark:border-zinc-800/80 dark:bg-zinc-950/70 dark:shadow-[0_20px_50px_rgba(0,0,0,0.3)]"
                >
                    <div
                        class="mb-4 inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/5 px-4 py-1 text-xs font-bold text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"
                    >
                        <span
                            class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500/80"
                        />
                        Đăng nhập an toàn
                    </div>
                    <h1
                        class="bg-gradient-to-r from-zinc-950 via-zinc-800 to-emerald-600 bg-clip-text text-3xl leading-none font-black tracking-tight text-transparent dark:from-white dark:via-zinc-200 dark:to-emerald-400"
                    >
                        Chào mừng trở lại
                    </h1>
                    <p class="mt-2.5 text-sm text-muted-foreground">
                        Nhập thông tin để đăng nhập vào hệ thống.
                    </p>

                    <div
                        v-if="status"
                        class="mt-4 animate-in rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 shadow-sm duration-300 fade-in slide-in-from-top-2 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400"
                    >
                        {{ status }}
                    </div>

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="mt-6 space-y-5"
                    >
                        <input
                            type="hidden"
                            name="plan_code"
                            :value="selectedPlan"
                        />

                        <div class="grid gap-1.5">
                            <Label
                                for="email"
                                class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                                >Địa chỉ email</Label
                            >
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autofocus
                                :tabindex="1"
                                autocomplete="email"
                                placeholder="email@example.com"
                                class="rounded-xl border-zinc-200 shadow-sm transition-all duration-300 hover:border-zinc-300 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20 dark:border-zinc-800 dark:hover:border-zinc-700"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-1.5">
                            <div class="flex items-center justify-between">
                                <Label
                                    for="password"
                                    class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                                    >Mật khẩu</Label
                                >
                                <TextLink
                                    v-if="canResetPassword"
                                    :href="request()"
                                    class="text-xs font-medium text-muted-foreground transition-colors duration-250 hover:text-emerald-600 dark:hover:text-emerald-400"
                                    :tabindex="5"
                                >
                                    Quên mật khẩu?
                                </TextLink>
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                :tabindex="2"
                                autocomplete="current-password"
                                placeholder="Mật khẩu"
                                class="rounded-xl border-zinc-200 shadow-sm transition-all duration-300 hover:border-zinc-300 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20 dark:border-zinc-800 dark:hover:border-zinc-700"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="flex items-center gap-2.5 py-1">
                            <Checkbox
                                id="remember"
                                name="remember"
                                :tabindex="3"
                                class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500/10 dark:border-zinc-700"
                            />
                            <Label
                                for="remember"
                                class="cursor-pointer text-sm font-semibold text-muted-foreground transition-colors duration-200 select-none hover:text-foreground"
                            >
                                Ghi nhớ đăng nhập
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            class="w-full cursor-pointer rounded-xl border-none bg-gradient-to-r from-emerald-600 to-teal-500 py-6 text-xs font-black tracking-wider text-white uppercase shadow-[0_4px_20px_rgba(16,185,129,0.3)] transition-all duration-300 hover:-translate-y-0.5 hover:from-emerald-500 hover:to-teal-400 hover:shadow-[0_4px_25px_rgba(16,185,129,0.45)] active:scale-[0.98]"
                            :tabindex="4"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" class="text-white" />
                            {{ processing ? 'Đang đăng nhập...' : 'Đăng nhập' }}
                        </Button>
                    </Form>

                    <div class="relative my-6 flex items-center gap-3">
                        <div
                            class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800/80"
                        />
                        <span
                            class="text-[10px] font-black tracking-widest text-muted-foreground/60 uppercase"
                            >Hoặc</span
                        >
                        <div
                            class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800/80"
                        />
                    </div>

                    <Button
                        as="a"
                        href="/auth/google"
                        variant="outline"
                        class="w-full cursor-pointer rounded-xl border-zinc-200 py-5 font-bold shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:bg-zinc-50 hover:shadow active:scale-[0.98] dark:border-zinc-800 dark:hover:bg-zinc-900"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 48 48"
                            class="mr-2 size-4 shrink-0 transition-transform duration-300"
                        >
                            <path
                                fill="#EA4335"
                                d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"
                            />
                            <path
                                fill="#4285F4"
                                d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"
                            />
                            <path
                                fill="#FBBC05"
                                d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"
                            />
                            <path
                                fill="#34A853"
                                d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"
                            />
                            <path fill="none" d="M0 0h48v48H0z" />
                        </svg>
                        Đăng nhập bằng Google
                    </Button>
                </div>
            </div>

            <!-- Footer (bottom) -->
            <p
                v-if="canRegister"
                class="relative z-10 mt-6 text-center text-sm text-muted-foreground"
            >
                Chưa có tài khoản?
                <TextLink
                    :href="register()"
                    :tabindex="6"
                    class="font-bold text-emerald-600 underline underline-offset-4 transition-colors hover:text-emerald-500 dark:text-emerald-400"
                >
                    Đăng ký miễn phí
                </TextLink>
            </p>
        </div>

        <!-- RIGHT: Plans panel (desktop only) -->
        <div
            class="relative hidden flex-col justify-between overflow-hidden border-l border-zinc-900 bg-zinc-950 px-12 py-10 lg:flex lg:min-h-dvh"
        >
            <!-- Dot-grid dynamic background -->
            <svg
                class="pointer-events-none absolute inset-0 size-full opacity-[0.06]"
                xmlns="http://www.w3.org/2000/svg"
            >
                <defs>
                    <pattern
                        id="dots-login-panel"
                        x="0"
                        y="0"
                        width="24"
                        height="24"
                        patternUnits="userSpaceOnUse"
                    >
                        <circle cx="1.5" cy="1.5" r="1.5" fill="white" />
                    </pattern>
                </defs>
                <rect
                    width="100%"
                    height="100%"
                    fill="url(#dots-login-panel)"
                />
            </svg>
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(ellipse_80%_60%_at_50%_0%,rgba(255,255,255,0.08),transparent)]"
            />

            <!-- Ambient light blob that floats in the dark section (vividly visible) -->
            <div
                class="pointer-events-none absolute top-1/4 left-1/3 z-0 h-[500px] w-[500px] animate-pulse rounded-full bg-emerald-500/[0.12] blur-[130px] duration-[8s]"
            />
            <div
                class="pointer-events-none absolute right-1/4 bottom-1/4 z-0 h-[450px] w-[450px] animate-pulse rounded-full bg-teal-500/[0.10] blur-[120px] duration-[10s]"
            />

            <!-- Header (top) -->
            <div class="relative z-10">
                <div
                    class="mb-3.5 inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3.5 py-1 text-xs text-zinc-400 shadow-sm backdrop-blur-md"
                >
                    <svg
                        class="size-3 animate-pulse text-emerald-400"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
                        <path
                            d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"
                        />
                    </svg>
                    Chọn gói phù hợp
                </div>
                <h2
                    class="bg-gradient-to-br from-white to-zinc-400 bg-clip-text text-3xl font-black tracking-tight text-transparent"
                >
                    Nâng cấp bất cứ lúc nào
                </h2>
                <p class="mt-2 max-w-md text-sm leading-relaxed text-zinc-500">
                    Chọn gói rồi đăng nhập — hệ thống chuyển bạn đến trang thanh
                    toán ngay.
                </p>
            </div>

            <!-- Plans (middle — flex-1 centered) -->
            <div class="relative z-10 my-6 flex flex-1 flex-col justify-center">
                <div
                    class="w-full animate-in duration-700 fill-mode-both fade-in slide-in-from-bottom-4"
                >
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
                    <div
                        class="mt-5 rounded-2xl border border-white/[0.08] bg-white/[0.04] px-5 py-4 shadow-md backdrop-blur-md transition-all duration-300"
                    >
                        <p
                            class="flex flex-wrap items-center gap-1.5 text-xs leading-relaxed text-zinc-400"
                        >
                            <span
                                class="inline-flex h-2 w-2 shrink-0 animate-pulse items-center justify-center rounded-full bg-emerald-500"
                            />
                            <span class="font-bold text-zinc-200"
                                >Đang chọn:</span
                            >
                            <span
                                class="rounded-md border border-white/20 bg-white/10 px-2 py-0.5 font-black text-white shadow-inner"
                            >
                                {{
                                    plans.find((p) => p.code === selectedPlan)
                                        ?.name ?? 'Free'
                                }}
                            </span>
                            <span class="text-zinc-500">
                                <template
                                    v-if="
                                        Number(
                                            plans.find(
                                                (p) => p.code === selectedPlan,
                                            )?.price ?? 0,
                                        ) > 0
                                    "
                                >
                                    · Sau đăng nhập sẽ tự động chuyển đến trang
                                    thanh toán.
                                </template>
                                <template v-else>
                                    · Gói trải nghiệm miễn phí, dễ dàng nâng cấp
                                    bất kỳ lúc nào.
                                </template>
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer (bottom) -->
            <p
                class="text-zinc-650 relative z-10 text-center text-xs tracking-wide"
            >
                14 ngày dùng thử miễn phí · Hủy bất cứ lúc nào · Không phí ẩn
            </p>
        </div>
    </div>
</template>
