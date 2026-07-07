<script setup lang="ts">
import { Form, Head, Link, usePage, useForm, router } from '@inertiajs/vue3';
import { User as UserIcon, Mail, ShieldCheck, Lock, Check, Copy, Gift, History, Landmark, Users, Coins, CreditCard, Wallet, ChevronRight, KeyRound } from 'lucide-vue-next';
import { computed, ref, onUnmounted } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import DeleteUser from '@/components/DeleteUser.vue';
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
                title: 'Profile settings',
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
        setTimeout(() => isCopied.value = false, 2000);
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

const referralsActiveSubTab = ref<'withdrawals' | 'referrals' | 'commissions'>('withdrawals');

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
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
    router.put('/settings/pin', {
        pin_code: pinForm.value.pin_code,
        pin_code_confirmation: pinForm.value.pin_code_confirmation,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            pinForm.value.pin_code = '';
            pinForm.value.pin_code_confirmation = '';
            import('vue-sonner').then(m => m.toast.success('Đã cập nhật mã PIN phê duyệt thành công!'));
        },
        onError: (errs: any) => {
            pinErrors.value = errs;
            import('vue-sonner').then(m => m.toast.error('Có lỗi xảy ra khi lưu mã PIN.'));
        },
        onFinish: () => {
            pinFormProcessing.value = false;
        }
    });
};
</script>

