<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Play,
    Zap,
    Shield,
    BarChart3,
    Headphones,
    UtensilsCrossed,
    FileText,
    Package,
    TrendingUp,
    Users,
    Sliders,
    Target,
    Eye,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppTopbarLayout from '@/layouts/AppTopbarLayout.vue';
import BareLayout from '@/layouts/BareLayout.vue';

defineOptions({
    layout: BareLayout,
});

defineProps<{
    canRegister?: boolean;
}>();

const isVideoModalOpen = ref(false);
</script>

<template>
    <AppTopbarLayout>
        <Head title="Giới thiệu - Aventura | Nền tảng quản lý nhà hàng thế hệ mới">
            <meta
                name="description"
                content="Tìm hiểu về Aventura - Nền tảng SaaS quản lý nhà hàng thông minh, tối ưu quy trình vận hành, kiểm soát nguyên vật liệu và nâng cao trải nghiệm khách hàng."
            />
        </Head>

        <div class="relative overflow-hidden bg-background text-foreground">
            <!-- ── SECTION 1: HERO VỀ AVENTURA ───────────────────────────────── -->
            <section class="relative border-b border-slate-200/80 bg-gradient-to-b from-[#f0f4f8] via-[#eff3f7] to-[#eaf0f5] py-16 lg:py-24 dark:border-slate-800/80 dark:from-slate-900/60 dark:via-slate-900/40 dark:to-background">
                <div class="mx-auto max-w-7xl xl:max-w-[1400px] px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-10 lg:grid-cols-12 lg:gap-8">
                        <div class="space-y-6 lg:col-span-6">
                            <div class="text-xs font-bold tracking-widest text-blue-600 uppercase dark:text-blue-400">
                                VỀ AVENTURA
                            </div>

                            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-[38px] xl:text-[44px] lg:leading-[1.18] dark:text-white">
                                <span class="block whitespace-normal sm:whitespace-nowrap">Nền tảng quản lý nhà hàng</span>
                                <span class="block whitespace-normal sm:whitespace-nowrap">thế hệ mới dành cho bạn</span>
                            </h1>

                            <p class="text-base leading-relaxed text-slate-600 sm:text-lg dark:text-slate-300">
                                Aventura là nền tảng SaaS giúp các nhà hàng, quán cà phê tối ưu quy trình vận hành, quản lý bàn, đơn hàng, nguyên vật liệu và doanh thu một cách hiệu quả, nhanh chóng và chính xác. Chúng tôi mang đến giải pháp toàn diện để bạn tập trung vào điều quan trọng nhất – trải nghiệm khách hàng.
                            </p>

                            <div class="flex flex-wrap items-center gap-4 pt-1">
                                <Button
                                    as-child
                                    size="lg"
                                    class="h-11 gap-2 rounded-lg bg-blue-600 px-6 font-semibold text-white shadow-md shadow-blue-600/20 transition-all hover:bg-blue-700 hover:shadow-lg dark:bg-blue-600 dark:hover:bg-blue-500"
                                >
                                    <Link href="/register" prefetch="hover" cache-for="1m">
                                        Bắt đầu ngay <ArrowRight class="size-4" />
                                    </Link>
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="lg"
                                    class="h-11 gap-2 rounded-lg border border-slate-200 bg-white px-5 font-semibold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                    @click="isVideoModalOpen = true"
                                >
                                    <Play class="size-4 text-blue-600 dark:text-blue-400" />
                                    Xem video giới thiệu
                                </Button>
                            </div>
                        </div>

                        <div class="relative flex justify-center lg:col-span-6 lg:justify-end">
                            <div class="relative w-full max-w-2xl lg:max-w-none">
                                <img
                                    src="/images/about_hero_mockup.webp"
                                    alt="Giao diện Aventura trên Laptop và Smartphone"
                                    class="h-auto w-full object-contain transition-transform duration-500 hover:scale-[1.01]"
                                    fetchpriority="high"
                                    decoding="async"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 2: TẠI SAO LẠI LÀ AVENTURA? ───────────────────────── -->
            <section class="relative border-b border-slate-200 bg-slate-100/70 py-16 lg:py-24 dark:border-slate-800 dark:bg-slate-900/60">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-12 lg:grid-cols-12 lg:gap-12">
                        <!-- Left text & 4 pillars -->
                        <div class="space-y-8 lg:col-span-7">
                            <div class="space-y-4">
                                <div class="inline-flex items-center gap-2 rounded-full border border-blue-500/20 bg-blue-500/10 px-3.5 py-1 text-xs font-bold tracking-wider text-blue-600 uppercase dark:text-blue-400">
                                    TẠI SAO LẠI LÀ AVENTURA?
                                </div>
                                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl lg:text-4xl">
                                    Giải pháp quản lý toàn diện <br class="hidden sm:inline" />
                                    cho nhà hàng, quán cà phê
                                </h2>
                                <p class="text-base leading-relaxed text-muted-foreground">
                                    Chúng tôi không chỉ cung cấp phần mềm, mà còn đồng hành cùng bạn trên hành trình phát triển kinh doanh bền vững. Với công nghệ hiện đại và đội ngũ hỗ trợ tận tâm, Aventura giúp bạn đơn giản hóa vận hành, tiết kiệm chi phí và nâng cao trải nghiệm khách hàng.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div class="group flex items-start gap-4 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-blue-500/40 hover:shadow-md dark:border-slate-800 dark:bg-slate-950 dark:hover:border-blue-400/40">
                                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 transition-colors duration-200 group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-500/20 dark:text-blue-400 dark:group-hover:bg-blue-500 dark:group-hover:text-white">
                                        <Zap class="size-5" />
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-foreground">Tối ưu quy trình vận hành</h3>
                                        <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                                            Quản lý bàn, đơn hàng, nhân viên trong một nền tảng duy nhất.
                                        </p>
                                    </div>
                                </div>

                                <div class="group flex items-start gap-4 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-blue-500/40 hover:shadow-md dark:border-slate-800 dark:bg-slate-950 dark:hover:border-blue-400/40">
                                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 transition-colors duration-200 group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-500/20 dark:text-blue-400 dark:group-hover:bg-blue-500 dark:group-hover:text-white">
                                        <Shield class="size-5" />
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-foreground">An toàn và bảo mật</h3>
                                        <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                                            Dữ liệu được mã hóa và sao lưu định kỳ, đảm bảo an toàn tuyệt đối.
                                        </p>
                                    </div>
                                </div>

                                <div class="group flex items-start gap-4 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-blue-500/40 hover:shadow-md dark:border-slate-800 dark:bg-slate-950 dark:hover:border-blue-400/40">
                                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 transition-colors duration-200 group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-500/20 dark:text-blue-400 dark:group-hover:bg-blue-500 dark:group-hover:text-white">
                                        <BarChart3 class="size-5" />
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-foreground">Báo cáo chi tiết, trực quan</h3>
                                        <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                                            Theo dõi doanh thu, hiệu suất kinh doanh theo thời gian thực.
                                        </p>
                                    </div>
                                </div>

                                <div class="group flex items-start gap-4 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-blue-500/40 hover:shadow-md dark:border-slate-800 dark:bg-slate-950 dark:hover:border-blue-400/40">
                                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 transition-colors duration-200 group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-500/20 dark:text-blue-400 dark:group-hover:bg-blue-500 dark:group-hover:text-white">
                                        <Headphones class="size-5" />
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-foreground">Hỗ trợ 24/7</h3>
                                        <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                                            Đội ngũ chuyên nghiệp, sẵn sàng hỗ trợ mọi lúc mọi nơi.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right photo with floating card -->
                        <div class="relative lg:col-span-5">
                            <div class="relative mx-auto max-w-md overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-2 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                                <img
                                    src="/images/about_restaurant_interior.webp"
                                    alt="Không gian nhà hàng hiện đại"
                                    class="h-[420px] w-full rounded-xl object-cover"
                                    loading="lazy"
                                    decoding="async"
                                />
                                <div class="absolute right-6 bottom-6 left-6 rounded-xl border border-white/40 bg-white/95 p-4 shadow-lg backdrop-blur-md dark:border-white/10 dark:bg-neutral-900/90 sm:left-auto sm:w-64">
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                                        Đơn giản hóa quản lý
                                    </h4>
                                    <p class="text-xs text-slate-600 dark:text-slate-300">
                                        Nâng tầm trải nghiệm
                                    </p>
                                    <div class="mt-2 h-1 w-12 rounded-full bg-blue-600 dark:bg-blue-400" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 3: CON SỐ NỔI BẬT (STATS) ────────────────────────── -->
            <section class="relative border-b border-blue-100/80 bg-gradient-to-b from-blue-50/60 via-slate-50/40 to-white py-16 lg:py-20 dark:border-blue-900/40 dark:from-blue-950/25 dark:via-slate-900/20 dark:to-background">
                <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl space-y-3">
                        <div class="inline-flex items-center gap-2 rounded-full border border-blue-500/20 bg-blue-500/10 px-3.5 py-1 text-xs font-bold tracking-wider text-blue-600 uppercase dark:text-blue-400">
                            CON SỐ NỔI BẬT
                        </div>
                        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl lg:text-4xl">
                            Hành trình đồng hành cùng hàng nghìn nhà hàng
                        </h2>
                        <p class="text-sm text-muted-foreground sm:text-base">
                            Aventura tự hào đã và đang được nhiều đối tác tin tưởng lựa chọn để phát triển kinh doanh.
                        </p>
                    </div>

                    <div class="mt-12 grid grid-cols-2 gap-5 lg:grid-cols-4 lg:gap-6">
                        <div class="group rounded-2xl border border-blue-100/80 bg-white p-6 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-blue-500/40 hover:shadow-md dark:border-blue-900/40 dark:bg-slate-900 dark:hover:border-blue-400/40">
                            <div class="text-3xl font-extrabold text-blue-600 transition-transform duration-200 group-hover:scale-105 sm:text-4xl lg:text-5xl dark:text-blue-400">
                                +2.000
                            </div>
                            <p class="mt-2 text-xs font-medium text-muted-foreground sm:text-sm">
                                Nhà hàng, quán cà phê đã tin dùng
                            </p>
                        </div>

                        <div class="group rounded-2xl border border-blue-100/80 bg-white p-6 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-blue-500/40 hover:shadow-md dark:border-blue-900/40 dark:bg-slate-900 dark:hover:border-blue-400/40">
                            <div class="text-3xl font-extrabold text-blue-600 transition-transform duration-200 group-hover:scale-105 sm:text-4xl lg:text-5xl dark:text-blue-400">
                                +150.000
                            </div>
                            <p class="mt-2 text-xs font-medium text-muted-foreground sm:text-sm">
                                Khách hàng tin dùng
                            </p>
                        </div>

                        <div class="group rounded-2xl border border-blue-100/80 bg-white p-6 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-blue-500/40 hover:shadow-md dark:border-blue-900/40 dark:bg-slate-900 dark:hover:border-blue-400/40">
                            <div class="text-3xl font-extrabold text-blue-600 transition-transform duration-200 group-hover:scale-105 sm:text-4xl lg:text-5xl dark:text-blue-400">
                                99,9%
                            </div>
                            <p class="mt-2 text-xs font-medium text-muted-foreground sm:text-sm">
                                Thời gian hoạt động
                            </p>
                        </div>

                        <div class="group rounded-2xl border border-blue-100/80 bg-white p-6 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-blue-500/40 hover:shadow-md dark:border-blue-900/40 dark:bg-slate-900 dark:hover:border-blue-400/40">
                            <div class="text-3xl font-extrabold text-blue-600 transition-transform duration-200 group-hover:scale-105 sm:text-4xl lg:text-5xl dark:text-blue-400">
                                4.8/5
                            </div>
                            <p class="mt-2 text-xs font-medium text-muted-foreground sm:text-sm">
                                Đánh giá từ người dùng
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 4: NỀN TẢNG VƯỢT TRỘI (CORE FEATURES) ─────────────── -->
            <section class="relative border-b border-slate-200 bg-slate-100/80 py-16 lg:py-24 dark:border-slate-800 dark:bg-slate-900/75">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center space-y-3">
                        <div class="inline-flex items-center gap-2 rounded-full border border-blue-500/20 bg-blue-500/10 px-3.5 py-1 text-xs font-bold tracking-wider text-blue-600 uppercase dark:text-blue-400">
                            NỀN TẢNG VƯỢT TRỘI
                        </div>
                        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl lg:text-4xl">
                            Những tính năng nổi bật
                        </h2>
                        <p class="text-sm text-muted-foreground sm:text-base">
                            Tất cả những gì bạn cần để quản lý nhà hàng, quán cà phê hiệu quả đều có trong Aventura.
                        </p>
                    </div>

                    <div class="mt-12 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <div class="group flex flex-col gap-3 rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-blue-500/40 hover:shadow-lg dark:border-slate-800 dark:bg-slate-950 dark:hover:border-blue-400/40">
                            <div class="flex size-12 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 transition-colors duration-200 group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-500/20 dark:text-blue-400 dark:group-hover:bg-blue-500 dark:group-hover:text-white">
                                <UtensilsCrossed class="size-6" />
                            </div>
                            <h3 class="text-base font-bold text-foreground">Quản lý bàn thông minh</h3>
                            <p class="text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                Theo dõi trạng thái bàn, đặt bàn và sắp xếp linh hoạt theo từng khu vực tầng, sảnh.
                            </p>
                        </div>

                        <div class="group flex flex-col gap-3 rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-blue-500/40 hover:shadow-lg dark:border-slate-800 dark:bg-slate-950 dark:hover:border-blue-400/40">
                            <div class="flex size-12 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 transition-colors duration-200 group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-500/20 dark:text-blue-400 dark:group-hover:bg-blue-500 dark:group-hover:text-white">
                                <FileText class="size-6" />
                            </div>
                            <h3 class="text-base font-bold text-foreground">Quản lý đơn hàng</h3>
                            <p class="text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                Xử lý đơn nhanh chóng, chính xác, đồng bộ tức thời giữa phục vụ và bếp bar, hạn chế sai sót.
                            </p>
                        </div>

                        <div class="group flex flex-col gap-3 rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-blue-500/40 hover:shadow-lg dark:border-slate-800 dark:bg-slate-950 dark:hover:border-blue-400/40">
                            <div class="flex size-12 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 transition-colors duration-200 group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-500/20 dark:text-blue-400 dark:group-hover:bg-blue-500 dark:group-hover:text-white">
                                <Package class="size-6" />
                            </div>
                            <h3 class="text-base font-bold text-foreground">Quản lý nguyên vật liệu</h3>
                            <p class="text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                Tự động trừ kho theo công thức định lượng (BOM), cảnh báo sớm khi hàng chạm ngưỡng an toàn.
                            </p>
                        </div>

                        <div class="group flex flex-col gap-3 rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-blue-500/40 hover:shadow-lg dark:border-slate-800 dark:bg-slate-950 dark:hover:border-blue-400/40">
                            <div class="flex size-12 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 transition-colors duration-200 group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-500/20 dark:text-blue-400 dark:group-hover:bg-blue-500 dark:group-hover:text-white">
                                <TrendingUp class="size-6" />
                            </div>
                            <h3 class="text-base font-bold text-foreground">Báo cáo doanh thu</h3>
                            <p class="text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                Phân tích chi tiết doanh thu theo giờ, món bán chạy, biên lợi nhuận, hỗ trợ ra quyết định kinh doanh.
                            </p>
                        </div>

                        <div class="group flex flex-col gap-3 rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-blue-500/40 hover:shadow-lg dark:border-slate-800 dark:bg-slate-950 dark:hover:border-blue-400/40">
                            <div class="flex size-12 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 transition-colors duration-200 group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-500/20 dark:text-blue-400 dark:group-hover:bg-blue-500 dark:group-hover:text-white">
                                <Users class="size-6" />
                            </div>
                            <h3 class="text-base font-bold text-foreground">Quản lý khách hàng</h3>
                            <p class="text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                Lưu trữ lịch sử giao dịch, tích lũy điểm thưởng thành viên và chăm sóc khách hàng tự động.
                            </p>
                        </div>

                        <div class="group flex flex-col gap-3 rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-blue-500/40 hover:shadow-lg dark:border-slate-800 dark:bg-slate-950 dark:hover:border-blue-400/40">
                            <div class="flex size-12 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 transition-colors duration-200 group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-500/20 dark:text-blue-400 dark:group-hover:bg-blue-500 dark:group-hover:text-white">
                                <Sliders class="size-6" />
                            </div>
                            <h3 class="text-base font-bold text-foreground">Tùy chỉnh linh hoạt</h3>
                            <p class="text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                Dễ dàng tùy biến menu, giá bán, phân quyền nhân sự, phù hợp với mọi mô hình từ quán nhỏ đến chuỗi.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 5: SỨ MỆNH & TẦM NHÌN ────────────────────────────── -->
            <section class="relative border-b border-slate-200 bg-white py-16 lg:py-24 dark:border-slate-800 dark:bg-background">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-12 lg:grid-cols-12 lg:gap-12">
                        <div class="space-y-4 lg:col-span-6">
                            <div class="inline-flex items-center gap-2 rounded-full border border-blue-500/20 bg-blue-500/10 px-3.5 py-1 text-xs font-bold tracking-wider text-blue-600 uppercase dark:text-blue-400">
                                SỨ MỆNH & TẦM NHÌN
                            </div>
                            <h2 class="text-2xl font-bold tracking-tight sm:text-3xl lg:text-4xl">
                                Vì một ngành F&B phát triển bền vững
                            </h2>
                            <p class="text-base leading-relaxed text-muted-foreground">
                                Chúng tôi tin rằng công nghệ có thể thay đổi cách các nhà hàng, quán cà phê vận hành và phát triển. Aventura ra đời với sứ mệnh mang đến giải pháp công nghệ hiện đại, dễ sử dụng và hiệu quả, giúp bạn nâng cao năng lực cạnh tranh và phát triển bền vững trong tương lai.
                            </p>
                        </div>

                        <div class="space-y-5 lg:col-span-6">
                            <div class="group flex items-start gap-4 rounded-2xl border border-blue-200 bg-blue-50/70 p-6 shadow-sm transition-all duration-200 hover:border-blue-400 hover:shadow-md dark:border-blue-800/60 dark:bg-blue-950/40">
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-600/20 dark:bg-blue-500">
                                    <Target class="size-6" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-foreground">Sứ mệnh</h3>
                                    <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                                        Mang công nghệ đến gần hơn với mọi nhà hàng, quán cà phê Việt Nam, giúp họ vận hành thông minh, hiệu quả và phát triển bền vững.
                                    </p>
                                </div>
                            </div>

                            <div class="group flex items-start gap-4 rounded-2xl border border-indigo-200 bg-indigo-50/70 p-6 shadow-sm transition-all duration-200 hover:border-indigo-400 hover:shadow-md dark:border-indigo-800/60 dark:bg-indigo-950/40">
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md shadow-indigo-600/20 dark:bg-indigo-500">
                                    <Eye class="size-6" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-foreground">Tầm nhìn</h3>
                                    <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                                        Trở thành nền tảng quản lý nhà hàng hàng đầu Đông Nam Á, được hàng triệu chủ kinh doanh tin tưởng và lựa chọn.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 6: BOTTOM CTA BANNER ─────────────────────────────── -->
            <section class="relative overflow-hidden bg-gradient-to-b from-neutral-900 via-neutral-950 to-black px-4 py-16 text-center text-white sm:px-6 lg:px-8 lg:py-20">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_60%_50%_at_50%_0%,rgba(59,130,246,0.15),transparent)]" />
                <div class="relative mx-auto max-w-4xl space-y-6">
                    <p class="text-xs font-bold tracking-widest text-blue-400 uppercase">
                        BẮT ĐẦU HÀNH TRÌNH CHUYỂN ĐỔI SỐ CÙNG AVENTURA NGAY HÔM NAY!
                    </p>
                    <h2 class="text-2xl font-black tracking-tight sm:text-3xl lg:text-4xl">
                        Đơn giản hơn – Hiệu quả hơn – Phát triển bền vững hơn
                    </h2>
                    <div>
                        <Button
                            as-child
                            size="lg"
                            class="h-12 gap-2 rounded-xl bg-blue-600 px-8 font-bold text-white shadow-lg shadow-blue-600/30 hover:bg-blue-500"
                        >
                            <Link href="/register" prefetch="hover" cache-for="1m">
                                Dùng miễn phí ngay <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </section>
        </div>

        <!-- Video Modal -->
        <Dialog v-model:open="isVideoModalOpen">
            <DialogContent class="max-w-3xl overflow-hidden p-0 sm:rounded-2xl">
                <DialogHeader class="p-6 pb-2">
                    <DialogTitle>Giới thiệu Aventura</DialogTitle>
                    <DialogDescription>
                        Khám phá tổng quan về nền tảng quản lý nhà hàng thông minh Aventura.
                    </DialogDescription>
                </DialogHeader>
                <div class="aspect-video w-full bg-black">
                    <iframe
                        class="h-full w-full"
                        src="https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=0"
                        title="Aventura Video Overview"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    />
                </div>
            </DialogContent>
        </Dialog>
    </AppTopbarLayout>
</template>

