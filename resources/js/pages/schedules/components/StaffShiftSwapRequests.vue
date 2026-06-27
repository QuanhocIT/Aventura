<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    pendingSwapRequests?: any[];
}>();

const acceptSwapRequest = (swapId: number) => {
    router.post(`/schedules/swap/${swapId}/accept`, {}, {
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Đồng ý đổi ca thành công! Đang chờ Quản lý duyệt.'));
        },
        onError: (errors: any) => {
            const errorMsg = errors.error || 'Có lỗi xảy ra khi đồng ý đổi ca.';
            import('vue-sonner').then(m => m.toast.error(errorMsg));
        }
    });
};

const cancelSwapRequest = (swapId: number) => {
    router.post(`/schedules/swap/${swapId}/cancel`, {}, {
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Đã hủy/từ chối yêu cầu đổi ca thành công!'));
        },
        onError: (errors: any) => {
            const errorMsg = errors.error || 'Có lỗi xảy ra khi xử lý hủy ca.';
            import('vue-sonner').then(m => m.toast.error(errorMsg));
        }
    });
};
</script>

<template>
    <Card class="shadow-sm">
        <CardHeader class="pb-3 border-b">
            <CardTitle class="text-base flex items-center gap-1.5 text-indigo-650 dark:text-indigo-400">
                <RefreshCw class="size-5 animate-spin-slow" />
                Danh Sách Yêu Cầu Đổi Ca Trực
            </CardTitle>
            <CardDescription>Theo dõi tình trạng các đề xuất đổi ca làm việc của bạn hoặc nhận được từ đồng nghiệp.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
            <div v-if="pendingSwapRequests && pendingSwapRequests.length > 0" class="divide-y divide-slate-100 dark:divide-slate-800">
                <div 
                    v-for="swap in pendingSwapRequests" 
                    :key="swap.id" 
                    class="p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3 text-xs"
                >
                    <div class="space-y-1">
                        <!-- Heading description -->
                        <div class="font-bold flex flex-wrap items-center gap-1.5 text-slate-800 dark:text-slate-205">
                            <span v-if="swap.is_requester" class="text-indigo-600 dark:text-indigo-400 font-extrabold">[ĐÃ GỬI]</span>
                            <span v-else class="text-emerald-600 dark:text-emerald-400 font-extrabold">[NHẬN ĐƯỢC]</span>
                            
                            <span v-if="swap.is_requester">
                                Bạn muốn đổi ca <strong class="font-mono text-indigo-600 dark:text-indigo-400">{{ swap.requester_shift }}</strong> ({{ swap.requester_date }}) 
                                lấy ca <strong class="font-mono text-indigo-600 dark:text-indigo-400">{{ swap.receiver_shift }}</strong> của <strong>{{ swap.receiver_name }}</strong>
                            </span>
                            <span v-else>
                                <strong>{{ swap.requester_name }}</strong> đề xuất đổi ca <strong class="font-mono text-indigo-600 dark:text-indigo-400">{{ swap.requester_shift }}</strong> ({{ swap.requester_date }})
                                lấy ca <strong class="font-mono text-indigo-600 dark:text-indigo-400">{{ swap.receiver_shift }}</strong> của bạn
                            </span>
                        </div>
                        <div class="text-slate-400 font-medium italic mt-0.5">Ghi chú: {{ swap.notes || 'Không có ghi chú' }}</div>
                    </div>

                    <!-- Status & Action Buttons -->
                    <div class="flex items-center gap-3 self-end md:self-center font-bold">
                        <span 
                            class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase shrink-0" 
                            :class="[
                                swap.status === 'pending' ? 'bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-950/20 dark:text-amber-400' : '',
                                swap.status === 'accepted' ? 'bg-blue-50 text-blue-600 border border-blue-200 dark:bg-blue-950/20 dark:text-amber-400' : '',
                                swap.status === 'approved' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400' : '',
                                swap.status === 'rejected' ? 'bg-rose-50 text-rose-600 border border-rose-200 dark:bg-rose-950/20 dark:text-rose-400' : '',
                                swap.status === 'cancelled' ? 'bg-slate-50 text-slate-600 border border-slate-200 dark:bg-slate-900/20 dark:text-slate-400' : ''
                            ]"
                        >
                            {{ 
                                swap.status === 'pending' ? 'Chờ đồng ý' : 
                                (swap.status === 'accepted' ? 'Chờ duyệt' : 
                                (swap.status === 'approved' ? 'Đã duyệt' : 
                                (swap.status === 'rejected' ? 'Đã từ chối' : 'Đã hủy'))) 
                            }}
                        </span>

                        <!-- Actions based on pending / accepted status -->
                        <template v-if="swap.status === 'pending'">
                            <button 
                                v-if="!swap.is_requester"
                                type="button"
                                @click="acceptSwapRequest(swap.id)"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold h-7 px-3 text-[10px] rounded-lg cursor-pointer select-none active:scale-95 transition-all"
                            >
                                Đồng ý
                            </button>
                            <button 
                                type="button"
                                @click="cancelSwapRequest(swap.id)"
                                class="bg-rose-600 hover:bg-rose-700 text-white font-bold h-7 px-3 text-[10px] rounded-lg cursor-pointer select-none active:scale-95 transition-all"
                            >
                                {{ swap.is_requester ? 'Hủy' : 'Từ chối' }}
                            </button>
                        </template>
                        <template v-else-if="swap.status === 'accepted'">
                            <button 
                                type="button"
                                @click="cancelSwapRequest(swap.id)"
                                class="bg-rose-600 hover:bg-rose-700 text-white font-bold h-7 px-3 text-[10px] rounded-lg cursor-pointer select-none active:scale-95 transition-all"
                            >
                                Hủy
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            <div v-else class="py-12 text-center text-slate-400">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-900 mx-auto mb-2">
                    <RefreshCw class="size-5 text-slate-350" />
                </div>
                <p class="text-xs font-semibold">Không có yêu cầu đổi ca nào đang chờ xử lý</p>
            </div>
        </CardContent>
    </Card>
</template>
