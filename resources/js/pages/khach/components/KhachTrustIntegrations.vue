<script setup lang="ts">
import { ShieldCheck, Award, Lock } from 'lucide-vue-next';

const integrations = [
    { name: 'VNPAY',            emoji: '💳', tag: 'Thanh toán' },
    { name: 'ZaloPay',          emoji: '📱', tag: 'Thanh toán' },
    { name: 'MoMo',             emoji: '💜', tag: 'Thanh toán' },
    { name: 'Grab Food',        emoji: '🛵', tag: 'Giao hàng'  },
    { name: 'ShopeeFood',       emoji: '🧡', tag: 'Giao hàng'  },
    { name: 'Máy in nhiệt',     emoji: '🖨️', tag: 'Thiết bị'   },
    { name: 'Máy POS',          emoji: '🖥️', tag: 'Thiết bị'   },
    { name: 'Google Analytics', emoji: '📊', tag: 'Analytics'  },
    { name: 'Facebook Pixel',   emoji: '📘', tag: 'Marketing'  },
    { name: 'Zalo OA',          emoji: '💬', tag: 'CRM'        },
    { name: 'MISA',             emoji: '📋', tag: 'Kế toán'    },
    { name: 'Webhook API',      emoji: '⚙️', tag: 'Developer'  },
];

const certifications = [
    { icon: ShieldCheck, label: 'SSL/TLS 256-bit',  sub: 'Mã hóa toàn bộ dữ liệu'   },
    { icon: Lock,        label: 'GDPR Compliant',    sub: 'Bảo vệ dữ liệu người dùng' },
    { icon: Award,       label: 'SLA 99.9%',         sub: 'Uptime đảm bảo hàng tháng' },
];
</script>

<template>
    <section id="integrations" class="relative overflow-hidden py-20 lg:py-24 bg-background">
        <!-- Subtle grid background that respects theme -->
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,theme(colors.border)_1px,transparent_0)] [background-size:40px_40px] opacity-60" />

        <div class="relative mx-auto max-w-7xl px-4 lg:px-8 space-y-16">

            <!-- ─── Trust Badges ─── -->
            <div class="reveal-on-scroll text-center">
                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-6">Bảo mật & chứng chỉ</p>
                <div class="inline-flex flex-wrap justify-center gap-4">
                    <div
                        v-for="cert in certifications"
                        :key="cert.label"
                        class="flex items-center gap-3 rounded-2xl border border-border bg-card px-5 py-3.5 shadow-sm hover:border-primary/40 hover:bg-accent transition-all duration-300"
                    >
                        <component :is="cert.icon" class="h-5 w-5 text-primary shrink-0" />
                        <div class="text-left">
                            <p class="text-sm font-semibold text-foreground">{{ cert.label }}</p>
                            <p class="text-xs text-muted-foreground">{{ cert.sub }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Divider -->
            <div class="h-px bg-border" />

            <!-- ─── Integrations ─── -->
            <div>
                <div class="reveal-on-scroll mb-10 text-center">
                    <span class="inline-block rounded-full border border-primary/30 bg-primary/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-primary mb-4">
                        Tích hợp
                    </span>
                    <h2 class="text-3xl font-bold text-foreground sm:text-4xl">
                        Kết nối <span class="text-primary">mọi công cụ</span> bạn đang dùng
                    </h2>
                    <p class="mt-3 text-muted-foreground max-w-xl mx-auto">
                        Từ thanh toán, giao hàng đến kế toán — Aventura tích hợp sẵn, không cần setup phức tạp.
                    </p>
                </div>

                <div class="reveal-on-scroll grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                    <div
                        v-for="item in integrations"
                        :key="item.name"
                        class="group flex flex-col items-center gap-2 rounded-xl border border-border bg-card p-4 text-center cursor-default transition-all duration-300 hover:-translate-y-1 hover:shadow-md hover:border-primary/30 hover:bg-accent"
                    >
                        <span class="text-2xl">{{ item.emoji }}</span>
                        <span class="text-xs font-semibold text-foreground leading-tight">{{ item.name }}</span>
                        <span class="rounded-full bg-muted border border-border px-2 py-0.5 text-[10px] text-muted-foreground group-hover:text-foreground transition-colors">
                            {{ item.tag }}
                        </span>
                    </div>

                    <!-- More coming soon -->
                    <div class="flex flex-col items-center justify-center gap-1.5 rounded-xl border border-dashed border-border p-4 text-center">
                        <span class="text-2xl">➕</span>
                        <span class="text-xs font-medium text-muted-foreground">Và nhiều hơn...</span>
                    </div>
                </div>

                <p class="mt-6 text-center text-sm text-muted-foreground">
                    Không tìm thấy phần mềm bạn đang dùng?
                    <a href="#" class="text-primary hover:underline underline-offset-2 transition-colors">Yêu cầu tích hợp mới</a>
                </p>
            </div>
        </div>
    </section>
</template>
