<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    X,
    Store,
    Utensils,
    Building2,
    Sparkles,
    ChevronDown,
    Headphones,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppTopbarLayout from '@/layouts/AppTopbarLayout.vue';
import BareLayout from '@/layouts/BareLayout.vue';

defineOptions({
    layout: BareLayout,
});

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
    canRegister?: boolean;
    plans?: DbPlan[];
}>();

type PlanKey = 'free' | 'basic' | 'pro' | 'enterprise';
const selectedPlanKey = ref<PlanKey>('pro');

const getPlan = (key: PlanKey): DbPlan | undefined => {
    const code = key === 'basic' ? 'starter' : key;

    return props.plans?.find((p) => p.code === code || p.code === key);
};

const getPlanPriceFormatted = (key: PlanKey): string => {
    const p = getPlan(key);

    if (!p) {
        if (key === 'free') {
            return '0đ';
        }

        if (key === 'basic') {
            return '299.000đ';
        }

        if (key === 'pro') {
            return '699.000đ';
        }

        return '1.499.000đ';
    }

    return p.price === 0 ? '0đ' : new Intl.NumberFormat('vi-VN').format(p.price) + 'đ';
};

const getPlanLimit = (key: PlanKey, field: 'max_branches' | 'max_tables' | 'max_users'): string => {
    const p = getPlan(key);

    if (!p || p[field] === null || p[field] === undefined) {
        return 'Không giới hạn';
    }

    return String(p[field]);
};

const hasPlanFeature = (key: PlanKey, featureKey: string, defaultValue: boolean): boolean => {
    const p = getPlan(key);

    if (!p || !p.features) {
        return defaultValue;
    }

    return Boolean((p.features as Record<string, any>)[featureKey] ?? defaultValue);
};

// FAQ accordion toggle state
const openFaqIndices = ref<number[]>([0, 1]);

const toggleFaq = (index: number) => {
    if (openFaqIndices.value.includes(index)) {
        openFaqIndices.value = openFaqIndices.value.filter((i) => i !== index);
    } else {
        openFaqIndices.value.push(index);
    }
};

const faqs = [
    {
        q: 'Tôi có thể nâng cấp gói bất kỳ lúc nào không?',
        a: 'Có, bạn có thể nâng cấp hoặc chuyển đổi gói dịch vụ bất kỳ lúc nào trực tiếp trong trang quản trị. Hệ thống sẽ tự động tính toán chi phí chênh lệch theo ngày sử dụng thực tế.',
    },
    {
        q: 'Tôi có thể hủy gói bất kỳ lúc nào không?',
        a: 'Hoàn toàn được. Aventura không ràng buộc hợp đồng dài hạn hay phát sinh phí ẩn. Khi hủy gói, bạn vẫn tiếp tục được sử dụng đầy đủ tính năng đến hết chu kỳ đã thanh toán.',
    },
    {
        q: 'Dữ liệu của tôi có được bảo mật không?',
        a: 'Toàn bộ dữ liệu nhà hàng, doanh thu và khách hàng được mã hóa chuẩn SSL 256-bit và sao lưu tự động hàng ngày trên hệ thống đám mây phân tán độ tin cậy 99.9%.',
    },
    {
        q: 'Phương thức thanh toán nào được hỗ trợ?',
        a: 'Chúng tôi hỗ trợ thanh toán linh hoạt qua Chuyển khoản ngân hàng tự động (VietQR / SePay), ví điện tử MoMo, VNPay, và thẻ quốc tế Visa / Mastercard.',
    },
    {
        q: 'Có hỗ trợ kỹ thuật 24/7 không?',
        a: 'Có. Đội ngũ chuyên viên vận hành của Aventura luôn sẵn sàng hỗ trợ bạn 24/7 qua hotline 0346 858 035, live chat trực tiếp và kênh hỗ trợ Zalo OA.',
    },
    {
        q: 'Có chính sách hoàn tiền không?',
        a: 'Có. Trong vòng 14 ngày đầu tiên kích hoạt gói trả phí, nếu cảm thấy phần mềm chưa đáp ứng tốt nhu cầu, chúng tôi sẽ hoàn trả 100% tiền không yêu cầu điều kiện phức tạp.',
    },
];

