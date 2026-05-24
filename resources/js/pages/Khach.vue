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
    price === 0 ? '0Ä‘' : price.toLocaleString('vi-VN') + 'Ä‘';

// â”€â”€ Hero carousel â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

// â”€â”€ Scroll reveal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

// â”€â”€ Demo tabs â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const demoTabs = [
    { key: 'pos', label: 'POS' },
    { key: 'kds', label: 'Báº¿p' },
    { key: 'report', label: 'BÃ¡o cÃ¡o' },
] as const;
const activeDemo = ref<(typeof demoTabs)[number]['key']>('pos');
const demoState = computed(() => {
    if (activeDemo.value === 'kds') return {
        title: 'Kitchen board',
        left: ['2 order chá»', '1 order Ä‘ang lÃ m', '0 trá»… SLA'],
        right: [{ label: 'BÃºn bÃ²', status: 'Äang lÃ m' }, { label: 'CÆ¡m gÃ ', status: 'Sáºµn sÃ ng' }, { label: 'TrÃ  Ä‘Ã o', status: 'Chá» in bill' }],
    };
    if (activeDemo.value === 'report') return {
        title: 'Daily snapshot',
        left: ['Doanh thu: 12,8M', 'Tá»· lá»‡ hoÃ n thÃ nh: 98%', 'MÃ³n bÃ¡n cháº¡y: Phá»Ÿ bÃ²'],
        right: [{ label: 'Giá» cao Ä‘iá»ƒm', status: '11:30â€“13:30' }, { label: 'Cáº£nh bÃ¡o kho', status: '2 nguyÃªn liá»‡u' }, { label: 'Audit', status: '1 thay Ä‘á»•i giÃ¡' }],
    };
    return {
        title: 'POS checkout',
        left: ['BÃ n 12', '3 mÃ³n', 'Tá»•ng: 168.000Ä‘'],
        right: [{ label: 'Phá»Ÿ bÃ² tÃ¡i', status: 'x2' }, { label: 'TrÃ  chanh', status: 'x1' }, { label: 'Thanh toÃ¡n', status: 'HoÃ n táº¥t' }],
    };
});

// â”€â”€ Data â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const featureMap = [
    { icon: QrCode, title: 'QR Order', description: 'KhÃ¡ch quÃ©t QR táº¡i bÃ n â€” táº¡o order nhanh, giáº£m sai sÃ³t, giáº£m táº£i nhÃ¢n viÃªn.' },
    { icon: Monitor, title: 'Kitchen Display', description: 'ÄÆ¡n lÃªn báº¿p theo realtime, tráº¡ng thÃ¡i rÃµ rÃ ng, háº¡n cháº¿ nháº§m láº«n giá» cao Ä‘iá»ƒm.' },
    { icon: Package, title: 'Inventory', description: 'Trá»« kho theo Ä‘á»‹nh lÆ°á»£ng, theo dÃµi nháº­p xuáº¥t, cáº£nh bÃ¡o thiáº¿u nguyÃªn liá»‡u.' },
    { icon: Users, title: 'NhÃ¢n sá»±', description: 'Quáº£n lÃ½ nhÃ¢n sá»±, ca lÃ m, vai trÃ², cháº¥m cÃ´ng vÃ  há»— trá»£ tÃ­nh lÆ°Æ¡ng.' },
    { icon: BarChart3, title: 'Analytics', description: 'Theo dÃµi doanh thu, hiá»‡u suáº¥t, xu hÆ°á»›ng mÃ³n bÃ¡n cháº¡y, bÃ¡o cÃ¡o váº­n hÃ nh.' },
    { icon: Building2, title: 'Multi-branch', description: 'Má»™t tÃ i khoáº£n quáº£n lÃ½ nhiá»u chi nhÃ¡nh, dá»¯ liá»‡u tÃ¡ch biá»‡t theo tenant.' },
    { icon: ShieldCheck, title: 'Audit Log', description: 'Ghi váº¿t thao tÃ¡c nháº¡y cáº£m Ä‘á»ƒ tra soÃ¡t, giáº£m phá»¥ thuá»™c vÃ o tÃ­nh trung thá»±c.' },
    { icon: Bot, title: 'AI Insights', description: 'Tá»•ng há»£p tÃ­n hiá»‡u dá»¯ liá»‡u Ä‘á»ƒ gá»£i Ã½ cáº£nh bÃ¡o, dá»± bÃ¡o vÃ  phÃ¡t hiá»‡n báº¥t thÆ°á»ng.' },
    { icon: Clock3, title: 'Queue', description: 'TÃ¡c vá»¥ náº·ng cháº¡y ná»n, giá»¯ UI bÃ¡n hÃ ng pháº£n há»“i nhanh trong má»i Ä‘iá»u kiá»‡n.' },
];

