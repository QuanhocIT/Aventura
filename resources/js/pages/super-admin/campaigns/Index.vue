<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    Send, 
    Trash2, 
    Megaphone, 
    Mail, 
    Smartphone, 
    Users, 
    CheckCircle2, 
    AlertCircle, 
    Loader2, 
    ToggleLeft, 
    ToggleRight, 
    Users2, 
    Layers, 
    Award,
    Activity
} from 'lucide-vue-next';
import { ref, watch, onMounted, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader, StatCard, StatusBadge, EmptyState } from '@/components/super-admin';

interface Campaign {
    id: number;
    title: string;
    content: string;
    target_type: 'all' | 'plan' | 'trial';
    target_plan_id: number | null;
    target_plan_name: string | null;
    target_role: 'owner' | 'all_staff';
    channels: string[];
    status: 'draft' | 'sending' | 'sent' | 'failed';
    sent_count: number;
    created_by_name: string | null;
    sent_at: string | null;
    created_at: string;
}

interface Plan {
    id: number;
    name: string;
    code: string;
}

interface Stats {
    total: number;
    sent: number;
    sending: number;
    draft: number;
}

const props = defineProps<{
    campaigns: { data: Campaign[] };
    plans: Plan[];
    stats: Stats;
    auth: { user: any };
}>();

// --- Builder Form Form ---
const form = useForm({
    title: '',
    content: '',
    target_type: 'all' as 'all' | 'plan' | 'trial',
    target_plan_id: null as number | null,
    target_role: 'all_staff' as 'owner' | 'all_staff',
    channels: ['websocket'] as string[],
});

// --- Dynamic Audience Preview ---
const isSimulating = ref(false);
const simulatedRestaurants = ref(0);
const simulatedUsers = ref(0);

async function simulateAudience() {
    isSimulating.value = true;

    try {
        const response = await axios.post('/super-admin/campaigns/preview-audience', {
            target_type: form.target_type,
            target_plan_id: form.target_plan_id,
            target_role: form.target_role,
        });
        simulatedRestaurants.value = response.data.restaurants_count;
        simulatedUsers.value = response.data.users_count;
    } catch (e) {
        console.error('Error simulating audience size:', e);
    } finally {
        isSimulating.value = false;
    }
}

// Re-simulate when target parameters change
watch(() => [form.target_type, form.target_plan_id, form.target_role], () => {
    simulateAudience();
}, { deep: true });

onMounted(() => {
    // Select the first plan by default if 'plan' target is selected
    if (props.plans.length > 0 && !form.target_plan_id) {
        form.target_plan_id = props.plans[0].id;
    }

    simulateAudience();
});

function handleTargetTypeChange(type: 'all' | 'plan' | 'trial') {
    form.target_type = type;

    if (type === 'plan' && props.plans.length > 0) {
        form.target_plan_id = props.plans[0].id;
    } else {
        form.target_plan_id = null;
    }
}

function toggleChannel(channel: string) {
    const idx = form.channels.indexOf(channel);

    if (idx > -1) {
        if (form.channels.length > 1) {
            form.channels.splice(idx, 1);
        }
    } else {
        form.channels.push(channel);
    }
}

// --- Submit Draft ---
function createCampaign() {
    form.post('/super-admin/campaigns', {
        onSuccess: () => {
            form.title = '';
            form.content = '';
            simulateAudience();
        }
    });
}

// --- Actions ---
function deleteCampaign(campaign: Campaign) {
    if (confirm(`Xóa chiến dịch "${campaign.title}"?`)) {
        router.delete(`/super-admin/campaigns/${campaign.id}`, { preserveScroll: true });
    }
}

const isSendingMap = ref<Record<number, boolean>>({});

