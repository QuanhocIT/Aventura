<script setup lang="ts">
import { ChevronRight, UserPlus, Settings, Zap } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';

const howItWorksSteps = [
    {
        step: 1,
        icon: UserPlus,
        title: 'Đăng ký tài khoản',
        description: '30 giây, không cần thẻ tín dụng. Gói Free hoạt động ngay lập tức.',
    },
    {
        step: 2,
        icon: Settings,
        title: 'Thiết lập nhà hàng',
        description: 'Chatbot hướng dẫn từng bước: menu → bàn → nhân viên → in bill.',
    },
    {
        step: 3,
        icon: Zap,
        title: 'Vận hành ngay hôm nay',
        description: 'QR order, quản lý bếp, kiểm kho — chạy thật trong 30 phút đầu.',
    },
];
</script>

<template>
    <!-- ── Cách hoạt động ─────────────────────────────────── -->
    <section class="px-4 py-10 lg:py-12 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <div class="text-center">
                <Badge variant="outline" class="mb-3">3 bước đơn giản</Badge>
                <h2 class="text-3xl font-semibold heading-section text-gradient-brand">Bắt đầu trong 30 phút</h2>
                <p class="mx-auto mt-3 max-w-xl text-muted-foreground">
                    Không cần cài đặt phức tạp. Từ đăng ký đến vận hành thật chỉ với 3 bước.
                </p>
            </div>
            <div class="reveal-on-scroll relative mt-10 grid gap-4 md:grid-cols-3">
                <div
                    v-for="(step, i) in howItWorksSteps"
                    :key="step.step"
                    class="stagger-child group relative flex flex-col items-center rounded-2xl border border-white/15 dark:border-white/10 bg-card/90 dark:bg-card/80 backdrop-blur-sm p-6 text-center transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-primary/5 hover:border-primary/30"
                >
                    <!-- Connector arrow -->
                    <div
                        v-if="i < 2"
                        class="absolute -right-2.5 top-1/2 z-10 hidden -translate-y-1/2 md:block"
                    >
                        <ChevronRight class="size-5 text-muted-foreground" />
                    </div>
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-primary/20 bg-primary/5 text-primary transition-all duration-300 group-hover:bg-primary/10 group-hover:shadow-lg group-hover:shadow-primary/15 group-hover:scale-105">
                        <component :is="step.icon" class="size-7" />
                    </div>
                    <span class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Bước {{ step.step }}</span>
                    <h3 class="mb-2 text-base font-semibold">{{ step.title }}</h3>
                    <p class="text-sm leading-relaxed text-muted-foreground">{{ step.description }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
