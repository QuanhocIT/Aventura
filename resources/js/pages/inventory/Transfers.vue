<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Plus,
    Route as RouteIcon,
    PackageCheck,
    PackageOpen,
    XCircle,
    KeyRound,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface Transfer {
    id: number;
    status:
        | 'requested'
        | 'routed'
        | 'dispatched'
        | 'received'
        | 'rejected'
        | 'cancelled';
    ingredient: string | null;
    to_branch: string | null;
    from_branch: string | null;
    quantity_requested: number;
    quantity_dispatched: number | null;
    quantity_received: number | null;
    reason: string;
    owner_note: string | null;
    handover_code: string | null;
    requested_by: string | null;
    dispatched_by: string | null;
    received_by: string | null;
    reject_reason: string | null;
    created_at: string;
}

const props = defineProps<{
    transfers: Transfer[];
    branches: Array<{ id: number; name: string }>;
    ingredients: Array<{ id: number; name: string }>;
    isOwner: boolean;
}>();

const showRequest = ref(false);
const requestForm = useForm({
    to_branch_id: props.branches[0]?.id ?? ('' as number | ''),
    ingredient_id: '' as number | '',
    quantity_requested: 0,
    reason: '',
});
const submitRequest = () => {
    if (requestForm.processing) {
        return;
    }

    requestForm.post('/inventory/transfers', {
        preserveScroll: true,
        onSuccess: () => {
            requestForm.reset();
            showRequest.value = false;
        },
    });
};

// Route (owner)
const routing = ref<Transfer | null>(null);
const routeForm = useForm({
    from_branch_id: '' as number | '',
    owner_note: '',
});
const submitRoute = () => {
    if (!routing.value || routeForm.processing) {
        return;
    }

    routeForm.post(`/inventory/transfers/${routing.value.id}/route`, {
        preserveScroll: true,
        onSuccess: () => {
            routing.value = null;
            routeForm.reset();
        },
    });
};

const doDispatch = (t: Transfer) => {
    const q = window.prompt(
        `Số lượng xuất (tối đa ${t.quantity_requested}):`,
        String(t.quantity_requested),
    );

    if (q === null) {
        return;
    }

    router.post(
        `/inventory/transfers/${t.id}/dispatch`,
        { quantity_dispatched: Number(q) },
        { preserveScroll: true },
    );
};

const doReceive = (t: Transfer) => {
    const code = window.prompt(
        'Nhập mã giao nhận để xác nhận đã nhận đủ hàng:',
        '',
    );

    if (code === null) {
        return;
    }

    router.post(
        `/inventory/transfers/${t.id}/receive`,
        { handover_code: code.trim() },
        { preserveScroll: true },
    );
};

const doReject = (t: Transfer) => {
    const reason = window.prompt('Lý do từ chối yêu cầu:', '');

    if (reason === null || reason.trim().length === 0) {
        return;
    }

    router.post(
        `/inventory/transfers/${t.id}/reject`,
        { reject_reason: reason.trim() },
        { preserveScroll: true },
    );
};

const statusConfig: Record<string, { label: string; cls: string }> = {
    requested: {
        label: 'Chờ Chủ định tuyến',
        cls: 'bg-blue-50 text-blue-700 dark:bg-blue-950/30 dark:text-blue-300',
    },
    routed: {
        label: 'Chờ chi nhánh xuất',
        cls: 'bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300',
    },
    dispatched: {
        label: 'Chờ nhận (có mã)',
        cls: 'bg-purple-50 text-purple-700 dark:bg-purple-950/30 dark:text-purple-300',
    },
    received: {
        label: 'Hoàn tất',
        cls: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300',
    },
    rejected: {
        label: 'Từ chối',
        cls: 'bg-rose-50 text-rose-700 dark:bg-rose-950/30 dark:text-rose-300',
    },
    cancelled: {
        label: 'Đã hủy',
        cls: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
    },
};

const activeCount = computed(
    () =>
        props.transfers.filter(
            (t) => !['received', 'rejected', 'cancelled'].includes(t.status),
        ).length,
);
</script>

