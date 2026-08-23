<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Calculator, CheckCircle2, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Line = { id: number; period_month: string; account_code: string; category_name: string | null; budget_amount: number; actual_amount: number; variance_amount: number };
type Budget = { id: number; name: string; branch_name: string | null; period_start: string; period_end: string; status: string; total_amount: number; lines: Line[] };
const props = defineProps<{ budgets: { data: Budget[] }; branches: Array<{ id: number; name: string }>; canApprove: boolean }>();
const showForm = ref(false);
const form = ref({ name: '', branch_id: '', period_start: `${new Date().getFullYear()}-01-01`, period_end: `${new Date().getFullYear()}-12-31`, notes: '', lines: [{ period_month: `${new Date().getFullYear()}-01`, account_code: '6271', category_id: null, budget_amount: 0 }] });
function money(v: number) { return new Intl.NumberFormat('vi-VN').format(v) + ' đ'; }
function addLine() { form.value.lines.push({ period_month: form.value.period_start.slice(0, 7), account_code: '6271', category_id: null, budget_amount: 0 }); }
function save() { router.post('/financial-budgets', form.value, { onSuccess: () => { showForm.value = false; } }); }
function approve(id: number) { if (window.confirm('Duyệt ngân sách này?')) router.patch(`/financial-budgets/${id}/approve`); }
</script>

<template>
    <Head title="Ngân sách tài chính" />
    <div class="space-y-6 p-4 sm:p-6">
        <FinancePageHeader title="Ngân sách tài chính" description="Lập ngân sách theo kỳ, tài khoản và chi nhánh; theo dõi thực tế." :icon="Calculator">
            <template #actions>
                <Button class="gap-2" @click="showForm = !showForm">
                    <Plus class="size-4" />
                    Tạo ngân sách
                </Button>
            </template>
        </FinancePageHeader>

        <section v-if="showForm" class="rounded-2xl border border-border/60 bg-card/80 p-5 shadow-sm sm:p-6">
            <div class="mb-5 flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary"><Plus class="size-4" /></div>
                <div><h2 class="font-semibold text-foreground">Thông tin ngân sách</h2><p class="text-sm text-muted-foreground">Khai báo phạm vi và các dòng ngân sách cần theo dõi.</p></div>
            </div>
            <div class="grid gap-3 md:grid-cols-4">
                <Input v-model="form.name" placeholder="Tên ngân sách" />
                <select v-model="form.branch_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"><option value="">Toàn nhà hàng</option><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option></select>
                <Input v-model="form.period_start" type="date" aria-label="Từ ngày" />
                <Input v-model="form.period_end" type="date" aria-label="Đến ngày" />
            </div>
            <div class="mt-5 space-y-2">
                <div class="grid gap-2 px-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground md:grid-cols-[1fr_1fr_1fr_auto]"><span>Tháng</span><span>Tài khoản</span><span>Số ngân sách</span><span /></div>
                <div v-for="(line, index) in form.lines" :key="index" class="grid items-center gap-2 rounded-xl border border-border/50 bg-muted/20 p-2 md:grid-cols-[1fr_1fr_1fr_auto]">
                    <Input v-model="line.period_month" type="month" />
                    <Input v-model="line.account_code" placeholder="TK (6271/6221/6211)" />
                    <input v-model.number="line.budget_amount" type="number" placeholder="Ngân sách" class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]" />
                    <Button v-if="form.lines.length > 1" variant="ghost" size="icon" type="button" class="text-rose-600 hover:bg-rose-500/10 hover:text-rose-600" aria-label="Xóa dòng" @click="form.lines.splice(index, 1)"><Trash2 class="size-4" /></Button>
                </div>
                <Button variant="ghost" size="sm" type="button" class="gap-2 text-primary" @click="addLine"><Plus class="size-4" /> Thêm dòng</Button>
            </div>
            <div class="mt-5 flex justify-end"><Button @click="save">Lưu nháp</Button></div>
        </section>

        <div class="space-y-4">
            <section v-for="budget in budgets.data" :key="budget.id" class="overflow-hidden rounded-2xl border border-border/60 bg-card/80 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 px-5 py-4 sm:px-6">
                    <div><h2 class="font-semibold text-foreground">{{ budget.name }}</h2><p class="text-xs text-muted-foreground">{{ budget.branch_name || 'Toàn nhà hàng' }} · {{ budget.period_start }} → {{ budget.period_end }}</p></div>
                    <div class="flex items-center gap-3"><span class="rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="budget.status === 'approved' ? 'bg-emerald-500/10 text-emerald-600 ring-1 ring-emerald-500/20' : 'bg-amber-500/10 text-amber-600 ring-1 ring-amber-500/20'">{{ budget.status }}</span><Button v-if="canApprove && budget.status === 'draft'" variant="outline" size="sm" class="gap-2" @click="approve(budget.id)"><CheckCircle2 class="size-4" /> Duyệt</Button></div>
                </div>
                <div class="overflow-auto px-5 py-2 sm:px-6"><table class="min-w-full text-sm"><thead class="text-left text-[11px] uppercase tracking-wide text-muted-foreground"><tr><th class="py-3">Tháng</th><th class="py-3">Tài khoản</th><th class="py-3 text-right">Ngân sách</th><th class="py-3 text-right">Thực tế</th><th class="py-3 text-right">Còn lại</th></tr></thead><tbody class="divide-y divide-border/60"><tr v-for="line in budget.lines" :key="line.id" class="hover:bg-muted/30"><td class="py-3">{{ line.period_month }}</td><td class="py-3 font-medium">{{ line.account_code }}<span v-if="line.category_name" class="ml-1 text-xs font-normal text-muted-foreground"> · {{ line.category_name }}</span></td><td class="py-3 text-right tabular-nums">{{ money(line.budget_amount) }}</td><td class="py-3 text-right tabular-nums">{{ money(line.actual_amount) }}</td><td class="py-3 text-right font-medium tabular-nums" :class="line.variance_amount < 0 ? 'text-rose-600' : 'text-emerald-600'">{{ money(line.variance_amount) }}</td></tr></tbody></table></div>
            </section>
            <div v-if="budgets.data.length === 0" class="rounded-2xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground">Chưa có ngân sách.</div>
        </div>
    </div>
</template>
