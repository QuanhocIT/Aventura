<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ShieldCheck, X, AlertCircle, CheckCircle2, XCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';
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
    assignment: any;
    action: 'check_in' | 'check_out' | 'absent';
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const isOnTime = ref(true);
const actualCheckInTime = ref('');
const overrideNotes = ref('');
const applyViolation = ref(false);
const penaltyAmount = ref(0);
const violationNotes = ref('');
const processingOverride = ref(false);

watch(
    () => [props.isOpen, props.action],
    ([newOpen, newAction]) => {
        if (newOpen) {
            isOnTime.value = true;
            actualCheckInTime.value = '';
            overrideNotes.value =
                newAction === 'check_in'
                    ? 'Xác nhận check-in vào ca bởi Quản lý'
                    : newAction === 'check_out'
                      ? 'Check-out hộ do nhân viên quên'
                      : 'Vắng mặt ca trực';
            applyViolation.value = false;
            penaltyAmount.value = 0;
            violationNotes.value =
                newAction === 'absent' ? 'Vắng mặt ca trực không lý do' : '';
        }
    },
    { immediate: true },
);

const submitAdminOverride = () => {
    if (!props.assignment) {
        return;
    }

    processingOverride.value = true;

    let url = '/schedules/check-in-employee';

    if (props.action === 'check_out') {
        url = '/schedules/check-out-employee';
    }

    if (props.action === 'absent') {
        url = '/schedules/absent-employee';
    }

    router.post(
        url,
        {
            assignment_id: props.assignment.id,
            is_on_time: isOnTime.value,
            actual_check_in_time: actualCheckInTime.value,
            notes: overrideNotes.value,
            apply_violation: applyViolation.value,
            penalty_amount: penaltyAmount.value,
            violation_notes: violationNotes.value,
        },
        {
            onSuccess: () => {
                emit('success');
                import('vue-sonner').then((m) =>
                    m.toast.success(
                        'Ghi nhận điều chỉnh chấm công thành công!',
                    ),
                );
            },
            onError: () => {
                import('vue-sonner').then((m) =>
                    m.toast.error('Có lỗi xảy ra khi cập nhật chấm công.'),
                );
            },
            onFinish: () => {
                processingOverride.value = false;
            },
        },
    );
};
</script>

