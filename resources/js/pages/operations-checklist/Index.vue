<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Camera,
    CheckCircle2,
    Circle,
    ClipboardCheck,
    Loader2,
    Pencil,
    Plus,
    Settings2,
    Trash2,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOfflineMutationQueue } from '@/composables/useOfflineMutationQueue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    templates: any[];
    completions: Record<string, any>;
    stats: Record<
        number,
        { total: number; completed: number; expected?: number; percent: number }
    >;
    date: string;
    canComplete?: boolean;
    branchContext?: { scope: string; active_branch_id: number | null };
    branchStats?: Record<
        number,
        Array<{
            branch_id: number;
            branch_name: string;
            completed: number;
            total: number;
            percent: number;
        }>
    >;
    branches?: Array<{ id: number; name: string }>;
    canManageTemplates: boolean;
}>();

const page = usePage();
watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    },
);

const completions = ref<Record<string, any>>({ ...props.completions });
const completing = ref<number | null>(null);
const { enqueue, pendingCount, failedCount, retryFailed } =
    useOfflineMutationQueue();

// Inertia keeps this page instance alive when the header scope changes. Reset
// the local optimistic map whenever the server sends a new branch/date.
watch(
    () => [
        props.completions,
        props.branchContext?.scope,
        props.branchContext?.active_branch_id,
        props.date,
    ],
    () => {
        completions.value = { ...props.completions };
    },
    { deep: true },
);

// Type labels
const typeLabel: Record<string, string> = {
    opening: 'Mở cửa',
    closing: 'Đóng cửa',
    attp: 'ATTP / Vệ sinh',
    handover: 'Bàn giao ca',
    custom: 'Tùy chỉnh',
};
const typeColor: Record<string, string> = {
    opening: 'bg-green-100 text-green-800',
    closing: 'bg-blue-100 text-blue-800',
    attp: 'bg-red-100 text-red-800',
    handover: 'bg-purple-100 text-purple-800',
    custom: 'bg-gray-100 text-gray-700',
};

// Photo capture
const photoRef = ref<string | null>(null);
const showPhotoDialog = ref(false);
const photoItemId = ref<number | null>(null);
const videoRef = ref<HTMLVideoElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
let stream: MediaStream | null = null;

async function openCamera(itemId: number) {
    photoItemId.value = itemId;
    showPhotoDialog.value = true;
    photoRef.value = null;
    await new Promise((r) => setTimeout(r, 200));

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
        });

        if (videoRef.value) {
            videoRef.value.srcObject = stream;
        }
    } catch {
        toast.error('Không thể truy cập camera.');
    }
}

function capturePhoto() {
    if (!videoRef.value || !canvasRef.value) {
        return;
    }

    const ctx = canvasRef.value.getContext('2d');
    const maxDimension = 1280;
    const scale = Math.min(
        1,
        maxDimension /
            Math.max(videoRef.value.videoWidth, videoRef.value.videoHeight),
    );
    canvasRef.value.width = Math.round(videoRef.value.videoWidth * scale);
    canvasRef.value.height = Math.round(videoRef.value.videoHeight * scale);
    ctx?.drawImage(videoRef.value, 0, 0);
    photoRef.value = canvasRef.value.toDataURL('image/jpeg', 0.85);
    stream?.getTracks().forEach((t) => t.stop());
}

function closeCamera() {
    stream?.getTracks().forEach((t) => t.stop());
    showPhotoDialog.value = false;
    photoRef.value = null;
}

async function submitPhotoItem() {
    if (!photoItemId.value) {
        return;
    }

    await completeItem(photoItemId.value, photoRef.value);
    closeCamera();
}

// Complete item
async function completeItem(itemId: number, photo: string | null = null) {
    if (props.canComplete === false) {
        toast.error('Hãy chọn một chi nhánh cụ thể để cập nhật checklist.');

        return;
    }

    completing.value = itemId;

    const payload = {
        item_id: itemId,
        photo,
        date: props.date,
        notes: null,
    };

    try {
        const { data } = await axios.post(
            '/operations-checklist/complete',
            payload,
        );

        if (data.success) {
            completions.value[itemId] = data.completion;
            toast.success('Đã hoàn thành!');
        }
    } catch (e: any) {
        if (
            !e.response ||
            (typeof navigator !== 'undefined' && !navigator.onLine)
        ) {
            enqueue('/operations-checklist/complete', payload);
            completions.value[itemId] = { pending_sync: true };
            toast.info('Đã lưu checklist, sẽ tự đồng bộ khi có mạng.');

            return;
        }

        toast.error(e.response?.data?.message ?? 'Có lỗi xảy ra.');
    } finally {
        completing.value = null;
    }
}

