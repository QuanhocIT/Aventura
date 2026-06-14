<script setup lang="ts">
import { Form, Head, Link, usePage, useForm } from '@inertiajs/vue3';
import { User as UserIcon, Mail, ShieldCheck, Lock, Check, Copy, Gift, History, Landmark, Users } from 'lucide-vue-next';
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
        </div>

        <!-- TAB: Referrals -->
        <div v-else-if="activeTab === 'referrals'" class="space-y-8">
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Referral Code Card -->
                <Card class="w-full relative overflow-hidden border border-emerald-500/20 dark:border-emerald-500/10 bg-gradient-to-br from-emerald-500/[0.04] via-emerald-500/[0.01] to-transparent p-6 shadow-xs rounded-2xl">
                    <div class="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-emerald-500/10 blur-xl animate-pulse" />
                    
                    <div class="flex items-center gap-4">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shrink-0">
                            <Gift class="h-5.5 w-5.5 animate-bounce" />
                        </div>
                        <div class="space-y-0.5">
                            <h3 class="text-sm font-black text-neutral-900 dark:text-neutral-50 uppercase tracking-wider">Mã giới thiệu của bạn</h3>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Chia sẻ mã này với đối tác và bạn bè</p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center gap-3">
                        <div class="flex-1 rounded-xl border border-emerald-500/20 bg-white dark:bg-neutral-950 px-4 py-3 font-mono text-xl font-black tracking-wider text-center uppercase shadow-inner text-emerald-600 dark:text-emerald-400">
                            {{ user?.referral_code ?? 'AVTXXXXX' }}
                        </div>
                        <Button 
                            type="button" 
                            variant="outline" 
                            size="icon" 
                            class="h-[52px] w-[52px] rounded-xl border-emerald-500/20 hover:bg-emerald-500/10 cursor-pointer"
                            @click="copyCode"
                        >
                            <Check v-if="isCopied" class="h-5 w-5 text-emerald-500" />
                            <Copy v-else class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        </Button>
                    </div>

                    <div class="mt-6 space-y-3 text-xs text-neutral-600 dark:text-neutral-400 font-semibold leading-relaxed">
                        <div class="flex items-start gap-2.5">
                            <span class="mt-0.5 flex h-4.5 w-4.5 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-[10px] font-black text-emerald-600 dark:text-emerald-400">1</span>
                            <span>Đối tác nhập mã này khi đăng ký tài khoản Aventura.</span>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <span class="mt-0.5 flex h-4.5 w-4.5 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-[10px] font-black text-emerald-600 dark:text-emerald-400">2</span>
                            <span>Họ nhận ngay 14 ngày dùng thử miễn phí tất cả tính năng.</span>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <span class="mt-0.5 flex h-4.5 w-4.5 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-[10px] font-black text-emerald-600 dark:text-emerald-400">3</span>
                            <span>Nhận hoa hồng tương ứng khi đối tác mua gói dịch vụ chính thức.</span>
                        </div>
                    </div>
                </Card>

                <!-- Earning Status & Withdrawal Form -->
                <Card class="w-full border border-neutral-200/60 dark:border-neutral-800/60 shadow-xs rounded-2xl overflow-hidden bg-white/70 dark:bg-neutral-900/40 backdrop-blur-md p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 shrink-0">
                                <Landmark class="h-5.5 w-5.5" />
                            </div>
                            <div class="space-y-0.5">
                                <h3 class="text-sm font-black text-neutral-900 dark:text-neutral-50 uppercase tracking-wider">Số dư hoa hồng</h3>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">Có thể yêu cầu rút tiền mặt</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xl font-black text-emerald-600 dark:text-emerald-400">{{ formatCurrency(user?.commission_balance ?? 0) }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitWithdrawal" class="mt-6 space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5 col-span-2">
                                <Label for="amount" class="text-[10px] font-black uppercase tracking-wider text-neutral-500">Số tiền muốn rút (đ)</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    v-model="withdrawalForm.amount"
                                    required
                                    placeholder="Tối thiểu 50,000đ"
                                    class="rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                                />
                                <InputError :message="withdrawalForm.errors.amount" />
                            </div>

                            <div class="grid gap-1.5 col-span-2">
                                <Label for="bank_name" class="text-[10px] font-black uppercase tracking-wider text-neutral-500">Tên ngân hàng</Label>
                                <Input
                                    id="bank_name"
                                    type="text"
                                    v-model="withdrawalForm.bank_name"
                                    required
                                    placeholder="Ví dụ: Vietcombank, Techcombank..."
                                    class="rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                                />
                                <InputError :message="withdrawalForm.errors.bank_name" />
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="bank_account_number" class="text-[10px] font-black uppercase tracking-wider text-neutral-500">Số tài khoản</Label>
                                <Input
                                    id="bank_account_number"
                                    type="text"
                                    v-model="withdrawalForm.bank_account_number"
                                    required
                                    placeholder="Số tài khoản ngân hàng"
                                    class="rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                                />
                                <InputError :message="withdrawalForm.errors.bank_account_number" />
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="bank_account_name" class="text-[10px] font-black uppercase tracking-wider text-neutral-500">Tên chủ tài khoản</Label>
                                <Input
                                    id="bank_account_name"
                                    type="text"
                                    v-model="withdrawalForm.bank_account_name"
                                    required
                                    placeholder="HOANG VAN A"
                                    class="rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800 uppercase"
                                />
                                <InputError :message="withdrawalForm.errors.bank_account_name" />
                            </div>
                        </div>

                        <Button
                            type="submit"
                            class="w-full bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-neutral-50 dark:text-neutral-950 dark:hover:bg-neutral-200 rounded-xl py-3 font-bold text-xs uppercase tracking-wider transition-all duration-200 shadow-sm active:scale-95 cursor-pointer disabled:opacity-50 mt-2"
                            :disabled="withdrawalForm.processing || (user?.commission_balance ?? 0) < 50000"
                        >
                            {{ withdrawalForm.processing ? 'Đang gửi yêu cầu...' : 'Yêu cầu rút tiền' }}
                        </Button>
                    </form>
                </Card>
            </div>

            <!-- History and Details Sections -->
            <div class="space-y-4">
                <div class="flex overflow-x-auto gap-2 pb-1 scrollbar-none border-b border-neutral-100 dark:border-neutral-800">
                    <button
                        type="button"
                        class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 cursor-pointer transition-all shrink-0"
                        :class="referralsActiveSubTab === 'withdrawals' ? 'border-neutral-900 text-neutral-900 dark:border-neutral-100 dark:text-neutral-100' : 'border-transparent text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-200'"
                        @click="referralsActiveSubTab = 'withdrawals'"
                    >
                        <span class="flex items-center gap-2">
                            <History class="h-4 w-4" /> Lịch sử rút tiền
                        </span>
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 cursor-pointer transition-all shrink-0"
                        :class="referralsActiveSubTab === 'referrals' ? 'border-neutral-900 text-neutral-900 dark:border-neutral-100 dark:text-neutral-100' : 'border-transparent text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-200'"
                        @click="referralsActiveSubTab = 'referrals'"
                    >
                        <span class="flex items-center gap-2">
                            <Users class="h-4 w-4" /> Đã giới thiệu ({{ referrals.length }})
                        </span>
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 cursor-pointer transition-all shrink-0"
                        :class="referralsActiveSubTab === 'commissions' ? 'border-neutral-900 text-neutral-900 dark:border-neutral-100 dark:text-neutral-100' : 'border-transparent text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-200'"
                        @click="referralsActiveSubTab = 'commissions'"
                    >
                        <span class="flex items-center gap-2">
                            <Gift class="h-4 w-4" /> Lịch sử hoa hồng
                        </span>
                    </button>
                </div>

                <!-- TAB: Withdrawal Requests -->
                <Card v-if="referralsActiveSubTab === 'withdrawals'" class="w-full border border-neutral-200/60 dark:border-neutral-800/60 shadow-xs rounded-2xl overflow-hidden bg-white/70 dark:bg-neutral-900/40 backdrop-blur-md p-2">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-neutral-100 dark:border-neutral-800 text-neutral-400 uppercase font-black tracking-wider">
                                    <th class="p-4">Thời gian</th>
                                    <th class="p-4">Số tiền</th>
                                    <th class="p-4">Thông tin ngân hàng</th>
                                    <th class="p-4">Trạng thái</th>
                                    <th class="p-4">Phản hồi admin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                                <tr v-for="req in withdrawalRequests" :key="req.id" class="hover:bg-neutral-100/30 dark:hover:bg-neutral-900/30 transition-colors">
                                    <td class="p-4 font-semibold">{{ req.created_at }}</td>
                                    <td class="p-4 font-black text-neutral-900 dark:text-neutral-50">{{ formatCurrency(req.amount) }}</td>
                                    <td class="p-4">
                                         <div class="font-bold text-neutral-800 dark:text-neutral-200">{{ req.bank_name }}</div>
                                         <div class="text-[10px] text-neutral-500 mt-0.5">{{ req.bank_account_number }} · {{ req.bank_account_name }}</div>
                                    </td>
                                    <td class="p-4">
                                         <span v-if="req.status === 'pending'" class="inline-flex items-center rounded-full bg-amber-50 dark:bg-amber-950/30 border border-amber-200/50 px-2 py-0.5 text-[9px] font-black uppercase text-amber-600 dark:text-amber-400">
                                             Chờ duyệt
                                         </span>
                                         <span v-else-if="req.status === 'approved'" class="inline-flex items-center rounded-full bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200/50 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-600 dark:text-emerald-400">
                                             Đã duyệt
                                         </span>
                                         <span v-else-if="req.status === 'rejected'" class="inline-flex items-center rounded-full bg-rose-50 dark:bg-rose-950/30 border border-rose-200/50 px-2 py-0.5 text-[9px] font-black uppercase text-rose-600 dark:text-rose-400">
                                             Từ chối
                                         </span>
                                    </td>
                                    <td class="p-4 text-neutral-500 italic text-[11px] font-medium">{{ req.notes || '—' }}</td>
                                </tr>
                                <tr v-if="withdrawalRequests.length === 0">
                                    <td colspan="5" class="p-8 text-center text-neutral-400 font-medium italic">
                                         Chưa có yêu cầu rút tiền nào được tạo.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>

                <!-- TAB: Referrals -->
                <Card v-if="referralsActiveSubTab === 'referrals'" class="w-full border border-neutral-200/60 dark:border-neutral-800/60 shadow-xs rounded-2xl overflow-hidden bg-white/70 dark:bg-neutral-900/40 backdrop-blur-md p-2">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-neutral-100 dark:border-neutral-800 text-neutral-400 uppercase font-black tracking-wider">
                                    <th class="p-4">Họ và tên</th>
                                    <th class="p-4">Ngày đăng ký</th>
                                    <th class="p-4">Trạng thái tài khoản</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                                <tr v-for="ref in referrals" :key="ref.name" class="hover:bg-neutral-100/30 dark:hover:bg-neutral-900/30 transition-colors">
                                    <td class="p-4 font-bold text-neutral-800 dark:text-neutral-200">{{ ref.name }}</td>
                                    <td class="p-4 text-neutral-500 font-semibold">{{ ref.created_at }}</td>
                                    <td class="p-4">
                                         <span v-if="ref.status === 'active'" class="inline-flex items-center rounded-full bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200/50 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-600 dark:text-emerald-400">
                                             Hoạt động
                                         </span>
                                         <span v-else class="inline-flex items-center rounded-full bg-neutral-100 dark:bg-neutral-800 border px-2 py-0.5 text-[9px] font-black uppercase text-neutral-500">
                                             Không hoạt động
                                         </span>
                                    </td>
                                </tr>
                                <tr v-if="referrals.length === 0">
                                    <td colspan="3" class="p-8 text-center text-neutral-400 font-medium italic">
                                         Bạn chưa giới thiệu thành viên nào. Hãy chia sẻ mã giới thiệu của bạn nhé!
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>

                <!-- TAB: Commissions -->
                <Card v-if="referralsActiveSubTab === 'commissions'" class="w-full border border-neutral-200/60 dark:border-neutral-800/60 shadow-xs rounded-2xl overflow-hidden bg-white/70 dark:bg-neutral-900/40 backdrop-blur-md p-2">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-neutral-100 dark:border-neutral-800 text-neutral-400 uppercase font-black tracking-wider">
                                    <th class="p-4">Người mua</th>
                                    <th class="p-4">Chi tiết thanh toán</th>
                                    <th class="p-4">Tỷ lệ %</th>
                                    <th class="p-4">Số tiền nhận</th>
                                    <th class="p-4">Ngày nhận</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                                <tr v-for="log in commissionLogs" :key="log.id" class="hover:bg-neutral-100/30 dark:hover:bg-neutral-900/30 transition-colors">
                                    <td class="p-4 font-bold text-neutral-800 dark:text-neutral-200">
                                         <div>{{ log.buyer_name }}</div>
                                         <div class="text-[10px] text-neutral-500 font-medium mt-0.5">{{ log.restaurant_name }}</div>
                                    </td>
                                    <td class="p-4 font-bold text-neutral-600 dark:text-neutral-400">Giá trị đơn: {{ formatCurrency(log.amount) }}</td>
                                    <td class="p-4 font-bold text-teal-600 dark:text-teal-400">{{ log.commission_percentage }}%</td>
                                    <td class="p-4 font-black text-emerald-600 dark:text-emerald-400">+{{ formatCurrency(log.commission_amount) }}</td>
                                    <td class="p-4 text-neutral-500 font-semibold">{{ log.created_at }}</td>
                                </tr>
                                <tr v-if="commissionLogs.length === 0">
                                    <td colspan="5" class="p-8 text-center text-neutral-400 font-medium italic">
                                         Chưa phát sinh hoa hồng nào. Hoa hồng sẽ được cộng khi đối tác nâng cấp hoặc gia hạn gói dịch vụ.
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
