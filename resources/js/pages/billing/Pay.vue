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
    User,
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
        const response = await fetch(
            `/api/billing/check/${props.subscription.transaction_code}`,
        );
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

    <div class="mx-auto w-full max-w-5xl space-y-6 px-4 py-6">
        <!-- 1. MÀN HÌNH THANH TOÁN THÀNH CÔNG -->
        <div
            v-if="isSuccess"
            class="mx-auto w-full max-w-xl animate-in rounded-2xl border border-emerald-500/20 bg-card p-8 text-center shadow-lg duration-500 fade-in zoom-in sm:p-12"
        >
            <div
                class="relative mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/10"
            >
                <Check
                    class="size-10 animate-bounce text-emerald-500 dark:text-emerald-400"
                />
                <Sparkles
                    class="absolute top-0 right-0 size-4 animate-pulse text-amber-500"
                />
                <Sparkles
                    class="absolute bottom-2 left-0 size-3 animate-pulse text-emerald-500 delay-100"
                />
            </div>

            <h2
                class="mb-3 text-2xl font-extrabold tracking-tight text-foreground sm:text-3xl"
            >
                Thanh Toán Thành Công!
            </h2>

            <p
                class="mx-auto mb-8 max-w-md text-sm leading-relaxed text-muted-foreground"
            >
                Cảm ơn bạn! Hệ thống đã nhận được tiền chuyển khoản và tự động
                kích hoạt gói dịch vụ chuyên nghiệp **{{
                    subscription.plan_name
                }}** thành công.
            </p>

            <div
                class="mx-auto inline-flex w-full max-w-xs items-center justify-center gap-3 rounded-xl border border-border bg-muted p-4"
            >
                <RefreshCw
                    class="size-4 animate-spin text-emerald-500 dark:text-emerald-400"
                />
                <span class="text-xs font-semibold text-muted-foreground">
                    Đang chuyển hướng về Dashboard sau {{ countdown }}s...
                </span>
            </div>
        </div>

        <!-- 2. MÀN HÌNH QUÉT MÃ QR THANH TOÁN CHÍNH -->
        <div
            v-else
            class="grid w-full animate-in gap-8 rounded-2xl border border-border bg-card p-6 shadow-sm duration-500 fade-in sm:p-8 md:grid-cols-12"
        >
            <!-- BÊN TRÁI: THÔNG TIN CHI TIẾT GÓI VÀ TÀI KHOẢN -->
            <div class="flex flex-col justify-between space-y-6 md:col-span-7">
                <div class="space-y-6">
                    <!-- Thông tin gói đang mua -->
                    <div class="space-y-2">
                        <span
                            class="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary/10 px-2.5 py-1 text-xs font-bold tracking-wider text-primary uppercase"
                        >
                            <Sparkles class="size-3.5" /> Gói
                            {{ subscription.plan_name }}
                        </span>
                        <h2
                            class="text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                        >
                            Xác nhận thanh toán dịch vụ
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Hãy quét mã QR bên phải hoặc chuyển khoản thủ công
                            theo thông tin bên dưới để hoàn tất.
                        </p>
                    </div>

                    <!-- Card chi tiết thông tin chuyển khoản -->
                    <div
                        class="space-y-3 rounded-xl border border-border bg-muted/40 p-5"
                    >
                        <!-- Hàng Số Tiền -->
                        <div
                            class="flex items-center justify-between border-b border-border/60 pb-3"
                        >
                            <div
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <DollarSign class="size-4 text-primary" />
                                <span>Số tiền chuyển khoản</span>
                            </div>
                            <span class="text-lg font-extrabold text-primary">
                                {{ formatCurrency(bank_details.amount) }}
                            </span>
                        </div>

                        <!-- Hàng Nội Dung -->
                        <div
                            class="flex items-center justify-between border-b border-border/60 py-2"
                        >
                            <div
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <Hash class="size-4" />
                                <span>Nội dung chuyển khoản (Memo)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded border border-border bg-muted px-2 py-1 font-mono text-sm font-bold text-foreground"
                                >
                                    {{ bank_details.content }}
                                </span>
                                <button
                                    @click="
                                        copyToClipboard(
                                            bank_details.content,
                                            'content',
                                        )
                                    "
                                    class="rounded-lg border border-border bg-muted p-1.5 text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    <Check
                                        v-if="copiedField === 'content'"
                                        class="size-3.5 text-emerald-500"
                                    />
                                    <Copy v-else class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Hàng Ngân Hàng -->
                        <div
                            class="flex items-center justify-between border-b border-border/60 py-2"
                        >
                            <div
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <CreditCard class="size-4" />
                                <span>Ngân hàng thụ hưởng</span>
                            </div>
                            <span class="text-xs font-bold text-foreground">
                                {{ bank_details.bank }}
                            </span>
                        </div>

                        <!-- Hàng Số Tài Khoản -->
                        <div
                            class="flex items-center justify-between border-b border-border/60 py-2"
                        >
                            <div
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <CreditCard class="size-4" />
                                <span>Số tài khoản</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="font-mono text-xs font-bold text-foreground"
                                >
                                    {{ bank_details.account_number }}
                                </span>
                                <button
                                    @click="
                                        copyToClipboard(
                                            bank_details.account_number,
                                            'account',
                                        )
                                    "
                                    class="rounded-lg border border-border bg-muted p-1.5 text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    <Check
                                        v-if="copiedField === 'account'"
                                        class="size-3.5 text-emerald-500"
                                    />
                                    <Copy v-else class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Hàng Tên Tài Khoản -->
                        <div class="flex items-center justify-between pt-2">
                            <div
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <User class="size-4" />
                                <span>Tên chủ tài khoản</span>
                            </div>
                            <span
                                class="text-xs font-bold tracking-wide text-foreground uppercase"
                            >
                                {{ bank_details.account_name }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Footer thông tin bảo mật -->
                <div
                    class="flex flex-col items-center justify-between gap-4 border-t border-border/60 pt-4 sm:flex-row"
                >
                    <div
                        class="flex items-center gap-2 text-[10px] text-muted-foreground sm:text-xs"
                    >
                        <ShieldCheck class="size-4 shrink-0 text-emerald-500" />
                        <span>Giao dịch mã hóa an toàn & Tự động xử lý</span>
                    </div>
                    <div class="flex w-full gap-2 sm:w-auto">
                        <Button
                            variant="outline"
                            size="sm"
                            class="w-full border-border bg-background text-xs text-muted-foreground hover:text-foreground sm:w-auto"
                            @click="checkPaymentStatus(false)"
                            :disabled="isChecking"
                        >
                            <RefreshCw
                                class="mr-1.5 size-3.5"
                                :class="isChecking ? 'animate-spin' : ''"
                            />
                            Kiểm tra lại
                        </Button>
                        <Button
                            variant="secondary"
                            size="sm"
                            class="w-full text-xs sm:w-auto"
                            @click="router.visit('/dashboard')"
                        >
                            <ArrowLeft class="mr-1.5 size-3.5" />
                            Quay lại
                        </Button>
                    </div>
                </div>
            </div>

            <!-- BÊN PHẢI: MÃ QR QUÉT MÃ -->
            <div
                class="relative flex flex-col items-center justify-center overflow-hidden rounded-2xl border border-border bg-muted/20 p-6 text-center sm:p-8 md:col-span-5"
            >
                <!-- Hộp trạng thái Live -->
                <div
                    class="absolute top-4 left-4 inline-flex animate-pulse items-center gap-1.5 rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-600 dark:text-amber-400"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                    <span>Đang chờ chuyển khoản...</span>
                </div>

                <!-- Viền phát sáng nhẹ xung quanh QR -->
                <div class="group relative mt-6 mb-4">
                    <div
                        class="absolute -inset-1 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 opacity-20 blur-md"
                    ></div>

                    <div
                        class="relative flex aspect-square max-w-[210px] items-center justify-center rounded-xl border border-border bg-white p-3.5 shadow-md sm:max-w-[240px]"
                    >
                        <img
                            :src="payment_url"
                            alt="Mã QR thanh toán SePay"
                            class="h-full w-full object-contain"
                        />
                    </div>
                </div>

                <p
                    class="max-w-[220px] text-[10px] leading-relaxed text-muted-foreground sm:text-xs"
                >
                    Mở ứng dụng ngân hàng quét mã QR này để tự động điền số tài
                    khoản, số tiền và nội dung chuyển khoản.
                </p>
            </div>
        </div>
    </div>
</template>
