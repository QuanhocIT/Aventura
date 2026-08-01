<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';

/**
 * Số đếm tăng dần (ease-out) khi mount và mỗi khi giá trị đổi.
 * Tôn trọng prefers-reduced-motion (nhảy thẳng đến giá trị cuối).
 *
 *   <AnimatedNumber :value="1250000" suffix="đ" />
 */
const props = withDefaults(
    defineProps<{
        value: number;
        duration?: number;
        /** số chữ số thập phân (mặc định 0) */
        decimals?: number;
        prefix?: string;
        suffix?: string;
        /** rút gọn kiểu 1,2 Tr / 3,4 T (Intl compact notation) */
        compact?: boolean;
    }>(),
    {
        duration: 900,
        decimals: 0,
        prefix: '',
        suffix: '',
        compact: false,
    },
);

const displayed = ref(0);
let raf: number | null = null;

function animateTo(target: number, from = 0) {
    if (raf !== null) {
        cancelAnimationFrame(raf);
    }

    if (
        typeof window !== 'undefined' &&
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    ) {
        displayed.value = target;

        return;
    }

    const start = performance.now();
    const diff = target - from;

    function tick(now: number) {
        const progress = Math.min((now - start) / props.duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        displayed.value = from + diff * eased;

        if (progress < 1) {
            raf = requestAnimationFrame(tick);
        } else {
            displayed.value = target;
            raf = null;
        }
    }

    raf = requestAnimationFrame(tick);
}

onMounted(() => animateTo(props.value));
watch(
    () => props.value,
    (val, old) => animateTo(val, old ?? 0),
);

function format(n: number): string {
    const val = Number(n);

    if (isNaN(val) || val === undefined || val === null) {
        return '0';
    }

    if (props.compact) {
        return new Intl.NumberFormat('vi-VN', {
            notation: 'compact',
            maximumFractionDigits: 1,
        }).format(val);
    }

    return val.toLocaleString('vi-VN', {
        minimumFractionDigits: props.decimals,
        maximumFractionDigits: props.decimals,
    });
}
</script>

<template>
    <span class="tabular-nums"
        >{{ prefix }}{{ format(displayed) }}{{ suffix }}</span
    >
</template>
