<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Camera,
    CheckCircle2,
    ClipboardCheck,
    Handshake,
    ListChecks,
    Wrench,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
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
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type HandoverStatus = 'draft' | 'pending_acceptance' | 'accepted' | 'disputed';

type Handover = {
    id: number;
    handover_date: string | null;
    status: HandoverStatus;
    from_user_id?: number;
    to_user_id?: number | null;
    can_manage?: boolean;
    can_accept?: boolean;
    template_id?: number | null;
    from_user_name: string | null;
    to_user_name: string | null;
    from_shift_name: string | null;
    to_shift_name: string | null;
    template_name: string | null;
    cash_amount: number | null;
    equipment_notes: string | null;
    incident_notes: string | null;
    pending_tasks: string | null;
    dispute_reason: string | null;
    unfinished_items: number;
    checklist_total: number;
    checklist_done: number;
    checklist: ChecklistEntry[];
    from_shift: Shift | null;
    to_shift: Shift | null;
    submitted_at: string | null;
    accepted_at: string | null;
};

type Shift = {
    id: number;
    name: string;
    code?: string;
    start_time?: string;
    end_time?: string;
    is_overnight?: boolean;
};

type ChecklistEntry = {
    id: number;
    title: string;
    description: string | null;
    requires_photo: boolean;
    is_done: boolean;
    notes: string | null;
    photo_url: string | null;
    checked_at: string | null;
};

type TemplateItem = {
    id: number;
    title: string;
    description: string | null;
    requires_photo: boolean;
};

type Template = { id: number; name: string; items: TemplateItem[] };

const props = defineProps<{
    handovers: {
        data: Handover[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    templates: Template[];
    shifts: Shift[];
    colleagues: { id: number; name: string }[];
    activeBranchId: number | null;
    activeBranch?: { id: number; name: string } | null;
    currentUserId?: number;
    isManager?: boolean;
}>();

const currency = new Intl.NumberFormat('vi-VN');
const activeBranchName = computed(
    () => props.activeBranch?.name ?? 'Chưa chọn chi nhánh',
);

const statusConfig: Record<
    HandoverStatus,
    { label: string; badge: string; dot: string }
> = {
    draft: {
        label: 'Đang lập',
        badge: 'bg-slate-100 text-slate-700 border-slate-300 font-bold dark:bg-slate-900 dark:text-slate-300 dark:border-slate-700',
        dot: 'bg-slate-500',
    },
    pending_acceptance: {
        label: 'Chờ ca sau nhận',
        badge: 'bg-amber-50 text-amber-800 border-amber-300 font-bold dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900/40',
        dot: 'bg-amber-500 animate-pulse',
    },
    accepted: {
        label: 'Đã bàn giao',
        badge: 'bg-emerald-50 text-emerald-800 border-emerald-300 font-bold dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/40',
        dot: 'bg-emerald-500',
    },
    disputed: {
        label: 'Không khớp',
        badge: 'bg-rose-50 text-rose-800 border-rose-300 font-bold dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/40',
        dot: 'bg-rose-500',
    },
};

// ── Mở phiên bàn giao ─────────────────────────────────────────────────────────

const openForm = useForm({
    template_id: props.templates[0]?.id ?? null,
    from_shift_id: null as number | null,
    to_shift_id: null as number | null,
    handover_date: new Date().toISOString().slice(0, 10),
});

function openHandover() {
    openForm.post('/shift-handovers', {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã mở phiên bàn giao ca.'),
        onError: () => toast.error('Không mở được phiên bàn giao.'),
    });
}

// ── Nộp bàn giao ──────────────────────────────────────────────────────────────

const submitTarget = ref<Handover | null>(null);
const submitForm = useForm({
    to_user_id: null as number | null,
    cash_amount: 0,
    equipment_notes: '',
    incident_notes: '',
    pending_tasks: '',
});

const draftHandover = computed(
    () =>
        props.handovers.data.find(
            (h) => h.status === 'draft' && (h.can_manage ?? true),
        ) ?? null,
);

function openSubmit(handover: Handover) {
    submitTarget.value = handover;
    submitForm.reset();
}

function submitHandover() {
    if (!submitTarget.value) {
        return;
    }

    submitForm.patch(`/shift-handovers/${submitTarget.value.id}/submit`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã nộp bàn giao, chờ ca sau xác nhận.');
            submitTarget.value = null;
        },
        onError: (errors: Record<string, string>) =>
            toast.error(
                errors.checklist ?? 'Chưa nộp được, kiểm tra lại thông tin.',
            ),
    });
}

// ── Tick checklist ────────────────────────────────────────────────────────────

