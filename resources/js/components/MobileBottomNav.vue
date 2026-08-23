<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, ShoppingBag, PlusCircle, Bot } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const currentUrl = computed(() => page.url);
const supplierPortalEnabled = computed(
    () => Boolean((page.props as any).supplier_portal_enabled),
);

const emit = defineEmits(['open-po-modal', 'toggle-chatbot']);
</script>

<template>
    <div
        class="fixed right-0 bottom-0 left-0 z-40 flex items-center justify-around border-t border-border/80 bg-background/90 px-2 py-2 shadow-lg backdrop-blur-xl md:hidden"
    >
        <Link
            href="/dashboard"
            class="flex flex-col items-center gap-1 rounded-xl px-3 py-1 text-[10px] font-bold transition-all"
            :class="
                currentUrl.startsWith('/dashboard')
                    ? 'scale-105 font-extrabold text-emerald-500'
                    : 'text-muted-foreground hover:text-foreground'
            "
        >
            <LayoutDashboard class="h-5 w-5" />
            <span>Dashboard</span>
        </Link>

        <Link
            v-if="supplierPortalEnabled"
            href="/suppliers"
            class="flex flex-col items-center gap-1 rounded-xl px-3 py-1 text-[10px] font-bold transition-all"
            :class="
                currentUrl.startsWith('/suppliers')
                    ? 'scale-105 font-extrabold text-emerald-500'
                    : 'text-muted-foreground hover:text-foreground'
            "
        >
            <ShoppingBag class="h-5 w-5" />
            <span>Kho & NCC</span>
        </Link>

        <!-- Floating Quick Order Button -->
        <button
            type="button"
            @click="emit('open-po-modal')"
            class="flex -translate-y-3 cursor-pointer flex-col items-center gap-0.5 rounded-full bg-gradient-to-r from-emerald-600 to-teal-500 p-2.5 text-[9px] font-black text-white shadow-lg shadow-emerald-500/30 transition-transform active:scale-90"
        >
            <PlusCircle class="h-6 w-6" />
            <span class="leading-none">Đặt PO</span>
        </button>

        <button
            type="button"
            @click="emit('toggle-chatbot')"
            class="flex cursor-pointer flex-col items-center gap-1 rounded-xl px-3 py-1 text-[10px] font-bold text-muted-foreground transition-all hover:text-foreground active:scale-95"
        >
            <Bot class="h-5 w-5 animate-pulse text-purple-500" />
            <span>Trợ lý AI</span>
        </button>
    </div>
</template>
