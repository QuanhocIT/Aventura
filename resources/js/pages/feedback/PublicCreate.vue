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
import { ref, onMounted } from 'vue';

interface OrderContext {
    order_id: number;
    order_number: string;
    table_name: string;
    restaurant_id: number;
    items?: { product_id: number; name: string }[];
}

interface Staff {
    employee_id: number;
    name: string;
    role: string;
}

const props = defineProps<{
    orderContext: OrderContext | null;
    queryRestaurantId: number | null;
    queryTableId: number | null;
    restaurantName: string;
    staffList?: Staff[];
    turnstileSiteKey?: string;
    captchaQuestion?: string;
    captchaToken?: string;
}>();

// --- STATE ---
const rating = ref<number>(0);
const hoverRating = ref<number>(0);
const content = ref('');
const isAnonymous = ref(true);
const submittedByName = ref('');
const submittedByPhone = ref('');
const turnstileToken = ref('');
const captchaAnswer = ref('');

const itemsRating = ref<Record<number, { rating: number; comment: string }>>(
    {},
);
const staffRating = ref<Record<number, { rating: number; comment: string }>>(
    {},
);

const isSubmitting = ref(false);
const isSuccess = ref(false);
const errorMessage = ref('');

// --- LIFECYCLE ---
onMounted(() => {
    // Tự động khởi tạo đánh giá món ăn mặc định là 5 sao
    if (props.orderContext?.items) {
        props.orderContext.items.forEach((item) => {
            itemsRating.value[item.product_id] = { rating: 5, comment: '' };
        });
    }

    // Tự động khởi tạo đánh giá nhân sự mặc định là 5 sao
    if (props.staffList) {
        props.staffList.forEach((s) => {
            staffRating.value[s.employee_id] = { rating: 5, comment: '' };
        });
    }

    if (props.turnstileSiteKey) {
        if (!window.turnstile) {
            const script = document.createElement('script');
            script.src =
                'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadTurnstileCallbackFeedback';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
            window.onloadTurnstileCallbackFeedback = () => {
                window.turnstile.render('#turnstile-container-feedback', {
                    sitekey: props.turnstileSiteKey,
                    callback: (token: string) => {
                        turnstileToken.value = token;
                    },
                });
            };
        } else {
            setTimeout(() => {
                window.turnstile.render('#turnstile-container-feedback', {
                    sitekey: props.turnstileSiteKey,
                    callback: (token: string) => {
                        turnstileToken.value = token;
                    },
                });
            }, 100);
        }
    }
});

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
            'cf-turnstile-response': turnstileToken.value,
            captcha_answer: captchaAnswer.value,
            captcha_token: props.captchaToken,
            order_id: props.orderContext?.order_id ?? null,
            table_id: props.queryTableId ?? null,
            restaurant_id:
                props.orderContext?.restaurant_id ??
                props.queryRestaurantId ??
                null,
            items_rating: Object.entries(itemsRating.value).map(([pId, r]) => ({
                product_id: parseInt(pId),
                rating: r.rating,
                comment: r.comment,
            })),
            staff_rating: Object.entries(staffRating.value).map(
                ([empId, r]) => ({
                    employee_id: parseInt(empId),
                    rating: r.rating,
                    comment: r.comment,
                }),
            ),
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
    } catch {
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

                <!-- Detailed Dish Ratings -->
                <div
                    v-if="orderContext?.items && orderContext.items.length > 0"
                    class="flex flex-col gap-2 border-t pt-4"
                >
                    <Label
                        class="text-slate-650 dark:text-slate-450 text-xs font-bold"
                    >
                        Đánh giá món ăn đã dùng:
                    </Label>
                    <div class="mt-1 space-y-3">
                        <div
                            v-for="item in orderContext.items"
                            :key="item.product_id"
                            class="space-y-2 rounded-2xl border border-slate-100 bg-slate-50/60 p-3 dark:border-slate-800/80 dark:bg-slate-900/60"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="line-clamp-1 text-xs font-bold text-slate-700 dark:text-slate-300"
                                >
                                    {{ item.name }}
                                </span>

                                <div class="flex items-center gap-0.5">
                                    <button
                                        v-for="star in 5"
                                        :key="star"
                                        type="button"
                                        @click="
                                            itemsRating[item.product_id] = {
                                                ...itemsRating[item.product_id],
                                                rating: star,
                                            }
                                        "
                                        class="p-0.5 transition-transform focus:outline-none active:scale-90"
                                    >
                                        <Star
                                            class="size-4.5 transition-colors"
                                            :class="[
                                                star <=
                                                (itemsRating[item.product_id]
                                                    ?.rating ?? 5)
                                                    ? 'fill-amber-400 text-amber-400'
                                                    : 'text-slate-200 dark:text-slate-800',
                                            ]"
                                        />
                                    </button>
                                </div>
                            </div>
                            <input
                                v-model="itemsRating[item.product_id].comment"
                                type="text"
                                placeholder="Góp ý về món ăn này (ví dụ: ngon, mặn, nguội...)"
                                class="bg-slate-905 text-xxs dark:text-slate-350 h-8 w-full rounded-lg border border-slate-200 px-2.5 text-slate-700 focus:ring-1 focus:ring-amber-500 focus:outline-none dark:border-slate-800"
                            />
                        </div>
                    </div>
                </div>

                <!-- Detailed Staff Ratings -->
                <div
                    v-if="staffList && staffList.length > 0"
                    class="flex flex-col gap-2 border-t pt-4"
                >
                    <Label
                        class="text-slate-650 dark:text-slate-450 text-xs font-bold"
                    >
                        Đánh giá nhân viên phục vụ ca trực:
                    </Label>
                    <div class="mt-1 space-y-3">
                        <div
                            v-for="staff in staffList"
                            :key="staff.employee_id"
                            class="space-y-2 rounded-2xl border border-slate-100 bg-slate-50/60 p-3 dark:border-slate-800/80 dark:bg-slate-900/60"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                    >
                                        {{ staff.name }}
                                    </span>
                                    <span
                                        class="border-slate-150 rounded border bg-slate-100 px-1 text-[9px] font-semibold text-slate-400 dark:border-slate-700 dark:bg-slate-800"
                                    >
                                        {{ staff.role }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-0.5">
                                    <button
                                        v-for="star in 5"
                                        :key="star"
                                        type="button"
                                        @click="
                                            staffRating[staff.employee_id] = {
                                                ...staffRating[
                                                    staff.employee_id
                                                ],
                                                rating: star,
                                            }
                                        "
                                        class="p-0.5 transition-transform focus:outline-none active:scale-90"
                                    >
                                        <Star
                                            class="size-4.5 transition-colors"
                                            :class="[
                                                star <=
                                                (staffRating[staff.employee_id]
                                                    ?.rating ?? 5)
                                                    ? 'fill-amber-400 text-amber-400'
                                                    : 'text-slate-200 dark:text-slate-800',
                                            ]"
                                        />
                                    </button>
                                </div>
                            </div>
                            <input
                                v-model="staffRating[staff.employee_id].comment"
                                type="text"
                                placeholder="Góp ý về nhân viên này (ví dụ: nhiệt tình, chậm chạp...)"
                                class="bg-slate-905 text-xxs dark:text-slate-350 h-8 w-full rounded-lg border border-slate-200 px-2.5 text-slate-700 focus:ring-1 focus:ring-amber-500 focus:outline-none dark:border-slate-800"
                            />
                        </div>
                    </div>
                </div>

                <!-- Comment Input Area -->
                <div class="flex flex-col gap-1.5 border-t pt-4">
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
                                class="bg-slate-905 h-8 rounded-lg border border-slate-200 px-3 text-xs placeholder:text-slate-400 focus:ring-1 focus:ring-amber-500 focus:outline-none dark:border-slate-800"
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
                                class="bg-slate-905 h-8 rounded-lg border border-slate-200 px-3 text-xs placeholder:text-slate-400 focus:ring-1 focus:ring-amber-500 focus:outline-none dark:border-slate-800"
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

                <!-- CAPTCHA / Turnstile security verification block -->
                <div
                    v-if="turnstileSiteKey || captchaQuestion"
                    class="my-1 grid gap-2 border-t pt-4"
                >
                    <Label
                        class="text-slate-650 flex items-center gap-1.5 text-xs font-bold tracking-wider uppercase dark:text-slate-400"
                    >
                        <svg
                            class="size-3.5 text-primary"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path
                                d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"
                            />
                        </svg>
                        Xác minh bảo mật:
                    </Label>

                    <!-- Cloudflare Turnstile -->
                    <div v-if="turnstileSiteKey">
                        <div
                            id="turnstile-container-feedback"
                            class="my-1.5 flex justify-center"
                        ></div>
                        <input
                            type="hidden"
                            name="cf-turnstile-response"
                            :value="turnstileToken"
                        />
                    </div>

                    <!-- Math CAPTCHA -->
                    <div v-else-if="captchaQuestion" class="grid gap-2">
                        <span
                            class="text-xs leading-normal font-semibold text-muted-foreground"
                        >
                            Vui lòng nhập kết quả của phép tính:
                            <strong
                                class="rounded border border-primary/20 bg-primary/10 px-1.5 py-0.5 font-mono text-sm text-primary"
                                >{{ captchaQuestion }}</strong
                            >
                        </span>
                        <input
                            type="hidden"
                            name="captcha_token"
                            :value="captchaToken"
                        />
                        <input
                            id="captcha_answer"
                            type="number"
                            v-model="captchaAnswer"
                            required
                            placeholder="Nhập kết quả"
                            class="bg-slate-905 dark:text-slate-350 h-8 w-full rounded-lg border border-slate-200 px-2.5 text-xs text-slate-700 focus:ring-1 focus:ring-amber-500 focus:outline-none dark:border-slate-800"
                        />
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
.text-xxs {
    font-size: 0.65rem;
}
.size-4\.5 {
    width: 1.125rem;
    height: 1.125rem;
}
.bg-slate-905 {
    background-color: rgba(241, 245, 249, 0.9);
}
.dark .bg-slate-905 {
    background-color: rgba(15, 23, 42, 0.9);
}
</style>
