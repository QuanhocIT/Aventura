<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { FileSearch2, Loader2, Lock, Save, Shield, ShieldCheck } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ policy: any; recentAudit: any[] }>();

const page = usePage();
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
});

const activeTab = ref<'config' | 'audit'>('config');

const form = useForm({
    max_discount_percent_staff: props.policy.max_discount_percent_staff ?? 10,
    max_discount_percent_manager: props.policy.max_discount_percent_manager ?? 30,
    max_cancel_amount_staff: props.policy.max_cancel_amount_staff ?? 0,
    max_cancel_amount_manager: props.policy.max_cancel_amount_manager ?? 500000,
    staff_view_revenue: props.policy.staff_view_revenue ?? false,
    staff_view_salary: props.policy.staff_view_salary ?? false,
    staff_view_cost_price: props.policy.staff_view_cost_price ?? false,
    manager_view_other_salary: props.policy.manager_view_other_salary ?? false,
    restrict_to_shift_hours: props.policy.restrict_to_shift_hours ?? false,
    audit_all_changes: props.policy.audit_all_changes ?? true,
});

function submit() { form.post('/operation-policies', { preserveScroll: true }); }

const actionLabel: Record<string, string> = {
    policy_check: 'Kiểm tra quyền', discount_applied: 'Áp dụng giảm giá',
    order_cancelled: 'Hủy đơn hàng', discount_applied_bypass: 'Bypass giảm giá',
    policy_updated: 'Cập nhật chính sách', system_settings_update: 'Cập nhật hệ thống',
};
</script>

<template>
    <Head title="Phân quyền thao tác" />

    <div class="flex flex-col gap-6 p-6 max-w-5xl mx-auto">
        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600">
                <ShieldCheck class="size-6" />
            </div>
            <div>
                <h1 class="text-2xl font-bold">Phân Quyền & Giới Hạn Thao Tác</h1>
                <p class="text-sm text-muted-foreground">Cấu hình giới hạn giảm giá, hủy đơn, truy cập dữ liệu theo vai trò.</p>
            </div>
        </div>

        <div class="flex gap-2 border-b pb-2">
            <button v-for="tab in [{key:'config',label:'Cấu hình'},{key:'audit',label:'Audit Trail'}]" :key="tab.key"
                @click="activeTab = tab.key as any"
                :class="['px-4 py-2 rounded-lg text-sm font-semibold', activeTab === tab.key ? 'bg-indigo-600 text-white' : 'hover:bg-muted']"
            >{{ tab.label }}</button>
        </div>

        <!-- Config -->
        <form v-if="activeTab === 'config'" @submit.prevent="submit" class="space-y-6">
            <!-- Discount limits -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-2"><Lock class="size-4" /> Giới hạn giảm giá</CardTitle>
                    <CardDescription>Staff/Manager chỉ được giảm tối đa X%. Vượt ngưỡng → cần phê duyệt Owner.</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Staff tối đa (%)</Label>
                        <Input type="number" step="0.1" v-model="form.max_discount_percent_staff" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Manager tối đa (%)</Label>
                        <Input type="number" step="0.1" v-model="form.max_discount_percent_manager" />
                    </div>
                </CardContent>
            </Card>

            <!-- Cancel limits -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-2"><Lock class="size-4" /> Giới hạn hủy đơn</CardTitle>
                    <CardDescription>Đơn hàng vượt giá trị X → chỉ Manager+ được hủy. 0 = không cho phép.</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Staff tối đa (VND)</Label>
                        <Input type="number" v-model="form.max_cancel_amount_staff" />
                        <span class="text-xs text-muted-foreground">0 = không cho phép hủy</span>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Manager tối đa (VND)</Label>
                        <Input type="number" v-model="form.max_cancel_amount_manager" />
                    </div>
                </CardContent>
            </Card>

            <!-- Data access -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-2"><Shield class="size-4" /> Giới hạn truy cập dữ liệu</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="form.staff_view_revenue" class="rounded" />
                        <span class="text-sm">Staff xem được doanh thu</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="form.staff_view_salary" class="rounded" />
                        <span class="text-sm">Staff xem được lương (của mình)</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="form.staff_view_cost_price" class="rounded" />
                        <span class="text-sm">Staff xem được giá vốn nguyên liệu</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="form.manager_view_other_salary" class="rounded" />
                        <span class="text-sm">Manager xem được lương nhân viên khác</span>
                    </label>
                </CardContent>
            </Card>

            <!-- Time & Audit -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-2"><FileSearch2 class="size-4" /> Thời gian & Audit</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="form.restrict_to_shift_hours" class="rounded" />
                        <span class="text-sm">Chỉ cho phép thao tác trong giờ ca làm việc (staff/manager)</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="form.audit_all_changes" class="rounded" />
                        <span class="text-sm">Ghi log tất cả thay đổi quan trọng (audit trail)</span>
                    </label>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing" class="gap-2 px-6">
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    {{ form.processing ? 'Đang lưu...' : 'Lưu chính sách' }}
                </Button>
            </div>
        </form>

        <!-- Audit Trail -->
        <Card v-if="activeTab === 'audit'">
            <CardHeader>
                <CardTitle class="text-base flex items-center gap-2"><FileSearch2 class="size-4" /> Audit Trail — Nhật ký thao tác nhạy cảm</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="px-4 py-3">Thời gian</th>
                            <th class="px-4 py-3">Người thực hiện</th>
                            <th class="px-4 py-3">Vai trò</th>
                            <th class="px-4 py-3">Hành động</th>
                            <th class="px-4 py-3">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in recentAudit" :key="log.id" class="border-b last:border-0 text-xs">
                            <td class="px-4 py-2.5">{{ log.created_at }}</td>
                            <td class="px-4 py-2.5 font-medium">{{ log.user_name }}</td>
                            <td class="px-4 py-2.5"><Badge variant="secondary" class="text-xs">{{ log.user_role }}</Badge></td>
                            <td class="px-4 py-2.5">{{ actionLabel[log.action] ?? log.action }}</td>
                            <td class="px-4 py-2.5 text-muted-foreground font-mono">{{ log.ip_address }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!recentAudit.length" class="text-center text-muted-foreground py-12">Chưa có nhật ký nào.</p>
            </CardContent>
        </Card>
    </div>
</template>
