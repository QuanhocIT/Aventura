<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Check, Crown, Edit2, Save, Star, Users, X, Zap } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface Plan {
    id: number; code: string; name: string; price: number;
    billing_cycle: string; max_branches: number; max_tables: number; max_users: number;
    features: Record<string, any>; status: string; restaurants_count: number;
}

const props = defineProps<{ plans: Plan[] }>();

const editingId = ref<number | null>(null);

const form = useForm({
    name: '', description: '',
    price: 0,
    max_branches: 1, max_tables: 10, max_users: 5,
    max_areas: 2, max_storage_mb: 500,
    ai_features: false, realtime: false, advanced_analytics: false,
    api_rate_limit: 60,
});

const toForm = (v: number | null) => (v === null ? -1 : v);

function startEdit(plan: Plan) {
    editingId.value = plan.id;
    form.name               = plan.name;
    form.description        = plan.features?.description ?? planNotes[plan.code] ?? '';
    form.price              = plan.price;
    form.max_branches       = toForm(plan.max_branches);
    form.max_tables         = toForm(plan.max_tables);
    form.max_users          = toForm(plan.max_users);
    form.max_areas          = plan.features?.max_areas ?? 2;
    form.max_storage_mb     = plan.features?.max_storage_mb ?? 500;
    form.ai_features        = plan.features?.ai_features ?? false;
    form.realtime           = plan.features?.realtime ?? false;
    form.advanced_analytics = plan.features?.advanced_analytics ?? false;
    form.api_rate_limit     = plan.features?.api_rate_limit ?? 60;
}

function save(planId: number) {
    form.patch(`/super-admin/plans/${planId}`, {
        onSuccess: () => { editingId.value = null; },
    });
}

// Build the customer-facing feature list from plan data (mirrors Khach.vue logic)
function planFeatures(plan: Plan): string[] {
    const lim = (v: number | null, unit: string) =>
        v === null || v === -1 ? `Không giới hạn ${unit}` : `${v} ${unit}`;
    const mb  = plan.features?.max_storage_mb ?? 500;
    const rate = plan.features?.api_rate_limit ?? 60;

    const list = [
        lim(plan.max_branches, 'chi nhánh'),
        lim(plan.max_tables, 'bàn'),
        lim(plan.max_users, 'nhân viên'),
        mb >= 1024 ? `${mb / 1024} GB lưu trữ` : `${mb} MB lưu trữ`,
        `Rate limit: ${new Intl.NumberFormat('vi-VN').format(rate)}/phút`,
    ];
    if (plan.features?.ai_features)        list.push('AI dự báo nguyên liệu & tồn kho');
    if (plan.features?.ai_features)        list.push('Thuật toán AI phát hiện gian lận');
    if (plan.features?.realtime)           list.push('Realtime sync & Advanced Analytics');
    if (plan.features?.advanced_analytics) list.push('Hệ thống Audit Log bảo mật');
    return list;
}

function planUnsupported(plan: Plan): string[] {
    const list: string[] = [];
    if (!plan.features?.ai_features)        list.push('AI dự báo nguyên liệu & tồn kho', 'Thuật toán AI phát hiện gian lận');
    if (!plan.features?.realtime)           list.push('Realtime sync & Advanced Analytics');
    if (!plan.features?.advanced_analytics) list.push('Hệ thống Audit Log bảo mật');
    return list;
}

function formatVnd(v: number) {
    return v === 0 ? '0đ' : new Intl.NumberFormat('vi-VN').format(v) + 'đ';
}

const planNotes: Record<string, string> = {
    free:  'Gói cơ bản trải nghiệm miễn phí.',
    pro:   'Tối ưu hiệu năng, chống thất thoát cho mô hình chuyên nghiệp.',
    max:   'Phù hợp cho chuỗi nhà hàng vừa và lớn.',
    ultra: 'Giải pháp tối thượng cho doanh nghiệp lớn & chuỗi rộng khắp.',
};

