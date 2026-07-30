<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { RefreshCw, Ban, X, AlertTriangle } from 'lucide-vue-next';
import { ref } from 'vue';
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

const props = defineProps<{
    allPendingSwaps?: any[];
}>();

const isRejectingSwap = ref(false);
const activeRejectSwapId = ref<number | null>(null);
const rejectNotes = ref('');
const isProcessing = ref(false);

const approveSwap = (swapId: number) => {
    if (isProcessing.value) {
return;
}

    isProcessing.value = true;
    router.patch(
        `/schedules/swap/${swapId}/approve`,
        {},
        {
            onSuccess: () => {
                import('vue-sonner').then((m) =>
                    m.toast.success('Đã phê duyệt đổi ca thành công!'),
                );
            },
            onError: (errors: any) => {
                const errorMsg = errors.error || 'Có lỗi xảy ra khi phê duyệt.';
                import('vue-sonner').then((m) => m.toast.error(errorMsg));
            },
            onFinish: () => {
                isProcessing.value = false;
            },
        },
    );
};

const openRejectSwapModal = (swapId: number) => {
    activeRejectSwapId.value = swapId;
    rejectNotes.value = '';
    isRejectingSwap.value = true;
};

const submitRejectSwap = () => {
    if (!activeRejectSwapId.value || isProcessing.value) {
        return;
    }

    isProcessing.value = true;

    router.patch(
        `/schedules/swap/${activeRejectSwapId.value}/reject`,
        {
            notes: rejectNotes.value,
        },
        {
            onSuccess: () => {
                isRejectingSwap.value = false;
                import('vue-sonner').then((m) =>
                    m.toast.success('Đã từ chối đổi ca thành công!'),
                );
            },
            onError: (errors: any) => {
                const errorMsg = errors.error || 'Có lỗi xảy ra khi từ chối.';
                import('vue-sonner').then((m) => m.toast.error(errorMsg));
            },
            onFinish: () => {
                isProcessing.value = false;
            },
        },
    );
};
</script>

