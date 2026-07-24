<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    FileSearch2,
    Loader2,
    Lock,
    Save,
    Shield,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
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
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ policy: any; recentAudit: any[] }>();

const page = usePage();
watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    },
);

const activeTab = ref<'config' | 'audit'>('config');

const staffDiscountText = computed(
    () => `${props.policy.max_discount_percent_staff}%`,
);
const managerDiscountText = computed(
    () => `${props.policy.max_discount_percent_manager}%`,
);
const staffCancelText = computed(() =>
    props.policy.max_cancel_amount_staff === 0
        ? 'Không cho phép'
        : `${Number(props.policy.max_cancel_amount_staff).toLocaleString()}đ`,
);
const managerCancelText = computed(
    () => `${Number(props.policy.max_cancel_amount_manager).toLocaleString()}đ`,
);
const auditLogsCount = computed(() => props.recentAudit?.length ?? 0);

const form = useForm({
    max_discount_percent_staff: props.policy.max_discount_percent_staff ?? 10,
    max_discount_percent_manager:
        props.policy.max_discount_percent_manager ?? 30,
    max_cancel_amount_staff: props.policy.max_cancel_amount_staff ?? 0,
    max_cancel_amount_manager: props.policy.max_cancel_amount_manager ?? 500000,
    staff_view_revenue: props.policy.staff_view_revenue ?? false,
    staff_view_salary: props.policy.staff_view_salary ?? false,
    staff_view_cost_price: props.policy.staff_view_cost_price ?? false,
    manager_view_other_salary: props.policy.manager_view_other_salary ?? false,
    restrict_to_shift_hours: props.policy.restrict_to_shift_hours ?? false,
    audit_all_changes: props.policy.audit_all_changes ?? true,
});

function submit() {
    form.post('/operation-policies', { preserveScroll: true });
}

const actionLabel: Record<string, string> = {
    policy_check: 'Kiểm tra quyền',
    discount_applied: 'Áp dụng giảm giá',
    order_cancelled: 'Hủy đơn hàng',
    discount_applied_bypass: 'Bypass giảm giá',
    policy_updated: 'Cập nhật chính sách',
    system_settings_update: 'Cập nhật hệ thống',
};
</script>

