<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    CheckCircle2,
    Crown,
    Landmark,
    X,
    XCircle,
} from 'lucide-vue-next';
import { ref } from 'vue';
import {
    PageHeader,
    DataTable,
    StatusBadge,
    Pagination,
    EmptyState,
} from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

type WithdrawalRequest = {
    id: number;
    user_name: string;
    user_email: string;
    user_balance: number;
    amount: number;
    bank_name: string;
    bank_account_number: string;
    bank_account_name: string;
    status: 'pending' | 'approved' | 'rejected';
    notes: string | null;
    created_at: string;
};

defineProps<{
    withdrawalRequests: {
        data: WithdrawalRequest[];
        links: any[];
        total: number;
        last_page: number;
    };
    commissionPercentage: number;
}>();

defineOptions({ layout: AppLayout });

// --- Cấu hình phần trăm hoa hồng ---
const settingsForm = useForm({
    commission_percentage: '',
});

const isEditingSettings = ref(false);
const saveSettings = (percentage: number) => {
    settingsForm.commission_percentage = String(percentage);
    settingsForm.post('/super-admin/referrals/settings', {
        onSuccess: () => {
            isEditingSettings.value = false;
        },
    });
};

// --- Xử lý duyệt rút tiền ---
const selectedRequest = ref<WithdrawalRequest | null>(null);
const actionType = ref<'approve' | 'reject' | null>(null);
const actionForm = useForm({
    notes: '',
});

const openActionModal = (
    req: WithdrawalRequest,
    type: 'approve' | 'reject',
) => {
    selectedRequest.value = req;
    actionType.value = type;
    actionForm.notes = type === 'approve' ? 'Phê duyệt bởi Super Admin' : '';
    actionForm.clearErrors();
};

const closeActionModal = () => {
    selectedRequest.value = null;
    actionType.value = null;
    actionForm.reset();
};

const submitAction = () => {
    if (!selectedRequest.value || !actionType.value) {
        return;
    }

    const url = `/super-admin/referrals/withdrawals/${selectedRequest.value.id}/${actionType.value}`;
    actionForm.post(url, {
        onSuccess: () => {
            closeActionModal();
        },
    });
};

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(value);
};
</script>

