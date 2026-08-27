<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Gift, PartyPopper, Pencil, Play, Plus, Power, Send, Sparkles, Ticket, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import BackButton from '@/components/BackButton.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Trigger = {
    id: number;
    name: string;
    event_type: string;
    milestone_count: number | null;
    discount_type: string;
    discount_value: number;
    max_discount_amount: number | null;
    validity_days: number;
    send_email: boolean;
    message_template: string | null;
    is_active: boolean;
    coupons_generated: number;
    creator: string | null;
    created_at: string;
};

type RecentCoupon = {
    id: number;
    code: string;
    customer_name: string;
    customer_phone: string;
    trigger_name: string;
    event_type: string;
    discount_type: string;
    discount_value: number;
    status: 'available' | 'used' | 'expired';
    expires_at: string;
    created_at: string;
};

type CustomerOption = {
    id: number;
    name: string;
    phone: string | null;
};

const props = defineProps<{
    triggers: Trigger[];
    recentCoupons?: RecentCoupon[];
    customers?: CustomerOption[];
    summary?: {
        total_triggers: number;
        active_triggers: number;
        total_coupons: number;
        used_coupons: number;
        conversion_rate: number;
    };
    eventTypes: Record<string, string>;
    canManageDiscounts: boolean;
}>();

const activeTab = ref<'triggers' | 'logs'>('triggers');
const showCreate = ref(false);
const showEdit = ref(false);
const showTestFire = ref(false);
const editing = ref<Trigger | null>(null);
const selectedTestTrigger = ref<Trigger | null>(null);

const blankTrigger = () => ({
    name: '',
    event_type: 'first_order',
    milestone_count: null as number | null,
    discount_type: 'percent',
    discount_value: 10,
    validity_days: 7,
    max_discount_amount: null as number | null,
    send_email: true,
    message_template: '',
});

const form = useForm(blankTrigger());
const editForm = useForm(blankTrigger());
const testFireForm = useForm({
    customer_id: '' as number | '',
});

const nullableNumber = (val: unknown): number | null => {
    if (val === '' || val === null || val === undefined) {
        return null;
    }

    const parsed = Number(val);

    return Number.isFinite(parsed) ? parsed : null;
};

const payload = (data: ReturnType<typeof blankTrigger>) => ({
    ...data,
    milestone_count:
        data.event_type === 'order_milestone'
            ? nullableNumber(data.milestone_count)
            : null,
    max_discount_amount:
        data.discount_type === 'percent'
            ? nullableNumber(data.max_discount_amount)
            : null,
    message_template: data.message_template?.trim() || null,
});

function openCreate() {
    form.reset();
    form.clearErrors();
    showCreate.value = true;
}

function submit() {
    form.transform(payload).post('/promotions/triggers', {
        preserveScroll: true,
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
            toast.success('Đã tạo trigger tự động thành công.');
        },
    });
}

function openEdit(trigger: Trigger) {
    editing.value = trigger;
    editForm.clearErrors();
    editForm.name = trigger.name;
    editForm.event_type = trigger.event_type;
    editForm.milestone_count = trigger.milestone_count;
    editForm.discount_type = trigger.discount_type;
    editForm.discount_value = trigger.discount_value;
    editForm.validity_days = trigger.validity_days;
    editForm.max_discount_amount = trigger.max_discount_amount;
    editForm.send_email = trigger.send_email;
    editForm.message_template = trigger.message_template ?? '';
    showEdit.value = true;
}

function submitEdit() {
    if (!editing.value) {
        return;
    }

    editForm
        .transform(payload)
        .put(`/promotions/triggers/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showEdit.value = false;
                editing.value = null;
                toast.success('Đã cập nhật trigger.');
            },
        });
}

function toggleTrigger(trigger: Trigger) {
    router.patch(
        `/promotions/triggers/${trigger.id}/toggle`,
        {},
        { preserveScroll: true },
    );
}

function openTestFire(trigger: Trigger) {
    selectedTestTrigger.value = trigger;
    testFireForm.customer_id = props.customers?.[0]?.id ?? '';
    showTestFire.value = true;
}

function submitTestFire() {
    if (!selectedTestTrigger.value || !testFireForm.customer_id) {
        return;
    }

    testFireForm.post(`/promotions/triggers/${selectedTestTrigger.value.id}/test-fire`, {
        preserveScroll: true,
        onSuccess: () => {
            showTestFire.value = false;
            toast.success('Đã phát mã thử nghiệm thành công.');
        },
    });
}

async function removeTrigger(trigger: Trigger) {
    const generated =
        trigger.coupons_generated > 0
            ? `\n\nTrigger đã phát sinh ${trigger.coupons_generated} mã cho khách. Xóa trigger không thu hồi các mã đã gửi.`
            : '';

    if (
        !(await confirmDialog({
            title: 'Xác nhận xóa trigger',
            description: `Xóa trigger "${trigger.name}"? Hành động này không thể hoàn tác.${generated}`,
        }))
    ) {
        return;
    }

    router.delete(`/promotions/triggers/${trigger.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã xóa trigger.'),
    });
}