<template>
    <Head title="Điều chuyển liên chi nhánh" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
        <div
            class="flex flex-col gap-4 rounded-2xl border border-teal-500/20 bg-gradient-to-r from-slate-950 via-slate-900 to-teal-950/70 p-5 text-white shadow-xl sm:flex-row sm:items-center sm:justify-between sm:p-6"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-teal-400/30 bg-teal-500/20 text-teal-300 shadow-lg"
                >
                    <ArrowLeftRight class="size-6" />
                </div>
                <div>
                    <h1
                        class="text-xl font-black tracking-tight text-white sm:text-2xl"
                    >
                        Điều chuyển liên chi nhánh
                    </h1>
                    <p class="mt-1 text-xs text-teal-100/75">
                        Yêu cầu → Chủ định tuyến (mã giao nhận) → chi nhánh thừa
                        xuất → chi nhánh thiếu nhận. Người xuất ≠ người nhận.
                    </p>
                </div>
            </div>
            <Button
                @click="showRequest = !showRequest"
                class="shrink-0 gap-1.5 rounded-xl border-0 bg-teal-500 font-bold text-white shadow-lg shadow-teal-950/30 hover:bg-teal-400"
            >
                <Plus class="size-4" /> Tạo yêu cầu
            </Button>
        </div>

        <!-- Request form -->
        <div
            v-if="showRequest"
            class="rounded-2xl border border-teal-900/50 bg-teal-950/20 p-5 shadow-sm"
        >
            <form
                @submit.prevent="submitRequest"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2"
            >
                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs font-bold">Chi nhánh cần hàng</Label>
                    <select
                        v-model="requestForm.to_branch_id"
                        required
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                    >
                        <option
                            v-for="b in props.branches"
                            :key="b.id"
                            :value="b.id"
                        >
                            {{ b.name }}
                        </option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs font-bold">Nguyên liệu</Label>
                    <select
                        v-model="requestForm.ingredient_id"
                        required
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                    >
                        <option value="" disabled>— Chọn —</option>
                        <option
                            v-for="i in props.ingredients"
                            :key="i.id"
                            :value="i.id"
                        >
                            {{ i.name }}
                        </option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs font-bold">Số lượng cần</Label>
                    <Input
                        v-model="requestForm.quantity_requested"
                        type="number"
                        step="0.001"
                        min="0.001"
                        required
                    />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs font-bold">Lý do</Label>
                    <Input
                        v-model="requestForm.reason"
                        required
                        placeholder="VD: Hết bột mì đột xuất do khách tăng"
                    />
                </div>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="showRequest = false"
                        class="rounded-xl"
                        >Hủy</Button
                    >
                    <Button
                        type="submit"
                        :disabled="requestForm.processing"
                        class="rounded-xl border-0 bg-teal-500 font-bold text-white hover:bg-teal-400"
                    >
                        Gửi yêu cầu
                    </Button>
                </div>
            </form>
        </div>

        <div class="text-xs font-semibold text-muted-foreground">
            {{ activeCount }} yêu cầu đang xử lý
        </div>

        <div
            v-if="props.transfers.length === 0"
            class="rounded-2xl border border-dashed border-border bg-card/40 p-10 text-center text-sm text-muted-foreground"
        >
            Chưa có yêu cầu điều chuyển nào.
        </div>

        <div v-else class="flex flex-col gap-3">
            <div
                v-for="t in props.transfers"
                :key="t.id"
                class="rounded-2xl border border-border bg-card/80 p-4 shadow-sm backdrop-blur-sm"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span
                                class="font-bold text-slate-800 dark:text-slate-100"
                                >{{ t.ingredient }}</span
                            >
                            <span class="text-xs text-slate-400"
                                >×{{ t.quantity_requested }}</span
                            >
                            <span
                                :class="[
                                    'rounded-lg px-2 py-0.5 text-[10px] font-extrabold',
                                    statusConfig[t.status]?.cls,
                                ]"
                            >
                                {{ statusConfig[t.status]?.label }}
                            </span>
                        </div>
                        <div
                            class="mt-1 flex flex-wrap gap-x-3 text-[11px] text-muted-foreground"
                        >
                            <span v-if="t.from_branch"
                                >Từ: <b>{{ t.from_branch }}</b></span
                            >
                            <span
                                >Đến: <b>{{ t.to_branch }}</b></span
                            >
                            <span>Bởi: {{ t.requested_by }}</span>
                            <span>🕒 {{ t.created_at }}</span>
                        </div>
                        <p
                            class="mt-1 text-xs text-slate-600 dark:text-slate-300"
                        >
                            “{{ t.reason }}”
                        </p>
                        <p
                            v-if="t.owner_note"
                            class="mt-0.5 text-[11px] text-muted-foreground"
                        >
                            Chủ: {{ t.owner_note }}
                        </p>
                        <p
                            v-if="t.reject_reason"
                            class="mt-0.5 text-[11px] text-rose-500"
                        >
                            Từ chối: {{ t.reject_reason }}
                        </p>
                        <div
                            v-if="t.status === 'dispatched' && t.handover_code"
                            class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-purple-950/30 px-2 py-1 font-mono text-xs font-bold text-purple-300"
                        >
                            <KeyRound class="size-3.5" /> Mã:
                            {{ t.handover_code }}
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-wrap items-center gap-2">
                        <template
                            v-if="t.status === 'requested' && props.isOwner"
                        >
                            <Button
                                size="sm"
                                @click="routing = t"
                                class="h-8 gap-1.5 rounded-lg border-0 bg-indigo-600 text-[11px] font-bold text-white hover:bg-indigo-700"
                            >
                                <RouteIcon class="size-3.5" /> Định tuyến
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                @click="doReject(t)"
                                class="h-8 gap-1.5 rounded-lg text-[11px] text-rose-600"
                            >
                                <XCircle class="size-3.5" /> Từ chối
                            </Button>
                        </template>
                        <Button
                            v-if="t.status === 'routed'"
                            size="sm"
                            @click="doDispatch(t)"
                            class="h-8 gap-1.5 rounded-lg border-0 bg-amber-600 text-[11px] font-bold text-white hover:bg-amber-700"
                        >
                            <PackageOpen class="size-3.5" /> Xuất hàng
                        </Button>
                        <Button
                            v-if="t.status === 'dispatched'"
                            size="sm"
                            @click="doReceive(t)"
                            class="h-8 gap-1.5 rounded-lg border-0 bg-emerald-600 text-[11px] font-bold text-white hover:bg-emerald-700"
                        >
                            <PackageCheck class="size-3.5" /> Nhận hàng
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROUTE MODAL (owner) -->
    <div
        v-if="routing"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
    >
        <div
            class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="mb-3 flex items-center gap-2 border-b pb-3 text-sm font-extrabold tracking-wider text-indigo-600 uppercase"
            >
                <RouteIcon class="size-4.5" /> Định tuyến chi nhánh cấp hàng
            </div>
            <div class="mb-3 rounded-xl bg-muted/60 p-3 text-xs">
                <b>{{ routing.ingredient }}</b> ×{{
                    routing.quantity_requested
                }}
                → {{ routing.to_branch }}
            </div>
            <form @submit.prevent="submitRoute" class="flex flex-col gap-3">
                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs font-bold"
                        >Chi nhánh cấp hàng (chi nhánh thừa)</Label
                    >
                    <select
                        v-model="routeForm.from_branch_id"
                        required
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="" disabled>— Chọn chi nhánh —</option>
                        <option
                            v-for="b in props.branches.filter(
                                (br) => br.name !== routing?.to_branch,
                            )"
                            :key="b.id"
                            :value="b.id"
                        >
                            {{ b.name }}
                        </option>
                    </select>
                    <p
                        v-if="routeForm.errors.from_branch_id"
                        class="text-[11px] font-semibold text-rose-500"
                    >
                        {{ routeForm.errors.from_branch_id }}
                    </p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs font-bold">Ghi chú (tùy chọn)</Label>
                    <Input
                        v-model="routeForm.owner_note"
                        placeholder="VD: Ưu tiên lô cận hạn"
                    />
                </div>
                <div class="flex justify-end gap-2 border-t pt-3">
                    <Button
                        type="button"
                        variant="outline"
                        @click="routing = null"
                        class="rounded-xl text-xs"
                        >Hủy</Button
                    >
                    <Button
                        type="submit"
                        :disabled="routeForm.processing"
                        class="rounded-xl border-0 bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                    >
                        Định tuyến & sinh mã
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
