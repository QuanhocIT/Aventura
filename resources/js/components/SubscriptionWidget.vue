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

// State Ä‘á»ƒ hiá»ƒn thá»‹ Modal NÃ¢ng cáº¥p
const isUpgradeModalOpen = ref(false);

if (!tenant.value) {
    // KhÃ´ng hiá»ƒn thá»‹ náº¿u chÆ°a Ä‘Äƒng nháº­p / khÃ´ng thuá»™c nhÃ  hÃ ng nÃ o
}

const planName = computed(() => tenant.value?.plan?.name ?? 'Miá»…n phÃ­');
const planCode = computed(() => tenant.value?.plan?.code?.toUpperCase() ?? 'FREE');
const isFree = computed(() => planCode.value === 'FREE');
const isTrial = computed(() => tenant.value?.status === 'trial');

// TÃ­nh sá»‘ ngÃ y cÃ²n láº¡i cá»§a trial hoáº·c subscription
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

// Thá»‘ng kÃª tÃ i nguyÃªn
const resources = computed(() => {
    const summary = tenant.value?.quota_summary?.resources ?? {};
    return [
        {
            key: 'branches',
            label: 'Chi nhÃ¡nh',
            icon: Building2,
            used: summary.branches?.used ?? 0,
            limit: summary.branches?.limit,
            unlimited: summary.branches?.unlimited ?? false,
            percentage: summary.branches?.percentage ?? 0
        },
        {
            key: 'tables',
            label: 'BÃ n hoáº¡t Ä‘á»™ng',
            icon: Grid,
            used: summary.tables?.used ?? 0,
            limit: summary.tables?.limit,
            unlimited: summary.tables?.unlimited ?? false,
            percentage: summary.tables?.percentage ?? 0
        },
        {
            key: 'employees',
            label: 'NhÃ¢n viÃªn',
            icon: Users2,
            used: summary.employees?.used ?? 0,
            limit: summary.employees?.limit,
            unlimited: summary.employees?.unlimited ?? false,
            percentage: summary.employees?.percentage ?? 0
        }
    ];
});

