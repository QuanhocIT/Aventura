<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { login } from '@/routes';

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
    plans?: DbPlan[];
    canRegister: boolean;
    user?: any;
}>();

const staticPlans = [
    {
        code: 'free',
        name: 'Free',
        price: '0đ',
        cycle: '/tháng',
        maxBranches: '1 chi nhánh',
        maxTables: '10 bàn',
        maxUsers: '5 nhân viên',
        note: 'Gói cơ bản, trải nghiệm POS miễn phí.',
        features: [
            '1 chi nhánh',
            '10 bàn',
            '5 nhân viên',
            '500 MB lưu trữ',
            'API: 30 req/phút',
        ],
        unsupportedFeatures: [
            'Màn hình Bếp (Kitchen Display)',
            'Đặt món qua QR',
            'Quản lý Tồn kho',
            'Chấm công & Lịch làm việc',
            'Lương & Nhân sự đầy đủ',
            'Báo cáo Nâng cao',
            'Cập nhật thời gian thực',
            'Phát hiện Gian lận',
            'Email Báo cáo tự động',
            'AI Tư vấn chiến lược',
            'AI Dự báo Tồn kho',
            'Truy cập API',
        ],
        isRecommended: false,
    },
    {
        code: 'starter',
        name: 'Cơ Bản',
        price: '299.000đ',
        cycle: '/tháng',
        maxBranches: 'Tối đa 3 chi nhánh',
        maxTables: 'Tối đa 60 bàn',
        maxUsers: 'Tối đa 20 nhân viên',
        note: 'Đầy đủ vận hành: bếp, QR, chấm công, tồn kho.',
        features: [
            '3 chi nhánh',
            '60 bàn',
            '20 nhân viên',
            '5 GB lưu trữ',
            'API: 120 req/phút',
            'Màn hình Bếp (Kitchen Display)',
            'Đặt món qua QR',
            'Quản lý Tồn kho',
            'Chấm công & Lịch làm việc',
            'Cập nhật thời gian thực',
        ],
        unsupportedFeatures: [
            'Lương & Nhân sự đầy đủ',
            'Báo cáo Nâng cao',
            'Phát hiện Gian lận',
            'Email Báo cáo tự động',
            'AI Tư vấn chiến lược',
            'AI Dự báo Tồn kho',
            'Truy cập API',
        ],
        isRecommended: false,
    },
    {
        code: 'pro',
        name: 'Chuyên Nghiệp',
        price: '699.000đ',
        cycle: '/tháng',
        maxBranches: 'Tối đa 10 chi nhánh',
        maxTables: 'Tối đa 200 bàn',
        maxUsers: 'Tối đa 60 nhân viên',
        note: 'Nâng cao toàn diện: AI, nhân sự, báo cáo, chống gian lận.',
        features: [
            '10 chi nhánh',
            '200 bàn',
            '60 nhân viên',
            '50 GB lưu trữ',
            'API: 600 req/phút',
            'Màn hình Bếp (Kitchen Display)',
            'Đặt món qua QR',
            'Quản lý Tồn kho',
            'Chấm công & Lịch làm việc',
            'Lương & Nhân sự đầy đủ',
            'Báo cáo Nâng cao',
            'Cập nhật thời gian thực',
            'Phát hiện Gian lận',
            'Email Báo cáo tự động',
            'AI Tư vấn chiến lược',
        ],
        unsupportedFeatures: [
            'AI Dự báo Tồn kho',
            'Truy cập API',
        ],
        isRecommended: true,
    },
    {
        code: 'enterprise',
        name: 'Doanh Nghiệp',
        price: '1.499.000đ',
        cycle: '/tháng',
        maxBranches: 'Không giới hạn chi nhánh',
        maxTables: 'Không giới hạn bàn',
        maxUsers: 'Không giới hạn nhân viên',
        note: 'Giải pháp doanh nghiệp: AI dự báo, API không giới hạn.',
        features: [
            'Không giới hạn chi nhánh',
            'Không giới hạn bàn',
            'Không giới hạn nhân viên',
            '200 GB lưu trữ',
            'API: 3.000 req/phút',
            'Màn hình Bếp (Kitchen Display)',
            'Đặt món qua QR',
            'Quản lý Tồn kho',
            'Chấm công & Lịch làm việc',
            'Lương & Nhân sự đầy đủ',
            'Báo cáo Nâng cao',
            'Cập nhật thời gian thực',
            'Phát hiện Gian lận',
            'Email Báo cáo tự động',
            'AI Tư vấn chiến lược',
            'AI Dự báo Tồn kho',
            'Truy cập API',
        ],
        unsupportedFeatures: [],
        isRecommended: false,
    },
];

