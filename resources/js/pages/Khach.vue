<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    BarChart3,
    Bot,
    Building2,
    Check,
    ChevronRight,
    CreditCard,
    LineChart,
    Monitor,
    Package,
    Pause,
    Play,
    QrCode,
    ShieldCheck,
    Star,
    Users,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppTopbarLayout from '@/layouts/AppTopbarLayout.vue';
import { register } from '@/routes';

defineProps<{
    canRegister: boolean;
}>();

const primaryCta = 'Dùng thử miễn phí';
const secondaryCta = 'Xem demo';

const trustStats = [
    { value: '28%', label: 'giảm thời gian nhận món' },
    { value: '18%', label: 'giảm thất thoát nguyên liệu' },
    { value: '2 phút', label: 'để tạo đơn QR đầu tiên' },
];

const customerLogos = ['Bếp Mộc', 'Nami Coffee', 'Phố Lẩu', 'Cơm Nhà 24h'];

const idealUsers = [
    {
        icon: Star,
        title: 'Quán nhỏ muốn chạy gọn',
        description:
            'Nhận order nhanh, giảm ghi tay, xem doanh thu ngày ngay khi đóng ca.',
    },
    {
        icon: Building2,
        title: 'Chuỗi 3-10 chi nhánh',
        description:
            'Theo dõi nhiều điểm bán, chuẩn hóa ca làm, giá món, kho và báo cáo.',
    },
    {
        icon: QrCode,
        title: 'Mô hình cần QR, KDS, kho',
        description:
            'Khách tự gọi món, bếp nhận phiếu rõ, thanh toán xong tự trừ định lượng.',
    },
];

const featureGroups = [
    {
        icon: QrCode,
        title: 'Bán hàng',
        outcome: 'Tăng tốc phục vụ, giảm sai món.',
        items: ['QR order tại bàn', 'POS checkout', 'KDS đẩy đơn xuống bếp'],
    },
    {
        icon: Package,
        title: 'Vận hành',
        outcome: 'Giảm thất thoát, kiểm soát nhiều chi nhánh.',
        items: [
            'Kho theo định lượng',
            'Nhân sự và ca làm',
            'Audit log thao tác nhạy cảm',
        ],
    },
    {
        icon: Bot,
        title: 'AI',
        outcome: 'Ra quyết định dựa trên tín hiệu thật.',
        items: [
            'Dự báo nguyên liệu',
            'Cảnh báo bất thường',
            'Dashboard doanh thu',
        ],
    },
];

const flowSteps = [
    {
        key: 'qr',
        title: 'Quét QR',
        icon: QrCode,
        screen: 'Khách chọn món',
        detail: 'Bàn 12 gọi 2 phở bò, 1 trà chanh.',
        metric: 'Không cần nhân viên ghi tay',
    },
    {
        key: 'order',
        title: 'Tạo đơn',
        icon: Monitor,
        screen: 'POS nhận order',
        detail: 'Đơn #A128 được gom theo bàn, ghi chú món rõ ràng.',
        metric: 'Giảm sai món',
    },
    {
        key: 'kds',
        title: 'Đẩy bếp',
        icon: Monitor,
        screen: 'KDS bếp nóng',
        detail: 'Bếp thấy thứ tự món, trạng thái chờ, đang làm, sẵn sàng.',
        metric: 'Phục vụ nhanh hơn',
    },
    {
        key: 'pay',
        title: 'Thanh toán',
        icon: CreditCard,
        screen: 'Thu ngân chốt bill',
        detail: 'Tiền mặt, chuyển khoản hoặc thẻ đều về cùng báo cáo ca.',
        metric: 'Đối soát dễ hơn',
    },
    {
        key: 'stock',
        title: 'Trừ kho',
        icon: Package,
        screen: 'Kho cập nhật',
        detail: 'Thịt bò, bánh phở, trà được trừ theo công thức món.',
        metric: 'Giảm thất thoát',
    },
];

