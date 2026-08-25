<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import { ref, onMounted } from 'vue';
import InputError from '@/components/InputError.vue';

import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot password',
        description: 'Enter your email to receive a password reset link',
    },
});

const props = defineProps<{
    status?: string;
    turnstileSiteKey?: string;
    captchaQuestion?: string;
    captchaToken?: string;
}>();

const turnstileToken = ref('');

onMounted(() => {
    if (props.turnstileSiteKey) {
        if (!window.turnstile) {
            const script = document.createElement('script');
            script.src =
                'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadTurnstileCallbackForgotPassword';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
            window.onloadTurnstileCallbackForgotPassword = () => {
                window.turnstile.render(
                    '#turnstile-container-forgot-password',
                    {
                        sitekey: props.turnstileSiteKey,
                        callback: (token: string) => {
                            turnstileToken.value = token;
                        },
                    },
                );
            };
        } else {
            setTimeout(() => {
                window.turnstile.render(
                    '#turnstile-container-forgot-password',
                    {
                        sitekey: props.turnstileSiteKey,
                        callback: (token: string) => {
                            turnstileToken.value = token;
                        },
                    },
                );
            }, 100);
        }
    }
});
</script>

<template>
    <Head title="Forgot password" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <!-- CAPTCHA / Turnstile security verification block -->
            <div
                v-if="turnstileSiteKey || captchaQuestion"
                class="my-4 grid gap-2 rounded-2xl border border-border/40 bg-muted/10 p-4"
            >
                <Label
                    class="text-slate-650 flex items-center gap-1.5 text-xs font-bold tracking-wider uppercase"
                >
                    <svg
                        class="size-3.5 text-primary"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                    Xác minh bảo mật
                </Label>

                <!-- Cloudflare Turnstile -->
                <div v-if="turnstileSiteKey">
                    <div
                        id="turnstile-container-forgot-password"
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

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    Email password reset link
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>Or, return to</span>
            <TextLink :href="login()">log in</TextLink>
        </div>
    </div>
</template>
