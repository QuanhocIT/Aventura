<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Settings, AlertCircle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

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
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="gps-lat" class="text-xs font-bold text-slate-700 dark:text-slate-350">
                            Vĩ độ GPS (Latitude)
                        </Label>
                        <Input 
                            id="gps-lat"
                            type="number"
                            step="0.00000001"
                            v-model="latitude"
                            placeholder="Ví dụ: 10.7769"
                            class="h-10 text-sm w-full"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="gps-lng" class="text-xs font-bold text-slate-700 dark:text-slate-350">
                            Kinh độ GPS (Longitude)
                        </Label>
                        <Input 
                            id="gps-lng"
                            type="number"
                            step="0.00000001"
                            v-model="longitude"
                            placeholder="Ví dụ: 106.7009"
                            class="h-10 text-sm w-full"
                        />
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
