<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Plus,
    ChevronDown,
    ChevronUp,
    Check,
    X,
    Calendar,
    FileText,
    Gavel,
    Award,
    Building2,
    Clock,
    ShieldAlert,
    AlertCircle,
    ShoppingCart,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
import { confirmDialog } from '@/composables/useConfirm';

const props = defineProps<{
    rfps: any[];
}>();

const currentPage = ref(1);
const itemsPerPage = 10;
const totalPages = computed(() => Math.ceil(props.rfps.length / itemsPerPage));
const paginatedRfps = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;

    return props.rfps.slice(start, start + itemsPerPage);
});
const visiblePages = computed(() => {
    const pages = [];
    const maxVisible = 5;
    let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2));
    const end = Math.min(totalPages.value, start + maxVisible - 1);

    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }

    return pages;
});
watch(
    () => props.rfps,
    () => {
        currentPage.value = 1;
    },
);

const page = usePage();
const roles = computed(() => {
    const raw = page.props.roles ?? [];

    return Array.isArray(raw)
        ? raw
        : Object.values(raw as Record<string, string>);
});
const isOwner = computed(() => roles.value.includes('owner'));

// Modal states
const showCreateModal = ref(false);
const activeRfpId = ref<number | null>(null);
const activeBidId = ref<number | null>(null);

// Form for creating RFP
const rfpForm = useForm({
    title: '',
    description: '',
    due_date: '',
    items: [
        {
            ingredient_name: '',
            quantity_required: 1,
            unit_symbol: 'kg',
            notes: '',
        },
    ],
});

const addRfpItem = () => {
    rfpForm.items.push({
        ingredient_name: '',
        quantity_required: 1,
        unit_symbol: 'kg',
        notes: '',
    });
};

const removeRfpItem = (index: number | string) => {
    rfpForm.items.splice(Number(index), 1);
};

const submitRfp = () => {
    rfpForm.post(route('rfps.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            rfpForm.reset();
        },
    });
};

const closeRfp = async (rfpId: number) => {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description:
                'Bạn có chắc chắn muốn đóng thầu sớm? Các nhà cung cấp sẽ không thể gửi thêm báo giá.',
            variant: 'default',
        })
    ) {
        router.post(route('rfps.close', rfpId));
    }
};

const acceptBid = async (bidId: number) => {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description:
                'Bạn có chắc chắn chọn hồ sơ báo giá này làm nhà thầu chiến thắng? Hệ thống sẽ tự động tạo đơn hàng PO tương ứng và gửi đi.',
            variant: 'default',
        })
    ) {
        router.post(route('rfps.bids.accept', bidId));
    }
};

const toggleRfpDetails = (id: number) => {
    activeRfpId.value = activeRfpId.value === id ? null : id;
};

const toggleBidDetails = (id: number) => {
    activeBidId.value = activeBidId.value === id ? null : id;
};

const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'open':
            return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50 animate-pulse';
        case 'closed':
            return 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50';
        case 'completed':
            return 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50';
        default:
            return 'bg-muted text-muted-foreground border border-border';
    }
};

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'open':
            return 'Đang mở thầu';
        case 'closed':
            return 'Đã đóng thầu';
        case 'completed':
            return 'Đã hoàn thành';
        default:
            return status;
    }
};
</script>

