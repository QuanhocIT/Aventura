<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Clock, AlertCircle, RefreshCw, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    isOpen: boolean;
    gpsSettings?: {
        latitude: number | null;
        longitude: number | null;
        radius: number;
    };
    qrSettings?: {
        code: string | null;
        expires_at: string | null;
        is_expired: boolean;
    };
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success', page: any): void;
}>();

const gpsCoords = ref<{ latitude: number | null; longitude: number | null }>({ latitude: null, longitude: null });
const gpsStatus = ref<'idle' | 'fetching' | 'success' | 'error'>('idle');
const gpsErrorMsg = ref('');
const inputQrCode = ref('');
const isCheckingIn = ref(false);

// Webcam & Selfie Check-In State
const videoRef = ref<HTMLVideoElement | null>(null);
const streamRef = ref<MediaStream | null>(null);
const checkInPhoto = ref<string | null>(null);
const webcamError = ref<string | null>(null);

const startWebcam = async () => {
    try {
        webcamError.value = null;
        checkInPhoto.value = null;
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: 480, height: 360 }
        });
        streamRef.value = stream;
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
        }
    } catch (err: any) {
        console.error(err);
        webcamError.value = 'Không thể truy cập camera. Vui lòng cấp quyền hoặc kiểm tra thiết bị.';
    }
};

const stopWebcam = () => {
    if (streamRef.value) {
        streamRef.value.getTracks().forEach(track => track.stop());
        streamRef.value = null;
    }
};

const captureSelfie = () => {
    if (!videoRef.value) return;
    const canvas = document.createElement('canvas');
    canvas.width = 480;
    canvas.height = 360;
    const ctx = canvas.getContext('2d');
    if (ctx) {
        ctx.drawImage(videoRef.value, 0, 0, canvas.width, canvas.height);
        checkInPhoto.value = canvas.toDataURL('image/jpeg', 0.85);
        stopWebcam();
    }
};

const retakeSelfie = () => {
    checkInPhoto.value = null;
    startWebcam();
};

const closeCheckInFlow = () => {
    stopWebcam();
    emit('close');
};

const submitCheckIn = () => {
    if (!checkInPhoto.value) {
        import('vue-sonner').then(m => m.toast.error('Vui lòng chụp ảnh selfie để xác minh danh tính.'));
        return;
    }
    isCheckingIn.value = true;
    router.post('/schedules/check-in', {
        latitude: gpsCoords.value.latitude,
        longitude: gpsCoords.value.longitude,
        qr_code: inputQrCode.value,
        check_in_photo: checkInPhoto.value
    }, {
        onSuccess: (page: any) => {
            stopWebcam();
            emit('success', page);
        },
        onError: (errors: any) => {
            const errorMsg = Object.values(errors).join(', ') || 'Có lỗi xảy ra khi check-in.';
            import('vue-sonner').then(m => m.toast.error(errorMsg));
        },
        onFinish: () => {
            isCheckingIn.value = false;
        }
    });
};

