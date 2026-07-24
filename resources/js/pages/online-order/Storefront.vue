<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ChevronRight,
    Clock,
    Loader2,
    Minus,
    MapPin,
    Phone,
    Plus,
    ShoppingCart,
    ShoppingBag,
    Star,
    Store,
    Truck,
    X,
    CheckCircle2,
    AlertTriangle,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { fireConfetti } from '@/composables/useConfetti';
import { useOfflineQueue } from '@/composables/useOfflineQueue';
import { useTracking } from '@/composables/useTracking';

const props = defineProps<{
    restaurant: {
        id: number;
        name: string;
        address: string | null;
        logo_url: string | null;
        phone: string | null;
    };
    config: {
        slug: string;
        banner_url: string | null;
        description: string | null;
        min_order_amount: number;
        enable_takeaway: boolean;
        enable_delivery: boolean;
        enable_preorder: boolean;
        is_open: boolean;
        operating_hours: any;
    };
    categories: { id: number; name: string; slug: string }[];
    products: Record<
        number,
        {
            id: number;
            name: string;
            description: string | null;
            price: number;
            image_url: string | null;
            category_id: number;
        }[]
    >;
    gateways: { key: string; name: string }[];
    tracking?: {
        ga_measurement_id?: string | null;
        fb_pixel_id?: string | null;
    };
    turnstileSiteKey?: string;
    captchaQuestion?: string;
    captchaToken?: string;
}>();

const analytics = useTracking(props.tracking ?? {});
const turnstileToken = ref('');
const captchaAnswer = ref('');

onMounted(() => {
    analytics.init();

    if (props.turnstileSiteKey) {
        // @ts-expect-error Cloudflare Turnstile not in window type definitions
        if (!window.turnstile) {
            const script = document.createElement('script');
            script.src =
                'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadTurnstileCallbackStorefront';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);

            // @ts-expect-error Cloudflare Turnstile callback not in window type definitions
            window.onloadTurnstileCallbackStorefront = () => {
                // @ts-expect-error Cloudflare Turnstile render not in window type definitions
                window.turnstile.render('#turnstile-container-storefront', {
                    sitekey: props.turnstileSiteKey,
                    callback: (token: string) => {
                        turnstileToken.value = token;
                    },
                });
            };
        } else {
            setTimeout(() => {
                // @ts-expect-error Cloudflare Turnstile render not in window type definitions
                window.turnstile.render('#turnstile-container-storefront', {
                    sitekey: props.turnstileSiteKey,
                    callback: (token: string) => {
                        turnstileToken.value = token;
                    },
                });
            }, 100);
        }
    }
});

const allCategories = computed(() => {
    return [
        { id: 0, name: 'Tất cả', slug: 'all' },
        ...props.categories,
    ];
});

const allProducts = computed(() => {
    const list: any[] = [];
    Object.values(props.products).forEach((prodList) => {
        list.push(...prodList);
    });
    const seen = new Set();

    return list.filter((p) => {
        if (seen.has(p.id)) {
            return false;
        }

        seen.add(p.id);

        return true;
    });
});

const activeCategory = ref<number>(0);
const cart = ref<
    Record<
        number,
        {
            id: number;
            name: string;
            price: number;
            quantity: number;
            notes: string;
        }
    >
>({});
const showCart = ref(false);
const showCheckout = ref(false);
const channel = ref<'takeaway' | 'delivery'>('takeaway');
const customerName = ref('');
const phone = ref('');
const address = ref('');
const latitude = ref<number | null>(null);
const longitude = ref<number | null>(null);
const deliveryFee = ref(0);
const deliveryError = ref('');
const paymentMethod = ref(props.gateways[0]?.key ?? 'bank_transfer');
const note = ref('');
const scheduledAt = ref('');
const submitting = ref(false);
const orderResult = ref<{
    order_number: string;
    payment_url: string | null;
    track_url: string;
} | null>(null);
const calculatingFee = ref(false);

const showOfflinePrintDialog = ref(false);
const offlineOrderDetails = ref<any>(null);

