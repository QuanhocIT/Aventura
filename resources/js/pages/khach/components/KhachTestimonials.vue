<script setup lang="ts">
import { Star, ShieldCheck, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';
import { Badge } from '@/components/ui/badge';

const testimonials = [
    {
        name: 'Trần Văn Hùng',
        restaurant: 'Phở Thái Sơn (3 chi nhánh)',
        text: 'Audit log giúp tôi tìm ra nhân viên thu tiền sai trong 5 phút. Gói Pro đáng từng đồng.',
        stars: 5,
    },
    {
        name: 'Nguyễn Thu Hà',
        restaurant: 'Cơm Tấm Hà My',
        text: 'AI dự báo nguyên liệu chính xác đến 90%. Giảm được 20% lãng phí thực phẩm mỗi tuần.',
        stars: 5,
    },
    {
        name: 'Lê Quốc Bảo',
        restaurant: 'Chuỗi Lẩu Đại Phú Gia',
        text: 'Multi-branch không cần 5 tab, 1 dashboard thấy hết. Tiết kiệm thời gian quản lý gấp đôi.',
        stars: 5,
    },
    {
        name: 'Phạm Minh Tuấn',
        restaurant: 'The Coffee House (Quận 1)',
        text: 'QR Order giúp khách tự gọi món nhanh chóng, nhân viên bớt áp lực giờ cao điểm. Doanh thu tăng 15%.',
        stars: 5,
    },
    {
        name: 'Hoàng Lệ Quyên',
        restaurant: 'Bánh Mì & Trà sữa Corner',
        text: 'Hạ tầng cloud rất ổn định, order in bill trơn tru. Báo cáo tài chính chi tiết từng ngày rất dễ hiểu.',
        stars: 5,
    },
];

const activeTestimonialIdx = ref(0);
let testimonialAutoplayTimer: any = null;

const startTestimonialAutoplay = () => {
    if (testimonialAutoplayTimer) {
return;
}

    testimonialAutoplayTimer = setInterval(() => {
        nextTestimonial();
    }, 5000);
};

const stopTestimonialAutoplay = () => {
    if (testimonialAutoplayTimer) {
        clearInterval(testimonialAutoplayTimer);
        testimonialAutoplayTimer = null;
    }
};

const nextTestimonial = () => {
    activeTestimonialIdx.value = (activeTestimonialIdx.value + 1) % testimonials.length;
};

const prevTestimonial = () => {
    activeTestimonialIdx.value = (activeTestimonialIdx.value - 1 + testimonials.length) % testimonials.length;
};

const resetTestimonialAutoplay = () => {
    stopTestimonialAutoplay();
    startTestimonialAutoplay();
};

const getInitials = (name: string) => {
    if (!name) {
return '';
}

    const parts = name.trim().split(/\s+/);

    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    return name.slice(0, 2).toUpperCase();
};

const getAvatarGradient = (idx: number) => {
    const gradients = [
        'bg-gradient-to-br from-amber-400 to-orange-500 text-zinc-950',
        'bg-gradient-to-br from-indigo-400 to-purple-500 text-white',
        'bg-gradient-to-br from-emerald-400 to-teal-500 text-white',
        'bg-gradient-to-br from-sky-400 to-blue-500 text-white',
        'bg-gradient-to-br from-rose-400 to-pink-500 text-white',
    ];

    return gradients[idx % gradients.length];
};

onMounted(() => {
    startTestimonialAutoplay();
});

onUnmounted(() => {
    stopTestimonialAutoplay();
});
</script>

<template>
    <!-- ── Testimonials (Premium Redesign) ──────────────────────────────────────── -->
    <section id="testimonials" class="relative overflow-hidden border-y border-border/80 bg-gradient-to-b from-muted/10 via-muted/30 to-muted/10 px-4 py-8 lg:py-10 lg:px-8">
        <!-- Hiệu ứng ánh sáng nền (Glow Effect) -->
        <div class="pointer-events-none absolute top-1/2 left-1/3 h-[500px] w-[500px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-amber-500/5 blur-[140px]"></div>
        <div class="pointer-events-none absolute top-1/2 right-1/4 h-[400px] w-[400px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary/5 blur-[140px]"></div>

        <div class="mx-auto max-w-7xl relative z-10">
            <div class="grid gap-12 lg:grid-cols-[0.85fr_1.15fr] lg:items-center">
                
                <!-- CỘT TRÁI: Tiêu đề & Đánh giá tổng quan -->
                <div class="space-y-8">
                    <div class="space-y-4">
                        <Badge variant="outline" class="border-amber-500/30 bg-amber-500/5 text-amber-600 dark:text-amber-400 px-3 py-1 text-xs font-semibold uppercase tracking-wider">
                            Khách hàng nói gì
                        </Badge>
                        <h2 class="text-4xl font-extrabold tracking-tight lg:text-5xl leading-[1.1] text-zinc-950 dark:text-zinc-50">
                            Được tin dùng bởi <br />
                            chủ quán thực tế
                        </h2>
                        <p class="text-base text-muted-foreground leading-relaxed max-w-md">
                            Xem các câu chuyện thành công từ những nhà hàng đang tối ưu vận hành và đột phá doanh thu mỗi ngày cùng Aventura.
                        </p>
                    </div>

                    <!-- Thẻ đánh giá tổng hợp (Social Proof) -->
                    <div class="flex items-center gap-5 rounded-3xl border border-white/20 dark:border-zinc-800/40 bg-white/40 dark:bg-zinc-950/40 backdrop-blur-md p-5 shadow-xl w-fit transition-all duration-300 hover:shadow-2xl">
                        <div class="bg-amber-500/10 text-amber-500 rounded-2xl p-3 flex flex-col items-center justify-center min-w-[75px] shadow-sm">
                            <span class="text-3xl font-extrabold tracking-tight">4.9</span>
                            <div class="flex gap-0.5 mt-1">
                                <Star class="size-3 fill-amber-500 text-amber-500" v-for="i in 5" :key="i" />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <div class="flex -space-x-3">
                                <div class="size-8 rounded-full border-2 border-white dark:border-zinc-900 bg-gradient-to-br from-indigo-500 to-purple-600 text-[10px] font-bold text-white flex items-center justify-center shadow-sm">TH</div>
                                <div class="size-8 rounded-full border-2 border-white dark:border-zinc-900 bg-gradient-to-br from-emerald-400 to-teal-600 text-[10px] font-bold text-white flex items-center justify-center shadow-sm">NH</div>
                                <div class="size-8 rounded-full border-2 border-white dark:border-zinc-900 bg-gradient-to-br from-amber-400 to-orange-600 text-[10px] font-bold text-white flex items-center justify-center shadow-sm">QB</div>
                                <div class="size-8 rounded-full border-2 border-white dark:border-zinc-900 bg-zinc-800 text-[9px] font-bold text-zinc-400 flex items-center justify-center shadow-sm">+200</div>
                            </div>
                            <p class="text-xs text-muted-foreground font-semibold flex items-center gap-1">
                                Hơn <span class="text-zinc-950 dark:text-zinc-200 font-extrabold">200+ chủ quán</span> tin cậy vận hành
                            </p>
                        </div>
                    </div>

                    <!-- Điều hướng (Prev / Next Buttons) -->
                    <div class="flex items-center gap-4 pt-2">
                        <button 
                            @click="prevTestimonial(); resetTestimonialAutoplay();"
                            class="flex size-11 items-center justify-center rounded-full border border-border bg-card hover:bg-zinc-950 hover:text-white dark:hover:bg-white dark:hover:text-zinc-950 transition-all duration-300 active:scale-95 cursor-pointer shadow-md hover:shadow-lg"
                            aria-label="Trước"
                        >
                            <ChevronLeft class="size-5" />
                        </button>
                        
                        <!-- Dấu chấm tròn (Indicator dots) -->
                        <div class="flex gap-2 mx-1">
                            <button
                                v-for="(t, idx) in testimonials"
                                :key="idx"
                                @click="activeTestimonialIdx = idx; resetTestimonialAutoplay();"
                                class="h-2.5 rounded-full transition-all duration-300 cursor-pointer"
                                :class="activeTestimonialIdx === idx ? 'w-8 bg-amber-500' : 'w-2.5 bg-zinc-300 dark:bg-zinc-800 hover:bg-zinc-400 dark:hover:bg-zinc-700'"
                            />
                        </div>

                        <button 
                            @click="nextTestimonial(); resetTestimonialAutoplay();"
                            class="flex size-11 items-center justify-center rounded-full border border-border bg-card hover:bg-zinc-950 hover:text-white dark:hover:bg-white dark:hover:text-zinc-950 transition-all duration-300 active:scale-95 cursor-pointer shadow-md hover:shadow-lg"
                            aria-label="Sau"
                        >
                            <ChevronRight class="size-5" />
                        </button>
                    </div>
                </div>

                <!-- CỘT PHẢI: Thẻ bình luận tâm điểm có chuyển động -->
                <div class="relative min-h-[320px] sm:min-h-[260px] flex items-center">
                    <Transition name="testimonial-fade" mode="out-in">
                        <div 
                            :key="activeTestimonialIdx"
                            @mouseenter="stopTestimonialAutoplay"
                            @mouseleave="startTestimonialAutoplay"
                            class="group relative w-full rounded-3xl border border-zinc-200/80 dark:border-zinc-800/80 bg-white dark:bg-zinc-950 p-8 sm:p-10 shadow-2xl transition-all duration-500 hover:border-amber-500/20 hover:shadow-amber-500/5"
                        >
                            <!-- Ký tự dấu nháy kép lớn ẩn phía sau -->
                            <span class="absolute top-4 right-8 font-serif text-[150px] leading-none text-zinc-200/40 dark:text-zinc-800/20 select-none pointer-events-none group-hover:text-amber-500/5 transition-colors">“</span>

                            <div class="relative z-10 flex flex-col h-full justify-between gap-6">
                                <!-- Đánh giá Star & Nhãn Verified -->
                                <div class="flex items-center justify-between flex-wrap gap-2 pb-2">
                                    <div class="flex gap-1">
                                        <Star 
                                            v-for="s in testimonials[activeTestimonialIdx].stars" 
                                            :key="s" 
                                            class="size-5 fill-amber-400 text-amber-400" 
                                        />
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 px-3 py-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wide shadow-sm">
                                        <ShieldCheck class="size-3.5 text-emerald-500" />
                                        Chủ quán đã xác thực
                                    </span>
                                </div>

                                <!-- Nội dung phản hồi -->
                                <p class="text-xl sm:text-2xl font-bold leading-relaxed tracking-tight text-zinc-900 dark:text-zinc-100 font-sans">
                                    “{{ testimonials[activeTestimonialIdx].text }}”
                                </p>

                                <!-- Thông tin người dùng -->
                                <div class="flex items-center gap-4 border-t border-zinc-100 dark:border-zinc-900 pt-6">
                                    <!-- Avatar tròn có gradient màu ngẫu nhiên/theo index -->
                                    <div 
                                        class="flex size-14 shrink-0 items-center justify-center rounded-full font-bold shadow-md ring-4 ring-zinc-50 dark:ring-zinc-900"
                                        :class="getAvatarGradient(activeTestimonialIdx)"
                                    >
                                        {{ getInitials(testimonials[activeTestimonialIdx].name) }}
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-bold text-zinc-900 dark:text-zinc-50 leading-tight">{{ testimonials[activeTestimonialIdx].name }}</h4>
                                        <p class="text-xs text-muted-foreground mt-1 font-semibold flex items-center gap-1.5">
                                            <span class="inline-block size-2 bg-amber-500/80 rounded-full"></span>
                                            {{ testimonials[activeTestimonialIdx].restaurant }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
                
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Testimonial Transition */
.testimonial-fade-enter-active,
.testimonial-fade-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.testimonial-fade-enter-from {
    opacity: 0;
    transform: translateY(10px);
}
.testimonial-fade-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
