<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import {
    Shield,
    Lock,
    Unlock,
    CheckCircle2,
    AlertTriangle,
    Globe,
    Send,
    Settings,
    Trash2,
    UserCheck,
    Key,
    Plus,
    Loader2,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { toast } from 'vue-sonner';
import { PageHeader } from '@/components/super-admin';
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
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface BlockedIp {
    ip: string;
    blocked_until: string;
    remaining_seconds: number;
}

const props = defineProps<{
    blockedIps: BlockedIp[];
    whitelist: string[];
    settings: {
        waf_login_max_attempts: number;
        waf_login_decay_seconds: number;
        waf_login_block_minutes: number;
        rate_limit_global_max: number;
        rate_limit_global_decay: number;
        telegram_bot_token: string;
        telegram_chat_id: string;
        turnstile_site_key: string;
        turnstile_secret_key: string;
    };
}>();

const page = usePage();
const activeTab = ref<'blocked' | 'whitelist' | 'config'>('blocked');
const addingWhitelist = ref(false);

// Toast alerts on flash messages
watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    },
);

// Whitelist Form
const whitelistForm = useForm({
    ip: '',
});

const submitWhitelist = () => {
    addingWhitelist.value = true;
    whitelistForm.post('/super-admin/firewall/whitelist', {
        preserveScroll: true,
        onSuccess: () => {
            whitelistForm.reset();
            toast.success('Đã thêm IP vào danh sách trắng.');
        },
        onFinish: () => {
            addingWhitelist.value = false;
        },
    });
};

// Remove from Whitelist
const removeWhitelist = async (ip: string) => {
    if (
        await confirmDialog({
            title: 'Xóa IP khỏi danh sách trắng',
            description: `Bạn có chắc chắn muốn xóa IP ${ip} khỏi danh sách trắng?`,
            variant: 'destructive',
        })
    ) {
        router.delete(`/super-admin/firewall/whitelist/${ip}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã xóa IP khỏi danh sách trắng.'),
        });
    }
};

// Unblock IP
const unblockIp = async (ip: string) => {
    if (
        await confirmDialog({
            title: 'Mở khóa IP',
            description: `Mở khóa truy cập cho IP ${ip}?`,
            variant: 'default',
        })
    ) {
        router.delete(`/super-admin/firewall/blocked/${ip}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã mở khóa IP thành công.'),
        });
    }
};

// Settings Form
const settingsForm = useForm({
    waf_login_max_attempts: props.settings.waf_login_max_attempts,
    waf_login_decay_seconds: props.settings.waf_login_decay_seconds,
    waf_login_block_minutes: props.settings.waf_login_block_minutes,
    rate_limit_global_max: props.settings.rate_limit_global_max,
    rate_limit_global_decay: props.settings.rate_limit_global_decay,
    telegram_bot_token: props.settings.telegram_bot_token,
    telegram_chat_id: props.settings.telegram_chat_id,
    turnstile_site_key: props.settings.turnstile_site_key,
    turnstile_secret_key: props.settings.turnstile_secret_key,
});

const submitSettings = () => {
    settingsForm.post('/super-admin/firewall/settings', {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã lưu cấu hình bảo mật thành công.');
        },
        onError: () => {
            toast.error('Lưu cấu hình thất bại. Vui lòng kiểm tra lại!');
        },
    });
};

// Format remaining time in seconds to human-readable string
const formatRemainingTime = (seconds: number) => {
    if (seconds <= 0) {
        return 'Hết hạn';
    }

    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;

    if (minutes > 0) {
        return `${minutes} phút ${secs} giây`;
    }

    return `${secs} giây`;
};
</script>

