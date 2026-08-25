<script setup lang="ts">
import { ChevronRight, UserPlus, Settings, Zap } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';

const howItWorksSteps = [
    {
        step: 1,
        icon: UserPlus,
        title: 'Đăng ký tài khoản',
        description:
            '30 giây, không cần thẻ tín dụng. Gói Free hoạt động ngay lập tức.',
    },
    {
        step: 2,
        icon: Settings,
        title: 'Thiết lập nhà hàng',
        description:
            'Chatbot hướng dẫn từng bước: menu → bàn → nhân viên → in bill.',
    },
    {
        step: 3,
        icon: Zap,
        title: 'Vận hành ngay hôm nay',
        description:
            'QR order, quản lý bếp, kiểm kho — chạy thật trong 30 phút đầu.',
    },
];
</script>

<template>
    <!-- ── Cách hoạt động ─────────────────────────────────── -->
    <section class="px-4 py-10 lg:px-8 lg:py-12">
        <div class="mx-auto max-w-7xl">
            <div class="text-center">
                <Badge variant="outline" class="mb-3">3 bước đơn giản</Badge>
                <h2
                    class="heading-section text-gradient-brand text-3xl font-semibold"
                >
                    Bắt đầu trong 30 phút
                </h2>
                <p class="mx-auto mt-3 max-w-xl text-muted-foreground">
                    Không cần cài đặt phức tạp. Từ đăng ký đến vận hành thật chỉ
                    với 3 bước.
                </p>
            </div>
            <div
                class="reveal-on-scroll relative mt-8 grid grid-cols-3 gap-2 sm:mt-10 sm:gap-4"
            >
                <div
                    v-for="(step, i) in howItWorksSteps"
                    :key="step.step"
                    class="stagger-child group relative flex min-w-0 flex-col items-center rounded-2xl border border-white/15 bg-card/90 p-2 text-center backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:border-primary/30 hover:shadow-xl hover:shadow-primary/5 sm:p-6 dark:border-white/10 dark:bg-card/80"
                >
                    <!-- Connector arrow -->
                    <div
                        v-if="i < 2"
                        class="absolute top-1/2 -right-2.5 z-10 hidden -translate-y-1/2 md:block"
                    >
                        <ChevronRight class="size-5 text-muted-foreground" />
                    </div>
                    <div
                        class="mb-2 flex h-9 w-9 items-center justify-center rounded-xl border border-primary/20 bg-primary/5 text-primary transition-all duration-300 group-hover:scale-105 group-hover:bg-primary/10 group-hover:shadow-lg group-hover:shadow-primary/15 sm:mb-4 sm:h-14 sm:w-14 sm:rounded-2xl"
                    >
                        <component :is="step.icon" class="size-5 sm:size-7" />
                    </div>
                    <span
                        class="mb-1 text-[9px] font-semibold tracking-wider text-muted-foreground uppercase sm:text-xs"
                        >Bước {{ step.step }}</span
                    >
                    <h3
                        class="mb-1 text-[11px] leading-tight font-semibold sm:mb-2 sm:text-base"
                    >
                        {{ step.title }}
                    </h3>
                    <p
                        class="text-[10px] leading-tight text-muted-foreground sm:text-sm sm:leading-relaxed"
                    >
                        {{ step.description }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
