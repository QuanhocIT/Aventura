<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import { 
    Sparkles, 
    Copy, 
    Check, 
    ArrowLeft, 
    RefreshCw, 
    ShieldCheck, 
    DollarSign, 
    Hash, 
    CreditCard, 
    User
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    subscription: {
        transaction_code: string;
        price: number;
        plan_name: string;
        plan_code: string;
    };
    bank_details: {
        bank: string;
        account_number: string;
        account_name: string;
        amount: number;
        content: string;
    };
    payment_url: string;
}>();

const copiedField = ref<string | null>(null);
const isSuccess = ref(false);
const isChecking = ref(false);
const countdown = ref(3);

let pollInterval: any = null;

// Sao chÃ©p nhanh vÃ o clipboard
function copyToClipboard(text: string, fieldName: string) {
    navigator.clipboard.writeText(text).then(() => {
        copiedField.value = fieldName;
        setTimeout(() => {
            if (copiedField.value === fieldName) {
                copiedField.value = null;
            }
        }, 2000);
    });
}

// Äá»‹nh dáº¡ng tiá»n tá»‡
function formatCurrency(val: number) {
    return val.toLocaleString('vi-VN') + ' â‚«';
}

// Kiá»ƒm tra tráº¡ng thÃ¡i thanh toÃ¡n tá»« API
async function checkPaymentStatus(silent = true) {
    if (!silent) isChecking.value = true;
    try {
        const response = await fetch(`/api/billing/check/${props.subscription.transaction_code}`);
        const data = await response.json();
        
        if (data.active) {
            triggerSuccess();
        }
    } catch (e) {
        console.error('Lá»—i khi kiá»ƒm tra tráº¡ng thÃ¡i thanh toÃ¡n:', e);
    } finally {
        if (!silent) isChecking.value = false;
    }
}

// KÃ­ch hoáº¡t giao diá»‡n thÃ nh cÃ´ng vÃ  chuyá»ƒn trang
function triggerSuccess() {
    isSuccess.value = true;
    if (pollInterval) clearInterval(pollInterval);
    
    // Äáº¿m ngÆ°á»£c chuyá»ƒn hÆ°á»›ng vá» Dashboard
    const timer = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            clearInterval(timer);
            router.visit('/dashboard', {
                flash: { success: 'GÃ³i dá»‹ch vá»¥ Ä‘Ã£ Ä‘Æ°á»£c kÃ­ch hoáº¡t thÃ nh cÃ´ng!' }
            });
        }
    }, 1000);
}

