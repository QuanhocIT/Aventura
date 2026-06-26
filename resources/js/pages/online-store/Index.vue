<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ExternalLink, Globe, Loader2, Save } from 'lucide-vue-next';
import { watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    config: any;
    storeUrl: string | null;
}>();

const page = usePage();
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
});

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

    <div class="flex flex-col gap-6 p-6 max-w-4xl mx-auto">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <Globe class="size-6 text-blue-500" />
                <div>
                    <h1 class="text-2xl font-bold">Cửa hàng Online</h1>
                    <p class="text-sm text-muted-foreground">Cấu hình trang đặt hàng online cho khách hàng.</p>
                </div>
            </div>
            <a v-if="storeUrl" :href="storeUrl" target="_blank" class="inline-flex items-center gap-2 rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted">
                <ExternalLink class="size-4" /> Xem trang
            </a>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- General -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Thiết lập chung</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="form.is_active" class="rounded" />
                        <span class="text-sm font-medium">{{ form.is_active ? 'Cửa hàng đang mở' : 'Cửa hàng đang đóng' }}</span>
                    </label>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label>Đường dẫn (slug)</Label>
                            <div class="flex items-center gap-1">
                                <span class="text-xs text-muted-foreground">/order/</span>
                                <Input v-model="form.slug" placeholder="ten-nha-hang" />
                            </div>
                            <p v-if="form.errors.slug" class="text-xs text-red-500">{{ form.errors.slug }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Banner URL</Label>
                            <Input v-model="form.banner_url" placeholder="https://..." />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label>Mô tả nhà hàng</Label>
                        <Input v-model="form.description" placeholder="Mô tả ngắn về nhà hàng..." />
                    </div>

                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.enable_takeaway" class="rounded" />
                            <span class="text-sm">Mang về</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.enable_delivery" class="rounded" />
                            <span class="text-sm">Giao hàng</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.enable_preorder" class="rounded" />
                            <span class="text-sm">Đặt trước</span>
                        </label>
                    </div>
                </CardContent>
            </Card>

            <!-- Delivery -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Giao hàng</CardTitle>
                    <CardDescription>Cấu hình phí ship và phạm vi giao hàng.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="grid gap-1.5">
                            <Label>Phí cơ bản (VND)</Label>
                            <Input type="number" v-model="form.delivery_base_fee" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Phí / km (VND)</Label>
                            <Input type="number" v-model="form.delivery_fee_per_km" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Bán kính tối đa (km)</Label>
                            <Input type="number" step="0.5" v-model="form.max_delivery_km" />
                        </div>
                    </div>
                    <div class="grid gap-1.5 mt-4">
                        <Label>Đơn hàng tối thiểu (VND)</Label>
                        <Input type="number" v-model="form.min_order_amount" />
                    </div>
                </CardContent>
            </Card>

            <!-- Submit -->
            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing" class="gap-2 px-6">
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    {{ form.processing ? 'Đang lưu...' : 'Lưu cấu hình' }}
                </Button>
            </div>
        </form>
    </div>
</template>
