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
    price: '0Ä‘',
    cycle: '/thÃ¡ng',
    maxBranches: '1 chi nhÃ¡nh',
    maxTables: '10 bÃ n',
    maxUsers: '5 nhÃ¢n viÃªn',
    note: 'BÃ¡m Ä‘Ãºng `subscription_plans`: free = 0, 1 branch, 10 tables.',
};

const proPlan = {
    code: 'pro',
    name: 'Pro',
    price: '499.000Ä‘',
    cycle: '/thÃ¡ng',
    maxBranches: 'KhÃ´ng giá»›i háº¡n',
    maxTables: 'KhÃ´ng giá»›i háº¡n',
    maxUsers: 'KhÃ´ng giá»›i háº¡n',
    note: 'Má»Ÿ khÃ³a multi-branch, analytics, queue, audit, staff, inventory, AI insights.',
};

const featureMap = [
    {
        icon: QrCode,
        title: 'QR order',
        description:
            'KhÃ¡ch quÃ©t QR táº¡i bÃ n -> táº¡o order nhanh, giáº£m sai sÃ³t, giáº£m táº£i nhÃ¢n viÃªn.',
    },
    {
        icon: Monitor,
        title: 'Kitchen display',
        description:
            'ÄÆ¡n lÃªn báº¿p theo realtime, tráº¡ng thÃ¡i rÃµ rÃ ng, Ã­t nháº§m láº«n.',
    },
    {
        icon: Package,
        title: 'Inventory',
        description:
            'Trá»« kho theo Ä‘á»‹nh lÆ°á»£ng, theo dÃµi nháº­p xuáº¥t, cáº£nh bÃ¡o thiáº¿u nguyÃªn liá»‡u.',
    },
    {
        icon: Users,
        title: 'Staff',
        description:
            'Quáº£n lÃ½ nhÃ¢n sá»±, ca lÃ m, vai trÃ², cháº¥m cÃ´ng vÃ  há»— trá»£ tÃ­nh lÆ°Æ¡ng.',
    },
    {
        icon: BarChart3,
        title: 'Analytics',
        description:
            'Theo dÃµi doanh thu, hiá»‡u suáº¥t, xu hÆ°á»›ng mÃ³n bÃ¡n cháº¡y, bÃ¡o cÃ¡o váº­n hÃ nh.',
    },
    {
        icon: Building2,
        title: 'Multi-branch',
        description:
            'Má»™t tÃ i khoáº£n quáº£n lÃ½ nhiá»u chi nhÃ¡nh, dá»¯ liá»‡u tÃ¡ch biá»‡t theo tenant.',
    },
    {
        icon: ShieldCheck,
        title: 'Audit log',
        description:
            'Ghi váº¿t thao tÃ¡c nháº¡y cáº£m Ä‘á»ƒ tra soÃ¡t, giáº£m phá»¥ thuá»™c vÃ o tÃ­nh trung thá»±c.',
    },
    {
        icon: Bot,
        title: 'AI insights',
        description:
            'Tá»•ng há»£p tÃ­n hiá»‡u dá»¯ liá»‡u Ä‘á»ƒ gá»£i Ã½ cáº£nh bÃ¡o, dá»± bÃ¡o vÃ  phÃ¡t hiá»‡n báº¥t thÆ°á»ng.',
    },
    {
        icon: Clock3,
        title: 'Queue',
        description: 'TÃ¡c vá»¥ náº·ng cháº¡y ná»n, giá»¯ UI bÃ¡n hÃ ng pháº£n há»“i nhanh.',
    },
];

const faq = [
    {
        q: 'Free cÃ³ Ä‘á»§ Ä‘á»ƒ cháº¡y quÃ¡n nhá» khÃ´ng?',
        a: 'CÃ³. Free phÃ¹ há»£p Ä‘á»ƒ thá»­ váº­n hÃ nh thá»±c táº¿ vá»›i 1 chi nhÃ¡nh, 10 bÃ n, 5 nhÃ¢n viÃªn. ÄÃ¢y lÃ  Ä‘Ãºng háº¡n má»©c DB hiá»‡n cÃ³.',
    },
    {
        q: 'Pro khÃ¡c gÃ¬ vá» máº·t váº­n hÃ nh?',
        a: 'Pro má»Ÿ multi-branch, analytics, queue, audit, staff, inventory vÃ  AI insights. ÄÃ¢y lÃ  gÃ³i dÃ nh cho mÃ´ hÃ¬nh cáº§n kiá»ƒm soÃ¡t cháº·t.',
    },
    {
        q: 'Landing nÃ y cÃ³ demo tháº­t khÃ´ng?',
        a: 'CÃ³ khá»‘i demo POS mÃ´ phá»ng luá»“ng order -> báº¿p -> thanh toÃ¡n, kÃ¨m chatbot FAQ Ä‘á»ƒ ngÆ°á»i dÃ¹ng tá»± kiá»ƒm tra nhanh.',
    },
    {
        q: 'Dá»¯ liá»‡u gÃ³i bÃ¡m schema nÃ o?',
        a: 'BÃ¡m `subscription_plans` trong `aventura.sql`, gá»“m `price`, `max_branches`, `max_tables`, `max_users`, `features`.',
    },
];