const comparisonFeatures = computed(() => [
    {
        name: 'Số chi nhánh',
        free: getPlanLimit('free', 'max_branches'),
        basic: getPlanLimit('basic', 'max_branches'),
        pro: getPlanLimit('pro', 'max_branches'),
        enterprise: getPlanLimit('enterprise', 'max_branches'),
    },
    {
        name: 'Số bàn',
        free: getPlanLimit('free', 'max_tables'),
        basic: getPlanLimit('basic', 'max_tables'),
        pro: getPlanLimit('pro', 'max_tables'),
        enterprise: getPlanLimit('enterprise', 'max_tables'),
    },
    {
        name: 'Số nhân viên',
        free: getPlanLimit('free', 'max_users'),
        basic: getPlanLimit('basic', 'max_users'),
        pro: getPlanLimit('pro', 'max_users'),
        enterprise: getPlanLimit('enterprise', 'max_users'),
    },
    { name: 'Dung lượng lưu trữ', free: '500 MB', basic: '5 GB', pro: '50 GB', enterprise: '200 GB' },
    { name: 'API (req/phút)', free: '30', basic: '120', pro: '600', enterprise: '3.000' },
    {
        name: 'Màn hình Bếp (Kitchen Display)',
        free: hasPlanFeature('free', 'kitchen_display', true),
        basic: hasPlanFeature('basic', 'kitchen_display', true),
        pro: hasPlanFeature('pro', 'kitchen_display', true),
        enterprise: hasPlanFeature('enterprise', 'kitchen_display', true),
    },
    {
        name: 'Đặt món qua QR',
        free: hasPlanFeature('free', 'qr_ordering', true),
        basic: hasPlanFeature('basic', 'qr_ordering', true),
        pro: hasPlanFeature('pro', 'qr_ordering', true),
        enterprise: hasPlanFeature('enterprise', 'qr_ordering', true),
    },
    {
        name: 'Quản lý Tồn kho',
        free: hasPlanFeature('free', 'inventory_basic', false),
        basic: hasPlanFeature('basic', 'inventory_basic', true),
        pro: hasPlanFeature('pro', 'inventory_basic', true),
        enterprise: hasPlanFeature('enterprise', 'inventory_basic', true),
    },
    {
        name: 'Quản lý Nhân sự & Chấm công',
        free: hasPlanFeature('free', 'hr_timekeeping', false),
        basic: hasPlanFeature('basic', 'hr_timekeeping', true),
        pro: hasPlanFeature('pro', 'hr_timekeeping', true),
        enterprise: hasPlanFeature('enterprise', 'hr_timekeeping', true),
    },
    {
        name: 'Báo cáo & Phân tích chuyên sâu',
        free: hasPlanFeature('free', 'advanced_analytics', false),
        basic: hasPlanFeature('basic', 'advanced_analytics', false),
        pro: hasPlanFeature('pro', 'advanced_analytics', true),
        enterprise: hasPlanFeature('enterprise', 'advanced_analytics', true),
    },
]);

const plansList: { key: PlanKey; label: string }[] = [
    { key: 'free', label: 'Miễn Phí' },
    { key: 'basic', label: 'Cơ Bản' },
    { key: 'pro', label: 'Chuyên Nghiệp' },
    { key: 'enterprise', label: 'Doanh Nghiệp' },
];
</script>

