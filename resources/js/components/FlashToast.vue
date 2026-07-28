<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, XCircle, X, Info } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Toast {
    id: number;
    type: 'success' | 'error' | 'info';
    message: string;
}

const page = usePage();
const toasts = ref<Toast[]>([]);
let nextId = 0;

function addToast(type: Toast['type'], message: string) {
    const id = nextId++;
    toasts.value.push({ id, type, message });
    setTimeout(() => removeToast(id), 4500);
}

function removeToast(id: number) {
    const idx = toasts.value.findIndex((t) => t.id === id);

    if (idx !== -1) {
        toasts.value.splice(idx, 1);
    }
}

watch(
    () => (page.props as any).flash,
    (flash) => {
        if (flash?.success) {
            addToast('success', flash.success);
        }

        if (flash?.error) {
            addToast('error', flash.error);
        }
    },
    { deep: true },
);
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed right-5 bottom-5 z-[9999] flex flex-col gap-2.5"
            style="max-width: 360px"
        >
            <TransitionGroup
                enter-active-class="transition-all duration-500 ease-[cubic-bezier(0.2,0.8,0.2,1)]"
                enter-from-class="opacity-0 translate-y-4 scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition-all duration-300 ease-in"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 translate-y-4 scale-95"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3.5 shadow-lg backdrop-blur-sm"
                    :class="{
                        'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/80':
                            toast.type === 'success',
                        'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/80':
                            toast.type === 'error',
                        'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/80':
                            toast.type === 'info',
                    }"
                >
                    <CheckCircle2
                        v-if="toast.type === 'success'"
                        class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                    />
                    <XCircle
                        v-else-if="toast.type === 'error'"
                        class="mt-0.5 size-5 shrink-0 text-red-600 dark:text-red-400"
                    />
                    <Info
                        v-else
                        class="mt-0.5 size-5 shrink-0 text-blue-600 dark:text-blue-400"
                    />

                    <p
                        class="flex-1 text-sm leading-snug font-medium"
                        :class="{
                            'text-emerald-800 dark:text-emerald-200':
                                toast.type === 'success',
                            'text-red-800 dark:text-red-200':
                                toast.type === 'error',
                            'text-blue-800 dark:text-blue-200':
                                toast.type === 'info',
                        }"
                    >
                        {{ toast.message }}
                    </p>

                    <button
                        @click="removeToast(toast.id)"
                        class="shrink-0 rounded-md p-0.5 opacity-60 transition hover:opacity-100"
                        :class="{
                            'text-emerald-700 dark:text-emerald-300':
                                toast.type === 'success',
                            'text-red-700 dark:text-red-300':
                                toast.type === 'error',
                            'text-blue-700 dark:text-blue-300':
                                toast.type === 'info',
                        }"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
