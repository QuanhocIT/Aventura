<script setup lang="ts">
import { ChevronRight, ChevronLeft } from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import KhachHeroDemo from './KhachHeroDemo.vue';

interface Banner {
    id: number;
    title: string | null;
    subtitle: string | null;
    image_url: string;
    link_url: string | null;
}

const props = defineProps<{
    banners?: { hero: Banner[]; promo: Banner[] };
}>();

// ── Hero Slideshow ──────────────────────────────────────────────
const heroIndex = ref(0);
const heroDir = ref<'next' | 'prev'>('next');
let heroTimer: ReturnType<typeof setInterval> | null = null;

const heroBanners = computed(() => props.banners?.hero ?? []);

const defaultSlides = [
    {
        subtitle: 'VẬN HÀNH THÔNG MINH – CHỐNG THẤT THOÁT TUYỆT ĐỐI',
        title: 'Vận hành nhà hàng <br /><span class="text-amber-400">vượt trội</span> cùng Aventura',
        description: 'Hệ thống quản trị SaaS tối ưu cho mọi mô hình: từ nhà hàng, quán cà phê đến chuỗi kinh doanh. Tự động hóa QR order, định lượng nguyên vật liệu kho, giám sát doanh thu tức thì và báo cáo vận hành thông minh bằng AI.',
        badges: [
            { icon: '✨', label: 'AI thông minh' },
            { icon: '⚡', label: 'Đồng bộ Realtime' },
            { icon: '🔒', label: 'Bảo mật Audit' },
            { icon: '🎁', label: 'Dùng miễn phí' }
        ],
        image: '/restaurant_hero_bg_1.jpg',
        gradient: 'linear-gradient(135deg, rgba(8, 10, 15, 0.93) 0%, rgba(15, 20, 30, 0.82) 50%, rgba(8, 10, 15, 0.91) 100%)'
    },
    {
        subtitle: 'QR ORDER TẠI BÀN – TỐI ƯU TRẢI NGHIỆM KHÁCH HÀNG',
        title: 'Đột phá doanh thu <br />với <span class="text-amber-400">QR Order</span> tại bàn',
        description: 'Khách tự quét mã QR gọi món và thanh toán trực tiếp trên di động. Bếp nhận đơn tức thì, đồng bộ thời gian thực qua màn hình KDS. Giảm 50% chi phí nhân sự và loại bỏ hoàn toàn sai sót phục vụ.',
        badges: [
            { icon: '📱', label: 'Gọi món không chạm' },
            { icon: '🍳', label: 'Bếp KDS Realtime' },
            { icon: '🚀', label: 'Tăng tốc phục vụ' },
            { icon: '📈', label: 'Tối ưu doanh số' }
        ],
        image: '/restaurant_hero_bg_2.jpg',
        gradient: 'linear-gradient(135deg, rgba(8, 10, 15, 0.94) 0%, rgba(12, 28, 24, 0.84) 50%, rgba(8, 10, 15, 0.92) 100%)'
    },
    {
        subtitle: 'QUẢN LÝ KHO THÔNG MINH – ĐỊNH LƯỢNG CHÍNH XÁC',
        title: 'Kiểm soát nguyên liệu <br /><span class="text-amber-400">tự động trừ kho</span>',
        description: 'Tự động khấu hao nguyên vật liệu trong kho ngay khi hóa đơn được thanh toán dựa trên công thức định lượng (recipe). Cảnh báo tồn kho thấp dưới định mức để chuẩn bị nguồn cung kịp thời.',
        badges: [
            { icon: '📦', label: 'Tự động trừ kho' },
            { icon: '⚖️', label: 'Định lượng chuẩn' },
            { icon: '⚠️', label: 'Cảnh báo tồn thấp' },
            { icon: '📉', label: 'Chống thất thoát' }
        ],
        image: '/restaurant_hero_bg_1.jpg',
        gradient: 'linear-gradient(135deg, rgba(8, 10, 15, 0.94) 0%, rgba(30, 18, 12, 0.84) 50%, rgba(8, 10, 15, 0.92) 100%)'
    }
];

