<script setup lang="ts">
import { Form, Head, Link, usePage, useForm, router } from '@inertiajs/vue3';
import {
    User as UserIcon,
    ShieldCheck,
    Lock,
    Check,
    Copy,
    Gift,
    History,
    Landmark,
    Users,
    Coins,
    CreditCard,
    Wallet,
    KeyRound,
} from 'lucide-vue-next';
import { computed, ref, onUnmounted } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { edit } from '@/routes/profile';
import { disable, enable } from '@/routes/two-factor';
import { send } from '@/routes/verification';

type Referral = {
    name: string;
    created_at: string;
    status: string;
};

type CommissionLog = {
    id: number;
    buyer_name: string;
    restaurant_name: string;
    amount: number;
    commission_percentage: number;
    commission_amount: number;
    created_at: string;
};

type WithdrawalRequest = {
    id: number;
    amount: number;
    bank_name: string;
    bank_account_number: string;
    bank_account_name: string;
    status: 'pending' | 'approved' | 'rejected';
    notes: string | null;
    created_at: string;
};

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
    passwordRules?: string;
    referrals?: Referral[];
    commissionLogs?: CommissionLog[];
    withdrawalRequests?: WithdrawalRequest[];
    success?: string;
    error?: string;
};

const props = withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
    passwordRules: '',
    referrals: () => [],
    commissionLogs: () => [],
    withdrawalRequests: () => [],
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Hồ sơ cá nhân',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

// Dynamic Tab Tracking
const activeTab = computed(() => {
    const url = new URL(page.url, window.location.origin);

    return url.searchParams.get('tab') || 'profile';
});

// 2FA Setup State
const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => clearTwoFactorAuthData());

// Referrals State
const isCopied = ref(false);
const copyCode = () => {
    if (user.value?.referral_code) {
        navigator.clipboard.writeText(user.value.referral_code);
        isCopied.value = true;
        setTimeout(() => (isCopied.value = false), 2000);
    }
};

const withdrawalForm = useForm({
    amount: '',
    bank_name: user.value?.bank_name || '',
    bank_account_number: user.value?.bank_account_number || '',
    bank_account_name: user.value?.bank_account_name || '',
});

const submitWithdrawal = () => {
    withdrawalForm.post('/settings/referrals/withdraw', {
        onSuccess: () => {
            withdrawalForm.reset('amount');
        },
    });
};

const referralsActiveSubTab = ref<'withdrawals' | 'referrals' | 'commissions'>(
    'withdrawals',
);

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(value);
};

// PIN Code State
const pinForm = ref({
    pin_code: '',
    pin_code_confirmation: '',
});
const pinErrors = ref<any>({});
const pinFormProcessing = ref(false);

const updatePin = () => {
    pinErrors.value = {};

    if (!/^\d{4,6}$/.test(pinForm.value.pin_code)) {
        pinForm.value.pin_code = '';
        pinForm.value.pin_code_confirmation = '';
        pinErrors.value.pin_code = 'Mã PIN phải gồm từ 4 đến 6 chữ số.';

        return;
    }

    if (pinForm.value.pin_code !== pinForm.value.pin_code_confirmation) {
        pinForm.value.pin_code_confirmation = '';
        pinErrors.value.pin_code_confirmation = 'Mã PIN xác nhận không khớp.';

        return;
    }

    pinFormProcessing.value = true;
    router.put(
        '/settings/pin',
        {
            pin_code: pinForm.value.pin_code,
            pin_code_confirmation: pinForm.value.pin_code_confirmation,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                pinForm.value.pin_code = '';
                pinForm.value.pin_code_confirmation = '';
                import('vue-sonner').then((m) =>
                    m.toast.success('Đã cập nhật mã PIN phê duyệt thành công!'),
                );
            },
            onError: (errs: any) => {
                pinErrors.value = errs;
                import('vue-sonner').then((m) =>
                    m.toast.error('Có lỗi xảy ra khi lưu mã PIN.'),
                );
            },
            onFinish: () => {
                pinFormProcessing.value = false;
            },
        },
    );
};
</script>

