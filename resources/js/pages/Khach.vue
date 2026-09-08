<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import DemoBookingModal from '@/components/DemoBookingModal.vue';
import { Button } from '@/components/ui/button';
import AppTopbarLayout from '@/layouts/AppTopbarLayout.vue';
import { register } from '@/routes';

// Import subcomponents
import KhachFeatures from './khach/components/KhachFeatures.vue';
import KhachHero from './khach/components/KhachHero.vue';
import KhachNews from './khach/components/KhachNews.vue';
import KhachPromoBanner from './khach/components/KhachPromoBanner.vue';
import KhachStats from './khach/components/KhachStats.vue';
import KhachStickyCta from './khach/components/KhachStickyCta.vue';
import KhachTestimonials from './khach/components/KhachTestimonials.vue';
import KhachTrustIntegrations from './khach/components/KhachTrustIntegrations.vue';

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

const promoBanners = computed(() => props.banners?.promo ?? []);
const firstPromoBanner = computed(() => promoBanners.value[0] ?? null);

const showStickyCta = ref(false);
const isDemoModalOpen = ref(false);
let heroObserver: IntersectionObserver | null = null;
let revealObserver: IntersectionObserver | null = null;

onMounted(() => {
    // Observer for toggling sticky bottom CTA bar
    heroObserver = new IntersectionObserver(
        ([entry]) => {
            showStickyCta.value = !entry.isIntersecting;
        },
        { threshold: 0.1 },
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
        { threshold: 0.1 },
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
        <Head title="Aventura | SaaS quản lý nhà hàng">
            <meta
                name="description"
                content="Aventura - Nền tảng quản lý nhà hàng thông minh. QR Order, Kitchen Display, Quản lý Kho, Nhân sự, Báo cáo AI. Dùng thử miễn phí."
            />
            <meta
                property="og:title"
                content="Aventura | SaaS quản lý nhà hàng"
            />
            <meta
                property="og:description"
                content="Vận hành nhà hàng vượt trội — QR Order, Kitchen Display, AI Analytics. Dùng thử miễn phí 14 ngày."
            />
            <meta property="og:type" content="website" />
            <meta name="twitter:card" content="summary_large_image" />
        </Head>

        <!-- 1. Promo Banner -->
        <KhachPromoBanner :firstPromoBanner="firstPromoBanner" />

        <!-- 2. Hero Section -->
        <KhachHero :banners="banners" />

        <!-- 3. Features Map & Console -->
        <KhachFeatures />

        <!-- 4. About Aventura Teaser Section (Linking to /gioi-thieu) -->
        <section class="border-y border-border/40 bg-muted/20 py-20 px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <span class="inline-block rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary">
                            Về Aventura
                        </span>
                        <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-foreground sm:text-4xl">
                            Nền tảng quản lý nhà hàng thế hệ mới dành cho bạn
                        </h2>
                        <p class="mt-4 text-base leading-relaxed text-muted-foreground">
                            Aventura là nền tảng SaaS giúp các nhà hàng, quán cà phê tối ưu quy trình vận hành, quản lý bàn, đơn hàng, nguyên vật liệu và doanh thu một cách hiệu quả, nhanh chóng và chính xác.
                        </p>
                        <div class="mt-8 grid grid-cols-2 gap-4">
                            <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm">
                                <p class="text-2xl font-bold text-primary">+2.000</p>
                                <p class="text-xs text-muted-foreground mt-1">Nhà hàng tin dùng</p>
                            </div>
                            <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm">
                                <p class="text-2xl font-bold text-primary">99.9%</p>
                                <p class="text-xs text-muted-foreground mt-1">Thời gian hoạt động</p>
                            </div>
                        </div>
                        <div class="mt-8 flex items-center gap-4">
                            <Button as-child size="lg" class="bg-primary text-primary-foreground hover:bg-primary/90">
                                <Link href="/gioi-thieu" prefetch="hover" cache-for="1m">Khám phá về Aventura &rarr;</Link>
                            </Button>
                        </div>
                    </div>
                    <div class="relative flex justify-center">
                        <img
                            src="/images/about_restaurant_interior.webp"
                            alt="Không gian nhà hàng hiện đại"
                            class="w-full max-w-md rounded-2xl border border-border/60 shadow-xl object-cover aspect-4/3"
                            loading="lazy"
                            decoding="async"
                        />
                        <div class="absolute -bottom-6 -left-6 hidden sm:block rounded-xl border border-border bg-card p-4 shadow-lg max-w-xs">
                            <p class="text-xs font-bold text-foreground">Đơn giản hóa quản lý</p>
                            <p class="text-xs text-muted-foreground mt-1">Nâng tầm trải nghiệm thực khách tại mọi điểm chạm.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5. Trust Badges + Integrations -->
        <KhachTrustIntegrations />

        <!-- 6. Pricing Overview Section with Link to /bang-gia -->
        <section class="py-20 px-4 sm:px-6 lg:px-8 bg-background">
            <div class="mx-auto max-w-7xl text-center">
                <span class="inline-block rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary">
                    Bảng giá minh bạch
                </span>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-foreground sm:text-4xl">
                    Lựa chọn gói phù hợp với nhu cầu của bạn
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-base text-muted-foreground">
                    Từ quán nhỏ đến chuỗi lớn — chúng tôi có gói phù hợp giúp bạn quản lý nhà hàng dễ dàng, hiệu quả và tiết kiệm chi phí nhất.
                </p>

                <!-- 4 Quick Plan Teaser Cards -->
                <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 text-left">
                    <div class="rounded-2xl border border-border bg-card p-6 shadow-sm flex flex-col justify-between">
                        <div>
                            <span class="text-xs font-semibold text-muted-foreground uppercase">Khởi đầu</span>
                            <h3 class="text-xl font-bold mt-1 text-foreground">Miễn Phí</h3>
                            <p class="text-xs text-muted-foreground mt-1">Dành cho quán nhỏ mới mở</p>
                            <div class="mt-4 flex items-baseline gap-1">
                                <span class="text-3xl font-extrabold text-foreground">0đ</span>
                                <span class="text-xs text-muted-foreground">/tháng</span>
                            </div>
                            <ul class="mt-6 space-y-2 text-xs text-muted-foreground">
                                <li>✓ 1 chi nhánh, 15 bàn</li>
                                <li>✓ 5 nhân viên</li>
                                <li>✓ Màn hình Bếp KDS</li>
                            </ul>
                        </div>
                        <Button as-child variant="outline" class="w-full mt-6">
                            <Link href="/bang-gia">Xem chi tiết</Link>
                        </Button>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-6 shadow-sm flex flex-col justify-between">
                        <div>
                            <span class="text-xs font-semibold text-muted-foreground uppercase">Phổ thông</span>
                            <h3 class="text-xl font-bold mt-1 text-foreground">Cơ Bản</h3>
                            <p class="text-xs text-muted-foreground mt-1">Dành cho quán vừa và nhỏ</p>
                            <div class="mt-4 flex items-baseline gap-1">
                                <span class="text-3xl font-extrabold text-foreground">299.000đ</span>
                                <span class="text-xs text-muted-foreground">/tháng</span>
                            </div>
                            <ul class="mt-6 space-y-2 text-xs text-muted-foreground">
                                <li>✓ 3 chi nhánh, 60 bàn</li>
                                <li>✓ 20 nhân viên</li>
                                <li>✓ Màn hình Bếp KDS</li>
                            </ul>
                        </div>
                        <Button as-child variant="outline" class="w-full mt-6">
                            <Link href="/bang-gia">Xem chi tiết</Link>
                        </Button>
                    </div>

                    <div class="rounded-2xl border-2 border-primary bg-card p-6 shadow-lg flex flex-col justify-between relative">
                        <span class="absolute -top-3 right-4 rounded-full bg-primary px-2.5 py-0.5 text-[10px] font-bold text-primary-foreground">Khuyên dùng</span>
                        <div>
                            <span class="text-xs font-semibold text-primary uppercase">Tiêu chuẩn</span>
                            <h3 class="text-xl font-bold mt-1 text-foreground">Chuyên Nghiệp</h3>
                            <p class="text-xs text-muted-foreground mt-1">Lựa chọn phổ biến cho nhà hàng</p>
                            <div class="mt-4 flex items-baseline gap-1">
                                <span class="text-3xl font-extrabold text-foreground">699.000đ</span>
                                <span class="text-xs text-muted-foreground">/tháng</span>
                            </div>
                            <ul class="mt-6 space-y-2 text-xs text-muted-foreground">
                                <li>✓ 10 chi nhánh, 200 bàn</li>
                                <li>✓ 60 nhân viên</li>
                                <li>✓ Đặt món qua QR & Quản lý tồn kho</li>
                            </ul>
                        </div>
                        <Button as-child class="w-full mt-6 bg-primary text-primary-foreground hover:bg-primary/90">
                            <Link href="/bang-gia" prefetch="hover" cache-for="1m">Xem chi tiết</Link>
                        </Button>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-6 shadow-sm flex flex-col justify-between">
                        <div>
                            <span class="text-xs font-semibold text-muted-foreground uppercase">Quy mô lớn</span>
                            <h3 class="text-xl font-bold mt-1 text-foreground">Doanh Nghiệp</h3>
                            <p class="text-xs text-muted-foreground mt-1">Chuỗi nhà hàng & phân quyền VIP</p>
                            <div class="mt-4 flex items-baseline gap-1">
                                <span class="text-3xl font-extrabold text-foreground">1.499.000đ</span>
                                <span class="text-xs text-muted-foreground">/tháng</span>
                            </div>
                            <ul class="mt-6 space-y-2 text-xs text-muted-foreground">
                                <li>✓ Không giới hạn chi nhánh & bàn</li>
                                <li>✓ AI dự báo doanh thu & kho</li>
                                <li>✓ Hỗ trợ kỹ thuật 24/7 VIP</li>
                            </ul>
                        </div>
                        <Button as-child variant="outline" class="w-full mt-6">
                            <Link href="/bang-gia" prefetch="hover" cache-for="1m">Xem chi tiết</Link>
                        </Button>
                    </div>
                </div>

                <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <Button as-child size="lg" class="bg-primary text-primary-foreground hover:bg-primary/90">
                        <Link href="/bang-gia" prefetch="hover" cache-for="1m">So sánh đầy đủ tính năng theo gói &rarr;</Link>
                    </Button>
                </div>
            </div>
        </section>

        <!-- 7. Testimonials -->
        <KhachTestimonials />

        <!-- 8. Stats count-up -->
        <KhachStats />

        <!-- 9. Latest News -->
        <KhachNews :latestNews="latestNews" />

        <!-- 10. Call to Action / footer section -->
        <section
            class="bg-gradient-to-b from-primary/5 via-transparent to-transparent px-4 py-16 lg:px-8 lg:py-20"
        >
            <div
                class="reveal-on-scroll mx-auto flex max-w-4xl flex-col items-center gap-5 text-center"
            >
                <h2
                    class="heading-section text-gradient-brand text-3xl font-bold"
                >
                    Đăng ký và thử ngay hôm nay
                </h2>
                <p class="max-w-2xl text-muted-foreground">
                    Aventura đủ gọn để thử, đủ sâu để chạy thật. Dùng thử miễn phí 14 ngày không cần thẻ tín dụng.
                </p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <Button v-if="canRegister" as-child size="lg">
                        <Link :href="register()" prefetch="hover" cache-for="1m">Bắt đầu miễn phí</Link>
                    </Button>
                    <Button
                        variant="outline"
                        size="lg"
                        @click="isDemoModalOpen = true"
                    >
                        <span class="flex cursor-pointer items-center gap-2">
                            <span>📅</span> Đặt lịch demo với chuyên gia
                        </span>
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    Demo 30 phút · Không cam kết · Chuyên gia tư vấn 1:1
                </p>
            </div>
        </section>
    </AppTopbarLayout>

    <!-- 11. Sticky bottom CTA bar -->
    <KhachStickyCta
        :canRegister="canRegister"
        :showStickyCta="showStickyCta"
        @openDemo="isDemoModalOpen = true"
    />

    <!-- Demo Booking Modal -->
    <DemoBookingModal
        :isOpen="isDemoModalOpen"
        @close="isDemoModalOpen = false"
    />
</template>

<style>
/* Scroll Reveal (Global style so it applies to subcomponents as well) */
.reveal-on-scroll {
    opacity: 0;
    transform: translateY(24px);
    transition:
        opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1),
        transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}
.reveal-on-scroll.revealed {
    opacity: 1;
    transform: translateY(0);
}
</style>