const activeTemplate = computed(
    () =>
        props.templates.find((t) => t.id === openForm.template_id) ??
        props.templates[0] ??
        null,
);

const handoverStats = computed(() => {
    const rows = props.handovers.data;

    return {
        total: props.handovers.total,
        draft: rows.filter((h) => h.status === 'draft').length,
        pending: rows.filter((h) => h.status === 'pending_acceptance').length,
        accepted: rows.filter((h) => h.status === 'accepted').length,
        disputed: rows.filter((h) => h.status === 'disputed').length,
    };
});

const draftChecklist = computed(() => draftHandover.value?.checklist ?? []);
const draftProgress = computed(() => {
    const total = draftHandover.value?.checklist_total ?? 0;

    return total === 0
        ? 100
        : Math.round(
              ((draftHandover.value?.checklist_done ?? 0) / total) * 100,
          );
});

const statusOrder: HandoverStatus[] = [
    'pending_acceptance',
    'disputed',
    'draft',
    'accepted',
];

const priorityHandovers = computed(() =>
    [...props.handovers.data]
        .filter((handover) => handover.status !== 'accepted')
        .sort(
            (a, b) =>
                statusOrder.indexOf(a.status) - statusOrder.indexOf(b.status),
        )
        .slice(0, 4),
);

const formatShift = (shift: Shift | null) => {
    if (!shift) {
        return 'Chưa chọn ca';
    }

    if (!shift.start_time || !shift.end_time) {
        return shift.name;
    }

    return `${shift.name} · ${shift.start_time.slice(0, 5)}–${shift.end_time.slice(0, 5)}${shift.is_overnight ? ' (+1)' : ''}`;
};

function tickItem(handover: Handover, item: ChecklistEntry, photo?: string) {
    const nextDone = !item.is_done;

    if (nextDone && item.requires_photo && !photo && !item.photo_url) {
        tickWithPhoto(handover, item);

        return;
    }

    router.post(
        `/shift-handovers/${handover.id}/check`,
        {
            item_id: item.id,
            is_done: nextDone,
            photo: photo ?? null,
            notes: item.notes ?? null,
        },
        {
            preserveScroll: true,
            onSuccess: () => toast.success(`Đã xác nhận: ${item.title}`),
            onError: (errors: Record<string, string>) =>
                toast.error(errors.photo ?? 'Không ghi nhận được mục này.'),
        },
    );
}

/** Mục bắt buộc ảnh: mở camera rồi gửi kèm ảnh dạng data URI. */
function tickWithPhoto(handover: Handover, item: ChecklistEntry) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.capture = 'environment';
    input.onchange = () => {
        const file = input.files?.[0];

        if (!file) {
            return;
        }

        const reader = new FileReader();
        reader.onload = () => tickItem(handover, item, String(reader.result));
        reader.readAsDataURL(file);
    };
    input.click();
}

// ── Nhận / báo không khớp ─────────────────────────────────────────────────────

function acceptHandover(handover: Handover) {
    router.patch(
        `/shift-handovers/${handover.id}/accept`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã xác nhận nhận bàn giao.'),
            onError: () => toast.error('Không xác nhận được.'),
        },
    );
}

const disputeTarget = ref<Handover | null>(null);
const disputeForm = useForm({ dispute_reason: '' });

function submitDispute() {
    if (!disputeTarget.value) {
        return;
    }

    disputeForm.patch(`/shift-handovers/${disputeTarget.value.id}/dispute`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã ghi nhận bàn giao không khớp.');
            disputeTarget.value = null;
        },
        onError: () => toast.error('Cần nêu rõ lý do.'),
    });
}
</script>