const faq = [
    { q: 'GÃ³i Free cÃ³ phÃ¹ há»£p cho quÃ¡n nhá» khÃ´ng?', a: 'CÃ³. Free phÃ¹ há»£p Ä‘á»ƒ thá»­ váº­n hÃ nh thá»±c táº¿ vá»›i 1 chi nhÃ¡nh, 10 bÃ n, 5 nhÃ¢n viÃªn â€” Ä‘á»§ Ä‘á»ƒ cáº£m nháº­n há»‡ thá»‘ng trÆ°á»›c khi nÃ¢ng cáº¥p.' },
    { q: 'GÃ³i Pro má»Ÿ thÃªm nhá»¯ng gÃ¬?', a: 'Pro má»Ÿ khÃ³a multi-branch, analytics, queue, audit log, staff, inventory vÃ  AI insights â€” dÃ nh cho mÃ´ hÃ¬nh cáº§n kiá»ƒm soÃ¡t cháº·t cháº½ hÆ¡n.' },
    { q: 'CÃ³ thá»ƒ dÃ¹ng thá»­ trÆ°á»›c khi Ä‘Äƒng kÃ½ tráº£ phÃ­ khÃ´ng?', a: 'CÃ³. ÄÄƒng kÃ½ Free ngay, tráº£i nghiá»‡m toÃ n bá»™ luá»“ng váº­n hÃ nh thá»±c táº¿. NÃ¢ng cáº¥p Pro báº¥t cá»© lÃºc nÃ o mÃ  khÃ´ng máº¥t dá»¯ liá»‡u.' },
    { q: 'Há»‡ thá»‘ng cÃ³ há»— trá»£ nhiá»u chi nhÃ¡nh khÃ´ng?', a: 'GÃ³i Pro há»— trá»£ khÃ´ng giá»›i háº¡n chi nhÃ¡nh. Dá»¯ liá»‡u má»—i chi nhÃ¡nh tÃ¡ch biá»‡t, bÃ¡o cÃ¡o tá»•ng há»£p theo chuá»—i.' },
];
</script>

