<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    Ban,
    CheckCircle2,
    History,
    ShieldAlert,
} from 'lucide-vue-next';
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

const props = defineProps<{
    recallOrders: Array<any>;
    activeBatches: Array<any>;
    canManageWarehouse: boolean;
}>();
const isProcessing = ref(false);
const form = ref({
    batch_id: '',
    severity: 'critical',
    reason: '',
    action_taken: '',
});

const severityLabel = (severity: string) =>
    severity === 'critical'
        ? 'Khẩn cấp'
        : severity === 'high'
          ? 'Cao'
          : 'Trung bình';
const statusLabel = (status: string) =>
    status === 'completed'
        ? 'Đã xử lý'
        : status === 'in_progress'
          ? 'Đang xử lý'
          : 'Đang khóa lô';

const submitRecall = async () => {
    if (!form.value.batch_id || !form.value.reason.trim()) {
        toast.error('Vui lòng chọn lô hàng và nhập lý do thu hồi.');

        return;
    }

    isProcessing.value = true;

    try {
        await axios.post('/api/batch-recalls/initiate', {
            ...form.value,
            batch_id: Number(form.value.batch_id),
        });
        toast.success('Đã khóa và phát lệnh thu hồi lô toàn hệ thống.');
        router.reload();
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Không thể phát lệnh thu hồi.',
        );
    } finally {
        isProcessing.value = false;
    }
};

const completeRecall = async (recall: any) => {
    const notes = prompt(
        'Nhập ghi chú xử lý hoàn tất:',
        recall.resolution_notes || '',
    );

    if (notes === null) {
        return;
    }

    isProcessing.value = true;

    try {
        await axios.post(`/api/batch-recalls/${recall.id}/complete`, {
            resolution_notes: notes,
        });
        toast.success('Đã hoàn tất xử lý lệnh thu hồi.');
        router.reload();
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Không thể hoàn tất lệnh thu hồi.',
        );
    } finally {
        isProcessing.value = false;
    }
};
</script>

