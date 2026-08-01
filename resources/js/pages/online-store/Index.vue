<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    ExternalLink,
    Globe,
    Loader2,
    Save,
    Copy,
    Check,
    Image as ImageIcon,
    ShoppingBag,
    Truck,
    Calendar,
    DollarSign,
    MapPin,
    Store,
    FileText,
    AlertTriangle,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    config: any;
    storeUrl: string | null;
}>();

const page = usePage();

watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    },
);

const form = useForm({
    is_active: props.config.is_active ?? false,
    slug: props.config.slug ?? '',
    banner_url: props.config.banner_url ?? '',
    description: props.config.description ?? '',
    min_order_amount: props.config.min_order_amount ?? 0,
    delivery_fee_per_km: props.config.delivery_fee_per_km ?? 5000,
    delivery_base_fee: props.config.delivery_base_fee ?? 15000,
    max_delivery_km: props.config.max_delivery_km ?? 10,
    enable_takeaway: props.config.enable_takeaway ?? true,
    enable_delivery: props.config.enable_delivery ?? true,
    enable_preorder: props.config.enable_preorder ?? false,
    accepted_payments: props.config.accepted_payments ?? ['bank_transfer'],
    operating_hours: props.config.operating_hours ?? null,
});

const isCopied = ref(false);
function copyStoreUrl() {
    if (!props.storeUrl) {
        return;
    }

    navigator.clipboard.writeText(props.storeUrl);
    isCopied.value = true;
    toast.success('Đã sao chép đường dẫn cửa hàng trực tuyến!');
    setTimeout(() => {
        isCopied.value = false;
    }, 2000);
}

function submit() {
    form.post('/online-store', { preserveScroll: true });
}
</script>

