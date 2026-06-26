<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Award, Plus, Save, Trash2, Pencil, Loader2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    program: any;
    tiers: any[];
}>();

const page = usePage();
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
});

const programForm = useForm({
    is_active: props.program?.is_active ?? true,
    points_per_vnd: props.program?.points_per_vnd ?? 0.0001,
    point_value_vnd: props.program?.point_value_vnd ?? 100,
    points_expiry_days: props.program?.points_expiry_days ?? null,
    min_redeem_points: props.program?.min_redeem_points ?? 10,
    birthday_bonus_points: props.program?.birthday_bonus_points ?? 50,
    enable_qr_card: props.program?.enable_qr_card ?? true,
});

function saveProgram() {
    programForm.post('/loyalty/settings', { preserveScroll: true });
}

// Tier CRUD
const showTierDialog = ref(false);
const editingTier = ref<any>(null);
const tierForm = useForm({
    name: '', min_spent: 0, discount_percent: 0, points_multiplier: 1.0,
    benefits_json: null as any, color: '#6366f1', display_order: 0,
});

function openCreateTier() {
    editingTier.value = null;
    tierForm.reset();
    tierForm.color = '#6366f1';
    tierForm.points_multiplier = 1.0;
    showTierDialog.value = true;
}

function openEditTier(tier: any) {
    editingTier.value = tier;
    tierForm.name = tier.name;
    tierForm.min_spent = tier.min_spent;
    tierForm.discount_percent = tier.discount_percent;
    tierForm.points_multiplier = tier.points_multiplier;
    tierForm.benefits_json = tier.benefits_json;
    tierForm.color = tier.color || '#6366f1';
    tierForm.display_order = tier.display_order;
    showTierDialog.value = true;
}

function submitTier() {
    if (editingTier.value) {
        tierForm.patch(`/loyalty/tiers/${editingTier.value.id}`, {
            onSuccess: () => { showTierDialog.value = false; },
        });
    } else {
        tierForm.post('/loyalty/tiers', {
            onSuccess: () => { showTierDialog.value = false; tierForm.reset(); },
        });
    }
}

function deleteTier(tier: any) {
    if (!confirm(`Xóa hạng "${tier.name}"?`)) return;
    router.delete(`/loyalty/tiers/${tier.id}`);
}
</script>

<template>
    <Head title="Cấu hình Loyalty" />

    <div class="flex flex-col gap-6 p-6 max-w-4xl mx-auto">
        <div class="flex items-center gap-3">
            <Award class="size-6 text-amber-500" />
            <h1 class="text-2xl font-bold">Cấu hình Chương trình Loyalty</h1>
        </div>

        <!-- Program Settings -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Thiết lập chung</CardTitle>
                <CardDescription>Cấu hình quy tắc tích điểm, quy đổi, và hết hạn.</CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="saveProgram" class="space-y-5">
                    <div class="flex items-center gap-3">
                        <Label for="is_active" class="font-bold">Trạng thái</Label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="programForm.is_active" class="rounded" />
                            <span class="text-sm">{{ programForm.is_active ? 'Đang hoạt động' : 'Tạm ngừng' }}</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label>Tỷ lệ tích điểm (điểm/VND)</Label>
                            <Input type="number" step="0.00001" v-model="programForm.points_per_vnd" />
                            <span class="text-xs text-muted-foreground">Ví dụ: 0.0001 = 1 điểm mỗi 10,000 VND</span>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Giá trị 1 điểm (VND)</Label>
                            <Input type="number" v-model="programForm.point_value_vnd" />
                            <span class="text-xs text-muted-foreground">Khi quy đổi điểm thành tiền giảm giá</span>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Thời hạn điểm (ngày)</Label>
                            <Input type="number" v-model="programForm.points_expiry_days" placeholder="Để trống = không hết hạn" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Tối thiểu để đổi (điểm)</Label>
                            <Input type="number" v-model="programForm.min_redeem_points" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Điểm thưởng sinh nhật</Label>
                            <Input type="number" v-model="programForm.birthday_bonus_points" />
                        </div>
                        <div class="flex items-end gap-2">
                            <label class="flex items-center gap-2 cursor-pointer pb-2">
                                <input type="checkbox" v-model="programForm.enable_qr_card" class="rounded" />
                                <span class="text-sm font-medium">Bật thẻ thành viên QR</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="programForm.processing" class="gap-2">
                            <Loader2 v-if="programForm.processing" class="size-4 animate-spin" />
                            <Save v-else class="size-4" />
                            {{ programForm.processing ? 'Đang lưu...' : 'Lưu cấu hình' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Tiers -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
                    <CardTitle class="text-base">Hạng thành viên</CardTitle>
                    <CardDescription>Thiết lập các bậc hạng dựa trên chi tiêu tích lũy.</CardDescription>
                </div>
                <Button @click="openCreateTier" class="gap-1.5" size="sm">
                    <Plus class="size-4" /> Thêm hạng
                </Button>
            </CardHeader>
            <CardContent>
                <table v-if="tiers.length" class="w-full text-sm">
                    <thead class="border-b">
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="pb-2 font-medium">Hạng</th>
                            <th class="pb-2 font-medium">Chi tiêu tối thiểu</th>
                            <th class="pb-2 font-medium">Giảm giá</th>
                            <th class="pb-2 font-medium">Nhân điểm</th>
                            <th class="pb-2 font-medium text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in tiers" :key="t.id" class="border-b last:border-0">
                            <td class="py-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: t.color || '#94a3b8' }"></span>
                                    <span class="font-medium">{{ t.name }}</span>
                                </div>
                            </td>
                            <td class="py-3">{{ Number(t.min_spent).toLocaleString() }}đ</td>
                            <td class="py-3">{{ t.discount_percent }}%</td>
                            <td class="py-3">x{{ t.points_multiplier }}</td>
                            <td class="py-3 text-right">
                                <Button variant="ghost" size="sm" @click="openEditTier(t)"><Pencil class="size-3.5" /></Button>
                                <Button variant="ghost" size="sm" class="text-red-500" @click="deleteTier(t)"><Trash2 class="size-3.5" /></Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-sm text-muted-foreground text-center py-6">Chưa có hạng thành viên nào. Nhấn "Thêm hạng" để bắt đầu.</p>
            </CardContent>
        </Card>
    </div>

    <!-- Tier Create/Edit Dialog -->
    <Dialog v-model:open="showTierDialog">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ editingTier ? 'Sửa hạng thành viên' : 'Thêm hạng thành viên' }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitTier" class="space-y-4">
                <div class="grid gap-1.5">
                    <Label>Tên hạng</Label>
                    <Input v-model="tierForm.name" placeholder="Ví dụ: Gold" required />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Chi tiêu tối thiểu (VND)</Label>
                        <Input type="number" v-model="tierForm.min_spent" required />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Giảm giá (%)</Label>
                        <Input type="number" step="0.1" v-model="tierForm.discount_percent" required />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Nhân điểm (x)</Label>
                        <Input type="number" step="0.1" v-model="tierForm.points_multiplier" required />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Thứ tự hiển thị</Label>
                        <Input type="number" v-model="tierForm.display_order" required />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label>Màu sắc</Label>
                    <input type="color" v-model="tierForm.color" class="h-9 w-full rounded-md border cursor-pointer" />
                </div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="showTierDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="tierForm.processing">
                        {{ tierForm.processing ? 'Đang lưu...' : (editingTier ? 'Cập nhật' : 'Tạo mới') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
