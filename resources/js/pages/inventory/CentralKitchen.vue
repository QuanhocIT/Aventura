<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Boxes,
    CheckCircle2,
    ClipboardList,
    Factory,
    Plus,
    RefreshCw,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    boms: Array<any>;
    workOrders: Array<any>;
    ingredients: Array<any>;
    canManageWarehouse: boolean;
}>();

const isProcessing = ref(false);
const form = ref({
    output_ingredient_id: props.ingredients[0]?.id || '',
    target_quantity: 10,
    central_bom_id: '',
    production_date: '',
    expiry_date: '',
    notes: '',
});

const activeOrders = computed(
    () =>
        props.workOrders.filter((order) => order.status !== 'completed').length,
);
const completedOrders = computed(
    () =>
        props.workOrders.filter((order) => order.status === 'completed').length,
);

const formatDate = (value: string | null | undefined) =>
    value ? new Date(value).toLocaleString('vi-VN') : '-';

const statusLabel = (status: string) => {
    if (status === 'completed') {
return 'Hoàn tất';
}

    if (status === 'in_progress') {
return 'Đang sơ chế';
}

    return 'Chờ thực hiện';
};

const createWorkOrder = async () => {
    isProcessing.value = true;

    try {
        await axios.post('/api/central-kitchen/work-orders', {
            ...form.value,
            output_ingredient_id: Number(form.value.output_ingredient_id),
            central_bom_id: form.value.central_bom_id
                ? Number(form.value.central_bom_id)
                : null,
        });
        toast.success('Đã tạo lệnh sơ chế thành công.');
        router.reload();
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Không thể tạo lệnh sơ chế.',
        );
    } finally {
        isProcessing.value = false;
    }
};

const executeWorkOrder = async (order: any) => {
    const value = prompt(
        `Nhập sản lượng thực tế của ${order.output_ingredient?.name || 'thành phẩm'}:`,
        order.target_quantity,
    );

    if (!value) {
return;
}

    const quantity = Number(value);

    if (!Number.isFinite(quantity) || quantity <= 0) {
        toast.error('Sản lượng thực tế không hợp lệ.');

        return;
    }

    isProcessing.value = true;

    try {
        await axios.post(
            `/api/central-kitchen/work-orders/${order.id}/execute`,
            {
                actual_yield_quantity: quantity,
            },
        );
        toast.success('Đã hoàn tất sơ chế và nhập lô mới vào kho.');
        router.reload();
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Không thể hoàn tất lệnh sơ chế.',
        );
    } finally {
        isProcessing.value = false;
    }
};
</script>

