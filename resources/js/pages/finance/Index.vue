<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BookOpen, LockKeyhole, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type TrialRow = {
    code: string;
    name: string;
    type: string;
    debit: number;
    credit: number;
    balance: number;
};

type Entry = {
    id: number;
    entry_number: string;
    entry_date: string;
    description: string | null;
    status: string;
    source_type: string | null;
    source_id: number | null;
    reversal_of_id: number | null;
    branch_name: string | null;
    created_by: string | null;
    total_debit: number;
    total_credit: number;
    lines: Array<{ account_code: string; account_name: string; debit: number; credit: number }>;
};

type Account = { id: number; code: string; name: string; type: string; normal_balance: string; is_system: boolean; is_active: boolean };

const props = defineProps<{
    period: { id: number; period: string; status: string; period_start: string; period_end: string };
    entries: { data: Entry[]; current_page: number; last_page: number; total: number };
    trialBalance: TrialRow[];
    periods: Array<{ id: number; period: string; status: string; closed_at: string | null }>;
    accounts: Account[];
    summary: { total_debit: number; total_credit: number; entry_count: number };
    statements: { income_statement: { revenue: number; expense: number; net_profit: number }; balance_sheet: { assets: number; liabilities: number; equity: number }; cash_position: number };
    canReverse: boolean;
    canManageAccounts: boolean;
}>();

const selectedPeriod = ref(props.period.period);
const selectedEntry = ref<Entry | null>(null);
const showAccountForm = ref(false);
const accountForm = ref({ code: '', name: '', type: 'expense', normal_balance: 'debit', parent_id: '' });
const isBalanced = computed(() => Math.abs(props.summary.total_debit - props.summary.total_credit) < 0.01);

function money(value: number) {
    return new Intl.NumberFormat('vi-VN').format(value) + ' đ';
}

function changePeriod() {
    router.get('/finance', { period: selectedPeriod.value }, { preserveState: true, replace: true });
}

function saveAccount() {
    router.post('/finance/accounts', accountForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showAccountForm.value = false;
            accountForm.value = { code: '', name: '', type: 'expense', normal_balance: 'debit', parent_id: '' };
        },
    });
}

function closePeriod() {
    if (props.period.status === 'closed') {
return;
}

    if (! window.confirm(`Khóa kỳ ${props.period.period}? Mọi chỉnh sửa sau đó phải dùng bút toán đảo.`)) {
return;
}

    router.patch(`/finance/periods/${props.period.id}/close`, {}, { preserveScroll: true });
}
function reverseSelectedEntry() {
    if (!selectedEntry.value || selectedEntry.value.reversal_of_id !== null) {
return;
}

    const reason = window.prompt('Lý do đảo bút toán:');

    if (!reason?.trim()) {
return;
}

    router.post('/finance/entries/' + selectedEntry.value.id + '/reverse', { reason }, { preserveScroll: true, onSuccess: () => {
 selectedEntry.value = null; 
} });
}
</script>

