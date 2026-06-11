<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Star,
    Send,
    CheckCircle2,
    AlertTriangle,
    ShieldCheck,
    Heart,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface OrderContext {
    order_id: number;
    order_number: string;
    table_name: string;
    restaurant_id: number;
}

const props = defineProps<{
    orderContext: OrderContext | null;
    queryRestaurantId: number | null;
    queryTableId: number | null;
    restaurantName: string;
}>();

// --- STATE ---
const rating = ref<number>(0);
const hoverRating = ref<number>(0);
const content = ref('');
const isAnonymous = ref(true);
const submittedByName = ref('');
const submittedByPhone = ref('');

const isSubmitting = ref(false);
const isSuccess = ref(false);
const errorMessage = ref('');

// --- ACTIONS ---
const setRating = (r: number) => {
    rating.value = r;
};

const handleHover = (r: number) => {
    hoverRating.value = r;
};

const handleSubmit = async () => {
    if (rating.value === 0) {
        errorMessage.value = 'Vui lòng chọn số sao đánh giá (từ 1 đến 5 sao).';
        return;
    }

    if (!isAnonymous.value && !submittedByPhone.value) {
        errorMessage.value =
            'Vui lòng nhập Số điện thoại để nhà hàng gửi tặng voucher đền bù.';
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        const payload = {
            rating: rating.value,
            content: content.value,
            is_anonymous: isAnonymous.value,
            submitted_by_name: isAnonymous.value ? null : submittedByName.value,
            submitted_by_phone: isAnonymous.value
                ? null
                : submittedByPhone.value,
            order_id: props.orderContext?.order_id ?? null,
            table_id: props.queryTableId ?? null,
            restaurant_id:
                props.orderContext?.restaurant_id ??
                props.queryRestaurantId ??
                null,
        };

        const res = await fetch('/feedback', {
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
            body: JSON.stringify(payload),
        });

        const result = await res.json();
        if (result.success) {
            isSuccess.value = true;
        } else {
            errorMessage.value =
                result.message || 'Đã xảy ra lỗi, vui lòng thử lại sau.';
        }
    } catch (e) {
        errorMessage.value = 'Lỗi kết nối hệ thống. Vui lòng thử lại.';
    } finally {
        isSubmitting.value = false;
    }
};

const ratingTexts: Record<number, string> = {
    1: 'Rất tệ - Không hài lòng',
    2: 'Tệ - Cần cải thiện nhiều',
    3: 'Tạm ổn - Cần nâng cấp',
    4: 'Tốt - Rất hài lòng',
    5: 'Xuất sắc - Trải nghiệm tuyệt vời!',
};
</script>

