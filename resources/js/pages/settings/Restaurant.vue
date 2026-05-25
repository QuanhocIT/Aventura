<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
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
};

defineProps<{
    restaurant: RestaurantData | null;
    status?: string;
}>();

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