const demoTabs = [
    { key: 'pos', label: 'POS' },
    { key: 'kds', label: 'Báº¿p' },
    { key: 'report', label: 'BÃ¡o cÃ¡o' },
] as const;

const activeDemo = ref<(typeof demoTabs)[number]['key']>('pos');

const demoState = computed(() => {
    if (activeDemo.value === 'kds') {
        return {
            title: 'Kitchen board',
            left: ['2 order chá»', '1 order Ä‘ang lÃ m', '0 trá»… SLA'],
            right: [
                { label: 'BÃºn bÃ²', status: 'Äang lÃ m' },
                { label: 'CÆ¡m gÃ ', status: 'Sáºµn sÃ ng' },
                { label: 'TrÃ  Ä‘Ã o', status: 'Chá» in bill' },
            ],
        };
    }

    if (activeDemo.value === 'report') {
        return {
            title: 'Daily snapshot',
            left: [
                'Doanh thu: 12,8M',
                'Tá»· lá»‡ hoÃ n thÃ nh: 98%',
                'MÃ³n bÃ¡n cháº¡y: Phá»Ÿ bÃ²',
            ],
            right: [
                { label: 'Giá» cao Ä‘iá»ƒm', status: '11:30 - 13:30' },
                { label: 'Cáº£nh bÃ¡o kho', status: '2 nguyÃªn liá»‡u tháº¥p' },
                { label: 'Audit', status: '1 thay Ä‘á»•i giÃ¡' },
            ],
        };
    }

    return {
        title: 'POS checkout',
        left: ['BÃ n 12', '3 mÃ³n', 'Tá»•ng: 168.000Ä‘'],
        right: [
            { label: 'Phá»Ÿ bÃ² tÃ¡i', status: 'x2' },
            { label: 'TrÃ  chanh', status: 'x1' },
            { label: 'Thanh toÃ¡n', status: 'HoÃ n táº¥t' },
        ],
    };
});
</script>

