<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore Vue SFC module declaration is provided by the project shim.
import AppLogoIcon from '@/components/AppLogoIcon.vue';
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore Vue SFC module declaration is provided by the project shim.
import InputError from '@/components/InputError.vue';
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore Vue SFC module declaration is provided by the project shim.
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: undefined, // Handled internally as BareLayout
});
</script>

<template>
    <Head title="Xác nhận mật khẩu · Aventura" />

    <div
        class="flex min-h-dvh grid-cols-1 flex-col lg:grid lg:grid-cols-[1.1fr_2fr]"
    >
        <!-- LEFT: Beautiful Glassmorphic Password Confirmation Card -->
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
                    class="to-zinc-650 bg-gradient-to-r from-zinc-900 bg-clip-text text-lg font-black tracking-tight text-transparent dark:from-white dark:to-zinc-300"
                    >Aventura</span
                >
            </Link>

            <!-- Form Container -->
            <div
                class="relative z-10 flex flex-1 flex-col justify-start pt-6 sm:pt-8"
            >
                <div
                    class="mx-auto w-full max-w-md animate-in rounded-3xl border border-white/80 bg-white/70 p-8 shadow-[0_20px_50px_rgba(0,0,0,0.06)] backdrop-blur-xl duration-500 fill-mode-both fade-in slide-in-from-bottom-6 sm:p-10 dark:border-zinc-800/80 dark:bg-zinc-950/70 dark:shadow-[0_20px_50px_rgba(0,0,0,0.3)]"
                >
                    <!-- Secure Badge -->
                    <div
                        class="mb-4 inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/5 px-4 py-1 text-xs font-bold text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"
                    >
                        <span
                            class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500/80"
                        />
                        Khu vực bảo mật
                    </div>

                    <!-- Heading -->
                    <h1
                        class="bg-gradient-to-r from-zinc-950 via-zinc-800 to-emerald-600 bg-clip-text text-2xl leading-none font-black tracking-tight text-transparent dark:from-white dark:via-zinc-200 dark:to-emerald-400"
                    >
                        Xác nhận mật khẩu
                    </h1>

                    <p
                        class="mt-2.5 text-sm leading-relaxed text-muted-foreground"
                    >
                        Đây là khu vực bảo mật nâng cao. Vui lòng xác nhận lại
                        mật khẩu tài khoản Super Admin của bạn để tiếp tục thực
                        hiện thao tác nhạy cảm này.
                    </p>

                    <Form
                        v-bind="store.form()"
                        reset-on-success
                        v-slot="{ errors, processing }"
                        class="mt-6 space-y-5"
                    >
                        <div class="grid gap-1.5">
                            <Label
                                for="password"
                                class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                                >Mật khẩu của bạn</Label
                            >
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                autofocus
                                placeholder="Nhập mật khẩu tài khoản"
                                class="rounded-xl border-zinc-200 py-5 shadow-sm transition-all duration-300 hover:border-zinc-300 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20 dark:border-zinc-800 dark:hover:border-zinc-700"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <Button
                            type="submit"
                            class="w-full cursor-pointer rounded-xl border-none bg-gradient-to-r from-emerald-600 to-teal-500 py-6 text-xs font-black tracking-wider text-white uppercase shadow-[0_4px_20px_rgba(16,185,129,0.3)] transition-all duration-300 hover:-translate-y-0.5 hover:from-emerald-500 hover:to-teal-400 hover:shadow-[0_4px_25px_rgba(16,185,129,0.45)] active:scale-[0.98]"
                            :disabled="processing"
                            data-test="confirm-password-button"
                        >
                            <Spinner
                                v-if="processing"
                                class="mr-1.5 text-white"
                            />
                            {{
                                processing
                                    ? 'ĐANG XÁC NHẬN...'
                                    : 'XÁC NHẬN MẬT KHẨU'
                            }}
                        </Button>
                    </Form>
                </div>
            </div>

            <!-- Footer (bottom) -->
            <p
                class="relative z-10 mt-6 text-center text-xs text-muted-foreground"
            >
                Gặp sự cố xác minh? Vui lòng liên hệ với Quản trị viên hệ thống
                của bạn.
            </p>
        </div>

        <!-- RIGHT: Beautiful Premium Security Visualization Panel (Desktop only) -->
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
                        id="dots-confirm-panel"
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
                    fill="url(#dots-confirm-panel)"
                />
            </svg>
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(ellipse_80%_60%_at_50%_0%,rgba(255,255,255,0.08),transparent)]"
            />

            <!-- Ambient light blobs (vividly visible) -->
            <div
                class="pointer-events-none absolute top-1/4 left-1/3 z-0 h-[500px] w-[500px] animate-pulse rounded-full bg-emerald-500/[0.12] blur-[130px] duration-[8s]"
            />
            <div
                class="pointer-events-none absolute right-1/4 bottom-1/4 z-0 h-[450px] w-[450px] animate-pulse rounded-full bg-blue-500/[0.10] blur-[120px] duration-[10s]"
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
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                    Xác minh bảo vệ kép
                </div>
                <h2
                    class="bg-gradient-to-br from-white to-zinc-400 bg-clip-text text-3xl font-black tracking-tight text-transparent"
                >
                    Xác thực thao tác nhạy cảm
                </h2>
                <p class="mt-2 max-w-md text-sm leading-relaxed text-zinc-500">
                    Xác nhận mật khẩu là lớp rào cản vững chắc nhằm chống lại
                    các cuộc tấn công chiếm quyền điều khiển phiên làm việc
                    (Session Hijacking) trên trình duyệt của bạn.
                </p>
            </div>

            <!-- Beautiful Tech Security Center Visual (middle) -->
            <div
                class="relative z-10 my-6 flex flex-1 flex-col items-center justify-center"
            >
                <div
                    class="relative flex h-48 w-48 animate-in items-center justify-center duration-1000 fade-in zoom-in"
                >
                    <!-- Concentric circles -->
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
                        class="absolute flex h-28 w-28 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/5 shadow-lg backdrop-blur-md dark:bg-emerald-500/10"
                    >
                        <!-- Key icon -->
                        <svg
                            class="size-12 animate-bounce text-emerald-400 duration-[3s]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"
                            />
                        </svg>
                    </div>
                </div>

                <!-- Features Cards -->
                <div class="mt-8 grid w-full max-w-lg grid-cols-1 gap-4">
                    <div
                        class="flex items-start gap-4 rounded-2xl border border-white/[0.06] bg-white/[0.02] p-4 shadow-sm transition-all duration-300 hover:border-emerald-500/20 hover:bg-white/[0.04]"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-400"
                        >
                            <svg
                                class="size-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                            >
                                <rect
                                    x="3"
                                    y="11"
                                    width="18"
                                    height="11"
                                    rx="2"
                                    ry="2"
                                />
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                        </div>
                        <div>
                            <h4
                                class="text-sm leading-tight font-bold text-zinc-100"
                            >
                                Xác minh danh nghĩa tức thời
                            </h4>
                            <p
                                class="mt-1 text-xs leading-relaxed text-zinc-500"
                            >
                                Đảm bảo các cấu hình nhạy cảm như override thanh
                                toán và phân quyền được thực thi bởi chính người
                                quản trị.
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-start gap-4 rounded-2xl border border-white/[0.06] bg-white/[0.02] p-4 shadow-sm transition-all duration-300 hover:border-blue-500/20 hover:bg-white/[0.04]"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-400"
                        >
                            <svg
                                class="size-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                            >
                                <path
                                    d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"
                                />
                            </svg>
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
                                Mọi phiên làm việc và thao tác sau khi xác nhận
                                mật khẩu thành công đều được mã hóa và lưu trữ
                                phục vụ truy vết sau này.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer (bottom) -->
            <p
                class="text-zinc-650 relative z-10 text-center text-xs font-medium tracking-wide"
            >
                © 2026 Aventura SaaS Platform · Bảo mật vượt trội đồng hành cùng
                sự phát triển chuỗi.
            </p>
        </div>
    </div>
</template>
