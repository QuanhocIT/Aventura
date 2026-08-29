<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { CheckCircle2, Clock3, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    restaurant: { id: number; name: string };
    reservation: {
        guest_name: string;
        reservation_date: string;
        reservation_time: string;
        party_size: number;
        status: string;
        status_label: string;
        table_name: string | null;
        branch_name: string | null;
        special_requests: string | null;
        cancellation_reason: string | null;
        can_cancel: boolean;
    };
}>();

const reservation = ref({ ...props.reservation });
const isCancelling = ref(false);
const errorMessage = ref('');

const statusIcon = computed(() => {
    if (reservation.value.status === 'cancelled') {
        return XCircle;
    }

    if (reservation.value.status === 'confirmed') {
        return CheckCircle2;
    }

    return Clock3;
});

async function cancelReservation() {
    if (!reservation.value.can_cancel || isCancelling.value) {
        return;
    }

    if (!window.confirm('Bạn có chắc muốn hủy đặt bàn này không?')) {
        return;
    }

    isCancelling.value = true;
    errorMessage.value = '';

    try {
        const token = window.location.pathname.split('/').pop() ?? '';
        const { data } = await axios.post(
            `/r/${props.restaurant.id}/reservations/${token}/cancel`,
            { reason: 'Khách tự hủy đặt bàn.' },
        );

        reservation.value = data.reservation;
    } catch (error: any) {
        errorMessage.value =
            error.response?.data?.message || 'Không thể hủy đặt bàn lúc này.';
    } finally {
        isCancelling.value = false;
    }
}
</script>

<template>
    <Head :title="`Đặt bàn · ${restaurant.name}`" />

    <main
        class="flex min-h-screen items-center justify-center bg-slate-50 p-4 dark:bg-slate-950"
    >
        <section
            class="w-full max-w-lg rounded-2xl border bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900"
        >
            <p class="text-sm font-semibold text-primary">
                {{ restaurant.name }}
            </p>
            <h1 class="mt-1 text-2xl font-bold">Thông tin đặt bàn</h1>

            <div
                class="mt-6 flex items-center gap-3 rounded-xl bg-muted/50 p-4"
            >
                <component :is="statusIcon" class="size-8 text-primary" />
                <div>
                    <p class="font-semibold">{{ reservation.status_label }}</p>
                    <p class="text-sm text-muted-foreground">
                        Xin chào {{ reservation.guest_name }}
                    </p>
                </div>
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-muted-foreground">Ngày</dt>
                    <dd class="font-medium">
                        {{ reservation.reservation_date }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Giờ</dt>
                    <dd class="font-medium">
                        {{ reservation.reservation_time }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Số khách</dt>
                    <dd class="font-medium">{{ reservation.party_size }}</dd>
                </div>
                <div v-if="reservation.table_name">
                    <dt class="text-muted-foreground">Bàn</dt>
                    <dd class="font-medium">{{ reservation.table_name }}</dd>
                </div>
                <div v-if="reservation.branch_name" class="col-span-2">
                    <dt class="text-muted-foreground">Chi nhánh</dt>
                    <dd class="font-medium">{{ reservation.branch_name }}</dd>
                </div>
            </dl>

            <p
                v-if="reservation.special_requests"
                class="mt-5 rounded-lg bg-muted/40 p-3 text-sm"
            >
                {{ reservation.special_requests }}
            </p>
            <p
                v-if="reservation.cancellation_reason"
                class="mt-5 rounded-lg bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-950/30 dark:text-rose-300"
            >
                Lý do hủy: {{ reservation.cancellation_reason }}
            </p>

            <p v-if="errorMessage" class="mt-4 text-sm text-destructive">
                {{ errorMessage }}
            </p>
            <button
                v-if="reservation.can_cancel"
                class="mt-6 w-full rounded-lg bg-rose-600 px-4 py-3 font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
                :disabled="isCancelling"
                @click="cancelReservation"
            >
                {{ isCancelling ? 'Đang hủy…' : 'Hủy đặt bàn' }}
            </button>
        </section>
    </main>
</template>
