<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppTopbarLayout from '@/layouts/AppTopbarLayout.vue';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';

// Import subcomponents
import KhachPromoBanner from './khach/components/KhachPromoBanner.vue';
import KhachHero from './khach/components/KhachHero.vue';
import KhachHowItWorks from './khach/components/KhachHowItWorks.vue';
import KhachFeatures from './khach/components/KhachFeatures.vue';
import KhachNews from './khach/components/KhachNews.vue';
import KhachPricing from './khach/components/KhachPricing.vue';
import KhachTestimonials from './khach/components/KhachTestimonials.vue';
import KhachFaq from './khach/components/KhachFaq.vue';
import KhachStats from './khach/components/KhachStats.vue';
import KhachStickyCta from './khach/components/KhachStickyCta.vue';

interface Banner {
    id: number;
    title: string | null;
    subtitle: string | null;
    image_url: string;
    link_url: string | null;
}

interface NewsPost {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    category: string;
    featured_image_url: string | null;
    is_featured: boolean;
    published_at: string;
}

interface DbPlan {
    id: number;
    code: string;
    name: string;
    price: number;
    billing_cycle: string;
    max_branches: number | null;
    max_tables: number | null;
    max_users: number | null;
    features: Record<string, unknown>;
}

const props = defineProps<{
    canRegister: boolean;
    banners?: { hero: Banner[]; promo: Banner[] };
    latestNews?: NewsPost[];
    plans?: DbPlan[];
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user);

const promoBanners = computed(() => props.banners?.promo ?? []);
const firstPromoBanner = computed(() => promoBanners.value[0] ?? null);

const showStickyCta = ref(false);
let heroObserver: IntersectionObserver | null = null;
let revealObserver: IntersectionObserver | null = null;

onMounted(() => {
    // Observer for toggling sticky bottom CTA bar
    heroObserver = new IntersectionObserver(
        ([entry]) => {
            showStickyCta.value = !entry.isIntersecting; 
        },
        { threshold: 0.1 }
    );
    const heroEl = document.getElementById('hero-section');

    if (heroEl) {
        heroObserver.observe(heroEl);
    }

    // Scroll Reveal Observer
    revealObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    revealObserver?.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.1 }
    );
    document.querySelectorAll('.reveal-on-scroll').forEach((el) => {
        revealObserver?.observe(el);
    });
});

onUnmounted(() => {
    heroObserver?.disconnect();
    revealObserver?.disconnect();
});
</script>

<template>
    <AppTopbarLayout transparent>
        <Head title="Aventura | SaaS quản lý nhà hàng" />

        <!-- 1. Promo Banner -->
        <KhachPromoBanner :firstPromoBanner="firstPromoBanner" />

        <!-- 2. Hero Section -->
        <KhachHero :banners="banners" />

        <!-- 3. How It Works -->
        <KhachHowItWorks />

        <!-- 4. Features Map & Console -->
        <KhachFeatures />

        <!-- 5. Latest News -->
        <KhachNews :latestNews="latestNews" />

        <!-- 6. Pricing -->
        <KhachPricing :plans="plans" :canRegister="canRegister" :user="user" />

        <!-- 7. Testimonials -->
        <KhachTestimonials />

        <!-- 8. FAQ Accordion -->
        <KhachFaq :canRegister="canRegister" />

        <!-- 9. Stats count-up -->
        <KhachStats />

        <!-- 10. Call to Action / footer section -->
        <section class="px-4 py-10 lg:py-12 lg:px-8">
            <div
                class="reveal-on-scroll mx-auto flex max-w-4xl flex-col items-center gap-5 text-center"
            >
                <h2 class="text-3xl font-semibold">Đăng ký và thử ngay</h2>
                <p class="max-w-2xl text-muted-foreground">
                    Aventura đủ gọn để thử, đủ sâu để chạy thật. Gói Free có hạn
                    mức rõ; gói Pro mở khóa phần cần kiểm soát.
                </p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <Button v-if="canRegister" as-child size="lg">
                        <Link :href="register()">Bắt đầu miễn phí</Link>
                    </Button>
                    <Button as-child variant="outline" size="lg">
                        <a href="#features">Xem lại tính năng</a>
                    </Button>
                </div>
            </div>
        </section>
    </AppTopbarLayout>

    <!-- 11. Sticky bottom CTA bar -->
    <KhachStickyCta :canRegister="canRegister" :showStickyCta="showStickyCta" />
</template>

<style>
/* Scroll Reveal (Global style so it applies to subcomponents as well) */
.reveal-on-scroll {
    opacity: 0;
    transform: translateY(24px);
    transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), 
                transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}
.reveal-on-scroll.revealed {
    opacity: 1;
    transform: translateY(0);
}
</style>
