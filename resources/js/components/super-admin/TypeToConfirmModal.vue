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

watch(() => props.show, (newVal) => {
    if (newVal) {
        inputVal.value = '';
    }
});

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
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/75 backdrop-blur-sm">
                <div 
                    class="w-full max-w-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-6 transition-all transform scale-100"
                >
                    <!-- Header -->
                    <div class="flex items-start gap-4">
                        <div 
                            :class="[
                                'p-3 rounded-xl flex items-center justify-center shrink-0',
                                danger ? 'bg-red-500/10 text-red-500 dark:bg-red-500/20' : 'bg-amber-500/10 text-amber-500'
                            ]"
                        >
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                                {{ title }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                {{ description }}
                            </p>
                        </div>
                    </div>

                    <!-- Instruction & Input -->
                    <div class="mt-5 space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Vui lòng nhập <span class="select-all font-mono font-bold text-red-500 bg-red-50 dark:bg-red-950/50 px-1.5 py-0.5 rounded border border-red-200 dark:border-red-800/50">{{ expectedText }}</span> để xác nhận:
                        </p>
                        <input 
                            v-model="inputVal"
                            type="text"
                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-300 dark:border-slate-700 rounded-xl font-mono text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all outline-none"
                            :placeholder="expectedText"
                            @keyup.enter="handleConfirm"
                        />
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            :disabled="loading"
                            class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-all disabled:opacity-50"
                            @click="handleClose"
                        >
                            {{ cancelText || 'Hủy bỏ' }}
                        </button>
                        <button
                            type="button"
                            :disabled="!isMatched || loading"
                            :class="[
                                'px-4 py-2 text-sm font-semibold text-white rounded-xl shadow-lg transition-all flex items-center gap-2',
                                isMatched && !loading
                                    ? (danger ? 'bg-red-600 hover:bg-red-700 shadow-red-500/20' : 'bg-amber-600 hover:bg-amber-700 shadow-amber-500/20')
                                    : 'bg-slate-300 dark:bg-slate-800 cursor-not-allowed text-slate-400'
                            ]"
                            @click="handleConfirm"
                        >
                            <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ confirmText || 'Xác nhận thực hiện' }}</span>
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
