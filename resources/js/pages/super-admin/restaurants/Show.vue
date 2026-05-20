<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Crown, CheckCircle2, XCircle, Building2, Users, LayoutGrid, Table2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    restaurant: {
        id: number; name: string; code: string; slug: string;
        tax_code: string; phone: string; email: string; address: string;
        status: string; timezone: string; currency: string;
        trial_ends_at: string; subscription_ends_at: string; created_at: string;
        owner: { name: string; email: string };
        plan: { id: number; name: string; code: string };
    };
    quota: {
        plan: string; plan_code: string;
        resources: Record<string, { used: number; limit: number; unlimited: boolean; percentage: number; can_add: boolean }>;
        features: Record<string, boolean>;
        rate_limit: number;
    };
    subscriptions: Array<{ plan: string; status: string; started_at: string; ended_at: string; price: string }>;
    plans: Array<{ id: number; code: string; name: string }>;
}>();

const statusForm = useForm({ status: props.restaurant.status, reason: '' });
function updateStatus() {
    statusForm.patch(`/super-admin/restaurants/${props.restaurant.id}/status`);
}

const planForm = useForm({ plan_id: String(props.restaurant.plan.id) });
function updatePlan() {
    planForm.patch(`/super-admin/restaurants/${props.restaurant.id}/plan`);
}

const statusColor: Record<string, string> = {
    active:    'bg-green-100 text-green-800',
    suspended: 'bg-yellow-100 text-yellow-800',
    expired:   'bg-red-100 text-red-800',
};
const statusLabel: Record<string, string> = { active: 'Hoạt động', suspended: 'Tạm ngưng', expired: 'Hết hạn' };

const resourceIcons: Record<string, any> = {
    branches: Building2, employees: Users, areas: LayoutGrid, tables: Table2,
};
const resourceLabels: Record<string, string> = {
    branches: 'Chi nhánh', employees: 'Nhân viên', areas: 'Khu vực', tables: 'Bàn ăn',
};

function barColor(pct: number, canAdd: boolean) {
    if (!canAdd) {
return 'bg-red-500';
}

    if (pct >= 80) {
return 'bg-yellow-500';
}

    return 'bg-green-500';
}
</script>

