<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { RefreshCw, Ban, X, AlertTriangle } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    allPendingSwaps?: any[];
}>();

const isRejectingSwap = ref(false);
const activeRejectSwapId = ref<number | null>(null);
const rejectNotes = ref('');

const approveSwap = (swapId: number) => {
    router.patch(`/schedules/swap/${swapId}/approve`, {}, {
        only: ['assignments', 'weeklyAssignments', 'monthlyAssignments', 'allPendingSwaps', 'stats'],
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Đã phê duyệt đổi ca thành công!'));
        },
        onError: (errors: any) => {
            const errorMsg = errors.error || 'Có lỗi xảy ra khi phê duyệt.';
            import('vue-sonner').then(m => m.toast.error(errorMsg));
        }
    });
};

const openRejectSwapModal = (swapId: number) => {
    activeRejectSwapId.value = swapId;
    rejectNotes.value = '';
    isRejectingSwap.value = true;
};

const submitRejectSwap = () => {
    if (!activeRejectSwapId.value) {
return;
}

    router.patch(`/schedules/swap/${activeRejectSwapId.value}/reject`, {
        notes: rejectNotes.value
    }, {
        only: ['assignments', 'weeklyAssignments', 'monthlyAssignments', 'allPendingSwaps', 'stats'],
        onSuccess: () => {
            isRejectingSwap.value = false;
            import('vue-sonner').then(m => m.toast.success('Đã từ chối đổi ca thành công!'));
        },
        onError: (errors: any) => {
            const errorMsg = errors.error || 'Có lỗi xảy ra khi từ chối.';
            import('vue-sonner').then(m => m.toast.error(errorMsg));
        }
    });
};
</script>

<template>
    <div>
        <Card class="shadow-sm">
            <CardHeader class="pb-3 border-b">
                <CardTitle class="text-base flex items-center gap-1.5 text-indigo-650">
                    <RefreshCw class="size-5 text-indigo-655" />
                    Phê Duyệt Đổi Ca Trực
                </CardTitle>
                <CardDescription>Xem xét và phê duyệt các yêu cầu trao đổi ca trực đã được cả hai nhân sự thống nhất.</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="allPendingSwaps && allPendingSwaps.length > 0" class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 dark:bg-slate-950 border-b text-[10px] uppercase font-bold tracking-wider text-slate-500">
                                <th class="p-3.5">Nhân viên yêu cầu</th>
                                <th class="p-3.5">Ca của người yêu cầu</th>
                                <th class="p-3.5">Nhân viên nhận ca</th>
                                <th class="p-3.5">Ca của người nhận</th>
                                <th class="p-3.5">Ghi chú đổi</th>
                                <th class="p-3.5 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="sw in allPendingSwaps" :key="sw.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                <td class="p-3.5 font-bold text-slate-800 dark:text-slate-200">
                                    {{ sw.requester_name }}
                                </td>
                                <td class="p-3.5">
                                    <span class="font-semibold text-indigo-700 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/30 px-2 py-0.5 rounded font-mono">{{ sw.requester_shift }}</span>
                                    <div class="text-[10px] text-slate-400 mt-0.5 font-mono">{{ sw.requester_date }}</div>
                                </td>
                                <td class="p-3.5 font-bold text-slate-800 dark:text-slate-200">
                                    {{ sw.receiver_name }}
                                </td>
                                <td class="p-3.5">
                                    <span class="font-semibold text-indigo-700 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/30 px-2 py-0.5 rounded font-mono">{{ sw.receiver_shift }}</span>
                                    <div class="text-[10px] text-slate-400 mt-0.5 font-mono">{{ sw.receiver_date }}</div>
                                </td>
                                <td class="p-3.5 text-slate-500 font-medium">
                                    <div v-if="sw.notes && sw.notes.includes('[⚠️ Vi phạm nghỉ 11h]')" class="text-rose-600 dark:text-rose-400 font-bold flex items-center gap-1 mb-1.5 animate-pulse">
                                        <AlertTriangle class="w-3.5 h-3.5" /> Vi phạm nghỉ 11h!
                                    </div>
                                    {{ sw.notes || '—' }}
                                </td>
                                <td class="p-3.5 text-right flex items-center justify-end gap-1.5">
                                    <button 
                                        @click="approveSwap(sw.id)"
                                        class="inline-flex cursor-pointer items-center justify-center rounded px-2.5 py-1 text-[10px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition active:scale-95 shadow-xs"
                                        title="Duyệt yêu cầu đổi ca"
                                    >
                                        Phê duyệt
                                    </button>
                                    <button 
                                        @click="openRejectSwapModal(sw.id)"
                                        class="inline-flex cursor-pointer items-center justify-center rounded px-2.5 py-1 text-[10px] font-bold bg-rose-50 text-rose-600 hover:bg-rose-100 border border-rose-200 transition active:scale-95"
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
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-900 mx-auto mb-3 text-slate-400">
                        <RefreshCw class="size-6 text-slate-350" />
                    </div>
                    <p class="text-xs font-semibold">Không có yêu cầu đổi ca nào đang chờ duyệt</p>
                    <p class="mt-1 text-[11px] text-slate-400">Yêu cầu đổi ca chỉ hiển thị ở đây sau khi cả hai nhân viên đã xác nhận đồng ý.</p>
                </div>
            </CardContent>
        </Card>

        <!-- Reject Swap Modal -->
        <div v-if="isRejectingSwap" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 print:hidden">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                    <div>
                        <CardTitle class="text-base flex items-center gap-1.5 text-rose-600">
                            <Ban class="size-5" />
                            Từ Chối Yêu Cầu Đổi Ca
                        </CardTitle>
                        <CardDescription>Vui lòng nhập lý do từ chối yêu cầu đổi ca để thông báo cho nhân viên.</CardDescription>
                    </div>
                    <button @click="isRejectingSwap = false" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer">
                        <X class="size-4" />
                    </button>
                </CardHeader>
                
                <CardContent class="pt-4 space-y-4">
                    <div class="grid gap-1.5">
                        <Label for="reject-reason" class="text-xs font-bold text-slate-500 uppercase tracking-wide">Lý do từ chối</Label>
                        <Input 
                            id="reject-reason" 
                            type="text" 
                            v-model="rejectNotes" 
                            placeholder="Ví dụ: Không cân bằng được vai trò nhân sự trong ca..."
                            required 
                            class="h-10 text-xs border-rose-200 focus-visible:ring-rose-500" 
                        />
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-end gap-2 pt-2 border-t">
                        <Button type="button" variant="outline" size="sm" @click="isRejectingSwap = false">Hủy</Button>
                        <Button 
                            type="button" 
                            size="sm" 
                            @click="submitRejectSwap" 
                            class="bg-rose-650 hover:bg-rose-755 text-white font-bold cursor-pointer"
                            :disabled="!rejectNotes"
                        >
                            Xác nhận từ chối
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
