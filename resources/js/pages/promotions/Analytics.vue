<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BarChart3,
    DollarSign,
    RefreshCw,
    ShoppingCart,
    TrendingUp,
    UserPlus,
} from 'lucide-vue-next';
import { ref } from 'vue';
import BackButton from '@/components/BackButton.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    metrics: {
        total_discount: number;
        total_revenue: number;
        total_uses: number;
        roi_percent: number;
        new_customers_acquired: number;
        repeat_rate: number;
        daily: Array<{
            date: string;
            discount: number;
            revenue: number;
            uses: number;
            new_customers: number;
            repeat_rate: number;
        }>;
        per_promotion: Array<{
            promotion_id: number;
            name: string;
            code: string | null;
            type: 'percent' | 'fixed_amount';
            value: number;
            uses: number;
            unique_customers: number;
            discount_given: number;
            revenue_influenced: number;
            avg_order_value: number;
            roi_percent: number;
            bypass_count: number;
        }>;
    };
    filters: { start_date: string; end_date: string };
}>();

const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);

function applyFilter() {
    router.get(
        '/promotions/analytics',
        { start_date: startDate.value, end_date: endDate.value },
        { preserveState: true, replace: true },
    );
}

function formatVND(val: number) {
    return new Intl.NumberFormat('vi-VN').format(val);
}

const maxRevenue = Math.max(
    ...(props.metrics.daily.map((d) => d.revenue) || [1]),
    1,
);
</script>

