<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    BarChart3, Bot, Building2, Check, ChevronLeft, ChevronRight,
    Clock3, LineChart, Monitor, Package, QrCode, Rocket,
    ShieldCheck, Star, Users, X,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import ChatbotWidget from '@/components/ChatbotWidget.vue';
import NewsCard from '@/components/NewsCard.vue';
import AppTopbarLayout from '@/layouts/AppTopbarLayout.vue';
import { register } from '@/routes';

interface BannerItem {
    id: number; title?: string | null; subtitle?: string | null;
    image_url: string; link_url?: string | null;
}
interface NewsItem {
    id: number; title: string; slug: string; excerpt: string | null;
    category: string; featured_image_url: string | null;
    is_featured: boolean; published_at: string;
}
interface PlanItem {
    id: number; code: string; name: string; price: number;
    billing_cycle: string; max_branches: number; max_tables: number;
    max_users: number; features: Record<string, unknown>;
}

const props = defineProps<{
    canRegister: boolean;
    banners?: Record<string, BannerItem[]>;
    latestNews?: NewsItem[];
    plans?: PlanItem[];
}>();

const heroBanners = computed(() => props.banners?.hero ?? []);
const promoBanners = computed(() => props.banners?.promo ?? []);
const freePlan = computed(() => props.plans?.find(p => p.price === 0) ?? null);
const proPlan = computed(() => props.plans?.find(p => p.price > 0) ?? null);
const formatPrice = (price: number) =>
    price === 0 ? '0đ' : price.toLocaleString('vi-VN') + 'đ';

// ── Hero carousel ──────────────────────────────────────────────────────────────
const heroIndex = ref(0);
const progress = ref(0);
const SLIDE_DURATION = 6000;
let heroTimer: ReturnType<typeof setInterval> | null = null;
let progressTimer: ReturnType<typeof setInterval> | null = null;

function startProgress() {
    if (progressTimer) clearInterval(progressTimer);
    progress.value = 0;
    if (heroBanners.value.length < 2) return;
    const step = 100 / (SLIDE_DURATION / 50);
    progressTimer = setInterval(() => {
        progress.value = Math.min(progress.value + step, 100);
    }, 50);
}

function heroPrev() {
    heroIndex.value = (heroIndex.value - 1 + heroBanners.value.length) % heroBanners.value.length;
    resetHeroTimer();
}
function heroNext() {
    heroIndex.value = (heroIndex.value + 1) % heroBanners.value.length;
    resetHeroTimer();
}
function goToSlide(i: number) {
    if (i === heroIndex.value) return;
    heroIndex.value = i;
    resetHeroTimer();
}
function resetHeroTimer() {
    if (heroTimer) clearInterval(heroTimer);
    startProgress();
    if (heroBanners.value.length > 1) {
        heroTimer = setInterval(() => {
            heroIndex.value = (heroIndex.value + 1) % heroBanners.value.length;
            startProgress();
        }, SLIDE_DURATION);
    }
}

// ── Scroll reveal ──────────────────────────────────────────────────────────────
let revealObserver: IntersectionObserver | null = null;

onMounted(() => {
    resetHeroTimer();
    revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                revealObserver?.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(el => revealObserver!.observe(el));
});

onUnmounted(() => {
    if (heroTimer) clearInterval(heroTimer);
    if (progressTimer) clearInterval(progressTimer);
    revealObserver?.disconnect();
});

// ── Demo tabs ──────────────────────────────────────────────────────────────────
const demoTabs = [
    { key: 'pos', label: 'POS' },
    { key: 'kds', label: 'Bếp' },
    { key: 'report', label: 'Báo cáo' },
] as const;
const activeDemo = ref<(typeof demoTabs)[number]['key']>('pos');
const demoState = computed(() => {
    if (activeDemo.value === 'kds') return {
        title: 'Kitchen board',
        left: ['2 order chờ', '1 order đang làm', '0 trễ SLA'],
        right: [{ label: 'Bún bò', status: 'Đang làm' }, { label: 'Cơm gà', status: 'Sẵn sàng' }, { label: 'Trà đào', status: 'Chờ in bill' }],
    };
    if (activeDemo.value === 'report') return {
        title: 'Daily snapshot',
        left: ['Doanh thu: 12,8M', 'Tỷ lệ hoàn thành: 98%', 'Món bán chạy: Phở bò'],
        right: [{ label: 'Giờ cao điểm', status: '11:30–13:30' }, { label: 'Cảnh báo kho', status: '2 nguyên liệu' }, { label: 'Audit', status: '1 thay đổi giá' }],
    };
    return {
        title: 'POS checkout',
        left: ['Bàn 12', '3 món', 'Tổng: 168.000đ'],
        right: [{ label: 'Phở bò tái', status: 'x2' }, { label: 'Trà chanh', status: 'x1' }, { label: 'Thanh toán', status: 'Hoàn tất' }],
    };
});

