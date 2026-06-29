<script setup lang="ts">
import { ref } from 'vue';
import { TrendingUp, Clock, Users, ChevronRight, ChevronLeft, Star, Quote } from 'lucide-vue-next';

const activeCase = ref(0);

const cases = [
    {
        restaurant: 'Bếp Nhà Mình',
        type: 'Nhà hàng gia đình · 2 chi nhánh · Đà Nẵng',
        avatar: 'B',
        owner: 'Chị Nguyễn Lan Anh – Chủ nhà hàng',
        quote: '"Trước khi dùng Aventura, tôi mất 2 giờ mỗi đêm để kiểm kê tồn kho và tính lương nhân viên. Giờ tất cả tự động, tôi chỉ cần xem báo cáo sáng hôm sau là biết hết."',
        problem: 'Quản lý bằng Excel, sai số liệu thường xuyên, không biết món nào đang lỗ',
        solution: 'Triển khai QR Order + KDS + báo cáo AI trong 3 ngày',
        metrics: [
            { icon: TrendingUp, value: '+34%', label: 'Doanh thu tháng 3'    },
            { icon: Clock,      value: '2.5h',  label: 'Tiết kiệm/ngày'      },
            { icon: Users,      value: '91%',   label: 'Giảm sai món'        },
        ],
        timeline: '3 tháng sử dụng',
        rating: 5,
    },
    {
        restaurant: 'The Rooftop Bar & Grill',
        type: 'Bar & Restaurant · 1 chi nhánh · TP.HCM',
        avatar: 'R',
        owner: 'Anh Trần Minh Đức – F&B Manager',
        quote: '"Chúng tôi có peak hour rất ngắn — 7pm đến 10pm. Aventura giúp bếp xử lý 40% đơn nhiều hơn trong khung giờ đó mà không cần thêm nhân viên. ROI đạt trong tháng đầu tiên."',
        problem: 'Bếp bị quá tải giờ cao điểm, table turn thấp, nhân viên tự ý huỷ order',
        solution: 'KDS + fraud detection + tối ưu quy trình với AI gợi ý',
        metrics: [
            { icon: TrendingUp, value: '+40%', label: 'Đơn giờ cao điểm'    },
            { icon: Clock,      value: '28%',   label: 'Giảm table turn time'},
            { icon: Users,      value: '0',     label: 'Fraud incident/tháng'},
        ],
        timeline: '2 tháng sử dụng',
        rating: 5,
    },
];

function next() { activeCase.value = (activeCase.value + 1) % cases.length; }
function prev() { activeCase.value = (activeCase.value - 1 + cases.length) % cases.length; }
</script>

<template>
    <section id="case-study" class="relative overflow-hidden py-20 lg:py-28 bg-muted/30">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[400px] rounded-full bg-primary/5 blur-3xl" />
        </div>

        <div class="relative mx-auto max-w-7xl px-4 lg:px-8">
            <!-- Header -->
            <div class="reveal-on-scroll mb-12 text-center">
                <span class="inline-block rounded-full border border-green-500/30 bg-green-500/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-green-600 dark:text-green-400 mb-4">
                    Case Study
                </span>
                <h2 class="text-3xl font-bold text-foreground sm:text-4xl">
                    Kết quả thực tế từ <span class="text-primary">khách hàng</span>
                </h2>
                <p class="mt-3 text-muted-foreground max-w-xl mx-auto">
                    Không phải số liệu demo — đây là dữ liệu thật từ nhà hàng đang dùng Aventura.
                </p>
            </div>

            <!-- Case card -->
            <div class="reveal-on-scroll">
                <Transition name="case-slide" mode="out-in">
                    <div :key="activeCase" class="grid gap-8 lg:grid-cols-2 lg:gap-12">

                        <!-- Left: Story -->
                        <div class="space-y-6">
                            <!-- Restaurant header -->
                            <div class="flex items-center gap-4">
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-primary/10 border border-primary/20 text-2xl font-black text-primary shadow-sm">
                                    {{ cases[activeCase].avatar }}
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-foreground">{{ cases[activeCase].restaurant }}</h3>
                                    <p class="text-sm text-muted-foreground">{{ cases[activeCase].type }}</p>
                                    <div class="flex items-center gap-0.5 mt-1">
                                        <Star v-for="i in cases[activeCase].rating" :key="i" class="h-3.5 w-3.5 fill-primary text-primary" />
                                    </div>
                                </div>
                            </div>

                            <!-- Quote -->
                            <div class="relative rounded-2xl border border-border bg-card p-6 shadow-sm">
                                <Quote class="absolute top-4 left-4 h-6 w-6 text-primary/20" />
                                <p class="relative text-sm leading-relaxed text-muted-foreground italic pl-2">
                                    {{ cases[activeCase].quote }}
                                </p>
                                <p class="mt-4 text-xs font-semibold text-primary">— {{ cases[activeCase].owner }}</p>
                            </div>

                            <!-- Before/After -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-xl border border-destructive/20 bg-destructive/5 p-4">
                                    <p class="text-xs font-bold text-destructive uppercase tracking-wider mb-2">❌ Trước</p>
                                    <p class="text-xs text-muted-foreground leading-relaxed">{{ cases[activeCase].problem }}</p>
                                </div>
                                <div class="rounded-xl border border-green-500/20 bg-green-500/5 p-4">
                                    <p class="text-xs font-bold text-green-600 dark:text-green-400 uppercase tracking-wider mb-2">✅ Sau</p>
                                    <p class="text-xs text-muted-foreground leading-relaxed">{{ cases[activeCase].solution }}</p>
                                </div>
                            </div>

                            <p class="text-xs text-muted-foreground">📅 {{ cases[activeCase].timeline }}</p>
                        </div>

                        <!-- Right: Metrics -->
                        <div class="space-y-4 flex flex-col justify-center">
                            <h4 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground mb-2">Kết quả đo được</h4>
                            <div
                                v-for="metric in cases[activeCase].metrics"
                                :key="metric.label"
                                class="flex items-center gap-5 rounded-2xl border border-border bg-card p-5 shadow-sm transition-all hover:scale-[1.01] hover:shadow-md hover:border-primary/30"
                            >
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 border border-primary/20">
                                    <component :is="metric.icon" class="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <p class="text-3xl font-black text-primary">{{ metric.value }}</p>
                                    <p class="text-sm text-muted-foreground">{{ metric.label }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Navigation -->
                <div class="mt-10 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span
                            v-for="(_, i) in cases"
                            :key="i"
                            class="h-2 rounded-full transition-all duration-300 cursor-pointer"
                            :class="i === activeCase ? 'w-8 bg-primary' : 'w-2 bg-muted-foreground/30 hover:bg-muted-foreground/60'"
                            @click="activeCase = i"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="prev"
                            class="flex h-10 w-10 items-center justify-center rounded-full border border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground transition-all"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </button>
                        <button
                            @click="next"
                            class="flex h-10 w-10 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-primary hover:bg-primary/20 transition-all"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.case-slide-enter-active,
.case-slide-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.case-slide-enter-from { opacity: 0; transform: translateX(20px);  }
.case-slide-leave-to   { opacity: 0; transform: translateX(-20px); }
</style>
