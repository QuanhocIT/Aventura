<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore Vue SFC module declaration is provided by the project shim.
import InputError from '@/components/InputError.vue';
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore Vue SFC module declaration is provided by the project shim.
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';
import { ref, onMounted } from 'vue';

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
        // @ts-ignore
        if (!window.turnstile) {
            const script = document.createElement('script');
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadTurnstileCallbackForgotPassword';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);

            // @ts-ignore
            window.onloadTurnstileCallbackForgotPassword = () => {
                // @ts-ignore
                window.turnstile.render('#turnstile-container-forgot-password', {
                    sitekey: props.turnstileSiteKey,
                    callback: (token: string) => {
                        turnstileToken.value = token;
                    },
                });
            };
        } else {
            setTimeout(() => {
                // @ts-ignore
                window.turnstile.render('#turnstile-container-forgot-password', {
                    sitekey: props.turnstileSiteKey,
                    callback: (token: string) => {
                        turnstileToken.value = token;
                    },
                });
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
            <div v-if="turnstileSiteKey || captchaQuestion" class="grid gap-2 my-4 border border-border/40 p-4 bg-muted/10 rounded-2xl">
                <Label class="text-xs font-bold text-slate-650 uppercase tracking-wider flex items-center gap-1.5">
                    <svg class="size-3.5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Xác minh bảo mật
                </Label>
                
                <!-- Cloudflare Turnstile -->
                <div v-if="turnstileSiteKey">
                    <div id="turnstile-container-forgot-password" class="my-1.5 flex justify-center"></div>
                    <input type="hidden" name="cf-turnstile-response" :value="turnstileToken" />
                </div>

                <!-- Math CAPTCHA -->
                <div v-else-if="captchaQuestion" class="grid gap-2">
                    <span class="text-xs text-muted-foreground font-semibold leading-normal">
                        Vui lòng nhập kết quả của phép tính: <strong class="text-primary font-mono text-sm px-1.5 py-0.5 bg-primary/10 rounded border border-primary/20">{{ captchaQuestion }}</strong>
                    </span>
                    <input type="hidden" name="captcha_token" :value="captchaToken" />
                    <Input id="captcha_answer" type="number" name="captcha_answer" required
                        placeholder="Nhập kết quả"
                        class="rounded-xl border-zinc-200 dark:border-zinc-800 transition-all duration-300 focus-visible:ring-primary/20 focus-visible:border-primary shadow-sm text-xs h-9 font-semibold" />
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