const planNotes: Record<string, string> = {
    free: 'Gói cơ bản, trải nghiệm POS miễn phí.',
    starter: 'Đầy đủ vận hành: bếp, QR, chấm công, tồn kho.',
    pro: 'Nâng cao toàn diện: AI, nhân sự, báo cáo, chống gian lận.',
    enterprise:
        'Giải pháp doanh nghiệp: AI dự báo, API không giới hạn.',
};

const ALL_FEATURES = [
    { key: 'kitchen_display', label: 'Màn hình Bếp (Kitchen Display)' },
    { key: 'qr_ordering', label: 'Đặt món qua QR' },
    { key: 'inventory_basic', label: 'Quản lý Tồn kho' },
    { key: 'hr_timekeeping', label: 'Chấm công & Lịch làm việc' },
    { key: 'hr_full', label: 'Lương & Nhân sự đầy đủ' },
    { key: 'advanced_analytics', label: 'Báo cáo Nâng cao' },
    { key: 'realtime', label: 'Cập nhật thời gian thực' },
    { key: 'fraud_detection', label: 'Phát hiện Gian lận' },
    { key: 'email_reports', label: 'Email Báo cáo tự động' },
    { key: 'ai_advisor', label: 'AI Tư vấn chiến lược' },
    { key: 'ai_forecasting', label: 'AI Dự báo Tồn kho' },
    { key: 'api_access', label: 'Truy cập API' },
];

function buildDisplayPlan(db: DbPlan) {
    const lim = (v: number | null, unit: string) =>
        v === null || v === -1 ? `Không giới hạn ${unit}` : `${v} ${unit}`;

    const mb = (db.features.max_storage_mb as number) ?? 500;
    const storage = mb >= 1024 ? `${mb / 1024} GB lưu trữ` : `${mb} MB lưu trữ`;
    const rate = (db.features.api_rate_limit as number) ?? 60;

    const features: string[] = [
        lim(db.max_branches, 'chi nhánh'),
        lim(db.max_tables, 'bàn'),
        lim(db.max_users, 'nhân viên'),
        storage,
        `API: ${rate.toLocaleString('vi-VN')} req/phút`,
    ];

    const unsupportedFeatures: string[] = [];

    for (const f of ALL_FEATURES) {
        if (db.features[f.key]) {
            features.push(f.label);
        } else {
            unsupportedFeatures.push(f.label);
        }
    }

    return {
        code: db.code,
        name: db.name,
        price: db.price === 0 ? '0đ' : db.price.toLocaleString('vi-VN') + 'đ',
        cycle: db.billing_cycle === 'monthly' ? '/tháng' : '/năm',
        note:
            (db.features.description as string | undefined) ||
            planNotes[db.code] ||
            '',
        features,
        unsupportedFeatures,
        isRecommended: db.code === 'pro',
        yearlyDiscountPercent:
            db.features.yearly_discount_percent !== undefined
                ? Number(db.features.yearly_discount_percent)
                : 20,
    };
}

// Pricing toggle: monthly / yearly
const billingCycle = ref<'monthly' | 'yearly'>('monthly');