<template>
    <Head title="Quản lý Đấu thầu Báo giá RFP" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-6">
        <!-- Page Header -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-500"
                >
                    <Gavel class="size-6" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        Quản lý Đấu thầu Báo giá (RFP)
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Đăng yêu cầu thu mua công khai, kêu gọi báo giá cạnh
                        tranh từ các nhà cung cấp nhằm tối ưu hóa chi phí và
                        triệt tiêu tiêu cực.
                    </p>
                </div>
            </div>
            <Button
                @click="showCreateModal = true"
                class="h-9 bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700"
            >
                <Plus class="mr-1.5 h-4 w-4" />
                Tạo yêu cầu RFP mới
            </Button>
        </div>

        <!-- RFP List -->
        <div class="space-y-4">
            <Card
                v-for="rfp in paginatedRfps"
                :key="rfp.id"
                class="overflow-hidden transition-all hover:shadow-sm"
            >
                <!-- RFP Accordion Header -->
                <div
                    @click="toggleRfpDetails(rfp.id)"
                    class="flex cursor-pointer flex-col justify-between gap-4 p-5 hover:bg-muted/10 md:flex-row md:items-center"
                >
                    <div class="space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <span
                                :class="[
                                    'rounded px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase',
                                    getStatusBadgeClass(rfp.status),
                                ]"
                            >
                                {{ getStatusLabel(rfp.status) }}
                            </span>
                            <h3 class="text-base font-bold">
                                #RFP-{{ rfp.id }}: {{ rfp.title }}
                            </h3>
                        </div>
                        <p
                            class="line-clamp-1 max-w-3xl text-xs leading-relaxed text-muted-foreground"
                        >
                            {{ rfp.description || 'Không có mô tả chi tiết.' }}
                        </p>
                        <div
                            class="flex items-center gap-4 font-mono text-[10px] text-muted-foreground"
                        >
                            <span class="flex items-center gap-1">
                                <Calendar
                                    class="h-3.5 w-3.5 text-muted-foreground"
                                />
                                Hạn nộp:
                                {{
                                    new Date(rfp.due_date).toLocaleString(
                                        'vi-VN',
                                    )
                                }}
                            </span>
                            <span class="flex items-center gap-1">
                                <FileText
                                    class="h-3.5 w-3.5 text-muted-foreground"
                                />
                                {{ rfp.items?.length || 0 }} mặt hàng
                            </span>
                            <span
                                class="flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                <Gavel class="h-3.5 w-3.5" />
                                {{ rfp.bids?.length || 0 }} hồ sơ nộp thầu
                            </span>
                        </div>
                    </div>

                    <div
                        class="flex shrink-0 items-center gap-3 self-end md:self-auto"
                    >
                        <Button
                            v-if="rfp.status === 'open'"
                            @click.stop="closeRfp(rfp.id)"
                            variant="outline"
                            size="sm"
                            class="h-8 border-amber-200 text-xs font-semibold text-amber-600 hover:bg-amber-50 dark:border-amber-900/50 dark:text-amber-400"
                        >
                            Đóng thầu sớm
                        </Button>
                        <div
                            class="rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            <ChevronDown
                                v-if="activeRfpId !== rfp.id"
                                class="h-5 w-5"
                            />
                            <ChevronUp v-else class="h-5 w-5" />
                        </div>
                    </div>
                </div>

                <!-- RFP Accordion Content -->
                <div
                    v-if="activeRfpId === rfp.id"
                    class="space-y-6 border-t bg-muted/10 p-5"
                >
                    <!-- Required Items -->
                    <div class="space-y-3">
                        <h4
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <FileText
                                class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                            />
                            Danh sách nguyên liệu yêu cầu
                        </h4>
                        <div class="overflow-hidden rounded-lg border bg-card">
                            <table class="w-full text-xs text-foreground">
                                <thead
                                    class="border-b bg-muted/40 text-[10px] font-semibold text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="px-4 py-2 text-left">
                                            Tên nguyên liệu
                                        </th>
                                        <th class="px-4 py-2 text-center">
                                            Số lượng yêu cầu
                                        </th>
                                        <th class="px-4 py-2 text-left">
                                            Ghi chú quy cách
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="item in rfp.items"
                                        :key="item.id"
                                    >
                                        <td class="px-4 py-2.5 font-bold">
                                            {{ item.ingredient_name }}
                                        </td>
                                        <td
                                            class="px-4 py-2.5 text-center font-bold text-emerald-600 dark:text-emerald-400"
                                        >
                                            {{
                                                Number(
                                                    item.quantity_required,
                                                ).toLocaleString()
                                            }}
                                            {{ item.unit_symbol }}
                                        </td>
                                        <td
                                            class="px-4 py-2.5 text-muted-foreground"
                                        >
                                            {{ item.notes || '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Bids Section -->
                    <div class="space-y-3">
                        <h4
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <Gavel
                                class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                            />
                            Hồ sơ chào thầu đã nhận
                        </h4>

                        <div
                            v-if="rfp.bids && rfp.bids.length > 0"
                            class="space-y-3"
                        >
                            <div
                                v-for="bid in rfp.bids"
                                :key="bid.id"
                                class="space-y-4 rounded-xl border border-border bg-muted/30 p-4"
                            >
                                <!-- Bid Summary -->
                                <div
                                    class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
                                >
                                    <div class="flex items-start gap-3">
                                        <div
                                            class="dark:text-emerald-450 border-emerald-250 shrink-0 rounded-lg border bg-emerald-50 p-2 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40"
                                        >
                                            <Building2 class="h-5 w-5" />
                                        </div>
                                        <div>
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <h5 class="text-sm font-bold">
                                                    {{ bid.supplier?.name }}
                                                </h5>
                                                <span
                                                    v-if="bid.is_ai_recommended"
                                                    class="inline-flex animate-pulse items-center gap-0.5 rounded-full border border-purple-200 bg-purple-100 px-2 py-0.5 text-[9px] font-extrabold tracking-wider text-purple-700 uppercase shadow-sm dark:border-purple-900 dark:bg-purple-950 dark:text-purple-400"
                                                >
                                                    ✨ AI Khuyên Chọn
                                                </span>
                                                <span
                                                    v-if="
                                                        bid.status ===
                                                        'accepted'
                                                    "
                                                    class="dark:text-blue-450 inline-flex items-center gap-0.5 rounded border border-blue-200 bg-blue-50 px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-blue-700 uppercase dark:border-blue-900/50 dark:bg-blue-950/40"
                                                >
                                                    <Award
                                                        class="h-2.5 w-2.5"
                                                    />
                                                    Thắng thầu (PO đã tạo)
                                                </span>
                                                <span
                                                    v-else-if="
                                                        bid.status ===
                                                        'rejected'
                                                    "
                                                    class="inline-flex items-center rounded border border-border bg-muted px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-muted-foreground uppercase"
                                                >
                                                    Từ chối
                                                </span>
                                            </div>
                                            <div
                                                class="mt-1 flex flex-wrap items-center gap-3 font-mono text-[10px] text-muted-foreground"
                                            >
                                                <span
                                                    class="flex items-center gap-1"
                                                >
                                                    <Clock
                                                        class="h-3.5 w-3.5 text-muted-foreground"
                                                    />
                                                    Cam kết giao:
                                                    {{
                                                        new Date(
                                                            bid.proposed_delivery_date,
                                                        ).toLocaleDateString(
                                                            'vi-VN',
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    v-if="bid.ai_score"
                                                    class="flex items-center gap-1 rounded border border-purple-100 bg-purple-50 px-1.5 py-0.5 font-bold text-purple-600 dark:border-purple-900/40 dark:bg-purple-950/40 dark:text-purple-400"
                                                >
                                                    🎯 AI Chấm:
                                                    {{ bid.ai_score }} / 10
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="flex shrink-0 items-center gap-4 self-end sm:self-auto"
                                    >
                                        <div class="text-right">
                                            <span
                                                class="block text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                                >Tổng tiền chào thầu</span
                                            >
                                            <span
                                                class="text-md font-black text-emerald-600 dark:text-emerald-400"
                                            >
                                                {{
                                                    Number(
                                                        bid.total_amount,
                                                    ).toLocaleString('vi-VN')
                                                }}đ
                                            </span>
                                        </div>

                                        <div class="flex gap-2">
                                            <Button
                                                @click="
                                                    toggleBidDetails(bid.id)
                                                "
                                                variant="outline"
                                                size="sm"
                                                class="h-8 text-xs font-semibold"
                                            >
                                                {{
                                                    activeBidId === bid.id
                                                        ? 'Ẩn chi tiết'
                                                        : 'Chi tiết giá'
                                                }}
                                            </Button>
                                            <Button
                                                v-if="
                                                    rfp.status !==
                                                        'completed' && isOwner
                                                "
                                                @click="acceptBid(bid.id)"
                                                size="sm"
                                                class="h-8 bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                                            >
                                                <Check
                                                    class="mr-1 h-3.5 w-3.5"
                                                />
                                                Chọn Thắng Thầu
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <!-- AI Recommended Reason Details -->
                                <div
                                    v-if="
                                        bid.is_ai_recommended && bid.ai_reason
                                    "
                                    class="flex gap-2.5 rounded-xl border border-purple-200 bg-purple-50/50 p-3 text-xs dark:border-purple-900/50 dark:bg-purple-950/10"
                                >
                                    <Sparkles
                                        class="mt-0.5 h-4 w-4 shrink-0 text-purple-500"
                                    />
                                    <div>
                                        <h6
                                            class="text-purple-750 text-[11px] font-extrabold dark:text-purple-400"
                                        >
                                            Đánh giá từ AI cố vấn mua hàng
                                        </h6>
                                        <p
                                            class="dark:text-slate-350 mt-0.5 text-[10px] leading-relaxed text-slate-700"
                                        >
                                            {{ bid.ai_reason }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Bid details (mặt hàng chi tiết) -->
                                <div
                                    v-if="activeBidId === bid.id"
                                    class="mt-3 animate-in border-t border-border pt-3 duration-150 fade-in slide-in-from-top-2"
                                >
                                    <div
                                        class="overflow-hidden rounded-lg border bg-card"
                                    >
                                        <table
                                            class="w-full text-[11px] text-foreground"
                                        >
                                            <thead
                                                class="border-b bg-muted/40 text-[9px] font-semibold text-muted-foreground uppercase"
                                            >
                                                <tr>
                                                    <th
                                                        class="px-4 py-1.5 text-left"
                                                    >
                                                        Tên nguyên liệu
                                                    </th>
                                                    <th
                                                        class="px-4 py-1.5 text-center"
                                                    >
                                                        Số lượng
                                                    </th>
                                                    <th
                                                        class="px-4 py-1.5 text-right"
                                                    >
                                                        Giá chào thầu / Đơn vị
                                                    </th>
                                                    <th
                                                        class="px-4 py-1.5 text-right"
                                                    >
                                                        Tổng cộng
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody
                                                class="divide-y divide-border"
                                            >
                                                <tr
                                                    v-for="bitem in bid.items"
                                                    :key="bitem.id"
                                                >
                                                    <td
                                                        class="px-4 py-2 font-semibold"
                                                    >
                                                        {{
                                                            bitem.rfp_item
                                                                ?.ingredient_name
                                                        }}
                                                    </td>
                                                    <td
                                                        class="px-4 py-2 text-center font-mono text-muted-foreground"
                                                    >
                                                        {{
                                                            bitem.rfp_item
                                                                ?.quantity_required
                                                        }}
                                                        {{
                                                            bitem.rfp_item
                                                                ?.unit_symbol
                                                        }}
                                                    </td>
                                                    <td
                                                        class="px-4 py-2 text-right font-mono text-emerald-600 dark:text-emerald-400"
                                                    >
                                                        {{
                                                            Number(
                                                                bitem.proposed_price_per_unit,
                                                            ).toLocaleString(
                                                                'vi-VN',
                                                            )
                                                        }}đ
                                                    </td>
                                                    <td
                                                        class="px-4 py-2 text-right font-mono font-semibold"
                                                    >
                                                        {{
                                                            Number(
                                                                bitem.rfp_item
                                                                    ?.quantity_required *
                                                                    bitem.proposed_price_per_unit,
                                                            ).toLocaleString(
                                                                'vi-VN',
                                                            )
                                                        }}đ
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div
                                        v-if="bid.notes"
                                        class="mt-2.5 rounded-lg border bg-muted/40 p-2.5 text-xs"
                                    >
                                        <span
                                            class="block text-[10px] font-bold text-muted-foreground uppercase"
                                            >Cam kết & Ghi chú từ đối tác</span
                                        >
                                        <p
                                            class="mt-0.5 leading-relaxed text-foreground"
                                        >
                                            {{ bid.notes }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="rounded-xl border border-dashed border-border bg-muted/10 py-8 text-center"
                        >
                            <AlertCircle
                                class="mx-auto mb-2 h-8 w-8 text-muted-foreground opacity-60"
                            />
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Chưa có nhà cung cấp nào nộp hồ sơ thầu.
                            </p>
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Pagination -->
            <div
                v-if="totalPages > 1"
                class="mt-4 flex items-center justify-between border-t bg-slate-50/50 pt-4 dark:bg-slate-900/30"
            >
                <div class="text-xs text-muted-foreground">
                    Hiển thị {{ (currentPage - 1) * itemsPerPage + 1 }} -
                    {{ Math.min(currentPage * itemsPerPage, rfps.length) }}
                    trong tổng số {{ rfps.length }} yêu cầu RFP
                </div>
                <div class="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="currentPage === 1"
                        @click="currentPage--"
                        class="h-8 text-xs"
                    >
                        Trước
                    </Button>
                    <Button
                        v-for="page in visiblePages"
                        :key="page"
                        variant="outline"
                        size="sm"
                        @click="currentPage = page"
                        :class="[
                            'h-8 w-8 p-0 text-xs',
                            currentPage === page
                                ? 'bg-emerald-600 font-semibold text-white hover:bg-emerald-700'
                                : '',
                        ]"
                    >
                        {{ page }}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="currentPage === totalPages"
                        @click="currentPage++"
                        class="h-8 text-xs"
                    >
                        Sau
                    </Button>
                </div>
            </div>

            <!-- Empty RFP State -->
            <div
                v-if="rfps.length === 0"
                class="rounded-2xl border border-dashed border-border bg-muted/20 py-16 text-center"
            >
                <Gavel
                    class="mx-auto mb-3 h-12 w-12 text-muted-foreground opacity-60"
                />
                <p class="text-sm font-medium text-muted-foreground">
                    Chưa có yêu cầu chào thầu (RFP) nào được tạo.
                </p>
                <Button
                    @click="showCreateModal = true"
                    class="mt-4 bg-emerald-600 font-semibold text-white hover:bg-emerald-700"
                    >Tạo yêu cầu RFP đầu tiên</Button
                >
            </div>
        </div>

        <!-- Create RFP Modal -->
        <div
            v-if="showCreateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-2xl animate-in overflow-hidden shadow-2xl duration-150 zoom-in-95 fade-in"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between border-b pb-4"
                >
                    <CardTitle class="text-lg font-bold"
                        >Tạo yêu cầu chào thầu báo giá (RFP) mới</CardTitle
                    >
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="showCreateModal = false"
                        class="h-8 w-8 text-muted-foreground"
                    >
                        <X class="h-5 w-5" />
                    </Button>
                </CardHeader>

                <form
                    @submit.prevent="submitRfp"
                    class="max-h-[80vh] space-y-4 overflow-y-auto p-6"
                >
                    <div class="space-y-4">
                        <div class="space-y-1.5">
                            <Label
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >Tiêu đề yêu cầu thầu
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                v-model="rfpForm.title"
                                required
                                type="text"
                                placeholder="Ví dụ: Cung ứng Thịt & Hải sản tươi sống tháng 7/2026"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >Mô tả chi tiết & Tiêu chí kỹ thuật</Label
                            >
                            <textarea
                                v-model="rfpForm.description"
                                rows="3"
                                class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:outline-none"
                                placeholder="Mô tả tiêu chuẩn chất lượng, quy cách đóng gói, tần suất giao hàng..."
                            ></textarea>
                        </div>
                        <div class="space-y-1.5">
                            <Label
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >Thời hạn nộp hồ sơ thầu
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                v-model="rfpForm.due_date"
                                required
                                type="datetime-local"
                            />
                        </div>
                    </div>

                    <div class="mt-6 border-t pt-4">
                        <div class="mb-3 flex items-center justify-between">
                            <h4
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Danh mục mặt hàng cần chào giá
                                <span class="text-rose-500">*</span>
                            </h4>
                            <Button
                                type="button"
                                variant="ghost"
                                @click="addRfpItem"
                                class="h-auto p-0 text-xs text-emerald-600 hover:text-emerald-700"
                            >
                                <Plus class="mr-1 h-4 w-4" /> Thêm mặt hàng
                            </Button>
                        </div>

                        <!-- Dynamic Items Form -->
                        <div class="space-y-3">
                            <div
                                v-for="(item, idx) in rfpForm.items"
                                :key="idx"
                                class="group relative grid grid-cols-12 gap-3 rounded-xl border border-border bg-muted/40 p-3"
                            >
                                <div class="col-span-5 space-y-1">
                                    <Label
                                        class="text-[10px] font-bold text-muted-foreground uppercase"
                                        >Tên nguyên liệu</Label
                                    >
                                    <Input
                                        v-model="item.ingredient_name"
                                        required
                                        type="text"
                                        placeholder="Bột mì, Thịt bò Wagyu..."
                                        class="h-8 text-xs"
                                    />
                                </div>
                                <div class="col-span-3 space-y-1">
                                    <Label
                                        class="text-[10px] font-bold text-muted-foreground uppercase"
                                        >Số lượng</Label
                                    >
                                    <Input
                                        v-model="item.quantity_required"
                                        required
                                        type="number"
                                        step="0.001"
                                        class="h-8 text-xs"
                                    />
                                </div>
                                <div class="col-span-3 space-y-1">
                                    <Label
                                        class="text-[10px] font-bold text-muted-foreground uppercase"
                                        >Đơn vị</Label
                                    >
                                    <Input
                                        v-model="item.unit_symbol"
                                        required
                                        type="text"
                                        placeholder="kg, lít, hộp..."
                                        class="h-8 text-xs"
                                    />
                                </div>
                                <div
                                    class="col-span-1 flex items-end justify-center pb-1.5"
                                >
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :disabled="rfpForm.items.length === 1"
                                        @click="removeRfpItem(idx)"
                                        class="h-8 w-8 text-rose-500 hover:bg-rose-50 hover:text-rose-600 disabled:opacity-40"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                                <div class="col-span-11 space-y-1">
                                    <Label
                                        class="text-[10px] font-bold text-muted-foreground uppercase"
                                        >Yêu cầu tiêu chuẩn kỹ thuật (Ghi
                                        chú)</Label
                                    >
                                    <Input
                                        v-model="item.notes"
                                        type="text"
                                        placeholder="Hàng loại 1, bảo quản mát dưới 4 độ, đóng khay xốp..."
                                        class="h-8 text-xs"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2 border-t pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showCreateModal = false"
                        >
                            Hủy bỏ
                        </Button>
                        <Button
                            type="submit"
                            :disabled="rfpForm.processing"
                            class="bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            {{
                                rfpForm.processing
                                    ? 'Đang gửi...'
                                    : 'Đăng tải thầu RFP'
                            }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    </div>
</template>
