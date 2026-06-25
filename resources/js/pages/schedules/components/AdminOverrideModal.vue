<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ShieldCheck, X, AlertCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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

const overrideNotes = ref(
    props.action === 'check_in' ? 'Check-in hộ do nhân viên quên' : 
    (props.action === 'check_out' ? 'Check-out hộ do nhân viên quên' : 'Vắng mặt không lý do')
);
const applyViolation = ref(false);
const penaltyAmount = ref(0);
const violationNotes = ref('');
const processingOverride = ref(false);

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
    
    router.post(url, {
        assignment_id: props.assignment.id,
        notes: overrideNotes.value,
        apply_violation: applyViolation.value,
        penalty_amount: penaltyAmount.value,
        violation_notes: violationNotes.value
    }, {
        only: ['assignments', 'stats', 'weeklyAssignments', 'monthlyAssignments'],
        onSuccess: () => {
            emit('success');
            import('vue-sonner').then(m => m.toast.success('Ghi nhận điều chỉnh chấm công thành công!'));
        },
        onError: () => {
            import('vue-sonner').then(m => m.toast.error('Có lỗi xảy ra khi cập nhật chấm công.'));
        },
        onFinish: () => {
            processingOverride.value = false;
        }
    });
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
        <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
            <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                <div>
                    <CardTitle class="text-base flex items-center gap-1.5 text-indigo-650">
                        <ShieldCheck class="size-5" />
                        Điều Chỉnh Chấm Công Thủ Công
                    </CardTitle>
                    <CardDescription>Ghi nhận thông tin chấm công hộ hoặc báo vắng trực tiếp dưới danh nghĩa quản trị viên.</CardDescription>
                </div>
                <button @click="emit('close')" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer">
                    <X class="size-4" />
                </button>
            </CardHeader>
            
            <CardContent class="pt-4 space-y-4">
                <!-- Target Employee Info -->
                <div class="bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40 rounded-xl p-3 flex gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-xs font-black">
                        {{ assignment?.employee_name?.charAt(0) }}
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ assignment?.employee_name }}</h4>
                        <p class="text-[10px] text-slate-500 mt-0.5">Ca: <span class="font-bold text-indigo-600 font-mono">{{ assignment?.shift_name }}</span> ({{ assignment?.shift_time }})</p>
                    </div>
                </div>

                <!-- Operation Type Display -->
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Thao tác thực hiện</Label>
                    <div class="p-3 border rounded-xl bg-slate-50 text-xs font-bold flex items-center gap-2">
                        <span v-if="action === 'check_in'" class="size-2 rounded-full bg-emerald-600 animate-ping"></span>
                        <span v-if="action === 'check_out'" class="size-2 rounded-full bg-indigo-600"></span>
                        <span v-if="action === 'absent'" class="size-2 rounded-full bg-rose-600"></span>
                        
                        {{ action === 'check_in' ? 'Check-in hộ (Báo vào ca)' : 
                           (action === 'check_out' ? 'Check-out hộ (Báo ra ca)' : 'Báo Vắng (Không phép/Có phép)') }}
                    </div>
                </div>

                <!-- Notes / Reason -->
                <div class="grid gap-1.5">
                    <Label for="override-notes" class="text-xs font-bold text-slate-500 uppercase tracking-wide">Lý do điều chỉnh (Ghi chú)</Label>
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
                        class="rounded border-slate-300 text-indigo-650 focus:ring-indigo-500 h-4 w-4 cursor-pointer"
                    />
                    <Label for="apply-violation-check" class="text-xs font-bold text-rose-600 cursor-pointer select-none">
                        Lập biên bản vi phạm kỷ luật & Khấu trừ lương
                    </Label>
                </div>

                <!-- Expandable Violation Fields -->
                <div v-if="applyViolation" class="space-y-3 p-3 bg-rose-50/50 dark:bg-rose-950/20 border border-rose-100 dark:border-rose-900/40 rounded-xl animate-in fade-in slide-in-from-top-1 duration-150">
                    <div class="grid gap-1.5">
                        <Label for="violation-notes" class="text-[10px] font-bold text-rose-500 uppercase tracking-wide">Mô tả vi phạm kỷ luật</Label>
                        <Input 
                            id="violation-notes" 
                            type="text" 
                            v-model="violationNotes" 
                            placeholder="Ví dụ: Đi trễ quá 30 phút / Vắng mặt không lý do..."
                            class="h-8 text-xs border-rose-200 focus-visible:ring-rose-500" 
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="penalty-amount" class="text-[10px] font-bold text-rose-500 uppercase tracking-wide">Số tiền khấu trừ phạt (VND)</Label>
                        <Input 
                            id="penalty-amount" 
                            type="number" 
                            v-model.number="penaltyAmount" 
                            min="0" 
                            step="1000"
                            placeholder="Nhập số tiền phạt ví dụ: 50000"
                            class="h-8 text-xs border-rose-200 focus-visible:ring-rose-500 font-mono font-bold" 
                        />
                    </div>
                </div>
                
                <div class="p-3 bg-amber-50/50 dark:bg-amber-950/20 rounded-xl flex items-start gap-2 text-[10px] text-amber-700 dark:text-amber-400 border border-amber-100/50">
                    <AlertCircle class="size-4 shrink-0 text-amber-600 mt-0.5" />
                    <p><strong>Cảnh báo kiểm toán:</strong> Mọi thao tác ghi nhận hộ sẽ được lưu vết trực tiếp trong Audit Log của nhà hàng và hiển thị trên bảng lương nhân viên.</p>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end gap-2 pt-2 border-t">
                    <Button type="button" variant="outline" size="sm" @click="emit('close')">Hủy</Button>
                    <Button 
                        type="button" 
                        size="sm" 
                        @click="submitAdminOverride" 
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer"
                        :disabled="processingOverride"
                    >
                        {{ processingOverride ? 'Đang cập nhật...' : 'Xác nhận ghi nhận' }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