<template>
    <Head title="Sổ tài chính" />

    <div class="space-y-6 p-4 sm:p-6">
        <FinancePageHeader title="Sổ tài chính" description="Bút toán, bảng cân đối phát sinh và khóa kỳ." :icon="BookOpen">
            <template #actions>
                <Input v-model="selectedPeriod" type="month" class="w-auto min-w-[9.5rem]" aria-label="Kỳ kế toán" @change="changePeriod" />
                <Button v-if="period.status !== 'closed'" type="button" class="gap-2" @click="closePeriod">
                    <LockKeyhole class="size-4" />
                    Khóa kỳ
                </Button>
                <span v-else class="inline-flex h-9 items-center gap-2 rounded-md bg-emerald-500/10 px-3 text-sm font-medium text-emerald-600 ring-1 ring-emerald-500/20">
                    <LockKeyhole class="size-4" /> Đã khóa
                </span>
            </template>
        </FinancePageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-border/60 bg-card/80 p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">Tổng Nợ</div>
                <div class="mt-2 text-xl font-semibold text-slate-900 dark:text-white">{{ money(summary.total_debit) }}</div>
            </div>
            <div class="rounded-2xl border border-border/60 bg-card/80 p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">Tổng Có</div>
                <div class="mt-2 text-xl font-semibold text-slate-900 dark:text-white">{{ money(summary.total_credit) }}</div>
            </div>
            <div class="rounded-2xl border border-border/60 bg-card/80 p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">Trạng thái</div>
                <div class="mt-2 text-xl font-semibold" :class="isBalanced ? 'text-emerald-600' : 'text-rose-600'">{{ isBalanced ? 'Cân bằng' : 'Cần kiểm tra' }}</div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-border/60 bg-card/80 p-4 shadow-sm"><div class="text-xs uppercase tracking-wide text-muted-foreground">Lãi/lỗ theo sổ</div><div class="mt-2 text-lg font-semibold" :class="statements.income_statement.net_profit >= 0 ? 'text-emerald-600' : 'text-rose-600'">{{ money(statements.income_statement.net_profit) }}</div><div class="mt-1 text-xs text-muted-foreground">Doanh thu {{ money(statements.income_statement.revenue) }} · Chi phí {{ money(statements.income_statement.expense) }}</div></div>
            <div class="rounded-2xl border border-border/60 bg-card/80 p-4 shadow-sm"><div class="text-xs uppercase tracking-wide text-muted-foreground">Tiền và tương đương tiền</div><div class="mt-2 text-lg font-semibold text-blue-600">{{ money(statements.cash_position) }}</div></div>
            <div class="rounded-2xl border border-border/60 bg-card/80 p-4 shadow-sm"><div class="text-xs uppercase tracking-wide text-muted-foreground">Tài sản / Nợ / Vốn</div><div class="mt-2 text-sm text-muted-foreground">{{ money(statements.balance_sheet.assets) }} / {{ money(statements.balance_sheet.liabilities) }} / {{ money(statements.balance_sheet.equity) }}</div></div>
        </div>

        <section v-if="canManageAccounts" class="overflow-hidden rounded-2xl border border-border/60 bg-card/80 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <div><h2 class="font-semibold text-slate-900 dark:text-white">Danh mục tài khoản</h2><p class="text-xs text-slate-500">Tài khoản hệ thống được tạo sẵn; tài khoản nội bộ có thể bổ sung theo mã tài khoản.</p></div>
                <Button type="button" variant="outline" size="sm" class="gap-2" @click="showAccountForm = !showAccountForm"><Plus class="size-4" /> Thêm tài khoản</Button>
            </div>
            <form v-if="showAccountForm" class="grid gap-3 border-b border-slate-200 p-5 md:grid-cols-5 dark:border-slate-800" @submit.prevent="saveAccount">
                <input v-model="accountForm.code" required placeholder="Mã TK" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950" />
                <input v-model="accountForm.name" required placeholder="Tên tài khoản" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950" />
                <select v-model="accountForm.type" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="asset">Tài sản</option><option value="liability">Nợ phải trả</option><option value="equity">Vốn</option><option value="revenue">Doanh thu</option><option value="expense">Chi phí</option></select>
                <select v-model="accountForm.normal_balance" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="debit">Dư Nợ</option><option value="credit">Dư Có</option></select>
                <Button type="submit" size="sm">Lưu</Button>
            </form>
            <div class="max-h-64 overflow-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-950"><tr><th class="px-4 py-3">Mã</th><th class="px-4 py-3">Tên</th><th class="px-4 py-3">Loại</th><th class="px-4 py-3">Tính chất</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800"><tr v-for="account in accounts" :key="account.id"><td class="px-4 py-2 font-medium">{{ account.code }}</td><td class="px-4 py-2">{{ account.name }}</td><td class="px-4 py-2">{{ account.type }}</td><td class="px-4 py-2">{{ account.normal_balance === 'debit' ? 'Dư Nợ' : 'Dư Có' }}</td></tr></tbody></table></div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.1fr_1.9fr]">
            <section class="overflow-hidden rounded-2xl border border-border/60 bg-card/80 shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="font-semibold text-slate-900 dark:text-white">Bảng cân đối phát sinh</h2>
                </div>
                <div class="max-h-[560px] overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-950">
                            <tr><th class="px-4 py-3">TK</th><th class="px-4 py-3">Tên tài khoản</th><th class="px-4 py-3 text-right">Nợ</th><th class="px-4 py-3 text-right">Có</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="row in trialBalance" :key="row.code">
                                <td class="px-4 py-3 font-medium">{{ row.code }}</td>
                                <td class="px-4 py-3">{{ row.name }}</td>
                                <td class="px-4 py-3 text-right">{{ money(row.debit) }}</td>
                                <td class="px-4 py-3 text-right">{{ money(row.credit) }}</td>
                            </tr>
                            <tr v-if="trialBalance.length === 0"><td colspan="4" class="px-4 py-8 text-center text-slate-500">Chưa có bút toán trong kỳ.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-border/60 bg-card/80 shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="font-semibold text-slate-900 dark:text-white">Nhật ký bút toán</h2>
                </div>
                <div class="overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-950">
                            <tr><th class="px-4 py-3">Ngày</th><th class="px-4 py-3">Chứng từ</th><th class="px-4 py-3">Nội dung</th><th class="px-4 py-3 text-right">Số tiền</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="entry in entries.data" :key="entry.id" class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60" @click="selectedEntry = entry">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ entry.entry_date }}</td>
                                <td class="px-4 py-3 font-medium">{{ entry.entry_number }}</td>
                                <td class="max-w-[300px] truncate px-4 py-3">{{ entry.description || '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">{{ money(entry.total_debit) }}</td>
                            </tr>
                            <tr v-if="entries.data.length === 0"><td colspan="4" class="px-4 py-8 text-center text-slate-500">Chưa có giao dịch.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div v-if="selectedEntry" class="rounded-xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-900 dark:bg-blue-950/30">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-blue-900 dark:text-blue-100">{{ selectedEntry.entry_number }}</h2>
                    <p class="text-sm text-blue-700 dark:text-blue-300">{{ selectedEntry.description || 'Chi tiết bút toán' }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <button v-if="canReverse && selectedEntry.reversal_of_id === null" type="button" class="text-sm text-rose-700 underline" @click="reverseSelectedEntry">Đảo bút toán</button>
                    <button type="button" class="text-sm text-blue-700 underline" @click="selectedEntry = null">Đóng</button>
                </div>
            </div>
            <div class="mt-4 overflow-auto">
                <table class="min-w-full text-sm"><thead><tr class="text-left text-xs uppercase text-blue-700"><th class="py-2">Tài khoản</th><th class="py-2 text-right">Nợ</th><th class="py-2 text-right">Có</th></tr></thead>
                    <tbody><tr v-for="line in selectedEntry.lines" :key="`${line.account_code}-${line.debit}-${line.credit}`"><td class="py-2">{{ line.account_code }} — {{ line.account_name }}</td><td class="py-2 text-right">{{ money(line.debit) }}</td><td class="py-2 text-right">{{ money(line.credit) }}</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</template>
