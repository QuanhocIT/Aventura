<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { RefreshCw, AlertCircle, X } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
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
            params: { assignment_id: props.selectedMyShift.id },
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
        import('vue-sonner').then((m) =>
            m.toast.error('Vui lòng chọn ca muốn đề xuất đổi.'),
        );

        return;
    }

    isSubmittingSwap.value = true;
    router.post(
        '/schedules/swap/request',
        {
            requester_assignment_id: props.selectedMyShift.id,
            receiver_assignment_id: selectedTargetShiftId.value,
            notes: swapNotes.value,
        },
        {
            onSuccess: () => {
                emit('success');
                import('vue-sonner').then((m) =>
                    m.toast.success(
                        'Đã gửi đề xuất đổi ca tới đồng nghiệp thành công!',
                    ),
                );
            },
            onError: (errors: any) => {
                const errorMsg =
                    errors.error || 'Có lỗi xảy ra khi gửi yêu cầu đổi ca.';
                import('vue-sonner').then((m) => m.toast.error(errorMsg));
            },
            onFinish: () => {
                isSubmittingSwap.value = false;
            },
        },
    );
};

onMounted(() => {
    loadSwapSuggestions();
});
</script>

<template>
    <div
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
                        class="flex items-center gap-1.5 text-base text-indigo-600"
                    >
                        <RefreshCw class="size-5" />
                        Đề Xuất Đổi Ca Trực
                    </CardTitle>
                    <CardDescription
                        >Gửi yêu cầu trao đổi ca làm việc của bạn cho một đồng
                        nghiệp trong tuần này.</CardDescription
                    >
                </div>
                <button
                    @click="emit('close')"
                    class="cursor-pointer rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    <X class="size-4" />
                </button>
            </CardHeader>

            <CardContent class="space-y-4 pt-4">
                <!-- Source Shift Info -->
                <div>
                    <Label
                        class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                        >Ca trực của bạn muốn đổi</Label
                    >
                    <div
                        class="mt-1 rounded-xl border border-indigo-100 bg-indigo-50/50 p-3 text-xs dark:bg-indigo-950/20"
                    >
                        <span
                            class="font-bold text-indigo-700 dark:text-indigo-400"
                            >Ca {{ selectedMyShift?.shift_name }}</span
                        >
                        · {{ selectedMyShift?.day_vn }} ({{
                            selectedMyShift?.date
                        }})
                        <div class="mt-0.5 text-[10px] text-slate-400">
                            Khung giờ: {{ selectedMyShift?.shift_time }}
                        </div>
                    </div>
                </div>

                <!-- AI Suggestions -->
                <div class="space-y-2">
                    <Label
                        class="text-indigo-650 flex items-center gap-1 text-[10px] font-bold tracking-wider uppercase"
                    >
                        <span>⚡</span> Gợi ý AI — Đồng nghiệp phù hợp nhất
                    </Label>
                    <div
                        v-if="isLoadingSuggestions"
                        class="flex items-center justify-center gap-1.5 py-4 text-center text-xs text-slate-400"
                    >
                        <RefreshCw
                            class="size-3.5 animate-spin text-indigo-600"
                        />
                        Đang tìm kiếm đồng nghiệp tối ưu...
                    </div>
                    <div
                        v-else-if="aiSwapSuggestions.length > 0"
                        class="max-h-[160px] space-y-2 overflow-y-auto pr-1"
                    >
                        <div
                            v-for="suggestion in aiSwapSuggestions"
                            :key="suggestion.id"
                            @click="selectedTargetShiftId = suggestion.id"
                            :class="[
                                'flex cursor-pointer flex-col gap-1 rounded-xl border p-2.5 text-[11px] transition-all duration-150 hover:border-indigo-400',
                                selectedTargetShiftId === suggestion.id
                                    ? 'border-indigo-300 bg-indigo-50 dark:border-indigo-900/60 dark:bg-indigo-950/20'
                                    : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900',
                            ]"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-slate-850 font-bold dark:text-slate-200"
                                    >{{ suggestion.employee_name }}</span
                                >
                                <span
                                    class="rounded px-1.5 py-0.5 text-[9px] font-bold"
                                    :class="[
                                        suggestion.score >= 70
                                            ? 'text-emerald-650 border border-emerald-100 bg-emerald-50 font-bold dark:bg-emerald-950/20 dark:text-emerald-400'
                                            : 'text-amber-650 border border-amber-100 bg-amber-50 font-bold dark:bg-amber-950/20 dark:text-amber-400',
                                    ]"
                                >
                                    Tương thích: {{ suggestion.score }}%
                                </span>
                            </div>
                            <div class="text-[10px] text-slate-500">
                                Ca {{ suggestion.shift_name }} ({{
                                    suggestion.shift_time
                                }}) · {{ suggestion.day }} ({{
                                    suggestion.date
                                }})
                            </div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <span
                                    v-for="(r, idx) in suggestion.reasons"
                                    :key="idx"
                                    class="rounded-[4px] bg-slate-100 px-1 py-0.5 text-[8px] font-medium text-slate-500 dark:bg-slate-800"
                                >
                                    {{ r }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="py-2 text-center text-xs text-slate-400 italic"
                    >
                        Không tìm thấy gợi ý đổi ca phù hợp tuần này.
                    </div>
                </div>

                <!-- Target Shift Selection -->
                <div class="grid gap-2">
                    <Label
                        for="target-shift-select"
                        class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                        >Hoặc chọn thủ công ca của đồng nghiệp</Label
                    >
                    <select
                        id="target-shift-select"
                        v-model="selectedTargetShiftId"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-xs ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option :value="null" disabled>
                            -- Chọn ca trực của đồng nghiệp --
                        </option>
                        <option
                            v-for="ts in swappableShifts"
                            :key="ts.id"
                            :value="ts.id"
                        >
                            {{ ts.employee_name }} · Ca {{ ts.shift_name }} ({{
                                ts.shift_time
                            }}) - {{ ts.day }} ({{ ts.date }})
                        </option>
                    </select>
                    <p
                        v-if="swappableShifts.length === 0"
                        class="text-[10px] font-semibold text-rose-500 italic"
                    >
                        Không có ca trực nào khác của đồng nghiệp trong tuần này
                        để đổi.
                    </p>
                </div>

                <!-- Swap Notes -->
                <div class="grid gap-1.5">
                    <Label
                        for="swap-reason"
                        class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                        >Lý do xin đổi ca (Ghi chú)</Label
                    >
                    <Input
                        id="swap-reason"
                        type="text"
                        v-model="swapNotes"
                        placeholder="Ví dụ: Bận việc gia đình đột xuất, nhờ đổi hộ..."
                        required
                        class="h-10 text-xs"
                    />
                </div>

                <div
                    class="flex items-start gap-2 rounded-xl border border-amber-100/50 bg-amber-50/50 p-3 text-[10px] text-amber-700 dark:bg-amber-950/20 dark:text-amber-400"
                >
                    <AlertCircle
                        class="mt-0.5 size-4 shrink-0 text-amber-600"
                    />
                    <p>
                        <strong>Quy trình phê duyệt:</strong> Sau khi gửi, đồng
                        nghiệp nhận được yêu cầu phải nhấn "Đồng ý", sau đó Quản
                        lý/Owner duyệt thì ca đổi mới chính thức có hiệu lực.
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-2 border-t pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="emit('close')"
                        >Hủy</Button
                    >
                    <Button
                        type="button"
                        size="sm"
                        @click="submitSwapRequest"
                        class="bg-indigo-650 hover:bg-indigo-755 font-bold text-white"
                        :disabled="isSubmittingSwap || !selectedTargetShiftId"
                    >
                        {{ isSubmittingSwap ? 'Đang gửi...' : 'Gửi Yêu Cầu' }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
