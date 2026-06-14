<script setup lang="ts">
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
import { ref, onMounted, onUnmounted } from 'vue';
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

const couponCode = ref('');
const isApplyingCoupon = ref(false);
const couponError = ref('');
const couponSuccess = ref('');

const currentAmount = ref(props.bank_details.amount);
const discountAmount = ref(0);
const qrCodeUrl = ref(props.payment_url);

async function applyCoupon() {
    if (!couponCode.value.trim()) {
return;
}

    isApplyingCoupon.value = true;
    couponError.value = '';
    couponSuccess.value = '';

    try {
        const response = await fetch('/api/billing/apply-coupon', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                transaction_code: props.subscription.transaction_code,
                coupon_code: couponCode.value.trim()
            })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            currentAmount.value = data.new_price;
            discountAmount.value = data.discount_amount;
            qrCodeUrl.value = data.payment_url;
            couponSuccess.value = data.message;
        } else {
            couponError.value = data.message || 'Mã giảm giá không hợp lệ.';
        }
    } catch (e) {
        console.error('Error applying coupon:', e);
        couponError.value = 'Lỗi hệ thống khi áp dụng mã giảm giá.';
    } finally {
        isApplyingCoupon.value = false;
    }
}

let pollInterval: any = null;

// Sao chép nhanh vào clipboard
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

// Định dạng tiền tệ
function formatCurrency(val: number) {
    return val.toLocaleString('vi-VN') + ' ₫';
}

// Kiểm tra trạng thái thanh toán từ API
async function checkPaymentStatus(silent = true) {
    if (!silent) {
isChecking.value = true;
}

    try {
        const response = await fetch(`/api/billing/check/${props.subscription.transaction_code}`);
        const data = await response.json();
        
        if (data.active) {
            triggerSuccess();
        }
    } catch (e) {
        console.error('Lỗi khi kiểm tra trạng thái thanh toán:', e);
    } finally {
        if (!silent) {
isChecking.value = false;
}
    }
}

// Kích hoạt giao diện thành công và chuyển trang
function triggerSuccess() {
    isSuccess.value = true;

    if (pollInterval) {
clearInterval(pollInterval);
}
    
    // Đếm ngược chuyển hướng về Dashboard
    const timer = setInterval(() => {
        countdown.value--;

        if (countdown.value <= 0) {
            clearInterval(timer);
            router.visit('/dashboard');
        }
    }, 1000);
}

onMounted(() => {
    // Tự động kiểm tra mỗi 3 giây
    pollInterval = setInterval(() => {
        checkPaymentStatus(true);
    }, 3000);
});

