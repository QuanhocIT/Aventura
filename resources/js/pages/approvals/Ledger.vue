<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { BookLock, Check, Eye, ShieldCheck, ShieldX } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Decision = {
    id: number;
    approval_request_id: number;
    operation_type: string;
    operation_label: string;
    decision: string;
    amount_involved: number | null;
    decided_by_name: string;
    decided_by_role: string | null;
    branch_name: string | null;
    reason: string | null;
    policy_snapshot: Record<string, unknown> | null;
    ip_address: string | null;
    owner_reviewed_at: string | null;
    owner_reviewed_by: string | null;
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

const props = defineProps<{
    decisions: Paginated<Decision>;
    filters: Record<string, string | number | null>;
    summary: {
        awaiting_review: number;
        this_month: number;
        amount_this_month: number;
    };
    branches: { id: number; name: string }[];
    managers: { id: number; name: string }[];
}>();

const currency = new Intl.NumberFormat('vi-VN');
const expandedId = ref<number | null>(null);
const acknowledgeForm = useForm({});

function applyFilter(key: string, value: string) {
    router.get(
        '/approvals/ledger',
        { ...props.filters, [key]: value || null },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function acknowledge(decision: Decision) {
    acknowledgeForm.patch(`/approvals/decisions/${decision.id}/acknowledge`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã ghi nhận xem xét.'),
        onError: () => toast.error('Không ghi nhận được.'),
    });
}
</script>

<template>
    <Head title="Sổ phê duyệt" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
        <div class="flex flex-col gap-1">
            <h1
                class="flex items-center gap-2 text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
            >
                <BookLock class="h-5 w-5 text-violet-600" />
                Quản lý đã duyệt gì
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Mọi quyết định Quản lý chi nhánh tự xử lý theo thẩm quyền được
                ủy quyền. Bản ghi không thể sửa hay xóa.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div
                class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-950/20"
            >
                <div
                    class="text-2xl font-bold text-amber-700 tabular-nums dark:text-amber-400"
                >
                    {{ summary.awaiting_review }}
                </div>
                <div
                    class="mt-1 text-[10px] font-semibold tracking-wide text-amber-600/80 uppercase dark:text-amber-500/80"
                >
                    Chờ bạn xem xét
                </div>
            </div>
            <div
                class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="text-2xl font-bold text-slate-900 tabular-nums dark:text-slate-100"
                >
                    {{ summary.this_month }}
                </div>
                <div
                    class="mt-1 text-[10px] font-semibold tracking-wide text-slate-400 uppercase"
                >
                    Quyết định tháng này
                </div>
            </div>
            <div
                class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="text-2xl font-bold text-slate-900 tabular-nums dark:text-slate-100"
                >
                    {{ currency.format(summary.amount_this_month) }}đ
                </div>
                <div
                    class="mt-1 text-[10px] font-semibold tracking-wide text-slate-400 uppercase"
                >
                    Giá trị đã duyệt tháng này
                </div>
            </div>
        </div>

        <Card>
            <CardHeader
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <CardTitle class="text-base">Sổ phê duyệt</CardTitle>
                    <CardDescription
                        >{{ decisions.total }} quyết định được ghi
                        nhận.</CardDescription
                    >
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select
                        class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium dark:border-slate-700 dark:bg-slate-900"
                        :value="filters.branch_id ?? ''"
                        @change="
                            applyFilter(
                                'branch_id',
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option value="">Mọi chi nhánh</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>

                    <select
                        class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium dark:border-slate-700 dark:bg-slate-900"
                        :value="filters.decided_by ?? ''"
                        @change="
                            applyFilter(
                                'decided_by',
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option value="">Mọi quản lý</option>
                        <option v-for="m in managers" :key="m.id" :value="m.id">
                            {{ m.name }}
                        </option>
                    </select>

                    <button
                        type="button"
                        @click="
                            applyFilter(
                                'unreviewed',
                                filters.unreviewed ? '' : '1',
                            )
                        "
                        :class="[
                            'rounded-lg px-3 py-1.5 text-xs font-bold transition-colors',
                            filters.unreviewed
                                ? 'bg-amber-500 text-white'
                                : 'border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300',
                        ]"
                    >
                        Chưa xem xét
                    </button>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <div
                    v-if="decisions.data.length === 0"
                    class="flex flex-col items-center justify-center gap-3 py-16 text-slate-400"
                >
                    <BookLock class="h-8 w-8" />
                    <p class="text-sm font-medium">
                        Chưa có quyết định ủy quyền nào được ghi nhận.
                    </p>
                </div>

                <ul
                    v-else
                    class="divide-y divide-slate-100 dark:divide-slate-800"
                >
                    <li
                        v-for="d in decisions.data"
                        :key="d.id"
                        class="px-5 py-4"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <component
                                        :is="
                                            d.decision === 'approved'
                                                ? ShieldCheck
                                                : ShieldX
                                        "
                                        :class="[
                                            'h-4 w-4 shrink-0',
                                            d.decision === 'approved'
                                                ? 'text-emerald-600'
                                                : 'text-rose-600',
                                        ]"
                                    />
                                    <span
                                        class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ d.operation_label }}
                                    </span>
                                    <span
                                        v-if="d.amount_involved"
                                        class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 tabular-nums dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        {{
                                            currency.format(d.amount_involved)
                                        }}đ
                                    </span>
                                </div>

                                <p
                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    <span class="font-semibold">{{
                                        d.decided_by_name
                                    }}</span>
                                    <template v-if="d.branch_name">
                                        · {{ d.branch_name }}
                                    </template>
                                    · {{ d.created_at }}
                                </p>

                                <p
                                    v-if="d.reason"
                                    class="mt-1.5 text-xs text-slate-500 italic dark:text-slate-400"
                                >
                                    “{{ d.reason }}”
                                </p>

                                <button
                                    type="button"
                                    class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                                    @click="
                                        expandedId =
                                            expandedId === d.id ? null : d.id
                                    "
                                >
                                    <Eye class="h-3 w-3" />
                                    {{
                                        expandedId === d.id
                                            ? 'Ẩn chi tiết'
                                            : 'Hạn mức áp dụng khi duyệt'
                                    }}
                                </button>

                                <pre
                                    v-if="expandedId === d.id"
                                    class="mt-2 max-w-full overflow-x-auto rounded-lg bg-slate-50 p-3 text-[10px] leading-relaxed text-slate-600 dark:bg-slate-900/60 dark:text-slate-400"
                                    >{{
                                        JSON.stringify(
                                            {
                                                ...d.policy_snapshot,
                                                ip: d.ip_address,
                                                yeu_cau: d.approval_request_id,
                                            },
                                            null,
                                            2,
                                        )
                                    }}</pre
                                >
                            </div>

                            <div class="shrink-0">
                                <div
                                    v-if="d.owner_reviewed_at"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400"
                                >
                                    <Check class="h-3 w-3" />
                                    Đã xem {{ d.owner_reviewed_at }}
                                </div>
                                <Button
                                    v-else
                                    size="sm"
                                    variant="outline"
                                    class="h-8 text-xs font-semibold"
                                    :disabled="acknowledgeForm.processing"
                                    @click="acknowledge(d)"
                                >
                                    Ghi nhận đã xem
                                </Button>
                            </div>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <div
            v-if="decisions.links.length > 3"
            class="flex flex-wrap items-center justify-center gap-1"
        >
            <button
                v-for="link in decisions.links"
                :key="link.label"
                type="button"
                :disabled="!link.url"
                @click="link.url && router.visit(link.url)"
                :class="[
                    'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                    link.active
                        ? 'bg-slate-800 text-white dark:bg-slate-100 dark:text-slate-900'
                        : link.url
                          ? 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                          : 'cursor-not-allowed text-slate-300 dark:text-slate-700',
                ]"
                v-html="link.label"
            />
        </div>
    </div>
</template>
