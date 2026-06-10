<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Check, Copy, Gift, History, Landmark, Users } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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

defineProps<{
    referrals: Referral[];
    commissionLogs: CommissionLog[];
    withdrawalRequests: WithdrawalRequest[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Giới thiệu & Nhận thưởng',
                href: '/settings/referrals',
            },
        ],
    },
});

const page = usePage();
const user = computed(() => (page.props.auth as any).user);

const isCopied = ref(false);
const copyCode = () => {
    if (user.value?.referral_code) {
        navigator.clipboard.writeText(user.value.referral_code);
        isCopied.value = true;
        setTimeout(() => isCopied.value = false, 2000);
    }
};

const form = useForm({
    amount: '',
    bank_name: user.value?.bank_name || '',
    bank_account_number: user.value?.bank_account_number || '',
    bank_account_name: user.value?.bank_account_name || '',
});

const submitWithdrawal = () => {
    form.post('/settings/referrals/withdraw', {
        onSuccess: () => {
            form.reset('amount');
        },
    });
};

const activeTab = ref<'withdrawals' | 'referrals' | 'commissions'>('withdrawals');

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
};
</script>

<template>
    <Head title="Giới thiệu &amp; Hoa hồng" />

    <div class="space-y-8">
        <Heading
            variant="small"
            title="Giới thiệu &amp; Hoa hồng"
            description="Chia sẻ mã giới thiệu của bạn và nhận hoa hồng hấp dẫn khi bạn bè đăng ký và thanh toán các gói dịch vụ."
        />

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Referral Code Card with Premium Aesthetic -->
            <div class="relative overflow-hidden rounded-3xl border border-emerald-500/10 bg-gradient-to-br from-emerald-500/5 via-teal-500/[0.02] to-transparent p-6 shadow-xs">
                <div class="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-emerald-500/10 blur-xl" />
                
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <Gift class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-foreground">Mã giới thiệu của bạn</h3>
                        <p class="text-xs text-muted-foreground">Chia sẻ mã này với bạn bè khi đăng ký</p>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-3">
                    <div class="flex-1 rounded-2xl border bg-background px-4 py-3.5 font-mono text-xl font-black tracking-wider text-center uppercase shadow-inner">
                        {{ user?.referral_code ?? 'AVTXXXXX' }}
                    </div>
                    <Button 
                        type="button" 
                        variant="outline" 
                        size="icon" 
                        class="h-[52px] w-[52px] rounded-2xl cursor-pointer"
                        @click="copyCode"
                    >
                        <Check v-if="isCopied" class="h-5 w-5 text-emerald-500" />
                        <Copy v-else class="h-5 w-5 text-muted-foreground" />
                    </Button>
                </div>

                <div class="mt-6 space-y-2.5 text-xs text-muted-foreground">
                    <div class="flex items-start gap-2">
                        <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">1</span>
                        <span>Người được giới thiệu nhập mã này khi đăng ký doanh nghiệp.</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">2</span>
                        <span>Họ nhận được 14 ngày dùng thử miễn phí tất cả các tính năng.</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">3</span>
                        <span>Khi họ mua gói, bạn nhận hoa hồng tương ứng tỉ lệ Super Admin quy định.</span>
                    </div>
                </div>
            </div>

            <!-- Earning Status & Withdrawal Form -->
            <div class="rounded-3xl border bg-card p-6 shadow-xs">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Landmark class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-foreground">Số dư hoa hồng</h3>
                            <p class="text-xs text-muted-foreground">Khả dụng để rút về tài khoản ngân hàng</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xl font-black text-primary">{{ formatCurrency(user?.commission_balance ?? 0) }}</p>
                    </div>
                </div>

                <form @submit.prevent="submitWithdrawal" class="mt-6 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5 col-span-2">
                            <Label for="amount" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">Số tiền muốn rút (đ)</Label>
                            <Input
                                id="amount"
                                type="number"
                                v-model="form.amount"
                                required
                                placeholder="Tối thiểu 50,000đ"
                                class="rounded-xl border-zinc-200 dark:border-zinc-800"
                            />
                            <InputError :message="form.errors.amount" />
                        </div>

                        <div class="grid gap-1.5 col-span-2">
                            <Label for="bank_name" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">Tên ngân hàng</Label>
                            <Input
                                id="bank_name"
                                type="text"
                                v-model="form.bank_name"
                                required
                                placeholder="Ví dụ: Vietcombank, Techcombank..."
                                class="rounded-xl border-zinc-200 dark:border-zinc-800"
                            />
                            <InputError :message="form.errors.bank_name" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="bank_account_number" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">Số tài khoản</Label>
                            <Input
                                id="bank_account_number"
                                type="text"
                                v-model="form.bank_account_number"
                                required
                                placeholder="Số tài khoản ngân hàng"
                                class="rounded-xl border-zinc-200 dark:border-zinc-800"
                            />
                            <InputError :message="form.errors.bank_account_number" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="bank_account_name" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground/80">Tên chủ tài khoản</Label>
                            <Input
                                id="bank_account_name"
                                type="text"
                                v-model="form.bank_account_name"
                                required
                                placeholder="HOANG VAN A"
                                class="rounded-xl border-zinc-200 dark:border-zinc-800 uppercase"
                            />
                            <InputError :message="form.errors.bank_account_name" />
                        </div>
                    </div>

                    <Button
                        type="submit"
                        class="w-full rounded-xl py-5 font-black uppercase tracking-wider text-xs cursor-pointer"
                        :disabled="form.processing || (user?.commission_balance ?? 0) < 50000"
                    >
                        {{ form.processing ? 'Đang gửi yêu cầu...' : 'Yêu cầu rút tiền' }}
                    </Button>
                </form>
            </div>
        </div>

        <!-- History and Details Sections -->
        <div class="space-y-4">
            <div class="flex border-b border-zinc-100 dark:border-zinc-800">
                <button
                    type="button"
                    class="px-4 py-2.5 text-xs font-black uppercase tracking-wider border-b-2 cursor-pointer transition-all"
                    :class="activeTab === 'withdrawals' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeTab = 'withdrawals'"
                >
                    <span class="flex items-center gap-2">
                        <History class="h-4 w-4" /> Lịch sử rút tiền
                    </span>
                </button>
                <button
                    type="button"
                    class="px-4 py-2.5 text-xs font-black uppercase tracking-wider border-b-2 cursor-pointer transition-all"
                    :class="activeTab === 'referrals' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeTab = 'referrals'"
                >
                    <span class="flex items-center gap-2">
                        <Users class="h-4 w-4" /> Đã giới thiệu ({{ referrals.length }})
                    </span>
                </button>
                <button
                    type="button"
                    class="px-4 py-2.5 text-xs font-black uppercase tracking-wider border-b-2 cursor-pointer transition-all"
                    :class="activeTab === 'commissions' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeTab = 'commissions'"
                >
                    <span class="flex items-center gap-2">
                        <Gift class="h-4 w-4" /> Lịch sử hoa hồng
                    </span>
                </button>
            </div>

            <!-- TAB: Withdrawal Requests -->
            <div v-if="activeTab === 'withdrawals'" class="rounded-3xl border bg-card/40 p-2 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-zinc-100 dark:border-zinc-800 text-muted-foreground uppercase font-black tracking-wider">
                                <th class="p-4">Thời gian</th>
                                <th class="p-4">Số tiền</th>
                                <th class="p-4">Thông tin ngân hàng</th>
                                <th class="p-4">Trạng thái</th>
                                <th class="p-4">Phản hồi admin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            <tr v-for="req in withdrawalRequests" :key="req.id" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-900/30">
                                <td class="p-4 font-medium">{{ req.created_at }}</td>
                                <td class="p-4 font-extrabold text-foreground">{{ formatCurrency(req.amount) }}</td>
                                <td class="p-4">
                                    <div class="font-semibold">{{ req.bank_name }}</div>
                                    <div class="text-[11px] text-muted-foreground">{{ req.bank_account_number }} · {{ req.bank_account_name }}</div>
                                </td>
                                <td class="p-4">
                                    <span v-if="req.status === 'pending'" class="inline-flex items-center rounded-full bg-amber-50 dark:bg-amber-950/30 border border-amber-200/50 px-2 py-0.5 text-[10px] font-black uppercase text-amber-600 dark:text-amber-400">
                                        Chờ duyệt
                                    </span>
                                    <span v-else-if="req.status === 'approved'" class="inline-flex items-center rounded-full bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200/50 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-600 dark:text-emerald-400">
                                        Đã duyệt
                                    </span>
                                    <span v-else-if="req.status === 'rejected'" class="inline-flex items-center rounded-full bg-rose-50 dark:bg-rose-950/30 border border-rose-200/50 px-2 py-0.5 text-[10px] font-black uppercase text-rose-600 dark:text-rose-400">
                                        Từ chối
                                    </span>
                                </td>
                                <td class="p-4 text-muted-foreground italic text-[11px]">{{ req.notes || '—' }}</td>
                            </tr>
                            <tr v-if="withdrawalRequests.length === 0">
                                <td colspan="5" class="p-8 text-center text-muted-foreground font-medium italic">
                                    Chưa có yêu cầu rút tiền nào được tạo.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Referrals -->
            <div v-if="activeTab === 'referrals'" class="rounded-3xl border bg-card/40 p-2 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-zinc-100 dark:border-zinc-800 text-muted-foreground uppercase font-black tracking-wider">
                                <th class="p-4">Họ và tên</th>
                                <th class="p-4">Ngày đăng ký</th>
                                <th class="p-4">Trạng thái tài khoản</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            <tr v-for="ref in referrals" :key="ref.name" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-900/30">
                                <td class="p-4 font-bold text-foreground">{{ ref.name }}</td>
                                <td class="p-4 text-muted-foreground">{{ ref.created_at }}</td>
                                <td class="p-4">
                                    <span v-if="ref.status === 'active'" class="inline-flex items-center rounded-full bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200/50 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-600 dark:text-emerald-400">
                                        Hoạt động
                                    </span>
                                    <span v-else class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-800/80 border px-2 py-0.5 text-[10px] font-black uppercase text-zinc-500">
                                        Không hoạt động
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="referrals.length === 0">
                                <td colspan="3" class="p-8 text-center text-muted-foreground font-medium italic">
                                    Bạn chưa giới thiệu thành viên nào. Hãy chia sẻ mã giới thiệu của bạn nhé!
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Commissions -->
            <div v-if="activeTab === 'commissions'" class="rounded-3xl border bg-card/40 p-2 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-zinc-100 dark:border-zinc-800 text-muted-foreground uppercase font-black tracking-wider">
                                <th class="p-4">Người mua</th>
                                <th class="p-4">Chi tiết thanh toán</th>
                                <th class="p-4">Tỷ lệ %</th>
                                <th class="p-4">Số tiền nhận</th>
                                <th class="p-4">Ngày nhận</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            <tr v-for="log in commissionLogs" :key="log.id" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-900/30">
                                <td class="p-4 font-bold">
                                    <div>{{ log.buyer_name }}</div>
                                    <div class="text-[11px] text-muted-foreground font-normal">{{ log.restaurant_name }}</div>
                                </td>
                                <td class="p-4 font-semibold text-muted-foreground">Giá trị đơn: {{ formatCurrency(log.amount) }}</td>
                                <td class="p-4 font-semibold text-teal-600 dark:text-teal-400">{{ log.commission_percentage }}%</td>
                                <td class="p-4 font-black text-emerald-600 dark:text-emerald-400">+{{ formatCurrency(log.commission_amount) }}</td>
                                <td class="p-4 text-muted-foreground font-medium">{{ log.created_at }}</td>
                            </tr>
                            <tr v-if="commissionLogs.length === 0">
                                <td colspan="5" class="p-8 text-center text-muted-foreground font-medium italic">
                                    Chưa phát sinh hoa hồng nào. Hoa hồng sẽ được cộng khi bạn bè nâng cấp hoặc gia hạn gói dịch vụ.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
