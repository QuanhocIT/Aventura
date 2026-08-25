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
    Loader2,
    ToggleLeft,
    ToggleRight,
    Users2,
    Layers,
    Award,
    Activity,
} from 'lucide-vue-next';
import { ref, watch, onMounted, computed } from 'vue';
import { PageHeader } from '@/components/super-admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirmDialog } from '@/composables/useConfirm';

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
        const response = await axios.post(
            '/super-admin/campaigns/preview-audience',
            {
                target_type: form.target_type,
                target_plan_id: form.target_plan_id,
                target_role: form.target_role,
            },
        );
        simulatedRestaurants.value = response.data.restaurants_count;
        simulatedUsers.value = response.data.users_count;
    } catch (e) {
        console.error('Error simulating audience size:', e);
    } finally {
        isSimulating.value = false;
    }
}

// Re-simulate when target parameters change
watch(
    () => [form.target_type, form.target_plan_id, form.target_role],
    () => {
        simulateAudience();
    },
    { deep: true },
);

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
        },
    });
}

// --- Actions ---
async function deleteCampaign(campaign: Campaign) {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Xóa chiến dịch "${campaign.title}"?`,
        })
    ) {
        router.delete(`/super-admin/campaigns/${campaign.id}`, {
            preserveScroll: true,
        });
    }
}

const isSendingMap = ref<Record<number, boolean>>({});

async function sendCampaign(campaign: Campaign) {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Bạn chắc chắn muốn gửi chiến dịch "${campaign.title}" ngay lập tức? Điều này sẽ phát sóng WebSocket và bắt đầu chạy tác vụ hàng loạt.`,
            variant: 'default',
        })
    ) {
        isSendingMap.value[campaign.id] = true;
        router.post(
            `/super-admin/campaigns/${campaign.id}/send`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    isSendingMap.value[campaign.id] = false;
                },
            },
        );
    }
}

// --- Mock Device Token Control ---
const currentDeviceToken = computed(
    () => props.auth?.user?.device_token || null,
);
const isUpdatingToken = ref(false);

