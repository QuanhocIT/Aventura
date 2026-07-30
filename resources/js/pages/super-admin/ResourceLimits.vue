<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Search,
    ShieldAlert,
    Building2,
    Users,
    FolderGit2,
    Database,
    Tag,
    Trash2,
    Layers,
    UtensilsCrossed,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    restaurants: Array<{
        id: number;
        name: string;
        code: string;
        status: string;
        plan_name: string;
        owner_name: string;
        owner_email: string;
        resources: Record<string, {
            used: number;
            limit: number | null;
            unlimited: boolean;
            percentage: number;
            can_add: boolean;
        }>;
        has_overlimit: boolean;
        has_warning: boolean;
    }>;
    filters: { search?: string; overlimit?: boolean };
}>();

const search = ref(props.filters.search ?? '');
const overlimit = ref(props.filters.overlimit ?? false);

const handleSearch = () => {
    router.get(
        '/super-admin/resource-limits',
        { search: search.value, overlimit: overlimit.value ? 1 : 0 },
        { preserveState: true, replace: true }
    );
};

// Watch overlimit checkbox to auto-trigger search
watch(overlimit, () => {
    handleSearch();
});

const sendUpgradePrompt = (restaurantId: number) => {
    toast.success('Đã gửi thông báo khuyến nghị nâng cấp gói dịch vụ tới chủ nhà hàng!');
};

// Helper for resource icon
const getResourceIcon = (key: string) => {
    switch (key) {
        case 'branches': return Building2;
        case 'tables': return Layers;
        case 'employees': return Users;
        case 'storage_mb': return Database;
        case 'dishes': return UtensilsCrossed;
        default: return Activity;
    }
};

const getResourceLabel = (key: string) => {
    switch (key) {
        case 'branches': return 'Chi nhánh';
        case 'tables': return 'Bàn ăn';
        case 'employees': return 'Nhân viên';
        case 'storage_mb': return 'Dung lượng (MB)';
        case 'dishes': return 'Món ăn';
        case 'areas': return 'Khu vực';
        default: return key;
    }
};

const getProgressBarColor = (percentage: number) => {
    if (percentage >= 100) {
return 'bg-red-500';
}

    if (percentage >= 80) {
return 'bg-amber-500';
}

    return 'bg-emerald-500';
};
</script>

<template>
    <Head title="Giám sát giới hạn tài nguyên SaaS" />

    <div class="space-y-6 p-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Giám sát giới hạn tài nguyên</h1>
            <p class="text-muted-foreground">
                Theo dõi tình hình sử dụng tài nguyên (bàn, nhân sự, chi nhánh, lưu trữ) của tất cả các Tenant.
            </p>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 items-center space-x-2">
                <div class="relative w-full max-w-sm">
                    <Search class="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Tìm tên hoặc mã nhà hàng..."
                        class="pl-8"
                        @keyup.enter="handleSearch"
                    />
                </div>
                <Button variant="secondary" @click="handleSearch">Tìm kiếm</Button>
            </div>

            <div class="flex items-center space-x-2">
                <input
                    id="overlimit-only"
                    v-model="overlimit"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                />
                <label for="overlimit-only" class="text-sm font-medium text-gray-300">
                    Chỉ hiển thị Tenant vượt giới hạn
                </label>
            </div>
        </div>

        <div class="grid gap-6">
            <Card v-for="restaurant in restaurants" :key="restaurant.id" class="overflow-hidden border border-slate-800 bg-slate-900/50">
                <CardHeader class="flex flex-row items-start justify-between space-y-0 bg-slate-900/80 p-4">
                    <div>
                        <div class="flex items-center space-x-2">
                            <CardTitle class="text-lg font-semibold text-white">{{ restaurant.name }}</CardTitle>
                            <span class="text-xs text-slate-450 font-mono">#{{ restaurant.code }}</span>
                            <span
                                :class="[
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                    restaurant.status === 'active' ? 'bg-emerald-500/10 text-emerald-450 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-450 border border-amber-500/20'
                                ]"
                            >
                                {{ restaurant.status === 'active' ? 'Hoạt động' : 'Tạm khóa' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-450 mt-1">
                            Gói cước: <span class="font-semibold text-slate-200">{{ restaurant.plan_name }}</span> | Chủ sở hữu: {{ restaurant.owner_name }} ({{ restaurant.owner_email }})
                        </p>
                    </div>

                    <div class="flex items-center space-x-2">
                        <span v-if="restaurant.has_overlimit" class="inline-flex items-center space-x-1 rounded-md bg-red-500/10 px-2.5 py-1 text-xs font-semibold text-red-400 border border-red-500/20">
                            <AlertTriangle class="h-3.5 w-3.5" />
                            <span>Vượt giới hạn</span>
                        </span>
                        <span v-else-if="restaurant.has_warning" class="inline-flex items-center space-x-1 rounded-md bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-400 border border-amber-500/20">
                            <AlertTriangle class="h-3.5 w-3.5" />
                            <span>Sắp chạm hạn mức</span>
                        </span>
                        <span v-else class="inline-flex items-center space-x-1 rounded-md bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-400 border border-emerald-500/20">
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            <span>An toàn</span>
                        </span>

                        <Button
                            v-if="restaurant.has_overlimit || restaurant.has_warning"
                            size="sm"
                            variant="destructive"
                            class="h-8"
                            @click="sendUpgradePrompt(restaurant.id)"
                        >
                            Khuyến nghị nâng cấp
                        </Button>
                    </div>
                </CardHeader>

                <CardContent class="p-6">
                    <div class="grid gap-6 md:grid-cols-3">
                        <div
                            v-for="(data, key) in restaurant.resources"
                            :key="key"
                            class="rounded-lg border border-slate-800/80 bg-slate-950/40 p-4 space-y-3"
                        >
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center space-x-2 text-slate-350">
                                    <component :is="getResourceIcon(key)" class="h-4 w-4 text-slate-400" />
                                    <span>{{ getResourceLabel(key) }}</span>
                                </span>
                                <span class="font-semibold text-slate-200">
                                    {{ data.used }} / {{ data.unlimited ? '∞' : data.limit }}
                                </span>
                            </div>

                            <div class="space-y-1">
                                <div class="h-2 w-full rounded-full bg-slate-800 overflow-hidden">
                                    <div
                                        :class="['h-full transition-all duration-500', getProgressBarColor(data.percentage)]"
                                        :style="{ width: `${data.unlimited ? 10 : Math.min(100, data.percentage)}%` }"
                                    ></div>
                                </div>
                                <div class="flex justify-between text-[11px] text-slate-450">
                                    <span>{{ data.unlimited ? 'Không giới hạn' : `${data.percentage}% đã dùng` }}</span>
                                    <span v-if="!data.unlimited && data.used >= (data.limit || 0)" class="text-red-400 font-medium">Over limit!</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div v-if="restaurants.length === 0" class="flex flex-col items-center justify-center p-12 text-center border border-slate-800 rounded-lg bg-slate-900/20">
                <ShieldAlert class="h-10 w-10 text-slate-500 mb-4" />
                <h3 class="text-lg font-semibold text-slate-300">Không có dữ liệu</h3>
                <p class="text-sm text-slate-500 mt-1">Không tìm thấy nhà hàng nào khớp với điều kiện lọc hiện tại.</p>
            </div>
        </div>
    </div>
</template>