<template>
    <Head title="Cấu hình hoa hồng &amp; Rút tiền" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Quản lý Hoa hồng & Rút tiền"
            subtitle="Thiết lập tỷ lệ chia sẻ hoa hồng và phê duyệt các yêu cầu rút tiền mặt từ hệ thống."
            :icon="Crown"
        />

        <div class="grid gap-6 md:grid-cols-3">
            <!-- Global Setting Card -->
            <div
                class="relative overflow-hidden rounded-3xl border bg-card p-6 shadow-xs md:col-span-1"
            >
                <div
                    class="absolute -top-6 -right-6 h-24 w-24 rounded-full bg-primary/5 blur-xl"
                />
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Crown class="h-4.5 w-4.5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-foreground">
                            Tỷ lệ hoa hồng giới thiệu
                        </h3>
                        <p class="text-xs font-medium text-muted-foreground">
                            Toàn hệ thống
                        </p>
                    </div>
                </div>

                <div class="mt-6">
                    <div
                        v-if="!isEditingSettings"
                        class="flex items-baseline justify-between"
                    >
                        <span class="text-3xl font-black text-primary"
                            >{{ commissionPercentage }}%</span
                        >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="cursor-pointer rounded-xl"
                            @click="isEditingSettings = true"
                        >
                            Thay đổi
                        </Button>
                    </div>
                    <form
                        v-else
                        @submit.prevent="
                            saveSettings(
                                Number(settingsForm.commission_percentage),
                            )
                        "
                        class="space-y-3"
                    >
                        <div class="grid gap-1">
                            <Label
                                for="commission_percentage"
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Phần trăm hoa hồng (%)</Label
                            >
                            <div class="flex items-center gap-2">
                                <Input
                                    id="commission_percentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.1"
                                    :default-value="commissionPercentage"
                                    required
                                    @input="
                                        (e: any) =>
                                            (settingsForm.commission_percentage =
                                                e.target.value)
                                    "
                                    class="h-9 rounded-xl"
                                />
                                <span
                                    class="text-sm font-bold text-muted-foreground"
                                    >%</span
                                >
                            </div>
                            <InputError
                                :message="
                                    settingsForm.errors.commission_percentage
                                "
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                type="submit"
                                size="sm"
                                class="h-8 cursor-pointer rounded-xl px-3"
                                :disabled="settingsForm.processing"
                            >
                                Lưu
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-8 cursor-pointer rounded-xl px-3"
                                @click="isEditingSettings = false"
                            >
                                Hủy
                            </Button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Description Card -->
            <div
                class="flex flex-col justify-center rounded-3xl border bg-zinc-50/50 p-6 md:col-span-2 dark:bg-zinc-900/10"
            >
                <h4
                    class="mb-2 text-xs font-black tracking-wider text-muted-foreground uppercase"
                >
                    Thông tin vận hành
                </h4>
                <p class="text-xs leading-relaxed text-muted-foreground">
                    Hệ thống hoa hồng giới thiệu áp dụng khi chủ doanh nghiệp
                    (Owner) thanh toán thành công hóa đơn các gói dịch vụ
                    Pro/Max. Số tiền hoa hồng sẽ tự động được ghi nhận và cộng
                    vào số dư khả dụng của người giới thiệu.
                </p>
                <div
                    class="mt-4 flex items-center gap-4 text-xs font-semibold text-foreground"
                >
                    <span class="flex items-center gap-1.5"
                        ><CheckCircle2
                            class="h-4 w-4 text-emerald-500 dark:text-emerald-400"
                        />
                        Tự động cộng số dư</span
                    >
                    <span class="flex items-center gap-1.5"
                        ><CheckCircle2
                            class="h-4 w-4 text-emerald-500 dark:text-emerald-400"
                        />
                        Lưu vết log chi tiết</span
                    >
                    <span class="flex items-center gap-1.5"
                        ><CheckCircle2
                            class="h-4 w-4 text-emerald-500 dark:text-emerald-400"
                        />
                        Bảo mật giao dịch</span
                    >
                </div>
            </div>
        </div>

        <!-- Withdrawal Requests List -->
        <div
            class="mt-2 overflow-hidden rounded-3xl border bg-card/60 p-2 shadow-xs"
        >
            <div class="border-b p-4">
                <h3 class="text-sm font-bold text-foreground">
                    Danh sách yêu cầu rút tiền
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr
                            class="border-b border-zinc-100 font-black tracking-wider text-muted-foreground uppercase dark:border-zinc-800"
                        >
                            <th class="p-4">Thành viên</th>
                            <th class="p-4">Số tiền</th>
                            <th class="p-4">Thông tin chuyển khoản</th>
                            <th class="p-4">Ngày yêu cầu</th>
                            <th class="p-4">Trạng thái</th>
                            <th class="p-4 text-right">Hành động</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-zinc-100 dark:divide-zinc-800"
                    >
                        <tr
                            v-for="req in withdrawalRequests.data"
                            :key="req.id"
                            class="hover:bg-zinc-50/50 dark:hover:bg-zinc-900/30"
                        >
                            <td class="p-4">
                                <div class="font-bold text-foreground">
                                    {{ req.user_name }}
                                </div>
                                <div class="text-[11px] text-muted-foreground">
                                    {{ req.user_email }}
                                </div>
                                <div
                                    class="text-[10px] font-semibold text-teal-600 dark:text-teal-400"
                                >
                                    Số dư hiện tại:
                                    {{ formatCurrency(req.user_balance) }}
                                </div>
                            </td>
                            <td class="p-4 text-sm font-black text-foreground">
                                {{ formatCurrency(req.amount) }}
                            </td>
                            <td class="p-4">
                                <div
                                    class="flex items-center gap-1 font-bold text-foreground"
                                >
                                    <Landmark
                                        class="h-3.5 w-3.5 text-muted-foreground"
                                    />
                                    {{ req.bank_name }}
                                </div>
                                <div
                                    class="text-[11px] font-semibold text-muted-foreground"
                                >
                                    STK: {{ req.bank_account_number }}
                                </div>
                                <div
                                    class="text-[11px] text-muted-foreground uppercase"
                                >
                                    Tên: {{ req.bank_account_name }}
                                </div>
                            </td>
                            <td class="p-4 font-medium text-muted-foreground">
                                {{ req.created_at }}
                            </td>
                            <td class="p-4">
                                <span
                                    v-if="req.status === 'pending'"
                                    class="inline-flex items-center rounded-full border border-amber-200/50 bg-amber-50 px-2 py-0.5 text-[10px] font-black text-amber-600 uppercase dark:bg-amber-950/30 dark:text-amber-400"
                                >
                                    Chờ duyệt
                                </span>
                                <span
                                    v-else-if="req.status === 'approved'"
                                    class="inline-flex items-center rounded-full border border-emerald-200/50 bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-600 uppercase dark:bg-emerald-950/30 dark:text-emerald-400"
                                >
                                    Đã duyệt
                                </span>
                                <span
                                    v-else-if="req.status === 'rejected'"
                                    class="inline-flex items-center rounded-full border border-rose-200/50 bg-rose-50 px-2 py-0.5 text-[10px] font-black text-rose-600 uppercase dark:bg-rose-950/30 dark:text-rose-400"
                                >
                                    Từ chối
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <div
                                    v-if="req.status === 'pending'"
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Button
                                        type="button"
                                        size="sm"
                                        class="h-8 cursor-pointer rounded-xl bg-emerald-600 px-3.5 font-bold text-white hover:bg-emerald-700"
                                        @click="openActionModal(req, 'approve')"
                                    >
                                        Phê duyệt
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 cursor-pointer rounded-xl border-rose-200 px-3.5 font-bold text-rose-600 hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-400 dark:hover:bg-rose-950/20"
                                        @click="openActionModal(req, 'reject')"
                                    >
                                        Từ chối
                                    </Button>
                                </div>
                                <div
                                    v-else
                                    class="ml-auto max-w-[180px] truncate text-[11px] font-medium text-muted-foreground italic"
                                    :title="req.notes || ''"
                                >
                                    {{ req.notes || '—' }}
                                </div>
                            </td>
                        </tr>
                        <tr v-if="withdrawalRequests.data.length === 0">
                            <td
                                colspan="6"
                                class="p-8 text-center font-medium text-muted-foreground italic"
                            >
                                Không có yêu cầu rút tiền nào trên hệ thống.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="withdrawalRequests.last_page > 1"
                class="mt-2 flex justify-center gap-1 border-t p-4"
            >
                <a
                    v-for="link in withdrawalRequests.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    v-html="link.label"
                    :class="[
                        'rounded border px-3 py-1 text-sm',
                        link.active
                            ? 'bg-primary font-semibold text-primary-foreground'
                            : 'bg-background text-foreground hover:bg-muted',
                        !link.url ? 'pointer-events-none opacity-40' : '',
                    ]"
                />
            </div>
        </div>
    </div>

    <!-- Actions Confirmation Modal -->
    <Dialog
        :open="selectedRequest !== null"
        @update:open="(v) => !v && closeActionModal()"
    >
        <DialogContent class="rounded-3xl sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-foreground">
                    <AlertTriangle
                        v-if="actionType === 'reject'"
                        class="h-5 w-5 text-rose-500 dark:text-rose-400"
                    />
                    <CheckCircle2
                        v-else
                        class="h-5 w-5 text-emerald-500 dark:text-emerald-400"
                    />
                    {{
                        actionType === 'approve'
                            ? 'Phê duyệt yêu cầu rút tiền'
                            : 'Từ chối yêu cầu rút tiền'
                    }}
                </DialogTitle>
                <DialogDescription class="text-xs">
                    {{
                        actionType === 'approve'
                            ? 'Xác nhận bạn đã chuyển khoản số tiền này tới tài khoản của thành viên thành công.'
                            : 'Yêu cầu rút tiền này sẽ bị hủy bỏ và số tiền sẽ được hoàn lại vào số dư của thành viên.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="selectedRequest"
                class="my-3 space-y-4 rounded-2xl border bg-zinc-50 p-4 text-xs dark:bg-zinc-900/40"
            >
                <div class="flex justify-between border-b pb-2">
                    <span class="font-medium text-muted-foreground"
                        >Thành viên:</span
                    >
                    <span class="font-bold text-foreground">{{
                        selectedRequest.user_name
                    }}</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                    <span class="font-medium text-muted-foreground"
                        >Số tiền rút:</span
                    >
                    <span class="text-sm font-black text-foreground">{{
                        formatCurrency(selectedRequest.amount)
                    }}</span>
                </div>
                <div class="space-y-1 pt-1">
                    <p class="font-medium text-muted-foreground">
                        Thông tin chuyển khoản:
                    </p>
                    <p class="font-bold text-foreground">
                        {{ selectedRequest.bank_name }}
                    </p>
                    <p class="font-semibold text-foreground">
                        STK: {{ selectedRequest.bank_account_number }}
                    </p>
                    <p class="font-bold text-foreground uppercase">
                        Tên: {{ selectedRequest.bank_account_name }}
                    </p>
                </div>
            </div>

            <form @submit.prevent="submitAction" class="space-y-4">
                <div class="grid gap-1.5">
                    <Label
                        for="notes"
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{
                            actionType === 'approve'
                                ? 'Ghi chú phê duyệt (Không bắt buộc)'
                                : 'Lý do từ chối (Bắt buộc)'
                        }}
                    </Label>
                    <Input
                        id="notes"
                        type="text"
                        v-model="actionForm.notes"
                        :required="actionType === 'reject'"
                        :placeholder="
                            actionType === 'approve'
                                ? 'Ví dụ: Đã chuyển khoản qua Internet Banking'
                                : 'Ví dụ: Thông tin tài khoản ngân hàng sai lệch...'
                        "
                        class="rounded-xl border-zinc-200 dark:border-zinc-800"
                    />
                    <InputError :message="actionForm.errors.notes" />
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="ghost"
                        class="cursor-pointer rounded-xl"
                        @click="closeActionModal"
                    >
                        Hủy
                    </Button>
                    <Button
                        type="submit"
                        class="cursor-pointer rounded-xl"
                        :variant="
                            actionType === 'reject' ? 'destructive' : 'default'
                        "
                        :disabled="actionForm.processing"
                    >
                        {{
                            actionForm.processing
                                ? 'Đang xử lý...'
                                : actionType === 'approve'
                                  ? 'Xác nhận duyệt'
                                  : 'Xác nhận từ chối'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
