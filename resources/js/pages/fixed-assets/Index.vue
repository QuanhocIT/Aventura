<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Boxes,
    CheckCircle2,
    ClipboardCheck,
    FileCheck2,
    Info,
    Plus,
    SearchCheck,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Manager = {
    id: number;
    name: string;
    email: string;
    branch_id: number | null;
};

type Branch = {
    id: number;
    name: string;
    manager: { id: number; name: string; email: string } | null;
};

type Handover = {
    id: number;
    handover_code: string;
    status: string;
    account_payable_id: number | null;
    disposed_at: string | null;
    handover_date: string;
    branch_name: string | null;
    handed_over_by: { id: number; name: string } | null;
    to_user: { id: number; name: string; email: string } | null;
    condition_at_handover: string;
    custody_location: string | null;
    notes: string | null;
    rejection_reason: string | null;
    accepted_at: string | null;
};

type Inspection = {
    id: number;
    inspection_code: string;
    inspection_type: string;
    inspected_at: string;
    condition_status: string;
    result: string;
    score: number | null;
    findings: string;
    action_required: string | null;
    inspector_name: string | null;
    evidence_url: string | null;
};

type Asset = {
    id: number;
    asset_code: string;
    name: string;
    category: string | null;
    brand: string | null;
    model: string | null;
    quantity: number;
    unit: string;
    serial_number: string | null;
    branch_id: number | null;
    branch_name: string | null;
    purchase_date: string | null;
    cost: number;
    unit_cost: number;
    supplier: string | null;
    invoice_number: string | null;
    warranty_until: string | null;
    specifications: string | null;
    notes: string | null;
    status: string;
    custody_status: string;
    condition_status: string;
    custody_location: string | null;
    last_inspected_at: string | null;
    custodian: { id: number; name: string; email: string } | null;
    latest_handover: Handover | null;
    latest_inspection: Inspection | null;
    can_accept_handover: boolean;
    handover_evidence_url: string | null;
};

const props = defineProps<{
    assets: { data: Asset[]; total: number };
    branches: Branch[];
    managers: Manager[];
    stats: {
        total: number;
        pending_handover: number;
        assigned: number;
        attention: number;
        unassessed: number;
    };
    permissions: {
        canManageAssets: boolean;
        canHandover: boolean;
        canAcceptHandover: boolean;
        canInspect: boolean;
        canViewAll: boolean;
    };
}>();

const showAssetForm = ref(false);
const selectedAsset = ref<Asset | null>(null);
const modal = ref<
    'details' | 'edit' | 'handover' | 'inspection' | 'dispose' | null
>(null);
const today = new Date().toISOString().slice(0, 10);

const assetForm = useForm({
    asset_code: '',
    name: '',
    category: '',
    brand: '',
    model: '',
    quantity: 1,
    unit: 'cái',
    serial_number: '',
    branch_id: (props.branches[0]?.id ?? '') as number | '',
    purchase_date: today,
    cost: 0,
    unit_cost: null as number | null,
    supplier: '',
    invoice_number: '',
    warranty_until: '',
    specifications: '',
    notes: '',
});

const assetDetailForm = useForm({
    name: '',
    category: '',
    brand: '',
    model: '',
    quantity: 1,
    unit: 'cái',
    serial_number: '',
    unit_cost: null as number | null,
    supplier: '',
    invoice_number: '',
    warranty_until: '',
    specifications: '',
    notes: '',
});

const handoverForm = useForm({
    branch_id: '' as number | '',
    to_user_id: '' as number | '',
    handover_date: today,
    condition_at_handover: 'good',
    custody_location: '',
    notes: '',
    evidence: null as File | null,
});

const inspectionForm = useForm({
    fixed_asset_handover_id: '' as number | '',
    inspection_type: 'routine',
    inspected_at: today,
    condition_status: 'good',
    result: 'pass',
    score: 100 as number | '',
    findings: '',
    action_required: '',
    evidence: null as File | null,
});

const disposeForm = useForm({
    disposed_at: today,
    disposal_proceeds: 0,
    reason: '',
    payment_method: 'bank_transfer',
});

const managersForBranch = computed(() =>
    props.managers.filter(
        (manager) =>
            Number(manager.branch_id) === Number(handoverForm.branch_id),
    ),
);

const statusMeta: Record<string, { label: string; className: string }> = {
    unassigned: {
        label: 'Chưa bàn giao',
        className: 'bg-slate-500/10 text-slate-600 ring-slate-500/20',
    },
    pending_handover: {
        label: 'Chờ xác nhận',
        className: 'bg-amber-500/10 text-amber-600 ring-amber-500/20',
    },
    assigned: {
        label: 'Đang quản lý',
        className: 'bg-emerald-500/10 text-emerald-600 ring-emerald-500/20',
    },
    attention: {
        label: 'Cần xử lý',
        className: 'bg-rose-500/10 text-rose-600 ring-rose-500/20',
    },
};

const conditionMeta: Record<string, { label: string; className: string }> = {
    unassessed: { label: 'Chưa đánh giá', className: 'text-muted-foreground' },
    good: { label: 'Tốt', className: 'text-emerald-600' },
    minor_issue: { label: 'Lỗi nhẹ', className: 'text-amber-600' },
    major_issue: { label: 'Lỗi nghiêm trọng', className: 'text-rose-600' },
    missing: { label: 'Không tìm thấy', className: 'text-rose-600' },
};