function sendCampaign(campaign: Campaign) {
    if (confirm(`Bạn chắc chắn muốn gửi chiến dịch "${campaign.title}" ngay lập tức? Điều này sẽ phát sóng Websocket và bắt đầu chạy tác vụ hàng loạt.`)) {
        isSendingMap.value[campaign.id] = true;
        router.post(`/super-admin/campaigns/${campaign.id}/send`, {}, {
            preserveScroll: true,
            onFinish: () => {
                isSendingMap.value[campaign.id] = false;
            }
        });
    }
}

// --- Mock Device Token Control ---
const currentDeviceToken = computed(() => props.auth?.user?.device_token || null);
const isUpdatingToken = ref(false);

function toggleMockDevice() {
    isUpdatingToken.value = true;
    const newToken = currentDeviceToken.value ? null : 'MOCK_TOKEN_' + Math.random().toString(36).substring(2, 7).toUpperCase();
    
    router.post('/settings/device-token', {
        device_token: newToken
    }, {
        preserveScroll: true,
        onFinish: () => {
            isUpdatingToken.value = false;
        }
    });
}
</script>

<template>
    <Head title="Chiến dịch Quảng bá & Thông báo" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Chiến dịch Thông báo & Quảng bá"
            subtitle="Gửi thông điệp khẩn cấp, cập nhật tính năng hoặc bảo trì qua Websocket, Email hoặc Push Notification."
            :icon="Megaphone"
        />

        <!-- KPI Status Bar -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <!-- Total -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Tổng chiến dịch</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1 text-indigo-500">{{ stats.total }}</h3>
                </div>
                <div class="size-9 rounded-xl bg-indigo-500/10 flex items-center justify-center border border-indigo-500/20 text-indigo-500">
                    <Megaphone class="size-4.5" />
                </div>
            </div>

            <!-- Sent -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Đã phát sóng</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1 text-emerald-500">{{ stats.sent }}</h3>
                </div>
                <div class="size-9 rounded-xl bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20 text-emerald-500">
                    <CheckCircle2 class="size-4.5" />
                </div>
            </div>

            <!-- Sending -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Đang xử lý</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1 text-amber-500">
                        <span v-if="stats.sending > 0" class="animate-pulse">{{ stats.sending }}</span>
                        <span v-else>{{ stats.sending }}</span>
                    </h3>
                </div>
                <div class="size-9 rounded-xl bg-amber-500/10 flex items-center justify-center border border-amber-500/20 text-amber-500" :class="{'animate-spin': stats.sending > 0}">
                    <Loader2 class="size-4.5" />
                </div>
            </div>

            <!-- Draft -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Chiến dịch nháp</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1 text-slate-500">{{ stats.draft }}</h3>
                </div>
                <div class="size-9 rounded-xl bg-slate-500/10 flex items-center justify-center border border-slate-500/20 text-slate-500">
                    <Activity class="size-4.5" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <!-- Left Panel: Campaign Builder -->
            <div class="lg:col-span-2 space-y-6">
                <Card class="border-border bg-card shadow-xs">
                    <CardHeader class="border-b border-border/40 bg-muted/10">
                        <CardTitle class="text-sm font-bold text-foreground">Tạo chiến dịch mới</CardTitle>
                        <CardDescription class="text-[11px]">Soạn thông điệp và chọn đối tượng mục tiêu nhận tin.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 pt-4">
                        <!-- Form fields -->
                        <div class="space-y-3.5">
                            <div>
                                <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Tiêu đề thông báo</Label>
                                <Input 
                                    v-model="form.title" 
                                    placeholder="Ví dụ: Hệ thống bảo trì lúc 0:00 ngày mai" 
                                    class="mt-1 border-border bg-background text-foreground rounded-xl focus-visible:ring-orange-500/20 focus-visible:border-orange-500 h-9 text-xs" 
                                />
                                <p v-if="form.errors.title" class="text-xs text-red-500 mt-1">{{ form.errors.title }}</p>
                            </div>

                            <div>
                                <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Nội dung chi tiết</Label>
                                <textarea 
                                    v-model="form.content" 
                                    rows="4"
                                    placeholder="Nhập nội dung thông điệp gửi cho đối tác..." 
                                    class="mt-1 flex w-full rounded-xl border border-border bg-background px-3 py-2 text-xs text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500/20 focus-visible:border-orange-500"
                                ></textarea>
                                <p v-if="form.errors.content" class="text-xs text-red-500 mt-1">{{ form.errors.content }}</p>
                            </div>

                            <!-- Target Type -->
                            <div>
                                <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Nhóm nhà hàng nhận tin</Label>
                                <div class="grid grid-cols-3 gap-2 mt-1.5">
                                    <button 
                                        type="button"
                                        @click="handleTargetTypeChange('all')"
                                        class="px-2 py-2 rounded-xl text-xs font-bold border cursor-pointer transition-all text-center flex flex-col items-center gap-1"
                                        :class="form.target_type === 'all' 
                                            ? 'border-orange-500/40 bg-orange-500/10 text-orange-500 dark:text-orange-400 font-extrabold' 
                                            : 'border-border bg-background text-muted-foreground hover:bg-muted/70'"
                                    >
                                        <Users class="size-4" />
                                        Tất cả
                                    </button>
                                    <button 
                                        type="button"
                                        @click="handleTargetTypeChange('plan')"
                                        class="px-2 py-2 rounded-xl text-xs font-bold border cursor-pointer transition-all text-center flex flex-col items-center gap-1"
                                        :class="form.target_type === 'plan' 
                                            ? 'border-orange-500/40 bg-orange-500/10 text-orange-500 dark:text-orange-400 font-extrabold' 
                                            : 'border-border bg-background text-muted-foreground hover:bg-muted/70'"
                                    >
                                        <Layers class="size-4" />
                                        Theo gói cước
                                    </button>
                                    <button 
                                        type="button"
                                        @click="handleTargetTypeChange('trial')"
                                        class="px-2 py-2 rounded-xl text-xs font-bold border cursor-pointer transition-all text-center flex flex-col items-center gap-1"
                                        :class="form.target_type === 'trial' 
                                            ? 'border-orange-500/40 bg-orange-500/10 text-orange-500 dark:text-orange-400 font-extrabold' 
                                            : 'border-border bg-background text-muted-foreground hover:bg-muted/70'"
                                    >
                                        <Award class="size-4" />
                                        Đang Trial
                                    </button>
                                </div>
                            </div>

                            <!-- Specific Plan Dropdown -->
                            <div v-if="form.target_type === 'plan'" class="animate-fadeIn">
                                <Label for="plan_id" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Chọn gói cước</Label>
                                <select 
                                    id="plan_id"
                                    v-model="form.target_plan_id"
                                    class="mt-1 flex h-9 w-full rounded-xl border border-border bg-background px-3 py-2 text-xs text-foreground cursor-pointer focus:outline-none focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 font-semibold"
                                >
                                    <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                        Gói {{ plan.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Target Roles -->
                            <div>
                                <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Vai trò nhận tin</Label>
                                <div class="grid grid-cols-2 gap-2 mt-1.5">
                                    <button 
                                        type="button"
                                        @click="form.target_role = 'owner'"
                                        class="px-2 py-2 rounded-xl text-xs font-bold border cursor-pointer transition-all text-center flex items-center justify-center gap-1.5"
                                        :class="form.target_role === 'owner' 
                                            ? 'border-orange-500/40 bg-orange-500/10 text-orange-500 dark:text-orange-400 font-extrabold' 
                                            : 'border-border bg-background text-muted-foreground hover:bg-muted/70'"
                                    >
                                        <Users2 class="size-4" />
                                        Chủ nhà hàng
                                    </button>
                                    <button 
                                        type="button"
                                        @click="form.target_role = 'all_staff'"
                                        class="px-2 py-2 rounded-xl text-xs font-bold border cursor-pointer transition-all text-center flex items-center justify-center gap-1.5"
                                        :class="form.target_role === 'all_staff' 
                                            ? 'border-orange-500/40 bg-orange-500/10 text-orange-500 dark:text-orange-400 font-extrabold' 
                                            : 'border-border bg-background text-muted-foreground hover:bg-muted/70'"
                                    >
                                        <Users class="size-4" />
                                        Tất cả nhân viên
                                    </button>
                                </div>
                            </div>

                            <!-- Channels selection -->
                            <div>
                                <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Kênh gửi tin</Label>
                                <div class="space-y-2 mt-2">
                                    <div 
                                        @click="toggleChannel('websocket')"
                                        class="flex items-center justify-between p-2.5 rounded-xl border border-border bg-background cursor-pointer hover:bg-muted/45 transition-colors"
                                        :class="{'border-orange-500/40 bg-orange-500/5': form.channels.includes('websocket')}"
                                    >
                                        <div class="flex items-center gap-2">
                                            <div class="p-1.5 rounded-lg bg-orange-500/10 text-orange-500">
                                                <Megaphone class="size-4" />
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold text-foreground">WebSocket Reverb</p>
                                                <p class="text-[10px] text-muted-foreground leading-normal mt-0.5">Thông báo nổi thời gian thực trên app</p>
                                            </div>
                                        </div>
                                        <div class="h-4 w-4 rounded border flex items-center justify-center transition-all" :class="form.channels.includes('websocket') ? 'bg-orange-500 border-orange-500 text-white' : 'border-border'">
                                            <span v-if="form.channels.includes('websocket')" class="text-[9px] font-black">✓</span>
                                        </div>
                                    </div>

                                    <div 
                                        @click="toggleChannel('email')"
                                        class="flex items-center justify-between p-2.5 rounded-xl border border-border bg-background cursor-pointer hover:bg-muted/45 transition-colors"
                                        :class="{'border-orange-500/40 bg-orange-500/5': form.channels.includes('email')}"
                                    >
                                        <div class="flex items-center gap-2">
                                            <div class="p-1.5 rounded-lg bg-orange-500/10 text-orange-500">
                                                <Mail class="size-4" />
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold text-foreground">Email Service</p>
                                                <p class="text-[10px] text-muted-foreground leading-normal mt-0.5">Gửi email hàng loạt tới hòm thư đối tác</p>
                                            </div>
                                        </div>
                                        <div class="h-4 w-4 rounded border flex items-center justify-center transition-all" :class="form.channels.includes('email') ? 'bg-orange-500 border-orange-500 text-white' : 'border-border'">
                                            <span v-if="form.channels.includes('email')" class="text-[9px] font-black">✓</span>
                                        </div>
                                    </div>

                                    <div 
                                        @click="toggleChannel('push')"
                                        class="flex items-center justify-between p-2.5 rounded-xl border border-border bg-background cursor-pointer hover:bg-muted/45 transition-colors"
                                        :class="{'border-orange-500/40 bg-orange-500/5': form.channels.includes('push')}"
                                    >
                                        <div class="flex items-center gap-2">
                                            <div class="p-1.5 rounded-lg bg-orange-500/10 text-orange-500">
                                                <Smartphone class="size-4" />
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold text-foreground">Push Notification</p>
                                                <p class="text-[10px] text-muted-foreground leading-normal mt-0.5">Gửi thông báo đẩy về thiết bị di động</p>
                                            </div>
                                        </div>
                                        <div class="h-4 w-4 rounded border flex items-center justify-center transition-all" :class="form.channels.includes('push') ? 'bg-orange-500 border-orange-500 text-white' : 'border-border'">
                                            <span v-if="form.channels.includes('push')" class="text-[9px] font-black">✓</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Target Audience Simulator Banner -->
                        <div class="rounded-xl border border-orange-500/20 bg-orange-500/[0.03] p-3 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <Users class="size-4 text-orange-500 shrink-0" />
                                <div>
                                    <span class="font-black text-foreground">Đối tượng dự kiến:</span>
                                    <p class="text-[10px] text-muted-foreground leading-normal mt-0.5">Mô phỏng dựa trên cấu hình hiện tại</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div v-if="isSimulating" class="flex items-center justify-end text-muted-foreground font-semibold">
                                    <Loader2 class="size-3.5 animate-spin mr-1" /> Simulating...
                                </div>
                                <div v-else class="font-black text-orange-500 text-xs font-mono">
                                    {{ simulatedRestaurants }} Cửa hàng / {{ simulatedUsers }} Users
                                </div>
                            </div>
                        </div>

                        <Button 
                            @click="createCampaign"
                            :disabled="!form.title || !form.content || form.processing"
                            class="w-full bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white rounded-xl shadow-xs cursor-pointer font-bold h-9 text-xs transition-all"
                        >
                            {{ form.processing ? 'Đang tạo...' : 'Tạo chiến dịch nháp' }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Mock Mobile Device Simulator Settings Card -->
                <Card class="border border-amber-500/30 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardHeader class="pb-3 border-b border-border/40 bg-muted/10">
                        <div class="flex items-center gap-2">
                            <CardTitle class="text-sm font-bold text-foreground">Mô phỏng Thiết bị di động để Test Push</CardTitle>
                            <span class="rounded-full bg-amber-500/10 border border-amber-500/30 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-600 dark:text-amber-400">Chỉ dùng Sandbox</span>
                        </div>
                        <CardDescription class="text-[11px] mt-0.5">Sinh token giả (không kết nối provider push thật) để kiểm tra tính năng Push Notification qua logs — không dùng để gửi push cho thiết bị thật.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 pt-4">
                        <div class="flex items-center justify-between p-3 rounded-xl border border-border/30 bg-muted/10">
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-foreground">
                                    {{ currentDeviceToken ? 'Mock Token hoạt động' : 'Chưa bật token' }}
                                </p>
                                <p class="text-[10px] text-muted-foreground truncate max-w-[220px] font-mono mt-0.5">
                                    {{ currentDeviceToken ? 'Token: ' + currentDeviceToken : 'Kích hoạt để nhận logs push notification' }}
                                </p>
                            </div>
                            
                            <button 
                                @click="toggleMockDevice"
                                :disabled="isUpdatingToken"
                                class="rounded-lg p-1 transition cursor-pointer disabled:opacity-50"
                                :class="currentDeviceToken ? 'text-orange-500 hover:bg-orange-500/10' : 'text-muted-foreground hover:bg-muted'"
                            >
                                <ToggleRight v-if="currentDeviceToken" class="h-8 w-8" />
                                <ToggleLeft v-else class="h-8 w-8" />
                            </button>
                        </div>
                        <p class="text-[10px] text-muted-foreground leading-normal font-semibold">
                            Khi bật, bất kỳ chiến dịch push nào chạy qua hệ thống mà tài khoản của bạn nằm trong đối tượng đích đều sẽ in log push chi tiết ra tệp <code class="bg-muted px-1.5 py-0.5 rounded font-mono text-[9px]">laravel.log</code>.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Panel: Campaigns List -->
            <div class="lg:col-span-3 space-y-4">
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardHeader class="pb-3 border-b border-border/40 bg-muted/10">
                        <CardTitle class="text-sm font-bold text-foreground">Danh sách chiến dịch đã tạo</CardTitle>
                    </CardHeader>
                    <CardContent class="pt-4">
                        <div v-if="campaigns.data.length === 0" class="flex flex-col items-center justify-center py-16 text-muted-foreground/60">
                            <Megaphone class="h-10 w-10 mb-2 text-muted-foreground/45" />
                            <p class="text-xs font-bold">Chưa có chiến dịch nào được tạo</p>
                        </div>

                        <div v-else class="space-y-4">
                            <div 
                                v-for="c in campaigns.data" 
                                :key="c.id"
                                class="p-4 rounded-xl border border-border/30 bg-muted/10 hover:bg-muted/20 transition-all space-y-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="space-y-1">
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-200 flex items-center gap-2">
                                            {{ c.title }}
                                        </h4>
                                        <p class="text-[11px] text-muted-foreground max-w-lg">
                                            Đối tượng: 
                                            <span class="font-bold text-foreground">
                                                <span v-if="c.target_type === 'all'">Tất cả nhà hàng</span>
                                                <span v-else-if="c.target_type === 'plan'">Gói {{ c.target_plan_name || 'Không xác định' }}</span>
                                                <span v-else-if="c.target_type === 'trial'">Nhà hàng đang Trial</span>
                                            </span>
                                            · Vai trò: 
                                            <span class="font-bold text-foreground">
                                                {{ c.target_role === 'owner' ? 'Chỉ chủ cửa hàng' : 'Chủ cửa hàng & Nhân viên' }}
                                            </span>
                                        </p>
                                    </div>
                                    
                                    <!-- Status Badges -->
                                    <div class="flex items-center gap-2">
                                        <Badge v-if="c.status === 'draft'" variant="outline" class="bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/25 rounded-full font-black text-[9px] uppercase px-2 py-0.5">Nháp</Badge>
                                        <Badge v-else-if="c.status === 'sending'" variant="outline" class="bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/25 rounded-full font-black text-[9px] uppercase px-2 py-0.5 animate-pulse">Đang gửi</Badge>
                                        <Badge v-else-if="c.status === 'sent'" variant="outline" class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/25 rounded-full font-black text-[9px] uppercase px-2 py-0.5">Đã gửi</Badge>
                                        <Badge v-else variant="outline" class="bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/25 rounded-full font-black text-[9px] uppercase px-2 py-0.5">Thất bại</Badge>
                                    </div>
                                </div>

                                <p class="text-xs text-slate-600 dark:text-slate-400 line-clamp-3 whitespace-pre-wrap leading-relaxed font-semibold">
                                    {{ c.content }}
                                </p>

                                <div class="flex items-center justify-between border-t border-border/60 pt-3">
                                    <!-- Channels Used -->
                                    <div class="flex items-center gap-1.5">
                                        <div 
                                            v-for="ch in c.channels" 
                                            :key="ch"
                                            class="px-2 py-0.5 rounded-md text-[10px] font-semibold flex items-center gap-1 border"
                                            :class="ch === 'websocket' ? 'bg-orange-500/10 text-orange-600 border-orange-500/20' : ch === 'email' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-amber-500/10 text-amber-600 border-amber-500/20'"
                                        >
                                            <Megaphone v-if="ch === 'websocket'" class="size-3" />
                                            <Mail v-else-if="ch === 'email'" class="size-3" />
                                            <Smartphone v-else class="size-3" />
                                            <span class="capitalize text-[9px] font-bold">{{ ch }}</span>
                                        </div>
                                    </div>

                                    <!-- Statistics & Actions -->
                                    <div class="flex items-center gap-3">
                                        <span class="text-[10px] text-muted-foreground font-semibold">
                                            Đã gửi: <strong class="text-foreground font-bold font-mono">{{ c.sent_count }}</strong> người
                                        </span>
                                        
                                        <div class="flex items-center gap-1">
                                            <button 
                                                v-if="c.status === 'draft'"
                                                @click="sendCampaign(c)"
                                                :disabled="isSendingMap[c.id]"
                                                class="bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-bold text-[10px] px-2.5 py-1 rounded-lg flex items-center gap-1 cursor-pointer transition-colors shadow-xs disabled:opacity-50"
                                            >
                                                <Loader2 v-if="isSendingMap[c.id]" class="size-3 animate-spin" />
                                                <Send v-else class="size-3" />
                                                Phát hành
                                            </button>
                                            
                                            <button 
                                                @click="deleteCampaign(c)"
                                                class="p-1.5 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/20 rounded-lg cursor-pointer transition-colors"
                                                title="Xóa chiến dịch"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
    animation: fadeIn 0.2s ease-out forwards;
}
</style>