function printOfflineReceipt() {
    if (!offlineOrderDetails.value) {
return;
}

    const order = offlineOrderDetails.value;
    const printWindow = window.open('', '_blank');

    if (!printWindow) {
        toast.error(
            'Trình chặn Pop-up đã ngăn chặn việc in ấn. Vui lòng cho phép pop-up cho trang web này.',
        );

        return;
    }

    const itemsHtml = order.items
        .map(
            (i: any) => `
        <tr style="border-bottom: 1px dashed #ccc;">
            <td style="padding: 6px 0;">${i.quantity}x ${i.name}</td>
            <td style="padding: 6px 0; text-align: right;">${(i.price * i.quantity).toLocaleString()}đ</td>
        </tr>
    `,
        )
        .join('');

    printWindow.document.write(`
        <html>
            <head>
                <title>VÉ CHẾ BIẾN BẾP (OFFLINE FALLBACK)</title>
                <style>
                    body { font-family: monospace; padding: 10px; max-width: 300px; margin: 0 auto; font-size: 13px; color: #000; }
                    .header { text-align: center; margin-bottom: 15px; }
                    .header h2 { margin: 0 0 5px 0; font-size: 16px; }
                    .header p { margin: 0; font-size: 11px; }
                    .info { margin-bottom: 10px; border-bottom: 2px dashed #000; padding-bottom: 8px; }
                    .info p { margin: 3px 0; font-size: 12px; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
                    .total { text-align: right; font-weight: bold; font-size: 14px; margin-top: 10px; border-top: 2px dashed #000; padding-top: 8px; }
                    .notes { margin-top: 10px; padding: 6px; border: 1px solid #000; font-size: 11px; }
                    .footer { text-align: center; margin-top: 20px; font-size: 11px; border-top: 1px solid #000; padding-top: 10px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>VÉ CHẾ BIẾN BẾP</h2>
                    <p>(Chế độ Offline Dự phòng)</p>
                </div>
                <div class="info">
                    <p><strong>Mã đơn tạm:</strong> ${order.order_number}</p>
                    <p><strong>Khách hàng:</strong> ${order.customer_name || 'Khách vãng lai'}</p>
                    <p><strong>Số điện thoại:</strong> ${order.phone || 'N/A'}</p>
                    <p><strong>Hình thức:</strong> ${order.channel === 'delivery' ? 'Giao hàng' : 'Mang về'}</p>
                    <p><strong>Thời gian:</strong> ${order.created_at}</p>
                </div>
                <table>
                    <thead>
                        <tr style="border-bottom: 2px dashed #000;">
                            <th style="text-align: left; padding-bottom: 4px;">Món ăn</th>
                            <th style="text-align: right; padding-bottom: 4px;">Tạm tính</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                </table>
                <div class="total">
                    TỔNG CỘNG: ${order.total.toLocaleString()}đ
                </div>
                ${order.note ? `<div class="notes"><strong>Ghi chú:</strong> ${order.note}</div>` : ''}
                <div class="footer">
                    Vui lòng đưa vé này cho bếp chế biến thủ công. Đơn hàng sẽ được tự động đồng bộ khi có kết nối internet trở lại.
                </div>
                <' + 'script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() { window.close(); }, 500);
                    };
                <' + '/script>
            <' + '/body>
        <' + '/html>
    `);
    printWindow.document.close();
}

const cartItems = computed(() => Object.values(cart.value));
const cartCount = computed(() =>
    cartItems.value.reduce((sum, i) => sum + i.quantity, 0),
);
const subtotal = computed(() =>
    cartItems.value.reduce((sum, i) => sum + i.price * i.quantity, 0),
);
const total = computed(
    () =>
        subtotal.value + (channel.value === 'delivery' ? deliveryFee.value : 0),
);

const cartBadgePop = ref(false);

function addToCart(product: any) {
    if (cart.value[product.id]) {
        cart.value[product.id].quantity++;
    } else {
        cart.value[product.id] = {
            id: product.id,
            name: product.name,
            price: product.price,
            quantity: 1,
            notes: '',
        };
    }

    analytics.trackAddToCart(product.name, Number(product.price));

    // Nảy badge giỏ hàng để phản hồi trực quan
    cartBadgePop.value = false;
    requestAnimationFrame(() => {
        cartBadgePop.value = true;
    });
}

function updateQuantity(id: number, delta: number) {
    if (!cart.value[id]) {
        return;
    }

    cart.value[id].quantity += delta;

    if (cart.value[id].quantity <= 0) {
        delete cart.value[id];
    }
}

function removeFromCart(id: number) {
    delete cart.value[id];
}

async function calculateDeliveryFee() {
    if (!latitude.value || !longitude.value) {
        deliveryError.value =
            'Vui lòng cho phép truy cập vị trí hoặc nhập tọa độ.';

        return;
    }

    calculatingFee.value = true;
    deliveryError.value = '';

    try {
        const { data } = await axios.post(
            `/api/online/${props.config.slug}/delivery-fee`,
            {
                latitude: latitude.value,
                longitude: longitude.value,
            },
        );

        if (data.deliverable) {
            deliveryFee.value = data.fee;
            deliveryError.value = '';
        } else {
            deliveryError.value = data.reason;
            deliveryFee.value = 0;
        }
    } catch {
        deliveryError.value = 'Không thể tính phí giao hàng.';
    } finally {
        calculatingFee.value = false;
    }
}

function getLocation() {
    if (!navigator.geolocation) {
        return;
    }

    navigator.geolocation.getCurrentPosition((pos) => {
        latitude.value = pos.coords.latitude;
        longitude.value = pos.coords.longitude;
        calculateDeliveryFee();
    });
}

// Hàng đợi offline: mất mạng vẫn đặt được đơn, tự gửi khi có mạng lại
const { postWithQueue, pendingCount, isOnline } = useOfflineQueue(
    (item, response: any) => {
        if (response?.success) {
            toast.success(
                `Đã gửi đơn offline thành công! Mã đơn: ${response.order_number}`,
            );
        }
    },
);