const activeFlowIndex = ref(0);
const isDemoRunning = ref(false);
let demoTimer: ReturnType<typeof setInterval> | null = null;

const activeFlow = computed(() => flowSteps[activeFlowIndex.value]);

const nextDemoStep = () => {
    activeFlowIndex.value = (activeFlowIndex.value + 1) % flowSteps.length;
};

const stopDemo = () => {
    isDemoRunning.value = false;
    if (demoTimer) {
        clearInterval(demoTimer);
        demoTimer = null;
    }
};

const toggleDemo = () => {
    if (isDemoRunning.value) {
        stopDemo();
        return;
    }

    isDemoRunning.value = true;
    demoTimer = setInterval(nextDemoStep, 2200);
};

onUnmounted(stopDemo);

const plans = [
    {
        code: 'free',
        name: 'Free',
        price: '0đ',
        cycle: '/tháng',
        fit: 'Phù hợp quán mới thử số hóa',
        outcome: 'Tạo QR, nhận order, xem vận hành cơ bản.',
        features: ['1 chi nhánh', '10 bàn', '5 nhân viên', '500 MB lưu trữ'],
        isRecommended: false,
    },
    {
        code: 'pro',
        name: 'Pro',
        price: '499.000đ',
        cycle: '/tháng',
        fit: 'Phù hợp quán đang tăng trưởng',
        outcome: 'Mở đầy đủ POS, KDS, kho, nhân sự, audit và AI.',
        features: [
            'Không giới hạn bàn',
            'Không giới hạn nhân viên',
            'AI insights',
            'Realtime analytics',
        ],
        isRecommended: true,
    },
    {
        code: 'max',
        name: 'Max',
        price: '999.000đ',
        cycle: '/tháng',
        fit: 'Phù hợp chuỗi 3-10 chi nhánh',
        outcome: 'Chuẩn hóa vận hành nhiều điểm bán, kiểm soát kho tốt hơn.',
        features: [
            'Tối đa 10 chi nhánh',
            '300 bàn',
            '80 nhân viên',
            '50 GB lưu trữ',
        ],
        isRecommended: false,
    },
    {
        code: 'ultra',
        name: 'Ultra',
        price: '1.999.000đ',
        cycle: '/tháng',
        fit: 'Phù hợp doanh nghiệp lớn',
        outcome: 'Mở rộng không giới hạn, ưu tiên dữ liệu, báo cáo và hỗ trợ.',
        features: [
            'Không giới hạn chi nhánh',
            '200 GB lưu trữ',
            'Rate limit cao',
            'Audit nâng cao',
        ],
        isRecommended: false,
    },
];

const testimonials = [
    {
        quote: 'Trước đây cuối ca phải đối sổ thủ công. Giờ đơn, bếp, thanh toán và kho khớp nhau rõ hơn.',
        name: 'Anh Minh',
        role: 'Chủ quán Bếp Mộc',
    },
    {
        quote: 'QR order giúp nhân viên tập trung phục vụ thay vì chạy đi ghi món. Giờ cao điểm đỡ nghẽn hẳn.',
        name: 'Chị Lan',
        role: 'Quản lý Nami Coffee',
    },
];

const faq = [
    {
        q: 'Quán nhỏ có cần hệ thống phức tạp không?',
        a: 'Không. Gói Free đủ để thử luồng QR order, POS và báo cáo cơ bản. Khi quán đông hơn, nâng gói để mở kho, audit, AI và nhiều chi nhánh.',
    },
    {
        q: 'Aventura giúp giảm sai món bằng cách nào?',
        a: 'Khách gọi món qua QR, đơn vào POS rồi đẩy thẳng xuống KDS. Nhân viên không phải chép lại nhiều lần nên giảm lỗi nghe nhầm, ghi nhầm, chuyển nhầm.',
    },
    {
        q: 'Có kiểm soát thất thoát kho không?',
        a: 'Có. Khi hóa đơn thanh toán, hệ thống trừ kho theo công thức món. Chủ quán dễ phát hiện nguyên liệu hao hụt bất thường hoặc nhập xuất không khớp.',
    },
    {
        q: 'Chuỗi nhiều chi nhánh xem báo cáo thế nào?',
        a: 'Quản lý có thể xem doanh thu, đơn hàng, kho và hiệu suất theo từng chi nhánh hoặc toàn chuỗi, phù hợp mô hình 3-10 chi nhánh đang cần chuẩn hóa.',
    },
];
</script>

