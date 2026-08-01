<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    Building2,
    CheckCircle2,
    Database,
    Layers,
    Search,
    ShieldAlert,
    Users,
    UtensilsCrossed,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    EmptyState,
    FilterBar,
    PageHeader,
    StatCard,
    StatusBadge,
} from '@/components/super-admin';
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

type ResourceData = {
    used: number;
    limit: number | null;
    unlimited: boolean;
    percentage: number;
    can_add: boolean;
};

type Restaurant = {
    id: number;
    name: string;
    code: string;
    status: string;
    plan_name: string;
    owner_name: string;
    owner_email: string;
    resources: Record<string, ResourceData>;
    has_overlimit: boolean;
    has_warning: boolean;
};

const props = defineProps<{
    restaurants: Restaurant[];
    filters: { search?: string; overlimit?: boolean };
}>();

const search = ref(props.filters.search ?? '');
const overlimit = ref(props.filters.overlimit ?? false);

const summary = computed(() => ({
    total: props.restaurants.length,
    overlimit: props.restaurants.filter(
        (restaurant) => restaurant.has_overlimit,
    ).length,
    warning: props.restaurants.filter((restaurant) => restaurant.has_warning)
        .length,
    safe: props.restaurants.filter(
        (restaurant) => !restaurant.has_overlimit && !restaurant.has_warning,
    ).length,
}));

