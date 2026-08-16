<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Calendar,
    FileText,
    Gavel,
    Award,
    X,
    Info,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { bid } from '@/routes/supplier/rfps';

const props = defineProps<{
    rfps: any[];
    supplier: any;
}>();

const activeRfpId = ref<number | null>(null);

// Form for bidding
const bidForm = useForm({
    proposed_delivery_date: '',
    notes: '',
    items: [] as Array<{
        rfp_item_id: number;
        proposed_price: number;
        ingredient_name: string;
        quantity_required: number;
        unit_symbol: string;
    }>,
});

const openBidForm = (rfp: any) => {
    activeRfpId.value = rfp.id;

    // Check if supplier already submitted a bid
    const existingBid = rfp.bids && rfp.bids[0];

    bidForm.reset();
    bidForm.proposed_delivery_date = existingBid
        ? existingBid.proposed_delivery_date.split(' ')[0]
        : '';
    bidForm.notes = existingBid ? existingBid.notes || '' : '';

    bidForm.items = rfp.items.map((item: any) => {
        const existingBidItem = existingBid?.items?.find(
            (bi: any) => bi.rfp_item_id === item.id,
        );

        return {
            rfp_item_id: item.id,
            proposed_price: existingBidItem
                ? parseFloat(existingBidItem.proposed_price_per_unit)
                : 0,
            ingredient_name: item.ingredient_name,
            quantity_required: parseFloat(item.quantity_required),
            unit_symbol: item.unit_symbol,
        };
    });
};

const calculateTotal = computed(() => {
    return bidForm.items.reduce(
        (sum: number, item: any) =>
            sum + item.quantity_required * item.proposed_price,
        0,
    );
});

const submitBid = (rfpId: number) => {
    bidForm.post(bid.url(rfpId), {
        onSuccess: () => {
            activeRfpId.value = null;
            toast.success('Đã nộp hồ sơ báo giá thầu thành công.');
        },
    });
};

const isDueDatePassed = (dueDateString: string) => {
    return new Date() > new Date(dueDateString);
};