// ── Data ───────────────────────────────────────────────────────────────────────
const featureMap = [
    { icon: QrCode, title: 'QR Order', description: 'Khách quét QR tại bàn — tạo order nhanh, giảm sai sót, giảm tải nhân viên.' },
    { icon: Monitor, title: 'Kitchen Display', description: 'Đơn lên bếp theo realtime, trạng thái rõ ràng, hạn chế nhầm lẫn giờ cao điểm.' },
    { icon: Package, title: 'Inventory', description: 'Trừ kho theo định lượng, theo dõi nhập xuất, cảnh báo thiếu nguyên liệu.' },
    { icon: Users, title: 'Nhân sự', description: 'Quản lý nhân sự, ca làm, vai trò, chấm công và hỗ trợ tính lương.' },
    { icon: BarChart3, title: 'Analytics', description: 'Theo dõi doanh thu, hiệu suất, xu hướng món bán chạy, báo cáo vận hành.' },
    { icon: Building2, title: 'Multi-branch', description: 'Một tài khoản quản lý nhiều chi nhánh, dữ liệu tách biệt theo tenant.' },
    { icon: ShieldCheck, title: 'Audit Log', description: 'Ghi vết thao tác nhạy cảm để tra soát, giảm phụ thuộc vào tính trung thực.' },
    { icon: Bot, title: 'AI Insights', description: 'Tổng hợp tín hiệu dữ liệu để gợi ý cảnh báo, dự báo và phát hiện bất thường.' },
    { icon: Clock3, title: 'Queue', description: 'Tác vụ nặng chạy nền, giữ UI bán hàng phản hồi nhanh trong mọi điều kiện.' },
];

const faq = [
    { q: 'Gói Free có phù hợp cho quán nhỏ không?', a: 'Có. Free phù hợp để thử vận hành thực tế với 1 chi nhánh, 10 bàn, 5 nhân viên — đủ để cảm nhận hệ thống trước khi nâng cấp.' },
    { q: 'Gói Pro mở thêm những gì?', a: 'Pro mở khóa multi-branch, analytics, queue, audit log, staff, inventory và AI insights — dành cho mô hình cần kiểm soát chặt chẽ hơn.' },
    { q: 'Có thể dùng thử trước khi đăng ký trả phí không?', a: 'Có. Đăng ký Free ngay, trải nghiệm toàn bộ luồng vận hành thực tế. Nâng cấp Pro bất cứ lúc nào mà không mất dữ liệu.' },
    { q: 'Hệ thống có hỗ trợ nhiều chi nhánh không?', a: 'Gói Pro hỗ trợ không giới hạn chi nhánh. Dữ liệu mỗi chi nhánh tách biệt, báo cáo tổng hợp theo chuỗi.' },
];
</script>