<template>
    <AppTopbarLayout>
        <Head title="Aventura | Ná»n táº£ng quáº£n lÃ½ nhÃ  hÃ ng" />

        <!-- â•â• HERO BANNER CAROUSEL (khi cÃ³ áº£nh) â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
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

                <!-- Text overlay (chá»‰ khi cÃ³ title) -->
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
                            KhÃ¡m phÃ¡ ngay â†’
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

        <!-- â•â• HERO DEFAULT (khi khÃ´ng cÃ³ áº£nh) â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
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
                        Ná»n táº£ng SaaS quáº£n lÃ½ nhÃ  hÃ ng
                    </div>
                    <h1 class="hero-h1 animate-fade-in-up" style="animation-delay:0.15s">
                        Quáº£n lÃ½ quÃ¡n Äƒn<br>
                        <span class="hero-gradient-text">theo nhá»‹p váº­n hÃ nh</span><br>
                        tháº­t sá»±.
                    </h1>
                    <p class="hero-desc animate-fade-in-up" style="animation-delay:0.28s">
                        Aventura gom order, báº¿p, kho, nhÃ¢n sá»±, audit vÃ  AI vÃ o má»™t ná»n táº£ng.
                        Giáº£m lá»‡ thuá»™c thá»§ cÃ´ng, tÄƒng kiá»ƒm soÃ¡t vÃ  cho chá»§ quÃ¡n nhÃ¬n tháº¥y dá»¯ liá»‡u rÃµ hÆ¡n.
                    </p>
                    <div class="mt-9 flex flex-wrap gap-3 animate-fade-in-up" style="animation-delay:0.4s">
                        <Button v-if="canRegister" as-child size="lg" class="cta-primary">
                            <Link :href="register()">Táº¡o tÃ i khoáº£n miá»…n phÃ­</Link>
                        </Button>
                        <Button as-child variant="outline" size="lg" class="cta-outline">
                            <a href="#pricing">Xem gÃ³i dá»‹ch vá»¥</a>
                        </Button>
                    </div>
                    <div class="mt-8 flex flex-wrap gap-5 animate-fade-in-up" style="animation-delay:0.52s">
                        <span class="hero-check"><Check class="size-4 text-primary" /> Free: 1 chi nhÃ¡nh, 10 bÃ n</span>
                        <span class="hero-check"><Check class="size-4 text-primary" /> Pro: {{ formatPrice(proPlan?.price ?? 499000) }}/thÃ¡ng</span>
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

        <!-- â•â• FEATURES â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <section id="features" class="section-alt px-4 py-20 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="section-header reveal">
                    <p class="section-eyebrow">TÃ­nh nÄƒng</p>
                    <h2 class="section-title">Má»i thá»© má»™t nhÃ  hÃ ng cáº§n</h2>
                    <p class="section-desc">Tá»« order Ä‘áº¿n bÃ¡o cÃ¡o â€” tÃ­ch há»£p sáºµn, khÃ´ng cáº§n ghÃ©p nhiá»u pháº§n má»m.</p>
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

        <!-- â•â• PROMO BANNER â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
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

        <!-- â•â• SOCIAL PROOF STRIP â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <section class="border-y border-border/50 bg-muted/20 px-4 py-10 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="grid gap-6 sm:grid-cols-3 reveal">
                    <div class="proof-card">
                        <Star class="size-5 text-primary mb-3" />
                        <h3 class="font-semibold text-foreground">Quáº£n lÃ½ chuá»—i</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Multi-branch + tenant isolation cho mÃ´ hÃ¬nh nhiá»u chi nhÃ¡nh.</p>
                    </div>
                    <div class="proof-card">
                        <LineChart class="size-5 text-primary mb-3" />
                        <h3 class="font-semibold text-foreground">Minh báº¡ch váº­n hÃ nh</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Audit log + analytics giÃºp nhÃ¬n rÃµ thay Ä‘á»•i vÃ  hiá»‡u suáº¥t thá»±c.</p>
                    </div>
                    <div class="proof-card">
                        <Rocket class="size-5 text-primary mb-3" />
                        <h3 class="font-semibold text-foreground">Onboarding nhanh</h3>
                        <p class="mt-1 text-sm text-muted-foreground">ÄÄƒng kÃ½ xong vÃ o Ä‘Æ°á»£c luá»“ng demo ngay, khÃ´ng cáº§n hÆ°á»›ng dáº«n dÃ i.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- â•â• PRICING â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <section id="pricing" class="px-4 py-20 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="section-header reveal">
                    <p class="section-eyebrow">Báº£ng giÃ¡</p>
                    <h2 class="section-title">Báº£ng giÃ¡ dá»‹ch vá»¥</h2>
                    <p class="section-desc">Minh báº¡ch, khÃ´ng phÃ­ áº©n. Chá»n gÃ³i phÃ¹ há»£p vÃ  nÃ¢ng cáº¥p báº¥t cá»© lÃºc nÃ o.</p>
                </div>
                <div class="mt-12 grid gap-6 lg:grid-cols-2">
                    <!-- Free -->
                    <div class="pricing-card reveal" style="transition-delay:0ms">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">{{ freePlan?.name ?? 'Miá»…n phÃ­' }}</p>
                                <h3 class="mt-1 text-3xl font-bold">{{ formatPrice(freePlan?.price ?? 0) }}</h3>
                                <p class="text-sm text-muted-foreground">/thÃ¡ng</p>
                            </div>
                            <Badge variant="secondary">Máº·c Ä‘á»‹nh</Badge>
                        </div>
                        <ul class="mt-8 space-y-3">
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> {{ freePlan ? (freePlan.max_branches === 1 ? '1 chi nhÃ¡nh' : `${freePlan.max_branches} chi nhÃ¡nh`) : '1 chi nhÃ¡nh' }}</li>
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> {{ freePlan ? (freePlan.max_tables > 0 ? `${freePlan.max_tables} bÃ n` : 'BÃ n khÃ´ng giá»›i háº¡n') : '10 bÃ n' }}</li>
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> {{ freePlan ? (freePlan.max_users > 0 ? `${freePlan.max_users} nhÃ¢n viÃªn` : 'NhÃ¢n viÃªn khÃ´ng giá»›i háº¡n') : '5 nhÃ¢n viÃªn' }}</li>
                            <li class="pricing-feature pricing-feature-off"><X class="size-4 shrink-0" /> AI insights</li>
                            <li class="pricing-feature pricing-feature-off"><X class="size-4 shrink-0" /> Multi-branch</li>
                        </ul>
                        <div class="mt-8">
                            <Button v-if="canRegister" as-child variant="outline" class="w-full">
                                <Link :href="register()">Báº¯t Ä‘áº§u miá»…n phÃ­</Link>
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
                                <p class="text-sm text-muted-foreground">/thÃ¡ng</p>
                            </div>
                            <Badge class="bg-primary text-primary-foreground">Khuyáº¿n nghá»‹</Badge>
                        </div>
                        <ul class="relative mt-8 space-y-3">
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> Chi nhÃ¡nh khÃ´ng giá»›i háº¡n</li>
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> BÃ n khÃ´ng giá»›i háº¡n</li>
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> NhÃ¢n viÃªn khÃ´ng giá»›i háº¡n</li>
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> AI insights + Dá»± bÃ¡o</li>
                            <li class="pricing-feature"><Check class="size-4 text-primary shrink-0" /> Analytics + Audit log</li>
                        </ul>
                        <div class="relative mt-8">
                            <Button v-if="canRegister" as-child class="w-full">
                                <Link :href="register()">Chá»n gÃ³i {{ proPlan?.name ?? 'Pro' }}</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- â•â• NEWS â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <section v-if="latestNews?.length" class="section-alt px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="mb-10 flex items-end justify-between reveal">
                    <div>
                        <p class="section-eyebrow">Blog</p>
                        <h2 class="section-title !text-2xl">Má»›i nháº¥t tá»« Aventura</h2>
                    </div>
                    <Link href="/tin-tuc" class="hidden text-sm font-medium text-primary hover:underline underline-offset-2 sm:block">
                        Xem táº¥t cáº£ â†’
                    </Link>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="(news, idx) in latestNews" :key="news.id" class="reveal" :style="`transition-delay:${idx * 70}ms`">
                        <NewsCard v-bind="news" />
                    </div>
                </div>
            </div>
        </section>

        <!-- â•â• FAQ â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <section class="px-4 py-20 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="section-header reveal">
                    <p class="section-eyebrow">FAQ</p>
                    <h2 class="section-title">CÃ¢u há»i thÆ°á»ng gáº·p</h2>
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

        <!-- â•â• CTA BOTTOM â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <section class="cta-section px-4 py-24 lg:px-8">
            <div class="cta-glow" />
            <div class="relative mx-auto flex max-w-2xl flex-col items-center gap-6 text-center reveal">
                <h2 class="text-3xl font-bold tracking-tight lg:text-4xl">Báº¯t Ä‘áº§u ngay hÃ´m nay</h2>
                <p class="text-base text-muted-foreground">
                    Aventura Ä‘á»§ gá»n Ä‘á»ƒ thá»­, Ä‘á»§ sÃ¢u Ä‘á»ƒ cháº¡y tháº­t.<br>
                    GÃ³i Free khÃ´ng giá»›i háº¡n thá»i gian â€” nÃ¢ng Pro khi sáºµn sÃ ng.
                </p>
                <div class="flex flex-wrap justify-center gap-3">
                    <Button v-if="canRegister" as-child size="lg" class="cta-primary">
                        <Link :href="register()">Táº¡o tÃ i khoáº£n miá»…n phÃ­</Link>
                    </Button>
                    <Button as-child variant="outline" size="lg">
                        <a href="#features">KhÃ¡m phÃ¡ tÃ­nh nÄƒng</a>
                    </Button>
                </div>
            </div>
        </section>

        <ChatbotWidget source="landing" />
    </AppTopbarLayout>
