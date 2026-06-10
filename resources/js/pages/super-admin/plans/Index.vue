<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Check, Crown, Edit2, Plus, Save, Star, Users, X, Zap } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface Plan {
    id: number; code: string; name: string; price: number;
    billing_cycle: string; max_branches: number; max_tables: number; max_users: number;
    features: Record<string, any>; status: string; restaurants_count: number;
}

defineProps<{ plans: Plan[] }>();

const editingId = ref<number | null>(null);
const isEditing = ref(false);

const isCreating = ref(false);
const createForm = useForm({
    code: '',
    name: '',
    description: '',
    billing_cycle: 'monthly',
    price: 0,
    max_branches: 1,
    max_tables: 15,
    max_users: 5,
    max_areas: 2,
    max_storage_mb: 500,
    api_rate_limit: 30,
    kitchen_display:    false,
    qr_ordering:        false,
    inventory_basic:    false,
    hr_timekeeping:     false,
    hr_full:            false,
    advanced_analytics: false,
    realtime:           false,
    fraud_detection:    false,
    email_reports:      false,
    ai_advisor:         false,
    supplier_portal:    false,
    ai_forecasting:     false,
    api_access:         false,
});

function submitCreate() {
    createForm.post(route('superadmin.plans.store'), {
        onSuccess: () => {
            isCreating.value = false;
            createForm.reset();
        },
    });
}

const selectedPlanForRestaurants = ref<Plan | null>(null);
const restaurants = ref<any[]>([]);
const isLoadingRestaurants = ref(false);

const form = useForm({
    name: '',
    description: '',
    price: 0,
    max_branches: 1,
    max_tables: 15,
    max_users: 5,
    max_areas: 2,
    max_storage_mb: 500,
    api_rate_limit: 30,
    kitchen_display:    false,
    qr_ordering:        false,
    inventory_basic:    false,
    hr_timekeeping:     false,
    hr_full:            false,
    advanced_analytics: false,
    realtime:           false,
    fraud_detection:    false,
    email_reports:      false,
    ai_advisor:         false,
    supplier_portal:    false,
    ai_forecasting:     false,
    api_access:         false,
});

const toForm = (v: number | null) => (v === null ? -1 : v);

function startEdit(plan: Plan) {
    editingId.value = plan.id;
    isEditing.value = true;
    form.name               = plan.name;
    form.description        = plan.features?.description ?? planNotes[plan.code] ?? '';
    form.price              = plan.price;
    form.max_branches       = toForm(plan.max_branches);
    form.max_tables         = toForm(plan.max_tables);
    form.max_users          = toForm(plan.max_users);
    form.max_areas          = plan.features?.max_areas ?? 2;
    form.max_storage_mb     = plan.features?.max_storage_mb ?? 500;
    form.api_rate_limit     = plan.features?.api_rate_limit ?? 30;
    form.kitchen_display    = plan.features?.kitchen_display ?? false;
    form.qr_ordering        = plan.features?.qr_ordering ?? false;
    form.inventory_basic    = plan.features?.inventory_basic ?? false;
    form.hr_timekeeping     = plan.features?.hr_timekeeping ?? false;
    form.hr_full            = plan.features?.hr_full ?? false;
    form.advanced_analytics = plan.features?.advanced_analytics ?? false;
    form.realtime           = plan.features?.realtime ?? false;
    form.fraud_detection    = plan.features?.fraud_detection ?? false;
    form.email_reports      = plan.features?.email_reports ?? false;
    form.ai_advisor         = plan.features?.ai_advisor ?? false;
    form.supplier_portal    = plan.features?.supplier_portal ?? false;
    form.ai_forecasting     = plan.features?.ai_forecasting ?? false;
    form.api_access         = plan.features?.api_access ?? false;
}

function save(planId: number) {
    form.patch(`/super-admin/plans/${planId}`, {
        onSuccess: () => {
            editingId.value = null; 
            isEditing.value = false;
        },
    });
}