<template>
    <AppTopbarLayout>
        <Head title="Aventura | Nền tảng quản lý nhà hàng" />

        <!-- ══ HERO BANNER CAROUSEL (khi có ảnh) ════════════════════════════════ -->
        <section v-if="heroBanners.length > 0" class="hero-carousel">
            <!-- Progress bar -->
            <div v-if="heroBanners.length > 1" class="progress-track">
                <div class="progress-fill" :style="{ width: progress + '%' }" />
            </div>

            <!-- Slides -->
            <div
                v-for="(banner, i) in heroBanners"
                :key="banner.id"
                class="carousel-slide"
                :class="i === heroIndex ? 'slide-active' : 'slide-inactive'"
            >
                <component
                    :is="banner.link_url ? 'a' : 'div'"
                    :href="banner.link_url ?? undefined"
                    :target="banner.link_url ? '_blank' : undefined"
                    :rel="banner.link_url ? 'noopener noreferrer' : undefined"
                    class="block h-full w-full"
                >
                    <img
                        :src="banner.image_url"
                        :alt="banner.title ?? 'Banner'"
                        class="h-full w-full object-cover"
                    />
                </component>

                <!-- Text overlay (chỉ khi có title) -->
                <div
                    v-if="banner.title"
                    class="slide-overlay"
                    :class="i === heroIndex ? 'overlay-visible' : ''"
                >
                    <div class="slide-overlay-bg" />
                    <div class="slide-text">
                        <p class="slide-eyebrow">Aventura</p>
                        <h2 class="slide-title">{{ banner.title }}</h2>
                        <p v-if="banner.subtitle" class="slide-subtitle">{{ banner.subtitle }}</p>
                        <a v-if="banner.link_url" :href="banner.link_url" class="slide-cta">
                            Khám phá ngay →
                        </a>
                    </div>
                </div>
            </div>

            <!-- Bottom fade -->
            <div class="carousel-bottom-fade pointer-events-none" />

            <!-- Nav arrows -->
            <template v-if="heroBanners.length > 1">
                <button @click.prevent="heroPrev" class="carousel-btn carousel-btn-l" aria-label="Previous">
                    <ChevronLeft class="h-5 w-5" />
                </button>
                <button @click.prevent="heroNext" class="carousel-btn carousel-btn-r" aria-label="Next">
                    <ChevronRight class="h-5 w-5" />
                </button>

                <!-- Dot indicators -->
                <div class="carousel-dots">
                    <button
                        v-for="(_, i) in heroBanners" :key="i"
                        @click="goToSlide(i)"
                        class="dot-btn"
                        :class="i === heroIndex ? 'dot-active' : 'dot-inactive'"
                        :aria-label="`Slide ${i + 1}`"
                    />
                </div>
            </template>
        </section>

        <!-- ══ HERO DEFAULT (khi không có ảnh) ═══════════════════════════════════ -->
        <section v-else class="hero-default relative overflow-hidden px-4 pt-20 pb-24 lg:px-8 lg:pt-28 lg:pb-32">
            <!-- Animated background orbs -->
            <div class="orb orb-1" />
            <div class="orb orb-2" />
            <div class="orb orb-3" />
            <div class="hero-grid-overlay" />

            <div class="relative z-10 mx-auto grid max-w-7xl gap-12 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
                <!-- Left: Text -->
                <div>
                    <div class="hero-badge animate-fade-in-up" style="animation-delay:0.05s">
                        <span class="badge-dot" />
                        Nền tảng SaaS quản lý nhà hàng
                    </div>
                    <h1 class="hero-h1 animate-fade-in-up" style="animation-delay:0.15s">
                        Quản lý quán ăn<br>
                        <span class="hero-gradient-text">theo nhịp vận hành</span><br>
                        thật sự.
                    </h1>
                    <p class="hero-desc animate-fade-in-up" style="animation-delay:0.28s">
                        Aventura gom order, bếp, kho, nhân sự, audit và AI vào một nền tảng.
                        Giảm lệ thuộc thủ công, tăng kiểm soát và cho chủ quán nhìn thấy dữ liệu rõ hơn.
                    </p>
                    <div class="mt-9 flex flex-wrap gap-3 animate-fade-in-up" style="animation-delay:0.4s">
                        <Button v-if="canRegister" as-child size="lg" class="cta-primary">
                            <Link :href="register()">Tạo tài khoản miễn phí</Link>
                        </Button>
                        <Button as-child variant="outline" size="lg" class="cta-outline">
                            <a href="#pricing">Xem gói dịch vụ</a>
                        </Button>
                    </div>
                    <div class="mt-8 flex flex-wrap gap-5 animate-fade-in-up" style="animation-delay:0.52s">
                        <span class="hero-check"><Check class="size-4 text-primary" /> Free: 1 chi nhánh, 10 bàn</span>
                        <span class="hero-check"><Check class="size-4 text-primary" /> Pro: {{ formatPrice(proPlan?.price ?? 499000) }}/tháng</span>
                        <span class="hero-check"><Check class="size-4 text-primary" /> AI + Audit + Queue</span>
                    </div>
                </div>

                <!-- Right: Demo card -->
                <div class="animate-fade-in-right" style="animation-delay:0.3s">
                    <div class="demo-card">
                        <div class="demo-card-header">
                            <div>
                                <p class="text-xs text-muted-foreground">Live demo</p>
                                <p class="text-base font-semibold">{{ demoState.title }}</p>
                            </div>
                            <Badge variant="secondary" class="text-xs">Interactive</Badge>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <button
                                v-for="tab in demoTabs" :key="tab.key"
                                @click="activeDemo = tab.key"
                                class="demo-tab"
                                :class="activeDemo === tab.key ? 'demo-tab-active' : 'demo-tab-inactive'"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                        <div class="demo-panel">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="demo-list">
                                    <p v-for="item in demoState.left" :key="item" class="demo-list-item">
                                        <ChevronRight class="size-3.5 text-primary shrink-0" />
                                        {{ item }}
                                    </p>
                                </div>
                                <div class="demo-list">
                                    <p v-for="item in demoState.right" :key="item.label" class="flex items-center justify-between gap-2 text-sm">
                                        <span class="text-muted-foreground">{{ item.label }}</span>
                                        <Badge variant="outline" class="text-xs shrink-0">{{ item.status }}</Badge>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ FEATURES ═══════════════════════════════════════════════════════════ -->
        <section id="features" class="section-alt px-4 py-20 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="section-header reveal">
                    <p class="section-eyebrow">Tính năng</p>
                    <h2 class="section-title">Mọi thứ một nhà hàng cần</h2>
                    <p class="section-desc">Từ order đến báo cáo — tích hợp sẵn, không cần ghép nhiều phần mềm.</p>
                </div>
                <div class="mt-12 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="(item, idx) in featureMap" :key="item.title"
                        class="feature-card reveal"
                        :style="`transition-delay:${(idx % 3) * 80}ms`"
                    >
                        <div class="feature-icon-wrap">
                            <component :is="item.icon" class="size-5 text-primary" />
                        </div>
                        <h3 class="feature-title">{{ item.title }}</h3>
                        <p class="feature-desc">{{ item.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ PROMO BANNER ════════════════════════════════════════════════════════ -->
        <section v-if="promoBanners.length > 0" class="px-4 py-8 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-4">
                <a
                    v-for="banner in promoBanners" :key="banner.id"
                    :href="banner.link_url ?? undefined"
                    :target="banner.link_url ? '_blank' : undefined"
                    :rel="banner.link_url ? 'noopener noreferrer' : undefined"
                    class="promo-banner reveal"
                    :class="banner.link_url ? 'cursor-pointer' : 'cursor-default'"
                >
                    <img :src="banner.image_url" :alt="banner.title ?? ''" class="h-auto w-full object-cover" />
                </a>
            </div>
        </section>

        <!-- ══ SOCIAL PROOF STRIP ═════════════════════════════════════════════════ -->
        <section class="border-y border-border/50 bg-muted/20 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="grid gap-6 sm:grid-cols-3 reveal">
                    <div class="proof-card">
                        <Star class="size-5 text-primary mb-3" />
                        <h3 class="font-semibold text-foreground">Quản lý chuỗi</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Multi-branch + tenant isolation cho mô hình nhiều chi nhánh.</p>
                    </div>
                    <div class="proof-card">
                        <LineChart class="size-5 text-primary mb-3" />
                        <h3 class="font-semibold text-foreground">Minh bạch vận hành</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Audit log + analytics giúp nhìn rõ thay đổi và hiệu suất thực.</p>
                    </div>
                    <div class="proof-card">
                        <Rocket class="size-5 text-primary mb-3" />
                        <h3 class="font-semibold text-foreground">Onboarding nhanh</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Đăng ký xong vào được luồng demo ngay, không cần hướng dẫn dài.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ PRICING ════════════════════════════════════════════════════════════ -->
        <section id="pricing" class="px-4 py-20 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="section-header reveal">
                    <p class="section-eyebrow">Bảng giá</p>
                    <h2 class="section-title">Bảng giá dịch vụ</h2>
                    <p class="section-desc">Minh bạch, không phí ẩn. Chọn gói phù hợp và nâng cấp bất cứ lúc nào.</p>
                </div>
                <div class="mt-12 grid gap-6 lg:grid-cols-2">
                    <!-- Free -->
                    <div class="pricing-card reveal" style="transition-delay:0ms">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">{{ freePlan?.name ?? 'Miễn phí' }}</p>
                                <h3 class="mt-1 text-3xl font-bold">{{ formatPrice(freePlan?.price ?? 0) }}</h3>
                                <p class="text-sm text-muted-foreground">/tháng</p>
                            </div>
                            <Badge variant="secondary">Mặc định</Badge>
                        </div>
                        <ul class="mt-8 space-y-3">
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> {{ freePlan ? (freePlan.max_branches === 1 ? '1 chi nhánh' : `${freePlan.max_branches} chi nhánh`) : '1 chi nhánh' }}</li>
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> {{ freePlan ? (freePlan.max_tables > 0 ? `${freePlan.max_tables} bàn` : 'Bàn không giới hạn') : '10 bàn' }}</li>
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> {{ freePlan ? (freePlan.max_users > 0 ? `${freePlan.max_users} nhân viên` : 'Nhân viên không giới hạn') : '5 nhân viên' }}</li>
                            <li class="pricing-feature pricing-feature-off"><X class="size-4 shrink-0" /> AI insights</li>
                            <li class="pricing-feature pricing-feature-off"><X class="size-4 shrink-0" /> Multi-branch</li>
                        </ul>
                        <div class="mt-8">
                            <Button v-if="canRegister" as-child variant="outline" class="w-full">
                                <Link :href="register()">Bắt đầu miễn phí</Link>
                            </Button>
                        </div>
                    </div>
                    <!-- Pro -->
                    <div class="pricing-card pricing-card-pro reveal" style="transition-delay:100ms">
                        <div class="pricing-pro-glow" />
                        <div class="relative flex items-start justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-widest text-primary">{{ proPlan?.name ?? 'Pro' }}</p>
                                <h3 class="mt-1 text-3xl font-bold">{{ formatPrice(proPlan?.price ?? 499000) }}</h3>
                                <p class="text-sm text-muted-foreground">/tháng</p>
                            </div>
                            <Badge class="bg-primary text-primary-foreground">Khuyến nghị</Badge>
                        </div>
                        <ul class="relative mt-8 space-y-3">
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> Chi nhánh không giới hạn</li>
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> Bàn không giới hạn</li>
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> Nhân viên không giới hạn</li>
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> AI insights + Dự báo</li>
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> Analytics + Audit log</li>
                        </ul>
                        <div class="relative mt-8">
                            <Button v-if="canRegister" as-child class="w-full">
                                <Link :href="register()">Chọn gói {{ proPlan?.name ?? 'Pro' }}</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ NEWS ═══════════════════════════════════════════════════════════════ -->
        <section v-if="latestNews?.length" class="section-alt px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="mb-10 flex items-end justify-between reveal">
                    <div>
                        <p class="section-eyebrow">Blog</p>
                        <h2 class="section-title !text-2xl">Mới nhất từ Aventura</h2>
                    </div>
                    <Link href="/tin-tuc" class="hidden text-sm font-medium text-primary hover:underline underline-offset-2 sm:block">
                        Xem tất cả →
                    </Link>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="(news, idx) in latestNews" :key="news.id" class="reveal" :style="`transition-delay:${idx * 70}ms`">
                        <NewsCard v-bind="news" />
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ FAQ ════════════════════════════════════════════════════════════════ -->
        <section class="px-4 py-20 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="section-header reveal">
                    <p class="section-eyebrow">FAQ</p>
                    <h2 class="section-title">Câu hỏi thường gặp</h2>
                </div>
                <div class="mt-12 grid gap-4 lg:grid-cols-2">
                    <div
                        v-for="(item, idx) in faq" :key="item.q"
                        class="faq-card reveal"
                        :style="`transition-delay:${(idx % 2) * 80}ms`"
                    >
                        <h3 class="font-semibold text-foreground">{{ item.q }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ item.a }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ CTA BOTTOM ══════════════════════════════════════════════════════════ -->
        <section class="cta-section px-4 py-24 lg:px-8">
            <div class="cta-glow" />
            <div class="relative mx-auto flex max-w-2xl flex-col items-center gap-6 text-center reveal">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Bắt đầu ngay hôm nay</h2>
                <p class="text-base text-muted-foreground">
                    Aventura đủ gọn để thử, đủ sâu để chạy thật.<br>
                    Gói Free không giới hạn thời gian — nâng Pro khi sẵn sàng.
                </p>
                <div class="flex flex-wrap justify-center gap-3">
                    <Button v-if="canRegister" as-child size="lg" class="cta-primary">
                        <Link :href="register()">Tạo tài khoản miễn phí</Link>
                    </Button>
                    <Button as-child variant="outline" size="lg">
                        <a href="#features">Khám phá tính năng</a>
                    </Button>
                </div>
            </div>
        </section>

        <ChatbotWidget source="landing" />
    </AppTopbarLayout>
</template>

<style scoped>
/* ── Keyframes ─────────────────────────────────────────────────────────────── */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(28px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeInRight {
    from { opacity: 0; transform: translateX(32px); }
    to   { opacity: 1; transform: translateX(0); }
}
@keyframes floatA {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50%       { transform: translate(30px, -40px) scale(1.05); }
}
@keyframes floatB {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50%       { transform: translate(-25px, 35px) scale(1.03); }
}
@keyframes floatC {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50%       { transform: translate(20px, 20px) scale(0.97); }
}
@keyframes kenBurns {
    from { transform: scale(1); }
    to   { transform: scale(1.07); }
}
@keyframes gradientShift {
    0%, 100% { background-position: 0% 50%; }
    50%       { background-position: 100% 50%; }
}
@keyframes shimmerText {
    0%   { background-position: -200% center; }
    100% { background-position: 200% center; }
}

/* ── Scroll reveal ─────────────────────────────────────────────────────────── */
.reveal {
    opacity: 0;
    transform: translateY(28px);
    transition: opacity 0.65s cubic-bezier(.22,.68,0,1.2), transform 0.65s cubic-bezier(.22,.68,0,1.2);
}
.reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
}

/* ── Mount animations ──────────────────────────────────────────────────────── */
.animate-fade-in-up {
    animation: fadeInUp 0.7s cubic-bezier(.22,.68,0,1.2) both;
}
.animate-fade-in-right {
    animation: fadeInRight 0.7s cubic-bezier(.22,.68,0,1.2) both;
}

/* ── Carousel ──────────────────────────────────────────────────────────────── */
.hero-carousel {
    position: relative;
    width: 100%;
    overflow: hidden;
    height: clamp(380px, 56vw, 600px);
    background: #080B12;
}
.carousel-slide {
    position: absolute;
    inset: 0;
    transition: opacity 1s cubic-bezier(.4,0,.2,1), transform 1s cubic-bezier(.4,0,.2,1);
}
.slide-active  { opacity: 1; z-index: 10; }
.slide-inactive { opacity: 0; z-index: 0; }

/* Progress bar */
.progress-track {
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: rgba(255,255,255,0.1);
    z-index: 40;
}
.progress-fill {
    height: 100%;
    background: rgba(255,255,255,0.7);
    transition: width 50ms linear;
}

/* Nav buttons */
.carousel-btn {
    position: absolute;
    top: 50%;
    z-index: 30;
    display: flex; align-items: center; justify-content: center;
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(0,0,0,0.35);
    color: white;
    border: 1px solid rgba(255,255,255,0.12);
    backdrop-filter: blur(8px);
    transition: background 0.2s, transform 0.2s, opacity 0.2s;
    transform: translateY(-50%);
}
.carousel-btn-l { left: 20px; }
.carousel-btn-r { right: 20px; }
.carousel-btn:hover {
    background: rgba(0,0,0,0.6);
    transform: translateY(-50%) scale(1.08);
    border-color: rgba(255,255,255,0.25);
}

/* Dots */
.carousel-dots {
    position: absolute;
    bottom: 22px;
    left: 50%;
    z-index: 30;
    display: flex;
    gap: 8px;
    transform: translateX(-50%);
}
.dot-btn { border-radius: 9999px; transition: all 0.4s cubic-bezier(.22,.68,0,1.2); }
.dot-active   { width: 28px; height: 6px; background: rgba(255,255,255,0.9); }
.dot-inactive { width: 6px;  height: 6px; background: rgba(255,255,255,0.35); }
.dot-inactive:hover { background: rgba(255,255,255,0.65); transform: scale(1.2); }

/* Bottom fade */
.carousel-bottom-fade {
    position: absolute;
    inset-x: 0; bottom: 0;
    height: 100px;
    background: linear-gradient(to top, hsl(var(--background)/0.5), transparent);
    z-index: 20;
}

/* Slide text overlay */
.slide-overlay {
    position: absolute;
    inset: 0;
    z-index: 15;
    display: flex;
    align-items: center;
}
.slide-overlay-bg {
    position: absolute;
    inset: 0;
    background: linear-gradient(to right, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.3) 50%, transparent 80%);
}
.slide-text {
    position: relative;
    padding-left: clamp(24px, 5vw, 80px);
    max-width: 600px;
}
.slide-eyebrow {
    font-size: 11px; font-weight: 700; letter-spacing: 0.15em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.5);
    margin-bottom: 10px;
    opacity: 0; transform: translateY(10px);
    transition: opacity 0.6s ease 0.1s, transform 0.6s ease 0.1s;
}
.slide-title {
    font-size: clamp(1.6rem, 3.2vw, 3rem);
    font-weight: 800;
    color: white;
    letter-spacing: -0.025em;
    line-height: 1.15;
    opacity: 0; transform: translateY(18px);
    transition: opacity 0.65s ease 0.2s, transform 0.65s ease 0.2s;
}
.slide-subtitle {
    margin-top: 10px;
    font-size: clamp(0.875rem, 1.2vw, 1rem);
    color: rgba(255,255,255,0.65);
    line-height: 1.6;
    opacity: 0; transform: translateY(14px);
    transition: opacity 0.65s ease 0.35s, transform 0.65s ease 0.35s;
}
.slide-cta {
    display: inline-flex;
    margin-top: 18px;
    padding: 10px 24px;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.28);
    border-radius: 9999px;
    color: white;
    font-size: 14px; font-weight: 600;
    text-decoration: none;
    backdrop-filter: blur(8px);
    opacity: 0; transform: translateY(10px);
    transition: opacity 0.6s ease 0.5s, transform 0.6s ease 0.5s, background 0.2s;
}
.slide-cta:hover { background: rgba(255,255,255,0.22); }
.overlay-visible .slide-eyebrow,
.overlay-visible .slide-title,
.overlay-visible .slide-subtitle,
.overlay-visible .slide-cta { opacity: 1; transform: translateY(0); }