</template>

<style scoped>
/* â”€â”€ Keyframes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

/* â”€â”€ Scroll reveal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.reveal {
    opacity: 0;
    transform: translateY(28px);
    transition: opacity 0.65s cubic-bezier(.22,.68,0,1.2), transform 0.65s cubic-bezier(.22,.68,0,1.2);
}
.reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
}

/* â”€â”€ Mount animations â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.animate-fade-in-up {
    animation: fadeInUp 0.7s cubic-bezier(.22,.68,0,1.2) both;
}
.animate-fade-in-right {
    animation: fadeInRight 0.7s cubic-bezier(.22,.68,0,1.2) both;
}

/* â”€â”€ Carousel â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

/* â”€â”€ Hero default â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

/* â”€â”€ Hero text â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

/* â”€â”€ Demo card â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

/* â”€â”€ Section shared â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.section-alt { background: hsl(var(--muted)/0.25); border-top: 1px solid hsl(var(--border)/0.5); border-bottom: 1px solid hsl(var(--border)/0.5); }
.section-header { text-align: center; max-width: 560px; margin: 0 auto; }
.section-eyebrow { font-size: 12px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: hsl(var(--primary)); margin-bottom: 8px; }
.section-title { font-size: clamp(1.7rem, 3vw, 2.25rem); font-weight: 700; letter-spacing: -0.02em; color: hsl(var(--foreground)); }
.section-desc { margin-top: 12px; font-size: 15px; line-height: 1.7; color: hsl(var(--muted-foreground)); }

/* â”€â”€ Feature cards â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

/* â”€â”€ Promo banner â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.promo-banner {
    display: block; border-radius: 16px; overflow: hidden;
    transition: opacity 0.25s, transform 0.25s,
                opacity 0.65s cubic-bezier(.22,.68,0,1.2) !important;
}
.promo-banner:hover { opacity: 0.94; transform: scale(1.005); }

/* â”€â”€ Proof cards â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.proof-card { padding: 20px; border-radius: 12px; background: hsl(var(--card)); border: 1px solid hsl(var(--border)/0.6); }

/* â”€â”€ Pricing cards â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

/* â”€â”€ FAQ cards â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.faq-card {
    padding: 24px; border-radius: 12px;
    border: 1px solid hsl(var(--border)/0.7);
    background: hsl(var(--card));
    transition: border-color 0.2s, opacity 0.65s cubic-bezier(.22,.68,0,1.2), transform 0.65s cubic-bezier(.22,.68,0,1.2);
}
.faq-card:hover { border-color: hsl(var(--primary)/0.3); }

/* â”€â”€ CTA section â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
.cta-section { position: relative; overflow: hidden; background: hsl(var(--muted)/0.2); border-top: 1px solid hsl(var(--border)/0.5); }
.cta-glow {
    position: absolute; top: 50%; left: 50%; width: 500px; height: 300px;
    transform: translate(-50%, -50%);
    background: radial-gradient(ellipse, hsl(var(--primary)/0.1) 0%, transparent 70%);
    pointer-events: none;
}
</style>