function toggleMockDevice() {
    isUpdatingToken.value = true;
    const newToken = currentDeviceToken.value
        ? null
        : 'MOCK_TOKEN_' +
          Math.random().toString(36).substring(2, 7).toUpperCase();

    router.post(
        '/settings/device-token',
        {
            device_token: newToken,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isUpdatingToken.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Chiến dịch Quảng bá & Thông báo" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Chiến dịch Thông báo & Quảng bá"
            subtitle="Gửi thông điệp khẩn cấp, cập nhật tính năng hoặc bảo trì qua WebSocket, thư điện tử hoặc thông báo đẩy."
            :icon="Megaphone"
        />

        <!-- KPI Status Bar -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <!-- Total -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Tổng chiến dịch
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-indigo-500"
                    >
                        {{ stats.total }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-indigo-500/20 bg-indigo-500/10 text-indigo-500"
                >
                    <Megaphone class="size-4.5" />
                </div>
            </div>

            <!-- Sent -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Đã phát sóng
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-emerald-500"
                    >
                        {{ stats.sent }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-500"
                >
                    <CheckCircle2 class="size-4.5" />
                </div>
            </div>

            <!-- Sending -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Đang xử lý
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-amber-500"
                    >
                        <span v-if="stats.sending > 0" class="animate-pulse">{{
                            stats.sending
                        }}</span>
                        <span v-else>{{ stats.sending }}</span>
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10 text-amber-500"
                    :class="{ 'animate-spin': stats.sending > 0 }"
                >
                    <Loader2 class="size-4.5" />
                </div>
            </div>

            <!-- Draft -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Chiến dịch nháp
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-slate-500"
                    >
                        {{ stats.draft }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-slate-500/20 bg-slate-500/10 text-slate-500"
                >
                    <Activity class="size-4.5" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <!-- Left Panel: Campaign Builder -->
            <div class="space-y-6 lg:col-span-2">
                <Card class="border-border bg-card shadow-xs">
                    <CardHeader class="border-b border-border/40 bg-muted/10">
                        <CardTitle class="text-sm font-bold text-foreground"
                            >Tạo chiến dịch mới</CardTitle
                        >
                        <CardDescription class="text-[11px]"
                            >Soạn thông điệp và chọn đối tượng mục tiêu nhận
                            tin.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4 pt-4">
                        <!-- Form fields -->
                        <div class="space-y-3.5">
                            <div>
                                <Label
                                    class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                    >Tiêu đề thông báo</Label
                                >
                                <Input
                                    v-model="form.title"
                                    placeholder="Ví dụ: Hệ thống bảo trì lúc 0:00 ngày mai"
                                    class="mt-1 h-9 rounded-xl border-border bg-background text-xs text-foreground focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                />
                                <p
                                    v-if="form.errors.title"
                                    class="mt-1 text-xs text-red-500"
                                >
                                    {{ form.errors.title }}
                                </p>
                            </div>

                            <div>
                                <Label
                                    class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                    >Nội dung chi tiết</Label
                                >
                                <textarea
                                    v-model="form.content"
                                    rows="4"
                                    placeholder="Nhập nội dung thông điệp gửi cho đối tác..."
                                    class="mt-1 flex w-full rounded-xl border border-border bg-background px-3 py-2 text-xs text-foreground placeholder:text-muted-foreground focus-visible:border-orange-500 focus-visible:ring-2 focus-visible:ring-orange-500/20 focus-visible:outline-none"
                                ></textarea>
                                <p
                                    v-if="form.errors.content"
                                    class="mt-1 text-xs text-red-500"
                                >
                                    {{ form.errors.content }}
                                </p>
                            </div>

                            <!-- Target Type -->
                            <div>
                                <Label
                                    class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                    >Nhóm nhà hàng nhận tin</Label
                                >
                                <div class="mt-1.5 grid grid-cols-3 gap-2">
                                    <button
                                        type="button"
                                        @click="handleTargetTypeChange('all')"
                                        class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border px-2 py-2 text-center text-xs font-bold transition-all"
                                        :class="
                                            form.target_type === 'all'
                                                ? 'border-orange-500/40 bg-orange-500/10 font-extrabold text-orange-500 dark:text-orange-400'
                                                : 'border-border bg-background text-muted-foreground hover:bg-muted/70'
                                        "
                                    >
                                        <Users class="size-4" />
                                        Tất cả
                                    </button>
                                    <button
                                        type="button"
                                        @click="handleTargetTypeChange('plan')"
                                        class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border px-2 py-2 text-center text-xs font-bold transition-all"
                                        :class="
                                            form.target_type === 'plan'
                                                ? 'border-orange-500/40 bg-orange-500/10 font-extrabold text-orange-500 dark:text-orange-400'
                                                : 'border-border bg-background text-muted-foreground hover:bg-muted/70'
                                        "
                                    >
                                        <Layers class="size-4" />
                                        Theo gói cước
                                    </button>
                                    <button
                                        type="button"
                                        @click="handleTargetTypeChange('trial')"
                                        class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border px-2 py-2 text-center text-xs font-bold transition-all"
                                        :class="
                                            form.target_type === 'trial'
                                                ? 'border-orange-500/40 bg-orange-500/10 font-extrabold text-orange-500 dark:text-orange-400'
                                                : 'border-border bg-background text-muted-foreground hover:bg-muted/70'
                                        "
                                    >
                                        <Award class="size-4" />
                                        Đang dùng thử
                                    </button>
                                </div>
                            </div>

                            <!-- Specific Plan Dropdown -->
                            <div
                                v-if="form.target_type === 'plan'"
                                class="animate-fadeIn"
                            >
                                <Label
                                    for="plan_id"
                                    class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                    >Chọn gói cước</Label
                                >
                                <select
                                    id="plan_id"
                                    v-model="form.target_plan_id"
                                    class="mt-1 flex h-9 w-full cursor-pointer rounded-xl border border-border bg-background px-3 py-2 text-xs font-semibold text-foreground focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 focus:outline-none"
                                >
                                    <option
                                        v-for="plan in plans"
                                        :key="plan.id"
                                        :value="plan.id"
                                    >
                                        Gói {{ plan.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Target Roles -->
                            <div>
                                <Label
                                    class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                    >Vai trò nhận tin</Label
                                >
                                <div class="mt-1.5 grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        @click="form.target_role = 'owner'"
                                        class="flex cursor-pointer items-center justify-center gap-1.5 rounded-xl border px-2 py-2 text-center text-xs font-bold transition-all"
                                        :class="
                                            form.target_role === 'owner'
                                                ? 'border-orange-500/40 bg-orange-500/10 font-extrabold text-orange-500 dark:text-orange-400'
                                                : 'border-border bg-background text-muted-foreground hover:bg-muted/70'
                                        "
                                    >
                                        <Users2 class="size-4" />
                                        Chủ nhà hàng
                                    </button>
                                    <button
                                        type="button"
                                        @click="form.target_role = 'all_staff'"
                                        class="flex cursor-pointer items-center justify-center gap-1.5 rounded-xl border px-2 py-2 text-center text-xs font-bold transition-all"
                                        :class="
                                            form.target_role === 'all_staff'
                                                ? 'border-orange-500/40 bg-orange-500/10 font-extrabold text-orange-500 dark:text-orange-400'
                                                : 'border-border bg-background text-muted-foreground hover:bg-muted/70'
                                        "
                                    >
                                        <Users class="size-4" />
                                        Tất cả nhân viên
                                    </button>
                                </div>
                            </div>

                            <!-- Channels selection -->
                            <div>
                                <Label
                                    class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                    >Kênh gửi tin</Label
                                >
                                <div class="mt-2 space-y-2">
                                    <div
                                        @click="toggleChannel('websocket')"
                                        class="flex cursor-pointer items-center justify-between rounded-xl border border-border bg-background p-2.5 transition-colors hover:bg-muted/45"
                                        :class="{
                                            'border-orange-500/40 bg-orange-500/5':
                                                form.channels.includes(
                                                    'websocket',
                                                ),
                                        }"
                                    >
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="rounded-lg bg-orange-500/10 p-1.5 text-orange-500"
                                            >
                                                <Megaphone class="size-4" />
                                            </div>
                                            <div>
                                                <p
                                                    class="text-xs font-bold text-foreground"
                                                >
                                                    WebSocket Reverb
                                                </p>
                                                <p
                                                    class="mt-0.5 text-[10px] leading-normal text-muted-foreground"
                                                >
                                                    Thông báo nổi thời gian thực
                                                    trên app
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            class="flex h-4 w-4 items-center justify-center rounded border transition-all"
                                            :class="
                                                form.channels.includes(
                                                    'websocket',
                                                )
                                                    ? 'border-orange-500 bg-orange-500 text-white'
                                                    : 'border-border'
                                            "
                                        >
                                            <span
                                                v-if="
                                                    form.channels.includes(
                                                        'websocket',
                                                    )
                                                "
                                                class="text-[9px] font-black"
                                                >✓</span
                                            >
                                        </div>
                                    </div>

                                    <div
                                        @click="toggleChannel('email')"
                                        class="flex cursor-pointer items-center justify-between rounded-xl border border-border bg-background p-2.5 transition-colors hover:bg-muted/45"
                                        :class="{
                                            'border-orange-500/40 bg-orange-500/5':
                                                form.channels.includes('email'),
                                        }"
                                    >
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="rounded-lg bg-orange-500/10 p-1.5 text-orange-500"
                                            >
                                                <Mail class="size-4" />
                                            </div>
                                            <div>
                                                <p
                                                    class="text-xs font-bold text-foreground"
                                                >
                                                    Dịch vụ thư điện tử
                                                </p>
                                                <p
                                                    class="mt-0.5 text-[10px] leading-normal text-muted-foreground"
                                                >
                                                    Gửi thư điện tử hàng loạt
                                                    tới hòm thư đối tác
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            class="flex h-4 w-4 items-center justify-center rounded border transition-all"
                                            :class="
                                                form.channels.includes('email')
                                                    ? 'border-orange-500 bg-orange-500 text-white'
                                                    : 'border-border'
                                            "
                                        >
                                            <span
                                                v-if="
                                                    form.channels.includes(
                                                        'email',
                                                    )
                                                "
                                                class="text-[9px] font-black"
                                                >✓</span
                                            >
                                        </div>
                                    </div>

                                    <div
                                        @click="toggleChannel('push')"
                                        class="flex cursor-pointer items-center justify-between rounded-xl border border-border bg-background p-2.5 transition-colors hover:bg-muted/45"
                                        :class="{
                                            'border-orange-500/40 bg-orange-500/5':
                                                form.channels.includes('push'),
                                        }"
                                    >
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="rounded-lg bg-orange-500/10 p-1.5 text-orange-500"
                                            >
                                                <Smartphone class="size-4" />
                                            </div>
                                            <div>
                                                <p
                                                    class="text-xs font-bold text-foreground"
                                                >
                                                    Push Notification
                                                </p>
                                                <p
                                                    class="mt-0.5 text-[10px] leading-normal text-muted-foreground"
                                                >
                                                    Gửi thông báo đẩy về thiết
                                                    bị di động
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            class="flex h-4 w-4 items-center justify-center rounded border transition-all"
                                            :class="
                                                form.channels.includes('push')
                                                    ? 'border-orange-500 bg-orange-500 text-white'
                                                    : 'border-border'
                                            "
                                        >
                                            <span
                                                v-if="
                                                    form.channels.includes(
                                                        'push',
                                                    )
                                                "
                                                class="text-[9px] font-black"
                                                >✓</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Target Audience Simulator Banner -->
                        <div
                            class="flex items-center justify-between rounded-xl border border-orange-500/20 bg-orange-500/[0.03] p-3 text-xs"
                        >
                            <div class="flex items-center gap-2">
                                <Users
                                    class="size-4 shrink-0 text-orange-500"
                                />
                                <div>
                                    <span class="font-black text-foreground"
                                        >Đối tượng dự kiến:</span
                                    >
                                    <p
                                        class="mt-0.5 text-[10px] leading-normal text-muted-foreground"
                                    >
                                        Mô phỏng dựa trên cấu hình hiện tại
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div
                                    v-if="isSimulating"
                                    class="flex items-center justify-end font-semibold text-muted-foreground"
                                >
                                    <Loader2
                                        class="mr-1 size-3.5 animate-spin"
                                    />
                                    Simulating...
                                </div>
                                <div
                                    v-else
                                    class="font-mono text-xs font-black text-orange-500"
                                >
                                    {{ simulatedRestaurants }} cửa hàng /
                                    {{ simulatedUsers }} người dùng
                                </div>
                            </div>
                        </div>

                        <Button
                            @click="createCampaign"
                            :disabled="
                                !form.title || !form.content || form.processing
                            "
                            class="h-9 w-full cursor-pointer rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 text-xs font-bold text-white shadow-xs transition-all hover:from-orange-600 hover:to-amber-600"
                        >
                            {{
                                form.processing
                                    ? 'Đang tạo...'
                                    : 'Tạo chiến dịch nháp'
                            }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Mock Mobile Device Simulator Settings Card -->
                <Card
                    class="overflow-hidden rounded-2xl border border-amber-500/30 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <CardHeader
                        class="border-b border-border/40 bg-muted/10 pb-3"
                    >
                        <div class="flex items-center gap-2">
                            <CardTitle class="text-sm font-bold text-foreground"
                                >Mô phỏng Thiết bị di động để Test
                                Push</CardTitle
                            >
                            <span
                                class="rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[9px] font-black tracking-wider text-amber-600 uppercase dark:text-amber-400"
                                >Chỉ dùng Sandbox</span
                            >
                        </div>
                        <CardDescription class="mt-0.5 text-[11px]"
                            >Sinh token giả (không kết nối provider push thật)
                            để kiểm tra tính năng Push Notification qua logs —
                            không dùng để gửi push cho thiết bị
                            thật.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-3 pt-4">
                        <div
                            class="flex items-center justify-between rounded-xl border border-border/30 bg-muted/10 p-3"
                        >
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-foreground">
                                    {{
                                        currentDeviceToken
                                            ? 'Mã mô phỏng đang hoạt động'
                                            : 'Chưa bật token'
                                    }}
                                </p>
                                <p
                                    class="mt-0.5 max-w-[220px] truncate font-mono text-[10px] text-muted-foreground"
                                >
                                    {{
                                        currentDeviceToken
                                            ? 'Token: ' + currentDeviceToken
                                            : 'Kích hoạt để nhận logs push notification'
                                    }}
                                </p>
                            </div>

                            <button
                                @click="toggleMockDevice"
                                :disabled="isUpdatingToken"
                                class="cursor-pointer rounded-lg p-1 transition disabled:opacity-50"
                                :class="
                                    currentDeviceToken
                                        ? 'text-orange-500 hover:bg-orange-500/10'
                                        : 'text-muted-foreground hover:bg-muted'
                                "
                            >
                                <ToggleRight
                                    v-if="currentDeviceToken"
                                    class="h-8 w-8"
                                />
                                <ToggleLeft v-else class="h-8 w-8" />
                            </button>
                        </div>
                        <p
                            class="text-[10px] leading-normal font-semibold text-muted-foreground"
                        >
                            Khi bật, bất kỳ chiến dịch push nào chạy qua hệ
                            thống mà tài khoản của bạn nằm trong đối tượng đích
                            đều sẽ in log push chi tiết ra tệp
                            <code
                                class="rounded bg-muted px-1.5 py-0.5 font-mono text-[9px]"
                                >laravel.log</code
                            >.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Panel: Campaigns List -->
            <div class="space-y-4 lg:col-span-3">
                <Card
                    class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <CardHeader
                        class="border-b border-border/40 bg-muted/10 pb-3"
                    >
                        <CardTitle class="text-sm font-bold text-foreground"
                            >Danh sách chiến dịch đã tạo</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="pt-4">
                        <div
                            v-if="campaigns.data.length === 0"
                            class="flex flex-col items-center justify-center py-16 text-muted-foreground/60"
                        >
                            <Megaphone
                                class="mb-2 h-10 w-10 text-muted-foreground/45"
                            />
                            <p class="text-xs font-bold">
                                Chưa có chiến dịch nào được tạo
                            </p>
                        </div>

                        <div v-else class="space-y-4">
                            <div
                                v-for="c in campaigns.data"
                                :key="c.id"
                                class="space-y-3 rounded-xl border border-border/30 bg-muted/10 p-4 transition-all hover:bg-muted/20"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="space-y-1">
                                        <h4
                                            class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-200"
                                        >
                                            {{ c.title }}
                                        </h4>
                                        <p
                                            class="max-w-lg text-[11px] text-muted-foreground"
                                        >
                                            Đối tượng:
                                            <span
                                                class="font-bold text-foreground"
                                            >
                                                <span
                                                    v-if="
                                                        c.target_type === 'all'
                                                    "
                                                    >Tất cả nhà hàng</span
                                                >
                                                <span
                                                    v-else-if="
                                                        c.target_type === 'plan'
                                                    "
                                                    >Gói
                                                    {{
                                                        c.target_plan_name ||
                                                        'Không xác định'
                                                    }}</span
                                                >
                                                <span
                                                    v-else-if="
                                                        c.target_type ===
                                                        'trial'
                                                    "
                                                    >Nhà hàng đang dùng
                                                    thử</span
                                                >
                                            </span>
                                            · Vai trò:
                                            <span
                                                class="font-bold text-foreground"
                                            >
                                                {{
                                                    c.target_role === 'owner'
                                                        ? 'Chỉ chủ cửa hàng'
                                                        : 'Chủ cửa hàng & Nhân viên'
                                                }}
                                            </span>
                                        </p>
                                    </div>

                                    <!-- Status Badges -->
                                    <div class="flex items-center gap-2">
                                        <Badge
                                            v-if="c.status === 'draft'"
                                            variant="outline"
                                            class="rounded-full border border-slate-500/25 bg-slate-500/10 px-2 py-0.5 text-[9px] font-black text-slate-600 uppercase dark:text-slate-400"
                                            >Nháp</Badge
                                        >
                                        <Badge
                                            v-else-if="c.status === 'sending'"
                                            variant="outline"
                                            class="animate-pulse rounded-full border border-amber-500/25 bg-amber-500/10 px-2 py-0.5 text-[9px] font-black text-amber-600 uppercase dark:text-amber-400"
                                            >Đang gửi</Badge
                                        >
                                        <Badge
                                            v-else-if="c.status === 'sent'"
                                            variant="outline"
                                            class="rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2 py-0.5 text-[9px] font-black text-emerald-600 uppercase dark:text-emerald-400"
                                            >Đã gửi</Badge
                                        >
                                        <Badge
                                            v-else
                                            variant="outline"
                                            class="rounded-full border border-rose-500/25 bg-rose-500/10 px-2 py-0.5 text-[9px] font-black text-rose-600 uppercase dark:text-rose-400"
                                            >Thất bại</Badge
                                        >
                                    </div>
                                </div>

                                <p
                                    class="line-clamp-3 text-xs leading-relaxed font-semibold whitespace-pre-wrap text-slate-600 dark:text-slate-400"
                                >
                                    {{ c.content }}
                                </p>

                                <div
                                    class="flex items-center justify-between border-t border-border/60 pt-3"
                                >
                                    <!-- Channels Used -->
                                    <div class="flex items-center gap-1.5">
                                        <div
                                            v-for="ch in c.channels"
                                            :key="ch"
                                            class="flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-semibold"
                                            :class="
                                                ch === 'websocket'
                                                    ? 'border-orange-500/20 bg-orange-500/10 text-orange-600'
                                                    : ch === 'email'
                                                      ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                                      : 'border-amber-500/20 bg-amber-500/10 text-amber-600'
                                            "
                                        >
                                            <Megaphone
                                                v-if="ch === 'websocket'"
                                                class="size-3"
                                            />
                                            <Mail
                                                v-else-if="ch === 'email'"
                                                class="size-3"
                                            />
                                            <Smartphone v-else class="size-3" />
                                            <span
                                                class="text-[9px] font-bold capitalize"
                                                >{{ ch }}</span
                                            >
                                        </div>
                                    </div>

                                    <!-- Statistics & Actions -->
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="text-[10px] font-semibold text-muted-foreground"
                                        >
                                            Đã gửi:
                                            <strong
                                                class="font-mono font-bold text-foreground"
                                                >{{ c.sent_count }}</strong
                                            >
                                            người
                                        </span>

                                        <div class="flex items-center gap-1">
                                            <button
                                                v-if="c.status === 'draft'"
                                                @click="sendCampaign(c)"
                                                :disabled="isSendingMap[c.id]"
                                                class="flex cursor-pointer items-center gap-1 rounded-lg bg-gradient-to-r from-orange-500 to-amber-500 px-2.5 py-1 text-[10px] font-bold text-white shadow-xs transition-colors hover:from-orange-600 hover:to-amber-600 disabled:opacity-50"
                                            >
                                                <Loader2
                                                    v-if="isSendingMap[c.id]"
                                                    class="size-3 animate-spin"
                                                />
                                                <Send v-else class="size-3" />
                                                Phát hành
                                            </button>

                                            <button
                                                @click="deleteCampaign(c)"
                                                class="cursor-pointer rounded-lg p-1.5 text-rose-500 transition-colors hover:bg-rose-50 dark:hover:bg-rose-950/20"
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
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fadeIn {
    animation: fadeIn 0.2s ease-out forwards;
}
</style>