async function submitOrder() {
    submitting.value = true;

    try {
        const result = await postWithQueue(
            `/api/online/${props.config.slug}/checkout`,
            {
                customer_name: customerName.value,
                phone: phone.value,
                channel: channel.value,
                address: address.value,
                latitude: latitude.value,
                longitude: longitude.value,
                items: cartItems.value.map((i) => ({
                    product_id: i.id,
                    quantity: i.quantity,
                    notes: i.notes,
                })),
                payment_method: paymentMethod.value,
                note: note.value,
                scheduled_at: scheduledAt.value || null,
                'cf-turnstile-response': turnstileToken.value,
                captcha_answer: captchaAnswer.value,
                captcha_token: props.captchaToken,
            },
        );

        if (result.queued) {
            offlineOrderDetails.value = {
                order_number:
                    'OFFLINE-' + Math.floor(100000 + Math.random() * 900000),
                customer_name: customerName.value,
                phone: phone.value,
                channel: channel.value,
                items: cartItems.value.map((i) => ({
                    product_id: i.id,
                    name: i.name,
                    quantity: i.quantity,
                    notes: i.notes,
                    price: i.price,
                })),
                total: total.value,
                note: note.value,
                created_at: new Date().toLocaleString(),
            };
            cart.value = {};
            showCart.value = false;
            showCheckout.value = false;
            showOfflinePrintDialog.value = true;
            toast.warning(
                'Mất kết nối mạng — đơn đã được lưu và sẽ tự động gửi ngay khi có mạng trở lại.',
            );

            return;
        }

        const data = result.data;

        if (data.success) {
            orderResult.value = data;
            analytics.trackPurchase(data.order_number, total.value);
            fireConfetti();

            if (data.payment_url) {
                window.open(data.payment_url, '_blank');
            }
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message ?? 'Có lỗi xảy ra.');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Head :title="restaurant.name + ' — Đặt hàng Online'" />

    <div class="min-h-screen" style="background:#f7f8fa; font-family:'Inter',-apple-system,sans-serif;">

        <!-- ✅ SUCCESS SCREEN -->
        <div
            v-if="orderResult"
            class="flex min-h-screen items-center justify-center p-6"
            style="background:linear-gradient(135deg,#f0f4ff 0%,#fef9ff 100%);"
        >
            <div class="w-full max-w-md overflow-hidden rounded-3xl shadow-2xl" style="background:#fff;">
                <div
                    class="flex flex-col items-center justify-center px-8 py-10 text-center"
                    style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);"
                >
                    <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-white/20">
                        <CheckCircle2 class="size-10 text-white" />
                    </div>
                    <h2 class="text-2xl font-black text-white">Đặt hàng thành công!</h2>
                    <p class="mt-1 text-sm text-indigo-100">Cảm ơn bạn đã đặt hàng 🎉</p>
                </div>
                <div class="space-y-4 p-6">
                    <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4 text-center">
                        <p class="text-xs font-semibold uppercase tracking-widest text-indigo-400">Mã đơn hàng</p>
                        <p class="mt-1 text-xl font-black text-indigo-700">{{ orderResult.order_number }}</p>
                    </div>
                    <div class="flex justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm">
                        <span class="text-slate-500">Tổng tiền</span>
                        <span class="font-bold text-slate-800">{{ total.toLocaleString() }}đ</span>
                    </div>
                    <div class="flex flex-col gap-2 pt-2">
                        <a
                            :href="orderResult.track_url"
                            class="flex items-center justify-center gap-2 rounded-2xl px-4 py-3.5 text-sm font-bold text-white transition-all hover:opacity-90"
                            style="background:linear-gradient(135deg,#6366f1,#8b5cf6);"
                        >
                            <Truck class="size-4" /> Theo dõi đơn hàng
                        </a>
                        <a
                            v-if="orderResult.payment_url"
                            :href="orderResult.payment_url"
                            target="_blank"
                            class="flex items-center justify-center gap-2 rounded-2xl border-2 border-indigo-200 px-4 py-3.5 text-sm font-bold text-indigo-600 transition-all hover:bg-indigo-50"
                        >
                            <Zap class="size-4" /> Thanh toán ngay
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ✅ MAIN STOREFRONT -->
        <div v-else>

            <!-- OFFLINE BANNER -->
            <div
                v-if="!isOnline || pendingCount > 0"
                class="flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-bold"
                style="background:#f59e0b;color:#1c1917;"
            >
                <Zap class="size-3.5" />
                <template v-if="!isOnline">📡 Mất kết nối mạng — bạn vẫn có thể đặt đơn, hệ thống sẽ tự gửi khi có mạng lại.</template>
                <template v-else>⏳ Đang gửi {{ pendingCount }} đơn đã lưu offline...</template>
            </div>

            <!-- HERO / BANNER SECTION -->
            <div class="relative">
                <div
                    class="relative h-52 w-full overflow-hidden sm:h-68"
                    style="background:linear-gradient(135deg,#1e1b4b 0%,#312e81 50%,#4c1d95 100%);"
                >
                    <img
                        v-if="config.banner_url"
                        :src="config.banner_url"
                        class="h-full w-full object-cover"
                        style="opacity:0.45;"
                    />
                    <div class="absolute -left-16 -top-16 h-56 w-56 rounded-full" style="background:radial-gradient(circle,rgba(167,139,250,0.25),transparent);"></div>
                    <div class="absolute -bottom-8 -right-8 h-44 w-44 rounded-full" style="background:radial-gradient(circle,rgba(129,140,248,0.25),transparent);"></div>
                    <div class="absolute inset-0" style="background:linear-gradient(to top,rgba(10,10,30,0.92) 0%,rgba(10,10,30,0.4) 45%,transparent 100%);"></div>
                </div>

                <!-- Restaurant info on banner -->
                <div class="absolute bottom-0 left-0 right-0 px-4 pb-5">
                    <div class="mx-auto max-w-2xl">
                        <div class="flex items-end gap-3">
                            <div class="shrink-0">
                                <div v-if="restaurant.logo_url" class="h-14 w-14 overflow-hidden rounded-xl shadow-xl ring-2 ring-white/20 sm:h-18 sm:w-18">
                                    <img :src="restaurant.logo_url" class="h-full w-full object-cover" />
                                </div>
                                <div
                                    v-else
                                    class="flex h-14 w-14 items-center justify-center rounded-xl shadow-xl ring-2 ring-white/20"
                                    style="background:linear-gradient(135deg,#6366f1,#8b5cf6);"
                                >
                                    <Store class="size-7 text-white" />
                                </div>
                            </div>
                            <div class="min-w-0 flex-1 pb-0.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h1 class="text-xl font-black leading-tight text-white sm:text-2xl">{{ restaurant.name }}</h1>
                                    <span
                                        v-if="config.is_open"
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold"
                                        style="background:rgba(16,185,129,0.2);color:#34d399;border:1px solid rgba(52,211,153,0.3);"
                                    >
                                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span>Đang mở cửa
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold"
                                        style="background:rgba(239,68,68,0.2);color:#f87171;border:1px solid rgba(248,113,113,0.3);"
                                    >Đóng cửa</span>
                                </div>
                                <p v-if="config.description" class="mt-0.5 line-clamp-1 text-xs text-white/60">{{ config.description }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-3">
                                    <span v-if="restaurant.phone" class="flex items-center gap-1 text-xs text-white/50">
                                        <Phone class="size-3" />{{ restaurant.phone }}
                                    </span>
                                    <span v-if="restaurant.address" class="flex items-center gap-1 text-xs text-white/50">
                                        <MapPin class="size-3" />{{ restaurant.address }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QUICK INFO CHIPS -->
            <div class="mx-auto max-w-2xl px-4">
                <div class="flex items-center gap-2 overflow-x-auto pb-1 pt-3" style="scrollbar-width:none;">
                    <div v-if="config.enable_delivery" class="flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold" style="background:#fff;border-color:#e5e7eb;color:#374151;">
                        <Truck class="size-3.5 text-indigo-500" /> Giao hàng tận nơi
                    </div>
                    <div v-if="config.enable_takeaway" class="flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold" style="background:#fff;border-color:#e5e7eb;color:#374151;">
                        <ShoppingBag class="size-3.5 text-violet-500" /> Mang về
                    </div>
                    <div v-if="config.enable_preorder" class="flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold" style="background:#fff;border-color:#e5e7eb;color:#374151;">
                        <Clock class="size-3.5 text-amber-500" /> Đặt trước
                    </div>
                    <div v-if="config.min_order_amount > 0" class="flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold" style="background:#fff;border-color:#e5e7eb;color:#374151;">
                        <Star class="size-3.5 text-yellow-400" /> Tối thiểu {{ config.min_order_amount.toLocaleString() }}đ
                    </div>
                </div>
            </div>

            <!-- STICKY CATEGORY NAV -->
            <div class="sticky top-0 z-30 mx-auto mt-3 max-w-2xl">
                <div class="mx-4 overflow-hidden rounded-2xl" style="background:#fff;border:1px solid #f0f0f5;box-shadow:0 2px 12px rgba(0,0,0,0.06);">
                    <div class="flex gap-1 overflow-x-auto p-1.5" style="scrollbar-width:none;">
                        <button
                            v-for="cat in allCategories"
                            :key="cat.id"
                            @click="activeCategory = cat.id"
                            :style="activeCategory === cat.id
                                ? 'background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;box-shadow:0 4px 12px rgba(99,102,241,0.35);'
                                : 'background:transparent;color:#6b7280;'"
                            class="shrink-0 rounded-xl px-4 py-2 text-xs font-bold whitespace-nowrap transition-all duration-200 hover:bg-slate-50"
                        >
                            {{ cat.name }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- PRODUCT GRID -->
            <main class="mx-auto max-w-2xl px-4 pb-36 pt-5">
                <!-- "Tất cả" view -->
                <div v-show="activeCategory === 0">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="h-5 w-1 rounded-full" style="background:linear-gradient(180deg,#6366f1,#8b5cf6);"></div>
                        <h2 class="text-sm font-black text-slate-700">Tất cả món ăn</h2>
                        <span class="ml-auto text-xs text-slate-400">{{ allProducts.length }} món</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div
                            v-for="p in allProducts"
                            :key="p.id"
                            class="group flex flex-col overflow-hidden rounded-2xl transition-all duration-300 hover:-translate-y-1"
                            style="background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);border:1px solid #f0f0f5;"
                        >
                            <div class="relative overflow-hidden" style="background:#f8f9fa;aspect-ratio:4/3;">
                                <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110" />
                                <div v-else class="flex h-full items-center justify-center" style="min-height:110px;">
                                    <Store class="size-10 text-slate-200" />
                                </div>
                                <div
                                    v-if="cart[p.id]"
                                    class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full text-xs font-black text-white"
                                    style="background:linear-gradient(135deg,#6366f1,#8b5cf6);"
                                >{{ cart[p.id].quantity }}</div>
                            </div>
                            <div class="flex flex-1 flex-col p-3">
                                <p class="line-clamp-2 text-sm font-bold leading-snug text-slate-800">{{ p.name }}</p>
                                <p v-if="p.description" class="mt-0.5 line-clamp-2 text-[11px] leading-relaxed text-slate-400">{{ p.description }}</p>
                                <div class="mt-auto flex items-center justify-between gap-2 pt-3">
                                    <p class="text-sm font-extrabold" style="color:#6366f1;">{{ p.price.toLocaleString() }}đ</p>
                                    <div v-if="cart[p.id]" class="flex items-center gap-1.5">
                                        <button @click="updateQuantity(p.id, -1)" class="flex h-7 w-7 items-center justify-center rounded-full transition-all active:scale-90" style="background:#e0e7ff;">
                                            <Minus class="size-3.5" style="color:#6366f1;" />
                                        </button>
                                        <span class="w-5 text-center text-sm font-black text-slate-800">{{ cart[p.id].quantity }}</span>
                                        <button @click="addToCart(p)" class="flex h-7 w-7 items-center justify-center rounded-full text-white transition-all active:scale-90" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                                            <Plus class="size-3.5" />
                                        </button>
                                    </div>
                                    <button
                                        v-else
                                        @click="addToCart(p)"
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-white transition-all active:scale-90"
                                        style="background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 4px 10px rgba(99,102,241,0.35);"
                                    ><Plus class="size-4" /></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Per-category views -->
                <div v-for="cat in categories" :key="cat.id" v-show="activeCategory === cat.id">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="h-5 w-1 rounded-full" style="background:linear-gradient(180deg,#6366f1,#8b5cf6);"></div>
                        <h2 class="text-sm font-black text-slate-700">{{ cat.name }}</h2>
                        <span class="ml-auto text-xs text-slate-400">{{ (products[cat.id] ?? []).length }} món</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div
                            v-for="p in products[cat.id] ?? []"
                            :key="p.id"
                            class="group flex flex-col overflow-hidden rounded-2xl transition-all duration-300 hover:-translate-y-1"
                            style="background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);border:1px solid #f0f0f5;"
                        >
                            <div class="relative overflow-hidden" style="background:#f8f9fa;aspect-ratio:4/3;">
                                <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110" />
                                <div v-else class="flex h-full items-center justify-center" style="min-height:110px;">
                                    <Store class="size-10 text-slate-200" />
                                </div>
                                <div
                                    v-if="cart[p.id]"
                                    class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full text-xs font-black text-white"
                                    style="background:linear-gradient(135deg,#6366f1,#8b5cf6);"
                                >{{ cart[p.id].quantity }}</div>
                            </div>
                            <div class="flex flex-1 flex-col p-3">
                                <p class="line-clamp-2 text-sm font-bold leading-snug text-slate-800">{{ p.name }}</p>
                                <p v-if="p.description" class="mt-0.5 line-clamp-2 text-[11px] leading-relaxed text-slate-400">{{ p.description }}</p>
                                <div class="mt-auto flex items-center justify-between gap-2 pt-3">
                                    <p class="text-sm font-extrabold" style="color:#6366f1;">{{ p.price.toLocaleString() }}đ</p>
                                    <div v-if="cart[p.id]" class="flex items-center gap-1.5">
                                        <button @click="updateQuantity(p.id, -1)" class="flex h-7 w-7 items-center justify-center rounded-full transition-all active:scale-90" style="background:#e0e7ff;">
                                            <Minus class="size-3.5" style="color:#6366f1;" />
                                        </button>
                                        <span class="w-5 text-center text-sm font-black text-slate-800">{{ cart[p.id].quantity }}</span>
                                        <button @click="addToCart(p)" class="flex h-7 w-7 items-center justify-center rounded-full text-white transition-all active:scale-90" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                                            <Plus class="size-3.5" />
                                        </button>
                                    </div>
                                    <button
                                        v-else
                                        @click="addToCart(p)"
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-white transition-all active:scale-90"
                                        style="background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 4px 10px rgba(99,102,241,0.35);"
                                    ><Plus class="size-4" /></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- CART FAB -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-6 scale-90"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 translate-y-4 scale-90"
            >
                <div v-if="cartCount > 0" class="fixed bottom-6 left-0 right-0 z-50 flex justify-center px-4">
                    <button
                        @click="showCart = true"
                        :class="[cartBadgePop ? 'animate-badge-pop' : '']"
                        @animationend="cartBadgePop = false"
                        class="flex w-full max-w-sm items-center justify-between gap-3 rounded-2xl px-5 py-4 text-white transition-all hover:scale-[1.02] active:scale-[0.98]"
                        style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);box-shadow:0 10px 40px rgba(99,102,241,0.45);"
                    >
                        <div class="flex items-center gap-3">
                            <div class="relative flex h-9 w-9 items-center justify-center rounded-xl bg-white/20">
                                <ShoppingCart class="size-5" />
                                <span class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-white text-xs font-black" style="color:#6366f1;">{{ cartCount }}</span>
                            </div>
                            <div class="text-left">
                                <p class="text-xs font-semibold opacity-80">{{ cartCount }} món trong giỏ</p>
                                <p class="text-sm font-black">{{ subtotal.toLocaleString() }}đ</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-sm font-bold opacity-90">
                            Xem giỏ <ChevronRight class="size-4" />
                        </div>
                    </button>
                </div>
            </Transition>

            <!-- CART BOTTOM SHEET -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showCart" class="fixed inset-0 z-50 flex items-end justify-center">
                    <div class="absolute inset-0" style="background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);" @click="showCart = false"></div>
                    <div class="relative w-full max-w-lg overflow-hidden" style="background:#fff;border-radius:28px 28px 0 0;max-height:82vh;">
                        <div class="flex justify-center pb-2 pt-3">
                            <div class="h-1 w-10 rounded-full" style="background:#e5e7eb;"></div>
                        </div>
                        <div class="overflow-y-auto" style="max-height:calc(82vh - 20px);">
                            <div class="sticky top-0 flex items-center justify-between border-b px-5 pb-4 pt-2" style="background:#fff;z-index:1;border-color:#f3f4f6;">
                                <div>
                                    <h2 class="text-lg font-black text-slate-800">Giỏ hàng của bạn</h2>
                                    <p class="text-xs text-slate-400">{{ cartCount }} món đã chọn</p>
                                </div>
                                <button @click="showCart = false" class="flex h-9 w-9 items-center justify-center rounded-full" style="background:#f3f4f6;">
                                    <X class="size-4 text-slate-500" />
                                </button>
                            </div>
                            <div class="space-y-1 px-5 py-3">
                                <div v-for="item in cartItems" :key="item.id" class="flex items-center gap-3 rounded-2xl p-3 transition-colors hover:bg-slate-50">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-bold text-slate-800">{{ item.name }}</p>
                                        <p class="text-xs font-semibold" style="color:#6366f1;">{{ (item.price * item.quantity).toLocaleString() }}đ</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button @click="updateQuantity(item.id, -1)" class="flex h-8 w-8 items-center justify-center rounded-full border-2 transition-all active:scale-90" style="border-color:#e0e7ff;color:#6366f1;">
                                            <Minus class="size-3.5" />
                                        </button>
                                        <span class="w-6 text-center text-sm font-black text-slate-800">{{ item.quantity }}</span>
                                        <button @click="updateQuantity(item.id, 1)" class="flex h-8 w-8 items-center justify-center rounded-full text-white transition-all active:scale-90" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                                            <Plus class="size-3.5" />
                                        </button>
                                    </div>
                                    <button @click="removeFromCart(item.id)" class="flex h-8 w-8 items-center justify-center rounded-full transition-colors hover:bg-red-50">
                                        <X class="size-3.5 text-red-400" />
                                    </button>
                                </div>
                            </div>
                            <div class="border-t px-5 pb-8 pt-4" style="border-color:#f3f4f6;">
                                <div class="mb-4 flex items-center justify-between">
                                    <span class="text-sm font-semibold text-slate-500">Tạm tính</span>
                                    <span class="text-xl font-black text-slate-800">{{ subtotal.toLocaleString() }}đ</span>
                                </div>
                                <button
                                    @click="showCart = false; showCheckout = true;"
                                    class="flex w-full items-center justify-center gap-2 rounded-2xl py-4 text-sm font-black text-white transition-all active:scale-[0.98]"
                                    style="background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 8px 24px rgba(99,102,241,0.4);"
                                >
                                    Tiến hành đặt hàng <ChevronRight class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- CHECKOUT BOTTOM SHEET -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showCheckout" class="fixed inset-0 z-50 flex items-end justify-center">
                    <div class="absolute inset-0" style="background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);" @click="showCheckout = false"></div>
                    <div class="relative w-full max-w-lg overflow-hidden" style="background:#fff;border-radius:28px 28px 0 0;max-height:90vh;">
                        <div class="flex justify-center pb-2 pt-3">
                            <div class="h-1 w-10 rounded-full" style="background:#e5e7eb;"></div>
                        </div>
                        <div class="overflow-y-auto" style="max-height:calc(90vh - 20px);">
                            <div class="sticky top-0 flex items-center justify-between border-b px-5 pb-4 pt-2" style="background:#fff;z-index:1;border-color:#f3f4f6;">
                                <div>
                                    <h2 class="text-lg font-black text-slate-800">Thông tin đặt hàng</h2>
                                    <p class="text-xs text-slate-400">Điền thông tin để hoàn tất đơn hàng</p>
                                </div>
                                <button @click="showCheckout = false" class="flex h-9 w-9 items-center justify-center rounded-full" style="background:#f3f4f6;">
                                    <X class="size-4 text-slate-500" />
                                </button>
                            </div>

                            <div class="space-y-5 px-5 py-4 pb-8">
                                <!-- Order type -->
                                <div>
                                    <p class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-400">Hình thức nhận hàng</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button
                                            v-if="config.enable_takeaway"
                                            @click="channel = 'takeaway'"
                                            class="flex items-center justify-center gap-2 rounded-2xl border-2 p-3.5 text-sm font-bold transition-all"
                                            :style="channel === 'takeaway' ? 'border-color:#6366f1;background:#eef2ff;color:#6366f1;' : 'border-color:#e5e7eb;color:#6b7280;'"
                                        ><ShoppingBag class="size-4" /> Mang về</button>
                                        <button
                                            v-if="config.enable_delivery"
                                            @click="channel = 'delivery'; getLocation();"
                                            class="flex items-center justify-center gap-2 rounded-2xl border-2 p-3.5 text-sm font-bold transition-all"
                                            :style="channel === 'delivery' ? 'border-color:#6366f1;background:#eef2ff;color:#6366f1;' : 'border-color:#e5e7eb;color:#6b7280;'"
                                        ><Truck class="size-4" /> Giao hàng</button>
                                    </div>
                                </div>

                                <!-- Customer info -->
                                <div>
                                    <p class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-400">Thông tin khách hàng</p>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-slate-600">Họ và tên *</label>
                                            <input
                                                v-model="customerName"
                                                placeholder="Nguyễn Văn A"
                                                class="w-full rounded-xl px-4 py-3 text-sm font-medium text-slate-800 outline-none transition-all"
                                                :style="customerName ? 'background:#f8f9fa;box-shadow:0 0 0 2px #6366f1;' : 'background:#f8f9fa;box-shadow:0 0 0 1px #e5e7eb;'"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-slate-600">Số điện thoại *</label>
                                            <input
                                                v-model="phone"
                                                placeholder="0912 345 678"
                                                class="w-full rounded-xl px-4 py-3 text-sm font-medium text-slate-800 outline-none transition-all"
                                                :style="phone ? 'background:#f8f9fa;box-shadow:0 0 0 2px #6366f1;' : 'background:#f8f9fa;box-shadow:0 0 0 1px #e5e7eb;'"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Delivery address -->
                                <div v-if="channel === 'delivery'">
                                    <p class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-400">Địa chỉ giao hàng</p>
                                    <div class="space-y-2">
                                        <input
                                            v-model="address"
                                            placeholder="Số nhà, đường, quận, thành phố..."
                                            class="w-full rounded-xl px-4 py-3 text-sm font-medium text-slate-800 outline-none"
                                            style="background:#f8f9fa;box-shadow:0 0 0 1px #e5e7eb;"
                                        />
                                        <button
                                            @click="getLocation"
                                            :disabled="calculatingFee"
                                            class="flex w-full items-center justify-center gap-2 rounded-xl py-3 text-xs font-bold transition-all"
                                            style="background:#eef2ff;color:#6366f1;border:1px dashed #a5b4fc;"
                                        >
                                            <MapPin class="size-3.5" />
                                            {{ calculatingFee ? 'Đang tính phí ship...' : 'Lấy vị trí & tính phí giao hàng' }}
                                        </button>
                                        <div v-if="deliveryFee > 0" class="flex items-center justify-between rounded-xl px-4 py-2.5 text-sm" style="background:#f0fdf4;">
                                            <span class="font-semibold text-green-700">Phí giao hàng</span>
                                            <span class="font-black text-green-700">{{ deliveryFee.toLocaleString() }}đ</span>
                                        </div>
                                        <p v-if="deliveryError" class="rounded-xl px-3 py-2 text-xs font-medium text-red-600" style="background:#fff1f2;">{{ deliveryError }}</p>
                                    </div>
                                </div>

                                <!-- Pre-order -->
                                <div v-if="config.enable_preorder">
                                    <p class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-400">Đặt trước (tuỳ chọn)</p>
                                    <input type="datetime-local" v-model="scheduledAt" class="w-full rounded-xl px-4 py-3 text-sm font-medium text-slate-800 outline-none" style="background:#f8f9fa;box-shadow:0 0 0 1px #e5e7eb;" />
                                </div>

                                <!-- Payment -->
                                <div>
                                    <p class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-400">Phương thức thanh toán</p>
                                    <div class="space-y-2">
                                        <label
                                            v-for="gw in gateways"
                                            :key="gw.key"
                                            class="flex cursor-pointer items-center gap-3 rounded-2xl border-2 p-3.5 transition-all"
                                            :style="paymentMethod === gw.key ? 'border-color:#6366f1;background:#eef2ff;' : 'border-color:#f0f0f5;background:#fafafa;'"
                                        >
                                            <input type="radio" :value="gw.key" v-model="paymentMethod" class="sr-only" />
                                            <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2" :style="paymentMethod === gw.key ? 'border-color:#6366f1;' : 'border-color:#d1d5db;'">
                                                <div v-if="paymentMethod === gw.key" class="h-2.5 w-2.5 rounded-full" style="background:#6366f1;"></div>
                                            </div>
                                            <span class="text-sm font-semibold text-slate-700">{{ gw.name }}</span>
                                        </label>
                                        <label
                                            v-if="channel === 'delivery'"
                                            class="flex cursor-pointer items-center gap-3 rounded-2xl border-2 p-3.5 transition-all"
                                            :style="paymentMethod === 'cod' ? 'border-color:#6366f1;background:#eef2ff;' : 'border-color:#f0f0f5;background:#fafafa;'"
                                        >
                                            <input type="radio" value="cod" v-model="paymentMethod" class="sr-only" />
                                            <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2" :style="paymentMethod === 'cod' ? 'border-color:#6366f1;' : 'border-color:#d1d5db;'">
                                                <div v-if="paymentMethod === 'cod'" class="h-2.5 w-2.5 rounded-full" style="background:#6366f1;"></div>
                                            </div>
                                            <span class="text-sm font-semibold text-slate-700">Thanh toán khi nhận hàng (COD)</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div>
                                    <p class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-400">Ghi chú thêm</p>
                                    <input v-model="note" placeholder="Ví dụ: Ít đá, không hành, không cay..." class="w-full rounded-xl px-4 py-3 text-sm font-medium text-slate-800 outline-none" style="background:#f8f9fa;box-shadow:0 0 0 1px #e5e7eb;" />
                                </div>

                                <!-- CAPTCHA / Turnstile -->
                                <div v-if="turnstileSiteKey || captchaQuestion" class="rounded-2xl border border-dashed p-4" style="border-color:#a5b4fc;background:#f5f3ff;">
                                    <p class="mb-2 flex items-center gap-1.5 text-xs font-bold text-indigo-600">
                                        <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></svg>
                                        Xác minh bảo mật
                                    </p>
                                    <div v-if="turnstileSiteKey">
                                        <div id="turnstile-container-storefront" class="my-1.5 flex justify-center"></div>
                                        <input type="hidden" name="cf-turnstile-response" :value="turnstileToken" />
                                    </div>
                                    <div v-else-if="captchaQuestion" class="space-y-2">
                                        <p class="text-xs text-slate-600">Vui lòng nhập kết quả:
                                            <strong class="ml-1 rounded-lg border border-indigo-200 bg-indigo-100 px-2 py-0.5 font-mono text-indigo-700">{{ captchaQuestion }}</strong>
                                        </p>
                                        <input type="hidden" name="captcha_token" :value="captchaToken" />
                                        <Input id="captcha_answer" type="number" v-model="captchaAnswer" required placeholder="Nhập kết quả" />
                                    </div>
                                </div>

                                <!-- Order summary -->
                                <div class="rounded-2xl p-4" style="background:#f8f9fa;">
                                    <p class="mb-3 text-xs font-bold uppercase tracking-widest text-slate-400">Tóm tắt đơn hàng</p>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between text-slate-600">
                                            <span>Tạm tính ({{ cartCount }} món)</span>
                                            <span class="font-semibold">{{ subtotal.toLocaleString() }}đ</span>
                                        </div>
                                        <div v-if="channel === 'delivery' && deliveryFee > 0" class="flex justify-between text-slate-600">
                                            <span>Phí giao hàng</span>
                                            <span class="font-semibold">{{ deliveryFee.toLocaleString() }}đ</span>
                                        </div>
                                        <div class="flex justify-between border-t pt-2 text-base" style="border-color:#e5e7eb;">
                                            <span class="font-bold text-slate-800">Tổng cộng</span>
                                            <span class="font-black" style="color:#6366f1;">{{ total.toLocaleString() }}đ</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit -->
                                <button
                                    :disabled="submitting || !customerName || !phone || cartCount === 0"
                                    @click="submitOrder"
                                    class="flex w-full items-center justify-center gap-2 rounded-2xl py-4 text-base font-black text-white transition-all active:scale-[0.98] disabled:opacity-50"
                                    style="background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 8px 24px rgba(99,102,241,0.4);"
                                >
                                    <Loader2 v-if="submitting" class="size-5 animate-spin" />
                                    {{ submitting ? 'Đang đặt hàng...' : 'Xác nhận đặt hàng 🎉' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- OFFLINE PRINT DIALOG -->
        <Dialog v-model:open="showOfflinePrintDialog">
            <DialogContent class="w-full max-w-md rounded-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-rose-600 dark:text-rose-500">
                        <AlertTriangle class="size-5 shrink-0" />
                        Chế độ Offline - In vé Bếp
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Đang mất kết nối mạng. Đơn hàng của bạn đã được ghi nhận ngoại tuyến và sẽ tự động gửi khi có mạng trở lại. Vui lòng in vé bếp để bếp thực hiện chế biến thủ công.
                    </DialogDescription>
                </DialogHeader>
                <div v-if="offlineOrderDetails" class="space-y-3 rounded-xl border bg-slate-50 p-4 font-mono text-xs text-slate-800 dark:bg-slate-900 dark:text-slate-200">
                    <div class="space-y-1 border-b border-dashed pb-2">
                        <div><strong>Đơn hàng:</strong> {{ offlineOrderDetails.order_number }}</div>
                        <div><strong>Hình thức:</strong> {{ offlineOrderDetails.channel === 'delivery' ? 'Giao hàng' : 'Mang về' }}</div>
                        <div><strong>Khách:</strong> {{ offlineOrderDetails.customer_name }} ({{ offlineOrderDetails.phone }})</div>
                        <div><strong>Giờ đặt:</strong> {{ offlineOrderDetails.created_at }}</div>
                    </div>
                    <div class="max-h-40 space-y-1 overflow-y-auto">
                        <div v-for="i in offlineOrderDetails.items" :key="i.product_id" class="flex justify-between">
                            <span>{{ i.quantity }}x {{ i.name }}</span>
                            <span>{{ (i.price * i.quantity).toLocaleString() }}đ</span>
                        </div>
                    </div>
                    <div class="flex justify-between border-t border-dashed pt-2 text-sm font-bold">
                        <span>TỔNG CỘNG:</span>
                        <span>{{ offlineOrderDetails.total.toLocaleString() }}đ</span>
                    </div>
                    <div v-if="offlineOrderDetails.note" class="pt-1 text-[11px] italic text-muted-foreground">* Ghi chú: {{ offlineOrderDetails.note }}</div>
                </div>
                <DialogFooter class="flex gap-2 sm:justify-end">
                    <Button variant="outline" @click="showOfflinePrintDialog = false" class="text-xs">Đóng</Button>
                    <Button @click="printOfflineReceipt" class="gap-2 text-xs">
                        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                            <path d="M6 14h12v8H6z" />
                        </svg>
                        In vé Bếp (LAN / Trực tiếp)
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

