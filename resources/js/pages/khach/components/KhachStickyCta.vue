<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Zap, X } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';

defineProps<{
    canRegister: boolean;
    showStickyCta: boolean;
}>();

const stickyCtaDismissed = ref(false);

function dismissStickyCta() {
    stickyCtaDismissed.value = true;
    localStorage.setItem('aventura_sticky_cta_dismissed', '1');
}

onMounted(() => {
    stickyCtaDismissed.value = localStorage.getItem('aventura_sticky_cta_dismissed') === '1';
});
</script>

<template>
    <!-- ── Sticky bottom CTA bar ──────────────────────────────── -->
    <Teleport to="body">
        <Transition name="slide-up">
            <div
                v-if="canRegister && showStickyCta && !stickyCtaDismissed"
                class="fixed bottom-0 left-0 right-0 z-50 border-t-2 border-t-primary/50 bg-background/95 px-4 py-3 shadow-lg backdrop-blur"
            >
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                    <p class="hidden text-sm font-medium sm:block">
                        Bắt đầu miễn phí — không cần thẻ tín dụng
                    </p>
                    <div class="flex flex-1 items-center justify-end gap-3">
                        <Button as-child size="sm">
                            <Link :href="register()" class="flex items-center gap-1.5">
                                <Zap class="size-3.5" />
                                Tạo tài khoản ngay
                            </Link>
                        </Button>
                        <button
                            @click="dismissStickyCta"
                            class="rounded p-1 text-muted-foreground hover:text-foreground"
                            aria-label="Đóng"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* Sticky CTA slide-up */
.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.3s ease, opacity 0.3s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
}
</style>
