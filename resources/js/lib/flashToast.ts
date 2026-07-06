import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    router.on('flash', (event: Event) => {
        const flash = (event as CustomEvent<{ flash?: { toast?: FlashToast } }>).detail?.flash;
        const data = flash?.toast;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });
}