onMounted(() => {
    setTimeout(() => {
        startWebcam();
    }, 100);

    if (props.gpsSettings?.latitude && props.gpsSettings?.longitude) {
        gpsStatus.value = 'fetching';
        if (!navigator.geolocation) {
            gpsStatus.value = 'error';
            gpsErrorMsg.value = 'Trình duyệt không hỗ trợ định vị GPS.';
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (position) => {
                gpsCoords.value.latitude = position.coords.latitude;
                gpsCoords.value.longitude = position.coords.longitude;
                gpsStatus.value = 'success';
            },
            (error) => {
                gpsStatus.value = 'error';
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        gpsErrorMsg.value = 'Quyền truy cập định vị bị từ chối. Vui lòng bật định vị.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        gpsErrorMsg.value = 'Không thể xác định vị trí GPS hiện tại.';
                        break;
                    case error.TIMEOUT:
                        gpsErrorMsg.value = 'Hết thời gian yêu cầu lấy vị trí GPS.';
                        break;
                    default:
                        gpsErrorMsg.value = error.message || 'Lỗi định vị vị trí.';
                }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    } else {
        gpsStatus.value = 'success';
    }
});

onUnmounted(() => {
    stopWebcam();
});
</script>

<template>
    <div class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 print:hidden">
        <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
            <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                <div>
                    <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                        <Clock class="size-5" />
                        Xác Thực Vào Ca (Check-In)
                    </CardTitle>
                    <CardDescription>Hệ thống tự động xác minh vị trí địa lý (GPS) và ảnh selfie an toàn để ghi nhận ca trực.</CardDescription>
                </div>
                <button @click="closeCheckInFlow" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer">
                    <X class="size-4" />
                </button>
            </CardHeader>
            
            <CardContent class="pt-4 space-y-4">
                <!-- Webcam & Selfie Verification -->
                <div class="space-y-2">
                    <Label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Chụp ảnh Selfie Xác Minh</Label>
                    <div class="relative rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden bg-slate-950 flex flex-col items-center justify-center min-h-[200px]">
                        <!-- Loading/Error state -->
                        <div v-if="webcamError" class="absolute inset-0 flex flex-col items-center justify-center p-4 text-center text-xs text-rose-500">
                            <AlertCircle class="size-8 text-rose-500 mb-2 animate-bounce" />
                            <p class="font-bold text-wrap">{{ webcamError }}</p>
                            <Button type="button" size="sm" variant="outline" class="mt-3 text-xs" @click="startWebcam">Thử lại</Button>
                        </div>
                        
                        <!-- Video camera stream if no photo captured yet -->
                        <video 
                            v-if="!checkInPhoto && !webcamError" 
                            ref="videoRef" 
                            autoplay 
                            playsinline 
                            class="w-full h-48 object-cover"
                        ></video>
                        
                        <!-- Snapshot Preview if photo is captured -->
                        <img 
                            v-if="checkInPhoto" 
                            :src="checkInPhoto" 
                            class="w-full h-48 object-cover animate-in fade-in duration-200" 
                            alt="Selfie Check-In" 
                        />

                        <!-- Camera overlay action button -->
                        <div v-if="!webcamError" class="absolute bottom-2 left-0 right-0 flex justify-center gap-2">
                            <Button 
                                v-if="!checkInPhoto"
                                type="button" 
                                size="sm" 
                                @click="captureSelfie" 
                                class="bg-indigo-650 hover:bg-indigo-755 text-white font-bold shadow-sm flex items-center gap-1.5 text-[11px]"
                            >
                                <Clock class="size-3.5" />
                                Chụp ảnh Selfie
                            </Button>
                            <Button 
                                v-else 
                                type="button" 
                                size="sm" 
                                variant="outline" 
                                @click="retakeSelfie" 
                                class="bg-white/95 hover:bg-white text-slate-800 font-semibold shadow-sm flex items-center gap-1 text-[11px] border-none"
                            >
                                <RefreshCw class="size-3.5" />
                                Chụp lại ảnh
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- GPS Verification Status -->
                <div v-if="gpsSettings?.latitude && gpsSettings?.longitude" class="space-y-2">
                    <Label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Xác minh định vị GPS</Label>
                    <div 
                        class="p-3 border rounded-xl flex items-center gap-3 text-xs" 
                        :class="[
                            gpsStatus === 'fetching' ? 'bg-amber-50/50 border-amber-200 text-amber-600' : '',
                            gpsStatus === 'success' ? 'bg-emerald-50/55 border-emerald-200 text-emerald-600' : '',
                            gpsStatus === 'error' ? 'bg-rose-50/50 border-rose-200 text-rose-600' : ''
                        ]"
                    >
                        <span v-if="gpsStatus === 'fetching'" class="size-2 rounded-full bg-amber-500 animate-ping"></span>
                        <span v-if="gpsStatus === 'success'" class="size-2 rounded-full bg-emerald-600"></span>
                        <span v-if="gpsStatus === 'error'" class="size-2 rounded-full bg-rose-600"></span>

                        <div class="flex-1">
                            <p v-if="gpsStatus === 'fetching'" class="font-bold">Đang lấy tọa độ GPS của thiết bị...</p>
                            <p v-if="gpsStatus === 'success'" class="font-bold">Định vị GPS hợp lệ! (Kinh độ: {{ gpsCoords.longitude }}, Vĩ độ: {{ gpsCoords.latitude }})</p>
                            <p v-if="gpsStatus === 'error'" class="font-bold">Không thể xác minh định vị GPS</p>
                            <p v-if="gpsStatus === 'error'" class="text-[10px] text-rose-500 mt-0.5 font-medium">{{ gpsErrorMsg }}</p>
                        </div>
                    </div>
                </div>

                <!-- QR Code Input (if configured) -->
                <div v-if="qrSettings?.code && !qrSettings?.is_expired" class="space-y-2">
                    <Label for="qr-input" class="text-xs font-bold text-slate-500 uppercase tracking-wide">Nhập mã QR của ca trực hôm nay</Label>
                    <Input 
                        id="qr-input" 
                        type="text" 
                        v-model="inputQrCode" 
                        placeholder="Mã QR hiển thị trên bảng thông tin..."
                        required 
                        class="h-10 text-sm font-mono tracking-widest text-center" 
                    />
                </div>

                <div class="p-3 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-xl flex items-start gap-2 text-[10px] text-indigo-700 dark:text-indigo-400 border border-indigo-100/50">
                    <AlertCircle class="size-4 shrink-0 text-indigo-650 mt-0.5" />
                    <p><strong>Lưu ý:</strong> Vị trí GPS của bạn phải nằm trong bán kính {{ gpsSettings?.radius ?? 100 }}m của cửa hàng để có thể check-in thành công.</p>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-2 pt-2 border-t">
                    <Button type="button" variant="outline" size="sm" @click="closeCheckInFlow">Hủy</Button>
                    <Button 
                        type="button" 
                        size="sm" 
                        @click="submitCheckIn" 
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold"
                        :disabled="isCheckingIn || !checkInPhoto || gpsStatus !== 'success' || (qrSettings?.code && !qrSettings?.is_expired && !inputQrCode)"
                    >
                        {{ isCheckingIn ? 'Đang xác thực...' : 'Xác nhận Vào Ca' }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
