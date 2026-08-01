<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Settings, AlertCircle } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    restaurantSettings: any;
    gpsSettings: any;
    qrSettings: any;
}>();

const gracePeriod = ref(props.restaurantSettings?.grace_period_minutes ?? 10);
const otMultiplier = ref(props.restaurantSettings?.ot_multiplier ?? 1.5);
const latitude = ref(props.gpsSettings?.latitude ?? '');
const longitude = ref(props.gpsSettings?.longitude ?? '');
const checkinRadius = ref(props.gpsSettings?.radius ?? 100);

const isSavingSettings = ref(false);
const isGeneratingQR = ref(false);

const saveSettings = () => {
    isSavingSettings.value = true;
    router.post(
        '/schedules/settings',
        {
            grace_period_minutes: gracePeriod.value,
            ot_multiplier: otMultiplier.value,
            latitude: latitude.value === '' ? null : Number(latitude.value),
            longitude: longitude.value === '' ? null : Number(longitude.value),
            checkin_radius_meters: Number(checkinRadius.value),
        },
        {
            onSuccess: () => {
                import('vue-sonner').then((m) =>
                    m.toast.success('Đã lưu cấu hình chấm công thành công!'),
                );
            },
            onError: () => {
                import('vue-sonner').then((m) =>
                    m.toast.error('Có lỗi xảy ra khi lưu cấu hình.'),
                );
            },
            onFinish: () => {
                isSavingSettings.value = false;
            },
        },
    );
};

const generateDailyQR = () => {
    isGeneratingQR.value = true;
    router.post(
        '/schedules/settings/generate-qr',
        {},
        {
            onSuccess: () => {
                import('vue-sonner').then((m) =>
                    m.toast.success(
                        'Đã tạo mã QR chấm công hôm nay thành công!',
                    ),
                );
            },
            onError: () => {
                import('vue-sonner').then((m) =>
                    m.toast.error('Có lỗi xảy ra khi tạo mã QR.'),
                );
            },
            onFinish: () => {
                isGeneratingQR.value = false;
            },
        },
    );
};

// Dynamic QR State
const dynamicQrCode = ref('');
const dynamicQrSvg = ref('');
const dynamicQrExpiresIn = ref(0);
let dynamicQrInterval: any = null;

const fetchDynamicQr = () => {
    fetch('/schedules/dynamic-qr')
        .then((res) => res.json())
        .then((data) => {
            if (data.code && data.svg) {
                dynamicQrCode.value = data.code;
                dynamicQrSvg.value = data.svg;
                dynamicQrExpiresIn.value = data.expires_in;
            }
        })
        .catch((err) => console.error('Lỗi lấy QR động:', err));
};

onMounted(() => {
    fetchDynamicQr();

    dynamicQrInterval = setInterval(() => {
        if (dynamicQrExpiresIn.value > 1) {
            dynamicQrExpiresIn.value--;
        } else {
            fetchDynamicQr();
        }
    }, 1000);
});

onUnmounted(() => {
    if (dynamicQrInterval) {
        clearInterval(dynamicQrInterval);
    }
});
</script>

