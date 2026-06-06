<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Check, X } from 'lucide-vue-next';
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
        note: 'Gói cơ bản trải nghiệm miễn phí.',
        features: [
            '1 chi nhánh',
            '10 bàn hoạt động',
            '5 nhân viên',
            '500 MB dung lượng lưu trữ',
            'Rate limit: 60 yêu cầu/phút',
        ],
        unsupportedFeatures: [
            'AI dự báo nguyên liệu & tồn kho',
            'Thuật toán AI phát hiện gian lận',
            'Realtime sync & Advanced Analytics',
        ],
        isRecommended: false,
    },
    {
        code: 'pro',
        name: 'Pro',
        price: '499.000đ',
        cycle: '/tháng',
        maxBranches: 'Không giới hạn chi nhánh',
        maxTables: 'Không giới hạn bàn',
        maxUsers: 'Không giới hạn nhân viên',
        note: 'Tối ưu hiệu năng, chống thất thoát cho mô hình chuyên nghiệp.',
        features: [
            'Không giới hạn chi nhánh',
            'Không giới hạn bàn',
            'Không giới hạn nhân viên',
            '10 GB dung lượng lưu trữ',
            'Rate limit: 600 yêu cầu/phút',
            'AI dự báo nguyên liệu & tồn kho',
            'Thuật toán AI phát hiện gian lận',
            'Realtime sync & Advanced Analytics',
            'Hệ thống Audit Log bảo mật',
        ],
        isRecommended: true,
    },
    {
        code: 'max',
        name: 'Max',
        price: '999.000đ',
        cycle: '/tháng',
        maxBranches: 'Tối đa 10 chi nhánh',
        maxTables: 'Tối đa 300 bàn',
        maxUsers: 'Tối đa 80 nhân viên',
        note: 'Phù hợp cho chuỗi nhà hàng vừa và lớn.',
        features: [
            'Tối đa 10 chi nhánh',
            'Tối đa 300 bàn',
            'Tối đa 80 nhân viên',
            '50 GB dung lượng lưu trữ',
            'Rate limit: 1.200 yêu cầu/phút',
            'AI dự báo nguyên liệu & tồn kho',
            'Thuật toán AI phát hiện gian lận',
            'Realtime sync & Advanced Analytics',
            'Hệ thống Audit Log bảo mật',
        ],
        isRecommended: false,
    },
    {
        code: 'ultra',
        name: 'Ultra',
        price: '1.999.000đ',
        cycle: '/tháng',
        maxBranches: 'Không giới hạn chi nhánh',
        maxTables: 'Không giới hạn bàn',
        maxUsers: 'Không giới hạn nhân viên',
        note: 'Giải pháp tối thượng cho doanh nghiệp lớn & chuỗi rộng khắp.',
        features: [
            'Không giới hạn chi nhánh',
            'Không giới hạn bàn',
            'Không giới hạn nhân viên',
            '200 GB dung lượng lưu trữ',
            'Rate limit: 3.000 yêu cầu/phút',
            'AI dự báo nguyên liệu & tồn kho',
            'Thuật toán AI phát hiện gian lận',
            'Realtime sync & Advanced Analytics',
            'Hệ thống Audit Log bảo mật',
        ],
        isRecommended: false,
    },
];

const planNotes: Record<string, string> = {
    free:  'Gói cơ bản trải nghiệm miễn phí.',
    pro:   'Tối ưu hiệu năng, chống thất thoát cho mô hình chuyên nghiệp.',
    max:   'Phù hợp cho chuỗi nhà hàng vừa và lớn.',
    ultra: 'Giải pháp tối thượng cho doanh nghiệp lớn & chuỗi rộng khắp.',
};

function buildDisplayPlan(db: DbPlan) {
    const lim = (v: number | null, unit: string) =>
        v === null || v === -1 ? `Không giới hạn ${unit}` : `${v} ${unit}`;

    const mb  = (db.features.max_storage_mb as number) ?? 500;
    const storage = mb >= 1024 ? `${mb / 1024} GB dung lượng lưu trữ` : `${mb} MB dung lượng lưu trữ`;
    const rate = (db.features.api_rate_limit as number) ?? 60;

    const always = [
        lim(db.max_branches, 'chi nhánh'),
        lim(db.max_tables, 'bàn'),
        lim(db.max_users, 'nhân viên'),
        storage,
        `Rate limit: ${rate.toLocaleString('vi-VN')} yêu cầu/phút`,
    ];

    const conditionals = [
        { key: 'ai_features',         label: 'AI dự báo nguyên liệu & tồn kho' },
        { key: 'ai_features',         label: 'Thuật toán AI phát hiện gian lận' },
        { key: 'realtime',            label: 'Realtime sync & Advanced Analytics' },
        { key: 'advanced_analytics',  label: 'Hệ thống Audit Log bảo mật' },
    ];

    const features: string[] = [...always];
    const unsupportedFeatures: string[] = [];
    const seen = new Set<string>();

    for (const { key, label } of conditionals) {
        if (seen.has(label)) {
            continue;
        }

        seen.add(label);

        if (db.features[key]) {
            features.push(label);
        } else {
            unsupportedFeatures.push(label);
        }
    }

    return {
        code:               db.code,
        name:               db.name,
        price:              db.price === 0 ? '0đ' : db.price.toLocaleString('vi-VN') + 'đ',
        cycle:              db.billing_cycle === 'monthly' ? '/tháng' : '/năm',
        note:               (db.features.description as string | undefined) || planNotes[db.code] || '',
        features,
        unsupportedFeatures,
        isRecommended:      db.code === 'pro',
    };
}

