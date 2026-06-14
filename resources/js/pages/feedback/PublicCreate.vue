<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Star, Send, CheckCircle2, AlertTriangle, ShieldCheck, Heart } from 'lucide-vue-next';
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
}>();

// --- STATE ---
const rating = ref<number>(0);
const hoverRating = ref<number>(0);
const content = ref('');
const isAnonymous = ref(true);
const submittedByName = ref('');
const submittedByPhone = ref('');

const itemsRating = ref<Record<number, { rating: number; comment: string }>>({});
const staffRating = ref<Record<number, { rating: number; comment: string }>>({});

const isSubmitting = ref(false);
const isSuccess = ref(false);
const errorMessage = ref('');

// --- LIFECYCLE ---
onMounted(() => {
    // Tự động khởi tạo đánh giá món ăn mặc định là 5 sao
    if (props.orderContext?.items) {
        props.orderContext.items.forEach(item => {
            itemsRating.value[item.product_id] = { rating: 5, comment: '' };
        });
    }

    // Tự động khởi tạo đánh giá nhân sự mặc định là 5 sao
    if (props.staffList) {
        props.staffList.forEach(s => {
            staffRating.value[s.employee_id] = { rating: 5, comment: '' };
        });
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
        errorMessage.value = 'Vui lòng nhập Số điện thoại để nhà hàng gửi tặng voucher đền bù.';

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
            submitted_by_phone: isAnonymous.value ? null : submittedByPhone.value,
            order_id: props.orderContext?.order_id ?? null,
            table_id: props.queryTableId ?? null,
            restaurant_id: props.orderContext?.restaurant_id ?? props.queryRestaurantId ?? null,
            items_rating: Object.entries(itemsRating.value).map(([pId, r]) => ({
                product_id: parseInt(pId),
                rating: r.rating,
                comment: r.comment
            })),
            staff_rating: Object.entries(staffRating.value).map(([empId, r]) => ({
                employee_id: parseInt(empId),
                rating: r.rating,
                comment: r.comment
            }))
        };

        const res = await fetch('/feedback', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''
            },
            body: JSON.stringify(payload)
        });

        const result = await res.json();

        if (result.success) {
            isSuccess.value = true;
        } else {
            errorMessage.value = result.message || 'Đã xảy ra lỗi, vui lòng thử lại sau.';
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

    <div class="min-h-screen bg-gradient-to-br from-amber-50/60 to-orange-50/40 dark:from-slate-950 dark:to-slate-900 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-2xl p-6 relative overflow-hidden transition-all duration-300">
            <!-- Decorative blur background -->
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-amber-200/40 dark:bg-amber-950/20 rounded-full blur-2xl pointer-events-none"></div>
            <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-orange-200/40 dark:bg-orange-950/20 rounded-full blur-2xl pointer-events-none"></div>

            <!-- SUCCESS STATE -->
            <div v-if="isSuccess" class="flex flex-col items-center justify-center text-center py-8 gap-4 select-none">
                <div class="h-16 w-16 bg-emerald-50 dark:bg-emerald-950/40 rounded-full flex items-center justify-center text-emerald-500 shadow-inner">
                    <CheckCircle2 class="size-10 animate-bounce" />
                </div>
                <h2 class="text-xl font-extrabold text-slate-800 dark:text-slate-100">Gửi Phản Hồi Thành Công!</h2>
                
                <!-- Special message for poor ratings to cool down the crisis -->
                <p v-if="rating <= 2" class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed max-w-[290px] mt-1">
                    Nhà hàng vô cùng xin lỗi vì trải nghiệm không tốt của quý khách hôm nay. Ý kiến này đã được gửi khẩn cấp tới Quản lý để xử lý trực tiếp. 
                    <span v-if="!isAnonymous" class="font-bold text-orange-600 dark:text-orange-400 mt-2 block">
                        Chúng tôi sẽ gửi Voucher đền bù vào SĐT {{ submittedByPhone }} trong thời gian sớm nhất!
                    </span>
                </p>
                <p v-else class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed max-w-[280px] mt-1">
                    Cảm ơn quý khách đã dành thời gian quý báu để góp ý. Nhà hàng sẽ không ngừng nâng cao chất lượng dịch vụ để phục vụ quý khách tốt hơn.
                </p>

                <div class="flex items-center gap-1 text-[10px] text-slate-400 dark:text-slate-600 font-medium mt-6">
                    <Heart class="size-3 text-rose-400 fill-rose-400" />
                    <span>Chúc quý khách một ngày tuyệt vời!</span>
                </div>
            </div>

            <!-- FORM STATE -->
            <div v-else class="flex flex-col gap-5">
                <!-- Header -->
                <div class="text-center border-b pb-4">
                    <span class="text-[10px] font-bold text-amber-600 dark:text-amber-500 tracking-widest uppercase">
                        {{ restaurantName }}
                    </span>
                    <h1 class="text-lg font-black text-slate-800 dark:text-slate-100 mt-1">Đóng Góp Ý Kiến & Đánh Giá</h1>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 max-w-[280px] mx-auto leading-relaxed">
                        Phản hồi của quý khách là tài sản quý báu giúp chúng tôi phục vụ tốt hơn mỗi ngày.
                    </p>

                    <!-- Table context indicator if available -->
                    <div v-if="orderContext" class="inline-flex items-center gap-1.5 mt-3 px-2.5 py-1 rounded-full bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 text-[10px] font-bold text-slate-500 dark:text-slate-400">
                        <span>Đơn hàng: {{ orderContext.order_number }}</span>
                        <span class="text-slate-300">•</span>
                        <span>Bàn: {{ orderContext.table_name }}</span>
                    </div>
                </div>

                <!-- Star selection group -->
                <div class="flex flex-col items-center gap-2 py-2">
                    <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        Quý khách thấy dịch vụ thế nào?
                    </span>
                    <div class="flex items-center gap-2 my-1">
                        <button 
                            v-for="star in 5" 
                            :key="star"
                            type="button"
                            @click="setRating(star)"
                            @mouseenter="handleHover(star)"
                            @mouseleave="handleHover(0)"
                            class="focus:outline-none transition-transform hover:scale-110 active:scale-95"
                        >
                            <Star 
                                class="size-9 transition-colors"
                                :class="[
                                    (hoverRating || rating) >= star 
                                        ? 'text-amber-400 fill-amber-400' 
                                        : 'text-slate-200 dark:text-slate-800'
                                ]"
                            />
                        </button>
                    </div>
                    <!-- Rating text descriptor -->
                    <span 
                        v-if="rating > 0 || hoverRating > 0" 
                        class="text-xs font-bold transition-all"
                        :class="[
                            (hoverRating || rating) <= 2 ? 'text-rose-500' : 
                            (hoverRating || rating) === 3 ? 'text-amber-600 dark:text-amber-500' : 'text-emerald-600 dark:text-emerald-500'
                        ]"
                    >
                        {{ ratingTexts[hoverRating || rating] }}
                    </span>
                </div>

                <!-- Empathy section for poor rating during inputs -->
                <div 
                    v-if="rating > 0 && rating <= 2" 
                    class="rounded-2xl bg-rose-50 dark:bg-rose-950/20 border border-rose-100 dark:border-rose-900/40 p-3.5 flex items-start gap-2.5 shadow-sm text-rose-700 dark:text-rose-400 animate-fadeIn"
                >
                    <AlertTriangle class="size-4 shrink-0 mt-0.5" />
                    <div class="flex-1">
                        <h4 class="text-xs font-bold">Thành thật xin lỗi quý khách!</h4>
                        <p class="text-[10px] leading-relaxed mt-0.5 opacity-90">
                            Hãy chia sẻ cụ thể điều gì khiến quý khách không hài lòng ở ô bên dưới. Chúng tôi sẽ giải quyết và đền bù thỏa đáng ngay lập tức.
                        </p>
                    </div>
                </div>

                <!-- Detailed Dish Ratings -->
                <div v-if="orderContext?.items && orderContext.items.length > 0" class="flex flex-col gap-2 border-t pt-4">
                    <Label class="text-xs font-bold text-slate-650 dark:text-slate-450">
                        Đánh giá món ăn đã dùng:
                    </Label>
                    <div class="space-y-3 mt-1">
                        <div 
                            v-for="item in orderContext.items" 
                            :key="item.product_id"
                            class="p-3 bg-slate-50/60 dark:bg-slate-900/60 rounded-2xl border border-slate-100 dark:border-slate-800/80 space-y-2"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300 line-clamp-1">
                                    {{ item.name }}
                                </span>
                                
                                <div class="flex items-center gap-0.5">
                                    <button 
                                        v-for="star in 5" 
                                        :key="star"
                                        type="button"
                                        @click="itemsRating[item.product_id] = { ...itemsRating[item.product_id], rating: star }"
                                        class="p-0.5 focus:outline-none transition-transform active:scale-90"
                                    >
                                        <Star 
                                            class="size-4.5 transition-colors" 
                                            :class="[
                                                star <= (itemsRating[item.product_id]?.rating ?? 5) 
                                                    ? 'fill-amber-400 text-amber-400' 
                                                    : 'text-slate-200 dark:text-slate-800'
                                            ]"
                                        />
                                    </button>
                                </div>
                            </div>
                            <input 
                                v-model="itemsRating[item.product_id].comment"
                                type="text" 
                                placeholder="Góp ý về món ăn này (ví dụ: ngon, mặn, nguội...)" 
                                class="w-full h-8 px-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-905 text-xxs text-slate-700 dark:text-slate-350 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            />
                        </div>
                    </div>
                </div>

                <!-- Detailed Staff Ratings -->
                <div v-if="staffList && staffList.length > 0" class="flex flex-col gap-2 border-t pt-4">
                    <Label class="text-xs font-bold text-slate-650 dark:text-slate-450">
                        Đánh giá nhân viên phục vụ ca trực:
                    </Label>
                    <div class="space-y-3 mt-1">
                        <div 
                            v-for="staff in staffList" 
                            :key="staff.employee_id"
                            class="p-3 bg-slate-50/60 dark:bg-slate-900/60 rounded-2xl border border-slate-100 dark:border-slate-800/80 space-y-2"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                        {{ staff.name }}
                                    </span>
                                    <span class="text-[9px] font-semibold text-slate-400 px-1 bg-slate-100 dark:bg-slate-800 rounded border border-slate-150 dark:border-slate-700">
                                        {{ staff.role }}
                                    </span>
                                </div>
                                
                                <div class="flex items-center gap-0.5">
                                    <button 
                                        v-for="star in 5" 
                                        :key="star"
                                        type="button"
                                        @click="staffRating[staff.employee_id] = { ...staffRating[staff.employee_id], rating: star }"
                                        class="p-0.5 focus:outline-none transition-transform active:scale-90"
                                    >
                                        <Star 
                                            class="size-4.5 transition-colors" 
                                            :class="[
                                                star <= (staffRating[staff.employee_id]?.rating ?? 5) 
                                                    ? 'fill-amber-400 text-amber-400' 
                                                    : 'text-slate-200 dark:text-slate-800'
                                            ]"
                                        />
                                    </button>
                                </div>
                            </div>
                            <input 
                                v-model="staffRating[staff.employee_id].comment"
                                type="text" 
                                placeholder="Góp ý về nhân viên này (ví dụ: nhiệt tình, chậm chạp...)" 
                                class="w-full h-8 px-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-905 text-xxs text-slate-700 dark:text-slate-350 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            />
                        </div>
                    </div>
                </div>

                <!-- Comment Input Area -->
                <div class="flex flex-col gap-1.5 border-t pt-4">
                    <Label class="text-xs font-bold text-slate-600 dark:text-slate-400">
                        Chi tiết trải nghiệm của quý khách:
                    </Label>
                    <textarea 
                        v-model="content"
                        placeholder="Hãy chia sẻ thêm ý kiến của bạn về món ăn, thái độ phục vụ hoặc không gian quán..."
                        rows="3"
                        class="w-full rounded-2xl border border-slate-200 dark:border-slate-850 bg-slate-50/50 dark:bg-slate-900 px-3.5 py-2.5 text-xs placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 dark:focus:ring-amber-500/10 transition-all resize-none"
                    ></textarea>
                </div>

                <!-- Identity selection and details -->
                <div class="flex flex-col gap-3 border-t pt-4">
                    <div class="flex items-center justify-between">
                        <Label class="text-xs font-bold text-slate-600 dark:text-slate-400">
                            Gửi đánh giá dưới dạng:
                        </Label>
                        <div class="flex items-center gap-1 bg-slate-50 dark:bg-slate-900 rounded-lg p-0.5 border border-slate-150 dark:border-slate-800">
                            <button 
                                type="button"
                                @click="isAnonymous = true"
                                :class="[
                                    'px-2.5 py-1 text-[10px] font-bold rounded-md transition-colors',
                                    isAnonymous ? 'bg-white dark:bg-slate-850 text-slate-700 dark:text-slate-200 shadow-sm' : 'text-slate-400'
                                ]"
                            >
                                Ẩn danh
                            </button>
                            <button 
                                type="button"
                                @click="isAnonymous = false"
                                :class="[
                                    'px-2.5 py-1 text-[10px] font-bold rounded-md transition-colors',
                                    !isAnonymous ? 'bg-white dark:bg-slate-850 text-slate-700 dark:text-slate-200 shadow-sm' : 'text-slate-400'
                                ]"
                            >
                                Định danh
                            </button>
                        </div>
                    </div>

                    <!-- Client detail input if not anonymous -->
                    <div v-if="!isAnonymous" class="flex flex-col gap-2.5 bg-slate-50/50 dark:bg-slate-900/30 rounded-2xl border p-3.5 animate-fadeIn">
                        <div class="flex flex-col gap-1">
                            <Label for="name" class="text-[10px] font-bold text-slate-500 dark:text-slate-400">Họ và tên:</Label>
                            <input 
                                id="name"
                                type="text"
                                v-model="submittedByName"
                                placeholder="Nhập tên của quý khách (tùy chọn)"
                                class="h-8 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-905 px-3 text-xs placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label for="phone" class="text-[10px] font-bold text-slate-500 dark:text-slate-400">
                                Số điện thoại nhận Voucher đền bù: <span class="text-rose-500 font-bold">*</span>
                            </Label>
                            <input 
                                id="phone"
                                type="tel"
                                v-model="submittedByPhone"
                                placeholder="Nhập SĐT để nhận mã giảm giá tri ân"
                                class="h-8 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-905 px-3 text-xs placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            />
                        </div>
                        <div class="flex items-center gap-1.5 text-[9px] text-slate-400 dark:text-slate-500 font-medium leading-normal">
                            <ShieldCheck class="size-3 text-emerald-500 shrink-0" />
                            <span>Thông tin của quý khách được bảo mật tuyệt đối, chỉ phục vụ gửi đền bù.</span>
                        </div>
                    </div>
                </div>

                <!-- Error panel -->
                <div 
                    v-if="errorMessage" 
                    class="rounded-xl bg-rose-50 dark:bg-rose-950/20 border border-rose-100 dark:border-rose-900/40 p-2.5 flex items-center gap-2 text-[10px] font-semibold text-rose-600 dark:text-rose-400"
                >
                    <AlertTriangle class="size-3.5 shrink-0" />
                    <span>{{ errorMessage }}</span>
                </div>

                <!-- Submit Button -->
                <button 
                    type="button"
                    @click="handleSubmit"
                    :disabled="isSubmitting"
                    class="w-full h-10 font-bold text-xs bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white rounded-2xl shadow-lg border-0 hover:shadow-xl transition-all duration-300 flex items-center justify-center gap-2 select-none"
                >
                    <Send class="size-3.5" />
                    {{ isSubmitting ? 'Đang gửi đánh giá...' : 'Gửi Đánh Giá Ngay' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
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