/* ── Hero default ──────────────────────────────────────────────────────────── */
.hero-default { background: radial-gradient(ellipse at 60% 0%, hsl(var(--primary)/0.06) 0%, transparent 60%), hsl(var(--background)); }
.hero-grid-overlay {
    position: absolute; inset: 0; z-index: 0; pointer-events: none;
    background-image: linear-gradient(hsl(var(--border)/0.35) 1px, transparent 1px),
                      linear-gradient(90deg, hsl(var(--border)/0.35) 1px, transparent 1px);
    background-size: 48px 48px;
    mask-image: radial-gradient(ellipse 80% 80% at 50% 0%, black 20%, transparent 100%);
}
.orb {
    position: absolute; border-radius: 50%; filter: blur(80px); pointer-events: none;
}
.orb-1 {
    width: 600px; height: 600px; top: -200px; right: -100px;
    background: hsl(var(--primary)/0.12);
    animation: floatA 14s ease-in-out infinite;
}
.orb-2 {
    width: 400px; height: 400px; bottom: -100px; left: -80px;
    background: hsl(var(--primary)/0.07);
    animation: floatB 18s ease-in-out infinite;
}
.orb-3 {
    width: 300px; height: 300px; top: 40%; left: 45%;
    background: hsl(var(--primary)/0.05);
    animation: floatC 22s ease-in-out infinite;
}

