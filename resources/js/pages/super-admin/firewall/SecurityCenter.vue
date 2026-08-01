<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Globe,
    KeyRound,
    LogOut,
    RefreshCw,
    Shield,
    Users,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Session = {
    id: string;
    user_id: number | null;
    user_name: string;
    user_email?: string;
    ip_address: string;
    user_agent: string;
    last_activity: string;
};

type AlertRow = {
    id: number;
    severity: string;
    title: string;
    message: string;
    status: string;
    occurrences: number;
    last_seen_at: string;
};

type ApiKey = {
    id: number;
    name: string;
    key_prefix: string;
    restaurant_name?: string;
    is_active: boolean;
    last_used_at?: string;
    rotated_at?: string;
};

const props = defineProps<{
    sessions: Session[];
    blockedIps: Array<{
        ip: string;
        blocked_until: string;
        remaining_seconds: number;
    }>;
    threatHistory: Array<{ label: string; value: number }>;
    sessionDriver: string;
    loginHistory: Array<{
        id: number;
        user_name?: string;
        email?: string;
        status: string;
        ip_address?: string;
        user_agent?: string;
        occurred_at: string;
    }>;
    anomalousIps: Array<{
        email?: string;
        ip_address: string;
        first_seen_at: string;
        reason: string;
    }>;
    alerts: AlertRow[];
    apiKeys: ApiKey[];
    users: Array<{
        id: number;
        name: string;
        email: string;
        restaurant_name?: string;
        status: string;
    }>;
}>();

const localSessions = ref([...props.sessions]);
const localAlerts = ref([...props.alerts]);
const localApiKeys = ref([...props.apiKeys]);
const csrf = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
        ?.content || '';

async function postJson(
    url: string,
    method = 'POST',
    body?: Record<string, unknown>,
) {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message || 'Request failed');
    }

    return data;
}

async function revokeSession(session: Session) {
    try {
        await postJson(
            `/super-admin/security-center/sessions/${session.id}/revoke`,
        );
        localSessions.value = localSessions.value.filter(
            (item) => item.id !== session.id,
        );
        toast.success(`Đã hủy phiên của ${session.user_name}.`);
    } catch (error) {
        toast.error(
            error instanceof Error ? error.message : 'Không thể hủy phiên.',
        );
    }
}

async function forceLogoutUser(user: { id: number; email: string }) {
    if (!window.confirm(`Force logout tài khoản ${user.email}?`)) {
        return;
    }

    try {
        await postJson(
            `/super-admin/security-center/users/${user.id}/force-logout`,
        );
        localSessions.value = localSessions.value.filter(
            (session) => session.user_id !== user.id,
        );
        toast.success(`Đã thu hồi toàn bộ phiên của ${user.email}.`);
    } catch (error) {
        toast.error(
            error instanceof Error ? error.message : 'Không thể force logout.',
        );
    }
}

async function forceLogoutAll() {
    if (
        !window.confirm(
            'Force logout toàn hệ thống? Mọi tài khoản sẽ phải đăng nhập lại.',
        )
    ) {
        return;
    }

    try {
        await postJson('/super-admin/security-center/force-logout-all');
        localSessions.value = [];
        toast.success('Đã thu hồi toàn bộ phiên đăng nhập.');
    } catch (error) {
        toast.error(
            error instanceof Error
                ? error.message
                : 'Không thể force logout toàn hệ thống.',
        );
    }
}

async function rotateKey(key: ApiKey) {
    if (
        !window.confirm(
            `Rotation key ${key.name} của ${key.restaurant_name || 'tenant'}?`,
        )
    ) {
        return;
    }

    try {
        const data = await postJson(
            `/super-admin/security-center/api-keys/${key.id}/rotate`,
        );
        window.prompt(
            'API key mới — hãy sao chép ngay, key không hiển thị lại:',
            data.plain_key,
        );
        key.key_prefix = data.plain_key.slice(0, 12);
        key.is_active = true;
        key.rotated_at = new Date().toLocaleString('vi-VN');
        toast.success('Đã rotation API key.');
    } catch (error) {
        toast.error(
            error instanceof Error
                ? error.message
                : 'Không thể rotation API key.',
        );
    }
}

async function revokeKey(key: ApiKey) {
    if (!window.confirm(`Thu hồi API key ${key.name}?`)) {
        return;
    }

    try {
        await postJson(
            `/super-admin/security-center/api-keys/${key.id}/revoke`,
        );
        key.is_active = false;
        toast.success('Đã thu hồi API key.');
    } catch (error) {
        toast.error(
            error instanceof Error
                ? error.message
                : 'Không thể thu hồi API key.',
        );
    }
}

async function updateAlert(alert: AlertRow, action: 'acknowledge' | 'resolve') {
    try {
        await postJson(
            `/super-admin/security-center/alerts/${alert.id}/${action}`,
        );
        alert.status = action === 'resolve' ? 'resolved' : 'acknowledged';
        toast.success(
            action === 'resolve'
                ? 'Đã đóng cảnh báo.'
                : 'Đã xác nhận cảnh báo.',
        );
    } catch (error) {
        toast.error(
            error instanceof Error
                ? error.message
                : 'Không thể cập nhật cảnh báo.',
        );
    }
}

