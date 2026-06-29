<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Zap, X, CalendarCheck } from 'lucide-vue-next';
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

function openDemoModal() {
    // Scroll to contact section or open a tel link
    window.open('tel:0346858035', '_self');
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
                v-if="showStickyCta && !stickyCtaDismissed"
                class="fixed bottom-0 left-0 right-0 z-50 border-t border-t-white/10 bg-zinc-950/95 px-4 py-3 shadow-2xl backdrop-blur-lg"
            >
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                    <p class="hidden text-sm text-zinc-300 sm:block">
                        🚀 <span class="font-semibold text-white">Dùng thử miễn phí 14 ngày</span> — không cần thẻ tín dụng
                    </p>
                    <div class="flex flex-1 items-center justify-end gap-2">
                        <!-- Demo booking button -->
                        <Button
                            variant="outline"
                            size="sm"
                            class="hidden border-white/20 bg-white/5 text-zinc-200 hover:bg-white/10 hover:text-white sm:flex items-center gap-1.5"
                            @click="openDemoModal"
                        >
                            <CalendarCheck class="size-3.5" />
                            Đặt lịch demo
                        </Button>

                        <!-- Register button -->
                        <Button
                            v-if="canRegister"
                            as-child
                            size="sm"
                            class="bg-amber-500 text-zinc-950 font-bold hover:bg-amber-400 border-none"
                        >
                            <Link :href="register()" class="flex items-center gap-1.5">
                                <Zap class="size-3.5" />
                                Bắt đầu miễn phí
                            </Link>
                        </Button>

                        <button
                            @click="dismissStickyCta"
                            class="rounded p-1 text-zinc-500 hover:text-zinc-200 transition-colors"
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