// Quyáº¿t Ä‘á»‹nh mÃ u sáº¯c cá»§a thanh tiáº¿n trÃ¬nh dá»±a trÃªn tá»‰ lá»‡ pháº§n trÄƒm sá»­ dá»¥ng
function getProgressColorClass(pct: number) {
    if (pct >= 90) return 'bg-rose-500'; // Äá» khi Ä‘áº§y / gáº§n Ä‘áº§y
    if (pct >= 70) return 'bg-amber-500'; // Cam khi á»Ÿ má»©c cáº£nh bÃ¡o
    return 'bg-emerald-500'; // Xanh lÃ¡ khi an toÃ n
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
        <!-- Widget hiá»ƒn thá»‹ thÃ´ng tin gÃ³i -->
        <div class="relative overflow-hidden rounded-xl border border-border/60 bg-gradient-to-b from-card to-background/50 p-4 shadow-sm backdrop-blur-md">
            
            <!-- Hiá»‡u á»©ng sÃ¡ng má» á»Ÿ background cho gÃ³i PRO -->
            <div v-if="!isFree" class="absolute -right-10 -top-10 h-24 w-24 rounded-full bg-primary/10 blur-2xl pointer-events-none"></div>
            
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" :class="isFree ? 'bg-amber-400' : 'bg-emerald-400'"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2" :class="isFree ? 'bg-amber-500' : 'bg-emerald-500'"></span>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        GÃ³i {{ planName }}
                    </span>
                </div>
                
                <!-- Huy hiá»‡u VIP cho PRO -->
                <span v-if="!isFree" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                    <Sparkles class="size-3" /> PRO
                </span>
            </div>

            <!-- Cáº£nh bÃ¡o dÃ¹ng thá»­ hoáº·c háº¿t háº¡n -->
            <div v-if="isTrial && daysRemaining !== null" class="mb-4 rounded-lg bg-amber-500/10 border border-amber-500/20 p-2.5 text-xs text-amber-600 dark:text-amber-400">
                <p class="font-medium flex items-center gap-1.5">
                    <Sparkles class="size-3.5 animate-pulse" />
                    Äang thá»­ nghiá»‡m PRO (CÃ²n {{ daysRemaining }} ngÃ y)
                </p>
            </div>

            <!-- Chi tiáº¿t háº¡n ngáº¡ch tÃ i nguyÃªn -->
            <div class="space-y-3.5">
                <div v-for="res in resources" :key="res.key" class="space-y-1.5">
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5 text-muted-foreground">
                            <component :is="res.icon" class="size-3.5" />
                            <span>{{ res.label }}</span>
                        </div>
                        <span class="font-medium text-foreground">
                            {{ res.used }} / <span v-if="res.unlimited">âˆž</span><span v-else>{{ res.limit }}</span>
                        </span>
                    </div>
                    
                    <!-- Thanh tiáº¿n trÃ¬nh custom mÆ°á»£t mÃ  -->
                    <div class="h-1.5 w-full rounded-full bg-secondary overflow-hidden">
                        <div 
                            class="h-full rounded-full transition-all duration-700 ease-out" 
                            :class="res.unlimited ? 'bg-primary' : getProgressColorClass(res.percentage)"
                            :style="{ width: (res.unlimited ? 100 : res.percentage) + '%' }"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- NÃºt nÃ¢ng cáº¥p hoáº·c Ä‘á»•i gÃ³i -->
            <div class="mt-4 pt-3 border-t border-border/40">
                <button 
                    @click="openUpgradeModal"
                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 transition-all duration-200 shadow-sm shadow-violet-500/15"
                >
                    <Sparkles class="size-3.5" />
                    <span>{{ isFree ? 'NÃ¢ng cáº¥p lÃªn gÃ³i PRO' : 'Xem thÃ´ng tin dá»‹ch vá»¥' }}</span>
                    <ArrowUpRight class="size-3.5" />
                </button>
            </div>
        </div>

        <!-- MODAL NÃ‚NG Cáº¤P GÃ“I CHUYÃŠN NGHIá»†P VÃ€ SANG TRá»ŒNG -->
        <div v-if="isUpgradeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-all duration-300">
            <div class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-border bg-card shadow-2xl transition-all">
                
                <!-- NÃºt Ä‘Ã³ng -->
                <button @click="closeUpgradeModal" class="absolute top-4 right-4 rounded-full p-1.5 text-muted-foreground hover:bg-muted transition-colors">
                    <X class="size-5" />
                </button>

                <!-- Header Modal -->
                <div class="px-6 pt-8 pb-4 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                        <Sparkles class="size-6 text-primary animate-pulse" />
                    </div>
                    <h3 class="text-xl font-bold tracking-tight text-foreground">
                        NÃ¢ng táº§m quÃ¡n Äƒn cá»§a báº¡n cÃ¹ng GÃ³i Pro
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Má»Ÿ khÃ³a toÃ n diá»‡n sá»©c máº¡nh cá»§a há»‡ thá»‘ng quáº£n lÃ½ Aventura thÃ´ng minh.
                    </p>
                </div>

                <!-- So sÃ¡nh hai gÃ³i -->
                <div class="px-6 py-4 grid gap-4 md:grid-cols-2">
                    <!-- GÃ³i Free -->
                    <div class="rounded-xl border border-border bg-muted/40 p-4 relative opacity-70">
                        <h4 class="text-sm font-semibold text-foreground">GÃ³i Miá»…n phÃ­ (Free)</h4>
                        <p class="mt-1 text-xs text-muted-foreground">DÃ nh cho quÃ¡n Äƒn má»›i láº­p nghiá»‡p Ä‘á»ƒ lÃ m quen váº­n hÃ nh.</p>
                        <p class="mt-3 text-lg font-bold text-foreground">0Ä‘ <span class="text-xs font-normal text-muted-foreground">/ thÃ¡ng</span></p>
                        
                        <ul class="mt-4 space-y-2 text-xs">
                            <li class="flex items-center gap-2"><Check class="size-3.5 text-amber-500" /> Tá»‘i Ä‘a 1 chi nhÃ¡nh</li>
                            <li class="flex items-center gap-2"><Check class="size-3.5 text-amber-500" /> Tá»‘i Ä‘a 10 bÃ n hoáº¡t Ä‘á»™ng</li>
                            <li class="flex items-center gap-2"><Check class="size-3.5 text-amber-500" /> Tá»‘i Ä‘a 5 nhÃ¢n viÃªn</li>
                            <li class="flex items-center gap-2 text-muted-foreground"><X class="size-3.5" /> KhÃ´ng há»— trá»£ AI Dá»± bÃ¡o kho</li>
                            <li class="flex items-center gap-2 text-muted-foreground"><X class="size-3.5" /> KhÃ´ng há»— trá»£ AI phÃ¡t hiá»‡n gian láº­n</li>
                        </ul>
                    </div>

                    <!-- GÃ³i Pro -->
                    <div class="rounded-xl border-2 border-primary bg-gradient-to-b from-primary/5 to-transparent p-4 relative shadow-md">
                        <div class="absolute top-3 right-3 rounded-full bg-primary/10 px-2 py-0.5 text-[9px] font-bold text-primary uppercase">
                            VIP
                        </div>
                        <h4 class="text-sm font-semibold text-foreground flex items-center gap-1">
                            GÃ³i Cao cáº¥p (Pro)
                        </h4>
                        <p class="mt-1 text-xs text-muted-foreground">Tá»‘i Æ°u hiá»‡u nÄƒng, chá»‘ng tháº¥t thoÃ¡t cho mÃ´ hÃ¬nh chuyÃªn nghiá»‡p.</p>
                        <p class="mt-3 text-lg font-bold text-primary">499.000Ä‘ <span class="text-xs font-normal text-muted-foreground">/ thÃ¡ng</span></p>
                        
                        <ul class="mt-4 space-y-2 text-xs">
                            <li class="flex items-center gap-2 font-medium text-foreground"><Check class="size-3.5 text-primary" /> KhÃ´ng giá»›i háº¡n bÃ n & nhÃ¢n sá»±</li>
                            <li class="flex items-center gap-2 font-medium text-foreground"><Check class="size-3.5 text-primary" /> Quáº£n lÃ½ nhiá»u chi nhÃ¡nh</li>
                            <li class="flex items-center gap-2 font-medium text-foreground"><Check class="size-3.5 text-primary" /> AI dá»± bÃ¡o nguyÃªn liá»‡u & tá»“n kho</li>
                            <li class="flex items-center gap-2 font-medium text-foreground"><Check class="size-3.5 text-primary" /> Thuáº­t toÃ¡n AI phÃ¡t hiá»‡n gian láº­n</li>
                            <li class="flex items-center gap-2 font-medium text-foreground"><Check class="size-3.5 text-primary" /> Há»‡ thá»‘ng Audit Log báº£o máº­t</li>
                        </ul>
                    </div>
                </div>

                <!-- Footer Modal -->
                <div class="px-6 py-5 border-t border-border bg-muted/30 flex flex-col sm:flex-row gap-2 justify-between items-center text-xs">
                    <span class="text-muted-foreground flex items-center gap-1">
                        <Lock class="size-3.5 text-muted-foreground" />
                        Giao dá»‹ch thanh toÃ¡n mÃ£ hÃ³a an toÃ n.
                    </span>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" @click="closeUpgradeModal">ÄÃ³ng</Button>
                        <Button size="sm" class="bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white border-0" @click="goToUpgradeCheckout">
                            NÃ¢ng cáº¥p gÃ³i ngay
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