onMounted(() => {
    // Tá»± Ä‘á»™ng kiá»ƒm tra má»—i 3 giÃ¢y
    pollInterval = setInterval(() => {
        checkPaymentStatus(true);
    }, 3000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Thanh toÃ¡n dá»‹ch vá»¥',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head title="Thanh toÃ¡n hÃ³a Ä‘Æ¡n dá»‹ch vá»¥" />

    <div class="px-4 py-6 space-y-6 max-w-5xl mx-auto w-full">
        
        <!-- 1. MÃ€N HÃŒNH THANH TOÃN THÃ€NH CÃ”NG -->
        <div 
            v-if="isSuccess" 
            class="w-full max-w-xl mx-auto rounded-2xl border border-emerald-500/20 bg-card p-8 sm:p-12 text-center shadow-lg animate-in fade-in zoom-in duration-500"
        >
            <div class="relative mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-emerald-500/10 border border-emerald-500/20">
                <Check class="size-10 text-emerald-500 dark:text-emerald-400 animate-bounce" />
                <Sparkles class="absolute top-0 right-0 size-4 text-amber-500 animate-pulse" />
                <Sparkles class="absolute bottom-2 left-0 size-3 text-emerald-500 animate-pulse delay-100" />
            </div>

            <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-foreground mb-3">
                Thanh ToÃ¡n ThÃ nh CÃ´ng!
            </h2>
            
            <p class="text-sm text-muted-foreground leading-relaxed max-w-md mx-auto mb-8">
                Cáº£m Æ¡n báº¡n! Há»‡ thá»‘ng Ä‘Ã£ nháº­n Ä‘Æ°á»£c tiá»n chuyá»ƒn khoáº£n vÃ  tá»± Ä‘á»™ng kÃ­ch hoáº¡t gÃ³i dá»‹ch vá»¥ chuyÃªn nghiá»‡p **{{ subscription.plan_name }}** thÃ nh cÃ´ng.
            </p>

            <div class="rounded-xl bg-muted border border-border p-4 inline-flex items-center gap-3 justify-center w-full max-w-xs mx-auto">
                <RefreshCw class="size-4 animate-spin text-emerald-500 dark:text-emerald-400" />
                <span class="text-xs font-semibold text-muted-foreground">
                    Äang chuyá»ƒn hÆ°á»›ng vá» Dashboard sau {{ countdown }}s...
                </span>
            </div>
        </div>

        <!-- 2. MÃ€N HÃŒNH QUÃ‰T MÃƒ QR THANH TOÃN CHÃNH -->
        <div 
            v-else 
            class="w-full grid gap-8 md:grid-cols-12 rounded-2xl border border-border bg-card p-6 sm:p-8 shadow-sm animate-in fade-in duration-500"
        >
            
            <!-- BÃŠN TRÃI: THÃ”NG TIN CHI TIáº¾T GÃ“I VÃ€ TÃ€I KHOáº¢N -->
            <div class="md:col-span-7 flex flex-col justify-between space-y-6">
                <div class="space-y-6">
                    <!-- ThÃ´ng tin gÃ³i Ä‘ang mua -->
                    <div class="space-y-2">
                        <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 border border-primary/20 px-2.5 py-1 text-xs font-bold text-primary uppercase tracking-wider">
                            <Sparkles class="size-3.5" /> GÃ³i {{ subscription.plan_name }}
                        </span>
                        <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                            XÃ¡c nháº­n thanh toÃ¡n dá»‹ch vá»¥
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            HÃ£y quÃ©t mÃ£ QR bÃªn pháº£i hoáº·c chuyá»ƒn khoáº£n thá»§ cÃ´ng theo thÃ´ng tin bÃªn dÆ°á»›i Ä‘á»ƒ hoÃ n táº¥t.
                        </p>
                    </div>

                    <!-- Card chi tiáº¿t thÃ´ng tin chuyá»ƒn khoáº£n -->
                    <div class="space-y-3 rounded-xl bg-muted/40 border border-border p-5">
                        
                        <!-- HÃ ng Sá»‘ Tiá»n -->
                        <div class="flex items-center justify-between pb-3 border-b border-border/60">
                            <div class="flex items-center gap-2 text-muted-foreground text-xs">
                                <DollarSign class="size-4 text-primary" />
                                <span>Sá»‘ tiá»n chuyá»ƒn khoáº£n</span>
                            </div>
                            <span class="text-lg font-extrabold text-primary">
                                {{ formatCurrency(bank_details.amount) }}
                            </span>
                        </div>

                        <!-- HÃ ng Ná»™i Dung -->
                        <div class="flex items-center justify-between py-2 border-b border-border/60">
                            <div class="flex items-center gap-2 text-muted-foreground text-xs">
                                <Hash class="size-4" />
                                <span>Ná»™i dung chuyá»ƒn khoáº£n (Memo)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm font-bold text-foreground bg-muted px-2 py-1 rounded border border-border">
                                    {{ bank_details.content }}
                                </span>
                                <button 
                                    @click="copyToClipboard(bank_details.content, 'content')"
                                    class="p-1.5 rounded-lg bg-muted border border-border text-muted-foreground hover:text-foreground transition-colors"
                                >
                                    <Check v-if="copiedField === 'content'" class="size-3.5 text-emerald-500" />
                                    <Copy v-else class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- HÃ ng NgÃ¢n HÃ ng -->
                        <div class="flex items-center justify-between py-2 border-b border-border/60">
                            <div class="flex items-center gap-2 text-muted-foreground text-xs">
                                <CreditCard class="size-4" />
                                <span>NgÃ¢n hÃ ng thá»¥ hÆ°á»Ÿng</span>
                            </div>
                            <span class="text-xs font-bold text-foreground">
                                {{ bank_details.bank }}
                            </span>
                        </div>

                        <!-- HÃ ng Sá»‘ TÃ i Khoáº£n -->
                        <div class="flex items-center justify-between py-2 border-b border-border/60">
                            <div class="flex items-center gap-2 text-muted-foreground text-xs">
                                <CreditCard class="size-4" />
                                <span>Sá»‘ tÃ i khoáº£n</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-bold text-foreground">
                                    {{ bank_details.account_number }}
                                </span>
                                <button 
                                    @click="copyToClipboard(bank_details.account_number, 'account')"
                                    class="p-1.5 rounded-lg bg-muted border border-border text-muted-foreground hover:text-foreground transition-colors"
                                >
                                    <Check v-if="copiedField === 'account'" class="size-3.5 text-emerald-500" />
                                    <Copy v-else class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- HÃ ng TÃªn TÃ i Khoáº£n -->
                        <div class="flex items-center justify-between pt-2">
                            <div class="flex items-center gap-2 text-muted-foreground text-xs">
                                <User class="size-4" />
                                <span>TÃªn chá»§ tÃ i khoáº£n</span>
                            </div>
                            <span class="text-xs font-bold text-foreground uppercase tracking-wide">
                                {{ bank_details.account_name }}
                            </span>
                        </div>

                    </div>
                </div>

                <!-- Footer thÃ´ng tin báº£o máº­t -->
                <div class="pt-4 flex flex-col sm:flex-row gap-4 items-center justify-between border-t border-border/60">
                    <div class="flex items-center gap-2 text-[10px] sm:text-xs text-muted-foreground">
                        <ShieldCheck class="size-4 text-emerald-500 shrink-0" />
                        <span>Giao dá»‹ch mÃ£ hÃ³a an toÃ n & Tá»± Ä‘á»™ng xá»­ lÃ½</span>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <Button 
                            variant="outline" 
                            size="sm" 
                            class="border-border bg-background text-muted-foreground hover:text-foreground w-full sm:w-auto text-xs"
                            @click="checkPaymentStatus(false)"
                            :disabled="isChecking"
                        >
                            <RefreshCw class="size-3.5 mr-1.5" :class="isChecking ? 'animate-spin' : ''" />
                            Kiá»ƒm tra láº¡i
                        </Button>
                        <Button 
                            variant="secondary" 
                            size="sm" 
                            class="w-full sm:w-auto text-xs"
                            @click="router.visit('/dashboard')"
                        >
                            <ArrowLeft class="size-3.5 mr-1.5" />
                            Quay láº¡i
                        </Button>
                    </div>
                </div>
            </div>

            <!-- BÃŠN PHáº¢I: MÃƒ QR QUÃ‰T MÃƒ -->
            <div class="md:col-span-5 flex flex-col items-center justify-center bg-muted/20 border border-border rounded-2xl p-6 sm:p-8 text-center relative overflow-hidden">
                
                <!-- Há»™p tráº¡ng thÃ¡i Live -->
                <div class="absolute top-4 left-4 inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 text-[10px] font-semibold text-amber-600 dark:text-amber-400 animate-pulse">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                    <span>Äang chá» chuyá»ƒn khoáº£n...</span>
                </div>

                <!-- Viá»n phÃ¡t sÃ¡ng nháº¹ xung quanh QR -->
                <div class="relative group mt-6 mb-4">
                    <div class="absolute -inset-1 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 opacity-20 blur-md"></div>
                    
                    <div class="relative rounded-xl border border-border bg-white p-3.5 shadow-md max-w-[210px] sm:max-w-[240px] aspect-square flex items-center justify-center">
                        <img 
                            :src="payment_url" 
                            alt="MÃ£ QR thanh toÃ¡n SePay" 
                            class="w-full h-full object-contain"
                        />
                    </div>
                </div>

                <p class="text-[10px] sm:text-xs text-muted-foreground max-w-[220px] leading-relaxed">
                    Má»Ÿ á»©ng dá»¥ng ngÃ¢n hÃ ng quÃ©t mÃ£ QR nÃ y Ä‘á»ƒ tá»± Ä‘á»™ng Ä‘iá»n sá»‘ tÃ i khoáº£n, sá»‘ tiá»n vÃ  ná»™i dung chuyá»ƒn khoáº£n.
                </p>
            </div>

        </div>

    </div>
</template>
