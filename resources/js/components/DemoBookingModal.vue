<script setup lang="ts">
import {
    Calendar,
    User,
    Phone,
    Mail,
    Building2,
    Store,
    X,
    CheckCircle2,
    Loader2,
    Sparkles,
} from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const form = ref({
    name: '',
    phone: '',
    email: '',
    restaurant_name: '',
    branch_count: 1,
    preferred_date: new Date().toISOString().split('T')[0],
    preferred_time: 'Sáng (8:00 - 12:00)',
    notes: '',
});

const isSubmitting = ref(false);
const isSuccess = ref(false);
const errorMessage = ref('');

const timeOptions = [
    'Sáng (8:00 - 12:00)',
    'Chiều (13:30 - 17:30)',
    'Tối (18:00 - 21:00)',
];

const branchOptions = [
    { label: '1 chi nhánh', value: 1 },
    { label: '2 - 5 chi nhánh', value: 3 },
    { label: '6 - 15 chi nhánh', value: 10 },
    { label: 'Trãi dài > 15 chi nhánh', value: 20 },
];

async function submitForm() {
    if (
        !form.value.name.trim() ||
        !form.value.phone.trim() ||
        !form.value.restaurant_name.trim()
    ) {
        errorMessage.value = 'Vui lòng điền đầy đủ các thông tin bắt buộc (*).';

        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch('/api/demo-booking', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content || '',
            },
            body: JSON.stringify(form.value),
        });

        const data = await response.json();

        if (response.ok && data.success) {
            isSuccess.value = true;
        } else {
            errorMessage.value =
                data.message ||
                'Không thể gửi yêu cầu đặt lịch. Vui lòng thử lại sau.';
        }
    } catch (e) {
        console.error('Error submitting demo booking:', e);
        errorMessage.value = 'Lỗi kết nối máy chủ. Vui lòng thử lại sau.';
    } finally {
        isSubmitting.value = false;
    }
}