const activeSlides = computed(() => {
    const dbBanners = props.banners?.hero ?? [];

    if (dbBanners.length === 0) {
        return defaultSlides;
    }

    return dbBanners.map((db, idx) => {
        const fallback = defaultSlides[idx % defaultSlides.length];
        const hasRealImage = db.image_url && 
                             !db.image_url.endsWith('.svg') && 
                             !db.image_url.includes('hero-dashboard') && 
                             !db.image_url.includes('hero-analytics');

        return {
            subtitle: db.subtitle || fallback.subtitle,
            title: db.title ? db.title : fallback.title,
            description: fallback.description,
            badges: fallback.badges,
            image: hasRealImage ? db.image_url : fallback.image,
            gradient: fallback.gradient
        };
    });
});

function startHero() {
    if (activeSlides.value.length <= 1) {
        return;
    }

    heroTimer = setInterval(() => advanceHero('next'), 6000);
}

function stopHero() {
    if (heroTimer) {
        clearInterval(heroTimer);
        heroTimer = null; 
    }
}

function advanceHero(dir: 'next' | 'prev') {
    heroDir.value = dir;
    const len = activeSlides.value.length;
    heroIndex.value = dir === 'next'
        ? (heroIndex.value + 1) % len
        : (heroIndex.value - 1 + len) % len;
}

function goHero(idx: number) {
    heroDir.value = idx > heroIndex.value ? 'next' : 'prev';
    heroIndex.value = idx;
    stopHero();
    startHero();
}

function navHero(dir: 'next' | 'prev') {
    advanceHero(dir);
    stopHero();
    startHero();
}

onMounted(() => {
    startHero();
});

onUnmounted(() => {
    stopHero();
});
</script>

