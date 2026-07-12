<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Bell,
    CheckCircle2,
    Mail,
    PauseCircle,
    RefreshCcw,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    PageHeader,
    StatusBadge,
    AlertBanner,
    SectionCard,
} from '@/components/super-admin';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface DunningEntry {
    id: number;
    restaurant_id: number;
    restaurant_name: string;
    owner_name: string;
    owner_email: string;
    ended_at: string | null;
    days_left: number | null;
    stage: string | null;
    last_notified_at: string | null;
    last_paid_at: string | null;
    is_paused: boolean;
    paused_until: string | null;
}

interface Groups {
    d7: DunningEntry[];
    d3: DunningEntry[];
    d0: DunningEntry[];
    overdue: DunningEntry[];
    converted: DunningEntry[];
}

interface Stats {
    total_in_dunning: number;
    converted_count: number;
    conversion_rate: number;
    d7_count: number;
    d3_count: number;
    d0_count: number;
    overdue_count: number;
}

const props = defineProps<{
    groups: Groups;
    stats: Stats;
}>();

const stageCols = [
    {
        key: 'd7' as keyof Groups,
        label: 'D-7 (7 ngày nữa)',
        color: 'sky',
        badge: 'bg-sky-100 text-sky-800',
    },
    {
        key: 'd3' as keyof Groups,
        label: 'D-3 (3 ngày nữa)',
        color: 'amber',
        badge: 'bg-amber-100 text-amber-800',
    },
    {
        key: 'd0' as keyof Groups,
        label: 'D0 (Hôm nay)',
        color: 'orange',
        badge: 'bg-orange-100 text-orange-800',
    },
    {
        key: 'overdue' as keyof Groups,
        label: 'Quá hạn',
        color: 'rose',
        badge: 'bg-rose-100 text-rose-800',
    },
];

// Send dunning email
const sendingId = ref<number | null>(null);

function sendEmail(subId: number, stage: string) {
    sendingId.value = subId;
    router.post(
        `/super-admin/billing/dunning/${subId}/send`,
        { stage },
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã gửi email nhắc gia hạn!'),
            onError: () => toast.error('Gửi email thất bại!'),
            onFinish: () => {
                sendingId.value = null;
            },
        },
    );
}

// Pause dunning dialog
const pauseSubId = ref<number | null>(null);
const pauseSubName = ref('');
const pauseDays = ref(7);

function openPause(sub: DunningEntry) {
    pauseSubId.value = sub.id;
    pauseSubName.value = sub.restaurant_name;
    pauseDays.value = 7;
}

function closePause() {
    pauseSubId.value = null;
    pauseSubName.value = '';
}

