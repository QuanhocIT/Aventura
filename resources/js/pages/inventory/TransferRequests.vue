<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Check,
    CheckCircle2,
    Clock3,
    FileText,
    Info,
    PackagePlus,
    Plus,
    Send,
    Truck,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type RequestStatus =
    | 'requested'
    | 'routed'
    | 'dispatched'
    | 'received'
    | 'discrepancy'
    | 'quarantined'
    | 'return_requested'
    | 'returned'
    | 'destroyed'
    | 'rejected'
    | 'cancelled';

interface TransferRequest {
    id: number;
    status: RequestStatus;
    priority: 'normal' | 'urgent';
    ingredient: string | null;
    unit: string;
    to_branch: string | null;
    quantity_requested: number;
    quantity_dispatched: number | null;
    quantity_received: number | null;
    reason: string;
    owner_note: string | null;
    reject_reason: string | null;
    cancel_reason: string | null;
    created_at: string;
    routed_at: string | null;
    dispatched_at: string | null;
    received_at: string | null;
    can_cancel: boolean;
}

interface Branch {
    id: number;
    name: string;
}

interface IngredientOption {
    id: number;
    name: string;
    branch_id: number | null;
    unit: string;
    available_quantity: number | null;
}

const props = defineProps<{
    transfers: TransferRequest[];
    branches: Branch[];
    ingredients: IngredientOption[];
    permissions: {
        can_create: boolean;
        request_only: boolean;
    };
    summary: {
        requested: number;
        routed: number;
        dispatched: number;
        discrepancy: number;
        completed: number;
    };
}>();

const showAll = ref(false);
const cancelling = ref<TransferRequest | null>(null);
const hasAssignedBranch = computed(() => props.branches.length > 0);

const requestForm = useForm({
    to_branch_id: props.branches[0]?.id ?? ('' as number | ''),
    ingredient_id: '' as number | '',
    quantity_requested: 0,
    priority: 'urgent' as 'normal' | 'urgent',
    reason: '',
});

const cancelForm = useForm({ cancel_reason: '' });

const selectedIngredient = computed(() =>
    availableIngredients.value.find(
        (ingredient) =>
            Number(ingredient.id) === Number(requestForm.ingredient_id),
    ),
);

const availableIngredients = computed(() =>
    props.ingredients.filter(
        (ingredient) =>
            ingredient.branch_id === null ||
            Number(ingredient.branch_id) === Number(requestForm.to_branch_id),
    ),
);

const visibleRequests = computed(() =>
    showAll.value ? props.transfers : props.transfers.slice(0, 10),
);

const inProgressCount = computed(
    () =>
        props.summary.routed +
        props.summary.dispatched +
        props.summary.discrepancy,
);

const statusMeta: Record<
    RequestStatus,
    { label: string; className: string; icon: typeof Clock3 }
> = {
    requested: {
        label: 'Chờ Chủ doanh nghiệp xem xét',
        className: 'border-amber-400/30 bg-amber-500/10 text-amber-300',
        icon: Clock3,
    },
    routed: {
        label: 'Đã được điều phối',
        className: 'border-indigo-400/30 bg-indigo-500/10 text-indigo-300',
        icon: Check,
    },
    dispatched: {
        label: 'Đang vận chuyển',
        className: 'border-violet-400/30 bg-violet-500/10 text-violet-300',
        icon: Truck,
    },
    received: {
        label: 'Đã nhận hàng',
        className: 'border-emerald-400/30 bg-emerald-500/10 text-emerald-300',
        icon: CheckCircle2,
    },
    discrepancy: {
        label: 'Chờ Chủ doanh nghiệp xử lý chênh lệch',
        className: 'border-rose-400/30 bg-rose-500/10 text-rose-300',
        icon: AlertTriangle,
    },
    quarantined: {
        label: 'Hàng đang cách ly',
        className: 'border-orange-400/30 bg-orange-500/10 text-orange-300',
        icon: AlertTriangle,
    },
    return_requested: {
        label: 'Đang xử lý hoàn trả',
        className: 'border-fuchsia-400/30 bg-fuchsia-500/10 text-fuchsia-300',
        icon: Truck,
    },
    returned: {
        label: 'Đã hoàn trả',
        className: 'border-cyan-400/30 bg-cyan-500/10 text-cyan-300',
        icon: CheckCircle2,
    },
    destroyed: {
        label: 'Đã tiêu hủy',
        className: 'border-slate-400/30 bg-slate-500/10 text-slate-400',
        icon: FileText,
    },
    rejected: {
        label: 'Đã từ chối',
        className: 'border-rose-400/30 bg-rose-500/10 text-rose-300',
        icon: X,
    },
    cancelled: {
        label: 'Đã hủy',
        className: 'border-slate-400/30 bg-slate-500/10 text-slate-400',
        icon: X,
    },
};

