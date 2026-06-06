<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Building2, LineChart, Rocket } from 'lucide-vue-next';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

const statsVisible   = ref(false);
const countRestaurants = ref(0);
const countOrders      = ref(0);
const countUptime      = ref(0);
let statsObserver: IntersectionObserver | null = null;

function animateCount(target: number, setter: (v: number) => void, duration = 1800) {
    const start     = performance.now();
    const step = (now: number) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased    = 1 - Math.pow(1 - progress, 3);
        setter(Math.round(target * eased));

        if (progress < 1) {
            requestAnimationFrame(step);
        }
    };
    requestAnimationFrame(step);
}

function startCountUp() {
    if (statsVisible.value) {
        return;
    }

    statsVisible.value = true;
    animateCount(500,  v => countRestaurants.value = v);
    animateCount(3000, v => countOrders.value      = v);
    animateCount(999,  v => countUptime.value      = v);
}

onMounted(() => {
    // Count-up stats observer
    statsObserver = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) {
                startCountUp();
            } 
        },
        { threshold: 0.3 }
    );
    const statsEl = document.getElementById('stats-section');

    if (statsEl) {
        statsObserver.observe(statsEl);
    }
});

onUnmounted(() => {
    statsObserver?.disconnect();
});
</script>

<template>
    <!-- ── Stats + Value Props ───────────────────────────────── -->
    <section id="stats-section" class="border-y border-border bg-muted/30 px-4 py-10 lg:py-12 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <!-- Số liệu — animated count-up -->
            <div class="grid grid-cols-3 gap-6 text-center">
                <div>
                    <p class="text-4xl font-extrabold tracking-tight tabular-nums">
                        {{ countRestaurants }}+
                    </p>
                    <p class="mt-1.5 text-sm text-muted-foreground">Nhà hàng đang vận hành</p>
                </div>
                <div>
                    <p class="text-4xl font-extrabold tracking-tight tabular-nums">
                        {{ (countOrders / 1000).toFixed(1) }}K+
                    </p>
                    <p class="mt-1.5 text-sm text-muted-foreground">Đơn hàng đã xử lý</p>
                </div>
                <div>
                    <p class="text-4xl font-extrabold tracking-tight tabular-nums">
                        {{ (countUptime / 10).toFixed(1) }}%
                    </p>
                    <p class="mt-1.5 text-sm text-muted-foreground">Uptime cam kết SLA</p>
                </div>
            </div>

            <!-- 3 value prop cards -->
            <div class="mt-10 grid gap-4 md:grid-cols-3">
                <Card class="reveal-on-scroll group border-border transition-shadow hover:shadow-md" style="transition-delay: 0ms">
                    <CardHeader>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-primary/20 bg-primary/5 text-primary transition-transform group-hover:scale-105">
                            <Building2 class="size-5" />
                        </div>
                        <CardTitle class="mt-3 text-base">Quản lý chuỗi quy mô lớn</CardTitle>
                        <CardDescription>
                            Multi-branch với dữ liệu tenant tách biệt. Một tài khoản kiểm soát toàn bộ hệ thống chi nhánh, báo cáo hợp nhất theo thời gian thực.
                        </CardDescription>
                    </CardHeader>
                </Card>
                <Card class="reveal-on-scroll group border-border transition-shadow hover:shadow-md" style="transition-delay: 150ms">
                    <CardHeader>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-primary/20 bg-primary/5 text-primary transition-transform group-hover:scale-105">
                            <LineChart class="size-5" />
                        </div>
                        <CardTitle class="mt-3 text-base">Minh bạch từng thao tác</CardTitle>
                        <CardDescription>
                            Audit log ghi vết mọi thay đổi nhạy cảm — thay giá, hủy đơn, xóa dữ liệu. Không thể tẩy xóa, tra soát bất kỳ lúc nào.
                        </CardDescription>
                    </CardHeader>
                </Card>
                <Card class="reveal-on-scroll group border-border transition-shadow hover:shadow-md" style="transition-delay: 300ms">
                    <CardHeader>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-primary/20 bg-primary/5 text-primary transition-transform group-hover:scale-105">
                            <Rocket class="size-5" />
                        </div>
                        <CardTitle class="mt-3 text-base">Onboarding không cần giải thích</CardTitle>
                        <CardDescription>
                            Đăng ký xong, chatbot hướng dẫn từng bước từ menu → bàn → nhân viên → order. Vận hành thật ngay trong 30 phút đầu.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>
        </div>
    </section>
</template>