<template>
    <Head title="Đánh giá chất lượng dịch vụ" />

    <div
        class="flex min-h-screen items-center justify-center bg-gradient-to-br from-amber-50/60 to-orange-50/40 p-4 dark:from-slate-950 dark:to-slate-900"
    >
        <div
            class="relative w-full max-w-md overflow-hidden rounded-3xl border border-slate-100 bg-white p-6 shadow-2xl transition-all duration-300 dark:border-slate-800 dark:bg-slate-900"
        >
            <!-- Decorative blur background -->
            <div
                class="pointer-events-none absolute -top-10 -right-10 h-32 w-32 rounded-full bg-amber-200/40 blur-2xl dark:bg-amber-950/20"
            ></div>
            <div
                class="pointer-events-none absolute -bottom-10 -left-10 h-32 w-32 rounded-full bg-orange-200/40 blur-2xl dark:bg-orange-950/20"
            ></div>

            <!-- SUCCESS STATE -->
            <div
                v-if="isSuccess"
                class="flex flex-col items-center justify-center gap-4 py-8 text-center select-none"
            >
                <div
                    class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-500 shadow-inner dark:bg-emerald-950/40"
                >
                    <CheckCircle2 class="size-10 animate-bounce" />
                </div>
                <h2
                    class="text-xl font-extrabold text-slate-800 dark:text-slate-100"
                >
                    Gửi Phản Hồi Thành Công!
                </h2>

                <!-- Special message for poor ratings to cool down the crisis -->
                <p
                    v-if="rating <= 2"
                    class="mt-1 max-w-[290px] text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                >
                    Nhà hàng vô cùng xin lỗi vì trải nghiệm không tốt của quý
                    khách hôm nay. Ý kiến này đã được gửi khẩn cấp tới Quản lý
                    để xử lý trực tiếp.
                    <span
                        v-if="!isAnonymous"
                        class="mt-2 block font-bold text-orange-600 dark:text-orange-400"
                    >
                        Chúng tôi sẽ gửi Voucher đền bù vào SĐT
                        {{ submittedByPhone }} trong thời gian sớm nhất!
                    </span>
                </p>
                <p
                    v-else
                    class="mt-1 max-w-[280px] text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                >
                    Cảm ơn quý khách đã dành thời gian quý báu để góp ý. Nhà
                    hàng sẽ không ngừng nâng cao chất lượng dịch vụ để phục vụ
                    quý khách tốt hơn.
                </p>

                <div
                    class="mt-6 flex items-center gap-1 text-[10px] font-medium text-slate-400 dark:text-slate-600"
                >
                    <Heart class="size-3 fill-rose-400 text-rose-400" />
                    <span>Chúc quý khách một ngày tuyệt vời!</span>
                </div>
            </div>

            <!-- FORM STATE -->
            <div v-else class="flex flex-col gap-5">
                <!-- Header -->
                <div class="border-b pb-4 text-center">
                    <span
                        class="text-[10px] font-bold tracking-widest text-amber-600 uppercase dark:text-amber-500"
                    >
                        {{ restaurantName }}
                    </span>
                    <h1
                        class="mt-1 text-lg font-black text-slate-800 dark:text-slate-100"
                    >
                        Đóng Góp Ý Kiến & Đánh Giá
                    </h1>
                    <p
                        class="mx-auto mt-1 max-w-[280px] text-xs leading-relaxed text-slate-400 dark:text-slate-500"
                    >
                        Phản hồi của quý khách là tài sản quý báu giúp chúng tôi
                        phục vụ tốt hơn mỗi ngày.
                    </p>

                    <!-- Table context indicator if available -->
                    <div
                        v-if="orderContext"
                        class="mt-3 inline-flex items-center gap-1.5 rounded-full border border-slate-100 bg-slate-50 px-2.5 py-1 text-[10px] font-bold text-slate-500 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400"
                    >
                        <span>Đơn hàng: {{ orderContext.order_number }}</span>
                        <span class="text-slate-300">•</span>
                        <span>Bàn: {{ orderContext.table_name }}</span>
                    </div>
                </div>

                <!-- Star selection group -->
                <div class="flex flex-col items-center gap-2 py-2">
                    <span
                        class="text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                    >
                        Quý khách thấy dịch vụ thế nào?
                    </span>
                    <div class="my-1 flex items-center gap-2">
                        <button
                            v-for="star in 5"
                            :key="star"
                            type="button"
                            @click="setRating(star)"
                            @mouseenter="handleHover(star)"
                            @mouseleave="handleHover(0)"
                            class="transition-transform hover:scale-110 focus:outline-none active:scale-95"
                        >
                            <Star
                                class="size-9 transition-colors"
                                :class="[
                                    (hoverRating || rating) >= star
                                        ? 'fill-amber-400 text-amber-400'
                                        : 'text-slate-200 dark:text-slate-800',
                                ]"
                            />
                        </button>
                    </div>
                    <!-- Rating text descriptor -->
                    <span
                        v-if="rating > 0 || hoverRating > 0"
                        class="text-xs font-bold transition-all"
                        :class="[
                            (hoverRating || rating) <= 2
                                ? 'text-rose-500'
                                : (hoverRating || rating) === 3
                                  ? 'text-amber-600 dark:text-amber-500'
                                  : 'text-emerald-600 dark:text-emerald-500',
                        ]"
                    >
                        {{ ratingTexts[hoverRating || rating] }}
                    </span>
                </div>

                <!-- Empathy section for poor rating during inputs -->
                <div
                    v-if="rating > 0 && rating <= 2"
                    class="animate-fadeIn flex items-start gap-2.5 rounded-2xl border border-rose-100 bg-rose-50 p-3.5 text-rose-700 shadow-sm dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-400"
                >
                    <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                    <div class="flex-1">
                        <h4 class="text-xs font-bold">
                            Thành thật xin lỗi quý khách!
                        </h4>
                        <p
                            class="mt-0.5 text-[10px] leading-relaxed opacity-90"
                        >
                            Hãy chia sẻ cụ thể điều gì khiến quý khách không hài
                            lòng ở ô bên dưới. Chúng tôi sẽ giải quyết và đền bù
                            thỏa đáng ngay lập tức.
                        </p>
                    </div>
                </div>

                <!-- Comment Input Area -->
                <div class="flex flex-col gap-1.5">
                    <Label
                        class="text-xs font-bold text-slate-600 dark:text-slate-400"
                    >
                        Chi tiết trải nghiệm của quý khách:
                    </Label>
                    <textarea
                        v-model="content"
                        placeholder="Hãy chia sẻ thêm ý kiến của bạn về món ăn, thái độ phục vụ hoặc không gian quán..."
                        rows="3"
                        class="dark:border-slate-850 w-full resize-none rounded-2xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-xs transition-all placeholder:text-slate-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none dark:bg-slate-900 dark:placeholder:text-slate-600 dark:focus:ring-amber-500/10"
                    ></textarea>
                </div>

                <!-- Identity selection and details -->
                <div class="flex flex-col gap-3 border-t pt-4">
                    <div class="flex items-center justify-between">
                        <Label
                            class="text-xs font-bold text-slate-600 dark:text-slate-400"
                        >
                            Gửi đánh giá dưới dạng:
                        </Label>
                        <div
                            class="border-slate-150 flex items-center gap-1 rounded-lg border bg-slate-50 p-0.5 dark:border-slate-800 dark:bg-slate-900"
                        >
                            <button
                                type="button"
                                @click="isAnonymous = true"
                                :class="[
                                    'rounded-md px-2.5 py-1 text-[10px] font-bold transition-colors',
                                    isAnonymous
                                        ? 'dark:bg-slate-850 bg-white text-slate-700 shadow-sm dark:text-slate-200'
                                        : 'text-slate-400',
                                ]"
                            >
                                Ẩn danh
                            </button>
                            <button
                                type="button"
                                @click="isAnonymous = false"
                                :class="[
                                    'rounded-md px-2.5 py-1 text-[10px] font-bold transition-colors',
                                    !isAnonymous
                                        ? 'dark:bg-slate-850 bg-white text-slate-700 shadow-sm dark:text-slate-200'
                                        : 'text-slate-400',
                                ]"
                            >
                                Định danh
                            </button>
                        </div>
                    </div>

                    <!-- Client detail input if not anonymous -->
                    <div
                        v-if="!isAnonymous"
                        class="animate-fadeIn flex flex-col gap-2.5 rounded-2xl border bg-slate-50/50 p-3.5 dark:bg-slate-900/30"
                    >
                        <div class="flex flex-col gap-1">
                            <Label
                                for="name"
                                class="text-[10px] font-bold text-slate-500 dark:text-slate-400"
                                >Họ và tên:</Label
                            >
                            <input
                                id="name"
                                type="text"
                                v-model="submittedByName"
                                placeholder="Nhập tên của quý khách (tùy chọn)"
                                class="dark:bg-slate-905 h-8 rounded-lg border border-slate-200 bg-white px-3 text-xs placeholder:text-slate-400 focus:ring-1 focus:ring-amber-500 focus:outline-none dark:border-slate-800"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label
                                for="phone"
                                class="text-[10px] font-bold text-slate-500 dark:text-slate-400"
                            >
                                Số điện thoại nhận Voucher đền bù:
                                <span class="font-bold text-rose-500">*</span>
                            </Label>
                            <input
                                id="phone"
                                type="tel"
                                v-model="submittedByPhone"
                                placeholder="Nhập SĐT để nhận mã giảm giá tri ân"
                                class="dark:bg-slate-905 h-8 rounded-lg border border-slate-200 bg-white px-3 text-xs placeholder:text-slate-400 focus:ring-1 focus:ring-amber-500 focus:outline-none dark:border-slate-800"
                            />
                        </div>
                        <div
                            class="flex items-center gap-1.5 text-[9px] leading-normal font-medium text-slate-400 dark:text-slate-500"
                        >
                            <ShieldCheck
                                class="size-3 shrink-0 text-emerald-500"
                            />
                            <span
                                >Thông tin của quý khách được bảo mật tuyệt đối,
                                chỉ phục vụ gửi đền bù.</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Error panel -->
                <div
                    v-if="errorMessage"
                    class="flex items-center gap-2 rounded-xl border border-rose-100 bg-rose-50 p-2.5 text-[10px] font-semibold text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-400"
                >
                    <AlertTriangle class="size-3.5 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </div>

                <!-- Submit Button -->
                <button
                    type="button"
                    @click="handleSubmit"
                    :disabled="isSubmitting"
                    class="flex h-10 w-full items-center justify-center gap-2 rounded-2xl border-0 bg-gradient-to-r from-amber-500 to-orange-500 text-xs font-bold text-white shadow-lg transition-all duration-300 select-none hover:from-amber-600 hover:to-orange-600 hover:shadow-xl"
                >
                    <Send class="size-3.5" />
                    {{
                        isSubmitting
                            ? 'Đang gửi đánh giá...'
                            : 'Gửi Đánh Giá Ngay'
                    }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fadeIn {
    animation: fadeIn 0.25s ease-out forwards;
}
</style>