<template>
    <Head title="Central Kitchen Sơ chế" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <div
            class="flex flex-col justify-between gap-4 rounded-2xl bg-gradient-to-r from-amber-950 via-orange-950 to-slate-950 p-6 text-white shadow-xl md:flex-row md:items-center"
        >
            <div>
                <p
                    class="flex items-center gap-2 text-xs font-bold tracking-[0.18em] text-amber-300 uppercase"
                >
                    <Factory class="h-4 w-4" /> Sản xuất trung tâm
                </p>
                <h1 class="mt-2 text-2xl font-bold">Central Kitchen Sơ chế</h1>
                <p class="mt-1 text-sm text-amber-100/75">
                    Lập lệnh sơ chế, theo dõi sản lượng và nhập lô thành phẩm
                    vào Kho Tổng.
                </p>
            </div>
            <div
                class="rounded-xl border border-white/15 bg-white/10 px-5 py-3 text-sm backdrop-blur"
            >
                <div class="text-amber-200/70">Lệnh đang mở</div>
                <div class="mt-1 text-2xl font-bold">{{ activeOrders }}</div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[360px_1fr]">
            <Card v-if="canManageWarehouse" class="border-amber-500/20">
                <CardHeader
                    class="border-b border-amber-500/10 bg-amber-950/10"
                >
                    <CardTitle class="flex items-center gap-2 text-base"
                        ><Plus class="h-4 w-4 text-amber-400" /> Tạo lệnh sơ
                        chế</CardTitle
                    >
                    <CardDescription class="text-xs"
                        >Lệnh sau khi hoàn tất sẽ tự tạo lô thành phẩm trong Kho
                        Tổng.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4 p-4 text-xs">
                    <div>
                        <label
                            class="mb-1 block font-semibold text-muted-foreground"
                            >Thành phẩm</label
                        >
                        <select
                            v-model="form.output_ingredient_id"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-foreground"
                        >
                            <option
                                v-for="ingredient in ingredients"
                                :key="ingredient.id"
                                :value="ingredient.id"
                            >
                                {{ ingredient.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-semibold text-muted-foreground"
                            >Định mức BOM</label
                        >
                        <select
                            v-model="form.central_bom_id"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-foreground"
                        >
                            <option value="">Không chọn BOM</option>
                            <option
                                v-for="bom in boms"
                                :key="bom.id"
                                :value="bom.id"
                            >
                                {{ bom.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-semibold text-muted-foreground"
                            >Sản lượng mục tiêu</label
                        >
                        <Input
                            v-model.number="form.target_quantity"
                            type="number"
                            min="0.1"
                            step="0.1"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="mb-1 block font-semibold text-muted-foreground"
                                >Ngày sản xuất</label
                            ><Input
                                v-model="form.production_date"
                                type="date"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1 block font-semibold text-muted-foreground"
                                >Hạn dùng</label
                            ><Input v-model="form.expiry_date" type="date" />
                        </div>
                    </div>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        placeholder="Ghi chú cho ca sơ chế..."
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-xs text-foreground outline-none focus:border-amber-500"
                    ></textarea>
                    <Button
                        :disabled="isProcessing || !ingredients.length"
                        class="w-full gap-2 bg-amber-600 text-white hover:bg-amber-700"
                        @click="createWorkOrder"
                    >
                        <ClipboardList class="h-4 w-4" /> Tạo lệnh sơ chế
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="border-b bg-muted/20">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <CardTitle class="flex items-center gap-2 text-base"
                                ><Boxes class="h-4 w-4 text-amber-400" /> Danh
                                sách lệnh sơ chế</CardTitle
                            ><CardDescription class="text-xs"
                                >{{ completedOrders }} lệnh đã hoàn
                                tất</CardDescription
                            >
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            class="gap-1.5 text-xs"
                            @click="router.reload()"
                            ><RefreshCw class="h-3.5 w-3.5" /> Làm mới</Button
                        >
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-left text-xs">
                            <thead
                                class="border-b bg-muted/50 font-semibold text-muted-foreground"
                            >
                                <tr>
                                    <th class="p-3 pl-4">Mã lệnh</th>
                                    <th class="p-3">Thành phẩm</th>
                                    <th class="p-3 text-right">Mục tiêu</th>
                                    <th class="p-3 text-right">Thực thu</th>
                                    <th class="p-3">Trạng thái</th>
                                    <th class="p-3">Ngày tạo</th>
                                    <th class="p-3 pr-4 text-right">
                                        Thao tác
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="!workOrders.length">
                                    <td
                                        colspan="7"
                                        class="p-8 text-center text-muted-foreground"
                                    >
                                        Chưa có lệnh sơ chế.
                                    </td>
                                </tr>
                                <tr
                                    v-for="order in workOrders"
                                    :key="order.id"
                                    class="hover:bg-muted/20"
                                >
                                    <td
                                        class="p-3 pl-4 font-mono font-bold text-amber-300"
                                    >
                                        {{ order.work_order_code }}
                                    </td>
                                    <td
                                        class="p-3 font-semibold text-foreground"
                                    >
                                        {{
                                            order.output_ingredient?.name || '-'
                                        }}
                                    </td>
                                    <td class="p-3 text-right">
                                        {{ order.target_quantity }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-bold text-emerald-400"
                                    >
                                        {{ order.actual_yield_quantity || '-' }}
                                    </td>
                                    <td class="p-3">
                                        <span
                                            :class="
                                                order.status === 'completed'
                                                    ? 'bg-emerald-500/10 text-emerald-400'
                                                    : 'bg-amber-500/10 text-amber-300'
                                            "
                                            class="rounded-full px-2 py-1 text-[10px] font-semibold"
                                            >{{
                                                statusLabel(order.status)
                                            }}</span
                                        >
                                    </td>
                                    <td class="p-3 text-muted-foreground">
                                        {{ formatDate(order.created_at) }}
                                    </td>
                                    <td class="p-3 pr-4 text-right">
                                        <Button
                                            v-if="
                                                canManageWarehouse &&
                                                order.status !== 'completed'
                                            "
                                            size="sm"
                                            class="h-7 bg-amber-600 text-[10px] text-white hover:bg-amber-700"
                                            @click="executeWorkOrder(order)"
                                            >Hoàn tất</Button
                                        ><span
                                            v-else
                                            class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-400"
                                            ><CheckCircle2
                                                class="h-3.5 w-3.5"
                                            />
                                            Đã nhập kho</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