async function unblockIp(ip: string) {
    try {
        await postJson(`/super-admin/firewall/blocked/${ip}`, 'DELETE');
        toast.success(`Đã gỡ khóa IP ${ip}.`);
    } catch {
        toast.error('Không thể gỡ khóa IP.');
    }
}

const formatUserAgent = (ua?: string) => {
    if (!ua) {
        return 'Không rõ';
    }

    if (ua.includes('Chrome')) {
        return 'Google Chrome';
    }

    if (ua.includes('Firefox')) {
        return 'Mozilla Firefox';
    }

    if (ua.includes('Safari') && !ua.includes('Chrome')) {
        return 'Apple Safari';
    }

    return ua.length > 32 ? `${ua.slice(0, 32)}...` : ua;
};
</script>

<template>
    <Head title="Security Center" />

    <div class="space-y-6 p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Security Center
                </h1>
                <p class="text-muted-foreground">
                    Phiên đăng nhập, IP bất thường, API key và cảnh báo bảo mật.
                </p>
            </div>
            <Button variant="destructive" @click="forceLogoutAll">
                <LogOut class="mr-2 h-4 w-4" /> Force logout toàn hệ thống
            </Button>
        </div>

        <div
            v-if="sessionDriver !== 'database'"
            class="flex items-start gap-3 rounded-lg border border-amber-500/20 bg-amber-500/10 p-4 text-sm"
        >
            <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
            <span
                >SESSION_DRIVER hiện là <strong>{{ sessionDriver }}</strong
                >. Force logout vẫn hoạt động bằng session version, nhưng muốn
                xem từng session/IP chính xác nên dùng database.</span
            >
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <Card v-for="threat in threatHistory" :key="threat.label">
                <CardHeader class="pb-2"
                    ><CardDescription>{{ threat.label }}</CardDescription
                    ><CardTitle class="text-2xl">{{
                        threat.value
                    }}</CardTitle></CardHeader
                >
            </Card>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <Card>
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"
                        ><Users class="h-4 w-4" /> Phiên đang hoạt
                        động</CardTitle
                    ><CardDescription
                        >Hủy một session hoặc force logout tất cả session của
                        user.</CardDescription
                    ></CardHeader
                >
                <CardContent class="space-y-3">
                    <div
                        v-for="session in localSessions"
                        :key="session.id"
                        class="flex items-center justify-between gap-3 border-b pb-3 last:border-0"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">{{ session.user_name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ session.user_email }} ·
                                {{ session.ip_address }} ·
                                {{ formatUserAgent(session.user_agent) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ session.last_activity }}
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="shrink-0 text-rose-600"
                            @click="revokeSession(session)"
                            ><LogOut class="mr-1 h-3.5 w-3.5" /> Hủy</Button
                        >
                    </div>
                    <p
                        v-if="!localSessions.length"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        Không có session đang hoạt động.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle>Force logout theo tài khoản</CardTitle
                    ><CardDescription
                        >Thu hồi tất cả session của một user, kể cả session
                        không lưu trong database.</CardDescription
                    ></CardHeader
                >
                <CardContent class="max-h-80 space-y-2 overflow-auto">
                    <div
                        v-for="user in users"
                        :key="user.id"
                        class="flex items-center justify-between gap-3 rounded-md border p-2"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ user.name }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ user.email }} ·
                                {{ user.restaurant_name || 'Platform' }}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="forceLogoutUser(user)"
                            >Force logout</Button
                        >
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <Card>
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"
                        ><AlertTriangle class="h-4 w-4 text-amber-500" /> Cảnh
                        báo bảo mật</CardTitle
                    ><CardDescription
                        >Cảnh báo được deduplicate theo fingerprint và có trạng
                        thái xử lý.</CardDescription
                    ></CardHeader
                >
                <CardContent class="space-y-3">
                    <div
                        v-for="alert in localAlerts"
                        :key="alert.id"
                        class="rounded-lg border p-3"
                        :class="
                            alert.severity === 'critical'
                                ? 'border-rose-300 bg-rose-50/50 dark:bg-rose-950/20'
                                : ''
                        "
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">{{ alert.title }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ alert.message }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ alert.last_seen_at }} ·
                                    {{ alert.occurrences }} lần ·
                                    {{ alert.status }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <Button
                                    v-if="alert.status === 'open'"
                                    variant="ghost"
                                    size="sm"
                                    @click="updateAlert(alert, 'acknowledge')"
                                    >Xác nhận</Button
                                ><Button
                                    v-if="alert.status !== 'resolved'"
                                    variant="ghost"
                                    size="sm"
                                    @click="updateAlert(alert, 'resolve')"
                                    >Đóng</Button
                                >
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="!localAlerts.length"
                        class="py-5 text-center text-sm text-muted-foreground"
                    >
                        <CheckCircle2
                            class="mx-auto mb-2 h-7 w-7 text-emerald-500"
                        />Không có cảnh báo đang ghi nhận.
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"
                        ><Globe class="h-4 w-4 text-rose-500" /> IP bất thường &
                        WAF</CardTitle
                    ><CardDescription
                        >IP mới theo lịch sử login và IP đang bị
                        khóa.</CardDescription
                    ></CardHeader
                >
                <CardContent class="space-y-3">
                    <div
                        v-for="item in anomalousIps"
                        :key="`${item.email}-${item.ip_address}`"
                        class="rounded border border-amber-300 p-3 text-sm"
                    >
                        <p class="font-medium">
                            {{ item.email }} ·
                            <span class="font-mono">{{ item.ip_address }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ item.reason }} · {{ item.first_seen_at }}
                        </p>
                    </div>
                    <div
                        v-for="blocked in blockedIps"
                        :key="blocked.ip"
                        class="flex items-center justify-between rounded border p-3"
                    >
                        <div>
                            <p class="font-mono font-medium">
                                {{ blocked.ip }}
                            </p>
                            <p class="text-xs text-rose-600">
                                Khóa đến {{ blocked.blocked_until }}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="unblockIp(blocked.ip)"
                            >Gỡ khóa</Button
                        >
                    </div>
                    <p
                        v-if="!anomalousIps.length && !blockedIps.length"
                        class="py-5 text-center text-sm text-muted-foreground"
                    >
                        Không phát hiện IP bất thường.
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader
                ><CardTitle class="flex items-center gap-2"
                    ><KeyRound class="h-4 w-4" /> API keys platform</CardTitle
                ><CardDescription
                    >Chỉ hiển thị prefix. Rotation hiển thị secret mới đúng một
                    lần.</CardDescription
                ></CardHeader
            >
            <CardContent class="overflow-x-auto"
                ><table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-muted-foreground">
                            <th class="p-2">Key</th>
                            <th class="p-2">Tenant</th>
                            <th class="p-2">Trạng thái</th>
                            <th class="p-2">Sử dụng gần nhất</th>
                            <th class="p-2 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="key in localApiKeys"
                            :key="key.id"
                            class="border-b last:border-0"
                        >
                            <td class="p-2">
                                <p class="font-medium">{{ key.name }}</p>
                                <code class="text-xs"
                                    >{{ key.key_prefix }}…</code
                                >
                            </td>
                            <td class="p-2">
                                {{ key.restaurant_name || '—' }}
                            </td>
                            <td
                                class="p-2"
                                :class="
                                    key.is_active
                                        ? 'text-emerald-600'
                                        : 'text-rose-600'
                                "
                            >
                                {{ key.is_active ? 'Active' : 'Revoked' }}
                            </td>
                            <td class="p-2 text-xs text-muted-foreground">
                                {{ key.last_used_at || 'Chưa dùng' }}
                            </td>
                            <td class="p-2 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="rotateKey(key)"
                                    ><RefreshCw class="mr-1 h-3.5 w-3.5" />
                                    Rotate</Button
                                ><Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-rose-600"
                                    :disabled="!key.is_active"
                                    @click="revokeKey(key)"
                                    >Revoke</Button
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!localApiKeys.length"
                    class="py-5 text-center text-sm text-muted-foreground"
                >
                    Chưa có API key.
                </p></CardContent
            >
        </Card>

        <Card>
            <CardHeader
                ><CardTitle class="flex items-center gap-2"
                    ><Shield class="h-4 w-4" /> Lịch sử đăng nhập</CardTitle
                ><CardDescription
                    >Success và failed login được lưu kèm IP, user agent và thời
                    điểm.</CardDescription
                ></CardHeader
            >
            <CardContent class="overflow-x-auto"
                ><table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-muted-foreground">
                            <th class="p-2">Thời điểm</th>
                            <th class="p-2">Tài khoản</th>
                            <th class="p-2">Kết quả</th>
                            <th class="p-2">IP</th>
                            <th class="p-2">Thiết bị</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="event in loginHistory"
                            :key="event.id"
                            class="border-b last:border-0"
                        >
                            <td class="p-2 text-xs">{{ event.occurred_at }}</td>
                            <td class="p-2">
                                {{
                                    event.user_name || event.email || 'Unknown'
                                }}
                            </td>
                            <td
                                class="p-2"
                                :class="
                                    event.status === 'success'
                                        ? 'text-emerald-600'
                                        : 'text-rose-600'
                                "
                            >
                                {{ event.status }}
                            </td>
                            <td class="p-2 font-mono text-xs">
                                {{ event.ip_address || '—' }}
                            </td>
                            <td class="p-2 text-xs text-muted-foreground">
                                {{ formatUserAgent(event.user_agent) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!loginHistory.length"
                    class="py-5 text-center text-sm text-muted-foreground"
                >
                    Chưa có dữ liệu login event.
                </p></CardContent
            >
        </Card>
    </div>
</template>