<template>
    <Head
        :title="
            activeTab === 'profile'
                ? 'Hồ sơ cá nhân'
                : activeTab === 'security'
                  ? 'Bảo mật tài khoản'
                  : 'Giới thiệu & Hoa hồng'
        "
    />

    <h1 class="sr-only">
        {{
            activeTab === 'profile'
                ? 'Hồ sơ cá nhân'
                : activeTab === 'security'
                  ? 'Bảo mật tài khoản'
                  : 'Giới thiệu & Hoa hồng'
        }}
    </h1>

    <div class="space-y-6">
        <!-- TAB: Profile -->
        <div v-if="activeTab === 'profile'" class="animate-fade-in space-y-6">
            <Card
                class="w-full overflow-hidden rounded-2xl border border-neutral-200/60 bg-white/70 shadow-xs backdrop-blur-md dark:border-neutral-800/60 dark:bg-neutral-900/40"
            >
                <CardHeader
                    class="flex flex-row items-center gap-4 border-b border-neutral-100 px-6 pt-6 pb-5 dark:border-neutral-800"
                >
                    <div
                        class="shrink-0 rounded-xl bg-neutral-100 p-2.5 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
                    >
                        <UserIcon class="h-5 w-5" />
                    </div>
                    <div class="space-y-0.5">
                        <CardTitle
                            class="text-lg font-black text-neutral-900 dark:text-neutral-50"
                            >Thông tin hồ sơ</CardTitle
                        >
                        <CardDescription
                            class="text-xs text-neutral-500 dark:text-neutral-400"
                            >Cập nhật họ tên và hòm thư điện tử của
                            bạn</CardDescription
                        >
                    </div>
                </CardHeader>
                <CardContent class="p-6 lg:p-8">
                    <Form
                        v-bind="ProfileController.update.form()"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label
                                for="name"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Họ và tên</Label
                            >
                            <Input
                                id="name"
                                class="mt-1 block w-full rounded-xl border-neutral-200 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800 dark:focus:border-neutral-50 dark:focus:ring-neutral-50"
                                name="name"
                                :default-value="user.name"
                                required
                                autocomplete="name"
                                placeholder="Họ và tên"
                            />
                            <InputError class="mt-2" :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label
                                for="email"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Địa chỉ email</Label
                            >
                            <div class="relative">
                                <Input
                                    id="email"
                                    type="email"
                                    class="mt-1 block w-full rounded-xl border-neutral-200 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800 dark:focus:border-neutral-50 dark:focus:ring-neutral-50"
                                    name="email"
                                    :default-value="user.email"
                                    required
                                    autocomplete="username"
                                    placeholder="Địa chỉ email"
                                />
                            </div>
                            <InputError class="mt-2" :message="errors.email" />
                        </div>

                        <div
                            v-if="mustVerifyEmail && !user.email_verified_at"
                            class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4"
                        >
                            <p
                                class="flex flex-col gap-1.5 text-xs leading-relaxed font-medium text-amber-700 sm:flex-row sm:items-center dark:text-amber-400"
                            >
                                <span
                                    >Địa chỉ email của bạn chưa được xác
                                    minh.</span
                                >
                                <Link
                                    :href="send()"
                                    as="button"
                                    class="font-bold text-amber-800 underline transition-colors hover:text-amber-600 dark:text-amber-300"
                                >
                                    Nhấn vào đây để gửi lại email xác minh.
                                </Link>
                            </p>

                            <div
                                v-if="status === 'verification-link-sent'"
                                class="mt-2 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                            >
                                Liên kết xác minh mới đã được gửi đến địa chỉ
                                email của bạn.
                            </div>
                        </div>

                        <div class="flex items-center gap-4 pt-2">
                            <Button
                                :disabled="processing"
                                data-test="update-profile-button"
                                class="cursor-pointer rounded-xl bg-neutral-900 px-6 py-2.5 text-xs font-bold tracking-wider text-white uppercase shadow-sm transition-all duration-200 hover:bg-neutral-800 active:scale-95 disabled:opacity-50 dark:bg-neutral-50 dark:text-neutral-950 dark:hover:bg-neutral-200"
                            >
                                Lưu thay đổi
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>

        <!-- TAB: Security -->
        <div v-else-if="activeTab === 'security'" class="space-y-6">
            <!-- Update password card -->
            <Card
                class="w-full overflow-hidden rounded-2xl border border-neutral-200/60 bg-white/70 shadow-xs backdrop-blur-md dark:border-neutral-800/60 dark:bg-neutral-900/40"
            >
                <CardHeader
                    class="flex flex-row items-center gap-4 border-b border-neutral-100 px-6 pt-6 pb-5 dark:border-neutral-800"
                >
                    <div
                        class="shrink-0 rounded-xl bg-neutral-100 p-2.5 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
                    >
                        <Lock class="h-5 w-5" />
                    </div>
                    <div class="space-y-0.5">
                        <CardTitle
                            class="text-lg font-black text-neutral-900 dark:text-neutral-50"
                            >Cập nhật mật khẩu</CardTitle
                        >
                        <CardDescription
                            class="text-xs text-neutral-500 dark:text-neutral-400"
                            >Đảm bảo tài khoản của bạn sử dụng mật khẩu mạnh để
                            bảo mật thông tin</CardDescription
                        >
                    </div>
                </CardHeader>
                <CardContent class="p-6 lg:p-8">
                    <Form
                        v-bind="SecurityController.update.form()"
                        :options="{
                            preserveScroll: true,
                        }"
                        reset-on-success
                        :reset-on-error="[
                            'password',
                            'password_confirmation',
                            'current_password',
                        ]"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label
                                for="current_password"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Mật khẩu hiện tại</Label
                            >
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                class="mt-1 block w-full rounded-xl border-neutral-200 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                                autocomplete="current-password"
                                placeholder="Mật khẩu hiện tại"
                            />
                            <InputError :message="errors.current_password" />
                        </div>

                        <div class="grid gap-2">
                            <Label
                                for="password"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Mật khẩu mới</Label
                            >
                            <PasswordInput
                                id="password"
                                name="password"
                                class="mt-1 block w-full rounded-xl border-neutral-200 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                                autocomplete="new-password"
                                placeholder="Mật khẩu mới"
                                :passwordrules="props.passwordRules"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label
                                for="password_confirmation"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Xác nhận mật khẩu mới</Label
                            >
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                class="mt-1 block w-full rounded-xl border-neutral-200 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                                autocomplete="new-password"
                                placeholder="Xác nhận mật khẩu mới"
                                :passwordrules="props.passwordRules"
                            />
                            <InputError
                                :message="errors.password_confirmation"
                            />
                        </div>

                        <div class="flex items-center gap-4 pt-2">
                            <Button
                                :disabled="processing"
                                data-test="update-password-button"
                                class="cursor-pointer rounded-xl bg-neutral-900 px-6 py-2.5 text-xs font-bold tracking-wider text-white uppercase shadow-sm transition-all duration-200 hover:bg-neutral-800 active:scale-95 disabled:opacity-50 dark:bg-neutral-50 dark:text-neutral-950 dark:hover:bg-neutral-200"
                            >
                                Lưu mật khẩu
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>

            <!-- Two-factor authentication card -->
            <Card
                v-if="canManageTwoFactor"
                class="w-full overflow-hidden rounded-2xl border border-neutral-200/60 bg-white/70 shadow-xs backdrop-blur-md dark:border-neutral-800/60 dark:bg-neutral-900/40"
            >
                <CardHeader
                    class="flex flex-row items-center gap-4 border-b border-neutral-100 px-6 pt-6 pb-5 dark:border-neutral-800"
                >
                    <div
                        class="shrink-0 rounded-xl bg-neutral-100 p-2.5 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
                    >
                        <ShieldCheck class="h-5 w-5" />
                    </div>
                    <div class="space-y-0.5">
                        <CardTitle
                            class="text-lg font-black text-neutral-900 dark:text-neutral-50"
                            >Xác thực hai yếu tố (2FA)</CardTitle
                        >
                        <CardDescription
                            class="text-xs text-neutral-500 dark:text-neutral-400"
                            >Yêu cầu mã xác minh an toàn từ điện thoại khi đăng
                            nhập tài khoản</CardDescription
                        >
                    </div>
                </CardHeader>
                <CardContent class="p-6 lg:p-8">
                    <div
                        v-if="!twoFactorEnabled"
                        class="flex flex-col items-start justify-start space-y-4"
                    >
                        <p
                            class="text-xs leading-relaxed font-semibold text-neutral-500 dark:text-neutral-400"
                        >
                            Khi bật xác thực hai yếu tố, hệ thống sẽ yêu cầu bạn
                            cung cấp mã xác nhận bảo mật từ ứng dụng tạo mã (như
                            Google Authenticator) trên điện thoại của bạn lúc
                            đăng nhập.
                        </p>

                        <div class="pt-2">
                            <Button
                                v-if="hasSetupData"
                                @click="showSetupModal = true"
                                class="cursor-pointer rounded-xl bg-neutral-900 px-5 py-2.5 text-xs font-bold tracking-wider text-white uppercase shadow-sm transition-all hover:bg-neutral-800 active:scale-95 dark:bg-neutral-50 dark:text-neutral-950 dark:hover:bg-neutral-200"
                            >
                                <ShieldCheck class="mr-1.5 size-4" /> Tiếp tục
                                thiết lập
                            </Button>
                            <Form
                                v-else
                                v-bind="enable.form()"
                                @success="showSetupModal = true"
                                #default="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    class="cursor-pointer rounded-xl bg-neutral-900 px-5 py-2.5 text-xs font-bold tracking-wider text-white uppercase shadow-sm transition-all hover:bg-neutral-800 active:scale-95 disabled:opacity-50 dark:bg-neutral-50 dark:text-neutral-950 dark:hover:bg-neutral-200"
                                >
                                    Kích hoạt 2FA
                                </Button>
                            </Form>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-start justify-start space-y-5"
                    >
                        <p
                            class="text-xs leading-relaxed font-semibold text-neutral-500 dark:text-neutral-400"
                        >
                            Xác thực hai yếu tố đã được kích hoạt. Hãy dùng ứng
                            dụng tạo mã trên điện thoại để lấy mã xác nhận mỗi
                            lần đăng nhập.
                        </p>

                        <div class="relative inline pt-2">
                            <Form
                                v-bind="disable.form()"
                                #default="{ processing }"
                            >
                                <Button
                                    variant="destructive"
                                    type="submit"
                                    :disabled="processing"
                                    class="cursor-pointer rounded-xl bg-red-600 px-5 py-2.5 text-xs font-bold tracking-wider text-white uppercase shadow-sm transition-all hover:bg-red-700 active:scale-95 disabled:opacity-50"
                                >
                                    Hủy kích hoạt 2FA
                                </Button>
                            </Form>
                        </div>

                        <div
                            class="w-full border-t border-neutral-100 pt-4 dark:border-neutral-800"
                        >
                            <TwoFactorRecoveryCodes />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <TwoFactorSetupModal
                v-model:isOpen="showSetupModal"
                :requiresConfirmation="requiresConfirmation"
                :twoFactorEnabled="twoFactorEnabled"
            />

            <!-- PIN Code settings card for Owner/Manager approval -->
            <Card
                v-if="
                    user?.roles?.some((r: any) =>
                        ['owner', 'manager'].includes(r.name),
                    )
                "
                class="w-full overflow-hidden rounded-2xl border border-neutral-200/60 bg-white/70 shadow-xs backdrop-blur-md dark:border-neutral-800/60 dark:bg-neutral-900/40"
            >
                <CardHeader
                    class="flex flex-row items-center gap-4 border-b border-neutral-100 px-6 pt-6 pb-5 dark:border-neutral-800"
                >
                    <div
                        class="shrink-0 rounded-xl bg-neutral-100 p-2.5 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
                    >
                        <KeyRound class="h-5 w-5" />
                    </div>
                    <div class="space-y-0.5">
                        <CardTitle
                            class="text-lg font-black text-neutral-900 dark:text-neutral-50"
                            >Mã PIN Phê Duyệt POS (Bypass PIN)</CardTitle
                        >
                        <CardDescription
                            class="text-xs text-neutral-500 dark:text-neutral-400"
                            >Thiết lập mã PIN 4-6 số để phê duyệt nhanh các thao
                            tác hủy đơn/sửa giá/hoàn tiền trên
                            POS</CardDescription
                        >
                    </div>
                </CardHeader>
                <CardContent class="p-6 lg:p-8">
                    <form @submit.prevent="updatePin" class="space-y-6">
                        <div class="grid gap-2">
                            <Label
                                for="pin_code"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Mã PIN mới (4 - 6 số)</Label
                            >
                            <Input
                                id="pin_code"
                                type="password"
                                pattern="[0-9]*"
                                inputmode="numeric"
                                maxlength="6"
                                v-model="pinForm.pin_code"
                                class="mt-1 block w-full rounded-xl border-neutral-200 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                                placeholder="Nhập mã PIN gồm 4 đến 6 chữ số"
                                required
                            />
                            <p
                                v-if="pinErrors.pin_code"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ pinErrors.pin_code }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label
                                for="pin_code_confirmation"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Xác nhận mã PIN mới</Label
                            >
                            <Input
                                id="pin_code_confirmation"
                                type="password"
                                pattern="[0-9]*"
                                inputmode="numeric"
                                maxlength="6"
                                v-model="pinForm.pin_code_confirmation"
                                class="mt-1 block w-full rounded-xl border-neutral-200 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                                placeholder="Xác nhận mã PIN mới"
                                required
                            />
                            <p
                                v-if="pinErrors.pin_code_confirmation"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ pinErrors.pin_code_confirmation }}
                            </p>
                        </div>

                        <div class="flex items-center gap-4 pt-2">
                            <Button
                                type="submit"
                                :disabled="pinFormProcessing"
                                class="cursor-pointer rounded-xl bg-neutral-900 px-6 py-2.5 text-xs font-bold tracking-wider text-white uppercase shadow-sm transition-all duration-200 hover:bg-neutral-800 active:scale-95 disabled:opacity-50 dark:bg-neutral-50 dark:text-neutral-950 dark:hover:bg-neutral-200"
                            >
                                {{
                                    pinFormProcessing
                                        ? 'Đang lưu...'
                                        : 'Lưu mã PIN'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- TAB: Referrals -->
        <div v-else-if="activeTab === 'referrals'" class="space-y-8">
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Referral Code Card -->
                <Card
                    class="group relative w-full overflow-hidden rounded-2xl border border-emerald-500/20 bg-gradient-to-br from-emerald-500/[0.05] via-emerald-500/[0.02] to-transparent p-6 shadow-lg transition-all duration-300 hover:shadow-xl hover:shadow-emerald-500/[0.01] dark:border-emerald-500/10"
                >
                    <div
                        class="absolute -top-6 -right-6 h-28 w-28 animate-pulse rounded-full bg-emerald-500/10 blur-xl"
                    />

                    <div class="relative z-10 flex items-center gap-4">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        >
                            <Gift class="h-5.5 w-5.5 animate-bounce" />
                        </div>
                        <div class="space-y-0.5">
                            <h3
                                class="text-sm font-extrabold tracking-wider text-neutral-900 uppercase dark:text-neutral-50"
                            >
                                Mã giới thiệu của bạn
                            </h3>
                            <p
                                class="text-xs text-neutral-500 dark:text-neutral-400"
                            >
                                Chia sẻ mã này với đối tác và bạn bè
                            </p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-6 flex items-center gap-3">
                        <div
                            class="relative flex-1 overflow-hidden rounded-xl border border-emerald-500/20 bg-background/50 px-4 py-3 text-center font-mono text-xl font-black tracking-wider text-emerald-500 uppercase shadow-inner select-all dark:text-emerald-400"
                        >
                            <div
                                class="absolute right-0 bottom-0 left-0 h-[2px] bg-gradient-to-r from-emerald-500/15 via-emerald-500/50 to-emerald-500/15"
                            ></div>
                            {{ user?.referral_code ?? 'AVTXXXXX' }}
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="h-[52px] w-[52px] shrink-0 cursor-pointer rounded-xl border-emerald-500/20 transition-all duration-200 hover:border-emerald-500/35 hover:bg-emerald-500/10 active:scale-95"
                            @click="copyCode"
                        >
                            <Check
                                v-if="isCopied"
                                class="h-5 w-5 text-emerald-500"
                            />
                            <Copy
                                v-else
                                class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
                            />
                        </Button>
                    </div>

                    <div
                        class="relative z-10 mt-6 space-y-4 text-xs leading-relaxed font-semibold text-neutral-600 dark:text-neutral-400"
                    >
                        <div class="group/item flex items-start gap-3">
                            <span
                                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-[10px] font-black text-emerald-600 transition-colors duration-200 group-hover/item:bg-emerald-500 group-hover/item:text-white dark:text-emerald-400"
                                >1</span
                            >
                            <span
                                >Đối tác nhập mã này khi đăng ký tài khoản
                                <span
                                    class="font-bold text-emerald-600 dark:text-emerald-400"
                                    >Aventura</span
                                >.</span
                            >
                        </div>
                        <div class="group/item flex items-start gap-3">
                            <span
                                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-[10px] font-black text-emerald-600 transition-colors duration-200 group-hover/item:bg-emerald-500 group-hover/item:text-white dark:text-emerald-400"
                                >2</span
                            >
                            <span
                                >Họ nhận ngay
                                <span
                                    class="font-bold text-emerald-600 dark:text-emerald-400"
                                    >14 ngày dùng thử miễn phí</span
                                >
                                tất cả tính năng cao cấp.</span
                            >
                        </div>
                        <div class="group/item flex items-start gap-3">
                            <span
                                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-[10px] font-black text-emerald-600 transition-colors duration-200 group-hover/item:bg-emerald-500 group-hover/item:text-white dark:text-emerald-400"
                                >3</span
                            >
                            <span
                                >Nhận
                                <span
                                    class="font-bold text-emerald-600 dark:text-emerald-400"
                                    >hoa hồng trọn đời</span
                                >
                                tương ứng khi đối tác thanh toán gói cước.</span
                            >
                        </div>
                    </div>
                </Card>

                <!-- Earning Status & Withdrawal Form -->
                <Card
                    class="relative w-full overflow-hidden rounded-2xl border border-neutral-200/60 bg-card/30 p-6 shadow-lg backdrop-blur-sm dark:border-neutral-800/60"
                >
                    <div
                        class="absolute -top-16 -right-16 h-32 w-32 rounded-full bg-emerald-500/5 blur-2xl"
                    ></div>

                    <div
                        class="relative z-10 flex items-center justify-between"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border bg-muted text-foreground"
                            >
                                <Wallet class="h-5.5 w-5.5 text-emerald-500" />
                            </div>
                            <div class="space-y-0.5">
                                <h3
                                    class="text-sm font-extrabold tracking-wider text-neutral-900 uppercase dark:text-neutral-50"
                                >
                                    Số dư hoa hồng
                                </h3>
                                <p
                                    class="text-xs text-neutral-500 dark:text-neutral-400"
                                >
                                    Có thể yêu cầu rút tiền mặt
                                </p>
                            </div>
                        </div>
                        <div
                            class="rounded-2xl border border-emerald-500/10 bg-emerald-500/5 p-3 text-right shadow-inner"
                        >
                            <p
                                class="text-2xl font-black tracking-tight text-emerald-500"
                            >
                                {{
                                    formatCurrency(
                                        user?.commission_balance ?? 0,
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <form
                        @submit.prevent="submitWithdrawal"
                        class="relative z-10 mt-6 space-y-4"
                    >
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Amount -->
                            <div class="col-span-2 grid gap-1.5">
                                <Label
                                    for="amount"
                                    class="text-[10px] font-bold tracking-wider text-neutral-500 uppercase"
                                    >Số tiền muốn rút (đ)</Label
                                >
                                <div class="relative">
                                    <span
                                        class="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground/60"
                                    >
                                        <Coins class="h-4 w-4" />
                                    </span>
                                    <Input
                                        id="amount"
                                        type="number"
                                        v-model="withdrawalForm.amount"
                                        required
                                        placeholder="Tối thiểu 50,000đ"
                                        class="rounded-xl border-border bg-background/50 pl-9 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20"
                                    />
                                </div>
                                <InputError
                                    :message="withdrawalForm.errors.amount"
                                />
                            </div>

                            <!-- Bank Name -->
                            <div class="col-span-2 grid gap-1.5">
                                <Label
                                    for="bank_name"
                                    class="text-[10px] font-bold tracking-wider text-neutral-500 uppercase"
                                    >Tên ngân hàng</Label
                                >
                                <div class="relative">
                                    <span
                                        class="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground/60"
                                    >
                                        <Landmark class="h-4 w-4" />
                                    </span>
                                    <Input
                                        id="bank_name"
                                        type="text"
                                        v-model="withdrawalForm.bank_name"
                                        required
                                        placeholder="Ví dụ: Vietcombank, Techcombank..."
                                        class="rounded-xl border-border bg-background/50 pl-9 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20"
                                    />
                                </div>
                                <InputError
                                    :message="withdrawalForm.errors.bank_name"
                                />
                            </div>

                            <!-- Account Number -->
                            <div class="col-span-1 grid gap-1.5">
                                <Label
                                    for="bank_account_number"
                                    class="text-[10px] font-bold tracking-wider text-neutral-500 uppercase"
                                    >Số tài khoản</Label
                                >
                                <div class="relative">
                                    <span
                                        class="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground/60"
                                    >
                                        <CreditCard class="h-4 w-4" />
                                    </span>
                                    <Input
                                        id="bank_account_number"
                                        type="text"
                                        v-model="
                                            withdrawalForm.bank_account_number
                                        "
                                        required
                                        placeholder="Số tài khoản"
                                        class="rounded-xl border-border bg-background/50 pl-9 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20"
                                    />
                                </div>
                                <InputError
                                    :message="
                                        withdrawalForm.errors
                                            .bank_account_number
                                    "
                                />
                            </div>

                            <!-- Account Owner Name -->
                            <div class="col-span-1 grid gap-1.5">
                                <Label
                                    for="bank_account_name"
                                    class="text-[10px] font-bold tracking-wider text-neutral-500 uppercase"
                                    >Chủ tài khoản</Label
                                >
                                <div class="relative">
                                    <span
                                        class="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground/60"
                                    >
                                        <UserIcon class="h-4 w-4" />
                                    </span>
                                    <Input
                                        id="bank_account_name"
                                        type="text"
                                        v-model="
                                            withdrawalForm.bank_account_name
                                        "
                                        required
                                        placeholder="HOANG VAN A"
                                        class="rounded-xl border-border bg-background/50 pl-9 font-semibold uppercase focus-visible:border-emerald-500 focus-visible:ring-emerald-500/20"
                                    />
                                </div>
                                <InputError
                                    :message="
                                        withdrawalForm.errors.bank_account_name
                                    "
                                />
                            </div>
                        </div>

                        <Button
                            type="submit"
                            class="mt-2 flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 py-3.5 text-xs font-extrabold tracking-wider text-white uppercase shadow-lg shadow-emerald-500/10 transition-all duration-300 hover:from-emerald-500 hover:to-teal-400 active:scale-95 disabled:opacity-50"
                            :disabled="
                                withdrawalForm.processing ||
                                (user?.commission_balance ?? 0) < 50000
                            "
                        >
                            <Sparkles
                                class="h-3.5 w-3.5"
                                v-if="!withdrawalForm.processing"
                            />
                            <RefreshCw
                                class="h-3.5 w-3.5 animate-spin"
                                v-else
                            />
                            {{
                                withdrawalForm.processing
                                    ? 'Đang gửi yêu cầu...'
                                    : 'Yêu cầu rút tiền'
                            }}
                        </Button>
                    </form>
                </Card>
            </div>

            <!-- History and Details Sections -->
            <div class="space-y-4">
                <div
                    class="flex scrollbar-none gap-2 overflow-x-auto border-b border-border pb-1"
                >
                    <button
                        type="button"
                        class="flex shrink-0 cursor-pointer items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-bold tracking-wider uppercase transition-all"
                        :class="
                            referralsActiveSubTab === 'withdrawals'
                                ? 'border-emerald-500 text-emerald-500'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="referralsActiveSubTab = 'withdrawals'"
                    >
                        <History class="h-4 w-4" /> Lịch sử rút tiền
                    </button>
                    <button
                        type="button"
                        class="flex shrink-0 cursor-pointer items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-bold tracking-wider uppercase transition-all"
                        :class="
                            referralsActiveSubTab === 'referrals'
                                ? 'border-emerald-500 text-emerald-500'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="referralsActiveSubTab = 'referrals'"
                    >
                        <Users class="h-4 w-4" /> Đã giới thiệu ({{
                            referrals.length
                        }})
                    </button>
                    <button
                        type="button"
                        class="flex shrink-0 cursor-pointer items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-bold tracking-wider uppercase transition-all"
                        :class="
                            referralsActiveSubTab === 'commissions'
                                ? 'border-emerald-500 text-emerald-500'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="referralsActiveSubTab = 'commissions'"
                    >
                        <Coins class="h-4 w-4" /> Lịch sử hoa hồng
                    </button>
                </div>

                <!-- TAB: Withdrawal Requests -->
                <Card
                    v-if="referralsActiveSubTab === 'withdrawals'"
                    class="w-full overflow-hidden rounded-2xl border border-border bg-card/30 p-2 shadow-md backdrop-blur-sm"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b border-border text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <th class="p-4">Thời gian</th>
                                    <th class="p-4">Số tiền</th>
                                    <th class="p-4">Thông tin ngân hàng</th>
                                    <th class="p-4">Trạng thái</th>
                                    <th class="p-4">Phản hồi admin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="req in withdrawalRequests"
                                    :key="req.id"
                                    class="transition-colors hover:bg-muted/30"
                                >
                                    <td
                                        class="p-4 font-semibold text-muted-foreground"
                                    >
                                        {{ req.created_at }}
                                    </td>
                                    <td
                                        class="p-4 text-sm font-extrabold text-foreground"
                                    >
                                        {{ formatCurrency(req.amount) }}
                                    </td>
                                    <td class="p-4">
                                        <div class="font-bold text-foreground">
                                            {{ req.bank_name }}
                                        </div>
                                        <div
                                            class="mt-0.5 text-[10px] font-semibold text-muted-foreground"
                                        >
                                            {{ req.bank_account_number }} ·
                                            {{ req.bank_account_name }}
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span
                                            v-if="req.status === 'pending'"
                                            class="inline-flex items-center rounded-full border border-amber-500/25 bg-amber-500/10 px-2 py-0.5 text-[9px] font-bold text-amber-500 uppercase"
                                        >
                                            Chờ duyệt
                                        </span>
                                        <span
                                            v-else-if="
                                                req.status === 'approved'
                                            "
                                            class="inline-flex items-center rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2 py-0.5 text-[9px] font-bold text-emerald-500 uppercase dark:text-emerald-400"
                                        >
                                            Đã duyệt
                                        </span>
                                        <span
                                            v-else-if="
                                                req.status === 'rejected'
                                            "
                                            class="inline-flex items-center rounded-full border border-rose-500/25 bg-rose-500/10 px-2 py-0.5 text-[9px] font-bold text-rose-500 uppercase"
                                        >
                                            Từ chối
                                        </span>
                                    </td>
                                    <td
                                        class="p-4 text-[11px] font-medium text-muted-foreground italic"
                                    >
                                        {{ req.notes || '—' }}
                                    </td>
                                </tr>
                                <tr v-if="withdrawalRequests.length === 0">
                                    <td
                                        colspan="5"
                                        class="p-8 text-center font-medium text-muted-foreground/80 italic"
                                    >
                                        Chưa có yêu cầu rút tiền nào được tạo.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>

                <!-- TAB: Referrals List -->
                <Card
                    v-if="referralsActiveSubTab === 'referrals'"
                    class="w-full overflow-hidden rounded-2xl border border-border bg-card/30 p-2 shadow-md backdrop-blur-sm"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b border-border text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <th class="p-4">Họ và tên</th>
                                    <th class="p-4">Ngày đăng ký</th>
                                    <th class="p-4">Trạng thái tài khoản</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="ref in referrals"
                                    :key="ref.name"
                                    class="transition-colors hover:bg-muted/30"
                                >
                                    <td class="p-4 font-bold text-foreground">
                                        {{ ref.name }}
                                    </td>
                                    <td
                                        class="p-4 font-semibold text-muted-foreground"
                                    >
                                        {{ ref.created_at }}
                                    </td>
                                    <td class="p-4">
                                        <span
                                            v-if="ref.status === 'active'"
                                            class="inline-flex items-center rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2 py-0.5 text-[9px] font-bold text-emerald-500 uppercase dark:text-emerald-400"
                                        >
                                            Hoạt động
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full border bg-muted px-2 py-0.5 text-[9px] font-bold text-muted-foreground uppercase"
                                        >
                                            Không hoạt động
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="referrals.length === 0">
                                    <td
                                        colspan="3"
                                        class="flex flex-col items-center justify-center gap-2 p-10 text-center text-muted-foreground"
                                    >
                                        <Users
                                            class="h-8 w-8 text-muted-foreground/60"
                                        />
                                        <p
                                            class="text-sm font-bold text-foreground"
                                        >
                                            Bạn chưa giới thiệu thành viên nào
                                        </p>
                                        <p
                                            class="max-w-sm text-xs text-muted-foreground"
                                        >
                                            Hãy chia sẻ mã giới thiệu phía trên
                                            với đối tác và bạn bè để cùng nhận
                                            nhiều ưu đãi.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>

                <!-- TAB: Commissions -->
                <Card
                    v-if="referralsActiveSubTab === 'commissions'"
                    class="w-full overflow-hidden rounded-2xl border border-border bg-card/30 p-2 shadow-md backdrop-blur-sm"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b border-border text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <th class="p-4">Người mua</th>
                                    <th class="p-4">Chi tiết thanh toán</th>
                                    <th class="p-4">Tỷ lệ %</th>
                                    <th class="p-4">Số tiền nhận</th>
                                    <th class="p-4">Ngày nhận</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="log in commissionLogs"
                                    :key="log.id"
                                    class="transition-colors hover:bg-muted/30"
                                >
                                    <td class="p-4 font-bold text-foreground">
                                        <div>{{ log.buyer_name }}</div>
                                        <div
                                            class="mt-0.5 text-[10px] font-semibold text-muted-foreground"
                                        >
                                            {{ log.restaurant_name }}
                                        </div>
                                    </td>
                                    <td
                                        class="p-4 font-semibold text-muted-foreground"
                                    >
                                        Giá trị đơn:
                                        <span
                                            class="font-extrabold text-foreground"
                                            >{{
                                                formatCurrency(log.amount)
                                            }}</span
                                        >
                                    </td>
                                    <td
                                        class="p-4 font-extrabold text-teal-500"
                                    >
                                        {{ log.commission_percentage }}%
                                    </td>
                                    <td
                                        class="p-4 font-extrabold text-emerald-500"
                                    >
                                        +{{
                                            formatCurrency(
                                                log.commission_amount,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="p-4 font-semibold text-muted-foreground"
                                    >
                                        {{ log.created_at }}
                                    </td>
                                </tr>
                                <tr v-if="commissionLogs.length === 0">
                                    <td
                                        colspan="5"
                                        class="flex flex-col items-center justify-center gap-2 p-10 text-center text-muted-foreground"
                                    >
                                        <Coins
                                            class="h-8 w-8 text-muted-foreground/60"
                                        />
                                        <p
                                            class="text-sm font-bold text-foreground"
                                        >
                                            Chưa phát sinh hoa hồng nào
                                        </p>
                                        <p
                                            class="max-w-sm text-xs text-muted-foreground"
                                        >
                                            Hoa hồng tích lũy sẽ tự động cộng
                                            dồn vào tài khoản của bạn khi người
                                            dùng được giới thiệu thanh toán hoặc
                                            nâng cấp gói cước thành công.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </div>
    </div>
</template>
