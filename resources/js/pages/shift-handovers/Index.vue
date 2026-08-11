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
    submitted_at: string | null;
    accepted_at: string | null;
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
    shifts: { id: number; name: string }[];
    colleagues: { id: number; name: string }[];
    activeBranchId: number | null;
}>();

const currency = new Intl.NumberFormat('vi-VN');

const statusConfig: Record<
    HandoverStatus,
    { label: string; badge: string; dot: string }
> = {
    draft: {
        label: 'Đang lập',
        badge: 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:border-slate-700',
        dot: 'bg-slate-400',
    },
    pending_acceptance: {
        label: 'Chờ ca sau nhận',
        badge: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900/40',
        dot: 'bg-amber-500 animate-pulse',
    },
    accepted: {
        label: 'Đã bàn giao',
        badge: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/40',
        dot: 'bg-emerald-500',
    },
    disputed: {
        label: 'Không khớp',
        badge: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/40',
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
    () => props.handovers.data.find((h) => h.status === 'draft') ?? null,
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

function tickItem(handover: Handover, item: TemplateItem, photo?: string) {
    router.post(
        `/shift-handovers/${handover.id}/check`,
        { item_id: item.id, is_done: true, photo: photo ?? null },
        {
            preserveScroll: true,
            onSuccess: () => toast.success(`Đã xác nhận: ${item.title}`),
            onError: (errors: Record<string, string>) =>
                toast.error(errors.photo ?? 'Không ghi nhận được mục này.'),
        },
    );
}

/** Mục bắt buộc ảnh: mở camera rồi gửi kèm ảnh dạng data URI. */
function tickWithPhoto(handover: Handover, item: TemplateItem) {
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

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
        <div class="flex flex-col gap-1">
            <h1
                class="flex items-center gap-2 text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
            >
                <Handshake class="h-5 w-5 text-indigo-600" />
                Bàn giao ca
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Ghi nhận tiền, hàng, thiết bị, sự cố và công việc còn tồn trong
                một phiên. Ca sau phải xác nhận thì phiên mới khép lại.
            </p>
        </div>

        <div
            v-if="!activeBranchId"
            class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-950/20"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-600" />
            <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">
                Hãy chọn một chi nhánh cụ thể để bàn giao ca.
            </p>
        </div>

        <!-- Mở phiên mới -->
        <Card v-else-if="!draftHandover">
            <CardHeader>
                <CardTitle class="text-base">Mở phiên bàn giao</CardTitle>
                <CardDescription>
                    Chọn mẫu bàn giao áp dụng cho chi nhánh này.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <Label class="text-xs font-bold text-slate-500 uppercase"
                        >Mẫu bàn giao</Label
                    >
                    <select
                        v-model="openForm.template_id"
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
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
                </div>
                <div class="flex-1">
                    <Label class="text-xs font-bold text-slate-500 uppercase"
                        >Ngày</Label
                    >
                    <Input
                        v-model="openForm.handover_date"
                        type="date"
                        class="mt-1"
                    />
                </div>
                <Button :disabled="openForm.processing" @click="openHandover">
                    Bắt đầu bàn giao
                </Button>
            </CardContent>
        </Card>

        <!-- Phiên đang lập: checklist -->
        <Card v-else>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <ListChecks class="h-4 w-4 text-indigo-600" />
                    Phiên đang lập
                </CardTitle>
                <CardDescription>
                    Còn
                    <strong>{{ draftHandover.unfinished_items }}</strong> mục
                    chưa hoàn thành. Chưa xong hết thì chưa nộp được.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-2">
                <div
                    v-for="item in activeTemplate?.items ?? []"
                    :key="item.id"
                    class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-800"
                >
                    <div class="min-w-0">
                        <p
                            class="text-sm font-medium text-slate-800 dark:text-slate-200"
                        >
                            {{ item.title }}
                        </p>
                        <p
                            v-if="item.requires_photo"
                            class="text-[11px] font-semibold text-amber-600"
                        >
                            Bắt buộc chụp ảnh
                        </p>
                    </div>
                    <Button
                        size="sm"
                        variant="outline"
                        class="shrink-0 gap-1.5"
                        @click="
                            item.requires_photo
                                ? tickWithPhoto(draftHandover, item)
                                : tickItem(draftHandover, item)
                        "
                    >
                        <component
                            :is="item.requires_photo ? Camera : CheckCircle2"
                            class="h-3.5 w-3.5"
                        />
                        Xác nhận
                    </Button>
                </div>

                <Button
                    class="mt-2 self-end"
                    :disabled="draftHandover.unfinished_items > 0"
                    @click="openSubmit(draftHandover)"
                >
                    Nộp bàn giao
                </Button>
            </CardContent>
        </Card>

        <!-- Lịch sử -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Lịch sử bàn giao</CardTitle>
                <CardDescription>{{ handovers.total }} phiên.</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-if="handovers.data.length === 0"
                    class="flex flex-col items-center justify-center gap-3 py-16 text-slate-400"
                >
                    <ClipboardCheck class="h-8 w-8" />
                    <p class="text-sm font-medium">
                        Chưa có phiên bàn giao nào.
                    </p>
                </div>

                <ul
                    v-else
                    class="divide-y divide-slate-100 dark:divide-slate-800"
                >
                    <li
                        v-for="h in handovers.data"
                        :key="h.id"
                        class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    {{ h.from_user_name }}
                                    <span class="text-slate-400">→</span>
                                    {{ h.to_user_name ?? 'chưa chọn' }}
                                </span>
                                <span
                                    v-if="h.cash_amount"
                                    class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 tabular-nums dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ currency.format(h.cash_amount) }}đ
                                </span>
                            </div>

                            <p
                                class="mt-1 text-xs text-slate-400 dark:text-slate-500"
                            >
                                {{ h.handover_date }}
                                <template v-if="h.template_name">
                                    · {{ h.template_name }}
                                </template>
                                <template v-if="h.submitted_at">
                                    · nộp {{ h.submitted_at }}
                                </template>
                            </p>

                            <div
                                v-if="
                                    h.equipment_notes ||
                                    h.incident_notes ||
                                    h.pending_tasks
                                "
                                class="mt-2 flex flex-col gap-1 text-xs text-slate-600 dark:text-slate-300"
                            >
                                <p
                                    v-if="h.equipment_notes"
                                    class="flex items-start gap-1.5"
                                >
                                    <Wrench
                                        class="mt-0.5 size-3 shrink-0 text-slate-400"
                                    />
                                    {{ h.equipment_notes }}
                                </p>
                                <p
                                    v-if="h.incident_notes"
                                    class="flex items-start gap-1.5"
                                >
                                    <AlertTriangle
                                        class="mt-0.5 size-3 shrink-0 text-amber-500"
                                    />
                                    {{ h.incident_notes }}
                                </p>
                                <p
                                    v-if="h.pending_tasks"
                                    class="flex items-start gap-1.5"
                                >
                                    <ListChecks
                                        class="mt-0.5 size-3 shrink-0 text-indigo-500"
                                    />
                                    {{ h.pending_tasks }}
                                </p>
                            </div>

                            <p
                                v-if="h.dispute_reason"
                                class="mt-2 rounded-lg bg-rose-50 px-2.5 py-1.5 text-xs text-rose-700 dark:bg-rose-950/30 dark:text-rose-300"
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
                                    class="h-7 text-xs"
                                    @click="acceptHandover(h)"
                                >
                                    Nhận
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-7 text-xs"
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
    <div
        v-if="submitTarget"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="submitTarget = null"
    >
        <div
            class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl dark:bg-slate-900"
        >
            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                Nộp bàn giao ca
            </h2>

            <div class="mt-4 flex flex-col gap-3">
                <div>
                    <Label class="text-xs font-bold text-slate-500 uppercase"
                        >Người nhận ca
                        <span class="text-rose-500">*</span></Label
                    >
                    <select
                        v-model="submitForm.to_user_id"
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
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
                    <Label class="text-xs font-bold text-slate-500 uppercase"
                        >Tiền mặt bàn giao</Label
                    >
                    <Input
                        v-model.number="submitForm.cash_amount"
                        type="number"
                        min="0"
                        step="1000"
                        class="mt-1"
                    />
                </div>

                <div>
                    <Label class="text-xs font-bold text-slate-500 uppercase"
                        >Thiết bị</Label
                    >
                    <textarea
                        v-model="submitForm.equipment_notes"
                        rows="2"
                        placeholder="Máy POS, máy in, tủ mát… có gì bất thường?"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-900"
                    ></textarea>
                </div>

                <div>
                    <Label class="text-xs font-bold text-slate-500 uppercase"
                        >Sự cố trong ca</Label
                    >
                    <textarea
                        v-model="submitForm.incident_notes"
                        rows="2"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-900"
                    ></textarea>
                </div>

                <div>
                    <Label class="text-xs font-bold text-slate-500 uppercase"
                        >Công việc còn tồn</Label
                    >
                    <textarea
                        v-model="submitForm.pending_tasks"
                        rows="2"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-900"
                    ></textarea>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <Button variant="outline" @click="submitTarget = null"
                    >Hủy</Button
                >
                <Button
                    :disabled="submitForm.processing || !submitForm.to_user_id"
                    @click="submitHandover"
                >
                    Nộp bàn giao
                </Button>
            </div>
        </div>
    </div>

    <!-- Dialog báo không khớp -->
    <div
        v-if="disputeTarget"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="disputeTarget = null"
    >
        <div
            class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl dark:bg-slate-900"
        >
            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                Bàn giao không khớp
            </h2>
            <textarea
                v-model="disputeForm.dispute_reason"
                rows="3"
                placeholder="Nêu rõ thiếu gì, lệch bao nhiêu…"
                class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-900"
            ></textarea>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" @click="disputeTarget = null"
                    >Hủy</Button
                >
                <Button
                    :disabled="disputeForm.processing"
                    @click="submitDispute"
                >
                    Gửi
                </Button>
            </div>
        </div>
    </div>
</template>