async function showRestaurants(plan: Plan) {
    selectedPlanForRestaurants.value = plan;
    isLoadingRestaurants.value = true;
    restaurants.value = [];

    try {
        const response = await fetch(`/super-admin/plans/${plan.id}/restaurants`);
        const data = await response.json();
        restaurants.value = data.restaurants || [];
    } catch (e) {
        console.error('Error fetching plan restaurants:', e);
    } finally {
        isLoadingRestaurants.value = false;
    }
}

const ALL_FEATURES: { key: string; label: string }[] = [
    { key: 'kitchen_display',    label: 'Màn hình Bếp (Kitchen Display)' },
    { key: 'qr_ordering',        label: 'Đặt món qua QR' },
    { key: 'inventory_basic',    label: 'Quản lý Tồn kho' },
    { key: 'hr_timekeeping',     label: 'Chấm công & Lịch làm việc' },
    { key: 'hr_full',            label: 'Lương & Nhân sự đầy đủ' },
    { key: 'advanced_analytics', label: 'Báo cáo Nâng cao' },
    { key: 'realtime',           label: 'Cập nhật thời gian thực' },
    { key: 'fraud_detection',    label: 'Phát hiện Gian lận' },
    { key: 'email_reports',      label: 'Email Báo cáo tự động' },
    { key: 'ai_advisor',         label: 'AI Tư vấn chiến lược' },
    { key: 'supplier_portal',    label: 'Cổng Nhà cung cấp (Supplier)' },
    { key: 'ai_forecasting',     label: 'AI Dự báo Tồn kho' },
    { key: 'api_access',         label: 'Truy cập API' },
];

function planFeatures(plan: Plan): string[] {
    const lim = (v: number | null, unit: string) =>
        v === null || v === -1 ? `Không giới hạn ${unit}` : `${v} ${unit}`;
    const mb   = plan.features?.max_storage_mb ?? 500;
    const rate = plan.features?.api_rate_limit ?? 30;

    const list = [
        lim(plan.max_branches, 'chi nhánh'),
        lim(plan.max_tables, 'bàn'),
        lim(plan.max_users, 'nhân viên'),
        mb >= 1024 ? `${mb / 1024} GB lưu trữ` : `${mb} MB lưu trữ`,
        `API: ${new Intl.NumberFormat('vi-VN').format(rate)} req/phút`,
    ];

    for (const f of ALL_FEATURES) {
        if (plan.features?.[f.key]) list.push(f.label);
    }

    return list;
}

function planUnsupported(plan: Plan): string[] {
    return ALL_FEATURES
        .filter(f => !plan.features?.[f.key])
        .map(f => f.label);
}

function formatVnd(v: number) {
    return v === 0 ? '0đ' : new Intl.NumberFormat('vi-VN').format(v) + 'đ';
}

const planNotes: Record<string, string> = {
    free:       'Gói cơ bản, trải nghiệm POS miễn phí.',
    starter:    'Đầy đủ vận hành: bếp, QR, chấm công, tồn kho.',
    pro:        'Nâng cao toàn diện: AI, nhân sự, báo cáo, chống gian lận.',
    enterprise: 'Giải pháp doanh nghiệp: nhà cung cấp, AI dự báo, API không giới hạn.',
};

const planIcon: Record<string, any> = {
    free: Star, starter: Check, pro: Crown, enterprise: Users,
};
</script>

