<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Minus, MapPin, Plus, ShoppingCart, Store, Truck, X, Loader2, CheckCircle2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fireConfetti } from '@/composables/useConfetti';
import { useOfflineQueue } from '@/composables/useOfflineQueue';
import { useTracking } from '@/composables/useTracking';

const props = defineProps<{
    restaurant: { id: number; name: string; address: string | null; logo_url: string | null; phone: string | null };
    config: {
        slug: string; banner_url: string | null; description: string | null;
        min_order_amount: number; enable_takeaway: boolean; enable_delivery: boolean;
        enable_preorder: boolean; is_open: boolean; operating_hours: any;
    };
    categories: { id: number; name: string; slug: string }[];
    products: Record<number, { id: number; name: string; description: string | null; price: number; image_url: string | null; category_id: number }[]>;
    gateways: { key: string; name: string }[];
    tracking?: { ga_measurement_id?: string | null; fb_pixel_id?: string | null };
}>();

const analytics = useTracking(props.tracking ?? {});
onMounted(() => analytics.init());

const activeCategory = ref<number | null>(props.categories[0]?.id ?? null);
const cart = ref<Record<number, { id: number; name: string; price: number; quantity: number; notes: string }>>({});
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
const orderResult = ref<{ order_number: string; payment_url: string | null; track_url: string } | null>(null);
const calculatingFee = ref(false);

const cartItems = computed(() => Object.values(cart.value));
const cartCount = computed(() => cartItems.value.reduce((sum, i) => sum + i.quantity, 0));
const subtotal = computed(() => cartItems.value.reduce((sum, i) => sum + i.price * i.quantity, 0));
const total = computed(() => subtotal.value + (channel.value === 'delivery' ? deliveryFee.value : 0));

const cartBadgePop = ref(false);

