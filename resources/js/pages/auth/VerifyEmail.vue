<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import InputError from '@/components/InputError.vue';

import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send, verifyCode } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Xác thực email',
        description:
            'Vui lòng xác thực địa chỉ email của bạn để tiếp tục sử dụng Aventura.',
    },
});

const props = defineProps<{
    status?: string;
}>();

const page = usePage();
const verified = computed<boolean>(() => {
    const url = new URL(page.url, window.location.origin);

    return url.searchParams.get('verified') === '1';
});

const code = ref<string>('');
</script>

<template>
    <Head title="Xác thực email" />

    <div
        v-if="verified"
        class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3.5 text-center text-sm font-semibold text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"
    >
        ✓ Email của bạn đã được xác thực thành công.
    </div>

    <div
        v-else-if="props.status === 'verification-link-sent'"
        class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3.5 text-center text-sm font-semibold text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"
    >
        ✓ Một email xác thực mới (gồm liên kết và mã xác thực) đã được gửi đến
        địa chỉ email bạn đã đăng ký.
    </div>

    <p class="mb-6 text-center text-sm leading-relaxed text-muted-foreground">
        Hãy kiểm tra hộp thư đến (và thư mục Spam) — bạn có thể chọn
        <span class="font-bold text-foreground">bấm vào liên kết xác thực</span>
        trong email, hoặc
        <span class="font-bold text-foreground">nhập mã 6 số</span> bên dưới để
        xác thực ngay tại đây.
    </p>

    <!-- OTP code verification -->
    <Form
        v-bind="verifyCode.form()"
        class="space-y-4"
        reset-on-error
        @error="code = ''"
        #default="{ errors, processing, clearErrors }"
    >
        <input type="hidden" name="code" :value="code" />
        <div
            class="flex flex-col items-center justify-center space-y-3 text-center"
        >
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
                            class="border-zinc-250 size-11 rounded-xl border text-base font-bold focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20 dark:border-zinc-800"
                        />
                    </InputOTPGroup>
                </InputOTP>
            </div>
            <InputError :message="errors.code" />
        </div>

        <Button
            type="submit"
            class="w-full"
            :disabled="processing || code.length !== 6"
        >
            <Spinner v-if="processing" />
            {{ processing ? 'Đang xác thực...' : 'Xác thực bằng mã' }}
        </Button>

        <button
            v-if="Object.keys(errors).length > 0"
            type="button"
            class="mx-auto block cursor-pointer text-xs font-semibold text-muted-foreground underline decoration-neutral-300 underline-offset-4 hover:decoration-current dark:decoration-neutral-500"
            @click="
                () => {
                    clearErrors();
                    code = '';
                }
            "
        >
            Xóa lỗi và nhập lại
        </button>
    </Form>

    <div class="my-6 flex items-center gap-3">
        <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800" />
        <span
            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
            >Hoặc</span
        >
        <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800" />
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-4 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary" class="w-full">
            <Spinner v-if="processing" />
            Gửi lại email xác thực
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Đăng xuất
        </TextLink>
    </Form>
</template>