async function uncompleteItem(itemId: number) {
    if (props.canComplete === false) {
        toast.error('Hãy chọn một chi nhánh cụ thể để cập nhật checklist.');

        return;
    }

    try {
        await axios.post('/operations-checklist/uncomplete', {
            item_id: itemId,
            date: props.date,
        });
        delete completions.value[itemId];
        toast.success('Đã bỏ đánh dấu.');
    } catch {
        toast.error('Có lỗi.');
    }
}

function isCompleted(itemId: number): boolean {
    return !!completions.value[itemId];
}

function branchProgress(templateId: number) {
    return props.branchStats?.[templateId] ?? [];
}

function completedPercent(templateId: number): number {
    if (props.branchContext?.scope === 'all') {
        return props.stats[templateId]?.percent ?? 0;
    }

    const template = props.templates.find((t) => t.id === templateId);

    if (!template) {
        return 0;
    }

    const total = template.items.length;
    const done = template.items.filter((i: any) => isCompleted(i.id)).length;

    return total > 0 ? Math.round((done / total) * 100) : 0;
}

// Template management
const activeView = ref<'run' | 'manage'>('run');
const showTemplateDialog = ref(false);
const isEditingTemplate = ref(false);
const editingTemplateId = ref<number | null>(null);
const templateForm = useForm<{
    name: string;
    type: string;
    items: Array<{
        id?: number;
        title: string;
        requires_photo: boolean;
    }>;
    branch_ids: number[];
}>({
    name: '',
    type: 'opening' as string,
    items: [{ title: '', requires_photo: false }] as {
        title: string;
        requires_photo: boolean;
    }[],
    branch_ids: [] as number[],
});

function addItem() {
    templateForm.items.push({ title: '', requires_photo: false });
}
function removeItem(idx: number) {
    templateForm.items.splice(idx, 1);
}

function resetTemplateForm() {
    templateForm.reset();
    templateForm.type = 'opening';
    templateForm.items = [{ title: '', requires_photo: false }];
    templateForm.branch_ids = [];
    templateForm.clearErrors();
}

function openCreateDialog() {
    isEditingTemplate.value = false;
    editingTemplateId.value = null;
    resetTemplateForm();
    showTemplateDialog.value = true;
}

function openEditDialog(template: any) {
    isEditingTemplate.value = true;
    editingTemplateId.value = template.id;
    templateForm.name = template.name;
    templateForm.type = template.type;
    templateForm.items = template.items.map((item: any) => ({
        id: item.id,
        title: item.title,
        requires_photo: !!item.requires_photo,
    }));
    templateForm.branch_ids = (template.branches ?? []).map(
        (branch: any) => branch.id,
    );
    templateForm.clearErrors();
    showTemplateDialog.value = true;
}

function closeTemplateDialog() {
    showTemplateDialog.value = false;
    editingTemplateId.value = null;
    templateForm.cancel();
}

function submitTemplate() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            closeTemplateDialog();
            resetTemplateForm();
        },
    };

    if (isEditingTemplate.value && editingTemplateId.value) {
        templateForm.put(
            `/operations-checklist/templates/${editingTemplateId.value}`,
            options,
        );

        return;
    }

    templateForm.post('/operations-checklist/templates', options);
}

