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
const maxLateCheckin = ref(props.restaurantSettings?.max_late_checkin_minutes ?? 60);
const latePenaltyType = ref(props.restaurantSettings?.late_penalty_type ?? 'none');
const latePenaltyAmount = ref(props.restaurantSettings?.late_penalty_amount ?? 0);

const earlyCheckoutGrace = ref(props.restaurantSettings?.early_checkout_grace_minutes ?? 5);
const maxEarlyCheckout = ref(props.restaurantSettings?.max_early_checkout_minutes ?? 60);
const earlyCheckoutPenaltyType = ref(props.restaurantSettings?.early_checkout_penalty_type ?? 'none');
const earlyCheckoutPenaltyAmount = ref(props.restaurantSettings?.early_checkout_penalty_amount ?? 0);

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
            max_late_checkin_minutes: maxLateCheckin.value,
            late_penalty_type: latePenaltyType.value,
            late_penalty_amount: latePenaltyAmount.value,
            early_checkout_grace_minutes: earlyCheckoutGrace.value,
            max_early_checkout_minutes: maxEarlyCheckout.value,
            early_checkout_penalty_type: earlyCheckoutPenaltyType.value,
            early_checkout_penalty_amount: earlyCheckoutPenaltyAmount.value,
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
                        Thời gian ân hạn đi trễ cho phép (Phút)
                    </Label>
                    <span class="text-[11px] text-muted-foreground">
                        Nhân viên check-in trễ trong khoảng thời gian này so với
                        giờ bắt đầu ca vẫn được tính là đi đúng giờ (không tính trễ/phạt).
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

                <!-- Max Late Checkin -->
                <div class="grid gap-2">
                    <Label
                        for="max-late-input"
                        class="dark:text-slate-350 text-xs font-bold text-slate-700"
                    >
                        Thời gian cho phép check-in muộn tối đa (Phút)
                    </Label>
                    <span class="text-[11px] text-muted-foreground">
                        Thời gian đi trễ tối đa so với giờ ca bắt đầu mà nhân viên vẫn được phép bấm Check-in (ví dụ: 60 phút). Nếu trễ quá khoảng thời gian này, hệ thống sẽ từ chối check-in và yêu cầu liên hệ Quản lý.
                    </span>
                    <Input
                        id="max-late-input"
                        type="number"
                        v-model.number="maxLateCheckin"
                        min="0"
                        max="480"
                        placeholder="Ví dụ: 60"
                        class="h-10 w-full text-sm md:w-2/3"
                    />
                </div>

                <!-- Late Penalty Type -->
                <div class="grid gap-2">
                    <Label
                        for="late-penalty-type-select"
                        class="dark:text-slate-350 text-xs font-bold text-slate-700"
                    >
                        Quy định hình thức phạt tiền khi đi muộn
                    </Label>
                    <span class="text-[11px] text-muted-foreground">
                        Cách thức hệ thống tự động tính tiền phạt khấu trừ lương khi nhân viên đi trễ vượt quá thời gian ân hạn.
                    </span>
                    <select
                        id="late-penalty-type-select"
                        v-model="latePenaltyType"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm md:w-2/3 focus:outline-none focus:ring-2 focus:ring-ring dark:bg-slate-900 dark:text-slate-100"
                    >
                        <option value="none">🟢 Không phạt tiền (Chỉ tạo biên bản nhắc nhở đi trễ)</option>
                        <option value="per_minute">⏱ Phạt cố định theo mỗi phút đi muộn (VNĐ/Phút)</option>
                        <option value="fixed_per_occurrence">💵 Phạt số tiền cố định cho mỗi lần đi muộn (VNĐ/Lần)</option>
                        <option value="deduct_minute_salary">📉 Trừ tiền theo mức lương giờ thực tế cho số phút đi muộn</option>
                    </select>
                </div>

                <!-- Late Penalty Amount -->
                <div
                    v-if="latePenaltyType === 'per_minute' || latePenaltyType === 'fixed_per_occurrence'"
                    class="grid gap-2"
                >
                    <Label
                        for="late-penalty-amount-input"
                        class="dark:text-slate-350 text-xs font-bold text-slate-700"
                    >
                        Mức tiền phạt đi muộn (VNĐ)
                    </Label>
                    <span class="text-[11px] text-muted-foreground">
                        {{ latePenaltyType === 'per_minute' ? 'Số tiền phạt trừ vào lương cho mỗi 1 phút đi trễ (ví dụ: 2000đ/phút).' : 'Số tiền phạt trừ vào lương cho 1 lần đi trễ (ví dụ: 50.000đ/lần).' }}
                    </span>
                    <Input
                        id="late-penalty-amount-input"
                        type="number"
                        v-model.number="latePenaltyAmount"
                        min="0"
                        step="500"
                        placeholder="Ví dụ: 5000"
                        class="h-10 w-full text-sm md:w-2/3"
                    />
                </div>

                <div class="my-4 border-t pt-4">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">
                        🚪 Tham Số Ra Ca & Check-out Sớm (Về Sớm)
                    </h3>
                </div>

                <!-- Early Checkout Grace -->
                <div class="grid gap-2">
                    <Label
                        for="early-checkout-grace-input"
                        class="dark:text-slate-350 text-xs font-bold text-slate-700"
                    >
                        Thời gian ân hạn về sớm cho phép (Phút)
                    </Label>
                    <span class="text-[11px] text-muted-foreground">
                        Nhân viên check-out sớm trong khoảng thời gian này trước khi ca kết thúc vẫn được tính là hoàn thành ca đúng giờ (không tính phạt về sớm).
                    </span>
                    <Input
                        id="early-checkout-grace-input"
                        type="number"
                        v-model.number="earlyCheckoutGrace"
                        min="0"
                        max="120"
                        required
                        class="h-10 w-full text-sm md:w-2/3"
                    />
                </div>

                <!-- Max Early Checkout -->
                <div class="grid gap-2">
                    <Label
                        for="max-early-checkout-input"
                        class="dark:text-slate-350 text-xs font-bold text-slate-700"
                    >
                        Thời gian cho phép check-out sớm tối đa (Phút)
                    </Label>
                    <span class="text-[11px] text-muted-foreground">
                        Thời gian về sớm tối đa so với giờ ca kết thúc mà nhân viên vẫn được phép bấm Check-out (ví dụ: 60 phút). Nếu về sớm quá thời gian này, hệ thống sẽ từ chối check-out và yêu cầu Quản lý duyệt.
                    </span>
                    <Input
                        id="max-early-checkout-input"
                        type="number"
                        v-model.number="maxEarlyCheckout"
                        min="0"
                        max="480"
                        placeholder="Ví dụ: 60"
                        class="h-10 w-full text-sm md:w-2/3"
                    />
                </div>

                <!-- Early Checkout Penalty Type -->
                <div class="grid gap-2">
                    <Label
                        for="early-checkout-penalty-type-select"
                        class="dark:text-slate-350 text-xs font-bold text-slate-700"
                    >
                        Quy định hình thức phạt tiền khi về sớm
                    </Label>
                    <span class="text-[11px] text-muted-foreground">
                        Cách thức hệ thống tự động tính tiền phạt khấu trừ lương khi nhân viên check-out sớm vượt quá thời gian ân hạn.
                    </span>
                    <select
                        id="early-checkout-penalty-type-select"
                        v-model="earlyCheckoutPenaltyType"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm md:w-2/3 focus:outline-none focus:ring-2 focus:ring-ring dark:bg-slate-900 dark:text-slate-100"
                    >
                        <option value="none">🟢 Không phạt tiền (Chỉ tạo biên bản nhắc nhở về sớm)</option>
                        <option value="per_minute">⏱ Phạt cố định theo mỗi phút về sớm (VNĐ/Phút)</option>
                        <option value="fixed_per_occurrence">💵 Phạt số tiền cố định cho mỗi lần về sớm (VNĐ/Lần)</option>
                        <option value="deduct_minute_salary">📉 Trừ tiền theo mức lương giờ thực tế cho số phút về sớm</option>
                    </select>
                </div>

                <!-- Early Checkout Penalty Amount -->
                <div
                    v-if="earlyCheckoutPenaltyType === 'per_minute' || earlyCheckoutPenaltyType === 'fixed_per_occurrence'"
                    class="grid gap-2"
                >
                    <Label
                        for="early-checkout-penalty-amount-input"
                        class="dark:text-slate-350 text-xs font-bold text-slate-700"
                    >
                        Mức tiền phạt về sớm (VNĐ)
                    </Label>
                    <span class="text-[11px] text-muted-foreground">
                        {{ earlyCheckoutPenaltyType === 'per_minute' ? 'Số tiền phạt trừ vào lương cho mỗi 1 phút về sớm (ví dụ: 2000đ/phút).' : 'Số tiền phạt trừ vào lương cho 1 lần về sớm (ví dụ: 50.000đ/lần).' }}
                    </span>
                    <Input
                        id="early-checkout-penalty-amount-input"
                        type="number"
                        v-model.number="earlyCheckoutPenaltyAmount"
                        min="0"
                        step="500"
                        placeholder="Ví dụ: 5000"
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
