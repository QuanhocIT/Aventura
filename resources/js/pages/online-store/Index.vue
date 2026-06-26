<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ExternalLink, Globe, Loader2, Save } from 'lucide-vue-next';
import { watch } from 'vue';
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
                    <p class="text-sm text-muted-foreground">Cấu hình trang đặt hàng online cho khách hàng.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a v-if="storeUrl" :href="storeUrl" target="_blank"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border bg-background px-3.5 py-2 text-xs font-semibold transition hover:bg-muted active:scale-95 text-foreground"
                >
                    <ExternalLink class="size-3.5" />
                    Xem trang
                </a>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- General Settings -->
            <Card>
                <CardContent class="pt-5 space-y-4">
                    <p class="font-semibold text-sm mb-4">Thiết lập chung</p>
                    
                    <label class="flex items-center gap-2.5 cursor-pointer p-2.5 rounded-lg border border-border bg-muted/20 hover:bg-muted/40 transition-colors w-fit">
                        <input type="checkbox" v-model="form.is_active" class="rounded border-border accent-blue-600" />
                        <span class="text-sm font-semibold text-foreground">{{ form.is_active ? 'Cửa hàng đang mở' : 'Cửa hàng đang đóng' }}</span>
                    </label>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-border pt-4">
                        <div class="grid gap-1.5">
                            <Label>Đường dẫn (slug)</Label>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground bg-muted px-2.5 py-2.5 rounded-lg border border-border">/order/</span>
                                <Input v-model="form.slug" placeholder="ten-nha-hang" class="rounded-lg" />
                            </div>
                            <p v-if="form.errors.slug" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.slug }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Banner URL</Label>
                            <Input v-model="form.banner_url" placeholder="https://..." class="rounded-lg" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Mô tả nhà hàng</Label>
                        <Input v-model="form.description" placeholder="Mô tả ngắn về nhà hàng..." class="rounded-lg" />
                    </div>

                    <div class="flex flex-wrap gap-4 border-t border-border pt-4">
                        <label class="flex items-center gap-2 cursor-pointer p-1.5 rounded hover:bg-muted/30">
                            <input type="checkbox" v-model="form.enable_takeaway" class="rounded border-border" />
                            <span class="text-sm font-medium text-foreground">Mang về</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer p-1.5 rounded hover:bg-muted/30">
                            <input type="checkbox" v-model="form.enable_delivery" class="rounded border-border" />
                            <span class="text-sm font-medium text-foreground">Giao hàng</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer p-1.5 rounded hover:bg-muted/30">
                            <input type="checkbox" v-model="form.enable_preorder" class="rounded border-border" />
                            <span class="text-sm font-medium text-foreground">Đặt trước</span>
                        </label>
                    </div>
                </CardContent>
            </Card>

            <!-- Delivery Settings -->
            <Card>
                <CardContent class="pt-5 space-y-4">
                    <div class="pb-1">
                        <p class="font-semibold text-sm">Giao hàng</p>
                        <p class="text-xs text-muted-foreground mt-0.5">Cấu hình phí ship và phạm vi giao hàng.</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-t border-border pt-4">
                        <div class="grid gap-1.5">
                            <Label>Phí cơ bản (VND)</Label>
                            <Input type="number" v-model="form.delivery_base_fee" class="rounded-lg" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Phí / km (VND)</Label>
                            <Input type="number" v-model="form.delivery_fee_per_km" class="rounded-lg" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Bán kính tối đa (km)</Label>
                            <Input type="number" step="0.5" v-model="form.max_delivery_km" class="rounded-lg" />
                        </div>
                    </div>
                    <div class="grid gap-1.5 border-t border-border pt-4">
                        <Label>Đơn hàng tối thiểu (VND)</Label>
                        <Input type="number" v-model="form.min_order_amount" class="rounded-lg" />
                    </div>
                </CardContent>
            </Card>

            <!-- Submit Button -->
            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing"
                    class="gap-2 px-6 cursor-pointer rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-sm transition active:scale-95 disabled:opacity-50"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    {{ form.processing ? 'Đang lưu...' : 'Lưu cấu hình' }}
                </Button>
            </div>
        </form>
    </div>
</template>
