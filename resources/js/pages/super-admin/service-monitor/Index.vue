<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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
    Terminal,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    PageHeader,
    StatCard,
    LedIndicator,
    AlertBanner,
    ProgressBar,
} from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

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
    circuit_breaker_state: string | null;
}

const props = defineProps<{
    services: Service[];
}>();

const localServices = ref<Service[]>([...props.services]);
const isPinging = ref(false);

const showEditMessageDialog = ref(false);
const editingService = ref<Service | null>(null);
const maintenanceMessageText = ref('');
const isSavingMessage = ref(false);

async function checkLiveStatuses() {
    isPinging.value = true;
    const promise = fetch('/super-admin/service-monitor/ping', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN':
                (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement | null
                )?.content || '',
            'X-Requested-With': 'XMLHttpRequest',
        },
    }).then(async (res) => {
        if (!res.ok) {
            throw new Error('Yêu cầu thất bại');
        }

        const data = await res.json();

        if (data.success) {
            localServices.value = data.services;

            return 'Đã kiểm tra xong trạng thái các dịch vụ';
        }

        throw new Error(data.message || 'Lỗi không xác định');
    });

    toast.promise(promise, {
        loading: 'Đang gửi ping đến các cổng dịch vụ...',
        success: (msg: any) => msg,
        error: (err: any) => `Lỗi: ${err.message || err}`,
    });

    promise.finally(() => {
        isPinging.value = false;
    });
}

async function toggleMaintenance(service: Service) {
    const originalValue = service.is_maintenance;
    service.is_maintenance = !originalValue;

    try {
        const response = await fetch(
            `/super-admin/service-monitor/${service.service_key}/toggle-maintenance`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    is_maintenance: service.is_maintenance,
                    message: service.maintenance_message,
                }),
            },
        );
        const data = await response.json();

        if (!data.success) {
            throw new Error('Lỗi cập nhật');
        }

        toast.success(
            `Đã ${service.is_maintenance ? 'BẬT' : 'TẮT'} chế độ bảo trì cho ${service.name}`,
        );
    } catch {
        service.is_maintenance = originalValue;
        toast.error('Không thể cập nhật chế độ bảo trì.');
    }
}

function openEditMessageModal(service: Service) {
    editingService.value = service;
    maintenanceMessageText.value = service.maintenance_message || '';
    showEditMessageDialog.value = true;
}

async function saveMaintenanceMessage() {
    if (!editingService.value) {
        return;
    }

    isSavingMessage.value = true;
    const service = editingService.value;

    try {
        const response = await fetch(
            `/super-admin/service-monitor/${service.service_key}/update-message`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ message: maintenanceMessageText.value }),
            },
        );
        const data = await response.json();

        if (data.success) {
            const target = localServices.value.find(
                (s) => s.service_key === service.service_key,
            );

            if (target) {
                target.maintenance_message = maintenanceMessageText.value;
            }

            toast.success('Đã cập nhật thông báo bảo trì thành công');
            showEditMessageDialog.value = false;
        } else {
            throw new Error();
        }
    } catch {
        toast.error('Lỗi khi cập nhật thông báo.');
    } finally {
        isSavingMessage.value = false;
    }
}