const workflowSteps = [
    'Gửi yêu cầu',
    'Chủ doanh nghiệp xem xét',
    'Kho thực hiện điều phối',
    'Hoàn tất bổ sung',
];

const statusInfo = (status: RequestStatus) => statusMeta[status];

const progressIndex = (status: RequestStatus) => {
    if (status === 'requested') {
        return 0;
    }

    if (status === 'routed') {
        return 1;
    }

    if (status === 'dispatched') {
        return 2;
    }

    if (status === 'received') {
        return 3;
    }

    return status === 'discrepancy' || status === 'quarantined' ? 2 : -1;
};

const formatNumber = (value: number | null) =>
    value === null
        ? '—'
        : new Intl.NumberFormat('vi-VN', {
              maximumFractionDigits: 3,
          }).format(value);

const openCancel = (request: TransferRequest) => {
    cancelling.value = request;
    cancelForm.reset();
};

const submitRequest = () => {
    if (requestForm.processing) {
        return;
    }

    requestForm.post('/inventory/transfers', {
        preserveScroll: true,
        onSuccess: () => {
            requestForm.reset();
            requestForm.to_branch_id = props.branches[0]?.id ?? '';
            requestForm.priority = 'urgent';
        },
    });
};

const submitCancel = () => {
    if (!cancelling.value || cancelForm.processing) {
        return;
    }

    cancelForm.post(`/inventory/transfers/${cancelling.value.id}/cancel`, {
        preserveScroll: true,
        onSuccess: () => {
            cancelling.value = null;
            cancelForm.reset();
        },
    });
};
</script>

