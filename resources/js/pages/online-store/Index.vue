<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { 
    ExternalLink, Globe, Loader2, Save, Copy, Check, 
    Image as ImageIcon, ShoppingBag, Truck, Calendar, 
    DollarSign, MapPin, Store, FileText, AlertTriangle 
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
    }
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
    if (!props.storeUrl) return;
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

    <div class="flex flex-col gap-5 p-4 lg:p-6 max-w-7xl mx-auto w-full">
        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-border pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                    <Globe class="size-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Cửa hàng Online</h1>
                    <p class="text-sm text-muted-foreground">Cấu hình trang đặt hàng online cho khách hàng của bạn.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button
                    v-if="storeUrl"
                    type="button"
                    @click="copyStoreUrl"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border bg-background px-3.5 py-2 text-xs font-semibold transition hover:bg-muted active:scale-95 text-foreground"
                >
                    <component :is="isCopied ? Check : Copy" class="size-3.5" :class="isCopied ? 'text-emerald-500' : ''" />
                    {{ isCopied ? 'Đã chép' : 'Sao chép link' }}
                </button>
                <a v-if="storeUrl" :href="storeUrl" target="_blank"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border bg-background px-3.5 py-2 text-xs font-semibold transition hover:bg-muted active:scale-95 text-foreground"
                >
                    <ExternalLink class="size-3.5" />
                    Xem trang
                </a>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- ── Left Column: Brand & Service configuration (2/3 width) ── -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Nhận diện cửa hàng -->
                    <Card class="shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-border bg-slate-50/50 dark:bg-slate-900/20">
                            <h3 class="font-bold text-sm text-foreground flex items-center gap-2">
                                <Store class="size-4.5 text-blue-500" />
                                Thiết lập Nhận diện & Thương hiệu
                            </h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Cấu hình đường dẫn kết nối và hình ảnh đại diện thương hiệu.</p>
                        </div>
                        <CardContent class="p-6 space-y-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-1.5">
                                    <Label class="font-bold text-xs flex items-center gap-1.5 text-muted-foreground uppercase tracking-wider">Đường dẫn cửa hàng (Slug)</Label>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-muted-foreground bg-muted px-3 py-2.5 rounded-xl border border-border">/order/</span>
                                        <Input v-model="form.slug" placeholder="ten-nha-hang" class="rounded-xl h-10 font-bold" />
                                    </div>
                                    <p v-if="form.errors.slug" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.slug }}</p>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label class="font-bold text-xs flex items-center gap-1.5 text-muted-foreground uppercase tracking-wider">Đường dẫn Banner URL</Label>
                                    <div class="relative">
                                        <Input v-model="form.banner_url" placeholder="https://images.unsplash.com/..." class="rounded-xl h-10 pl-8 text-xs" />
                                        <ImageIcon class="absolute left-2.5 top-3 size-4 text-muted-foreground/60" />
                                    </div>
                                </div>
                            </div>

                            <!-- Live Banner Preview Card -->
                            <div class="grid gap-1.5 pt-2">
                                <Label class="font-bold text-xs text-muted-foreground uppercase tracking-wider">Xem trước ảnh bìa (Live Preview)</Label>
                                <div class="relative w-full h-36 rounded-xl overflow-hidden border border-border bg-slate-50 dark:bg-slate-900 flex items-center justify-center group">
                                    <img 
                                        v-if="form.banner_url" 
                                        :src="form.banner_url" 
                                        alt="Banner Preview" 
                                        class="w-full h-full object-cover transition duration-300 group-hover:scale-102"
                                    />
                                    <div v-else class="flex flex-col items-center gap-2 text-muted-foreground/50">
                                        <ImageIcon class="size-8 stroke-1" />
                                        <span class="text-xs font-medium">Chưa có ảnh bìa thiết lập</span>
                                    </div>
                                    <!-- Visual Overlay if image exists -->
                                    <div v-if="form.banner_url" class="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-transparent to-transparent flex items-end p-4">
                                        <div class="text-white">
                                            <p class="text-sm font-extrabold">{{ form.slug ? '/' + form.slug : 'Aventura Online Store' }}</p>
                                            <p class="text-[10px] text-slate-300 mt-0.5">Đặt hàng trực tuyến mượt mà</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-1.5">
                                <Label class="font-bold text-xs flex items-center gap-1.5 text-muted-foreground uppercase tracking-wider">Mô tả ngắn nhà hàng</Label>
                                <div class="relative">
                                    <Input v-model="form.description" placeholder="Chào mừng quý khách đến với cửa hàng đặt món trực tuyến của chúng tôi..." class="rounded-xl h-10 pl-8 text-xs" />
                                    <FileText class="absolute left-2.5 top-3 size-4 text-muted-foreground/60" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Fulfillment Options (Các hình thức phục vụ) -->
                    <Card class="shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-border bg-slate-50/50 dark:bg-slate-900/20">
                            <h3 class="font-bold text-sm text-foreground flex items-center gap-2">
                                <ShoppingBag class="size-4.5 text-indigo-500" />
                                Hình thức phục vụ khách hàng
                            </h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Lựa chọn các hình thức cung cấp dịch vụ trên trang đặt hàng.</p>
                        </div>
                        <CardContent class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                
                                <!-- Option 1: Takeaway (Mang về) -->
                                <div 
                                    @click="form.enable_takeaway = !form.enable_takeaway"
                                    :class="[
                                        'relative flex flex-col p-4 rounded-2xl border cursor-pointer select-none transition-all duration-200 gap-3 group',
                                        form.enable_takeaway 
                                            ? 'border-indigo-500 bg-indigo-500/[0.03] dark:bg-indigo-950/10' 
                                            : 'border-border bg-card hover:bg-muted/30'
                                    ]"
                                >
                                    <div class="flex items-center justify-between">
                                        <div :class="['p-2 rounded-xl border transition-colors', form.enable_takeaway ? 'bg-indigo-500/10 text-indigo-600 border-indigo-200 dark:text-indigo-400 dark:border-indigo-900/50' : 'bg-muted text-muted-foreground border-border']">
                                            <ShoppingBag class="size-4.5" />
                                        </div>
                                        <div v-if="form.enable_takeaway" class="h-4.5 w-4.5 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[9px] font-bold"><Check class="size-3 stroke-3" /></div>
                                    </div>
                                    <div>
                                        <p class="text-xs font-black text-foreground">Tự đến lấy (Takeaway)</p>
                                        <p class="text-[10px] text-muted-foreground mt-0.5">Cho phép khách đặt món và tự qua cửa hàng lấy đồ.</p>
                                    </div>
                                </div>

                                <!-- Option 2: Delivery (Giao hàng) -->
                                <div 
                                    @click="form.enable_delivery = !form.enable_delivery"
                                    :class="[
                                        'relative flex flex-col p-4 rounded-2xl border cursor-pointer select-none transition-all duration-200 gap-3 group',
                                        form.enable_delivery 
                                            ? 'border-emerald-500 bg-emerald-500/[0.03] dark:bg-emerald-950/10' 
                                            : 'border-border bg-card hover:bg-muted/30'
                                    ]"
                                >
                                    <div class="flex items-center justify-between">
                                        <div :class="['p-2 rounded-xl border transition-colors', form.enable_delivery ? 'bg-emerald-500/10 text-emerald-600 border-emerald-200 dark:text-emerald-400 dark:border-emerald-900/50' : 'bg-muted text-muted-foreground border-border']">
                                            <Truck class="size-4.5" />
                                        </div>
                                        <div v-if="form.enable_delivery" class="h-4.5 w-4.5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[9px] font-bold"><Check class="size-3 stroke-3" /></div>
                                    </div>
                                    <div>
                                        <p class="text-xs font-black text-foreground">Giao tận nơi (Delivery)</p>
                                        <p class="text-[10px] text-muted-foreground mt-0.5">Tích hợp giao hàng tận nhà theo phạm vi thiết lập phí ship.</p>
                                    </div>
                                </div>

                                <!-- Option 3: Preorder (Đặt trước) -->
                                <div 
                                    @click="form.enable_preorder = !form.enable_preorder"
                                    :class="[
                                        'relative flex flex-col p-4 rounded-2xl border cursor-pointer select-none transition-all duration-200 gap-3 group',
                                        form.enable_preorder 
                                            ? 'border-amber-500 bg-amber-500/[0.03] dark:bg-amber-950/10' 
                                            : 'border-border bg-card hover:bg-muted/30'
                                    ]"
                                >
                                    <div class="flex items-center justify-between">
                                        <div :class="['p-2 rounded-xl border transition-colors', form.enable_preorder ? 'bg-amber-500/10 text-amber-600 border-amber-200 dark:text-amber-400 dark:border-amber-900/50' : 'bg-muted text-muted-foreground border-border']">
                                            <Calendar class="size-4.5" />
                                        </div>
                                        <div v-if="form.enable_preorder" class="h-4.5 w-4.5 rounded-full bg-amber-500 text-white flex items-center justify-center text-[9px] font-bold"><Check class="size-3 stroke-3" /></div>
                                    </div>
                                    <div>
                                        <p class="text-xs font-black text-foreground">Lên lịch trước (Preorder)</p>
                                        <p class="text-[10px] text-muted-foreground mt-0.5">Khách hàng lên lịch hẹn ngày/giờ nhận món trong tương lai.</p>
                                    </div>
                                </div>

                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- ── Right Column: Status Toggle & Shipping Rules (1/3 width) ── -->
                <div class="lg:col-span-1 space-y-6">
                    
                    <!-- Hoạt động Switch -->
                    <Card class="shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-border bg-slate-50/50 dark:bg-slate-900/20">
                            <p class="font-bold text-sm text-foreground">Trạng thái Cửa hàng</p>
                            <p class="text-xs text-muted-foreground mt-0.5">Đóng/Mở kết nối trực tuyến nhanh.</p>
                        </div>
                        <CardContent class="p-6 flex flex-col gap-4">
                            <!-- Glowing status block -->
                            <div 
                                class="p-4 rounded-2xl border transition-all duration-300 text-center flex flex-col items-center gap-1"
                                :class="[
                                    form.is_active
                                        ? 'bg-emerald-500/10 border-emerald-200 dark:border-emerald-900/40 text-emerald-800 dark:text-emerald-400 shadow-sm shadow-emerald-500/5'
                                        : 'bg-rose-500/10 border-rose-200 dark:border-rose-900/40 text-rose-800 dark:text-rose-400 shadow-sm shadow-rose-500/5'
                                ]"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="size-2.5 rounded-full" :class="form.is_active ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'" />
                                    <span class="text-xs font-black uppercase tracking-wider">{{ form.is_active ? 'Cửa hàng Đang mở' : 'Cửa hàng Đang đóng' }}</span>
                                </div>
                                <span class="text-[10px] text-muted-foreground mt-1 leading-normal">
                                    {{ form.is_active ? 'Khách hàng có thể truy cập liên kết và đặt đơn món ăn bình thường.' : 'Tạm thời chặn kết nối đặt món trực tuyến từ khách hàng.' }}
                                </span>
                            </div>

                            <label class="flex items-center justify-between cursor-pointer p-3 rounded-xl border border-border bg-muted/20 hover:bg-muted/40 transition-colors w-full group">
                                <span class="text-xs font-bold text-foreground">Trạng thái hoạt động</span>
                                <input type="checkbox" v-model="form.is_active" class="sr-only peer" />
                                <div class="relative w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                            </label>
                        </CardContent>
                    </Card>

                    <!-- Quy tắc vận chuyển & Đơn hàng -->
                    <Card class="shadow-sm overflow-hidden" v-if="form.enable_delivery">
                        <div class="p-5 border-b border-border bg-slate-50/50 dark:bg-slate-900/20">
                            <h3 class="font-bold text-sm text-foreground flex items-center gap-2">
                                <Truck class="size-4.5 text-emerald-500" />
                                Thiết lập Giao hàng & Phí ship
                            </h3>
                        </div>
                        <CardContent class="p-6 space-y-4">
                            <div class="grid gap-1.5">
                                <Label class="font-bold text-[10px] flex items-center gap-1.5 text-muted-foreground uppercase tracking-wider">Phí ship cơ bản (VND)</Label>
                                <div class="relative">
                                    <Input type="number" v-model="form.delivery_base_fee" class="rounded-xl h-10 pl-8 font-mono text-xs font-bold" />
                                    <DollarSign class="absolute left-2.5 top-3 size-4 text-muted-foreground/60" />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label class="font-bold text-[10px] flex items-center gap-1.5 text-muted-foreground uppercase tracking-wider">Phí cộng thêm mỗi km (VND)</Label>
                                <div class="relative">
                                    <Input type="number" v-model="form.delivery_fee_per_km" class="rounded-xl h-10 pl-8 font-mono text-xs font-bold" />
                                    <DollarSign class="absolute left-2.5 top-3 size-4 text-muted-foreground/60" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="grid gap-1.5">
                                    <Label class="font-bold text-[10px] flex items-center gap-1.5 text-muted-foreground uppercase tracking-wider">Bán kính tối đa</Label>
                                    <div class="relative">
                                        <Input type="number" step="0.5" v-model="form.max_delivery_km" class="rounded-xl h-10 pr-8 font-mono text-xs font-bold text-right" />
                                        <span class="absolute right-2.5 top-2.5 text-xxs font-bold text-muted-foreground/60">km</span>
                                    </div>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label class="font-bold text-[10px] flex items-center gap-1.5 text-muted-foreground uppercase tracking-wider">Đơn hàng tối thiểu</Label>
                                    <div class="relative">
                                        <Input type="number" v-model="form.min_order_amount" class="rounded-xl h-10 pr-10 font-mono text-[10px] font-bold text-right" />
                                        <span class="absolute right-2.5 top-2.5 text-[9px] font-bold text-muted-foreground/60">VND</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Alert message when delivery is disabled -->
                    <div 
                        v-else 
                        class="p-4 rounded-2xl border border-amber-250 bg-amber-50/40 text-amber-700 text-xs flex gap-2.5 dark:bg-amber-950/20 dark:border-amber-900/40 dark:text-amber-400"
                    >
                        <AlertTriangle class="size-4 shrink-0 mt-0.5 text-amber-600" />
                        <p>Các cấu hình liên quan đến <strong>Phí ship</strong> và <strong>Bán kính giao hàng</strong> được ẩn đi do bạn đang tắt hình thức phục vụ <strong>Giao tận nơi</strong>.</p>
                    </div>
                </div>
            </div>

            <!-- Submit Button Footer -->
            <div class="flex justify-end pt-4 border-t border-border/80">
                <Button 
                    type="submit" 
                    :disabled="form.processing"
                    class="gap-2 px-6 h-11 cursor-pointer rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-md shadow-blue-500/10 transition duration-150 active:scale-95 disabled:opacity-50"
                >
                    <Loader2 v-if="form.processing" class="size-4.5 animate-spin" />
                    <Save v-else class="size-4.5" />
                    {{ form.processing ? 'Đang lưu cấu hình...' : 'Lưu cấu hình cửa hàng' }}
                </Button>
            </div>
        </form>
    </div>
</template>
