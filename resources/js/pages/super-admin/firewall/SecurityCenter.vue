<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Shield,
    Users,
    Globe,
    Terminal,
    Lock,
    LogOut,
    EyeOff,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    sessions: Array<{
        id: string;
        ip_address: string;
        user_agent: string;
        last_activity: string;
        user_name: string;
    }>;
    blockedIps: Array<{
        ip: string;
        blocked_until: string;
        remaining_seconds: number;
    }>;
    threatHistory: Array<{
        label: string;
        value: number;
    }>;
    sessionDriver: string;
}>();

const localSessions = ref([...props.sessions]);
const localBlockedIps = ref([...props.blockedIps]);

async function revokeSession(sessionId: string, userName: string) {
    try {
        const response = await fetch(
            `/super-admin/security-center/sessions/${sessionId}/revoke`,
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
            localSessions.value = localSessions.value.filter((s) => s.id !== sessionId);
            toast.success(`Đã hủy phiên làm việc của tài khoản "${userName}" thành công.`);
        } else {
            throw new Error();
        }
    } catch {
        toast.error('Lỗi khi hủy phiên đăng nhập.');
    }
}

async function unblockIp(ip: string) {
    try {
        const response = await fetch(
            `/super-admin/firewall/blocked/${ip}`,
            {
                method: 'DELETE',
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
        localBlockedIps.value = localBlockedIps.value.filter((b) => b.ip !== ip);
        toast.success(`Đã gỡ chặn địa chỉ IP ${ip} thành công.`);
    } catch {
        toast.error('Không thể gỡ chặn địa chỉ IP.');
    }
}

// Clean user agent format
const formatUserAgent = (ua: string) => {
    if (ua.includes('Chrome')) return 'Google Chrome';
    if (ua.includes('Firefox')) return 'Mozilla Firefox';
    if (ua.includes('Safari') && !ua.includes('Chrome')) return 'Apple Safari';
    if (ua.includes('Edge')) return 'Microsoft Edge';
    return ua.length > 30 ? ua.substring(0, 30) + '...' : ua;
};
</script>

<template>
    <Head title="Trung tâm Bảo mật & Phiên hoạt động" />

    <div class="space-y-6 p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Trung tâm Bảo mật & Phiên hoạt động</h1>
            <p class="text-muted-foreground">
                Quản lý các kết nối thời gian thực, phiên đăng nhập hoạt động và lịch sử ngăn chặn của Firewall WAF.
            </p>
        </div>

        <!-- Session Driver Alert -->
        <div v-if="sessionDriver !== 'database'" class="rounded-lg border border-amber-500/20 bg-amber-500/10 p-4 flex items-start space-x-3">
            <AlertTriangle class="h-5 w-5 text-amber-500 shrink-0 mt-0.5" />
            <div>
                <h4 class="text-sm font-semibold text-amber-200">Driver Session hiện tại là "{{ sessionDriver }}"</h4>
                <p class="text-xs text-amber-300/80 mt-1">
                    Để theo dõi chính xác địa chỉ IP, User Agent và quản lý hủy phiên làm việc từ xa của toàn bộ người dùng, bạn nên cấu hình <span class="font-mono bg-slate-900/60 px-1 rounded text-white">SESSION_DRIVER=database</span> trong tệp <span class="font-mono bg-slate-900/60 px-1 rounded text-white">.env</span>.
                </p>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Active Sessions -->
            <Card class="border-slate-800 bg-slate-900/50">
                <CardHeader>
                    <CardTitle class="flex items-center space-x-2 text-base">
                        <Users class="h-4 w-4 text-indigo-400" />
                        <span>Phiên đăng nhập đang hoạt động</span>
                    </CardTitle>
                    <CardDescription>Danh sách các tài khoản đang kết nối vào hệ thống.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-for="session in localSessions"
                        :key="session.id"
                        class="flex items-center justify-between border-b border-slate-800/80 pb-3 last:border-0 last:pb-0"
                    >
                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-white">{{ session.user_name }}</p>
                            <p class="text-xs text-slate-450 font-mono">
                                IP: {{ session.ip_address }} | {{ formatUserAgent(session.user_agent) }}
                            </p>
                            <p class="text-[10px] text-slate-450">
                                Hoạt động cuối: {{ session.last_activity }}
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 text-rose-400 hover:text-rose-300 hover:bg-rose-500/10"
                            @click="revokeSession(session.id, session.user_name)"
                        >
                            <LogOut class="h-3.5 w-3.5 mr-1" />
                            Hủy phiên
                        </Button>
                    </div>

                    <div v-if="localSessions.length === 0" class="py-6 text-center text-slate-500 text-sm">
                        Không ghi nhận phiên đăng nhập nào.
                    </div>
                </CardContent>
            </Card>

            <div class="space-y-6">
                <!-- Threat Statistics -->
                <Card class="border-slate-800 bg-slate-900/50">
                    <CardHeader>
                        <CardTitle class="flex items-center space-x-2 text-base">
                            <Shield class="h-4 w-4 text-emerald-400" />
                            <span>Thống kê đe dọa bị chặn (24h qua)</span>
                        </CardTitle>
                        <CardDescription>Số lượng sự cố xâm nhập bị WAF ngăn chặn tự động.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-for="threat in threatHistory" :key="threat.label" class="space-y-2">
                            <div class="flex items-center justify-between text-xs font-medium">
                                <span class="text-slate-350">{{ threat.label }}</span>
                                <span class="text-white font-mono font-bold">{{ threat.value }} lần</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-slate-800 overflow-hidden">
                                <div
                                    class="h-full bg-indigo-500"
                                    :style="{ width: `${Math.min(100, (threat.value / 40) * 100)}%` }"
                                ></div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Blocked IPs -->
                <Card class="border-slate-800 bg-slate-900/50">
                    <CardHeader>
                        <CardTitle class="flex items-center space-x-2 text-base">
                            <Globe class="h-4 w-4 text-rose-400" />
                            <span>Địa chỉ IP đang bị khóa</span>
                        </CardTitle>
                        <CardDescription>Các IP bị khóa tạm thời do vi phạm chính sách bảo mật hoặc nhập sai mật khẩu quá số lần.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-for="blocked in localBlockedIps"
                            :key="blocked.ip"
                            class="flex items-center justify-between border-b border-slate-800/80 pb-3 last:border-0"
                        >
                            <div class="space-y-0.5">
                                <p class="text-sm font-semibold font-mono text-white">{{ blocked.ip }}</p>
                                <p class="text-xs text-rose-400">
                                    Khóa đến: {{ blocked.blocked_until }}
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 border-slate-700 hover:bg-slate-800 text-slate-300"
                                @click="unblockIp(blocked.ip)"
                            >
                                Gỡ khóa IP
                            </Button>
                        </div>

                        <div v-if="localBlockedIps.length === 0" class="py-6 text-center text-slate-500 text-sm">
                            <CheckCircle2 class="h-8 w-8 text-emerald-500/20 mx-auto mb-2" />
                            <span>Không có địa chỉ IP nào bị khóa.</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