<template>
    <AppTopbarLayout>
        <Head title="Bảng giá - Aventura | Gói dịch vụ quản lý nhà hàng & quán cà phê">
            <meta
                name="description"
                content="Bảng giá dịch vụ phần mềm quản lý nhà hàng Aventura. Khởi đầu miễn phí 0đ, dùng thử đầy đủ tính năng 14 ngày không cần thẻ tín dụng."
            />
        </Head>

        <div class="relative overflow-hidden bg-background text-foreground">
            <!-- ── SECTION 1: HERO HEADER ────────────────────────────────────── -->
            <section class="relative border-b border-border/60 bg-gradient-to-r from-[#f2f7fc] via-[#f5f9fd] to-[#edf4fb] py-8 lg:py-12 dark:border-slate-800/80 dark:from-slate-900/60 dark:via-slate-900/40 dark:to-background">
                <div class="mx-auto max-w-7xl xl:max-w-[1400px] px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-10 lg:grid-cols-12 lg:gap-8">
                        <!-- Left text -->
                        <div class="space-y-6 lg:col-span-5 xl:col-span-5">
                            <div class="text-xs font-bold tracking-widest text-blue-600 uppercase dark:text-blue-400">
                                BẢNG GIÁ
                            </div>

                            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-[40px] xl:text-[44px] lg:leading-[1.18] dark:text-white">
                                Lựa chọn gói phù hợp <br class="hidden sm:inline" />
                                với nhu cầu của bạn
                            </h1>

                            <p class="text-sm leading-relaxed text-slate-600 sm:text-base dark:text-slate-300 max-w-lg">
                                Từ quán nhỏ đến chuỗi lớn – chúng tôi có gói phù hợp giúp bạn quản lý nhà hàng dễ dàng, hiệu quả và tiết kiệm chi phí nhất.
                            </p>

                            <div class="space-y-4 pt-1">
                                <div class="flex items-start gap-3">
                                    <div class="flex size-5 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white shadow-xs dark:bg-blue-500">
                                        <Check class="size-3 stroke-[3]" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">
                                            Dùng thử miễn phí 14 ngày
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            Không cần thẻ tín dụng
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3">
                                    <div class="flex size-5 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white shadow-xs dark:bg-blue-500">
                                        <Headphones class="size-3" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">
                                            Hỗ trợ 24/7
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            Luôn đồng hành cùng bạn
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right image: Seamless banner illustration matching Image 2 -->
                        <div class="relative flex justify-center lg:col-span-7 xl:col-span-7 lg:justify-end">
                            <div class="relative w-full max-w-2xl lg:max-w-none">
                                <img
                                    src="/images/pricing_hero_banner_target.webp?v=2"
                                    alt="Hệ thống POS thu ngân Aventura"
                                    class="h-auto w-full object-contain transition-transform duration-500 hover:scale-[1.01]"
                                    fetchpriority="high"
                                    decoding="async"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 2: 4 PRICING CARDS ─────────────────────────────────── -->
            <section class="border-b border-border/40 bg-muted/20 py-10 lg:py-14">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Card 1: Miễn Phí -->
                        <div
                            @click="selectedPlanKey = 'free'"
                            class="relative flex cursor-pointer flex-col justify-between rounded-2xl transition-all duration-200"
                            :class="[
                                selectedPlanKey === 'free'
                                    ? 'border-2 border-blue-600 bg-card p-6 shadow-xl ring-4 ring-blue-500/10 dark:border-blue-500 dark:bg-card/70'
                                    : 'border border-border/80 bg-card p-6 shadow-xs hover:border-blue-400/50 hover:shadow-md dark:border-white/10 dark:bg-card/50'
                            ]"
                        >
                            <div
                                v-if="selectedPlanKey === 'free'"
                                class="absolute -top-3 right-6"
                            >
                                <Badge class="rounded-full bg-blue-600 px-3 py-0.5 text-[11px] font-bold text-white shadow-sm">
                                    Đang chọn
                                </Badge>
                            </div>

                            <div>
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                        <Store class="size-5" />
                                    </div>
                                    <h3 class="text-lg font-bold text-foreground">Miễn Phí</h3>
                                </div>
                                <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                                    Phù hợp cho quán nhỏ, bắt đầu với những tính năng cơ bản.
                                </p>

                                <div class="mt-6 flex items-baseline gap-1">
                                    <span
                                        class="text-3xl font-extrabold"
                                        :class="selectedPlanKey === 'free' ? 'text-blue-600 dark:text-blue-400' : 'text-foreground'"
                                    >{{ getPlanPriceFormatted('free') }}</span>
                                    <span class="text-xs text-muted-foreground">/tháng</span>
                                </div>

                                <ul class="mt-6 space-y-2.5 text-xs">
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'free' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span><strong>{{ getPlanLimit('free', 'max_branches') }}</strong> chi nhánh</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'free' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span><strong>{{ getPlanLimit('free', 'max_tables') }}</strong> bàn</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'free' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span><strong>{{ getPlanLimit('free', 'max_users') }}</strong> nhân viên</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'free' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span><strong>500 MB</strong> lưu trữ</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'free' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span>API: 30 req/phút</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'free' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span>Màn hình Bếp (Kitchen Display)</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'free' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span>Đặt món qua QR</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-muted-foreground/60 line-through">
                                        <X class="size-4 shrink-0 text-muted-foreground/40" />
                                        <span>Quản lý Tồn kho</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="mt-8">
                                <Button
                                    as-child
                                    :variant="selectedPlanKey === 'free' ? 'default' : 'outline'"
                                    class="w-full rounded-xl font-semibold"
                                    :class="selectedPlanKey === 'free' ? 'bg-blue-600 text-white hover:bg-blue-700' : ''"
                                >
                                    <Link href="/register">Chọn Miễn Phí</Link>
                                </Button>
                            </div>
                        </div>

                        <!-- Card 2: Cơ Bản -->
                        <div
                            @click="selectedPlanKey = 'basic'"
                            class="relative flex cursor-pointer flex-col justify-between rounded-2xl transition-all duration-200"
                            :class="[
                                selectedPlanKey === 'basic'
                                    ? 'border-2 border-blue-600 bg-card p-6 shadow-xl ring-4 ring-blue-500/10 dark:border-blue-500 dark:bg-card/70'
                                    : 'border border-border/80 bg-card p-6 shadow-xs hover:border-blue-400/50 hover:shadow-md dark:border-white/10 dark:bg-card/50'
                            ]"
                        >
                            <div
                                v-if="selectedPlanKey === 'basic'"
                                class="absolute -top-3 right-6"
                            >
                                <Badge class="rounded-full bg-blue-600 px-3 py-0.5 text-[11px] font-bold text-white shadow-sm">
                                    Đang chọn
                                </Badge>
                            </div>

                            <div>
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                        <Utensils class="size-5" />
                                    </div>
                                    <h3 class="text-lg font-bold text-foreground">Cơ Bản</h3>
                                </div>
                                <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                                    Dành cho quán vừa và nhỏ, muốn tối ưu quy trình vận hành.
                                </p>

                                <div class="mt-6 flex items-baseline gap-1">
                                    <span
                                        class="text-3xl font-extrabold"
                                        :class="selectedPlanKey === 'basic' ? 'text-blue-600 dark:text-blue-400' : 'text-foreground'"
                                    >{{ getPlanPriceFormatted('basic') }}</span>
                                    <span class="text-xs text-muted-foreground">/tháng</span>
                                </div>

                                <ul class="mt-6 space-y-2.5 text-xs">
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'basic' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span><strong>{{ getPlanLimit('basic', 'max_branches') }}</strong> chi nhánh</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'basic' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span><strong>{{ getPlanLimit('basic', 'max_tables') }}</strong> bàn</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'basic' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span><strong>{{ getPlanLimit('basic', 'max_users') }}</strong> nhân viên</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'basic' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span><strong>5 GB</strong> lưu trữ</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'basic' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span>API: 120 req/phút</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'basic' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span>Màn hình Bếp (Kitchen Display)</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'basic' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span>Đặt món qua QR</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'basic' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span>Quản lý Tồn kho</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="mt-8">
                                <Button
                                    as-child
                                    :variant="selectedPlanKey === 'basic' ? 'default' : 'outline'"
                                    class="w-full rounded-xl font-semibold"
                                    :class="selectedPlanKey === 'basic' ? 'bg-blue-600 text-white hover:bg-blue-700' : ''"
                                >
                                    <Link href="/register">Chọn Cơ Bản</Link>
                                </Button>
                            </div>
                        </div>

                        <!-- Card 3: Chuyên Nghiệp (Featured) -->
                        <div
                            @click="selectedPlanKey = 'pro'"
                            class="relative flex cursor-pointer flex-col justify-between rounded-2xl transition-all duration-200"
                            :class="[
                                selectedPlanKey === 'pro'
                                    ? 'border-2 border-blue-600 bg-card p-6 shadow-xl ring-4 ring-blue-500/10 dark:border-blue-500 dark:bg-card/70'
                                    : 'border border-border/80 bg-card p-6 shadow-xs hover:border-blue-400/50 hover:shadow-md dark:border-white/10 dark:bg-card/50'
                            ]"
                        >
                            <div class="absolute -top-3 right-6">
                                <Badge class="rounded-full bg-blue-600 px-3 py-0.5 text-[11px] font-bold text-white shadow-sm hover:bg-blue-600 dark:bg-blue-500">
                                    {{ selectedPlanKey === 'pro' ? 'Đang chọn' : 'Khuyên dùng' }}
                                </Badge>
                            </div>

                            <div>
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-9 items-center justify-center rounded-lg bg-blue-600 text-white shadow-xs">
                                        <Building2 class="size-5" />
                                    </div>
                                    <h3 class="text-lg font-bold text-foreground">Chuyên Nghiệp</h3>
                                </div>
                                <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                                    Lựa chọn phổ biến cho nhà hàng và chuỗi cửa hàng.
                                </p>

                                <div class="mt-6 flex items-baseline gap-1">
                                    <span class="text-3xl font-extrabold text-blue-600 dark:text-blue-400">{{ getPlanPriceFormatted('pro') }}</span>
                                    <span class="text-xs text-muted-foreground">/tháng</span>
                                </div>

                                <ul class="mt-6 space-y-2.5 text-xs">
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                        <span><strong>{{ getPlanLimit('pro', 'max_branches') }}</strong> chi nhánh</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                        <span><strong>{{ getPlanLimit('pro', 'max_tables') }}</strong> bàn</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                        <span><strong>{{ getPlanLimit('pro', 'max_users') }}</strong> nhân viên</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                        <span><strong>50 GB</strong> lưu trữ</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                        <span>API: 600 req/phút</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                        <span>Màn hình Bếp (Kitchen Display)</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                        <span>Đặt món qua QR</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                        <span>Quản lý Tồn kho</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="mt-8">
                                <Button
                                    as-child
                                    :variant="selectedPlanKey === 'pro' ? 'default' : 'outline'"
                                    class="w-full rounded-xl font-bold shadow-md"
                                    :class="selectedPlanKey === 'pro' ? 'bg-blue-600 text-white hover:bg-blue-700 shadow-blue-600/20' : ''"
                                >
                                    <Link href="/register">Chọn Chuyên Nghiệp</Link>
                                </Button>
                            </div>
                        </div>

                        <!-- Card 4: Doanh Nghiệp -->
                        <div
                            @click="selectedPlanKey = 'enterprise'"
                            class="relative flex cursor-pointer flex-col justify-between rounded-2xl transition-all duration-200"
                            :class="[
                                selectedPlanKey === 'enterprise'
                                    ? 'border-2 border-blue-600 bg-card p-6 shadow-xl ring-4 ring-blue-500/10 dark:border-blue-500 dark:bg-card/70'
                                    : 'border border-border/80 bg-card p-6 shadow-xs hover:border-purple-400/50 hover:shadow-md dark:border-white/10 dark:bg-card/50'
                            ]"
                        >
                            <div class="absolute -top-3 right-6">
                                <Badge
                                    class="rounded-full px-3 py-0.5 text-[11px] font-bold text-white shadow-sm"
                                    :class="selectedPlanKey === 'enterprise' ? 'bg-blue-600' : 'bg-gradient-to-r from-purple-600 to-indigo-600'"
                                >
                                    {{ selectedPlanKey === 'enterprise' ? 'Đang chọn' : 'VIP' }}
                                </Badge>
                            </div>

                            <div>
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-9 items-center justify-center rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400">
                                        <Sparkles class="size-5" />
                                    </div>
                                    <h3 class="text-lg font-bold text-foreground">Doanh Nghiệp</h3>
                                </div>
                                <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                                    Giải pháp doanh nghiệp: AI dự báo, API không giới hạn.
                                </p>

                                <div class="mt-6 flex items-baseline gap-1">
                                    <span
                                        class="text-3xl font-extrabold"
                                        :class="selectedPlanKey === 'enterprise' ? 'text-blue-600 dark:text-blue-400' : 'text-foreground'"
                                    >{{ getPlanPriceFormatted('enterprise') }}</span>
                                    <span class="text-xs text-muted-foreground">/tháng</span>
                                </div>

                                <ul class="mt-6 space-y-2.5 text-xs">
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'enterprise' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span><strong>{{ getPlanLimit('enterprise', 'max_branches') }}</strong> chi nhánh</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'enterprise' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span><strong>{{ getPlanLimit('enterprise', 'max_tables') }}</strong> bàn</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'enterprise' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span><strong>{{ getPlanLimit('enterprise', 'max_users') }}</strong> nhân viên</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'enterprise' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span><strong>200 GB</strong> lưu trữ</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'enterprise' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span>API: 3.000 req/phút</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'enterprise' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span>Màn hình Bếp (Kitchen Display)</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'enterprise' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span>Đặt món qua QR</span>
                                    </li>
                                    <li class="flex items-center gap-2 text-foreground">
                                        <Check class="size-4 shrink-0" :class="selectedPlanKey === 'enterprise' ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600 dark:text-emerald-400'" />
                                        <span>Quản lý Tồn kho</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="mt-8">
                                <Button
                                    as-child
                                    :variant="selectedPlanKey === 'enterprise' ? 'default' : 'outline'"
                                    class="w-full rounded-xl font-semibold"
                                    :class="selectedPlanKey === 'enterprise' ? 'bg-blue-600 text-white hover:bg-blue-700' : 'hover:border-purple-500 hover:text-purple-600'"
                                >
                                    <Link href="/register">Chọn Doanh Nghiệp</Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 3: SO SÁNH GÓI DỊCH VỤ ────────────────────────────── -->
            <section class="border-b border-border/40 py-10 lg:py-14">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="space-y-3">
                        <div class="inline-flex items-center gap-2 rounded-full border border-blue-500/20 bg-blue-500/10 px-3.5 py-1 text-xs font-bold tracking-wider text-blue-600 uppercase dark:text-blue-400">
                            SO SÁNH GÓI DỊCH VỤ
                        </div>
                        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl lg:text-4xl">
                            Tính năng chi tiết theo từng gói
                        </h2>
                        <p class="text-sm text-muted-foreground sm:text-base">
                            Xem chi tiết các tính năng và lựa chọn gói phù hợp nhất với nhu cầu của bạn.
                        </p>
                    </div>

                    <div class="mt-8 grid items-start gap-8 lg:grid-cols-12">
                        <!-- Left photo -->
                        <div class="relative lg:col-span-4">
                            <div class="relative overflow-hidden rounded-2xl border border-border/80 shadow-xl">
                                <img
                                    src="/images/pricing_qr_table.webp"
                                    alt="Mã QR đặt món tại bàn"
                                    class="h-[320px] w-full object-cover sm:h-[380px] lg:h-[460px]"
                                    loading="lazy"
                                    decoding="async"
                                />
                                <div class="absolute right-4 bottom-4 left-4 rounded-xl border border-white/20 bg-white/90 p-4 shadow-lg backdrop-blur-md dark:border-white/10 dark:bg-neutral-900/90">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-9 items-center justify-center rounded-lg bg-blue-600 text-white">
                                            <Utensils class="size-4" />
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-bold text-slate-900 dark:text-white">
                                                Quản lý toàn diện
                                            </h4>
                                            <p class="text-[11px] text-slate-600 dark:text-slate-300">
                                                Từ bàn, bếp đến kho
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right table -->
                        <div class="overflow-x-auto rounded-2xl border border-border/80 bg-card/60 shadow-xs backdrop-blur-md lg:col-span-8 dark:border-white/10 dark:bg-card/40">
                            <table class="w-full text-left text-xs sm:text-sm">
                                <thead>
                                    <tr class="border-b border-border/80 bg-muted/50 text-foreground font-bold">
                                        <th class="p-4 sm:px-6">Tính năng</th>
                                        <th
                                            v-for="p in plansList"
                                            :key="p.key"
                                            @click="selectedPlanKey = p.key"
                                            class="p-4 text-center sm:px-4 cursor-pointer select-none transition-all duration-200"
                                            :class="[
                                                selectedPlanKey === p.key
                                                    ? 'text-blue-600 dark:text-blue-400 font-bold bg-blue-500/[0.08] dark:bg-blue-500/[0.18] border-t-2 border-t-blue-600 dark:border-t-blue-400'
                                                    : 'text-foreground hover:text-blue-600 hover:bg-muted/40 font-bold'
                                            ]"
                                        >
                                            <div class="flex items-center justify-center gap-1.5">
                                                <span>{{ p.label }}</span>
                                                <span
                                                    v-if="selectedPlanKey === p.key"
                                                    class="inline-block size-1.5 rounded-full bg-blue-600 dark:bg-blue-400 animate-pulse"
                                                />
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/40">
                                    <tr
                                        v-for="row in comparisonFeatures"
                                        :key="row.name"
                                        class="transition-colors hover:bg-muted/30"
                                    >
                                        <td class="p-4 font-medium text-foreground sm:px-6">
                                            {{ row.name }}
                                        </td>
                                        <td
                                            v-for="p in plansList"
                                            :key="p.key"
                                            @click="selectedPlanKey = p.key"
                                            class="p-4 text-center sm:px-4 cursor-pointer transition-all duration-200"
                                            :class="[
                                                selectedPlanKey === p.key
                                                    ? 'bg-blue-500/[0.06] dark:bg-blue-500/[0.12] font-bold text-blue-600 dark:text-blue-400'
                                                    : 'text-muted-foreground hover:bg-muted/20'
                                            ]"
                                        >
                                            <template v-if="typeof row[p.key] === 'boolean'">
                                                <Check
                                                    v-if="row[p.key]"
                                                    class="mx-auto size-4 transition-colors"
                                                    :class="selectedPlanKey === p.key ? 'text-blue-600 dark:text-blue-400 stroke-[2.5]' : 'text-emerald-600 dark:text-emerald-400'"
                                                />
                                                <X v-else class="mx-auto size-4 text-muted-foreground/40" />
                                            </template>
                                            <template v-else>
                                                {{ row[p.key] }}
                                            </template>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 4: CÂU HỎI THƯỜNG GẶP (FAQ) ────────────────────────── -->
            <section class="border-b border-border/40 bg-muted/20 py-10 lg:py-14">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid items-start gap-8 lg:grid-cols-12">
                        <!-- Left FAQs -->
                        <div class="space-y-6 lg:col-span-8">
                            <div class="space-y-3">
                                <div class="inline-flex items-center gap-2 rounded-full border border-blue-500/20 bg-blue-500/10 px-3.5 py-1 text-xs font-bold tracking-wider text-blue-600 uppercase dark:text-blue-400">
                                    CÂU HỎI THƯỜNG GẶP
                                </div>
                                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl lg:text-4xl">
                                    Những câu hỏi thường gặp
                                </h2>
                                <p class="text-sm text-muted-foreground sm:text-base">
                                    Nếu bạn có bất kỳ thắc mắc nào, hãy xem các câu hỏi thường gặp dưới đây.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 pt-4 sm:grid-cols-2">
                                <div
                                    v-for="(faq, idx) in faqs"
                                    :key="faq.q"
                                    class="rounded-xl border border-border/70 bg-card p-4 shadow-xs transition-all dark:border-white/10 dark:bg-card/40"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-start justify-between gap-3 text-left font-bold text-foreground"
                                        @click="toggleFaq(idx)"
                                    >
                                        <span class="text-xs sm:text-sm">{{ faq.q }}</span>
                                        <ChevronDown
                                            class="size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                            :class="{ 'rotate-180': openFaqIndices.includes(idx) }"
                                        />
                                    </button>
                                    <div
                                        v-show="openFaqIndices.includes(idx)"
                                        class="mt-2.5 border-t border-border/40 pt-2.5 text-xs leading-relaxed text-muted-foreground"
                                    >
                                        {{ faq.a }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right phone mockup -->
                        <div class="relative lg:col-span-4">
                            <div class="relative mx-auto max-w-sm overflow-hidden rounded-2xl border border-border/80 shadow-xl">
                                <img
                                    src="/images/pricing_phone_hand.webp"
                                    alt="Quản lý nhà hàng trên di động"
                                    class="h-[380px] w-full object-cover sm:h-[420px]"
                                    loading="lazy"
                                    decoding="async"
                                />
                                <div class="absolute top-4 left-4 rounded-xl border border-white/20 bg-white/90 px-3.5 py-1.5 shadow-md backdrop-blur-md dark:border-white/10 dark:bg-neutral-900/90">
                                    <p class="text-xs font-handwriting font-bold text-slate-900 dark:text-white">
                                        Quản lý mọi lúc, mọi nơi ~
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── SECTION 5: CALL TO ACTION ─────────────────────────────────── -->
            <section class="py-8 lg:py-10">
                <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div class="relative overflow-hidden grid items-center gap-6 rounded-2xl border-2 border-blue-200/90 bg-gradient-to-br from-[#e6f1fc] via-[#edf5fd] to-[#dff0fb] px-6 py-5 sm:px-8 sm:py-6 lg:px-10 lg:py-6 shadow-lg shadow-blue-500/10 lg:grid-cols-12 lg:gap-8 dark:border-blue-800/60 dark:from-slate-900 dark:via-blue-950/40 dark:to-slate-900">
                        <div class="space-y-3 lg:col-span-7">
                            <div class="inline-flex items-center gap-2 rounded-full border border-blue-400/30 bg-blue-600 px-3.5 py-1 text-xs font-bold tracking-wider text-white uppercase shadow-sm">
                                SẴN SÀNG BẮT ĐẦU?
                            </div>

                            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                                Trải nghiệm Aventura ngay hôm nay!
                            </h2>

                            <p class="text-sm leading-relaxed text-slate-600 sm:text-base dark:text-slate-300 max-w-xl">
                                Đăng ký để dùng thử miễn phí 14 ngày và khám phá tất cả các tính năng của chúng tôi.
                            </p>

                            <div class="pt-0.5">
                                <Button
                                    as-child
                                    size="lg"
                                    class="h-10 gap-2 rounded-xl bg-blue-600 px-6 text-sm font-bold text-white shadow-md shadow-blue-600/20 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 transition-transform hover:scale-[1.02]"
                                >
                                    <Link href="/register" prefetch="hover" cache-for="1m">
                                        Dùng miễn phí ngay <ArrowRight class="size-4" />
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        <div class="lg:col-span-5">
                            <div class="overflow-hidden rounded-xl border-2 border-white/80 shadow-xl transition-transform duration-300 hover:scale-[1.01] dark:border-white/10">
                                <img
                                    src="/images/pricing_laptop_cta.webp"
                                    alt="Bắt đầu sử dụng Aventura"
                                    class="h-[170px] sm:h-[190px] lg:h-[205px] w-full object-cover"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppTopbarLayout>
</template>
