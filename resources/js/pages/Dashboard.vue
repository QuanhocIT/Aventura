<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    BarChart3,
    Bot,
    Building2,
    Check,
    ChevronRight,
    Clock3,
    LineChart,
    Monitor,
    Package,
    QrCode,
    Rocket,
    ShieldCheck,
    Star,
    Users,
    X,
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

const freePlan = {
    code: 'free',
    name: 'Free',
    price: '0đ',
    cycle: '/tháng',
    maxBranches: '1 chi nhánh',
    maxTables: '10 bàn',
    maxUsers: '5 nhân viên',
    note: 'Bám đúng `subscription_plans`: free = 0, 1 branch, 10 tables.',
};

const proPlan = {
    code: 'pro',
    name: 'Pro',
    price: '499.000đ',
    cycle: '/tháng',
    maxBranches: 'Không giới hạn',
    maxTables: 'Không giới hạn',
    maxUsers: 'Không giới hạn',
    note: 'Mở khóa multi-branch, analytics, queue, audit, staff, inventory, AI insights.',
};

const featureMap = [
    {
        icon: QrCode,
        title: 'QR order',
        description:
            'Khách quét QR tại bàn -> tạo order nhanh, giảm sai sót, giảm tải nhân viên.',
    },
    {
        icon: Monitor,
        title: 'Kitchen display',
        description:
            'Đơn lên bếp theo realtime, trạng thái rõ ràng, ít nhầm lẫn.',
    },
    {
        icon: Package,
        title: 'Inventory',
        description:
            'Trừ kho theo định lượng, theo dõi nhập xuất, cảnh báo thiếu nguyên liệu.',
    },
    {
        icon: Users,
        title: 'Staff',
        description:
            'Quản lý nhân sự, ca làm, vai trò, chấm công và hỗ trợ tính lương.',
    },
    {
        icon: BarChart3,
        title: 'Analytics',
        description:
            'Theo dõi doanh thu, hiệu suất, xu hướng món bán chạy, báo cáo vận hành.',
    },
    {
        icon: Building2,
        title: 'Multi-branch',
        description:
            'Một tài khoản quản lý nhiều chi nhánh, dữ liệu tách biệt theo tenant.',
    },
    {
        icon: ShieldCheck,
        title: 'Audit log',
        description:
            'Ghi vết thao tác nhạy cảm để tra soát, giảm phụ thuộc vào tính trung thực.',
    },
    {
        icon: Bot,
        title: 'AI insights',
        description:
            'Tổng hợp tín hiệu dữ liệu để gợi ý cảnh báo, dự báo và phát hiện bất thường.',
    },
    {
        icon: Clock3,
        title: 'Queue',
        description: 'Tác vụ nặng chạy nền, giữ UI bán hàng phản hồi nhanh.',
    },
];

const faq = [
    {
        q: 'Free có đủ để chạy quán nhỏ không?',
        a: 'Có. Free phù hợp để thử vận hành thực tế với 1 chi nhánh, 10 bàn, 5 nhân viên. Đây là đúng hạn mức DB hiện có.',
    },
    {
        q: 'Pro khác gì về mặt vận hành?',
        a: 'Pro mở multi-branch, analytics, queue, audit, staff, inventory và AI insights. Đây là gói dành cho mô hình cần kiểm soát chặt.',
    },
    {
        q: 'Landing này có demo thật không?',
        a: 'Có khối demo POS mô phỏng luồng order -> bếp -> thanh toán, kèm chatbot FAQ để người dùng tự kiểm tra nhanh.',
    },
    {
        q: 'Dữ liệu gói bám schema nào?',
        a: 'Bám `subscription_plans` trong `aventura.sql`, gồm `price`, `max_branches`, `max_tables`, `max_users`, `features`.',
    },
];

const demoTabs = [
    { key: 'pos', label: 'POS' },
    { key: 'kds', label: 'Bếp' },
    { key: 'report', label: 'Báo cáo' },
] as const;

const activeDemo = ref<(typeof demoTabs)[number]['key']>('pos');

const demoState = computed(() => {
    if (activeDemo.value === 'kds') {
        return {
            title: 'Kitchen board',
            left: ['2 order chờ', '1 order đang làm', '0 trễ SLA'],
            right: [
                { label: 'Bún bò', status: 'Đang làm' },
                { label: 'Cơm gà', status: 'Sẵn sàng' },
                { label: 'Trà đào', status: 'Chờ in bill' },
            ],
        };
    }

    if (activeDemo.value === 'report') {
        return {
            title: 'Daily snapshot',
            left: [
                'Doanh thu: 12,8M',
                'Tỷ lệ hoàn thành: 98%',
                'Món bán chạy: Phở bò',
            ],
            right: [
                { label: 'Giờ cao điểm', status: '11:30 - 13:30' },
                { label: 'Cảnh báo kho', status: '2 nguyên liệu thấp' },
                { label: 'Audit', status: '1 thay đổi giá' },
            ],
        };
    }

    return {
        title: 'POS checkout',
        left: ['Bàn 12', '3 món', 'Tổng: 168.000đ'],
        right: [
            { label: 'Phở bò tái', status: 'x2' },
            { label: 'Trà chanh', status: 'x1' },
            { label: 'Thanh toán', status: 'Hoàn tất' },
        ],
    };
});
</script>