function handleClose() {
    isSuccess.value = false;
    errorMessage.value = '';
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[99999] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-md"
            >
                <div
                    class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-white/10 bg-slate-900 text-slate-100 shadow-2xl"
                >
                    <!-- Header Bar -->
                    <div
                        class="relative flex items-center justify-between border-b border-white/10 bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-transparent p-5"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-slate-950 shadow-lg shadow-amber-500/20"
                            >
                                <Sparkles class="size-5" />
                            </div>
                            <div>
                                <h3
                                    class="font-serif text-lg font-bold text-white"
                                >
                                    Đặt lịch Demo với Chuyên gia Aventura
                                </h3>
                                <p class="text-xs text-slate-400">
                                    Tư vấn 1:1 chuyên sâu giải pháp quản lý cho
                                    nhà hàng của bạn
                                </p>
                            </div>
                        </div>
                        <button
                            @click="handleClose"
                            class="rounded-full p-1.5 text-slate-400 transition hover:bg-white/10 hover:text-white"
                        >
                            <X class="size-5" />
                        </button>
                    </div>

                    <!-- Body Content -->
                    <div class="p-6">
                        <!-- Màn hình thành công -->
                        <div
                            v-if="isSuccess"
                            class="space-y-4 py-8 text-center"
                        >
                            <div
                                class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-emerald-500/30 bg-emerald-500/20 text-emerald-400"
                            >
                                <CheckCircle2 class="size-10" />
                            </div>
                            <h4 class="text-xl font-bold text-white">
                                Đặt Lịch Demo Thành Công!
                            </h4>
                            <p
                                class="mx-auto max-w-md text-sm leading-relaxed text-slate-300"
                            >
                                Cảm ơn bạn đã quan tâm đến Aventura. Đội ngũ
                                chuyên gia của chúng tôi đã nhận được thông tin
                                và sẽ gọi điện tư vấn trực tiếp trong vòng
                                <strong class="text-amber-400"
                                    >15 - 30 phút</strong
                                >.
                            </p>
                            <div class="pt-4">
                                <button
                                    @click="handleClose"
                                    class="rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-2.5 text-sm font-semibold text-slate-950 shadow-lg shadow-amber-500/20 transition hover:from-amber-400 hover:to-orange-400"
                                >
                                    Hoàn tất & Đóng
                                </button>
                            </div>
                        </div>

                        <!-- Biểu mẫu đăng ký -->
                        <form
                            v-else
                            @submit.prevent="submitForm"
                            class="space-y-4"
                        >
                            <div
                                v-if="errorMessage"
                                class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-300"
                            >
                                {{ errorMessage }}
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <!-- Họ tên -->
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-slate-300"
                                    >
                                        Họ và tên
                                        <span class="text-amber-400">*</span>
                                    </label>
                                    <div class="relative">
                                        <User
                                            class="absolute top-2.5 left-3 size-4 text-slate-500"
                                        />
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            placeholder="Nguyễn Văn A"
                                            required
                                            class="w-full rounded-xl border border-white/10 bg-slate-800/80 py-2 pr-3 pl-9 text-sm text-white placeholder-slate-500 focus:border-amber-400 focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <!-- Số điện thoại -->
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-slate-300"
                                    >
                                        Số điện thoại
                                        <span class="text-amber-400">*</span>
                                    </label>
                                    <div class="relative">
                                        <Phone
                                            class="absolute top-2.5 left-3 size-4 text-slate-500"
                                        />
                                        <input
                                            v-model="form.phone"
                                            type="tel"
                                            placeholder="0987 654 321"
                                            required
                                            class="w-full rounded-xl border border-white/10 bg-slate-800/80 py-2 pr-3 pl-9 text-sm text-white placeholder-slate-500 focus:border-amber-400 focus:outline-none"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <!-- Tên nhà hàng -->
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-slate-300"
                                    >
                                        Tên nhà hàng / thương hiệu
                                        <span class="text-amber-400">*</span>
                                    </label>
                                    <div class="relative">
                                        <Store
                                            class="absolute top-2.5 left-3 size-4 text-slate-500"
                                        />
                                        <input
                                            v-model="form.restaurant_name"
                                            type="text"
                                            placeholder="Nhà hàng Aventura BBQ"
                                            required
                                            class="w-full rounded-xl border border-white/10 bg-slate-800/80 py-2 pr-3 pl-9 text-sm text-white placeholder-slate-500 focus:border-amber-400 focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <!-- Số lượng chi nhánh -->
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-slate-300"
                                    >
                                        Số lượng chi nhánh
                                    </label>
                                    <div class="relative">
                                        <Building2
                                            class="absolute top-2.5 left-3 size-4 text-slate-500"
                                        />
                                        <select
                                            v-model="form.branch_count"
                                            class="w-full rounded-xl border border-white/10 bg-slate-800/80 py-2 pr-3 pl-9 text-sm text-white focus:border-amber-400 focus:outline-none"
                                        >
                                            <option
                                                v-for="opt in branchOptions"
                                                :key="opt.value"
                                                :value="opt.value"
                                            >
                                                {{ opt.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <!-- Email (tùy chọn) -->
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-slate-300"
                                    >
                                        Email nhận thông tin (Tùy chọn)
                                    </label>
                                    <div class="relative">
                                        <Mail
                                            class="absolute top-2.5 left-3 size-4 text-slate-500"
                                        />
                                        <input
                                            v-model="form.email"
                                            type="email"
                                            placeholder="quanly@nhahang.com"
                                            class="w-full rounded-xl border border-white/10 bg-slate-800/80 py-2 pr-3 pl-9 text-sm text-white placeholder-slate-500 focus:border-amber-400 focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <!-- Ngày mong muốn tư vấn -->
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-slate-300"
                                    >
                                        Ngày mong muốn tư vấn
                                    </label>
                                    <div class="relative">
                                        <Calendar
                                            class="absolute top-2.5 left-3 size-4 text-slate-500"
                                        />
                                        <input
                                            v-model="form.preferred_date"
                                            type="date"
                                            class="w-full rounded-xl border border-white/10 bg-slate-800/80 py-2 pr-3 pl-9 text-sm text-white focus:border-amber-400 focus:outline-none"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Khung giờ mong muốn -->
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-300"
                                >
                                    Khung giờ tư vấn mong muốn
                                </label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        v-for="t in timeOptions"
                                        :key="t"
                                        type="button"
                                        @click="form.preferred_time = t"
                                        class="rounded-xl border py-2 text-xs font-medium transition"
                                        :class="
                                            form.preferred_time === t
                                                ? 'border-amber-400 bg-amber-500/20 text-amber-300'
                                                : 'border-white/10 bg-slate-800/50 text-slate-400 hover:bg-slate-800'
                                        "
                                    >
                                        {{ t }}
                                    </button>
                                </div>
                            </div>

                            <!-- Ghi chú thêm -->
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-300"
                                >
                                    Nhu cầu tư vấn chi tiết (Tùy chọn)
                                </label>
                                <div class="relative">
                                    <textarea
                                        v-model="form.notes"
                                        rows="2"
                                        placeholder="Ví dụ: Cần tư vấn phần mềm POS kết nối QR thanh toán & báo cáo tồn kho..."
                                        class="w-full rounded-xl border border-white/10 bg-slate-800/80 p-3 text-sm text-white placeholder-slate-500 focus:border-amber-400 focus:outline-none"
                                    ></textarea>
                                </div>
                            </div>

                            <!-- Footer CTA -->
                            <div class="flex items-center justify-between pt-3">
                                <p class="text-[11px] text-slate-400">
                                    * Cam kết bảo mật 100% thông tin doanh
                                    nghiệp.
                                </p>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="handleClose"
                                        class="rounded-xl border border-white/10 px-4 py-2 text-xs font-medium text-slate-300 transition hover:bg-white/10"
                                    >
                                        Hủy
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="isSubmitting"
                                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 px-5 py-2 text-xs font-bold text-slate-950 shadow-lg shadow-amber-500/20 transition hover:from-amber-400 hover:to-orange-400 disabled:opacity-50"
                                    >
                                        <Loader2
                                            v-if="isSubmitting"
                                            class="size-4 animate-spin"
                                        />
                                        <span>Gửi Yêu Cầu Đặt Lịch</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