/* ── Hero text ─────────────────────────────────────────────────────────────── */
.hero-badge {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 6px 14px; border-radius: 9999px; margin-bottom: 20px;
    font-size: 13px; font-weight: 500;
    border: 1px solid hsl(var(--border));
    background: hsl(var(--muted)/0.5);
    color: hsl(var(--muted-foreground));
    backdrop-filter: blur(4px);
}
.badge-dot {
    display: inline-block; width: 6px; height: 6px; border-radius: 50%;
    background: hsl(var(--primary));
    box-shadow: 0 0 6px hsl(var(--primary)/0.6);
}
.hero-h1 {
    font-size: clamp(2.4rem, 5vw, 3.75rem);
    font-weight: 800; line-height: 1.1; letter-spacing: -0.03em;
    color: hsl(var(--foreground));
}
.hero-gradient-text {
    background: linear-gradient(135deg, hsl(var(--primary)) 0%, hsl(var(--primary)/0.7) 100%);
    background-size: 200% auto;
    -webkit-background-clip: text; background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: shimmerText 4s linear infinite;
}
.hero-desc {
    margin-top: 20px; max-width: 520px;
    font-size: 1.05rem; line-height: 1.75;
    color: hsl(var(--muted-foreground));
}
.hero-check {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 13px; color: hsl(var(--muted-foreground));
}
.cta-primary { transition: transform 0.2s, box-shadow 0.2s; }
.cta-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px hsl(var(--primary)/0.25); }
.cta-outline { transition: transform 0.2s; }
.cta-outline:hover { transform: translateY(-2px); }

