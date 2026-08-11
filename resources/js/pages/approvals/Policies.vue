<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Ban, Save, ShieldCheck, SlidersHorizontal } from 'lucide-vue-next';
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

type Policy = {
    id: number;
    operation_type: string;
    operation_label: string;
    branch_id: number | null;
    branch_name: string | null;
    manager_can_approve: boolean;
    manager_limit_amount: number | null;
    manager_daily_limit: number | null;
    manager_monthly_limit: number | null;
    requires_owner_countersign: boolean;
    conditions: Record<string, unknown> | null;
    is_active: boolean;
};

const props = defineProps<{
    policies: Policy[];
    branches: { id: number; name: string }[];
    forbiddenForManager: {
        operation_type: string;
        operation_label: string;
    }[];
}>();

const form = useForm({
    policies: props.policies.map((p) => ({
        operation_type: p.operation_type,
        branch_id: p.branch_id,
        manager_can_approve: p.manager_can_approve,
        manager_limit_amount: p.manager_limit_amount,
        manager_daily_limit: p.manager_daily_limit,
        manager_monthly_limit: p.manager_monthly_limit,
        requires_owner_countersign: p.requires_owner_countersign,
        is_active: p.is_active,
    })),
});

const conditionLabels: Record<string, string> = {
    kitchen_not_started: 'Chỉ khi bếp chưa bấm bắt đầu chế biến',
};

function conditionText(policy: Policy): string | null {
    if (!policy.conditions) {
        return null;
    }

    const active = Object.entries(policy.conditions)
        .filter(([, value]) => value === true)
        .map(([key]) => conditionLabels[key] ?? key);

    return active.length > 0 ? active.join(' · ') : null;
}

function save() {
    form.put('/approvals/policies', {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã cập nhật ma trận thẩm quyền.'),
        onError: () => toast.error('Không lưu được thay đổi.'),
    });
}
</script>

<template>
    <Head title="Thẩm quyền phê duyệt" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
        <div class="flex flex-col gap-1">
            <h1
                class="flex items-center gap-2 text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
            >
                <SlidersHorizontal class="h-5 w-5 text-indigo-600" />
                Thẩm quyền phê duyệt
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Quyết định loại việc nào Quản lý chi nhánh được tự duyệt, và
                trong hạn mức bao nhiêu. Mọi quyết định của họ vẫn được ghi lại
                và báo về cho bạn.
            </p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Ủy quyền cho Quản lý</CardTitle>
                <CardDescription>
                    Bỏ trống hạn mức nghĩa là không giới hạn theo giá trị. Hạn
                    mức ngày và tháng được cộng dồn để chặn việc chia nhỏ nhiều
                    yêu cầu.
                </CardDescription>
            </CardHeader>

            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead>
                            <tr
                                class="border-y border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/60"
                            >
                                <th
                                    class="px-4 py-2.5 text-left text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Loại thao tác
                                </th>
                                <th
                                    class="px-3 py-2.5 text-center text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Quản lý duyệt
                                </th>
                                <th
                                    class="px-3 py-2.5 text-right text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Mỗi lần
                                </th>
                                <th
                                    class="px-3 py-2.5 text-right text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Mỗi ngày
                                </th>
                                <th
                                    class="px-3 py-2.5 text-right text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Mỗi tháng
                                </th>
                                <th
                                    class="px-3 py-2.5 text-center text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Chủ hậu kiểm
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr
                                v-for="(row, i) in form.policies"
                                :key="`${row.operation_type}-${row.branch_id ?? 'all'}`"
                                class="hover:bg-slate-50/60 dark:hover:bg-slate-900/40"
                            >
                                <td class="px-4 py-2.5">
                                    <div
                                        class="font-medium text-slate-800 dark:text-slate-200"
                                    >
                                        {{ policies[i].operation_label }}
                                    </div>
                                    <div
                                        v-if="policies[i].branch_name"
                                        class="text-[10px] font-semibold text-indigo-500"
                                    >
                                        Riêng {{ policies[i].branch_name }}
                                    </div>
                                    <div
                                        v-if="conditionText(policies[i])"
                                        class="mt-0.5 text-[10px] text-amber-600 dark:text-amber-500"
                                    >
                                        {{ conditionText(policies[i]) }}
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <input
                                        v-model="row.manager_can_approve"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 accent-emerald-600"
                                    />
                                </td>
                                <td class="px-3 py-2.5">
                                    <input
                                        v-model.number="
                                            row.manager_limit_amount
                                        "
                                        type="number"
                                        min="0"
                                        placeholder="—"
                                        :disabled="!row.manager_can_approve"
                                        class="w-28 rounded-lg border border-slate-200 px-2 py-1 text-right text-xs tabular-nums disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900"
                                    />
                                </td>
                                <td class="px-3 py-2.5">
                                    <input
                                        v-model.number="row.manager_daily_limit"
                                        type="number"
                                        min="0"
                                        placeholder="—"
                                        :disabled="!row.manager_can_approve"
                                        class="w-28 rounded-lg border border-slate-200 px-2 py-1 text-right text-xs tabular-nums disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900"
                                    />
                                </td>
                                <td class="px-3 py-2.5">
                                    <input
                                        v-model.number="
                                            row.manager_monthly_limit
                                        "
                                        type="number"
                                        min="0"
                                        placeholder="—"
                                        :disabled="!row.manager_can_approve"
                                        class="w-28 rounded-lg border border-slate-200 px-2 py-1 text-right text-xs tabular-nums disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900"
                                    />
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <input
                                        v-model="row.requires_owner_countersign"
                                        type="checkbox"
                                        :disabled="!row.manager_can_approve"
                                        class="h-4 w-4 rounded border-slate-300 accent-indigo-600 disabled:opacity-40"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end">
            <Button :disabled="form.processing" class="gap-1.5" @click="save">
                <Save class="h-4 w-4" />
                Lưu thay đổi
            </Button>
        </div>

        <!-- Ranh giới cứng -->
        <Card
            class="border-rose-200 bg-rose-50/40 dark:border-rose-900/40 dark:bg-rose-950/10"
        >
            <CardHeader>
                <CardTitle
                    class="flex items-center gap-2 text-base text-rose-700 dark:text-rose-400"
                >
                    <Ban class="h-4 w-4" />
                    Không bao giờ giao cho Quản lý
                </CardTitle>
                <CardDescription>
                    Các thao tác dưới đây bị chặn ở tầng hệ thống. Không có công
                    tắc nào bật được, kể cả tài khoản Chủ doanh nghiệp.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul class="flex flex-wrap gap-2">
                    <li
                        v-for="f in forbiddenForManager"
                        :key="f.operation_type"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-white px-2.5 py-1 text-xs font-medium text-rose-700 dark:border-rose-900/40 dark:bg-slate-900 dark:text-rose-400"
                    >
                        <ShieldCheck class="h-3 w-3" />
                        {{ f.operation_label }}
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
