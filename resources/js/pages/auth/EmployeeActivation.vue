<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Kích hoạt tài khoản nhân viên',
        description: 'Đặt mật khẩu riêng cho tài khoản Aventura',
    },
});

const props = defineProps<{
    employeeName: string;
    email: string;
    jobTitle?: string | null;
    activationUrl: string;
    passwordRules: string;
}>();
</script>

<template>
    <Head title="Kích hoạt tài khoản nhân viên" />

    <div class="mx-auto w-full max-w-md space-y-6">
        <div>
            <p class="text-sm font-semibold text-primary">AVENTURA POS</p>
            <h1 class="mt-2 text-2xl font-bold">Kích hoạt tài khoản</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Xin chào {{ props.employeeName }}. Hãy đặt mật khẩu riêng để hoàn tất kích hoạt
                tài khoản{{ props.jobTitle ? ` ${props.jobTitle}` : '' }} với email {{ props.email }}.
            </p>
        </div>

        <Form
            :action="props.activationUrl"
            method="post"
            v-slot="{ errors, processing }"
            class="space-y-5"
            :reset-on-success="['password', 'password_confirmation']"
        >
            <div class="grid gap-2">
                <Label for="password">Mật khẩu mới</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autofocus
                    autocomplete="new-password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Nhập lại mật khẩu</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button type="submit" class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                Kích hoạt tài khoản
            </Button>
        </Form>
    </div>
</template>
