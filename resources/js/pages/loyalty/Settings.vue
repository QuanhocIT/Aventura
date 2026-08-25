<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Award,
    Plus,
    Save,
    Trash2,
    Pencil,
    Loader2,
    ArrowLeft,
    Gift,
    Calendar,
    Coins,
    QrCode,
    Sparkles,
    CheckCircle2,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
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
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    program: any;
    tiers: any[];
    canManageFinancials: boolean;
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

const programForm = useForm({
    is_active: props.program?.is_active ?? true,
    points_per_vnd: props.program?.points_per_vnd ?? 0.0001,
    point_value_vnd: props.program?.point_value_vnd ?? 100,
    points_expiry_days: props.program?.points_expiry_days ?? null,
    min_redeem_points: props.program?.min_redeem_points ?? 10,
    birthday_bonus_points: props.program?.birthday_bonus_points ?? 50,
    enable_qr_card: props.program?.enable_qr_card ?? true,
});

function saveProgram() {
    programForm.post('/loyalty/settings', { preserveScroll: true });
}

// Tier CRUD
const showTierDialog = ref(false);
const editingTier = ref<any>(null);
const tierForm = useForm({
    name: '',
    min_spent: 0,
    discount_percent: 0,
    points_multiplier: 1.0,
    benefits_json: null as any,
    color: '#6366f1',
    display_order: 0,
});

function openCreateTier() {
    editingTier.value = null;
    tierForm.reset();
    tierForm.color = '#6366f1';
    tierForm.points_multiplier = 1.0;
    showTierDialog.value = true;
}

function openEditTier(tier: any) {
    editingTier.value = tier;
    tierForm.name = tier.name;
    tierForm.min_spent = tier.min_spent;
    tierForm.discount_percent = tier.discount_percent;
    tierForm.points_multiplier = tier.points_multiplier;
    tierForm.benefits_json = tier.benefits_json;
    tierForm.color = tier.color || '#6366f1';
    tierForm.display_order = tier.display_order;
    showTierDialog.value = true;
}

function submitTier() {
    if (editingTier.value) {
        tierForm.patch(`/loyalty/tiers/${editingTier.value.id}`, {
            onSuccess: () => {
                showTierDialog.value = false;
            },
        });
    } else {
        tierForm.post('/loyalty/tiers', {
            onSuccess: () => {
                showTierDialog.value = false;
                tierForm.reset();
            },
        });
    }
}

