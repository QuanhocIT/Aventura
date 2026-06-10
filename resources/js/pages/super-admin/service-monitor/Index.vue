<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    Activity,
    Settings,
    MessageSquare,
    RefreshCw,
    AlertTriangle,
    CheckCircle,
    XCircle,
    Server,
    Hammer,
    Clock,
    Terminal
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// Layout configuration
defineOptions({ layout: AppLayout });

interface Service {
    id: number;
    service_key: string;
    name: string;
    port: number;
    host: string;
    is_maintenance: boolean;
    maintenance_message: string | null;
    last_checked_at: string | null;
    last_status: string;
    last_latency: number;
    error_message: string | null;
}

const props = defineProps<{
    services: Service[];
}>();

const localServices = ref<Service[]>([...props.services]);
const isPinging = ref(false);

// Maintenance Message Dialog State
const showEditMessageDialog = ref(false);
const editingService = ref<Service | null>(null);
const maintenanceMessageText = ref('');
const isSavingMessage = ref(false);

async function checkLiveStatuses() {
    isPinging.value = true;
    toast.promise(
        fetch('/super-admin/service-monitor/ping', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(async (res) => {
            if (!res.ok) throw new Error('Yêu cầu thất bại');
            const data = await res.json();
            if (data.success) {
                localServices.value = data.services;
                return 'Đã kiểm tra xong trạng thái các dịch vụ';
            }
            throw new Error(data.message || 'Lỗi không xác định');
        }),
        {
            loading: 'Đang gửi ping đến các cổng dịch vụ...',
            success: (msg) => msg,
            error: (err) => `Lỗi: ${err.message}`
        }
    ).finally(() => {
        isPinging.value = false;
    });
}

async function toggleMaintenance(service: Service) {
    const originalValue = service.is_maintenance;
    service.is_maintenance = !originalValue; // optimistic UI update

    try {
        const response = await fetch(`/super-admin/service-monitor/${service.service_key}/toggle-maintenance`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                is_maintenance: service.is_maintenance,
                message: service.maintenance_message
            })
        });

        const data = await response.json();
        if (!data.success) {
            throw new Error('Lỗi cập nhật');
        }

        toast.success(
            `Đã ${service.is_maintenance ? 'BẬT' : 'TẮT'} chế độ bảo trì cho ${service.name}`
        );
    } catch {
        service.is_maintenance = originalValue; // revert on failure
        toast.error('Không thể cập nhật chế độ bảo trì. Vui lòng thử lại.');
    }
}

function openEditMessageModal(service: Service) {
    editingService.value = service;
    maintenanceMessageText.value = service.maintenance_message || '';
    showEditMessageDialog.value = true;
}

async function saveMaintenanceMessage() {
    if (!editingService.value) return;
    
    isSavingMessage.value = true;
    const service = editingService.value;

    try {
        const response = await fetch(`/super-admin/service-monitor/${service.service_key}/update-message`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                message: maintenanceMessageText.value
            })
        });

        const data = await response.json();
        if (data.success) {
            // Update local state
            const target = localServices.value.find(s => s.service_key === service.service_key);
            if (target) {
                target.maintenance_message = maintenanceMessageText.value;
            }
            toast.success('Đã cập nhật thông báo bảo trì thành công');
            showEditMessageDialog.value = false;
        } else {
            throw new Error();
        }
    } catch {
        toast.error('Lỗi khi cập nhật thông báo. Vui lòng thử lại.');
    } finally {
        isSavingMessage.value = false;
    }
}

function getLatencyColor(ms: number): string {
    if (ms <= 10) return 'text-cyan-400 font-bold';
    if (ms <= 50) return 'text-emerald-400';
    if (ms <= 150) return 'text-amber-400';
    return 'text-rose-500 font-bold animate-pulse';
}

function formatTime(timeStr: string | null): string {
    if (!timeStr) return 'Chưa kiểm tra';
    const date = new Date(timeStr);
    return date.toLocaleTimeString('vi-VN') + ' ' + date.toLocaleDateString('vi-VN');
}
</script>

