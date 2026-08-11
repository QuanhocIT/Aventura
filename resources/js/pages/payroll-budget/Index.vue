<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';
import { confirmDialog } from '@/composables/useConfirm';

defineOptions({ layout: AppLayout });

interface BranchRow {
    branch_id: number;
    branch_name: string;
    budget_amount: number;
    committed: number;
    remaining: number | null;
    over_budget: boolean;
}
interface WageTier {
    id: number;
    branch_id: number | null;
    name: string;
    compensation_type: 'hourly' | 'shift' | 'fixed';
    rate: number;
    is_active: boolean;
}

const props = defineProps<{
    month: string;
    branches: BranchRow[];
    wageTiers: WageTier[];
}>();

const vnd = (n: number) => new Intl.NumberFormat('vi-VN').format(Math.round(n)) + 'đ';
const compLabel: Record<string, string> = { hourly: 'Theo giờ', shift: 'Theo ca', fixed: 'Cố định (tháng)' };

const budgetForm = useForm({ branch_id: props.branches[0]?.branch_id ?? null, budget_amount: 0, notes: '' });
const tierForm = useForm({ name: '', compensation_type: 'fixed', rate: 0, branch_id: null as number | null });

function saveBudget() {
    budgetForm.post('/payroll-budget/budget', {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã cập nhật quỹ lương.'),
        onError: (e) => toast.error(String(Object.values(e)[0] ?? 'Có lỗi.')),
    });
}
function addTier() {
    tierForm.post('/payroll-budget/wage-tiers', {
        preserveScroll: true,
        onSuccess: () => { toast.success('Đã thêm bậc lương.'); tierForm.reset(); },
        onError: (e) => toast.error(String(Object.values(e)[0] ?? 'Có lỗi.')),
    });
}
async function removeTier(id: number) {
    if (await confirmDialog({ title: 'Xoá bậc lương', description: 'Bạn chắc chắn xoá bậc lương này?', variant: 'destructive' })) {
        router.delete(`/payroll-budget/wage-tiers/${id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Quỹ lương chi nhánh" />
    <div class="mx-auto max-w-5xl space-y-8 p-4 sm:p-6">
        <header>
            <h1 class="text-xl font-bold tracking-tight">Quỹ lương theo chi nhánh</h1>
            <p class="text-sm text-muted-foreground">Kỳ áp dụng: tháng {{ month }} · Chủ doanh nghiệp đặt quỹ &amp; bậc lương; tổng lương nhân viên mỗi chi nhánh không vượt quỹ.</p>
        </header>

        <!-- Quỹ theo chi nhánh -->
        <section class="rounded-xl border border-border bg-card shadow-sm">
            <div class="border-b border-border px-5 py-3 text-sm font-semibold">Quỹ &amp; mức đã cam kết</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr class="border-b border-border">
                            <th class="px-5 py-2 font-medium">Chi nhánh</th>
                            <th class="px-5 py-2 text-right font-medium">Quỹ tháng</th>
                            <th class="px-5 py-2 text-right font-medium">Đã cam kết</th>
                            <th class="px-5 py-2 text-right font-medium">Còn lại</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in branches" :key="b.branch_id" class="border-b border-border/60">
                            <td class="px-5 py-2.5 font-medium">{{ b.branch_name }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums">{{ b.budget_amount ? vnd(b.budget_amount) : '—' }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums">{{ vnd(b.committed) }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums"
                                :class="b.over_budget ? 'font-bold text-rose-600' : b.remaining !== null ? 'text-emerald-600' : 'text-muted-foreground'">
                                {{ b.remaining === null ? 'Chưa đặt quỹ' : (b.over_budget ? 'Vượt ' + vnd(-b.remaining) : vnd(b.remaining)) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Đặt quỹ -->
            <div class="flex flex-wrap items-end gap-3 border-t border-border px-5 py-4">
                <label class="text-xs font-medium">Chi nhánh
                    <select v-model="budgetForm.branch_id" class="mt-1 block rounded-lg border border-border bg-background px-3 py-1.5 text-sm">
                        <option v-for="b in branches" :key="b.branch_id" :value="b.branch_id">{{ b.branch_name }}</option>
                    </select>
                </label>
                <label class="text-xs font-medium">Quỹ tháng (đ)
                    <input v-model.number="budgetForm.budget_amount" type="number" min="0" class="mt-1 block w-40 rounded-lg border border-border bg-background px-3 py-1.5 text-sm" />
                </label>
                <button @click="saveBudget" :disabled="budgetForm.processing"
                    class="rounded-lg bg-primary px-4 py-1.5 text-sm font-semibold text-primary-foreground disabled:opacity-50">Lưu quỹ</button>
            </div>
        </section>

        <!-- Bậc lương -->
        <section class="rounded-xl border border-border bg-card shadow-sm">
            <div class="border-b border-border px-5 py-3 text-sm font-semibold">Bậc lương (Quản lý chỉ được chọn từ đây khi tạo nhân viên)</div>
            <ul class="divide-y divide-border/60">
                <li v-for="t in wageTiers" :key="t.id" class="flex items-center justify-between px-5 py-2.5 text-sm">
                    <span><b>{{ t.name }}</b> · {{ compLabel[t.compensation_type] }} · {{ vnd(t.rate) }}
                        <span v-if="t.branch_id" class="text-xs text-muted-foreground">(chi nhánh)</span>
                        <span v-else class="text-xs text-muted-foreground">(toàn chuỗi)</span>
                    </span>
                    <button @click="removeTier(t.id)" class="text-xs text-rose-600 hover:underline">Xoá</button>
                </li>
                <li v-if="!wageTiers.length" class="px-5 py-4 text-sm text-muted-foreground">Chưa có bậc lương nào.</li>
            </ul>
            <div class="flex flex-wrap items-end gap-3 border-t border-border px-5 py-4">
                <label class="text-xs font-medium">Tên bậc
                    <input v-model="tierForm.name" placeholder="VD: Phục vụ ca ngày" class="mt-1 block w-48 rounded-lg border border-border bg-background px-3 py-1.5 text-sm" />
                </label>
                <label class="text-xs font-medium">Hình thức
                    <select v-model="tierForm.compensation_type" class="mt-1 block rounded-lg border border-border bg-background px-3 py-1.5 text-sm">
                        <option value="hourly">Theo giờ</option>
                        <option value="shift">Theo ca</option>
                        <option value="fixed">Cố định (tháng)</option>
                    </select>
                </label>
                <label class="text-xs font-medium">Mức (đ)
                    <input v-model.number="tierForm.rate" type="number" min="0" class="mt-1 block w-36 rounded-lg border border-border bg-background px-3 py-1.5 text-sm" />
                </label>
                <button @click="addTier" :disabled="tierForm.processing"
                    class="rounded-lg bg-primary px-4 py-1.5 text-sm font-semibold text-primary-foreground disabled:opacity-50">Thêm bậc</button>
            </div>
        </section>
    </div>
</template>
