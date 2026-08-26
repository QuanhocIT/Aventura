<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertCircle, ArrowUpRight, Calculator, CheckCircle2, Info, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Line = {
    id: number;
    period_month: string;
    account_code: string;
    account_name: string;
    actual_basis: string;
    category_name: string | null;
    budget_amount: number;
    actual_amount: number;
    variance_amount: number;
};
type Budget = { id: number; name: string; branch_name: string | null; period_start: string; period_end: string; status: string; total_amount: number; lines: Line[] };
type BudgetAccount = { code: string; name: string; actual_basis: string };

const props = defineProps<{
    budgets: { data: Budget[] };
    branches: Array<{ id: number; name: string }>;
    budgetAccounts: BudgetAccount[];
    canApprove: boolean;
}>();

const defaultAccount = props.budgetAccounts.find((account) => account.code === '6271')?.code ?? props.budgetAccounts[0]?.code ?? '';
const showForm = ref(false);
const form = ref({
    name: '',
    branch_id: '',
    period_start: new Date().getFullYear() + '-01-01',
    period_end: new Date().getFullYear() + '-12-31',
    notes: '',
    lines: [{ period_month: new Date().getFullYear() + '-01', account_code: defaultAccount, category_id: null, budget_amount: 0 }],
});

function money(value: number) {
 return new Intl.NumberFormat('vi-VN').format(value) + ' đ'; 
}
function accountFor(code: string) {
 return props.budgetAccounts.find((account) => account.code === code); 
}
function addLine() {
 form.value.lines.push({ period_month: form.value.period_start.slice(0, 7), account_code: defaultAccount, category_id: null, budget_amount: 0 }); 
}
function save() {
 router.post('/financial-budgets', form.value, { onSuccess: () => {
 showForm.value = false; 
} }); 
}
function approve(id: number) {
 if (window.confirm('Duyệt ngân sách này?')) {
router.patch('/financial-budgets/' + id + '/approve');
} 
}
function statusLabel(status: string) {
 return status === 'approved' ? 'Đã duyệt' : 'Bản nháp'; 
}
</script>

