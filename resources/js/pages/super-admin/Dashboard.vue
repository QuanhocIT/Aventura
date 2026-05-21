<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Building2, Users, ShieldCheck, FileText,
    CheckCircle2, XCircle, Clock, Crown,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout-clean.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    stats: {
        total_restaurants: number;
        active: number;
        suspended: number;
        expired: number;
        total_users: number;
        pro_plan: number;
    };
    recentRestaurants: Array<{
        id: number;
        name: string;
        status: string;
        plan: string;
        plan_code: string;
        owner: string;
        created_at: string;
    }>;
    planDistribution: Array<{ name: string; code: string; count: number }>;
}>();

const statusColor: Record<string, string> = {
    active:    'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    suspended: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    expired:   'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
};
const statusLabel: Record<string, string> = {
    active: 'Hoạt động', suspended: 'Tạm ngưng', expired: 'Hết hạn',
};

const statCards = [
    { label: 'Tổng nhà hàng',  value: props.stats.total_restaurants, icon: Building2,    color: 'text-blue-600' },
    { label: 'Đang hoạt động', value: props.stats.active,            icon: CheckCircle2, color: 'text-green-600' },
    { label: 'Gói Cao cấp',   value: props.stats.pro_plan,           icon: Crown,        color: 'text-purple-600' },
    { label: 'Tổng người dùng', value: props.stats.total_users,      icon: Users,        color: 'text-orange-600' },
];
</script>

<template>
    <Head title="Super Admin Dashboard" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Bảng điều khiển</h1>
                <p class="text-sm text-muted-foreground">Tổng quan hệ thống Aventura</p>
            </div>
            <div class="flex gap-2">
                <Link
                    href="/super-admin/restaurants"
                    class="inline-flex items-center gap-2 rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted transition-colors"
                >
                    <Building2 class="size-4" />
                    Nhà hàng
                </Link>
                <Link
                    href="/super-admin/accounts"
                    class="inline-flex items-center gap-2 rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted transition-colors"
                >
                    <ShieldCheck class="size-4" />
                    Tài khoản & Bảo mật
                </Link>
                <Link
                    href="/super-admin/audit-logs"
                    class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                >
                    <FileText class="size-4" />
                    Audit Log
                </Link>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <Card v-for="card in statCards" :key="card.label">
                <CardContent class="flex items-center justify-between p-6">
                    <div>
                        <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                        <p class="text-3xl font-bold">{{ card.value }}</p>
                    </div>
                    <component :is="card.icon" :class="['size-10 opacity-80', card.color]" />
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Recent Restaurants -->
            <Card class="lg:col-span-2">
                <CardHeader class="flex-row items-center justify-between pb-2">
                    <CardTitle class="text-base">Nhà hàng mới nhất</CardTitle>
                    <Link href="/super-admin/restaurants" class="text-xs text-muted-foreground hover:text-foreground">
                        Xem tất cả →
                    </Link>
                </CardHeader>
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead class="border-b">
                            <tr class="text-left text-xs text-muted-foreground">
                                <th class="px-6 py-3 font-medium">Tên</th>
                                <th class="px-4 py-3 font-medium">Gói</th>
                                <th class="px-4 py-3 font-medium">Trạng thái</th>
                                <th class="px-4 py-3 font-medium">Ngày tạo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="r in recentRestaurants"
                                :key="r.id"
                                class="border-b last:border-0 hover:bg-muted/50 transition-colors"
                            >
                                <td class="px-6 py-3">
                                    <Link :href="`/super-admin/restaurants/${r.id}`" class="font-medium hover:underline">
                                        {{ r.name }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">{{ r.owner }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="r.plan_code === 'PRO' ? 'text-purple-600 font-semibold' : 'text-muted-foreground'">
                                        {{ r.plan }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', statusColor[r.status]]">
                                        {{ statusLabel[r.status] ?? r.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ r.created_at }}</td>
                            </tr>
                            <tr v-if="!recentRestaurants.length">
                                <td colspan="4" class="px-6 py-8 text-center text-muted-foreground">
                                    Chưa có nhà hàng nào
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <!-- Plan Distribution + Quick Stats -->
            <div class="flex flex-col gap-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">Phân bổ gói dịch vụ</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3">
                        <div v-for="p in planDistribution" :key="p.code" class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Crown v-if="p.code === 'PRO'" class="size-4 text-purple-500" />
                                <Building2 v-else class="size-4 text-blue-500" />
                                <span class="text-sm font-medium">{{ p.name }}</span>
                            </div>
                            <span class="text-sm font-bold">{{ p.count }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">Trạng thái hệ thống</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-1.5"><CheckCircle2 class="size-4 text-green-500" /> Hoạt động</span>
                            <span class="font-bold text-green-600">{{ stats.active }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-1.5"><Clock class="size-4 text-yellow-500" /> Tạm ngưng</span>
                            <span class="font-bold text-yellow-600">{{ stats.suspended }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-1.5"><XCircle class="size-4 text-red-500" /> Hết hạn</span>
                            <span class="font-bold text-red-600">{{ stats.expired }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
