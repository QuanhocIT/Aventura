<script setup lang="ts">
import { ref, watch, computed } from 'vue';

const props = defineProps<{
    show: boolean;
    title: string;
    description: string;
    expectedText: string;
    confirmText?: string;
    cancelText?: string;
    danger?: boolean;
    loading?: boolean;
}>();

const emit = defineEmits(['confirm', 'close']);

const inputVal = ref('');

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            inputVal.value = '';
        }
    },
);

const isMatched = computed(() => {
    return inputVal.value.trim() === props.expectedText.trim();
});

const handleConfirm = () => {
    if (isMatched.value && !props.loading) {
        emit('confirm');
    }
};

const handleClose = () => {
    if (!props.loading) {
        emit('close');
    }
};
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 p-4 backdrop-blur-sm"
            >
                <div
                    class="w-full max-w-md scale-100 transform rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl transition-all dark:border-slate-800 dark:bg-slate-900"
                >
                    <!-- Header -->
                    <div class="flex items-start gap-4">
                        <div
                            :class="[
                                'flex shrink-0 items-center justify-center rounded-xl p-3',
                                danger
                                    ? 'bg-red-500/10 text-red-500 dark:bg-red-500/20'
                                    : 'bg-amber-500/10 text-amber-500',
                            ]"
                        >
                            <svg
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h3
                                class="text-lg font-bold text-slate-900 dark:text-white"
                            >
                                {{ title }}
                            </h3>
                            <p
                                class="mt-1 text-sm text-slate-600 dark:text-slate-400"
                            >
                                {{ description }}
                            </p>
                        </div>
                    </div>

                    <!-- Instruction & Input -->
                    <div class="mt-5 space-y-3">
                        <p
                            class="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                        >
                            Vui lòng nhập
                            <span
                                class="rounded border border-red-200 bg-red-50 px-1.5 py-0.5 font-mono font-bold text-red-500 select-all dark:border-red-800/50 dark:bg-red-950/50"
                                >{{ expectedText }}</span
                            >
                            để xác nhận:
                        </p>
                        <input
                            v-model="inputVal"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 font-mono text-sm text-slate-900 transition-all outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-white"
                            :placeholder="expectedText"
                            @keyup.enter="handleConfirm"
                        />
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            :disabled="loading"
                            class="rounded-xl px-4 py-2 text-sm font-medium text-slate-700 transition-all hover:bg-slate-100 disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800"
                            @click="handleClose"
                        >
                            {{ cancelText || 'Hủy bỏ' }}
                        </button>
                        <button
                            type="button"
                            :disabled="!isMatched || loading"
                            :class="[
                                'flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all',
                                isMatched && !loading
                                    ? danger
                                        ? 'bg-red-600 shadow-red-500/20 hover:bg-red-700'
                                        : 'bg-amber-600 shadow-amber-500/20 hover:bg-amber-700'
                                    : 'cursor-not-allowed bg-slate-300 text-slate-400 dark:bg-slate-800',
                            ]"
                            @click="handleConfirm"
                        >
                            <svg
                                v-if="loading"
                                class="h-4 w-4 animate-spin"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                            <span>{{
                                confirmText || 'Xác nhận thực hiện'
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