const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'open':
            return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-250 dark:border-emerald-900/50 animate-pulse';
        case 'closed':
            return 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-250 dark:border-amber-900/50';
        case 'completed':
            return 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-250 dark:border-blue-900/50';
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
    <Head title="Cổng Đấu thầu B2B (RFP Portal)" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-6">
        <!-- Header -->
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
                        Cổng Đấu thầu Báo giá (RFP Portal)
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Xem các yêu cầu chào giá thu mua từ phía nhà hàng, nộp
                        báo giá cạnh tranh để giành đơn hàng.
                    </p>
                </div>
            </div>
        </div>

        <!-- RFP Bidding List -->
        <div class="grid grid-cols-1 gap-6">
            <Card
                v-for="rfp in rfps"
                :key="rfp.id"
                class="overflow-hidden transition-all hover:shadow-sm"
            >
                <CardContent class="space-y-6 p-6">
                    <!-- RFP Header Info -->
                    <div
                        class="flex flex-col justify-between gap-4 border-b pb-5 lg:flex-row lg:items-center"
                    >
                        <div class="space-y-2">
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
                                class="max-w-4xl text-xs leading-relaxed text-muted-foreground"
                            >
                                {{
                                    rfp.description ||
                                    'Không có mô tả chi tiết từ phía nhà hàng.'
                                }}
                            </p>
                            <div
                                class="flex items-center gap-4 font-mono text-[10px] text-muted-foreground"
                            >
                                <span class="flex items-center gap-1">
                                    <Calendar
                                        class="h-3.5 w-3.5 text-muted-foreground"
                                    />
                                    Hạn nộp thầu:
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
                                    {{ rfp.items?.length || 0 }} mặt hàng yêu
                                    cầu
                                </span>
                            </div>
                        </div>

                        <!-- Bid Status for this supplier -->
                        <div class="flex shrink-0 items-center gap-3">
                            <!-- If supplier has submitted a bid -->
                            <div
                                v-if="rfp.bids && rfp.bids.length > 0"
                                class="text-right"
                            >
                                <span
                                    class="block text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >Hồ sơ chào thầu của bạn</span
                                >
                                <div
                                    class="mt-1 flex items-center justify-end gap-2"
                                >
                                    <span
                                        class="text-sm font-black text-emerald-600 dark:text-emerald-400"
                                    >
                                        {{
                                            Number(
                                                rfp.bids[0].total_amount,
                                            ).toLocaleString('vi-VN')
                                        }}đ
                                    </span>
                                    <span
                                        v-if="rfp.bids[0].status === 'accepted'"
                                        class="dark:text-blue-450 inline-flex items-center gap-0.5 rounded border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-bold tracking-wide text-blue-700 uppercase dark:border-blue-900/50 dark:bg-blue-950/40"
                                    >
                                        <Award class="h-3 w-3" /> Thắng thầu
                                    </span>
                                    <span
                                        v-else-if="
                                            rfp.bids[0].status === 'rejected'
                                        "
                                        class="dark:text-rose-450 inline-flex items-center rounded border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-bold tracking-wide text-rose-700 uppercase dark:border-rose-900/50 dark:bg-rose-950/40"
                                    >
                                        Từ chối
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded border border-border bg-muted px-2 py-0.5 text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Đã nộp/Đang xét duyệt
                                    </span>
                                </div>
                            </div>

                            <!-- Action buttons -->
                            <Button
                                v-if="
                                    rfp.status === 'open' &&
                                    !isDueDatePassed(rfp.due_date)
                                "
                                @click="openBidForm(rfp)"
                                class="h-8 bg-emerald-600 px-3 text-xs font-bold text-white hover:bg-emerald-700"
                            >
                                {{
                                    rfp.bids && rfp.bids.length > 0
                                        ? 'Sửa báo giá thầu'
                                        : 'Nộp báo giá thầu'
                                }}
                            </Button>
                        </div>
                    </div>

                    <!-- Bidding Panel Form (Expanded) -->
                    <div
                        v-if="activeRfpId === rfp.id"
                        class="animate-in space-y-5 rounded-xl border bg-muted/30 p-5 duration-200 fade-in slide-in-from-top-3"
                    >
                        <div
                            class="flex items-center justify-between border-b pb-3"
                        >
                            <h4
                                class="flex items-center gap-1.5 text-sm font-bold"
                            >
                                <Gavel
                                    class="h-4.5 w-4.5 text-emerald-600 dark:text-emerald-400"
                                />
                                Kê khai hồ sơ thầu & Đơn giá chào thầu
                            </h4>
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="activeRfpId = null"
                                class="h-8 w-8 text-muted-foreground"
                            >
                                <X class="h-4 w-4" />
                            </Button>
                        </div>

                        <form
                            @submit.prevent="submitBid(rfp.id)"
                            class="space-y-4"
                        >
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="space-y-1.5">
                                    <Label
                                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >Ngày cam kết giao hàng
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <Input
                                        v-model="bidForm.proposed_delivery_date"
                                        required
                                        type="date"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <Label
                                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >Cam kết vận chuyển & Ghi chú
                                        thêm</Label
                                    >
                                    <Input
                                        v-model="bidForm.notes"
                                        type="text"
                                        placeholder="Ví dụ: Đảm bảo giao trước 8h sáng, xe chuyên dụng..."
                                    />
                                </div>
                            </div>

                            <!-- Price grid -->
                            <div
                                class="overflow-hidden rounded-xl border bg-card"
                            >
                                <table class="w-full text-xs text-foreground">
                                    <thead
                                        class="border-b bg-muted/40 text-[10px] font-semibold text-muted-foreground uppercase"
                                    >
                                        <tr>
                                            <th class="px-4 py-2 text-left">
                                                Tên nguyên liệu yêu cầu
                                            </th>
                                            <th class="px-4 py-2 text-center">
                                                Số lượng
                                            </th>
                                            <th class="px-4 py-2 text-right">
                                                Đơn giá chào thầu / Đơn vị
                                                <span class="text-rose-500"
                                                    >*</span
                                                >
                                            </th>
                                            <th class="px-4 py-2 text-right">
                                                Thành tiền dự tính
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr
                                            v-for="(item, idx) in bidForm.items"
                                            :key="idx"
                                            class="hover:bg-muted/10"
                                        >
                                            <td class="px-4 py-3 font-semibold">
                                                {{ item.ingredient_name }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-center font-bold text-muted-foreground"
                                            >
                                                {{ item.quantity_required }}
                                                {{ item.unit_symbol }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <div
                                                    class="inline-flex items-center rounded-lg border border-input bg-background px-2 py-1 focus-within:ring-1 focus-within:ring-emerald-500"
                                                >
                                                    <input
                                                        v-model.number="
                                                            item.proposed_price
                                                        "
                                                        required
                                                        type="number"
                                                        min="0"
                                                        class="w-24 border-0 bg-transparent text-right text-xs font-bold text-emerald-600 focus:outline-none dark:text-emerald-400"
                                                    />
                                                    <span
                                                        class="ml-1 text-[10px] font-semibold text-muted-foreground"
                                                        >đ /
                                                        {{
                                                            item.unit_symbol
                                                        }}</span
                                                    >
                                                </div>
                                            </td>
                                            <td
                                                class="px-4 py-3 text-right font-black"
                                            >
                                                {{
                                                    Number(
                                                        item.quantity_required *
                                                            item.proposed_price,
                                                    ).toLocaleString('vi-VN')
                                                }}đ
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Submit Section -->
                            <div
                                class="flex items-center justify-between border-t pt-4"
                            >
                                <div>
                                    <span
                                        class="block text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                        >Tổng giá trị chào thầu:</span
                                    >
                                    <span
                                        class="text-emerald-650 text-lg font-black dark:text-emerald-400"
                                    >
                                        {{
                                            Number(
                                                calculateTotal,
                                            ).toLocaleString('vi-VN')
                                        }}đ
                                    </span>
                                </div>
                                <div class="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="activeRfpId = null"
                                    >
                                        Hủy bỏ
                                    </Button>
                                    <Button
                                        type="submit"
                                        :disabled="bidForm.processing"
                                        class="bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                                    >
                                        {{
                                            bidForm.processing
                                                ? 'Đang nộp...'
                                                : 'Xác nhận Nộp thầu'
                                        }}
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Bid Details for historical/closed/completed RFPs -->
                    <div
                        v-else-if="rfp.bids && rfp.bids.length > 0"
                        class="space-y-3 rounded-xl border bg-muted/20 p-4"
                    >
                        <div
                            class="flex items-center gap-1 text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <Info
                                class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400"
                            />
                            Chi tiết báo giá thầu đã gửi
                        </div>
                        <div
                            class="grid grid-cols-2 gap-4 text-xs md:grid-cols-4"
                        >
                            <div class="rounded-lg border bg-card p-3">
                                <span
                                    class="block text-[10px] font-bold text-muted-foreground uppercase"
                                    >Tổng giá thầu</span
                                >
                                <span
                                    class="mt-1 block text-sm font-black text-emerald-600 dark:text-emerald-400"
                                    >{{
                                        Number(
                                            rfp.bids[0].total_amount,
                                        ).toLocaleString('vi-VN')
                                    }}đ</span
                                >
                            </div>
                            <div class="rounded-lg border bg-card p-3">
                                <span
                                    class="block text-[10px] font-bold text-muted-foreground uppercase"
                                    >Ngày cam kết giao</span
                                >
                                <span
                                    class="mt-1 block text-xs font-semibold"
                                    >{{
                                        new Date(
                                            rfp.bids[0].proposed_delivery_date,
                                        ).toLocaleDateString('vi-VN')
                                    }}</span
                                >
                            </div>
                            <div
                                class="col-span-2 rounded-lg border bg-card p-3"
                            >
                                <span
                                    class="block text-[10px] font-bold text-muted-foreground uppercase"
                                    >Ghi chú & Cam kết</span
                                >
                                <span
                                    class="mt-1 block text-xs text-foreground italic"
                                    >{{
                                        rfp.bids[0].notes ||
                                        'Không đính kèm ghi chú.'
                                    }}</span
                                >
                            </div>
                        </div>

                        <!-- Items prices -->
                        <div
                            class="mt-2 overflow-hidden rounded-lg border bg-card"
                        >
                            <table class="w-full text-[11px] text-foreground">
                                <thead
                                    class="border-b bg-muted/40 text-[9px] font-semibold text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="px-4 py-1.5 text-left">
                                            Tên nguyên liệu
                                        </th>
                                        <th class="px-4 py-1.5 text-center">
                                            Số lượng
                                        </th>
                                        <th class="px-4 py-1.5 text-right">
                                            Giá chào thầu / Đơn vị
                                        </th>
                                        <th class="px-4 py-1.5 text-right">
                                            Thành tiền
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="bitem in rfp.bids[0].items"
                                        :key="bitem.id"
                                    >
                                        <td class="px-4 py-2 font-medium">
                                            {{
                                                bitem.rfp_item?.ingredient_name
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-2 text-center font-bold text-muted-foreground"
                                        >
                                            {{
                                                bitem.rfp_item
                                                    ?.quantity_required
                                            }}
                                            {{ bitem.rfp_item?.unit_symbol }}
                                        </td>
                                        <td
                                            class="px-4 py-2 text-right font-semibold text-emerald-600 dark:text-emerald-400"
                                        >
                                            {{
                                                Number(
                                                    bitem.proposed_price_per_unit,
                                                ).toLocaleString('vi-VN')
                                            }}đ
                                        </td>
                                        <td
                                            class="px-4 py-2 text-right font-bold"
                                        >
                                            {{
                                                Number(
                                                    bitem.rfp_item
                                                        ?.quantity_required *
                                                        bitem.proposed_price_per_unit,
                                                ).toLocaleString('vi-VN')
                                            }}đ
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Empty RFP List -->
            <div
                v-if="rfps.length === 0"
                class="rounded-2xl border border-dashed border-border bg-muted/20 py-16 text-center"
            >
                <Gavel
                    class="mx-auto mb-3 h-12 w-12 text-muted-foreground opacity-60"
                />
                <p class="text-sm font-medium text-muted-foreground">
                    Hiện không có yêu cầu chào thầu (RFP) nào khả dụng từ phía
                    nhà hàng.
                </p>
            </div>
        </div>
    </div>
</template>
