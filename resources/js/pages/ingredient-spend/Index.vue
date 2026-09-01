<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    CircleAlert,
    Package,
    RefreshCw,
    Wallet,
} from 'lucide-vue-next';
import { reactive } from 'vue';
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Branch = {
    id: number;
    name: string;
    code?: string | null;
    is_central?: boolean;
};
type BranchRow = {
    branch_id: number;
    branch_name: string;
    branch_code?: string | null;
    is_central?: boolean;
    central_purchase_amount: number;
    external_receipt_amount: number;
    central_supply_amount: number;
    external_purchase_amount: number;
    interbranch_transfer_amount: number;
    total_inbound_value: number;
};
type Transaction = {
    id: number;
    occurred_at: string | null;
    document_code: string;
    category: string;
    category_label: string;
    branch_name: string;
    ingredient_name: string;
    unit_symbol: string | null;
    quantity: number;
    unit_cost: number;
    amount: number;
    supplier_name: string | null;
    source_type: string | null;
    source_id: number | null;
    notes: string | null;
};
type Report = {
    summary: {
        central_purchase_amount: number;
        external_receipt_amount: number;
        central_supply_amount: number;
        external_purchase_amount: number;
        interbranch_transfer_amount: number;
        actual_cash_commitment_amount: number;
        unclassified_inbound_amount: number;
        unclassified_inbound_count: number;
    };
    branch_rows: BranchRow[];
    transactions: Transaction[];
    transaction_count: number;
    central_branch: { id: number; name: string } | null;
};

const props = defineProps<{
    report: Report;
    filters: { date_from: string; date_to: string; branch_id: number | null };
    branches: Branch[];
    canViewAllBranches: boolean;
}>();

const filters = reactive({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    branch_id:
        props.filters.branch_id === null
            ? 'all'
            : String(props.filters.branch_id),
});

const money = (value: number) =>
    new Intl.NumberFormat('vi-VN').format(Math.round(value)) + ' đ';
const qty = (value: number) =>
    new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(value);

