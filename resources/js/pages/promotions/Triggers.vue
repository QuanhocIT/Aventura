<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Bell, Pencil, Plus, Power, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import BackButton from '@/components/BackButton.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

defineProps<{
    triggers: Trigger[];
    eventTypes: Record<string, string>;
    canManageDiscounts: boolean;
}>();

const showCreate = ref(false);
const showEdit = ref(false);
const editing = ref<Trigger | null>(null);

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

/** Ép chuỗi rỗng của <input type="number"> về null cho rule `nullable`. */
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
        },
    });
}

// Ba route dưới đây đã tồn tại từ đầu nhưng không có nút nào gọi tới:
// promotions.triggers.update / .destroy / .toggle
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
</script>

<template>
    <Head title="Trigger Khuyến mãi tự động" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <BackButton fallback-href="/promotions" label="Khuyến mãi" />
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Trigger Khuyến mãi Tự động
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Tự động tạo & gửi mã giảm giá khi có sự kiện: đơn đầu,
                        sinh nhật, lên VIP...
                    </p>
                </div>
            </div>
            <Button
                v-if="canManageDiscounts"
                @click="openCreate"
                class="gap-1.5"
                ><Plus class="size-4" /> Tạo trigger</Button
            >
        </div>

        <div
            v-if="triggers.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
            <Card
                v-for="t in triggers"
                :key="t.id"
                class="transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
            >
                <CardContent class="p-5">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">{{
                                eventEmojis[t.event_type] ?? '⚡'
                            }}</span>
                            <div>
                                <h3 class="text-sm font-bold">{{ t.name }}</h3>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ eventTypes[t.event_type]
                                    }}{{
                                        t.milestone_count
                                            ? ` (${t.milestone_count} đơn)`
                                            : ''
                                    }}
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
                            {{ t.is_active ? 'Active' : 'Tắt' }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        <span
                            class="rounded-md bg-primary/10 px-2 py-1 font-bold text-primary"
                        >
                            {{
                                t.discount_type === 'percent'
                                    ? `${t.discount_value}%`
                                    : `${t.discount_value}₫`
                            }}
                        </span>
                        <span
                            class="rounded-md bg-muted px-2 py-1 text-muted-foreground"
                            >{{ t.validity_days }} ngày</span
                        >
                        <span
                            v-if="t.send_email"
                            class="rounded-md bg-sky-100 px-2 py-1 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300"
                            >📧 Email</span
                        >
                    </div>

                    <div
                        class="mt-3 flex items-center justify-between border-t pt-3 text-[10px] text-muted-foreground"
                    >
                        <span>{{ t.coupons_generated }} mã đã tạo</span>
                        <span>{{ t.created_at }}</span>
                    </div>

                    <!-- Trước đây card chỉ hiển thị badge Active/Tắt: 3 route
                         update/destroy/toggle tồn tại mà không có nút nào gọi. -->
                    <div
                        v-if="canManageDiscounts"
                        class="mt-2 flex items-center gap-1.5 border-t pt-2"
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-7 flex-1 gap-1 text-[10px]"
                            @click="toggleTrigger(t)"
                        >
                            <Power class="size-3" />
                            {{ t.is_active ? 'Tạm dừng' : 'Kích hoạt' }}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-7 gap-1 text-[10px]"
                            @click="openEdit(t)"
                        >
                            <Pencil class="size-3" /> Sửa
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-7 px-2 text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/30"
                            title="Xóa trigger"
                            @click="removeTrigger(t)"
                        >
                            <Trash2 class="size-3" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div
            v-else
            class="flex flex-col items-center justify-center py-16 text-center"
        >
            <Bell class="mb-3 size-12 text-muted-foreground/40" />
            <p class="font-semibold text-muted-foreground">
                Chưa có trigger nào
            </p>
            <p class="mt-1 text-xs text-muted-foreground/70">
                Tạo trigger để tự động gửi mã giảm giá cho khách hàng.
            </p>
        </div>
    </div>

    <Dialog v-model:open="showCreate">
        <DialogContent class="max-w-md">
            <DialogHeader
                ><DialogTitle>Tạo trigger mới</DialogTitle></DialogHeader
            >
            <form @submit.prevent="submit" class="grid gap-4 py-2">
                <div class="grid gap-1.5">
                    <Label>Tên *</Label>
                    <Input
                        v-model="form.name"
                        placeholder="Chào mừng khách mới"
                        required
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label>Sự kiện kích hoạt *</Label>
                    <Select v-model="form.event_type">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(label, key) in eventTypes"
                                :key="key"
                                :value="key"
                                >{{ label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div
                    v-if="form.event_type === 'order_milestone'"
                    class="grid gap-1.5"
                >
                    <Label>Cột mốc (số đơn)</Label>
                    <Input
                        v-model.number="form.milestone_count"
                        type="number"
                        min="1"
                        placeholder="10"
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Loại giảm</Label>
                        <Select v-model="form.discount_type">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="percent">%</SelectItem>
                                <SelectItem value="fixed_amount">₫</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Giá trị</Label>
                        <Input
                            v-model.number="form.discount_value"
                            type="number"
                            min="0"
                        />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label>Thời hạn mã (ngày)</Label>
                    <Input
                        v-model.number="form.validity_days"
                        type="number"
                        min="1"
                    />
                </div>
                <div
                    v-if="form.discount_type === 'percent'"
                    class="grid gap-1.5"
                >
                    <Label>Giảm tối đa (₫)</Label>
                    <Input
                        v-model.number="form.max_discount_amount"
                        type="number"
                        min="0"
                        placeholder="Để trống nếu không giới hạn"
                    />
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
                    <Checkbox
                        v-model:checked="form.send_email"
                        id="send-email"
                    />
                    <Label for="send-email">Gửi email thông báo</Label>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showCreate = false"
                        >Hủy</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Tạo trigger</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="showEdit">
        <DialogContent class="max-w-md">
            <DialogHeader
                ><DialogTitle>Chỉnh sửa trigger</DialogTitle></DialogHeader
            >
            <form @submit.prevent="submitEdit" class="grid gap-4 py-2">
                <div class="grid gap-1.5">
                    <Label>Tên *</Label>
                    <Input v-model="editForm.name" required />
                </div>
                <div class="grid gap-1.5">
                    <Label>Sự kiện kích hoạt *</Label>
                    <Select v-model="editForm.event_type">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(label, key) in eventTypes"
                                :key="key"
                                :value="key"
                                >{{ label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div
                    v-if="editForm.event_type === 'order_milestone'"
                    class="grid gap-1.5"
                >
                    <Label>Cột mốc (số đơn)</Label>
                    <Input
                        v-model.number="editForm.milestone_count"
                        type="number"
                        min="1"
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Loại giảm</Label>
                        <Select v-model="editForm.discount_type">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="percent">%</SelectItem>
                                <SelectItem value="fixed_amount">₫</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Giá trị</Label>
                        <Input
                            v-model.number="editForm.discount_value"
                            type="number"
                            min="0"
                        />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label>Thời hạn mã (ngày)</Label>
                    <Input
                        v-model.number="editForm.validity_days"
                        type="number"
                        min="1"
                    />
                </div>
                <div
                    v-if="editForm.discount_type === 'percent'"
                    class="grid gap-1.5"
                >
                    <Label>Giảm tối đa (₫)</Label>
                    <Input
                        v-model.number="editForm.max_discount_amount"
                        type="number"
                        min="0"
                        placeholder="Để trống nếu không giới hạn"
                    />
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
                    <Checkbox
                        v-model:checked="editForm.send_email"
                        id="edit-send-email"
                    />
                    <Label for="edit-send-email">Gửi email thông báo</Label>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showEdit = false"
                        >Hủy</Button
                    >
                    <Button type="submit" :disabled="editForm.processing"
                        >Lưu thay đổi</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