/* ── Demo card ─────────────────────────────────────────────────────────────── */
.demo-card {
    border: 1px solid hsl(var(--border));
    background: hsl(var(--card));
    border-radius: 16px; padding: 20px;
    box-shadow: 0 4px 40px rgba(0,0,0,0.08);
    backdrop-filter: blur(8px);
}
.demo-card-header {
    display: flex; align-items: center; justify-content: space-between;
    padding-bottom: 16px; border-bottom: 1px solid hsl(var(--border));
}
.demo-tab {
    padding: 5px 14px; border-radius: 6px; font-size: 13px; font-weight: 500;
    transition: all 0.2s;
}
.demo-tab-active  { background: hsl(var(--primary)); color: hsl(var(--primary-foreground)); }
.demo-tab-inactive { background: transparent; color: hsl(var(--muted-foreground)); border: 1px solid hsl(var(--border)); }
.demo-tab-inactive:hover { background: hsl(var(--muted)/0.5); }
.demo-panel { margin-top: 14px; border: 1px solid hsl(var(--border)); border-radius: 8px; padding: 14px; }
.demo-list { display: flex; flex-direction: column; gap: 8px; }
.demo-list-item { display: flex; align-items: center; gap: 6px; font-size: 13px; }

/* ── Section shared ────────────────────────────────────────────────────────── */
.section-alt { background: hsl(var(--muted)/0.25); border-top: 1px solid hsl(var(--border)/0.5); border-bottom: 1px solid hsl(var(--border)/0.5); }
.section-header { text-align: center; max-width: 560px; margin: 0 auto; }
.section-eyebrow { font-size: 12px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: hsl(var(--primary)); margin-bottom: 8px; }
.section-title { font-size: clamp(1.7rem, 3vw, 2.25rem); font-weight: 700; letter-spacing: -0.02em; color: hsl(var(--foreground)); }
.section-desc { margin-top: 12px; font-size: 15px; line-height: 1.7; color: hsl(var(--muted-foreground)); }

