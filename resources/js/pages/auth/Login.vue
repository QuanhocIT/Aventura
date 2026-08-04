<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
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
    failedAttemptsCount: number;
    turnstileSiteKey?: string;
    captchaQuestion?: string;
    captchaToken?: string;
}>();

const urlParams =
    typeof window !== 'undefined'
        ? new URLSearchParams(window.location.search)
        : null;
const selectedPlan = ref(urlParams?.get('plan') || 'free');
const selectedCycle = ref(urlParams?.get('cycle') || 'monthly');
const turnstileToken = ref('');

onMounted(() => {
    if (props.failedAttemptsCount >= 3 && props.turnstileSiteKey) {
        // @ts-ignore
        if (!window.turnstile) {
            const script = document.createElement('script');
            script.src =
                'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadTurnstileCallback';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);

            // @ts-ignore
            window.onloadTurnstileCallback = () => {
                // @ts-ignore
                window.turnstile.render('#turnstile-container', {
                    sitekey: props.turnstileSiteKey,
                    callback: (token: string) => {
                        turnstileToken.value = token;
                    },
                });
            };
        } else {
            setTimeout(() => {
                // @ts-ignore
                window.turnstile.render('#turnstile-container', {
                    sitekey: props.turnstileSiteKey,
                    callback: (token: string) => {
                        turnstileToken.value = token;
                    },
                });
            }, 100);
        }
    }
});

const maxDiscountPercent = computed(() => {
    if (!props.plans?.length) {
        return 20;
    }

    const percentages = props.plans.map((p: Plan) =>
        p.features?.yearly_discount_percent !== undefined
            ? Number(p.features.yearly_discount_percent)
            : 20,
    );

    return Math.max(...percentages);
});
</script>