<template>
    <Head title="Phân tích Khuyến mãi" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <BackButton fallback-href="/promotions" label="Khuyến mãi" />
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Phân tích Hiệu quả Khuyến mãi
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        ROI, redemption rate, chi phí vs doanh thu.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Input v-model="startDate" type="date" class="w-[150px]" />
                <Input v-model="endDate" type="date" class="w-[150px]" />
                <Button variant="outline" size="sm" @click="applyFilter"
                    >Lọc</Button
                >
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-rose-500/10 p-2.5 text-rose-600">
                        <DollarSign class="size-5" />
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Chi phí KM
                        </p>
                        <p class="text-xl font-bold text-rose-600">
                            {{ formatVND(metrics.total_discount) }}₫
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <div
                        class="rounded-xl bg-emerald-500/10 p-2.5 text-emerald-600"
                    >
                        <TrendingUp class="size-5" />
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Doanh thu từ KM
                        </p>
                        <p class="text-xl font-bold text-emerald-600">
                            {{ formatVND(metrics.total_revenue) }}₫
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-sky-500/10 p-2.5 text-sky-600">
                        <ShoppingCart class="size-5" />
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Lần sử dụng
                        </p>
                        <p class="text-xl font-bold">
                            {{ metrics.total_uses }}
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <div
                        class="rounded-xl bg-violet-500/10 p-2.5 text-violet-600"
                    >
                        <BarChart3 class="size-5" />
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            ROI
                        </p>
                        <p
                            :class="[
                                'text-xl font-bold',
                                metrics.roi_percent > 100
                                    ? 'text-emerald-600'
                                    : 'text-rose-600',
                            ]"
                        >
                            {{ metrics.roi_percent }}%
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <div
                        class="rounded-xl bg-amber-500/10 p-2.5 text-amber-600"
                    >
                        <UserPlus class="size-5" />
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Khách mới nhờ KM
                        </p>
                        <p class="text-xl font-bold text-amber-600">
                            {{ metrics.new_customers_acquired }}
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-teal-500/10 p-2.5 text-teal-600">
                        <RefreshCw class="size-5" />
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Khách quay lại dùng KM
                        </p>
                        <p class="text-xl font-bold text-teal-600">
                            {{ metrics.repeat_rate }}%
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader class="pb-3"
                ><CardTitle class="text-sm"
                    >Doanh thu từ KM theo ngày</CardTitle
                ></CardHeader
            >
            <CardContent>
                <div v-if="metrics.daily.length" class="space-y-1.5">
                    <div
                        v-for="day in metrics.daily"
                        :key="day.date"
                        class="flex items-center gap-3"
                    >
                        <span
                            class="w-12 shrink-0 font-mono text-[10px] text-muted-foreground"
                            >{{ day.date }}</span
                        >
                        <div
                            class="h-4 flex-1 overflow-hidden rounded-full bg-muted/30"
                        >
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all duration-500"
                                :style="{
                                    width: `${(day.revenue / maxRevenue) * 100}%`,
                                }"
                            />
                        </div>
                        <span
                            class="w-24 shrink-0 text-right font-mono text-[10px] text-muted-foreground tabular-nums"
                            >{{ formatVND(day.revenue) }}₫</span
                        >
                        <span
                            class="w-10 shrink-0 text-right font-mono text-[10px] text-muted-foreground"
                            >{{ day.uses }}x</span
                        >
                    </div>
                </div>
                <p
                    v-else
                    class="py-10 text-center text-sm text-muted-foreground"
                >
                    Chưa có dữ liệu trong khoảng thời gian này.
                </p>
                <p
                    class="mt-4 border-t pt-3 text-[10px] leading-relaxed text-muted-foreground"
                >
                    Các số liệu tổng phía trên tính trên
                    <strong>mọi khoản giảm giá</strong> của đơn hàng — bao gồm
                    cả điểm tích lũy, ưu đãi hạng VIP và giảm giá thủ công. Bảng
                    bên dưới mới quy đúng từng đồng chiết khấu về đúng mã
                    voucher đã tạo ra nó.
                </p>
            </CardContent>
        </Card>

        <!-- Hiệu quả từng chương trình: chỉ có được sau khi hệ thống lưu vết
             mỗi lượt áp mã vào bảng promotion_usages. Trước đây snapshot luôn
             ghi promotion_id = null nên không thể tách theo từng mã. -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-sm"
                    >Hiệu quả theo từng chương trình</CardTitle
                >
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-if="metrics.per_promotion.length"
                    class="overflow-x-auto"
                >
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b bg-muted/40 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                <th class="p-3">Chương trình</th>
                                <th class="p-3">Mã</th>
                                <th class="p-3 text-right">Lượt dùng</th>
                                <th class="p-3 text-right">Khách riêng biệt</th>
                                <th class="p-3 text-right">
                                    Chi phí chiết khấu
                                </th>
                                <th class="p-3 text-right">
                                    Doanh thu tác động
                                </th>
                                <th class="p-3 text-right">Giá trị đơn TB</th>
                                <th class="p-3 text-right">ROI</th>
                                <th class="p-3 text-right">Vượt quyền</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="row in metrics.per_promotion"
                                :key="row.promotion_id"
                                class="transition-colors hover:bg-muted/20"
                            >
                                <td class="p-3 font-bold">{{ row.name }}</td>
                                <td class="p-3">
                                    <span
                                        v-if="row.code"
                                        class="rounded border border-indigo-100 bg-indigo-50 px-2 py-0.5 font-mono font-bold text-indigo-600 dark:border-indigo-900/40 dark:bg-indigo-950/40 dark:text-indigo-400"
                                        >{{ row.code }}</span
                                    >
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </td>
                                <td
                                    class="p-3 text-right font-mono font-bold tabular-nums"
                                >
                                    {{ row.uses }}
                                </td>
                                <td
                                    class="p-3 text-right font-mono text-muted-foreground tabular-nums"
                                >
                                    {{ row.unique_customers }}
                                </td>
                                <td
                                    class="p-3 text-right font-mono text-rose-600 tabular-nums"
                                >
                                    -{{ formatVND(row.discount_given) }}₫
                                </td>
                                <td
                                    class="p-3 text-right font-mono text-emerald-600 tabular-nums"
                                >
                                    {{ formatVND(row.revenue_influenced) }}₫
                                </td>
                                <td
                                    class="p-3 text-right font-mono text-muted-foreground tabular-nums"
                                >
                                    {{ formatVND(row.avg_order_value) }}₫
                                </td>
                                <td
                                    class="p-3 text-right font-mono font-bold tabular-nums"
                                    :class="
                                        row.roi_percent >= 100
                                            ? 'text-emerald-600'
                                            : 'text-amber-600'
                                    "
                                >
                                    {{ row.roi_percent }}%
                                </td>
                                <td class="p-3 text-right">
                                    <span
                                        v-if="row.bypass_count > 0"
                                        class="rounded bg-rose-100 px-1.5 py-0.5 font-mono text-[10px] font-black text-rose-700 dark:bg-rose-950/40 dark:text-rose-400"
                                        title="Số lượt phải dùng mã phê duyệt của quản lý để vượt cảnh báo gian lận"
                                        >{{ row.bypass_count }}</span
                                    >
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    v-else
                    class="py-10 text-center text-sm text-muted-foreground"
                >
                    Chưa ghi nhận lượt áp mã nào trong khoảng thời gian này.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