<template>
    <Head title="Quản trị Tường lửa (WAF) & Rate Limiting" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Quản trị Tường lửa (WAF) & Rate Limiting"
            subtitle="Giám sát lưu lượng truy cập mạng, các IP bị chặn do brute-force và cấu hình tường lửa ứng dụng."
            :icon="Shield"
        />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <!-- Left Navigation Tabs -->
            <div class="flex flex-col gap-2 lg:col-span-1">
                <button
                    type="button"
                    @click="activeTab = 'blocked'"
                    :class="[
                        'flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 text-left text-xs font-bold transition-all',
                        activeTab === 'blocked'
                            ? 'border-orange-500 bg-orange-500 text-white shadow-md shadow-orange-500/15'
                            : 'border-border/40 bg-card text-slate-700 hover:bg-muted/30 dark:text-slate-300',
                    ]"
                >
                    <Lock class="size-4 shrink-0" />
                    <span>IP đang bị khóa ({{ blockedIps.length }})</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'whitelist'"
                    :class="[
                        'flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 text-left text-xs font-bold transition-all',
                        activeTab === 'whitelist'
                            ? 'border-orange-500 bg-orange-500 text-white shadow-md shadow-orange-500/15'
                            : 'border-border/40 bg-card text-slate-700 hover:bg-muted/30 dark:text-slate-300',
                    ]"
                >
                    <UserCheck class="size-4 shrink-0" />
                    <span
                        >Danh sách trắng Whitelist ({{
                            whitelist.length
                        }})</span
                    >
                </button>

                <button
                    type="button"
                    @click="activeTab = 'config'"
                    :class="[
                        'flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 text-left text-xs font-bold transition-all',
                        activeTab === 'config'
                            ? 'border-orange-500 bg-orange-500 text-white shadow-md shadow-orange-500/15'
                            : 'border-border/40 bg-card text-slate-700 hover:bg-muted/30 dark:text-slate-300',
                    ]"
                >
                    <Settings class="size-4 shrink-0" />
                    <span>Cấu hình Firewall</span>
                </button>
            </div>

            <!-- Main Panel Content -->
            <div class="space-y-6 lg:col-span-4">
                <!-- Tab: Blocked IPs -->
                <Card
                    v-if="activeTab === 'blocked'"
                    class="rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <CardHeader class="border-b border-border/40 bg-muted/10">
                        <CardTitle
                            class="flex items-center gap-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-100"
                        >
                            <Lock class="size-5 text-red-500" /> Danh sách địa
                            chỉ IP bị khóa truy cập
                        </CardTitle>
                        <CardDescription
                            class="mt-0.5 text-[10px] font-semibold text-muted-foreground"
                        >
                            Các IP này đã spam thử mật khẩu sai vượt quá ngưỡng
                            quy định và bị khóa tạm thời.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            v-if="blockedIps.length === 0"
                            class="flex flex-col items-center justify-center px-4 py-16"
                        >
                            <div
                                class="mb-3 flex h-12 w-12 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/10 text-emerald-500"
                            >
                                <CheckCircle2 class="size-6" />
                            </div>
                            <h3
                                class="text-sm font-bold text-slate-800 dark:text-slate-200"
                            >
                                Hệ thống an toàn
                            </h3>
                            <p
                                class="mt-1 max-w-sm text-center text-xs font-medium text-muted-foreground"
                            >
                                Không phát hiện địa chỉ IP nào bị chặn tại thời
                                điểm này.
                            </p>
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table
                                class="w-full text-left text-xs font-semibold"
                            >
                                <thead
                                    class="border-b border-border/40 bg-muted/30 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="p-4">Địa chỉ IP</th>
                                        <th class="p-4">Bị chặn đến lúc</th>
                                        <th class="p-4">Thời gian còn lại</th>
                                        <th class="p-4 text-right">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/40">
                                    <tr
                                        v-for="ip in blockedIps"
                                        :key="ip.ip"
                                        class="transition-colors hover:bg-muted/20"
                                    >
                                        <td
                                            class="p-4 font-mono font-bold text-slate-900 dark:text-slate-100"
                                        >
                                            {{ ip.ip }}
                                        </td>
                                        <td class="p-4 text-muted-foreground">
                                            {{ ip.blocked_until }}
                                        </td>
                                        <td class="p-4">
                                            <span
                                                class="inline-flex items-center gap-1 rounded-lg border border-red-500/10 bg-red-500/5 px-2 py-0.5 font-mono text-red-500"
                                            >
                                                {{
                                                    formatRemainingTime(
                                                        ip.remaining_seconds,
                                                    )
                                                }}
                                            </span>
                                        </td>
                                        <td class="p-4 text-right">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="ml-auto flex h-8 cursor-pointer items-center gap-1.5 rounded-xl border-border/60 text-[10px] font-bold hover:border-emerald-500/30 hover:bg-emerald-500/10 hover:text-emerald-600"
                                                @click="unblockIp(ip.ip)"
                                            >
                                                <Unlock class="size-3.5" />
                                                <span>Mở khóa IP</span>
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Tab: IP Whitelist -->
                <div v-if="activeTab === 'whitelist'" class="space-y-6">
                    <!-- Add to Whitelist Form -->
                    <Card
                        class="rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                    >
                        <CardHeader class="p-5 pb-3">
                            <CardTitle
                                class="flex items-center gap-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-100"
                            >
                                <Plus class="size-4 text-orange-500" /> Thêm IP
                                vào danh sách trắng
                            </CardTitle>
                            <CardDescription
                                class="text-[10px] font-semibold text-muted-foreground"
                            >
                                Các địa chỉ IP trong danh sách trắng sẽ được bỏ
                                qua mọi quy định giới hạn tần suất (Rate
                                Limiting).
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-5 pt-0">
                            <form
                                @submit.prevent="submitWhitelist"
                                class="flex max-w-lg flex-col items-end gap-4 sm:flex-row"
                            >
                                <div class="grid w-full flex-1 gap-2">
                                    <Label
                                        for="whitelist-ip"
                                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                        >Địa chỉ IP (IPv4/IPv6)</Label
                                    >
                                    <Input
                                        id="whitelist-ip"
                                        type="text"
                                        v-model="whitelistForm.ip"
                                        placeholder="Ví dụ: 192.168.1.100"
                                        class="h-9 rounded-xl border-border bg-background font-mono text-xs font-semibold focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                        required
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    class="h-9 w-full shrink-0 cursor-pointer rounded-xl bg-orange-500 text-xs font-bold text-white hover:bg-orange-600 sm:w-auto"
                                    :disabled="addingWhitelist"
                                >
                                    <Loader2
                                        v-if="addingWhitelist"
                                        class="mr-1.5 size-4 animate-spin"
                                    />
                                    <span>Thêm vào danh sách</span>
                                </Button>
                            </form>
                            <p
                                v-if="whitelistForm.errors.ip"
                                class="mt-2 text-[10px] font-bold text-red-500"
                            >
                                {{ whitelistForm.errors.ip }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Whitelist Table -->
                    <Card
                        class="rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                    >
                        <CardHeader
                            class="border-b border-border/40 bg-muted/10"
                        >
                            <CardTitle
                                class="flex items-center gap-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-100"
                            >
                                <UserCheck class="size-5 text-emerald-500" />
                                Danh sách địa chỉ IP tin cậy
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div
                                v-if="whitelist.length === 0"
                                class="flex flex-col items-center justify-center px-4 py-12"
                            >
                                <Globe
                                    class="mb-2 size-8 text-muted-foreground/30"
                                />
                                <h3
                                    class="text-sm font-bold text-slate-800 dark:text-slate-200"
                                >
                                    Chưa có IP Whitelist
                                </h3>
                                <p
                                    class="mt-1 text-xs font-semibold text-muted-foreground"
                                >
                                    Tất cả truy cập hiện tại đều được giám sát.
                                </p>
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table
                                    class="w-full text-left text-xs font-semibold"
                                >
                                    <thead
                                        class="border-b border-border/40 bg-muted/30 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        <tr>
                                            <th class="p-4">Địa chỉ IP</th>
                                            <th class="p-4 text-right">
                                                Thao tác
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/40">
                                        <tr
                                            v-for="ip in whitelist"
                                            :key="ip"
                                            class="transition-colors hover:bg-muted/20"
                                        >
                                            <td
                                                class="flex items-center gap-2 p-4 font-mono font-bold text-slate-900 dark:text-slate-100"
                                            >
                                                <span
                                                    class="h-2 w-2 rounded-full bg-emerald-500 shadow-xs"
                                                />
                                                {{ ip }}
                                            </td>
                                            <td class="p-4 text-right">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    class="ml-auto flex h-8 cursor-pointer items-center gap-1.5 rounded-xl border-border/60 text-[10px] font-bold hover:border-red-500/30 hover:bg-red-500/10 hover:text-red-600"
                                                    @click="removeWhitelist(ip)"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                    <span>Xóa bỏ</span>
                                                </Button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Tab: Configurations -->
                <Card
                    v-if="activeTab === 'config'"
                    class="rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <CardHeader class="border-b border-border/40 bg-muted/10">
                        <CardTitle
                            class="flex items-center gap-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-100"
                        >
                            <Settings class="size-5 text-orange-500" /> Cấu hình
                            các tham số bảo mật
                        </CardTitle>
                        <CardDescription
                            class="mt-0.5 text-[10px] font-semibold text-muted-foreground"
                        >
                            Thay đổi các ngưỡng lọc và thiết lập tích hợp các
                            dịch vụ bảo mật bên thứ 3.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-6">
                        <form
                            @submit.prevent="submitSettings"
                            class="space-y-6"
                        >
                            <!-- Section: WAF Login -->
                            <div class="space-y-4">
                                <h3
                                    class="flex items-center gap-1.5 border-b border-border/30 pb-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                                >
                                    <Lock class="size-4 text-primary" /> Tường
                                    lửa đăng nhập (WAF Brute-Force)
                                </h3>
                                <div
                                    class="grid grid-cols-1 gap-5 md:grid-cols-3"
                                >
                                    <div class="grid gap-2">
                                        <Label
                                            for="waf-max-attempts"
                                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                            >Số lần thử tối đa</Label
                                        >
                                        <Input
                                            id="waf-max-attempts"
                                            type="number"
                                            v-model="
                                                settingsForm.waf_login_max_attempts
                                            "
                                            class="h-9 rounded-xl border-border bg-background text-xs font-semibold focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                            required
                                        />
                                        <span
                                            class="text-[9px] font-semibold text-muted-foreground"
                                            >Cho phép gõ sai tối đa bao nhiêu
                                            lần.</span
                                        >
                                    </div>
                                    <div class="grid gap-2">
                                        <Label
                                            for="waf-decay"
                                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                            >Khoảng thời gian (Giây)</Label
                                        >
                                        <Input
                                            id="waf-decay"
                                            type="number"
                                            v-model="
                                                settingsForm.waf_login_decay_seconds
                                            "
                                            class="h-9 rounded-xl border-border bg-background text-xs font-semibold focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                            required
                                        />
                                        <span
                                            class="text-[9px] font-semibold text-muted-foreground"
                                            >Thời gian cửa sổ để ghi nhận các
                                            lần sai.</span
                                        >
                                    </div>
                                    <div class="grid gap-2">
                                        <Label
                                            for="waf-block"
                                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                            >Thời gian khóa (Phút)</Label
                                        >
                                        <Input
                                            id="waf-block"
                                            type="number"
                                            v-model="
                                                settingsForm.waf_login_block_minutes
                                            "
                                            class="h-9 rounded-xl border-border bg-background text-xs font-semibold focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                            required
                                        />
                                        <span
                                            class="text-[9px] font-semibold text-muted-foreground"
                                            >Thời gian chặn kết nối IP vi
                                            phạm.</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Global Rate Limit -->
                            <div class="space-y-4">
                                <h3
                                    class="flex items-center gap-1.5 border-b border-border/30 pb-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                                >
                                    <Activity class="size-4 text-primary" />
                                    Giới hạn tần suất chung (Rate Limiting)
                                </h3>
                                <div
                                    class="grid grid-cols-1 gap-5 md:grid-cols-2"
                                >
                                    <div class="grid gap-2">
                                        <Label
                                            for="rl-max"
                                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                            >Số lượng request tối đa</Label
                                        >
                                        <Input
                                            id="rl-max"
                                            type="number"
                                            v-model="
                                                settingsForm.rate_limit_global_max
                                            "
                                            class="h-9 rounded-xl border-border bg-background text-xs font-semibold focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                            required
                                        />
                                        <span
                                            class="text-[9px] font-semibold text-muted-foreground"
                                            >Số request được phép thực hiện
                                            trong thời gian chu kỳ.</span
                                        >
                                    </div>
                                    <div class="grid gap-2">
                                        <Label
                                            for="rl-decay"
                                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                            >Chu kỳ giới hạn (Giây)</Label
                                        >
                                        <Input
                                            id="rl-decay"
                                            type="number"
                                            v-model="
                                                settingsForm.rate_limit_global_decay
                                            "
                                            class="h-9 rounded-xl border-border bg-background text-xs font-semibold focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                            required
                                        />
                                        <span
                                            class="text-[9px] font-semibold text-muted-foreground"
                                            >Mặc định là 60 giây (tạo ra giới
                                            hạn per minute).</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Turnstile -->
                            <div class="space-y-4">
                                <h3
                                    class="flex items-center gap-1.5 border-b border-border/30 pb-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                                >
                                    <Key class="size-4 text-primary" />
                                    Cloudflare Turnstile CAPTCHA (Tùy chọn)
                                </h3>
                                <div
                                    class="grid grid-cols-1 gap-5 md:grid-cols-2"
                                >
                                    <div class="grid gap-2">
                                        <Label
                                            for="ts-site"
                                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                            >Site Key</Label
                                        >
                                        <Input
                                            id="ts-site"
                                            type="text"
                                            v-model="
                                                settingsForm.turnstile_site_key
                                            "
                                            placeholder="Chưa cấu hình"
                                            class="h-9 rounded-xl border-border bg-background font-mono text-xs font-semibold focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                        />
                                        <span
                                            class="text-[9px] font-semibold text-muted-foreground"
                                            >Nếu để trống, hệ thống sẽ tự động
                                            dùng Math CAPTCHA.</span
                                        >
                                    </div>
                                    <div class="grid gap-2">
                                        <Label
                                            for="ts-secret"
                                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                            >Secret Key</Label
                                        >
                                        <Input
                                            id="ts-secret"
                                            type="password"
                                            v-model="
                                                settingsForm.turnstile_secret_key
                                            "
                                            placeholder="••••••••••••••••"
                                            class="h-9 rounded-xl border-border bg-background font-mono text-xs font-semibold focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                        />
                                        <span
                                            class="text-[9px] font-semibold text-muted-foreground"
                                            >Khóa bí mật dùng để xác thực token
                                            từ phía server.</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Telegram Alerting -->
                            <div class="space-y-4">
                                <h3
                                    class="flex items-center gap-1.5 border-b border-border/30 pb-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                                >
                                    <Send class="size-4 text-primary" /> Cảnh
                                    báo qua Telegram (Tùy chọn)
                                </h3>
                                <div
                                    class="grid grid-cols-1 gap-5 md:grid-cols-2"
                                >
                                    <div class="grid gap-2">
                                        <Label
                                            for="tg-token"
                                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                            >Telegram Bot Token</Label
                                        >
                                        <Input
                                            id="tg-token"
                                            type="password"
                                            v-model="
                                                settingsForm.telegram_bot_token
                                            "
                                            placeholder="••••••••••••••••"
                                            class="h-9 rounded-xl border-border bg-background font-mono text-xs font-semibold focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                        />
                                        <span
                                            class="text-[9px] font-semibold text-muted-foreground"
                                            >Mã token được cấp bởi
                                            @BotFather.</span
                                        >
                                    </div>
                                    <div class="grid gap-2">
                                        <Label
                                            for="tg-chat"
                                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                            >Telegram Chat ID</Label
                                        >
                                        <Input
                                            id="tg-chat"
                                            type="text"
                                            v-model="
                                                settingsForm.telegram_chat_id
                                            "
                                            placeholder="Ví dụ: -100123456789"
                                            class="h-9 rounded-xl border-border bg-background font-mono text-xs font-semibold focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                        />
                                        <span
                                            class="text-[9px] font-semibold text-muted-foreground"
                                            >ID của cuộc hội thoại hoặc nhóm
                                            nhận cảnh báo.</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div
                                class="flex items-center justify-end gap-3 border-t border-border/40 pt-5"
                            >
                                <Button
                                    type="submit"
                                    class="h-9 cursor-pointer rounded-xl bg-orange-500 px-5 text-xs font-bold text-white hover:bg-orange-600"
                                    :disabled="settingsForm.processing"
                                >
                                    <Loader2
                                        v-if="settingsForm.processing"
                                        class="mr-1.5 size-4 animate-spin"
                                    />
                                    <span>Lưu cấu hình</span>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