<template>
    <Head :title="`${restaurant.name} - Chi tiết`" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <Link href="/super-admin/restaurants">
                <Button variant="ghost" size="icon-sm"><ArrowLeft class="size-4" /></Button>
            </Link>
            <div class="flex-1">
                <h1 class="text-2xl font-bold">{{ restaurant.name }}</h1>
                <p class="text-sm text-muted-foreground font-mono">{{ restaurant.code }}</p>
            </div>
            <span :class="['inline-flex rounded-full px-3 py-1 text-sm font-medium', statusColor[restaurant.status]]">
                {{ statusLabel[restaurant.status] }}
            </span>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Thông tin nhà hàng -->
            <div class="lg:col-span-2 flex flex-col gap-4">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Thông tin doanh nghiệp</CardTitle>
                    </CardHeader>
                    <CardContent class="grid grid-cols-2 gap-4 text-sm">
                        <div><p class="text-muted-foreground">Tên</p><p class="font-medium">{{ restaurant.name }}</p></div>
                        <div><p class="text-muted-foreground">Mã số thuế</p><p class="font-medium">{{ restaurant.tax_code || '—' }}</p></div>
                        <div><p class="text-muted-foreground">Email</p><p class="font-medium">{{ restaurant.email || '—' }}</p></div>
                        <div><p class="text-muted-foreground">Điện thoại</p><p class="font-medium">{{ restaurant.phone || '—' }}</p></div>
                        <div class="col-span-2"><p class="text-muted-foreground">Địa chỉ</p><p class="font-medium">{{ restaurant.address || '—' }}</p></div>
                        <div><p class="text-muted-foreground">Chủ sở hữu</p><p class="font-medium">{{ restaurant.owner.name }}</p></div>
                        <div><p class="text-muted-foreground">Email chủ</p><p class="font-medium">{{ restaurant.owner.email }}</p></div>
                        <div><p class="text-muted-foreground">Múi giờ</p><p class="font-medium">{{ restaurant.timezone }}</p></div>
                        <div><p class="text-muted-foreground">Tiền tệ</p><p class="font-medium">{{ restaurant.currency }}</p></div>
                        <div><p class="text-muted-foreground">Hết thử nghiệm</p><p class="font-medium">{{ restaurant.trial_ends_at || '—' }}</p></div>
                        <div><p class="text-muted-foreground">Hết hạn đăng ký</p><p class="font-medium">{{ restaurant.subscription_ends_at || '—' }}</p></div>
                        <div><p class="text-muted-foreground">Ngày tạo</p><p class="font-medium">{{ restaurant.created_at }}</p></div>
                    </CardContent>
                </Card>

                <!-- Quota Usage -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            Sử dụng tài nguyên
                            <span :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium', quota.plan_code === 'PRO' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700']">
                                <Crown v-if="quota.plan_code === 'PRO'" class="size-3" />
                                {{ quota.plan }}
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="grid grid-cols-2 gap-4">
                        <div v-for="(res, key) in quota.resources" :key="key" class="flex flex-col gap-1.5">
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-1.5 font-medium">
                                    <component :is="resourceIcons[key]" class="size-4 text-muted-foreground" />
                                    {{ resourceLabels[key] ?? key }}
                                </span>
                                <span class="text-muted-foreground text-xs">
                                    {{ res.used }} / {{ res.unlimited ? '∞' : res.limit }}
                                </span>
                            </div>
                            <div class="h-1.5 rounded-full bg-muted overflow-hidden">
                                <div
                                    v-if="!res.unlimited"
                                    :class="['h-full rounded-full transition-all', barColor(res.percentage, res.can_add)]"
                                    :style="{ width: `${res.percentage}%` }"
                                />
                                <div v-else class="h-full w-full rounded-full bg-green-500 opacity-30" />
                            </div>
                            <p v-if="!res.can_add" class="text-xs text-red-600 flex items-center gap-1">
                                <XCircle class="size-3" /> Đã đạt giới hạn
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Tính năng -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Tính năng gói</CardTitle>
                    </CardHeader>
                    <CardContent class="grid grid-cols-3 gap-3 text-sm">
                        <div v-for="(enabled, feature) in quota.features" :key="feature"
                            :class="['flex items-center gap-2 rounded-lg border p-3', enabled ? 'border-green-200 bg-green-50 dark:bg-green-900/20' : 'opacity-50']">
                            <CheckCircle2 v-if="enabled" class="size-4 text-green-600 shrink-0" />
                            <XCircle v-else class="size-4 text-muted-foreground shrink-0" />
                            <span class="text-xs capitalize">{{ String(feature).replace(/_/g, ' ') }}</span>
                        </div>
                        <div class="flex items-center gap-2 rounded-lg border p-3 border-blue-200 bg-blue-50 dark:bg-blue-900/20">
                            <span class="text-xs font-mono font-bold text-blue-600">{{ quota.rate_limit }}</span>
                            <span class="text-xs">req/phút</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Sidebar: Controls + Subscriptions -->
            <div class="flex flex-col gap-4">
                <!-- Đổi trạng thái -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Trạng thái tài khoản</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="updateStatus" class="flex flex-col gap-3">
                            <select v-model="statusForm.status" class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                                <option value="active">✅ Kích hoạt</option>
                                <option value="suspended">⏸ Tạm ngưng</option>
                                <option value="expired">❌ Hết hạn</option>
                            </select>
                            <Button type="submit" :disabled="statusForm.processing" size="sm" class="w-full">
                                {{ statusForm.processing ? 'Đang lưu...' : 'Cập nhật trạng thái' }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <!-- Đổi gói -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Gói dịch vụ</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="updatePlan" class="flex flex-col gap-3">
                            <select v-model="planForm.plan_id" class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                                <option v-for="p in plans" :key="p.id" :value="String(p.id)">
                                    {{ p.name }}
                                </option>
                            </select>
                            <Button type="submit" :disabled="planForm.processing" size="sm" variant="outline" class="w-full">
                                {{ planForm.processing ? 'Đang lưu...' : 'Đổi gói' }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <!-- Lịch sử đăng ký -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">Lịch sử đăng ký</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2">
                        <div v-for="s in subscriptions" :key="s.started_at"
                            class="rounded border p-2.5 text-xs flex flex-col gap-1">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ s.plan }}</span>
                                <span :class="['rounded-full px-1.5 py-0.5', s.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-muted text-muted-foreground']">
                                    {{ s.status }}
                                </span>
                            </div>
                            <span class="text-muted-foreground">{{ s.started_at }} → {{ s.ended_at }}</span>
                            <span class="font-mono">{{ s.price }} VND</span>
                        </div>
                        <p v-if="!subscriptions.length" class="text-xs text-muted-foreground text-center py-2">
                            Chưa có lịch sử
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