async function resetCircuit(service: Service) {
    try {
        const response = await fetch(
            `/super-admin/service-monitor/${service.service_key}/reset-circuit-breaker`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        const data = await response.json();

        if (data.success) {
            service.circuit_breaker_state = 'closed';
            toast.success(`Đã khôi phục (Reset) mạch cho ${service.name}`);
        } else {
            throw new Error();
        }
    } catch {
        toast.error('Không thể reset Circuit Breaker.');
    }
}

function getLatencyColor(ms: number): string {
    if (ms <= 10) {
        return 'text-cyan-600 dark:text-cyan-400 font-bold';
    }

    if (ms <= 50) {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (ms <= 150) {
        return 'text-amber-600 dark:text-amber-400';
    }

    return 'text-rose-600 dark:text-rose-500 font-bold animate-pulse';
}

function getServiceLedStatus(
    service: Service,
): 'online' | 'offline' | 'warning' | 'maintenance' {
    if (service.is_maintenance) {
        return 'maintenance';
    }

    if (service.last_status === 'online') {
        return 'online';
    }

    return 'offline';
}

function formatTime(timeStr: string | null): string {
    if (!timeStr) {
        return 'Chưa kiểm tra';
    }

    const date = new Date(timeStr);

    return (
        date.toLocaleTimeString('vi-VN') +
        ' ' +
        date.toLocaleDateString('vi-VN')
    );
}
</script>

<template>
    <Head title="DevOps - Giám sát Dịch vụ" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Giám sát Dịch vụ & Microservices"
            subtitle="Theo dõi trạng thái cổng, đo độ trễ và quản lý chế độ bảo trì."
            :icon="Activity"
        >
            <template #actions>
                <Button
                    @click="checkLiveStatuses"
                    :disabled="isPinging"
                    class="gap-2"
                >
                    <RefreshCw
                        :class="['size-4', { 'animate-spin': isPinging }]"
                    />
                    Kiểm tra trạng thái ngay
                </Button>
                <a href="/status" target="_blank">
                    <Button variant="outline">Mở Status Page công khai</Button>
                </a>
            </template>
        </PageHeader>

        <!-- Alert banner when services are offline -->
        <AlertBanner
            v-if="
                localServices.some(
                    (s) => s.last_status === 'offline' && !s.is_maintenance,
                )
            "
            severity="critical"
            :title="`${localServices.filter((s) => s.last_status === 'offline' && !s.is_maintenance).length} dịch vụ đang gặp sự cố!`"
            message="Cần kiểm tra và khắc phục ngay lập tức."
            class=""
        />

        <!-- Stats -->
        <div class="grid gap-4 sm:grid-cols-3">
            <StatCard
                label="Hoạt động bình thường"
                :value="`${localServices.filter((s) => s.last_status === 'online' && !s.is_maintenance).length}/${localServices.length}`"
                :icon="CheckCircle"
                color="emerald"
                class=""
            />
            <StatCard
                label="Đang bảo trì"
                :value="localServices.filter((s) => s.is_maintenance).length"
                :icon="Hammer"
                color="amber"
                class=""
            />
            <StatCard
                label="Gặp sự cố (Offline)"
                :value="
                    localServices.filter(
                        (s) => s.last_status === 'offline' && !s.is_maintenance,
                    ).length
                "
                :icon="XCircle"
                color="rose"
                class=""
            />
        </div>

        <!-- Services Grid -->
        <div class="grid gap-6 lg:grid-cols-2">
            <Card
                v-for="(service, idx) in localServices"
                :key="service.id"
                class="flex flex-col justify-between overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-[var(--shadow-elevated)]"
                :style="{ animationDelay: `${idx * 50}ms` }"
            >
                <div>
                    <!-- Header -->
                    <div
                        class="flex items-start justify-between gap-4 border-b p-5"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-foreground">
                                    {{ service.name }}
                                </h3>
                                <span
                                    class="rounded border bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground"
                                >
                                    {{ service.service_key }}
                                </span>
                            </div>
                            <div
                                class="flex items-center gap-1.5 font-mono text-xs text-muted-foreground"
                            >
                                <Server class="size-3" />
                                <span
                                    >{{ service.host }}:{{ service.port }}</span
                                >
                            </div>
                        </div>

                        <LedIndicator
                            :status="getServiceLedStatus(service)"
                            :label="
                                service.is_maintenance
                                    ? 'Bảo trì'
                                    : service.last_status === 'online'
                                      ? 'Online'
                                      : 'Offline'
                            "
                            size="md"
                        />
                    </div>

                    <!-- Body -->
                    <div class="space-y-4 p-5 text-sm">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="rounded-xl border bg-muted/30 p-3">
                                <div
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Độ trễ (Latency)
                                </div>
                                <div class="mt-1 font-mono text-lg font-black">
                                    <span
                                        v-if="service.last_status === 'online'"
                                        :class="
                                            getLatencyColor(
                                                service.last_latency,
                                            )
                                        "
                                    >
                                        {{ service.last_latency }}ms
                                    </span>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </div>
                                <ProgressBar
                                    v-if="service.last_status === 'online'"
                                    :value="service.last_latency"
                                    :max="200"
                                    class="mt-2"
                                />
                            </div>
                            <div class="rounded-xl border bg-muted/30 p-3">
                                <div
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Lần cuối kiểm tra
                                </div>
                                <div
                                    class="mt-2 flex items-center gap-1 text-xs font-semibold text-foreground"
                                >
                                    <Clock
                                        class="size-3 text-muted-foreground"
                                    />
                                    {{ formatTime(service.last_checked_at) }}
                                </div>
                            </div>
                        </div>

                        <!-- Maintenance toggle -->
                        <div
                            class="flex items-center justify-between border-t pt-4"
                        >
                            <div class="space-y-0.5">
                                <span class="text-xs font-bold text-foreground"
                                    >Chế độ bảo trì</span
                                >
                                <p
                                    class="text-[11px] leading-relaxed text-muted-foreground"
                                >
                                    Tắt riêng lẻ tính năng này.
                                </p>
                            </div>
                            <label
                                class="relative inline-flex cursor-pointer items-center select-none"
                            >
                                <input
                                    type="checkbox"
                                    :checked="service.is_maintenance"
                                    class="peer sr-only"
                                    @change="toggleMaintenance(service)"
                                />
                                <div
                                    class="peer h-6 w-11 rounded-full bg-muted peer-checked:bg-amber-500 peer-focus:outline-none after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-border after:bg-background after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white"
                                />
                            </label>
                        </div>

                        <!-- Circuit Breaker Status (For microservices) -->
                        <div
                            v-if="
                                service.circuit_breaker_state !== undefined &&
                                service.circuit_breaker_state !== null
                            "
                            class="flex items-center justify-between border-t pt-4"
                        >
                            <div class="space-y-0.5">
                                <span class="text-xs font-bold text-foreground"
                                    >Circuit Breaker</span
                                >
                                <p
                                    class="text-[11px] leading-relaxed text-muted-foreground"
                                >
                                    Mạch ngắt kết nối microservice tự động.
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase',
                                        service.circuit_breaker_state ===
                                        'closed'
                                            ? 'text-emerald-450 border border-emerald-500/20 bg-emerald-500/10'
                                            : service.circuit_breaker_state ===
                                                'open'
                                              ? 'text-rose-450 animate-pulse border border-rose-500/20 bg-rose-500/10'
                                              : 'text-amber-450 border border-amber-500/20 bg-amber-500/10',
                                    ]"
                                >
                                    {{ service.circuit_breaker_state }}
                                </span>
                                <Button
                                    v-if="
                                        service.circuit_breaker_state !==
                                        'closed'
                                    "
                                    variant="outline"
                                    size="sm"
                                    class="text-slate-350 h-7 border-slate-700 px-2 text-[10px] hover:bg-slate-800"
                                    @click="resetCircuit(service)"
                                >
                                    Reset mạch
                                </Button>
                            </div>
                        </div>

                        <!-- Message area -->
                        <div
                            class="space-y-2.5 rounded-xl border bg-muted/20 p-4"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="flex items-center gap-1 text-[10px] font-bold text-muted-foreground uppercase"
                                >
                                    <MessageSquare class="size-3" />
                                    Thông báo cho người dùng
                                </span>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-6 rounded px-2 text-[10px] text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                    @click="openEditMessageModal(service)"
                                >
                                    Chỉnh sửa
                                </Button>
                            </div>
                            <p
                                class="text-xs leading-relaxed text-muted-foreground italic"
                            >
                                "{{
                                    service.maintenance_message ||
                                    'Không có thông báo riêng...'
                                }}"
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Error log footer -->
                <div
                    v-if="
                        service.last_status === 'offline' &&
                        service.error_message
                    "
                    class="border-t border-rose-500/20 bg-rose-500/[0.06] px-5 py-3.5"
                >
                    <div
                        class="mb-1 flex items-center gap-1 text-[10px] font-bold tracking-wider text-rose-600 uppercase dark:text-rose-400"
                    >
                        <Terminal class="size-3 animate-pulse text-rose-500" />
                        Nhật ký lỗi hệ thống
                    </div>
                    <div
                        class="font-mono text-[11px] leading-relaxed break-words text-rose-700 dark:text-rose-300/80"
                    >
                        {{ service.error_message }}
                    </div>
                </div>
            </Card>
        </div>
    </div>

    <!-- Edit Message Dialog -->
    <Dialog v-model:open="showEditMessageDialog">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Chỉnh sửa thông báo bảo trì</DialogTitle>
                <DialogDescription>
                    Thông báo này sẽ được hiển thị cho khách hàng khi phân hệ
                    bảo trì.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4 py-4">
                <div class="space-y-1.5">
                    <Label>Tên phân hệ</Label>
                    <Input
                        :value="editingService?.name"
                        disabled
                        class="cursor-not-allowed bg-muted text-muted-foreground"
                    />
                </div>
                <div class="space-y-1.5">
                    <Label>Nội dung thông báo</Label>
                    <textarea
                        v-model="maintenanceMessageText"
                        rows="4"
                        class="w-full rounded-lg border bg-background p-3 text-sm text-foreground transition-colors outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                        placeholder="Nhập thông báo hiển thị..."
                        maxlength="500"
                    />
                </div>
            </div>
            <DialogFooter class="gap-2">
                <Button variant="ghost" @click="showEditMessageDialog = false"
                    >Hủy</Button
                >
                <Button
                    @click="saveMaintenanceMessage"
                    :disabled="isSavingMessage"
                >
                    {{ isSavingMessage ? 'Đang lưu...' : 'Lưu thay đổi' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