function submitPause() {
    if (!pauseSubId.value) return;
    router.post(
        `/super-admin/billing/dunning/${pauseSubId.value}/pause`,
        { days: pauseDays.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Đã tạm dừng dunning ${pauseDays.value} ngày.`);
                closePause();
            },
            onError: () => toast.error('Tạm dừng thất bại!'),
        },
    );
}

const colBorderClass: Record<string, string> = {
    sky: 'border-sky-200 dark:border-sky-900/40',
    amber: 'border-amber-200 dark:border-amber-900/40',
    orange: 'border-orange-200 dark:border-orange-900/40',
    rose: 'border-rose-200 dark:border-rose-900/40',
};

const colHeaderClass: Record<string, string> = {
    sky: 'bg-sky-50 dark:bg-sky-900/20 text-sky-700',
    amber: 'bg-amber-50 dark:bg-amber-900/20 text-amber-700',
    orange: 'bg-orange-50 dark:bg-orange-900/20 text-orange-700',
    rose: 'bg-rose-50 dark:bg-rose-900/20 text-rose-700',
};
</script>

<template>
    <Head title="Dunning Dashboard" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Dunning Dashboard"
            subtitle="Giám sát và điều phối chiến dịch nhắc gia hạn."
            :icon="Bell"
        >
            <template #actions>
                <Link href="/super-admin/billing">
                    <Button variant="outline" size="sm"
                        ><ArrowLeft class="mr-1.5 size-4" /> Billing</Button
                    >
                </Link>
            </template>
        </PageHeader>

        <!-- Stats row -->
        <div class="grid gap-4 md:grid-cols-5">
            <Card>
                <CardContent class="flex items-center gap-3 p-4">
                    <div
                        class="rounded-lg bg-violet-100 p-2 dark:bg-violet-900/30"
                    >
                        <Mail class="size-4 text-violet-600" />
                    </div>
                    <div>
                        <p class="text-lg font-bold">
                            {{ stats.total_in_dunning }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Đã nhắc (30 ngày)
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card class="border-emerald-200 dark:border-emerald-900/40">
                <CardContent class="flex items-center gap-3 p-4">
                    <div
                        class="rounded-lg bg-emerald-100 p-2 dark:bg-emerald-900/30"
                    >
                        <CheckCircle2 class="size-4 text-emerald-600" />
                    </div>
                    <div>
                        <p class="text-lg font-bold text-emerald-600">
                            {{ stats.conversion_rate }}%
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Tỷ lệ chuyển đổi
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card
                :class="
                    stats.d7_count > 0
                        ? 'border-sky-200 dark:border-sky-900/40'
                        : ''
                "
            >
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="rounded-lg bg-sky-100 p-2 dark:bg-sky-900/30">
                        <RefreshCcw class="size-4 text-sky-600" />
                    </div>
                    <div>
                        <p class="text-lg font-bold">{{ stats.d7_count }}</p>
                        <p class="text-xs text-muted-foreground">D-7</p>
                    </div>
                </CardContent>
            </Card>
            <Card
                :class="
                    stats.d3_count > 0
                        ? 'border-amber-200 dark:border-amber-900/40'
                        : ''
                "
            >
                <CardContent class="flex items-center gap-3 p-4">
                    <div
                        class="rounded-lg bg-amber-100 p-2 dark:bg-amber-900/30"
                    >
                        <RefreshCcw class="size-4 text-amber-600" />
                    </div>
                    <div>
                        <p class="text-lg font-bold">{{ stats.d3_count }}</p>
                        <p class="text-xs text-muted-foreground">D-3</p>
                    </div>
                </CardContent>
            </Card>
            <Card
                :class="
                    stats.overdue_count > 0
                        ? 'border-rose-200 dark:border-rose-900/40'
                        : ''
                "
            >
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="rounded-lg bg-rose-100 p-2 dark:bg-rose-900/30">
                        <RefreshCcw class="size-4 text-rose-600" />
                    </div>
                    <div>
                        <p
                            class="text-lg font-bold"
                            :class="
                                stats.overdue_count > 0 ? 'text-rose-600' : ''
                            "
                        >
                            {{ stats.overdue_count }}
                        </p>
                        <p class="text-xs text-muted-foreground">Quá hạn</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Kanban Columns -->
        <div class="grid gap-4 lg:grid-cols-4">
            <div v-for="col in stageCols" :key="col.key">
                <Card :class="['h-full', colBorderClass[col.color]]">
                    <CardHeader
                        :class="[
                            'rounded-t-xl px-4 py-3',
                            colHeaderClass[col.color],
                        ]"
                    >
                        <CardTitle
                            class="flex items-center justify-between text-sm font-semibold"
                        >
                            <span>{{ col.label }}</span>
                            <Badge :class="col.badge">{{
                                (groups[col.key] as DunningEntry[]).length
                            }}</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 p-3">
                        <p
                            v-if="!(groups[col.key] as DunningEntry[]).length"
                            class="py-6 text-center text-xs text-muted-foreground"
                        >
                            Không có
                        </p>
                        <div
                            v-for="entry in groups[col.key] as DunningEntry[]"
                            :key="entry.id"
                            class="space-y-2 rounded-xl border bg-card p-3"
                            :class="
                                entry.is_paused
                                    ? 'border-dashed opacity-60'
                                    : ''
                            "
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ entry.restaurant_name }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ entry.owner_email }}
                                    </p>
                                </div>
                                <Badge
                                    v-if="entry.is_paused"
                                    class="shrink-0 bg-slate-100 text-slate-600"
                                >
                                    <PauseCircle class="mr-1 size-3" /> Paused
                                </Badge>
                            </div>
                            <div
                                class="space-y-0.5 text-xs text-muted-foreground"
                            >
                                <p v-if="entry.ended_at">
                                    Hết hạn:
                                    <strong>{{ entry.ended_at }}</strong>
                                </p>
                                <p v-if="entry.days_left !== null">
                                    <span
                                        :class="
                                            entry.days_left < 0
                                                ? 'font-semibold text-rose-600'
                                                : ''
                                        "
                                    >
                                        {{
                                            entry.days_left < 0
                                                ? `Quá hạn ${Math.abs(entry.days_left)} ngày`
                                                : `Còn ${entry.days_left} ngày`
                                        }}
                                    </span>
                                </p>
                                <p v-if="entry.last_notified_at">
                                    Nhắc lần cuối: {{ entry.last_notified_at }}
                                </p>
                                <p
                                    v-if="entry.is_paused && entry.paused_until"
                                    class="text-amber-600"
                                >
                                    Tạm dừng đến {{ entry.paused_until }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-7 text-xs"
                                    :disabled="sendingId === entry.id"
                                    @click="sendEmail(entry.id, col.key)"
                                >
                                    <Mail class="mr-1 size-3" />
                                    {{
                                        sendingId === entry.id
                                            ? '...'
                                            : 'Gửi Email'
                                    }}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-7 text-xs"
                                    @click="openPause(entry)"
                                >
                                    <PauseCircle class="mr-1 size-3" /> Pause
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Converted section -->
        <Card
            v-if="groups.converted.length > 0"
            class="border-emerald-200 dark:border-emerald-900/40"
        >
            <CardHeader
                class="rounded-t-xl bg-emerald-50 pb-3 dark:bg-emerald-900/20"
            >
                <CardTitle
                    class="flex items-center gap-2 text-sm text-emerald-700"
                >
                    <CheckCircle2 class="size-4" />
                    Đã Thanh Toán Sau Khi Nhắc ({{ groups.converted.length }})
                </CardTitle>
            </CardHeader>
            <CardContent class="p-4">
                <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="entry in groups.converted"
                        :key="entry.id"
                        class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-3 dark:bg-emerald-900/10"
                    >
                        <p class="text-sm font-medium">
                            {{ entry.restaurant_name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ entry.owner_email }}
                        </p>
                        <p class="mt-1 text-xs text-emerald-600">
                            ✓ Thanh toán: {{ entry.last_paid_at }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Pause Dialog -->
        <div
            v-if="pauseSubId !== null"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
            @click.self="closePause"
        >
            <div
                class="w-full max-w-sm rounded-2xl bg-background p-6 shadow-2xl"
            >
                <h2 class="mb-1 text-lg font-bold">⏸ Tạm dừng Dunning</h2>
                <p class="mb-4 text-sm text-muted-foreground">
                    Tạm dừng nhắc nhở cho <strong>{{ pauseSubName }}</strong
                    >.
                </p>
                <div class="space-y-2">
                    <Label>Số ngày tạm dừng</Label>
                    <Input
                        v-model.number="pauseDays"
                        type="number"
                        min="1"
                        max="30"
                    />
                    <p class="text-xs text-muted-foreground">Tối đa 30 ngày</p>
                </div>
                <div class="mt-4 flex justify-end gap-3">
                    <Button variant="outline" @click="closePause">Hủy</Button>
                    <Button @click="submitPause">Xác nhận Pause</Button>
                </div>
            </div>
        </div>
    </div>
</template>
