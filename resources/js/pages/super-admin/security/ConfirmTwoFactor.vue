<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    FileCheck2,
    Fingerprint,
    LockKeyhole,
    ShieldCheck,
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: undefined, // Được gắn BareLayout trong app.ts như các form xác thực khác.
});

defineProps<{
    validityMinutes: number;
}>();

const form = useForm({
    code: '',
});

const submit = (): void => {
    if (form.code.trim().length !== 6) {
        form.setError('code', 'Vui lòng nhập đủ mã xác thực 6 chữ số.');
        toast.error('Vui lòng nhập đủ mã xác thực 6 chữ số.');

        return;
    }

    form.post('/super-admin/security/confirm-2fa', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Xác nhận 2FA · Quản trị cấp cao" />

    <div
        class="flex min-h-dvh grid-cols-1 flex-col lg:grid lg:grid-cols-[1.1fr_2fr]"
    >
        <div
            class="relative flex min-h-dvh flex-col overflow-hidden bg-gradient-to-b from-zinc-50 via-white to-zinc-50 px-6 py-10 sm:px-10 md:px-12 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950"
        >
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary/30 to-transparent"
            />
            <div
                class="pointer-events-none absolute -bottom-20 -left-20 h-[400px] w-[400px] animate-pulse rounded-full bg-emerald-500/15 blur-[120px] duration-[8s]"
            />
            <div
                class="pointer-events-none absolute top-1/4 -right-20 h-[350px] w-[350px] animate-pulse rounded-full bg-blue-500/10 blur-[100px] duration-[6s]"
            />

            <Link
                href="/"
                class="relative z-10 flex w-fit items-center gap-2.5 transition-transform duration-300 hover:scale-[1.02]"
            >
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary shadow-md"
                >
                    <AppLogoIcon
                        class="size-5 fill-current text-primary-foreground"
                    />
                </span>
                <span
                    class="bg-gradient-to-r from-zinc-900 bg-clip-text text-lg font-black tracking-tight text-transparent dark:from-white dark:to-zinc-300"
                    >Aventura</span
                >
            </Link>

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
                        Xác thực an toàn
                    </div>

                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        >
                            <ShieldCheck class="size-6" />
                        </div>
                        <div>
                            <h1
                                class="bg-gradient-to-r from-zinc-950 via-zinc-800 to-emerald-600 bg-clip-text text-2xl leading-none font-black tracking-tight text-transparent dark:from-white dark:via-zinc-200 dark:to-emerald-400"
                            >
                                Xác nhận thao tác nhạy cảm
                            </h1>
                            <p
                                class="mt-2.5 text-sm leading-relaxed text-muted-foreground"
                            >
                                Nhập mã 6 chữ số từ ứng dụng Authenticator.
                                Phiên xác nhận có hiệu lực
                                {{ validityMinutes }} phút.
                            </p>
                        </div>
                    </div>

                    <form class="mt-6 space-y-5" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <label
                                for="superadmin-2fa-code"
                                class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                            >
                                Mã xác thực 2FA
                            </label>
                            <InputOTP
                                id="superadmin-2fa-code"
                                v-model="form.code"
                                :maxlength="6"
                                :disabled="form.processing"
                                autofocus
                            >
                                <InputOTPGroup
                                    class="w-full justify-between gap-1.5"
                                >
                                    <InputOTPSlot
                                        v-for="index in 6"
                                        :key="index"
                                        :index="index - 1"
                                        class="size-11 flex-1 rounded-xl border-zinc-200 text-base font-bold shadow-sm transition-all focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20 dark:border-zinc-800"
                                    />
                                </InputOTPGroup>
                            </InputOTP>
                            <InputError :message="form.errors.code" />
                        </div>

                        <Button
                            type="submit"
                            class="w-full cursor-pointer rounded-xl border-none bg-gradient-to-r from-emerald-600 to-teal-500 py-6 text-xs font-black tracking-wider text-white uppercase shadow-[0_4px_20px_rgba(16,185,129,0.3)] transition-all duration-300 hover:-translate-y-0.5 hover:from-emerald-500 hover:to-teal-400 hover:shadow-[0_4px_25px_rgba(16,185,129,0.45)] active:scale-[0.98]"
                            :disabled="
                                form.processing || form.code.length !== 6
                            "
                        >
                            <Spinner
                                v-if="form.processing"
                                class="mr-1.5 text-white"
                            />
                            {{
                                form.processing
                                    ? 'ĐANG XÁC NHẬN...'
                                    : 'XÁC NHẬN & TIẾP TỤC'
                            }}
                        </Button>

                        <div
                            class="text-center text-xs font-semibold text-muted-foreground"
                        >
                            Hoặc
                            <Link
                                href="/super-admin/dashboard"
                                class="font-bold text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors hover:text-emerald-600 dark:decoration-neutral-500 dark:hover:text-emerald-400"
                            >
                                hủy và quay lại trang quản trị
                            </Link>
                        </div>
                    </form>
                </div>
            </div>

            <p
                class="relative z-10 mt-6 text-center text-xs text-muted-foreground"
            >
                Gặp sự cố xác minh? Vui lòng liên hệ Quản trị viên hệ thống.
            </p>
        </div>

        <div
            class="relative hidden flex-col justify-between overflow-hidden border-l border-zinc-900 bg-zinc-950 px-12 py-10 lg:flex lg:min-h-dvh"
        >
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(ellipse_80%_60%_at_50%_0%,rgba(255,255,255,0.08),transparent)]"
            />
            <div
                class="pointer-events-none absolute top-1/4 left-1/3 z-0 h-[500px] w-[500px] animate-pulse rounded-full bg-emerald-500/[0.12] blur-[130px] duration-[8s]"
            />
            <div
                class="pointer-events-none absolute right-1/4 bottom-1/4 z-0 h-[450px] w-[450px] animate-pulse rounded-full bg-blue-500/[0.10] blur-[120px] duration-[10s]"
            />

            <div class="relative z-10">
                <div
                    class="mb-3.5 inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3.5 py-1 text-xs text-zinc-400 shadow-sm backdrop-blur-md"
                >
                    <ShieldCheck
                        class="size-3 animate-pulse text-emerald-400"
                    />
                    Xác minh bảo vệ kép
                </div>
                <h2
                    class="bg-gradient-to-br from-white to-zinc-400 bg-clip-text text-3xl font-black tracking-tight text-transparent"
                >
                    Bảo vệ thao tác nhạy cảm
                </h2>
                <p class="mt-2 max-w-md text-sm leading-relaxed text-zinc-500">
                    Mã xác thực bổ sung giúp bảo vệ các thao tác quan trọng và
                    ngăn truy cập trái phép vào khu vực quản trị.
                </p>
            </div>

            <div
                class="relative z-10 my-6 flex flex-1 flex-col items-center justify-center"
            >
                <div
                    class="relative flex h-48 w-48 items-center justify-center"
                >
                    <div
                        class="absolute h-44 w-44 animate-spin rounded-full border-2 border-dashed border-emerald-500/20"
                        style="animation-duration: 20s"
                    />
                    <div
                        class="absolute h-36 w-36 animate-spin rounded-full border border-dashed border-blue-500/30"
                        style="
                            animation-duration: 12s;
                            animation-direction: reverse;
                        "
                    />
                    <div
                        class="flex h-28 w-28 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/5 shadow-lg backdrop-blur-md dark:bg-emerald-500/10"
                    >
                        <Fingerprint
                            class="size-12 animate-pulse text-emerald-400"
                        />
                    </div>
                </div>

                <div class="mt-8 grid w-full max-w-lg grid-cols-1 gap-4">
                    <div
                        class="flex items-start gap-4 rounded-2xl border border-white/[0.06] bg-white/[0.02] p-4 shadow-sm transition-all duration-300 hover:border-emerald-500/20 hover:bg-white/[0.04]"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400"
                        >
                            <LockKeyhole class="size-4" />
                        </div>
                        <div>
                            <h4
                                class="text-sm leading-tight font-bold text-zinc-100"
                            >
                                Xác minh danh tính tức thời
                            </h4>
                            <p
                                class="mt-1 text-xs leading-relaxed text-zinc-500"
                            >
                                Chỉ quản trị viên đã xác thực mới có thể tiếp
                                tục các thao tác nhạy cảm.
                            </p>
                        </div>
                    </div>
                    <div
                        class="flex items-start gap-4 rounded-2xl border border-white/[0.06] bg-white/[0.02] p-4 shadow-sm transition-all duration-300 hover:border-blue-500/20 hover:bg-white/[0.04]"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-400"
                        >
                            <FileCheck2 class="size-4" />
                        </div>
                        <div>
                            <h4
                                class="text-sm leading-tight font-bold text-zinc-100"
                            >
                                Ghi nhật ký kiểm toán chặt chẽ
                            </h4>
                            <p
                                class="mt-1 text-xs leading-relaxed text-zinc-500"
                            >
                                Mỗi lần xác nhận đều được ghi nhận để phục vụ
                                truy vết và bảo mật hệ thống.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <p
                class="relative z-10 text-center text-xs font-medium tracking-wide text-zinc-500"
            >
                © 2026 Aventura SaaS Platform · Bảo mật vượt trội đồng hành cùng
                sự phát triển chuỗi.
            </p>
        </div>
    </div>
</template>
