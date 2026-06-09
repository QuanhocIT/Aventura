<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore Vue SFC module declaration is provided by the project shim.
import AppLogoIcon from '@/components/AppLogoIcon.vue';
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore Vue SFC module declaration is provided by the project shim.
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { send as sendEmailOtp } from '@/routes/two-factor/email-code';
import { store } from '@/routes/two-factor/login';

defineOptions({
    layout: undefined, // Let resolvePageComponent resolve it to BareLayout or handle bare internally
});

const showRecoveryInput = ref<boolean>(false);
const recoveryButtonText = ref<string>('Sử dụng mã khôi phục dự phòng');
const code = ref<string>('');

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
    recoveryButtonText.value = showRecoveryInput.value
        ? 'Sử dụng ứng dụng xác thực OTP'
        : 'Sử dụng mã khôi phục dự phòng';
};

const page = usePage();
const flash = computed(() => (page.props as any).flash ?? {});
const emailCodeSuccess = computed<string | undefined>(() => flash.value.success);
const emailCodeError = computed<string | undefined>(() => flash.value.error);
const emailCodeNotice = computed<string | undefined>(() => flash.value.info);

// Đếm số lần gửi email OTP thất bại liên tiếp — khi vượt ngưỡng, hiển thị
// banner gợi ý chuyển sang Authenticator/mã khôi phục thay vì chỉ 1 dòng lỗi nhỏ
// dễ bị bỏ qua (đúng tình huống người dùng gặp phải khi dịch vụ email gián đoạn).
const emailFailureStreak = ref<number>(0);
const showEmailTroubleBanner = computed<boolean>(() => emailFailureStreak.value >= 2);

watch(emailCodeError, (value) => {
    if (value) {
emailFailureStreak.value += 1;
}
});
watch(emailCodeSuccess, (value) => {
    if (value) {
emailFailureStreak.value = 0;
}
});

const switchToRecoveryMode = (): void => {
    if (showRecoveryInput.value) {
return;
}

    showRecoveryInput.value = true;
    code.value = '';
    recoveryButtonText.value = 'Sử dụng ứng dụng xác thực OTP';
};

const emailCodeSending = ref<boolean>(false);
const emailCodeCooldown = ref<number>(0);
let cooldownTimer: ReturnType<typeof setInterval> | null = null;

const startCooldown = (seconds: number): void => {
    emailCodeCooldown.value = seconds;

    if (cooldownTimer) {
clearInterval(cooldownTimer);
}

    cooldownTimer = setInterval(() => {
        if (emailCodeCooldown.value <= 1) {
            emailCodeCooldown.value = 0;

            if (cooldownTimer) {
clearInterval(cooldownTimer);
}

            cooldownTimer = null;
        } else {
            emailCodeCooldown.value -= 1;
        }
    }, 1000);
};

const requestEmailCode = (): void => {
    if (emailCodeSending.value || emailCodeCooldown.value > 0) {
return;
}

    emailCodeSending.value = true;
    router.post(sendEmailOtp.url(), {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            emailCodeSending.value = false;

            // Đồng bộ thời gian đếm ngược với thời gian còn lại thực tế phía server
            // (vd: trường hợp đã gửi trước đó, server trả về số giây chính xác còn
            // phải đợi — có thể ngắn hơn mức mặc định 60s).
            const retryAfter = Number((page.props as any).flash?.retry_after);
            startCooldown(Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter : 60);
        },
    });
};

onBeforeUnmount(() => {
    if (cooldownTimer) {
clearInterval(cooldownTimer);
}
});

// Tự động gửi mã OTP qua email ngay khi vào trang xác thực, để người dùng
// không cần thêm thao tác bấm "Gửi mã qua email".
onMounted(() => {
    requestEmailCode();
});
</script>

