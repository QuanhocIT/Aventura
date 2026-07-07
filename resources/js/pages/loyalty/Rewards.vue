<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Gift, Plus, Pencil, Trash2, ToggleLeft, ToggleRight, Loader2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    rewards: any[];
}>();

const page = usePage();
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) {
toast.success(flash.success);
}

    if (flash?.error) {
toast.error(flash.error);
}
});

const showDialog = ref(false);
const editing = ref<any>(null);
const form = useForm({
    name: '', description: '', type: 'voucher' as string,
    value: 0, product_id: null as number | null, points_cost: 100,
    max_redemptions: null as number | null, is_active: true, image_url: '',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.type = 'voucher';
    form.is_active = true;
    form.points_cost = 100;
    showDialog.value = true;
}

function openEdit(r: any) {
    editing.value = r;
    form.name = r.name;
    form.description = r.description ?? '';
    form.type = r.type;
    form.value = r.value;
    form.product_id = r.product_id;
    form.points_cost = r.points_cost;
    form.max_redemptions = r.max_redemptions;
    form.is_active = r.is_active;
    form.image_url = r.image_url ?? '';
    showDialog.value = true;
}

function submit() {
    if (editing.value) {
        form.patch(`/loyalty/rewards/${editing.value.id}`, { onSuccess: () => {
 showDialog.value = false; 
} });
    } else {
        form.post('/loyalty/rewards', { onSuccess: () => {
 showDialog.value = false; form.reset(); 
} });
    }
}

function toggleReward(r: any) {
    router.patch(`/loyalty/rewards/${r.id}/toggle`);
}

async function deleteReward(r: any) {
    if (!(await confirmDialog({ title: 'Xác nhận thao tác', description: `Xóa phần thưởng "${r.name}"?` }))) {
return;
}

    router.delete(`/loyalty/rewards/${r.id}`);
}

const typeLabel: Record<string, string> = {
    voucher: 'Voucher', free_product: 'Món miễn phí',
    discount_percent: 'Giảm %', discount_fixed: 'Giảm cố định',
};
</script>

<template>
    <Head title="Phần thưởng Loyalty" />

    <div class="flex flex-col gap-6 p-6 max-w-5xl mx-auto">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <Gift class="size-6 text-amber-500" />
                <h1 class="text-2xl font-bold">Quản lý Phần thưởng</h1>
            </div>
            <Button @click="openCreate" class="gap-1.5">
                <Plus class="size-4" /> Thêm phần thưởng
            </Button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <Card v-for="r in rewards" :key="r.id" :class="{ 'opacity-60': !r.is_active }">
                <CardHeader class="pb-2">
                    <div class="flex items-start justify-between">
                        <CardTitle class="text-sm font-semibold">{{ r.name }}</CardTitle>
                        <Badge :variant="r.is_active ? 'default' : 'secondary'" class="text-xs">
                            {{ r.is_active ? 'Hoạt động' : 'Tạm ngừng' }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-2">
                    <p class="text-xs text-muted-foreground">{{ r.description || 'Không có mô tả' }}</p>
                    <div class="flex items-center justify-between text-xs">
                        <Badge variant="outline">{{ typeLabel[r.type] ?? r.type }}</Badge>
                        <span class="font-bold text-amber-600">{{ r.points_cost }} điểm</span>
                    </div>
                    <div v-if="r.max_redemptions" class="text-xs text-muted-foreground">
                        Đã đổi: {{ r.current_redemptions }}/{{ r.max_redemptions }}
                    </div>
                    <div class="flex items-center gap-1 pt-2 border-t">
                        <Button variant="ghost" size="sm" @click="openEdit(r)"><Pencil class="size-3.5" /></Button>
                        <Button variant="ghost" size="sm" @click="toggleReward(r)">
                            <ToggleRight v-if="r.is_active" class="size-3.5" />
                            <ToggleLeft v-else class="size-3.5" />
                        </Button>
                        <Button variant="ghost" size="sm" class="text-red-500" @click="deleteReward(r)"><Trash2 class="size-3.5" /></Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <p v-if="!rewards.length" class="text-center text-muted-foreground py-12">
            Chưa có phần thưởng nào. Nhấn "Thêm phần thưởng" để bắt đầu.
        </p>
    </div>

    <!-- Create/Edit Dialog -->
    <Dialog v-model:open="showDialog">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ editing ? 'Sửa phần thưởng' : 'Thêm phần thưởng' }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid gap-1.5">
                    <Label>Tên phần thưởng</Label>
                    <Input v-model="form.name" placeholder="Ví dụ: Giảm 20% đơn tiếp theo" required />
                </div>
                <div class="grid gap-1.5">
                    <Label>Mô tả</Label>
                    <Input v-model="form.description" placeholder="Mô tả ngắn..." />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Loại</Label>
                        <select v-model="form.type" class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                            <option value="voucher">Voucher</option>
                            <option value="free_product">Món miễn phí</option>
                            <option value="discount_percent">Giảm %</option>
                            <option value="discount_fixed">Giảm cố định</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Giá trị</Label>
                        <Input type="number" step="0.01" v-model="form.value" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Điểm cần đổi</Label>
                        <Input type="number" v-model="form.points_cost" required />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Giới hạn lượt đổi</Label>
                        <Input type="number" v-model="form.max_redemptions" placeholder="Không giới hạn" />
                    </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="form.is_active" class="rounded" />
                    <span class="text-sm">Kích hoạt ngay</span>
                </label>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="showDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="size-4 animate-spin mr-1" />
                        {{ form.processing ? 'Đang lưu...' : (editing ? 'Cập nhật' : 'Tạo mới') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