function deleteTemplate(template: any) {
    const confirmed = window.confirm(
        `Xóa mẫu "${template.name}"? Các mục checklist và lịch sử hoàn thành của mẫu này cũng sẽ bị xóa.`,
    );

    if (!confirmed) {
        return;
    }

    router.delete(`/operations-checklist/templates/${template.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã xóa mẫu checklist.');
        },
    });
}

function assignedBranchLabel(template: any): string {
    const assigned = template.branches ?? [];

    return assigned.length > 0
        ? assigned.map((branch: any) => branch.name).join(', ')
        : 'Toàn chuỗi';
}

const totalTemplates = computed(() => props.templates.length);

const totalAllItems = computed(() => {
    if (props.branchContext?.scope === 'all') {
        return Object.values(props.stats).reduce(
            (sum, stat) => sum + (stat.expected ?? stat.total),
            0,
        );
    }

    return props.templates.reduce((sum, t) => sum + (t.items?.length ?? 0), 0);
});

const totalCompletedItems = computed(() => {
    if (props.branchContext?.scope === 'all') {
        return Object.values(props.stats).reduce(
            (sum, stat) => sum + stat.completed,
            0,
        );
    }

    return props.templates.reduce((sum, t) => {
        const done = t.items?.filter((i: any) => isCompleted(i.id)).length ?? 0;

        return sum + done;
    }, 0);
});

const overallPercent = computed(() => {
    const total = totalAllItems.value;

    return total > 0
        ? Math.round((totalCompletedItems.value / total) * 100)
        : 0;
});
</script>

<template>
    <Head title="Checklist Vận Hành" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- HEADER -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400"
                >
                    <ClipboardCheck class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Checklist Vận Hành Hàng Ngày
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Ngày:
                        {{
                            new Date(date).toLocaleDateString('vi-VN', {
                                weekday: 'long',
                                day: '2-digit',
                                month: '2-digit',
                                year: 'numeric',
                            })
                        }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <p
                    v-if="props.canComplete === false"
                    class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 dark:bg-amber-950/30 dark:text-amber-300"
                >
                    Đang xem toàn chuỗi — chọn chi nhánh để cập nhật
                </p>
                <button
                    v-if="pendingCount"
                    type="button"
                    class="rounded-lg bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-300"
                    @click="retryFailed"
                >
                    {{ pendingCount }} mục chờ đồng bộ<span v-if="failedCount">
                        · {{ failedCount }} lỗi</span
                    >
                </button>
                <Button
                    v-if="props.canManageTemplates"
                    @click="openCreateDialog"
                    class="flex h-10 items-center gap-1.5 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                >
                    <Plus class="size-4" />
                    Tạo checklist mới
                </Button>
            </div>
        </div>

        <!-- KPI STATS CARDS -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Total Checklists -->
            <Card
                class="shadow-xs transition-transform hover:translate-y-[-2px]"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >Tổng mẫu quy trình</CardDescription
                    >
                    <ClipboardCheck class="size-4 text-slate-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-3xl font-black text-slate-800 dark:text-slate-100"
                        >{{ totalTemplates }}</span
                    >
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        quy trình đang được áp dụng
                    </p>
                </CardContent>
            </Card>

            <!-- Overall Completion Rate -->
            <Card
                class="border-emerald-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-emerald-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-emerald-500 uppercase"
                        >Tiến độ vận hành</CardDescription
                    >
                    <CheckCircle2
                        class="size-4 text-emerald-600 dark:text-emerald-400"
                    />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-3xl font-black text-emerald-600 dark:text-emerald-400"
                        >{{ overallPercent }}%</span
                    >
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        mức độ hoàn thành hôm nay
                    </p>
                </CardContent>
            </Card>

            <!-- Completed Items Ratio -->
            <Card
                class="border-indigo-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-indigo-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-indigo-500 uppercase"
                        >Hạng mục đã làm</CardDescription
                    >
                    <TrendingUp
                        class="size-4 text-indigo-600 dark:text-indigo-400"
                    />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-3xl font-black text-indigo-600 dark:text-indigo-400"
                        >{{ totalCompletedItems }}/{{ totalAllItems }}</span
                    >
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        việc đã được đánh dấu xong
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Checklist workspace navigation -->
        <div class="flex items-center gap-2 border-b border-border pb-2">
            <button
                type="button"
                @click="activeView = 'run'"
                :class="[
                    'border-b-2 px-4 py-2 text-xs font-bold transition focus:outline-none',
                    activeView === 'run'
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                        : 'border-transparent text-muted-foreground hover:text-foreground',
                ]"
            >
                📋 Bảng checklist vận hành
            </button>
            <button
                v-if="props.canManageTemplates"
                type="button"
                @click="activeView = 'manage'"
                :class="[
                    'flex items-center gap-1.5 border-b-2 px-4 py-2 text-xs font-bold transition focus:outline-none',
                    activeView === 'manage'
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                        : 'border-transparent text-muted-foreground hover:text-foreground',
                ]"
            >
                <Settings2 class="size-3.5" /> Quản lý mẫu checklist
            </button>
        </div>

        <!-- Checklist cards -->
        <template v-if="activeView === 'run'">
            <div
                v-for="template in templates"
                :key="template.id"
                class="space-y-4"
            >
                <Card
                    class="overflow-hidden rounded-2xl border border-slate-100 bg-white p-6 shadow-md transition-transform duration-200 hover:translate-y-[-2px] dark:border-slate-800 dark:bg-slate-900/45"
                >
                    <div
                        class="flex flex-col justify-between gap-4 border-b border-border/60 pb-4 sm:flex-row sm:items-center"
                    >
                        <div>
                            <h3
                                class="flex items-center gap-2 text-base font-bold text-slate-800 dark:text-slate-100"
                            >
                                {{ template.name }}
                                <Badge
                                    :class="typeColor[template.type]"
                                    class="text-xs font-semibold"
                                    >{{ typeLabel[template.type] }}</Badge
                                >
                            </h3>
                            <p class="mt-1 text-xs font-medium text-slate-500">
                                {{ completedPercent(template.id) }}% hoàn thành
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <div
                                class="h-2 w-28 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                            >
                                <div
                                    class="h-full bg-indigo-500 transition-all duration-300"
                                    :style="{
                                        width:
                                            completedPercent(template.id) + '%',
                                    }"
                                ></div>
                            </div>
                            <span
                                class="text-xs font-bold"
                                :class="
                                    completedPercent(template.id) === 100
                                        ? 'text-indigo-600 dark:text-indigo-400'
                                        : 'text-slate-500'
                                "
                            >
                                {{
                                    props.branchContext?.scope === 'all'
                                        ? (stats[template.id]?.completed ?? 0)
                                        : template.items.filter((i: any) =>
                                              isCompleted(i.id),
                                          ).length
                                }}/{{
                                    props.branchContext?.scope === 'all'
                                        ? (stats[template.id]?.expected ?? 0)
                                        : template.items.length
                                }}
                            </span>
                        </div>
                    </div>
                    <div
                        v-if="props.branchContext?.scope === 'all'"
                        class="mt-4 overflow-x-auto rounded-xl border border-border/60 bg-muted/10"
                    >
                        <div
                            class="grid min-w-[420px] grid-cols-[minmax(0,1fr)_100px_90px] gap-3 border-b border-border/60 px-3 py-2 text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
                        >
                            <span>Chi nhánh</span>
                            <span class="text-center">Tiến độ</span>
                            <span class="text-right">Tỷ lệ</span>
                        </div>
                        <div
                            v-for="branch in branchProgress(template.id)"
                            :key="`${template.id}-${branch.branch_id}`"
                            class="grid min-w-[420px] grid-cols-[minmax(0,1fr)_100px_90px] items-center gap-3 px-3 py-2 text-xs"
                        >
                            <span
                                class="truncate font-semibold text-foreground"
                                >{{ branch.branch_name }}</span
                            >
                            <span class="text-center text-muted-foreground"
                                >{{ branch.completed }}/{{ branch.total }}</span
                            >
                            <span class="text-right font-bold text-indigo-400"
                                >{{ branch.percent }}%</span
                            >
                        </div>
                    </div>
                    <CardContent class="divide-y divide-border/60 p-0 pt-2">
                        <div
                            v-for="item in template.items"
                            :key="item.id"
                            class="flex items-center gap-3 py-3.5 transition-colors hover:bg-muted/15"
                        >
                            <!-- Checkbox -->
                            <button
                                v-if="
                                    props.branchContext?.scope !== 'all' &&
                                    !isCompleted(item.id)
                                "
                                @click="
                                    item.requires_photo
                                        ? openCamera(item.id)
                                        : completeItem(item.id)
                                "
                                :disabled="
                                    completing === item.id ||
                                    props.canComplete === false
                                "
                                class="shrink-0 focus:outline-none"
                            >
                                <Loader2
                                    v-if="completing === item.id"
                                    class="size-5 animate-spin text-muted-foreground"
                                />
                                <Circle
                                    v-else
                                    class="size-5 text-slate-400 transition-colors hover:text-indigo-500"
                                />
                            </button>
                            <button
                                v-else-if="props.branchContext?.scope !== 'all'"
                                @click="uncompleteItem(item.id)"
                                :disabled="props.canComplete === false"
                                class="shrink-0 focus:outline-none"
                            >
                                <CheckCircle2 class="size-5 text-indigo-500" />
                            </button>
                            <span
                                v-else
                                class="flex size-5 shrink-0 items-center justify-center rounded-full border border-slate-500/40 text-[10px] text-slate-400"
                                title="Chế độ toàn chuỗi chỉ xem"
                            >
                                •
                            </span>

                            <!-- Title -->
                            <div class="min-w-0 flex-1">
                                <p
                                    :class="[
                                        'text-xs',
                                        isCompleted(item.id)
                                            ? 'text-muted-foreground line-through'
                                            : 'font-bold text-slate-700 dark:text-slate-200',
                                    ]"
                                >
                                    {{ item.title }}
                                </p>
                                <p
                                    v-if="
                                        isCompleted(item.id) &&
                                        completions[item.id]
                                    "
                                    class="mt-0.5 text-[10px] text-muted-foreground"
                                >
                                    ✔️
                                    {{
                                        completions[item.id].completed_by?.name
                                    }}
                                    lúc
                                    {{
                                        new Date(
                                            completions[item.id].completed_at,
                                        ).toLocaleTimeString('vi-VN', {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })
                                    }}
                                </p>
                            </div>

                            <!-- Photo badge -->
                            <Badge
                                v-if="item.requires_photo"
                                variant="outline"
                                class="shrink-0 gap-1 bg-slate-50 px-1.5 py-0 text-[10px] dark:bg-slate-800"
                            >
                                <Camera class="size-3" /> Ảnh
                            </Badge>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <p
                v-if="!templates.length"
                class="py-16 text-center text-xs font-semibold text-muted-foreground"
            >
                Chưa có checklist nào. Nhấn "Tạo checklist" để bắt đầu.
            </p>
        </template>

        <!-- Owner/manager template management -->
        <template
            v-else-if="activeView === 'manage' && props.canManageTemplates"
        >
            <Card class="overflow-hidden border-border/80">
                <CardHeader class="border-b border-border bg-muted/20 py-5">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2
                                class="flex items-center gap-2 text-base font-bold text-foreground"
                            >
                                <Settings2 class="size-4 text-indigo-400" />
                                Quản lý mẫu checklist
                            </h2>
                            <CardDescription class="mt-1 text-xs">
                                Tổ chức quy trình theo loại và chi nhánh; chỉnh
                                sửa sẽ giữ nguyên các mục đang có để không làm
                                mất dấu vết vận hành.
                            </CardDescription>
                        </div>
                        <Button
                            @click="openCreateDialog"
                            class="h-9 gap-1.5 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-500"
                        >
                            <Plus class="size-3.5" /> Thêm mẫu
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div
                        v-for="template in templates"
                        :key="`manage-${template.id}`"
                        class="flex flex-col gap-4 border-b border-border p-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between sm:p-5"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-300"
                            >
                                <ClipboardCheck class="size-5" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3
                                        class="truncate text-sm font-bold text-foreground"
                                    >
                                        {{ template.name }}
                                    </h3>
                                    <Badge
                                        :class="typeColor[template.type]"
                                        class="text-[10px] font-semibold"
                                    >
                                        {{ typeLabel[template.type] }}
                                    </Badge>
                                </div>
                                <p
                                    class="mt-1 text-[11px] text-muted-foreground"
                                >
                                    {{ template.items.length }} mục ·
                                    {{ assignedBranchLabel(template) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 gap-1.5 text-xs text-indigo-600 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-indigo-950/30"
                                @click="openEditDialog(template)"
                            >
                                <Pencil class="size-3.5" /> Sửa
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 gap-1.5 border-rose-200 text-xs text-rose-600 hover:bg-rose-50 dark:border-rose-900/50 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                @click="deleteTemplate(template)"
                            >
                                <Trash2 class="size-3.5" /> Xóa
                            </Button>
                        </div>
                    </div>
                    <div
                        v-if="!templates.length"
                        class="p-12 text-center text-xs text-muted-foreground"
                    >
                        Chưa có mẫu checklist để quản lý.
                    </div>
                </CardContent>
            </Card>
        </template>
    </div>

    <!-- Photo capture dialog -->
    <Dialog v-model:open="showPhotoDialog">
        <DialogContent class="max-w-sm">
            <DialogHeader
                ><DialogTitle>Chụp ảnh xác nhận</DialogTitle></DialogHeader
            >
            <div class="space-y-3">
                <video
                    v-if="!photoRef"
                    ref="videoRef"
                    autoplay
                    playsinline
                    class="aspect-[4/3] w-full rounded-lg bg-black"
                ></video>
                <img v-else :src="photoRef" class="w-full rounded-lg" />
                <canvas ref="canvasRef" class="hidden"></canvas>
            </div>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="closeCamera">Hủy</Button>
                <Button v-if="!photoRef" @click="capturePhoto" class="gap-1.5"
                    ><Camera class="size-4" /> Chụp</Button
                >
                <Button v-else @click="submitPhotoItem" class="gap-1.5"
                    ><CheckCircle2 class="size-4" /> Xác nhận</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Create template dialog -->
    <Dialog v-model:open="showTemplateDialog">
        <DialogContent class="max-h-[80vh] max-w-lg overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{
                    isEditingTemplate
                        ? 'Sửa mẫu checklist'
                        : 'Tạo Checklist mới'
                }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitTemplate" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Tên checklist</Label>
                        <Input
                            v-model="templateForm.name"
                            placeholder="Checklist mở cửa"
                            required
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Loại</Label>
                        <select
                            v-model="templateForm.type"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="opening">Mở cửa</option>
                            <option value="closing">Đóng cửa</option>
                            <option value="attp">ATTP / Vệ sinh</option>
                            <option value="handover">Bàn giao ca</option>
                            <option value="custom">Tùy chỉnh</option>
                        </select>
                    </div>
                </div>

                <!-- Áp dụng cho chi nhánh (bỏ trống = toàn chuỗi) -->
                <div v-if="(props.branches?.length ?? 0) > 0" class="space-y-2">
                    <Label
                        >Áp dụng cho chi nhánh
                        <span class="text-xs font-normal text-muted-foreground"
                            >(bỏ trống = toàn chuỗi)</span
                        ></Label
                    >
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="br in props.branches"
                            :key="br.id"
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-border px-2.5 py-1 text-xs"
                        >
                            <input
                                type="checkbox"
                                :value="br.id"
                                v-model="templateForm.branch_ids"
                                class="rounded"
                            />
                            {{ br.name }}
                        </label>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label>Các mục kiểm tra</Label>
                    <div
                        v-for="(item, idx) in templateForm.items"
                        :key="idx"
                        class="flex items-center gap-2"
                    >
                        <Input
                            v-model="item.title"
                            :placeholder="'Mục ' + (idx + 1)"
                            class="flex-1"
                            required
                        />
                        <label
                            class="flex cursor-pointer items-center gap-1 text-xs whitespace-nowrap"
                        >
                            <input
                                type="checkbox"
                                v-model="item.requires_photo"
                                class="rounded"
                            />
                            <Camera class="size-3" />
                        </label>
                        <Button
                            v-if="templateForm.items.length > 1"
                            variant="ghost"
                            size="sm"
                            class="shrink-0 text-red-500"
                            type="button"
                            @click="removeItem(idx)"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        type="button"
                        @click="addItem"
                        class="w-full gap-1.5"
                    >
                        <Plus class="size-3.5" /> Thêm mục
                    </Button>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="closeTemplateDialog"
                        >Hủy</Button
                    >
                    <Button type="submit" :disabled="templateForm.processing">
                        {{
                            templateForm.processing
                                ? isEditingTemplate
                                    ? 'Đang lưu...'
                                    : 'Đang tạo...'
                                : isEditingTemplate
                                  ? 'Lưu thay đổi'
                                  : 'Tạo checklist'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
