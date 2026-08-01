<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Clock, CheckCircle2, Check } from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

const props = defineProps<{
    myRegistrations?: Array<{ shift_id: number; date: string }>;
    shifts?: Array<{ id: number; name: string; start: string; end: string }>;
}>();

const selectedRegs = ref<Array<{ shift_id: number; date: string }>>([]);
const isSavingRegs = ref(false);

const weekDays = [
    { key: 'Monday', label: 'Thứ Hai' },
    { key: 'Tuesday', label: 'Thứ Ba' },
    { key: 'Wednesday', label: 'Thứ Tư' },
    { key: 'Thursday', label: 'Thứ Năm' },
    { key: 'Friday', label: 'Thứ Sáu' },
    { key: 'Saturday', label: 'Thứ Bảy' },
    { key: 'Sunday', label: 'Chủ Nhật' },
];

const weekDaysWithDates = computed(() => {
    const current = new Date();
    const day = current.getDay();
    const diff = current.getDate() - day + (day === 0 ? -6 : 1);
    const monday = new Date(current.setDate(diff));

    return weekDays.map((wd, index) => {
        const nextDay = new Date(monday);
        nextDay.setDate(monday.getDate() + index);
        const yyyy = nextDay.getFullYear();
        const mm = String(nextDay.getMonth() + 1).padStart(2, '0');
        const dd = String(nextDay.getDate()).padStart(2, '0');

        return {
            ...wd,
            dateLabel: `${dd}/${mm}`,
            fullLabel: `${wd.label} (${dd}/${mm})`,
            dateStr: `${yyyy}-${mm}-${dd}`,
        };
    });
});

const isShiftSelected = (shiftId: number, dateStr: string) => {
    return selectedRegs.value.some(
        (r) => r.shift_id === shiftId && r.date === dateStr,
    );
};

const toggleShiftSelection = (shiftId: number, dateStr: string) => {
    const index = selectedRegs.value.findIndex(
        (r) => r.shift_id === shiftId && r.date === dateStr,
    );

    if (index > -1) {
        selectedRegs.value.splice(index, 1);
    } else {
        selectedRegs.value.push({ shift_id: shiftId, date: dateStr });
    }
};

const saveRegistrations = () => {
    isSavingRegs.value = true;
    router.post(
        '/schedules/register',
        {
            registrations: selectedRegs.value,
        },
        {
            onSuccess: () => {
                import('vue-sonner').then((m) =>
                    m.toast.success('Đã lưu đăng ký ca làm rảnh thành công!'),
                );
            },
            onError: () => {
                import('vue-sonner').then((m) =>
                    m.toast.error('Có lỗi xảy ra khi lưu đăng ký.'),
                );
            },
            onFinish: () => {
                isSavingRegs.value = false;
            },
        },
    );
};

onMounted(() => {
    if (props.myRegistrations) {
        selectedRegs.value = props.myRegistrations.map((r) => ({
            shift_id: r.shift_id,
            date: r.date,
        }));
    }
});
</script>

<template>
    <Card class="shadow-sm">
        <CardHeader
            class="flex flex-col gap-4 border-b pb-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <CardTitle
                    class="text-emerald-655 dark:text-emerald-450 flex items-center gap-1.5 text-base"
                >
                    <Clock
                        class="text-emerald-650 dark:text-emerald-455 size-5"
                    />
                    Đăng Ký Ca Làm Việc Rảnh Tuần Này
                </CardTitle>
                <CardDescription
                    >Chọn các ca trực rảnh trong tuần của bạn. Chủ quán sẽ dựa
                    trên thông tin này để xếp lịch phù hợp.</CardDescription
                >
            </div>
            <Button
                @click="saveRegistrations"
                :disabled="isSavingRegs"
                class="bg-emerald-650 flex h-8 cursor-pointer items-center gap-1.5 text-xs font-semibold text-white shadow hover:bg-emerald-700"
            >
                <CheckCircle2 class="size-4" />
                {{ isSavingRegs ? 'Đang lưu...' : 'Lưu đăng ký ca rảnh' }}
            </Button>
        </CardHeader>

        <CardContent class="p-4">
            <div
                class="overflow-hidden rounded-2xl border bg-white dark:bg-slate-950"
            >
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr
                            class="bg-slate-55 border-b text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-900"
                        >
                            <th class="w-[120px] border-r p-3.5">
                                Thứ trong tuần
                            </th>
                            <th class="p-3.5">
                                Các ca có thể nhận việc (Tích chọn để đăng ký)
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <tr
                            v-for="day in weekDaysWithDates"
                            :key="day.key"
                            class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                        >
                            <td
                                class="border-r bg-slate-50/30 p-3.5 font-bold text-slate-700 dark:text-slate-300"
                            >
                                <div class="flex flex-col gap-0.5">
                                    <span>{{ day.label }}</span>
                                    <span
                                        class="font-mono text-[10px] font-medium text-slate-400 dark:text-slate-500"
                                        >({{ day.dateLabel }})</span
                                    >
                                </div>
                            </td>
                            <td class="flex flex-wrap items-center gap-2 p-3.5">
                                <button
                                    v-for="shift in shifts"
                                    :key="shift.id"
                                    type="button"
                                    @click="
                                        toggleShiftSelection(
                                            shift.id,
                                            day.dateStr,
                                        )
                                    "
                                    :class="[
                                        'inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-bold transition-all duration-150 select-none active:scale-95',
                                        isShiftSelected(shift.id, day.dateStr)
                                            ? 'border-emerald-200 bg-emerald-50 font-extrabold text-emerald-600 dark:border-emerald-900/30 dark:bg-emerald-950/20 dark:text-emerald-400'
                                            : 'border-slate-200 bg-white font-medium text-slate-600 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400',
                                    ]"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="
                                            isShiftSelected(
                                                shift.id,
                                                day.dateStr,
                                            )
                                                ? 'animate-pulse bg-emerald-600 dark:bg-emerald-400'
                                                : 'bg-slate-300 dark:bg-slate-700'
                                        "
                                    />
                                    <span>{{ shift.name.split(' (')[0] }}</span>
                                    <span
                                        class="font-mono text-[9px] opacity-70"
                                        >({{ shift.start }} -
                                        {{ shift.end }})</span
                                    >
                                    <Check
                                        v-if="
                                            isShiftSelected(
                                                shift.id,
                                                day.dateStr,
                                            )
                                        "
                                        class="text-emerald-650 dark:text-emerald-450 ml-0.5 size-3 shrink-0"
                                    />
                                </button>
                                <div
                                    v-if="!shifts || !shifts.length"
                                    class="text-[10px] text-slate-400 italic"
                                >
                                    Không có ca làm việc khả dụng
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