const displayPlans = computed(() => {
    const plans = props.plans?.length
        ? props.plans.map(buildDisplayPlan)
        : staticPlans.map((p) => ({ ...p, yearlyDiscountPercent: 20 }));

    if (billingCycle.value === 'yearly') {
        return plans.map((p) => {
            if (p.price === '0đ' || p.price === 'Miễn phí') {
                return p;
            }

            let rawPrice = 0;

            if (props.plans?.length) {
                const dbPlan = props.plans.find((d) => d.code === p.code);
                rawPrice = dbPlan ? Number(dbPlan.price) : 0;
            } else {
                rawPrice = Number(p.price.replace(/[^\d]/g, ''));
            }

            if (rawPrice === 0) {
                return p;
            }

            const discountPercent = p.yearlyDiscountPercent ?? 20;
            const yearlyMonthly = Math.round(
                rawPrice * (1 - discountPercent / 100),
            );

            return {
                ...p,
                price: yearlyMonthly.toLocaleString('vi-VN') + 'đ',
                cycle: '/tháng (thanh toán năm)',
            };
        });
    }

    return plans;
});
</script>

<template>
    <section id="pricing" class="px-4 py-10 lg:px-8 lg:py-12">
        <div class="mx-auto max-w-7xl">
            <div
                class="reveal-on-scroll flex max-w-full flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="max-w-2xl">
                    <h2
                        class="heading-section text-gradient-brand text-3xl font-semibold"
                    >
                        Gói dịch vụ linh hoạt
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        Từ quán nhỏ đến chuỗi lớn — chọn gói phù hợp và nâng cấp
                        bất kỳ lúc nào mà không mất dữ liệu.
                    </p>
                </div>
                <!-- Billing toggle -->
                <div
                    class="flex shrink-0 items-center gap-1 self-start rounded-xl border border-border bg-muted p-1 text-sm sm:self-auto"
                >
                    <button
                        @click="billingCycle = 'monthly'"
                        class="rounded-lg px-4 py-1.5 font-medium transition-all"
                        :class="
                            billingCycle === 'monthly'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        Tháng
                    </button>
                    <button
                        @click="billingCycle = 'yearly'"
                        class="flex items-center gap-1.5 rounded-lg px-4 py-1.5 font-medium transition-all"
                        :class="
                            billingCycle === 'yearly'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        Năm
                        <span
                            class="rounded-full bg-emerald-500 px-1.5 py-0.5 text-[10px] font-bold text-white"
                            >-20%</span
                        >
                    </button>
                </div>
            </div>
            <div
                class="reveal-on-scroll mt-8 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4"
            >
                <Card
                    v-for="plan in displayPlans"
                    :key="plan.code"
                    class="stagger-child flex min-w-0 flex-col justify-between gap-4 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl sm:gap-6"
                    :class="{
                        'border-primary/30 shadow-xl ring-2 shadow-primary/10 ring-primary/40':
                            plan.isRecommended,
                        'border-2 border-violet-500/80 bg-gradient-to-b from-violet-500/5 to-transparent':
                            plan.code === 'enterprise' || plan.code === 'ultra',
                    }"
                >
                    <CardHeader class="px-3 sm:px-6">
                        <div
                            class="mb-2 flex flex-wrap items-start justify-between gap-2"
                        >
                            <CardTitle
                                class="min-w-0 text-lg font-bold break-words sm:text-2xl"
                                :class="{
                                    'text-primary': plan.isRecommended,
                                    'text-violet-500':
                                        plan.code === 'enterprise' ||
                                        plan.code === 'ultra',
                                }"
                            >
                                {{ plan.name }}
                            </CardTitle>
                            <Badge
                                v-if="plan.code === 'free'"
                                variant="secondary"
                                >Mặc định</Badge
                            >
                            <Badge v-else-if="plan.isRecommended"
                                >Khuyến nghị</Badge
                            >
                            <Badge
                                v-else-if="
                                    plan.code === 'enterprise' ||
                                    plan.code === 'ultra'
                                "
                                class="bg-violet-600 text-white hover:bg-violet-700"
                                >VIP</Badge
                            >
                        </div>
                        <div class="mt-2 flex min-h-[40px] items-end gap-1">
                            <Transition name="pricing-fade" mode="out-in">
                                <div
                                    :key="plan.price"
                                    class="flex min-w-0 flex-wrap items-end gap-1"
                                >
                                    <span
                                        class="text-xl font-extrabold text-foreground sm:text-3xl"
                                        :class="{
                                            'text-primary': plan.isRecommended,
                                            'text-violet-500':
                                                plan.code === 'enterprise' ||
                                                plan.code === 'ultra',
                                        }"
                                    >
                                        {{ plan.price }}
                                    </span>
                                    <span
                                        class="pb-0.5 text-[10px] text-muted-foreground sm:pb-1 sm:text-xs"
                                        >{{ plan.cycle }}</span
                                    >
                                    <Badge
                                        v-if="
                                            billingCycle === 'yearly' &&
                                            plan.yearlyDiscountPercent > 0
                                        "
                                        variant="outline"
                                        class="ml-0 self-center border-emerald-500/20 bg-emerald-500/10 text-[9px] font-bold text-emerald-600 sm:ml-1 sm:text-[10px] dark:text-emerald-400"
                                    >
                                        Giảm {{ plan.yearlyDiscountPercent }}%
                                    </Badge>
                                </div>
                            </Transition>
                        </div>
                        <CardDescription
                            class="mt-2 min-h-[52px] text-[11px] leading-tight sm:min-h-[40px] sm:text-xs"
                        >
                            {{ plan.note }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent
                        class="flex-grow px-3 text-[11px] sm:px-6 sm:text-xs"
                    >
                        <div
                            class="custom-scrollbar max-h-[180px] space-y-2 overflow-y-auto pr-1.5"
                        >
                            <p
                                v-for="feat in plan.features"
                                :key="feat"
                                class="flex items-start gap-1.5 leading-tight"
                            >
                                <Check
                                    class="size-4 flex-shrink-0 text-emerald-500"
                                    :class="{
                                        'text-primary': plan.isRecommended,
                                        'text-violet-500':
                                            plan.code === 'enterprise' ||
                                            plan.code === 'ultra',
                                    }"
                                />
                                <span class="min-w-0">{{ feat }}</span>
                            </p>
                            <p
                                v-for="unfeat in plan.unsupportedFeatures"
                                :key="unfeat"
                                class="flex items-start gap-1.5 leading-tight text-muted-foreground opacity-60"
                            >
                                <X class="size-4 flex-shrink-0" />
                                <span class="min-w-0">{{ unfeat }}</span>
                            </p>
                        </div>
                    </CardContent>
                    <div class="mt-4 px-3 pb-4 sm:px-6 sm:pb-6">
                        <Button
                            v-if="canRegister"
                            as-child
                            :variant="
                                plan.isRecommended
                                    ? 'default'
                                    : plan.code === 'enterprise' ||
                                        plan.code === 'ultra'
                                      ? 'default'
                                      : 'outline'
                            "
                            class="w-full text-xs font-semibold"
                            :class="{
                                'border-0 bg-violet-600 text-white hover:bg-violet-700':
                                    plan.code === 'enterprise' ||
                                    plan.code === 'ultra',
                            }"
                        >
                            <Link
                                :href="
                                    user
                                        ? `/billing/checkout?plan=${plan.code}&cycle=${billingCycle}`
                                        : login.url({
                                              query: {
                                                  status: 'Bạn cần đăng nhập tài khoản để nâng gói',
                                                  plan: plan.code,
                                                  cycle: billingCycle,
                                              },
                                          })
                                "
                                >Chọn {{ plan.name }}</Link
                            >
                        </Button>
                    </div>
                </Card>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Pricing cross-fade switch */
.pricing-fade-enter-active,
.pricing-fade-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}
.pricing-fade-enter-from {
    opacity: 0;
    transform: translateY(4px);
}
.pricing-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 9999px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.15) transparent;
}
</style>