<template>
    <Teleport to="body">
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
    >
        <Card
            class="w-full max-w-md animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
        >
            <CardHeader
                class="flex flex-row items-center justify-between gap-4 border-b pb-3"
            >
                <div>
                    <CardTitle
                        class="text-indigo-650 flex items-center gap-1.5 text-base"
                    >
                        <ShieldCheck class="size-5" />
                        Điều Chỉnh Chấm Công Thủ Công
                    </CardTitle>
                    <CardDescription
                        >Ghi nhận thông tin chấm công hộ hoặc báo vắng trực tiếp
                        dưới danh nghĩa quản trị viên.</CardDescription
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
                <!-- Target Employee Info -->
                <div
                    class="flex gap-3 rounded-xl border border-indigo-100 bg-indigo-50/40 p-3 dark:border-indigo-900/40 dark:bg-indigo-950/20"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-black text-indigo-700"
                    >
                        {{ assignment?.employee_name?.charAt(0) }}
                    </div>
                    <div>
                        <h4
                            class="text-xs font-bold text-slate-800 dark:text-slate-200"
                        >
                            {{ assignment?.employee_name }}
                        </h4>
                        <p class="mt-0.5 text-[10px] text-slate-500">
                            Ca:
                            <span class="font-mono font-bold text-indigo-600">{{
                                assignment?.shift_name
                            }}</span>
                            ({{ assignment?.shift_time }})
                        </p>
                    </div>
                </div>

                <!-- Operation Type Display -->
                <div class="grid gap-1.5">
                    <Label
                        class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                        >Thao tác thực hiện</Label
                    >
                    <div
                        class="flex items-center gap-2 rounded-xl border bg-slate-50 p-3 text-xs font-bold"
                    >
                        <span
                            v-if="action === 'check_in'"
                            class="size-2 animate-ping rounded-full bg-emerald-600"
                        ></span>
                        <span
                            v-if="action === 'check_out'"
                            class="size-2 rounded-full bg-indigo-600"
                        ></span>
                        <span
                            v-if="action === 'absent'"
                            class="size-2 rounded-full bg-rose-600"
                        ></span>

                        {{
                            action === 'check_in'
                                ? 'Xác nhận check-in (Báo vào ca)'
                                : action === 'check_out'
                                  ? 'Check-out hộ (Báo ra ca)'
                                  : 'Báo Vắng (Không phép/Có phép)'
                        }}
                    </div>
                </div>

                <!-- Check-in Question & Choices -->
                <div
                    v-if="action === 'check_in'"
                    class="space-y-3 rounded-xl border border-indigo-200 bg-indigo-50/50 p-3.5 dark:border-indigo-900/40 dark:bg-indigo-950/20"
                >
                    <Label class="text-xs font-bold text-slate-800 dark:text-slate-200">
                        Nhân viên vào ca đúng giờ so với thời gian gửi yêu cầu xin xác nhận check-in không?
                    </Label>

                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <button
                            type="button"
                            @click="isOnTime = true"
                            :class="[
                                'flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border p-2.5 text-xs font-bold transition',
                                isOnTime
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700 ring-2 ring-emerald-500/20 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400'
                            ]"
                        >
                            <CheckCircle2 class="size-4 text-emerald-600 dark:text-emerald-400" />
                            🟢 CÓ (Đúng giờ)
                        </button>

                        <button
                            type="button"
                            @click="isOnTime = false"
                            :class="[
                                'flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border p-2.5 text-xs font-bold transition',
                                !isOnTime
                                    ? 'border-rose-500 bg-rose-50 text-rose-700 ring-2 ring-rose-500/20 dark:bg-rose-950/40 dark:text-rose-300'
                                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400'
                            ]"
                        >
                            <XCircle class="size-4 text-rose-600 dark:text-rose-400" />
                            🔴 KHÔNG (Đi trễ)
                        </button>
                    </div>

                    <div v-if="!isOnTime" class="space-y-1.5 pt-2 animate-in fade-in duration-150">
                        <Label for="actual-time-input" class="text-xs font-bold text-slate-700 dark:text-slate-300">
                            Thời gian vào ca thực tế
                        </Label>
                        <Input
                            id="actual-time-input"
                            type="time"
                            v-model="actualCheckInTime"
                            class="h-9 font-mono text-xs font-bold"
                            placeholder="HH:mm"
                            required
                        />
                        <p class="text-[10px] text-muted-foreground">
                            Nhập giờ nhân viên có mặt làm việc thực tế (Ví dụ: 06:15).
                        </p>
                    </div>
                </div>

                <!-- Notes / Reason -->
                <div class="grid gap-1.5">
                    <Label
                        for="override-notes"
                        class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                        >Lý do điều chỉnh (Ghi chú)</Label
                    >
                    <Input
                        id="override-notes"
                        type="text"
                        v-model="overrideNotes"
                        placeholder="Ví dụ: Nhân viên quên bấm máy check-in..."
                        required
                        class="h-9 text-xs"
                    />
                </div>

                <!-- Violation Integration Checkbox -->
                <div class="flex items-center space-x-2 p-1 pt-2">
                    <input
                        id="apply-violation-check"
                        type="checkbox"
                        v-model="applyViolation"
                        class="text-indigo-650 h-4 w-4 cursor-pointer rounded border-slate-300 focus:ring-indigo-500"
                    />
                    <Label
                        for="apply-violation-check"
                        class="cursor-pointer text-xs font-bold text-rose-600 select-none"
                    >
                        Lập biên bản vi phạm kỷ luật & Khấu trừ lương
                    </Label>
                </div>

                <!-- Expandable Violation Fields -->
                <div
                    v-if="applyViolation"
                    class="animate-in space-y-3 rounded-xl border border-rose-100 bg-rose-50/50 p-3 duration-150 fade-in slide-in-from-top-1 dark:border-rose-900/40 dark:bg-rose-950/20"
                >
                    <div class="grid gap-1.5">
                        <Label
                            for="violation-notes"
                            class="text-[10px] font-bold tracking-wide text-rose-500 uppercase"
                            >Mô tả vi phạm kỷ luật</Label
                        >
                        <Input
                            id="violation-notes"
                            type="text"
                            v-model="violationNotes"
                            placeholder="Ví dụ: Đi trễ quá 30 phút / Vắng mặt không lý do..."
                            class="h-8 border-rose-200 text-xs focus-visible:ring-rose-500"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label
                            for="penalty-amount"
                            class="text-[10px] font-bold tracking-wide text-rose-500 uppercase"
                            >Số tiền khấu trừ phạt (VND)</Label
                        >
                        <Input
                            id="penalty-amount"
                            type="number"
                            v-model.number="penaltyAmount"
                            min="0"
                            step="1000"
                            placeholder="Nhập số tiền phạt ví dụ: 50000"
                            class="h-8 border-rose-200 font-mono text-xs font-bold focus-visible:ring-rose-500"
                        />
                    </div>
                </div>

                <div
                    class="flex items-start gap-2 rounded-xl border border-amber-100/50 bg-amber-50/50 p-3 text-[10px] text-amber-700 dark:bg-amber-950/20 dark:text-amber-400"
                >
                    <AlertCircle
                        class="mt-0.5 size-4 shrink-0 text-amber-600"
                    />
                    <p>
                        <strong>Cảnh báo kiểm toán:</strong> Mọi thao tác ghi
                        nhận hộ sẽ được lưu vết trực tiếp trong Audit Log của
                        nhà hàng và hiển thị trên bảng lương nhân viên.
                    </p>
                </div>

                <!-- Buttons -->
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
                        @click="submitAdminOverride"
                        class="cursor-pointer bg-indigo-600 font-semibold text-white hover:bg-indigo-700"
                        :disabled="processingOverride"
                    >
                        {{
                            processingOverride
                                ? 'Đang cập nhật...'
                                : 'Xác nhận ghi nhận'
                        }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
    </Teleport>
</template>