<template>
    <Head title="DevOps - Giám sát Dịch vụ" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full font-sans text-zinc-100">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4 border-b pb-5 border-zinc-800">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-zinc-50 via-zinc-200 to-zinc-400 bg-clip-text text-transparent">
                    Giám sát Dịch vụ &amp; Microservices
                </h1>
                <p class="text-sm text-zinc-400 flex items-center gap-1.5 mt-1">
                    <Activity class="size-4 text-indigo-500 animate-pulse" />
                    Theo dõi trạng thái cổng, đo độ trễ và quản lý chế độ bảo trì riêng biệt.
                </p>
            </div>
            
            <div class="flex gap-2">
                <Button 
                    @click="checkLiveStatuses" 
                    :disabled="isPinging" 
                    class="bg-indigo-600 hover:bg-indigo-500 text-white gap-2 rounded-xl transition-all shadow-lg shadow-indigo-600/20 active:scale-[0.98]"
                >
                    <RefreshCw :class="['size-4', { 'animate-spin': isPinging }]" />
                    Kiểm tra trạng thái ngay
                </Button>
                <a 
                    href="/status" 
                    target="_blank"
                    class="inline-flex items-center justify-center rounded-xl border border-zinc-800 px-4 py-2 text-xs font-semibold text-zinc-300 hover:bg-zinc-800 transition-all cursor-pointer"
                >
                    Mở Status Page công khai
                </a>
            </div>
        </div>

        <!-- Quick Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Card class="bg-zinc-950/40 border-zinc-900 backdrop-blur-md">
                <CardHeader class="pb-3 flex flex-row items-center justify-between">
                    <CardTitle class="text-xs font-bold uppercase tracking-wider text-zinc-500">Hoạt động bình thường</CardTitle>
                    <CheckCircle class="size-5 text-emerald-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black">
                        {{ localServices.filter(s => s.last_status === 'online' && !s.is_maintenance).length }}
                        <span class="text-sm font-medium text-zinc-500">/ {{ localServices.length }} dịch vụ</span>
                    </div>
                </CardContent>
            </Card>

            <Card class="bg-zinc-950/40 border-zinc-900 backdrop-blur-md">
                <CardHeader class="pb-3 flex flex-row items-center justify-between">
                    <CardTitle class="text-xs font-bold uppercase tracking-wider text-zinc-500">Đang bảo trì (Maintenance)</CardTitle>
                    <Hammer class="size-5 text-amber-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black text-amber-500">
                        {{ localServices.filter(s => s.is_maintenance).length }}
                        <span class="text-sm font-medium text-zinc-500">phân hệ tạm dừng</span>
                    </div>
                </CardContent>
            </Card>

            <Card class="bg-zinc-950/40 border-zinc-900 backdrop-blur-md">
                <CardHeader class="pb-3 flex flex-row items-center justify-between">
                    <CardTitle class="text-xs font-bold uppercase tracking-wider text-zinc-500">Gặp sự cố (Offline)</CardTitle>
                    <XCircle class="size-5 text-rose-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-black text-rose-500">
                        {{ localServices.filter(s => s.last_status === 'offline' && !s.is_maintenance).length }}
                        <span class="text-sm font-medium text-zinc-500">dịch vụ gián đoạn</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Services Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-2">
            <Card 
                v-for="service in localServices" 
                :key="service.id" 
                class="bg-zinc-950/40 border-zinc-900 backdrop-blur-md overflow-hidden hover:border-zinc-800 transition-all flex flex-col justify-between"
            >
                <div>
                    <!-- Card Header / Status Indicator -->
                    <div class="p-5 border-b border-zinc-900 flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-base text-zinc-100">{{ service.name }}</h3>
                                <span class="bg-zinc-900 text-zinc-400 border border-zinc-800 rounded px-1.5 py-0.5 text-[10px] font-mono">
                                    {{ service.service_key }}
                                </span>
                            </div>
                            <div class="text-xs text-zinc-500 flex items-center gap-1.5 font-mono">
                                <Server class="size-3" />
                                <span>{{ service.host }}:{{ service.port }}</span>
                            </div>
                        </div>

                        <!-- Status badge with pulsing glow effect -->
                        <div class="flex items-center gap-2">
                            <span v-if="service.is_maintenance" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/20 shadow-md shadow-amber-500/5">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-500 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                </span>
                                Bảo trì
                            </span>
                            <span v-else-if="service.last_status === 'online'" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shadow-md shadow-emerald-500/5">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
                                </span>
                                Online
                            </span>
                            <span v-else class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-500 border border-rose-500/20 shadow-md shadow-rose-500/5">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-500 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                                </span>
                                Offline
                            </span>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-5 space-y-4 text-sm">
                        <!-- Metrics info -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-zinc-900/30 border border-zinc-900 rounded-xl p-3">
                                <div class="text-[10px] uppercase font-bold text-zinc-500 tracking-wider">Độ trễ (Latency)</div>
                                <div class="text-lg font-black mt-1 font-mono">
                                    <span v-if="service.last_status === 'online'" :class="getLatencyColor(service.last_latency)">
                                        {{ service.last_latency }}ms
                                    </span>
                                    <span v-else class="text-zinc-600">—</span>
                                </div>
                            </div>
                            
                            <div class="bg-zinc-900/30 border border-zinc-900 rounded-xl p-3">
                                <div class="text-[10px] uppercase font-bold text-zinc-500 tracking-wider">Lần cuối kiểm tra</div>
                                <div class="text-xs font-semibold text-zinc-300 mt-2 flex items-center gap-1">
                                    <Clock class="size-3 text-zinc-500" />
                                    {{ formatTime(service.last_checked_at) }}
                                </div>
                            </div>
                        </div>

                        <!-- Maintenance Mode Switch -->
                        <div class="flex items-center justify-between border-t border-zinc-900 pt-4">
                            <div class="space-y-0.5">
                                <span class="text-xs font-bold text-zinc-300">Chế độ bảo trì (Maintenance Mode)</span>
                                <p class="text-[11px] text-zinc-500 leading-relaxed">
                                    Tắt riêng lẻ tính năng này mà không cần đóng ứng dụng Laravel.
                                </p>
                            </div>
                            
                            <!-- Custom Toggle Switch -->
                            <label class="relative inline-flex items-center cursor-pointer select-none">
                                <input 
                                    type="checkbox" 
                                    :checked="service.is_maintenance" 
                                    @change="toggleMaintenance(service)" 
                                    class="sr-only peer"
                                >
                                <div class="w-11 h-6 bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-zinc-100 after:border-zinc-300 after:border after:rounded-full after:height-5 after:width-5 after:transition-all peer-checked:bg-amber-500"></div>
                            </label>
                        </div>

                        <!-- Custom Warning Message Area -->
                        <div class="bg-zinc-900/20 border border-zinc-900/60 rounded-xl p-4 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] uppercase font-bold text-zinc-400 flex items-center gap-1">
                                    <MessageSquare class="size-3" />
                                    Thông báo hiển thị cho người dùng
                                </span>
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    @click="openEditMessageModal(service)"
                                    class="h-6 text-[10px] text-indigo-400 hover:text-indigo-300 hover:bg-zinc-800 rounded px-2"
                                >
                                    Chỉnh sửa
                                </Button>
                            </div>
                            <p class="text-xs text-zinc-400 italic leading-relaxed">
                                "{{ service.maintenance_message || 'Không có thông báo riêng...' }}"
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Footer error log if offline -->
                <div 
                    v-if="service.last_status === 'offline' && service.error_message" 
                    class="bg-rose-950/15 border-t border-zinc-900 px-5 py-3.5"
                >
                    <div class="text-[10px] uppercase font-bold text-rose-500 tracking-wider flex items-center gap-1 mb-1">
                        <Terminal class="size-3 text-rose-500 animate-pulse" />
                        Nhật ký lỗi hệ thống
                    </div>
                    <div class="text-[11px] font-mono text-rose-300/80 break-words leading-relaxed">
                        {{ service.error_message }}
                    </div>
                </div>
            </Card>
        </div>

        <!-- Custom Edit Message Dialog -->
        <Dialog v-model:open="showEditMessageDialog">
            <DialogContent class="bg-zinc-950 border-zinc-800 text-zinc-100 max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-lg font-bold">Chỉnh sửa thông báo bảo trì</DialogTitle>
                    <DialogDescription class="text-xs text-zinc-400">
                        Thông báo này sẽ được hiển thị trực tiếp cho khách hàng (tenants) khi phân hệ tương ứng bị ngắt hoặc đưa vào chế độ bảo trì.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-bold text-zinc-400">Tên phân hệ</Label>
                        <Input 
                            :value="editingService?.name" 
                            disabled 
                            class="bg-zinc-900 border-zinc-800 text-zinc-500 cursor-not-allowed rounded-lg"
                        />
                    </div>
                    
                    <div class="space-y-1.5">
                        <Label for="warning-text" class="text-xs font-bold text-zinc-300">Nội dung thông báo hiển thị</Label>
                        <textarea
                            id="warning-text"
                            v-model="maintenanceMessageText"
                            rows="4"
                            class="w-full bg-zinc-900 border border-zinc-800 rounded-lg p-3 text-sm text-zinc-200 outline-none focus:border-indigo-500 transition-colors"
                            placeholder="Nhập thông báo hiển thị..."
                            maxlength="500"
                        ></textarea>
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button 
                        variant="ghost" 
                        @click="showEditMessageDialog = false"
                        class="text-zinc-400 hover:text-zinc-300 hover:bg-zinc-900 rounded-xl"
                    >
                        Hủy
                    </Button>
                    <Button 
                        @click="saveMaintenanceMessage" 
                        :disabled="isSavingMessage"
                        class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl"
                    >
                        {{ isSavingMessage ? 'Đang lưu...' : 'Lưu thay đổi' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