// Pricing toggle: monthly / yearly
const billingCycle = ref<'monthly' | 'yearly'>('monthly');

const displayPlans = computed(() => {
    const plans = props.plans?.length ? props.plans.map(buildDisplayPlan) : staticPlans;

    if (billingCycle.value === 'yearly') {
        return plans.map(p => {
            if (p.price === '0đ') {
                return p;
            }

            // Strip existing price, compute 20% discount
            const dbPlan = props.plans?.find(d => d.code === p.code);

            if (!dbPlan || dbPlan.price === 0) {
                return p;
            }

            const yearlyMonthly = Math.round(dbPlan.price * 0.8);

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
    <section id="pricing" class="px-4 py-10 lg:py-12 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <div class="reveal-on-scroll flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 max-w-full">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold">Gói dịch vụ linh hoạt</h2>
                    <p class="mt-3 text-muted-foreground">
                        Từ quán nhỏ đến chuỗi lớn — chọn gói phù hợp và nâng cấp bất kỳ lúc nào mà không mất dữ liệu.
                    </p>
                </div>
                <!-- Billing toggle -->
                <div class="flex items-center gap-1 rounded-xl border border-border bg-muted p-1 text-sm shrink-0 self-start sm:self-auto">
                    <button
                        @click="billingCycle = 'monthly'"
                        class="rounded-lg px-4 py-1.5 font-medium transition-all"
                        :class="billingCycle === 'monthly' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    >Tháng</button>
                    <button
                        @click="billingCycle = 'yearly'"
                        class="rounded-lg px-4 py-1.5 font-medium transition-all flex items-center gap-1.5"
                        :class="billingCycle === 'yearly' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'"
                    >
                        Năm
                        <span class="text-[10px] font-bold bg-emerald-500 text-white px-1.5 py-0.5 rounded-full">-20%</span>
                    </button>
                </div>
            </div>
            <div
                class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
            >
                <Card
                    v-for="(plan, idx) in displayPlans"
                    :key="plan.code"
                    class="reveal-on-scroll flex flex-col justify-between border-border transition-all duration-200 hover:shadow-md"
                    :class="{
                        'border-2 border-primary shadow-sm':
                            plan.isRecommended,
                        'border-2 border-violet-500/80 bg-gradient-to-b from-violet-500/5 to-transparent':
                            plan.code === 'ultra',
                    }"
                    :style="{ transitionDelay: idx * 150 + 'ms' }"
                >
                    <CardHeader>
                        <div class="mb-2 flex items-center justify-between">
                            <CardTitle
                                class="flex items-center gap-1.5 text-2xl font-bold"
                                :class="{
                                    'text-primary': plan.isRecommended,
                                    'text-violet-500':
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
                                v-else-if="plan.code === 'ultra'"
                                class="bg-violet-600 text-white hover:bg-violet-700"
                                >VIP</Badge
                            >
                        </div>
                        <div class="mt-2 flex items-end gap-1 min-h-[40px]">
                            <Transition name="pricing-fade" mode="out-in">
                                <div :key="plan.price" class="flex items-end gap-1">
                                    <span
                                        class="text-3xl font-extrabold text-foreground"
                                        :class="{
                                            'text-primary': plan.isRecommended,
                                            'text-violet-500':
                                                plan.code === 'ultra',
                                        }"
                                    >
                                        {{ plan.price }}
                                    </span>
                                    <span
                                        class="pb-1 text-xs text-muted-foreground"
                                        >{{ plan.cycle }}</span
                                    >
                                </div>
                            </Transition>
                        </div>
                        <CardDescription class="mt-2 min-h-[40px] text-xs">
                            {{ plan.note }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex-grow space-y-2 text-xs">
                        <p
                            v-for="feat in plan.features"
                            :key="feat"
                            class="flex items-center gap-2"
                        >
                            <Check
                                class="size-4 flex-shrink-0 text-emerald-500"
                                :class="{
                                    'text-primary': plan.isRecommended,
                                    'text-violet-500':
                                        plan.code === 'ultra',
                                }"
                            />
                            <span>{{ feat }}</span>
                        </p>
                        <p
                            v-for="unfeat in plan.unsupportedFeatures"
                            :key="unfeat"
                            class="flex items-center gap-2 text-muted-foreground opacity-60"
                        >
                            <X class="size-4 flex-shrink-0" />
                            <span>{{ unfeat }}</span>
                        </p>
                    </CardContent>
                    <div class="mt-4 px-6 pb-6">
                        <Button
                            v-if="canRegister"
                            as-child
                            :variant="
                                plan.isRecommended
                                    ? 'default'
                                    : plan.code === 'ultra'
                                      ? 'default'
                                      : 'outline'
                            "
                            class="w-full text-xs font-semibold"
                            :class="{
                                'border-0 bg-violet-600 text-white hover:bg-violet-700':
                                    plan.code === 'ultra',
                            }"
                        >
                            <Link :href="user ? '/billing/checkout?plan=' + plan.code : login.url({ query: { status: 'Bạn cần đăng nhập tài khoản để nâng gói', plan: plan.code } })"
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
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.pricing-fade-enter-from {
    opacity: 0;
    transform: translateY(4px);
}
.pricing-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
