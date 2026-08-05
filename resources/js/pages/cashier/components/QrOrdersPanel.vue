<script setup lang="ts">
import { Sparkles, Clock } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card';

defineProps<{
    qrOrders: any[];
    externalOrders: any[];
    confirmingOrderId?: number | null;
    updatingExternalId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'confirmQrOrder', orderId: number): void;
    (
        e: 'updateExternalOrderStatus',
        payload: { orderId: number; status: string },
    ): void;
}>();

const numberFormat = (val: number) =>
    new Intl.NumberFormat('vi-VN').format(val);
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Đơn QR Tại Bàn Chờ Xác Nhận -->
        <Card
            class="rounded-3xl border-slate-200 shadow-sm dark:border-slate-800"
        >
            <CardHeader
                class="border-b border-slate-100 pb-4 dark:border-slate-800"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-left">
                        <Sparkles
                            class="size-5 animate-pulse text-indigo-600"
                        />
                        <div>
                            <CardTitle class="text-base font-black"
                                >Đơn QR tại bàn chờ duyệt</CardTitle
                            >
                            <CardDescription class="text-xs"
                                >Khách tự quét mã QR gọi món tại
                                bàn</CardDescription
                            >
                        </div>
                    </div>
                    <Badge
                        variant="secondary"
                        class="rounded-xl bg-indigo-50 font-mono text-xs font-bold text-indigo-600"
                    >
                        {{ qrOrders.length }} đơn mới
                    </Badge>
                </div>
            </CardHeader>

            <CardContent class="p-6">
                <div
                    v-if="qrOrders.length === 0"
                    class="flex h-32 flex-col items-center justify-center text-slate-400"
                >
                    <Sparkles class="size-8 stroke-1" />
                    <p class="mt-2 text-xs font-bold">
                        Hiện không có đơn QR mới nào
                    </p>
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="order in qrOrders"
                        :key="order.id"
                        class="flex flex-col justify-between rounded-2xl border border-indigo-100 bg-indigo-50/30 p-4 text-left dark:border-indigo-900/30 dark:bg-indigo-950/20"
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <span
                                    class="font-black text-slate-800 dark:text-slate-100"
                                >
                                    Bàn
                                    {{ order.table?.name || 'Chưa chọn bàn' }}
                                </span>
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                    >#{{ order.order_number }}</span
                                >
                            </div>

                            <div
                                class="mt-3 flex flex-col gap-1 border-t border-indigo-100/60 pt-2 text-xs dark:border-indigo-900/40"
                            >
                                <div
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="flex justify-between"
                                >
                                    <span
                                        class="text-slate-600 dark:text-slate-300"
                                    >
                                        {{ item.quantity }}x
                                        {{ item.product?.name || 'Món' }}
                                    </span>
                                    <span class="font-mono font-bold"
                                        >{{
                                            numberFormat(
                                                item.unit_price * item.quantity,
                                            )
                                        }}đ</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-4 flex items-center justify-between border-t border-indigo-100/60 pt-3 dark:border-indigo-900/40"
                        >
                            <span
                                class="font-mono text-sm font-black text-indigo-600 dark:text-indigo-400"
                            >
                                {{ numberFormat(order.total_amount) }}đ
                            </span>
                            <Button
                                size="sm"
                                class="rounded-xl bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                                :disabled="confirmingOrderId === order.id"
                                @click="emit('confirmQrOrder', order.id)"
                            >
                                {{
                                    confirmingOrderId === order.id
                                        ? 'Đang duyệt...'
                                        : 'Duyệt đơn QR'
                                }}
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Đơn hàng Ngoại sàn / Delivery -->
        <Card
            class="rounded-3xl border-slate-200 shadow-sm dark:border-slate-800"
        >
            <CardHeader
                class="border-b border-slate-100 pb-4 dark:border-slate-800"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-left">
                        <Clock class="size-5 text-emerald-600" />
                        <div>
                            <CardTitle class="text-base font-black"
                                >Đơn Mang về & Giao hàng</CardTitle
                            >
                            <CardDescription class="text-xs"
                                >Quản lý trạng thái các đơn online, mang
                                về</CardDescription
                            >
                        </div>
                    </div>
                    <Badge
                        variant="secondary"
                        class="rounded-xl bg-emerald-50 font-mono text-xs font-bold text-emerald-600"
                    >
                        {{ externalOrders.length }} đơn đang chạy
                    </Badge>
                </div>
            </CardHeader>

            <CardContent class="p-6">
                <div
                    v-if="externalOrders.length === 0"
                    class="flex h-32 flex-col items-center justify-center text-slate-400"
                >
                    <Clock class="size-8 stroke-1" />
                    <p class="mt-2 text-xs font-bold">
                        Không có đơn hàng mang về nào đang xử lý
                    </p>
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="order in externalOrders"
                        :key="order.id"
                        class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <span
                                    class="font-black text-slate-800 dark:text-slate-100"
                                    >#{{ order.order_number }}</span
                                >
                                <Badge
                                    variant="outline"
                                    class="text-[10px] font-bold"
                                >
                                    {{
                                        order.order_type === 'takeaway'
                                            ? 'Mang về'
                                            : 'Giao hàng'
                                    }}
                                </Badge>
                            </div>

                            <div
                                class="mt-2 text-xs font-bold text-slate-600 dark:text-slate-300"
                            >
                                👤 {{ order.customer_name || 'Khách lẻ' }}
                            </div>

                            <div
                                class="mt-2 flex flex-col gap-1 border-t pt-2 text-xs"
                            >
                                <div
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="flex justify-between"
                                >
                                    <span
                                        >{{ item.quantity }}x
                                        {{ item.product?.name || 'Món' }}</span
                                    >
                                    <span class="font-mono font-bold"
                                        >{{
                                            numberFormat(
                                                item.unit_price * item.quantity,
                                            )
                                        }}đ</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-4 flex items-center justify-between border-t pt-3"
                        >
                            <span
                                class="font-mono text-sm font-black text-emerald-600"
                            >
                                {{ numberFormat(order.total_amount) }}đ
                            </span>
                            <div class="flex gap-1.5">
                                <Button
                                    v-if="order.status === 'pending'"
                                    size="sm"
                                    class="rounded-xl bg-indigo-600 text-[11px] font-bold hover:bg-indigo-700"
                                    :disabled="updatingExternalId === order.id"
                                    @click="
                                        emit('updateExternalOrderStatus', {
                                            orderId: order.id,
                                            status: 'confirmed',
                                        })
                                    "
                                >
                                    Nhận đơn
                                </Button>
                                <Button
                                    v-else-if="order.status === 'confirmed'"
                                    size="sm"
                                    class="rounded-xl bg-amber-600 text-[11px] font-bold hover:bg-amber-700"
                                    :disabled="updatingExternalId === order.id"
                                    @click="
                                        emit('updateExternalOrderStatus', {
                                            orderId: order.id,
                                            status: 'preparing',
                                        })
                                    "
                                >
                                    Chuẩn bị
                                </Button>
                                <Button
                                    v-else-if="order.status === 'preparing'"
                                    size="sm"
                                    class="rounded-xl bg-emerald-600 text-[11px] font-bold hover:bg-emerald-700"
                                    :disabled="updatingExternalId === order.id"
                                    @click="
                                        emit('updateExternalOrderStatus', {
                                            orderId: order.id,
                                            status: 'completed',
                                        })
                                    "
                                >
                                    Hoàn thành
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