const resultMeta: Record<string, { label: string; className: string }> = {
    pass: {
        label: 'Đạt',
        className: 'bg-emerald-500/10 text-emerald-600 ring-emerald-500/20',
    },
    needs_action: {
        label: 'Cần khắc phục',
        className: 'bg-amber-500/10 text-amber-600 ring-amber-500/20',
    },
    fail: {
        label: 'Không đạt',
        className: 'bg-rose-500/10 text-rose-600 ring-rose-500/20',
    },
};

function money(value: number): string {
    return new Intl.NumberFormat('vi-VN').format(value) + ' đ';
}

function statusFor(status: string) {
    return statusMeta[status] ?? statusMeta.unassigned;
}

function conditionFor(condition: string) {
    return conditionMeta[condition] ?? conditionMeta.unassessed;
}

function resultFor(result: string) {
    return resultMeta[result] ?? resultMeta.needs_action;
}

function openDetails(asset: Asset) {
    selectedAsset.value = asset;
    modal.value = 'details';
}

function openEdit(asset: Asset) {
    selectedAsset.value = asset;
    modal.value = 'edit';
    assetDetailForm.reset();
    assetDetailForm.name = asset.name;
    assetDetailForm.category = asset.category ?? '';
    assetDetailForm.brand = asset.brand ?? '';
    assetDetailForm.model = asset.model ?? '';
    assetDetailForm.quantity = asset.quantity;
    assetDetailForm.unit = asset.unit;
    assetDetailForm.serial_number = asset.serial_number ?? '';
    assetDetailForm.unit_cost = asset.unit_cost || null;
    assetDetailForm.supplier = asset.supplier ?? '';
    assetDetailForm.invoice_number = asset.invoice_number ?? '';
    assetDetailForm.warranty_until = asset.warranty_until ?? '';
    assetDetailForm.specifications = asset.specifications ?? '';
    assetDetailForm.notes = asset.notes ?? '';
}

function saveAsset() {
    assetForm.post('/fixed-assets', {
        preserveScroll: true,
        onSuccess: () => {
            showAssetForm.value = false;
            assetForm.reset();
            assetForm.purchase_date = today;
            assetForm.branch_id = props.branches[0]?.id ?? '';
        },
    });
}

function submitAssetDetails() {
    if (!selectedAsset.value) {
        return;
    }

    assetDetailForm.patch('/fixed-assets/' + selectedAsset.value.id, {
        preserveScroll: true,
        onSuccess: closeModal,
    });
}

function openHandover(asset: Asset) {
    selectedAsset.value = asset;
    modal.value = 'handover';
    handoverForm.reset();
    handoverForm.branch_id = asset.branch_id ?? props.branches[0]?.id ?? '';
    handoverForm.handover_date = today;
    handoverForm.condition_at_handover = [
        'good',
        'minor_issue',
        'major_issue',
    ].includes(asset.condition_status)
        ? asset.condition_status
        : 'good';
    handoverForm.to_user_id =
        props.managers.find(
            (manager) =>
                Number(manager.branch_id) === Number(handoverForm.branch_id),
        )?.id ?? '';
}

function openInspection(asset: Asset) {
    selectedAsset.value = asset;
    modal.value = 'inspection';
    inspectionForm.reset();
    inspectionForm.fixed_asset_handover_id =
        asset.latest_handover?.status === 'accepted'
            ? asset.latest_handover.id
            : '';
    inspectionForm.inspection_type =
        asset.latest_handover?.status === 'accepted' && !asset.latest_inspection
            ? 'handover'
            : 'routine';
    inspectionForm.inspected_at = today;
    inspectionForm.condition_status =
        asset.condition_status === 'unassessed'
            ? 'good'
            : asset.condition_status;
    inspectionForm.result =
        asset.condition_status === 'major_issue' ||
        asset.condition_status === 'missing'
            ? 'needs_action'
            : 'pass';
    inspectionForm.score = inspectionForm.result === 'pass' ? 100 : 50;
}

function submitHandover() {
    if (!selectedAsset.value) {
        return;
    }

    handoverForm.post(
        '/fixed-assets/' + selectedAsset.value.id + '/handovers',
        { forceFormData: true, preserveScroll: true, onSuccess: closeModal },
    );
}

function submitInspection() {
    if (!selectedAsset.value) {
        return;
    }

    inspectionForm.post(
        '/fixed-assets/' + selectedAsset.value.id + '/inspections',
        { forceFormData: true, preserveScroll: true, onSuccess: closeModal },
    );
}

function openDispose(asset: Asset) {
    selectedAsset.value = asset;
    modal.value = 'dispose';
    disposeForm.reset();
    disposeForm.disposed_at = today;
    disposeForm.disposal_proceeds = 0;
    disposeForm.payment_method = 'bank_transfer';
}

function submitDispose() {
    if (!selectedAsset.value) {
        return;
    }

    disposeForm.post(`/fixed-assets/${selectedAsset.value.id}/dispose`, {
        preserveScroll: true,
        onSuccess: closeModal,
    });
}