<template>
    <Head title="Cấu hình Cửa hàng Online" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 lg:p-6">
        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400"
                >
                    <Globe class="size-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        Cửa hàng Online
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Cấu hình trang đặt hàng online cho khách hàng của bạn.
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button
                    v-if="storeUrl"
                    type="button"
                    @click="copyStoreUrl"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border bg-background px-3.5 py-2 text-xs font-semibold text-foreground transition hover:bg-muted active:scale-95"
                >
                    <component
                        :is="isCopied ? Check : Copy"
                        class="size-3.5"
                        :class="isCopied ? 'text-emerald-500' : ''"
                    />
                    {{ isCopied ? 'Đã chép' : 'Sao chép link' }}
                </button>
                <a
                    v-if="storeUrl"
                    :href="storeUrl"
                    target="_blank"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border bg-background px-3.5 py-2 text-xs font-semibold text-foreground transition hover:bg-muted active:scale-95"
                >
                    <ExternalLink class="size-3.5" />
                    Xem trang
                </a>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- ── Left Column: Brand & Service configuration (2/3 width) ── -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Nhận diện cửa hàng -->
                    <Card class="overflow-hidden shadow-sm">
                        <div
                            class="border-b border-border bg-slate-50/50 p-5 dark:bg-slate-900/20"
                        >
                            <h3
                                class="flex items-center gap-2 text-sm font-bold text-foreground"
                            >
                                <Store class="size-4.5 text-blue-500" />
                                Thiết lập Nhận diện & Thương hiệu
                            </h3>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Cấu hình đường dẫn kết nối và hình ảnh đại diện
                                thương hiệu.
                            </p>
                        </div>
                        <CardContent class="space-y-5 p-6">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="grid gap-1.5">
                                    <Label
                                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                        >Đường dẫn cửa hàng (Slug)</Label
                                    >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="rounded-xl border border-border bg-muted px-3 py-2.5 text-xs font-bold text-muted-foreground"
                                            >/order/</span
                                        >
                                        <Input
                                            v-model="form.slug"
                                            placeholder="ten-nha-hang"
                                            class="h-10 rounded-xl font-bold"
                                        />
                                    </div>
                                    <p
                                        v-if="form.errors.slug"
                                        class="mt-1 text-xs font-semibold text-rose-500"
                                    >
                                        {{ form.errors.slug }}
                                    </p>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label
                                        class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                        >Đường dẫn Banner URL</Label
                                    >
                                    <div class="relative">
                                        <Input
                                            v-model="form.banner_url"
                                            placeholder="https://images.unsplash.com/..."
                                            class="h-10 rounded-xl pl-8 text-xs"
                                        />
                                        <ImageIcon
                                            class="absolute top-3 left-2.5 size-4 text-muted-foreground/60"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Live Banner Preview Card -->
                            <div class="grid gap-1.5 pt-2">
                                <Label
                                    class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                    >Xem trước ảnh bìa (Live Preview)</Label
                                >
                                <div
                                    class="group relative flex h-36 w-full items-center justify-center overflow-hidden rounded-xl border border-border bg-slate-50 dark:bg-slate-900"
                                >
                                    <img
                                        v-if="form.banner_url"
                                        :src="form.banner_url"
                                        alt="Banner Preview"
                                        class="h-full w-full object-cover transition duration-300 group-hover:scale-102"
                                    />
                                    <div
                                        v-else
                                        class="flex flex-col items-center gap-2 text-muted-foreground/50"
                                    >
                                        <ImageIcon class="size-8 stroke-1" />
                                        <span class="text-xs font-medium"
                                            >Chưa có ảnh bìa thiết lập</span
                                        >
                                    </div>
                                    <!-- Visual Overlay if image exists -->
                                    <div
                                        v-if="form.banner_url"
                                        class="absolute inset-0 flex items-end bg-gradient-to-t from-slate-950/70 via-transparent to-transparent p-4"
                                    >
                                        <div class="text-white">
                                            <p class="text-sm font-extrabold">
                                                {{
                                                    form.slug
                                                        ? '/' + form.slug
                                                        : 'Aventura Online Store'
                                                }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-[10px] text-slate-300"
                                            >
                                                Đặt hàng trực tuyến mượt mà
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-1.5">
                                <Label
                                    class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                    >Mô tả ngắn nhà hàng</Label
                                >
                                <div class="relative">
                                    <Input
                                        v-model="form.description"
                                        placeholder="Chào mừng quý khách đến với cửa hàng đặt món trực tuyến của chúng tôi..."
                                        class="h-10 rounded-xl pl-8 text-xs"
                                    />
                                    <FileText
                                        class="absolute top-3 left-2.5 size-4 text-muted-foreground/60"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Fulfillment Options (Các hình thức phục vụ) -->
                    <Card class="overflow-hidden shadow-sm">
                        <div
                            class="border-b border-border bg-slate-50/50 p-5 dark:bg-slate-900/20"
                        >
                            <h3
                                class="flex items-center gap-2 text-sm font-bold text-foreground"
                            >
                                <ShoppingBag class="size-4.5 text-indigo-500" />
                                Hình thức phục vụ khách hàng
                            </h3>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Lựa chọn các hình thức cung cấp dịch vụ trên
                                trang đặt hàng.
                            </p>
                        </div>
                        <CardContent class="p-6">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <!-- Option 1: Takeaway (Mang về) -->
                                <div
                                    @click="
                                        form.enable_takeaway =
                                            !form.enable_takeaway
                                    "
                                    :class="[
                                        'group relative flex cursor-pointer flex-col gap-3 rounded-2xl border p-4 transition-all duration-200 select-none',
                                        form.enable_takeaway
                                            ? 'border-indigo-500 bg-indigo-500/[0.03] dark:bg-indigo-950/10'
                                            : 'border-border bg-card hover:bg-muted/30',
                                    ]"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div
                                            :class="[
                                                'rounded-xl border p-2 transition-colors',
                                                form.enable_takeaway
                                                    ? 'border-indigo-200 bg-indigo-500/10 text-indigo-600 dark:border-indigo-900/50 dark:text-indigo-400'
                                                    : 'border-border bg-muted text-muted-foreground',
                                            ]"
                                        >
                                            <ShoppingBag class="size-4.5" />
                                        </div>
                                        <div
                                            v-if="form.enable_takeaway"
                                            class="flex h-4.5 w-4.5 items-center justify-center rounded-full bg-indigo-500 text-[9px] font-bold text-white"
                                        >
                                            <Check class="size-3 stroke-3" />
                                        </div>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs font-black text-foreground"
                                        >
                                            Tự đến lấy (Takeaway)
                                        </p>
                                        <p
                                            class="mt-0.5 text-[10px] text-muted-foreground"
                                        >
                                            Cho phép khách đặt món và tự qua cửa
                                            hàng lấy đồ.
                                        </p>
                                    </div>
                                </div>

                                <!-- Option 2: Delivery (Giao hàng) -->
                                <div
                                    @click="
                                        form.enable_delivery =
                                            !form.enable_delivery
                                    "
                                    :class="[
                                        'group relative flex cursor-pointer flex-col gap-3 rounded-2xl border p-4 transition-all duration-200 select-none',
                                        form.enable_delivery
                                            ? 'border-emerald-500 bg-emerald-500/[0.03] dark:bg-emerald-950/10'
                                            : 'border-border bg-card hover:bg-muted/30',
                                    ]"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div
                                            :class="[
                                                'rounded-xl border p-2 transition-colors',
                                                form.enable_delivery
                                                    ? 'border-emerald-200 bg-emerald-500/10 text-emerald-600 dark:border-emerald-900/50 dark:text-emerald-400'
                                                    : 'border-border bg-muted text-muted-foreground',
                                            ]"
                                        >
                                            <Truck class="size-4.5" />
                                        </div>
                                        <div
                                            v-if="form.enable_delivery"
                                            class="flex h-4.5 w-4.5 items-center justify-center rounded-full bg-emerald-500 text-[9px] font-bold text-white"
                                        >
                                            <Check class="size-3 stroke-3" />
                                        </div>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs font-black text-foreground"
                                        >
                                            Giao tận nơi (Delivery)
                                        </p>
                                        <p
                                            class="mt-0.5 text-[10px] text-muted-foreground"
                                        >
                                            Tích hợp giao hàng tận nhà theo phạm
                                            vi thiết lập phí ship.
                                        </p>
                                    </div>
                                </div>

                                <!-- Option 3: Preorder (Đặt trước) -->
                                <div
                                    @click="
                                        form.enable_preorder =
                                            !form.enable_preorder
                                    "
                                    :class="[
                                        'group relative flex cursor-pointer flex-col gap-3 rounded-2xl border p-4 transition-all duration-200 select-none',
                                        form.enable_preorder
                                            ? 'border-amber-500 bg-amber-500/[0.03] dark:bg-amber-950/10'
                                            : 'border-border bg-card hover:bg-muted/30',
                                    ]"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div
                                            :class="[
                                                'rounded-xl border p-2 transition-colors',
                                                form.enable_preorder
                                                    ? 'border-amber-200 bg-amber-500/10 text-amber-600 dark:border-amber-900/50 dark:text-amber-400'
                                                    : 'border-border bg-muted text-muted-foreground',
                                            ]"
                                        >
                                            <Calendar class="size-4.5" />
                                        </div>
                                        <div
                                            v-if="form.enable_preorder"
                                            class="flex h-4.5 w-4.5 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white"
                                        >
                                            <Check class="size-3 stroke-3" />
                                        </div>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs font-black text-foreground"
                                        >
                                            Lên lịch trước (Preorder)
                                        </p>
                                        <p
                                            class="mt-0.5 text-[10px] text-muted-foreground"
                                        >
                                            Khách hàng lên lịch hẹn ngày/giờ
                                            nhận món trong tương lai.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- ── Right Column: Status Toggle & Shipping Rules (1/3 width) ── -->
                <div class="space-y-6 lg:col-span-1">
                    <!-- Hoạt động Switch -->
                    <Card class="overflow-hidden shadow-sm">
                        <div
                            class="border-b border-border bg-slate-50/50 p-5 dark:bg-slate-900/20"
                        >
                            <p class="text-sm font-bold text-foreground">
                                Trạng thái Cửa hàng
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Đóng/Mở kết nối trực tuyến nhanh.
                            </p>
                        </div>
                        <CardContent class="flex flex-col gap-4 p-6">
                            <!-- Glowing status block -->
                            <div
                                class="flex flex-col items-center gap-1 rounded-2xl border p-4 text-center transition-all duration-300"
                                :class="[
                                    form.is_active
                                        ? 'border-emerald-200 bg-emerald-500/10 text-emerald-800 shadow-sm shadow-emerald-500/5 dark:border-emerald-900/40 dark:text-emerald-400'
                                        : 'border-rose-200 bg-rose-500/10 text-rose-800 shadow-sm shadow-rose-500/5 dark:border-rose-900/40 dark:text-rose-400',
                                ]"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="size-2.5 rounded-full"
                                        :class="
                                            form.is_active
                                                ? 'animate-pulse bg-emerald-500'
                                                : 'bg-rose-500'
                                        "
                                    />
                                    <span
                                        class="text-xs font-black tracking-wider uppercase"
                                        >{{
                                            form.is_active
                                                ? 'Cửa hàng Đang mở'
                                                : 'Cửa hàng Đang đóng'
                                        }}</span
                                    >
                                </div>
                                <span
                                    class="mt-1 text-[10px] leading-normal text-muted-foreground"
                                >
                                    {{
                                        form.is_active
                                            ? 'Khách hàng có thể truy cập liên kết và đặt đơn món ăn bình thường.'
                                            : 'Tạm thời chặn kết nối đặt món trực tuyến từ khách hàng.'
                                    }}
                                </span>
                            </div>

                            <label
                                class="group flex w-full cursor-pointer items-center justify-between rounded-xl border border-border bg-muted/20 p-3 transition-colors hover:bg-muted/40"
                            >
                                <span class="text-xs font-bold text-foreground"
                                    >Trạng thái hoạt động</span
                                >
                                <input
                                    type="checkbox"
                                    v-model="form.is_active"
                                    class="peer sr-only"
                                />
                                <div
                                    class="peer relative h-5 w-9 rounded-full bg-slate-200 peer-checked:bg-blue-600 peer-focus:outline-none after:absolute after:top-[2px] after:left-[2px] after:h-4 after:w-4 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full dark:border-gray-600 dark:bg-slate-700"
                                ></div>
                            </label>
                        </CardContent>
                    </Card>

                    <!-- Quy tắc vận chuyển & Đơn hàng -->
                    <Card
                        class="overflow-hidden shadow-sm"
                        v-if="form.enable_delivery"
                    >
                        <div
                            class="border-b border-border bg-slate-50/50 p-5 dark:bg-slate-900/20"
                        >
                            <h3
                                class="flex items-center gap-2 text-sm font-bold text-foreground"
                            >
                                <Truck class="size-4.5 text-emerald-500" />
                                Thiết lập Giao hàng & Phí ship
                            </h3>
                        </div>
                        <CardContent class="space-y-4 p-6">
                            <div class="grid gap-1.5">
                                <Label
                                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >Phí ship cơ bản (VND)</Label
                                >
                                <div class="relative">
                                    <Input
                                        type="number"
                                        v-model="form.delivery_base_fee"
                                        class="h-10 rounded-xl pl-8 font-mono text-xs font-bold"
                                    />
                                    <DollarSign
                                        class="absolute top-3 left-2.5 size-4 text-muted-foreground/60"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >Phí cộng thêm mỗi km (VND)</Label
                                >
                                <div class="relative">
                                    <Input
                                        type="number"
                                        v-model="form.delivery_fee_per_km"
                                        class="h-10 rounded-xl pl-8 font-mono text-xs font-bold"
                                    />
                                    <DollarSign
                                        class="absolute top-3 left-2.5 size-4 text-muted-foreground/60"
                                    />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-1.5">
                                    <Label
                                        class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                        >Bán kính tối đa</Label
                                    >
                                    <div class="relative">
                                        <Input
                                            type="number"
                                            step="0.5"
                                            v-model="form.max_delivery_km"
                                            class="h-10 rounded-xl pr-8 text-right font-mono text-xs font-bold"
                                        />
                                        <span
                                            class="text-xxs absolute top-2.5 right-2.5 font-bold text-muted-foreground/60"
                                            >km</span
                                        >
                                    </div>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label
                                        class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                        >Đơn hàng tối thiểu</Label
                                    >
                                    <div class="relative">
                                        <Input
                                            type="number"
                                            v-model="form.min_order_amount"
                                            class="h-10 rounded-xl pr-10 text-right font-mono text-[10px] font-bold"
                                        />
                                        <span
                                            class="absolute top-2.5 right-2.5 text-[9px] font-bold text-muted-foreground/60"
                                            >VND</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Alert message when delivery is disabled -->
                    <div
                        v-else
                        class="border-amber-250 flex gap-2.5 rounded-2xl border bg-amber-50/40 p-4 text-xs text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-400"
                    >
                        <AlertTriangle
                            class="mt-0.5 size-4 shrink-0 text-amber-600"
                        />
                        <p>
                            Các cấu hình liên quan đến
                            <strong>Phí ship</strong> và
                            <strong>Bán kính giao hàng</strong> được ẩn đi do
                            bạn đang tắt hình thức phục vụ
                            <strong>Giao tận nơi</strong>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Submit Button Footer -->
            <div class="flex justify-end border-t border-border/80 pt-4">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    class="h-11 cursor-pointer gap-2 rounded-xl bg-blue-600 px-6 font-semibold text-white shadow-md shadow-blue-500/10 transition duration-150 hover:bg-blue-700 active:scale-95 disabled:opacity-50"
                >
                    <Loader2
                        v-if="form.processing"
                        class="size-4.5 animate-spin"
                    />
                    <Save v-else class="size-4.5" />
                    {{
                        form.processing
                            ? 'Đang lưu cấu hình...'
                            : 'Lưu cấu hình cửa hàng'
                    }}
                </Button>
            </div>
        </form>
    </div>
</template>
