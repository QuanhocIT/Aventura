<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore Vue SFC module declaration is provided by the project shim.
import InputError from '@/components/InputError.vue';
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore Vue SFC module declaration is provided by the project shim.
import PasswordInput from '@/components/PasswordInput.vue';
// eslint-disable-next-line @typescript-eslint/ban-ts-comment
// @ts-ignore Vue SFC module declaration is provided by the project shim.
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { ref, computed } from 'vue';

const props = defineProps<{
    passwordRules: string;
    planOptions: Array<{ code: string; name: string; price: number }>;
}>();

// Chá»n gÃ³i Ä‘áº§u tiÃªn lÃ m máº·c Ä‘á»‹nh (thÆ°á»ng lÃ  gÃ³i Free)
const selectedPlan = ref<string>(props.planOptions[0]?.code ?? 'free');

const formatPrice = (price: number): string =>
    price === 0 ? 'Miá»…n phÃ­' : price.toLocaleString('vi-VN') + ' â‚«/thÃ¡ng';

const isPlanSelected = (code: string) => selectedPlan.value === code;

defineOptions({
    layout: {
        title: 'ÄÄƒng kÃ½ doanh nghiá»‡p',
        description: 'Nháº­p thÃ´ng tin nhÃ  hÃ ng Ä‘á»ƒ báº¯t Ä‘áº§u tráº£i nghiá»‡m miá»…n phÃ­',
    },
});
</script>

<template>
    <Head title="ÄÄƒng kÃ½ doanh nghiá»‡p" />

    <div class="flex flex-col gap-6">
        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <!-- TÃªn nhÃ  hÃ ng -->
                <div class="grid gap-2">
                    <Label for="restaurant_name">TÃªn nhÃ  hÃ ng</Label>
                    <Input
                        id="restaurant_name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="organization"
                        name="restaurant_name"
                        placeholder="Phá»Ÿ Viá»‡t, QuÃ¡n Ä‚n 24h..."
                    />
                    <InputError :message="errors.restaurant_name" />
                </div>

                <!-- TÃªn chá»§ tÃ i khoáº£n -->
                <div class="grid gap-2">
                    <Label for="name">Há» vÃ  tÃªn chá»§ tÃ i khoáº£n</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        :tabindex="2"
                        autocomplete="name"
                        name="name"
                        placeholder="Nguyá»…n VÄƒn A"
                    />
                    <InputError :message="errors.name" />
                </div>

                <!-- Email -->
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="3"
                        autocomplete="email"
                        name="email"
                        placeholder="owner@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <!-- Sá»‘ Ä‘iá»‡n thoáº¡i -->
                <div class="grid gap-2">
                    <Label for="phone">Sá»‘ Ä‘iá»‡n thoáº¡i</Label>
                    <Input
                        id="phone"
                        type="tel"
                        :tabindex="4"
                        autocomplete="tel"
                        name="phone"
                        placeholder="0900 000 000"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <!-- Chá»n gÃ³i dá»‹ch vá»¥ â€” Card style -->
                <div class="grid gap-2">
                    <Label>GÃ³i khá»Ÿi táº¡o</Label>
                    <div
                        v-if="planOptions.length > 0"
                        class="grid gap-3"
                        :class="planOptions.length > 1 ? 'grid-cols-1 sm:grid-cols-' + Math.min(planOptions.length, 3) : 'grid-cols-1'"
                    >
                        <label
                            v-for="plan in planOptions"
                            :key="plan.code"
                            :for="'plan_' + plan.code"
                            :class="[
                                'relative flex cursor-pointer flex-col gap-1 rounded-lg border-2 p-4 transition-all',
                                isPlanSelected(plan.code)
                                    ? 'border-primary bg-primary/5 shadow-sm'
                                    : 'border-border hover:border-primary/40 hover:bg-muted/30',
                            ]"
                        >
                            <input
                                :id="'plan_' + plan.code"
                                type="radio"
                                name="plan_code"
                                :value="plan.code"
                                v-model="selectedPlan"
                                class="sr-only"
                                :tabindex="5"
                            />
                            <!-- TÃ­ch chá»n -->
                            <span
                                v-if="isPlanSelected(plan.code)"
                                class="absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
                                aria-hidden="true"
                            >
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 12 12">
                                    <path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <span class="text-sm font-semibold leading-none text-foreground">{{ plan.name }}</span>
                            <span class="text-xs text-muted-foreground">{{ formatPrice(plan.price) }}</span>
                        </label>
                    </div>
                    <!-- Fallback náº¿u khÃ´ng load Ä‘Æ°á»£c plan -->
                    <div v-else>
                        <input type="hidden" name="plan_code" value="free" />
                        <p class="text-xs text-muted-foreground">Báº¯t Ä‘áº§u vá»›i gÃ³i miá»…n phÃ­. NÃ¢ng cáº¥p báº¥t cá»© lÃºc nÃ o.</p>
                    </div>
                    <InputError :message="errors.plan_code" />
                </div>

                <!-- Máº­t kháº©u -->
                <div class="grid gap-2">
                    <Label for="password">Máº­t kháº©u</Label>
                    <PasswordInput
                        id="password"
                        required
                        :tabindex="6"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Tá»‘i thiá»ƒu 8 kÃ½ tá»±"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="errors.password" />
                </div>

                <!-- XÃ¡c nháº­n máº­t kháº©u -->
                <div class="grid gap-2">
                    <Label for="password_confirmation">XÃ¡c nháº­n máº­t kháº©u</Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        :tabindex="7"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Nháº­p láº¡i máº­t kháº©u"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    tabindex="8"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    {{ processing ? 'Äang khá»Ÿi táº¡o há»‡ thá»‘ng...' : 'Táº¡o doanh nghiá»‡p ngay' }}
                </Button>

                <p class="text-center text-xs text-muted-foreground">
                    Sau khi Ä‘Äƒng kÃ½, há»‡ thá»‘ng tá»± Ä‘á»™ng thiáº¿t láº­p bÃ n, menu vÃ  kho máº«u â€” sáºµn sÃ ng bÃ¡n hÃ ng trong dÆ°á»›i 3 giÃ¢y.
                </p>
            </div>
        </Form>

        <div class="text-center text-sm text-muted-foreground">
            ÄÃ£ cÃ³ tÃ i khoáº£n?
            <TextLink :href="login()" class="underline underline-offset-4" :tabindex="9">ÄÄƒng nháº­p</TextLink>
        </div>
    </div>
</template>
