<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BarChart3, Building2, Check, ClipboardList, Monitor, Package, QrCode, Rocket, Settings2, Users, X } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppTopbarLayout from '@/layouts/AppTopbarLayout.vue';
import { register } from '@/routes';

defineProps<{
    canRegister: boolean;
}>();

const features = [
    {
        icon: QrCode,
        title: 'Đặt món qua QR',
        description: 'Khách quét mã QR tại bàn, tự gọi món trực tiếp. Không cần nhân viên order, giảm sai sót và tăng tốc độ phục vụ.',
    },
    {
        icon: Monitor,
        title: 'Màn hình bếp (KDS)',
        description: 'Đơn hàng tự động gửi tới bếp theo thời gian thực. Bếp cập nhật trạng thái, thu ngân nhận thông báo ngay lập tức.',
    },
    {
        icon: Package,
        title: 'Quản lý kho hàng',
        description: 'Tự động trừ kho theo công thức định lượng khi bán. Cảnh báo hết nguyên liệu, theo dõi giá vốn theo thời gian thực.',
    },
    {
        icon: Users,
        title: 'Quản lý nhân sự',
        description: 'Phân ca làm việc, chấm công tự động, tính lương cuối tháng. Quản lý vi phạm và thưởng phạt minh bạch.',
    },
    {
        icon: BarChart3,
        title: 'Phân tích doanh thu',
        description: 'Báo cáo doanh thu realtime theo ngày, tuần, tháng. Biểu đồ trực quan, xuất Excel/PDF chỉ một cú nhấp.',
    },
    {
        icon: Building2,
        title: 'Nhiều chi nhánh',
        description: 'Quản lý toàn bộ chuỗi nhà hàng từ một tài khoản duy nhất. Dữ liệu tách biệt hoàn toàn giữa các chi nhánh.',
    },
];

const steps = [
    {
        icon: ClipboardList,
        number: '01',
        title: 'Đăng ký tài khoản',
        description: 'Tạo tài khoản miễn phí trong 30 giây. Không cần thẻ tín dụng, không phí ẩn.',
    },
    {
        icon: Settings2,
        number: '02',
        title: 'Thiết lập hệ thống',
        description: 'Thêm menu, khu vực bàn ăn, nhân viên và kho hàng. Có hướng dẫn từng bước rõ ràng.',
    },
    {
        icon: Rocket,
        number: '03',
        title: 'Bắt đầu bán hàng',
        description: 'Tiếp nhận đơn, quản lý bếp, thanh toán và xem báo cáo ngay trong ngày đầu tiên.',
    },
];

const freePlanFeatures = [
    { included: true, label: '1 chi nhánh' },
    { included: true, label: 'Quản lý đơn hàng cơ bản' },
    { included: true, label: 'QR gọi món tại bàn' },
    { included: true, label: 'Tối đa 10 bàn ăn' },
    { included: false, label: 'Màn hình bếp (KDS)' },
    { included: false, label: 'Quản lý kho hàng' },
    { included: false, label: 'Báo cáo nâng cao' },
];

const proPlanFeatures = [
    { label: 'Không giới hạn chi nhánh' },
    { label: 'Màn hình bếp realtime (KDS)' },
    { label: 'Quản lý kho & nguyên liệu' },
    { label: 'Quản lý nhân sự & lịch làm' },
    { label: 'Tính lương tự động' },
    { label: 'Báo cáo & phân tích nâng cao' },
    { label: 'Phát hiện gian lận (AI)' },
];
</script>