function applyFilters() {
    router.get(
        '/finance/ingredient-spend',
        {
            date_from: filters.date_from,
            date_to: filters.date_to,
            branch_id: filters.branch_id,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function resetFilters() {
    filters.date_from = props.filters.date_from;
    filters.date_to = props.filters.date_to;
    filters.branch_id = props.canViewAllBranches
        ? 'all'
        : String(props.filters.branch_id ?? '');
    applyFilters();
}
</script>

<template>
    <Head title="Giá trị nhập nguyên liệu" />

    <div class="space-y-6 p-4 sm:p-6">
        <FinancePageHeader
            title="Giá trị nhập nguyên liệu"
            description="Đối soát tiền mua vào Kho Tổng, giá trị cấp phát xuống chi nhánh và phần chi nhánh tự mua ngoài."
            :icon="BadgeDollarSign"
        >
            <template #actions>
                <Button variant="outline" class="gap-2" @click="applyFilters">
                    <RefreshCw class="size-4" />
                    Làm mới
                </Button>
            </template>
        </FinancePageHeader>

        <section
            class="rounded-2xl border border-border/60 bg-card/80 p-4 shadow-sm sm:p-5"
        >
            <div
                class="grid gap-3 md:grid-cols-[1fr_1fr_1.4fr_auto_auto] md:items-end"
            >
                <label
                    class="space-y-1 text-xs font-medium text-muted-foreground"
                >
                    Từ ngày
                    <Input v-model="filters.date_from" type="date" />
                </label>
                <label
                    class="space-y-1 text-xs font-medium text-muted-foreground"
                >
                    Đến ngày
                    <Input v-model="filters.date_to" type="date" />
                </label>
                <label
                    class="space-y-1 text-xs font-medium text-muted-foreground"
                >
                    Phạm vi chi nhánh
                    <select
                        v-model="filters.branch_id"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <option v-if="canViewAllBranches" value="all">
                            Toàn bộ nhà hàng
                        </option>
                        <option
                            v-for="branch in branches"
                            :key="branch.id"
                            :value="String(branch.id)"
                        >
                            {{ branch.name }}
                        </option>
                    </select>
                </label>
                <Button class="gap-2" @click="applyFilters">Áp dụng</Button>
                <Button variant="ghost" @click="resetFilters">Đặt lại</Button>
            </div>
            <p class="mt-3 text-xs text-muted-foreground">
                Nguồn số liệu là giao dịch kho đã ghi nhận thực tế. Nhập ngoài vào
                Kho Tổng được tách riêng, không phải mua hàng và không tạo công nợ.
                Đơn PO chưa nhận chưa được tính vào chi phí; cấp phát nội bộ chỉ là
                giá trị luân chuyển, không cộng lại vào tiền mua.
            </p>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div
                class="rounded-2xl border border-border/60 bg-card/80 p-5 shadow-sm"
            >
                <div
                    class="flex items-center justify-between text-muted-foreground"
                >
                    <span class="text-sm">Nhập ngoài vào Kho Tổng</span
                    ><Package class="size-5 text-orange-600" />
                </div>
                <p class="mt-4 text-2xl font-semibold tabular-nums">
                    {{ money(report.summary.external_receipt_amount) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Không qua nhà cung cấp, không tạo công nợ mua hàng
                </p>
            </div>
            <div
                class="rounded-2xl border border-border/60 bg-card/80 p-5 shadow-sm"
            >
                <div
                    class="flex items-center justify-between text-muted-foreground"
                >
                    <span class="text-sm">Mua vào Kho Tổng</span
                    ><Package class="size-5 text-primary" />
                </div>
                <p class="mt-4 text-2xl font-semibold tabular-nums">
                    {{ money(report.summary.central_purchase_amount) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Tiền mua thực tế đã nhập kho
                </p>
            </div>
            <div
                class="rounded-2xl border border-border/60 bg-card/80 p-5 shadow-sm"
            >
                <div
                    class="flex items-center justify-between text-muted-foreground"
                >
                    <span class="text-sm">Kho Tổng cấp xuống</span
                    ><BadgeDollarSign class="size-5 text-sky-600" />
                </div>
                <p class="mt-4 text-2xl font-semibold tabular-nums">
                    {{ money(report.summary.central_supply_amount) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Theo số lượng chi nhánh đã nhận tốt
                </p>
            </div>
            <div
                class="rounded-2xl border border-border/60 bg-card/80 p-5 shadow-sm"
            >
                <div
                    class="flex items-center justify-between text-muted-foreground"
                >
                    <span class="text-sm">Chi nhánh mua ngoài</span
                    ><Wallet class="size-5 text-amber-600" />
                </div>
                <p class="mt-4 text-2xl font-semibold tabular-nums">
                    {{ money(report.summary.external_purchase_amount) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Nhập trực tiếp tại chi nhánh kinh doanh
                </p>
            </div>
            <div
                class="rounded-2xl border border-border/60 bg-card/80 p-5 shadow-sm"
            >
                <div
                    class="flex items-center justify-between text-muted-foreground"
                >
                    <span class="text-sm">Tiền mua nguyên liệu</span
                    ><Wallet class="size-5 text-emerald-600" />
                </div>
                <p class="mt-4 text-2xl font-semibold tabular-nums">
                    {{ money(report.summary.actual_cash_commitment_amount) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Kho Tổng mua + chi nhánh mua ngoài
                </p>
            </div>
        </section>

        <section
            v-if="report.summary.unclassified_inbound_count > 0"
            class="flex gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/5 p-4 text-sm"
        >
            <CircleAlert class="mt-0.5 size-5 shrink-0 text-amber-600" />
            <div>
                <p class="font-medium">
                    Có {{ report.summary.unclassified_inbound_count }} giao dịch
                    nhập chưa phân loại
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Giá trị
                    {{ money(report.summary.unclassified_inbound_amount) }}
                    không được tự động gán vào mua ngoài/cấp phát. Cần kiểm tra
                    chứng từ hoặc cập nhật nguồn nghiệp vụ.
                </p>
            </div>
        </section>

        <section
            v-if="!report.central_branch"
            class="flex gap-3 rounded-2xl border border-rose-500/30 bg-rose-500/5 p-4 text-sm"
        >
            <CircleAlert class="mt-0.5 size-5 shrink-0 text-rose-600" />
            <div>
                <p class="font-medium">Chưa cấu hình Kho Tổng</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Số liệu nhập mua tại chi nhánh vẫn hiển thị, nhưng chưa thể
                    tách chính xác phần mua vào Kho Tổng và phần cấp phát nội
                    bộ.
                </p>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-2xl border border-border/60 bg-card/80 shadow-sm"
        >
            <div class="border-b border-border/60 px-5 py-4 sm:px-6">
                <h2 class="font-semibold">Đối soát theo chi nhánh</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Giá trị cấp phát và mua ngoài là giá trị hàng đi vào tồn chi
                    nhánh; không phải khoản tiền mua mới lần hai.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-sm">
                    <thead
                        class="bg-muted/30 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3">Chi nhánh</th>
                            <th class="px-5 py-3 text-right">Mua Kho Tổng</th>
                            <th class="px-5 py-3 text-right">
                                Nhận từ Kho Tổng
                            </th>
                            <th class="px-5 py-3 text-right">Mua ngoài</th>
                            <th class="px-5 py-3 text-right">Nhập ngoài Kho Tổng</th>
                            <th class="px-5 py-3 text-right">Điều chuyển</th>
                            <th class="px-5 py-3 text-right">
                                Tổng vào chi nhánh
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="row in report.branch_rows"
                            :key="row.branch_id"
                            class="hover:bg-muted/20"
                        >
                            <td class="px-5 py-3 font-medium">
                                {{ row.branch_name
                                }}<span
                                    v-if="row.is_central"
                                    class="ml-2 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] text-primary"
                                    >Kho Tổng</span
                                >
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                {{ money(row.central_purchase_amount) }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                {{ money(row.central_supply_amount) }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                {{ money(row.external_purchase_amount) }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                {{ money(row.external_receipt_amount) }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                {{ money(row.interbranch_transfer_amount) }}
                            </td>
                            <td
                                class="px-5 py-3 text-right font-semibold tabular-nums"
                            >
                                {{ money(row.total_inbound_value) }}
                            </td>
                        </tr>
                        <tr v-if="report.branch_rows.length === 0">
                            <td
                                colspan="7"
                                class="px-5 py-10 text-center text-sm text-muted-foreground"
                            >
                                Chưa có dữ liệu trong khoảng thời gian này.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-2xl border border-border/60 bg-card/80 shadow-sm"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 px-5 py-4 sm:px-6"
            >
                <div>
                    <h2 class="font-semibold">Nhật ký giá trị nhập</h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Hiển thị {{ report.transactions.length }} /
                        {{ report.transaction_count }} giao dịch phân loại.
                    </p>
                </div>
                <span class="text-xs text-muted-foreground"
                    >{{ filters.date_from }} → {{ filters.date_to }}</span
                >
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-sm">
                    <thead
                        class="bg-muted/30 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3">Thời gian / chứng từ</th>
                            <th class="px-5 py-3">Loại</th>
                            <th class="px-5 py-3">Chi nhánh</th>
                            <th class="px-5 py-3">Nguyên liệu</th>
                            <th class="px-5 py-3 text-right">SL</th>
                            <th class="px-5 py-3 text-right">Giá trị</th>
                            <th class="px-5 py-3">Nhà cung cấp / nguồn bên ngoài</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="item in report.transactions"
                            :key="item.id"
                            class="hover:bg-muted/20"
                        >
                            <td class="px-5 py-3">
                                <div class="font-medium">
                                    {{ item.document_code }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ item.occurred_at }}
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span
                                    class="rounded-full bg-primary/10 px-2 py-1 text-xs text-primary"
                                    >{{ item.category_label }}</span
                                >
                            </td>
                            <td class="px-5 py-3">{{ item.branch_name }}</td>
                            <td class="px-5 py-3">
                                {{ item.ingredient_name }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                {{ qty(item.quantity) }}
                                {{ item.unit_symbol || '' }}
                            </td>
                            <td
                                class="px-5 py-3 text-right font-medium tabular-nums"
                                :class="item.amount < 0 ? 'text-rose-600' : ''"
                            >
                                {{ money(item.amount) }}
                            </td>
                            <td class="px-5 py-3 text-muted-foreground">
                                {{ item.supplier_name || '—' }}
                            </td>
                        </tr>
                        <tr v-if="report.transactions.length === 0">
                            <td
                                colspan="7"
                                class="px-5 py-10 text-center text-sm text-muted-foreground"
                            >
                                Chưa có giao dịch phân loại.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
