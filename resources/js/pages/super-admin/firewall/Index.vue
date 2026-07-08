<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import {
    Shield, Lock, Unlock, CheckCircle2, AlertTriangle, Globe, Send,
    Settings, Trash2, UserCheck, Key, Plus, Loader2
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/components/super-admin';
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
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
});

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
        }
    });
};

// Remove from Whitelist
const removeWhitelist = (ip: string) => {
    if (confirm(`Bạn có chắc chắn muốn xóa IP ${ip} khỏi danh sách trắng?`)) {
        router.delete(`/super-admin/firewall/whitelist/${ip}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã xóa IP khỏi danh sách trắng.')
        });
    }
};

// Unblock IP
const unblockIp = (ip: string) => {
    if (confirm(`Mở khóa truy cập cho IP ${ip}?`)) {
        router.delete(`/super-admin/firewall/blocked/${ip}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã mở khóa IP thành công.')
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
        }
    });
};

// Format remaining time in seconds to human-readable string
const formatRemainingTime = (seconds: number) => {
    if (seconds <= 0) return 'Hết hạn';
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

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <!-- Left Navigation Tabs -->
            <div class="lg:col-span-1 flex flex-col gap-2">
                <button
                    type="button"
                    @click="activeTab = 'blocked'"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all text-left border cursor-pointer',
                        activeTab === 'blocked'
                            ? 'bg-orange-500 text-white border-orange-500 shadow-md shadow-orange-500/15'
                            : 'bg-card hover:bg-muted/30 border-border/40 text-slate-700 dark:text-slate-300'
                    ]"
                >
                    <Lock class="size-4 shrink-0" />
                    <span>IP đang bị khóa ({{ blockedIps.length }})</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'whitelist'"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all text-left border cursor-pointer',
                        activeTab === 'whitelist'
                            ? 'bg-orange-500 text-white border-orange-500 shadow-md shadow-orange-500/15'
                            : 'bg-card hover:bg-muted/30 border-border/40 text-slate-700 dark:text-slate-300'
                    ]"
                >
                    <UserCheck class="size-4 shrink-0" />
                    <span>Danh sách trắng Whitelist ({{ whitelist.length }})</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'config'"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all text-left border cursor-pointer',
                        activeTab === 'config'
                            ? 'bg-orange-500 text-white border-orange-500 shadow-md shadow-orange-500/15'
                            : 'bg-card hover:bg-muted/30 border-border/40 text-slate-700 dark:text-slate-300'
                    ]"
                >
                    <Settings class="size-4 shrink-0" />
                    <span>Cấu hình Firewall</span>
                </button>
            </div>

            <!-- Main Panel Content -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Tab: Blocked IPs -->
                <Card v-if="activeTab === 'blocked'" class="border border-border/40 bg-card/45 backdrop-blur-md rounded-2xl shadow-2xs">
                    <CardHeader class="border-b border-border/40 bg-muted/10">
                        <CardTitle class="text-xs font-black uppercase tracking-wider flex items-center gap-2 text-slate-800 dark:text-slate-100">
                            <Lock class="size-5 text-red-500" /> Danh sách địa chỉ IP bị khóa truy cập
                        </CardTitle>
                        <CardDescription class="text-[10px] font-semibold text-muted-foreground mt-0.5">
                            Các IP này đã spam thử mật khẩu sai vượt quá ngưỡng quy định và bị khóa tạm thời.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="blockedIps.length === 0" class="flex flex-col items-center justify-center py-16 px-4">
                            <div class="h-12 w-12 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-500 mb-3 border border-emerald-500/20">
                                <CheckCircle2 class="size-6" />
                            </div>
                            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Hệ thống an toàn</h3>
                            <p class="text-xs text-muted-foreground mt-1 max-w-sm text-center font-medium">Không phát hiện địa chỉ IP nào bị chặn tại thời điểm này.</p>
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left text-xs font-semibold">
                                <thead class="bg-muted/30 border-b border-border/40 text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                    <tr>
                                        <th class="p-4">Địa chỉ IP</th>
                                        <th class="p-4">Bị chặn đến lúc</th>
                                        <th class="p-4">Thời gian còn lại</th>
                                        <th class="p-4 text-right">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/40">
                                    <tr v-for="ip in blockedIps" :key="ip.ip" class="hover:bg-muted/20 transition-colors">
                                        <td class="p-4 font-mono font-bold text-slate-900 dark:text-slate-100">{{ ip.ip }}</td>
                                        <td class="p-4 text-muted-foreground">{{ ip.blocked_until }}</td>
                                        <td class="p-4">
                                            <span class="inline-flex items-center gap-1 text-red-500 font-mono bg-red-500/5 px-2 py-0.5 rounded-lg border border-red-500/10">
                                                {{ formatRemainingTime(ip.remaining_seconds) }}
                                            </span>
                                        </td>
                                        <td class="p-4 text-right">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="rounded-xl h-8 text-[10px] font-bold border-border/60 hover:bg-emerald-500/10 hover:text-emerald-600 hover:border-emerald-500/30 flex items-center gap-1.5 cursor-pointer ml-auto"
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
                    <Card class="border border-border/40 bg-card/45 backdrop-blur-md rounded-2xl shadow-2xs">
                        <CardHeader class="p-5 pb-3">
                            <CardTitle class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-100 flex items-center gap-2">
                                <Plus class="size-4 text-orange-500" /> Thêm IP vào danh sách trắng
                            </CardTitle>
                            <CardDescription class="text-[10px] font-semibold text-muted-foreground">
                                Các địa chỉ IP trong danh sách trắng sẽ được bỏ qua mọi quy định giới hạn tần suất (Rate Limiting).
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-5 pt-0">
                            <form @submit.prevent="submitWhitelist" class="flex flex-col sm:flex-row items-end gap-4 max-w-lg">
                                <div class="grid gap-2 flex-1 w-full">
                                    <Label for="whitelist-ip" class="text-xs font-bold text-slate-700 dark:text-slate-300">Địa chỉ IP (IPv4/IPv6)</Label>
                                    <Input
                                        id="whitelist-ip"
                                        type="text"
                                        v-model="whitelistForm.ip"
                                        placeholder="Ví dụ: 192.168.1.100"
                                        class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 font-semibold font-mono"
                                        required
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    class="rounded-xl h-9 text-xs font-bold bg-orange-500 hover:bg-orange-600 text-white shrink-0 cursor-pointer w-full sm:w-auto"
                                    :disabled="addingWhitelist"
                                >
                                    <Loader2 v-if="addingWhitelist" class="size-4 animate-spin mr-1.5" />
                                    <span>Thêm vào danh sách</span>
                                </Button>
                            </form>
                            <p v-if="whitelistForm.errors.ip" class="text-[10px] text-red-500 font-bold mt-2">{{ whitelistForm.errors.ip }}</p>
                        </CardContent>
                    </Card>

                    <!-- Whitelist Table -->
                    <Card class="border border-border/40 bg-card/45 backdrop-blur-md rounded-2xl shadow-2xs">
                        <CardHeader class="border-b border-border/40 bg-muted/10">
                            <CardTitle class="text-xs font-black uppercase tracking-wider flex items-center gap-2 text-slate-800 dark:text-slate-100">
                                <UserCheck class="size-5 text-emerald-500" /> Danh sách địa chỉ IP tin cậy
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div v-if="whitelist.length === 0" class="flex flex-col items-center justify-center py-12 px-4">
                                <Globe class="size-8 text-muted-foreground/30 mb-2" />
                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Chưa có IP Whitelist</h3>
                                <p class="text-xs text-muted-foreground mt-1 font-semibold">Tất cả truy cập hiện tại đều được giám sát.</p>
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="w-full text-left text-xs font-semibold">
                                    <thead class="bg-muted/30 border-b border-border/40 text-muted-foreground text-[10px] font-bold uppercase tracking-wider">
                                        <tr>
                                            <th class="p-4">Địa chỉ IP</th>
                                            <th class="p-4 text-right">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/40">
                                        <tr v-for="ip in whitelist" :key="ip" class="hover:bg-muted/20 transition-colors">
                                            <td class="p-4 font-mono font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                                <span class="h-2 w-2 rounded-full bg-emerald-500 shadow-xs" />
                                                {{ ip }}
                                            </td>
                                            <td class="p-4 text-right">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    class="rounded-xl h-8 text-[10px] font-bold border-border/60 hover:bg-red-500/10 hover:text-red-600 hover:border-red-500/30 flex items-center gap-1.5 cursor-pointer ml-auto"
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
                <Card v-if="activeTab === 'config'" class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs rounded-2xl">
                    <CardHeader class="border-b border-border/40 bg-muted/10">
                        <CardTitle class="text-xs font-black uppercase tracking-wider flex items-center gap-2 text-slate-800 dark:text-slate-100">
                            <Settings class="size-5 text-orange-500" /> Cấu hình các tham số bảo mật
                        </CardTitle>
                        <CardDescription class="text-[10px] font-semibold text-muted-foreground mt-0.5">
                            Thay đổi các ngưỡng lọc và thiết lập tích hợp các dịch vụ bảo mật bên thứ 3.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-6">
                        <form @submit.prevent="submitSettings" class="space-y-6">
                            <!-- Section: WAF Login -->
                            <div class="space-y-4">
                                <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5 border-b pb-2 border-border/30">
                                    <Lock class="size-4 text-primary" /> Tường lửa đăng nhập (WAF Brute-Force)
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div class="grid gap-2">
                                        <Label for="waf-max-attempts" class="text-xs font-bold text-slate-700 dark:text-slate-300">Số lần thử tối đa</Label>
                                        <Input
                                            id="waf-max-attempts"
                                            type="number"
                                            v-model="settingsForm.waf_login_max_attempts"
                                            class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 font-semibold"
                                            required
                                        />
                                        <span class="text-[9px] text-muted-foreground font-semibold">Cho phép gõ sai tối đa bao nhiêu lần.</span>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="waf-decay" class="text-xs font-bold text-slate-700 dark:text-slate-300">Khoảng thời gian (Giây)</Label>
                                        <Input
                                            id="waf-decay"
                                            type="number"
                                            v-model="settingsForm.waf_login_decay_seconds"
                                            class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 font-semibold"
                                            required
                                        />
                                        <span class="text-[9px] text-muted-foreground font-semibold">Thời gian cửa sổ để ghi nhận các lần sai.</span>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="waf-block" class="text-xs font-bold text-slate-700 dark:text-slate-300">Thời gian khóa (Phút)</Label>
                                        <Input
                                            id="waf-block"
                                            type="number"
                                            v-model="settingsForm.waf_login_block_minutes"
                                            class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 font-semibold"
                                            required
                                        />
                                        <span class="text-[9px] text-muted-foreground font-semibold">Thời gian chặn kết nối IP vi phạm.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Global Rate Limit -->
                            <div class="space-y-4">
                                <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5 border-b pb-2 border-border/30">
                                    <Activity class="size-4 text-primary" /> Giới hạn tần suất chung (Rate Limiting)
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div class="grid gap-2">
                                        <Label for="rl-max" class="text-xs font-bold text-slate-700 dark:text-slate-300">Số lượng request tối đa</Label>
                                        <Input
                                            id="rl-max"
                                            type="number"
                                            v-model="settingsForm.rate_limit_global_max"
                                            class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 font-semibold"
                                            required
                                        />
                                        <span class="text-[9px] text-muted-foreground font-semibold">Số request được phép thực hiện trong thời gian chu kỳ.</span>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="rl-decay" class="text-xs font-bold text-slate-700 dark:text-slate-300">Chu kỳ giới hạn (Giây)</Label>
                                        <Input
                                            id="rl-decay"
                                            type="number"
                                            v-model="settingsForm.rate_limit_global_decay"
                                            class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 font-semibold"
                                            required
                                        />
                                        <span class="text-[9px] text-muted-foreground font-semibold">Mặc định là 60 giây (tạo ra giới hạn per minute).</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Turnstile -->
                            <div class="space-y-4">
                                <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5 border-b pb-2 border-border/30">
                                    <Key class="size-4 text-primary" /> Cloudflare Turnstile CAPTCHA (Tùy chọn)
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div class="grid gap-2">
                                        <Label for="ts-site" class="text-xs font-bold text-slate-700 dark:text-slate-300">Site Key</Label>
                                        <Input
                                            id="ts-site"
                                            type="text"
                                            v-model="settingsForm.turnstile_site_key"
                                            placeholder="Chưa cấu hình"
                                            class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 font-semibold font-mono"
                                        />
                                        <span class="text-[9px] text-muted-foreground font-semibold">Nếu để trống, hệ thống sẽ tự động dùng Math CAPTCHA.</span>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="ts-secret" class="text-xs font-bold text-slate-700 dark:text-slate-300">Secret Key</Label>
                                        <Input
                                            id="ts-secret"
                                            type="password"
                                            v-model="settingsForm.turnstile_secret_key"
                                            placeholder="••••••••••••••••"
                                            class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 font-semibold font-mono"
                                        />
                                        <span class="text-[9px] text-muted-foreground font-semibold">Khóa bí mật dùng để xác thực token từ phía server.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Telegram Alerting -->
                            <div class="space-y-4">
                                <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5 border-b pb-2 border-border/30">
                                    <Send class="size-4 text-primary" /> Cảnh báo qua Telegram (Tùy chọn)
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div class="grid gap-2">
                                        <Label for="tg-token" class="text-xs font-bold text-slate-700 dark:text-slate-300">Telegram Bot Token</Label>
                                        <Input
                                            id="tg-token"
                                            type="password"
                                            v-model="settingsForm.telegram_bot_token"
                                            placeholder="••••••••••••••••"
                                            class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 font-semibold font-mono"
                                        />
                                        <span class="text-[9px] text-muted-foreground font-semibold">Mã token được cấp bởi @BotFather.</span>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="tg-chat" class="text-xs font-bold text-slate-700 dark:text-slate-300">Telegram Chat ID</Label>
                                        <Input
                                            id="tg-chat"
                                            type="text"
                                            v-model="settingsForm.telegram_chat_id"
                                            placeholder="Ví dụ: -100123456789"
                                            class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 font-semibold font-mono"
                                        />
                                        <span class="text-[9px] text-muted-foreground font-semibold">ID của cuộc hội thoại hoặc nhóm nhận cảnh báo.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex items-center justify-end gap-3 border-t pt-5 border-border/40">
                                <Button
                                    type="submit"
                                    class="rounded-xl h-9 text-xs font-bold bg-orange-500 hover:bg-orange-600 text-white cursor-pointer px-5"
                                    :disabled="settingsForm.processing"
                                >
                                    <Loader2 v-if="settingsForm.processing" class="size-4 animate-spin mr-1.5" />
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
