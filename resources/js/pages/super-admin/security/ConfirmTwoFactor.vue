<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps<{
    validityMinutes: number;
}>();

const form = useForm({
    code: '',
});

const submit = (): void => {
    form.post('/super-admin/security/confirm-2fa', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Xác nhận 2FA · Superadmin" />

    <div
        class="mx-auto flex min-h-[70vh] max-w-xl items-center justify-center px-4 py-10"
    >
        <Card class="w-full">
            <CardHeader class="space-y-3">
                <div
                    class="flex size-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                >
                    <ShieldCheck class="size-6" />
                </div>
                <CardTitle>Xác nhận thao tác nhạy cảm</CardTitle>
                <CardDescription>
                    Nhập mã 6 chữ số từ ứng dụng Authenticator. Phiên xác nhận
                    có hiệu lực {{ validityMinutes }} phút.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <form class="space-y-5" @submit.prevent="submit">
                    <div class="space-y-2">
                        <label
                            for="superadmin-2fa-code"
                            class="text-sm font-medium"
                            >Mã xác thực 2FA</label
                        >
                        <Input
                            id="superadmin-2fa-code"
                            v-model="form.code"
                            name="code"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            placeholder="000000"
                            autofocus
                            class="max-w-xs text-center text-xl tracking-[0.35em]"
                        />
                        <InputError :message="form.errors.code" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Button
                            type="submit"
                            :disabled="
                                form.processing || form.code.length !== 6
                            "
                        >
                            {{
                                form.processing
                                    ? 'Đang xác nhận...'
                                    : 'Xác nhận 2FA'
                            }}
                        </Button>
                        <Link
                            href="/super-admin/dashboard"
                            class="text-sm text-muted-foreground hover:underline"
                        >
                            Hủy
                        </Link>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
