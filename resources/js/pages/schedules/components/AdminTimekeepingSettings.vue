<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Settings, AlertCircle, LocateFixed, MapPin, CheckCircle2 } from 'lucide-vue-next';
import { ref, nextTick, onBeforeUnmount } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    restaurantSettings: any;
    gpsSettings: any;
    qrSettings: any;
}>();

const gracePeriod = ref(props.restaurantSettings?.grace_period_minutes ?? 10);
const otMultiplier = ref(props.restaurantSettings?.ot_multiplier ?? 1.50);
const latitude = ref(props.gpsSettings?.latitude ?? '');
const longitude = ref(props.gpsSettings?.longitude ?? '');
const checkinRadius = ref(props.gpsSettings?.radius ?? 100);

const isSavingSettings = ref(false);
const isGeneratingQR = ref(false);
const isDetectingLocation = ref(false);
const locationStatus = ref<'idle' | 'success' | 'error'>('idle');
const locationError = ref('');

// Leaflet map state
const showMap = ref(false);
let leafletMap: any = null;
let leafletMarker: any = null;

const saveSettings = () => {
    isSavingSettings.value = true;
    router.post('/schedules/settings', {
        grace_period_minutes: gracePeriod.value,
        ot_multiplier: otMultiplier.value,
        latitude: latitude.value === '' ? null : Number(latitude.value),
        longitude: longitude.value === '' ? null : Number(longitude.value),
        checkin_radius_meters: Number(checkinRadius.value),
    }, {
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Đã lưu cấu hình chấm công thành công!'));
        },
        onError: () => {
            import('vue-sonner').then(m => m.toast.error('Có lỗi xảy ra khi lưu cấu hình.'));
        },
        onFinish: () => {
            isSavingSettings.value = false;
        }
    });
};

const generateDailyQR = () => {
    isGeneratingQR.value = true;
    router.post('/schedules/settings/generate-qr', {}, {
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Đã tạo mã QR chấm công hôm nay thành công!'));
        },
        onError: () => {
            import('vue-sonner').then(m => m.toast.error('Có lỗi xảy ra khi tạo mã QR.'));
        },
        onFinish: () => {
            isGeneratingQR.value = false;
        }
    });
};

/**
 * Auto-detect restaurant location using browser Geolocation API.
 * Fills in the latitude/longitude fields and opens the preview map.
 */