/* ── Feature cards ─────────────────────────────────────────────────────────── */
.feature-card {
    padding: 24px; border-radius: 14px;
    border: 1px solid hsl(var(--border)/0.7);
    background: hsl(var(--card));
    transition: border-color 0.25s, box-shadow 0.25s, transform 0.25s,
                opacity 0.65s cubic-bezier(.22,.68,0,1.2), translate 0.65s cubic-bezier(.22,.68,0,1.2);
}
.feature-card:hover { border-color: hsl(var(--primary)/0.4); box-shadow: 0 4px 24px hsl(var(--primary)/0.08); transform: translateY(-3px); }
.feature-icon-wrap {
    display: flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: 10px;
    background: hsl(var(--primary)/0.1); margin-bottom: 14px;
    transition: background 0.25s;
}
.feature-card:hover .feature-icon-wrap { background: hsl(var(--primary)/0.18); }
.feature-title { font-size: 15px; font-weight: 600; color: hsl(var(--foreground)); }
.feature-desc  { margin-top: 6px; font-size: 13px; line-height: 1.65; color: hsl(var(--muted-foreground)); }

/* ── Promo banner ──────────────────────────────────────────────────────────── */
.promo-banner {
    display: block; border-radius: 16px; overflow: hidden;
    transition: opacity 0.25s, transform 0.25s,
                opacity 0.65s cubic-bezier(.22,.68,0,1.2) !important;
}
.promo-banner:hover { opacity: 0.94; transform: scale(1.005); }