<template>
    <Head title="Đăng nhập · Aventura" />

    <div
        class="flex min-h-dvh grid-cols-1 flex-col lg:grid lg:grid-cols-[1.1fr_2fr]"
    >
        <Link
            :href="home()"
            class="fixed top-4 right-4 z-50 inline-flex items-center gap-1.5 rounded-full border border-white/25 bg-zinc-950/35 px-3.5 py-2 text-xs font-semibold text-white shadow-lg backdrop-blur-md transition-all hover:-translate-y-0.5 hover:bg-zinc-950/55 sm:top-6 sm:right-6"
        >
            <span aria-hidden="true">←</span>
            Về trang chủ
        </Link>

        <!-- LEFT: Form with a rich subtle gradient and highly defined glassmorphic container -->
        <div
            class="relative flex min-h-dvh flex-col overflow-hidden bg-gradient-to-b from-zinc-50 via-white to-zinc-50 px-4 py-6 sm:px-10 sm:py-10 md:px-12 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950"
        >
            <!-- Premium gradient blobs -->
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary/30 to-transparent"
            />
            <div
                class="float-glow pointer-events-none absolute -bottom-32 -left-32 h-[450px] w-[450px] rounded-full bg-primary/15 blur-[120px]"
            />
            <div
                class="float-glow pointer-events-none absolute top-1/4 -right-20 h-[350px] w-[350px] rounded-full bg-violet-500/10 blur-[100px]"
                style="animation-delay: 4s"
            />
            <div
                class="float-glow pointer-events-none absolute top-3/4 right-1/3 h-[200px] w-[200px] rounded-full bg-rose-500/8 blur-[80px]"
                style="animation-delay: 6s"
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
                    class="to-zinc-650 bg-gradient-to-r from-zinc-900 bg-clip-text text-lg font-black tracking-tight text-transparent dark:from-white dark:to-zinc-300"
                    >Aventura</span
                >
            </Link>

            <!-- Form (middle — brought up to eliminate whitespace) -->
            <div
                class="relative z-10 flex flex-1 flex-col justify-start pt-5 sm:pt-8"
            >
                <div
                    class="focus-glow-card mx-auto w-full max-w-md animate-in rounded-2xl border border-white/40 bg-white/75 p-5 shadow-[0_24px_64px_rgba(0,0,0,0.06),0_8px_20px_rgba(0,0,0,0.03)] backdrop-blur-2xl transition-all duration-500 fill-mode-both fade-in slide-in-from-bottom-6 sm:rounded-3xl sm:p-10 dark:border-white/[0.08] dark:bg-zinc-950/65 dark:shadow-[0_24px_64px_rgba(0,0,0,0.35),0_8px_20px_rgba(0,0,0,0.2)]"
                >
                    <div
                        class="mb-4 inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/5 px-4 py-1 text-xs font-bold text-primary dark:bg-primary/10 dark:text-primary"
                    >
                        <span
                            class="bg-primary/50/80 h-1.5 w-1.5 animate-pulse rounded-full"
                        />
                        Đăng nhập an toàn
                    </div>
                    <h1
                        class="bg-gradient-to-r from-zinc-950 via-zinc-800 to-primary bg-clip-text text-2xl leading-tight font-black tracking-tight text-transparent sm:text-3xl sm:leading-none dark:from-white dark:via-zinc-200 dark:to-primary"
                    >
                        Chào mừng trở lại
                    </h1>
                    <p class="mt-2.5 text-sm text-muted-foreground">
                        Nhập thông tin để đăng nhập vào hệ thống.
                    </p>

                    <!-- Mobile plan selector: the desktop panel is hidden below lg -->
                    <div v-if="plans?.length" class="mt-5 lg:hidden">
                        <div
                            class="mb-2.5 flex items-center justify-between gap-2"
                        >
                            <span
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Chọn gói dịch vụ
                            </span>
                            <span
                                class="truncate text-xs font-semibold text-primary"
                            >
                                {{
                                    plans.find((p) => p.code === selectedPlan)
                                        ?.name ?? 'Free'
                                }}
                            </span>
                        </div>

                        <div
                            class="mb-2 flex w-full rounded-xl border border-white/10 bg-white/5 p-1 text-xs backdrop-blur-md"
                        >
                            <button
                                type="button"
                                @click="selectedCycle = 'monthly'"
                                class="flex-1 rounded-lg px-2 py-2 font-semibold transition-colors"
                                :class="
                                    selectedCycle === 'monthly'
                                        ? 'bg-zinc-800 text-white shadow-sm'
                                        : 'text-zinc-400 hover:text-white'
                                "
                            >
                                Theo tháng
                            </button>
                            <button
                                type="button"
                                @click="selectedCycle = 'yearly'"
                                class="flex-1 rounded-lg px-2 py-2 font-semibold transition-colors"
                                :class="
                                    selectedCycle === 'yearly'
                                        ? 'bg-zinc-800 text-white shadow-sm'
                                        : 'text-zinc-400 hover:text-white'
                                "
                            >
                                Theo năm
                                <span class="ml-0.5 text-primary"
                                    >-{{ maxDiscountPercent }}%</span
                                >
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <PlanCard
                                v-for="plan in plans"
                                :key="`mobile-${plan.code}`"
                                :plan="plan"
                                compact
                                :selected="selectedPlan === plan.code"
                                :billing-cycle="
                                    selectedCycle === 'yearly'
                                        ? 'yearly'
                                        : 'monthly'
                                "
                                @select="selectedPlan = $event"
                            />
                        </div>
                    </div>

                    <div
                        v-if="status"
                        class="mt-4 animate-in rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm font-semibold text-primary shadow-sm duration-300 fade-in slide-in-from-top-2 dark:border-primary/20 dark:bg-primary/10 dark:text-primary"
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
                        <input
                            type="hidden"
                            name="cycle"
                            :value="selectedCycle"
                        />

                        <div class="animate-enter stagger-1 grid gap-1.5">
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
                                class="rounded-xl border-zinc-200 shadow-sm transition-all duration-300 hover:border-zinc-300 focus-visible:border-primary focus-visible:ring-primary/20 dark:border-zinc-800 dark:hover:border-zinc-700"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="animate-enter stagger-2 grid gap-1.5">
                            <div class="flex items-center justify-between">
                                <Label
                                    for="password"
                                    class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                                    >Mật khẩu</Label
                                >
                                <TextLink
                                    v-if="canResetPassword"
                                    :href="request()"
                                    class="text-xs font-medium text-muted-foreground transition-colors duration-250 hover:text-primary dark:hover:text-primary"
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
                                class="rounded-xl border-zinc-200 shadow-sm transition-all duration-300 hover:border-zinc-300 focus-visible:border-primary focus-visible:ring-primary/20 dark:border-zinc-800 dark:hover:border-zinc-700"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <!-- CAPTCHA block if failed login attempts >= 3 -->
                        <div
                            v-if="failedAttemptsCount >= 3"
                            class="animate-enter stagger-3 my-1 grid gap-2 rounded-2xl border border-border/40 bg-muted/10 p-4"
                        >
                            <Label
                                class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-300"
                            >
                                <svg
                                    class="size-3.5 text-primary"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                >
                                    <path
                                        d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"
                                    />
                                </svg>
                                Xác minh bảo mật
                            </Label>

                            <!-- Cloudflare Turnstile -->
                            <div v-if="turnstileSiteKey">
                                <div
                                    id="turnstile-container"
                                    class="my-1.5 flex justify-center"
                                ></div>
                                <input
                                    type="hidden"
                                    name="cf-turnstile-response"
                                    :value="turnstileToken"
                                />
                            </div>

                            <!-- Math CAPTCHA -->
                            <div v-else-if="captchaQuestion" class="grid gap-2">
                                <span
                                    class="text-xs leading-normal font-semibold text-muted-foreground"
                                >
                                    Vui lòng nhập kết quả của phép tính:
                                    <strong
                                        class="rounded border border-primary/20 bg-primary/10 px-1.5 py-0.5 font-mono text-sm text-primary"
                                        >{{ captchaQuestion }}</strong
                                    >
                                </span>
                                <input
                                    type="hidden"
                                    name="captcha_token"
                                    :value="captchaToken"
                                />
                                <Input
                                    id="captcha_answer"
                                    type="number"
                                    name="captcha_answer"
                                    required
                                    placeholder="Nhập kết quả"
                                    class="h-9 rounded-xl border-zinc-200 text-xs font-semibold shadow-sm transition-all duration-300 focus-visible:border-primary focus-visible:ring-primary/20 dark:border-zinc-800"
                                />
                            </div>
                        </div>

                        <div
                            class="animate-enter stagger-3 flex items-center gap-2.5 py-1"
                        >
                            <Checkbox
                                id="remember"
                                name="remember"
                                :tabindex="3"
                                class="rounded border-zinc-300 text-primary focus:ring-primary/10 dark:border-zinc-700"
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
                            class="animate-enter stagger-4 w-full cursor-pointer rounded-xl border-none bg-gradient-to-r from-primary via-amber-500 to-rose-500 py-6 text-xs font-bold tracking-wider text-white uppercase shadow-[0_4px_24px_rgba(245,158,11,0.3)] transition-all duration-300 hover:-translate-y-0.5 hover:from-primary/90 hover:via-amber-400 hover:to-rose-400 hover:shadow-[0_8px_32px_rgba(245,158,11,0.4)] active:scale-95"
                            :tabindex="4"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" class="text-white" />
                            {{ processing ? 'Đang đăng nhập...' : 'Đăng nhập' }}
                        </Button>
                    </Form>

                    <div
                        class="animate-enter stagger-5 relative my-6 flex items-center gap-3"
                    >
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
                        class="animate-enter stagger-5 w-full cursor-pointer rounded-xl border-zinc-200 py-5 font-bold shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:bg-zinc-50 hover:shadow active:scale-[0.98] dark:border-zinc-800 dark:hover:bg-zinc-900"
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
            <div
                class="relative z-10 mt-6 flex flex-col items-center gap-2 text-center text-sm text-muted-foreground"
            >
                <p v-if="canRegister">
                Chưa có tài khoản?
                <TextLink
                    :href="
                        register.url({
                            query: { plan: selectedPlan, cycle: selectedCycle },
                        })
                    "
                    :tabindex="6"
                    class="font-bold text-primary underline underline-offset-4 transition-colors hover:text-primary/80 dark:text-primary"
                >
                    Đăng ký miễn phí
                </TextLink>
                </p>
            </div>
        </div>

        <!-- RIGHT: Plans panel (desktop only) -->
        <div
            class="relative hidden flex-col justify-between overflow-hidden border-l border-zinc-900 bg-zinc-950 px-12 py-10 lg:flex lg:min-h-dvh"
        >
            <!-- Restaurant background image -->
            <img
                src="/restaurant-register-bg.png"
                alt=""
                class="pointer-events-none absolute inset-0 size-full object-cover opacity-75"
            />
            <div
                class="pointer-events-none absolute inset-0 bg-zinc-950/55"
            />

            <!-- Header (top) -->
            <div
                class="relative z-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="max-w-xl">
                    <div
                        class="mb-3.5 inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3.5 py-1 text-xs text-zinc-400 shadow-sm backdrop-blur-md"
                    >
                        <svg
                            class="size-3 animate-pulse text-primary"
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
                    <p class="mt-2 text-sm leading-relaxed text-zinc-500">
                        Chọn gói rồi đăng nhập — hệ thống chuyển bạn đến trang
                        thanh toán ngay.
                    </p>
                </div>

                <!-- Billing toggle with sliding background -->
                <div
                    class="relative flex shrink-0 items-center gap-1 self-start rounded-xl border border-white/10 bg-white/5 p-1 text-xs backdrop-blur-md select-none sm:self-auto"
                >
                    <div
                        class="pointer-events-none absolute top-1 bottom-1 rounded-lg bg-zinc-800 shadow-sm transition-all duration-300 ease-out"
                        :style="{
                            left: selectedCycle === 'monthly' ? '4px' : '62px',
                            width:
                                selectedCycle === 'monthly' ? '54px' : '82px',
                        }"
                    />
                    <button
                        type="button"
                        @click="selectedCycle = 'monthly'"
                        class="relative z-10 cursor-pointer rounded-lg px-3 py-1.5 font-semibold transition-colors duration-300"
                        :class="
                            selectedCycle === 'monthly'
                                ? 'text-white'
                                : 'text-zinc-400 hover:text-white'
                        "
                    >
                        Tháng
                    </button>
                    <button
                        type="button"
                        @click="selectedCycle = 'yearly'"
                        class="relative z-10 flex cursor-pointer items-center gap-1 rounded-lg px-3 py-1.5 font-semibold transition-colors duration-300"
                        :class="
                            selectedCycle === 'yearly'
                                ? 'text-white'
                                : 'text-zinc-400 hover:text-white'
                        "
                    >
                        Năm
                        <span
                            class="rounded-full bg-primary/50 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-xs transition-transform duration-300"
                            :class="
                                selectedCycle === 'yearly'
                                    ? 'scale-100'
                                    : 'scale-90'
                            "
                            >-{{ maxDiscountPercent }}%</span
                        >
                    </button>
                </div>
            </div>

            <!-- Plans (middle — flex-1 centered) -->
            <div
                class="relative z-10 my-4 flex flex-1 flex-col justify-start pt-2"
            >
                <div
                    class="w-full animate-in duration-700 fill-mode-both fade-in slide-in-from-bottom-4"
                >
                    <div class="grid grid-cols-4 gap-3">
                        <PlanCard
                            v-for="plan in plans"
                            :key="plan.code"
                            :plan="plan"
                            :selected="selectedPlan === plan.code"
                            :billing-cycle="
                                selectedCycle === 'yearly'
                                    ? 'yearly'
                                    : 'monthly'
                            "
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
                                class="inline-flex h-2 w-2 shrink-0 animate-pulse items-center justify-center rounded-full bg-primary/50"
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