<template>
    <Head title="Phân quyền thao tác" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- HEADER -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400"
                >
                    <ShieldCheck class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Phân Quyền & Giới Hạn Thao Tác
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Cấu hình giới hạn giảm giá, hủy đơn, truy cập dữ liệu
                        theo vai trò.
                    </p>
                </div>
            </div>
        </div>

        <!-- KPI STATS CARDS -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Discount Limits -->
            <Card
                class="shadow-xs transition-transform hover:translate-y-[-2px]"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >Hạn mức giảm giá</CardDescription
                    >
                    <Lock class="size-4 text-slate-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >{{ staffDiscountText }} /
                        {{ managerDiscountText }}</span
                    >
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Nhân viên tối đa / Quản lý tối đa
                    </p>
                </CardContent>
            </Card>

            <!-- Cancel limits -->
            <Card
                class="border-emerald-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-emerald-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-emerald-500 uppercase"
                        >Giới hạn hủy đơn</CardDescription
                    >
                    <Shield
                        class="size-4 text-emerald-600 dark:text-emerald-400"
                    />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                        >{{ managerCancelText }}</span
                    >
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Mức trần hủy của Quản lý (Nhân viên:
                        {{ staffCancelText }})
                    </p>
                </CardContent>
            </Card>

            <!-- Audit Trail Count -->
            <Card
                class="border-indigo-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-indigo-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-indigo-500 uppercase"
                        >Nhật ký Audit</CardDescription
                    >
                    <FileSearch2
                        class="size-4 text-indigo-600 dark:text-indigo-400"
                    />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-2xl font-black text-indigo-600 dark:text-indigo-400"
                        >{{ auditLogsCount }} log</span
                    >
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        ghi nhận thao tác nhạy cảm gần đây
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- TAB NAVIGATION -->
        <div class="flex items-center gap-2 border-b pb-2">
            <button
                v-for="tab in [
                    { key: 'config', label: 'Cấu hình chính sách', icon: '🛡️' },
                    {
                        key: 'audit',
                        label: 'Audit Trail (Nhật ký)',
                        icon: '📋',
                    },
                ]"
                :key="tab.key"
                type="button"
                @click="activeTab = tab.key as any"
                :class="[
                    'border-b-2 px-4 py-2 text-xs font-bold transition-all focus:outline-none',
                    activeTab === tab.key
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                        : 'hover:border-slate-350 border-transparent text-slate-400 hover:text-slate-600 dark:hover:text-slate-300',
                ]"
            >
                {{ tab.icon }} {{ tab.label }}
            </button>
        </div>

        <!-- Config -->
        <form
            v-if="activeTab === 'config'"
            @submit.prevent="submit"
            class="animate-fade-in space-y-6"
        >
            <!-- Discount limits -->
            <Card
                class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md transition-all duration-200 hover:translate-y-[-1px] dark:border-slate-800 dark:bg-slate-900/45"
            >
                <CardHeader>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold"
                        ><Lock class="size-4 text-indigo-500" /> Giới hạn giảm
                        giá</CardTitle
                    >
                    <CardDescription
                        >Nhân viên/Quản lý chỉ được giảm tối đa X%. Vượt ngưỡng
                        → cần phê duyệt của Chủ cửa hàng.</CardDescription
                    >
                </CardHeader>
                <CardContent class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Nhân viên tối đa (%)</Label>
                        <Input
                            type="number"
                            step="0.1"
                            v-model="form.max_discount_percent_staff"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Quản lý tối đa (%)</Label>
                        <Input
                            type="number"
                            step="0.1"
                            v-model="form.max_discount_percent_manager"
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- Cancel limits -->
            <Card
                class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md transition-all duration-200 hover:translate-y-[-1px] dark:border-slate-800 dark:bg-slate-900/45"
            >
                <CardHeader>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold"
                        ><Lock class="size-4 text-indigo-500" /> Giới hạn hủy
                        đơn</CardTitle
                    >
                    <CardDescription
                        >Đơn hàng vượt giá trị X → chỉ Quản lý trở lên mới được
                        phép hủy. Nhập 0 = không cho phép hủy.</CardDescription
                    >
                </CardHeader>
                <CardContent class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Nhân viên tối đa (VND)</Label>
                        <Input
                            type="number"
                            v-model="form.max_cancel_amount_staff"
                        />
                        <span class="text-xs text-muted-foreground"
                            >0 = không cho phép nhân viên hủy đơn</span
                        >
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Quản lý tối đa (VND)</Label>
                        <Input
                            type="number"
                            v-model="form.max_cancel_amount_manager"
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- Data access -->
            <Card
                class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md transition-all duration-200 hover:translate-y-[-1px] dark:border-slate-800 dark:bg-slate-900/45"
            >
                <CardHeader>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold"
                        ><Shield class="size-4 text-indigo-500" /> Giới hạn truy
                        cập dữ liệu</CardTitle
                    >
                    <CardDescription
                        >Cấu hình các quyền hạn truy cập thông tin tài chính
                        nhạy cảm của nhân viên.</CardDescription
                    >
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-100 p-4 font-medium transition-colors hover:bg-muted/30 dark:border-slate-800/80"
                    >
                        <input
                            type="checkbox"
                            v-model="form.staff_view_revenue"
                            class="text-indigo-650 rounded focus:ring-indigo-500"
                        />
                        <span class="text-xs text-slate-700 dark:text-slate-200"
                            >Staff xem được doanh thu</span
                        >
                    </label>
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-100 p-4 font-medium transition-colors hover:bg-muted/30 dark:border-slate-800/80"
                    >
                        <input
                            type="checkbox"
                            v-model="form.staff_view_salary"
                            class="text-indigo-650 rounded focus:ring-indigo-500"
                        />
                        <span class="text-xs text-slate-700 dark:text-slate-200"
                            >Staff xem được lương (của mình)</span
                        >
                    </label>
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-100 p-4 font-medium transition-colors hover:bg-muted/30 dark:border-slate-800/80"
                    >
                        <input
                            type="checkbox"
                            v-model="form.staff_view_cost_price"
                            class="text-indigo-650 rounded focus:ring-indigo-500"
                        />
                        <span class="text-xs text-slate-700 dark:text-slate-200"
                            >Staff xem được giá vốn nguyên liệu</span
                        >
                    </label>
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-100 p-4 font-medium transition-colors hover:bg-muted/30 dark:border-slate-800/80"
                    >
                        <input
                            type="checkbox"
                            v-model="form.manager_view_other_salary"
                            class="text-indigo-650 rounded focus:ring-indigo-500"
                        />
                        <span class="text-xs text-slate-700 dark:text-slate-200"
                            >Manager xem được lương nhân viên khác</span
                        >
                    </label>
                </CardContent>
            </Card>

            <!-- Time & Audit -->
            <Card
                class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md transition-all duration-200 hover:translate-y-[-1px] dark:border-slate-800 dark:bg-slate-900/45"
            >
                <CardHeader>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold"
                        ><FileSearch2 class="size-4 text-indigo-500" /> Thời
                        gian & Audit</CardTitle
                    >
                    <CardDescription
                        >Cài đặt kiểm soát thời gian làm việc và ghi nhận nhật
                        ký thao tác.</CardDescription
                    >
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-100 p-4 font-medium transition-colors hover:bg-muted/30 dark:border-slate-800/80"
                    >
                        <input
                            type="checkbox"
                            v-model="form.restrict_to_shift_hours"
                            class="text-indigo-650 rounded focus:ring-indigo-500"
                        />
                        <span class="text-xs text-slate-700 dark:text-slate-200"
                            >Chỉ cho phép thao tác trong giờ ca làm việc
                            (staff/manager)</span
                        >
                    </label>
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-100 p-4 font-medium transition-colors hover:bg-muted/30 dark:border-slate-800/80"
                    >
                        <input
                            type="checkbox"
                            v-model="form.audit_all_changes"
                            class="text-indigo-650 rounded focus:ring-indigo-500"
                        />
                        <span class="text-xs text-slate-700 dark:text-slate-200"
                            >Ghi log tất cả thay đổi quan trọng (audit
                            trail)</span
                        >
                    </label>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    class="h-10 gap-2 rounded-xl bg-indigo-600 px-6 text-xs font-semibold text-white hover:bg-indigo-700"
                >
                    <Loader2
                        v-if="form.processing"
                        class="size-4 animate-spin"
                    />
                    <Save v-else class="size-4" />
                    {{ form.processing ? 'Đang lưu...' : 'Lưu chính sách' }}
                </Button>
            </div>
        </form>

        <!-- Audit Trail -->
        <Card
            v-if="activeTab === 'audit'"
            class="animate-fade-in overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md dark:border-slate-800 dark:bg-slate-900/45"
        >
            <CardHeader class="border-b border-border/60 pb-4">
                <CardTitle class="flex items-center gap-2 text-base font-bold"
                    ><FileSearch2 class="size-4 text-indigo-500" /> Audit Trail
                    — Nhật ký thao tác nhạy cảm</CardTitle
                >
                <CardDescription
                    >Ghi nhận tất cả các thay đổi chính sách, giảm giá vượt
                    quyền hoặc các hành động quan trọng.</CardDescription
                >
            </CardHeader>
            <CardContent class="p-0">
                <table class="w-full border-collapse text-left text-xs">
                    <thead
                        class="border-b bg-slate-100 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                    >
                        <tr class="border-b">
                            <th class="p-3.5">Thời gian</th>
                            <th class="p-3.5">Người thực hiện</th>
                            <th class="p-3.5">Vai trò</th>
                            <th class="p-3.5">Hành động</th>
                            <th class="p-3.5">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="log in recentAudit"
                            :key="log.id"
                            class="border-b last:border-0 hover:bg-muted/30"
                        >
                            <td class="p-3.5 font-medium text-muted-foreground">
                                {{ log.created_at }}
                            </td>
                            <td
                                class="p-3.5 font-bold text-slate-800 dark:text-slate-200"
                            >
                                {{ log.user_name }}
                            </td>
                            <td class="p-3.5">
                                <Badge
                                    variant="outline"
                                    class="bg-slate-50 text-[10px] font-semibold dark:bg-slate-800"
                                    >{{ log.user_role }}</Badge
                                >
                            </td>
                            <td
                                class="text-indigo-650 p-3.5 font-bold dark:text-indigo-400"
                            >
                                {{ actionLabel[log.action] ?? log.action }}
                            </td>
                            <td class="p-3.5 font-mono text-muted-foreground">
                                {{ log.ip_address }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!recentAudit.length"
                    class="py-16 text-center text-xs font-semibold text-slate-500"
                >
                    Chưa có nhật ký nào.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