function addToCart(product: any) {
    if (cart.value[product.id]) {
        cart.value[product.id].quantity++;
    } else {
        cart.value[product.id] = { id: product.id, name: product.name, price: product.price, quantity: 1, notes: '' };
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
        deliveryError.value = 'Vui lòng cho phép truy cập vị trí hoặc nhập tọa độ.';

        return;
    }

    calculatingFee.value = true;
    deliveryError.value = '';

    try {
        const { data } = await axios.post(`/api/online/${props.config.slug}/delivery-fee`, {
            latitude: latitude.value, longitude: longitude.value,
        });

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

    navigator.geolocation.getCurrentPosition(pos => {
        latitude.value = pos.coords.latitude;
        longitude.value = pos.coords.longitude;
        calculateDeliveryFee();
    });
}

// Hàng đợi offline: mất mạng vẫn đặt được đơn, tự gửi khi có mạng lại
const { postWithQueue, pendingCount, isOnline } = useOfflineQueue((item, response: any) => {
    if (response?.success) {
        toast.success(`Đã gửi đơn offline thành công! Mã đơn: ${response.order_number}`);
    }
});

async function submitOrder() {
    submitting.value = true;

    try {
        const result = await postWithQueue(`/api/online/${props.config.slug}/checkout`, {
            customer_name: customerName.value,
            phone: phone.value,
            channel: channel.value,
            address: address.value,
            latitude: latitude.value,
            longitude: longitude.value,
            items: cartItems.value.map(i => ({ product_id: i.id, quantity: i.quantity, notes: i.notes })),
            payment_method: paymentMethod.value,
            note: note.value,
            scheduled_at: scheduledAt.value || null,
        });

        if (result.queued) {
            cart.value = {};
            showCart.value = false;
            showCheckout.value = false;
            toast.warning('Mất kết nối mạng — đơn đã được lưu và sẽ tự động gửi ngay khi có mạng trở lại.');

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
        <div v-if="orderResult" class="flex items-center justify-center min-h-screen p-6">
            <Card class="max-w-md w-full text-center">
                <CardContent class="p-8 space-y-4">
                    <CheckCircle2 class="size-16 text-green-500 mx-auto" />
                    <h2 class="text-xl font-bold">Đặt hàng thành công!</h2>
                    <p class="text-muted-foreground">Mã đơn: <strong class="text-foreground">{{ orderResult.order_number }}</strong></p>
                    <p class="text-sm text-muted-foreground">Tổng tiền: {{ total.toLocaleString() }}đ</p>
                    <div class="flex flex-col gap-2 pt-4">
                        <a :href="orderResult.track_url" class="inline-flex items-center justify-center rounded-md bg-primary text-primary-foreground px-4 py-2.5 font-semibold">
                            Theo dõi đơn hàng
                        </a>
                        <a v-if="orderResult.payment_url" :href="orderResult.payment_url" target="_blank" class="inline-flex items-center justify-center rounded-md border px-4 py-2.5 font-semibold hover:bg-muted">
                            Thanh toán ngay
                        </a>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Main storefront -->
        <div v-else>
            <!-- Header -->
            <header class="sticky top-0 z-40 bg-white/95 dark:bg-slate-900/95 backdrop-blur border-b shadow-sm">
                <div class="max-w-3xl mx-auto px-4 py-3 flex items-center gap-3">
                    <img v-if="restaurant.logo_url" :src="restaurant.logo_url" class="h-10 w-10 rounded-full object-cover" />
                    <div class="flex-1 min-w-0">
                        <h1 class="text-lg font-bold truncate">{{ restaurant.name }}</h1>
                        <p v-if="restaurant.address" class="text-xs text-muted-foreground truncate flex items-center gap-1">
                            <MapPin class="size-3 shrink-0" /> {{ restaurant.address }}
                        </p>
                    </div>
                    <Badge v-if="config.is_open" class="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">Mở cửa</Badge>
                    <Badge v-else variant="destructive">Đóng cửa</Badge>
                </div>
            </header>

            <!-- Chỉ báo offline / hàng đợi đơn -->
            <div
                v-if="!isOnline || pendingCount > 0"
                class="sticky top-[60px] z-30 bg-amber-500 text-amber-950 text-center text-xs font-bold py-1.5 px-4"
            >
                <template v-if="!isOnline">📡 Mất kết nối mạng — bạn vẫn có thể đặt đơn, hệ thống sẽ tự gửi khi có mạng lại.</template>
                <template v-else>⏳ Đang gửi {{ pendingCount }} đơn đã lưu offline...</template>
            </div>

            <!-- Banner -->
            <div v-if="config.banner_url" class="max-w-3xl mx-auto">
                <img :src="config.banner_url" class="w-full h-40 object-cover" />
            </div>

            <!-- Categories -->
            <nav class="sticky top-[65px] z-30 bg-white dark:bg-slate-900 border-b">
                <div class="max-w-3xl mx-auto px-4 flex gap-1 overflow-x-auto py-2 scrollbar-hide">
                    <button
                        v-for="cat in categories" :key="cat.id"
                        @click="activeCategory = cat.id"
                        :class="['px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-colors', activeCategory === cat.id ? 'bg-primary text-primary-foreground' : 'bg-muted hover:bg-muted/80']"
                    >{{ cat.name }}</button>
                </div>
            </nav>

            <!-- Products -->
            <main class="max-w-3xl mx-auto px-4 py-4 pb-24">
                <div v-for="cat in categories" :key="cat.id" v-show="activeCategory === cat.id" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div
                        v-for="p in (products[cat.id] ?? [])" :key="p.id"
                        class="bg-white dark:bg-slate-900 rounded-xl border overflow-hidden shadow-sm hover:shadow-md transition-shadow"
                    >
                        <div class="aspect-square bg-muted relative">
                            <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="w-full h-full object-cover" />
                            <div v-else class="flex items-center justify-center h-full text-muted-foreground"><Store class="size-8" /></div>
                        </div>
                        <div class="p-3 space-y-1">
                            <p class="text-sm font-semibold line-clamp-2 leading-tight">{{ p.name }}</p>
                            <p class="text-primary font-bold text-sm">{{ p.price.toLocaleString() }}đ</p>
                            <Button size="sm" class="w-full gap-1 h-8 text-xs" @click="addToCart(p)">
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
                class="fixed bottom-6 right-6 z-50 bg-primary text-primary-foreground rounded-full p-4 shadow-xl flex items-center gap-2 font-bold transition-transform hover:scale-105"
                @animationend="cartBadgePop = false"
            >
                <ShoppingCart class="size-5" />
                <span>{{ cartCount }} món — {{ subtotal.toLocaleString() }}đ</span>
            </button>

            <!-- Cart Sheet -->
            <div v-if="showCart" class="fixed inset-0 z-50 flex items-end justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showCart = false"></div>
                <div class="relative bg-white dark:bg-slate-900 w-full max-w-lg rounded-t-2xl max-h-[80vh] overflow-y-auto p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold">Giỏ hàng ({{ cartCount }})</h2>
                        <button @click="showCart = false"><X class="size-5" /></button>
                    </div>

                    <div v-for="item in cartItems" :key="item.id" class="flex items-center gap-3 border-b pb-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate">{{ item.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ item.price.toLocaleString() }}đ</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="updateQuantity(item.id, -1)" class="h-7 w-7 rounded-full border flex items-center justify-center"><Minus class="size-3" /></button>
                            <span class="text-sm font-bold w-6 text-center">{{ item.quantity }}</span>
                            <button @click="updateQuantity(item.id, 1)" class="h-7 w-7 rounded-full border flex items-center justify-center"><Plus class="size-3" /></button>
                        </div>
                        <button @click="removeFromCart(item.id)" class="text-red-500"><X class="size-4" /></button>
                    </div>

                    <div class="flex justify-between font-bold text-lg pt-2">
                        <span>Tạm tính</span>
                        <span>{{ subtotal.toLocaleString() }}đ</span>
                    </div>

                    <Button class="w-full h-12 text-base font-bold" @click="showCart = false; showCheckout = true">
                        Tiến hành đặt hàng
                    </Button>
                </div>
            </div>

            <!-- Checkout Sheet -->
            <div v-if="showCheckout" class="fixed inset-0 z-50 flex items-end justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showCheckout = false"></div>
                <div class="relative bg-white dark:bg-slate-900 w-full max-w-lg rounded-t-2xl max-h-[85vh] overflow-y-auto p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold">Thanh toán</h2>
                        <button @click="showCheckout = false"><X class="size-5" /></button>
                    </div>

                    <!-- Channel selection -->
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-if="config.enable_takeaway"
                            @click="channel = 'takeaway'"
                            :class="['flex items-center gap-2 p-3 rounded-xl border-2 text-sm font-semibold', channel === 'takeaway' ? 'border-primary bg-primary/5' : 'border-muted']"
                        ><Store class="size-4" /> Mang về</button>
                        <button
                            v-if="config.enable_delivery"
                            @click="channel = 'delivery'; getLocation()"
                            :class="['flex items-center gap-2 p-3 rounded-xl border-2 text-sm font-semibold', channel === 'delivery' ? 'border-primary bg-primary/5' : 'border-muted']"
                        ><Truck class="size-4" /> Giao hàng</button>
                    </div>

                    <!-- Customer info -->
                    <div class="grid gap-3">
                        <div class="grid gap-1.5">
                            <Label>Họ tên</Label>
                            <Input v-model="customerName" placeholder="Nguyễn Văn A" required />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Số điện thoại</Label>
                            <Input v-model="phone" placeholder="0912345678" required />
                        </div>
                    </div>

                    <!-- Delivery address -->
                    <div v-if="channel === 'delivery'" class="grid gap-3 border-t pt-3">
                        <div class="grid gap-1.5">
                            <Label>Địa chỉ giao hàng</Label>
                            <Input v-model="address" placeholder="Số nhà, đường, quận..." required />
                        </div>
                        <Button variant="outline" size="sm" @click="getLocation" :disabled="calculatingFee" class="gap-1.5">
                            <MapPin class="size-3.5" />
                            {{ calculatingFee ? 'Đang tính...' : 'Lấy vị trí & tính phí ship' }}
                        </Button>
                        <p v-if="deliveryFee > 0" class="text-sm text-green-600 font-medium">Phí giao hàng: {{ deliveryFee.toLocaleString() }}đ</p>
                        <p v-if="deliveryError" class="text-sm text-red-500">{{ deliveryError }}</p>
                    </div>

                    <!-- Pre-order -->
                    <div v-if="config.enable_preorder" class="grid gap-1.5 border-t pt-3">
                        <Label>Đặt trước (tùy chọn)</Label>
                        <Input type="datetime-local" v-model="scheduledAt" />
                    </div>

                    <!-- Payment -->
                    <div class="grid gap-1.5 border-t pt-3">
                        <Label>Phương thức thanh toán</Label>
                        <div class="grid gap-2">
                            <label
                                v-for="gw in gateways" :key="gw.key"
                                :class="['flex items-center gap-2 p-3 rounded-lg border cursor-pointer text-sm', paymentMethod === gw.key ? 'border-primary bg-primary/5' : '']"
                            >
                                <input type="radio" :value="gw.key" v-model="paymentMethod" class="sr-only" />
                                <span class="font-medium">{{ gw.name }}</span>
                            </label>
                            <label
                                v-if="channel === 'delivery'"
                                :class="['flex items-center gap-2 p-3 rounded-lg border cursor-pointer text-sm', paymentMethod === 'cod' ? 'border-primary bg-primary/5' : '']"
                            >
                                <input type="radio" value="cod" v-model="paymentMethod" class="sr-only" />
                                <span class="font-medium">Thanh toán khi nhận hàng (COD)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Note -->
                    <div class="grid gap-1.5">
                        <Label>Ghi chú</Label>
                        <Input v-model="note" placeholder="Ghi chú thêm cho nhà hàng..." />
                    </div>

                    <!-- Summary -->
                    <div class="border-t pt-3 space-y-1 text-sm">
                        <div class="flex justify-between"><span>Tạm tính</span><span>{{ subtotal.toLocaleString() }}đ</span></div>
                        <div v-if="channel === 'delivery' && deliveryFee > 0" class="flex justify-between">
                            <span>Phí giao hàng</span><span>{{ deliveryFee.toLocaleString() }}đ</span>
                        </div>
                        <div class="flex justify-between font-bold text-lg pt-1">
                            <span>Tổng cộng</span><span class="text-primary">{{ total.toLocaleString() }}đ</span>
                        </div>
                    </div>

                    <Button
                        class="w-full h-12 text-base font-bold gap-2"
                        :disabled="submitting || !customerName || !phone || cartCount === 0"
                        @click="submitOrder"
                    >
                        <Loader2 v-if="submitting" class="size-5 animate-spin" />
                        {{ submitting ? 'Đang đặt hàng...' : 'Xác nhận đặt hàng' }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