<template>
    <Head title="Bàn giao ca" />

    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 lg:p-8"
    >
        <div
            class="relative overflow-hidden rounded-[24px] border border-indigo-200/90 bg-gradient-to-r from-indigo-50/80 via-slate-50/70 to-white p-5 text-slate-800 shadow-sm shadow-indigo-100/50 sm:p-7 dark:border-indigo-500/20 dark:bg-[radial-gradient(circle_at_top_right,_rgba(99,102,241,0.25),_transparent_36%),linear-gradient(120deg,_#0f172a,_#1e1b4b_55%,_#111827)] dark:text-white dark:shadow-2xl dark:shadow-indigo-950/20"
        >
            <div
                class="relative z-10 flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-14 items-center justify-center rounded-2xl border border-indigo-200 bg-indigo-100/80 text-indigo-700 shadow-xs dark:border-indigo-300/30 dark:bg-indigo-400/15 dark:text-indigo-200 dark:shadow-inner dark:shadow-indigo-300/10"
                    >
                        <Handshake class="size-6" />
                    </div>
                    <div>
                        <h1
                            class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl dark:text-white"
                        >
                            Trung tâm Bàn giao ca
                        </h1>
                        <p
                            class="max-w-2xl text-sm leading-6 font-medium text-slate-600 dark:text-indigo-100/75"
                        >
                            Chốt trách nhiệm giữa hai ca bằng checklist, số
                            tiền, tài sản, sự cố và việc tồn. Phiên chỉ khép lại
                            khi ca sau xác nhận.
                        </p>
                    </div>
                </div>
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center xl:min-w-[360px] xl:justify-end"
                >
                    <div
                        class="rounded-2xl border border-indigo-200/80 bg-white/90 px-4 py-3 text-xs shadow-xs backdrop-blur-md dark:border-white/15 dark:bg-white/10"
                    >
                        <p class="font-semibold text-slate-500 dark:text-indigo-200/70">Phạm vi bàn giao</p>
                        <p class="mt-1 font-black text-slate-900 dark:text-white">
                            {{ activeBranchName }}
                        </p>
                        <p class="mt-0.5 text-[10px] font-medium text-slate-500 dark:text-indigo-200/60">
                            {{ handoverStats.total }} phiên trong lịch sử
                        </p>
                    </div>
                    <a
                        href="#handover-history"
                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700 active:scale-95 dark:bg-white dark:text-indigo-950 dark:hover:bg-indigo-50"
                        >Xem lịch sử</a
                    >
                </div>
            </div>
        </div>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm shadow-slate-200/50 dark:border-border dark:bg-card dark:shadow-none">
                <div class="flex items-center justify-between">
                    <p
                        class="text-xs font-black tracking-wide text-slate-700 uppercase dark:text-slate-300"
                    >
                        Phiên đang lập
                    </p>
                    <ListChecks class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                </div>
                <p class="mt-2 text-3xl font-black text-slate-900 dark:text-foreground">
                    {{ handoverStats.draft }}
                </p>
                <p class="mt-1 text-[11px] font-medium text-slate-500 dark:text-muted-foreground">
                    Chưa hoàn tất checklist
                </p>
            </div>
            <div
                class="rounded-2xl border border-amber-300/80 bg-amber-50/70 p-4 shadow-sm shadow-amber-100/50 dark:border-amber-500/20 dark:bg-amber-950/20 dark:shadow-none"
            >
                <div class="flex items-center justify-between">
                    <p
                        class="text-xs font-black tracking-wide text-amber-800 uppercase dark:text-amber-300"
                    >
                        Chờ ca sau
                    </p>
                    <AlertTriangle class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                </div>
                <p class="mt-2 text-3xl font-black text-amber-900 dark:text-amber-100">
                    {{ handoverStats.pending }}
                </p>
                <p class="mt-1 text-[11px] font-semibold text-amber-700 dark:text-amber-200/70">
                    Cần xác nhận để đóng phiên
                </p>
            </div>
            <div
                class="rounded-2xl border border-rose-300/80 bg-rose-50/70 p-4 shadow-sm shadow-rose-100/50 dark:border-rose-500/20 dark:bg-rose-950/20 dark:shadow-none"
            >
                <div class="flex items-center justify-between">
                    <p
                        class="text-xs font-black tracking-wide text-rose-800 uppercase dark:text-rose-300"
                    >
                        Không khớp
                    </p>
                    <AlertTriangle class="h-4 w-4 text-rose-600 dark:text-rose-400" />
                </div>
                <p class="mt-2 text-3xl font-black text-rose-900 dark:text-rose-100">
                    {{ handoverStats.disputed }}
                </p>
                <p class="mt-1 text-[11px] font-semibold text-rose-700 dark:text-rose-200/70">
                    Cần xử lý và ghi nhận nguyên nhân
                </p>
            </div>
            <div
                class="rounded-2xl border border-emerald-300/80 bg-emerald-50/70 p-4 shadow-sm shadow-emerald-100/50 dark:border-emerald-500/20 dark:bg-emerald-950/20 dark:shadow-none"
            >
                <div class="flex items-center justify-between">
                    <p
                        class="text-xs font-black tracking-wide text-emerald-800 uppercase dark:text-emerald-300"
                    >
                        Đã hoàn tất
                    </p>
                    <CheckCircle2 class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                </div>
                <p class="mt-2 text-3xl font-black text-emerald-900 dark:text-emerald-100">
                    {{ handoverStats.accepted }}
                </p>
                <p class="mt-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-200/70">
                    Đã có ca sau xác nhận
                </p>
            </div>
        </section>

        <div
            v-if="!activeBranchId"
            class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/20"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-600" />
            <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">
                Hãy chọn một chi nhánh cụ thể để bàn giao ca.
            </p>
        </div>

        <!-- Mở phiên mới -->
        <Card
            v-else-if="!draftHandover"
            class="overflow-hidden border-slate-200/90 bg-white shadow-sm dark:border-indigo-500/20 dark:bg-card"
        >
            <CardHeader class="border-b border-slate-200/90 bg-slate-50/80 dark:border-indigo-500/10 dark:bg-indigo-950/10">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle class="flex items-center gap-2 text-base text-slate-900 dark:text-foreground"
                            ><Handshake class="h-4 w-4 text-indigo-600 dark:text-indigo-400" /> Mở
                            phiên bàn giao</CardTitle
                        >
                        <CardDescription class="mt-1 text-slate-600 dark:text-muted-foreground"
                            >Chốt đúng ngày, ca giao, ca nhận và mẫu checklist
                            áp dụng cho {{ activeBranchName }}.</CardDescription
                        >
                    </div>
                    <span
                        class="hidden rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[11px] font-bold text-indigo-700 sm:inline-flex dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300"
                        >Bước 1 / 3</span
                    >
                </div>
            </CardHeader>
            <CardContent class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-5">
                <div>
                    <Label class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                        >Mẫu bàn giao</Label
                    >
                    <select
                        v-model="openForm.template_id"
                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                    >
                        <option :value="null">Không dùng mẫu</option>
                        <option
                            v-for="t in templates"
                            :key="t.id"
                            :value="t.id"
                        >
                            {{ t.name }}
                        </option>
                    </select>
                    <p class="mt-1 text-[11px] font-medium text-slate-500 dark:text-muted-foreground">
                        {{ activeTemplate?.items.length ?? 0 }} mục checklist sẽ
                        được áp dụng.
                    </p>
                </div>
                <div>
                    <Label class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                        >Ngày</Label
                    >
                    <Input
                        v-model="openForm.handover_date"
                        type="date"
                        class="mt-1 border-slate-300 text-slate-800 shadow-xs dark:border-slate-700 dark:text-slate-100"
                    />
                </div>
                <div>
                    <Label class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                        >Ca giao</Label
                    >
                    <select
                        v-model="openForm.from_shift_id"
                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                    >
                        <option :value="null">— Chọn ca giao —</option>
                        <option
                            v-for="shift in shifts"
                            :key="`from-${shift.id}`"
                            :value="shift.id"
                        >
                            {{ formatShift(shift) }}
                        </option>
                    </select>
                </div>
                <div>
                    <Label class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                        >Ca nhận</Label
                    >
                    <select
                        v-model="openForm.to_shift_id"
                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                    >
                        <option :value="null">— Chọn ca nhận —</option>
                        <option
                            v-for="shift in shifts"
                            :key="`to-${shift.id}`"
                            :value="shift.id"
                        >
                            {{ formatShift(shift) }}
                        </option>
                    </select>
                </div>
                <div class="flex items-end">
                    <Button
                        class="w-full gap-2 shadow-sm font-bold"
                        :disabled="openForm.processing"
                        @click="openHandover"
                        ><Handshake class="h-4 w-4" /> Bắt đầu bàn giao</Button
                    >
                </div>
            </CardContent>
        </Card>

        <!-- Phiên đang lập: checklist -->
        <Card v-else class="overflow-hidden border-slate-200/90 bg-white shadow-sm dark:border-indigo-500/20 dark:bg-card">
            <CardHeader class="border-b border-slate-200/90 bg-slate-50/80 dark:border-indigo-500/10 dark:bg-indigo-950/10">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div>
                        <CardTitle class="flex items-center gap-2 text-base text-slate-900 dark:text-foreground"
                            ><ListChecks class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                            Phiên đang lập</CardTitle
                        >
                        <CardDescription class="mt-1 font-medium text-slate-600 dark:text-muted-foreground"
                            >{{ draftHandover.from_user_name }} ·
                            {{ formatShift(draftHandover.from_shift) }} →
                            {{
                                formatShift(draftHandover.to_shift)
                            }}</CardDescription
                        >
                    </div>
                    <div class="min-w-[220px]">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-slate-600 dark:text-indigo-200/70"
                                >Tiến độ checklist</span
                            ><strong class="font-black text-slate-900 dark:text-indigo-200"
                                >{{ draftHandover.checklist_done }}/{{
                                     draftHandover.checklist_total
                                }}
                                · {{ draftProgress }}%</strong
                            >
                        </div>
                        <div
                            class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800"
                        >
                            <div
                                class="h-full rounded-full bg-indigo-600 transition-all dark:bg-indigo-400"
                                :style="{ width: `${draftProgress}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-5">
                <div
                    v-if="draftChecklist.length"
                    class="grid gap-3 lg:grid-cols-2"
                >
                    <div
                        v-for="item in draftChecklist"
                        :key="item.id"
                        class="rounded-xl border p-4 transition shadow-2xs"
                        :class="
                            item.is_done
                                ? 'border-emerald-300 bg-emerald-50/60 dark:border-emerald-500/25 dark:bg-emerald-500/5'
                                : 'border-slate-200/90 bg-white dark:border-border dark:bg-background'
                        "
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                :class="
                                    item.is_done
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'
                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-400'
                                "
                            >
                                <CheckCircle2
                                    v-if="item.is_done"
                                    class="h-4 w-4"
                                /><Camera
                                    v-else-if="item.requires_photo"
                                    class="h-4 w-4"
                                /><ListChecks v-else class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-sm font-bold text-slate-900 dark:text-foreground"
                                >
                                    {{ item.title }}
                                </p>
                                <p
                                    v-if="item.description"
                                    class="mt-1 text-[11px] leading-4 font-medium text-slate-600 dark:text-muted-foreground"
                                >
                                    {{ item.description }}
                                </p>
                                <p
                                    class="mt-2 text-[11px] font-bold"
                                    :class="
                                        item.is_done
                                            ? 'text-emerald-700 dark:text-emerald-400'
                                            : 'text-amber-700 dark:text-amber-400'
                                    "
                                >
                                    {{
                                        item.is_done
                                            ? `Đã xác nhận${item.checked_at ? ` · ${item.checked_at}` : ''}`
                                            : item.requires_photo
                                              ? 'Bắt buộc chụp ảnh'
                                              : 'Chưa xác nhận'
                                    }}
                                </p>
                                <p
                                    v-if="item.photo_url"
                                    class="mt-1 text-[11px] font-semibold text-indigo-700 hover:underline dark:text-indigo-300"
                                >
                                    <a
                                        :href="item.photo_url"
                                        target="_blank"
                                        rel="noreferrer"
                                        >Mở ảnh xác nhận ↗</a
                                    >
                                </p>
                            </div>
                            <Button
                                size="sm"
                                :variant="item.is_done ? 'outline' : 'default'"
                                class="shrink-0 gap-1.5 text-xs font-semibold"
                                @click="tickItem(draftHandover, item)"
                                ><CheckCircle2
                                    v-if="!item.is_done"
                                    class="h-3.5 w-3.5"
                                /><span>{{
                                    item.is_done ? 'Bỏ xác nhận' : 'Xác nhận'
                                }}</span></Button
                            >
                        </div>
                        <Input
                            :model-value="item.notes ?? ''"
                            class="mt-3 h-8 border-slate-300 text-xs text-slate-800 placeholder:text-slate-400 dark:border-slate-700 dark:text-slate-100"
                            placeholder="Ghi chú cho mục này (nếu có)"
                            @update:model-value="
                                item.notes = String($event ?? '')
                            "
                        />
                    </div>
                </div>
                <div
                    v-else
                    class="rounded-xl border border-dashed border-amber-300 bg-amber-50/70 p-5 text-sm font-semibold text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/5 dark:text-amber-200"
                >
                    Mẫu bàn giao hiện chưa có mục checklist. Bạn vẫn có thể nộp
                    phiên sau khi bổ sung thông tin tiền, thiết bị, sự cố và
                    việc tồn.
                </div>
                <div
                    class="mt-5 flex flex-col gap-3 border-t border-slate-200/90 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-border"
                >
                    <p class="text-xs font-semibold text-slate-600 dark:text-muted-foreground">
                        {{
                            draftHandover.unfinished_items
                                ? `Còn ${draftHandover.unfinished_items} mục cần hoàn tất trước khi nộp.`
                                : 'Checklist đã hoàn tất, sẵn sàng chuyển sang ca nhận.'
                        }}
                    </p>
                    <Button
                        class="gap-2 font-bold shadow-sm"
                        :disabled="draftHandover.unfinished_items > 0"
                        @click="openSubmit(draftHandover)"
                        ><Handshake class="h-4 w-4" /> Nộp bàn giao</Button
                    >
                </div>
            </CardContent>
        </Card>

        <section
            v-if="priorityHandovers.length"
            class="grid gap-4 xl:grid-cols-[1.25fr_0.75fr]"
        >
            <Card class="overflow-hidden border-amber-300/80 bg-white shadow-sm dark:border-amber-500/20 dark:bg-card">
                <CardHeader
                    class="border-b border-amber-200/80 bg-amber-50/70 py-4 dark:border-amber-500/10 dark:bg-amber-950/10"
                >
                    <CardTitle class="flex items-center gap-2 text-base text-amber-900 dark:text-amber-300"
                        ><AlertTriangle class="h-4 w-4 text-amber-600 dark:text-amber-400" /> Cần
                        hành động</CardTitle
                    >
                    <CardDescription class="mt-1 text-xs font-medium text-amber-800/80 dark:text-amber-200/70"
                        >Các phiên chưa khép hoặc có chênh lệch được đưa lên đầu
                        để xử lý.</CardDescription
                    >
                </CardHeader>
                <CardContent class="divide-y divide-slate-200/80 p-0 dark:divide-border">
                    <div
                        v-for="handover in priorityHandovers"
                        :key="handover.id"
                        class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="font-mono text-xs font-black text-indigo-700 dark:text-indigo-300"
                                    >#{{ handover.id }}</span
                                ><span
                                    :class="[
                                        'rounded-full border px-2 py-0.5 text-[10px] font-bold',
                                        statusConfig[handover.status].badge,
                                    ]"
                                    >{{
                                        statusConfig[handover.status].label
                                    }}</span
                                >
                            </div>
                            <p
                                class="mt-1 text-xs font-bold text-slate-900 dark:text-foreground"
                            >
                                {{ handover.from_user_name }} →
                                {{
                                    handover.to_user_name ||
                                    'Chưa chọn người nhận'
                                }}
                            </p>
                            <p class="mt-1 text-[11px] font-medium text-slate-500 dark:text-muted-foreground">
                                {{ formatShift(handover.from_shift) }} →
                                {{ formatShift(handover.to_shift) }} ·
                                {{ handover.handover_date }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <Button
                                v-if="handover.status === 'draft'"
                                size="sm"
                                class="gap-1.5 text-xs font-semibold"
                                @click="openSubmit(handover)"
                                ><Handshake class="h-3.5 w-3.5" /> Nộp</Button
                            >
                            <template
                                v-else-if="
                                    handover.status === 'pending_acceptance'
                                "
                            >
                                <Button
                                    size="sm"
                                    class="gap-1.5 text-xs font-semibold"
                                    @click="acceptHandover(handover)"
                                    ><CheckCircle2 class="h-3.5 w-3.5" /> Nhận
                                    ca</Button
                                >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="text-xs font-semibold"
                                    @click="disputeTarget = handover"
                                    >Báo lệch</Button
                                >
                            </template>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-slate-200/90 bg-white shadow-sm dark:border-border dark:bg-card">
                <CardHeader class="border-b border-slate-200/90 bg-slate-50/70 py-4 dark:border-border dark:bg-muted/20"
                    ><CardTitle class="text-base text-slate-900 dark:text-foreground">Luồng đóng phiên</CardTitle
                    ><CardDescription class="mt-1 text-xs text-slate-600 dark:text-muted-foreground"
                        >Không bỏ qua bước xác nhận hai bên.</CardDescription
                    ></CardHeader
                >
                <CardContent class="space-y-4 p-5">
                    <div class="flex gap-3">
                        <div
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-black text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300"
                        >
                            1
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-900 dark:text-foreground">
                                Ca ra lập phiên
                            </p>
                            <p class="mt-1 text-[11px] font-medium text-slate-600 dark:text-muted-foreground">
                                Chọn ca, người nhận và hoàn tất checklist.
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-black text-amber-800 dark:bg-amber-500/15 dark:text-amber-300"
                        >
                            2
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-900 dark:text-foreground">
                                Ca vào kiểm tra
                            </p>
                            <p class="mt-1 text-[11px] font-medium text-slate-600 dark:text-muted-foreground">
                                Đối chiếu tiền, thiết bị, sự cố và việc tồn.
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-black text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300"
                        >
                            3
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-900 dark:text-foreground">
                                Xác nhận hoặc báo lệch
                            </p>
                            <p class="mt-1 text-[11px] font-medium text-slate-600 dark:text-muted-foreground">
                                Chỉ trạng thái đã nhận mới được xem là hoàn tất.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <!-- Lịch sử -->
        <Card id="handover-history" class="overflow-hidden border-slate-200/90 bg-white shadow-sm dark:border-border dark:bg-card">
            <CardHeader class="border-b border-slate-200/90 bg-slate-50/80 dark:border-border dark:bg-slate-900/50">
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <CardTitle class="text-base text-slate-900 dark:text-foreground">Lịch sử bàn giao</CardTitle
                        ><CardDescription class="mt-1 text-xs text-slate-600 dark:text-muted-foreground"
                            >{{ handovers.total }} phiên trong phạm vi chi
                            nhánh.</CardDescription
                        >
                    </div>
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-muted-foreground"
                        >Hiển thị {{ handovers.data.length }} phiên gần
                        nhất</span
                    >
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-if="handovers.data.length === 0"
                    class="flex flex-col items-center justify-center gap-3 py-20 text-slate-400"
                >
                    <div
                        class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-900/30 dark:bg-indigo-950/40"
                    >
                        <ClipboardCheck
                            class="size-10 text-indigo-600 opacity-60 dark:text-indigo-400"
                        />
                    </div>
                    <p
                        class="text-center text-sm font-semibold text-slate-600 dark:text-slate-300"
                    >
                        Chưa có phiên bàn giao nào.
                    </p>
                </div>

                <ul
                    v-else
                    class="divide-y divide-slate-200/80 dark:divide-slate-800"
                >
                    <li
                        v-for="h in handovers.data"
                        :key="h.id"
                        class="flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-slate-50/80 sm:flex-row sm:items-start sm:justify-between dark:hover:bg-slate-900/30"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-sm font-bold text-slate-900 dark:text-slate-200"
                                >
                                    {{ h.from_user_name }}
                                    <span class="text-slate-400">→</span>
                                    {{ h.to_user_name ?? 'chưa chọn' }}
                                </span>
                                <span
                                    v-if="h.cash_amount"
                                    class="rounded-md border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700 tabular-nums dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ currency.format(h.cash_amount) }}đ
                                </span>
                            </div>

                            <p
                                class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{ h.handover_date }}
                                · {{ formatShift(h.from_shift) }} →
                                {{ formatShift(h.to_shift) }}
                                <template v-if="h.template_name">
                                    · {{ h.template_name }}
                                </template>
                                <template v-if="h.submitted_at">
                                    · nộp {{ h.submitted_at }}
                                </template>
                            </p>

                            <div
                                v-if="h.checklist_total"
                                class="mt-2 flex items-center gap-2 text-[11px] font-semibold text-slate-600 dark:text-muted-foreground"
                            >
                                <div
                                    class="h-1.5 w-28 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800"
                                >
                                    <div
                                        class="h-full rounded-full bg-indigo-600 dark:bg-indigo-500"
                                        :style="{
                                            width: `${Math.min(Math.round((h.checklist_done / h.checklist_total) * 100), 100)}%`,
                                        }"
                                    ></div>
                                </div>
                                <span
                                    >{{ h.checklist_done }}/{{
                                        h.checklist_total
                                    }}
                                    mục checklist</span
                                >
                            </div>

                            <div
                                v-if="
                                    h.equipment_notes ||
                                    h.incident_notes ||
                                    h.pending_tasks
                                "
                                class="mt-2 flex flex-col gap-1 text-xs text-slate-700 dark:text-slate-300"
                            >
                                <p
                                    v-if="h.equipment_notes"
                                    class="flex items-start gap-1.5 font-medium"
                                >
                                    <Wrench
                                        class="mt-0.5 size-3 shrink-0 text-slate-500"
                                    />
                                    {{ h.equipment_notes }}
                                </p>
                                <p
                                    v-if="h.incident_notes"
                                    class="flex items-start gap-1.5 font-medium text-amber-800 dark:text-amber-300"
                                >
                                    <AlertTriangle
                                        class="mt-0.5 size-3 shrink-0 text-amber-600"
                                    />
                                    {{ h.incident_notes }}
                                </p>
                                <p
                                    v-if="h.pending_tasks"
                                    class="flex items-start gap-1.5 font-medium text-indigo-800 dark:text-indigo-300"
                                >
                                    <ListChecks
                                        class="mt-0.5 size-3 shrink-0 text-indigo-600"
                                    />
                                    {{ h.pending_tasks }}
                                </p>
                            </div>

                            <p
                                v-if="h.dispute_reason"
                                class="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300"
                            >
                                {{ h.dispute_reason }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-bold tracking-wider uppercase',
                                    statusConfig[h.status].badge,
                                ]"
                            >
                                <span
                                    :class="[
                                        'h-1.5 w-1.5 rounded-full',
                                        statusConfig[h.status].dot,
                                    ]"
                                />
                                {{ statusConfig[h.status].label }}
                            </span>

                            <div
                                v-if="h.status === 'pending_acceptance'"
                                class="flex gap-1.5"
                            >
                                <Button
                                    size="sm"
                                    class="h-7 text-xs font-semibold"
                                    @click="acceptHandover(h)"
                                >
                                    Nhận
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-7 text-xs font-semibold"
                                    @click="disputeTarget = h"
                                >
                                    Không khớp
                                </Button>
                            </div>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>

    <!-- Dialog nộp bàn giao -->
    <Teleport to="body">
        <div
            v-if="submitTarget"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto backdrop-blur-xs"
            @click.self="submitTarget = null"
        >
            <div
                class="my-auto flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xl dark:border-indigo-500/20 dark:bg-slate-900"
            >
                <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-200/90 bg-slate-50/70 p-5 sm:p-6 pb-4 dark:border-slate-800 dark:bg-slate-900">
                    <div>
                        <h2
                            class="text-base font-bold text-slate-900 dark:text-slate-100"
                        >
                            Nộp bàn giao ca
                        </h2>
                        <p
                            class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ submitTarget.from_user_name }} →
                            {{
                                submitTarget.to_user_name ||
                                'Chưa chọn người nhận'
                            }}
                            · {{ formatShift(submitTarget.from_shift) }} →
                            {{ formatShift(submitTarget.to_shift) }}
                        </p>
                    </div>
                    <span
                        class="rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300"
                        >Bước 2 / 3</span
                    >
                </div>

                <div class="flex-1 overflow-y-auto p-5 sm:p-6 pt-4">
                    <div
                        class="grid gap-3 rounded-xl border border-indigo-200 bg-indigo-50/70 p-3 text-xs sm:grid-cols-3 dark:border-indigo-500/15 dark:bg-indigo-950/20"
                    >
                        <div>
                            <p class="font-semibold text-slate-600 dark:text-slate-400">
                                Checklist
                            </p>
                            <p
                                class="mt-1 font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{ submitTarget.checklist_done }}/{{
                                    submitTarget.checklist_total
                                }}
                                mục
                            </p>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-600 dark:text-slate-400">
                                Tiền mặt
                            </p>
                            <p
                                class="mt-1 font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{ currency.format(submitForm.cash_amount || 0) }}đ
                            </p>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-600 dark:text-slate-400">Ngày</p>
                            <p
                                class="mt-1 font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{ submitTarget.handover_date }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div>
                            <Label
                                class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                                >Người nhận ca
                                <span class="text-rose-500">*</span></Label
                            >
                            <select
                                v-model="submitForm.to_user_id"
                                class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-xs focus:border-indigo-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                            >
                                <option :value="null">— Chọn người nhận —</option>
                                <option
                                    v-for="c in colleagues"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <Label
                                class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                                >Tiền mặt bàn giao</Label
                            >
                            <Input
                                v-model.number="submitForm.cash_amount"
                                type="number"
                                min="0"
                                step="1000"
                                class="mt-1 border-slate-300 text-slate-800 shadow-xs dark:border-slate-700 dark:text-slate-100"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <Label
                                class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                                >Thiết bị</Label
                            >
                            <textarea
                                v-model="submitForm.equipment_notes"
                                rows="2"
                                placeholder="Máy POS, máy in, tủ mát… có gì bất thường?"
                                class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 shadow-xs focus:border-indigo-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                            ></textarea>
                        </div>

                        <div class="sm:col-span-2">
                            <Label
                                class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                                >Sự cố trong ca</Label
                            >
                            <textarea
                                v-model="submitForm.incident_notes"
                                rows="2"
                                class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 shadow-xs focus:border-indigo-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                            ></textarea>
                        </div>

                        <div class="sm:col-span-2">
                            <Label
                                class="text-xs font-black text-slate-700 uppercase dark:text-slate-300"
                                >Công việc còn tồn</Label
                            >
                            <textarea
                                v-model="submitForm.pending_tasks"
                                rows="2"
                                class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 shadow-xs focus:border-indigo-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200/90 p-5 sm:p-6 py-4 dark:border-slate-800">
                    <Button variant="outline" class="font-semibold" @click="submitTarget = null"
                        >Hủy</Button
                    >
                    <Button
                        class="font-bold shadow-sm"
                        :disabled="
                            submitForm.processing || !submitForm.to_user_id
                        "
                        @click="submitHandover"
                    >
                        Nộp bàn giao
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Dialog báo không khớp -->
    <Teleport to="body">
        <div
            v-if="disputeTarget"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
            @click.self="disputeTarget = null"
        >
            <div
                class="w-full max-w-md rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900"
            >
                <h2
                    class="text-base font-bold text-slate-900 dark:text-slate-100"
                >
                    Bàn giao không khớp
                </h2>
                <textarea
                    v-model="disputeForm.dispute_reason"
                    rows="3"
                    placeholder="Nêu rõ thiếu gì, lệch bao nhiêu…"
                    class="mt-3 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 shadow-xs focus:border-indigo-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                ></textarea>
                <div class="mt-4 flex justify-end gap-2">
                    <Button variant="outline" class="font-semibold" @click="disputeTarget = null"
                        >Hủy</Button
                    >
                    <Button
                        variant="destructive"
                        class="font-bold shadow-sm"
                        :disabled="disputeForm.processing"
                        @click="submitDispute"
                    >
                        Gửi báo cáo
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