<template>
    <AppTopbarLayout>
        <Head title="Aventura | SaaS quản lý nhà hàng" />

        <section class="px-4 pt-12 pb-10 lg:px-8 lg:pt-16">
            <div
                class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[1.02fr_0.98fr] lg:items-center"
            >
                <div>
                    <Badge variant="outline" class="mb-4 w-fit"
                        >SaaS quản lý nhà hàng</Badge
                    >
                    <h1
                        class="max-w-2xl text-4xl font-semibold tracking-tight lg:text-6xl"
                    >
                        Bán nhanh hơn, kiểm soát quán rõ hơn.
                    </h1>
                    <p
                        class="mt-5 max-w-xl text-base leading-7 text-muted-foreground lg:text-lg"
                    >
                        Aventura gom QR order, POS, KDS, kho, nhân sự và báo cáo
                        vào một nền tảng để giảm sai món, giảm thất thoát và
                        tăng tốc phục vụ.
                    </p>
                    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                        <Button v-if="canRegister" as-child size="lg">
                            <Link :href="register()">{{ primaryCta }}</Link>
                        </Button>
                        <Button as-child variant="outline" size="lg">
                            <a href="#demo">{{ secondaryCta }}</a>
                        </Button>
                    </div>
                    <div class="mt-8 grid gap-3 sm:grid-cols-3">
                        <div
                            v-for="stat in trustStats"
                            :key="stat.label"
                            class="border border-border bg-card p-4"
                        >
                            <p class="text-2xl font-semibold">
                                {{ stat.value }}
                            </p>
                            <p
                                class="mt-1 text-xs leading-5 text-muted-foreground"
                            >
                                {{ stat.label }}
                            </p>
                        </div>
                    </div>
                </div>

                <form
                    action="/register"
                    method="get"
                    class="border border-border bg-card p-5 shadow-sm"
                >
                    <div
                        class="flex items-center justify-between gap-3 border-b border-border pb-4"
                    >
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Đăng ký nhanh
                            </p>
                            <h2 class="text-xl font-semibold">
                                Nhận tài khoản dùng thử
                            </h2>
                        </div>
                        <Badge variant="secondary">Miễn phí</Badge>
                    </div>
                    <div class="mt-4 grid gap-3">
                        <label class="grid gap-1.5 text-sm font-medium">
                            Tên quán
                            <input
                                name="restaurant"
                                class="h-11 border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                                placeholder="VD: Bếp Nhà An"
                            />
                        </label>
                        <label class="grid gap-1.5 text-sm font-medium">
                            Số điện thoại
                            <input
                                name="phone"
                                class="h-11 border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                                placeholder="090..."
                            />
                        </label>
                        <label class="grid gap-1.5 text-sm font-medium">
                            Nhu cầu chính
                            <select
                                name="need"
                                class="h-11 border border-input bg-background px-3 text-sm outline-none focus:border-primary"
                            >
                                <option>QR order và POS</option>
                                <option>KDS và tốc độ ra món</option>
                                <option>Kho và chống thất thoát</option>
                                <option>Quản lý nhiều chi nhánh</option>
                            </select>
                        </label>
                        <Button type="submit" size="lg" class="mt-1 w-full">{{
                            primaryCta
                        }}</Button>
                    </div>
                    <p class="mt-3 text-xs leading-5 text-muted-foreground">
                        Form ngắn để giảm rơi funnel. Có thể hoàn tất hồ sơ chi
                        tiết sau khi vào hệ thống.
                    </p>
                </form>
            </div>
        </section>

        <section class="border-y border-border bg-muted/30 px-4 py-8 lg:px-8">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
            >
                <div
                    class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <ShieldCheck class="size-4 text-primary" />
                    <span
                        >Được tin dùng bởi các mô hình F&B đang cần chuẩn hóa
                        vận hành</span
                    >
                </div>
                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                    <span
                        v-for="logo in customerLogos"
                        :key="logo"
                        class="border border-border bg-background px-4 py-2 text-center text-sm font-semibold"
                    >
                        {{ logo }}
                    </span>
                </div>
            </div>
        </section>

        <section class="px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="max-w-2xl">
                    <Badge variant="outline" class="mb-3">Ai nên dùng</Badge>
                    <h2 class="text-3xl font-semibold">
                        Thiết kế cho quán cần vận hành thật, không chỉ ghi nhận
                        đơn.
                    </h2>
                </div>
                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <Card
                        v-for="item in idealUsers"
                        :key="item.title"
                        class="border-border"
                    >
                        <CardHeader>
                            <component
                                :is="item.icon"
                                class="size-5 text-primary"
                            />
                            <CardTitle class="text-base">{{
                                item.title
                            }}</CardTitle>
                            <CardDescription>{{
                                item.description
                            }}</CardDescription>
                        </CardHeader>
                    </Card>
                </div>
            </div>
        </section>

        <section
            id="demo"
            class="border-y border-border bg-muted/30 px-4 py-16 lg:px-8"
        >
            <div
                class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-start"
            >
                <div>
                    <Badge variant="outline" class="mb-3">Demo vận hành</Badge>
                    <h2 class="text-3xl font-semibold">
                        Luồng thật: QR -> đơn -> bếp -> thanh toán -> kho.
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        Demo mô phỏng giao diện POS, KDS và dashboard doanh thu
                        để người xem hiểu ngay hệ thống giúp gì trong giờ cao
                        điểm.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <button
                            v-for="(step, index) in flowSteps"
                            :key="step.key"
                            class="flex items-center gap-2 border px-3 py-2 text-sm transition"
                            :class="
                                activeFlowIndex === index
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-background text-muted-foreground hover:text-foreground'
                            "
                            @click="activeFlowIndex = index"
                        >
                            <component :is="step.icon" class="size-4" />
                            {{ step.title }}
                        </button>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <Button type="button" @click="toggleDemo">
                            <Pause v-if="isDemoRunning" class="mr-2 size-4" />
                            <Play v-else class="mr-2 size-4" />
                            {{ isDemoRunning ? 'Tạm dừng' : 'Tự chạy' }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="nextDemoStep"
                        >
                            Bước tiếp theo
                            <ChevronRight class="ml-2 size-4" />
                        </Button>
                    </div>
                </div>

                <div class="border border-border bg-card p-4 shadow-sm">
                    <div
                        class="flex items-center justify-between border-b border-border pb-3"
                    >
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ activeFlow.title }}
                            </p>
                            <h3 class="text-lg font-semibold">
                                {{ activeFlow.screen }}
                            </h3>
                        </div>
                        <Badge variant="secondary">Live state</Badge>
                    </div>
                    <div class="mt-4 grid gap-4 md:grid-cols-[0.9fr_1.1fr]">
                        <div
                            class="space-y-3 border border-border bg-background p-3"
                        >
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span>Bàn 12</span>
                                <Badge variant="outline">#A128</Badge>
                            </div>
                            <div class="space-y-2 text-sm">
                                <p class="flex justify-between">
                                    <span>Phở bò tái</span><strong>x2</strong>
                                </p>
                                <p class="flex justify-between">
                                    <span>Trà chanh</span><strong>x1</strong>
                                </p>
                                <p class="flex justify-between">
                                    <span>Ghi chú</span><span>ít đá</span>
                                </p>
                            </div>
                            <div class="border-t border-border pt-3 text-sm">
                                <p class="flex justify-between font-semibold">
                                    <span>Tổng tạm tính</span
                                    ><span>168.000đ</span>
                                </p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="border border-border bg-background p-3">
                                <div
                                    class="mb-2 flex items-center gap-2 text-sm font-semibold"
                                >
                                    <Monitor class="size-4 text-primary" /> KDS
                                </div>
                                <div class="grid gap-2 text-xs sm:grid-cols-3">
                                    <span class="border border-border p-2"
                                        >Chờ: 2</span
                                    >
                                    <span class="border border-border p-2"
                                        >Đang làm: 1</span
                                    >
                                    <span class="border border-border p-2"
                                        >Sẵn sàng: 0</span
                                    >
                                </div>
                            </div>
                            <div class="border border-border bg-background p-3">
                                <div
                                    class="mb-2 flex items-center gap-2 text-sm font-semibold"
                                >
                                    <BarChart3 class="size-4 text-primary" />
                                    Revenue dashboard
                                </div>
                                <div class="grid gap-2 text-xs sm:grid-cols-3">
                                    <span class="border border-border p-2"
                                        >Ca trưa: 12,8M</span
                                    >
                                    <span class="border border-border p-2"
                                        >Bill: 86</span
                                    >
                                    <span class="border border-border p-2"
                                        >Kho thấp: 2</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="mt-4 border border-primary/30 bg-primary/5 p-3 text-sm"
                    >
                        <p class="font-semibold">{{ activeFlow.metric }}</p>
                        <p class="mt-1 text-muted-foreground">
                            {{ activeFlow.detail }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="max-w-2xl">
                    <Badge variant="outline" class="mb-3">Feature map</Badge>
                    <h2 class="text-3xl font-semibold">
                        3 nhóm tính năng gắn với lợi ích kinh doanh.
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        Thay vì liệt kê kỹ thuật rời rạc, Aventura gom chức năng
                        theo mục tiêu: bán nhanh, vận hành chắc, ra quyết định
                        tốt hơn.
                    </p>
                </div>
                <div class="mt-8 grid gap-4 lg:grid-cols-3">
                    <Card
                        v-for="group in featureGroups"
                        :key="group.title"
                        class="border-border"
                    >
                        <CardHeader>
                            <component
                                :is="group.icon"
                                class="size-6 text-primary"
                            />
                            <CardTitle>{{ group.title }}</CardTitle>
                            <CardDescription>{{
                                group.outcome
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <p
                                v-for="item in group.items"
                                :key="item"
                                class="flex items-center gap-2"
                            >
                                <Check class="size-4 text-emerald-500" />
                                <span>{{ item }}</span>
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>

        <section
            id="pricing"
            class="border-y border-border bg-muted/30 px-4 py-16 lg:px-8"
        >
            <div class="mx-auto max-w-7xl">
                <div class="max-w-2xl">
                    <Badge variant="outline" class="mb-3">Pricing</Badge>
                    <h2 class="text-3xl font-semibold">
                        Chọn gói theo nhu cầu vận hành.
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        Gói không chỉ khác nhau ở hạn mức kỹ thuật. Mỗi gói
                        tương ứng một giai đoạn phát triển của quán.
                    </p>
                </div>
                <div
                    class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
                >
                    <Card
                        v-for="plan in plans"
                        :key="plan.code"
                        class="flex flex-col justify-between border-border"
                        :class="{
                            'border-2 border-primary shadow-sm':
                                plan.isRecommended,
                        }"
                    >
                        <CardHeader>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <CardTitle class="text-2xl">{{
                                    plan.name
                                }}</CardTitle>
                                <Badge v-if="plan.isRecommended"
                                    >Khuyến nghị</Badge
                                >
                            </div>
                            <div class="mt-2 flex items-end gap-1">
                                <span class="text-3xl font-extrabold">{{
                                    plan.price
                                }}</span>
                                <span
                                    class="pb-1 text-xs text-muted-foreground"
                                    >{{ plan.cycle }}</span
                                >
                            </div>
                            <CardDescription class="min-h-[64px]">
                                <strong class="block text-foreground">{{
                                    plan.fit
                                }}</strong>
                                {{ plan.outcome }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="flex-grow space-y-2 text-sm">
                            <p
                                v-for="feat in plan.features"
                                :key="feat"
                                class="flex items-center gap-2"
                            >
                                <Check class="size-4 text-emerald-500" />
                                <span>{{ feat }}</span>
                            </p>
                        </CardContent>
                        <div class="px-6 pb-6">
                            <Button
                                v-if="canRegister"
                                as-child
                                :variant="
                                    plan.isRecommended ? 'default' : 'outline'
                                "
                                class="w-full"
                            >
                                <Link
                                    :href="register() + '?plan=' + plan.code"
                                    >{{ primaryCta }}</Link
                                >
                            </Button>
                        </div>
                    </Card>
                </div>
            </div>
        </section>

        <section class="px-4 py-16 lg:px-8">
            <div
                class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-start"
            >
                <div>
                    <Badge variant="outline" class="mb-3">Case study</Badge>
                    <h2 class="text-3xl font-semibold">
                        Từ order rời rạc sang dữ liệu khớp toàn ca.
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        Một quán ăn 4 chi nhánh dùng QR order và KDS để giảm
                        nghẽn giờ trưa. Sau 30 ngày, thời gian nhận món giảm, ca
                        trưởng dễ đối soát thanh toán và kho cuối ngày hơn.
                    </p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="border border-border p-3">
                            <strong>4</strong>
                            <p class="text-xs text-muted-foreground">
                                chi nhánh
                            </p>
                        </div>
                        <div class="border border-border p-3">
                            <strong>1.800+</strong>
                            <p class="text-xs text-muted-foreground">
                                đơn/tháng
                            </p>
                        </div>
                        <div class="border border-border p-3">
                            <strong>30 ngày</strong>
                            <p class="text-xs text-muted-foreground">
                                để chuẩn hóa
                            </p>
                        </div>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <Card
                        v-for="item in testimonials"
                        :key="item.name"
                        class="border-border"
                    >
                        <CardHeader>
                            <LineChart class="size-5 text-primary" />
                            <CardDescription class="text-sm leading-6"
                                >“{{ item.quote }}”</CardDescription
                            >
                            <CardTitle class="text-base">{{
                                item.name
                            }}</CardTitle>
                            <p class="text-xs text-muted-foreground">
                                {{ item.role }}
                            </p>
                        </CardHeader>
                    </Card>
                </div>
            </div>
        </section>

        <section class="border-y border-border bg-muted/30 px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="max-w-2xl">
                    <Badge variant="outline" class="mb-3">FAQ</Badge>
                    <h2 class="text-3xl font-semibold">
                        Trả lời theo nỗi đau thật của chủ quán.
                    </h2>
                </div>
                <div class="mt-8 grid gap-4 lg:grid-cols-2">
                    <Card
                        v-for="item in faq"
                        :key="item.q"
                        class="border-border"
                    >
                        <CardHeader>
                            <CardTitle class="text-base">{{
                                item.q
                            }}</CardTitle>
                            <CardDescription>{{ item.a }}</CardDescription>
                        </CardHeader>
                    </Card>
                </div>
            </div>
        </section>

        <section class="px-4 py-16 lg:px-8">
            <div
                class="mx-auto flex max-w-4xl flex-col items-center gap-5 text-center"
            >
                <Users class="size-8 text-primary" />
                <h2 class="text-3xl font-semibold">
                    Sẵn sàng thử với dữ liệu quán của bạn?
                </h2>
                <p class="max-w-2xl text-muted-foreground">
                    Bắt đầu bằng gói Free, xem demo luồng thật, rồi nâng cấp khi
                    cần quản lý kho, nhiều chi nhánh và AI.
                </p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <Button v-if="canRegister" as-child size="lg">
                        <Link :href="register()">{{ primaryCta }}</Link>
                    </Button>
                    <Button as-child variant="outline" size="lg">
                        <a href="#demo">{{ secondaryCta }}</a>
                    </Button>
                </div>
            </div>
        </section>
    </AppTopbarLayout>
</template>