const eventEmojis: Record<string, string> = {
    first_order: '🎉',
    birthday: '🎂',
    inactive_30_days: '💤',
    loyalty_tier_upgrade: '⭐',
    order_milestone: '🏆',
};

const vnd = (val: number) => `${Math.round(val).toLocaleString('vi-VN')}₫`;
</script>

<template>
    <Head title="Trigger Khuyến mãi tự động" />

    <div class="flex flex-col gap-6 px-6 py-5">
        <!-- Header -->
        <div class="flex flex-col gap-4 border-b border-border/70 pb-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <BackButton fallback-href="/promotions" label="Khuyến mãi" />
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">
                        Trigger Khuyến mãi Tự động
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Tự động tạo & gửi mã giảm giá khi có sự kiện: đơn đầu, sinh nhật, 30 ngày không mua, lên VIP...
                    </p>
                </div>
            </div>
            <Button
                v-if="canManageDiscounts"
                @click="openCreate"
                class="gap-1.5 font-bold shadow-sm"
            >
                <Plus class="size-4" /> Tạo trigger mới
            </Button>
        </div>

        <!-- KPI Summary Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-primary/10 p-2.5 text-primary">
                        <Sparkles class="size-5" />
                    </div>
                    <div>
                        <p class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">Trigger đang bật</p>
                        <p class="text-xl font-bold text-foreground">
                            {{ summary?.active_triggers ?? 0 }} <span class="text-xs font-normal text-muted-foreground">/ {{ summary?.total_triggers ?? 0 }} quy tắc</span>
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-sky-500/10 p-2.5 text-sky-600">
                        <Ticket class="size-5" />
                    </div>
                    <div>
                        <p class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">Mã đã tạo tự động</p>
                        <p class="text-xl font-bold text-sky-600">
                            {{ summary?.total_coupons ?? 0 }} <span class="text-xs font-normal text-muted-foreground">mã</span>
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-emerald-500/10 p-2.5 text-emerald-600">
                        <Gift class="size-5" />
                    </div>
                    <div>
                        <p class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">Mã đã được dùng</p>
                        <p class="text-xl font-bold text-emerald-600">
                            {{ summary?.used_coupons ?? 0 }} <span class="text-xs font-normal text-muted-foreground">lượt</span>
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-amber-500/10 p-2.5 text-amber-600">
                        <PartyPopper class="size-5" />
                    </div>
                    <div>
                        <p class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase">Tỷ lệ đổi quà (Conversion)</p>
                        <p class="text-xl font-bold text-amber-600">
                            {{ summary?.conversion_rate ?? 0 }}%
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 border-b border-border/70 pb-3">
            <button
                type="button"
                @click="activeTab = 'triggers'"
                :class="[
                    'flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-bold transition-colors',
                    activeTab === 'triggers'
                        ? 'bg-primary text-primary-foreground shadow-sm'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                ]"
            >
                <Sparkles class="size-4" /> Quy tắc Trigger tự động ({{ triggers.length }})
            </button>
            <button
                type="button"
                @click="activeTab = 'logs'"
                :class="[
                    'flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-bold transition-colors',
                    activeTab === 'logs'
                        ? 'bg-primary text-primary-foreground shadow-sm'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                ]"
            >
                <Ticket class="size-4" /> Nhật ký phát mã tự động ({{ recentCoupons?.length ?? 0 }})
            </button>
        </div>

        <!-- Tab 1: Triggers Grid -->
        <div v-if="activeTab === 'triggers'">
            <div
                v-if="triggers.length"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Card
                    v-for="t in triggers"
                    :key="t.id"
                    class="transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                >
                    <CardContent class="p-5">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-2xl">{{ eventEmojis[t.event_type] ?? '⚡' }}</span>
                                <div>
                                    <h3 class="text-sm font-bold text-foreground">{{ t.name }}</h3>
                                    <p class="text-[11px] text-muted-foreground">
                                        {{ eventTypes[t.event_type] }}{{ t.milestone_count ? ` (${t.milestone_count} đơn)` : '' }}
                                    </p>
                                </div>
                            </div>
                            <span
                                :class="[
                                    'rounded-md px-2 py-0.5 text-[10px] font-bold',
                                    t.is_active
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                                ]"
                            >
                                {{ t.is_active ? 'Đang chạy' : 'Tạm dừng' }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-md bg-primary/10 px-2 py-1 font-bold text-primary">
                                {{ t.discount_type === 'percent' ? `Giảm ${t.discount_value}%` : `Giảm ${vnd(t.discount_value)}` }}
                            </span>
                            <span class="rounded-md bg-muted px-2 py-1 text-muted-foreground">Hạn {{ t.validity_days }} ngày</span>
                            <span v-if="t.send_email" class="rounded-md bg-sky-500/10 px-2 py-1 text-sky-600 dark:text-sky-400">📧 Gửi email</span>
                        </div>

                        <div class="mt-3 flex items-center justify-between border-t border-border/70 pt-3 text-[10px] text-muted-foreground">
                            <span>{{ t.coupons_generated }} mã đã tạo tự động</span>
                            <span>Tạo ngày {{ t.created_at }}</span>
                        </div>

                        <div v-if="canManageDiscounts" class="mt-3 flex items-center gap-1.5 border-t border-border/70 pt-2">
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-7 flex-1 gap-1 text-[10px] font-medium"
                                @click="openTestFire(t)"
                                title="Phát mã thử nghiệm cho 1 khách hàng"
                            >
                                <Play class="size-3 text-emerald-600" /> Thử nghiệm
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-7 gap-1 text-[10px] font-medium"
                                @click="toggleTrigger(t)"
                            >
                                <Power class="size-3" /> {{ t.is_active ? 'Tắt' : 'Bật' }}
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-7 gap-1 text-[10px] font-medium"
                                @click="openEdit(t)"
                            >
                                <Pencil class="size-3" /> Sửa
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="h-7 text-rose-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40"
                                @click="removeTrigger(t)"
                            >
                                <Trash2 class="size-3" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
            <Card v-else class="p-12 text-center">
                <Sparkles class="mx-auto size-10 text-muted-foreground/50" />
                <h3 class="mt-3 font-bold text-foreground">Chưa có trigger nào</h3>
                <p class="mt-1 text-xs text-muted-foreground">Bấm "Tạo trigger mới" để tự động gửi ưu đãi cho khách hàng.</p>
            </Card>
        </div>

        <!-- Tab 2: Recent Coupon Issuance Logs -->
        <div v-if="activeTab === 'logs'">
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-sm font-bold">Lịch sử phát mã & đổi quà từ Trigger</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="recentCoupons?.length" class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr class="border-b bg-muted/40 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                    <th class="p-3">Mã Coupon</th>
                                    <th class="p-3">Khách hàng</th>
                                    <th class="p-3">Quy tắc Trigger</th>
                                    <th class="p-3 text-right">Ưu đãi</th>
                                    <th class="p-3 text-center">Trạng thái</th>
                                    <th class="p-3 text-right">Ngày phát hành</th>
                                    <th class="p-3 text-right">Ngày hết hạn</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y border-border/70">
                                <tr v-for="coupon in recentCoupons" :key="coupon.id" class="transition-colors hover:bg-muted/20">
                                    <td class="p-3 font-mono font-bold text-primary">{{ coupon.code }}</td>
                                    <td class="p-3">
                                        <p class="font-bold text-foreground">{{ coupon.customer_name }}</p>
                                        <p class="text-[10px] text-muted-foreground">{{ coupon.customer_phone }}</p>
                                    </td>
                                    <td class="p-3">
                                        <p class="font-medium text-foreground">{{ coupon.trigger_name }}</p>
                                        <p class="text-[10px] text-muted-foreground">{{ eventTypes[coupon.event_type] ?? coupon.event_type }}</p>
                                    </td>
                                    <td class="p-3 text-right font-mono font-bold text-emerald-600">
                                        {{ coupon.discount_type === 'percent' ? `${coupon.discount_value}%` : `${vnd(coupon.discount_value)}` }}
                                    </td>
                                    <td class="p-3 text-center">
                                        <span v-if="coupon.status === 'used'" class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">Đã đổi quà</span>
                                        <span v-else-if="coupon.status === 'available'" class="rounded-full bg-sky-500/10 px-2 py-0.5 text-[10px] font-bold text-sky-600 dark:text-sky-400">Chưa sử dụng</span>
                                        <span v-else class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-bold text-muted-foreground">Đã hết hạn</span>
                                    </td>
                                    <td class="p-3 text-right font-mono text-muted-foreground">{{ coupon.created_at }}</td>
                                    <td class="p-3 text-right font-mono text-muted-foreground">{{ coupon.expires_at }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="p-10 text-center text-xs text-muted-foreground">
                        Chưa có lịch sử phát mã tự động nào.
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Create Dialog -->
    <Dialog v-model:open="showCreate">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Tạo Trigger Khuyến mãi Tự động</DialogTitle>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label>Tên Trigger *</Label>
                    <Input v-model="form.name" placeholder="Ví dụ: Chào mừng khách hàng mới" required />
                </div>
                <div class="grid gap-1.5">
                    <Label>Sự kiện kích hoạt *</Label>
                    <Select v-model="form.event_type">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="(label, key) in eventTypes" :key="key" :value="key">{{ label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div v-if="form.event_type === 'order_milestone'" class="grid gap-1.5">
                    <Label>Cột mốc (số đơn)</Label>
                    <Input v-model.number="form.milestone_count" type="number" min="1" placeholder="10" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Loại giảm</Label>
                        <Select v-model="form.discount_type">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="percent">% Chiết khấu</SelectItem>
                                <SelectItem value="fixed_amount">₫ Số tiền cố định</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Giá trị</Label>
                        <Input v-model.number="form.discount_value" type="number" min="0" required />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label>Thời hạn mã (ngày)</Label>
                    <Input v-model.number="form.validity_days" type="number" min="1" required />
                </div>
                <div v-if="form.discount_type === 'percent'" class="grid gap-1.5">
                    <Label>Giảm tối đa (₫)</Label>
                    <Input v-model.number="form.max_discount_amount" type="number" min="0" placeholder="Để trống nếu không giới hạn" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Nội dung tin nhắn gửi khách</Label>
                    <textarea
                        v-model="form.message_template"
                        rows="2"
                        placeholder="Cảm ơn quý khách! Tặng bạn mã giảm giá..."
                        class="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox id="create-send-email" v-model:checked="form.send_email" />
                    <Label for="create-send-email">Gửi email thông báo cho khách</Label>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showCreate = false">Hủy</Button>
                    <Button type="submit" :disabled="form.processing" class="font-bold">Tạo Trigger</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Edit Dialog -->
    <Dialog v-model:open="showEdit">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Chỉnh sửa Trigger</DialogTitle>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitEdit">
                <div class="grid gap-1.5">
                    <Label>Tên *</Label>
                    <Input v-model="editForm.name" required />
                </div>
                <div class="grid gap-1.5">
                    <Label>Sự kiện kích hoạt *</Label>
                    <Select v-model="editForm.event_type">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="(label, key) in eventTypes" :key="key" :value="key">{{ label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div v-if="editForm.event_type === 'order_milestone'" class="grid gap-1.5">
                    <Label>Cột mốc (số đơn)</Label>
                    <Input v-model.number="editForm.milestone_count" type="number" min="1" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Loại giảm</Label>
                        <Select v-model="editForm.discount_type">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="percent">% Chiết khấu</SelectItem>
                                <SelectItem value="fixed_amount">₫ Số tiền cố định</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Giá trị</Label>
                        <Input v-model.number="editForm.discount_value" type="number" min="0" />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label>Thời hạn mã (ngày)</Label>
                    <Input v-model.number="editForm.validity_days" type="number" min="1" />
                </div>
                <div v-if="editForm.discount_type === 'percent'" class="grid gap-1.5">
                    <Label>Giảm tối đa (₫)</Label>
                    <Input v-model.number="editForm.max_discount_amount" type="number" min="0" placeholder="Để trống nếu không giới hạn" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Nội dung tin nhắn gửi khách</Label>
                    <textarea
                        v-model="editForm.message_template"
                        rows="2"
                        placeholder="Cảm ơn quý khách! Tặng bạn mã giảm giá..."
                        class="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox id="edit-send-email" v-model:checked="editForm.send_email" />
                    <Label for="edit-send-email">Gửi email thông báo</Label>
                </div>
                <DialogFooter>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