<template>
    <AppTopbarLayout>
        <Head title="Aventura | SaaS quản lý nhà hàng" />

        <section class="px-4 pt-16 lg:px-8 lg:pt-20">
            <div
                class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1.15fr_0.85fr] lg:items-center"
            >
                <div>
                    <Badge variant="outline" class="mb-5 w-fit"
                        >SaaS quản lý nhà hàng</Badge
                    >
                    <h1
                        class="max-w-2xl text-4xl font-semibold tracking-tight lg:text-6xl"
                    >
                        Quản lý quán ăn theo nhịp vận hành thật.
                    </h1>
                    <p
                        class="mt-5 max-w-2xl text-base leading-7 text-muted-foreground lg:text-lg"
                    >
                        Aventura gom order, bếp, kho, nhân sự, audit và AI vào
                        một nền tảng. Mục tiêu là giảm lệ thuộc thủ công, tăng
                        khả năng kiểm soát và cho chủ quán nhìn thấy dữ liệu rõ
                        hơn.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Button v-if="canRegister" as-child size="lg">
                            <Link :href="register()"
                                >Tạo tài khoản miễn phí</Link
                            >
                        </Button>
                        <Button as-child variant="outline" size="lg">
                            <a href="#pricing">Xem gói</a>
                        </Button>
                    </div>
                    <div
                        class="mt-8 flex flex-wrap gap-3 text-sm text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-2"
                            ><Check class="size-4 text-primary" /> Free: 1 chi
                            nhánh, 10 bàn</span
                        >
                        <span class="inline-flex items-center gap-2"
                            ><Check class="size-4 text-primary" /> Pro:
                            499.000đ/tháng</span
                        >
                        <span class="inline-flex items-center gap-2"
                            ><Check class="size-4 text-primary" /> Audit + AI +
                            queue</span
                        >
                    </div>
                </div>

                <div class="border border-border bg-card p-4 shadow-sm lg:p-5">
                    <div
                        class="flex items-center justify-between border-b border-border pb-4"
                    >
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Live demo
                            </p>
                            <p class="text-lg font-semibold">POS flow</p>
                        </div>
                        <Badge variant="secondary">Interactive</Badge>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <Button
                            v-for="tab in demoTabs"
                            :key="tab.key"
                            size="sm"
                            :variant="
                                activeDemo === tab.key ? 'default' : 'outline'
                            "
                            @click="activeDemo = tab.key"
                        >
                            {{ tab.label }}
                        </Button>
                    </div>
                    <div
                        class="mt-4 grid gap-3 rounded-md border border-border p-4"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">{{
                                demoState.title
                            }}</span>
                            <span class="text-xs text-muted-foreground"
                                >Realtime</span
                            >
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div
                                class="space-y-2 rounded-md bg-muted/40 p-3 text-sm"
                            >
                                <p
                                    v-for="item in demoState.left"
                                    :key="item"
                                    class="flex items-center gap-2"
                                >
                                    <ChevronRight class="size-4 text-primary" />
                                    {{ item }}
                                </p>
                            </div>
                            <div
                                class="space-y-2 rounded-md bg-muted/40 p-3 text-sm"
                            >
                                <p
                                    v-for="item in demoState.right"
                                    :key="item.label"
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span>{{ item.label }}</span>
                                    <Badge variant="outline">{{
                                        item.status
                                    }}</Badge>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            id="features"
            class="mt-16 border-y border-border bg-muted/30 px-4 py-16 lg:px-8"
        >
            <div class="mx-auto max-w-7xl">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold">
                        Map tính năng theo DB + vận hành
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        Không marketing mơ hồ. Đây là các lớp chức năng khớp
                        trực tiếp với schema và báo cáo kỹ thuật đã chốt.
                    </p>
                </div>
                <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Card
                        v-for="item in featureMap"
                        :key="item.title"
                        class="border-border"
                    >
                        <CardHeader class="space-y-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-md bg-primary/10"
                            >
                                <component
                                    :is="item.icon"
                                    class="size-5 text-primary"
                                />
                            </div>
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

        <section id="pricing" class="px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold">
                        Bảng giá bám `subscription_plans`
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        Free và Pro được dựng đúng theo schema DB, tránh lệch
                        giữa UI và dữ liệu hệ thống.
                    </p>
                </div>
                <div class="mt-8 grid gap-4 lg:grid-cols-2">
                    <Card class="border-border">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-2xl">{{
                                    freePlan.name
                                }}</CardTitle>
                                <Badge variant="secondary">Mặc định</Badge>
                            </div>
                            <div class="flex items-end gap-1">
                                <span class="text-4xl font-semibold">{{
                                    freePlan.price
                                }}</span>
                                <span
                                    class="pb-1 text-sm text-muted-foreground"
                                    >{{ freePlan.cycle }}</span
                                >
                            </div>
                            <CardDescription>{{
                                freePlan.note
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <p class="flex items-center gap-2">
                                <Check class="size-4 text-primary" />
                                {{ freePlan.maxBranches }}
                            </p>
                            <p class="flex items-center gap-2">
                                <Check class="size-4 text-primary" />
                                {{ freePlan.maxTables }}
                            </p>
                            <p class="flex items-center gap-2">
                                <Check class="size-4 text-primary" />
                                {{ freePlan.maxUsers }}
                            </p>
                            <p
                                class="flex items-center gap-2 text-muted-foreground"
                            >
                                <X class="size-4" /> Không mở AI insights
                            </p>
                        </CardContent>
                        <div class="px-6 pb-6">
                            <Button
                                v-if="canRegister"
                                as-child
                                variant="outline"
                                class="w-full"
                            >
                                <Link :href="register()">Dùng Free</Link>
                            </Button>
                        </div>
                    </Card>

                    <Card class="border-2 border-primary">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-2xl">{{
                                    proPlan.name
                                }}</CardTitle>
                                <Badge>Khuyến nghị</Badge>
                            </div>
                            <div class="flex items-end gap-1">
                                <span class="text-4xl font-semibold">{{
                                    proPlan.price
                                }}</span>
                                <span
                                    class="pb-1 text-sm text-muted-foreground"
                                    >{{ proPlan.cycle }}</span
                                >
                            </div>
                            <CardDescription>{{
                                proPlan.note
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <p class="flex items-center gap-2">
                                <Check class="size-4 text-primary" />
                                {{ proPlan.maxBranches }}
                            </p>
                            <p class="flex items-center gap-2">
                                <Check class="size-4 text-primary" />
                                {{ proPlan.maxTables }}
                            </p>
                            <p class="flex items-center gap-2">
                                <Check class="size-4 text-primary" />
                                {{ proPlan.maxUsers }}
                            </p>
                            <p class="flex items-center gap-2">
                                <Check class="size-4 text-primary" /> Analytics
                                + queue + audit + staff + inventory + AI
                            </p>
                        </CardContent>
                        <div class="px-6 pb-6">
                            <Button v-if="canRegister" as-child class="w-full">
                                <Link :href="register()">Chọn Pro</Link>
                            </Button>
                        </div>
                    </Card>
                </div>
            </div>
        </section>

        <section class="px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold">Chatbot FAQ</h2>
                    <p class="mt-3 text-muted-foreground">
                        Khối này thay phần mô tả dài bằng câu hỏi ngắn, đúng
                        nhịp onboarding thực tế.
                    </p>
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

        <section class="border-y border-border bg-muted/30 px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="grid gap-4 md:grid-cols-3">
                    <Card class="border-border">
                        <CardHeader>
                            <Star class="size-5 text-primary" />
                            <CardTitle class="text-base"
                                >Quản lý chuỗi</CardTitle
                            >
                            <CardDescription
                                >Multi-branch + tenant separation cho mô hình
                                nhiều chi nhánh.</CardDescription
                            >
                        </CardHeader>
                    </Card>
                    <Card class="border-border">
                        <CardHeader>
                            <LineChart class="size-5 text-primary" />
                            <CardTitle class="text-base"
                                >Minh bạch vận hành</CardTitle
                            >
                            <CardDescription
                                >Audit log + analytics giúp nhìn rõ thay đổi và
                                hiệu suất.</CardDescription
                            >
                        </CardHeader>
                    </Card>
                    <Card class="border-border">
                        <CardHeader>
                            <Rocket class="size-5 text-primary" />
                            <CardTitle class="text-base"
                                >Onboarding nhanh</CardTitle
                            >
                            <CardDescription
                                >Đăng ký xong có thể vào luồng demo, không cần
                                giải thích dài dòng.</CardDescription
                            >
                        </CardHeader>
                    </Card>
                </div>
            </div>
        </section>

        <section class="px-4 py-16 lg:px-8">
            <div
                class="mx-auto flex max-w-4xl flex-col items-center gap-5 text-center"
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
</template>