<template>
    <Head :title="activeTab === 'profile' ? 'Profile settings' : (activeTab === 'security' ? 'Security settings' : 'Giới thiệu & Hoa hồng')" />

    <h1 class="sr-only">
        {{ activeTab === 'profile' ? 'Profile settings' : (activeTab === 'security' ? 'Security settings' : 'Giới thiệu & Hoa hồng') }}
    </h1>

    <div class="space-y-6">
        <!-- TAB: Profile -->
        <div v-if="activeTab === 'profile'" class="space-y-6">
            <Card class="w-full border border-neutral-200/60 dark:border-neutral-800/60 shadow-xs rounded-2xl overflow-hidden bg-white/70 dark:bg-neutral-900/40 backdrop-blur-md">
                <CardHeader class="flex flex-row items-center gap-4 border-b border-neutral-100 dark:border-neutral-800 pb-5 px-6 pt-6">
                    <div class="p-2.5 bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 rounded-xl shrink-0">
                        <UserIcon class="w-5 h-5" />
                    </div>
                    <div class="space-y-0.5">
                        <CardTitle class="text-lg font-black text-neutral-900 dark:text-neutral-50">Thông tin hồ sơ</CardTitle>
                        <CardDescription class="text-xs text-neutral-500 dark:text-neutral-400">Cập nhật họ tên và hòm thư điện tử của bạn</CardDescription>
                    </div>
                </CardHeader>
                <CardContent class="p-6">
                    <Form
                        v-bind="ProfileController.update.form()"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="name" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Họ và tên</Label>
                            <Input
                                id="name"
                                class="mt-1 block w-full rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800 dark:focus:ring-neutral-50 dark:focus:border-neutral-50"
                                name="name"
                                :default-value="user.name"
                                required
                                autocomplete="name"
                                placeholder="Họ và tên"
                            />
                            <InputError class="mt-2" :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Địa chỉ email</Label>
                            <div class="relative">
                                <Input
                                    id="email"
                                    type="email"
                                    class="mt-1 block w-full rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800 dark:focus:ring-neutral-50 dark:focus:border-neutral-50"
                                    name="email"
                                    :default-value="user.email"
                                    required
                                    autocomplete="username"
                                    placeholder="Địa chỉ email"
                                />
                            </div>
                            <InputError class="mt-2" :message="errors.email" />
                        </div>

                        <div v-if="mustVerifyEmail && !user.email_verified_at" class="p-4 bg-amber-500/10 border border-amber-500/20 rounded-xl">
                            <p class="text-xs text-amber-700 dark:text-amber-400 flex flex-col sm:flex-row sm:items-center gap-1.5 font-medium leading-relaxed">
                                <span>Địa chỉ email của bạn chưa được xác minh.</span>
                                <Link
                                    :href="send()"
                                    as="button"
                                    class="text-amber-800 dark:text-amber-300 underline font-bold hover:text-amber-600 transition-colors"
                                >
                                    Nhấn vào đây để gửi lại email xác minh.
                                </Link>
                            </p>

                            <div
                                v-if="status === 'verification-link-sent'"
                                class="mt-2 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                            >
                                Liên kết xác minh mới đã được gửi đến địa chỉ email của bạn.
                            </div>
                        </div>

                        <div class="flex items-center gap-4 pt-2">
                            <Button 
                                :disabled="processing" 
                                data-test="update-profile-button"
                                class="bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-neutral-50 dark:text-neutral-950 dark:hover:bg-neutral-200 px-6 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider transition-all duration-200 shadow-sm active:scale-95 cursor-pointer disabled:opacity-50"
                            >
                                Lưu thay đổi
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
            <DeleteUser />
        </div>

        <!-- TAB: Security -->
        <div v-else-if="activeTab === 'security'" class="space-y-6">
            <!-- Update password card -->
            <Card class="w-full border border-neutral-200/60 dark:border-neutral-800/60 shadow-xs rounded-2xl overflow-hidden bg-white/70 dark:bg-neutral-900/40 backdrop-blur-md">
                <CardHeader class="flex flex-row items-center gap-4 border-b border-neutral-100 dark:border-neutral-800 pb-5 px-6 pt-6">
                    <div class="p-2.5 bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 rounded-xl shrink-0">
                        <Lock class="w-5 h-5" />
                    </div>
                    <div class="space-y-0.5">
                        <CardTitle class="text-lg font-black text-neutral-900 dark:text-neutral-50">Cập nhật mật khẩu</CardTitle>
                        <CardDescription class="text-xs text-neutral-500 dark:text-neutral-400">Đảm bảo tài khoản của bạn sử dụng mật khẩu mạnh để bảo mật thông tin</CardDescription>
                    </div>
                </CardHeader>
                <CardContent class="p-6">
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
                            <Label for="current_password" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Mật khẩu hiện tại</Label>
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                class="mt-1 block w-full rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                                autocomplete="current-password"
                                placeholder="Mật khẩu hiện tại"
                            />
                            <InputError :message="errors.current_password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Mật khẩu mới</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                class="mt-1 block w-full rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                                autocomplete="new-password"
                                placeholder="Mật khẩu mới"
                                :passwordrules="props.passwordRules"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Xác nhận mật khẩu mới</Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                class="mt-1 block w-full rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                                autocomplete="new-password"
                                placeholder="Xác nhận mật khẩu mới"
                                :passwordrules="props.passwordRules"
                            />
                            <InputError :message="errors.password_confirmation" />
                        </div>

                        <div class="flex items-center gap-4 pt-2">
                            <Button
                                :disabled="processing"
                                data-test="update-password-button"
                                class="bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-neutral-50 dark:text-neutral-950 dark:hover:bg-neutral-200 px-6 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider transition-all duration-200 shadow-sm active:scale-95 cursor-pointer disabled:opacity-50"
                            >
                                Lưu mật khẩu
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>

            <!-- Two-factor authentication card -->
            <Card v-if="canManageTwoFactor" class="w-full border border-neutral-200/60 dark:border-neutral-800/60 shadow-xs rounded-2xl overflow-hidden bg-white/70 dark:bg-neutral-900/40 backdrop-blur-md">
                <CardHeader class="flex flex-row items-center gap-4 border-b border-neutral-100 dark:border-neutral-800 pb-5 px-6 pt-6">
                    <div class="p-2.5 bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 rounded-xl shrink-0">
                        <ShieldCheck class="w-5 h-5" />
                    </div>
                    <div class="space-y-0.5">
                        <CardTitle class="text-lg font-black text-neutral-900 dark:text-neutral-50">Xác thực hai yếu tố (2FA)</CardTitle>
                        <CardDescription class="text-xs text-neutral-500 dark:text-neutral-400">Yêu cầu mã xác minh an toàn từ điện thoại khi đăng nhập tài khoản</CardDescription>
                    </div>
                </CardHeader>
                <CardContent class="p-6">
                    <div
                        v-if="!twoFactorEnabled"
                        class="flex flex-col items-start justify-start space-y-4"
                    >
                        <p class="text-xs font-semibold leading-relaxed text-neutral-500 dark:text-neutral-400">
                            Khi bật xác thực hai yếu tố, hệ thống sẽ yêu cầu bạn cung cấp mã xác nhận bảo mật từ ứng dụng tạo mã (như Google Authenticator) trên điện thoại của bạn lúc đăng nhập.
                        </p>

                        <div class="pt-2">
                            <Button v-if="hasSetupData" @click="showSetupModal = true" class="bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-neutral-50 dark:text-neutral-950 dark:hover:bg-neutral-200 rounded-xl font-bold text-xs uppercase tracking-wider px-5 py-2.5 transition-all shadow-sm active:scale-95 cursor-pointer">
                                <ShieldCheck class="mr-1.5 size-4" /> Tiếp tục thiết lập
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
                                    class="bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-neutral-50 dark:text-neutral-950 dark:hover:bg-neutral-200 rounded-xl font-bold text-xs uppercase tracking-wider px-5 py-2.5 transition-all shadow-sm active:scale-95 cursor-pointer disabled:opacity-50"
                                >
                                    Kích hoạt 2FA
                                </Button>
                            </Form>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-start justify-start space-y-5">
                        <p class="text-xs font-semibold leading-relaxed text-neutral-500 dark:text-neutral-400">
                            Xác thực hai yếu tố đã được kích hoạt. Hãy dùng ứng dụng tạo mã trên điện thoại để lấy mã xác nhận mỗi lần đăng nhập.
                        </p>

                        <div class="relative inline pt-2">
                            <Form v-bind="disable.form()" #default="{ processing }">
                                <Button
                                    variant="destructive"
                                    type="submit"
                                    :disabled="processing"
                                    class="bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider px-5 py-2.5 transition-all shadow-sm active:scale-95 cursor-pointer disabled:opacity-50"
                                >
                                    Hủy kích hoạt 2FA
                                </Button>
                            </Form>
                        </div>

                        <div class="w-full pt-4 border-t border-neutral-100 dark:border-neutral-800">
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
            <Card v-if="user?.roles?.some((r: any) => ['owner', 'manager'].includes(r.name))" class="w-full border border-neutral-200/60 dark:border-neutral-800/60 shadow-xs rounded-2xl overflow-hidden bg-white/70 dark:bg-neutral-900/40 backdrop-blur-md">
                <CardHeader class="flex flex-row items-center gap-4 border-b border-neutral-100 dark:border-neutral-800 pb-5 px-6 pt-6">
                    <div class="p-2.5 bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 rounded-xl shrink-0">
                        <KeyRound class="w-5 h-5" />
                    </div>
                    <div class="space-y-0.5">
                        <CardTitle class="text-lg font-black text-neutral-900 dark:text-neutral-50">Mã PIN Phê Duyệt POS (Bypass PIN)</CardTitle>
                        <CardDescription class="text-xs text-neutral-500 dark:text-neutral-400">Thiết lập mã PIN 4-6 số để phê duyệt nhanh các thao tác hủy đơn/sửa giá/hoàn tiền trên POS</CardDescription>
                    </div>
                </CardHeader>
                <CardContent class="p-6">
                    <form @submit.prevent="updatePin" class="space-y-6">
                        <div class="grid gap-2">
                            <Label for="pin_code" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Mã PIN mới (4 - 6 số)</Label>
                            <Input
                                id="pin_code"
                                type="password"
                                pattern="[0-9]*"
                                inputmode="numeric"
                                maxlength="6"
                                v-model="pinForm.pin_code"
                                class="mt-1 block w-full rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                                placeholder="Nhập mã PIN gồm 4 đến 6 chữ số"
                                required
                            />
                            <p v-if="pinErrors.pin_code" class="text-xs text-red-600 mt-1">{{ pinErrors.pin_code }}</p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="pin_code_confirmation" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Xác nhận mã PIN mới</Label>
                            <Input
                                id="pin_code_confirmation"
                                type="password"
                                pattern="[0-9]*"
                                inputmode="numeric"
                                maxlength="6"
                                v-model="pinForm.pin_code_confirmation"
                                class="mt-1 block w-full rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                                placeholder="Xác nhận mã PIN mới"
                                required
                            />
                            <p v-if="pinErrors.pin_code_confirmation" class="text-xs text-red-600 mt-1">{{ pinErrors.pin_code_confirmation }}</p>
                        </div>

                        <div class="flex items-center gap-4 pt-2">
                            <Button
                                type="submit"
                                :disabled="pinFormProcessing"
                                class="bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-neutral-50 dark:text-neutral-950 dark:hover:bg-neutral-200 px-6 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider transition-all duration-200 shadow-sm active:scale-95 cursor-pointer disabled:opacity-50"
                            >
                                {{ pinFormProcessing ? 'Đang lưu...' : 'Lưu mã PIN' }}
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
                <Card class="w-full relative overflow-hidden border border-emerald-500/20 dark:border-emerald-500/10 bg-gradient-to-br from-emerald-500/[0.05] via-emerald-500/[0.02] to-transparent p-6 shadow-lg rounded-2xl group hover:shadow-xl hover:shadow-emerald-500/[0.01] transition-all duration-300">
                    <div class="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-emerald-500/10 blur-xl animate-pulse" />
                    
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shrink-0">
                            <Gift class="h-5.5 w-5.5 animate-bounce" />
                        </div>
                        <div class="space-y-0.5">
                            <h3 class="text-sm font-extrabold text-neutral-900 dark:text-neutral-50 uppercase tracking-wider">Mã giới thiệu của bạn</h3>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Chia sẻ mã này với đối tác và bạn bè</p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center gap-3 relative z-10">
                        <div class="flex-1 rounded-xl border border-emerald-500/20 bg-background/50 px-4 py-3 font-mono text-xl font-black tracking-wider text-center uppercase shadow-inner text-emerald-500 dark:text-emerald-400 select-all relative overflow-hidden">
                            <div class="absolute bottom-0 left-0 right-0 h-[2px] bg-gradient-to-r from-emerald-500/15 via-emerald-500/50 to-emerald-500/15"></div>
                            {{ user?.referral_code ?? 'AVTXXXXX' }}
                        </div>
                        <Button 
                            type="button" 
                            variant="outline" 
                            size="icon" 
                            class="h-[52px] w-[52px] rounded-xl border-emerald-500/20 hover:bg-emerald-500/10 hover:border-emerald-500/35 cursor-pointer transition-all active:scale-95 duration-200 shrink-0"
                            @click="copyCode"
                        >
                            <Check v-if="isCopied" class="h-5 w-5 text-emerald-500" />
                            <Copy v-else class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        </Button>
                    </div>

                    <div class="mt-6 space-y-4 text-xs text-neutral-600 dark:text-neutral-400 font-semibold leading-relaxed relative z-10">
                        <div class="flex items-start gap-3 group/item">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-[10px] font-black text-emerald-600 dark:text-emerald-400 group-hover/item:bg-emerald-500 group-hover/item:text-white transition-colors duration-200">1</span>
                            <span>Đối tác nhập mã này khi đăng ký tài khoản <span class="text-emerald-600 dark:text-emerald-400 font-bold">Aventura</span>.</span>
                        </div>
                        <div class="flex items-start gap-3 group/item">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-[10px] font-black text-emerald-600 dark:text-emerald-400 group-hover/item:bg-emerald-500 group-hover/item:text-white transition-colors duration-200">2</span>
                            <span>Họ nhận ngay <span class="text-emerald-600 dark:text-emerald-400 font-bold">14 ngày dùng thử miễn phí</span> tất cả tính năng cao cấp.</span>
                        </div>
                        <div class="flex items-start gap-3 group/item">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-[10px] font-black text-emerald-600 dark:text-emerald-400 group-hover/item:bg-emerald-500 group-hover/item:text-white transition-colors duration-200">3</span>
                            <span>Nhận <span class="text-emerald-600 dark:text-emerald-400 font-bold">hoa hồng trọn đời</span> tương ứng khi đối tác thanh toán gói cước.</span>
                        </div>
                    </div>
                </Card>

                <!-- Earning Status & Withdrawal Form -->
                <Card class="w-full border border-neutral-200/60 dark:border-neutral-800/60 shadow-lg rounded-2xl overflow-hidden bg-card/30 backdrop-blur-sm p-6 relative">
                    <div class="absolute -right-16 -top-16 w-32 h-32 bg-emerald-500/5 rounded-full blur-2xl"></div>

                    <div class="flex items-center justify-between relative z-10">
                        <div class="flex items-center gap-4">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-muted text-foreground shrink-0 border">
                                <Wallet class="h-5.5 w-5.5 text-emerald-500" />
                            </div>
                            <div class="space-y-0.5">
                                <h3 class="text-sm font-extrabold text-neutral-900 dark:text-neutral-50 uppercase tracking-wider">Số dư hoa hồng</h3>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">Có thể yêu cầu rút tiền mặt</p>
                            </div>
                        </div>
                        <div class="text-right p-3 rounded-2xl bg-emerald-500/5 border border-emerald-500/10 shadow-inner">
                            <p class="text-2xl font-black text-emerald-500 tracking-tight">{{ formatCurrency(user?.commission_balance ?? 0) }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitWithdrawal" class="mt-6 space-y-4 relative z-10">
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Amount -->
                            <div class="grid gap-1.5 col-span-2">
                                <Label for="amount" class="text-[10px] font-bold uppercase tracking-wider text-neutral-500">Số tiền muốn rút (đ)</Label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground/60">
                                        <Coins class="w-4 h-4" />
                                    </span>
                                    <Input
                                        id="amount"
                                        type="number"
                                        v-model="withdrawalForm.amount"
                                        required
                                        placeholder="Tối thiểu 50,000đ"
                                        class="pl-9 rounded-xl border-border bg-background/50 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500"
                                    />
                                </div>
                                <InputError :message="withdrawalForm.errors.amount" />
                            </div>

                            <!-- Bank Name -->
                            <div class="grid gap-1.5 col-span-2">
                                <Label for="bank_name" class="text-[10px] font-bold uppercase tracking-wider text-neutral-500">Tên ngân hàng</Label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground/60">
                                        <Landmark class="w-4 h-4" />
                                    </span>
                                    <Input
                                        id="bank_name"
                                        type="text"
                                        v-model="withdrawalForm.bank_name"
                                        required
                                        placeholder="Ví dụ: Vietcombank, Techcombank..."
                                        class="pl-9 rounded-xl border-border bg-background/50 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500"
                                    />
                                </div>
                                <InputError :message="withdrawalForm.errors.bank_name" />
                            </div>

                            <!-- Account Number -->
                            <div class="grid gap-1.5 col-span-1">
                                <Label for="bank_account_number" class="text-[10px] font-bold uppercase tracking-wider text-neutral-500">Số tài khoản</Label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground/60">
                                        <CreditCard class="w-4 h-4" />
                                    </span>
                                    <Input
                                        id="bank_account_number"
                                        type="text"
                                        v-model="withdrawalForm.bank_account_number"
                                        required
                                        placeholder="Số tài khoản"
                                        class="pl-9 rounded-xl border-border bg-background/50 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500"
                                    />
                                </div>
                                <InputError :message="withdrawalForm.errors.bank_account_number" />
                            </div>

                            <!-- Account Owner Name -->
                            <div class="grid gap-1.5 col-span-1">
                                <Label for="bank_account_name" class="text-[10px] font-bold uppercase tracking-wider text-neutral-500">Chủ tài khoản</Label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground/60">
                                        <UserIcon class="w-4 h-4" />
                                    </span>
                                    <Input
                                        id="bank_account_name"
                                        type="text"
                                        v-model="withdrawalForm.bank_account_name"
                                        required
                                        placeholder="HOANG VAN A"
                                        class="pl-9 rounded-xl border-border bg-background/50 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500 uppercase font-semibold"
                                    />
                                </div>
                                <InputError :message="withdrawalForm.errors.bank_account_name" />
                            </div>
                        </div>

                        <Button
                            type="submit"
                            class="w-full bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white font-extrabold text-xs uppercase tracking-wider transition-all duration-300 shadow-lg shadow-emerald-500/10 rounded-xl py-3.5 active:scale-95 cursor-pointer disabled:opacity-50 mt-2 flex items-center justify-center gap-1.5"
                            :disabled="withdrawalForm.processing || (user?.commission_balance ?? 0) < 50000"
                        >
                            <Sparkles class="w-3.5 h-3.5" v-if="!withdrawalForm.processing" />
                            <RefreshCw class="w-3.5 h-3.5 animate-spin" v-else />
                            {{ withdrawalForm.processing ? 'Đang gửi yêu cầu...' : 'Yêu cầu rút tiền' }}
                        </Button>
                    </form>
                </Card>
            </div>

            <!-- History and Details Sections -->
            <div class="space-y-4">
                <div class="flex overflow-x-auto gap-2 pb-1 scrollbar-none border-b border-border">
                    <button
                        type="button"
                        class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 cursor-pointer transition-all shrink-0 flex items-center gap-2"
                        :class="referralsActiveSubTab === 'withdrawals' ? 'border-emerald-500 text-emerald-500' : 'border-transparent text-muted-foreground hover:text-foreground'"
                        @click="referralsActiveSubTab = 'withdrawals'"
                    >
                        <History class="h-4 w-4" /> Lịch sử rút tiền
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 cursor-pointer transition-all shrink-0 flex items-center gap-2"
                        :class="referralsActiveSubTab === 'referrals' ? 'border-emerald-500 text-emerald-500' : 'border-transparent text-muted-foreground hover:text-foreground'"
                        @click="referralsActiveSubTab = 'referrals'"
                    >
                        <Users class="h-4 w-4" /> Đã giới thiệu ({{ referrals.length }})
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 cursor-pointer transition-all shrink-0 flex items-center gap-2"
                        :class="referralsActiveSubTab === 'commissions' ? 'border-emerald-500 text-emerald-500' : 'border-transparent text-muted-foreground hover:text-foreground'"
                        @click="referralsActiveSubTab = 'commissions'"
                    >
                        <Coins class="h-4 w-4" /> Lịch sử hoa hồng
                    </button>
                </div>

                <!-- TAB: Withdrawal Requests -->
                <Card v-if="referralsActiveSubTab === 'withdrawals'" class="w-full border border-border shadow-md rounded-2xl overflow-hidden bg-card/30 backdrop-blur-sm p-2">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-border text-muted-foreground uppercase text-[10px] font-bold tracking-wider">
                                    <th class="p-4">Thời gian</th>
                                    <th class="p-4">Số tiền</th>
                                    <th class="p-4">Thông tin ngân hàng</th>
                                    <th class="p-4">Trạng thái</th>
                                    <th class="p-4">Phản hồi admin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="req in withdrawalRequests" :key="req.id" class="hover:bg-muted/30 transition-colors">
                                    <td class="p-4 font-semibold text-muted-foreground">{{ req.created_at }}</td>
                                    <td class="p-4 font-extrabold text-foreground text-sm">{{ formatCurrency(req.amount) }}</td>
                                    <td class="p-4">
                                         <div class="font-bold text-foreground">{{ req.bank_name }}</div>
                                         <div class="text-[10px] text-muted-foreground mt-0.5 font-semibold">{{ req.bank_account_number }} · {{ req.bank_account_name }}</div>
                                    </td>
                                    <td class="p-4">
                                         <span v-if="req.status === 'pending'" class="inline-flex items-center rounded-full bg-amber-500/10 border border-amber-500/25 px-2 py-0.5 text-[9px] font-bold uppercase text-amber-500">
                                             Chờ duyệt
                                         </span>
                                         <span v-else-if="req.status === 'approved'" class="inline-flex items-center rounded-full bg-emerald-500/10 border border-emerald-500/25 px-2 py-0.5 text-[9px] font-bold uppercase text-emerald-500 dark:text-emerald-400">
                                             Đã duyệt
                                         </span>
                                         <span v-else-if="req.status === 'rejected'" class="inline-flex items-center rounded-full bg-rose-500/10 border border-rose-500/25 px-2 py-0.5 text-[9px] font-bold uppercase text-rose-500">
                                             Từ chối
                                         </span>
                                    </td>
                                    <td class="p-4 text-muted-foreground italic text-[11px] font-medium">{{ req.notes || '—' }}</td>
                                </tr>
                                <tr v-if="withdrawalRequests.length === 0">
                                    <td colspan="5" class="p-8 text-center text-muted-foreground/80 font-medium italic">
                                         Chưa có yêu cầu rút tiền nào được tạo.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>

                <!-- TAB: Referrals List -->
                <Card v-if="referralsActiveSubTab === 'referrals'" class="w-full border border-border shadow-md rounded-2xl overflow-hidden bg-card/30 backdrop-blur-sm p-2">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-border text-muted-foreground uppercase text-[10px] font-bold tracking-wider">
                                    <th class="p-4">Họ và tên</th>
                                    <th class="p-4">Ngày đăng ký</th>
                                    <th class="p-4">Trạng thái tài khoản</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="ref in referrals" :key="ref.name" class="hover:bg-muted/30 transition-colors">
                                    <td class="p-4 font-bold text-foreground">{{ ref.name }}</td>
                                    <td class="p-4 text-muted-foreground font-semibold">{{ ref.created_at }}</td>
                                    <td class="p-4">
                                         <span v-if="ref.status === 'active'" class="inline-flex items-center rounded-full bg-emerald-500/10 border border-emerald-500/25 px-2 py-0.5 text-[9px] font-bold uppercase text-emerald-500 dark:text-emerald-400">
                                             Hoạt động
                                         </span>
                                         <span v-else class="inline-flex items-center rounded-full bg-muted border px-2 py-0.5 text-[9px] font-bold uppercase text-muted-foreground">
                                             Không hoạt động
                                         </span>
                                    </td>
                                </tr>
                                <tr v-if="referrals.length === 0">
                                    <td colspan="3" class="p-10 text-center text-muted-foreground flex flex-col items-center justify-center gap-2">
                                         <Users class="w-8 h-8 text-muted-foreground/60" />
                                         <p class="font-bold text-sm text-foreground">Bạn chưa giới thiệu thành viên nào</p>
                                         <p class="text-xs text-muted-foreground max-w-sm">Hãy chia sẻ mã giới thiệu phía trên với đối tác và bạn bè để cùng nhận nhiều ưu đãi.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>

                <!-- TAB: Commissions -->
                <Card v-if="referralsActiveSubTab === 'commissions'" class="w-full border border-border shadow-md rounded-2xl overflow-hidden bg-card/30 backdrop-blur-sm p-2">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-border text-muted-foreground uppercase text-[10px] font-bold tracking-wider">
                                    <th class="p-4">Người mua</th>
                                    <th class="p-4">Chi tiết thanh toán</th>
                                    <th class="p-4">Tỷ lệ %</th>
                                    <th class="p-4">Số tiền nhận</th>
                                    <th class="p-4">Ngày nhận</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="log in commissionLogs" :key="log.id" class="hover:bg-muted/30 transition-colors">
                                    <td class="p-4 font-bold text-foreground">
                                         <div>{{ log.buyer_name }}</div>
                                         <div class="text-[10px] text-muted-foreground font-semibold mt-0.5">{{ log.restaurant_name }}</div>
                                    </td>
                                    <td class="p-4 font-semibold text-muted-foreground">Giá trị đơn: <span class="font-extrabold text-foreground">{{ formatCurrency(log.amount) }}</span></td>
                                    <td class="p-4 font-extrabold text-teal-500">{{ log.commission_percentage }}%</td>
                                    <td class="p-4 font-extrabold text-emerald-500">+{{ formatCurrency(log.commission_amount) }}</td>
                                    <td class="p-4 text-muted-foreground font-semibold">{{ log.created_at }}</td>
                                </tr>
                                <tr v-if="commissionLogs.length === 0">
                                    <td colspan="5" class="p-10 text-center text-muted-foreground flex flex-col items-center justify-center gap-2">
                                         <Coins class="w-8 h-8 text-muted-foreground/60" />
                                         <p class="font-bold text-sm text-foreground">Chưa phát sinh hoa hồng nào</p>
                                         <p class="text-xs text-muted-foreground max-w-sm">Hoa hồng tích lũy sẽ tự động cộng dồn vào tài khoản của bạn khi người dùng được giới thiệu thanh toán hoặc nâng cấp gói cước thành công.</p>
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