<template>
    <div>
        <Card class="shadow-sm">
            <CardHeader class="border-b pb-3">
                <CardTitle
                    class="text-indigo-650 flex items-center gap-1.5 text-base"
                >
                    <RefreshCw class="text-indigo-655 size-5" />
                    Phê Duyệt Đổi Ca Trực
                </CardTitle>
                <CardDescription
                    >Xem xét và phê duyệt các yêu cầu trao đổi ca trực đã được
                    cả hai nhân sự thống nhất.</CardDescription
                >
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-if="allPendingSwaps && allPendingSwaps.length > 0"
                    class="overflow-x-auto"
                >
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b bg-slate-100 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                            >
                                <th class="p-3.5">Nhân viên yêu cầu</th>
                                <th class="p-3.5">Ca của người yêu cầu</th>
                                <th class="p-3.5">Nhân viên nhận ca</th>
                                <th class="p-3.5">Ca của người nhận</th>
                                <th class="p-3.5">Ghi chú đổi</th>
                                <th class="p-3.5 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr
                                v-for="sw in allPendingSwaps"
                                :key="sw.id"
                                class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                            >
                                <td
                                    class="p-3.5 font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ sw.requester_name }}
                                </td>
                                <td class="p-3.5">
                                    <span
                                        class="rounded bg-indigo-50 px-2 py-0.5 font-mono font-semibold text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-400"
                                        >{{ sw.requester_shift }}</span
                                    >
                                    <div
                                        class="mt-0.5 font-mono text-[10px] text-slate-400"
                                    >
                                        {{ sw.requester_date }}
                                    </div>
                                </td>
                                <td
                                    class="p-3.5 font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ sw.receiver_name }}
                                </td>
                                <td class="p-3.5">
                                    <span
                                        class="rounded bg-indigo-50 px-2 py-0.5 font-mono font-semibold text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-400"
                                        >{{ sw.receiver_shift }}</span
                                    >
                                    <div
                                        class="mt-0.5 font-mono text-[10px] text-slate-400"
                                    >
                                        {{ sw.receiver_date }}
                                    </div>
                                </td>
                                <td class="p-3.5 font-medium text-slate-500">
                                    <div
                                        v-if="
                                            sw.notes &&
                                            sw.notes.includes(
                                                '[⚠️ Vi phạm nghỉ 11h]',
                                            )
                                        "
                                        class="mb-1.5 flex animate-pulse items-center gap-1 font-bold text-rose-600 dark:text-rose-400"
                                    >
                                        <AlertTriangle class="h-3.5 w-3.5" /> Vi
                                        phạm nghỉ 11h!
                                    </div>
                                    {{ sw.notes || '—' }}
                                </td>
                                <td
                                    class="flex items-center justify-end gap-1.5 p-3.5 text-right"
                                >
                                    <button
                                        @click="approveSwap(sw.id)"
                                        :disabled="isProcessing"
                                        class="inline-flex cursor-pointer items-center justify-center rounded bg-emerald-600 px-2.5 py-1 text-[10px] font-bold text-white shadow-xs transition hover:bg-emerald-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                                        title="Duyệt yêu cầu đổi ca"
                                    >
                                        {{
                                            isProcessing
                                                ? 'Đang duyệt...'
                                                : 'Phê duyệt'
                                        }}
                                    </button>
                                    <button
                                        @click="openRejectSwapModal(sw.id)"
                                        :disabled="isProcessing"
                                        class="inline-flex cursor-pointer items-center justify-center rounded border border-rose-200 bg-rose-50 px-2.5 py-1 text-[10px] font-bold text-rose-600 transition hover:bg-rose-100 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                                        title="Từ chối yêu cầu đổi ca"
                                    >
                                        Từ chối
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="py-16 text-center text-slate-400">
                    <div
                        class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 text-slate-400 dark:bg-slate-900"
                    >
                        <RefreshCw class="text-slate-350 size-6" />
                    </div>
                    <p class="text-xs font-semibold">
                        Không có yêu cầu đổi ca nào đang chờ duyệt
                    </p>
                    <p class="mt-1 text-[11px] text-slate-400">
                        Yêu cầu đổi ca chỉ hiển thị ở đây sau khi cả hai nhân
                        viên đã xác nhận đồng ý.
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Reject Swap Modal -->
        <div
            v-if="isRejectingSwap"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs print:hidden"
        >
            <Card
                class="w-full max-w-md animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-base text-rose-600"
                        >
                            <Ban class="size-5" />
                            Từ Chối Yêu Cầu Đổi Ca
                        </CardTitle>
                        <CardDescription
                            >Vui lòng nhập lý do từ chối yêu cầu đổi ca để thông
                            báo cho nhân viên.</CardDescription
                        >
                    </div>
                    <button
                        @click="isRejectingSwap = false"
                        class="cursor-pointer rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </CardHeader>

                <CardContent class="space-y-4 pt-4">
                    <div class="grid gap-1.5">
                        <Label
                            for="reject-reason"
                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                            >Lý do từ chối</Label
                        >
                        <Input
                            id="reject-reason"
                            type="text"
                            v-model="rejectNotes"
                            placeholder="Ví dụ: Không cân bằng được vai trò nhân sự trong ca..."
                            required
                            class="h-10 border-rose-200 text-xs focus-visible:ring-rose-500"
                        />
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-end gap-2 border-t pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="isRejectingSwap = false"
                            >Hủy</Button
                        >
                        <Button
                            type="button"
                            size="sm"
                            @click="submitRejectSwap"
                            class="bg-rose-650 hover:bg-rose-755 cursor-pointer font-bold text-white"
                            :disabled="!rejectNotes || isProcessing"
                        >
                            {{
                                isProcessing
                                    ? 'Đang xử lý...'
                                    : 'Xác nhận từ chối'
                            }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