const autoDetectLocation = () => {
    if (!navigator.geolocation) {
        locationStatus.value = 'error';
        locationError.value = 'Trình duyệt không hỗ trợ định vị GPS.';
        return;
    }

    isDetectingLocation.value = true;
    locationStatus.value = 'idle';

    navigator.geolocation.getCurrentPosition(
        async (pos) => {
            latitude.value = pos.coords.latitude.toFixed(7);
            longitude.value = pos.coords.longitude.toFixed(7);
            isDetectingLocation.value = false;
            locationStatus.value = 'success';
            await openMap();
        },
        (err) => {
            isDetectingLocation.value = false;
            locationStatus.value = 'error';
            locationError.value = err.code === 1
                ? 'Bạn đã từ chối quyền truy cập vị trí. Vui lòng cấp quyền trong trình duyệt và thử lại.'
                : 'Không thể lấy vị trí. Kiểm tra kết nối GPS / mạng và thử lại.';
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
};

/**
 * Open the Leaflet map panel to preview/confirm the configured coordinates.
 */
const openMap = async () => {
    const lat = parseFloat(String(latitude.value));
    const lng = parseFloat(String(longitude.value));

    if (isNaN(lat) || isNaN(lng)) {
        import('vue-sonner').then(m => m.toast.error('Vui lòng nhập hoặc định vị tọa độ trước khi xem bản đồ.'));
        return;
    }

    showMap.value = true;
    await nextTick();

    // Lazily load Leaflet only when the map panel is first opened
    const L = (await import('leaflet')).default;
    await import('leaflet/dist/leaflet.css');

    // Fix default marker icon path broken by bundlers
    delete (L.Icon.Default.prototype as any)._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    });

    const container = document.getElementById('restaurant-map');
    if (!container) return;

    if (leafletMap) {
        // Map already initialised — update view and marker
        leafletMap.setView([lat, lng], 16);
        leafletMarker?.setLatLng([lat, lng]);
        return;
    }

    leafletMap = L.map(container).setView([lat, lng], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(leafletMap);

    leafletMarker = L.marker([lat, lng], { draggable: true })
        .addTo(leafletMap)
        .bindPopup('📍 Vị trí cửa hàng (kéo để điều chỉnh)')
        .openPopup();

    // Allow admin to drag the marker to fine-tune the coordinates
    leafletMarker.on('dragend', () => {
        const pos = leafletMarker.getLatLng();
        latitude.value = pos.lat.toFixed(7);
        longitude.value = pos.lng.toFixed(7);
    });
};

const closeMap = () => {
    showMap.value = false;
};

onBeforeUnmount(() => {
    leafletMap?.remove();
    leafletMap = null;
    leafletMarker = null;
});
</script>

<template>
    <Card class="shadow-sm">
        <CardHeader class="pb-3 border-b">
            <CardTitle class="text-base flex items-center gap-1.5 text-indigo-650">
                <Settings class="size-5 text-indigo-655" />
                Cấu Hình Tham Số Chấm Công
            </CardTitle>
            <CardDescription>Thiết lập thời gian đi trễ cho phép và hệ số tính lương làm thêm giờ cho nhà hàng.</CardDescription>
        </CardHeader>
        <CardContent class="p-6">
            <form @submit.prevent="saveSettings" class="max-w-md space-y-6">
                <!-- Grace period -->
                <div class="grid gap-2">
                    <Label for="grace-period-input" class="text-xs font-bold text-slate-700 dark:text-slate-350">
                        Thời gian đi trễ cho phép (Phút)
                    </Label>
                    <span class="text-[11px] text-muted-foreground">
                        Nhân viên check-in trễ trong khoảng thời gian này so với giờ bắt đầu ca vẫn được tính là đi đúng giờ.
                    </span>
                    <Input 
                        id="grace-period-input"
                        type="number"
                        v-model.number="gracePeriod"
                        min="0"
                        max="120"
                        required
                        class="h-10 text-sm w-full md:w-2/3"
                    />
                </div>

                <!-- OT Multiplier -->
                <div class="grid gap-2">
                    <Label for="ot-multiplier-input" class="text-xs font-bold text-slate-700 dark:text-slate-350">
                        Hệ số tính lương tăng ca (Overtime Multiplier)
                    </Label>
                    <span class="text-[11px] text-muted-foreground">
                        Hệ số nhân với mức lương cơ bản theo giờ cho phần thời gian nhân viên làm ngoài giờ ca trực được duyệt.
                    </span>
                    <Input 
                        id="ot-multiplier-input"
                        type="number"
                        v-model.number="otMultiplier"
                        min="1.0"
                        max="5.0"
                        step="0.05"
                        required
                        class="h-10 text-sm w-full md:w-2/3"
                    />
                </div>

                <!-- GPS Latitude & Longitude -->
                <div class="grid gap-3">
                    <div class="flex items-center justify-between">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-350">
                            Tọa độ GPS cửa hàng
                        </Label>
                        <!-- Auto-detect button -->
                        <Button
                            id="btn-auto-detect-gps"
                            type="button"
                            @click="autoDetectLocation"
                            :disabled="isDetectingLocation"
                            size="sm"
                            variant="outline"
                            class="h-7 text-[11px] gap-1.5 border-indigo-300 text-indigo-700 hover:bg-indigo-50 dark:border-indigo-700 dark:text-indigo-300 dark:hover:bg-indigo-950/30 cursor-pointer"
                        >
                            <LocateFixed class="size-3.5" :class="isDetectingLocation ? 'animate-spin' : ''" />
                            {{ isDetectingLocation ? 'Đang định vị...' : 'Định vị tự động' }}
                        </Button>
                    </div>

                    <!-- Success / error feedback -->
                    <div v-if="locationStatus === 'success'" class="flex items-center gap-1.5 text-[11px] text-emerald-600 dark:text-emerald-400">
                        <CheckCircle2 class="size-3.5 shrink-0" />
                        Đã lấy vị trí thành công! Kéo ghim trên bản đồ để tinh chỉnh chính xác hơn.
                    </div>
                    <div v-if="locationStatus === 'error'" class="flex items-start gap-1.5 text-[11px] text-rose-600 dark:text-rose-400">
                        <AlertCircle class="size-3.5 shrink-0 mt-0.5" />
                        {{ locationError }}
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="gps-lat" class="text-xs font-bold text-slate-700 dark:text-slate-350">
                                Vĩ độ (Latitude)
                            </Label>
                            <Input 
                                id="gps-lat"
                                type="number"
                                step="0.0000001"
                                v-model="latitude"
                                placeholder="Ví dụ: 10.7769"
                                class="h-10 text-sm w-full"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="gps-lng" class="text-xs font-bold text-slate-700 dark:text-slate-350">
                                Kinh độ (Longitude)
                            </Label>
                            <Input 
                                id="gps-lng"
                                type="number"
                                step="0.0000001"
                                v-model="longitude"
                                placeholder="Ví dụ: 106.7009"
                                class="h-10 text-sm w-full"
                            />
                        </div>
                    </div>

                    <!-- Map preview button -->
                    <Button
                        id="btn-preview-map"
                        type="button"
                        @click="openMap"
                        variant="outline"
                        size="sm"
                        class="h-8 text-[11px] gap-1.5 w-fit cursor-pointer"
                        :disabled="!latitude || !longitude"
                    >
                        <MapPin class="size-3.5" />
                        Xem vị trí trên bản đồ
                    </Button>

                    <!-- Leaflet map panel -->
                    <div v-if="showMap" class="space-y-2">
                        <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                            <span class="flex items-center gap-1"><MapPin class="size-3 text-indigo-500" /> Kéo ghim để điều chỉnh vị trí chính xác</span>
                            <button type="button" @click="closeMap" class="text-muted-foreground hover:text-foreground underline cursor-pointer">Đóng</button>
                        </div>
                        <div
                            id="restaurant-map"
                            class="w-full rounded-xl border border-border overflow-hidden"
                            style="height: 280px;"
                        ></div>
                        <p class="text-[10px] text-muted-foreground">
                            Bản đồ sử dụng OpenStreetMap — miễn phí, không cần API key.
                        </p>
                    </div>
                </div>

                <!-- GPS Radius -->
                <div class="grid gap-2">
                    <Label for="gps-radius" class="text-xs font-bold text-slate-700 dark:text-slate-350">
                        Bán kính giới hạn check-in (Mét)
                    </Label>
                    <span class="text-[11px] text-muted-foreground">
                        Khoảng cách tối đa cho phép nhân viên check-in so với vị trí của nhà hàng.
                    </span>
                    <Input 
                        id="gps-radius"
                        type="number"
                        v-model.number="checkinRadius"
                        min="10"
                        max="10000"
                        required
                        class="h-10 text-sm w-full md:w-2/3"
                    />
                </div>

                <!-- Daily QR Code Generation Section -->
                <div class="border-t pt-6 space-y-4">
                    <h3 class="text-xs font-bold text-slate-700 dark:text-slate-350 uppercase tracking-wider">
                        Mã QR Code chấm công hôm nay
                    </h3>
                    <p class="text-[11px] text-muted-foreground">
                        Tạo mã QR an toàn dùng để dán tại nhà hàng. Nhân viên nhập mã này khi check-in để xác minh.
                    </p>
                    
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-slate-50 dark:bg-slate-900 border rounded-xl flex-1">
                            <div class="text-[10px] text-slate-400 font-bold uppercase">Mã hiện tại:</div>
                            <div class="text-lg font-mono font-black text-indigo-650 dark:text-indigo-400 mt-1">
                                {{ qrSettings?.code || 'Chưa tạo mã' }}
                            </div>
                            <div v-if="qrSettings?.expires_at" class="text-[10px] text-slate-400 mt-1">
                                Hiệu lực đến: {{ qrSettings.expires_at }} 
                                <span v-if="qrSettings.is_expired" class="text-rose-600 font-bold ml-1">(Hết hạn)</span>
                                <span v-else class="text-emerald-600 font-bold ml-1">(Còn hiệu lực)</span>
                            </div>
                        </div>
                        <Button 
                            type="button" 
                            @click="generateDailyQR"
                            :disabled="isGeneratingQR"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs h-12 px-4 shadow cursor-pointer"
                        >
                            {{ isGeneratingQR ? 'Đang tạo...' : 'Tạo mã QR' }}
                        </Button>
                    </div>
                </div>

                <!-- Audit info notice -->
                <div class="p-3 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-xl flex items-start gap-2 text-[10px] text-indigo-700 dark:text-indigo-400 border border-indigo-100/50">
                    <AlertCircle class="size-4 shrink-0 text-indigo-650 mt-0.5" />
                    <p><strong>Lưu ý:</strong> Cài đặt mới sẽ lập tức áp dụng cho mọi lượt chấm công hoàn thành tiếp theo và được dùng khi tính toán lại bảng lương nháp tháng này.</p>
                </div>

                <!-- Submit button -->
                <div class="flex justify-start">
                    <Button 
                        type="submit" 
                        :disabled="isSavingSettings"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs h-9 px-4 shadow-sm cursor-pointer"
                    >
                        {{ isSavingSettings ? 'Đang lưu...' : 'Lưu cấu hình' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