<template>
    <AppTopbarLayout>
        <Head title="Aventura | SaaS quáº£n lÃ½ nhÃ  hÃ ng" />

        <section class="px-4 pt-16 lg:px-8 lg:pt-20">
            <div
                class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1.15fr_0.85fr] lg:items-center"
            >
                <div>
                    <Badge variant="outline" class="mb-5 w-fit"
                        >SaaS quáº£n lÃ½ nhÃ  hÃ ng</Badge
                    >
                    <h1
                        class="max-w-2xl text-4xl font-semibold tracking-tight lg:text-6xl"
                    >
                        Quáº£n lÃ½ quÃ¡n Äƒn theo nhá»‹p váº­n hÃ nh tháº­t.
                    </h1>
                    <p
                        class="mt-5 max-w-2xl text-base leading-7 text-muted-foreground lg:text-lg"
                    >
                        Aventura gom order, báº¿p, kho, nhÃ¢n sá»±, audit vÃ  AI vÃ o
                        má»™t ná»n táº£ng. Má»¥c tiÃªu lÃ  giáº£m lá»‡ thuá»™c thá»§ cÃ´ng, tÄƒng
                        kháº£ nÄƒng kiá»ƒm soÃ¡t vÃ  cho chá»§ quÃ¡n nhÃ¬n tháº¥y dá»¯ liá»‡u rÃµ
                        hÆ¡n.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Button v-if="canRegister" as-child size="lg">
                            <Link :href="register()"
                                >Táº¡o tÃ i khoáº£n miá»…n phÃ­</Link
                            >
                        </Button>
                        <Button as-child variant="outline" size="lg">
                            <a href="#pricing">Xem gÃ³i</a>
                        </Button>
                    </div>
                    <div
                        class="mt-8 flex flex-wrap gap-3 text-sm text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-2"
                            ><Check class="size-4 text-primary" /> Free: 1 chi
                            nhÃ¡nh, 10 bÃ n</span
                        >
                        <span class="inline-flex items-center gap-2"
                            ><Check class="size-4 text-primary" /> Pro:
                            499.000Ä‘/thÃ¡ng</span
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
                        Map tÃ­nh nÄƒng theo DB + váº­n hÃ nh
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        KhÃ´ng marketing mÆ¡ há»“. ÄÃ¢y lÃ  cÃ¡c lá»›p chá»©c nÄƒng khá»›p
                        trá»±c tiáº¿p vá»›i schema vÃ  bÃ¡o cÃ¡o ká»¹ thuáº­t Ä‘Ã£ chá»‘t.
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
                        Báº£ng giÃ¡ bÃ¡m `subscription_plans`
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        Free vÃ  Pro Ä‘Æ°á»£c dá»±ng Ä‘Ãºng theo schema DB, trÃ¡nh lá»‡ch
                        giá»¯a UI vÃ  dá»¯ liá»‡u há»‡ thá»‘ng.
                    </p>
                </div>
                <div class="mt-8 grid gap-4 lg:grid-cols-2">
                    <Card class="border-border">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-2xl">{{
                                    freePlan.name
                                }}</CardTitle>
                                <Badge variant="secondary">Máº·c Ä‘á»‹nh</Badge>
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
                                <X class="size-4" /> KhÃ´ng má»Ÿ AI insights
                            </p>
                        </CardContent>
                        <div class="px-6 pb-6">
                            <Button
                                v-if="canRegister"
                                as-child
                                variant="outline"
                                class="w-full"
                            >
                                <Link :href="register()">DÃ¹ng Free</Link>
                            </Button>
                        </div>
                    </Card>

                    <Card class="border-2 border-primary">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-2xl">{{
                                    proPlan.name
                                }}</CardTitle>
                                <Badge>Khuyáº¿n nghá»‹</Badge>
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
                                <Link :href="register()">Chá»n Pro</Link>
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
                        Khá»‘i nÃ y thay pháº§n mÃ´ táº£ dÃ i báº±ng cÃ¢u há»i ngáº¯n, Ä‘Ãºng
                        nhá»‹p onboarding thá»±c táº¿.
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
                                >Quáº£n lÃ½ chuá»—i</CardTitle
                            >
                            <CardDescription
                                >Multi-branch + tenant separation cho mÃ´ hÃ¬nh
                                nhiá»u chi nhÃ¡nh.</CardDescription
                            >
                        </CardHeader>
                    </Card>
                    <Card class="border-border">
                        <CardHeader>
                            <LineChart class="size-5 text-primary" />
                            <CardTitle class="text-base"
                                >Minh báº¡ch váº­n hÃ nh</CardTitle
                            >
                            <CardDescription
                                >Audit log + analytics giÃºp nhÃ¬n rÃµ thay Ä‘á»•i vÃ 
                                hiá»‡u suáº¥t.</CardDescription
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
                                >ÄÄƒng kÃ½ xong cÃ³ thá»ƒ vÃ o luá»“ng demo, khÃ´ng cáº§n
                                giáº£i thÃ­ch dÃ i dÃ²ng.</CardDescription
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
                <h2 class="text-3xl font-semibold">ÄÄƒng kÃ½ vÃ  thá»­ ngay</h2>
                <p class="max-w-2xl text-muted-foreground">
                    Aventura Ä‘á»§ gá»n Ä‘á»ƒ thá»­, Ä‘á»§ sÃ¢u Ä‘á»ƒ cháº¡y tháº­t. GÃ³i Free cÃ³ háº¡n
                    má»©c rÃµ; gÃ³i Pro má»Ÿ khÃ³a pháº§n cáº§n kiá»ƒm soÃ¡t.
                </p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <Button v-if="canRegister" as-child size="lg">
                        <Link :href="register()">Báº¯t Ä‘áº§u miá»…n phÃ­</Link>
                    </Button>
                    <Button as-child variant="outline" size="lg">
                        <a href="#features">Xem láº¡i tÃ­nh nÄƒng</a>
                    </Button>
                </div>
            </div>
        </section>
    </AppTopbarLayout>
</template>
