<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { 
    Sparkles, 
    Building2, 
    Grid, 
    Users2, 
    ArrowUpRight, 
    Check, 
    X,
    Lock,
    HelpCircle
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const page = usePage();
const tenant = computed(() => page.props.tenant as any);

// State để hiển thị Modal Nâng cấp
const isUpgradeModalOpen = ref(false);

if (!tenant.value) {
    // Không hiển thị nếu chưa đăng nhập / không thuộc nhà hàng nào
}

const planName = computed(() => tenant.value?.plan?.name ?? 'Miễn phí');
const planCode = computed(() => tenant.value?.plan?.code?.toUpperCase() ?? 'FREE');
const isFree = computed(() => planCode.value === 'FREE');
const isTrial = computed(() => tenant.value?.status === 'trial');

// Tính số ngày còn lại của trial hoặc subscription
const daysRemaining = computed(() => {
    const targetDateStr = tenant.value?.subscription_ends_at || tenant.value?.trial_ends_at;
    if (!targetDateStr) return null;
    
    const targetDate = new Date(targetDateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const diffTime = targetDate.getTime() - today.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    return diffDays > 0 ? diffDays : 0;
});

// Thống kê tài nguyên
const resources = computed(() => {
    const summary = tenant.value?.quota_summary?.resources ?? {};
    return [
        {
            key: 'branches',
            label: 'Chi nhánh',
            icon: Building2,
            used: summary.branches?.used ?? 0,
            limit: summary.branches?.limit,
            unlimited: summary.branches?.unlimited ?? false,
            percentage: summary.branches?.percentage ?? 0
        },
        {
            key: 'tables',
            label: 'Bàn hoạt động',
            icon: Grid,
            used: summary.tables?.used ?? 0,
            limit: summary.tables?.limit,
            unlimited: summary.tables?.unlimited ?? false,
            percentage: summary.tables?.percentage ?? 0
        },
        {
            key: 'employees',
            label: 'Nhân viên',
            icon: Users2,
            used: summary.employees?.used ?? 0,
            limit: summary.employees?.limit,
            unlimited: summary.employees?.unlimited ?? false,
            percentage: summary.employees?.percentage ?? 0
        }
    ];
});

// Quyết định màu sắc của thanh tiến trình dựa trên tỉ lệ phần trăm sử dụng
function getProgressColorClass(pct: number) {
    if (pct >= 90) return 'bg-rose-500'; // Đỏ khi đầy / gần đầy
    if (pct >= 70) return 'bg-amber-500'; // Cam khi ở mức cảnh báo
    return 'bg-emerald-500'; // Xanh lá khi an toàn
}

const plans = [
    {
        code: 'free',
        name: 'Gói Miễn phí (Free)',
        price: '0đ',
        note: 'Dành cho quán ăn mới lập nghiệp để làm quen vận hành.',
        features: [
            { text: 'Tối đa 1 chi nhánh', supported: true },
            { text: 'Tối đa 10 bàn hoạt động', supported: true },
            { text: 'Tối đa 5 nhân viên', supported: true },
            { text: 'Không hỗ trợ AI Dự báo kho', supported: false },
            { text: 'Không hỗ trợ AI phát hiện gian lận', supported: false },
        ]
    },
    {
        code: 'pro',
        name: 'Gói Cao cấp (Pro)',
        price: '499.000đ',
        note: 'Tối ưu hiệu năng, chống thất thoát cho mô hình chuyên nghiệp.',
        isRecommended: true,
        features: [
            { text: 'Không giới hạn bàn & nhân sự', supported: true },
            { text: 'Quản lý nhiều chi nhánh', supported: true },
            { text: 'AI dự báo nguyên liệu & tồn kho', supported: true },
            { text: 'Thuật toán AI phát hiện gian lận', supported: true },
            { text: 'Hệ thống Audit Log bảo mật', supported: true },
        ]
    },
    {
        code: 'max',
        name: 'Gói Chuyên nghiệp (Max)',
        price: '999.000đ',
        note: 'Phù hợp cho chuỗi nhà hàng vừa và lớn.',
        features: [
            { text: 'Tối đa 10 chi nhánh', supported: true },
            { text: 'Tối đa 300 bàn hoạt động', supported: true },
            { text: 'Tối đa 80 nhân viên', supported: true },
            { text: 'AI dự báo nguyên liệu & tồn kho', supported: true },
            { text: 'Thuật toán AI phát hiện gian lận', supported: true },
            { text: 'Hệ thống Audit Log bảo mật', supported: true },
        ]
    },
    {
        code: 'ultra',
        name: 'Gói Doanh nghiệp (Ultra)',
        price: '1.999.000đ',
        note: 'Giải pháp tối thượng cho doanh nghiệp lớn & chuỗi rộng khắp.',
        isVip: true,
        features: [
            { text: 'Không giới hạn chi nhánh', supported: true },
            { text: 'Không giới hạn bàn hoạt động', supported: true },
            { text: 'Không giới hạn nhân viên', supported: true },
            { text: 'AI dự báo nguyên liệu & tồn kho', supported: true },
            { text: 'Thuật toán AI phát hiện gian lận', supported: true },
            { text: 'Hệ thống Audit Log bảo mật', supported: true },
        ]
    }
];

function openUpgradeModal() {
    isUpgradeModalOpen.value = true;
}

function closeUpgradeModal() {
    isUpgradeModalOpen.value = false;
}

function goToUpgradeCheckout(code: string) {
    window.location.href = `/billing/checkout?plan=${code}`;
}
</script>

<template>
    <div v-if="tenant" class="px-4 py-2">
        <!-- Widget hiển thị thông tin gói -->
        <div class="relative overflow-hidden rounded-xl border border-border/60 bg-gradient-to-b from-card to-background/50 p-4 shadow-sm backdrop-blur-md">
            
            <!-- Hiệu ứng sáng mờ ở background cho gói PRO -->
            <div v-if="!isFree" class="absolute -right-10 -top-10 h-24 w-24 rounded-full bg-primary/10 blur-2xl pointer-events-none"></div>
            
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" :class="isFree ? 'bg-amber-400' : 'bg-emerald-400'"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2" :class="isFree ? 'bg-amber-500' : 'bg-emerald-500'"></span>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Gói {{ planName }}
                    </span>
                </div>
                
                <!-- Huy hiệu VIP cho PRO -->
                <span v-if="!isFree" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                    <Sparkles class="size-3" /> PRO
                </span>
            </div>

            <!-- Cảnh báo dùng thử hoặc hết hạn -->
            <div v-if="isTrial && daysRemaining !== null" class="mb-4 rounded-lg bg-amber-500/10 border border-amber-500/20 p-2.5 text-xs text-amber-600 dark:text-amber-400">
                <p class="font-medium flex items-center gap-1.5">
                    <Sparkles class="size-3.5 animate-pulse" />
                    Đang thử nghiệm PRO (Còn {{ daysRemaining }} ngày)
                </p>
            </div>

            <!-- Chi tiết hạn ngạch tài nguyên -->
            <div class="space-y-3.5">
                <div v-for="res in resources" :key="res.key" class="space-y-1.5">
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5 text-muted-foreground">
                            <component :is="res.icon" class="size-3.5" />
                            <span>{{ res.label }}</span>
                        </div>
                        <span class="font-medium text-foreground">
                            {{ res.used }} / <span v-if="res.unlimited">∞</span><span v-else>{{ res.limit }}</span>
                        </span>
                    </div>
                    
                    <!-- Thanh tiến trình custom mượt mà -->
                    <div class="h-1.5 w-full rounded-full bg-secondary overflow-hidden">
                        <div 
                            class="h-full rounded-full transition-all duration-700 ease-out" 
                            :class="res.unlimited ? 'bg-primary' : getProgressColorClass(res.percentage)"
                            :style="{ width: (res.unlimited ? 100 : res.percentage) + '%' }"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- Nút nâng cấp hoặc đổi gói -->
            <div class="mt-4 pt-3 border-t border-border/40">
                <button 
                    @click="openUpgradeModal"
                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 transition-all duration-200 shadow-sm shadow-violet-500/15"
                >
                    <Sparkles class="size-3.5" />
                    <span>{{ isFree ? 'Nâng cấp lên gói PRO' : 'Xem thông tin dịch vụ' }}</span>
                    <ArrowUpRight class="size-3.5" />
                </button>
            </div>
        </div>

        <!-- MODAL NÂNG CẤP GÓI CHUYÊN NGHIỆP VÀ SANG TRỌNG -->
        <div v-if="isUpgradeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-all duration-300">
            <div class="relative w-full max-w-5xl overflow-hidden rounded-2xl border border-border bg-card shadow-2xl transition-all">
                
                <!-- Nút đóng -->
                <button @click="closeUpgradeModal" class="absolute top-4 right-4 rounded-full p-1.5 text-muted-foreground hover:bg-muted transition-colors">
                    <X class="size-5" />
                </button>

                <!-- Header Modal -->
                <div class="px-6 pt-8 pb-4 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                        <Sparkles class="size-6 text-primary animate-pulse" />
                    </div>
                    <h3 class="text-xl font-bold tracking-tight text-foreground">
                        Nâng cấp gói dịch vụ Aventura của bạn
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Mở khóa toàn diện sức mạnh của hệ thống quản lý Aventura thông minh.
                    </p>
                </div>

                <!-- So sánh các gói -->
                <div class="px-6 py-4 grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                    <div 
                        v-for="plan in plans" 
                        :key="plan.code"
                        class="rounded-xl border p-4 relative flex flex-col justify-between transition-all duration-300"
                        :class="[
                            plan.code === tenant?.plan?.code?.toLowerCase() 
                                ? 'border-2 border-emerald-500 bg-emerald-500/5 shadow-sm' 
                                : plan.isRecommended 
                                    ? 'border-2 border-primary bg-gradient-to-b from-primary/5 to-transparent shadow-md hover:scale-[1.02]' 
                                    : plan.isVip
                                        ? 'border-2 border-violet-500 bg-gradient-to-b from-violet-500/10 to-transparent hover:scale-[1.02] shadow-sm hover:shadow-violet-500/10'
                                        : 'border-border bg-muted/40 opacity-90 hover:opacity-100 hover:scale-[1.02]'
                        ]"
                    >
                        <!-- Huy hiệu -->
                        <div 
                            v-if="plan.isRecommended" 
                            class="absolute top-3 right-3 rounded-full bg-primary/10 px-2 py-0.5 text-[9px] font-bold text-primary uppercase"
                        >
                            Khuyến nghị
                        </div>
                        <div 
                            v-else-if="plan.isVip" 
                            class="absolute top-3 right-3 rounded-full bg-violet-600/15 px-2 py-0.5 text-[9px] font-bold text-violet-600 uppercase tracking-wider"
                        >
                            VIP
                        </div>
                        <div 
                            v-else-if="plan.code === tenant?.plan?.code?.toLowerCase()" 
                            class="absolute top-3 right-3 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[9px] font-bold text-emerald-600 uppercase"
                        >
                            Hiện tại
                        </div>

                        <div>
                            <h4 class="text-sm font-bold text-foreground">{{ plan.name }}</h4>
                            <p class="mt-1 text-[11px] leading-relaxed text-muted-foreground min-h-[34px]">{{ plan.note }}</p>
                            
                            <div class="mt-3 flex items-end gap-0.5">
                                <span 
                                    class="text-lg font-extrabold"
                                    :class="[
                                        plan.isRecommended ? 'text-primary' : plan.isVip ? 'text-violet-600 dark:text-violet-400' : 'text-foreground'
                                    ]"
                                >
                                    {{ plan.price }}
                                </span>
                                <span class="text-[10px] text-muted-foreground pb-0.5">/ tháng</span>
                            </div>

                            <ul class="mt-4 space-y-2 text-[11px] mb-5">
                                <li 
                                    v-for="feat in plan.features" 
                                    :key="feat.text" 
                                    class="flex items-start gap-1.5"
                                    :class="feat.supported ? 'text-foreground' : 'text-muted-foreground opacity-60'"
                                >
                                    <Check v-if="feat.supported" class="size-3.5 flex-shrink-0" :class="plan.isRecommended ? 'text-primary' : plan.isVip ? 'text-violet-500' : 'text-emerald-500'" />
                                    <X v-else class="size-3.5 flex-shrink-0 text-muted-foreground" />
                                    <span>{{ feat.text }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Nút hành động trực tiếp -->
                        <div class="mt-auto pt-2">
                            <Button 
                                v-if="plan.code === tenant?.plan?.code?.toLowerCase()"
                                disabled 
                                variant="secondary" 
                                class="w-full text-xs font-semibold"
                            >
                                Gói hiện tại
                            </Button>
                            <Button 
                                v-else
                                :variant="plan.isRecommended ? 'default' : 'outline'" 
                                class="w-full text-xs font-semibold"
                                :class="[
                                    plan.isRecommended 
                                        ? 'bg-gradient-to-r from-primary to-primary/80 hover:from-primary/95 hover:to-primary/75 text-white border-0' 
                                        : plan.isVip 
                                            ? 'bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white border-0' 
                                            : ''
                                ]"
                                @click="goToUpgradeCheckout(plan.code)"
                            >
                                {{ plan.code === 'free' ? 'Chọn Free' : 'Nâng cấp ngay' }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Footer Modal -->
                <div class="px-6 py-5 border-t border-border bg-muted/30 flex flex-col sm:flex-row gap-2 justify-between items-center text-xs">
                    <span class="text-muted-foreground flex items-center gap-1">
                        <Lock class="size-3.5 text-muted-foreground" />
                        Giao dịch thanh toán mã hóa an toàn.
                    </span>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" @click="closeUpgradeModal">Đóng</Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