function acceptHandover(asset: Asset) {
    const handover = asset.latest_handover;

    if (!handover) {
        return;
    }

    const notes =
        window.prompt('Ghi chú khi nhận tài sản (không bắt buộc):') ?? '';
    router.post(
        '/fixed-asset-handovers/' + handover.id + '/accept',
        { notes },
        { preserveScroll: true },
    );
}

function rejectHandover(asset: Asset) {
    const handover = asset.latest_handover;

    if (!handover) {
        return;
    }

    const reason = window.prompt('Lý do từ chối nhận tài sản:');

    if (!reason?.trim()) {
        return;
    }

    router.post(
        '/fixed-asset-handovers/' + handover.id + '/reject',
        { reason },
        { preserveScroll: true },
    );
}

function onFileChange(event: Event, target: 'handover' | 'inspection') {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (target === 'handover') {
        handoverForm.evidence = file;
    } else {
        inspectionForm.evidence = file;
    }
}

function closeModal() {
    modal.value = null;
    selectedAsset.value = null;
}

watch(
    () => handoverForm.branch_id,
    () => {
        if (
            !managersForBranch.value.some(
                (manager) =>
                    Number(manager.id) === Number(handoverForm.to_user_id),
            )
        ) {
            handoverForm.to_user_id = managersForBranch.value[0]?.id ?? '';
        }
    },
);
</script>

