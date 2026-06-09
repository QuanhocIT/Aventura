<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ImagePlus, X } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
// @ts-ignore
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type RestaurantData = {
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    tax_code: string | null;
    timezone: string;
    currency: string;
    logo_url: string | null;
};

defineProps<{
    restaurant: RestaurantData | null;
    status?: string;
}>();

const logoInput = ref<HTMLInputElement | null>(null);
const logoPreview = ref<string | null>(null);

function onLogoChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
    logoPreview.value = URL.createObjectURL(file);
}

function clearLogoSelection() {
    if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
    logoPreview.value = null;
    if (logoInput.value) logoInput.value.value = '';
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Cài đặt nhà hàng', href: '/settings/restaurant' },
        ],
    },
});
</script>

<template>
    <Head title="Cài đặt nhà hàng" />

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Thông tin nhà hàng"
            description="Cập nhật tên, địa chỉ, liên hệ và thông tin thuế của nhà hàng"
        />

        <div v-if="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400">
            {{ status }}
        </div>

        <div v-if="!restaurant" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-950/20 dark:text-amber-400">
            Không tìm thấy thông tin nhà hàng. Vui lòng liên hệ hỗ trợ.
        </div>

        <Form
            v-else
            method="patch"
            action="/settings/restaurant"
            v-slot="{ errors, processing }"
            class="space-y-5"
        >
            <!-- Logo nhà hàng -->
            <div class="grid gap-1.5">
                <Label>Logo nhà hàng</Label>
                <div class="flex items-center gap-4">
                    <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-muted/30">
                        <img
                            v-if="logoPreview ?? restaurant.logo_url"
                            :src="logoPreview ?? restaurant.logo_url ?? ''"
                            alt="Logo nhà hàng"
                            class="size-full object-cover"
                        />
                        <ImagePlus v-else class="size-6 text-muted-foreground" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <input
                            ref="logoInput"
                            type="file"
                            name="logo"
                            accept="image/jpg,image/jpeg,image/png,image/webp"
                            class="hidden"
                            @change="onLogoChange"
                        />
                        <div class="flex items-center gap-2">
                            <Button type="button" variant="outline" size="sm" @click="logoInput?.click()">
                                <ImagePlus class="size-4" /> Chọn ảnh
                            </Button>
                            <Button v-if="logoPreview" type="button" variant="ghost" size="sm" @click="clearLogoSelection">
                                <X class="size-4" /> Hủy chọn
                            </Button>
                        </div>
                        <p class="text-xs text-muted-foreground">PNG, JPG hoặc WEBP, tối đa 2MB.</p>
                        <InputError :message="errors.logo" />
                    </div>
                </div>
            </div>

            <!-- Tên nhà hàng -->
            <div class="grid gap-1.5">
                <Label for="name">Tên nhà hàng <span class="text-rose-500">*</span></Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="restaurant.name"
                    required
                    placeholder="Ví dụ: Phở Việt, Quán Cơm Nhà..."
                />
                <InputError :message="errors.name" />
            </div>

            <!-- Phone + Email -->
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-1.5">
                    <Label for="phone">Số điện thoại</Label>
                    <Input
                        id="phone"
                        name="phone"
                        type="tel"
                        :default-value="restaurant.phone ?? ''"
                        placeholder="0900 000 000"
                    />
                    <InputError :message="errors.phone" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="email">Email nhà hàng</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        :default-value="restaurant.email ?? ''"
                        placeholder="info@nhahang.vn"
                    />
                    <InputError :message="errors.email" />
                </div>
            </div>

            <!-- Address -->
            <div class="grid gap-1.5">
                <Label for="address">Địa chỉ</Label>
                <Input
                    id="address"
                    name="address"
                    :default-value="restaurant.address ?? ''"
                    placeholder="Số nhà, đường, phường, quận, thành phố..."
                />
                <InputError :message="errors.address" />
            </div>

            <!-- Tax code -->
            <div class="grid gap-1.5">
                <Label for="tax_code">Mã số thuế (MST)</Label>
                <Input
                    id="tax_code"
                    name="tax_code"
                    :default-value="restaurant.tax_code ?? ''"
                    placeholder="0123456789"
                    class="font-mono"
                />
                <InputError :message="errors.tax_code" />
                <p class="text-xs text-muted-foreground">Dùng để xuất hóa đơn GTGT. Bỏ trống nếu không có.</p>
            </div>

            <!-- Read-only info -->
            <div class="rounded-xl border border-border bg-muted/30 px-4 py-3 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <p class="text-xs text-muted-foreground">Múi giờ</p>
                    <p class="font-medium mt-0.5">{{ restaurant.timezone }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Đơn vị tiền tệ</p>
                    <p class="font-medium mt-0.5">{{ restaurant.currency }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    {{ processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                </Button>
            </div>
        </Form>
    </div>
</template>
