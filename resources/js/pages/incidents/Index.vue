<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Flame,
    ShieldAlert,
    CheckCircle2,
    Siren,
    ArrowUpCircle,
    ClipboardCheck,
    Plus,
    HeartPulse,
    Wrench,
    Lock,
    ShieldQuestion,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface Incident {
    id: number;
    type: string;
    severity: 'low' | 'medium' | 'high' | 'critical';
    title: string;
    description: string;
    location: string | null;
    occurred_at_display: string;
    immediate_action: string | null;
    injured_count: number;
    needs_shift_cover: boolean;
    status: 'open' | 'investigating' | 'escalated' | 'resolved';
    escalated: boolean;
    escalated_to_name: string | null;
    reported_by_name: string;
    branch_name: string | null;
    acknowledged_by_name: string | null;
    acknowledged_at_display: string | null;
    resolution_report: string | null;
    resolved_by_name: string | null;
    resolved_at_display: string | null;
    created_at_display: string;
    has_photo: boolean;
}

const props = defineProps<{
    incidents: Incident[];
    stats: { open: number; escalated: number; resolved: number; critical: number };
    canManage: boolean;
}>();

const showReportForm = ref(false);
const activeFilter = ref<'active' | 'resolved' | 'all'>('active');

const reportForm = useForm({
    type: 'accident',
    severity: 'medium',
    title: '',
    description: '',
    location: '',
    occurred_at: new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16),
    immediate_action: '',
    injured_count: 0,
    needs_shift_cover: false,
    photo: null as File | null,
});

const submitReport = () => {
    if (reportForm.processing) return;
    reportForm.post('/incidents', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            reportForm.reset();
            showReportForm.value = false;
        },
    });
};

// Resolve modal
const showResolveModal = ref(false);
const selected = ref<Incident | null>(null);
const resolveForm = useForm({ resolution_report: '' });

const openResolve = (i: Incident) => {
    selected.value = i;
    resolveForm.reset();
    resolveForm.clearErrors();
    showResolveModal.value = true;
};
const submitResolve = () => {
    if (!selected.value || resolveForm.processing) return;
    resolveForm.post(`/incidents/${selected.value.id}/resolve`, {
        preserveScroll: true,
        onSuccess: () => {
            showResolveModal.value = false;
            resolveForm.reset();
        },
    });
};

const doAcknowledge = (i: Incident) => {
    useForm({}).post(`/incidents/${i.id}/acknowledge`, { preserveScroll: true });
};
const doEscalate = (i: Incident) => {
    useForm({}).post(`/incidents/${i.id}/escalate`, { preserveScroll: true });
};

const filtered = computed(() =>
    props.incidents.filter((i) => {
        if (activeFilter.value === 'all') return true;
        if (activeFilter.value === 'resolved') return i.status === 'resolved';
        return i.status !== 'resolved';
    }),
);

const typeConfig: Record<string, { label: string; icon: any; cls: string }> = {
    accident: { label: 'Tai nạn', icon: HeartPulse, cls: 'text-rose-600' },
    food_poisoning: { label: 'Ngộ độc TP', icon: Siren, cls: 'text-orange-600' },
    fire: { label: 'Cháy nổ', icon: Flame, cls: 'text-red-600' },
    security: { label: 'An ninh', icon: ShieldAlert, cls: 'text-indigo-600' },
    equipment_failure: { label: 'Hỏng thiết bị', icon: Wrench, cls: 'text-slate-600' },
    theft: { label: 'Trộm cắp', icon: ShieldQuestion, cls: 'text-amber-600' },
    other: { label: 'Khác', icon: ShieldAlert, cls: 'text-slate-500' },
};

