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

function openUpgradeModal() {
    isUpgradeModalOpen.value = true;
}

function closeUpgradeModal() {
    isUpgradeModalOpen.value = false;
}

function goToUpgradeCheckout() {
    window.location.href = '/billing/checkout?plan=pro';
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
            <div class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-border bg-card shadow-2xl transition-all">
                
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
                        Nâng tầm quán ăn của bạn cùng Gói Pro
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Mở khóa toàn diện sức mạnh của hệ thống quản lý Aventura thông minh.
                    </p>
                </div>

                <!-- So sánh hai gói -->
                <div class="px-6 py-4 grid gap-4 md:grid-cols-2">
                    <!-- Gói Free -->
                    <div class="rounded-xl border border-border bg-muted/40 p-4 relative opacity-70">
                        <h4 class="text-sm font-semibold text-foreground">Gói Miễn phí (Free)</h4>
                        <p class="mt-1 text-xs text-muted-foreground">Dành cho quán ăn mới lập nghiệp để làm quen vận hành.</p>
                        <p class="mt-3 text-lg font-bold text-foreground">0đ <span class="text-xs font-normal text-muted-foreground">/ tháng</span></p>
                        
                        <ul class="mt-4 space-y-2 text-xs">
                            <li class="flex items-center gap-2"><Check class="size-3.5 text-amber-500" /> Tối đa 1 chi nhánh</li>
                            <li class="flex items-center gap-2"><Check class="size-3.5 text-amber-500" /> Tối đa 10 bàn hoạt động</li>
                            <li class="flex items-center gap-2"><Check class="size-3.5 text-amber-500" /> Tối đa 5 nhân viên</li>
                            <li class="flex items-center gap-2 text-muted-foreground"><X class="size-3.5" /> Không hỗ trợ AI Dự báo kho</li>
                            <li class="flex items-center gap-2 text-muted-foreground"><X class="size-3.5" /> Không hỗ trợ AI phát hiện gian lận</li>
                        </ul>
                    </div>

                    <!-- Gói Pro -->
                    <div class="rounded-xl border-2 border-primary bg-gradient-to-b from-primary/5 to-transparent p-4 relative shadow-md">
                        <div class="absolute top-3 right-3 rounded-full bg-primary/10 px-2 py-0.5 text-[9px] font-bold text-primary uppercase">
                            VIP
                        </div>
                        <h4 class="text-sm font-semibold text-foreground flex items-center gap-1">
                            Gói Cao cấp (Pro)
                        </h4>
                        <p class="mt-1 text-xs text-muted-foreground">Tối ưu hiệu năng, chống thất thoát cho mô hình chuyên nghiệp.</p>
                        <p class="mt-3 text-lg font-bold text-primary">499.000đ <span class="text-xs font-normal text-muted-foreground">/ tháng</span></p>
                        
                        <ul class="mt-4 space-y-2 text-xs">
                            <li class="flex items-center gap-2 font-medium text-foreground"><Check class="size-3.5 text-primary" /> Không giới hạn bàn & nhân sự</li>
                            <li class="flex items-center gap-2 font-medium text-foreground"><Check class="size-3.5 text-primary" /> Quản lý nhiều chi nhánh</li>
                            <li class="flex items-center gap-2 font-medium text-foreground"><Check class="size-3.5 text-primary" /> AI dự báo nguyên liệu & tồn kho</li>
                            <li class="flex items-center gap-2 font-medium text-foreground"><Check class="size-3.5 text-primary" /> Thuật toán AI phát hiện gian lận</li>
                            <li class="flex items-center gap-2 font-medium text-foreground"><Check class="size-3.5 text-primary" /> Hệ thống Audit Log bảo mật</li>
                        </ul>
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
                        <Button size="sm" class="bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white border-0" @click="goToUpgradeCheckout">
                            Nâng cấp gói ngay
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
