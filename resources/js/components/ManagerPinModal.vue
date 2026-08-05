<script setup lang="ts">
import { ShieldCheck, Lock, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    open: boolean;
    title?: string;
    description?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success', verifiedBy: string): void;
}>();

const pin = ref('');
const isSubmitting = ref(false);
const errorMessage = ref('');

const handleVerify = async () => {
    if (!pin.value || !/^\d{4,6}$/.test(pin.value)) {
        errorMessage.value = 'Mã PIN phải từ 4 đến 6 chữ số';

        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        const res = await fetch('/fraud/verify-pin', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content || '',
            },
            body: JSON.stringify({ pin: pin.value }),
        });

        const data = await res.json();

        if (res.ok && data.success) {
            toast.success(data.message || 'Xác thực PIN Quản lý thành công!');
            pin.value = '';
            emit('success', data.verified_by || 'Quản lý');
            emit('close');
        } else {
            errorMessage.value = data.message || 'Mã PIN không chính xác';
        }
    } catch {
        errorMessage.value = 'Không thể kết nối đến máy chủ xác thực';
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    >
        <div
            class="w-full max-w-md animate-in rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl duration-200 zoom-in-95 fade-in dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="rounded-xl bg-amber-500/10 p-2.5 text-amber-600 dark:text-amber-400"
                    >
                        <ShieldCheck class="h-6 w-6" />
                    </div>
                    <div>
                        <h3
                            class="text-lg font-bold text-slate-900 dark:text-white"
                        >
                            {{ title || 'Xác Thực Mã PIN Quản Lý' }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{
                                description ||
                                'Vui lòng nhập PIN của Quản lý ca để phê duyệt thao tác nhạy cảm'
                            }}
                        </p>
                    </div>
                </div>
                <button
                    @click="emit('close')"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <form @submit.prevent="handleVerify" class="mt-6 space-y-4">
                <div>
                    <label
                        class="mb-2 block text-xs font-semibold text-slate-700 dark:text-slate-300"
                    >
                        Mã PIN Quản lý (4-6 số)
                    </label>
                    <div class="relative">
                        <Lock
                            class="absolute top-1/2 left-3.5 h-5 w-5 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="pin"
                            type="password"
                            maxlength="6"
                            inputmode="numeric"
                            placeholder="••••••"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 py-3 pr-4 pl-11 text-center font-mono text-2xl tracking-[0.5em] text-slate-900 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800/50 dark:text-white"
                            autofocus
                        />
                    </div>
                    <p
                        v-if="errorMessage"
                        class="mt-2 flex items-center gap-1 text-xs font-medium text-red-500"
                    >
                        ⚠️ {{ errorMessage }}
                    </p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="w-1/2 rounded-xl"
                        @click="emit('close')"
                    >
                        Hủy bỏ
                    </Button>
                    <Button
                        type="submit"
                        class="w-1/2 rounded-xl bg-amber-600 text-white hover:bg-amber-700"
                        :disabled="isSubmitting || !pin"
                    >
                        {{ isSubmitting ? 'Đang xác thực...' : 'Xác nhận PIN' }}
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