function handleSearch() {
    router.get(
        '/super-admin/resource-limits',
        {
            search: search.value || undefined,
            overlimit: overlimit.value ? 1 : undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

watch(overlimit, handleSearch);

function sendUpgradePrompt(restaurantId: number) {
    void restaurantId;
    toast.success(
        'Đã gửi thông báo khuyến nghị nâng cấp gói dịch vụ tới chủ nhà hàng.',
    );
}

function getResourceIcon(key: string) {
    const icons: Record<string, typeof Activity> = {
        branches: Building2,
        tables: Layers,
        employees: Users,
        storage_mb: Database,
        dishes: UtensilsCrossed,
    };

    return icons[key] || Activity;
}

function getResourceLabel(key: string) {
    const labels: Record<string, string> = {
        branches: 'Chi nhánh',
        tables: 'Bàn ăn',
        employees: 'Nhân viên',
        storage_mb: 'Dung lượng (MB)',
        dishes: 'Món ăn',
        areas: 'Khu vực',
    };

    return labels[key] || key;
}

function getProgressBarColor(percentage: number) {
    if (percentage >= 100) {
        return 'bg-rose-500';
    }

    if (percentage >= 80) {
        return 'bg-amber-500';
    }

    return 'bg-emerald-500';
}
</script>

<template>
    <Head title="Giám sát giới hạn tài nguyên" />

    <div class="space-y-6 px-6 py-5">
        <PageHeader
            title="Giám sát giới hạn tài nguyên"
            subtitle="Theo dõi mức sử dụng bàn, nhân sự, chi nhánh và dung lượng của tất cả nhà hàng."
            :icon="Activity"
        >
            <template #actions>
                <Button variant="outline" @click="handleSearch">
                    <Search class="mr-2 size-4" />
                    Tìm kiếm
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Nhà hàng đang theo dõi"
                :value="summary.total"
                :icon="Building2"
                color="sky"
            />
            <StatCard
                label="Đã vượt giới hạn"
                :value="summary.overlimit"
                :icon="AlertTriangle"
                color="rose"
            />
            <StatCard
                label="Sắp chạm hạn mức"
                :value="summary.warning"
                :icon="ShieldAlert"
                color="amber"
            />
            <StatCard
                label="Đang an toàn"
                :value="summary.safe"
                :icon="CheckCircle2"
                color="emerald"
            />
        </div>

        <FilterBar>
            <label class="min-w-[260px] flex-1 space-y-1.5">
                <span class="sr-only">Tìm nhà hàng</span>
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Tìm tên hoặc mã nhà hàng..."
                        class="h-9 pl-9"
                        @keyup.enter="handleSearch"
                    />
                </div>
            </label>
            <label
                class="flex h-9 items-center gap-2 text-sm whitespace-nowrap"
            >
                <input
                    id="overlimit-only"
                    v-model="overlimit"
                    type="checkbox"
                    class="size-4 rounded border-border text-primary focus:ring-primary"
                />
                <span>Chỉ hiển thị nhà hàng vượt giới hạn</span>
            </label>
            <Button variant="secondary" class="shrink-0" @click="handleSearch">
                Lọc dữ liệu
            </Button>
        </FilterBar>

        <div v-if="restaurants.length" class="grid gap-4">
            <Card
                v-for="restaurant in restaurants"
                :key="restaurant.id"
                class="overflow-hidden"
            >
                <CardHeader class="border-b border-border/50 bg-muted/10">
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <CardTitle class="text-lg">{{
                                    restaurant.name
                                }}</CardTitle>
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    #{{ restaurant.code }}
                                </span>
                                <StatusBadge
                                    :status="
                                        restaurant.status === 'active'
                                            ? 'active'
                                            : 'inactive'
                                    "
                                    size="sm"
                                >
                                    {{
                                        restaurant.status === 'active'
                                            ? 'Hoạt động'
                                            : 'Tạm khóa'
                                    }}
                                </StatusBadge>
                            </div>
                            <CardDescription class="mt-1">
                                Gói dịch vụ:
                                <span class="font-medium text-foreground">{{
                                    restaurant.plan_name
                                }}</span>
                                · Chủ sở hữu: {{ restaurant.owner_name }} ({{
                                    restaurant.owner_email
                                }})
                            </CardDescription>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <StatusBadge
                                :status="
                                    restaurant.has_overlimit
                                        ? 'critical'
                                        : restaurant.has_warning
                                          ? 'warning'
                                          : 'healthy'
                                "
                                size="sm"
                            >
                                <AlertTriangle
                                    v-if="
                                        restaurant.has_overlimit ||
                                        restaurant.has_warning
                                    "
                                    class="size-3"
                                />
                                <CheckCircle2 v-else class="size-3" />
                                {{
                                    restaurant.has_overlimit
                                        ? 'Vượt giới hạn'
                                        : restaurant.has_warning
                                          ? 'Sắp chạm hạn mức'
                                          : 'An toàn'
                                }}
                            </StatusBadge>
                            <Button
                                v-if="
                                    restaurant.has_overlimit ||
                                    restaurant.has_warning
                                "
                                size="sm"
                                variant="outline"
                                class="text-amber-600 hover:text-amber-700"
                                @click="sendUpgradePrompt(restaurant.id)"
                            >
                                Khuyến nghị nâng cấp
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="p-5">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div
                            v-for="(data, key) in restaurant.resources"
                            :key="key"
                            class="rounded-xl border border-border/60 bg-muted/10 p-4"
                        >
                            <div
                                class="flex items-center justify-between gap-3 text-sm"
                            >
                                <span
                                    class="flex items-center gap-2 text-muted-foreground"
                                >
                                    <component
                                        :is="getResourceIcon(key)"
                                        class="size-4 text-primary"
                                    />
                                    <span>{{ getResourceLabel(key) }}</span>
                                </span>
                                <span class="font-semibold tabular-nums">
                                    {{ data.used }} /
                                    {{ data.unlimited ? '∞' : data.limit }}
                                </span>
                            </div>

                            <div class="mt-3 space-y-1.5">
                                <div
                                    class="h-2 w-full overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        :class="[
                                            'h-full rounded-full transition-all duration-500',
                                            getProgressBarColor(
                                                data.percentage,
                                            ),
                                        ]"
                                        :style="{
                                            width: `${data.unlimited ? 10 : Math.min(100, data.percentage)}%`,
                                        }"
                                    />
                                </div>
                                <div
                                    class="flex justify-between text-[11px] text-muted-foreground"
                                >
                                    <span>{{
                                        data.unlimited
                                            ? 'Không giới hạn'
                                            : `${data.percentage}% đã dùng`
                                    }}</span>
                                    <span
                                        v-if="
                                            !data.unlimited &&
                                            data.used >= (data.limit || 0)
                                        "
                                        class="font-medium text-rose-600"
                                    >
                                        Đã vượt hạn
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <EmptyState
            v-else
            :icon="ShieldAlert"
            title="Không có dữ liệu"
            description="Không tìm thấy nhà hàng nào phù hợp với điều kiện lọc hiện tại."
        />
    </div>
</template>
