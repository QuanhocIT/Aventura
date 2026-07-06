<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Bell, Gift, Plus, Trash2, Zap } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    triggers: Array<{
        id: number; name: string; event_type: string; milestone_count: number | null;
        discount_type: string; discount_value: number; validity_days: number;
        send_email: boolean; is_active: boolean; coupons_generated: number;
        creator: string | null; created_at: string;
    }>;
    eventTypes: Record<string, string>;
}>();

const showCreate = ref(false);
const form = useForm({
    name: '', event_type: 'first_order', milestone_count: '',
    discount_type: 'percent', discount_value: 10, validity_days: 7,
    max_discount_amount: '', send_email: true, message_template: '',
});

function submit() {
    form.post('/promotions/triggers', {
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
}

const eventEmojis: Record<string, string> = {
    first_order: '🎉', birthday: '🎂', inactive_30_days: '💤',
    loyalty_tier_upgrade: '⭐', order_milestone: '🏆',
};
</script>

<template>
    <Head title="Trigger Khuyến mãi tự động" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Trigger Khuyến mãi Tự động</h1>
                <p class="text-sm text-muted-foreground">Tự động tạo & gửi mã giảm giá khi có sự kiện: đơn đầu, sinh nhật, lên VIP...</p>
            </div>
            <Button @click="showCreate = true" class="gap-1.5"><Plus class="size-4" /> Tạo trigger</Button>
        </div>

        <div v-if="triggers.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="t in triggers" :key="t.id" class="transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg">
                <CardContent class="p-5">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">{{ eventEmojis[t.event_type] ?? '⚡' }}</span>
                            <div>
                                <h3 class="font-bold text-sm">{{ t.name }}</h3>
                                <p class="text-[11px] text-muted-foreground">{{ eventTypes[t.event_type] }}{{ t.milestone_count ? ` (${t.milestone_count} đơn)` : '' }}</p>
                            </div>
                        </div>
                        <span :class="['rounded-md px-2 py-0.5 text-[10px] font-bold', t.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400']">
                            {{ t.is_active ? 'Active' : 'Tắt' }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        <span class="rounded-md bg-primary/10 px-2 py-1 font-bold text-primary">
                            {{ t.discount_type === 'percent' ? `${t.discount_value}%` : `${t.discount_value}₫` }}
                        </span>
                        <span class="rounded-md bg-muted px-2 py-1 text-muted-foreground">{{ t.validity_days }} ngày</span>
                        <span v-if="t.send_email" class="rounded-md bg-sky-100 px-2 py-1 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300">📧 Email</span>
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t pt-3 text-[10px] text-muted-foreground">
                        <span>{{ t.coupons_generated }} mã đã tạo</span>
                        <span>{{ t.created_at }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-16 text-center">
            <Bell class="size-12 text-muted-foreground/40 mb-3" />
            <p class="font-semibold text-muted-foreground">Chưa có trigger nào</p>
            <p class="text-xs text-muted-foreground/70 mt-1">Tạo trigger để tự động gửi mã giảm giá cho khách hàng.</p>
        </div>
    </div>

    <Dialog v-model:open="showCreate">
        <DialogContent class="max-w-md">
            <DialogHeader><DialogTitle>Tạo trigger mới</DialogTitle></DialogHeader>
            <form @submit.prevent="submit" class="grid gap-4 py-2">
                <div class="grid gap-1.5">
                    <Label>Tên *</Label>
                    <Input v-model="form.name" placeholder="Chào mừng khách mới" required />
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
                                <SelectItem value="percent">%</SelectItem>
                                <SelectItem value="fixed_amount">₫</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Giá trị</Label>
                        <Input v-model.number="form.discount_value" type="number" min="0" />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label>Thời hạn mã (ngày)</Label>
                    <Input v-model.number="form.validity_days" type="number" min="1" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox v-model:checked="form.send_email" id="send-email" />
                    <Label for="send-email">Gửi email thông báo</Label>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showCreate = false">Hủy</Button>
                    <Button type="submit" :disabled="form.processing">Tạo trigger</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
