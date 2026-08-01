<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Building2, ImagePlus, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

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
    reservation_deposit_amount: number;
    latitude: number | null;
    longitude: number | null;
};

const props = defineProps<{
    restaurant: RestaurantData | null;
    status?: string;
}>();

const logoInput = ref<HTMLInputElement | null>(null);
const logoPreview = ref<string | null>(null);

const latitude = ref<number | null>(props.restaurant?.latitude ?? null);
const longitude = ref<number | null>(props.restaurant?.longitude ?? null);
const addressText = ref<string>(props.restaurant?.address ?? '');

const isSearchingLocation = ref(false);
const searchLocationError = ref<string | null>(null);

const mapContainer = ref<HTMLElement | null>(null);
let map: any = null;
let marker: any = null;
let L: any = null;

async function searchAddressLocation() {
    if (!addressText.value) {
        searchLocationError.value = 'Vui lòng nhập địa chỉ trước khi tìm kiếm.';

        return;
    }

    isSearchingLocation.value = true;
    searchLocationError.value = null;

    try {
        const query = encodeURIComponent(addressText.value);
        const response = await fetch(
            `https://nominatim.openstreetmap.org/search?format=json&q=${query}&limit=1`,
            {
                headers: {
                    'Accept-Language': 'vi,en',
                },
            },
        );
        const results = await response.json();

        if (results && results.length > 0) {
            const result = results[0];
            const lat = parseFloat(result.lat);
            const lng = parseFloat(result.lon);

            latitude.value = Number(lat.toFixed(6));
            longitude.value = Number(lng.toFixed(6));

            if (map && L) {
                const latLng = L.latLng(lat, lng);
                map.setView(latLng, 16);

                if (marker) {
                    marker.setLatLng(latLng);
                } else {
                    marker = L.marker(latLng, { draggable: true }).addTo(map);
                    setupMarkerEvents();
                }
            }
        } else {
            searchLocationError.value =
                'Không tìm thấy tọa độ cho địa chỉ này. Bạn có thể kéo thả ghim trên bản đồ để tự chọn.';
        }
    } catch (err) {
        console.error('Lỗi tìm kiếm địa chỉ:', err);
        searchLocationError.value =
            'Đã xảy ra lỗi khi kết nối với máy chủ tìm kiếm địa điểm.';
    } finally {
        isSearchingLocation.value = false;
    }
}

function setupMarkerEvents() {
    if (!marker) {
        return;
    }

    marker.on('dragend', () => {
        const position = marker.getLatLng();
        latitude.value = Number(position.lat.toFixed(6));
        longitude.value = Number(position.lng.toFixed(6));
    });
}

onMounted(async () => {
    if (!mapContainer.value) {
        return;
    }

    try {
        L = (await import('leaflet')).default;
        await import('leaflet/dist/leaflet.css');

        // Fix default Leaflet icon paths
        const DefaultIcon = L.icon({
            iconUrl:
                'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
            shadowUrl:
                'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41],
        });
        L.Marker.prototype.options.icon = DefaultIcon;

        // Use current coordinates, or default to Ho Chi Minh City if null/0.0
        const initialLat = latitude.value || 10.776889;
        const initialLng = longitude.value || 106.700806;

        map = L.map(mapContainer.value, {
            zoomControl: true,
            attributionControl: false,
        }).setView([initialLat, initialLng], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap',
        }).addTo(map);

        L.control.attribution({ prefix: false }).addTo(map);

        // Add Marker
        marker = L.marker([initialLat, initialLng], {
            draggable: true,
        }).addTo(map);

        setupMarkerEvents();

        // Listen for map clicks to move marker
        map.on('click', (e: any) => {
            const latLng = e.latlng;
            marker.setLatLng(latLng);
            latitude.value = Number(latLng.lat.toFixed(6));
            longitude.value = Number(latLng.lng.toFixed(6));
        });
    } catch (err) {
        console.error('Lỗi khởi tạo bản đồ Leaflet:', err);
    }
});

function onLogoChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value);
    }

    logoPreview.value = URL.createObjectURL(file);
}