async function deleteTier(tier: any) {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Xóa hạng "${tier.name}"?`,
        }))
    ) {
        return;
    }

    router.delete(`/loyalty/tiers/${tier.id}`);
}
</script>

<template>
    <Head title="Cấu hình Khách hàng thân thiết" />

    <div class="mx-auto flex max-w-7xl flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <Link href="/loyalty">
                    <Button
                        variant="outline"
                        size="icon"
                        class="h-10 w-10 rounded-xl border-slate-200 dark:border-slate-800"
                    >
                        <ArrowLeft class="size-4" />
                    </Button>
                </Link>
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 shadow-xs dark:bg-amber-950/60"
                >
                    <Award class="size-6 text-amber-500" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-bold tracking-tight">
                            Cấu hình Chương trình Thân thiết
                        </h1>
                        <Badge
                            :variant="
                                programForm.is_active ? 'default' : 'secondary'
                            "
                            :class="
                                programForm.is_active
                                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                            "
                        >
                            {{
                                programForm.is_active
                                    ? 'Đang hoạt động'
                                    : 'Tạm ngừng'
                            }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Cấu hình quy tắc tích điểm, giá trị điểm thưởng, thời
                        hạn và các hạng thành viên.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button
                    v-if="canManageFinancials"
                    type="button"
                    @click="saveProgram"
                    :disabled="programForm.processing"
                    class="gap-2 rounded-xl bg-amber-600 font-semibold text-white shadow-md hover:bg-amber-700 dark:bg-amber-600 dark:hover:bg-amber-500"
                >
                    <Loader2
                        v-if="programForm.processing"
                        class="size-4 animate-spin"
                    />
                    <Save v-else class="size-4" />
                    {{
                        programForm.processing ? 'Đang lưu...' : 'Lưu thay đổi'
                    }}
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left 2 Cols: General Settings Form & Tiers List -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Program Settings -->
                <Card
                    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardHeader
                        class="border-b border-slate-100 pb-4 dark:border-slate-800/80"
                    >
                        <div class="flex items-center justify-between">
                            <div class="space-y-1">
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-bold"
                                >
                                    <Sparkles class="size-4 text-amber-500" />
                                    Quy tắc Tích & Đổi điểm
                                </CardTitle>
                                <CardDescription class="text-xs">
                                    Thiết lập tỷ lệ tích điểm từ doanh thu đơn
                                    hàng và giá trị quy đổi điểm sang tiền giảm
                                    giá.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-6">
                        <form @submit.prevent="saveProgram" class="space-y-6">
                            <!-- Status Toggle -->
                            <div
                                class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-4 dark:border-slate-800/80 dark:bg-slate-900/60"
                            >
                                <div class="space-y-0.5">
                                    <Label
                                        class="text-sm font-bold text-slate-800 dark:text-slate-200"
                                        >Kích hoạt chương trình tích điểm</Label
                                    >
                                    <p class="text-xs text-muted-foreground">
                                        Tự động cộng điểm cho khách hàng sau khi
                                        thanh toán hóa đơn
                                    </p>
                                </div>
                                <button
                                    @click="
                                        programForm.is_active =
                                            !programForm.is_active
                                    "
                                    type="button"
                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                    :class="
                                        programForm.is_active
                                            ? 'bg-emerald-500'
                                            : 'bg-slate-300 dark:bg-slate-700'
                                    "
                                >
                                    <span
                                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md transition duration-200 ease-in-out"
                                        :class="
                                            programForm.is_active
                                                ? 'translate-x-5'
                                                : 'translate-x-0'
                                        "
                                    ></span>
                                </button>
                            </div>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <!-- Tỷ lệ tích điểm -->
                                <div class="space-y-2">
                                    <Label
                                        class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                        >Tỷ lệ tích điểm (điểm / 1 VNĐ)</Label
                                    >
                                    <Input
                                        type="number"
                                        step="0.00001"
                                        v-model="programForm.points_per_vnd"
                                        class="rounded-xl font-mono text-sm"
                                    />
                                    <p
                                        class="flex items-center gap-1 text-[11px] text-muted-foreground"
                                    >
                                        <Coins class="size-3 text-amber-500" />
                                        Ví dụ: <strong>0.0001</strong> = 1 điểm
                                        cho mỗi 10,000 VNĐ
                                    </p>
                                </div>

                                <!-- Giá trị 1 điểm -->
                                <div class="space-y-2">
                                    <Label
                                        class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                        >Giá trị quy đổi 1 điểm (VNĐ)</Label
                                    >
                                    <Input
                                        type="number"
                                        v-model="programForm.point_value_vnd"
                                        class="rounded-xl font-mono text-sm"
                                    />
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Khi quy đổi điểm thành tiền giảm giá
                                        trên đơn hàng
                                    </p>
                                </div>

                                <!-- Thời hạn điểm -->
                                <div class="space-y-2">
                                    <Label
                                        class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                        >Thời hạn sử dụng điểm (ngày)</Label
                                    >
                                    <Input
                                        type="number"
                                        v-model="programForm.points_expiry_days"
                                        placeholder="Để trống = Không bao giờ hết hạn"
                                        class="rounded-xl text-sm"
                                    />
                                    <p
                                        class="flex items-center gap-1 text-[11px] text-muted-foreground"
                                    >
                                        <Calendar
                                            class="size-3 text-blue-500"
                                        />
                                        Tự động xoá điểm quá hạn
                                    </p>
                                </div>

                                <!-- Tối thiểu để đổi -->
                                <div class="space-y-2">
                                    <Label
                                        class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                        >Số điểm tối thiểu để quy đổi</Label
                                    >
                                    <Input
                                        type="number"
                                        v-model="programForm.min_redeem_points"
                                        class="rounded-xl font-mono text-sm"
                                    />
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Mốc điểm tối thiểu cần đạt để áp dụng
                                        giảm giá
                                    </p>
                                </div>

                                <!-- Điểm thưởng sinh nhật -->
                                <div class="space-y-2">
                                    <Label
                                        class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                        >Điểm thưởng sinh nhật</Label
                                    >
                                    <Input
                                        type="number"
                                        v-model="
                                            programForm.birthday_bonus_points
                                        "
                                        class="rounded-xl font-mono text-sm"
                                    />
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Tự động cộng điểm cho hội viên vào dịp
                                        sinh nhật
                                    </p>
                                </div>

                                <!-- Bật thẻ QR -->
                                <div class="flex flex-col justify-end">
                                    <div
                                        class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 dark:border-slate-800/80 dark:bg-slate-900/60"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <QrCode
                                                class="size-4 text-indigo-500"
                                            />
                                            <Label
                                                class="cursor-pointer text-xs font-semibold"
                                                >Thẻ thành viên QR</Label
                                            >
                                        </div>
                                        <button
                                            @click="
                                                programForm.enable_qr_card =
                                                    !programForm.enable_qr_card
                                            "
                                            type="button"
                                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                            :class="
                                                programForm.enable_qr_card
                                                    ? 'bg-indigo-600'
                                                    : 'bg-slate-300 dark:bg-slate-700'
                                            "
                                        >
                                            <span
                                                class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-md transition duration-200 ease-in-out"
                                                :class="
                                                    programForm.enable_qr_card
                                                        ? 'translate-x-4'
                                                        : 'translate-x-0'
                                                "
                                            ></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Tiers Card -->
                <Card
                    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardHeader
                        class="border-b border-slate-100 pb-4 dark:border-slate-800/80"
                    >
                        <div class="flex items-center justify-between">
                            <div class="space-y-1">
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-bold"
                                >
                                    <Award class="size-4 text-indigo-500" />
                                    Cấu hình Bậc Hạng Thành viên
                                </CardTitle>
                                <CardDescription class="text-xs">
                                    Phân hạng hội viên (Bạc, Vàng, Kim Cương)
                                    theo tổng chi tiêu kèm ưu đãi % giảm giá &
                                    hệ số tích điểm.
                                </CardDescription>
                            </div>
                            <Button
                                v-if="canManageFinancials"
                                @click="openCreateTier"
                                class="gap-1.5 rounded-xl"
                                size="sm"
                            >
                                <Plus class="size-4" /> Thêm hạng
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="tiers.length" class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b border-slate-100 bg-slate-50/50 text-xs tracking-wider text-muted-foreground uppercase dark:border-slate-800 dark:bg-slate-900/60"
                                >
                                    <tr>
                                        <th class="px-6 py-3.5 font-bold">
                                            Hạng thành viên
                                        </th>
                                        <th class="px-4 py-3.5 font-bold">
                                            Chi tiêu tối thiểu
                                        </th>
                                        <th class="px-4 py-3.5 font-bold">
                                            Giảm giá đơn
                                        </th>
                                        <th class="px-4 py-3.5 font-bold">
                                            Hệ số tích điểm
                                        </th>
                                        <th
                                            class="px-6 py-3.5 text-right font-bold"
                                        >
                                            Thao tác
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-slate-800/80"
                                >
                                    <tr
                                        v-for="t in tiers"
                                        :key="t.id"
                                        class="transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-800/40"
                                    >
                                        <td class="px-6 py-4">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <span
                                                    class="h-3.5 w-3.5 shrink-0 rounded-full shadow-xs"
                                                    :style="{
                                                        backgroundColor:
                                                            t.color ||
                                                            '#94a3b8',
                                                    }"
                                                ></span>
                                                <span
                                                    class="font-bold text-slate-800 dark:text-slate-200"
                                                    >{{ t.name }}</span
                                                >
                                            </div>
                                        </td>
                                        <td
                                            class="px-4 py-4 font-mono font-semibold text-slate-700 dark:text-slate-300"
                                        >
                                            {{
                                                Number(
                                                    t.min_spent,
                                                ).toLocaleString()
                                            }}đ
                                        </td>
                                        <td class="px-4 py-4">
                                            <Badge
                                                variant="outline"
                                                class="border-emerald-500/20 bg-emerald-50 font-bold text-emerald-600 dark:bg-emerald-950/30 dark:text-emerald-400"
                                            >
                                                -{{ t.discount_percent }}%
                                            </Badge>
                                        </td>
                                        <td class="px-4 py-4">
                                            <Badge
                                                variant="secondary"
                                                class="border-none bg-indigo-50 font-bold text-indigo-600 dark:bg-indigo-950/30 dark:text-indigo-400"
                                            >
                                                x{{ t.points_multiplier }}
                                            </Badge>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div
                                                class="flex items-center justify-end gap-1"
                                            >
                                                <Button
                                                    v-if="canManageFinancials"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-8 w-8 rounded-lg text-slate-500 hover:text-slate-900 dark:hover:text-slate-100"
                                                    @click="openEditTier(t)"
                                                >
                                                    <Pencil class="size-3.5" />
                                                </Button>
                                                <Button
                                                    v-if="canManageFinancials"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-8 w-8 rounded-lg text-rose-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40"
                                                    @click="deleteTier(t)"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="p-8 text-center">
                            <Award
                                class="mx-auto mb-2 size-8 text-slate-300 dark:text-slate-700"
                            />
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Chưa có hạng thành viên nào.
                            </p>
                            <p class="mt-0.5 text-xs text-slate-400">
                                Nhấn "Thêm hạng" để tạo các cấp bậc Bạc, Vàng,
                                Kim Cương.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Sidebar: Quick Simulation & Info -->
            <div class="space-y-6">
                <Card
                    class="rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardHeader
                        class="border-b border-slate-100 pb-3 dark:border-slate-800/80"
                    >
                        <CardTitle
                            class="flex items-center gap-2 text-sm font-bold"
                        >
                            <Gift class="size-4 text-amber-500" /> Mô phỏng tính
                            điểm
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 p-5">
                        <div
                            class="rounded-xl border border-amber-200/40 bg-amber-50/60 p-4 dark:border-amber-900/30 dark:bg-amber-950/30"
                        >
                            <span
                                class="mb-1 block text-xs font-semibold text-amber-700 dark:text-amber-300"
                                >Ví dụ đơn hàng 500,000đ:</span
                            >
                            <div
                                class="flex items-center justify-between text-sm font-bold text-amber-900 dark:text-amber-200"
                            >
                                <span>Điểm tích lũy:</span>
                                <span
                                    class="font-mono text-base text-amber-600 dark:text-amber-400"
                                >
                                    +{{
                                        Math.floor(
                                            500000 *
                                                (programForm.points_per_vnd ||
                                                    0),
                                        )
                                    }}
                                    pt
                                </span>
                            </div>
                            <div
                                class="mt-2 flex items-center justify-between border-t border-amber-200/40 pt-2 text-xs text-amber-800 dark:border-amber-900/30 dark:text-amber-300"
                            >
                                <span>Giá trị giảm giá:</span>
                                <span class="font-mono font-bold">
                                    {{
                                        (
                                            Math.floor(
                                                500000 *
                                                    (programForm.points_per_vnd ||
                                                        0),
                                            ) *
                                            (programForm.point_value_vnd || 0)
                                        ).toLocaleString()
                                    }}đ
                                </span>
                            </div>
                        </div>

                        <div class="space-y-2.5 text-xs text-muted-foreground">
                            <div class="flex items-start gap-2">
                                <CheckCircle2
                                    class="mt-0.5 size-3.5 shrink-0 text-emerald-500"
                                />
                                <span
                                    >Tự động tính chiết khấu cho khách khi thu
                                    ngân thanh toán hóa đơn.</span
                                >
                            </div>
                            <div class="flex items-start gap-2">
                                <CheckCircle2
                                    class="mt-0.5 size-3.5 shrink-0 text-emerald-500"
                                />
                                <span
                                    >Tự động nâng hạng thẻ khi tổng chi tiêu
                                    khách hàng đạt mốc tích lũy.</span
                                >
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <!-- Tier Create/Edit Dialog -->
    <Dialog v-model:open="showTierDialog">
        <DialogContent class="max-w-md rounded-2xl">
            <DialogHeader>
                <DialogTitle class="text-base font-bold">{{
                    editingTier ? 'Sửa hạng thành viên' : 'Thêm hạng thành viên'
                }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitTier" class="space-y-4">
                <div class="grid gap-1.5">
                    <Label class="text-xs font-semibold">Tên hạng</Label>
                    <Input
                        v-model="tierForm.name"
                        placeholder="Ví dụ: Vàng / Gold"
                        class="rounded-xl"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-semibold"
                            >Chi tiêu tối thiểu (VNĐ)</Label
                        >
                        <Input
                            type="number"
                            v-model="tierForm.min_spent"
                            class="rounded-xl font-mono text-sm"
                            required
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-semibold"
                            >Giảm giá (%)</Label
                        >
                        <Input
                            type="number"
                            step="0.1"
                            v-model="tierForm.discount_percent"
                            class="rounded-xl font-mono text-sm"
                            required
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-semibold"
                            >Nhân điểm (x)</Label
                        >
                        <Input
                            type="number"
                            step="0.1"
                            v-model="tierForm.points_multiplier"
                            class="rounded-xl font-mono text-sm"
                            required
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-semibold"
                            >Thứ tự hiển thị</Label
                        >
                        <Input
                            type="number"
                            v-model="tierForm.display_order"
                            class="rounded-xl text-sm"
                            required
                        />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs font-semibold"
                        >Màu nhận diện hạng</Label
                    >
                    <div class="flex items-center gap-3">
                        <input
                            type="color"
                            v-model="tierForm.color"
                            class="h-9 w-14 cursor-pointer rounded-lg border border-slate-200 p-0.5 dark:border-slate-800"
                        />
                        <span class="font-mono text-xs text-muted-foreground">{{
                            tierForm.color
                        }}</span>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button
                        variant="outline"
                        type="button"
                        class="rounded-xl"
                        @click="showTierDialog = false"
                        >Hủy</Button
                    >
                    <Button
                        type="submit"
                        :disabled="tierForm.processing"
                        class="rounded-xl bg-amber-600 text-white hover:bg-amber-700"
                    >
                        {{
                            tierForm.processing
                                ? 'Đang lưu...'
                                : editingTier
                                  ? 'Cập nhật'
                                  : 'Tạo mới'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