<template>
    <Head title="Tài sản & Bàn giao" />

    <div class="space-y-6 p-4 sm:p-6">
        <FinancePageHeader
            title="Tài sản & Bàn giao"
            description="Quản lý người chịu trách nhiệm, biên bản bàn giao và kiểm tra thực tế tài sản tại từng chi nhánh."
            :icon="Boxes"
        >
            <template #actions>
                <Button
                    v-if="permissions.canManageAssets"
                    class="gap-2"
                    @click="showAssetForm = !showAssetForm"
                >
                    <Plus class="size-4" /> Thêm tài sản
                </Button>
            </template>
        </FinancePageHeader>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div
                class="rounded-2xl border border-border/60 bg-card/80 p-4 shadow-sm"
            >
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Tổng tài sản
                </p>
                <p class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ stats.total }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Trong phạm vi được phân quyền
                </p>
            </div>
            <div
                class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-4 shadow-sm"
            >
                <p
                    class="text-xs font-semibold tracking-wide text-amber-700 uppercase dark:text-amber-300"
                >
                    Chờ nhận
                </p>
                <p
                    class="mt-2 text-2xl font-semibold text-amber-600 tabular-nums"
                >
                    {{ stats.pending_handover }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Cần quản lý chi nhánh xác nhận
                </p>
            </div>
            <div
                class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4 shadow-sm"
            >
                <p
                    class="text-xs font-semibold tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                >
                    Đang quản lý
                </p>
                <p
                    class="mt-2 text-2xl font-semibold text-emerald-600 tabular-nums"
                >
                    {{ stats.assigned }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Đã có người chịu trách nhiệm
                </p>
            </div>
            <div
                class="rounded-2xl border border-rose-500/20 bg-rose-500/5 p-4 shadow-sm"
            >
                <p
                    class="text-xs font-semibold tracking-wide text-rose-700 uppercase dark:text-rose-300"
                >
                    Cần xử lý
                </p>
                <p
                    class="mt-2 text-2xl font-semibold text-rose-600 tabular-nums"
                >
                    {{ stats.attention }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Kiểm tra chưa đạt hoặc có ngoại lệ
                </p>
            </div>
            <div
                class="rounded-2xl border border-border/60 bg-card/80 p-4 shadow-sm"
            >
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Chưa đánh giá
                </p>
                <p class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ stats.unassessed }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Cần lập biên bản kiểm tra
                </p>
            </div>
        </div>

        <section
            v-if="showAssetForm"
            class="rounded-2xl border border-border/60 bg-card/80 p-5 shadow-sm sm:p-6"
        >
            <div class="mb-5 flex items-center gap-3">
                <div
                    class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <Plus class="size-4" />
                </div>
                <div>
                    <h2 class="font-semibold">Tạo hồ sơ tài sản</h2>
                    <p class="text-sm text-muted-foreground">
                        Ghi nhận tài sản và chi nhánh dự kiến trước khi lập biên
                        bản bàn giao.
                    </p>
                </div>
            </div>
            <div class="grid gap-3 md:grid-cols-3">
                <Input
                    v-model="assetForm.asset_code"
                    placeholder="Mã tài sản"
                />
                <Input v-model="assetForm.name" placeholder="Tên tài sản" />
                <Input
                    v-model="assetForm.category"
                    placeholder="Nhóm tài sản"
                />
                <Input v-model="assetForm.brand" placeholder="Thương hiệu" />
                <Input
                    v-model="assetForm.model"
                    placeholder="Model / mã sản phẩm"
                />
                <select
                    v-model="assetForm.branch_id"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="">Chọn chi nhánh</option>
                    <option
                        v-for="branch in branches"
                        :key="branch.id"
                        :value="branch.id"
                    >
                        {{ branch.name }}
                    </option>
                </select>
                <input
                    v-model.number="assetForm.quantity"
                    type="number"
                    min="1"
                    placeholder="Số lượng"
                    class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <Input
                    v-model="assetForm.unit"
                    placeholder="Đơn vị (cái, bộ...)"
                />
                <Input
                    v-model="assetForm.serial_number"
                    placeholder="Serial / IMEI / mã nhận diện"
                />
                <Input
                    v-model="assetForm.purchase_date"
                    type="date"
                    aria-label="Ngày mua"
                />
                <input
                    v-model.number="assetForm.cost"
                    type="number"
                    min="0"
                    placeholder="Tổng giá trị / nguyên giá"
                    class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <input
                    v-model.number="assetForm.unit_cost"
                    type="number"
                    min="0"
                    placeholder="Đơn giá mỗi đơn vị (tùy chọn)"
                    class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <Input
                    v-model="assetForm.supplier"
                    placeholder="Nhà cung cấp"
                />
                <Input
                    v-model="assetForm.invoice_number"
                    placeholder="Số hóa đơn / chứng từ"
                />
                <Input
                    v-model="assetForm.warranty_until"
                    type="date"
                    aria-label="Bảo hành đến ngày"
                />
            </div>
            <textarea
                v-model="assetForm.specifications"
                rows="3"
                placeholder="Thông số kỹ thuật, cấu hình, phụ kiện đi kèm..."
                class="mt-3 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <textarea
                v-model="assetForm.notes"
                rows="3"
                placeholder="Ghi chú hồ sơ, nguồn mua hoặc thông tin khác..."
                class="mt-3 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <div class="mt-5 flex justify-end">
                <Button :disabled="assetForm.processing" @click="saveAsset"
                    >Lưu hồ sơ</Button
                >
            </div>
        </section>

        <section
            class="overflow-hidden rounded-2xl border border-border/60 bg-card/80 shadow-sm"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 px-5 py-4 sm:px-6"
            >
                <div>
                    <h2 class="font-semibold">Danh mục tài sản</h2>
                    <p class="text-xs text-muted-foreground">
                        Theo dõi người nhận, tình trạng và kết quả kiểm tra gần
                        nhất.
                    </p>
                </div>
                <span
                    class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground"
                    >{{ assets.total }} hồ sơ</span
                >
            </div>
            <div class="overflow-auto">
                <table class="w-full min-w-[1040px] text-sm">
                    <thead
                        class="bg-muted/40 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3">Tài sản</th>
                            <th class="px-4 py-3">Chi nhánh / người giữ</th>
                            <th class="px-4 py-3">Bàn giao</th>
                            <th class="px-4 py-3">Kiểm tra gần nhất</th>
                            <th class="px-4 py-3">Trạng thái</th>
                            <th class="px-4 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="asset in assets.data"
                            :key="asset.id"
                            class="align-top transition-colors hover:bg-muted/30"
                        >
                            <td class="px-4 py-4">
                                <div class="font-semibold">
                                    {{ asset.name }}
                                </div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    {{ asset.asset_code }} ·
                                    {{ asset.category || 'Chưa phân loại' }}
                                </div>
                                <div
                                    v-if="asset.brand || asset.model"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ asset.brand || 'Chưa có thương hiệu'
                                    }}<span v-if="asset.model">
                                        · {{ asset.model }}</span
                                    >
                                </div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    Số lượng: {{ asset.quantity }}
                                    {{ asset.unit }} · Tổng giá trị:
                                    {{ money(asset.cost) }}
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-medium">
                                    {{ asset.branch_name || 'Chưa xác định' }}
                                </div>
                                <div class="mt-1 text-xs text-muted-foreground">
                                    {{
                                        asset.custodian?.name ||
                                        'Chưa có người chịu trách nhiệm'
                                    }}
                                </div>
                                <div
                                    v-if="asset.custody_location"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    Vị trí: {{ asset.custody_location }}
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div
                                    v-if="asset.latest_handover"
                                    class="space-y-1"
                                >
                                    <div class="font-medium">
                                        {{
                                            asset.latest_handover.handover_code
                                        }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            asset.latest_handover.to_user
                                                ?.name || 'Chưa có người nhận'
                                        }}
                                    </div>
                                    <div
                                        class="text-xs font-medium"
                                        :class="
                                            asset.latest_handover.status ===
                                            'accepted'
                                                ? 'text-emerald-600'
                                                : asset.latest_handover
                                                        .status === 'rejected'
                                                  ? 'text-rose-600'
                                                  : 'text-amber-600'
                                        "
                                    >
                                        {{
                                            asset.latest_handover.status ===
                                            'accepted'
                                                ? 'Đã xác nhận'
                                                : asset.latest_handover
                                                        .status === 'rejected'
                                                  ? 'Đã từ chối'
                                                  : 'Chờ xác nhận'
                                        }}
                                    </div>
                                </div>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >Chưa lập biên bản</span
                                >
                            </td>
                            <td class="px-4 py-4">
                                <div
                                    v-if="asset.latest_inspection"
                                    class="space-y-1"
                                >
                                    <div class="font-medium">
                                        {{
                                            asset.latest_inspection
                                                .inspection_code
                                        }}
                                        <span
                                            class="ml-1 text-xs font-normal text-muted-foreground"
                                            >{{
                                                asset.latest_inspection
                                                    .inspected_at
                                            }}</span
                                        >
                                    </div>
                                    <div
                                        :class="[
                                            'text-xs font-medium',
                                            conditionFor(
                                                asset.latest_inspection
                                                    .condition_status,
                                            ).className,
                                        ]"
                                    >
                                        {{
                                            conditionFor(
                                                asset.latest_inspection
                                                    .condition_status,
                                            ).label
                                        }}<span
                                            v-if="
                                                asset.latest_inspection
                                                    .score !== null
                                            "
                                        >
                                            ·
                                            {{
                                                asset.latest_inspection.score
                                            }}/100</span
                                        >
                                    </div>
                                    <div
                                        :class="[
                                            'inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ring-1',
                                            resultFor(
                                                asset.latest_inspection.result,
                                            ).className,
                                        ]"
                                    >
                                        {{
                                            resultFor(
                                                asset.latest_inspection.result,
                                            ).label
                                        }}
                                    </div>
                                </div>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >Chưa kiểm tra</span
                                >
                            </td>
                            <td class="px-4 py-4">
                                <div
                                    :class="[
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1',
                                        statusFor(asset.custody_status)
                                            .className,
                                    ]"
                                >
                                    {{ statusFor(asset.custody_status).label }}
                                </div>
                                <div
                                    class="mt-2 text-xs"
                                    :class="
                                        conditionFor(asset.condition_status)
                                            .className
                                    "
                                >
                                    {{
                                        conditionFor(asset.condition_status)
                                            .label
                                    }}
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    <template
                                        v-if="
                                            asset.can_accept_handover &&
                                            permissions.canAcceptHandover
                                        "
                                        ><Button
                                            size="sm"
                                            class="gap-1.5 bg-emerald-600 text-xs text-white hover:bg-emerald-700"
                                            @click="acceptHandover(asset)"
                                            ><CheckCircle2 class="size-3.5" />
                                            Nhận</Button
                                        ><Button
                                            variant="outline"
                                            size="sm"
                                            class="gap-1.5 border-rose-200 text-xs text-rose-600 hover:bg-rose-500/10 hover:text-rose-700"
                                            @click="rejectHandover(asset)"
                                            ><XCircle class="size-3.5" /> Từ
                                            chối</Button
                                        ></template
                                    >
                                    <Button
                                        v-if="
                                            permissions.canHandover &&
                                            !asset.can_accept_handover
                                        "
                                        variant="outline"
                                        size="sm"
                                        class="gap-1.5 text-xs"
                                        @click="openHandover(asset)"
                                        ><FileCheck2 class="size-3.5" /> Bàn
                                        giao</Button
                                    >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="gap-1.5 text-xs"
                                        @click="openDetails(asset)"
                                        ><Info class="size-3.5" /> Chi
                                        tiết</Button
                                    >
                                    <Button
                                        v-if="permissions.canInspect"
                                        variant="ghost"
                                        size="sm"
                                        class="gap-1.5 text-xs text-primary"
                                        @click="openInspection(asset)"
                                        ><SearchCheck class="size-3.5" /> Kiểm
                                        tra</Button
                                    >
                                </div>
                            </td>
                        </tr>
                        <tr v-if="assets.data.length === 0">
                            <td
                                colspan="6"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                Chưa có hồ sơ tài sản trong phạm vi này.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div
            v-if="modal && selectedAsset"
            class="fixed inset-0 z-50 grid place-items-center bg-background/80 p-4 backdrop-blur-sm"
        >
            <div
                class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl border border-border/60 bg-card p-5 shadow-2xl sm:p-6"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wide text-primary uppercase"
                        >
                            {{ selectedAsset.asset_code }}
                        </p>
                        <h2 class="mt-1 text-lg font-semibold">
                            {{
                                modal === 'details'
                                    ? 'Chi tiết hồ sơ tài sản'
                                    : modal === 'edit'
                                      ? 'Cập nhật thông tin tài sản'
                                      : modal === 'handover'
                                        ? 'Lập biên bản bàn giao'
                                        : modal === 'dispose'
                                          ? 'Thanh lý tài sản'
                                          : 'Kiểm tra & đánh giá tài sản'
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ selectedAsset.name }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Đóng"
                        @click="closeModal"
                        ><X class="size-4"
                    /></Button>
                </div>

                <div v-if="modal === 'details'" class="mt-5 space-y-5">
                    <div
                        v-if="
                            permissions.canManageAssets &&
                            selectedAsset.status === 'active'
                        "
                        class="flex justify-end"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            class="text-rose-600"
                            @click="openDispose(selectedAsset)"
                            >Thanh lý tài sản</Button
                        >
                    </div>
                    <div
                        v-if="permissions.canManageAssets"
                        class="flex justify-end"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            @click="openEdit(selectedAsset)"
                            >Chỉnh sửa thông tin</Button
                        >
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            class="rounded-xl border border-border/60 bg-muted/20 p-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                Nhóm tài sản
                            </p>
                            <p class="mt-1 font-medium">
                                {{ selectedAsset.category || 'Chưa phân loại' }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border/60 bg-muted/20 p-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                Thương hiệu / model
                            </p>
                            <p class="mt-1 font-medium">
                                {{ selectedAsset.brand || 'Chưa có thương hiệu'
                                }}<span v-if="selectedAsset.model">
                                    · {{ selectedAsset.model }}</span
                                >
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border/60 bg-muted/20 p-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                Số lượng
                            </p>
                            <p class="mt-1 font-medium">
                                {{ selectedAsset.quantity }}
                                {{ selectedAsset.unit }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border/60 bg-muted/20 p-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                Serial / mã nhận diện
                            </p>
                            <p class="mt-1 font-medium break-all">
                                {{
                                    selectedAsset.serial_number ||
                                    'Chưa cập nhật'
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border/60 bg-muted/20 p-3"
                        >
                            <p class="text-xs text-muted-foreground">Đơn giá</p>
                            <p class="mt-1 font-medium">
                                {{ money(selectedAsset.unit_cost) }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border/60 bg-muted/20 p-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                Tổng giá trị / nguyên giá
                            </p>
                            <p class="mt-1 font-medium">
                                {{ money(selectedAsset.cost) }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border/60 bg-muted/20 p-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                Ngày mua / ghi nhận
                            </p>
                            <p class="mt-1 font-medium">
                                {{
                                    selectedAsset.purchase_date ||
                                    'Chưa cập nhật'
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border/60 bg-muted/20 p-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                Nhà cung cấp
                            </p>
                            <p class="mt-1 font-medium">
                                {{ selectedAsset.supplier || 'Chưa cập nhật' }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border/60 bg-muted/20 p-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                Bảo hành đến
                            </p>
                            <p class="mt-1 font-medium">
                                {{
                                    selectedAsset.warranty_until ||
                                    'Không khai báo'
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-border/60 p-4">
                        <h3 class="font-semibold">
                            Thông tin quản lý hiện tại
                        </h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Chi nhánh
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        selectedAsset.branch_name ||
                                        'Chưa xác định'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Người chịu trách nhiệm
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        selectedAsset.custodian?.name ||
                                        'Chưa bàn giao'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Vị trí sử dụng / lưu giữ
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        selectedAsset.custody_location ||
                                        'Chưa cập nhật'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Trạng thái
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        statusFor(selectedAsset.custody_status)
                                            .label
                                    }}
                                    ·
                                    {{
                                        conditionFor(
                                            selectedAsset.condition_status,
                                        ).label
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-border/60 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-semibold">
                                Lịch sử bàn giao gần nhất
                            </h3>
                            <a
                                v-if="selectedAsset.handover_evidence_url"
                                :href="selectedAsset.handover_evidence_url"
                                target="_blank"
                                class="text-xs text-primary hover:underline"
                                >Xem biên bản / ảnh</a
                            >
                        </div>
                        <div
                            v-if="selectedAsset.latest_handover"
                            class="mt-3 grid gap-3 sm:grid-cols-2"
                        >
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Mã biên bản
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        selectedAsset.latest_handover
                                            .handover_code
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Ngày bàn giao
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        selectedAsset.latest_handover
                                            .handover_date
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Người bàn giao
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        selectedAsset.latest_handover
                                            .handed_over_by?.name ||
                                        'Chưa cập nhật'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Người nhận
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        selectedAsset.latest_handover.to_user
                                            ?.name || 'Chưa cập nhật'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Tình trạng lúc giao
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        conditionFor(
                                            selectedAsset.latest_handover
                                                .condition_at_handover,
                                        ).label
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Thời điểm xác nhận
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        selectedAsset.latest_handover
                                            .accepted_at || 'Chưa xác nhận'
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="selectedAsset.latest_handover.notes"
                                class="sm:col-span-2"
                            >
                                <p class="text-xs text-muted-foreground">
                                    Ghi chú bàn giao
                                </p>
                                <p class="mt-1 whitespace-pre-line">
                                    {{ selectedAsset.latest_handover.notes }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="mt-3 text-sm text-muted-foreground">
                            Chưa có biên bản bàn giao.
                        </p>
                    </div>

                    <div class="rounded-xl border border-border/60 p-4">
                        <h3 class="font-semibold">
                            Kiểm tra và đánh giá gần nhất
                        </h3>
                        <div
                            v-if="selectedAsset.latest_inspection"
                            class="mt-3 grid gap-3 sm:grid-cols-2"
                        >
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Ngày kiểm tra
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        selectedAsset.latest_inspection
                                            .inspected_at
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Người kiểm tra
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        selectedAsset.latest_inspection
                                            .inspector_name || 'Chưa cập nhật'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Kết quả / điểm
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        resultFor(
                                            selectedAsset.latest_inspection
                                                .result,
                                        ).label
                                    }}<span
                                        v-if="
                                            selectedAsset.latest_inspection
                                                .score !== null
                                        "
                                    >
                                        ·
                                        {{
                                            selectedAsset.latest_inspection
                                                .score
                                        }}/100</span
                                    >
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Tình trạng
                                </p>
                                <p class="mt-1 font-medium">
                                    {{
                                        conditionFor(
                                            selectedAsset.latest_inspection
                                                .condition_status,
                                        ).label
                                    }}
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-xs text-muted-foreground">
                                    Phát hiện
                                </p>
                                <p class="mt-1 whitespace-pre-line">
                                    {{
                                        selectedAsset.latest_inspection.findings
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="
                                    selectedAsset.latest_inspection
                                        .action_required
                                "
                                class="sm:col-span-2"
                            >
                                <p class="text-xs text-muted-foreground">
                                    Hành động cần xử lý
                                </p>
                                <p
                                    class="mt-1 whitespace-pre-line text-amber-700"
                                >
                                    {{
                                        selectedAsset.latest_inspection
                                            .action_required
                                    }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="mt-3 text-sm text-muted-foreground">
                            Chưa có biên bản kiểm tra.
                        </p>
                    </div>

                    <div
                        v-if="
                            selectedAsset.specifications ||
                            selectedAsset.invoice_number ||
                            selectedAsset.notes
                        "
                        class="rounded-xl border border-border/60 p-4"
                    >
                        <h3 class="font-semibold">Thông tin khác</h3>
                        <div class="mt-3 space-y-3 text-sm">
                            <p v-if="selectedAsset.specifications">
                                <span class="text-xs text-muted-foreground"
                                    >Thông số kỹ thuật:</span
                                ><br /><span class="whitespace-pre-line">{{
                                    selectedAsset.specifications
                                }}</span>
                            </p>
                            <p v-if="selectedAsset.invoice_number">
                                <span class="text-xs text-muted-foreground"
                                    >Hóa đơn / chứng từ:</span
                                >
                                {{ selectedAsset.invoice_number }}
                            </p>
                            <p v-if="selectedAsset.notes">
                                <span class="text-xs text-muted-foreground"
                                    >Ghi chú:</span
                                ><br /><span class="whitespace-pre-line">{{
                                    selectedAsset.notes
                                }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else-if="modal === 'edit'" class="mt-5 space-y-4">
                    <div
                        class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-3 text-xs text-muted-foreground"
                    >
                        Nguyên giá, ngày mua và chi nhánh đang quản lý được giữ
                        theo chứng từ kế toán. Hãy cập nhật các thông tin nhận
                        diện và hồ sơ chi tiết bên dưới.
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <Input
                            v-model="assetDetailForm.name"
                            placeholder="Tên tài sản"
                        />
                        <Input
                            v-model="assetDetailForm.category"
                            placeholder="Nhóm tài sản"
                        />
                        <Input
                            v-model="assetDetailForm.brand"
                            placeholder="Thương hiệu"
                        />
                        <Input
                            v-model="assetDetailForm.model"
                            placeholder="Model / mã sản phẩm"
                        />
                        <input
                            v-model.number="assetDetailForm.quantity"
                            type="number"
                            min="1"
                            placeholder="Số lượng"
                            class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <Input
                            v-model="assetDetailForm.unit"
                            placeholder="Đơn vị (cái, bộ...)"
                        />
                        <Input
                            v-model="assetDetailForm.serial_number"
                            placeholder="Serial / IMEI / mã nhận diện"
                        />
                        <input
                            v-model.number="assetDetailForm.unit_cost"
                            type="number"
                            min="0"
                            placeholder="Đơn giá mỗi đơn vị"
                            class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <Input
                            v-model="assetDetailForm.supplier"
                            placeholder="Nhà cung cấp"
                        />
                        <Input
                            v-model="assetDetailForm.invoice_number"
                            placeholder="Số hóa đơn / chứng từ"
                        />
                        <Input
                            v-model="assetDetailForm.warranty_until"
                            type="date"
                            aria-label="Bảo hành đến ngày"
                        />
                        <div
                            class="rounded-md border border-dashed border-border/70 px-3 py-2 text-sm"
                        >
                            <span class="text-xs text-muted-foreground"
                                >Tổng giá trị / nguyên giá</span
                            >
                            <p class="font-medium">
                                {{ money(selectedAsset.cost) }}
                            </p>
                        </div>
                    </div>
                    <textarea
                        v-model="assetDetailForm.specifications"
                        rows="3"
                        placeholder="Thông số kỹ thuật, cấu hình, phụ kiện đi kèm..."
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <textarea
                        v-model="assetDetailForm.notes"
                        rows="3"
                        placeholder="Ghi chú hồ sơ, nguồn mua hoặc thông tin khác..."
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <div class="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            @click="openDetails(selectedAsset)"
                            >Hủy</Button
                        ><Button
                            :disabled="assetDetailForm.processing"
                            @click="submitAssetDetails"
                            >Lưu thay đổi</Button
                        >
                    </div>
                </div>

                <div v-else-if="modal === 'dispose'" class="mt-5 space-y-4">
                    <div
                        class="rounded-xl border border-rose-500/20 bg-rose-500/5 p-3 text-xs text-muted-foreground"
                    >
                        Thanh lý sẽ dừng khấu hao và ghi nhận xóa nguyên giá,
                        hao mòn cùng lãi/lỗ thanh lý vào sổ tài chính.
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <Input
                            v-model="disposeForm.disposed_at"
                            type="date"
                            aria-label="Ngày thanh lý"
                        /><input
                            v-model.number="disposeForm.disposal_proceeds"
                            type="number"
                            min="0"
                            placeholder="Tiền thu thanh lý"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                        /><select
                            v-model="disposeForm.payment_method"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                        >
                            <option value="bank_transfer">Chuyển khoản</option>
                            <option value="cash">Tiền mặt</option>
                        </select>
                    </div>
                    <textarea
                        v-model="disposeForm.reason"
                        rows="3"
                        required
                        minlength="5"
                        placeholder="Lý do thanh lý, biên bản hoặc chứng từ liên quan..."
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                    />
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="closeModal"
                            >Hủy</Button
                        ><Button
                            :disabled="disposeForm.processing"
                            class="bg-rose-600 text-white hover:bg-rose-700"
                            @click="submitDispose"
                            >Ghi nhận thanh lý</Button
                        >
                    </div>
                </div>

                <div v-else-if="modal === 'handover'" class="mt-5 space-y-4">
                    <div
                        class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-3 text-xs text-muted-foreground"
                    >
                        Biên bản chỉ hợp lệ khi người nhận là Quản lý đang được
                        gán cho đúng chi nhánh. Tài sản chỉ chuyển sang trạng
                        thái “Đang quản lý” sau khi người nhận xác nhận.
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold"
                                >Chi nhánh nhận</label
                            ><select
                                v-model="handoverForm.branch_id"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">Chọn chi nhánh</option>
                                <option
                                    v-for="branch in branches"
                                    :key="branch.id"
                                    :value="branch.id"
                                >
                                    {{ branch.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold"
                                >Quản lý nhận</label
                            ><select
                                v-model="handoverForm.to_user_id"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">Chọn quản lý</option>
                                <option
                                    v-for="manager in managersForBranch"
                                    :key="manager.id"
                                    :value="manager.id"
                                >
                                    {{ manager.name }} · {{ manager.email }}
                                </option>
                            </select>
                            <p
                                v-if="
                                    handoverForm.branch_id &&
                                    managersForBranch.length === 0
                                "
                                class="mt-1 text-xs text-rose-600"
                            >
                                Chi nhánh chưa được gán Quản lý.
                            </p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold"
                                >Ngày bàn giao</label
                            ><Input
                                v-model="handoverForm.handover_date"
                                type="date"
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold"
                                >Tình trạng lúc giao</label
                            ><select
                                v-model="handoverForm.condition_at_handover"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="good">Tốt</option>
                                <option value="minor_issue">Lỗi nhẹ</option>
                                <option value="major_issue">
                                    Lỗi nghiêm trọng
                                </option>
                            </select>
                        </div>
                    </div>
                    <Input
                        v-model="handoverForm.custody_location"
                        placeholder="Vị trí lưu giữ / khu vực sử dụng"
                    />
                    <textarea
                        v-model="handoverForm.notes"
                        rows="3"
                        placeholder="Ghi chú, phụ kiện kèm theo, số serial, tình trạng bàn giao..."
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <input
                        type="file"
                        accept="image/*"
                        class="block w-full rounded-xl border border-input bg-background p-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-foreground"
                        @change="onFileChange($event, 'handover')"
                    />
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="closeModal"
                            >Hủy</Button
                        ><Button
                            :disabled="
                                handoverForm.processing ||
                                !handoverForm.to_user_id
                            "
                            class="gap-2"
                            @click="submitHandover"
                            ><FileCheck2 class="size-4" /> Lập biên bản</Button
                        >
                    </div>
                </div>

                <div v-else class="mt-5 space-y-4">
                    <div
                        class="rounded-xl border border-sky-500/20 bg-sky-500/5 p-3 text-xs text-muted-foreground"
                    >
                        Kết quả đánh giá là căn cứ vận hành để theo dõi việc bảo
                        quản, sử dụng và xử lý ngoại lệ; không tự động suy ra
                        khấu hao kế toán.
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold"
                                >Loại kiểm tra</label
                            ><select
                                v-model="inspectionForm.inspection_type"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="handover">Sau bàn giao</option>
                                <option value="routine">Định kỳ</option>
                                <option value="surprise">Đột xuất</option>
                                <option value="incident">Theo sự cố</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold"
                                >Ngày kiểm tra</label
                            ><Input
                                v-model="inspectionForm.inspected_at"
                                type="date"
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold"
                                >Tình trạng thực tế</label
                            ><select
                                v-model="inspectionForm.condition_status"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="good">Tốt</option>
                                <option value="minor_issue">Lỗi nhẹ</option>
                                <option value="major_issue">
                                    Lỗi nghiêm trọng
                                </option>
                                <option value="missing">Không tìm thấy</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold"
                                >Kết quả đánh giá</label
                            ><select
                                v-model="inspectionForm.result"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="pass">Đạt</option>
                                <option value="needs_action">
                                    Cần khắc phục
                                </option>
                                <option value="fail">Không đạt</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold"
                            >Điểm đánh giá (0–100)</label
                        ><input
                            v-model.number="inspectionForm.score"
                            type="number"
                            min="0"
                            max="100"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </div>
                    <textarea
                        v-model="inspectionForm.findings"
                        rows="4"
                        required
                        placeholder="Nêu tiêu chí đã kiểm, hiện trạng, số serial/đặc điểm đối chiếu và bằng chứng..."
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <textarea
                        v-if="inspectionForm.result !== 'pass'"
                        v-model="inspectionForm.action_required"
                        rows="3"
                        required
                        placeholder="Yêu cầu khắc phục, người phụ trách và thời hạn đề xuất..."
                        class="w-full rounded-md border border-amber-500/40 bg-amber-500/5 px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <input
                        type="file"
                        accept="image/*"
                        class="block w-full rounded-xl border border-input bg-background p-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-sky-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white"
                        @change="onFileChange($event, 'inspection')"
                    />
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="closeModal"
                            >Hủy</Button
                        ><Button
                            :disabled="inspectionForm.processing"
                            class="gap-2 bg-sky-600 text-white hover:bg-sky-700"
                            @click="submitInspection"
                            ><ClipboardCheck class="size-4" /> Lưu đánh
                            giá</Button
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