/* ── Proof cards ───────────────────────────────────────────────────────────── */
.proof-card { padding: 20px; border-radius: 12px; background: hsl(var(--card)); border: 1px solid hsl(var(--border)/0.6); }

/* ── Pricing cards ─────────────────────────────────────────────────────────── */
.pricing-card {
    position: relative; padding: 32px; border-radius: 16px;
    border: 1px solid hsl(var(--border));
    background: hsl(var(--card));
    overflow: hidden;
    transition: opacity 0.65s cubic-bezier(.22,.68,0,1.2), transform 0.65s cubic-bezier(.22,.68,0,1.2);
}
.pricing-card-pro { border-color: hsl(var(--primary)/0.5); box-shadow: 0 0 0 1px hsl(var(--primary)/0.15), 0 8px 40px hsl(var(--primary)/0.1); }
.pricing-pro-glow {
    position: absolute; top: 0; right: 0; width: 260px; height: 260px; border-radius: 50%;
    background: radial-gradient(circle, hsl(var(--primary)/0.12) 0%, transparent 70%);
    pointer-events: none;
}
.pricing-feature { display: flex; align-items: center; gap: 10px; font-size: 14px; color: hsl(var(--foreground)); }
.pricing-feature-off { color: hsl(var(--muted-foreground)); text-decoration: line-through; opacity: 0.6; }

/* ── FAQ cards ─────────────────────────────────────────────────────────────── */
.faq-card {
    padding: 24px; border-radius: 12px;
    border: 1px solid hsl(var(--border)/0.7);
    background: hsl(var(--card));
    transition: border-color 0.2s, opacity 0.65s cubic-bezier(.22,.68,0,1.2), transform 0.65s cubic-bezier(.22,.68,0,1.2);
}
.faq-card:hover { border-color: hsl(var(--primary)/0.3); }

/* ── CTA section ───────────────────────────────────────────────────────────── */
.cta-section { position: relative; overflow: hidden; background: hsl(var(--muted)/0.2); border-top: 1px solid hsl(var(--border)/0.5); }
.cta-glow {
    position: absolute; top: 50%; left: 50%; width: 500px; height: 300px;
    transform: translate(-50%, -50%);
    background: radial-gradient(ellipse, hsl(var(--primary)/0.1) 0%, transparent 70%);
    pointer-events: none;
}
</style>