<template>
    <Head title="Xin điều chuyển kho" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
        <!-- Header -->
        <section class="flex flex-col gap-4 rounded-2xl border border-border/70 bg-card p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div class="flex items-start gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-border/70 bg-primary/10 text-primary">
                    <PackagePlus class="size-6" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                        Xin điều chuyển kho
                    </h1>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-muted-foreground">
                        Gửi đề nghị bổ sung nguyên liệu cho chi nhánh. Chủ doanh nghiệp sẽ xem xét nguồn cấp và điều phối phiếu điều chuyển.
                    </p>
                </div>
            </div>
            <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-xs text-amber-600 dark:text-amber-400 lg:max-w-xs">
                <div class="flex items-start gap-2">
                    <Info class="mt-0.5 size-4 shrink-0" />
                    <p>Quản lý không tự chọn kho nguồn và không được tự trừ tồn kho.</p>
                </div>
            </div>
        </section>

        <!-- Summary KPI Cards -->
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-border/70 bg-card p-4 shadow-sm">
                <p class="text-xs font-medium text-muted-foreground">Chờ xem xét</p>
                <p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ props.summary.requested }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Đang chờ Chủ doanh nghiệp phản hồi</p>
            </div>
            <div class="rounded-xl border border-border/70 bg-card p-4 shadow-sm">
                <p class="text-xs font-medium text-muted-foreground">Đang xử lý</p>
                <p class="mt-2 text-2xl font-bold text-foreground">{{ inProgressCount }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Đã điều phối hoặc đang giao</p>
            </div>
            <div class="rounded-xl border border-border/70 bg-card p-4 shadow-sm">
                <p class="text-xs font-medium text-muted-foreground">Đã hoàn tất</p>
                <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ props.summary.completed }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Đã được ghi nhận vào kho</p>
            </div>
        </section>

        <!-- Main Workspace -->
        <section class="grid items-start gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(300px,0.9fr)]">
            <!-- Form Card -->
            <article class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm sm:p-6">
                <div class="flex items-start gap-3 border-b border-border/70 pb-4">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Plus class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-foreground">Tạo yêu cầu bổ sung</h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Thông tin này sẽ được gửi trực tiếp đến hàng chờ của Chủ doanh nghiệp.
                        </p>
                    </div>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="submitRequest">
                    <div class="rounded-xl border border-border/70 bg-muted/40 p-3.5">
                        <p class="text-xs font-semibold text-muted-foreground">Chi nhánh yêu cầu</p>
                        <p class="mt-1 font-bold text-foreground">{{ props.branches[0]?.name || 'Chưa được phân công chi nhánh' }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Chi nhánh được xác định theo tài khoản Quản lý.</p>
                        <p v-if="!hasAssignedBranch" class="mt-2 text-xs font-semibold text-rose-500">Tài khoản chưa được gán chi nhánh nên chưa thể gửi yêu cầu.</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label>Nguyên liệu cần bổ sung</Label>
                        <select
                            v-model="requestForm.ingredient_id"
                            required
                            :disabled="!hasAssignedBranch"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                        >
                            <option value="" disabled>— Chọn nguyên liệu —</option>
                            <option v-for="ingredient in availableIngredients" :key="ingredient.id" :value="ingredient.id">
                                {{ ingredient.name }} · tồn hiện tại {{ formatNumber(ingredient.available_quantity) }} {{ ingredient.unit }}
                            </option>
                        </select>
                        <p v-if="requestForm.errors.ingredient_id" class="text-xs text-rose-500">{{ requestForm.errors.ingredient_id }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label>Số lượng đề nghị</Label>
                            <Input v-model="requestForm.quantity_requested" type="number" step="0.001" min="0.001" required :disabled="!hasAssignedBranch" />
                            <p v-if="selectedIngredient" class="text-[11px] text-muted-foreground">Đơn vị: {{ selectedIngredient.unit }}</p>
                            <p v-if="requestForm.errors.quantity_requested" class="text-xs text-rose-500">{{ requestForm.errors.quantity_requested }}</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>Mức độ ưu tiên</Label>
                            <select v-model="requestForm.priority" required :disabled="!hasAssignedBranch" class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground">
                                <option value="urgent">Khẩn cấp — cần bổ sung sớm</option>
                                <option value="normal">Thông thường</option>
                            </select>
                            <p class="text-[11px] text-muted-foreground">Khẩn cấp sẽ được đưa lên đầu hàng chờ Chủ doanh nghiệp.</p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label>Lý do phát sinh và ảnh hưởng vận hành</Label>
                        <textarea
                            v-model="requestForm.reason"
                            required
                            :disabled="!hasAssignedBranch"
                            minlength="5"
                            maxlength="500"
                            rows="4"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground outline-none focus:ring-2 focus:ring-primary"
                            placeholder="Ví dụ: nguyên liệu hết nhanh bất ngờ do đơn tăng đột biến, hỏng hàng hoặc sai lệch kiểm kê..."
                        />
                        <p class="text-[11px] text-muted-foreground">Ghi rõ nguyên nhân để Chủ doanh nghiệp quyết định số lượng và nguồn cấp phù hợp.</p>
                        <p v-if="requestForm.errors.reason" class="text-xs text-rose-500">{{ requestForm.errors.reason }}</p>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-border/70 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-muted-foreground">Gửi yêu cầu không làm thay đổi tồn kho.</p>
                        <Button type="submit" :disabled="requestForm.processing || !props.permissions.can_create || !hasAssignedBranch" class="gap-2 font-bold shadow-sm">
                            <Send class="size-4" /> Gửi Chủ doanh nghiệp
                        </Button>
                    </div>
                </form>
            </article>

            <!-- Guide Sidebar -->
            <aside class="space-y-5">
                <article class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm">
                    <div class="flex items-center gap-2">
                        <ArrowRight class="size-5 text-primary" />
                        <h2 class="font-bold text-foreground">Yêu cầu được xử lý thế nào?</h2>
                    </div>
                    <div class="mt-5 space-y-4">
                        <div v-for="(step, index) in workflowSteps" :key="step" class="flex items-start gap-3">
                            <div class="flex size-7 shrink-0 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-xs font-bold text-primary">{{ index + 1 }}</div>
                            <div>
                                <p class="text-sm font-semibold text-foreground">{{ step }}</p>
                                <p v-if="index === 0" class="mt-0.5 text-xs text-muted-foreground">Quản lý nêu rõ nguyên nhân, số lượng và mức độ ưu tiên.</p>
                                <p v-else-if="index === 1" class="mt-0.5 text-xs text-muted-foreground">Chủ doanh nghiệp quyết định có điều chuyển hay từ chối.</p>
                                <p v-else-if="index === 2" class="mt-0.5 text-xs text-muted-foreground">Kho được chọn thực hiện xuất, bàn giao và cập nhật chứng từ.</p>
                                <p v-else class="mt-0.5 text-xs text-muted-foreground">Phiếu chỉ hoàn tất sau khi giao nhận được ghi nhận.</p>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-5">
                    <div class="flex items-start gap-3">
                        <AlertTriangle class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div>
                            <h2 class="font-bold text-amber-700 dark:text-amber-300">Lưu ý nghiệp vụ</h2>
                            <p class="mt-1 text-xs leading-5 text-amber-800 dark:text-amber-200">
                                Không tạo yêu cầu trùng khi phiếu cũ vẫn đang xử lý. Nếu tình hình thay đổi, hãy hủy phiếu đang chờ và tạo phiếu mới với lý do cập nhật.
                            </p>
                        </div>
                    </div>
                </article>
            </aside>
        </section>

        <!-- Track Sent Requests -->
        <section class="rounded-2xl border border-border/70 bg-card p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 border-b border-border/70 pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-foreground">Yêu cầu đã gửi</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">Chỉ hiển thị các yêu cầu do tài khoản của bạn tạo.</p>
                </div>
                <span class="rounded-full border border-border/70 bg-muted/40 px-3 py-1 text-xs font-semibold text-muted-foreground">{{ props.transfers.length }} yêu cầu</span>
            </div>

            <div v-if="visibleRequests.length" class="mt-4 space-y-3">
                <article v-for="request in visibleRequests" :key="request.id" class="rounded-xl border border-border/70 bg-background p-4 shadow-sm">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-[11px] font-semibold text-muted-foreground">YC-{{ String(request.id).padStart(5, '0') }}</span>
                                <h3 class="text-base font-bold text-foreground">{{ request.ingredient }}</h3>
                                <span v-if="request.priority === 'urgent'" class="rounded-full border border-orange-500/20 bg-orange-500/10 px-2 py-0.5 text-[10px] font-bold text-orange-600 dark:text-orange-400">Khẩn cấp</span>
                                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-bold" :class="statusInfo(request.status).className">
                                    <component :is="statusInfo(request.status).icon" class="size-3" /> {{ statusInfo(request.status).label }}
                                </span>
                            </div>
                            <div class="mt-3 grid gap-3 text-xs text-muted-foreground sm:grid-cols-3">
                                <div><p class="text-[10px] font-semibold text-muted-foreground uppercase">Chi nhánh</p><p class="mt-0.5 font-medium text-foreground">{{ request.to_branch }}</p></div>
                                <div><p class="text-[10px] font-semibold text-muted-foreground uppercase">Số lượng đề nghị</p><p class="mt-0.5 font-medium text-foreground">{{ formatNumber(request.quantity_requested) }} {{ request.unit }}</p></div>
                                <div><p class="text-[10px] font-semibold text-muted-foreground uppercase">Ngày gửi</p><p class="mt-0.5 font-medium text-foreground">{{ request.created_at }}</p></div>
                            </div>
                            <p class="mt-3 rounded-lg bg-muted/40 px-3 py-2 text-xs leading-5 text-muted-foreground"><b class="text-foreground">Lý do:</b> {{ request.reason }}</p>
                            <p v-if="request.owner_note" class="mt-2 text-xs text-primary font-medium"><b>Phản hồi điều phối:</b> {{ request.owner_note }}</p>
                            <p v-if="request.reject_reason" class="mt-2 text-xs text-rose-500 font-medium"><b>Lý do từ chối:</b> {{ request.reject_reason }}</p>
                            <p v-if="request.cancel_reason" class="mt-2 text-xs text-rose-500 font-medium"><b>Lý do hủy:</b> {{ request.cancel_reason }}</p>
                        </div>
                        <Button v-if="request.can_cancel" type="button" variant="outline" size="sm" class="shrink-0 gap-1.5 text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/30" @click="openCancel(request)">
                            <X class="size-3.5" /> Hủy yêu cầu
                        </Button>
                    </div>

                    <div v-if="['requested', 'routed', 'dispatched', 'received'].includes(request.status)" class="mt-4 flex items-center gap-1 border-t border-border/70 pt-4">
                        <template v-for="(step, index) in workflowSteps" :key="step">
                            <div class="flex min-w-0 items-center gap-1 text-[10px] font-bold" :class="index <= progressIndex(request.status) ? 'text-primary' : 'text-muted-foreground/60'">
                                <Check v-if="index <= progressIndex(request.status)" class="size-3 shrink-0" />
                                <Clock3 v-else class="size-3 shrink-0" />
                                <span class="hidden truncate sm:inline">{{ step }}</span>
                            </div>
                            <span v-if="index < workflowSteps.length - 1" class="h-px min-w-2 flex-1 bg-border/70" :class="index < progressIndex(request.status) ? 'bg-primary/50' : ''"></span>
                        </template>
                    </div>
                </article>
            </div>

            <div v-else class="mt-4 rounded-xl border border-dashed border-border/70 bg-muted/20 p-10 text-center">
                <FileText class="mx-auto size-9 text-muted-foreground/60" />
                <h3 class="mt-3 font-semibold text-foreground">Chưa có yêu cầu nào</h3>
                <p class="mt-1 text-xs text-muted-foreground">Tạo yêu cầu đầu tiên khi chi nhánh phát sinh nhu cầu bổ sung đột xuất.</p>
            </div>

            <div v-if="props.transfers.length > 10" class="mt-4 flex justify-center">
                <Button type="button" variant="outline" size="sm" @click="showAll = !showAll">{{ showAll ? 'Thu gọn danh sách' : 'Xem tất cả yêu cầu' }}</Button>
            </div>
        </section>
    </div>

    <Teleport to="body">
        <div v-if="cancelling" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm" @click.self="cancelling = null">
            <div class="w-full max-w-md rounded-2xl border border-border/70 bg-background p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold text-rose-500">Kiểm soát yêu cầu</p>
                        <h2 class="mt-1 text-lg font-bold text-foreground">Hủy yêu cầu bổ sung?</h2>
                        <p class="mt-1 text-xs text-muted-foreground">{{ cancelling.ingredient }} · {{ formatNumber(cancelling.quantity_requested) }} {{ cancelling.unit }}</p>
                    </div>
                    <Button variant="ghost" size="icon" @click="cancelling = null"><X class="size-4" /></Button>
                </div>
                <form class="mt-5 space-y-4" @submit.prevent="submitCancel">
                    <div class="flex flex-col gap-1.5">
                        <Label>Lý do hủy</Label>
                        <textarea v-model="cancelForm.cancel_reason" required minlength="5" maxlength="500" rows="4" class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary" placeholder="Ví dụ: chi nhánh đã tự cân đối được tồn trong ngày..." />
                        <p v-if="cancelForm.errors.cancel_reason" class="text-xs text-rose-500">{{ cancelForm.errors.cancel_reason }}</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="cancelling = null">Quay lại</Button>
                        <Button type="submit" :disabled="cancelForm.processing" class="bg-rose-600 font-bold text-white hover:bg-rose-700">Xác nhận hủy</Button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