<template>
    <Head title="Xác thực 2 lớp (2FA) · Aventura" />

    <div class="min-h-dvh grid-cols-1 flex flex-col lg:grid lg:grid-cols-[1.1fr_2fr]">

        <!-- LEFT: Beautiful Glassmorphic 2FA Verification Card -->
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

            <!-- Card Container -->
            <div class="flex flex-1 flex-col justify-start pt-6 sm:pt-8 relative z-10">
                <div class="animate-in fade-in slide-in-from-bottom-6 duration-500 fill-mode-both w-full max-w-md mx-auto backdrop-blur-xl bg-white/70 dark:bg-zinc-950/70 border border-white/80 dark:border-zinc-800/80 p-8 sm:p-10 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.06)] dark:shadow-[0_20px_50px_rgba(0,0,0,0.3)]">

                    <!-- Secure Badge -->
                    <div class="mb-4 inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/5 dark:bg-emerald-500/10 px-4 py-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500/80" />
                        Xác thực an toàn
                    </div>

                    <!-- Heading -->
                    <h1 class="bg-gradient-to-r from-zinc-950 via-zinc-800 to-emerald-600 dark:from-white dark:via-zinc-200 dark:to-emerald-400 bg-clip-text text-2xl font-black tracking-tight text-transparent leading-none">Xác thực 2 lớp (2FA)</h1>
                    
                    <!-- Dynamic Context Instructions (Addresses user's QR code query) -->
                    <p class="mt-4 text-xs text-muted-foreground leading-relaxed bg-zinc-50 dark:bg-zinc-900/60 p-3.5 rounded-xl border border-zinc-100 dark:border-zinc-800/80 font-medium">
                        💡 <span class="font-bold text-foreground">Lưu ý:</span> Mã QR quét để kích hoạt chỉ hiển thị một lần duy nhất khi thiết lập. Để đăng nhập lúc này, bạn hãy mở ứng dụng <span class="font-bold text-foreground">Google Authenticator</span> hoặc <span class="font-bold text-emerald-600 dark:font-bold dark:text-emerald-400">Microsoft Authenticator</span> trên điện thoại của mình để lấy mã OTP 6 số hiện tại — hoặc chọn gửi mã về email bên dưới nếu không có sẵn ứng dụng.
                    </p>

                    <!-- Email OTP request -->
                    <div class="mt-3 flex flex-col gap-2 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-800 p-3.5">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold text-muted-foreground">Không có ứng dụng Authenticator?</p>
                            <button
                                type="button"
                                class="shrink-0 rounded-lg bg-zinc-900 dark:bg-white px-3.5 py-2 text-[11px] font-black uppercase tracking-wider text-white dark:text-zinc-900 transition-all duration-200 hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer"
                                :disabled="emailCodeSending || emailCodeCooldown > 0"
                                @click="requestEmailCode"
                            >
                                <span v-if="emailCodeSending">Đang gửi...</span>
                                <span v-else-if="emailCodeCooldown > 0">Gửi lại sau {{ emailCodeCooldown }}s</span>
                                <span v-else>Gửi mã qua email</span>
                            </button>
                        </div>
                        <p v-if="emailCodeSuccess" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                            ✓ {{ emailCodeSuccess }}
                        </p>
                        <p v-else-if="emailCodeNotice" class="text-xs font-semibold text-sky-600 dark:text-sky-400">
                            ℹ {{ emailCodeNotice }}
                        </p>
                        <p v-else-if="emailCodeError" class="text-xs font-semibold text-red-500">
                            ⚠ {{ emailCodeError }}
                        </p>
                    </div>

                    <!-- Banner: gửi email OTP thất bại liên tục → gợi ý mạnh chuyển phương án khác -->
                    <div
                        v-if="showEmailTroubleBanner && !showRecoveryInput"
                        class="mt-3 flex flex-col gap-2 rounded-xl border border-amber-300/60 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10 p-3.5"
                    >
                        <p class="text-xs font-semibold text-amber-700 dark:text-amber-400">
                            ⚠ Hệ thống gửi email đang gặp sự cố. Vui lòng dùng mã từ ứng dụng Authenticator (đã thiết lập trên điện thoại của bạn) hoặc mã khôi phục dự phòng để đăng nhập ngay.
                        </p>
                        <button
                            type="button"
                            class="self-start rounded-lg bg-amber-600 px-3.5 py-2 text-[11px] font-black uppercase tracking-wider text-white transition-all duration-200 hover:opacity-90 cursor-pointer"
                            @click="switchToRecoveryMode"
                        >
                            Dùng mã khôi phục dự phòng
                        </button>
                    </div>

                    <!-- Form block for OTP -->
                    <template v-if="!showRecoveryInput">
                        <Form
                            v-bind="store.form()"
                            class="mt-6 space-y-5"
                            reset-on-error
                            @error="code = ''"
                            #default="{ errors, processing, clearErrors }"
                        >
                            <input type="hidden" name="code" :value="code" />
                            <div class="flex flex-col items-center justify-center space-y-3 text-center">
                                <div class="flex w-full items-center justify-center">
                                    <InputOTP
                                        id="otp"
                                        v-model="code"
                                        :maxlength="6"
                                        :disabled="processing"
                                        autofocus
                                    >
                                        <InputOTPGroup class="gap-1.5">
                                            <InputOTPSlot
                                                v-for="index in 6"
                                                :key="index"
                                                :index="index - 1"
                                                class="rounded-xl border border-zinc-250 dark:border-zinc-800 text-base font-bold size-11 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500"
                                            />
                                        </InputOTPGroup>
                                    </InputOTP>
                                </div>
                                <InputError :message="errors.code" />
                            </div>

                            <Button type="submit" class="w-full rounded-xl py-6 font-black uppercase tracking-wider text-xs bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white transition-all duration-300 shadow-[0_4px_20px_rgba(16,185,129,0.3)] hover:shadow-[0_4px_25px_rgba(16,185,129,0.45)] hover:-translate-y-0.5 active:scale-[0.98] border-none cursor-pointer" :disabled="processing">
                                <Spinner v-if="processing" class="text-white mr-1.5" />
                                {{ processing ? 'ĐANG XÁC THỰC...' : 'XÁC THỰC & TIẾP TỤC' }}
                            </Button>

                            <div class="text-center text-xs font-semibold text-muted-foreground">
                                <span>Hoặc bạn có thể </span>
                                <button
                                    type="button"
                                    class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500 font-bold cursor-pointer"
                                    @click="() => toggleRecoveryMode(clearErrors)"
                                >
                                    {{ recoveryButtonText }}
                                </button>
                            </div>
                        </Form>
                    </template>

                    <!-- Form block for Recovery Code -->
                    <template v-else>
                        <Form
                            v-bind="store.form()"
                            class="mt-6 space-y-5"
                            reset-on-error
                            #default="{ errors, processing, clearErrors }"
                        >
                            <div class="grid gap-1.5">
                                <Input
                                    name="recovery_code"
                                    type="text"
                                    placeholder="Nhập mã khôi phục dự phòng"
                                    :autofocus="showRecoveryInput"
                                    required
                                    class="rounded-xl border-zinc-200 dark:border-zinc-800 transition-all duration-300 hover:border-zinc-300 dark:hover:border-zinc-700 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500 shadow-sm py-5 px-4"
                                />
                                <InputError :message="errors.recovery_code" />
                            </div>

                            <Button type="submit" class="w-full rounded-xl py-6 font-black uppercase tracking-wider text-xs bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white transition-all duration-300 shadow-[0_4px_20px_rgba(16,185,129,0.3)] hover:shadow-[0_4px_25px_rgba(16,185,129,0.45)] hover:-translate-y-0.5 active:scale-[0.98] border-none cursor-pointer" :disabled="processing">
                                <Spinner v-if="processing" class="text-white mr-1.5" />
                                {{ processing ? 'ĐANG XÁC THỰC...' : 'XÁC THỰC & TIẾP TỤC' }}
                            </Button>

                            <div class="text-center text-xs font-semibold text-muted-foreground">
                                <span>Hoặc bạn có thể </span>
                                <button
                                    type="button"
                                    class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500 font-bold cursor-pointer"
                                    @click="() => toggleRecoveryMode(clearErrors)"
                                >
                                    {{ recoveryButtonText }}
                                </button>
                            </div>
                        </Form>
                    </template>

                </div>
            </div>

            <!-- Footer (bottom) -->
            <p class="text-center text-xs text-muted-foreground mt-6 relative z-10">
                Gặp sự cố đăng nhập? Vui lòng liên hệ với Quản trị viên hệ thống của bạn.
            </p>
        </div>

        <!-- RIGHT: Beautiful Premium Security Visualization Panel (Desktop only) -->
        <div class="relative hidden flex-col overflow-hidden border-l border-zinc-900 bg-zinc-950 px-12 py-10 lg:flex lg:min-h-dvh justify-between">
            <!-- Dot-grid dynamic background -->
            <svg class="pointer-events-none absolute inset-0 size-full opacity-[0.06]" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="dots-mfa-panel" x="0" y="0" width="24" height="24" patternUnits="userSpaceOnUse">
                        <circle cx="1.5" cy="1.5" r="1.5" fill="white" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#dots-mfa-panel)" />
            </svg>
            <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(ellipse_80%_60%_at_50%_0%,rgba(255,255,255,0.08),transparent)]" />

            <!-- Ambient light blobs (vividly visible) -->
            <div class="pointer-events-none absolute top-1/4 left-1/3 h-[500px] w-[500px] rounded-full bg-emerald-500/[0.12] blur-[130px] z-0 animate-pulse duration-[8s]" />
            <div class="pointer-events-none absolute bottom-1/4 right-1/4 h-[450px] w-[450px] rounded-full bg-blue-500/[0.10] blur-[120px] z-0 animate-pulse duration-[10s]" />

            <!-- Header (top) -->
            <div class="relative z-10">
                <div class="mb-3.5 inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3.5 py-1 text-xs text-zinc-400 shadow-sm backdrop-blur-md">
                    <svg class="size-3 text-emerald-400 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Hệ thống bảo mật Aventura Shield
                </div>
                <h2 class="bg-gradient-to-br from-white to-zinc-400 bg-clip-text text-3xl font-black tracking-tight text-transparent">Bảo mật cấp doanh nghiệp</h2>
                <p class="mt-2 text-sm text-zinc-500 max-w-md leading-relaxed">
                    Aventura áp dụng các tiêu chuẩn an ninh thông tin khắt khe nhất để bảo vệ toàn vẹn tài sản và dữ liệu vận hành của chuỗi nhà hàng của bạn.
                </p>
            </div>

            <!-- Beautiful Tech Security Center Visual (middle) -->
            <div class="relative z-10 flex flex-1 flex-col justify-center my-6 items-center">
                <div class="relative h-48 w-48 flex items-center justify-center animate-in fade-in zoom-in duration-1000">
                    <!-- Spinning glowing concentric circles -->
                    <div class="absolute h-44 w-44 rounded-full border-2 border-dashed border-emerald-500/20 animate-spin" style="animation-duration: 20s;" />
                    <div class="absolute h-36 w-36 rounded-full border border-dashed border-blue-500/30 animate-spin" style="animation-duration: 12s; animation-direction: reverse;" />
                    <div class="absolute h-28 w-28 rounded-full bg-emerald-500/5 dark:bg-emerald-500/10 border border-emerald-500/20 backdrop-blur-md flex items-center justify-center shadow-lg">
                        <!-- Shield and check icon -->
                        <svg class="size-12 text-emerald-400 animate-bounce duration-[3s]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            <path d="m9 11 2 2 4-4"/>
                        </svg>
                    </div>
                </div>

                <!-- Three Premium Features Info Card -->
                <div class="mt-8 grid grid-cols-1 gap-4 w-full max-w-lg">
                    <div class="rounded-2xl border border-white/[0.06] bg-white/[0.02] hover:bg-white/[0.04] p-4 transition-all duration-300 flex items-start gap-4 shadow-sm hover:border-emerald-500/20">
                        <div class="h-9 w-9 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 shrink-0">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-zinc-100 leading-tight">Mã hóa đa yếu tố (MFA)</h4>
                            <p class="mt-1 text-xs text-zinc-500 leading-relaxed">Sự kết hợp giữa mật khẩu mạnh và mã xác thực OTP thay đổi liên tục giúp ngăn chặn 99.9% nguy cơ xâm nhập trái phép.</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/[0.06] bg-white/[0.02] hover:bg-white/[0.04] p-4 transition-all duration-300 flex items-start gap-4 shadow-sm hover:border-blue-500/20">
                        <div class="h-9 w-9 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400 shrink-0">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-zinc-100 leading-tight">Cô lập dữ liệu đa chi nhánh</h4>
                            <p class="mt-1 text-xs text-zinc-500 leading-relaxed">Cơ chế bảo mật cô lập dữ liệu độc lập giữa các nhà hàng và chi nhánh (Tenant Isolation) chống rò rỉ dữ liệu tuyệt đối.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer (bottom) -->
            <p class="relative z-10 text-center text-xs text-zinc-600 tracking-wide font-medium">
                © 2026 Aventura SaaS Platform · Được tin dùng bởi hàng trăm nhà hàng toàn quốc.
            </p>
        </div>

    </div>
</template>