<template>
    <Head title="Ngân sách chi phí & giá vốn" />
    <div class="space-y-6 p-4 sm:p-6">
        <FinancePageHeader title="Ngân sách chi phí & giá vốn" description="Đặt hạn mức kế hoạch theo tháng, khoản mục và chi nhánh; sau đó đối chiếu với số đã phát sinh." :icon="Calculator">
            <template #actions>
                <Button class="gap-2" @click="showForm = !showForm">
                    <Plus class="size-4" />
                    Tạo ngân sách chi phí
                </Button>
            </template>
        </FinancePageHeader>

        <section class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-4 sm:p-5">
            <div class="flex items-start gap-3">
                <Info class="mt-0.5 size-5 shrink-0 text-amber-600" />
                <div class="space-y-1 text-sm">
                    <p class="font-semibold text-foreground">Đây là ngân sách kế hoạch cho chi phí và giá vốn, không phải số dư tiền mặt.</p>
                    <p class="text-muted-foreground">“Ngân sách” là số tiền tối đa dự kiến được phép phát sinh trong kỳ. “Đã phát sinh” lấy từ giao dịch đã được duyệt/ghi nhận: nguyên liệu là giá vốn đã xuất dùng, lương là kỳ lương đã duyệt/đã trả, các khoản khác là phiếu chi phí đã duyệt/đã trả.</p>
                    <p class="text-muted-foreground">Giá trị tiền mua nguyên liệu vào Kho Tổng, cấp xuống chi nhánh hoặc chi nhánh tự mua được theo dõi riêng tại <Link href="/finance/ingredient-spend" class="inline-flex items-center gap-1 font-medium text-primary hover:underline">Giá trị nhập nguyên liệu <ArrowUpRight class="size-3.5" /></Link>.</p>
                </div>
            </div>
        </section>

        <section v-if="showForm" class="rounded-2xl border border-border/60 bg-card/80 p-5 shadow-sm sm:p-6">
            <div class="mb-5 flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary"><Plus class="size-4" /></div>
                <div><h2 class="font-semibold text-foreground">Thông tin ngân sách chi phí</h2><p class="text-sm text-muted-foreground">Chọn đúng khoản mục để hệ thống lấy đúng nguồn số thực tế.</p></div>
            </div>
            <div class="grid gap-3 md:grid-cols-4">
                <Input v-model="form.name" placeholder="Tên ngân sách, ví dụ: Chi phí vận hành T8/2026" />
                <select v-model="form.branch_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"><option value="">Toàn nhà hàng</option><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option></select>
                <Input v-model="form.period_start" type="date" aria-label="Từ ngày" />
                <Input v-model="form.period_end" type="date" aria-label="Đến ngày" />
            </div>
            <div class="mt-5 space-y-2">
                <div class="grid gap-2 px-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground md:grid-cols-[1fr_1.6fr_1fr_auto]"><span>Tháng</span><span>Khoản mục</span><span>Số ngân sách</span><span /></div>
                <div v-for="(line, index) in form.lines" :key="index" class="grid items-start gap-2 rounded-xl border border-border/50 bg-muted/20 p-2 md:grid-cols-[1fr_1.6fr_1fr_auto]">
                    <Input v-model="line.period_month" type="month" />
                    <div>
                        <select v-model="line.account_code" class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"><option v-for="account in budgetAccounts" :key="account.code" :value="account.code">{{ account.code }} · {{ account.name }}</option></select>
                        <p v-if="accountFor(line.account_code)" class="mt-1 flex items-start gap-1 text-xs text-muted-foreground"><AlertCircle class="mt-0.5 size-3 shrink-0" />{{ accountFor(line.account_code)?.actual_basis }}</p>
                    </div>
                    <input v-model.number="line.budget_amount" type="number" min="0" placeholder="Ngân sách" class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]" />
                    <Button v-if="form.lines.length > 1" variant="ghost" size="icon" type="button" class="text-rose-600 hover:bg-rose-500/10 hover:text-rose-600" aria-label="Xóa dòng" @click="form.lines.splice(index, 1)"><Trash2 class="size-4" /></Button>
                </div>
                <Button variant="ghost" size="sm" type="button" class="gap-2 text-primary" @click="addLine"><Plus class="size-4" /> Thêm dòng</Button>
            </div>
            <div class="mt-5 flex justify-end"><Button @click="save">Lưu bản nháp</Button></div>
        </section>

        <div class="space-y-4">
            <section v-for="budget in budgets.data" :key="budget.id" class="overflow-hidden rounded-2xl border border-border/60 bg-card/80 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 px-5 py-4 sm:px-6">
                    <div><h2 class="font-semibold text-foreground">{{ budget.name }}</h2><p class="text-xs text-muted-foreground">{{ budget.branch_name || 'Toàn nhà hàng' }} · {{ budget.period_start }} → {{ budget.period_end }}</p></div>
                    <div class="flex items-center gap-3"><span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="budget.status === 'approved' ? 'bg-emerald-500/10 text-emerald-600 ring-1 ring-emerald-500/20' : 'bg-amber-500/10 text-amber-600 ring-1 ring-amber-500/20'">{{ statusLabel(budget.status) }}</span><Button v-if="canApprove && budget.status === 'draft'" variant="outline" size="sm" class="gap-2" @click="approve(budget.id)"><CheckCircle2 class="size-4" /> Duyệt</Button></div>
                </div>
                <div class="overflow-auto px-5 py-2 sm:px-6"><table class="min-w-full text-sm"><thead class="text-left text-[11px] uppercase tracking-wide text-muted-foreground"><tr><th class="py-3">Tháng</th><th class="py-3">Khoản mục</th><th class="py-3 text-right">Ngân sách</th><th class="py-3 text-right">Đã phát sinh</th><th class="py-3 text-right">Còn lại</th></tr></thead><tbody class="divide-y divide-border/60"><tr v-for="line in budget.lines" :key="line.id" class="hover:bg-muted/30"><td class="py-3">{{ line.period_month }}</td><td class="py-3 font-medium"><span>{{ line.account_code }} · {{ line.account_name }}</span><span v-if="line.category_name" class="ml-1 text-xs font-normal text-muted-foreground"> · {{ line.category_name }}</span><span class="block text-xs font-normal text-muted-foreground">{{ line.actual_basis }}</span></td><td class="py-3 text-right tabular-nums">{{ money(line.budget_amount) }}</td><td class="py-3 text-right tabular-nums">{{ money(line.actual_amount) }}</td><td class="py-3 text-right font-medium tabular-nums" :class="line.variance_amount < 0 ? 'text-rose-600' : 'text-emerald-600'">{{ money(line.variance_amount) }}</td></tr></tbody></table></div>
            </section>
            <div v-if="budgets.data.length === 0" class="rounded-2xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground">Chưa có ngân sách chi phí.</div>
        </div>
    </div>
</template>