const planIcon: Record<string, any> = {
    free: Star, pro: Crown, max: Zap, ultra: Users,
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
            <Badge variant="outline" class="gap-1.5">
                <span class="size-2 rounded-full bg-green-500 animate-pulse inline-block" />
                Đồng bộ trang khách
            </Badge>
        </div>

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            <div v-for="plan in plans" :key="plan.id" class="flex flex-col gap-0">

                <!-- ── VIEW MODE: giống trang khách hàng ── -->
                <template v-if="editingId !== plan.id">
                    <Card
                        class="flex flex-col justify-between border-border transition-all duration-200 hover:shadow-md h-full"
                        :class="{
                            'border-2 border-primary shadow-sm': plan.code === 'pro',
                            'border-2 border-violet-500/80 bg-gradient-to-b from-violet-500/5 to-transparent': plan.code === 'ultra',
                        }"
                    >
                        <CardHeader>
                            <div class="mb-2 flex items-center justify-between">
                                <CardTitle
                                    class="flex items-center gap-1.5 text-2xl font-bold"
                                    :class="{
                                        'text-primary': plan.code === 'pro',
                                        'text-violet-500': plan.code === 'ultra',
                                    }"
                                >
                                    <component :is="planIcon[plan.code] ?? Star" class="size-5" />
                                    {{ plan.name }}
                                </CardTitle>
                                <div class="flex items-center gap-1">
                                    <Badge v-if="plan.code === 'free'" variant="secondary">Mặc định</Badge>
                                    <Badge v-else-if="plan.code === 'pro'">Khuyến nghị</Badge>
                                    <Badge v-else-if="plan.code === 'ultra'" class="bg-violet-600 text-white">VIP</Badge>
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
                                        'text-violet-500': plan.code === 'ultra',
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
                                        'text-violet-500': plan.code === 'ultra',
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
                            <p class="text-xs text-muted-foreground flex items-center justify-between">
                                <span>{{ plan.restaurants_count }} nhà hàng đang dùng</span>
                                <span class="font-mono text-[10px] bg-muted px-1.5 py-0.5 rounded">{{ plan.code }}</span>
                            </p>
                        </div>
                    </Card>
                </template>

                <!-- ── EDIT MODE ── -->
                <template v-else>
                    <Card class="border-2 border-primary/40">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-base">Chỉnh sửa · {{ plan.name }}</CardTitle>
                                <Button variant="ghost" size="icon" class="size-7" @click="editingId = null">
                                    <X class="size-4" />
                                </Button>
                            </div>
                        </CardHeader>

                        <CardContent class="flex flex-col gap-4">
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
                                <div class="grid gap-1.5">
                                    <Label class="text-xs">Lưu trữ (MB)</Label>
                                    <Input v-model.number="form.max_storage_mb" type="number" min="1" />
                                </div>
                            </div>

                            <!-- Feature toggles - styled -->
                            <div class="rounded-lg border border-border p-3 space-y-2">
                                <p class="text-xs font-semibold text-muted-foreground mb-2">Tính năng nâng cao</p>
                                <label class="flex items-center justify-between gap-2 cursor-pointer">
                                    <span class="text-sm">AI dự báo & phát hiện gian lận</span>
                                    <input type="checkbox" v-model="form.ai_features" class="size-4 rounded accent-primary" />
                                </label>
                                <label class="flex items-center justify-between gap-2 cursor-pointer">
                                    <span class="text-sm">Realtime sync</span>
                                    <input type="checkbox" v-model="form.realtime" class="size-4 rounded accent-primary" />
                                </label>
                                <label class="flex items-center justify-between gap-2 cursor-pointer">
                                    <span class="text-sm">Phân tích nâng cao & Audit Log</span>
                                    <input type="checkbox" v-model="form.advanced_analytics" class="size-4 rounded accent-primary" />
                                </label>
                            </div>

                            <div class="flex gap-2">
                                <Button size="sm" @click="save(plan.id)" :disabled="form.processing" class="flex-1">
                                    <Save class="size-3.5 mr-1.5" />
                                    {{ form.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                                </Button>
                                <Button size="sm" variant="outline" @click="editingId = null">Hủy</Button>
                            </div>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>

        <!-- Info note -->
        <p class="text-xs text-muted-foreground text-center">
            Thay đổi giá hoặc tính năng sẽ ảnh hưởng ngay đến trang khách hàng — không cần deploy lại.
        </p>
    </div>
</template>