const severityConfig: Record<string, { label: string; cls: string }> = {
    low: { label: 'Thấp', cls: 'bg-slate-100 text-slate-600 border-slate-200' },
    medium: { label: 'Trung bình', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
    high: { label: 'Cao', cls: 'bg-orange-50 text-orange-700 border-orange-200' },
    critical: { label: 'Nghiêm trọng', cls: 'bg-rose-50 text-rose-700 border-rose-200' },
};

const statusConfig: Record<string, { label: string; cls: string }> = {
    open: { label: 'Mới báo', cls: 'bg-blue-50 text-blue-700' },
    investigating: { label: 'Đang xử lý', cls: 'bg-amber-50 text-amber-700' },
    escalated: { label: 'Đã báo Chủ', cls: 'bg-rose-50 text-rose-700' },
    resolved: { label: 'Đã đóng', cls: 'bg-emerald-50 text-emerald-700' },
};
</script>

<template>
    <Head title="Sự cố khẩn cấp" />

    <div class="mx-auto flex max-w-6xl flex-col gap-6 p-4 sm:p-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div
                    class="flex size-11 items-center justify-center rounded-2xl bg-gradient-to-br from-rose-500 to-red-600 text-white shadow-lg"
                >
                    <Siren class="size-6" />
                </div>
                <div>
                    <h1 class="text-xl font-black tracking-tight text-slate-800 dark:text-slate-100">
                        Sổ Sự Cố Khẩn Cấp
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Báo & xử lý tai nạn, ngộ độc, cháy nổ, an ninh — tự động báo Chủ khi nghiêm trọng.
                    </p>
                </div>
            </div>
            <Button
                @click="showReportForm = !showReportForm"
                class="gap-1.5 rounded-xl border-0 bg-gradient-to-r from-rose-600 to-red-600 font-bold text-white hover:from-rose-700 hover:to-red-700"
            >
                <Plus class="size-4" /> Báo sự cố
            </Button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="text-2xl font-black text-blue-600">{{ props.stats.open }}</div>
                <div class="text-[11px] font-semibold text-slate-500">Đang mở</div>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="text-2xl font-black text-rose-600">{{ props.stats.escalated }}</div>
                <div class="text-[11px] font-semibold text-slate-500">Đã báo Chủ</div>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="text-2xl font-black text-orange-600">{{ props.stats.critical }}</div>
                <div class="text-[11px] font-semibold text-slate-500">Nghiêm trọng đang mở</div>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="text-2xl font-black text-emerald-600">{{ props.stats.resolved }}</div>
                <div class="text-[11px] font-semibold text-slate-500">Đã đóng</div>
            </div>
        </div>

        <!-- Report form -->
        <div
            v-if="showReportForm"
            class="animate-fade-in rounded-3xl border border-rose-100 bg-rose-50/40 p-5 dark:border-rose-950/40 dark:bg-rose-950/10"
        >
            <form @submit.prevent="submitReport" class="flex flex-col gap-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold">Loại sự cố</Label>
                        <select
                            v-model="reportForm.type"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="accident">Tai nạn</option>
                            <option value="food_poisoning">Ngộ độc thực phẩm</option>
                            <option value="fire">Cháy nổ</option>
                            <option value="security">An ninh</option>
                            <option value="equipment_failure">Hỏng thiết bị</option>
                            <option value="theft">Trộm cắp</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold">Mức độ</Label>
                        <select
                            v-model="reportForm.severity"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="low">Thấp</option>
                            <option value="medium">Trung bình</option>
                            <option value="high">Cao</option>
                            <option value="critical">Nghiêm trọng</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold">Thời điểm xảy ra</Label>
                        <Input v-model="reportForm.occurred_at" type="datetime-local" class="h-9" />
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs font-bold">Tiêu đề <span class="text-rose-500">*</span></Label>
                    <Input v-model="reportForm.title" required placeholder="VD: Khách trượt ngã ở khu vực lễ tân" />
                    <p v-if="reportForm.errors.title" class="text-[11px] font-semibold text-rose-500">
                        {{ reportForm.errors.title }}
                    </p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs font-bold">Mô tả chi tiết <span class="text-rose-500">*</span></Label>
                    <textarea
                        v-model="reportForm.description"
                        rows="3"
                        required
                        minlength="10"
                        placeholder="Diễn biến, nguyên nhân ban đầu, phạm vi ảnh hưởng (tối thiểu 10 ký tự)..."
                        class="w-full resize-none rounded-xl border border-slate-200 bg-background px-3 py-2 text-xs focus:outline-none dark:border-slate-800"
                    ></textarea>
                    <p v-if="reportForm.errors.description" class="text-[11px] font-semibold text-rose-500">
                        {{ reportForm.errors.description }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold">Vị trí</Label>
                        <Input v-model="reportForm.location" placeholder="Khu bếp / Sảnh / Kho..." />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold">Số người bị thương</Label>
                        <Input v-model="reportForm.injured_count" type="number" min="0" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold">Ảnh hiện trường</Label>
                        <input
                            type="file"
                            accept="image/*"
                            @input="reportForm.photo = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            class="text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-rose-100 file:px-2 file:py-1.5 file:text-xs file:font-bold file:text-rose-700"
                        />
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs font-bold">Xử lý ngay tại chỗ (nếu có)</Label>
                    <textarea
                        v-model="reportForm.immediate_action"
                        rows="2"
                        placeholder="VD: Đã sơ cứu, ngắt điện khu vực, gọi cấp cứu 115..."
                        class="w-full resize-none rounded-xl border border-slate-200 bg-background px-3 py-2 text-xs focus:outline-none dark:border-slate-800"
                    ></textarea>
                </div>

                <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                    <input v-model="reportForm.needs_shift_cover" type="checkbox" class="rounded" />
                    Cần thay ca gấp (nhân sự không thể tiếp tục làm việc)
                </label>

                <div
                    class="flex items-start gap-2 rounded-xl border border-orange-100 bg-orange-50 p-3 text-[11px] font-semibold text-orange-700 dark:bg-orange-950/20"
                >
                    <ArrowUpCircle class="mt-0.5 size-4 shrink-0" />
                    Sự cố Cháy nổ / Ngộ độc / Tai nạn hoặc mức độ Cao/Nghiêm trọng hoặc có người bị thương sẽ TỰ ĐỘNG báo lên Chủ nhà hàng ngay.
                </div>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="showReportForm = false" class="rounded-xl">Hủy</Button>
                    <Button
                        type="submit"
                        :disabled="reportForm.processing"
                        class="rounded-xl border-0 bg-rose-600 font-bold text-white hover:bg-rose-700"
                    >
                        Ghi nhận sự cố
                    </Button>
                </div>
            </form>
        </div>

        <!-- Filters -->
        <div class="flex gap-2">
            <button
                v-for="f in [
                    { k: 'active', l: 'Đang xử lý' },
                    { k: 'resolved', l: 'Đã đóng' },
                    { k: 'all', l: 'Tất cả' },
                ]"
                :key="f.k"
                @click="activeFilter = f.k as any"
                :class="[
                    'rounded-full px-3.5 py-1.5 text-xs font-bold transition',
                    activeFilter === f.k
                        ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900'
                        : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                ]"
            >
                {{ f.l }}
            </button>
        </div>

        <!-- List -->
        <div v-if="filtered.length === 0" class="rounded-2xl border border-dashed border-slate-200 p-10 text-center text-sm text-slate-400">
            Không có sự cố nào trong nhóm này.
        </div>

        <div v-else class="flex flex-col gap-3">
            <div
                v-for="i in filtered"
                :key="i.id"
                class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <component
                            :is="typeConfig[i.type]?.icon ?? ShieldAlert"
                            :class="['mt-0.5 size-6 shrink-0', typeConfig[i.type]?.cls]"
                        />
                        <div>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="font-bold text-slate-800 dark:text-slate-100">{{ i.title }}</span>
                                <span
                                    :class="['rounded-md border px-1.5 py-0.5 text-[9px] font-extrabold', severityConfig[i.severity]?.cls]"
                                >{{ severityConfig[i.severity]?.label }}</span>
                                <span
                                    v-if="i.escalated"
                                    class="flex items-center gap-0.5 rounded-md bg-rose-100 px-1.5 py-0.5 text-[9px] font-extrabold text-rose-700"
                                ><ArrowUpCircle class="size-3" /> ĐÃ BÁO CHỦ</span>
                                <span
                                    v-if="i.injured_count > 0"
                                    class="rounded-md bg-red-100 px-1.5 py-0.5 text-[9px] font-extrabold text-red-700"
                                >{{ i.injured_count }} người bị thương</span>
                                <span
                                    v-if="i.needs_shift_cover"
                                    class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[9px] font-extrabold text-amber-700"
                                >Cần thay ca</span>
                            </div>
                            <p class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ i.description }}</p>
                            <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-slate-400">
                                <span>{{ typeConfig[i.type]?.label }}</span>
                                <span v-if="i.location">📍 {{ i.location }}</span>
                                <span>🕒 {{ i.occurred_at_display }}</span>
                                <span>👤 {{ i.reported_by_name }}</span>
                                <span v-if="i.branch_name">🏢 {{ i.branch_name }}</span>
                                <span v-if="i.has_photo">📷 có ảnh</span>
                            </div>
                            <p v-if="i.immediate_action" class="mt-1.5 text-[11px] text-slate-500">
                                <span class="font-bold">Xử lý ngay:</span> {{ i.immediate_action }}
                            </p>
                        </div>
                    </div>
                    <span
                        :class="['shrink-0 rounded-lg px-2 py-1 text-[10px] font-extrabold', statusConfig[i.status]?.cls]"
                    >{{ statusConfig[i.status]?.label }}</span>
                </div>

                <!-- Resolution report -->
                <div
                    v-if="i.status === 'resolved' && i.resolution_report"
                    class="mt-3 rounded-xl border border-emerald-100 bg-emerald-50/50 p-3 dark:border-emerald-950/40 dark:bg-emerald-950/10"
                >
                    <div class="flex items-center gap-1.5 text-[10px] font-extrabold tracking-wide text-emerald-700 uppercase">
                        <ClipboardCheck class="size-3.5" /> Báo cáo xử lý
                    </div>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{ i.resolution_report }}</p>
                    <p class="mt-1 text-[10px] text-slate-400">— {{ i.resolved_by_name }} · {{ i.resolved_at_display }}</p>
                </div>

                <!-- Actions -->
                <div
                    v-if="props.canManage && i.status !== 'resolved'"
                    class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3 dark:border-slate-800"
                >
                    <Button
                        v-if="i.status === 'open'"
                        size="sm"
                        variant="outline"
                        @click="doAcknowledge(i)"
                        class="h-8 gap-1.5 rounded-lg text-[11px]"
                    >
                        <ClipboardCheck class="size-3.5" /> Tiếp nhận
                    </Button>
                    <Button
                        v-if="!i.escalated"
                        size="sm"
                        variant="outline"
                        @click="doEscalate(i)"
                        class="h-8 gap-1.5 rounded-lg text-[11px] text-rose-600"
                    >
                        <ArrowUpCircle class="size-3.5" /> Báo Chủ
                    </Button>
                    <Button
                        size="sm"
                        @click="openResolve(i)"
                        class="h-8 gap-1.5 rounded-lg border-0 bg-emerald-600 text-[11px] font-bold text-white hover:bg-emerald-700"
                    >
                        <CheckCircle2 class="size-3.5" /> Đóng sự cố
                    </Button>
                </div>
                <div
                    v-else-if="!props.canManage && i.status !== 'resolved'"
                    class="mt-3 flex items-center gap-1.5 border-t border-slate-100 pt-3 text-[11px] text-slate-400 dark:border-slate-800"
                >
                    <Lock class="size-3.5" /> Chỉ Quản lý/Chủ được tiếp nhận & đóng sự cố.
                </div>
            </div>
        </div>
    </div>

    <!-- RESOLVE MODAL -->
    <div
        v-if="showResolveModal && selected"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
    >
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-3 flex items-center gap-2 border-b pb-3 text-sm font-extrabold tracking-wider text-emerald-600 uppercase">
                <CheckCircle2 class="size-4.5" /> Đóng sự cố kèm báo cáo
            </div>
            <div class="mb-3 rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-800/40">
                <span class="font-bold">{{ selected.title }}</span>
            </div>
            <form @submit.prevent="submitResolve" class="flex flex-col gap-3">
                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs font-bold">Báo cáo xử lý <span class="text-rose-500">*</span></Label>
                    <textarea
                        v-model="resolveForm.resolution_report"
                        rows="5"
                        required
                        minlength="20"
                        placeholder="Nguyên nhân, biện pháp đã thực hiện, kết quả, phòng ngừa tái diễn (tối thiểu 20 ký tự)..."
                        class="w-full resize-none rounded-xl border border-slate-200 bg-background px-3 py-2 text-xs focus:outline-none dark:border-slate-800"
                    ></textarea>
                    <p v-if="resolveForm.errors.resolution_report" class="text-[11px] font-semibold text-rose-500">
                        {{ resolveForm.errors.resolution_report }}
                    </p>
                </div>
                <div class="flex justify-end gap-2 border-t pt-3">
                    <Button type="button" variant="outline" @click="showResolveModal = false" class="rounded-xl text-xs">Hủy</Button>
                    <Button
                        type="submit"
                        :disabled="resolveForm.processing"
                        class="rounded-xl border-0 bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700"
                    >
                        Đóng sự cố
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