function clearLogoSelection() {
    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value);
    }

    logoPreview.value = null;

    if (logoInput.value) {
        logoInput.value.value = '';
    }
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

    <div class="w-full space-y-6">
        <Card
            class="w-full overflow-hidden rounded-2xl border border-neutral-200/60 bg-white/70 shadow-xs backdrop-blur-md dark:border-neutral-800/60 dark:bg-neutral-900/40"
        >
            <CardHeader
                class="flex flex-row items-center gap-4 border-b border-neutral-100 px-6 pt-6 pb-5 dark:border-neutral-800"
            >
                <div
                    class="shrink-0 rounded-xl bg-neutral-100 p-2.5 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
                >
                    <Building2 class="h-5 w-5" />
                </div>
                <div class="space-y-0.5">
                    <CardTitle
                        class="text-lg font-black text-neutral-900 dark:text-neutral-50"
                        >Thông tin nhà hàng</CardTitle
                    >
                    <CardDescription
                        class="text-xs text-neutral-500 dark:text-neutral-400"
                        >Cập nhật tên, địa chỉ, liên hệ và thông tin thuế của
                        nhà hàng</CardDescription
                    >
                </div>
            </CardHeader>
            <CardContent class="p-6">
                <div
                    v-if="status"
                    class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs font-semibold text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400"
                >
                    {{ status }}
                </div>

                <div
                    v-if="!restaurant"
                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3.5 text-xs font-semibold text-amber-700 dark:border-amber-800 dark:bg-amber-950/20 dark:text-amber-400"
                >
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
                        <Label
                            class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                            >Logo nhà hàng</Label
                        >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-border bg-neutral-100 dark:bg-neutral-800"
                            >
                                <img
                                    v-if="logoPreview ?? restaurant.logo_url"
                                    :src="
                                        logoPreview ?? restaurant.logo_url ?? ''
                                    "
                                    alt="Logo nhà hàng"
                                    class="size-full animate-in object-cover duration-200 fade-in"
                                />
                                <ImagePlus
                                    v-else
                                    class="size-6 text-neutral-400"
                                />
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
                                        class="h-9 cursor-pointer gap-1.5 rounded-xl text-xs font-bold"
                                        @click="logoInput?.click()"
                                    >
                                        <ImagePlus class="size-4" /> Chọn ảnh
                                    </Button>
                                    <Button
                                        v-if="logoPreview"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-9 cursor-pointer gap-1.5 rounded-xl text-xs font-bold text-rose-500 hover:bg-rose-500/10 hover:text-rose-600"
                                        @click="clearLogoSelection"
                                    >
                                        <X class="size-4" /> Hủy chọn
                                    </Button>
                                </div>
                                <p
                                    class="text-[10px] text-neutral-500 dark:text-neutral-400"
                                >
                                    PNG, JPG hoặc WEBP, tối đa 2MB.
                                </p>
                                <InputError :message="errors.logo" />
                            </div>
                        </div>
                    </div>

                    <!-- Tên nhà hàng -->
                    <div class="grid gap-2">
                        <Label
                            for="name"
                            class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                            >Tên nhà hàng
                            <span class="text-rose-500">*</span></Label
                        >
                        <Input
                            id="name"
                            name="name"
                            :default-value="restaurant.name"
                            required
                            placeholder="Ví dụ: Phở Việt, Quán Cơm Nhà..."
                            class="block w-full rounded-xl border-neutral-200 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <!-- Phone + Email -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label
                                for="phone"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Số điện thoại</Label
                            >
                            <Input
                                id="phone"
                                name="phone"
                                type="tel"
                                :default-value="restaurant.phone ?? ''"
                                placeholder="0900 000 000"
                                class="block w-full rounded-xl border-neutral-200 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                            />
                            <InputError :message="errors.phone" />
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="email"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Email nhà hàng</Label
                            >
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                :default-value="restaurant.email ?? ''"
                                placeholder="info@nhahang.vn"
                                class="block w-full rounded-xl border-neutral-200 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                            />
                            <InputError :message="errors.email" />
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="grid gap-2">
                        <Label
                            for="address"
                            class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                            >Địa chỉ</Label
                        >
                        <div class="flex gap-2">
                            <Input
                                id="address"
                                name="address"
                                :value="addressText"
                                @input="
                                    addressText = (
                                        $event.target as HTMLInputElement
                                    ).value
                                "
                                placeholder="Số nhà, đường, phường, quận, thành phố..."
                                class="block w-full rounded-xl border-neutral-200 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="isSearchingLocation"
                                class="h-9 shrink-0 cursor-pointer gap-1.5 rounded-xl border-neutral-200 text-xs font-bold hover:bg-neutral-100 dark:border-neutral-800"
                                @click="searchAddressLocation"
                            >
                                <span v-if="isSearchingLocation"
                                    >Đang tìm...</span
                                >
                                <span v-else>Tìm trên bản đồ</span>
                            </Button>
                        </div>
                        <p
                            v-if="searchLocationError"
                            class="mt-0.5 text-xs text-rose-500"
                        >
                            {{ searchLocationError }}
                        </p>
                        <InputError :message="errors.address" />
                    </div>

                    <!-- GPS Coordinates (Vĩ độ & Kinh độ) -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label
                                for="latitude"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Vĩ độ GPS (Latitude)</Label
                            >
                            <Input
                                id="latitude"
                                name="latitude"
                                type="number"
                                step="any"
                                :value="
                                    latitude !== null ? String(latitude) : ''
                                "
                                @input="
                                    latitude = (
                                        $event.target as HTMLInputElement
                                    ).value
                                        ? Number(
                                              (
                                                  $event.target as HTMLInputElement
                                              ).value,
                                          )
                                        : null
                                "
                                placeholder="Ví dụ: 10.776889"
                                class="block w-full rounded-xl border-neutral-200 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                            />
                            <InputError :message="errors.latitude" />
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="longitude"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Kinh độ GPS (Longitude)</Label
                            >
                            <Input
                                id="longitude"
                                name="longitude"
                                type="number"
                                step="any"
                                :value="
                                    longitude !== null ? String(longitude) : ''
                                "
                                @input="
                                    longitude = (
                                        $event.target as HTMLInputElement
                                    ).value
                                        ? Number(
                                              (
                                                  $event.target as HTMLInputElement
                                              ).value,
                                          )
                                        : null
                                "
                                placeholder="Ví dụ: 106.700806"
                                class="block w-full rounded-xl border-neutral-200 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                            />
                            <InputError :message="errors.longitude" />
                        </div>
                    </div>

                    <!-- Map Container -->
                    <div class="grid gap-2">
                        <Label
                            class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                        >
                            Bản đồ định vị (Kéo thả ghim để chọn vị trí chính
                            xác)
                        </Label>
                        <div
                            ref="mapContainer"
                            class="z-0 h-[240px] w-full overflow-hidden rounded-2xl border border-neutral-200/80 bg-neutral-50 shadow-inner dark:border-neutral-800 dark:bg-neutral-950"
                        ></div>
                        <p
                            class="text-[10px] text-neutral-500 dark:text-neutral-400"
                        >
                            * Hướng dẫn: Bạn có thể nhập địa chỉ và bấm "Tìm
                            trên bản đồ", hoặc trực tiếp kéo ghim / click vào
                            một điểm trên bản đồ để xác định tọa độ chính xác.
                        </p>
                    </div>

                    <!-- Tax code -->
                    <div class="grid gap-2">
                        <Label
                            for="tax_code"
                            class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                            >Mã số thuế (MST)</Label
                        >
                        <Input
                            id="tax_code"
                            name="tax_code"
                            :default-value="restaurant.tax_code ?? ''"
                            placeholder="0123456789"
                            class="block w-full rounded-xl border-neutral-200 font-mono focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                        />
                        <InputError :message="errors.tax_code" />
                        <p
                            class="text-[10px] text-neutral-500 dark:text-neutral-400"
                        >
                            Dùng để xuất hóa đơn GTGT. Bỏ trống nếu không có.
                        </p>
                    </div>

                    <!-- Mức cọc giữ bàn -->
                    <div class="grid gap-2">
                        <Label
                            for="reservation_deposit_amount"
                            class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                            >Tiền cọc giữ bàn (đ)</Label
                        >
                        <Input
                            id="reservation_deposit_amount"
                            name="reservation_deposit_amount"
                            type="number"
                            min="0"
                            step="10000"
                            :default-value="
                                String(
                                    restaurant.reservation_deposit_amount ?? 0,
                                )
                            "
                            placeholder="0"
                            class="block w-full rounded-xl border-neutral-200 font-mono focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                        />
                        <InputError
                            :message="errors.reservation_deposit_amount"
                        />
                        <p
                            class="text-[10px] text-neutral-500 dark:text-neutral-400"
                        >
                            Khách đặt bàn online sẽ được yêu cầu thanh toán cọc
                            để giữ chỗ — bàn tự động xác nhận khi cọc được trả.
                            Đặt 0 để tắt.
                        </p>
                    </div>

                    <!-- Cấu hình Ngân hàng thụ hưởng nhận chuyển khoản QR -->
                    <div
                        class="space-y-4 border-t border-neutral-100 pt-6 text-left dark:border-neutral-800"
                    >
                        <h3
                            class="text-xs font-bold tracking-wider text-neutral-800 uppercase dark:text-neutral-200"
                        >
                            Cấu hình Thanh toán QR
                        </h3>

                        <div class="flex items-center space-x-2.5">
                            <Checkbox
                                id="qr_enabled"
                                name="qr_enabled"
                                value="1"
                                :checked="restaurant.qr_enabled"
                                class="size-4 cursor-pointer rounded border-neutral-300 text-neutral-900 dark:border-neutral-700 dark:text-neutral-50"
                            />
                            <Label
                                for="qr_enabled"
                                class="cursor-pointer text-xs font-bold text-neutral-700 select-none dark:text-neutral-300"
                                >Kích hoạt thanh toán VietQR động cho khách hàng
                                tại bàn</Label
                            >
                        </div>
                        <InputError :message="errors.qr_enabled" />

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="grid gap-2">
                                <Label
                                    for="qr_bank_id"
                                    class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                    >Ngân hàng</Label
                                >
                                <Select
                                    name="qr_bank_id"
                                    :default-value="restaurant.qr_bank_id ?? ''"
                                >
                                    <SelectTrigger
                                        class="flex h-9 w-full items-center justify-between rounded-xl border border-neutral-200 bg-white px-3 text-xs text-neutral-800 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-200"
                                    >
                                        <SelectValue
                                            placeholder="Chọn ngân hàng"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="mbbank"
                                            >MB Bank</SelectItem
                                        >
                                        <SelectItem value="vietcombank"
                                            >Vietcombank</SelectItem
                                        >
                                        <SelectItem value="techcombank"
                                            >Techcombank</SelectItem
                                        >
                                        <SelectItem value="bidv"
                                            >BIDV</SelectItem
                                        >
                                        <SelectItem value="vietinbank"
                                            >Vietinbank</SelectItem
                                        >
                                        <SelectItem value="acb">ACB</SelectItem>
                                        <SelectItem value="sacombank"
                                            >Sacombank</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.qr_bank_id" />
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    for="qr_account_number"
                                    class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                    >Số tài khoản</Label
                                >
                                <Input
                                    id="qr_account_number"
                                    name="qr_account_number"
                                    :default-value="
                                        restaurant.qr_account_number ?? ''
                                    "
                                    placeholder="Ví dụ: 0123456789"
                                    class="block w-full rounded-xl border-neutral-200 font-mono focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                                />
                                <InputError
                                    :message="errors.qr_account_number"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    for="qr_account_name"
                                    class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                    >Tên chủ tài khoản</Label
                                >
                                <Input
                                    id="qr_account_name"
                                    name="qr_account_name"
                                    :default-value="
                                        restaurant.qr_account_name ?? ''
                                    "
                                    placeholder="VIET COMBANK"
                                    class="block w-full rounded-xl border-neutral-200 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950 dark:border-neutral-800"
                                />
                                <InputError :message="errors.qr_account_name" />
                            </div>
                        </div>
                    </div>

                    <!-- Read-only info -->
                    <div
                        class="grid grid-cols-2 gap-4 rounded-2xl border border-neutral-200 bg-neutral-50/50 px-5 py-4 text-xs font-semibold dark:border-neutral-800 dark:bg-neutral-900/30"
                    >
                        <div>
                            <p
                                class="text-[10px] font-bold tracking-wider text-neutral-500 uppercase"
                            >
                                Múi giờ
                            </p>
                            <p
                                class="mt-1 font-mono font-bold text-neutral-800 dark:text-neutral-200"
                            >
                                {{ restaurant.timezone }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[10px] font-bold tracking-wider text-neutral-500 uppercase"
                            >
                                Đơn vị tiền tệ
                            </p>
                            <p
                                class="mt-1 font-mono font-bold text-neutral-800 dark:text-neutral-200"
                            >
                                {{ restaurant.currency }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <Button
                            type="submit"
                            :disabled="processing"
                            class="cursor-pointer rounded-xl bg-neutral-900 px-6 py-2.5 text-xs font-bold tracking-wider text-white uppercase shadow-sm transition-all duration-200 hover:bg-neutral-800 active:scale-95 disabled:opacity-50 dark:bg-neutral-50 dark:text-neutral-950 dark:hover:bg-neutral-200"
                        >
                            {{ processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