<template>
    <AppTopbarLayout>
    <Head title="Trang chủ" />

    <!-- HERO -->
    <section class="relative overflow-hidden px-4 pt-20 pb-24 lg:pt-32 lg:pb-40">
        <div class="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-br from-primary/5 via-transparent to-transparent" />

        <div class="mx-auto max-w-7xl text-center lg:px-8">
            <Badge variant="outline" class="mb-6">Nền tảng quản lý nhà hàng SaaS</Badge>

            <h1 class="mb-6 text-4xl font-bold tracking-tight lg:text-6xl">
                Quản lý nhà hàng<br />
                <span class="text-primary">thông minh hơn</span>
            </h1>

            <p class="mx-auto mb-10 max-w-2xl text-lg text-muted-foreground">
                Aventura giúp bạn quản lý đơn hàng, kho hàng, nhân sự và phân tích doanh thu — tất cả trong một nền tảng.
                Tăng doanh thu, giảm chi phí vận hành ngay hôm nay.
            </p>

            <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
                <Button v-if="canRegister" as-child size="lg">
                    <Link :href="register()">Dùng thử miễn phí 14 ngày</Link>
                </Button>
                <Button as-child variant="outline" size="lg">
                    <a href="#tinh-nang">Xem tính năng</a>
                </Button>
            </div>

            <!-- Stats -->
            <div class="mx-auto mt-16 grid max-w-lg grid-cols-3 gap-8">
                <div class="text-center">
                    <p class="text-3xl font-bold">2,500+</p>
                    <p class="text-sm text-muted-foreground">Nhà hàng tin dùng</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl font-bold">99.9%</p>
                    <p class="text-sm text-muted-foreground">Uptime đảm bảo</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl font-bold">4.9★</p>
                    <p class="text-sm text-muted-foreground">Đánh giá khách hàng</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FEATURES -->
    <section id="tinh-nang" class="bg-muted/30 py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <div class="mb-16 text-center">
                <h2 class="mb-4 text-3xl font-bold lg:text-4xl">Tất cả những gì bạn cần</h2>
                <p class="mx-auto max-w-xl text-muted-foreground">Từ gọi món đến báo cáo tài chính, Aventura lo hết.</p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="feature in features"
                    :key="feature.title"
                    class="border border-border transition-shadow hover:shadow-md"
                >
                    <CardHeader>
                        <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <component :is="feature.icon" class="size-5 text-primary" />
                        </div>
                        <CardTitle class="text-lg">{{ feature.title }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <CardDescription class="text-sm leading-relaxed">{{ feature.description }}</CardDescription>
                    </CardContent>
                </Card>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section id="cach-hoat-dong" class="py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <div class="mb-16 text-center">
                <h2 class="mb-4 text-3xl font-bold lg:text-4xl">Bắt đầu chỉ trong 3 bước</h2>
                <p class="text-muted-foreground">Thiết lập nhanh, vận hành ngay trong ngày đầu tiên.</p>
            </div>

            <div class="grid grid-cols-1 gap-10 md:grid-cols-3">
                <div v-for="step in steps" :key="step.number" class="flex flex-col items-center text-center">
                    <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                        <component :is="step.icon" class="size-7 text-primary" />
                    </div>
                    <span class="mb-2 text-4xl font-black text-primary/20">{{ step.number }}</span>
                    <h3 class="mb-2 text-lg font-semibold">{{ step.title }}</h3>
                    <p class="text-sm text-muted-foreground">{{ step.description }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- PRICING -->
    <section id="bang-gia" class="bg-muted/30 py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <div class="mb-16 text-center">
                <h2 class="mb-4 text-3xl font-bold lg:text-4xl">Bảng giá minh bạch</h2>
                <p class="text-muted-foreground">Không phí ẩn. Hủy bất cứ lúc nào.</p>
            </div>

            <div class="mx-auto grid max-w-3xl grid-cols-1 gap-8 md:grid-cols-2">
                <!-- Free Plan -->
                <Card class="border border-border">
                    <CardHeader>
                        <Badge variant="secondary" class="mb-2 w-fit">Miễn phí</Badge>
                        <CardTitle class="text-2xl">0₫ / tháng</CardTitle>
                        <CardDescription>Phù hợp để thử nghiệm hệ thống</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-3">
                            <li
                                v-for="item in freePlanFeatures"
                                :key="item.label"
                                class="flex items-start gap-2 text-sm"
                                :class="item.included ? '' : 'text-muted-foreground'"
                            >
                                <Check v-if="item.included" class="mt-0.5 size-4 shrink-0 text-primary" />
                                <X v-else class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                {{ item.label }}
                            </li>
                        </ul>
                    </CardContent>
                    <CardFooter>
                        <Button v-if="canRegister" as-child variant="outline" class="w-full">
                            <Link :href="register()">Bắt đầu miễn phí</Link>
                        </Button>
                    </CardFooter>
                </Card>

                <!-- Pro Plan -->
                <Card class="relative border-2 border-primary">
                    <div class="absolute -top-3.5 left-1/2 -translate-x-1/2">
                        <Badge class="bg-primary text-primary-foreground">Phổ biến nhất</Badge>
                    </div>
                    <CardHeader>
                        <Badge class="mb-2 w-fit">Pro</Badge>
                        <CardTitle class="text-2xl">299,000₫ / tháng</CardTitle>
                        <CardDescription>Dành cho nhà hàng chuyên nghiệp</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-3">
                            <li v-for="item in proPlanFeatures" :key="item.label" class="flex items-start gap-2 text-sm">
                                <Check class="mt-0.5 size-4 shrink-0 text-primary" />
                                {{ item.label }}
                            </li>
                        </ul>
                    </CardContent>
                    <CardFooter>
                        <Button v-if="canRegister" as-child class="w-full">
                            <Link :href="register()">Dùng thử 14 ngày miễn phí</Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </section>

    <!-- FINAL CTA -->
    <section class="py-20 lg:py-28">
        <div class="mx-auto max-w-3xl px-4 text-center">
            <h2 class="mb-6 text-3xl font-bold lg:text-4xl">Sẵn sàng nâng cấp nhà hàng của bạn?</h2>
            <p class="mb-10 text-muted-foreground">
                Tham gia cùng 2,500+ nhà hàng đang sử dụng Aventura. Không cần thẻ tín dụng. Bắt đầu ngay hôm nay.
            </p>
            <Button v-if="canRegister" as-child size="lg">
                <Link :href="register()">Tạo tài khoản miễn phí</Link>
            </Button>
        </div>
    </section>
    </AppTopbarLayout>
</template>