<template>
    <Head title="Quản lý gói dịch vụ" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Gói dịch vụ</h1>
                <p class="text-sm text-muted-foreground">
                    Thay đổi ở đây sẽ hiển thị ngay trên trang khách hàng
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button size="sm" variant="outline" @click="isCreating = true">
                    <Plus class="size-4 mr-1.5" />
                    Tạo gói mới
                </Button>
                <Badge variant="outline" class="gap-1.5">
                    <span class="size-2 rounded-full bg-green-500 animate-pulse inline-block" />
                    Đồng bộ trang khách
                </Badge>
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            <div v-for="plan in plans" :key="plan.id" class="flex flex-col gap-0">
                <Card
                    class="flex flex-col justify-between border-border transition-all duration-200 hover:shadow-md h-full"
                    :class="{
                        'border-2 border-primary shadow-sm': plan.code === 'pro',
                        'border-2 border-violet-500/80 bg-gradient-to-b from-violet-500/5 to-transparent': plan.code === 'enterprise',
                    }"
                >
                    <CardHeader>
                        <div class="mb-2 flex items-center justify-between">
                            <CardTitle
                                class="flex items-center gap-1.5 text-2xl font-bold"
                                :class="{
                                    'text-primary': plan.code === 'pro',
                                    'text-violet-500': plan.code === 'enterprise',
                                }"
                            >
                                <component :is="planIcon[plan.code] ?? Star" class="size-5" />
                                {{ plan.name }}
                            </CardTitle>
                            <div class="flex items-center gap-1">
                                <Badge v-if="plan.code === 'free'" variant="secondary">Mặc định</Badge>
                                <Badge v-else-if="plan.code === 'pro'">Khuyến nghị</Badge>
                                <Badge v-else-if="plan.code === 'enterprise'" class="bg-violet-600 text-white">VIP</Badge>
                                <Button variant="ghost" size="icon" class="size-7 ml-1" @click="startEdit(plan)">
                                    <Edit2 class="size-3.5 text-muted-foreground" />
                                </Button>
                            </div>
                        </div>

                        <div class="flex items-end gap-1">
                            <span
                                class="text-3xl font-extrabold"
                                :class="{
                                    'text-primary': plan.code === 'pro',
                                    'text-violet-500': plan.code === 'enterprise',
                                }"
                            >
                                {{ formatVnd(plan.price) }}
                            </span>
                            <span class="pb-1 text-xs text-muted-foreground">/tháng</span>
                        </div>

                        <CardDescription class="mt-2 min-h-[36px] text-xs">
                            {{ plan.features?.description || planNotes[plan.code] || '' }}
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex-grow space-y-1.5 text-xs">
                        <p v-for="feat in planFeatures(plan)" :key="feat" class="flex items-center gap-2">
                            <Check
                                class="size-4 flex-shrink-0 text-emerald-500"
                                :class="{
                                    'text-primary': plan.code === 'pro',
                                    'text-violet-500': plan.code === 'enterprise',
                                }"
                            />
                            {{ feat }}
                        </p>
                        <p
                            v-for="unfeat in planUnsupported(plan)"
                            :key="unfeat"
                            class="flex items-center gap-2 text-muted-foreground opacity-60"
                        >
                            <X class="size-4 flex-shrink-0" />
                            {{ unfeat }}
                        </p>
                    </CardContent>

                    <!-- Meta footer -->
                    <div class="px-6 pb-4 pt-2 border-t border-border mt-3">
                        <div class="text-xs text-muted-foreground flex items-center justify-between">
                            <button 
                                @click="showRestaurants(plan)" 
                                class="text-xs text-muted-foreground hover:text-primary hover:underline transition-colors flex items-center gap-1 cursor-pointer"
                            >
                                {{ plan.restaurants_count }} nhà hàng đang dùng
                            </button>
                            <span class="font-mono text-[10px] bg-muted px-1.5 py-0.5 rounded">{{ plan.code }}</span>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <!-- Info note -->
        <p class="text-xs text-muted-foreground text-center">
            Thay đổi giá hoặc tính năng sẽ ảnh hưởng ngay đến trang khách hàng — không cần deploy lại.
        </p>

        <!-- ── PLAN EDIT SHEET (DRAWER) ── -->
        <Sheet v-model:open="isEditing">
            <SheetContent class="sm:max-w-md overflow-y-auto" @close="editingId = null">
                <SheetHeader class="pb-4 border-b border-border">
                    <SheetTitle>Chỉnh sửa gói dịch vụ</SheetTitle>
                    <SheetDescription>Cập nhật thông tin chi tiết và hạn mức cho gói {{ form.name }}</SheetDescription>
                </SheetHeader>
                
                <div class="flex flex-col gap-4 py-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2 grid gap-1.5">
                            <Label class="text-xs">Tên gói</Label>
                            <Input v-model="form.name" />
                        </div>
                        <div class="col-span-2 grid gap-1.5">
                            <Label class="text-xs">Mô tả ngắn (hiển thị trên trang khách)</Label>
                            <textarea
                                v-model="form.description"
                                rows="2"
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 resize-none"
                                placeholder="Mô tả ngắn về gói dịch vụ..."
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Giá (VND/tháng)</Label>
                            <Input v-model.number="form.price" type="number" min="0" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Rate limit (req/phút)</Label>
                            <Input v-model.number="form.api_rate_limit" type="number" min="10" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Chi nhánh tối đa (-1 = ∞)</Label>
                            <Input v-model.number="form.max_branches" type="number" min="-1" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Bàn tối đa (-1 = ∞)</Label>
                            <Input v-model.number="form.max_tables" type="number" min="-1" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Nhân viên tối đa (-1 = ∞)</Label>
                            <Input v-model.number="form.max_users" type="number" min="-1" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Khu vực tối đa (-1 = ∞)</Label>
                            <Input v-model.number="form.max_areas" type="number" min="-1" />
                        </div>
                        <div class="col-span-2 grid gap-1.5">
                            <Label class="text-xs">Lưu trữ (MB)</Label>
                            <Input v-model.number="form.max_storage_mb" type="number" min="1" />
                        </div>
                    </div>

                    <!-- Feature toggles — 13 flags -->
                    <div class="rounded-lg border border-border p-3 space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground mb-2">Tính năng được kích hoạt</p>
                        <label v-for="f in ALL_FEATURES" :key="f.key" class="flex items-center justify-between gap-2 cursor-pointer py-0.5">
                            <span class="text-sm">{{ f.label }}</span>
                            <input type="checkbox" v-model="(form as any)[f.key]" class="size-4 rounded accent-primary" />
                        </label>
                    </div>

                    <div class="flex gap-2 pt-2 border-t border-border mt-2">
                        <Button size="sm" @click="editingId && save(editingId)" :disabled="form.processing" class="flex-1">
                            <Save class="size-3.5 mr-1.5" />
                            {{ form.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                        </Button>
                        <Button size="sm" variant="outline" @click="isEditing = false">Hủy</Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>

        <!-- ── CREATE PLAN DIALOG ── -->
        <Dialog :open="isCreating" @update:open="val => { if (!val) { isCreating = false; createForm.reset(); } }">
            <DialogContent class="sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Tạo gói dịch vụ mới</DialogTitle>
                    <DialogDescription>Gói mới sẽ xuất hiện ngay trên trang khách hàng sau khi tạo.</DialogDescription>
                </DialogHeader>

                <form class="flex flex-col gap-4 py-2" @submit.prevent="submitCreate">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Mã gói (code) <span class="text-destructive">*</span></Label>
                            <Input v-model="createForm.code" placeholder="vd: starter" />
                            <p v-if="createForm.errors.code" class="text-xs text-destructive">{{ createForm.errors.code }}</p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Chu kỳ thanh toán <span class="text-destructive">*</span></Label>
                            <Select v-model="createForm.billing_cycle">
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="monthly">Hàng tháng</SelectItem>
                                    <SelectItem value="yearly">Hàng năm</SelectItem>
                                    <SelectItem value="quarterly">Hàng quý</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="col-span-2 grid gap-1.5">
                            <Label class="text-xs">Tên gói <span class="text-destructive">*</span></Label>
                            <Input v-model="createForm.name" placeholder="vd: Gói Khởi Nghiệp" />
                            <p v-if="createForm.errors.name" class="text-xs text-destructive">{{ createForm.errors.name }}</p>
                        </div>
                        <div class="col-span-2 grid gap-1.5">
                            <Label class="text-xs">Mô tả ngắn (hiển thị trang khách)</Label>
                            <textarea
                                v-model="createForm.description"
                                rows="2"
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 resize-none"
                                placeholder="Mô tả ngắn gọn về gói..."
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Giá (VND/tháng) <span class="text-destructive">*</span></Label>
                            <Input v-model.number="createForm.price" type="number" min="0" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Rate limit (req/phút)</Label>
                            <Input v-model.number="createForm.api_rate_limit" type="number" min="10" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Chi nhánh tối đa (-1 = ∞)</Label>
                            <Input v-model.number="createForm.max_branches" type="number" min="-1" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Bàn tối đa (-1 = ∞)</Label>
                            <Input v-model.number="createForm.max_tables" type="number" min="-1" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Nhân viên tối đa (-1 = ∞)</Label>
                            <Input v-model.number="createForm.max_users" type="number" min="-1" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Khu vực tối đa (-1 = ∞)</Label>
                            <Input v-model.number="createForm.max_areas" type="number" min="-1" />
                        </div>
                        <div class="col-span-2 grid gap-1.5">
                            <Label class="text-xs">Lưu trữ (MB)</Label>
                            <Input v-model.number="createForm.max_storage_mb" type="number" min="1" />
                        </div>
                    </div>

                    <div class="rounded-lg border border-border p-3 space-y-1">
                        <p class="text-xs font-semibold text-muted-foreground mb-2">Tính năng được kích hoạt</p>
                        <label v-for="f in ALL_FEATURES" :key="f.key" class="flex items-center justify-between gap-2 cursor-pointer py-0.5">
                            <span class="text-sm">{{ f.label }}</span>
                            <input type="checkbox" v-model="(createForm as any)[f.key]" class="size-4 rounded accent-primary" />
                        </label>
                    </div>

                    <div class="flex gap-2 pt-2 border-t border-border">
                        <Button type="submit" size="sm" :disabled="createForm.processing" class="flex-1">
                            <Save class="size-3.5 mr-1.5" />
                            {{ createForm.processing ? 'Đang tạo...' : 'Tạo gói' }}
                        </Button>
                        <Button type="button" size="sm" variant="outline" @click="isCreating = false; createForm.reset()">Hủy</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ── RESTAURANT DIRECTORY DIALOG ── -->
        <Dialog :open="!!selectedPlanForRestaurants" @update:open="val => { if(!val) selectedPlanForRestaurants = null }">
            <DialogContent class="sm:max-w-2xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Nhà hàng đang dùng gói: {{ selectedPlanForRestaurants?.name }}</DialogTitle>
                    <DialogDescription>Danh sách các doanh nghiệp đang đăng ký gói dịch vụ này.</DialogDescription>
                </DialogHeader>

                <div class="py-4">
                    <div v-if="isLoadingRestaurants" class="flex items-center justify-center py-8">
                        <span class="size-6 rounded-full border-2 border-primary border-t-transparent animate-spin inline-block" />
                    </div>
                    <div v-else-if="restaurants.length === 0" class="text-center py-8 text-sm text-muted-foreground">
                        Không có nhà hàng nào đang sử dụng gói này.
                    </div>
                    <div v-else class="overflow-x-auto rounded-lg border border-border">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-muted border-b border-border text-muted-foreground uppercase font-semibold">
                                    <th class="p-3">Tên Nhà Hàng</th>
                                    <th class="p-3">Mã Code</th>
                                    <th class="p-3">Chủ sở hữu</th>
                                    <th class="p-3">Ngày hết hạn</th>
                                    <th class="p-3 text-right">Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="res in restaurants" :key="res.id" class="border-b border-border last:border-0 hover:bg-muted/30">
                                    <td class="p-3 font-semibold">
                                        <a :href="`/super-admin/restaurants/${res.id}`" class="text-primary hover:underline">
                                            {{ res.name }}
                                        </a>
                                    </td>
                                    <td class="p-3 font-mono text-[10px]">{{ res.code }}</td>
                                    <td class="p-3">
                                        <div>{{ res.owner_name }}</div>
                                        <div class="text-[10px] text-muted-foreground">{{ res.owner_email }}</div>
                                    </td>
                                    <td class="p-3">{{ res.subscription_ends_at }}</td>
                                    <td class="p-3 text-right">
                                        <Badge 
                                            :variant="res.status === 'active' ? 'default' : 'secondary'"
                                            :class="res.status === 'active' ? 'bg-emerald-500 text-white' : ''"
                                        >
                                            {{ res.status }}
                                        </Badge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
