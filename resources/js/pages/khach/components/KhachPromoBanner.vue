<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';

interface Banner {
    id: number;
    title: string | null;
    subtitle: string | null;
    image_url: string;
    link_url: string | null;
}

defineProps<{
    firstPromoBanner: Banner | null;
}>();

const promoDismissed = ref(false);

function dismissPromo() {
    promoDismissed.value = true;
    localStorage.setItem('aventura_promo_dismissed', '1');
}

onMounted(() => {
    promoDismissed.value = localStorage.getItem('aventura_promo_dismissed') === '1';
});
</script>

<template>
    <Transition name="promo-strip">
        <div
            v-if="firstPromoBanner && !promoDismissed"
            class="border-y border-green-500/20 bg-gradient-to-r from-green-500/10 via-emerald-400/8 to-green-500/10"
        >
            <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 lg:px-8">
                <!-- Badge -->
                <span class="hidden shrink-0 items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/15 px-2.5 py-0.5 text-xs font-semibold text-green-700 sm:inline-flex dark:text-green-300">
                    🎉 {{ firstPromoBanner.title ?? 'Khuyến mãi' }}
                </span>

                <!-- Middle text -->
                <p class="flex-1 text-center text-sm text-green-800 dark:text-green-200">
                    <span class="font-semibold sm:hidden">{{ firstPromoBanner.title }} </span>
                    <span class="opacity-80">{{ firstPromoBanner.subtitle }}</span>
                </p>

                <!-- CTA -->
                <a
                    v-if="firstPromoBanner.link_url"
                    :href="firstPromoBanner.link_url"
                    class="shrink-0 rounded-full bg-green-600 px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-green-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500"
                >
                    Bắt đầu →
                </a>

                <!-- Dismiss -->
                <button
                    @click="dismissPromo"
                    class="shrink-0 rounded p-1 text-green-700/50 transition hover:text-green-700 focus:outline-none dark:text-green-300/50 dark:hover:text-green-300"
                    aria-label="Đóng thông báo"
                >
                    <X class="size-4" />
                </button>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
/* Promo strip dismiss */
.promo-strip-leave-active {
    transition: max-height 0.3s ease, opacity 0.3s ease, padding 0.3s ease;
    overflow: hidden;
}
.promo-strip-leave-to {
    max-height: 0;
    opacity: 0;
    padding-top: 0;
    padding-bottom: 0;
}
.promo-strip-enter-active {
    transition: max-height 0.3s ease, opacity 0.3s ease;
    overflow: hidden;
}
.promo-strip-enter-from { max-height: 0; opacity: 0; }
.promo-strip-enter-to   { max-height: 80px; opacity: 1; }
</style>
