<script setup lang="ts">
import { ref } from 'vue';
import { ShieldCheck, Lock, X } from 'lucide-vue-next';
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
        'Accept': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
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
  } catch (e) {
    errorMessage.value = 'Không thể kết nối đến máy chủ xác thực';
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800 animate-in fade-in zoom-in-95 duration-200">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="rounded-xl bg-amber-500/10 p-2.5 text-amber-600 dark:text-amber-400">
            <ShieldCheck class="h-6 w-6" />
          </div>
          <div>
            <h3 class="font-bold text-slate-900 dark:text-white text-lg">
              {{ title || 'Xác Thực Mã PIN Quản Lý' }}
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              {{ description || 'Vui lòng nhập PIN của Quản lý ca để phê duyệt thao tác nhạy cảm' }}
            </p>
          </div>
        </div>
        <button @click="emit('close')" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
          <X class="h-5 w-5" />
        </button>
      </div>

      <form @submit.prevent="handleVerify" class="mt-6 space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
            Mã PIN Quản lý (4-6 số)
          </label>
          <div class="relative">
            <Lock class="absolute left-3.5 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" />
            <input
              v-model="pin"
              type="password"
              maxlength="6"
              inputmode="numeric"
              placeholder="••••••"
              class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 pl-11 pr-4 py-3 text-center text-2xl tracking-[0.5em] font-mono focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 text-slate-900 dark:text-white"
              autofocus
            />
          </div>
          <p v-if="errorMessage" class="mt-2 text-xs text-red-500 font-medium flex items-center gap-1">
            ⚠️ {{ errorMessage }}
          </p>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <Button type="button" variant="outline" class="w-1/2 rounded-xl" @click="emit('close')">
            Hủy bỏ
          </Button>
          <Button type="submit" class="w-1/2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white" :disabled="isSubmitting || !pin">
            {{ isSubmitting ? 'Đang xác thực...' : 'Xác nhận PIN' }}
          </Button>
        </div>
      </form>
    </div>
  </div>
</template>