<template>
    <Card class="shadow-sm">
        <CardHeader class="border-b pb-3">
            <CardTitle
                class="text-indigo-650 flex items-center gap-1.5 text-base"
            >
                <Settings class="text-indigo-655 size-5" />
                Cấu Hình Tham Số Chấm Công
            </CardTitle>
            <CardDescription
                >Thiết lập thời gian đi trễ cho phép và hệ số tính lương làm
                thêm giờ cho nhà hàng.</CardDescription
            >
        </CardHeader>
        <CardContent class="p-6">
            <form @submit.prevent="saveSettings" class="max-w-md space-y-6">
                <!-- Grace period -->
                <div class="grid gap-2">
                    <Label
                        for="grace-period-input"
                        class="dark:text-slate-350 text-xs font-bold text-slate-700"
                    >
                        Thời gian đi trễ cho phép (Phút)
                    </Label>
                    <span class="text-[11px] text-muted-foreground">
                        Nhân viên check-in trễ trong khoảng thời gian này so với
                        giờ bắt đầu ca vẫn được tính là đi đúng giờ.
                    </span>
                    <Input
                        id="grace-period-input"
                        type="number"
                        v-model.number="gracePeriod"
                        min="0"
                        max="120"
                        required
                        class="h-10 w-full text-sm md:w-2/3"
                    />
                </div>

                <!-- OT Multiplier -->
                <div class="grid gap-2">
                    <Label
                        for="ot-multiplier-input"
                        class="dark:text-slate-350 text-xs font-bold text-slate-700"
                    >
                        Hệ số tính lương tăng ca (Overtime Multiplier)
                    </Label>
                    <span class="text-[11px] text-muted-foreground">
                        Hệ số nhân với mức lương cơ bản theo giờ cho phần thời
                        gian nhân viên làm ngoài giờ ca trực được duyệt.
                    </span>
                    <Input
                        id="ot-multiplier-input"
                        type="number"
                        v-model.number="otMultiplier"
                        min="1.0"
                        max="5.0"
                        step="0.05"
                        required
                        class="h-10 w-full text-sm md:w-2/3"
                    />
                </div>

                <!-- GPS Latitude & Longitude -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label
                            for="gps-lat"
                            class="dark:text-slate-350 text-xs font-bold text-slate-700"
                        >
                            Vĩ độ GPS (Latitude)
                        </Label>
                        <Input
                            id="gps-lat"
                            type="number"
                            step="0.00000001"
                            v-model="latitude"
                            placeholder="Ví dụ: 10.7769"
                            class="h-10 w-full text-sm"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label
                            for="gps-lng"
                            class="dark:text-slate-350 text-xs font-bold text-slate-700"
                        >
                            Kinh độ GPS (Longitude)
                        </Label>
                        <Input
                            id="gps-lng"
                            type="number"
                            step="0.00000001"
                            v-model="longitude"
                            placeholder="Ví dụ: 106.7009"
                            class="h-10 w-full text-sm"
                        />
                    </div>
                </div>

                <!-- GPS Radius -->
                <div class="grid gap-2">
                    <Label
                        for="gps-radius"
                        class="dark:text-slate-350 text-xs font-bold text-slate-700"
                    >
                        Bán kính giới hạn check-in (Mét)
                    </Label>
                    <span class="text-[11px] text-muted-foreground">
                        Khoảng cách tối đa cho phép nhân viên check-in so với vị
                        trí của nhà hàng.
                    </span>
                    <Input
                        id="gps-radius"
                        type="number"
                        v-model.number="checkinRadius"
                        min="10"
                        max="10000"
                        required
                        class="h-10 w-full text-sm md:w-2/3"
                    />
                </div>

                <!-- Daily QR Code Generation Section -->
                <div class="space-y-4 border-t pt-6">
                    <h3
                        class="dark:text-slate-350 text-xs font-bold tracking-wider text-slate-700 uppercase"
                    >
                        Mã QR Code chấm công hôm nay
                    </h3>
                    <p class="text-[11px] text-muted-foreground">
                        Tạo mã QR an toàn dùng để dán tại nhà hàng. Nhân viên
                        nhập mã này khi check-in để xác minh.
                    </p>

                    <div class="flex items-center gap-3">
                        <div
                            class="flex-1 rounded-xl border bg-slate-50 p-3 dark:bg-slate-900"
                        >
                            <div
                                class="text-[10px] font-bold text-slate-400 uppercase"
                            >
                                Mã hiện tại:
                            </div>
                            <div
                                class="text-indigo-650 mt-1 font-mono text-lg font-black dark:text-indigo-400"
                            >
                                {{ qrSettings?.code || 'Chưa tạo mã' }}
                            </div>
                            <div
                                v-if="qrSettings?.expires_at"
                                class="mt-1 text-[10px] text-slate-400"
                            >
                                Hiệu lực đến: {{ qrSettings.expires_at }}
                                <span
                                    v-if="qrSettings.is_expired"
                                    class="ml-1 font-bold text-rose-600"
                                    >(Hết hạn)</span
                                >
                                <span
                                    v-else
                                    class="ml-1 font-bold text-emerald-600"
                                    >(Còn hiệu lực)</span
                                >
                            </div>
                        </div>
                        <Button
                            type="button"
                            @click="generateDailyQR"
                            :disabled="isGeneratingQR"
                            class="h-12 cursor-pointer bg-indigo-600 px-4 text-xs font-semibold text-white shadow hover:bg-indigo-700"
                        >
                            {{ isGeneratingQR ? 'Đang tạo...' : 'Tạo mã QR' }}
                        </Button>
                    </div>
                </div>

                <!-- Dynamic Rotating QR Code for POS / Front Desk -->
                <div class="space-y-4 border-t pt-6">
                    <h3
                        class="dark:text-slate-350 flex items-center gap-2 text-xs font-bold tracking-wider text-slate-700 uppercase"
                    >
                        <span
                            class="flex h-2 w-2 animate-pulse rounded-full bg-emerald-500"
                        ></span>
                        Mã QR xoay vòng thời gian thực (POS / Quầy)
                    </h3>
                    <p class="text-[11px] text-muted-foreground">
                        Mã QR này tự động thay đổi sau mỗi 20 giây để nhân viên
                        quét trực tiếp tại cửa hàng, chống GPS giả lập và chấm
                        công hộ từ xa.
                    </p>

                    <div
                        class="flex flex-col items-center justify-center gap-4 rounded-xl border bg-slate-50 p-4 dark:bg-slate-900"
                    >
                        <div
                            v-if="dynamicQrSvg"
                            v-html="dynamicQrSvg"
                            class="rounded-lg border border-slate-100 bg-white p-2 shadow-sm dark:border-slate-800"
                        />
                        <div
                            v-else
                            class="flex h-[155px] w-[155px] items-center justify-center bg-slate-100 text-xs text-slate-400 dark:bg-slate-800"
                        >
                            Đang tải mã QR...
                        </div>

                        <div class="text-center">
                            <span
                                class="border-indigo-150 rounded-full border bg-indigo-50 px-3 py-1 font-mono text-xs font-bold tracking-widest text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300"
                            >
                                {{ dynamicQrCode || '------' }}
                            </span>
                            <p class="mt-2 text-[10px] text-slate-400">
                                Tự động đổi mới trong
                                <strong
                                    class="text-indigo-600 dark:text-indigo-400"
                                    >{{ dynamicQrExpiresIn }}</strong
                                >
                                giây
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Audit info notice -->
                <div
                    class="flex items-start gap-2 rounded-xl border border-indigo-100/50 bg-indigo-50/50 p-3 text-[10px] text-indigo-700 dark:bg-indigo-950/20 dark:text-indigo-400"
                >
                    <AlertCircle
                        class="text-indigo-650 mt-0.5 size-4 shrink-0"
                    />
                    <p>
                        <strong>Lưu ý:</strong> Cài đặt mới sẽ lập tức áp dụng
                        cho mọi lượt chấm công hoàn thành tiếp theo và được dùng
                        khi tính toán lại bảng lương nháp tháng này.
                    </p>
                </div>

                <!-- Submit button -->
                <div class="flex justify-start">
                    <Button
                        type="submit"
                        :disabled="isSavingSettings"
                        class="h-9 cursor-pointer bg-indigo-600 px-4 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                    >
                        {{ isSavingSettings ? 'Đang lưu...' : 'Lưu cấu hình' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
