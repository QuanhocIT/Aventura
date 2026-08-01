<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

const props = defineProps<{
    pendingSwapRequests?: any[];
}>();

const isProcessing = ref(false);

const acceptSwapRequest = (swapId: number) => {
    if (isProcessing.value) {
        return;
    }

    isProcessing.value = true;
    router.post(
        `/schedules/swap/${swapId}/accept`,
        {},
        {
            onSuccess: () => {
                import('vue-sonner').then((m) =>
                    m.toast.success(
                        'Đồng ý đổi ca thành công! Đang chờ Quản lý duyệt.',
                    ),
                );
            },
            onError: (errors: any) => {
                const errorMsg =
                    errors.error || 'Có lỗi xảy ra khi đồng ý đổi ca.';
                import('vue-sonner').then((m) => m.toast.error(errorMsg));
            },
            onFinish: () => {
                isProcessing.value = false;
            },
        },
    );
};

const cancelSwapRequest = (swapId: number) => {
    if (isProcessing.value) {
        return;
    }

    isProcessing.value = true;
    router.post(
        `/schedules/swap/${swapId}/cancel`,
        {},
        {
            onSuccess: () => {
                import('vue-sonner').then((m) =>
                    m.toast.success(
                        'Đã hủy/từ chối yêu cầu đổi ca thành công!',
                    ),
                );
            },
            onError: (errors: any) => {
                const errorMsg =
                    errors.error || 'Có lỗi xảy ra khi xử lý hủy ca.';
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
    <Card class="shadow-sm">
        <CardHeader class="border-b pb-3">
            <CardTitle
                class="text-indigo-650 flex items-center gap-1.5 text-base dark:text-indigo-400"
            >
                <RefreshCw class="animate-spin-slow size-5" />
                Danh Sách Yêu Cầu Đổi Ca Trực
            </CardTitle>
            <CardDescription
                >Theo dõi tình trạng các đề xuất đổi ca làm việc của bạn hoặc
                nhận được từ đồng nghiệp.</CardDescription
            >
        </CardHeader>
        <CardContent class="p-0">
            <div
                v-if="pendingSwapRequests && pendingSwapRequests.length > 0"
                class="divide-y divide-slate-100 dark:divide-slate-800"
            >
                <div
                    v-for="swap in pendingSwapRequests"
                    :key="swap.id"
                    class="flex flex-col gap-3 p-4 text-xs md:flex-row md:items-center md:justify-between"
                >
                    <div class="space-y-1">
                        <!-- Heading description -->
                        <div
                            class="dark:text-slate-205 flex flex-wrap items-center gap-1.5 font-bold text-slate-800"
                        >
                            <span
                                v-if="swap.is_requester"
                                class="font-extrabold text-indigo-600 dark:text-indigo-400"
                                >[ĐÃ GỬI]</span
                            >
                            <span
                                v-else
                                class="font-extrabold text-emerald-600 dark:text-emerald-400"
                                >[NHẬN ĐƯỢC]</span
                            >

                            <span v-if="swap.is_requester">
                                Bạn muốn đổi ca
                                <strong
                                    class="font-mono text-indigo-600 dark:text-indigo-400"
                                    >{{ swap.requester_shift }}</strong
                                >
                                ({{ swap.requester_date }}) lấy ca
                                <strong
                                    class="font-mono text-indigo-600 dark:text-indigo-400"
                                    >{{ swap.receiver_shift }}</strong
                                >
                                của <strong>{{ swap.receiver_name }}</strong>
                            </span>
                            <span v-else>
                                <strong>{{ swap.requester_name }}</strong> đề
                                xuất đổi ca
                                <strong
                                    class="font-mono text-indigo-600 dark:text-indigo-400"
                                    >{{ swap.requester_shift }}</strong
                                >
                                ({{ swap.requester_date }}) lấy ca
                                <strong
                                    class="font-mono text-indigo-600 dark:text-indigo-400"
                                    >{{ swap.receiver_shift }}</strong
                                >
                                của bạn
                            </span>
                        </div>
                        <div class="mt-0.5 font-medium text-slate-400 italic">
                            Ghi chú: {{ swap.notes || 'Không có ghi chú' }}
                        </div>
                    </div>

                    <!-- Status & Action Buttons -->
                    <div
                        class="flex items-center gap-3 self-end font-bold md:self-center"
                    >
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-extrabold uppercase"
                            :class="[
                                swap.status === 'pending'
                                    ? 'border border-amber-200 bg-amber-50 text-amber-600 dark:bg-amber-950/20 dark:text-amber-400'
                                    : '',
                                swap.status === 'accepted'
                                    ? 'border border-blue-200 bg-blue-50 text-blue-600 dark:bg-blue-950/20 dark:text-amber-400'
                                    : '',
                                swap.status === 'approved'
                                    ? 'border border-emerald-200 bg-emerald-50 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400'
                                    : '',
                                swap.status === 'rejected'
                                    ? 'border border-rose-200 bg-rose-50 text-rose-600 dark:bg-rose-950/20 dark:text-rose-400'
                                    : '',
                                swap.status === 'cancelled'
                                    ? 'border border-slate-200 bg-slate-50 text-slate-600 dark:bg-slate-900/20 dark:text-slate-400'
                                    : '',
                            ]"
                        >
                            {{
                                swap.status === 'pending'
                                    ? 'Chờ đồng ý'
                                    : swap.status === 'accepted'
                                      ? 'Chờ duyệt'
                                      : swap.status === 'approved'
                                        ? 'Đã duyệt'
                                        : swap.status === 'rejected'
                                          ? 'Đã từ chối'
                                          : 'Đã hủy'
                            }}
                        </span>

                        <!-- Actions based on pending / accepted status -->
                        <template v-if="swap.status === 'pending'">
                            <button
                                v-if="!swap.is_requester"
                                type="button"
                                @click="acceptSwapRequest(swap.id)"
                                :disabled="isProcessing"
                                class="h-7 cursor-pointer rounded-lg bg-emerald-600 px-3 text-[10px] font-bold text-white transition-all select-none hover:bg-emerald-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ isProcessing ? 'Đang xử lý...' : 'Đồng ý' }}
                            </button>
                            <button
                                type="button"
                                @click="cancelSwapRequest(swap.id)"
                                :disabled="isProcessing"
                                class="h-7 cursor-pointer rounded-lg bg-rose-600 px-3 text-[10px] font-bold text-white transition-all select-none hover:bg-rose-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{
                                    swap.is_requester
                                        ? isProcessing
                                            ? 'Đang hủy...'
                                            : 'Hủy'
                                        : isProcessing
                                          ? 'Đang từ chối...'
                                          : 'Từ chối'
                                }}
                            </button>
                        </template>
                        <template v-else-if="swap.status === 'accepted'">
                            <button
                                type="button"
                                @click="cancelSwapRequest(swap.id)"
                                :disabled="isProcessing"
                                class="h-7 cursor-pointer rounded-lg bg-rose-600 px-3 text-[10px] font-bold text-white transition-all select-none hover:bg-rose-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ isProcessing ? 'Đang hủy...' : 'Hủy' }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            <div v-else class="py-12 text-center text-slate-400">
                <div
                    class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-900"
                >
                    <RefreshCw class="text-slate-350 size-5" />
                </div>
                <p class="text-xs font-semibold">
                    Không có yêu cầu đổi ca nào đang chờ xử lý
                </p>
            </div>
        </CardContent>
    </Card>
</template>
