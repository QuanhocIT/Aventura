<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { RefreshCw, AlertCircle, X } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    isOpen: boolean;
    selectedMyShift: any;
    swappableShifts: any[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const selectedTargetShiftId = ref<number | null>(null);
const swapNotes = ref('');
const isSubmittingSwap = ref(false);

const aiSwapSuggestions = ref<any[]>([]);
const isLoadingSuggestions = ref(false);

const loadSwapSuggestions = async () => {
    if (!props.selectedMyShift?.id) {
return;
}

    isLoadingSuggestions.value = true;
    aiSwapSuggestions.value = [];

    try {
        const response = await axios.get('/schedules/swap-suggestions', {
            params: { assignment_id: props.selectedMyShift.id }
        });

        if (response.data && response.data.success) {
            aiSwapSuggestions.value = response.data.suggestions;
        }
    } catch (error) {
        console.error('Lỗi khi tải gợi ý đổi ca:', error);
    } finally {
        isLoadingSuggestions.value = false;
    }
};

const submitSwapRequest = () => {
    if (!props.selectedMyShift || !selectedTargetShiftId.value) {
        import('vue-sonner').then(m => m.toast.error('Vui lòng chọn ca muốn đề xuất đổi.'));

        return;
    }

    isSubmittingSwap.value = true;
    router.post('/schedules/swap/request', {
        requester_assignment_id: props.selectedMyShift.id,
        receiver_assignment_id: selectedTargetShiftId.value,
        notes: swapNotes.value
    }, {
        onSuccess: () => {
            emit('success');
            import('vue-sonner').then(m => m.toast.success('Đã gửi đề xuất đổi ca tới đồng nghiệp thành công!'));
        },
        onError: (errors: any) => {
            const errorMsg = errors.error || 'Có lỗi xảy ra khi gửi yêu cầu đổi ca.';
            import('vue-sonner').then(m => m.toast.error(errorMsg));
        },
        onFinish: () => {
            isSubmittingSwap.value = false;
        }
    });
};

onMounted(() => {
    loadSwapSuggestions();
});
</script>

<template>
    <div class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 print:hidden">
        <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
            <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                <div>
                    <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                        <RefreshCw class="size-5" />
                        Đề Xuất Đổi Ca Trực
                    </CardTitle>
                    <CardDescription>Gửi yêu cầu trao đổi ca làm việc của bạn cho một đồng nghiệp trong tuần này.</CardDescription>
                </div>
                <button @click="emit('close')" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer">
                    <X class="size-4" />
                </button>
            </CardHeader>
            
            <CardContent class="pt-4 space-y-4">
                <!-- Source Shift Info -->
                <div>
                    <Label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Ca trực của bạn muốn đổi</Label>
                    <div class="p-3 bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 rounded-xl mt-1 text-xs">
                        <span class="font-bold text-indigo-700 dark:text-indigo-400">Ca {{ selectedMyShift?.shift_name }}</span> · {{ selectedMyShift?.day_vn }} ({{ selectedMyShift?.date }})
                        <div class="text-[10px] text-slate-400 mt-0.5">Khung giờ: {{ selectedMyShift?.shift_time }}</div>
                    </div>
                </div>

                <!-- AI Suggestions -->
                <div class="space-y-2">
                    <Label class="text-[10px] font-bold text-indigo-650 uppercase tracking-wider flex items-center gap-1">
                        <span>⚡</span> Gợi ý AI — Đồng nghiệp phù hợp nhất
                    </Label>
                    <div v-if="isLoadingSuggestions" class="py-4 text-center text-xs text-slate-400 flex items-center justify-center gap-1.5">
                        <RefreshCw class="size-3.5 animate-spin text-indigo-600" />
                        Đang tìm kiếm đồng nghiệp tối ưu...
                    </div>
                    <div v-else-if="aiSwapSuggestions.length > 0" class="space-y-2 max-h-[160px] overflow-y-auto pr-1">
                        <div 
                            v-for="suggestion in aiSwapSuggestions" 
                            :key="suggestion.id"
                            @click="selectedTargetShiftId = suggestion.id"
                            :class="[
                                'p-2.5 rounded-xl border text-[11px] cursor-pointer transition-all duration-150 flex flex-col gap-1 hover:border-indigo-400',
                                selectedTargetShiftId === suggestion.id
                                    ? 'bg-indigo-50 border-indigo-300 dark:bg-indigo-950/20 dark:border-indigo-900/60'
                                    : 'bg-white border-slate-200 dark:bg-slate-900 dark:border-slate-800'
                            ]"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-850 dark:text-slate-200">{{ suggestion.employee_name }}</span>
                                <span 
                                    class="px-1.5 py-0.5 rounded text-[9px] font-bold"
                                    :class="[
                                        suggestion.score >= 70 ? 'bg-emerald-50 text-emerald-650 border border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-400 font-bold' : 'bg-amber-50 text-amber-650 border border-amber-100 dark:bg-amber-950/20 dark:text-amber-400 font-bold'
                                    ]"
                                >
                                    Tương thích: {{ suggestion.score }}%
                                </span>
                            </div>
                            <div class="text-[10px] text-slate-500">
                                Ca {{ suggestion.shift_name }} ({{ suggestion.shift_time }}) · {{ suggestion.day }} ({{ suggestion.date }})
                            </div>
                            <div class="flex flex-wrap gap-1 mt-1">
                                <span 
                                    v-for="(r, idx) in suggestion.reasons" 
                                    :key="idx"
                                    class="px-1 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-500 rounded-[4px] text-[8px] font-medium"
                                >
                                    {{ r }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-2 text-center text-xs text-slate-400 italic">
                        Không tìm thấy gợi ý đổi ca phù hợp tuần này.
                    </div>
                </div>

                <!-- Target Shift Selection -->
                <div class="grid gap-2">
                    <Label for="target-shift-select" class="text-xs font-bold text-slate-500 uppercase tracking-wide">Hoặc chọn thủ công ca của đồng nghiệp</Label>
                    <select 
                        id="target-shift-select"
                        v-model="selectedTargetShiftId"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-xs ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option :value="null" disabled>-- Chọn ca trực của đồng nghiệp --</option>
                        <option 
                            v-for="ts in swappableShifts" 
                            :key="ts.id" 
                            :value="ts.id"
                        >
                            {{ ts.employee_name }} · Ca {{ ts.shift_name }} ({{ ts.shift_time }}) - {{ ts.day }} ({{ ts.date }})
                        </option>
                    </select>
                    <p v-if="swappableShifts.length === 0" class="text-[10px] text-rose-500 font-semibold italic">Không có ca trực nào khác của đồng nghiệp trong tuần này để đổi.</p>
                </div>

                <!-- Swap Notes -->
                <div class="grid gap-1.5">
                    <Label for="swap-reason" class="text-xs font-bold text-slate-500 uppercase tracking-wide">Lý do xin đổi ca (Ghi chú)</Label>
                    <Input 
                        id="swap-reason" 
                        type="text" 
                        v-model="swapNotes" 
                        placeholder="Ví dụ: Bận việc gia đình đột xuất, nhờ đổi hộ..."
                        required 
                        class="h-10 text-xs" 
                    />
                </div>

                <div class="p-3 bg-amber-50/50 dark:bg-amber-950/20 rounded-xl flex items-start gap-2 text-[10px] text-amber-700 dark:text-amber-400 border border-amber-100/50">
                    <AlertCircle class="size-4 shrink-0 text-amber-600 mt-0.5" />
                    <p><strong>Quy trình phê duyệt:</strong> Sau khi gửi, đồng nghiệp nhận được yêu cầu phải nhấn "Đồng ý", sau đó Quản lý/Owner duyệt thì ca đổi mới chính thức có hiệu lực.</p>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-2 pt-2 border-t">
                    <Button type="button" variant="outline" size="sm" @click="emit('close')">Hủy</Button>
                    <Button 
                        type="button" 
                        size="sm" 
                        @click="submitSwapRequest" 
                        class="bg-indigo-650 hover:bg-indigo-755 text-white font-bold"
                        :disabled="isSubmittingSwap || !selectedTargetShiftId"
                    >
                        {{ isSubmittingSwap ? 'Đang gửi...' : 'Gửi Yêu Cầu' }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
