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

// Chọn gói đầu tiên làm mặc định (thường là gói Free)
const selectedPlan = ref<string>(props.planOptions[0]?.code ?? 'free');

const formatPrice = (price: number): string =>
    price === 0 ? 'Miễn phí' : price.toLocaleString('vi-VN') + ' ₫/tháng';

const isPlanSelected = (code: string) => selectedPlan.value === code;

defineOptions({
    layout: {
        title: 'Đăng ký doanh nghiệp',
        description: 'Nhập thông tin nhà hàng để bắt đầu trải nghiệm miễn phí',
    },
});
</script>

<template>
    <Head title="Đăng ký doanh nghiệp" />

    <div class="flex flex-col gap-6">
        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <!-- Tên nhà hàng -->
                <div class="grid gap-2">
                    <Label for="restaurant_name">Tên nhà hàng</Label>
                    <Input
                        id="restaurant_name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="organization"
                        name="restaurant_name"
                        placeholder="Phở Việt, Quán Ăn 24h..."
                    />
                    <InputError :message="errors.restaurant_name" />
                </div>

                <!-- Tên chủ tài khoản -->
                <div class="grid gap-2">
                    <Label for="name">Họ và tên chủ tài khoản</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        :tabindex="2"
                        autocomplete="name"
                        name="name"
                        placeholder="Nguyễn Văn A"
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

                <!-- Số điện thoại -->
                <div class="grid gap-2">
                    <Label for="phone">Số điện thoại</Label>
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

                <!-- Chọn gói dịch vụ — Card style -->
                <div class="grid gap-2">
                    <Label>Gói khởi tạo</Label>
                    <div
                        v-if="planOptions.length > 0"
                        class="grid gap-3"
                        :class="
                            planOptions.length > 1
                                ? 'sm:grid-cols- grid-cols-1' +
                                  Math.min(planOptions.length, 3)
                                : 'grid-cols-1'
                        "
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
                            <!-- Tích chọn -->
                            <span
                                v-if="isPlanSelected(plan.code)"
                                class="absolute top-3 right-3 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
                                aria-hidden="true"
                            >
                                <svg
                                    class="h-3 w-3"
                                    fill="none"
                                    viewBox="0 0 12 12"
                                >
                                    <path
                                        d="M2 6l3 3 5-5"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </span>
                            <span
                                class="text-sm leading-none font-semibold text-foreground"
                                >{{ plan.name }}</span
                            >
                            <span class="text-xs text-muted-foreground">{{
                                formatPrice(plan.price)
                            }}</span>
                        </label>
                    </div>
                    <!-- Fallback nếu không load được plan -->
                    <div v-else>
                        <input type="hidden" name="plan_code" value="free" />
                        <p class="text-xs text-muted-foreground">
                            Bắt đầu với gói miễn phí. Nâng cấp bất cứ lúc nào.
                        </p>
                    </div>
                    <InputError :message="errors.plan_code" />
                </div>

                <!-- Mật khẩu -->
                <div class="grid gap-2">
                    <Label for="password">Mật khẩu</Label>
                    <PasswordInput
                        id="password"
                        required
                        :tabindex="6"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Tối thiểu 8 ký tự"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="errors.password" />
                </div>

                <!-- Xác nhận mật khẩu -->
                <div class="grid gap-2">
                    <Label for="password_confirmation">Xác nhận mật khẩu</Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        :tabindex="7"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Nhập lại mật khẩu"
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
                    {{
                        processing
                            ? 'Đang khởi tạo hệ thống...'
                            : 'Tạo doanh nghiệp ngay'
                    }}
                </Button>

                <p class="text-center text-xs text-muted-foreground">
                    Sau khi đăng ký, hệ thống tự động thiết lập bàn, menu và kho
                    mẫu — sẵn sàng bán hàng trong dưới 3 giây.
                </p>
            </div>
        </Form>

        <div class="text-center text-sm text-muted-foreground">
            Đã có tài khoản?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="9"
                >Đăng nhập</TextLink
            >
        </div>
    </div>
</template>
