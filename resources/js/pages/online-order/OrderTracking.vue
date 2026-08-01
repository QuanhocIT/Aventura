<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    CheckCircle2,
    Circle,
    Clock,
    Loader2,
    Package,
    Truck,
} from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import echo from '@/lib/echo';

const props = defineProps<{
    orderNumber: string;
    tracking?: {
        order: {
            order_number: string;
            channel: string;
            status: string;
            payment_status: string;
            total_amount: number;
            created_at: string;
        };
        items: { name: string; quantity: number; price: number }[];
        timeline: {
            status: string;
            label: string;
            at: string | null;
            done: boolean;
        }[];
    };
}>();

const tracking = ref(props.tracking ?? null);
const loading = ref(!props.tracking);

const statusIcon: Record<string, any> = {
    created: Clock,
    confirmed: CheckCircle2,
    preparing: Package,
    delivering: Truck,
    completed: CheckCircle2,
};

let pollInterval: ReturnType<typeof setInterval>;
let echoChannel: any = null;

async function fetchTracking() {
    try {
        const { data } = await axios.get(
            `/api/online/order/${props.orderNumber}/status`,
        );
        tracking.value = data;
        loading.value = false;

        if (
            data.order.status === 'completed' ||
            data.order.status === 'cancelled'
        ) {
            clearInterval(pollInterval);
        }
    } catch {
        loading.value = false;
    }
}

onMounted(() => {
    if (!tracking.value) {
        fetchTracking();
    }

    pollInterval = setInterval(fetchTracking, 15000);

    try {
        echoChannel = echo.channel(`order.${props.orderNumber}`);
        echoChannel.listen('.order.updated', () => fetchTracking());
        echoChannel.listen('.delivery.status', () => fetchTracking());
    } catch {}
});

onUnmounted(() => {
    clearInterval(pollInterval);
    echoChannel?.stopListening('.order.updated');
    echoChannel?.stopListening('.delivery.status');
});
</script>

<template>
    <Head :title="'Theo dõi đơn #' + orderNumber" />

    <div
        class="flex min-h-screen items-start justify-center bg-slate-50 p-6 pt-12 dark:bg-slate-950"
    >
        <Card class="w-full max-w-md">
            <CardHeader>
                <CardTitle class="text-center text-lg">
                    Theo dõi đơn hàng
                    <span class="text-primary">#{{ orderNumber }}</span>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    v-if="loading"
                    class="flex items-center justify-center py-12"
                >
                    <Loader2
                        class="size-8 animate-spin text-muted-foreground"
                    />
                </div>

                <div v-else-if="tracking" class="space-y-6">
                    <!-- Timeline -->
                    <div class="space-y-4">
                        <div
                            v-for="(step, idx) in tracking.timeline"
                            :key="step.status"
                            class="flex gap-3"
                        >
                            <div class="flex flex-col items-center">
                                <div
                                    :class="[
                                        'rounded-full p-1.5',
                                        step.done
                                            ? 'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400'
                                            : 'bg-muted text-muted-foreground',
                                    ]"
                                >
                                    <component
                                        :is="statusIcon[step.status] ?? Circle"
                                        class="size-4"
                                    />
                                </div>
                                <div
                                    v-if="idx < tracking.timeline.length - 1"
                                    :class="[
                                        'my-1 w-0.5 flex-1',
                                        step.done
                                            ? 'bg-green-300 dark:bg-green-700'
                                            : 'bg-muted',
                                    ]"
                                ></div>
                            </div>
                            <div class="pb-4">
                                <p
                                    :class="[
                                        'text-sm font-semibold',
                                        step.done
                                            ? 'text-foreground'
                                            : 'text-muted-foreground',
                                    ]"
                                >
                                    {{ step.label }}
                                </p>
                                <p
                                    v-if="step.at"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        new Date(step.at).toLocaleString(
                                            'vi-VN',
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment status -->
                    <div class="border-t pt-4">
                        <div class="flex items-center justify-between text-sm">
                            <span>Thanh toán</span>
                            <span
                                :class="[
                                    'font-semibold',
                                    tracking.order.payment_status === 'paid'
                                        ? 'text-green-600'
                                        : 'text-amber-500',
                                ]"
                            >
                                {{
                                    tracking.order.payment_status === 'paid'
                                        ? 'Đã thanh toán'
                                        : 'Chưa thanh toán'
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- Order items -->
                    <div class="space-y-2 border-t pt-4">
                        <p
                            class="text-xs font-semibold text-muted-foreground uppercase"
                        >
                            Chi tiết đơn hàng
                        </p>
                        <div
                            v-for="item in tracking.items"
                            :key="item.name"
                            class="flex justify-between text-sm"
                        >
                            <span>{{ item.name }} x{{ item.quantity }}</span>
                            <span class="font-medium"
                                >{{
                                    (
                                        item.price * item.quantity
                                    ).toLocaleString()
                                }}đ</span
                            >
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 text-base font-bold"
                        >
                            <span>Tổng cộng</span>
                            <span class="text-primary"
                                >{{
                                    tracking.order.total_amount.toLocaleString()
                                }}đ</span
                            >
                        </div>
                    </div>
                </div>

                <div v-else class="py-12 text-center text-muted-foreground">
                    Không tìm thấy đơn hàng.
                </div>
            </CardContent>
        </Card>
    </div>
</template>
