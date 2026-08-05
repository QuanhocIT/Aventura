<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import PlanCard from '@/components/PlanCard.vue';
import type { Plan } from '@/components/PlanCard.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home, login } from '@/routes';
import { store } from '@/routes/register';

const props = defineProps<{
    passwordRules: string;
    plans: Plan[];
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
    if (props.turnstileSiteKey) {
        if (!window.turnstile) {
            const script = document.createElement('script');
            script.src =
                'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadTurnstileCallbackRegister';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
            window.onloadTurnstileCallbackRegister = () => {
                window.turnstile.render('#turnstile-container-register', {
                    sitekey: props.turnstileSiteKey,
                    callback: (token: string) => {
                        turnstileToken.value = token;
                    },
                });
            };
        } else {
            setTimeout(() => {
                window.turnstile.render('#turnstile-container-register', {
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

const passwordValue = ref('');
const hasReferral = ref(false);

const passwordStrength = computed(
    (): { score: number; label: string; color: string } => {
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

        return { score, label: 'Mạnh', color: 'bg-primary' };
    },
);
</script>

<template>
    <Head title="Đăng ký · Aventura" />

    <div
        class="flex h-dvh min-h-0 grid-cols-1 flex-col overflow-hidden lg:grid lg:grid-cols-[1.1fr_2fr]"
    >
        <!-- LEFT: Form with a rich subtle gradient and highly defined glassmorphic container -->
        <div
            class="relative flex h-dvh min-h-0 flex-col overflow-hidden bg-gradient-to-b from-zinc-50 via-white to-zinc-50 px-4 py-6 sm:px-10 sm:py-10 md:px-12 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950"
        >
            <!-- Decorative dynamic glowing elements (clearly visible) -->
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent"
            />
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
                class="register-scroll-area relative z-10 flex min-h-0 flex-1 flex-col justify-start overflow-y-auto overscroll-contain pt-5 pb-2 sm:pt-8"
            >
                <div
                    class="focus-glow-card mx-auto w-full max-w-md animate-in rounded-2xl border border-white/40 bg-white/75 p-5 shadow-[0_24px_64px_rgba(0,0,0,0.06),0_8px_20px_rgba(0,0,0,0.03)] backdrop-blur-2xl transition-all duration-500 fill-mode-both fade-in slide-in-from-bottom-6 sm:rounded-3xl sm:p-10 dark:border-white/[0.08] dark:bg-zinc-950/65 dark:shadow-[0_24px_64px_rgba(0,0,0,0.35)]"
                >
                    <div
                        class="mb-4 inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/5 px-3.5 py-1 text-xs font-bold text-primary"
                    >
                        <span
                            class="h-1.5 w-1.5 animate-pulse rounded-full bg-primary/80"
                        />
                        Miễn phí 14 ngày
                    </div>
                    <h1
                        class="bg-gradient-to-r from-zinc-950 via-zinc-800 to-primary bg-clip-text text-2xl leading-tight font-black tracking-tight text-transparent sm:text-3xl sm:leading-none dark:from-white dark:via-zinc-200 dark:to-primary"
                    >
                        Tạo tài khoản doanh nghiệp
                    </h1>
                    <p class="mt-2.5 text-sm text-muted-foreground">
                        Điền thông tin bên dưới — hệ thống sẵn sàng trong dưới 3
                        giây.
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

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="[
                            'password',
                            'password_confirmation',
                        ]"
                        v-slot="{ errors, processing }"
                        class="mt-6 space-y-4"
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
                                for="restaurant_name"
                                class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                                >Tên nhà hàng</Label
                            >
                            <Input
                                id="restaurant_name"
                                type="text"
                                name="restaurant_name"
                                required
                                autofocus
                                :tabindex="1"
                                autocomplete="organization"
                                placeholder="Phở Việt, Quán Ăn 24h..."
                                class="rounded-xl border-zinc-200 shadow-sm transition-all duration-300 hover:border-zinc-300 focus-visible:border-primary focus-visible:ring-primary/20 dark:border-zinc-800 dark:hover:border-zinc-700"
                            />
                            <InputError :message="errors.restaurant_name" />
                        </div>

                        <div class="animate-enter stagger-2 grid gap-1.5">
                            <Label
                                for="name"
                                class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                                >Họ và tên chủ tài khoản</Label
                            >
                            <Input
                                id="name"
                                type="text"
                                name="name"
                                required
                                :tabindex="2"
                                autocomplete="name"
                                placeholder="Nguyễn Văn A"
                                class="rounded-xl border-zinc-200 shadow-sm transition-all duration-300 hover:border-zinc-300 focus-visible:border-primary focus-visible:ring-primary/20 dark:border-zinc-800 dark:hover:border-zinc-700"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div
                            class="animate-enter stagger-3 grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-3.5"
                        >
                            <div class="grid gap-1.5">
                                <Label
                                    for="email"
                                    class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                                    >Email</Label
                                >
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    :tabindex="3"
                                    autocomplete="email"
                                    placeholder="owner@example.com"
                                    class="rounded-xl border-zinc-200 shadow-sm transition-all duration-300 hover:border-zinc-300 focus-visible:border-primary focus-visible:ring-primary/20 dark:border-zinc-800 dark:hover:border-zinc-700"
                                />
                                <InputError :message="errors.email" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    for="phone"
                                    class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                                    >Số điện thoại</Label
                                >
                                <Input
                                    id="phone"
                                    type="tel"
                                    name="phone"
                                    :tabindex="4"
                                    autocomplete="tel"
                                    placeholder="0900 000 000"
                                    class="rounded-xl border-zinc-200 shadow-sm transition-all duration-300 hover:border-zinc-300 focus-visible:border-primary focus-visible:ring-primary/20 dark:border-zinc-800 dark:hover:border-zinc-700"
                                />
                                <InputError :message="errors.phone" />
                            </div>
                        </div>

                        <div class="animate-enter stagger-4 grid gap-1.5">
                            <Label
                                for="password"
                                class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                                >Mật khẩu</Label
                            >
                            <PasswordInput
                                id="password"
                                name="password"
                                :modelValue="passwordValue"
                                @update:modelValue="
                                    passwordValue = String($event)
                                "
                                required
                                :tabindex="5"
                                autocomplete="new-password"
                                placeholder="Tối thiểu 8 ký tự"
                                :passwordrules="passwordRules"
                                class="rounded-xl border-zinc-200 shadow-sm transition-all duration-300 hover:border-zinc-300 focus-visible:border-primary focus-visible:ring-primary/20 dark:border-zinc-800 dark:hover:border-zinc-700"
                            />

                            <!-- Segmented Glowing Password Strength Indicator -->
                            <div
                                v-if="passwordValue"
                                class="mt-1.5 animate-in space-y-1.5 duration-300 fade-in slide-in-from-top-1"
                            >
                                <div class="flex h-1 w-full gap-1.5">
                                    <div
                                        v-for="step in 5"
                                        :key="step"
                                        class="h-full flex-1 rounded-full transition-all duration-300"
                                        :class="[
                                            step <= passwordStrength.score
                                                ? passwordStrength.color +
                                                  ' opacity-100'
                                                : 'bg-zinc-200 opacity-80 dark:bg-zinc-800',
                                        ]"
                                        :style="
                                            step <= passwordStrength.score
                                                ? {
                                                      boxShadow:
                                                          passwordStrength.score <=
                                                          1
                                                              ? '0 0 6px rgba(239,68,68,0.5)'
                                                              : passwordStrength.score ===
                                                                  2
                                                                ? '0 0 6px rgba(251,191,36,0.5)'
                                                                : passwordStrength.score ===
                                                                    3
                                                                  ? '0 0 6px rgba(234,179,8,0.5)'
                                                                  : '0 0 6px rgba(16,185,129,0.5)',
                                                  }
                                                : {}
                                        "
                                    />
                                </div>
                                <p
                                    class="text-[11px] font-bold tracking-wide transition-colors duration-300"
                                    :class="{
                                        'text-red-500':
                                            passwordStrength.score <= 1,
                                        'text-amber-500':
                                            passwordStrength.score === 2,
                                        'text-yellow-600':
                                            passwordStrength.score === 3,
                                        'text-emerald-500':
                                            passwordStrength.score >= 4,
                                    }"
                                >
                                    Trạng thái: {{ passwordStrength.label }}
                                </p>
                            </div>
                            <InputError :message="errors.password" />
                        </div>

                        <div class="animate-enter stagger-5 grid gap-1.5">
                            <Label
                                for="password_confirmation"
                                class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                                >Xác nhận mật khẩu</Label
                            >
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                :tabindex="6"
                                autocomplete="new-password"
                                placeholder="Nhập lại mật khẩu"
                                :passwordrules="passwordRules"
                                class="rounded-xl border-zinc-200 shadow-sm transition-all duration-300 hover:border-zinc-300 focus-visible:border-primary focus-visible:ring-primary/20 dark:border-zinc-800 dark:hover:border-zinc-700"
                            />
                            <InputError
                                :message="errors.password_confirmation"
                            />
                        </div>

                        <!-- Referral Code Section -->
                        <div
                            class="animate-enter stagger-5 space-y-3 rounded-2xl border border-zinc-100 bg-zinc-50/50 p-3.5 dark:border-zinc-800 dark:bg-zinc-900/30"
                        >
                            <div class="flex items-center space-x-2.5">
                                <input
                                    type="checkbox"
                                    id="has_referral"
                                    v-model="hasReferral"
                                    class="h-4 w-4 cursor-pointer rounded border-zinc-300 text-primary focus:ring-primary focus:ring-offset-0"
                                />
                                <Label
                                    for="has_referral"
                                    class="cursor-pointer text-xs font-bold text-muted-foreground select-none"
                                >
                                    Có phải bạn được giới thiệu không?
                                </Label>
                            </div>
                            <div
                                v-if="hasReferral"
                                class="grid animate-in gap-1.5 duration-200 fade-in slide-in-from-top-2"
                            >
                                <Label
                                    for="referral_code"
                                    class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                                    >Mã giới thiệu</Label
                                >
                                <Input
                                    id="referral_code"
                                    type="text"
                                    name="referral_code"
                                    placeholder="Ví dụ: AVT12345"
                                    class="rounded-xl border-zinc-200 font-mono uppercase shadow-sm transition-all duration-300 hover:border-zinc-300 focus-visible:border-primary focus-visible:ring-primary/20 dark:border-zinc-800 dark:hover:border-zinc-700"
                                />
                                <InputError :message="errors.referral_code" />
                            </div>
                        </div>

                        <!-- CAPTCHA / Turnstile security verification block -->
                        <div
                            v-if="turnstileSiteKey || captchaQuestion"
                            class="animate-enter stagger-5 my-1 grid gap-2 rounded-2xl border border-border/40 bg-muted/10 p-4"
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
                                    id="turnstile-container-register"
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

                        <Button
                            type="submit"
                            class="animate-enter stagger-5 mt-2 w-full cursor-pointer rounded-xl border-none bg-gradient-to-r from-primary via-amber-500 to-rose-500 py-6 text-xs font-bold tracking-wider text-white uppercase shadow-[0_4px_24px_rgba(245,158,11,0.3)] transition-all duration-300 hover:-translate-y-0.5 hover:from-primary/90 hover:via-amber-400 hover:to-rose-400 hover:shadow-[0_8px_32px_rgba(245,158,11,0.4)] active:scale-95"
                            :tabindex="7"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" class="text-white" />
                            {{
                                processing
                                    ? 'Đang khởi tạo hệ thống...'
                                    : 'Tạo doanh nghiệp ngay'
                            }}
                        </Button>
                    </Form>
                </div>
            </div>

            <!-- Footer (bottom) -->
            <p
                class="relative z-10 mt-6 text-center text-sm text-muted-foreground"
            >
                Đã có tài khoản?
                <TextLink
                    :href="
                        login.url({
                            query: { plan: selectedPlan, cycle: selectedCycle },
                        })
                    "
                    :tabindex="8"
                    class="font-bold text-primary underline underline-offset-4 transition-colors hover:text-primary/80"
                >
                    Đăng nhập
                </TextLink>
            </p>
        </div>

        <!-- RIGHT: Plans panel (desktop only) -->
        <div
            class="relative hidden h-dvh min-h-0 flex-col justify-between overflow-hidden border-l border-zinc-900 bg-zinc-950 px-12 py-10 lg:flex"
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
                        Chọn gói để bắt đầu
                    </div>
                    <h2
                        class="bg-gradient-to-br from-white to-zinc-400 bg-clip-text text-3xl font-black tracking-tight text-transparent"
                    >
                        14 ngày dùng thử miễn phí
                    </h2>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-500">
                        Nếu không thanh toán, tài khoản sẽ tự động chuyển về gói
                        <span class="font-black text-zinc-300">Free</span> —
                        hoàn toàn không mất gì cả.
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
                            class="rounded-full bg-primary px-1.5 py-0.5 text-[9px] font-bold text-white shadow-xs transition-transform duration-300"
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
                                class="inline-flex h-2 w-2 shrink-0 animate-pulse items-center justify-center rounded-full bg-primary"
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
                                    · Sau khi đăng ký thành công bạn sẽ được tự
                                    động chuyển đến trang thanh toán.
                                </template>
                                <template v-else>
                                    · Gói cơ bản hoàn toàn miễn phí, dễ dàng
                                    nâng cấp bất cứ lúc nào.
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
                Không ràng buộc hợp đồng · Hủy bất cứ lúc nào · Hỗ trợ khách
                hàng 24/7
            </p>
        </div>
    </div>
</template>

<style scoped>
.register-scroll-area {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.register-scroll-area::-webkit-scrollbar {
    display: none;
    width: 0;
    height: 0;
}
</style>