<template>
    <!-- ── Premium Travel-Inspired Hero Section ────────────────────────── -->
    <section
        id="hero-section"
        class="relative overflow-hidden px-4 pt-24 pb-16 lg:px-8 lg:pt-28 lg:pb-20"
    >
        <!-- Ambient Backgrounds with smooth transitions -->
        <div class="absolute inset-0 z-0 select-none pointer-events-none">
            <Transition name="fade-bg">
                <div 
                    :key="heroIndex"
                    class="absolute inset-0 bg-cover bg-center transition-all duration-1000"
                    :style="{
                        backgroundImage: activeSlides[heroIndex].gradient + ', url(' + activeSlides[heroIndex].image + ')'
                    }"
                />
            </Transition>
        </div>

        <div class="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:items-center relative z-10">

            <!-- Premium gradient blobs -->
            <div class="pointer-events-none absolute -bottom-32 left-1/5 h-[500px] w-[500px] rounded-full bg-primary/15 blur-[140px] float-glow"></div>
            <div class="pointer-events-none absolute -top-20 right-1/4 h-[400px] w-[400px] rounded-full bg-violet-500/10 blur-[120px] float-glow" style="animation-delay: 3s;"></div>
            <div class="pointer-events-none absolute top-1/3 left-1/2 h-[300px] w-[300px] rounded-full bg-rose-500/8 blur-[100px] float-glow" style="animation-delay: 5s;"></div>
            <div class="pointer-events-none absolute bottom-1/4 right-1/3 h-[250px] w-[250px] rounded-full bg-teal-500/8 blur-[80px] float-glow" style="animation-delay: 7s;"></div>

            <!-- Left Column: Premium Value Proposition & Feature Badges -->
            <div class="relative min-h-[480px] sm:min-h-[420px] lg:min-h-[450px] flex flex-col justify-center">
                <Transition :name="`slide-${heroDir}`">
                    <div :key="heroIndex" class="absolute inset-0 flex flex-col justify-center w-full">
                        <!-- Amber Subtitle -->
                        <span class="text-amber-400 font-extrabold tracking-wider mb-4 text-xs sm:text-sm uppercase block">
                            {{ activeSlides[heroIndex].subtitle }}
                        </span>
                        
                        <!-- Massive Headline -->
                        <h1 class="max-w-2xl text-4xl font-extrabold tracking-tight lg:text-6xl text-gradient-hero font-sans leading-[1.12]" v-html="activeSlides[heroIndex].title">
                        </h1>
                        
                        <!-- Description -->
                        <p class="mt-6 max-w-xl text-base leading-relaxed text-zinc-300 lg:text-lg">
                            {{ activeSlides[heroIndex].description }}
                        </p>
                        
                        <!-- Glassmorphic Tag Badges in Travel layout style -->
                        <div class="mt-10 flex flex-wrap gap-3.5">
                            <div 
                                v-for="(badge, idx) in activeSlides[heroIndex].badges"
                                :key="idx"
                                class="flex items-center gap-2 rounded-xl border border-white/15 bg-white/8 backdrop-blur-md px-4 py-3 text-xs sm:text-sm font-semibold text-white shadow-lg hover:bg-white/15 hover:border-white/25 hover:scale-[1.02] transition-all duration-300"
                            >
                                <span class="text-amber-400 text-sm">{{ badge.icon }}</span> {{ badge.label }}
                            </div>
                        </div>

                        <!-- Slideshow Navigation Controls (Arrows & Dots) -->
                        <div class="mt-10 flex items-center gap-3.5">
                            <!-- Prev button -->
                            <button 
                                @click="navHero('prev')"
                                class="flex items-center justify-center size-8 sm:size-9 rounded-xl border border-white/15 bg-white/5 text-zinc-400 hover:text-white hover:bg-white/15 hover:shadow-[0_0_16px_rgba(245,158,11,0.2)] transition-all duration-300 active:scale-95 cursor-pointer backdrop-blur-sm"
                                aria-label="Previous Slide"
                            >
                                <ChevronLeft class="size-4" />
                            </button>

                            <!-- Indicators Dots -->
                            <div class="flex gap-2.5">
                                <button
                                    v-for="(slide, idx) in activeSlides"
                                    :key="idx"
                                    @click="goHero(idx)"
                                    class="h-2 rounded-full transition-all duration-300 cursor-pointer"
                                    :class="heroIndex === idx ? 'w-7 bg-amber-400' : 'w-2 bg-white/30 hover:bg-white/50'"
                                    :aria-label="`Go to slide ${idx + 1}`"
                                />
                            </div>

                            <!-- Next button -->
                            <button 
                                @click="navHero('next')"
                                class="flex items-center justify-center size-8 sm:size-9 rounded-xl border border-white/15 bg-white/5 text-zinc-400 hover:text-white hover:bg-white/15 hover:shadow-[0_0_16px_rgba(245,158,11,0.2)] transition-all duration-300 active:scale-95 cursor-pointer backdrop-blur-sm"
                                aria-label="Next Slide"
                            >
                                <ChevronRight class="size-4" />
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>

            <!-- Right Column: Interactive Live Demo widget -->
            <KhachHeroDemo />
            
        </div>
    </section>
</template>

<style scoped>
/* Background cross-fade transition */
.fade-bg-enter-active,
.fade-bg-leave-active {
    transition: opacity 1.2s ease-in-out;
}
.fade-bg-enter-from,
.fade-bg-leave-to {
    opacity: 0;
}

/* Hero slideshow — slide-next (left → right) */
.slide-next-enter-active,
.slide-next-leave-active,
.slide-prev-enter-active,
.slide-prev-leave-active {
    transition: transform 0.6s cubic-bezier(0.77, 0, 0.175, 1), opacity 0.6s ease;
    position: absolute;
    inset: 0;
}

.slide-next-enter-from  { transform: translateX(100%);  opacity: 0; }
.slide-next-enter-to    { transform: translateX(0);      opacity: 1; }
.slide-next-leave-from  { transform: translateX(0);      opacity: 1; }
.slide-next-leave-to    { transform: translateX(-100%);  opacity: 0; }

.slide-prev-enter-from  { transform: translateX(-100%); opacity: 0; }
.slide-prev-enter-to    { transform: translateX(0);      opacity: 1; }
.slide-prev-leave-from  { transform: translateX(0);      opacity: 1; }
.slide-prev-leave-to    { transform: translateX(100%);   opacity: 0; }
</style>
