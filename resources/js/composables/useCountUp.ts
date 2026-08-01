import { ref, watch } from 'vue';
import type { Ref } from 'vue';

export function useCountUp(target: Ref<number>, duration = 700): Ref<number> {
    const displayed = ref(target.value);
    let raf: number | null = null;

    watch(target, (newVal, oldVal) => {
        if (raf !== null) {
            cancelAnimationFrame(raf);
        }

        const start = oldVal ?? 0;
        const diff = newVal - start;

        if (diff === 0) {
            return;
        }

        const startTime = performance.now();

        function tick(now: number) {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // ease-out cubic
            const eased = 1 - Math.pow(1 - progress, 3);
            displayed.value = Math.round(start + diff * eased);

            if (progress < 1) {
                raf = requestAnimationFrame(tick);
            } else {
                displayed.value = newVal;
                raf = null;
            }
        }

        raf = requestAnimationFrame(tick);
    });

    return displayed;
}
