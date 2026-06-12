<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Building2, ImagePlus, X } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type RestaurantData = {
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    tax_code: string | null;
    timezone: string;
    currency: string;
    logo_url: string | null;
    qr_bank_id: string | null;
    qr_account_number: string | null;
    qr_account_name: string | null;
    qr_enabled: boolean;
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

    <div class="space-y-6 w-full">
        <Card class="w-full border border-neutral-200/60 dark:border-neutral-800/60 shadow-xs rounded-2xl overflow-hidden bg-white/70 dark:bg-neutral-900/40 backdrop-blur-md">
            <CardHeader class="flex flex-row items-center gap-4 border-b border-neutral-100 dark:border-neutral-800 pb-5 px-6 pt-6">
                <div class="p-2.5 bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 rounded-xl shrink-0">
                    <Building2 class="w-5 h-5" />
                </div>
                <div class="space-y-0.5">
                    <CardTitle class="text-lg font-black text-neutral-900 dark:text-neutral-50">Thông tin nhà hàng</CardTitle>
                    <CardDescription class="text-xs text-neutral-500 dark:text-neutral-400">Cập nhật tên, địa chỉ, liên hệ và thông tin thuế của nhà hàng</CardDescription>
                </div>
            </CardHeader>
            <CardContent class="p-6">
                <div v-if="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs font-semibold text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400">
                    {{ status }}
                </div>

                <div v-if="!restaurant" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3.5 text-xs font-semibold text-amber-700 dark:border-amber-800 dark:bg-amber-950/20 dark:text-amber-400">
                    Không tìm thấy thông tin nhà hàng. Vui lòng liên hệ hỗ trợ.
                </div>

                <Form
                    v-else
                    method="patch"
                    action="/settings/restaurant"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <!-- Logo nhà hàng -->
                    <div class="grid gap-2">
                        <Label class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Logo nhà hàng</Label>
                        <div class="flex items-center gap-4">
                            <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-border bg-neutral-100 dark:bg-neutral-800">
                                <img
                                    v-if="logoPreview ?? restaurant.logo_url"
                                    :src="logoPreview ?? restaurant.logo_url ?? ''"
                                    alt="Logo nhà hàng"
                                    class="size-full object-cover animate-in fade-in duration-200"
                                />
                                <ImagePlus v-else class="size-6 text-neutral-400" />
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
                                    <Button 
                                        type="button" 
                                        variant="outline" 
                                        size="sm" 
                                        class="rounded-xl font-bold text-xs cursor-pointer gap-1.5 h-9"
                                        @click="logoInput?.click()"
                                    >
                                        <ImagePlus class="size-4" /> Chọn ảnh
                                    </Button>
                                    <Button 
                                        v-if="logoPreview" 
                                        type="button" 
                                        variant="ghost" 
                                        size="sm" 
                                        class="rounded-xl font-bold text-xs cursor-pointer gap-1.5 h-9 text-rose-500 hover:text-rose-600 hover:bg-rose-500/10"
                                        @click="clearLogoSelection"
                                    >
                                        <X class="size-4" /> Hủy chọn
                                    </Button>
                                </div>
                                <p class="text-[10px] text-neutral-500 dark:text-neutral-400">PNG, JPG hoặc WEBP, tối đa 2MB.</p>
                                <InputError :message="errors.logo" />
                            </div>
                        </div>
                    </div>

                    <!-- Tên nhà hàng -->
                    <div class="grid gap-2">
                        <Label for="name" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Tên nhà hàng <span class="text-rose-500">*</span></Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="restaurant.name"
                            required
                            placeholder="Ví dụ: Phở Việt, Quán Cơm Nhà..."
                            class="w-full block rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <!-- Phone + Email -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="phone" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Số điện thoại</Label>
                            <Input
                                id="phone"
                                name="phone"
                                type="tel"
                                :default-value="restaurant.phone ?? ''"
                                placeholder="0900 000 000"
                                class="w-full block rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                            />
                            <InputError :message="errors.phone" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="email" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Email nhà hàng</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                :default-value="restaurant.email ?? ''"
                                placeholder="info@nhahang.vn"
                                class="w-full block rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                            />
                            <InputError :message="errors.email" />
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="grid gap-2">
                        <Label for="address" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Địa chỉ</Label>
                        <Input
                            id="address"
                            name="address"
                            :default-value="restaurant.address ?? ''"
                            placeholder="Số nhà, đường, phường, quận, thành phố..."
                            class="w-full block rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                        />
                        <InputError :message="errors.address" />
                    </div>

                    <!-- Tax code -->
                    <div class="grid gap-2">
                        <Label for="tax_code" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Mã số thuế (MST)</Label>
                        <Input
                            id="tax_code"
                            name="tax_code"
                            :default-value="restaurant.tax_code ?? ''"
                            placeholder="0123456789"
                            class="w-full block font-mono rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                        />
                        <InputError :message="errors.tax_code" />
                        <p class="text-[10px] text-neutral-500 dark:text-neutral-400">Dùng để xuất hóa đơn GTGT. Bỏ trống nếu không có.</p>
                    </div>

                    <!-- Cấu hình Ngân hàng thụ hưởng nhận chuyển khoản QR -->
                    <div class="border-t border-neutral-100 dark:border-neutral-800 pt-6 space-y-4 text-left">
                        <h3 class="text-xs font-bold text-neutral-800 dark:text-neutral-200 uppercase tracking-wider">Cấu hình Thanh toán QR</h3>
                        
                        <div class="flex items-center space-x-2">
                            <input 
                                id="qr_enabled" 
                                name="qr_enabled" 
                                type="checkbox"
                                value="1"
                                :checked="restaurant.qr_enabled" 
                                class="rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900 size-4 cursor-pointer"
                            />
                            <Label for="qr_enabled" class="text-xs font-bold text-neutral-700 cursor-pointer">Kích hoạt thanh toán VietQR động cho khách hàng tại bàn</Label>
                        </div>
                        <InputError :message="errors.qr_enabled" />

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="grid gap-2">
                                <Label for="qr_bank_id" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Ngân hàng</Label>
                                <select 
                                    id="qr_bank_id" 
                                    name="qr_bank_id" 
                                    class="w-full h-9 rounded-xl border border-neutral-200 bg-white text-xs px-3 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800 dark:bg-neutral-950 text-neutral-800 dark:text-neutral-200"
                                >
                                    <option value="">-- Chọn ngân hàng --</option>
                                    <option value="mbbank" :selected="restaurant.qr_bank_id === 'mbbank'">MB Bank</option>
                                    <option value="vietcombank" :selected="restaurant.qr_bank_id === 'vietcombank'">Vietcombank</option>
                                    <option value="techcombank" :selected="restaurant.qr_bank_id === 'techcombank'">Techcombank</option>
                                    <option value="bidv" :selected="restaurant.qr_bank_id === 'bidv'">BIDV</option>
                                    <option value="vietinbank" :selected="restaurant.qr_bank_id === 'vietinbank'">Vietinbank</option>
                                    <option value="acb" :selected="restaurant.qr_bank_id === 'acb'">ACB</option>
                                    <option value="sacombank" :selected="restaurant.qr_bank_id === 'sacombank'">Sacombank</option>
                                </select>
                                <InputError :message="errors.qr_bank_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="qr_account_number" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Số tài khoản</Label>
                                <Input
                                    id="qr_account_number"
                                    name="qr_account_number"
                                    :default-value="restaurant.qr_account_number ?? ''"
                                    placeholder="Ví dụ: 0123456789"
                                    class="w-full block font-mono rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                                />
                                <InputError :message="errors.qr_account_number" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="qr_account_name" class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Tên chủ tài khoản</Label>
                                <Input
                                    id="qr_account_name"
                                    name="qr_account_name"
                                    :default-value="restaurant.qr_account_name ?? ''"
                                    placeholder="VIET COMBANK"
                                    class="w-full block rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                                />
                                <InputError :message="errors.qr_account_name" />
                            </div>
                        </div>
                    </div>

                    <!-- Read-only info -->
                    <div class="rounded-2xl border border-neutral-200 bg-neutral-50/50 dark:border-neutral-800 dark:bg-neutral-900/30 px-5 py-4 grid grid-cols-2 gap-4 text-xs font-semibold">
                        <div>
                            <p class="text-[10px] text-neutral-500 uppercase tracking-wider font-bold">Múi giờ</p>
                            <p class="text-neutral-800 dark:text-neutral-200 mt-1 font-mono font-bold">{{ restaurant.timezone }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-neutral-500 uppercase tracking-wider font-bold">Đơn vị tiền tệ</p>
                            <p class="text-neutral-800 dark:text-neutral-200 mt-1 font-mono font-bold">{{ restaurant.currency }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <Button 
                            type="submit" 
                            :disabled="processing"
                            class="bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-neutral-50 dark:text-neutral-950 dark:hover:bg-neutral-200 px-6 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider transition-all duration-200 shadow-sm active:scale-95 cursor-pointer disabled:opacity-50"
                        >
                            {{ processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
