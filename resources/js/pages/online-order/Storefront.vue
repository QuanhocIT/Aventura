<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Minus,
    MapPin,
    Plus,
    ShoppingCart,
    Store,
    Truck,
    X,
    Loader2,
    CheckCircle2,
    AlertTriangle,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
        // @ts-ignore
        if (!window.turnstile) {
            const script = document.createElement('script');
            script.src =
                'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadTurnstileCallbackStorefront';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);

            // @ts-ignore
            window.onloadTurnstileCallbackStorefront = () => {
                // @ts-ignore
                window.turnstile.render('#turnstile-container-storefront', {
                    sitekey: props.turnstileSiteKey,
                    callback: (token: string) => {
                        turnstileToken.value = token;
                    },
                });
            };
        } else {
            setTimeout(() => {
                // @ts-ignore
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

const activeCategory = ref<number | null>(props.categories[0]?.id ?? null);
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
    if (!offlineOrderDetails.value) return;

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

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
        <!-- Success screen -->
        <div
            v-if="orderResult"
            class="flex min-h-screen items-center justify-center p-6"
        >
            <Card class="w-full max-w-md text-center">
                <CardContent class="space-y-4 p-8">
                    <CheckCircle2 class="mx-auto size-16 text-green-500" />
                    <h2 class="text-xl font-bold">Đặt hàng thành công!</h2>
                    <p class="text-muted-foreground">
                        Mã đơn:
                        <strong class="text-foreground">{{
                            orderResult.order_number
                        }}</strong>
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Tổng tiền: {{ total.toLocaleString() }}đ
                    </p>
                    <div class="flex flex-col gap-2 pt-4">
                        <a
                            :href="orderResult.track_url"
                            class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2.5 font-semibold text-primary-foreground"
                        >
                            Theo dõi đơn hàng
                        </a>
                        <a
                            v-if="orderResult.payment_url"
                            :href="orderResult.payment_url"
                            target="_blank"
                            class="inline-flex items-center justify-center rounded-md border px-4 py-2.5 font-semibold hover:bg-muted"
                        >
                            Thanh toán ngay
                        </a>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Main storefront -->
        <div v-else>
            <!-- Header -->
            <header
                class="sticky top-0 z-40 border-b bg-white/95 shadow-sm backdrop-blur dark:bg-slate-900/95"
            >
                <div
                    class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-3"
                >
                    <img
                        v-if="restaurant.logo_url"
                        :src="restaurant.logo_url"
                        class="h-10 w-10 rounded-full object-cover"
                    />
                    <div class="min-w-0 flex-1">
                        <h1 class="truncate text-lg font-bold">
                            {{ restaurant.name }}
                        </h1>
                        <p
                            v-if="restaurant.address"
                            class="flex items-center gap-1 truncate text-xs text-muted-foreground"
                        >
                            <MapPin class="size-3 shrink-0" />
                            {{ restaurant.address }}
                        </p>
                    </div>
                    <Badge
                        v-if="config.is_open"
                        class="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300"
                        >Mở cửa</Badge
                    >
                    <Badge v-else variant="destructive">Đóng cửa</Badge>
                </div>
            </header>

            <!-- Chỉ báo offline / hàng đợi đơn -->
            <div
                v-if="!isOnline || pendingCount > 0"
                class="sticky top-[60px] z-30 bg-amber-500 px-4 py-1.5 text-center text-xs font-bold text-amber-950"
            >
                <template v-if="!isOnline"
                    >📡 Mất kết nối mạng — bạn vẫn có thể đặt đơn, hệ thống sẽ
                    tự gửi khi có mạng lại.</template
                >
                <template v-else
                    >⏳ Đang gửi {{ pendingCount }} đơn đã lưu
                    offline...</template
                >
            </div>

            <!-- Banner -->
            <div v-if="config.banner_url" class="mx-auto max-w-3xl">
                <img
                    :src="config.banner_url"
                    class="h-40 w-full object-cover"
                />
            </div>

            <!-- Categories -->
            <nav
                class="sticky top-[65px] z-30 border-b bg-white dark:bg-slate-900"
            >
                <div
                    class="scrollbar-hide mx-auto flex max-w-3xl gap-1 overflow-x-auto px-4 py-2"
                >
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        @click="activeCategory = cat.id"
                        :class="[
                            'rounded-full px-3 py-1.5 text-xs font-semibold whitespace-nowrap transition-colors',
                            activeCategory === cat.id
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted hover:bg-muted/80',
                        ]"
                    >
                        {{ cat.name }}
                    </button>
                </div>
            </nav>

            <!-- Products -->
            <main class="mx-auto max-w-3xl px-4 py-4 pb-24">
                <div
                    v-for="cat in categories"
                    :key="cat.id"
                    v-show="activeCategory === cat.id"
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3"
                >
                    <div
                        v-for="p in products[cat.id] ?? []"
                        :key="p.id"
                        class="overflow-hidden rounded-xl border bg-white shadow-sm transition-shadow hover:shadow-md dark:bg-slate-900"
                    >
                        <div class="relative aspect-square bg-muted">
                            <img
                                v-if="p.image_url"
                                :src="p.image_url"
                                :alt="p.name"
                                class="h-full w-full object-cover"
                            />
                            <div
                                v-else
                                class="flex h-full items-center justify-center text-muted-foreground"
                            >
                                <Store class="size-8" />
                            </div>
                        </div>
                        <div class="space-y-1 p-3">
                            <p
                                class="line-clamp-2 text-sm leading-tight font-semibold"
                            >
                                {{ p.name }}
                            </p>
                            <p class="text-sm font-bold text-primary">
                                {{ p.price.toLocaleString() }}đ
                            </p>
                            <Button
                                size="sm"
                                class="h-8 w-full gap-1 text-xs"
                                @click="addToCart(p)"
                            >
                                <Plus class="size-3" /> Thêm
                            </Button>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Cart FAB -->
            <button
                v-if="cartCount > 0"
                @click="showCart = true"
                :class="cartBadgePop ? 'animate-badge-pop' : ''"
                class="fixed right-6 bottom-6 z-50 flex items-center gap-2 rounded-full bg-primary p-4 font-bold text-primary-foreground shadow-xl transition-transform hover:scale-105"
                @animationend="cartBadgePop = false"
            >
                <ShoppingCart class="size-5" />
                <span
                    >{{ cartCount }} món —
                    {{ subtotal.toLocaleString() }}đ</span
                >
            </button>

            <!-- Cart Sheet -->
            <div
                v-if="showCart"
                class="fixed inset-0 z-50 flex items-end justify-center"
            >
                <div
                    class="absolute inset-0 bg-black/50"
                    @click="showCart = false"
                ></div>
                <div
                    class="relative max-h-[80vh] w-full max-w-lg space-y-4 overflow-y-auto rounded-t-2xl bg-white p-6 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold">
                            Giỏ hàng ({{ cartCount }})
                        </h2>
                        <button @click="showCart = false">
                            <X class="size-5" />
                        </button>
                    </div>

                    <div
                        v-for="item in cartItems"
                        :key="item.id"
                        class="flex items-center gap-3 border-b pb-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ item.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ item.price.toLocaleString() }}đ
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                @click="updateQuantity(item.id, -1)"
                                class="flex h-7 w-7 items-center justify-center rounded-full border"
                            >
                                <Minus class="size-3" />
                            </button>
                            <span class="w-6 text-center text-sm font-bold">{{
                                item.quantity
                            }}</span>
                            <button
                                @click="updateQuantity(item.id, 1)"
                                class="flex h-7 w-7 items-center justify-center rounded-full border"
                            >
                                <Plus class="size-3" />
                            </button>
                        </div>
                        <button
                            @click="removeFromCart(item.id)"
                            class="text-red-500"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <div class="flex justify-between pt-2 text-lg font-bold">
                        <span>Tạm tính</span>
                        <span>{{ subtotal.toLocaleString() }}đ</span>
                    </div>

                    <Button
                        class="h-12 w-full text-base font-bold"
                        @click="
                            showCart = false;
                            showCheckout = true;
                        "
                    >
                        Tiến hành đặt hàng
                    </Button>
                </div>
            </div>

            <!-- Checkout Sheet -->
            <div
                v-if="showCheckout"
                class="fixed inset-0 z-50 flex items-end justify-center"
            >
                <div
                    class="absolute inset-0 bg-black/50"
                    @click="showCheckout = false"
                ></div>
                <div
                    class="relative max-h-[85vh] w-full max-w-lg space-y-4 overflow-y-auto rounded-t-2xl bg-white p-6 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold">Thanh toán</h2>
                        <button @click="showCheckout = false">
                            <X class="size-5" />
                        </button>
                    </div>

                    <!-- Channel selection -->
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-if="config.enable_takeaway"
                            @click="channel = 'takeaway'"
                            :class="[
                                'flex items-center gap-2 rounded-xl border-2 p-3 text-sm font-semibold',
                                channel === 'takeaway'
                                    ? 'border-primary bg-primary/5'
                                    : 'border-muted',
                            ]"
                        >
                            <Store class="size-4" /> Mang về
                        </button>
                        <button
                            v-if="config.enable_delivery"
                            @click="
                                channel = 'delivery';
                                getLocation();
                            "
                            :class="[
                                'flex items-center gap-2 rounded-xl border-2 p-3 text-sm font-semibold',
                                channel === 'delivery'
                                    ? 'border-primary bg-primary/5'
                                    : 'border-muted',
                            ]"
                        >
                            <Truck class="size-4" /> Giao hàng
                        </button>
                    </div>

                    <!-- Customer info -->
                    <div class="grid gap-3">
                        <div class="grid gap-1.5">
                            <Label>Họ tên</Label>
                            <Input
                                v-model="customerName"
                                placeholder="Nguyễn Văn A"
                                required
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Số điện thoại</Label>
                            <Input
                                v-model="phone"
                                placeholder="0912345678"
                                required
                            />
                        </div>
                    </div>

                    <!-- Delivery address -->
                    <div
                        v-if="channel === 'delivery'"
                        class="grid gap-3 border-t pt-3"
                    >
                        <div class="grid gap-1.5">
                            <Label>Địa chỉ giao hàng</Label>
                            <Input
                                v-model="address"
                                placeholder="Số nhà, đường, quận..."
                                required
                            />
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="getLocation"
                            :disabled="calculatingFee"
                            class="gap-1.5"
                        >
                            <MapPin class="size-3.5" />
                            {{
                                calculatingFee
                                    ? 'Đang tính...'
                                    : 'Lấy vị trí & tính phí ship'
                            }}
                        </Button>
                        <p
                            v-if="deliveryFee > 0"
                            class="text-sm font-medium text-green-600"
                        >
                            Phí giao hàng: {{ deliveryFee.toLocaleString() }}đ
                        </p>
                        <p v-if="deliveryError" class="text-sm text-red-500">
                            {{ deliveryError }}
                        </p>
                    </div>

                    <!-- Pre-order -->
                    <div
                        v-if="config.enable_preorder"
                        class="grid gap-1.5 border-t pt-3"
                    >
                        <Label>Đặt trước (tùy chọn)</Label>
                        <Input type="datetime-local" v-model="scheduledAt" />
                    </div>

                    <!-- Payment -->
                    <div class="grid gap-1.5 border-t pt-3">
                        <Label>Phương thức thanh toán</Label>
                        <div class="grid gap-2">
                            <label
                                v-for="gw in gateways"
                                :key="gw.key"
                                :class="[
                                    'flex cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm',
                                    paymentMethod === gw.key
                                        ? 'border-primary bg-primary/5'
                                        : '',
                                ]"
                            >
                                <input
                                    type="radio"
                                    :value="gw.key"
                                    v-model="paymentMethod"
                                    class="sr-only"
                                />
                                <span class="font-medium">{{ gw.name }}</span>
                            </label>
                            <label
                                v-if="channel === 'delivery'"
                                :class="[
                                    'flex cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm',
                                    paymentMethod === 'cod'
                                        ? 'border-primary bg-primary/5'
                                        : '',
                                ]"
                            >
                                <input
                                    type="radio"
                                    value="cod"
                                    v-model="paymentMethod"
                                    class="sr-only"
                                />
                                <span class="font-medium"
                                    >Thanh toán khi nhận hàng (COD)</span
                                >
                            </label>
                        </div>
                    </div>

                    <!-- Note -->
                    <div class="grid gap-1.5">
                        <Label>Ghi chú</Label>
                        <Input
                            v-model="note"
                            placeholder="Ghi chú thêm cho nhà hàng..."
                        />
                    </div>

                    <!-- CAPTCHA / Turnstile security verification block -->
                    <div
                        v-if="turnstileSiteKey || captchaQuestion"
                        class="my-1 grid gap-2 border-t pt-3"
                    >
                        <Label
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-300"
                        >
                            <svg
                                class="size-3.5 text-primary"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                            >
                                <path
                                    d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"
                                />
                            </svg>
                            Xác minh bảo mật
                        </Label>

                        <!-- Cloudflare Turnstile -->
                        <div v-if="turnstileSiteKey">
                            <div
                                id="turnstile-container-storefront"
                                class="my-1.5 flex justify-center"
                            ></div>
                            <input
                                type="hidden"
                                name="cf-turnstile-response"
                                :value="turnstileToken"
                            />
                        </div>

                        <!-- Math CAPTCHA -->
                        <div v-else-if="captchaQuestion" class="grid gap-2">
                            <span
                                class="text-xs leading-normal font-semibold text-muted-foreground"
                            >
                                Vui lòng nhập kết quả của phép tính:
                                <strong
                                    class="rounded border border-primary/20 bg-primary/10 px-1.5 py-0.5 font-mono text-sm text-primary"
                                    >{{ captchaQuestion }}</strong
                                >
                            </span>
                            <input
                                type="hidden"
                                name="captcha_token"
                                :value="captchaToken"
                            />
                            <Input
                                id="captcha_answer"
                                type="number"
                                v-model="captchaAnswer"
                                required
                                placeholder="Nhập kết quả"
                                class="h-9 rounded-xl border-zinc-200 text-xs font-semibold shadow-sm transition-all duration-300 focus-visible:border-primary focus-visible:ring-primary/20 dark:border-zinc-800"
                            />
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="space-y-1 border-t pt-3 text-sm">
                        <div class="flex justify-between">
                            <span>Tạm tính</span
                            ><span>{{ subtotal.toLocaleString() }}đ</span>
                        </div>
                        <div
                            v-if="channel === 'delivery' && deliveryFee > 0"
                            class="flex justify-between"
                        >
                            <span>Phí giao hàng</span
                            ><span>{{ deliveryFee.toLocaleString() }}đ</span>
                        </div>
                        <div
                            class="flex justify-between pt-1 text-lg font-bold"
                        >
                            <span>Tổng cộng</span
                            ><span class="text-primary"
                                >{{ total.toLocaleString() }}đ</span
                            >
                        </div>
                    </div>

                    <Button
                        class="h-12 w-full gap-2 text-base font-bold"
                        :disabled="
                            submitting ||
                            !customerName ||
                            !phone ||
                            cartCount === 0
                        "
                        @click="submitOrder"
                    >
                        <Loader2
                            v-if="submitting"
                            class="size-5 animate-spin"
                        />
                        {{
                            submitting
                                ? 'Đang đặt hàng...'
                                : 'Xác nhận đặt hàng'
                        }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Dialog In Offline Dự Phòng (LAN Fallback) -->
        <Dialog v-model:open="showOfflinePrintDialog">
            <DialogContent class="w-full max-w-md rounded-2xl">
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center gap-2 text-rose-600 dark:text-rose-500"
                    >
                        <AlertTriangle class="size-5 shrink-0" />
                        Chế độ Offline - In vé Bếp
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Đang mất kết nối mạng. Đơn hàng của bạn đã được ghi nhận
                        ngoại tuyến và sẽ tự động gửi khi có mạng trở lại. Vui
                        lòng in vé bếp để bếp thực hiện chế biến thủ công.
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="offlineOrderDetails"
                    class="space-y-3 rounded-xl border bg-slate-50 p-4 font-mono text-xs text-slate-800 dark:bg-slate-900 dark:text-slate-200"
                >
                    <div class="space-y-1 border-b border-dashed pb-2">
                        <div>
                            <strong>Đơn hàng:</strong>
                            {{ offlineOrderDetails.order_number }}
                        </div>
                        <div>
                            <strong>Hình thức:</strong>
                            {{
                                offlineOrderDetails.channel === 'delivery'
                                    ? 'Giao hàng'
                                    : 'Mang về'
                            }}
                        </div>
                        <div>
                            <strong>Khách:</strong>
                            {{ offlineOrderDetails.customer_name }} ({{
                                offlineOrderDetails.phone
                            }})
                        </div>
                        <div>
                            <strong>Giờ đặt:</strong>
                            {{ offlineOrderDetails.created_at }}
                        </div>
                    </div>
                    <div class="max-h-40 space-y-1 overflow-y-auto">
                        <div
                            v-for="i in offlineOrderDetails.items"
                            :key="i.product_id"
                            class="flex justify-between"
                        >
                            <span>{{ i.quantity }}x {{ i.name }}</span>
                            <span
                                >{{
                                    (i.price * i.quantity).toLocaleString()
                                }}đ</span
                            >
                        </div>
                    </div>
                    <div
                        class="flex justify-between border-t border-dashed pt-2 text-sm font-bold"
                    >
                        <span>TỔNG CỘNG:</span>
                        <span
                            >{{
                                offlineOrderDetails.total.toLocaleString()
                            }}đ</span
                        >
                    </div>
                    <div
                        v-if="offlineOrderDetails.note"
                        class="pt-1 text-[11px] text-muted-foreground italic"
                    >
                        * Ghi chú: {{ offlineOrderDetails.note }}
                    </div>
                </div>

                <DialogFooter class="flex gap-2 sm:justify-end">
                    <Button
                        variant="outline"
                        @click="showOfflinePrintDialog = false"
                        class="text-xs"
                    >
                        Đóng
                    </Button>
                    <Button @click="printOfflineReceipt" class="gap-2 text-xs">
                        <svg
                            class="size-4"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"
                            />
                            <path d="M6 14h12v8H6z" />
                        </svg>
                        In vé Bếp (LAN / Trực tiếp)
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