<template>
    <Head title="Thu hồi Lô Khẩn cấp" />
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <div
            class="flex flex-col justify-between gap-4 rounded-2xl border border-rose-100/90 bg-gradient-to-r from-rose-50/90 via-slate-50 to-red-50/60 p-4 text-slate-900 shadow-xs backdrop-blur-md sm:p-5 md:flex-row md:items-center dark:border-slate-800 dark:bg-black/80 dark:from-[#100606] dark:via-black dark:to-[#100606] dark:text-white"
        >
            <div class="flex items-center gap-3.5">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-rose-600 text-white shadow-sm shadow-rose-600/20 backdrop-blur-md dark:border dark:border-rose-500/30 dark:bg-rose-600/25 dark:text-rose-300"
                >
                    <ShieldAlert class="size-5" />
                </div>
                <div>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-100/80 px-2.5 py-0.5 text-[9px] font-extrabold tracking-widest text-rose-700 uppercase dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300"
                    >
                        An toàn nguyên vật liệu
                    </span>
                    <h1
                        class="mt-1 text-lg font-black tracking-tight text-slate-900 md:text-xl lg:text-2xl dark:text-white"
                    >
                        Thu hồi Lô Khẩn cấp
                    </h1>
                    <p
                        class="mt-0.5 text-xs leading-normal text-slate-600 dark:text-slate-400"
                    >
                        Khóa lô lỗi, truy vết ảnh hưởng và ghi nhận xử lý trên
                        toàn chuỗi nhà hàng.
                    </p>
                </div>
            </div>
            <div
                class="shrink-0 rounded-xl border border-slate-200/80 bg-white/90 px-3.5 py-1.5 text-xs shadow-2xs backdrop-blur-sm dark:border-white/10 dark:bg-black/50"
            >
                <div
                    class="text-[9px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                >
                    Lệnh thu hồi
                </div>
                <div
                    class="text-base font-extrabold text-slate-900 dark:text-white"
                >
                    {{ recallOrders.length }}
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[380px_1fr]">
            <Card v-if="canManageWarehouse" class="border-rose-500/20"
                ><CardHeader class="border-b border-rose-500/10 bg-rose-950/10"
                    ><CardTitle
                        class="flex items-center gap-2 text-base text-rose-200"
                        ><Ban class="h-4 w-4 text-rose-400" /> Phát lệnh thu
                        hồi</CardTitle
                    ><CardDescription class="text-xs"
                        >Lô được khóa ngay sau khi phát lệnh và không thể tiếp
                        tục xuất kho.</CardDescription
                    ></CardHeader
                ><CardContent class="space-y-4 p-4 text-xs"
                    ><div>
                        <label
                            class="mb-1 block font-semibold text-muted-foreground"
                            >Lô cần thu hồi</label
                        ><select
                            v-model="form.batch_id"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-foreground"
                        >
                            <option value="">Chọn mã lô</option>
                            <option
                                v-for="batch in activeBatches"
                                :key="batch.id"
                                :value="batch.id"
                            >
                                {{
                                    batch.batch_code ||
                                    batch.batch_number ||
                                    `Lô #${batch.id}`
                                }}
                                · {{ batch.ingredient?.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block font-semibold text-muted-foreground"
                            >Mức độ</label
                        ><select
                            v-model="form.severity"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-foreground"
                        >
                            <option value="critical">Khẩn cấp</option>
                            <option value="high">Cao</option>
                            <option value="medium">Trung bình</option>
                        </select>
                    </div>
                    <textarea
                        v-model="form.reason"
                        rows="4"
                        placeholder="Lý do: lỗi kiểm nghiệm, hết hạn, nghi ngờ chất lượng..."
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-xs text-foreground outline-none focus:border-rose-500"
                    ></textarea
                    ><textarea
                        v-model="form.action_taken"
                        rows="2"
                        placeholder="Biện pháp xử lý dự kiến..."
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-xs text-foreground outline-none focus:border-rose-500"
                    ></textarea
                    ><Button
                        :disabled="isProcessing"
                        class="w-full gap-2 bg-rose-600 font-bold text-white hover:bg-rose-700"
                        @click="submitRecall"
                        ><AlertTriangle class="h-4 w-4" /> KHÓA & PHÁT LỆNH THU
                        HỒI</Button
                    ></CardContent
                ></Card
            >

            <Card
                ><CardHeader class="border-b bg-muted/20"
                    ><CardTitle class="flex items-center gap-2 text-base"
                        ><History class="h-4 w-4 text-rose-400" /> Lịch sử thu
                        hồi</CardTitle
                    ><CardDescription class="text-xs"
                        >Theo dõi các lô đã khóa và tiến độ xử
                        lý.</CardDescription
                    ></CardHeader
                ><CardContent class="p-0"
                    ><div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left text-xs">
                            <thead
                                class="border-b bg-muted/50 font-semibold text-muted-foreground"
                            >
                                <tr>
                                    <th class="p-3 pl-4">Mã thu hồi</th>
                                    <th class="p-3">Nguyên liệu / Lô</th>
                                    <th class="p-3">Mức độ</th>
                                    <th class="p-3">Lý do</th>
                                    <th class="p-3">Trạng thái</th>
                                    <th class="p-3 pr-4 text-right">
                                        Thao tác
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="!recallOrders.length">
                                    <td
                                        colspan="6"
                                        class="p-8 text-center text-muted-foreground"
                                    >
                                        Chưa có lệnh thu hồi.
                                    </td>
                                </tr>
                                <tr
                                    v-for="recall in recallOrders"
                                    :key="recall.id"
                                >
                                    <td
                                        class="p-3 pl-4 font-mono font-bold text-rose-300"
                                    >
                                        {{ recall.recall_code }}
                                    </td>
                                    <td class="p-3">
                                        <strong>{{
                                            recall.batch?.ingredient?.name ||
                                            '-'
                                        }}</strong
                                        ><span
                                            class="mt-0.5 block text-[10px] text-muted-foreground"
                                            >{{
                                                recall.batch?.batch_code ||
                                                recall.batch?.batch_number ||
                                                `Lô #${recall.batch_id}`
                                            }}</span
                                        >
                                    </td>
                                    <td class="p-3">
                                        <span
                                            class="rounded-full bg-rose-500/10 px-2 py-1 text-[10px] font-semibold text-rose-300"
                                            >{{
                                                severityLabel(recall.severity)
                                            }}</span
                                        >
                                    </td>
                                    <td
                                        class="max-w-xs truncate p-3 text-muted-foreground"
                                    >
                                        {{ recall.reason }}
                                    </td>
                                    <td class="p-3">
                                        <span
                                            :class="
                                                recall.status === 'completed'
                                                    ? 'bg-emerald-500/10 text-emerald-400'
                                                    : 'bg-rose-500/10 text-rose-300'
                                            "
                                            class="rounded-full px-2 py-1 text-[10px] font-semibold"
                                            >{{
                                                statusLabel(recall.status)
                                            }}</span
                                        >
                                    </td>
                                    <td class="p-3 pr-4 text-right">
                                        <Button
                                            v-if="
                                                canManageWarehouse &&
                                                recall.status !== 'completed'
                                            "
                                            size="sm"
                                            :disabled="isProcessing"
                                            class="h-7 bg-emerald-600 text-[10px] text-white hover:bg-emerald-700"
                                            @click="completeRecall(recall)"
                                            ><CheckCircle2
                                                class="mr-1 h-3.5 w-3.5"
                                            />
                                            Hoàn tất</Button
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div></CardContent
                ></Card
            >
        </div>
    </div>
</template>