onUnmounted(() => {
    if (pollInterval) {
clearInterval(pollInterval);
}
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Thanh toán dịch vụ',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head title="Thanh toán hóa đơn dịch vụ" />

    <div class="px-4 py-6 space-y-6 max-w-5xl mx-auto w-full">
        
        <!-- 1. MÀN HÌNH THANH TOÁN THÀNH CÔNG -->
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
                Thanh Toán Thành Công!
            </h2>
            
            <p class="text-sm text-muted-foreground leading-relaxed max-w-md mx-auto mb-8">
                Cảm ơn bạn! Hệ thống đã nhận được tiền chuyển khoản và tự động kích hoạt gói dịch vụ chuyên nghiệp **{{ subscription.plan_name }}** thành công.
            </p>

            <div class="rounded-xl bg-muted border border-border p-4 inline-flex items-center gap-3 justify-center w-full max-w-xs mx-auto">
                <RefreshCw class="size-4 animate-spin text-emerald-500 dark:text-emerald-400" />
                <span class="text-xs font-semibold text-muted-foreground">
                    Đang chuyển hướng về Dashboard sau {{ countdown }}s...
                </span>
            </div>
        </div>

        <!-- 2. MÀN HÌNH QUÉT MÃ QR THANH TOÁN CHÍNH -->
        <div 
            v-else 
            class="w-full grid gap-8 md:grid-cols-12 rounded-2xl border border-border bg-card p-6 sm:p-8 shadow-sm animate-in fade-in duration-500"
        >
            
            <!-- BÊN TRÁI: THÔNG TIN CHI TIẾT GÓI VÀ TÀI KHOẢN -->
            <div class="md:col-span-7 flex flex-col justify-between space-y-6">
                <div class="space-y-6">
                    <!-- Thông tin gói đang mua -->
                    <div class="space-y-2">
                        <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 border border-primary/20 px-2.5 py-1 text-xs font-bold text-primary uppercase tracking-wider">
                            <Sparkles class="size-3.5" /> Gói {{ subscription.plan_name }}
                        </span>
                        <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                            Xác nhận thanh toán dịch vụ
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Hãy quét mã QR bên phải hoặc chuyển khoản thủ công theo thông tin bên dưới để hoàn tất.
                        </p>
                    </div>

                    <!-- Card chi tiết thông tin chuyển khoản -->
                    <div class="space-y-3 rounded-xl bg-muted/40 border border-border p-5">
                        
                        <!-- Hàng Số Tiền -->
                        <div class="flex flex-col gap-1.5 pb-3 border-b border-border/60">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2 text-muted-foreground text-xs">
                                    <DollarSign class="size-4 text-primary" />
                                    <span>Số tiền chuyển khoản</span>
                                </div>
                                <span class="text-lg font-extrabold text-primary">
                                    {{ formatCurrency(currentAmount) }}
                                </span>
                            </div>
                            <div v-if="discountAmount > 0" class="flex items-center justify-between text-[11px] px-1">
                                <span class="text-muted-foreground">Giá gốc: <del>{{ formatCurrency(bank_details.amount) }}</del></span>
                                <span class="text-emerald-600 font-bold">-{{ formatCurrency(discountAmount) }}</span>
                            </div>
                        </div>

                        <!-- Hàng Nội Dung -->
                        <div class="flex items-center justify-between py-2 border-b border-border/60">
                            <div class="flex items-center gap-2 text-muted-foreground text-xs">
                                <Hash class="size-4" />
                                <span>Nội dung chuyển khoản (Memo)</span>
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

                        <!-- Hàng Ngân Hàng -->
                        <div class="flex items-center justify-between py-2 border-b border-border/60">
                            <div class="flex items-center gap-2 text-muted-foreground text-xs">
                                <CreditCard class="size-4" />
                                <span>Ngân hàng thụ hưởng</span>
                            </div>
                            <span class="text-xs font-bold text-foreground">
                                {{ bank_details.bank }}
                            </span>
                        </div>

                        <!-- Hàng Số Tài Khoản -->
                        <div class="flex items-center justify-between py-2 border-b border-border/60">
                            <div class="flex items-center gap-2 text-muted-foreground text-xs">
                                <CreditCard class="size-4" />
                                <span>Số tài khoản</span>
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

                        <!-- Hàng Tên Tài Khoản -->
                        <div class="flex items-center justify-between pt-2">
                            <div class="flex items-center gap-2 text-muted-foreground text-xs">
                                <User class="size-4" />
                                <span>Tên chủ tài khoản</span>
                            </div>
                            <span class="text-xs font-bold text-foreground uppercase tracking-wide">
                                {{ bank_details.account_name }}
                            </span>
                        </div>

                    </div>

                    <!-- Mã giảm giá -->
                    <div class="rounded-xl border border-border p-4 bg-muted/20 space-y-2">
                        <label class="text-xs font-semibold text-muted-foreground block">Mã giảm giá (Coupon Code)</label>
                        <div class="flex gap-2">
                            <input 
                                v-model="couponCode" 
                                type="text" 
                                placeholder="Nhập mã giảm giá..." 
                                class="flex-grow rounded-lg border border-input bg-background px-3 py-1.5 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                :disabled="isApplyingCoupon || discountAmount > 0"
                                @keyup.enter="applyCoupon"
                            />
                            <Button 
                                size="sm" 
                                @click="applyCoupon" 
                                :disabled="isApplyingCoupon || discountAmount > 0 || !couponCode.trim()"
                            >
                                {{ isApplyingCoupon ? 'Đang áp dụng...' : 'Áp dụng' }}
                            </Button>
                        </div>
                        <p v-if="couponError" class="text-xs text-rose-500 font-medium animate-in fade-in">{{ couponError }}</p>
                        <p v-if="couponSuccess" class="text-xs text-emerald-600 font-medium animate-in fade-in">{{ couponSuccess }}</p>
                    </div>
                </div>

                <!-- Footer thông tin bảo mật -->
                <div class="pt-4 flex flex-col sm:flex-row gap-4 items-center justify-between border-t border-border/60">
                    <div class="flex items-center gap-2 text-[10px] sm:text-xs text-muted-foreground">
                        <ShieldCheck class="size-4 text-emerald-500 shrink-0" />
                        <span>Giao dịch mã hóa an toàn & Tự động xử lý</span>
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
                            Kiểm tra lại
                        </Button>
                        <Button 
                            variant="secondary" 
                            size="sm" 
                            class="w-full sm:w-auto text-xs"
                            @click="router.visit('/dashboard')"
                        >
                            <ArrowLeft class="size-3.5 mr-1.5" />
                            Quay lại
                        </Button>
                    </div>
                </div>
            </div>

            <!-- BÊN PHẢI: MÃ QR QUÉT MÃ -->
            <div class="md:col-span-5 flex flex-col items-center justify-center bg-muted/20 border border-border rounded-2xl p-6 sm:p-8 text-center relative overflow-hidden">
                
                <!-- Hộp trạng thái Live -->
                <div class="absolute top-4 left-4 inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 text-[10px] font-semibold text-amber-600 dark:text-amber-400 animate-pulse">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                    <span>Đang chờ chuyển khoản...</span>
                </div>

                <!-- Viền phát sáng nhẹ xung quanh QR -->
                <div class="relative group mt-6 mb-4">
                    <div class="absolute -inset-1 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 opacity-20 blur-md"></div>
                    
                    <div class="relative rounded-xl border border-border bg-white p-3.5 shadow-md max-w-[210px] sm:max-w-[240px] aspect-square flex items-center justify-center">
                        <img 
                            :src="qrCodeUrl" 
                            alt="Mã QR thanh toán SePay" 
                            class="w-full h-full object-contain"
                        />
                    </div>
                </div>

                <p class="text-[10px] sm:text-xs text-muted-foreground max-w-[220px] leading-relaxed">
                    Mở ứng dụng ngân hàng quét mã QR này để tự động điền số tài khoản, số tiền và nội dung chuyển khoản.
                </p>
            </div>

        </div>

    </div>
</template>
